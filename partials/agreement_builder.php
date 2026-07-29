<?php
/**
 * Component: Token Purchase Agreement Builder (Tookle Edition)
 * Version: 12.1 - Mandatory Address & Simplified Ack
 * Description: Enforces Registered Address as mandatory and simplifies the legal acknowledgement text.
 */
?>

<!-- Add PDF.js (Required for parsing imported PDFs) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<!-- Add html2pdf.js (Required for exporting to PDF) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<!-- TOOKLE AGREEMENT WORKSPACE -->
<div id="agreement-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm animate-in fade-in duration-300 font-montserrat">
    
    <!-- MAIN CARD CONTAINER -->
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col overflow-hidden border border-gray-200 relative">
        
        <!-- HEADER -->
        <header class="h-14 flex-shrink-0 border-b border-gray-100 flex items-center justify-between px-6 bg-white z-20">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-md flex items-center justify-center bg-gray-100 text-gray-700">
                    <i data-lucide="scale" class="w-3.5 h-3.5"></i>
                </div>
                <div>
                    <h2 class="text-xs font-bold text-gray-900 tracking-wide font-montserrat uppercase">Token Sale Agreement Builder</h2>
                </div>
            </div>

            <button id="close-agreement-modal-btn" type="button" class="group p-2 rounded-lg hover:bg-gray-50 transition-colors">
                <i data-lucide="x" class="w-4 h-4 text-gray-400 group-hover:text-gray-900 transition-colors"></i>
            </button>
        </header>

        <!-- Primary Workspace -->
        <main class="flex-1 flex overflow-hidden relative font-montserrat">
            
            <!-- Sidebar: Inputs moved here -->
            <aside id="agreement-sidebar" class="w-80 border-r border-gray-100 bg-gray-50/50 overflow-y-auto hidden xl:block z-10">
                <div class="p-6 space-y-8">
                    
                    <!-- Sale Particulars -->
                    <div>
                        <div class="flex items-center gap-2 mb-3 opacity-60">
                            <i data-lucide="tag" class="w-3 h-3 text-gray-400"></i>
                            <h3 class="text-[9px] uppercase tracking-widest font-bold text-gray-900 font-montserrat">Sale Terms (Annexe A)</h3>
                        </div>
                        <div id="sale-details-list" class="space-y-2">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- Clause Ideas (REFINED: Non-Financial) -->
                    <div id="clause-ideas-panel">
                        <button onclick="toggleClauseIdeas()" class="flex items-center justify-between w-full gap-2 mb-3 opacity-80 hover:opacity-100 transition-opacity">
                            <div class="flex items-center gap-2">
                                <i data-lucide="lightbulb" class="w-3 h-3 text-purple-600"></i>
                                <h3 class="text-[9px] uppercase tracking-widest font-bold text-purple-700 font-montserrat">Standard Clause Ideas</h3>
                            </div>
                            <i id="clause-chevron" data-lucide="chevron-down" class="w-3 h-3 text-gray-400 transition-transform"></i>
                        </button>
                        
                        <div id="clause-list" class="hidden space-y-3 bg-white p-3 rounded-lg border border-purple-100 shadow-sm text-[10px] text-gray-600 leading-relaxed max-h-[200px] overflow-y-auto">
                            <p class="font-medium text-gray-900 mb-1">Recommended Structure:</p>
                            <ul class="list-disc pl-3 space-y-1 marker:text-purple-400">
                                <li><strong>4.1 Utility Qualification:</strong> Explicit statement that the Token is a utility token for platform use only.</li>
                                <li><strong>4.2 Definitions:</strong> Define "Platform", "Token", "Service", etc.</li>
                                <li><strong>4.3 Representations:</strong> Buyer confirms they are not a restricted person.</li>
                                <li><strong>4.4 Risk Factors:</strong> Acknowledgement of technical and regulatory risks.</li>
                                <li><strong>4.5 No Expectation of Profit:</strong> Purchase is for consumptive use only.</li>
                                <li><strong>4.6 Non-Custodial:</strong> User is responsible for their own wallet security.</li>
                            </ul>
                            <div class="mt-2 pt-2 border-t border-gray-100">
                                <button onclick="copyStandardClauses()" class="text-purple-600 hover:text-purple-800 font-bold text-[9px] flex items-center gap-1">
                                    <i data-lucide="copy" class="w-3 h-3"></i> Copy Structure to Clipboard
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Variable Map (EDITABLE INPUTS) -->
                    <div>
                        <div class="flex items-center gap-2 mb-3 opacity-60">
                            <i data-lucide="database" class="w-3 h-3 text-gray-400"></i>
                            <h3 class="text-[9px] uppercase tracking-widest font-bold text-gray-900 font-montserrat">Agreement Data</h3>
                        </div>
                        
                        <div class="space-y-6">
                            <!-- Founder Section -->
                            <div id="founder-inputs-group">
                                <div class="flex items-center gap-2 mb-3 pl-1">
                                    <div class="w-1.5 h-1.5 rounded-full bg-purple-600"></div>
                                    <span class="text-[9px] font-bold text-gray-900 uppercase tracking-tight font-montserrat">Company (Party A)</span>
                                </div>
                                <div id="variable-list-founder" class="space-y-3"></div>
                            </div>

                            <!-- Legal Framework Section -->
                            <div id="legal-inputs-group">
                                <div class="flex items-center gap-2 mb-3 pl-1">
                                    <div class="w-1.5 h-1.5 rounded-full bg-gray-600"></div>
                                    <span class="text-[9px] font-bold text-gray-900 uppercase tracking-tight font-montserrat">Governing Law</span>
                                </div>
                                <div id="variable-list-legal" class="space-y-3"></div>
                            </div>
                            
                            <!-- Backer Note -->
                            <div class="p-3 bg-gray-100 rounded-lg border border-gray-200">
                                <p class="text-[9px] text-gray-500 leading-relaxed italic">
                                    <strong>Party B (Purchaser)</strong> details are captured dynamically during the signing process.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Workspace: Single Text Surface -->
            <section id="agreement-body-container" class="flex-1 overflow-y-auto relative scroll-smooth bg-white">
                
                <div id="view-editor-panel" class="max-w-4xl mx-auto py-8 px-10 h-full flex flex-col">
                    
                    <!-- EDITOR TOOLBAR -->
                    <div class="flex items-center justify-between mb-4 flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <!-- PDF Upload Button (Import) -->
                            <button id="upload-pdf-btn" class="flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-200 rounded-md text-xs font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-all uppercase tracking-wide font-montserrat shadow-sm">
                                <i data-lucide="upload-cloud" class="w-3.5 h-3.5"></i> Import Clauses
                            </button>
                            <input type="file" id="pdf-upload-input" accept=".pdf" class="hidden">
                        </div>

                        <div class="flex items-center gap-2">
                             <!-- Preview Toggle -->
                             <button id="preview-toggle-btn" class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 border border-gray-200 text-gray-700 rounded-md text-xs font-bold hover:bg-gray-200 transition-all uppercase tracking-wide font-montserrat">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> <span id="preview-btn-text">Preview Document</span>
                            </button>
                        </div>
                    </div>

                    <!-- FOUNDER GUIDANCE (Updated Colors) -->
                    <div id="founder-legal-guidance" class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-start gap-3 relative overflow-hidden">
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-purple-500"></div>
                        <div class="p-1.5 bg-white rounded-md border border-gray-200 shrink-0 text-purple-600">
                            <i data-lucide="shield-alert" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h3 class="text-[10px] font-bold text-gray-900 uppercase tracking-wide mb-1 font-montserrat">Mandatory Legal Requirement</h3>
                            <p class="text-[10px] text-gray-600 leading-relaxed font-montserrat font-medium">
                                It is <strong>mandatory</strong> to insert valid legal clauses provided by a qualified lawyer. Tookle provides <strong>only the infrastructure</strong> for signing and variable management; we <strong>do not</strong> provide legal advice or contract content.
                            </p>
                            <p class="text-[10px] text-purple-700 mt-2 leading-relaxed font-montserrat">
                                <span class="font-bold">Note:</span> Financial terms (Vesting, Cliff, TGE, Price) are automatically handled in Annexe A. Do not duplicate them in your clauses.
                            </p>
                        </div>
                    </div>

                    <!-- SIGNATORY INPUT PANEL (Visible ONLY in Backer Mode) -->
                    <div id="backer-party-inputs" class="hidden mb-6 flex-shrink-0 bg-white border border-gray-200 shadow-sm rounded-xl p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-gray-600">
                                <i data-lucide="user-pen" class="w-3.5 h-3.5"></i>
                            </div>
                            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wide font-montserrat">Purchaser Details</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="backer-inline-inputs">
                            <!-- Inputs injected here via JS -->
                        </div>
                    </div>

                    <!-- SPLIT EDITOR (FOUNDER MODE) -->
                    <div id="founder-split-editor" class="hidden flex-1 flex flex-col gap-4 min-h-[500px]">
                        
                        <!-- System Header (Locked 1-4) -->
                        <div class="p-6 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 font-montserrat leading-relaxed select-none opacity-80">
                            <div class="flex items-center gap-2 mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400 border-b border-gray-200 pb-2">
                                <i data-lucide="lock" class="w-3 h-3"></i> Master Agreement (Sections 1-4)
                            </div>
                            <div id="system-header-preview" class="whitespace-pre-wrap"></div>
                        </div>

                        <!-- Founder Content Area (Schedule 1) -->
                        <div class="flex-1 relative flex flex-col mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-900 px-1">
                                    <i data-lucide="paperclip" class="w-3 h-3"></i> Schedule 1 Content (Terms & Conditions) <span class="text-purple-500">*</span>
                                </div>
                                <span class="text-[9px] text-gray-400 font-medium">MANDATORY</span>
                            </div>
                            <textarea id="founder-clauses-input" class="w-full flex-1 p-6 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 font-montserrat leading-relaxed resize-none focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition-all shadow-sm placeholder-gray-400 min-h-[300px]" placeholder="[MANDATORY] Paste your full commercial terms and legal clauses here..."></textarea>
                        </div>

                        <!-- System Footer (Locked 5 + Sig) -->
                        <div class="p-6 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 font-montserrat leading-relaxed select-none opacity-80 mt-4">
                            <div class="flex items-center gap-2 mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400 border-b border-gray-200 pb-2">
                                <i data-lucide="lock" class="w-3 h-3"></i> Master Agreement (Section 5 + Signatures)
                            </div>
                            <div id="system-footer-preview" class="whitespace-pre-wrap"></div>
                        </div>

                    </div>
                    
                    <!-- BACKER MODE / PREVIEW: Rendered HTML (Full Doc) -->
                    <div id="master-contract-view" class="hidden flex-1 p-12 bg-white text-sm text-gray-800 leading-relaxed overflow-y-auto font-montserrat border border-gray-100 rounded-lg shadow-sm"></div>
                    
                </div>

                <!-- PDF PREVIEW (Hidden by default) -->
                <div id="view-pdf-panel" class="absolute inset-0 hidden bg-gray-800 flex items-center justify-center p-8">
                    <iframe id="pdf-preview-frame" class="w-full h-full bg-white shadow-2xl rounded-sm" src=""></iframe>
                </div>
            </section>
        </main>

        <!-- FOOTER -->
        <footer id="command-bar" class="h-16 border-t border-gray-100 bg-white flex items-center justify-between px-6 shrink-0 z-20 font-montserrat">
            <div id="founder-tools" class="flex items-center gap-3">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest font-montserrat">Actions:</span>
                <button onclick="document.getElementById('founder-clauses-input').value = ''" class="h-8 px-3 rounded-md text-[10px] font-bold text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-all uppercase tracking-widest flex items-center gap-2 border border-transparent hover:border-gray-200 font-montserrat">
                    <i data-lucide="trash-2" class="w-3 h-3"></i> <span>Clear Schedule</span>
                </button>
            </div>

            <div id="backer-tools" class="hidden flex items-center gap-4 px-4">
                 <span class="text-[10px] font-bold text-gray-900 flex items-center gap-2 uppercase tracking-widest font-montserrat">
                    <span class="w-1.5 h-1.5 bg-gray-900 rounded-full animate-pulse"></span>
                    Ready to Sign
                 </span>
            </div>

            <div class="flex items-center gap-4">
                
                <!-- Legal Acknowledge Checkbox -->
                <div id="founder-ack-wrapper" class="flex items-center gap-2 hidden">
                    <input type="checkbox" id="legal-ack-checkbox" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-600 cursor-pointer">
                    <label for="legal-ack-checkbox" class="text-[9px] text-gray-600 font-medium select-none cursor-pointer max-w-[220px] leading-tight">
                        I certify that this information is true and I acknowledge that Tookle is only an infrastructure provider and does not provide legal advice.
                    </label>
                </div>

                <div class="h-8 w-px bg-gray-200 mx-2 hidden lg:block"></div>

                <!-- PDF Export Button -->
                <button id="export-pdf-btn" type="button" class="h-9 px-4 rounded-lg font-bold text-[10px] uppercase tracking-[0.15em] border border-gray-200 hover:bg-gray-50 transition-all font-montserrat flex items-center gap-2">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> PDF
                </button>
                
                <button id="save-agreement-changes-btn" type="button" class="btn-tookle-primary h-9 px-6 rounded-lg font-bold text-[10px] uppercase tracking-[0.15em] hover:shadow-lg transition-all font-montserrat disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="save-text">Save & Seal</span>
                </button>
            </div>
        </footer>
    </div>
</div>

<style>
    /* TOOKLE DESIGN SYSTEM FONTS */
    .font-montserrat { font-family: 'Montserrat', sans-serif !important; }
    
    /* TOOKLE BUTTONS - MONOCHROME */
    .btn-tookle-primary {
        background-color: #1F2937; /* Gray 800 */
        color: white;
        border: none;
        transition: all 0.2s ease;
    }
    .btn-tookle-primary:hover { background-color: #000000; transform: translateY(-1px); }

    /* Rendered Contract Styling */
    #master-contract-view { white-space: pre-wrap; font-family: 'Montserrat', sans-serif; }
    
    /* Variable Highlight in Text - MONOCHROME */
    .doc-variable-highlight {
        color: #111827; /* Gray 900 */
        background: #F3F4F6;
        padding: 0 4px;
        border-radius: 2px;
        font-weight: 600;
        border-bottom: 1px dotted #9CA3AF;
    }
    
    /* Sidebar Input Styling */
    .sidebar-input-group label { display: block; font-size: 8px; font-weight: 700; color: #9CA3AF; uppercase; letter-spacing: 0.05em; margin-bottom: 4px; text-transform: uppercase; font-family: 'Montserrat', sans-serif; }
    .sidebar-input { width: 100%; padding: 6px 10px; font-size: 11px; border: 1px solid #E5E7EB; rounded: 6px; color: #1F2937; outline: none; transition: all 0.2s; font-family: 'Montserrat', sans-serif; font-weight: 500; }
    .sidebar-input:focus { border-color: #7C3AED; background: white; box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.05); }
    /* UPDATED: Purple/Gray for error state per user request (Removed Red) */
    .sidebar-input.required-missing { border-color: #7C3AED; background: #F5F3FF; }

    /* Inline Input Styling (View Mode) */
    .inline-input-group label { display: block; font-size: 10px; font-weight: 700; color: #6B7280; uppercase; letter-spacing: 0.05em; margin-bottom: 6px; font-family: 'Montserrat', sans-serif; }
    .inline-input { width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #E5E7EB; rounded: 8px; color: #1F2937; outline: none; transition: all 0.2s; font-family: 'Montserrat', sans-serif; font-weight: 500; background: #F9FAFB; }
    .inline-input:focus { border-color: #4B5563; background: white; box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05); }

    .sale-detail-pill {
        padding: 0.5rem 0.75rem; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 0.5rem;
    }
    .sale-detail-pill .label { display: block; font-size: 8px; text-transform: uppercase; color: #9CA3AF; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 2px; font-family: 'Montserrat', sans-serif; }
    .sale-detail-pill .value { display: block; font-size: 11px; color: #111827; font-weight: 600; white-space: pre-line; font-family: 'Montserrat', sans-serif; }
</style>

<script>
    var tokenSaleInfo = <?php echo json_encode($token_sale_data ?? []); ?>;
    var agreementProjectId = "<?php echo htmlspecialchars($current_project_id ?? ''); ?>";
    var agreementBackendUrl = '/backend/compliance_backend.php';
    
    // --- MASTER AGREEMENT STRUCTURE (Numbering 1-5 fixed) ---
    const HEADER_TEMPLATE = `TOKEN SALE AGREEMENT

1. PARTIES

Company
{{FOUNDER_ENTITY}}, a company duly incorporated and existing under the laws of {{GOVERNING_LAW}}, with its registered office at {{FOUNDER_ADDRESS}}.
Email: {{FOUNDER_EMAIL}}
(the “Company”)

and

Purchaser
____________________________________________________
(Name and Address to be completed by Purchaser at signature)

The Company and the Purchaser are hereinafter individually referred to as a “Party” and collectively as the “Parties.”

2. SALE PARTICULARS
The sale particulars, including price, ticker symbol, and vesting terms, are explicitly set forth in Annexe A.

3. CONTRIBUTION
The Purchaser commits to a Contribution amount of {{CONTRIBUTION_AMOUNT}} USD specified at the time of signature via the Platform.

4. TERMS AND CONDITIONS
The specific Terms and Conditions governing this Token Sale are attached hereto as SCHEDULE 1 and are incorporated herein by reference.`;

    const FOOTER_TEMPLATE = `5. GOVERNING LAW AND JURISDICTION
This Agreement shall be governed by and construed in accordance with the laws of {{GOVERNING_LAW}}.
Any disputes arising out of or in connection with this Agreement shall be submitted to the exclusive jurisdiction of the competent courts of {{JURISDICTION}}.

_________________________
Signed by {{FOUNDER_ENTITY}}

_________________________
Signed by Purchaser (Electronically)

[ANNEXE A: SALE DETAILS]
{{ANNEXE_DATA}}`;

    const DEFAULT_CLAUSES = ``;
    
    // RESTORED: Standard Clause Ideas for "Copy to Clipboard" functionality
    // UPDATED: Removed strict "financial instrument" refs to ensure Utility compliance
    const REFERENCE_CLAUSES = `4.1 UTILITY QUALIFICATION
The Purchaser expressly acknowledges and agrees that the Tokens are utility tokens intended solely for the use and interaction within the Platform. The Tokens do not constitute securities, shares, or other similar instruments under the laws of [Governing Jurisdiction]. The Purchaser confirms that they are acquiring the Tokens for consumptive and utility purposes only.

4.2 DEFINITIONS
"Platform" means [Platform Name/Description].
"Token" means the {{TOKEN_TICKER}} digital asset.
"Services" means the utility functions available on the Platform.

4.3 REPRESENTATIONS AND WARRANTIES
The Purchaser represents and warrants that:
(a) They have the legal capacity to enter into this Agreement.
(b) They are not a citizen or resident of any jurisdiction where the purchase of the Tokens is prohibited or restricted.
(c) They are not a Restricted Person or sanctioned entity.

4.4 RISK FACTORS
The Purchaser understands and accepts the inherent risks associated with blockchain technology, including but not limited to:
(a) Technical risks such as bugs, errors, or delays.
(b) Market risks such as extreme volatility.
(c) Regulatory risks such as adverse legal changes.
(d) Risk of loss of private keys or wallet access.

4.5 NON-CUSTODIAL ACKNOWLEDGEMENT
The Purchaser acknowledges that they are solely responsible for the security, custody, and management of their private keys and digital wallets. The Company does not hold, store, or manage private keys on behalf of the Purchaser and shall not be liable for any loss of Tokens resulting from the Purchaser’s failure to secure their wallet.`;

    let userRole = 'founder';
    
    // CONSTRUCT ANNEXE DATA WITH TGE/CLIFF/VESTING & CONTRACT ADDR
    let vestingDetails = tokenSaleInfo.vesting_text || "As defined in platform parameters";
    
    let annexeDataString = `1. Asset Ticker: ${tokenSaleInfo.ticker || 'TBA'}
2. Unit Price: ${tokenSaleInfo.round_price}
3. Vesting Terms:
   - Schedule: ${vestingDetails}
   - TGE Release: (See Vesting Schedule)
   - Cliff Period: (See Vesting Schedule)
4. Token Contract: 
   - The Smart Contract Address/Code will be shared separately by the Company upon generation.`;

    // DATA STORE
    let documentVariables = {
        'FOUNDER_ENTITY': '',
        'FOUNDER_ADDRESS': '',
        'FOUNDER_EMAIL': '',
        'GOVERNING_LAW': 'Switzerland',
        'JURISDICTION': 'Canton of Vaud',
        'TOKEN_TICKER': tokenSaleInfo.ticker || 'TBA',
        'SIG_NAME': '', // Required for storage but filled by backer
        'SIG_ADDRESS': '', // Required for storage but filled by backer
        'SIG_EMAIL': '', // Required for storage but filled by backer
        'CONTRIBUTION_AMOUNT': '', // Required for storage but filled by backer
        'ANNEXE_DATA': annexeDataString
    };

    /**
     * PUBLIC API: Allows external scripts (e.g. Newsale) to inject Round Data dynamically
     */
    window.updateBuilderSaleParticulars = function(roundData) {
        if (!roundData) return;

        // 1. Update Internal Token Data
        tokenSaleInfo = {
            ...tokenSaleInfo,
            ...roundData
        };

        // 2. Reconstruct Annexe String
        const newVesting = roundData.vesting_text || "As defined in platform parameters";
        const newAnnexe = `1. Asset Ticker: ${roundData.ticker || 'TBA'}
2. Unit Price: ${roundData.round_price || 'TBA'}
3. Vesting Terms:
   - Schedule: ${newVesting}
   - TGE Release: (See Vesting Schedule)
   - Cliff Period: (See Vesting Schedule)
4. Token Contract: 
   - The Smart Contract Address/Code will be shared separately by the Company upon generation.`;

        // 3. Update Document Variables
        updateVar('ANNEXE_DATA', newAnnexe);
        if (roundData.ticker) updateVar('TOKEN_TICKER', roundData.ticker);

        // 4. Force UI Refresh
        updateVariableUI();
        updateSystemPreviews();
        
        console.log("Agreement Builder updated with new round data:", roundData);
    };

    document.addEventListener('DOMContentLoaded', () => {
        // Load existing content (JSON Structure Check)
        const input = document.getElementById('doc-agreement-content');
        let savedContent = "";
        
        if (input?.value) {
            try { 
                const raw = input.value.trim();
                // Try parsing as JSON first (New Format)
                if (raw.startsWith('{')) {
                    const parsed = JSON.parse(raw);
                    if (parsed.type === 'master_schedule_v1') {
                        // Restore state completely
                        savedContent = parsed.clauses || "";
                        if (parsed.variables) {
                            // Merge variables, but keep system ones like ANNEXE_DATA fresh from current round unless explicit overwrite
                            documentVariables = { ...documentVariables, ...parsed.variables };
                            // Ensure ANNEXE_DATA is always re-synced from tokenSaleInfo
                            documentVariables['ANNEXE_DATA'] = annexeDataString; 
                        }
                    }
                } else {
                    // Fallback to legacy text extraction
                    if (raw.includes("[SCHEDULE 1")) {
                        savedContent = raw;
                    } else if (raw.includes("1. PARTIES")) {
                        const parts = raw.split("4. TERMS");
                        if (parts.length > 1) {
                            savedContent = ""; // Reset for new structure
                        }
                    }
                }
            } catch(e) { 
                savedContent = input.value; 
            }
        }
        
        // Ensure default is empty to force action
        if (!savedContent || savedContent.trim() === "") savedContent = "";
        
        document.getElementById('founder-clauses-input').value = savedContent;

        // Initialize
        setUserRole('founder');
        updateVariableUI();
        updateSystemPreviews();
        validateForm(); // Initial Validation check

        // Bind Events
        document.getElementById('open-agreement-modal-btn')?.addEventListener('click', () => window.openAgreementModal('builder'));
        document.getElementById('close-agreement-modal-btn')?.addEventListener('click', closeAgreementModal);
        document.getElementById('save-agreement-changes-btn')?.addEventListener('click', saveAgreementChanges);
        document.getElementById('founder-clauses-input')?.addEventListener('input', () => { updateSystemPreviews(); validateForm(); });
        document.getElementById('export-pdf-btn')?.addEventListener('click', exportToPDF);
        document.getElementById('legal-ack-checkbox')?.addEventListener('change', validateForm);
        
        // PDF UPLOAD LOGIC
        const pdfBtn = document.getElementById('upload-pdf-btn');
        const pdfInput = document.getElementById('pdf-upload-input');
        
        if(pdfBtn && pdfInput) {
            pdfBtn.addEventListener('click', () => pdfInput.click());
            pdfInput.addEventListener('change', async function(e) {
                if (this.files.length === 0) return;
                const file = this.files[0];
                const originalText = pdfBtn.innerHTML;
                pdfBtn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Parsing...';
                lucide.createIcons();

                try {
                    const arrayBuffer = await file.arrayBuffer();
                    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                    let fullText = "[SCHEDULE 1: TERMS AND CONDITIONS]\n\n";
                    for (let i = 1; i <= pdf.numPages; i++) {
                        const page = await pdf.getPage(i);
                        const textContent = await page.getTextContent();
                        const pageText = textContent.items.map(item => item.str).join(' ');
                        fullText += pageText + "\n\n";
                    }
                    document.getElementById('founder-clauses-input').value = fullText;
                    validateForm();
                    if(window.showToast) window.showToast("PDF Content Imported to Schedule 1", "success");
                } catch (err) {
                    console.error(err);
                    if(window.showToast) window.showToast("Failed to parse PDF", "error");
                } finally {
                    pdfBtn.innerHTML = originalText;
                    lucide.createIcons();
                    this.value = ''; 
                }
            });
        }

        // PREVIEW TOGGLE
        const previewBtn = document.getElementById('preview-toggle-btn');
        let isPreviewMode = false;
        
        if(previewBtn) {
            previewBtn.addEventListener('click', () => {
                isPreviewMode = !isPreviewMode;
                const splitEditor = document.getElementById('founder-split-editor');
                const viewEl = document.getElementById('master-contract-view');
                const btnText = document.getElementById('preview-btn-text');
                const icon = document.querySelector('#preview-toggle-btn i');
                const guide = document.getElementById('founder-legal-guidance');
                const backerInputs = document.getElementById('backer-party-inputs');

                if (isPreviewMode) {
                    renderBackerView(); 
                    splitEditor.classList.add('hidden');
                    viewEl.classList.remove('hidden');
                    if (guide) guide.classList.add('hidden');
                    
                    if(userRole === 'backer') {
                        backerInputs.classList.remove('hidden');
                    } else {
                        backerInputs.classList.add('hidden');
                    }

                    btnText.textContent = "Edit Mode";
                    icon.setAttribute('data-lucide', 'edit-3');
                } else {
                    splitEditor.classList.remove('hidden');
                    viewEl.classList.add('hidden');
                    if (guide) guide.classList.remove('hidden');
                    backerInputs.classList.add('hidden');
                    btnText.textContent = "Preview Mode";
                    icon.setAttribute('data-lucide', 'eye');
                }
                lucide.createIcons();
            });
        }
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    // Helper functions for Clause Ideas Panel
    window.toggleClauseIdeas = () => {
        const list = document.getElementById('clause-list');
        const chev = document.getElementById('clause-chevron');
        list.classList.toggle('hidden');
        chev.style.transform = list.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
    }

    window.copyStandardClauses = () => {
        // Copy to clipboard
        const el = document.createElement('textarea');
        el.value = REFERENCE_CLAUSES;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        if(window.showToast) window.showToast("Standard Clauses copied to clipboard", "success");
    }

    /**
     * EXPORT TO PDF
     */
    function exportToPDF() {
        const clauses = document.getElementById('founder-clauses-input').value;
        const fullDocText = replaceVars(HEADER_TEMPLATE + "\n\n" + "______________________________________________________\n\n" + clauses + "\n\n" + "______________________________________________________\n\n" + FOOTER_TEMPLATE);
        
        const element = document.createElement('div');
        element.innerHTML = `<div style="font-family: 'Montserrat', sans-serif; font-size: 12px; line-height: 1.5; padding: 40px; color: #000;">
            <h1 style="text-align:center; margin-bottom: 20px;">TOKEN SALE AGREEMENT</h1>
            <div style="white-space: pre-wrap;">${fullDocText}</div>
        </div>`;
        
        const opt = {
            margin:       10,
            filename:     'Token_Sale_Agreement_Draft.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }

    /**
     * OPEN MODAL with CONTEXT
     */
    window.openAgreementModal = (context) => {
        const modal = document.getElementById('agreement-modal');
        if(!modal) return;
        modal.classList.remove('hidden');
        
        if (context === 'salepage') {
            setUserRole('backer');
        } else {
            setUserRole('founder');
        }
    };

    function setUserRole(role) {
        userRole = role;
        
        const splitEditor = document.getElementById('founder-split-editor');
        const viewEl = document.getElementById('master-contract-view');
        const founderTools = document.getElementById('founder-tools');
        const backerTools = document.getElementById('backer-tools');
        const founderGuidance = document.getElementById('founder-legal-guidance');
        const previewBtn = document.getElementById('preview-toggle-btn');
        const backerInputs = document.getElementById('backer-party-inputs');
        const ackWrapper = document.getElementById('founder-ack-wrapper');

        if (role === 'founder') {
            splitEditor.classList.remove('hidden');
            viewEl.classList.add('hidden');
            backerInputs.classList.add('hidden');
            if (founderGuidance) founderGuidance.classList.remove('hidden');
            if (ackWrapper) ackWrapper.classList.remove('hidden');
            founderTools.classList.remove('hidden');
            backerTools.classList.add('hidden');
            if(previewBtn) previewBtn.parentElement.classList.remove('hidden');
            updateSystemPreviews();
        } else {
            splitEditor.classList.add('hidden');
            viewEl.classList.remove('hidden');
            backerInputs.classList.remove('hidden');
            if (founderGuidance) founderGuidance.classList.add('hidden');
            if (ackWrapper) ackWrapper.classList.add('hidden');
            founderTools.classList.add('hidden');
            backerTools.classList.remove('hidden');
            if(previewBtn) previewBtn.parentElement.classList.add('hidden');
            renderBackerView();
        }
        updateVariableUI(); 
    }

    function replaceVars(text) {
        let html = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        Object.keys(documentVariables).forEach(k => {
            const regex = new RegExp(`{{${k}}}`, 'g');
            const val = documentVariables[k] ? documentVariables[k] : `<span class="opacity-50 border-b border-gray-300">__${k.replace('_',' ')}__</span>`;
            const style = documentVariables[k] ? 'font-bold text-gray-900 border-b border-gray-300' : 'text-gray-400 italic';
            html = html.replace(regex, `<span class="${style} px-1">${val}</span>`);
        });
        return html;
    }

    function updateSystemPreviews() {
        const headerEl = document.getElementById('system-header-preview');
        const footerEl = document.getElementById('system-footer-preview');
        if(headerEl) headerEl.innerHTML = replaceVars(HEADER_TEMPLATE);
        if(footerEl) footerEl.innerHTML = replaceVars(FOOTER_TEMPLATE);
    }

    function updateVar(key, val) {
        documentVariables[key] = val;
        const sidebarInput = document.querySelector(`.sidebar-input[data-key="${key}"]`);
        if(sidebarInput && sidebarInput.value !== val) sidebarInput.value = val;
        
        updateSystemPreviews();
        validateForm(); // Validate on every variable change
        if (userRole === 'backer') renderBackerView();
    }

    function updateVariableUI() {
        const listFounder = document.getElementById('variable-list-founder');
        const listLegal = document.getElementById('variable-list-legal');
        const saleList = document.getElementById('sale-details-list');
        
        if (!listFounder || !listLegal || !saleList) return;
        
        listFounder.innerHTML = '';
        listLegal.innerHTML = '';
        
        saleList.innerHTML = `
            <div class="sale-detail-pill"><span class="label">Ticker Symbol</span><span class="value">${tokenSaleInfo.ticker || 'TBA'}</span></div>
            <div class="sale-detail-pill"><span class="label">Participation Price</span><span class="value">${tokenSaleInfo.round_price || 'TBA'}</span></div>
            <div class="sale-detail-pill"><span class="label">Release Schedule</span><span class="value">${tokenSaleInfo.vesting_text || 'TBA'}</span></div>
        `;

        const founderKeys = ['FOUNDER_ENTITY', 'FOUNDER_EMAIL', 'FOUNDER_ADDRESS'];
        const legalKeys = ['GOVERNING_LAW', 'JURISDICTION'];

        const labelMap = {
            'FOUNDER_ENTITY': 'Company Legal Name',
            'FOUNDER_EMAIL': 'Company Email',
            'FOUNDER_ADDRESS': 'Registered Address',
            'GOVERNING_LAW': 'Governing Law',
            'JURISDICTION': 'Jurisdiction'
        };

        Object.keys(documentVariables).forEach(key => {
            if (['ANNEXE_DATA', 'TOKEN_TICKER', 'SIG_NAME', 'SIG_ADDRESS', 'SIG_EMAIL', 'CONTRIBUTION_AMOUNT'].includes(key)) return; 
            
            let label = labelMap[key] || key.replace(/_/g, ' ');
            let val = documentVariables[key];
            let isRequired = (key === 'FOUNDER_ENTITY' || key === 'FOUNDER_EMAIL' || key === 'FOUNDER_ADDRESS');
            
            const sidebarWrapper = document.createElement('div');
            sidebarWrapper.className = 'sidebar-input-group';
            sidebarWrapper.innerHTML = `
                <label>${label} ${isRequired ? '<span class="text-purple-500">*</span>' : ''}</label>
                <input type="text" class="sidebar-input ${isRequired && !val ? 'required-missing' : ''}" data-key="${key}" value="${val}" oninput="updateVar('${key}', this.value)">
            `;
            
            if (founderKeys.includes(key)) {
                listFounder.appendChild(sidebarWrapper);
            } else if (legalKeys.includes(key)) {
                listLegal.appendChild(sidebarWrapper);
            }
        });
    }
    
    function renderBackerView() {
        const viewEl = document.getElementById('master-contract-view');
        const clauses = document.getElementById('founder-clauses-input').value;
        const fullDoc = HEADER_TEMPLATE + "\n\n" + "______________________________________________________\n\n" + clauses + "\n\n" + "______________________________________________________\n\n" + FOOTER_TEMPLATE;
        viewEl.innerHTML = replaceVars(fullDoc);
    }

    // MANDATORY VALIDATION
    function validateForm() {
        if (userRole !== 'founder') return true; 

        const companyName = documentVariables['FOUNDER_ENTITY'];
        const companyEmail = documentVariables['FOUNDER_EMAIL'];
        const companyAddress = documentVariables['FOUNDER_ADDRESS'];
        const clauses = document.getElementById('founder-clauses-input').value.trim();
        const ackChecked = document.getElementById('legal-ack-checkbox')?.checked;
        const saveBtn = document.getElementById('save-agreement-changes-btn');

        let isValid = true;

        if (!companyName || companyName.trim() === '') isValid = false;
        if (!companyEmail || companyEmail.trim() === '') isValid = false;
        if (!companyAddress || companyAddress.trim() === '') isValid = false;
        if (!clauses || clauses === '') isValid = false;
        if (!ackChecked) isValid = false;

        if (saveBtn) {
            saveBtn.disabled = !isValid;
            saveBtn.title = isValid ? "Ready to Seal" : "Please fill all mandatory fields (*), add clauses, and check the box.";
        }
        
        // Visual feedback
        document.querySelectorAll('.sidebar-input').forEach(input => {
            const key = input.dataset.key;
            if ((key === 'FOUNDER_ENTITY' || key === 'FOUNDER_EMAIL' || key === 'FOUNDER_ADDRESS') && !input.value) {
                input.classList.add('required-missing');
            } else {
                input.classList.remove('required-missing');
            }
        });

        return isValid;
    }

    async function saveAgreementChanges() {
        if (!validateForm()) return;

        const btn = document.getElementById('save-agreement-changes-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="animate-pulse">Sealing...</span>';

        const clauses = document.getElementById('founder-clauses-input').value;
        
        // ROBUST DATA STORAGE: Save full state in JSON
        const storagePayload = {
            type: 'master_schedule_v1',
            clauses: clauses,
            variables: documentVariables
        };

        const formData = new FormData();
        formData.append('action', 'save_agreement');
        formData.append('project_id', agreementProjectId);
        formData.append('doc_agreement_content', JSON.stringify(storagePayload)); 
        formData.append('variables', JSON.stringify(documentVariables)); 

        try {
            const res = await fetch(agreementBackendUrl, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                if (window.showToast) window.showToast("Instrument Sealed Successfully.");

                // --- FORCE PARENT PAGE SYNC ---
                // Update the hidden input for form submission
                const parentInput = document.getElementById('doc-agreement-content');
                if (parentInput) {
                    parentInput.value = JSON.stringify(storagePayload);
                }

                // Update the visual label ("Custom Agreement (vX)")
                const parentDisplay = document.getElementById('agreement-filename-display');
                if (parentDisplay) {
                    const ver = data.new_version || 'New';
                    parentDisplay.textContent = `Custom Agreement (v${ver})`;
                    parentDisplay.classList.remove('italic', 'text-gray-500');
                    parentDisplay.classList.add('text-purple-700', 'font-semibold', 'not-italic');
                    
                    // Show 'Remove' button so user can delete if needed
                    const slot = parentDisplay.closest('.document-upload-slot');
                    if (slot) {
                        const removeBtn = slot.querySelector('.remove-doc-button');
                        if (removeBtn) removeBtn.classList.remove('hidden');
                        
                        // Hide upload button to avoid confusion
                        const uploadBtn = slot.querySelector('.upload-button');
                        if (uploadBtn) uploadBtn.classList.add('hidden');
                    }
                }
            }
            
            window.closeAgreementModal();
        } catch (e) {
            console.error(e);
            if(window.showToast) window.showToast("Error saving agreement", "error");
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="save-text">Save & Seal</span>';
        }
    }

    window.closeAgreementModal = () => document.getElementById('agreement-modal').classList.add('hidden');
</script>