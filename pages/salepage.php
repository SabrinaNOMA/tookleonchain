<?php
// pages/salepage.php

// 1. Session & DB Setup
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/db.php';

$finalProjectData = [];
$page_error = null;
$agreement_text_content = '[]';

try {
    if (!isset($pdo)) {
        throw new Exception("Database connection is not available.");
    }

    if (!isset($_SESSION['selected_project_id']) || !isset($_SESSION['selected_sale_name'])) {
        throw new Exception('No project sale has been selected. Please return to the discovery page.');
    }
    $projectId = $_SESSION['selected_project_id'];
    $saleName = $_SESSION['selected_sale_name'];

    // 2. Fetch Project & Sale Data (Including scenario_version_id)
    // UPDATE: Removed "AND tsp.status = 'live'" from SQL to allow Founder Preview logic below
    $sql = "
        SELECT p.*, tsp.* FROM projet p
        JOIN token_sale_pages tsp ON p.id = tsp.project_id
        WHERE p.id = :project_id AND tsp.sale_name = :sale_name
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['project_id' => $projectId, 'sale_name' => $saleName]);
    $projectData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$projectData) {
        throw new Exception("The selected sale page could not be found.");
    }

    // 2b. ACCESS CONTROL: Enforce Live Status (unless Founder)
    // This allows the Founder to preview the content even if status is 'draft' or 'ended'
    $status = strtolower($projectData['status'] ?? 'draft');
    $isLive = ($status === 'live');
    $currentUserId = $_SESSION['user_id'] ?? null;
    $isFounder = ($currentUserId && $currentUserId == ($projectData['founder_id'] ?? null));

    if (!$isLive && !$isFounder) {
        throw new Exception("The selected sale page could not be found or is no longer live.");
    }

    // 3. Helper for JSON decoding
    function decodeJsonSafely($jsonString, $default = []) {
        if ($jsonString === null || $jsonString === '') return $default;
        $decoded = json_decode($jsonString, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $default;
    }

    // 4. Decode Standard JSON Fields
    $jsonFields = ['general_images_json', 'community_metrics_json', 'value_props_json', 'team_json', 'partners_json', 'faqs_json', 'socials_json', 'project_roadmap_json', 'sale_terms_json'];
    foreach ($jsonFields as $field) {
        $projectData[$field] = decodeJsonSafely($projectData[$field] ?? null);
    }
    
    // 5. Fetch Utilities & Compliance
    $stmt_utils = $pdo->prepare("SELECT utility_name as name, utility_description as description FROM utility_token WHERE projet_id = :project_id");
    $stmt_utils->execute(['project_id' => $projectId]);
    $projectData['token_utilities_json'] = $stmt_utils->fetchAll(PDO::FETCH_ASSOC);

    $stmt_comp = $pdo->prepare("SELECT * FROM compliance_settings WHERE projet_id = :project_id");
    $stmt_comp->execute(['project_id' => $projectId]);
    $projectData['complianceSettings'] = $stmt_comp->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // 6. Merge Sale Terms (TSP Columns take precedence for Sale Params)
    $saleTerms = $projectData['sale_terms_json'] ?? [];
    
    $saleTerms['soft_cap'] = $projectData['soft_cap_usd'];
    $saleTerms['hard_cap'] = $projectData['hard_cap_usd']; 
    $saleTerms['min_purchase'] = $projectData['min_investment_usd'];
    $saleTerms['max_purchase'] = $projectData['max_investment_usd'];
    $saleTerms['round_name'] = $projectData['sale_name'];
    
    // Fallback for price if not set in JSON terms
    if (!isset($saleTerms['round_price']) && !empty($projectData['calculated_price_tge'])) {
        $saleTerms['round_price'] = $projectData['calculated_price_tge'];
    }

    $projectData['selectedRoundDetails'] = $saleTerms;

    // Default supply/type from DB, might be overridden by Scenario below
    if (isset($saleTerms['total_supply'])) { $projectData['supply_value'] = $saleTerms['total_supply']; }
    if (isset($saleTerms['type_supply'])) { $projectData['type_supply'] = $saleTerms['type_supply']; }

    // 7. --- SCENARIO LOGIC: Fetch Vesting & Tokenomics from Frozen Version ---
    $vestingSchedules = [];
    $scenarioId = $projectData['scenario_version_id'] ?? null;

    if ($scenarioId) {
        // A. Fetch Frozen Snapshot
        $stmt_ver = $pdo->prepare("SELECT data FROM scenario_version WHERE id = ?");
        $stmt_ver->execute([$scenarioId]);
        $rawJson = $stmt_ver->fetchColumn();
        
        if ($rawJson) {
            $snapshot = json_decode($rawJson, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                // Fetch version label for context/display
                $stmt_label = $pdo->prepare("SELECT version_label FROM scenario_version WHERE id = ?");
                $stmt_label->execute([$scenarioId]);
                $versionLabel = $stmt_label->fetchColumn();
                $projectData['scenario_label'] = $versionLabel;

                // B. Extract Core Params (Supply, Price, Target Raise)
                $core = $snapshot['core_params'] ?? [];
                
                // Override project globals so charts/metrics reflect the scenario
                $supplyValue = floatval($core['supply_value'] ?? $core['initialTokenSupply'] ?? $projectData['supply_value'] ?? 0);
                $tgePrice = floatval($core['calculated_price_tge'] ?? $core['tgePrice'] ?? $projectData['calculated_price_tge'] ?? 0);
                $targetRaise = floatval($core['target_raise'] ?? $core['target_raise_usd'] ?? $projectData['target_raise_usd'] ?? 0);

                $projectData['supply_value'] = $supplyValue;
                $projectData['type_supply'] = $core['type_supply'] ?? $projectData['type_supply'];
                // Update calculated price for consistent display, unless overriden by specific round price
                if ($tgePrice > 0) $projectData['calculated_price_tge'] = $tgePrice;

                // C. Build Vesting Schedules
                if (!empty($snapshot['vesting']) && is_array($snapshot['vesting'])) {
                    foreach ($snapshot['vesting'] as $item) {
                        $name = $item['vesting_block_name'] ?? $item['round_name'] ?? $item['tranche_name'] ?? 'Unknown';
                        
                        $vestingSchedules[] = [
                            'category' => $name,
                            'percentTotalSupply' => floatval($item['percent_supply_vesting'] ?? $item['percent_round_supply'] ?? 0),
                            'unlockAtTGE' => floatval($item['percent_unlock_at_tge'] ?? $item['unlock_tge'] ?? 0),
                            'cliff' => intval($item['cliff_months'] ?? 0),
                            'vestingPeriod' => intval($item['vesting_months'] ?? 0)
                        ];
                    }
                } else {
                    // Fallback Logic
                    $rounds = $snapshot['rounds'] ?? [];
                    foreach ($rounds as $round) {
                        $name = $round['round_name'] ?? '';
                        if (empty($name)) continue;

                        $percentSupply = 0;
                        if (isset($round['percent_round_supply'])) {
                            $percentSupply = floatval($round['percent_round_supply']);
                        } else {
                            $percentRaise = floatval($round['percent_total_raise'] ?? 0);
                            $percentDiscount = floatval($round['percent_discount'] ?? 0);
                            
                            $amountRaised = $targetRaise * ($percentRaise / 100);
                            $roundPrice = $tgePrice * (1 - ($percentDiscount / 100));
                            
                            if ($roundPrice > 0 && $supplyValue > 0) {
                                $tokens = $amountRaised / $roundPrice;
                                $percentSupply = ($tokens / $supplyValue) * 100;
                            }
                        }

                        $vestingSchedules[] = [
                            'category' => $name,
                            'percentTotalSupply' => $percentSupply,
                            'unlockAtTGE' => $round['unlock_tge'] ?? 0,
                            'cliff' => $round['cliff_months'] ?? 0,
                            'vestingPeriod' => $round['vesting_months'] ?? 0
                        ];
                    }

                    $allocations = $snapshot['allocations'] ?? [];
                    foreach ($allocations as $alloc) {
                        $name = $alloc['tranche_name'] ?? '';
                        if (empty($name) || strtolower($name) === 'investors' || strtolower($name) === 'investor') continue;

                        $vestingSchedules[] = [
                            'category' => $name,
                            'percentTotalSupply' => floatval($alloc['allocation_percent'] ?? 0),
                            'unlockAtTGE' => $alloc['unlock_tge'] ?? 0,
                            'cliff' => $alloc['cliff_months'] ?? 0,
                            'vestingPeriod' => $alloc['vesting_months'] ?? 0
                        ];
                    }
                }
            }
        }
    }

    if (empty($vestingSchedules)) {
        $vestingSchedules = []; 
    }
    
    $projectData['detailedVestingSchedule'] = $vestingSchedules;

    // 9. Prepare Distribution Data for Pie Chart
    $distributionData = ['labels' => [], 'data' => []];
    foreach ($vestingSchedules as $vestingItem) {
        if ($vestingItem['percentTotalSupply'] > 0) {
            $distributionData['labels'][] = $vestingItem['category'];
            $distributionData['data'][] = (float)$vestingItem['percentTotalSupply'];
        }
    }
    $projectData['distributionData'] = $distributionData;
    
    // 10. Fetch Agreement
    $stmt_agreement = $pdo->prepare("SELECT * FROM agreement_versions WHERE projet_id = :project_id AND is_active = 1 LIMIT 1");
    $stmt_agreement->execute(['project_id' => $projectId]);
    $activeAgreement = $stmt_agreement->fetch(PDO::FETCH_ASSOC);

    $cs = $projectData['complianceSettings'];
    $projectData['complianceDocsOnly'] = [];

    if ($activeAgreement) {
        if (!empty($activeAgreement['file_url'])) {
            $projectData['complianceDocsOnly'][] = ['icon' => 'file-signature', 'label' => 'Token Sale Agreement (PDF)', 'url' => $activeAgreement['file_url'], 'is_download' => true];
        } elseif (!empty($activeAgreement['content']) && $activeAgreement['content'] !== '[]') {
            $projectData['complianceDocsOnly'][] = ['icon' => 'file-text', 'label' => 'View Agreement (Text)', 'url' => '#', 'is_download' => false, 'modal_trigger' => true];
            
            $rawContent = $activeAgreement['content'];
            $decodedContent = json_decode($rawContent, true);
            if (is_string($decodedContent)) { $decodedContent = json_decode($decodedContent, true); }

            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                if (isset($decodedContent['clauses'])) {
                    $agreement_text_content = [['title' => 'Terms & Conditions', 'body' => nl2br(htmlspecialchars($decodedContent['clauses']))]];
                } elseif (isset($decodedContent[0]) && is_array($decodedContent[0])) {
                    $agreement_text_content = $decodedContent;
                } else {
                    $agreement_text_content = [['title' => 'Agreement Details', 'body' => '<pre>' . htmlspecialchars(json_encode($decodedContent, JSON_PRETTY_PRINT)) . '</pre>']];
                }
            } else {
                $agreement_text_content = [['title' => 'Agreement', 'body' => nl2br(htmlspecialchars($rawContent))]];
            }
        }
    }
    
    if (!empty($cs['legal_opinion_url'])) $projectData['complianceDocsOnly'][] = ['icon' => 'scale', 'label' => 'Legal Opinion', 'url' => $cs['legal_opinion_url'], 'is_download' => true];
    if (!empty($cs['terms_of_service_url'])) $projectData['complianceDocsOnly'][] = ['icon' => 'shield-check', 'label' => 'Terms of Service', 'url' => $cs['terms_of_service_url'], 'is_download' => true];
    if (!empty($cs['other_doc_url'])) $projectData['complianceDocsOnly'][] = ['icon' => 'file', 'label' => 'Other Document', 'url' => $cs['other_doc_url'], 'is_download' => true];

    $projectData['docLinks'] = [];
    if (!empty($projectData['whitepaper_file_path'])) $projectData['docLinks'][] = ['icon' => 'book-open', 'label' => 'Whitepaper', 'url' => $projectData['whitepaper_file_path'], 'is_download' => true];

    // 11. Map to Frontend Keys
    $keyMapping = [
        'project_name' => 'projectName', 'token_ticker' => 'projectTicker', 'hard_cap_usd' => 'targetRaise', 
        'soft_cap_usd' => 'softCap', 'supply_value' => 'supply', 'type_supply' => 'supplyType', 
        'sale_launch_at' => 'launchDate', 'sale_end_at' => 'saleEndDate', 'purchase_method' => 'purchaseOptions',
        'min_investment_usd' => 'minPurchase', 'max_investment_usd' => 'maxPurchase', 'project_description_story' => 'projectDescription',
        'team_json' => 'teamData', 'partners_json' => 'partnerData', 'faqs_json' => 'faqData', 'socials_json' => 'socialLinks',
        'project_roadmap_json' => 'project_roadmap_items', 'value_props_json' => 'valueProps',
        'token_utilities_json' => 'tokenUtilities', 'community_metrics_json' => 'communityStats',
        'video_file_path' => 'video_file_path', 'general_images_json' => 'general_images_json',
        'complianceDocsOnly' => 'complianceDocsOnly', 'docLinks' => 'docLinks',
        'calculated_price_tge' => 'calculatedPrice'
    ];

    foreach($keyMapping as $oldKey => $newKey) {
        if(isset($projectData[$oldKey])) {
            $finalProjectData[$newKey] = $projectData[$oldKey];
        }
    }
    
    $finalProjectData['selectedRoundDetails'] = $projectData['selectedRoundDetails'];
    $finalProjectData['distributionData'] = $projectData['distributionData'];
    $finalProjectData['detailedVestingSchedule'] = $projectData['detailedVestingSchedule'];
    $finalProjectData['complianceSettings'] = $projectData['complianceSettings'];

    if (isset($finalProjectData['selectedRoundDetails']['round_price'])) {
        $finalProjectData['tokenPrice'] = $finalProjectData['selectedRoundDetails']['round_price'];
    } elseif (isset($finalProjectData['calculatedPrice'])) {
        $finalProjectData['tokenPrice'] = $finalProjectData['calculatedPrice'];
    }
    
    if (isset($_SESSION['user_id'])) {
        $stmt_user = $pdo->prepare("SELECT id, first_name, last_name, email FROM user WHERE id = ?");
        $stmt_user->execute([$_SESSION['user_id']]);
        $finalProjectData['userInfo'] = $stmt_user->fetch(PDO::FETCH_ASSOC) ?: null;
    }

} catch (Exception $e) {
    $page_error = $e->getMessage();
    $finalProjectData = ['error' => $page_error];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.1/dist/chartjs-adapter-luxon.umd.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<style>
    :root {
        --accent-purple: #8b5cf6; 
        --accent-purple-light: #f5f3ff; 
        --text-primary: #1f2937;
        --text-secondary: #6b7280; 
        --border-color: #e5e7eb; 
        --main-bg: #f9fafb;
    }

    /* Base Layout Improvements */
    .main-content-inner { 
        padding: 1rem; /* Mobile first: smaller padding */
        max-width: 80rem; 
        margin: auto; 
    }
    
    @media (min-width: 768px) {
        .main-content-inner { padding: 2.5rem; }
    }

    .content-section-card { 
        background-color: white; 
        border-radius: 0.75rem; 
        margin-bottom: 2rem; /* Reduced margin for mobile */
        border: 1px solid var(--border-color); 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        padding: 1.5rem; /* Mobile first: smaller internal padding */
    }

    @media (min-width: 768px) {
        .content-section-card { 
            padding: 2.5rem; 
            margin-bottom: 3rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        }
    }

    /* Video Optimization */
    .placeholder-media { 
        border-radius: 0.75rem; 
        overflow: hidden; 
        position: relative; 
        background-color: #f3f4f6; 
        width: 100%;
        /* Replaces fixed height with ratio */
        aspect-ratio: 16 / 9;
    }
    .placeholder-media video, .placeholder-media img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
    }

    /* Typography Responsive Scaling */
    .section-title { 
        font-size: 1.75rem; /* Smaller on mobile */
        font-weight: 800; 
        text-align: center; 
        margin-bottom: 1rem; 
        color: var(--text-primary); 
        line-height: 1.2;
    }

    @media (min-width: 768px) {
        .section-title { font-size: 2.25rem; margin-bottom: 1.5rem; }
    }

    .section-subtitle { 
        font-size: 1rem; 
        color: var(--text-secondary); 
        text-align: center; 
        margin-bottom: 2rem; 
        max-width: 700px; 
        margin-left: auto; 
        margin-right: auto; 
    }

    @media (min-width: 768px) {
        .section-subtitle { font-size: 1.125rem; margin-bottom: 3.5rem; }
    }

    /* Components */
    .inner-card { 
        background-color: white; 
        border: 1px solid var(--border-color); 
        border-radius: 0.625rem; 
        padding: 1.25rem; 
        text-align: center; 
        transition: all 0.25s ease; 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: flex-start; 
        text-decoration: none; 
        height: 100%; 
    }

    .team-member-image { 
        width: 90px; 
        height: 90px; 
        border-radius: 9999px; 
        margin: 0 auto 1rem auto; 
        object-fit: cover; 
        border: 3px solid var(--accent-purple-light); 
    }
    
    @media (min-width: 768px) {
        .inner-card { padding: 1.75rem; }
        .team-member-image { width: 110px; height: 110px; border: 4px solid; margin-bottom: 1.25rem; }
    }

    /* Tables */
    .launch-details-table td { 
        padding: 0.75rem 0; 
        font-size: 0.875rem; 
        border-bottom: 1px solid #f1f5f9; 
    }
    .launch-details-table tr:last-child td { border-bottom: none; }
    .launch-details-table td:first-child { 
        color: var(--text-secondary); 
        padding-right: 1rem; 
        font-weight: 500; 
        width: 40%;
    }
    .launch-details-table td:last-child { 
        font-weight: 600; 
        text-align: right; 
        width: 60%;
    }

    /* Buttons */
    .button { display: inline-block; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: all 0.2s; text-align: center; font-size: 0.95rem; cursor: pointer; }
    .button-gradient { background-image: linear-gradient(to right, var(--accent-purple), #34D399, var(--accent-purple)); background-size: 200% auto; color: white; text-decoration: none; }
    .button-gradient:hover { transform: translateY(-2px); background-position: right center; }
    .button-secondary { border: 1px solid var(--border-color); background-color: white; color: var(--text-primary); }
    .button-secondary:hover { background-color: #f9fafb; }

    /* Sticky Bar - Mobile Friendly */
    .sticky-invest-bar {
        position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        border-top: 1px solid var(--border-color); box-shadow: 0 -4px 20px rgba(0,0,0,0.08); z-index: 50;
        opacity: 0; transform: translateY(100%); visibility: hidden;
        transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out, visibility 0.3s ease-in-out;
        display: flex; justify-content: space-between; align-items: center; 
        padding: 0.75rem 1rem;
    }
    .sticky-invest-bar.visible { opacity: 1; transform: translateY(0); visibility: visible; }
    .sticky-project-name { 
        font-size: 1rem; 
        font-weight: 600; 
        color: var(--text-primary); 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        max-width: 50%;
    }
    
    @media (min-width: 768px) {
        .sticky-invest-bar { padding: 1rem 2rem; background: rgba(255, 255, 255, 0.85); }
        .sticky-project-name { font-size: 1.125rem; max-width: none; }
    }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background-color: rgba(30, 41, 59, 0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 50; opacity: 0; transition: opacity 0.3s ease; pointer-events: none; padding: 1rem; }
    .modal-overlay.active { opacity: 1; pointer-events: auto; }
    .modal-box { background-color: white; border-radius: 0.75rem; padding: 1.5rem; width: 100%; max-width: 700px; transform: scale(0.95); transition: transform 0.3s ease; text-align: left; display: flex; flex-direction: column; max-height: 85vh; }
    .modal-overlay.active .modal-box { transform: scale(1); }
    .modal-content-scroll { overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1rem; }
</style>

<div class="main-content-inner">
    <main>
        <!-- Hero Section -->
        <section id="introduction" class="content-section-card">
            <!-- Fixed Media Container: Removed fixed height, using aspect ratio -->
            <div class="placeholder-media mb-6 md:mb-10 rounded-lg bg-gray-100 flex items-center justify-center mx-auto max-w-5xl">
                <span id="media-placeholder" class="text-gray-500 text-sm">Loading visual...</span>
            </div>
            
            <h2 id="project-overview-title" class="section-title text-2xl md:text-4xl !mb-4">Project Overview</h2>
            <p id="project-description-display" class="text-center text-base md:text-lg text-gray-600 max-w-3xl mx-auto mb-6 md:mb-10 leading-relaxed px-2">Loading...</p>
            <div class="text-center mb-4 md:mb-8">
                <a href="<?= get_url('purchase') ?>" id="top-invest-now-button" class="button button-gradient w-full md:w-auto !py-3 md:!py-4 !px-8 md:!px-12 !text-base md:!text-lg !font-bold">Join Now!</a>
            </div>
        </section>

        <!-- Metrics -->
        <section id="key-metrics" class="content-section-card">
            <h2 class="section-title">Key Metrics</h2>
            <div id="key-metrics-grid" class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-6 justify-center max-w-5xl mx-auto">
                <p class="text-gray-400 italic col-span-full text-center">Loading...</p>
            </div>
        </section>

        <!-- Sale Details -->
        <section id="details" class="content-section-card">
            <h2 class="section-title">Token Sale Details</h2>
            <div class="max-w-xl mx-auto bg-gray-50 p-4 md:p-8 rounded-lg border border-gray-100">
                <h3 id="project-name-details" class="text-center font-semibold text-lg md:text-xl mb-4 md:mb-8">Token Sale</h3>
                <table class="w-full launch-details-table"><tbody></tbody></table>
            </div>
        </section>

        <!-- Value Props -->
        <section id="why-project-text-section" class="content-section-card">
            <h2 id="project-name-value" class="section-title">Why Invest?</h2>
            <div id="why-project-text-content" class="max-w-3xl mx-auto space-y-6 md:space-y-10"></div>
        </section>

        <!-- Utilities -->
        <section id="token-utilities" class="content-section-card">
            <h2 class="section-title">Core Utilities</h2>
            <div id="token-utilities-list" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 md:gap-6 max-w-5xl mx-auto"></div>
        </section>

        <!-- Charts -->
        <section id="distribution" class="content-section-card">
            <h2 class="section-title">Distribution & Vesting</h2>
            <p class="section-subtitle">Allocation and release schedule.</p>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-10 items-start">
                <div class="lg:col-span-1">
                    <h3 class="chart-title text-sm md:text-base">Initial Distribution</h3>
                    <div class="h-[250px] md:h-[350px]">
                        <canvas id="launchPageDistributionPieChart"></canvas>
                    </div>
                </div>
                <div class="lg:col-span-1">
                    <h3 class="chart-title text-sm md:text-base">Inflation from Vesting</h3>
                    <div class="h-[250px] md:h-[350px]">
                        <canvas id="launchPageVestingChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="mt-8 md:mt-12">
                <h3 class="chart-title text-sm md:text-base">Emission Over Time</h3>
                <div class="h-[250px] md:h-[400px]">
                    <canvas id="launchPageEmissionChart"></canvas>
                </div>
            </div>
        </section>

        <section id="roadmap" class="content-section-card" style="display: none;">
            <h2 class="section-title">Roadmap</h2>
            <div id="roadmap-content" class="space-y-8 md:space-y-10 max-w-4xl mx-auto pl-2"></div>
        </section>

        <section id="team" class="content-section-card">
            <h2 class="section-title">The Team</h2>
            <div id="team-list-cards" class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-8 max-w-5xl mx-auto"></div>
        </section>

        <section id="partners" class="content-section-card" style="display: none;">
            <h2 class="section-title">Backed By</h2>
            <div id="partner-list" class="flex flex-wrap justify-center items-center gap-6 md:gap-16 max-w-6xl mx-auto"></div>
        </section>

        <section id="resources" class="content-section-card">
            <h2 class="section-title">Community</h2>
            <div id="resources-grid" class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6 justify-center max-w-5xl mx-auto"></div>
        </section>
        
        <section id="faq" class="content-section-card" style="display: none;">
            <h2 class="section-title">FAQ</h2>
            <div id="faq-list" class="max-w-3xl mx-auto space-y-4"></div>
        </section>
        
        <section id="compliance-documents" class="content-section-card">
            <h2 class="section-title">Documents</h2>
            <div id="compliance-documents-list" class="text-center"></div>
        </section>

        <!-- Footer / Disclaimer -->
        <section class="max-w-5xl mx-auto mb-20 md:mb-8 text-left px-2">
            <h2 class="text-lg md:text-xl font-semibold mb-4 text-gray-800">Terms & Eligibility</h2>
            <div class="bg-white border border-gray-200 rounded-lg p-4 md:p-6 shadow-sm">
                <div id="compliance-disclaimer-addendum" class="text-xs md:text-sm text-gray-600 space-y-3"></div>
            </div>
            
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 md:p-8 mt-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-shrink-0 hidden md:block">
                        <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-gray-500"></i>
                        </div>
                    </div>
                    <div class="space-y-3 text-xs md:text-sm text-gray-500 leading-relaxed text-justify">
                        <h3 class="font-bold text-gray-900 text-xs uppercase tracking-wider mb-1">Disclaimer</h3>
                        <p>This page is part of a private, non-public fundraising campaign accessible only to invited participants. It does not constitute a public offering, advertisement, solicitation, or promotion of financial instruments, securities, or investments in any jurisdiction.</p>
                        <p>The token described herein is a utility token intended solely to provide access to specific features, products, or services within the project’s ecosystem. It is not designed for investment purposes, and it should not be purchased with the expectation of profit, income, or appreciation.</p>
                        <p>Participation is conducted directly between you and the Founder. Tookle is a non-custodial software provider and does not manage, hold, supervise, or control any funds or digital assets.</p>
                        <p>Nothing on this page or within the Tookle interface constitutes financial, legal, tax, or investment advice. You are solely responsible for determining whether your participation is lawful in your jurisdiction and for seeking independent professional advice if needed.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<div id="sticky-invest-bar" class="sticky-invest-bar">
    <span id="sticky-project-name" class="sticky-project-name"></span>
    <a href="<?= get_url('purchase') ?>" id="sticky-invest-button" class="button button-gradient !py-2 md:!py-3 !px-6 md:!px-10 !text-sm md:!text-base !font-bold">Join Now</a>
</div>

<div id="agreement-view-modal" class="modal-overlay">
    <div class="modal-box !max-w-3xl !text-left">
        <h3 class="text-lg md:text-xl font-bold mb-4">Token Purchase Agreement</h3>
        <div id="agreement-view-content" class="modal-content-scroll prose prose-sm bg-gray-50 p-4 rounded-md border h-64 md:h-96">
            <!-- Agreement text will be injected here -->
        </div>
        <div class="flex justify-center gap-4 mt-4 md:mt-6">
            <button id="modal-close-view" class="button button-secondary">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const projectDataGlobal = <?php echo json_encode($finalProjectData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_NUMERIC_CHECK); ?>;
    const agreementTextContent = <?php echo json_encode($agreement_text_content, JSON_HEX_TAG | JSON_HEX_APOS | JSON_NUMERIC_CHECK); ?>;
    
    if (projectDataGlobal.error) {
        document.querySelector('.main-content-inner').innerHTML = `<div class="text-red-500 text-center p-8">${projectDataGlobal.error}</div>`;
        return;
    }

    const stickyBar = document.getElementById('sticky-invest-bar');
    const tokenDetailsSection = document.getElementById('details');
    const mainContentArea = document.querySelector('main.overflow-y-auto') || window; // Fallback to window if main isn't scrolling
    const sidebar = document.querySelector('aside');

    if (stickyBar && sidebar && window.getComputedStyle(sidebar).display !== 'none') {
        const sidebarWidth = sidebar.offsetWidth;
        stickyBar.style.left = `${sidebarWidth}px`;
        stickyBar.style.width = `calc(100% - ${sidebarWidth}px)`;
    }

    // Improve Sticky Observer logic for all viewport types
    if (stickyBar && tokenDetailsSection) {
        const observer = new IntersectionObserver((entries) => {
            const detailsSectionIsVisible = entries[0].isIntersecting;
            // Sticky logic: Show when scrolled PAST the details section usually, 
            // but simplified here to show as long as details are viewed or passed.
            if (!detailsSectionIsVisible && window.scrollY > 100) {
                 stickyBar.classList.add('visible');
            } else if (entries[0].boundingClientRect.top < 0) {
                 // Section passed top of screen
                 stickyBar.classList.add('visible');
            } else {
                 stickyBar.classList.remove('visible');
            }
        }, { threshold: 0 });
        
        // Simple Scroll fallback for robust mobile behavior
        window.addEventListener('scroll', () => {
            const triggerPoint = tokenDetailsSection.offsetTop + tokenDetailsSection.offsetHeight;
            if (window.scrollY > triggerPoint / 2) {
                stickyBar.classList.add('visible');
            } else {
                stickyBar.classList.remove('visible');
            }
        });
    }

    const CHART_COLORS = ['#6d28d9', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#5b21b6', '#7c3aed', '#9f7aea', '#b794f4', '#d6bcfa'];
    const formatCurrency = (v, d = 2) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: d, maximumFractionDigits: 6 }).format(v || 0);
    const formatNumber = (v, d = 0) => new Intl.NumberFormat('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }).format(v || 0);

    function renderPage() {
        const { projectName, projectDescription, projectTicker, launchDate, saleEndDate, tokenPrice, targetRaise, minPurchase, maxPurchase, supply, supplyType, selectedRoundDetails, communityStats, teamData, partnerData, faqData, valueProps, tokenUtilities, complianceSettings, docLinks, socialLinks, complianceDocsOnly } = projectDataGlobal;

        document.title = `${projectName || 'Project'} Details - Tookle`;
        document.getElementById('project-overview-title').textContent = `Overview of ${projectName}`;
        document.getElementById('project-description-display').textContent = projectDescription;
        document.getElementById('project-name-details').textContent = `${projectName} Token Sale`;
        document.getElementById('project-name-value').textContent = `Why ${projectName}?`;
        document.getElementById('sticky-project-name').textContent = projectName || 'Project';

        const mediaParent = document.getElementById('media-placeholder').parentElement;
        mediaParent.innerHTML = '';
        if (projectDataGlobal.video_file_path) {
            mediaParent.innerHTML = `<video controls autoplay muted loop playsinline class="w-full h-full object-cover"><source src="/uploads/${projectDataGlobal.video_file_path}"></video>`;
        } else if (Array.isArray(projectDataGlobal.general_images_json) && projectDataGlobal.general_images_json[0]) {
            mediaParent.innerHTML = `<img src="/uploads/${projectDataGlobal.general_images_json[0]}" class="w-full h-full object-cover">`;
        } else {
            mediaParent.innerHTML = `<div class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500">No Media</div>`;
        }
        
        const metricsGrid = document.getElementById('key-metrics-grid');
        const validMetrics = Array.isArray(communityStats)
          ? communityStats
              .map(s => ({
                indicator: (s?.indicator ?? s?.label ?? '').toString().trim(),
                value: (s?.value ?? '').toString().trim()
              }))
              .filter(s => s.indicator !== '' || s.value !== '')
          : [];

        metricsGrid.innerHTML = validMetrics.length
          ? validMetrics.map(s => `
              <div class="inner-card p-2 md:p-4">
                <div class="text-xl md:text-2xl font-bold text-purple-600 break-all">${s.value}</div>
                <div class="text-xs md:text-sm text-gray-500">${s.indicator}</div>
              </div>
            `).join('')
          : '<p class="col-span-full text-center text-gray-500">No metrics available.</p>';

        
        let vestingText = "N/A";
        if (selectedRoundDetails && selectedRoundDetails.percent_unlock_at_tge !== undefined) {
            vestingText = `${selectedRoundDetails.percent_unlock_at_tge}% TGE, ${selectedRoundDetails.cliff_months}m cliff, ${selectedRoundDetails.vesting_months}m vesting`;
        }
        
        const eligibility = [];
        if (complianceSettings.kyc_required) eligibility.push('KYC Required');
        if (complianceSettings.exclude_sanctioned) eligibility.push('No Sanctioned');
        if (complianceSettings.exclude_us_non_accredited) eligibility.push('No US Retail');

        document.querySelector('.launch-details-table tbody').innerHTML = `
            <tr><td>Round</td><td>${selectedRoundDetails?.round_name || 'N/A'}</td></tr>
            <tr><td>Dates</td><td>${launchDate ? new Date(launchDate).toLocaleDateString() : 'TBA'} - ${saleEndDate ? new Date(saleEndDate).toLocaleDateString() : 'TBA'}</td></tr>
            <tr><td>Ticker</td><td>${projectTicker||'N/A'}</td></tr>
            <tr><td>Price</td><td>${formatCurrency(tokenPrice, 6)}</td></tr>
            <tr><td>Vesting</td><td>${vestingText}</td></tr>
            <tr><td>Supply</td><td>${formatNumber(supply)}</td></tr>
            <tr><td>Goal</td><td>${formatCurrency(targetRaise, 0)}</td></tr>
            <tr><td>Min/Max</td><td>${formatCurrency(minPurchase, 0)} / ${formatCurrency(maxPurchase, 0)}</td></tr>
        `;
        
        const validProps = valueProps && valueProps.length > 0 ? valueProps.filter(p => p.title && p.title.trim() !== '') : [];
        document.getElementById('why-project-text-content').innerHTML = validProps.length > 0 ? validProps.map(p => `<div><h3 class="text-lg md:text-xl font-bold mb-2">${p.title}</h3><p class="text-sm md:text-base text-gray-700">${p.description}</p></div>`).join('') : '<p>Information not available.</p>';
        document.getElementById('token-utilities-list').innerHTML = tokenUtilities && tokenUtilities.length > 0 ? tokenUtilities.map(u => `<div class="inner-card"><i data-lucide="check-circle" class="w-6 h-6 md:w-8 md:h-8 text-purple-600 mb-2"></i><h4 class="font-bold text-sm md:text-base">${u.name}</h4><p class="text-xs md:text-sm text-gray-600">${u.description}</p></div>`).join('') : '<p>Utilities not specified.</p>';
        
        const validTeam = teamData && teamData.length > 0 ? teamData.filter(m => m.name && m.name.trim() !== '') : [];
        const teamList = document.getElementById('team-list-cards');
        const defaultAvatar = (name) => `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=e0e0e0&color=333`;
        teamList.innerHTML = validTeam.length > 0 ? validTeam.map(m => `<div class="inner-card"><img src="${m.picture_file_path ? `/uploads/${m.picture_file_path}` : defaultAvatar(m.name)}" class="team-member-image"><h4 class="font-bold text-sm md:text-base">${m.name}</h4><p class="text-purple-600 text-xs md:text-sm">${m.role}</p></div>`).join('') : '<p class="col-span-full text-center text-gray-500">Team information not available.</p>';
        
        if (projectDataGlobal.project_roadmap_items && projectDataGlobal.project_roadmap_items.length > 0) {
            document.getElementById('roadmap').style.display = 'block';
            document.getElementById('roadmap-content').innerHTML = projectDataGlobal.project_roadmap_items.map(item => `<div class="relative pl-4 border-l-2 border-purple-200"><h3 class="font-bold text-gray-900">${item.title}</h3><p class="text-sm text-gray-600 mt-1">${item.description}</p></div>`).join('');
        }
        
        const validPartners = partnerData && partnerData.length > 0 ? partnerData.filter(p => p.name && p.name.trim() !== '') : [];
        if (validPartners.length > 0) {
            document.getElementById('partners').style.display = 'block';
            document.getElementById('partner-list').innerHTML = validPartners.map(p => 
                `<a href="${p.website}" target="_blank" class="block transition hover:scale-105 transform duration-300">
                    <img src="/uploads/${p.logo_file_path}" alt="${p.name}" class="h-12 md:h-16 w-auto object-contain grayscale hover:grayscale-0 opacity-80 hover:opacity-100">
                </a>`
            ).join('');
        }
        
        const validFAQs = faqData && faqData.length > 0 ? faqData.filter(f => f.question && f.question.trim() !== '') : [];
        if (validFAQs.length > 0) {
            document.getElementById('faq').style.display = 'block';
            document.getElementById('faq-list').innerHTML = validFAQs.map(f => `<div class="faq-item border border-gray-200 rounded-lg p-3 md:p-4"><details class="group"><summary class="flex justify-between items-center font-medium cursor-pointer list-none"><span class="text-sm md:text-base">${f.question}</span><span class="transition group-open:rotate-180"><i data-lucide="chevron-down" class="w-4 h-4"></i></span></summary><div class="text-gray-600 mt-3 text-sm leading-relaxed">${f.answer}</div></details></div>`).join('');
        }

        const complianceList = document.getElementById('compliance-documents-list');
        const allDocs = [...(docLinks || []), ...(complianceDocsOnly || [])];
        if(allDocs.length > 0) {
            complianceList.className = 'grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 max-w-4xl mx-auto';
            complianceList.innerHTML = allDocs.map(d => {
                const tag = d.is_download ? 'a' : 'button';
                const href = d.is_download ? `href="/uploads/${d.url}" target="_blank"` : `type="button"`;
                const modalTrigger = d.modal_trigger ? 'data-modal-trigger="agreement-view"' : '';
                return `<${tag} ${href} ${modalTrigger} class="inner-card group hover:border-purple-400 hover:shadow-lg p-3 md:p-4"><i data-lucide="${d.icon || 'file-text'}" class="w-8 h-8 md:w-12 md:h-12 text-purple-600 mb-2 md:mb-3 transition-transform group-hover:scale-110"></i><h4 class="font-bold text-gray-800 text-xs md:text-sm">${d.label}</h4></${tag}>`;
            }).join('');
        } else {
            complianceList.innerHTML = '<p class="text-gray-400 text-sm">No documents available.</p>';
        }

        const resourcesGrid = document.getElementById('resources-grid');
        if(socialLinks && socialLinks.length > 0) {
             resourcesGrid.innerHTML = socialLinks.map(r => {
                const icon = r.platform_select?.toLowerCase().includes('twitter') ? 'twitter' : 'link'; 
                return `<a href="${r.url || '#'}" target="_blank" class="inner-card group hover:border-purple-400 p-3 md:p-4"><i data-lucide="${icon}" class="w-6 h-6 md:w-8 md:h-8 text-purple-600 mb-2 md:mb-3 transition-transform group-hover:scale-110"></i><h4 class="font-bold text-sm">${r.platform_select}</h4><p class="text-xs text-gray-600 break-all truncate w-full">${r.url}</p></a>`;
             }).join('');
        } else {
            resourcesGrid.innerHTML = '<p class="col-span-full text-center text-gray-500 text-sm">No resources specified.</p>';
        }
        
        const complianceAddendumEl = document.getElementById('compliance-disclaimer-addendum');
        complianceAddendumEl.innerHTML = '';
        const addDisclaimerParagraph = (text, title) => {
            const div = document.createElement('div');
            if (title) div.innerHTML = `<strong class="text-gray-800">${title}:</strong> `;
            div.appendChild(document.createTextNode(text));
            complianceAddendumEl.appendChild(div);
        };
        
        let termsAdded = false;
        
        if (complianceSettings.kyc_required) { 
            addDisclaimerParagraph("Participation in this token sale requires KYC (Know Your Customer) verification.", "KYC Required"); 
            termsAdded = true; 
        }

        if (complianceSettings.exclude_sanctioned) { 
            addDisclaimerParagraph("Participation in this token sale is strictly prohibited for individuals or entities located in, established in, or ordinarily resident in jurisdictions subject to international sanctions, including but not limited to: Iran, North Korea (Democratic People’s Republic of Korea), Syria, Cuba, and the Crimea, Donetsk, and Luhansk regions of Ukraine. This restriction applies to any jurisdiction or party currently listed on the sanctions lists issued by the U.S. Office of Foreign Assets Control (OFAC), the European Union, the United Nations Security Council, or any other applicable national or international sanctions authority. Participants are solely responsible for ensuring they are not subject to any such restrictions. Attempts to bypass these restrictions may result in immediate exclusion and reporting to relevant authorities.", "Exclude Sanctioned Country"); 
            termsAdded = true; 
        }

        if (complianceSettings.exclude_us_non_accredited) { 
            addDisclaimerParagraph("This utility token is not available to U.S. retail participants. U.S. persons may only participate if they qualify as accredited participants under applicable U.S. law. By proceeding, you confirm that you are not a U.S. retail investor.", "Exclude U.S. Retail Participants"); 
            termsAdded = true; 
        }

        if (complianceSettings.require_eu_consent) {
            addDisclaimerParagraph("Participation from residents of the European Union requires explicit consent to data processing in accordance with the General Data Protection Regulation (GDPR). Participants must also acknowledge that crypto-assets may fall under the Markets in Crypto-Assets (MiCA) regulation, which may impose additional compliance requirements depending on your jurisdiction and the nature of the token.", "Require EU Consent");
            termsAdded = true;
        }

        try {
            const customRestrictions = JSON.parse(complianceSettings.custom_country_disclaimer || '[]');
            if (Array.isArray(customRestrictions) && customRestrictions.length > 0) {
                customRestrictions.forEach(cr => { 
                    if(cr.country && cr.disclaimer) { 
                        addDisclaimerParagraph(cr.disclaimer, `${cr.country} Restrictions`); 
                        termsAdded = true; 
                    } 
                });
                addDisclaimerParagraph("Additional geographic or regulatory restrictions may apply based on the evolving legal status of token offerings in certain jurisdictions. It is your responsibility to ensure that your participation is compliant with local laws and regulations.", "Custom Country Disclaimer");
                termsAdded = true;
            }
        } catch(e) {}
        
        if (!termsAdded) complianceAddendumEl.innerHTML = '<p>No specific eligibility restrictions listed. Please refer to the full agreement.</p>';

        initializeLaunchPageDistributionChart();
        initializeLaunchPageVestingAndEmissionCharts();
        lucide.createIcons();
    }
    
    function renderChartPlaceholder(canvasId, msg) { const canvas = document.getElementById(canvasId); if (canvas) canvas.parentElement.innerHTML = `<div class="h-full flex items-center justify-center text-gray-400 p-4 text-sm">${msg}</div>`; }
    
    function initializeLaunchPageDistributionChart() { 
        const data = projectDataGlobal.distributionData; 
        if (!data || !data.labels || !data.labels.length) { renderChartPlaceholder('launchPageDistributionPieChart', 'No data available'); return; } 
        const ctx = document.getElementById('launchPageDistributionPieChart')?.getContext('2d'); 
        if (ctx) new Chart(ctx, { type: 'pie', data: { labels: data.labels, datasets: [{ data: data.data, backgroundColor: CHART_COLORS, borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } } } }); 
    }
    
    function calculateMonthlyUnlocks(vestingItem, totalSupply) { 
        const MONTHS = 48, unlocks = new Array(MONTHS).fill(0); 
        if (isNaN(totalSupply) || !vestingItem) return unlocks; 
        const totalTokens = (parseFloat(vestingItem.percentTotalSupply) / 100) * totalSupply; 
        const tgeAmount = (parseFloat(vestingItem.unlockAtTGE) / 100) * totalTokens; 
        if (tgeAmount > 0) unlocks[0] = tgeAmount; 
        const remaining = totalTokens - tgeAmount; 
        const vestingMonths = parseInt(vestingItem.vestingPeriod, 10) || 0; 
        if (remaining > 0 && vestingMonths > 0) { 
            const monthlyVest = remaining / vestingMonths; 
            const startMonth = parseInt(vestingItem.cliff, 10) || 0; 
            for (let m = 0; m < vestingMonths; m++) { if (startMonth + m < MONTHS) unlocks[startMonth + m] += monthlyVest; } 
        } 
        return unlocks; 
    }
    
    function initializeLaunchPageVestingAndEmissionCharts() {
        const schedules = projectDataGlobal.detailedVestingSchedule, totalSupply = parseFloat(projectDataGlobal.supply || 0); 
        if (!schedules || schedules.length === 0 || isNaN(totalSupply)) { 
            renderChartPlaceholder('launchPageVestingChart', 'No data available'); 
            renderChartPlaceholder('launchPageEmissionChart', 'No data available'); 
            return; 
        } 
        const MONTHS = 48, labels = Array.from({ length: MONTHS }, (_, i) => `M${i}`), totalMonthlyInflation = new Array(MONTHS).fill(0), categories = [...new Set(schedules.map(s => s.category))], categoryMonthlyUnlocks = {}; 
        categories.forEach(cat => categoryMonthlyUnlocks[cat] = new Array(MONTHS).fill(0)); 
        schedules.forEach(item => { 
            const monthlyUnlocks = calculateMonthlyUnlocks(item, totalSupply); 
            for (let i = 0; i < MONTHS; i++) { 
                totalMonthlyInflation[i] += monthlyUnlocks[i]; 
                if (item.category && categoryMonthlyUnlocks[item.category]) categoryMonthlyUnlocks[item.category][i] += monthlyUnlocks[i]; 
            } 
        }); 
        const cumulativeCategoryData = {}; 
        categories.forEach(cat => { 
            cumulativeCategoryData[cat] = new Array(MONTHS).fill(0); 
            let runningTotal = 0; 
            for (let i = 0; i < MONTHS; i++) { runningTotal += (categoryMonthlyUnlocks[cat][i] || 0); cumulativeCategoryData[cat][i] = runningTotal; } 
        }); 
        const emissionDatasets = categories.map((cat, index) => ({ label: cat, data: cumulativeCategoryData[cat], borderColor: CHART_COLORS[index % CHART_COLORS.length], backgroundColor: CHART_COLORS[index % CHART_COLORS.length] + '30', fill: true, tension: 0.1, pointRadius: 0 })); 
        const commonOptions = (yLabel, isStacked = false) => ({ 
            responsive: true, maintainAspectRatio: false, plugins: { legend: { display: isStacked, position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }, 
            scales: { x: { title: { display: false }, ticks: { font: { size: 10 } } }, y: { stacked: isStacked, beginAtZero: true, title: { display: true, text: yLabel, font: { size: 10 } }, ticks: { font: { size: 10 } } } } 
        }); 
        const inflCtx = document.getElementById('launchPageVestingChart')?.getContext('2d'); 
        if (inflCtx) new Chart(inflCtx, { type: 'bar', data: { labels, datasets: [{ label: 'Monthly Inflation', data: totalMonthlyInflation, backgroundColor: CHART_COLORS[0] }] }, options: commonOptions('Tokens Unlocked') }); 
        const emissCtx = document.getElementById('launchPageEmissionChart')?.getContext('2d'); 
        if (emissCtx) new Chart(emissCtx, { type: 'line', data: { labels, datasets: emissionDatasets }, options: commonOptions('Cumulative Tokens', true) }); 
    }

    const viewModal = document.getElementById('agreement-view-modal');
    const viewModalContent = document.getElementById('agreement-view-content');
    const closeViewBtn = document.getElementById('modal-close-view');
    
    function formatAgreementText(sections) {
        if (!Array.isArray(sections)) return '<p>No agreement content found.</p>';
        return sections.map(section => {
            return `<h3 class="font-bold mb-2">${section.title}</h3><div class="mb-4 text-sm">${section.body}</div>`;
        }).join('<hr class="my-4">');
    }

    const docListContainer = document.getElementById('compliance-documents-list');
    if (docListContainer) {
        docListContainer.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal-trigger="agreement-view"]');
            if (trigger) {
                if (viewModalContent) viewModalContent.innerHTML = formatAgreementText(agreementTextContent);
                if (viewModal) viewModal.classList.add('active');
            }
        });
    }
    
    if (closeViewBtn) closeViewBtn.addEventListener('click', () => viewModal.classList.remove('active'));
    if (viewModal) viewModal.addEventListener('click', (e) => { if (e.target === viewModal) viewModal.classList.remove('active'); });

    renderPage();
});
</script>