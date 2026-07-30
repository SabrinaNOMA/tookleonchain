<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/src/db.php';
header('Content-Type: text/plain');
$id = $_GET['id'] ?? null;
if (!$id) { echo "No ID"; exit; }
$stmt = $pdo->prepare('SELECT * FROM token_sale_pages WHERE id = :id');
$stmt->execute(['id' => $id]);
$saleData = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$saleData) { echo "Sale not found"; exit; }
$scen = $saleData['scenario_version_id'];
$activeScenario = null;
if ($scen) {
    $stmt2 = $pdo->prepare('SELECT * FROM scenario_version WHERE id = :id');
    $stmt2->execute(['id' => $scen]);
    $activeScenario = $stmt2->fetch(PDO::FETCH_ASSOC);
}
$stmt3 = $pdo->prepare('SELECT * FROM projet WHERE id = :id');
$stmt3->execute(['id' => $saleData['project_id']]);
$projectData = $stmt3->fetch(PDO::FETCH_ASSOC);

$finalProjectData = [
    'projectName' => $projectData['project_name'] ?? 'Unknown Project',
    'tokenName' => $projectData['token_name'] ?? '',
    'tokenTicker' => $projectData['token_ticker'] ?? '',
    'projectDescription' => $saleData['project_description_story'] ?? 'No description provided.',
    'launchDate' => $saleData['sale_launch_at'] ?? null,
    'saleEndDate' => $saleData['sale_end_at'] ?? null,
    'status' => $saleData['status'] ?? 'draft',
    'minPurchase' => floatval($saleData['min_investment_usd'] ?? 0),
    'maxPurchase' => floatval($saleData['max_investment_usd'] ?? 0),
    'supply' => floatval($projectData['supply_value'] ?? 0),
    'supplyType' => $projectData['type_supply'] ?? '',
    'video_file_path' => $saleData['video_file_path'] ?? null,
    'general_images_json' => json_decode($saleData['general_images_json'] ?? '[]', true),
    'complianceSettings' => [],
];
$saleTerms = json_decode($saleData['sale_terms_json'] ?? '{}', true);
$finalProjectData['selectedRoundDetails'] = $saleTerms;
$finalProjectData['tokenPrice'] = floatval($saleTerms['round_price'] ?? 0);
$finalProjectData['targetRaise'] = floatval($saleTerms['round_amount'] ?? 0);

$stmt_comp = $pdo->prepare("SELECT * FROM compliance_settings WHERE projet_id = :id");
$stmt_comp->execute(['id' => $saleData['project_id']]);
$cs = $stmt_comp->fetch(PDO::FETCH_ASSOC);
if ($cs) {
    $finalProjectData['complianceSettings'] = [
        'kyc_required' => (bool)($cs['kyc_required'] ?? false),
        'exclude_sanctioned' => (bool)($cs['exclude_sanctioned'] ?? false),
        'exclude_us_non_accredited' => (bool)($cs['exclude_us_non_accredited'] ?? false),
        'require_eu_consent' => (bool)($cs['require_eu_consent'] ?? false),
        'custom_country_disclaimer' => $cs['custom_country_disclaimer'] ?? '[]'
    ];
}

$vestingSchedules = [];
if ($activeScenario && !empty($activeScenario['data'])) {
    $snapshot = is_string($activeScenario['data']) ? json_decode($activeScenario['data'], true) : $activeScenario['data'];
    if ($snapshot && !empty($snapshot['vesting'])) {
        $supplyMap = [];
        if (!empty($snapshot['rounds'])) { foreach ($snapshot['rounds'] as $r) { $supplyMap['round-' . $r['id']] = floatval($r['percent_round_supply'] ?? 0); } }
        if (!empty($snapshot['allocations'])) { foreach ($snapshot['allocations'] as $a) { $supplyMap['tranche-' . $a['id']] = floatval($a['allocation_percent'] ?? 0); } }
        foreach ($snapshot['vesting'] as $item) {
            $name = $item['vesting_block_name'] ?? $item['round_name'] ?? $item['tranche_name'] ?? 'Unknown';
            $supply = floatval($item['percent_supply_vesting'] ?? 0);
            if ($supply <= 0 && isset($item['source_type'], $item['source_id'])) {
                $key = $item['source_type'] . '-' . $item['source_id'];
                $supply = $supplyMap[$key] ?? 0;
            }
            $vestingSchedules[] = [
                'category' => $name,
                'percentTotalSupply' => $supply,
                'unlockAtTGE' => floatval($item['percent_unlock_at_tge'] ?? $item['unlock_tge'] ?? 0),
                'cliff' => intval($item['cliff_months'] ?? 0),
                'vestingPeriod' => intval($item['vesting_months'] ?? 0)
            ];
        }
    }
}
$finalProjectData['detailedVestingSchedule'] = $vestingSchedules;

$distributionData = ['labels' => [], 'data' => []];
foreach ($vestingSchedules as $vestingItem) {
    if ($vestingItem['percentTotalSupply'] > 0) {
        $distributionData['labels'][] = $vestingItem['category'];
        $distributionData['data'][] = (float)$vestingItem['percentTotalSupply'];
    }
}
$finalProjectData['distributionData'] = $distributionData;

echo "const projectDataGlobal = " . json_encode($finalProjectData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_NUMERIC_CHECK) . ";\n";
