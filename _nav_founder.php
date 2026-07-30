<?php
/**
 * Navigation bar for founders (Persistent View with Static Context)
 *
 * This file implements a persistent navigation menu with a static context display.
 * Management links are disabled until a project is selected and its setup is complete.
 *
 * --- MODIFICATION ---
 * Removed the top-level "Role Switcher" tabs. This functionality is now
 * handled in layout.php within the user profile menu.
 * ---
 */

// Data is pre-loaded by index.php
$projects_for_nav = $_SESSION['founder_projects'] ?? [];
$active_project_id_for_nav = $_SESSION['active_project_id'] ?? null;
$active_project_for_nav = null;

if ($active_project_id_for_nav !== null) {
    foreach ($projects_for_nav as $p) {
        if ($p['id'] == $active_project_id_for_nav) {
            $active_project_for_nav = $p;
            break;
        }
    }
}

// Helper function to render navigation links.
// It now includes a 'data-nav-key' for the JavaScript to target.
function render_nav_link($icon, $label, $href, $nav_key, $is_enabled = true, $tooltip_text = '') {
    $base_classes = 'sidebar-link flex items-center justify-between px-3 py-2 text-sm rounded-md transition-colors duration-150';
    $inactive_classes = 'text-gray-600 hover:bg-purple-50 hover:text-purple-700';
    $disabled_classes = 'text-gray-400 cursor-not-allowed';
    $tooltip_attr = '';
    
    $classes = $base_classes;
    if (!$is_enabled) {
        $classes .= ' ' . $disabled_classes;
        $href = '#'; // Disable link
        if (!empty($tooltip_text)) {
            $tooltip_attr = "title='" . htmlspecialchars($tooltip_text) . "'";
        }
    } else {
        $classes .= ' ' . $inactive_classes;
    }
    // Convert hardcoded paths using the namespace helper
    if ($href !== '#' && strpos($href, '/') === 0) {
        $href = get_url(ltrim($href, '/'));
    }
    
    // The 'data-nav-key' attribute is the crucial addition.
    echo "<a href='{$href}' class='{$classes}' data-nav-key='{$nav_key}' {$tooltip_attr}>";
    echo "<span class='flex items-center'>";
    echo "<i data-lucide='{$icon}' class='w-5 h-5 mr-3'></i>{$label}";
    echo "</span>";
    echo "</a>";
}

// An active project is determined by the session, regardless of the page.
$has_active_project = !empty($active_project_for_nav);

// Determine if management features are unlocked.
$is_management_unlocked = false;
$disabled_tooltip = 'Select a project from the dashboard to enable this';

if ($has_active_project) {
    // A project is considered operational once it has a sale page record, even if it's a draft.
    // The status from the DB will not be NULL if a sale record exists.
    if (isset($active_project_for_nav['status'])) {
        $is_management_unlocked = true;
    } else {
        $disabled_tooltip = 'Complete project setup to unlock this feature';
    }
}
?>

<!--
    --- ROLE SWITCHER REMOVED ---
    The "Role Switcher" div that was here has been removed.
    Its logic is now in layout.php in the user profile dropdown.
-->

<!-- Spacer -->
<div class="h-2"></div>


<!-- Persistent Management Navigation -->
<nav class="px-3 space-y-1">
    <?php
    // We now pass a unique key ('dashboard', 'investors', etc.) to each link.
    // The PHP no longer needs to decide if the link is active.
    render_nav_link('layout-dashboard', 'Dashboard', '/dashboard', 'dashboard', true);
    ?>
    
    <!-- Divider -->
    <div class="pt-2 pb-1 px-3">
        <div class="border-t border-gray-200"></div>
    </div>

    <?php
    // RENAMED "Investors" to "Backers"
    render_nav_link('users', 'Backers', '/investors', 'investors', $is_management_unlocked, $disabled_tooltip);
    render_nav_link('pie-chart', 'Rounds', '/rounds', 'rounds', $is_management_unlocked, $disabled_tooltip);
    
    // MASKED PROMOTION
    // render_nav_link('megaphone', 'Promotion', '/promotion', 'promotion', $is_management_unlocked, $disabled_tooltip);
    
    render_nav_link('send', 'Distribution', '/distribute', 'distribution', $is_management_unlocked, $disabled_tooltip);
    render_nav_link('wallet', 'Wallets', '/projectwallet', 'projectwallet', $is_management_unlocked, $disabled_tooltip);
    
    // Account Section
    ?>
    <div class="pt-2 pb-1 px-3">
        <div class="border-t border-gray-200"></div>
    </div>
    <?php
    render_nav_link('user-cog', 'Settings', '/settings', 'settings', true);
    ?>
</nav>