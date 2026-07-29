<?php
/**
 * Unified Settings Endpoint
 *
 * Handles both fetching (GET) and saving (POST) user account information.
 * This script is now more resilient to database errors.
 */

// Use a secure session start function if available
if (file_exists(__DIR__ . '/../src/session.php')) {
    require_once __DIR__ . '/../src/session.php';
    start_secure_session();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/db.php'; 
header('Content-Type: application/json');

// --- AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    ob_clean();
    echo json_encode(['error' => 'User not authenticated.']);
    exit;
}

$userId = $_SESSION['user_id'];
$response = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // --- FETCH USER DATA ---
        $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user) {
            unset($user['password']); // Never send the password hash
            $response['basic_info'] = [
                'first_name'          => $user['first_name'] ?? '',
                'last_name'           => $user['last_name'] ?? '',
                'email'               => $user['email'] ?? '',
                'country'             => $user['country'] ?? '',
                'profile_description' => $user['profile_description'] ?? '',
                'language'            => $user['language'] ?? ''
            ];
        } else {
            http_response_code(404);
            $response['error'] = 'User not found.';
        }

        // --- FETCH PROJECT DATA (with specific error handling) ---
        if (isset($_SESSION['active_project_id'])) {
            try {
                $projectId = $_SESSION['active_project_id'];
                $stmt_project = $pdo->prepare("SELECT project_name FROM project WHERE id = ?");
                $stmt_project->execute([$projectId]);
                $project = $stmt_project->fetch();
                if ($project) {
                    $response['project_info'] = [
                        'project_name' => $project['project_name'] ?? ''
                    ];
                }
            } catch (PDOException $e) {
                // Log the specific error for debugging but don't crash the whole script
                error_log("Could not fetch project info in settings: " . $e->getMessage());
                // Let the frontend know something went wrong with this part
                $response['project_info_error'] = 'Could not load project details.';
            }
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- SAVE USER AND PROJECT DATA (transactional) ---
        try {
            $pdo->beginTransaction();

            // Update user information
            $sql_user = "UPDATE user SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        country = :country, 
                        profile_description = :profile_description, 
                        language = :language 
                    WHERE id = :id";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([
                ':first_name' => trim($_POST['firstName'] ?? ''),
                ':last_name' => trim($_POST['lastName'] ?? ''),
                ':country' => trim($_POST['country'] ?? ''),
                ':profile_description' => trim($_POST['profileDescription'] ?? ''),
                ':language' => trim($_POST['language'] ?? ''),
                ':id' => $userId
            ]);

            // Update project name if provided
            if (isset($_POST['projectName']) && isset($_SESSION['active_project_id'])) {
                $sql_project = "UPDATE project SET project_name = :project_name WHERE id = :id";
                $stmt_project = $pdo->prepare($sql_project);
                $stmt_project->execute([
                    ':project_name' => trim($_POST['projectName']),
                    ':id' => $_SESSION['active_project_id']
                ]);
            }

            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Your account has been updated successfully!';

        } catch (PDOException $e) {
            // If the transaction is active, roll it back
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Then re-throw the exception to be caught by the outer block for a consistent error response
            throw $e;
        }

    } else {
        http_response_code(405); // Method Not Allowed
        $response['error'] = 'Invalid request method.';
    }

} catch (PDOException $e) {
    // Main catch block for all database-related errors
    error_log("Settings Endpoint Error: " . $e->getMessage());
    http_response_code(500);
    $response['error'] = 'A server error occurred while processing your request.';
}

ob_clean();
echo json_encode($response);
exit;

