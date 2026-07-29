<?php
/**
 * Page: Distribute
 * Filepath: /pages/distribute.php
 */

// --- MERGED BACKEND LOGIC FOR PAGE LOAD ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/db.php';

// --- Global Variables for Page Content ---
$userInfo = null;
$projectDetails = null;
$deployedTokens = [];
$page_error = null;
$project_id = $_SESSION['active_project_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;


if (!$user_id) {
    $page_error = "Authentication required. Please log in.";
} elseif (!$project_id) {
    $page_error = "No active project found in session. Please return to the dashboard and re-select your project.";
}

// Fetch user info for the layout, regardless of other page logic.
if ($user_id && isset($pdo)) {
    try {
        $user_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM user WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $userInfo = $user_stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_info'] = $userInfo;
    } catch (PDOException $e) {
        error_log("Distribute UserInfo Error: " . $e->getMessage());
    }
}

if (!$page_error) {
    try {
        // --- Verification and Data Fetching ---
        $stmt_project = $pdo->prepare(
            "SELECT id, project_name, type_supply FROM projet WHERE id = ? AND founder_id = ?"
        );
        $stmt_project->execute([$project_id, $user_id]);
        $projectBaseDetails = $stmt_project->fetch(PDO::FETCH_ASSOC);

        if (!$projectBaseDetails) {
            throw new Exception("You do not have permission to access this project or it does not exist.");
        }

        // Fetch active scenario data
        $stmt_scenario = $pdo->prepare(
            "SELECT id as scenario_version_id, data, version_label FROM scenario_version WHERE projet_id = ? AND is_active = 1"
        );
        $stmt_scenario->execute([$project_id]);
        $scenarioData = $stmt_scenario->fetch(PDO::FETCH_ASSOC);

        $tokenDetails = [];
        if ($scenarioData) {
            $tokenDetails['version_label'] = $scenarioData['version_label'] ?? 'N/A';
            $tokenDetails['scenario_version_id'] = $scenarioData['scenario_version_id'] ?? null;
            if (!empty($scenarioData['data'])) {
                $jsonData = json_decode($scenarioData['data'], true);
                 if (isset($jsonData['core_params'])) {
                    $coreParams = $jsonData['core_params'];
                    $tokenDetails['token_name'] = $coreParams['token_name'] ?? 'N/A';
                    $tokenDetails['token_ticker'] = $coreParams['token_ticker'] ?? 'N/A';
                    $tokenDetails['supply_value'] = $coreParams['supply_value'] ?? 0;
                }
            }
        }
        $projectDetails = array_merge($projectBaseDetails, $tokenDetails);
        
        $supplyType = $projectDetails['type_supply'] ?? 'capped';
        $projectDetails['supply_type'] = ($supplyType === 'inflationary' || $supplyType === 'uncapped') ? 'dynamic' : 'capped';

        // Fetch selected token
        $stmt_selected = $pdo->prepare(
            "SELECT id, contract FROM deployed_token WHERE projet_id = ? AND selected_contract = 'yes' LIMIT 1"
        );
        $stmt_selected->execute([$project_id]);
        $selectedTokenInfo = $stmt_selected->fetch(PDO::FETCH_ASSOC);
        
        $projectDetails['selected_token_id'] = $selectedTokenInfo['id'] ?? null;
        $projectDetails['selected_token_contract'] = $selectedTokenInfo['contract'] ?? null;

        // Fetch all tokens for the project, including the scenario version label
        $stmt_tokens = $pdo->prepare(
            "SELECT dt.id, dt.contract, dt.deployment_date, dt.network, dt.wallet, dt.snapshot_data, dt.selected_contract, sv.version_label 
             FROM deployed_token dt
             LEFT JOIN scenario_version sv ON dt.scenario_version_id = sv.id
             WHERE dt.projet_id = ? ORDER BY dt.deployment_date DESC"
        );
        $stmt_tokens->execute([$project_id]);
        $deployedTokens = $stmt_tokens->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Distribute Page GET Error: " . $e->getMessage());
        $page_error = 'Failed to fetch project data. ' . $e->getMessage();
    }
}
?>
<main class="flex-1 p-10 overflow-y-auto">
    <?php if (isset($page_error)): ?>
        <div class="content-panel text-center max-w-lg mx-auto">
            <h2 class="text-xl font-bold text-red-600">Project Not Found</h2>
            <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($page_error); ?></p>
            <a href="/dashboard" class="btn btn-primary mt-6"><i data-lucide="arrow-left"></i> Go to Dashboard</a>
        </div>
    <?php else: ?>
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Distribution</h1>
            <p class="mt-2 text-base text-gray-500">Manage on-chain actions for your project. Deploy new token contracts and distribute tokens to investors from this hub.</p>
        </header>

        <div id="app-container" class="relative">
            <div id="main-actions-view">
                <div class="mb-10 content-panel">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6">Onchain Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div id="start-deploy-card" class="card-hover-effect flex flex-col items-center text-center cursor-pointer p-6 border rounded-lg bg-gray-50 hover:bg-white hover:shadow-md transition-all">
                            <div class="p-3 rounded-lg mb-4 bg-purple-100 text-purple-700"><i data-lucide="plus-circle" class="w-10 h-10"></i></div>
                            <h4 class="text-lg font-bold">Deploy Your Token</h4>
                            <p class="text-gray-500 text-sm mt-2 flex-grow">Deploy your token contract to the blockchain based on your project settings.</p>
                            <span class="mt-6 w-full text-center btn btn-primary py-2">Start Deploying</span>
                        </div>
                        <div id="distribute-card" class="card-hover-effect flex flex-col items-center text-center cursor-pointer p-6 border rounded-lg bg-gray-50 hover:bg-white hover:shadow-md transition-all">
                            <div class="p-3 rounded-lg mb-4 bg-cyan-100 text-cyan-600"><i data-lucide="send" class="w-10 h-10"></i></div>
                            <h4 id="distribute-card-title" class="text-lg font-bold">Distribute Tokens</h4>
                            <p id="distribute-card-text" class="text-gray-500 text-sm mt-2 flex-grow">Distribute tokens from your designated contract to investors.</p>
                            <span id="distribute-card-button" class="mt-6 w-full text-center btn text-white py-2 bg-cyan-500 hover:bg-cyan-600">Configure Distribution</span>
                        </div>
                        <div id="airdrop-card" class="relative overflow-hidden card-hover-effect flex flex-col items-center text-center cursor-not-allowed p-6 border rounded-lg bg-gray-50 opacity-70">
                            <div class="coming-soon-banner">SOON</div>
                            <div class="p-3 rounded-lg mb-4 bg-gray-100 text-gray-600"><i data-lucide="gift" class="w-10 h-10"></i></div>
                            <h4 class="text-lg font-bold">Airdrop Tokens</h4>
                            <p class="text-gray-500 text-sm mt-2 flex-grow">Airdrop tokens to a large list of community members or early supporters simultaneously.</p>
                            <span class="mt-6 w-full text-center btn bg-gray-600 text-white py-2 cursor-not-allowed">Start Airdrop</span>
                        </div>
                        <div id="list-token-card" class="relative overflow-hidden card-hover-effect flex flex-col items-center text-center cursor-not-allowed p-6 border rounded-lg bg-gray-50 opacity-70">
                            <div class="coming-soon-banner">SOON</div>
                            <div class="p-3 rounded-lg mb-4 bg-gray-100 text-gray-600"><i data-lucide="layers" class="w-10 h-10"></i></div>
                            <h4 class="text-lg font-bold">List Your Token</h4>
                            <p class="text-gray-500 text-sm mt-2 flex-grow">List your token on decentralized and centralized exchanges to enable public trading.</p>
                            <span class="mt-6 w-full text-center btn bg-gray-600 text-white py-2 cursor-not-allowed">Start Listing</span>
                        </div>
                    </div>
                </div>
                <div id="deployed-tokens-section">
                     <div class="flex items-center gap-2 mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Your Project's Deployed Tokens</h3>
                        <i data-lucide="info" class="w-4 h-4 text-gray-400" data-tooltip="For each project, you must select one token contract to be the official one for distribution. You can change this selection at any time."></i>
                     </div>
                     <div id="deployed-token-list" class="space-y-4 mt-6"></div>
                     <div id="no-tokens-message" class="hidden mt-4"></div>
                </div>
            </div>

            <div id="creation-flow-view" class="hidden">
                <button id="back-to-actions" class="text-sm font-semibold text-gray-700 hover:text-gray-900 mb-8 flex items-center"><i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>Back to Actions</button>
                <div class="max-w-3xl mx-auto space-y-8">
                    <!-- Simplified Deployment Step -->
                    <div id="deploy-step" class="bg-white rounded-2xl shadow-lg p-8 sm:p-10">
                         <div class="flex items-center">
                            <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full border-2 text-purple-600 font-bold text-xl">1</div>
                            <div class="ml-4">
                                <h3 class="text-2xl font-bold text-gray-900">Deploy your Token</h3>
                                <p class="text-gray-500 text-sm mt-1">Connect your wallet, review token details, and execute the deployment.</p>
                            </div>
                        </div>
                        <div id="deploy-content" class="mt-6"></div>
                    </div>
                </div>
            </div>
        </div>
        <div id="message-box" class="fixed bottom-5 right-5 transition-all duration-500 transform translate-x-full max-w-md z-50"></div>
        
        <div id="wallet-connect-modal" class="modal-overlay">
            <div class="modal-content">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">Connect Your Wallet</h3>
                    <button id="close-connect-modal-btn" class="p-1 text-gray-500 hover:text-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <p class="text-gray-600 mt-2 mb-6">An on-chain action requires a wallet connection. Please select a wallet to continue.</p>
                <div id="wallet-buttons-container" class="space-y-4">
                </div>
            </div>
        </div>
        
        <div id="delete-modal" class="modal-overlay">
            <div class="modal-content">
                <h3 class="text-xl font-bold text-gray-900">Confirm Deletion</h3>
                <p class="text-gray-600 mt-2">Are you sure you want to delete this token contract? This action is irreversible.</p>
                <p class="text-xs text-red-600 mt-2 bg-red-50 p-2 rounded-md">Note: This does not destroy the contract on the blockchain, but it will no longer be manageable here.</p>
                <div class="mt-6 flex justify-end gap-4">
                    <button id="cancel-delete-btn" class="btn bg-gray-200 text-gray-800 hover:bg-gray-300">Cancel</button>
                    <button id="confirm-delete-btn" class="btn bg-red-600 text-white hover:bg-red-700">Yes, Delete</button>
                </div>
            </div>
        </div>

        <div id="deploy-success-modal" class="modal-overlay">
            <div class="modal-content text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <i data-lucide="party-popper" class="h-10 w-10 text-green-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">Deployment Successful!</h3>
                <div id="deploy-success-details" class="text-gray-600 mt-4 text-left space-y-3">
                </div>
                <p class="mt-6 text-gray-500">Your new token is now available in the list below. The next step is to select it for distribution.</p>
                <div class="mt-8">
                    <button id="close-success-modal-btn" class="btn btn-primary w-full">Got it!</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
<style>
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0, 0, 0, 0.5); display: flex;
        align-items: center; justify-content: center; z-index: 50;
        opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s;
    }
    .modal-overlay.visible { opacity: 1; visibility: visible; }
    .modal-content {
        background: white; padding: 2rem; border-radius: 0.75rem; max-width: 500px;
        width: 90%; transform: scale(0.95); transition: transform 0.3s;
    }
    .modal-overlay.visible .modal-content { transform: scale(1); }
    .coming-soon-banner {
        position: absolute; top: 10px; right: -30px; transform: rotate(45deg);
        background-color: #fbbf24; color: #78350f; font-size: 0.7rem; font-weight: 700;
        padding: 2px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
<script src="/js/wallet-connector.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- PHP Data Injection ---
        const projectDetails = <?php echo json_encode($projectDetails ?? null); ?>;
        let deployedTokens = <?php echo json_encode($deployedTokens ?? []); ?>;
        const currentProjectId = <?php echo json_encode($project_id ?? null); ?>;
        let selectedTokenId = <?php echo json_encode($projectDetails['selected_token_id'] ?? null); ?>;
        const BACKEND_URL = '/backend/distribute_backend.php';

        if (!currentProjectId) {
            document.querySelector('main').innerHTML = '<div class="content-panel text-center max-w-lg mx-auto"><h2 class="text-xl font-bold text-red-600">Project Not Found</h2><p class="mt-2 text-gray-600">No active project is selected. Please return to the dashboard and choose a project.</p><a href="/dashboard" class="btn btn-primary mt-6"><i data-lucide="arrow-left"></i> Go to Dashboard</a></div>';
            lucide.createIcons();
            return;
        }

        // --- CONTRACT CONFIG ---
        const FACTORY_ADDRESS = "0xf2Ce5A4e87bFa1249F35c16c4DC477E499edd88C";
        const FACTORY_ABI = ["event TokenCreated(address indexed tokenAddress, address indexed creator, string name, string symbol, bool isMintable)", "function createToken(string memory name, string memory symbol, uint256 initialSupply, bool isMintable) public returns (address)"];
        
        // --- DOM Elements ---
        const walletConnectModal = document.getElementById('wallet-connect-modal');
        const closeConnectModalBtn = document.getElementById('close-connect-modal-btn');
        const walletButtonsContainer = document.getElementById('wallet-buttons-container');
        const mainActionsView = document.getElementById('main-actions-view');
        const creationFlowView = document.getElementById('creation-flow-view');
        const allViews = [mainActionsView, creationFlowView];
        const messageBox = document.getElementById('message-box');
        const deployedTokenList = document.getElementById('deployed-token-list');
        const noTokensMessage = document.getElementById('no-tokens-message');
        const deleteModal = document.getElementById('delete-modal');
        const deploySuccessModal = document.getElementById('deploy-success-modal');
        const closeSuccessModalBtn = document.getElementById('close-success-modal-btn');
        const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
        const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
        const backToActionsBtn = document.getElementById('back-to-actions');
        let tokenToDeleteId = null;
        
        // --- Application State ---
        let provider, signer, userAccount;
        let selectedProviderDetail = null;
        let isWalletInitialized = false;
        let resolveWalletConnection;

        // --- UI Update & Modal Functions ---
        function showMessage(title, text, status = 'info') {
            const icons = { info: 'info', success: 'check-circle-2', warning: 'alert-triangle', error: 'alert-circle' };
            const colors = { info: 'bg-blue-600', success: 'bg-green-600', warning: 'bg-yellow-500', error: 'bg-red-600' };
            const timeout = status === 'warning' ? 30000 : 6000;
            messageBox.innerHTML = `<div class="flex items-start p-4 rounded-lg shadow-lg ${colors[status] || 'bg-gray-800'} text-white"><div class="flex-shrink-0"><i data-lucide="${icons[status] || 'info'}" class="w-6 h-6"></i></div><div class="ml-3 flex-1"><p class="font-bold">${title}</p><div class="text-sm mt-1">${text}</div></div></div>`;
            lucide.createIcons();
            messageBox.classList.remove('translate-x-full');
            setTimeout(() => { messageBox.classList.add('translate-x-full'); }, timeout);
        }
        
        function showConnectModal() { walletConnectModal.classList.add('visible'); }
        function hideConnectModal() { 
            walletConnectModal.classList.remove('visible'); 
            if (resolveWalletConnection) {
                resolveWalletConnection(null);
                resolveWalletConnection = null;
            }
        }
        
        // --- "Just-in-Time" Wallet Connection Logic ---
        function renderWalletButtons(providers) {
            walletButtonsContainer.innerHTML = '';
            if (!providers || providers.length === 0) {
                 walletButtonsContainer.innerHTML = '<p class="text-center text-gray-500">No browser wallet detected.<br>Please install a wallet extension like Coinbase Wallet or MetaMask.</p>';
                return;
            }

            for (const providerDetail of providers) {
                const button = document.createElement('button');
                button.className = 'btn btn-primary px-5 py-2 w-full justify-center';
                button.innerHTML = `<img src="${providerDetail.info.icon}" alt="${providerDetail.info.name} logo" class="w-6 h-6 mr-2"> Connect ${providerDetail.info.name}`;
                button.onclick = () => connectWallet(providerDetail);
                walletButtonsContainer.appendChild(button);
            }
        }
        
        window.addEventListener('wallet-providers-updated', (event) => {
            renderWalletButtons(event.detail.providers);
        });
        
        async function disconnectWallet() {
            localStorage.removeItem('lastConnectedWalletUUID');
            if(selectedProviderDetail && selectedProviderDetail.provider.removeListener) {
                selectedProviderDetail.provider.removeListener('accountsChanged', handleAccountsChanged);
                selectedProviderDetail.provider.removeListener('chainChanged', handleChainChanged);
            }
            provider = signer = userAccount = selectedProviderDetail = null;
            isWalletInitialized = false;
            await updateDeployStepUI();
            showMessage('Disconnected', 'Your wallet has been disconnected.', 'info');
        }

        async function setupApplicationState(providerDetail) {
            try {
                selectedProviderDetail = providerDetail;
                provider = new ethers.BrowserProvider(selectedProviderDetail.provider, 'any');
                const accounts = await provider.send('eth_requestAccounts', []);

                if (accounts.length > 0) {
                    userAccount = accounts[0];
                    signer = await provider.getSigner();
                    
                    if (!isWalletInitialized) {
                        selectedProviderDetail.provider.on('accountsChanged', handleAccountsChanged);
                        selectedProviderDetail.provider.on('chainChanged', handleChainChanged);
                        isWalletInitialized = true;
                    }

                    localStorage.setItem('lastConnectedWalletUUID', providerDetail.info.uuid);
                    return signer;
                } else {
                    throw new Error("No accounts found.");
                }
            } catch (e) {
                console.error("Error setting up application state:", e);
                await disconnectWallet();
                throw e;
            }
        }

        async function connectWallet(providerDetail) {
            try {
                const signerInstance = await setupApplicationState(providerDetail);
                hideConnectModal();
                if (resolveWalletConnection) {
                    resolveWalletConnection(signerInstance);
                    resolveWalletConnection = null;
                }
                await updateDeployStepUI(); // VISUAL FIX: Update UI immediately after connection
                return signerInstance; 
            } catch (error) {
                hideConnectModal();
                if (resolveWalletConnection) {
                    resolveWalletConnection(null);
                    resolveWalletConnection = null;
                }
                if (error.code !== 4001) { 
                    console.error("Wallet connection failed:", error);
                    const errorMessage = error.reason || error.message || 'An unexpected error occurred. Please check the console for details.';
                    showMessage('Connection Error', errorMessage, 'error');
                }
                return null;
            }
        }
        
        async function ensureWalletConnected() {
            if (signer && provider) {
                const network = await provider.getNetwork();
                if (network.chainId !== 8453n) {
                    showMessage('Wrong Network', 'Please switch to the Base network to proceed.', 'warning');
                    await switchToBaseNetwork();
                    const newNetwork = await provider.getNetwork();
                    if (newNetwork.chainId !== 8453n) return null;
                }
                return signer;
            }

            renderWalletButtons(WalletConnector.getProviders());
            showConnectModal();
            return new Promise((resolve) => {
                resolveWalletConnection = resolve;
            });
        }
        
        async function switchToBaseNetwork() {
            if (!provider) return;
            try {
                await provider.send('wallet_switchEthereumChain', [{ chainId: '0x2105' }]);
            } catch (error) {
                showMessage('Network Switch Failed', 'Please manually switch to the Base network in your wallet.', 'warning');
            }
        }
        
        async function handleAccountsChanged(accounts) {
            if (accounts.length === 0) {
                await disconnectWallet();
            } else if (accounts[0].toLowerCase() !== userAccount.toLowerCase()) {
                await setupApplicationState(selectedProviderDetail)
                await updateDeployStepUI();
                showMessage("Account Switched", `Switched to new address: ${accounts[0].substring(0, 6)}...`, "info");
            }
        }

        async function handleChainChanged() {
            showMessage("Network Changed", "Wallet network has been updated.", "info");
            await updateDeployStepUI();
        }
        
        // --- View & Data Logic ---
        function showView(viewToShow) { allViews.forEach(v => v.classList.add('hidden')); viewToShow.classList.remove('hidden'); }
        function showMainActions() { showView(mainActionsView); }
        
        async function updateDeployStepUI() {
            const deployContent = document.getElementById('deploy-content');

            if (userAccount && signer) {
                const network = await provider.getNetwork();
                const isCorrectNetwork = network.chainId === 8453n;
                const truncatedAddress = `${userAccount.substring(0, 6)}...${userAccount.substring(userAccount.length - 4)}`;
                const statusClass = isCorrectNetwork ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800';
                const statusDot = isCorrectNetwork ? 'fill-green-500' : 'fill-yellow-500';
                const statusText = isCorrectNetwork ? 'Connected' : 'Wrong Network';
                const networkInfoHtml = !isCorrectNetwork ? `<button id="switchNetworkButton" class="w-full text-center font-semibold text-yellow-800 bg-yellow-100 hover:bg-yellow-200 p-1 rounded-md mt-2">Switch to Base</button>` : '';
                const displaySupplyType = projectDetails.type_supply ? projectDetails.type_supply.charAt(0).toUpperCase() + projectDetails.type_supply.slice(1) : 'Capped';

                deployContent.innerHTML = `
                    <div class="space-y-8">
                        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm text-sm mb-6">
                            <div class="flex items-center space-x-3">
                                <i data-lucide="wallet" class="w-5 h-5 text-gray-500"></i>
                                <p class="font-medium text-gray-800 truncate" title="${userAccount}">${truncatedAddress}</p>
                                <span class="inline-flex items-center gap-x-1.5 rounded-full ${statusClass} px-2 py-1 text-xs font-medium">
                                    <svg class="h-1.5 w-1.5 ${statusDot}" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3" /></svg>
                                    ${statusText}
                                </span>
                                <button id="disconnectButton" class="p-1 text-gray-400 hover:text-red-500 ml-auto"><i data-lucide="x" class="w-4 h-4"></i></button>
                            </div>
                            ${networkInfoHtml}
                        </div>
                        <div class="space-y-5 text-base">
                            <div class="flex justify-between items-center py-2 border-b"><span class="text-gray-500">Token Name</span><span class="font-semibold text-gray-800">${projectDetails.token_name}</span></div>
                            <div class="flex justify-between items-center py-2 border-b"><span class="text-gray-500">Ticker</span><span class="font-mono bg-gray-100 px-3 py-1 rounded-md text-sm font-semibold">${projectDetails.token_ticker}</span></div>
                            <div class="flex justify-between items-center py-2 border-b"><span class="text-gray-500">Initial Supply</span><span class="font-semibold text-gray-800">${Number(projectDetails.supply_value).toLocaleString()}</span></div>
                            <div class="flex justify-between items-center py-2"><span class="text-gray-500">Supply Type</span><span class="font-semibold text-gray-800">${displaySupplyType}</span></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-sm">
                             <div class="flex items-start">
                                <div class="flex-shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-500"></i></div>
                                <div class="ml-4">
                                    <h4 class="font-semibold text-gray-800">Final Confirmation</h4>
                                    <p class="mt-1 text-gray-600">Based on version "<strong>${projectDetails.version_label || 'current'}</strong>", these details cannot be changed after deployment.</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="mt-8 text-center">
                                <button id="deploy-token-button" class="btn btn-primary text-lg px-8 py-3 w-full sm:w-auto">
                                    <i data-lucide="send"></i> Deploy Token
                                </button>
                                <p class="text-xs text-gray-500 mt-3" id="gas-estimate">This action is irreversible and requires a gas fee.</p>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('disconnectButton')?.addEventListener('click', disconnectWallet);
                document.getElementById('switchNetworkButton')?.addEventListener('click', switchToBaseNetwork);
                document.getElementById('deploy-token-button').addEventListener('click', deployToken);
                displayGasEstimate();
            } else {
                const displaySupplyType = projectDetails.type_supply ? projectDetails.type_supply.charAt(0).toUpperCase() + projectDetails.type_supply.slice(1) : 'Capped';
                deployContent.innerHTML = `
                     <div class="space-y-8">
                         <div class="space-y-5 text-base opacity-70">
                            <div class="flex justify-between items-center py-2 border-b"><span class="text-gray-500">Token Name</span><span class="font-semibold text-gray-800">${projectDetails.token_name}</span></div>
                            <div class="flex justify-between items-center py-2 border-b"><span class="text-gray-500">Ticker</span><span class="font-mono bg-gray-100 px-3 py-1 rounded-md text-sm font-semibold">${projectDetails.token_ticker}</span></div>
                            <div class="flex justify-between items-center py-2 border-b"><span class="text-gray-500">Initial Supply</span><span class="font-semibold text-gray-800">${Number(projectDetails.supply_value).toLocaleString()}</span></div>
                            <div class="flex justify-between items-center py-2"><span class="text-gray-500">Supply Type</span><span class="font-semibold text-gray-800">${displaySupplyType}</span></div>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-sm">
                             <div class="flex items-start">
                                <div class="flex-shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-500"></i></div>
                                <div class="ml-4">
                                    <h4 class="font-semibold text-gray-800">Final Confirmation</h4>
                                    <p class="mt-1 text-gray-600">Based on version "<strong>${projectDetails.version_label || 'current'}</strong>", these details cannot be changed after deployment.</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8 text-center border-t pt-8">
                             <button id="connect-for-deploy-btn" class="btn btn-primary text-lg px-8 py-3 w-full sm:w-auto">
                                <i data-lucide="wallet"></i> Connect Wallet to Deploy
                             </button>
                             <p class="text-xs text-gray-500 mt-3">This action is irreversible and requires a gas fee.</p>
                        </div>
                    </div>
                `;
                document.getElementById('connect-for-deploy-btn').addEventListener('click', async () => {
                    const activeSigner = await ensureWalletConnected();
                    if(activeSigner) {
                        await updateDeployStepUI();
                    }
                });
            }
            lucide.createIcons();
        }

        function displayGasEstimate() {
            const gasEstimateEl = document.getElementById('gas-estimate');
            if (!gasEstimateEl) return;
            gasEstimateEl.textContent = 'This action is irreversible and requires a gas fee.';
        }

        function showCreationFlow() {
            if (!projectDetails || !projectDetails.token_name) { 
                showMessage("Token details missing", "Configure your token in project settings first.", "error"); 
                return; 
            }
            showView(creationFlowView);
            updateDeployStepUI();
        }

        function renderTokenList() {
            deployedTokenList.innerHTML = '';
            if (deployedTokens && deployedTokens.length > 0) {
                noTokensMessage.classList.add('hidden');
                deployedTokens.forEach(token => {
                    const isSelected = token.id === selectedTokenId;
                    const snapshot = JSON.parse(token.snapshot_data || '{}');
                    const basescanUrl = `https://basescan.org/address/${token.contract}`;
                    const cardClasses = isSelected ? 'border-purple-500 border-2 shadow-lg' : 'border-gray-300 border'; 
                    const buttonHtml = isSelected ? `<button class="btn bg-gray-200 text-gray-500 cursor-not-allowed w-full md:w-auto" disabled><i data-lucide="check-circle"></i> Selected</button>` : `<button data-action="select" data-id="${token.id}" class="btn bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 w-full md:w-auto">Select for Distribution</button>`;
                    const deleteButtonHtml = !isSelected ? `<button data-action="delete" data-id="${token.id}" class="btn bg-transparent text-gray-500 hover:text-red-600 hover:bg-red-50 w-full md:w-auto mt-2"><i data-lucide="trash-2" class="w-4 h-4 mr-1"></i> Delete</button>` : '';
                    const versionLabelHtml = token.version_label ? `<span class="text-xs bg-purple-100 text-purple-700 font-semibold px-2 py-1 rounded-full">${token.version_label}</span>` : '';
                    const card = `<div class="content-panel !p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative ${cardClasses}">${isSelected ? '<div class="absolute top-0 right-4 -mt-3 px-3 py-1 bg-purple-600 text-white text-xs font-bold rounded-full shadow-md">SELECTED</div>' : ''}<div><div class="flex items-center gap-3"><h4 class="text-lg font-bold">${snapshot.token_name}</h4><span class="font-mono bg-gray-100 px-2 py-1 rounded-md text-sm">${snapshot.token_symbol}</span>${versionLabelHtml}</div><div class="flex items-center gap-2 mt-2"><p class="text-sm text-gray-600 font-mono break-all"><span class="font-semibold text-gray-800">Contract:</span> ${token.contract}</p><button data-action="copy" data-address="${token.contract}" class="p-1 text-gray-400 hover:text-purple-600" title="Copy Address"><i data-lucide="copy" class="w-4 h-4"></i></button></div><a href="${basescanUrl}" target="_blank" class="text-sm text-purple-600 hover:underline font-mono flex items-center gap-1 mt-1">View on BaseScan <i data-lucide="external-link" class="w-3 h-3"></i></a></div><div class="mt-4 md:mt-0 flex-shrink-0 flex flex-col items-center">${buttonHtml}${deleteButtonHtml}</div></div>`;
                    deployedTokenList.innerHTML += card;
                });
            } else {
                noTokensMessage.classList.remove('hidden');
                noTokensMessage.innerHTML = `<div class="text-center p-12 bg-gray-50 border-2 border-dashed rounded-lg"><i data-lucide="shield-off" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i><h3 class="text-xl font-bold">No Tokens Deployed Yet</h3><p class="text-gray-500">Use the "Deploy New Token" action to get started.</p></div>`;
            }
            lucide.createIcons();
        }

        function showDeleteModal(tokenId) { tokenToDeleteId = tokenId; deleteModal.classList.add('visible'); }
        function hideDeleteModal() { tokenToDeleteId = null; deleteModal.classList.remove('visible'); }

        function showSuccessModal(token) {
            const successDetailsContainer = document.getElementById('deploy-success-details');
            const snapshot = JSON.parse(token.snapshot_data || '{}');
            const basescanUrl = `https://basescan.org/address/${token.contract}`;

            successDetailsContainer.innerHTML = `
                <p><strong class="font-semibold text-gray-800">Token Name:</strong> ${snapshot.token_name}</p>
                <p><strong class="font-semibold text-gray-800">Token Symbol:</strong> <span class="font-mono bg-gray-100 px-2 py-1 rounded-md text-sm">${snapshot.token_symbol}</span></p>
                <div>
                    <strong class="font-semibold text-gray-800">Contract Address:</strong>
                    <div class="flex items-center gap-2 mt-1">
                        <p class="text-sm font-mono break-all p-2 bg-gray-50 rounded-md flex-grow">${token.contract}</p>
                        <button data-action="copy" data-address="${token.contract}" class="p-2 text-gray-500 hover:text-purple-600 bg-gray-100 hover:bg-gray-200 rounded-md" title="Copy Address">
                            <i data-lucide="copy" class="w-5 h-5"></i>
                        </button>
                        <a href="${basescanUrl}" target="_blank" class="p-2 text-gray-500 hover:text-purple-600 bg-gray-100 hover:bg-gray-200 rounded-md" title="View on BaseScan">
                            <i data-lucide="external-link" class="w-5 h-5"></i>
                        </a>
                    </div>
                </div>
            `;
            
            deploySuccessModal.classList.add('visible');
            lucide.createIcons();
        }

        function hideSuccessModal() {
            deploySuccessModal.classList.remove('visible');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showMessage('Copied!', 'Contract address copied.', 'success');
            });
        }
        
        // --- Action Handlers ---
        async function postData(payload) {
            try {
                const response = await fetch(BACKEND_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload), credentials: 'include' });
                if (!response.ok) throw new Error(`Server error: ${response.statusText}`);
                return await response.json();
            } catch (error) {
                return { success: false, error: error.message };
            }
        }

        async function handleDistributeClick() {
            if (selectedTokenId && projectDetails.selected_token_contract) {
                const result = await postData({ 
                    action: 'set_vesting_contract', 
                    contract_address: projectDetails.selected_token_contract 
                });
                
                if (result && result.success) {
                    window.location.href = `/release`;
                } else {
                    showMessage('Navigation Error', result.error || 'Could not prepare for vesting page.', 'error');
                }
            } else {
                showMessage('🎯 Choose a Token', 'Select one of your deployed tokens below to begin distribution.', 'info');
            }
        }

        async function selectTokenForDistribution(tokenId) {
            const result = await postData({ action: 'select_token', project_id: currentProjectId, token_id: parseInt(tokenId, 10) });
            if (result && result.success) {
                showMessage('✅ Token Selected', 'This token is now set for distribution.', 'success');
                selectedTokenId = parseInt(tokenId, 10);
                projectDetails.selected_token_id = selectedTokenId;
                projectDetails.selected_token_contract = deployedTokens.find(t => t.id == tokenId)?.contract;
                renderTokenList();
            } else if (result) {
                showMessage('Selection Failed', result.error, 'error');
            }
        }

        async function confirmDeletion() {
            if (!tokenToDeleteId) return;

            const result = await postData({ action: 'delete_token', project_id: currentProjectId, token_id: tokenToDeleteId });
            if (result && result.success) {
                showMessage('🗑️ Token Deleted', 'The token contract has been removed.', 'success');
                deployedTokens = deployedTokens.filter(t => t.id.toString() !== tokenToDeleteId.toString());
                if (selectedTokenId && selectedTokenId.toString() === tokenToDeleteId.toString()) selectedTokenId = null;
                renderTokenList();
            } else if (result) {
                showMessage('Deletion Failed', result.error, 'error');
            }
            hideDeleteModal();
        }
        
        async function performDeployment(config) {
            const { signer, projectDetails } = config;
            const { token_name: name, token_ticker: symbol, supply_value: initialSupplyStr, supply_type } = projectDetails;

            if (!name || name.trim() === '' || name === 'N/A') throw new Error("Token Name is missing.");
            if (!symbol || symbol.trim() === '' || symbol === 'N/A') throw new Error("Token Ticker/Symbol is missing.");
            if (!initialSupplyStr || Number(initialSupplyStr) <= 0) throw new Error("Initial supply must be greater than 0.");

            const isMintable = supply_type === 'dynamic';
            const initialSupply = ethers.parseUnits(String(initialSupplyStr), 18);
            const activeFactoryContract = new ethers.Contract(FACTORY_ADDRESS, FACTORY_ABI, signer);
            
            const unsignedTx = await activeFactoryContract.createToken.populateTransaction(name, symbol, initialSupply, isMintable);
            
            try {
                await signer.call(unsignedTx);
            } catch (error) {
                const reason = error.reason || "Transaction simulation failed for an unknown reason.";
                console.error("Transaction simulation failed:", error);
                throw new Error(`Smart contract revert reason: ${reason}`);
            }

            return await signer.sendTransaction(unsignedTx);
        }


        async function deployToken() {
            const deployButton = document.getElementById('deploy-token-button');
            
            try {
                const activeSigner = await ensureWalletConnected();
                if (!activeSigner) return;

                deployButton.disabled = true;
                deployButton.innerHTML = `<i data-lucide="loader-2" class="animate-spin"></i> Awaiting confirmation...`;
                lucide.createIcons();
                showMessage('Action Required', 'Please confirm the transaction in your wallet.', 'info');

                const txResponse = await performDeployment({
                    signer: activeSigner,
                    projectDetails: projectDetails
                });

                deployButton.innerHTML = `<i data-lucide="loader-2" class="animate-spin"></i> Processing Transaction...`;
                showMessage('Transaction Sent', 'Deployment is being processed by the network. This may take a moment.', 'info');

                const receipt = await txResponse.wait();
                const factoryInterface = new ethers.Interface(FACTORY_ABI);
                const event = receipt.logs
                    .map(log => { try { return factoryInterface.parseLog(log); } catch { return null; }})
                    .find(l => l?.name === "TokenCreated");

                if (event) {
                    const payload = {
                        action: 'save_deployed_token',
                        project_id: currentProjectId,
                        contract_address: event.args.tokenAddress,
                        wallet_address: userAccount,
                        token_name: projectDetails.token_name,
                        token_symbol: projectDetails.token_ticker,
                        initial_supply: projectDetails.supply_value,
                        is_mintable: projectDetails.supply_type === 'dynamic',
                        scenario_version_id: projectDetails.scenario_version_id,
                        deployment_tx_hash: receipt.hash,
                        network_chain_id: (await provider.getNetwork()).chainId.toString()
                    };
                    const result = await postData(payload);
                    
                    if (result?.success && result.new_token) {
                        showSuccessModal(result.new_token);
                        deployedTokens.unshift(result.new_token);
                        renderTokenList();
                        showMainActions();
                    } else {
                        throw new Error(result.error || "Backend error: Could not save the token.");
                    }
                } else {
                    throw new Error("TokenCreated event not found in transaction logs.");
                }

            } catch (deploymentError) {
                console.error("Full Deployment Error:", JSON.stringify(deploymentError, null, 2));
                // Simplified error message as requested
                showMessage('🕵️ Deployment Failed', 'The transaction has failed - check gas fees.', 'error');
            } finally {
                if (deployButton) {
                    deployButton.disabled = false;
                    deployButton.innerHTML = '<i data-lucide="send"></i> Deploy Token';
                    lucide.createIcons();
                }
            }
        }
        
        // --- AUTO-CONNECTION & INITIAL LOAD ---
        async function tryAutoConnect() {
            const lastUUID = localStorage.getItem('lastConnectedWalletUUID');
            if (!lastUUID) return;

            const getProvidersWithTimeout = (timeout = 1000) => {
                return new Promise((resolve) => {
                    const initialProviders = WalletConnector.getProviders();
                    if (initialProviders && initialProviders.length > 0) {
                        return resolve(initialProviders);
                    }
                    const timer = setTimeout(() => {
                        window.removeEventListener('wallet-providers-updated', providerListener);
                        resolve(WalletConnector.getProviders() || []);
                    }, timeout);
                    const providerListener = (event) => {
                        clearTimeout(timer);
                        resolve(event.detail.providers);
                    };
                    window.addEventListener('wallet-providers-updated', providerListener, { once: true });
                });
            };

            const providers = await getProvidersWithTimeout();
            const providerDetail = providers.find(p => p.info.uuid === lastUUID);
            
            if (providerDetail) {
                try {
                    const connectedSigner = await connectWallet(providerDetail);
                    if (connectedSigner) {
                        await updateDeployStepUI();
                    }
                } catch (e) {
                    console.error("Auto-connect failed:", e);
                    localStorage.removeItem('lastConnectedWalletUUID');
                }
            }
        }

        renderTokenList();
        lucide.createIcons();
        tryAutoConnect(); // Attempt to reconnect on page load.
        
        // --- EVENT LISTENERS ---
        closeConnectModalBtn.addEventListener('click', hideConnectModal);
        closeSuccessModalBtn.addEventListener('click', hideSuccessModal);
        cancelDeleteBtn.addEventListener('click', hideDeleteModal);
        confirmDeleteBtn.addEventListener('click', confirmDeletion);
        
        document.getElementById('start-deploy-card').addEventListener('click', showCreationFlow);
        
        document.getElementById('distribute-card').addEventListener('click', handleDistributeClick);
        backToActionsBtn.addEventListener('click', showMainActions);

        // Use event delegation for the dynamic token list
        document.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (!button) return;

            const action = button.dataset.action;
            const id = button.dataset.id;
            
            if (action === 'select') {
                selectTokenForDistribution(id);
            } else if (action === 'delete') {
                showDeleteModal(id);
            } else if (action === 'copy') {
                const address = button.dataset.address;
                if(address) copyToClipboard(address);
            }
        });
    });
</script>

