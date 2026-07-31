<?php
/**
 * Page: Project Wallet Management
 * Filepath: /pages/projectwallet.php
 */

// --- SETUP & DEPENDENCIES ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pdo = require __DIR__ . '/../src/db.php'; 

// --- STATE DEFINITION ---
$founder_id = $_SESSION['user_id'] ?? null;
$project_id = $_SESSION['active_project_id'] ?? null;

// --- DATA FETCHING & ERROR HANDLING ---
$errorMessage = null;
if (!$project_id || !$founder_id) {
    $errorMessage = "No active project is selected. Please return to your <a href='" . get_url('dashboard') . "' class='text-purple-700 underline'>dashboard</a> and select a project to continue.";
}
?>
<style>
    /* Styles to match the button from the other pages */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        border-radius: 0.5rem;
        transition: all 0.2s ease-in-out;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* MATCH NAVIGATION GRADIENT */
    .btn-primary {
        background-image: linear-gradient(to right, var(--gradient-start), var(--gradient-mid), var(--gradient-end));
        background-size: 200% auto;
        color: white;
        border: none;
    }
    .btn-primary:hover {
        background-position: right center;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    /* Validation Styles */
    .input-group {
        display: flex;
        flex-direction: column;
    }
    .validation-msg {
        font-size: 0.75rem;
        margin-top: 0.25rem;
        height: 1rem; /* Reserve space */
    }
    .text-green-600 { color: #16a34a; }
    .text-red-600 { color: #dc2626; }
    /* Updated to target border-bottom specifically for the clean look */
    .border-green-500 { border-bottom-color: #22c55e !important; }
    .border-red-500 { border-bottom-color: #ef4444 !important; }
    
    .info-box ul {
        list-style-type: disc;
        padding-left: 1.5rem;
        margin-top: 0.5rem;
    }

    /* New Clean Input Style */
    .clean-input {
        background-color: transparent;
        border: none;
        border-bottom: 1px solid #e5e7eb; /* gray-200 */
        border-radius: 0;
        padding: 0.5rem 0;
        width: 100%;
        transition: border-color 0.2s;
    }
    .clean-input:focus {
        outline: none;
        border-bottom: 2px solid var(--theme-primary, #6D28D9);
        box-shadow: none;
    }
    /* Fix for select elements to align text */
    select.clean-input {
        padding-right: 2rem;
        cursor: pointer;
    }
    /* Read-only style for Token Sale column */
    .readonly-text {
        padding: 0.5rem 0;
        color: #6b7280; /* gray-500 */
        font-size: 0.875rem;
        border-bottom: 1px solid transparent; /* Keeps alignment but invisible */
    }
</style>
<main class="flex-1 overflow-y-auto p-8 md:p-12">
    <?php if ($errorMessage): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md" role="alert">
            <p class="font-bold">Action Required</p>
            <p><?php echo $errorMessage; ?></p>
        </div>
    <?php else: ?>
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Project Wallets</h1>
        <p class="mt-2 text-base text-gray-500">Manage the official crypto wallets for this specific project.</p>
    </header>

    <div class="bg-white border border-gray-200 p-5 mb-8 rounded-xl shadow-sm">
        <div class="flex items-start">
            <div class="bg-gray-100 p-2 rounded-lg mr-4 flex-shrink-0">
                <i data-lucide="shield-check" class="w-6 h-6 text-gray-900"></i>
            </div>
            <div>
                <h3 class="font-bold text-lg text-gray-900 mb-1">Secure Project Treasury</h3>
                <p class="text-gray-600 mb-3 text-sm">
                    Register the official wallets for your project. These wallets are used to securely receive funds from your private token sales.
                </p>
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-xs text-gray-700">
                    <div class="flex items-center mb-1 text-gray-900">
                        <i data-lucide="info" class="w-4 h-4 mr-1.5"></i>
                        <span class="font-bold">Important Guidelines:</span>
                    </div>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li>Always double-check your address. Blockchain transactions are irreversible.</li>
                        <li><strong>Recommendation:</strong> Use a multi-sig wallet (like Gnosis Safe) for enhanced institutional security.</li>
                        <li>Wallets currently linked to an active token sale are locked and cannot be edited or removed.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <form id="wallet-form">
            <div id="wallet-list">
                 <!-- Updated Grid: Added Token Sale column (1.5fr), removed Delete column -->
                 <div class="wallet-list-header hidden md:grid" style="grid-template-columns: 1fr 2fr 1fr 1.5fr; gap: 1rem; align-items: center; padding: 0 0.5rem 0.5rem 0.5rem; border-bottom: 1px solid var(--border-color);">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Label</span>
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Wallet Address</span>
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Network</span>
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Token Sale Usage</span>
                 </div>
                 <!-- Wallet items will be dynamically inserted here -->
            </div>
           
            <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between items-center">
                <button type="button" id="add-wallet-button" class="btn btn-neutral text-sm font-semibold shadow-sm"><i data-lucide="plus" class="w-4 h-4 mr-2"></i>Add New Wallet</button>
                <button type="submit" id="save-changes-button" class="btn bg-gray-900 text-white hover:bg-black hidden shadow-sm px-6">Save Changes</button>
            </div>
        </form>
    </div>
    
    <?php endif; ?>
</main>

<!-- Modal structure for notifications -->
<div id="custom-modal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease;">
    <div class="modal-content" style="background-color: white; padding: 2rem; border-radius: 1rem; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <p id="modal-message" class="mb-6 text-gray-700 font-medium"></p>
        <button id="modal-close-button" class="btn bg-gray-900 text-white hover:bg-black px-8">OK</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const walletList = document.getElementById('wallet-list');
    const addWalletButton = document.getElementById('add-wallet-button');
    const walletForm = document.getElementById('wallet-form');
    const saveChangesButton = document.getElementById('save-changes-button');
    
    // UPDATED: Restricting networks to Base and Solana only
    const networks = [
        { value: 'base', name: 'Base' },
        { value: 'solana', name: 'Solana' }
    ];

    // Regex patterns for validation
    const validators = {
        'base': {
            regex: /^0x[a-fA-F0-9]{40}$/,
            hint: 'Must start with 0x and be 42 characters long.'
        },
        'solana': {
            regex: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/,
            hint: 'Must be a valid Base58 address (32-44 chars).'
        }
    };

    function createWalletRow(wallet = {}) {
        const isUsed = !!wallet.token_sale_name;
        const walletItem = document.createElement('div');
        walletItem.className = `wallet-item grid md:grid-cols-[1fr,2fr,1fr,1.5fr] gap-4 items-start py-5 border-b border-gray-100 ${isUsed ? 'bg-slate-50/50 rounded-lg px-2 -mx-2 mb-2 border-none ring-1 ring-slate-100' : ''}`;

        // Helper to create an input
        const createInput = (name, placeholder, value, isAddress = false) => {
            const container = document.createElement('div');
            container.className = isAddress ? 'input-group relative' : 'input-group';
            
            const input = document.createElement('input');
            input.type = isAddress ? 'password' : 'text';
            input.name = name;
            input.placeholder = placeholder;
            input.className = isAddress ? 'clean-input pr-10 font-mono text-sm tracking-widest' : 'clean-input font-medium';
            input.value = value;
            if (isAddress) input.autocomplete = 'new-password'; // Prevent aggressive autofill
            
            if (isUsed) {
                input.readOnly = true;
                input.classList.add('bg-transparent', 'text-gray-500', 'cursor-not-allowed', 'border-transparent');
                input.classList.remove('clean-input');
            } else {
                input.addEventListener('input', () => saveChangesButton.classList.remove('hidden'));
            }
            
            container.appendChild(input);

            if (isAddress) {
                // Eye button toggle
                const eyeBtn = document.createElement('button');
                eyeBtn.type = 'button';
                eyeBtn.className = 'absolute right-3 top-[10px] text-gray-400 hover:text-gray-600 focus:outline-none bg-transparent';
                eyeBtn.innerHTML = '<i data-lucide="eye" class="w-4 h-4"></i>';
                
                eyeBtn.addEventListener('click', () => {
                    if (input.type === 'password') {
                        input.type = 'text';
                        input.classList.remove('tracking-widest');
                        eyeBtn.innerHTML = '<i data-lucide="eye-off" class="w-4 h-4"></i>';
                    } else {
                        input.type = 'password';
                        input.classList.add('tracking-widest');
                        eyeBtn.innerHTML = '<i data-lucide="eye" class="w-4 h-4"></i>';
                    }
                    if (window.lucide) window.lucide.createIcons();
                });
                
                container.appendChild(eyeBtn);

                const msg = document.createElement('span');
                msg.className = 'validation-msg block mt-1';
                container.appendChild(msg);
            }

            return { container, input };
        };

        const labelObj = createInput('walletName[]', 'e.g., Treasury', wallet.label || '');
        const addressObj = createInput('walletAddress[]', 'Enter Address', wallet.wallet_address || '', true);
        
        // Note Field is hidden
        const noteInputHidden = document.createElement('input');
        noteInputHidden.type = 'hidden';
        noteInputHidden.name = 'walletNote[]';
        noteInputHidden.value = wallet.note || '';

        // Create Network Dropdown
        const networkContainer = document.createElement('div');
        networkContainer.className = 'input-group';
        const networkElement = document.createElement('select');
        networkElement.name = 'walletNetwork[]';
        networkElement.className = isUsed ? 'bg-transparent text-gray-500 cursor-not-allowed border-none font-medium appearance-none pl-0' : 'clean-input font-medium';
        
        if (isUsed) {
            networkElement.disabled = true;
            // Add hidden input so it still submits
            const hiddenNetwork = document.createElement('input');
            hiddenNetwork.type = 'hidden';
            hiddenNetwork.name = 'walletNetwork[]';
            hiddenNetwork.value = wallet.network || 'base';
            networkContainer.appendChild(hiddenNetwork);
        }

        networks.forEach(net => {
            const option = document.createElement('option');
            option.value = net.value;
            option.textContent = net.name;
            if (wallet.network === net.value) {
                option.selected = true;
            }
            networkElement.appendChild(option);
        });
        networkContainer.appendChild(networkElement);

        // --- Token Sale Usage Column ---
        const usageContainer = document.createElement('div');
        usageContainer.className = 'input-group flex flex-row items-center justify-between gap-2 h-full';
        const usageText = document.createElement('div');
        usageText.className = 'readonly-text truncate flex items-center h-full border-none pt-0 pb-0';
        
        // Display usage if exists, otherwise "Unused"
        if (isUsed) {
             usageText.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-gray-100 text-gray-800 border border-gray-200 shadow-sm"><i data-lucide="lock" class="w-3 h-3 mr-1.5 text-gray-500"></i>${wallet.token_sale_name}</span>`;
        } else {
             usageText.innerHTML = `<span class="inline-flex items-center text-gray-400 italic text-sm">Unused</span>`;
        }
        usageContainer.appendChild(usageText);
        
        // If unused, add a remove button that clears the row
        if (!isUsed) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-gray-300 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50';
            removeBtn.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i>';
            removeBtn.title = "Remove wallet";
            removeBtn.addEventListener('click', () => {
                walletItem.remove();
                saveChangesButton.classList.remove('hidden');
            });
            usageContainer.appendChild(removeBtn);
        }

        // --- VALIDATION LOGIC ---
        const validateAddress = () => {
            const network = networkElement.value;
            const address = addressObj.input.value.trim();
            const msgElement = addressObj.container.querySelector('.validation-msg');
            const validator = validators[network];

            addressObj.input.classList.remove('border-green-500', 'border-red-500');
            msgElement.textContent = '';
            msgElement.className = 'validation-msg';

            if (address === '') return;

            if (validator && validator.regex.test(address)) {
                addressObj.input.classList.add('border-green-500');
                msgElement.textContent = '✓ Valid format';
                msgElement.classList.add('text-green-600');
            } else {
                addressObj.input.classList.add('border-red-500');
                msgElement.textContent = validator ? validator.hint : 'Invalid format';
                msgElement.classList.add('text-red-600');
            }
        };

        addressObj.input.addEventListener('input', validateAddress);
        networkElement.addEventListener('change', () => {
            saveChangesButton.classList.remove('hidden');
            validateAddress();
        });

        if (wallet.wallet_address) {
            setTimeout(validateAddress, 0);
        }

        // REMOVED: Delete Button logic has been removed entirely.

        walletItem.append(labelObj.container, addressObj.container, networkContainer, usageContainer, noteInputHidden);
        walletList.appendChild(walletItem);
    }

    function showModal(message) {
        const modal = document.getElementById('custom-modal');
        const messageEl = document.getElementById('modal-message');
        const closeButton = document.getElementById('modal-close-button');

        messageEl.textContent = message;
        modal.style.display = 'flex';
        setTimeout(() => { modal.style.opacity = '1'; modal.style.visibility = 'visible'; }, 10);
        
        const closeModal = () => {
            modal.style.opacity = '0';
            modal.style.visibility = 'hidden';
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        };
        
        closeButton.onclick = closeModal;
    }

    function fetchWallets() {
        fetch('/backend/projectwallet_backend.php')
            .then(response => response.ok ? response.json() : Promise.reject(response))
            .then(data => {
                if (data.error) throw new Error(data.error);
                
                walletList.querySelectorAll('.wallet-item').forEach(item => item.remove());
                
                if (data.wallets && data.wallets.length > 0) {
                    data.wallets.forEach(wallet => createWalletRow(wallet));
                }
            })
            .catch(error => {
                console.error("Error fetching project wallets:", error);
                showModal('Could not load your project wallet data. Please try again later.');
            });
    }

    addWalletButton.addEventListener('click', () => {
        createWalletRow();
        saveChangesButton.classList.remove('hidden');
    });

    walletForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const invalidInputs = document.querySelectorAll('.border-red-500');
        if (invalidInputs.length > 0) {
            showModal('Please correct the invalid wallet addresses before saving.');
            return;
        }

        const formData = new FormData(walletForm);
        
        fetch('/backend/projectwallet_backend.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModal(data.message || 'Wallets saved successfully!');
                saveChangesButton.classList.add('hidden');
                fetchWallets();
            } else {
                throw new Error(data.error || 'An unknown error occurred.');
            }
        })
        .catch(error => {
            console.error('Error saving wallets:', error);
            showModal(`Failed to save wallets: ${error.message}`);
        });
    });

    const errorMessageDiv = document.querySelector('.bg-yellow-100');
    if (!errorMessageDiv) {
        fetchWallets();
    }
});
</script>