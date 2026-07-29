<?php
/**
 * Backend script to set the active project in the session.
 * This script now includes session_write_close() to prevent race conditions.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$project_id = $input['project_id'] ?? null;

if (empty($project_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Project ID is required.']);
    exit;
}

// --- Security Check & Database Operation ---
try {
    $pdo = require_once __DIR__ . '/../src/db.php';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projet WHERE id = ? AND founder_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $_SESSION['active_project_id'] = $project_id;
        // CRITICAL FIX: Immediately write and close the session to prevent race conditions
        // where the page reloads before the session file is updated on the server.
        session_write_close(); 
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'error' => 'Project not found or you do not have permission.']);
    }
} catch (PDOException $e) {
    error_log("Set Active Project Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'A database error occurred.']);
}

