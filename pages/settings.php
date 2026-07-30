<?php
// settings.php - Tookle Layout Aligned (Centered Header, Founder Navigation Gradient for Save, Clean Neutral Top Buttons)

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

<style>
    .settings-wrapper {
        font-family: 'Montserrat', sans-serif !important;
        display: flex;
        flex-direction: column;
        align-items: flex-start; /* Moved to left to be closer to navigation */
        padding: 2rem 1.5rem;
        width: 100%;
        box-sizing: border-box;
        background-color: #f9fafb;
    }
    
    .settings-container {
        width: 100%;
        max-width: 860px;
        background-color: #ffffff;
        padding: 2.5rem;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.07);
        box-sizing: border-box;
        margin-bottom: 2rem;
    }

    /* LEFT-ALIGNED HEADER & TAGLINE */
    .settings-container h1 {
        text-align: left !important;
        font-size: 1.65rem;
        font-weight: 700;
        color: #1f2937;
        margin-top: 0;
        margin-bottom: 0.35rem;
        font-family: 'Montserrat', sans-serif !important;
    }

    .settings-container .tagline {
        text-align: left !important;
        font-size: 0.9rem;
        color: #6b7280;
        margin-top: 0;
        margin-bottom: 1.75rem;
        font-weight: 400;
        font-family: 'Montserrat', sans-serif !important;
    }

    .settings-container h2 {
        font-size: 1rem;
        font-weight: 600;
        margin-top: 0;
        margin-bottom: 1.25rem;
        color: #1f2937;
        display: flex;
        align-items: center;
        font-family: 'Montserrat', sans-serif !important;
    }

    .section-divider {
        margin-top: 2rem;
        border-top: 1px solid #e5e7eb;
        padding-top: 1.75rem;
    }
    
    /* TOP BUTTONS: CLEAN NEUTRAL / OUTLINE (NO GRADIENTS) */
    .btn-top-action {
        background-color: #ffffff;
        color: #4b5563 !important;
        border: 1px solid #d1d5db;
        padding: 0.625rem 1.25rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: 'Montserrat', sans-serif !important;
    }
    .btn-top-action:hover {
        background-color: #f9fafb;
        color: #111827 !important;
        border-color: #9ca3af;
        transform: translateY(-1px);
    }

    /* SAVE BUTTON: EXACT FOUNDER NAVIGATION GRADIENT FROM LAYOUT.PHP */
    .btn-save-gradient {
        background-image: linear-gradient(to right, #6D28D9, #06b6d4, #6D28D9) !important;
        background-size: 200% auto !important;
        color: #ffffff !important;
        padding: 0.75rem 1.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.4s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        font-family: 'Montserrat', sans-serif !important;
    }
    .btn-save-gradient:hover {
        background-position: right center !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 12px -2px rgba(109, 40, 217, 0.25);
    }

    .action-buttons-container {
        display: flex;
        justify-content: flex-start !important;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    /* Standard Clean Dashboard Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 0.875rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-family: 'Montserrat', sans-serif !important;
    }
    .badge-success { background-color: #d1fae5; color: #065f46; }
    .badge-warning { background-color: #fef3c7; color: #92400e; }
    .badge-danger  { background-color: #fee2e2; color: #991b1b; }
    .badge-secondary { background-color: #f3f4f6; color: #4b5563; }

    /* Clean Form Elements */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    .form-group { display: flex; flex-direction: column; margin-bottom: 1.25rem; }
    .form-group label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #4b5563;
        margin-bottom: 0.5rem;
        font-family: 'Montserrat', sans-serif !important;
    }
    .form-group input, .form-group select, .form-group textarea {
        padding: 0.625rem 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-family: 'Montserrat', sans-serif !important;
        width: 100%;
        box-sizing: border-box;
        background-color: #ffffff;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        color: #111827;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: #6D28D9;
        box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.15);
    }
    .form-group input:read-only { background-color: #f9fafb; cursor: not-allowed; color: #6b7280; }
    .form-group select {
        appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236B7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
        background-repeat: no-repeat;
        background-position: right 0.875rem top 50%;
        background-size: .65em auto;
        padding-right: 2.5em;
    }
    .save-button-container { margin-top: 2rem; display: flex; justify-content: flex-end; }
    
    /* Modal & Overlay */
    .settings-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(17, 24, 39, 0.6); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center; z-index: 1000;
        opacity: 0; visibility: hidden; transition: all 0.2s ease;
    }
    .settings-modal-overlay.visible { opacity: 1; visibility: visible; }
    .settings-modal-content {
        background-color: white; padding: 2rem; border-radius: 0.75rem;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center;
        max-width: 380px; transform: scale(0.95); transition: transform 0.2s ease;
        font-family: 'Montserrat', sans-serif !important;
    }
    .settings-modal-overlay.visible .settings-modal-content { transform: scale(1); }
    .settings-modal-message { font-size: 1rem; margin-bottom: 1.5rem; color: #111827; font-weight: 500; }

    /* KYC IFRAME MODAL */
    .kyc-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(17, 24, 39, 0.75); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
    .kyc-window { width: 100%; max-width: 600px; height: 85vh; background: #ffffff; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); position: relative; overflow: hidden; }
    .kyc-window iframe { width: 100%; height: 100%; border: none; }
    .kyc-close-btn { position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.08); color: #374151; width: 32px; height: 32px; border-radius: 50%; border: none; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .kyc-close-btn:hover { background: rgba(0,0,0,0.15); }
    
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .action-buttons-container { gap: 0.75rem; }
        .settings-container { padding: 1.75rem 1.25rem; }
    }
</style>

<div class="settings-wrapper w-full h-full overflow-y-auto">
    <main class="settings-container">
        <h1>Manage Account</h1>
        <p class="tagline">Keep your personal and profile information up to date.</p>
        
        <div class="action-buttons-container">
            <a href="#" id="start-fundraising-btn" class="btn-top-action">Start Fundraising</a>
            <a href="#" id="invest-in-projects-btn" class="btn-top-action">Discover Projects</a>

            <?php if (empty($kycApplicantId)): ?>
                <a href="#" onclick="openKycModal(event)" id="check-kyc-btn" class="btn-top-action">Verify my Identity</a>
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
                <div class="form-group" style="max-width: 320px;">
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
                <button type="submit" class="btn-save-gradient">Save Changes</button>
            </div>
        </form>
    </main>

    <!-- Custom Alert Modal -->
    <div id="custom-modal" class="settings-modal-overlay">
        <div class="settings-modal-content">
            <p id="modal-message" class="settings-modal-message"></p>
            <button id="modal-close-button" class="btn-save-gradient" style="min-width: 100px;">OK</button>
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
            if (currentUserRole === 'investor') window.location.href = '<?= get_url('projects') ?>';
            else switchRoleAndRedirect('investor', '/projects');
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