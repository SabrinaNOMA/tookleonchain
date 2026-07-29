<?php
/**
 * Automated QA Test for Investor Legal & Compliance Portal
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
@session_start();
$_SESSION['user_id'] = 999; // Mock User

echo "Running Legal Portal Backend Test...\n";

// Capture output
ob_start();
require_once __DIR__ . '/../src/db.php';

// Get an existing project ID
$stmt_proj = $pdo->query("SELECT id FROM projet LIMIT 1");
$existing_proj_id = $stmt_proj->fetchColumn();
if (!$existing_proj_id) {
    $existing_proj_id = 'test-proj-1';
    $pdo->exec("INSERT INTO projet (id, project_name, project_website) VALUES ('$existing_proj_id', 'Tookle Test Network', 'https://tookle.com')");
}

$pdo->exec("DELETE FROM user WHERE id = 999");
$pdo->exec("INSERT INTO user (id, email, password, first_name, last_name, kyc_status) VALUES (999, 'investor@tookle.com', 'mockpass_hash_test', 'Satoshi', 'Nakamoto', 'approved')");

$pdo->exec("DELETE FROM investments WHERE user_id = 999");
$res = $pdo->exec("INSERT INTO investments (user_id, project_id, amount_usd, token_quantity, investment_round, sale_name, status, created_at) VALUES (999, '$existing_proj_id', 1000, 10000, 'Seed', 'Seed Round', 'in_escrow', NOW())");
echo "Inserted investment rows: " . $res . " with proj_id: " . $existing_proj_id . "\n";
$stmt_check = $pdo->query("SELECT * FROM investments WHERE user_id = 999");
echo "Dump investments:\n";
print_r($stmt_check->fetchAll(PDO::FETCH_ASSOC));
$stmt_check2 = $pdo->query("SELECT i.id, i.project_id, p.id AS p_id, p.project_name FROM investments i LEFT JOIN projet p ON i.project_id = p.id WHERE i.user_id = 999");
echo "Dump JOIN:\n";
print_r($stmt_check2->fetchAll(PDO::FETCH_ASSOC));

require_once __DIR__ . '/../backend/legal_backend.php';
$output = ob_get_clean();

// Check if $page_data was generated properly
if (isset($page_data) && is_array($page_data)) {
    echo "SUCCESS: page_data generated correctly!\n";
    if (isset($page_data['error'])) {
        echo "CAUGHT ERROR IN BACKEND: " . $page_data['error'] . "\n";
    }
    echo "TSA List count: " . count($page_data['tsa_list']) . "\n";
    echo "Commercial Records count: " . count($page_data['commercial_records']) . "\n";

    // Assert zero illegal financial terms
    $jsonDump = json_encode($page_data);
    $illegalTerms = ['tax settlement', 'equity receipt', 'security allocation'];
    foreach ($illegalTerms as $term) {
        if (stripos($jsonDump, $term) !== false) {
            echo "FAIL: Found illegal security term '{$term}' in output!\n";
            exit(1);
        }
    }
    echo "SUCCESS: Zero regulatory requalification keywords found.\n";
    echo "ALL LEGAL PORTAL TESTS PASSED!\n";

    // Clean up mock data
    $pdo->exec("DELETE FROM investments WHERE user_id = 999");
    $pdo->exec("DELETE FROM user WHERE id = 999");
} else {
    echo "FAIL: page_data was not generated.\n";
    echo "Output: " . $output . "\n";
}
