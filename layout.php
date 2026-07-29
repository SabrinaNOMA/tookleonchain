<?php
/**
 * Main Application Layout (MODIFIED FOR RESPONSIVE MOBILE VIEW)
 *
 * This file contains the primary HTML structure, including the <head>,
 * global CSS, global JavaScript, and the sidebar navigation.
 * It dynamically loads the specific page content into the <main> section.
 *
 * --- MODIFICATION (UX/UI) ---
 * Added the Role Switcher logic directly into the user-menu-dropdown.
 * This removes the tabs from the top of the nav and places the switcher
 * correctly with the user's profile.
 *
 * --- UX FINETUNING (User Feedback) ---
 * 1. Removed the word "Switch" from the role-switcher for a cleaner, more implied action.
 * 2. Changed the active-role highlight from a bright color (bg-purple-50) to a
 * more subtle, premium neutral (bg-gray-100) while keeping the text colored.
 * 3. RENAMED "Investor" to "Backer" in the UI.
 * 4. REMOVED active text color (purple/green) for a neutral `text-gray-900` premium feel.
 * 5. FIXED typo on "Backer" link (was `a>`).
 * 6. CHANGED "Logout" link from red to neutral black/gray text.
 * 7. INCREASED Logo size in sidebar for better visibility.
 */
if (file_exists(__DIR__ . '/src/session.php')) {
    require_once __DIR__ . '/src/session.php';
    start_secure_session();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SILICON VALLEY STD: Ensure CSRF Token Exists Globally ---
// This must happen before any output to ensure session is updated correctly.
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('generate_csrf_token')) {
        generate_csrf_token();
    } elseif (function_exists('bin2hex')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        // Fallback for older PHP versions (unlikely but safe)
        $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
    }
}
$csrf_token_for_meta = $_SESSION['csrf_token'];

$user_role_for_layout = (isset($url_context) && in_array($url_context, ['founder', 'investor'])) 
    ? $url_context 
    : ($_SESSION['user_role'] ?? 'investor');
$user_info_for_layout = $_SESSION['user_info'] ?? null;
$user_has_membership_for_layout = !empty($user_info_for_layout['has_membership']);
// The $sidebar_mode variable is passed from index.php
$sidebar_mode = $sidebar_mode ?? 'full';
$active_project_name_for_layout = $_SESSION['active_project_name'] ?? 'New Project';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tookle</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- lucide.js is loaded synchronously (blocking) in the head -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ADD THIS LINE HERE for Security -->
    <!-- CRITICAL: Using the pre-calculated variable to ensure sync -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token_for_meta); ?>">

    <!-- config_logo.js is deferred -->
    <script src="/config_logo.js" defer></script>
    <link rel="stylesheet" href="/css/dashboard.css">
    <style>
        :root {
            --main-bg: #f9fafb;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;

            <?php if ($user_role_for_layout === 'founder'): ?>
            --theme-primary: #6D28D9; /* Purple */
            --theme-secondary: #06b6d4; /* Cyan */
            --theme-primary-light: #EDE9FE;
            --gradient-start: #6D28D9;
            --gradient-mid: #06b6d4;
            --gradient-end: #6D28D9;
            <?php else: ?>
            --theme-primary: #10B981; /* Green */
            --theme-secondary: #3B82F6; /* Blue */
            --theme-primary-light: #D1FAE5;
            /* PHASE 0: BRAND ALIGNMENT - Use founder gradient for investor nav */
            --gradient-start: #6D28D9;
            --gradient-mid: #06b6d4;
            --gradient-end: #6D28D9;
            <?php endif; ?>
        }
        body { font-family: 'Montserrat', sans-serif; background-color: var(--main-bg); color: var(--text-primary); }
        .sidebar-link { display:flex; align-items:center; padding: 0.75rem 1rem; transition: all 0.2s ease-in-out; border-radius: 0.5rem; color: var(--text-secondary); font-weight: 500; }
        .sidebar-link:hover { background-color: #f3f4f6; color: var(--theme-primary); }
        .sidebar-link.active {
            background-image: linear-gradient(to right, var(--gradient-start), var(--gradient-mid), var(--gradient-end)) !important;
            background-size: 200% auto;
            color: white !important; 
            font-weight: 600; 
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            transition: background-position 0.5s ease;
        }
        .sidebar-link.active:hover {
             background-position: right center;
        }
        .sidebar-link.active i { color: white !important; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; font-weight: 600; border-radius: 0.5rem; transition: all 0.2s; padding: 0.625rem 1.25rem; font-size: 0.875rem; line-height: 1.25rem; }
        .btn-primary { background-image: linear-gradient(to right, var(--gradient-start), var(--gradient-mid), var(--gradient-end)); background-size: 200% auto; color: white; border: none; }
        .btn-secondary { background-color: white; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-primary:hover { background-position: right center; }
        .card { background-color: #ffffff; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.07); }
    </style>
</head>
<body class="h-full overflow-hidden">
    
    <!-- 
      NEW: Mobile overlay
      This dims the background when the mobile menu is open and closes the menu when clicked.
    -->
    <div id="mobile-overlay" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden hidden" aria-hidden="true"></div>

    <!-- MODIFIED: Apply blur effect to the whole viewport content (sidebar + main) when in focus mode -->
    <div class="flex h-full w-full">
        <!-- 
          MODIFIED: Sidebar
          - Hides on mobile (lg:flex) and is positioned fixed/off-screen (-translate-x-full).
          - Becomes static and visible on desktop (lg:static, lg:translate-x-0).
          - JS will remove -translate-x-full to slide it in on mobile.
          - ADDED BLUR CLASS WHEN IN FOCUS MODE
        -->
        <aside id="sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col flex-shrink-0
               fixed inset-y-0 left-0 z-40
               transform -translate-x-full transition-transform duration-300 ease-in-out
               lg:static lg:flex lg:translate-x-0
               <?php echo $sidebar_mode === 'focus' ? 'blur-sm pointer-events-none' : ''; ?>">
            
             <!-- NEW: Mobile close button -->
            <div class="absolute top-0 right-0 pt-4 pr-4 lg:hidden">
                <button id="mobile-menu-close-button" type="button" class="p-2 text-gray-500 hover:text-gray-700 rounded-full">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

             <div class="p-4 flex-shrink-0 flex items-center justify-center">
                <!-- LOGO SIZE INCREASED: h-14 to h-20 -->
                <a href="<?= get_url('dashboard') ?>"><img id="tookle-logo" src="" alt="Tookle Logo" class="h-20 w-auto"></a>
            </div>
            <div class="flex-grow overflow-y-auto px-4">
                <nav class="space-y-1">
                  <?php
                    $nav_file = ($user_role_for_layout === 'founder') ? '_nav_founder.php' : '_nav_investor.php';
                    if (file_exists($nav_file)) {
                        include_once $nav_file; // Kept your include_once
                    }
                  ?>
                </nav>
            </div>
            <div class="border-t border-gray-200 p-4 flex-shrink-0">
                <div class="flex items-center justify-between relative">
                    <div class="flex items-center min-w-0">
                        <span id="user-initials" class="inline-flex items-center justify-center h-9 w-9 rounded-full bg-gray-200 font-semibold text-sm mr-3 shrink-0"></span>
                        <div class="truncate user-info-text">
                            <p id="user-name" class="text-sm font-medium text-gray-800 truncate"></p>
                            <p id="user-email" class="text-xs text-gray-500 truncate"></p>
                        </div>
                    </div>
                    <div class="relative">
                        <button id="user-menu-button" type="button" class="p-2 text-gray-500 hover:bg-gray-100 rounded-full">
                            <i data-lucide="sliders-horizontal" class="w-5 h-5"></i>
                        </button>
                        <div id="user-menu-dropdown" class="absolute bottom-full right-0 mb-2 hidden opacity-0 -translate-y-2 bg-white rounded-xl shadow-xl border z-50 w-56 p-2 transition-all duration-200">
                            
                            <!-- === NEW: ROLE SWITCHER START === -->
                            <div class="px-4 py-2">
                                <p class="text-xs font-medium text-gray-400 uppercase">Viewing as</p>
                            </div>
                            
                            <?php if ($user_role_for_layout === 'founder'): ?>
                                <!-- Active Role: Founder -->
                                <!-- MOD: Changed text-purple-700 to text-gray-900 -->
                                <div class="flex items-center px-4 py-2 text-sm font-semibold text-gray-900 bg-gray-100 rounded-md">
                                    <i data-lucide="rocket" class="w-5 h-5 mr-3"></i>
                                    <span>Founder</span>
                                    <i data-lucide="check" class="w-5 h-5 ml-auto text-gray-900"></i>
                                </div>
                                <!-- Switch Link: Backer -->
                                <!-- UPDATED: Added class 'js-role-switcher' and data-role attribute -->
                                <a href="#" class="js-role-switcher flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md" data-role="investor">
                                    <i data-lucide="briefcase" class="w-5 h-5 mr-3"></i>
                                    <span>Backer</span>
                                </a>
                            <?php else: // User role is investor ?>
                                <!-- Active Role: Backer -->
                                <!-- MOD: Renamed "Investor" to "Backer" and changed text-green-700 to text-gray-900 -->
                                <div class="flex items-center px-4 py-2 text-sm font-semibold text-gray-900 bg-gray-100 rounded-md">
                                    <i data-lucide="briefcase" class="w-5 h-5 mr-3"></i>
                                    <span>Backer</span>
                                    <i data-lucide="check" class="w-5 h-5 ml-auto text-gray-900"></i>
                                </div>
                                <!-- Switch Link: Founder OR Subscribe CTA -->
                                <?php if ($user_has_membership_for_layout): ?>
                                    <a href="#" class="js-role-switcher flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md" data-role="founder">
                                        <i data-lucide="rocket" class="w-5 h-5 mr-3"></i>
                                        <span>Founder</span>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= get_url('subscription') ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                        <i data-lucide="rocket" class="w-5 h-5 mr-3"></i>
                                        <span>Subscribe as Founder</span>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="my-2 h-px bg-gray-100"></div>
                            <!-- === NEW: ROLE SWITCHER END === -->

                            <a href="<?= get_url('settings') ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md"><i data-lucide="user-cog" class="w-5 h-5 mr-3"></i> Settings</a>
                            <a href="https://noma-2.gitbook.io/tookle/" target="_blank" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md"><i data-lucide="help-circle" class="w-5 h-5 mr-3"></i> Help</a>
                            <div class="my-1 h-px bg-gray-100"></div>
                            <!-- MOD: Changed text-red-600 hover:bg-red-50 to text-gray-700 hover:bg-gray-50 -->
                            <a href="<?= get_url('logout') ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md"><i data-lucide="log-out" class="w-5 h-5 mr-3"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- 
          NEW: Main content wrapper
          - Replaces the old <main> tag.
          - Is now a flex-col container to hold the mobile header and the main content.
          - ADDED BLUR CLASS WHEN IN FOCUS MODE
          - CHANGED overflow-y-auto to overflow-y-scroll to prevent horizontal jittering in wizards
        -->
        <main class="flex-1 overflow-y-scroll flex flex-col w-full lg:w-auto 
                     <?php echo $sidebar_mode === 'focus' ? 'blur-sm pointer-events-none' : ''; ?>">
            <!-- 
              NEW: Mobile Header
              - Contains the hamburger button to open the menu.
              - Only visible on mobile (lg:hidden) and is sticky.
            -->
            <header class="sticky top-0 z-10 lg:hidden bg-white/80 backdrop-blur-sm border-b border-gray-200 flex items-center justify-between px-4 py-2">
                <button id="mobile-menu-open-button" type="button" class="p-2 text-gray-700 rounded-full hover:bg-gray-100">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <!-- INCREASED MOBILE LOGO SIZE: h-14 to h-16 -->
                <a href="<?= get_url('dashboard') ?>"><img id="tookle-logo-mobile" src="" alt="Tookle Logo" class="h-16 w-auto"></a>
                <!-- Mobile user settings icon -->
                <a href="<?= get_url('settings') ?>" class="p-2 text-gray-500 hover:bg-gray-100 rounded-full">
                    <i data-lucide="user-cog" class="w-6 h-6"></i>
                </a>
            </header>
            
            <!-- 
              MODIFIED: Main content area
              - This div now just holds the $content and is the scrollable part.
            -->
            <div class="flex-1 overflow-y-auto">
                 <?php 
                    // CRITICAL FIX: Only include content here if NOT in focus mode.
                    if ($sidebar_mode !== 'focus') {
                        echo $content; 
                    }
                 ?>
            </div>
        </main>
    </div>

    <?php if ($sidebar_mode === 'focus'): ?>
    <?php
        // LOGIC CHANGE: If we are on the subscription page, show the USER NAME instead of the project name.
        // This is because subscription is personal/account-level, not project-level.
        $focus_subtitle = htmlspecialchars($active_project_name_for_layout);
        
        if (isset($original_page_key) && $original_page_key === 'subscription') {
            $f_name = $_SESSION['user_info']['first_name'] ?? '';
            $l_name = $_SESSION['user_info']['last_name'] ?? '';
            $full_name = trim($f_name . ' ' . $l_name);
            
            if (!empty($full_name)) {
                $focus_subtitle = htmlspecialchars($full_name);
            } else {
                // Fallback to email if name is empty
                $focus_subtitle = htmlspecialchars($_SESSION['user_info']['email'] ?? 'User');
            }
        }
    ?>
    <!-- 
        MODIFIED: Focus Mode Overlay 
    -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 md:p-8 bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl h-full max-h-[95vh] flex flex-col overflow-hidden">
                <header class="flex-shrink-0 border-b border-gray-200 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-gray-800"><?php echo htmlspecialchars($focus_mode_title); ?></h2>
                                <p class="text-sm text-gray-500 truncate"><?php echo $focus_subtitle; ?></p>
                            </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($focus_mode_exit_url); ?>" class="btn btn-secondary">
                            <i data-lucide="x" class="w-4 h-4 mr-2"></i>
                            <span><?php echo htmlspecialchars($focus_mode_exit_text); ?></span>
                        </a>
                    </div>
                </header>
            <div class="flex-1 overflow-y-auto">
                <?php 
                    // CRITICAL FIX: The page content MUST be echoed here when in focus mode.
                    echo $content; 
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

<script>
    // DEFINE GLOBAL HELPERS
    const user = <?php echo json_encode($user_info_for_layout); ?>;
    async function createNewProject() { 
        try {
            const response = await fetch('/backend/set_active_project.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: null })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                window.location.href = '/setup';
            } else {
                throw new Error(result.error || 'Could not start a new project session.');
            }
        } catch (error) {
            console.error('Create project error:', error);
            // Consider adding a global error display function here
        }
     }

    // Restore the full script logic within DOMContentLoaded
    document.addEventListener('DOMContentLoaded', () => {

        // --- FIX: Wrap lucide.createIcons() in a setTimeout ---
        // This pushes the execution to the next event loop tick,
        // giving the browser more time to ensure lucide.js is ready.
        setTimeout(() => {
            if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                try {
                    lucide.createIcons();
                } catch (error) {
                    console.error('Lucide icons failed to create inside setTimeout:', error);
                }
            } else {
                console.warn('Lucide library still not ready inside setTimeout.');
            }
        }, 0); // 0ms delay is often enough

        // --- Load Logo (Desktop) ---
        const logoImg = document.getElementById('tookle-logo');
        if (logoImg && typeof TOOKLE_LOGO_BASE64 !== 'undefined' && TOOKLE_LOGO_BASE64) {
            logoImg.src = TOOKLE_LOGO_BASE64;
        } else if (logoImg) {
            console.warn('TOOKLE_LOGO_BASE64 is not defined. Logo will not load. Check .htaccess and config_logo.js.');
        }

        // --- NEW: Load Logo (Mobile) ---
        const logoMobileImg = document.getElementById('tookle-logo-mobile');
        if (logoMobileImg && typeof TOOKLE_LOGO_BASE64 !== 'undefined' && TOOKLE_LOGO_BASE64) {
            logoMobileImg.src = TOOKLE_LOGO_BASE64;
        }
        
        // --- Sidebar User Info & Initials ---
        const userNameEl = document.getElementById('user-name');
        const userEmailEl = document.getElementById('user-email');
        const userInitialsEl = document.getElementById('user-initials');

        // This data comes from the 'user' const above
        if (user && userNameEl && userEmailEl && userInitialsEl) {
            const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim();
            userNameEl.textContent = fullName || user.email || 'User';
            userEmailEl.textContent = user.email || '';
            let initials = (user.first_name?.[0] || '') + (user.last_name?.[0] || '');
            if (!initials && user.email) initials = user.email[0];
            userInitialsEl.textContent = initials.toUpperCase() || 'U';
        }

        // --- Active Navigation Link Highlighting ---
        const activeNavKey = '<?php echo $page_key ?? 'home'; ?>';
        const navLinks = document.querySelectorAll('aside nav a.sidebar-link');
        navLinks.forEach(link => {
            if (link.dataset.navKey === activeNavKey) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // --- User menu dropdown handler ---
        const menuButton = document.getElementById('user-menu-button');
        const dropdownMenu = document.getElementById('user-menu-dropdown');
        if (menuButton && dropdownMenu) {
            const toggle = () => {
                const isOpen = !dropdownMenu.classList.contains('hidden');
                if (isOpen) {
                    dropdownMenu.classList.add('opacity-0', '-translate-y-2');
                    setTimeout(() => dropdownMenu.classList.add('hidden'), 200);
                } else {
                    dropdownMenu.classList.remove('hidden');
                    // We need to re-run lucide.createIcons() *after* the menu is shown
                    // to render the new icons inside it.
                    requestAnimationFrame(() => {
                        dropdownMenu.classList.remove('opacity-0', '-translate-y-2');
                        if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                            lucide.createIcons();
                        }
                    });
                }
            };
            menuButton.addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
            document.addEventListener('click', (e) => {
                if (!dropdownMenu.classList.contains('hidden') && !menuButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    toggle();
                }
            });
        }

        // --- NEW: Mobile Menu Toggle Logic ---
        const sidebar = document.getElementById('sidebar');
        const openButton = document.getElementById('mobile-menu-open-button');
        const closeButton = document.getElementById('mobile-menu-close-button');
        const overlay = document.getElementById('mobile-overlay');

        const openMenu = () => {
            if (sidebar) sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.remove('hidden');
            // Re-run lucide when menu opens in case icons (like 'x') weren't rendered
            if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }
        };

        const closeMenu = () => {
            if (sidebar) sidebar.classList.add('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
        };

        if (openButton) openButton.addEventListener('click', openMenu);
        if (closeButton) closeButton.addEventListener('click', closeMenu);
        if (overlay) overlay.addEventListener('click', closeMenu);
        
        // --- NEW: Close mobile menu on nav link click ---
        // This improves mobile UX significantly.
        const sidebarNavLinks = document.querySelectorAll('#sidebar nav a.sidebar-link');
        sidebarNavLinks.forEach(link => {
            link.addEventListener('click', () => {
                // Only run closeMenu if in mobile view (i.e., overlay exists and is visible)
                if (overlay && !overlay.classList.contains('hidden')) {
                    closeMenu();
                }
            });
        });

    });
</script>
<!-- MODIFIED: Inlined Secure Switch Logic to prevent 404 errors on external file -->
<script>
/**
 * Silicon Valley Standard: Secure Action Handler
 * Handles Role Switching via POST with CSRF protection.
 */
document.addEventListener('DOMContentLoaded', () => {
    
    // Find all elements with the class 'js-role-switcher'
    const switchers = document.querySelectorAll('.js-role-switcher');

    switchers.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault(); // Stop the link from opening normally

            const targetRole = this.getAttribute('data-role');
            // Get token from meta tag, handle case where it might be missing
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = metaToken ? metaToken.getAttribute('content') : '';

            // Silicon Valley Std: Use fetch for background POST request
            // UPDATED PATH: Corrected to point to 'backend' directory as per your file structure
            fetch('/backend/role_switcher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    role: targetRole,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (response.status === 404) {
                    throw new Error('Role switcher endpoint not found at /tookle2/backend/role_switcher.php');
                } else if (response.status === 403) {
                    // NEW: Handle CSRF/Permission errors gracefully
                    // Often means session expired or token mismatch. Prompt refresh.
                    return response.json().then(data => {
                        throw new Error(data.error || 'Security Token Mismatch. Please refresh the page.');
                    }).catch(() => {
                        throw new Error('Security Token Mismatch (403). Please refresh the page.');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Success! Redirect to the dashboard/portfolio
                    window.location.href = data.redirect;
                } else if (data.error === 'membership_required') {
                    // Handle the Membership Check failure gracefully
                    window.location.href = data.redirect; // Redirects to /subscription
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // If token is invalid, offer to refresh
                if (error.message.includes('Security Token') || error.message.includes('refresh')) {
                    if (confirm('Your session security token has expired. Reload page?')) {
                        window.location.reload();
                    }
                } else {
                    alert('System Error: ' + error.message);
                }
            });
        });
    });
});
</script>
</body>
</html>