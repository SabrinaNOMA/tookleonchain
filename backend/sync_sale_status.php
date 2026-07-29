<?php
/**
 * backend/sync_sale_status.php
 * Updates the sale status based on on-chain data verification from claim_funds.php
 */

// Basic security checks
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// Allow requests from the frontend
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Load DB
if (file_exists('../src/db.php')) require_once '../src/db.php';
else require_once __DIR__ . '/../src/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $sale_id = $input['sale_id'] ?? null;
    $new_status = $input['status'] ?? null;

    if (!$sale_id || !$new_status) {
        throw new Exception('Missing sale_id or status');
    }

    // Allowed status transitions (Security: Prevent arbitrary status changes)
    $allowed_statuses = ['ended_successful', 'ended_failed', 'live'];
    if (!in_array($new_status, $allowed_statuses)) {
        throw new Exception('Invalid status value');
    }

    // Verify ownership (Security: Only the project owner can sync their own sale)
    $stmt = $pdo->prepare("
        SELECT tsp.id 
        FROM token_sale_pages tsp 
        JOIN projet p ON tsp.project_id = p.id 
        WHERE tsp.id = ? AND p.founder_id = ?
    ");
    $stmt->execute([$sale_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Sale not found or access denied');
    }

    // Perform Update
    $update = $pdo->prepare("UPDATE token_sale_pages SET status = ? WHERE id = ?");
    $update->execute([$new_status, $sale_id]);

    echo json_encode(['success' => true, 'new_status' => $new_status]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>