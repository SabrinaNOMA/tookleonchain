<?php
/**
 * Backend script for the Promotion page.
 * Filepath: /backend/promotion_backend.php
 *
 * This script handles all data fetching and processing for the founder promotion page.
 * It is called by the main router `index.php` before the `promotion.php` page is rendered.
 */
// CORRECTED: Ensure session is started before trying to access $_SESSION variables.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/db.php';

// --- Global Variables for Page Content ---
$kpis = [];
$all_campaigns = [];
$is_active_campaign = false;
$active_campaign = null;
$participants = [];
$page_error = null;
$userInfo = null;

// --- Helper Functions ---
function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// --- Main Logic ---
$method = $_SERVER['REQUEST_METHOD'];
// Check the request body for JSON data first for POST requests
$input = [];
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
}

// Now, get the project_id from the session or from the parsed JSON input.
$project_id = $_SESSION['active_project_id'] ?? ($input['project_id'] ?? null);
$founder_id = $_SESSION['user_id'] ?? null;

// Fetch user info for the layout, regardless of other page logic.
if ($founder_id && isset($pdo)) {
    try {
        $user_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM user WHERE id = ?");
        $user_stmt->execute([$founder_id]);
        $userInfo = $user_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Promotion UserInfo Error: " . $e->getMessage());
    }
}

if (!$project_id) {
    if ($method === 'GET') {
        $page_error = "No active project found. Please select a project from the dashboard.";
    } else {
        send_json_response(['error' => 'Project ID is required.'], 400);
    }
}

global $pdo;
if (!isset($pdo) && !$page_error) {
    if ($method === 'GET') {
        $page_error = "Database connection object not found. Please check configuration.";
    } else {
        send_json_response(['error' => 'Database connection failed.'], 500);
    }
}

if ($method === 'GET' && !$page_error) {
    try {
        assign_campaign_to_new_referrals($pdo, $project_id);
        calculate_initial_commissions_for_participants($pdo, $project_id);

        $kpis = get_kpi_data($pdo, $project_id);
        $all_campaigns = get_campaigns_data($pdo, $project_id);
        $participants = get_participants_data($pdo, $project_id);
        
        $active_campaign_array = array_filter($all_campaigns, function($c) { return $c['is_active']; });
        $active_campaign = reset($active_campaign_array);
        $is_active_campaign = !empty($active_campaign);

    } catch (PDOException $e) {
        error_log("Growth Campaign GET Error: " . $e->getMessage());
        $page_error = 'Failed to fetch campaign data. Please try again later.';
    }
} elseif ($method === 'POST') {
    handle_post_request($pdo, $project_id, $input);
}

function handle_post_request($pdo, $project_id, $input) {
    $action = $input['action'] ?? null;
    if (empty($project_id)) {
        send_json_response(['error' => 'Project ID is required.'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        $message = '';
        switch ($action) {
            case 'create_invite_campaign':
                if (empty($input['campaign_name']) || !isset($input['inviter_reward_percent']) || !isset($input['invitee_bonus_percent'])) {
                    throw new Exception("Missing required campaign data.");
                }
                create_invite_campaign($pdo, $project_id, $input);
                $message = 'Campaign created successfully.';
                assign_campaign_to_new_referrals($pdo, $project_id);
                calculate_initial_commissions_for_participants($pdo, $project_id);
                break;
            case 'update_participants':
                update_participants($pdo, $project_id, $input['updates'] ?? []);
                $message = 'Participant data updated successfully.';
                break;
            case 'deactivate_campaign':
                deactivate_current_campaign($pdo, $project_id);
                $message = 'Campaign deactivated successfully.';
                break;
            default:
                throw new Exception("Invalid action specified.");
        }
        $pdo->commit();
        
        $participants = get_participants_data($pdo, $project_id);
        $kpis = get_kpi_data($pdo, $project_id);
        $campaigns = get_campaigns_data($pdo, $project_id);

        send_json_response(['success' => true, 'message' => $message, 'kpis' => $kpis, 'campaigns' => $campaigns, 'participants' => $participants]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Growth Campaign POST Error: " . $e->getMessage());
        send_json_response(['error' => $e->getMessage()], 500);
    }
}

function get_kpi_data($pdo, $project_id) {
    $sql_commissions = "SELECT commission_status as status, SUM(COALESCE(inviter_commission, 0) + COALESCE(invitee_commission, 0)) as total 
            FROM investments 
            WHERE project_id = ? AND commission_status IN ('pending', 'due', 'paid')
            GROUP BY commission_status";
    $stmt_commissions = $pdo->prepare($sql_commissions);
    $stmt_commissions->execute([$project_id]);
    $commission_results = $stmt_commissions->fetchAll(PDO::FETCH_KEY_PAIR);

    $sql_investments = "SELECT SUM(amount_usd) as total 
                        FROM investments 
                        WHERE project_id = ? AND campaign_id IS NOT NULL AND status IN ('Successful', 'Completed')";
    $stmt_investments = $pdo->prepare($sql_investments);
    $stmt_investments->execute([$project_id]);
    $total_investments = $stmt_investments->fetchColumn();

    return [
        'total_investment_from_referrals' => (float)($total_investments ?? 0),
        'total_pending_commissions' => (float)($commission_results['pending'] ?? 0),
        'total_due_commissions' => (float)($commission_results['due'] ?? 0),
        'total_paid_commissions' => (float)($commission_results['paid'] ?? 0),
    ];
}

function get_campaigns_data($pdo, $project_id) {
    $stmt = $pdo->prepare("SELECT id, campaign_name, is_active, inviter_reward_percent, invitee_bonus_percent FROM invite_settings WHERE projet_id = ? ORDER BY created_at DESC");
    $stmt->execute([$project_id]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($campaigns as &$campaign) {
        $campaign['is_active'] = (bool)$campaign['is_active'];
        $campaign['inviter_reward_percent'] = (float)$campaign['inviter_reward_percent'];
        $campaign['invitee_bonus_percent'] = (float)$campaign['invitee_bonus_percent'];
    }
    return $campaigns;
}

function get_participants_data($pdo, $project_id) {
    $sql = "
        SELECT
            i.id as investment_id,
            i.reference_id as investment_reference,
            (SELECT email FROM user WHERE invite_code = i.referral_code_used COLLATE utf8mb4_unicode_ci LIMIT 1) as inviter_email,
            invitee.email as invitee_email,
            i.amount_usd as investment_amount,
            i.inviter_commission as inviter_commission_earned,
            i.invitee_commission as invitee_bonus_earned,
            i.commission_status as status,
            i.status as payment_status,
            i.created_at as date,
            i.campaign_id as campaign_reference,
            s.campaign_name
        FROM investments i
        LEFT JOIN user invitee ON i.user_id = invitee.id
        LEFT JOIN invite_settings s ON i.campaign_id COLLATE utf8mb4_unicode_ci = s.id
        WHERE i.project_id = ? 
          AND i.referral_code_used IS NOT NULL
          AND EXISTS (SELECT 1 FROM user u WHERE u.invite_code = i.referral_code_used COLLATE utf8mb4_unicode_ci)
        ORDER BY i.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$project_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function create_invite_campaign($pdo, $project_id, $data) {
    $stmt_deactivate = $pdo->prepare("UPDATE invite_settings SET is_active = 0 WHERE projet_id = ?");
    $stmt_deactivate->execute([$project_id]);

    $sql = "INSERT INTO invite_settings (projet_id, campaign_name, inviter_reward_percent, invitee_bonus_percent, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())";
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->execute([$project_id, $data['campaign_name'], $data['inviter_reward_percent'], $data['invitee_bonus_percent']]);
}

function deactivate_current_campaign($pdo, $project_id) {
    $stmt_deactivate = $pdo->prepare("UPDATE invite_settings SET is_active = 0 WHERE projet_id = ? AND is_active = 1");
    $stmt_deactivate->execute([$project_id]);
}

function update_participants($pdo, $project_id, $updates) {
    if (empty($updates)) return;

    $stmt_update_status_only = $pdo->prepare("UPDATE investments SET commission_status = ? WHERE id = ? AND project_id = ?");
    $stmt_update_full = $pdo->prepare("UPDATE investments SET campaign_id = ?, commission_status = ?, inviter_commission = ?, invitee_commission = ? WHERE id = ? AND project_id = ?");
    $stmt_get_investment = $pdo->prepare("SELECT amount_usd, campaign_id FROM investments WHERE id = ? AND project_id = ?");
    $stmt_get_campaign = $pdo->prepare("SELECT inviter_reward_percent, invitee_bonus_percent FROM invite_settings WHERE id = ? AND projet_id = ?");

    foreach ($updates as $update) {
        if (!isset($update['investment_id'])) continue;
        $investment_id = $update['investment_id'];
        
        $stmt_get_investment->execute([$investment_id, $project_id]);
        $investment = $stmt_get_investment->fetch(PDO::FETCH_ASSOC);
        if (!$investment) continue;
        
        $original_campaign_id = $investment['campaign_id'];
        $new_campaign_id = (isset($update['campaign_reference']) && $update['campaign_reference'] !== '') ? $update['campaign_reference'] : $original_campaign_id;
        $new_status = $update['status'] ?? 'pending';
        
        $campaign_has_changed = ($new_campaign_id != $original_campaign_id);

        if ($campaign_has_changed) {
            $new_inviter_commission = 0;
            $new_invitee_commission = 0;
            if ($new_campaign_id) {
                $stmt_get_campaign->execute([$new_campaign_id, $project_id]);
                $campaign = $stmt_get_campaign->fetch(PDO::FETCH_ASSOC);
                if ($campaign) {
                    $investment_amount = (float)$investment['amount_usd'];
                    $new_inviter_commission = round(($investment_amount * (float)$campaign['inviter_reward_percent']) / 100.0, 2);
                    $new_invitee_commission = round(($investment_amount * (float)($campaign['invitee_bonus_percent'] ?? 0)) / 100.0, 2);
                }
            }
            $stmt_update_full->execute([$new_campaign_id, $new_status, $new_inviter_commission, $new_invitee_commission, $investment_id, $project_id]);
        } else {
            $stmt_update_status_only->execute([$new_status, $investment_id, $project_id]);
        }
    }
}

function assign_campaign_to_new_referrals($pdo, $project_id) {
    $stmt_camp = $pdo->prepare("SELECT id FROM invite_settings WHERE projet_id = ? AND is_active = 1 LIMIT 1");
    $stmt_camp->execute([$project_id]);
    $active_campaign_id = $stmt_camp->fetchColumn();

    if ($active_campaign_id) {
        $stmt_update = $pdo->prepare("UPDATE investments SET campaign_id = ? WHERE project_id = ? AND referral_code_used IS NOT NULL AND campaign_id IS NULL");
        $stmt_update->execute([$active_campaign_id, $project_id]);
    }
}

function calculate_initial_commissions_for_participants($pdo, $project_id) {
    $stmt = $pdo->prepare(
        "SELECT i.id, i.amount_usd, s.inviter_reward_percent, s.invitee_bonus_percent
         FROM investments i
         JOIN invite_settings s ON i.campaign_id COLLATE utf8mb4_unicode_ci = s.id
         WHERE i.project_id = ? 
           AND i.status IN ('Successful', 'Completed') 
           AND i.amount_usd > 0 
           AND (i.inviter_commission IS NULL OR (i.inviter_commission = 0 AND i.invitee_commission = 0))"
    );
    $stmt->execute([$project_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($participants)) return;

    $stmt_update = $pdo->prepare(
        "UPDATE investments SET inviter_commission = ?, invitee_commission = ?, commission_status = COALESCE(commission_status, 'pending') 
         WHERE id = ? AND project_id = ?"
    );

    foreach ($participants as $p) {
        $inviter_commission = round(((float)$p['amount_usd'] * (float)$p['inviter_reward_percent']) / 100.0, 2);
        $invitee_commission = round(((float)$p['amount_usd'] * (float)$p['invitee_bonus_percent']) / 100.0, 2);
        $stmt_update->execute([$inviter_commission, $invitee_commission, $p['id'], $project_id]);
    }
}

