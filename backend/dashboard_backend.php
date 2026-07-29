<?php
/**
 * Backend for the Founder's Single-Project Kanban Dashboard.
 * Includes logic for Project Switching and New Project Preparation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/db.php';

// Helper function to establish PDO connection if not set
function get_pdo() {
    global $pdo;
    if (!isset($pdo)) {
        require __DIR__ . '/../src/db.php';
    }
    if (!isset($pdo)) {
        throw new Exception("Database connection could not be established.");
    }
    return $pdo;
}

// Start output buffering to prevent stray whitespace from breaking JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    try {
        $pdo = get_pdo();
        $founder_id = $_SESSION['user_id'] ?? null;

        if (!$founder_id) {
            http_response_code(403);
            if (ob_get_length()) ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
            exit;
        }

        // --- ACTION: Prepare New Project (Clear Session) ---
        if ($action === 'prepare_new_project') {
            unset($_SESSION['active_project_id']);
            session_write_close(); 
            if (ob_get_length()) ob_end_clean();
            echo json_encode(['success' => true]);
            exit;
        }

        // --- ACTION: Switch Project (Update Session) ---
        if ($action === 'switch_project') {
            $target_project_id = $input['project_id'] ?? null;

            if (!$target_project_id) {
                http_response_code(400);
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Project ID missing.']);
                exit;
            }

            // Verify ownership and fetch the EXACT ID from the database
            $check_stmt = $pdo->prepare("SELECT id FROM projet WHERE id = ? AND founder_id = ?");
            $check_stmt->execute([$target_project_id, $founder_id]);
            $project_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($project_data) {
                $_SESSION['active_project_id'] = $project_data['id'];
                session_write_close(); // CRITICAL: Save session immediately
                
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => true]);
            } else {
                http_response_code(403);
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Project not found or access denied.']);
                exit;
            }
            exit;
        }

        // --- NEW ACTION: Sync Status from Chain (Oracle) ---
        if ($action === 'sync_status') {
            $sale_id = $input['sale_id'] ?? null;
            $chain_status = $input['chain_status'] ?? null;

            if (empty($sale_id) || !in_array($chain_status, ['ended_successful', 'ended_failed'])) {
                http_response_code(400);
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Invalid sync parameters.']);
                exit;
            }

            // Verify Ownership
            $stmt = $pdo->prepare("SELECT p.founder_id, tsp.project_id, tsp.sale_name FROM token_sale_pages tsp JOIN projet p ON tsp.project_id = p.id WHERE tsp.id = ?");
            $stmt->execute([$sale_id]);
            $sale_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale_info || $sale_info['founder_id'] != $founder_id) {
                http_response_code(403);
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Permission denied.']);
                exit;
            }

            // Update DB to match Chain
            $update_stmt = $pdo->prepare("
                UPDATE token_sale_pages 
                SET status = ?, sale_end_at = NOW() 
                WHERE id = ?
            ");
            $success = $update_stmt->execute([$chain_status, $sale_id]);

            // If Chain says FAILED, we must trigger refund logic
            if ($chain_status === 'ended_failed') {
                $refund_stmt = $pdo->prepare("
                    UPDATE investments i 
                    JOIN payments p ON i.id = p.investment_id 
                    SET i.status = 'refund_pending' 
                    WHERE i.project_id = ? 
                    AND i.sale_name COLLATE utf8mb4_unicode_ci = ? 
                    AND i.status = 'in_escrow' 
                    AND p.status = 'successful'
                ");
                $refund_stmt->execute([$sale_info['project_id'], $sale_info['sale_name']]);
            }

            if (ob_get_length()) ob_end_clean();
            echo json_encode(['success' => $success]);
            exit;
        }

        // --- EXISTING ACTIONS ---
        if (in_array($action, ['stop_sale', 'update_sale', 'cancel_sale', 'unschedule_sale'])) {
            $sale_id = $input['sale_id'] ?? null;

            if (empty($sale_id)) {
                http_response_code(400);
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Sale ID is missing.']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT p.founder_id, tsp.* FROM token_sale_pages tsp JOIN projet p ON tsp.project_id = p.id WHERE tsp.id = ?");
            $stmt->execute([$sale_id]);
            $sale_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale_info || $sale_info['founder_id'] != $founder_id) {
                http_response_code(403);
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Permission denied or sale not found.']);
                exit;
            }

            $is_external = strtolower($sale_info['hosting'] ?? 'tookle') !== 'tookle';

            if ($action === 'stop_sale') {
                if ($is_external) {
                    $outcome = $input['external_outcome'] ?? null;
                    if (!in_array($outcome, ['ended_successful', 'ended_failed'])) {
                        http_response_code(400);
                        if (ob_get_length()) ob_end_clean();
                        echo json_encode(['success' => false, 'error' => 'External sales require a specified outcome.']);
                        exit;
                    }

                    $update_stmt = $pdo->prepare("UPDATE token_sale_pages SET status = ?, sale_end_at = NOW() WHERE id = ? AND status = 'live'");
                    $update_stmt->execute([$outcome, $sale_id]);

                    if (ob_get_length()) ob_end_clean();
                    echo json_encode(['success' => true]);
                    exit;
                }

                // Internal Manual Stop
                $investment_stmt = $pdo->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN investments i ON p.investment_id = i.id WHERE i.project_id = ? AND i.sale_name COLLATE utf8mb4_unicode_ci = ? AND p.status = 'successful'");
                $investment_stmt->execute([$sale_info['project_id'], $sale_info['sale_name']]);
                $current_funding = (float)$investment_stmt->fetchColumn();
                $soft_cap = (float)$sale_info['soft_cap_usd'];

                $new_status = ($current_funding >= $soft_cap) ? 'ended_successful' : 'ended_failed';
                $update_stmt = $pdo->prepare("UPDATE token_sale_pages SET status = ?, sale_end_at = NOW() WHERE id = ? AND status = 'live'");
                $update_stmt->execute([$new_status, $sale_id]);
                
                if ($new_status === 'ended_failed') {
                    $refund_stmt = $pdo->prepare("
                        UPDATE investments i 
                        JOIN payments p ON i.id = p.investment_id 
                        SET i.status = 'refund_pending' 
                        WHERE i.project_id = ? 
                        AND i.sale_name COLLATE utf8mb4_unicode_ci = ? 
                        AND i.status = 'in_escrow' 
                        AND p.status = 'successful'
                    ");
                    $refund_stmt->execute([$sale_info['project_id'], $sale_info['sale_name']]);
                }

                if (ob_get_length()) ob_end_clean();
                if ($update_stmt->rowCount() > 0) echo json_encode(['success' => true]);
                else echo json_encode(['success' => false, 'error' => 'Could not stop sale.']);
                exit;
            }

            if ($action === 'cancel_sale') {
                if ($is_external) { http_response_code(400); if (ob_get_length()) ob_end_clean(); echo json_encode(['success' => false, 'error' => 'External sales cannot be canceled.']); exit; }
                $status = strtolower(trim($sale_info['status'] ?? 'draft'));
                if (!in_array($status, ['draft', 'scheduled'])) { http_response_code(400); if (ob_get_length()) ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Only draft/scheduled sales can be canceled.']); exit; }
                
                $update_stmt = $pdo->prepare("UPDATE token_sale_pages SET status = 'canceled', canceled_at = NOW() WHERE id = ?");
                $success = $update_stmt->execute([$sale_id]);
                
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => $success]);
                exit;
            }

            if ($action === 'unschedule_sale') {
                if ($is_external) { http_response_code(400); if (ob_get_length()) ob_end_clean(); echo json_encode(['success' => false, 'error' => 'External sales cannot be unscheduled.']); exit; }
                if ($sale_info['status'] !== 'scheduled') { http_response_code(400); if (ob_get_length()) ob_end_clean(); echo json_encode(['success' => false, 'error' => 'Only scheduled sales can be unscheduled.']); exit; }
                 $update_stmt = $pdo->prepare("UPDATE token_sale_pages SET status = 'draft', sale_launch_at = NULL, sale_end_at = NULL WHERE id = ?");
                 $success = $update_stmt->execute([$sale_id]);
                 if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => $success]);
                exit;
            }

            if ($action === 'update_sale') {
                $status_from_db = strtolower(trim($sale_info['status'] ?? 'draft'));
                $is_live_on_tookle = ($status_from_db === 'live' && !$is_external);
                $is_finished_on_tookle = in_array($status_from_db, ['ended_successful', 'ended_failed', 'canceled']) && !$is_external;

                if ($is_finished_on_tookle) {
                    http_response_code(400);
                    if (ob_get_length()) ob_end_clean();
                    echo json_encode(['success' => false, 'error' => 'Cannot edit a completed or canceled sale.']);
                    exit;
                }

                $editable_fields_frontend = [];
                if ($is_external) {
                    $editable_fields_frontend = ['sale_name', 'min_raise', 'max_raise', 'sale_url', 'hosting_platform', 'status', 'sale_launch_at', 'sale_end_at'];
                } else if ($is_live_on_tookle) {
                    $editable_fields_frontend = ['max_raise', 'sale_url'];
                } else {
                    $editable_fields_frontend = ['sale_name', 'min_raise', 'duration', 'max_raise', 'sale_url', 'hosting_platform'];
                }
                
                $set_clauses = [];
                $params = [];
                foreach ($editable_fields_frontend as $field) {
                    if (array_key_exists($field, $input)) {
                        $db_column = $field;
                        if ($field === 'duration') $db_column = 'duration_seconds';
                        if ($field === 'min_raise') $db_column = 'soft_cap_usd';
                        if ($field === 'max_raise') $db_column = 'hard_cap_usd';
                        if ($field === 'hosting_platform') $db_column = 'hosting';
                        
                        $value = $input[$field];
                        
                        if ($field === 'duration' && is_numeric($value)) {
                            $value = (int)$value * 86400; 
                        }

                        if (in_array($db_column, ['soft_cap_usd', 'hard_cap_usd', 'duration_seconds']) && ($value === '' || !is_numeric($value) || $value < 0)) $value = null;
                        if ($db_column === 'duration_seconds' && $value !== null && $value <= 0) $value = null;
                        if ($db_column === 'hosting' && empty($value)) $value = 'Tookle';
                        if (in_array($db_column, ['sale_launch_at', 'sale_end_at']) && empty($value)) $value = null;
                        if ($db_column === 'status' && empty($value)) $value = 'draft';

                        $set_clauses[] = "$db_column = ?";
                        $params[] = $value;
                    }
                }

                if (empty($set_clauses)) {
                    if (ob_get_length()) ob_end_clean();
                    echo json_encode(['success' => true, 'message' => 'No fields were submitted for update.']);
                    exit;
                }
                $params[] = $sale_id;
                $sql = "UPDATE token_sale_pages SET " . implode(', ', $set_clauses) . " WHERE id = ?";
                $update_stmt = $pdo->prepare($sql);
                $success = $update_stmt->execute($params);
                if (ob_get_length()) ob_end_clean();
                echo json_encode(['success' => $success, 'message' => 'Update successful.']);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard POST Action Error: " . $e->getMessage());
        http_response_code(500);
        if (ob_get_length()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'A server error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// --- Data Fetching for GET requests ---
$response = ['project_list' => [], 'active_project_details' => null, 'active_project_id' => null];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in.');
    }
    $founder_id = $_SESSION['user_id'];
    $pdo = get_pdo();

    $list_stmt = $pdo->prepare("SELECT id, project_name FROM projet WHERE founder_id = ? ORDER BY created_at DESC");
    $list_stmt->execute([$founder_id]);
    $all_projects = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['project_list'] = $all_projects;

    if (!empty($all_projects)) {
        
        $requested_id = $_SESSION['active_project_id'] ?? null;
        $is_valid = false;
        if ($requested_id) {
            foreach ($all_projects as $p) {
                if ($p['id'] == $requested_id) {
                    $is_valid = true;
                    break;
                }
            }
        }
        if (!$is_valid) {
            $_SESSION['active_project_id'] = $all_projects[0]['id'];
        }
        
        $active_project_id = $_SESSION['active_project_id'];
        $response['active_project_id'] = $active_project_id;

        $detail_stmt = $pdo->prepare("
            SELECT 
                p.*, 
                p.token_logo_path AS project_logo, 
                dt.contract as contract_address, 
                dt.network as contract_network
            FROM projet p
            LEFT JOIN deployed_token dt ON p.id = dt.projet_id AND dt.selected_contract = 'yes'
            WHERE p.id = ? AND p.founder_id = ?
        ");
        $detail_stmt->execute([$active_project_id, $founder_id]);
        $project_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);

        if ($project_details) {
            $total_investment_stmt = $pdo->prepare("SELECT i.user_id, p.amount AS amount_usd FROM payments p JOIN investments i ON p.investment_id = i.id WHERE i.project_id = ? AND p.status = 'successful'");
            $total_investment_stmt->execute([$active_project_id]);
            $all_project_payments = $total_investment_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $project_details['metrics'] = [
                'total_raised' => array_sum(array_column($all_project_payments, 'amount_usd')),
                'unique_investors' => count(array_unique(array_column($all_project_payments, 'user_id')))
            ];
            
            $sales_stmt = $pdo->prepare("
                SELECT *, 
                       sale_url as public_token,
                       (duration_seconds / 86400) as duration, 
                       soft_cap_usd as min_raise,
                       hard_cap_usd as max_raise,
                       sale_launch_at,
                       sale_end_at,
                       canceled_at as cancellation_date,
                       DATEDIFF(sale_end_at, CURDATE()) as days_remaining,
                       hosting,
                       sale_terms_json,
                       hard_cap_usd as hard_cap,
                       soft_cap_usd as soft_cap,
                       contract_address
                FROM token_sale_pages 
                WHERE project_id = ? 
                ORDER BY created_at DESC
            ");
            $sales_stmt->execute([$active_project_id]);
            $sales = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sales as &$sale) {
                $is_tookle_hosted = strtolower($sale['hosting'] ?? 'tookle') === 'tookle';
                $is_draft = strtolower($sale['status']) === 'draft';
                $sale['contract_required'] = ($is_tookle_hosted && $is_draft && empty($sale['contract_address']));

                $investment_per_sale_stmt = $pdo->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN investments i ON p.investment_id = i.id WHERE i.project_id = ? AND i.sale_name COLLATE utf8mb4_unicode_ci = ? AND p.status = 'successful'");
                $investment_per_sale_stmt->execute([$active_project_id, $sale['sale_name']]);
                $sale['current_funding'] = (float)$investment_per_sale_stmt->fetchColumn();

                $investor_count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT i.user_id) FROM investments i JOIN payments p ON i.id = p.investment_id WHERE i.project_id = ? AND i.sale_name COLLATE utf8mb4_unicode_ci = ? AND p.status = 'successful'");
                $investor_count_stmt->execute([$active_project_id, $sale['sale_name']]);
                $sale['investor_count'] = (int)$investor_count_stmt->fetchColumn();

                $sale['sale_logo'] = null;
                if (!empty($sale['general_images_json'])) {
                    $images = json_decode($sale['general_images_json'], true);
                    if (is_array($images) && !empty($images) && isset($images[0])) {
                        $sale['sale_logo'] = '/uploads/' . ltrim($images[0], '/');
                    }
                }

                $sale['round'] = null;
                $sale['sale_terms'] = null;
                if (!empty($sale['sale_terms_json'])) {
                    $terms_data = json_decode($sale['sale_terms_json'], true);
                    if (is_array($terms_data)) {
                        $sale['round'] = $terms_data['round_name'] ?? null;
                        if (isset($terms_data['vesting_schedule_text'])) {
                            $sale['sale_terms'] = $terms_data['vesting_schedule_text'];
                        } else {
                            $tge = $terms_data['percent_unlock_at_tge'] ?? 0;
                            $cliff = $terms_data['cliff_months'] ?? 0;
                            $vesting = $terms_data['vesting_months'] ?? 0;
                            $terms_parts = [];
                            if ($tge > 0) $terms_parts[] = "TGE: {$tge}%";
                            if ($cliff > 0) $terms_parts[] = "Cliff: {$cliff}m";
                            if ($vesting > 0) $terms_parts[] = "Vesting: {$vesting}m";
                            $sale['sale_terms'] = implode(', ', $terms_parts);
                        }
                    }
                }

                if (strtolower($sale['hosting'] ?? 'tookle') === 'tookle') {
                    $now = new DateTime();
                    $sale_end_at = !empty($sale['sale_end_at']) ? new DateTime($sale['sale_end_at']) : null;
                    $sale_launch_at = !empty($sale['sale_launch_at']) ? new DateTime($sale['sale_launch_at']) : null;

                    // PATCH: Do not auto-update via PHP if a Contract exists (Let Oracle handle it)
                    // This prevents PHP server time from overriding Blockchain time
                    $has_contract = !empty($sale['contract_address']);

                    if ($sale['status'] === 'live' && $sale_end_at && $now > $sale_end_at) {
                        if (!$has_contract) {
                            $soft_cap = (float)$sale['soft_cap_usd'];
                            $new_status = ($sale['current_funding'] >= $soft_cap) ? 'ended_successful' : 'ended_failed';
                            $pdo->prepare("UPDATE token_sale_pages SET status = ? WHERE id = ?")->execute([$new_status, $sale['id']]);
                            $sale['status'] = $new_status;

                            if ($new_status === 'ended_failed') {
                                $pdo->prepare("
                                    UPDATE investments i 
                                    JOIN payments p ON i.id = p.investment_id 
                                    SET i.status = 'refund_pending' 
                                    WHERE i.project_id = ? 
                                    AND i.sale_name COLLATE utf8mb4_unicode_ci = ? 
                                    AND i.status = 'in_escrow' 
                                    AND p.status = 'successful'
                                ")->execute([$active_project_id, $sale['sale_name']]);
                            }
                        }
                    }
                    if ($sale['status'] === 'scheduled' && $sale_launch_at && $now >= $sale_launch_at) {
                         // Scheduled sales becoming live is fine to handle in PHP
                         if ($sale_end_at && $now > $sale_end_at) {
                            if (!$has_contract) {
                                $soft_cap = (float)$sale['soft_cap_usd'];
                                $new_status = ($soft_cap > 0) ? 'ended_failed' : 'ended_successful';
                                $pdo->prepare("UPDATE token_sale_pages SET status = ? WHERE id = ?")->execute([$new_status, $sale['id']]);
                                $sale['status'] = $new_status;

                                if ($new_status === 'ended_failed') {
                                    $pdo->prepare("
                                        UPDATE investments i 
                                        JOIN investments p ON i.id = p.investment_id 
                                        SET i.status = 'refund_pending' 
                                        WHERE i.project_id = ? 
                                        AND i.sale_name COLLATE utf8mb4_unicode_ci = ? 
                                        AND i.status = 'in_escrow'
                                    ")->execute([$active_project_id, $sale['sale_name']]);
                                }
                            }
                        } else {
                            $pdo->prepare("UPDATE token_sale_pages SET status = 'live' WHERE id = ?")->execute([$sale['id']]);
                            $sale['status'] = 'live';
                        }
                    }
                }
            }
            unset($sale);
            $project_details['sales'] = $sales;
            $response['active_project_details'] = $project_details;
        }
    }
} catch (Exception $e) {
    error_log("Dashboard Backend Error: " . $e->getMessage());
    $response['error'] = 'A server error occurred: ' . $e->getMessage();
}
?>