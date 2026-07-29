<?php
// This file is now included by index.php, which has already run the backend script.
// The data from the backend is available in the $response variable.

// --- Data Preparation for JavaScript ---
$dashboard_data_json = "null"; // Default to null string
$project_data_for_js = "null";

// Define safety flags globally for consistency
// JSON_HEX_TAG ensures </script> cannot be injected. JSON_HEX_AMP is generally safe.
// We keep JSON_THROW_ON_ERROR to catch encoding issues server-side.
$flags = JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP;

try {
    // 1. Encode main dashboard data
    $raw_dashboard_json = json_encode($response ?? ['error' => 'No data available.'], $flags);
    // CRITICAL FIX: PHP json_encode does not escape U+2028/U+2029 line separators.
    // JS treats them as newlines, which breaks the script. We must replace them manually.
    $dashboard_data_json = str_replace(
        ["\xe2\x80\xa8", "\xe2\x80\xa9"], 
        ['\u2028', '\u2029'], 
        $raw_dashboard_json
    );

    // 2. Prepare project details for JS bridge
    $project_details = $response['active_project_details'] ?? null;
    $contract_address = $project_details['contract_address'] ?? null;
    $contract_network = $project_details['contract_network'] ?? 'base'; // Default to base
    $project_logo = $project_details['project_logo'] ?? null;

    // Determine explorer URL
    $explorer_url = 'https://basescan.org/token/'; // Default to Base
    if (strtolower($contract_network) === 'solana') {
        $explorer_url = 'https://solscan.io/token/';
    } else if (strtolower($contract_network) === 'ethereum') {
        $explorer_url = 'https://etherscan.io/token/';
    } else if (strtolower($contract_network) === 'polygon') {
        $explorer_url = 'https://polygonscan.com/token/';
    }
    $contract_link = $contract_address ? $explorer_url . $contract_address : null;

    $raw_project_json = json_encode([
        'contract_link' => $contract_link,
        'project_logo' => $project_logo
    ], $flags);
    
    // Apply the same fix to project data
    $project_data_for_js = str_replace(
        ["\xe2\x80\xa8", "\xe2\x80\xa9"], 
        ['\u2028', '\u2029'], 
        $raw_project_json
    );

} catch (Exception $e) {
    error_log("Dashboard JSON encoding error: " . $e->getMessage());
    // Fallback to safe defaults if encoding completely fails
    $dashboard_data_json = '{"error": "Failed to encode data safely."}';
}

$show_wizard_success = false;
if (isset($_SESSION['show_wizard_success_popup']) && $_SESSION['show_wizard_success_popup'] === true) {
    $show_wizard_success = true;
    unset($_SESSION['show_wizard_success_popup']); // Consume it so it only shows once
}
// --- END: Safer JSON encoding ---
?>

<?php if ($show_wizard_success): ?>
<div id="wizard-success-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 overflow-hidden animate-fade-in-up">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="check-circle" class="w-8 h-8 text-green-600"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Setup Complete!</h3>
            <p class="text-gray-600 mb-6">Your token sale room is fully configured. You can go live anytime directly from your dashboard and share the link to start receiving contributions!</p>
            <button onclick="document.getElementById('wizard-success-modal').remove()" class="w-full bg-blue-600 text-white rounded-lg py-3 font-semibold hover:bg-blue-700 transition-colors">
                Awesome, let's go!
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Favicon Link -->
<link id="favicon" rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAACAAAAAgACAYAAACyp9MwAAAQAElEQVR4Aezce6xuaV0f8LUPSFqstcGopI0xkEglKWkoCba2aYIYYk2LzAyTlBqVm0AFB2gqw8CAw/0ycAbExPpHm7Sx7R+WmKZRQ0SSptGKAYRBBgYYQGpiMRJNK5GLZy/X2vucmXPZl/eyLs/zfT7Dec/Z73rXep7f9/OcP+ac/R0udP4hQIAAAQIECBAgQIAAAQIECBBoXkI0CAAAECBAgQIAAAQIECBAgEC+wJhQAWBU8CJAgAABAgQIECBAgAABArkCkhEgQIAAAQIECBAgQIAAAQL5...">

<!-- Force gradient style for primary buttons (Inline CSS is kept here for global overrides) -->
<style>
    /* Force gradient on primary button, overriding potential conflicts */
    .btn-primary {
        background-image: linear-gradient(to right, var(--gradient-start), var(--gradient-mid), var(--gradient-end)) !important;
        background-size: 200% auto !important;
        color: white !important;
        border: none !important;
    }
    .btn-primary:hover {
        background-position: right center !important;
    }
</style>

<!-- Top section, title, and action container -->
<!-- FIXED: Using dirname(__DIR__) to robustly get the parent directory -->
<?php include dirname(__DIR__) . '/partials/dashboard_header.php'; ?>

<!-- Content placeholders (metrics, sales list) and static guides -->
<?php include dirname(__DIR__) . '/partials/sales_list.php'; ?>

<!-- All modals (Edit, View, Schedule, Stop, Cancel, Share, Release Funds) -->
<?php include dirname(__DIR__) . '/partials/modals.php'; ?>


<!-- JS Data Bridge & Oracle -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/5.7.2/ethers.umd.min.js"></script>
<script>
    // These variables are now sanitized against U+2028/U+2029 line breaks
    const dashboardData = <?php echo $dashboard_data_json; ?>;
    const projectData = <?php echo $project_data_for_js; ?>;

    /**
     * SILICON VALLEY ORACLE: SELF-HEALING DASHBOARD
     * Automatically corrects DB status if Blockchain status differs.
     */
    document.addEventListener('DOMContentLoaded', async () => {
        if (!window.ethereum || !dashboardData?.active_project_details?.sales) return;
        
        try {
            const provider = new ethers.providers.Web3Provider(window.ethereum);
            const sales = dashboardData.active_project_details.sales;
            const BASE_CHAIN_ID = 8453;
            
            // Only check if we are on the correct network to avoid RPC errors
            const net = await provider.getNetwork();
            if(net.chainId !== BASE_CHAIN_ID) return;

            const ORACLE_ABI = [
                "function isFinalized() view returns (bool)",
                "function deadline() view returns (uint256)",
                "function totalRaised() view returns (uint256)",
                "function goal() view returns (uint256)"
            ];

            for (const sale of sales) {
                // Only verify active/contract-based sales
                if (sale.contract_address && sale.status !== 'ended_successful' && sale.status !== 'ended_failed') {
                    try {
                        const c = new ethers.Contract(sale.contract_address, ORACLE_ABI, provider);
                        
                        // Parallel Fetch
                        const [finalized, deadline, raised, goal] = await Promise.all([
                            c.isFinalized(), c.deadline(), c.totalRaised(), c.goal()
                        ]);
                        
                        const now = Math.floor(Date.now()/1000);
                        let chainStatus = 'live'; // Default assumption
                        
                        if (finalized) {
                            chainStatus = 'ended_successful';
                        } else if (now > deadline) {
                            // Deadline passed - check goal
                            chainStatus = raised.gte(goal) ? 'ended_successful' : 'ended_failed';
                        }
                        
                        // HEALING LOGIC: If DB mismatch, force sync
                        // Note: We don't sync 'live' because DB might be 'scheduled', which is fine. 
                        // We primarily sync FINAL states.
                        if ((chainStatus === 'ended_successful' || chainStatus === 'ended_failed') && sale.status !== chainStatus) {
                            console.log(`[Oracle] Discrepancy Found for ${sale.sale_name}. Chain: ${chainStatus}, DB: ${sale.status}. Healing...`);
                            
                            await fetch('/backend/dashboard_backend.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({
                                    action: 'sync_status',
                                    sale_id: sale.id,
                                    chain_status: chainStatus
                                })
                            });
                            // Optional: Reload to show correct state immediately
                            // window.location.reload(); 
                        }
                    } catch(e) { 
                        console.warn(`[Oracle] Skip ${sale.sale_name}:`, e.message); 
                    }
                }
            }
        } catch(e) { console.error("[Oracle] Init Error:", e); }
    });
</script>

<!-- Dashboard Logic (Now includes all event handlers and API calls) -->
<script src="/js/dashboard.js?v=1.13"></script>