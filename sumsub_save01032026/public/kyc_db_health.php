<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

echo "== KYC DB Health (no auto-increment) ==\n";

$cfg = __DIR__ . '/../config/db.php';
if (!is_file($cfg)) {
  echo "ERROR: DB config missing\n";
  exit;
}
$db = require $cfg;

// Connexion PDO
try {
  $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  echo "DB connect: OK\n";
  $pdo->query("SELECT 1")->fetch();
  echo "DB simple query: OK\n";
} catch (Throwable $e) {
  echo "DB ERROR: ".$e->getMessage()."\n";
  exit;
}

/**
 * Génère un BIGINT déterministe depuis applicant_id.
 *  - On prend les 15 premiers hex de SHA-256 (≈60 bits) => int 64-bit OK.
 *  - Stable: même applicant_id => même id.
 */
function kyc_deterministic_id(string $applicantId): int {
  $hex = substr(hash('sha256', $applicantId), 0, 15); // 15 hex = 60 bits
  // hexdec retourne int (64-bit sur PHP x64). Sécuriser cast int.
  return (int) hexdec($hex);
}

// Données de test via query string
$applicantId        = isset($_GET['aid'])         ? (string)$_GET['aid']         : ('health_' . date('Ymd_His'));
$externalUserId     = isset($_GET['ext'])         ? (string)$_GET['ext']         : null;
$reviewStatus       = isset($_GET['rev_status'])  ? (string)$_GET['rev_status']  : null; // ex: init/pending/completed
$reviewAnswer       = isset($_GET['rev_answer'])  ? (string)$_GET['rev_answer']  : null; // ex: GREEN/RED
$rejectLabels       = isset($_GET['rej'])         ? json_decode((string)$_GET['rej'], true) : null; // JSON array
$moderationComment  = isset($_GET['comment'])     ? (string)$_GET['comment']     : null;
$reviewedAt         = isset($_GET['reviewed_at']) ? (string)$_GET['reviewed_at'] : null; // 'YYYY-MM-DD HH:MM:SS'
$rawStatus          = isset($_GET['raw_status'])  ? json_decode((string)$_GET['raw_status'], true)  : null;
$rawApplicant       = isset($_GET['raw_app'])     ? json_decode((string)$_GET['raw_app'], true)     : null;

// JSON → string ou NULL
$rejectLabelsJson = $rejectLabels !== null ? json_encode($rejectLabels, JSON_UNESCAPED_UNICODE) : null;
$rawStatusJson    = $rawStatus    !== null ? json_encode($rawStatus,    JSON_UNESCAPED_UNICODE) : null;
$rawApplicantJson = $rawApplicant !== null ? json_encode($rawApplicant, JSON_UNESCAPED_UNICODE) : null;

// Calcul d'un id BIGINT déterministe (pas d'AUTO_INCREMENT)
$externalUserId = (string)$_SESSION['user_id'];

// UPSERT basé sur UNIQUE(applicant_id) — on DOIT fournir id
$sql = "
  INSERT INTO kyc_applicants (
    id,
    applicant_id,
    external_user_id
  )
  VALUES (
    :id,
    :aid,
    :ext
  )
  ON DUPLICATE KEY UPDATE
    external_user_id    = VALUES(external_user_id),
    review_status       = VALUES(review_status),
    review_answer       = VALUES(review_answer),
    reject_labels       = VALUES(reject_labels),
    moderation_comment  = VALUES(moderation_comment),
    reviewed_at         = VALUES(reviewed_at),
    raw_status          = VALUES(raw_status),
    raw_applicant       = VALUES(raw_applicant),
    updated_at          = CURRENT_TIMESTAMP
";

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':id'            => $externalUserId,
    ':aid'           => $applicantId,
    ':ext'           => $externalUserId
  ]);

  echo "UPSERT OK\n";
  echo "id(deterministic) = {$externalUserId}\n";
  echo "applicant_id      = {$applicantId}\n";
  if ($externalUserId) echo "external_user_id = {$externalUserId}\n";
  if ($reviewStatus)   echo "review_status    = {$reviewStatus}\n";
  if ($reviewAnswer)   echo "review_answer    = {$reviewAnswer}\n";

} catch (Throwable $e) {
  echo "UPSERT ERROR: ".$e->getMessage()."\n";
}

echo "Done.\n";
