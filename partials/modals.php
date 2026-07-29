<!-- UPDATED: View Details Modal (Read-Only, Modern UI) -->
<!-- FIXED: Added 'modal-center' class for centering -->
<div id="sale-details-modal" class="modal-overlay modal-center" style="display: none;">
    <!-- FIXED: Added 'padding: 0' to style to prevent double padding from the modal-center class -->
    <div class="modal-content overflow-hidden" style="max-width: 30rem; border-radius: 16px; padding: 0;">
        
        <!-- Header: Identity & Status -->
        <div class="modal-header bg-gray-50/80 backdrop-blur-sm border-b border-gray-100 px-6 py-5">
            <div class="flex justify-between items-start">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <img id="details-modal-logo" src="" alt="Logo" class="w-14 h-14 rounded-xl object-contain bg-white border border-gray-100 shadow-sm p-1">
                        <div id="details-modal-network-icon" class="absolute -bottom-1 -right-1 bg-blue-600 text-white rounded-full p-0.5 border-2 border-white">
                            <!-- Network icon can go here -->
                        </div>
                    </div>
                    <div>
                        <h3 id="details-modal-title" class="text-lg font-bold text-gray-900 leading-tight"></h3>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span id="details-modal-round" class="text-xs font-semibold text-gray-500 uppercase tracking-wide"></span>
                            <span id="details-modal-status-badge" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600">
                                Draft
                            </span>
                        </div>
                    </div>
                </div>
                <button id="details-close-btn-top" class="modal-close-btn -mr-2 text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-100 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <!-- Body: Information Architecture -->
        <div class="modal-body px-6 py-6 space-y-6 bg-white">
            
            <!-- 1. Primary Metrics (Visual Highlight) -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-indigo-50/50 rounded-xl p-4 border border-indigo-50/50">
                    <div class="flex items-center gap-2 mb-1">
                        <i data-lucide="banknote" class="w-4 h-4 text-indigo-400"></i>
                        <span class="text-xs font-semibold text-indigo-900/60 uppercase tracking-wider">Raised</span>
                    </div>
                    <div id="details-modal-raised" class="text-xl font-bold text-indigo-900 tabular-nums"></div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                    <div class="flex items-center gap-2 mb-1">
                        <i data-lucide="users" class="w-4 h-4 text-gray-400"></i>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Backers</span>
                    </div>
                    <div id="details-modal-investors" class="text-xl font-bold text-gray-900 tabular-nums"></div>
                </div>
            </div>

            <!-- 2. Campaign Data Grid (Clean Hierarchy) -->
            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Campaign Data</h4>
                <div class="grid grid-cols-2 gap-y-5 gap-x-8">
                    <!-- Row 1 -->
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Soft Cap</div>
                        <div id="details-modal-soft" class="text-sm font-semibold text-gray-900 tabular-nums"></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Hard Cap</div>
                        <div id="details-modal-hard" class="text-sm font-semibold text-gray-900 tabular-nums"></div>
                    </div>
                    <!-- Row 2 -->
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Platform</div>
                        <div id="details-modal-platform" class="text-sm font-semibold text-gray-900"></div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Terms</div>
                        <div id="details-modal-terms" class="text-sm font-semibold text-gray-900 truncate" title=""></div>
                    </div>
                    <!-- Row 3 -->
                    <div class="col-span-2 grid grid-cols-2 gap-8 border-t border-gray-50 pt-4 mt-1">
                        <div>
                            <div class="text-xs text-gray-500 mb-1 flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i> Start Date</div>
                            <div id="details-modal-start" class="text-sm font-semibold text-gray-900"></div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 mb-1 flex items-center gap-1"><i data-lucide="calendar-clock" class="w-3 h-3"></i> End Date</div>
                            <div id="details-modal-end" class="text-sm font-semibold text-gray-900"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Links & Assets (Merged Section) -->
            <div id="details-links-section">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Links & Contracts</h4>
                <div class="space-y-2">
                    <!-- Sale Page Link -->
                    <a id="details-modal-link-url" href="#" target="_blank" class="group flex items-center justify-between p-3 bg-white border border-gray-200 rounded-xl hover:border-indigo-300 hover:shadow-sm transition-all text-decoration-none">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="link" class="w-4 h-4"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-semibold text-gray-900">Sale Page</span>
                                <span class="text-xs text-gray-400 truncate max-w-[180px] group-hover:text-blue-500 transition-colors">View public page</span>
                            </div>
                        </div>
                        <i data-lucide="external-link" class="w-4 h-4 text-gray-300 group-hover:text-blue-500"></i>
                    </a>

                    <!-- Vault Contract -->
                    <div id="details-vault-row" class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-xl" style="display: none;">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-sm font-semibold text-gray-900">Smart Vault</span>
                                <span id="details-modal-vault-address" class="text-xs text-gray-400 font-mono truncate max-w-[150px]"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                             <a id="details-modal-vault-link" href="#" target="_blank" class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="View on Explorer">
                                <i data-lucide="external-link" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
            <div class="flex gap-2">
                <!-- Action buttons (Stop/Release) injected here by JS -->
                <button type="button" id="details-stop-btn" class="btn btn-destructive-secondary text-xs font-medium py-2" style="display: none;">
                    Stop Sale
                </button>
                <button type="button" id="details-release-btn" class="btn btn-primary text-xs font-medium py-2" style="display: none;">
                    Release Funds
                </button>
            </div>
            <button type="button" id="details-close-btn-footer" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900 hover:bg-gray-200/50 rounded-lg transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- NEW: Release Funds Modal (Kept as is) -->
<div id="release-funds-modal" class="modal-overlay modal-center" style="display: none;">
    <div class="modal-content relative">
        <button id="release-funds-close-button" class="modal-close-btn"><i data-lucide="x" class="w-6 h-6"></i></button>
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100"><i data-lucide="coins" class="h-6 w-6 text-blue-600"></i></div>
            <h3 class="mt-4 text-xl font-bold text-gray-800">Confirm Fund Release</h3>
            <p class="mt-2 text-sm text-gray-500">Are you sure you want to release the funds for this sale? This will mark all escrowed investments as ready for distribution to the project creator.</p>
        </div>
        <div id="release-funds-error-container" class="mt-4"></div>
        <div class="mt-8 flex justify-center gap-4">
            <button type="button" id="release-funds-cancel-button" class="btn btn-secondary w-full">Cancel</button>
            <button type="button" id="release-funds-confirm-button" class="btn btn-primary w-full">Yes, Release Funds</button>
        </div>
    </div>
</div>

<!-- NEW: View Sale Modal (Public Share - Kept as is) -->
<div id="view-sale-modal" class="modal-overlay modal-center" style="display: none;">
    <div class="modal-content" style="max-width: 28rem;">
            <button id="view-modal-close-button" class="modal-close-btn">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <div class="modal-body-overview">
                <div class="view-logo-container">
                    <img id="view-modal-logo" src="" alt="Project Logo" class="w-16 h-16 rounded-lg object-contain">
                </div>
                <div class="text-center mt-4">
                    <h3 id="view-modal-title" class="text-xl font-bold text-gray-900"></h3>
                    <p id="view-modal-subtitle" class="mt-1 text-base text-gray-500"></p>
                </div>
                <p class="view-discreet-text">
                    Share your private link to let supporters request early access.
                </p>
                 <div class="mt-5 flex justify-center space-x-3">
                    <a id="view-twitter-share-link" href="#" target="_blank" class="social-btn twitter-btn social-btn-minimal">
                        <svg class="social-btn-icon w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a id="view-facebook-share-link" href="#" target="_blank" class="social-btn facebook-btn social-btn-minimal">
                        <svg class="social-btn-icon w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd"/></svg>
                    </a>
                    <a id="view-linkedin-share-link" href="#" target="_blank" class="social-btn linkedin-btn social-btn-minimal">
                        <svg class="social-btn-icon w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" clip-rule="evenodd"/></svg>
                    </a>
                </div>
                <button type="button" id="view-copy-link-button" class="btn btn-primary w-full mt-6">
                    <i data-lucide="link" class="w-4 h-4 mr-2"></i>
                    <span id="view-copy-link-text">Copy Link</span>
                </button>
            </div>
        </div>
    </div>

<div id="stop-sale-modal" class="modal-overlay modal-center" style="display: none;">
    <div class="modal-content relative">
        <button id="stop-sale-close-button" class="modal-close-btn"><i data-lucide="x" class="w-6 h-6"></i></button>
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100"><i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i></div>
            <h3 class="mt-4 text-xl font-bold text-gray-800">Are you sure you want to stop this sale?</h3>
            <p class="mt-2 text-sm text-gray-500">This will end the sale immediately. The final status (Successful or Failed) will be determined by the soft cap. This action cannot be undone.</p>
        </div>
        <div id="stop-sale-error-container" class="mt-4"></div>
        <div class="mt-8 flex justify-center gap-4">
            <button type="button" id="stop-sale-cancel-button" class="btn btn-secondary w-full">Cancel</button>
            <button type="button" id="stop-sale-confirm-button" class="btn bg-red-600 text-white hover:bg-red-700 w-full">Yes, Stop Sale</button>
        </div>
    </div>
</div>

<div id="stop-external-sale-modal" class="modal-overlay modal-center" style="display: none;">
    <div class="modal-content relative" style="max-width: 32rem;">
        <button id="stop-external-close-button" class="modal-close-btn"><i data-lucide="x" class="w-6 h-6"></i></button>
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                <i data-lucide="help-circle" class="h-6 w-6 text-blue-600"></i>
            </div>
            <h3 class="mt-4 text-xl font-bold text-gray-800">End External Sale</h3>
            <p class="mt-2 text-sm text-gray-500">Since this sale is hosted externally, we cannot automatically determine the result. Please select the final outcome to update your dashboard.</p>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-4">
            <button type="button" id="stop-external-fail-btn" class="btn btn-secondary w-full border-red-200 text-red-700 hover:bg-red-50">Mark as Failed</button>
            <button type="button" id="stop-external-success-btn" class="btn btn-primary w-full bg-green-600 hover:bg-green-700 border-transparent">Mark as Successful</button>
        </div>
    </div>
</div>

<div id="cancel-sale-modal" class="modal-overlay modal-center" style="display: none;">
    <div class="modal-content relative">
        <button id="cancel-sale-close-button" class="modal-close-btn"><i data-lucide="x" class="w-6 h-6"></i></button>
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100"><i data-lucide="trash-2" class="h-6 w-6 text-red-600"></i></div>
            <h3 class="mt-4 text-xl font-bold text-gray-800">Are you sure you want to cancel this sale?</h3>
            <p class="mt-2 text-sm text-gray-500">This action is irreversible. The sale will be marked as canceled and cannot be launched.</p>
        </div>
        <div id="cancel-sale-error-container" class="mt-4"></div>
        <div class="mt-8 flex justify-center gap-4">
            <button type="button" id="cancel-sale-cancel-button" class="btn btn-secondary w-full">Keep Sale</button>
            <button type="button" id="cancel-sale-confirm-button" class="btn bg-red-600 text-white hover:bg-red-700 w-full">Yes, Cancel Sale</button>
        </div>
    </div>
</div>

<dialog id="social-share-dialog" class="dialog-modal" style="max-width: 28rem; width: 100%;">
    <div class="dialog-content">
        <button id="share-modal-close-button" class="modal-close-btn"><i data-lucide="x" class="w-6 h-6"></i></button>
        <div class="share-logo-container">
            <img id="share-modal-logo" src="" alt="Project Logo" class="w-16 h-16 rounded-lg object-contain">
        </div>
        <div class="mt-6 text-center">
            <span id="view-modal-time-left" class="text-lg font-semibold text-gray-800">Not yet live</span>
        </div>
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">Share this campaign or copy the link to spread the word.</p>
        </div>
    </div>
</dialog>