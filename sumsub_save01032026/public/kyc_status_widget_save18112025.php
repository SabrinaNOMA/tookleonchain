<?php
declare(strict_types=1);
session_start();

header('Content-Type: text/html; charset=utf-8');

/**
 * kyc_status_widget.php
 * - En POST: reçoit email, résout user.id, construit external_user_id = 'user_<id>',
 *            récupère applicant_id dans kyc_applicants via external_user_id,
 *            stocke en session et redirige (PRG).
 * - En GET: lit applicantId depuis session (ou ?applicantId=...), puis affiche le widget.
 *
 * Dépendances back:
 *   - ../config/db.php           → ['dsn' => ..., 'user' => ..., 'pass' => ...]
 *   - ../config/secrets.php      (optionnel) ['TOOKLE_API_KEY' => '...']
 * Endpoints JSON consommés côté JS:
 *   - /sumsub/api/kyc_status.php?applicantId=...
 *   - /sumsub/api/aml_status.php?applicantId=...
 */

function hp(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function abort_msg(string $msg, int $code=500): never {
  http_response_code($code);
  echo "<!doctype html><meta charset='utf-8'><pre>".hp($msg)."</pre>";
  exit;
}

/* ---------- (0) Chemins & config ---------- */
$portalUrl = '/sumsub/public/kyc_portal.php';
$cfgSecrets = is_file(__DIR__ . '/../config/secrets.php') ? (require __DIR__ . '/../config/secrets.php') : [];
$TOOKLE_API_KEY = $cfgSecrets['TOOKLE_API_KEY'] ?? 'change_this_super_secret_key';

/* Base URL calculée proprement (ex: /sumsub/public) */
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/sumsub/public/kyc_status_widget.php';
$BASE_DIR   = rtrim(str_replace('/kyc_status_widget.php', '', $scriptPath), '/'); // ex: /sumsub/public

/* ---------- (1) Traitement POST (email → applicantId en session) ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_POST['email'])) {
  $email = trim((string)$_POST['email']);

  // Chargement DB
  $dbCfgPath = __DIR__ . '/../config/db.php';
  if (!is_file($dbCfgPath)) abort_msg('DB config missing at ' . $dbCfgPath);
  $db = require $dbCfgPath;

  try {
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 1) Trouver l'utilisateur par email
    $st = $pdo->prepare('SELECT id FROM user WHERE email = :email LIMIT 1');
    $st->execute([':email' => $email]);
    $user = $st->fetch();

    $resolvedApplicantId = null;
    if ($user && !empty($user['id'])) {
      $userId = (int)$user['id'];
      $externalUserId = 'user_' . $userId;

      // 2) Chercher applicant via external_user_id
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
    }

    // 3) Enregistrer en session (même si null pour signaler "non trouvé")
    $_SESSION['sumsub_applicant_id'] = $resolvedApplicantId;

    // 4) PRG redirect vers l’URL *sans* query-string (évite les boucles)
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? $scriptPath, PHP_URL_PATH) ?: $scriptPath;
    header('Location: ' . $uri, true, 303);
    exit;

  } catch (Throwable $e) {
    abort_msg('DB error: ' . $e->getMessage());
  }
}

/* ---------- (2) Source applicantId: GET override > session ---------- */
$applicantId = null;
if (!empty($_GET['applicantId'])) {
  $applicantId = trim((string)$_GET['applicantId']);
  $_SESSION['sumsub_applicant_id'] = $applicantId;
} elseif (!empty($_SESSION['sumsub_applicant_id'])) {
  $applicantId = (string)$_SESSION['sumsub_applicant_id'];
}

/* ---------- (3) HTML ---------- */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>Tookle • KYC/AML Status (Session)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900">
  <div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-1">KYC / AML Status (Session)</h1>
    <p class="text-sm text-slate-500 mb-6">
      This page fetches KYC / AML status using your JSON endpoints.
    </p>

    <!-- Form de résolution par email (POST) -->
    <form method="post" action="<?= hp($scriptPath) ?>" class="mb-4 flex gap-2 items-center">
      <label for="email" class="text-sm text-slate-600">Find applicant by email:</label>
      <input id="email" name="email" type="email" required placeholder="investor@domain.tld"
             class="px-3 py-2 border rounded-md border-slate-300 min-w-[260px]">
      <button type="submit" class="px-3 py-2 rounded-md bg-slate-900 text-white hover:bg-slate-800 text-sm">Search</button>
      <span class="text-xs text-slate-400">Resolves user → external_user_id → applicant_id, then stores in session.</span>
    </form>

    <?php if (!$applicantId): ?>
      <div class="p-4 border rounded-xl bg-amber-50 border-amber-200 text-amber-800">
        <div class="font-semibold mb-1">No applicant selected</div>
        <div class="text-sm">Use the form above or start a verification first.</div>
        <a href="<?= hp($portalUrl) ?>"
           class="inline-block mt-3 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
          Open KYC Portal
        </a>
      </div>
    <?php else: ?>
      <div class="border rounded-xl p-4 shadow-sm mt-4">
        <div class="flex flex-wrap items-center gap-2 text-sm mb-3">
          <span class="px-2 py-1 rounded-full bg-slate-100" id="idApplicant">Applicant: <?= hp($applicantId) ?></span>
          
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">KYC</div>
            <div class="mt-1 font-semibold flex items-center gap-2">
              <span id="kycBadge" class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span>
              <span id="kycDetail" class="text-sm text-slate-500">Status: —</span>
            </div>
            <div class="text-xs text-slate-400 mt-1" id="kycReviewed">Reviewed: —</div>
            <ul id="kycLabels" class="text-sm text-rose-600 list-disc list-inside mt-2 hidden"></ul>
          </div>

          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">Sanctions</div>
            <div class="mt-1 font-semibold"><span id="amlSanctions" class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span></div>
            <div class="text-xs text-slate-400 mt-1" id="amlSanctionsMeta">Lists: — • Checked: —</div>
          </div>

          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">PEP</div>
            <div class="mt-1 font-semibold"><span id="amlPep" class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span></div>
            <div class="text-xs text-slate-400 mt-1" id="amlPepMeta">Checked: —</div>
          </div>

          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">Adverse Media</div>
            <div class="mt-1 font-semibold"><span id="amlAdverse" class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span></div>
            <div class="text-xs text-slate-400 mt-1" id="amlAdverseMeta">Checked: —</div>
          </div>
        </div>

        <div class="mt-4 flex gap-2">
          <button id="btnRefresh" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Refresh</button>
          <button id="btnAuto" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-800 hover:bg-slate-200">Auto-refresh: OFF</button>
        </div>

        <details class="mt-4">
          <summary class="cursor-pointer select-none text-sm text-slate-500 hover:text-slate-700">Show raw JSON</summary>
          <pre id="debugJson" class="mt-3 text-xs bg-slate-50 p-3 rounded-lg overflow-auto"></pre>
        </details>
      </div>
    <?php endif; ?>

    <div id="toast" class="fixed bottom-4 right-4 hidden px-3 py-2 rounded-md bg-slate-900 text-white text-sm shadow-lg"></div>
  </div>

<?php if ($applicantId): ?>
<script>
const BASE_URL = <?= json_encode($BASE_DIR) ?>; // ex: "/sumsub/public"
const API_KEY  = <?= json_encode($TOOKLE_API_KEY) ?>;
const APPLICANT_ID = <?= json_encode($applicantId) ?>;

const $ = (q) => document.querySelector(q);
function toast(msg, ok=true) {
  const t = $('#toast');
  t.textContent = msg;
  t.className = `fixed bottom-4 right-4 px-3 py-2 rounded-md text-sm shadow-lg ${ok?'bg-emerald-600':'bg-rose-600'} text-white`;
  t.style.display = 'block';
  setTimeout(()=> t.style.display='none', 2500);
}
function pill(el, label, tone) {
  const map = {
    ok: 'bg-emerald-100 text-emerald-700 border border-emerald-300',
    ko: 'bg-rose-100 text-rose-700 border border-rose-300',
    pending: 'bg-amber-100 text-amber-700 border border-amber-300',
    na: 'bg-slate-100 text-slate-700 border border-slate-300'
  };
  el.className = `inline-flex items-center px-2 py-1 rounded-full text-sm ${map[tone]||map.na}`;
  el.textContent = label;
}
const badgeForKyc = (reviewStatus, reviewAnswer) => {
  if (reviewStatus === 'completed' && reviewAnswer === 'GREEN') return ['✅ Approved','ok'];
  if (reviewStatus === 'completed' && reviewAnswer === 'RED')   return ['❌ Rejected','ko'];
  return ['⌛ Under review','pending'];
};
const badgeForAml = (result) => {
  const r = (result||'').toUpperCase();
  if (r === 'CLEAR') return ['✅ Clear','ok'];
  if (r === 'MATCH') return ['❌ Match','ko'];
  if (r === 'REVIEW' || r === 'PENDING') return ['⏳ In review','pending'];
  return ['– N/A','na'];
};

const GET = async (path, params={}) => {
  const url = new URL(path, window.location.origin + BASE_URL);
  Object.entries(params).forEach(([k,v]) => v!=null && url.searchParams.set(k, v));
  const res = await fetch(url.toString(), { headers: { 'Authorization': `Bearer ${API_KEY}` } });
  const data = await res.json().catch(()=> ({}));
  if (!res.ok || data.ok === false) {
    const msg = data.error || `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
};

async function refresh() {
  try {
    // KYC
    const kyc = await GET('/api/kyc_status.php', { applicantId: APPLICANT_ID });
    $('#idApplicant').textContent = `Applicant: ${kyc.applicantId || '—'}`;
    $('#idExternal').textContent  = `User: ${kyc.externalUserId || '—'}`;
    const rs = kyc.kyc?.reviewStatus || null;
    const ra = kyc.kyc?.reviewAnswer || null;
    const [kycLabel, kycTone] = badgeForKyc(rs, ra);
    pill($('#kycBadge'), kycLabel, kycTone);
    $('#kycDetail').textContent = `Status: ${rs || '—'}${ra ? ` • Decision: ${ra}` : ''}`;
    $('#kycReviewed').textContent = `Reviewed: ${kyc.kyc?.reviewedAt || '—'}`;
    const labels = Array.isArray(kyc.kyc?.labels) ? kyc.kyc.labels : [];
    const ul = $('#kycLabels'); ul.innerHTML = '';
    if (ra === 'RED' && labels.length) {
      ul.classList.remove('hidden');
      labels.forEach(l => {
        const li = document.createElement('li');
        li.textContent = (typeof l === 'string') ? l : (l?.label || JSON.stringify(l));
        ul.appendChild(li);
      });
    } else { ul.classList.add('hidden'); }

    // AML
    const aml = await GET('/api/aml_status.php', { applicantId: APPLICANT_ID });
    const setAml = (node, metaNode, obj) => {
      const [label, tone] = badgeForAml(obj?.result || null);
      pill(node, label, tone);
      if (metaNode) {
        const checked = obj?.checkedAt || '—';
        if (obj && obj.lists) {
          const lists = Array.isArray(obj.lists) ? obj.lists.join(', ') : '—';
          metaNode.textContent = `Lists: ${lists} • Checked: ${checked}`;
        } else {
          metaNode.textContent = `Checked: ${checked}`;
        }
      }
    };
    setAml($('#amlSanctions'), $('#amlSanctionsMeta'), aml.aml?.sanctions || null);
    setAml($('#amlPep'),       $('#amlPepMeta'),       aml.aml?.pep || null);
    setAml($('#amlAdverse'),   $('#amlAdverseMeta'),   aml.aml?.adverse_media || null);

    $('#debugJson').textContent = JSON.stringify({kyc, aml}, null, 2);
    toast('Refreshed');
  } catch (e) {
    console.error(e);
    toast(`Error: ${e.message}`, false);
  }
}

let auto=false, timer=null;
document.getElementById('btnRefresh').addEventListener('click', refresh);
document.getElementById('btnAuto').addEventListener('click', ()=>{
  auto = !auto;
  const b = document.getElementById('btnAuto');
  b.textContent = `Auto-refresh: ${auto ? 'ON' : 'OFF'}`;
  b.className = `px-4 py-2 rounded-lg ${auto ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'}`;
  clearInterval(timer);
  if (auto) timer = setInterval(refresh, 5000);
});
document.addEventListener('DOMContentLoaded', refresh);
</script>
<?php endif; ?>
</body>
</html>
