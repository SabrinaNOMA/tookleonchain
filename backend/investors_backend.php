<?php
/**
 * investors_backend.php - MERGED VERSION
 * Combines:
 * 1. Robust Real-time KYC Status fetching (SQL Subquery)
 * 2. Robust Founder Status Logic with NULL handling (V0.3 Fix)
 * 3. Sale Status Awareness for accurate 'Refunding' display (V0.4 Fix)
 * 4. Manual Entry Status Fix (V0.5): Ensures manual entries skip 'in_escrow'
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$project_id = $_GET['pid'] ?? $_POST['project_id'] ?? $_SESSION['active_project_id'] ?? null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No active project selected.']);
    exit;
}

/**
 * Derives the Founder-facing status based on Investor Status V.02 PDF Matrix
 * UPDATED: Includes V0.4 Fix for Sale Status Awareness
 */
function deriveFounderStatus($investment_status, $payment_status, $distribution_status, $refund_tx_hash = null, $sale_status = null) {
    
    // --- FIX: Robust NULL Handling ---
    $pay_stat = empty($payment_status) ? 'pending' : strtolower(trim($payment_status));
    $dist_stat = empty($distribution_status) ? null : $distribution_status;
    $s_stat = empty($sale_status) ? '' : strtolower($sale_status);

    // 1. Awaiting Payment
    if ($investment_status === 'initiated' && $pay_stat === 'pending' && $dist_stat === null) {
        return 'Awaiting Payment';
    }
    
    // 2. Payment Failed
    if ($investment_status === 'canceled' && $pay_stat === 'failed' && $dist_stat === null) {
        return 'Payment Failed';
    }
    
    // 7. Refunding (PRIORITY CHECK)
    // If the sale itself failed/canceled, anyone in_escrow is effectively 'Refunding'
    if (($investment_status === 'refund_pending' || $investment_status === 'in_escrow') && 
        $pay_stat === 'successful' && 
        in_array($s_stat, ['ended_failed', 'canceled'])) {
        return 'Refunding';
    }
    // Explicit status check fallback
    if ($investment_status === 'refund_pending' && $pay_stat === 'successful') {
        return 'Refunding';
    }
    
    // 8. Refunded
    // Checks for explicit returned status and presence of hash
    if ($investment_status === 'returned_to_backer' && ($pay_stat === 'successful' || $pay_stat === 'refunded')) {
        return 'Refunded';
    }
    
    // 3. Payment Secured (In Progress)
    // Only if NOT refunding/refunded
    if ($investment_status === 'in_escrow' && $pay_stat === 'successful' && $dist_stat === null) {
        return 'Payment Secured';
    }
    
    // 4. Ready for Distribution (Action Required)
    if ($investment_status === 'released_to_creator' && $pay_stat === 'successful' && $dist_stat === null) {
        return 'Ready for Distribution';
    }
    
    // 5. Vesting Active
    if ($investment_status === 'released_to_creator' && $pay_stat === 'successful' && $dist_stat === 'Active') {
        return 'Vesting Active';
    }
    
    // 6. Vesting Canceled
    if ($investment_status === 'released_to_creator' && $pay_stat === 'successful' && $dist_stat === 'Revoked') {
        return 'Vesting Canceled';
    }
    
    // 9. Canceled (Final state)
    if ($investment_status === 'canceled') {
        return 'Canceled';
    }

    return 'Under Review';
}

try {
    if ($method === 'GET') {
        handle_get_request($pdo, $project_id);
    } elseif ($method === 'POST') {
        handle_post_request($pdo, $project_id);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => "Method not allowed."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Investors Backend Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

function handle_get_request($pdo, $project_id) {
    $response_data = [ 'project_name' => '', 'rounds' => [], 'allocations' => [], 'sale_pages' => [] ];
    
    // Project Info
    $stmt_project = $pdo->prepare("SELECT project_name FROM projet WHERE id = :project_id LIMIT 1");
    $stmt_project->execute([':project_id' => $project_id]);
    $project_result = $stmt_project->fetch(PDO::FETCH_ASSOC);
    $response_data['project_name'] = $project_result['project_name'] ?? 'Unnamed Project';

    // Sales for filtering
    $stmt_sales = $pdo->prepare("SELECT sale_name FROM token_sale_pages WHERE project_id = :project_id ORDER BY sale_name");
    $stmt_sales->execute([':project_id' => $project_id]);
    $response_data['sale_pages'] = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);

    // Main Query - USING ROBUST KYC SUBQUERY
    // UPDATED: Added tsp.status AS sale_status
    $sql_investors = "
        SELECT
            i.id AS investment_id, i.user_id, i.amount_usd AS amount, i.investment_round AS roundName, i.sale_name AS saleName,
            i.token_quantity, i.reference_id AS investmentReference, i.status AS investment_status, 
            
            -- ROBUST KYC FETCH: Get value from user table and kyc_applicants
            COALESCE(
                NULLIF(u.kyc_status, ''),
                (SELECT review_answer 
                 FROM kyc_applicants k 
                 WHERE k.external_user_id IN (CONCAT('user_', i.user_id), CONCAT('sess_', i.user_id), 'guest_user') 
                 ORDER BY k.created_at DESC 
                 LIMIT 1), 
                'Not Started'
            ) as kyc_status, 
            
            i.distribution_status, i.distribution_tx_hash AS distribution_hash, i.notes AS comment, 
            i.source, i.investor_wallet_address,
            i.agreement_approved, i.agreement_approved_at, i.signed_agreement_snapshot,
            u.first_name, u.last_name, u.email AS contact, 
            p.status AS payment_status,
            p.transaction_hash AS payment_hash,
            i.refund_tx_hash,
            tsp.sale_terms_json,
            tsp.status AS sale_status
        FROM investments i
        JOIN user u ON i.user_id = u.id
        LEFT JOIN payments p ON i.id = p.investment_id
        LEFT JOIN token_sale_pages tsp ON i.project_id = tsp.project_id AND i.sale_name = tsp.sale_name
        WHERE i.project_id = :project_id_investors
        ORDER BY i.created_at DESC
    ";
    
    $stmt_investors = $pdo->prepare($sql_investors);
    $stmt_investors->execute([':project_id_investors' => $project_id]);
    $results_investors = $stmt_investors->fetchAll(PDO::FETCH_ASSOC);

    if ($results_investors) {
        $response_data['allocations'] = array_map(function($row) {
            $row['derived_status'] = deriveFounderStatus(
                $row['investment_status'], 
                $row['payment_status'], 
                $row['distribution_status'],
                $row['refund_tx_hash'] ?? null,
                $row['sale_status'] ?? null
            );
            return $row;
        }, $results_investors);
    }
    
    echo json_encode(['success' => true, 'data' => $response_data]);
}

function handle_post_request($pdo, $project_id) {
    $json_payload_raw = file_get_contents('php://input');
    $data = json_decode($json_payload_raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        return;
    }

    if (isset($data['investor'])) {
        handle_update_investor($pdo, $project_id, $data['investor']);
    } elseif (isset($data['newInvestor'])) {
        handle_add_investor($pdo, $project_id, $data['newInvestor']);
    } elseif (isset($data['action']) && $data['action'] === 'batch_import') {
        handle_batch_import($pdo, $project_id, $data['investors']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid payload structure.']);
    }
}

function handle_update_investor($pdo, $project_id, $investor) {
    if (empty($investor) || !isset($investor['investment_id']) || !isset($investor['user_id'])) {
         http_response_code(400);
         echo json_encode(['success' => false, 'message' => 'Investor or User ID is missing for update.']);
         return;
    }

    try {
        $pdo->beginTransaction();

        $stmt_update_user = $pdo->prepare(
            "UPDATE user SET first_name = :firstName, last_name = :lastName WHERE id = :user_id"
        );
        $stmt_update_user->execute([
            ':firstName' => $investor['firstName'],
            ':lastName' => $investor['lastName'],
            ':user_id' => $investor['user_id']
        ]);

        $stmt_update_investment = $pdo->prepare(
            "UPDATE investments SET distribution_status = :distribution_status 
             WHERE id = :investment_id AND project_id = :project_id"
        );
        $stmt_update_investment->execute([
            ':distribution_status' => $investor['distribution_status'] === 'N/A' ? null : $investor['distribution_status'],
            ':investment_id' => $investor['investment_id'],
            ':project_id' => $project_id
        ]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Investor updated successfully.']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        error_log("Investor Update Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error during update.', 'error' => $e->getMessage()]);
    }
}

function create_investor_record($pdo, $project_id, $newInvestor) {
    $stmt_find_user = $pdo->prepare("SELECT id FROM user WHERE email = :email LIMIT 1");
    $stmt_find_user->execute([':email' => $newInvestor['contact']]);
    $user = $stmt_find_user->fetch(PDO::FETCH_ASSOC);
    $user_id = null;
    
    $kyc_status_value = (empty($newInvestor['kyc_status']) || $newInvestor['kyc_status'] === 'N/A') ? null : $newInvestor['kyc_status'];

    if ($user) {
        $user_id = $user['id'];
        $stmt_update_user = $pdo->prepare(
            "UPDATE user SET first_name = :firstName, last_name = :lastName WHERE id = :user_id"
        );
        $stmt_update_user->execute([
            ':firstName' => $newInvestor['firstName'] ?? '',
            ':lastName' => $newInvestor['lastName'],
            ':user_id' => $user_id
        ]);
    } else {
        $stmt_create_user = $pdo->prepare(
            "INSERT INTO user (first_name, last_name, email, password, kyc_status) 
             VALUES (:firstName, :lastName, :email, :password, :kyc_status)"
        );
        $stmt_create_user->execute([
            ':firstName' => $newInvestor['firstName'] ?? '',
            ':lastName' => $newInvestor['lastName'],
            ':email' => $newInvestor['contact'],
            ':password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 
            ':kyc_status' => $kyc_status_value
        ]);
        $user_id = $pdo->lastInsertId();
    }

    $stmt_sale_info = $pdo->prepare(
        "SELECT sale_terms_json FROM token_sale_pages 
         WHERE project_id = :project_id AND sale_name = :sale_name LIMIT 1"
    );
    $stmt_sale_info->execute([
        ':project_id' => $project_id,
        ':sale_name' => $newInvestor['sale_name']
    ]);
    $sale_info = $stmt_sale_info->fetch(PDO::FETCH_ASSOC);

    if (!$sale_info || empty($sale_info['sale_terms_json'])) {
        throw new Exception("Sale not found or sale terms are missing for sale: " . $newInvestor['sale_name']);
    }
    
    $sale_terms = json_decode($sale_info['sale_terms_json'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($sale_terms['round_price']) || $sale_terms['round_price'] === '' || $sale_terms['round_price'] == 0) {
        throw new Exception("Token price is missing or invalid in sale terms for sale: " . $newInvestor['sale_name']);
    }

    $token_price = (float)$sale_terms['round_price'];
    $investment_round = $sale_terms['round_name'] ?? 'Manual Round';
    $amount_usd = (float)$newInvestor['amount_usd'];
    $token_quantity = $amount_usd / $token_price;
    
    $cliff_months = $sale_terms['cliff_months'] ?? null;
    $vesting_months = $sale_terms['vesting_months'] ?? null;
    $percent_unlock_at_tge = $sale_terms['percent_unlock_at_tge'] ?? null;

    // --- FIX V0.5: Force 'released_to_creator' for successful manual payments ---
    // We treat ANY successful payment added via this manual endpoint as 'released_to_creator'
    // bypassing 'in_escrow' because the founder presumably already has the funds.
    $pay_status_cleaned = strtolower(trim($newInvestor['payment_status'] ?? ''));
    
    $investment_status = 'initiated'; 
    if ($pay_status_cleaned === 'successful') {
        $investment_status = 'released_to_creator'; 
    } elseif ($pay_status_cleaned === 'failed') {
        $investment_status = 'canceled';
    }

    $stmt_create_investment = $pdo->prepare(
        "INSERT INTO investments (user_id, project_id, amount_usd, sale_name, investment_round, 
         investor_wallet_address, source, token_quantity, status, reference_id,
         token_price_at_purchase, cliff_months, vesting_months, percent_unlock_at_tge) 
         VALUES (:user_id, :project_id, :amount_usd, :sale_name, :investment_round, 
         :wallet_address, :source, :token_quantity, :status, :reference_id,
         :token_price_at_purchase, :cliff_months, :vesting_months, :percent_unlock_at_tge)"
    );
    
    $stmt_create_investment->execute([
        ':user_id' => $user_id,
        ':project_id' => $project_id,
        ':amount_usd' => $amount_usd,
        ':sale_name' => $newInvestor['sale_name'],
        ':investment_round' => $investment_round,
        ':wallet_address' => empty($newInvestor['wallet_address']) ? null : $newInvestor['wallet_address'],
        ':source' => $newInvestor['source'] ?? 'added by project team',
        ':token_quantity' => $token_quantity,
        ':status' => $investment_status,
        ':reference_id' => 'manual_' . bin2hex(random_bytes(8)),
        ':token_price_at_purchase' => $token_price,
        ':cliff_months' => $cliff_months,
        ':vesting_months' => $vesting_months,
        ':percent_unlock_at_tge' => $percent_unlock_at_tge
    ]);
    $investment_id = $pdo->lastInsertId();

    $stmt_create_payment = $pdo->prepare(
        "INSERT INTO payments (investment_id, amount, status, method) 
         VALUES (:investment_id, :amount_usd, :status, :method)"
    );
    $stmt_create_payment->execute([
        ':investment_id' => $investment_id,
        ':amount_usd' => $amount_usd,
        ':status' => $newInvestor['payment_status'], // Save original casing for UI consistency if preferred
        ':method' => $newInvestor['payment_method']
    ]);

    return $investment_id;
}

function handle_add_investor($pdo, $project_id, $newInvestor) {
    if (empty($newInvestor['contact']) || empty($newInvestor['lastName']) || empty($newInvestor['amount_usd']) || empty($newInvestor['sale_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        return;
    }

    try {
        $pdo->beginTransaction();
        $investment_id = create_investor_record($pdo, $project_id, $newInvestor);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'New investor added successfully.', 'investment_id' => $investment_id]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        error_log("Add Investor Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error while adding investor.', 'error' => $e->getMessage()]);
    }
}

function handle_batch_import($pdo, $project_id, $investors) {
    if (empty($investors) || !is_array($investors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No investors data provided.']);
        return;
    }

    $imported_count = 0;
    $total_count = count($investors);
    $index = 0; 
    $investor = null; 

    try {
        $pdo->beginTransaction();
        foreach ($investors as $index => $investor) {
            create_investor_record($pdo, $project_id, $investor);
            $imported_count++;
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Successfully imported $imported_count of $total_count investors."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        $failed_row = $index + 1; 
        $contact_info = $investor ? $investor['contact'] : 'unknown';
        error_log("Batch Import Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "Import failed on investor #$failed_row ('$contact_info') with error: " . $e->getMessage()]);
    }
}
?>