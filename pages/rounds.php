<?php
// pages/rounds.php
// The project ID is now expected to be in the session, set by the central router.
$current_project_id = $_SESSION['active_project_id'] ?? null;
if (!$current_project_id) {
    // Or handle this more gracefully, maybe a message in the layout
    die("No active project selected.");
}
?>
<!-- Page-specific styles for the rounds page -->
<style>
    :root {
        --tookle-purple: #6D28D9; --tookle-cyan: #06b6d4; --text-primary: #1f2937;
        --text-secondary: #6b7280; --border-color: #e5e7eb; --error-red: #dc2626; --success-green: #16a34a;
    }
    fieldset:disabled { cursor: not-allowed; }
    fieldset:disabled .tookle-button, fieldset:disabled button { pointer-events: none; opacity: 0.6; }
    fieldset:disabled .modern-input, fieldset:disabled select, fieldset:disabled textarea {
        background-color: #f3f4f6; color: #6b7280; cursor: not-allowed; opacity: 0.7;
    }
    .output-actual-raised.has-investors {
        cursor: pointer; text-decoration: underline; color: var(--tookle-purple); font-weight: 600;
    }
    .output-actual-raised.has-investors:hover { color: #5b21b6; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .main-container { animation: fadeIn 0.5s ease-in-out; }
    .tookle-card { background-color: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05); border: 1px solid var(--border-color); margin-bottom: 1.5rem; }
    .modern-input { background-color: white; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; color: var(--text-primary); transition: all 0.2s ease-in-out; margin-top: 0.25rem; display: block; width: 100%; }
    .modern-input:focus { border-color: var(--tookle-purple); outline: none; box-shadow: 0 0 0 2px rgba(109, 40, 217, 0.2); }
    .modern-input[readonly] { background-color: #f3f4f6; color: #6b7280; cursor: not-allowed; }
    .modern-input.is-invalid { border-color: var(--error-red); }
    .tookle-button { padding: 0.6rem 1.2rem; border-radius: 0.375rem; font-weight: 600; transition: all 0.2s ease-in-out; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem; border: 1px solid transparent; }
    .tookle-button-primary { background-image: linear-gradient(to right, var(--tookle-purple), var(--tookle-cyan)); color: white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
    .tookle-button-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
    .tookle-button-secondary { background-color: #fff; color: #374151; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); border-color: #d1d5db; }
    .tookle-button-secondary:hover:not(:disabled) { background-color: #f9fafb; }
    .tookle-button:disabled { opacity: 0.5; cursor: not-allowed; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td { vertical-align: middle; padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; }
    
    @media (max-width: 1023px) {
        .data-table { min-width: max-content; }
        .data-table th, .data-table td { white-space: nowrap; }
    }
    @media (min-width: 1024px) {
        .data-table { table-layout: fixed; }
        .data-table th, .data-table td { word-wrap: break-word; white-space: normal; }
    }
    .data-table td .modern-input { padding: 0.25rem 0.5rem; font-size: 0.8125rem; }
    .data-table thead th { background-color: #f9fafb; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; font-size: 0.75rem; }
    .data-table tfoot td { font-weight: 600; background-color: #f9fafb; }
    label { font-size: 0.875rem; font-weight: 500; color: var(--text-secondary); display: inline-flex; align-items: center; margin-bottom: 0.25rem; }
    .error-message { color: var(--error-red); font-size: 0.75rem; margin-top: 0.25rem; display: block; }
    .chart-container { position: relative; width: 100%; height: 280px; }
    .status-badge { border-radius: 9999px; padding: 0.125rem 0.625rem; font-size: 0.75rem; font-weight: 500; text-align: center; display: inline-block; }
    /* --- NEW ---: Tooltip for disabled Edit button */
    .tooltip-container { position: relative; display: inline-block; }
    .tooltip-text {
        visibility: hidden; width: 280px; background-color: #374151; color: #fff; text-align: center;
        border-radius: 6px; padding: 8px; position: absolute; z-index: 1;
        bottom: 125%; left: 50%; margin-left: -140px; opacity: 0; transition: opacity 0.3s;
    }
    .tooltip-text::after {
        content: ""; position: absolute; top: 100%; left: 50%;
        margin-left: -5px; border-width: 5px; border-style: solid;
        border-color: #374151 transparent transparent transparent;
    }
    .tooltip-container:hover .tooltip-text { visibility: visible; opacity: 1; }
    
    @media print {
        body * { visibility: hidden; }
        .printable-area, .printable-area * { visibility: visible; }
        .printable-area { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<!-- Main content for the rounds page, to be injected into layout.php -->
<main class="flex-1 p-8 main-container overflow-y-auto">
    <div class="printable-area">
        <header class="mb-8 flex justify-between items-center no-print">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Rounds</h1>
                <p class="mt-2 text-base text-gray-500">Define your project's tokenomics, manage funding rounds, and structure the token allocation for all stakeholders.</p>
            </div>
        </header>

        <div id="status-container" class="mb-4 space-y-2 no-print">
            <!-- MODIFICATION: Changed flex properties for better wrapping -->
            <div id="validation-status" class="p-3 border rounded-md flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hidden bg-red-100 border-red-300 text-red-700">
                <!-- Grouping icon and text -->
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0"></i>
                    <span id="validation-error-summary" class="font-semibold"></span>
                </div>
                <!-- Separate container for the button -->
                <button type="button" id="validation-adjust-btn" onclick="adjustAllocations()" class="hidden ml-8 sm:ml-0 underline text-red-800 font-bold flex-shrink-0">Auto-adjust now?</button>
            </div>
            <div id="draft-status" class="p-3 bg-blue-100 border-blue-300 text-blue-800 rounded-md flex items-center gap-3 hidden">
                <i data-lucide="pencil" class="w-5 h-5"></i>
                <span>You have unsaved changes.</span>
            </div>
        </div>
        
        <!-- Scenario Manager is now OUTSIDE the fieldset -->
        <div class="tookle-card no-print">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Scenario Manager</h2>
                    <p class="text-sm text-gray-600">Load a saved version or edit the current scenario.</p>
                </div>
                <!-- UX IMPROVEMENT: Action buttons moved here from header and logic refined -->
                <div id="scenario-actions" class="flex items-center gap-2">
                    <!-- View Mode Buttons -->
                    <div id="view-mode-btns" class="flex items-center gap-2">
                        <button id="howItWorksBtn" class="tookle-button tookle-button-secondary !p-2" data-tooltip="How it Works"><i data-lucide="help-circle" class="h-5 w-5"></i></button>
                        <button id="printSummaryBtn" class="tookle-button tookle-button-secondary !p-2" data-tooltip="Print Summary"><i data-lucide="printer" class="h-5 w-5"></i></button>
                        <!-- --- NEW ---: Tooltip container for the Edit button -->
                        <div id="edit-button-container" class="tooltip-container">
                            <button id="editScenarioBtn" class="tookle-button tookle-button-primary"><i data-lucide="edit-3" class="mr-2 h-5 w-5"></i>Edit</button>
                            <span id="edit-tooltip" class="tooltip-text"></span>
                        </div>
                    </div>
                    <!-- Edit Mode Buttons -->
                    <div id="edit-mode-btns" class="hidden flex items-center gap-2">
                        <button id="cancelChangesBtn" class="tookle-button tookle-button-secondary">Cancel</button>
                        <button id="saveScenarioBtn" class="tookle-button"><i data-lucide="save" class="mr-2 h-5 w-5"></i>Save</button>
                    </div>
                </div>
            </div>
            <div class="flex items-end gap-4 border-t pt-4">
                <div class="flex-grow">
                    <label for="versionSelector">Load Existing Version:</label>
                    <select id="versionSelector" class="modern-input"></select>
                </div>
            </div>
        </div>

        <fieldset id="main-fieldset">
            <div class="tookle-card">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Summary & Visualizations</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <h3 class="text-base font-semibold mb-2 text-gray-800 text-center">Token Allocation Breakdown</h3>
                        <div class="chart-container mx-auto" style="max-width: 280px;"><canvas id="allocationPieChart"></canvas></div>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold mb-2 text-gray-800 text-center">Token Emission (Cumulative)</h3>
                        <div class="chart-container"><canvas id="emissionChart"></canvas></div>
                    </div>
                </div>
                <div class="border-t my-4"></div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Calculated Indicators</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-center shadow-sm">
                        <div class="text-xs text-purple-600 mb-0.5 uppercase tracking-wider font-medium">Valuation (FDV) at Token Generation Event (TGE)</div>
                        <div class="text-xl font-semibold text-purple-900" id="displayFdvAtTGE">$0</div>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-center shadow-sm">
                        <div class="text-xs text-purple-600 mb-0.5 uppercase tracking-wider font-medium">Market Cap at Token Generation Event (TGE)</div>
                        <div class="text-xl font-semibold text-purple-900" id="displayMarketCapAtTGE">$0</div>
                    </div>
                </div>
            </div>
            
            <div id="core-parameters-card" class="tookle-card">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Core Parameters</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-3">
                    <div class="lg:col-span-1"><label for="tokenName">Token Name:</label><input type="text" id="tokenName" class="modern-input"></div>
                    <div class="lg:col-span-1"><label for="tokenTicker">Token Ticker:</label><input type="text" id="tokenTicker" class="modern-input"></div>
                    <div class="lg:col-span-1"><label for="supplyType">Supply Type: <span id="supplyTypeLock"></span></label><select id="supplyType" class="modern-input"><option value="capped">Capped</option><option value="uncapped">Uncapped (Inflationary)</option></select></div>
                    <div id="maxSupplyContainer" class="lg:col-span-1"><label for="tokenSupply">Total Token Supply (Max): <span id="tokenSupplyLock"></span></label><input type="number" id="tokenSupply" class="modern-input"></div>
                    <div id="uncappedFieldsContainer" class="lg:col-span-2 grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-3" style="display:none;">
                        <div class="lg:col-span-1"><label for="initialTokenSupply">Initial Token Supply: <span id="initialTokenSupplyLock"></span></label><input type="number" id="initialTokenSupply" class="modern-input"></div>
                        <div class="lg:col-span-1"><label for="annualInflationRate">Annual Inflation Rate (%):</label><input type="number" id="annualInflationRate" class="modern-input"></div>
                    </div>
                     <div class="lg:col-span-1"><label for="tgePrice">Price at TGE ($):</label><input type="number" id="tgePrice" step="0.000001" class="modern-input"></div>
                    <div class="lg:col-span-1"><label for="targetRaise">Total Target Raise ($):</label><input type="text" id="targetRaise" class="modern-input" placeholder="e.g., 3,000,000"><span id="targetRaiseError" class="error-message"></span></div>
                </div>
            </div>

            <div class="tookle-card">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Fundraising Rounds</h2>
                    <button onclick="addRound()" class="tookle-button tookle-button-secondary"><i data-lucide="plus-circle" class="mr-2 h-5 w-5"></i>Add Round</button>

                </div>
                <div class="overflow-x-auto">
                    <table id="roundsTable" class="data-table">
                        <colgroup class="hidden lg:table-column-group">
                            <col style="width: 15%;"><col style="width: 8%;"><col style="width: 8%;"><col style="width: 11%;"><col style="width: 11%;"><col style="width: 9%;"><col style="width: 8%;"><col style="width: 7%;"><col style="width: 7%;"><col style="width: 12%;"><col style="width: 8%;"><col style="width: 6%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Round Name</th><th class="hidden xl:table-cell">% Total Raise</th><th class="hidden xl:table-cell">% Discount</th><th class="bg-gray-200">Target Raise ($)</th><th class="bg-gray-200 hidden xl:table-cell">Actual Raised ($)</th><th class="bg-gray-200">Token Price ($)</th><th class="hidden md:table-cell">% TGE Unlock</th><th class="hidden md:table-cell">Cliff (m)</th><th class="hidden md:table-cell">Vesting (m)</th><th class="bg-gray-200"># Tokens</th><th class="bg-gray-200 hidden md:table-cell">% Supply</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr class="border-t-2">
                                <td class="font-semibold">Total</td><td id="totalRaisePercentCell" class="text-right font-semibold hidden xl:table-cell">0%</td><td class="hidden xl:table-cell"></td> <td id="totalAmountRaisedCell" class="text-right font-semibold">$0</td><td id="totalActualRaisedCell" class="text-right font-semibold hidden xl:table-cell">$0</td><td></td><td colspan="3" class="hidden md:table-cell"></td><td id="totalTokensCell" class="text-right font-semibold">0</td><td id="totalSupplyPercentCell" class="text-right font-semibold hidden md:table-cell">0.00%</td><td></td> 
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="tookle-card">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Token Allocation Table</h2>
                    <button onclick="addTranche()" class="tookle-button tookle-button-secondary"><i data-lucide="plus-circle" class="mr-2 h-5 w-5"></i>Add Main Tranche</button>
                </div>
                <p class="text-sm text-gray-500 -mt-4 mb-4">Define allocations for Team, Treasury, Ecosystem, etc. Other allocations are derived from the Fundraising Rounds table above.</p>
                <div class="overflow-x-auto">
                    <table id="allocationTable" class="data-table">
                         <colgroup class="hidden lg:table-column-group">
                            <col style="width: 30%;"><col style="width: 15%;"><col style="width: 15%;"><col style="width: 10%;"><col style="width: 10%;"><col style="width: 10%;"><col style="width: 10%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Tranche Name / Category</th><th class="hidden md:table-cell">% Allocation</th><th>Amount Tokens</th><th class="hidden md:table-cell">% TGE Unlock</th><th class="hidden md:table-cell">Cliff (months)</th><th class="hidden md:table-cell">Vesting (months)</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <td class="text-right font-semibold">Total Allocation:</td>
                                <td class="font-bold">
                                    <div class="flex items-center justify-start gap-x-2">
                                        <span id="totalAllocationCell">0.00%</span>
                                        <button type="button" id="adjustAllocationBtn" class="tookle-button tookle-button-secondary !p-1 !text-xs" title="Adjust tranches to 100%">Adjust</button>
                                    </div>
                                </td>
                                <td colspan="2" class="hidden md:table-cell"></td><td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="tookle-card">
                <div class="flex items-center gap-4">
                    <input type="checkbox" id="setActiveToggle" class="h-5 w-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <label for="setActiveToggle" class="font-medium text-gray-700 m-0">Make this the active scenario upon saving</label>
                </div>
            </div>
        </fieldset>
    </div>
    
    <!-- Investor List Modal -->
    <div id="investor-list-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 no-print">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="investor-list-modal-title">Investors</h3>
                    <button id="investor-list-modal-close" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th>Name</th><th>Email</th><th>Amount ($)</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="investor-list-modal-tbody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works Modal -->
    <div id="how-it-works-modal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-75 overflow-y-auto h-full w-full z-50 no-print flex items-center justify-center">
        <div class="relative mx-auto p-8 border w-full max-w-3xl shadow-lg rounded-md bg-white">
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button id="how-it-works-modal-close" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="mt-3 text-left">
                <h3 class="text-2xl leading-6 font-bold text-gray-900 mb-4">How to Manage Your Token Sale</h3>
                <div class="mt-4 space-y-4 text-sm text-gray-600">
                     <p>Your token sale setup is designed to be fair and transparent for everyone. Here’s how it works:</p>
                    
                    <div>
                        <h4 class="font-semibold text-gray-800 text-base">Our Commitment to Fairness: Automatic Locks 🔒</h4>
                        <p class="mt-1">To protect backers, some terms will automatically lock once the first investment is made. This builds trust by showing that the core deal won't change.</p>
                        <ul class="list-disc list-inside space-y-2 mt-2 pl-2">
                            <li><strong>Once an investment is made, these are locked for good:</strong>
                                <ul class="list-circle list-inside pl-4 mt-1">
                                    <li>Total Token Supply</li>
                                    <li>Token Type (e.g., Capped)</li>
                                </ul>
                            </li>
                            <li><strong>For rounds that have received funding:</strong> Key terms like <strong>Discount</strong> and <strong>Vesting</strong> are locked. You can still increase the % Total Raise to accept more investment, but you can't lower it below what's already been sold.</li>
                            <li><strong>For rounds with zero investment:</strong> You are free to edit all terms.</li>
                            </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-gray-800 text-base">Managing Your Allocations</h4>
                        <ul class="list-disc list-inside space-y-2 mt-2 pl-2">
                             <li>You can always adjust other allocations (like for your <strong>Team, Marketing, or Ecosystem</strong> funds) to reflect your final fundraising numbers.</li>
                            <li><strong>Quick Check:</strong> Use the “Adjust” button to make sure your non-fundraising allocations add up to 100% before you save.</li>
                        </ul>
                    </div>
                    
                    <!-- NEW: Vesting rules explainer -->
                    <div>
                        <h4 class="font-semibold text-gray-800 text-base">Vesting Rules</h4>
                        <ul class="list-disc list-inside space-y-2 mt-2 pl-2">
                            <li><strong>Immediate Unlock:</strong> If you set <strong>% TGE Unlock</strong> to 100%, the <strong>Cliff</strong> and <strong>Vesting</strong> periods are automatically set to 0, as all tokens are available immediately.</li>
                            <li><strong>No Vesting Period:</strong> Conversely, setting both <strong>Cliff</strong> and <strong>Vesting</strong> to 0 signifies that 100% of tokens for that tranche are unlocked at TGE.</li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-gray-800 text-base">Pro Tips for a Smooth Raise</h4>
                         <ul class="list-disc list-inside space-y-2 mt-2 pl-2">
                            <li>🆕 <strong>Need a major overhaul?</strong> It's best to start a new project from your dashboard to keep things clean.</li>
                            <li>⚠️ <strong>Editing is disabled during a live sale.</strong> This ensures clarity for your backers. You can make adjustments after the sale ends.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Page-specific scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
    const currentProjectId = <?php echo json_encode($current_project_id); ?>;
    const BACKEND_URL = '/backend/rounds_backend.php';
    let isDirty = false;
    let isEditMode = false;
    let allocationPieChart, emissionChart;
    let totalRaisedFromServer = 0;
    const validationState = { allocationSum: false, fundraisingSum: false, targetRaise: true, vestingCliffs: true, roundsRaise: true, allocationsPositive: true };
    const dom = { 
        mainFieldset: null, editScenarioBtn: null, 
        printSummaryBtn: null, supplyType: null, maxSupplyContainer: null, uncappedFieldsContainer: null, 
        versionSelector: null, coreParamsCard: null, draftStatus: null, roundsTableBody: null, 
        allocationTableBody: null, saveScenarioBtn: null, setActiveToggle: null, totalAllocationCell: null, 
        adjustAllocationBtn: null, targetRaiseError: null, validationErrorSummary: null, 
        totalRaisePercentCell: null, totalAmountRaisedCell: null, totalTokensCell: null, 
        totalSupplyPercentCell: null, validationStatus: null, howItWorksBtn: null, 
        howItWorksModal: null, howItWorksModalClose: null, cancelChangesBtn: null,
        editButtonContainer: null, editTooltip: null,
        validationAdjustBtn: null // MODIFICATION: Added new DOM element
    };
    
    const getStatusBadgeClass = (status) => { 
        status = String(status||'').toLowerCase().replace(/\s+/g,'-'); 
        if(['successful', 'payment-confirmed', 'campaign-successful', 'verified'].includes(status)) return 'bg-green-100 text-green-800';
        if(['initiated', 'in-escrow', 'submitted-in-escrow'].includes(status)) return 'bg-blue-100 text-blue-800';
        if(['submitted', 'pending', 'in-review'].includes(status)) return 'bg-yellow-100 text-yellow-800';
        if(['refunded', 'failed', 'rejected'].includes(status)) return 'bg-red-100 text-red-800';
        return 'bg-gray-100 text-gray-800';
    };

    function parseFormattedNumber(str) { return parseFloat(String(str).replace(/,/g, '')) || 0; }
    function formatNumberInput(input) { const value = parseFormattedNumber(input.value); input.value = value.toLocaleString('en-US');}
    
    function showCustomAlert(title, message) {
        alert(`${title}\n\n${message}`);
    }
    
    function toggleEditMode(enable) {
        isEditMode = enable;
        dom.mainFieldset.disabled = !enable;
        dom.versionSelector.disabled = enable;

        document.getElementById('view-mode-btns').classList.toggle('hidden', enable);
        document.getElementById('edit-mode-btns').classList.toggle('hidden', !enable);

        if (enable) {
            dom.saveScenarioBtn.disabled = true;
            dom.saveScenarioBtn.classList.remove('tookle-button-primary');
            dom.saveScenarioBtn.classList.add('tookle-button-secondary');

            applyLocks(totalRaisedFromServer);
            dom.roundsTableBody.querySelectorAll('tr').forEach(async (row) => {
                const roundName = row.dataset.roundName || row.querySelector('[data-col="name"]')?.value;
                if (roundName) {
                    const raised = await getRaisedAmountForRound(roundName);
                    lockRoundRow(row, raised > 0);
                }
            });
        }
    }

    async function fetchAndLoadScenarioData(versionId = null) {
        if (!currentProjectId) {
            showCustomAlert('Error', 'Project ID is missing. Cannot load data.', 'error');
            return;
        }
        let url = versionId 
            ? `${BACKEND_URL}?action=get_version&id=${versionId}&project_id=${currentProjectId}` 
            : `${BACKEND_URL}?action=get_latest&project_id=${currentProjectId}`;
        
        try {
            const response = await fetch(url);
            const data = await response.json();
            if (!response.ok || data.error) throw new Error(data.error || `HTTP error! status: ${response.status}`);
            
            const vestingMap = new Map();
            if (data.vesting && Array.isArray(data.vesting)) {
                for (const vest of data.vesting) {
                    vestingMap.set(vest.vesting_block_name, { unlock_tge: vest.percent_unlock_at_tge, cliff_months: vest.cliff_months, vesting_months: vest.vesting_months });
                }
            }
            const enrichedRounds = (data.rounds || []).map(round => ({ ...round, ...(vestingMap.get(round.round_name) || {}) }));
            const enrichedAllocations = (data.allocations || []).map(alloc => ({ ...alloc, ...(vestingMap.get(alloc.tranche_name) || {}) }));

            clearAllInputs(false);
            dom.setActiveToggle.checked = !!data.is_active;
            populateCoreParameters(data.core_params);
            await populateRoundsTable(enrichedRounds);
            populateAllocationTable(enrichedAllocations);
            await updateAllCalculations();
            setDirty(false);
            if (versionId) dom.versionSelector.value = versionId;
        } catch (error) {
            console.error("Error fetching scenario data:", error);
            showCustomAlert('Error', `Failed to load project data. ${error.message}`);
        }
    }

    async function fetchVersions() {
        if (!currentProjectId) return;
        try {
            const response = await fetch(`${BACKEND_URL}?action=list_versions&project_id=${currentProjectId}`);
            const versions = await response.json();
            if (versions.error) throw new Error(versions.error);
            dom.versionSelector.innerHTML = '<option value="" disabled>Select a version...</option>';
            if (versions.length > 0) {
                versions.forEach(version => {
                    const option = document.createElement('option');
                    option.value = version.id;
                    let text = `${version.version_label} (${new Date(version.created_at).toLocaleDateString()})`;
                    if (version.is_active == 1) { text += ' (Active)'; option.style.fontWeight = 'bold'; }
                    option.textContent = text;
                    dom.versionSelector.appendChild(option);
                });
            } else {
                dom.versionSelector.innerHTML = '<option disabled>No saved versions</option>';
            }
        } catch (error) { console.error("Error fetching versions:", error); }
    }
    
    function populateCoreParameters(params) { 
        if (!params) return;
        document.getElementById('tokenName').value = params.token_name || '';
        document.getElementById('tokenTicker').value = params.token_ticker || '';
        const supplyType = (params.type_supply || 'capped').toLowerCase();
        dom.supplyType.value = supplyType;
        handleSupplyTypeChange(supplyType);
        if (supplyType === 'capped') {
            document.getElementById('tokenSupply').value = params.supply_value || '';
        } else {
            document.getElementById('initialTokenSupply').value = params.supply_value || '';
            document.getElementById('annualInflationRate').value = params.annual_inflation_percent || '';
        }
        document.getElementById('tgePrice').value = params.calculated_price_tge || '';
        const targetRaiseInput = document.getElementById('targetRaise');
        targetRaiseInput.value = params.target_raise_usd || params.target_raise || '';
        formatNumberInput(targetRaiseInput);
    }
    
    function clearAllInputs(makeDirty = true) {
        document.querySelectorAll('#core-parameters-card input, #core-parameters-card select').forEach(input => {
            if (input.type !== 'select-one') input.value = ''; else input.selectedIndex = 0;
        });
        dom.roundsTableBody.innerHTML = '';
        dom.allocationTableBody.innerHTML = '';
        if (makeDirty) setDirty(true);
    }

    async function populateRoundsTable(rounds) {
        if (!rounds) return;
        dom.roundsTableBody.innerHTML = '';
        const uniqueRounds = []; const seenNames = new Set();
        for (const roundData of rounds) {
            if (roundData.round_name) {
                const roundNameLower = roundData.round_name.toLowerCase();
                if (!seenNames.has(roundNameLower)) { seenNames.add(roundNameLower); uniqueRounds.push(roundData); }
            }
        }
        for (const roundData of uniqueRounds) { await addRound(roundData, false); }
        lucide.createIcons();
    }
    
    function populateAllocationTable(allocations) {
        dom.allocationTableBody.innerHTML = '';
        const investorRow = dom.allocationTableBody.insertRow();
        investorRow.id = 'investor-tranche-row';
        investorRow.innerHTML = `<td><input type="text" class="modern-input" value="Investors" readonly></td><td class="hidden md:table-cell"><input type="number" class="modern-input" data-col="percentage" value="0.00" readonly></td><td class="text-right text-gray-600 bg-gray-50">0</td><td class="hidden md:table-cell"><input type="number" class="modern-input" data-col="tge_unlock" value="0" readonly></td><td class="hidden md:table-cell"><input type="number" class="modern-input" data-col="cliff" readonly></td><td class="hidden md:table-cell"><input type="number" class="modern-input" data-col="vesting" readonly></td><td class="text-center"></td>`;
        if (allocations) {
            allocations.forEach(allocData => {
                const nameLower = (allocData.tranche_name || '').trim().toLowerCase();
                if (nameLower && nameLower !== 'investor' && nameLower !== 'investors') { addTranche(allocData, false); }
            });
        }
    }

    function checkAllValidations() { 
        const isValid = validationState.allocationSum && validationState.fundraisingSum && validationState.targetRaise && validationState.vestingCliffs && validationState.roundsRaise && validationState.allocationsPositive;
        const errorMessages = [];
        let hasAllocationError = false;

        if (!validationState.allocationSum) {
            errorMessages.push("Allocation total must be 100%.");
            hasAllocationError = true;
        }
        if (!validationState.fundraisingSum) errorMessages.push("Fundraising round percentages must sum to 100%.");
        if (!validationState.targetRaise) errorMessages.push("Target Raise is invalid or below amount raised.");
        if (!validationState.vestingCliffs) errorMessages.push("One or more cliff periods are longer than vesting periods.");
        if (!validationState.roundsRaise) errorMessages.push("A round's '% Total Raise' is below the minimum required for tokens sold.");
        if (!validationState.allocationsPositive) errorMessages.push("Tranche allocation percentages cannot be negative.");

        if (isValid) {
            dom.validationStatus.classList.add('hidden');
        } else {
            dom.validationStatus.classList.remove('hidden');
            let errorHTML = "Please fix the following errors: " + errorMessages.join(' ');
            
            // --- MODIFICATION ---
            // The button is no longer part of this innerHTML
            dom.validationErrorSummary.innerHTML = errorHTML;

            // Show or hide the separate button
            if (hasAllocationError) {
                dom.validationAdjustBtn.classList.remove('hidden');
            } else {
                dom.validationAdjustBtn.classList.add('hidden');
            }
            // --- END MODIFICATION ---
        }
        
        if (dom.saveScenarioBtn) {
            if (isDirty && isValid) {
                dom.saveScenarioBtn.disabled = false;
                dom.saveScenarioBtn.classList.add('tookle-button-primary');
                dom.saveScenarioBtn.classList.remove('tookle-button-secondary');
            } else {
                dom.saveScenarioBtn.disabled = true;
                dom.saveScenarioBtn.classList.remove('tookle-button-primary');
                dom.saveScenarioBtn.classList.add('tookle-button-secondary');
            }
        }
        return isValid;
    }

    function validateTargetRaise() { 
        const targetRaiseInput = document.getElementById("targetRaise");
        const targetRaise = parseFormattedNumber(targetRaiseInput.value);
        if (totalRaisedFromServer > 0 && targetRaise < totalRaisedFromServer) {
            dom.targetRaiseError.textContent = `Must be >= ${totalRaisedFromServer.toLocaleString('en-US', { style: 'currency', currency: 'USD' })} (already raised).`;
            targetRaiseInput.classList.add('is-invalid');
            validationState.targetRaise = false;
        } else {
            dom.targetRaiseError.textContent = '';
            targetRaiseInput.classList.remove('is-invalid');
            validationState.targetRaise = true;
        }
    }
    function updateAllocationSummary() { 
        let totalPercentage = 0;
        dom.allocationTableBody.querySelectorAll('input[data-col="percentage"]').forEach(input => { totalPercentage += parseFloat(input.value) || 0; });
        dom.totalAllocationCell.textContent = `${totalPercentage.toFixed(2)}%`;
        
        // MODIFICATION: Increased tolerance for allocation sum check (from 0.01 to 0.1)
        const isTotal100 = Math.abs(totalPercentage - 100.0) < 0.1; 
        
        if(dom.adjustAllocationBtn) { dom.adjustAllocationBtn.disabled = isTotal100; }
        dom.totalAllocationCell.style.color = isTotal100 ? 'var(--success-green)' : 'var(--error-red)';
        validationState.allocationSum = isTotal100;
    }
    function validateVestingCliffs() {
        let allCliffsValid = true;
        [...dom.roundsTableBody.querySelectorAll('tr'), ...dom.allocationTableBody.querySelectorAll('tr')].forEach(row => {
            const cliffInput = row.querySelector('input[data-col="cliff"]'); const vestingInput = row.querySelector('input[data-col="vesting"]'); if(!cliffInput || !vestingInput) return;
            const cliff = parseInt(cliffInput.value, 10) || 0; const vesting = parseInt(vestingInput.value, 10) || 0;
            let errorCell = cliffInput.parentElement.querySelector('.error-message');
            if (!errorCell) { errorCell = document.createElement('span'); errorCell.className = 'error-message'; cliffInput.parentElement.appendChild(errorCell); }
            if (vesting > 0 && cliff > vesting) { cliffInput.classList.add('is-invalid'); errorCell.textContent = 'Cannot be > vesting.'; allCliffsValid = false; } else { cliffInput.classList.remove('is-invalid'); errorCell.textContent = ''; }
        });
        validationState.vestingCliffs = allCliffsValid;
    }
    
    function validateAllocations() {
        let allAllocationsValid = true;
        dom.allocationTableBody.querySelectorAll("tr:not(#investor-tranche-row)").forEach(row => {
            const percentageInput = row.querySelector('input[data-col="percentage"]');
            if (!percentageInput) return;
            
            const percentage = parseFloat(percentageInput.value) || 0;
            let errorCell = percentageInput.parentElement.querySelector('.error-message');
            if (!errorCell) { 
                errorCell = document.createElement('span'); 
                errorCell.className = 'error-message'; 
                percentageInput.parentElement.appendChild(errorCell); 
            }

            if (percentage < 0) {
                percentageInput.classList.add('is-invalid');
                errorCell.textContent = 'Cannot be negative.';
                allAllocationsValid = false;
            } else {
                percentageInput.classList.remove('is-invalid');
                errorCell.textContent = '';
            }
        });
        validationState.allocationsPositive = allAllocationsValid;
    }

    async function updateAllCalculations(){
        let allRoundsValid = true, totalActualRaised = 0;
        const isCapped = dom.supplyType ? dom.supplyType.value === 'capped' : true;
        const supplyValueInput = isCapped ? document.getElementById("tokenSupply") : document.getElementById("initialTokenSupply");
        const supplyValue = supplyValueInput ? supplyValueInput.value : 0;
        const tgePrice = parseFloat(document.getElementById("tgePrice").value) || 0;
        const totalTargetRaise = parseFormattedNumber(document.getElementById("targetRaise").value);
        const totalSupply = parseFloat(supplyValue) || 0;
        const fdv = totalSupply * tgePrice;
        if(document.getElementById("displayFdvAtTGE")) document.getElementById("displayFdvAtTGE").textContent = fdv > 0 ? fdv.toLocaleString("en-US", {style:'currency', currency:'USD', maximumFractionDigits: 0}) : "$0";
        
        let circulatingSupplyAtTGE = 0, totalRaisePercent = 0, totalAmount = 0, totalTokens = 0, totalSupplyPercent = 0;

        for (const row of dom.roundsTableBody.rows) {
            const raisePercent = parseFloat(row.querySelector('input[data-col="percent_raise"]').value) || 0;
            const discountPercent = parseFloat(row.querySelector('input[data-col="percent_discount"]').value) || 0;
            const tgeUnlockPercent = parseFloat(row.querySelector('input[data-col="tge_unlock"]').value) || 0;
            const roundName = row.querySelector('[data-col="name"]').value;
            const amountRaised = totalTargetRaise * (raisePercent / 100);
            const roundPrice = tgePrice * (1 - (discountPercent / 100));
            const numTokens = roundPrice > 0 ? amountRaised / roundPrice : 0;
            const percentSupply = totalSupply > 0 ? numTokens / totalSupply * 100 : 0;
            row.querySelector('.output-amount').textContent = amountRaised.toLocaleString("en-US", {style:'currency', currency:'USD', maximumFractionDigits:0});
            row.querySelector('.output-price').textContent = roundPrice.toLocaleString("en-US", {style:'currency', currency:'USD', minimumFractionDigits: 2, maximumFractionDigits:6});
            row.querySelector('.output-tokens').textContent = numTokens.toLocaleString("en-US", {maximumFractionDigits: 0});
            row.querySelector('.output-supply-percent').textContent = `${percentSupply.toFixed(2)}%`;
            totalRaisePercent += raisePercent; totalAmount += amountRaised; totalTokens += numTokens; totalSupplyPercent += percentSupply;
            circulatingSupplyAtTGE += numTokens * (tgeUnlockPercent / 100);
            const actualRaised = await getRaisedAmountForRound(roundName);
            const actualRaisedCell = row.querySelector('.output-actual-raised');
            actualRaisedCell.textContent = actualRaised.toLocaleString("en-US", {style:'currency', currency:'USD', maximumFractionDigits:0});
            if (actualRaised > 0) {
                actualRaisedCell.classList.add('has-investors');
                actualRaisedCell.dataset.roundName = roundName;
            } else {
                actualRaisedCell.classList.remove('has-investors');
            }

            totalActualRaised += actualRaised;
            
            let errorCell = row.querySelector('.raise-percent-error');
            if (!errorCell) { errorCell = document.createElement('span'); errorCell.className = 'error-message raise-percent-error'; row.querySelector('input[data-col="percent_raise"]').parentElement.appendChild(errorCell); }
            
            if(actualRaised > 0) {
                lockRoundRow(row, true);
                const minTargetRaiseUSD = actualRaised; // The minimum raise is what's already been raised.
                const minRaisePercent = totalTargetRaise > 0 ? (minTargetRaiseUSD / totalTargetRaise) * 100 : 0;
                
                // UX FIX: Use a small epsilon for float comparison to prevent incorrect validation errors due to precision.
                if ((raisePercent + 0.001) < minRaisePercent) { 
                    row.querySelector('input[data-col="percent_raise"]').classList.add('is-invalid'); 
                    errorCell.textContent = `Min ${minRaisePercent.toFixed(2)}%`; 
                    allRoundsValid = false; 
                } else { 
                    row.querySelector('input[data-col="percent_raise"]').classList.remove('is-invalid'); 
                    errorCell.textContent = ''; 
                }
            } else { 
                lockRoundRow(row, false); 
                row.querySelector('input[data-col="percent_raise"]').classList.remove('is-invalid'); 
                errorCell.textContent = ''; 
            }
        }
        validationState.roundsRaise = allRoundsValid;
        document.getElementById('totalActualRaisedCell').textContent = totalActualRaised.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
        validationState.fundraisingSum = Math.abs(totalRaisePercent - 100) < 0.1;
        // UX FIX: Display the total raise percentage with more precision to avoid confusion.
        dom.totalRaisePercentCell.textContent = `${totalRaisePercent.toFixed(2)}%`;
        dom.totalRaisePercentCell.style.color = validationState.fundraisingSum ? 'inherit' : 'var(--error-red)';
        dom.totalAmountRaisedCell.textContent = totalAmount.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
        dom.totalTokensCell.textContent = totalTokens.toLocaleString('en-US', {maximumFractionDigits: 0});
        dom.totalSupplyPercentCell.textContent = `${totalSupplyPercent.toFixed(2)}%`;
        
        let investorRow = document.getElementById('investor-tranche-row');
        if (investorRow) {
            investorRow.querySelector('input[data-col="percentage"]').value = totalSupplyPercent.toFixed(2);
            investorRow.cells[2].textContent = (totalSupply * totalSupplyPercent / 100).toLocaleString("en-US", {maximumFractionDigits: 0});
        }

        dom.allocationTableBody.querySelectorAll("tr:not(#investor-tranche-row)").forEach(row => {
            const percent = parseFloat(row.querySelector('input[data-col="percentage"]').value) || 0;
            const numTokens = totalSupply * percent / 100;
            if(row.cells[2]) row.cells[2].textContent = numTokens.toLocaleString("en-US", {maximumFractionDigits: 0});
            const tgeUnlockPercent = parseFloat(row.querySelector('input[data-col="tge_unlock"]').value) || 0;
            circulatingSupplyAtTGE += numTokens * (tgeUnlockPercent / 100);
        });

        const marketCap = circulatingSupplyAtTGE * tgePrice;
        if(document.getElementById("displayMarketCapAtTGE")) document.getElementById("displayMarketCapAtTGE").textContent = marketCap > 0 ? marketCap.toLocaleString("en-US", {style:'currency', currency:'USD', maximumFractionDigits: 0}) : "$0";
        
        const allocationDataForChart = [];
        if (totalSupplyPercent > 0) allocationDataForChart.push({ name: 'Investors', percent: totalSupplyPercent });
        dom.allocationTableBody.querySelectorAll("tr:not(#investor-tranche-row)").forEach(row => {
            const name = row.querySelector('input[data-col="name"]').value;
            const percent = parseFloat(row.querySelector('input[data-col="percentage"]').value);
            if (percent > 0 && name) allocationDataForChart.push({ name: name, percent: percent });
        });
        
        updateAllocationChart(allocationDataForChart);
        updateEmissionChart();
        updateAllocationSummary();
        validateVestingCliffs();
        validateTargetRaise();
        validateAllocations();
        checkAllValidations();
    }
    
    function setDirty(state = true) { 
        isDirty = state; 
        dom.draftStatus.classList.toggle('hidden', !isDirty); 
        checkAllValidations(); 
    }

    // --- BUG FIX (Attempt 2) ---
    // This function now copies the supply value between the two inputs when switching types.
    // It is called by an async event listener to ensure calculations run *after* the value is copied.
    function handleSupplyTypeChange() {
        const selectedType = dom.supplyType.value;
        const isCapped = selectedType.toLowerCase() === 'capped';
        
        const cappedInput = document.getElementById("tokenSupply");
        const uncappedInput = document.getElementById("initialTokenSupply");

        if (isCapped) {
            // Switching TO Capped
            // If Capped is empty but Uncapped has a value, copy it over.
            if (cappedInput.value === '' && uncappedInput.value !== '') {
                cappedInput.value = uncappedInput.value;
            }
        } else {
            // Switching TO Uncapped
            // If Uncapped is empty but Capped has a value, copy it over.
            if (uncappedInput.value === '' && cappedInput.value !== '') {
                uncappedInput.value = cappedInput.value;
            }
        }

        dom.maxSupplyContainer.style.display = isCapped ? 'block' : 'none';
        dom.uncappedFieldsContainer.style.display = isCapped ? 'none' : 'grid';
    }
    // --- END BUG FIX ---

    function isRoundNameDuplicate(name, currentRow) { for (const row of dom.roundsTableBody.querySelectorAll("tr")) { if (row !== currentRow) { const input = row.querySelector('input[data-col="name"]'); if (input && input.value.trim().toLowerCase() === name.trim().toLowerCase()) return true; } } return false; }
    
    function setupVestingRulesForRow(row) {
        const tgeUnlockInput = row.querySelector('[data-col="tge_unlock"]');
        const cliffInput = row.querySelector('[data-col="cliff"]');
        const vestingInput = row.querySelector('[data-col="vesting"]');
        
        if (!tgeUnlockInput || !cliffInput || !vestingInput) return;

        const onTgeChange = () => {
            const isCliffLocked = cliffInput.hasAttribute('data-investment-locked');
            const isVestingLocked = vestingInput.hasAttribute('data-investment-locked');
            
            const tgeUnlock = parseFloat(tgeUnlockInput.value) || 0;

            if (tgeUnlock === 100) {
                if (!isCliffLocked && cliffInput.value !== '0') cliffInput.value = '0';
                if (!isVestingLocked && vestingInput.value !== '0') vestingInput.value = '0';
            }
        };

        const onVestingChange = () => {
            const isTgeLocked = tgeUnlockInput.hasAttribute('data-investment-locked');
            
            const cliff = parseInt(cliffInput.value) || 0;
            const vesting = parseInt(vestingInput.value) || 0;
            const tgeUnlock = parseFloat(tgeUnlockInput.value) || 0;

            if (cliff === 0 && vesting === 0) {
                if (!isTgeLocked && tgeUnlock !== 100) {
                    tgeUnlockInput.value = '100';
                }
            } else { // cliff or vesting is > 0
                if (tgeUnlock === 100 && !isTgeLocked) {
                    tgeUnlockInput.value = '0'; 
                }
            }
        };

        tgeUnlockInput.addEventListener('input', onTgeChange);
        cliffInput.addEventListener('input', onVestingChange);
        vestingInput.addEventListener('input', onVestingChange);
        
        const syncInitialState = () => {
            const tgeUnlock = parseFloat(tgeUnlockInput.value) || 0;
            const cliff = parseInt(cliffInput.value) || 0;
            const vesting = parseInt(vestingInput.value) || 0;

            if (tgeUnlock === 100) {
                onTgeChange();
            } else if (cliff === 0 && vesting === 0) {
                onVestingChange();
            }
        };

        syncInitialState();
    }
    
    async function addRound(data = {}, renderIcons = true) { 
        const newRow = dom.roundsTableBody.insertRow();
        newRow.setAttribute('data-round-name', data.round_name || '');
        newRow.innerHTML = `<td><input type="text" class="modern-input" data-col="name" value="${data.round_name || ''}"></td><td class="hidden xl:table-cell"><div class="relative"><input type="number" min="0" class="modern-input" data-col="percent_raise" value="${data.percent_total_raise || ''}"></div></td><td class="hidden xl:table-cell"><input type="number" min="0" class="modern-input" data-col="percent_discount" value="${data.percent_discount || ''}"></td><td class="text-right text-gray-600 bg-gray-50 output-amount">$0</td><td class="text-right text-gray-600 bg-gray-50 output-actual-raised hidden xl:table-cell">$0</td><td class="text-right text-gray-600 bg-gray-50 output-price">$0.00</td><td class="hidden md:table-cell"><input type="number" min="0" max="100" class="modern-input" data-col="tge_unlock" value="${data.unlock_tge || 0}"></td><td class="hidden md:table-cell"><input type="number" min="0" class="modern-input" data-col="cliff" value="${data.cliff_months || ''}"></td><td class="hidden md:table-cell"><input type="number" min="0" class="modern-input" data-col="vesting" value="${data.vesting_months || ''}"></td><td class="text-right text-gray-600 bg-gray-50 output-tokens">0</td><td class="text-right text-gray-600 bg-gray-50 output-supply-percent hidden md:table-cell">0.00%</td><td class="text-center actions-cell"><button type="button" onclick="deleteRow(this)" class="delete-row-btn"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>`;
        newRow.querySelector('input[data-col="name"]').addEventListener('blur', (e) => { if (e.target.value && isRoundNameDuplicate(e.target.value, e.target.closest('tr'))) { showCustomAlert('Duplicate Name', `A fundraising round named "${e.target.value}" already exists.`); e.target.value = ''; } });
        newRow.querySelectorAll('input').forEach(input => input.addEventListener('input', async () => { setDirty(true); await updateAllCalculations(); }));
        setupVestingRulesForRow(newRow); // Apply vesting rules
        if(renderIcons) lucide.createIcons();
    }
    function addTranche(data = {}, renderIcons = true) { 
        const newRow = dom.allocationTableBody.insertRow();
        newRow.innerHTML = `<td><input type="text" class="modern-input" data-col="name" value="${data.tranche_name || ''}"></td><td class="hidden md:table-cell"><input type="number" min="0" class="modern-input" data-col="percentage" value="${data.allocation_percent || ''}"></td><td class="text-right text-gray-600 bg-gray-50">0</td><td class="hidden md:table-cell"><input type="number" min="0" max="100" class="modern-input" data-col="tge_unlock" value="${data.unlock_tge || 0}"></td><td class="hidden md:table-cell"><input type="number" min="0" class="modern-input" data-col="cliff" value="${data.cliff_months || ''}"></td><td class="hidden md:table-cell"><input type="number" min="0" class="modern-input" data-col="vesting" value="${data.vesting_months || ''}"></td><td class="text-center"><button type="button" onclick="deleteRow(this)" class="delete-row-btn"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>`;
        newRow.querySelector('input[data-col="name"]').addEventListener('blur', (e) => { const nameValue = e.target.value.trim().toLowerCase(); if (nameValue === 'investor' || nameValue === 'investors') { showCustomAlert('Invalid Name', 'The "Investors" category is automatically calculated.'); e.target.value = ''; } });
        newRow.querySelectorAll('input').forEach(input => input.addEventListener('input', () => { setDirty(true); updateAllCalculations(); }));
        setupVestingRulesForRow(newRow); // Apply vesting rules
        if(renderIcons) lucide.createIcons();
    }
    
    function adjustAllocations() {
        const editableRows = Array.from(dom.allocationTableBody.querySelectorAll("tr:not(#investor-tranche-row)"));
        const lockedPercent = parseFloat(document.getElementById('investor-tranche-row')?.querySelector('input[data-col="percentage"]').value) || 0;
        let editableTotal = 0;
        editableRows.forEach(row => { editableTotal += parseFloat(row.querySelector('input[data-col="percentage"]').value) || 0; });
        const difference = 100 - (lockedPercent + editableTotal);
        if (Math.abs(difference) < 0.01 || editableRows.length === 0) return;
        if (editableTotal === 0) {
            const adjustmentPerTranche = difference / editableRows.length;
            editableRows.forEach(row => { row.querySelector('input[data-col="percentage"]').value = (adjustmentPerTranche).toFixed(2); });
        } else {
            editableRows.forEach(row => { const currentPercent = parseFloat(row.querySelector('input[data-col="percentage"]').value) || 0; const proportion = currentPercent / editableTotal; row.querySelector('input[data-col="percentage"]').value = (currentPercent + (difference * proportion)).toFixed(2); });
        }
        updateAllCalculations(); setDirty(true);
    }
    
    function deleteRow(button) { button.closest('tr').remove(); updateAllCalculations(); setDirty(true); }
    
    async function fetchInvestmentsForRound(roundName) {
        if (!currentProjectId || !roundName) return [];
        try {
            const url = `${BACKEND_URL}?action=get_investments_for_round&project_id=${currentProjectId}&round_name=${encodeURIComponent(roundName)}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            return data.investments || [];
        } catch (error) { console.error(`Failed to fetch investments for round "${roundName}":`, error); return []; }
    }
    async function getRaisedAmountForRound(roundName) {
        if (!roundName) return 0;
        const investments = await fetchInvestmentsForRound(roundName);
        if (!Array.isArray(investments)) { console.error("fetchInvestmentsForRound did not return an array for round:", roundName); return 0; }
        
        // Filter for investments with a 'successful' payment status before summing
        return investments
            .filter(inv => inv.payment_status && inv.payment_status.toLowerCase() === 'successful')
            .reduce((total, inv) => total + (parseFloat(inv.investment_amount) || 0), 0);
    }
    
    function applyLocks(totalRaised) {
        const isLocked = totalRaised > 0;
        document.querySelectorAll('#supplyType, #tokenSupply, #initialTokenSupply').forEach(el => el.disabled = isLocked);
        document.querySelectorAll('#supplyTypeLock, #tokenSupplyLock, #initialTokenSupplyLock').forEach(el => { el.innerHTML = isLocked ? `<i data-lucide="lock" class="w-3 h-3 ml-1 text-gray-400" data-tooltip="Supply is locked after the first investment is received."></i>` : ''; });
        lucide.createIcons();
    }
        
    function lockRoundRow(row, shouldLock) {
        const fieldsToLock = ['percent_discount', 'tge_unlock', 'cliff', 'vesting'];
        row.querySelectorAll('input').forEach(input => {
            if (fieldsToLock.includes(input.dataset.col)) {
                if (shouldLock) {
                    input.readOnly = true;
                    input.setAttribute('data-investment-locked', 'true');
                } else {
                    input.readOnly = false;
                    input.removeAttribute('data-investment-locked');
                }
            }
        });

        const deleteBtn = row.querySelector('.delete-row-btn');
        const actionsCell = row.querySelector('.actions-cell');
        if(deleteBtn) { deleteBtn.style.display = shouldLock ? 'none' : 'inline-block'; deleteBtn.disabled = shouldLock; }
        const existingLockIcon = actionsCell.querySelector('.lock-icon');
        if (shouldLock && !existingLockIcon) {
            const lockIconSpan = document.createElement('span'); lockIconSpan.className = 'lock-icon inline-flex items-center'; lockIconSpan.setAttribute('data-tooltip', 'This round is partially locked because it has received investments.'); lockIconSpan.innerHTML = '<i data-lucide="lock" class="w-4 h-4 text-gray-500"></i>';
            actionsCell.appendChild(lockIconSpan); lucide.createIcons({nodes: [lockIconSpan]});
        } else if (!shouldLock && existingLockIcon) { existingLockIcon.remove(); }
    }

    async function saveData() {
        if (!checkAllValidations()) { showCustomAlert('Invalid Data', 'Please fix the errors before saving.'); return; }
        if (!currentProjectId) { showCustomAlert('Error', 'No project ID is loaded.'); return; }
        if (!isDirty) { showCustomAlert('Up to Date', 'No changes to save.'); return; }
        
        const payload = {
            project_id: currentProjectId,
            make_active: dom.setActiveToggle.checked,
            core_params: { token_name: document.getElementById('tokenName').value, token_ticker: document.getElementById('tokenTicker').value, type_supply: dom.supplyType.value, supply_value: dom.supplyType.value === 'capped' ? document.getElementById('tokenSupply').value : document.getElementById('initialTokenSupply').value, annual_inflation_percent:  dom.supplyType.value === 'uncapped' ? document.getElementById('annualInflationRate').value : null, calculated_price_tge: document.getElementById('tgePrice').value, target_raise: parseFormattedNumber(document.getElementById('targetRaise').value).toString() },
            rounds: Array.from(dom.roundsTableBody.rows).map(r => ({ round_name: r.querySelector('[data-col="name"]').value, percent_total_raise: r.querySelector('[data-col="percent_raise"]').value, percent_discount: r.querySelector('[data-col="percent_discount"]').value, unlock_tge: r.querySelector('[data-col="tge_unlock"]').value, cliff_months: r.querySelector('[data-col="cliff"]').value, vesting_months: r.querySelector('[data-col="vesting"]').value })),
            allocations: Array.from(dom.allocationTableBody.rows).filter(r => r.id !== 'investor-tranche-row').map(r => ({ tranche_name: r.querySelector('[data-col="name"]').value, allocation_percent: r.querySelector('[data-col="percentage"]').value, unlock_tge: r.querySelector('[data-col="tge_unlock"]').value, cliff_months: r.querySelector('[data-col="cliff"]').value, vesting_months: r.querySelector('[data-col="vesting"]').value }))
        };
        
        try {
            const response = await fetch(BACKEND_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await response.json();
            if (result.success) {
                showCustomAlert('Success', result.message); 
                setDirty(false); 
                await fetchVersions(); 
                dom.versionSelector.value = result.new_version_id;
                toggleEditMode(false);
            } else { throw new Error(result.details || result.error); }
        } catch (error) { console.error("Error saving scenario:", error); showCustomAlert('Error', `Failed to save data: ${error.message}`); }
    }
    
    function updateAllocationChart(allocationData) {
        if (!allocationData) return;
        const labels = [], data = [];
        allocationData.forEach(item => { if (item.name && item.percent > 0) { labels.push(item.name); data.push(item.percent); } });
        const ctx = document.getElementById('allocationPieChart').getContext('2d');
        if (allocationPieChart) allocationPieChart.destroy();
        allocationPieChart = new Chart(ctx, { type: 'pie', data: { labels: labels, datasets: [{ data: data, backgroundColor: ['#6d28d9','#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe','#5b21b6','#7c3aed','#9f7aea'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom', labels:{ boxWidth: 12, padding: 15, font: {size: 11} } } } } });
    }
    function updateEmissionChart() {
        const CHART_MONTHS = 60, CHART_COLORS = ['#6d28d9','#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe','#5b21b6', '#7c3aed', '#9f7aea', '#b794f4', '#d6bcfa'];
        const supplyValue = (dom.supplyType.value === 'capped') ? document.getElementById("tokenSupply").value : document.getElementById("initialTokenSupply").value;
        const totalSupply = parseFloat(supplyValue) || 0;
        const ctx = document.getElementById('emissionChart').getContext('2d');
        if (emissionChart) emissionChart.destroy();
        if (totalSupply === 0) return;
        const vestingItems = [], categories = new Set();
        dom.roundsTableBody.querySelectorAll('tr').forEach(row => { const name = row.querySelector('[data-col="name"]').value, numTokens = parseFormattedNumber(row.querySelector('.output-tokens').textContent); if (numTokens > 0 && name) { categories.add(name); vestingItems.push({ category: name, tokens: numTokens, tgeUnlock: parseFloat(row.querySelector('[data-col="tge_unlock"]').value) || 0, cliff: parseInt(row.querySelector('[data-col="cliff"]').value) || 0, vesting: parseInt(row.querySelector('[data-col="vesting"]').value) || 0 }); } });
        dom.allocationTableBody.querySelectorAll('tr:not(#investor-tranche-row)').forEach(row => { const name = row.querySelector('[data-col="name"]').value, numTokens = parseFormattedNumber(row.cells[2].textContent); if (numTokens > 0 && name) { categories.add(name); vestingItems.push({ category: name, tokens: numTokens, tgeUnlock: parseFloat(row.querySelector('[data-col="tge_unlock"]').value) || 0, cliff: parseInt(row.querySelector('[data-col="cliff"]').value) || 0, vesting: parseInt(row.querySelector('[data-col="vesting"]').value) || 0 }); } });
        const categoryList = Array.from(categories), categoryMonthlyUnlocks = {};
        categoryList.forEach(cat => { categoryMonthlyUnlocks[cat] = new Array(CHART_MONTHS).fill(0); });
        vestingItems.forEach(item => { if (item.tokens <= 0 || !item.category) return; const tgeAmount = item.tokens * (item.tgeUnlock / 100); if (tgeAmount > 0) categoryMonthlyUnlocks[item.category][0] += tgeAmount; const remainingTokens = item.tokens - tgeAmount; if (remainingTokens > 0 && item.vesting > 0) { const monthlyVestingAmount = remainingTokens / item.vesting; for (let m = 0; m < item.vesting; m++) { const currentMonthIndex = (item.cliff || 0) + m + 1; if (currentMonthIndex < CHART_MONTHS) categoryMonthlyUnlocks[item.category][currentMonthIndex] += monthlyVestingAmount; } } });
        const cumulativeCategoryData = {};
        categoryList.forEach(cat => { cumulativeCategoryData[cat] = new Array(CHART_MONTHS).fill(0); let runningTotal = 0; for (let i = 0; i < CHART_MONTHS; i++) { runningTotal += categoryMonthlyUnlocks[cat]?.[i] || 0; cumulativeCategoryData[cat][i] = runningTotal; } });
        const emissionDatasets = categoryList.map((cat, index) => ({ label: cat, data: cumulativeCategoryData[cat], borderColor: CHART_COLORS[index % CHART_COLORS.length], backgroundColor: CHART_COLORS[index % CHART_COLORS.length] + '30', fill: true, tension: 0.1, pointRadius: 0 }));
        const monthlyLabels = Array.from({ length: CHART_MONTHS }, (_, i) => `M${i}`); monthlyLabels[0] = 'TGE';
        emissionChart = new Chart(ctx, { type: 'line', data: { labels: monthlyLabels, datasets: emissionDatasets }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10, padding: 10 } } }, scales: { x: { title: { display: true, text: 'Months Since TGE' } }, y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Cumulative Tokens Unlocked' }, ticks: { callback: (val) => (val/1e6) >= 1 ? `${(val/1e6).toFixed(1)}M` : (val/1e3) >= 1 ? `${(val/1e3).toFixed(0)}K` : val } } } } });
    }

    async function showInvestorsForRound(roundName) {
        const modalTitle = document.getElementById('investor-list-modal-title');
        const modalTbody = document.getElementById('investor-list-modal-tbody');
        const modal = document.getElementById('investor-list-modal');

        modalTitle.textContent = `Backers in ${roundName}`;
        modalTbody.innerHTML = '<tr><td colspan="4" class="text-center p-4">Loading...</td></tr>';
        modal.classList.remove('hidden');
        
        const investments = await fetchInvestmentsForRound(roundName);
        
        if (investments.length > 0) {
            modalTbody.innerHTML = '';
            investments.forEach(inv => {
                const row = modalTbody.insertRow();
                const name = `${inv.first_name || ''} ${inv.last_name || ''}`.trim() || 'N/A';
                const amount = parseFloat(inv.investment_amount).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
                row.innerHTML = `<td>${name}</td><td>${inv.email}</td><td>${amount}</td><td><span class="status-badge ${getStatusBadgeClass(inv.payment_status)}">${inv.payment_status || 'N/A'}</span></td>`;
            });
        } else {
            modalTbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-gray-500">No Backers found for this round.</td></tr>';
        }
        lucide.createIcons();
    }

    document.addEventListener('DOMContentLoaded', async () => {
        // Initialize DOM element references
        dom.mainFieldset = document.getElementById('main-fieldset');
        dom.editScenarioBtn = document.getElementById('editScenarioBtn');
        dom.printSummaryBtn = document.getElementById('printSummaryBtn');
        dom.supplyType = document.getElementById('supplyType');
        dom.maxSupplyContainer = document.getElementById('maxSupplyContainer');
        dom.uncappedFieldsContainer = document.getElementById('uncappedFieldsContainer');
        dom.versionSelector = document.getElementById('versionSelector');
        dom.coreParamsCard = document.getElementById('core-parameters-card');
        dom.draftStatus = document.getElementById('draft-status');
        dom.roundsTableBody = document.getElementById('roundsTable').querySelector('tbody');
        dom.allocationTableBody = document.getElementById('allocationTable').querySelector('tbody');
        dom.saveScenarioBtn = document.getElementById('saveScenarioBtn');
        dom.setActiveToggle = document.getElementById('setActiveToggle');
        dom.totalAllocationCell = document.getElementById('totalAllocationCell');
        dom.adjustAllocationBtn = document.getElementById('adjustAllocationBtn');
        dom.targetRaiseError = document.getElementById('targetRaiseError');
        dom.validationErrorSummary = document.getElementById('validation-error-summary');
        dom.totalRaisePercentCell = document.getElementById('totalRaisePercentCell');
        dom.totalAmountRaisedCell = document.getElementById('totalAmountRaisedCell');
        dom.totalTokensCell = document.getElementById('totalTokensCell');
        dom.totalSupplyPercentCell = document.getElementById('totalSupplyPercentCell');
        dom.validationStatus = document.getElementById('validation-status');
        dom.howItWorksBtn = document.getElementById('howItWorksBtn');
        dom.howItWorksModal = document.getElementById('how-it-works-modal');
        dom.howItWorksModalClose = document.getElementById('how-it-works-modal-close');
        dom.cancelChangesBtn = document.getElementById('cancelChangesBtn');
        dom.editButtonContainer = document.getElementById('edit-button-container');
        dom.editTooltip = document.getElementById('edit-tooltip');
        dom.validationAdjustBtn = document.getElementById('validation-adjust-btn'); // MODIFICATION: Added new DOM element
        
        lucide.createIcons();

        // Setup event listeners
        dom.editScenarioBtn.addEventListener('click', () => toggleEditMode(true));
        dom.cancelChangesBtn.addEventListener('click', async () => {
            if (isDirty && !confirm("You have unsaved changes that will be lost. Are you sure you want to cancel?")) {
                return;
            }
            await fetchAndLoadScenarioData(dom.versionSelector.value || null);
            toggleEditMode(false);
        });
        document.getElementById('targetRaise').addEventListener('blur', (e) => formatNumberInput(e.target));
        dom.printSummaryBtn?.addEventListener('click', () => window.print());
        dom.adjustAllocationBtn?.addEventListener('click', adjustAllocations);
        
        // --- BUG FIX (Attempt 2) ---
        // Make the supplyType listener async to control the order of operations.
        dom.supplyType.addEventListener('change', async () => { 
            handleSupplyTypeChange(); // 1. Copy the value first.
            setDirty(true);           // 2. Set dirty flag.
            await updateAllCalculations(); // 3. Manually trigger calculations *after* value is copied.
        });
        // --- END BUG FIX ---

        dom.saveScenarioBtn.addEventListener('click', saveData);
        dom.setActiveToggle.addEventListener('change', () => { if(dom.versionSelector.value) setDirty(true); });
        dom.versionSelector.addEventListener('change', async (e) => {
            const versionId = e.target.value; if (!versionId) return;
            if (isDirty && !confirm("You have unsaved changes that will be lost. Load selected version?")) {
                e.target.value = Array.from(e.target.options).find(opt => opt.defaultSelected)?.value || ""; 
                return; 
            }
            await fetchAndLoadScenarioData(versionId);
            toggleEditMode(false);
        });
        dom.roundsTableBody.addEventListener('click', (e) => {
            const targetCell = e.target.closest('.output-actual-raised');
            if (targetCell && targetCell.classList.contains('has-investors')) {
                const roundName = targetCell.dataset.roundName;
                if (roundName) showInvestorsForRound(roundName);
            }
        });
        document.getElementById('investor-list-modal-close').addEventListener('click', () => {
            document.getElementById('investor-list-modal').classList.add('hidden');
        });

        // Modal listeners
        dom.howItWorksBtn.addEventListener('click', () => dom.howItWorksModal.classList.remove('hidden'));
        dom.howItWorksModalClose.addEventListener('click', () => dom.howItWorksModal.classList.add('hidden'));
        dom.howItWorksModal.addEventListener('click', (e) => {
            if (e.target === dom.howItWorksModal) { // Click on overlay
                dom.howItWorksModal.classList.add('hidden');
            }
        });


        // Add a single event listener to the fieldset for all inputs
        dom.mainFieldset.addEventListener('input', async (e) => {
            // --- BUG FIX (Attempt 2) ---
            // Prevent this from running when the supplyType dropdown is changed,
            // as we now have a dedicated listener for that.
            if (e.target.id === 'supplyType') {
                return;
            }
            // --- END BUG FIX ---
            setDirty(true);
            await updateAllCalculations();
        });


        // Initial data load
        toggleEditMode(false); // Start in read-only mode.

        if (currentProjectId) {
            try {
                const res = await fetch(`${BACKEND_URL}?action=get_total_raised&project_id=${currentProjectId}`);
                if (!res.ok) throw new Error(`Server responded with status ${res.status}`);
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                if (typeof data.total_raised === 'undefined') throw new Error('Backend response did not include total_raised.');
                totalRaisedFromServer = parseFloat(data.total_raised) || 0;
                applyLocks(totalRaisedFromServer);

                // --- NEW ---: Check for live sale status
                const liveSaleRes = await fetch(`${BACKEND_URL}?action=check_live_sale&project_id=${currentProjectId}`);
                const liveSaleData = await liveSaleRes.json();
                if (liveSaleData.is_live) {
                    dom.editScenarioBtn.disabled = true;
                    dom.editTooltip.textContent = '🔒 Backers Protection: Editing is disabled during a live sale to keep information clear and readable for backers.';
                } else {
                     dom.editScenarioBtn.disabled = false;
                    dom.editTooltip.textContent = '';
                }

            } catch (e) {
                console.error("CRITICAL ERROR: Could not fetch initial project data.", e);
                showCustomAlert('Fatal Error', `Could not load critical project data. Please check the server connection and refresh the page.`);
                return;
            }
            await fetchVersions();
            await fetchAndLoadScenarioData();
        } else {
            showCustomAlert('Error', 'No project selected.');
            if (dom.saveScenarioBtn) dom.saveScenarioBtn.disabled = true;
        }
    });
</script>
