<?php
/**
 * backend/record_refund.php
 * * Handles the recording of a successful refund transaction on-chain.
 * Updates the investment status to 'returned_to_backer' and saves the tx hash.
 */

// Include DB and Session
require_once __DIR__ . '/../src/session.php';
start_secure_session();
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// 2. Input Validation
$input = json_decode(file_get_contents('php://input'), true);
$investment_id = $input['investment_id'] ?? null;
$tx_hash = $input['tx_hash'] ?? null;

if (!$investment_id || !$tx_hash) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing investment ID or transaction hash']);
    exit;
}

try {
    // 3. Security: Verify Ownership
    // Ensure the investment belongs to the logged-in user before updating
    $stmtCheck = $pdo->prepare("SELECT id FROM investments WHERE id = :id AND user_id = :uid");
    $stmtCheck->execute([
        ':id' => $investment_id, 
        ':uid' => $_SESSION['user_id']
    ]);
    
    if (!$stmtCheck->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Investment not found or access denied']);
        exit;
    }

    // 4. Update Database
    // Set status to 'returned_to_backer' so the dashboard updates the badge to Gray/Refunded
    $stmtUpdate = $pdo->prepare("
        UPDATE investments 
        SET refund_tx_hash = :hash,
            status = 'returned_to_backer',
            completed_at = NOW()
        WHERE id = :id
    ");
    
    $stmtUpdate->execute([
        ':hash' => $tx_hash, 
        ':id' => $investment_id
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Record Refund Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>