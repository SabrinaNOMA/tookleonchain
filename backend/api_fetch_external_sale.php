<?php
// api_fetch_external_sale.php - Dedicated endpoint to fetch single sale data for editing
// Optimized for reliability when editing external sales.

// 1. Output Buffering & Error Handling
// Start buffering immediately to prevent HTML injection into JSON
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Disable error display
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Correct file paths - ensure all dependencies are loaded
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/session.php'; 
require_once __DIR__ . '/../src/auth.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // Verify DB connection
    if (!isset($pdo)) {
        throw new Exception("Database connection failed.");
    }

    $user = auth_check_user($pdo); // Ensure user is logged in
    $user_id = $user['id'];

    $sale_id = $_GET['sale_id'] ?? null;
    $project_id = $_GET['project_id'] ?? null;

    if (empty($sale_id) || empty($project_id)) {
        throw new Exception("Missing sale_id or project_id.");
    }

    // 2. Security: Verify Ownership
    // Check if the sale belongs to a project owned by the current user
    $stmtVerify = $pdo->prepare("
        SELECT count(*) 
        FROM token_sale_pages tsp
        JOIN projet p ON tsp.project_id = p.id
        WHERE tsp.id = ? AND tsp.project_id = ? AND p.founder_id = ?
    ");
    $stmtVerify->execute([$sale_id, $project_id, $user_id]);
    
    if ($stmtVerify->fetchColumn() == 0) {
        http_response_code(403);
        throw new Exception("Unauthorized access to this sale.");
    }

    // 3. Fetch Sale Data
    // We explicitly select fields to map them correctly in the frontend
    $stmtFetch = $pdo->prepare("
        SELECT 
            tsp.*,
            (tsp.duration_seconds / 86400) as duration_days_calc,
            cs.kyc_required, 
            cs.exclude_sanctioned, 
            cs.exclude_us_non_accredited, 
            cs.require_eu_consent, 
            cs.custom_country_disclaimer, 
            cs.legal_opinion_url, 
            cs.terms_of_service_url
        FROM token_sale_pages tsp
        LEFT JOIN compliance_settings cs ON tsp.project_id = cs.projet_id
        WHERE tsp.id = ?
    ");
    $stmtFetch->execute([$sale_id]);
    $saleData = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if (!$saleData) {
        throw new Exception("Sale not found.");
    }

    // 4. Data Normalization for Frontend
    // Map database columns to the keys expected by 'applyPrefill' in newsale.js
    
    $data = [
        'sale_id' => $saleData['id'],
        'project_id' => $saleData['project_id'],
        'sale_name' => $saleData['sale_name'],
        'hosting' => $saleData['hosting'],
        'status' => $saleData['status'],
        
        // Financials
        'min_raise' => $saleData['soft_cap_usd'],
        'max_raise' => $saleData['hard_cap_usd'],
        'min_purchase_limit' => $saleData['min_investment_usd'],
        'max_purchase_limit' => $saleData['max_investment_usd'],
        
        // Dates & Duration
        'sale_launch_date' => $saleData['sale_launch_at'],
        'sale_end_date' => $saleData['sale_end_at'],
        'duration_days' => $saleData['duration_days_calc'] ? round((float)$saleData['duration_days_calc'], 2) : null,
        'duration_custom' => $saleData['duration_seconds'], // Fallback
        
        // External Specifics
        'sale_url' => $saleData['sale_url'],
        'external_platform_name' => $saleData['sale_url'], // Map URL to platform name field for external
        'external_status' => $saleData['status'], // Map status
        
        // Content
        'projectDescription' => $saleData['project_description_story'],
        'country' => $saleData['country'],
        'videoFilePath' => $saleData['video_file_path'],
        'whitepaperFilePath' => $saleData['whitepaper_file_path'],
        'heroImageDisplayPath' => !empty($saleData['general_images_json']) ? json_decode($saleData['general_images_json'], true)[0] ?? null : null,
        
        // JSON Fields (Decode them safely)
        'team' => !empty($saleData['team_json']) ? json_decode($saleData['team_json'], true) : [],
        'partners' => !empty($saleData['partners_json']) ? json_decode($saleData['partners_json'], true) : [],
        'socials' => !empty($saleData['socials_json']) ? json_decode($saleData['socials_json'], true) : [],
        'valueProps' => !empty($saleData['value_props_json']) ? json_decode($saleData['value_props_json'], true) : [],
        'faqs' => !empty($saleData['faqs_json']) ? json_decode($saleData['faqs_json'], true) : [],
        'communityMetrics' => !empty($saleData['community_metrics_json']) ? json_decode($saleData['community_metrics_json'], true) : [],
        
        // Compliance
        'kyc_required' => (bool)$saleData['kyc_required'],
        'exclude_sanctioned' => (bool)$saleData['exclude_sanctioned'],
        'exclude_us_non_accredited' => (bool)$saleData['exclude_us_non_accredited'],
        'require_eu_consent' => (bool)$saleData['require_eu_consent'],
        'custom_country_disclaimer' => $saleData['custom_country_disclaimer'],
        'legal_opinion_url' => $saleData['legal_opinion_url'],
        'terms_of_service_url' => $saleData['terms_of_service_url'],
        
        // Agreement
        'token_sale_agreement_url' => null 
    ];

    // Try to get agreement URL if possible
    $stmtAgree = $pdo->prepare("SELECT file_url FROM agreement_versions WHERE projet_id = ? AND is_active = 1 ORDER BY version DESC LIMIT 1");
    $stmtAgree->execute([$project_id]);
    $agreeUrl = $stmtAgree->fetchColumn();
    if ($agreeUrl) {
        $data['token_sale_agreement_url'] = $agreeUrl;
    }

    $response = [
        'success' => true,
        'data' => $data
    ];

} catch (Throwable $e) {
    http_response_code(500); // Internal Server Error
    $response = [
        'success' => false, 
        'message' => 'Server Error: ' . $e->getMessage()
    ];
}

// 5. Clean Output & Send JSON
// Discard any previous output (HTML warnings/errors)
ob_end_clean(); 

echo json_encode($response);
exit;
?>