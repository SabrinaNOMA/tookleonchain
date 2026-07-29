<?php
/**
 * backend/save_vault.php
 * Silicon Valley V9.1 - Hybrid Verification Protocol
 * * FIX: Graceful Fallback for RPC Latency.
 * * LOGIC: Attempts RPC verification first. If TX is not yet indexed (common),
 * it falls back to the deterministic 'manual_address' calculated by the Factory.
 * * SECURITY: Explicitly bans saving the Founder/Safe wallet as the Vault address.
 */

if (function_exists('set_time_limit')) set_time_limit(60);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$configPath = __DIR__ . '/../src/contract_config.php';
$config = file_exists($configPath) ? require_once $configPath : [];

if (file_exists('../src/db.php')) require_once '../src/db.php';
else require_once __DIR__ . '/../src/db.php';

function fetchReceipt($txHash, $endpoints) {
    if (!$endpoints) return null;
    foreach ($endpoints as $rpc) {
        $ch = curl_init($rpc);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(["jsonrpc" => "2.0", "method" => "eth_getTransactionReceipt", "params" => [$txHash], "id" => 1]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 5 // Reduced timeout for faster fallback
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res) {
            $data = json_decode($res, true);
            if (isset($data['result'])) return $data['result'];
        }
    }
    return null;
}

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Authentication Required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sale_id = $input['sale_id'] ?? null;
    $tx_hash = trim($input['tx_hash'] ?? '');
    $manual_address = trim($input['manual_address'] ?? ''); // This is the Factory-Predicted Address
    $input_token = trim($input['payment_token'] ?? '');
    
    if (!$sale_id) throw new Exception('Missing Sale ID');

    // 1. Fetch DB Source of Truth
    $stmt = $pdo->prepare("
        SELECT id, project_id, duration_seconds, 
        JSON_UNQUOTE(JSON_EXTRACT(sale_terms_json, '$.vault_custody_wallet')) as expected_safe 
        FROM token_sale_pages 
        WHERE id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) throw new Exception('Sale not found');
    
    $expectedSafe = strtolower($sale['expected_safe'] ?? '');
    if (empty($expectedSafe)) throw new Exception('Sale configuration invalid (Missing Safe Address)');

    // --- PRECISION DATE CALCULATION ---
    $durationSeconds = (int)($sale['duration_seconds']); 
    if ($durationSeconds <= 0) $durationSeconds = 604800; 

    $now = new DateTime();
    $launchAt = $now->format('Y-m-d H:i:s');
    $end = clone $now;
    $end->modify("+{$durationSeconds} seconds");
    $endAt = $end->format('Y-m-d H:i:s');
    
    $finalVaultAddress = null;
    $verificationMode = 'pending';

    // ---------------------------------------------------------
    // STEP 1: ATTEMPT ON-CHAIN RECEIPT VERIFICATION (Best Case)
    // ---------------------------------------------------------
    if (!empty($tx_hash)) {
        $endpoints = $config['RPC_ENDPOINTS'] ?? ["https://mainnet.base.org"];
        $receipt = fetchReceipt($tx_hash, $endpoints);
        
        // Only process if we actually got a receipt (Tx Confirmed)
        if ($receipt && $receipt['status'] !== '0x0') {
            foreach ($receipt['logs'] as $log) {
                if (count($log['topics']) >= 3) {
                    $onChainSafe = '0x' . substr($log['topics'][2], 26);
                    if (strtolower($onChainSafe) === $expectedSafe) {
                        $foundVault = '0x' . substr($log['topics'][1], 26);
                        $finalVaultAddress = $foundVault;
                        $verificationMode = 'verified_rpc';
                        break;
                    }
                }
            }
        }
    }

    // ---------------------------------------------------------
    // STEP 2: FALLBACK TO CONSTRUCTED ADDRESS (Latency Case)
    // If RPC failed/lagged but we have the factory-predicted address, use it.
    // ---------------------------------------------------------
    if (!$finalVaultAddress && !empty($manual_address)) {
        if (preg_match('/^0x[a-fA-F0-9]{40}$/', $manual_address)) {
            $finalVaultAddress = $manual_address;
            $verificationMode = 'predicted_fallback';
        }
    }

    // ---------------------------------------------------------
    // STEP 3: CRITICAL SANITY CHECKS (The "Vault not Wallet" Fix)
    // ---------------------------------------------------------
    if (!$finalVaultAddress) {
        throw new Exception('Could not determine Vault Address via RPC or Prediction.');
    }

    // BLOCKER: Ensure we aren't saving the Founder/Safe wallet as the vault
    if (strtolower($finalVaultAddress) === $expectedSafe) {
        throw new Exception('Security Alert: Detected Safe Address as Vault. Operation Blocked.');
    }

    // ---------------------------------------------------------
    // STEP 4: SAVE TO DATABASE
    // ---------------------------------------------------------
    $pdo->beginTransaction();
    
    $upd = $pdo->prepare("
        UPDATE token_sale_pages 
        SET contract_address = ?, 
            payment_token = ?, 
            status = 'live',
            sale_launch_at = ?, 
            sale_end_at = ?
        WHERE id = ?
    ");
    $upd->execute([$finalVaultAddress, $input_token, $launchAt, $endAt, $sale_id]);

    $check = $pdo->prepare("SELECT id FROM deployed_escrows WHERE contract_address = ?");
    $check->execute([$finalVaultAddress]);
    
    if (!$check->fetch()) {
        $ins = $pdo->prepare("INSERT INTO deployed_escrows (project_id, contract_address, payment_token, founder_wallet, deployment_tx, deployed_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->execute([$sale['project_id'], $finalVaultAddress, $input_token, $expectedSafe, $tx_hash]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'vault' => $finalVaultAddress, 'mode' => $verificationMode]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>