<?php
/**
 * backend/heal_refund.php
 * SURGICAL SYNC: Finds ANY transaction log for this wallet on this contract.
 * STRATEGY: "If this wallet interacted with this contract and logs exist, assume it's the refund."
 */
require_once __DIR__ . '/../src/session.php';
start_secure_session();
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Auth required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$investment_id = $input['investment_id'] ?? null;
// The wallet address currently connected in the frontend
$connected_wallet = $input['wallet_address'] ?? null;

if (!$investment_id) {
    echo json_encode(['success' => false, 'error' => 'Missing investment ID']);
    exit;
}

// 1. Get Contract Address & Stored Wallet from DB
$stmt = $pdo->prepare("
    SELECT tsp.contract_address, i.status, i.investor_wallet_address
    FROM investments i 
    JOIN token_sale_pages tsp ON i.sale_name = tsp.sale_name AND i.project_id = tsp.project_id
    WHERE i.id = :id AND i.user_id = :uid
");
$stmt->execute([':id' => $investment_id, ':uid' => $_SESSION['user_id']]);
$row = $stmt->fetch();

if (!$row) exit(json_encode(['success' => false, 'error' => 'Not found']));
if ($row['status'] === 'returned_to_backer') exit(json_encode(['success' => true, 'msg' => 'Already updated']));

// 2. Determine target wallet
$target_wallet = !empty($row['investor_wallet_address']) ? $row['investor_wallet_address'] : $connected_wallet;
if (empty($target_wallet)) {
     echo json_encode(['success' => false, 'error' => 'No wallet to check.']);
     exit;
}

// Normalize wallet for search (lowercase, no 0x)
$search_wallet_clean = strtolower(str_replace('0x', '', $target_wallet));

// 3. Query Alchemy (ULTRA-BROAD SEARCH)
// We ask for ALL logs emitted by this contract. We do not filter by topic.
$ALCHEMY_API_KEY = "FW0D58_KXM7iLCmKNUeVU"; 
$RPC_URL = "https://base-mainnet.g.alchemy.com/v2/" . $ALCHEMY_API_KEY;

$payload = json_encode([
    "jsonrpc" => "2.0", "id" => 1, "method" => "eth_getLogs",
    "params" => [[
        "address" => $row['contract_address'],
        "fromBlock" => "earliest",
        "toBlock" => "latest"
    ]]
]);

$ch = curl_init($RPC_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$found_tx = null;

if (!empty($result['result'])) {
    foreach ($result['result'] as $log) {
        // Iterate through ALL topics in the log to find the wallet address
        foreach ($log['topics'] as $topic) {
            $topic_clean = str_replace('0x', '', $topic);
            // Check if our wallet address (without 0x) is present inside the topic string
            // (Topics are 64 chars, addresses are 40 chars. It will be padded.)
            if (strpos(strtolower($topic_clean), $search_wallet_clean) !== false) {
                $found_tx = $log['transactionHash'];
                break 2; // Found it! Break both loops.
            }
        }
    }
}

if ($found_tx) {
    // UPDATE
    $update = $pdo->prepare("
        UPDATE investments 
        SET status = 'returned_to_backer', 
            refund_tx_hash = :tx, 
            investor_wallet_address = :wallet,
            notes = CONCAT(COALESCE(notes,''), '\n[Self-Heal]: Recovered TX via Ultra-Broad Search') 
        WHERE id = :id
    ");
    $update->execute([
        ':tx' => $found_tx, 
        ':wallet' => $target_wallet,
        ':id' => $investment_id
    ]);
    
    echo json_encode(['success' => true, 'healed' => true, 'tx' => $found_tx]);
} else {
    $count = isset($result['result']) ? count($result['result']) : 0;
    echo json_encode(['success' => false, 'error' => "Scanned $count total contract logs. Your wallet ($search_wallet_clean) does not appear in any of them."]);
}
?>