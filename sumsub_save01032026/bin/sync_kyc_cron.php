#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * sync_kyc_cron.php — Resynchronise KYC/AML pour tous les applicants.
 * Usage (CLI): php sumsub/bin/sync_kyc_cron.php
 */

const BATCH_SIZE = 100;
const MAX_RETRIES = 5;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';

$cfg = require __DIR__ . '/../config/secrets.php';
$db  = require __DIR__ . '/../config/db.php';

// 1) Connexions
$pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: ($cfg['SUMSUB_APP_TOKEN']  ?? '');
$appSecret = getenv('SUMSUB_APP_SECRET') ?: ($cfg['SUMSUB_APP_SECRET'] ?? '');
if (!$appToken || !$appSecret) {
  fwrite(STDERR, "[FATAL] Missing SUMSUB credentials\n");
  exit(1);
}

$client = new SumsubClient($appToken, $appSecret);

// 2) Pagination applicants
$offset = 0;
$totalUpdated = 0;

do {
  $stmt = $pdo->prepare("
    SELECT id, applicant_id, external_user_id, last_status, last_answer, last_reviewed_at
    FROM kyc_applicants
    ORDER BY id ASC
    LIMIT :limit OFFSET :offset
  ");
  $stmt->bindValue(':limit',  BATCH_SIZE, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
  $stmt->execute();

  $rows = $stmt->fetchAll();
  if (!$rows) break;

  foreach ($rows as $row) {
    $aid = $row['applicant_id'];
    $retries = 0;
    while (true) {
      try {
        // 3) KYC
        $status = $client->getApplicantStatus($aid);          // reviewStatus, reviewAnswer, reviewedAt
        // 4) AML
        $verif  = $client->getApplicantVerifications($aid);   // tableau de checks

        // Extraction AML par type
        $find = function(array $verifs, string $type): ?array {
          foreach ($verifs as $v) {
            if (isset($v['checkType']) && strtoupper((string)$v['checkType']) === strtoupper($type)) return $v;
          }
          return null;
        };
        $amlSan = $find($verif, 'SANCTIONS');
        $amlPep = $find($verif, 'PEP');
        $amlAdv = $find($verif, 'ADVERSE_MEDIA');

        // 5) Détecter changements KYC
        $newStatus = $status['reviewStatus'] ?? null;
        $newAnswer = $status['reviewAnswer'] ?? null;
        $newReviewedAt = $status['reviewedAt'] ?? null;

        // Historiser si changement KYC
        if ($newStatus !== $row['last_status'] || $newAnswer !== $row['last_answer']) {
          $evtStmt = $pdo->prepare("
            INSERT INTO kyc_applicant_events (applicant_id, event_type, payload)
            VALUES (:aid, 'KYC_STATUS', :payload)
          ");
          $evtStmt->execute([
            ':aid'     => $aid,
            ':payload' => json_encode($status, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
          ]);
        }

        // Historiser AML (toujours, ou seulement si result change — à votre choix)
        $insEvt = $pdo->prepare("
          INSERT INTO kyc_applicant_events (applicant_id, event_type, payload)
          VALUES (:aid, :type, :payload)
        ");
        if ($amlSan) {
          $insEvt->execute([
            ':aid' => $aid, ':type' => 'AML_SANCTIONS',
            ':payload' => json_encode($amlSan, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
          ]);
        }
        if ($amlPep) {
          $insEvt->execute([
            ':aid' => $aid, ':type' => 'AML_PEP',
            ':payload' => json_encode($amlPep, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
          ]);
        }
        if ($amlAdv) {
          $insEvt->execute([
            ':aid' => $aid, ':type' => 'AML_ADVERSE',
            ':payload' => json_encode($amlAdv, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
          ]);
        }

        // 6) Upsert colonnes “last_*”
        $up = $pdo->prepare("
          UPDATE kyc_applicants
          SET last_status = :s, last_answer = :a, last_reviewed_at = :r,
              last_aml_sanctions = :san, last_aml_pep = :pep, last_aml_adverse = :adv,
              updated_at = NOW()
          WHERE applicant_id = :aid
        ");
        $up->execute([
          ':s'   => $newStatus,
          ':a'   => $newAnswer,
          ':r'   => $newReviewedAt ? date('Y-m-d H:i:s', strtotime($newReviewedAt)) : null,
          ':san' => $amlSan ? json_encode($amlSan, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null,
          ':pep' => $amlPep ? json_encode($amlPep, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null,
          ':adv' => $amlAdv ? json_encode($amlAdv, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null,
          ':aid' => $aid,
        ]);

        $totalUpdated++;
        // petit sleep pour être “nice” avec l’API
        usleep(50_000); // 50ms
        break;

      } catch (SumsubApiException $e) {
        // 429/5xx : backoff exponentiel
        if (in_array($e->statusCode, [429,500,502,503,504], true) && $retries < MAX_RETRIES) {
          $delayMs = (int)(pow(2, $retries) * 250 + random_int(0, 150));
          usleep($delayMs * 1000);
          $retries++;
          continue;
        }
        // 404: applicant supprimé/archivé → on log et on continue
        if ($e->statusCode === 404) {
          fwrite(STDERR, "[WARN] 404 for applicant {$aid}\n");
          break;
        }
        // Autres erreurs: log et continue
        fwrite(STDERR, "[ERROR] {$e->getMessage()}\n");
        break;
      } catch (\Throwable $e) {
        fwrite(STDERR, "[ERROR] ".$e->getMessage()."\n");
        break;
      }
    }
  }

  $offset += BATCH_SIZE;
} while (count($rows) === BATCH_SIZE);

echo "[OK] Updated: {$totalUpdated}\n";
