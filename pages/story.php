<?php
/**
 * Page: Configure Token Sale - Step 1: Tell Your Story
 * Filepath: /pages/story.php
 * UPDATED for the new `token_sale_pages` schema.
 */

// --- Refactor: Include the centralized wizard navigation system ---
require_once __DIR__ . '/../wizard_nav.php';

// --- Refactor: Define the current step for the navigation system ---
$current_main_step = 'private_sale';
$current_sub_step = 'story';

$pdo = require __DIR__ . '/../src/db.php';
$project_id = $_SESSION['active_project_id'] ?? null;
$page_error = null;
$dbProjectData = [];

if (empty($project_id)) {
    $page_error = "No active project. Please select a project from your dashboard.";
} else {
    try {
        // Mark tokenomics as done when entering this new flow
        $stmt = $pdo->prepare("UPDATE projet SET tokenomics_done = 1 WHERE id = ?");
        $stmt->execute([$project_id]);

        // --- FIX: Fetch from `token_sale_pages` using the correct `project_id` column ---
        $sql = "SELECT p.project_name, tsp.* FROM projet p LEFT JOIN token_sale_pages tsp ON p.id = tsp.project_id WHERE p.id = :project_id AND p.founder_id = :founder_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':project_id' => $project_id, ':founder_id' => $_SESSION['user_id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $dbProjectData = $data;
        } else {
            // It's possible no token_sale_pages record exists yet, which is fine.
            // We just need the project name.
            $stmtProject = $pdo->prepare("SELECT project_name FROM projet WHERE id = ?");
            $stmtProject->execute([$project_id]);
            $projectName = $stmtProject->fetchColumn();
            if(!$projectName) {
                 $page_error = "Project not found or you do not have permission to access it.";
            } else {
                $dbProjectData['project_name'] = $projectName;
            }
        }
    } catch (PDOException $e) {
        $page_error = "Database error: " . $e->getMessage();
        error_log("pages/story.php DB Error: " . $e->getMessage());
    }
}
?>
<main class="flex-1 p-4 sm:p-8 md:p-12 flex justify-center bg-gray-50">
    <div class="w-full max-w-6xl">

        <?php render_main_stepper($current_main_step); ?>

        <div class="bg-white p-6 md:p-8 rounded-lg shadow border border-gray-200">
            
            <?php render_sub_stepper($current_main_step, $current_sub_step); ?>
            
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Tell your Project's Story</h1>
            <p class="text-gray-600 text-sm mb-8">Provide the core narrative for your project. Showcase your unique value, introduce your team and partners, answer common questions, highlight key metrics, and link your social channels to build a compelling and trustworthy presence.</p>

            <?php if ($page_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($page_error); ?></span>
                </div>
            <?php else: ?>
                <form id="storyForm" action="/backend/story_backend.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project_id); ?>">
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold text-gray-800">Your Project Narrative</h2>
                        <div class="mb-4 mt-4">
                            <label for="project-description" class="form-label">Project Description<span class="text-red-500">*</span></label>
                            <textarea id="project-description" name="project_description" rows="5" class="form-textarea" required><?php echo htmlspecialchars($dbProjectData['project_description_story'] ?? ''); ?></textarea>
                        </div>
                         <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch mt-6">
                            <div>
                                <label class="form-label">Teaser Video</label>
                                <div class="dropzone" id="video_dropzone"><i data-lucide="video" class="w-8 h-8 text-gray-400 mb-2"></i><span class="font-semibold text-sm text-purple-600">Upload File</span><p class="text-xs text-gray-500 mt-1">MP4/WebM, Max 50MB</p><input type="file" name="video_file" class="hidden" accept="video/mp4, video/webm" data-existing-file="<?php echo htmlspecialchars($dbProjectData['video_file_path'] ?? ''); ?>"><div class="existing-file-name"></div></div>
                            </div>
                             <div>
                                <label class="form-label">Hero Image</label>
                                <div class="dropzone" id="hero_image_dropzone"><i data-lucide="image" class="w-8 h-8 text-gray-400 mb-2"></i><span class="font-semibold text-sm text-purple-600">Upload File</span><p class="text-xs text-gray-500 mt-1">JPG/PNG, Max 2MB</p><input type="file" name="hero_image_file" class="hidden" accept="image/*" data-existing-file="<?php echo htmlspecialchars(json_decode($dbProjectData['general_images_json'] ?? '[]')[0] ?? ''); ?>"><div class="existing-file-name"></div></div>
                            </div>
                             <div>
                                <label class="form-label">White Paper</label>
                                <div class="dropzone" id="whitepaper_dropzone"><i data-lucide="file-text" class="w-8 h-8 text-gray-400 mb-2"></i><span class="font-semibold text-sm text-purple-600">Upload File</span><p class="text-xs text-gray-500 mt-1">PDF, Max 10MB</p><input type="file" name="whitepaper_file" class="hidden" accept=".pdf" data-existing-file="<?php echo htmlspecialchars($dbProjectData['whitepaper_file_path'] ?? ''); ?>"><div class="existing-file-name"></div></div>
                            </div>
                        </div>
                    </section>
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Key Metrics & Community</h2>
                        <div id="community-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-community-button">Add metric</button>
                    </section>
                    
                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Why <?php echo htmlspecialchars($dbProjectData['project_name'] ?? 'Your Project'); ?>?</h2>
                        <div id="value-props-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-value-prop-button">Add Value Proposition</button>
                    </section>

                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Socials</h2>
                        <div id="socials-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-social-button">Add social</button>
                    </section>

                    <section class="form-section">
                        <h2 class="text-lg font-semibold mb-4">Team</h2>
                        <div id="team-container" class="space-y-4"></div>
                        <button type="button" class="add-button mt-4" id="add-team-button">Add team member</button>
                    </section>

                    <section class="form-section">
                        <div class="flex items-center mb-4"><input type="checkbox" id="include-partners-checkbox" name="include_partners_toggle" value="1" class="h-4 w-4 rounded"><label for="include-partners-checkbox" class="ml-2">Include Partners Section</label></div>
                        <div id="partners-details-container" class="hidden">
                            <h2 class="text-lg font-semibold mb-4">Partners</h2>
                            <div id="partners-container" class="space-y-4"></div>
                            <button type="button" class="add-button mt-4" id="add-partner-button">Add partner</button>
                        </div>
                    </section>
                    
                    <div class="flex justify-between items-center pt-6 mt-8">
                         <a href="/validate" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-medium text-sm hover:bg-gray-50">Back</a>
                        <button type="submit" class="px-8 py-2.5 bg-gradient-to-r from-purple-700 to-cyan-500 text-white rounded-lg font-medium">Save and Continue</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<style>
.form-label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem; }
.form-input, .form-textarea, .form-select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
.form-section { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; }
.dropzone { border: 2px dashed #d1d5db; border-radius: 0.5rem; padding: 1rem; text-align: center; cursor: pointer; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 120px; }
.existing-file-name { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }
.repeater-item { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; background-color: #ffffff; position: relative; }
.add-button { display: inline-flex; align-items: center; font-size: 0.875rem; font-weight: 500; background-color: white; border: 1px solid #e2e8f0; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
.remove-button { position: absolute; top: 0.75rem; right: 0.75rem; color: #ef4444; background: none; border: none; padding: 0.25rem; cursor: pointer; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    const dbData = <?php echo json_encode($dbProjectData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function setupDropzone(dropzoneElem) {
        const input = dropzoneElem.querySelector('input[type="file"]');
        const existingFileDiv = dropzoneElem.querySelector('.existing-file-name');
        if (!input || !existingFileDiv) return;
        
        function updateDisplay() {
            let fileName = '';
            if (input.files.length > 0) { fileName = input.files[0].name; } 
            else if (input.dataset.existingFile) { fileName = input.dataset.existingFile.split('/').pop(); }
            existingFileDiv.textContent = fileName;
        }

        dropzoneElem.addEventListener('click', () => input.click());
        input.addEventListener('change', updateDisplay);
        updateDisplay();
    }
    document.querySelectorAll('.dropzone').forEach(setupDropzone);

    function addRepeaterItem(container, templateFn, data = {}) {
        const uniqueId = Date.now() + Math.random().toString(36).substr(2, 5);
        const newItem = templateFn(uniqueId, data);
        container.appendChild(newItem);
        lucide.createIcons({ nodes: [newItem.querySelector('.remove-button i')] });
        newItem.querySelector('.remove-button').addEventListener('click', () => newItem.remove());
        if (newItem.querySelector('.dropzone')) {
            setupDropzone(newItem.querySelector('.dropzone'));
        }
    }

    function initializeRepeater(containerId, addButtonId, templateFn, initialData) {
        const container = document.getElementById(containerId);
        const addButton = document.getElementById(addButtonId);
        if (!container || !addButton) return;
        container.innerHTML = '';
        const dataArray = (typeof initialData === 'string') ? JSON.parse(initialData || '[]') : (initialData || []);
        if (dataArray.length > 0) {
            dataArray.forEach(item => addRepeaterItem(container, templateFn, item));
        } else {
            addRepeaterItem(container, templateFn);
        }
        addButton.addEventListener('click', (e) => {
            e.preventDefault();
            addRepeaterItem(container, templateFn);
        });
    }

    const createValuePropItem = (index, data = {}) => {
        const div = document.createElement('div'); div.className = 'repeater-item';
        div.innerHTML = `<label class="form-label">Title</label><input type="text" name="value_props[${index}][title]" class="form-input mb-2" value="${data.title || ''}"><label class="form-label">Description</label><textarea name="value_props[${index}][description]" class="form-textarea">${data.description || ''}</textarea><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createCommunityItem = (index, data = {}) => {
        const div = document.createElement('div'); div.className = 'repeater-item grid grid-cols-2 gap-4';
        div.innerHTML = `<div><label class="form-label">Indicator</label><input type="text" name="community_metrics[${index}][indicator]" class="form-input" value="${data.indicator || ''}"></div><div><label class="form-label">Value</label><input type="text" name="community_metrics[${index}][value]" class="form-input" value="${data.value || ''}"></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createSocialItem = (index, data = {}) => {
        const div = document.createElement('div'); div.className = 'repeater-item grid grid-cols-2 gap-4';
        const platforms = ['Website', 'Twitter', 'Linkedin', 'Telegram', 'Discord', 'Youtube', 'Medium', 'Other'];
        const options = platforms.map(p => `<option value="${p}" ${data.platform_select === p ? 'selected' : ''}>${p}</option>`).join('');
        div.innerHTML = `<div><label class="form-label">Platform</label><select name="socials[${index}][platform_select]" class="form-select">${options}</select></div><div><label class="form-label">URL</label><input type="url" name="socials[${index}][url]" class="form-input" value="${data.url || ''}" placeholder="https://..."></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createTeamItem = (index, data = {}) => {
        const div = document.createElement('div');
        div.className = 'repeater-item grid grid-cols-1 md:grid-cols-3 gap-4 items-start';
        const picturePath = data.picture_file_path || '';
        div.innerHTML = `<div><label class="form-label">Name</label><input type="text" name="team[${index}][name]" class="form-input" value="${data.name || ''}"><input type="hidden" name="team[${index}][existing_picture_path]" value="${picturePath}"></div><div><label class="form-label">Role</label><input type="text" name="team[${index}][role]" class="form-input" value="${data.role || ''}"></div><div><label class="form-label">Picture</label><div class="dropzone"><i data-lucide="image" class="w-6 h-6 text-gray-400"></i><input type="file" name="team[${index}][picture]" class="hidden" accept="image/*" data-existing-file="${picturePath}"><div class="existing-file-name"></div></div></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };
    const createPartnerItem = (index, data = {}) => {
        const div = document.createElement('div');
        div.className = 'repeater-item grid grid-cols-1 md:grid-cols-3 gap-4 items-start';
        const logoPath = data.logo_file_path || '';
        div.innerHTML = `<div><label class="form-label">Name</label><input type="text" name="partners[${index}][name]" class="form-input" value="${data.name || ''}"><input type="hidden" name="partners[${index}][existing_logo_path]" value="${logoPath}"></div><div><label class="form-label">Website</label><input type="url" name="partners[${index}][website]" class="form-input" value="${data.website || ''}" placeholder="https://..."></div><div><label class="form-label">Logo</label><div class="dropzone"><i data-lucide="image" class="w-6 h-6 text-gray-400"></i><input type="file" name="partners[${index}][logo]" class="hidden" accept="image/*" data-existing-file="${logoPath}"><div class="existing-file-name"></div></div></div><button type="button" class="remove-button"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`;
        return div;
    };

    initializeRepeater('value-props-container', 'add-value-prop-button', createValuePropItem, dbData.value_props_json);
    initializeRepeater('community-container', 'add-community-button', createCommunityItem, dbData.community_metrics_json);
    initializeRepeater('socials-container', 'add-social-button', createSocialItem, dbData.socials_json);
    initializeRepeater('team-container', 'add-team-button', createTeamItem, dbData.team_json);
    initializeRepeater('partners-container', 'add-partner-button', createPartnerItem, dbData.partners_json);

    const partnersCheckbox = document.getElementById('include-partners-checkbox');
    const partnersContainer = document.getElementById('partners-details-container');
    partnersCheckbox.checked = dbData.partners_json && JSON.parse(dbData.partners_json).length > 0;
    partnersContainer.classList.toggle('hidden', !partnersCheckbox.checked);
    partnersCheckbox.addEventListener('change', () => {
        partnersContainer.classList.toggle('hidden', !partnersCheckbox.checked);
    });
});
</script>
