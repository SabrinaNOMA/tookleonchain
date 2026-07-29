// dashboard.js - Main Dashboard Logic
// Handles rendering of sales cards, metrics, and modal interactions.
// UPDATED: Added "Self-Healing Oracle" to sync Blockchain Status with Database automatically.

document.addEventListener('DOMContentLoaded', () => {
    // Note: dashboardData and projectData are globally defined in pages/dashboard.php
    console.log('Dashboard JS v2.28 Loaded. Soft Cap Tracking Active.'); 
    
    // --- Global Selectors ---
    const metricsContainer = document.getElementById('key-metrics-container');
    const contentContainer = document.getElementById('dashboard-content');

    // --- Formatters ---
    const formatCurrency = (amount) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount || 0);

    // --- Helpers ---
    const isTrue = (val) => val == 1 || val === '1' || val === true;

    // --- Modal Logic ---
    function closeModal(modalElement) {
        if (!modalElement) return;
        if (modalElement.tagName === 'DIALOG') {
            modalElement.close();
        } else {
            modalElement.style.display = 'none';
            modalElement.classList.remove('active');
        }
    }

    function openModal(modalElement) {
        if (!modalElement) return;
        if (modalElement.tagName === 'DIALOG') {
            modalElement.showModal();
        } else {
            modalElement.style.display = 'flex';
            setTimeout(() => modalElement.classList.add('active'), 10);
        }
        if (window.lucide) window.lucide.createIcons();
    }

    // Modal Selectors & Listeners
    const viewModal = document.getElementById('view-sale-modal');
    const detailsModal = document.getElementById('sale-details-modal');

    document.querySelectorAll('.modal-close-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.modal-overlay') || e.target.closest('dialog');
            closeModal(modal);
        });
    });
    
    const footerCloseBtn = document.getElementById('details-close-btn-footer');
    if (footerCloseBtn) footerCloseBtn.addEventListener('click', () => closeModal(detailsModal));

    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });

    // --- Setup Copy Link Listener (View Modal) ---
    const viewCopyBtn = document.getElementById('view-copy-link-button');
    if (viewCopyBtn) {
        viewCopyBtn.addEventListener('click', () => {
            const shareUrl = viewCopyBtn.dataset.shareUrl;
            if (shareUrl) {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    const span = document.getElementById('view-copy-link-text');
                    if (span) {
                        const originalText = span.textContent;
                        span.textContent = 'Copied!';
                        setTimeout(() => span.textContent = originalText, 2000);
                    }
                }).catch(err => console.error('Failed to copy: ', err));
            }
        });
    }

    // --- NEW: Stop External Sale Logic ---
    let activeExternalSaleId = null;
    const stopExternalModal = document.getElementById('stop-external-sale-modal');
    const stopExtCloseBtn = document.getElementById('stop-external-close-button');
    if (stopExtCloseBtn) {
        stopExtCloseBtn.addEventListener('click', () => closeModal(stopExternalModal));
    }

    async function finalizeExternalSale(outcome) {
        if (!activeExternalSaleId) return;
        
        const btnId = outcome === 'ended_successful' ? 'stop-external-success-btn' : 'stop-external-fail-btn';
        const btn = document.getElementById(btnId);
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Processing...';

        try {
            const response = await fetch('/backend/dashboard_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'stop_sale', 
                    sale_id: activeExternalSaleId,
                    external_outcome: outcome
                })
            });
            const result = await response.json();
            if (result.success) window.location.reload();
            else throw new Error(result.error || 'Failed to stop sale.');
        } catch (error) {
            console.error(error);
            alert(error.message);
            btn.disabled = false;
            btn.innerText = originalText;
        }
    }

    const btnSuccess = document.getElementById('stop-external-success-btn');
    const btnFail = document.getElementById('stop-external-fail-btn');
    
    if (btnSuccess) btnSuccess.addEventListener('click', () => finalizeExternalSale('ended_successful'));
    if (btnFail) btnFail.addEventListener('click', () => finalizeExternalSale('ended_failed'));

    window.openStopExternalModal = function(saleId) {
        activeExternalSaleId = saleId;
        openModal(stopExternalModal);
    };

    // --- Exposed Window Functions ---
    window.openDetailsModal = function(saleJsonBase64) {
        try {
            const sale = JSON.parse(decodeURIComponent(escape(atob(saleJsonBase64))));
            if (!detailsModal) return;
            
            // 1. Populate Header & Identity
            document.getElementById('details-modal-title').textContent = sale.sale_name || 'Untitled Sale';
            document.getElementById('details-modal-round').textContent = sale.round || 'Private Round';
            
            // 2. Metrics
            document.getElementById('details-modal-raised').textContent = formatCurrency(sale.current_funding || 0);
            document.getElementById('details-modal-investors').textContent = sale.investor_count || 0;
            
            // 3. Campaign Data Grid
            document.getElementById('details-modal-platform').textContent = (sale.hosting || 'Tookle');
            document.getElementById('details-modal-terms').textContent = sale.sale_terms || 'Standard Vesting';
            document.getElementById('details-modal-terms').title = sale.sale_terms || ''; // Tooltip for truncation
            
            document.getElementById('details-modal-soft').textContent = formatCurrency(sale.min_raise || sale.soft_cap || 0);
            document.getElementById('details-modal-hard').textContent = formatCurrency(sale.max_raise || sale.hard_cap || 0);
            
            const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'TBD';
            document.getElementById('details-modal-start').textContent = formatDate(sale.sale_launch_at);
            document.getElementById('details-modal-end').textContent = formatDate(sale.sale_end_at);

            // 4. Logo
            const logoImg = document.getElementById('details-modal-logo');
            if (logoImg) {
                const logoSrc = sale.sale_logo || (typeof projectData !== 'undefined' ? projectData.project_logo : null) || 'https://placehold.co/64x64/6D28D9/FFFFFF?text=L';
                logoImg.src = logoSrc;
            }

            // 5. Status Badge Logic
            const statusEl = document.getElementById('details-modal-status-badge');
            const s = (sale.status || 'draft').toLowerCase();
            let badgeClass = 'bg-gray-100 text-gray-600';
            let statusText = 'Draft';
            
            if (s === 'live') { badgeClass = 'bg-green-100 text-green-700'; statusText = 'Live'; }
            else if (s === 'ended_successful') { badgeClass = 'bg-indigo-100 text-indigo-700'; statusText = 'Successful'; }
            else if (s === 'ended_failed') { badgeClass = 'bg-red-100 text-red-700'; statusText = 'Failed'; }
            else if (s === 'scheduled') { badgeClass = 'bg-blue-100 text-blue-700'; statusText = 'Scheduled'; }
            
            statusEl.className = `inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${badgeClass}`;
            statusEl.textContent = statusText;

            // 6. Links Section (Updated for Internal/Draft support)
            const linkUrl = document.getElementById('details-modal-link-url');
            const isTookle = (sale.hosting === 'tookle' || !sale.hosting);
            
            if (isTookle) {
                // Internal Sale (Tookle)
                // Use the public_token exposed by backend to construct the link
                if (sale.public_token) {
                    linkUrl.style.display = 'flex';
                    linkUrl.href = `/p/${sale.public_token}`; 
                    
                    const linkText = linkUrl.querySelector('span.text-xs');
                    if (linkText) {
                        linkText.textContent = (s === 'draft') ? "Preview Sale Page" : "View public page";
                    }
                } else {
                    linkUrl.style.display = 'none';
                }
            } else if (sale.sale_url) {
                // External Sale
                linkUrl.style.display = 'flex';
                linkUrl.href = sale.sale_url;
                const linkText = linkUrl.querySelector('span.text-xs');
                if (linkText) linkText.textContent = "View external page";
            } else {
                linkUrl.style.display = 'none';
            }

            const vaultRow = document.getElementById('details-vault-row');
            const vaultAddr = document.getElementById('details-modal-vault-address');
            const vaultLink = document.getElementById('details-modal-vault-link');
            
            if (sale.contract_address) {
                vaultRow.style.display = 'flex';
                vaultAddr.textContent = sale.contract_address;
                vaultLink.href = `https://basescan.org/address/${sale.contract_address}`;
            } else {
                vaultRow.style.display = 'none';
            }

            // 7. Actions (Stop / Release)
            const stopBtn = document.getElementById('details-stop-btn');
            const releaseBtn = document.getElementById('details-release-btn');
            
            if (stopBtn) stopBtn.style.display = 'none';
            if (releaseBtn) releaseBtn.style.display = 'none'; // Always hidden, logic removed

            openModal(detailsModal);
        } catch(e) { console.error(e); }
    };

    window.openViewModal = function(saleJsonBase64) {
        try {
            const sale = JSON.parse(decodeURIComponent(escape(atob(saleJsonBase64))));
            if (!viewModal) return;
            
            const logoImg = document.getElementById('view-modal-logo');
            if (logoImg) {
                const logoSrc = sale.sale_logo || (typeof projectData !== 'undefined' ? projectData.project_logo : null) || 'https://placehold.co/64x64/6D28D9/FFFFFF?text=L';
                logoImg.src = logoSrc;
            }

            const title = document.getElementById('view-modal-title');
            const subtitle = document.getElementById('view-modal-subtitle');
            if(title) title.textContent = sale.sale_name;
            if(subtitle) subtitle.textContent = sale.round || 'Private Sale';

            // --- Social Share Logic ---
            // Construct URL: Use public_token if available (Internal), else sale_url (External)
            const shareUrl = sale.public_token 
                ? `${window.location.origin}/p/${sale.public_token}`
                : (sale.sale_url || window.location.href);
            
            const encodedUrl = encodeURIComponent(shareUrl);
            const encodedText = encodeURIComponent(`Check out ${sale.sale_name} on TOOKLE.app!`);

            // Update Social Links
            const twBtn = document.getElementById('view-twitter-share-link');
            if(twBtn) twBtn.href = `https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`;

            const fbBtn = document.getElementById('view-facebook-share-link');
            if(fbBtn) fbBtn.href = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
            
            const liBtn = document.getElementById('view-linkedin-share-link');
            if(liBtn) liBtn.href = `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`;

            // Update Copy Button Data for the listener defined at top of file
            const copyBtn = document.getElementById('view-copy-link-button');
            if(copyBtn) copyBtn.dataset.shareUrl = shareUrl;

            openModal(viewModal);
        } catch(e) { console.error(e); }
    };

    // --- Institutional Card Actions ---

    window.toggleSaleMenu = function(event, saleId) {
        event.stopPropagation();
        const menu = document.getElementById(`sale-menu-${saleId}`);
        const isHidden = menu.classList.contains('hidden');
        document.querySelectorAll('[id^="sale-menu-"]').forEach(m => m.classList.add('hidden'));
        if (isHidden) menu.classList.remove('hidden');
    };

    document.addEventListener('click', () => {
        document.querySelectorAll('[id^="sale-menu-"]').forEach(m => m.classList.add('hidden'));
    });

    window.startPrivateSale = function(saleId) {
        let modal = document.getElementById('start-sale-confirmation-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'start-sale-confirmation-modal';
            modal.className = 'modal-overlay modal-center';
            modal.style.display = 'none';
            modal.style.zIndex = '1000';
            modal.innerHTML = `
                <div class="modal-content relative bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full">
                    <button class="modal-close-btn absolute top-4 right-4 text-gray-400 hover:text-gray-600" onclick="document.getElementById('start-sale-confirmation-modal').style.display='none'">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 mb-4">
                            <i data-lucide="shield-check" class="h-7 w-7 text-slate-900"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Initialize Smart Vault</h3>
                        <p class="text-sm text-gray-600 leading-relaxed px-2">
                            You are about to deploy your Smart Vault. This will create a secure, immutable contract for your sale.
                        </p>
                    </div>
                    <div class="mt-8 flex justify-center gap-3">
                        <button type="button" class="btn btn-secondary w-full py-2.5 font-medium" onclick="document.getElementById('start-sale-confirmation-modal').style.display='none'">Cancel</button>
                        <button type="button" id="start-sale-confirm-btn" class="btn w-full py-2.5 font-medium shadow-md bg-slate-900 text-white hover:bg-black">Proceed</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            if (window.lucide) window.lucide.createIcons();
        }

        const confirmBtn = modal.querySelector('#start-sale-confirm-btn');
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', () => {
             window.location.href = `/setup_vault?sale_id=${saleId}`;
        });

        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    };

    // --- Switcher Logic ---
    async function handleProjectSwitch(projectId) {
        try {
            const response = await fetch('/backend/dashboard_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'switch_project', project_id: projectId })
            });
            const result = await response.json();
            if (result.success) window.location.reload();
            else throw new Error(result.error || 'Failed.');
        } catch (error) { console.error(error); alert(error.message); }
    }

    async function handleCreateNewProject() {
        try {
            const response = await fetch('/backend/dashboard_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'prepare_new_project' })
            });
            const result = await response.json();
            if (result.success) window.location.href = '/setup';
            else throw new Error(result.error || 'Failed.');
        } catch (error) { console.error(error); alert(error.message); }
    }

    // --- Create Selector ---
    function createProjectSelector(projects, activeId) {
        const actionsContainer = document.getElementById('dashboard-actions-container');
        const safeProjects = Array.isArray(projects) ? projects : [];

        if (actionsContainer) {
            let optionsHtml = safeProjects.map(p => `<a href="#" data-project-id="${p.id}" class="project-switch-link block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">${p.project_name}</a>`).join('');
            optionsHtml += `<div class="my-1 h-px bg-gray-100"></div><a href="#" id="create-new-project-link" class="flex items-center px-4 py-2 text-sm font-semibold text-[var(--theme-primary)] hover:bg-gray-100 rounded-md"><i data-lucide="plus-circle" class="inline w-4 h-4 mr-2"></i>New Company</a>`;
            
            // LOGIC UPDATE: Only show "New Private Sale" button if setup is complete (Operational Phase)
            let showNewSaleBtn = false;
            if (typeof dashboardData !== 'undefined' && dashboardData.active_project_details) {
                const details = dashboardData.active_project_details;
                showNewSaleBtn = isTrue(details.project_described) && 
                                 isTrue(details.tokenomics_done) && 
                                 isTrue(details.token_sale_page_ready);
            }

            const newSaleBtnHtml = showNewSaleBtn ? `
                <a href="/sales" class="flex-shrink-0 inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-gray-900 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200">
                    <i data-lucide="plus" class="w-4 h-4 mr-2 text-gray-500"></i>New Private Sale
                </a>
            ` : '';

            actionsContainer.className = "flex items-center gap-3"; 
            actionsContainer.innerHTML = `
                ${newSaleBtnHtml}
                <div class="relative">
                    <button type="button" id="project-selector-button" class="inline-flex items-center justify-between w-full px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200" style="min-width: 160px;">
                        <span>My Companies</span>
                        <i data-lucide="chevrons-up-down" class="w-4 h-4 mr-2 text-gray-400"></i>
                    </button>
                    <div id="project-selector-dropdown" class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl bg-white p-2 shadow-lg ring-1 ring-black ring-opacity-5 hidden z-20 transition-all">
                        ${optionsHtml}
                    </div>
                </div>
            `;
            
            const button = actionsContainer.querySelector('#project-selector-button');
            const dropdown = actionsContainer.querySelector('#project-selector-dropdown');
            if (button && dropdown) {
                button.addEventListener('click', (e) => { e.stopPropagation(); dropdown.classList.toggle('hidden'); });
                document.addEventListener('click', (e) => { 
                    if (!dropdown.classList.contains('hidden') && !button.contains(e.target)) dropdown.classList.add('hidden');
                });
                dropdown.addEventListener('click', (e) => {
                    const link = e.target.closest('a');
                    if (!link) return;
                    e.preventDefault();
                    if (link.id === 'create-new-project-link') handleCreateNewProject();
                    else if (link.classList.contains('project-switch-link')) handleProjectSwitch(link.dataset.projectId);
                });
            }
        }

        const sectionHeader = document.createElement('div');
        sectionHeader.className = "mb-6";
        sectionHeader.innerHTML = `
            <div class="mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span>Private Sales</span>
                </h2>
            </div>
            <div class="flex items-center bg-gray-100 p-1 rounded-lg w-fit">
                <button class="btn-filter-pill active" data-filter="all">All Sales</button>
                <button class="btn-filter-pill" data-filter="tookle">Self-Hosted</button>
                <button class="btn-filter-pill" data-filter="external">External</button>
            </div>
        `;
        
        sectionHeader.querySelectorAll('.btn-filter-pill').forEach(button => {
            button.addEventListener('click', () => {
                sectionHeader.querySelectorAll('.btn-filter-pill').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                const filter = button.dataset.filter;
                document.querySelectorAll('.sale-card-list-item').forEach(card => {
                    const platform = card.dataset.platform; 
                    let show = false;
                    if (filter === 'all') show = true;
                    else if (filter === 'tookle') show = platform === 'tookle';
                    else if (filter === 'external') show = platform !== 'tookle';
                    card.style.display = show ? 'flex' : 'none';
                });
            });
        });
        return sectionHeader;
    }

    // --- Institutional Private Sale Card ---
    function createSalesCards(sales) {
        if (!sales || sales.length === 0) return '';
        
        const btnClass = "px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm inline-flex items-center justify-center min-w-[80px]";

        const getStatusBadge = (status) => {
            const s = (status || 'draft').toLowerCase();
            let label = s.charAt(0).toUpperCase() + s.slice(1).replace('_', ' ');
            let badgeClass = `status-${s}`;
            
            if (s === 'ended_successful') {
                label = 'Successful';
                badgeClass = 'status-successful';
            } else if (s === 'ended_failed') {
                label = 'Failed';
                badgeClass = 'status-failed';
            }

            let iconHtml = '<span class="status-badge-dot"></span>';
            if (s === 'ended_successful') {
                iconHtml = `<i data-lucide="check-circle-2" class="w-3.5 h-3.5 mr-1.5"></i>`;
            }

            return `<span class="status-badge ${badgeClass}">${iconHtml}${label}</span>`;
        };

        return `<div class="flex flex-col gap-4">` + sales.map(sale => {
             const logoSrc = sale.sale_logo || (projectData?.project_logo) || 'https://placehold.co/64x64/F3F4F6/9CA3AF?text=L';
             const saleJsonBase64 = btoa(unescape(encodeURIComponent(JSON.stringify(sale))));
             
             const status = (sale.status || 'draft').toLowerCase();
             const isSelfHosted = (sale.hosting || 'Tookle').toLowerCase() === 'tookle';
             const raised = parseFloat(sale.current_funding || 0);
             // UPDATE: Use Soft Cap (min_raise) as the Goal for progress tracking
             const goal = parseFloat(sale.min_raise || sale.soft_cap || 0); 
             const investors = parseInt(sale.investor_count || 0);
             const daysRemaining = sale.days_remaining !== undefined ? parseInt(sale.days_remaining) : null;
             
             const progressPercent = goal > 0 ? Math.min(100, (raised / goal) * 100) : 0;
             const raisedStr = formatCurrency(raised);
             const goalStr = goal > 0 ? formatCurrency(goal) : 'TBD';

             let actionButtons = '';
             
             if (status === 'draft') {
                 if (!isSelfHosted) {
                     // EXTERNAL DRAFT -> REDIRECT TO NEWSALE PAGE WITH SALE ID
                     actionButtons += `<a href="/sales?sale_id=${sale.id}" class="${btnClass}">Edit</a>`;
                 } else {
                     // INTERNAL DRAFT -> SHOW START PRIVATE SALE BUTTON AND VIEW DETAILS
                     actionButtons += `<button onclick="window.startPrivateSale('${sale.id}')" class="${btnClass}">Start Private Sale</button>`;
                     // NEW: Added View Details button for self-hosted drafts
                     actionButtons += `<button onclick="window.openDetailsModal('${saleJsonBase64}')" class="${btnClass}">View Details</button>`;
                 }
             } 
             else {
                 if (status === 'live') {
                     if (!isSelfHosted) {
                         actionButtons += `<button onclick="window.openStopExternalModal('${sale.id}')" class="${btnClass} text-red-600 hover:text-red-700 hover:bg-red-50 hover:border-red-200">Stop</button>`;
                     } else {
                         actionButtons += `<button onclick="window.openViewModal('${saleJsonBase64}')" class="${btnClass}">Share</button>`;
                     }
                 }
                 
                 // MODIFIED: Only show "View Vault" (Claim Funds) for self-hosted sales
                 if (sale.contract_address && isSelfHosted) {
                      actionButtons += `<a href="/claim_funds?sale_id=${sale.id}" class="${btnClass}">View Vault</a>`;
                 }
                 
                 const viewLabel = status === 'live' ? 'View Details' : 'View Details';
                 // MODIFIED: All "View" buttons now open the read-only details modal
                 actionButtons += `<button onclick="window.openDetailsModal('${saleJsonBase64}')" class="${btnClass}">${viewLabel}</button>`;
             }

             let centerContent = '';
             if (status === 'draft') {
                 centerContent = `
                    <div class="flex items-center h-full">
                        <span class="text-sm text-gray-500 font-medium">Private sale not started yet.</span>
                    </div>
                 `;
             } else {
                 const investorsText = investors === 0 ? "Waiting for backers." : `${investors} backers`;
                 
                 let metricHtml = '';
                 let progressBarHtml = '';
                 
                 const timeLabel = (status === 'live' && daysRemaining !== null) 
                    ? (daysRemaining > 1 ? `${daysRemaining} Days Left` : (daysRemaining === 1 ? '1 Day Left' : 'Ending Soon'))
                    : null;

                 if (raised === 0) {
                      const mainLabel = timeLabel || 'No funds raised';
                      metricHtml = `
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-lg font-bold text-gray-900 tabular-nums">${mainLabel}</span>
                            <span class="text-sm text-gray-500 font-medium">to raise ${goalStr}</span>
                        </div>`;
                      progressBarHtml = ''; 
                 } else {
                      // UPDATE: Changed label to explicit "Soft Cap"
                      let subLabel = `raised of Soft Cap ${goalStr}`;
                      if (timeLabel) subLabel += ` • ${timeLabel}`;
                      
                      metricHtml = `
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-lg font-bold text-gray-900 tabular-nums">${raisedStr}</span>
                            <span class="text-sm text-gray-500 font-medium">${subLabel}</span>
                        </div>`;
                      
                      progressBarHtml = `
                     <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="bg-gray-900 h-2 rounded-full transition-all duration-500" style="width: ${progressPercent}%"></div>
                     </div>`;
                 }

                 centerContent = `
                     <div class="flex items-baseline justify-between mb-2">
                        ${metricHtml}
                        <span class="text-xs text-gray-500 font-medium tabular-nums">${investorsText}</span>
                     </div>
                     ${progressBarHtml}
                 `;
             }

             return `
            <div class="sale-card-list-item bg-white border border-gray-200 rounded-xl p-6 hover:shadow-md transition-shadow flex flex-col md:flex-row md:items-center justify-between gap-6" data-platform="${(sale.hosting || 'tookle').toLowerCase()}">
                <div class="flex items-center gap-4 min-w-[240px]">
                    <div class="h-12 w-12 rounded-lg border border-gray-100 bg-gray-50 flex-shrink-0 overflow-hidden flex items-center justify-center">
                        <img src="${logoSrc}" alt="" class="h-full w-full object-contain">
                    </div>
                    <div class="flex flex-col">
                        <span class="text-base font-semibold text-gray-900 tracking-tight">${sale.sale_name}</span>
                        <div class="flex items-center gap-2 mt-1 text-sm text-gray-500 font-medium">
                            <span>${sale.round || 'Private Round'}</span>
                            <span class="text-gray-300">•</span>
                            ${getStatusBadge(status)}
                        </div>
                    </div>
                </div>
                <div class="flex-1 flex flex-col justify-center max-w-lg min-h-[48px]">
                     ${centerContent}
                </div>
                <div class="flex items-center justify-end gap-3 min-w-[200px]">
                    ${actionButtons}
                </div>
            </div>`;
        }).join('') + `</div>`;
    }

    // --- Render Design Phase (Kanban) ---
    function createDesignKanban(project) {
        const getCardClass = (status) => (status === 'completed') ? 'bg-white border border-gray-200 border-l-4 border-l-[var(--theme-primary)]' : ((status === 'inprogress') ? 'bg-white border border-[var(--theme-primary)] shadow-md ring-1 ring-indigo-50 relative z-10' : 'bg-gray-50 border border-gray-100 opacity-60');
        const getIconClass = (status) => (status === 'completed') ? 'text-[var(--theme-primary)]' : (status === 'inprogress' ? 'text-indigo-600' : 'text-gray-400');

        const isDescribed = isTrue(project.project_described);
        const isTokenomics = isTrue(project.tokenomics_done);
        const isStory = isTrue(project.token_sale_page_ready);

        const step1Status = isDescribed ? 'completed' : 'inprogress';
        const step2Status = isDescribed ? (isTokenomics ? 'completed' : 'inprogress') : 'todo';
        const step3Status = (isDescribed && isTokenomics) ? (isStory ? 'completed' : 'inprogress') : 'todo';

        const steps = [
            { title: 'Project Overview', desc: 'Define the problem you address and your solution.', status: step1Status, link: '/setup', num: 'Phase 1 - Step 1', btnText: 'Configure Overview' },
            { title: 'Funding Plan', desc: 'Structure your private raise.', status: step2Status, link: '/tokenname', num: 'Phase 1 - Step 2', btnText: 'Define Plan' },
            { title: 'Private Sale Room', desc: 'Create your private sale space.', status: step3Status, link: '/story', num: 'Phase 1 - Step 3', btnText: 'Create Room' }
        ];

        const cards = steps.map(step => {
            const cardClass = getCardClass(step.status);
            const iconClass = getIconClass(step.status);
            const checkmark = step.status === 'completed' ? `<div class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-50"><i data-lucide="check" class="w-3.5 h-3.5 text-[var(--theme-primary)]"></i></div>` : '';
            return `
             <a href="${step.link}" class="block p-6 rounded-lg transition-all duration-200 hover:shadow-md ${cardClass}">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold uppercase tracking-widest text-gray-500">${step.num}</span>
                    ${checkmark}
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">${step.title}</h3>
                <p class="text-sm font-medium text-gray-700 mb-1">${step.desc}</p>
                <div class="mt-auto flex items-center text-sm font-semibold ${iconClass}">
                    ${step.status === 'completed' ? 'Completed' : (step.status === 'inprogress' ? step.btnText + ' &rarr;' : 'Pending Previous')}
                </div>
            </a>`;
        }).join('');

        return `<div class="mb-6"><h2 class="text-2xl font-bold text-gray-900 tracking-tight">Design Phase</h2><p class="text-gray-500 mt-1 text-sm">Prepare your private sale for execution. Define your project, structure your funding plan, and prepare your private sale room.</p></div><div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">${cards}</div>`;
    }
    
    function createOperationalView(project) {
        if (metricsContainer) {
            metricsContainer.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">Total Funds Raised</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">${formatCurrency(project?.metrics?.total_raised || 0)}</div>
                </div>
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">Total Backers</div>
                    <div class="text-3xl font-bold text-gray-900 mt-1">${project?.metrics?.unique_investors || 0}</div>
                </div>
            </div>`;
        }
        if (project.sales && project.sales.length > 0) return createSalesCards(project.sales);
        return '<div class="text-center p-8 bg-white rounded-xl border-2 border-dashed border-gray-300"><h3>Ready for Liftoff!</h3><a href="/sales" class="btn btn-primary mt-6">Create First Sale</a></div>';
    }
    
    function loadDashboard() {
        if (typeof dashboardData === 'undefined' || !dashboardData) {
            console.error('Dashboard: Data object is missing.');
            if (contentContainer) contentContainer.innerHTML = '<div class="p-6 text-center text-gray-500">Dashboard data is unavailable. Please refresh.</div>';
            return;
        }

        if (dashboardData.error) {
             console.error('Dashboard: Backend Error', dashboardData.error);
             if (contentContainer) contentContainer.innerHTML = `<div class="p-6 text-center text-red-600 bg-red-50 rounded-xl border border-red-200">Unable to load dashboard: ${dashboardData.error}</div>`;
             return;
        }
        
        const { project_list, active_project_details } = dashboardData;
        
        if (active_project_details) {
            console.log("Project Data Debug:", {
                id: active_project_details.id,
                name: active_project_details.project_name
            });
        }

        const isSetupComplete = (
            active_project_details && 
            isTrue(active_project_details.project_described) && 
            isTrue(active_project_details.tokenomics_done) && 
            isTrue(active_project_details.token_sale_page_ready)
        );

        const status = isSetupComplete ? 'operational' : 'design'; 
        
        const sectionHeader = createProjectSelector(project_list, dashboardData.active_project_id);
        const phpDesignKanban = document.getElementById('php-design-kanban-wrapper');
        
        if (status === 'design') {
            if (!phpDesignKanban && contentContainer) {
                contentContainer.innerHTML = createDesignKanban(active_project_details);
            }
            if (metricsContainer) metricsContainer.style.display = 'none';
        } else {
            if (contentContainer) {
                if (contentContainer.parentNode) {
                    Array.from(contentContainer.parentNode.children).forEach(el => {
                        if (el.textContent.includes('Operational Phase') && el !== contentContainer) el.style.display = 'none';
                    });
                    if (sectionHeader) contentContainer.parentNode.insertBefore(sectionHeader, contentContainer);
                }
                contentContainer.innerHTML = createOperationalView(active_project_details);
            }
            if (metricsContainer) metricsContainer.style.display = 'block';
        }
        if (window.lucide) window.lucide.createIcons();
    }
    
    loadDashboard();
});