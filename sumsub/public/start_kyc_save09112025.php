<?php
declare(strict_types=1);

/**
 * start_kyc.php
 * - Résout ou crée un applicant (externalUserId)
 * - Génère un WebSDK permalink "frais"
 * - Redirige vers le permalink (par défaut) ou l'affiche dans une iframe (&mode=iframe)
 *
 * Usage :
 *   /sumsub/public/start_kyc.php?user=USER123&email=a@b.c&phone=+33123456789&level=leveltookle
 * Options :
 *   &mode=iframe  → au lieu d'une redirection, on affiche l'iframe intégrée
 *   &debug=1      → traces minimalistes
 */

if (isset($_GET['debug'])) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';

function abort_http(int $code, string $msg): never {
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

// Charge secrets (fallback depuis config/secrets.php si pas en env)
$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
  $cfg = require $configFile;
  if (is_array($cfg)) {
    $_SERVER['SUMSUB_APP_TOKEN']  = $_SERVER['SUMSUB_APP_TOKEN']  ?? ($cfg['SUMSUB_APP_TOKEN']  ?? '');
    $_SERVER['SUMSUB_APP_SECRET'] = $_SERVER['SUMSUB_APP_SECRET'] ?? ($cfg['SUMSUB_APP_SECRET'] ?? '');
    $_SERVER['SUMSUB_LEVEL']      = $_SERVER['SUMSUB_LEVEL']      ?? ($cfg['SUMSUB_LEVEL']      ?? 'leveltookle');
  }
}

$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: ($_SERVER['SUMSUB_APP_TOKEN'] ?? '');
$appSecret = getenv('SUMSUB_APP_SECRET') ?: ($_SERVER['SUMSUB_APP_SECRET'] ?? '');
$levelName = $_GET['level'] ?? (getenv('SUMSUB_LEVEL') ?: ($_SERVER['SUMSUB_LEVEL'] ?? 'leveltookle'));

if (!$appToken || !$appSecret) {
  abort_http(500, 'Missing SUMSUB credentials (SUMSUB_APP_TOKEN / SUMSUB_APP_SECRET).');
}

// Paramètres utilisateur
$externalUserId = trim((string)($_GET['user'] ?? ''));
$email          = trim((string)($_GET['email'] ?? ''));
$phone          = trim((string)($_GET['phone'] ?? ''));
$mode           = strtolower((string)($_GET['mode'] ?? 'redirect')); // 'redirect' | 'iframe'

// Si aucun user fourni, on en génère un dynamique (utile pour test)
if ($externalUserId === '') {
  $externalUserId = 'user_' . bin2hex(random_bytes(6));
}

$client = new SumsubClient($appToken, $appSecret);

try {
  // 1) Résoudre ou créer l’applicant
  try {
    $applicant = $client->getApplicantByExternalUserId($externalUserId);
  } catch (SumsubApiException $e) {
    if ($e->statusCode === 404) {
      // Création on-demand
      $payload = [
        'externalUserId' => $externalUserId,
        'email'          => $email ?: null,
        'phone'          => $phone ?: null,
        'fixedInfo'      => ['country' => 'FRA'], // adapte si besoin
      ];
      $applicant = $client->createApplicant($payload, $levelName);
    } else {
      throw $e;
    }
  }

  $applicantId = $applicant['id'] ?? null;
  if (!$applicantId) {
    abort_http(500, 'Sumsub: applicant id missing after create/resolve.');
  }

  // 2) Génère un lien WebSDK "frais"
  $identifiers = [];
  if ($email !== '') $identifiers['email'] = $email;
  if ($phone !== '') $identifiers['phone'] = $phone;

  $link = $client->generateWebsdkLink($externalUserId, $levelName, 1800, $identifiers);
  $websdkUrl = $link['url'] ?? null;

  if (!$websdkUrl) {
    abort_http(500, 'Sumsub: websdkUrl missing.');
  }

  // 3) Mode d’affichage
  if ($mode !== 'iframe') {
    // Redirection HTTP 303 (See Other) pour éviter re-POST issues
    header('Location: ' . $websdkUrl, true, 303);
    exit;
  }

  // Sinon, mode iframe : on affiche une page avec l’iframe embarquée
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Start KYC — WebSDK</title>
    <style>
      :root {
        --bg:#0b0f19; --card:#121826; --text:#e5e7eb; --muted:#9aa4b2; --blue:#2151ff; --border:#243249;
      }
      *{box-sizing:border-box}
      body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif}
      .wrap{max-width:980px;margin:32px auto;padding:0 16px}
      .card{background:var(--card);border-radius:16px;padding:20px;border:1px solid var(--border);box-shadow:0 12px 36px rgba(0,0,0,.35)}
      h1{margin:0 0 6px;font-size:22px}
      .muted{color:var(--muted);font-size:14px;margin-bottom:12px}
      iframe{width:100%;height:78vh;border:0;border-radius:12px;background:#0f1422}
      .row{display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 0}
      .pill{display:inline-flex;gap:8px;align-items:center;padding:6px 10px;border-radius:999px;background:#0f1422;border:1px solid var(--border)}
      a.btn{color:#fff;background:var(--blue);text-decoration:none;padding:8px 12px;border-radius:10px}
      .kv{display:grid;grid-template-columns:150px 1fr;gap:6px;margin:10px 0 0}
      .k{color:var(--muted)}
      .v{font-weight:600}
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="card">
        <h1>Start KYC — WebSDK</h1>
        <div class="muted">L’upload des documents (ID/Passport/PoA/Selfie) se fait dans l’iframe Sumsub ci-dessous.</div>

        <div class="kv">
          <div class="k">ExternalUserId</div><div class="v"><?= htmlspecialchars($externalUserId) ?></div>
          <div class="k">ApplicantId</div><div class="v"><?= htmlspecialchars($applicantId) ?></div>
          <div class="k">Level</div><div class="v"><?= htmlspecialchars($levelName) ?></div>
        </div>

        <div class="row" style="margin-top:12px">
          <span class="pill">Mode: iframe</span>
          <a class="btn" target="_blank" href="<?= htmlspecialchars($websdkUrl) ?>">Open WebSDK in new tab</a>
        </div>

        <div style="margin-top:12px">
          <iframe src="<?= htmlspecialchars($websdkUrl) ?>" allow="camera *; microphone *; fullscreen *"></iframe>
        </div>
      </div>
    </div>
  </body>
  </html>
  <?php

} catch (SumsubApiException $e) {
  // Affiche une erreur claire (utile en debug)
  http_response_code(max(400, $e->statusCode ?: 500));
  header('Content-Type: text/plain; charset=utf-8');
  echo "Sumsub error (HTTP {$e->statusCode})\n";
  if (is_array($e->responseJson)) {
    echo json_encode($e->responseJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  } else {
    echo $e->getMessage();
  }
  exit;
}
?>