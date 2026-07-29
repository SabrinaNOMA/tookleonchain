<?php
/**
 * Backend: Process Token Supply
 * Filepath: /backend/token_supply_backend.php
 *
 * Description: Handles the form submission for defining token supply.
 * It validates data, updates the database, and redirects.
 */

// Start a session only if one isn't already active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/db.php'; // Get $pdo connection

// --- Security and Initialization ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    die('Forbidden: You must be logged in.');
}

$founder_id = $_SESSION['user_id'];
$project_id = $_POST['project_id'] ?? null;
$_SESSION['form_data'] = $_POST; // Store POST data for repopulation on error

// --- Validation ---
if (empty($project_id)) {
    $_SESSION['form_errors'] = ['global' => 'Project ID is missing. Cannot save.'];
    header('Location: /dashboard'); // Redirect to a safe page
    exit;
}

$errors = [];
$supply_type = $_POST['supply_type'] ?? null;
$supply_value_input = str_replace(',', '', $_POST['supply_value'] ?? '');
$supply_unit = $_POST['supply_unit'] ?? '1';
$supply_value = '0';

// Validate supply type
if (!in_array($supply_type, ['capped', 'dynamic'])) {
    $errors['supply_type'] = "You must select a supply type.";
}

// Calculate and validate supply value
if (!is_numeric($supply_value_input) || !is_numeric($supply_unit)) {
    $errors['supply_value'] = "Invalid number format for supply value or unit.";
} else {
    // Use bcmath for precision with large numbers
    $supply_value = bcmul((string)$supply_value_input, (string)$supply_unit);
    if (bccomp($supply_value, '10000') < 0) {
        $label = ($supply_type === 'capped') ? "Max Supply" : "Initial Supply";
        $errors['supply_value'] = "$label must be at least 10,000.";
    }
}

// --- Redirect on Error ---
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    header('Location: /tokensupply');
    exit;
}

// --- Database Update ---

// WORKAROUND: Convert 'dynamic' from UI to 'inflationary' for the DB ENUM field.
$db_supply_type = ($supply_type === 'dynamic') ? 'inflationary' : $supply_type;

$sql = "UPDATE projet SET type_supply = :type_supply, supply_value = :supply_value, updated_at = NOW() WHERE id = :project_id AND founder_id = :founder_id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type_supply' => $db_supply_type,
        ':supply_value' => $supply_value,
        ':project_id' => $project_id,
        ':founder_id' => $founder_id
    ]);

    // Success: clear session data and redirect to the next step
    unset($_SESSION['form_data'], $_SESSION['form_errors']);
    header('Location: /fundraising', true, 303); // Use 303 redirect
    exit;

} catch (PDOException $e) {
    error_log("PDO Error in token_supply_backend.php: " . $e->getMessage());
    $_SESSION['form_errors'] = ['global' => 'A database error occurred. Please try again.'];
    header('Location: /tokensupply');
    exit;
}