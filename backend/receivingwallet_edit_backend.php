<?php
/**
 * backend/receivingwallet_edit_backend.php
 *
 * Intermediate script to securely set the investment ID in the session
 * before redirecting to the wallet edit page (/edit-wallet).
 * This avoids exposing the ID in the URL.
 */

// Use __DIR__ for reliable path construction relative to this file's location
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/session.php';
start_secure_session();

// 1. Authentication Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login (path relative to root)
    header('Location: /login?error=auth_required');
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. Input Validation: Get parameters from the URL query string
$investment_id = filter_input(INPUT_GET, 'investment_id', FILTER_VALIDATE_INT);
$redirect_to = filter_input(INPUT_GET, 'redirect_to', FILTER_SANITIZE_URL);

// Define allowed redirection targets to prevent open redirect vulnerabilities
$allowed_redirects = ['/edit-wallet']; // Add more paths here if needed in the future

// Validate inputs: investment ID must be a positive integer, redirect URL must be present and allowed
if (!$investment_id || $investment_id <= 0 || !$redirect_to || !in_array($redirect_to, $allowed_redirects)) {
    // If inputs are invalid or disallowed, redirect to a safe default page
    header('Location: /investor/portfolio?error=invalid_context');
    exit;
}

// 3. Authorization Check (CRITICAL): Verify ownership
// Ensure the investment ID provided belongs to the currently logged-in user.
try {
    // Check if the database connection object ($pdo) is available
    if (!isset($pdo) || !($pdo instanceof PDO)) {
         throw new Exception("Database connection not available.");
    }

    // Prepare and execute the query to check ownership
    $stmt_check = $pdo->prepare("SELECT id FROM investments WHERE id = :investment_id AND user_id = :user_id");
    $stmt_check->execute([
        ':investment_id' => $investment_id,
        ':user_id'       => $user_id
    ]);

    // Check if a matching record was found
    if ($stmt_check->fetch()) {
        // Authorization successful: User owns this investment.
        // Store the validated investment ID in the session.
        $_SESSION['selected_investment_id_for_edit'] = $investment_id;

        // 4. Redirect: Send the user to the target page (e.g., /edit-wallet)
        header('Location: ' . $redirect_to);
        exit;
    } else {
        // Authorization failed: No matching record found for this user and investment ID.
        // Redirect to portfolio page with an access denied error.
        header('Location: /investor/portfolio?error=access_denied');
        exit;
    }

} catch (Exception $e) {
    // Log any errors that occur during the process
    error_log("Error in " . basename(__FILE__) . " for user {$user_id}: " . $e->getMessage());
    // Redirect to a safe error page or the portfolio page
    header('Location: /investor/portfolio?error=server_error');
    exit;
}

?>
