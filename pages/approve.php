<?php
/**
 * Page: Configure Token Sale - Step 4: Review and Approve
 * Filepath: /pages/approve.php
 */

require_once __DIR__ . '/../wizard_nav.php';

$current_main_step = 'private_sale';
$current_sub_step = 'approve';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_project_id = $_SESSION['active_project_id'] ?? null;

if (!$current_project_id) {
    header('Location: /dashboard?error=noproject');
    exit;
}

require_once __DIR__ . '/../src/db.php';
$projectData = ['project_id' => $current_project_id];
$page_error = null;

$agreement_text_content = '[]';

try {
    $stmt = $pdo->prepare("
        SELECT p.*, tsp.* FROM projet p
        LEFT JOIN token_sale_pages tsp ON p.id = tsp.project_id
        WHERE p.id = :project_id
        ORDER BY tsp.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':project_id' => $current_project_id]);
    $mainData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mainData) {
        throw new Exception("Project with ID '$current_project_id' could not be found.");
    }
    $projectData = array_merge($projectData, $mainData);

    function decodeJsonSafely($jsonString, $default = []) {
        if (empty($jsonString)) return $default;
        $decoded = json_decode($jsonString, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $default;
    }

    $jsonFields = [
        'general_images_json', 'community_metrics_json', 'value_props_json', 'team_json',
        'partners_json', 'faqs_json', 'socials_json', 'project_roadmap_json', 'sale_terms_json'
    ];
    foreach ($jsonFields as $field) {
        $projectData[$field] = decodeJsonSafely($projectData[$field] ?? null);
    }

    $stmt_utils = $pdo->prepare("SELECT utility_name as name, 'Default description' as description FROM utility_token WHERE projet_id = :project_id");
    $stmt_utils->execute([':project_id' => $current_project_id]);
    $projectData['token_utilities_json'] = $stmt_utils->fetchAll(PDO::FETCH_ASSOC);

    $stmt_comp = $pdo->prepare("SELECT * FROM compliance_settings WHERE projet_id = :project_id");
    $stmt_comp->execute([':project_id' => $current_project_id]);
    $projectData['complianceSettings'] = $stmt_comp->fetch(PDO::FETCH_ASSOC) ?: [];

    $saleTerms = $projectData['sale_terms_json'] ?? [];
    $projectData['selectedRoundDetails'] = $saleTerms;
    
    if (isset($saleTerms['total_supply'])) {
        $projectData['supply_value'] = $saleTerms['total_supply'];
    }
    if (isset($saleTerms['round_price'])) {
        $projectData['calculated_price_tge'] = $saleTerms['round_price'];
    }

    $stmtFetchLabel = $pdo->prepare("SELECT version_label FROM scenario_version WHERE projet_id = :project_id ORDER BY created_at DESC LIMIT 1");
    $stmtFetchLabel->execute([':project_id' => $current_project_id]);
    $projectData['scenarioLabel'] = $stmtFetchLabel->fetchColumn() ?: 'Initial Version';

    $stmt_vesting = $pdo->prepare("
        SELECT
            tt.tranche_name AS category,
            tt.allocation_percent AS percentTotalSupply,
            vt.percent_unlock_at_tge AS unlockAtTGE,
            vt.cliff_months AS cliff,
            vt.vesting_months AS vestingPeriod
        FROM
            tranche_token tt
        LEFT JOIN
            vesting_token vt ON tt.projet_id = vt.projet_id AND tt.tranche_name = vt.vesting_block_name
        WHERE
            tt.projet_id = :project_id
    ");
    $stmt_vesting->execute([':project_id' => $current_project_id]);
    $vestingSchedules = $stmt_vesting->fetchAll(PDO::FETCH_ASSOC);
    $projectData['detailedVestingSchedule'] = $vestingSchedules;

    $distributionData = ['labels' => [], 'data' => []];
    if (!empty($vestingSchedules)) {
        foreach ($vestingSchedules as $vestingItem) {
            if (isset($vestingItem['category']) && isset($vestingItem['percentTotalSupply'])) {
                $distributionData['labels'][] = $vestingItem['category'];
                $distributionData['data'][] = (float)($vestingItem['percentTotalSupply'] ?? 0);
            }
        }
    }
    $projectData['distributionData'] = $distributionData;

    $stmt_agreement = $pdo->prepare("
        SELECT file_url, content FROM agreement_versions 
        WHERE projet_id = :project_id AND is_active = 1 
        LIMIT 1
    ");
    $stmt_agreement->execute([':project_id' => $current_project_id]);
    $activeAgreement = $stmt_agreement->fetch(PDO::FETCH_ASSOC);

    $projectData['complianceDocsOnly'] = [];
    $cs = $projectData['complianceSettings'];

    if (!empty($activeAgreement['file_url'])) {
        $projectData['complianceDocsOnly'][] = ['icon' => 'file-signature', 'label' => 'Token Sale Agreement', 'url' => $activeAgreement['file_url'], 'is_download' => true];
    } elseif (!empty($activeAgreement['content']) && $activeAgreement['content'] !== '[]') {
        $projectData['complianceDocsOnly'][] = ['icon' => 'file-text', 'label' => 'View Agreement (Text)', 'url' => '#', 'is_download' => false, 'modal_trigger' => true];
        $agreement_text_content = $activeAgreement['content'];
    }

    if (!empty($cs['legal_opinion_url'])) $projectData['complianceDocsOnly'][] = ['icon' => 'scale', 'label' => 'Legal Opinion', 'url' => $cs['legal_opinion_url'], 'is_download' => true];
    if (!empty($cs['terms_of_service_url'])) $projectData['complianceDocsOnly'][] = ['icon' => 'shield-check', 'label' => 'Terms of Service', 'url' => $cs['terms_of_service_url'], 'is_download' => true];
    
    // --- ADDED: Handle 'Other Document' mapping to correct column ---
    if (!empty($cs['other_doc_url'])) {
        $projectData['complianceDocsOnly'][] = ['icon' => 'file', 'label' => 'Other Document', 'url' => $cs['other_doc_url'], 'is_download' => true];
    }
    
    $projectData['docLinks'] = [];
    if (!empty($mainData['whitepaper_file_path'])) $projectData['docLinks'][] = ['icon' => 'book-open', 'name' => 'Whitepaper', 'url' => $mainData['whitepaper_file_path'], 'is_download' => true];

    $keyMapping = [
        'project_name' => 'projectName', 'token_ticker' => 'projectTicker', 'calculated_price_tge' => 'tokenPrice', 'valuation_tge_usd' => 'fdv',
        'supply_value' => 'totalSupply', 'type_supply' => 'supplyType', 'sale_launch_date' => 'launchDate',
        'sale_end_date' => 'saleEndDate', 
        'hard_cap_usd' => 'targetRaise',
        'soft_cap_usd' => 'softCap',
        'min_investment_usd' => 'minPurchase',
        'max_investment_usd' => 'maxPurchase',
        'selected_round_id' => 'selectedRoundId', 'video_file_path' => 'video_file_path',
        'project_description_story' => 'projectDescription', 'team_json' => 'teamData', 'partners_json' => 'partnerData', 'faqs_json' => 'faqData',
        'socials_json' => 'socialLinks', 'project_roadmap_json' => 'project_roadmap_items', 'value_props_json' => 'valueProps',
        'token_utilities_json' => 'tokenUtilities', 'community_metrics_json' => 'communityStats'
    ];
    foreach($keyMapping as $oldKey => $newKey) {
        if(isset($projectData[$oldKey])) { $projectData[$newKey] = $projectData[$oldKey]; }
    }

} catch (Exception $e) {
    error_log("Approve Page Data Fetch Error: " . $e->getMessage());
    $page_error = "Could not load project data for preview. Please go back and try again.";
}
?>
<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-gray-50">
    <div class="w-full max-w-8xl">
        <?php if ($page_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($page_error); ?></p>
            </div>
        <?php else: ?>
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900">Approve Your Token Sale Page</h1>
                <p class="text-lg text-gray-500 mt-2">This is a preview of what potential investors will see. Please review all details carefully.</p>
            </div>

            <div class="pb-24">
                
                <section id="introduction" class="content-section-card"><div class="placeholder-media mb-10 rounded-lg bg-gray-100 flex items-center justify-center min-h-[450px] mx-auto max-w-5xl"></div><h2 id="project-overview-title" class="section-title !text-4xl !mb-4"></h2><p id="project-description-display" class="text-center text-lg text-gray-600 max-w-3xl mx-auto mb-10 leading-relaxed"></p></section>
                <section id="key-metrics" class="content-section-card"><h2 class="section-title">Key Metrics & Community</h2><div id="key-metrics-grid" class="grid gap-6 justify-center max-w-5xl mx-auto"></div></section>
                <section id="details" class="content-section-card"><h2 class="section-title">Token Sale Details</h2><div class="max-w-xl mx-auto bg-gray-50 p-8 rounded-lg"><h3 id="project-name-details" class="text-center font-semibold text-xl mb-8"></h3><table class="w-full launch-details-table"><tbody></tbody></table></div></section>
                <section id="why-project" class="content-section-card"><h2 id="why-project-title" class="section-title"></h2><div id="why-project-content" class="max-w-3xl mx-auto space-y-10"></div></section>
                <section id="token-utilities" class="content-section-card"><h2 class="section-title">Core Token Utilities</h2><div id="token-utilities-list" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 max-w-5xl mx-auto"></div></section>
                <section id="distribution" class="content-section-card"><h2 class="section-title">Distribution & Vesting</h2><p class="section-subtitle">Understand how tokens are allocated and released over time.</p><div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start"><div class="lg:col-span-1"><h3 class="chart-title">Initial Token Distribution</h3><div class="h-[350px]"><canvas id="distribution-pie-chart"></canvas></div></div><div class="lg:col-span-1"><h3 class="chart-title">Inflation from Vesting</h3><div class="h-[350px]"><canvas id="vesting-bar-chart"></canvas></div></div></div><div class="mt-12"><h3 class="chart-title">Token Emission Over Time</h3><div class="h-[400px]"><canvas id="emission-line-chart"></canvas></div></div></section>
                <section id="team" class="content-section-card"><h2 class="section-title">Meet the Team</h2><div id="team-list-cards" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 max-w-5xl mx-auto"></div></section>
                <section id="compliance-documents" class="content-section-card"><h2 class="section-title">Compliance & Documents</h2><div id="compliance-documents-list" class="text-center"></div></section>
                
                <!-- Terms & Conditions Section (Dynamic) -->
                <section class="max-w-5xl mx-auto mb-8 text-left">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 px-2">Terms & Conditions</h2>
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <!-- Content populated by JS to avoid empty brackets -->
                        <div id="compliance-disclaimer-content" class="text-sm text-gray-600 space-y-3">
                            <p class="text-gray-400 italic">Loading terms...</p>
                        </div>
                    </div>
                </section>

                <!-- Disclaimer Section (Static) - CLEANER DESIGN -->
                <section class="max-w-5xl mx-auto">
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-6 md:p-8">
                        <div class="flex gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                    <i data-lucide="alert-circle" class="w-4 h-4 text-gray-500"></i>
                                </div>
                            </div>
                            <div class="space-y-4 text-sm text-gray-500 leading-relaxed text-justify">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-xs uppercase tracking-wider mb-1">Disclaimer</h3>
                                    <p>This page is part of a private, non-public fundraising campaign accessible only to invited participants. It does not constitute a public offering, advertisement, solicitation, or promotion of financial instruments, securities, or investments in any jurisdiction.</p>
                                </div>
                                <p>The token described herein is a utility token intended solely to provide access to specific features, products, or services within the project’s ecosystem. It is not designed for investment purposes, and it should not be purchased with the expectation of profit, income, or appreciation.</p>
                                <p>Participation is conducted directly between you and the Founder. Tookle is a non-custodial software provider and does not manage, hold, supervise, or control any funds or digital assets.</p>
                                <p>Nothing on this page or within the Tookle interface constitutes financial, legal, tax, or investment advice. You are solely responsible for determining whether your participation is lawful in your jurisdiction and for seeking independent professional advice if needed.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer class="sticky-approve-footer">
    <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
        <a href="<?= get_url('story') ?>" class="button button-secondary">Back to Edit</a>
        <button id="approve-submit-button" type="button" class="button button-gradient">Approve</button>
    </div>
    <p class="text-xs text-gray-500 text-center mt-2">Note: This page has been realised with the '<?php echo htmlspecialchars($projectData['scenarioLabel'] ?? 'Initial Version'); ?>' version of the tokenomics.</p>
</footer>

<div id="confirmation-modal" class="modal-overlay">
    <div class="modal-box"><h3 id="modal-title" class="text-xl font-bold mb-4"></h3><p id="modal-message" class="text-gray-600 mb-6"></p><div class="flex justify-center gap-4"><button id="modal-cancel" class="button button-secondary">Cancel</button><button id="modal-confirm" class="button button-gradient"></button></div></div>
</div>

<div id="agreement-view-modal" class="modal-overlay">
    <div class="modal-box !max-w-3xl !text-left">
        <h3 class="text-xl font-bold mb-4">Token Purchase Agreement</h3>
        <div id="agreement-view-content" class="prose prose-sm max-h-[60vh] overflow-y-auto bg-gray-50 p-4 rounded-md border"></div>
        <div class="flex justify-center gap-4 mt-6">
            <button id="modal-close-view" class="button button-secondary">Close</button>
        </div>
    </div>
</div>

<style>
    :root { --accent-purple: #6D28D9; --text-primary: #1f2937; --text-secondary: #6b7280; --border-color: #e5e7eb; --accent-purple-light: #f5f3ff; }
    .content-section-card { background-color: white; border-radius: 0.75rem; margin-bottom: 2rem; border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 2.5rem; }
    .section-title { font-size: 2.25rem; font-weight: 800; text-align: center; margin-bottom: 1.5rem; color: var(--text-primary); }
    .section-subtitle { font-size: 1.125rem; color: var(--text-secondary); text-align: center; margin-bottom: 3.5rem; max-width: 700px; margin-left: auto; margin-right: auto; }
    .placeholder-media { border-radius: 0.75rem; overflow: hidden; max-height: 500px; aspect-ratio: 16 / 9; position: relative; background-color: #f3f4f6; }
    .placeholder-media video, .placeholder-media img { width: 100%; height: 100%; object-fit: cover; }
    .button { display: inline-block; padding: 0.875rem 2rem; border-radius: 0.5rem; font-weight: 600; text-align: center; cursor: pointer; transition: all 0.2s; }
    .button-gradient { background-image: linear-gradient(to right, var(--accent-purple), #06b6d4); color: white; }
    .button-secondary { border: 1px solid var(--border-color); background-color: white; }
    .modal-overlay { position: fixed; inset: 0; background-color: rgba(30, 41, 59, 0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 50; opacity: 0; transition: opacity 0.3s ease; pointer-events: none; }
    .modal-overlay.active { opacity: 1; pointer-events: auto; }
    .modal-box { background-color: white; border-radius: 0.75rem; padding: 2rem; max-width: 90%; width: 500px; transform: scale(0.95); transition: transform 0.3s ease; text-align: center; }
    .modal-overlay.active .modal-box { transform: scale(1); }
    .chart-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1.5rem; text-align: center; }
    .inner-card { background-color: white; border: 1px solid var(--border-color); border-radius: 0.625rem; padding: 1.75rem; text-align: center; transition: all 0.25s ease; box-shadow: 0 4px 8px -1px rgba(0,0,0,0.04); display: flex; flex-direction: column; align-items: center; justify-content: flex-start; text-decoration: none; height: 100%; }
    .team-member-image { width: 110px; height: 110px; border-radius: 9999px; margin: 0 auto 1.25rem auto; object-fit: cover; border: 4px solid var(--accent-purple-light); }
    .launch-details-table td { padding: 1rem 0; font-size: 0.925rem; border-bottom: 1px solid #f1f5f9; }
    .launch-details-table tr:last-child td { border-bottom: none; }
    .launch-details-table td:first-child { color: var(--text-secondary); padding-right: 2rem; font-weight: 500; }
    .launch-details-table td:last-child { font-weight: 600; text-align: right; }
    .sticky-approve-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-top: 1px solid var(--border-color);
        box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
        z-index: 40;
        padding: 1rem 2rem;
    }
    #agreement-view-content pre {
        white-space: pre-wrap;
        font-family: inherit;
        font-size: 0.875rem;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.1/dist/chartjs-adapter-luxon.umd.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const projectData = <?php echo json_encode($projectData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_NUMERIC_CHECK); ?>;
    const agreementTextContent = <?php echo $agreement_text_content; ?>;

    const CHART_COLORS = ['#6d28d9', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#5b21b6', '#7c3aed'];
    
    const formatCurrency = (v, d = 2) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: d, maximumFractionDigits: 6 }).format(v || 0);
    const formatNumber = (v) => new Intl.NumberFormat('en-US').format(v || 0);
    const formatDate = (d) => d ? new Date(d).toLocaleDateString() : 'TBA';

    function renderPage() {
        document.title = `Review: ${projectData.projectName || 'Project'}`;
        document.getElementById('project-overview-title').textContent = `Overview of ${projectData.projectName}`;
        document.getElementById('project-description-display').textContent = projectData.projectDescription;
        document.getElementById('project-name-details').textContent = `${projectData.projectName} Token Sale`;
        document.getElementById('why-project-title').textContent = `Why Invest in ${projectData.projectName}?`;

        const mediaContainer = document.querySelector('#introduction .placeholder-media');
        let mediaHtml = '<div class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500">No Media</div>';
        if (projectData.video_file_path) {
            mediaHtml = `<video controls autoplay muted loop playsinline><source src="/uploads/${projectData.video_file_path}"></video>`;
        } else if (projectData.general_images_json && projectData.general_images_json[0]) {
            mediaHtml = `<img src="/uploads/${projectData.general_images_json[0]}">`;
        }
        mediaContainer.innerHTML = mediaHtml;
        
        const metricsContainer = document.getElementById('key-metrics-grid');
        metricsContainer.innerHTML = (projectData.communityStats && projectData.communityStats.length > 0)
            ? projectData.communityStats.map(s => `<div class="inner-card"><p class="text-2xl font-bold text-purple-600">${s.value}</p><p class="text-sm">${s.indicator}</p></div>`).join('')
            : '<p class="col-span-full text-center text-gray-500">No metrics available.</p>';

        const detailsTableBody = document.querySelector('#details tbody');
        
        let vestingText = "N/A";
        if (projectData.selectedRoundDetails && projectData.selectedRoundDetails.percent_unlock_at_tge !== undefined) {
             vestingText = `${projectData.selectedRoundDetails.percent_unlock_at_tge}% TGE, ${projectData.selectedRoundDetails.cliff_months}m cliff, ${projectData.selectedRoundDetails.vesting_months}m vesting`;
        }
        
        const eligibility = [];
        if (projectData.complianceSettings?.kyc_required) eligibility.push('KYC Required');
        if (projectData.complianceSettings?.exclude_sanctioned) eligibility.push('Sanctioned Countries Excluded');
        
        detailsTableBody.innerHTML = `
            <tr><td>Current Round</td><td>${projectData.selectedRoundDetails?.round_name || 'N/A'}</td></tr>
            <tr><td>Sale Dates</td><td>${formatDate(projectData.launchDate)} - ${formatDate(projectData.saleEndDate)}</td></tr>
            <tr><td>Token Ticker</td><td>${projectData.projectTicker || 'N/A'}</td></tr>
            <tr><td>Token Price</td><td>${formatCurrency(projectData.tokenPrice, 6)}</td></tr>
            <tr><td>Vesting</td><td>${vestingText}</td></tr>
            <tr><td>Total Supply</td><td>${formatNumber(projectData.totalSupply)} ${projectData.supplyType || ''}</td></tr>
            <tr><td>Target Raise (Hard Cap)</td><td>${formatCurrency(projectData.targetRaise, 0)}</td></tr>
            <tr><td>Soft Cap (USD)</td><td>${formatCurrency(projectData.softCap, 0)}</td></tr>
            <tr><td>Min/Max Purchase</td><td>${formatCurrency(projectData.minPurchase, 0)} / ${formatCurrency(projectData.maxPurchase, 0)}</td></tr>
            <tr><td>Eligibility</td><td>${eligibility.length > 0 ? eligibility.join(', ') : 'All eligible'}</td></tr>
        `;

        const whyContainer = document.getElementById('why-project-content');
        whyContainer.innerHTML = (projectData.valueProps && projectData.valueProps.length > 0)
            ? projectData.valueProps.map(p => `<div class="p-4 border-b last:border-b-0"><h4 class="font-bold text-lg mb-1">${p.title}</h4><p class="text-gray-600">${p.description}</p></div>`).join('')
            : '<p class="text-center text-gray-500">No value propositions provided.</p>';

         const utilitiesContainer = document.getElementById('token-utilities-list');
         utilitiesContainer.innerHTML = (projectData.tokenUtilities && projectData.tokenUtilities.length > 0)
            ? projectData.tokenUtilities.map(u => `<div class="inner-card"><i data-lucide="gem" class="w-8 h-8 text-purple-600 mb-2"></i><h4 class="font-bold">${u.name}</h4><p class="text-sm text-gray-600">${u.description}</p></div>`).join('')
            : '<p class="col-span-full text-center text-gray-500">No utilities defined.</p>';

        const teamContainer = document.getElementById('team-list-cards');
        const defaultAvatar = (name) => `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=e0e0e0&color=333`;
        teamContainer.innerHTML = (projectData.teamData && projectData.teamData.length > 0)
            ? projectData.teamData.map(m => `<div class="inner-card"><img src="${m.picture_file_path ? `/uploads/${m.picture_file_path}` : defaultAvatar(m.name)}" class="team-member-image" onerror="this.onerror=null;this.src='${defaultAvatar(m.name)}';"><p class="font-bold">${m.name}</p><p class="text-sm text-purple-600">${m.role}</p></div>`).join('')
            : '<p class="col-span-full text-center text-gray-500">Team not specified.</p>';

        const docs = [...(projectData.docLinks || []), ...(projectData.complianceDocsOnly || [])];
        const docsContainer = document.getElementById('compliance-documents-list');
        if (docs.length > 0) {
            docsContainer.className = 'grid grid-cols-2 md:grid-cols-4 gap-6 max-w-4xl mx-auto';
            docsContainer.innerHTML = docs.map(d => {
                const tag = d.is_download ? 'a' : 'button';
                const href = d.is_download ? `href="/uploads/${d.url}" target="_blank"` : `type="button"`;
                const modalTrigger = d.modal_trigger ? 'data-modal-trigger="agreement-view"' : '';
                
                return `
                <${tag} ${href} ${modalTrigger} class="inner-card group hover:border-purple-400 hover:shadow-lg">
                    <i data-lucide="${d.icon}" class="w-12 h-12 text-purple-600 mb-3 transition-transform group-hover:scale-110"></i>
                    <h4 class="font-bold text-gray-800 text-sm">${d.label || d.name}</h4>
                </${tag}>
            `;
            }).join('');
        } else {
            docsContainer.className = 'text-gray-500';
            docsContainer.innerHTML = 'No documents provided.';
        }
        
        // --- NEW LOGIC: Dynamic Terms Rendering + Parse Custom JSON ---
        const termsContainer = document.getElementById('compliance-disclaimer-content');
        if (termsContainer) {
            termsContainer.innerHTML = ''; // Clear previous
            
            const p = document.createElement('p');
            p.textContent = "By participating in this token sale, you acknowledge and agree to the following terms regarding eligibility and regulatory compliance:";
            termsContainer.appendChild(p);

            const addTerm = (title, text) => {
                const div = document.createElement('div');
                div.innerHTML = `<span class="font-semibold text-gray-800">${title}:</span> ${text}`;
                termsContainer.appendChild(div);
            };
            
            const settings = projectData.complianceSettings || {};
            if (settings.kyc_required) addTerm("KYC Verification", "Participation in this token sale requires Identity Verification (KYC).");
            if (settings.exclude_sanctioned) addTerm("Sanctioned Jurisdictions", "Participation is strictly prohibited for individuals or entities in sanctioned jurisdictions.");
            if (settings.exclude_us_non_accredited) addTerm("U.S. Participants", "Available only to accredited investors under applicable U.S. law. Retail U.S. participation is prohibited.");
            if (settings.require_eu_consent) addTerm("EU Residents", "Explicit consent for data processing under GDPR is required.");
            
            // Handle Custom Restrictions JSON
            try {
                const customRestrictions = JSON.parse(settings.custom_country_disclaimer || '[]');
                if (Array.isArray(customRestrictions) && customRestrictions.length > 0) {
                    customRestrictions.forEach(cr => {
                        if(cr.country && cr.disclaimer) {
                            addTerm(`${cr.country} Restrictions`, cr.disclaimer);
                        }
                    });
                }
            } catch(e) {
                // Ignore parsing errors or fallback for legacy text
            }
        }
        // --- END NEW LOGIC ---
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
        renderCharts();
    }

    function renderChartPlaceholder(canvasId, msg) { const canvas = document.getElementById(canvasId); if (canvas) canvas.parentElement.innerHTML = `<div class="h-full flex items-center justify-center text-gray-400 p-4">${msg}</div>`; }
    
    function renderCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(renderCharts, 100);
            return;
        }

        const { distributionData, detailedVestingSchedule, totalSupply } = projectData;

        const pieCtx = document.getElementById('distribution-pie-chart')?.getContext('2d');
        if (pieCtx && distributionData && distributionData.labels && distributionData.labels.length > 0) {
            new Chart(pieCtx, { type: 'pie', data: { labels: distributionData.labels, datasets: [{ data: distributionData.data, backgroundColor: CHART_COLORS }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
        } else {
            renderChartPlaceholder('distribution-pie-chart', 'Distribution data not available.');
        }
        
        const numericTotalSupply = parseFloat(totalSupply);
        if (!detailedVestingSchedule || detailedVestingSchedule.length === 0 || isNaN(numericTotalSupply)) {
            renderChartPlaceholder('vesting-bar-chart', 'Vesting data not available.');
            renderChartPlaceholder('emission-line-chart', 'Emission data not available.');
            return;
        }

        const MONTHS = 48;
        const labels = Array.from({ length: MONTHS }, (_, i) => `M${i}`);
        let totalMonthlyInflation = new Array(MONTHS).fill(0);
        const categories = [...new Set(detailedVestingSchedule.map(s => s.category))];
        let categoryMonthlyUnlocks = {};
        categories.forEach(cat => categoryMonthlyUnlocks[cat] = new Array(MONTHS).fill(0));

        detailedVestingSchedule.forEach(item => {
            const totalTokens = (parseFloat(item.percentTotalSupply) / 100) * numericTotalSupply;
            const tgeAmount = (parseFloat(item.unlockAtTGE) / 100) * totalTokens;
            if (tgeAmount > 0) {
                totalMonthlyInflation[0] += tgeAmount;
                if(categoryMonthlyUnlocks[item.category]) categoryMonthlyUnlocks[item.category][0] += tgeAmount;
            }
            const remaining = totalTokens - tgeAmount;
            const vestingMonths = parseInt(item.vestingPeriod, 10) || 0;
            if (remaining > 0 && vestingMonths > 0) {
                const monthlyVest = remaining / vestingMonths;
                const startMonth = parseInt(item.cliff, 10) || 0;
                for (let m = 0; m < vestingMonths; m++) {
                    const monthIndex = startMonth + m;
                    if (monthIndex < MONTHS) {
                        totalMonthlyInflation[monthIndex] += monthlyVest;
                        if(categoryMonthlyUnlocks[item.category]) categoryMonthlyUnlocks[item.category][monthIndex] += monthlyVest;
                    }
                }
            }
        });

        const commonOptions = (yLabel, isStacked = false) => ({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: isStacked, position: 'bottom' } }, scales: { x: { title: { display: true, text: 'Months Since TGE' } }, y: { stacked: isStacked, beginAtZero: true, title: { display: true, text: yLabel }, ticks: { callback: (val) => formatNumber(val) } } } });
        
        const barCtx = document.getElementById('vesting-bar-chart')?.getContext('2d');
        if(barCtx) new Chart(barCtx, { type: 'bar', data: { labels, datasets: [{ label: 'Monthly Inflation', data: totalMonthlyInflation, backgroundColor: CHART_COLORS[1] }] }, options: commonOptions('Tokens Unlocked') });
        
        const emissionDatasets = categories.map((cat, index) => {
            const cumulativeData = [];
            let runningTotal = 0;
            for (let i = 0; i < MONTHS; i++) {
                runningTotal += (categoryMonthlyUnlocks[cat]?.[i] || 0);
                cumulativeData.push(runningTotal);
            }
            return {
                label: cat, data: cumulativeData,
                borderColor: CHART_COLORS[index % CHART_COLORS.length],
                backgroundColor: CHART_COLORS[index % CHART_COLORS.length] + '30',
                fill: true, tension: 0.1, pointRadius: 0
            };
        });
        const lineCtx = document.getElementById('emission-line-chart')?.getContext('2d');
        if(lineCtx) new Chart(lineCtx, { type: 'line', data: { labels, datasets: emissionDatasets }, options: commonOptions('Cumulative Tokens Unlocked', true) });
    }
    
    renderPage();
    
    const modal = document.getElementById('confirmation-modal');
    const approveBtn = document.getElementById('approve-submit-button');
    const cancelBtn = document.getElementById('modal-cancel');
    const confirmBtn = document.getElementById('modal-confirm');
    const modalTitle = document.getElementById('modal-title');
    const modalMessage = document.getElementById('modal-message');

    if (approveBtn) {
        approveBtn.addEventListener('click', () => {
            modalTitle.textContent = 'Confirm Approval';
            modalMessage.textContent = `You are about to approve your private sale page for "${projectData.projectName}". You can follow the status in your private sale page.`;
            confirmBtn.textContent = 'Approve';
            modal.classList.add('active');
        });
    }

    if (cancelBtn) cancelBtn.addEventListener('click', () => modal.classList.remove('active'));
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Approving...';
            
            fetch('/backend/approve_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: projectData.project_id })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    modalTitle.textContent = 'Success!';
                    modalMessage.textContent = 'Your project has been submitted. Redirecting to dashboard...';
                    confirmBtn.style.display = 'none';
                    cancelBtn.textContent = 'Close';
                    setTimeout(() => { window.location.href = data.redirect_url; }, 2000);
                } else {
                    alert('Submission failed: ' + data.error);
                    modal.classList.remove('active');
                }
            })
            .catch(err => {
                alert('An error occurred. Please try again.');
                console.error(err);
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Approve';
            });
        });
    }

    const viewModal = document.getElementById('agreement-view-modal');
    const viewModalContent = document.getElementById('agreement-view-content');
    const closeViewBtn = document.getElementById('modal-close-view');
    
    function formatAgreementText(sections) {
        if (!Array.isArray(sections)) return '<p>No agreement content found.</p>';
        return sections.map(section => {
            const safeTitle = section.title.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            const safeBody = section.body.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            return `<h3>${safeTitle}</h3><pre>${safeBody}</pre>`;
        }).join('<hr class="my-4">');
    }

    const docListContainer = document.getElementById('compliance-documents-list');
    if (docListContainer) {
        docListContainer.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal-trigger="agreement-view"]');
            if (trigger) {
                if (viewModalContent) {
                    viewModalContent.innerHTML = formatAgreementText(agreementTextContent);
                }
                if (viewModal) {
                    viewModal.classList.add('active');
                }
            }
        });
    }
    
    if (closeViewBtn) closeViewBtn.addEventListener('click', () => viewModal.classList.remove('active'));
    if (viewModal) viewModal.addEventListener('click', (e) => { if (e.target === viewModal) viewModal.classList.remove('active'); });

});
</script>