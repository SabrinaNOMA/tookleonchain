<?php
/**
 * Page: Token Distribution & Vesting Setup
 * Filepath: /pages/vesting.php
 *
 * Description: View for setting the vesting schedules for all token tranches.
 * This is the final step in the 'Design Tokenomics' flow.
 */

// --- Refactor: Include the centralized wizard navigation system ---
require_once __DIR__ . '/../wizard_nav.php';

// --- Refactor: Define the current step for the navigation system ---
$current_main_step = 'tokenomics';
$current_sub_step = 'vesting';

// Core dependencies are loaded by index.php
$project_id = $_SESSION['active_project_id'] ?? null;
?>
<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-gray-50">
    <div class="w-full max-w-6xl">
        
        <!-- Refactor: Call the main stepper function -->
        <?php render_main_stepper($current_main_step); ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow border border-gray-200">
            <!-- Refactor: Call the sub stepper function -->
            <?php render_sub_stepper($current_main_step, $current_sub_step); ?>

            <!-- Investor Rounds Vesting Section -->
            <section class="mb-8">
                 <div class="border-b border-gray-200 pb-5 mb-6">
                     <h2 class="text-xl font-semibold leading-7 text-gray-900">Investor Rounds Vesting</h2>
                     <p class="mt-1 text-sm leading-6 text-gray-500">Vesting schedule for investor rounds defined in the Fundraising step. You can adjust their vesting terms here.</p>
                 </div>
                 <div>
                     <div class="overflow-x-auto">
                         <table id="investorRoundsTable" class="min-w-full border-separate border-spacing-0 text-sm">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 py-3.5 pl-4 pr-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sm:pl-6 lg:pl-8 min-w-[140px]">Round Name</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">% Supply</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">% Unlock at TGE</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Cliff (Months)</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Vesting (Months)</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[160px]">Tokens Unlocked at TGE</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[160px]">Total Tokens in Round</th>
                                 </tr>
                             </thead>
                             <tbody id="investorRoundsTableBody" class="divide-y divide-gray-200 bg-white">
                             </tbody>
                         </table>
                     </div>
                 </div>
            </section>

            <!-- Other Distribution Categories Section -->
            <section class="mb-8">
                 <div class="border-b border-gray-200 pb-5 mb-6">
                     <h2 class="text-xl font-semibold leading-7 text-gray-900">Other Distribution Categories</h2>
                     <p class="mt-1 text-sm leading-6 text-gray-500">Define other token distribution categories like Team, Treasury, etc. The total % supply of all categories must equal 100%.</p>
                 </div>
                 <div>
                     <div class="overflow-x-auto">
                         <table id="distributionTable" class="min-w-full border-separate border-spacing-0 text-sm">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 py-3.5 pl-4 pr-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sm:pl-6 lg:pl-8 min-w-[140px]">Category Name</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">% Supply</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">% Unlock at TGE</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Cliff (Months)</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">Vesting (Months)</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[160px]">Tokens Unlocked at TGE</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 px-3 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[160px]">Total Tokens in Category</th>
                                     <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 py-3.5 pl-3 pr-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider sm:pr-6 lg:pr-8 min-w-[80px]">Actions</th>
                                 </tr>
                             </thead>
                             <tbody id="distributionTableBody" class="divide-y divide-gray-200 bg-white"></tbody>
                         </table>
                     </div>
                     <div class="mt-6 flex justify-start">
                         <button type="button" class="add-dist-btn inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" onclick="addDistributionCategory()">
                             <i data-lucide="plus" class="w-4 h-4 text-gray-500 mr-1"></i> Add Distribution Category
                         </button>
                     </div>
                 </div>
            </section>

            <!-- Summary & Visualizations Section -->
            <section class="mb-8">
                 <div class="border-b border-gray-200 pb-5 mb-6">
                     <h2 class="text-lg font-semibold leading-7 text-gray-900">Summary & Visualizations</h2>
                     <p class="mt-1 text-sm leading-6 text-gray-500">Review the total distribution and visualize the token release schedule.</p>
                 </div>
                 <div>
                    <div id="distributionTotals" class="mb-6 space-y-2 text-sm font-medium p-4 bg-gray-50 rounded-lg border"></div>
                     <div class="mb-8 text-center">
                         <span class="text-sm font-medium text-gray-600">Estimated Market Cap at TGE:</span>
                         <span id="marketCapTgeDisplay" class="ml-2 text-lg font-semibold text-purple-700">$0.00</span>
                     </div>
                     <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                          <div class="line-chart-container bg-gray-50 p-4 rounded-lg border border-gray-200">
                              <h4 class="text-sm font-medium text-center mb-2 text-gray-600">Inflation from Vesting</h4>
                              <canvas id="inflationChart"></canvas>
                          </div>
                          <div class="line-chart-container bg-gray-50 p-4 rounded-lg border border-gray-200">
                              <h4 class="text-sm font-medium text-center mb-2 text-gray-600">Token Emission Over Time</h4>
                              <canvas id="emissionChart"></canvas>
                          </div>
                     </div>
                 </div>
             </section>

            <!-- Footer Actions -->
            <footer class="flex items-center justify-between mt-8 pt-6 border-t border-gray-200">
                <a href="<?= get_url('fundraising') ?>" id="back-button-footer" class="nav-button bg-gray-200 text-gray-700">Back</a>
                 <div class="flex items-center gap-x-4">
                     <button type="button" id="save-button-footer" class="nav-button btn-gradient">Save & Continue</button>
                 </div>
            </footer>
        </div>
    </div>
</main>

<!-- Success Modal -->
<div id="success-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full btn-gradient mb-4">
            <i data-lucide="check" class="h-10 w-10 text-white"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Distribution Saved!</h3>
        <p class="mt-2 text-sm text-gray-600">Your Token Distribution has been saved. You can now proceed to the final validation step.</p>
        <div class="mt-6">
            <button type="button" id="modal-validate-btn" class="w-full rounded-md btn-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm">
                Go to Validation
            </button>
        </div>
    </div>
</div>

<!-- API Error Message -->
<div id="api-error-message" class="fixed top-5 left-1/2 -translate-x-1/2 z-[100] hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg">
    <strong class="font-bold">Error:</strong>
    <span class="block sm:inline" id="api-error-text">Something went wrong.</span>
</div>

<!-- Styles and Scripts -->
<style>
:root { --tookle-purple: #6D28D9; }
.btn-gradient { background-image: linear-gradient(to right, var(--tookle-purple), #06b6d4); color: white; }
.nav-button { padding: 0.5rem 1.5rem; border-radius: 0.5rem; font-semibold; transition: all 0.2s ease; }
.standard-input { width: 100%; border-radius: 0.375rem; border: 1px solid #d1d5db; padding: 0.375rem 0.75rem; font-size: 0.875rem; }
.line-chart-container canvas { max-height: 250px; }
.modal-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 50; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
.modal-overlay.active { opacity: 1; pointer-events: auto; }
.modal-content { background-color: white; padding: 2rem; border-radius: 0.75rem; text-align: center; max-width: 400px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    // --- Configuration & State ---
    const CHART_MONTHS = 60;
    const API_ENDPOINT = '/backend/vesting_backend.php';
    let projectId = '<?php echo htmlspecialchars($project_id ?? ""); ?>';
    let totalTokenSupply = 0;
    let tokenPriceTGE = 0;
    let investorRoundsData = [];
    let distributionData = [];
    let nextDistId = 0;
    const generateDistId = () => `new-${nextDistId++}`;
    let inflationChart, emissionChart;
    const CHART_COLORS = ['#6d28d9', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#5b21b6', '#7c3aed', '#9f7aea', '#b794f4', '#d6bcfa'];

    // --- DOM Elements ---
    const saveButton = document.getElementById('save-button-footer');
    const modalValidateBtn = document.getElementById('modal-validate-btn');

    // --- Initialization ---
    async function initializePage() {
        if (!projectId) {
            showError('No project is active. Please return to the dashboard.');
            return;
        }
        await fetchInitialData();
        saveButton?.addEventListener('click', saveDistributionData);
        modalValidateBtn.addEventListener('click', () => { window.location.href = '<?= get_url('story') ?>'; });
    }

    // --- Data Fetching & Saving ---
    async function fetchInitialData() {
        try {
            const response = await fetch(`${API_ENDPOINT}?project_id=${projectId}`);
            if (!response.ok) throw new Error(`Server responded with ${response.status}`);
            const result = await response.json();

            if (result.success && result.data) {
                totalTokenSupply = result.data.totalTokenSupply;
                tokenPriceTGE = result.data.tokenPriceTGE;
                investorRoundsData = result.data.investorRounds || [];
                distributionData = result.data.distributionCategories || [];
                nextDistId = Math.max(0, ...distributionData.map(d => parseInt(d.id?.toString().split('-').pop() || '0'))) + 1;
                populateAllTables();
                updateAllVisualizations();
            } else {
                throw new Error(result.error || 'Invalid data from server.');
            }
        } catch (error) {
            console.error(`Failed to fetch initial data:`, error);
            showError(`Could not load data: ${error.message}`);
        }
    }

    async function saveDistributionData() {
        const allCurrentData = [...investorRoundsData, ...distributionData];
        let totalPercent = allCurrentData.reduce((sum, item) => sum + (item.percentSupply || 0), 0);
        
        if (Math.abs(totalPercent - 100) > 0.05) {
            showError('Total % Supply must be exactly 100%.');
            return;
        }

        const payload = {
            projectId,
            vestingData: allCurrentData,
            marketCapTGE: calculateMarketCapTGE()
        };

        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (response.ok && result.success) {
                document.getElementById('success-modal').classList.add('active');
                lucide.createIcons();
            } else {
                throw new Error(result.error || 'Failed to save data.');
            }
        } catch(error) {
            console.error('Save Error:', error);
            showError(`Could not save data: ${error.message}`);
        }
    }

    // --- Table Rendering ---
    function populateAllTables() {
        populateInvestorRoundsTable();
        populateDistributionTable();
    }

    function populateInvestorRoundsTable() {
        const tableBody = document.getElementById('investorRoundsTableBody');
        tableBody.innerHTML = '';
        if (investorRoundsData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-500 py-4">No investor rounds found.</td></tr>';
            return;
        }
        investorRoundsData.forEach(item => renderRow(item, tableBody, true));
    }

    function populateDistributionTable() {
        const tableBody = document.getElementById('distributionTableBody');
        tableBody.innerHTML = '';
        distributionData.forEach(item => renderRow(item, tableBody, false));
    }

    function renderRow(item, tableBody, isInvestor) {
        const row = tableBody.insertRow();
        row.dataset.id = item.id;
        row.dataset.type = isInvestor ? 'investor' : 'distribution';
        
        const totalTokens = ((item.percentSupply || 0) / 100) * totalTokenSupply;
        const tgeTokens = ((item.unlockAtTGE || 0) / 100) * totalTokens;

        const readonlyClass = 'bg-gray-100';
        const commonInputClass = 'standard-input text-right';

        row.innerHTML = `
            <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm sm:pl-6 lg:pl-8"><input type="text" value="${item.block}" class="standard-input ${isInvestor ? readonlyClass : ''}" ${isInvestor ? 'readonly' : ''} data-field="block"></td>
            <td class="whitespace-nowrap px-3 py-2 text-sm"><input type="number" step="0.000001" value="${(item.percentSupply || 0).toFixed(6)}" class="${commonInputClass} ${isInvestor ? readonlyClass : ''}" ${isInvestor ? 'readonly' : ''} data-field="percentSupply"></td>
            <td class="whitespace-nowrap px-3 py-2 text-sm"><input type="number" value="${item.unlockAtTGE || 0}" class="${commonInputClass}" data-field="unlockAtTGE"></td>
            <td class="whitespace-nowrap px-3 py-2 text-sm"><input type="number" value="${item.cliff || 0}" class="${commonInputClass}" data-field="cliff"></td>
            <td class="whitespace-nowrap px-3 py-2 text-sm"><input type="number" value="${item.vesting || 0}" class="${commonInputClass}" data-field="vesting"></td>
            <td class="whitespace-nowrap px-3 py-2 text-sm"><input type="text" value="${formatNumber(tgeTokens)}" class="${commonInputClass} ${readonlyClass}" readonly data-field="tgeTokensCalculated"></td>
            <td class="whitespace-nowrap px-3 py-2 text-sm"><input type="text" value="${formatNumber(totalTokens)}" class="${commonInputClass} ${readonlyClass}" readonly data-field="totalTokensCalculated"></td>
            ${!isInvestor ? `<td class="whitespace-nowrap py-2 pl-3 pr-4 text-center text-sm"><button type="button" class="delete-btn text-red-600 hover:text-red-800 p-1" onclick="deleteDistributionCategory(this)"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>` : '<td></td>'}
        `;
        row.querySelectorAll('input:not([readonly])').forEach(input => input.addEventListener('input', () => handleInputChange(input)));
    }
    
    window.handleInputChange = (inputElement) => {
        const row = inputElement.closest('tr');
        if (!row) return;
        
        const type = row.dataset.type;
        const id = row.dataset.id;
        const dataArray = type === 'investor' ? investorRoundsData : distributionData;
        const item = dataArray.find(d => d.id == id);
        if (!item) return;

        const field = inputElement.dataset.field;
        let value = inputElement.value;

        item[field] = (inputElement.type === 'number') ? (parseFloat(value) || 0) : value;
        if (field === 'block') item.category = value;

        const totalTokens = ((item.percentSupply || 0) / 100) * totalTokenSupply;
        const tgeTokens = ((item.unlockAtTGE || 0) / 100) * totalTokens;

        row.querySelector('[data-field="tgeTokensCalculated"]').value = formatNumber(tgeTokens);
        row.querySelector('[data-field="totalTokensCalculated"]').value = formatNumber(totalTokens);
        
        updateAllVisualizations();
    };
    
    window.addDistributionCategory = () => {
        const newId = generateDistId();
        const newName = `New Category ${nextDistId}`;
        distributionData.push({ 
            id: newId, category: newName, block: newName, 
            percentSupply: 0, unlockAtTGE: 0, cliff: 6, vesting: 24, isInvestor: false
        });
        populateDistributionTable();
        lucide.createIcons();
        updateAllVisualizations();
    };

    window.deleteDistributionCategory = (button) => {
        const row = button.closest('tr');
        distributionData = distributionData.filter(item => item.id != row.dataset.id);
        populateDistributionTable();
        updateAllVisualizations();
    };
    
    // --- Calculation & Visualization ---
    function updateAllVisualizations() {
        updateDistributionTotals(); 
        renderVestingCharts();
        updateMarketCapTgeDisplay();
    }
    
    function renderVestingCharts() {
        const inflCtx = document.getElementById('inflationChart')?.getContext('2d');
        const emissCtx = document.getElementById('emissionChart')?.getContext('2d');
        if (!inflCtx || !emissCtx) return;

        if (inflationChart) inflationChart.destroy();
        if (emissionChart) emissionChart.destroy();

        const allData = [...investorRoundsData, ...distributionData];
        const chartLabels = Array.from({ length: CHART_MONTHS }, (_, i) => i);
        const totalMonthlyInflation = Array(CHART_MONTHS).fill(0);
        const categoryMonthlyUnlocks = {};

        allData.forEach(item => {
            if (!categoryMonthlyUnlocks[item.block]) {
                categoryMonthlyUnlocks[item.block] = Array(CHART_MONTHS).fill(0);
            }
            const blockTokens = (item.percentSupply / 100) * totalTokenSupply;
            if (isNaN(blockTokens) || blockTokens <= 0) return;

            const tgeUnlockAmount = blockTokens * (item.unlockAtTGE / 100);
            if (tgeUnlockAmount > 0) {
                totalMonthlyInflation[0] += tgeUnlockAmount;
                categoryMonthlyUnlocks[item.block][0] += tgeUnlockAmount;
            }
            
            const vestingTokens = blockTokens - tgeUnlockAmount;
            if (vestingTokens > 0 && item.vesting > 0) {
                const monthlyAmount = vestingTokens / item.vesting;
                for (let i = 0; i < item.vesting; i++) {
                    const monthIndex = (item.cliff || 0) + i; 
                    if (monthIndex < CHART_MONTHS) {
                        totalMonthlyInflation[monthIndex] += monthlyAmount;
                        categoryMonthlyUnlocks[item.block][monthIndex] += monthlyAmount;
                    }
                }
            }
        });

        inflationChart = new Chart(inflCtx, {
            type: 'bar',
            data: { labels: chartLabels, datasets: [{ data: totalMonthlyInflation, backgroundColor: '#6d28d9' }] },
            options: chartOptions('bar', 'Months Since TGE', 'Tokens Unlocked')
        });
        
        const emissionDatasets = Object.keys(categoryMonthlyUnlocks).map((label, index) => {
            const cumulativeData = [];
            let runningTotal = 0;
            for (let i = 0; i < CHART_MONTHS; i++) {
                runningTotal += (categoryMonthlyUnlocks[label]?.[i] || 0);
                cumulativeData.push(runningTotal);
            }
            return {
                label, data: cumulativeData,
                borderColor: CHART_COLORS[index % CHART_COLORS.length],
                backgroundColor: CHART_COLORS[index % CHART_COLORS.length] + '30',
                fill: true, tension: 0.1, pointRadius: 0
            }
        });

        emissionChart = new Chart(emissCtx, {
            type: 'line',
            data: { labels: chartLabels, datasets: emissionDatasets },
            options: chartOptions('line', 'Months Since TGE', 'Cumulative Tokens Unlocked')
        });
    }
    
    function chartOptions(type, xTitle, yTitle) {
        return {
            responsive: true, maintainAspectRatio: false,
            interaction: type === 'line' ? { mode: 'index', intersect: false } : {},
            plugins: {
                legend: type === 'line' ? { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10, padding: 10 } } : { display: false },
                tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label || 'Unlocked'}: ${formatNumber(ctx.raw)} Tokens` } }
            },
            scales: {
                x: { title: { display: true, text: xTitle } },
                y: { stacked: type === 'line', beginAtZero: true, title: { display: true, text: yTitle }, ticks: { callback: (val) => formatNumber(val, 0) } }
            }
        };
    }
    
    function calculateMarketCapTGE() {
        if (tokenPriceTGE <= 0) return 0;
        let totalTokensUnlockedAtTGE = [...investorRoundsData, ...distributionData].reduce((sum, item) => {
            const totalTokensInBlock = (item.percentSupply / 100) * totalTokenSupply;
            return sum + ((item.unlockAtTGE || 0) / 100) * totalTokensInBlock;
        }, 0);
        return totalTokensUnlockedAtTGE * tokenPriceTGE;
    }

    function updateDistributionTotals() {
        const totalsDiv = document.getElementById('distributionTotals'); 
        const allItems = [...investorRoundsData, ...distributionData];
        let totalPercent = allItems.reduce((sum, item) => sum + (item.percentSupply || 0), 0);
        
        if (totalTokenSupply > 0) {
            const totalTokensAllocated = allItems.reduce((sum, item) => {
                return sum + (((item.percentSupply || 0) / 100) * totalTokenSupply);
            }, 0);
            const tokenRatio = (totalTokensAllocated / totalTokenSupply) * 100;
            if (Math.abs(100 - tokenRatio) < 0.1) {
                totalPercent = 100;
            }
        } else if (Math.abs(100 - totalPercent) < 0.1) {
            totalPercent = 100;
        }

        const isGreen = Math.abs(100 - totalPercent) < 0.1;
        totalsDiv.innerHTML = `<div class="flex justify-between font-bold text-gray-900 text-base"><span>Total Distribution:</span><span class="${isGreen ? 'text-green-600' : 'text-red-600'}">${totalPercent.toFixed(2)}% / 100.00%</span></div>`;
    }
    
    function updateMarketCapTgeDisplay() {
        const displayElement = document.getElementById('marketCapTgeDisplay'); 
        const marketCapTGE = calculateMarketCapTGE();
        displayElement.textContent = marketCapTGE > 0 ? formatCurrency(marketCapTGE, 2) : 'N/A';
    }
    
    const formatNumber = (value, dec = 0) => (isNaN(value) || value === null) ? '0' : value.toLocaleString('en-US', { maximumFractionDigits: dec });
    const formatCurrency = (value, dec=2) => (isNaN(value) || value === null) ? '$0.00' : `$${value.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec })}`;
    const showError = (msg) => { const el = document.getElementById('api-error-message'); el.querySelector('#api-error-text').textContent = msg; el.classList.remove('hidden'); setTimeout(() => el.classList.add('hidden'), 5000); };
    
    initializePage();
});
</script>
