<?php
/**
 * backend/portfolio_backend.php
 *
 * This script fetches all data required for the user's portfolio page.
 * Updated with ROBUST status logic to handle un-fetched/transient payment states.
 */

if (!isset($pdo) || !isset($user_id)) {
    http_response_code(500);
    $page_data = ['error' => 'Backend script included without proper context.'];
    return;
}

// Function to process media fields from various JSON/db columns
function process_media_fields(&$project_row) {
    $project_row['media_url'] = null;
    $project_row['media_type'] = null;
    if (!empty($project_row['general_images_json'])) {
        $images = json_decode($project_row['general_images_json'], true);
        if (is_array($images) && !empty($images[0])) {
            $project_row['media_url'] = $images[0];
            $project_row['media_type'] = 'image';
        }
    }
    if (empty($project_row['media_url']) && !empty($project_row['video_file_path'])) {
        $project_row['media_url'] = $project_row['video_file_path'];
        $project_row['media_type'] = 'video';
    }
    if ($project_row['media_url']) {
        $project_row['media_url'] = str_replace('\\', '/', $project_row['media_url']);
    }
}

/**
 * Implements the Definitive Status Logic Matrix.
 * Derives a user-facing status and description from the three core table statuses.
 */
function getInvestorStatus($investmentStatus, $paymentStatus, $saleStatus) {
    // 1. SANITIZE INPUTS (Trim + Lowercase to avoid mismatch)
    $invStatus = trim(strtolower((string)$investmentStatus));
    $payStatus = trim(strtolower((string)$paymentStatus));
    $saleStatus = trim(strtolower((string)$saleStatus));

    // --- PRIORITY OVERRIDES ---

    // 0. ROBUST FIX: Catch-all for Pending States
    // Matches: Investment (In Escrow OR Initiated) + Payment (Not Final) + Sale (Live OR Active)
    // This explicitly handles cases where the cron hasn't fetched the payment yet (payStatus is null/empty).
    $is_pending_inv = in_array($invStatus, ['in_escrow', 'initiated', 'pending']);
    $is_live_sale = in_array($saleStatus, ['live', 'scheduled', 'active', 'open']);
    $is_incomplete_payment = ($payStatus !== 'successful' && $payStatus !== 'failed');

    if ($is_pending_inv && $is_incomplete_payment && $is_live_sale) {
        return ['status' => 'Pending', 'description' => 'Your payment is being processed. We\'ll notify you upon confirmation.'];
    }
    
    // 1. FAILED: Payment explicitly failed or Investment Canceled
    if (($invStatus === 'canceled' || $payStatus === 'failed') && $is_live_sale) {
        return ['status' => 'Failed', 'description' => 'Your payment could not be processed. Please try again.'];
    }

    // 2. ACTIVE: Funds secured AND Payment Successful
    if ($invStatus === 'in_escrow' && $payStatus === 'successful' && $is_live_sale) {
        return ['status' => 'Active', 'description' => 'Your payment was successful. Your funds are held securely until the sale ends.'];
    }

    // 3. PROCESSING: Sale ended successfully
    if ($invStatus === 'in_escrow' && ($payStatus === 'successful' || $payStatus === '') && $saleStatus === 'ended_successful') {
        return ['status' => 'Processing', 'description' => 'The sale was a success! Funds will be distributed to the founder and your allocation is being prepared.'];
    }

    // 4. FULFILLED
    if ($invStatus === 'released_to_creator' && ($payStatus === 'successful' || $payStatus === '') && $saleStatus === 'ended_successful') {
        return ['status' => 'Fulfilled', 'description' => 'Success! The project was funded. Check your dashboard to follow your token distribution.'];
    }
    
    // 5. REFUNDING
    if (in_array($invStatus, ['refund_pending', 'in_escrow']) && in_array($saleStatus, ['ended_failed', 'canceled'])) {
        return ['status' => 'Refunding', 'description' => 'The sale did not meet its goal. Your contribution is being prepared for refund.'];
    }

    // 6. REFUNDED
    if (($invStatus === 'returned_to_backer') && in_array($saleStatus, ['ended_failed', 'canceled'])) {
        return ['status' => 'Refunded', 'description' => 'Your contribution has been fully refunded to your original payment method.'];
    }
    
    // 7. CANCELED (Legacy catch)
    if ($invStatus === 'canceled' && in_array($saleStatus, ['ended_failed', 'canceled'])) {
        return ['status' => 'Canceled', 'description' => 'This contribution was canceled because the sale ended before your payment could be confirmed.'];
    }

    // --- Default Fallback ---
    return [
        'status' => 'Under Review', 
        'description' => 'There is an issue with this contribution\'s status. Our team has been notified and is investigating.'
    ];
}


try {
    // --- Step 1: Get all investments for the current user ---
    $investments_sql = "
        SELECT
            inv.id as investmentId,
            inv.project_id as projectId,
            inv.amount_usd as yourContribution,
            inv.status as investmentStatus,
            inv.investment_round as round,
            inv.sale_name
        FROM investments inv
        WHERE inv.user_id = ?
    ";
    $stmt_inv = $pdo->prepare($investments_sql);
    $stmt_inv->execute([$user_id]);
    $all_investments = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all_investments)) {
        $page_data = ['portfolioCards' => [], 'analytics' => ['projectsBacked' => 0]];
        return;
    }

    // --- Step 2: Get corresponding payment statuses ---
    $investment_ids = array_column($all_investments, 'investmentId');
    $payment_statuses = [];
    if (!empty($investment_ids)) {
        $placeholders = implode(',', array_fill(0, count($investment_ids), '?'));
        // Get the latest payment status for each investment
        $payment_sql = "
            SELECT p1.investment_id, p1.status, p1.transaction_hash
            FROM payments p1
            LEFT JOIN payments p2 ON p1.investment_id = p2.investment_id AND p1.created_at < p2.created_at
            WHERE p2.id IS NULL AND p1.investment_id IN ($placeholders)
        ";
        $stmt_pay = $pdo->prepare($payment_sql);
        $stmt_pay->execute($investment_ids);
        $payment_results = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);
        foreach ($payment_results as $payment) {
            $payment_statuses[$payment['investment_id']] = [
                'status' => $payment['status'],
                'hash' => $payment['transaction_hash']
            ];
        }
    }

    // --- Step 3: Get unique project IDs and fetch project details ---
    $project_ids = array_unique(array_column($all_investments, 'projectId'));
    $project_ids_filtered = array_filter($project_ids);

    $sale_details_lookup = [];
    if (!empty($project_ids_filtered)) {
        $placeholders = implode(',', array_fill(0, count($project_ids_filtered), '?'));
        
        // UPDATED QUERY: Calculates totalRaised and backers on a per-sale basis.
        $projects_sql = "
            SELECT 
                p.id, p.project_name, p.industry_focus, p.selected_category, tsp.country,
                tsp.sale_name, tsp.sale_url, tsp.status, tsp.soft_cap_usd, tsp.hard_cap_usd,
                tsp.sale_end_at as sale_end_date, tsp.video_file_path, tsp.general_images_json,
                (SELECT COALESCE(SUM(pay.amount), 0)
                 FROM payments pay
                 JOIN investments inv ON pay.investment_id = inv.id
                 WHERE inv.project_id = p.id AND inv.sale_name = tsp.sale_name AND pay.status = 'successful') as totalRaised,
                (SELECT COUNT(DISTINCT inv.user_id)
                 FROM investments inv
                 JOIN payments pay ON inv.id = pay.investment_id
                 WHERE inv.project_id = p.id AND inv.sale_name = tsp.sale_name AND pay.status = 'successful') as backers
            FROM projet p
            LEFT JOIN token_sale_pages tsp ON p.id = tsp.project_id
            WHERE p.id IN ($placeholders)
        ";
        $stmt_proj = $pdo->prepare($projects_sql);
        $stmt_proj->execute(array_values($project_ids_filtered));
        $projects_results = $stmt_proj->fetchAll(PDO::FETCH_ASSOC);

        foreach ($projects_results as $result) {
            $key = $result['id'] . '-' . $result['sale_name'];
            $sale_details_lookup[$key] = $result;
        }
    }

    $portfolio_cards = [];
    $analytics_data = [
        'successfulContributions' => 0, 'inEscrowContributions' => 0, 'pendingContributions' => 0,
        'failedOrRefundedContributions' => 0, 'industryFocus' => [], 'tokenomics' => [],
        'regionAllocation' => [], 'projectIds' => []
    ];

    foreach ($all_investments as $investment) {
        $projectId = $investment['projectId'];
        $saleName = $investment['sale_name'];
        $lookupKey = $projectId . '-' . $saleName;

        if (!isset($sale_details_lookup[$lookupKey])) continue; 

        $project = $sale_details_lookup[$lookupKey];
        process_media_fields($project);
        
        // --- DERIVE STATUS using the new logic ---
        $investmentId = $investment['investmentId'];
        $investmentStatus = $investment['investmentStatus'];
        $paymentData = $payment_statuses[$investmentId] ?? null; 
        $paymentStatus = $paymentData['status'] ?? null;
        $saleStatus = $project['status'];
        $derivedStatus = getInvestorStatus($investmentStatus, $paymentStatus, $saleStatus);
        
        $daysRemaining = null;
        if ($project['status'] === 'live' && !empty($project['sale_end_date'])) {
            $endDate = new DateTime($project['sale_end_date']);
            $now = new DateTime();
            $daysRemaining = ($endDate > $now) ? $now->diff($endDate)->days : 0;
        }

        $portfolio_cards[] = [
            'projectId' => $projectId,
            'projectName' => $project['project_name'],
            'industryFocus' => $project['industry_focus'],
            'country' => $project['country'],
            'saleName' => $investment['sale_name'], 
            'saleUrl' => $project['sale_url'],
            'saleStatus' => $project['status'],
            'softCap' => $project['soft_cap_usd'],
            'hardCap' => $project['hard_cap_usd'],
            'raised' => (float)($project['totalRaised'] ?? 0),
            'backers' => (int)($project['backers'] ?? 0),
            'daysRemaining' => $daysRemaining,
            'media_url' => $project['media_url'],
            'media_type' => $project['media_type'],
            'yourContribution' => (float)$investment['yourContribution'],
            'investorStatus' => $derivedStatus['status'],
            'investorDescription' => $derivedStatus['description'],
            'round' => $investment['round'],
            'hash' => $paymentData['hash'] ?? null
        ];
        
        // --- Analytics Calculations based on new derived statuses ---
        $contribution_amount = (float)$investment['yourContribution'];
        $statusForAnalytics = strtolower($derivedStatus['status']);

        $include_in_analytics = false;
        switch ($statusForAnalytics) {
            case 'fulfilled':
                $analytics_data['successfulContributions'] += $contribution_amount;
                $include_in_analytics = true;
                break;
            case 'active':
            case 'processing':
                $analytics_data['inEscrowContributions'] += $contribution_amount;
                $include_in_analytics = true;
                break;
            case 'pending':
                $analytics_data['pendingContributions'] += $contribution_amount;
                break;
            case 'refunding':
            case 'refunded':
            case 'failed':
            case 'canceled':
                $analytics_data['failedOrRefundedContributions'] += $contribution_amount;
                break;
        }
        
        if ($include_in_analytics) {
            $analytics_data['projectIds'][$projectId] = true;
            $industry = $project['industry_focus'] ?? 'Other';
            $analytics_data['industryFocus'][$industry] = ($analytics_data['industryFocus'][$industry] ?? 0) + $contribution_amount;
            
            $tokenomic = $project['selected_category'] ?? 'Other';
            $analytics_data['tokenomics'][$tokenomic] = ($analytics_data['tokenomics'][$tokenomic] ?? 0) + $contribution_amount;
            
            $region = $project['country'] ?? 'Unknown';
            $analytics_data['regionAllocation'][$region] = ($analytics_data['regionAllocation'][$region] ?? 0) + $contribution_amount;
        }
    }

    // Sorting: live > successful > closed > others
    usort($portfolio_cards, function ($a, $b) {
        $statusOrder = ['live' => 1, 'successful' => 2, 'ended_successful' => 2, 'closed' => 3];
        $aStatus = strtolower($a['saleStatus'] ?? 'other');
        $bStatus = strtolower($b['saleStatus'] ?? 'other');
        $aOrder = $statusOrder[$aStatus] ?? 4;
        $bOrder = $statusOrder[$bStatus] ?? 4;
        return $aOrder <=> $bOrder;
    });

    $page_data = [
        'portfolioCards' => $portfolio_cards,
        'analytics' => [
            'projectsBacked' => count($analytics_data['projectIds']),
            'successfulContributions' => $analytics_data['successfulContributions'],
            'inEscrowContributions' => $analytics_data['inEscrowContributions'],
            'pendingContributions' => $analytics_data['pendingContributions'],
            'failedOrRefundedContributions' => $analytics_data['failedOrRefundedContributions'],
            'breakdown' => [
                'industryFocus' => $analytics_data['industryFocus'],
                'tokenomics' => $analytics_data['tokenomics']
            ],
            'regionAllocation' => $analytics_data['regionAllocation']
        ]
    ];

} catch (PDOException $e) {
    error_log("Portfolio Backend PDO Error: " . $e->getMessage());
    $page_data = ['error' => 'Database Query Failed. Please check the logs.'];
} catch (Exception $e) {
    error_log("Portfolio Backend General Error: " . $e->getMessage());
    $page_data = ['error' => 'An unexpected error occurred. Please check the logs.'];
}
?>