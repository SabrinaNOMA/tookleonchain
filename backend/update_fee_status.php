<?php
/**
 * backend/update_fee_status.php
 * Records the success fee payment in the database.
 * * Features:
 * - Session Fix: Guaranteed session start logic.
 * - Idempotency: Returns success if tx_hash already exists.
 * - Error Handling: Detailed JSON errors.
 */

// 1. Force Session Cookie settings (Best practice for subdirectories)
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => '/', 
    'domain' => $cookieParams['domain'],
    'secure' => $cookieParams['secure'],
    'httponly' => $cookieParams['httponly']
]);

// 2. Load Dependencies & Session
// Attempt to include session.php but don't rely on it to start the session
$paths = ['../src/session.php', '../../src/session.php', '../../../src/session.php'];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// CRITICAL FIX: Always verify if session is active. If not, START IT.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// 3. Database Connection
if (!isset($pdo)) {
    $dbPaths = ['../src/db.php', '../../src/db.php', '../../../src/db.php'];
    foreach ($dbPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database config missing.']);
    exit;
}

try {
    // 4. Debugging/Auth Check
    /* [TEMPORARILY DISABLED FOR POSTMAN TESTING]
    if (!isset($_SESSION['user_id'])) {
        $debugInfo = 'Session Status: ' . session_status() . ' | ID: ' . session_id();
        http_response_code(401); 
        throw new Exception('User not authenticated. ' . $debugInfo);
    }
    */

    // 5. Input Parsing
    $input = json_decode(file_get_contents('php://input'), true);
    
    $sale_id = $input['sale_id'] ?? null;
    $tx_hash = $input['tx_hash'] ?? null;
    $amount = $input['amount'] ?? '0';
    $currency = $input['currency'] ?? 'TOKEN';
    $payer = $input['payer_address'] ?? null;

    if (empty($sale_id) || empty($tx_hash)) {
        http_response_code(400); 
        throw new Exception('Missing parameters: sale_id or tx_hash');
    }

    // 6. Verify Ownership
    /* [TEMPORARILY DISABLED FOR POSTMAN TESTING]
    $stmtOwner = $pdo->prepare("SELECT id FROM token_sale_pages WHERE id = ? AND project_id IN (SELECT id FROM projet WHERE founder_id = ?)");
    $stmtOwner->execute([$sale_id, $_SESSION['user_id']]);
    if (!$stmtOwner->fetch()) {
        http_response_code(403); 
        throw new Exception('Access Denied: Sale not owned by user.');
    }
    */

    // 7. Idempotency (Prevent Duplicate Entries)
    $stmtCheck = $pdo->prepare("SELECT id FROM success_fee WHERE tx_hash = ?");
    $stmtCheck->execute([$tx_hash]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Already recorded']);
        exit;
    }

    // 8. Insert Record
    $stmtInsert = $pdo->prepare("
        INSERT INTO success_fee (sale_id, amount, currency, tx_hash, status, payer_address, created_at)
        VALUES (?, ?, ?, ?, 'confirmed', ?, NOW())
    ");
    $stmtInsert->execute([$sale_id, $amount, $currency, $tx_hash, $payer]);

    // 9. Update the Token Sale Page
    $stmtUpdate = $pdo->prepare("UPDATE token_sale_pages SET fee_settled = 1 WHERE id = ?");
    $stmtUpdate->execute([$sale_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400); 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>