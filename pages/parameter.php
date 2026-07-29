<?php
/**
 * Page: Configure Token Sale - Step 2: Define Parameters
 * Filepath: /pages/parameter.php
 */

require_once __DIR__ . '/../wizard_nav.php';

$current_main_step = 'private_sale';
$current_sub_step = 'parameter';

$pdo = require __DIR__ . '/../src/db.php';
$project_id = $_SESSION['active_project_id'] ?? null;
$page_error = null;
$saleData = [];
$availableRounds = [];
$scenarioName = 'No Active Scenario Found';
$coreTokenomics = []; 

if (empty($project_id)) {
    $page_error = "No active project. Please select a project from your dashboard.";
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM token_sale_pages WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $saleData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        $stmtFetchScenario = $pdo->prepare("
            SELECT data, version_label 
            FROM scenario_version 
            WHERE projet_id = :project_id 
            ORDER BY is_active DESC, created_at DESC 
            LIMIT 1
        ");
        $stmtFetchScenario->execute([':project_id' => $project_id]);
        $scenario = $stmtFetchScenario->fetch(PDO::FETCH_ASSOC);

        if ($scenario && !empty($scenario['data'])) {
            $scenarioName = $scenario['version_label'] ?? 'Untitled Scenario';
            
            $jsonString = preg_replace('/[[:cntrl:]]/', '', $scenario['data']);
            $scenarioData = json_decode(trim($jsonString, "\xEF\xBB\BF"), true);
            
            if ($scenarioData !== null) {
                $coreTokenomics = [
                    'token_name' => $scenarioData['core_params']['token_name'] ?? null,
                    'token_ticker' => $scenarioData['core_params']['token_ticker'] ?? null,
                    'type_supply' => $scenarioData['core_params']['type_supply'] ?? null,
                    'supply_value' => $scenarioData['core_params']['supply_value'] ?? 0,
                ];

                $vestingMap = [];
                if (isset($scenarioData['vesting']) && is_array($scenarioData['vesting'])) {
                    foreach ($scenarioData['vesting'] as $vestingItem) {
                        if (($vestingItem['source_type'] ?? null) === 'round' && isset($vestingItem['source_id'])) {
                            $vestingMap[(string)$vestingItem['source_id']] = $vestingItem;
                        }
                    }
                }
                
                if (isset($scenarioData['rounds']) && is_array($scenarioData['rounds'])) {
                    foreach ($scenarioData['rounds'] as $round) {
                        $isInvestorRound = (isset($round['tranche_type']) && $round['tranche_type'] === 'investor') || !isset($round['tranche_type']);
                        if ($isInvestorRound) {
                            $roundId = $round['id'] ?? $round['round_name'] ?? null;
                            if ($roundId === null) continue;

                            $vestingData = $vestingMap[(string)$roundId] ?? [];
                            $tge = $vestingData['percent_unlock_at_tge'] ?? $round['unlock_tge'] ?? 0;
                            $cliff = $vestingData['cliff_months'] ?? $round['cliff_months'] ?? 0;
                            $vesting = $vestingData['vesting_months'] ?? $round['vesting_months'] ?? 0;
                            
                            $roundData = [
                                'id' => (string)$roundId,
                                'round_name' => $round['round_name'] ?? 'Unnamed Round',
                                'round_price' => $round['round_price'] ?? 0,
                                'percent_discount' => $round['percent_discount'] ?? 0,
                                'round_amount' => $round['round_amount'] ?? 0,
                                'number_of_tokens' => $round['number_of_tokens'] ?? 0,
                                'percent_unlock_at_tge' => $tge,
                                'cliff_months' => $cliff,
                                'vesting_months' => $vesting,
                                'vesting_schedule_text' => "TGE: {$tge}%, Cliff: {$cliff}m, Vesting: {$vesting}m",
                                'scenario_label' => $scenarioName
                            ];
                            
                            $availableRounds[] = $roundData;
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $page_error = "Database error: " . $e->getMessage();
    }
}
?>

<script src="/js/countries.js?v=<?php echo time(); ?>"></script>

<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-gray-50">
    <div class="w-full max-w-6xl">
        <?php render_main_stepper($current_main_step); ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow border border-gray-200">
            <?php render_sub_stepper($current_main_step, $current_sub_step); ?>

             <h1 class="text-2xl font-bold text-gray-900 mb-2">Define Parameters</h1>
             <p class="text-gray-600 text-sm mb-6">Define the critical parameters for your token sale round.</p>
             
             <div class="mb-8 p-4 bg-purple-50 border border-purple-100 rounded-lg flex items-start gap-3">
                <i data-lucide="info" class="w-5 h-5 text-purple-600 mt-0.5 shrink-0"></i>
                <div class="text-sm text-purple-800">
                    <span class="font-semibold block mb-1">Vault Configuration</span>
                    These parameters will be used to automatically configure your project's smart contract vault.
                </div>
             </div>
            
            <?php if ($page_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                    <strong class="font-bold">Error!</strong> <?php echo htmlspecialchars($page_error); ?>
                </div>
            <?php else: ?>
                <form id="parameterForm" action="/backend/parameter_backend.php" method="POST">
                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                    <input type="hidden" name="sale_terms_json" id="sale_terms_json">
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4 text-gray-800">Select Round for Sale</h2>
                        <div>
                            <label for="select-round" class="form-label">Round<span class="text-red-500">*</span>
                                <span class="text-xs text-gray-500 ml-2 font-normal">(Scenario: <?php echo htmlspecialchars($scenarioName); ?>)</span>
                            </label>
                            <div class="relative">
                                <select id="select-round" name="selected_round_id" class="form-select appearance-none bg-white" required>
                                    <option value="">-- Select a Round --</option>
                                    <?php 
                                    $availableRoundsJson = [];
                                    $selectedRoundJson = $saleData['sale_terms_json'] ? json_decode($saleData['sale_terms_json'], true) : [];
                                    $selectedRoundId = $selectedRoundJson['id'] ?? null;
                                    
                                    foreach($availableRounds as $round): 
                                        $availableRoundsJson[(string)$round['id']] = $round;
                                    ?>
                                        <option value="<?php echo htmlspecialchars($round['id']); ?>" <?php echo ($selectedRoundId == $round['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($round['round_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                        </div>
                        <div id="round-details-card" class="round-detail-card" style="display:none;"></div>
                    </section>

                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4 text-gray-800">Sale Configuration</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                <label for="sale_name" class="form-label">Sale Name<span class="text-red-500">*</span></label>
                                <input type="text" id="sale_name" name="sale_name" class="form-input" placeholder="e.g., Private Round One" required value="<?php echo htmlspecialchars($saleData['sale_name'] ?? ''); ?>">
                            </div>
                             
                            <div>
                                <label class="form-label">Campaign Duration<span class="text-red-500">*</span></label>
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
                                <input type="hidden" name="duration_seconds" id="duration_seconds" value="<?php echo htmlspecialchars($saleData['duration_seconds'] ?? '604800'); ?>">
                            </div>

                            <!-- Amount inputs -->
                            <div class="relative group">
                                <label for="soft_cap" class="form-label flex items-center gap-2">Min raise (soft cap) (USD)<span class="text-red-500">*</span></label>
                                <input type="text" id="soft_cap" name="soft_cap_usd" class="form-input amount-field" placeholder="20,000" required value="<?php echo htmlspecialchars($saleData['soft_cap_usd'] ?? ''); ?>">
                            </div>

                            <div class="relative group">
                                <label for="target_raise" class="form-label flex items-center gap-2">Max raise (hard cap) (USD)</label>
                                <input type="text" id="target_raise" name="hard_cap_usd" class="form-input amount-field" placeholder="60,000" value="<?php echo htmlspecialchars($saleData['hard_cap_usd'] ?? ''); ?>">
                            </div>
                        </div>
                    </section>
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4 text-gray-800">Purchase Limits</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="min-purchase" class="form-label">Minimum Purchase (USD)<span class="text-red-500">*</span></label>
                                <input type="text" id="min-purchase" name="min_investment_usd" class="form-input amount-field" placeholder="0.1" required value="<?php echo htmlspecialchars($saleData['min_investment_usd'] ?? '0.1'); ?>">
                            </div>
                            <div>
                                <label for="max-purchase" class="form-label">Maximum Purchase (USD)<span class="text-red-500">*</span></label>
                                <input type="text" id="max-purchase" name="max_investment_usd" class="form-input amount-field" placeholder="10,000" required value="<?php echo htmlspecialchars($saleData['max_investment_usd'] ?? ''); ?>">
                                <p id="max-purchase-error" class="text-red-500 text-xs mt-1 hidden font-medium">
                                    <i data-lucide="alert-circle" class="w-3 h-3 inline"></i> Maximum purchase cannot exceed Hard Cap.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="form-section border-none">
                        <h2 class="text-lg font-semibold mb-4 text-gray-800">Jurisdiction</h2>
                        <label for="country-search" class="form-label">Country of Project Registration<span class="text-red-500">*</span></label>
                        <div id="country-select-wrapper" class="relative">
                            <input type="text" id="country-search" class="form-input pr-10" placeholder="Search country..." autocomplete="off">
                            <input type="hidden" id="country" name="country" required>
                            <div id="country-dropdown" class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto hidden"></div>
                        </div>
                    </section>

                    <div class="flex justify-between items-center pt-6 mt-8 border-t border-gray-200">
                        <a href="<?= get_url('story') ?>" class="px-6 py-2.5 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-medium text-sm transition-colors">Back</a>
                        <button type="submit" class="px-8 py-2.5 bg-gradient-to-r from-purple-700 to-cyan-500 text-white rounded-lg font-medium transition-all shadow-md">Save and Continue</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Validation Modal -->
<div id="validation-modal" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-40 backdrop-blur-sm transition-opacity opacity-0" id="validation-modal-backdrop"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6 transform transition-all opacity-0 translate-y-4" id="validation-modal-panel">
            <div class="flex items-start gap-4">
                <div class="bg-red-100 rounded-full p-2"><i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i></div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Check your parameters</h3>
                    <p class="text-sm text-gray-500 mt-2" id="validation-modal-message"></p>
                </div>
            </div>
            <div class="mt-6 flex justify-end"><button type="button" onclick="closeValidationModal()" class="bg-gray-900 text-white px-4 py-2 rounded-lg font-semibold text-sm">Okay, I'll fix it</button></div>
        </div>
    </div>
</div>

<div id="toast-notification" class="fixed bottom-5 right-5 bg-green-500 text-white py-3 px-6 rounded-lg shadow-xl text-sm hidden flex items-center gap-2 transform transition-all duration-300 translate-y-full opacity-0">
    <i data-lucide="check-circle" class="w-5 h-5"></i> Data saved successfully!
</div>

<style>
.form-section { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #f3f4f6; }
.form-label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; }
.form-input, .form-select { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.95rem; }
.round-detail-card { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.25rem; margin-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem; }
</style>

<script>
const availableRoundsData = <?php echo json_encode($availableRoundsJson); ?>;
const coreTokenomicsData = <?php echo json_encode($coreTokenomics); ?>; 
const initialCountry = "<?php echo htmlspecialchars($saleData['country'] ?? ''); ?>";
const initialDurationSeconds = <?php echo (int)($saleData['duration_seconds'] ?? ($saleData['duration_days'] ? ($saleData['duration_days'] * 86400) : 604800)); ?>;

function showValidationModal(message) {
    const modal = document.getElementById('validation-modal');
    document.getElementById('validation-modal-message').textContent = message;
    modal.classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('validation-modal-backdrop').classList.remove('opacity-0');
        document.getElementById('validation-modal-panel').classList.remove('opacity-0', 'translate-y-4');
    }, 10);
}

function closeValidationModal() {
    document.getElementById('validation-modal-backdrop').classList.add('opacity-0');
    document.getElementById('validation-modal-panel').classList.add('opacity-0', 'translate-y-4');
    setTimeout(() => document.getElementById('validation-modal').classList.add('hidden'), 300);
}

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    if (typeof window.initializeCountrySearch === 'function') window.initializeCountrySearch([], initialCountry);

    // --- HELPER: Formats number with thousands commas ---
    function formatValueWithCommas(value) {
        // Strip everything except digits and decimal point
        let clean = value.replace(/[^0-9.]/g, '');
        
        // Handle multiple dots (keep first)
        const parts = clean.split('.');
        if (parts.length > 2) {
            clean = parts[0] + '.' + parts.slice(1).join('');
        }
        
        const [integer, decimal] = clean.split('.');
        
        // Format thousands with regex
        const formattedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        
        // Return joined with decimal part if present
        return decimal !== undefined ? formattedInteger + '.' + decimal : formattedInteger;
    }

    // --- APPLY LIVE FORMATTING ---
    const amountInputs = document.querySelectorAll('.amount-field');
    amountInputs.forEach(input => {
        // Initial format if data exists
        if (input.value) {
            input.value = formatValueWithCommas(input.value);
        }

        input.addEventListener('input', (e) => {
            const el = e.target;
            const start = el.selectionStart;
            const oldLen = el.value.length;
            
            el.value = formatValueWithCommas(el.value);
            
            // Re-adjust cursor position
            const newLen = el.value.length;
            const pos = start + (newLen - oldLen);
            el.setSelectionRange(pos, pos);
            
            checkCaps();
        });
    });

    // Duration logic
    const durationValueInput = document.getElementById('duration_value');
    const durationUnitSelect = document.getElementById('duration_unit');
    const durationSecondsInput = document.getElementById('duration_seconds');

    function updateDurationCalculations() {
        const val = parseFloat(durationValueInput.value) || 0;
        const unit = durationUnitSelect.value;
        let seconds = 0;
        if (unit === 'minutes') seconds = val * 60;
        else if (unit === 'hours') seconds = val * 3600;
        else if (unit === 'days') seconds = val * 86400;
        if (durationSecondsInput) durationSecondsInput.value = seconds;
    }

    function initDurationUI(totalSeconds) {
        if (totalSeconds % 86400 === 0) {
            durationValueInput.value = totalSeconds / 86400;
            durationUnitSelect.value = 'days';
        } else if (totalSeconds % 3600 === 0) {
            durationValueInput.value = totalSeconds / 3600;
            durationUnitSelect.value = 'hours';
        } else {
            durationValueInput.value = Math.floor(totalSeconds / 60);
            durationUnitSelect.value = 'minutes';
        }
    }
    
    initDurationUI(initialDurationSeconds);
    durationValueInput.addEventListener('input', updateDurationCalculations);
    durationUnitSelect.addEventListener('change', updateDurationCalculations);

    // Round Selection
    const roundSelect = document.getElementById('select-round');
    const roundDetailsCard = document.getElementById('round-details-card');
    const roundJsonInput = document.getElementById('sale_terms_json');

    function updateRoundDetails() {
        const roundId = roundSelect.value;
        const roundData = availableRoundsData[roundId];
        if (roundData) {
            roundDetailsCard.innerHTML = `
                <div class="flex justify-between text-sm items-center"><span class="text-gray-500">Token Price</span><span class="font-bold text-gray-900">$${parseFloat(roundData.round_price).toFixed(6)}</span></div>
                <div class="flex justify-between text-sm items-center"><span class="text-gray-500">Discount</span><span class="font-medium bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs">${roundData.percent_discount}%</span></div>
                <div class="flex justify-between text-sm items-center"><span class="text-gray-500">Vesting Schedule</span><span class="font-medium text-gray-900 text-right">${roundData.vesting_schedule_text}</span></div>
                <div class="h-px bg-gray-200 my-1"></div>
                <div class="flex justify-between text-sm items-center"><span class="text-gray-500">Max Allocation</span><span class="font-medium text-gray-900">$${Number(roundData.round_amount).toLocaleString()}</span></div>
                <div class="flex justify-between text-sm items-center"><span class="text-gray-500">Tokens for Sale</span><span class="font-medium text-gray-900">${Number(roundData.number_of_tokens).toLocaleString()} ${coreTokenomicsData.token_ticker || ''}</span></div>
            `;
            roundDetailsCard.style.display = 'flex';
            roundJsonInput.value = JSON.stringify({ ...roundData, ...coreTokenomicsData });
        } else {
            roundDetailsCard.style.display = 'none';
        }
    }
    roundSelect.addEventListener('change', updateRoundDetails);
    updateRoundDetails();

    // Validation
    const hardCapInput = document.getElementById('target_raise');
    const maxPurchaseInput = document.getElementById('max-purchase');
    
    function checkCaps() {
        // Strip commas for parsing
        const hardCap = parseFloat(hardCapInput.value.replace(/,/g, '')) || 0;
        const maxPurchase = parseFloat(maxPurchaseInput.value.replace(/,/g, '')) || 0;
        
        const isValid = !(hardCap > 0 && maxPurchase > 0 && maxPurchase > hardCap);
        document.getElementById('max-purchase-error').classList.toggle('hidden', isValid);
        maxPurchaseInput.classList.toggle('border-red-500', !isValid);
        return isValid;
    }
    
    // Form Submission
    document.getElementById('parameterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!checkCaps()) {
            showValidationModal('Max purchase cannot exceed Hard Cap.');
            return;
        }

        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline mr-2"></i> Saving...';
        lucide.createIcons();

        // Prepare FormData and strip commas from numeric fields
        const formData = new FormData(this);
        ['soft_cap_usd', 'hard_cap_usd', 'min_investment_usd', 'max_investment_usd'].forEach(key => {
            const fieldId = key === 'soft_cap_usd' ? 'soft_cap' : (key === 'hard_cap_usd' ? 'target_raise' : (key === 'min_investment_usd' ? 'min-purchase' : 'max-purchase'));
            const element = document.getElementById(fieldId);
            
            if (element) {
                // Ensure commas are stripped before sending to backend
                formData.set(key, element.value.replace(/,/g, ''));
            }
        });

        fetch(this.action, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const toast = document.getElementById('toast-notification');
                toast.classList.remove('hidden', 'translate-y-full', 'opacity-0');
                setTimeout(() => window.location.href = '<?= get_url('compliance') ?>', 1000);
            } else {
                showValidationModal(data.error);
                btn.disabled = false;
                btn.textContent = 'Save and Continue';
            }
        }).catch(() => {
            showValidationModal('Network error.');
            btn.disabled = false;
        });
    });
});
</script>