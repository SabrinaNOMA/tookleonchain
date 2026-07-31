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

            <!-- === STRIPE-STYLE UNIFIED WORKSPACE & ROLE SWITCHER (INSTITUTIONAL GRADE) === -->
            <div class="px-4 pb-3 flex-shrink-0 relative">
                <button id="workspace-switcher-btn" type="button" class="w-full flex items-center justify-between px-3 py-2.5 bg-white hover:bg-gray-50 border border-gray-200 rounded-xl transition-all shadow-sm group">
                    <div class="flex items-center min-w-0 text-left">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-2.5 shrink-0 bg-gray-100 text-gray-700 border border-gray-200">
                            <i data-lucide="<?= ($user_role_for_layout === 'founder') ? 'building' : 'briefcase' ?>" class="w-4 h-4 text-gray-700"></i>
                        </div>
                        <div class="truncate">
                            <p class="text-xs font-bold text-gray-900 truncate">
                                <?= ($user_role_for_layout === 'founder') ? htmlspecialchars($_SESSION['active_project_name'] ?? 'Founder Company') : 'Personal Portfolio' ?>
                            </p>
                            <p class="text-[11px] font-medium text-gray-500 truncate flex items-center">
                                <span><?= ($user_role_for_layout === 'founder') ? 'Founder Workspace' : 'Backer Workspace' ?></span>
                            </p>
                        </div>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 ml-1 group-hover:text-gray-600 transition-colors"></i>
                </button>

                <!-- UNIFIED DROPDOWN MENU (INSTITUTIONAL MONOCHROME) -->
                <div id="workspace-switcher-menu" class="absolute top-full left-4 right-4 mt-1 hidden opacity-0 -translate-y-2 bg-white rounded-xl shadow-2xl border border-gray-200 z-50 p-2 transition-all duration-200">
                    
                    <!-- SECTION 1: ROLE SWITCH -->
                    <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Role Mode</div>
                    
                    <a href="#" class="js-ws-action flex items-center px-3 py-2 text-xs rounded-lg transition-colors <?= ($user_role_for_layout === 'investor') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-700 hover:bg-gray-50 font-medium' ?>" data-action="switch_role" data-role="investor">
                        <i data-lucide="briefcase" class="w-4 h-4 mr-2 text-gray-600"></i>
                        <span>Backer Workspace</span>
                        <?php if ($user_role_for_layout === 'investor'): ?><i data-lucide="check" class="w-4 h-4 ml-auto text-gray-900"></i><?php endif; ?>
                    </a>

                    <?php if ($user_has_membership_for_layout): ?>
                        <a href="#" class="js-ws-action flex items-center px-3 py-2 text-xs rounded-lg transition-colors <?= ($user_role_for_layout === 'founder') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-700 hover:bg-gray-50 font-medium' ?>" data-action="switch_role" data-role="founder">
                            <i data-lucide="zap" class="w-4 h-4 mr-2 text-gray-600"></i>
                            <span>Founder Workspace</span>
                            <?php if ($user_role_for_layout === 'founder'): ?><i data-lucide="check" class="w-4 h-4 ml-auto text-gray-900"></i><?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="<?= get_url('subscription') ?>" class="flex items-center px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 rounded-lg">
                            <i data-lucide="sparkles" class="w-4 h-4 mr-2 text-gray-600"></i>
                            <span>Upgrade to Founder</span>
                        </a>
                    <?php endif; ?>

                    <!-- SECTION 2: MY COMPANIES (Only if Founder) -->
                    <?php if (!empty($_SESSION['founder_projects'])): ?>
                        <div class="my-1.5 h-px bg-gray-100"></div>
                        <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">My Companies</div>
                        <?php foreach ($_SESSION['founder_projects'] as $proj): ?>
                            <?php $isCurrentProj = (($_SESSION['active_project_id'] ?? null) == $proj['id']); ?>
                            <a href="#" class="js-ws-action flex items-center px-3 py-2 text-xs rounded-lg transition-colors <?= $isCurrentProj ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-700 hover:bg-gray-50 font-medium' ?>" data-action="switch_project" data-project-id="<?= $proj['id'] ?>">
                                <i data-lucide="building" class="w-4 h-4 mr-2 text-gray-600"></i>
                                <span class="truncate"><?= htmlspecialchars($proj['project_name']) ?></span>
                                <?php if ($isCurrentProj): ?><i data-lucide="check" class="w-4 h-4 ml-auto text-gray-900"></i><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- SECTION 3: TEAM MEMBERS & ACTIONS -->
                    <div class="my-1.5 h-px bg-gray-100"></div>
                    <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Organization</div>

                    <a href="#" id="team-members-trigger-btn" class="flex items-center px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 rounded-lg">
                        <i data-lucide="users" class="w-4 h-4 mr-2 text-gray-500"></i>
                        <span>Team Members</span>
                        <span class="ml-auto text-[9px] font-medium bg-gray-100 text-gray-600 border border-gray-200 px-1.5 py-0.5 rounded-md">Under Creation</span>
                    </a>

                    <a href="#" class="js-ws-action flex items-center px-3 py-2 text-xs font-medium text-gray-900 hover:bg-gray-50 rounded-lg" data-action="create_project">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-2 text-gray-700"></i>
                        <span>Create New Company</span>
                    </a>
                </div>
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
                        <div id="user-menu-dropdown" class="absolute bottom-full right-0 mb-2 hidden opacity-0 -translate-y-2 bg-white rounded-xl shadow-xl border z-50 w-48 p-2 transition-all duration-200">
                            <a href="https://noma-2.gitbook.io/tookle/" target="_blank" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md"><i data-lucide="help-circle" class="w-5 h-5 mr-3 text-gray-500"></i> Help & Docs</a>
                            <div class="my-1 h-px bg-gray-100"></div>
                            <a href="<?= get_url('logout') ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md"><i data-lucide="log-out" class="w-5 h-5 mr-3 text-gray-500"></i> Logout</a>
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
                <!-- INCREASED MOBILE LOGO SIZE -->
                <a href="<?= get_url('dashboard') ?>" class="flex-1 flex justify-center">
                    <img id="tookle-logo-mobile" src="" alt="Tookle Logo" class="h-20 w-auto">
                </a>
                <!-- Empty div to balance flex layout and keep logo centered -->
                <div class="w-10 h-10"></div>
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

        // --- STRIPE-STYLE UNIFIED WORKSPACE SWITCHER JS ---
        const wsBtn = document.getElementById('workspace-switcher-btn');
        const wsMenu = document.getElementById('workspace-switcher-menu');
        if (wsBtn && wsMenu) {
            const toggleWsMenu = () => {
                const isOpen = !wsMenu.classList.contains('hidden');
                if (isOpen) {
                    wsMenu.classList.add('opacity-0', '-translate-y-2');
                    setTimeout(() => wsMenu.classList.add('hidden'), 200);
                } else {
                    wsMenu.classList.remove('hidden');
                    requestAnimationFrame(() => {
                        wsMenu.classList.remove('opacity-0', '-translate-y-2');
                        if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                            lucide.createIcons();
                        }
                    });
                }
            };
            wsBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleWsMenu(); });
            document.addEventListener('click', (e) => {
                if (!wsMenu.classList.contains('hidden') && !wsBtn.contains(e.target) && !wsMenu.contains(e.target)) {
                    toggleWsMenu();
                }
            });
        }

        // --- WORKSPACE ACTIONS HANDLER ---
        const wsActions = document.querySelectorAll('.js-ws-action');
        wsActions.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const action = this.getAttribute('data-action');
                const role = this.getAttribute('data-role');
                const projectId = this.getAttribute('data-project-id');
                const metaToken = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = metaToken ? metaToken.getAttribute('content') : '';

                fetch('/backend/role_switcher.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: action,
                        role: role,
                        project_id: projectId,
                        csrf_token: csrfToken
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect;
                    } else if (data.error === 'membership_required') {
                        window.location.href = data.redirect;
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => console.error(err));
            });
        });

        // --- BRANDED TEAM MEMBERS MODAL HANDLER ---
        const teamTrigger = document.getElementById('team-members-trigger-btn');
        const teamModal = document.getElementById('team-members-modal');
        const teamModalBox = document.getElementById('team-members-modal-box');
        const closeTeamBtn = document.getElementById('close-team-modal-btn');
        const confirmTeamBtn = document.getElementById('confirm-team-modal-btn');

        const openTeamModal = () => {
            if (!teamModal) return;
            teamModal.classList.remove('hidden');
            requestAnimationFrame(() => {
                teamModal.classList.remove('opacity-0');
                if (teamModalBox) teamModalBox.classList.remove('scale-95');
                if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                    lucide.createIcons();
                }
            });
        };

        const closeTeamModal = () => {
            if (!teamModal) return;
            teamModal.classList.add('opacity-0');
            if (teamModalBox) teamModalBox.classList.add('scale-95');
            setTimeout(() => teamModal.classList.add('hidden'), 200);
        };

        if (teamTrigger) teamTrigger.addEventListener('click', (e) => { e.preventDefault(); openTeamModal(); });
        if (closeTeamBtn) closeTeamBtn.addEventListener('click', closeTeamModal);
        if (confirmTeamBtn) confirmTeamBtn.addEventListener('click', closeTeamModal);
        if (teamModal) teamModal.addEventListener('click', (e) => { if (e.target === teamModal) closeTeamModal(); });

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
<!-- BRANDED TEAM MEMBERS MODAL -->
<div id="team-members-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm hidden opacity-0 transition-all duration-200">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden border border-gray-100 transform scale-95 transition-all duration-200" id="team-members-modal-box">
        <div class="p-6">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-800 border border-gray-200">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Team Members</h3>
                        <p class="text-xs text-gray-500">Multi-User Organization Management</p>
                    </div>
                </div>
                <button type="button" id="close-team-modal-btn" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="py-5 space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <span class="text-xs font-semibold text-gray-700">Status</span>
                    <span class="text-[10px] font-bold bg-gray-200 text-gray-800 px-2 py-0.5 rounded-md border border-gray-300">Under Creation / Coming Soon</span>
                </div>
                
                <p class="text-xs text-gray-600 leading-relaxed">
                    This feature will allow organization founders to invite co-founders, financial controllers, and compliance officers with role-based access control and multi-signature authorization.
                </p>
            </div>

            <div class="pt-3 border-t border-gray-100">
                <button type="button" id="confirm-team-modal-btn" class="w-full py-2.5 px-4 bg-gray-900 hover:bg-gray-800 text-white font-semibold text-xs rounded-xl shadow-sm transition-colors">
                    Got it
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>