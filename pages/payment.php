<?php
/**
 * pages/payment.php
 * Displays payment options.
 * FIX: Removed "investment" wording and updated stepper UI.
 */
require_once 'src/db.php';
require_once 'src/session.php';
start_secure_session();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$current_user_id = $_SESSION['user_id'];
$formMessage = '';

$investmentId = $_SESSION['current_investment_id'] ?? null;
$projectName = 'N/A';
$investmentAmount = 0;
$escrowContractAddress = '0x1234567890AbCdEf1234567890AbCdEf12345678'; // Example
$stablecoinName = 'USDC';
$networkName = 'Base';
$is_loaded = false;

if (!$investmentId) {
    $formMessage = "Could not find an active contribution. Please start over.";
} else {
    try {
        $stmtInvestment = $pdo->prepare(
            "SELECT i.project_id, i.amount_usd, p.project_name
             FROM investments i
             JOIN projet p ON i.project_id = p.id
             WHERE i.id = :investment_id AND i.user_id = :current_user_id AND i.status = 'initiated'"
        );
        $stmtInvestment->execute(['investment_id' => $investmentId, 'current_user_id' => $current_user_id]);
        $investmentData = $stmtInvestment->fetch(PDO::FETCH_ASSOC);

        if ($investmentData) {
            $is_loaded = true;
            $investmentAmount = (float)$investmentData['amount_usd'];
            $projectName = htmlspecialchars($investmentData['project_name']);
        } else {
            $formMessage = "Your contribution intent was not found or has already been paid. Please check your portfolio.";
        }
    } catch (PDOException $e) {
        $formMessage = "A database error occurred while fetching data.";
        error_log("PDO Error on payment page: " . $e->getMessage());
    }
}
?>
<style>
    :root { --accent-purple-dark: #6d28d9; }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.6rem 1.25rem; border-radius: 0.5rem; font-weight: 600; transition: all 150ms ease-in-out; border: 1px solid transparent; text-decoration: none; cursor: pointer; }
    .btn-primary { background-color: var(--accent-purple-dark); color: white; }
    .btn-primary:hover:not(:disabled) { background-color: #5b21b6; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .spinner { border: 2px solid #f3f3f3; border-top: 2px solid var(--accent-purple-dark); border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .modal-overlay { position: fixed; inset: 0; background-color: rgba(17, 24, 39, 0.75); display: flex; align-items: center; justify-content: center; z-index: 50; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
    .modal-overlay.visible { opacity: 1; visibility: visible; }
    .modal-content { background: white; padding: 1.5rem; border-radius: 0.75rem; max-width: 400px; width: 90%; transform: scale(0.95); transition: transform 0.3s; }
    .modal-overlay.visible .modal-content { transform: scale(1); }
    .step-circle { width: 2rem; height: 2rem; border-radius: 9999px; border-width: 2px; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; transition: all 0.2s; }
    .step-title { margin-left: 0.75rem; font-size: 0.875rem; }
    .step-active .step-circle { background-color: var(--accent-purple-dark); border-color: var(--accent-purple-dark); color: white; }
    .step-active .step-title { color: #111827; font-weight: 600; }
    .step-inactive .step-circle { border-color: #d1d5db; color: #6b7280; }
    .step-inactive .step-title { color: #6b7280; }
    .step-complete .step-circle { background-color: var(--accent-purple-dark); border-color: var(--accent-purple-dark); color: white; }
    .step-complete .step-title { color: #111827; font-weight: 600; }
</style>

<div class="max-w-xl mx-auto p-4 md:p-6">
    <div id="success-modal" class="modal-overlay">
         <div class="modal-content text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100"><i data-lucide="check-check" class="h-6 w-6 text-green-600"></i></div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-5">Payment Submitted</h3>
            <div class="mt-4 px-4 py-3"><p class="text-sm text-gray-600">Your payment has been submitted. You can check the final status on your portfolio page.</p></div>
            <div class="mt-4"><a href="portfolio" class="btn btn-primary w-full text-center block">Go to Portfolio</a></div>
        </div>
    </div>
    
    <div id="wallet-connect-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="flex justify-between items-center"><h3 class="text-xl font-bold text-gray-900">Connect Wallet</h3><button id="close-connect-modal-btn" class="p-1 text-gray-500 hover:text-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button></div>
            <p class="text-gray-600 mt-2 mb-6">Please select a wallet to continue.</p>
            <div id="wallet-buttons-container" class="space-y-3"></div>
        </div>
    </div>

    <div class="text-center mb-8"><h1 class="text-3xl font-bold text-gray-900">Secure Payment</h1><p class="mt-2 text-gray-600">Complete your contribution for <?php echo $projectName; ?>.</p></div>
    
    <div class="max-w-xl mx-auto mb-10">
        <div class="flex items-center">
            <div class="flex items-center step-complete">
                <div class="step-circle">1</div>
                <span class="step-title">Your Amount</span>
            </div>
            <div class="flex-1 h-0.5 bg-purple-600 mx-4"></div>
            <div class="flex items-center <?php echo empty($formMessage) ? 'step-active' : 'step-inactive'; ?>">
                <div class="step-circle">2</div>
                <span class="step-title">Secure Payment</span>
            </div>
        </div>
    </div>
    
    <?php if (!empty($formMessage)): ?>
        <div class="bg-white p-6 sm:p-8 rounded-lg border shadow-sm text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">There was a problem</h3>
            <p class="mt-2 text-sm text-red-600"><?php echo htmlspecialchars($formMessage); ?></p>
            <div class="mt-6">
                 <a href="purchase" class="btn btn-primary w-full sm:w-auto">Go Back and Try Again</a>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 sm:p-8 rounded-lg border shadow-sm">
            <div class="text-center border-b pb-6"><p class="text-sm text-gray-500">Contribution Amount</p><p class="text-4xl font-bold text-gray-900 my-1">$<?php echo number_format($investmentAmount, 2); ?></p></div>
            
            <div class="mt-6 pt-6" id="payment-options-section">
                <div id="stablecoin-content">
                    <div id="connect-wallet-section">
                        <p class="text-center text-sm text-gray-600 mb-4">Connect your wallet to pay with <?php echo htmlspecialchars($stablecoinName); ?> on the <?php echo htmlspecialchars($networkName); ?> network.</p>
                        <button id="connect-wallet-btn" class="btn btn-primary w-full"><i data-lucide="wallet" class="w-5 h-5 mr-2"></i>Connect Wallet</button>
                        <p class="text-center text-xs text-gray-500 mt-4">No stablecoin? <a href="#" class="font-semibold text-purple-600 hover:text-purple-800">Onramp in seconds by clicking here</a>.</p>
                    </div>
                    <div id="payment-section" class="hidden">
                        <div class="text-center text-sm bg-gray-100 p-3 rounded-md mb-4">Connected as: <strong id="wallet-address" class="font-mono"></strong></div>
                        <button id="deposit-btn" class="btn btn-primary w-full" data-investment-id="<?php echo htmlspecialchars($investmentId); ?>" data-idempotency-key="<?php echo bin2hex(random_bytes(16)); ?>">
                            <span id="deposit-btn-text">Deposit to Escrow</span>
                            <div id="deposit-spinner" class="spinner hidden ml-2"></div>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t">
                <div class="flex items-start space-x-3 text-xs text-gray-500">
                    <div class="flex-shrink-0"><i data-lucide="lock" class="w-4 h-4 text-gray-400"></i></div>
                    <div><p>Your funds are sent to a secure on-chain escrow. If the campaign fails, you are automatically refunded.</p></div>
                </div>
            </div>
        </div>
        <div class="text-center mt-4"><a href="purchase" class="text-sm text-gray-500 hover:text-black">Go Back</a></div>
    <?php endif; ?>
</div>

<script src="js/wallet-connector.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    
    const connectWalletBtn = document.getElementById('connect-wallet-btn');
    const depositBtn = document.getElementById('deposit-btn');
    const walletConnectModal = document.getElementById('wallet-connect-modal');
    const closeConnectModalBtn = document.getElementById('close-connect-modal-btn');
    const walletButtonsContainer = document.getElementById('wallet-buttons-container');
    const walletAddressEl = document.getElementById('wallet-address');
    const successModal = document.getElementById('success-modal');
    
    const showSuccessModal = () => {
        if (successModal) {
            successModal.classList.add('visible');
        }
    };

    const handleBackendSubmit = async () => {
        const depositBtnText = document.getElementById('deposit-btn-text');
        const depositSpinner = document.getElementById('deposit-spinner');
        
        depositBtn.disabled = true;
        depositBtnText.textContent = 'Processing...';
        depositSpinner.classList.remove('hidden');

        // This is a placeholder for actual transaction hash from a wallet interaction
        const testTxHash = '0x' + [...Array(64)].map(() => Math.floor(Math.random() * 16).toString(16)).join('');

        const formData = new FormData();
        formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>');
        formData.append('idempotency_key', depositBtn.dataset.idempotencyKey);
        formData.append('investment_id', depositBtn.dataset.investmentId);
        formData.append('payment_method', 'stablecoin');
        formData.append('tx_hash', testTxHash);

        try {
            const response = await fetch('/backend/payment_backend.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Server error');
            if (result.success) {
                showSuccessModal();
            } else {
                throw new Error(result.message || 'Unknown error');
            }
        } catch (error) {
            alert('An error occurred: ' + error.message);
            depositBtn.disabled = false;
            depositBtnText.textContent = 'Deposit to Escrow';
            depositSpinner.classList.add('hidden');
        }
    };
    
    const showConnectModal = () => walletConnectModal.classList.add('visible');
    const hideConnectModal = () => walletConnectModal.classList.remove('visible');
    
    const renderWalletButtons = () => {
        if (typeof WalletConnector === 'undefined' || !walletButtonsContainer) return;
        const providers = WalletConnector.getProviders();
        walletButtonsContainer.innerHTML = '';
        providers.forEach(p => {
            const btn = document.createElement('button');
            btn.className = 'btn btn-primary px-5 py-2 w-full justify-start';
            btn.innerHTML = `<img src="${p.info.icon}" alt="${p.info.name}" class="w-6 h-6 mr-3"> Connect ${p.info.name}`;
            btn.onclick = () => connectWallet(p);
            walletButtonsContainer.appendChild(btn);
        });
         if (providers.length === 0) {
            walletButtonsContainer.innerHTML = `<p class="text-sm text-center text-gray-500">No browser wallets detected. Please install a wallet like MetaMask to continue.</p>`;
        }
    };
    
    const connectWallet = async (providerDetail) => {
        try {
            const accounts = await providerDetail.provider.request({ method: 'eth_requestAccounts' });
            if (accounts[0]) {
                hideConnectModal();
                document.getElementById('connect-wallet-section').classList.add('hidden');
                document.getElementById('payment-section').classList.remove('hidden');
                walletAddressEl.textContent = `${accounts[0].substring(0, 6)}...${accounts[0].substring(accounts[0].length - 4)}`;
            }
        } catch (e) { alert('Connection rejected by user.'); }
    };
    
    window.addEventListener('wallet-providers-updated', renderWalletButtons);
    if(typeof WalletConnector !== 'undefined'){
      renderWalletButtons(); 
    }

    if (connectWalletBtn) connectWalletBtn.addEventListener('click', showConnectModal);
    if (depositBtn) depositBtn.addEventListener('click', handleBackendSubmit);
    if (closeConnectModalBtn) closeConnectModalBtn.addEventListener('click', hideConnectModal);
});
</script>

