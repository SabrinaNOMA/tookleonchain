<?php
// backend/rounds_backend.php

// --- BAY VALLEY DEBUG V12: Added Token Price to JSON Blob ---
// --- UPDATED: Now calculates and injects 'token_price' into the JSON data before saving ---

// Start session and include all necessary configuration files
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (ob_get_level() > 0) ob_clean();
    echo json_encode($data);
    exit;
}

/**
 * Transforms older scenario data formats to the structure expected by the current frontend.
 */
function transform_scenario_data($data) {
    if (!isset($data['allocations']) && isset($data['vesting']) && is_array($data['vesting'])) {
        $new_allocations = [];
        foreach ($data['vesting'] as $item) {
            if (isset($item['source_type']) && $item['source_type'] === 'tranche') {
                $new_allocations[] = [
                    'tranche_name' => $item['vesting_block_name'],
                    'allocation_percent' => $item['percent_supply_vesting'],
                    'unlock_tge' => $item['percent_unlock_at_tge'],
                    'cliff_months' => $item['cliff_months'],
                    'vesting_months' => $item['vesting_months']
                ];
            }
        }
        $data['allocations'] = $new_allocations;
    }
    return $data;
}

try {
    // --- User Authentication ---
    $user = auth_check_user($pdo);
    $user_id = $user['id'];

    // --- Project ID Handling ---
    $project_id = null;
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $project_id = $input['project_id'] ?? null;
    } else { // GET
        $project_id = $_GET['project_id'] ?? null;
    }

    if (!$project_id) {
        $project_id = $_SESSION['active_project_id'] ?? null;
    }

    if (!$project_id) {
        send_json_response(['error' => 'Project ID is required.'], 400);
    }

    // --- Authorization check ---
    $auth_stmt = $pdo->prepare("SELECT COUNT(*) FROM projet WHERE id = ? AND founder_id = ?");
    $auth_stmt->execute([$project_id, $user_id]);
    if ($auth_stmt->fetchColumn() == 0) {
        send_json_response(['error' => 'You do not have permission to access this project.'], 403);
    }

    // --- Main Logic Router ---
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? null;

    if ($method === 'GET') {
        handle_get_request($pdo, $project_id, $action);
    } elseif ($method === 'POST') {
        handle_post_request($pdo, $project_id, $user_id, $input);
    } else {
        send_json_response(['error' => "Method $method not allowed."], 405);
    }

} catch (Throwable $e) {
    error_log("Rounds Backend Critical Failure: " . $e->getMessage());
    send_json_response(['error' => 'A critical server error occurred: ' . $e->getMessage()], 500);
}


function handle_get_request(PDO $pdo, $project_id, $action) {
    switch ($action) {
        case 'list_versions':
            list_scenario_versions($pdo, $project_id);
            break;
        case 'get_version':
            $version_id = $_GET['id'] ?? null;
            if (!$version_id) send_json_response(['error' => 'Version ID is required.'], 400);
            get_scenario_version($pdo, $project_id, $version_id);
            break;
        case 'get_latest':
            get_active_or_latest_version($pdo, $project_id);
            break;
        case 'get_investments_for_round':
            $round_name = $_GET['round_name'] ?? '';
            if (empty($round_name)) {
                send_json_response(['investments' => []]);
                return;
            }
            get_investments_for_round($pdo, $project_id, $round_name);
            break;
        case 'get_all_investments':
            get_all_investments($pdo, $project_id);
            break;
        case 'get_total_raised':
            get_total_raised_amount($pdo, $project_id);
            break;
        case 'check_live_sale':
            check_for_live_sale($pdo, $project_id);
            break;
        default:
            get_active_or_latest_version($pdo, $project_id);
            break;
    }
}

function validate_scenario_data(array $core_params, array $rounds, array $allocations, float $totalSupplyPercentFromRounds) {
    $total_round_percent = 0;
    foreach ($rounds as $round) {
        $total_round_percent += floatval($round['percent_total_raise'] ?? 0);
    }
    if (abs($total_round_percent - 100.0) > 0.01) {
        throw new Exception("The sum of all fundraising round percentages must be 100%. Current sum: " . round($total_round_percent, 2) . "%");
    }

    $total_allocation_percent = $totalSupplyPercentFromRounds;
    foreach ($allocations as $alloc) {
        $percent = floatval($alloc['allocation_percent'] ?? 0);
        if ($percent < 0) throw new Exception("Allocation percentages cannot be negative.");
        $total_allocation_percent += $percent;
    }
    
    // Increased tolerance slightly for floating point math
    if (abs($total_allocation_percent - 100.0) > 0.1) {
        throw new Exception("The sum of all tranche allocations must be 100%. Current sum: " . round($total_allocation_percent, 2) . "%");
    }

    $fields_to_check = [
        'target_raise_usd' => $core_params['target_raise_usd'] ?? 0,
        'calculated_price_tge' => $core_params['calculated_price_tge'] ?? 0
    ];
    if (($core_params['type_supply'] ?? 'capped') === 'capped') {
        $fields_to_check['supply_value'] = $core_params['supply_value'] ?? 0;
    } else {
        $fields_to_check['supply_value'] = $core_params['supply_value'] ?? 0;
    }
    foreach ($fields_to_check as $field => $value) {
        if (floatval($value) < 0) throw new Exception("Core parameter '$field' cannot be negative.");
    }

    foreach ($rounds as $index => $round) {
        $round_name = $round['round_name'] ?? ('#' . ($index + 1));
        $unlock_tge = floatval($round['unlock_tge'] ?? 0);
        $cliff = intval($round['cliff_months'] ?? 0);
        $vesting = intval($round['vesting_months'] ?? 0);

        if ($unlock_tge == 100 && ($cliff != 0 || $vesting != 0)) {
            throw new Exception("For round '{$round_name}', if TGE Unlock is 100%, Cliff and Vesting must both be 0.");
        }
        if ($cliff == 0 && $vesting == 0 && $unlock_tge != 100) {
            throw new Exception("For round '{$round_name}', if Cliff and Vesting are 0, TGE Unlock must be 100%.");
        }
    }
    
    foreach ($allocations as $index => $alloc) {
        $tranche_name = $alloc['tranche_name'] ?? ('#' . ($index + 1));
        $unlock_tge = floatval($alloc['unlock_tge'] ?? 0);
        $cliff = intval($alloc['cliff_months'] ?? 0);
        $vesting = intval($alloc['vesting_months'] ?? 0);

        if ($unlock_tge == 100 && ($cliff != 0 || $vesting != 0)) {
            throw new Exception("For allocation '{$tranche_name}', if TGE Unlock is 100%, Cliff and Vesting must both be 0.");
        }
        if ($cliff == 0 && $vesting == 0 && $unlock_tge != 100) {
            throw new Exception("For allocation '{$tranche_name}', if Cliff and Vesting are 0, TGE Unlock must be 100%.");
        }
    }
}

function get_raised_for_round_internal(PDO $pdo, $project_id, $round_name) {
    $stmt = $pdo->prepare("
        SELECT SUM(i.amount_usd) as total_for_round
        FROM investments i
        WHERE i.project_id = ? AND i.investment_round = ? AND EXISTS (
            SELECT 1 FROM payments p WHERE p.investment_id = i.id AND LOWER(p.status) = 'successful'
        )
    ");
    $stmt->execute([$project_id, $round_name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return floatval($result['total_for_round'] ?? 0);
}


/**
 * Handles saving the scenario data, creating a version snapshot, and updating the domain tables.
 */
function handle_post_request(PDO $pdo, $project_id, $user_id, $data) {
    if (empty($data)) {
        send_json_response(['error' => 'Invalid or empty JSON payload.'], 400);
    }

    $pdo->beginTransaction();
    try {
        $live_sale_stmt = $pdo->prepare("SELECT COUNT(*) FROM token_sale_pages WHERE project_id = ? AND status = 'live'");
        $live_sale_stmt->execute([$project_id]);
        if ($live_sale_stmt->fetchColumn() > 0) {
            throw new Exception("Editing is disabled because there is a live sale for this project.");
        }

        $core_params = $data['core_params'] ?? [];
        $rounds = $data['rounds'] ?? [];
        $allocations = $data['allocations'] ?? [];
        $is_active = $data['make_active'] ?? false;
        
        // --- 1. Preparation and Validation ---
        $tge_price = floatval($core_params['calculated_price_tge'] ?? 0);
        $total_target_raise = floatval($core_params['target_raise'] ?? 0);
        $supply = floatval($core_params['supply_value'] ?? 0);
        
        if ($total_target_raise > 0) {
            foreach ($rounds as $round) {
                $round_name = $round['round_name'] ?? '';
                if (empty($round_name)) continue;

                $actual_raised = get_raised_for_round_internal($pdo, $project_id, $round_name);
                if ($actual_raised > 0) {
                    $min_raise_percent = ($actual_raised / $total_target_raise) * 100;
                    $current_raise_percent = floatval($round['percent_total_raise'] ?? 0);

                    if (($current_raise_percent + 0.001) < $min_raise_percent) {
                         throw new Exception("For round '{$round_name}', the '% Total Raise' is below the minimum required based on funds already raised.");
                    }
                }
            }
        }
        
        $totalSupplyPercentFromRounds = 0;
        
        // --- UPDATED LOGIC: Calculate price and inject into rounds array for JSON save ---
        // We use reference &$round to modify the array in place
        foreach ($rounds as &$round) {
            $round_price = $tge_price * (1 - (floatval($round['percent_discount'] ?? 0) / 100));
            
            // Inject calculated price into the array so it is saved in the JSON
            $round['token_price'] = $round_price;
            
            if ($supply > 0) {
                $amount_raised = $total_target_raise * (floatval($round['percent_total_raise'] ?? 0) / 100);
                $num_tokens = ($round_price > 0) ? $amount_raised / $round_price : 0;
                $totalSupplyPercentFromRounds += ($num_tokens / $supply) * 100;
            }
        }
        unset($round); // Important: break the reference
        
        validate_scenario_data($core_params, $rounds, $allocations, $totalSupplyPercentFromRounds);

        // --- 2. Insert new snapshot row ---
        // $rounds now includes 'token_price' inside each round object
        $payload_json = json_encode(['core_params' => $core_params, 'rounds' => $rounds, 'allocations' => $allocations]);
        
        $stmt_last_version = $pdo->prepare("SELECT COUNT(*) FROM scenario_version WHERE projet_id = ?");
        $stmt_last_version->execute([$project_id]);
        $new_version_number = $stmt_last_version->fetchColumn() + 1;
        $new_version_label = "Version " . str_pad($new_version_number, 2, '0', STR_PAD_LEFT);

        if ($is_active) {
            $stmt_deactivate = $pdo->prepare("UPDATE scenario_version SET is_active = 0 WHERE projet_id = ?");
            $stmt_deactivate->execute([$project_id]);
        }
        
        $sql_version = "INSERT INTO scenario_version (projet_id, version_label, data, created_at, is_active) VALUES (?, ?, ?, NOW(), ?)";
        $stmt_version = $pdo->prepare($sql_version);
        $stmt_version->execute([$project_id, $new_version_label, $payload_json, $is_active ? 1 : 0]);
        $new_version_id = $pdo->lastInsertId();

        // --- 3. Update domain tables ---
        
        // 3.1 Update `projet` table
        $sql_projet = "UPDATE projet SET 
            token_name = :token_name, token_ticker = :token_ticker, type_supply = :type_supply, 
            supply_value = :supply_value, calculated_price_tge = :calculated_price_tge, 
            target_raise_usd = :target_raise_usd
            WHERE id = :project_id";
        $stmt_projet = $pdo->prepare($sql_projet);
        $stmt_projet->execute([
            ':token_name' => $core_params['token_name'] ?? null,
            ':token_ticker' => $core_params['token_ticker'] ?? null,
            ':type_supply' => $core_params['type_supply'] ?? 'capped',
            ':supply_value' => $core_params['supply_value'] ?? null,
            ':calculated_price_tge' => $core_params['calculated_price_tge'] ?? null,
            ':target_raise_usd' => $core_params['target_raise'] ?? null,
            ':project_id' => $project_id
        ]);
        
        // Tracking for vesting table insertion
        $inserted_tranche_map = []; // name => ['id' => int, 'percent' => float]
        $inserted_round_map = [];   // name => ['id' => int, 'percent' => float]

        // 3.2 Update `tranche_token`
        $pdo->prepare("DELETE FROM tranche_token WHERE projet_id = ?")->execute([$project_id]);
        
        // FIX: Include 'tranche_type' in INSERT (Required by DB)
        $sql_tranche = "INSERT INTO tranche_token (projet_id, tranche_name, tranche_type, allocation_percent) VALUES (:projet_id, :tranche_name, :tranche_type, :allocation_percent)";
        $stmt_tranche = $pdo->prepare($sql_tranche);
        
        // Insert User-defined Allocations
        if (!empty($allocations)) {
            foreach ($allocations as $alloc) {
                if (!empty($alloc['tranche_name'])) {
                    $stmt_tranche->execute([
                        ':projet_id' => $project_id,
                        ':tranche_name' => $alloc['tranche_name'],
                        ':tranche_type' => 'other', // Default for custom allocations
                        ':allocation_percent' => $alloc['allocation_percent'] ?? 0
                    ]);
                    $inserted_tranche_map[$alloc['tranche_name']] = [
                        'id' => $pdo->lastInsertId(),
                        'percent' => $alloc['allocation_percent'] ?? 0
                    ];
                }
            }
        }
        
        // Insert Investor Tranche
        $stmt_tranche->execute([
            ':projet_id' => $project_id, 
            ':tranche_name' => 'Investors', 
            ':tranche_type' => 'investor', 
            ':allocation_percent' => $totalSupplyPercentFromRounds
        ]);
        // Note: We don't necessarily need to map the 'Investors' aggregate tranche for detailed vesting rows, 
        // as vesting rows for investors usually map to specific Rounds.

        // 3.3 Update `round_token`
        $pdo->prepare("DELETE FROM round_token WHERE projet_id = ?")->execute([$project_id]);
        
        if (!empty($rounds)) {
            // FIX: Include 'round_price' in INSERT (Required by DB)
            $sql_round = "INSERT INTO round_token (projet_id, round_name, percent_total_raise, percent_discount, round_amount, number_of_token, percent_round_supply, round_price) VALUES (:projet_id, :round_name, :percent_total_raise, :percent_discount, :round_amount, :number_of_token, :percent_round_supply, :round_price)";
            $stmt_round = $pdo->prepare($sql_round);
            
            foreach($rounds as $round) {
                if(!empty($round['round_name'])) {
                    // Note: 'token_price' was calculated above, but we recalculate vars here for the DB insert to be safe and explicit
                    $round_amount = $total_target_raise * (floatval($round['percent_total_raise'] ?? 0) / 100);
                    $round_price = $tge_price * (1 - (floatval($round['percent_discount'] ?? 0) / 100));
                    $num_tokens = ($round_price > 0) ? $round_amount / $round_price : 0;
                    $percent_supply = ($supply > 0) ? ($num_tokens / $supply) * 100 : 0;
                    
                    $stmt_round->execute([
                        ':projet_id' => $project_id,
                        ':round_name' => $round['round_name'],
                        ':percent_total_raise' => $round['percent_total_raise'] ?? 0,
                        ':percent_discount' => $round['percent_discount'] ?? 0,
                        ':round_amount' => $round_amount,
                        ':number_of_token' => $num_tokens,
                        ':percent_round_supply' => $percent_supply,
                        ':round_price' => $round_price
                    ]);
                    
                    $inserted_round_map[$round['round_name']] = [
                        'id' => $pdo->lastInsertId(),
                        'percent' => $percent_supply
                    ];
                }
            }
        }

        // 3.4 Update `vesting_token`
        $pdo->prepare("DELETE FROM vesting_token WHERE projet_id = ?")->execute([$project_id]);
        
        // FIX: Include source_id, source_type, tranche_type, percent_supply_vesting (Required by DB)
        $sql_vesting = "INSERT INTO vesting_token 
            (projet_id, vesting_block_name, source_id, source_type, tranche_type, percent_supply_vesting, percent_unlock_at_tge, cliff_months, vesting_months) 
            VALUES 
            (:projet_id, :vesting_block_name, :source_id, :source_type, :tranche_type, :percent_supply_vesting, :percent_unlock_at_tge, :cliff_months, :vesting_months)";
        $stmt_vesting = $pdo->prepare($sql_vesting);
        
        // Insert Vesting for Rounds
        foreach ($rounds as $round) {
            $name = $round['round_name'] ?? '';
            if (isset($inserted_round_map[$name])) {
                $round_info = $inserted_round_map[$name];
                $stmt_vesting->execute([
                    ':projet_id' => $project_id,
                    ':vesting_block_name' => $name,
                    ':source_id' => $round_info['id'],
                    ':source_type' => 'round',
                    ':tranche_type' => 'investor',
                    ':percent_supply_vesting' => $round_info['percent'],
                    ':percent_unlock_at_tge' => (float)($round['unlock_tge'] ?? 0),
                    ':cliff_months' => (int)($round['cliff_months'] ?? 0),
                    ':vesting_months' => (int)($round['vesting_months'] ?? 0)
                ]);
            }
        }
        
        // Insert Vesting for Allocations
        foreach ($allocations as $alloc) {
            $name = $alloc['tranche_name'] ?? '';
            if (isset($inserted_tranche_map[$name])) {
                $alloc_info = $inserted_tranche_map[$name];
                $stmt_vesting->execute([
                    ':projet_id' => $project_id,
                    ':vesting_block_name' => $name,
                    ':source_id' => $alloc_info['id'],
                    ':source_type' => 'tranche',
                    ':tranche_type' => 'other',
                    ':percent_supply_vesting' => $alloc_info['percent'],
                    ':percent_unlock_at_tge' => (float)($alloc['unlock_tge'] ?? 0),
                    ':cliff_months' => (int)($alloc['cliff_months'] ?? 0),
                    ':vesting_months' => (int)($alloc['vesting_months'] ?? 0)
                ]);
            }
        }

        $pdo->commit();
        send_json_response(['success' => true, 'message' => "Scenario saved successfully as '$new_version_label'.", 'new_version_id' => $new_version_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Save Scenario Transaction Error for project $project_id: " . $e->getMessage());
        send_json_response(['success' => false, 'error' => 'Failed to save scenario.', 'details' => $e->getMessage()], 500);
    }
}


function list_scenario_versions(PDO $pdo, $project_id) {
    $stmt = $pdo->prepare("SELECT id, version_label, created_at, is_active FROM scenario_version WHERE projet_id = ? ORDER BY created_at DESC");
    $stmt->execute([$project_id]);
    $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json_response($versions);
}

function get_scenario_version(PDO $pdo, $project_id, $version_id) {
    $stmt = $pdo->prepare("SELECT data, is_active FROM scenario_version WHERE id = ? AND projet_id = ?");
    $stmt->execute([$version_id, $project_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $decoded_data = json_decode($result['data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $transformed_data = transform_scenario_data($decoded_data);
            $transformed_data['is_active'] = $result['is_active'];
            send_json_response($transformed_data);
        } else {
            send_json_response(['error' => 'Corrupted scenario data found.'], 500);
        }
    } else {
        send_json_response(['error' => 'Scenario version not found.'], 404);
    }
}

function get_active_or_latest_version(PDO $pdo, $project_id) {
    $stmt_active = $pdo->prepare("SELECT data, is_active FROM scenario_version WHERE projet_id = ? AND is_active = 1 LIMIT 1");
    $stmt_active->execute([$project_id]);
    $result = $stmt_active->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $stmt_latest = $pdo->prepare("SELECT data, is_active FROM scenario_version WHERE projet_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt_latest->execute([$project_id]);
        $result = $stmt_latest->fetch(PDO::FETCH_ASSOC);
    }

    if ($result) {
        $decoded_data = json_decode($result['data'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $transformed_data = transform_scenario_data($decoded_data);
            $transformed_data['is_active'] = $result['is_active'];
            send_json_response($transformed_data);
        } else {
            send_json_response(['error' => 'Corrupted scenario data found for the latest version.'], 500);
        }
    } else {
        send_json_response(['core_params' => [], 'rounds' => [], 'allocations' => [], 'is_active' => 0], 200);
    }
}

function get_investments_for_round(PDO $pdo, $project_id, $round_name) {
    $stmt = $pdo->prepare("
        SELECT
            i.amount_usd as investment_amount,
            (SELECT p.status FROM payments p WHERE p.investment_id = i.id ORDER BY p.created_at DESC LIMIT 1) as payment_status,
            u.first_name,
            u.last_name,
            u.email
        FROM
            investments i
        JOIN
            user u ON i.user_id = u.id
        WHERE
            i.project_id = ? AND i.investment_round = ?
    ");
    $stmt->execute([$project_id, $round_name]);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json_response(['investments' => $investments]);
}

function get_all_investments(PDO $pdo, $project_id) {
    $stmt = $pdo->prepare("
        SELECT
            i.investment_round,
            i.amount_usd as investment_amount,
            (SELECT p.status FROM payments p WHERE p.investment_id = i.id ORDER BY p.created_at DESC LIMIT 1) as payment_status,
            u.first_name,
            u.last_name,
            u.email
        FROM
            investments i
        JOIN
            user u ON i.user_id = u.id
        WHERE
            i.project_id = ?
        ORDER BY
            i.created_at DESC
    ");
    $stmt->execute([$project_id]);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json_response(['investments' => $investments]);
}

function get_total_raised_amount(PDO $pdo, $project_id) {
    $stmt = $pdo->prepare("
        SELECT SUM(i.amount_usd) as total
        FROM investments i
        WHERE i.project_id = ? AND EXISTS (
            SELECT 1 FROM payments p WHERE p.investment_id = i.id AND LOWER(p.status) = 'successful'
        )
    ");
    $stmt->execute([$project_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    send_json_response(['total_raised' => $result['total'] ?? 0]);
}

function check_for_live_sale(PDO $pdo, $project_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as live_sales_count FROM token_sale_pages WHERE project_id = ? AND status = 'live'");
    $stmt->execute([$project_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_live = ($result && $result['live_sales_count'] > 0);
    send_json_response(['is_live' => $is_live]);
}
?>