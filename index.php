<?php
/**
 * Main Application Router (Front Controller)
 *
 * This file acts as the single entry point for all requests.
 * It handles URL parsing, authentication, authorization (access control),
 * and loading the correct page content into a master layout.
 */

// --- 0. SESSION & INCLUDES ---
// Load the session logic FIRST
require_once __DIR__ . '/src/session.php';

// Start the secure sliding session
start_secure_session();

$pdo = require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/roles.php';
require_once __DIR__ . '/src/security.php'; // SILICON VALLEY STD: Security Layer

// --- 1. PRE-LOAD USER & FOUNDER DATA ---
if (isset($_SESSION['user_id'])) {

    // --- LOGIN SUCCESS HANDLER REDIRECT (FIXED) ---
    if (!empty($_SESSION['redirect_after_login'])) {
        $dest = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        
        // Filter out non-navigational requests (backend scripts, devtools, static files)
        $is_invalid_dest = (
            strpos($dest, '/backend/') !== false ||
            strpos($dest, 'well-known') !== false ||
            preg_match('/\.(json|png|jpg|jpeg|gif|ico|svg|css|js)$/i', $dest)
        );
        
        if (!$is_invalid_dest && strpos($dest, '/') === 0 && strpos($dest, '//') === false) {
            header('Location: ' . $dest);
            exit;
        }
    }

    // --- GATEKEEPER: STRICT MEMBERSHIP SYNC ---
    // Silicon Valley Standard: Never rely solely on session data for paid features.
    // We fetch the latest status from the DB on every request.
    $user_stmt = $pdo->prepare("SELECT first_name, last_name, email, has_membership FROM user WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $freshUserInfo = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($freshUserInfo) {
        // Update session with fresh data so UI is always correct
        $_SESSION['user_info'] = array_merge($_SESSION['user_info'] ?? [], $freshUserInfo);
    }

    // --- FOUNDER ACCESS GUARD ---
    // Rule: If you are in 'founder' mode but has_membership != 1, you are evicted.
    if (($_SESSION['user_role'] ?? '') === 'founder') {
        
        $has_valid_membership = ($freshUserInfo['has_membership'] == 1);

        if (!$has_valid_membership) {
            // 1. Force Demotion: Switch back to investor immediately.
            $_SESSION['user_role'] = 'investor';
            
            // 2. Redirect Loop Protection: Only redirect if not already on the subscription page.
            $current_uri = $_SERVER['REQUEST_URI'];
            if (strpos($current_uri, 'subscription') === false) {
                header('Location: /subscription?msg=required');
                exit;
            }
        }
    }

    // --- Load Founder Projects (Only if they passed the Guard) ---
    if (($_SESSION['user_role'] ?? '') === 'founder') {
        $founder_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare(
           "SELECT
                p.id,
                p.project_name,
                (SELECT status FROM token_sale_pages WHERE project_id = p.id ORDER BY created_at DESC LIMIT 1) as status
             FROM projet p
             WHERE p.founder_id = ?"
        );
        $stmt->execute([$founder_id]);
        $_SESSION['founder_projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- ROBUST ACTIVE PROJECT HANDLING ---
        $setup_pages = ['setup', 'story', 'tokenname', 'tokensupply', 'fundraising', 'vesting', 'validate', 'parameter', 'compliance', 'approve', 'sales'];
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (strpos($current_path, 'tookle2/') === 0) {
            $current_path = substr($current_path, strlen('tookle2/'));
        }
        $path_parts = explode('/', $current_path);
        $base_page_key = in_array($path_parts[0], ['founder', 'investor', 'admin']) ? ($path_parts[1] ?? '') : $path_parts[0];
        
        $is_in_setup_flow = in_array($base_page_key, $setup_pages);

        // Only enforce active project outside the setup flow
        if (!$is_in_setup_flow) {
            $founder_project_ids = array_column($_SESSION['founder_projects'] ?? [], 'id'); 
            $active_project_id_is_valid = isset($_SESSION['active_project_id']) && in_array($_SESSION['active_project_id'], $founder_project_ids);

            if (!$active_project_id_is_valid) {
                if (!empty($founder_project_ids)) {
                    $_SESSION['active_project_id'] = $founder_project_ids[0]; 
                } else {
                    unset($_SESSION['active_project_id']); 
                }
            }
        }

        // Update active project name
        $_SESSION['active_project_name'] = 'New Project'; 
        if (isset($_SESSION['active_project_id'])) {
            foreach ($_SESSION['founder_projects'] ?? [] as $proj) { 
                if ($proj['id'] === $_SESSION['active_project_id']) {
                    $_SESSION['active_project_name'] = $proj['project_name'];
                    break;
                }
            }
        }
    }
}


// --- 2. URL PARSING & NAMESPACING ---
$request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$project_folder = ''; // Root installation

if (!empty($project_folder) && strpos($request_path, $project_folder . '/') === 0) {
    $request_path = substr($request_path, strlen($project_folder) + 1);
} elseif (!empty($project_folder) && $request_path === $project_folder) {
    $request_path = '';
}

$url_context = 'public'; 
$page_key = strtolower($request_path);

if (empty($page_key)) {
    $page_key = 'home';
} else {
    $parts = explode('/', $page_key);
    if ($parts[0] === 'founder' || $parts[0] === 'investor') {
        $url_context = $parts[0];
        array_shift($parts);
        $page_key = empty($parts) ? 'home' : implode('/', $parts);
    }
}

// --- HELPER: Construct Correct URL ---
function get_url($path) {
    global $project_folder, $url_context;
    
    // Normalize path: strip leading slashes
    $path = ltrim($path, '/');
    
    // Strip prefix if path already starts with founder/ or investor/
    if (strpos($path, 'founder/') === 0) {
        $path = substr($path, 8);
    } elseif (strpos($path, 'investor/') === 0) {
        $path = substr($path, 9);
    }
    
    // Special public pages that don't need namespaces
    $public_pages = ['login', 'logout', 'privacy', 'terms', 'subscription', 'activate'];
    if (in_array($path, $public_pages)) {
        return '/' . $path;
    }
    
    // If we are in a public context (like root or settings), fallback to the session role
    $effective_context = $url_context;
    if ($effective_context === 'public' && isset($_SESSION['user_role'])) {
        $effective_context = $_SESSION['user_role'];
    }
    
    $context_prefix = ($effective_context === 'public') ? '' : '/' . $effective_context;
    
    return $context_prefix . '/' . $path;
}


// --- 3. PUBLIC, BACKEND & STANDALONE PAGE HANDLING ---
$standalone_pages = ['escrow'];
// MODIFIED: Added 'privacy' and 'terms' to public pages to allow access without login
$public_pages = ['login', 'logout', 'privacy', 'terms', 'activate']; 

// --- 3.A PUBLIC SALE PAGE ROUTE (no login) ---
if (preg_match('#^p/([A-Za-z0-9]{6,64})$#', $page_key, $m)) {
    $sale_url_token = $m[1];
    
    // Automatically record visited/unlocked private sale token in session
    if (!isset($_SESSION['my_unlocked_sales']) || !is_array($_SESSION['my_unlocked_sales'])) {
        $_SESSION['my_unlocked_sales'] = [];
    }
    if (!in_array($sale_url_token, $_SESSION['my_unlocked_sales'])) {
        $_SESSION['my_unlocked_sales'][] = $sale_url_token;
    }

    $file_to_include = 'pages/salepage_public.php';

    if (file_exists($file_to_include)) {
        $GLOBALS['sale_url_token'] = $sale_url_token;
        include $file_to_include;
    } else {
        http_response_code(500);
        echo 'Missing public sale page handler.';
    }
    exit();
}

if (in_array($page_key, $public_pages) || in_array($page_key, $standalone_pages)) {
    if ($page_key === 'logout') {
        session_destroy();
        // FIX: Use helper or clean logic to avoid //login
        header('Location: ' . get_url('login'));
        exit();
    }

    if ($page_key === 'home' && !isset($_SESSION['user_id'])) {
        $page_key = 'login';
    }

    $file_to_include = 'pages/' . $page_key . '.php';

    if (!file_exists($file_to_include) && $page_key === 'login') {
         $file_to_include = 'pages/login.html';
    }

    if (file_exists($file_to_include)) {
        include $file_to_include; 
    } else {
        http_response_code(404);
        include 'pages/404_not_found.php';
    }
    exit(); 
}


// --- 4. AUTHENTICATION (for all remaining pages) ---
if (!isset($_SESSION['user_id'])) {
    // Only remember redirect destination if it's a valid app page route
    if (!empty($page_key) && strpos($page_key, '.') === false && strpos($page_key, 'well-known') === false && strpos($page_key, 'backend') === false) {
        $redirect_url = get_url($page_key);
        if (!empty($_SERVER['QUERY_STRING'])) {
            $redirect_url .= '?' . $_SERVER['QUERY_STRING'];
        }
        $_SESSION['redirect_after_login'] = $redirect_url;
    }
    
    header('Location: ' . get_url('login'));
    exit();
}
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'investor'; 


// --- Special case for 'home' when logged in ---
if ($page_key === 'home') {
    // Determine redirect based strictly on paying membership status
    $stmt = $pdo->prepare("SELECT has_membership FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($u && $u['has_membership'] == 1) {
        $_SESSION['user_role'] = 'founder';
        header('Location: /founder/dashboard');
        exit;
    } else {
        $_SESSION['user_role'] = 'investor';
        header('Location: /investor/projects');
        exit;
    }
}

// --- has_access function definition ---
if (!function_exists('has_access')) {
    function has_access($user_role, $allowed_roles) {
        return in_array($user_role, $allowed_roles);
    }
}


// --- 5. PAGE & ACCESS DEFINITIONS (ACL) ---
$pages = [
    // Investor & Payment Flow Pages (Accessible to both Investors and Founders)
    'portfolio'       => ['file' => 'pages/portfolio.php', 'backend' => 'backend/portfolio_backend.php', 'roles' => ['investor', 'founder']],
    'projects'        => ['file' => 'pages/projects.html',  'roles' => ['investor', 'founder']], 
    'salepage'        => ['file' => 'pages/salepage.php', 'roles' => ['investor', 'founder'], 'nav_parent' => 'projects'],
    'purchase'        => ['file' => 'pages/purchase.php', 'roles' => ['investor', 'founder'], 'nav_parent' => 'projects'],
    'kyc'             => ['file' => 'sumsub/public/start_kyc.php', 'roles' => ['investor', 'founder'], 'nav_parent' => 'projects'],
    'receivingwallet' => ['file' => 'pages/receivingwallet.php', 'roles' => ['investor', 'founder'], 'nav_parent' => 'projects'], 
    'payment'         => ['file' => 'pages/payment.php', 'roles' => ['investor', 'founder'], 'nav_parent' => 'projects'],
    'wallet'          => ['file' => 'pages/wallet.php', 'roles' => ['investor', 'founder']],
    'legal'           => ['file' => 'pages/legal.php', 'backend' => 'backend/legal_backend.php', 'roles' => ['investor', 'founder']],
    'edit-wallet'     => ['file' => 'pages/receivingwallet.php', 'roles' => ['investor', 'founder'], 'nav_parent' => 'portfolio'], 
    'backerdashboard' => [
        'file' => 'pages/backerdashboard.php',
        'backend' => 'backend/backerdashboard_backend.php',
        'roles' => ['investor', 'founder'],
        'nav_parent' => 'portfolio'
    ],

    // Founder Pages
    'dashboard'       => ['file' => 'pages/dashboard.php', 'backend' => 'backend/dashboard_backend.php', 'roles' => ['founder']],
    'setup'           => ['file' => 'pages/setup.php', 'roles' => ['founder']], 
    'tokenname'       => ['file' => 'pages/token_name.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'tokensupply'     => ['file' => 'pages/token_supply.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'fundraising'     => ['file' => 'pages/fundraising.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'vesting'         => ['file' => 'pages/vesting.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'validate'        => ['file' => 'pages/validate.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'story'           => ['file' => 'pages/story.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'parameter'       => ['file' => 'pages/parameter.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'compliance'      => ['file' => 'pages/compliance.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'approve'         => ['file' => 'pages/approve.php', 'roles' => ['founder'], 'nav_parent' => 'setup'], 
    'investors'       => ['file' => 'pages/investors.php', 'roles' => ['founder']],
    'promotion'       => ['file' => 'pages/promotion.php', 'roles' => ['founder']],
    'sales'           => ['file' => 'pages/newsale.php', 'roles' => ['founder']], 
    'rounds'          => ['file' => 'pages/rounds.php', 'roles' => ['founder']], 
    'distribute'      => ['file' => 'pages/distribute.php', 'roles' => ['founder'], 'nav_parent' => 'distribution'], 
    'release'         => ['file' => 'pages/release_tokens.php', 'roles' => ['founder'], 'nav_parent' => 'distribution'], 
    'projectwallet'   => ['file' => 'pages/projectwallet.php', 'roles' => ['founder']],
    
    // Vault Pages
    'setup_vault'     => ['file' => 'pages/setup_vault.php', 'roles' => ['founder']],
    'setup_escrow'    => ['file' => 'pages/setup_escrow.php', 'roles' => ['founder']], 
    'claim_funds'     => ['file' => 'pages/claim_funds.php', 'roles' => ['founder']],

    // Shared Pages
    'settings'        => ['file' => 'pages/settings.php', 'roles' => ['investor', 'founder']],
    'invites'         => ['file' => 'pages/invites.html', 'roles' => ['investor', 'founder']], 
    'subscription'    => [
        'file' => 'pages/subscription.php', 
        'backend' => 'backend/subscription_backend.php', 
        'roles' => ['investor', 'founder'] // Both roles can access
    ],
];


// --- GATEKEEPER: Enforce Founder Membership ---
if ($url_context === 'founder') {
    // $pdo is already loaded at the top of index.php
    $stmt = $pdo->prepare("SELECT has_membership FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['has_membership'] == 0) {
        header('Location: /subscription?msg=access_denied');
        exit;
    }
}

// --- 6. ROUTING LOGIC & CONTENT LOADING ---
ob_start(); 

$original_page_key = $page_key; 
$page_to_include = 'pages/404_not_found.php'; 
$layout_page_key = $original_page_key; 
$page_data = []; 

if (isset($pages[$page_key])) {
    $page_config = $pages[$page_key];

    // Access is granted if either the URL context or the active session user_role is allowed for this page.
    $active_role = ($url_context === 'public') ? ($_SESSION['user_role'] ?? 'investor') : $url_context;

    if (in_array($active_role, $page_config['roles']) || in_array($url_context, $page_config['roles'])) {
        if (file_exists($page_config['file'])) {
            if (isset($page_config['backend']) && file_exists($page_config['backend'])) {
                include $page_config['backend'];
            }
            $page_to_include = $page_config['file']; 

            if (isset($page_config['nav_parent'])) {
                $layout_page_key = $page_config['nav_parent'];
            } else {
                 $layout_page_key = $page_key; 
            }

        } else {
             error_log("Routing error: File not found for page key '{$page_key}': {$page_config['file']}");
             $page_to_include = 'pages/404_not_found.php'; 
             http_response_code(404);
        }
    } else {
        // If they accessed a URL without a namespace (public), but they have a role that grants access, redirect them seamlessly
        if ($url_context === 'public' && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $page_config['roles'])) {
            header('Location: /' . $_SESSION['user_role'] . '/' . $page_key);
            exit;
        }

        // If an investor context tries to access a Founder page, send them to Subscription
        if ($url_context === 'investor' && in_array('founder', $page_config['roles'])) {
             header('Location: /subscription?msg=access_denied');
             exit;
        }

        $page_to_include = 'pages/403_forbidden.php';
        http_response_code(403);
    }
} else {
     http_response_code(404);
}

include $page_to_include;
$content = ob_get_clean(); 

// --- 7. RENDER FINAL LAYOUT ---
$page_key = $layout_page_key; 

$focus_mode_pages = [
    'setup', 'story', 'tokenname', 'tokensupply', 'fundraising',
    'vesting', 'validate', 'parameter', 'compliance', 'approve',
    'purchase', 'receivingwallet', 'payment','kyc',
    'sales',
    'setup_vault', 
    'setup_escrow',
    'edit-wallet',
    'claim_funds',
    'subscription' // Distraction-free upgrade page
];
$sidebar_mode = in_array($original_page_key, $focus_mode_pages) ? 'focus' : 'full';

$setup_flow_pages = [
    'setup', 'story', 'tokenname', 'tokensupply', 'fundraising',
    'vesting', 'validate', 'parameter', 'compliance', 'approve'
];
$purchase_flow_pages = ['purchase', 'receivingwallet', 'payment', 'kyc'];

$focus_mode_exit_url = get_url('dashboard'); 
$focus_mode_exit_text = 'Exit';
$focus_mode_title = 'Manage Project'; 

if ($sidebar_mode === 'focus') {
    if (in_array($original_page_key, $setup_flow_pages)) {
        $focus_mode_exit_url = get_url('dashboard');
        $focus_mode_exit_text = 'Exit';
        $focus_mode_title = 'Project Setup';
    } elseif ($original_page_key === 'sales') {
        $focus_mode_exit_url = get_url('dashboard'); 
        $focus_mode_exit_text = 'Cancel';
        $focus_mode_title = 'Private Sale';
    } elseif ($original_page_key === 'setup_vault') {
        $focus_mode_exit_url = get_url('dashboard');
        $focus_mode_exit_text = 'Close';
        $focus_mode_title = 'Smart Vault Protocol';
    } elseif ($original_page_key === 'claim_funds') {
        $focus_mode_exit_url = get_url('dashboard');
        $focus_mode_exit_text = 'Exit';
        $focus_mode_title = 'Vault Management';
    } elseif ($original_page_key === 'setup_escrow') {
        $focus_mode_exit_url = get_url('dashboard');
        $focus_mode_exit_text = 'Cancel Deployment';
        $focus_mode_title = 'Deploy Escrow Contract';
    } elseif (in_array($original_page_key, $purchase_flow_pages)) {
        $focus_mode_exit_url = get_url('projects'); 
        $focus_mode_exit_text = 'Cancel Purchase';
        $focus_mode_title = 'Complete Your Purchase';
    } elseif ($original_page_key === 'edit-wallet') {
        $focus_mode_exit_url = get_url('backerdashboard'); 
        $focus_mode_exit_text = 'Back to Dashboard';
        $focus_mode_title = 'Configure Receiving Wallet'; 
    } elseif ($original_page_key === 'subscription') {
        $focus_mode_exit_url = '/portfolio'; 
        $focus_mode_exit_text = 'Back';
        $focus_mode_title = 'Upgrade Account'; 
    }
}

include 'layout.php';
?>