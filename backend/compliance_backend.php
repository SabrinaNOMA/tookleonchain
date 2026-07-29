<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../src/db.php'; 

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$project_id = $_POST['project_id'] ?? null;
if (empty($project_id)) {
    $response['message'] = 'Project ID is missing.';
    echo json_encode($response);
    exit;
}

$_SESSION['active_project_id'] = $project_id;
$action = $_POST['action'] ?? 'save_all';

try {
    if (!isset($pdo)) throw new Exception("Database connection failed.");

    // Helper: Upload File
    function uploadFile($fileKey, $pid) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
        
        $uploadDir = __DIR__ . '/../uploads/compliance_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = basename($_FILES[$fileKey]['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        // Simple sanitization
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($fileName, PATHINFO_FILENAME));
        $newJsonName = $pid . '_' . $fileKey . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $newJsonName;

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destPath)) {
            return 'uploads/compliance_docs/' . $newJsonName;
        }
        return null;
    }

    // Helper: Create New Version
    function createNewVersion($pdo, $pid, $content, $fileUrl = null) {
        // 1. Get next version number
        $stmt = $pdo->prepare("SELECT MAX(version) FROM agreement_versions WHERE projet_id = ?");
        $stmt->execute([$pid]);
        $nextVer = ($stmt->fetchColumn() ?? 0) + 1;

        // 2. Deactivate old
        $pdo->prepare("UPDATE agreement_versions SET is_active = 0 WHERE projet_id = ?")->execute([$pid]);

        // 3. Insert new
        $stmt = $pdo->prepare("INSERT INTO agreement_versions (projet_id, version, content, file_url, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$pid, $nextVer, $content, $fileUrl]);
        
        return $nextVer;
    }

    // --- ACTION 1: Upload & Parse Agreement (The Fix) ---
    if ($action === 'upload_parse_agreement') {
        
        // 1. Upload the PDF file physically
        $fileUrl = uploadFile('doc_agreement', $project_id);
        if (!$fileUrl) throw new Exception("File upload failed or no file provided.");

        // 2. Get the parsed content (sent from frontend)
        $parsedContent = $_POST['parsed_content'] ?? '[]';
        
        // 3. Create new database entry
        $newVer = createNewVersion($pdo, $project_id, $parsedContent, $fileUrl);

        $response['success'] = true;
        $response['message'] = 'Agreement uploaded and parsed.';
        $response['new_version'] = $newVer;
        $response['file_url'] = $fileUrl;
    }

    // --- ACTION 2: Save Agreement (Editor Only) ---
    elseif ($action === 'save_agreement') {
        $content = $_POST['doc_agreement_content'] ?? '[]';
        if ($content === '[]') throw new Exception("Empty content.");

        // Create version without file
        $newVer = createNewVersion($pdo, $project_id, $content, null);

        $response['success'] = true;
        $response['message'] = 'Agreement saved.';
        $response['new_version'] = $newVer;
    }

    // --- ACTION 3: Save All (Compliance Settings) ---
    elseif ($action === 'save_all') {
        
        $builderContent = $_POST['doc_agreement_content'] ?? '[]';
        $directAgreementFile = uploadFile('doc_agreement', $project_id);

        // [FIX] Priority 1: Handle Direct File Upload during Save
        // If the user selects a file and clicks "Save" (instead of relying on async upload),
        // we must process this file first to ensure the Mandatory check passes.
        if ($directAgreementFile) {
            // Create a new version with this file.
            // We use the builder content if present, otherwise empty array.
            createNewVersion($pdo, $project_id, $builderContent, $directAgreementFile);
        }
        else {
            // [FIX] Priority 2: Smart Agreement Saving (Text Only)
            // If NO file is being uploaded right now, we check if we need to update the text content.
            // We only create a new version if the content has CHANGED compared to the active version.
            
            // Fetch current active version to compare
            $stmtActive = $pdo->prepare("SELECT content, file_url FROM agreement_versions WHERE projet_id = ? AND is_active = 1 ORDER BY version DESC LIMIT 1");
            $stmtActive->execute([$project_id]);
            $activeVer = $stmtActive->fetch(PDO::FETCH_ASSOC);

            $shouldCreateNewAgreement = false;

            if ($builderContent !== '[]' && $builderContent !== '') {
                if (!$activeVer) {
                    // No active version exists, create one
                    $shouldCreateNewAgreement = true;
                } else {
                    // If active version exists, compare content.
                    $cleanBuilder = trim($builderContent);
                    $cleanActive = trim($activeVer['content'] ?? '');
                    
                    if ($cleanBuilder !== $cleanActive) {
                        $shouldCreateNewAgreement = true;
                    }
                }
            }

            if ($shouldCreateNewAgreement) {
                // Creating a text-only update. 
                // We pass null for fileUrl to indicate this version relies on text/builder.
                createNewVersion($pdo, $project_id, $builderContent, null);
            }
        }

        // --- VALIDATION: Mandatory Agreement Check ---
        // This check runs AFTER we potentially processed the file above.
        $checkStmt = $pdo->prepare("SELECT 1 FROM agreement_versions WHERE projet_id = ? AND is_active = 1 LIMIT 1");
        $checkStmt->execute([$project_id]);
        if (!$checkStmt->fetchColumn()) {
            $response['message'] = 'A Token Purchase Agreement is mandatory.';
            echo json_encode($response);
            exit;
        }
        // ---------------------------------------------

        // A. Handle standard compliance settings
        $kyc = isset($_POST['kyc_verification']) ? 1 : 0;
        
        // Handle array of checkboxes
        $restr = $_POST['restriction_set'] ?? [];
        $sanctioned = in_array('sanctioned', $restr) ? 1 : 0;
        $us_acc = in_array('us-non-accredited', $restr) ? 1 : 0;
        $eu = in_array('eu-consent', $restr) ? 1 : 0;
        
        $customJson = $_POST['custom_country_disclaimer'] ?? '[]';

        // File uploads (Other docs)
        $opinionUrl = uploadFile('doc_opinion', $project_id);
        $otherDocUrl = uploadFile('doc_other', $project_id);

        // Check exists
        $exists = $pdo->prepare("SELECT 1 FROM compliance_settings WHERE projet_id = ?");
        $exists->execute([$project_id]);
        
        if ($exists->fetchColumn()) {
            // UPDATE
            $sql = "UPDATE compliance_settings SET kyc_required=?, exclude_sanctioned=?, exclude_us_non_accredited=?, require_eu_consent=?, custom_country_disclaimer=?";
            $params = [$kyc, $sanctioned, $us_acc, $eu, $customJson];
            
            if ($opinionUrl) { 
                $sql .= ", legal_opinion_url=?"; 
                $params[] = $opinionUrl; 
            }
            if ($otherDocUrl) { 
                $sql .= ", other_doc_url=?"; 
                $params[] = $otherDocUrl; 
            }
            
            $sql .= " WHERE projet_id=?";
            $params[] = $project_id;
            
            $pdo->prepare($sql)->execute($params);
        } else {
            // INSERT
            $sql = "INSERT INTO compliance_settings (projet_id, kyc_required, exclude_sanctioned, exclude_us_non_accredited, require_eu_consent, custom_country_disclaimer, legal_opinion_url, other_doc_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$project_id, $kyc, $sanctioned, $us_acc, $eu, $customJson, $opinionUrl, $otherDocUrl]);
        }

        $response['success'] = true;
        $response['message'] = 'Settings saved.';
        $response['redirect_url'] = '/approve';
    }

    // --- ACTION 4: Delete Document ---
    elseif ($action === 'delete_document') {
        $docType = $_POST['doc_type'] ?? '';
        
        if ($docType === 'agreement') {
            // Soft delete active version
            $stmt = $pdo->prepare("UPDATE agreement_versions SET is_active = 0 WHERE projet_id = ?");
            $stmt->execute([$project_id]);
            $response['message'] = 'Agreement deleted.';
        }
        elseif ($docType === 'opinion') {
            $stmt = $pdo->prepare("UPDATE compliance_settings SET legal_opinion_url = NULL WHERE projet_id = ?");
            $stmt->execute([$project_id]);
            $response['message'] = 'Legal opinion deleted.';
        }
        elseif ($docType === 'other') {
            $stmt = $pdo->prepare("UPDATE compliance_settings SET other_doc_url = NULL WHERE projet_id = ?");
            $stmt->execute([$project_id]);
            $response['message'] = 'Document deleted.';
        } 
        else {
            throw new Exception("Invalid document type for deletion.");
        }
        $response['success'] = true;
    }

} catch (Exception $e) {
    error_log("Compliance Backend Error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>