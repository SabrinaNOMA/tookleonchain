<?php
declare(strict_types=1);
session_start();

header('Content-Type: text/html; charset=utf-8');

// Récup l'applicantId depuis la session (posé par start_kyc.php)
$applicantId = $_SESSION['sumsub_applicant_id'] ?? null;

// (Option) URL d’entrée pour (re)lancer le KYC si pas de session
$portalUrl = '/sumsub/public/kyc_portal.php';

// (Option) Clé API interne (sinon la page front lira depuis config côté JS)
$cfg = is_file(__DIR__ . '/../config/secrets.php') ? (require __DIR__ . '/../config/secrets.php') : [];
$TOOKLE_API_KEY = $cfg['TOOKLE_API_KEY'] ?? 'change_this_super_secret_key';

// Racine des endpoints JSON
$BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . ''; // ex: /sumsub/public
$BASE_URL = str_replace('/kyc_status_widget.php', '', $_SERVER['SCRIPT_NAME']); // robustesse
$BASE_URL = rtrim(dirname($BASE_URL), '/'); // /sumsub/public
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
      This page fetches statuses.
    </p>

    <?php if (!$applicantId): ?>
      <div class="p-4 border rounded-xl bg-amber-50 border-amber-200 text-amber-800">
        <div class="font-semibold mb-1">No applicant in session</div>
        <div class="text-sm">Start a verification first.</div>
        <a href="<?= htmlspecialchars($portalUrl) ?>" class="inline-block mt-3 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Open KYC Portal</a>
      </div>
    <?php else: ?>
      <div id="app" data-applicant-id="<?= htmlspecialchars($applicantId) ?>"></div>

      <div class="border rounded-xl p-4 shadow-sm mt-4">
        <div class="flex flex-wrap items-center gap-2 text-sm mb-3">
          <span class="px-2 py-1 rounded-full bg-slate-100" id="idApplicant">Applicant: <?= htmlspecialchars($applicantId) ?></span>
          <span class="px-2 py-1 rounded-full bg-slate-100" id="idExternal">User: —</span>
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
const BASE_URL = "<?= htmlspecialchars($BASE_URL) ?>"; // /sumsub/public
const API_KEY  = "<?= htmlspecialchars($TOOKLE_API_KEY) ?>";
const APPLICANT_ID = "<?= htmlspecialchars($applicantId) ?>";

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
