<?php
/**
 * Page: Fundraising Setup
 * Filepath: /pages/fundraising.php
 *
 * Description: View for setting fundraising goals and token allocation.
 * This is part of the 'Design Tokenomics' flow and is loaded by index.php.
 */

// --- Refactor: Include the centralized wizard navigation system ---
require_once __DIR__ . '/../wizard_nav.php';

// --- Refactor: Define the current step for the navigation system ---
$current_main_step = 'tokenomics';
$current_sub_step = 'fundraising';

// Core dependencies are loaded by index.php, which makes $pdo and $_SESSION available.
$pdo = require __DIR__ . '/../src/db.php';

// --- DEBUG: Add error display from backend redirects ---
$form_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']); // Clear after reading

// --- Helper Functions for Display ---
function format_number_display($number, $decimals = 0) {
    return number_format(is_numeric($number) ? (float)$number : 0, $decimals, '.', ',');
}

// --- Data Fetching ---
$founder_id = $_SESSION['user_id'];
$project_id = $_SESSION['active_project_id'] ?? null;

$projectInfo = null;
$userInfo = $_SESSION['user_info'] ?? null;
$funding_rounds_data = [];
$allocationDataPHP = [];
$benchmark_tranches_data = [];
$errorMessage = '';
$target_raise = 300000; // Default
$target_fdv = 3000000;  // Default
$total_supply = 0;      // Default

if (!$project_id) {
    $errorMessage = 'No active project selected. Please return to your <a href="/dashboard" class="text-purple-700 underline">dashboard</a>.';
} else {
    try {
        // --- Fetch Project Data (Supply, Category, Fundraising Goals) ---
        $stmt_proj = $pdo->prepare("SELECT supply_value, selected_category, target_raise_usd, valuation_tge_usd FROM projet WHERE id = ? AND founder_id = ?");
        $stmt_proj->execute([$project_id, $founder_id]);
        $projectInfo = $stmt_proj->fetch(PDO::FETCH_ASSOC);

        if (!$projectInfo) {
            $errorMessage = 'Project not found or you do not have permission to access it.';
        } else {
            $total_supply = (float)($projectInfo['supply_value'] ?? 0);
            $selected_category_id = $projectInfo['selected_category'] ?? null;
            if (!empty($projectInfo['target_raise_usd'])) $target_raise = (float)$projectInfo['target_raise_usd'];
            if (!empty($projectInfo['valuation_tge_usd'])) $target_fdv = (float)$projectInfo['valuation_tge_usd'];

            // --- Fetch Fundraising Rounds Data (Existing or Presets) ---
            $stmt_rounds = $pdo->prepare("SELECT * FROM round_token WHERE projet_id = ? ORDER BY id ASC");
            $stmt_rounds->execute([$project_id]);
            $existing_rounds = $stmt_rounds->fetchAll(PDO::FETCH_ASSOC);

            if ($existing_rounds) {
                foreach ($existing_rounds as $row) {
                    $funding_rounds_data[] = [
                        'roundName' => $row['round_name'] ?? 'Unnamed', 'totalRaisePercent' => (float)($row['percent_total_raise'] ?? 0),
                        'discountPercent' => (float)($row['percent_discount'] ?? 0)
                    ];
                }
            } else {
                $stmt_preset_rounds = $pdo->query("SELECT recommended_round_name, recommended_percent_total_raise, recommended_percent_discount FROM preset_round ORDER BY id ASC");
                if ($stmt_preset_rounds) {
                    foreach ($stmt_preset_rounds->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $funding_rounds_data[] = [
                            'roundName' => $row['recommended_round_name'], 'totalRaisePercent' => (float)$row['recommended_percent_total_raise'],
                            'discountPercent' => (float)$row['recommended_percent_discount']
                        ];
                    }
                }
            }

            // --- Fetch Allocation Data (Existing or Presets) ---
            $stmt_tranches = $pdo->prepare("SELECT tranche_name, allocation_percent, tranche_type FROM tranche_token WHERE projet_id = ? ORDER BY FIELD(LOWER(tranche_type), 'backers', 'other'), id ASC");
            $stmt_tranches->execute([$project_id]);
            $existing_tranches = $stmt_tranches->fetchAll(PDO::FETCH_ASSOC);

            if ($existing_tranches) {
                 foreach ($existing_tranches as $item_raw) {
                    $allocationDataPHP[] = [
                        'tranche' => $item_raw['tranche_name'],
                        'percent' => (float)$item_raw['allocation_percent'],
                        'readonly' => (strtolower($item_raw['tranche_type']) === 'backers')
                    ];
                }
            } else if ($selected_category_id) {
                $allocationDataPHP[] = ['tranche' => 'Backers', 'percent' => 0, 'readonly' => true];
                $stmt_preset_tranches = $pdo->prepare("SELECT tranche_name, SUM(allocation_percent) as allocation_percent FROM preset_tranche WHERE category_id = ? AND tranche_type != 'backers' GROUP BY tranche_name");
                $stmt_preset_tranches->execute([$selected_category_id]);
                foreach($stmt_preset_tranches->fetchAll(PDO::FETCH_ASSOC) as $row) {
                     $allocationDataPHP[] = [
                        'tranche' => $row['tranche_name'],
                        'percent' => (float)$row['allocation_percent'],
                        'readonly' => false
                    ];
                }
            }
            
            // --- Fetch Benchmark Data for the button ---
            if ($selected_category_id) {
                $stmt_benchmark = $pdo->prepare("SELECT tranche_name, allocation_percent FROM preset_tranche WHERE category_id = ? AND tranche_type != 'backers'");
                $stmt_benchmark->execute([$selected_category_id]);
                $benchmark_tranches_data = $stmt_benchmark->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        error_log("Error in fundraising.php: " . $e->getMessage());
        $errorMessage = 'A database error occurred while loading data.';
    }
}
?>

<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-gray-50">
    <div class="w-full max-w-6xl">
        <?php if ($errorMessage): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php else: ?>
        
        <!-- Refactor: Call the main stepper function -->
        <?php render_main_stepper($current_main_step); ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow border border-gray-200">
            <!-- Refactor: Call the sub stepper function -->
            <?php render_sub_stepper($current_main_step, $current_sub_step); ?>

            <h1 class="text-xl font-semibold text-gray-900 mb-2">Set Your Fundraising Goals</h1>
            <p class="text-gray-600 text-sm mb-8">Define your fundraising targets and allocate your total token supply across different tranches.</p>

            <!-- DEBUG: Display global errors from backend -->
            <?php if (!empty($form_errors['global'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Could Not Save Changes</p>
                    <p><?php echo htmlspecialchars($form_errors['global']); ?></p>
                </div>
            <?php endif; ?>

            <form id="fundraising-form" method="POST" action="/backend/fundraising_backend.php">
                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                
                <div class="mb-8 border-b border-gray-200 pb-8">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">1. Fundraising Goals</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 items-start">
                        <div>
                            <label for="target-raise" class="block text-sm font-medium text-gray-700 mb-1">Target Raise ($)</label>
                            <input type="text" id="target-raise" name="target_raise" class="w-full px-3 py-2 border border-gray-300 rounded-md" value="<?php echo format_number_display($target_raise, 0); ?>">
                        </div>
                        <div>
                            <label for="target-fdv" class="block text-sm font-medium text-gray-700 mb-1">Valuation (FDV) ($)</label>
                            <input type="text" id="target-fdv" name="target_fdv" class="w-full px-3 py-2 border border-gray-300 rounded-md" value="<?php echo format_number_display($target_fdv, 0); ?>">
                        </div>
                        <div>
                            <label for="total-supply" class="block text-sm font-medium text-gray-700 mb-1">Total Supply</label>
                            <input type="text" id="total-supply" name="total_supply" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" value="<?php echo format_number_display($total_supply, 0); ?>" readonly>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Calculated % Supply to Backers </label>
                            <p class="text-lg font-semibold text-gray-800 mt-1"><span id="supply-allocated-percent">--%</span></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Calculated Price at TGE</label>
                            <p class="text-lg font-semibold text-gray-800 mt-1"><span id="calculated-tge-price">$0.00000</span></p>
                        </div>
                    </div>
                    <h3 class="text-md font-semibold text-gray-800 mb-4">Fundraising Round Break Down</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm mb-4">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Round Name</th>
                                    <th class="px-3 py-2 text-right font-medium">% Total Raise</th>
                                    <th class="px-3 py-2 text-right font-medium">% Discount</th>
                                    <th class="px-3 py-2 text-right font-medium">Amount Raised</th>
                                    <th class="px-3 py-2 text-right font-medium">Token Price</th>
                                    <th class="px-3 py-2 text-right font-medium">% of Supply</th>
                                    <th class="px-3 py-2 text-right font-medium"># of Tokens</th>
                                    <th class="px-3 py-2 text-center font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="funding-rounds-body"></tbody>
                            <tfoot>
                                <tr class="border-t-2">
                                    <td class="px-3 py-2 font-semibold">Total</td>
                                    <td id="total-raise-percent" class="px-3 py-2 text-right font-semibold">--%</td>
                                    <td></td>
                                    <td id="total-amount-raised" class="px-3 py-2 text-right font-semibold">$ --</td>
                                    <td></td>
                                    <td id="total-percent-supply" class="px-3 py-2 text-right font-semibold">--%</td>
                                    <td id="total-number-of-tokens" class="px-3 py-2 text-right font-semibold">--</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" id="add-round-button" class="text-sm text-purple-700 hover:text-purple-900 font-medium"><i data-lucide="plus" class="w-4 h-4 inline-block -mt-1"></i> Add Round</button>
                </div>

                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">2. Token Allocation</h2>
                    <div class="flex flex-col lg:flex-row items-start gap-8">
                        <div class="w-full lg:w-2/3">
                            <div class="overflow-x-auto">
                                <table id="allocation-table" class="min-w-full text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="w-2/5 py-3 pl-4 pr-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tranche</th>
                                            <th scope="col" class="w-2/5 px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% Total Supply</th>
                                            <th scope="col" class="w-1/5 relative py-3 pl-3 pr-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="allocation-table-body" class="divide-y divide-gray-200 bg-white"></tbody>
                                </table>
                            </div>
                            <div class="mt-4 flex flex-col sm:flex-row justify-between items-start gap-4">
                                <div>
                                    <div id="totalAllocationMessageContainer" class="flex items-center">
                                        <div id="totalAllocationMessage" class="text-sm font-medium flex items-center">
                                            <span id="allocationTotalText" class="mr-2">Total: 0.00% / 100.00%</span>
                                            <button type="button" id="adjustAllocationBtn" class="adjust-button" title="Adjust 'other' tranches proportionally to reach 100%">Adjust</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-x-2 flex-shrink-0">
                                    <button type="button" id="load-benchmark-button" class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        <i data-lucide="download-cloud" class="w-4 h-4 text-gray-500 mr-1"></i> Load Benchmark Data
                                    </button>
                                    <button type="button" id="add-tranche-button" class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        <i data-lucide="plus" class="w-4 h-4 text-gray-500 mr-1"></i> Add Tranche
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="w-full lg:w-1/3 flex justify-center items-center mt-6 lg:mt-0 chart-container">
                            <canvas id="allocationChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center border-t border-gray-200 pt-6 mt-8">
                    <a href="/tokensupply" class="px-6 py-2 border rounded-lg font-medium">Back</a>
                    <button type="submit" id="next-button" class="btn-gradient px-6 py-2 rounded-lg font-medium">Save & Continue</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
    :root { --tookle-purple: #6D28D9; }
    .btn-gradient { background-image: linear-gradient(to right, var(--tookle-purple), #06b6d4); color: white; }
    .table-input { border: 1px solid #d1d5db; border-radius: 0.25rem; padding: 0.25rem 0.5rem; font-size: 0.875rem; width: 100%; text-align: right; }
    .table-input.input-readonly { background-color: #f3f4f6; cursor: not-allowed; }
    .table-input.category-input { text-align: left; }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    .chart-container canvas { max-height: 250px; }
    .adjust-button { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 0.25rem; border: 1px solid #d1d5db; margin-left: 0.75rem; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    
    const initialFundingRounds = <?php echo json_encode($funding_rounds_data); ?>;
    let allocationData = <?php echo json_encode($allocationDataPHP); ?>;
    const benchmarkTranches = <?php echo json_encode($benchmark_tranches_data); ?>;

    const targetRaiseInput = document.getElementById('target-raise');
    const targetFdvInput = document.getElementById('target-fdv');
    const totalSupplyInput = document.getElementById('total-supply');
    const calculatedPriceDisplay = document.getElementById('calculated-tge-price');
    const fundingTableBody = document.getElementById('funding-rounds-body');
    const allocationTableBody = document.getElementById('allocation-table-body');
    const addRoundButton = document.getElementById('add-round-button');
    const addTrancheButton = document.getElementById('add-tranche-button');
    const loadBenchmarkButton = document.getElementById('load-benchmark-button');
    const supplyAllocatedPercentEl = document.getElementById('supply-allocated-percent');
    const nextButton = document.getElementById('next-button');
    const form = document.getElementById('fundraising-form');
    
    let allocationChart;
    const CHART_COLORS = ['#6d28d9', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#5b21b6', '#9333ea', '#a855f7', '#c084fc', '#e9d5ff'];
    
    const parseNum = (str) => parseFloat(String(str).replace(/[$,\s]/g, '')) || 0;
    const formatCurrency = (val, dec = 2) => `$${(val || 0).toLocaleString('en-US', {minimumFractionDigits: dec, maximumFractionDigits: dec})}`;
    const formatPercent = (val, dec = 2) => `${(val || 0).toFixed(dec)}%`;
    const formatNum = (val, dec = 0) => (val || 0).toLocaleString('en-US', {minimumFractionDigits: dec, maximumFractionDigits: dec});
    const formatTokenPrice = (val) => formatCurrency(val, 6);

    function recalculateAll() {
        const targetRaise = parseNum(targetRaiseInput.value);
        const fdv = parseNum(targetFdvInput.value);
        const supply = parseNum(totalSupplyInput.value);
        const tgePrice = supply > 0 ? fdv / supply : 0;
        calculatedPriceDisplay.textContent = formatTokenPrice(tgePrice);
        
        let totalRaisePercent = 0, totalAmount = 0, totalTokens = 0, totalPercentSupply = 0;
        
        fundingTableBody.querySelectorAll('tr').forEach(row => {
            const raisePercent = parseNum(row.querySelector('.input-total-raise-percent').value);
            const discountPercent = parseNum(row.querySelector('.input-discount-percent').value);
            const amount = targetRaise * (raisePercent / 100);
            const price = tgePrice * (1 - (discountPercent / 100));
            const tokens = price > 0 ? amount / price : 0;
            const percentSupply = supply > 0 ? (tokens / supply) * 100 : 0;

            row.querySelector('.output-amount-raised').textContent = formatCurrency(amount, 0);
            row.querySelector('.output-token-price').textContent = formatTokenPrice(price);
            row.querySelector('.output-percent-supply').textContent = formatPercent(percentSupply);
            row.querySelector('.output-number-of-tokens').textContent = formatNum(tokens, 0);
            
            totalRaisePercent += raisePercent;
            totalAmount += amount;
            totalTokens += tokens;
            totalPercentSupply += percentSupply;
        });

        document.getElementById('total-raise-percent').textContent = formatPercent(totalRaisePercent, 0);
        document.getElementById('total-amount-raised').textContent = formatCurrency(totalAmount, 0);
        document.getElementById('total-number-of-tokens').textContent = formatNum(totalTokens, 0);
        document.getElementById('total-percent-supply').textContent = formatPercent(totalPercentSupply);
        supplyAllocatedPercentEl.textContent = formatPercent(totalPercentSupply);

        const investorAllocData = allocationData.find(item => item.readonly);
        if (investorAllocData) {
            investorAllocData.percent = totalPercentSupply;
        }
        
        populateAllocationTable();
        updateAllocation();
        validateAll();
    }

    function validateAll() {
        const fundRaiseTotal = parseNum(document.getElementById('total-raise-percent').textContent);
        // BUG FIX: Use a higher precision for validation to match internal calculations.
        const allocTotal = allocationData.reduce((sum, item) => sum + (item.percent || 0), 0);
        const isFundraiseValid = Math.abs(fundRaiseTotal - 100) < 0.1;
        const isAllocationValid = Math.abs(allocTotal - 100) < 0.0001;
        
        if (nextButton) {
            nextButton.disabled = !(isFundraiseValid && isAllocationValid);
        }
    }

    function createFundingRow(data = {}) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-3 py-1"><input type="text" name="round_name[]" class="table-input category-input" value="${data.roundName || ''}"></td>
            <td class="px-3 py-1"><input type="number" name="total_raise_percent[]" step="0.1" class="table-input input-total-raise-percent" value="${data.totalRaisePercent || 0}"></td>
            <td class="px-3 py-1"><input type="number" name="discount_percent[]" step="0.1" class="table-input input-discount-percent" value="${data.discountPercent || 0}"></td>
            <td class="output-amount-raised px-3 py-1 text-right"></td>
            <td class="output-token-price px-3 py-1 text-right"></td>
            <td class="output-percent-supply px-3 py-1 text-right"></td>
            <td class="output-number-of-tokens px-3 py-1 text-right"></td>
            <td class="px-3 py-1 text-center"><button type="button" class="remove-row-button text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>`;
        row.querySelectorAll('input').forEach(input => input.addEventListener('input', recalculateAll));
        row.querySelector('.remove-row-button').addEventListener('click', () => { row.remove(); recalculateAll(); });
        fundingTableBody.appendChild(row);
        lucide.createIcons();
    }

    function initializeCharts() {
        const ctx = document.getElementById('allocationChart')?.getContext('2d');
        if (ctx) {
            allocationChart = new Chart(ctx, {
                type: 'pie',
                data: { labels: [], datasets: [{ data: [], backgroundColor: CHART_COLORS }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }
    }

    function populateAllocationTable() {
        allocationTableBody.innerHTML = '';
        allocationData.forEach(item => createAllocationRow(item));
    }

    function createAllocationRow(item) {
        const row = document.createElement('tr');
        row.dataset.trancheName = item.tranche || `alloc-${Math.random()}`;
        // BUG FIX: Use 4 decimal places for the value to prevent data loss from rounding
        const percentValue = (item.percent || 0).toFixed(4);
        row.innerHTML = `
            <td class="py-2 pl-4 pr-3"><input type="text" name="alloc_tranche[]" value="${item.tranche || ''}" class="table-input category-input ${item.readonly ? 'input-readonly' : ''}" ${item.readonly ? 'readonly' : ''}><input type="hidden" name="alloc_readonly[]" value="${item.readonly ? '1' : '0'}"></td>
            <td class="px-3 py-2"><input type="number" name="alloc_percent[]" value="${percentValue}" class="table-input ${item.readonly ? 'input-readonly' : ''}" ${item.readonly ? 'readonly' : ''} step="0.01"></td>
            <td class="py-2 pl-3 pr-4 text-center">${item.readonly ? '' : '<button type="button" class="remove-tranche-button text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button>'}</td>`;
        
        if (!item.readonly) {
            row.querySelector('input[name="alloc_percent[]"]').addEventListener('input', (e) => handleAllocationInputChange(e.target));
            row.querySelector('input[name="alloc_tranche[]"]').addEventListener('input', (e) => {
                const oldName = row.dataset.trancheName;
                const newName = e.target.value;
                const itemToUpdate = allocationData.find(d => d.tranche === oldName);
                if(itemToUpdate) itemToUpdate.tranche = newName;
                row.dataset.trancheName = newName;
            });
            row.querySelector('.remove-tranche-button')?.addEventListener('click', () => {
                allocationData = allocationData.filter(d => d.tranche !== item.tranche);
                recalculateAll();
            });
        }
        allocationTableBody.appendChild(row);
        lucide.createIcons();
    }
    
    function handleAllocationInputChange(input) {
        const row = input.closest('tr');
        const trancheName = row.querySelector('input[name="alloc_tranche[]"]').value;
        const item = allocationData.find(d => d.tranche === trancheName);
        if (item) {
            item.percent = parseNum(input.value);
        }
        updateAllocation();
        validateAll();
    }

    function addTranche() {
        const newName = `New Tranche ${allocationData.length}`;
        allocationData.push({ tranche: newName, percent: 0, readonly: false });
        populateAllocationTable();
        updateAllocation();
    }

    function updateAllocation() {
        let totalPercent = 0;
        const labels = [], data = [];
        allocationData.forEach(item => {
            const percent = item.percent || 0;
            totalPercent += percent;
            if (item.tranche && percent > 0) {
                labels.push(item.tranche);
                data.push(percent);
            }
        });
        
        // BUG FIX: Use 4 decimal places for total display to be more accurate
        document.getElementById('allocationTotalText').textContent = `Total: ${totalPercent.toFixed(4)}% / 100.00%`;
        const isTotal100 = Math.abs(totalPercent - 100) < 0.0001;
        document.getElementById('allocationTotalText').className = isTotal100 ? 'text-green-600' : 'text-red-600';
        document.getElementById('adjustAllocationBtn').disabled = isTotal100;
        
        if (allocationChart) {
            allocationChart.data.labels = labels;
            allocationChart.data.datasets[0].data = data;
            allocationChart.update();
        }
    }

    /**
     * BUG FIX: Rewritten `adjustAllocationsTo100` function.
     * This version avoids floating-point errors by converting all percentages to integers
     * for calculation, effectively using basis points. It proportionally distributes the
     * difference and then assigns any rounding remainders to the largest tranche,
     * ensuring the total is *exactly* 100.
     */
    function adjustAllocationsTo100() {
        const PRECISION = 10000; // Using 10,000 mimics BPS * 100 for more precision
        const otherTranches = [];
        let otherTotalInt = 0;
        let investorTotalInt = 0;

        // Convert all percentages to integers for precise calculations
        allocationData.forEach(item => {
            const percentInt = Math.round((item.percent || 0) * PRECISION);
            item.percentInt = percentInt; // Temporarily store integer value
            if (item.readonly) {
                investorTotalInt += percentInt;
            } else {
                otherTranches.push(item);
                otherTotalInt += percentInt;
            }
        });

        const totalInt = investorTotalInt + otherTotalInt;
        const differenceInt = (100 * PRECISION) - totalInt;
        
        if (Math.abs(differenceInt) < 1 || otherTranches.length === 0) {
            allocationData.forEach(item => delete item.percentInt); // Clean up temp property
            return;
        }

        if (otherTotalInt === 0) {
            // If all non-investor tranches are 0, distribute the difference equally
            const adjustmentPerTranche = Math.floor(differenceInt / otherTranches.length);
            otherTranches.forEach(item => {
                item.percentInt += adjustmentPerTranche;
            });
        } else {
            // Distribute the difference proportionally
            let distributedAdjustment = 0;
            otherTranches.forEach(item => {
                const proportion = item.percentInt / otherTotalInt;
                const adjustment = Math.round(differenceInt * proportion);
                item.percentInt += adjustment;
                distributedAdjustment += adjustment;
            });
        }
        
        // Check for any rounding remainder after distribution
        let newOtherTotalInt = 0;
        otherTranches.forEach(item => newOtherTotalInt += item.percentInt);
        const remainder = (100 * PRECISION) - investorTotalInt - newOtherTotalInt;

        if (remainder !== 0) {
            // Give remainder to the largest tranche to minimize visual impact
            otherTranches.sort((a, b) => b.percentInt - a.percentInt);
            if (otherTranches[0]) {
               otherTranches[0].percentInt += remainder;
            }
        }
        
        // Update the original float-based 'percent' property from our precise integer calculations
        allocationData.forEach(item => {
            item.percent = item.percentInt / PRECISION;
            delete item.percentInt; // Clean up temporary property
        });
        
        recalculateAll();
    }
    
    function loadBenchmarkData() {
        if (!benchmarkTranches || benchmarkTranches.length === 0) return;
        const investorTranche = allocationData.find(item => item.readonly);
        const newAllocationData = investorTranche ? [investorTranche] : [];
        benchmarkTranches.forEach(bench => {
            newAllocationData.push({
                tranche: bench.tranche_name,
                percent: parseFloat(bench.allocation_percent) || 0,
                readonly: false
            });
        });
        allocationData = newAllocationData;
        recalculateAll();
    }

    // --- Init ---
    initializeCharts();
    if (initialFundingRounds.length > 0) {
        initialFundingRounds.forEach(createFundingRow);
    }
    recalculateAll();
    
    // --- Event Listeners ---
    [targetRaiseInput, targetFdvInput].forEach(el => {
        el.addEventListener('input', recalculateAll);
        el.addEventListener('blur', (e) => {
            const value = parseNum(e.target.value);
            e.target.value = formatNum(value, 0);
        });
    });
    addRoundButton.addEventListener('click', createFundingRow);
    addTrancheButton.addEventListener('click', addTranche);
    loadBenchmarkButton.addEventListener('click', loadBenchmarkData);
    document.getElementById('adjustAllocationBtn').addEventListener('click', adjustAllocationsTo100);

    if (!benchmarkTranches || benchmarkTranches.length === 0) {
        loadBenchmarkButton.disabled = true;
    }
});
</script>
