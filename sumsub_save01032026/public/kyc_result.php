<?php
declare(strict_types=1);

/**
 * kyc_result.php — Résultat KYC (status global)
 * Usage :
 *   /sumsub/public/kyc_result.php?applicantId=...
 *   /sumsub/public/kyc_result.php?user=externalUserId...
 *   (option) &debug=1
 */

if (isset($_GET['debug'])) { ini_set('display_errors','1'); error_reporting(E_ALL); }

header('Content-Type: text/html; charset=utf-8');

function abort(int $code, string $msg): never {
  http_response_code($code);
  echo "<!doctype html><meta charset='utf-8'><pre>{$msg}</pre>";
  exit;
}

/*** includes ***/
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';

/*** secrets via fichier (fallback) ***/
$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
  $cfg = require $configFile;
  if (is_array($cfg)) {
    $_SERVER['SUMSUB_APP_TOKEN']  = $_SERVER['SUMSUB_APP_TOKEN']  ?? ($cfg['SUMSUB_APP_TOKEN']  ?? '');
    $_SERVER['SUMSUB_APP_SECRET'] = $_SERVER['SUMSUB_APP_SECRET'] ?? ($cfg['SUMSUB_APP_SECRET'] ?? '');
    $_SERVER['SUMSUB_LEVEL']      = $_SERVER['SUMSUB_LEVEL']      ?? ($cfg['SUMSUB_LEVEL']      ?? 'leveltookle');
  }
}

/*** récup secrets (env ou fallback serveur) ***/
$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: ($_SERVER['SUMSUB_APP_TOKEN'] ?? '');
$appSecret = getenv('SUMSUB_APP_SECRET') ?: ($_SERVER['SUMSUB_APP_SECRET'] ?? '');

if (isset($_GET['debug'])) {
  echo "<pre>DEBUG kyc_result.php
token_present = " . (int)!!$appToken . " (ends ****" . htmlspecialchars(substr((string)$appToken, -6)) . ")
secret_present= " . (int)!!$appSecret . " (ends ****" . htmlspecialchars(substr((string)$appSecret, -6)) . ")
</pre>";
}

if (!$appToken || !$appSecret) {
  abort(500, "Missing Sumsub credentials (SUMSUB_APP_TOKEN / SUMSUB_APP_SECRET). "
    . "Ajoute-les dans /sumsub/config/secrets.php ou via SetEnv dans ton vHost.");
}

$client = new SumsubClient($appToken, $appSecret);

/*** inputs ***/
$applicantId = isset($_GET['applicantId']) ? trim((string)$_GET['applicantId']) : null;
$externalId  = isset($_GET['user'])        ? trim((string)$_GET['user'])        : null;

try {
  if (!$applicantId && $externalId) {
    $applicant   = $client->getApplicantByExternalUserId($externalId);
    $applicantId = $applicant['id'] ?? null;
  }
  if (!$applicantId) abort(400, 'Missing applicantId or user');

  $status    = $client->getApplicantStatus($applicantId);
  $applicant = $client->getApplicant($applicantId);

} catch (SumsubApiException $e) {
  http_response_code(max(400, $e->statusCode ?: 500));
  echo "<h1>Sumsub error</h1><pre>"
     . htmlspecialchars($e->getMessage()) . "\n\n"
     . htmlspecialchars(json_encode($e->responseJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))
     . "</pre>";
  exit;
}

/*** view data ***/
$firstName = $applicant['info']['firstName'] ?? ($applicant['fixedInfo']['firstName'] ?? null);
$lastName  = $applicant['info']['lastName']  ?? ($applicant['fixedInfo']['lastName']  ?? null);
$reviewStatus = $status['reviewStatus'] ?? 'pending';
$reviewAnswer = $status['reviewAnswer'] ?? null;

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>KYC Status</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root { --ok:#16a34a; --ko:#dc2626; --pending:#eab308; --border:#e5e7eb; --text:#111827; --muted:#6b7280; --card:#f9fafb; }
  *{box-sizing:border-box} body{margin:0; background:#fff; color:var(--text); font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial}
  .wrap{max-width:780px;margin:40px auto;padding:0 16px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px}
  h1{margin:0 0 8px}
  .muted{color:var(--muted);font-size:14px}
  .row{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0}
  .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#f3f4f6;border:1px solid var(--border);font-weight:600}
  .pill.ok{background:rgba(22,163,74,.1);color:var(--ok);border-color:rgba(22,163,74,.3)}
  .pill.ko{background:rgba(220,38,38,.1);color:var(--ko);border-color:rgba(220,38,38,.3)}
  .pill.pending{background:rgba(234,179,8,.1);color:var(--pending);border-color:rgba(234,179,8,.3)}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:8px}
  .item{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px}
  .k{display:block;font-size:12px;color:var(--muted)}
  .v{display:block;margin-top:4px;font-weight:600}
  .sep{height:1px;background:var(--border);margin:16px 0}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>KYC Status</h1>
      <div class="muted">Applicant ID: <?= htmlspecialchars($applicantId) ?></div>

      <div class="row">
        <?php
          $cls = 'pending'; $txt = '⌛ Under review';
          if ($reviewStatus === 'completed' && $reviewAnswer === 'GREEN') { $cls='ok'; $txt='✅ Approved'; }
          elseif ($reviewStatus === 'completed' && $reviewAnswer === 'RED') { $cls='ko'; $txt='❌ Rejected'; }
        ?>
        <span class="pill <?= $cls ?>"><?= htmlspecialchars($txt) ?></span>
        <span class="pill">Status: <?= htmlspecialchars((string)$reviewStatus) ?></span>
        <?php if ($reviewAnswer): ?><span class="pill">Decision: <?= htmlspecialchars((string)$reviewAnswer) ?></span><?php endif; ?>
      </div>

      <div class="sep"></div>
      <div class="grid">
        <div class="item"><span class="k">Name</span><span class="v"><?= htmlspecialchars(trim(($firstName ?? '').' '.($lastName ?? '')) ?: '—') ?></span></div>
      </div>

      <div style="margin-top:10px">
        <a class="pill" href="/sumsub/public/kyc_result.php?applicantId=<?= urlencode($applicantId) ?>&debug=1">Refresh (debug)</a>
      </div>
    </div>
  </div>
</body>
</html>
