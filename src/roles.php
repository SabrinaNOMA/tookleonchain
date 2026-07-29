<?php
/**
 * src/roles.php
 *
 * Defines user roles and provides functions for access control.
 */

/**
 * Gets the current user's role from the session.
 *
 * @return string The user's role, or 'guest' if not set.
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? 'guest';
}

/**
 * Checks if a user with a given role has permission for an action.
 *
 * @param string $role The user's role.
 * @param string $action The action to check (e.g., 'view_portfolio').
 * @return bool True if the user has permission, false otherwise.
 */
function can($role, $action) {
    $permissions = [
        'investor' => ['view_portfolio', 'view_projects', 'view_wallet'],
        'founder' => ['view_projects', 'manage_project'],
        'guest' => []
    ];

    return isset($permissions[$role]) && in_array($action, $permissions[$role]);
}
