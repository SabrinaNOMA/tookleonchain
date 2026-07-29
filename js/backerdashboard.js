/**
 * TOOKLE PROJECT - BACKER DASHBOARD SMART CONTRACT BRIDGE
 * * SENIOR ENGINEER UPDATE:
 * * 1. REVERT: Removed Strict Guard Clause on 'expectedWallet'.
 * * 2. LOGIC: Verification is now Contract-Centric.
 * * - Check balance of CURRENT connected wallet.
 * * - If Balance > 0: Allow Refund.
 * * - If Balance == 0: Trigger Speculative Self-Heal to see if THIS wallet already refunded.
 */

document.addEventListener('DOMContentLoaded', async () => {
    lucide.createIcons();

    // --- State ---
    let provider, signer, userAccount;
    let availableProviders = [];
    
    // ... (Keep existing State & Elements) ...
    // --- DOM Elements ---
    const dashboardContainer = document.getElementById('dashboard-container');
    const connectWalletBtn = document.getElementById('connect-wallet-btn-header');
    
    // KPI ELEMENTS
    const totalLockedEl = document.getElementById('total-locked-tokens');
    const totalClaimedEl = document.getElementById('total-claimed-tokens');
    const totalClaimableEl = document.getElementById('total-claimable-tokens');
    
    // MODAL ELEMENTS
    const claimModal = document.getElementById('claim-modal');
    const claimSuccessModal = document.getElementById('claim-success-modal');
    const successTxHashLink = document.getElementById('success-tx-hash-link');
    const closeSuccessModalBtn = document.getElementById('close-success-modal-btn');
    const cancelClaimBtn = document.querySelector('.modal-cancel-btn');
    const modalTitle = document.getElementById('modal-title');

    // ERROR MODAL ELEMENTS
    const errorModal = document.getElementById('error-modal');
    const errorModalMessage = document.getElementById('error-modal-message');
    const closeErrorModalBtn = document.getElementById('close-error-modal-btn');
    const closeErrorModalX = document.getElementById('close-error-modal-x');

    // REFUND ELEMENTS
    const refundDetailsSection = document.getElementById('refund-details-section');
    const refundDisplayAmount = document.getElementById('refund-display-amount');
    const executeRefundBtn = document.getElementById('execute-refund-btn');
    
    // VESTING ELEMENTS
    const vestingDetailsSection = document.getElementById('vesting-details-section');
    const claimModalIntro = document.getElementById('claim-modal-intro');
    const claimAmountInput = document.getElementById('claim-amount-input');
    const payFeeBtn = document.getElementById('pay-fee-btn');
    const claimTokensBtn = document.getElementById('claim-tokens-btn');
    const step1 = document.getElementById('step-1'); 
    const step2 = document.getElementById('step-2');

    // --- CONFIG ---
    const FEE_IN_USD = parseFloat(dashboardContainer?.dataset?.feeUsd || 1.0);
    const FEE_RECIPIENT = dashboardContainer?.dataset?.feeRecipient;
    const VESTING_CONTRACT_ADDRESS = "0xb5d78dd3276325f5faf3106cc4acc56e28e0fe3b";
    const BASE_CHAIN_ID = '8453'; 
    const BASE_CHAIN_ID_HEX = '0x2105'; 

    const numberFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });
    
    // --- ABIs ---
    const CAMPAIGN_ABI = [
        "function claimRefund() external",
        "function contributions(address) view returns (uint256)"
    ];
    const VESTING_ABI = [
        "function getDepositedAmount(uint256 streamId) view returns (uint128)",
        "function getWithdrawnAmount(uint256 streamId) view returns (uint128)",
        "function withdrawableAmountOf(uint256 streamId) view returns (uint128)",
        "function withdraw(uint256 streamId, address to, uint128 amount) payable"
    ];

    // --- UTILITIES ---
    function getNumericStreamId(streamId) {
        if (!streamId) return null;
        const strId = String(streamId);
        if (strId.includes('-')) {
            const parts = strId.split('-');
            return parts[parts.length - 1];
        }
        return strId;
    }

    function showError(message) {
        if (errorModal && errorModalMessage) {
            errorModalMessage.innerHTML = message; 
            errorModal.classList.remove('hidden');
            errorModal.classList.add('flex');
        } else alert(message);
    }

    function hideError() {
        if (errorModal) {
            errorModal.classList.add('hidden');
            errorModal.classList.remove('flex');
        }
    }

    async function getEthPrice() {
        try {
            const response = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=ethereum&vs_currencies=usd');
            const data = await response.json();
            return data.ethereum.usd;
        } catch { return 3500; }
    }

    function getFriendlyErrorMessage(error, context = {}) {
        if (typeof error === 'object' && error !== null) {
            if (error.code === 'INSUFFICIENT_FUNDS') return 'Funds insufficient for gas.';
            if (error.code === 'ACTION_REJECTED') return 'User rejected transaction.';
            
            if (error.message && (error.message.includes('estimateGas') || error.code === 'CALL_EXCEPTION')) {
                return `<b>On-chain Revert:</b> The Vault rejected the refund.<br><br>Connected Wallet: <code>${context.active?.slice(0,12)}...</code><br>Vault Address: <code>${context.vault?.slice(0,12)}...</code><br><br><b>Probable Cause:</b> This wallet has 0 balance in the vault. Either it was already refunded, or you are connected with the wrong wallet.`;
            }
            if (error.reason) return error.reason;
        }
        return error.message || 'Transaction failed.';
    }

    async function checkAndSwitchNetwork(providerInstance) {
        try {
            const network = await providerInstance.getNetwork();
            if (network.chainId.toString() !== BASE_CHAIN_ID) {
                await providerInstance.send('wallet_switchEthereumChain', [{ chainId: BASE_CHAIN_ID_HEX }]);
                return true;
            }
            return true;
        } catch { return false; }
    }

    async function requestWalletSwitch() {
        if (!window.ethereum) return;
        try {
            await window.ethereum.request({
                method: "wallet_requestPermissions",
                params: [{ eth_accounts: {} }]
            });
            await connectWallet();
        } catch (error) {
            console.error("Wallet switch cancelled or failed:", error);
        }
    }

    // --- DB SYNC HELPER (IMPROVED) ---
    async function syncRefundToDb(investmentId, txHash, attempt = 1) {
        try {
            const response = await fetch('/backend/record_refund.php', {
                method: 'POST',
                keepalive: true, 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ investment_id: investmentId, tx_hash: txHash })
            });
            
            if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.error || "Unknown Server Error");
            console.log("DB Sync Successful");
            return true;
        } catch (e) {
            console.warn(`DB Sync attempt ${attempt} failed:`, e);
            if (attempt < 3) {
                await new Promise(r => setTimeout(r, 1000 * Math.pow(2, attempt - 1)));
                return syncRefundToDb(investmentId, txHash, attempt + 1);
            }
            throw e;
        }
    }

    // --- TRIGGER FORCED SYNC FOR GHOST STATES (IMPL. SOLUTION 1) ---
    async function triggerSelfHeal(investmentId, walletAddr) {
        console.log(`Triggering Self-Heal for Inv #${investmentId} with wallet ${walletAddr}...`);
        try {
            const res = await fetch('/backend/heal_refund.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ investment_id: investmentId, wallet_address: walletAddr })
            });
            const data = await res.json();
            if (data.success && data.healed) {
                console.log("Self-Heal Successful: Found TX and updated DB.");
                // SUCCESS: Reload to reflect changes in UI
                window.location.reload(); 
                return true;
            } else {
                // If healing failed (TX not found), it likely means this wallet is NOT the payment wallet.
                // We do NOT update the UI to "Refunded" in this case.
                console.warn("Self-Heal: Backend could not find TX for this wallet.", data.error);
                return false; 
            }
        } catch (e) {
            console.error("Self-Heal Failed:", e);
            return false;
        }
    }

    // --- WALLET FUNCTIONS ---
    window.addEventListener('wallet-providers-updated', (event) => { availableProviders = event.detail.providers; });

    async function connectWallet() {
        let selectedProvider = availableProviders.length > 0 ? availableProviders[0].provider : window.ethereum;
        if (!selectedProvider) return showError("No Web3 wallet detected.");

        try {
            provider = new ethers.BrowserProvider(selectedProvider);
            await provider.send("eth_requestAccounts", []);
            
            if (!(await checkAndSwitchNetwork(provider))) {
                showError("Switch to Base network.");
                return;
            }

            signer = await provider.getSigner();
            userAccount = await signer.getAddress();
            
            window.isWalletConnected = true; 
            updateHeaderButton(true);
            updateDashboardState(userAccount); 
            verifyRefundStatuses(userAccount); 
        } catch (e) {
            console.error("Connection Error:", e);
            window.isWalletConnected = false;
            updateHeaderButton(false);
        }
    }

    function updateHeaderButton(connected) {
        if (connected && userAccount) {
            const short = userAccount.slice(0, 6) + '...' + userAccount.slice(-4);
            connectWalletBtn.innerHTML = `<div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div><span class="font-mono">${short}</span>`;
            connectWalletBtn.className = "inline-flex items-center px-4 py-2 border border-green-200 bg-green-50 text-green-700 text-sm font-medium rounded-lg";
        } else {
            connectWalletBtn.innerHTML = `<i data-lucide="wallet" class="w-4 h-4 mr-2 text-gray-400"></i>Connect Wallet`;
        }
    }

    // --- LIVE REFUND VERIFICATION & SELF-HEALING (UPDATED) ---
    async function verifyRefundStatuses(currentAccount) {
        const refundButtons = document.querySelectorAll('.claim-refund-btn');
        if (refundButtons.length === 0) return;

        console.log("Verifying Refund Statuses on-chain...");

        for (const btn of refundButtons) {
            const contractAddr = btn.dataset.contract;
            const invId = btn.dataset.investmentId;
            // Note: We ignore dataset.wallet for verification now, as it refers to Vesting wallet.

            try {
                const campaignContract = new ethers.Contract(contractAddr, CAMPAIGN_ABI, provider);
                const balance = await campaignContract.contributions(currentAccount);
                
                if (balance > 0n) {
                    // CASE 1: Balance Found
                    // This wallet definitely paid and has not been refunded.
                    // Enable the button for this specific wallet.
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    btn.disabled = false;
                    btn.innerText = "Refund";
                    btn.onclick = null; 
                    
                } else {
                    // CASE 2: Zero Balance
                    // Possibility A: This wallet never contributed.
                    // Possibility B: This wallet contributed AND was already refunded (but DB missed it).
                    console.warn(`Inv #${invId}: Balance is 0. Checking if this wallet refunded...`);
                    
                    // Trigger Background Check
                    // Even if DB has a wallet address, if balance is 0, we check for a refund event.
                    const healed = await triggerSelfHeal(invId, currentAccount);

                    if (healed) {
                        // If backend found a refund TX for THIS wallet, it reloads page.
                        // Code stops here due to reload.
                    } else {
                        // If backend did NOT find a refund TX for this wallet, 
                        // it means this is likely the WRONG wallet (Possibility A).
                        // We do NOT hide the button, but we indicate mismatch.
                        btn.innerText = "Wrong Wallet?";
                        btn.classList.add('opacity-50', 'cursor-not-allowed');
                        // Optional: Add tooltip or click to explain
                        btn.onclick = () => alert("This connected wallet has 0 balance and no refund history for this contract. Please connect the wallet you used for payment.");
                    }
                }
            } catch (err) {
                console.error("Failed to verify refund status:", err);
            }
        }
    }

    async function updateDashboardState(currentAccount) {
        if (!provider) return;
        const buttons = document.querySelectorAll('.claim-vesting-btn');
        const contract = new ethers.Contract(VESTING_CONTRACT_ADDRESS, VESTING_ABI, provider);

        // ... (Vesting logic remains same, removed for brevity but assumed present) ...
        // Keeping original logic structure
        let globalClaimable = 0;
        let globalClaimed = 0;
        let globalLocked = 0;

        for (const btn of buttons) {
            const rawStreamId = btn.dataset.streamId;
            const streamId = getNumericStreamId(rawStreamId);
            const requiredWallet = btn.dataset.recipientWallet;
            const decimals = parseInt(btn.dataset.tokenDecimals || 18); 
            
            const row = btn.closest('.historical-item-row');
            const rowVestedEl = row?.querySelector('.live-locked');
            const rowClaimedEl = row?.querySelector('.live-claimed');
            const rowClaimableContainer = row?.querySelector('.live-claimable-container');
            const rowClaimableVal = row?.querySelector('.live-claimable');

            if (requiredWallet && currentAccount.toLowerCase() !== requiredWallet.toLowerCase()) {
                btn.classList.add('hidden');
                continue; 
            }

            try {
                const [withdrawable, withdrawn, totalInStream] = await Promise.all([
                    contract.withdrawableAmountOf(streamId),
                    contract.getWithdrawnAmount(streamId),
                    contract.getDepositedAmount(streamId)
                ]);

                const withdrawableFormatted = parseFloat(ethers.formatUnits(withdrawable, decimals));
                const withdrawnFormatted = parseFloat(ethers.formatUnits(withdrawn, decimals));
                const depositedFormatted = parseFloat(ethers.formatUnits(totalInStream, decimals));
                const lockedFormatted = depositedFormatted - withdrawnFormatted - withdrawableFormatted;

                if (rowClaimedEl) rowClaimedEl.textContent = numberFormatter.format(withdrawnFormatted);
                if (rowVestedEl) rowVestedEl.textContent = numberFormatter.format(lockedFormatted);

                globalClaimable += withdrawableFormatted;
                globalClaimed += withdrawnFormatted;
                globalLocked += (depositedFormatted - withdrawnFormatted);

                if (withdrawable == 0n) {
                    btn.classList.add('hidden');
                    if (rowClaimableContainer) rowClaimableContainer.classList.add('hidden');
                } else {
                    btn.classList.remove('hidden');
                    btn.onclick = (e) => openClaimModal(e, streamId, withdrawableFormatted, decimals);
                    if (rowClaimableContainer) {
                        rowClaimableContainer.classList.remove('hidden');
                        if (rowClaimableVal) rowClaimableVal.textContent = numberFormatter.format(withdrawableFormatted);
                    }
                }
            } catch (e) { console.error("Stream update error:", e); }
        }

        if (totalClaimableEl) totalClaimableEl.textContent = numberFormatter.format(globalClaimable);
        if (totalClaimedEl) totalClaimedEl.textContent = numberFormatter.format(globalClaimed);
        if (totalLockedEl) totalLockedEl.textContent = numberFormatter.format(globalLocked);
    }

    // --- VESTING CLAIM LOGIC ---
    let activeStreamId = null;
    let activeDecimals = 18;

    function openClaimModal(e, streamId, amount, decimals) {
        e.preventDefault();
        modalTitle.textContent = 'Claim Tokens';
        refundDetailsSection.classList.add('hidden');
        executeRefundBtn.classList.add('hidden');
        vestingDetailsSection.classList.remove('hidden');
        activeStreamId = streamId;
        activeDecimals = decimals;
        claimAmountInput.value = amount; 
        claimModalIntro.textContent = `You have ${numberFormatter.format(amount)} tokens available to claim.`;
        step1.classList.remove('bg-emerald-50', 'border-emerald-200');
        step1.querySelector('.step-status').classList.add('hidden');
        payFeeBtn.disabled = false;
        payFeeBtn.textContent = 'Pay Fee';
        step2.classList.remove('bg-white', 'border-emerald-200');
        step2.classList.add('bg-gray-50');
        claimTokensBtn.disabled = true;
        claimModal.classList.remove('hidden');
        claimModal.classList.add('flex');
        setTimeout(() => claimModal.classList.remove('opacity-0', 'pointer-events-none'), 10);
    }

    // --- FEE & CLAIM EVENT LISTENERS (Same as original) ---
    payFeeBtn.addEventListener('click', async () => {
        if (!signer) return;
        payFeeBtn.textContent = 'Processing...';
        payFeeBtn.disabled = true;
        try {
            const feeRecipient = FEE_RECIPIENT || '0x2F8039cD25814C3987Dd3d4d547bFDd5B83e357E';
            const ethPrice = await getEthPrice();
            const feeEth = (FEE_IN_USD / ethPrice).toFixed(18);
            const tx = await signer.sendTransaction({
                to: feeRecipient,
                value: ethers.parseEther(feeEth)
            });
            await tx.wait();
            step1.classList.add('bg-emerald-50', 'border-emerald-200');
            step1.querySelector('.step-status').classList.remove('hidden');
            payFeeBtn.textContent = 'Paid';
            step2.classList.remove('bg-gray-50');
            step2.classList.add('bg-white', 'border-emerald-200');
            claimTokensBtn.disabled = false;
            claimTokensBtn.classList.remove('bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
            claimTokensBtn.classList.add('bg-emerald-600', 'text-white', 'hover:bg-emerald-700');
        } catch (error) {
            payFeeBtn.disabled = false;
            payFeeBtn.textContent = 'Retry Fee';
            showError(getFriendlyErrorMessage(error));
        }
    });

    claimTokensBtn.addEventListener('click', async () => {
        if (!signer || !activeStreamId) return;
        claimTokensBtn.textContent = 'Claiming...';
        claimTokensBtn.disabled = true;
        try {
            const contract = new ethers.Contract(VESTING_CONTRACT_ADDRESS, VESTING_ABI, signer);
            const amountToWithdraw = ethers.parseUnits(claimAmountInput.value, activeDecimals);
            const tx = await contract.withdraw(activeStreamId, userAccount, amountToWithdraw);
            const receipt = await tx.wait();
            if (receipt.status === 1) {
                showSuccess(receipt.hash);
            } else throw new Error("Transaction failed on-chain");
        } catch (error) {
            claimTokensBtn.disabled = false;
            claimTokensBtn.textContent = 'Retry Claim';
            showError(getFriendlyErrorMessage(error));
        }
    });

    // --- REFUND LOGIC (UPDATED) ---
    async function executeRefund() {
        if (!window.ethereum) return showError("No wallet detected.");
        
        try {
            const freshProvider = new ethers.BrowserProvider(window.ethereum);
            const freshSigner = await freshProvider.getSigner();
            const activeAddr = (await freshSigner.getAddress()).toLowerCase();

            const row = document.querySelector(`.claim-refund-btn[data-investment-id="${currentInvestmentId}"]`);
            // REMOVED expectedWallet CHECK here too. Rely on contract balance check.

            executeRefundBtn.disabled = true;
            executeRefundBtn.innerText = "Verifying on Chain...";

            const campaignContract = new ethers.Contract(currentEscrowAddress, CAMPAIGN_ABI, freshSigner);
            
            // 2. CHECK BALANCE BEFORE GAS ESTIMATE
            const balance = await campaignContract.contributions(activeAddr);
            if (balance == 0n) {
                // If we got here via click, it means UI thought we could refund, 
                // but contract says NO. This catches the mismatch right before execution.
                throw {
                    message: "Zero Balance",
                    reason: "This specific wallet has 0 contribution balance. Please ensure you are connected with the wallet that originally funded this investment."
                };
            }

            // 3. Estimate Gas
            try {
                await campaignContract.claimRefund.estimateGas();
            } catch (gasErr) {
                console.error("Contract level revert detected:", gasErr);
                throw gasErr; 
            }

            executeRefundBtn.innerText = "Confirm in Wallet...";
            const tx = await campaignContract.claimRefund();
            
            // IMPORTANT: Optimistically allow the user to see progress
            executeRefundBtn.innerText = "Processing...";
            const receipt = await tx.wait();
            
            if (receipt.status === 1) {
                // 4. Force DB Update with Retries AND Keepalive
                executeRefundBtn.innerText = "Saving to DB...";
                
                // We await this, but even if user navigates away, 'keepalive' inside syncRefundToDb helps.
                await syncRefundToDb(currentInvestmentId, receipt.hash);
                
                showSuccess(receipt.hash);
            } else throw new Error("On-chain transaction failure.");

        } catch (error) {
            executeRefundBtn.disabled = false;
            executeRefundBtn.textContent = 'Retry Refund';
            showError(getFriendlyErrorMessage(error, { vault: currentEscrowAddress, active: userAccount }));
        }
    }

    function showSuccess(hash) {
        claimModal.classList.add('hidden');
        if(successTxHashLink) {
            successTxHashLink.textContent = `TX: ${hash.substring(0, 15)}...`;
            successTxHashLink.href = `https://basescan.org/tx/${hash}`;
        }
        if(claimSuccessModal) {
            claimSuccessModal.classList.remove('hidden');
            claimSuccessModal.classList.add('flex');
        }
        // Increased delay slightly to allow user to read success before reload
        setTimeout(() => window.location.reload(), 4000);
    }

    document.body.addEventListener('click', async (e) => {
        const refundBtn = e.target.closest('.claim-refund-btn');
        if (refundBtn) {
            e.preventDefault();
            if (!window.isWalletConnected) await connectWallet();
            
            currentInvestmentId = refundBtn.dataset.investmentId;
            currentEscrowAddress = refundBtn.dataset.contract;
            
            modalTitle.textContent = 'Withdraw Refund';
            vestingDetailsSection.classList.add('hidden');
            refundDetailsSection.classList.remove('hidden');
            executeRefundBtn.classList.remove('hidden');
            refundDisplayAmount.textContent = `$${parseFloat(refundBtn.dataset.amount).toFixed(2)}`;

            claimModal.classList.remove('hidden');
            claimModal.classList.add('flex'); 
            setTimeout(() => claimModal.classList.remove('opacity-0', 'pointer-events-none'), 10);
        }
    });

    if (executeRefundBtn) executeRefundBtn.addEventListener('click', executeRefund);
    if (connectWalletBtn) connectWalletBtn.addEventListener('click', connectWallet);
    if (cancelClaimBtn) cancelClaimBtn.addEventListener('click', () => {
        claimModal.classList.add('opacity-0', 'pointer-events-none');
        setTimeout(() => {
            claimModal.classList.add('hidden');
            claimModal.classList.remove('flex');
        }, 200);
    });

    if (closeErrorModalBtn) closeErrorModalBtn.addEventListener('click', hideError);
    if (closeErrorModalX) closeErrorModalX.addEventListener('click', hideError);

    if (window.ethereum) {
        window.ethereum.request({ method: 'eth_accounts' })
            .then(a => { if(a.length) connectWallet(); })
            .catch(console.error);
    }
});