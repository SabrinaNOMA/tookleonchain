<?php
// Fichier: backend/save_coinbase_backend.php
session_start();
header('Content-Type: application/json');

// Disable outputting errors to the response (logs them to server log instead)
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// SECURITY: Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh and log in again.']);
    exit;
}

// 1. ROBUST DB CONNECTION
// We try multiple paths to ensure we find the DB connection file regardless of inclusion context
$paths = [
    __DIR__ . '/../src/db.php',
    __DIR__ . '/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/src/db.php',
    'src/db.php'
];

$pdo = null;
foreach ($paths as $path) {
    if (file_exists($path)) {
        $pdo = require $path;
        break;
    }
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed (db.php not found).']);
    exit;
}

// 2. INPUT SANITIZATION
// Get Address from POST (FormData) or JSON Body
$addr = '';
if (!empty($_POST['address'])) {
    $addr = $_POST['address'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $addr = $input['address'] ?? $input['walletAddress'] ?? '';
}

// 3. VALIDATION
if (strlen($addr) < 40 || strpos($addr, '0x') !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid wallet address format.']);
    exit;
}

// 4. EXECUTION
try {
    // CRITICAL FIX: Using 'coinbase_wallet_adress' (one 'd') to match the existing database schema.
    // Do not change this to 'address' unless you have renamed the column in MySQL.
    $stmt = $pdo->prepare("UPDATE user SET coinbase_wallet_adress = :addr WHERE id = :uid");
    
    $result = $stmt->execute([':addr' => $addr, ':uid' => $_SESSION['user_id']]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Update query reported failure.");
    }

} catch (Exception $e) {
    // Log the actual error for the admin
    error_log("Wallet Save Error: " . $e->getMessage());
    
    // Return a generic error to the user, but specific enough to know it's the backend
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database Error: Could not save wallet. Check error logs.'
    ]);
}
?>