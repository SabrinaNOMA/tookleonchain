<?php
/**
 * src/session.php
 *
 * MANAGED SESSION HANDLER
 * Implements a "Sliding Window" session.
 * - Inactivity > 1 Hour = Logout
 * - Activity = Extends session by 1 Hour
 */

// Define session-related constants
define('SESSION_TIMEOUT_SECONDS', 3600); // 1 hour

/**
 * Starts the session and handles security checks.
 *
 * @return void
 */
function start_secure_session() {
    // 1. Start Session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT_SECONDS,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // 2. TIMEOUT CHECK (Server Side)
    // If the user has been inactive for too long, destroy the session.
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
        session_unset();
        session_destroy();
        
        // Start a fresh empty session to hold the "expired" message if needed
        session_start(); 
        
        // Optional: Redirect immediately (handled by index.php usually, but good for safety)
        return; 
    }

    // 3. UPDATE ACTIVITY TIMESTAMP
    $_SESSION['last_activity'] = time();

    // 4. REFRESH COOKIE (The Fix for "Hard Limit")
    // This extends the browser cookie lifetime by another hour.
    // Without this, the browser deletes the cookie after 1 hour total, regardless of activity.
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            time() + SESSION_TIMEOUT_SECONDS, // New expiry: Now + 1 Hour
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // 5. SECURITY: Rotate ID on first use
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}
?>