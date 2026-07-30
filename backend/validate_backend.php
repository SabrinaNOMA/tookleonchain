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

    // We want to ensure the active scenario has the LATEST data from domain tables (including vesting and allocations)
    $stmt_active_version = $pdo->prepare("SELECT id FROM scenario_version WHERE projet_id = :project_id AND is_active = 1 LIMIT 1");
    $stmt_active_version->execute([':project_id' => $project_id]);
    $active_version_id = $stmt_active_version->fetchColumn();

    // Fetch all necessary data to build and save the full project snapshot.
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

    $stmt_allocations = $pdo->prepare("SELECT * FROM tranche_token WHERE projet_id = :project_id AND LOWER(tranche_type) != 'investor'");
    $stmt_allocations->execute([':project_id' => $project_id]);
    $allocations = $stmt_allocations->fetchAll(PDO::FETCH_ASSOC);

    $version_payload = [
        'core_params' => $core_params,
        'rounds' => $rounds,
        'vesting' => $vesting,
        'allocations' => $allocations
    ];
    
    $version_data_json = json_encode($version_payload);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to encode scenario JSON data: " . json_last_error_msg());
    }
    
    if (!$active_version_id) {
        // Insert the new snapshot
        $sql_version = "INSERT INTO scenario_version (projet_id, version_label, data, is_active) VALUES (:projet_id, 'Initial Version', :data, TRUE)";
        $stmt_version = $pdo->prepare($sql_version);
        $stmt_version->execute([
            ':projet_id' => $project_id,
            ':data' => $version_data_json
        ]);
        $response['message'] = 'Initial tokenomics snapshot successfully created.';
    } else {
        // Update the active snapshot with the full tokenomics data
        $sql_version = "UPDATE scenario_version SET data = :data WHERE id = :version_id";
        $stmt_version = $pdo->prepare($sql_version);
        $stmt_version->execute([
            ':data' => $version_data_json,
            ':version_id' => $active_version_id
        ]);
        $response['message'] = 'Tokenomics snapshot successfully updated with full vesting and allocation data.';
    }

    // --- FIX: Corrected the column name from 'tokenomics_approved' to 'tokenomics_done' ---
    $stmt_update_projet = $pdo->prepare("UPDATE projet SET tokenomics_done = 1 WHERE id = :project_id");
    $stmt_update_projet->execute([':project_id' => $project_id]);

    // --- Ensure any existing sale pages for this project are linked to the active scenario ---
    $scenarioIdToLink = $active_version_id ?: $pdo->lastInsertId();
    $stmt_link_sales = $pdo->prepare("UPDATE token_sale_pages SET scenario_version_id = :scenario_id WHERE project_id = :project_id AND (scenario_version_id IS NULL OR scenario_version_id != :scenario_id2)");
    $stmt_link_sales->execute([
        ':scenario_id' => $scenarioIdToLink,
        ':project_id' => $project_id,
        ':scenario_id2' => $scenarioIdToLink
    ]);

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

