<?php
/**
 * Backend script for the Investor Wallet Page.
 * Fetches and returns the user's wallet information AND basic user info.
 */

ob_start();
// We must include the session file and then start the session.
require_once __DIR__ . '/../src/session.php'; 
start_secure_session();
require_once __DIR__ . '/../src/db.php'; 

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated.', 401);
    }
    
    $userId = $_SESSION['user_id'];
    $response = ['wallets' => [], 'userInfo' => null];

    // 1. Fetch basic user info for the layout's sidebar
    $user_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM user WHERE id = ?");
    $user_stmt->execute([$userId]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $response['userInfo'] = $user;
    }

    // 2. Fetch wallet information
    $wallet_stmt = $pdo->prepare("SELECT label, wallet_address, network FROM user_wallet WHERE user_id = ?");
    $wallet_stmt->execute([$userId]);
    $wallets = $wallet_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($wallets) {
        $response['wallets'] = $wallets;
    }
    
    ob_end_clean();
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    ob_end_clean();
    $statusCode = $e->getCode() === 401 ? 401 : 500;
    http_response_code($statusCode);
    error_log("Wallet Fetch Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>

