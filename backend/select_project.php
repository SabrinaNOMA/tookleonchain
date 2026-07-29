<?php
/**
 * Handles the selection of a specific project sale.
 *
 * This script receives a project_id and a sale_name, validates them,
 * stores them in the user's session, and redirects to the correct
 * token sale page.
 */

session_start();

// Ensure the user is logged in before proceeding.
if (!isset($_SESSION['user_id'])) {
    header('Location: /login?error=unauthorized');
    exit();
}

require_once '../src/db.php';

// 1. Retrieve and Validate Input
// Check if both project_id and sale_name are provided in the URL.
if (!isset($_GET['project_id']) || !isset($_GET['sale_name'])) {
    // Redirect to the projects page with an error if parameters are missing.
    header('Location: /projects?error=missing_selection_details');
    exit();
}

$project_id = $_GET['project_id'];
$sale_name = urldecode($_GET['sale_name']); // Decode the sale_name from the URL.

try {
    // 2. Verify the selected project and sale exist in the database and is live.
    // FIX: Updated table to 'token_sale_pages' and column to 'project_id'.
    $stmt = $pdo->prepare("
        SELECT project_id 
        FROM token_sale_pages 
        WHERE project_id = :project_id AND sale_name = :sale_name AND status = 'live'
    ");
    $stmt->execute(['project_id' => $project_id, 'sale_name' => $sale_name]);
    $sale_exists = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no matching live sale is found, redirect back with an error.
    if (!$sale_exists) {
        header('Location: /projects?error=sale_not_found_or_not_live');
        exit();
    }

    // 3. Store Selection in Session
    // Save the validated project ID and sale name into the session.
    // The salepage will read these values to display the correct content.
    $_SESSION['selected_project_id'] = $project_id;
    $_SESSION['selected_sale_name'] = $sale_name;

    // 4. Redirect to the main Sale Page
    // The redirection is now hardcoded to the generic salepage.
    header('Location: /salepage');
    exit();

} catch (PDOException $e) {
    // In case of a database error, log the error and show a generic message.
    error_log('Database error in select_project.php: ' . $e->getMessage());
    header('Location: /projects?error=database_error');
    exit();
}
?>
