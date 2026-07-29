<?php
/**
 * Navigation bar for investors
 *
 * This file is included in the main layout (`layout.php`) when the user role is 'investor'.
 *
 * --- MODIFICATION ---
 * Removed the top-level "Role Switcher" tabs. This functionality is now
 * handled in layout.php within the user profile menu.
 * ---
 */

// Helper function to render investor navigation links.
function render_investor_nav_link($icon, $label, $href, $nav_key) {
    // The 'data-nav-key' is used by layout.php to highlight the active page.
    // FIX: Added text-sm to match the font size of the founder nav.
    echo "<a href='{$href}' class='sidebar-link py-2 px-3 text-sm' data-nav-key='{$nav_key}'>";
    echo "<i data-lucide='{$icon}' class='w-5 h-5 mr-3'></i>";
    echo "<span>{$label}</span>";
    echo "</a>";
}
?>

<!--
    --- ROLE SWITCHER REMOVED ---
    The "Role Switcher" div that was here has been removed.
    Its logic is now in layout.php in the user profile dropdown.
-->

<!-- Spacer -->
<div class="h-4"></div>

<!-- Investor Navigation Links -->
<nav class="px-3 space-y-1">
    <?php
    // --- MODIFICATION ---
    // Reverted labels back to "Portfolio" and "Projects" as requested.
    render_investor_nav_link('layout-dashboard', 'Portfolio', '/portfolio', 'portfolio');
    render_investor_nav_link('briefcase', 'Projects', '/projects', 'projects');
    // --- End Modification ---
    
    render_investor_nav_link('wallet', 'Wallet', '/wallet', 'wallet', 'wallets');
    
    // MASKED INVITES
    // render_investor_nav_link('user-plus', 'Invites', '/invites', 'invites');
    ?>
</nav>