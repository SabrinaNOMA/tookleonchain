<?php
/**
 * project_router.php - Project Status Router (Refactored)
 * This script checks the project's setup completion flags
 * to redirect the founder to the appropriate setup page.
 * If all setup steps are complete, it forwards to the management page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load necessary application files
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

// 1. Check for user authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

// 2. Get Project ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: /dashboard?error=no_project_id");
    exit;
}
$project_id = $_GET['id'];
$founder_id = $_SESSION['user_id'];

// Set the current project in the session for other pages to use.
$_SESSION['active_project_id'] = $project_id;

try {
    // 3. Fetch project setup flags from the database.
    // We verify the project belongs to the logged-in founder for security.
    $stmt = $pdo->prepare(
        "SELECT p.project_described, p.tokenomics_done, p.token_sale_page_ready
         FROM projet p
         WHERE p.id = ? AND p.founder_id = ?"
    );
    $stmt->execute([$project_id, $founder_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no project is found, it doesn't exist or doesn't belong to the user.
    if (!$project) {
        header("Location: /dashboard?error=project_not_found");
        exit;
    }

    // 4. Redirection Logic based on project setup steps
    // This script now ONLY handles the setup flow.

    // Step 1: If the project description is not complete, send to the setup page.
    if (!$project['project_described']) {
        header("Location: /setup");
        exit;
    }

    // Step 2: If description is done, but tokenomics are not, send to the tokenomics page.
    if (!$project['tokenomics_done']) {
        header("Location: /tokenname");
        exit;
    }
    
    // Step 3: If tokenomics are done, but the sale page isn't ready, send to that setup page.
    if (!$project['token_sale_page_ready']) {
        header("Location: /story");
        exit;
    }

    // FINAL STEP: If all the above checks have passed, it means setup is complete.
    // Redirect to the main project management page.
    header("Location: /investors");
    exit;

} catch (PDOException $e) {
    // Log the error and redirect to a generic error page.
    error_log("Project Router Error: " . $e->getMessage());
    header("Location: /dashboard?error=database_error");
    exit;
}
