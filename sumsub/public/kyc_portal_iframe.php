<?php
declare(strict_types=1);
session_start();

/**
 * kyc_portal_iframe.php
 * - Version "iframe only" (pas de bouton)
 * - Auto-remplit externalUserId depuis la session, sinon génère un id invité
 * - Optionnel : ?user=..., ?level=..., ?email=..., ?phone=...
 */

function getExternalUserId(): string {
    if (!empty($_GET['user'])) {
        return preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_GET['user']);
    }
    if (!empty($_SESSION['user_id'])) {
        return 'sess_' . preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_SESSION['user_id']);
    }
    return 'guest_' . bin2hex(random_bytes(6));
}

$externalUserId = getExternalUserId();
$level          = isset($_GET['level']) ? (string)$_GET['level'] : '';
$email          = isset($_GET['email']) ? (string)$_GET['email'] : '';
$phone          = isset($_GET['phone']) ? (string)$_GET['phone'] : '';

$base = './start_kyc.php';
$qs = http_build_query(array_filter([
    'user'  => $externalUserId,
    'level' => $level ?: null,
    'email' => $email ?: null,
    'phone' => $phone ?: null,
]));
$iframeUrl = $base . ($qs ? ('?' . $qs . '&mode=iframe') : '?mode=iframe&user=' . urlencode($externalUserId));

?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Tookle – KYC (iframe)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root { --bg:#0b0f19; --card:#121826; --text:#e5e7eb; --border:#243249; }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;display:grid;place-items:center}
    .card{width:min(1100px,95vw);height:min(92vh,900px);background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 12px 36px rgba(0,0,0,.35)}
    iframe{width:100%;height:100%;border:0}
  </style>
</head>
<body>
  <div class="card">
    <iframe
      src="<?= htmlspecialchars($iframeUrl) ?>"
      allow="camera *; microphone *; fullscreen *">
    </iframe>
  </div>
</body>
</html>
