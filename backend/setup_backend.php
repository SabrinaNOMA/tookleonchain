<?php
/**
 * Backend: Save Project Description
 * Filepath: /backend/setup_backend.php
 *
 * Description: API endpoint to handle creating or updating a project's description.
 * NOW SAVES UTILITY DESCRIPTIONS CORRECTLY.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function gen_uuid_v4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

header('Content-Type: application/json');
ob_start(); 

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_length()) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal server error', 'debug' => $error]);
        exit;
    }
});

set_exception_handler(function ($exception) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    exit;
});

require_once __DIR__ . '/../src/session.php'; 
$pdo = require __DIR__ . '/../src/db.php';      

if (!isset($_SESSION['user_id'])) {
    throw new Exception("Authentication required.", 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception("Invalid request method.", 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("Invalid JSON payload.", 400);
}

$founder_id = $_SESSION['user_id'];
$projet_id = !empty($data['projet_id']) ? (string)$data['projet_id'] : null;

$response = ['success' => false];

try {
    $pdo->beginTransaction();

    // 1. INSERT or UPDATE the main project data
    if ($projet_id) {
        $check_stmt = $pdo->prepare("SELECT id FROM projet WHERE id = ? AND founder_id = ?");
        $check_stmt->execute([$projet_id, $founder_id]);
        if ($check_stmt->fetchColumn() === false) {
            throw new Exception("Authorization error.", 403);
        }

        $sql = "UPDATE projet SET project_name = ?, pain_point = ?, solution = ?, competitive_advantage = ?, industry_focus = ?, selected_category = ?, project_described = 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['project_name'] ?? null,
            $data['pain_point'] ?? null,
            $data['solution'] ?? null,
            $data['competitive_advantage'] ?? null,
            $data['industry_focus'] ?? null,
            $data['selected_category'] ?? null,
            $projet_id
        ]);
    } else {
        $projet_id = gen_uuid_v4();
        
        $sql = "INSERT INTO projet (id, founder_id, project_name, pain_point, solution, competitive_advantage, industry_focus, selected_category, project_website, created_at, project_described) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $projet_id, 
            $founder_id,
            $data['project_name'] ?? null,
            $data['pain_point'] ?? null,
            $data['solution'] ?? null,
            $data['competitive_advantage'] ?? null,
            $data['industry_focus'] ?? null,
            $data['selected_category'] ?? null,
            $data['project_website'] ?? '', 
        ]);
    }

    // 2. Synchronize the token utilities
    if (isset($data['utilities']) && is_array($data['utilities']) && $projet_id) {
        // A) Delete all existing utilities for this project
        $delete_stmt = $pdo->prepare("DELETE FROM utility_token WHERE projet_id = ?");
        $delete_stmt->execute([$projet_id]);

        // B) Insert the new set of utilities WITH DESCRIPTIONS
        if (!empty($data['utilities'])) {
            // --- SMART FIX: Added utility_description to the insert query ---
            $insert_sql = "INSERT INTO utility_token (projet_id, utility_name, utility_description, is_custom) VALUES (?, ?, ?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);

            foreach ($data['utilities'] as $utility) {
                if (isset($utility['name']) && trim($utility['name']) !== '') { 
                    $insert_stmt->execute([
                        $projet_id,
                        trim($utility['name']),
                        // Map the frontend 'description' to the DB column 'utility_description'
                        $utility['description'] ?? null, 
                        (int)($utility['is_custom'] ?? 0)
                    ]);
                }
            }
        }
    }

    $pdo->commit();

    $_SESSION['active_project_id'] = $projet_id;

    $response['success'] = true;
    $response['projet_id'] = $projet_id;
    $response['message'] = 'Project saved successfully.';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

ob_end_clean();
echo json_encode($response);
exit;