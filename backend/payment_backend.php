<?php
/**
 * backend/payment_backend.php
 * Handles the payment submission and updates the investment status.
 * This script is called from the payment page.
 * FIX: Correctly updates investment and payment statuses based on the provided logic.
 * EDIT: Also saves the percent_unlock_at_tge from the sale terms.
 */
require_once __DIR__ . '/../src/session.php';
start_secure_session();
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

function send_json_error($message, $http_status = 400) {
    http_response_code($http_status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Security: Check CSRF token and authentication
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    send_json_error('Invalid security token.', 403);
}

if (!isset($_SESSION['user_id'])) {
    send_json_error('User not authenticated.', 401);
}
$user_id = $_SESSION['user_id'];

// Idempotency: Prevent duplicate submissions
$idempotency_key = $_POST['idempotency_key'] ?? '';
if (empty($idempotency_key)) {
    send_json_error('A system error occurred (missing idempotency key).');
}
if (isset($_SESSION['processed_keys']) && in_array($idempotency_key, $_SESSION['processed_keys'])) {
    echo json_encode(['success' => true, 'message' => 'Request already processed.']);
    exit();
}

// Validate inputs
$investment_id = filter_input(INPUT_POST, 'investment_id', FILTER_VALIDATE_INT);
if (!$investment_id) {
    send_json_error('Invalid investment ID.');
}

$payment_method = $_POST['payment_method'] ?? '';
if (!in_array($payment_method, ['stablecoin', 'bank_transfer'])) {
    send_json_error('Invalid payment method.');
}

$tx_hash = ($payment_method === 'stablecoin') ? ($_POST['tx_hash'] ?? null) : null;
// For stablecoin, tx_hash is mandatory
if ($payment_method === 'stablecoin' && (empty($tx_hash) || !preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash))) {
    send_json_error('Invalid transaction hash provided for stablecoin payment.');
}

try {
    $pdo->beginTransaction();

    // Fetch the investment to verify ownership and that it's ready for payment
    $stmt = $pdo->prepare(
        "SELECT id, amount_usd, status, project_id, sale_name FROM investments WHERE id = :investment_id AND user_id = :user_id FOR UPDATE"
    );
    $stmt->execute(['investment_id' => $investment_id, 'user_id' => $user_id]);
    $investment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$investment) {
        send_json_error('Contribution not found or you do not have permission to access it.', 404);
    }

    if ($investment['status'] !== 'initiated') {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'This contribution has already been processed.']);
        exit();
    }

    // Fetch sale terms to get the TGE unlock percentage
    $stmt_sale = $pdo->prepare(
        "SELECT sale_terms_json FROM token_sale_pages WHERE project_id = :project_id AND sale_name = :sale_name"
    );
    $stmt_sale->execute(['project_id' => $investment['project_id'], 'sale_name' => $investment['sale_name']]);
    $sale_terms_raw = $stmt_sale->fetchColumn();
    $sale_terms = json_decode($sale_terms_raw ?: '{}', true);
    $percent_unlock_at_tge = $sale_terms['percent_unlock_at_tge'] ?? null;


    // Determine new statuses based on payment method
    $new_investment_status = 'initiated'; // Default for bank transfer
    $new_payment_status = 'pending';    // Default for bank transfer

    if ($payment_method === 'stablecoin') {
        $new_investment_status = 'in_escrow';
        $new_payment_status = 'successful';
    }

    // Update the investment status and TGE percentage
    $stmt_update = $pdo->prepare(
        "UPDATE investments 
         SET status = :status, agreement_approved = 1, agreement_approved_at = NOW(), percent_unlock_at_tge = :tge_unlock
         WHERE id = :investment_id"
    );
    $stmt_update->execute([
        'status' => $new_investment_status,
        'tge_unlock' => $percent_unlock_at_tge,
        'investment_id' => $investment_id
    ]);

    // Create a corresponding payment record
    $stmt_payment = $pdo->prepare(
        "INSERT INTO payments (investment_id, amount, currency, method, status, transaction_hash)
         VALUES (:investment_id, :amount, 'USD', :method, :status, :tx_hash)"
    );
    $stmt_payment->execute([
        'investment_id' => $investment_id,
        'amount' => $investment['amount_usd'],
        'method' => $payment_method,
        'status' => $new_payment_status,
        'tx_hash' => $tx_hash
    ]);
    
    $pdo->commit();

    // Store the idempotency key to prevent re-processing
    if (!isset($_SESSION['processed_keys'])) $_SESSION['processed_keys'] = [];
    $_SESSION['processed_keys'][] = $idempotency_key;
    if (count($_SESSION['processed_keys']) > 50) array_shift($_SESSION['processed_keys']);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Payment backend error: " . $e->getMessage());
    send_json_error('A database error occurred.', 500);
}

