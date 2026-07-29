<?php
declare(strict_types=1);

/**
 * start_kyc.php (OVH mutualisé, Option A)
 * - Résout applicantId depuis ta DB (source de vérité)
 * - Sinon crée l'applicant via POST (pas de GET externalUserId => évite le 403)
 * - Génère un lien WebSDK “frais” (redirect ou iframe)
 *
 * Params:
 *   ?user=...       (string conseillé)
 *   ?mode=iframe    (ou redirect, default=redirect)
 *   ?level=...      (fallback SUMSUB_LEVEL)
 *   ?email=...&phone=...
 *   ?debug=1
 */

if (isset($_GET['debug'])) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

session_start();

/* ===== Helpers ===== */
function hp(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function abort_html(int $code, string $message): void {
  http_response_code($code);
  header('Content-Type: text/html; charset=utf-8');
  echo "<!doctype html><meta charset='utf-8'><h1>Error</h1><pre>".hp($message)."</pre>";
  exit;
}

/** Normalise externalUserId (évite / et autres chars) */
function normalizeExternalUserId(string $id): string {
  $id = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', $id);
  return substr($id, 0, 128);
}

/* ===== DB config (OVH friendly) =====
 * Supporte:
 * - ../config/db.php  (format direct: ['dsn'=>..., 'user'=>..., 'pass'=>...])
 * - ../config/config.php (format: ['db'=>['dsn'=>..., 'user'=>..., 'pass'=>...]])
 */
$db = null;

$dbCfg1 = __DIR__ . '/../config/db.php';
$dbCfg2 = __DIR__ . '/../config/config.php';

if (is_file($dbCfg1)) {
  $tmp = require $dbCfg1;
  if (is_array($tmp) && !empty($tmp['dsn'])) $db = $tmp;
}

if (!$db && is_file($dbCfg2)) {
  $tmp = require $dbCfg2;
  if (is_array($tmp) && isset($tmp['db']) && is_array($tmp['db']) && !empty($tmp['db']['dsn'])) {
    $db = $tmp['db'];
  }
}

if (!$db || empty($db['dsn'])) {
  abort_html(500, "DB config missing.\nExpected config/db.php or config/config.php (with db.dsn).");
}

/* ===== DB connect ===== */
try {
  $pdo = new PDO($db['dsn'], (string)($db['user'] ?? ''), (string)($db['pass'] ?? ''), [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);
} catch (Throwable $e) {
  abort_html(500, 'DB connect error: ' . $e->getMessage());
}

/* ===== Includes ===== */
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
  abort_html(500, "Missing vendor/autoload.php.\nRun composer install locally and upload vendor/ to OVH.");
}
require $autoload;

$sumsubClientFile = __DIR__ . '/../src/SumsubClient.php';
if (!is_file($sumsubClientFile)) {
  abort_html(500, "Missing src/SumsubClient.php");
}
require $sumsubClientFile;

/* ===== Secrets ===== */
$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
  $cfg2 = require $configFile;
  if (is_array($cfg2)) {
    $_SERVER['SUMSUB_APP_TOKEN']  = $cfg2['SUMSUB_APP_TOKEN']  ?? ($_SERVER['SUMSUB_APP_TOKEN']  ?? null);
    $_SERVER['SUMSUB_APP_SECRET'] = $cfg2['SUMSUB_APP_SECRET'] ?? ($_SERVER['SUMSUB_APP_SECRET'] ?? null);
    $_SERVER['SUMSUB_LEVEL']      = $cfg2['SUMSUB_LEVEL']      ?? ($_SERVER['SUMSUB_LEVEL']      ?? null);
  }
}

$appToken  = $_SERVER['SUMSUB_APP_TOKEN']  ?? null;
$appSecret = $_SERVER['SUMSUB_APP_SECRET'] ?? null;
$defaultLevel = $_SERVER['SUMSUB_LEVEL'] ?? 'leveltookle';

if (!$appToken || !$appSecret) {
  abort_html(500, 'SUMSUB_APP_TOKEN / SUMSUB_APP_SECRET missing (config/secrets.php or env).');
}

/* ===== Params ===== */
$mode  = $_GET['mode'] ?? 'redirect';
$level = (string)($_GET['level'] ?? $defaultLevel);
$email = isset($_GET['email']) ? (string)$_GET['email'] : null;
$phone = isset($_GET['phone']) ? (string)$_GET['phone'] : null;

/* external_user_id : query > session > guest */
if (!empty($_GET['user'])) {
  $externalUserId = normalizeExternalUserId((string)$_GET['user']);
} elseif (!empty($_SESSION['user_id'])) {
  $externalUserId = normalizeExternalUserId('user_' . (string)$_SESSION['user_id']);
} else {
  $externalUserId = 'guest_' . bin2hex(random_bytes(6));
}

$__debug = '';
if (isset($_GET['debug'])) {
  $__debug .= "== DEBUG start_kyc.php ==\n";
  $__debug .= "levelName      = {$level}\n";
  $__debug .= "externalUserId = {$externalUserId}\n";
  $__debug .= "has_email      = " . (int)!!$email . "\n";
  $__debug .= "has_phone      = " . (int)!!$phone . "\n";
}

/* ===== Sumsub client ===== */
$client = new SumsubClient($appToken, $appSecret);

try {
  // 1) Chercher applicantId en DB (source de vérité) — évite tout GET Sumsub par externalUserId
  $applicantId = null;

  $stmt = $pdo->prepare("SELECT applicant_id FROM kyc_applicants WHERE external_user_id = :ext LIMIT 1");
  $stmt->execute([':ext' => $externalUserId]);
  $row = $stmt->fetch();
  if ($row && !empty($row['applicant_id'])) {
    $applicantId = (string)$row['applicant_id'];
  }

  // 2) Si absent en DB : créer via POST /resources/applicants
  if (!$applicantId) {
    // Compatibilité: 2 signatures possibles selon ton SumsubClient.php
    // - save: createApplicant(array $payload, string $levelName)
    // - new : createApplicant(string $extUserId, ?string $email, ?string $phone)
    $rm = new ReflectionMethod($client, 'createApplicant');
$params = $rm->getParameters();

// Si le 1er param s'appelle extUserId (ou est typé string), on est sur la signature "nouvelle"
$firstName = $params[0]->getName() ?? '';
$firstType = $params[0]->hasType() ? (string)$params[0]->getType() : '';

if ($firstName === 'extUserId' || $firstType === 'string') {
    // signature: createApplicant(string $extUserId, ?string $email=null, ?string $phone=null)
    $created = $client->createApplicantWithLevel($externalUserId, $level, $email ?: null, $phone ?: null);

} else {
    // signature legacy: createApplicant(array $payload, string $levelName)
    $payload = [
        'externalUserId' => $externalUserId,
        'fixedInfo'      => ['country' => 'FRA'],
    ];
    if ($email) $payload['email'] = $email;
    if ($phone) $payload['phone'] = $phone;

    $created = $client->createApplicant($payload, $level);
}


    $applicantId = $created['id'] ?? null;
    if (!$applicantId) {
      throw new RuntimeException('Failed to create applicant (missing id in response).');
    }
  }

  if (!$applicantId) {
    throw new RuntimeException('No applicantId resolved or created.');
  }

  // 3) Session
  $_SESSION['sumsub_applicant_id']  = $applicantId;
  $_SESSION['sumsub_external_user'] = $externalUserId;

  // 4) UPSERT DB (table: kyc_applicants, PK applicant_id, UNIQUE external_user_id)
  $sql = "
    INSERT INTO kyc_applicants (applicant_id, external_user_id)
    VALUES (:aid, :ext)
    ON DUPLICATE KEY UPDATE applicant_id = VALUES(applicant_id)
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':aid' => $applicantId, ':ext' => $externalUserId]);

  // 5) Lien WebSDK “frais”
  $identifiers = [];
  if ($email) $identifiers['email'] = $email;
  if ($phone) $identifiers['phone'] = $phone;

  $link = $client->generateWebsdkLink($externalUserId, $level, 1800, $identifiers);
  $websdkUrl = $link['url'] ?? null;
  if (!$websdkUrl) {
    throw new RuntimeException('No WebSDK url in response.');
  }

  if (isset($_GET['debug'])) {
    $__debug .= "applicantId    = {$applicantId}\n";
    $__debug .= "websdkUrl      = {$websdkUrl}\n";
  }

} catch (Throwable $e) {
  if ($e instanceof SumsubApiException && isset($_GET['debug'])) {
    $msg = $e->getMessage();
    $msg .= "\n\nResponse JSON:\n" . json_encode($e->responseJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    abort_html(500, $__debug . "\n== ERROR ==\n" . $msg . "\n");
}
  abort_html(500, 'Internal error: ' . $e->getMessage());
}

/* ===== Render ===== */
if ($mode === 'iframe') {
  ?>
  <!doctype html>
  <html lang="fr">
  <head>
    <meta charset="utf-8">
    <title>KYC — Sumsub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 0; background:#0b1020; color:#fff;}
      header { padding: 16px 20px; background:#121a33; }
      main { padding: 16px 20px; }
      iframe { width: 100%; height: 86vh; border: 0; background:#fff; border-radius: 12px; }
      pre { white-space: pre-wrap; background:#050814; padding:12px; border-radius: 12px; overflow:auto; }
      a { color:#9fb4ff; }
    </style>
  </head>
  <body>
    <header>
      <strong>Sumsub KYC</strong>
      <span style="opacity:.7; margin-left:12px;">user: <?= hp($externalUserId) ?></span>
    </header>
    <main>
      <?php if (isset($_GET['debug'])): ?>
        <pre><?= hp($__debug) ?></pre>
      <?php endif; ?>
      <iframe src="<?= hp($websdkUrl) ?>" allow="camera; microphone; fullscreen"></iframe>
    </main>
  </body>
  </html>
  <?php
  exit;
}

if (isset($_GET['debug'])) {
  echo "<!doctype html><meta charset='utf-8'><pre>".hp($__debug)."</pre>";
  echo '<p><a href="'.hp($websdkUrl).'">Continuer vers Sumsub</a></p>';
  exit;
}

header('Location: ' . $websdkUrl, true, 302);
exit;
