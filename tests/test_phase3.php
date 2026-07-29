<?php
// Mock HTTP Environment for CLI testing
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'sale_id' => 7,
    'amount_usd' => 500,
    'tx_hash' => '0x_AUTOMATED_TEST_HASH_' . time(),
    'project_id' => 46,
    'sale_name' => 'Automated Test Round',
    'is_test' => '1'
];
// Suppress session warnings in CLI
@session_start();
$_SESSION['user_id'] = 999; // Test User

echo "Running Phase 3 Backend Test...\n";

// Capture output first to prevent header warnings
ob_start();
require_once __DIR__ . '/../src/db.php';
// Insert a mock initiated row so the backend finds a signed agreement
$pdo->exec("DELETE FROM investments WHERE user_id = 999 AND project_id = 46");
$pdo->exec("INSERT INTO investments (user_id, project_id, amount_usd, investment_round, sale_name, status, created_at) VALUES (999, 46, 500, 'Test Round', 'Automated Test Round', 'initiated', NOW())");

require_once __DIR__ . '/../backend/record_direct_investment.php';
$output = ob_get_clean();

$response = json_decode($output, true);
if ($response && isset($response['success']) && $response['success'] === true) {
    echo "SUCCESS: Direct Gnosis purchase recorded properly in database!\n";
    echo "Response: " . print_r($response, true) . "\n";
} else {
    echo "FAILED: Expected success response.\n";
    echo "Output: " . $output . "\n";
}
