<?php
/**
 * backend/record_claim.php
 * Records the successful fundraising claim and updates investment statuses.
 * VERSION: Silicon Valley Refined v7.0
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Session & DB Initialization
if (session_status() === PHP_SESSION_NONE) {
    $paths = ['../src/session.php', '../../src/session.php', '../../../src/session.php'];
    foreach ($paths as $path) { if (file_exists($path)) { require_once $path; break; } }
    if (session_status() === PHP_SESSION_NONE) session_start();
}

if (!isset($pdo)) {
    $dbPaths = ['../src/db.php', '../../src/db.php', '../../../src/db.php'];
    foreach ($dbPaths as $path) { if (file_exists($path)) { require_once $path; break; } }
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database configuration missing']);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized session.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sale_id = $input['sale_id'] ?? null;
    $tx_hash = $input['tx_hash'] ?? null;
    $raw_amount = $input['final_amount'] ?? 0;
    $final_amount = floatval(str_replace(',', '', (string)$raw_amount));

    if (!$sale_id || !$tx_hash) {
        http_response_code(400);
        throw new Exception('Missing required parameters (sale_id or tx_hash)');
    }

    // 1. Fetch Sale details and verify ownership
    $stmt = $pdo->prepare("
        SELECT tsp.id, tsp.status, tsp.project_id, tsp.contract_address, tsp.sale_name
        FROM token_sale_pages tsp
        JOIN projet p ON tsp.project_id = p.id
        WHERE tsp.id = ? AND p.founder_id = ?
    ");
    $stmt->execute([$sale_id, $_SESSION['user_id']]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        http_response_code(403);
        throw new Exception('Access Denied or Sale not found');
    }

    $pdo->beginTransaction();
    $debug = [];

    // 2. Update the Vault Record
    // We target the specific contract address to ensure we don't overwrite other vaults for the same project
    $updVault = $pdo->prepare("
        UPDATE deployed_escrows 
        SET claim_tx = ?, claimed_amount = ?, claimed_at = NOW() 
        WHERE project_id = ? AND contract_address = ?
    ");
    $updVault->execute([$tx_hash, $final_amount, $sale['project_id'], $sale['contract_address']]);
    $debug['vault_updated'] = $updVault->rowCount();

    // 3. Update Investment Statuses
    // REFINEMENT: We match by both project_id AND sale_name to avoid "Bleeding" status 
    // into other active sales under the same project.
    $updInvest = $pdo->prepare("
        UPDATE investments 
        SET status = 'released_to_creator' 
        WHERE project_id = ? 
        AND sale_name = ?
        AND status IN ('in_escrow', 'initiated')
    ");
    $updInvest->execute([$sale['project_id'], $sale['sale_name']]);
    $debug['investments_affected'] = $updInvest->rowCount();

    // 4. Finalize the Sale Page status
    $updSale = $pdo->prepare("UPDATE token_sale_pages SET status = 'ended_successful' WHERE id = ?");
    $updSale->execute([$sale_id]);
    $debug['sale_page_finalized'] = $updSale->rowCount();

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Claim synchronized with database',
        'details' => $debug
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(http_response_code() === 200 ? 400 : http_response_code());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>