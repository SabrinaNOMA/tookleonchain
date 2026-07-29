<?php
/**
 * Security Helper Functions
 * * Implements Anti-Cross-Site Request Forgery (CSRF) protection.
 * Silicon Valley Standard: Never trust state-changing requests without a token.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates a crypto-secure CSRF token and stores it in the session.
 * @return string The token to embed in forms.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the CSRF token from a request.
 * Terminates execution with 403 if invalid.
 */
function require_csrf_token() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Security Error: Invalid CSRF Token. Please refresh the page.');
    }
}