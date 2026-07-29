<?php
/**
 * Page: Promotion
 * Filepath: /pages/promotion.php
 *
 * This page allows founders to manage their invite and earn program.
 */

// Explicitly load the backend script to ensure data is always available on page load.
// This guarantees that variables like $kpis, $all_campaigns, etc., are set before the HTML is rendered.
require_once __DIR__ . '/../backend/promotion_backend.php';

// The backend script above has already populated the necessary variables.
// We just need to check for a project_id for context.
$project_id = $_SESSION['active_project_id'] ?? null;
if (empty($project_id) && empty($page_error)) {
    // This should ideally be handled by the router, but as a safeguard.
    $page_error = "No active project. Please select a project from your dashboard.";
}
?>

<main class="flex-1 p-8 md:p-12 main-content-area">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Promotion</h1>
        <p class="mt-2 text-base text-gray-500">Activate and manage your project's invite and earn program. Reward your community for spreading the word and attracting new investors.</p>
    </header>
    
    <?php if (isset($page_error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($page_error); ?></span>
        </div>
    <?php else: ?>
        <div class="mb-8 p-4 bg-gray-50 border border-gray-200 rounded-lg">
            <h2 class="text-md font-semibold text-gray-800 flex items-center gap-2"><i data-lucide="info" class="w-5 h-5"></i>How Referrals Work</h2>
            <p class="text-sm text-gray-700 mt-2">
                Activate a campaign to start rewarding your investors for spreading the word. When an existing investor (Inviter) refers a new investor (Invitee) who successfully invests, both can earn a commission based on your campaign settings.
            </p>
            <p class="text-sm text-gray-700 mt-1 font-semibold">
                Please note: Only one campaign can be active at a time. Creating a new one will deactivate the current active campaign.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500"><span>Referred Investments</span><div class="tooltip"><i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i><span class="tooltip-text">Total investment amount from all referred investors with a confirmed payment.</span></div></div>
                    <p id="kpi-total-investments" class="text-3xl font-bold text-gray-900 mt-1">$0</p>
                </div>
            </div>
             <div class="card p-6">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500"><span>Pending</span><div class="tooltip"><i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i><span class="tooltip-text">Total commissions calculated and awaiting admin approval.</span></div></div>
                    <p id="kpi-pending-commissions" class="text-3xl font-bold text-gray-900 mt-1">$0</p>
                </div>
            </div>
             <div class="card p-6">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500"><span>Due for Payout</span><div class="tooltip"><i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i><span class="tooltip-text">Total commissions approved and ready for payout.</span></div></div>
                    <p id="kpi-due-commissions" class="text-3xl font-bold text-gray-900 mt-1">$0</p>
                </div>
            </div>
             <div class="card p-6">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500"><span>Total Paid</span><div class="tooltip"><i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i><span class="tooltip-text">Total commissions that have been successfully paid out.</span></div></div>
                    <p id="kpi-paid-commissions" class="text-3xl font-bold text-gray-900 mt-1">$0</p>
                </div>
            </div>
        </div>

        <div class="card p-6 mb-8">
            <div id="campaign-status-section">
                <div id="inactive-state" class="<?php echo ($is_active_campaign ?? false) ? 'hidden' : ''; ?>">
                    <div class="flex justify-between items-center flex-wrap gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Invite & Earn Program</h2>
                            <p class="text-gray-500 mt-1">The program is currently <span class="font-semibold text-red-600">Inactive</span>. Create a campaign to start.</p>
                        </div>
                        <button id="create-campaign-btn-inactive" class="btn btn-primary">
                            <i data-lucide="plus-circle" class="w-5 h-5 mr-2"></i>
                            <span>Create First Campaign</span>
                        </button>
                    </div>
                </div>

                <div id="active-state" class="<?php echo (!($is_active_campaign ?? false)) ? 'hidden' : ''; ?>">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Invite & Earn Program</h2>
                            <p class="text-gray-500 mt-1">Your program is currently <span class="font-semibold text-green-600">Active</span>.</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button id="deactivate-campaign-btn" title="Deactivate Current Campaign" class="btn btn-subtle">
                                <i data-lucide="power" class="w-4 h-4 mr-2"></i>
                                <span>Deactivate</span>
                            </button>
                            <button id="create-campaign-btn-active" class="btn btn-secondary">
                                <i data-lucide="plus-circle" class="w-4 h-4 mr-2"></i>
                                <span>New Campaign</span>
                            </button>
                        </div>
                    </div>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-4">Active Campaign: <span id="active-campaign-name" class="font-bold text-purple-700"><?php echo htmlspecialchars($active_campaign['campaign_name'] ?? ''); ?></span></h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group mb-0">
                                <label class="form-label text-sm text-gray-600">Inviter Reward (%)</label>
                                <p id="active-inviter-reward" class="text-2xl font-medium text-gray-800"><?php echo htmlspecialchars($active_campaign['inviter_reward_percent'] ?? 0); ?>%</p>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label text-sm text-gray-600">Invitee Bonus (%)</label>
                                <p id="active-invitee-bonus" class="text-2xl font-medium text-gray-800"><?php echo htmlspecialchars($active_campaign['invitee_bonus_percent'] ?? 0); ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
                    <h2 class="text-xl font-semibold text-gray-900">Participants List</h2>
                    <div class="flex items-center gap-4">
                        <button id="export-csv-button" class="btn btn-secondary inline-flex items-center gap-2">
                            <i data-lucide="download-cloud" class="w-4 h-4"></i>
                            Export (CSV)
                        </button>
                        <button id="save-changes-button" class="btn btn-secondary" disabled>
                            <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-container overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr class="border-b border-gray-200">
                            <th class="w-[20%] p-4 text-left text-sm font-semibold text-gray-600">Inviter</th>
                            <th class="w-[20%] p-4 text-left text-sm font-semibold text-gray-600">Invitee</th>
                            <th class="w-[8%] p-4 text-left text-sm font-semibold text-gray-600">Ref</th>
                            <th class="w-[8%] p-4 text-left text-sm font-semibold text-gray-600">Amount</th>
                            <th class="w-[12%] p-4 text-left text-sm font-semibold text-gray-600">Inviter Comm.</th>
                            <th class="w-[12%] p-4 text-left text-sm font-semibold text-gray-600">Invitee Bonus</th>
                            <th class="w-[8%] p-4 text-left text-sm font-semibold text-gray-600">Campaign</th>
                            <th class="w-[8%] p-4 text-left text-sm font-semibold text-gray-600">Commission</th>
                            <th class="w-[4%] p-4 text-left text-sm font-semibold text-gray-600">Date</th>
                        </tr>
                    </thead>
                    <tbody id="participants-tbody">
                        <?php if (empty($participants)): ?>
                            <tr><td colspan="9" class="text-center p-6 text-gray-500">No participants with valid referral codes found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($participants as $p): ?>
                                <tr data-investment-id="<?php echo htmlspecialchars($p['investment_id']); ?>" data-campaign-ref="<?php echo htmlspecialchars($p['campaign_reference'] ?? ''); ?>" class="border-b border-gray-200 last:border-b-0 hover:bg-gray-50 transition-colors">
                                    <td class="p-4 truncate" title="<?php echo htmlspecialchars($p['inviter_email'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($p['inviter_email'] ?? 'N/A'); ?></td>
                                    <td class="p-4 truncate" title="<?php echo htmlspecialchars($p['invitee_email'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($p['invitee_email'] ?? 'N/A'); ?></td>
                                    <td class="p-4 font-mono text-xs text-gray-500 truncate" title="<?php echo htmlspecialchars($p['investment_reference'] ?? 'N/A'); ?>">
                                        <?php 
                                            $ref = $p['investment_reference'] ?? 'N/A';
                                            if (strlen($ref) > 12) {
                                                echo htmlspecialchars(substr($ref, 0, 6) . '...' . substr($ref, -6));
                                            } else {
                                                echo htmlspecialchars($ref);
                                            }
                                        ?>
                                    </td>
                                    <td class="p-4 font-medium text-gray-800"><?php echo htmlspecialchars('$' . number_format($p['investment_amount'] ?? 0, 0)); ?></td>
                                    <td class="p-4 font-semibold text-gray-700"><?php echo htmlspecialchars('$' . number_format($p['inviter_commission_earned'] ?? 0, 0)); ?></td>
                                    <td class="p-4 font-semibold text-gray-700"><?php echo htmlspecialchars('$' . number_format($p['invitee_bonus_earned'] ?? 0, 0)); ?></td>
                                    <td class="p-4">
                                        <?php if (!empty($p['campaign_name']) && empty($all_campaigns)): ?>
                                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-md text-xs font-medium"><?php echo htmlspecialchars($p['campaign_name']); ?></span>
                                        <?php else: ?>
                                            <select class="p-2 border rounded-md bg-white w-full campaign-select text-xs" data-original-value="<?php echo htmlspecialchars($p['campaign_reference'] ?? ''); ?>">
                                                <option value="">None</option>
                                                <?php foreach ($all_campaigns as $c): ?>
                                                    <option value="<?php echo htmlspecialchars($c['id']); ?>" <?php echo ($c['id'] == $p['campaign_reference']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($c['campaign_name']) . ($c['is_active'] ? ' (Active)' : ' (Inactive)'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <?php
                                            $status = $p['status'] ?? 'pending';
                                            $status_classes = [
                                                'pending' => 'status-badge-pending',
                                                'due' => 'status-badge-due',
                                                'paid' => 'status-badge-paid',
                                                'rejected' => 'status-badge-rejected',
                                            ];
                                            $status_class = $status_classes[$status] ?? 'status-badge-pending';
                                        ?>
                                        <div class="relative status-changer" 
                                             data-original-status="<?php echo htmlspecialchars($status); ?>" 
                                             data-current-status="<?php echo htmlspecialchars($status); ?>">
                                            <button class="status-badge <?php echo $status_class; ?>">
                                                <span class="capitalize"><?php echo htmlspecialchars($status); ?></span>
                                                <i data-lucide="chevron-down" class="w-4 h-4 ml-1"></i>
                                            </button>
                                            <div class="status-dropdown hidden">
                                                <a href="#" class="status-dropdown-item" data-status="pending">Pending</a>
                                                <a href="#" class="status-dropdown-item" data-status="due">Due</a>
                                                <a href="#" class="status-dropdown-item" data-status="paid">Paid</a>
                                                <a href="#" class="status-dropdown-item" data-status="rejected">Rejected</a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm text-gray-600"><?php echo htmlspecialchars(date('M d, Y', strtotime($p['date']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>

<div id="campaign-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Create New Invite Campaign</h3>
                <button id="close-modal-btn" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div id="modal-error-box" class="hidden p-3 mb-4 bg-red-100 border-l-4 border-red-500 text-red-800 rounded-r-lg" role="alert">
                <div class="flex">
                    <div class="py-1"><i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-3 flex-shrink-0"></i></div>
                    <div><p id="modal-error-message" class="font-semibold"></p></div>
                </div>
            </div>
            <form id="campaign-form" class="space-y-4">
                <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-200">
                    Creating a new campaign will deactivate the current one. Existing referrals will remain under their original campaign.
                </p>
                <div class="text-left">
                    <label for="campaign-name" class="form-label">Campaign Name</label>
                    <input type="text" id="campaign-name" placeholder="e.g. Q3 2025 Pre-seed" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none" required>
                </div>
                <div class="text-left">
                    <label for="modal-inviter-reward" class="form-label">Inviter Reward (%)</label>
                    <input type="number" id="modal-inviter-reward" min="0" max="100" step="0.1" placeholder="e.g. 5" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none" required>
                </div>
                <div class="text-left">
                    <label for="modal-invitee-bonus" class="form-label">Invitee Bonus (%)</label>
                    <input type="number" id="modal-invitee-bonus" min="0" max="100" step="0.1" placeholder="e.g. 5" class="w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none" required>
                </div>
                <div class="items-center pt-3">
                    <button id="submit-campaign-btn" type="submit" class="btn btn-primary w-full justify-center">
                        <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
                        <span>Create and Activate Campaign</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="toast-notification" class="fixed bottom-5 right-5 bg-green-500 text-white py-2 px-4 rounded-lg shadow-lg text-sm hidden">
    Changes saved successfully!
</div>

<style>
/* --- Use styles from layout.php where possible --- */
.card {
    background-color: #ffffff;
    border-radius: 0.75rem; /* 12px */
    border: 1px solid #e5e7eb; /* border-gray-200 */
    box-shadow: 0 1px 3px 0 rgba(0,0,0,0.07);
    transition: box-shadow 0.3s ease-in-out;
}
.card:hover {
    box-shadow: 0 4px 12px 0 rgba(0,0,0,0.08);
}
.btn-subtle {
    background-color: transparent;
    color: #1f2937; /* text-gray-800 */
    border: 1px solid #d1d5db; /* border-gray-300 */
}
.btn-subtle:hover {
    background-color: #f3f4f6; /* bg-gray-100 */
}
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Tooltip CSS Fix */
.tooltip .tooltip-text {
    visibility: hidden;
    width: 220px;
    background-color: #374151;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 8px;
    position: absolute;
    z-index: 10;
    bottom: 125%;
    left: 50%;
    margin-left: -110px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.75rem;
    font-weight: 400;
}
.tooltip:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}
.tooltip .tooltip-text::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #374151 transparent transparent transparent;
}

/* --- New Status Changer Styles --- */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem; /* 12px */
    font-weight: 500;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    justify-content: space-between;
}
.status-badge-pending { background-color: #f3f4f6; color: #374151; border-color: #d1d5db; }
.status-badge-due { background-color: #fef3c7; color: #92400e; border-color: #fcd34d; }
.status-badge-paid { background-color: #d1fae5; color: #065f46; border-color: #6ee7b7; }
.status-badge-rejected { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.status-badge .lucide-chevron-down { transition: transform 0.2s ease; }
.status-changer[data-open="true"] .lucide-chevron-down { transform: rotate(180deg); }

.status-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    background-color: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
    z-index: 20;
    width: 150px;
    overflow: hidden;
    opacity: 0;
    transform: translateY(-10px);
    visibility: hidden;
    transition: all 0.2s ease;
}
.status-changer[data-open="true"] .status-dropdown {
    opacity: 1;
    transform: translateY(0);
    visibility: visible;
}
.status-dropdown.hidden {
    display: none;
}
.status-dropdown-item {
    display: block;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    color: #374151;
    text-decoration: none;
    text-transform: capitalize;
}
.status-dropdown-item:hover {
    background-color: #f3f4f6;
}

@keyframes highlight-fade {
    from { background-color: #fef9c3; } /* yellow-100 */
    to { background-color: #ffffff; }
}
.highlight-on-update {
    animation: highlight-fade 1.5s ease-out;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        lucide.createIcons();
        
        // --- User Info Rendering (from dashboard) ---
        const renderUserInfo = (user) => {
            const userNameEl = document.getElementById('user-name');
            const userEmailEl = document.getElementById('user-email');
            const userInitialsEl = document.getElementById('user-initials');

            if (!user) {
                if(userNameEl) userNameEl.textContent = 'Founder Name';
                if(userEmailEl) userEmailEl.textContent = 'email@address.com';
                if(userInitialsEl) userInitialsEl.textContent = 'FN';
                return;
            };

            const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim();
            if(userNameEl) userNameEl.textContent = fullName || user.email || 'Founder';
            if(userEmailEl) userEmailEl.textContent = user.email || 'No email';

            let initials = (user.first_name?.[0] || '') + (user.last_name?.[0] || '');
            if (!initials && user.email) {
                initials = user.email[0];
            } else if (!initials) {
                initials = 'P';
            }
            if(userInitialsEl) userInitialsEl.textContent = initials.toUpperCase();
        };

        renderUserInfo(<?php echo json_encode($userInfo ?? null); ?>);
        // --- End User Info ---

        const pageError = <?php echo json_encode($page_error ?? null); ?>;
        if (pageError) {
            // If there's a page-level error, don't try to run the rest of the script.
            return;
        }

        const mainContentArea = document.querySelector('.main-content-area');
        const participantsTbody = document.getElementById('participants-tbody');
        const campaignModal = document.getElementById('campaign-modal');
        const campaignForm = document.getElementById('campaign-form');

        const activeCampaignId = "<?php echo htmlspecialchars($active_campaign['id'] ?? ''); ?>";
        let allCampaigns = <?php echo json_encode($all_campaigns ?? []); ?>;
        let hasUnsavedChanges = false;
        
        function showModalError(message) {
            const errorBox = document.getElementById('modal-error-box');
            const errorMessage = document.getElementById('modal-error-message');
            if (errorBox && errorMessage) {
                errorMessage.textContent = message;
                errorBox.classList.remove('hidden');
                lucide.createIcons();
            }
        }

        function hideModalError() {
            const errorBox = document.getElementById('modal-error-box');
            if (errorBox) {
                errorBox.classList.add('hidden');
            }
        }

        function openCampaignModal() {
            hideModalError();
            if(campaignModal) campaignModal.classList.remove('hidden');
        }

        function closeCampaignModal() {
            hideModalError();
            if(campaignModal) campaignModal.classList.add('hidden');
            if(campaignForm) campaignForm.reset();
        }

        function setUnsavedChanges(changed) {
            hasUnsavedChanges = changed;
            const saveButton = document.getElementById('save-changes-button');
            if(saveButton) saveButton.disabled = !changed;
        }

        function formatCurrency(amount, decimals = 0) {
            return new Intl.NumberFormat('en-US', { 
                style: 'currency', 
                currency: 'USD',
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(amount || 0);
        }

        function updateUI(data, highlight = false) {
            document.getElementById('kpi-total-investments').textContent = formatCurrency(data.kpis.total_investment_from_referrals || 0, 0);
            document.getElementById('kpi-pending-commissions').textContent = formatCurrency(data.kpis.total_pending_commissions || 0, 0);
            document.getElementById('kpi-due-commissions').textContent = formatCurrency(data.kpis.total_due_commissions || 0, 0);
            document.getElementById('kpi-paid-commissions').textContent = formatCurrency(data.kpis.total_paid_commissions || 0, 0);

            allCampaigns = data.campaigns || [];

            if (participantsTbody) {
                participantsTbody.innerHTML = '';
                if (data.participants && data.participants.length > 0) {
                    data.participants.forEach(p => {
                        const row = document.createElement('tr');
                        row.dataset.investmentId = p.investment_id;
                        row.dataset.campaignRef = p.campaign_reference || '';
                        row.className = 'border-b border-gray-200 last:border-b-0 hover:bg-gray-50 transition-colors';
                        const status = p.status || 'pending';
                        const statusClasses = { pending: 'status-badge-pending', due: 'status-badge-due', paid: 'status-badge-paid', rejected: 'status-badge-rejected' };
                        const statusClass = statusClasses[status] || 'status-badge-pending';
                        const ref = p.investment_reference || 'N/A';
                        const shortRef = ref.length > 12 ? `${ref.substring(0, 6)}...${ref.substring(ref.length - 6)}` : ref;
                        const campaignOptions = allCampaigns.map(c => `<option value="${c.id}" ${c.id == p.campaign_reference ? 'selected' : ''}>${c.campaign_name}${c.is_active ? ' (Active)' : ' (Inactive)'}</option>`).join('');
                        const date = p.date ? new Date(p.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
                        row.innerHTML = `<td class="p-4 truncate" title="${p.inviter_email || 'N/A'}">${p.inviter_email || 'N/A'}</td><td class="p-4 truncate" title="${p.invitee_email || 'N/A'}">${p.invitee_email || 'N/A'}</td><td class="p-4 font-mono text-xs text-gray-500 truncate" title="${ref}">${shortRef}</td><td class="p-4 font-medium text-gray-800">${formatCurrency(p.investment_amount || 0, 0)}</td><td class="p-4 font-semibold text-gray-700">${formatCurrency(p.inviter_commission_earned || 0, 0)}</td><td class="p-4 font-semibold text-gray-700">${formatCurrency(p.invitee_bonus_earned || 0, 0)}</td><td class="p-4"><select class="p-2 border rounded-md bg-white w-full campaign-select text-xs" data-original-value="${p.campaign_reference || ''}"><option value="">None</option>${campaignOptions}</select></td><td class="p-4"><div class="relative status-changer" data-original-status="${status}" data-current-status="${status}"><button class="status-badge ${statusClass}"><span class="capitalize">${status}</span><i data-lucide="chevron-down" class="w-4 h-4 ml-1"></i></button><div class="status-dropdown hidden"><a href="#" class="status-dropdown-item" data-status="pending">Pending</a><a href="#" class="status-dropdown-item" data-status="due">Due</a><a href="#" class="status-dropdown-item" data-status="paid">Paid</a><a href="#" class="status-dropdown-item" data-status="rejected">Rejected</a></div></div></td><td class="p-4 text-sm text-gray-600">${date}</td>`;
                        participantsTbody.appendChild(row);
                    });
                } else {
                    participantsTbody.innerHTML = '<tr><td colspan="9" class="text-center p-6 text-gray-500">No participants found.</td></tr>';
                }
            }


            const activeCampaign = data.campaigns.find(c => c.is_active);
            const inactiveState = document.getElementById('inactive-state');
            const activeState = document.getElementById('active-state');

            if (activeCampaign && activeState && inactiveState) {
                inactiveState.classList.add('hidden');
                activeState.classList.remove('hidden');
                document.getElementById('active-campaign-name').textContent = activeCampaign.campaign_name;
                document.getElementById('active-inviter-reward').textContent = `${activeCampaign.inviter_reward_percent}%`;
                document.getElementById('active-invitee-bonus').textContent = `${activeCampaign.invitee_bonus_percent}%`;
            } else if (inactiveState && activeState) {
                inactiveState.classList.remove('hidden');
                activeState.classList.add('hidden');
            }

            if (highlight) {
                const campaignCard = document.getElementById('campaign-status-section').closest('.card');
                if (campaignCard) {
                    campaignCard.classList.add('highlight-on-update');
                    setTimeout(() => campaignCard.classList.remove('highlight-on-update'), 1500);
                }
            }
            
            lucide.createIcons();
            setUnsavedChanges(false);
        }

        async function postData(payload) {
            payload.project_id = "<?php echo htmlspecialchars($project_id); ?>";
            const buttonToSpin = document.getElementById(payload.buttonId);
            let originalButtonHTML = '';

            if (buttonToSpin) {
                originalButtonHTML = buttonToSpin.innerHTML;
                buttonToSpin.disabled = true;
                buttonToSpin.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Processing...';
                lucide.createIcons();
            }
            
            const toast = document.getElementById('toast-notification');

            try {
                const response = await fetch('/backend/promotion_backend.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || `Server Error: ${response.status}.`);
                
                if (toast) {
                    toast.textContent = result.message || 'Success!';
                    toast.classList.remove('hidden', 'bg-red-500');
                    toast.classList.add('bg-green-500');
                    setTimeout(() => toast.classList.add('hidden'), 3000);
                }
                
                const highlight = ['create_invite_campaign', 'deactivate_campaign'].includes(payload.action);
                
                if (['update_participants', 'create_invite_campaign', 'deactivate_campaign'].includes(payload.action)) {
                    updateUI(result, highlight);
                }
                if (payload.action === 'create_invite_campaign') {
                    closeCampaignModal();
                }

            } catch (error) {
                console.error('Request failed:', error);
                if (payload.action === 'create_invite_campaign') {
                    showModalError(error.message);
                } else if (toast) {
                    toast.textContent = 'Error: ' + error.message;
                    toast.classList.remove('hidden', 'bg-green-500');
                    toast.classList.add('bg-red-500');
                    setTimeout(() => toast.classList.add('hidden'), 5000);
                }
            } finally {
                if (buttonToSpin) {
                    buttonToSpin.disabled = false;
                    buttonToSpin.innerHTML = originalButtonHTML;
                    lucide.createIcons();
                }
            }
        }

        // --- Event Handlers ---
        const handleCreateCampaign = (event) => {
            event.preventDefault();
            hideModalError();
            const campaignName = document.getElementById('campaign-name').value.trim();
            const inviterReward = document.getElementById('modal-inviter-reward').value;
            const inviteeBonus = document.getElementById('modal-invitee-bonus').value;

            if (!campaignName) return showModalError('Campaign Name is required.');
            if (inviterReward === '' || isNaN(parseFloat(inviterReward))) return showModalError('A valid Inviter Reward percentage is required.');
            if (inviteeBonus === '' || isNaN(parseFloat(inviteeBonus))) return showModalError('A valid Invitee Bonus percentage is required.');

            postData({ action: 'create_invite_campaign', campaign_name: campaignName, inviter_reward_percent: parseFloat(inviterReward), invitee_bonus_percent: parseFloat(inviteeBonus), buttonId: 'submit-campaign-btn' });
        };
        
        const handleDeactivateCampaign = () => {
            if (!activeCampaignId) return;
            postData({ action: 'deactivate_campaign', buttonId: 'deactivate-campaign-btn' });
        };
        
        const handleSaveChanges = () => {
            const participantChanges = [];
            if(participantsTbody) {
                participantsTbody.querySelectorAll('tr[data-investment-id]').forEach(row => {
                    const campaignSelect = row.querySelector('.campaign-select');
                    const statusChanger = row.querySelector('.status-changer');
                    const statusChanged = statusChanger.dataset.currentStatus !== statusChanger.dataset.originalStatus;
                    const campaignChanged = campaignSelect ? campaignSelect.value !== campaignSelect.dataset.originalValue : false;
                    if (campaignChanged || statusChanged) {
                        participantChanges.push({ investment_id: row.dataset.investmentId, status: statusChanger.dataset.currentStatus, campaign_reference: campaignSelect ? campaignSelect.value : row.dataset.campaignRef });
                    }
                });
            }


            if (participantChanges.length > 0) {
                postData({ action: 'update_participants', updates: participantChanges, buttonId: 'save-changes-button' });
            }
        };

        const exportParticipantsToCSV = () => {
            if(!participantsTbody) return;
            const headers = ["Inviter", "Invitee", "Investment Ref", "Amount", "Inviter Commission", "Invitee Bonus", "Campaign Name", "Commission Status", "Date"];
            let csvContent = headers.join(",") + "\r\n";
            participantsTbody.querySelectorAll("tr[data-investment-id]").forEach(row => {
                const cells = Array.from(row.cells);
                const campaignSelect = cells[6].querySelector('.campaign-select');
                let campaignName = campaignSelect ? campaignSelect.options[campaignSelect.selectedIndex].text : cells[6].textContent.trim();
                const rowData = [`"${cells[0].textContent.trim()}"`, `"${cells[1].textContent.trim()}"`, `"${cells[2].title}"`, `"${cells[3].textContent.replace(/[^0-9.]/g, '')}"`, `"${cells[4].textContent.replace(/[^0-9.]/g, '')}"`, `"${cells[5].textContent.replace(/[^0-9.]/g, '')}"`, `"${campaignName.trim()}"`, `"${row.querySelector('.status-changer').dataset.currentStatus}"`, `"${cells[8].textContent.trim()}"`];
                csvContent += rowData.join(",") + "\r\n";
            });
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", `promotions-export-${"<?php echo htmlspecialchars($project_id); ?>"}-${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
        
        // --- Central Event Listener ---
        if (mainContentArea) {
             mainContentArea.addEventListener('click', (event) => {
                const target = event.target;
                // Button clicks
                if (target.closest('#create-campaign-btn-inactive') || target.closest('#create-campaign-btn-active')) return openCampaignModal();
                if (target.closest('#deactivate-campaign-btn')) return handleDeactivateCampaign();
                if (target.closest('#save-changes-button')) return handleSaveChanges();
                if (target.closest('#export-csv-button')) return exportParticipantsToCSV();
                if (target.closest('#close-modal-btn')) return closeCampaignModal();

                // Status changer logic
                const statusChanger = target.closest('.status-changer');
                if (statusChanger) {
                    if (target.closest('.status-badge')) {
                        event.preventDefault();
                        const dropdown = statusChanger.querySelector('.status-dropdown');
                        const wasOpen = dropdown && !dropdown.classList.contains('hidden');
                        document.querySelectorAll('.status-changer[data-open="true"]').forEach(c => { 
                            const d = c.querySelector('.status-dropdown');
                            if(d) d.classList.add('hidden'); 
                            c.dataset.open = 'false'; 
                        });
                        if (!wasOpen && dropdown) { dropdown.classList.remove('hidden'); statusChanger.dataset.open = 'true'; }
                    }
                    if (target.closest('.status-dropdown-item')) {
                        event.preventDefault();
                        const newStatus = target.closest('.status-dropdown-item').dataset.status;
                        statusChanger.dataset.currentStatus = newStatus;
                        const badge = statusChanger.querySelector('.status-badge');
                        badge.className = 'status-badge'; 
                        const statusClasses = { pending: 'status-badge-pending', due: 'status-badge-due', paid: 'status-badge-paid', rejected: 'status-badge-rejected' };
                        badge.classList.add(statusClasses[newStatus] || 'status-badge-pending');
                        badge.querySelector('span').textContent = newStatus;
                        const dropdown = statusChanger.querySelector('.status-dropdown');
                        if (dropdown) dropdown.classList.add('hidden');
                        statusChanger.dataset.open = 'false';
                        setUnsavedChanges(true);
                    }
                    return;
                }

                // Close dropdown if clicking outside
                const openChanger = document.querySelector('.status-changer[data-open="true"]');
                if (openChanger && !openChanger.contains(target)) {
                    const d = openChanger.querySelector('.status-dropdown');
                    if (d) d.classList.add('hidden');
                    openChanger.dataset.open = 'false';
                }
            });
        }
       
        if (campaignForm) {
            campaignForm.addEventListener('submit', handleCreateCampaign);
        }

        if (participantsTbody) {
            participantsTbody.addEventListener('change', (event) => {
                if (event.target.matches('.campaign-select')) setUnsavedChanges(true);
            });
        }


        // --- Initial Data Rendering ---
        updateUI({ 
            kpis: <?php echo json_encode($kpis ?? []); ?>, 
            campaigns: <?php echo json_encode($all_campaigns ?? []); ?>, 
            participants: <?php echo json_encode($participants ?? []); ?> 
        });
        setUnsavedChanges(false);
    });
</script>
