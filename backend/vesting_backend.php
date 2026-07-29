<?php
/**
 * Backend: Fetch and Save Token Distribution/Vesting Data
 * Filepath: /backend/distribution_backend.php
 *
 * Description: A unified backend script to handle both GET (fetch) and POST (save)
 * requests for the token distribution and vesting page.
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/db.php';

// --- Helper Functions ---
function send_json_error($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function applyVestingRules($blocks) {
    $VestingBase = 36; $VestingMin = 12; $CliffBase = 4; $CliffMin = 0;
    $UnlockBeforeLast = 5; $UnlockLast = 10; $UnlockIfPublic = 100;
    $OTHER_VESTING_CLIFFS = [
        'team' => ['vesting' => 48, 'cliff' => 12], 'ecosystem' => ['vesting' => 42, 'cliff' => 0],
        'treasury' => ['vesting' => 39, 'cliff' => 0], 'miscellaneous' => ['vesting' => 36, 'cliff' => 0],
    ];
    $rounds = array_filter($blocks, fn($b) => $b['type'] === 'round');
    $others = array_filter($blocks, fn($b) => $b['type'] === 'other');
    usort($rounds, fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));
    $totalRounds = count($rounds);
    foreach ($rounds as $index => &$round) {
        $isPublic = str_contains(strtolower($round['name']), 'public') || str_contains(strtolower($round['name']), 'ico') || str_contains(strtolower($round['name']), 'ido');
        if ($isPublic) {
            $round['vesting'] = 0; $round['cliff'] = 0; $round['unlockAtTGE'] = $UnlockIfPublic;
        } else {
            $round['vesting'] = max($VestingBase - $index * 6, $VestingMin);
            $round['cliff'] = max($CliffBase - $index, $CliffMin);
            $isLastNonPublic = true;
            for ($i = $index + 1; $i < $totalRounds; $i++) {
                if (!str_contains(strtolower($rounds[$i]['name']), 'public')) { $isLastNonPublic = false; break; }
            }
            $round['unlockAtTGE'] = $isLastNonPublic ? $UnlockLast : $UnlockBeforeLast;
        }
    }
    unset($round);
    foreach ($others as &$block) {
        $subtype = strtolower($block['subtype'] ?? '');
        $config = $OTHER_VESTING_CLIFFS[$subtype] ?? ['vesting' => 36, 'cliff' => 0];
        $block['vesting'] = $config['vesting']; $block['cliff'] = $config['cliff'];
        $block['unlockAtTGE'] = 0;
    }
    unset($block);
    return array_merge($rounds, $others);
}

// --- Request Handling ---
$request_method = $_SERVER['REQUEST_METHOD'];

try {
    if (empty($_SESSION['user_id'])) {
        send_json_error('User not authenticated.', 401);
    }
    $user_id = $_SESSION['user_id'];
    
    if ($request_method === 'GET') {
        // --- FETCH DATA LOGIC ---
        $project_id = $_GET['project_id'] ?? null;
        if (empty($project_id)) {
            send_json_error("Project ID is required.", 400);
        }

        $pdo->beginTransaction();
        
        $proj_stmt = $pdo->prepare("SELECT supply_value, calculated_price_tge FROM projet WHERE id = ? AND founder_id = ?");
        $proj_stmt->execute([$project_id, $user_id]);
        $project_data = $proj_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project_data) send_json_error("Project not found or you do not have access.", 404);
        
        $vesting_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM vesting_token WHERE projet_id = ?");
        $vesting_check_stmt->execute([$project_id]);
        $vesting_exists = $vesting_check_stmt->fetchColumn() > 0;
        
        $investorRounds = [];
        $distributionCategories = [];

        if ($vesting_exists) {
            $vesting_map = [];
            $vesting_stmt = $pdo->prepare("SELECT source_id, source_type, id, percent_unlock_at_tge, cliff_months, vesting_months FROM vesting_token WHERE projet_id = ?");
            $vesting_stmt->execute([$project_id]);
            foreach ($vesting_stmt->fetchAll(PDO::FETCH_ASSOC) as $v_row) {
                $vesting_map[$v_row['source_type'] . '-' . $v_row['source_id']] = $v_row;
            }

            $investor_stmt = $pdo->prepare("SELECT id, round_name, percent_round_supply FROM round_token WHERE projet_id = ? ORDER BY id");
            $investor_stmt->execute([$project_id]);
            foreach ($investor_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $vesting_data = $vesting_map['round-' . $row['id']] ?? [];
                $investorRounds[] = [
                    'id' => $vesting_data['id'] ?? 'investor-round-' . $row['id'], 'block' => $row['round_name'],
                    'percentSupply' => (float)$row['percent_round_supply'], 'unlockAtTGE' => (float)($vesting_data['percent_unlock_at_tge'] ?? 0),
                    'cliff' => (int)($vesting_data['cliff_months'] ?? 6), 'vesting' => (int)($vesting_data['vesting_months'] ?? 24),
                    'isInvestor' => true,
                ];
            }

            $other_stmt = $pdo->prepare("SELECT id, tranche_name, allocation_percent FROM tranche_token WHERE projet_id = ? AND tranche_type = 'other' ORDER BY id");
            $other_stmt->execute([$project_id]);
            foreach ($other_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $vesting_data = $vesting_map['tranche-' . $row['id']] ?? [];
                $distributionCategories[] = [
                    'id' => $vesting_data['id'] ?? 'other-tranche-' . $row['id'], 'block' => $row['tranche_name'],
                    'category' => $row['tranche_name'], 'percentSupply' => (float)$row['allocation_percent'],
                    'unlockAtTGE' => (float)($vesting_data['percent_unlock_at_tge'] ?? 0),
                    'cliff' => (int)($vesting_data['cliff_months'] ?? 0), 'vesting' => (int)($vesting_data['vesting_months'] ?? 12),
                    'isInvestor' => false,
                ];
            }
        } else {
            // Generate defaults if no vesting data exists
            $blocks = [];
            $rounds_stmt = $pdo->prepare("SELECT id, round_name, percent_round_supply FROM round_token WHERE projet_id = ? ORDER BY id");
            $rounds_stmt->execute([$project_id]);
            foreach ($rounds_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $blocks[] = ['db_id' => $row['id'], 'name' => $row['round_name'], 'type' => 'round', 'percentSupply' => (float)$row['percent_round_supply']];
            }

            $other_tranches_stmt = $pdo->prepare("SELECT id, tranche_name, allocation_percent FROM tranche_token WHERE projet_id = ? AND tranche_type = 'other' ORDER BY id");
            $other_tranches_stmt->execute([$project_id]);
            foreach ($other_tranches_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $blocks[] = ['db_id' => $row['id'], 'name' => $row['tranche_name'], 'type' => 'other', 'subtype' => $row['tranche_name'], 'percentSupply' => (float)$row['allocation_percent']];
            }
            
            $updatedBlocks = applyVestingRules($blocks);
            foreach ($updatedBlocks as $block) {
                if ($block['type'] === 'round') {
                    $investorRounds[] = ['id' => 'investor-round-' . $block['db_id'], 'block' => $block['name'], 'percentSupply' => $block['percentSupply'], 'unlockAtTGE' => $block['unlockAtTGE'], 'cliff' => $block['cliff'], 'vesting' => $block['vesting'], 'isInvestor' => true];
                } else {
                    $distributionCategories[] = ['id' => 'other-tranche-' . $block['db_id'], 'block' => $block['name'], 'category' => $block['name'], 'percentSupply' => $block['percentSupply'], 'unlockAtTGE' => $block['unlockAtTGE'], 'cliff' => $block['cliff'], 'vesting' => $block['vesting'], 'isInvestor' => false];
                }
            }
        }
        
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'data' => [
                'totalTokenSupply' => (float)($project_data['supply_value'] ?? 0),
                'tokenPriceTGE' => (float)($project_data['calculated_price_tge'] ?? 0),
                'investorRounds' => $investorRounds,
                'distributionCategories' => $distributionCategories,
            ]
        ]);

    } elseif ($request_method === 'POST') {
        // --- SAVE DATA LOGIC ---
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['projectId'], $input['vestingData'])) {
            send_json_error('Invalid input data.', 400);
        }
        
        $project_id = $input['projectId'];
        $vestingSchedules = $input['vestingData'];
        $marketCapTGE = $input['marketCapTGE'] ?? 0;

        $pdo->beginTransaction();

        // Cleanup old data
        $pdo->prepare("DELETE FROM vesting_token WHERE projet_id = ?")->execute([$project_id]);
        $pdo->prepare("DELETE FROM tranche_token WHERE projet_id = ? AND tranche_type = 'other'")->execute([$project_id]);

        $stmt_insert_tranche = $pdo->prepare("INSERT INTO tranche_token (projet_id, tranche_name, tranche_type, allocation_percent) VALUES (?, ?, 'other', ?)");
        $stmt_insert_vesting = $pdo->prepare("INSERT INTO vesting_token (projet_id, source_id, source_type, tranche_type, vesting_block_name, percent_supply_vesting, percent_unlock_at_tge, cliff_months, vesting_months) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $findRoundIdStmt = $pdo->prepare("SELECT id FROM round_token WHERE projet_id = ? AND round_name = ?");

        foreach ($vestingSchedules as $vesting) {
            $isInvestor = $vesting['isInvestor'] ?? false;
            $blockName = trim($vesting['block']);
            $percentSupply = $vesting['percentSupply'] ?? 0;
            if (empty($blockName)) continue;

            $source_id = null;
            $source_type = $isInvestor ? 'round' : 'tranche';
            $tranche_type = $isInvestor ? 'investor' : 'other';

            if ($isInvestor) {
                $findRoundIdStmt->execute([$project_id, $blockName]);
                $source_id = $findRoundIdStmt->fetchColumn();
            } else {
                $stmt_insert_tranche->execute([$project_id, $vesting['category'] ?? $blockName, $percentSupply]);
                $source_id = $pdo->lastInsertId();
            }
            
            if ($source_id) {
                $stmt_insert_vesting->execute([
                    $project_id, $source_id, $source_type, $tranche_type, $blockName,
                    $percentSupply, $vesting['unlockAtTGE'] ?? 0, $vesting['cliff'] ?? 0, $vesting['vesting'] ?? 0
                ]);
            }
        }

        $pdo->prepare("UPDATE projet SET marketcap_at_tge = ? WHERE id = ?")->execute([$marketCapTGE, $project_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Distribution saved successfully.']);

    } else {
        send_json_error('Method not supported.', 405);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    send_json_error("An error occurred: " . $e->getMessage());
}
