// Global state variables
let projectData = { investor_pipeline_data: [], sale_pages: [] };

const API_URL_FETCH = '/backend/investors_backend.php'; 
const API_URL_UPDATE = '/backend/investors_backend.php';

// Expanded options to cover both DB values and UI preferences
const KYC_OPTIONS = ["Pending", "Verified", "COMPLETED", "Failed", "In Review"];
const DIST_OPTIONS = ["Pending", "Active", "Revoked", "Completed", "N/A"];

let investorTbody, loadingIndicator;

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Initialize DOM Elements with safety checks
    loadingIndicator = document.getElementById('loading-indicator');
    investorTbody = document.getElementById('investor-pipeline-tbody');

    if (typeof currentProjectId !== 'undefined' && currentProjectId) {
        fetchCapTableData(currentProjectId);
    } else {
        console.warn("No currentProjectId defined. Skipping data fetch.");
        if (loadingIndicator) loadingIndicator.style.display = 'none';
    }
    
    setupEventListeners();
});

/**
 * Safely updates text content of an element if it exists
 */
const safeSetText = (id, text) => {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
};

const formatCurrency = (amount) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);

const getStatusBadgeClass = (status) => {
    const s = String(status || '').toLowerCase().replace(/\s+/g, '-');
    return `status-${s}`;
};

const getDynamicBadgeClass = (status) => {
    const s = String(status || 'n-a').toLowerCase().replace(/\s+/g, '-');
    if (['verified', 'active', 'successful', 'completed', 'in-escrow', 'released-to-creator'].includes(s)) return 'status-verified';
    if (['pending', 'initiated', 'in-review'].includes(s)) return 'status-in-review';
    if (['failed', 'revoked', 'canceled'].includes(s)) return 'status-failed';
    return 'status-n-a';
};

const getActionStepText = (status) => {
    switch (status) {
        case 'Awaiting Payment': return 'Monitor payment processing.';
        case 'Payment Failed': return 'Action: Contact backer.';
        case 'Payment Secured': return 'Action: Go to smart vault.';
        case 'Ready for Distribution': return 'Action: Create vesting schedule.';
        case 'Vesting Active': return 'Monitor vesting stream.';
        case 'Vesting Canceled': return 'Monitor canceled stream.';
        case 'Refunding': return 'Monitor refund claim.';
        case 'Refunded': return 'None (Complete).';
        case 'Canceled': return 'None (Final).';
        default: return 'Review needed.';
    }
};

async function fetchCapTableData(projectId) { 
    if (loadingIndicator) loadingIndicator.style.display = 'flex';
    
    try {
        const response = await fetch(`${API_URL_FETCH}?pid=${projectId}&_=${new Date().getTime()}`);
        const result = await response.json();
        if (!result.success) throw new Error(result.message);
        
        projectData.investor_pipeline_data = result.data.allocations || [];
        updateMetrics();
        renderTable();
    } catch (error) {
        console.error('Fetch error:', error);
        if (investorTbody) {
            investorTbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-red-500">Error loading data: ${error.message}</td></tr>`;
        }
    } finally {
        if (loadingIndicator) loadingIndicator.style.display = 'none';
    }
}

function updateMetrics() {
    let totals = { awaiting: 0, secured: 0, ready: 0, active: 0, failed: 0 };
    
    projectData.investor_pipeline_data.forEach(inv => {
        const val = parseFloat(inv.amount || 0);
        const status = inv.derived_status;
        
        if (status === 'Awaiting Payment') totals.awaiting += val;
        else if (status === 'Payment Secured') totals.secured += val;
        else if (status === 'Ready for Distribution') totals.ready += val;
        else if (status === 'Vesting Active') totals.active += val;
        else if (['Payment Failed', 'Vesting Canceled', 'Canceled'].includes(status)) totals.failed += val;
    });

    safeSetText('total-awaiting-payment', formatCurrency(totals.awaiting));
    safeSetText('total-in-escrow', formatCurrency(totals.secured));
    safeSetText('total-ready-for-distribution', formatCurrency(totals.ready));
    safeSetText('total-vesting-active', formatCurrency(totals.active));
    safeSetText('total-unsuccessful-funds', formatCurrency(totals.failed));
}

function renderTable() {
    if (!investorTbody) {
        console.error("Table body 'investor-pipeline-tbody' not found in DOM.");
        return;
    }
    
    investorTbody.innerHTML = '';

    if (projectData.investor_pipeline_data.length === 0) {
        investorTbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-gray-500 italic">No backers found.</td></tr>';
        return;
    }

    projectData.investor_pipeline_data.forEach(inv => {
        const tr = document.createElement('tr');
        tr.className = "border-b hover:bg-gray-50 transition-colors";
        tr.dataset.investorId = inv.investment_id; // CRITICAL: Ensures click handler can find the data
        
        const fullName = `${inv.first_name || ''} ${inv.last_name || ''}`.trim();
        const derivedStatus = inv.derived_status || 'Under Review';
        
        // Handle null payment status visually
        const payStatus = inv.payment_status || 'pending';
        
        tr.innerHTML = `
            <td class="p-3 text-center">
                <button class="text-gray-400 hover:text-purple-600 transition-colors edit-btn" title="Edit Backer">
                    <i data-lucide="edit" class="w-4 h-4"></i>
                </button>
            </td>
            <td class="p-3">
                <div class="font-bold text-gray-800">${fullName}</div>
                <div class="text-xs text-gray-500">${inv.contact || ''}</div>
            </td>
            <td class="p-3 text-right font-mono text-gray-700">${formatCurrency(inv.amount)}</td>
            
            <!-- NEW RAW STATUS COLUMNS -->
            <td class="p-3">
                <span class="status-badge ${getDynamicBadgeClass(inv.investment_status)}">${inv.investment_status || 'N/A'}</span>
            </td>
            <td class="p-3">
                <span class="status-badge ${getDynamicBadgeClass(payStatus)}">${payStatus}</span>
            </td>
            
            <td class="p-3">
                <span class="status-badge ${getStatusBadgeClass(derivedStatus)}">
                    ${derivedStatus}
                </span>
            </td>
            <td class="p-3 text-gray-500 text-xs italic">${getActionStepText(derivedStatus)}</td>
            <td class="p-3">
                <span class="status-badge ${getDynamicBadgeClass(inv.kyc_status)}">${inv.kyc_status || 'Pending'}</span>
            </td>
            <td class="p-3 text-sm text-gray-600">${inv.distribution_status || 'N/A'}</td>
            <td class="p-3 text-right font-mono text-gray-700">
                ${parseFloat(inv.token_quantity || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}
            </td>
        `;
        investorTbody.appendChild(tr);
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/**
 * ROBUST MODAL POPULATION
 * Handles case-sensitivity and missing values safely.
 */
const openEditModal = (investor) => {
    document.getElementById('edit-investment-id').value = investor.investment_id;
    document.getElementById('edit-user-id').value = investor.user_id;
    document.getElementById('edit-first-name').value = investor.first_name;
    document.getElementById('edit-last-name').value = investor.last_name;
    document.getElementById('edit-contact').value = investor.contact;
    
    // Helper function to populate Select with Robust Selection Logic
    const populateSelect = (elementId, optionsArray, currentValue) => {
        const select = document.getElementById(elementId);
        select.innerHTML = '';
        
        const rawValue = currentValue || ''; 
        const normalizedCurrent = String(rawValue).trim().toLowerCase();
        let matchFound = false;
        
        // 1. Add standard options
        optionsArray.forEach(opt => {
            // Check matching (case-insensitive)
            const isSelected = String(opt).toLowerCase() === normalizedCurrent;
            if (isSelected) matchFound = true;
            
            const option = new Option(opt, opt);
            if (isSelected) {
                option.selected = true;
            }
            select.add(option);
        });

        // 2. Safety Net: If the DB value isn't in our standard list (e.g. "COMPLETED" vs "Verified"),
        // add it as a new option so the user sees the REAL current state instead of a default.
        if (!matchFound && rawValue.trim() !== '') {
             const customOpt = new Option(rawValue, rawValue);
             customOpt.selected = true;
             select.add(customOpt);
        }
        
        // 3. FORCE SELECTION via value property (The most reliable way)
        // If we found a match or added a custom option, setting .value ensures the browser UI updates.
        if (matchFound || (rawValue.trim() !== '')) {
            // Try setting exact value first
            select.value = rawValue;
            
            // If that didn't work (case mismatch), find the option by text content
            if (select.selectedIndex === -1 || select.value !== rawValue) {
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].text.toLowerCase() === normalizedCurrent) {
                        select.selectedIndex = i;
                        break;
                    }
                }
            }
        }
    };

    // Populate using helper
    populateSelect('edit-kyc-status', KYC_OPTIONS, investor.kyc_status || 'Pending');
    populateSelect('edit-distribution-status', DIST_OPTIONS, investor.distribution_status || 'N/A');

    const modal = document.getElementById('edit-investor-modal');
    if (modal) modal.classList.add('show');
};

function setupEventListeners() {
    const toggle = document.getElementById('definitions-toggle');
    const content = document.getElementById('definitions-content');
    
    if (toggle && content) {
        toggle.addEventListener('click', () => {
            const isHidden = content.classList.toggle('hidden');
            const chevron = toggle.querySelector('[data-lucide="chevron-down"]');
            if (chevron) {
                chevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
    }
}