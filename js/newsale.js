/**
 * newsale.js - Silicon Valley Standard
 * Engineered for Robust External vs. Internal Sale Separation
 * Fixes: 
 * 1. Relational Join for Vesting Data (0% TGE Bug)
 * 2. Data Normalization for Copying Sales (Missing Details/Team Bug)
 * 3. Round Selection Value Fix (Round Name vs ID)
 * 4. FIX: Social Media Dropdown & Indicator Fetching (LinkedIn/Metrics Bug)
 * 5. FIX: JSON String Parsing for Repeater Inputs (Fixes empty fields on Edit)
 * 6. FIX: Corrected mapping for Story Data (Fixed Name/Number swap)
 * 7. FIX: Robust Aliasing (Handles 0 and empty strings correctly)
 * 8. FIX: Handles Object-based arrays (PHP quirks)
 * 9. FIX: Write Compatibility with Viewer (Reverted input names to Story format)
 * 10. FIX: Restriction Prefill and Custom Restriction Generation
 * 11. FIX: Integrated Agreement Sync & Mandatory Review Check
 */

const projectUuid = window.TOOKLE_CONFIG?.projectUuid || '';
let dbProjectDataGlobal = {}; 
let availableRoundsGlobal = {}; 
let hasOpenedAgreement = false; // Flag to enforce mandatory agreement review
window.validateGnosisInput = function() {
    const gnosisInput = document.getElementById('gnosis_safe_address');
    const gnosisStatus = document.getElementById('gnosis-address-status');
    if (!gnosisInput || !gnosisStatus) return true;
    const val = gnosisInput.value.trim();
    if (val === '') {
        gnosisStatus.textContent = '';
        gnosisStatus.classList.add('hidden');
        gnosisInput.classList.remove('border-red-500');
        return false;
    }
    
    const isHex = /^0x[0-9a-fA-F]{40}$/.test(val);
    const isZero = /^0x0+$/.test(val);
    
    if (!isHex || isZero) {
        gnosisStatus.textContent = 'Please enter a valid Gnosis Safe Base address (starts with 0x, exactly 42 characters).';
        gnosisStatus.classList.remove('hidden');
        gnosisInput.classList.add('border-red-500');
        return false;
    } else {
        gnosisStatus.textContent = '';
        gnosisStatus.classList.add('hidden');
        gnosisInput.classList.remove('border-red-500');
        return true;
    }
};

// --- 1. CORE UTILITIES ---

function showToast(message, isError = false) {
    const toast = document.getElementById('toast-container');
    if (toast) {
        const el = document.createElement('div');
        el.className = `p-3 rounded shadow-lg text-white mb-2 ${isError ? 'bg-red-600' : 'bg-green-600'}`;
        el.textContent = message;
        toast.appendChild(el);
        toast.classList.add('show');
        setTimeout(() => { el.remove(); if(!toast.hasChildNodes()) toast.classList.remove('show'); }, 3000);
    }
}

// --- 2. UI MODE SWITCHER ---

/**
 * Toggles the form between "Internal (Tookle)" and "External" modes.
 * Handles visibility AND validation logic (removing 'required' attrs).
 */
function toggleExternalMode(isExternal) {
    const tookleFields = document.getElementById('tookle-sale-fields');
    const extDetails = document.getElementById('external-platform-details');
    const extSpecifics = document.getElementById('external-specific-fields');
    const minPContainer = document.getElementById('min-purchase-container');
    const maxPContainer = document.getElementById('max-purchase-container');
    const tookleSettlement = document.getElementById('tookle-settlement-details');

    // inputs that change requirement status
    const extNameInput = document.getElementById('external_platform_name');
    const projDescInput = document.getElementById('project-description');
    const minPInput = document.getElementById('min-purchase');
    const maxPInput = document.getElementById('max-purchase');

    if (isExternal) {
        // --- EXTERNAL MODE ---
        if(tookleFields) tookleFields.style.display = 'none';
        if(extDetails) extDetails.classList.remove('hidden');
        if(extSpecifics) extSpecifics.classList.remove('hidden');
        if(tookleSettlement) tookleSettlement.classList.add('hidden');
        
        // Hide Internal-Only Fields
        if(minPContainer) minPContainer.style.display = 'none';
        if(maxPContainer) maxPContainer.style.display = 'none';

        // Update Validation Requirements
        if(extNameInput) extNameInput.setAttribute('required', 'true');
        
        // Disable validation for hidden internal fields
        if(projDescInput) projDescInput.removeAttribute('required');
        if(minPInput) minPInput.removeAttribute('required');
        if(maxPInput) maxPInput.removeAttribute('required');

        // Clear required from file inputs inside dropzones if they exist
        document.querySelectorAll('#tookle-sale-fields input[required]').forEach(el => {
            el.dataset.wasRequired = 'true'; // Store state if needed later
            el.removeAttribute('required');
        });

    } else {
        // --- INTERNAL MODE ---
        if(tookleFields) tookleFields.style.display = 'block';
        if(extDetails) extDetails.classList.add('hidden');
        if(extSpecifics) extSpecifics.classList.add('hidden');
        if(tookleSettlement) tookleSettlement.classList.remove('hidden');

        // Show Internal-Only Fields
        if(minPContainer) minPContainer.style.display = 'block';
        if(maxPContainer) maxPContainer.style.display = 'block';

        // Update Validation Requirements
        if(extNameInput) extNameInput.removeAttribute('required');
        
        // Restore validation for internal fields
        if(projDescInput) projDescInput.setAttribute('required', 'true');
        if(minPInput) minPInput.setAttribute('required', 'true');
        if(maxPInput) maxPInput.setAttribute('required', 'true');
    }
}

// --- 3. ACCORDION & REPEATER LOGIC ---

function setupAccordionListeners() {
    document.querySelectorAll('.accordion-header').forEach(header => {
        if (header.dataset.hasListener) return;
        header.dataset.hasListener = 'true';

        header.addEventListener('click', () => {
            const panel = header.nextElementSibling;
            if (!panel) return;
            header.classList.toggle('is-open');
            panel.classList.toggle('is-open');
        });
    });
}

function initializeRepeater(containerId, buttonId, createItemFn, initialData = []) {
    const container = document.getElementById(containerId);
    const button = document.getElementById(buttonId);
    if (!container || !button) return;

    container.innerHTML = ''; 

    const addItem = (data = null) => {
        const item = createItemFn(data, container.children.length);
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-button';
        removeBtn.type = 'button';
        removeBtn.innerHTML = '<i data-lucide="x" class="w-4 h-4"></i>';
        removeBtn.onclick = () => item.remove();
        item.appendChild(removeBtn);
        container.appendChild(item);
        if(typeof lucide !== 'undefined') lucide.createIcons({ nodes: [item] });
    };

    const newButton = button.cloneNode(true);
    button.parentNode.replaceChild(newButton, button);
    newButton.addEventListener('click', () => addItem());

    if (Array.isArray(initialData) && initialData.length > 0) {
        initialData.forEach(item => addItem(item));
    }
}

/**
 * NORMALIZATION HELPER (The Fix for Missing Data)
 * Handles cases where data is saved as:
 * 1. Standard: [{name: "A", role: "B"}]
 * 2. Transposed (PHP array_values): [["A"], ["B"]]
 * 3. Corrupted (PHP loop over columns): [{"0":"A"}, {"0":"B"}]
 * 4. FIX ADDED: Raw JSON Strings: "[{...}]" (When backend API returns string instead of array)
 */
function normalizeRepeaterInput(data, fieldMap, aliasMap = {}) {
    if (!data) return []; // Guard against null/undefined immediately
    
    // FIX: Auto-parse if data is a JSON string
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            console.error("Error parsing repeater JSON:", e);
            return [];
        }
    }

    // FIX: Handle Object-based arrays (PHP associative array encoded as JSON object)
    // e.g. {"0": {...}, "1": {...}} -> [{...}, {...}]
    if (typeof data === 'object' && data !== null && !Array.isArray(data)) {
        // If it looks like a list (numeric keys), convert to array
        if (Object.keys(data).every(k => !isNaN(parseInt(k)))) {
            data = Object.values(data);
        }
    }

    if (!Array.isArray(data) || data.length === 0) return [];

    // Helper to detect if an item looks like a "Column Object" (Numeric keys "0", "1"...)
    // This happens when PHP loops over $_POST['team'] (which has keys 'name', 'role') 
    // and saves the columns as rows.
    const isColumnObject = (obj) => {
        return typeof obj === 'object' && obj !== null && '0' in obj && !('name' in obj || 'platform' in obj);
    };

    // Scenario: Transposed Data (Array of Arrays OR Array of Column-Objects)
    if (Array.isArray(data[0]) || isColumnObject(data[0])) {
        // Determine the number of rows based on the length of the first column
        let rowCount = 0;
        if (Array.isArray(data[0])) {
            rowCount = data[0].length;
        } else {
            // Count keys that look like integers
            rowCount = Object.keys(data[0]).filter(k => !isNaN(parseInt(k))).length;
        }

        const normalized = [];
        for (let i = 0; i < rowCount; i++) {
            const item = {};
            fieldMap.forEach((key, colIndex) => {
                // Get the column container (Array or Object)
                const colContainer = data[colIndex]; 
                if (colContainer) {
                    // Extract value at row index 'i'
                    // Works for both Array[i] and Object["i"]
                    item[key] = colContainer[i] || '';
                }
                
                // Special handling for file paths which might be attached to the object
                // In corrupted data, the picture path is often on the 'name' column object
                if (colContainer && colContainer['picture_file_path'] && key === 'picture_file_path') {
                     item[key] = colContainer['picture_file_path'];
                }
                if (colContainer && colContainer['logo_file_path'] && key === 'logo_file_path') {
                     item[key] = colContainer['logo_file_path'];
                }
            });
            normalized.push(item);
        }
        return normalized;
    }

    // Scenario: Standard Data (Array of Objects)
    if (typeof data[0] === 'object' && data[0] !== null) {
        return data.map(item => {
            const newItem = { ...item };
            
            // Fix Aliases (e.g., 'platform_select' -> 'platform')
            // UPDATED: More robust check using != null to handle 0 and false correctly
            Object.keys(aliasMap).forEach(sourceKey => {
                // If source exists (is not null/undefined) AND (target is missing OR target is empty string)
                if (newItem[sourceKey] != null && (newItem[aliasMap[sourceKey]] == null || newItem[aliasMap[sourceKey]] === '')) {
                    newItem[aliasMap[sourceKey]] = newItem[sourceKey];
                }
            });

            return newItem;
        });
    }

    return data;
}

// Function to Create Custom Restriction UI Item
function createCustomRestrictionElement(data = {}) {
    const container = document.getElementById('custom-restrictions-container');
    if(!container) return;
    const div = document.createElement('div');
    div.className = 'custom-restriction-item flex gap-2 items-start mt-2';
    // Use data for prefill if provided
    const country = data.country || '';
    const disclaimer = data.disclaimer || '';
    div.innerHTML = `
        <input type="text" placeholder="Country (e.g. Canada)" class="form-input custom-country-input w-1/3" value="${country}">
        <input type="text" placeholder="Disclaimer text..." class="form-input custom-disclaimer-input w-full" value="${disclaimer}">
        <button type="button" class="text-red-500 p-2 hover:bg-red-50 rounded" onclick="this.parentElement.remove()"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
    `;
    container.appendChild(div);
    if(typeof lucide !== 'undefined') lucide.createIcons({ nodes: [div] });
}

// --- Item Creators ---
function createTeamItem(data, index) {
    const el = document.createElement('div'); el.className = 'repeater-item';
    const name = data?.name || ''; const role = data?.role || ''; const pic = data?.picture_file_path || '';
    el.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label class="form-label">Name</label><input type="text" name="team[name][]" value="${name}" class="form-input"></div><div><label class="form-label">Role</label><input type="text" name="team[role][]" value="${role}" class="form-input"></div><div class="md:col-span-2"><label class="form-label">Picture</label><div class="dropzone" id="team_dropzone_${index}"><input type="file" name="team[picture][]" class="hidden"><div class="text-sm">Upload</div></div><input type="hidden" name="team[existing_picture_path][]" value="${pic}"></div></div>`;
    setupDropzone(el.querySelector('.dropzone')); if(pic) renderDropzoneFile(el.querySelector('.dropzone'), null, pic); return el;
}
function createPartnerItem(data, index) {
    const el = document.createElement('div'); el.className = 'repeater-item';
    const logo = data?.logo_file_path || '';
    el.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div><label class="form-label">Partner Name</label><input type="text" name="partners[name][]" value="${data?.name || ''}" class="form-input"></div><div><label class="form-label">Logo</label><div class="dropzone" id="partner_dropzone_${index}"><input type="file" name="partners[logo][]" class="hidden"><div class="text-sm">Upload</div></div><input type="hidden" name="partners[existing_logo_path][]" value="${logo}"></div></div>`;
    setupDropzone(el.querySelector('.dropzone')); if(logo) renderDropzoneFile(el.querySelector('.dropzone'), null, logo); return el;
}
function createSocialItem(data) {
    const el = document.createElement('div'); el.className = 'repeater-item';
    // Robust selection: checks 'platform' or 'platform_select'
    const platform = data?.platform || data?.platform_select || ''; 
    const url = data?.url || '';

    // FIX: Expanded options list to include LinkedIn and others so values don't get lost
    const options = ['Twitter', 'Discord', 'Website', 'Linkedin', 'Telegram', 'Medium', 'Instagram', 'Youtube'];
    // FIX: Case-insensitive comparison for selection AND trim
    const safePlatform = (platform || '').trim().toLowerCase();
    
    const optionsHtml = options.map(opt => 
        `<option value="${opt}" ${safePlatform === opt.toLowerCase() ? 'selected' : ''}>${opt}</option>`
    ).join('');

    // FIX: Reverted name to 'platform_select' to match Story/Viewer format
    el.innerHTML = `<div class="flex gap-4"><select name="socials[platform_select][]" class="form-select w-1/3">${optionsHtml}</select><input type="text" name="socials[url][]" value="${url}" class="form-input w-2/3" placeholder="URL"></div>`;
    return el;
}
function createValuePropItem(data) {
    const el = document.createElement('div'); el.className = 'repeater-item';
    el.innerHTML = `<div><label class="form-label">Title</label><input type="text" name="value_props[title][]" value="${data?.title || ''}" class="form-input mb-2"></div><div><label class="form-label">Description</label><textarea name="value_props[description][]" class="form-textarea" rows="2">${data?.description || ''}</textarea></div>`; return el;
}
function createFaqItem(data) {
    const el = document.createElement('div'); el.className = 'repeater-item';
    el.innerHTML = `<div><label class="form-label">Question</label><input type="text" name="faqs[question][]" value="${data?.question || ''}" class="form-input mb-2"></div><div><label class="form-label">Answer</label><textarea name="faqs[answer][]" class="form-textarea" rows="2">${data?.answer || ''}</textarea></div>`; return el;
}
function createCommunityItem(data) {
    const el = document.createElement('div'); el.className = 'repeater-item';
    // Community item logic often has differing keys (value/indicator vs platform/count)
    // FIX: Use null coalescing to prevent 0 values from becoming empty strings
    // AND prioritize 'platform' if it exists, otherwise check 'indicator'
    // Also check for 'Platform' (case issues)
    const platform = data?.platform ?? data?.indicator ?? data?.Platform ?? ''; 
    // AND prioritize 'count' if it exists, otherwise check 'value'
    // Also check for 'Count' (case issues)
    const count = data?.count ?? data?.value ?? data?.Count ?? ''; 
    
    // FIX: Reverted names to 'indicator' and 'value' to match Story/Viewer format
    el.innerHTML = `<div class="flex gap-4"><input type="text" name="community_metrics[indicator][]" value="${platform}" class="form-input w-1/2" placeholder="Platform"><input type="text" name="community_metrics[value][]" value="${count}" class="form-input w-1/2" placeholder="Count (e.g. 50k)"></div>`; return el;
}

// --- 4. VALIDATION & MODAL ---

function showValidationModal(message) {
    const modal = document.getElementById('validation-modal');
    if (modal) {
        document.getElementById('validation-modal-message').textContent = message;
        modal.classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('validation-modal-backdrop').classList.remove('opacity-0');
            const panel = document.getElementById('validation-modal-panel');
            panel.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
            panel.classList.add('translate-y-0', 'sm:scale-100');
        }, 10);
    }
}
window.closeValidationModal = function() {
    const modal = document.getElementById('validation-modal');
    if (modal) {
        document.getElementById('validation-modal-backdrop').classList.add('opacity-0');
        const panel = document.getElementById('validation-modal-panel');
        panel.classList.remove('translate-y-0', 'sm:scale-100');
        panel.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }
};

// --- 5. DATA FETCHING & PREFILL ---

async function fetchAndInitialize() {
    const urlParams = new URLSearchParams(window.location.search);
    const saleIdFromUrl = urlParams.get('sale_id');
    
    // If editing existing sale, mark agreement as opened/valid so they aren't forced to re-open unless they want to
    if (saleIdFromUrl) {
        hasOpenedAgreement = true;
    }

    document.querySelectorAll('input[name="hosting"]').forEach(radio => {
        radio.addEventListener('change', (e) => toggleExternalMode(e.target.value === 'external'));
    });

    // Settlement Payment Routing Toggles
    const cardEscrow = document.getElementById('label-routing-escrow');
    const cardMultisig = document.getElementById('label-routing-multisig');
    const gnosisContainer = document.getElementById('gnosis-address-container');
    const gnosisInput = document.getElementById('gnosis_safe_address');
    const gnosisStatus = document.getElementById('gnosis-address-status');
    
    if (gnosisInput) {
        gnosisInput.addEventListener('input', validateGnosisInput);
    }
    
    document.querySelectorAll('input[name="payment_routing"]').forEach(radio => {
        const updateRoutingCards = (val) => {
            const isMultisig = val === 'multisig';
            if (isMultisig) {
                if(cardMultisig) cardMultisig.className = "relative flex flex-col p-4 border-2 border-slate-900 bg-slate-50/20 rounded-lg cursor-pointer transition-all";
                if(cardEscrow) cardEscrow.className = "relative flex flex-col p-4 border border-slate-200 rounded-lg cursor-pointer transition-all hover:bg-slate-50/50";
                if(gnosisContainer) gnosisContainer.classList.remove('hidden');
                if(gnosisInput) gnosisInput.setAttribute('required', 'true');
            } else {
                if(cardEscrow) cardEscrow.className = "relative flex flex-col p-4 border-2 border-slate-900 bg-slate-50/20 rounded-lg cursor-pointer transition-all";
                if(cardMultisig) cardMultisig.className = "relative flex flex-col p-4 border border-slate-200 rounded-lg cursor-pointer transition-all hover:bg-slate-50/50";
                if(gnosisContainer) gnosisContainer.classList.add('hidden');
                if(gnosisInput) {
                    gnosisInput.removeAttribute('required');
                    gnosisInput.value = '';
                }
                if(gnosisStatus) {
                    gnosisStatus.classList.add('hidden');
                    gnosisStatus.textContent = '';
                }
            }
        };
        radio.addEventListener('change', (e) => updateRoutingCards(e.target.value));
        radio.closest('label')?.addEventListener('click', () => {
            radio.checked = true;
            updateRoutingCards(radio.value);
        });
    });

    try {
        const baseFetchUrl = `/backend/newsale_backend.php?projet_id=${projectUuid}`;
        const baseResponse = await fetch(baseFetchUrl);
        const baseData = await baseResponse.json();

        if (baseData.success) {
            const scenarioData = window.TOOKLE_CONFIG?.activeScenario || {};
            const projectInfo = window.TOOKLE_CONFIG?.projectInfo || {};
            
            // Initialize metadata with full Scenario (Rounds + Vesting)
            initializeFormMetadata(scenarioData, projectInfo, baseData.countries || []);
            
            // --- Agreement Builder Sync ---
            if (baseData.agreementData && window.updateAgreementBuilderState) {
                let agreementContent = [];
                try {
                    agreementContent = typeof baseData.agreementData.content === 'string' 
                        ? JSON.parse(baseData.agreementData.content) 
                        : baseData.agreementData.content;
                } catch(e) {
                    console.error("Error parsing agreement content:", e);
                }

                window.updateAgreementBuilderState(
                    baseData.agreementData.version,
                    baseData.agreementData.file_url,
                    agreementContent
                );
                
                const aggDisplay = document.getElementById('agreement-filename-display');
                if (aggDisplay && baseData.agreementData.file_url) {
                    const fname = baseData.agreementData.file_url.split('/').pop();
                    aggDisplay.textContent = `${fname} (v${baseData.agreementData.version})`;
                    aggDisplay.classList.remove('italic');
                    const slot = aggDisplay.closest('.document-upload-slot');
                    if(slot) slot.querySelector('.remove-doc-button')?.classList.remove('hidden');
                } else if (aggDisplay && baseData.agreementData.version > 0) {
                     aggDisplay.textContent = `Custom Agreement (v${baseData.agreementData.version})`;
                     aggDisplay.classList.remove('italic');
                }
            }
            
            let dataToPrefill = null;
            
            if (saleIdFromUrl) {
                const specificUrl = `/backend/api_fetch_external_sale.php?sale_id=${saleIdFromUrl}&project_id=${projectUuid}`;
                const specResp = await fetch(specificUrl);
                const specData = await specResp.json();
                
                if (specData.success && specData.data) {
                    dataToPrefill = specData.data;
                    document.querySelector('h2.text-2xl.font-bold').textContent = `Edit Sale: ${dataToPrefill.sale_name}`;
                    
                    const form = document.getElementById('unifiedSaleForm');
                    if (form && !document.getElementById('hidden_sale_id')) {
                        const hiddenId = document.createElement('input');
                        hiddenId.type = 'hidden';
                        hiddenId.name = 'sale_id';
                        hiddenId.id = 'hidden_sale_id';
                        hiddenId.value = saleIdFromUrl;
                        form.appendChild(hiddenId);
                    }
                    
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.history.replaceState({path: newUrl}, '', newUrl);
                }
            } else if (baseData.initialData) {
                dbProjectDataGlobal = baseData.initialData; 
            }

            const prefillBtn = document.getElementById('prefill-button');
            if (prefillBtn) {
                prefillBtn.addEventListener('click', () => {
                    if (dbProjectDataGlobal && Object.keys(dbProjectDataGlobal).length > 0) {
                        applyPrefill(dbProjectDataGlobal);
                        showToast("Loaded last sale data.");
                    } else {
                        showToast("No previous sale data found.", true);
                    }
                });
            }

            if (dataToPrefill) {
                applyPrefill(dataToPrefill);
                showToast("Sale data loaded.");
            }
        }
    } catch (error) {
        console.error("Initialization Error:", error);
        showToast("Error loading sale data.", true);
    }
}

/**
 * Initializes the round dropdown AND calculates derived metrics.
 * REFACTORED: Now performs a relational join between Rounds and Vesting arrays.
 */
function initializeFormMetadata(scenarioData, projectInfo, countries) {
    const roundSelect = document.getElementById('select-round');
    const roundDetailsDisplay = document.getElementById('round-details-display');
    const targetRaiseInput = document.getElementById('target_raise');
    const softCapInput = document.getElementById('soft_cap');
    const saleNameInput = document.getElementById('sale_name');
    const tokenTicker = projectInfo.token_ticker || 'TOKEN';

    const rounds = scenarioData?.rounds || [];
    const vestingSchedules = scenarioData?.vesting || [];

    // Populate Rounds
    if (Array.isArray(rounds) && rounds.length > 0) {
        roundSelect.innerHTML = '<option value="" disabled selected>Select a round...</option>';
        rounds.forEach((round) => {
            const opt = document.createElement('option');
            // FIX: Use round_name as value so backend can find it by name (Backend expects Name, not ID)
            opt.value = round.round_name; 
            const percentRaise = parseFloat(round.percent_total_raise || 0).toFixed(2);
            opt.textContent = `${round.round_name} (${percentRaise}% of raise)`;
            opt.dataset.id = round.id; // Store ID in dataset for local lookup
            roundSelect.appendChild(opt);
        });
    } else {
        roundSelect.innerHTML = '<option value="" disabled selected>No rounds found in active scenario</option>';
    }

    // Handle Round Selection
    roundSelect.addEventListener('change', function() {
        const selectedOpt = this.options[this.selectedIndex];
        // Retrieve ID from dataset since value is now name
        const selectedId = parseInt(selectedOpt.dataset.id); 
        
        // 1. Find Round Data
        const roundData = rounds.find(r => parseInt(r.id) === selectedId);
        if (!roundData) return;

        // 2. JOIN: Find Matching Vesting Data (Fix for 0% TGE)
        // Data structure separates rounds from vesting schedules. We link them via source_id.
        const vestingData = vestingSchedules.find(v => 
            v.source_type === 'round' && 
            parseInt(v.source_id) === selectedId
        );

        const coreParams = scenarioData.core_params || {};
        
        // --- CLIENT-SIDE CALCULATIONS ---
        const tgePrice = parseFloat(coreParams.calculated_price_tge || 0);
        const totalTargetRaise = parseFloat(coreParams.target_raise || coreParams.target_raise_usd || 0);
        const discount = parseFloat(roundData.percent_discount || 0);
        
        // A. Price Calculation
        let roundPrice = 0;
        if (roundData.token_price && parseFloat(roundData.token_price) > 0) {
            roundPrice = parseFloat(roundData.token_price);
        } else if (roundData.round_price && parseFloat(roundData.round_price) > 0) {
            roundPrice = parseFloat(roundData.round_price);
        } else {
            roundPrice = tgePrice * (1 - (discount / 100));
        }
        
        // B. Allocation
        const roundAmount = totalTargetRaise * (parseFloat(roundData.percent_total_raise || 0) / 100);
        const numTokens = roundPrice > 0 ? roundAmount / roundPrice : 0;
        
        // C. % Supply & FDV
        const supplyValue = parseFloat(projectInfo.supply_value || 0);
        const percentSupply = supplyValue > 0 ? (numTokens / supplyValue) * 100 : 0;
        const fdv = supplyValue * roundPrice;

        // D. Vesting Metrics (Using correctly joined data)
        const tgePercent = vestingData ? parseFloat(vestingData.percent_unlock_at_tge || 0) : 0;
        const cliff = vestingData ? parseInt(vestingData.cliff_months || 0) : 0;
        const duration = vestingData ? parseInt(vestingData.vesting_months || 0) : 0;

        // Formatter
        const fmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 6 });
        const fmtCompact = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', notation: "compact" });

        // --- RENDER UI (Updated to Silicon Valley Standard) ---
        roundDetailsDisplay.classList.remove('hidden');
        roundDetailsDisplay.innerHTML = `
            <div class="round-detail-card animate-in fade-in slide-in-from-top-2 duration-300">
                <div class="flex items-center justify-between border-b border-gray-200 pb-2 mb-2">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Configuration Snapshot</span>
                    <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-bold">${roundData.round_name}</span>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase font-semibold">Token Price</p>
                        <p class="text-sm font-bold text-gray-900">${fmt.format(roundPrice)}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase font-semibold">Implied FDV</p>
                        <p class="text-sm font-medium text-gray-700">${fmtCompact.format(fdv)}</p>
                    </div>

                    <!-- Vesting Info -->
                    <div class="col-span-2 bg-white rounded border border-gray-100 p-2 mt-1">
                        <div class="flex items-center justify-between text-xs">
                            <div class="text-center flex-1 border-r border-gray-100">
                                <span class="block text-[9px] text-gray-400 font-bold uppercase">TGE Unlock</span>
                                <span class="block font-bold text-green-600 text-lg">${tgePercent.toFixed(2)}%</span>
                            </div>
                            <div class="text-center flex-1 border-r border-gray-100">
                                <span class="block text-[9px] text-gray-400 font-bold uppercase">Cliff</span>
                                <span class="block font-bold text-gray-700">${cliff} <span class="text-[9px] font-normal">mos</span></span>
                            </div>
                            <div class="text-center flex-1">
                                <span class="block text-[9px] text-gray-400 font-bold uppercase">Vesting</span>
                                <span class="block font-bold text-gray-700">${duration} <span class="text-[9px] font-normal">mos</span></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-2 pt-2 border-t border-gray-200">
                    <p class="text-xs text-gray-500 italic">
                        <i data-lucide="info" class="w-3 h-3 inline mr-1 align-middle"></i>
                        Allocated: ${numTokens.toLocaleString(undefined, {maximumFractionDigits:0})} ${tokenTicker} (${percentSupply.toFixed(2)}% of supply)
                    </p>
                </div>
            </div>
        `;
        
        // Auto-fill Hard Cap/Soft Cap
        if (targetRaiseInput) {
            targetRaiseInput.value = roundAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        if (softCapInput) {
            softCapInput.value = (roundAmount * 0.1).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        // Auto-fill Sale Name if empty
        if(saleNameInput && !saleNameInput.value) {
            saleNameInput.value = `${roundData.round_name} Sale`;
        }

        if(window.lucide) window.lucide.createIcons();

        // --- NEW: AUTO-SYNC AGREEMENT BUILDER ---
        if (window.updateBuilderSaleParticulars) {
            const vestingText = `TGE: ${tgePercent}%, Cliff: ${cliff}m, Vesting: ${duration}m`;
            window.updateBuilderSaleParticulars({
                 ticker: tokenTicker,
                 round_price: roundPrice > 0 ? fmt.format(roundPrice) : 'TBA',
                 vesting_text: vestingText,
                 tge: tgePercent,
                 cliff: cliff,
                 vesting_months: duration
            });
        }
    });

    if(typeof initializeCountrySearch === 'function') initializeCountrySearch(countries, '');
    
    document.getElementById('add-custom-restriction-button')?.addEventListener('click', () => createCustomRestrictionElement());
}

function applyPrefill(data) {
    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if(el) { el.value = val || ''; el.dispatchEvent(new Event('input')); }
    };

    // 1. Determine Hosting Mode FIRST
    const hostingMode = data.hosting || 'tookle';
    const isExternal = (hostingMode === 'external');
    
    const radio = document.querySelector(`input[name="hosting"][value="${hostingMode}"]`);
    if(radio) {
        radio.checked = true;
        toggleExternalMode(isExternal);
    }

    // 2. Common Fields
    setVal('sale_name', data.sale_name || data.source_sale_name || '');
    setVal('soft_cap', data.min_raise || data.soft_cap || '');
    setVal('target_raise', data.max_raise || data.hard_cap || '');
    if(data.country) {
         setVal('country', data.country);
         const search = document.getElementById('country-search');
         if(search) search.value = data.country;
    }

    // 3. Duration Logic
    let seconds = parseInt(data.duration_seconds || data.duration_custom || 0);
    if(seconds > 0) {
        let val = 0, unit = 'days';
        if (seconds % 86400 === 0) { val = seconds / 86400; unit = 'days'; }
        else if (seconds % 3600 === 0) { val = seconds / 3600; unit = 'hours'; }
        else { val = Math.ceil(seconds / 60); unit = 'minutes'; }
        setVal('duration_value', val); setVal('duration_unit', unit);
        // Force update of hidden field immediately after prefill
        const hiddenDur = document.getElementById('duration_seconds');
        if(hiddenDur) hiddenDur.value = seconds;
    } else if (data.duration_days) {
        setVal('duration_value', data.duration_days); setVal('duration_unit', 'days');
    }

    // 4. External Specifics
    if (isExternal) {
        setVal('external_platform_name', data.sale_url || data.external_platform_name || '');
        const statusMap = {'ended_successful': 'successful', 'ended_failed': 'failed', 'live': 'live', 'draft': 'draft'};
        const rawStatus = data.external_status || data.status;
        setVal('external_status', statusMap[rawStatus] || 'draft');
        
        const formatDT = (s) => s ? s.replace(' ', 'T').substring(0, 16) : '';
        setVal('sale_launch_at', formatDT(data.sale_launch_date || data.sale_launch_at));
        setVal('sale_end_at', formatDT(data.sale_end_date || data.sale_end_at));
        
        return; 
    }

    // 5. Internal Specifics
    setVal('project-description', data.projectDescription || data.project_description_story || '');
    setVal('min-purchase', data.min_purchase_limit || data.min_investment_usd || '');
    setVal('max-purchase', data.max_purchase_limit || data.max_investment_usd || '');

    // 5b. Gnosis Safe Routing Prefill
    const gnosisAddr = data.gnosis_safe_address || '';
    if (gnosisAddr) {
        // Select the "multisig" routing card
        const multisigRadio = document.querySelector('input[name="payment_routing"][value="multisig"]');
        if (multisigRadio) {
            multisigRadio.checked = true;
            multisigRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }
        setVal('gnosis_safe_address', gnosisAddr);

        // Lock address field if sale is not draft
        const saleStatus = (data.status || 'draft').toLowerCase();
        if (saleStatus !== 'draft') {
            const addrInput = document.getElementById('gnosis_safe_address');
            if (addrInput) {
                addrInput.setAttribute('readonly', 'true');
                addrInput.classList.add('bg-slate-50', 'cursor-not-allowed', 'opacity-75');
            }
        }
    } else {
        // Default: escrow selected (already default in HTML)
        const escrowRadio = document.querySelector('input[name="payment_routing"][value="escrow"]');
        if (escrowRadio) {
            escrowRadio.checked = true;
            escrowRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    const video = data.videoFilePath || data.video_url || data.video_path;
    if(video) prefillDropzone('video_dropzone', video);
    const hero = data.heroImageDisplayPath || data.picture_url || data.hero_image_url;
    if(hero) prefillDropzone('hero_image_dropzone', hero);
    const wp = data.whitepaperFilePath || data.whitepaper_url;
    if(wp) prefillDropzone('whitepaper_dropzone', wp);

    // --- APPLY NORMALIZATION BEFORE RENDERING REPEATERS ---
    // FIX: Added fallbacks for JSON columns (e.g. team_json, socials_json) to ensure data is found even if keys vary.
    
    const teamData = normalizeRepeaterInput(data.team || data.team_json, ['name', 'role']);
    try { initializeRepeater('team-container', 'add-team-button', createTeamItem, teamData); } catch(e){}

    const partnerData = normalizeRepeaterInput(data.partners || data.partners_json, ['name']);
    try { initializeRepeater('partners-container', 'add-partner-button', createPartnerItem, partnerData); } catch(e){}

    const socialData = normalizeRepeaterInput(data.socials || data.socials_json, ['platform', 'url'], {'platform_select': 'platform'});
    try { initializeRepeater('socials-container', 'add-social-button', createSocialItem, socialData); } catch(e){}

    const vpData = normalizeRepeaterInput(data.valueProps || data.value_props_json, ['title', 'description']);
    try { initializeRepeater('value-props-container', 'add-value-prop-button', createValuePropItem, vpData); } catch(e){}

    const faqData = normalizeRepeaterInput(data.faqs || data.faqs_json, ['question', 'answer']);
    try { initializeRepeater('faq-container', 'add-faq-button', createFaqItem, faqData); } catch(e){}

    // FIX: Added 'data.community_metrics_json' and 'data.community_metrics' to the check.
    const commData = normalizeRepeaterInput(
        data.communityMetrics || data.community_metrics || data.community_metrics_json, 
        ['platform', 'count'], 
        {'value': 'count', 'indicator': 'platform'}
    );
    try { initializeRepeater('community-container', 'add-community-button', createCommunityItem, commData); } catch(e){}
    
    const restList = document.getElementById('restrictions-list');
    if(restList) {
        restList.querySelectorAll('input').forEach(cb => {
            if(cb.value === 'sanctioned') cb.checked = !!data.exclude_sanctioned;
            if(cb.value === 'us-non-accredited') cb.checked = !!data.exclude_us_non_accredited;
            if(cb.value === 'eu-consent') cb.checked = !!data.require_eu_consent;
            cb.parentElement.classList.toggle('selected', cb.checked);
        });
    }

    // FIX: Populate Custom Restrictions
    const customResContainer = document.getElementById('custom-restrictions-container');
    if (customResContainer) {
        customResContainer.innerHTML = '';
        let customRes = [];
        try {
            // It might come as a JSON string from backend OR an object/array
            const raw = data.custom_country_disclaimer;
            if (typeof raw === 'string' && raw.length > 2) {
                customRes = JSON.parse(raw);
            } else if (Array.isArray(raw)) {
                customRes = raw;
            }
        } catch(e) { console.error('Error parsing custom restrictions', e); }

        if (Array.isArray(customRes)) {
            customRes.forEach(item => {
                if (item && item.country && item.disclaimer) {
                    createCustomRestrictionElement(item);
                }
            });
        }
    }

    const kycCheck = document.getElementById('kyc-verification');
    if(kycCheck) kycCheck.checked = !!data.kyc_required;
}

// --- 6. DOCUMENT READY ---
document.addEventListener('DOMContentLoaded', () => {
    if(typeof lucide !== 'undefined') lucide.createIcons();
    setupAccordionListeners();
    fetchAndInitialize();

    ['video_dropzone', 'hero_image_dropzone', 'whitepaper_dropzone'].forEach(id => {
        const el = document.getElementById(id);
        if (el) setupDropzone(el);
    });

    document.getElementById('prefill-button')?.addEventListener('click', () => {
        if (dbProjectDataGlobal) {
            applyPrefill(dbProjectDataGlobal);
            showToast("Reloaded base sale data.");
        }
    });

    const durVal = document.getElementById('duration_value');
    const durUnit = document.getElementById('duration_unit');
    const durSec = document.getElementById('duration_seconds');
    
    const updateDurationHidden = () => {
        if(!durVal || !durUnit || !durSec) return;
        const val = parseInt(durVal.value) || 0;
        const unit = durUnit.value;
        let mult = 86400; // days
        if(unit === 'hours') mult = 3600;
        if(unit === 'minutes') mult = 60;
        
        durSec.value = val * mult;
    };
    
    if(durVal) durVal.addEventListener('input', updateDurationHidden);
    if(durUnit) durUnit.addEventListener('change', updateDurationHidden);

    const hardCapInput = document.getElementById('target_raise');
    const maxPurchaseInput = document.getElementById('max-purchase');
    
    const checkCaps = () => {
        if (document.querySelector('input[name="hosting"]:checked')?.value === 'external') return true;
        
        const hc = parseFloat(hardCapInput?.value.replace(/,/g, '') || 0);
        const mp = parseFloat(maxPurchaseInput?.value.replace(/,/g, '') || 0);
        const errEl = document.getElementById('max-purchase-error');
        
        if (hc > 0 && mp > 0 && mp > hc) {
            if(errEl) errEl.textContent = 'Maximum Purchase cannot exceed Max Raise.';
            maxPurchaseInput.classList.add('border-red-500');
            return false;
        }
        if(errEl) errEl.textContent = '';
        maxPurchaseInput?.classList.remove('border-red-500');
        return true;
    };
    if(hardCapInput) hardCapInput.addEventListener('input', checkCaps);
    if(maxPurchaseInput) maxPurchaseInput.addEventListener('input', checkCaps);

    const form = document.getElementById('unifiedSaleForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // --- MANDATORY AGREEMENT CHECK ---
            // If it's an internal sale, check if the agreement was reviewed/opened
            const isInternal = document.querySelector('input[name="hosting"][value="tookle"]')?.checked;
            if (isInternal && !hasOpenedAgreement) {
                showValidationModal('Please review the Token Purchase Agreement (Click "View / Edit") before submitting.');
                return;
            }

            const durationSecs = document.getElementById('duration_seconds');
            if (!durationSecs || parseInt(durationSecs.value) < 60) {
                showValidationModal('Duration must be at least 1 minute.');
                return;
            }
            if (!checkCaps()) {
                showValidationModal('Check Max Purchase Limit.');
                return;
            }
            const routingMode = document.querySelector('input[name="payment_routing"]:checked')?.value;
            if (isInternal && routingMode === 'multisig' && !validateGnosisInput()) {
                showValidationModal('Please enter a valid Gnosis Safe Base address.');
                return;
            }
            if (!document.getElementById('country').value) {
                showValidationModal('Please select a jurisdiction.');
                return;
            }

            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="animate-spin w-4 h-4 mr-2"></i> Saving...`;

            const formData = new FormData(this);
            ['soft_cap', 'target_raise', 'min_purchase', 'max_purchase'].forEach(k => {
                if(formData.has(k)) formData.set(k, formData.get(k).replace(/,/g, ''));
            });
            formData.set('duration_select', 'custom');
            formData.set('duration_custom', parseInt(durationSecs.value));

            const custRes = [];
            document.querySelectorAll('.custom-restriction-item').forEach(i => {
                const c = i.querySelector('.custom-country-input').value.trim();
                const d = i.querySelector('.custom-disclaimer-input').value.trim();
                if(c && d) custRes.push({country: c, disclaimer: d});
            });
            formData.set('custom_country_disclaimer', JSON.stringify(custRes));

            fetch(window.TOOKLE_CONFIG.backendUrl, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        const modal = document.getElementById('success-modal');
                        if(d.data?.sale_name) modal.querySelector('h3').textContent = `Sale '${d.data.sale_name}' Saved!`;
                        modal.classList.add('active');
                    } else {
                        showValidationModal(d.message || 'Error saving sale.');
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(err => {
                    console.error(err);
                    showValidationModal('Network error.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });
    }

    document.getElementById('modal-dashboard-btn')?.addEventListener('click', () => window.location.href = `/dashboard`);
    document.getElementById('modal-close-btn')?.addEventListener('click', () => {
        document.getElementById('success-modal').classList.remove('active');
        window.location.reload();
    });

    // Agreement Modal Interceptor
    const openModalBtn = document.getElementById('open-agreement-modal-btn');
    if(openModalBtn) {
        openModalBtn.addEventListener('click', (e) => {
            e.preventDefault(); 
            
            // --- RE-SYNC DATA JUST IN CASE ---
            // Trigger the agreement update logic even if Round didn't "change"
            const roundSelect = document.getElementById('select-round');
            const roundId = roundSelect ? roundSelect.value : null;

            if (window.TOOKLE_CONFIG && window.TOOKLE_CONFIG.activeScenario) {
                 const scenario = window.TOOKLE_CONFIG.activeScenario;
                 const rounds = scenario.rounds || [];
                 // (Reuse vesting map logic)
                 const vestingMap = {};
                 if (scenario.vesting && Array.isArray(scenario.vesting)) {
                     scenario.vesting.forEach(v => {
                         if (v.source_type === 'round' && v.source_id) {
                             vestingMap[v.source_id] = { tge: v.percent_unlock_at_tge, cliff: v.cliff_months, vesting: v.vesting_months };
                         }
                     });
                 }
                 const roundData = rounds.find(r => (r.id && String(r.id) === String(roundId)) || (r.round_name && r.round_name === roundId));
                 
                 if (roundData && window.updateBuilderSaleParticulars) {
                     // Resolve data (simplified for brevity, mirrors initialize logic)
                     let tge = roundData.unlock_tge ?? roundData.percent_unlock_at_tge;
                     let cliff = roundData.cliff_months;
                     let duration = roundData.vesting_months;
                     if (vestingMap[roundData.id]) {
                         const mapData = vestingMap[roundData.id];
                         if (mapData.tge != null) tge = mapData.tge;
                         if (mapData.cliff != null) cliff = mapData.cliff;
                         if (mapData.vesting != null) duration = mapData.vesting;
                     }
                     const vestingText = `TGE: ${parseFloat(tge||0)}%, Cliff: ${parseInt(cliff||0)}m, Vesting: ${parseInt(duration||0)}m`;
                     
                     window.updateBuilderSaleParticulars({
                         ticker: window.TOOKLE_CONFIG.projectInfo?.token_ticker || 'TBA',
                         round_price: roundData.round_price ? '$' + roundData.round_price : 'TBA',
                         vesting_text: vestingText
                     });
                 }
            }

            // Also check if file upload (manual bypass)
            const fileInput = document.getElementById('doc-agreement-upload');
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                hasOpenedAgreement = true;
            }

            // Open Modal & Mark as Checked
            if (typeof window.openAgreementModal === 'function') {
                window.openAgreementModal('builder');
                hasOpenedAgreement = true; 
            } else {
                const modal = document.getElementById('agreement-modal');
                if(modal) {
                    modal.classList.remove('hidden');
                    hasOpenedAgreement = true;
                }
            }
        });
        
        // Also allow manual file upload to satisfy the check
        const fileInput = document.getElementById('doc-agreement-upload');
        if(fileInput) {
            fileInput.addEventListener('change', () => {
                if(fileInput.files.length > 0) hasOpenedAgreement = true;
            });
        }
    }
});

// Dropzone Helpers
function renderDropzoneFile(dropzone, file = null, filePath = null) {
    const input = dropzone.querySelector('input[type="file"]');
    dropzone.innerHTML = '';
    dropzone.appendChild(input);
    let fileName = file ? file.name : (filePath ? filePath.split('/').pop() : null);
    if (!fileName) {
            dropzone.classList.remove('has-file', 'bg-purple-50', 'border-purple-200');
            dropzone.innerHTML += `<div class="text-sm font-medium text-gray-600">Upload</div><div class="existing-file-name text-xs text-gray-400 mt-1"></div>`;
            return;
    }
    dropzone.classList.add('has-file', 'bg-purple-50', 'border-purple-200');
    dropzone.innerHTML += `<div class="flex flex-col items-center justify-center pointer-events-none p-4"><i data-lucide="file" class="w-10 h-10 text-purple-500 mb-3"></i><span class="text-xs text-gray-700 font-medium truncate block">${fileName}</span></div>`;
    if(typeof lucide !== 'undefined') lucide.createIcons({ nodes: [dropzone] });
}
function setupDropzone(el) {
    if(!el) return;
    const input = el.querySelector('input');
    el.addEventListener('click', () => input.click());
    input.addEventListener('click', e => e.stopPropagation());
    input.addEventListener('change', () => { if(input.files.length) renderDropzoneFile(el, input.files[0]); });
}
function prefillDropzone(id, path) {
    const el = document.getElementById(id);
    if(el && path) { 
        const hidden = document.getElementById('existing_' + id.replace('_dropzone', '') + '_path');
        if(hidden) hidden.value = path;
        renderDropzoneFile(el, null, path); 
    }
}