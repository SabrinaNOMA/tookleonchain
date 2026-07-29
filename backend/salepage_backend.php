<?php
/**
 * Backend for the Token Sale Page.
 *
 * Fetches all necessary data for a specific project sale based on the
 * project ID and sale name stored in the user's session.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../src/db.php';

// --- Session Validation ---
if (!isset($_SESSION['selected_project_id']) || !isset($_SESSION['selected_sale_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No project sale has been selected. Please return to the discovery page.']);
    exit;
}
$projectId = $_SESSION['selected_project_id'];
$saleName = $_SESSION['selected_sale_name'];

$projectData = [];

try {
    $sql = "
        SELECT p.*, tsp.* FROM projet p
        JOIN token_sale_pages tsp ON p.id = tsp.project_id
        WHERE p.id = :project_id AND tsp.sale_name = :sale_name AND tsp.status = 'live'
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['project_id' => $projectId, 'sale_name' => $saleName]);
    $mainData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mainData) {
        http_response_code(404);
        echo json_encode(['error' => "The selected sale page could not be found or is no longer live."]);
        exit;
    }
    $projectData = $mainData;

    function decodeJsonSafely($jsonString, $default = []) {
        if ($jsonString === null || $jsonString === '') return $default;
        $decoded = json_decode($jsonString, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $default;
    }

    $jsonFields = ['general_images_json', 'community_metrics_json', 'value_props_json', 'team_json', 'partners_json', 'faqs_json', 'socials_json', 'project_roadmap_json', 'sale_terms_json'];
    foreach ($jsonFields as $field) {
        $projectData[$field] = decodeJsonSafely($projectData[$field] ?? null);
    }
    
    $stmt_utils = $pdo->prepare("SELECT utility_name as name, 'Default description' as description FROM utility_token WHERE projet_id = :project_id");
    $stmt_utils->execute(['project_id' => $projectId]);
    $projectData['token_utilities_json'] = $stmt_utils->fetchAll(PDO::FETCH_ASSOC);

    $stmt_comp = $pdo->prepare("SELECT * FROM compliance_settings WHERE projet_id = :project_id");
    $stmt_comp->execute(['project_id' => $projectId]);
    $projectData['complianceSettings'] = $stmt_comp->fetch(PDO::FETCH_ASSOC) ?: [];

    // --- MODIFICATION: Use sale_terms_json as SSOT and override base data ---
    $saleTerms = $projectData['sale_terms_json'] ?? [];
    $projectData['selectedRoundDetails'] = $saleTerms;

    // Override data from projet table with data from sale_terms_json if it exists.
    // This makes the JSON the single source of truth.
    if (isset($saleTerms['total_supply'])) {
        $projectData['total_supply'] = $saleTerms['total_supply'];
    }
    if (isset($saleTerms['type_supply'])) {
        $projectData['type_supply'] = $saleTerms['type_supply'];
    }
    // --- END MODIFICATION ---

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
    $stmt_vesting->execute(['project_id' => $projectId]);
    $vestingSchedules = $stmt_vesting->fetchAll(PDO::FETCH_ASSOC);
    $projectData['detailedVestingSchedule'] = $vestingSchedules;

    $distributionData = ['labels' => [], 'data' => []];
    foreach ($vestingSchedules as $vestingItem) {
        $distributionData['labels'][] = $vestingItem['category'];
        $distributionData['data'][] = (float)($vestingItem['percentTotalSupply'] ?? 0);
    }
    $projectData['distributionData'] = $distributionData;
    
    // --- MODIFICATION: Update keyMapping to use 'supply' ---
    $keyMapping = [
        'project_name' => 'projectName', 'token_ticker' => 'projectTicker',
        'hard_cap_usd' => 'targetRaise', 'soft_cap_usd' => 'softCap',
        'total_supply' => 'supply',
        'type_supply' => 'supplyType',
        'sale_launch_at' => 'launchDate',
        'sale_end_at' => 'saleEndDate', 'purchase_method' => 'purchaseOptions',
        'min_investment_usd' => 'minPurchase', 'max_investment_usd' => 'maxPurchase',
        'project_description_story' => 'projectDescription',
        'team_json' => 'teamData', 'partners_json' => 'partnerData',
        'faqs_json' => 'faqData', 'socials_json' => 'socialLinks',
        'project_roadmap_json' => 'project_roadmap_items', 'value_props_json' => 'valueProps',
        'token_utilities_json' => 'tokenUtilities', 'community_metrics_json' => 'communityStats',
        'video_file_path' => 'video_file_path', 'general_images_json' => 'general_images_json'
    ];
    // --- END MODIFICATION ---

    if (isset($projectData['selectedRoundDetails']['round_price'])) {
        $projectData['tokenPrice'] = $projectData['selectedRoundDetails']['round_price'];
    }

    foreach($keyMapping as $oldKey => $newKey) {
        if(isset($projectData[$oldKey])) {
            $projectData[$newKey] = $projectData[$oldKey];
        }
    }
    
    if (isset($_SESSION['user_id'])) {
        $stmt_user = $pdo->prepare("SELECT id, first_name, last_name, email FROM user WHERE id = ?");
        $stmt_user->execute([$_SESSION['user_id']]);
        $projectData['userInfo'] = $stmt_user->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode($projectData, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Salepage Backend Error for project $projectId / sale $saleName: " . $e->getMessage());
    echo json_encode(['error' => 'A server error occurred while fetching project data.']);
}
?>

