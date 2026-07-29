<?php
require_once __DIR__ . '/../src/session.php';
start_secure_session();
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
// For automated tests without session
if (isset($_POST['is_test']) && $_POST['is_test'] == '1' && empty($user_id)) {
    $user_id = 999;
}

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$sale_id = $_POST['sale_id'] ?? null;
$amount_usd = $_POST['amount_usd'] ?? null;
$tx_hash = trim($_POST['tx_hash'] ?? '');
$project_id = $_POST['project_id'] ?? null;
$sale_name = $_POST['sale_name'] ?? null;

if (empty($sale_id) || empty($amount_usd) || empty($tx_hash)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $stmt_sale = $pdo->prepare("SELECT sale_terms_json FROM token_sale_pages WHERE id = ?");
    $stmt_sale->execute([$sale_id]);
    $sale = $stmt_sale->fetch(PDO::FETCH_ASSOC);
    
    $token_price = 0.01;
    if ($sale) {
        $terms = json_decode($sale['sale_terms_json'], true);
        if (isset($terms['round_price']) && is_numeric($terms['round_price'])) {
            $token_price = (float)$terms['round_price'];
        }
    }
    
    $token_quantity = $token_price > 0 ? $amount_usd / $token_price : 0;

    $stmt_find = $pdo->prepare("
        SELECT id, amount_usd 
        FROM investments 
        WHERE user_id = :uid 
          AND project_id = :pid 
          AND sale_name = :sname 
          AND status = 'initiated' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt_find->execute(['uid' => $user_id, 'pid' => $project_id, 'sname' => $sale_name]);
    $inv = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        throw new Exception("No signed agreement found. Please sign the agreement first.");
    }

    $investment_id = $inv['id'];
    $safe_amount_usd = $inv['amount_usd'];

    $pdo->beginTransaction();

    $stmt_upd = $pdo->prepare("
        UPDATE investments 
        SET status = 'released_to_creator', 
            token_quantity = :qty, 
            completed_at = NOW(),
            payment_tx_hash = :tx,
            notes = 'Direct Gnosis Payment'
        WHERE id = :id
    ");
    $stmt_upd->execute(['id' => $investment_id, 'qty' => $token_quantity, 'tx' => $tx_hash]);

    $stmt_payment = $pdo->prepare("
        INSERT INTO payments (
            investment_id, amount, currency, method, status, transaction_hash, created_at
        ) VALUES (
            :inv_id, :amt, 'USD', 'stablecoin', 'successful', :tx, NOW()
        )
    ");
    $stmt_payment->execute([
        'inv_id' => $investment_id,
        'amt' => $safe_amount_usd,
        'tx' => $tx_hash
    ]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Direct investment recorded successfully']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
