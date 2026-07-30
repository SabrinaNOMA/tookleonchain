<?php
// Ensure the session is active to check the user's role
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine if this is an investor trying to access a founder-only page
$is_investor_on_founder_page = false;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'investor') {
    $is_investor_on_founder_page = true;
}

// CORRECTION : Redirection vers la vraie page de setup
$redirect_after_switch = '/setup';
?>
<div class="flex-1 flex items-center justify-center h-full min-h-[70vh] p-4">
    <div class="text-center bg-white p-8 md:p-12 rounded-xl shadow-sm border border-gray-100 max-w-md w-full">
        <?php if ($is_investor_on_founder_page): ?>
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="lock" class="w-8 h-8 text-purple-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-3">Founder Mode Required</h1>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">To start fundraising or set up a new project, you need to switch your active profile to Founder Mode.</p>
            
            <button onclick="switchRoleToFounder()" class="w-full bg-purple-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                Switch to Founder Mode
            </button>
            
            <a href="/portfolio" class="inline-block mt-4 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                Cancel, take me back
            </a>
            
            <script>
            function switchRoleToFounder() {
                fetch('/backend/role_switcher.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'switch_role',
                        role: 'founder',
                        redirect_url: '<?php echo addslashes($redirect_after_switch); ?>',
                        csrf_token: document.querySelector('meta[name="csrf-token"]').content
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                    } else if (data.error === 'membership_required') {
                        window.location.href = data.redirect;
                    } else {
                        alert(data.error || 'Failed to switch role.');
                    }
                })
                .catch(error => {
                    console.error('Error switching role:', error);
                    alert('An error occurred. Please try again.');
                });
            }
            </script>
        <?php else: ?>
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="shield-alert" class="w-8 h-8 text-red-600"></i>
            </div>
            <h1 class="text-6xl font-bold text-gray-900 mb-2">403</h1>
            <p class="text-xl font-semibold text-gray-800 mb-3">Access Denied</p>
            <p class="text-gray-500 text-sm mb-8">You do not have permission to view this page.</p>
            <a href="/" class="inline-block bg-purple-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                Go to Homepage
            </a>
        <?php endif; ?>
    </div>
</div>
<script>
    // Make sure Lucide icons render
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>