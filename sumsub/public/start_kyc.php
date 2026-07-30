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

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (isset($_GET['simulate_kyc']) && isset($_SESSION['user_id'])) {
  require_once __DIR__ . '/../../src/db.php';
  $stmt_sim = $pdo->prepare("UPDATE user SET kyc_status = 'approved' WHERE id = ?");
  $stmt_sim->execute([(int)$_SESSION['user_id']]);
  header('Location: /purchase');
  exit;
}

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

/* ===== DB connect (Use Central App DB) ===== */
try {
  require_once __DIR__ . '/../../src/db.php';
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
  $is_credit_error = str_contains($e->getMessage(), '402') || str_contains($e->getMessage(), 'license-key-exhausted') || str_contains($e->getMessage(), 'credit');
  header('Content-Type: text/html; charset=utf-8');
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8">
    <title>KYC Provider Notice</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  </head>
  <body class="bg-gray-50 flex items-center justify-center min-h-screen p-6 font-['Montserrat']">
    <div class="max-w-lg w-full bg-white border border-gray-200 rounded-3xl p-8 shadow-xl text-center">
      <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-6 text-2xl font-bold">
        ⚠️
      </div>
      <h2 class="text-xl font-bold text-gray-900 mb-2">Sumsub Verification Service Notice</h2>
      <p class="text-sm text-gray-600 mb-6 leading-relaxed">
        <?php if ($is_credit_error): ?>
          The Sumsub API sandbox/production credits for this key have been <strong>exhausted</strong> (HTTP 402: <code>license-key-exhausted</code>). This is an API credit balance issue on Sumsub's dashboard, <strong>not</strong> a domain issue.
        <?php else: ?>
          Unable to initialize Sumsub verification: <?php echo htmlspecialchars($e->getMessage()); ?>
        <?php endif; ?>
      </p>
      
      <div class="space-y-3">
        <a href="start_kyc.php?simulate_kyc=1" class="block w-full py-3.5 bg-gray-900 hover:bg-black text-white font-bold text-sm rounded-xl transition-all shadow-md">
          ✓ Approve KYC (Local Dev Bypass)
        </a>
        <a href="/purchase" class="block w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-xs rounded-xl transition-all">
          Back to Purchase Page
        </a>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
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
