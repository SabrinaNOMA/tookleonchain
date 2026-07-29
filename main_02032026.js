// ... existing code ...
async function wE(e){let t=await fetch("/save_wallet_address.php",{method:"POST",headers:{"Content-Type":"application/json"},credentials:"same-origin",body:JSON.stringify({walletAddress:e,csrf_token:hE})}),r=await t.json().catch(()=>({}));if(!t.ok)throw new Error(r.error||"Erreur serveur");return r}yE?.addEventListener("click",async()=>{try{Eo("Reset session\u2026"),await _b(),Eo("Connexion\u2026"),await bE(),Eo("Provision du wallet\u2026"),await gE(),Eo("R\xE9cup\xE9ration de l\u2019adresse\u2026");let e=await xE();Eo(`Adresse wallet : ${e}`);let t=await wE(e);console.log("Adresse enregistr\xE9e c\xF4t\xE9 serveur :",t)}catch(e){console.error(e),alert(e?.message||"Erreur inconnue"),Eo("Erreur \u2014 voir console.")}});(async()=>{try{console.log("isSignedIn ?",await vs())}catch{}})();

// --- NEW: Handle Export Private Key Request ---
// Moved to global scope to ensure it captures the event
window.addEventListener('tookle:request-export-key', async () => {
    console.log("[Main.js] Received request-export-key event");
    const display = document.getElementById('branded-modal-pk-display');
    if (!display) {
        console.error("[Main.js] Display element #branded-modal-pk-display not found");
        return;
    }

    // 1. IMMEDIATE UI UPDATE:
    // This overwrites "Loading..." so the wallet.php 5s timeout doesn't show the error.
    display.innerHTML = '<span class="text-gray-500">Initializing secure session...</span>';

    try {
        // 2. Check if bE (login function) is available
        if (typeof bE === 'undefined') {
            throw new Error("Internal Error: Login function not found.");
        }

        // 3. Force Re-authentication (Security Step)
        console.log("[Main.js] Triggering Login Flow (bE)...");
        
        // Wait briefly to let the UI update render
        await new Promise(r => setTimeout(r, 100));

        // Call bE() - The bundled login function
        // This triggers: SignOut -> Prompt Email -> Prompt OTP -> SignIn
        // Use try/catch to handle user cancellation (e.g. cancelling prompt)
        try {
            await bE();
        } catch (loginError) {
            console.error("[Main.js] Login flow failed:", loginError);
            throw new Error("Login failed or cancelled.");
        }

        // 4. Update UI
        display.innerHTML = '<span class="text-gray-500">Retrieving Secret...</span>';

        // 5. Get Auth Manager (Retry Logic)
        let auth;
        let retries = 0;
        // Retry loop to allow SDK to sync auth state after login
        while (retries < 10) {
            try {
                auth = ne(); // Get Auth Manager instance
                // Verify we are actually signed in
                const signedIn = await auth.isSignedIn();
                if (signedIn) break;
            } catch (err) {
                console.warn("[Main.js] Waiting for Auth...", err);
                
                // If SDK is lost, try to re-init
                if(retries === 5) {
                    console.log("[Main.js] Re-initializing SDK...");
                    await Qf({projectId: Cb, environment: mE}).catch(e=>console.error(e));
                }
            }
            await new Promise(r => setTimeout(r, 500));
            retries++;
        }

        if (!auth || !(await auth.isSignedIn())) {
            throw new Error("Authentication failed. Please try again.");
        }

        // 6. Retrieve Secret ID
        console.log("[Main.js] Fetching Secret ID...");
        const secretId = await auth.getWalletSecretId();
        
        console.log("[Main.js] Secret ID retrieved successfully");

        // 7. Final Display
        display.innerHTML = `
            <div style="word-break: break-all;">
                <span class="text-indigo-600 font-bold">Device Wallet Secret ID:</span><br>
                <span class="font-mono text-xs select-all bg-gray-100 p-1 rounded">${secretId}</span>
            </div>
            <br>
            <div class="p-2 bg-yellow-50 border border-yellow-100 rounded text-xs text-yellow-700">
                <strong>Note:</strong> This wallet uses <strong>MPC (Multi-Party Computation)</strong>. 
                The raw Private Key is never fully reconstructed on the device for security.
                This ID authorizes your specific device to sign transactions.
            </div>
        `;

    } catch (e) {
        console.error("[Main.js] Export Key Error:", e);
        display.style.color = '#dc2626'; // red-600
        display.style.fontWeight = '600';
        display.textContent = "Error: " + (e.message || "Could not retrieve key info.");
    }
});

console.log("[Main.js] Listener for 'tookle:request-export-key' attached.");

/*! Bundled license information:

@noble/hashes/esm/utils.js:
  (*! noble-hashes - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/utils.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/abstract/modular.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/abstract/curve.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/abstract/weierstrass.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/_shortw_utils.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/secp256k1.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@coinbase/cdp-core/dist/esm/index76.js:
  (*! noble-hashes - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/utils.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)
*/