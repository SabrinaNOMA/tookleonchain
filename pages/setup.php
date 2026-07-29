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
                        <div id="form-steps-container" class="min-h-[350px]">
                            <div class="form-step">
                                <label for="project_name" class="form-label">What is the Name of Your Project?</label>
                                <p class="form-hint mb-4">This is the name your community will know you by. Make it memorable!</p>
                                <input type="text" id="project_name" name="project_name" class="form-input" placeholder="Type your project name here" required>
                            </div>

                            <div class="form-step">
                                <div class="mb-8">
                                    <label for="pain_point" class="form-label">What Problem Are You Solving?</label>
                                    <p class="form-hint mb-4">Who is affected, and why is it important to solve?</p>
                                    <textarea id="pain_point" name="pain_point" rows="6" class="form-textarea" placeholder="Describe the specific pain-point..." maxlength="1000" required></textarea>
                                </div>
                                <div>
                                    <label for="solution" class="form-label">How Are You Solving It?</label>
                                    <p class="form-hint mb-4">Explain how your project works, and what the primary benefit is.</p>
                                    <textarea id="solution" name="solution" rows="6" class="form-textarea" placeholder="Describe your solution..." maxlength="1000" required></textarea>
                                </div>
                            </div>

                            <div class="form-step">
                                <div class="mb-8">
                                    <label for="competitive_advantage" class="form-label">What is Your Competitive Advantage?</label>
                                    <p class="form-hint mb-4">Highlight what makes you different: unique tech, better UX, partnerships, etc.</p>
                                    <textarea id="competitive_advantage" name="competitive_advantage" rows="6" class="form-textarea" placeholder="Describe what makes your solution unique and defensible..." maxlength="1000" required></textarea>
                                </div>
                                <div>
                                    <label for="industry_focus" class="form-label">What is Your Industry Focus?</label>
                                    <p class="form-hint mb-4">Select the sector that best represents your project’s core activity.</p>
                                    <select id="industry_focus" name="industry_focus" class="form-input" required>
                                        <option value="" disabled selected>Select an industry...</option>
                                        <option value="Artificial Intelligence">Artificial Intelligence</option>
                                        <option value="Blockchain & Web3">Blockchain & Web3</option>
                                        <option value="FinTech">FinTech</option>
                                        <option value="HealthTech">HealthTech</option>
                                        <option value="BioTech">BioTech</option>
                                        <option value="CleanTech">CleanTech</option>
                                        <option value="ClimateTech">ClimateTech</option>
                                        <option value="AgriTech">AgriTech</option>
                                        <option value="Mobility & Transportation">Mobility & Transportation</option>
                                        <option value="Cybersecurity">Cybersecurity</option>
                                        <option value="Cloud & DevOps">Cloud & DevOps</option>
                                        <option value="Data & Analytics">Data & Analytics</option>
                                        <option value="SaaS & Enterprise Software">SaaS & Enterprise Software</option>
                                        <option value="EdTech">EdTech</option>
                                        <option value="PropTech">PropTech</option>
                                        <option value="Gaming & Entertainment">Gaming & Entertainment</option>
                                        <option value="SpaceTech">SpaceTech</option>
                                        <option value="IoT & Hardware">IoT & Hardware</option>
                                        <option value="Robotics & Automation">Robotics & Automation</option>
                                        <option value="Quantum Computing">Quantum Computing</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="analysis-section" class="analysis-section">
                            <div id="analysis-loader" class="flex flex-col items-center justify-center text-center p-8">
                                <i data-lucide="loader-2" class="w-12 h-12 text-purple-600 animate-spin"></i>
                                <p class="mt-4 text-gray-600 font-semibold">Analyzing your project...</p>
                                <p class="text-sm text-gray-500">This may take a moment.</p>
                            </div>
                            <div id="analysis-results" class="hidden space-y-8">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Analysis Complete</h3>
                                    <p class="text-gray-600 text-sm mb-6">Based on your inputs, TOOKLE analyzed similar projects and suggests the following tokenomics category.</p>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Tokenomics Category</h3>
                                    <div id="category-list" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                                    <input type="hidden" id="selected_category" name="selected_category" value="">
                                </div>
                            </div>
                        </div>

                        <div id="utilities-section" class="utilities-section">
                            <label class="form-label">Select Your Token's Utilities</label>
                            <p class="form-hint mb-4">Choose the core functions your token will provide. Select all that apply.</p>
                            <div id="utilities-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-4xl">
                            </div>
                        </div>
                    </div>
                </div>
                 <div id="navigation-footer" class="bg-slate-50/75 rounded-b-xl px-8 py-5 border-t border-slate-200 flex items-center justify-between -mt-1 shadow-xl">
                    <button type="button" id="prev-btn" class="text-slate-600 font-semibold py-2 px-4 rounded-lg hover:bg-slate-200 transition-all">Back</button>
                    <div id="form-status" class="text-sm mx-4 font-medium text-center flex-1"></div>
                    <button type="button" id="next-btn" class="btn btn-primary">Next</button>
                    <button type="button" id="analyze-btn" class="btn btn-primary hidden">Analyze Project</button>
                    <button type="submit" id="submit-btn" class="btn btn-primary hidden">Save Project</button>
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

<style>
    .form-label { font-size: 1.25rem; font-weight: 600; color: #374151; }
    .form-hint { font-size: 0.875rem; color: #6B7280; }
    .form-input, .form-textarea { border: 1px solid #D1D5DB; transition: all 0.2s; width: 100%; padding: 0.75rem; border-radius: 0.375rem; }
    .form-input:focus, .form-textarea:focus { border-color: #6D28D9; box-shadow: 0 0 0 2px #EDE9FE; outline: none; }
    .form-step, .analysis-section, .utilities-section { display: none; }
    .form-step.active, .analysis-section.active, .utilities-section.active { display: block; animation: fadeIn 0.5s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .utility-box, .category-item { border: 2px solid #e5e7eb; transition: all 0.2s ease-in-out; cursor: pointer; }
    .category-item.selected, .utility-box.selected { border-color: #6D28D9; box-shadow: 0 0 0 2px #EDE9FE; background-color: #faf5ff; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- STATE & UI ELEMENTS ---
    const initialData = <?php echo $safe_initial_data_json; ?>;
    let projectId = <?php echo json_encode($project_id); ?>;
    const backendUrl = <?php echo json_encode($backend_url); ?>;
    
    // FIX: Removed projectFolder usage completely.
    // const projectFolder = 'tookle2'; 

    const progressBar = document.getElementById('progress-bar');
    const subStepIndicator = document.getElementById('sub-step-indicator');
    const formStepsContainer = document.getElementById('form-steps-container');
    const analysisSection = document.getElementById('analysis-section');
    const utilitiesSection = document.getElementById('utilities-section');
    const formSteps = Array.from(document.querySelectorAll('.form-step'));
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const analyzeBtn = document.getElementById('analyze-btn');
    const submitBtn = document.getElementById('submit-btn');
    const form = document.getElementById('project-form');
    const formStatus = document.getElementById('form-status');
    const analysisLoader = document.getElementById('analysis-loader');
    const analysisResults = document.getElementById('analysis-results');
    const categoryListContainer = document.getElementById('category-list');
    const hiddenCategoryInput = document.getElementById('selected_category');
    const utilitiesGrid = document.getElementById('utilities-grid');
    const successModal = document.getElementById('success-modal');
    const customUtilityModal = document.getElementById('custom-utility-modal');
    const customUtilityForm = document.getElementById('custom-utility-form');
    const customUtilityCancelBtn = document.getElementById('custom-utility-cancel-btn');

    let formStepIndex = 0;
    let describeView = 'form'; 
    let selectedCategoryName = '';
    
    const internalSteps = ["Project Name", "Problem & Solution", "Competitive Advantage", "Project Analysis", "Token Utilities"];
    const TOTAL_INTERNAL_STEPS = internalSteps.length;
    
    const categories = [ { name: 'Layer 1', definition: 'Foundational blockchains for building decentralized applications.', icon: 'server' }, { name: 'Layer 2', definition: 'Scaling solutions on top of Layer 1 to boost speed and reduce fees.', icon: 'layers' }, { name: 'DePIN', definition: 'Tokens powering decentralized physical infrastructure (connectivity, storage, loT).', icon: 'wifi' }, { name: 'Payment', definition: 'Cryptocurrencies designed for global payments and transactions.', icon: 'credit-card' }, { name: 'Meme Tokens', definition: 'Community-driven tokens with viral or cultural appeal.', icon: 'smile' }, { name: 'Gaming', definition: 'Tokens supporting in-game economies and blockchain-based games.', icon: 'gamepad-2' }, { name: 'Fan Tokens', definition: 'Tokens offering exclusive perks, content, or voting rights for fans.', icon: 'star' }, { name: 'Marketplaces', definition: 'Tokens used in platforms trading digital goods, assets, or services.', icon: 'shopping-cart' }, { name: 'AI Agents', definition: 'Tokens powering AI-based applications integrated with blockchain.', icon: 'brain-circuit' }, { name: 'Decentralized Exchanges', definition: 'Tokens used in peer-to-peer trading platforms with no intermediaries.', icon: 'repeat' }, { name: 'Centralized Exchanges', definition: 'Utility tokens native to centralized exchange ecosystems.', icon: 'building' }, { name: 'Staking/Yield Farming', definition: 'Tokens that generate rewards for users providing liquidity or staking.', icon: 'trending-up' }, { name: 'Startup Utility Tokens', definition: 'Tokens designed to engage backers and or incentivize early adoption.', icon: 'rocket' } ];
    const utilitiesData = [ { name: 'Token Buyback', description: 'The protocol uses a portion of its revenue to buy back tokens on the open market, reducing circulating supply.', icon: 'refresh-cw' }, { name: 'Governance', description: 'Token holders can vote on protocol decisions like feature updates, treasury use, or policy changes.', icon: 'scale' }, { name: 'Protocol Activity Rewards', description: 'Users receive rewards tied to their usage of the platform, such as transaction volume or feature use.', icon: 'zap' }, { name: 'Rewards', description: 'Tokens are used to reward users for actions like engagement, referrals, or usage of the platform.', icon: 'award' }, { name: 'Access', description: 'Tokens grant access to premium features, early product access, or gated areas of the protocol.', icon: 'key' }, { name: 'Yield', description: 'Token holders can earn yield through mechanisms like staking or liquidity provisioning.', icon: 'trending-up' }, { name: 'Network Security', description: 'Validators or node operators stake tokens to secure the network and earn rewards.', icon: 'shield' }, { name: 'Payment', description: 'The token is used as a native currency to pay for services or transactions within the platform.', icon: 'credit-card' }, { name: 'Staking', description: 'Users can lock tokens for a fixed period to earn rewards or strengthen their alignment with the project.', icon: 'anchor' }, { name: 'Gas Token', description: 'The token is required to pay for transaction or execution fees on the protocol.', icon: 'fuel' }, { name: 'Fee Discounts', description: 'Holding tokens gives users reduced fees when trading or using services on the platform.', icon: 'percent' }, { name: 'Collateralisation', description: 'The token can be used as collateral to borrow assets or access credit lines in DeFi.', icon: 'layers' } ];

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

    const updateUI = () => {
        formStepsContainer.style.display = describeView === 'form' ? 'block' : 'none';
        analysisSection.style.display = describeView === 'analysis' ? 'block' : 'none';
        utilitiesSection.style.display = describeView === 'utilities' ? 'block' : 'none';

        let currentStepNumber = 0;
        if (describeView === 'form') {
            currentStepNumber = formStepIndex + 1;
            formSteps.forEach((step, index) => step.classList.toggle('active', index === formStepIndex));
        } else if (describeView === 'analysis') {
            currentStepNumber = 4;
            analysisLoader.style.display = 'none';
            analysisResults.style.display = 'block';
        } else if (describeView === 'utilities') {
            currentStepNumber = 5;
        }
        
        const percentage = TOTAL_INTERNAL_STEPS > 0 ? (currentStepNumber / TOTAL_INTERNAL_STEPS) * 100 : 0;
        progressBar.style.width = `${percentage}%`;
        subStepIndicator.textContent = `Step ${currentStepNumber} / ${TOTAL_INTERNAL_STEPS} of Describe Project`;

        const isLastFormStep = describeView === 'form' && formStepIndex === formSteps.length - 1;
        const isUtilitiesStep = describeView === 'utilities';
        nextBtn.classList.toggle('hidden', isLastFormStep || isUtilitiesStep);
        analyzeBtn.classList.toggle('hidden', !isLastFormStep);
        submitBtn.classList.toggle('hidden', !isUtilitiesStep);
        prevBtn.style.visibility = (describeView === 'form' && formStepIndex === 0) ? 'hidden' : 'visible';
    };
    
    const createUtilityBox = (utility, isSelected = false) => {
        const box = document.createElement('div');
        box.className = 'utility-box p-4 rounded-lg flex items-start space-x-4';
        if (isSelected) box.classList.add('selected');
        
        const descriptionText = utility.description || 'No description available.';
        
        box.innerHTML = `
            <input type="checkbox" name="utilities[]" value="${utility.name}" class="hidden" ${isSelected ? 'checked' : ''}>
            <div class="flex-shrink-0 text-purple-600 mt-1"><i data-lucide="${utility.icon}" class="w-6 h-6"></i></div>
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

    const renderCategories = (recommendedCategoryName) => {
        selectedCategoryName = recommendedCategoryName;
        hiddenCategoryInput.value = selectedCategoryName;
        categoryListContainer.innerHTML = '';
        categories.forEach(category => {
            const isRecommended = category.name === recommendedCategoryName;
            const item = document.createElement('div');
            item.className = `category-item p-4 rounded-lg cursor-pointer ${isRecommended ? 'selected' : ''}`;
            item.dataset.categoryName = category.name;
            item.innerHTML = `<div class="flex justify-between items-start"><div class="flex items-start space-x-3"><i data-lucide="${category.icon}" class="w-5 h-5 mt-1 text-gray-500"></i><div><h4 class="font-semibold text-gray-800 category-name">${category.name}</h4><p class="text-sm text-gray-500">${category.definition}</p></div></div><i data-lucide="check" class="w-5 h-5 ml-4 flex-shrink-0" style="display: ${isRecommended ? 'block' : 'none'}; color: var(--tookle-purple);"></i></div> ${isRecommended ? '<div class="text-xs font-semibold text-purple-600 mt-2 pl-8">RECOMMENDED FOR YOU</div>' : ''}`;
            categoryListContainer.appendChild(item);
            item.addEventListener('click', () => {
                selectedCategoryName = category.name;
                hiddenCategoryInput.value = selectedCategoryName;
                document.querySelectorAll('.category-item').forEach(el => {
                    el.classList.remove('selected');
                    el.querySelector('[data-lucide="check"]').style.display = 'none';
                });
                item.classList.add('selected');
                item.querySelector('[data-lucide="check"]').style.display = 'block';
            });
        });
        lucide.createIcons();
    };
    
    const performAnalysis = async () => {
        describeView = 'analysis';
        updateUI();
        analysisLoader.style.display = 'flex';
        analysisResults.style.display = 'none';
        
        await new Promise(resolve => setTimeout(resolve, 1500)); 

        const recommendedCategory = { name: 'DePIN' };
        renderCategories(recommendedCategory.name);
        
        analysisLoader.style.display = 'none';
        analysisResults.style.display = 'block';
        lucide.createIcons();
    };

    nextBtn.addEventListener('click', () => {
        if (describeView === 'analysis') {
            describeView = 'utilities';
            renderUtilities(); 
            updateUI();
        } else if (describeView === 'form' && formStepIndex < formSteps.length - 1) {
            const currentStepFields = formSteps[formStepIndex].querySelectorAll('[required]');
            let allValid = true;
            currentStepFields.forEach(field => { if (!field.value.trim()) { field.focus(); allValid = false; }});
            if (allValid) {
                formStepIndex++;
                updateUI();
            }
        }
    });

    prevBtn.addEventListener('click', () => {
        if (describeView === 'utilities') {
            describeView = 'analysis';
        } else if (describeView === 'analysis') {
             describeView = 'form';
             formStepIndex = formSteps.length - 1;
        }
        else if (describeView === 'form' && formStepIndex > 0) {
            formStepIndex--;
        }
        updateUI();
    });

    analyzeBtn.addEventListener('click', () => {
        const currentStepFields = formSteps[formStepIndex].querySelectorAll('[required]');
        let allValid = true;
        currentStepFields.forEach(field => { if (!field.value.trim()) { field.focus(); allValid = false; }});
        if (allValid) performAnalysis();
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
            const name = box.querySelector('.utility-name').textContent;
            const description = box.querySelector('.utility-description').textContent;
            const icon = box.querySelector('[data-lucide]').getAttribute('data-lucide');
            
            selectedUtilities.push({
                name: name,
                description: description,
                is_custom: (icon === 'sparkles') ? 1 : 0
            });
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
                if(result.projet_id) projectId = result.projet_id; 
                
                formStatus.textContent = '';
                if(successModal) {
                    successModal.classList.remove('hidden');
                    setTimeout(() => successModal.querySelector('.modal-content')?.classList.add('scale-100', 'opacity-100'), 10);
                } else {
                     // FIX: Relative redirection
                     window.location.href = 'tokenname';
                }
            } else {
                throw new Error(result.error || 'An unknown error occurred.');
            }
        } catch (error) {
            console.error('Error saving form:', error);
            formStatus.textContent = `Error: ${error.message}`;
            formStatus.className = "text-red-600 font-medium";
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Project';
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
                Object.keys(initialData.projectData).forEach(key => {
                    const input = document.getElementById(key);
                    if (input) {
                         input.value = initialData.projectData[key] || '';
                    }
                });
                
                if(initialData.projectData.selected_category) {
                   renderCategories(initialData.projectData.selected_category);
                }
                
                renderUtilities(initialData.projectData?.utilities || []);
                
                const hasName = initialData.projectData.project_name && initialData.projectData.project_name.trim() !== "";
                const hasCategory = !!initialData.projectData.selected_category;

                if (hasCategory && hasName) {
                    describeView = 'utilities';
                } else {
                    describeView = 'form';
                    formStepIndex = 0; 
                }
            }
        } catch(e) {
            console.error("Initialization error:", e);
            describeView = 'form';
            formStepIndex = 0;
        }
        updateUI();
        lucide.createIcons();
    };

    initialize();
});
</script>