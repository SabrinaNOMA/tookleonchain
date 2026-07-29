<?php
declare(strict_types=1);
session_start();

/**
 * kyc_portal.php (PROD CLEAN VERSION)
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
    // Guest fallback: remains functional, but in prod
    // you probably want to enforce Tookle login
    $externalUserId = 'guest_' . bin2hex(random_bytes(6));
}

// Email / phone from session if available
$email = $_SESSION['email'] ?? null;
$phone = $_SESSION['phone'] ?? null;

// Parameters for the KYC start URL
$params = [
    'user' => $externalUserId,
    // 'mode' => 'redirect', // useless as redirect is the default mode in start_kyc.php
];

// Only pass email/phone if they exist
if ($email) $params['email'] = $email;
if ($phone) $params['phone'] = $phone;

// Let start_kyc.php choose the default level (SUMSUB_LEVEL)
// but you can force it here e.g.: $params['level'] = 'leveltookle';
$startKycUrl = $baseUrlStart . '?' . http_build_query($params);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Tookle • KYC Verification</title>
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
    <a href="https://dev.tookle.app/tookle2/settings" class="backlink">← Back to my dashboard</a>
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
          <a href="<?= hp($startKycUrl) ?>" class="primary-btn">
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