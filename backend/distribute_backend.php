<?php
/**
 * Backend script for the Distribute page.
 * Filepath: /backend/distribute_backend.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/db.php';

// --- Helper Functions ---
function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// --- Main Logic ---
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    // For this backend, we only care about POST requests.
    // GET request data is now handled directly within distribute.php
    return;
}

$user_id = $_SESSION['user_id'] ?? null;
$project_id_from_session = $_SESSION['active_project_id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);

handle_post_request($pdo, $project_id_from_session, $user_id, $input);


function handle_post_request($pdo, $project_id_from_session, $user_id, $input) {
    $project_id = $input['project_id'] ?? $project_id_from_session;

    if (!$project_id || !$user_id) {
        send_json_response(['error' => 'Project or User session not found. Please log in and select a project.'], 400);
    }

    $action = $input['action'] ?? null;

    try {
        switch ($action) {
            case 'save_deployed_token':
                saveDeployedToken($pdo, $input, $user_id, $project_id);
                break;
            case 'select_token':
                selectToken($pdo, $input, $user_id, $project_id);
                break;
            case 'delete_token':
                deleteToken($pdo, $input, $user_id, $project_id);
                break;
            case 'set_vesting_contract':
                setVestingContract($input);
                break;
            default:
                throw new Exception('Invalid action specified.');
        }
    } catch (Exception $e) {
        error_log("Distribute Page POST Error: " . $e->getMessage());
        send_json_response(['error' => $e->getMessage()], 500);
    }
}

function setVestingContract(array $data): void {
    if (empty($data['contract_address'])) {
        throw new Exception('Contract address is missing.');
    }
    $_SESSION['vesting_token_contract'] = $data['contract_address'];
    send_json_response(['success' => true]);
}


function saveDeployedToken(PDO $pdo, array $data, int $user_id, string $project_id): void {
    if (!$user_id || !is_int($user_id)) {
        throw new Exception("Authentication required. Please log in.", 401);
    }
    
    if (empty($project_id) || !is_string($project_id)) {
        throw new Exception("Project ID is missing or invalid.", 400);
    }

    $required_fields = ['contract_address', 'network_chain_id', 'wallet_address', 'token_name', 'token_symbol', 'initial_supply', 'is_mintable'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || ($data[$field] === '' && !is_bool($data[$field]))) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    $stmt = $pdo->prepare("SELECT founder_id FROM projet WHERE id = ?");
    $stmt->execute([$project_id]);
    if ($stmt->fetchColumn() != $user_id) {
        throw new Exception('You do not have permission to modify this project.', 403);
    }

    $networkName = 'Unknown';
    switch ($data['network_chain_id']) {
        case '1': $networkName = 'Ethereum'; break;
        case '8453': $networkName = 'Base'; break;
        case '84532': $networkName = 'Base Sepolia'; break;
    }

    $snapshot_data = json_encode([
        'token_name' => $data['token_name'],
        'token_symbol' => $data['token_symbol'],
        'initial_supply' => $data['initial_supply'],
        'is_mintable' => $data['is_mintable'],
        'deployment_tx_hash' => $data['deployment_tx_hash'] ?? null
    ]);

    $sql = "INSERT INTO deployed_token (contract, deployment_date, network, wallet, user_id, projet_id, scenario_version_id, snapshot_data) VALUES (:contract, CURDATE(), :network, :wallet, :user_id, :projet_id, :scenario_version_id, :snapshot_data)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':contract', $data['contract_address'], PDO::PARAM_STR);
    $stmt->bindValue(':network', $networkName, PDO::PARAM_STR);
    $stmt->bindValue(':wallet', $data['wallet_address'], PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':projet_id', $project_id, PDO::PARAM_STR);
    $stmt->bindValue(':scenario_version_id', $data['scenario_version_id'] ?? null, PDO::PARAM_INT);
    $stmt->bindValue(':snapshot_data', $snapshot_data, PDO::PARAM_STR);
    $stmt->execute();
    
    $new_token_id = $pdo->lastInsertId();

    if ($stmt->rowCount() > 0) {
        $new_token_record = [
            'id' => $new_token_id,
            'contract' => $data['contract_address'],
            'deployment_date' => date('Y-m-d'),
            'network' => $networkName,
            'wallet' => $data['wallet_address'],
            'snapshot_data' => $snapshot_data,
            'selected_contract' => 'no'
        ];
        send_json_response([
            'success' => true, 
            'message' => 'Token saved successfully.', 
            'new_token' => $new_token_record
        ]);
    } else {
        throw new Exception('Failed to save the token to the database.');
    }
}

function selectToken(PDO $pdo, array $data, int $user_id, string $project_id): void {
    $token_id = $data['token_id'] ?? null;
    if (!$token_id) throw new Exception('Missing token ID.');

    $stmt_verify = $pdo->prepare("SELECT p.id FROM deployed_token dt JOIN projet p ON dt.projet_id = p.id WHERE dt.id = ? AND p.founder_id = ? AND p.id = ?");
    $stmt_verify->execute([$token_id, $user_id, $project_id]);
    if (!$stmt_verify->fetch()) {
        throw new Exception('Token not found or you are not authorized for this project.', 403);
    }

    $pdo->beginTransaction();
    $stmt_reset = $pdo->prepare("UPDATE deployed_token SET selected_contract = 'no' WHERE projet_id = ?");
    $stmt_reset->bindValue(1, $project_id, PDO::PARAM_STR);
    $stmt_reset->execute();
    $stmt_select = $pdo->prepare("UPDATE deployed_token SET selected_contract = 'yes' WHERE id = ?");
    $stmt_select->execute([$token_id]);
    $pdo->commit();

    send_json_response(['success' => true, 'message' => 'Token selected successfully.']);
}

function deleteToken(PDO $pdo, array $data, int $user_id, string $project_id): void {
    $token_id = $data['token_id'] ?? null;
    if (!$token_id) throw new Exception('Missing token ID.');

    $stmt_check = $pdo->prepare("SELECT dt.selected_contract FROM deployed_token dt JOIN projet p ON dt.projet_id = p.id WHERE dt.id = ? AND p.founder_id = ? AND p.id = ?");
    $stmt_check->execute([$token_id, $user_id, $project_id]);
    $token_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$token_info) {
        throw new Exception('Token not found or you do not have permission to delete it.', 404);
    }
    if ($token_info['selected_contract'] === 'yes') {
        throw new Exception('Cannot delete a token that is currently selected for distribution.');
    }

    $stmt_delete = $pdo->prepare("DELETE FROM deployed_token WHERE id = ?");
    $stmt_delete->execute([$token_id]);
    
    if ($stmt_delete->rowCount() > 0) {
        send_json_response(['success' => true, 'message' => 'Token deleted successfully.']);
    } else {
        throw new Exception('Failed to delete the token.');
    }
}
