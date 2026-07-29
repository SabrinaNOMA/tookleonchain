<?php
/**
 * Page: Project Setup (FIXED: Folder Structure & Redirects)
 * Filepath: /pages/setup.php
 * * Updates:
 * 1. Removed hardcoded 'tookle2' folder reference.
 * 2. Updated redirection to be relative (works in any folder).
 * 3. Kept the description saving fix.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_GET['new']) && $_GET['new'] === '1') {
    unset($_SESSION['active_project_id']);
    $project_id = null;
}
$pdo = require __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../wizard_nav.php';

// --- STATE DEFINITION ---
$current_main_step = 'describe';

// FIX: Removed specific folder name. Backend URL is relative to root.
// If your setup.php is in /pages/, ensure /backend/ is accessible from web root.
$backend_url = '/backend/setup_backend.php';

$founder_id = $_SESSION['user_id'] ?? null;
$project_id = $_SESSION['active_project_id'] ?? null;

$initial_data = [
    'userInfo' => null,
    'projectData' => null,
    'success' => true
];

try {
    if ($founder_id) {
        $user_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM user WHERE id = ?");
        $user_stmt->execute([$founder_id]);
        if ($userInfo = $user_stmt->fetch(PDO::FETCH_ASSOC)) {
            $initial_data['userInfo'] = $userInfo;
            $_SESSION['user_info'] = $userInfo; 
        }
    }

    if ($project_id && $founder_id) {
        $project_stmt = $pdo->prepare("SELECT * FROM projet WHERE id = ? AND founder_id = ?");
        $project_stmt->execute([$project_id, $founder_id]);
        
        if ($projectData = $project_stmt->fetch(PDO::FETCH_ASSOC)) {
            $initial_data['projectData'] = $projectData;
            
            // Fetch descriptions correctly
            $utilities_stmt = $pdo->prepare("SELECT utility_name, utility_description FROM utility_token WHERE projet_id = ?");
            $utilities_stmt->execute([$project_id]);
            $initial_data['projectData']['utilities'] = $utilities_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            unset($_SESSION['active_project_id']);
            $project_id = null; 
        }
    }
} catch (PDOException $e) {
    error_log("Database Error in setup.php: " . $e->getMessage());
    $initial_data['success'] = false;
    $initial_data['error'] = 'A database error occurred.';
}

// --- SECURE JSON ENCODING ---
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
try {
    $raw_json = json_encode($initial_data, $flags);
    $safe_initial_data_json = str_replace(
        ["\xe2\x80\xa8", "\xe2\x80\xa9"], 
        ['\u2028', '\u2029'], 
        $raw_json
    );
} catch (Exception $e) {
    $safe_initial_data_json = json_encode(['success' => false, 'error' => 'JSON Encoding Error', 'projectData' => null]);
}
?>
<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-slate-100">
    <div class="w-full max-w-5xl">
        <?php render_main_stepper($current_main_step); ?>

        <div class="w-full max-w-3xl mx-auto">
            <form id="project-form">
                <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl border border-gray-200">
                    <div class="mb-8">
                        <div id="sub-step-indicator" class="mb-2 text-sm font-semibold text-slate-700"></div>
                        <div class="bg-slate-200 rounded-full h-2 w-full">
                            <div id="progress-bar" class="bg-gradient-to-r from-purple-600 to-cyan-500 h-2 rounded-full transition-all" style="width: 0%;"></div>
                        </div>
                    </div>

                    <div id="form-content">
                        <!-- STEP 1: Project Details (Name, Category, Solution) -->
                        <div id="form-steps-container" class="min-h-[350px]">
                            <div class="form-step active">
                                <div class="mb-8">
                                    <label for="project_name" class="form-label">What is the Name of Your Project?</label>
                                    <p class="form-hint mb-4">This is the name your community will know you by. Make it memorable!</p>
                                    <input type="text" id="project_name" name="project_name" class="form-input" placeholder="Type your project name here" required>
                                </div>

                                <div class="mb-8">
                                    <label for="solution" class="form-label">Describe your solution</label>
                                    <p class="form-hint mb-4">Explain how your project works, and what the primary benefit is.</p>
                                    <textarea id="solution" name="solution" rows="5" class="form-textarea" placeholder="Describe your solution..." maxlength="1000" required></textarea>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="selected_category" class="form-label mb-0">Select your category</label>
                                        <button type="button" id="open-category-matrix-btn" class="inline-flex items-center text-xs font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 px-3 py-1.5 rounded-lg transition-all shadow-sm cursor-pointer">
                                            <i data-lucide="layout-grid" class="w-4 h-4 mr-1.5 text-purple-600"></i> Explore Category Matrix
                                        </button>
                                    </div>
                                    <p class="form-hint mb-4">Choose the category that best represents your project’s core activity, or hover/click "Explore Category Matrix" to compare definitions & supply benchmarks.</p>
                                    <select id="selected_category" name="selected_category" class="form-input text-base py-3 px-4 rounded-lg bg-white border border-gray-300 font-medium text-gray-800 shadow-sm cursor-pointer" required>
                                        <option value="" disabled selected>Select a category...</option>
                                        <option value="Layer 1">Layer 1 — Foundational blockchains for building DApps</option>
                                        <option value="Layer 2">Layer 2 — Scaling solutions boosting speed and reducing fees</option>
                                        <option value="DePIN">DePIN — Tokens powering decentralized physical infrastructure</option>
                                        <option value="Payment">Payment — Cryptocurrencies designed for global payments</option>

                                        <option value="Gaming">Gaming — Tokens supporting in-game economies</option>
                                        <option value="Fan Tokens">Fan Tokens — Tokens offering exclusive perks & voting rights</option>
                                        <option value="Marketplaces">Marketplaces — Tokens used in trading digital assets/goods</option>
                                        <option value="AI Agents">AI Agents — Tokens powering AI-based applications</option>
                                        <option value="Decentralized Exchanges">Decentralized Exchanges — Peer-to-peer trading tokens</option>
                                        <option value="Centralized Exchanges">Centralized Exchanges — Utility tokens for exchange ecosystems</option>
                                        <option value="Staking/Yield Farming">Staking/Yield Farming — Tokens generating staking rewards</option>
                                        <option value="Startup Utility Tokens">Startup Utility Tokens — Tokens designed for early adoption</option>
                                    </select>
                                    <div id="category-description-box" class="mt-3 p-3 bg-purple-50 border border-purple-200 rounded-lg text-sm text-purple-900 hidden flex items-center space-x-3">
                                        <i data-lucide="info" class="w-5 h-5 text-purple-600 flex-shrink-0"></i>
                                        <span id="category-description-text"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: Token Utilities -->
                        <div id="utilities-section" class="utilities-section">
                            <h3 class="text-xl font-semibold text-gray-800 mb-2">Select your utility</h3>
                            <p class="form-hint mb-6">Choose the core functions your token will provide. Select all that apply.</p>
                            <div id="utilities-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-4xl">
                            </div>
                        </div>
                    </div>
                </div>
                <div id="navigation-footer" class="bg-slate-50/75 rounded-b-xl px-8 py-5 border-t border-slate-200 flex items-center justify-between -mt-1 shadow-xl">
                    <button type="button" id="prev-btn" class="text-slate-600 font-semibold py-2 px-4 rounded-lg hover:bg-slate-200 transition-all">Back</button>
                    <div id="form-status" class="text-sm mx-4 font-medium text-center flex-1"></div>
                    <button type="button" id="next-btn" class="btn btn-primary">Next</button>
                    <button type="submit" id="submit-btn" class="btn btn-primary hidden">Save & Continue</button>
                </div>
            </form>
        </div>
    </div>
</main>

<div id="custom-utility-modal" class="fixed inset-0 bg-slate-900 bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="modal-content bg-white w-full max-w-md p-6 sm:p-8 rounded-xl shadow-2xl transition-all duration-200 ease-out opacity-0 scale-95">
        <h3 class="text-xl font-semibold text-gray-800 mb-2">Add a Custom Utility</h3>
        <p class="text-gray-500 text-sm mb-6">Define a unique utility for your token.</p>
        <form id="custom-utility-form">
            <div class="mb-4">
                <label for="custom_utility_name" class="block text-sm font-medium text-gray-700 mb-1">Utility Name</label>
                <input type="text" id="custom_utility_name" name="custom_utility_name" class="form-input" placeholder="e.g., Exclusive Content Access" required>
            </div>
            <div class="mb-6">
                <label for="custom_utility_description" class="block text-sm font-medium text-gray-700 mb-1">Brief Description</label>
                <textarea id="custom_utility_description" name="custom_utility_description" rows="3" class="form-textarea" placeholder="Explain what this utility does"></textarea>
            </div>
            <div class="flex items-center justify-end space-x-3">
                <button type="button" id="custom-utility-cancel-btn" class="text-slate-600 font-semibold py-2 px-4 rounded-lg hover:bg-slate-200 transition-all">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Utility</button>
            </div>
        </form>
    </div>
</div>

<!-- CATEGORY MATRIX MODAL -->
<div id="category-matrix-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-[60] hidden">
    <div class="cat-matrix-content bg-white w-full max-w-5xl rounded-2xl shadow-2xl transition-all duration-300 ease-out opacity-0 scale-95 flex flex-col" style="max-height: 90vh;">
        <!-- Header -->
        <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 flex items-center">
                        <i data-lucide="layout-grid" class="w-5 h-5 mr-2 text-purple-600"></i>
                        Category Matrix
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Compare all categories at a glance. Click one to select it.</p>
                </div>
                <button type="button" id="close-category-matrix-btn" class="p-2 rounded-lg hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        <!-- Grid -->
        <div class="p-6 overflow-y-auto flex-1">
            <div id="category-matrix-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- JS will populate -->
            </div>
        </div>
    </div>
</div>

<style>
    .form-label { font-size: 1.25rem; font-weight: 600; color: #374151; }
    .form-hint { font-size: 0.875rem; color: #6B7280; }
    .form-input, .form-textarea { border: 1px solid #D1D5DB; transition: all 0.2s; width: 100%; padding: 0.75rem; border-radius: 0.375rem; }
    .form-input:focus, .form-textarea:focus { border-color: #6D28D9; box-shadow: 0 0 0 2px #EDE9FE; outline: none; }
    .form-step, .utilities-section { display: none; }
    .form-step.active, .utilities-section.active { display: block; animation: fadeIn 0.5s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .utility-box, .category-item { border: 2px solid #e5e7eb; transition: all 0.2s ease-in-out; cursor: pointer; }
    .category-item.selected, .utility-box.selected { border-color: #6D28D9; box-shadow: 0 0 0 2px #EDE9FE; background-color: #faf5ff; }

    /* Category Matrix Card */
    .cat-matrix-card {
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        background: #fff;
        display: flex;
        flex-direction: column;
    }
    .cat-matrix-card:hover {
        border-color: #a78bfa;
        box-shadow: 0 4px 16px rgba(109, 40, 217, 0.1);
        transform: translateY(-2px);
    }
    .cat-matrix-card.active {
        border-color: #6D28D9;
        box-shadow: 0 0 0 3px #EDE9FE;
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
    }
    .cat-matrix-card .cat-icon-wrap {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .cat-matrix-card .cat-supply-badge {
        display: inline-flex; align-items: center;
        font-size: 0.75rem; font-weight: 600;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: #f0fdf4; color: #166534;
        border: 1px solid #bbf7d0;
        margin-top: auto;
    }
    .cat-matrix-card.active .cat-supply-badge {
        background: #ede9fe; color: #5b21b6; border-color: #c4b5fd;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- STATE & UI ELEMENTS ---
    const initialData = <?php echo $safe_initial_data_json; ?>;
    let projectId = <?php echo json_encode($project_id); ?>;
    const backendUrl = <?php echo json_encode($backend_url); ?>;

    const progressBar = document.getElementById('progress-bar');
    const subStepIndicator = document.getElementById('sub-step-indicator');
    const formStepsContainer = document.getElementById('form-steps-container');
    const utilitiesSection = document.getElementById('utilities-section');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');
    const form = document.getElementById('project-form');
    const formStatus = document.getElementById('form-status');
    const categorySelect = document.getElementById('selected_category');
    const categoryDescBox = document.getElementById('category-description-box');
    const categoryDescText = document.getElementById('category-description-text');
    const utilitiesGrid = document.getElementById('utilities-grid');
    const customUtilityModal = document.getElementById('custom-utility-modal');
    const customUtilityForm = document.getElementById('custom-utility-form');
    const customUtilityCancelBtn = document.getElementById('custom-utility-cancel-btn');

    let currentStepIndex = 0; // 0 = Project Details, 1 = Token Utilities
    const TOTAL_INTERNAL_STEPS = 2;
    let selectedCategoryName = '';

    const categories = [ 
        { name: 'Layer 1', definition: 'Foundational blockchains for building decentralized applications.', icon: 'server', supply: '1,000,000,000', color: '#6366f1' }, 
        { name: 'Layer 2', definition: 'Scaling solutions on top of Layer 1 to boost speed and reduce fees.', icon: 'layers', supply: '10,000,000,000', color: '#8b5cf6' }, 
        { name: 'DePIN', definition: 'Tokens powering decentralized physical infrastructure (connectivity, storage, loT).', icon: 'wifi', supply: '1,000,000,000', color: '#06b6d4' }, 
        { name: 'Payment', definition: 'Cryptocurrencies designed for global payments and transactions.', icon: 'credit-card', supply: '100,000,000', color: '#10b981' }, 

        { name: 'Gaming', definition: 'Tokens supporting in-game economies and blockchain-based games.', icon: 'gamepad-2', supply: '1,000,000,000', color: '#ef4444' }, 
        { name: 'Fan Tokens', definition: 'Tokens offering exclusive perks, content, or voting rights for fans.', icon: 'star', supply: '20,000,000', color: '#f97316' }, 
        { name: 'Marketplaces', definition: 'Tokens used in platforms trading digital goods, assets, or services.', icon: 'shopping-cart', supply: '500,000,000', color: '#14b8a6' }, 
        { name: 'AI Agents', definition: 'Tokens powering AI-based applications integrated with blockchain.', icon: 'brain-circuit', supply: '1,000,000,000', color: '#a855f7' }, 
        { name: 'Decentralized Exchanges', definition: 'Tokens used in peer-to-peer trading platforms with no intermediaries.', icon: 'repeat', supply: '1,000,000,000', color: '#3b82f6' }, 
        { name: 'Centralized Exchanges', definition: 'Utility tokens native to centralized exchange ecosystems.', icon: 'building', supply: '200,000,000', color: '#64748b' }, 
        { name: 'Staking/Yield Farming', definition: 'Tokens that generate rewards for users providing liquidity or staking.', icon: 'trending-up', supply: '100,000,000', color: '#22c55e' }, 
        { name: 'Startup Utility Tokens', definition: 'Tokens designed to engage backers and or incentivize early adoption.', icon: 'rocket', supply: '100,000,000', color: '#ec4899' } 
    ];

    const utilitiesData = [ 
        { name: 'Token Buyback', description: 'The protocol uses a portion of its revenue to buy back tokens on the open market, reducing circulating supply.', icon: 'refresh-cw' }, 
        { name: 'Governance', description: 'Token holders can vote on protocol decisions like feature updates, treasury use, or policy changes.', icon: 'scale' }, 
        { name: 'Protocol Activity Rewards', description: 'Users receive rewards tied to their usage of the platform, such as transaction volume or feature use.', icon: 'zap' }, 
        { name: 'Rewards', description: 'Tokens are used to reward users for actions like engagement, referrals, or usage of the platform.', icon: 'award' }, 
        { name: 'Access', description: 'Tokens grant access to premium features, early product access, or gated areas of the protocol.', icon: 'key' }, 
        { name: 'Yield', description: 'Token holders can earn yield through mechanisms like staking or liquidity provisioning.', icon: 'trending-up' }, 
        { name: 'Network Security', description: 'Validators or node operators stake tokens to secure the network and earn rewards.', icon: 'shield' }, 
        { name: 'Payment', description: 'The token is used as a native currency to pay for services or transactions within the platform.', icon: 'credit-card' }, 
        { name: 'Staking', description: 'Users can lock tokens for a fixed period to earn rewards or strengthen their alignment with the project.', icon: 'anchor' }, 
        { name: 'Gas Token', description: 'The token is required to pay for transaction or execution fees on the protocol.', icon: 'fuel' }, 
        { name: 'Fee Discounts', description: 'Holding tokens gives users reduced fees when trading or using services on the platform.', icon: 'percent' }, 
        { name: 'Collateralisation', description: 'The token can be used as collateral to borrow assets or access credit lines in DeFi.', icon: 'layers' } 
    ];

    const hideCustomUtilityModal = () => {
        if (customUtilityModal) {
            const modalContent = customUtilityModal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.classList.remove('opacity-100', 'scale-100');
                modalContent.classList.add('opacity-0', 'scale-95');
            }
            setTimeout(() => {
                customUtilityModal.classList.add('hidden');
                if (customUtilityForm) customUtilityForm.reset();
            }, 200);
        }
    };

    const createUtilityBox = (utility, isSelected = false) => {
        const box = document.createElement('div');
        box.className = 'utility-box p-4 rounded-lg flex items-start space-x-4';
        if (isSelected) box.classList.add('selected');

        const descriptionText = utility.description || 'No description available.';

        box.innerHTML = `
            <input type="checkbox" name="utilities[]" value="${utility.name}" class="hidden" ${isSelected ? 'checked' : ''}>
            <div class="flex-shrink-0 text-purple-600 mt-1"><i data-lucide="${utility.icon || 'sparkles'}" class="w-6 h-6"></i></div>
            <div>
                <h4 class="font-semibold text-gray-800 utility-name">${utility.name}</h4>
                <p class="text-sm text-gray-500 utility-description">${descriptionText}</p>
            </div>
            <i data-lucide="check" class="w-5 h-5 ml-auto flex-shrink-0" style="display: ${isSelected ? 'block' : 'none'}; color: var(--tookle-purple);"></i>`;
        
        box.addEventListener('click', () => {
            const checkbox = box.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            box.classList.toggle('selected', checkbox.checked);
            box.querySelector('[data-lucide="check"]').style.display = checkbox.checked ? 'block' : 'none';
        });
        return box;
    };

    const renderUtilities = (savedUtilities = []) => {
        if (!utilitiesGrid) return;
        utilitiesGrid.innerHTML = '';
        const savedUtilitiesMap = new Map();

        savedUtilities.forEach(u => {
            if (typeof u === 'string') {
                savedUtilitiesMap.set(u, null);
            } else {
                savedUtilitiesMap.set(u.utility_name, u.utility_description);
            }
        });

        utilitiesData.forEach(utility => {
            const isSelected = savedUtilitiesMap.has(utility.name);
            const box = createUtilityBox(utility, isSelected);
            utilitiesGrid.appendChild(box);
        });

        savedUtilitiesMap.forEach((desc, name) => {
            const isStandard = utilitiesData.some(u => u.name === name);
            if (!isStandard) {
                const customUtility = { 
                    name: name, 
                    description: desc || 'Custom utility', 
                    icon: 'sparkles' 
                };
                const box = createUtilityBox(customUtility, true);
                utilitiesGrid.appendChild(box);
            }
        });

        const addCard = document.createElement('div');
        addCard.className = 'utility-box p-4 rounded-lg flex flex-col items-center justify-center text-center cursor-pointer border-2 border-dashed hover:bg-gray-50 hover:border-purple-400';
        addCard.id = 'add-utility-card';
        addCard.innerHTML = `<div class="text-gray-400"><i data-lucide="plus-circle" class="w-10 h-10"></i></div><h4 class="font-semibold text-gray-600 mt-2">Add Custom Utility</h4>`;
        utilitiesGrid.appendChild(addCard);

        addCard.addEventListener('click', () => {
            if (customUtilityModal) {
                customUtilityModal.classList.remove('hidden');
                setTimeout(() => {
                    const modalContent = customUtilityModal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.classList.remove('opacity-0', 'scale-95');
                        modalContent.classList.add('opacity-100', 'scale-100');
                    }
                }, 10);
            }
        });
        lucide.createIcons();
    };

    const updateCategoryDescription = (catName) => {
        const cat = categories.find(c => c.name === catName);
        if (cat && categoryDescBox && categoryDescText) {
            categoryDescText.textContent = `${cat.name}: ${cat.definition}`;
            categoryDescBox.classList.remove('hidden');
        } else if (categoryDescBox) {
            categoryDescBox.classList.add('hidden');
        }
    };

    const updateUI = () => {
        if (formStepsContainer) formStepsContainer.style.display = currentStepIndex === 0 ? 'block' : 'none';
        if (utilitiesSection) utilitiesSection.style.display = currentStepIndex === 1 ? 'block' : 'none';
        if (currentStepIndex === 1 && utilitiesSection) utilitiesSection.classList.add('active');

        const currentStepNumber = currentStepIndex + 1;
        const percentage = (currentStepNumber / TOTAL_INTERNAL_STEPS) * 100;
        if (progressBar) progressBar.style.width = `${percentage}%`;
        if (subStepIndicator) subStepIndicator.textContent = `Step ${currentStepNumber} / ${TOTAL_INTERNAL_STEPS} of Describe Project`;

        if (currentStepIndex === 0) {
            if (nextBtn) nextBtn.classList.remove('hidden');
            if (submitBtn) submitBtn.classList.add('hidden');
            if (prevBtn) prevBtn.style.visibility = 'hidden';
        } else {
            if (nextBtn) nextBtn.classList.add('hidden');
            if (submitBtn) submitBtn.classList.remove('hidden');
            if (prevBtn) prevBtn.style.visibility = 'visible';
        }
    };

    const renderCategories = (selectedCatName = '') => {
        selectedCategoryName = selectedCatName || (categorySelect ? categorySelect.value : '');
        if (categorySelect && selectedCategoryName) {
            categorySelect.value = selectedCategoryName;
            updateCategoryDescription(selectedCategoryName);
        }
    };

    if (categorySelect) {
        categorySelect.addEventListener('change', (e) => {
            selectedCategoryName = e.target.value;
            updateCategoryDescription(selectedCategoryName);
        });
    }

    // --- CATEGORY MATRIX MODAL ---
    const catMatrixModal = document.getElementById('category-matrix-modal');
    const catMatrixGrid = document.getElementById('category-matrix-grid');
    const openMatrixBtn = document.getElementById('open-category-matrix-btn');
    const closeMatrixBtn = document.getElementById('close-category-matrix-btn');

    const openCategoryMatrix = () => {
        if (!catMatrixModal) return;
        // Build the grid
        catMatrixGrid.innerHTML = '';
        categories.forEach(cat => {
            const isActive = cat.name === selectedCategoryName;
            const card = document.createElement('div');
            card.className = `cat-matrix-card${isActive ? ' active' : ''}`;
            card.dataset.catName = cat.name;
            card.innerHTML = `
                <div class="flex items-start space-x-3 mb-3">
                    <div class="cat-icon-wrap" style="background: ${cat.color}15;">
                        <i data-lucide="${cat.icon}" class="w-5 h-5" style="color: ${cat.color};"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-gray-900 text-sm leading-tight">${cat.name}</h4>
                        ${isActive ? '<span class="text-[10px] font-semibold text-purple-600 uppercase tracking-wide">Selected</span>' : ''}
                    </div>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed mb-3 flex-1">${cat.definition}</p>
                <div>
                    <span class="cat-supply-badge">
                        <i data-lucide="coins" class="w-3 h-3 mr-1"></i>
                        Supply: ${cat.supply}
                    </span>
                </div>
            `;

            card.addEventListener('click', () => {
                selectedCategoryName = cat.name;
                if (categorySelect) {
                    categorySelect.value = cat.name;
                }
                updateCategoryDescription(cat.name);
                closeCategoryMatrix();
            });

            catMatrixGrid.appendChild(card);
        });

        lucide.createIcons();
        catMatrixModal.classList.remove('hidden');
        setTimeout(() => {
            const content = catMatrixModal.querySelector('.cat-matrix-content');
            if (content) {
                content.classList.remove('opacity-0', 'scale-95');
                content.classList.add('opacity-100', 'scale-100');
            }
        }, 10);
    };

    const closeCategoryMatrix = () => {
        if (!catMatrixModal) return;
        const content = catMatrixModal.querySelector('.cat-matrix-content');
        if (content) {
            content.classList.remove('opacity-100', 'scale-100');
            content.classList.add('opacity-0', 'scale-95');
        }
        setTimeout(() => {
            catMatrixModal.classList.add('hidden');
        }, 200);
    };

    if (openMatrixBtn) openMatrixBtn.addEventListener('click', openCategoryMatrix);
    if (closeMatrixBtn) closeMatrixBtn.addEventListener('click', closeCategoryMatrix);
    if (catMatrixModal) {
        catMatrixModal.addEventListener('click', (e) => {
            if (e.target === catMatrixModal) closeCategoryMatrix();
        });
    }

    nextBtn.addEventListener('click', () => {
        if (currentStepIndex === 0) {
            const nameInput = document.getElementById('project_name');
            const solutionInput = document.getElementById('solution');

            if (!nameInput.value.trim()) {
                nameInput.focus();
                formStatus.textContent = 'Please enter a project name.';
                formStatus.className = 'text-red-600 font-medium';
                return;
            }
            if (!categorySelect.value) {
                formStatus.textContent = 'Please select a category.';
                formStatus.className = 'text-red-600 font-medium';
                return;
            }
            if (!solutionInput.value.trim()) {
                solutionInput.focus();
                formStatus.textContent = 'Please describe your solution.';
                formStatus.className = 'text-red-600 font-medium';
                return;
            }

            formStatus.textContent = '';
            currentStepIndex = 1;
            renderUtilities(initialData.projectData?.utilities || []);
            updateUI();
        }
    });

    prevBtn.addEventListener('click', () => {
        if (currentStepIndex === 1) {
            currentStepIndex = 0;
            updateUI();
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (document.querySelectorAll('.utility-box.selected').length === 0) {
            formStatus.textContent = 'Please select at least one token utility.';
            formStatus.className = "text-red-600 font-medium";
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="animate-spin w-5 h-5"></i>';
        lucide.createIcons();
        formStatus.textContent = 'Saving...';
        formStatus.className = "text-gray-600 font-medium";

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        data.projet_id = projectId;
        data.selected_category = selectedCategoryName;

        const selectedUtilities = [];
        document.querySelectorAll('.utility-box.selected').forEach(box => {
            const nameElem = box.querySelector('.utility-name');
            const descElem = box.querySelector('.utility-description');
            if (nameElem) {
                const icon = box.querySelector('[data-lucide]')?.getAttribute('data-lucide') || '';
                selectedUtilities.push({
                    name: nameElem.textContent.trim(),
                    description: descElem ? descElem.textContent.trim() : '',
                    is_custom: (icon === 'sparkles') ? 1 : 0
                });
            }
        });
        data.utilities = selectedUtilities;

        try {
            const response = await fetch(backendUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const errorResult = await response.json().catch(() => ({ error: `Server responded with status: ${response.status}` }));
                throw new Error(errorResult.error || 'An unknown server error occurred.');
            }

            const result = await response.json();

            if (result.success) {
                if (result.projet_id) projectId = result.projet_id;
                formStatus.textContent = '';
                window.location.href = '<?= get_url('tokenname') ?>';
            } else {
                throw new Error(result.error || 'An unknown error occurred.');
            }
        } catch (error) {
            console.error('Error saving form:', error);
            formStatus.textContent = `Error: ${error.message}`;
            formStatus.className = "text-red-600 font-medium";
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save & Continue';
        }
    });

    if (customUtilityCancelBtn) {
        customUtilityCancelBtn.addEventListener('click', hideCustomUtilityModal);
    }
    if (customUtilityForm) {
        customUtilityForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const name = e.target.elements.custom_utility_name.value.trim();
            const desc = e.target.elements.custom_utility_description.value.trim();
            if (!name) return;
            const newBox = createUtilityBox({ name: name, description: desc, icon: 'sparkles' }, true);
            utilitiesGrid.insertBefore(newBox, document.getElementById('add-utility-card'));
            lucide.createIcons();
            hideCustomUtilityModal();
        });
    }

    const initialize = () => {
        try {
            if (initialData.projectData) {
                if (initialData.projectData.project_name) {
                    document.getElementById('project_name').value = initialData.projectData.project_name;
                }
                if (initialData.projectData.solution) {
                    document.getElementById('solution').value = initialData.projectData.solution;
                }
                if (initialData.projectData.selected_category) {
                    selectedCategoryName = initialData.projectData.selected_category;
                }
            }
        } catch (e) {
            console.error("Initialization error:", e);
        }

        renderCategories(selectedCategoryName);
        renderUtilities(initialData.projectData?.utilities || []);
        currentStepIndex = 0;
        updateUI();
        lucide.createIcons();
    };

    initialize();
});
</script>