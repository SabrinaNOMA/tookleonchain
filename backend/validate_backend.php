<?php
/**
 * Backend: Validate and Snapshot Token Economy
 * Filepath: /backend/validate_backend.php
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');
$response = ['success' => false];

// Get the data sent from the frontend AJAX call
$input = json_decode(file_get_contents('php://input'), true);
$project_id = $input['project_id'] ?? $_SESSION['active_project_id'] ?? null;

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
        // Fetch all necessary data to build and save the initial project version.
        $stmt_projet = $pdo->prepare("SELECT * FROM projet WHERE id = :project_id");
        $stmt_projet->execute([':project_id' => $project_id]);
        $core_params = $stmt_projet->fetch(PDO::FETCH_ASSOC);

        if (!$core_params) {
            throw new Exception("Core project data not found. Cannot create snapshot.");
        }

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

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to encode scenario JSON data: " . json_last_error_msg());
        }
        
        // Insert the new snapshot
        $sql_version = "INSERT INTO scenario_version (projet_id, version_label, data, is_active) VALUES (:projet_id, 'Initial Version', :data, TRUE)";
        $stmt_version = $pdo->prepare($sql_version);
        $stmt_version->execute([
            ':projet_id' => $project_id,
            ':data' => $version_data_json
        ]);
        $response['message'] = 'Initial tokenomics snapshot successfully created.';

    } else {
        $response['message'] = 'Tokenomics snapshot already exists. No new snapshot created.';
    }

    // --- FIX: Corrected the column name from 'tokenomics_approved' to 'tokenomics_done' ---
    $stmt_update_projet = $pdo->prepare("UPDATE projet SET tokenomics_done = 1 WHERE id = :project_id");
    $stmt_update_projet->execute([':project_id' => $project_id]);

    // Commit all changes
    $pdo->commit();

    $response['success'] = true;
    echo json_encode($response);

} catch (Exception $e) {
    // If any error occurs, roll back the transaction
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    
    error_log("Validate Backend Error: " . $e->getMessage());
    $response['error'] = 'A server error occurred while creating the snapshot.';
    echo json_encode($response);
}
?>

