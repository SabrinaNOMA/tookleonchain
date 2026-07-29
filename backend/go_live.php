<?php
/**
 * backend/go_live.php
 * Activates the Smart Vault / Campaign.
 * Updates status to 'live', sets launch date to NOW(), and calculates end date based on duration_seconds.
 * FIX: Correctly joins project table to verify founder ownership.
 */

header('Content-Type: application/json');

require_once '../src/session.php'; 
require_once '../src/db.php'; 

if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('start_secure_session')) {
        start_secure_session();
    } else {
        session_start();
    }
}

try {
    // 1. Auth Check
    if (!isset($_SESSION['user_id'])) { 
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Authentication required.']); 
        exit;
    }
    $founder_id = $_SESSION['user_id'];

    // 2. Input
    $input = json_decode(file_get_contents('php://input'), true);
    $sale_id = $input['sale_id'] ?? null;

    if (empty($sale_id)) {
        throw new Exception("Sale ID is required.");
    }

    // 3. Verify Sale, Ownership, and Get Duration
    // We join with `projet` to verify the founder_id matches the session user.
    $stmt = $pdo->prepare("
        SELECT tsp.id, tsp.status, tsp.duration_seconds, tsp.contract_address, p.founder_id
        FROM token_sale_pages tsp 
        JOIN projet p ON tsp.project_id = p.id
        WHERE tsp.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Sale not found.");
    }

    if ($sale['founder_id'] != $founder_id) {
        http_response_code(403);
        throw new Exception("Permission denied.");
    }

    // Safety check: Ensure Vault is deployed
    if (empty($sale['contract_address'])) {
        throw new Exception("Smart Vault not configured. Please deploy the vault first.");
    }

    // 4. Update Status & Dates
    $durationSeconds = (int)($sale['duration_seconds'] ?? 604800); // Default 7 days
    
    $now = new DateTime();
    $launchAt = $now->format('Y-m-d H:i:s');
    
    $end = clone $now;
    // modify requires a string like "+123 seconds"
    $end->modify("+{$durationSeconds} seconds");
    $endAt = $end->format('Y-m-d H:i:s');

    $updateStmt = $pdo->prepare("
        UPDATE token_sale_pages 
        SET 
            status = 'live',
            sale_launch_at = :launch_at,
            sale_end_at = :end_at
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':launch_at' => $launchAt,
        ':end_at'    => $endAt,
        ':id'        => $sale_id
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'Smart Vault activated successfully.',
        'sale_id' => $sale_id
    ]);

} catch (Exception $e) {
    error_log("Go Live Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>