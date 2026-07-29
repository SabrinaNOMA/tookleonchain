<?php
/**
 * pages/purchase.php
 * * CHANGES V3 (BRAND & LOGIC MERGE):
 * 1. FONT: Switched to 'Montserrat' to match brand guidelines.
 * 2. LOGIC: Applied V6 "Global Timelock" to prevent early reverts.
 * 3. GAS: Replaced hardcoded 250k limit with dynamic estimation + 50% buffer.
 */

$sidebar_mode = 'focus';

require_once 'src/session.php';
start_secure_session();

if (!isset($pdo)) {
    require_once __DIR__ . '/../src/db.php';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$user_id_for_query = $_SESSION['user_id'];
$error_message = null;
$round_data_loaded = false;

// Initialize variables
$user_full_name = "Investor";
$kyc_status = 'pending';
$kyc_valid = false;
$previous_investments = [];

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new Exception("DB Error: Connection not found");

    // 1. User & KYC Fetch
    $stmt_user = $pdo->prepare("SELECT first_name, last_name, kyc_status FROM user WHERE id = :user_id");
    $stmt_user->execute([':user_id' => $user_id_for_query]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if($user_info) {
        $user_full_name = trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''));
        $kyc_status = strtolower($user_info['kyc_status'] ?? 'pending');
        if (in_array($kyc_status, ['approved', 'verified', 'completed'])) {
            $kyc_valid = true;
        }
    }

    // 2. Context & Round Data
    $project_id_for_data = $_SESSION['selected_project_id'] ?? null;
    $sale_name_for_data = $_SESSION['selected_sale_name'] ?? null;
    if (!$project_id_for_data || !$sale_name_for_data) throw new Exception("No project selected.");
    
    $stmt_round = $pdo->prepare("
        SELECT tsp.*, p.project_name, p.token_name, p.id AS actual_project_id
        FROM token_sale_pages tsp
        JOIN projet p ON tsp.project_id = p.id
        WHERE tsp.project_id = :project_id AND tsp.sale_name = :sale_name AND tsp.status = 'live'
        LIMIT 1
    ");
    $stmt_round->execute([':project_id' => $project_id_for_data, ':sale_name' => $sale_name_for_data]);
    $round_data = $stmt_round->fetch(PDO::FETCH_ASSOC);

    if (!$round_data) throw new Exception("This round is no longer active.");

    $contract_address = $round_data['contract_address'];
    $payment_token_address = $round_data['payment_token'];
    
    // Fetch Agreement
    $stmt_agreement = $pdo->prepare("SELECT id, content FROM agreement_versions WHERE projet_id = :pid AND is_active = 1 LIMIT 1");
    $stmt_agreement->execute(['pid' => $project_id_for_data]);
    $activeAgreement = $stmt_agreement->fetch(PDO::FETCH_ASSOC);
    $agreement_text_content = $activeAgreement ? $activeAgreement['content'] : '[]';
    $active_agreement_version_id = $activeAgreement ? $activeAgreement['id'] : null;

    $round_json_data = json_decode($round_data['sale_terms_json'] ?? '{}', true);
    $token_price = (float)($round_json_data['round_price'] ?? 0.01);
    
    // Vesting Logic & Extras
    $vesting_tge = $round_json_data['percent_unlock_at_tge'] ?? 0;
    $vesting_cliff = $round_json_data['cliff_months'] ?? 0;
    $vesting_duration = $round_json_data['vesting_months'] ?? 0;
    $discount_percent = $round_json_data['percent_discount'] ?? 0;

    // Limits
    $min_amount = (float)($round_data['min_investment_usd'] ?? 100);
    $max_amount = (float)($round_data['max_investment_usd'] ?? 50000);
    $initial_investment = max($min_amount, 1000);

    // 3. Contribution History
    $stmt_prev = $pdo->prepare("
        SELECT id, amount_usd, token_quantity, created_at, status, 
               COALESCE(sale_name, investment_round) AS sale_name
        FROM investments 
        WHERE user_id = :user_id AND project_id = :project_id 
        ORDER BY created_at DESC
    ");
    $stmt_prev->execute([':user_id' => $user_id_for_query, ':project_id' => $round_data['actual_project_id']]);
    $previous_investments = $stmt_prev->fetchAll(PDO::FETCH_ASSOC);

    $round_data_loaded = true;
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/5.7.2/ethers.umd.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<!-- BRAND FONT: Montserrat -->
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* Base Styles - REPLACED Inter with Montserrat */
    body { font-family: 'Montserrat', sans-serif; background-color: #f9fafb; color: #111827; }
    
    .section-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    
    /* Brand Colors (Zinc-900) */
    .btn-primary { background-color: #111827; color: white; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-weight: 700; letter-spacing: -0.01em; }
    .btn-primary:hover:not(:disabled) { background-color: #000; transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    .btn-primary:active:not(:disabled) { transform: scale(0.98); }
    .btn-primary:disabled { background-color: #e5e7eb; color: #9ca3af; cursor: not-allowed; transform: none; box-shadow: none; opacity: 1; }
    
    /* Error State */
    .btn-error { background-color: #fee2e2 !important; color: #dc2626 !important; border: 1px solid #fecaca; }

    .progress-bar-bg { background: #f3f4f6; border-radius: 999px; height: 6px; overflow: hidden; }
    .progress-bar-fill { background: #111827; height: 100%; transition: width 1s ease; width: 0%; border-radius: 999px; }
    
    /* Modals */
    .modal-overlay { 
        position: fixed; inset: 0; 
        background: rgba(255, 255, 255, 0.9); /* Lighter, cleaner backdrop */
        backdrop-filter: blur(8px); 
        display: none; 
        align-items: center; justify-content: center; 
        z-index: 100; 
        opacity: 0; transition: opacity 0.3s ease;
    }
    .modal-overlay.active { display: flex; opacity: 1; }
    
    .modal-box { 
        background: white; 
        width: 90%; max-width: 600px; 
        border-radius: 24px; 
        max-height: 90vh; 
        display: flex; flex-direction: column; 
        border: 1px solid #f3f4f6;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
        transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }
    .modal-overlay.active .modal-box { transform: scale(1); }

    .skeleton { background: linear-gradient(90deg, #f9fafb 25%, #f3f4f6 50%, #f9fafb 75%); background-size: 200% 100%; animation: loading 1.5s infinite; }
    @keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    
    .confirm-box { border: 1px solid #e5e7eb; padding: 1rem; border-radius: 12px; transition: 0.2s; cursor: pointer; display: flex; align-items: flex-start; gap: 12px; }
    .confirm-box:hover { background: #f9fafb; border-color: #d1d5db; }
    .confirm-box.checked { border-color: #111827; background: #f9fafb; }

    .loader { border: 2px solid #e5e7eb; border-top: 2px solid #111827; border-radius: 50%; width: 14px; height: 14px; animation: spin 1s linear infinite; display: inline-block; margin-left: 5px; vertical-align: middle;}
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    
    .stat-value { font-feature-settings: "tnum"; font-variant-numeric: tabular-nums; }
</style>

<div class="max-w-5xl mx-auto p-4 md:p-8">
    <?php if ($round_data_loaded): ?>
        
        <div class="mb-8">
            <a href="projects" class="text-sm text-gray-400 hover:text-gray-900 flex items-center mb-2 transition-colors"><i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Projects</a>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight uppercase"><?php echo htmlspecialchars($round_data['project_name']); ?></h1>
            <p class="text-gray-500 mt-1 font-medium">Contributing to <span class="font-bold text-gray-900"><?php echo htmlspecialchars($round_data['sale_name']); ?></span></p>
        </div>

        <?php if (!$kyc_valid): ?>
            <div class="bg-white border border-gray-200 rounded-3xl p-12 text-center max-w-2xl mx-auto shadow-sm">
                <div class="w-16 h-16 bg-yellow-50 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="shield-alert" class="w-8 h-8 text-yellow-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-3">Identity Verification</h2>
                <p class="text-gray-500 mb-8 max-w-md mx-auto">To comply with financial regulations, please complete your identity verification (KYC) before proceeding.</p>
                <div class="flex justify-center gap-4">
                    <a href="kyc" class="btn-primary px-8 py-4 rounded-xl font-bold shadow-lg">Start Verification</a>
                </div>
            </div>
        <?php else: ?>

            <div class="flex flex-col lg:flex-row gap-8">
                <!-- MAIN COLUMN -->
                <div class="lg:w-2/3 space-y-6">
                    
                    <!-- ALCHEMY POWERED STATUS CARD -->
                    <div id="vault-status-card" class="section-card p-8 bg-white relative overflow-hidden">
                        <div class="flex justify-between items-start mb-6 relative z-10">
                            <div>
                                <h3 class="text-[11px] font-black text-gray-400 uppercase tracking-widest flex items-center mb-1">
                                    <i data-lucide="activity" class="w-3 h-3 mr-2"></i> Vault Telemetry
                                </h3>
                                <div class="flex items-center gap-2">
                                    <span id="vault-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1.5 animate-pulse"></span> Connecting...
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Contract</p>
                                <a href="https://basescan.org/address/<?php echo $contract_address; ?>" target="_blank" class="text-xs font-mono text-gray-500 hover:text-black hover:underline flex items-center justify-end">
                                    <?php echo substr($contract_address, 0, 8) . '...' . substr($contract_address, -6); ?> 
                                    <i data-lucide="external-link" class="w-3 h-3 ml-1"></i>
                                </a>
                            </div>
                        </div>

                        <div id="vault-loading" class="space-y-4 relative z-10">
                            <div class="h-10 w-1/3 skeleton rounded-lg"></div>
                            <div class="h-4 w-full skeleton rounded-full"></div>
                        </div>

                        <div id="vault-content" class="hidden relative z-10">
                            <!-- METRICS GRID -->
                            <div class="grid grid-cols-2 gap-8 mb-6 border-b border-gray-50 pb-6">
                                <div>
                                    <p class="text-xs text-gray-400 font-medium mb-1 uppercase tracking-wide">Total Raised</p>
                                    <div class="flex items-baseline gap-1">
                                        <span id="onchain-raised" class="text-3xl font-extrabold text-gray-900 stat-value">$0</span>
                                        <span class="text-sm text-gray-400 font-medium">USD</span>
                                    </div>
                                    <p id="onchain-softcap" class="text-xs text-gray-400 mt-1">Goal: $0</p>
                                </div>
                                <div>
                                    <p id="time-label" class="text-xs text-gray-400 font-medium mb-1 uppercase tracking-wide">Time Remaining</p>
                                    <div id="time-remaining-box" class="flex items-baseline gap-1">
                                        <span id="time-remaining" class="text-3xl font-extrabold text-gray-900 stat-value">--</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1" id="deadline-date">Ends: --</p>
                                </div>
                            </div>

                            <!-- PROGRESS BAR -->
                            <div>
                                <div class="flex justify-between text-xs font-bold text-gray-900 mb-2 uppercase tracking-tight">
                                    <span>Progress</span>
                                    <span id="progress-percent-label">0%</span>
                                </div>
                                <div class="progress-bar-bg relative">
                                    <div id="progress-fill" class="progress-bar-fill relative z-10"></div>
                                    <!-- Goal Marker -->
                                    <div class="absolute top-0 bottom-0 w-0.5 bg-white z-20 border-l border-r border-gray-300 h-full" style="left: 100%" title="Goal"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Background Decor -->
                        <div class="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-gray-50 rounded-full blur-3xl opacity-50 z-0"></div>
                    </div>

                    <!-- CAMPAIGN ENDED BANNER -->
                    <div id="campaign-ended-banner" class="hidden section-card p-6 bg-gray-50 border-gray-200 text-center">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="lock" class="w-6 h-6 text-gray-500"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Campaign Ended</h3>
                        <p class="text-sm text-gray-500 mt-1">This vault is closed for new contributions.</p>
                    </div>

                    <!-- INVESTMENT INPUT -->
                    <div id="invest-ui-container">
                        <div class="section-card p-8">
                            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4">Your Contribution</h2>
                            <div class="relative rounded-2xl border border-gray-200 hover:border-gray-300 focus-within:border-black focus-within:ring-1 focus-within:ring-black transition-all bg-gray-50/50">
                                <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none font-medium text-gray-400 text-2xl">$</div>
                                <input type="number" id="investment-amount" step="any" class="block w-full pl-10 pr-6 py-6 border-none bg-transparent text-3xl font-bold text-gray-900 placeholder-gray-300 focus:ring-0 stat-value" 
                                       value="<?php echo $initial_investment; ?>" 
                                       min="<?php echo $min_amount; ?>" 
                                       max="<?php echo $max_amount; ?>">
                                <div class="absolute inset-y-0 right-5 flex items-center pointer-events-none">
                                    <span class="text-xs font-bold bg-white border border-gray-200 px-2 py-1 rounded text-gray-500">USDC</span>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-between text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                                <span>Min: $<?php echo number_format($min_amount, 0); ?></span>
                                <span>Limit: $<?php echo number_format($max_amount, 0); ?></span>
                            </div>
                        </div>

                        <div class="section-card p-8 mt-6">
                            
                            <div class="confirm-box mb-8" id="disclaimer-container">
                                <input id="disclaimer-checkbox" type="checkbox" class="h-5 w-5 rounded-md border-gray-300 text-black focus:ring-black cursor-pointer mt-0.5">
                                <label for="disclaimer-checkbox" class="text-sm text-gray-600 cursor-pointer select-none leading-relaxed">
                                    <span class="font-bold text-gray-900 block mb-0.5">I confirm understanding.</span> 
                                    I acknowledge my funds are held in the non-custodial Smart Vault and settle only upon success.
                                </label>
                            </div>

                            <div id="step-sign-wrapper">
                                <div class="flex justify-between items-center mb-6 border-b border-gray-50 pb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-xs">1</div>
                                        <div>
                                            <h3 class="font-bold text-gray-900 text-sm">Sign Agreement</h3>
                                            <p class="text-xs text-gray-500">Legal allocation lock.</p>
                                        </div>
                                    </div>
                                    <span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Required</span>
                                </div>
                                <button id="btn-open-agreement" disabled class="w-full btn-primary py-4 rounded-xl font-bold flex items-center justify-center text-sm uppercase tracking-wide">
                                    Review & Sign Agreement
                                    <i data-lucide="arrow-right" class="ml-2 w-4 h-4"></i>
                                </button>
                            </div>

                            <div id="step-pay-wrapper" class="hidden">
                                <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-xl flex items-center mb-8">
                                    <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 mr-3"></i>
                                    <span class="text-sm font-bold text-emerald-800">Agreement Secured</span>
                                </div>
                                
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-8 h-8 rounded-full bg-black text-white flex items-center justify-center font-bold text-xs">2</div>
                                    <h3 class="font-bold text-gray-900 text-sm">Fund Vault</h3>
                                </div>
                                
                                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-8 text-center">
                                    <div id="web3-connect-section">
                                        <button id="connectBtn" class="btn-primary px-10 py-4 rounded-xl font-bold shadow-lg text-sm uppercase tracking-wide">Connect Wallet</button>
                                        <div class="flex justify-center gap-4 mt-6 opacity-40 grayscale">
                                            <img src="https://upload.wikimedia.org/wikipedia/commons/3/36/MetaMask_Fox.svg" class="h-6 w-6">
                                            <img src="https://seeklogo.com/images/C/coinbase-coin-logo-C86F46D7B8-seeklogo.com.png" class="h-6 w-6">
                                            <img src="https://seeklogo.com/images/W/walletconnect-logo-EE83B50C97-seeklogo.com.png" class="h-6 w-6">
                                        </div>
                                    </div>
                                    
                                    <div id="web3-action-section" class="hidden space-y-4 max-w-sm mx-auto">
                                        <div class="flex gap-3">
                                            <button id="approveBtn" class="flex-1 bg-white border border-gray-200 text-gray-900 py-4 rounded-xl font-bold hover:bg-gray-50 transition-all text-xs uppercase tracking-wide">
                                                Authorize USDC
                                            </button>
                                            <button id="investBtn" disabled class="flex-1 btn-primary py-4 rounded-xl font-bold disabled:opacity-50 text-xs uppercase tracking-wide">
                                                Deposit Funds
                                            </button>
                                        </div>
                                        <div class="h-6">
                                            <p id="tx-logs" class="text-[10px] font-mono text-gray-400 uppercase"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR -->
                <div class="lg:w-1/3">
                    <div class="section-card p-8 sticky top-6">
                        <h3 class="text-[11px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-4 mb-6">Offering Summary</h3>
                        <div class="space-y-5 mb-8">
                            <div class="flex justify-between text-sm group">
                                <span class="text-gray-500 font-medium">Asset</span>
                                <span class="font-bold text-gray-900 group-hover:text-black transition-colors"><?php echo htmlspecialchars($round_data['token_name']); ?></span>
                            </div>
                            <div class="flex justify-between text-sm group">
                                <span class="text-gray-500 font-medium">Price per Token</span>
                                <span class="font-mono text-gray-900 font-bold bg-gray-50 px-2 py-0.5 rounded">$<?php echo number_format($token_price, 4); ?></span>
                            </div>
                            
                            <?php if ($discount_percent > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500 font-medium">Early Bird Discount</span>
                                <span class="font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded text-xs"><?php echo $discount_percent; ?>% OFF</span>
                            </div>
                            <?php endif; ?>

                            <div class="flex justify-between text-sm items-start pt-2">
                                <span class="text-gray-500 font-medium">Vesting</span>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900"><?php echo $vesting_tge; ?>% / TGE</div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <?php echo $vesting_cliff; ?>m Lock • <?php echo $vesting_duration; ?>m Linear
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-gray-100 flex justify-between items-center">
                                <span class="text-gray-400 font-black uppercase text-[10px] tracking-widest">Est. Allocation</span>
                                <span id="summary-token" class="text-lg font-black text-gray-900 tracking-tight">0 <?php echo htmlspecialchars($round_data['token_name']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="bg-red-50 text-red-700 p-8 rounded-2xl text-center">
            <h2 class="font-bold mb-2">Error Loading Round</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- SUCCESS MODAL -->
<div id="success-modal" class="modal-overlay">
    <div class="modal-box text-center p-8">
        <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-6 animate-[bounce_1s_infinite]">
            <i data-lucide="check" class="w-10 h-10 text-emerald-500"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Deposit Successful</h2>
        <p class="text-gray-500 text-sm mb-8 leading-relaxed max-w-xs mx-auto">Your funds are now securely held in the Smart Vault. If the campaign is successfull, your tokens will be distributed to your wallet upon TGE, otherwise you will be able to claim your funds in your dashboard.</p>
        <a href="portfolio" class="block w-full btn-primary py-4 rounded-xl font-bold text-sm uppercase tracking-wide">View Portfolio</a>
    </div>
</div>

<?php include 'partials/legal_signing_modal.php'; ?>

<!-- ERROR MODAL -->
<div id="error-modal" class="modal-overlay">
    <div class="modal-box text-center p-8">
        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="alert-octagon" class="w-8 h-8 text-red-500"></i>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Transaction Failed</h2>
        <p id="error-modal-msg" class="text-gray-500 text-sm leading-relaxed mb-6">An unexpected error occurred.</p>
        <div class="bg-gray-100 p-3 rounded-lg text-[10px] font-mono text-left mb-6 text-gray-600 overflow-x-auto hidden border border-gray-200" id="error-debug"></div>
        <button onclick="document.getElementById('error-modal').classList.remove('active')" class="w-full bg-gray-100 text-gray-900 py-3 rounded-xl font-bold text-xs uppercase tracking-wide hover:bg-gray-200 transition-all">
            Dismiss
        </button>
    </div>
</div>

<script>
    lucide.createIcons();

    // Data from PHP (Sanitized)
    const VAULT_ADDRESS = "<?php echo $contract_address; ?>";
    const USDC_ADDRESS = "<?php echo $payment_token_address; ?>";
    const TOKEN_PRICE = <?php echo $token_price; ?>;
    const TOKEN_NAME = "<?php echo htmlspecialchars($round_data['token_name']); ?>";
    const AGREEMENT_JSON = <?php echo json_encode($agreement_text_content); ?>;
    const CSRF_TOKEN = "<?php echo $_SESSION['csrf_token']; ?>";
    const PROJECT_ID = "<?php echo $project_id_for_data; ?>";
    const SALE_NAME = "<?php echo $sale_name_for_data; ?>";
    const AGREEMENT_ID = "<?php echo $active_agreement_version_id; ?>";
    const BASE_CHAIN_ID = 8453;

    // --- 1. SILICON VALLEY ENGINE (RPC FAILOVER) ---
    // Alchemy High Performance Node included
    const RPC_LIST = [
        "https://base-mainnet.g.alchemy.com/v2/FW0D58_KXM7iLCmKNUeVU", 
        "https://base.publicnode.com",                                   
        "https://mainnet.base.org",                                      
        "https://1rpc.io/base"                                           
    ];

    const VAULT_ABI = [
        "function goal() view returns (uint256)",
        "function totalContributed() view returns (uint256)",
        "function deadline() view returns (uint256)",
        "function startTimestamp() view returns (uint256)", // V6: Publicly fetch start
        "function isFinalized() view returns (bool)",
        "function contribute(uint256 amount) external"
    ];

    const ERC20_ABI = [
        "function approve(address spender, uint256 amount) external returns (bool)",
        "function decimals() view returns (uint8)",
        "function balanceOf(address account) view returns (uint256)",
        "function allowance(address owner, address spender) view returns (uint256)"
    ];

    let provider, signer, vaultContract, usdcContract;
    let tokenDecimals = 6; 
    let campaignEnded = false;
    let isWarmup = false; // V6: Track Warmup State
    
    function showError(msg, debugInfo = null) {
        document.getElementById('error-modal-msg').innerText = msg;
        const debugEl = document.getElementById('error-debug');
        if (debugInfo) {
            debugEl.innerText = debugInfo;
            debugEl.classList.remove('hidden');
        } else {
            debugEl.classList.add('hidden');
        }
        document.getElementById('error-modal').classList.add('active');
    }

    async function fetchJsonOrError(url, options) {
        const res = await fetch(url, options);
        const text = await res.text();
        try { return JSON.parse(text); } 
        catch (e) {
            console.error("Backend Error:", text);
            throw new Error("Server Error: " + text.substring(0, 100));
        }
    }

    async function getWorkingProvider() {
        for (let url of RPC_LIST) {
            try {
                const p = new ethers.providers.JsonRpcProvider({ url: url, timeout: 2500 });
                await p.getNetwork(); 
                return p;
            } catch (e) { console.warn("RPC Skipped:", url); }
        }
        return new ethers.providers.JsonRpcProvider("https://mainnet.base.org");
    }

    function formatTimeRemaining(seconds) {
        if (seconds <= 0) return "Expired";
        const d = Math.floor(seconds / (3600*24));
        const h = Math.floor((seconds % (3600*24)) / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        
        if (d > 0) return `${d}d ${h}h`;
        if (h > 0) return `${h}h ${m}m`;
        return `${m}m ${seconds % 60}s`;
    }

    async function initWeb3AndSync() {
        try {
            const readProvider = await getWorkingProvider();
            const readVault = new ethers.Contract(VAULT_ADDRESS, VAULT_ABI, readProvider);
            
            // Fetch Critical Vault Data
            const [raisedBN, goalBN, deadlineBN, startBN, isFinalized] = await Promise.all([
                readVault.totalContributed(),
                readVault.goal(),
                readVault.deadline(),
                readVault.startTimestamp(),
                readVault.isFinalized()
            ]);
            
            const displayDecimals = 6; 
            const raised = parseFloat(ethers.utils.formatUnits(raisedBN, displayDecimals));
            const goal = parseFloat(ethers.utils.formatUnits(goalBN, displayDecimals));
            const deadline = deadlineBN.toNumber();
            const start = startBN.toNumber(); // V6
            const now = Math.floor(Date.now() / 1000);
            
            // 1. Update Raised Amount
            document.getElementById('onchain-raised').innerText = "$" + raised.toLocaleString(undefined, {maximumFractionDigits: 0});
            document.getElementById('onchain-softcap').innerText = "Goal: $" + goal.toLocaleString(undefined, {maximumFractionDigits: 0});
            
            // 2. Update Progress Bar
            const percent = goal > 0 ? (raised / goal) * 100 : 0;
            document.getElementById('progress-fill').style.width = Math.min(percent, 100) + "%";
            document.getElementById('progress-percent-label').innerText = percent.toFixed(1) + "%";

            // 3. Campaign Status Logic (V6 Upgrade)
            const timeLeft = deadline - now;
            
            // Format deadline date for display
            const deadlineDate = new Date(deadline * 1000).toLocaleString();
            document.getElementById('deadline-date').innerText = "Ends: " + deadlineDate;

            if (isFinalized || (timeLeft <= 0)) {
                // CAMPAIGN DEAD/ENDED
                campaignEnded = true;
                const badge = document.getElementById('vault-badge');
                badge.className = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800";
                badge.innerHTML = `<span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span> ${isFinalized ? 'Finalized' : 'Ended'}`;
                
                document.getElementById('time-remaining').innerText = isFinalized ? "Closed" : "Expired";
                // Show closing date clearly when ended
                document.getElementById('deadline-date').innerText = "Closed on: " + deadlineDate;
                
                // Disable UI
                document.getElementById('invest-ui-container').classList.add('hidden');
                document.getElementById('campaign-ended-banner').classList.remove('hidden');
            } 
            else if (now < start) {
                // V6: WARMUP MODE (The "15 Minute" Fix)
                isWarmup = true;
                const badge = document.getElementById('vault-badge');
                badge.className = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800";
                badge.innerHTML = `<span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1.5 animate-pulse"></span> Opening Soon`;
                
                document.getElementById('time-label').innerText = "Opens In";

                const timerEl = document.getElementById('time-remaining');
                const updateWarmup = () => {
                    const nowLoop = Math.floor(Date.now() / 1000);
                    const diff = start - nowLoop;
                    if(diff <= 0) location.reload(); // AUTO-UNLOCK
                    timerEl.innerText = formatTimeRemaining(diff);
                };
                updateWarmup();
                setInterval(updateWarmup, 1000);

                // Lock Invest Button
                const invBtn = document.getElementById('investBtn');
                if(invBtn) {
                     invBtn.disabled = true;
                     invBtn.classList.remove('btn-primary');
                     invBtn.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
                     invBtn.innerHTML = "<i data-lucide='lock' class='w-3 h-3 inline'></i> Locked (Warmup)";
                }
            }
            else {
                // CAMPAIGN LIVE
                const badge = document.getElementById('vault-badge');
                badge.className = "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800";
                badge.innerHTML = `<span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1.5 animate-pulse"></span> Live`;

                // Start Countdown
                const timerEl = document.getElementById('time-remaining');
                const updateTimer = () => {
                    const nowLoop = Math.floor(Date.now() / 1000);
                    const diff = deadline - nowLoop;
                    if(diff <= 0) location.reload(); // Refresh if expired while watching
                    timerEl.innerText = formatTimeRemaining(diff);
                };
                updateTimer();
                setInterval(updateTimer, 1000); // Live countdown
            }

            document.getElementById('vault-loading').classList.add('hidden');
            document.getElementById('vault-content').classList.remove('hidden');
            
        } catch (err) {
            console.error("Alchemy Sync Error:", err);
            document.getElementById('vault-badge').innerText = "Connection Error";
        }

        if (window.ethereum && !campaignEnded) {
            try {
                const web3Provider = new ethers.providers.Web3Provider(window.ethereum);
                const accounts = await web3Provider.listAccounts();
                if (accounts.length > 0) {
                    await connectWalletInternal(web3Provider);
                }
            } catch (e) { console.log("Wallet check silent fail"); }
        }
    }

    async function connectWalletInternal(web3Provider) {
        if(campaignEnded) return; // Block wallet actions if ended
        
        const network = await web3Provider.getNetwork();
        if (network.chainId !== BASE_CHAIN_ID) {
            document.getElementById('vault-badge').innerText = "Wrong Network";
            return;
        }

        signer = web3Provider.getSigner();
        
        document.getElementById('web3-connect-section').classList.add('hidden');
        document.getElementById('web3-action-section').classList.remove('hidden');
        
        usdcContract = new ethers.Contract(USDC_ADDRESS, ERC20_ABI, signer);
        vaultContract = new ethers.Contract(VAULT_ADDRESS, VAULT_ABI, signer);
        tokenDecimals = await usdcContract.decimals();
        
        checkAndSyncAllowance();
    }

    const amountInput = document.getElementById('investment-amount');
    const summaryToken = document.getElementById('summary-token');
    
    function updateSummary() {
        const val = parseFloat(amountInput.value) || 0;
        const tokens = val / TOKEN_PRICE;
        summaryToken.innerText = `${tokens.toLocaleString(undefined, {maximumFractionDigits:0})} ${TOKEN_NAME}`;
        if (signer) checkAndSyncAllowance();
    }
    if (amountInput) {
        amountInput.addEventListener('input', updateSummary);
        updateSummary();
    }

    // --- AGREEMENT LOGIC ---
    const disclaimerChk = document.getElementById('disclaimer-checkbox');
    const signTrigger = document.getElementById('btn-open-agreement');

    if (disclaimerChk) {
        disclaimerChk.addEventListener('change', (e) => {
            signTrigger.disabled = !e.target.checked;
            document.getElementById('disclaimer-container').classList.toggle('checked', e.target.checked);
        });
    }

    if (signTrigger) {
        signTrigger.addEventListener('click', () => {
            const val = parseFloat(amountInput.value) || 0;
            const usdAmount = val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const price = (typeof TOKEN_PRICE !== 'undefined' && TOKEN_PRICE > 0) ? TOKEN_PRICE : 1; 
            const tokenQty = (val / price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 });
            
            if (typeof openLegalModal === 'function') {
                openLegalModal(AGREEMENT_JSON, usdAmount, tokenQty); 
            }
        });
    }

    // CALLBACK
    async function handleLegalSignature(details) {
        const btn = document.getElementById('legal-sign-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = "<i data-lucide='loader-2' class='animate-spin w-4 h-4'></i> Sealing...";
        btn.disabled = true;
        
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('agreement_version_id', AGREEMENT_ID);
        formData.append('amount_usd', amountInput.value);
        formData.append('signer_fname', details.firstName);
        formData.append('signer_lname', details.lastName);
        formData.append('signer_full_address', details.address + ", " + details.city + " " + details.zip);
        formData.append('signer_email', details.email);
        formData.append('signed_agreement_snapshot', details.fullSnapshot);
        formData.append('digital_agreement_hash', details.digitalHash);
        formData.append('disclaimer_accepted', details.disclaimer_accepted || 'on');
        formData.append('terms', details.terms || 'on');

        try {
            const json = await fetchJsonOrError('backend/purchase_backend.php', { method: 'POST', body: formData });
            if(json.success) {
                if(window.showLegalSuccess) window.showLegalSuccess();
                window.onLegalSigningComplete = function() {
                    document.getElementById('step-sign-wrapper').classList.add('hidden');
                    document.getElementById('step-pay-wrapper').classList.remove('hidden');
                    amountInput.disabled = true;
                    amountInput.classList.add('text-gray-400', 'bg-transparent');
                    if(signer) checkAndSyncAllowance();
                };
            } else { 
                alert(json.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        } catch(e) { 
            alert("System Error: " + e.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    const connectBtn = document.getElementById('connectBtn');
    const approveBtn = document.getElementById('approveBtn');
    const investBtn = document.getElementById('investBtn');
    const txLogs = document.getElementById('tx-logs');
    

    async function checkAndSyncAllowance() {
        if (!signer || campaignEnded) return;
        try {
            const userAddress = await signer.getAddress();
            if (!usdcContract) usdcContract = new ethers.Contract(USDC_ADDRESS, ERC20_ABI, signer);
            
            const currentAllowance = await usdcContract.allowance(userAddress, VAULT_ADDRESS);
            const requiredAmount = ethers.utils.parseUnits(amountInput.value || "0", tokenDecimals);

            if (currentAllowance.gte(requiredAmount) && requiredAmount.gt(0)) {
                approveBtn.innerText = "Authorized";
                approveBtn.disabled = true;
                approveBtn.classList.remove('text-gray-900', 'bg-white', 'border');
                approveBtn.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-200');
                
                // V6: Only unlock if not in warmup
                if (!isWarmup) {
                    investBtn.disabled = false;
                }
            } else {
                approveBtn.innerText = "Authorize USDC";
                approveBtn.disabled = false;
                approveBtn.classList.add('text-gray-900', 'bg-white', 'border');
                approveBtn.classList.remove('bg-emerald-50', 'text-emerald-700');
                investBtn.disabled = true;
            }
        } catch (e) { console.warn("Allowance sync error:", e); }
    }

    if (connectBtn) {
        connectBtn.addEventListener('click', async () => {
            if(!window.ethereum) return showError("Web3 Wallet Not Found.");
            try {
                const wp = new ethers.providers.Web3Provider(window.ethereum);
                await wp.send("eth_requestAccounts", []);
                const network = await wp.getNetwork();
                if (network.chainId !== BASE_CHAIN_ID) {
                    await window.ethereum.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: '0x2105' }] });
                }
                await connectWalletInternal(wp);
            } catch (e) { showError("Wallet connection failed."); }
        });
    }

    if (approveBtn) {
        approveBtn.addEventListener('click', async () => {
            approveBtn.innerHTML = "Processing... <span class='loader'></span>";
            approveBtn.disabled = true;
            try {
                if (!usdcContract) usdcContract = new ethers.Contract(USDC_ADDRESS, ERC20_ABI, signer);
                const tx = await usdcContract.approve(VAULT_ADDRESS, ethers.constants.MaxUint256);
                txLogs.innerText = "Broadcasting Authorization...";
                await tx.wait();
                txLogs.innerText = "Authorized. Syncing...";
                
                let attempts = 0;
                const pollInterval = setInterval(async () => {
                    attempts++;
                    await checkAndSyncAllowance();
                    if (approveBtn.innerText === "Authorized" || attempts > 5) {
                        clearInterval(pollInterval);
                        txLogs.innerText = "";
                    }
                }, 1000);
            } catch (e) {
                showError("Authorization Failed: " + (e.reason || e.message));
                approveBtn.innerText = "Authorize USDC";
                approveBtn.disabled = false;
            }
        });
    }

    if (investBtn) {
        investBtn.addEventListener('click', async () => {
            if(isWarmup) return; // V6: Hard block for warmup
            
            investBtn.innerHTML = "Depositing... <span class='loader'></span>";
            investBtn.disabled = true;
            try {
                if (!vaultContract) vaultContract = new ethers.Contract(VAULT_ADDRESS, VAULT_ABI, signer);
                const amount = ethers.utils.parseUnits(amountInput.value, tokenDecimals);
                const userAddress = await signer.getAddress();

                const balance = await usdcContract.balanceOf(userAddress);
                if (balance.lt(amount)) throw new Error("Insufficient USDC Balance.");

                const allowance = await usdcContract.allowance(userAddress, VAULT_ADDRESS);
                if (allowance.lt(amount)) throw new Error("Allowance too low. Re-Authorize.");
                
                // V6: Simulation Check
                try {
                    await vaultContract.callStatic.contribute(amount);
                } catch(simErr) {
                    console.error("Simulation failed", simErr);
                    let reason = simErr.reason || simErr.message;
                    if(reason.includes("started")) throw new Error("Round hasn't started yet.");
                    if(reason.includes("ended")) throw new Error("Round has ended.");
                    // Fallback to let real tx try if unsure
                }

                // V6: Dynamic Gas + 50% Buffer
                let estimatedGas = await vaultContract.estimateGas.contribute(amount);
                estimatedGas = estimatedGas.mul(150).div(100);

                const tx = await vaultContract.contribute(amount, { gasLimit: estimatedGas });
                txLogs.innerText = "Broadcasting Deposit...";
                await tx.wait();
                
                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN);
                formData.append('action', 'record_tx');
                formData.append('tx_hash', tx.hash);
                formData.append('amount_usd', amountInput.value); 
                formData.append('project_id', PROJECT_ID);
                formData.append('sale_name', SALE_NAME);
                
                const json = await fetchJsonOrError('backend/purchase_backend.php', { method: 'POST', body: formData });
                if (json.success) document.getElementById('success-modal').classList.add('active');
                else showError("Record Warning: " + json.message);
            } catch (e) {
                let msg = e.reason || e.message;
                if (msg.includes("user rejected")) msg = "Transaction rejected.";
                showError(msg);
                investBtn.innerText = "Deposit Funds";
                investBtn.disabled = false;
            }
        });
    }

    window.addEventListener('load', initWeb3AndSync);
</script>