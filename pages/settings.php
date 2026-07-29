<?php
// settings.php - MERGED VERSION (Secure + KYC) - CORRIGÉ

// 1. SECURE SESSION START
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 7; // 1 week
    session_set_cookie_params($lifetime, '/', '', isset($_SERVER['HTTPS']), true);
    session_start();
}

// 2. CSRF TOKEN GENERATION
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('bin2hex')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// 3. AUTHENTICATION CHECK
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
$user_role = $_SESSION['user_role'] ?? 'investor';

// ======================================================================
//  4. BLOCS KYC / SUMSUB - AFFICHAGE INITIAL (Lecture Seule)
// ======================================================================

$userId         = $_SESSION['user_id'];
$userEmail      = null;
$kycApplicantId = null;
$externalUserId = 'sess_' . $userId;

// Résumé par défaut
$kycSummary = [
    'label' => 'KYC: not started',
    'class' => 'badge badge-warning',
];

$kycRawStatus = null;
$dbCfgPath = __DIR__ . '/../sumsub/config/db.php';

try {
    if (is_file($dbCfgPath)) {
        $dbCfg = require $dbCfgPath; 
        if (is_array($dbCfg) && !empty($dbCfg['dsn'])) {
            $pdoKyc = new PDO($dbCfg['dsn'], $dbCfg['user'], $dbCfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            if (!empty($_SESSION['user_info']['email'])) {
                 $userEmail = $_SESSION['user_info']['email'];
            }

            // Récupération de l'état actuel en base LOCALE (sans appel API bloquant)
            $st2 = $pdoKyc->prepare("
                SELECT applicant_id, review_status, review_answer
                  FROM kyc_applicants
                 WHERE external_user_id = :ext
                    OR external_user_id LIKE :extLike
                 ORDER BY updated_at DESC, created_at DESC
                 LIMIT 1
            ");
            $st2->execute([
                ':ext' => $externalUserId,
                ':extLike' => $externalUserId . '%'
            ]);
            
            if ($rowKyc = $st2->fetch()) {
                $kycApplicantId = $rowKyc['applicant_id'];
                $status = $rowKyc['review_status'] ?? 'init';
                $answer = $rowKyc['review_answer'] ?? null;
                
                $kycRawStatus = $status;

                // Logique des Labels
                if ($status === 'completed' && $answer === 'GREEN') {
                    $kycSummary = [
                        'label' => 'KYC: Approved',
                        'class' => 'badge badge-success',
                    ];
                } elseif ($status === 'completed' && $answer === 'RED') {
                    $kycSummary = [
                        'label' => 'KYC: Rejected',
                        'class' => 'badge badge-danger',
                    ];
                } elseif (in_array($status, ['init', 'pending', 'queued', 'prechecked'], true)) {
                    $kycSummary = [
                        'label' => 'KYC: Under review',
                        'class' => 'badge badge-warning',
                    ];
                } else {
                    $kycSummary = [
                        'label' => 'KYC: ' . ucfirst($status),
                        'class' => 'badge badge-secondary',
                    ];
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log("Erreur KYC settings.php : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage my Account - TOOKLE</title>
    
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="/config_logo.js" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-purple: #8e52ff; --primary-purple-light: #f0e6ff; --primary-purple-dark: #7038cc;
            --secondary-color: #FFFFFF; --border-color: #D1D5DB; --text-color: #111827;
            --label-color: #4B5563; --tagline-color: #6B7280;
            --font-family: 'Montserrat', sans-serif;
            --container-max-width: 900px;
            --border-radius: 6px;

            /* Dynamic Roles Colors */
            <?php if ($user_role === 'founder'): ?>
            --gradient-start: #6D28D9;
            --gradient-mid: #06b6d4;
            --gradient-end: #6D28D9;
            <?php else: ?>
            --gradient-start: #34D399;
            --gradient-mid: #8B5CF6;
            --gradient-end: #34D399;
            <?php endif; ?>
        }
        body { font-family: var(--font-family); color: var(--text-color); background-color: #F9FAFB; margin: 0; padding: 2rem; display: flex; flex-direction: column; align-items: center; min-height: 100vh; box-sizing: border-box; }
        .app-header { width: 100%; max-width: var(--container-max-width); margin-bottom: 2rem; }
        .logo { height: 120px; display: block; }
        .container { width: 100%; max-width: var(--container-max-width); background-color: var(--secondary-color); padding: 2rem 3rem; border-radius: var(--border-radius); box-shadow: 0 1px 3px rgba(0,0,0,0.05); box-sizing: border-box; margin-bottom: 2rem; }
        h1 { text-align: center; font-size: 1.75rem; font-weight: 600; margin-top: 0; margin-bottom: 0.5rem; letter-spacing: 0.5px; }
        h2 { font-size: 1.1rem; font-weight: 600; margin-top: 0; margin-bottom: 1.5rem; color: var(--text-color); display: flex; align-items: center;}
        .tagline { text-align: center; font-size: 1rem; color: var(--tagline-color); margin-top: 0; margin-bottom: 2rem; font-weight: 400; }
        .section-divider { margin-top: 2.5rem; border-top: 1px solid var(--border-color); padding-top: 2rem; }
        
        .btn { padding: 0.75rem 1.5rem; border: 1px solid transparent; border-radius: var(--border-radius); font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; background-size: 200% auto; color: white;}
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); background-position: right center; }
        .btn-primary { background-image: linear-gradient(to right, var(--gradient-start), var(--gradient-mid), var(--gradient-end)); border: none; }
        .btn-founder-gradient { background-image: linear-gradient(to right, #6D28D9, #06b6d4, #6D28D9); }
        .btn-investor-gradient { background-image: linear-gradient(to right, #34D399, #8B5CF6, #34D399); }
        
        .action-buttons-container { display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 2.5rem; flex-wrap: wrap; }

        /* Badges KYC Styles */
        .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; border: 1px solid transparent; }
        .badge-success { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .badge-danger { background-color: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        .badge-warning { background-color: #fef3c7; color: #92400e; border-color: #fde68a; }
        .badge-secondary { background-color: #e5e7eb; color: #374151; border-color: #d1d5db; }

        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.5rem; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 1.5rem; }
        label { font-size: 0.875rem; font-weight: 500; color: var(--label-color); margin-bottom: 0.5rem; }
        input, select, textarea { padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.9rem; font-family: inherit; width: 100%; box-sizing: border-box; }
        input:read-only { background-color: #f3f4f6; cursor: default; }
        select { appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236B7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right .7em top 50%; background-size: .65em auto; padding-right: 2.5em; }
        .save-button-container { margin-top: 2rem; display: flex; justify-content: flex-end; }
        
        /* Modal & Overlay */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .modal-overlay.visible { opacity: 1; visibility: visible; }
        .modal-content { background-color: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; max-width: 400px; transform: scale(0.9); transition: transform 0.3s ease; }
        .modal-overlay.visible .modal-content { transform: scale(1); }
        .modal-message { font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--text-color); }

        /* KYC IFRAME MODAL */
        .kyc-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center; }
        .kyc-window { width: 100%; max-width: 600px; height: 85vh; background: #ffffff; border-radius: 16px; border: 1px solid #1e293b; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); position: relative; overflow: hidden; animation: popInKYC 0.3s ease-out; }
        .kyc-window iframe { width: 100%; height: 100%; border: none; }
        .kyc-close-btn { position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.1); color: #333; width: 36px; height: 36px; border-radius: 50%; border: none; cursor: pointer; font-size: 20px; line-height: 1; display: flex; align-items: center; justify-content: center; z-index: 1000; transition: background 0.2s; }
        .kyc-close-btn:hover { background: rgba(0,0,0,0.2); }
        @keyframes popInKYC { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .action-buttons-container { gap: 1rem; } .kyc-window { width: 95%; height: 90vh; } }
    </style>
</head>
<body>
    <header class="app-header">
         <a href="/">
            <img id="tookle-logo" src="" alt="TOOKLE Logo" class="logo">
        </a>
    </header>

    <main class="container">
        <h1>MANAGE MY ACCOUNT</h1>
        <p class="tagline">Keep your personal and profile information up to date.</p>
        
        <div class="action-buttons-container">
            <a href="#" id="start-fundraising-btn" class="btn btn-founder-gradient">Start Fundraising</a>
            <a href="#" id="invest-in-projects-btn" class="btn btn-investor-gradient">Discover Projects</a>

            <?php if (empty($kycApplicantId)): ?>
                <a href="#" onclick="openKycModal(event)" id="check-kyc-btn" class="btn btn-investor-gradient">CHECK KYC</a>
            <?php else: ?>
                <span id="kyc-badge-span" class="<?= htmlspecialchars($kycSummary['class']) ?>">
                    <?= htmlspecialchars($kycSummary['label']) ?>
                </span>
            <?php endif; ?>
        </div>

        <form id="account-form">
            <section class="basic-info-section section-divider">
                <h2>Basic Information</h2>
                <div class="form-grid">
                    <div class="form-group"><label for="first-name">First Name</label><input type="text" id="first-name" name="firstName"></div>
                    <div class="form-group"><label for="last-name">Last Name</label><input type="text" id="last-name" name="lastName"></div>
                    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" readonly></div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <select id="country" name="country">
                            <option value="">Select Country</option>
                            <option value="Afghanistan">Afghanistan</option>
                            <option value="France">France</option>
                            <option value="United States">United States</option>
                            </select>
                    </div>
                </div>
            </section>
            
            <section class="profile-links-section section-divider">
                <h2>Profile Details</h2>
                <div class="form-group"><label for="profile-description">Profile Description</label><textarea id="profile-description" name="profileDescription" rows="4"></textarea></div>
                <div class="form-group" style="max-width: 300px;">
                    <label for="language">Preferred Language</label>
                    <select id="language" name="language">
                        <option value="">Select Language</option><option value="en">English</option><option value="fr">French</option><option value="de">German</option><option value="zh">Chinese (Mandarin)</option><option value="es">Spanish</option><option value="hi">Hindi</option>
                    </select>
                </div>
            </section>

             <div class="save-button-container">
                    <button type="submit" class="btn btn-founder-gradient">Save Changes</button>
             </div>
        </form>
    </main>

    <div id="custom-modal" class="modal-overlay">
        <div class="modal-content">
            <p id="modal-message" class="modal-message"></p>
            <button id="modal-close-button" class="btn btn-primary">OK</button>
        </div>
    </div>

    <div id="kycModal" class="kyc-overlay">
        <div class="kyc-window">
            <button class="kyc-close-btn" onclick="closeKycModal()">&times;</button>
            <iframe id="kycIframe" src="" allow="accelerometer *; camera *; encrypted-media *; gyroscope *; microphone *; payment *; autoplay *" allowfullscreen title="KYC Verification"></iframe>
        </div>
    </div>

    <script>
    // --- KYC MODAL LOGIC ---
    function openKycModal(e) {
        if(e) e.preventDefault();
        const iframe = document.getElementById('kycIframe');
        if(iframe.src === "" || iframe.src === window.location.href) {
            iframe.src = "/sumsub/public/kyc_portal.php";
        }
        document.getElementById('kycModal').style.display = 'flex';
    }
    function closeKycModal() {
        document.getElementById('kycModal').style.display = 'none';
        // Reload to check status immediately after closing
        window.location.reload();
    }

    // --- MAIN LOGIC ---
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof TOOKLE_LOGO_BASE64 !== 'undefined') {
            document.getElementById('tookle-logo').src = TOOKLE_LOGO_BASE64;
        }

        const accountForm = document.getElementById('account-form');
        const customModal = document.getElementById('custom-modal');
        const modalMessage = document.getElementById('modal-message');
        const modalCloseButton = document.getElementById('modal-close-button');
        
        function showModal(message) {
            modalMessage.textContent = message;
            customModal.classList.add('visible');
        }
        function hideModal() {
            customModal.classList.remove('visible');
        }
        modalCloseButton.addEventListener('click', hideModal);
        customModal.addEventListener('click', (e) => { if(e.target === customModal) hideModal(); });

        // === KYC SYNC VIA FETCH (Non-Blocking & Robust) ===
        // On récupère l'ID s'il existe, sinon on utilise l'ID externe (sess_XX)
        const applicantId = "<?php echo $kycApplicantId ?? ''; ?>";
        const externalUserId = "<?php echo $externalUserId; ?>";

        console.log("Starting KYC Sync check...");
        
        // Construction dynamique de l'URL
        let urlParams = '&force=1';
        if (applicantId) {
            urlParams += '&applicantId=' + applicantId;
        } else {
            urlParams += '&externalUserId=' + externalUserId;
        }

        fetch('/sumsub/public/kyc_status.php?' + urlParams)
            .then(response => response.json())
            .then(data => {
                console.log("KYC Sync Result:", data); // Debug

                if (data.ok && data.kyc) {
                    const status = data.kyc.reviewStatus;
                    const answer = data.kyc.reviewAnswer;
                    const badge = document.getElementById('kyc-badge-span');
                    
                    // Si on a trouvé un ID Sumsub alors qu'on n'en avait pas en local -> RECHARGE pour mettre à jour la vue
                    if (!applicantId && data.kyc.applicantId) {
                         console.log("New Applicant ID found, reloading...");
                         window.location.reload();
                         return;
                    }

                    if(badge) {
                        if (status === 'completed' && answer === 'GREEN') {
                            badge.className = 'badge badge-success';
                            badge.innerText = 'KYC: Approved';
                            // Vérification Session vs Réalité
                            if("<?php echo $_SESSION['user_info']['kyc_status'] ?? ''; ?>" !== "COMPLETED") {
                                window.location.reload();
                            }
                        } else if (status === 'completed' && answer === 'RED') {
                            badge.className = 'badge badge-danger';
                            badge.innerText = 'KYC: Rejected';
                        } else {
                            badge.className = 'badge badge-warning';
                            badge.innerText = 'KYC: ' + status.charAt(0).toUpperCase() + status.slice(1);
                        }
                    }
                }
            })
            .catch(err => console.error("KYC Sync Error:", err));

        // === ROLE SWITCHING ===
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        const currentUserRole = '<?php echo $user_role; ?>';

        function switchRoleAndRedirect(targetRole, redirectUrl) {
            fetch('/backend/role_switcher.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role: targetRole, csrf_token: csrfToken })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) window.location.href = redirectUrl;
                else if (data.error === 'membership_required') window.location.href = data.redirect;
                else showModal('Error: ' + (data.error || 'Unknown error'));
            });
        }

        document.getElementById('start-fundraising-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentUserRole === 'founder') window.location.href = '/dashboard';
            else switchRoleAndRedirect('founder', '/dashboard');
        });
        
        document.getElementById('invest-in-projects-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentUserRole === 'investor') window.location.href = '/portfolio';
            else switchRoleAndRedirect('investor', '/portfolio');
        });

        // === LOAD & SAVE FORM ===
        async function fetchAccountData() {
            try {
                const response = await fetch('/backend/settings_backend.php');
                if (!response.ok) { if (response.status === 401) window.location.href = '/login'; throw new Error(response.status); }
                const data = await response.json();
                if (data.basic_info) {
                    document.getElementById('first-name').value = data.basic_info.first_name || '';
                    document.getElementById('last-name').value = data.basic_info.last_name || '';
                    document.getElementById('email').value = data.basic_info.email || '';
                    document.getElementById('country').value = data.basic_info.country || '';
                    document.getElementById('profile-description').value = data.basic_info.profile_description || '';
                    document.getElementById('language').value = data.basic_info.language || '';
                }
            } catch (e) { console.error(e); }
        }
        fetchAccountData();

        accountForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const saveButton = event.target.querySelector('button[type="submit"]');
            saveButton.textContent = 'Saving...';
            saveButton.disabled = true;
            try {
                const formData = new FormData(accountForm);
                const response = await fetch('/backend/settings_backend.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) showModal('Changes saved successfully!');
                else throw new Error(result.error);
            } catch (error) { showModal(error.message); } 
            finally { saveButton.textContent = 'Save Changes'; saveButton.disabled = false; }
        });
    });
    </script>
</body>
</html>