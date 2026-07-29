<?php
 /**
  * pages/receivingwallet.php
  *
  * This file handles both the investment flow and direct editing from the dashboard.
  */
 require_once 'src/db.php';
 require_once 'src/session.php';
 start_secure_session();
 
 $user_id_for_query = $_SESSION['user_id'];
 $user_info = null;
 
 // Determine the context: are we in the investment flow or editing from the dashboard?
 $is_edit_mode = (strpos($_SERVER['REQUEST_URI'], 'edit-wallet') !== false);
 
 // --- Get investment_id from SESSION for edit mode ---
 $investment_id = null;
 $project_uuid = null; // Initialize project ID
 
 if ($is_edit_mode) {
     // 1. Try reading from secure session (set by receivingwallet_edit_backend.php)
     if (isset($_SESSION['selected_investment_id_for_edit'])) {
         $investment_id = $_SESSION['selected_investment_id_for_edit'];
         // CRITICAL: Unset the session variable immediately after reading it
         unset($_SESSION['selected_investment_id_for_edit']);
     } 
     // 2. Fallback: Try reading from URL (GET) if session failed or direct link used
     elseif (isset($_GET['investment_id'])) {
         $investment_id = filter_input(INPUT_GET, 'investment_id', FILTER_VALIDATE_INT);
     }
     // 3. Fallback: Project context only
     else {
         $project_uuid = $_SESSION['active_project_id'] ?? null;
     }
 } else {
     // In the standard investment flow, use the ID stored in the session during purchase
     $investment_id = $_SESSION['current_investment_id'] ?? null;
 }
 // --- End Investment ID logic ---
 
 
 $wallets = [];
 $fetch_error = null;
 $fetched_project_name = 'Project';
 $fetched_sale_name = ''; // NEW: Variable for sale name
 $project_network = null;
 $existing_wallet_data = null;
 $user_projects = []; // To hold projects for the selection dropdown
 $networks = ['Ethereum', 'Base', 'Polygon', 'Solana', 'Other']; // Define available networks
 
 // --- AUTHORIZATION CHECK & DATA FETCHING ---
 if ($pdo instanceof PDO) {
     $stmt_user = $pdo->prepare("SELECT first_name, last_name, email FROM user WHERE id = :user_id");
     $stmt_user->execute([':user_id' => $user_id_for_query]);
     $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
      if ($user_info) {
         $_SESSION['user_name'] = trim(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''));
         $_SESSION['user_email'] = $user_info['email'] ?? '';
     }
 
     // Handle case where user lands on /edit-wallet without context
     if ($is_edit_mode && empty($investment_id) && empty($project_uuid)) {
         $stmt_user_projects = $pdo->prepare(
             "SELECT DISTINCT p.id, p.project_name
              FROM projet p
              JOIN investments i ON p.id = i.project_id
              WHERE i.user_id = :user_id
              ORDER BY p.project_name"
         );
         $stmt_user_projects->execute([':user_id' => $user_id_for_query]);
         $user_projects = $stmt_user_projects->fetchAll(PDO::FETCH_ASSOC);
         if (empty($user_projects)) {
              $fetch_error = "You have not invested in any projects yet.";
         }
     // Handle missing ID in standard investment flow
     } else if (!$is_edit_mode && empty($investment_id)) {
         $fetch_error = "No active investment found. Please start the investment process again.";
     }
 
     // Proceed if we have an investment ID or a project UUID (for project selection fallback)
     if (!$fetch_error && ($investment_id || $project_uuid)) {
         try {
             // AUTHORIZATION CHECK (Combined with data fetching)
             if ($investment_id) {
                 // Fetch investment data AND verify ownership in one query
                 // UPDATED: Added i.sale_name and i.investment_round to selection
                 $stmt_check_ownership = $pdo->prepare(
                    "SELECT i.project_id, i.investor_wallet_address, i.sale_name, i.investment_round, p.project_name, uw.network, uw.label
                     FROM investments i
                     JOIN projet p ON i.project_id = p.id
                     LEFT JOIN user_wallet uw ON i.investor_wallet_address = uw.wallet_address AND uw.user_id = i.user_id
                     WHERE i.id = :investment_id AND i.user_id = :user_id"
                 );
                 $stmt_check_ownership->execute([
                     ':investment_id' => $investment_id,
                     ':user_id' => $user_id_for_query
                 ]);
                 $investment_data = $stmt_check_ownership->fetch(PDO::FETCH_ASSOC);
 
                 if ($investment_data) {
                     $project_uuid = $investment_data['project_id'];
                     $fetched_project_name = $investment_data['project_name'];
                     // Set Sale Name (prefer sale_name, fallback to round)
                     $fetched_sale_name = !empty($investment_data['sale_name']) ? $investment_data['sale_name'] : ($investment_data['investment_round'] ?? '');
                     
                     // Ensure all keys exist, even if null, for reliable form population
                     $existing_wallet_data = [
                          'investor_wallet_address' => $investment_data['investor_wallet_address'] ?? null,
                          'network' => $investment_data['network'] ?? null,
                          'label' => $investment_data['label'] ?? null
                     ];
                 } else {
                     $investment_id = null; // Clear invalid ID
                     throw new Exception("Access Denied: You do not have permission to view or edit this investment.");
                 }
             } else if ($is_edit_mode && $project_uuid) {
                  // Fetch project name if only project UUID is known
                  $stmt_proj_name = $pdo->prepare("SELECT project_name FROM projet WHERE id = :uuid");
                  $stmt_proj_name->execute([':uuid' => $project_uuid]);
                  if($proj_data = $stmt_proj_name->fetch()) {
                     $fetched_project_name = $proj_data['project_name'];
                  } else {
                     $project_uuid = null; // Clear invalid project ID
                     $fetch_error = "Selected project not found.";
                  }
             }
 
             // Fetch user's general saved wallets (always safe) - only if no error so far
             if (!$fetch_error) {
                 $stmt_wallets = $pdo->prepare("SELECT id, wallet_address, network, label FROM user_wallet WHERE user_id = :uid ORDER BY label, id");
                 $stmt_wallets->execute([':uid' => $user_id_for_query]);
                 $wallets = $stmt_wallets->fetchAll(PDO::FETCH_ASSOC);
             }
 
             // Fetch required project network if project_uuid is known and valid - only if no error so far
             if(!$fetch_error && $project_uuid) {
                 $stmt_proj_net = $pdo->prepare("SELECT network FROM project_wallet WHERE projet_id = :uuid LIMIT 1");
                 $stmt_proj_net->execute([':uuid' => $project_uuid]);
                 if ($row = $stmt_proj_net->fetch(PDO::FETCH_ASSOC)) {
                     $project_network = $row['network'];
                 }
             }
 
         } catch (Exception $e) {
             $fetch_error = $e->getMessage();
             error_log("Error in receivingwallet.php data fetch: " . $e->getMessage());
         }
     }
 } else {
     $fetch_error = "Database connection object was not established.";
 }
 
 $page_title = $is_edit_mode ? "Configure Your Wallet for " . htmlspecialchars($fetched_project_name) : "Invest in " . htmlspecialchars($fetched_project_name ?? 'Project');
 ?>
 
 <!-- NO LAYOUT INCLUDE NEEDED HERE - layout.php handles it -->
 <!DOCTYPE html>
 <html lang="en">
 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title><?php echo htmlspecialchars($page_title); ?> - Tookle</title>
     <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
     <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
     <script src="https://unpkg.com/lucide@latest"></script>
     <style>
         body { font-family: 'Montserrat', sans-serif; background-color: #f8fafc; }
         .btn-investor-gradient { background-image: linear-gradient(to right, #6D28D9, #06B6D4); color: white; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); padding: 0.65rem 1.5rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.875rem; border: none; }
         .btn-investor-gradient:hover { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); filter: brightness(1.1); }
         .saved-wallet-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 0.75rem; cursor: pointer; transition: border-color 0.2s; }
         .saved-wallet-item:hover { border-color: #a78bfa; }
         .saved-wallet-item.selected { border-color: #6D28D9; background-color: #f3e8ff; border-width: 2px; padding: calc(0.75rem - 1px) calc(1rem - 1px); }
         .tooltip { position: relative; display: inline-block; cursor: pointer; }
         .tooltip .tooltiptext { visibility: hidden; width: 160px; background-color: #374151; color: #fff; text-align: center; border-radius: 6px; padding: 5px 0; position: absolute; z-index: 1; bottom: 125%; left: 50%; margin-left: -80px; opacity: 0; transition: opacity 0.3s; font-size: 0.75rem; }
         .tooltip .tooltiptext::after { content: ""; position: absolute; top: 100%; left: 50%; margin-left: -5px; border-width: 5px; border-style: solid; border-color: #374151 transparent transparent transparent; }
         .tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }
         /* Style to de-emphasize saved wallets in edit mode */
         .edit-mode .saved-wallets-section { opacity: 0.7; /* Make it slightly faded */ }
         .edit-mode .saved-wallets-section h2 { font-size: 1.125rem; /* Slightly smaller title */ }
     </style>
 </head>
 <body class="<?php echo $is_edit_mode ? 'edit-mode' : ''; ?>">
     <!-- Main Content Area - This will be injected into layout.php -->
     <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
         <!-- Page Header -->
         <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($page_title); ?></h1>
         <!-- NEW: Subtitle with Sale Name -->
         <?php if (!empty($fetched_sale_name)): ?>
            <p class="text-sm font-medium text-purple-600 mb-8 uppercase tracking-wide">
                Private Sale: <?php echo htmlspecialchars($fetched_sale_name); ?>
            </p>
         <?php else: ?>
            <div class="mb-8"></div>
         <?php endif; ?>
 
         <div class="max-w-3xl mx-auto">
             <?php if ($fetch_error): ?>
                 <div class="mb-8 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md">
                     <p><?php echo htmlspecialchars($fetch_error); ?></p>
                      <?php if (strpos($fetch_error, "Access Denied") !== false || strpos($fetch_error, "No active investment") !== false): ?>
                      <div class="mt-4">
                          <a href="<?= get_url('portfolio') ?>" class="inline-flex items-center justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                              Return to Portfolio
                          </a>
                      </div>
                      <?php endif; ?>
                 </div>
             <?php endif; ?>
 
             <?php if ($is_edit_mode && empty($investment_id) && empty($project_uuid) && !empty($user_projects)): ?>
                 <!-- Project Selector for Edit Mode Fallback -->
                 <div class="bg-white p-6 sm:p-8 rounded-lg border border-gray-200 shadow-lg">
                     <h2 class="text-xl font-semibold text-gray-800 mb-4">Select Project</h2>
                     <p class="text-sm text-gray-600 mb-4">Please select the project for which you want to configure the wallet.</p>
                     <form method="POST"> <!-- Keep POST for project selection -->
                         <select name="project_selection_id" class="block w-full px-3 py-2 border border-gray-300 rounded-md mb-4" required>
                             <option value="">-- Choose a project --</option>
                             <?php foreach ($user_projects as $proj): ?>
                                 <option value="<?php echo htmlspecialchars($proj['id']); ?>"><?php echo htmlspecialchars($proj['project_name']); ?></option>
                             <?php endforeach; ?>
                         </select>
                         <button type="submit" class="btn-investor-gradient">Load Project Wallets</button>
                     </form>
                 </div>
             <?php elseif (!$fetch_error): // Only show form if no fetch error occurred (or if it was just 'no projects') ?>
                 <!-- Stepper (only if not edit mode) -->
                 <?php if (!$is_edit_mode): ?>
                 <nav aria-label="Progress" class="mb-10">
                     <ol role="list" class="flex items-center">
                         <li class="relative pr-8 sm:pr-20"><div class="absolute inset-0 flex items-center" aria-hidden="true"><div class="h-0.5 w-full bg-purple-600"></div></div><a href="<?= get_url('purchase') ?>" class="relative flex h-8 w-8 items-center justify-center rounded-full bg-purple-600 hover:bg-purple-900"><i data-lucide="check" class="h-5 w-5 text-white"></i><span class="sr-only">Amount</span></a></li>
                         <li class="relative pr-8 sm:pr-20"><div class="absolute inset-0 flex items-center" aria-hidden="true"><div class="h-0.5 w-full bg-purple-600"></div></div><a href="#" class="relative flex h-8 w-8 items-center justify-center rounded-full border-2 border-purple-600 bg-white" aria-current="step"><span class="h-2.5 w-2.5 rounded-full bg-purple-600"></span><span class="sr-only">Wallet</span></a></li>
                         <li class="relative"><div class="absolute inset-0 flex items-center" aria-hidden="true"><div class="h-0.5 w-full bg-gray-200"></div></div><a href="#" class="group relative flex h-8 w-8 items-center justify-center rounded-full border-2 border-gray-300 bg-white hover:border-gray-400"><span class="h-2.5 w-2.5 rounded-full bg-transparent group-hover:bg-gray-300"></span><span class="sr-only">Payment</span></a></li>
                     </ol>
                 </nav>
                 <?php endif; ?>
 
                 <!-- Wallet Form Container -->
                 <div class="bg-white p-6 sm:p-8 rounded-lg border border-gray-200 shadow-lg <?php echo $is_edit_mode ? '' : 'mt-10'; ?>">
                      <!-- Card 1: Select Saved Wallet -->
                     <?php if (!empty($wallets)): ?>
                         <div class="mb-8 pb-6 border-b border-gray-200 saved-wallets-section">
                             <h2 class="text-xl font-semibold text-gray-800 mb-4">Select a Saved Wallet</h2>
                             <p class="text-sm text-gray-600 mb-4">Click a wallet to auto-fill the details<?php echo $is_edit_mode ? ' below, or simply modify the current details directly' : ''; ?>.</p>
                             <div id="saved-wallets-list">
                                 <?php foreach ($wallets as $wallet): ?>
                                 <div class="saved-wallet-item"
                                      data-id="<?php echo htmlspecialchars($wallet['id']); ?>"
                                      data-address="<?php echo htmlspecialchars($wallet['wallet_address']); ?>"
                                      data-network="<?php echo htmlspecialchars($wallet['network']); ?>"
                                      data-label="<?php echo htmlspecialchars($wallet['label']); ?>">
                                     <div>
                                         <p class="font-medium text-sm text-gray-900"><?php echo htmlspecialchars($wallet['label']); ?></p>
                                         <p class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($wallet['wallet_address']); ?></p>
                                         <p class="text-xs text-gray-500"><?php echo htmlspecialchars($wallet['network']); ?></p>
                                     </div>
                                     <i data-lucide="chevron-right" class="w-5 h-5 text-gray-400"></i>
                                 </div>
                                 <?php endforeach; ?>
                             </div>
                         </div>
                     <?php endif; ?>
 
                     <!-- Card 2: Fill Wallet Details -->
                     <form id="walletForm">
                         <!-- Hidden fields remain the same -->
                         <input type="hidden" name="investment_id" id="investment_id" value="<?php echo htmlspecialchars($investment_id ?? ''); ?>">
                         <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_uuid ?? ''); ?>">
                         <input type="hidden" name="user_wallet_id" id="user_wallet_id" value="">
                          <?php if ($is_edit_mode): ?>
                             <input type="hidden" name="redirect_url" value="/backerdashboard">
                         <?php endif; ?>
 
                         <h2 class="text-xl font-semibold text-gray-800 mb-2">Wallet Details</h2>
                         <p class="text-sm text-gray-600 mb-6"><?php echo $is_edit_mode ? 'Review or modify the wallet details for this investment.' : 'Enter details for a new or existing wallet.'; ?></p>
 
                         <div class="space-y-5">
                             <div>
                                 <label for="wallet_address" class="block text-sm font-medium text-gray-700 mb-1">Wallet Address <span class="text-red-600">*</span></label>
                                 <input type="text" name="wallet_address" id="wallet_address" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm" placeholder="0x..." value="<?php echo htmlspecialchars($existing_wallet_data['investor_wallet_address'] ?? ''); ?>" required>
                             </div>
 
                             <!-- Network Dropdown -->
                             <div>
                                 <label for="network" class="block text-sm font-medium text-gray-700 mb-1">Network <span class="text-red-600">*</span></label>
                                 <select id="network" name="network" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm" required>
                                     <option value="">Select a network</option>
                                     <?php
                                     // Use null coalescing directly here for cleaner code
                                     $selectedNetwork = $existing_wallet_data['network'] ?? '';
                                     foreach ($networks as $net):
                                         $selected = ($net === $selectedNetwork) ? 'selected' : '';
                                     ?>
                                         <option value="<?php echo htmlspecialchars($net); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($net); ?></option>
                                     <?php endforeach; ?>
                                 </select>
                                 <?php if ($project_network): ?>
                                     <p class="mt-1 text-xs text-gray-500">Recommended network for this project: <strong><?php echo htmlspecialchars($project_network); ?></strong></p>
                                 <?php endif; ?>
                             </div>
 
                             <!-- Label Input -->
                             <div>
                                 <label for="label" class="block text-sm font-medium text-gray-700 mb-1">Wallet Name / Label <span class="text-red-600">*</span>
                                     <span class="tooltip ml-1"><i data-lucide="help-circle" class="w-4 h-4 inline text-gray-400"></i><span class="tooltiptext">Give your wallet a recognizable name (e.g., My MetaMask, Ledger Investment).</span></span>
                                 </label>
                                 <input type="text" name="label" id="label" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 sm:text-sm" placeholder="e.g., My Investment Wallet" value="<?php echo htmlspecialchars($existing_wallet_data['label'] ?? ''); ?>" required>
                             </div>
                         </div>
 
                         <div class="mt-10 pt-6 border-t border-gray-200 flex <?php echo $is_edit_mode ? 'justify-start' : 'justify-between'; ?> items-center gap-x-4">
                              <?php if ($is_edit_mode): ?>
                                 <a href="<?= get_url('backerdashboard') ?>" class="inline-flex items-center px-6 py-2.5 border border-gray-300 text-sm font-semibold rounded-lg bg-white hover:bg-gray-50">Cancel</a>
                                 <button type="submit" id="submit-btn" class="btn-investor-gradient">Save Wallet</button>
                              <?php else: ?>
                                  <a href="<?= get_url('purchase') ?>" class="inline-flex items-center px-6 py-2.5 border border-gray-300 text-sm font-semibold rounded-lg bg-white hover:bg-gray-50">Previous</a>
                                  <button type="submit" id="submit-btn" class="btn-investor-gradient">Save Wallet & Continue</button>
                              <?php endif; ?>
                         </div>
                     </form>
                 </div>
             <?php endif; ?>
         </div>
     </div>
 
     <!-- Footer -->
     <footer class="bg-white border-t border-gray-200 mt-auto">
         <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
             <p class="text-center text-sm text-gray-500">&copy; <?php echo date("Y"); ?> Tookle. All rights reserved.</p>
         </div>
     </footer>
 
     <!-- JavaScript -->
     <script>
         // Function to select a saved wallet and populate the form
         function selectWallet(id, address, network, label) {
             console.log("selectWallet called with:", { id, address, network, label }); // Debug log
 
             // Target the form fields directly by their IDs
             const addressInput = document.getElementById('wallet_address');
             const networkSelect = document.getElementById('network');
             const labelInput = document.getElementById('label');
             const hiddenIdInput = document.getElementById('user_wallet_id');
 
             // Check if elements exist before setting values
             if (addressInput) {
                 addressInput.value = address || '';
             }
 
             if (networkSelect) {
                 networkSelect.value = network || '';
             }
 
             if (labelInput) {
                 labelInput.value = label || '';
             }
 
             if (hiddenIdInput) {
                 hiddenIdInput.value = id || '';
             }
 
             // Highlight selected wallet visually
             document.querySelectorAll('.saved-wallet-item').forEach(item => {
                 item.classList.remove('selected');
                 // Check if the current item's data-id matches the clicked one
                 if (item.dataset.id === id) {
                     item.classList.add('selected');
                 }
             });
         }
 
         // Function to clear form and selection (now just visually deselects)
         function deselectSavedWallet() {
              document.querySelectorAll('.saved-wallet-item').forEach(item => item.classList.remove('selected'));
              document.getElementById('user_wallet_id').value = ''; // Clear ID so backend knows it might be modified/new
         }
 
         document.addEventListener('DOMContentLoaded', function() {
             lucide.createIcons();
             const walletForm = document.getElementById('walletForm');
             const savedWalletsList = document.getElementById('saved-wallets-list');
 
             // Add click listeners to saved wallet items
             if (savedWalletsList) {
                 savedWalletsList.addEventListener('click', function(event) {
                     const item = event.target.closest('.saved-wallet-item');
                     if (item && item.dataset.id) { // Check if item and data-id exist
                         // Pass the data attributes directly to the function
                         selectWallet(
                             item.dataset.id,
                             item.dataset.address,
                             item.dataset.network,
                             item.dataset.label
                         );
                     }
                 });
             }
 
             // Deselect saved wallet visually if user starts editing details manually
             const formInputs = walletForm?.querySelectorAll('input[type="text"], select');
             formInputs?.forEach(input => {
                 input.addEventListener('input', () => {
                      // Check if a saved wallet ID is currently set
                      if (document.getElementById('user_wallet_id').value !== '') {
                          deselectSavedWallet();
                      }
                 });
             });
 
             // Handle Form Submission
             if (walletForm) {
                 walletForm.addEventListener('submit', async function(event) {
                     event.preventDefault();
                     const form = event.target;
                     const button = document.getElementById('submit-btn');
                     const originalButtonText = button.textContent;
                     button.disabled = true;
                     button.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...`;
 
                     try {
                         const response = await fetch('/backend/receivingwallet_backend.php', {
                             method: 'POST',
                             body: new FormData(form)
                         });
                         const result = await response.json();
                         if (result.success) {
                             // Redirect based on the backend response
                             window.location.href = result.redirect_url || `/payment`; // Default to payment page if not specified
                         } else {
                             alert('Error: ' + result.message);
                             button.disabled = false;
                             button.textContent = originalButtonText;
                         }
                     } catch (error) {
                         alert('An unexpected error occurred. Please try again.');
                         console.error('Save wallet error:', error);
                         button.disabled = false;
                         button.textContent = originalButtonText;
                     }
                 });
             }
         });
     </script>
 </body>
 </html>