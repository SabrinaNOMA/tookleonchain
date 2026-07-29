<?php
/**
 * Backend Handler for Investor Legal & Compliance Portal
 */
if (!isset($_SESSION['user_id'])) {
    return;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // 1. Fetch User KYC and Profile info
    $stmt_user = $pdo->prepare("SELECT first_name, last_name, email, kyc_status FROM user WHERE id = :user_id");
    $stmt_user->execute([':user_id' => $user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch all investments for this user with signed agreement snapshots and project details
    $stmt_inv = $pdo->prepare("
        SELECT 
            i.id AS investment_id,
            i.project_id,
            i.amount_usd,
            i.token_quantity,
            i.status AS investment_status,
            i.created_at AS contribution_date,
            i.investment_round,
            i.sale_name,
            i.signed_agreement_snapshot,
            i.agreement_version_id,
            p.project_name,
            p.project_website,
            av.file_url AS token_sale_agreement_url,
            av.content AS default_agreement_content
        FROM investments i
        JOIN projet p ON i.project_id = p.id
        LEFT JOIN agreement_versions av ON i.project_id = av.projet_id AND av.is_active = 1
        WHERE i.user_id = :user_id
        ORDER BY i.created_at DESC
    ");
    $stmt_inv->execute([':user_id' => $user_id]);
    $investments = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    // Build signed agreements list (deduplicated by project_id + sale_name or list all)
    $tsa_list = [];
    $commercial_records = [];

    foreach ($investments as $inv) {
        // Prepare commercial record for accounting statement
        $commercial_records[] = [
            'id' => $inv['investment_id'],
            'project_name' => $inv['project_name'],
            'round' => $inv['investment_round'] ?: $inv['sale_name'],
            'date' => date('M d, Y', strtotime($inv['contribution_date'])),
            'amount_usd' => $inv['amount_usd'],
            'token_quantity' => $inv['token_quantity'],
            'status' => $inv['investment_status'],
            'asset_class' => 'Utility Token (MiCA Art. 3 / FINMA Utility)'
        ];

        // Prepare signed TSA link
        $tsa_url = $inv['token_sale_agreement_url'] ?? '#';
        $snapshot_html = !empty($inv['signed_agreement_snapshot']) 
            ? $inv['signed_agreement_snapshot'] 
            : ($inv['default_agreement_content'] ?? '');
        $has_snapshot = !empty($snapshot_html);

        $tsa_list[] = [
            'id' => $inv['investment_id'],
            'project_name' => $inv['project_name'],
            'round' => $inv['investment_round'] ?: $inv['sale_name'],
            'date' => date('M d, Y', strtotime($inv['contribution_date'])),
            'tsa_url' => $tsa_url,
            'has_snapshot' => $has_snapshot,
            'snapshot_html' => $snapshot_html
        ];
    }

    $page_data = [
        'user_info' => $user_info,
        'tsa_list' => $tsa_list,
        'commercial_records' => $commercial_records
    ];

} catch (Exception $e) {
    error_log("Legal Portal Backend Error: " . $e->getMessage());
    $page_data = [
        'error' => 'Unable to load compliance records: ' . $e->getMessage(),
        'tsa_list' => [],
        'commercial_records' => []
    ];
}
