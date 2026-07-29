<?php
/**
 * Backend script for the Investor Wallet Page.
 * Handles saving/synchronizing the user's wallet list.
 */

ob_start();
// We must include the session file and then start the session.
require_once __DIR__ . '/../src/session.php';
start_secure_session();
require_once __DIR__ . '/../src/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['error' => 'User not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = [];

try {
    // 1. Prepare an array of valid wallets from the POST data.
    $wallets_to_save = [];
    if (isset($_POST['walletAddress']) && is_array($_POST['walletAddress'])) {
        foreach ($_POST['walletAddress'] as $index => $address) {
            $trimmed_address = trim($address);
            if (!empty($trimmed_address)) {
                $wallets_to_save[] = [
                    'label' => trim($_POST['walletName'][$index] ?? 'My Wallet'),
                    'address' => $trimmed_address,
                    'network' => trim($_POST['walletNetwork'][$index] ?? 'other'),
                ];
            }
        }
    }

    // 2. Begin transaction.
    $pdo->beginTransaction();

    // 3. Delete all existing wallets for the user.
    $delete_stmt = $pdo->prepare("DELETE FROM user_wallet WHERE user_id = ?");
    $delete_stmt->execute([$user_id]);

    // 4. Insert the newly submitted wallets.
    if (!empty($wallets_to_save)) {
        // NOTE: `is_primary` column logic removed as it's not in the schema.
        $insert_sql = "INSERT INTO user_wallet (user_id, label, wallet_address, network) VALUES (?, ?, ?, ?)";
        $insert_stmt = $pdo->prepare($insert_sql);
        
        foreach ($wallets_to_save as $wallet) {
            $insert_stmt->execute([$user_id, $wallet['label'], $wallet['address'], $wallet['network']]);
        }
    }
    
    // 5. Commit transaction.
    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Your wallets have been updated successfully!';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Wallet Save Error: " . $e->getMessage());
    http_response_code(500);
    $response['error'] = 'A database error occurred while saving your wallets.';
}

ob_clean();
echo json_encode($response);
exit;
?>

