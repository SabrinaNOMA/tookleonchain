<?php
declare(strict_types=1);
session_start();

/**
 * kyc_portal.php (VERSION PROD CLEAN)
 *
 * Rôle :
 * - Page d’entrée Tookle pour lancer la vérification KYC Sumsub
 * - 1 seul call-to-action : "Commencer ma vérification"
 * - Appelle start_kyc.php en mode redirect (expérience plein écran Sumsub)
 *
 * Hypothèses :
 * - L'utilisateur est déjà connecté sur Tookle (session active)
 * - $_SESSION['user_id'] peut exister (sinon on génère un guest_xxx)
 * - Optionnellement, $_SESSION['email'] et $_SESSION['phone'] peuvent exister
 *
 * Flux :
 *   kyc_portal.php  -->  start_kyc.php?user=...&email=...&phone=...
 *   start_kyc.php   -->  Sumsub WebSDK (redirect)
 */

// Helper HTML
function hp(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ------------------------------------------------------------------
// Construction des paramètres pour start_kyc.php
// ------------------------------------------------------------------
$baseUrlStart = 'start_kyc.php';

// ExternalUserId : on réutilise la logique de start_kyc.php
if (!empty($_SESSION['user_id'])) {
    $externalUserId = 'sess_' . preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_SESSION['user_id']);
} else {
    // fallback invité : ça restera fonctionnel, mais en prod
    // tu voudras probablement forcer la connexion Tookle
    $externalUserId = 'guest_' . bin2hex(random_bytes(6));
}

// Email / téléphone depuis la session si disponibles
$email = $_SESSION['email'] ?? null;
$phone = $_SESSION['phone'] ?? null;

// Paramètres pour l’URL de démarrage KYC
$params = [
    'user' => $externalUserId,
    // 'mode' => 'redirect', // inutile car redirect est le mode par défaut dans start_kyc.php
];

// On ne passe email / phone que s’ils existent
if ($email) $params['email'] = $email;
if ($phone) $params['phone'] = $phone;

// On laisse start_kyc.php choisir le level par défaut (SUMSUB_LEVEL)
// mais tu peux forcer ici par ex. : $params['level'] = 'leveltookle';
$startKycUrl = $baseUrlStart . '?' . http_build_query($params);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Tookle • Vérification KYC</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root {
      --bg:#0b1120;
      --card:#020617;
      --card2:#020817;
      --accent:#38bdf8;
      --accent-soft:rgba(56,189,248,.15);
      --border:#1f2937;
      --text:#e5e7eb;
      --muted:#9ca3af;
      --danger:#f97373;
      --ok:#22c55e;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      min-height:100vh;
      background:radial-gradient(circle at top,#0f172a 0,var(--bg) 55%,#020617 100%);
      color:var(--text);
      font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;
    }
    header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:16px 32px;
    }
    header .brand{
      display:flex;
      align-items:center;
      gap:10px;
    }
    header img{
      height:32px;
      width:auto;
    }
    header .brand-title{
      font-weight:600;
      letter-spacing:.03em;
      font-size:16px;
    }
    header .backlink{
      color:var(--muted);
      text-decoration:none;
      font-size:14px;
    }
    header .backlink:hover{
      color:var(--accent);
    }
    .wrap{
      max-width:960px;margin:40px auto;padding:0 20px;
    }
    .card{
      background:linear-gradient(145deg,#020617,#020617 40%,#020617 70%,rgba(56,189,248,.18) 140%);
      border-radius:22px;
      border:1px solid rgba(148,163,184,.35);
      padding:28px 26px 22px;
      box-shadow:0 24px 80px rgba(15,23,42,.85);
      position:relative;
      overflow:hidden;
    }
    .pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 10px;
      border-radius:999px;
      background:rgba(15,23,42,.9);
      border:1px solid rgba(148,163,184,.5);
      color:var(--muted);
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.14em;
    }
    .pill span.dot{
      width:6px;height:6px;border-radius:50%;background:#22c55e;
    }
    h1{
      margin:12px 0 8px;
      font-size:26px;
      letter-spacing:.02em;
    }
    .subtitle{
      font-size:14px;
      color:var(--muted);
      max-width:520px;
    }
    .grid{
      display:grid;
      grid-template-columns: minmax(0,1.3fr) minmax(0,1fr);
      gap:20px;
      margin-top:20px;
      align-items:flex-start;
    }
    .block{
      background:rgba(15,23,42,.92);
      border-radius:18px;
      border:1px solid rgba(30,64,175,.7);
      padding:14px 16px 16px;
    }
    .block h2{
      margin:0 0 8px;color:var(--accent);
      font-size:14px;
      text-transform:uppercase;
      letter-spacing:.16em;
    }
    .steps{
      list-style:none;
      padding:0;
      margin:0;
    }
    .steps li{
      display:flex;
      align-items:flex-start;
      gap:10px;
      padding:6px 0;
      color:var(--muted);
      font-size:13px;
    }
    .steps .badge{
      width:20px;height:20px;border-radius:999px;
      background:rgba(15,23,42,1);
      border:1px solid rgba(148,163,184,.55);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:11px;
      color:var(--accent);
      flex-shrink:0;
    }
    .steps strong{
      color:var(--text);
      display:block;
      font-size:13px;
    }
    .cta-area{
      display:flex;
      flex-direction:column;
      gap:10px;
    }
    .primary-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:11px 18px;
      border-radius:999px;
      border:none;
      background:linear-gradient(135deg,#38bdf8,#0ea5e9);
      color:#0b1120;
      font-weight:600;
      font-size:14px;
      cursor:pointer;
      box-shadow:0 10px 30px rgba(8,47,73,.9);
      text-decoration:none;
    }
    .primary-btn span.icon{
      font-size:16px;
    }
    .primary-btn:hover{
      filter:brightness(1.05);
      box-shadow:0 12px 36px rgba(8,47,73,1);
    }
    .meta{
      font-size:12px;
      color:var(--muted);
    }
    .meta code{
      background:rgba(15,23,42,.9);
      padding:1px 4px;
      border-radius:4px;
      font-size:11px;
      border:1px solid rgba(31,41,55,.8);
    }
    @media (max-width:780px){
      header{padding:12px 18px;}
      .wrap{margin:24px auto;}
      .card{padding:20px 16px;}
      .grid{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <img src="https://dev.tookle.app/assets/logo_tookle.svg" alt="Tookle logo">
      <span class="brand-title">Tookle • Identity Verification</span>
    </div>
    <a href="https://dev.tookle.app/tookle2/settings" class="backlink">← Retour à mon espace</a>
  </header>

  <div class="wrap">
    <div class="card">
      <span class="pill">
        <span class="dot"></span>
        Sécurisé par Sumsub • KYC / AML
      </span>
      <h1>Vérifiez votre identité pour accéder à Tookle</h1>
      <p class="subtitle">
        Pour des raisons réglementaires (KYC / LCB-FT), nous devons vérifier votre identité avant
        de vous donner accès complet aux fonctionnalités Tookle (investissements, escrow, flux tokenisés, etc.).
      </p>

      <div class="grid">
        <div class="block">
          <h2>Parcours de vérification</h2>
          <ul class="steps">
            <li>
              <span class="badge">1</span>
              <div>
                <strong>Document d’identité</strong>
                Carte d’identité nationale ou passeport en cours de validité.
              </div>
            </li>
            <li>
              <span class="badge">2</span>
              <div>
                <strong>Justificatif de domicile</strong>
                Facture de services (eau, électricité, internet), relevé bancaire ou avis d’imposition récent.
              </div>
            </li>
            <li>
              <span class="badge">3</span>
              <div>
                <strong>Selfie de contrôle</strong>
                Vérification biométrique pour s’assurer que vous êtes bien le titulaire du document.
              </div>
            </li>
            <li>
              <span class="badge">4</span>
              <div>
                <strong>Contrôles KYC &amp; AML automatiques</strong>
                Sumsub effectue les vérifications sanctions, PEP, adverse media et la revue KYC.
              </div>
            </li>
          </ul>
        </div>

        <div class="block cta-area">
          <div>
            <h2>Commencer maintenant</h2>
            <p class="subtitle" style="margin:4px 0 10px;">
              La vérification prend généralement quelques minutes. Vous pouvez la reprendre si vous
              interrompez le parcours.
            </p>
          </div>
          <a href="<?= hp($startKycUrl) ?>" class="primary-btn">
            <span class="icon">⚡</span>
            <span>Commencer ma vérification</span>
          </a>
          <p class="meta">
            Votre identifiant de vérification est généré automatiquement à partir de votre compte Tookle.<br>
            Les données sont traitées via notre partenaire <strong>Sumsub</strong> et ne sont utilisées qu’à des fins de conformité réglementaire.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
