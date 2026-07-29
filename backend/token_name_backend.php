<?php
/**
 * Backend: Save Token Name, Ticker, and Logo
 * Filepath: /backend/token_name_backend.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Configuration ---
// Get URLs dynamically from the config file
// FIX: Corrected the path to wizard_nav.php. It's in the project root, not a 'components' folder.
require_once __DIR__ . '/../wizard_nav.php'; // Includes the config
$wizard_config = get_wizard_config();

$form_page_url = $wizard_config['tokenomics']['subSteps']['token_name']['url'];
$next_page_url = $wizard_config['tokenomics']['subSteps']['token_supply']['url'];

$upload_dir_base = '/uploads/logos/';
$upload_dir_system = $_SERVER['DOCUMENT_ROOT'] . $upload_dir_base;

// --- Security and Initialization ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

$pdo = require __DIR__ . '/../src/db.php';
$founder_id = $_SESSION['user_id'];
$projet_id = $_POST['projet_id'] ?? null;

$errors = [];
$form_data = $_POST;

// --- Validation ---
if (empty($projet_id)) {
    $errors['global'] = 'Project ID is missing.';
    $_SESSION['form_errors'] = $errors;
    header('Location: ' . $form_page_url);
    exit();
}

$token_name = trim($_POST['token_name'] ?? '');
$token_ticker = trim($_POST['token_ticker'] ?? '');
if (empty($token_name)) $errors['token_name'] = 'Token Name is required.';
if (empty($token_ticker)) $errors['token_ticker'] = 'Token Ticker is required.';

$logo_file = $_FILES['logo_upload'] ?? null;
$logo_db_path = null;
if ($logo_file && $logo_file['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($logo_file['type'], $allowed_types)) {
        $errors['logo_upload'] = 'Invalid file type. Only JPG, PNG, SVG, GIF are allowed.';
    } elseif ($logo_file['size'] > $max_size) {
        $errors['logo_upload'] = 'File is too large. Maximum size is 2MB.';
    } else {
        if (!is_dir($upload_dir_system)) { mkdir($upload_dir_system, 0777, true); }
        $file_extension = pathinfo($logo_file['name'], PATHINFO_EXTENSION);
        $safe_filename = bin2hex(random_bytes(16)) . '.' . $file_extension;
        $destination = $upload_dir_system . $safe_filename;
        if (move_uploaded_file($logo_file['tmp_name'], $destination)) {
            $logo_db_path = $upload_dir_base . $safe_filename;
        } else {
            $errors['logo_upload'] = 'Failed to save uploaded logo.';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $form_data;
    header('Location: ' . $form_page_url);
    exit();
}

// --- Database Update ---
try {
    $check_stmt = $pdo->prepare("SELECT id FROM projet WHERE id = ? AND founder_id = ?");
    $check_stmt->execute([$projet_id, $founder_id]);
    if (!$check_stmt->fetch()) {
        throw new Exception("Permission denied or project not found.");
    }

    $sql_parts = ["token_name = ?", "token_ticker = ?"];
    $params = [$token_name, $token_ticker];
    if ($logo_db_path !== null) {
        $sql_parts[] = "token_logo_path = ?";
        $params[] = $logo_db_path;
    }
    $sql = "UPDATE projet SET " . implode(', ', $sql_parts) . " WHERE id = ?";
    $params[] = $projet_id;
    $pdo->prepare($sql)->execute($params);

    // --- Handle Success ---
    unset($_SESSION['form_errors'], $_SESSION['form_data']);
    header('Location: ' . $next_page_url, true, 303);
    exit();
} catch (Exception $e) {
    error_log("Error in token_name_backend.php: " . $e->getMessage());
    $_SESSION['form_errors'] = ['global' => 'A server error occurred. Please try again.'];
    $_SESSION['form_data'] = $form_data;
    header('Location: ' . $form_page_url);
    exit();
}