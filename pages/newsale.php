<?php
// newsale.php - The Front-End Page for Creating a Token Sale
// Refactored: Logic moved to js/newsale.js

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for required project ID
$project_uuid = $_SESSION['active_project_id'] ?? null;
if (empty($project_uuid)) {
    header("Location: /dashboard.php");
    exit;
}

// --- FETCH CORE TOKEN & SCENARIO DATA (PHP Fallback) ---
require_once __DIR__ . '/../src/db.php';

$token_sale_data = [
    'round_name' => 'TBA', 
    'ticker' => 'TBA', 
    'round_price' => 'TBA', 
    'total_supply' => 'TBA', 
    'vesting_text' => 'TBA',
    'scenario_label' => ''
];

$scenario_data_decoded = []; // Holder for the full scenario JSON
$active_agreement_data = null; // Holder for the active agreement

try {
    // 1. Fetch Token Details
    $stmt_proj = $pdo->prepare("SELECT token_ticker, supply_value, type_supply FROM projet WHERE id = ?");
    $stmt_proj->execute([$project_uuid]);
    $project_info = $stmt_proj->fetch(PDO::FETCH_ASSOC);

    if ($project_info) {
        $token_sale_data['ticker'] = $project_info['token_ticker'] ?? 'TBA';
        $supply = $project_info['supply_value'] ? number_format($project_info['supply_value']) : 'TBA';
        $type = $project_info['type_supply'] ?? '';
        $token_sale_data['total_supply'] = "$supply $type";
    }

    // 2. Fetch Active Scenario Label AND Data (FIX: Now fetching 'data' column AND 'id')
    // UPDATED: Added 'id' to the select query to pass to the form
    $stmt_scenario = $pdo->prepare("SELECT id, version_label, data FROM scenario_version WHERE projet_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt_scenario->execute([$project_uuid]);
    $scenario_row = $stmt_scenario->fetch(PDO::FETCH_ASSOC);
    
    if ($scenario_row) {
        $token_sale_data['scenario_label'] = $scenario_row['version_label'];
        // Decode the JSON data so we can pass it to the frontend
        $scenario_data_decoded = json_decode($scenario_row['data'], true);
    }

    // 3. Fetch Active Agreement Version
    $stmt_agreement = $pdo->prepare("SELECT id, content, file_url, version FROM agreement_versions WHERE projet_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt_agreement->execute([$project_uuid]);
    $agreement_row = $stmt_agreement->fetch(PDO::FETCH_ASSOC);

    if ($agreement_row) {
        $active_agreement_data = [
            'id' => $agreement_row['id'],
            'version' => $agreement_row['version'],
            'file_url' => $agreement_row['file_url'],
            // Decode content if it exists, otherwise null
            'content' => !empty($agreement_row['content']) ? json_decode($agreement_row['content'], true) : null
        ];
    }

} catch (Exception $e) {
    error_log("Newsale Data Fetch Error: " . $e->getMessage());
}

$current_project_id = $project_uuid;
?>

<!-- Import for Country Search Logic -->
<script src="/js/countries.js"></script>

<style>
    :root { --tookle-purple: #6D28D9; --tookle-cyan: #06B6D4; }
    body { font-family: 'Montserrat', sans-serif; background-color: #f9fafb; }
    .form-label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem; }
    .form-input, .form-textarea, .form-select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; }
    .form-input:focus, .form-textarea:focus, .form-select:focus { outline: 1px solid transparent; border-color: var(--tookle-purple); box-shadow: 0 0 0 1px var(--tookle-purple); }
    .currency-input-container { position: relative; display: flex; align-items: center; }
    .currency-symbol { position: absolute; left: 0.75rem; color: #6b7280; pointer-events: none; }
    .form-input-currency { padding-left: 1.75rem !important; }
    
    /* Simplified Dropzone Styles */
    .dropzone { 
        border: 2px dashed #d1d5db; 
        border-radius: 0.5rem; 
        padding: 1rem; 
        text-align: center; 
        cursor: pointer; 
        transition: all 0.2s ease;
        min-height: 120px; 
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background-color: #ffffff;
        position: relative;
        overflow: hidden; /* Ensure images stay within border */
    }
    .dropzone:hover { background-color: #f9fafb; border-color: var(--tookle-purple); }
    .dropzone.has-file { border-style: solid; border-color: #d1d5db; background-color: #f9fafb; }
    
    .repeater-item { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; background-color: #ffffff; position: relative; }
    .add-button { display: inline-flex; align-items: center; font-size: 0.875rem; font-weight: 500; color: #374151; background-color: white; border: 1px solid #e5e7eb; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
    .remove-button { position: absolute; top: 0.75rem; right: 0.75rem; color: #ef4444; background: none; border: none; padding: 0.25rem; cursor: pointer; }
    
    /* Accordion Styles */
    .accordion-item { border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 1.5rem; background-color: white; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); overflow: hidden; }
    .accordion-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; cursor: pointer; background-color: white; transition: background-color 0.2s ease; }
    .accordion-header:hover { background-color: #f9fafb; }
    .accordion-header.is-open { border-bottom: 1px solid #e5e7eb; }
    .accordion-title { font-size: 1.25rem; font-weight: 600; color: #1f2937; }
    .accordion-icon { transition: transform 0.3s ease; }
    .accordion-header.is-open .accordion-icon { transform: rotate(180deg); }
    .accordion-panel { padding: 0 1.5rem; max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out, padding 0.3s ease; opacity: 0; }
    .accordion-panel.is-open { padding: 1.5rem; max-height: 5000px; opacity: 1; }

    .toast-notification { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background-color: #333; color: white; padding: 1rem 1.5rem; border-radius: 0.5rem; z-index: 1000; opacity: 0; transition: opacity 0.3s ease, top 0.3s ease; pointer-events: none; }
    .toast-notification.error { background-color: #ef4444; }
    .toast-notification.show { opacity: 1; top: 40px; }
    
    /* Compliance & Builder Styles */
    .document-upload-slot { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background-color: white; border: 1px solid #e5e7eb; border-radius: 0.375rem; margin-bottom: 0.75rem; }
    .upload-button { display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border: 1px solid #d1d5db; border-radius: 0.25rem; background-color: #f9fafb; color: #374151; cursor: pointer; }
    .file-name-display { font-size: 0.75rem; color: #6b7280; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .create-edit-button { display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 500; border: 1px solid #d1d5db; border-radius: 0.25rem; background-color: #f9fafb; color: #374151; cursor: pointer; }

    /* Modal Styles */
    .modal-overlay { position: fixed; inset: 0; background-color: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 0.3s ease; z-index: 1000; }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .modal-content { background-color: white; padding: 2rem; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-width: 500px; width: 90%; text-align: center; transform: scale(0.95); transition: transform 0.3s ease; }
    .modal-overlay.active .modal-content { transform: scale(1); }
    .btn-gradient { background-image: linear-gradient(to right, var(--tookle-purple), var(--tookle-cyan)); color: white; }
    
    /* Restriction Cards */
    .restriction-card { position: relative; border: 1px solid #e5e7eb; background-color: white; padding: 0.75rem 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.15s ease-in-out; display: flex; align-items: center; }
    .restriction-card:hover { border-color: #a78bfa; }
    .restriction-card.selected { border-color: var(--tookle-purple); background-color: #f3e8ff; border-width: 2px; padding: calc(0.75rem - 1px) calc(1rem - 1px); }
    .restriction-card .selected-check { display: none; margin-left: auto; color: var(--tookle-purple); }
    .restriction-card.selected .selected-check { display: block; }

    /* Round Detail Card */
    .round-detail-card { 
        background-color: #f9fafb; 
        border: 1px solid #e5e7eb; 
        border-radius: 0.5rem; 
        padding: 1.25rem; 
        margin-top: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
</style>

<div id="toast-container" class="toast-notification"></div>
<div class="p-8 md:p-12 max-w-4xl mx-auto">
    
    <form id="unifiedSaleForm" action="/backend/newsale_backend.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="projet_id" value="<?php echo htmlspecialchars($project_uuid); ?>">
        <input type="hidden" name="form_identifier" value="newsale_unified_sale">
        <!-- ADDED: Scenario Version ID input to ensure backend uses the correct version -->
        <input type="hidden" name="scenario_version_id" value="<?php echo htmlspecialchars($scenario_row['id'] ?? ''); ?>">
        <input type="hidden" name="doc_agreement_content" id="doc-agreement-content" value="<?php echo (!empty($active_agreement_data['content'])) ? htmlspecialchars(json_encode($active_agreement_data['content'])) : '[]'; ?>">
        
        <div class="mb-8 flex flex-col sm:flex-row items-center justify-between">
            <div>
                 <h2 class="text-2xl font-bold text-gray-800">Create a New Sale</h2>
                 <p class="text-gray-600 mt-1">Fill out the details below. Data from your last sale is loaded automatically if available.</p>
            </div>
            <button type="button" id="prefill-button" class="btn-secondary mt-3 sm:mt-0 flex items-center justify-center px-3 py-1.5 rounded-md font-medium text-xs shadow-sm hover:shadow">
                <i data-lucide="refresh-cw" class="w-3 h-3 mr-1.5"></i> Reload Last Sale Data
            </button>
        </div>

        <!-- Primary Details -->
        <div class="accordion-item">
            <div class="accordion-header is-open"><h2 class="accordion-title">Primary Details</h2><i data-lucide="chevron-down" class="accordion-icon"></i></div>
            <div class="accordion-panel is-open">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="sale_name" class="form-label">Sale Name<span class="text-red-500">*</span></label>
                        <input type="text" id="sale_name" name="sale_name" class="form-input" required>
                        <p id="sale-name-error" class="text-red-500 text-xs mt-1 h-4"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Where will this sale run?<span class="text-red-500">*</span></label>
                        <div class="mt-2 space-y-3">
                            <label class="flex items-center p-3 border border-gray-200 rounded-md">
                                <input type="radio" name="hosting" value="tookle" class="h-4 w-4 text-purple-600 border-gray-300 focus:ring-purple-500" required>
                                <span class="ml-3 text-sm font-medium text-gray-900">TOOKLE</span>
                            </label>
                            
                            <!-- Sub-section: Direct Gnosis Routing vs. On-Chain Escrow -->
                            <div id="tookle-settlement-details" class="hidden pl-8 pt-2 space-y-4">
                                <label class="form-label text-xs font-semibold text-slate-700">Settlement Trust Model</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Card 1: Direct Gnosis Safe -->
                                    <label class="relative flex flex-col p-4 border-2 border-slate-900 bg-slate-50/20 rounded-lg cursor-pointer transition-all" id="label-routing-multisig">
                                        <input type="radio" name="payment_routing" value="multisig" class="sr-only" checked>
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-bold text-xs text-slate-900">Direct Gnosis Safe</span>
                                            <span class="text-[9px] bg-slate-900 text-white px-2 py-0.5 rounded font-medium">Direct Settle</span>
                                        </div>
                                        <p class="text-[10px] text-slate-500">Funds route directly to your Gnosis Safe. Settle the 3.5% TOOKLE fee on-chain post-sale.</p>
                                    </label>

                                    <!-- Card 2: On-Chain Escrow -->
                                    <label class="relative flex flex-col p-4 border border-slate-200 rounded-lg cursor-pointer transition-all hover:bg-slate-50/50" id="label-routing-escrow">
                                        <input type="radio" name="payment_routing" value="escrow" class="sr-only">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-bold text-xs text-slate-900">On-Chain Escrow</span>
                                            <span class="text-[9px] bg-slate-100 text-slate-800 px-2 py-0.5 rounded font-medium">Trust Gated</span>
                                        </div>
                                        <p class="text-[10px] text-slate-500">Capital is locked in a smart contract escrow and released as milestones are completed. Automated 3.5% success fee.</p>
                                    </label>
                                </div>
                                
                                <div id="gnosis-address-container" class="block">
                                    <label for="gnosis_safe_address" class="form-label text-xs">Gnosis Safe Address (Base Network - USDC/USDT)<span class="text-red-500">*</span></label>
                                    <input type="text" id="gnosis_safe_address" name="gnosis_safe_address" class="form-input text-xs" placeholder="e.g. 0x71C2345678901234567890123456789012345678" required>
                                    <p id="gnosis-address-status" class="text-[10px] text-red-500 mt-1 hidden"></p>
                                    <p class="text-[10px] text-slate-400 mt-1">Once live, this destination address is locked and cannot be edited.</p>
                                    <p class="text-[10px] text-slate-500 font-semibold mt-1">⚠️ Note: TOOKLE success fee (3.5%) must be paid on-chain before the token claim portal is unlocked for investors.</p>
                                </div>
                            </div>

                            <label class="flex items-center p-3 border border-gray-200 rounded-md">
                                <input type="radio" name="hosting" value="external" class="h-4 w-4 text-purple-600 border-gray-300 focus:ring-purple-500" required>
                                <span class="ml-3 text-sm font-medium text-gray-900">External Platform</span>
                            </label>
                            <div id="external-platform-details" class="hidden pl-8 pt-2">
                                <label for="external_platform_name" class="form-label">Platform Name/URL<span class="text-red-500">*</span></label>
                                <input type="text" id="external_platform_name" name="external_platform_name" class="form-input" placeholder="e.g., Binance Launchpad">
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                         <label class="form-label">Campaign Duration<span class="text-red-500">*</span>
                            <span class="tooltip-trigger inline-flex items-center ml-1 text-gray-400 hover:text-gray-600 cursor-pointer" title="How long the vault will accept funds."><i data-lucide="info" class="w-3 h-3"></i></span>
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <input type="number" id="duration_value" class="form-input" placeholder="e.g. 7" min="1" required>
                            </div>
                            <div class="col-span-1">
                                <select id="duration_unit" class="form-select">
                                    <option value="days" selected>Days</option>
                                    <option value="hours">Hours</option>
                                    <option value="minutes">Minutes</option>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="duration_seconds" id="duration_seconds" value="604800">
                    </div>
                    
                    <div id="external-specific-fields" class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                        <div><label class="form-label">Start Date</label><input type="datetime-local" id="sale_launch_at" name="sale_launch_at" class="form-input"></div>
                        <div><label class="form-label">End Date</label><input type="datetime-local" id="sale_end_at" name="sale_end_at" class="form-input"></div>
                        <div>
                            <label class="form-label">Sale Status</label>
                            <select id="external_status" name="external_status" class="form-select">
                                <option value="draft">Draft</option><option value="live">Live</option><option value="successful">Successful</option><option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label">Country*
                            <span class="tooltip-trigger inline-flex items-center ml-1 text-gray-400 hover:text-gray-600 cursor-pointer" title="The primary jurisdiction the sale is being conducted from."><i data-lucide="info" class="w-3 h-3"></i></span>
                        </label>
                        <div id="country-select-wrapper" class="relative">
                            <input type="text" id="country-search" class="form-input w-full" placeholder="Type to search country..." autocomplete="off">
                            <input type="hidden" id="country" name="country" required>
                            <div id="country-dropdown" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto hidden"></div>
                            <p id="country-error" class="text-red-500 text-xs mt-1 h-4"></p>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="form-label">
                            Select Round
                            <?php if (!empty($token_sale_data['scenario_label'])): ?>
                                <span class="text-gray-400 font-normal ml-1 text-xs">(<?php echo htmlspecialchars($token_sale_data['scenario_label']); ?>)</span>
                            <?php endif; ?>
                            <span class="text-red-500">*</span>
                        </label>
                        <select id="select-round" name="selected_round_id" class="form-select" required></select>
                        <div id="round-details-display" class="mt-3 hidden"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sale Configuration -->
        <div class="accordion-item">
            <div class="accordion-header is-open"><h2 class="accordion-title">Sale Configuration</h2><i data-lucide="chevron-down" class="accordion-icon"></i></div>
            <div class="accordion-panel is-open">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label">Min Raise (Soft Cap)
                            <span class="tooltip-trigger inline-flex items-center ml-1 text-gray-400 hover:text-gray-600 cursor-pointer" title="The minimum total amount required to be raised for the sale to be considered successful."><i data-lucide="info" class="w-3 h-3"></i></span>
                        </label>
                        <div class="currency-input-container"><span class="currency-symbol">$</span><input type="text" id="soft_cap" name="soft_cap" class="form-input form-input-currency"></div>
                    </div>
                    <div>
                        <label class="form-label">Maximum Raise (Hard Cap)
                            <span class="tooltip-trigger inline-flex items-center ml-1 text-gray-400 hover:text-gray-600 cursor-pointer" title="The maximum total amount that can be raised during the sale. Once this cap is met, the sale ends."><i data-lucide="info" class="w-3 h-3"></i></span>
                        </label>
                        <div class="currency-input-container"><span class="currency-symbol">$</span><input type="text" id="target_raise" name="target_raise" class="form-input form-input-currency"></div>
                    </div>
                    
                    <div id="min-purchase-container">
                        <label class="form-label">Min Purchase (USD)*</label>
                        <div class="currency-input-container"><span class="currency-symbol">$</span><input type="text" id="min-purchase" name="min_purchase" class="form-input form-input-currency" required></div>
                    </div>
                    <div id="max-purchase-container">
                        <label class="form-label">Max Purchase (USD)*</label>
                        <div class="currency-input-container"><span class="currency-symbol">$</span><input type="text" id="max-purchase" name="max_purchase" class="form-input form-input-currency" required></div>
                        <p id="max-purchase-error" class="text-red-500 text-xs mt-1 h-4"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOOKLE ONLY FIELDS WRAPPER -->
        <div id="tookle-sale-fields">
            <!-- Project Story -->
            <div class="accordion-item">
                <div class="accordion-header"><h2 class="accordion-title">Project Story & Media</h2><i data-lucide="chevron-down" class="accordion-icon"></i></div>
                <div class="accordion-panel">
                    <div class="space-y-6">
                        <div><label class="form-label">Project Description*</label><textarea id="project-description" name="project_description" rows="4" class="form-textarea" required></textarea></div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div><label class="form-label">Teaser Video*</label><div class="dropzone" id="video_dropzone"><input type="file" name="video_file" class="hidden"><div class="text-sm">Upload</div><div class="existing-file-name text-xs text-gray-400"></div></div><input type="hidden" name="existing_video_path" id="existing_video_path"></div>
                            <div><label class="form-label">Hero Image*</label><div class="dropzone" id="hero_image_dropzone"><input type="file" name="hero_image_file" class="hidden"><div class="text-sm">Upload</div><div class="existing-file-name text-xs text-gray-400"></div></div><input type="hidden" name="existing_hero_image_path" id="existing_hero_image_path"></div>
                            <div><label class="form-label">White Paper*</label><div class="dropzone" id="whitepaper_dropzone"><input type="file" name="whitepaper_file" class="hidden"><div class="text-sm">Upload</div><div class="existing-file-name text-xs text-gray-400"></div></div><input type="hidden" name="existing_whitepaper_path" id="existing_whitepaper_path"></div>
                        </div>
                        <div><h3 class="text-lg font-semibold mb-2">Details</h3><div id="value-props-container"></div><button type="button" class="add-button mt-2" id="add-value-prop-button">Add Prop</button></div>
                        <div><h3 class="text-lg font-semibold mb-2">Metrics</h3><div id="community-container"></div><button type="button" class="add-button mt-2" id="add-community-button">Add Metric</button></div>
                        <div><h3 class="text-lg font-semibold mb-2">Team</h3><div id="team-container"></div><button type="button" class="add-button mt-2" id="add-team-button">Add Member</button></div>
                        <div><h3 class="text-lg font-semibold mb-2">Socials</h3><div id="socials-container"></div><button type="button" class="add-button mt-2" id="add-social-button">Add Social</button></div>
                        <div><h3 class="text-lg font-semibold mb-2">Partners</h3><div id="partners-container"></div><button type="button" class="add-button mt-2" id="add-partner-button">Add Partner</button></div>
                        <div><h3 class="text-lg font-semibold mb-2">FAQs</h3><div id="faq-container"></div><button type="button" class="add-button mt-2" id="add-faq-button">Add FAQ</button></div>
                    </div>
                </div>
            </div>

            <!-- Compliance -->
            <div class="accordion-item">
                <div class="accordion-header"><h2 class="accordion-title">Compliance</h2><i data-lucide="chevron-down" class="accordion-icon"></i></div>
                <div class="accordion-panel">
                    <section class="mb-8">
                        <div class="space-y-3" id="document-upload-list">
                            <div class="document-upload-slot" data-doc-type="agreement">
                                <label class="flex-grow font-medium text-sm text-gray-700">
                                    Token Purchase Agreement
                                    <span class="block text-xs text-gray-400 font-normal">Create, upload, or use existing.</span>
                                </label>
                                <div class="upload-area flex items-center gap-2">
                                    <span class="file-name-display italic text-gray-500" id="agreement-filename-display">
                                        <?php if (!empty($active_agreement_data['file_url'])): ?>
                                            Attached: <?php echo htmlspecialchars(basename($active_agreement_data['file_url'])); ?>
                                        <?php elseif (!empty($active_agreement_data['content'])): ?>
                                            Using Agreement Builder
                                        <?php else: ?>
                                            No agreement selected
                                        <?php endif; ?>
                                    </span>
                                    <input type="file" id="doc-agreement-upload" class="hidden" accept=".pdf" onchange="window.handleAgreementUpload(this)">
                                    <input type="hidden" name="existing_doc_agreement_path" id="existing_doc_agreement_path" value="<?php echo htmlspecialchars($active_agreement_data['file_url'] ?? ''); ?>">
                                    <button type="button" class="create-edit-button" id="open-agreement-modal-btn"><i data-lucide="file-text" class="w-3 h-3 mr-1"></i>View / Edit</button>
                                    <button type="button" class="remove-doc-button hidden" title="Remove file" onclick="removeFile(this)"><i data-lucide="x" class="w-4 h-4"></i></button>
                                </div>
                            </div>
                            <div class="document-upload-slot" data-doc-type="opinion"><label class="flex-grow font-medium text-sm text-gray-700">Legal Opinion</label><div class="upload-area flex items-center gap-2"><span class="file-name-display italic text-gray-500"></span><button type="button" class="upload-button" onclick="document.getElementById('doc-opinion').click()"><i data-lucide="upload" class="w-3 h-3 mr-1"></i>Upload</button><input type="file" id="doc-opinion" name="doc_opinion" class="hidden" accept=".pdf,.doc,.docx" onchange="updateFileNameDisplay(this)"><input type="hidden" name="existing_doc_opinion_path" id="existing_doc_opinion_path"></div></div>
                            <div class="document-upload-slot" data-doc-type="tos"><label class="flex-grow font-medium text-sm text-gray-700">Other (Terms)</label><div class="upload-area flex items-center gap-2"><span class="file-name-display italic text-gray-500"></span><button type="button" class="upload-button" onclick="document.getElementById('doc-tos').click()"><i data-lucide="upload" class="w-3 h-3 mr-1"></i>Upload</button><input type="file" id="doc-tos" name="doc_tos" class="hidden" accept=".pdf,.doc,.docx" onchange="updateFileNameDisplay(this)"><input type="hidden" name="existing_doc_tos_path" id="existing_doc_tos_path"></div></div>
                        </div>
                    </section>
                    <section class="grid grid-cols-1 md:grid-cols-2 gap-8 border-t border-gray-200 pt-6">
                        <div>
                            <h3 class="text-md font-semibold text-gray-800 mb-3">Restrictions</h3>
                            <!-- UPDATED: Hardcoded checkboxes with correct names to ensure backend receives them -->
                            <div id="restrictions-list" class="space-y-2 mb-4">
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="restriction_set[]" value="sanctioned" class="h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                    <span class="text-sm text-gray-700">Exclude Sanctioned Countries</span>
                                </label>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="restriction_set[]" value="us-non-accredited" class="h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                    <span class="text-sm text-gray-700">Exclude U.S. Non-Accredited</span>
                                </label>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="restriction_set[]" value="eu-consent" class="h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                                    <span class="text-sm text-gray-700">Require EU Consent</span>
                                </label>
                            </div>
                            
                            <h4 class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Custom Restrictions</h4>
                            <div id="custom-restrictions-container" class="space-y-2"></div>
                            <button type="button" id="add-custom-restriction-button" class="mt-4 text-sm text-purple-600 hover:underline flex items-center"><i data-lucide="plus" class="w-4 h-4 mr-1"></i> Add Custom Restriction</button>
                        </div>
                        <div>
                            <h3 class="text-md font-semibold text-gray-800 mb-1">KYC</h3>
                            <div class="mt-4 p-4 bg-gray-50 rounded-md border"><label class="flex items-center"><input type="checkbox" id="kyc-verification" name="kyc_verification" class="h-4 w-4 text-purple-600 border-gray-300 rounded"><span class="ml-2 text-sm text-gray-700">KYC Required</span></label></div>
                        </div>
                    </section>
                    <input type="hidden" name="custom_country_disclaimer" id="custom-country-disclaimer-input">
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-6">
            <button type="submit" class="px-8 py-2.5 bg-gradient-to-r from-purple-700 to-cyan-500 text-white rounded-lg font-medium">Submit Sale</button>
        </div>
    </form>
</div>

<!-- Success Modal -->
<div id="success-modal" class="modal-overlay"><div class="modal-content"><div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4"><i data-lucide="check-circle-2" class="w-8 h-8 text-green-600"></i></div><h3 class="text-lg font-semibold mb-2 text-gray-900">Sale Created!</h3><p class="text-sm text-gray-600 mb-6">You can start your private sale anytime from the dashboard.</p><div class="flex flex-col sm:flex-row justify-center gap-4"><button id="modal-dashboard-btn" class="btn-gradient px-6 py-2 rounded-lg font-medium">Dashboard</button><button id="modal-close-btn" class="btn-secondary px-6 py-2 rounded-lg font-medium text-sm sm:hidden">Close</button></div></div></div>

<!-- Validation Modal -->
<div id="validation-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-40 backdrop-blur-sm transition-opacity opacity-0" id="validation-modal-backdrop"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" id="validation-modal-panel">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-bold leading-6 text-gray-900" id="modal-title">Check your parameters</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500" id="validation-modal-message">Something needs your attention.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="button" onclick="closeValidationModal()" class="inline-flex w-full justify-center rounded-lg bg-gradient-to-r from-purple-700 to-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:from-purple-800 hover:to-cyan-700 sm:ml-3 sm:w-auto transition-all">Okay, I'll fix it</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/agreement_builder.php'; ?>

<!-- 1. Configuration Script: Passes server-side PHP data to JS -->
<script>
    window.TOOKLE_CONFIG = {
        projectUuid: '<?php echo htmlspecialchars($project_uuid); ?>',
        backendUrl: '/backend/newsale_backend.php',
        projectInfo: <?php echo json_encode($project_info ?? []); ?>,
        activeScenario: <?php echo json_encode($scenario_data_decoded ?? []); ?>,
        activeAgreement: <?php echo json_encode($active_agreement_data ?? null); ?>
    };
    // Map existing PHP backend URL for agreement builder
    var agreementBackendUrl = window.TOOKLE_CONFIG.backendUrl;
</script>

<!-- 2. Main Logic Script -->
<script src="/js/newsale.js"></script>

<!-- 3. Interceptor Script: Injects Round Data into Agreement Builder -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Intercept the 'Edit' button click
    const btn = document.getElementById('open-agreement-modal-btn');
    if(btn) {
        btn.addEventListener('click', (e) => {
            e.preventDefault(); 
            
            // Wrap data logic in try-catch to ensure modal opens even if data sync fails
            try {
                // 1. DATA INJECTION LOGIC
                const roundSelect = document.getElementById('select-round');
                const roundId = roundSelect ? roundSelect.value : null;
                
                if (window.TOOKLE_CONFIG && window.TOOKLE_CONFIG.activeScenario) {
                     const scenario = window.TOOKLE_CONFIG.activeScenario;
                     const rounds = scenario.rounds || [];
                     
                     // Build Vesting Map
                     const vestingMap = {};
                     if (scenario.vesting && Array.isArray(scenario.vesting)) {
                         scenario.vesting.forEach(v => {
                             if (v.source_type === 'round' && v.source_id) {
                                 vestingMap[v.source_id] = {
                                     tge: v.percent_unlock_at_tge,
                                     cliff: v.cliff_months,
                                     vesting: v.vesting_months
                                 };
                             }
                         });
                     }

                     // Find the Specific Round Object
                     // Use loose comparison or string conversion for robustness
                     const roundData = rounds.find(r => 
                        (r.id && String(r.id) === String(roundId)) || 
                        (r.round_name && r.round_name === roundId)
                     );

                     if (roundData) {
                         // Resolve Vesting Data (Robust Merge Strategy)
                         let tge = roundData.unlock_tge ?? roundData.percent_unlock_at_tge;
                         let cliff = roundData.cliff_months;
                         let duration = roundData.vesting_months;

                         // If a specific schedule exists in the map for this ID, use it
                         if (vestingMap[roundData.id]) {
                             const mapData = vestingMap[roundData.id];
                             if (mapData.tge != null) tge = mapData.tge;
                             if (mapData.cliff != null) cliff = mapData.cliff;
                             if (mapData.vesting != null) duration = mapData.vesting;
                         }

                         // Ensure clean numbers
                         tge = parseFloat(tge || 0);
                         cliff = parseInt(cliff || 0);
                         duration = parseInt(duration || 0);

                         const vestingText = `TGE: ${tge}%, Cliff: ${cliff}m, Vesting: ${duration}m`;
                         
                         // Pass resolved data to the Agreement Builder
                         if (window.updateBuilderSaleParticulars) {
                             window.updateBuilderSaleParticulars({
                                 ticker: window.TOOKLE_CONFIG.projectInfo?.token_ticker || 'TBA',
                                 round_price: roundData.round_price ? '$' + roundData.round_price : 'TBA',
                                 vesting_text: vestingText,
                                 tge: tge,
                                 cliff: cliff,
                                 vesting_months: duration
                             });
                             // VISUAL CONFIRMATION
                             if(window.showToast) window.showToast("Agreement updated with Round details.", false);
                         }
                     }
                }
            } catch(err) {
                console.warn("Auto-fill warning:", err);
                // We swallow the error so it doesn't block the modal opening
            }

            // 2. EXPLICIT OPEN COMMAND (The Fix)
            // Ensures the modal opens even if data update logic didn't run
            if (typeof window.openAgreementModal === 'function') {
                window.openAgreementModal('builder');
            } else {
                console.error("Agreement Modal function not found.");
                // Fallback attempt to find modal manually if function is missing
                const modal = document.getElementById('agreement-modal');
                if(modal) modal.classList.remove('hidden');
            }
        });
    }
});
</script>