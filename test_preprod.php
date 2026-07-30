<?php
/**
 * Automated Preprod Test Suite
 * Tests: scenario linkage, vesting data, distribution data, sale page integrity
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/src/db.php';

header('Content-Type: application/json');

$results = ['tests' => [], 'passed' => 0, 'failed' => 0, 'total' => 0];

function test($name, $condition, $detail = '') {
    global $results;
    $results['total']++;
    $status = $condition ? 'PASS' : 'FAIL';
    if ($condition) $results['passed']++; else $results['failed']++;
    $results['tests'][] = ['name' => $name, 'status' => $status, 'detail' => $detail];
}

try {
    // ============================================================
    // TEST GROUP 1: Database Integrity
    // ============================================================

    // 1.1 All sale pages have scenario_version_id
    $stmt = $pdo->query("SELECT id, project_id, sale_name, scenario_version_id, status FROM token_sale_pages");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    test('Sales exist in DB', count($sales) > 0, 'Found ' . count($sales) . ' sale(s)');

    $orphanedSales = [];
    foreach ($sales as $sale) {
        if (empty($sale['scenario_version_id'])) {
            $orphanedSales[] = $sale['sale_name'] . ' (id=' . $sale['id'] . ')';
        }
    }
    test('All sales have scenario_version_id', empty($orphanedSales), 
        empty($orphanedSales) ? 'All sales linked' : 'Missing: ' . implode(', ', $orphanedSales));

    // 1.2 All referenced scenario_versions exist and have data
    $stmt2 = $pdo->query("
        SELECT tsp.id as sale_id, tsp.sale_name, tsp.scenario_version_id, 
               sv.id as sv_id, LENGTH(sv.data) as data_length
        FROM token_sale_pages tsp
        LEFT JOIN scenario_version sv ON tsp.scenario_version_id = sv.id
        WHERE tsp.scenario_version_id IS NOT NULL
    ");
    $linked = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    $brokenLinks = [];
    $emptySnapshots = [];
    foreach ($linked as $row) {
        if ($row['sv_id'] === null) {
            $brokenLinks[] = $row['sale_name'];
        } elseif ($row['data_length'] < 10) {
            $emptySnapshots[] = $row['sale_name'];
        }
    }
    test('All scenario_version references are valid', empty($brokenLinks),
        empty($brokenLinks) ? 'All references valid' : 'Broken: ' . implode(', ', $brokenLinks));
    test('All snapshots have data', empty($emptySnapshots),
        empty($emptySnapshots) ? 'All snapshots populated' : 'Empty: ' . implode(', ', $emptySnapshots));

    // ============================================================
    // TEST GROUP 2: Snapshot Data Integrity
    // ============================================================

    $stmt3 = $pdo->query("
        SELECT tsp.id, tsp.sale_name, tsp.project_id, tsp.scenario_version_id, sv.data
        FROM token_sale_pages tsp
        JOIN scenario_version sv ON tsp.scenario_version_id = sv.id
    ");
    $salesWithData = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    foreach ($salesWithData as $sale) {
        $prefix = "[{$sale['sale_name']}] ";
        $snapshot = json_decode($sale['data'], true);
        
        test($prefix . 'Snapshot JSON is valid', $snapshot !== null && json_last_error() === JSON_ERROR_NONE);
        
        if ($snapshot) {
            // Core params
            test($prefix . 'Has core_params', !empty($snapshot['core_params']),
                isset($snapshot['core_params']['project_name']) ? $snapshot['core_params']['project_name'] : '');
            
            // Rounds
            $hasRounds = !empty($snapshot['rounds']) && is_array($snapshot['rounds']);
            test($prefix . 'Has rounds', $hasRounds, $hasRounds ? count($snapshot['rounds']) . ' round(s)' : 'No rounds');
            
            // Vesting
            $hasVesting = !empty($snapshot['vesting']) && is_array($snapshot['vesting']);
            test($prefix . 'Has vesting data', $hasVesting, $hasVesting ? count($snapshot['vesting']) . ' vesting block(s)' : 'No vesting');
            
            // Vesting has percent_supply_vesting
            if ($hasVesting) {
                $vestingWithSupply = 0;
                foreach ($snapshot['vesting'] as $v) {
                    if (floatval($v['percent_supply_vesting'] ?? 0) > 0) $vestingWithSupply++;
                }
                test($prefix . 'Vesting blocks have percent_supply_vesting', $vestingWithSupply > 0,
                    "$vestingWithSupply/" . count($snapshot['vesting']) . " blocks have supply data");
            }
        }
    }

    // ============================================================
    // TEST GROUP 3: Salepage PHP Data Assembly
    // ============================================================

    // Simulate the salepage.php logic for each sale
    foreach ($salesWithData as $sale) {
        $prefix = "[{$sale['sale_name']}] Salepage: ";
        $snapshot = json_decode($sale['data'], true);
        if (!$snapshot) continue;

        $vestingSchedules = [];
        
        // Replicate salepage.php scenario logic
        if (!empty($snapshot['vesting']) && is_array($snapshot['vesting'])) {
            $supplyMap = [];
            if (!empty($snapshot['rounds'])) {
                foreach ($snapshot['rounds'] as $r) {
                    $supplyMap['round-' . $r['id']] = floatval($r['percent_round_supply'] ?? 0);
                }
            }
            if (!empty($snapshot['allocations'])) {
                foreach ($snapshot['allocations'] as $a) {
                    $supplyMap['tranche-' . $a['id']] = floatval($a['allocation_percent'] ?? 0);
                }
            }
            foreach ($snapshot['vesting'] as $item) {
                $name = $item['vesting_block_name'] ?? $item['round_name'] ?? $item['tranche_name'] ?? 'Unknown';
                $supply = floatval($item['percent_supply_vesting'] ?? 0);
                if ($supply <= 0 && isset($item['source_type'], $item['source_id'])) {
                    $key = $item['source_type'] . '-' . $item['source_id'];
                    $supply = $supplyMap[$key] ?? 0;
                }
                $vestingSchedules[] = [
                    'category' => $name,
                    'percentTotalSupply' => $supply
                ];
            }
        }

        test($prefix . 'Produces vesting schedules', count($vestingSchedules) > 0,
            count($vestingSchedules) . ' schedule(s)');

        // Distribution data
        $distributionData = ['labels' => [], 'data' => []];
        foreach ($vestingSchedules as $vi) {
            if ($vi['percentTotalSupply'] > 0) {
                $distributionData['labels'][] = $vi['category'];
                $distributionData['data'][] = $vi['percentTotalSupply'];
            }
        }
        test($prefix . 'Distribution has labels', count($distributionData['labels']) > 0,
            implode(', ', $distributionData['labels']));
        
        $totalPercent = array_sum($distributionData['data']);
        test($prefix . 'Total allocation is reasonable (>50%)', $totalPercent > 50,
            'Total: ' . round($totalPercent, 2) . '%');
    }

    // ============================================================
    // TEST GROUP 4: Fallback Logic (live domain tables)
    // ============================================================

    // For each project that has sales, verify domain tables have data
    $projectIds = array_unique(array_column($sales, 'project_id'));
    foreach ($projectIds as $pid) {
        $stmtR = $pdo->prepare("SELECT COUNT(*) FROM round_token WHERE projet_id = ?");
        $stmtR->execute([$pid]);
        $roundCount = $stmtR->fetchColumn();

        $stmtV = $pdo->prepare("SELECT COUNT(*) FROM vesting_token WHERE projet_id = ?");
        $stmtV->execute([$pid]);
        $vestingCount = $stmtV->fetchColumn();

        $stmtT = $pdo->prepare("SELECT COUNT(*) FROM tranche_token WHERE projet_id = ? AND tranche_type = 'other'");
        $stmtT->execute([$pid]);
        $trancheCount = $stmtT->fetchColumn();

        $shortId = substr($pid, 0, 8);
        test("[Project $shortId] Has rounds in domain tables", $roundCount > 0, "$roundCount round(s)");
        test("[Project $shortId] Has vesting in domain tables", $vestingCount > 0, "$vestingCount vesting block(s)");
        test("[Project $shortId] Has tranches in domain tables", $trancheCount > 0, "$trancheCount tranche(s)");
    }

    // ============================================================
    // TEST GROUP 5: validate_backend.php scenario linking
    // ============================================================

    // Verify that every project with tokenomics_done=1 has an active scenario
    $stmtDone = $pdo->query("
        SELECT p.id, p.project_name, p.tokenomics_done,
               (SELECT COUNT(*) FROM scenario_version sv WHERE sv.projet_id = p.id AND sv.is_active = 1) as active_scenarios
        FROM projet p
        WHERE p.tokenomics_done = 1
    ");
    $approvedProjects = $stmtDone->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($approvedProjects as $proj) {
        test("[{$proj['project_name']}] Approved project has active scenario", 
            $proj['active_scenarios'] > 0,
            $proj['active_scenarios'] . ' active scenario(s)');
    }

} catch (Exception $e) {
    $results['tests'][] = ['name' => 'CRITICAL ERROR', 'status' => 'FAIL', 'detail' => $e->getMessage()];
    $results['failed']++;
    $results['total']++;
}

$results['summary'] = $results['failed'] === 0 
    ? "ALL {$results['passed']}/{$results['total']} TESTS PASSED" 
    : "{$results['failed']} FAILED out of {$results['total']} tests";

echo json_encode($results, JSON_PRETTY_PRINT);
