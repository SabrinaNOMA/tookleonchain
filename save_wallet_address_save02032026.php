<?php
// Fichier: save_wallet_address.php (Root Directory)
session_start();
header('Content-Type: application/json');

// Disable outputting errors to the response
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 1. SECURITY CHECK
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    // Note: main.js looks for 'error' key, not 'message'
    echo json_encode(['success' => false, 'error' => 'User not logged in. Please refresh.']);
    exit;
}

// 2. DB CONNECTION (Robust Path Finding for Root File)
$paths = [
    __DIR__ . '/src/db.php',       // Standard root structure
    __DIR__ . '/db.php',           // Flat structure
    __DIR__ . '/../src/db.php',    // Fallback
    $_SERVER['DOCUMENT_ROOT'] . '/src/db.php'
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// 3. INPUT PARSING (Handle JSON from main.js)
$addr = '';
// main.js sends JSON body: { walletAddress: "..." }
$input = json_decode(file_get_contents('php://input'), true);

if (!empty($input['walletAddress'])) {
    $addr = $input['walletAddress'];
} elseif (!empty($_POST['address'])) {
    // Fallback for form data
    $addr = $_POST['address'];
}

// 4. VALIDATION
if (strlen($addr) < 40 || strpos($addr, '0x') !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid wallet address format.']);
    exit;
}

// 5. EXECUTION
try {
    // CRITICAL FIX: 'coinbase_wallet_adress' (one 'd') to match DB
    $stmt = $pdo->prepare("UPDATE user SET coinbase_wallet_adress = :addr WHERE id = :uid");
    $result = $stmt->execute([':addr' => $addr, ':uid' => $_SESSION['user_id']]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Update failed.");
    }

} catch (Exception $e) {
    error_log("Wallet Save Error: " . $e->getMessage());
    http_response_code(500);
    // Return 'error' key for main.js compatibility
    echo json_encode([
        'success' => false, 
        'error' => 'Database Error: Check server logs.'
    ]);
}
?>