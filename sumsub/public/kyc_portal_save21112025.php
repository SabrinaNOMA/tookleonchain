<?php
declare(strict_types=1);
session_start();

/**
 * kyc_portal.php (PROD CLEAN VERSION - LIGHT THEME)
 *
 * Role:
 * - Tookle entry page to launch Sumsub KYC verification
 * - Single call-to-action: "Start my verification"
 * - Calls start_kyc.php in redirect mode (Sumsub full-screen experience)
 *
 * Assumptions:
 * - User is already logged into Tookle (active session)
 * - $_SESSION['user_id'] may exist (otherwise a guest_xxx is generated)
 * - Optionally, $_SESSION['email'] and $_SESSION['phone'] may exist
 *
 * Flow:
 * kyc_portal.php  -->  start_kyc.php?user=...&email=...&phone=...
 * start_kyc.php   -->  Sumsub WebSDK (redirect)
 */

// HTML Helper
function hp(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ------------------------------------------------------------------
// Build parameters for start_kyc.php
// ------------------------------------------------------------------
$baseUrlStart = 'start_kyc.php';

// ExternalUserId: reusing logic from start_kyc.php
if (!empty($_SESSION['user_id'])) {
    $externalUserId = 'sess_' . preg_replace('/[^a-zA-Z0-9_\-\.@]/', '', (string)$_SESSION['user_id']);
} else {
    // Guest fallback
    $externalUserId = 'guest_' . bin2hex(random_bytes(6));
}

// Email / phone from session if available
$email = $_SESSION['email'] ?? null;
$phone = $_SESSION['phone'] ?? null;

// Parameters for the KYC start URL
$params = [
    'user' => $externalUserId,
];

// Only pass email/phone if they exist
if ($email) $params['email'] = $email;
if ($phone) $params['phone'] = $phone;

$startKycUrl = $baseUrlStart . '?' . http_build_query($params);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Tookle • KYC Verification</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    /* --- LIGHT MODE THEME --- */
    :root {
      --bg: #f3f4f6;           /* Fond général gris très clair */
      --card: #ffffff;         /* Fond de la carte blanc pur */
      --text: #111827;         /* Texte principal presque noir */
      --muted: #6b7280;        /* Texte secondaire gris moyen */
      
      --border: #e5e7eb;       /* Bordures gris clair */
      --accent: #0284c7;       /* Bleu soutenu pour le texte important */
      
      --block-bg: #f9fafb;     /* Fond des blocs internes */
      --badge-bg: #e0f2fe;     /* Fond des numéros d'étapes */
      --badge-text: #0369a1;   /* Texte des numéros */
      
      --pill-bg: #f1f5f9;      /* Fond du badge "Secured" */
      --pill-border: #cbd5e1;
    }
    
    *{box-sizing:border-box}
    
    body{
      margin:0;
      min-height:100vh;
      background: var(--bg);
      color: var(--text);
      font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;
      /* Centrage vertical optionnel si utilisé en iframe */
      display: flex;
      flex-direction: column;
      justify-content: center; 
    }

    header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:16px 32px;
      /* Si le header est vide, il ne prendra pas de place inutile */
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
      color: var(--text);
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
      max-width:960px;
      margin:0 auto; /* Centré horizontalement */
      padding:40px 20px;
      width: 100%;
    }

    /* Carte blanche sur fond gris */
    .card{
      background: var(--card);
      border-radius: 22px;
      border: 1px solid var(--border);
      padding: 28px 26px 22px;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
      position: relative;
      overflow: hidden;
    }

    /* Badge "Secured by Sumsub" */
    .pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 10px;
      border-radius:999px;
      background: var(--pill-bg);
      border: 1px solid var(--pill-border);
      color: var(--muted);
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.14em;
      font-weight: 600;
    }
    .pill span.dot{
      width:6px;height:6px;border-radius:50%;background:#22c55e;
    }

    h1{
      margin:12px 0 8px;
      font-size:26px;
      letter-spacing:.02em;
      color: #0f172a; /* Noir profond */
    }

    .subtitle{
      font-size:14px;
      color:var(--muted);
      max-width:520px;
      line-height: 1.5;
    }

    .grid{
      display:grid;
      grid-template-columns: minmax(0,1.3fr) minmax(0,1fr);
      gap:20px;
      margin-top:20px;
      align-items:flex-start;
    }

    /* Blocs internes gris clair */
    .block{
      background: var(--block-bg);
      border-radius: 18px;
      border: 1px solid var(--border);
      padding: 14px 16px 16px;
    }

    .block h2{
      margin:0 0 8px;
      color: var(--accent);
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.16em;
      font-weight: 700;
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
    
    /* Numéros d'étapes */
    .steps .badge{
      width:22px;height:22px;border-radius:999px;
      background: var(--badge-bg);
      border: 1px solid transparent;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:11px;
      font-weight: 700;
      color: var(--badge-text);
      flex-shrink:0;
    }

    .steps strong{
      color: var(--text);
      display:block;
      font-size:13px;
      font-weight: 600;
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
      padding:12px 18px;
      border-radius:999px;
      border:none;
      /* Dégradé bleu moderne */
      background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
      color: #ffffff;
      font-weight:600;
      font-size:14px;
      cursor:pointer;
      box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.2);
      text-decoration:none;
      transition: transform 0.15s, box-shadow 0.15s;
    }
    .primary-btn span.icon{
      font-size:16px;
    }
    .primary-btn:hover{
      transform: translateY(-1px);
      box-shadow: 0 6px 8px -1px rgba(2, 132, 199, 0.3);
    }

    .meta{
      font-size:12px;
      color:var(--muted);
    }
    .meta code{
      background: #f1f5f9; /* Gris très clair pour le code */
      padding:2px 5px;
      border-radius:4px;
      font-size:11px;
      border:1px solid var(--border);
      color: #334155;
      font-family: monospace;
    }

    @media (max-width:780px){
      header{padding:12px 18px;}
      .wrap{padding: 20px 15px;}
      .card{padding:20px 16px;}
      .grid{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
  <header>
    </header>

  <div class="wrap">
    <div class="card">
      <span class="pill">
        <span class="dot"></span>
        Secured by Sumsub • KYC / AML
      </span>
      <h1>Verify your identity to access Tookle</h1>
      <p class="subtitle">
        For regulatory reasons (KYC / AML), we must verify your identity before granting you
        full access to Tookle features (investments, escrow, tokenized flows, etc.).
      </p>

      <div class="grid">
        <div class="block">
          <h2>Verification Process</h2>
          <ul class="steps">
            <li>
              <span class="badge">1</span>
              <div>
                <strong>Identity Document</strong>
                Valid national ID card or passport.
              </div>
            </li>
            <li>
              <span class="badge">2</span>
              <div>
                <strong>Proof of Address</strong>
                Utility bill (water, electricity, internet), bank statement, or recent tax notice.
              </div>
            </li>
            <li>
              <span class="badge">3</span>
              <div>
                <strong>Liveness Selfie</strong>
                Biometric verification to ensure you are the document holder.
              </div>
            </li>
            <li>
              <span class="badge">4</span>
              <div>
                <strong>Automated KYC &amp; AML Checks</strong>
                Sumsub performs sanctions, PEP, adverse media checks, and KYC review.
              </div>
            </li>
          </ul>
        </div>

        <div class="block cta-area">
          <div>
            <h2>Start Now</h2>
            <p class="subtitle" style="margin:4px 0 10px;">
              Verification usually takes a few minutes. You can resume later if you interrupt the process.
            </p>
          </div>
          <a href="<?= hp($startKycUrl) ?>" class="primary-btn" target="_top">
            <span class="icon">⚡</span>
            <span>Start my verification</span>
          </a>
          <p class="meta">
            Your verification ID is automatically generated from your Tookle account.<br>
            Data is processed via our partner <strong>Sumsub</strong> and is used solely for regulatory compliance purposes.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>