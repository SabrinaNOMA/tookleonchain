<?php
/**
 * TOOKLE - On-Chain Refund Synchronizer (Alchemy Integrated)
 * This script queries the Base blockchain for 'Refunded' events using Alchemy.
 * Located in /cron/ for scheduled execution and backend utility use.
 * * ENGINEER UPDATE:
 * - Increased Timeout to 15s to prevent incomplete syncs.
 * - Added error logging to better trace failures.
 */

// Prevent direct access if not included or CLI
if (count(get_included_files()) == 1 && php_sapi_name() !== 'cli' && !isset($_GET['run_sync'])) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

require_once __DIR__ . '/../src/db.php';

// --- CONFIGURATION ---
$ALCHEMY_API_KEY = "FW0D58_KXM7iLCmKNUeVU"; 
$RPC_URL = "https://base-mainnet.g.alchemy.com/v2/" . $ALCHEMY_API_KEY;

// Signature: "Refunded(address,uint256)"
$REFUND_EVENT_TOPIC = "0xdb006a7506720d65b746813271705608b688d073994d54645258673752762261";

function decodeAddress($topic) {
    return '0x' . substr($topic, 26);
}

function fetchRefundLogs($contractAddress, $fromBlock = "earliest") {
    global $RPC_URL, $REFUND_EVENT_TOPIC;

    $payload = json_encode([
        "jsonrpc" => "2.0",
        "id" => 1,
        "method" => "eth_getLogs",
        "params" => [[
            "address" => $contractAddress,
            "topics" => [$REFUND_EVENT_TOPIC],
            "fromBlock" => $fromBlock,
            "toBlock" => "latest"
        ]]
    ]);

    $ch = curl_init($RPC_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    // UPDATE: Increased timeout from 5s to 15s to handle large log queries
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Alchemy RPC Curl Error: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['error'])) {
        error_log('Alchemy RPC Error: ' . json_encode($data['error']));
        return [];
    }

    return $data['result'] ?? [];
}

/**
 * Main Sync Function
 * Scans for refunds and updates the database status to 'returned_to_backer'
 */
function syncProjectRefunds($projectId, $contractAddress) {
    global $pdo;
    
    // 1. Fetch Logs from Alchemy
    $logs = fetchRefundLogs($contractAddress);
    
    if (empty($logs)) {
        return;
    }

    $refundedWallets = [];
    foreach ($logs as $log) {
        if (isset($log['topics'][1])) {
            $wallet = strtolower(decodeAddress($log['topics'][1]));
            $txHash = $log['transactionHash'];
            $refundedWallets[$wallet] = $txHash;
        }
    }

    if (empty($refundedWallets)) {
        return;
    }

    // 2. Find matching investments in DB that are still marked as 'in_escrow'
    $stmt = $pdo->prepare("
        SELECT id, investor_wallet_address 
        FROM investments 
        WHERE project_id = :pid 
        AND status IN ('in_escrow', 'refund_pending')
    ");
    $stmt->execute([':pid' => $projectId]);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Update DB if match found
    foreach ($investments as $inv) {
        $dbWallet = strtolower($inv['investor_wallet_address']);
        
        if (isset($refundedWallets[$dbWallet])) {
            $txHash = $refundedWallets[$dbWallet];
            
            error_log("Syncing Refund for Investment ID: " . $inv['id'] . " Tx: " . $txHash);

            $updateStmt = $pdo->prepare("
                UPDATE investments 
                SET status = 'returned_to_backer', 
                    refund_tx_hash = :tx,
                    notes = CONCAT(COALESCE(notes, ''), '\n[Alchemy Sync]: Refund confirmed on-chain.')
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':tx' => $txHash,
                ':id' => $inv['id']
            ]);
        }
    }
}
?>