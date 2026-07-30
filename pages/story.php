<?php
/**
 * Page: Configure Token Sale - Step 1: Tell Your Story
 * Filepath: /pages/story.php
 * UPDATED for the new `token_sale_pages` schema.
 */

// --- Refactor: Include the centralized wizard navigation system ---
require_once __DIR__ . '/../wizard_nav.php';

// --- Refactor: Define the current step for the navigation system ---
$current_main_step = 'private_sale';
$current_sub_step = 'story';

$pdo = require __DIR__ . '/../src/db.php';
$project_id = $_SESSION['active_project_id'] ?? null;
$page_error = null;
$dbProjectData = [];

if (empty($project_id)) {
    $page_error = "No active project. Please select a project from your dashboard.";
} else {
    try {
        // Mark tokenomics as done when entering this new flow
        $stmt = $pdo->prepare("UPDATE projet SET tokenomics_done = 1 WHERE id = ?");
        $stmt->execute([$project_id]);

        // --- FIX: Fetch from `token_sale_pages` using the correct `project_id` column ---
        $sql = "SELECT p.project_name, tsp.* FROM projet p LEFT JOIN token_sale_pages tsp ON p.id = tsp.project_id WHERE p.id = :project_id AND p.founder_id = :founder_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':project_id' => $project_id, ':founder_id' => $_SESSION['user_id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $dbProjectData = $data;
        } else {
            // It's possible no token_sale_pages record exists yet, which is fine.
            // We just need the project name.
            $stmtProject = $pdo->prepare("SELECT project_name FROM projet WHERE id = ?");
            $stmtProject->execute([$project_id]);
            $projectName = $stmtProject->fetchColumn();
            if(!$projectName) {
                 $page_error = "Project not found or you do not have permission to access it.";
            } else {
                $dbProjectData['project_name'] = $projectName;
            }
        }
    } catch (PDOException $e) {
        $page_error = "Database error: " . $e->getMessage();
        error_log("pages/story.php DB Error: " . $e->getMessage());
    }

    // Fetch existing project wallets for the dropdown
    $projectWallets = [];
    if (!$page_error) {
        $walletsStmt = $pdo->prepare("SELECT label, wallet_address FROM project_wallet WHERE projet_id = ? ORDER BY label ASC");
        $walletsStmt->execute([$project_id]);
        $projectWallets = $walletsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-gray-50">
    <div class="w-full max-w-6xl">

        <?php render_main_stepper($current_main_step); ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow border border-gray-200">
            
            <?php render_sub_stepper($current_main_step, $current_sub_step); ?>
            
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Tell your Project's Story</h1>
            <p class="text-gray-600 text-sm mb-8">Provide the core narrative for your project. Showcase your unique value, introduce your team and partners, answer common questions, highlight key metrics, and link your social channels to build a compelling and trustworthy presence.</p>

            <?php 
            $session_error = $_SESSION['error_message'] ?? null;
            if ($session_error) { unset($_SESSION['error_message']); }
            $display_error = $page_error ?: $session_error;
            ?>

            <?php if ($display_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($display_error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$page_error): ?>
                <form id="storyForm" action="/backend/story_backend.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold text-gray-800">Sale Settings</h2>
                        <p class="text-xs text-purple-700 bg-purple-50 p-2.5 rounded border border-purple-200 mt-2 mb-4">
                            <i data-lucide="info" class="inline-block w-4 h-4 mr-1 align-text-bottom"></i>
                            These settings apply specifically to <strong>this private sale room</strong> (not the overall multi-round project funding plan).
                        </p>
                        <div class="mb-4 mt-4">
                            <label for="sale_name" class="form-label">Sale Name<span class="text-red-500">*</span></label>
                            <input type="text" id="sale_name" name="sale_name" class="form-input" required placeholder="e.g., Early Contributors Round" value="<?php echo htmlspecialchars($dbProjectData['sale_name'] ?? ''); ?>">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 mt-4">
                            <div>
                                <label for="soft_cap_usd" class="form-label">Min Raise (Soft Cap) USD for this sale<span class="text-red-500">*</span></label>
                                <input type="number" id="soft_cap_usd" name="soft_cap_usd" class="form-input" required placeholder="e.g., 50000" value="<?php echo htmlspecialchars($dbProjectData['soft_cap_usd'] ?? ''); ?>" min="0" step="any">
                                <p class="text-xs text-gray-500 mt-1">Soft cap target for this sale room.</p>
                            </div>
                            <div>
                                <label for="hard_cap_usd" class="form-label">Max Raise (Hard Cap) USD for this sale<span class="text-red-500">*</span></label>
                                <input type="number" id="hard_cap_usd" name="hard_cap_usd" class="form-input" required placeholder="e.g., 200000" value="<?php echo htmlspecialchars($dbProjectData['hard_cap_usd'] ?? ''); ?>" min="0" step="any">
                                <p class="text-xs text-gray-500 mt-1">Hard cap limit for this sale room.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 mt-4">
                            <div>
                                <label for="min_investment_usd" class="form-label">Min Purchase per Investor USD</label>
                                <input type="number" id="min_investment_usd" name="min_investment_usd" class="form-input" placeholder="e.g., 100" value="<?php echo htmlspecialchars($dbProjectData['min_investment_usd'] ?? ''); ?>" min="0" step="any">
                                <p class="text-xs text-gray-500 mt-1">Minimum contribution allowed per ticket.</p>
                            </div>
                            <div>
                                <label for="max_investment_usd" class="form-label">Max Purchase per Investor USD</label>
                                <input type="number" id="max_investment_usd" name="max_investment_usd" class="form-input" placeholder="e.g., 10000" value="<?php echo htmlspecialchars($dbProjectData['max_investment_usd'] ?? ''); ?>" min="0" step="any">
                                <p class="text-xs text-gray-500 mt-1">Maximum contribution allowed per ticket.</p>
                            </div>
                        </div>
                        <div class="mb-4 mt-4">
                            <?php
                            $isNewForm = empty($dbProjectData['sale_name']);
                            $isGnosis = !empty($dbProjectData['gnosis_safe_address']) || $isNewForm;
                            $isEscrow = empty($dbProjectData['gnosis_safe_address']) && !$isNewForm;
                            ?>
                            <label class="form-label">Vault Setup<span class="text-red-500">*</span></label>
                            <div class="flex flex-col gap-3 mt-2">
                                <!-- Prominent Direct Gnosis -->
                                <label class="flex items-start gap-3 cursor-pointer p-4 border-2 <?php echo $isGnosis ? 'border-purple-500 bg-purple-50' : 'border-gray-200 bg-white'; ?> rounded-xl hover:bg-purple-50 transition-colors relative shadow-sm" id="label-gnosis">
                                    <div class="absolute top-0 right-0 -mt-2 -mr-2 bg-gradient-to-r from-purple-600 to-cyan-500 text-white text-[10px] uppercase font-bold px-2 py-0.5 rounded shadow">Recommended</div>
                                    <input type="radio" name="vault_type" value="gnosis" class="mt-1 w-4 h-4 text-purple-600 focus:ring-purple-500" required <?php if($isGnosis) echo 'checked'; ?>> 
                                    <div>
                                        <span class="block font-bold text-gray-900 text-base">Direct Gnosis</span>
                                        <span class="block text-sm text-gray-600 mt-1">Funds are routed instantly and directly to your project's Gnosis Safe multi-sig wallet upon investment. You retain full control.</span>
                                    </div>
                                </label>
                                <!-- De-emphasized Vault -->
                                <label class="flex items-start gap-3 cursor-pointer p-3 border border-gray-200 rounded-lg hover:bg-gray-100 bg-gray-50 transition-colors opacity-75 hover:opacity-100" id="label-escrow">
                                    <input type="radio" name="vault_type" value="escrow" class="mt-1" required <?php if($isEscrow) echo 'checked'; ?>> 
                                    <div>
                                        <span class="block font-medium text-gray-700">Tookle Smart Vault</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">Funds are held in a secure, immutable smart contract. Investors can claim automatic refunds if the Soft Cap is missed.</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="mb-4 <?php echo $isGnosis ? '' : 'hidden'; ?>" id="gnosis_input">
                            <label for="gnosis_safe_address" class="form-label">Gnosis Safe Address<span class="text-red-500">*</span></label>
                            
                            <div class="flex flex-col gap-2 mt-1">
                                <select id="wallet_selector" onchange="handleWalletSelect()" class="w-full p-4 bg-white border border-gray-200 rounded-xl text-sm focus:ring-1 focus:ring-purple-500 outline-none transition cursor-pointer">
                                    <option value="">Select a wallet from your address book...</option>
                                    <?php foreach ($projectWallets as $pw): ?>
                                        <option value="<?php echo htmlspecialchars($pw['wallet_address']); ?>" <?php if (($dbProjectData['gnosis_safe_address'] ?? '') === $pw['wallet_address']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($pw['label']); ?> (<?php echo substr($pw['wallet_address'], 0, 6) . '...' . substr($pw['wallet_address'], -4); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="MANUAL" <?php if(!empty($dbProjectData['gnosis_safe_address']) && !in_array($dbProjectData['gnosis_safe_address'], array_column($projectWallets, 'wallet_address'))) echo 'selected'; ?>>Enter a new address manually...</option>
                                </select>
                                
                                <input type="text" id="gnosis_safe_address" name="gnosis_safe_address" class="form-input font-mono text-sm <?php echo (!empty($dbProjectData['gnosis_safe_address']) && !in_array($dbProjectData['gnosis_safe_address'], array_column($projectWallets, 'wallet_address'))) ? '' : 'hidden'; ?>" placeholder="0x..." value="<?php echo htmlspecialchars($dbProjectData['gnosis_safe_address'] ?? ''); ?>" pattern="^0x[a-fA-F0-9]{40}$" title="Must be a valid 42-character Ethereum address starting with 0x" maxlength="42">
                            </div>
                            
                            <p class="text-xs text-gray-500 mt-1">Please enter your project's Gnosis Safe multi-sig wallet address (must be a valid 42-character Ethereum address starting with "0x").</p>
                            <p id="gnosis_error" class="text-xs text-red-500 mt-1 hidden font-semibold bg-red-50 p-2 rounded border border-red-100">Invalid format! Address must be exactly 42 characters and start with 0x.</p>
                        </div>
                        <div class="mt-6 mb-2 p-4 bg-blue-50 text-blue-800 text-sm rounded-lg flex items-start gap-3 border border-blue-100">
                            <i data-lucide="info" class="w-5 h-5 flex-shrink-0 text-blue-500 mt-0.5"></i>
                            <p><strong>Note:</strong> You are currently setting up your <em>first</em> token sale room. Once this is complete, you will be able to easily launch additional sales for other rounds (like Seed or Public) directly from your Dashboard.</p>
                        </div>
                    </section>
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold text-gray-800">Your Project Narrative</h2>
                        <div class="mb-4 mt-4">
                            <label for="project-description" class="form-label">Project Description<span class="text-red-500">*</span></label>
                            <textarea id="project-description" name="project_description" rows="5" class="form-textarea" required><?php echo htmlspecialchars($dbProjectData['project_description_story'] ?? ''); ?></textarea>
                        </div>
                         <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch mt-6">
                            <div>
                                <label class="form-label">Teaser Video</label>
                                <div class="dropzone" id="video_dropzone">
                                    <i data-lucide="video" class="w-8 h-8 text-gray-400 mb-2"></i>
                                    <span class="font-semibold text-sm text-purple-600">Upload File</span>
                                    <p class="text-xs text-gray-500 mt-1">MP4/WebM, Max 50MB (Teaser &lt; 50MB)</p>
                                    <input type="file" name="video_file" class="hidden" accept="video/mp4, video/webm" data-existing-file="<?php echo htmlspecialchars($dbProjectData['video_file_path'] ?? ''); ?>">
                                    <div class="existing-file-name"></div>
                                </div>
                                <p id="video_too_heavy_note" class="text-xs font-semibold text-red-600 mt-2 hidden bg-red-50 p-2 rounded border border-red-200 flex items-center gap-1.5">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500 shrink-0"></i>
                                    <span>⚠️ File too heavy! Please upload a teaser video smaller than 50MB.</span>
                                </p>
                            </div>
                             <div>
                                <label class="form-label">Hero Image</label>
                                <div class="dropzone" id="hero_image_dropzone"><i data-lucide="image" class="w-8 h-8 text-gray-400 mb-2"></i><span class="font-semibold text-sm text-purple-600">Upload File</span><p class="text-xs text-gray-500 mt-1">JPG/PNG, Max 2MB</p><input type="file" name="hero_image_file" class="hidden" accept="image/*" data-existing-file="<?php echo htmlspecialchars(json_decode($dbProjectData['general_images_json'] ?? '[]')[0] ?? ''); ?>"><div class="existing-file-name"></div></div>
                            </div>
                             <div>
                                <label class="form-label">White Paper</label>
                                <div class="dropzone" id="whitepaper_dropzone"><i data-lucide="file-text" class="w-8 h-8 text-gray-400 mb-2"></i><span class="font-semibold text-sm text-purple-600">Upload File</span><p class="text-xs text-gray-500 mt-1">PDF, Max 10MB</p><input type="file" name="whitepaper_file" class="hidden" accept=".pdf" data-existing-file="<?php echo htmlspecialchars($dbProjectData['whitepaper_file_path'] ?? ''); ?>"><div class="existing-file-name"></div></div>
                            </div>
                        </div>
                    </section>
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Key Metrics & Community</h2>
                        <div id="community-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-community-button">Add metric</button>
                    </section>
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Why <?php echo htmlspecialchars($dbProjectData['project_name'] ?? 'Your Project'); ?>?</h2>
                        <div id="value-props-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-value-prop-button">Add Value Proposition</button>
                    </section>

                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Socials</h2>
                        <div id="socials-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-social-button">Add social</button>
                    </section>

                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Team</h2>
                        <div id="team-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-team-button">Add team member</button>
                    </section>

                    <section class="form-section">
                        <div class="flex items-center mb-4"><input type="checkbox" id="include-partners-checkbox" name="include_partners_toggle" value="1" class="h-4 w-4 rounded"><label for="include-partners-checkbox" class="ml-2">Include Partners Section</label></div>
                        <div id="partners-details-container" class="hidden">
                            <h2 class="text-lg font-semibold mb-4">Partners</h2>
                            <div id="partners-container" class="space-y-4"></div>
                            <button type="button" class="add-button mt-4" id="add-partner-button">Add partner</button>
                        </div>
                    </section>
                    
                    <div class="flex justify-between items-center pt-6 mt-8">
                         <a href="<?= get_url('fundraising') ?>" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50">Back</a>
                        <button type="submit" class="px-8 py-2.5 bg-gradient-to-r from-purple-700 to-cyan-500 text-white rounded-lg font-medium">Save and Continue</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<style>
.form-label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem; }
.form-input, .form-textarea, .form-select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
.form-section { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; }
.dropzone { border: 2px dashed #d1d5db; border-radius: 0.5rem; padding: 1rem; text-align: center; cursor: pointer; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 120px; }
.existing-file-name { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }
.repeater-item { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; background-color: #ffffff; position: relative; }
.add-button { display: inline-flex; align-items: center; font-size: 0.875rem; font-weight: 500; background-color: white; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
.remove-button { position: absolute; top: 0.75rem; right: 0.75rem; color: #ef4444; background: none; border: none; padding: 0.25rem; cursor: pointer; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    const dbData = <?php echo json_encode($dbProjectData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function setupDropzone(dropzoneElem) {
        const input = dropzoneElem.querySelector('input[type="file"]');
        const existingFileDiv = dropzoneElem.querySelector('.existing-file-name');
        if (!input || !existingFileDiv) return;
        
        function updateDisplay() {
            let fileName = '';
            const tooHeavyNote = document.getElementById('video_too_heavy_note');
            if (input.files.length > 0) {
                const file = input.files[0];
                const fileSizeMB = file.size / (1024 * 1024);
                let maxMB = 50;
                if (input.name === 'hero_image_file') maxMB = 2;
                if (input.name === 'whitepaper_file') maxMB = 10;
                if (input.name === 'video_file') maxMB = 50;

                if (fileSizeMB > maxMB) {
                    if (input.name === 'video_file' && tooHeavyNote) {
                        tooHeavyNote.classList.remove('hidden');
                    } else {
                        alert(`⚠️ File too heavy!\n"${file.name}" is ${fileSizeMB.toFixed(1)}MB.\nMaximum allowed size is ${maxMB}MB.`);
                    }
                    input.value = ''; // Reset input so file > 50MB CANNOT be submitted
                    fileName = '';
                } else {
                    if (input.name === 'video_file' && tooHeavyNote) {
                        tooHeavyNote.classList.add('hidden');
                    }
                    fileName = file.name + ' (' + fileSizeMB.toFixed(1) + 'MB)';
                }
            } 
            else if (input.dataset.existingFile) { 
                fileName = input.dataset.existingFile.split('/').pop(); 
            }
            existingFileDiv.textContent = fileName;
        }

        dropzoneElem.addEventListener('click', (e) => {
            if (e.target.tagName !== 'INPUT') {
                input.click();
            }
        });
        input.addEventListener('change', updateDisplay);
        updateDisplay();
    }
    document.querySelectorAll('.dropzone').forEach(setupDropzone);

    // --- HTML5 Validation Guidance UX ---
    const storyForm = document.getElementById('storyForm');
    if (storyForm) {
        let firstInvalidField = null;
        storyForm.addEventListener('invalid', (e) => {
            if (!firstInvalidField) {
                firstInvalidField = e.target;
                firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalidField.focus();
                firstInvalidField.classList.add('border-red-500', 'bg-red-50');
                setTimeout(() => {
                    alert('⚠️ Please fill in all required fields (highlighted in red) before continuing.');
                    firstInvalidField = null;
                }, 300);
            }
        }, true);
    }

    // --- Vault Type Toggle ---
    const vaultRadios = document.querySelectorAll('input[name="vault_type"]');
    const gnosisInputContainer = document.getElementById('gnosis_input');
    const gnosisInput = document.getElementById('gnosis_safe_address');
    const gnosisError = document.getElementById('gnosis_error');
    
    vaultRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            const labelGnosis = document.getElementById('label-gnosis');
            const labelEscrow = document.getElementById('label-escrow');
            
            if (e.target.value === 'gnosis') {
                gnosisInputContainer.classList.remove('hidden');
                gnosisInput.required = true;
                labelGnosis.classList.add('border-purple-500', 'bg-purple-50');
                labelGnosis.classList.remove('border-gray-200', 'bg-white');
                
                labelEscrow.classList.add('opacity-75', 'bg-gray-50');
                labelEscrow.classList.remove('opacity-100', 'bg-white');
            } else {
                gnosisInputContainer.classList.add('hidden');
                gnosisInput.required = false;
                gnosisInput.value = '';
                gnosisError.classList.add('hidden');
                gnosisInput.classList.remove('border-red-500', 'bg-red-50');
                
                labelGnosis.classList.remove('border-purple-500', 'bg-purple-50');
                labelGnosis.classList.add('border-gray-200', 'bg-white');
                
                labelEscrow.classList.remove('opacity-75', 'bg-gray-50');
                labelEscrow.classList.add('opacity-100', 'bg-white');
            }
        });
    });

    gnosisInput.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        if (val.length > 0 && !/^0x[a-fA-F0-9]{40}$/.test(val)) {
            gnosisError.classList.remove('hidden');
            e.target.classList.add('border-red-500', 'bg-red-50');
        } else {
            gnosisError.classList.add('hidden');
            e.target.classList.remove('border-red-500', 'bg-red-50');
        }
    });

    function addRepeaterItem(container, templateFn, data = {}) {
        const uniqueId = Date.now() + Math.random().toString(36).substr(2, 5);
        const newItem = templateFn(uniqueId, data);
        container.appendChild(newItem);
        lucide.createIcons({ nodes: [newItem.querySelector('.remove-button i')] });
        newItem.querySelector('.remove-button').addEventListener('click', () => newItem.remove());
        if (newItem.querySelector('.dropzone')) {
            setupDropzone(newItem.querySelector('.dropzone'));
        }
    }

    function initializeRepeater(containerId, addButtonId, templateFn, initialData) {
        const container = document.getElementById(containerId);
        const addButton = document.getElementById(addButtonId);
        if (!container || !addButton) return;
        container.innerHTML = '';
        const dataArray = (typeof initialData === 'string') ? JSON.parse(initialData || '[]') : (initialData || []);
        if (dataArray.length > 0) {
            dataArray.forEach(item => addRepeaterItem(container, templateFn, item));
        } else {
            addRepeaterItem(container, templateFn);
        }
        addButton.addEventListener('click', (e) => {
            e.preventDefault();
            addRepeaterItem(container, templateFn);
        });
    }

    const createValuePropItem = (index, data = {}) => {
        const div = document.createElement('div'); div.className = 'repeater-item';
        div.innerHTML = `<label class="form-label">Title</label><input type="text" name="value_props[${index}][title]" class="form-input mb-2" value="${data.title || ''}"><label class="form-label">Description</label><textarea name="value_props[${index}][description]" class="form-textarea">${data.description || ''}</textarea><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createCommunityItem = (index, data = {}) => {
        const div = document.createElement('div'); div.className = 'repeater-item grid grid-cols-2 gap-4';
        div.innerHTML = `<div><label class="form-label">Indicator</label><input type="text" name="community_metrics[${index}][indicator]" class="form-input" value="${data.indicator || ''}"></div><div><label class="form-label">Value</label><input type="text" name="community_metrics[${index}][value]" class="form-input" value="${data.value || ''}"></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createSocialItem = (index, data = {}) => {
        const div = document.createElement('div'); div.className = 'repeater-item grid grid-cols-2 gap-4';
        const platforms = ['Website', 'Twitter', 'Linkedin', 'Telegram', 'Discord', 'Youtube', 'Medium', 'Other'];
        const options = platforms.map(p => `<option value="${p}" ${data.platform_select === p ? 'selected' : ''}>${p}</option>`).join('');
        div.innerHTML = `<div><label class="form-label">Platform</label><select name="socials[${index}][platform_select]" class="form-select">${options}</select></div><div><label class="form-label">URL</label><input type="url" name="socials[${index}][url]" class="form-input" value="${data.url || ''}" placeholder="https://..."></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createTeamItem = (index, data = {}) => {
        const div = document.createElement('div');
        div.className = 'repeater-item grid grid-cols-1 md:grid-cols-3 gap-4 items-start';
        const picturePath = data.picture_file_path || '';
        div.innerHTML = `<div><label class="form-label">Name</label><input type="text" name="team[${index}][name]" class="form-input" value="${data.name || ''}"><input type="hidden" name="team[${index}][existing_picture_path]" value="${picturePath}"></div><div><label class="form-label">Role</label><input type="text" name="team[${index}][role]" class="form-input" value="${data.role || ''}"></div><div><label class="form-label">Picture</label><div class="dropzone"><i data-lucide="image" class="w-6 h-6 text-gray-400"></i><input type="file" name="team[${index}][picture]" class="hidden" accept="image/*" data-existing-file="${picturePath}"><div class="existing-file-name"></div></div></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createPartnerItem = (index, data = {}) => {
        const div = document.createElement('div');
        div.className = 'repeater-item grid grid-cols-1 md:grid-cols-3 gap-4 items-start';
        const logoPath = data.logo_file_path || '';
        div.innerHTML = `<div><label class="form-label">Name</label><input type="text" name="partners[${index}][name]" class="form-input" value="${data.name || ''}"><input type="hidden" name="partners[${index}][existing_logo_path]" value="${logoPath}"></div><div><label class="form-label">Website</label><input type="url" name="partners[${index}][website]" class="form-input" value="${data.website || ''}" placeholder="https://..."></div><div><label class="form-label">Logo</label><div class="dropzone"><i data-lucide="image" class="w-6 h-6 text-gray-400"></i><input type="file" name="partners[${index}][logo]" class="hidden" accept="image/*" data-existing-file="${logoPath}"><div class="existing-file-name"></div></div></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };

    initializeRepeater('value-props-container', 'add-value-prop-button', createValuePropItem, dbData.value_props_json);
    initializeRepeater('community-container', 'add-community-button', createCommunityItem, dbData.community_metrics_json);
    initializeRepeater('socials-container', 'add-social-button', createSocialItem, dbData.socials_json);
    initializeRepeater('team-container', 'add-team-button', createTeamItem, dbData.team_json);
    initializeRepeater('partners-container', 'add-partner-button', createPartnerItem, dbData.partners_json);

    const partnersCheckbox = document.getElementById('include-partners-checkbox');
    const partnersContainer = document.getElementById('partners-details-container');
    partnersCheckbox.checked = dbData.partners_json && JSON.parse(dbData.partners_json).length > 0;
    partnersContainer.classList.toggle('hidden', !partnersCheckbox.checked);
    partnersCheckbox.addEventListener('change', () => {
        partnersContainer.classList.toggle('hidden', !partnersCheckbox.checked);
    });
    
    // Trigger select initialization on load
    handleWalletSelect();
});
</script>
<script>
function handleWalletSelect() {
    const selector = document.getElementById('wallet_selector');
    const manualInput = document.getElementById('gnosis_safe_address');
    if (!selector || !manualInput) return;
    
    if (selector.value === 'MANUAL') {
        manualInput.classList.remove('hidden');
        // If it was already manual on page load, keep the value, else clear it
        if (!manualInput.value || selector.options[selector.selectedIndex].defaultSelected === false) {
            manualInput.value = ''; 
        }
        manualInput.focus();
    } else if (selector.value !== '') {
        manualInput.classList.add('hidden');
        manualInput.value = selector.value;
    } else {
        manualInput.classList.add('hidden');
        manualInput.value = '';
    }
}
</script>
