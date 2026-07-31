<?php
/**
 * Cron Job: Revoke 7-day trials
 * Runs daily via OVH Cron.
 * For now, hardcoded for giada@dragonflydigitalassets.fund.
 */

require_once __DIR__ . '/../config.php';
$pdo = require_once __DIR__ . '/../src/db.php';

$email_to_revoke = 'giada@dragonflydigitalassets.fund';

try {
    $stmt = $pdo->prepare("SELECT id, created_at, has_membership FROM user WHERE email = ?");
    $stmt->execute([$email_to_revoke]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "User $email_to_revoke not found.\n";
        exit;
    }

    if ($user['has_membership'] == 0) {
        echo "User $email_to_revoke already has no membership.\n";
        exit;
    }

    $created_at = new DateTime($user['created_at']);
    $now = new DateTime();
    $interval = $now->diff($created_at);

    if ($interval->days >= 7) {
        $update = $pdo->prepare("UPDATE user SET has_membership = 0 WHERE id = ?");
        $update->execute([$user['id']]);
        echo "SUCCESS: Revoked access for $email_to_revoke (Account age: " . $interval->days . " days).\n";
    } else {
        echo "PENDING: User $email_to_revoke still has access (Account age: " . $interval->days . " days).\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
