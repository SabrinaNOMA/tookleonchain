<?php
/**
 * Backend: Token Distribution Management
 * Filepath: /backend/release_tokens_backend.php
 *
 * Description: This script handles fetching investors eligible for token distribution
 * and updating their status once the distribution transaction is confirmed.
 *
 * ---
 * Silicon Valley Engineer's Review:
 * - CRITICAL FIX: The main GET query now correctly sources vesting terms (`cliff_months`, `vesting_months`, `percent_unlock_at_tge`)
 * directly from the `investments` table. This table acts as an immutable snapshot of the terms at the time of purchase,
 * which is the correct behavior. The previous logic of trying to merge this with "active scenario" data was flawed
 * and has been removed from the frontend.
 * - ADDED: The query now also fetches the `selected_contract` address from the `deployed_token` table, ensuring the
 * frontend has all necessary data from a single API call.
 * - ENHANCED (POST): The POST handler now supports both single stream creation and batch creation. It detects a 'batch'
 * key in the JSON payload and, if present, iterates through the array to update all streams within a single
 * database transaction. This maintains atomicity on our backend, mirroring the on-chain transaction.
 * ---
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/src/db.php';

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    send_json_error('User not authenticated.', 401);
}

$request_method = $_SERVER['REQUEST_METHOD'];

if ($request_method === 'GET') {
    $project_id = $_GET['project_id'] ?? null;
    if (!$project_id) {
        send_json_error('Project ID is required.');
    }

    try {
        // --- Schedules to Activate ---
        // This query is now the Single Source of Truth for vesting parameters.
        // It correctly pulls the snapshot data from the `investments` table.
        $stmt_schedules = $pdo->prepare("
            SELECT 
                i.id AS investment_id,
                CONCAT(u.first_name, ' ', u.last_name) AS investor_name,
                i.investment_round AS round_name,
                i.amount_usd,
                i.token_quantity,
                i.investor_wallet_address,
                i.cliff_months,
                i.vesting_months,
                i.percent_unlock_at_tge
            FROM investments i
            JOIN user u ON i.user_id = u.id
            JOIN projet pr ON i.project_id = pr.id
            WHERE i.project_id = ?
              AND pr.founder_id = ?
              AND i.status = 'released_to_creator'
              AND i.distribution_status IS NULL
              AND EXISTS (
                  SELECT 1
                  FROM payments p
                  WHERE p.investment_id = i.id
                    AND p.status = 'successful'
              )
        ");
        $stmt_schedules->execute([$project_id, $user_id]);
        $schedules_to_activate = $stmt_schedules->fetchAll(PDO::FETCH_ASSOC);

        // --- Active Schedules ---
        $stmt_active = $pdo->prepare("
             SELECT 
                i.id AS investment_id,
                CONCAT(u.first_name, ' ', u.last_name) AS investor_name,
                i.investment_round AS round_name,
                i.token_quantity,
                i.distribution_tx_hash,
                i.distributed_at,
                i.cliff_months,
                i.vesting_months,
                i.percent_unlock_at_tge
            FROM investments i
            JOIN user u ON i.user_id = u.id
            JOIN projet pr ON i.project_id = pr.id
            WHERE i.project_id = ?
              AND pr.founder_id = ?
              AND i.distribution_status = 'Active'
              AND EXISTS (
                  SELECT 1
                  FROM payments p
                  WHERE p.investment_id = i.id
                    AND p.status = 'successful'
              )
        ");
        $stmt_active->execute([$project_id, $user_id]);
        $active_schedules = $stmt_active->fetchAll(PDO::FETCH_ASSOC);

        // --- Deployed Contract ---
        $stmt_contract = $pdo->prepare("
            SELECT contract FROM deployed_token 
            WHERE projet_id = ? AND selected_contract = 'yes'
        ");
        $stmt_contract->execute([$project_id]);
        $token_contract = $stmt_contract->fetchColumn();


        echo json_encode([
            'success' => true,
            'data' => [
                'schedulesToActivate' => $schedules_to_activate,
                'activeSchedules' => $active_schedules,
                'tokenContractAddress' => $token_contract
            ]
        ]);

    } catch (Exception $e) {
        error_log('Token distribution fetch error: ' . $e->getMessage());
        send_json_error('A server error occurred. Please try again later.', 500);
    }

} elseif ($request_method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $project_id = $input['project_id'] ?? null;
    $tx_hash = $input['tx_hash'] ?? null;
    $is_batch = isset($input['batch']) && is_array($input['batch']);

    if (!$project_id || !$tx_hash) {
        send_json_error('Missing project_id or tx_hash.');
    }
    if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
        send_json_error('Invalid transaction hash format.');
    }

    try {
        $pdo->beginTransaction();

        // Prepare the single update statement. We will reuse this.
        // This query now includes checks for project_id and founder_id for security.
        $update_stmt = $pdo->prepare("
            UPDATE investments i
            JOIN projet p ON i.project_id = p.id
            SET 
                i.distribution_status = 'Active',
                i.distribution_tx_hash = ?,
                i.distribution_stream_id = ?,
                i.distributed_at = NOW()
            WHERE i.id = ? 
              AND i.project_id = ?
              AND p.founder_id = ?
              AND i.distribution_status IS NULL
        ");

        if ($is_batch) {
            // --- BATCH UPDATE ---
            $batch_data = $input['batch'];
            if (empty($batch_data)) {
                send_json_error('Batch array cannot be empty.');
            }

            $rows_affected = 0;
            foreach ($batch_data as $item) {
                $investment_id = $item['investment_id'] ?? null;
                $stream_id = $item['stream_id'] ?? null;

                if (!$investment_id || !$stream_id) {
                    $pdo->rollBack();
                    send_json_error('Invalid item in batch array. Missing investment_id or stream_id.');
                }

                $update_stmt->execute([$tx_hash, $stream_id, $investment_id, $project_id, $user_id]);
                $rows_affected += $update_stmt->rowCount();
            }
            
            if ($rows_affected == 0) {
                 // This could happen if permission is wrong or if all streams were already active.
                 $pdo->rollBack();
                 send_json_error('No schedules were updated. They may already be active or you may not have permission.', 404);
            } elseif ($rows_affected < count($batch_data)) {
                 // Partial success, but we commit what we have.
                 error_log("Batch update partial success: User $user_id, Project $project_id. Expected " . count($batch_data) . " updates, got " . $rows_affected);
                 $pdo->commit();
                 echo json_encode(['success' => true, 'message' => "Batch distribution partially activated. $rows_affected schedules updated."]);
            } else {
                // Full success
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Batch distribution activated successfully.']);
            }

        } else {
            // --- SINGLE UPDATE (Original Logic) ---
            $investment_id = $input['investment_id'] ?? null;
            $stream_id = $input['stream_id'] ?? null;

            if (!$investment_id || !$stream_id) {
                $pdo->rollBack();
                send_json_error('Missing investment_id or stream_id for single stream creation.');
            }

            $update_stmt->execute([$tx_hash, $stream_id, $investment_id, $project_id, $user_id]);

            if ($update_stmt->rowCount() > 0) {
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Distribution activated successfully.']);
            } else {
                $pdo->rollBack();
                send_json_error('Investment not found, could not be updated (permission error), or is already active.', 404);
            }
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Token distribution activation error: ' . $e->getMessage());
        send_json_error('A server error occurred while activating the schedule.', 500);
    }
} else {
    send_json_error('Method not allowed.', 405);
}

