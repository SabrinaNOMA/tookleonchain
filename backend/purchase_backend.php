<?php
/**
 * backend/purchase_backend.php
 * Handles the investment intent creation and finalization.
 * * SECURITY & STABILITY FIXES:
 * 1. OUTPUT BUFFERING: Prevents stray HTML (warnings/errors) from breaking JSON.
 * 2. ERROR HANDLING: Catches Fatal Errors and returns them as JSON.
 * 3. LOGIC: Securely records transactions and enforces agreements.
 * 4. DB FIX: Corrected ENUM status for payments table (pending_verification -> pending).
 * 5. SECURITY UPDATE: Marks payments as 'pending' to be verified by the Cron Job.
 * 6. DATA FIX: Restored saving of Vesting Terms (Cliff, Duration, TGE Unlock) to the database.
 * 7. HASH STORAGE: Appends the Digital Signature Stamp to the saved agreement in the DB.
 */

// 1. SILENCE HTML ERRORS & START BUFFER
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ob_start(); // Catch any accidental output

header('Content-Type: application/json');

try {
    // 2. DEPENDENCIES
    require_once __DIR__ . '/../src/session.php';
    if (!function_exists('start_secure_session')) {
        throw new Exception("Session system missing");
    }
    start_secure_session();

    require_once __DIR__ . '/../src/db.php';
    if (!isset($pdo)) {
        throw new Exception("Database connection failed");
    }

    // 3. HELPER: Clean Exit
    function send_json_response($data, $http_code = 200) {
        // Clear buffer before sending to ensure clean JSON
        ob_clean(); 
        http_response_code($http_code);
        echo json_encode($data);
        exit();
    }

    function send_json_error($error_code, $message, $http_status = 400) {
        send_json_response(['success' => false, 'error_code' => $error_code, 'message' => $message], $http_status);
    }

    // 4. SECURITY CHECKS
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        send_json_error('CSRF_FAILED', 'Invalid security token. Please refresh the page.', 403);
    }

    if (!isset($_SESSION['user_id'])) {
        send_json_error('UNAUTHENTICATED', 'User not authenticated.', 401);
    }

    $user_id = $_SESSION['user_id'];
    $project_id = $_POST['project_id'] ?? $_SESSION['selected_project_id'] ?? null;
    $sale_name = $_POST['sale_name'] ?? $_SESSION['selected_sale_name'] ?? null;

    // =================================================================================
    // ACTION: RECORD TRANSACTION (Finalize Investment)
    // =================================================================================
    if (isset($_POST['action']) && $_POST['action'] === 'record_tx') {
        $tx_hash = filter_input(INPUT_POST, 'tx_hash', FILTER_SANITIZE_STRING);
        
        if (!$tx_hash || strlen($tx_hash) < 10) send_json_error('INVALID_HASH', 'Invalid transaction hash provided.');
        if (!$project_id || !$sale_name) send_json_error('MISSING_CONTEXT', 'Project ID or Sale Name missing.');
        
        $pdo->beginTransaction();

        // Find initiated investment
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
            $pdo->rollBack();
            throw new Exception("No active agreement found. Please sign the agreement first.");
        }

        $investment_id = $inv['id'];
        $safe_amount_usd = $inv['amount_usd'];

        // Calculate Tokens
        $token_quantity = 0;
        $stmt_sale = $pdo->prepare("SELECT sale_terms_json FROM token_sale_pages WHERE project_id = :pid AND sale_name = :sname LIMIT 1");
        $stmt_sale->execute(['pid' => $project_id, 'sname' => $sale_name]);
        $sale_data = $stmt_sale->fetch(PDO::FETCH_ASSOC);
        
        if ($sale_data && !empty($sale_data['sale_terms_json'])) {
            $terms = json_decode($sale_data['sale_terms_json'], true);
            $price = $terms['round_price'] ?? 0;
            if ($price > 0) $token_quantity = $safe_amount_usd / $price;
        }

        // Check Duplicate Hash
        $stmt_check_pay = $pdo->prepare("SELECT id FROM payments WHERE transaction_hash = :tx");
        $stmt_check_pay->execute(['tx' => $tx_hash]);
        if ($stmt_check_pay->fetch()) {
            $pdo->rollBack();
            send_json_response(['success' => true, 'message' => 'Transaction already processed.']);
        }

        // Update Investment
        $stmt_upd = $pdo->prepare("
            UPDATE investments 
            SET status = 'in_escrow', 
                token_quantity = :qty, 
                completed_at = NOW() 
            WHERE id = :id
        ");
        $stmt_upd->execute(['id' => $investment_id, 'qty' => $token_quantity]);

        // Create Payment
        // SECURITY: We mark as 'pending' and let the Cron Job verify on-chain.
        $stmt_payment = $pdo->prepare("
            INSERT INTO payments (
                investment_id, amount, currency, method, status, transaction_hash, created_at
            ) VALUES (
                :inv_id, :amt, 'USD', 'stablecoin', 'pending', :tx, NOW()
            )
        ");
        $stmt_payment->execute([
            'inv_id' => $investment_id,
            'amt' => $safe_amount_usd, // We use the SAFE amount from the DB agreement
            'tx' => $tx_hash
        ]);

        $pdo->commit();
        send_json_response(['success' => true, 'message' => 'Transaction recorded. Awaiting confirmation.']);
    }

    // =================================================================================
    // ACTION: INITIATE INVESTMENT (Sign Agreement)
    // =================================================================================
    
    // Validate Inputs
    if (empty($_POST['disclaimer_accepted']) || $_POST['disclaimer_accepted'] !== 'on') {
         send_json_error('DISCLAIMER_REQUIRED', 'Please accept the Smart Wallet Notice.');
    }
    // We check either 'terms' (from checkbox) or explicit snapshot presence
    if (empty($_POST['terms']) && empty($_POST['signed_agreement_snapshot'])) {
        send_json_error('TERMS_NOT_AGREED', 'Please sign the Token Sale Agreement.');
    }

    $agreement_version_id = filter_input(INPUT_POST, 'agreement_version_id', FILTER_VALIDATE_INT);
    $amount_usd = filter_input(INPUT_POST, 'amount_usd', FILTER_VALIDATE_FLOAT);
    
    if (!$amount_usd || $amount_usd <= 0) send_json_error('AMOUNT_INVALID', 'Invalid amount.');
    
    // Check Sale Status
    $stmt_sale = $pdo->prepare("SELECT * FROM token_sale_pages WHERE project_id = :project_id AND sale_name = :sale_name AND status = 'live'");
    $stmt_sale->execute(['project_id' => $project_id, 'sale_name' => $sale_name]);
    $sale = $stmt_sale->fetch(PDO::FETCH_ASSOC);

    if (!$sale) send_json_error('SALE_NOT_LIVE', 'Sale not active.');

    // Prepare Snapshot & Signature Audit Stamp
    $raw_snapshot = $_POST['signed_agreement_snapshot'] ?? '';
    
    $stmt_user = $pdo->prepare("SELECT first_name, last_name, email FROM user WHERE id = :user_id");
    $stmt_user->execute(['user_id' => $user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_full_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
    if (empty($user_full_name)) $user_full_name = "Authorized Investor";
    $user_email = $user_data['email'] ?? 'N/A';

    $hash = !empty($_POST['digital_agreement_hash']) ? $_POST['digital_agreement_hash'] : hash('sha256', $raw_snapshot . $user_id . time());
    $signed_date = date('F j, Y \a\t H:i:s T');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $audit_stamp_html = '
    <div class="legal-audit-stamp" style="margin-top: 30px; padding: 20px; border: 2px solid #6d28d9; background-color: #f9f5ff; border-radius: 12px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd6fe; padding-bottom: 10px; margin-bottom: 12px;">
            <strong style="color: #5b21b6; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em;">✓ ELECTRONICALLY SIGNED & VERIFIED CONTRACT</strong>
            <span style="font-size: 11px; font-family: monospace; background-color: #ede9fe; color: #5b21b6; padding: 3px 8px; border-radius: 4px; font-weight: bold;">Doc ID: SIGN-'.strtoupper(substr($hash, 0, 8)).'</span>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 12px; color: #374151;">
            <div>
                <p style="margin: 4px 0;"><strong>Signed By (Investor):</strong> '.htmlspecialchars($user_full_name).' ('.htmlspecialchars($user_email).')</p>
                <p style="margin: 4px 0;"><strong>Date & Time of Signature:</strong> '.htmlspecialchars($signed_date).'</p>
                <p style="margin: 4px 0;"><strong>Signer Account ID:</strong> User #'.(int)$user_id.'</p>
            </div>
            <div>
                <p style="margin: 4px 0;"><strong>IP Address:</strong> '.htmlspecialchars($ip).' (Verified Session)</p>
                <p style="margin: 4px 0;"><strong>SHA-256 Signature Hash:</strong> <span style="font-family: monospace; font-size: 11px;">'.substr($hash, 0, 24).'...</span></p>
                <p style="margin: 4px 0;"><strong>Signature Status:</strong> <span style="color: #15803d; font-weight: bold;">EXECUTED & BINDING</span></p>
            </div>
        </div>
    </div>';

    if (empty($raw_snapshot)) {
        $raw_snapshot = '<div class="p-6 bg-white border rounded-lg"><h3 class="text-lg font-bold mb-2">Token Purchase Agreement</h3><p class="text-sm text-gray-600 mb-4">Commercial token purchase agreement between project issuer and investor.</p></div>';
    }

    if (!str_contains($raw_snapshot, 'ELECTRONICALLY SIGNED & VERIFIED CONTRACT')) {
        $raw_snapshot .= $audit_stamp_html;
    }

    // DB Operations
    $pdo->beginTransaction();

    $stmt_find = $pdo->prepare("SELECT id FROM investments WHERE user_id = :u AND project_id = :p AND sale_name = :s AND status = 'initiated'");
    $stmt_find->execute(['u' => $user_id, 'p' => $project_id, 's' => $sale_name]);
    $existing = $stmt_find->fetch(PDO::FETCH_ASSOC);

    // 1. EXTRACT VESTING TERMS FROM SALE DATA
    $terms = json_decode($sale['sale_terms_json'] ?? '{}', true);
    $token_price = (float)($terms['round_price'] ?? 0);
    $token_quantity = ($token_price > 0) ? ($amount_usd / $token_price) : 0;
    
    // Vesting Variables
    $cliff = isset($terms['cliff_months']) ? (int)$terms['cliff_months'] : 0;
    $vesting = isset($terms['vesting_months']) ? (int)$terms['vesting_months'] : 0;
    $tge_unlock = isset($terms['percent_unlock_at_tge']) ? (float)$terms['percent_unlock_at_tge'] : 0.0;

    if ($existing) {
        // 2. UPDATE QUERY: Re-added cliff/vesting/tge columns
        $stmt_update = $pdo->prepare("
            UPDATE investments SET 
                amount_usd = :amt, 
                token_quantity = :qty,
                cliff_months = :cliff,
                vesting_months = :vesting,
                percent_unlock_at_tge = :tge,
                agreement_approved = 1,
                agreement_approved_at = NOW(),
                agreement_version_id = :avid,
                signed_agreement_snapshot = :snap
            WHERE id = :id
        ");
        $stmt_update->execute([
            'amt' => $amount_usd, 
            'qty' => $token_quantity, 
            'cliff' => $cliff,
            'vesting' => $vesting,
            'tge' => $tge_unlock,
            'avid' => $agreement_version_id, 
            'snap' => $raw_snapshot, 
            'id' => $existing['id']
        ]);
    } else {
        // 3. INSERT QUERY: Re-added cliff/vesting/tge columns
        $stmt_insert = $pdo->prepare("
            INSERT INTO investments (
                user_id, project_id, sale_name, investment_round, amount_usd, status, 
                token_price_at_purchase, token_quantity, 
                cliff_months, vesting_months, percent_unlock_at_tge,
                agreement_approved, agreement_approved_at,
                agreement_version_id, signed_agreement_snapshot, created_at
            ) VALUES (
                :uid, :pid, :sname, :round, :amt, 'initiated', 
                :price, :qty, 
                :cliff, :vesting, :tge,
                1, NOW(),
                :avid, :snap, NOW()
            )
        ");
        $stmt_insert->execute([
            'uid' => $user_id, 
            'pid' => $project_id, 
            'sname' => $sale_name, 
            'round' => $terms['round_name'] ?? 'Round', 
            'amt' => $amount_usd,
            'price' => $token_price, 
            'qty' => $token_quantity,
            'cliff' => $cliff,
            'vesting' => $vesting,
            'tge' => $tge_unlock,
            'avid' => $agreement_version_id, 
            'snap' => $raw_snapshot
        ]);
    }

    $pdo->commit();
    send_json_response(['success' => true, 'next_step' => 'payment']);

} catch (Throwable $e) {
    // 5. CATCH ALL ERRORS (Even Fatal Ones)
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Backend Critical Error: " . $e->getMessage());
    send_json_error('SERVER_ERROR', 'Server Error: ' . $e->getMessage(), 500);
}