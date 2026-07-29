<?php
/**
 * Backend for the Invites page (i27d).
 * Fetches user's invite code, invitee list, and basic user info.
 */

ob_start();

// Centralized includes for core functionality.
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/session.php'; // Correctly include the session file first
require_once __DIR__ . '/../src/auth.php'; // Ensure this file exists and defines auth_check_user()
require_once __DIR__ . '/../src/roles.php';

header('Content-Type: application/json');

try {
    // Check for authentication. The auth_check_user function is expected to be in src/auth.php
    $user = auth_check_user($pdo);
    if (!$user) {
        throw new Exception('User not authenticated.', 401);
    }
    $userId = $user['id'];

    $response = [
        'userInfo' => null,
        'invite_code' => '',
        'invitees' => []
    ];

    // Fetch user info for the header and the invite code.
    $user_stmt = $pdo->prepare("SELECT first_name, last_name, email, invite_code FROM user WHERE id = ?");
    $user_stmt->execute([$userId]);
    $userInfo = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($userInfo) {
        $response['userInfo'] = [
            'first_name' => $userInfo['first_name'],
            'last_name' => $userInfo['last_name'],
            'email' => $userInfo['email']
        ];
        $response['invite_code'] = $userInfo['invite_code'] ?? 'N/A';
    } else {
        throw new Exception('User not found.', 404);
    }

    // Fetch invitees by looking for investments made with the user's invite code.
    try {
        if (!empty($userInfo['invite_code'])) {
            $invitee_stmt = $pdo->prepare("
                SELECT DISTINCT
                    u.email AS referee_email,
                    i.status AS investment_status
                FROM investments AS i
                JOIN user AS u ON i.user_id = u.id
                WHERE i.referral_code_used = ?
            ");
            $invitee_stmt->execute([$userInfo['invite_code']]);
            $investments_by_invitees = $invitee_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $invitees = [];
            if ($investments_by_invitees) {
                foreach ($investments_by_invitees as $investment) {
                    $invitees[] = [
                        'referee_email' => $investment['referee_email'],
                        // Map investment status to a simplified referral status for the frontend.
                        'status' => ($investment['investment_status'] === 'Successful') ? 'Completed' : 'Pending'
                    ];
                }
                // Remove duplicate emails, showing the "best" status (Completed > Pending)
                $unique_invitees = [];
                foreach ($invitees as $invitee) {
                    $email = $invitee['referee_email'];
                    if (!isset($unique_invitees[$email]) || $invitee['status'] === 'Completed') {
                        $unique_invitees[$email] = $invitee;
                    }
                }
                $response['invitees'] = array_values($unique_invitees);
            }
        }
    } catch (PDOException $e) {
        // Gracefully handle potential DB errors.
        error_log("Error fetching invitees from investments table: " . $e->getMessage());
    }
    
    ob_end_clean();
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    ob_end_clean();
    $statusCode = $e->getCode() === 401 ? 401 : ($e->getCode() === 404 ? 404 : 500);
    http_response_code($statusCode);
    error_log("Invites backend error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
