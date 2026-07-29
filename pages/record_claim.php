<?php
/**
 * backend/record_claim.php
 * Records the successful fundraising claim in the database.
 * * SILICON VALLEY PATCH v6.1 (Precision Targeting):
 * - FIX: Targets updates by 'contract_address' AND 'project_id' to avoid updating old/wrong vaults.
 * - FIX: Ensures the INSERT creates a record specifically for the current sale's contract.
 * - DEBUG: Returns explicit details on what was updated/inserted.
 */

// 1. CORS & Session Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// 2. Force Session Cookie settings
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => '/', 
    'domain' => $cookieParams['domain'],
    'secure' => $cookieParams['secure'],
    'httponly' => $cookieParams['httponly']
]);

// 3. Start Session (Robust)
if (session_status() === PHP_SESSION_NONE) {
    $paths = ['../src/session.php', '../../src/session.php', '../../../src/session.php'];
    $loaded = false;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }
    if (!$loaded) session_start();
}

// 4. Database Connection
if (!isset($pdo)) {
    $dbPaths = ['../src/db.php', '../../src/db.php', '../../../src/db.php'];
    foreach ($dbPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database config missing']);
    exit;
}

try {
    // 5. Auth Check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('User not authenticated. Session ID: ' . session_id());
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sale_id = $input['sale_id'] ?? null;
    $tx_hash = $input['tx_hash'] ?? null;
    $raw_amount = $input['final_amount'] ?? 0;
    
    // Sanitize Amount (Remove commas, ensure float)
    $final_amount = floatval(str_replace(',', '', (string)$raw_amount));

    if (!$sale_id) {
        http_response_code(400);
        throw new Exception('Missing sale_id');
    }

    // 6. Verify Ownership & Fetch Vital Data
    $stmt = $pdo->prepare("
        SELECT id, status, project_id, contract_address, payment_token, sale_terms_json 
        FROM token_sale_pages 
        WHERE id = ? AND project_id IN (SELECT id FROM projet WHERE founder_id = ?)
    ");
    $stmt->execute([$sale_id, $_SESSION['user_id']]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        http_response_code(403);
        throw new Exception('Access Denied');
    }

    $contractAddr = $sale['contract_address'];
    
    // Fallback: If contract address is missing in DB (unlikely for a claiming sale), we can't accurately log.
    if (empty($contractAddr)) {
        // We will continue but warn.
        $contractAddr = '0x0000000000000000000000000000000000000000';
    }

    // --- START TRANSACTION ---
    $pdo->beginTransaction();
    $debugLog = [];
    $debugLog['target_contract'] = $contractAddr;
    $debugLog['final_amount'] = $final_amount;

    // 7. Log Claim Transaction (With Strict Upsert Logic)
    if ($tx_hash) {
        // STRICT CHECK: Match BOTH project_id AND contract_address
        // This prevents updating an old/different vault belonging to the same project.
        $checkVault = $pdo->prepare("SELECT id FROM deployed_escrows WHERE project_id = ? AND contract_address = ?");
        $checkVault->execute([$sale['project_id'], $contractAddr]);
        $existingVault = $checkVault->fetch();

        if ($existingVault) {
            // Scenario A: Precise Record exists, UPDATE it
            $log = $pdo->prepare("
                UPDATE deployed_escrows 
                SET claim_tx = ?, claimed_amount = ?, claimed_at = NOW() 
                WHERE id = ?
            ");
            $log->execute([$tx_hash, $final_amount, $existingVault['id']]);
            $debugLog['escrow_action'] = 'updated_existing_id_' . $existingVault['id'];
            $debugLog['escrow_rows'] = $log->rowCount();
        } else {
            // Scenario B: Record missing for THIS contract, INSERT it
            $terms = json_decode($sale['sale_terms_json'] ?? '{}', true);
            $founderWallet = $terms['vault_custody_wallet'] ?? '';
            
            // Fallback wallet if missing in JSON
            if (empty($founderWallet)) {
                $wStmt = $pdo->prepare("SELECT wallet_address FROM user_wallet WHERE user_id = ? LIMIT 1");
                $wStmt->execute([$_SESSION['user_id']]);
                $founderWallet = $wStmt->fetchColumn() ?? '0x0000000000000000000000000000000000000000';
            }

            $ins = $pdo->prepare("
                INSERT INTO deployed_escrows 
                (project_id, contract_address, payment_token, founder_wallet, deployment_tx, duration, deployed_at, claim_tx, claimed_amount, claimed_at) 
                VALUES (?, ?, ?, ?, 'legacy_missing_tx', 0, NOW(), ?, ?, NOW())
            ");
            $ins->execute([
                $sale['project_id'],
                $contractAddr, // Use the specific contract address
                $sale['payment_token'] ?? '0x0000000000000000000000000000000000000000',
                $founderWallet,
                $tx_hash,
                $final_amount
            ]);
            $debugLog['escrow_action'] = 'inserted_new';
        }
    }

    // 8. Update Investor Statuses
    $updInvest = $pdo->prepare("
        UPDATE investments 
        SET status = 'released_to_creator' 
        WHERE project_id = ? 
        AND status IN ('in_escrow', 'initiated')
        AND status != 'released_to_creator'
    ");
    $updInvest->execute([$sale['project_id']]);
    $debugLog['investments_updated'] = $updInvest->rowCount();

    // 9. Update Sale Page Status
    if ($sale['status'] !== 'ended_successful') {
        $updSale = $pdo->prepare("UPDATE token_sale_pages SET status = 'ended_successful' WHERE id = ?");
        $updSale->execute([$sale_id]);
        $debugLog['sale_status_updated'] = $updSale->rowCount();
    } else {
        $debugLog['sale_status_msg'] = 'Already successful';
    }

    // --- COMMIT TRANSACTION ---
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Claim recorded successfully',
        'debug' => $debugLog
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>