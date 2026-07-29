<?php 
declare(strict_types=1);
session_start();

header('Content-Type: text/html; charset=utf-8');

/**
 * kyc_status_widget.php
 * - En POST: reçoit email, résout user.id puis applicant_id (via kyc_applicants.external_user_id = 'user_{id}'), stocke en session.
 * - En GET/affichage: lit applicantId depuis session (ou ?applicantId=...), et affiche un widget KYC/AML.
 *
 * Dépendances:
 *   - ../config/db.php           retourne ['dsn' => ..., 'user' => ..., 'pass' => ...]
 *   - ../config/secrets.php      optionnel: ['TOOKLE_API_KEY' => '...']
 * Endpoints attendus côté serveur:
 *   - /sumsub/api/kyc_status.php?applicantId=...
 *   - /sumsub/api/aml_status.php?applicantId=...
 */

// Helpers
function hp(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function abort_msg(string $msg): never {
  http_response_code(500);
  echo "<!doctype html><meta charset='utf-8'><pre>".hp($msg)."</pre>";
  exit;
}

// ---------------------------------------------------------
// (1) Résolution par email (POST) => session applicant_id
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
    $email = trim((string)$_POST['email']);

    // Charger la config DB
    $dbCfg = __DIR__ . '/../config/db.php';
    if (!is_file($dbCfg)) abort_msg('DB config missing');
    $db = require $dbCfg;

    try {
        $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // 1) Récupérer l'id utilisateur depuis la table user par email
        $st = $pdo->prepare('SELECT id FROM user WHERE email = :email LIMIT 1');
        $st->execute([':email' => $email]);
        $user = $st->fetch();

        $resolvedApplicantId = null;

        if ($user && !empty($user['id'])) {
            $userId = (int)$user['id'];
            $externalUserId = 'user_' . $userId;

            // 2) Récupérer applicant_id via kyc_applicants.external_user_id
            $st2 = $pdo->prepare(
                'SELECT applicant_id
                   FROM kyc_applicants
                  WHERE external_user_id = :ext
                  ORDER BY updated_at DESC, created_at DESC
                  LIMIT 1'
            );
            $st2->execute([':ext' => $externalUserId]);
            $row2 = $st2->fetch();

            if (!empty($row2['applicant_id'])) {
                $resolvedApplicantId = (string)$row2['applicant_id'];
            }

            // Sauvegarder aussi l’externalUserId pour d’éventuels usages
            $_SESSION['sumsub_external_user'] = $externalUserId;
        } else {
            // Aucun user pour cet email
            $_SESSION['sumsub_external_user'] = null;
        }

        // Mettre en session l’applicant_id (ou null)
        $_SESSION['sumsub_applicant_id'] = $resolvedApplicantId;

        // PRG pour éviter le repost
        $url = strtok((string)$_SERVER['REQUEST_URI'], '#'); // retire l’ancre éventuelle
        header('Location: ' . $url, true, 303);
        exit;

    } catch (Throwable $e) {
        abort_msg('DB error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------
// (2) Lecture applicantId (GET override > session)
// ---------------------------------------------------------
$applicantId = null;
if (!empty($_GET['applicantId'])) {
    $applicantId = trim((string)$_GET['applicantId']);
    $_SESSION['sumsub_applicant_id'] = $applicantId;
} elseif (!empty($_SESSION['sumsub_applicant_id'])) {
    $applicantId = (string)$_SESSION['sumsub_applicant_id'];
}

// ---------------------------------------------------------
// (3) Charger l'API KEY pour appels aux endpoints JSON
// ---------------------------------------------------------
$apiKey = 'change_this_super_secret_key'; // fallback
$secCfg = __DIR__ . '/../config/secrets.php';
if (is_file($secCfg)) {
    $cx = require $secCfg;
    if (is_array($cx) && !empty($cx['TOOKLE_API_KEY'])) {
        $apiKey = (string)$cx['TOOKLE_API_KEY'];
    }
}

// ---------------------------------------------------------
// (4) HTML + Widget JS
// ---------------------------------------------------------
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>KYC / AML Status – Widget</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root{
      --bg:#ffffff; --text:#111827; --muted:#6b7280; --border:#e5e7eb;
      --ok:#16a34a; --ko:#dc2626; --pending:#eab308; --chip:#f3f4f6; --blue:#2563eb;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial}
    .wrap{max-width:960px;margin:32px auto;padding:0 16px}
    .card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px;box-shadow:0 4px 12px rgba(0,0,0,.06)}
    h1{margin:0 0 8px;font-size:22px}
    .muted{color:var(--muted);font-size:13px}
    .row{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:var(--chip);border:1px solid var(--border);font-weight:600}
    .pill.ok{color:var(--ok);border-color:rgba(22,163,74,.35);background:rgba(22,163,74,.08)}
    .pill.ko{color:var(--ko);border-color:rgba(220,38,38,.35);background:rgba(220,38,38,.08)}
    .pill.pending{color:var(--pending);border-color:rgba(234,179,8,.35);background:rgba(234,179,8,.08)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
    .item{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px}
    .k{display:block;font-size:12px;color:var(--muted)}
    .v{display:block;margin-top:4px;font-weight:600}
    .actions{display:flex;gap:10px;margin-top:10px}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;background:var(--blue);color:#fff;text-decoration:none;font-weight:600}
    .btn.secondary{background:#4b5563}
    form.inline{display:flex;gap:10px;align-items:center;margin-bottom:14px}
    form.inline input[type="email"]{padding:8px 10px;border:1px solid var(--border);border-radius:8px;min-width:280px}
    form.inline button{padding:8px 12px;border:0;border-radius:8px;background:#111827;color:#fff}
    .tiny{font-size:12px;color:var(--muted)}
    pre.log{max-height:240px;overflow:auto;background:#0b1020;color:#e5e7eb;padding:10px;border-radius:8px;border:1px solid #1f2a3e}
    .right{margin-left:auto}
  </style>
</head>
<body>
  <div class="wrap">

    <!-- Formulaire de résolution par email -->
    <form method="post" class="inline" action="">
      <label for="email">Trouver un applicant par email&nbsp;:</label>
      <input type="email" id="email" name="email" placeholder="investor@domain.tld" required />
      <button type="submit">Trouver</button>
      <span class="tiny">Résout l’applicant associé et l’enregistre en session.</span>
    </form>

    <div class="card">
      <h1>Status Center – KYC / AML</h1>
      <div class="muted">
        <?php if ($applicantId): ?>
          Applicant ID courant&nbsp;: <strong><?= hp($applicantId) ?></strong>
        <?php else: ?>
          Aucun <code>applicantId</code> en session. Utilisez le formulaire ci‐dessus ou passez <code>?applicantId=...</code> dans l’URL.
        <?php endif; ?>
      </div>

      <div class="row">
        <span class="pill" id="badgeKyc">KYC Status: —</span>
        <span class="pill" id="badgeAml">AML Status: —</span>
        <a class="pill right" id="toggleRaw" href="#" style="text-decoration:none">👁️ Show raw JSON</a>
      </div>

      <div class="grid">
        <div class="item">
          <span class="k">KYC</span>
          <span class="v" id="kycText">—</span>
        </div>
        <div class="item">
          <span class="k">AML</span>
          <span class="v" id="amlText">—</span>
        </div>
      </div>

      <div class="actions">
        <a class="btn" id="openKyc" target="_blank" rel="noopener">Open KYC Result</a>
        <a class="btn secondary" id="openAml" target="_blank" rel="noopener">Open AML Result</a>
      </div>

      <div id="rawZone" style="display:none;margin-top:14px">
        <h3 style="margin:10px 0 6px 0;">Debug JSON</h3>
        <pre class="log" id="rawJson">—</pre>
      </div>

      <div class="tiny" style="margin-top:10px">
        Astuce: ce widget consomme vos endpoints JSON sécurisés (<code>/sumsub/api/kyc_status.php</code> et <code>/sumsub/api/aml_status.php</code>) avec un header <code>Authorization: Bearer &lt;TOOKLE_API_KEY&gt;</code>.
      </div>
    </div>
  </div>

<script>
(function(){
  const APPLICANT_ID = <?= $applicantId ? json_encode($applicantId) : 'null' ?>;
  const API_KEY      = <?= json_encode($apiKey) ?>;

  const $badgeKyc = document.getElementById('badgeKyc');
  const $badgeAml = document.getElementById('badgeAml');
  const $kycText  = document.getElementById('kycText');
  const $amlText  = document.getElementById('amlText');
  const $openKyc  = document.getElementById('openKyc');
  const $openAml  = document.getElementById('openAml');
  const $rawZone  = document.getElementById('rawZone');
  const $rawJson  = document.getElementById('rawJson');
  const $toggleRaw= document.getElementById('toggleRaw');

  function badge(el, type, text){
    el.className = 'pill';
    if (type === 'ok') el.classList.add('ok');
    else if (type === 'ko') el.classList.add('ko');
    else if (type === 'pending') el.classList.add('pending');
    el.textContent = text;
  }
  function kycLabel(s, a){
    if (!s) return '—';
    if (s === 'init') return '⌛ En cours d’examen (init)';
    if (s === 'pending' || s === 'queued') return '⌛ En cours d’examen';
    if (s === 'completed' && a === 'GREEN') return '✅ Vérification réussie';
    if (s === 'completed' && a === 'RED')   return '❌ Refusé';
    return s + (a ? (' / ' + a) : '');
  }
  function amlLabel(x){
    if (!x) return '—';
    const g = ((x.global || '') + '').toUpperCase();
    if (g === 'CLEAR')  return '✅ Clear';
    if (g === 'MATCH')  return '❌ Match';
    if (g === 'REVIEW' || g === 'PENDING') return '⏳ En revue';
    return '– N/A';
  }

  function updateLinks(appId){
    $openKyc.href = '/sumsub/public/kyc_result.php?applicantId=' + encodeURIComponent(appId);
    $openAml.href = '/sumsub/public/kyc_aml_result.php?applicantId=' + encodeURIComponent(appId);
  }

  function fetchJson(url){
    return fetch(url, {
      headers: { 'Authorization': 'Bearer ' + API_KEY }
    }).then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  $toggleRaw.addEventListener('click', function(e){
    e.preventDefault();
    const vis = $rawZone.style.display !== 'none';
    $rawZone.style.display = vis ? 'none' : 'block';
    $toggleRaw.textContent = vis ? '👁️ Show raw JSON' : '🙈 Hide raw JSON';
  });

  if (!APPLICANT_ID) {
    badge($badgeKyc, 'pending', 'KYC Status: —');
    badge($badgeAml, 'pending', 'AML Status: —');
    $kycText.textContent = 'Aucun applicantId sélectionné.';
    $amlText.textContent = 'Saisissez un email ci-dessus pour résoudre un applicant.';
    $openKyc.removeAttribute('href');
    $openAml.removeAttribute('href');
    return;
  }

  updateLinks(APPLICANT_ID);

  const base = '/sumsub/api';
  const kycURL = base + '/kyc_status.php?applicantId=' + encodeURIComponent(APPLICANT_ID);
  const amlURL = base + '/aml_status.php?applicantId=' + encodeURIComponent(APPLICANT_ID);

  Promise.allSettled([ fetchJson(kycURL), fetchJson(amlURL) ]).then(([kyc, aml]) => {
    let raw = {};
    if (kyc.status === 'fulfilled') {
      const s = (kyc.value && kyc.value.reviewStatus) || null;
      const a = (kyc.value && kyc.value.reviewAnswer) || null;
      $kycText.textContent = kycLabel(s, a);
      if (s === 'completed' && a === 'GREEN') badge($badgeKyc, 'ok', 'KYC Status: GREEN');
      else if (s === 'completed' && a === 'RED') badge($badgeKyc, 'ko', 'KYC Status: RED');
      else badge($badgeKyc, 'pending', 'KYC Status: pending');
      raw.kyc = kyc.value;
    } else {
      $kycText.textContent = 'Erreur KYC: ' + kyc.reason;
      badge($badgeKyc, 'ko', 'KYC Status: error');
      raw.kyc_error = String(kyc.reason || '');
    }

    if (aml.status === 'fulfilled') {
      $amlText.textContent = amlLabel(aml.value);
      const g = ((aml.value || {}).global || '').toUpperCase();
      if (g === 'CLEAR') badge($badgeAml, 'ok', 'AML Status: CLEAR');
      else if (g === 'MATCH') badge($badgeAml, 'ko', 'AML Status: MATCH');
      else badge($badgeAml, 'pending', 'AML Status: pending/N-A');
      raw.aml = aml.value;
    } else {
      $amlText.textContent = 'Erreur AML: ' + aml.reason;
      badge($badgeAml, 'ko', 'AML Status: error');
      raw.aml_error = String(aml.reason || '');
    }

    $rawJson.textContent = JSON.stringify(raw, null, 2);
  }).catch(err => {
    $kycText.textContent = 'Erreur: ' + err.message;
    $amlText.textContent = 'Erreur: ' + err.message;
    badge($badgeKyc, 'ko', 'KYC Status: error');
    badge($badgeAml, 'ko', 'AML Status: error');
    $rawJson.textContent = JSON.stringify({ fatal: String(err) }, null, 2);
  });
})();
</script>
</body>
</html>
