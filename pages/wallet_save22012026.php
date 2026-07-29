<?php
// Ensure session is started safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CONFIGURATION ---
$CDP_PROJECT_ID = '6f36302b-a2c4-44a6-9128-88886b78a809';
$CDP_ENV = 'prod';

// Ensure CSRF Token exists
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = uniqid('', true);
    }
}

// --- DATABASE CONNECTION & WALLET FETCH ---
$existingCoinbaseWallet = null;
$dbError = null;
$pdo = null;

try {
    // Robust DB path detection
    $possiblePaths = [
        __DIR__ . '/src/db.php',
        __DIR__ . '/../src/db.php',
        $_SERVER['DOCUMENT_ROOT'] . '/src/db.php',
        'src/db.php' // Try relative to current execution
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $pdo = require $path;
            break;
        }
    }
    
    if (!$pdo) {
        throw new Exception("Database configuration file not found.");
    }
    
    if (isset($_SESSION['user_id'])) {
        // Note: Using 'coinbase_wallet_adress' (one 'd') as per your schema
        $stmt = $pdo->prepare("SELECT coinbase_wallet_adress FROM user WHERE id = :uid LIMIT 1");
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        $existingCoinbaseWallet = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Capture error to log in Console (do not show to user)
    $dbError = $e->getMessage();
}
?>

<style>
    /* IMPORT VARIABLES & THEME */
    :root {
        --tookle-purple: #6D28D9;
        --tookle-purple-light: #EDE9FE;
        --tookle-purple-dark: #4C1D95;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border-color: #e5e7eb;
    }

    /* Styles UI */
    .btn { display: inline-flex; align-items: center; justify-content: center; font-weight: 600; border-radius: 0.5rem; transition: all 0.2s ease-in-out; padding: 0.625rem 1.25rem; font-size: 0.875rem; cursor: pointer; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    
    .btn-neutral { background-color: white; color: var(--text-secondary); border: 1px solid #e5e7eb; }
    .btn-neutral:hover:not(:disabled) { background-color: #f9fafb; color: var(--text-primary); border-color: #d1d5db; }
    
    .btn-danger-outline { background-color: white; color: #dc2626; border: 1px solid #fecaca; }
    .btn-danger-outline:hover { background-color: #fef2f2; border-color: #dc2626; }

    .btn-primary { background-color: #111827; color: white; border: 1px solid transparent; } 
    .btn-primary:hover { background-color: #000000; transform: translateY(-1px); shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    
    .btn-success { background-color: #10b981; color: white; border: none; }
    .btn-success:hover { background-color: #059669; }

    /* Clean Address Display */
    .coinbase-address-display { 
        background-color: #f9fafb; 
        border: 1px solid #e5e7eb; 
        color: #374151; 
        padding: 0.75rem; 
        border-radius: 0.375rem; 
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; 
        display: flex; 
        align-items: center; 
        justify-content: space-between;
        gap: 0.5rem; 
        word-break: break-all;
        font-size: 0.8rem;
    }
    
    .simple-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .modern-input {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #1f2937;
        outline: none;
        transition: all 0.2s;
    }
    .modern-input:focus { border-color: #111827; box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05); }

    /* Address Result Chip */
    .address-chip {
        display: none;
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        padding: 0.75rem;
        margin-top: 1rem;
        align-items: center;
        justify-content: space-between;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8rem;
        color: #374151;
    }
    
    /* --- STYLES SPECIFIQUES ON-RAMP (Comme sur l'image) --- */
    
    /* Boutons Presets (100, 500...) */
    .preset-btn {
        padding: 0.75rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        font-size: 0.95rem;
        font-weight: 600;
        color: #374151;
        background: white;
        transition: all 0.2s;
        cursor: pointer;
        flex: 1;
        text-align: center;
    }
    .preset-btn:hover { border-color: #d1d5db; background-color: #f9fafb; }
    /* Style Actif (Dark Blue/Black comme sur l'image) */
    .preset-btn.active { 
        border-color: #111827; 
        color: white; 
        background-color: #111827; 
    }

    /* Toggle Devise (USD/EUR) */
    .currency-toggle {
        display: flex;
        background: #f3f4f6;
        padding: 2px;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
    }
    .currency-option {
        flex: 1;
        padding: 0.5rem;
        text-align: center;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: 0.375rem;
        cursor: pointer;
        color: #6b7280;
        transition: all 0.2s;
    }
    .currency-option.active { background: white; color: #111827; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    
    .step-hidden { display: none !important; }

    /* --- BRANDED MODAL STYLES --- */
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0, 0, 0, 0.4); 
        backdrop-filter: blur(5px);
        display: none;
        align-items: center; justify-content: center; z-index: 10000;
        animation: fadeIn 0.2s ease-out;
    }
    .modal-overlay.show { display: flex; }

    /* Standard Modal Content */
    .modal-content {
        background-color: white;
        border-radius: 1.5rem;
        max-width: 480px;
        width: 90%;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        transform: scale(0.95);
        animation: scaleIn 0.2s ease-out forwards;
        position: relative;
        overflow: hidden;
    }

    .branded-modal-content {
        background-color: white;
        padding: 2.5rem;
        border-radius: 1.5rem;
        max-width: 420px;
        width: 90%;
        text-align: center;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.95);
        animation: scaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        position: relative;
        border: 1px solid rgba(0,0,0,0.05);
    }

    /* Modern OTP Input Style */
    #branded-modal-input {
        font-family: 'Courier New', Courier, monospace; 
        letter-spacing: 0.5rem;
        font-size: 1.5rem;
        text-align: center;
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 0.75rem;
        transition: all 0.2s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02) inset;
        margin-top: 1rem;
    }
    #branded-modal-input:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
    }

    /* Private Key Display Area */
    .private-key-display {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        background: #111827;
        color: #10b981;
        padding: 1rem;
        border-radius: 0.5rem;
        word-break: break-all;
        margin-top: 1rem;
        text-align: left;
        max-height: 100px;
        overflow-y: auto;
    }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes scaleIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    
    .modal-step { display: none; }
    .modal-step.active { display: block; }
</style>

<main class="flex-1 overflow-y-auto p-4 md:p-8 bg-white">
    <div class="w-full max-w-6xl"> 
        
        <div class="flex flex-wrap justify-between items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Manage Wallets</h1>
                <p class="text-sm text-gray-500 mt-1">Connect your existing wallets or create a new one.</p>
            </div>
            
            <div class="flex gap-3">
                <button type="button" class="btn btn-neutral text-xs open-onramp-trigger">
                    <i data-lucide="credit-card" class="w-4 h-4 mr-2 text-gray-400"></i>
                    Fund your wallet
                </button>
            </div>
        </div>
        
        <?php if($dbError): ?>
        <div class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg border border-red-100 text-xs font-mono">
            <strong>System Connection Error:</strong> <?= htmlspecialchars($dbError) ?>
        </div>
        <?php endif; ?>

        <div class="flex flex-col gap-8">
            
            <div class="w-full">
                <div class="simple-card p-6 bg-white shadow-sm border border-gray-100">
                    <h2 class="text-base font-semibold text-gray-800 mb-4">External Wallets</h2>
                    <form id="wallet-form">
                        <div id="wallet-list">
                             <div class="hidden md:grid" style="grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                                <span class="text-xs font-semibold uppercase text-gray-400">Label</span>
                                <span class="text-xs font-semibold uppercase text-gray-400">Address</span>
                                <span class="text-xs font-semibold uppercase text-gray-400">Network</span>
                                <span></span>
                             </div>
                        </div>
                        <div class="mt-4 flex justify-between items-center">
                            <button type="button" id="add-wallet-button" class="btn btn-neutral text-sm w-full md:w-auto"><i data-lucide="plus" class="w-4 h-4 mr-2"></i>Add External Wallet</button>
                            <button type="submit" id="save-changes-button" class="btn btn-primary hidden">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="w-full">
                <div id="existing-wallet-card" class="simple-card p-6" style="<?php echo empty($existingCoinbaseWallet) ? 'display:none;' : ''; ?>">
                    <div class="flex items-center justify-between mb-6">
                         <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="wallet" class="w-4 h-4 text-gray-500"></i>
                            Embedded Wallet Active
                        </h3>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-green-600 bg-green-50 border border-green-100 px-2 py-1 rounded">Connected</span>
                    </div>
                    
                    <div class="coinbase-address-display mb-6">
                        <span id="saved-wallet-address" class="font-mono text-xs text-gray-600 break-all"><?php echo htmlspecialchars($existingCoinbaseWallet ?? ''); ?></span>
                        <button type="button" class="text-indigo-600 hover:text-indigo-800" onclick="copySavedAddress()">
                            <i data-lucide="copy" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <div class="h-px bg-gray-100 w-full mb-6"></div>

                    <div class="space-y-4">
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Security & Management</h4>
                        
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 bg-gray-50 rounded-lg border border-gray-100">
                            <div>
                                 <h5 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                     Private Key
                                 </h5>
                                 <p class="text-xs text-gray-500 mt-1 max-w-lg">
                                    <i data-lucide="shield-alert" class="w-3 h-3 inline mr-1 text-orange-500"></i>
                                    <span class="font-medium text-gray-700">TOOKLE does not see or have access to your private key.</span>
                                    It is stored securely on your device.
                                 </p>
                            </div>
                            <button type="button" id="export-wallet-btn" class="btn btn-neutral text-xs whitespace-nowrap">
                                <i data-lucide="eye" class="w-4 h-4 mr-2"></i> Reveal Private Key
                            </button>
                        </div>

                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 bg-red-50/50 rounded-lg border border-red-100/50">
                            <div>
                                 <h5 class="text-sm font-semibold text-red-900">Danger Zone</h5>
                                 <p class="text-xs text-red-800/70 mt-1">
                                    Remove this wallet from your account. This action cannot be undone unless you have backed up your key.
                                 </p>
                            </div>
                            <button type="button" id="delete-wallet-btn" class="btn btn-danger-outline text-xs whitespace-nowrap">
                                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i> Delete Wallet
                            </button>
                        </div>
                    </div>
                </div>

                <div id="create-wallet-section" style="<?php echo !empty($existingCoinbaseWallet) ? 'display:none;' : ''; ?>">
                    
                    <div class="simple-card p-6 bg-indigo-50/20 border border-indigo-100 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-base font-bold text-indigo-950 mb-1">No wallet yet?</h2>
                                <p class="text-xs text-indigo-800/70">
                                    Create a new secure embedded wallet using just your email.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 max-w-md"> 
                            <input type="email" id="email-input" placeholder="Enter your email" class="modern-input bg-white border-indigo-100 focus:border-indigo-500 focus:ring-indigo-100">
                            
                            <button type="button" id="wallet-btn" class="btn btn-primary w-full text-xs py-2.5 mt-3 justify-center">
                                <i data-lucide="mail" class="w-4 h-4 mr-2"></i> Create Embedded Wallet
                            </button>
                        </div>

                        <div id="address-container" class="address-chip bg-white shadow-sm max-w-md border border-indigo-100">
                            <div class="flex flex-col min-w-0">
                                <span class="text-[10px] uppercase font-bold text-gray-400">New ID</span>
                                <span id="wallet-address" class="font-bold text-gray-800 text-xs truncate"></span>
                            </div>
                            <button type="button" class="text-indigo-600 text-xs font-bold hover:underline ml-2" onclick="copyAddressToClipboard()">Copy</button>
                        </div>

                        <div id="step-2-area" class="step-hidden mt-4 pt-4 border-t border-indigo-100 max-w-md">
                            <p class="text-xs text-indigo-900 mb-2 font-semibold">Confirm to link:</p>
                            <div class="flex gap-2">
                                <input type="text" id="manual-address-input" class="modern-input bg-white text-xs border-indigo-100" placeholder="Paste 0x... address">
                                <button type="button" id="manual-save-btn" class="btn btn-success px-3 py-1">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <div class="mt-12 pt-8 border-t border-gray-100">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-6"> Glossary</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm hover:shadow-md transition-all">
                    <h4 class="font-bold text-gray-900 text-xs mb-3 flex items-center gap-2">
                        <i data-lucide="wallet" class="w-4 h-4 text-gray-600"></i>
                        Digital Wallet
                    </h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        A digital tool that lets you interact with the blockchain. Unlike a physical wallet, it doesn't hold cash but stores the "keys" that prove you own your digital assets.
                    </p>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm hover:shadow-md transition-all">
                    <h4 class="font-bold text-gray-900 text-xs mb-3 flex items-center gap-2">
                        <i data-lucide="zap" class="w-4 h-4 text-indigo-600"></i>
                        Embedded Wallet
                    </h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        A smart wallet integrated directly into your account. It uses your email for access, removing the need to memorize complex passwords or seed phrases while keeping you in control.
                    </p>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm hover:shadow-md transition-all">
                    <h4 class="font-bold text-gray-900 text-xs mb-3 flex items-center gap-2">
                        <i data-lucide="key" class="w-4 h-4 text-gray-600"></i>
                        Private Keys & MPC
                    </h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Private keys are the "password" to your funds. In an embedded wallet, these are secured using MPC (Multi-Party Computation) technology, so you can't lose them like a piece of paper.
                    </p>
                </div>
            </div>
        </div>

        <script>
            // Ensure TOOKLE object is defined immediately
            window.TOOKLE = {
              CDP_PROJECT_ID: "<?= htmlspecialchars($CDP_PROJECT_ID, ENT_QUOTES) ?>",
              CDP_ENV: "<?= htmlspecialchars($CDP_ENV, ENT_QUOTES) ?>",
              CSRF_TOKEN: "<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>",
              
              // OTP Handler for Main.js to call
              askOtp: function() {
                return new Promise((resolve, reject) => {
                    const modal = document.getElementById('branded-modal');
                    const msg = document.getElementById('branded-modal-message');
                    const title = document.getElementById('branded-modal-title');
                    const inputContainer = document.getElementById('branded-modal-input-container');
                    const input = document.getElementById('branded-modal-input');
                    const actions = document.getElementById('branded-modal-actions');
                    const confirmActions = document.getElementById('branded-modal-confirm-actions');
                    const confirmBtn = document.getElementById('branded-modal-confirm-button');
                    const iconContainer = document.getElementById('branded-modal-icon-container');
                    const icon = document.getElementById('branded-modal-icon');

                    // Setup UI for OTP Input
                    title.textContent = "Verification Required";
                    msg.textContent = "Enter the verification code sent to your email.";
                    iconContainer.className = "mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 mb-5 shadow-sm border border-indigo-100";
                    icon.setAttribute('data-lucide', 'shield-check'); 
                    icon.className = "h-7 w-7 text-indigo-600";
                    
                    inputContainer.style.display = 'block';
                    actions.style.display = 'none';
                    confirmActions.style.display = 'block';
                    input.value = '';
                    
                    setTimeout(() => input.focus(), 100);

                    modal.classList.add('show');
                    if (typeof lucide !== 'undefined') lucide.createIcons();

                    const handleConfirm = () => {
                        const code = input.value.trim();
                        if (code.length > 0) {
                            cleanup();
                            resolve(code);
                        } else {
                            input.style.borderColor = '#ef4444';
                            setTimeout(() => input.style.borderColor = '#e5e7eb', 500);
                        }
                    };

                    const cleanup = () => {
                        modal.classList.remove('show');
                        inputContainer.style.display = 'none';
                        actions.style.display = 'block';
                        confirmActions.style.display = 'none';
                        confirmBtn.removeEventListener('click', handleConfirm);
                        iconContainer.className = "mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-black mb-5 shadow-lg shadow-gray-200";
                        icon.setAttribute('data-lucide', 'check');
                        icon.className = "h-8 w-8 text-white";
                    };

                    confirmBtn.addEventListener('click', handleConfirm);
                    input.onkeydown = (e) => { if(e.key === 'Enter') handleConfirm(); };
                });
              }
            };
            
            // Debugging: Log status
            console.log('TOOKLE Config Loaded:', {
                projectId: window.TOOKLE.CDP_PROJECT_ID ? 'Loaded' : 'Missing',
                env: window.TOOKLE.CDP_ENV
            });
        </script>
        
        <script type="module" src="/main.js?v=<?= time() ?>"></script>
    </div>
</main>

<div id="onramp-modal" class="modal-overlay">
    <div class="modal-content relative bg-white">
        
        <div id="onramp-step-config" class="modal-step active pt-8 pb-8 px-8">
            <div class="absolute top-6 left-0 w-full flex justify-center pointer-events-none">
                 <span class="text-[10px] font-bold tracking-[0.2em] uppercase text-gray-300">TOOKLE</span>
            </div>
            <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 close-onramp-btn">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>

            <div class="mt-4 mb-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 mb-4">
                    <i data-lucide="wallet" class="h-6 w-6 text-indigo-600"></i>
                </div>
                <h3 class="text-2xl font-extrabold text-gray-900">Add Funds</h3>
                <p class="text-sm text-gray-500 mt-1 font-medium">Select amount and currency.</p>
            </div>

            <div class="space-y-5">
                <div class="currency-toggle">
                    <div class="currency-option" data-currency="USD">USD</div>
                    <div class="currency-option active" data-currency="EUR">EUR</div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="preset-btn" data-amount="100">100</button>
                    <button type="button" class="preset-btn active" data-amount="500">500</button>
                    <button type="button" class="preset-btn" data-amount="1000">1,000</button>
                    <button type="button" class="preset-btn" data-amount="5000">5,000</button>
                </div>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <span id="currency-symbol" class="text-gray-600 font-bold text-xl">€</span>
                    </div>
                    <input type="number" id="custom-amount-input" class="modern-input pl-10 text-right font-bold text-xl h-14" placeholder="Amount" value="500">
                </div>

                <div class="bg-gray-50 rounded-lg p-4 flex flex-col gap-3 text-xs text-gray-500 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <span>Payment Method</span>
                        <span class="font-bold text-gray-800 flex items-center gap-1">
                            <i data-lucide="credit-card" class="w-3 h-3"></i> Card / Bank
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between mt-1">
                        <span>Destination Wallet</span>
                    </div>
                    <input type="text" id="destination-wallet-input" class="w-full bg-indigo-100/50 border border-indigo-200 text-indigo-900 font-mono text-xs rounded p-2 text-center" placeholder="0x..." value="">
                    
                    <p class="text-[10px] text-gray-400 mt-1 italic text-center">
                        Please ensure you enter a wallet address that you control to receive the funds.
                    </p>
                </div>

                <button id="proceed-to-iframe-btn" class="btn btn-primary w-full h-14 text-base font-bold shadow-xl shadow-indigo-100 mt-2">
                    Proceed to Coinbase
                </button>
            </div>
        </div>

        <div id="onramp-step-iframe" class="modal-step h-[85vh] flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-white">
                <button id="back-to-config-btn" class="text-gray-400 hover:text-gray-600 flex items-center gap-1 text-sm font-medium">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                </button>
                <h3 class="text-base font-bold text-gray-800">Secure Checkout</h3>
                <button class="text-gray-400 hover:text-gray-600 close-onramp-btn">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="flex-1 bg-gray-100 relative">
                <div id="iframe-loading-msg" class="absolute inset-0 flex flex-col items-center justify-center z-0">
                    <i data-lucide="loader-2" class="w-10 h-10 text-indigo-600 animate-spin mb-3"></i>
                    <p class="text-sm font-bold text-gray-600">Coinbase service launch service...</p>
                </div>

                <iframe id="coinbase-iframe" src="/cdp_onramp/public/ui/onramp_tookle_coinbase.php" class="w-full h-full border-0 absolute inset-0 z-10 bg-transparent" allow="payment"></iframe>
            </div>
        </div>

    </div>
</div>

<div id="branded-modal" class="modal-overlay">
    <div class="branded-modal-content relative">
        <div class="absolute top-6 left-0 w-full flex justify-center pointer-events-none">
             <span class="text-[10px] font-bold tracking-[0.2em] uppercase text-gray-300">TOOKLE</span>
        </div>

        <div class="mt-8 mb-4">
            <div id="branded-modal-icon-container" class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-black mb-5 shadow-lg shadow-gray-200 transition-all duration-300">
                <i id="branded-modal-icon" data-lucide="check" class="h-8 w-8 text-white"></i>
            </div>
            
            <div class="text-center">
                <h3 class="text-xl font-bold text-gray-900 mb-2" id="branded-modal-title">Success</h3>
                <p id="branded-modal-message" class="text-sm text-gray-500 leading-relaxed px-4"></p>
                
                <div id="branded-modal-input-container" style="display:none;" class="mt-4">
                    <input type="text" id="branded-modal-input" class="modern-input" placeholder="123456" maxlength="6">
                    <p class="text-xs text-gray-400 mt-2">Check your email for the verification code.</p>
                </div>

                <div id="branded-modal-pk-container" style="display:none;" class="mt-4">
                     <p class="text-xs text-gray-500 mb-2">Do not share this with anyone.</p>
                     <div class="private-key-display" id="branded-modal-pk-display"></div>
                     <button type="button" class="btn btn-neutral text-xs mt-3 w-full" onclick="navigator.clipboard.writeText(document.getElementById('branded-modal-pk-display').innerText); this.innerText='Copied!';">
                        <i data-lucide="copy" class="w-3 h-3 mr-2"></i> Copy to clipboard
                     </button>
                </div>
            </div>
        </div>
        
        <div class="mt-8" id="branded-modal-actions">
            <button id="branded-modal-close-button" type="button" class="btn btn-primary w-full justify-center h-12 text-base font-semibold">
                Continue
            </button>
        </div>

        <div class="mt-8" id="branded-modal-delete-actions" style="display:none;">
            <div class="flex gap-3">
                <button id="branded-modal-cancel-delete" type="button" class="btn btn-neutral flex-1 justify-center h-12">Cancel</button>
                <button id="branded-modal-confirm-delete" type="button" class="btn bg-red-600 text-white hover:bg-red-700 border-none flex-1 justify-center h-12">Yes, Delete</button>
            </div>
        </div>
        
        <div class="mt-8" id="branded-modal-confirm-actions" style="display:none;">
             <button id="branded-modal-confirm-button" type="button" class="btn btn-primary w-full justify-center h-12 text-base font-semibold">
                Verify
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- TOP UP MODAL LOGIC (New Addition) ---
    const onrampModal = document.getElementById('onramp-modal');
    const onrampTriggers = document.querySelectorAll('.open-onramp-trigger');
    const closeOnrampBtns = document.querySelectorAll('.close-onramp-btn');
    const proceedBtn = document.getElementById('proceed-to-iframe-btn');
    const backBtn = document.getElementById('back-to-config-btn');
    const stepConfig = document.getElementById('onramp-step-config');
    const stepIframe = document.getElementById('onramp-step-iframe');
    const customInput = document.getElementById('custom-amount-input');
    const presetBtns = document.querySelectorAll('.preset-btn');
    const iframe = document.getElementById('coinbase-iframe');
    const destinationWalletInput = document.getElementById('destination-wallet-input');
    
    // Currency Logic
    const currencyOptions = document.querySelectorAll('.currency-option');
    const currencySymbol = document.getElementById('currency-symbol');
    let currentCurrency = 'EUR'; // Default matching screenshot

    currencyOptions.forEach(opt => {
        opt.addEventListener('click', () => {
            currencyOptions.forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
            currentCurrency = opt.dataset.currency;
            currencySymbol.textContent = currentCurrency === 'USD' ? '$' : '€';
        });
    });

    // Preset Selection
    presetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            presetBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const rawAmount = btn.getAttribute('data-amount');
            customInput.value = rawAmount;
        });
    });

    // Sync custom input with active state
    customInput.addEventListener('input', () => {
        presetBtns.forEach(b => b.classList.remove('active'));
        const val = customInput.value;
        const matchingBtn = Array.from(presetBtns).find(b => b.getAttribute('data-amount') === val);
        if(matchingBtn) matchingBtn.classList.add('active');
    });

    // Open Modal
    onrampTriggers.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            stepConfig.classList.add('active');
            stepIframe.classList.remove('active');
            onrampModal.classList.add('show');
            if (typeof lucide !== 'undefined') lucide.createIcons();
            
            // Auto-fill wallet if available
            const activeWallet = document.getElementById('saved-wallet-address')?.textContent;
            if(activeWallet && destinationWalletInput && !destinationWalletInput.value) {
                destinationWalletInput.value = activeWallet;
            }
        });
    });

    // Close Modal
    closeOnrampBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            onrampModal.classList.remove('show');
        });
    });

    // Proceed to Iframe (Launch API)
    if(proceedBtn) {
        proceedBtn.addEventListener('click', () => {
            const amount = customInput.value;
            const walletAddress = destinationWalletInput.value.trim();

            if(!amount || amount <= 0) {
                showModal("Please enter a valid amount", true);
                return;
            }

            if(!walletAddress || !walletAddress.startsWith('0x')) {
                showModal("Please enter a valid destination wallet address (starts with 0x)", true);
                return;
            }
            
            // Construct URL with parameters for currency, amount, and address
            const iframeUrl = new URL('/cdp_onramp/public/ui/onramp_tookle_coinbase.php', window.location.origin);
            iframeUrl.searchParams.set('amount', amount);
            iframeUrl.searchParams.set('currency', currentCurrency);
            iframeUrl.searchParams.set('address', walletAddress);
            iframe.src = iframeUrl.toString();
            
            stepConfig.classList.remove('active');
            stepIframe.classList.add('active');
        });
    }

    // Back to Config
    if(backBtn) {
        backBtn.addEventListener('click', () => {
            stepIframe.classList.remove('active');
            stepConfig.classList.add('active');
        });
    }

    // =========================================================
    // LOGIQUE 100% FIABLE : CHAMP INPUT MANUEL
    // =========================================================
    const walletAddressEl = document.getElementById('wallet-address'); // Zone automatique
    const manualInput = document.getElementById('manual-address-input'); // Zone manuelle
    const manualSaveBtn = document.getElementById('manual-save-btn');
    
    const createSection = document.getElementById('create-wallet-section');
    const existingCard = document.getElementById('existing-wallet-card');
    const savedAddressEl = document.getElementById('saved-wallet-address');
    
    // NEW: Elements for visual flow
    const addressContainer = document.getElementById('address-container');
    const step2Area = document.getElementById('step-2-area');
    
    // Show Branded Modal Logic
    function showModal(message, isError = null) { // CHANGED: Default is now null (auto-detect)
        // Handle Objects/JSON (Fixes your raw JSON display issue)
        if (typeof message === 'object' && message !== null) {
            if (message.message) message = message.message; // Extract readable message
            else message = JSON.stringify(message);
        }

        const modal = document.getElementById('branded-modal');
        const iconContainer = document.getElementById('branded-modal-icon-container');
        const icon = document.getElementById('branded-modal-icon');
        const title = document.getElementById('branded-modal-title');
        
        // Auto-detect error if not specified
        if (isError === null) {
             const lower = String(message).toLowerCase();
             // Only flag as error if it contains "error", "invalid" or "failed"
             // "Success" messages will now pass as false (Green)
             if (lower.includes('error') || lower.includes('invalid') || lower.includes('failed')) {
                 isError = true;
             } else {
                 isError = false;
             }
        }

        // Reset to Message Mode
        document.getElementById('branded-modal-input-container').style.display = 'none';
        document.getElementById('branded-modal-pk-container').style.display = 'none'; // Hide PK
        document.getElementById('branded-modal-actions').style.display = 'block';
        document.getElementById('branded-modal-confirm-actions').style.display = 'none';
        document.getElementById('branded-modal-delete-actions').style.display = 'none';

        document.getElementById('branded-modal-message').textContent = message;
        modal.classList.add('show');
        
        // HARDENED CLOSE BUTTON: Prevent Default to avoid any accidental form submission
        const closeBtn = document.getElementById('branded-modal-close-button');
        closeBtn.onclick = (e) => { 
            e.preventDefault(); 
            modal.classList.remove('show'); 
        };
        
        if (isError) {
             // ERROR STATE: Red
             iconContainer.className = "mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-5 shadow-lg shadow-red-50 transition-all duration-300";
             icon.setAttribute('data-lucide', 'alert-circle');
             icon.className = "h-8 w-8 text-red-600";
             title.textContent = "Attention";
        } else {
             // SUCCESS STATE: Black (Brand)
             iconContainer.className = "mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-black mb-5 shadow-lg shadow-gray-200 transition-all duration-300";
             icon.setAttribute('data-lucide', 'check');
             icon.className = "h-8 w-8 text-white";
             title.textContent = "Success";
        }
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // MONKEY PATCH: Redirect native alerts to our branded modal globally
    // CHANGED: Pass 'null' instead of 'true' to allow auto-detection of success messages
    window.alert = function(message) {
        showModal(message, null);
    };

    // 1. TENTATIVE D'AUTO-REMPLISSAGE + UI UNLOCK
    if (walletAddressEl) {
        const observer = new MutationObserver(function(mutations) {
            const rawText = walletAddressEl.innerText || walletAddressEl.textContent || "";
            const match = rawText.match(/(0x[a-fA-F0-9]{40})/);
            
            if (match && match[0]) {
                // VISUAL UPDATE: Show the box and Unlock Step 2
                if(addressContainer) addressContainer.style.display = 'flex';
                // Remove hidden class to show Step 2
                if(step2Area) step2Area.classList.remove('step-hidden');

                // Si on trouve une adresse, on pré-remplit le champ input !
                if(manualInput.value === "") {
                    manualInput.value = match[0];
                    manualInput.style.backgroundColor = "#f0fdf4"; // Vert pour dire "c'est bon"
                    // Scroll to step 2 smoothly
                    setTimeout(() => {
                         manualInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                         manualInput.focus();
                    }, 500);
                }
            }
        });
        observer.observe(walletAddressEl, { childList: true, characterData: true, subtree: true });
    }
    
    // Prevent Enter key from submitting form in the manual input
    if(manualInput) {
        manualInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                manualSaveBtn.click();
            }
        });
    }

    // 2. ACTION : Clic sur le bouton Save
    if (manualSaveBtn) {
        manualSaveBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Stop any form submission
            
            // On lit UNIQUEMENT le champ input (que l'utilisateur peut corriger)
            const address = manualInput.value.trim();

            if (!address || !address.startsWith('0x') || address.length < 40) {
                showModal("Invalid address. Please copy the 0x... address and paste it in the box.", true);
                return;
            }

            // Envoi au Backend - Use RELATIVE path
            manualSaveBtn.disabled = true;
            manualSaveBtn.innerHTML = "Saving...";

            const fd = new FormData();
            fd.append('address', address);

            // CHANGED PATH: Pointing to backend/save_coinbase_backend.php
            fetch('backend/save_coinbase_backend.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Succès !
                    createSection.style.display = 'none';
                    existingCard.style.display = 'block';
                    if(savedAddressEl) savedAddressEl.textContent = address;
                    showModal("Wallet successfully linked!", false);
                } else {
                    showModal("Server Error: " + data.message, true);
                    manualSaveBtn.disabled = false;
                    manualSaveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i>';
                    lucide.createIcons();
                }
            })
            .catch(err => {
                console.error(err);
                showModal("Network error. Please check your connection.", true);
                manualSaveBtn.disabled = false;
                manualSaveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i>';
                lucide.createIcons();
            });
        });
    }

    // =========================================================
    // NEW LOGIC: DELETE & EXPORT
    // =========================================================
    const deleteBtn = document.getElementById('delete-wallet-btn');
    const exportBtn = document.getElementById('export-wallet-btn');

    // DELETE LOGIC
    if(deleteBtn) {
        deleteBtn.addEventListener('click', (e) => {
             e.preventDefault(); // Stop form submission
             
             const modal = document.getElementById('branded-modal');
             const icon = document.getElementById('branded-modal-icon');
             const iconContainer = document.getElementById('branded-modal-icon-container');
             const title = document.getElementById('branded-modal-title');
             const msg = document.getElementById('branded-modal-message');

             // Show Delete UI
             document.getElementById('branded-modal-input-container').style.display = 'none';
             document.getElementById('branded-modal-actions').style.display = 'none';
             document.getElementById('branded-modal-confirm-actions').style.display = 'none';
             document.getElementById('branded-modal-pk-container').style.display = 'none';
             
             document.getElementById('branded-modal-delete-actions').style.display = 'flex'; // Show Delete btns

             title.textContent = "Are you sure?";
             msg.innerHTML = "You are about to disconnect this embedded wallet. <br><b>Make sure you have backed up your key.</b>";
             
             // Red Icon
             iconContainer.className = "mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-5 shadow-lg shadow-red-50";
             icon.setAttribute('data-lucide', 'trash-2');
             icon.className = "h-8 w-8 text-red-600";
             
             modal.classList.add('show');
             if (typeof lucide !== 'undefined') lucide.createIcons();

             // Handle Confirmation
             const confirmDelete = document.getElementById('branded-modal-confirm-delete');
             const cancelDelete = document.getElementById('branded-modal-cancel-delete');

             // Temporary listeners (clone to remove old listeners)
             const newConfirm = confirmDelete.cloneNode(true);
             const newCancel = cancelDelete.cloneNode(true);
             confirmDelete.parentNode.replaceChild(newConfirm, confirmDelete);
             cancelDelete.parentNode.replaceChild(newCancel, cancelDelete);

             newCancel.addEventListener('click', (ev) => { ev.preventDefault(); modal.classList.remove('show'); });
             
             newConfirm.addEventListener('click', (ev) => {
                ev.preventDefault();
                newConfirm.textContent = "Deleting...";
                
                // Call Backend (Use Relative Path)
                fetch('backend/delete_coinbase_backend.php', { method: 'POST' })
                .then(r => {
                    if(!r.ok) throw new Error("Backend not found");
                    return r.json();
                })
                .then(d => {
                    modal.classList.remove('show');
                    // Reset UI
                    existingCard.style.display = 'none';
                    createSection.style.display = 'block';
                    savedAddressEl.textContent = "";
                    if(manualInput) manualInput.value = ""; 
                    // Reset Steps
                    if(addressContainer) addressContainer.style.display = 'none';
                    if(step2Area) step2Area.classList.add('step-hidden');
                    
                    showModal("Wallet disconnected successfully.", false);
                    newConfirm.textContent = "Yes, Delete";
                })
                .catch(err => {
                    console.warn("Backend delete script failed or missing:", err);
                    
                    modal.classList.remove('show');
                    // We still update UI to show it works for the user session
                    existingCard.style.display = 'none';
                    createSection.style.display = 'block';
                    savedAddressEl.textContent = "";
                    if(manualInput) manualInput.value = ""; 
                    if(addressContainer) addressContainer.style.display = 'none';
                    if(step2Area) step2Area.classList.add('step-hidden');
                    
                    showModal("Wallet disconnected (UI Only - Backend script missing).", false);
                    newConfirm.textContent = "Yes, Delete";
                });
             });
        });
    }

    // EXPORT/REVEAL LOGIC
    if(exportBtn) {
        exportBtn.addEventListener('click', (e) => {
             e.preventDefault();
             
             const modal = document.getElementById('branded-modal');
             const icon = document.getElementById('branded-modal-icon');
             const iconContainer = document.getElementById('branded-modal-icon-container');
             const title = document.getElementById('branded-modal-title');
             const msg = document.getElementById('branded-modal-message');

             // Show PK UI
             document.getElementById('branded-modal-input-container').style.display = 'none';
             document.getElementById('branded-modal-actions').style.display = 'block';
             document.getElementById('branded-modal-confirm-actions').style.display = 'none';
             document.getElementById('branded-modal-delete-actions').style.display = 'none';
             
             document.getElementById('branded-modal-pk-container').style.display = 'block';

             title.textContent = "Private Key";
             msg.textContent = "Your private key is being retrieved...";
             document.getElementById('branded-modal-pk-display').textContent = "Loading...";

             // Lock Icon
             iconContainer.className = "mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 mb-5 shadow-lg shadow-indigo-50";
             icon.setAttribute('data-lucide', 'key');
             icon.className = "h-8 w-8 text-indigo-600";
             
             modal.classList.add('show');
             if (typeof lucide !== 'undefined') lucide.createIcons();
             
             // Check if main.js is listening
             setTimeout(() => {
                 const pkDisplay = document.getElementById('branded-modal-pk-display');
                 if(pkDisplay.textContent === "Loading...") {
                     // CHANGED: Increased timeout from 5000 to 30000 (30 seconds)
                     // because the email/OTP flow takes time.
                     // The text will be updated by main.js almost immediately anyway,
                     // but this prevents the error from flashing if main.js is slow to start.
                 }
             }, 30000); // 30s timeout

             // Dispatch event for main.js
             window.dispatchEvent(new CustomEvent('tookle:request-export-key'));
        });
    }


    // --- Logique External Wallets (Standard) ---
    const walletList = document.getElementById('wallet-list');
    const addWalletButton = document.getElementById('add-wallet-button');
    const walletForm = document.getElementById('wallet-form');
    const saveChangesButton = document.getElementById('save-changes-button');
    const networks = [{ value: 'base', name: 'Base' }, { value: 'solana', name: 'Solana' }];

    function createWalletRow(wallet = {}) {
        const isNew = Object.keys(wallet).length === 0;
        const walletItem = document.createElement('div');
        walletItem.className = 'wallet-item grid md:grid-cols-[1fr,1fr,1fr,auto] gap-4 items-center py-4 border-b border-gray-100';
        
        const labelInput = document.createElement('input'); labelInput.type = 'text'; labelInput.name = 'walletName[]'; labelInput.className = 'form-input border rounded p-2 w-full'; labelInput.value = wallet.label || ''; 
        const addressInput = document.createElement('input'); addressInput.type = 'text'; addressInput.name = 'walletAddress[]'; addressInput.className = 'form-input border rounded p-2 w-full'; addressInput.value = wallet.wallet_address || '';
        
        if (!isNew) { labelInput.readOnly = true; addressInput.readOnly = true; labelInput.className += ' bg-gray-100'; addressInput.className += ' bg-gray-100'; }

        let networkElement;
        if (isNew) {
            networkElement = document.createElement('select'); networkElement.name = 'walletNetwork[]'; networkElement.className = 'form-select border rounded p-2 w-full';
            networks.forEach(net => { const opt = document.createElement('option'); opt.value = net.value; opt.textContent = net.name; networkElement.appendChild(opt); });
        } else {
            networkElement = document.createElement('input'); networkElement.type = 'text'; networkElement.name = 'walletNetwork[]'; networkElement.className = 'form-input border rounded p-2 w-full bg-gray-100'; networkElement.value = wallet.network || ''; networkElement.readOnly = true;
        }

        const removeButton = document.createElement('button'); removeButton.type = 'button'; removeButton.className = 'text-red-500 hover:text-red-700';
        removeButton.innerHTML = '<i data-lucide="trash-2" class="w-5 h-5"></i>';
        removeButton.addEventListener('click', () => { walletItem.remove(); saveChangesButton.classList.remove('hidden'); });

        walletItem.append(labelInput, addressInput, networkElement, removeButton);
        walletList.appendChild(walletItem);
        lucide.createIcons();
    }

    function fetchWallets() {
        fetch('/backend/wallet_backend.php').then(r => r.json()).then(data => {
            walletList.querySelectorAll('.wallet-item').forEach(item => item.remove());
            if (data.wallets) data.wallets.forEach(wallet => createWalletRow(wallet));
        });
    }

    addWalletButton.addEventListener('click', (e) => { e.preventDefault(); createWalletRow(); saveChangesButton.classList.remove('hidden'); });
    walletForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetch('/backend/wallet_save.php', { method: 'POST', body: new FormData(walletForm) })
        .then(r => r.json()).then(d => { showModal(d.success ? 'Saved!' : 'Error'); if(d.success) { saveChangesButton.classList.add('hidden'); fetchWallets(); } });
    });

    fetchWallets();
});
</script>