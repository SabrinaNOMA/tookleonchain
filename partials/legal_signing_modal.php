<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<?php $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'; ?>

<div id="legal-signing-modal" class="fixed inset-0 z-50 hidden bg-slate-900/95 backdrop-blur-sm flex items-center justify-center p-4 font-sans animate-in fade-in duration-300">
    <div class="bg-white w-full max-w-[1600px] h-[95vh] rounded-xl shadow-2xl flex flex-col overflow-hidden border border-gray-200">
        
        <!-- HEADER -->
        <div class="bg-white text-gray-900 h-16 flex items-center justify-between px-6 shrink-0 shadow-sm border-b border-gray-200 z-20">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 text-gray-700">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <span class="text-xs font-bold uppercase tracking-widest">Secure Space</span>
                </div>
                <div class="h-4 w-px bg-gray-300"></div>
                <h2 class="text-sm font-semibold text-gray-700">Agreement Ref. #<?php echo substr($_SESSION['csrf_token'] ?? 'SECURE', 0, 8); ?></h2>
            </div>
            <button onclick="closeLegalModal()" class="text-gray-500 hover:text-gray-900 transition-colors p-2 rounded-full hover:bg-gray-100">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="flex-1 flex overflow-hidden">
            <!-- LEFT PANEL: SIGNATORY DESK -->
            <div class="w-[450px] bg-white border-r border-gray-200 flex flex-col shrink-0 z-10 relative shadow-[4px_0_24px_rgba(0,0,0,0.02)]">
                
                <div id="legal-input-panel" class="flex flex-col h-full bg-white transition-opacity duration-300">
                    <div class="p-8 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2 mb-1">
                            <i data-lucide="pen-tool" class="w-5 h-5 text-gray-900"></i> Signatory Details
                        </h3>
                        <p class="text-xs text-gray-500 font-medium">Please complete the details and affirmations below.</p>
                    </div>

                    <div class="flex-1 overflow-y-auto p-8 space-y-6">
                        
                        <!-- SECTION 1: IDENTITY -->
                        <div class="space-y-4">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b pb-2">1. Identity & Address</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">First Name</label><input type="text" id="signer-fname" class="signer-input w-full p-3 bg-gray-50 border rounded-lg text-sm font-medium focus:border-gray-900 focus:ring-0" placeholder="John"></div>
                                <div><label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Last Name</label><input type="text" id="signer-lname" class="signer-input w-full p-3 bg-gray-50 border rounded-lg text-sm font-medium focus:border-gray-900 focus:ring-0" placeholder="Doe"></div>
                            </div>
                            <div><label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Email</label><input type="email" id="signer-email" class="signer-input w-full p-3 bg-gray-50 border rounded-lg text-sm font-medium focus:border-gray-900 focus:ring-0" placeholder="john@google.com"></div>
                            <div><label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Street</label><input type="text" id="signer-address" class="signer-input w-full p-3 bg-gray-50 border rounded-lg text-sm font-medium focus:border-gray-900 focus:ring-0" placeholder="123 Blockchain Blvd"></div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">City</label><input type="text" id="signer-city" class="signer-input w-full p-3 bg-gray-50 border rounded-lg text-sm font-medium focus:border-gray-900 focus:ring-0" placeholder="Lausanne"></div>
                                <div><label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Zip</label><input type="text" id="signer-zip" class="signer-input w-full p-3 bg-gray-50 border rounded-lg text-sm font-medium focus:border-gray-900 focus:ring-0" placeholder="1005"></div>
                            </div>
                        </div>

                        <!-- SECTION 2: BINDING DECLARATIONS -->
                        <div class="space-y-4 pt-4 border-t border-gray-100">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-1">2. Final Declarations</h4>
                            
                            <!-- Notice 1: Identity & Non-Custodial Vault -->
                            <label for="decl-identity-vault" class="group flex items-start gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer hover:bg-white hover:border-gray-900 hover:shadow-md transition-all">
                                <div class="relative flex items-center mt-0.5">
                                    <input type="checkbox" id="decl-identity-vault" class="decl-checkbox w-5 h-5 text-gray-900 border-gray-300 rounded focus:ring-gray-900 cursor-pointer">
                                </div>
                                <div class="flex-1">
                                    <p class="text-[11px] font-bold text-gray-900 leading-tight">Identity & Asset Custody</p>
                                    <p class="text-[10px] text-gray-500 mt-1 leading-snug">I am the authorized signer and acknowledge that all funds are held in a non-custodial Vault infrastructure.</p>
                                </div>
                            </label>

                            <!-- Notice 2: Agreement Acceptance -->
                            <label for="decl-agreement" class="group flex items-start gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer hover:bg-white hover:border-gray-900 hover:shadow-md transition-all">
                                <div class="relative flex items-center mt-0.5">
                                    <input type="checkbox" id="decl-agreement" class="decl-checkbox w-5 h-5 text-gray-900 border-gray-300 rounded focus:ring-gray-900 cursor-pointer">
                                </div>
                                <div class="flex-1">
                                    <p class="text-[11px] font-bold text-gray-900 leading-tight">Agreement Acceptance</p>
                                    <p class="text-[10px] text-gray-500 mt-1 leading-snug">I have reviewed the Token Sale Agreement and agree to be bound by its full terms and conditions.</p>
                                </div>
                            </label>

                            <!-- Notice 3: Transaction Binding Confirmation -->
                            <label for="decl-binding" class="group flex items-start gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer hover:bg-white hover:border-gray-900 hover:shadow-md transition-all">
                                <div class="relative flex items-center mt-0.5">
                                    <input type="checkbox" id="decl-binding" class="decl-checkbox w-5 h-5 text-gray-900 border-gray-300 rounded focus:ring-gray-900 cursor-pointer">
                                </div>
                                <div class="flex-1">
                                    <p class="text-[11px] font-bold text-gray-900 leading-tight">Binding Confirmation</p>
                                    <p class="text-[10px] text-gray-500 mt-1 leading-snug">I confirm this agreement becomes legally binding upon successful confirmation of the transaction (tx) in the Vault.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="p-8 border-t bg-gray-50">
                        <button id="legal-sign-btn" disabled class="w-full py-4 bg-[#111827] text-white font-bold rounded-xl shadow-lg hover:bg-black transition-all disabled:opacity-50 flex items-center justify-center gap-2 text-sm uppercase tracking-wide">
                            <i data-lucide="pen-line" class="w-4 h-4"></i> Sign & Seal Document
                        </button>
                        <p class="text-[10px] text-center text-gray-400 mt-4"><i data-lucide="lock" class="inline w-3 h-3 mr-1"></i> 256-bit Encrypted Timestamp • IP Logged</p>
                    </div>
                </div>

                <!-- SUCCESS SCREEN OVERLAY -->
                <div id="legal-success-panel" class="absolute inset-0 bg-white z-50 flex flex-col items-center justify-center p-10 text-center hidden animate-in fade-in zoom-in duration-300">
                    <div class="w-24 h-24 bg-green-50 rounded-full flex items-center justify-center mb-6 border border-green-100 animate-bounce shadow-sm">
                        <i data-lucide="check" class="w-12 h-12 text-green-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Successfully Signed</h3>
                    <p class="text-sm text-gray-500 mb-10 max-w-xs leading-relaxed">The contract has been legally sealed. Please download your copy before continuing.</p>
                    
                    <div class="w-full space-y-4">
                        <button id="success-download-btn" onclick="downloadSignedPDF()" class="w-full py-4 bg-gray-100 text-gray-900 font-bold rounded-xl border border-gray-200 hover:bg-gray-200 transition-all flex items-center justify-center gap-3 shadow-sm">
                            <i data-lucide="download" class="w-5 h-5 text-gray-600"></i> Download PDF Copy
                        </button>
                        <button onclick="finishAndClose()" class="w-full py-4 bg-[#111827] text-white font-bold rounded-xl shadow-lg hover:bg-black transition-all flex items-center justify-center gap-3">
                            <span>Continue to Payment</span> <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: CONTRACT PREVIEW -->
            <div class="flex-1 bg-gray-100 p-8 overflow-y-auto flex justify-center relative shadow-inner scroll-smooth">
                <div class="bg-white w-full max-w-[850px] min-h-[1200px] h-fit shadow-2xl p-16 text-sm text-gray-900 leading-relaxed whitespace-pre-wrap border border-gray-200 relative mb-12 font-montserrat" id="legal-doc-paper">
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .font-montserrat { font-family: 'Montserrat', sans-serif !important; }
    #legal-doc-paper { font-family: 'Montserrat', sans-serif !important; color: #111827 !important; }
    input[type="checkbox"]:focus { ring: 0; outline: none; }
    .decl-checkbox:checked + label, .group:has(.decl-checkbox:checked) { border-color: #111827; background-color: white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
</style>

<script>
    const CLIENT_IP = "<?php echo $user_ip; ?>";
    
    const HEADER_TPL = `TOKEN SALE AGREEMENT\n\n1. PARTIES\n\nCompany\n{{FOUNDER_ENTITY}}, registered office at {{FOUNDER_ADDRESS}}.\nEmail: {{FOUNDER_EMAIL}}\n(the “Company”)\n\nand\n\nPurchaser\n{{SIG_FULL_NAME}}\n{{SIG_FULL_ADDRESS}}\nEmail: {{SIG_EMAIL}}\n(the “Purchaser”)\n\n2. SALE PARTICULARS\nThe sale particulars, including unit price and ticker symbol, are explicitly set forth in Annexe A.\n\n3. CONTRIBUTION & ALLOCATION\nThe Purchaser commits to a total contribution amount of {{CONTRIBUTION_AMOUNT}} USD.\nSubject to the receipt of funds, the Purchaser shall receive an allocation of approximately {{TOKEN_QUANTITY}} {{TOKEN_TICKER}}.\n\nThis Agreement is considered legally valid and binding once the transaction (tx) is confirmed and received by the non-custodial Vault.`;
    
    const FOOTER_TPL = `4. TERMS AND CONDITIONS\nThe specific Terms and Conditions are attached hereto as SCHEDULE 1.\n\n5. GOVERNING LAW\nLaws of {{GOVERNING_LAW}}. Jurisdiction: {{JURISDICTION}}.\n\n_________________________\nSigned by {{FOUNDER_ENTITY}}\n\n_________________________\nSigned by {{SIG_FULL_NAME}}\nDate: {{CURRENT_DATE}}\n\n[ANNEXE A: SALE DETAILS]\n{{ANNEXE_DATA}}`;

    let docVariables = {};
    let customClauses = "";

    function openLegalModal(jsonData, amount, tokenQty) {
        document.getElementById('legal-signing-modal').classList.remove('hidden');
        document.getElementById('legal-input-panel').classList.remove('hidden', 'opacity-0');
        document.getElementById('legal-success-panel').classList.add('hidden');
        
        try {
            const data = (typeof jsonData === 'string') ? JSON.parse(jsonData) : jsonData;
            docVariables = data.variables || {};
            customClauses = data.clauses || "";
        } catch(e) { console.error("Data Parse Error:", e); }
        
        docVariables['CONTRIBUTION_AMOUNT'] = amount;
        docVariables['TOKEN_QUANTITY'] = tokenQty || "0";
        
        renderDocument();
        if(window.lucide) lucide.createIcons();
    }

    function closeLegalModal() { 
        document.getElementById('legal-signing-modal').classList.add('hidden'); 
    }

    function finishAndClose() { 
        closeLegalModal(); 
        if (window.onLegalSigningComplete) window.onLegalSigningComplete(); 
    }

    window.showLegalSuccess = function() {
        const inputPanel = document.getElementById('legal-input-panel');
        const successPanel = document.getElementById('legal-success-panel');
        inputPanel.classList.add('opacity-0');
        setTimeout(() => {
            inputPanel.classList.add('hidden');
            successPanel.classList.remove('hidden');
            if(window.lucide) lucide.createIcons();
        }, 300);
    };

    function renderDocument() {
        const fname = document.getElementById('signer-fname').value.trim();
        const lname = document.getElementById('signer-lname').value.trim();
        const address = document.getElementById('signer-address').value.trim();
        const city = document.getElementById('signer-city').value.trim();
        const zip = document.getElementById('signer-zip').value.trim();
        const email = document.getElementById('signer-email').value.trim();
        
        const fullName = (fname || lname) ? `${fname} ${lname}` : "[Purchaser Name]";
        const fullAddress = (address || city || zip) ? `${address}, ${zip} ${city}` : "[Registered Address]";
        
        const renderVars = { 
            ...docVariables, 
            'SIG_FULL_NAME': fullName, 
            'SIG_FULL_ADDRESS': fullAddress, 
            'SIG_EMAIL': email || "[Email Address]",
            'CURRENT_DATE': new Date().toLocaleDateString() 
        };

        let fullText = HEADER_TPL + "\n\n" + "______________________________________________________\n\n" + "[SCHEDULE 1]\n" + customClauses + "\n\n" + "______________________________________________________\n\n" + FOOTER_TPL;
        
        Object.keys(renderVars).forEach(k => { 
            const val = String(renderVars[k] || `[${k}]`);
            fullText = fullText.split(`{{${k}}}`).join(val); 
        });

        document.getElementById('legal-doc-paper').innerText = fullText;
    }

    async function downloadSignedPDF() {
        const element = document.getElementById('legal-doc-paper');
        const timestamp = new Date().toISOString();
        const msgBuffer = new TextEncoder().encode(element.innerText + timestamp);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        
        const stampHTML = `<div style="margin-top:40px;padding:15px;border-top:1px solid #eee;font-size:8px;color:#666;font-family:'Montserrat', sans-serif;"><p><strong>Digital Signature Verification Stamp</strong></p><p>Timestamp: ${timestamp}</p><p>IP Address: ${CLIENT_IP}</p><p>SHA-256 Fingerprint: ${hashHex}</p></div>`;
        const clone = element.cloneNode(true);
        clone.innerHTML += stampHTML;
        
        const opt = { 
            margin: 15, 
            filename: 'Signed_Agreement.pdf', 
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } 
        };

        html2pdf().from(clone).set(opt).toPdf().get('pdf').then(function (pdf) {
            var totalPages = pdf.internal.getNumberOfPages();
            for (let i = 1; i <= totalPages; i++) {
                pdf.setPage(i);
                pdf.setFontSize(8);
                pdf.text('Page ' + i + ' of ' + totalPages, pdf.internal.pageSize.getWidth() - 30, pdf.internal.pageSize.getHeight() - 10);
            }
        }).save();
    }

    document.querySelectorAll('.signer-input').forEach(input => {
        input.addEventListener('input', () => { renderDocument(); validateSigner(); });
    });

    document.querySelectorAll('.decl-checkbox').forEach(chk => {
        chk.addEventListener('change', () => {
            // Update wrapper style based on checked status
            const wrapper = chk.closest('label');
            if (chk.checked) {
                wrapper.classList.add('border-gray-900', 'bg-white', 'shadow-md');
                wrapper.classList.remove('bg-gray-50');
            } else {
                wrapper.classList.remove('border-gray-900', 'bg-white', 'shadow-md');
                wrapper.classList.add('bg-gray-50');
            }
            validateSigner();
        });
    });

    function validateSigner() {
        let valid = true;
        document.querySelectorAll('.signer-input').forEach(input => { if(!input.value.trim()) valid = false; });
        const c1 = document.getElementById('decl-identity-vault').checked;
        const c2 = document.getElementById('decl-agreement').checked;
        const c3 = document.getElementById('decl-binding').checked;
        if(!c1 || !c2 || !c3) valid = false;
        document.getElementById('legal-sign-btn').disabled = !valid;
    }

    document.getElementById('legal-sign-btn').addEventListener('click', async () => {
        const content = document.getElementById('legal-doc-paper').innerText;
        const timestamp = new Date().toISOString();
        const msgBuffer = new TextEncoder().encode(content + timestamp);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashHex = Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');

        if (typeof handleLegalSignature === 'function') {
            handleLegalSignature({
                firstName: document.getElementById('signer-fname').value,
                lastName: document.getElementById('signer-lname').value,
                address: document.getElementById('signer-address').value,
                city: document.getElementById('signer-city').value,
                zip: document.getElementById('signer-zip').value,
                email: document.getElementById('signer-email').value,
                fullSnapshot: content,
                digitalHash: hashHex,
                // These specific keys ensure the backend validation for the notice is satisfied
                disclaimer_accepted: 'on',
                terms: 'on'
            });
        } else {
            console.error("Critical Error: handleLegalSignature function not found.");
            alert("Application Error: Legal signing engine unavailable.");
        }
    });
</script>