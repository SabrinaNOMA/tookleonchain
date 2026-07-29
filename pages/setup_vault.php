<?php
/**
 * pages/setup_vault.php
 * Silicon Valley UX v13.2 - Absolute On-Chain Sync
 * * FIXED: Added Alchemy RPC check to force UI sync with blockchain state.
 * * LOGIC: JS now overrides PHP display if on-chain timestamp differs from DB.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// --- CONFIG & DB LOADING ---
$configPath = __DIR__ . '/../src/contract_config.php';
$config = file_exists($configPath) ? require_once $configPath : [];

if (file_exists(__DIR__ . '/../src/db.php')) require_once __DIR__ . '/../src/db.php';
elseif (file_exists(__DIR__ . '/../../src/db.php')) require_once __DIR__ . '/../../src/db.php';

$sale_id = $_GET['sale_id'] ?? $_SESSION['sv_active_sale_id'] ?? null;
if ($sale_id) {
    $_SESSION['sv_active_sale_id'] = $sale_id;
}

$saleDetails = null;
$error_message = null;
$isDeployed = false;
$safeAddress = null;
$stableTimestamp = null;

// Conditions Variables
$tokenPrice = 0;
$tgePercent = 0;
$cliffMonths = 0;
$vestingMonths = 0;

if (isset($_SESSION['user_id']) && $sale_id && isset($pdo)) {
    $stmt = $pdo->prepare("
        SELECT tsp.*, p.project_name, p.token_name,
        JSON_UNQUOTE(JSON_EXTRACT(tsp.sale_terms_json, '$.vault_custody_wallet')) as safe_wallet,
        JSON_UNQUOTE(JSON_EXTRACT(tsp.sale_terms_json, '$.vault_salt_timestamp')) as salt_ts
        FROM token_sale_pages tsp 
        JOIN projet p ON tsp.project_id = p.id 
        WHERE tsp.id = ? AND p.founder_id = ?
    ");
    $stmt->execute([$sale_id, $_SESSION['user_id']]);
    $saleDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($saleDetails) {
        $safeAddress = $saleDetails['safe_wallet'];
        $stableTimestamp = $saleDetails['salt_ts'];

        $terms = json_decode($saleDetails['sale_terms_json'] ?? '{}', true);
        $tokenPrice = (float)($terms['round_price'] ?? 0);
        $tgePercent = (float)($terms['percent_unlock_at_tge'] ?? 0);
        $cliffMonths = (int)($terms['cliff_months'] ?? 0);
        $vestingMonths = (int)($terms['vesting_months'] ?? 0);

        // 1. DETERMINE DEPLOYMENT STATUS FIRST
        $hasContract = !empty($saleDetails['contract_address']) && strlen($saleDetails['contract_address']) > 20;
        if ($hasContract || !in_array($saleDetails['status'], ['draft', 'scheduled'])) {
            $isDeployed = true;
        }

        // 2. STABLE SALT UPDATE LOGIC
        if (!$isDeployed) {
            if (!$stableTimestamp || $stableTimestamp === 'null' || $stableTimestamp < time()) {
                $stableTimestamp = time() + 90; 
                $upd = $pdo->prepare("UPDATE token_sale_pages SET sale_terms_json = JSON_SET(COALESCE(sale_terms_json, '{}'), '$.vault_salt_timestamp', ?) WHERE id = ?");
                $upd->execute([$stableTimestamp, $sale_id]);
            }
        }

        $softCap = (float)$saleDetails['soft_cap_usd'];
        $hardCap = (float)$saleDetails['hard_cap_usd'];
        $maxContrib = ($saleDetails['max_investment_usd'] > 0) ? (float)$saleDetails['max_investment_usd'] : $hardCap;
        $duration = (int)$saleDetails['duration_seconds'];
        
        $displayDuration = ($duration >= 86400) ? (floor($duration / 86400) . ' days') : ($duration . 's');
    }
}

$isEditingSafe = isset($_GET['edit_safe']) && $_GET['edit_safe'] == '1';
$needsSafeConfig = (!$isDeployed && (!$safeAddress || strlen($safeAddress) < 40 || $isEditingSafe));

// Fetch existing project wallets for the dropdown
$projectWallets = [];
if ($saleDetails && $needsSafeConfig) {
    $walletsStmt = $pdo->prepare("SELECT label, wallet_address FROM project_wallet WHERE projet_id = ? ORDER BY label ASC");
    $walletsStmt->execute([$saleDetails['project_id']]);
    $projectWallets = $walletsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// API Endpoint to refresh timestamp if expired
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'refresh_timestamp') {
    header('Content-Type: application/json');
    if ($isDeployed) {
        echo json_encode(['success' => false, 'message' => 'Cannot refresh timestamp on deployed vault']);
        exit;
    }
    $newTs = time() + 900;
    $upd = $pdo->prepare("UPDATE token_sale_pages SET sale_terms_json = JSON_SET(COALESCE(sale_terms_json, '{}'), '$.vault_salt_timestamp', ?) WHERE id = ?");
    $upd->execute([$newTs, $sale_id]);
    echo json_encode(['success' => true, 'new_ts' => $newTs]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_safe') {
    $newSafe = trim($_POST['safe_address']);
    if (preg_match('/^0x[a-fA-F0-9]{40}$/', $newSafe)) {
        // 1. Update token_sale_pages
        $upd = $pdo->prepare("UPDATE token_sale_pages SET sale_terms_json = JSON_SET(COALESCE(sale_terms_json, '{}'), '$.vault_custody_wallet', ?) WHERE id = ?");
        $upd->execute([$newSafe, $sale_id]);
        
        // 2. Auto-save to project_wallet (Phase 2)
        $saleName = $saleDetails['sale_name'] ?? 'Sale';
        $label = $saleName . ' Beneficiary';
        $checkStmt = $pdo->prepare("SELECT id FROM project_wallet WHERE projet_id = ? AND LOWER(wallet_address) = LOWER(?)");
        $checkStmt->execute([$saleDetails['project_id'], $newSafe]);
        if (!$checkStmt->fetch()) {
            $insStmt = $pdo->prepare("INSERT INTO project_wallet (projet_id, label, wallet_address, network) VALUES (?, ?, ?, 'base')");
            $insStmt->execute([$saleDetails['project_id'], $label, $newSafe]);
        }

        header("Location: ?sale_id=" . $sale_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Vault | Tookle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/5.7.2/ethers.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script type="module">
        import { createWeb3Modal, defaultConfig } from 'https://esm.sh/@web3modal/ethers5@5.0.1'
        
        window.initWeb3 = (projectId) => {
            const base = { chainId: 8453, name: 'Base', currency: 'ETH', explorerUrl: 'https://basescan.org', rpcUrl: 'https://mainnet.base.org' };
            const metadata = { name: 'Tookle Protocol', description: 'Smart Vault Deployment', url: 'https://tookle.com', icons: ['https://tookle.com/icon.png'] };
            
            window.modal = createWeb3Modal({ 
                ethersConfig: defaultConfig({ metadata }), 
                chains: [base], 
                projectId,
                enableAnalytics: true 
            });
            
            window.modal.subscribeProvider(handleProviderChange);
        }

        async function handleProviderChange({ isConnected }) {
            if (isConnected) {
                const provider = new ethers.providers.Web3Provider(window.modal.getWalletProvider());
                window.provider = provider;
                window.signer = provider.getSigner();
                const addr = await window.signer.getAddress();
                
                const cBtn = document.getElementById('connectWalletBtn');
                const btnText = document.getElementById('btnText');
                
                if(cBtn) {
                    cBtn.classList.remove('bg-black', 'text-white');
                    cBtn.classList.add('bg-zinc-50', 'text-black', 'border-zinc-200', 'border');
                    btnText.innerHTML = `<span class='font-mono font-bold'>${addr.slice(0,6)}...${addr.slice(-4)}</span>`;
                }
                
                if(window.predictAddress) window.predictAddress(); 
            }
        }
    </script>
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f9fafb; color: #111827; letter-spacing: -0.01em; }
        .step-line { border-left: 1px solid #e5e7eb; position: absolute; top: 40px; bottom: 0; left: 19px; z-index: 0; }
        .btn-tookle { background-color: #111827; color: white; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-weight: 700; }
        .btn-tookle:hover { background-color: #000; transform: translateY(-1px); }
        .btn-tookle:disabled { background-color: #9ca3af; cursor: not-allowed; }
        .section-card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .token-btn { border: 1px solid #e5e7eb; transition: all 0.2s; font-weight: 700; color: #6b7280; border-radius: 12px; }
        .token-btn:hover { border-color: #111827; background-color: #fafafa; color: #111827; }
        .token-btn.active { border-color: #111827; background-color: #111827; color: white; }
        .policy-box { border: 1px solid #e5e7eb; background: #fdfdfd; border-radius: 12px; }
        
        .engine-container { background: #27272a; border: 1px solid #3f3f46; border-radius: 20px; color: #f4f4f5; }
        .engine-sub-box { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; color: #111827; }
    </style>
</head>
<body class="selection:bg-black selection:text-white p-4 md:p-8">

<!-- SUCCESS MODAL -->
<div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
    <div class="bg-white rounded-2xl p-10 max-w-md w-full text-center shadow-2xl animate-in fade-in zoom-in duration-300">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 text-green-600">
            <i data-lucide="check" class="w-8 h-8"></i>
        </div>
        <h2 class="text-xl font-bold mb-3 tracking-tight text-gray-900 uppercase">Vault Synchronized</h2>
        <p class="text-gray-500 text-sm mb-6 leading-relaxed">Your non-custodial smart vault is operational on the Base Network and linked to your project.</p>
        
        <!-- WARMUP NOTICE -->
        <div class="mt-4 mb-8 p-4 bg-blue-50 border border-blue-100 rounded-xl text-left flex gap-3">
            <div class="bg-blue-100 p-2 rounded-lg h-fit shrink-0">
                <i data-lucide="clock" class="w-4 h-4 text-blue-600"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-blue-900 uppercase tracking-wide mb-1">15-Minute Warmup</p>
                <p class="text-[10px] text-blue-700 leading-relaxed">The vault will remain in "Initializing" state for 15 minutes to ensure global timestamp stability before accepting deposits.</p>
            </div>
        </div>

        <a href="<?= get_url('dashboard') ?>" class="block w-full bg-black text-white py-4 rounded-xl font-bold hover:bg-zinc-800 transition-colors text-xs uppercase tracking-widest text-center">Return to Dashboard</a>
    </div>
</div>

<div class="max-w-5xl mx-auto">
    <!-- HEADER -->
    <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-4 border-b border-gray-100 pb-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 mb-1 uppercase">
                Create Vault
            </h1>
            <p class="text-gray-500 font-medium text-sm">Securely deploy your non-custodial vault logic on the Base mainnet.</p>
        </div>
        <div class="hidden md:flex flex-col items-end">
            <div class="flex items-center gap-3 font-bold text-[10px] bg-white px-4 py-2 rounded-full border border-gray-200 shadow-sm uppercase tracking-widest text-gray-400">
                <svg class="w-3.5 h-3.5" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 32C24.8366 32 32 24.8366 32 16C32 7.16344 24.8366 0 16 0C7.16344 0 0 7.16344 0 16C0 24.8366 7.16344 32 16 32Z" fill="#0052FF"/>
                    <path d="M16 24.5C20.6944 24.5 24.5 20.6944 24.5 16C24.5 11.3056 20.6944 7.5 16 7.5C11.3056 7.5 7.5 11.3056 7.5 16C7.5 20.6944 11.3056 24.5 16 24.5Z" fill="white"/>
                </svg>
                Base Mainnet
            </div>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="bg-red-50 border border-red-100 p-5 rounded-xl mb-10 flex items-center gap-3 shadow-sm">
            <i data-lucide="alert-circle" class="text-red-600 w-5 h-5"></i>
            <p class="text-red-700 font-bold text-sm"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($isDeployed): ?>
        <?php 
            // DEFAULT PHP STATE (Overridden by JS)
            $warmupRemaining = $stableTimestamp - time();
            $isWarmingUp = $warmupRemaining > 0;
        ?>
        <div id="vault-status-card" class="section-card p-12 text-center max-w-2xl mx-auto mt-10">
            <!-- Content populated by JS or PHP default -->
            <!-- Icon -->
            <div class="w-16 h-16 <?php echo $isWarmingUp ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-green-50 text-green-600 border-green-100'; ?> rounded-full flex items-center justify-center mx-auto mb-8 border">
                <?php if($isWarmingUp): ?>
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin"></i>
                <?php else: ?>
                    <i data-lucide="shield-check" class="w-8 h-8"></i>
                <?php endif; ?>
            </div>

            <!-- Title -->
            <h2 class="text-2xl font-bold mb-3 tracking-tight text-gray-900 uppercase">
                <?php echo $isWarmingUp ? 'Initializing Vault...' : 'Vault Operational'; ?>
            </h2>

            <!-- Message or Timer -->
            <?php if($isWarmingUp): ?>
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 mb-8 max-w-md mx-auto text-left relative overflow-hidden">
                    <div class="flex items-start gap-4 z-10 relative">
                        <div class="bg-blue-100 p-2 rounded-lg shrink-0">
                            <i data-lucide="hourglass" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-blue-900 font-bold text-sm mb-1 uppercase tracking-wide">Timestamp Synchronization</p>
                            <p class="text-blue-700 text-xs mb-3 leading-relaxed">
                                The decentralized network requires a 15-minute buffer to sync the start time globally. Deposits will unlock automatically when the timer ends.
                            </p>
                            <p class="text-2xl font-mono font-bold text-blue-900" id="warmup-timer">
                                <?php echo gmdate("i:s", $warmupRemaining); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-500 font-medium mb-8">The contract is verified and active on the blockchain.</p>
            <?php endif; ?>

            <div class="bg-gray-50 p-6 rounded-xl border border-gray-100 mb-8 text-left">
                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest mb-2">Verified Contract Address</p>
                <code class="font-mono text-base text-black break-all font-bold"><?php echo htmlspecialchars($saleDetails['contract_address']); ?></code>
            </div>
            
            <a href="<?= get_url('dashboard') ?>" class="btn-tookle px-10 py-4 rounded-xl font-bold inline-block uppercase text-xs tracking-widest">Return to Dashboard</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <div class="lg:col-span-7 space-y-10">
                
                <!-- STEP 1: DESTINATION -->
                <div class="relative pl-14">
                    <div class="step-line"></div>
                    <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-gray-900 text-white flex items-center justify-center font-bold z-10 border-2 border-white shadow-sm">1</div>
                    <div class="section-card p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-bold text-lg tracking-tight text-gray-900 uppercase">Beneficiary</h3>
                                <p class="text-xs text-gray-500 font-medium">The wallet that will receive funds after a successful campaign.</p>
                            </div>
                            <?php if(!$needsSafeConfig): ?>
                                <div class="text-green-600 bg-green-50 px-3 py-1 rounded-full text-[10px] font-bold border border-green-100 uppercase tracking-widest">Linked</div>
                            <?php endif; ?>
                        </div>

                        <div class="policy-box p-5 mb-8 flex gap-4">
                            <div class="bg-gray-100 p-2 rounded-lg shrink-0 h-fit"><i data-lucide="shield" class="w-4 h-4 text-gray-900"></i></div>
                            <div class="text-xs text-gray-600 leading-relaxed font-medium">
                                <span class="font-bold text-gray-900 block mb-1 uppercase text-[10px]">Security Recommendation</span>
                                We recommend a <a href="https://safe.global/" target="_blank" class="font-bold underline text-gray-900">Safe Multisig</a>. This ensures decentralized management of collected funds.
                            </div>
                        </div>
                        
                        <?php if($needsSafeConfig): ?>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="set_safe">
                                <div class="relative flex flex-col gap-2">
                                    <select id="wallet_selector" onchange="handleWalletSelect()" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-1 focus:ring-black outline-none transition cursor-pointer">
                                        <option value="">Select a wallet from your address book...</option>
                                        <?php foreach ($projectWallets as $pw): ?>
                                            <option value="<?php echo htmlspecialchars($pw['wallet_address']); ?>">
                                                <?php echo htmlspecialchars($pw['label']); ?> (<?php echo substr($pw['wallet_address'], 0, 6) . '...' . substr($pw['wallet_address'], -4); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="MANUAL">Enter a new address manually...</option>
                                    </select>
                                    
                                    <input type="text" id="safe_address" name="safe_address" placeholder="0x..." class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl font-mono text-sm focus:ring-1 focus:ring-black outline-none transition hidden" required pattern="^0x[a-fA-F0-9]{40}$">
                                </div>
                                <button type="submit" class="btn-tookle px-8 py-3 rounded-lg font-bold text-xs uppercase tracking-widest w-full md:w-auto">Confirm Beneficiary</button>
                            </form>
                        <?php else: ?>
                            <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 flex items-center justify-between group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center border border-zinc-100 shadow-sm text-green-600"><i data-lucide="link" class="w-4 h-4"></i></div>
                                    <p class="text-xs text-gray-900 font-bold uppercase tracking-tight">Beneficiary Linked Securely</p>
                                </div>
                                <a href="?sale_id=<?php echo $sale_id; ?>&edit_safe=1" class="opacity-0 group-hover:opacity-100 p-2 bg-white shadow-sm border border-gray-100 rounded-lg text-gray-400 hover:text-red-500 transition-all">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- STEP 2: CONFIGURATION -->
                <div class="relative pl-14">
                    <div class="step-line"></div>
                    <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-white border-2 border-gray-200 text-gray-300 flex items-center justify-center font-bold z-10 shadow-sm">2</div>
                    <div class="section-card p-8">
                        <div class="mb-6 flex justify-between items-end border-b border-gray-50 pb-4">
                            <div>
                                <h3 class="font-bold text-lg tracking-tight text-gray-900 uppercase">Sale Conditions</h3>
                                <p class="text-xs text-gray-500 font-medium">Locked parameters fetched from project metadata.</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] text-gray-400 uppercase font-black tracking-widest mb-1"><?php echo htmlspecialchars($saleDetails['project_name'] ?? ''); ?></p>
                                <p class="text-xs font-bold text-gray-900"><?php echo htmlspecialchars($saleDetails['sale_name'] ?? ''); ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-8">
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <p class="text-[8px] text-gray-400 uppercase font-bold mb-1">Soft Cap</p>
                                <p class="font-bold text-sm text-gray-900">$<?php echo number_format($softCap); ?></p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <p class="text-[8px] text-gray-400 uppercase font-bold mb-1">Price</p>
                                <p class="font-bold text-sm text-gray-900">$<?php echo number_format($tokenPrice, 4); ?></p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <p class="text-[8px] text-gray-400 uppercase font-bold mb-1">Duration</p>
                                <p class="font-bold text-sm text-gray-900"><?php echo $displayDuration; ?></p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <p class="text-[8px] text-gray-400 uppercase font-bold mb-1">TGE</p>
                                <p class="font-bold text-sm text-gray-900"><?php echo $tgePercent; ?>%</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <p class="text-[8px] text-gray-400 uppercase font-bold mb-1">Vesting</p>
                                <p class="font-bold text-[9px] text-gray-900"><?php echo $cliffMonths; ?>m / <?php echo $vestingMonths; ?>m</p>
                            </div>
                        </div>

                        <label class="block text-[10px] font-bold uppercase text-gray-400 mb-3 tracking-widest">Contribution Currency</label>
                        <div class="flex flex-col md:flex-row gap-3">
                            <button type="button" id="usdcBtn" onclick="setToken('USDC')" class="token-btn flex-1 p-4 rounded-xl flex items-center justify-between text-sm shadow-sm">
                                <span>USDC <span class="text-[10px] opacity-50 ml-1">Base</span></span>
                                <img src="https://cryptologos.cc/logos/usd-coin-usdc-logo.svg" class="w-4 h-4" alt="USDC">
                            </button>
                            <button type="button" id="usdtBtn" onclick="setToken('USDT')" class="token-btn flex-1 p-4 rounded-xl flex items-center justify-between text-sm shadow-sm">
                                <span>USDT <span class="text-[10px] opacity-50 ml-1">Base</span></span>
                                <img src="https://cryptologos.cc/logos/tether-usdt-logo.svg" class="w-4 h-4" alt="USDT">
                            </button>
                        </div>
                        <select id="tokenSelect" onchange="predictAddress()" class="hidden">
                            <option value="">Select Token...</option>
                            <option value="0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913">USDC</option>
                            <option value="0xfde4C96c8593536E31F229EA8f376eDd11561Fcc">USDT</option>
                        </select>
                    </div>
                </div>

                <!-- STEP 3: WALLET -->
                <div class="relative pl-14">
                    <div class="absolute left-0 top-0 w-10 h-10 rounded-full bg-white border-2 border-gray-200 text-gray-300 flex items-center justify-center font-bold z-10 shadow-sm">3</div>
                    <div class="section-card p-8">
                        <div class="mb-6 text-sm">
                            <h3 class="font-bold text-lg tracking-tight text-gray-900 uppercase">Establish Connection</h3>
                            <p class="text-gray-500 font-medium leading-relaxed mt-2">
                                Connect a wallet to pay deployment gas fees. This vault is <b>non-custodial</b>: funds are programmatically held in escrow and settled to the beneficiary automatically <b>only after a successful campaign</b>.
                            </p>
                        </div>
                        <button id="connectWalletBtn" onclick="window.modal?.open()" class="btn-tookle w-full p-4 rounded-xl font-bold flex items-center justify-center gap-3 shadow-md uppercase text-xs tracking-widest">
                            <i data-lucide="wallet" class="w-5 h-5"></i> <span id="btnText">Connect Wallet</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT: SUMMARY (Engine box) -->
            <div class="lg:col-span-5">
                <div class="sticky top-8 space-y-6">
                    <div class="engine-container p-8 relative overflow-hidden shadow-xl border-none">
                        <h3 class="text-base font-bold mb-8 flex items-center gap-2 uppercase tracking-tighter text-zinc-100">
                            <i data-lucide="terminal" class="w-4 h-4 opacity-40"></i> Local Engine
                        </h3>

                        <div class="space-y-6 mb-10">
                            <div class="px-1 space-y-4">
                                <div class="flex items-center gap-3 text-xs font-bold text-zinc-400 uppercase tracking-tight"><i data-lucide="check" class="w-4 h-4 text-green-500"></i> non custodial</div>
                                <div class="flex items-center gap-3 text-xs font-bold text-zinc-400 uppercase tracking-tight">
                                    <i data-lucide="clock" class="w-4 h-4 text-zinc-500"></i> 
                                    Duration: <span class="text-white ml-1"><?php echo $displayDuration; ?></span>
                                </div>
                                <div class="flex items-center gap-3 text-xs font-bold text-zinc-400 uppercase tracking-tight"><i data-lucide="check" class="w-4 h-4 text-green-500"></i> immutable</div>
                            </div>

                            <div id="ts-warning" class="hidden p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg text-[10px] text-amber-500 font-medium">
                                <i data-lucide="clock" class="w-3 h-3 inline mr-1"></i> Safe parameters expired. Refreshing...
                            </div>
                        </div>

                        <!-- Main Create Button -->
                        <button id="deployBtn" disabled onclick="handleDeployClick()" class="w-full bg-white text-black py-6 rounded-xl font-bold shadow-lg flex items-center justify-center gap-3 hover:bg-zinc-100 transition-colors disabled:opacity-20 uppercase text-xs tracking-[0.2em]">
                            <span id="deployBtnText">Create Vault</span>
                            <i data-lucide="zap" class="w-4 h-4"></i>
                        </button>

                        <div id="statusArea" class="hidden mt-8 text-center">
                            <div class="w-8 h-8 border-2 border-white border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                            <p id="statusText" class="text-[10px] font-bold uppercase tracking-widest text-zinc-100">Establishing Node...</p>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 bg-white rounded-xl border border-gray-100 shadow-sm space-y-2">
                        <p class="text-[10px] font-bold text-gray-900 uppercase tracking-widest mb-1 flex items-center gap-2">
                            <i data-lucide="info" class="w-3 h-3 text-gray-400"></i> Protocol Note
                        </p>
                        <p class="text-[10px] text-gray-500 font-medium leading-relaxed">
                            Vault creation deploys a standalone campaign instance on Base.
                        </p>
                        <!-- Added UI Note -->
                        <div class="p-2 bg-blue-50 rounded border border-blue-100 flex gap-2 items-start mt-2">
                            <i data-lucide="clock" class="w-3 h-3 text-blue-500 mt-0.5 shrink-0"></i>
                            <p class="text-[9px] text-blue-600 font-medium leading-relaxed">
                                <strong>Initialization:</strong> After creation, the vault will enter a 15-minute initialization period before the shareable link becomes active for deposits.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- DATA HIDDEN -->
<input type="hidden" id="factoryAddr" value="<?php echo $config['FACTORY_ADDRESS']; ?>">
<input type="hidden" id="targetSafe" value="<?php echo $safeAddress; ?>">
<input type="hidden" id="saleId" value="<?php echo $sale_id; ?>">
<input type="hidden" id="goalAmount" value="<?php echo $softCap; ?>">
<input type="hidden" id="duration" value="<?php echo $duration; ?>">
<input type="hidden" id="maxContrib" value="<?php echo $maxContrib; ?>">
<input type="hidden" id="saltTs" value="<?php echo $stableTimestamp; ?>">
<input type="hidden" id="deployedContractAddr" value="<?php echo htmlspecialchars($saleDetails['contract_address'] ?? ''); ?>">

<script>
    window.onload = () => { 
        window.initWeb3('1f8a615ee9a5898de0f8d72fb19c2559'); 
        if(typeof lucide !== 'undefined') lucide.createIcons(); 
        if(typeof syncVaultStatus === 'function') syncVaultStatus();
    };

    const FACTORY_ADDR = document.getElementById('factoryAddr').value;
    const TARGET_SAFE = document.getElementById('targetSafe').value;
    const GOAL = document.getElementById('goalAmount').value;
    const DURATION = parseInt(document.getElementById('duration').value) || 180; // FORCE PARSE
    const MAX_CONTRIB = document.getElementById('maxContrib').value;
    const SALE_ID = document.getElementById('saleId').value;
    let SALT_TS = parseInt(document.getElementById('saltTs').value);
    const FACTORY_ABI = <?php echo $config['FACTORY_ABI_JSON']; ?>;
    const ERC20_ABI = ["function decimals() view returns (uint8)"];
    const DEPLOYED_ADDR = document.getElementById('deployedContractAddr')?.value;

    const TOKENS = {
        'USDC': '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        'USDT': '0xfde4C96c8593536E31F229EA8f376eDd11561Fcc'
    };

    // ALCHEMY / RPC SYNC FOR DEPLOYED VAULTS
    const RPC_LIST = [
        "https://base-mainnet.g.alchemy.com/v2/FW0D58_KXM7iLCmKNUeVU", 
        "https://base.publicnode.com",                                   
        "https://mainnet.base.org",                                      
        "https://1rpc.io/base"                                           
    ];
    const STATUS_ABI = ["function startTimestamp() view returns (uint256)"];

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

    async function syncVaultStatus() {
        if (!DEPLOYED_ADDR || DEPLOYED_ADDR.length < 20) return;
        
        try {
            const provider = await getWorkingProvider();
            const contract = new ethers.Contract(DEPLOYED_ADDR, STATUS_ABI, provider);
            const startBN = await contract.startTimestamp();
            const start = startBN.toNumber();
            const now = Math.floor(Date.now() / 1000);
            const diff = start - now;

            const container = document.getElementById('vault-status-card');
            if(!container) return;

            // OVERRIDE PHP DISPLAY IF ON-CHAIN STATUS DIFFERS
            // If Diff > 0 it means warmup. If PHP showed live, we fix it.
            if (diff > 0) {
                renderWarmupUI(container, diff);
            } else {
                renderLiveUI(container);
            }
        } catch (e) {
            console.error("Status sync failed", e);
        }
    }

    function renderWarmupUI(container, initialDiff) {
        container.innerHTML = `
            <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-8 border border-blue-100">
                <i data-lucide="loader-2" class="w-8 h-8 animate-spin"></i>
            </div>
            <h2 class="text-2xl font-bold mb-3 tracking-tight text-gray-900 uppercase">Initializing Vault...</h2>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 mb-8 max-w-md mx-auto text-left relative overflow-hidden">
                <div class="flex items-start gap-4 z-10 relative">
                    <div class="bg-blue-100 p-2 rounded-lg shrink-0"><i data-lucide="hourglass" class="w-5 h-5 text-blue-600"></i></div>
                    <div>
                        <p class="text-blue-900 font-bold text-sm mb-1 uppercase tracking-wide">Timestamp Synchronization</p>
                        <p class="text-blue-700 text-xs mb-3 leading-relaxed">The decentralized network requires a 15-minute buffer to sync the start time globally. Deposits will unlock automatically when the timer ends.</p>
                        <p class="text-2xl font-mono font-bold text-blue-900" id="js-warmup-timer">--:--</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 p-6 rounded-xl border border-gray-100 mb-8 text-left">
                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest mb-2">Verified Contract Address</p>
                <code class="font-mono text-base text-black break-all font-bold">${DEPLOYED_ADDR}</code>
            </div>
            <a href="<?= get_url('dashboard') ?>" class="btn-tookle px-10 py-4 rounded-xl font-bold inline-block uppercase text-xs tracking-widest">Return to Dashboard</a>
        `;
        lucide.createIcons();
        
        let timeLeft = initialDiff;
        const timerEl = document.getElementById('js-warmup-timer');
        const timer = setInterval(() => {
            timeLeft--;
            if(timeLeft <= 0) {
                clearInterval(timer);
                renderLiveUI(container); // Auto-switch to live
            }
            const m = Math.floor(timeLeft / 60);
            const s = timeLeft % 60;
            if(timerEl) timerEl.innerText = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
        }, 1000);
    }

    function renderLiveUI(container) {
        container.innerHTML = `
            <div class="w-16 h-16 bg-green-50 text-green-600 rounded-full flex items-center justify-center mx-auto mb-8 border border-green-100">
                <i data-lucide="shield-check" class="w-8 h-8"></i>
            </div>
            <h2 class="text-2xl font-bold mb-3 tracking-tight text-gray-900 uppercase">Vault Operational</h2>
            <p class="text-gray-500 font-medium mb-8">The contract is verified and active on the blockchain.</p>
            <div class="bg-gray-50 p-6 rounded-xl border border-gray-100 mb-8 text-left">
                <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest mb-2">Verified Contract Address</p>
                <code class="font-mono text-base text-black break-all font-bold">${DEPLOYED_ADDR}</code>
            </div>
            <a href="<?= get_url('dashboard') ?>" class="btn-tookle px-10 py-4 rounded-xl font-bold inline-block uppercase text-xs tracking-widest">Return to Dashboard</a>
        `;
        lucide.createIcons();
    }

    let currentPredictedAddress = "";
    let deploymentComplete = false;

    window.setToken = (type) => {
        const addr = TOKENS[type];
        const sel = document.getElementById('tokenSelect');
        sel.value = addr;
        document.querySelectorAll('.token-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(type.toLowerCase() + 'Btn').classList.add('active');
        predictAddress();
    }

    window.predictAddress = async () => {
        const token = document.getElementById('tokenSelect').value;
        if(!token || !window.signer) return;
        try {
            const userAddr = await window.signer.getAddress();
            const factory = new ethers.Contract(FACTORY_ADDR, FACTORY_ABI, window.provider);
            const tc = new ethers.Contract(token, ERC20_ABI, window.provider);
            const decimals = await tc.decimals();
            
            const goalWei = ethers.utils.parseUnits(String(GOAL), decimals);
            const maxWei = ethers.utils.parseUnits(String(MAX_CONTRIB), decimals);
            
            currentPredictedAddress = await factory.predictCampaignAddress(
                token, TARGET_SAFE, goalWei, SALT_TS, DURATION, maxWei, SALE_ID, userAddr
            );

            const code = await window.provider.getCode(currentPredictedAddress);
            if (code && code !== '0x') {
                await finalizeDeployment("AUTO_CHAIN_DISCOVERY");
            } else {
                document.getElementById('deployBtn').disabled = false;
                document.getElementById('deployBtnText').innerText = "Create Vault";
            }
        } catch(e) { console.error("Discovery error:", e); }
    }

    async function ensureTimestampValid() {
        const now = Math.floor(Date.now() / 1000);
        // If our SALT_TS is in the past (even by 1 second), we must refresh.
        // We added a 15-minute buffer in PHP, so if JS thinks it's past, either
        // the buffer ran out or clocks are desynced. In either case, REFRESH.
        if (SALT_TS <= now) {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=refresh_timestamp&sale_id=${SALE_ID}`
            });
            const data = await res.json();
            if (data.success) {
                SALT_TS = data.new_ts;
                // Force update the hidden input so predictAddress picks it up
                document.getElementById('saltTs').value = SALT_TS;
                await predictAddress();
                return true; // Refreshed
            }
        }
        return false; // Valid
    }

    async function handleDeployClick() {
        if(!window.signer || !currentPredictedAddress) return;
        const status = document.getElementById('statusArea');
        const btn = document.getElementById('deployBtn');
        
        status.classList.remove('hidden');
        btn.disabled = true;

        const code = await window.provider.getCode(currentPredictedAddress);
        if (code && code !== '0x') {
            await finalizeDeployment("AUTO_CHAIN_SYNC");
            return;
        }

        // CRITICAL: Ensure timestamp is valid right before sending TX
        await ensureTimestampValid();
        
        startDeepPoller(currentPredictedAddress);

        try {
            const token = document.getElementById('tokenSelect').value;
            const tc = new ethers.Contract(token, ERC20_ABI, window.provider);
            const decimals = await tc.decimals();
            const factory = new ethers.Contract(FACTORY_ADDR, FACTORY_ABI, window.signer);
            
            // Re-predict to be safe if timestamp changed
            if (await ensureTimestampValid()) {
               await predictAddress();
            }

            // DEBUG: Log parameters
            const goalWei = ethers.utils.parseUnits(String(GOAL), decimals);
            const maxWei = ethers.utils.parseUnits(String(MAX_CONTRIB), decimals);
            const userAddr = await window.signer.getAddress();
            
            console.log("Deploying with:", {
                token, TARGET_SAFE, goalWei: goalWei.toString(), SALT_TS, 
                DURATION, maxWei: maxWei.toString(), SALE_ID, deployer: userAddr
            });

            // FIXED: Added manual gas limit to prevent estimation errors causing "nothing to happen"
            const overrides = {
                gasLimit: 3000000 
            };

            factory.createDeterministicCampaign(
                token, 
                TARGET_SAFE, 
                goalWei, 
                SALT_TS, 
                DURATION, 
                maxWei, 
                String(SALE_ID), 
                userAddr,
                overrides
            ).then(async (tx) => {
                console.log("Tx sent:", tx.hash);
                await tx.wait();
                finalizeDeployment(tx.hash);
            }).catch(e => {
                console.error("Deployment Error:", e);
                // Extract useful error message
                let msg = e.reason || e.message || "Unknown error";
                if(msg.includes("user rejected")) msg = "User rejected transaction";
                else if(msg.includes("insufficient funds")) msg = "Insufficient ETH for gas";
                
                alert("Transaction Failed: " + msg);
                
                if(!deploymentComplete) { btn.disabled = false; status.classList.add('hidden'); }
            });
            
            setTimeout(() => { if(!deploymentComplete) btn.disabled = false; }, 60000);

        } catch(e) { 
            console.error("Pre-flight Error:", e);
            alert("Error preparing transaction: " + e.message);
            btn.disabled = false; 
            status.classList.add('hidden'); 
        }
    }

    function startDeepPoller(address) {
        const interval = setInterval(async () => {
            if (deploymentComplete) { clearInterval(interval); return; }
            try {
                const code = await window.provider.getCode(address);
                if (code && code !== '0x') {
                    deploymentComplete = true;
                    clearInterval(interval);
                    finalizeDeployment("POLL_SYNC");
                }
            } catch(e) { }
        }, 4000);
    }

    async function finalizeDeployment(txHash) {
        deploymentComplete = true;
        const token = document.getElementById('tokenSelect').value;
        const res = await fetch('/backend/save_vault.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ sale_id: SALE_ID, manual_address: currentPredictedAddress, payment_token: token, tx_hash: txHash })
        });
        const data = await res.json();
        
        if(data.success || data.error?.includes('already live')) {
            document.getElementById('successModal').classList.remove('hidden');
            if(typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            console.error("Sync error:", data.error);
            document.getElementById('statusArea').classList.add('hidden');
            document.getElementById('deployBtn').disabled = false;
            deploymentComplete = false;
        }
    }
</script>
<script>
function handleWalletSelect() {
    const selector = document.getElementById('wallet_selector');
    const manualInput = document.getElementById('safe_address');
    
    if (selector.value === 'MANUAL') {
        manualInput.classList.remove('hidden');
        manualInput.value = ''; // Clear it so they can type
        manualInput.focus();
    } else if (selector.value !== '') {
        manualInput.classList.add('hidden');
        manualInput.value = selector.value;
    } else {
        manualInput.classList.add('hidden');
        manualInput.value = '';
    }
}
</script>
</body>
</html>