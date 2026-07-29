<?php
declare(strict_types=1);

session_start();
header('Content-Type: text/html; charset=utf-8');

/**
 * kyc_status_widget.php
 *
 * Modes d'appel :
 * 1) GET  ?applicantId=XXXX              (✅ maintenant supporté -> session + redirect)
 * 2) GET  ?externalUserId=sess_41
 * 3) POST email=...  -> resolve applicantId via DB puis PRG -> GET ?applicantId=...
 *
 * Sources :
 * - DB: ../config/db.php
 * - Secrets Sumsub: ../config/secrets.php
 */

function abort_msg(string $msg, int $code = 500): never {
    http_response_code($code);
    echo "<!doctype html><meta charset='utf-8'><pre>" . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    exit;
}

/* ======================================================
 * (0) ✅ SUPPORT GET applicantId=... (évite 500 en appel direct)
 * ====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['applicantId'])) {
    $_SESSION['sumsub_applicant_id'] = trim((string)$_GET['applicantId']);

    // PRG-like: nettoyer l'URL (évite rechargements / double traitement)
    $target = strtok($_SERVER['REQUEST_URI'], '?'); // /sumsub/public/kyc_status_widget.php
    header('Location: ' . $target, true, 303);
    exit;
}

/* ======================================================
 * 1) Charger DB config
 * ====================================================== */
$dbCfgPath = __DIR__ . '/../config/db.php';
if (!is_file($dbCfgPath)) abort_msg('DB config missing: ' . $dbCfgPath);
$db = require $dbCfgPath;
if (!is_array($db) || empty($db['dsn'])) abort_msg('DB config invalid: ' . $dbCfgPath);

try {
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    abort_msg('DB connection error: ' . $e->getMessage());
}

/* ======================================================
 * 2) Charger Sumsub secrets
 * ====================================================== */
$secCfg = __DIR__ . '/../config/secrets.php';
if (!is_file($secCfg)) abort_msg('Sumsub config missing (secrets.php not found): ' . $secCfg);

$cfg = require $secCfg;
if (!is_array($cfg)) abort_msg('Sumsub config invalid: secrets.php must return an array');

$appToken  = (string)($cfg['SUMSUB_APP_TOKEN'] ?? '');
$secretKey = (string)($cfg['SUMSUB_APP_SECRET'] ?? '');
$baseUrl   = 'https://api.sumsub.com';

if ($appToken === '' || $secretKey === '') {
    abort_msg('Sumsub credentials missing in secrets.php (SUMSUB_APP_TOKEN / SUMSUB_APP_SECRET)');
}

/* ======================================================
 * 3) Helpers DB: resolve applicantId
 * ====================================================== */
function resolveApplicantIdFromEmail(PDO $pdo, string $email): ?string
{
    $email = trim($email);
    if ($email === '') return null;

    // 1) user.id
    $st = $pdo->prepare('SELECT id FROM user WHERE email = :email LIMIT 1');
    $st->execute([':email' => $email]);
    $user = $st->fetch();

    if (!$user || empty($user['id'])) return null;

    $externalUserId = 'sess_' . (int)$user['id'];

    // 2) applicant_id via kyc_applicants
    $st2 = $pdo->prepare(
        'SELECT applicant_id
           FROM kyc_applicants
          WHERE external_user_id = :ext
          ORDER BY updated_at DESC, created_at DESC
          LIMIT 1'
    );
    $st2->execute([':ext' => $externalUserId]);
    $row = $st2->fetch();

    return !empty($row['applicant_id']) ? (string)$row['applicant_id'] : null;
}

function resolveApplicantIdFromExternalUserId(PDO $pdo, string $externalUserId): ?string
{
    $externalUserId = trim($externalUserId);
    if ($externalUserId === '') return null;

    $st = $pdo->prepare(
        'SELECT applicant_id
           FROM kyc_applicants
          WHERE external_user_id = :ext
          ORDER BY updated_at DESC, created_at DESC
          LIMIT 1'
    );
    $st->execute([':ext' => $externalUserId]);
    $row = $st->fetch();

    return !empty($row['applicant_id']) ? (string)$row['applicant_id'] : null;
}

/* ======================================================
 * 4) Mode POST (email) -> DB -> redirect GET
 * ====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
    $email = trim((string)$_POST['email']);
    $appId = resolveApplicantIdFromEmail($pdo, $email);

    if (!$appId) {
        abort_msg("Impossible de retrouver applicant_id en base (kyc_applicants) pour l'email: $email", 404);
    }

    // On stocke aussi en session (utile si tu préfères afficher sans query)
    $_SESSION['sumsub_applicant_id'] = $appId;

    $target = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $target . '?applicantId=' . rawurlencode($appId), true, 303);
    exit;
}

/* ======================================================
 * 5) Client Sumsub
 * ====================================================== */
function sumsubSign(string $ts, string $method, string $uriWithQuery, string $body, string $secretKey): string {
    $payload = $ts . strtoupper($method) . $uriWithQuery . $body;
    return hash_hmac('sha256', $payload, $secretKey);
}

function sumsubRequest(string $method, string $uriWithQuery, ?array $jsonBody = null): array {
    global $appToken, $secretKey, $baseUrl;

    $method = strtoupper($method);
    $ts = (string)time();

    $body = '';
    if ($jsonBody !== null) {
        $body = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) throw new RuntimeException('JSON encode failed');
    }

    $sig = sumsubSign($ts, $method, $uriWithQuery, $body, $secretKey);

    $ch = curl_init($baseUrl . $uriWithQuery);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => array_filter([
            'Accept: application/json',
            $jsonBody ? 'Content-Type: application/json' : null,
            'X-App-Token: ' . $appToken,
            'X-App-Access-Ts: ' . $ts,
            'X-App-Access-Sig: ' . $sig,
        ]),
        CURLOPT_POSTFIELDS     => $jsonBody ? $body : null,
    ]);

    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('cURL error: ' . $err);

    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException("Invalid JSON from Sumsub (HTTP $http): " . $raw);

    if ($http < 200 || $http >= 300) {
        $msg = $json['description'] ?? $json['message'] ?? $json['error'] ?? 'Sumsub API error';
$cid = $json['correlationId'] ?? '';
throw new RuntimeException("Sumsub error (HTTP $http): $msg" . ($cid ? " | correlationId=$cid" : ""));
    }
    return $json;
}

function getApplicantById(string $applicantId): array {
    return sumsubRequest('GET', '/resources/applicants/' . rawurlencode($applicantId) . '/one');
}
function getApplicantByExternalUserId(string $externalUserId): array {
    return sumsubRequest('GET', '/resources/applicants/-;externalUserId=' . rawurlencode($externalUserId) . '/one');
}
function getReviewStatus(string $applicantId): array {
    return sumsubRequest('GET', '/resources/applicants/' . rawurlencode($applicantId) . '/review/status');
}
function getRequiredDocsStatus(string $applicantId): array {
    return sumsubRequest('GET', '/resources/applicants/' . rawurlencode($applicantId) . '/requiredIdDocsStatus');
}

/* ======================================================
 * 6) MAIN (GET)
 * ====================================================== */
$applicantId    = trim((string)($_GET['applicantId'] ?? ''));
$externalUserId = trim((string)($_GET['externalUserId'] ?? ''));

// ✅ fallback session (après PRG GET nettoyé)
if ($applicantId === '' && $externalUserId === '' && !empty($_SESSION['sumsub_applicant_id'])) {
    $applicantId = trim((string)$_SESSION['sumsub_applicant_id']);
}

// Si on reçoit externalUserId mais pas applicantId, on tente DB d'abord
if ($applicantId === '' && $externalUserId !== '') {
    $dbAppId = resolveApplicantIdFromExternalUserId($pdo, $externalUserId);
    if ($dbAppId) $applicantId = $dbAppId;
}

if ($applicantId === '' && $externalUserId === '') {
    abort_msg("Missing applicantId or externalUserId", 400);
}

try {
    // Résolution profil (Sumsub)
    $profile = ($applicantId !== '')
        ? getApplicantById($applicantId)
        : getApplicantByExternalUserId($externalUserId);

    $resolvedApplicantId = (string)($profile['id'] ?? $applicantId);
    if ($resolvedApplicantId === '') throw new RuntimeException("Cannot resolve applicantId.");

    $review = getReviewStatus($resolvedApplicantId);
    $steps  = getRequiredDocsStatus($resolvedApplicantId);

    // Extraction synthèse
    $info  = $profile['info'] ?? [];
    $fixed = $profile['fixedInfo'] ?? [];

    $fullName = trim(
        (string)(($info['firstName'] ?? '') ?: ($fixed['firstName'] ?? '')) . ' ' .
        (string)(($info['lastName'] ?? '')  ?: ($fixed['lastName'] ?? ''))
    );

    $dob     = (string)(($info['dob'] ?? '') ?: ($fixed['dob'] ?? '—'));
    $country = (string)(($info['country'] ?? '') ?: ($fixed['country'] ?? '—'));

    $status = (string)($review['reviewStatus'] ?? '—');
    $answer = (string)(($review['reviewResult']['reviewAnswer'] ?? '') ?: ($review['reviewAnswer'] ?? '—'));
    $at     = (string)(($review['reviewedAt'] ?? '') ?: ($review['reviewDate'] ?? ''));

    $docType   = (string)($steps['IDENTITY']['idDocType'] ?? '—');
    $docResult = (string)($steps['IDENTITY']['reviewResult']['reviewAnswer'] ?? '—');

} catch (Throwable $e) {
    abort_msg('Erreur: ' . $e->getMessage(), 500);
}

/* ======================================================
 * 7) HTML
 * ====================================================== */
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function formatDate(?string $iso): string {
    if (!$iso) return '—';
    $ts = strtotime($iso);
    return $ts ? date('Y-m-d H:i:s', $ts) : $iso;
}

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Rapport de synthèse KYC</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f7fb;padding:24px;margin:0}
.page{max-width:900px;margin:auto;background:#fff;padding:24px;border-radius:12px;border:1px solid #e5e7eb}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.card{border:1px solid #e5e7eb;border-radius:12px;padding:14px}
.row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #eee;gap:12px}
.row:last-child{border-bottom:none}
.k{color:#6b7280;font-size:12px}
.v{font-weight:600}
.pill{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;display:inline-block}
.green{background:#dcfce7;color:#166534}
.red{background:#fee2e2;color:#b91c1c}
.amber{background:#fef3c7;color:#92400e}
</style>
</head>
<body>
<div class="page">
  <h1 style="margin-top:0">Rapport de synthèse KYC</h1>
  <p style="color:#6b7280;font-size:13px">
    ApplicantId: <b><?= h($resolvedApplicantId) ?></b>
    <?php if ($externalUserId !== ''): ?> — ExternalUserId: <b><?= h($externalUserId) ?></b><?php endif; ?>
    — Généré le <?= date('Y-m-d H:i:s') ?>
  </p>

  <div class="grid">
    <div class="card">
      <h3 style="margin-top:0">Identité</h3>
      <div class="row"><span class="k">Nom</span><span class="v"><?= h($fullName ?: '—') ?></span></div>
      <div class="row"><span class="k">Date de naissance</span><span class="v"><?= h($dob) ?></span></div>
      <div class="row"><span class="k">Pays</span><span class="v"><?= h($country) ?></span></div>
    </div>

    <div class="card">
      <h3 style="margin-top:0">Décision KYC</h3>
      <?php
        $pill = 'amber';
        if ($status === 'completed' && strtoupper($answer) === 'GREEN') $pill = 'green';
        elseif ($status === 'completed' && strtoupper($answer) === 'RED') $pill = 'red';
      ?>
      <div class="row"><span class="k">Status</span><span class="v"><span class="pill <?= $pill ?>"><?= h($status) ?></span></span></div>
      <div class="row"><span class="k">Réponse</span><span class="v"><?= h($answer) ?></span></div>
      <div class="row"><span class="k">Vérifié le</span><span class="v"><?= h(formatDate($at)) ?></span></div>
    </div>

    <div class="card" style="grid-column:1/-1">
      <h3 style="margin-top:0">Documents</h3>
      <div class="row"><span class="k">Type</span><span class="v"><?= h($docType) ?></span></div>
      <div class="row"><span class="k">Résultat</span><span class="v"><?= h($docResult) ?></span></div>
      <p style="font-size:12px;color:#6b7280;margin:10px 0 0">
        Imprimer → Enregistrer en PDF pour l’archivage.
      </p>
    </div>
  </div>
</div>
</body>
</html>