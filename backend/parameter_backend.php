<?php
/**
 * Backend: Save Sale Parameters
 * Filepath: /backend/parameter_backend.php
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/utils.php'; // Required for generateUniqueSaleToken

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$project_id = $_POST['project_id'] ?? null;
if (empty($project_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Project ID is missing.']);
    exit;
}

$country = $_POST['country'] ?? null;
if (empty($country)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Country is a mandatory field.']);
    exit;
}

try {
    // Get active scenario
    $stmtScenario = $pdo->prepare(
        "SELECT id FROM scenario_version WHERE projet_id = :project_id AND is_active = 1 LIMIT 1"
    );
    $stmtScenario->execute(['project_id' => $project_id]);
    $scenario_version_id = $stmtScenario->fetchColumn() ?: null;

    $stmt = $pdo->prepare("SELECT id FROM token_sale_pages WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $exists = $stmt->fetchColumn();

    // Map fields using the new duration_seconds column
    // We strip commas here to ensure the database receives clean numeric strings
    $data = [
        'sale_name' => $_POST['sale_name'] ?: null,
        'duration_seconds' => (int)($_POST['duration_seconds'] ?? 604800), // Default to 7 days
        'sale_terms_json' => $_POST['sale_terms_json'] ?: null,
        'soft_cap_usd' => !empty($_POST['soft_cap_usd']) ? str_replace(',', '', $_POST['soft_cap_usd']) : null,
        'hard_cap_usd' => !empty($_POST['hard_cap_usd']) ? str_replace(',', '', $_POST['hard_cap_usd']) : null,
        'min_investment_usd' => !empty($_POST['min_investment_usd']) ? str_replace(',', '', $_POST['min_investment_usd']) : null,
        'max_investment_usd' => !empty($_POST['max_investment_usd']) ? str_replace(',', '', $_POST['max_investment_usd']) : null,
        'country' => $country,
        'scenario_version_id' => $scenario_version_id
    ];
    
    if ($exists) {
        $sql = "UPDATE token_sale_pages SET 
                    sale_name = :sale_name,
                    duration_seconds = :duration_seconds,
                    sale_terms_json = :sale_terms_json,
                    soft_cap_usd = :soft_cap_usd, 
                    hard_cap_usd = :hard_cap_usd,
                    min_investment_usd = :min_investment_usd, 
                    max_investment_usd = :max_investment_usd,
                    country = :country,
                    scenario_version_id = :scenario_version_id
                WHERE project_id = :project_id";
    } else {
         // Generate token for new draft if it doesn't exist
         $token = generateUniqueSaleToken($pdo);
         
         $sql = "INSERT INTO token_sale_pages (
                    project_id, sale_url, sale_name, duration_seconds, sale_terms_json, soft_cap_usd, hard_cap_usd, 
                    min_investment_usd, max_investment_usd, country, scenario_version_id
                ) 
                VALUES (
                    :project_id, :sale_url, :sale_name, :duration_seconds, :sale_terms_json, :soft_cap_usd, :hard_cap_usd, 
                    :min_investment_usd, :max_investment_usd, :country, :scenario_version_id
                )";
         
         // Add the token to the data array for the INSERT
         $data['sale_url'] = $token;
    }
    
    $stmt = $pdo->prepare($sql);
    $data['project_id'] = $project_id;
    $stmt->execute($data);
    
    echo json_encode(['success' => true]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Parameter backend error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>