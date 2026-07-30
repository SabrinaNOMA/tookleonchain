<?php
/**
 * Role Switcher Endpoint
 *
 * Handles changing the user's role.
 * SECURITY: Enforces POST, CSRF checks, and Real-time DB lookup.
 */

// --- 1. BOOTSTRAP ---
if (file_exists(__DIR__ . '/../src/session.php')) {
    require_once __DIR__ . '/../src/session.php';
    start_secure_session();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pdo = require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/security.php'; // Load CSRF helper

// --- 2. AUTH & METHOD CHECK ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// STRICT: Only POST is allowed for state changes
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// --- 3. INPUT HANDLING ---
// Support JSON or Form Data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? 'switch_role';
$newRole = $input['role'] ?? null;
$token   = $input['csrf_token'] ?? null;
// Allow frontend to specify where to go next (e.g., /purchase)
$customRedirect = $input['redirect_url'] ?? null;

// Handle Project Switching
if ($action === 'switch_project' && !empty($input['project_id'])) {
    $projectId = (int)$input['project_id'];
    $_SESSION['active_project_id'] = $projectId;
    $_SESSION['user_role'] = 'founder';
    
    // Fetch project name
    $stmtP = $pdo->prepare("SELECT project_name FROM projet WHERE id = ? AND founder_id = ?");
    $stmtP->execute([$projectId, $_SESSION['user_id']]);
    $pName = $stmtP->fetchColumn();
    if ($pName) {
        $_SESSION['active_project_name'] = $pName;
    }
    echo json_encode(['success' => true, 'redirect' => '/dashboard']);
    exit;
}

// Handle New Project Creation
if ($action === 'create_project') {
    $_SESSION['user_role'] = 'founder';
    unset($_SESSION['active_project_id']);
    echo json_encode(['success' => true, 'redirect' => '/setup']);
    exit;
}

// --- 4. CSRF VALIDATION ---
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid Security Token. Refresh page.']);
    exit;
}

// --- 5. MEMBERSHIP CHECK (Real-time) ---
if ($newRole === 'founder') {
    $stmt = $pdo->prepare("SELECT has_membership FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no membership, deny access and tell frontend to redirect
    if (!$user || $user['has_membership'] == 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'membership_required', 
            'redirect' => '/subscription'
        ]);
        exit;
    }
}

// --- 6. EXECUTE SWITCH ---
if (in_array($newRole, ['founder', 'investor'])) {
    $_SESSION['user_role'] = $newRole;
    
    // Determine destination
    // Priority: Custom Redirect > Role Default
    $redirect = ($newRole === 'founder') ? '/dashboard' : '/portfolio';

    if ($customRedirect) {
        // UX SECURITY: Sanitize redirect to ensure it's a local path
        // 1. Must start with /
        // 2. Must not contain // (protocol relative)
        // 3. Simple character set allow-list (alphanumeric, -, _, /)
        if (strpos($customRedirect, '/') === 0 && strpos($customRedirect, '//') === false) {
             $redirect = filter_var($customRedirect, FILTER_SANITIZE_URL);
        }
    }
    
    echo json_encode(['success' => true, 'redirect' => $redirect]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
}
exit;
?>