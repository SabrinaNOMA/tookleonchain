<?php
declare(strict_types=1);

/**
 * start_kyc.php
 * Lance un flux WebSDK Sumsub pour un externalUserId donné :
 * - ?user=...  (obligatoire si pas de session ; sinon on peut auto depuis $_SESSION['user_id'])
 * - ?mode=iframe | redirect (default: redirect)
 * - ?level=...   (fallback SUMSUB_LEVEL)
 * - ?email=... & ?phone=...
 * - ?debug=1     (trace lisible)
 */

if (isset($_GET['debug'])) { ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL); }

session_start();

/** Helpers **/
function hp(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function abort_html(int $code, string $msg): never {
  http_response_code($code);
  echo "<!doctype html><meta charset='utf-8'><pre>".hp($msg)."</pre>";
  exit;
}

/** Includes (adapte si besoin) **/
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';

/** Secrets fallback via fichier **/
$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
  $cfg = require $configFile;
  if (is_array($cfg)) {
    $_SERVER['SUMSUB_APP_TOKEN']  = $_SERVER['SUMSUB_APP_TOKEN']  ?? ($cfg['SUMSUB_APP_TOKEN']  ?? '');
    $_SERVER['SUMSUB_APP_SECRET'] = $_SERVER['SUMSUB_APP_SECRET'] ?? ($cfg['SUMSUB_APP_SECRET'] ?? '');
    $_SERVER['SUMSUB_LEVEL']      = $_SERVER['SUMSUB_LEVEL']      ?? ($cfg['SUMSUB_LEVEL']      ?? 'leveltookle');
  }
}

/** Charge secrets **/
$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: ($_SERVER['SUMSUB_APP_TOKEN'] ?? '');
$appSecret = getenv('SUMSUB_APP_SECRET') ?: ($_SERVER['SUMSUB_APP_SECRET'] ?? '');
$defaultLevel = getenv('SUMSUB_LEVEL') ?: ($_SERVER['SUMSUB_LEVEL'] ?? 'leveltookle');

if (!$appToken || !$appSecret) abort_html(500, 'Missing SUMSUB credentials');

/** Inputs **/
$mode  = $_GET['mode'] ?? 'redirect';
$level = $_GET['level'] ?? $defaultLevel;
$email = $_GET['email'] ?? null;
$phone = $_GET['phone'] ?? null;

// External user id: query > session > random guest
if (!empty($_GET['user'])) {
  $externalUserId = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_GET['user']);
} elseif (!empty($_SESSION['user_id'])) {
  $externalUserId = 'sess_' . preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_SESSION['user_id']);
} else {
  $externalUserId = 'guest_' . bin2hex(random_bytes(6));
}

/** Debug (non bloquant) **/
$__debug = '';
if (isset($_GET['debug'])) {
  $__debug .= "== DEBUG start_kyc.php ==\n";
  $__debug .= "token_present = " . (int)!!$appToken . " (ends ****" . substr((string)$appToken, -6) . ")\n";
  $__debug .= "secret_present= " . (int)!!$appSecret . " (ends ****" . substr((string)$appSecret, -6) . ")\n";
  $__debug .= "levelName     = " . $level . "\n";
  $__debug .= "externalUserId= " . $externalUserId . "\n";
  if ($email) $__debug .= "email         = " . $email . "\n";
  if ($phone) $__debug .= "phone         = " . $phone . "\n";
}

$client = new SumsubClient($appToken, $appSecret);

try {
  // 1) Résoudre applicant via externalUserId
  try {
    $applicant = $client->getApplicantByExternalUserId($externalUserId);
    $applicantId = $applicant['id'] ?? null;
  } catch (SumsubApiException $e) {
    if ($e->statusCode === 404) {
      // 1bis) Créer si introuvable
      $payload = [
        'externalUserId' => $externalUserId,
        'fixedInfo'      => ['country' => 'FRA'],
      ];
      if ($email) $payload['email'] = $email;
      if ($phone) $payload['phone'] = $phone;

      $created = $client->createApplicant($payload, $level);
      $applicantId = $created['id'] ?? null;
      if (!$applicantId) throw new RuntimeException('Failed to create applicant');
    } else {
      throw $e;
    }
  }

  // 2) Générer un WebSDK permalink "frais"
  $identifiers = [];
  if ($email) $identifiers['email'] = $email;
  if ($phone) $identifiers['phone'] = $phone;

  $link = $client->generateWebsdkLink($externalUserId, $level, 1800, $identifiers);
  $websdkUrl = $link['url'] ?? null;
  if (!$websdkUrl) throw new RuntimeException('Missing websdkUrl in response');

  if (isset($_GET['debug'])) {
    $__debug .= "applicantId   = " . ($applicantId ?: 'null') . "\n";
    $__debug .= "websdkUrl     = " . $websdkUrl . "\n";
  }

} catch (SumsubApiException $e) {
  // Affiche l’erreur Sumsub lisible
  $json = $e->responseJson ? json_encode($e->responseJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;
  $msg  = $e->getMessage() . ($json ? "\n\n$json" : '');
  abort_html(max(400, $e->statusCode ?: 500), (isset($_GET['debug']) ? $__debug . "\n" : '') . $msg);
} catch (Throwable $e) {
  abort_html(500, (isset($_GET['debug']) ? $__debug . "\n" : '') . 'Fatal: ' . $e->getMessage());
}

/** Sortie selon mode **/
if ($mode === 'iframe') {
  // Page propre avec iframe
  ?>
  <!doctype html>
  <html lang="fr">
  <head>
    <meta charset="utf-8" />
    <title>Tookle – KYC (iframe)</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <style>
      html,body{height:100%} body{margin:0;background:#0b0f19;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto}
      .wrap{display:grid;place-items:center;height:100%}
      .card{width:min(1100px,95vw);height:min(92vh,900px);background:#121826;border:1px solid #243249;border-radius:16px;overflow:hidden;box-shadow:0 12px 36px rgba(0,0,0,.35)}
      iframe{width:100%;height:100%;border:0}
      pre.debug{position:fixed;bottom:8px;left:8px;right:8px;max-height:30vh;overflow:auto;background:#111827;color:#e5e7eb;border:1px solid #374151;border-radius:8px;padding:8px}
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="card">
        <iframe src="<?= hp($websdkUrl) ?>" allow="camera *; microphone *; fullscreen *"></iframe>
      </div>
    </div>
    <?php if (isset($_GET['debug'])): ?>
      <pre class="debug"><?= hp($__debug) ?></pre>
    <?php endif; ?>
  </body>
  </html>
  <?php
  exit;
}

// mode redirect (par défaut)
if (isset($_GET['debug'])) {
  // on montre le lien au lieu de rediriger immédiatement (plus simple pour inspecter)
  echo "<!doctype html><meta charset='utf-8'><pre>".hp($__debug)."</pre>";
  echo '<p><a href="'.hp($websdkUrl).'">Continuer vers Sumsub</a></p>';
  exit;
}

header('Location: ' . $websdkUrl, true, 302);
exit;
