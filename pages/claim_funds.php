<?php
/**
 * pages/claim_funds.php
 * Interface for Founders to claim funds from a successful Smart Vault.
 * * VERSION: Silicon Valley v13.3 - SAFEGUARDED
 * * SECURITY: Added "Dead-End" UI State for invalid vault addresses.
 * * LOGIC: Checks bytecode on load. If 0x (EOA/Empty), blocks entire UI.
 */

// --- LAYOUT VARIABLES ---
$sidebar_mode = 'focus';
$focus_mode_title = "Smart Vault Manager";
$focus_mode_exit_url = "/dashboard";
$focus_mode_exit_text = "Exit";

// Ensure session is started for direct access
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['sale_id'])) $_SESSION['active_claim_sale_id'] = $_GET['sale_id'];
$project_id = $_SESSION['active_project_id'] ?? null;
$requested_sale_id = $_GET['sale_id'] ?? $_SESSION['active_claim_sale_id'] ?? null; 
$error_message = null;
$sale = null;
$is_fee_paid = 'false'; 

// Configuration Defaults
$platform_fee_wallet = "0x93B58E9B126E5A416366aEA4a190cbc6CbbD4629"; 
$platform_fee_bps = 350; 

if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/../src/db.php')) require_once __DIR__ . '/../src/db.php';
    elseif (file_exists(__DIR__ . '/../../src/db.php')) require_once __DIR__ . '/../../src/db.php';
}

if(isset($pdo)) {
    try {
        $stmt_fee_config = $pdo->prepare("SELECT success_fee_address, success_fee_bps FROM tookle_wallets WHERE status = 'active' LIMIT 1");
        $stmt_fee_config->execute();
        $fee_config = $stmt_fee_config->fetch(PDO::FETCH_ASSOC);
        if ($fee_config) {
            $platform_fee_wallet = $fee_config['success_fee_address'];
            $platform_fee_bps = intval($fee_config['success_fee_bps']);
        }
    } catch (Exception $e) {}
}

if (!isset($_SESSION['user_id']) || (!$project_id && !$requested_sale_id)) {
    $error_message = "Unauthorized access. Please select a project from the dashboard.";
} else {
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("
                SELECT 
                    tsp.id, tsp.sale_name, tsp.contract_address, tsp.payment_token, 
                    tsp.soft_cap_usd, tsp.hard_cap_usd, tsp.status, 
                    p.project_name,
                    JSON_UNQUOTE(JSON_EXTRACT(tsp.sale_terms_json, '$.vault_custody_wallet')) as recipient_wallet
                FROM token_sale_pages tsp 
                JOIN projet p ON tsp.project_id = p.id
                WHERE tsp.id = ? AND p.founder_id = ?
            ");
            $stmt->execute([$requested_sale_id, $_SESSION['user_id']]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$sale) {
            $error_message = "No active sale found or access denied.";
        } elseif (empty($sale['contract_address'])) {
            $error_message = "This sale does not have a Smart Vault deployed.";
        } else {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt_fee = $pdo->prepare("SELECT id FROM success_fee WHERE sale_id = ? AND status = 'confirmed' LIMIT 1");
                $stmt_fee->execute([$sale['id']]);
                if ($stmt_fee->fetch()) $is_fee_paid = 'true';
            }
        }
    } catch (Exception $e) {
        $error_message = "Database System Error: " . $e->getMessage();
    }
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/5.7.2/ethers.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js"></script>
<script src="https://unpkg.com/@safe-global/safe-apps-sdk@8.1.0/dist/index.umd.js"></script>
<script src="https://unpkg.com/@safe-global/safe-apps-provider@0.18.3/dist/index.umd.js"></script>

<style>
    .tookle-modal-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 100; }
    .tookle-modal-box { background: white; width: 90%; max-width: 400px; border-radius: 12px; padding: 2rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; }
    .btn-action-primary { background-color: #1f2937; color: white; transition: all 0.2s; }
    .btn-action-primary:hover { background-color: #000000; }
    .btn-action-secondary { background-color: #4f46e5; color: white; transition: all 0.2s; }
    .btn-action-secondary:hover { background-color: #4338ca; }
    .step-node { display: flex; flex-direction: column; align-items: center; position: relative; flex: 1; }
    .step-circle { width: 32px; height: 32px; border-radius: 50%; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; background: white; z-index: 2; transition: all 0.3s; }
    .step-circle.active { border-color: #4f46e5; color: #4f46e5; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
    .step-circle.complete { background: #4f46e5; border-color: #4f46e5; color: white; }
    .step-line { position: absolute; top: 16px; left: 50%; width: 100%; height: 2px; background: #e5e7eb; z-index: 1; }
    .error-tooltip:hover::after { content: attr(title); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 10px; white-space: nowrap; z-index: 20; }
    
    /* NEW SAFEGUARD STYLES */
    #critical-error-screen { display: none; }
</style>

<div class="max-w-4xl mx-auto p-4 sm:p-8">

    <!-- UI SAFEGUARD: CRITICAL ERROR SCREEN -->
    <div id="critical-error-screen" class="bg-red-50 border border-red-100 rounded-2xl p-10 text-center shadow-sm">
        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="alert-octagon" class="w-8 h-8"></i>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2 uppercase tracking-tight">Configuration Error</h2>
        <p class="text-gray-600 font-medium mb-6">Something went wrong. We cannot fetch your vault contract address.</p>
        <div class="bg-white p-4 rounded-xl border border-red-100 inline-block text-left mb-8 max-w-lg">
             <p class="text-xs text-red-500 font-bold uppercase mb-2">System Diagnostic</p>
             <p class="text-sm text-gray-600 mb-1">The address saved for this vault is not a valid smart contract.</p>
             <p class="text-[10px] font-mono bg-gray-100 p-2 rounded text-gray-500 break-all">Address: <?php echo htmlspecialchars($sale['contract_address'] ?? 'Unknown'); ?></p>
        </div>
        <div class="flex flex-col gap-2 max-w-xs mx-auto">
            <p class="text-xs text-gray-400 font-medium">Please create a new vault or wait for synchronization.<br>Do not share this vault until it is fully synced.</p>
            <a href="/dashboard" class="mt-4 block w-full bg-gray-900 text-white py-3 rounded-xl font-bold hover:bg-black transition-colors text-xs uppercase tracking-widest text-center">Return to Dashboard</a>
        </div>
    </div>

    <!-- MAIN INTERFACE CONTAINER -->
    <div id="main-interface">
        <?php if ($error_message): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200 mb-6 flex flex-col items-start gap-2">
                <p class="font-semibold"><i data-lucide="alert-circle" class="inline w-4 h-4 mr-1"></i> Error</p>
                <p><?php echo htmlspecialchars($error_message ?? ''); ?></p>
                <a href="/dashboard" class="underline font-semibold text-sm mt-2">Return to Dashboard</a>
            </div>
            <?php return; ?>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 border-b border-gray-100 pb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2 text-gray-900"><i data-lucide="shield-check" class="w-6 h-6 text-gray-900"></i> Smart Vault Manager</h1>
                <p class="text-gray-500 mt-1 text-sm">Settlement layer for <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($sale['sale_name'] ?? ''); ?></span></p>
            </div>
            <div class="flex flex-col items-end gap-2">
                <div id="wallet-btn" class="px-5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm" onclick="connectWallet()">
                    <i data-lucide="wallet" class="w-4 h-4"></i> Connect Wallet
                </div>
                <button id="network-switch-btn" onclick="switchToBase()" class="hidden px-4 py-1.5 bg-red-50 border border-red-100 text-red-600 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-red-100 transition-colors flex items-center gap-2">
                    <i data-lucide="shuffle" class="w-3 h-3"></i> Switch to Base
                </button>
            </div>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 sm:p-8 mb-8">
            <div class="flex items-start justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600"><i data-lucide="box" class="w-5 h-5"></i></div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Vault Overview</h2>
                        <p class="text-xs text-gray-500 font-mono mb-1"><?php echo htmlspecialchars($sale['contract_address'] ?? ''); ?></p>
                        <a href="https://basescan.org/address/<?php echo htmlspecialchars($sale['contract_address'] ?? ''); ?>" target="_blank" class="inline-flex items-center text-[10px] font-medium text-indigo-600 hover:text-indigo-800 transition-colors relative z-10 cursor-pointer">
                            View on Basescan <i data-lucide="external-link" class="w-3 h-3 ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <span id="contract-status-badge" title="Waiting for sync..." class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500 uppercase tracking-wide transition-all relative error-tooltip">Waiting...</span>
                    <button onclick="manualRefresh()" id="refresh-btn" class="flex items-center gap-2 px-3 py-1.5 text-[10px] font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition-all">
                        <i data-lucide="refresh-cw" class="w-3 h-3"></i> <span>Refresh Status</span>
                    </button>
                </div>
            </div>

            <div id="recipient-wallet-box" class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-lg">
                <div class="flex items-center gap-2 mb-2"><i data-lucide="user-check" class="w-4 h-4 text-slate-600"></i><h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Authorized Recipient Wallet</h3></div>
                <p class="text-xs text-slate-600 leading-relaxed mb-3">Funds can only be released to this address.</p>
                <div class="bg-white border border-slate-200 px-3 py-2 rounded font-mono text-xs text-indigo-700 break-all select-all">
                    <?php echo htmlspecialchars($sale['recipient_wallet'] ?? 'Fetching from contract...'); ?>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-6">
                <div id="vault-data-grid" class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-100"><p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Total Raised</p><p id="ui-raised" class="text-3xl font-bold text-gray-900 tracking-tight">--</p></div>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-100"><p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Soft Cap (Goal)</p><p id="ui-goal" class="text-3xl font-bold text-gray-500 tracking-tight">--</p></div>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-100"><p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Time Remaining</p><p id="ui-time" class="text-3xl font-bold text-gray-900 tracking-tight">--</p></div>
                </div>
            </div>
        </div>
        
        <div id="action-area" class="hidden border border-gray-200 bg-white rounded-xl p-8 mb-8">
            <div class="flex items-center mb-10 max-w-md mx-auto">
                <div class="step-node">
                    <div class="step-line"></div>
                    <div id="step-1-circle" class="step-circle text-xs font-bold">1</div>
                    <span class="text-[10px] uppercase font-bold text-gray-400 mt-2">Fee Payment</span>
                </div>
                <div class="step-node">
                    <div id="step-2-circle" class="step-circle text-xs font-bold">2</div>
                    <span class="text-[10px] uppercase font-bold text-gray-400 mt-2">Fund Claim</span>
                </div>
            </div>
            <div id="step-content" class="text-center"></div>
        </div>
        
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 mt-6">
            <h4 class="text-sm font-bold text-indigo-900 mb-2 flex items-center gap-2"><i data-lucide="info" class="w-4 h-4 text-indigo-600"></i> Disclaimer</h4>
            <div class="space-y-3">
                <p class="text-xs text-indigo-800 leading-relaxed">Fully onchain and non-custodial.</p>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="saleId" value="<?php echo htmlspecialchars($sale['id'] ?? ''); ?>">
<input type="hidden" id="contractAddr" value="<?php echo htmlspecialchars($sale['contract_address'] ?? ''); ?>">
<input type="hidden" id="tokenAddr" value="<?php echo htmlspecialchars($sale['payment_token'] ?? ''); ?>">
<input type="hidden" id="feeStatus" value="<?php echo $is_fee_paid; ?>">
<input type="hidden" id="platformFeeWallet" value="<?php echo htmlspecialchars($platform_fee_wallet ?? ''); ?>">
<input type="hidden" id="platformFeeBps" value="<?php echo $platform_fee_bps; ?>">
<input type="hidden" id="dbStatus" value="<?php echo htmlspecialchars($sale['status'] ?? ''); ?>">

<div id="success-modal" class="tookle-modal-overlay">
    <div class="tookle-modal-box">
        <div id="modal-icon-container" class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4"><i data-lucide="check" class="w-8 h-8 text-indigo-600"></i></div>
        <h3 id="modal-title" class="text-xl font-bold text-gray-900 mb-2">Transaction Complete</h3>
        <p id="modal-message" class="text-gray-500 text-sm mb-6"></p>
        <a id="modal-tx-link" href="#" target="_blank" class="block mb-6 text-[10px] font-medium text-indigo-600 hover:text-indigo-700 hover:underline break-all bg-indigo-50 p-2 rounded border border-indigo-100 transition-colors"></a>
        <button onclick="window.location.reload();" class="w-full py-3 bg-gray-900 hover:bg-black text-white text-sm font-bold uppercase rounded transition-colors">Finish</button>
    </div>
</div>

<script>
    // --- SILICON VALLEY ENGINE: MULTI-RPC FAILOVER ---
    // This list attempts to find ANY working door to the blockchain.
    const RPC_LIST = [
        "https://base-mainnet.g.alchemy.com/v2/FW0D58_KXM7iLCmKNUeVU", // Your Alchemy (Primary)
        "https://base.publicnode.com",                                   // Reliable Public (Secondary)
        "https://mainnet.base.org",                                      // Official (Backup)
        "https://base.meowrpc.com"                                       // Community (Last Resort)
    ];

    const CAMPAIGN_ABI = [
        "function totalContributed() view returns (uint256)", "function goal() view returns (uint256)",
        "function deadline() view returns (uint256)", "function isFinalized() view returns (bool)",
        "function finalizeProjectFunds() external", "function projectWallet() view returns (address)",
        "function startTimestamp() view returns (uint256)" // ADDED: Start timestamp for warmup check
    ];
    const ERC20_ABI = ["function decimals() view returns (uint8)", "function symbol() view returns (string)", "function balanceOf(address account) view returns (uint256)", "function transfer(address recipient, uint256 amount) external returns (bool)"];

    // Globals
    const SALE_ID = document.getElementById('saleId').value;
    const CONTRACT_ADDR = document.getElementById('contractAddr').value;
    const FEE_TOKEN_ADDR = document.getElementById('tokenAddr').value;
    const PLATFORM_WALLET = document.getElementById('platformFeeWallet').value;
    const PLATFORM_FEE_BPS = parseInt(document.getElementById('platformFeeBps').value);
    const BASE_CHAIN_ID = 8453;
    const DB_STATUS = document.getElementById('dbStatus').value;
    let HAS_PAID_FEE = document.getElementById('feeStatus').value === 'true'; 

    let signer, readContract;
    let decimals = 18; 
    let feeTokenSymbol = 'TOKEN';
    let totalRaisedHuman = 0;
    let safeSdk, safeConnected = false;
    let isRefreshing = false;

    // --- 1. ROBUST PROVIDER FINDER ---
    async function getWorkingProvider() {
        console.log("Locating best RPC node...");
        for (let url of RPC_LIST) {
            try {
                const p = new ethers.providers.JsonRpcProvider(url);
                p.pollingInterval = 99999999; 
                await p.getNetwork();
                console.log("Connected via:", url);
                return p;
            } catch (e) { console.warn("RPC Failed:", url); }
        }
        throw new Error("All RPCs are down. Check internet connection.");
    }

    // --- 2. INITIALIZATION ---
    window.addEventListener('load', async () => {
        console.log("App Loaded.");
        if(window.lucide) lucide.createIcons();
        
        try {
            // 1. Find a working provider first
            const provider = await getWorkingProvider();
            
            // --- UI SAFEGUARD: VERIFY CONTRACT CODE ---
            if (CONTRACT_ADDR && CONTRACT_ADDR.length > 20) {
                console.log("Verifying bytecode integrity...");
                const code = await provider.getCode(CONTRACT_ADDR);
                if (!code || code === '0x') {
                    console.error("SAFEGUARD TRIGGERED: No contract found at address.");
                    document.getElementById('main-interface').style.display = 'none';
                    document.getElementById('critical-error-screen').style.display = 'block';
                    return; // HALT EXECUTION
                }
            }
            // ------------------------------------------

            // 2. Initialize Contracts
            readContract = new ethers.Contract(CONTRACT_ADDR, CAMPAIGN_ABI, provider);
            
            // 3. Fetch Token Info
            if(FEE_TOKEN_ADDR) {
                try {
                    const t = new ethers.Contract(FEE_TOKEN_ADDR, ERC20_ABI, provider);
                    decimals = await t.decimals();
                    feeTokenSymbol = await t.symbol();
                } catch(e) { console.warn("Token load failed, defaulting to 18 decimals"); }
            }

            // 4. NOW fetch data (Single Shot)
            await fetchDataOnce();

            // 5. Wallet Checks
            checkSafeAndWallet();
            
        } catch(e) {
            console.error("Critical Init Error:", e);
            document.getElementById('contract-status-badge').innerText = "Offline";
            const refreshBtn = document.getElementById('refresh-btn');
            if(refreshBtn) refreshBtn.querySelector('span').innerText = "Retry Connection";
        }
    });

    // --- 3. FETCH DATA (SINGLE SHOT) ---
    async function fetchDataOnce() {
        if(isRefreshing) return;
        isRefreshing = true;
        
        const btn = document.getElementById('refresh-btn');
        const icon = btn.querySelector('i');
        const label = btn.querySelector('span');
        
        // UI: Loading
        if(icon) icon.classList.add('animate-spin');
        if(label) label.innerText = "Checking Chain...";
        btn.classList.add('opacity-75', 'cursor-not-allowed');

        try {
            console.log("Fetching Data...");
            
            // Serial Fetching (Safe)
            const raised = await readContract.totalContributed();
            const goal = await readContract.goal();
            const deadline = await readContract.deadline();
            const startTimestamp = await readContract.startTimestamp();
            const isFinalized = await readContract.isFinalized();

            updateUI(raised, goal, deadline, startTimestamp, isFinalized);

            // UI: Success
            if(label) label.innerText = "Up to Date";
            document.getElementById('contract-status-badge').title = "Synced successfully";

        } catch (e) {
            console.error("Fetch Failed:", e);
            if(label) label.innerText = "Sync Failed";
            document.getElementById('contract-status-badge').innerText = "Sync Error";
            document.getElementById('contract-status-badge').className = "px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-600 uppercase tracking-wide";
        } finally {
            // UI: Reset
            setTimeout(() => {
                if(label) label.innerText = "Refresh Status";
                if(icon) icon.classList.remove('animate-spin');
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                isRefreshing = false;
            }, 1000);
        }
    }
    
    function manualRefresh() {
        fetchDataOnce();
    }
    // --- 4. UI UPDATE LOGIC ---
    function updateUI(raised, goal, deadline, startTimestamp, isFinalized) {
        const now = Math.floor(Date.now() / 1000);
        const start = startTimestamp.toNumber();
        totalRaisedHuman = parseFloat(ethers.utils.formatUnits(raised, decimals));
        const goalNum = parseFloat(ethers.utils.formatUnits(goal, decimals));
        const feeAmount = totalRaisedHuman * (PLATFORM_FEE_BPS / 10000);

        document.getElementById('ui-raised').innerText = `$${totalRaisedHuman.toLocaleString()}`;
        document.getElementById('ui-goal').innerText = `$${goalNum.toLocaleString()}`;
        
        // Handle Warmup
        if (now < start) {
             const diff = start - now;
             document.getElementById('ui-time').innerText = `Opens in ${formatRemaining(start)}`;
             document.getElementById('contract-status-badge').innerText = "Warmup";
             document.getElementById('contract-status-badge').className = "px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 uppercase tracking-wide";
        } else {
             document.getElementById('ui-time').innerText = now < deadline ? formatRemaining(deadline) : "Ended";
        }

        const statusBadge = document.getElementById('contract-status-badge');
        let badgeText = 'Live'; let badgeClass = 'bg-indigo-100 text-indigo-700'; let realStatus = 'live';

        if (isFinalized) { 
            realStatus = 'ended_successful'; 
            badgeText = 'Settled'; 
            badgeClass = 'bg-slate-900 text-white'; 
        }
        else if (now >= deadline) {
            if (raised.gte(goal)) { 
                realStatus = 'ended_successful'; 
                badgeText = 'Ready to Claim'; 
                badgeClass = 'bg-green-100 text-green-700'; 
            }
            else { 
                realStatus = 'ended_failed'; 
                badgeText = 'Goal Not Reached'; 
                badgeClass = 'bg-red-100 text-red-700'; 
            }
        } else if (now < start) {
             badgeText = 'Warmup';
             badgeClass = 'bg-blue-100 text-blue-700';
        }
        
        statusBadge.innerText = badgeText; 
        statusBadge.className = `px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide ${badgeClass}`;

        if (DB_STATUS !== realStatus) {
             fetch('../backend/sync_sale_status.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ sale_id: SALE_ID, status: realStatus }) })
             .then(r => r.json().then(d => console.log('Sync status response:', d))).catch(e => console.error('Sync status failed:', e));
        }

        document.getElementById('action-area').classList.remove('hidden');
        const stepContent = document.getElementById('step-content');
        const recipientBox = document.getElementById('recipient-wallet-box');

        if (isFinalized) {
            if (recipientBox) recipientBox.classList.add('hidden');
            document.getElementById('step-1-circle').classList.add('complete'); document.getElementById('step-2-circle').classList.add('complete');
            stepContent.innerHTML = `<p class="text-green-600 font-bold">Vault Finalized. Funds Claimed.</p>`;
        } else if (now < start) {
             stepContent.innerHTML = `<p class="text-blue-500 font-bold">Vault is in warmup period. Please wait.</p>`;
        } else if (now < deadline) {
            stepContent.innerHTML = `<p class="text-gray-500">Wait for deadline to claim funds.</p>`;
        } else {
            if (raised.gte(goal)) {
                if (!HAS_PAID_FEE) {
                    document.getElementById('step-1-circle').classList.add('active');
                    stepContent.innerHTML = `<p class="text-sm text-gray-500 mb-1">Step 1: Protocol Settlement</p><h3 class="text-xl font-bold mb-4">Pay Platform Fee: ${feeAmount.toFixed(2)} ${feeTokenSymbol}</h3><button id="pay-fee-btn" onclick="payServiceFee('${feeAmount.toFixed(decimals)}')" class="btn-action-secondary py-4 px-10 rounded-xl font-bold uppercase tracking-wide">Pay Success Fee</button>`;
                } else {
                    document.getElementById('step-1-circle').classList.add('complete'); document.getElementById('step-2-circle').classList.add('active');
                    stepContent.innerHTML = `<p class="text-sm text-gray-500 mb-1">Step 2: Capital Release</p><h3 class="text-xl font-bold mb-4">Claim Fundraising Proceeds</h3><p class="text-xs text-red-500 mb-4 font-semibold">Platform Fees Paid. </p><button id="claim-btn" onclick="claimFunds()" class="btn-action-primary py-4 px-10 rounded-xl font-bold uppercase tracking-wide">Claim Funds</button>`;
                }
            } else { stepContent.innerHTML = `<p class="text-red-500 font-bold">Soft Cap Not Reached. Contributors can now refund.</p>`; }
        }
    }

    function formatRemaining(targetTime) {
        const now = Math.floor(Date.now() / 1000);
        const diff = targetTime - now;
        if (diff <= 0) return "00m 00s"; 
        return `${Math.floor(diff / 86400)}d ${Math.floor((diff % 86400) / 3600)}h ${Math.floor((diff % 3600) / 60)}m`;
    }

    // --- HELPERS & WALLET ---
    async function checkSafeAndWallet() {
        try {
            if (window.SafeAppsSDK) {
                const SafeSDK = window.SafeAppsSDK.default || window.SafeAppsSDK;
                const SafeProvider = window.SafeAppsProvider.default || window.SafeAppsProvider;
                safeSdk = new SafeSDK();
                const info = await Promise.race([safeSdk.safe.getInfo(), new Promise(r=>setTimeout(()=>r(null),1000))]);
                if (info) {
                    safeConnected = true;
                    setupSigner(new ethers.providers.Web3Provider(new SafeProvider(info, safeSdk)), info.safeAddress);
                    return;
                }
            }
        } catch (e) {}
        if (window.ethereum) {
            const p = new ethers.providers.Web3Provider(window.ethereum);
            const a = await p.listAccounts();
            if(a.length > 0) setupSigner(p, a[0]);
        }
    }

    function setupSigner(provider, account) {
        signer = provider.getSigner();
        document.getElementById('wallet-btn').innerHTML = `<div class="w-2 h-2 bg-green-500 rounded-full"></div> <span class="font-mono text-gray-700">${account.slice(0,6)}...</span>`;
        provider.getNetwork().then(n => {
            if(n.chainId !== BASE_CHAIN_ID) document.getElementById('network-switch-btn').classList.remove('hidden');
            else document.getElementById('network-switch-btn').classList.add('hidden');
        });
        if (window.ethereum) window.ethereum.on('chainChanged', c => { if (parseInt(c, 16) !== BASE_CHAIN_ID) document.getElementById('network-switch-btn').classList.remove('hidden'); });
    }

    async function ensureSigner() {
        if (!signer) { await connectWallet(); if(!signer) throw new Error("Wallet not connected."); }
        const n = await signer.provider.getNetwork();
        if (n.chainId !== BASE_CHAIN_ID) { await switchToBase(); signer = (new ethers.providers.Web3Provider(window.ethereum)).getSigner(); }
    }

    async function waitForSafeTransaction(safeTxHash) {
        if (!safeSdk) return null;
        return new Promise((resolve, reject) => {
            let attempts = 0;
            const checkStatus = async () => {
                try {
                    const txDetails = await safeSdk.txs.getBySafeTxHash(safeTxHash);
                    if (txDetails.txStatus === 'SUCCESS') resolve(txDetails);
                    else if (['FAILED','CANCELLED'].includes(txDetails.txStatus)) reject(new Error("Safe Transaction Failed"));
                    else if (attempts++ >= 600) reject(new Error("Timeout"));
                    else setTimeout(checkStatus, 3000);
                } catch (e) { setTimeout(checkStatus, 3000); }
            }; checkStatus(); 
        });
    }

    async function connectWallet() {
        if (!window.ethereum) return alert("MetaMask not found");
        await window.ethereum.request({ method: 'eth_requestAccounts' });
        const provider = new ethers.providers.Web3Provider(window.ethereum);
        const accounts = await provider.listAccounts();
        if(accounts.length > 0) setupSigner(provider, accounts[0]);
    }
    
    async function switchToBase() {
        await window.ethereum.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: '0x2105' }] });
    }

    // --- TX FUNCTIONS ---
    function showModal(icon, iconColor, title, message, txHash) {
        const modal = document.getElementById('success-modal');
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-message').textContent = message;
        document.getElementById('modal-icon-container').innerHTML = `<i data-lucide="${icon}" class="w-8 h-8 text-${iconColor}-600"></i>`;
        document.getElementById('modal-icon-container').className = `w-16 h-16 bg-${iconColor}-100 rounded-full flex items-center justify-center mx-auto mb-4`;
        const txLink = document.getElementById('modal-tx-link');
        if (txHash) { txLink.href = `https://basescan.org/tx/${txHash}`; txLink.textContent = `View Transaction: ${txHash.slice(0, 10)}...`; txLink.classList.remove('hidden'); }
        else { txLink.classList.add('hidden'); }
        modal.style.display = 'flex';
        if(window.lucide) lucide.createIcons();
    }

    async function payServiceFee(amt) {
        const btn = document.getElementById('pay-fee-btn'); btn.disabled = true; btn.innerText = "Processing...";
        try {
            await ensureSigner();
            const tx = await (new ethers.Contract(FEE_TOKEN_ADDR, ERC20_ABI, signer)).transfer(PLATFORM_WALLET, ethers.utils.parseUnits(amt, decimals));
            if (safeConnected && safeSdk) await waitForSafeTransaction(tx.hash); else await tx.wait();
            // FIXED: Adjusted path to '../backend/' for robust access
            await fetch('../backend/update_fee_status.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ sale_id: SALE_ID, tx_hash: tx.hash, amount: amt, currency: feeTokenSymbol, payer_address: await signer.getAddress() }) });
            HAS_PAID_FEE = true; showModal('check', 'green', 'Payment Successful', 'Fee paid. Proceeding.', tx.hash); setTimeout(() => window.location.reload(), 2000);
        } catch(e) { showModal('alert-triangle', 'red', 'Payment Failed', e.message, null); btn.disabled = false; btn.innerText = "Pay Platform Fee"; }
    }

    async function claimFunds() {
        const btn = document.getElementById('claim-btn'); btn.disabled = true; btn.innerText = "Processing...";
        try {
            await ensureSigner();
            const tx = await (new ethers.Contract(CONTRACT_ADDR, CAMPAIGN_ABI, signer)).finalizeProjectFunds();
            if (safeConnected && safeSdk) await waitForSafeTransaction(tx.hash); else await tx.wait();
            // FIXED: Adjusted path to '../backend/' for robust access
            await fetch('../backend/record_claim.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ sale_id: SALE_ID, tx_hash: tx.hash, final_amount: totalRaisedHuman }) });
            showModal('award', 'indigo', 'Funds Claimed', 'Fundraising successfully settled.', tx.hash);
        } catch(e) { showModal('alert-octagon', 'red', 'Claim Failed', e.message, null); btn.disabled = false; btn.innerText = "Claim Funds"; }
    }
</script>