<?php
// --- 1. LOAD BACKEND LOGIC FIRST ---
require_once __DIR__ . '/../backend/backerdashboard_backend.php';

// --- 2. DATA PREPARATION ---
if (!isset($page_data)) {
    $page_data = ['projectName' => 'Error Loading Project', 'successful_investments' => [], 'other_investments' => [], 'db_error' => 'Backend logic not loaded.'];
}

$successful_investments = $page_data['successful_investments'] ?? [];
$other_investments = $page_data['other_investments'] ?? [];

$totalContributed = 0;
$totalTokens = 0;

foreach ($successful_investments as $inv) {
    $totalContributed += $inv['investment_amount'];
    $totalTokens += $inv['token_quantity'];
}

$project_fee_usd = $page_data['project_fee'] ?? 1.0;
$fee_recipient_address = !empty($page_data['fee_recipient_address']) ? $page_data['fee_recipient_address'] : '0x2F8039cD25814C3987Dd3d4d547bFDd5B83e357E';
?>

<style>
    :root {
        --main-bg: #f9fafb;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border-color: #e5e7eb;
        --theme-primary: #10B981;
        --accent-neutral: #1f2937;
    }

    body { font-family: 'Montserrat', sans-serif; background-color: var(--main-bg); color: var(--text-primary); }

    .status-badge {
        display: inline-flex; align-items: center; padding: 0.25rem 0.75rem;
        font-size: 0.75rem; font-weight: 600; border-radius: 9999px;
        text-transform: capitalize;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .historical-header-row, .historical-item-row {
        display: grid;
        grid-template-columns: 1fr 1.5fr 1fr 1.2fr 1.8fr 1.1fr 0.9fr;
        gap: 1rem;
        align-items: center;
        padding: 0.75rem 1.5rem;
    }
    .historical-header-row {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        background-color: #f9fafb;
        border-radius: 0.5rem;
        margin-bottom: 0.75rem;
    }
    .historical-item-row {
        background-color: white;
        border-radius: 0.5rem;
        border: 1px solid var(--border-color);
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .stat-card {
        background-color: #ffffff;
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        padding: 1.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }

    .tooltip-container {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    .tooltip-trigger {
        margin-left: 0.5rem;
        color: #9ca3af;
        cursor: help;
    }
    .tooltip {
        position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
        margin-bottom: 0.5rem; background-color: #1f2937; color: white;
        padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem;
        line-height: 1.4; width: 220px; opacity: 0; visibility: hidden;
        transition: opacity 0.2s, visibility 0.2s; z-index: 50;
    }
    .tooltip-container:hover .tooltip { opacity: 1; visibility: visible; }
    
    .btn-primary {
        background-color: #10B981;
        color: white;
        border: none;
        transition: 0.3s;
    }
    .btn-primary:hover {
        background-color: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .btn-ghost {
        background-color: transparent;
        color: #10B981;
        border: 1px solid #10B981;
        transition: 0.2s ease-in-out;
    }
    .btn-ghost:hover {
        background-color: #f0fdf4;
        border-color: #059669;
        color: #059669;
        transform: translateY(-1px);
    }
    .btn-ghost:disabled {
        border-color: #e5e7eb;
        color: #9ca3af;
        cursor: not-allowed;
        transform: none;
    }

    .guide-card {
        border: 1px solid var(--border-color);
        border-radius: .75rem;
        background-color: white;
    }
    .guide-icon {
        flex-shrink: 0;
        width: 3rem;
        height: 3rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        background-color: #f0fdf4;
        color: #059669;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .guide-details {
        background-color: #ffffff;
        box-shadow: inset 0 2px 4px 0 rgba(0,0,0,0.03);
    }
</style>

<div class="max-w-7xl mx-auto p-6 md:p-8" id="dashboard-container" data-fee-usd="<?php echo $project_fee_usd; ?>" data-fee-recipient="<?php echo htmlspecialchars($fee_recipient_address); ?>">

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Project Dashboard</h1>
            <p class="text-gray-600 mt-1">Reviewing your position in <span class="font-semibold text-emerald-600"><?php echo htmlspecialchars($page_data['projectName'] ?? 'Unknown'); ?></span></p>
        </div>
        <div class="flex items-center gap-3">
            <?php
            $kycStatus = strtolower($page_data['kyc_status'] ?? 'pending');
            $isKycCompleted = in_array($kycStatus, ['completed', 'approved', 'verified', 'Verified']);
            ?>
            <div class="flex items-center bg-white border border-gray-200 rounded-lg px-4 py-2 shadow-sm">
                <span class="text-xs font-semibold text-gray-500 uppercase mr-3">Identity:</span>
                <span class="status-badge <?php echo $isKycCompleted ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-50 text-gray-500'; ?>">
                    <i data-lucide="<?php echo $isKycCompleted ? 'check-circle' : 'user-check'; ?>" class="w-3 h-3 mr-1.5"></i>
                    <?php echo $isKycCompleted ? 'Verified' : 'Pending'; ?>
                </span>
            </div>
            <button id="connect-wallet-btn-header" class="btn btn-primary font-semibold py-2 px-6 rounded-lg">
                Connect Wallet
            </button>
        </div>
    </div>

    <?php if (!empty($page_data['db_error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl flex items-center mb-8">
            <i data-lucide="alert-circle" class="w-5 h-5 mr-3"></i> <?php echo htmlspecialchars($page_data['db_error']); ?>
        </div>
    <?php else: ?>

        <!-- Stats Overview Cards -->
        <div id="portfolio-snapshot" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10" data-total-tokens="<?php echo $totalTokens; ?>">
            <div class="stat-card">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Total Contribution</p>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($totalContributed, 2); ?></p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Allocated Tokens</p>
                <p class="text-2xl font-bold text-gray-900" id="allocated-tokens-display"><?php echo number_format($totalTokens, 0); ?></p>
            </div>
            <div class="stat-card">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Vested</p>
                    <i data-lucide="lock" class="w-4 h-4 text-emerald-500"></i>
                </div>
                <p id="total-locked-tokens" class="text-2xl font-bold text-gray-900">--</p>
                <p class="text-[10px] text-gray-500 mt-1">Claimed: <span id="total-claimed-tokens">--</span></p>
            </div>
            <div class="stat-card">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Claimable</p>
                    <i data-lucide="unlock" class="w-4 h-4 text-emerald-500"></i>
                </div>
                <p id="total-claimable-tokens" class="text-2xl font-bold text-gray-900">--</p>
            </div>
        </div>

        <!-- Contribution Ledger -->
        <h2 class="text-xl font-semibold text-gray-800 mb-6">Contribution Ledger</h2>
        <div class="historical-header-row">
            <span>Date</span>
            <span>Private Sale</span>
            <span>Contribution</span>
            <span>Tokens</span>
            <span>Vesting Terms</span>
            <span>Status</span>
            <span class="text-right">Action</span>
        </div>

        <div id="ledger-items">
            <?php 
            function renderDashboardRow($inv) {
                // Use backend-provided status data directly
                $displayStatus = $inv['investorStatus'] ?? 'Review';
                $statusClass = $inv['investorStatusClass'] ?? 'bg-gray-50 text-gray-500 border border-gray-200';
                $statusDesc = $inv['investorDescription'] ?? 'Status check required.';
                
                $canRefund = ($displayStatus === 'Refunding');
                $tokenQty = $inv['token_quantity'] ?? 0;
                $hasWallet = !empty($inv['investor_wallet_address']);
                $streamId = $inv['distribution_stream_id'] ?? null;
                
                // Hide Link Wallet for refunding/failed/canceled statuses
                $isRefundOrFailed = in_array($displayStatus, ['Refunding', 'Refunded', 'Failed', 'Canceled']);
                $showLinkWallet = !$hasWallet && !$isRefundOrFailed;
                ?>
                <div class="historical-item-row <?php echo !empty($streamId) ? 'sablier-stream' : ''; ?>" <?php echo !empty($streamId) ? 'data-stream-id="'.htmlspecialchars($streamId).'"' : ''; ?>>
                    <div class="text-gray-500 font-medium text-xs"><?php echo date('M d, Y', strtotime($inv['investment_date'])); ?></div>
                    <div>
                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($inv['investment_round'] ?? 'Round'); ?></div>
                        <div class="text-[10px] text-gray-500 uppercase font-bold"><?php echo htmlspecialchars($inv['sale_name'] ?? ''); ?></div>
                    </div>
                    <div class="font-bold text-gray-900">
                        $<?php echo number_format($inv['investment_amount'], 2); ?>
                    </div>
                    <div>
                        <div class="text-gray-900 font-bold"><?php echo number_format($tokenQty, 0); ?></div>
                        <?php if($streamId): ?>
                        <div class="text-[9px] text-gray-400 mt-1.5 pt-1.5 border-t border-gray-100 space-y-1">
                            <div class="flex justify-between gap-2"><span>Remaining:</span> <span class="live-locked font-bold text-gray-600">--</span></div>
                            <div class="flex justify-between gap-2 text-emerald-600"><span>Claimed:</span> <span class="live-claimed font-bold">--</span></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-[10px] text-gray-500 leading-tight">
                        <span class="block">TGE Unlock: <span class="text-gray-900 font-semibold"><?php echo $inv['percent_unlock_at_tge'] ?? 0; ?>%</span></span>
                        <span class="block">Cliff: <span class="text-gray-900 font-semibold"><?php echo $inv['cliff_months'] ?? 0; ?> mo</span></span>
                        <span class="block">Vesting: <span class="text-gray-900 font-semibold"><?php echo $inv['vesting_months'] ?? 0; ?> mo</span></span>
                    </div>
                    <div>
                        <div class="tooltip-container">
                            <span class="status-badge <?php echo $statusClass; ?> whitespace-nowrap cursor-help">
                                <?php echo htmlspecialchars($displayStatus); ?>
                            </span>
                            <div class="tooltip"><?php echo htmlspecialchars($statusDesc); ?></div>
                        </div>
                    </div>
                    <div class="text-right relative z-10">
                        <?php if ($canRefund): ?>
                            <!-- Updated Refund Button: Purple & Added data-wallet for verification -->
                            <button class="btn btn-primary text-xs py-1.5 px-4 bg-purple-600 hover:bg-purple-700 claim-refund-btn"
                                data-investment-id="<?php echo $inv['id']; ?>"
                                data-contract="<?php echo htmlspecialchars($inv['sale_contract_address'] ?? ''); ?>"
                                data-amount="<?php echo $inv['investment_amount']; ?>"
                                data-wallet="<?php echo htmlspecialchars($inv['investor_wallet_address'] ?? ''); ?>">
                                Refund
                            </button>
                        <?php elseif ($showLinkWallet): ?>
                            <div class="flex flex-col items-end gap-1 unlinked-wallet-actions" data-investment-id="<?php echo $inv['id']; ?>">
                                <span class="text-[9px] text-red-500 font-bold uppercase mb-1">wallet missing</span>
                                <a href="/backend/receivingwallet_edit_backend.php?investment_id=<?php echo $inv['id']; ?>&redirect_to=/edit-wallet" class="text-[10px] text-emerald-600 hover:underline font-bold mb-1">
                                    Link Wallet
                                </a>
                            </div>
                        <?php elseif ($hasWallet && !$isRefundOrFailed): ?>
                            <?php if ($streamId): ?>
                                <button class="btn btn-ghost text-xs py-1.5 px-4 claim-vesting-btn font-bold rounded-lg"
                                    data-stream-id="<?php echo htmlspecialchars($streamId); ?>"
                                    data-token-decimals="18"
                                    data-investment-id="<?php echo $inv['id']; ?>"
                                    data-recipient-wallet="<?php echo htmlspecialchars($inv['investor_wallet_address'] ?? ''); ?>">
                                    Claim
                                </button>
                                <div class="text-[9px] text-gray-400 mt-1 live-claimable-container hidden">
                                    Ready: <span class="live-claimable font-bold text-emerald-600">--</span>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-300 text-[10px] font-bold uppercase italic block text-right">Awaiting Stream</span>
                            <?php endif; ?>
                            
                            <!-- Display Saved Wallet for Verification -->
                            <div class="mt-2 text-[9px] text-gray-400">
                                <span class="uppercase font-bold text-[8px] block opacity-70">Receiving Wallet</span>
                                <span class="font-mono text-emerald-600">
                                    <?php 
                                    $addr = $inv['investor_wallet_address'];
                                    echo !empty($inv['wallet_label']) ? htmlspecialchars($inv['wallet_label']) : (substr($addr, 0, 6) . '...' . substr($addr, -4));
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php }
            foreach ($other_investments as $inv) renderDashboardRow($inv);
            foreach ($successful_investments as $inv) renderDashboardRow($inv);
            ?>
        </div>

        <!-- Status Legend & Vesting Intelligence -->
        <section id="guides-section" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-12">
            <!-- Contribution Status Legend -->
            <div class="guide-card"> 
                <div class="p-6"> 
                    <div class="flex justify-between items-start gap-4"> 
                        <div class="flex items-start gap-4"> 
                            <div class="guide-icon"> <i data-lucide="shield-check" class="w-6 h-6"></i> </div> 
                            <div> 
                                <h4 class="text-lg font-semibold text-gray-800">Status Legend</h4> 
                                <p class="text-sm text-gray-500 mt-1">Understand your contribution stages.</p> 
                            </div> 
                        </div> 
                        <button data-toggle="status-legend-details" class="text-sm font-semibold text-emerald-600 hover:text-emerald-500 flex items-center shrink-0"> 
                            <i data-lucide="chevron-down" class="w-5 h-5 toggle-icon"></i> 
                        </button> 
                    </div> 
                </div> 
                <div id="status-legend-details" class="hidden p-6 border-t guide-details text-sm text-gray-600"> 
                    <div class="grid grid-cols-1 gap-4">
                        <div class="flex items-start gap-4">
                            <span class="status-badge bg-white text-gray-500 border border-dashed border-gray-300 w-28 justify-center shrink-0">Pending</span>
                            <p class="leading-relaxed">Payment initiated. Waiting for blockchain network confirmation.</p>
                        </div>
                        <div class="flex items-start gap-4">
                            <span class="status-badge bg-emerald-50 text-emerald-700 border border-emerald-200 w-28 justify-center shrink-0">Active</span>
                            <p class="leading-relaxed">Payment successful. Funds are secured within the project's Smart Vault.</p>
                        </div>
                        <div class="flex items-start gap-4">
                            <span class="status-badge bg-slate-100 text-slate-700 border border-slate-200 w-28 justify-center shrink-0">Processing</span>
                            <p class="leading-relaxed">Sale concluded. The team is finalizing token allocations for distribution.</p>
                        </div>
                        <div class="flex items-start gap-4">
                            <span class="status-badge bg-transparent text-emerald-700 border border-emerald-500 font-bold w-28 justify-center shrink-0 text-[10px]">Vesting Active</span>
                            <p class="leading-relaxed">Distribution has begun. Tokens are streaming to your wallet live.</p>
                        </div>
                        <!-- Refunding in Purple -->
                        <div class="flex items-start gap-4">
                            <span class="status-badge bg-purple-50 text-purple-900 border border-purple-200 w-28 justify-center shrink-0">Refunding</span>
                            <p class="leading-relaxed">Funding goal not met. You can now withdraw your full contribution manually.</p>
                        </div>
                        <div class="flex items-start gap-4">
                            <span class="status-badge bg-gray-100 text-gray-500 border border-transparent w-28 justify-center shrink-0">Refunded</span>
                            <p class="leading-relaxed">Process complete. Funds have been returned to your origin wallet.</p>
                        </div>
                    </div>
                </div> 
            </div>

            <!-- Vesting Intelligence Guide -->
            <div class="guide-card"> 
                <div class="p-6"> 
                    <div class="flex justify-between items-start gap-4"> 
                        <div class="flex items-start gap-4"> 
                            <div class="guide-icon"> <i data-lucide="brain" class="w-6 h-6"></i> </div> 
                            <div> 
                                <h4 class="text-lg font-semibold text-gray-800">Vesting Intelligence</h4> 
                                <p class="text-sm text-gray-500 mt-1">How your tokens are released over time.</p> 
                            </div> 
                        </div> 
                        <button data-toggle="vesting-details" class="text-sm font-semibold text-emerald-600 hover:text-emerald-500 flex items-center shrink-0"> <i data-lucide="chevron-down" class="w-5 h-5 toggle-icon"></i> </button> 
                    </div> 
                </div> 
                <div id="vesting-details" class="hidden p-6 border-t guide-details text-sm text-gray-600 space-y-4"> 
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="font-bold text-gray-900 mb-1">Locked Tokens</p>
                            <p>Tokens currently bound by the smart contract. This equals your total allocation minus what has been released (Claimed + Claimable). Locked tokens decrease as they move into the "Ready" state.</p>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 mb-1">Claimed Tokens</p>
                            <p>The amount of tokens you have already successfully withdrawn from the vesting contract into your personal wallet.</p>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 mb-1">Awaiting Stream</p>
                            <p>Indicates that the sale is complete but the on-chain distribution stream hasn't started yet. Once the project creator initiates the stream on Sablier, this will turn into a "Claim" action.</p>
                        </div>
                    </div>
                    <div class="pt-4 border-t text-xs italic text-gray-500 flex items-center">
                        <i data-lucide="info" class="w-3 h-3 mr-1.5 text-emerald-500"></i>
                        Vested tokens are calculated live via Sablier. Remaining represents the portion currently remaining in the contract.
                    </div>
                </div> 
            </div>
        </section>

        <!-- Non-Custodial Notice -->
        <div class="mt-12 p-6 bg-gray-50 border border-gray-200 rounded-xl">
            <h5 class="text-sm font-bold text-gray-900 mb-2">Non-Custodial Notice</h5>
            <p class="text-xs text-gray-600 leading-relaxed">
                TOOKLE is a non-custodial platform. It does not hold user funds, private keys, or digital assets at any time. All transactions are executed directly on-chain through user wallets and smart contracts.
            </p>
        </div>

    <?php endif; ?>
</div>

<!-- Modal Container (Fixed width to max-w-md, Purple Text) -->
<div id="claim-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 transition-opacity opacity-0 pointer-events-none modal-hidden">
    <!-- Changed width to max-w-md for smaller popup -->
    <div class="bg-white rounded-2xl w-full max-w-md p-8 shadow-2xl transform scale-95 transition-transform duration-200 border border-gray-100">
        <div class="flex justify-between items-center mb-6">
            <h2 id="modal-title" class="text-xl font-bold text-gray-900">Execute Claim</h2> 
            <button class="modal-cancel-btn text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- REFUND SECTION (Changed Amber to Purple) -->
        <div id="refund-details-section" class="mb-6 hidden text-gray-600">
             <p>Contribution is eligible for a full refund.</p>
             <!-- Changed text-amber-700 to text-purple-700 -->
             <p class="mt-2 font-bold text-purple-700 text-lg">Total: <span id="refund-display-amount">--</span></p>
             <div id="refund-id-display" class="hidden"></div>
             <div id="refund-contract-display" class="hidden"></div>
        </div>
        <div id="refund-step-2" class="hidden mb-6">
            <div id="refund-step-2-status" class="text-sm text-emerald-600 font-bold hidden">Success</div>
        </div>
        
        <!-- VESTING SECTION -->
        <div id="vesting-details-section" class="mb-6 hidden">
             <div id="claim-modal-intro" class="text-sm text-gray-600 mb-4"></div>
             <div class="relative mb-6">
                 <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                 <input type="number" id="claim-amount-input" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 font-mono text-lg">
                 <button id="claim-max-btn" class="absolute right-4 bottom-4 text-emerald-600 font-bold text-sm">MAX</button>
             </div>

             <!-- Step 1: Pay Fee -->
             <div id="step-1" class="p-4 border border-gray-100 rounded-xl mb-3 transition-colors">
                 <div class="flex items-center justify-between">
                     <div>
                         <p class="text-sm font-bold text-gray-900">Step 1: Protocol Fee</p>
                         <p class="text-xs text-gray-500 step-description">Fee: ~$<?php echo number_format($project_fee_usd, 2); ?> in ETH</p>
                     </div>
                     <button id="pay-fee-btn" class="btn btn-primary text-xs py-2 px-4 rounded-lg">Pay Fee</button>
                     <div class="step-status hidden text-emerald-600"><i data-lucide="check-circle" class="w-5 h-5"></i></div>
                 </div>
             </div>

             <!-- Step 2: Claim -->
             <div id="step-2" class="p-4 border border-gray-100 rounded-xl transition-colors">
                 <div class="flex items-center justify-between">
                     <div>
                         <p class="text-sm font-bold text-gray-400">Step 2: Transfer Tokens</p>
                         <p class="text-xs text-gray-400 step-description">Complete step 1 first</p>
                     </div>
                     <button id="claim-tokens-btn" disabled class="bg-gray-100 text-gray-400 text-xs font-bold py-2 px-4 rounded-lg cursor-not-allowed">Claim</button>
                     <div class="step-status hidden text-emerald-600"><i data-lucide="check-circle" class="w-5 h-5"></i></div>
                 </div>
             </div>
        </div>

        <div class="mt-6">
             <!-- Changed bg-purple-600 to keep it consistent (it was already purple here but checking consistency) -->
             <button id="execute-refund-btn" class="w-full bg-purple-600 text-white font-bold py-2.5 rounded-lg hover:bg-purple-700 hidden">Withdraw Refund</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="claim-success-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-[60]">
    <div class="bg-white rounded-2xl w-full max-w-sm p-8 text-center shadow-2xl border border-gray-100">
        <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="check" class="w-10 h-10"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Claim Success!</h2>
        <p class="text-sm text-gray-600 mb-6 leading-relaxed">Your tokens have been transferred to your wallet. You can view the transaction on-chain.</p>
        <a href="#" id="success-tx-hash-link" target="_blank" class="block mb-6 text-[10px] font-medium text-emerald-600 hover:text-emerald-900 hover:underline break-all bg-emerald-50 p-2 rounded border border-emerald-100 transition-colors">View Transaction</a>
        <button id="close-success-modal-btn" class="w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-900 text-sm font-bold rounded-xl transition-colors">Close Dashboard</button>
    </div>
</div>

<!-- Error Modal (NEW) -->
<div id="error-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-[70]">
    <div class="bg-white rounded-2xl w-full max-w-sm p-6 text-center shadow-2xl border border-red-100 relative">
        <button id="close-error-modal-x" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-8 h-8"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Action Failed</h3>
        <p id="error-modal-message" class="text-sm text-gray-600 mb-6 leading-relaxed">Something went wrong.</p>
        <button id="close-error-modal-btn" class="w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-sm font-bold rounded-xl transition-colors">Dismiss</button>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
<script src="/js/wallet-connector.js?v=<?php echo time(); ?>"></script>
<script src="/js/backerdashboard.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        
        // Handle guide toggle
        document.body.addEventListener('click', (e) => {
            const toggleButton = e.target.closest('[data-toggle]');
            if(toggleButton) {
                const targetId = toggleButton.dataset.toggle;
                const targetElement = document.getElementById(targetId);
                const icon = toggleButton.querySelector('.toggle-icon');
                if(targetElement) {
                    const isHidden = targetElement.classList.toggle('hidden');
                    icon.outerHTML = isHidden 
                        ? `<i data-lucide=\"chevron-down\" class=\"w-5 h-5 toggle-icon\"></i>` 
                        : `<i data-lucide=\"chevron-up\" class=\"w-5 h-5 toggle-icon\"></i>`;
                    lucide.createIcons();
                }
            }
        });
    });
</script>