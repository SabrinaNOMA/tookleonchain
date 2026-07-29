<?php 
// 1. Get Project Details
$p = $response['active_project_details'] ?? [];
$sales = $p['sales'] ?? [];
$has_sales = !empty($sales);

// 2. Define Setup Flags (Strictly match Router Logic)
$is_described = !empty($p['project_described']);
$is_tokenomics = !empty($p['tokenomics_done']);
$is_story = !empty($p['token_sale_page_ready']);

// Determine if the "Wizard" is fully complete
$is_setup_complete = ($is_described && $is_tokenomics && $is_story);

// 3. Status Logic for Kanban Highlighting
// Step 1
$step1_status = $is_described ? 'completed' : 'inprogress';

// Step 2 (Only active if Step 1 is done)
$step2_status = 'todo';
if ($is_described) {
    $step2_status = $is_tokenomics ? 'completed' : 'inprogress';
}

// Step 3 (Only active if Step 1 & 2 are done)
$step3_status = 'todo';
if ($is_described && $is_tokenomics) {
    $step3_status = $is_story ? 'completed' : 'inprogress';
}

// 4. CSS Helpers for Standard Look
function get_standard_card_class($status) {
    if ($status === 'completed') {
        // White bg, left border (trait), normal border elsewhere
        return 'bg-white border border-gray-200 border-l-4 border-l-[var(--theme-primary)]';
    }
    if ($status === 'inprogress') {
        // Active step - slight shadow, perhaps a subtle border highlight
        return 'bg-white border border-indigo-300 shadow-sm';
    }
    // "Locked" or future step
    return 'bg-gray-50 border border-gray-100 opacity-60';
}

function get_standard_icon_class($status) {
    if ($status === 'completed') return 'text-[var(--theme-primary)]';
    if ($status === 'inprogress') return 'text-indigo-600';
    return 'text-gray-400';
}
?>

<!-- LOGIC: Only Show Metrics if Setup is COMPLETE (Operational Phase) -->
<?php if ($is_setup_complete): ?>
    <div id="key-metrics-container" class="mb-8"></div>
<?php endif; ?>

<!-- LOGIC: Show Kanban if Setup is INCOMPLETE (Design Phase) -->
<!-- This strictly enforces the router logic: if wizard isn't done, you stay here. -->
<?php if (!$is_setup_complete): ?>
    
    <div id="php-design-kanban-wrapper">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Design Phase</h2>
            <p class="text-gray-500 mt-1 text-sm">Prepare your private sale for execution. Define your project, structure your funding plan, and prepare your private sale room.</p>
            
            <!-- Encouraging Message based on current active step -->
            <?php if ($step1_status === 'inprogress'): ?>
                <div class="mt-4 p-3 bg-indigo-50 text-indigo-700 rounded-md text-sm flex items-center">
                    <i data-lucide="sparkles" class="w-4 h-4 mr-2"></i>
                    <span>Let's get started! Define your project's core identity to begin.</span>
                </div>
            <?php elseif ($step2_status === 'inprogress'): ?>
                <div class="mt-4 p-3 bg-indigo-50 text-indigo-700 rounded-md text-sm flex items-center">
                    <i data-lucide="trending-up" class="w-4 h-4 mr-2"></i>
                    <span>Great start! Now, let's structure your tokenomics and funding rounds.</span>
                </div>
            <?php elseif ($step3_status === 'inprogress'): ?>
                <div class="mt-4 p-3 bg-indigo-50 text-indigo-700 rounded-md text-sm flex items-center">
                    <i data-lucide="pen-tool" class="w-4 h-4 mr-2"></i>
                    <span>Almost there! Craft your story to compel investors.</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- STANDARD GRID LAYOUT -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            
            <!-- CARD 1: Project Overview -->
            <a href="/setup" class="block h-full p-6 rounded-lg transition-all duration-200 hover:shadow-md <?php echo get_standard_card_class($step1_status); ?>">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold uppercase tracking-widest text-gray-500">Phase 1 - Step 1</span>
                    <?php if ($step1_status === 'completed'): ?>
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-50">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-[var(--theme-primary)]"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Project Overview</h3>
                <p class="text-sm font-medium text-gray-700 mb-1">Define the problem you address and your solution.</p>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">Specify your industry focus, category, and the role of the token within the product.</p>
                <div class="mt-auto flex items-center text-sm font-semibold <?php echo get_standard_icon_class($step1_status); ?>">
                    <?php echo ($step1_status === 'completed' ? 'Completed' : 'Configure Overview &rarr;'); ?>
                </div>
            </a>

            <!-- CARD 2: Funding Plan -->
            <a href="/tokenname" class="block h-full p-6 rounded-lg transition-all duration-200 hover:shadow-md <?php echo get_standard_card_class($step2_status); ?>">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold uppercase tracking-widest text-gray-500">Phase 1 - Step 2</span>
                    <?php if ($step2_status === 'completed'): ?>
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-50">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-[var(--theme-primary)]"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Funding Plan</h3>
                <p class="text-sm font-medium text-gray-700 mb-1">Structure your private raise.</p>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">Define amounts, rounds, pricing, and token economics.</p>
                <div class="mt-auto flex items-center text-sm font-semibold <?php echo get_standard_icon_class($step2_status); ?>">
                    <?php 
                    if ($step2_status === 'completed') echo 'Completed';
                    elseif ($step2_status === 'inprogress') echo 'Define Plan &rarr;';
                    else echo 'Pending Step 1';
                    ?>
                </div>
            </a>

            <!-- CARD 3: Private Sale Room -->
            <a href="/story" class="block h-full p-6 rounded-lg transition-all duration-200 hover:shadow-md <?php echo get_standard_card_class($step3_status); ?>">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-xs font-bold uppercase tracking-widest text-gray-500">Phase 1 - Step 3</span>
                    <?php if ($step3_status === 'completed'): ?>
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-50">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-[var(--theme-primary)]"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Your Story</h3>
                <p class="text-sm font-medium text-gray-700 mb-1">Create your private sale space for investors.</p>
                <p class="text-sm text-gray-500 leading-relaxed mb-6">Define terms, access, and deal information in a dedicated private environment.</p>
                <div class="mt-auto flex items-center text-sm font-semibold <?php echo get_standard_icon_class($step3_status); ?>">
                    <?php 
                    if ($step3_status === 'completed') echo 'Completed';
                    elseif ($step3_status === 'inprogress') echo 'Create Room &rarr;';
                    else echo 'Pending Step 2';
                    ?>
                </div>
            </a>

        </div>
    </div>

<?php else: ?>
    <!-- LOGIC: Show Operational Dashboard if Setup is COMPLETE -->
    
    <div class="mb-6 flex justify-between items-end">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Operational Phase</h2>
            <p class="text-gray-600 mt-1">Manage your active campaigns and track performance.</p>
        </div>
        <a href="/investors" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Manage Investors &rarr;</a>
    </div>

    <!-- The dashboard.js will target this div to render the sales list -->
    <div id="dashboard-content"></div>

<?php endif; ?>

<!-- Guides Section (Static Bottom Reference) -->
<section id="founder-guides-section" class="mt-12 border-t border-gray-200 pt-8">
    <div class="mb-6">
        <h3 class="text-lg font-bold text-gray-800">Project Status Reference</h3>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
         <div id="lifecycle-design" class="lifecycle-card bg-white border border-gray-200 rounded-xl p-6 shadow-sm transition-all flex flex-col">
            <div class="flex items-center">
                <i data-lucide="clipboard-list" class="w-8 h-8 text-gray-400 mr-4 flex-shrink-0"></i>
                <div>
                    <div class="text-xs text-gray-500 uppercase font-semibold">Phase 1</div>
                    <div class="text-lg font-bold text-gray-800">Design</div>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-2 font-medium text-gray-800">Structure your private sale.</p>
            <p class="text-sm text-gray-500 mt-1 flex-grow">Define your project, funding plan, and private sale room before execution.</p>
         </div>
         
         <div id="lifecycle-operational" class="lifecycle-card bg-white border border-gray-200 rounded-xl p-6 shadow-sm transition-all flex flex-col">
             <div class="flex items-center">
                <i data-lucide="rocket" class="w-8 h-8 text-gray-400 mr-4 flex-shrink-0"></i>
                <div>
                    <div class="text-xs text-gray-500 uppercase font-semibold">Phase 2</div>
                    <div class="text-lg font-bold text-gray-800">Operational</div>
                </div>
            </div>
             <p class="text-sm text-gray-500 mt-2 font-medium text-gray-800">Execute and manage your private sale.</p>
             <p class="text-sm text-gray-500 mt-1 flex-grow">Create and edit private sales, deploy contracts and vaults, manage allocations and distribution, and connect wallets and escrow.</p>
             
             <!-- Example Statuses List -->
             <div class="mt-4 pt-4 border-t border-gray-100">
                <h4 class="text-xs font-semibold text-gray-500 mb-2">EXAMPLE SALE STATUSES:</h4>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between items-center"><span class="text-gray-600">Community Presale</span><span class="font-medium" style="color: var(--color-status-draft);">Draft</span></div>
                    <div class="flex justify-between items-center"><span class="text-gray-600">Friends and Family</span><span class="font-medium" style="color: var(--color-status-live);">Live</span></div>
                    <div class="flex justify-between items-center"><span class="text-gray-600">Private 1</span><span class="font-medium" style="color: var(--color-status-successful);">Successful</span></div>
                    <div class="flex justify-between items-center"><span class="text-gray-600">Private 2 </span><span class="font-medium" style="color: var(--color-status-failed);">Failed</span></div>
                    <div class="flex justify-between items-center"><span class="text-gray-600">Launchpdad</span><span class="font-medium" style="color: var(--color-status-tracking);">External</span></div>

                </div>
            </div>
         </div>
    </div>
</section>