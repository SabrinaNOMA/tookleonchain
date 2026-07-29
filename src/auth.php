<?php
/**
 * src/auth.php
 *
 * Contains functions for user authentication.
 */

// The `start_secure_session()` function is now expected to be in src/session.php,
// which is included by the main index.php router.

/**
 * Checks if a user is authenticated based on session data.
 *
 * @param PDO $pdo The database connection object.
 * @return array|null The user's row from the database, or null if not authenticated.
 */
function auth_check_user(PDO $pdo) {
    // Start session to access session variables.
    start_secure_session();

    if (!isset($_SESSION['user_id'])) {
        return null; // Not authenticated
    }

    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, invite_code FROM user WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

/**
 * Logs a user in by setting the session.
 *
 * @param int $userId The ID of the user to log in.
 * @return void
 */
function loginUser($userId) {
    start_secure_session();
    $_SESSION['user_id'] = $userId;
    // Assume the role is 'investor' by default as per the index.php router
    $_SESSION['user_role'] = 'investor';
}

/**
 * Logs a user out by destroying the session.
 *
 * @return void
 */
function logoutUser() {
    start_secure_session();
    session_unset();
    session_destroy();
}
