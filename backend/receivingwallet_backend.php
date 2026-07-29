<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/session.php';
start_secure_session();
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');
$response = ["success" => false, "message" => "Initialization error."];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception("User not authenticated. Please log in.");
    }
    $user_id_for_operation = $_SESSION['user_id'];
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Critical server error: Database connection object not available.");
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Invalid request method.");
    }
    
    $wallet_address = trim($_POST['wallet_address'] ?? '');
    $network = trim($_POST['network'] ?? '');
    $label = trim($_POST['label'] ?? '');
    // Crucially capture investment_id passed from the dashboard or session
    $investment_id = filter_var(trim($_POST['investment_id'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
    $project_id_from_form = trim($_POST['project_id'] ?? ''); 
    $selected_user_wallet_id = filter_var(trim($_POST['user_wallet_id'] ?? ''), FILTER_VALIDATE_INT);

    $errors = [];
    if (empty($wallet_address)) $errors[] = "Wallet address is required.";
    if (empty($network)) $errors[] = "Network is required.";
    if (empty($label)) $errors[] = "Wallet label/name is required.";
    
    if (!empty($errors)) {
        throw new Exception(implode(" ", $errors));
    }
    
    $pdo->beginTransaction();

    // Always ensure the wallet exists/is updated in the user's personal list (`user_wallet`)
    $stmt_check = $pdo->prepare("SELECT id FROM user_wallet WHERE user_id = :user_id AND wallet_address = :wallet_address");
    $stmt_check->execute([':user_id' => $user_id_for_operation, ':wallet_address' => $wallet_address]);
    
    if ($existing_wallet = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
        $stmt_update_label = $pdo->prepare("UPDATE user_wallet SET label = :label, network = :network WHERE id = :id");
        $stmt_update_label->execute([':label' => $label, ':network' => $network, ':id' => $existing_wallet['id']]);
    } else {
        $stmt_insert = $pdo->prepare("INSERT INTO user_wallet (user_id, wallet_address, network, label) VALUES (:user_id, :wallet_address, :network, :label)");
        $stmt_insert->execute([':user_id' => $user_id_for_operation, ':wallet_address' => $wallet_address, ':network' => $network, ':label' => $label]);
    }
    
    // --- CRITICAL FIX: Ensure update is per-investment when ID is provided ---
    if ($investment_id) {
        // This handles BOTH the investment flow (via session/form) AND the edit-wallet dashboard link.
        // It guarantees that the update only hits the single, specific investment record.
        $stmt_update = $pdo->prepare("UPDATE investments SET investor_wallet_address = :wallet_address WHERE id = :investment_id AND user_id = :user_id_owner");
        $stmt_update->execute([':wallet_address' => $wallet_address, ':investment_id' => $investment_id, ':user_id_owner' => $user_id_for_operation]);

        // Clear the current_investment_id session variable after saving, if it exists
        if (isset($_SESSION['current_investment_id']) && $_SESSION['current_investment_id'] == $investment_id) {
             unset($_SESSION['current_investment_id']);
        }
        
    } elseif (!empty($project_id_from_form)) {
        // REMOVED: Case 2, which used to update ALL investments for a project. 
        // Based on user request, this behavior is now disabled.
        // For dashboard editing, the backerdashboard.php file now ensures an investment_id is passed
        // via the URL parameter. The receivingwallet.php file then correctly places it into 
        // the hidden form field. This fallback logic should now be unnecessary 
        // and is removed to enforce per-investment logic.
         error_log("Warning: receivingwallet_backend.php received project_id without investment_id. No mass update performed.");
         // We still commit, as the user_wallet table update was successful.
    }

    $pdo->commit();
    $response["success"] = true;
    $response["message"] = "Wallet successfully saved.";
    // For dashboard editing, redirect_url will be set to /backerdashboard
    $response["redirect_url"] = $_POST['redirect_url'] ?? '/payment';

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getMessage() === "User not authenticated. Please log in.") {
        http_response_code(401);
    } else {
        http_response_code(400);
    }
    $response["message"] = $e->getMessage();
    error_log("receivingwallet_backend.php ERROR: " . $e->getMessage());
}

echo json_encode($response);
exit;
