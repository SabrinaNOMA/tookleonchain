<?php
declare(strict_types=1);
session_start();

/**
 * kyc_portal.php
 * - Hub KYC simple avec :
 *   • Bouton "Commencer ma vérification" (redirection vers start_kyc.php)
 *   • Intégration en iframe (start_kyc.php?mode=iframe)
 * - Auto-remplit externalUserId à partir de $_SESSION['user_id'] si présent,
 *   sinon génère un identifiant éphémère (utilisateur non connecté / test).
 *
 * Usage :
 *   /sumsub/public/kyc_portal.php
 *
 * Optionnel :
 *   ?user=...  → force un externalUserId au lieu de la session/généré
 *   ?level=... → force un level (sinon SUMSUB_LEVEL ou défaut)
 *   ?email=... & ?phone=... → pass-through vers start_kyc.php
 */

function getExternalUserId(): string {
    // 1) si un 'user' est fourni dans la query → priorité
    if (!empty($_GET['user'])) {
        return preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_GET['user']);
    }
    // 2) si la session porte un id → l'utiliser
    if (!empty($_SESSION['user_id'])) {
        // le normaliser pour Sumsub (autorise alphanum + _-.@)
        return 'sess_' . preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_SESSION['user_id']);
    }
    // 3) fallback généré (test invité)
    return 'guest_' . bin2hex(random_bytes(6));
}

$externalUserId = getExternalUserId();
$level          = isset($_GET['level']) ? (string)$_GET['level'] : '';
$email          = isset($_GET['email']) ? (string)$_GET['email'] : '';
$phone          = isset($_GET['phone']) ? (string)$_GET['phone'] : '';

// URL de base vers start_kyc.php (même dossier public/)
$base = './start_kyc.php';

// URLs construites
$qs = http_build_query(array_filter([
    'user'  => $externalUserId,
    'level' => $level ?: null,
    'email' => $email ?: null,
    'phone' => $phone ?: null,
]));

$redirectUrl = $base . ($qs ? ('?' . $qs) : '');
$iframeUrl   = $base . ($qs ? ('?' . $qs . '&mode=iframe') : '?mode=iframe&user=' . urlencode($externalUserId));

?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Tookle – KYC Portal</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root {
      --bg:#0b0f19; --card:#121826; --text:#e5e7eb; --muted:#9aa4b2; --blue:#2151ff; --border:#243249; --ok:#16a34a;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif}
    .wrap{max-width:1000px;margin:32px auto;padding:0 16px}
    .card{background:var(--card);border-radius:16px;padding:20px;border:1px solid var(--border);box-shadow:0 12px 36px rgba(0,0,0,.35)}
    h1{margin:0 0 6px;font-size:22px}
    .muted{color:var(--muted);font-size:14px;margin-bottom:12px}
    .row{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0 16px}
    .pill{display:inline-flex;gap:8px;align-items:center;padding:6px 10px;border-radius:999px;background:#0f1422;border:1px solid var(--border);font-size:14px}
    a.btn{color:#fff;background:var(--blue);text-decoration:none;padding:10px 14px;border-radius:10px;display:inline-block}
    a.btn:hover{filter:brightness(1.05)}
    .kv{display:grid;grid-template-columns:160px 1fr;gap:8px;margin:8px 0 0}
    .k{color:var(--muted)}
    .v{font-weight:600}
    iframe{width:100%;height:76vh;border:0;border-radius:12px;background:#0f1422}
    .note{font-size:13px;color:var(--muted);margin-top:8px}
    @media (max-width:640px){.kv{grid-template-columns:130px 1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>KYC Portal</h1>
      <div class="muted">Vérifiez votre identité avec Sumsub. L’upload (passeport / CNI / justificatif de domicile / selfie) se fait dans l’interface Sumsub.</div>

      <div class="kv">
        <div class="k">ExternalUserId</div><div class="v"><?= htmlspecialchars($externalUserId) ?></div>
        <?php if ($level): ?>
          <div class="k">Level</div><div class="v"><?= htmlspecialchars($level) ?></div>
        <?php endif; ?>
        <?php if ($email): ?>
          <div class="k">Email</div><div class="v"><?= htmlspecialchars($email) ?></div>
        <?php endif; ?>
        <?php if ($phone): ?>
          <div class="k">Téléphone</div><div class="v"><?= htmlspecialchars($phone) ?></div>
        <?php endif; ?>
      </div>

      <div class="row" style="margin-top:10px">
        <!-- Bouton Commencer (redirection) -->
        <a class="btn" href="<?= htmlspecialchars($redirectUrl) ?>">Commencer ma vérification</a>
        <!-- Lien pour ouvrir l’iframe en plein onglet -->
        <a class="btn" target="_blank" href="<?= htmlspecialchars($iframeUrl) ?>">Ouvrir en onglet (iframe)</a>
      </div>

      <!-- Intégration iframe immédiate (optionnelle) -->
      <div class="muted" style="margin-top:8px">Intégration en iframe :</div>
      <iframe
        src="<?= htmlspecialchars($iframeUrl) ?>"
        allow="camera *; microphone *; fullscreen *">
      </iframe>

      <div class="note">
        Astuces :
        <ul>
          <li>Assurez-vous que votre CSP autorise <code>frame-src https://in.sumsub.com</code> (ou domaine équivalent).</li>
          <li>Si l’iframe refuse de se charger (CSP stricte), utilisez le bouton “Commencer ma vérification”.</li>
        </ul>
      </div>
    </div>
  </div>
</body>
</html>
