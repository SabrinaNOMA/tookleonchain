<?php
/**
 * Page: Validate Token Economy Design
 * Filepath: /pages/validate.php
 *
 * Description: A read-only summary page that fetches and displays all the tokenomics
 * data for the active project, allowing the founder to review everything before finalizing.
 */

// --- Refactor: Include the centralized wizard navigation system ---
require_once __DIR__ . '/../wizard_nav.php';

// --- Refactor: Define the current step for the navigation system ---
$current_main_step = 'tokenomics';
$current_sub_step = 'validate';


$pdo = require __DIR__ . '/../src/db.php';

// --- Data Fetching ---
$project_id = $_SESSION['active_project_id'] ?? null;
$founder_id = $_SESSION['user_id'];
$tokenData = null;
$errorMessage = '';

if (!$project_id) {
    $errorMessage = 'No active project selected. Please return to your <a href="/dashboard" class="text-purple-700 underline">dashboard</a> to select a project.';
} else {
    try {
        // --- Initialize Data Structure ---
        $tokenData = [
            'project_id' => $project_id, // --- MODIFICATION: Added project_id for frontend script ---
            'project_name' => 'N/A',
            'supply_params' => [],
            'indicators' => [],
            'utilities' => [],
            'fundraising' => [],
            'allocation' => [],
            'vesting' => []
        ];

        // --- Fetch All Project Data ---
        $project_stmt = $pdo->prepare("SELECT * FROM projet WHERE id = ? AND founder_id = ?");
        $project_stmt->execute([$project_id, $founder_id]);
        $projectData = $project_stmt->fetch(PDO::FETCH_ASSOC);

        if ($projectData) {
            // Populate project name
            $tokenData['project_name'] = $projectData['project_name'];

            // Populate Supply Params
            $tokenData['supply_params']['tokenName'] = $projectData['token_name'];
            $tokenData['supply_params']['tokenTicker'] = $projectData['token_ticker'];
            $tokenData['supply_params']['supplyType'] = $projectData['type_supply'];
            $tokenData['supply_params']['supply_value'] = (float)$projectData['supply_value'];

            // Populate Indicators
            $tokenData['indicators']['targetRaise'] = (float)($projectData['target_raise_usd'] ?? 0);
            $tokenData['indicators']['marketCapTGE'] = (float)($projectData['marketcap_at_tge'] ?? 0);
            $tokenData['indicators']['targetFDV'] = (float)($projectData['valuation_tge_usd'] ?? 0);
            $tokenData['indicators']['tgePrice'] = (float)($projectData['calculated_price_tge'] ?? 0);
            
            // Fetch Utilities
            $utility_stmt = $pdo->prepare("SELECT utility_name FROM utility_token WHERE projet_id = ?");
            $utility_stmt->execute([$project_id]);
            $tokenData['utilities'] = $utility_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Fetch Fundraising Rounds
            $rounds_stmt = $pdo->prepare("SELECT round_name, percent_total_raise, round_amount, round_price, number_of_tokens, percent_round_supply FROM round_token WHERE projet_id = ? ORDER BY id");
            $rounds_stmt->execute([$project_id]);
            $tokenData['fundraising'] = array_map(fn($r) => [
                'category' => $r['round_name'], 'totalRaisePercent' => (float)$r['percent_total_raise'], 'amountRaised' => (float)$r['round_amount'],
                'tokenPrice' => (float)$r['round_price'], 'numTokens' => (float)$r['number_of_tokens'], 'maxSupplyPercent' => (float)$r['percent_round_supply']
            ], $rounds_stmt->fetchAll(PDO::FETCH_ASSOC));

            // Fetch Allocations
            $dist_stmt = $pdo->prepare("SELECT vesting_block_name AS category, SUM(percent_supply_vesting) AS percent FROM vesting_token WHERE projet_id = ? GROUP BY vesting_block_name ORDER BY category");
            $dist_stmt->execute([$project_id]);
            $tokenData['allocation'] = array_map(fn($a) => ['category' => $a['category'], 'percent' => (float)$a['percent']], $dist_stmt->fetchAll(PDO::FETCH_ASSOC));

            // Fetch Vesting Data
            $vesting_stmt = $pdo->prepare("SELECT vesting_block_name AS category, percent_unlock_at_tge AS unlock_tge, cliff_months, vesting_months, percent_supply_vesting AS percent_max_supply FROM vesting_token WHERE projet_id = ? ORDER BY id");
            $vesting_stmt->execute([$project_id]);
            $tokenData['vesting'] = array_map(fn($v) => [
                'category' => $v['category'], 'unlockAtTGE' => (float)$v['unlock_tge'], 'cliff' => (int)$v['cliff_months'],
                'vesting' => (int)$v['vesting_months'], 'percentMaxSupply' => (float)$v['percent_max_supply']
            ], $vesting_stmt->fetchAll(PDO::FETCH_ASSOC));

        } else {
             $errorMessage = 'Could not retrieve project information. Please check the project ID or your permissions.';
        }
    } catch (PDOException $e) {
        $errorMessage = 'A database error occurred. Details: ' . $e->getMessage();
        error_log("pages/validate.php Error: " . $e->getMessage());
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
        <?php elseif ($tokenData): ?>
        
        <!-- Refactor: Call the main stepper function -->
        <?php render_main_stepper($current_main_step); ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow border border-gray-200">
            <!-- Refactor: Call the sub stepper function -->
            <?php render_sub_stepper($current_main_step, $current_sub_step); ?>

            <div class="space-y-6">
                <div class="flex justify-between items-baseline">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Validate Your Design</h1>
                </div>
                <p class="text-gray-600 text-base mb-8 -mt-4">Review the complete summary of your token economy design before proceeding.</p>

                <section class="bg-white p-6 rounded-lg shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Token Economy Indicators</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                         <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                             <div class="text-xs text-purple-600 mb-1 uppercase font-medium">Target Raise</div>
                             <div class="text-2xl font-semibold text-purple-900" id="summary-target-raise">-</div>
                         </div>
                         <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                             <div class="text-xs text-purple-600 mb-1 uppercase font-medium">Token Price at TGE</div>
                             <div class="text-2xl font-semibold text-purple-900" id="summary-tge-price">-</div>
                         </div>
                         <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                             <div class="text-xs text-purple-600 mb-1 uppercase font-medium">Market Cap at TGE</div>
                             <div class="text-2xl font-semibold text-purple-900" id="summary-market-cap">-</div>
                         </div>
                         <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                             <div class="text-xs text-purple-600 mb-1 uppercase font-medium">Valuation (FDV)</div>
                             <div class="text-2xl font-semibold text-purple-900" id="summary-fdv">-</div>
                         </div>
                    </div>
                </section>

                <section class="bg-white p-6 rounded-lg shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Token Supply Parameters</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                        <div><dt class="text-gray-500">Project Name</dt><dd class="font-medium text-gray-800 mt-0.5" id="summary-project-name">-</dd></div>
                        <div><dt class="text-gray-500">Token Name</dt><dd class="font-medium text-gray-800 mt-0.5" id="summary-token-name">-</dd></div>
                        <div><dt class="text-gray-500">Token Ticker</dt><dd class="font-medium text-gray-800 mt-0.5" id="summary-token-ticker">-</dd></div>
                        <div><dt class="text-gray-500">Supply Type</dt><dd class="font-medium text-gray-800 mt-0.5" id="summary-supply-type">-</dd></div>
                        <div><dt class="text-gray-500" id="summary-supply-detail-label">Supply</dt><dd class="font-medium text-gray-800 mt-0.5" id="summary-supply-detail-value">-</dd></div>
                    </dl>
                </section>

                <section class="bg-white p-6 rounded-lg shadow border border-gray-200">
                     <h2 class="text-lg font-semibold text-gray-900 mb-4">Token Utilities</h2>
                    <div id="summary-utilities-list" class="space-y-3 mb-4"></div>
                </section>

                <section class="bg-white p-6 rounded-lg shadow border border-gray-200">
                     <h2 class="text-lg font-semibold text-gray-900 mb-4">Token Valuation & Fundraising</h2>
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Round</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">% Total Raise</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount Raised ($)</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Token Price ($)</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase"># of Tokens</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">% of Max Supply</th>
                                </tr>
                            </thead>
                            <tbody id="summary-valuation-table" class="bg-white divide-y divide-gray-200"></tbody>
                        </table>
                    </div>
                </section>

                <section class="bg-white p-6 rounded-lg shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Token Distribution & Allocation</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                        <div>
                            <h3 class="text-base font-semibold mb-2 text-gray-800">Allocation Breakdown</h3>
                            <dl id="summary-distribution-list" class="space-y-2 text-sm border p-4 rounded-lg bg-gray-50"></dl>
                        </div>
                        <div class="relative">
                             <h3 class="text-base font-semibold mb-2 text-gray-800 text-center">Allocation Chart</h3>
                            <div class="chart-container mx-auto">
                                <canvas id="distribution-pie-chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 pt-6 border-t border-gray-200">
                         <h3 class="text-base font-semibold text-gray-800 mb-4">Vesting Visualization</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="line-chart-container bg-gray-50 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-center mb-2 text-gray-600">Inflation from Vesting</h4>
                                <canvas id="inflationChart"></canvas>
                            </div>
                            <div class="line-chart-container bg-gray-50 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-center mb-2 text-gray-600">Token Emission Over Time</h4>
                                 <canvas id="emissionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <footer class="flex justify-between items-center mt-8">
                    <a href="/vesting" class="px-6 py-2 border rounded-lg font-medium text-sm">Back</a>
                    <button type="button" id="approve-design-btn" class="bg-gradient-to-r from-purple-700 to-cyan-500 text-white px-6 py-2 rounded-lg font-medium text-sm">
                        Approve Token Economy
                    </button>
                </footer>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<div id="confirmation-popup" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-sm w-full text-center">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-r from-purple-600 to-cyan-500 mb-4">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900">Token Economy Approved!</h3>
        <p class="text-gray-600 mt-2 mb-6">Well done! You have successfully defined your project's tokenomics. Let's move on to configuring your public sale page.</p>
        <button id="next-step-btn" class="w-full bg-gradient-to-r from-purple-700 to-cyan-500 text-white px-5 py-2.5 rounded-lg font-medium">
            Let's Go!
        </button>
    </div>
</div>

<style>
:root { --tookle-purple: #6D28D9; }
.chart-container canvas { max-height: 250px; max-width: 250px; }
.line-chart-container canvas { max-height: 250px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    const tokenData = <?php echo json_encode($tokenData); ?>;

    const formatNumber = (val, dec = 0) => (isNaN(val) || val === null) ? '0' : Number(val).toLocaleString('en-US', {minimumFractionDigits: dec, maximumFractionDigits: dec});
    const formatCurrency = (val, type = 'standard') => {
        const num = Number(val);
        if (isNaN(num)) return type === 'token_price' ? '$0.000000' : '$0.00';
        const options = { style: 'currency', currency: 'USD' };
        if (type === 'token_price') {
            options.maximumFractionDigits = num < 0.01 ? 6 : 2;
        } else {
            options.maximumFractionDigits = 2;
        }
        return num.toLocaleString('en-US', options);
    };

    function populateUI(data) {
        document.getElementById('summary-target-raise').textContent = formatCurrency(data.indicators?.targetRaise);
        document.getElementById('summary-tge-price').textContent = formatCurrency(data.indicators?.tgePrice, 'token_price');
        document.getElementById('summary-market-cap').textContent = formatCurrency(data.indicators?.marketCapTGE);
        document.getElementById('summary-fdv').textContent = formatCurrency(data.indicators?.targetFDV);
        document.getElementById('summary-project-name').textContent = data.project_name || 'N/A';
        document.getElementById('summary-token-name').textContent = data.supply_params?.tokenName || 'N/A';
        document.getElementById('summary-token-ticker').textContent = data.supply_params?.tokenTicker || 'N/A';
        const supplyType = data.supply_params?.supplyType === 'inflationary' ? 'Dynamic' : 'Capped';
        document.getElementById('summary-supply-type').textContent = supplyType;
        document.getElementById('summary-supply-detail-label').textContent = supplyType === 'Dynamic' ? 'Initial Supply' : 'Max Supply';
        document.getElementById('summary-supply-detail-value').textContent = formatNumber(data.supply_params?.supply_value);

        const utilsList = document.getElementById('summary-utilities-list');
        utilsList.innerHTML = '';
        if (data.utilities?.length > 0) {
            data.utilities.forEach(u => {
                utilsList.innerHTML += `<div class="flex items-center p-3 border rounded-lg bg-gray-50"><i data-lucide="check" class="w-5 h-5 text-purple-700 mr-3"></i><span class="text-sm font-medium text-gray-700">${u}</span></div>`;
            });
        } else {
            utilsList.innerHTML = '<p class="italic text-gray-500">No utilities defined.</p>';
        }

        const valuationTable = document.getElementById('summary-valuation-table');
        valuationTable.innerHTML = '';
        if (data.fundraising?.length > 0) {
            data.fundraising.forEach(r => {
                valuationTable.innerHTML += `<tr>
                    <td class="px-4 py-2 text-left">${r.category}</td>
                    <td class="px-4 py-2 text-right">${formatNumber(r.totalRaisePercent, 2)}%</td>
                    <td class="px-4 py-2 text-right">${formatCurrency(r.amountRaised)}</td>
                    <td class="px-4 py-2 text-right">${formatCurrency(r.tokenPrice, 'token_price')}</td>
                    <td class="px-4 py-2 text-right">${formatNumber(r.numTokens)}</td>
                    <td class="px-4 py-2 text-right">${formatNumber(r.maxSupplyPercent, 2)}%</td>
                </tr>`;
            });
        }

        const distList = document.getElementById('summary-distribution-list');
        distList.innerHTML = '';
        if (data.allocation?.length > 0) {
            data.allocation.forEach(a => {
                distList.innerHTML += `<div class="flex justify-between pb-1 border-b last:border-b-0"><dt class="text-gray-500">${a.category}</dt><dd class="font-medium text-gray-800">${formatNumber(a.percent, 2)}%</dd></div>`;
            });
        }
        lucide.createIcons();
    }

    function initializeCharts(data) {
        const CHART_COLORS = ['#6d28d9','#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe','#5b21b6'];
        
        const distCtx = document.getElementById('distribution-pie-chart').getContext('2d');
        new Chart(distCtx, {
            type: 'pie',
            data: {
                labels: data.allocation.map(a => a.category),
                datasets: [{ data: data.allocation.map(a => a.percent), backgroundColor: CHART_COLORS }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } } }
        });

        const CHART_MONTHS = 48;
        const monthlyLabels = Array.from({ length: CHART_MONTHS }, (_, i) => i);
        const totalMonthlyInflation = Array(CHART_MONTHS).fill(0);
        const categoryMonthlyUnlocks = {};
        data.allocation.forEach(a => { categoryMonthlyUnlocks[a.category] = Array(CHART_MONTHS).fill(0); });
        
        data.vesting.forEach(v => {
            const totalTokens = (v.percentMaxSupply / 100) * data.supply_params.supply_value;
            const tgeAmount = (v.unlockAtTGE / 100) * totalTokens;
            if (tgeAmount > 0) {
                totalMonthlyInflation[0] += tgeAmount;
                if (categoryMonthlyUnlocks[v.category]) categoryMonthlyUnlocks[v.category][0] += tgeAmount;
            }
            const vestingTokens = totalTokens - tgeAmount;
            if (vestingTokens > 0 && v.vesting > 0) {
                const monthlyVesting = vestingTokens / v.vesting;
                for (let i = 0; i < v.vesting; i++) {
                    const month = (v.cliff || 0) + i;
                    if (month < CHART_MONTHS) {
                        totalMonthlyInflation[month] += monthlyVesting;
                        if (categoryMonthlyUnlocks[v.category]) categoryMonthlyUnlocks[v.category][month] += monthlyVesting;
                    }
                }
            }
        });
        
        const inflCtx = document.getElementById('inflationChart').getContext('2d');
        new Chart(inflCtx, {
            type: 'bar',
            data: { labels: monthlyLabels, datasets: [{ label: 'Monthly Inflation', data: totalMonthlyInflation, backgroundColor: '#6d28d9' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
        
        const emissionDatasets = Object.keys(categoryMonthlyUnlocks).map((cat, i) => {
            const cumulativeData = [];
            let runningTotal = 0;
            categoryMonthlyUnlocks[cat].forEach(monthly => {
                runningTotal += monthly;
                cumulativeData.push(runningTotal);
            });
            return {
                label: cat, data: cumulativeData,
                borderColor: CHART_COLORS[i % CHART_COLORS.length],
                backgroundColor: CHART_COLORS[i % CHART_COLORS.length] + '30',
                fill: true, tension: 0.1, pointRadius: 0
            };
        });

        const emissCtx = document.getElementById('emissionChart').getContext('2d');
        new Chart(emissCtx, {
            type: 'line',
            data: { labels: monthlyLabels, datasets: emissionDatasets },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { stacked: true, beginAtZero: true } }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: {size: 10} } } } }
        });
    }

    if (tokenData && tokenData.project_id) {
        populateUI(tokenData);
        initializeCharts(tokenData);
        const approveBtn = document.getElementById('approve-design-btn');
        const nextStepBtn = document.getElementById('next-step-btn');
        const popup = document.getElementById('confirmation-popup');
        
        // --- MODIFICATION START: Added AJAX call to snapshot the approved design ---
        approveBtn.addEventListener('click', () => {
            approveBtn.disabled = true;
            approveBtn.textContent = 'Approving...';

            fetch('/backend/validate_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: tokenData.project_id })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    // On success, show the confirmation popup
                    popup.classList.remove('hidden');
                } else {
                    // On failure, show an error message
                    alert('Approval failed: ' + (data.error || 'An unknown error occurred.'));
                }
            })
            .catch(error => {
                console.error('Error during approval:', error);
                alert('An error occurred while communicating with the server. Please try again.');
            })
            .finally(() => {
                // Re-enable the button
                approveBtn.disabled = false;
                approveBtn.textContent = 'Approve Token Economy';
            });
        });

        nextStepBtn.addEventListener('click', () => {
            window.location.href = '/story';
        });
        // --- MODIFICATION END ---
    }
});
</script>
