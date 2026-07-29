<?php
/**
 * Page: Configure Token Sale - Step 3: Set Compliance
 * Filepath: /pages/compliance.php
 */

require_once __DIR__ . '/../wizard_nav.php';

$current_main_step = 'private_sale';
$current_sub_step = 'compliance';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/db.php';

$current_project_id = $_SESSION['active_project_id'] ?? null;

if (empty($current_project_id)) {
    die("Error: Project ID is missing. Please ensure you have selected a project.");
}

// --- 1. FETCH TOKEN SALE DATA ---
$token_sale_data = [
    'round_name' => 'TBA',
    'round_price' => 'TBA',
    'vesting_text' => 'TBA',
    'total_supply' => 'TBA',
    'ticker' => 'TBA'
];

try {
    $stmt_sale = $pdo->prepare("
        SELECT p.token_ticker, p.supply_value, p.type_supply, tsp.sale_terms_json 
        FROM projet p
        LEFT JOIN token_sale_pages tsp ON p.id = tsp.project_id
        WHERE p.id = :pid
        ORDER BY tsp.created_at DESC LIMIT 1
    ");
    $stmt_sale->execute([':pid' => $current_project_id]);
    $sale_result = $stmt_sale->fetch(PDO::FETCH_ASSOC);

    if ($sale_result) {
        $terms = json_decode($sale_result['sale_terms_json'] ?? '[]', true) ?? [];
        $token_sale_data['ticker'] = $sale_result['token_ticker'] ?? 'TBA';
        
        $supply = $sale_result['supply_value'] ?? 0;
        $type = $sale_result['type_supply'] ?? '';
        $token_sale_data['total_supply'] = number_format($supply) . ' ' . ucfirst($type);

        if (!empty($terms)) {
            $token_sale_data['round_name'] = $terms['round_name'] ?? 'TBA';
            $token_sale_data['round_price'] = isset($terms['round_price']) ? '$' . $terms['round_price'] : 'TBA';
            
            $tge = $terms['percent_unlock_at_tge'] ?? 0;
            $cliff = $terms['cliff_months'] ?? 0;
            $duration = $terms['vesting_months'] ?? 0;
            $token_sale_data['vesting_text'] = "{$tge}% at TGE, {$cliff} months cliff, {$duration} months vesting";
        }
    }
} catch (Exception $e) {
    error_log("Error fetching compliance data: " . $e->getMessage());
}

// --- Fetch existing compliance settings ---
$kyc_required = true;
$exclude_sanctioned = false;
$exclude_us_non_accredited = false;
$require_eu_consent_value = false;
$db_custom_country_json = '[]';
$doc_opinion_filename = 'No file selected';
$doc_other_filename = 'No file selected';

// Agreement variables
$db_agreement_content_json = '[]';
$doc_agreement_filename = 'No file selected';
$doc_agreement_url = '';
$doc_agreement_version = 0;

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM compliance_settings WHERE projet_id = :project_id_param");
        $stmt->execute([':project_id_param' => $current_project_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($settings) {
            $kyc_required = !empty($settings['kyc_required']);
            $exclude_sanctioned = !empty($settings['exclude_sanctioned']);
            $exclude_us_non_accredited = !empty($settings['exclude_us_non_accredited']);
            $require_eu_consent_value = !empty($settings['require_eu_consent']);
            $db_custom_country_json = !empty($settings['custom_country_disclaimer']) ? $settings['custom_country_disclaimer'] : '[]';
            
            $legal_opinion_url = $settings['legal_opinion_url'] ?? '';
            $other_doc_url = $settings['other_doc_url'] ?? '';
            
            if (!empty($legal_opinion_url)) $doc_opinion_filename = basename($legal_opinion_url);
            if (!empty($other_doc_url)) $doc_other_filename = basename($other_doc_url);
        }

        // [UPDATED] Robust fetch logic for active agreement
        $agg_stmt = $pdo->prepare("SELECT content, file_url, version FROM agreement_versions WHERE projet_id = :project_id_param AND is_active = 1 ORDER BY version DESC LIMIT 1");
        $agg_stmt->execute([':project_id_param' => $current_project_id]);
        $active_agreement = $agg_stmt->fetch(PDO::FETCH_ASSOC);

        if ($active_agreement) {
            $doc_agreement_version = $active_agreement['version'];
            $doc_agreement_url = $active_agreement['file_url'] ?? '';
            
            // Prioritize File URL if it exists
            if (!empty($active_agreement['file_url'])) {
                 $doc_agreement_filename = basename($active_agreement['file_url']) . ' (v' . $active_agreement['version'] . ')';
                 // Even if file exists, we might have content, but file takes display precedence usually.
                 // However, we still load content if available just in case.
                 if (isset($active_agreement['content']) && strlen($active_agreement['content']) > 2) {
                     $db_agreement_content_json = $active_agreement['content'];
                 }
            } 
            // Check for Builder Content (Non-empty and length > 4 to catch '[]' or 'null')
            elseif (isset($active_agreement['content']) && strlen($active_agreement['content']) > 4 && $active_agreement['content'] !== '[]') {
                 $doc_agreement_filename = 'Custom Agreement (v' . $active_agreement['version'] . ')';
                 $db_agreement_content_json = $active_agreement['content'];
            }
        }

    } catch (PDOException $e) {
        error_log("Error fetching settings: " . $e->getMessage());
    }
}

$js_initial_restrictions = [
    ['value' => 'sanctioned', 'title' => 'Exclude Sanctioned Countries', 'description' => 'Excludes participation from comprehensively sanctioned jurisdictions.', 'checked' => $exclude_sanctioned],
    ['value' => 'us-non-accredited', 'title' => 'Exclude US Retail Participants', 'description' => 'Restricts participation to U.S. accredited participants only.', 'checked' => $exclude_us_non_accredited],
    ['value' => 'eu-consent', 'title' => 'Require EU Consent', 'description' => 'Requires explicit consent for data processing from EU residents.', 'checked' => $require_eu_consent_value]
];
$js_custom_restrictions = json_decode($db_custom_country_json, true);
if (json_last_error() !== JSON_ERROR_NONE) { $js_custom_restrictions = []; }
$js_agreement_content = $db_agreement_content_json;
?>

<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-gray-50">
    <div class="w-full max-w-6xl">

        <?php render_main_stepper($current_main_step); ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow border border-gray-200 relative">
             <?php render_sub_stepper($current_main_step, $current_sub_step); ?>

            <h1 class="text-2xl font-bold text-gray-900 mb-2">Set Compliance</h1>
            <p class="text-gray-600 text-sm mb-6">Upload compliance documents, define country restrictions, and configure KYC requirements.</p>

            <form id="compliance-form" enctype="multipart/form-data">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($current_project_id); ?>">
                <input type="hidden" name="action" value="save_all">
                <input type="hidden" name="doc_agreement_content" id="doc-agreement-content" value="<?php echo htmlspecialchars($js_agreement_content); ?>">
                
                <section class="mb-8">
                    <h3 class="text-md font-semibold text-gray-800 mb-3">Upload Documents</h3>
                    <p class="text-xs text-gray-500 mb-4 italic">Note: Automatic parsing is provided for convenience only. You must independently verify all extracted content.</p>

                    <div class="space-y-4" id="document-upload-list">
                        
                        <!-- Guidance Block -->
                        <div class="bg-gray-50 border border-gray-200 rounded-md p-3 flex items-start gap-3">
                            <i data-lucide="scale" class="w-5 h-5 text-gray-400 mt-0.5 shrink-0"></i>
                            <div class="text-sm text-gray-600">
                                <p class="font-semibold text-gray-900 mb-1">Mandatory Agreement</p>
                                <p class="mb-1">Use our <strong>Agreement Builder</strong> (View/Edit) to introduce your clauses and share your contract with your backers. Signatures are managed online.</p>
                                <p class="text-xs text-gray-500 mt-2">
                                    This tool does not provide legal advice. You are responsible for ensuring that your documents comply with applicable participant protection laws, and we recommend review by a qualified professional.
                                </p>
                            </div>
                        </div>

                        <!-- Agreement Slot (MANDATORY) -->
                        <div class="document-upload-slot border-l-4 border-l-purple-600" data-doc-type="agreement">
                            <label class="flex-grow font-medium text-sm text-gray-900">
                                Token Purchase Agreement <span class="text-red-600 ml-1" title="Required">*</span>
                                <span class="block text-xs text-gray-400 font-normal mt-0.5">Required. Upload a PDF or use the builder.</span>
                            </label>
                            <div class="upload-area flex items-center gap-2">
                                <span class="file-name-display italic text-gray-500 <?php echo ($doc_agreement_filename !== 'No file selected') ? 'text-purple-700 font-semibold not-italic' : ''; ?>" id="agreement-filename-display">
                                    <?php echo htmlspecialchars($doc_agreement_filename); ?>
                                </span>
                                <input type="file" id="doc-agreement-upload" class="hidden" accept=".pdf" onchange="window.handleAgreementUpload(this)">
                                <button type="button" class="create-edit-button text-purple-700 bg-purple-50 border-purple-200 hover:bg-purple-100" id="open-agreement-modal-btn"><i data-lucide="file-text" class="w-3 h-3 mr-1"></i>View / Edit</button>
                                <button type="button" class="remove-doc-button <?php echo ($doc_agreement_filename === 'No file selected') ? 'hidden' : ''; ?>" title="Remove file" onclick="removeFile(this)"><i data-lucide="x" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                        
                        <!-- Opinion Slot -->
                        <div class="document-upload-slot" data-doc-type="opinion">
                            <label for="doc-opinion" class="flex-grow font-medium text-sm text-gray-700">Legal Opinion</label>
                            <div class="upload-area flex items-center gap-2">
                                <span class="file-name-display italic text-gray-500"><?php echo htmlspecialchars($doc_opinion_filename); ?></span>
                                <button type="button" class="upload-button <?php echo ($doc_opinion_filename !== 'No file selected') ? 'hidden' : ''; ?>" onclick="document.getElementById('doc-opinion').click()"><i data-lucide="upload" class="w-3 h-3 mr-1"></i>Upload</button>
                                <input type="file" id="doc-opinion" name="doc_opinion" class="hidden" accept=".pdf,.doc,.docx" onchange="updateFileNameDisplay(this)">
                                <button type="button" class="remove-doc-button <?php echo ($doc_opinion_filename === 'No file selected') ? 'hidden' : ''; ?>" title="Remove file" onclick="removeFile(this)"><i data-lucide="x" class="w-4 h-4"></i></button>
                            </div>
                        </div>

                        <!-- Other Doc Slot -->
                        <div class="document-upload-slot" data-doc-type="other">
                            <label for="doc-other" class="flex-grow font-medium text-sm text-gray-700">Other (Terms, etc.)</label>
                            <div class="upload-area flex items-center gap-2">
                                <span class="file-name-display italic text-gray-500"><?php echo htmlspecialchars($doc_other_filename); ?></span>
                                <button type="button" class="upload-button <?php echo ($doc_other_filename !== 'No file selected') ? 'hidden' : ''; ?>" onclick="document.getElementById('doc-other').click()"><i data-lucide="upload" class="w-3 h-3 mr-1"></i>Upload</button>
                                <input type="file" id="doc-other" name="doc_other" class="hidden" accept=".pdf,.doc,.docx" onchange="updateFileNameDisplay(this)">
                                <button type="button" class="remove-doc-button <?php echo ($doc_other_filename === 'No file selected') ? 'hidden' : ''; ?>" title="Remove file" onclick="removeFile(this)"><i data-lucide="x" class="w-4 h-4"></i></button>
                            </div>
                        </div>
                    </div>
                </section>
                
                <section class="grid grid-cols-1 md:grid-cols-2 gap-8 border-t border-gray-200 pt-6">
                    <div>
                        <h3 class="text-md font-semibold text-gray-800 mb-3">Set Restrictions per Country or Region</h3>
                        <p class="text-xs text-gray-500 mb-3">By setting country restrictions, you acknowledge that you are solely responsible for compliance with international sanctions, cross-border securities rules, and participant eligibility criteria.</p>
                        <div id="restrictions-list" class="space-y-2"></div>
                        <div id="custom-restrictions-container" class="mt-4 space-y-2"></div>
                         <div id="custom-restriction-controls" class="mt-4">
                            <button type="button" id="add-custom-restriction-button" class="text-sm text-purple-600 hover:underline flex items-center"><i data-lucide="plus" class="w-4 h-4 mr-1"></i> Add Custom Country Restriction</button>
                        </div>
                    </div>
                     <div>
                        <h3 class="text-md font-semibold text-gray-800 mb-1">Enable KYC check</h3>
                        <p class="text-xs text-gray-500 mb-3">If KYC is enabled, you confirm that you have the legal basis to collect, store, and process personal data.</p>
                        <div class="mt-2 p-4 bg-gray-50 rounded-md border">
                           <label class="flex items-center"><input type="checkbox" id="kyc-verification" name="kyc_verification" class="h-4 w-4 text-purple-600 border-gray-300 rounded" <?php if($kyc_required) echo 'checked'; ?>><span class="ml-2 text-sm text-gray-700">KYC Verification Required</span></label>
                        </div>
                    </div>
                </section>
                
                <input type="hidden" name="custom_country_disclaimer" id="custom-country-disclaimer-input">

                <div class="flex justify-between items-center border-t border-gray-200 pt-6 mt-8">
                    <a href="<?= get_url('parameter') ?>" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50">Back</a>
                    <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-purple-700 to-cyan-500 text-white rounded-lg font-medium shadow-md text-sm">Save and Continue</button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- TOAST -->
<div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<!-- CUSTOM CONFIRM MODAL -->
<div id="custom-confirm-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6 text-center transform scale-100">
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 mb-4">
            <i data-lucide="alert-circle" class="h-6 w-6 text-purple-600"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirmation Required</h3>
        <p id="custom-confirm-message" class="text-sm text-gray-500 mb-6">Are you sure you want to proceed?</p>
        <div class="flex justify-center gap-3">
            <button id="custom-confirm-cancel" type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50 transition-colors">Cancel</button>
            <button id="custom-confirm-ok" type="button" class="px-4 py-2 bg-gradient-to-r from-purple-700 to-cyan-500 text-white rounded-lg font-medium shadow-md text-sm hover:shadow-lg transition-all">Proceed</button>
        </div>
    </div>
</div>

<style>
    :root { --tookle-purple: #6D28D9; --tookle-purple-light: #EDE9FE; }
    .document-upload-slot { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background-color: white; border: 1px solid #e5e7eb; border-radius: 0.375rem; margin-bottom: 0.75rem; }
    .upload-button, .create-edit-button { display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border: 1px solid #d1d5db; border-radius: 0.25rem; background-color: #f9fafb; color: #374151; cursor: pointer; }
    .file-name-display { font-size: 0.75rem; color: #6b7280; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .remove-doc-button { color: #ef4444; padding: 0.25rem; }
    .restriction-card { position: relative; border: 1px solid #e5e7eb; background-color: white; padding: 0.75rem 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.15s ease-in-out; display: flex; align-items: center; }
    .restriction-card:hover { border-color: #a78bfa; }
    .restriction-card.selected { border-color: var(--tookle-purple); background-color: var(--tookle-purple-light); border-width: 2px; padding: calc(0.75rem - 1px) calc(1rem - 1px); }
    .restriction-card .selected-check { display: none; margin-left: auto; color: var(--tookle-purple); }
    .restriction-card.selected .selected-check { display: block; }
    .custom-restriction-item { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-top: 0.5rem; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .toast-notification { animation: slideIn 0.3s ease-out; }
</style>

<script>
    const currentProjectId = "<?php echo htmlspecialchars($current_project_id); ?>";

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-green-100 border-green-500 text-green-800' : 'bg-red-100 border-red-500 text-red-800';
        toast.className = `toast-notification flex items-center p-4 border-l-4 rounded shadow-lg bg-white ${bgColor}`;
        toast.innerHTML = `<span class="font-medium text-sm">${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => { toast.remove(); }, 4000);
    }
    window.showToast = showToast;

    // --- Custom Confirm Modal Logic ---
    let confirmCallback = null;
    function openCustomConfirm(message, onConfirm) {
        const modal = document.getElementById('custom-confirm-modal');
        const msgEl = document.getElementById('custom-confirm-message');
        if(modal && msgEl) {
            msgEl.textContent = message;
            confirmCallback = onConfirm;
            modal.classList.remove('hidden');
        }
    }
    window.openCustomConfirm = openCustomConfirm;
    
    function closeCustomConfirm() {
        const modal = document.getElementById('custom-confirm-modal');
        if(modal) modal.classList.add('hidden');
        confirmCallback = null;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const initialRestrictions = <?php echo json_encode($js_initial_restrictions); ?>;
        const restrictionsList = document.getElementById('restrictions-list');
        if (restrictionsList) {
            initialRestrictions.forEach(data => {
                const label = document.createElement('label');
                label.className = 'restriction-card' + (data.checked ? ' selected' : '');
                label.innerHTML = `
                    <input type="checkbox" name="restriction_set[]" value="${data.value}" class="sr-only" ${data.checked ? 'checked' : ''}>
                    <span class="flex-grow">
                        <span class="block font-semibold text-sm">${data.title}</span>
                        <span class="block text-xs text-gray-500">${data.description}</span>
                    </span>
                    <i data-lucide="check-circle" class="w-5 h-5 selected-check"></i>`;
                label.querySelector('input').addEventListener('change', function() {
                    label.classList.toggle('selected', this.checked);
                });
                restrictionsList.appendChild(label);
            });
        }
        
        const existingCustomRestrictions = <?php echo json_encode($js_custom_restrictions); ?>;
        existingCustomRestrictions.forEach(createCustomRestrictionElement);

        document.getElementById('custom-confirm-cancel')?.addEventListener('click', closeCustomConfirm);
        document.getElementById('custom-confirm-ok')?.addEventListener('click', () => {
            if(confirmCallback) confirmCallback();
            closeCustomConfirm();
        });

        document.getElementById('compliance-form').addEventListener('submit', (e) => {
            e.preventDefault();
            
            // --- UX VALIDATION: Mandatory Agreement ---
            const agrFileDisplay = document.getElementById('agreement-filename-display');
            const agrContentInput = document.getElementById('doc-agreement-content');
            
            // Check if file display is NOT 'No file selected' OR content input has significant JSON length
            const hasFile = agrFileDisplay && agrFileDisplay.textContent.trim() !== 'No file selected';
            const hasContent = agrContentInput && agrContentInput.value.length > 5 && agrContentInput.value !== '[]';

            if (!hasFile && !hasContent) {
                showToast("A Token Purchase Agreement is mandatory. Please upload a PDF or use the builder.", "error");
                
                // Visual feedback
                const slot = document.querySelector('.document-upload-slot[data-doc-type="agreement"]');
                if(slot) {
                    slot.classList.add('ring-2', 'ring-red-500', 'bg-red-50');
                    setTimeout(() => slot.classList.remove('ring-2', 'ring-red-500', 'bg-red-50'), 2000);
                }
                return;
            }
            // -------------------------------------------

            const formData = new FormData(e.target);
            formData.set('action', 'save_all');
            
            const customData = [];
            document.querySelectorAll('.custom-restriction-item').forEach(item => {
                const countryEl = item.querySelector('.custom-country-input');
                const disclaimerEl = item.querySelector('.custom-disclaimer-input');
                // SAFETY: Check for existence before reading value
                if (countryEl && disclaimerEl) {
                    customData.push({
                        country: countryEl.value,
                        disclaimer: disclaimerEl.value
                    });
                }
            });
            formData.set('custom_country_disclaimer', JSON.stringify(customData));

            fetch('/backend/compliance_backend.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) window.location.href = data.redirect_url;
                    else showToast(data.message, 'error');
                })
                .catch(() => showToast("A network error occurred while saving.", "error"));
        });
        
        document.getElementById('add-custom-restriction-button').addEventListener('click', () => createCustomRestrictionElement());
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    // --- PATCH: Agreement Builder Safety Patch ---
    // This script block ensures that saveAgreementChanges doesn't crash on non-editable sections
    (function() {
        const originalSave = window.saveAgreementChanges;
        window.saveAgreementChanges = function() {
            try {
                // If the original function crashes, we catch it here to prevent breaking the whole page
                // But better yet, we ensure the sections are scraped safely
                const sections = [];
                const sectionElements = document.querySelectorAll('.agreement-section-editor, .agreement-section-item');
                
                if (sectionElements.length === 0) {
                    // If the original function is available, let it run its internal logic
                    if (typeof originalSave === 'function') return originalSave.apply(this, arguments);
                }

                sectionElements.forEach(el => {
                    const id = el.dataset.id;
                    if (!id) return;

                    const titleInput = el.querySelector('.section-title-input');
                    const bodyTextarea = el.querySelector('.section-body-textarea');
                    
                    // Fallback to existing section data if inputs aren't found (e.g., non-editable Exhibit A)
                    const existingSection = (typeof currentAgreementSections !== 'undefined') 
                        ? currentAgreementSections.find(s => s.id === id) 
                        : null;

                    const title = titleInput ? titleInput.value : (existingSection ? existingSection.title : 'Untitled');
                    const body = bodyTextarea ? bodyTextarea.value : (existingSection ? existingSection.body : '');
                    const editable = existingSection ? existingSection.editable : true;

                    sections.push({ id, title, body, editable });
                });

                if (typeof currentAgreementSections !== 'undefined') {
                    currentAgreementSections = sections;
                    const agreementInput = document.getElementById('doc-agreement-content');
                    if(agreementInput) agreementInput.value = JSON.stringify(sections);
                }

                if (typeof renderAgreementSections === 'function') renderAgreementSections(sections);
                if (typeof hideAgreementModal === 'function') hideAgreementModal();
                showToast("Agreement changes captured.");

            } catch (err) {
                console.error("Patch error:", err);
                // Try fallback to original if our patch fails
                if (typeof originalSave === 'function') return originalSave.apply(this, arguments);
            }
        };
    })();

    function createCustomRestrictionElement(data = { country: '', disclaimer: '' }) {
        const element = document.createElement('div');
        element.className = 'custom-restriction-item';
        element.innerHTML = `
            <div class="flex justify-between items-center mb-2">
                <label class="text-sm font-medium text-gray-700">Custom Restriction</label>
                <button type="button" class="text-red-500 hover:text-red-700" onclick="this.closest('.custom-restriction-item').remove()"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Country</label>
                    <input type="text" value="${data.country}" class="w-full p-2 border rounded-md text-sm custom-country-input">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Disclaimer</label>
                    <input type="text" value="${data.disclaimer}" class="w-full p-2 border rounded-md text-sm custom-disclaimer-input">
                </div>
            </div>`;
        document.getElementById('custom-restrictions-container').appendChild(element);
        if (typeof lucide !== 'undefined') lucide.createIcons({nodes: element.querySelectorAll('[data-lucide]')});
    }

    window.removeFile = async function(removeButton) {
        openCustomConfirm("Are you sure you want to delete this document?", async () => {
            const slot = removeButton.closest('.document-upload-slot');
            const docType = slot.dataset.docType;
            const formData = new FormData();
            formData.append('action', 'delete_document');
            formData.append('project_id', currentProjectId);
            formData.append('doc_type', docType);
            
            try {
                const response = await fetch('/backend/compliance_backend.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    const display = slot.querySelector('.file-name-display');
                    display.textContent = 'No file selected';
                    display.classList.add('italic');
                    display.classList.remove('text-purple-700', 'font-semibold', 'not-italic');
                    slot.querySelector('.remove-doc-button').classList.add('hidden');
                    const uploadBtn = slot.querySelector('.upload-button');
                    if (uploadBtn) uploadBtn.classList.remove('hidden');
                    if (docType === 'agreement' && window.resetAgreementBuilderState) window.resetAgreementBuilderState();
                    showToast("Document deleted successfully.");
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) { showToast("Error deleting file.", 'error'); }
        });
    }
    
    window.updateFileNameDisplay = function(fileInput) {
        const slot = fileInput.closest('.document-upload-slot');
        if(slot && slot.dataset.docType !== 'agreement') {
             const display = slot.querySelector('.file-name-display');
             if(fileInput.files.length > 0) {
                 display.textContent = fileInput.files[0].name;
                 display.classList.remove('italic');
                 slot.querySelector('.remove-doc-button').classList.remove('hidden');
                 const uploadBtn = slot.querySelector('.upload-button');
                 if (uploadBtn) uploadBtn.classList.add('hidden');
             }
        }
    }
</script>

<?php include __DIR__ . '/../partials/agreement_builder.php'; ?>