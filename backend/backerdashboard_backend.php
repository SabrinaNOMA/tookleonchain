<?php
/**
 * Backend logic for the Investment Dashboard (backerdashboard.php).
 *
 * ARCHITECTURE UPDATE:
 * - Fetches Project Token Contract (Global)
 * - Fetches Sale Vault Contract (Per Investment/TSP)
 * - Fetches 3-way Transaction Audit Trail (Payment, Distribution, Refund)
 * - [ENABLED] Auto-syncs Refunds via Alchemy (Using Cron Utility)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../src/db.php';

// --- NEW: Handle Quick Link Wallet Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_wallet') {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? null;
    $inv_id = $_POST['investment_id'] ?? null;
    $wallet = $_POST['wallet_address'] ?? null;
    
    if (!$user_id || !$inv_id || !$wallet) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized or missing data.']);
        exit;
    }
    
    try {
        // Update the specific investment with the provided wallet address
        $stmt = $pdo->prepare("UPDATE investments SET investor_wallet_address = :wallet WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':wallet' => $wallet, ':id' => $inv_id, ':user_id' => $user_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/**
 * Determines the user-facing status based on the definitive logic matrix.
 */
function getInvestorStatus($investmentStatus, $paymentStatus, $saleStatus, $hasPaymentTx = false) {
    $inv = strtolower($investmentStatus ?? '');
    $pay = strtolower($paymentStatus ?? '');
    $sale = strtolower($saleStatus ?? '');
    $hasTx = (bool)$hasPaymentTx;

    // 1. Unpaid Draft Attempt (Agreement signed, no payment tx submitted)
    if ($inv === 'initiated' && !$hasTx && $pay !== 'successful') {
        return ['status' => 'Unpaid (Draft)', 'description' => 'Agreement signed. Blockchain payment has not been submitted yet.'];
    }

    // 2. Pending Blockchain Confirmation
    $is_pending_inv = in_array($inv, ['in_escrow', 'initiated', 'pending']);
    $is_live_sale = in_array($sale, ['live', 'scheduled', 'active', 'open']);
    $is_incomplete_payment = ($pay !== 'successful' && $pay !== 'failed');

    if ($is_pending_inv && ($pay === 'pending' || ($hasTx && $is_incomplete_payment)) && $is_live_sale) {
        return ['status' => 'Processing', 'description' => "Your payment is being confirmed on-chain."];
    }

    if (($inv === 'cancelled' || $inv === 'canceled') && $pay === 'failed') {
        return ['status' => 'Failed', 'description' => 'Your payment could not be processed.'];
    }
    
    // Active / Paid
    if (in_array($inv, ['in_escrow', 'released_to_creator']) && ($pay === 'successful' || $hasTx) && $is_live_sale) {
        return ['status' => 'Active', 'description' => 'Payment received - campaign active.'];
    }
    
    // Success Flow
    if ($inv === 'in_escrow' && ($pay === 'successful' || $hasTx) && $sale === 'ended_successful') {
        return ['status' => 'Processing', 'description' => 'Sale successful. Preparing allocation.'];
    }
    if ($inv === 'released_to_creator' && ($pay === 'successful' || $hasTx) && $sale === 'ended_successful') {
        return ['status' => 'Fulfilled', 'description' => 'Project funded. Tokens distributed.'];
    }
    
    // Refund Flow
    if (in_array($inv, ['refund_pending', 'in_escrow']) && in_array($sale, ['ended_failed', 'canceled'])) {
        return ['status' => 'Refunding', 'description' => 'Sale failed. Refund available.'];
    }
    if ($inv === 'returned_to_backer' && ($pay === 'refunded' || $pay === 'successful')) {
        return ['status' => 'Refunded', 'description' => 'Contribution refunded to wallet.'];
    }
    
    if ($inv === 'cancelled') {
        return ['status' => 'Canceled', 'description' => 'Contribution canceled.'];
    }

    return ['status' => 'Under Review', 'description' => "Status check required."];
}


// --- 1. User & Project Authentication ---
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}
$user_id = $_SESSION['user_id'];
$project_id = $_SESSION['selected_project_id'] ?? null;

// --- Initialize Data Array ---
$page_data = [
    'projectName' => 'Unknown Project',
    'successful_investments' => [], 
    'other_investments' => [],      
    'db_error' => null,
    'project_id' => $project_id,
    'kyc_status' => 'pending',
    'wallet_configured' => false,
    'sale_status' => null,
    'project_links' => ['website' => '#', 'escrow' => '#'],
    'fee_recipient_address' => null,
    'deployed_token' => ['contract' => null],
];

if (empty($project_id)) {
    $page_data['db_error'] = "No project selected.";
    return;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query 1: Fetch Project Details
    $stmt_project = $pdo->prepare("SELECT project_name, project_website FROM projet WHERE id = :project_id");
    $stmt_project->execute([':project_id' => $project_id]);
    $project_details = $stmt_project->fetch(PDO::FETCH_ASSOC);

    if ($project_details) {
        $page_data['projectName'] = htmlspecialchars($project_details['project_name']);
        $page_data['project_links']['website'] = $project_details['project_website'] ?? '#';
    } else {
        $page_data['db_error'] = "Project not found.";
        return;
    }

    // --- ALCHEMY SYNC INTEGRATION (ENABLED) ---
    // This cron runs on page load to catch any refunds that might have been
    // processed on-chain but missed by the database (e.g. user disconnection).
    if (file_exists(__DIR__ . '/../cron/sync_refunds.php')) {
        require_once __DIR__ . '/../cron/sync_refunds.php';
        
        $stmt_sync = $pdo->prepare("
            SELECT contract_address 
            FROM token_sale_pages 
            WHERE project_id = :pid 
            AND status IN ('ended_failed', 'canceled') 
            AND contract_address IS NOT NULL
        ");
        $stmt_sync->execute([':pid' => $project_id]);
        
        while ($row = $stmt_sync->fetch(PDO::FETCH_ASSOC)) {
            // Function imported from sync_refunds.php
            if (function_exists('syncProjectRefunds')) {
                syncProjectRefunds($project_id, $row['contract_address']);
            }
        }
    }
    // --- ALCHEMY SYNC INTEGRATION END ---

    // --- MAIN QUERY: Investment + Audit Trail + Vault Address ---
    $stmt_investments = $pdo->prepare("
        SELECT
            i.id, i.project_id,
            i.amount_usd as investment_amount,
            i.token_quantity,
            i.status as investment_status,
            i.created_at as investment_date,
            i.investment_round, i.sale_name, 
            i.cliff_months, i.vesting_months, i.percent_unlock_at_tge, 
            u.kyc_status, 
            i.investor_wallet_address,
            
            -- SALE & VAULT INFO (Per TSP)
            tsp.status as sale_status,
            tsp.sale_terms_json,
            tsp.contract_address as sale_contract_address, -- The Smart Vault
            tsp.fee_settled,
            tsp.gnosis_safe_address,
            
            -- AUDIT TRAIL (Hashes)
            (SELECT p.status FROM payments p WHERE p.investment_id = i.id ORDER BY p.created_at DESC LIMIT 1) as payment_status,
            COALESCE((SELECT p.transaction_hash FROM payments p WHERE p.investment_id = i.id ORDER BY p.created_at DESC LIMIT 1), i.payment_tx_hash) as payment_tx_hash,
            i.distribution_tx_hash,
            i.refund_tx_hash,
            
            -- Distribution
            i.distributed_at, i.distribution_status, i.distribution_stream_id,
            uw.label as wallet_label
        FROM investments i
        JOIN user u ON i.user_id = u.id 
        LEFT JOIN token_sale_pages tsp ON i.project_id = tsp.project_id AND i.sale_name = tsp.sale_name
        LEFT JOIN user_wallet uw ON i.investor_wallet_address = uw.wallet_address AND uw.user_id = i.user_id
        WHERE i.user_id = :user_id AND i.project_id = :project_id
        ORDER BY i.created_at DESC
    ");
    
    $stmt_investments->execute([':user_id' => $user_id, ':project_id' => $project_id]);
    $investments_raw = $stmt_investments->fetchAll(PDO::FETCH_ASSOC);

    $successful_investments = [];
    $other_investments = [];

    // --- Process investments ---
    foreach ($investments_raw as $investment) {
        $hasTx = !empty($investment['payment_tx_hash']);
        $statusInfo = getInvestorStatus($investment['investment_status'], $investment['payment_status'], $investment['sale_status'], $hasTx);
        $investment['investorStatus'] = $statusInfo['status'];
        $investment['investorDescription'] = $statusInfo['description'];

        if (strtolower($statusInfo['status']) === 'active') {
            $investment['investorStatusClass'] = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        } elseif (strtolower($statusInfo['status']) === 'unpaid (draft)') {
            $investment['investorStatusClass'] = 'bg-amber-50 text-amber-700 border border-amber-200 font-semibold';
        }

        if (!empty($investment['investor_wallet_address'])) {
            $page_data['wallet_configured'] = true;
        }

        if (strtolower($investment['sale_status']) === 'ended_successful') {
            $successful_investments[] = $investment;
        } else {
            $other_investments[] = $investment;
        }
    }

    $page_data['successful_investments'] = $successful_investments;
    $page_data['other_investments'] = $other_investments;

    // --- KYC Status ---
    if (!empty($investments_raw)) {
        $page_data['kyc_status'] = !empty($investments_raw[0]['kyc_status']) ? $investments_raw[0]['kyc_status'] : 'pending';
    } else {
        $stmt_kyc = $pdo->prepare("SELECT kyc_status FROM user WHERE id = :user_id");
        $stmt_kyc->execute([':user_id' => $user_id]);
        $user_kyc = $stmt_kyc->fetch(PDO::FETCH_ASSOC);
        $page_data['kyc_status'] = $user_kyc['kyc_status'] ?? 'pending';
    }

    // --- Fee Recipient ---
    $stmt_wallet = $pdo->prepare("SELECT claim_fee_address FROM tookle_wallets WHERE status = 'active' AND (network = 'Base' OR network = 'Ethereum') LIMIT 1");
    $stmt_wallet->execute();
    $wallet_details = $stmt_wallet->fetch(PDO::FETCH_ASSOC);
    if ($wallet_details) $page_data['fee_recipient_address'] = $wallet_details['claim_fee_address'];

    // --- Deployed Token Contract (Project Global) ---
    $stmt_contract = $pdo->prepare("SELECT contract, network FROM deployed_token WHERE projet_id = :project_id AND contract IS NOT NULL AND contract != '' ORDER BY (selected_contract = 'yes') DESC, id DESC LIMIT 1");
    $stmt_contract->execute([':project_id' => $project_id]);
    $contract_details = $stmt_contract->fetch(PDO::FETCH_ASSOC);
    if ($contract_details && !empty($contract_details['contract'])) {
        $page_data['deployed_token']['contract'] = htmlspecialchars($contract_details['contract']);
        $page_data['deployed_token']['network'] = htmlspecialchars($contract_details['network'] ?? 'Base');
    }

} catch (Exception $e) {
    error_log("Backer Dashboard DB Error: " . $e->getMessage());
    $page_data['db_error'] = "A database error occurred.";
}
?>