<?php
declare(strict_types=1);
session_start();

header('Content-Type: text/html; charset=utf-8');

/**
 * kyc_status_widget.php
 * 1) En POST:
 *    - Reçoit un email (hidden ou form)
 *    - SELECT id FROM user WHERE email = :email
 *    - Construit externalUserId = 'sess_' . id
 *    - SELECT applicant_id FROM kyc_applicants WHERE external_user_id = :ext
 *    - Stocke applicant_id en session (sumsub_applicant_id)
 *    - PRG: redirection 303 vers la même page (GET)
 *
 * 2) En GET:
 *    - Lit $_SESSION['sumsub_applicant_id']
 *    - Affiche le widget KYC / AML qui consomme:
 *        /sumsub/api/kyc_status.php?applicantId=...
 *        /sumsub/api/aml_status.php?applicantId=...
 *
 * ✅ Ajouts:
 *    - Support GET ?applicantId=... (session + PRG)
 *    - Affichage Identité (prénom, nom, dob, pays) via Sumsub /one
 */

// ---------- Helper pour erreurs simples ----------
function abort_msg(string $msg): never {
    http_response_code(500);
    echo "<!doctype html><meta charset='utf-8'><pre>" . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</pre>";
    exit;
}

/* ---------------------------------------------------------
 * (0) ✅ Support GET applicantId=... -> session + PRG (évite 500 / URL propre)
 * --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['applicantId'])) {
    $_SESSION['sumsub_applicant_id'] = trim((string)$_GET['applicantId']);
    $target = strtok($_SERVER['REQUEST_URI'], '?'); // même script sans query
    header('Location: ' . $target, true, 303);
    exit;
}

// ---------------------------------------------------------
// (1) Résolution par email (POST) → applicantId en session
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
    $email = trim((string)$_POST['email']);

    // Charger la config DB
    $dbCfg = __DIR__ . '/../config/db.php';
    if (!is_file($dbCfg)) {
        abort_msg('DB config missing: ' . $dbCfg);
    }
    $db = require $dbCfg;

    try {
        $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        abort_msg('DB connection error: ' . $e->getMessage());
    }

    $resolvedApplicantId = null;

    try {
        // 1) Récupérer l'ID user à partir de l'email
        $st = $pdo->prepare('SELECT id FROM user WHERE email = :email LIMIT 1');
        $st->execute([':email' => $email]);
        $user = $st->fetch();

        if ($user && !empty($user['id'])) {
            $userId = (int)$user['id'];

            // 2) Construire externalUserId = 'sess_' . id
            $externalUserId = 'sess_' . $userId;

            // 3) Récupérer applicant_id dans kyc_applicants via external_user_id
            $st2 = $pdo->prepare(
                'SELECT applicant_id
                   FROM kyc_applicants
                  WHERE external_user_id = :ext
                  ORDER BY updated_at DESC, created_at DESC
                  LIMIT 1'
            );
            $st2->execute([':ext' => $externalUserId]);
            $row = $st2->fetch();

            if (!empty($row['applicant_id'])) {
                $resolvedApplicantId = (string)$row['applicant_id'];
            }
        }

        // 4) Sauvegarde en session (peut être null si rien trouvé)
        $_SESSION['sumsub_applicant_id'] = $resolvedApplicantId;

        // 5) PRG: redirection 303 vers la même URL en GET
        $target = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $target, true, 303);
        exit;

    } catch (Throwable $e) {
        abort_msg('DB error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------
// (2) En GET: lecture applicantId depuis la session
// ---------------------------------------------------------
$applicantId = $_SESSION['sumsub_applicant_id'] ?? null;

// (Option) URL d’entrée pour relancer le KYC si pas d’applicant
$portalUrl = '/sumsub/public/kyc_portal.php';

// (Option) Clé API interne pour appeler /api/*.php
$sec = is_file(__DIR__ . '/../config/secrets.php')
    ? (require __DIR__ . '/../config/secrets.php')
    : [];
$TOOKLE_API_KEY = $sec['TOOKLE_API_KEY'] ?? 'change_this_super_secret_key';

// ✅ Ajout: Sumsub creds (si présents) pour lire l'identité via /one
$SUMSUB_APP_TOKEN  = $sec['SUMSUB_APP_TOKEN'] ?? '';
$SUMSUB_APP_SECRET = $sec['SUMSUB_APP_SECRET'] ?? '';
$SUMSUB_BASE_URL   = 'https://api.sumsub.com';

// Racine des endpoints JSON (ex: /sumsub/public)
$BASE_URL = str_replace('/kyc_status_widget.php', '', $_SERVER['SCRIPT_NAME']);
$BASE_URL = rtrim(dirname($BASE_URL), '/');

// ---------------------------------------------------------
// (3) ✅ Identité (server-side) : prénom/nom/dob/pays via Sumsub /one
// ---------------------------------------------------------
$identity = [
    'firstName' => '—',
    'lastName'  => '—',
    'dob'       => '—',
    'country'   => '—',
];

function sumsub_sign(string $ts, string $method, string $uriWithQuery, string $body, string $secret): string {
    $payload = $ts . strtoupper($method) . $uriWithQuery . $body;
    return hash_hmac('sha256', $payload, $secret);
}

function sumsub_get_json(string $baseUrl, string $appToken, string $secret, string $uriWithQuery): array {
    $method = 'GET';
    $ts = (string)time();
    $body = '';
    $sig = sumsub_sign($ts, $method, $uriWithQuery, $body, $secret);

    $ch = curl_init($baseUrl . $uriWithQuery);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'X-App-Token: ' . $appToken,
            'X-App-Access-Ts: ' . $ts,
            'X-App-Access-Sig: ' . $sig,
        ],
    ]);

    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('cURL error: ' . $err);
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException("Sumsub invalid JSON (HTTP $http): " . $raw);
    }
    if ($http < 200 || $http >= 300) {
        $msg = $json['description'] ?? $json['message'] ?? $json['error'] ?? 'Sumsub API error';
        throw new RuntimeException("Sumsub error (HTTP $http): $msg");
    }
    return $json;
}

if ($applicantId && $SUMSUB_APP_TOKEN !== '' && $SUMSUB_APP_SECRET !== '') {
    try {
        // /resources/applicants/{id}/one
        $profile = sumsub_get_json(
            $SUMSUB_BASE_URL,
            $SUMSUB_APP_TOKEN,
            $SUMSUB_APP_SECRET,
            '/resources/applicants/' . rawurlencode((string)$applicantId) . '/one'
        );

        $info  = is_array($profile['info'] ?? null) ? $profile['info'] : [];
        $fixed = is_array($profile['fixedInfo'] ?? null) ? $profile['fixedInfo'] : [];

        $firstName = (string)($info['firstName'] ?? $fixed['firstName'] ?? '');
        $lastName  = (string)($info['lastName']  ?? $fixed['lastName']  ?? '');
        $dob       = (string)($info['dob']       ?? $fixed['dob']       ?? '');
        $country   = (string)($info['country']   ?? $fixed['country']   ?? '');

        $identity['firstName'] = $firstName !== '' ? $firstName : '—';
        $identity['lastName']  = $lastName  !== '' ? $lastName  : '—';
        $identity['dob']       = $dob       !== '' ? $dob       : '—';
        $identity['country']   = $country   !== '' ? $country   : '—';

    } catch (Throwable $e) {
        // On ne casse pas le widget : identité indisponible => on garde "—"
        // (utile si mismatch tenant / applicantId pas trouvé -> 404)
        error_log('[kyc_status_widget] Identity fetch error: ' . $e->getMessage());
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
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
      This page displays the latest KYC / AML status for the current Sumsub applicant.
    </p>

    <?php if (!$applicantId): ?>
      <div class="p-4 border rounded-xl bg-amber-50 border-amber-200 text-amber-800">
        <div class="font-semibold mb-1">No applicant in session</div>
        <div class="text-sm">
          Start a verification first with the KYC portal,
          or open this widget via the "🔍" button from your investors list (POST email).
        </div>
        <a href="<?= h($portalUrl) ?>"
           class="inline-block mt-3 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
          Open KYC Portal
        </a>
      </div>
    <?php else: ?>

      <!-- ✅ Identité -->
      <div class="border rounded-xl p-4 shadow-sm mb-4">
        <div class="text-xs uppercase text-slate-500 mb-2">Identity</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div class="flex justify-between gap-3 border rounded-lg p-3">
            <span class="text-slate-500">First name</span>
            <span class="font-semibold"><?= h($identity['firstName']) ?></span>
          </div>
          <div class="flex justify-between gap-3 border rounded-lg p-3">
            <span class="text-slate-500">Last name</span>
            <span class="font-semibold"><?= h($identity['lastName']) ?></span>
          </div>
          <div class="flex justify-between gap-3 border rounded-lg p-3">
            <span class="text-slate-500">Date of birth</span>
            <span class="font-semibold"><?= h($identity['dob']) ?></span>
          </div>
          <div class="flex justify-between gap-3 border rounded-lg p-3">
            <span class="text-slate-500">Country</span>
            <span class="font-semibold"><?= h($identity['country']) ?></span>
          </div>
        </div>
      </div>

      <div class="border rounded-xl p-4 shadow-sm mt-4">
        <div class="flex flex-wrap items-center gap-2 text-sm mb-3">
          <span class="px-2 py-1 rounded-full bg-slate-100" id="idApplicant">
            Applicant: <?= h((string)$applicantId) ?>
          </span>
          <span class="px-2 py-1 rounded-full bg-slate-100" id="idExternal">
            User: —
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- KYC -->
          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">KYC</div>
            <div class="mt-1 font-semibold flex items-center gap-2">
              <span id="kycBadge"
                    class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span>
              <span id="kycDetail" class="text-sm text-slate-500">Status: —</span>
            </div>
            <div class="text-xs text-slate-400 mt-1" id="kycReviewed">Reviewed: —</div>
            <ul id="kycLabels"
                class="text-sm text-rose-600 list-disc list-inside mt-2 hidden"></ul>
          </div>

          <!-- Sanctions -->
          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">Sanctions</div>
            <div class="mt-1 font-semibold">
              <span id="amlSanctions"
                    class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span>
            </div>
            <div class="text-xs text-slate-400 mt-1" id="amlSanctionsMeta">
              Lists: — • Checked: —
            </div>
          </div>

          <!-- PEP -->
          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">PEP</div>
            <div class="mt-1 font-semibold">
              <span id="amlPep"
                    class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span>
            </div>
            <div class="text-xs text-slate-400 mt-1" id="amlPepMeta">
              Checked: —
            </div>
          </div>

          <!-- Adverse Media -->
          <div class="border rounded-lg p-3">
            <div class="text-xs uppercase text-slate-500">Adverse Media</div>
            <div class="mt-1 font-semibold">
              <span id="amlAdverse"
                    class="inline-flex items-center px-2 py-1 rounded-full text-sm bg-slate-100">—</span>
            </div>
            <div class="text-xs text-slate-400 mt-1" id="amlAdverseMeta">
              Checked: —
            </div>
          </div>
        </div>

        <div class="mt-4 text-xs text-slate-400">
          Data is fetched from internal JSON endpoints and displayed for convenience.
        </div>
      </div>

      <script>
(async function(){
  try{
    const applicantId = <?= json_encode((string)$applicantId) ?>;

    // ✅ chemins corrects chez toi (pas de /api, pas de api_key)
    const kycUrl = '/sumsub/public/kyc_status.php?applicantId=' + encodeURIComponent(applicantId) + '&force=1';
    const amlUrl = '/sumsub/public/aml_status.php?applicantId=' + encodeURIComponent(applicantId) + '&force=1'; // si tu as ce fichier

    const kycRes = await fetch(kycUrl, { credentials: 'same-origin' });
    const kyc = await kycRes.json().catch(()=>null);

    // Si AML n'existe pas ou renvoie erreur, on n'empêche pas l'affichage KYC
    let aml = null;
    try{
      const amlRes = await fetch(amlUrl, { credentials: 'same-origin' });
      aml = await amlRes.json().catch(()=>null);
    } catch(e){ /* ignore */ }

    // ExternalUserId (si présent)
    const ext = (kyc && kyc.externalUserId) ? kyc.externalUserId : '—';
    document.getElementById('idExternal').textContent = 'User: ' + ext;

    // KYC
    const badge = document.getElementById('kycBadge');
    const detail = document.getElementById('kycDetail');
    const reviewed = document.getElementById('kycReviewed');
    const labelsUl = document.getElementById('kycLabels');

    if (kyc && kyc.ok && kyc.kyc) {
      const status = kyc.kyc.reviewStatus || '—';
      const answer = kyc.kyc.reviewAnswer || '—';
      const reviewedAt = kyc.kyc.reviewedAt || kyc.kyc.completedAt || kyc.kyc.updatedAt || '—';


      // Badge (GREEN/RED/...)
      badge.textContent = String(answer).toUpperCase();
      detail.textContent = 'Status: ' + status;
      reviewed.textContent = 'Verified / Reviewed: ' + reviewedAt;

      // Couleurs
      badge.className = 'inline-flex items-center px-2 py-1 rounded-full text-sm ';
      if (status === 'completed' && String(answer).toUpperCase() === 'GREEN') {
        badge.className += 'bg-green-100 text-green-700';
      } else if (status === 'completed' && String(answer).toUpperCase() === 'RED') {
        badge.className += 'bg-red-100 text-red-700';
      } else {
        badge.className += 'bg-amber-100 text-amber-700';
      }

      // Labels
      const labels = Array.isArray(kyc.kyc.labels) ? kyc.kyc.labels : [];
      labelsUl.innerHTML = '';
      if (labels.length) {
        labelsUl.classList.remove('hidden');
        labels.forEach(l => {
          const li = document.createElement('li');
          li.textContent = l;
          labelsUl.appendChild(li);
        });
      } else {
        labelsUl.classList.add('hidden');
      }
    } else {
      // Debug visible
      console.warn('KYC endpoint response:', kyc);
    }

    // AML (optionnel) -> adapte selon TON format JSON si besoin
    // Si tu n'as pas aml_status.php, tu peux supprimer ce bloc.
    if (aml && aml.ok !== false) {
      // TODO: mapping selon ton aml_status.php
      // console.log('AML:', aml);
    }

  } catch (e) {
    console.error('Widget error:', e);
  }
})();
</script>

    <?php endif; ?>
  </div>
</body>
</html>
