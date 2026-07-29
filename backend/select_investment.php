<?php
/**
 * Handles selecting a project and specific sale to make it "active" in the session.
 * It expects a project_id, a sale_name, and a page to redirect to.
 */

require_once '../src/session.php';
start_secure_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login?error=unauthorized');
    exit();
}

require_once '../src/db.php';

$project_id = $_GET['project_id'] ?? null;
$sale_name = isset($_GET['sale_name']) ? urldecode($_GET['sale_name']) : null;
// FIX: Make the redirect target flexible. Default to portfolio.
$redirect_to = $_GET['redirect_to'] ?? 'portfolio'; 

if (!$project_id || !$sale_name) {
    header('Location: /portfolio?error=missing_details');
    exit();
}

try {
    // Verify the user actually has an investment in this specific sale to prevent unauthorized access
    $stmt = $pdo->prepare(
        "SELECT id FROM investments WHERE user_id = :user_id AND project_id = :project_id AND sale_name = :sale_name"
    );
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':project_id' => $project_id,
        ':sale_name' => $sale_name
    ]);

    if (!$stmt->fetch()) {
        header('Location: /portfolio?error=access_denied');
        exit();
    }
    
    // Set the authoritative session variables for the selected context
    $_SESSION['selected_project_id'] = $project_id;
    $_SESSION['selected_sale_name'] = $sale_name;
    
    // Also set active_project_id for compatibility with pages like receivingwallet.php
    $_SESSION['active_project_id'] = $project_id;

    // Sanitize redirect to prevent open redirect vulnerabilities
    $allowed_redirects = ['portfolio', 'backerdashboard', 'edit-wallet', 'purchase', 'salepage'];
    if (in_array($redirect_to, $allowed_redirects)) {
        header('Location: /' . $redirect_to);
    } else {
        // Fallback to portfolio if an invalid redirect is specified
        header('Location: /portfolio');
    }
    exit();

} catch (PDOException $e) {
    error_log('Error in select_investment.php: ' . $e->getMessage());
    header('Location: /portfolio?error=db_error');
    exit();
}
