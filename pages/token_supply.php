<?php
/**
 * Page: Token Supply Setup
 * Filepath: /pages/token_supply.php
 *
 * Description: View for setting the token supply type and value.
 * This is part of the 'Design Tokenomics' flow and is loaded by index.php.
 */

// Core dependencies are loaded by index.php, which makes $pdo and $_SESSION available.
$pdo = require __DIR__ . '/../src/db.php';

// --- Wizard Navigation System ---
// Include the centralized navigation functions
require_once __DIR__ . '/../wizard_nav.php';

// Set the current step for the wizard navigation
$current_main_step = 'tokenomics';
$current_sub_step = 'token_supply';


// --- Helper Function to format large numbers ---
function format_large_number($num) {
    if (!is_numeric($num)) return 'N/A';
    $count = floatval($num);
    if ($count >= 1e9) return rtrim(rtrim(number_format($count / 1e9, 1), '0'), '.') . ' Billion';
    if ($count >= 1e6) return rtrim(rtrim(number_format($count / 1e6, 1), '0'), '.') . ' Million';
    return number_format($count);
}

// --- Data Fetching ---
$founder_id = $_SESSION['user_id'];
$project_id = $_SESSION['active_project_id'] ?? null;

$projectInfo = null;
$recommendedData = null;
$errorMessage = '';

if (!$project_id) {
    $errorMessage = 'No active project selected. Please return to your dashboard.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projet WHERE id = ? AND founder_id = ?");
        $stmt->execute([$project_id, $founder_id]);
        $projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$projectInfo) {
            $errorMessage = 'You do not have permission to access this project or it does not exist.';
        } else {
            // Fetch recommendation data based on the project's category
            $category = $projectInfo['selected_category'] ?? null;
            if ($category) {
                $rec_stmt = $pdo->prepare("SELECT * FROM preset_supply WHERE category = ?");
                $rec_stmt->execute([$category]);
                $recommendedData = $rec_stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching project info for token_supply.php: " . $e->getMessage());
        $errorMessage = 'A database error occurred.';
    }
}

// --- Form Value Preparation ---
// Retrieve form data/errors from session if a submission failed
$form_data = $_SESSION['form_data'] ?? [];
$form_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);

// For the UI, 'inflationary' in the DB is treated as 'dynamic'
$db_type_supply = $projectInfo['type_supply'] ?? null;
$ui_type_supply = ($db_type_supply === 'inflationary') ? 'dynamic' : $db_type_supply;

// Prioritize failed form data, then database value, then recommendation
$form_type_supply = $form_data['supply_type'] ?? $ui_type_supply;
$form_supply_value = $form_data['supply_value'] ?? $projectInfo['supply_value'] ?? $recommendedData['recommended_supply_value'] ?? 10000;

?>

<main class="flex-1 p-4 sm:p-8 md:p-12 flex flex-col items-center bg-gray-50">
    
    <?php render_main_stepper($current_main_step); ?>

    <div class="w-full max-w-3xl">
        <?php if (!empty($errorMessage)): ?>
            <div class="p-4 text-center text-red-700 bg-red-100 rounded-lg">
                <strong>Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php else: ?>
            <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg border border-gray-200">
                
                <?php render_sub_stepper($current_main_step, $current_sub_step); ?>

                <h1 class="text-xl font-semibold text-gray-900 mb-2">Define Token Supply</h1>
                <p class="text-gray-600 text-sm mb-6">Specify how your token supply will behave over time.</p>
                 
                <?php if ($recommendedData): ?>
                <div class="bg-gray-100 border border-gray-200 p-4 rounded-md mb-6">
                    <div class="flex">
                        <div class="py-1"><i data-lucide="info" class="w-5 h-5 text-gray-500 mr-3 shrink-0"></i></div>
                        <div>
                            <p class="font-semibold text-gray-700">Observation for <?php echo htmlspecialchars($projectInfo['selected_category']); ?> Projects</p>
                            <p class="text-sm text-gray-600">
                                This type of project usually has a <strong class="text-gray-900"><?php echo htmlspecialchars(ucfirst($recommendedData['recommended_type_supply'])); ?> Supply</strong> 
                                with a quantity of <strong class="text-gray-900"><?php echo format_large_number($recommendedData['recommended_supply_value']); ?></strong> tokens.
                                <span class="block text-xs mt-1 italic text-gray-500"><?php echo htmlspecialchars($recommendedData['explanation']); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form id="token-supply-form" method="post" action="/backend/token_supply_backend.php">
                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">

                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Select Supply Type</label>
                        <?php if (isset($form_errors['supply_type'])): ?>
                            <p class="text-sm text-red-600 mb-2"><?php echo htmlspecialchars($form_errors['supply_type']); ?></p>
                        <?php endif; ?>
                        <div class="flex flex-col space-y-3">
                            <label id="label-capped" class="radio-label flex items-start cursor-pointer p-4 border rounded-lg">
                                <input type="radio" name="supply_type" value="capped" class="h-4 w-4 mt-1 accent-purple-600" <?php echo $form_type_supply === 'capped' ? 'checked' : ''; ?>>
                                <div class="ml-3">
                                    <span class="font-medium text-gray-800">Capped Supply</span>
                                    <p class="text-xs text-gray-500">A fixed, maximum number of tokens that will ever be created. This is the most common and straightforward model.</p>
                                </div>
                            </label>
                            <label id="label-dynamic" class="radio-label flex items-start cursor-pointer p-4 border rounded-lg">
                                <input type="radio" name="supply_type" value="dynamic" class="h-4 w-4 mt-1 accent-purple-600" <?php echo $form_type_supply === 'dynamic' ? 'checked' : ''; ?>>
                                <div class="ml-3">
                                    <span class="font-medium text-gray-800">Dynamic Supply</span>
                                    <p class="text-xs text-gray-500">The total supply can change over time. The specifics of the inflation schedule will be defined in a later step.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                     
                    <div id="supply-input-container" class="hidden mb-8 border-t border-gray-200 pt-6">
                        <label id="supply-value-label" for="supply-value-input" class="block text-sm font-medium text-gray-700 mb-2"></label>
                        <?php if (isset($form_errors['supply_value'])): ?>
                            <p class="text-sm text-red-600 mb-2"><?php echo htmlspecialchars($form_errors['supply_value']); ?></p>
                        <?php endif; ?>
                        <div class="flex items-center space-x-2">
                            <input type="text" id="supply-value-input" name="supply_value" class="w-full px-3 py-2 border rounded-md" value="">
                            <select id="supply-unit-select" name="supply_unit" class="px-3 py-2 border rounded-md bg-gray-50">
                                <option value="1">Units</option>
                                <option value="1000000">Million</option>
                                <option value="1000000000">Billion</option>
                                <option value="1000000000000">Trillion</option>
                            </select>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">Total Supply: <span id="total-supply-display" class="font-bold"></span></p>
                    </div>

                    <div class="flex justify-between items-center mt-8">
                        <a href="<?= get_url('tokenname') ?>" class="px-6 py-2 border border-gray-300 text-gray-700 bg-white rounded-lg font-medium hover:bg-gray-50 transition">Back</a>
                        <button type="submit" class="btn-gradient px-8 py-2 rounded-lg font-medium">Next</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
    /* Styles can be moved to a central CSS file, but are kept here for encapsulation based on the app structure */
    :root { 
        --tookle-purple: #6D28D9;
        --tookle-purple-light: #EDE9FE;
        --highlight-glow-color: rgba(147, 51, 234, 0.4);
    }
    .btn-gradient {
        background-image: linear-gradient(to right, #6D28D9, #06b6d4); color: white;
        transition: all 0.3s ease;
    }
    .btn-gradient:hover { filter: brightness(1.1); }

    /* The old hardcoded stepper styles can be removed if they aren't used elsewhere. */
    /*
    .section-stepper-container { display: flex; align-items: center; margin-bottom: 2.5rem; }
    .stepper-item { display: flex; align-items: center; flex-shrink: 0; }
    .stepper-circle { display: flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; margin-right: 0.5rem; }
    .stepper-text { font-size: 0.875rem; font-weight: 500; }
    .stepper-item.active .stepper-circle { background-color: var(--tookle-purple); color: white; border: 2px solid var(--tookle-purple); }
    .stepper-item.completed .stepper-circle { background-color: var(--tookle-purple); color: white; }
    .stepper-item.completed .stepper-text { color: #374151; }
    .stepper-item.inactive .stepper-circle { border: 2px solid #d1d5db; color: #9ca3af; }
    .stepper-line { height: 2px; flex-grow: 1; margin: 0 1rem; background-color: #e5e7eb; }
    .stepper-line.completed { background-color: var(--tookle-purple); }
    */

    .radio-label { transition: all 0.2s ease-in-out; }
    .radio-label.selected { border-color: var(--tookle-purple); background-color: var(--tookle-purple-light); }
    .radio-label.recommendation-highlight {
        border-color: var(--tookle-purple);
        box-shadow: 0 0 8px var(--highlight-glow-color);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    
    const initialSupplyValue = <?php echo json_encode(floatval($form_supply_value)); ?>;
    const hasSavedSelection = <?php echo json_encode(!empty($form_type_supply)); ?>;
    const recommendedType = <?php echo json_encode($recommendedData['recommended_type_supply'] ?? null); ?>;

    const cappedRadio = document.querySelector('input[value="capped"]');
    const dynamicRadio = document.querySelector('input[value="dynamic"]');
    const cappedLabel = document.getElementById('label-capped');
    const dynamicLabel = document.getElementById('label-dynamic');
    
    const supplyInputContainer = document.getElementById('supply-input-container');
    const supplyValueLabel = document.getElementById('supply-value-label');
    const supplyValueInput = document.getElementById('supply-value-input');
    const supplyUnitSelect = document.getElementById('supply-unit-select');
    const totalSupplyDisplay = document.getElementById('total-supply-display');

    function formatFullNumber(num) {
        return isNaN(num) ? '...' : Math.max(0, num).toLocaleString('en-US');
    }

    function updateSupplyDisplay() {
        const unit = parseFloat(supplyUnitSelect.value);
        const value = parseFloat(supplyValueInput.value.replace(/,/g, ''));
        totalSupplyDisplay.textContent = formatFullNumber(value * unit);
    }

    function setInitialSupplyValues(initial) {
        let unit = 1;
        if (initial >= 1e12) unit = 1e12;
        else if (initial >= 1e9) unit = 1e9;
        else if (initial >= 1e6) unit = 1e6;
        
        supplyUnitSelect.value = unit;
        const displayValue = initial > 0 ? initial / unit : 0;
        supplyValueInput.value = displayValue.toLocaleString('en-US', {maximumFractionDigits: 20});
        updateSupplyDisplay();
    }



    function updateFormUI() {
        const isCapped = cappedRadio.checked;
        cappedLabel.classList.toggle('selected', isCapped);
        dynamicLabel.classList.toggle('selected', dynamicRadio.checked);
        
        const isAnySelected = isCapped || dynamicRadio.checked;
        supplyInputContainer.classList.toggle('hidden', !isAnySelected);

        if (isAnySelected) {
            supplyValueLabel.textContent = isCapped ? 'Define your Max Supply' : 'Define your Initial Supply';
        }
    }
    
    [cappedRadio, dynamicRadio].forEach(radio => radio.addEventListener('change', updateFormUI));
    [supplyValueInput, supplyUnitSelect].forEach(el => el.addEventListener('input', updateSupplyDisplay));

    // Initialize the component
    setInitialSupplyValues(initialSupplyValue);
    updateFormUI();

    // Highlight recommendation if no selection has been saved yet
    if (!hasSavedSelection) {
        if (recommendedType === 'capped') cappedLabel.classList.add('recommendation-highlight');
        if (recommendedType === 'dynamic' || recommendedType === 'inflationary') dynamicLabel.classList.add('recommendation-highlight');
    }
});
</script>