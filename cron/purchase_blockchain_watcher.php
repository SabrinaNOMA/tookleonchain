<?php
/**
 * cron/purchase_blockchain_watcher.php
 * PRODUCTION VERSION - SILENT MODE COMPATIBLE
 * FIXED: Functions moved to top to prevent "Call to undefined function" error.
 */

// 1. CONFIGURATION DU MODE SILENCIEUX
if (!isset($watcher_silent_mode)) {
    $watcher_silent_mode = false;
}

if (!function_exists('watcher_log')) {
    function watcher_log($msg) {
        global $watcher_silent_mode;
        if ($watcher_silent_mode === false) {
            echo $msg;
        }
    }
}

// 2. HELPER FUNCTIONS (IMPORTANT : Placées AVANT leur utilisation)
// =============================================================================

if (!function_exists('parseAmountFromInput')) {
    function parseAmountFromInput($inputHex) {
        $input = substr($inputHex, 2);
        if (strlen($input) < 72) return 0; 
        $paramHex = substr($input, 8, 64);
        $valRaw = hexdec($paramHex);
        return $valRaw / 1000000;
    }
}

if (!function_exists('fetchRpcData')) {
    function fetchRpcData($method, $params, $endpoints) {
        foreach ($endpoints as $rpcUrl) {
            $payload = json_encode([
                "jsonrpc" => "2.0",
                "method" => $method,
                "params" => $params,
                "id" => 1
            ]);

            $ch = curl_init($rpcUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) continue;
            
            $data = json_decode($response, true);
            if (isset($data['result'])) return $data['result'];
        }
        return null;
    }
}

if (!function_exists('markAsSuccessful')) {
    function markAsSuccessful($pdo, $payId, $invId) {
        try {
            if (!$pdo->inTransaction()) $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("UPDATE payments SET status = 'successful', updated_at = NOW() WHERE id = :id");
            $stmt1->execute(['id' => $payId]);
            $stmt2 = $pdo->prepare("UPDATE investments SET status = 'in_escrow', completed_at = NOW() WHERE id = :id AND status != 'in_escrow'");
            $stmt2->execute(['id' => $invId]);
            $pdo->commit();
            watcher_log("    -> DB Updated: PAID\n");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}

if (!function_exists('markAsFailed')) {
    function markAsFailed($pdo, $payId, $invId, $reason = "") {
        try {
            if (!$pdo->inTransaction()) $pdo->beginTransaction();
            $stmt1 = $pdo->prepare("UPDATE payments SET status = 'failed', updated_at = NOW() WHERE id = :id");
            $stmt1->execute(['id' => $payId]);
            $stmt2 = $pdo->prepare("UPDATE investments SET status = 'failed', notes = CONCAT(COALESCE(notes, ''), :reason) WHERE id = :id");
            $stmt2->execute(['reason' => " [Auto-Fail: $reason]", 'id' => $invId]);
            $pdo->commit();
            watcher_log("    -> DB Updated: FAILED\n");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}

// 3. ENVIRONMENT SETUP & LOGIC
// =============================================================================

if (!isset($pdo)) {
    $dbPath = __DIR__ . '/../src/db.php';
    if (!file_exists($dbPath)) {
        watcher_log("Error: Could not find database file at $dbPath\n");
        return;
    }
    $pdo = require_once $dbPath;
    if ($pdo === true || $pdo === 1) { 
        global $pdo; 
    }
}

$rpcEndpoints = [
    'https://mainnet.base.org',       
    'https://base.publicnode.com',    
    'https://1rpc.io/base'            
];

$USDC_CONTRACT = strtolower('0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'); 

watcher_log("\n[" . date('Y-m-d H:i:s') . "] --- STARTING WATCHER ---\n");

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id AS payment_id, 
            p.transaction_hash, 
            p.investment_id,
            p.amount AS expected_amount,
            i.investor_wallet_address,
            i.user_id
        FROM payments p
        JOIN investments i ON p.investment_id = i.id
        WHERE p.status = 'pending' 
        ORDER BY p.created_at ASC
        LIMIT 20
    ");
    $stmt->execute();
    $pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($pendingPayments);
    watcher_log("Found {$count} pending transactions.\n");

    if ($count > 0) {
        foreach ($pendingPayments as $payment) {
            $txHash = trim($payment['transaction_hash']);
            $payId = $payment['payment_id'];
            $invId = $payment['investment_id'];
            $expectedAmount = (float)$payment['expected_amount'];
            $expectedSender = strtolower(trim($payment['investor_wallet_address'] ?? '')); 

            watcher_log("------------------------------------------------\n");
            watcher_log("Checking TX: $txHash\n");

            // MAINTENANT CELA VA MARCHER CAR LA FONCTION EST DÉFINIE PLUS HAUT
            $receipt = fetchRpcData('eth_getTransactionReceipt', [$txHash], $rpcEndpoints);
            $txData  = fetchRpcData('eth_getTransactionByHash', [$txHash], $rpcEndpoints);

            if ($receipt === null || $txData === null) {
                watcher_log(" -> RESULT: Syncing / Not found yet\n");
                continue;
            }

            $statusHex = $receipt['status'] ?? null; 
            
            if ($statusHex === '0x0') {
                watcher_log(" -> RESULT: REVERTED ON-CHAIN (0x0)\n");
                markAsFailed($pdo, $payId, $invId, "On-chain revert");
                continue;
            }

            if ($statusHex !== '0x1') {
                watcher_log(" -> RESULT: Unknown Status (" . json_encode($statusHex) . ")\n");
                continue;
            }

            $actualSender = strtolower($txData['from'] ?? '');
            if ($expectedSender && $actualSender !== $expectedSender) {
                watcher_log(" -> ALERT: SENDER MISMATCH!\n");
                markAsFailed($pdo, $payId, $invId, "Sender Mismatch: $actualSender");
                continue;
            }

            $inputData = $txData['input'] ?? '';
            $actualAmount = parseAmountFromInput($inputData);
            
            if (abs($actualAmount - $expectedAmount) > 0.1) {
                watcher_log(" -> ALERT: AMOUNT MISMATCH!\n");
                markAsFailed($pdo, $payId, $invId, "Amount Mismatch. Paid: $actualAmount");
                continue;
            }

            watcher_log(" -> RESULT: VERIFIED\n");
            markAsSuccessful($pdo, $payId, $invId);
        }
    }

} catch (Exception $e) {
    watcher_log("CRITICAL ERROR: " . $e->getMessage() . "\n");
}

watcher_log("[" . date('Y-m-d H:i:s') . "] --- FINISHED ---\n");
?>