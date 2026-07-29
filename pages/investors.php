<?php
// investors.php - MERGED VERSION (Preprod Features + Dev KYC Logic + Technical Columns)
// Page content for "Manage Investors"
$current_project_id = $_GET['project_id'] ?? $_SESSION['active_project_id'] ?? null;

?>
<style>
    :root {
        --tookle-purple: #6D28D9;
        --tookle-purple-light: #EDE9FE;
        --tookle-purple-dark: #4C1D95;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border-color: #e5e7eb;

        /* Status Colors */
        --status-green-bg: #dcfce7;
        --status-green-text: #16a34a; /* Active / Verified */
        --status-gray-bg: #f3f4f6;
        --status-gray-text: #4b5563; /* Canceled / N/A */
        --status-yellow-bg: #fef9c3;
        --status-yellow-text: #ca8a04; /* Pending / In Review */
        --status-red-bg: #fee2e2;
        --status-red-text: #dc2626; /* Failed */
        --status-blue-bg: #dbeafe;
        --status-blue-text: #2563eb; /* Secured */
    }
    body { font-family: 'Montserrat', sans-serif; background-color: #f9fafb; color: var(--text-primary); }

    .data-table th, .data-table td { vertical-align: middle; padding: 0.75rem 1rem; white-space: nowrap; }
    .data-table thead th {
        background-color: #f9fafb; color: var(--text-secondary); font-weight: 500;
        text-align: left; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;
    }
    .data-table tbody tr:hover { background-color: #f0f9ff; }

    .status-badge {
        border-radius: 9999px; padding: 0.125rem 0.625rem; font-size: 0.75rem;
        font-weight: 500; text-align: center; min-width: 100px;
        display: inline-block; text-transform: capitalize;
    }

    /* Main Matrix Operational Statuses */
    .status-badge.status-awaiting-payment { background-color: var(--status-yellow-bg); color: var(--status-yellow-text); }
    .status-badge.status-payment-failed { background-color: var(--status-red-bg); color: var(--status-red-text); }
    .status-badge.status-payment-secured { background-color: var(--status-blue-bg); color: var(--status-blue-text); }
    .status-badge.status-ready-for-distribution { background-color: var(--tookle-purple-light); color: var(--tookle-purple-dark); }
    .status-badge.status-vesting-active { background-color: var(--status-green-bg); color: var(--status-green-text); }
    .status-badge.status-vesting-canceled { background-color: var(--status-red-bg); color: var(--status-red-text); }
    .status-badge.status-refunding { background-color: var(--status-yellow-bg); color: var(--status-yellow-text); }
    .status-badge.status-refunded { background-color: var(--status-gray-bg); color: var(--status-gray-text); }
    .status-badge.status-canceled { background-color: var(--status-gray-bg); color: var(--status-gray-text); }
    .status-badge.status-under-review { background-color: var(--status-yellow-bg); color: var(--status-yellow-text); }

    /* KYC & Tech Statuses (Merged from Dev) */
    .status-badge.status-verified, .status-badge.status-active, .status-badge.status-successful, .status-badge.status-released-to-creator, .status-badge.status-completed { background-color: var(--status-green-bg); color: var(--status-green-text); }
    .status-badge.status-in-review, .status-badge.status-pending, .status-badge.status-initiated { background-color: var(--status-yellow-bg); color: var(--status-yellow-text); }
    .status-badge.status-failed { background-color: var(--status-red-bg); color: var(--status-red-text); }
    .status-badge.status-in-escrow { background-color: var(--status-blue-bg); color: var(--status-blue-text); }
    .status-badge.status-n-a { background-color: var(--status-gray-bg); color: var(--status-gray-text); }

    .indicator-card {
        background-color: white; border: 1px solid var(--border-color); border-radius: 0.75rem;
        padding: 1.5rem; text-align: center; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.07);
        display: flex; flex-direction: column; justify-content: center; min-height: 110px;
        position: relative; transition: all 0.2s ease-in-out;
    }
    .indicator-card:hover { transform: translateY(-4px); box-shadow: 0 4px 12px 0 rgba(0,0,0,0.08); }
    .indicator-card .label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; }
    .indicator-card .value { font-size: 1.75rem; font-weight: 700; color: var(--text-primary); }

    /* Tooltips */
    .tooltip-icon { position: absolute; top: 0.5rem; right: 0.5rem; cursor: help; }
    .tooltip-text {
        visibility: hidden; width: 220px; background-color: #374151; color: #fff; text-align: center;
        border-radius: 6px; padding: 5px 10px; position: absolute; z-index: 10;
        bottom: 125%; left: 50%; margin-left: -110px; opacity: 0; transition: opacity 0.3s;
        font-size: 0.75rem; font-weight: 400; line-height: 1.4; pointer-events: none;
    }
    .tooltip-icon:hover .tooltip-text { visibility: visible; opacity: 1; }

    /* Header Tooltip (from Dev) */
    .header-tooltip-wrapper {
        position: relative; display: inline-flex; align-items: center; margin-left: 4px;
        z-index: 10; cursor: help; vertical-align: middle;
    }
    .header-tooltip-wrapper:hover .header-tooltip-text { visibility: visible; opacity: 1; }
    .header-tooltip-text {
        visibility: hidden; width: 180px; background-color: #374151; color: #fff;
        text-align: left; border-radius: 6px; padding: 10px;
        position: absolute; z-index: 50; top: 100%; left: 50%;
        transform: translateX(-50%); margin-top: 5px;
        opacity: 0; transition: opacity 0.2s ease-in-out;
        font-size: 0.7rem; font-weight: normal; line-height: 1.5;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        text-transform: none; white-space: normal;
    }
    .header-tooltip-text::after {
        content: ""; position: absolute; bottom: 100%; left: 50%;
        margin-left: -5px; border-width: 5px; border-style: solid;
        border-color: transparent transparent #374151 transparent;
    }

    /* Modals */
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0, 0, 0, 0.4); display: none;
        align-items: center; justify-content: center; z-index: 10000;
        backdrop-filter: blur(4px);
    }
    .modal-overlay.show { display: flex; }
    .modal-content {
        background-color: white; border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); width: 90%; max-width: 600px;
        position: relative;
        /* UPDATED: Flex layout for sticky footer effect */
        display: flex; flex-direction: column;
        max-height: 90vh; 
    }
    
    .modal-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border-color); /* The "Trait" */
        flex-shrink: 0;
        /* Added Flex for Close Button */
        display: flex; justify-content: space-between; align-items: center;
    }
    
    .modal-body {
        padding: 2rem;
        overflow-y: auto;
    }
    
    .modal-footer {
        padding: 1rem 2rem;
        border-top: 1px solid var(--border-color); /* The "Trait" */
        background-color: #f9fafb;
        border-bottom-left-radius: 1rem;
        border-bottom-right-radius: 1rem;
        flex-shrink: 0;
        display: flex; justify-content: flex-end; gap: 0.75rem;
    }

    .modal-content.modal-wide { max-width: 800px; }
    .modal-title { font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--text-primary); }

    .modern-input {
        width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 0.5rem;
        font-size: 0.875rem; margin-top: 0.25rem; transition: border-color 0.2s;
    }
    .modern-input:focus { border-color: var(--tookle-purple); outline: none; ring: 2px var(--tookle-purple-light); }
    .modern-input.error { border-color: var(--status-red-text); }
    .modern-input.success { border-color: var(--status-green-text); }

    .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; }
    .pagination-buttons { display: flex; gap: 0.5rem; }
    .pagination-buttons button { padding: 0.5rem 1rem; border: 1px solid #e5e7eb; border-radius: 0.375rem; background: white; }
    .pagination-buttons button:disabled { opacity: 0.5; cursor: not-allowed; }

    #loading-indicator { pointer-events: none; z-index: 9999; }
    .pe { pointer-events: none; }
    .sortable-header { cursor: pointer; position: relative; }
    .sortable-header .sort-icon { display: inline-block; margin-left: 0.5rem; width: 14px; height: 14px; color: #9ca3af; }
    .hidden { display: none !important; }

    /* CSV Import Steps */
    .import-step { display: none; }
    .import-step.active { display: block; }
    .file-upload-area { border: 2px dashed #d1d5db; border-radius: 1rem; padding: 3rem; text-align: center; cursor: pointer; background: #f9fafb; transition: all 0.2s; }
    .file-upload-area:hover { border-color: var(--tookle-purple); background: var(--tookle-purple-light); }
</style>

<div class="p-8 md:p-12 main-container overflow-y-auto">
    <div id="loading-indicator" class="fixed inset-0 bg-white/80 flex items-center justify-center hidden">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-tookle-purple"></div>
        <span class="ml-3 text-tookle-purple font-semibold">Processing...</span>
    </div>

    <!-- GENERIC ALERT MODAL -->
    <div id="custom-alert-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; padding: 0;">
            <div class="modal-body text-center p-8">
                <h3 id="custom-alert-title" class="text-xl font-bold mb-2">Alert</h3>
                <p id="custom-alert-message" class="text-gray-600 mb-6"></p>
                <button id="custom-alert-ok-button" class="w-full py-2 bg-tookle-purple text-white rounded-md">OK</button>
            </div>
        </div>
    </div>

    <!-- SUCCESS FEEDBACK MODAL (NEW) -->
    <div id="success-action-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 450px; padding: 0;">
            <div class="modal-body text-center p-8">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                    <i data-lucide="check" class="h-10 w-10 text-green-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Success!</h3>
                <p id="success-action-message" class="text-gray-500 mb-6">The action has been completed successfully.</p>
                <button id="success-action-close-button" class="w-full py-3 bg-tookle-purple text-white font-semibold rounded-lg hover:bg-purple-700 transition-colors shadow-sm">
                    Continue
                </button>
            </div>
        </div>
    </div>

    <div id="edit-investor-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Backer</h3>
            </div>
            <div class="modal-body">
                <form id="edit-investor-form" class="space-y-4">
                    <input type="hidden" id="edit-investment-id">
                    <input type="hidden" id="edit-user-id">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase">First Name</label>
                            <input type="text" id="edit-first-name" class="modern-input">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase">Last Name</label>
                            <input type="text" id="edit-last-name" class="modern-input">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase">Contact Email</label>
                        <input type="text" id="edit-contact" class="modern-input" readonly>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase">KYC Status</label>
                            <select id="edit-kyc-status" class="modern-input"></select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase">Distribution Status</label>
                            <select id="edit-distribution-status" class="modern-input"></select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 close-modal-button" data-modal-id="edit-investor-modal">Cancel</button>
                <button id="confirm-edit-investor" type="button" class="px-4 py-2 bg-tookle-purple text-white font-medium rounded-md hover:bg-purple-700">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- UPDATED ADD INVESTOR MODAL -->
    <div id="add-investor-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Backer</h3>
                <button class="text-gray-400 hover:text-gray-600 close-modal-button" data-modal-id="add-investor-modal">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="add-investor-form" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">First Name <span class="text-red-500">*</span></label>
                            <input type="text" id="add-first-name" class="modern-input" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" id="add-last-name" class="modern-input" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Investor Email <span class="text-red-500">*</span></label>
                        <input type="email" id="add-contact" class="modern-input" required placeholder="name@example.com">
                        <p id="email-validation-msg" class="text-xs mt-1 hidden"></p>
                        
                        <p class="text-xs text-gray-500 mt-2 bg-blue-50 p-3 rounded-md border border-blue-100 flex items-start gap-2">
                            <i data-lucide="info" class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5"></i>
                            <span><strong>Important:</strong> Ensure this email is correct. The investor will need it to access their tokens.</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount (USD) <span class="text-red-500">*</span></label>
                            <input type="number" id="add-amount-usd" class="modern-input" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sale Round <span class="text-red-500">*</span></label>
                            <select id="add-sale-name" class="modern-input" required></select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Status <span class="text-red-500">*</span></label>
                            <select id="add-payment-status" class="modern-input" required></select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <select id="add-payment-method" class="modern-input" required>
                                <option value="">-- Select --</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="stablecoin">Stablecoin</option>
                                <option value="fiat">Fiat (Other)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Wallet Address (Optional)</label>
                        <input type="text" id="add-wallet-address" placeholder="0x..." class="modern-input">
                    </div>

                    <input type="hidden" id="add-kyc-status" value="Pending">
                    <input type="hidden" id="add-source" value="Manual Entry">
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="px-5 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors close-modal-button" data-modal-id="add-investor-modal">Cancel</button>
                <button id="confirm-add-investor" type="button" class="px-6 py-2.5 bg-white border border-gray-300 text-black font-bold rounded-lg hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2" style="font-family: 'Montserrat', sans-serif;">
                    <i data-lucide="plus" class="w-4 h-4 text-black"></i> Add Backer
                </button>
            </div>
        </div>
    </div>

    <div id="kyc-widget-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 800px; width: 95%; height: 80vh; display: flex; flex-direction: column; padding: 0;">
            <div class="flex justify-between items-center p-4 border-b border-gray-200">
                <h3 class="modal-title" style="margin:0; font-size: 1.25rem;">KYC </h3>
                <button class="text-gray-400 hover:text-gray-600 close-modal-button" data-modal-id="kyc-widget-modal" title="Close">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="flex-grow bg-gray-50 relative">
                <iframe name="kyc-iframe-target" id="kyc-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
                <form id="kyc-hidden-form" method="POST" action="/sumsub/public/kyc_status_widget.php" target="kyc-iframe-target" style="display:none;">
                    <input type="hidden" name="email" id="kyc-target-email">
                </form>
            </div>
        </div>
    </div>

    <div id="import-csv-modal" class="modal-overlay">
        <div class="modal-content modal-wide" style="display:block;"> <!-- CSV Modal uses its own layout, block override -->
            <button class="absolute top-4 right-4 text-gray-400 close-modal-button" data-modal-id="import-csv-modal"><i data-lucide="x" class="w-5 h-5"></i></button>
            <div class="p-8"> <!-- Manual Padding for content block -->
                <div id="import-step-1" class="import-step active">
                    <h3 class="modal-title mb-6">Import Backers from CSV</h3>
                    <div class="file-upload-area" id="file-upload-dropzone">
                        <i data-lucide="upload-cloud" class="w-12 h-12 text-tookle-purple mx-auto mb-4"></i>
                        <p class="font-medium">Click to upload or drag and drop</p>
                        <p class="text-xs text-gray-400 mt-1">.csv file (max 5MB)</p>
                    </div>
                    <input type="file" id="csv-file-input" accept=".csv" class="hidden">
                    <p id="csv-file-name" class="mt-4 text-center font-medium"></p>
                    <div id="import-step-1-error" class="text-red-500 text-sm mt-2 text-center"></div>
                    <div class="mt-6 flex justify-between">
                        <button id="download-csv-template" class="text-tookle-purple text-sm font-medium flex items-center gap-1"><i data-lucide="download" class="w-4 h-4"></i> Template</button>
                        <button id="upload-csv-button" class="px-6 py-2 bg-tookle-purple text-white rounded-md opacity-50" disabled>Upload & Review</button>
                    </div>
                </div>
                <div id="import-step-2" class="import-step">
                    <h3 class="modal-title mb-4">Review Import</h3>
                    <div id="import-review-summary" class="p-4 rounded-md mb-4"></div>
                    <div class="overflow-y-auto max-h-64 border rounded-md">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr><th class="p-2">Name</th><th class="p-2">Email</th><th class="p-2">Status</th></tr>
                            </thead>
                            <tbody id="import-valid-tbody"></tbody>
                        </table>
                    </div>
                    <div id="import-errors-section" class="mt-4 hidden">
                        <p class="text-red-500 font-bold mb-2">Errors Found:</p>
                        <div class="text-xs text-red-600 bg-red-50 p-2 rounded-md max-h-32 overflow-y-auto" id="import-errors-tbody"></div>
                    </div>
                    <div class="mt-6 flex justify-between">
                        <button id="import-back-button" class="px-4 py-2 border rounded-md">Back</button>
                        <button id="confirm-import-button" class="px-6 py-2 bg-tookle-purple text-white rounded-md">Import Valid Backers</button>
                    </div>
                </div>
                <div id="import-step-3" class="import-step text-center py-8">
                    <div id="import-result-icon-container"></div>
                    <h3 id="import-result-title" class="text-2xl font-bold mt-4"></h3>
                    <p id="import-result-message" class="text-gray-500 mt-2"></p>
                    <button class="mt-8 px-8 py-2 bg-tookle-purple text-white rounded-md close-modal-button" data-modal-id="import-csv-modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Manage Backers</h1>
        <p class="mt-2 text-base text-gray-500">Capitalization table and lifecycle management.</p>
    </header>

    <section class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Financial Overview</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="indicator-card">
                <div class="label">Awaiting Payment</div>
                <div id="total-awaiting-payment" class="value">$0</div>
                <div class="tooltip-icon"><i data-lucide="info" class="w-4 h-4 text-gray-400"></i><span class="tooltip-text">Pending payment confirmation.</span></div>
            </div>
            <div class="indicator-card">
                <div class="label">Payment Secured</div>
                <div id="total-in-escrow" class="value">$0</div>
                <div class="tooltip-icon"><i data-lucide="info" class="w-4 h-4 text-gray-400"></i><span class="tooltip-text">Funds successfully received and held in escrow.</span></div>
            </div>
            <div class="indicator-card">
                <div class="label">Ready for Distribution</div>
                <div id="total-ready-for-distribution" class="value">$0</div>
                <div class="tooltip-icon"><i data-lucide="info" class="w-4 h-4 text-gray-400"></i><span class="tooltip-text">Funds released to creator, ready for distribution setup.</span></div>
            </div>
            <div class="indicator-card">
                <div class="label">Vesting Active</div>
                <div id="total-vesting-active" class="value">$0</div>
                <div class="tooltip-icon"><i data-lucide="info" class="w-4 h-4 text-gray-400"></i><span class="tooltip-text">Streaming tokens via active contracts.</span></div>
            </div>
            <div class="indicator-card">
                <div class="label">Unsuccessful Funds</div>
                <div id="total-unsuccessful-funds" class="value">$0</div>
                <div class="tooltip-icon"><i data-lucide="info" class="w-4 h-4 text-gray-400"></i><span class="tooltip-text">Failed or canceled investment attempts.</span></div>
            </div>
            <div class="indicator-card">
                <div class="label">Funds Returned</div>
                <div id="total-funds-returned" class="value">$0</div>
                <div class="tooltip-icon"><i data-lucide="info" class="w-4 h-4 text-gray-400"></i><span class="tooltip-text">Refunds processed for failed sales.</span></div>
            </div>
        </div>
    </section>

    <div class="bg-white rounded-lg shadow-md border border-gray-100 p-6 mb-8">
         <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <h2 class="text-lg font-semibold text-gray-900">Backer List</h2>
                <select id="sale-name-filter" class="modern-input py-1 px-4 text-sm w-48">
                    <option value="">All Sales</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button id="import-csv-button" class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 flex items-center gap-2 shadow-sm">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i> Import CSV
                </button>
                <button id="add-investor-button" class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 flex items-center gap-2 shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add Backer
                </button>
                <button id="export-csv-button" class="px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 flex items-center gap-2 shadow-sm">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full data-table text-sm">
                <thead>
                    <tr>
                        <th class="text-center">Actions</th>
                        <th class="sortable-header" data-sort-field="fullName">Backer<i data-lucide="chevrons-up-down" class="sort-icon"></i></th>
                        <th class="text-right sortable-header" data-sort-field="amount">Amount ($)<i data-lucide="chevrons-up-down" class="sort-icon"></i></th>

                        <th class="sortable-header" data-sort-field="investment_status">Inv. State<i data-lucide="chevrons-up-down" class="sort-icon"></i></th>
                        <th class="sortable-header" data-sort-field="payment_status">Pay. State<i data-lucide="chevrons-up-down" class="sort-icon"></i></th>

                        <th class="sortable-header" data-sort-field="derived_status">Status<i data-lucide="chevrons-up-down" class="sort-icon"></i></th>
                        <th>Action / Next Step</th>

                        <th class="sortable-header" data-sort-field="kyc_status">
                            KYC Status
                            <span class="header-tooltip-wrapper">
                                <i data-lucide="info" class="w-3 h-3 text-gray-400"></i>
                                <div class="header-tooltip-text">
                                    <div class="mb-1"><span style="color:#4ade80">●</span> <b>Verified:</b> Identity confirmed.</div>
                                    <div class="mb-1"><span style="color:#facc15">●</span> <b>Pending:</b> Under review or incomplete.</div>
                                    <div><span style="color:#f87171">●</span> <b>Failed:</b> Rejected or error.</div>
                                </div>
                            </span>
                            <i data-lucide="chevrons-up-down" class="sort-icon"></i>
                        </th>

                        <th class="sortable-header" data-sort-field="distribution_status">Distribution<i data-lucide="chevrons-up-down" class="sort-icon"></i></th>
                        <th class="text-right">Tokens</th>
                    </tr>
                </thead>
                <tbody id="investor-pipeline-tbody">
                     <tr><td colspan="10" class="text-center py-8 text-gray-400">Loading backer list...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="pagination-container" class="pagination-container hidden">
            <div id="pagination-info" class="text-sm text-gray-500"></div>
            <div class="pagination-buttons">
                <button id="pagination-prev" disabled><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
                <button id="pagination-next"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
            </div>
        </div>
    </div>

    <section class="mb-8">
        <div id="definitions-toggle" class="flex items-center justify-between cursor-pointer p-4 bg-white rounded-lg shadow-sm border border-gray-100">
            <h2 class="text-xl font-semibold text-gray-900">Legend: Operational Status Definitions</h2>
            <i data-lucide="chevron-down" id="definitions-chevron" class="transition-transform"></i>
        </div>
        <div id="definitions-content" class="mt-4 text-sm bg-white p-6 rounded-lg shadow-md border border-gray-100 hidden">
             <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-6">
                <div>
                    <h3 class="font-semibold text-gray-800 flex items-center"><span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--status-yellow-text);"></span>Awaiting Payment</h3>
                    <p class="text-gray-600 pl-5 mt-1"><span class="font-medium text-gray-800">Action:</span> Monitor. System is waiting for payment confirmation from the investor.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 flex items-center"><span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--status-red-text);"></span>Payment Failed</h3>
                    <p class="text-gray-600 pl-5 mt-1"><span class="font-medium text-gray-800">Action:</span> Contact investor to resolve payment issue or suggest a new contribution.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 flex items-center"><span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--status-blue-text);"></span>Payment Secured</h3>
                    <p class="text-gray-600 pl-5 mt-1"><span class="font-medium text-gray-800">Action:</span> Go to Smart Vault to claim funds then go to Distribution page.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 flex items-center"><span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--tookle-purple);"></span>Ready for Distribution</h3>
                    <p class="text-gray-600 pl-5 mt-1"><span class="font-medium text-gray-800">Action:</span> Go to the Distribution page to create the vesting schedule for this investment.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 flex items-center"><span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--status-green-text);"></span>Vesting Active</h3>
                    <p class="text-gray-600 pl-5 mt-1"><span class="font-medium text-gray-800">Action:</span> Monitor. The vesting contract is active and streaming tokens.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 flex items-center"><span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--status-gray-text);"></span>Canceled</h3>
                    <p class="text-gray-600 pl-5 mt-1"><span class="font-medium text-gray-800">Action:</span> None. The investment was canceled (final state).</p>
                </div>
            </div>
        </div>
    </section>

    <section class="legal-disclaimer p-6 bg-gray-50 border rounded-lg mt-8">
        <h3 class="text-lg font-bold flex items-center gap-2"><i data-lucide="shield" class="w-5 h-5"></i> Compliance & Responsibility</h3>
        <p class="text-sm text-gray-500 mt-2">
            Tookle is a non-custodial software toolkit. Project Founders are entirely responsible for all regulatory compliance, KYC/AML, and adherence to securities laws.
        </p>
    </section>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    const currentProjectId = "<?php echo $current_project_id; ?>";
    let projectData = { investor_pipeline_data: [], sale_pages: [] };

    // API Paths
    const API_URL_FETCH = '/backend/investors_backend.php';
    const API_URL_UPDATE = '/backend/investors_backend.php';
    const API_URL_CSV_HANDLER = '/backend/investors_csv_handler.php';

    // State Variables
    let currentSortColumn = 'fullName';
    let currentSortDirection = 'asc';
    let currentPage = 1;
    const ROWS_PER_PAGE = 100;
    let csvFile = null;
    let validCsvImportData = [];
    let isEmailValid = false;

    // Constants
    const KYC_OPTIONS = ["Pending", "Verified", "Failed", "In Review"];
    const DIST_OPTIONS = ["Pending", "Active", "Revoked", "Completed", "N/A"];

    // DOM Elements
    let investorTbody, loadingIndicator, saleNameFilter, paginationContainer, paginationInfo, paginationPrev, paginationNext, editInvestorModal, addInvestorModal;
    
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        investorTbody = document.getElementById('investor-pipeline-tbody');
        loadingIndicator = document.getElementById('loading-indicator');
        saleNameFilter = document.getElementById('sale-name-filter');
        paginationContainer = document.getElementById('pagination-container');
        paginationInfo = document.getElementById('pagination-info');
        paginationPrev = document.getElementById('pagination-prev');
        paginationNext = document.getElementById('pagination-next');
        editInvestorModal = document.getElementById('edit-investor-modal');
        addInvestorModal = document.getElementById('add-investor-modal');

        if (currentProjectId) {
            fetchCapTableData(currentProjectId);
        }
        setupEventListeners();
    });

    const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(parseFloat(val || 0));

    const getStatusBadgeClass = (status) => {
        if (!status) return 'status-under-review';
        return `status-${status.toLowerCase().replace(/\s+/g, '-')}`;
    };

    // --- NOUVEAU : Logique KYC Avancée (from Dev) ---
    const getDynamicStatusBadgeClass = (status) => {
        const s = String(status || 'n-a').toLowerCase();
        // GREEN / Verified
        if (s.includes('green') || s.includes('completed') || s.includes('verified') || s === 'active' || s === 'successful') {
            return 'status-verified';
        }
        // RED / Rejected
        if (s.includes('red') || s.includes('rejected') || s.includes('final-reject') || s === 'failed') {
            return 'status-failed';
        }
        // Init / Pending / Review
        if (s.includes('init') || s.includes('pending') || s.includes('queued') || s.includes('review') || s === 'retry') {
            return 'status-pending';
        }
        // Autres cas
        if (s === 'in-escrow') return 'status-in_escrow';
        if (s === 'returned-to-backer') return 'status-returned_to_backer';
        return 'status-n-a';
    };

    // NOUVEAU : Formatage du Label KYC
    const formatKycLabel = (status) => {
        if (!status) return 'N/A';
        const s = String(status).toLowerCase();
        if (s.includes('green') || s === 'completed' || s === 'verified') return 'Verified';
        if (s.includes('red') || s === 'rejected' || s === 'final-reject') return 'Rejected';
        if (s === 'init' || s === 'pending' || s === 'queued' || s.includes('review')) return 'Pending';
        if (s === 'retry') return 'Retry Needed';
        return status.charAt(0).toUpperCase() + status.slice(1);
    };
    // ----------------------------------------------------

    // Keep old getDynamicBadgeClass for backward compat in other cols if needed
    const getDynamicBadgeClass = getDynamicStatusBadgeClass;

    const getActionText = (status) => {
        switch (status) {
            case 'Awaiting Payment': return 'Waiting for payment confirmation.';
            case 'Payment Failed': return 'Contact backer for resolution.';
            case 'Payment Secured': return 'Funds secured.';
            case 'Ready for Distribution': return 'Action: Setup vesting schedule.';
            case 'Vesting Active': return 'Vesting active and streaming.';
            case 'Refunding': return 'Refund in progress.';
            case 'Refunded': return 'Contribution fully refunded.';
            case 'Canceled': return 'Contribution canceled.';
            default: return 'Review required.';
        }
    };

    async function fetchCapTableData(pid) {
        if (loadingIndicator) loadingIndicator.classList.remove('hidden');
        try {
            const res = await fetch(`${API_URL_FETCH}?pid=${pid}&_=${Date.now()}`);
            const result = await res.json();
            if (!result.success) throw new Error(result.message);

            projectData.investor_pipeline_data = result.data.allocations || [];
            projectData.sale_pages = result.data.sale_pages || [];

            populateSaleFilter();
            updateMetrics();
            renderTable();
			
			// --- APPEL ASYNCHRONE BLOCKCHAIN WATCHER ---
            // On lance la vérification en arrière-plan sans ralentir la page
            fetch('/backend/trigger_blockchain_check.php')
                .then(r => r.json())
                .then(data => {
                    console.log("Blockchain Watcher:", data);
                    // Si une transaction a été validée (succès), on recharge le tableau discrètement
                    // pour afficher le nouveau statut "Payment Secured"
                    if (data.success) {
                        // Optionnel : On peut relancer un fetchCapTableData après 2-3 secondes
                        // fetchCapTableData(pid); 
                    }
                })
                .catch(err => console.warn("Background Check Error:", err));
            // -------------------------------------------
			
        } catch (e) {
            console.error(e);
            if (investorTbody) investorTbody.innerHTML = `<tr><td colspan="10" class="text-center text-red-500 p-4">Error: ${e.message}</td></tr>`;
        } finally {
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
        }
    }

    function populateSaleFilter() {
        if (!saleNameFilter) return;
        saleNameFilter.innerHTML = '<option value="">All Sales</option>';
        const names = [...new Set(projectData.sale_pages.map(s => s.sale_name))];
        names.forEach(n => saleNameFilter.add(new Option(n, n)));
    }

    function updateMetrics() {
        let totals = { awaiting: 0, secured: 0, ready: 0, active: 0, failed: 0, returned: 0 };
        projectData.investor_pipeline_data.forEach(inv => {
            const val = parseFloat(inv.amount || 0);
            switch (inv.derived_status) {
                case 'Awaiting Payment': totals.awaiting += val; break;
                case 'Payment Secured': totals.secured += val; break;
                case 'Ready for Distribution': totals.ready += val; break;
                case 'Vesting Active': totals.active += val; break;
                case 'Payment Failed': case 'Canceled': case 'Vesting Canceled': totals.failed += val; break;
                case 'Refunding': case 'Refunded': totals.returned += val; break;
            }
        });

        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = formatCurrency(val); };
        set('total-awaiting-payment', totals.awaiting);
        set('total-in-escrow', totals.secured);
        set('total-ready-for-distribution', totals.ready);
        set('total-vesting-active', totals.active);
        set('total-unsuccessful-funds', totals.failed);
        set('total-funds-returned', totals.returned);
    }

    function renderTable() {
        if (!investorTbody) return;
        investorTbody.innerHTML = '';

        const selected = saleNameFilter ? saleNameFilter.value : "";
        const filtered = projectData.investor_pipeline_data.filter(inv => !selected || inv.saleName === selected);

        const sorted = [...filtered].sort((a, b) => {
            let vA = a[currentSortColumn], vB = b[currentSortColumn];
            if (currentSortColumn === 'fullName') {
                vA = `${a.first_name} ${a.last_name}`.toLowerCase();
                vB = `${b.first_name} ${b.last_name}`.toLowerCase();
            } else if (['amount', 'token_quantity'].includes(currentSortColumn)) {
                vA = parseFloat(vA || 0); vB = parseFloat(vB || 0);
                return currentSortDirection === 'asc' ? vA - vB : vB - vA;
            }
            return currentSortDirection === 'asc' ? String(vA).localeCompare(String(vB)) : String(vB).localeCompare(String(vA));
        });

        const start = (currentPage - 1) * ROWS_PER_PAGE;
        const pageItems = sorted.slice(start, start + ROWS_PER_PAGE);

        pageItems.forEach(inv => {
            const tr = document.createElement('tr');
            tr.className = "border-b";
            tr.dataset.investorId = inv.investment_id;
            const fullName = `${inv.first_name || ''} ${inv.last_name || ''}`.trim();
            const distStatus = inv.distribution_status || 'N/A';

            // --- NOUVEAU : Bouton Loupe (from Dev) ---
            const kycButtonHtml = inv.contact ?
                `<button type="button" class="view-kyc-button text-gray-500 hover:text-blue-600" data-email="${inv.contact}" title="Voir KYC / AML" style="margin-left:6px;">🔍</button>`
                : '';
            const prettyKycLabel = formatKycLabel(inv.kyc_status);

            // --- NOUVEAU : Récupération statuts techniques ---
            const invState = inv.investment_status || 'N/A';
            const payState = inv.payment_status || 'N/A';
            // ------------------------------------------

            tr.innerHTML = `
                <td class="text-center"><button class="text-gray-400 hover:text-purple-600 edit-btn"><i data-lucide="edit" class="w-4 h-4 pe"></i></button></td>
                <td><div class="font-bold">${fullName}</div><div class="text-xs text-gray-400">${inv.contact}</div></td>
                <td class="text-right font-mono">${formatCurrency(inv.amount)}</td>

                <td><span class="status-badge ${getDynamicBadgeClass(invState)}">${invState}</span></td>
                <td><span class="status-badge ${getDynamicBadgeClass(payState)}">${payState}</span></td>
                <td><span class="status-badge ${getStatusBadgeClass(inv.derived_status)}">${inv.derived_status}</span></td>
                <td class="text-gray-500 italic text-xs">${getActionText(inv.derived_status)}</td>

                <td>
                    <div class="flex items-center gap-1">
                       <span class="status-badge ${getDynamicStatusBadgeClass(inv.kyc_status)}">${prettyKycLabel}</span>
                       ${kycButtonHtml}
                    </div>
                </td>

                <td><span class="status-badge ${getDynamicBadgeClass(distStatus)}">${distStatus}</span></td>
                <td class="text-right font-mono">${parseFloat(inv.token_quantity || 0).toLocaleString()}</td>
            `;
            investorTbody.appendChild(tr);
        });

        if (paginationContainer) {
            paginationContainer.classList.toggle('hidden', sorted.length === 0);
            if (paginationInfo) paginationInfo.textContent = `Showing ${start + 1} to ${Math.min(start + ROWS_PER_PAGE, sorted.length)} of ${sorted.length}`;
            if (paginationPrev) paginationPrev.disabled = currentPage === 1;
            if (paginationNext) paginationNext.disabled = start + ROWS_PER_PAGE >= sorted.length;
        }
        lucide.createIcons();
    }

    // Modal helpers
    const openEditModal = (investor) => {
        document.getElementById('edit-investment-id').value = investor.investment_id;
        document.getElementById('edit-user-id').value = investor.user_id;
        document.getElementById('edit-first-name').value = investor.first_name;
        document.getElementById('edit-last-name').value = investor.last_name;
        document.getElementById('edit-contact').value = investor.contact;

        const kycSelect = document.getElementById('edit-kyc-status');
        kycSelect.innerHTML = '';
        KYC_OPTIONS.forEach(opt => kycSelect.add(new Option(opt, opt, false, opt === (investor.kyc_status || "Pending"))));

        const distSelect = document.getElementById('edit-distribution-status');
        distSelect.innerHTML = '';
        DIST_OPTIONS.forEach(opt => distSelect.add(new Option(opt, opt, false, opt === (investor.distribution_status || "N/A"))));

        editInvestorModal.classList.add('show');
    };

    const openAddModal = () => {
        document.getElementById('add-investor-form').reset();
        
        // Reset Email Validation UI
        const emailEl = document.getElementById('add-contact');
        const msgEl = document.getElementById('email-validation-msg');
        emailEl.classList.remove('error', 'success');
        msgEl.classList.add('hidden');
        isEmailValid = false;

        const saleSelect = document.getElementById('add-sale-name');
        saleSelect.innerHTML = '<option value="">-- Select a Sale --</option>';
        projectData.sale_pages.forEach(s => saleSelect.add(new Option(s.sale_name, s.sale_name)));

        const payStatus = document.getElementById('add-payment-status');
        payStatus.innerHTML = '';
        ["successful", "pending", "failed"].forEach(s => payStatus.add(new Option(s, s)));

        addInvestorModal.classList.add('show');
    };

    async function saveInvestorUpdate() {
        const data = {
            investor: {
                investment_id: document.getElementById('edit-investment-id').value,
                user_id: document.getElementById('edit-user-id').value,
                firstName: document.getElementById('edit-first-name').value,
                lastName: document.getElementById('edit-last-name').value,
                kyc_status: document.getElementById('edit-kyc-status').value,
                distribution_status: document.getElementById('edit-distribution-status').value
            }
        };

        loadingIndicator.classList.remove('hidden');
        try {
            const res = await fetch(API_URL_UPDATE, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                editInvestorModal.classList.remove('show');
                fetchCapTableData(currentProjectId);
            } else throw new Error(result.message);
        } catch (e) { alert(e.message); }
        finally { loadingIndicator.classList.add('hidden'); }
    }

    async function saveNewInvestor() {
        // --- UPDATED VALIDATION LOGIC ---
        const requiredIds = ['add-first-name', 'add-last-name', 'add-contact', 'add-amount-usd', 'add-sale-name', 'add-payment-status', 'add-payment-method'];
        let isValid = true;
        let firstError = null;

        requiredIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el.value.trim()) {
                el.style.borderColor = '#ef4444'; // Red
                isValid = false;
                if(!firstError) firstError = el;
            } else {
                el.style.borderColor = '#d1d5db'; // Gray
            }
        });

        // Use the realtime flag if possible, but double check just in case
        const emailEl = document.getElementById('add-contact');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailEl.value.trim() && !emailRegex.test(emailEl.value.trim())) {
             emailEl.classList.add('error');
             alert("Please enter a valid email address.");
             return;
        }

        if (!isValid) {
            alert("Please fill in all mandatory fields marked with *.");
            if(firstError) firstError.focus();
            return;
        }

        const data = {
            newInvestor: {
                firstName: document.getElementById('add-first-name').value.trim(),
                lastName: document.getElementById('add-last-name').value.trim(),
                contact: document.getElementById('add-contact').value.trim(),
                amount_usd: document.getElementById('add-amount-usd').value,
                sale_name: document.getElementById('add-sale-name').value,
                payment_status: document.getElementById('add-payment-status').value,
                payment_method: document.getElementById('add-payment-method').value,
                wallet_address: document.getElementById('add-wallet-address').value.trim(),
                kyc_status: "Pending",
                source: "Manual Entry"
            }
        };

        loadingIndicator.classList.remove('hidden');
        try {
            const res = await fetch(API_URL_UPDATE, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                addInvestorModal.classList.remove('show');
                // SHOW SUCCESS MODAL
                document.getElementById('success-action-modal').classList.add('show');
                fetchCapTableData(currentProjectId);
            } else throw new Error(result.message);
        } catch (e) { alert(e.message); }
        finally { loadingIndicator.classList.add('hidden'); }
    }
    
    // Real-time Email Validation Logic
    function validateEmailRealtime(e) {
        const input = e.target;
        const msg = document.getElementById('email-validation-msg');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const val = input.value.trim();

        if (val === "") {
            input.classList.remove('error', 'success');
            msg.classList.add('hidden');
            isEmailValid = false;
            return;
        }

        if (emailRegex.test(val)) {
            input.classList.remove('error');
            input.classList.add('success');
            msg.textContent = "Valid email format.";
            msg.className = "text-xs mt-1 text-green-600";
            msg.classList.remove('hidden');
            isEmailValid = true;
        } else {
            input.classList.remove('success');
            input.classList.add('error');
            msg.textContent = "Invalid email format. Missing '@' or domain.";
            msg.className = "text-xs mt-1 text-red-500";
            msg.classList.remove('hidden');
            isEmailValid = false;
        }
    }

    function setupEventListeners() {
        // Toggle Legend
        const defToggle = document.getElementById('definitions-toggle');
        const defContent = document.getElementById('definitions-content');
        if (defToggle) defToggle.addEventListener('click', () => defContent.classList.toggle('hidden'));
        
        // Add Real-time validation listener
        const addEmailInput = document.getElementById('add-contact');
        if (addEmailInput) {
            addEmailInput.addEventListener('input', validateEmailRealtime);
        }

        // Row Action (Edit) AND KYC Widget
        investorTbody.addEventListener('click', (e) => {
            // Edit
            const btn = e.target.closest('.edit-btn');
            if (btn) {
                const tr = btn.closest('tr');
                const id = tr.dataset.investorId;
                const inv = projectData.investor_pipeline_data.find(x => x.investment_id == id);
                if (inv) openEditModal(inv);
            }

            // NOUVEAU : Bouton Loupe (Ouvrir Widget KYC)
            const kycBtn = e.target.closest('.view-kyc-button');
            if (kycBtn) {
                const email = kycBtn.dataset.email;
                const kycModal = document.getElementById('kyc-widget-modal');
                const hiddenForm = document.getElementById('kyc-hidden-form');
                const emailInput = document.getElementById('kyc-target-email');
                const iframe = document.getElementById('kyc-iframe');

                if (email && kycModal && hiddenForm) {
                    kycModal.classList.add('show'); // Use 'show' class like other modals
                    iframe.src = 'about:blank';
                    emailInput.value = email;
                    hiddenForm.submit();
                }
            }
        });

        // Add Backer
        const addBtn = document.getElementById('add-investor-button');
        if (addBtn) addBtn.addEventListener('click', openAddModal);
        document.getElementById('confirm-add-investor').addEventListener('click', saveNewInvestor);
        document.getElementById('confirm-edit-investor').addEventListener('click', saveInvestorUpdate);

        // Filter & Sort
        if (saleNameFilter) saleNameFilter.addEventListener('change', () => { currentPage = 1; renderTable(); });
        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sortField;
                if (currentSortColumn === f) currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
                else { currentSortColumn = f; currentSortDirection = 'asc'; }
                renderTable();
            });
        });

        // Pagination
        if (paginationPrev) paginationPrev.addEventListener('click', () => { currentPage--; renderTable(); });
        if (paginationNext) paginationNext.addEventListener('click', () => { currentPage++; renderTable(); });

        // Modals Close logic
        document.querySelectorAll('.close-modal-button').forEach(b => {
            b.addEventListener('click', () => {
                const m = document.getElementById(b.dataset.modalId);
                if (m) m.classList.remove('show');
            });
        });

        // Generic Alert / Success Close logic
        const closeAlert = document.getElementById('custom-alert-ok-button');
        if (closeAlert) closeAlert.addEventListener('click', () => document.getElementById('custom-alert-modal').classList.remove('show'));
        
        const closeSuccess = document.getElementById('success-action-close-button');
        if (closeSuccess) closeSuccess.addEventListener('click', () => document.getElementById('success-action-modal').classList.remove('show'));


        // Fermeture spécifique KYC modal si on clique dehors
        const kycModal = document.getElementById('kyc-widget-modal');
        if (kycModal) {
            kycModal.addEventListener('click', (e) => {
                if (e.target === kycModal) kycModal.classList.remove('show');
            });
        }

        // CSV Export
        const expBtn = document.getElementById('export-csv-button');
        if (expBtn) expBtn.addEventListener('click', exportToCSV);

        // CSV Import
        const impBtn = document.getElementById('import-csv-button');
        const impModal = document.getElementById('import-csv-modal');
        if (impBtn) impBtn.addEventListener('click', () => impModal.classList.add('show'));

        const drop = document.getElementById('file-upload-dropzone');
        const fileIn = document.getElementById('csv-file-input');
        if (drop) {
            drop.addEventListener('click', () => fileIn.click());
            drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('bg-purple-50'); });
            drop.addEventListener('dragleave', () => drop.classList.remove('bg-purple-50'));
            drop.addEventListener('drop', (e) => {
                e.preventDefault();
                drop.classList.remove('bg-purple-50');
                if (e.dataTransfer.files.length) handleFileSelect(e.dataTransfer.files[0]);
            });
        }
        if (fileIn) fileIn.addEventListener('change', () => handleFileSelect(fileIn.files[0]));

        document.getElementById('upload-csv-button').addEventListener('click', handleCsvUpload);
        document.getElementById('import-back-button').addEventListener('click', () => setStep(1));
        document.getElementById('confirm-import-button').addEventListener('click', handleCsvImport);
    }

    function setStep(s) {
        document.querySelectorAll('.import-step').forEach(el => el.classList.remove('active'));
        document.getElementById(`import-step-${s}`).classList.add('active');
    }

    function handleFileSelect(file) {
        if (!file.name.endsWith('.csv')) return;
        csvFile = file;
        document.getElementById('csv-file-name').textContent = file.name;
        const upBtn = document.getElementById('upload-csv-button');
        upBtn.disabled = false;
        upBtn.classList.remove('opacity-50');
    }

    async function handleCsvUpload() {
        const formData = new FormData();
        formData.append('csv_file', csvFile);
        formData.append('action', 'review_csv');

        loadingIndicator.classList.remove('hidden');
        try {
            const res = await fetch(API_URL_CSV_HANDLER, { method: 'POST', body: formData });
            const result = await res.json();
            if (!result.success) throw new Error(result.message);

            validCsvImportData = result.data.validRows;
            const summary = document.getElementById('import-review-summary');
            summary.className = result.data.summary.invalid_count > 0 ? "bg-yellow-50 text-yellow-700 p-4" : "bg-green-50 text-green-700 p-4";
            summary.textContent = `Found ${result.data.summary.total_rows} rows. ${result.data.summary.valid_count} valid, ${result.data.summary.invalid_count} errors.`;

            const tbody = document.getElementById('import-valid-tbody');
            tbody.innerHTML = validCsvImportData.map(r => `<tr><td class="p-2">${r.firstName} ${r.lastName}</td><td class="p-2">${r.contact}</td><td class="p-2">${r.payment_status}</td></tr>`).join('');

            const errSection = document.getElementById('import-errors-section');
            if (result.data.summary.invalid_count > 0) {
                errSection.classList.remove('hidden');
                document.getElementById('import-errors-tbody').innerHTML = result.data.invalidRows.map(r => `<div>Row ${r.row_number}: ${r.errors.join(', ')}</div>`).join('');
            } else {
                errSection.classList.add('hidden');
            }

            setStep(2);
        } catch (e) { alert(e.message); }
        finally { loadingIndicator.classList.add('hidden'); }
    }

    async function handleCsvImport() {
        loadingIndicator.classList.remove('hidden');
        try {
            const res = await fetch(API_URL_UPDATE, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'batch_import', investors: validCsvImportData })
            });
            const result = await res.json();

            const icon = document.getElementById('import-result-icon-container');
            icon.innerHTML = result.success ? '<i data-lucide="check-circle" class="w-16 h-16 text-green-500 mx-auto"></i>' : '<i data-lucide="x-circle" class="w-16 h-16 text-red-500 mx-auto"></i>';
            document.getElementById('import-result-title').textContent = result.success ? "Success" : "Failed";
            document.getElementById('import-result-message').textContent = result.message;
            setStep(3);
            lucide.createIcons();
            fetchCapTableData(currentProjectId);
        } catch (e) { console.error(e); }
        finally { loadingIndicator.classList.add('hidden'); }
    }

    function exportToCSV() {
        const investors = projectData.investor_pipeline_data;
        if (!investors || investors.length === 0) return;

        const headers = ["Full Name", "Email", "Amount USD", "Status", "KYC Status", "Distribution Status", "Tokens Allocated"];
        const rows = investors.map(inv => [
            `"${inv.first_name} ${inv.last_name}"`,
            inv.contact,
            inv.amount,
            inv.derived_status,
            inv.kyc_status,
            inv.distribution_status,
            inv.token_quantity
        ].join(','));

        const csvContent = [headers.join(','), ...rows].join('\n');
        const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `backer_export_${currentProjectId}.csv`;
        link.click();
    }
</script>