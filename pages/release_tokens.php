<?php
/**
 * Page: Vesting (Token Release)
 * Filepath: /pages/release_tokens.php
 *
 * ---
 * Silicon Valley Engineer's Review:
 * - REFACTORED: Removed all direct database fetching logic from this file. This page is now a pure frontend template.
 * - SIMPLIFIED: The page relies on a single backend API endpoint (`release_tokens_backend.php`) to fetch all data.
 * - ENHANCED: The JavaScript now drives the entire page's state, fetching data on load and dynamically building the UI.
 * - NEW: Added batch creation functionality. Users can now select multiple schedules (with checkboxes) and create
 * them in a single transaction using the Sablier contract's `batch` function, saving significant gas fees.
 * - UI/UX UPDATE: Swapped button hierarchy per user feedback. Primary actions ("Create" in table) are now solid purple,
 * while the main "Batch Create" button uses a larger, cleaner outline style to be less prominent.
 * - FORMATTING: Added a robust `formatTokens` helper to remove all decimals and add comma-separated thousands for a cleaner UI.
 * ---
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$project_id = $_SESSION['active_project_id'] ?? null;
$project_name = $_SESSION['active_project_name'] ?? 'N/A'; // Assuming project name is stored in session
?>
<main class="flex-1 p-10 overflow-y-auto">
    <header class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 font-montserrat tracking-tight">Configure Vesting Schedules</h1>
            <p class="text-purple-600 font-semibold">Project: <?php echo htmlspecialchars($project_name); ?></p>
        </div>
        <div id="wallet-info-container" class="flex-shrink-0">
            <!-- Wallet info will be populated here by script after an action requires connection -->
        </div>
    </header>
    
    <a href="/distribute" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-purple-700 font-medium mb-6 transition-all">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Actions
    </a>

    <!-- Explanation Section -->
    <div class="mb-8 border border-gray-200 bg-white rounded-xl shadow-sm transition-all hover:shadow-md overflow-hidden">
        <details class="group">
            <summary class="flex items-center justify-between p-5 cursor-pointer list-none bg-white hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center mr-3">
                        <i data-lucide="info" class="w-4 h-4 text-purple-600"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-800">
                        Vesting Protocol Intelligence
                    </h2>
                </div>
                <div class="text-gray-400 transition-transform duration-300 transform group-open:rotate-180">
                    <i data-lucide="chevron-down" class="w-5 h-5"></i>
                </div>
            </summary>
            <div class="px-5 pb-5 pt-0 text-sm text-gray-600 space-y-4 border-t border-gray-50 mt-2 pt-4">
                 <p><strong>Secure Streaming:</strong> We utilize the <a href="https://sablier.com/" target="_blank" class="text-purple-600 hover:underline font-medium">Sablier V2 Protocol</a>. Tokens are locked in a secure smart contract and released linearly to your investors' wallets.</p>
                 <p><strong>Gas Efficiency:</strong> By selecting multiple recipients, you can utilize <code>batch()</code> execution, consolidating up to 50 recipients into a single gas-efficient transaction.</p>
                 <p><strong>Clawback Policy:</strong> All streams are created as <em>Cancellable</em>. This grants the project owner the right to terminate future vesting if an agreement is breached, while protecting tokens already earned by the recipient.</p>
            </div>
        </details>
    </div>

    <div id="page-loading-state" class="text-center p-20">
        <div class="loader-ring mx-auto"></div>
        <p class="mt-6 text-gray-500 font-medium">Synchronizing with blockchain data...</p>
    </div>

    <div id="page-error-state" class="content-panel text-center max-w-lg mx-auto py-12" style="display: none;">
        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-circle" class="w-8 h-8 text-red-500"></i>
        </div>
        <h2 class="text-xl font-bold text-gray-900">Initialization Failed</h2>
        <p id="page-error-message" class="mt-2 text-gray-600 px-4"></p>
        <a href="/distribute" class="btn-primary inline-block mt-8">Return to Dashboard</a>
    </div>

    <div id="distribution-content" class="distribution-hub" style="display: none;">
        <header class="hub-header bg-gray-50/50">
            <div class="flex justify-between items-start">
                <h1 class="font-bold text-gray-900 tracking-tight">Distribute</h1>
            </div>
            <div class="metric-group mt-6">
                <div class="metric-item"><h3>Token Name</h3><p id="token-name-value" class="text-gray-900">-</p></div>
                <div class="metric-item"><h3>Ticker</h3><p id="token-ticker-value" class="text-purple-600 font-bold">-</p></div>
                <div class="metric-item"><h3>Total Supply</h3><p id="token-supply-value" class="text-gray-900">-</p></div>
                <div class="metric-item"><h3>To Be Distributed</h3><p id="tokens-to-distribute-value" class="text-gray-900">0</p></div>
                <div class="metric-item"><h3>Network</h3><p class="flex items-center gap-1.5 font-semibold text-gray-900"><span class="w-2 h-2 rounded-full bg-gray-400"></span> Base</p></div>
            </div>
        </header>

        <nav class="hub-tabs px-8 border-b border-gray-100">
            <button class="hub-tab active" data-tab="create">Schedules to Activate <span class="tab-count-pill" id="create-tab-count">0</span></button>
            <button class="hub-tab" data-tab="active">Active Schedules <span class="tab-count-pill gray" id="active-tab-count">0</span></button>
        </nav>

        <div class="hub-content-area">
            <div id="create-content" class="tab-content active">
                <div class="table-toolbar bg-white p-4 rounded-xl border border-gray-100 mb-6 flex justify-between items-center">
                    <div class="contract-info">
                        <div class="address-line bg-gray-50 px-3 py-2 rounded-lg border border-gray-100 flex items-center shadow-sm">
                            <span class="label text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mr-3">Token Contract</span>
                            <span id="contract-address-display" class="font-mono text-xs text-gray-600 font-medium tracking-tight">Not set</span>
                            <button class="copy-btn ml-3 text-gray-400 hover:text-purple-600 transition-colors" title="Copy Address" id="copy-address-btn" style="display: none;">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <a href="#" id="explorer-link" class="explorer-link mt-2 text-[10px] inline-flex items-center hover:underline" target="_blank" rel="noopener noreferrer" style="display: none;">
                            Verify on BaseScan <i data-lucide="external-link" class="w-3 h-3 ml-1"></i>
                        </a>
                    </div>
                    <button id="batch-create-btn" class="btn-secondary flex items-center gap-2 group shadow-sm" style="display: none;" disabled>
                        <i data-lucide="zap" class="w-4 h-4 group-hover:fill-purple-600 transition-all"></i>
                        <span class="btn-text">Batch Create Selected (<span id="batch-selected-count">0</span>)</span>
                    </button>
                </div>
                <div id="eligible-schedules-container">
                    <table id="investor-table" style="display: none;">
                        <thead>
                            <tr>
                                <th style="padding: 1rem 0.5rem; width: 2.5rem; text-align: center;">
                                    <input type="checkbox" id="select-all-checkbox" class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer">
                                </th>
                                <th>Investor Name</th>
                                <th>Round</th>
                                <th class="align-right">Token Allocation</th>
                                <th class="align-right">Vesting Profile (TGE/C/V)</th>
                                <th class="align-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div id="eligible-schedules-empty-state" class="empty-state">
                        <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="check-circle-2" class="w-8 h-8 text-green-500"></i>
                        </div>
                        <p class="text-gray-400 text-xs mt-1">All eligible schedules have been activated.</p>
                    </div>
                </div>
            </div>

            <div id="active-content" class="tab-content">
                 <div id="active-schedules-container">
                    <table id="active-schedules-table" style="display: none;">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Round</th>
                                <th class="align-right">Total Tokens</th>
                                <th class="align-right">Terms</th>
                                <th class="align-right">Activation Date</th>
                                <th>TX Hash</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                     <div id="active-schedules-empty-state" class="empty-state">
                        <i data-lucide="clock" class="w-12 h-12 text-gray-200 mx-auto mb-4"></i>
                        <p class="text-gray-500 font-medium">No active vesting streams detected.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- STYLES -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
    
    :root {
        --brand-purple: #7C5DFA; --brand-purple-light: #9277FF; --background-color: #F8F9FA;
        --card-background: #FFFFFF; --text-primary: #0C0E16; --text-secondary: #888EB0;
        --text-light: #FFFFFF; --border-color: #EBEDF2; --danger-color: #EC5757;
        --danger-color-light: #FF9797; --font-family-main: 'Montserrat', sans-serif;
    }
    
    body { font-family: var(--font-family-main); background: var(--background-color); }
    .distribution-hub { background-color: var(--card-background); border: 1px solid var(--border-color); border-radius: 20px; box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.08); overflow: hidden; }
    .hub-header { padding: 2.5rem; border-bottom: 1px solid var(--border-color); }
    .hub-header h1 { margin: 0; font-size: 1.75rem; color: var(--text-primary); letter-spacing: -0.02em; }
    .metric-group { display: flex; flex-wrap: wrap; gap: 3rem; }
    .metric-item h3 { margin: 0 0 0.5rem 0; font-size: 0.7rem; color: var(--text-secondary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
    .metric-item p { margin: 0; font-size: 1.2rem; font-weight: 700; letter-spacing: -0.01em; }
    
    .hub-tabs { display: flex; gap: 2.5rem; margin-bottom: -1px; }
    .hub-tab { padding: 1.5rem 0.5rem; border: none; background: none; cursor: pointer; font-size: 0.9rem; font-weight: 700; color: var(--text-secondary); position: relative; border-bottom: 3px solid transparent; transition: all 0.25s ease; }
    .hub-tab.active { color: var(--brand-purple); border-bottom-color: var(--brand-purple); }
    
    .tab-count-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 7px; border-radius: 7px; background: #EFEBFD; color: var(--brand-purple); font-size: 0.75rem; font-weight: 800; margin-left: 10px; }
    .tab-count-pill.gray { background: #F3F4F6; color: #6B7280; }
    
    .hub-content-area { padding: 2.5rem; }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th { padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.08em; background: #fafafa; }
    td { padding: 1.5rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; color: var(--text-primary); font-weight: 500; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background-color: #f9f9fb; }
    .align-right { text-align: right; }
    
    .btn-primary { padding: 0.75rem 1.5rem; font-size: 0.85rem; border-radius: 12px; color: white; font-weight: 800; border: none; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background-color: var(--brand-purple); box-shadow: 0 4px 14px rgba(124, 93, 250, 0.25); }
    .btn-primary:hover { background-color: var(--brand-purple-light); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124, 93, 250, 0.35); }
    .btn-primary:active { transform: translateY(0); }
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
    
    .btn-secondary { padding: 0.85rem 1.75rem; font-size: 0.95rem; border-radius: 14px; color: var(--brand-purple); font-weight: 800; border: 2.5px solid var(--brand-purple); background-color: transparent; cursor: pointer; transition: all 0.25s ease; }
    .btn-secondary:hover { background-color: #EFEBFD; transform: scale(1.02); }
    .btn-secondary:disabled { opacity: 0.4; cursor: not-allowed; border-color: var(--border-color); color: var(--text-secondary); transform: none; }
    
    .status-pill-active { display: inline-flex; align-items: center; gap: 8px; padding: 0.45rem 1rem; border-radius: 24px; font-weight: 800; font-size: 0.75rem; background-color: #ECFDF5; color: #059669; letter-spacing: 0.02em; }
    .status-pill-active::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }

    .loader-ring { border: 5px solid #f3f3f3; border-top: 5px solid var(--brand-purple); border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; }
    #status-modal-overlay { backdrop-filter: blur(12px); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .status-modal-content { box-shadow: 0 30px 100px -20px rgba(0, 0, 0, 0.4); border: 1px solid rgba(255,255,255,0.2); border-radius: 32px; }
    .status-icon-box { width: 80px; height: 80px; border-radius: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 36px; box-shadow: 0 15px 30px -10px rgba(0,0,0,0.15); }
    .status-icon-success { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; }
    .status-icon-error { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: white; }
</style>

<!-- MODALS -->
<div id="status-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(12, 14, 22, 0.88); align-items: center; justify-content: center; z-index: 9999;">
    <div class="status-modal-content" style="background-color: white; padding: 4rem 3rem; width: 90%; max-width: 480px; text-align: center;">
        <div id="status-modal-icon-container" style="margin-bottom: 2.5rem;"></div>
        <h2 id="status-modal-title" style="font-size: 1.75rem; font-weight: 800; color: #0C0E16; margin-bottom: 1.25rem; letter-spacing: -0.02em;"></h2>
        <p id="status-modal-message" style="color: #64748B; font-size: 1.05rem; line-height: 1.7; font-weight: 500;"></p>
        <button id="status-modal-close" style="display: none; margin-top: 3rem; width: 100%; padding: 1.25rem; font-size: 1rem;" class="btn-primary font-bold">Dismiss & Continue</button>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentProjectId = <?php echo json_encode($project_id); ?>;
    const BACKEND_URL = '/backend/release_tokens_backend.php';

    let provider, signer, userAccount, isWalletReady = false, eligibleWallets = [], activeSchedulesFromDB = [], tokenContractAddress = '', tokenDecimals = 18;

    function formatTokens(value) {
        if (!value || isNaN(value)) return '0';
        return Math.floor(parseFloat(value)).toLocaleString('en-US');
    }

    const VESTING_CONTRACT_ADDRESS = "0xb5D78DD3276325f5FAF3106Cc4Acc56E28e0Fe3B"; 
    
    // Correct ABI for Sablier events parsing based on successful previous runs
    const LOCKUP_LINEAR_ABI = [
        "event CreateLockupLinearStream(uint256 indexed streamId, (address funder, address sender, address recipient, (uint128 deposit, uint128 brokerFee) amounts, address token, bool cancelable, bool transferable, (uint40 start, uint40 end) timestamps, string shape, address broker) commonParams, uint40 cliffTime, (uint128 start, uint128 cliff) unlockAmounts)"
    ];
    
    const VESTING_ABI = [
        ...LOCKUP_LINEAR_ABI,
        "function batch(bytes[] calls) payable returns (bytes[])",
        "function createWithTimestampsLL((address sender, address recipient, uint128 totalAmount, address token, bool cancelable, bool transferable, (uint40 start, uint40 end) timestamps, string shape, (address account, uint256 fee) broker), (uint128 start, uint128 cliff) unlockAmounts, uint40 cliffTime) payable returns (uint256)"
    ];

    const ERC20_ABI = [ 
        "function name() view returns (string)", 
        "function symbol() view returns (string)", 
        "function decimals() view returns (uint8)", 
        "function approve(address spender, uint256 amount) returns (bool)", 
        "function allowance(address owner, address spender) view returns (uint256)",
        "function totalSupply() view returns (uint256)" 
    ];

    const walletInfoContainer = document.getElementById('wallet-info-container');
    const tokenNameValue = document.getElementById('token-name-value');
    const tokenTickerValue = document.getElementById('token-ticker-value');
    const tokenSupplyValue = document.getElementById('token-supply-value');
    const tokensToDistributeValue = document.getElementById('tokens-to-distribute-value');
    const contractAddressDisplay = document.getElementById('contract-address-display');
    const copyAddressBtn = document.getElementById('copy-address-btn');
    const explorerLink = document.getElementById('explorer-link');
    const createTabCount = document.getElementById('create-tab-count');
    const activeTabCount = document.getElementById('active-tab-count');
    const investorTable = document.getElementById('investor-table');
    const investorTableBody = investorTable.querySelector('tbody');
    const eligibleSchedulesEmptyState = document.getElementById('eligible-schedules-empty-state');
    const activeSchedulesTable = document.getElementById('active-schedules-table');
    const activeSchedulesTableBody = activeSchedulesTable.querySelector('tbody');
    const activeSchedulesEmptyState = document.getElementById('active-schedules-empty-state');
    const statusModalOverlay = document.getElementById('status-modal-overlay');
    const statusModalIconContainer = document.getElementById('status-modal-icon-container');
    const statusModalTitle = document.getElementById('status-modal-title');
    const statusModalMessage = document.getElementById('status-modal-message');
    const statusModalClose = document.getElementById('status-modal-close');
    const pageLoadingState = document.getElementById('page-loading-state');
    const pageErrorState = document.getElementById('page-error-state');
    const pageErrorMessage = document.getElementById('page-error-message');
    const distributionContent = document.getElementById('distribution-content');
    const batchCreateBtn = document.getElementById('batch-create-btn');
    const batchSelectedCount = document.getElementById('batch-selected-count');
    const selectAllCheckbox = document.getElementById('select-all-checkbox');

    function showStatusMessage(message, type = 'loading', title = '', duration = 0) {
        statusModalOverlay.style.display = 'flex';
        statusModalClose.style.display = 'none';
        let iconHtml = '';
        if (type === 'loading') {
            iconHtml = '<div class="loader-ring mx-auto"></div>';
            statusModalTitle.innerText = title || 'Processing Request';
        } else if (type === 'success') {
            iconHtml = '<div class="status-icon-box status-icon-success">✓</div>';
            statusModalTitle.innerText = title || 'Protocol Verified';
        } else {
            iconHtml = '<div class="status-icon-box status-icon-error">✕</div>';
            statusModalTitle.innerText = title || 'System Fault';
            statusModalClose.style.display = 'block';
        }
        statusModalIconContainer.innerHTML = iconHtml;
        statusModalMessage.innerText = message;
        if (duration > 0) setTimeout(() => { statusModalOverlay.style.display = 'none'; }, duration);
    }

    statusModalClose.addEventListener('click', () => { statusModalOverlay.style.display = 'none'; });
    
    // RESTORED: Simple wallet info design
    function populateWalletInfo(account) {
        if (account) {
            const truncatedAddress = `${account.substring(0, 6)}...${account.substring(account.length - 4)}`;
            walletInfoContainer.innerHTML = `<div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm text-sm w-64"><div class="flex items-center space-x-3"><i data-lucide="wallet" class="w-5 h-5 text-gray-500"></i><p class="font-medium text-gray-800 truncate" title="${account}">${truncatedAddress}</p><span class="inline-flex items-center gap-x-1.5 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Connected</span></div></div>`;
        } else {
            walletInfoContainer.innerHTML = ``;
        }
        lucide.createIcons();
    }

    async function fetchTokenDetails() {
        if (!provider || !ethers.isAddress(tokenContractAddress)) return;
        try {
            const contract = new ethers.Contract(tokenContractAddress, ERC20_ABI, provider);
            const [name, symbol, supply, decimals] = await Promise.all([contract.name(), contract.symbol(), contract.totalSupply(), contract.decimals()]);
            tokenNameValue.textContent = name;
            tokenTickerValue.textContent = `$${symbol}`;
            tokenDecimals = Number(decimals);
            tokenSupplyValue.textContent = formatTokens(ethers.formatUnits(supply, decimals));
            contractAddressDisplay.textContent = `${tokenContractAddress.substring(0, 6)}...${tokenContractAddress.substring(tokenContractAddress.length - 4)}`;
            copyAddressBtn.style.display = 'inline-flex';
            explorerLink.href = `https://basescan.org/token/${tokenContractAddress}`;
            explorerLink.style.display = 'inline-flex';
        } catch (error) { console.error(error); }
    }

    async function ensureConnection() {
        if (isWalletReady) return true;
        try {
            if (!window.ethereum) { showStatusMessage("Web3 Wallet interface not found. Connection required.", 'error', 'Wallet Connection Failure'); return false; }
            provider = new ethers.BrowserProvider(window.ethereum);
            const accounts = await provider.send("eth_requestAccounts", []);
            if (accounts.length === 0) return false;
            const network = await provider.getNetwork();
            if (network.chainId !== 8453n) {
                showStatusMessage('Network mismatch detected. Switch to Base Mainnet.', 'loading', 'Base Optimization');
                await provider.send('wallet_switchEthereumChain', [{ chainId: '0x2105' }]);
                provider = new ethers.BrowserProvider(window.ethereum);
            }
            signer = await provider.getSigner();
            userAccount = await signer.getAddress();
            isWalletReady = true;
            populateWalletInfo(userAccount);
            if (tokenContractAddress) await fetchTokenDetails();
            return true;
        } catch (error) { showStatusMessage("Blockchain handshake rejected or timed out.", 'error', 'Authorization Failed'); return false; }
    }

    async function handleAction(investmentIds) {
        if (!await ensureConnection()) return;
        try {
            const tokenContract = new ethers.Contract(tokenContractAddress, ERC20_ABI, signer);
            const vestingContract = new ethers.Contract(VESTING_CONTRACT_ADDRESS, VESTING_ABI, signer);
            const targets = eligibleWallets.filter(w => investmentIds.includes(w.investment_id.toString()));
            if (targets.length === 0) throw new Error("Protocol logic failure: Selected targets not found.");

            let totalNeeded = 0n;
            targets.forEach(w => totalNeeded += ethers.parseUnits(w.token_quantity.toString(), tokenDecimals));

            showStatusMessage("Initializing cryptographic validation checks...", "loading", "Step 1/4: Auth");
            const currentAllowance = await tokenContract.allowance(userAccount, VESTING_CONTRACT_ADDRESS);
            if (currentAllowance < totalNeeded) {
                showStatusMessage("Grant token spending authority to the Sablier Smart Protocol.", "loading", "Granting Access");
                const approveTx = await tokenContract.approve(VESTING_CONTRACT_ADDRESS, ethers.MaxUint256);
                showStatusMessage("Waiting for confirmation on the Base Network...", "loading", "Synchronizing Permissions");
                await approveTx.wait();
            }

            showStatusMessage("Constructing linear distribution vectors...", "loading", "Step 2/4: Mapping");
            const now = Math.floor(Date.now() / 1000);
            const calls = targets.map(w => {
                const total = ethers.parseUnits(w.token_quantity.toString(), tokenDecimals);
                const cliffTime = now + (parseInt(w.cliff_months) * 30 * 24 * 60 * 60);
                const endTime = cliffTime + (parseInt(w.vesting_months) * 30 * 24 * 60 * 60);
                const tgePercent = parseFloat(w.percent_unlock_at_tge) || 0;
                const tgeAmount = (total * BigInt(Math.floor(tgePercent * 100))) / 10000n;
                const params = { sender: userAccount, recipient: w.investor_wallet_address, totalAmount: total, token: tokenContractAddress, cancelable: true, transferable: true, timestamps: { start: now, end: endTime }, shape: "linear", broker: { account: ethers.ZeroAddress, fee: 0n } };
                const unlockAmounts = { start: tgeAmount, cliff: 0n };
                return vestingContract.interface.encodeFunctionData("createWithTimestampsLL", [params, unlockAmounts, BigInt(cliffTime)]);
            });

            showStatusMessage(`Transmitting ${targets.length} streams to the chain. Await signature.`, "loading", "Step 3/4: Broadcasting");
            const tx = await vestingContract.batch(calls);
            showStatusMessage("Confirming block finality on Base...", "loading", "Awaiting Confirmation");
            const receipt = await tx.wait();

            // 4. DATABASE SYNC
            showStatusMessage("Commitment complete. Synchronizing protocol logs...", "loading", "Step 4/4: Archiving");
            
            // Log Parsing logic corrected to match working topic hashes
            const streamIds = [];
            const TOPIC_HASH = "0x69b9cccf0c4314c478985f4306354898144b6338455d35cfff9389124479d2bd"; // Sablier V2 Create Topic

            receipt.logs.forEach(log => {
                if (log.topics[0] === TOPIC_HASH) {
                    // streamId is typically topic[1] in Sablier V2
                    const sid = ethers.toBigInt(log.topics[1]).toString();
                    streamIds.push(sid);
                } else {
                    // Try parsing with interface as fallback
                    try {
                        const parsed = vestingContract.interface.parseLog(log);
                        if (parsed && (parsed.name === 'CreateLockupLinearStream' || parsed.name === 'CreateLockupLinear')) {
                            streamIds.push(parsed.args.streamId.toString());
                        }
                    } catch (e) {}
                }
            });

            if (streamIds.length !== targets.length) {
                throw new Error(`Integrity Mismatch: ${targets.length} streams sent, but only ${streamIds.length} stream IDs recovered. Check blockchain explorer.`);
            }

            const payload = {
                project_id: currentProjectId,
                tx_hash: tx.hash,
                batch: targets.map((w, idx) => ({
                    investment_id: w.investment_id,
                    stream_id: streamIds[idx]
                }))
            };

            const dbRes = await fetch(BACKEND_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const dbJson = await dbRes.json();
            if (!dbJson.success) throw new Error(dbJson.error || "Database synchronization failed after chain success.");

            showStatusMessage("All streams are operational. Tokens are now streaming in real-time.", "success", "Protocol Deployed");
            setTimeout(() => location.reload(), 3000);

        } catch (error) {
            console.error(error);
            showStatusMessage(error.reason || error.message || "A protocol execution error occurred.", "error", "Transaction Failure");
        }
    }

    function populateEligibleInvestorsTable() {
        investorTableBody.innerHTML = '';
        if (eligibleWallets.length === 0) {
            investorTable.style.display = 'none'; eligibleSchedulesEmptyState.style.display = 'block'; batchCreateBtn.style.display = 'none';
            createTabCount.textContent = 0; return;
        }
        let total = 0;
        eligibleWallets.forEach(w => {
            const hasWallet = w.investor_wallet_address && ethers.isAddress(w.investor_wallet_address);
            if (hasWallet) total += parseFloat(w.token_quantity);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="checkbox" class="row-checkbox h-4 w-4 border-gray-300 rounded text-purple-600 focus:ring-purple-500" ${!hasWallet ? 'disabled' : ''} value="${w.investment_id}"></td>
                <td><div class="font-bold text-gray-900">${w.investor_name}</div>${!hasWallet ? '<span class="text-red-500 text-[9px] font-black uppercase tracking-tight">Invalid Destination Address</span>' : ''}</td>
                <td><span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">${w.round_name || 'N/A'}</span></td>
                <td class="align-right font-bold text-gray-900 tracking-tight">${formatTokens(w.token_quantity)}</td>
                <td class="align-right text-[10px] font-bold text-gray-400 tracking-tighter uppercase">${parseFloat(w.percent_unlock_at_tge).toFixed(2)}% TGE / ${w.cliff_months}M Cliff / ${w.vesting_months}M Vest</td>
                <td class="align-right"><button class="btn-primary create-btn px-4 py-2" data-id="${w.investment_id}" ${!hasWallet ? 'disabled' : ''}>Activate</button></td>`;
            investorTableBody.appendChild(row);
        });
        investorTable.style.display = ''; eligibleSchedulesEmptyState.style.display = 'none';
        batchCreateBtn.style.display = 'inline-flex'; createTabCount.textContent = eligibleWallets.length;
        tokensToDistributeValue.textContent = formatTokens(total);
        updateBatchButtonState();
    }
    
    function populateActiveSchedulesTable() {
        activeSchedulesTableBody.innerHTML = '';
        if (activeSchedulesFromDB.length === 0) {
            activeSchedulesTable.style.display = 'none'; activeSchedulesEmptyState.style.display = 'block';
            activeTabCount.textContent = 0; return;
        }
        activeSchedulesFromDB.forEach(s => {
            const row = document.createElement('tr');
            row.innerHTML = `<td><div class="font-bold text-gray-800">${s.investor_name}</div></td><td><span class="bg-gray-100 text-gray-500 px-2 py-0.5 rounded text-[10px] font-bold">${s.round_name || 'N/A'}</span></td><td class="align-right font-bold text-gray-900">${formatTokens(s.token_quantity)}</td><td class="align-right text-[10px] font-bold text-gray-400">${parseFloat(s.percent_unlock_at_tge).toFixed(2)}% / ${s.cliff_months}M / ${s.vesting_months}M</td><td class="align-right font-medium text-gray-500">${new Date(s.distributed_at).toLocaleDateString()}</td><td><a href="https://basescan.org/tx/${s.distribution_tx_hash}" target="_blank" class="explorer-link text-[10px] font-bold tracking-tighter">View TX</a></td><td><span class="status-pill-active">Streaming</span></td>`;
            activeSchedulesTableBody.appendChild(row);
        });
        activeSchedulesTable.style.display = ''; activeSchedulesEmptyState.style.display = 'none';
        activeTabCount.textContent = activeSchedulesFromDB.length;
    }

    function updateBatchButtonState() {
        const selected = investorTableBody.querySelectorAll('.row-checkbox:checked').length;
        batchSelectedCount.textContent = selected;
        batchCreateBtn.disabled = (selected === 0);
        const totalChecked = investorTableBody.querySelectorAll('.row-checkbox:not(:disabled)').length;
        selectAllCheckbox.checked = (totalChecked > 0 && selected === totalChecked);
    }

    async function initializePage() {
        if (!currentProjectId) { 
            pageErrorMessage.textContent = "Data connection failed: missing active_project_id."; pageLoadingState.style.display = 'none'; pageErrorState.style.display = 'block'; return; 
        }
        try {
            const response = await fetch(`${BACKEND_URL}?project_id=${currentProjectId}`);
            const result = await response.json();
            eligibleWallets = result.data.schedulesToActivate || [];
            activeSchedulesFromDB = result.data.activeSchedules || [];
            tokenContractAddress = result.data.tokenContractAddress || '';
            if (!tokenContractAddress) throw new Error("Distributed asset contract not found.");
            pageLoadingState.style.display = 'none'; distributionContent.style.display = 'block';
            lucide.createIcons();
            provider = new ethers.JsonRpcProvider('https://mainnet.base.org');
            fetchTokenDetails();
            populateEligibleInvestorsTable();
            populateActiveSchedulesTable();
            document.querySelector('.hub-tabs').addEventListener('click', (e) => {
                const btn = e.target.closest('.hub-tab');
                if(btn) {
                    document.querySelectorAll('.hub-tab').forEach(t => t.classList.remove('active')); btn.classList.add('active');
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    document.getElementById(btn.dataset.tab + '-content').classList.add('active');
                }
            });
            selectAllCheckbox.addEventListener('change', (e) => {
                investorTableBody.querySelectorAll('.row-checkbox:not(:disabled)').forEach(cb => cb.checked = e.target.checked);
                updateBatchButtonState();
            });
            investorTableBody.addEventListener('change', (e) => { if(e.target.classList.contains('row-checkbox')) updateBatchButtonState(); });
            investorTableBody.addEventListener('click', (e) => { if (e.target.classList.contains('create-btn')) handleAction([e.target.dataset.id]); });
            batchCreateBtn.addEventListener('click', () => {
                const ids = Array.from(investorTableBody.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
                if (ids.length > 0) handleAction(ids);
            });
            copyAddressBtn.addEventListener('click', () => {
                const dummy = document.createElement("textarea"); document.body.appendChild(dummy); dummy.value = tokenContractAddress; dummy.select(); document.execCommand("copy"); document.body.removeChild(dummy);
                showStatusMessage("Asset contract copied to memory.", "success", "Address Captured", 2000);
            });
        } catch (error) { pageErrorMessage.textContent = error.message; pageLoadingState.style.display = 'none'; pageErrorState.style.display = 'block'; }
    }
    initializePage();
});
</script>