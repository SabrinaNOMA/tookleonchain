<?php
/**
 * Backend: Save Story Section
 * Filepath: /backend/story_backend.php
 * Description: Handles all data from the 'Tell Your Story' page,
 * including text, JSON for repeaters, and file uploads.
 * UPDATED for the new `token_sale_pages` schema and Token Generation.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/utils.php'; // Required for generateUniqueSaleToken

function handleFileUpload($fileInfo, $project_id, $subDir, $prefix) {
    if (!isset($fileInfo['error']) || $fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded, normal behavior
    }

    if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
        switch ($fileInfo['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => 'File too heavy! Please upload a teaser video smaller than 50MB.'];
            case UPLOAD_ERR_PARTIAL:
                return ['error' => 'The file upload was interrupted. Please try again.'];
            default:
                return ['error' => 'File upload failed with error code: ' . $fileInfo['error']];
        }
    }

    $safe_project_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_id);
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/project_' . $safe_project_id . '/' . $subDir . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            return ['error' => "Failed to create upload directory on server."];
        }
    }

    $fileName = $prefix . '_' . time() . '_' . basename(preg_replace('/[^A-Za-z0-9.\-_]/', '', $fileInfo['name']));
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
        return 'project_' . $safe_project_id . '/' . $subDir . '/' . $fileName;
    }

    return ['error' => 'Failed to save uploaded file to destination directory.'];
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /founder/story');
    exit;
}

// Catch post_max_size overflow (when $_POST is cleared by PHP due to large payload)
if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $_SESSION['error_message'] = 'The uploaded files exceed the server maximum POST size limit. Please upload a smaller video file (under 50MB).';
    header('Location: /founder/story');
    exit;
}

$project_id = $_POST['project_id'] ?? null;
if (empty($project_id)) {
    $_SESSION['error_message'] = 'Project ID is missing. Cannot save story.';
    header('Location: /founder/story');
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if a sale page entry already exists for this project
    $stmt = $pdo->prepare("SELECT * FROM token_sale_pages WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $existingData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Prepare data array with defaults or existing values
    $dataToSave = [
        'project_description_story' => $_POST['project_description'] ?? ($existingData['project_description_story'] ?? ''),
        'value_props_json' => isset($_POST['value_props']) ? json_encode(array_values($_POST['value_props'])) : ($existingData['value_props_json'] ?? '[]'),
        'community_metrics_json' => isset($_POST['community_metrics']) ? json_encode(array_values($_POST['community_metrics'])) : ($existingData['community_metrics_json'] ?? '[]'),
        'socials_json' => isset($_POST['socials']) ? json_encode(array_values($_POST['socials'])) : ($existingData['socials_json'] ?? '[]'),
        'faqs_json' => (isset($_POST['include_faq_toggle']) && isset($_POST['faqs'])) ? json_encode(array_values($_POST['faqs'])) : '[]',
        'project_roadmap_json' => null
    ];

    // Handle File Uploads (Videos/Whitepaper)
    $videoRes = handleFileUpload($_FILES['video_file'] ?? null, $project_id, 'videos', 'video');
    if (is_array($videoRes) && isset($videoRes['error'])) {
        $_SESSION['error_message'] = 'Teaser Video Upload Error: ' . $videoRes['error'];
        header('Location: /founder/story');
        exit;
    }
    $dataToSave['video_file_path'] = is_string($videoRes) ? $videoRes : ($existingData['video_file_path'] ?? null);

    $wpRes = handleFileUpload($_FILES['whitepaper_file'] ?? null, $project_id, 'docs', 'wp');
    if (is_array($wpRes) && isset($wpRes['error'])) {
        $_SESSION['error_message'] = 'Whitepaper Upload Error: ' . $wpRes['error'];
        header('Location: /founder/story');
        exit;
    }
    $dataToSave['whitepaper_file_path'] = is_string($wpRes) ? $wpRes : ($existingData['whitepaper_file_path'] ?? null);

    // Handle Hero Image
    $generalImages = json_decode($existingData['general_images_json'] ?? '[]', true);
    if (!is_array($generalImages)) $generalImages = [];
    $heroRes = handleFileUpload($_FILES['hero_image_file'] ?? null, $project_id, 'images', 'hero');
    if (is_array($heroRes) && isset($heroRes['error'])) {
        $_SESSION['error_message'] = 'Hero Image Upload Error: ' . $heroRes['error'];
        header('Location: /founder/story');
        exit;
    }
    if (is_string($heroRes)) {
        array_unshift($generalImages, $heroRes);
    }
    $dataToSave['general_images_json'] = json_encode(array_values(array_unique($generalImages)));

    // Handle Team Members
    $teamData = [];
    if (isset($_POST['team'])) {
        foreach ($_POST['team'] as $index => $member) {
            $picturePath = $member['existing_picture_path'] ?? null;
            if (isset($_FILES['team']['name'][$index]['picture']) && $_FILES['team']['error'][$index]['picture'] === UPLOAD_ERR_OK) {
                $fileInfo = [
                    'name'     => $_FILES['team']['name'][$index]['picture'],
                    'type'     => $_FILES['team']['type'][$index]['picture'],
                    'tmp_name' => $_FILES['team']['tmp_name'][$index]['picture'],
                    'error'    => $_FILES['team']['error'][$index]['picture'],
                    'size'     => $_FILES['team']['size'][$index]['picture']
                ];
                $newPath = handleFileUpload($fileInfo, $project_id, 'team_pictures', 'member_' . $index);
                if ($newPath && !isset($newPath['error'])) {
                    $picturePath = $newPath;
                }
            }
            $teamData[] = [ 'name' => $member['name'], 'role' => $member['role'], 'picture_file_path' => $picturePath ];
        }
    }
    $dataToSave['team_json'] = json_encode($teamData);

    // Handle Partners
    $partnerData = [];
    if (isset($_POST['include_partners_toggle']) && isset($_POST['partners'])) {
        foreach ($_POST['partners'] as $index => $partner) {
            $logoPath = $partner['existing_logo_path'] ?? null;
             if (isset($_FILES['partners']['name'][$index]['logo']) && $_FILES['partners']['error'][$index]['logo'] === UPLOAD_ERR_OK) {
                $fileInfo = [
                    'name'     => $_FILES['partners']['name'][$index]['logo'],
                    'type'     => $_FILES['partners']['type'][$index]['logo'],
                    'tmp_name' => $_FILES['partners']['tmp_name'][$index]['logo'],
                    'error'    => $_FILES['partners']['error'][$index]['logo'],
                    'size'     => $_FILES['partners']['size'][$index]['logo']
                ];
                $newPath = handleFileUpload($fileInfo, $project_id, 'partner_logos', 'partner_' . $index);
                if ($newPath && !isset($newPath['error'])) {
                    $logoPath = $newPath;
                }
            }
            $partnerData[] = [ 'name' => $partner['name'], 'website' => $partner['website'], 'logo_file_path' => $logoPath ];
        }
    }
    $dataToSave['partners_json'] = json_encode($partnerData);

    // Handle Roadmap
    if (isset($_POST['include_roadmap_toggle'])) {
        $roadmapImagePath = handleFileUpload($_FILES['roadmap_image_file'] ?? null, $project_id, 'roadmap', 'roadmap_img') ?: ($_POST['existing_roadmap_image'] ?? null);
        $roadmapData = ['text' => $_POST['roadmap_text'] ?? '', 'image_path' => $roadmapImagePath];
        $dataToSave['project_roadmap_json'] = json_encode($roadmapData);
    }

    // Update Project Website if provided
    $projectWebsiteUrl = null;
    if (isset($_POST['socials']) && is_array($_POST['socials'])) {
        foreach ($_POST['socials'] as $social) {
            if (isset($social['platform_select']) && $social['platform_select'] === 'Website' && !empty($social['url'])) {
                $projectWebsiteUrl = trim($social['url']);
                break;
            }
        }
    }
    if ($projectWebsiteUrl !== null) {
        $updateProjectSql = "UPDATE projet SET project_website = :project_website WHERE id = :project_id";
        $stmtProject = $pdo->prepare($updateProjectSql);
        $stmtProject->execute([':project_website' => $projectWebsiteUrl, ':project_id' => $project_id]);
    }

    // --- INSERT or UPDATE Logic with Token Generation ---
    if ($existingData) {
        $sql = "UPDATE token_sale_pages SET 
                    sale_name = :sale_name, gnosis_safe_address = :gnosis_safe_address,
                    soft_cap_usd = :soft_cap_usd, hard_cap_usd = :hard_cap_usd,
                    min_investment_usd = :min_investment_usd, max_investment_usd = :max_investment_usd,
                    project_description_story = :project_description_story, value_props_json = :value_props_json,
                    community_metrics_json = :community_metrics_json, socials_json = :socials_json, team_json = :team_json,
                    partners_json = :partners_json, faqs_json = :faqs_json, project_roadmap_json = :project_roadmap_json,
                    video_file_path = :video_file_path, whitepaper_file_path = :whitepaper_file_path,
                    general_images_json = :general_images_json
                WHERE project_id = :project_id";
    } else {
        // Generate a new token for the draft sale
        $token = generateUniqueSaleToken($pdo);
        
        $sql = "INSERT INTO token_sale_pages 
                    (project_id, sale_url, sale_name, gnosis_safe_address, soft_cap_usd, hard_cap_usd, min_investment_usd, max_investment_usd, project_description_story, value_props_json, community_metrics_json, socials_json, team_json, partners_json, faqs_json, project_roadmap_json, video_file_path, whitepaper_file_path, general_images_json) 
                VALUES 
                    (:project_id, :sale_url, :sale_name, :gnosis_safe_address, :soft_cap_usd, :hard_cap_usd, :min_investment_usd, :max_investment_usd, :project_description_story, :value_props_json, :community_metrics_json, :socials_json, :team_json, :partners_json, :faqs_json, :project_roadmap_json, :video_file_path, :whitepaper_file_path, :general_images_json)";
        
        // Add the token to the data array for the INSERT
        $dataToSave['sale_url'] = $token;
    }
    
    // Add the new fields
    $dataToSave['sale_name'] = $_POST['sale_name'] ?? null;
    
    // Gnosis Address Validation
    $gnosisAddress = null;
    if (($_POST['vault_type'] ?? '') === 'gnosis') {
        $inputAddress = trim($_POST['gnosis_safe_address'] ?? '');
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $inputAddress)) {
            $_SESSION['error_message'] = 'Invalid Gnosis Safe Address format. Must be a 42-character Ethereum address starting with 0x.';
            header('Location: /founder/story');
            exit;
        }
        $gnosisAddress = $inputAddress;
    }
    $dataToSave['gnosis_safe_address'] = $gnosisAddress;
    
    function parseCurrencyInput($val) {
        if ($val === null || $val === '') return null;
        $val = trim((string)$val);
        $val = preg_replace('/[^\d.,]/', '', $val);
        if ($val === '') return null;
        if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
            $val = str_replace(',', '', $val);
        } elseif (strpos($val, ',') !== false) {
            if (preg_match('/^\d+,\d{3}$/', $val)) {
                $val = str_replace(',', '', $val);
            } else {
                $val = str_replace(',', '.', $val);
            }
        }
        return is_numeric($val) ? (float)$val : null;
    }

    $dataToSave['soft_cap_usd'] = parseCurrencyInput($_POST['soft_cap_usd'] ?? null);
    $dataToSave['hard_cap_usd'] = parseCurrencyInput($_POST['hard_cap_usd'] ?? null);
    $dataToSave['min_investment_usd'] = parseCurrencyInput($_POST['min_investment_usd'] ?? null);
    $dataToSave['max_investment_usd'] = parseCurrencyInput($_POST['max_investment_usd'] ?? null);

    if ($dataToSave['soft_cap_usd'] === null || $dataToSave['hard_cap_usd'] === null) {
        $_SESSION['error_message'] = 'Minimum Raise and Maximum Raise are required.';
        header('Location: /founder/story');
        exit;
    }

    $stmt = $pdo->prepare($sql);
    $dataToSave['project_id'] = $project_id;
    $stmt->execute($dataToSave);

    // Auto-save Gnosis Safe to project_wallet
    if (!empty($gnosisAddress)) {
        $saleName = $_POST['sale_name'] ?? 'Sale';
        $label = $saleName . ' Direct Gnosis';
        $checkStmt = $pdo->prepare("SELECT id FROM project_wallet WHERE projet_id = ? AND LOWER(wallet_address) = LOWER(?)");
        $checkStmt->execute([$project_id, $gnosisAddress]);
        if (!$checkStmt->fetch()) {
            $insStmt = $pdo->prepare("INSERT INTO project_wallet (projet_id, label, wallet_address, network) VALUES (?, ?, ?, 'base')");
            $insStmt->execute([$project_id, $label, $gnosisAddress]);
        }
    }

    $pdo->commit();
    header('Location: /founder/compliance');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = 'A database error occurred: ' . $e->getMessage();
    error_log("Story Backend Error: " . $e->getMessage());
    header('Location: /founder/story');
    exit;
}
?>