<?php
/**
 * Page: Token Name Setup
 * Filepath: /pages/token_name.php
 */

// --- DEBUGGING: Force display of errors. Remove these lines in production. ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- SETUP & DEPENDENCIES ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pdo = require __DIR__ . '/../src/db.php';

// Include the centralized wizard navigation component
require_once __DIR__ . '/../wizard_nav.php';

// --- STATE DEFINITION ---
$current_main_step = 'tokenomics';
$current_sub_step = 'token_name';
$founder_id = $_SESSION['user_id'] ?? null;
$project_id = $_SESSION['active_project_id'] ?? null;


// --- DATA FETCHING & ERROR HANDLING ---
$errorMessage = null;
if (!$project_id || !$founder_id) {
    $errorMessage = "No active project is selected. Please return to your <a href='/dashboard' class='text-purple-700 underline'>dashboard</a> and select a project to continue.";
} else {
    $form_data = $_SESSION['form_data'] ?? [];
    $form_errors = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_data'], $_SESSION['form_errors']);

    $projectInfo = null;
    try {
        $stmt = $pdo->prepare("SELECT token_name, token_ticker, token_logo_path FROM projet WHERE id = ? AND founder_id = ?");
        $stmt->execute([$project_id, $founder_id]);
        $projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching project info for token_name.php: " . $e->getMessage());
        $errorMessage = "Could not load project data.";
    }

    $token_name = $form_data['token_name'] ?? $projectInfo['token_name'] ?? '';
    $token_ticker = $form_data['token_ticker'] ?? $projectInfo['token_ticker'] ?? '';
}
?>
<!-- MODIFIED: Simplified single-background layout -->
<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-slate-100">
    <div class="w-full max-w-5xl">
        <?php if ($errorMessage): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md" role="alert">
                <p class="font-bold">Action Required</p>
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php else: ?>
        
        <!-- DYNAMIC MAIN STEPPER -->
        <?php render_main_stepper($current_main_step); ?>

        <div class="w-full max-w-3xl mx-auto">
            <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl border border-gray-200">
                
                <!-- DYNAMIC SUB-STEPPER -->
                <?php render_sub_stepper($current_main_step, $current_sub_step); ?>

                <h1 class="text-2xl font-bold text-slate-900 mb-2">Define your Token Name</h1>
                <p class="text-slate-600 text-sm mb-8">
                    Choose your token's name, symbol (ticker), and logo. These should clearly represent your project.
                </p>

                <?php if (isset($form_errors['global'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Error:</strong>
                        <span class="block sm:inline"><?php echo htmlspecialchars($form_errors['global']); ?></span>
                    </div>
                <?php endif; ?>

                <form id="token-name-form" method="post" action="/backend/token_name_backend.php" enctype="multipart/form-data">
                    <input type="hidden" name="projet_id" value="<?php echo htmlspecialchars($project_id ?? ''); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="token_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Token Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="token_name" name="token_name" class="form-input" placeholder="e.g., Tookle Token" value="<?php echo htmlspecialchars($token_name); ?>" required>
                            <?php if (isset($form_errors['token_name'])): ?>
                                <p class="text-red-600 text-sm mt-1"><?php echo htmlspecialchars($form_errors['token_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="token_ticker" class="block text-sm font-medium text-gray-700 mb-1">
                                Token Ticker <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="token_ticker" name="token_ticker" class="form-input" placeholder="e.g., TKL" value="<?php echo htmlspecialchars($token_ticker); ?>" required>
                            <?php if (isset($form_errors['token_ticker'])): ?>
                                <p class="text-red-600 text-sm mt-1"><?php echo htmlspecialchars($form_errors['token_ticker']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Token Logo</label>
                        <div id="logo-dropzone" class="dropzone p-6 text-center cursor-pointer relative bg-white">
                           <div id="logo-preview-container" class="mb-4">
                               <?php if (!empty($projectInfo['token_logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $projectInfo['token_logo_path'])): ?>
                                   <img src="<?php echo htmlspecialchars($projectInfo['token_logo_path']); ?>" alt="Current Logo" class="max-h-24 mx-auto rounded-md">
                               <?php else: ?>
                                    <div class="icon-container flex justify-center mb-2"> <i data-lucide="upload-cloud" class="w-10 h-10 text-gray-400"></i> </div>
                                    <p class="text-sm text-gray-600"><span class="font-semibold text-purple-700">Click to upload</span> or drag and drop</p>
                                    <p class="text-xs text-gray-500 mt-1">SVG, PNG, JPG (MAX. 2MB)</p>
                               <?php endif; ?>
                           </div>
                           <input type="file" id="logo_upload" name="logo_upload" class="hidden" accept="image/*"/>
                        </div>
                        <?php if (isset($form_errors['logo_upload'])): ?>
                           <p id="logo-error" class="text-red-600 text-sm mt-2"><?php echo htmlspecialchars($form_errors['logo_upload']); ?></p>
                        <?php else: ?>
                           <div id="logo-error" class="text-red-600 text-sm mt-2"></div>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between items-center mt-8 pt-6 border-t">
                        <a href="/setup" class="text-slate-600 font-semibold py-2 px-4 rounded-lg hover:bg-slate-200 transition-all">Back</a>
                        <button type="submit" class="btn btn-primary">Save & Continue</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
    .form-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
    .dropzone { border: 2px dashed #d1d5db; transition: background-color 0.2s ease; border-radius: 0.5rem; }
    .dropzone:hover { background-color: #f3f4f6; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const dropzone = document.getElementById('logo-dropzone');
    if (dropzone) {
        const fileInput = document.getElementById('logo_upload');
        const previewContainer = document.getElementById('logo-preview-container');
        const logoError = document.getElementById('logo-error');
        const MAX_SIZE = 2 * 1024 * 1024; // 2MB

        const defaultPreviewHTML = `
            <div class="icon-container flex justify-center mb-2"> <i data-lucide="upload-cloud" class="w-10 h-10 text-gray-400"></i> </div>
            <p class="text-sm text-gray-600"><span class="font-semibold text-purple-700">Click to upload</span> or drag and drop</p>
            <p class="text-xs text-gray-500 mt-1">SVG, PNG, JPG (MAX. 2MB)</p>`;

        const validateFile = (file) => {
            if (!file) { logoError.textContent = ''; return true; }
            if (file.size > MAX_SIZE) {
                logoError.textContent = 'File is too large. Maximum size is 2MB.';
                fileInput.value = ''; 
                previewContainer.innerHTML = defaultPreviewHTML;
                lucide.createIcons();
                return false;
            }
            logoError.textContent = ''; return true;
        };
        
        dropzone.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && validateFile(file)) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewContainer.innerHTML = `<img src="${event.target.result}" alt="Logo Preview" class="max-h-24 mx-auto rounded-md">`;
                }
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>