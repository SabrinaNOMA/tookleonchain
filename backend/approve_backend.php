<?php
/**
 * Backend: Approve and Submit Sale Page
 * Filepath: /backend/approve_backend.php
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');
$response = ['success' => false];

// Get the data sent from the frontend AJAX call
$input = json_decode(file_get_contents('php://input'), true);
$project_id = $input['project_id'] ?? null;

if (empty($project_id)) {
    $response['error'] = 'Project ID is missing.';
    echo json_encode($response);
    exit;
}

try {
    // Start a transaction to ensure all database operations succeed or fail together
    $pdo->beginTransaction();

    // Check if a version already exists for this project. If not, create one.
    $stmt_version_check = $pdo->prepare("SELECT COUNT(*) FROM scenario_version WHERE projet_id = :project_id");
    $stmt_version_check->execute([':project_id' => $project_id]);
    $version_exists = ($stmt_version_check->fetchColumn() > 0);

    if (!$version_exists) {
        // This logic is adapted from your f23_save_project_data.php
        // It fetches all necessary data to build and save the initial project version.
        $stmt_projet = $pdo->prepare("SELECT * FROM projet WHERE id = :project_id");
        $stmt_projet->execute([':project_id' => $project_id]);
        $core_params = $stmt_projet->fetch(PDO::FETCH_ASSOC);

        // --- MODIFICATION: Remove payment details from the version snapshot ---
        unset($core_params['purchase_method']);

        $stmt_rounds = $pdo->prepare("SELECT * FROM round_token WHERE projet_id = :project_id");
        $stmt_rounds->execute([':project_id' => $project_id]);
        $rounds = $stmt_rounds->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_vesting = $pdo->prepare("SELECT * FROM vesting_token WHERE projet_id = :project_id");
        $stmt_vesting->execute([':project_id' => $project_id]);
        $vesting = $stmt_vesting->fetchAll(PDO::FETCH_ASSOC);

        $version_payload = [
            'core_params' => $core_params,
            'rounds' => $rounds,
            'vesting' => $vesting
        ];
        
        $version_data_json = json_encode($version_payload);

        if (json_last_error() === JSON_ERROR_NONE) {
            $sql_version = "INSERT INTO scenario_version (projet_id, version_label, data, is_active) VALUES (:projet_id, 'Initial Version', :data, TRUE)";
            $stmt_version = $pdo->prepare($sql_version);
            $stmt_version->execute([
                ':projet_id' => $project_id,
                ':data' => $version_data_json
            ]);
        } else {
            throw new Exception("Failed to encode scenario JSON data.");
        }
    }

    // Update the token sale page status to 'draft'
    $sql_update_status = "UPDATE token_sale_pages SET status = 'draft' WHERE project_id = :project_id";
    $stmt_status = $pdo->prepare($sql_update_status);
    $stmt_status->execute([':project_id' => $project_id]);

    // Update the projet table to mark the page as ready
    $sql_update_projet = "UPDATE projet SET token_sale_page_ready = 1 WHERE id = :project_id";
    $stmt_projet_ready = $pdo->prepare($sql_update_projet);
    $stmt_projet_ready->execute([':project_id' => $project_id]);


    // Commit all changes to the database
    $pdo->commit();

    // Send a success response with the redirect URL
    $response['success'] = true;
    $response['redirect_url'] = '/dashboard';
    echo json_encode($response);

} catch (Exception $e) {
    // If any error occurs, roll back the transaction
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    
    // Send an error response
    error_log("Approve Backend Error: " . $e->getMessage());
    $response['error'] = 'A server error occurred during submission.';
    echo json_encode($response);
}
?>
