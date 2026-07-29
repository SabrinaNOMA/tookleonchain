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
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-full">
    <div class="text-center bg-white p-12 rounded-lg shadow-lg max-w-md w-full">
        <?php if ($is_investor_on_founder_page): ?>
            <h1 class="text-3xl font-bold text-purple-600 mb-4">Founder Mode Required</h1>
            <p class="text-gray-500 mt-2 mb-6">To start fundraising or set up a new project, you need to be in your Founder account.</p>
            
            <a href="/backend/role_switcher.php?role=founder&redirect=<?php echo urlencode($redirect_after_switch); ?>" class="inline-block w-full bg-purple-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                Switch to Founder Mode
            </a>
            
            <a href="/" class="inline-block mt-4 text-sm text-gray-500 hover:underline">
                Cancel
            </a>
        <?php else: ?>
            <h1 class="text-8xl font-bold text-purple-600">403</h1>
            <p class="text-2xl font-semibold text-gray-800 mt-4">Access Denied</p>
            <p class="text-gray-500 mt-2 mb-6">You do not have permission to view this page.</p>
            <a href="/" class="inline-block bg-purple-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors">
                Go to Homepage
            </a>
        <?php endif; ?>
    </div>
</body>
</html>