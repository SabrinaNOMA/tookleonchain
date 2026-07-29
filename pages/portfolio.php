<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Include Database Connection
require_once __DIR__ . '/../src/db.php'; 

// 2. Define User ID (assuming from session)
if (!isset($_SESSION['user_id'])) {
    $user_id = 0; 
} else {
    $user_id = $_SESSION['user_id'];
}

// 3. Include the Backend Logic
require_once __DIR__ . '/../backend/portfolio_backend.php'; 
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tookle - Portfolio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="/config_logo.js" defer></script>
    <style>
        :root {
            --main-bg: #f9fafb;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --theme-primary: #10B981; 
            --accent-purple: #8b5cf6;
            --accent-purple-light: #f5f3ff;
            --accent-purple-dark: #7c3aed;
        }
        body { font-family: 'Montserrat', sans-serif; background-color: var(--main-bg); color: var(--text-primary); }
        
        /* Card Styles */
        .project-card {
            background-color: white;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .project-card:hover { transform: translateY(-4px); }
        
        .card-media-container {
            width: 100%;
            aspect-ratio: 16 / 9;
            background-color: #f3f4f6;
            position: relative;
        }
        .card-media-container img, .card-media-container video {
            width: 100%; height: 100%; object-fit: cover;
        }
        .placeholder-gradient {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, #6b7280 0%, #1f2937 100%);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex; align-items: center; padding: 0.25rem 0.75rem;
            font-size: 0.75rem; font-weight: 600; border-radius: 9999px;
            text-transform: capitalize;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .status-live { color: #166534; }
        .status-ended_successful { color: #15803d; }
        .status-ended_failed, .status-canceled { color: #4b5563; }

        /* Historical Table */
        .historical-header-row {
            display: grid;
            grid-template-columns: 2.5fr 1.5fr 1.5fr 2.5fr 1.5fr;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.75rem; font-weight: 600; color: var(--text-secondary);
            text-transform: uppercase; background-color: #f9fafb; border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .historical-item-row {
            display: grid;
            grid-template-columns: 2.5fr 1.5fr 1.5fr 2.5fr 1.5fr;
            gap: 1rem;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: white; border: 1px solid var(--border-color);
            border-radius: 0.5rem; font-size: 0.875rem;
        }
        @media (max-width: 768px) {
            .historical-header-row { display: none; }
            .historical-item-row { grid-template-columns: 1fr; gap: 0.5rem; }
        }

        /* Tabs & Buttons */
        .tab-active { border-bottom: 2px solid var(--accent-purple); color: var(--accent-purple); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; }
        .btn-primary { background-color: var(--text-primary); color: white; }
    </style>
</head>
<body class="h-full overflow-hidden flex flex-col">

    <div class="max-w-7xl mx-auto p-6 md:p-8 w-full flex-1 overflow-y-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Portfolio</h1>
            <p class="text-gray-600 mt-1">Welcome! Here's an overview of your contributions and portfolio performance.</p>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-8">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="#" id="tab-contributions" class="tab-active whitespace-nowrap py-4 px-1 text-sm font-medium">My Contributions</a>
                <a href="#" id="tab-analytics" class="text-gray-500 hover:text-gray-700 whitespace-nowrap py-4 px-1 text-sm font-medium">Analytics</a>
            </nav>
        </div>

        <!-- Content Area -->
        <div id="content-contributions">
            <!-- 1. Active Investments (CARDS) -->
            <div id="active-investments-section" class="hidden mb-12">
                 <h2 class="text-xl font-semibold text-gray-800 mb-6">Active & Successful Contributions</h2>
                 <div id="cards-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"></div>
            </div>

            <!-- 2. Historical/Past Investments (TABLE) -->
            <div id="historical-investments-section" class="hidden mb-12">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Past Contributions</h2>
                <div class="overflow-x-auto">
                    <div class="min-w-full">
                        <div class="historical-header-row">
                            <div>Project</div>
                            <div>Contribution</div>
                            <div>Sale Status</div>
                            <div>My Status</div>
                            <div>Hash</div>
                        </div>
                        <div id="historical-grid" class="space-y-3"></div>
                    </div>
                </div>
            </div>

            <!-- 3. Guides (Unified Legend - Badges Style) -->
            <section id="guides-section" class="mt-8">
                <div class="bg-white border border-gray-200 rounded-xl p-6"> 
                    <div class="flex items-start gap-4"> 
                        <div class="p-3 bg-purple-50 rounded-lg text-purple-600 shrink-0"><i data-lucide="info" class="w-6 h-6"></i></div> 
                        <div> 
                            <h4 class="text-lg font-semibold text-gray-800">Status Guide</h4> 
                            <p class="text-sm text-gray-500 mt-2 mb-4">Understanding your investment statuses:</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4 text-sm text-gray-600">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700 border border-green-100">Active</span>
                                    <span>Funds held securely in escrow.</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Fulfilled</span>
                                    <span>Sale successful, tokens reserved.</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">Processing</span>
                                    <span>Distribution in progress.</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <!-- PURPLE for Refunding -->
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-900 border border-purple-200">Refunding</span>
                                    <span>Goal missed, refund ready.</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">Refunded</span>
                                    <span>Funds returned to wallet.</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 border border-red-100">Failed</span>
                                    <span>Transaction failed.</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-100">Pending</span>
                                    <span>Payment verification ongoing.</span>
                                </div>
                            </div>
                        </div> 
                    </div> 
                </div>
            </section>
        </div>

        <div id="content-analytics" class="hidden">
             <!-- Analytics populated by JS -->
        </div>
        
        <!-- Non-Custodial Notice -->
        <div class="mt-8 mb-4 p-6 bg-gray-50 border border-gray-200 rounded-xl">
            <h5 class="text-sm font-bold text-gray-900 mb-2">Non-Custodial Notice</h5>
            <p class="text-xs text-gray-600 leading-relaxed">
                TOOKLE is a non-custodial platform. It does not hold user funds, private keys, or digital assets at any time. All transactions are executed directly on-chain through user wallets and smart contracts.
            </p>
        </div>
    </div>

<script>
    const portfolioData = <?php echo json_encode($page_data ?? ['error' => 'Data could not be loaded from backend.']); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val);

        const tabContributions = document.getElementById('tab-contributions');
        const tabAnalytics = document.getElementById('tab-analytics');
        const contentContributions = document.getElementById('content-contributions');
        const contentAnalytics = document.getElementById('content-analytics');
        
        // Tab Switching Logic
        function showTab(tabName) {
            const isContributions = tabName === 'contributions';
            tabContributions.classList.toggle('tab-active', isContributions);
            tabContributions.classList.toggle('text-gray-500', !isContributions);
            
            tabAnalytics.classList.toggle('tab-active', !isContributions);
            tabAnalytics.classList.toggle('text-gray-500', isContributions);

            contentContributions.classList.toggle('hidden', !isContributions);
            contentAnalytics.classList.toggle('hidden', isContributions);
        }

        tabContributions.addEventListener('click', (e) => { e.preventDefault(); showTab('contributions'); });
        tabAnalytics.addEventListener('click', (e) => { e.preventDefault(); showTab('analytics'); });

        // Event Delegation for Buttons
        contentContributions.addEventListener('click', (e) => {
            const viewButton = e.target.closest('.view-investment-btn');
            if (viewButton) {
                e.preventDefault();
                handleViewInvestmentClick(viewButton.dataset.projectId, viewButton.dataset.saleName);
            }
        });

        // Redirects to Backend Processor -> Then to Backer Dashboard
        function handleViewInvestmentClick(projectId, saleName) {
            if (!projectId || !saleName) { return; }
            const saleNameEncoded = encodeURIComponent(saleName);
            // This redirection is correct: It sets the session context and moves to the dashboard
            window.location.href = `/backend/select_investment.php?project_id=${projectId}&sale_name=${saleNameEncoded}&redirect_to=backerdashboard`;
        }

        function displayErrorState(message) {
            console.error("Portfolio Error:", message);
            contentContributions.innerHTML = `
                <div class="bg-white border-2 border-dashed border-red-200 rounded-xl p-12 text-center">
                    <div class="mx-auto bg-red-100 rounded-full w-16 h-16 flex items-center justify-center mb-4">
                        <i data-lucide="alert-triangle" class="w-8 h-8 text-red-500"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Unable to load portfolio</h3>
                    <p class="text-sm text-gray-500 mt-2">${message}</p>
                </div>`;
            lucide.createIcons();
        }

        // Card HTML Generator with Updated Styles
        function createPortfolioCard(card) {
            const goal = Number(card.softCap) || Number(card.hardCap) || 1;
            const progress = Math.min((card.raised / goal) * 100, 100);
            
            let media = `<div class="placeholder-gradient flex items-center justify-center"><h3 class="text-white/80 font-bold text-xl p-4 text-center">${card.projectName}</h3></div>`;
            if (card.media_url) {
                const src = `/uploads/${card.media_url}`;
                media = card.media_type === 'video' 
                    ? `<video src="${src}" autoplay muted loop playsinline></video>` 
                    : `<img src="${src}" alt="${card.projectName}">`;
            }

            // Status Logic & Styling
            const status = (card.investorStatus || '').toLowerCase();
            let buttonLabel = "View Details";
            let buttonClass = "bg-gray-900 hover:bg-gray-800 text-white"; // Default dark
            let statusClass = "bg-blue-50 text-blue-700 border-blue-100"; // Default status color

            // Match colors to legend/dashboard
            if (status === 'refunding') {
                buttonLabel = "Manage Refund";
                // Updated to Purple/Black per request
                buttonClass = "bg-purple-600 hover:bg-purple-700 text-white";
                statusClass = "bg-purple-50 text-purple-900 border-purple-200";
            } else if (status === 'failed') {
                buttonLabel = "Manage Refund";
                buttonClass = "bg-red-600 hover:bg-red-700 text-white";
                statusClass = "bg-red-50 text-red-700 border-red-100";
            } else if (status === 'fulfilled') {
                buttonLabel = "View Tokens";
                buttonClass = "bg-emerald-600 hover:bg-emerald-700 text-white";
                statusClass = "bg-emerald-50 text-emerald-700 border-emerald-100";
            } else if (status === 'active') {
                statusClass = "bg-green-50 text-green-700 border-green-100";
            } else if (status === 'processing') {
                statusClass = "bg-blue-50 text-blue-700 border-blue-100";
            } else if (status === 'refunded') {
                statusClass = "bg-gray-100 text-gray-500 border-gray-200";
            } else if (status === 'pending') {
                statusClass = "bg-yellow-50 text-yellow-700 border-yellow-100";
            }

            return `
            <div class="project-card h-full">
                <div class="card-media-container">
                    ${media}
                    <span class="status-badge absolute top-3 right-3 status-${(card.saleStatus||'').toLowerCase()}">
                        ${(card.saleStatus||'').replace('_', ' ')}
                    </span>
                </div>
                <div class="p-6 flex flex-col flex-grow">
                    <div class="mb-4">
                        <h3 class="text-xl font-bold text-gray-900 truncate">${card.projectName}</h3>
                        <p class="text-sm font-medium text-gray-500 truncate">${card.saleName}</p>
                    </div>
                    
                    <div class="space-y-2 mb-6">
                        <div class="flex justify-between text-sm">
                            <span class="font-bold text-gray-900">${formatCurrency(card.raised)}</span>
                            <span class="text-gray-500">Raised</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: ${progress}%"></div>
                        </div>
                    </div>

                    <div class="mt-auto pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-xs font-semibold uppercase text-gray-400">Your Contribution</span>
                            <span class="text-lg font-bold text-gray-900">${formatCurrency(card.yourContribution)}</span>
                        </div>
                        <div class="mb-4">
                            <div class="w-full text-center px-3 py-1.5 rounded text-sm font-medium border ${statusClass}">
                                ${card.investorStatus}
                            </div>
                        </div>
                        <button data-project-id="${card.projectId}" data-sale-name="${card.saleName}" class="view-investment-btn w-full btn ${buttonClass} py-2 rounded-lg font-medium text-sm transition-colors">
                            ${buttonLabel}
                        </button>
                    </div>
                </div>
            </div>`;
        }

        // Historical Row HTML Generator
        function createHistoricalRow(item) {
            const hashLink = item.hash 
                ? `<a href="#" class="text-purple-600 hover:underline font-mono" title="${item.hash}">${item.hash.substring(0,6)}...${item.hash.substring(item.hash.length-4)}</a>` 
                : '<span class="text-gray-400">-</span>';

            const status = (item.investorStatus || '').toLowerCase();
            let statusClass = "bg-gray-100 text-gray-800";
            // Matches new Legend/Card colors
            if (status === 'refunding') statusClass = "bg-purple-100 text-purple-900"; 
            if (status === 'refunded') statusClass = "bg-gray-100 text-gray-500";
            if (status === 'failed') statusClass = "bg-red-100 text-red-800";

            return `
            <div class="historical-item-row">
                <div>
                    <div class="font-bold text-gray-900">${item.projectName}</div>
                    <div class="text-xs text-gray-500">${item.saleName}</div>
                </div>
                <div class="font-semibold text-gray-700">${formatCurrency(item.yourContribution)}</div>
                <div class="capitalize text-sm text-gray-600">${(item.saleStatus||'').replace('_', ' ')}</div>
                <div>
                    <span class="px-2 py-1 rounded text-xs font-semibold ${statusClass}">
                        ${item.investorStatus}
                    </span>
                </div>
                <div class="text-xs">${hashLink}</div>
            </div>`;
        }

        // Main Load Logic
        function loadPortfolio() {
            if (portfolioData.error) {
                displayErrorState(portfolioData.error);
                return;
            }

            const activeList = [];
            const historicalList = [];
            
            // Filter logic
            if (portfolioData.portfolioCards) {
                portfolioData.portfolioCards.forEach(card => {
                    // Statuses considered "Active" for the grid view
                    // We include 'refunding' and 'failed' here so the user sees the Action Button
                    const activeStatuses = ['active', 'processing', 'fulfilled', 'pending', 'refunding', 'failed'];
                    const status = (card.investorStatus || '').toLowerCase();
                    
                    if (activeStatuses.includes(status)) {
                        activeList.push(card);
                    } else {
                        historicalList.push(card);
                    }
                });
            }

            // Render Active
            const activeSection = document.getElementById('active-investments-section');
            if (activeList.length > 0) {
                activeSection.classList.remove('hidden');
                document.getElementById('cards-grid').innerHTML = activeList.map(createPortfolioCard).join('');
            }

            // Render Historical
            const histSection = document.getElementById('historical-investments-section');
            if (historicalList.length > 0) {
                histSection.classList.remove('hidden');
                document.getElementById('historical-grid').innerHTML = historicalList.map(createHistoricalRow).join('');
            }

            // Handle Empty State
            if (activeList.length === 0 && historicalList.length === 0) {
                document.getElementById('content-contributions').innerHTML = `
                    <div class="text-center py-16 bg-white rounded-xl border border-dashed border-gray-300">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                            <i data-lucide="rocket" class="w-8 h-8 text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">No contributions yet</h3>
                        <p class="text-gray-500 mt-1 max-w-sm mx-auto">Start exploring projects to build your portfolio.</p>
                        <a href="/projects" class="mt-6 inline-flex btn btn-primary">Explore Projects</a>
                    </div>
                `;
            }
            
            lucide.createIcons();
        }

        loadPortfolio();
    });
</script>
</body>
</html>