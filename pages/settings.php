<?php
// settings.php - Tookle Brand Redesign (Montserrat & Modern Web3 Aesthetic)

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

// 4. KYC / SUMSUB SETUP
$userId         = $_SESSION['user_id'];
$userEmail      = null;
$kycApplicantId = null;
$externalUserId = 'sess_' . $userId;

$kycSummary = [
    'label' => 'KYC: not started',
    'class' => 'badge badge-warning',
];
$kycRawStatus = null;
?>

<!-- Google Montserrat Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-purple: #8e52ff;
        --primary-purple-dark: #7433e6;
        --primary-gradient: linear-gradient(135deg, #8e52ff 0%, #6366f1 100%);
        --secondary-color: #ffffff;
        --border-color: #E5E7EB;
        --text-color: #111827;
        --label-color: #374151;
        --tagline-color: #6B7280;
        --font-family: 'Montserrat', sans-serif;
        --container-max-width: 880px;
        --border-radius: 0.85rem;
    }
    
    .settings-wrapper {
        font-family: var(--font-family) !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 2.5rem 1.5rem;
        width: 100%;
        box-sizing: border-box;
    }
    
    .settings-container {
        width: 100%;
        max-width: var(--container-max-width);
        background-color: var(--secondary-color);
        padding: 2.5rem 3rem;
        border-radius: var(--border-radius);
        border: 1px solid rgba(229, 231, 235, 0.8);
        box-shadow: 0 20px 25px -5px rgba(142, 82, 255, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
        box-sizing: border-box;
        margin-bottom: 2rem;
    }
    
    .settings-title-gradient {
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 800;
        letter-spacing: -0.025em;
    }

    .settings-container h1 {
        text-align: center;
        font-size: 2rem;
        margin-top: 0;
        margin-bottom: 0.4rem;
        font-family: var(--font-family) !important;
    }

    .settings-container h2 {
        font-size: 1.15rem;
        font-weight: 700;
        margin-top: 0;
        margin-bottom: 1.25rem;
        color: var(--text-color);
        display: flex;
        align-items: center;
        font-family: var(--font-family) !important;
    }
    
    .settings-container .tagline {
        text-align: center;
        font-size: 0.95rem;
        color: var(--tagline-color);
        margin-top: 0;
        margin-bottom: 2.25rem;
        font-weight: 500;
        font-family: var(--font-family) !important;
    }

    .section-divider {
        margin-top: 2.25rem;
        border-top: 1px solid var(--border-color);
        padding-top: 2rem;
    }
    
    .settings-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 0.6rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: white !important;
        font-family: var(--font-family) !important;
        border: none;
    }
    .settings-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(142, 82, 255, 0.25);
    }
    .btn-founder-gradient {
        background: var(--primary-gradient);
    }
    .btn-investor-gradient {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    }
    
    .action-buttons-container {
        display: flex;
        justify-content: center;
        gap: 1.25rem;
        margin-bottom: 2.5rem;
        flex-wrap: wrap;
    }

    /* Badges KYC Styles */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.6rem 1.25rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-family: var(--font-family) !important;
    }
    .badge-success { background-color: #DEF7EC; color: #03543F; }
    .badge-warning { background-color: #FEF3C7; color: #92400E; }
    .badge-danger  { background-color: #FDE8E8; color: #9B1C1C; }
    .badge-secondary { background-color: #F3F4F6; color: #4B5563; }

    /* Form Styles */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .form-group { display: flex; flex-direction: column; margin-bottom: 1.25rem; }
    .form-group label {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--label-color);
        margin-bottom: 0.5rem;
        font-family: var(--font-family) !important;
    }
    .form-group input, .form-group select, .form-group textarea {
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 0.6rem;
        font-size: 0.925rem;
        font-family: var(--font-family) !important;
        width: 100%;
        box-sizing: border-box;
        background-color: #F9FAFB;
        transition: all 0.2s ease;
        color: #111827;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-purple);
        background-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(142, 82, 255, 0.15);
    }
    .form-group input:read-only { background-color: #F3F4F6; cursor: not-allowed; opacity: 0.8; }
    .form-group select {
        appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236B7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
        background-repeat: no-repeat;
        background-position: right 1em top 50%;
        background-size: .65em auto;
        padding-right: 2.5em;
    }
    .save-button-container { margin-top: 2rem; display: flex; justify-content: flex-end; }
    
    /* Modal & Overlay */
    .settings-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center; z-index: 1000;
        opacity: 0; visibility: hidden; transition: all 0.25s ease;
    }
    .settings-modal-overlay.visible { opacity: 1; visibility: visible; }
    .settings-modal-content {
        background-color: white; padding: 2.25rem; border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); text-align: center;
        max-width: 400px; transform: scale(0.95); transition: transform 0.25s ease;
        font-family: var(--font-family) !important;
    }
    .settings-modal-overlay.visible .settings-modal-content { transform: scale(1); }
    .settings-modal-message { font-size: 1.05rem; margin-bottom: 1.5rem; color: var(--text-color); font-weight: 500; }

    /* KYC IFRAME MODAL */
    .kyc-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(6px); z-index: 9999; align-items: center; justify-content: center; }
    .kyc-window { width: 100%; max-width: 620px; height: 85vh; background: #ffffff; border-radius: 1.25rem; border: 1px solid #E5E7EB; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); position: relative; overflow: hidden; animation: popInKYC 0.3s ease-out; }
    .kyc-window iframe { width: 100%; height: 100%; border: none; }
    .kyc-close-btn { position: absolute; top: 15px; right: 15px; background: rgba(0,0,0,0.1); color: #333; width: 36px; height: 36px; border-radius: 50%; border: none; cursor: pointer; font-size: 20px; line-height: 1; display: flex; align-items: center; justify-content: center; z-index: 1000; transition: background 0.2s; }
    .kyc-close-btn:hover { background: rgba(0,0,0,0.2); }
    @keyframes popInKYC { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .action-buttons-container { gap: 1rem; }
        .kyc-window { width: 95%; height: 90vh; }
        .settings-container { padding: 1.75rem 1.25rem; }
    }
</style>

<div class="settings-wrapper w-full h-full overflow-y-auto">
    <main class="settings-container">
        <h1><span class="settings-title-gradient">Manage My Account</span></h1>
        <p class="tagline">Keep your personal and profile information up to date.</p>
        
        <div class="action-buttons-container">
            <a href="#" id="start-fundraising-btn" class="settings-btn btn-founder-gradient">Start Fundraising</a>
            <a href="#" id="invest-in-projects-btn" class="settings-btn btn-investor-gradient">Discover Projects</a>

            <?php if (empty($kycApplicantId)): ?>
                <a href="#" onclick="openKycModal(event)" id="check-kyc-btn" class="settings-btn btn-founder-gradient">CHECK KYC</a>
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
                    <div class="form-group">
                        <label for="first-name">First Name</label>
                        <input type="text" id="first-name" name="firstName" placeholder="First Name">
                    </div>
                    <div class="form-group">
                        <label for="last-name">Last Name</label>
                        <input type="text" id="last-name" name="lastName" placeholder="Last Name">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" readonly placeholder="name@example.com">
                    </div>
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
                <div class="form-group">
                    <label for="profile-description">Profile Description</label>
                    <textarea id="profile-description" name="profileDescription" rows="4" placeholder="Brief description about yourself or your organization..."></textarea>
                </div>
                <div class="form-group" style="max-width: 340px;">
                    <label for="language">Preferred Language</label>
                    <select id="language" name="language">
                        <option value="">Select Language</option>
                        <option value="en">English</option>
                        <option value="fr">French</option>
                        <option value="de">German</option>
                        <option value="zh">Chinese (Mandarin)</option>
                        <option value="es">Spanish</option>
                        <option value="hi">Hindi</option>
                    </select>
                </div>
            </section>

            <div class="save-button-container">
                <button type="submit" class="settings-btn btn-founder-gradient">Save Changes</button>
            </div>
        </form>
    </main>

    <!-- Custom Alert Modal -->
    <div id="custom-modal" class="settings-modal-overlay">
        <div class="settings-modal-content">
            <p id="modal-message" class="settings-modal-message"></p>
            <button id="modal-close-button" class="settings-btn btn-founder-gradient" style="min-width: 100px;">OK</button>
        </div>
    </div>

    <!-- KYC SumSub Verification Modal -->
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
        window.location.reload();
    }

    // --- MAIN LOGIC ---
    document.addEventListener('DOMContentLoaded', function() {
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

        // === KYC SYNC VIA FETCH ===
        const applicantId = "<?php echo $kycApplicantId ?? ''; ?>";
        const externalUserId = "<?php echo $externalUserId; ?>";

        let urlParams = '&force=1';
        if (applicantId) {
            urlParams += '&applicantId=' + applicantId;
        } else {
            urlParams += '&externalUserId=' + externalUserId;
        }

        fetch('/sumsub/public/kyc_status.php?' + urlParams)
            .then(response => response.json())
            .then(data => {
                if (data.ok && data.kyc) {
                    const status = data.kyc.reviewStatus;
                    const answer = data.kyc.reviewAnswer;
                    const badge = document.getElementById('kyc-badge-span');
                    
                    if (!applicantId && data.kyc.applicantId) {
                         window.location.reload();
                         return;
                    }

                    if(badge) {
                        if (status === 'completed' && answer === 'GREEN') {
                            badge.className = 'badge badge-success';
                            badge.innerText = 'KYC: Approved';
                        } else if (status === 'completed' && answer === 'RED') {
                            badge.className = 'badge badge-danger';
                            badge.innerText = 'KYC: Rejected';
                        } else if (['init', 'pending', 'queued', 'prechecked'].includes(status)) {
                            badge.className = 'badge badge-warning';
                            badge.innerText = 'KYC: Under review';
                        } else {
                            badge.className = 'badge badge-secondary';
                            badge.innerText = 'KYC: ' + status.charAt(0).toUpperCase() + status.slice(1);
                        }
                    }
                } else {
                    const badge = document.getElementById('kyc-badge-span');
                    if (badge) {
                        badge.className = 'badge badge-secondary';
                        badge.innerText = 'KYC data not fetched';
                    }
                }
            })
            .catch(err => {
                const badge = document.getElementById('kyc-badge-span');
                if (badge) {
                    badge.className = 'badge badge-secondary';
                    badge.innerText = 'KYC data not fetched';
                }
            });

        // === ROLE SWITCHING ===
        const csrfToken = '<?php echo htmlspecialchars($csrf_token ?? "", ENT_QUOTES); ?>';
        const currentUserRole = '<?php echo $user_role; ?>';

        function switchRoleAndRedirect(targetRole, redirectUrl) {
            fetch('/backend/role_switcher.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role: targetRole, csrf_token: csrfToken })
            })
            .then(r => r.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) window.location.href = redirectUrl;
                    else if (data.error === 'membership_required') window.location.href = data.redirect;
                    else showModal('Error: ' + (data.error || 'Unknown error'));
                } catch (e) {
                    showModal('Unexpected server response.');
                }
            })
            .catch(err => {
                showModal('Network error: ' + err.message);
            });
        }

        document.getElementById('start-fundraising-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentUserRole === 'founder') window.location.href = '<?= get_url('dashboard') ?>';
            else switchRoleAndRedirect('founder', '/dashboard');
        });
        
        document.getElementById('invest-in-projects-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentUserRole === 'investor') window.location.href = '<?= get_url('portfolio') ?>';
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
</div>