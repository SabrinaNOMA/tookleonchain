<div class="flex-1 p-6 md:p-10 overflow-y-auto bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
                <p class="mt-2 text-base text-gray-500">
                    Your central command for <?php echo htmlspecialchars($response['active_project_details']['project_name'] ?? 'your project'); ?>.
                </p>
            </div>
            <div id="dashboard-actions-container"></div>
        </header>
        <!-- Dynamic containers (metrics and sales list) and guides will follow in sales_list.php -->