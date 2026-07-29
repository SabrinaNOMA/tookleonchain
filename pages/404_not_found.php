<?php
/**
 * A basic 404 Not Found page.
 * This is included by the router when a page is not found.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="text-center p-8 bg-white shadow-lg rounded-lg">
        <h1 class="text-6xl font-bold text-gray-800">404</h1>
        <p class="text-xl text-gray-600 mt-2">Page Not Found</p>
        <p class="text-gray-500 mt-4">The page you're looking for doesn't exist.</p>
        <a href="/settings" class="mt-6 inline-block px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
            Go to Settings
        </a>
    </div>
</body>
</html>