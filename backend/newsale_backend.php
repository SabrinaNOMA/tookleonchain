<?php
// newsale_backend.php - Unified Handler for Sale Creation and Data Fetching
// Updated: Added Data Transpose Logic to Fix "Column-Based" POST Issues

// 1. Output Buffering & Error Handling
// Start buffering immediately to prevent HTML injection into JSON
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Correct file paths by using the absolute path of the current directory.
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../config.php';
// ADDED: Missing import for token generation
require_once __DIR__ . '/../src/utils.php';

// Use safer flags for JSON
$json_flags = JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$response = ['success' => false, 'message' => '', 'errors' => [], 'data' => []];

// --- Project ID Handling ---
$projectUuid = trim($_SESSION['active_project_id'] ?? $_GET['projet_id'] ?? $_GET['project_id'] ?? $_POST['projet_id'] ?? ($_POST['project_id'] ?? ''));

if (empty($projectUuid)) {
    ob_end_clean();
    header('Content-Type: application/json');
    $response['message'] = "Project ID is missing.";
    echo json_encode($response, $json_flags);
    exit;
}

// --- Helper Functions ---

function handleFileUpload($fileInfo, $uploadMainDir, $dbStorageBasePath, $uploadSubDir, $fileNamePrefix, $allowedMimeTypes = [], $maxFileSize = 50000000) {
    if (isset($fileInfo['name']) && $fileInfo['error'] === UPLOAD_ERR_OK && !empty($fileInfo['tmp_name']) && $fileInfo['size'] > 0) {
        $fileName = $fileInfo['name'];
        $fileTmpName = $fileInfo['tmp_name'];
        $fileSize = $fileInfo['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileSize > $maxFileSize) {
            $maxFileSizeMB = round($maxFileSize / 1024 / 1024, 2);
            return ['error' => "Error uploading $fileNamePrefix (file: $fileName): File is too large. Maximum size is {$maxFileSizeMB}MB."];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMimeType = finfo_file($finfo, $fileTmpName);
        finfo_close($finfo);

        if (!empty($allowedMimeTypes) && !in_array($actualMimeType, $allowedMimeTypes)) {
            return ['error' => "Error uploading $fileNamePrefix (file: $fileName): Invalid file type '$actualMimeType'. Allowed types: " . implode(', ', $allowedMimeTypes)];
        }

        $newFileName = $fileNamePrefix . '_' . time() . '_' . uniqid('', true) . '.' . $fileExt;
        $targetDirAbsolute = rtrim($uploadMainDir, '/') . '/' . rtrim($uploadSubDir, '/') . '/';

        if (!is_dir($targetDirAbsolute) && !mkdir($targetDirAbsolute, 0775, true)) {
            return ['error' => "Error uploading $fileNamePrefix (file: $fileName): Failed to create directory."];
        }
        
        $destinationAbsolute = $targetDirAbsolute . $newFileName;

        if (move_uploaded_file($fileTmpName, $destinationAbsolute)) {
            return ['path' => rtrim($dbStorageBasePath, '/') . '/' . rtrim($uploadSubDir, '/') . '/' . $newFileName];
        } else {
            return ['error' => "Error uploading $fileNamePrefix (file: $fileName): Failed to move uploaded file."];
        }
    } elseif (isset($fileInfo['error']) && $fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
        return ['error' => "Error with $fileNamePrefix upload: System error code " . $fileInfo['error'] . "."];
    }
    return ['path' => null];
}

// Helper: Create New Agreement Version with Deduplication
function createNewVersion($pdo, $pid, $content, $fileUrl = null) {
    // 1. Fetch the currently active version
    $stmtCurrent = $pdo->prepare("SELECT version, content, file_url FROM agreement_versions WHERE projet_id = ? AND is_active = 1 LIMIT 1");
    $stmtCurrent->execute([$pid]);
    $currentVersion = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    // 2. Compare incoming data with current data
    // We treat null and empty strings as equivalent for comparison to avoid strict type mismatches
    $currentContent = $currentVersion['content'] ?? '[]';
    $currentFile = $currentVersion['file_url'] ?? null;
    
    // Normalize content (e.g. if one is '[]' and other is empty)
    $normNewContent = (empty($content) || $content === '[]') ? '[]' : $content;
    $normOldContent = (empty($currentContent) || $currentContent === '[]') ? '[]' : $currentContent;
    
    $contentMatch = ($normNewContent === $normOldContent);
    $fileMatch = ($fileUrl === $currentFile);

    // 3. If everything matches, return existing version and DO NOT insert
    if ($currentVersion && $contentMatch && $fileMatch) {
        return $currentVersion['version'];
    }

    // 4. If different, proceed with insertion
    $stmtMax = $pdo->prepare("SELECT MAX(version) FROM agreement_versions WHERE projet_id = ?");
    $stmtMax->execute([$pid]);
    $nextVer = ($stmtMax->fetchColumn() ?? 0) + 1;

    // Deactivate old versions
    $pdo->prepare("UPDATE agreement_versions SET is_active = 0 WHERE projet_id = ?")->execute([$pid]);

    // Insert new version
    $stmt = $pdo->prepare("INSERT INTO agreement_versions (projet_id, version, content, file_url, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$pid, $nextVer, $content, $fileUrl]);
    
    return $nextVer;
}

// Helper to transpose $_POST data (column-based) to Row-based array
function transposePostData($postArray) {
    // Input: ['title' => ['T1', 'T2'], 'desc' => ['D1', 'D2']]
    // Output: [['title'=>'T1', 'desc'=>'D1'], ['title'=>'T2', 'desc'=>'D2']]
    if (empty($postArray) || !is_array($postArray)) return [];
    
    $result = [];
    $keys = array_keys($postArray);
    if (empty($keys)) return [];
    
    // Check if the first element is an array to determine row count
    if (!isset($postArray[$keys[0]]) || !is_array($postArray[$keys[0]])) return [];
    
    $count = count($postArray[$keys[0]]);
    for ($i = 0; $i < $count; $i++) {
        $item = [];
        foreach ($keys as $k) {
            $item[$k] = $postArray[$k][$i] ?? null;
        }
        $result[] = $item;
    }
    return $result;
}

// --- Main Script Logic ---
$requestMethod = $_SERVER["REQUEST_METHOD"];

if ($requestMethod == "GET") {
    try {
        $user = auth_check_user($pdo);
        $user_id = $user['id'];

        // Security Check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projet WHERE id = ? AND founder_id = ?");
        $stmt->execute([$projectUuid, $user_id]);
        if ($stmt->fetchColumn() === 0) {
            $response['message'] = "Unauthorized access to project.";
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode($response, $json_flags);
            exit;
        }

        // Fetch Scenario Logic
        $stmtFetchScenario = $pdo->prepare("SELECT data, version_label, created_at FROM scenario_version WHERE projet_id = :project_id ORDER BY created_at DESC LIMIT 1");
        $stmtFetchScenario->execute([':project_id' => $projectUuid]);
        $scenario = $stmtFetchScenario->fetch(PDO::FETCH_ASSOC);

        $availableRounds = [];
        $scenarioLabel = null;

        if ($scenario && !empty($scenario['data'])) {
            $scenarioName = htmlspecialchars($scenario['version_label'] ?? 'Latest Scenario');
            $scenarioDate = new DateTime($scenario['created_at']);
            $scenarioLabel = "Using rounds from scenario: '{$scenarioName}' (created on " . $scenarioDate->format('M j, Y') . ")";
            
            $jsonString = preg_replace('/[[:cntrl:]]/', '', $scenario['data']);
            $jsonString = trim($jsonString, "\xEF\xBB\xBF");
            $scenarioData = json_decode($jsonString, true);
            
            if ($scenarioData !== null) {
                $vestingDetailsMap = [];
                if (isset($scenarioData['vesting'])) {
                    foreach ($scenarioData['vesting'] as $vestingBlock) {
                        if (isset($vestingBlock['source_type']) && $vestingBlock['source_type'] === 'round' && isset($vestingBlock['source_id'])) {
                            $roundId = $vestingBlock['source_id'];
                            $vestingDetailsMap[$roundId] = [
                                'unlock_tge' => $vestingBlock['percent_unlock_at_tge'] ?? 0,
                                'cliff_months' => $vestingBlock['cliff_months'] ?? 0,
                                'vesting_months' => $vestingBlock['vesting_months'] ?? 0,
                            ];
                        }
                    }
                }
                
                if (isset($scenarioData['rounds']) && is_array($scenarioData['rounds'])) {
                    foreach ($scenarioData['rounds'] as $round) {
                        $roundName = $round['round_name'] ?? null;
                        if (empty($roundName)) continue;

                        $tge = 0; $cliff = 0; $vesting = 0;
                        if (isset($round['unlock_tge'])) {
                            $tge = $round['unlock_tge'];
                            $cliff = $round['cliff_months'] ?? 0;
                            $vesting = $round['vesting_months'] ?? 0;
                        } else {
                            $roundId = $round['id'] ?? null;
                            if ($roundId && isset($vestingDetailsMap[$roundId])) {
                                 $vestingData = $vestingDetailsMap[$roundId];
                                 $tge = $vestingData['unlock_tge'];
                                 $cliff = $vestingData['cliff_months'];
                                 $vesting = $vestingData['vesting_months'];
                            }
                        }

                        $availableRounds[] = [
                            'id' => $round['id'] ?? $roundName, // Ensure ID is passed for frontend selection
                            'round_amount' => $round['round_amount'] ?? null,
                            'vesting_schedule_text' => "TGE: {$tge}%, Cliff: {$cliff}m, Vesting: {$vesting}m",
                            'round_name' => $roundName,
                            'round_price' => $round['round_price'] ?? null,
                            'percent_discount' => $round['percent_discount'] ?? null,
                            'unlock_tge' => (float)$tge,
                            'cliff_months' => (int)$cliff,
                            'vesting_months' => (int)$vesting
                        ];
                    }
                }
            }
        }
        $response['availableRounds'] = $availableRounds;
        $response['scenarioLabel'] = $scenarioLabel;
        
        $stmtFetchWallets = $pdo->prepare("SELECT id, label, network, wallet_address FROM project_wallet WHERE projet_id = :project_id");
        $stmtFetchWallets->execute([':project_id' => $projectUuid]);
        $response['founderWallets'] = $stmtFetchWallets->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Agreement Data
        $agg_stmt = $pdo->prepare("SELECT content, file_url, version FROM agreement_versions WHERE projet_id = :project_id AND is_active = 1 ORDER BY version DESC LIMIT 1");
        $agg_stmt->execute([':project_id' => $projectUuid]);
        $activeAgreement = $agg_stmt->fetch(PDO::FETCH_ASSOC);
        
        $response['agreementData'] = null;
        if ($activeAgreement) {
            $response['agreementData'] = [
                'version' => $activeAgreement['version'],
                'file_url' => $activeAgreement['file_url'],
                'content' => $activeAgreement['content']
            ];
        }

        // --- Fetch Prefill or Specific Sale Data ---
        $requestedSaleId = $_GET['sale_id'] ?? null;
        $prefillData = [];
        
        if (!empty($requestedSaleId)) {
            // Case 1: Fetch SPECIFIC sale data (Edit Mode)
            $stmtFetchSpecificSale = $pdo->prepare("
                SELECT tsp.*, cs.kyc_required, cs.exclude_sanctioned, cs.exclude_us_non_accredited, cs.require_eu_consent, cs.custom_country_disclaimer, cs.legal_opinion_url, cs.terms_of_service_url,
                tsp.sale_launch_at as sale_launch_date, tsp.sale_end_at as sale_end_date
                FROM token_sale_pages tsp
                LEFT JOIN compliance_settings cs ON tsp.project_id = cs.projet_id
                WHERE tsp.id = :sale_id AND tsp.project_id = :project_id
            ");
            $stmtFetchSpecificSale->execute([':sale_id' => $requestedSaleId, ':project_id' => $projectUuid]);
            $prefillData = $stmtFetchSpecificSale->fetch(PDO::FETCH_ASSOC) ?: [];
        } else {
            // Case 2: Fetch LATEST INTERNAL sale data (hosting = 'tookle') to ensure we get assets
            // We ignore external sales for prefill because they lack images/story/team data
            // If the user has only external sales, this returns nothing, which is fine (start fresh).
            $stmtFetchLastSale = $pdo->prepare("
                SELECT tsp.*, cs.kyc_required, cs.exclude_sanctioned, cs.exclude_us_non_accredited, cs.require_eu_consent, cs.custom_country_disclaimer, cs.legal_opinion_url, cs.terms_of_service_url,
                tsp.sale_launch_at as sale_launch_date, tsp.sale_end_at as sale_end_date
                FROM token_sale_pages tsp
                LEFT JOIN compliance_settings cs ON tsp.project_id = cs.projet_id
                WHERE tsp.project_id = :project_id 
                AND tsp.hosting = 'tookle' 
                ORDER BY tsp.created_at DESC 
                LIMIT 1
            ");
            $stmtFetchLastSale->execute([':project_id' => $projectUuid]);
            $prefillData = $stmtFetchLastSale->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        if (empty($prefillData) && empty($requestedSaleId)) {
            $stmtFetchComplianceOnly = $pdo->prepare("SELECT * FROM compliance_settings WHERE projet_id = :project_id");
            $stmtFetchComplianceOnly->execute([':project_id' => $projectUuid]);
            $prefillData = $stmtFetchComplianceOnly->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $initialData = [
            'project_name' => '', 'user_email' => $user['email'], 'existingSaleNames' => [],
            'kyc_required' => true, 'exclude_sanctioned' => false, 'exclude_us_non_accredited' => false, 
            'require_eu_consent' => false, 'custom_country_disclaimer' => '[]',
            'token_sale_agreement_url' => null, 'legal_opinion_url' => null, 'terms_of_service_url' => null
        ];

        if ($activeAgreement && !empty($activeAgreement['file_url'])) {
            $initialData['token_sale_agreement_url'] = $activeAgreement['file_url'];
        } 

        $stmtProject = $pdo->prepare("SELECT project_name FROM projet WHERE id = :project_id AND founder_id = :user_id");
        $stmtProject->execute([':project_id' => $projectUuid, ':user_id' => $user_id]);
        if ($project = $stmtProject->fetch(PDO::FETCH_ASSOC)) {
            $initialData['project_name'] = $project['project_name'];
        }

        $stmtNames = $pdo->prepare("SELECT sale_name FROM token_sale_pages WHERE project_id = :project_id");
        $stmtNames->execute([':project_id' => $projectUuid]);
        $initialData['existingSaleNames'] = $stmtNames->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($prefillData)) {
            $keyMappings = [
                'sale_name' => (!empty($requestedSaleId) ? 'sale_name' : 'source_sale_name'), 
                'soft_cap_usd' => 'min_raise', 'hard_cap_usd' => 'max_raise', 
                'min_investment_usd' => 'min_purchase_limit', 'max_investment_usd' => 'max_purchase_limit',
                'hosting' => 'hosting', 'sale_url' => 'sale_url',
                'project_description_story' => 'projectDescription', 'video_file_path' => 'videoFilePath', 'whitepaper_file_path' => 'whitepaperFilePath',
                'kyc_required' => 'kyc_required', 'exclude_sanctioned' => 'exclude_sanctioned', 'exclude_us_non_accredited' => 'exclude_us_non_accredited',
                'require_eu_consent' => 'require_eu_consent', 'custom_country_disclaimer' => 'custom_country_disclaimer', 
                'legal_opinion_url' => 'legal_opinion_url', 'terms_of_service_url' => 'terms_of_service_url',
                'country' => 'country', 'sale_launch_date' => 'sale_launch_date', 'sale_end_date' => 'sale_end_date',
                'status' => 'status'
            ];
            
            foreach($keyMappings as $dbKey => $jsKey) {
                if (array_key_exists($dbKey, $prefillData)) {
                     $initialData[$jsKey] = $prefillData[$dbKey];
                }
            }
             $initialData['kyc_required'] = !empty($prefillData['kyc_required']);
            
            if (!empty($prefillData['duration_seconds'])) {
                $seconds = (int)$prefillData['duration_seconds'];
                $initialData['duration_days'] = round($seconds / 86400, 2);
            }

            $jsonFields = [
                'general_images_json' => 'heroImageDisplayPath', 'value_props_json' => 'valueProps', 'team_json' => 'team',
                'partners_json' => 'partners', 'faqs_json' => 'faqs', 'community_metrics_json' => 'communityMetrics', 'socials_json' => 'socials'
            ];
            foreach($jsonFields as $dbKey => $jsKey) {
                $decoded = !empty($prefillData[$dbKey]) ? json_decode($prefillData[$dbKey], true) : [];
                if (json_last_error() === JSON_ERROR_NONE) {
                     if ($jsKey === 'heroImageDisplayPath') {
                         $initialData[$jsKey] = $decoded[0] ?? null;
                     } else {
                         $initialData[$jsKey] = $decoded;
                     }
                }
            }
        }
        
        if (!empty($requestedSaleId)) {
            $initialData['sale_id'] = $requestedSaleId;
        }

        $response['initialData'] = $initialData;
        $response['countries'] = ['Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo, Democratic Republic of the', 'Congo, Republic of the', 'Costa Rica', "Cote d'Ivoire", 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kosovo', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine State', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'];
        $response['success'] = true;

    } catch (Exception $e) {
        $response['message'] = "An error occurred during data fetch: " . $e->getMessage();
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response, $json_flags);
    exit;

} elseif ($requestMethod == "POST") {

    // --- HANDLE SPECIFIC ACTIONS (AGREEMENT BUILDER) ---
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        try {
            $user = auth_check_user($pdo);
            $upload_dir = __DIR__ . '/../uploads/';
            $db_base_path = '/uploads';

            if ($action === 'upload_parse_agreement') {
                // Handle file upload and create new version
                $fileResult = handleFileUpload($_FILES['doc_agreement'] ?? [], $upload_dir, $db_base_path, 'compliance_docs', 'agreement', ['application/pdf']);
                if (isset($fileResult['error'])) throw new Exception($fileResult['error']);
                
                $parsedContent = $_POST['parsed_content'] ?? '[]';
                $newVer = createNewVersion($pdo, $projectUuid, $parsedContent, $fileResult['path']);
                
                $response['success'] = true;
                $response['message'] = 'Agreement uploaded and parsed.';
                $response['new_version'] = $newVer;
                $response['file_url'] = $fileResult['path'];
            } 
            elseif ($action === 'save_agreement') {
                // Save content only
                $content = $_POST['doc_agreement_content'] ?? '[]';
                if ($content === '[]') throw new Exception("Empty content.");
                $newVer = createNewVersion($pdo, $projectUuid, $content, null);
                
                $response['success'] = true;
                $response['message'] = 'Agreement saved.';
                $response['new_version'] = $newVer;
            }
            elseif ($action === 'delete_document') {
                $docType = $_POST['doc_type'] ?? '';
                if ($docType === 'agreement') {
                    // Soft delete current agreement version
                    $pdo->prepare("UPDATE agreement_versions SET is_active = 0 WHERE projet_id = ?")->execute([$projectUuid]);
                } else {
                    $col = ($docType === 'opinion') ? 'legal_opinion_url' : 'terms_of_service_url';
                    $pdo->prepare("UPDATE compliance_settings SET $col = NULL WHERE projet_id = ?")->execute([$projectUuid]);
                }
                $response['success'] = true;
            }
            else {
                throw new Exception("Unknown action.");
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response, $json_flags);
        exit;
    }

    // --- MAIN SALE SUBMISSION LOGIC ---
    if (isset($_POST['form_identifier']) && $_POST['form_identifier'] === 'newsale_unified_sale') {
        try {
             $user = auth_check_user($pdo);
             $user_id = $user['id'];

            $pdo->beginTransaction();
            
            // Check if it's an UPDATE or INSERT
            $existingSaleId = isset($_POST['sale_id']) && !empty($_POST['sale_id']) ? $_POST['sale_id'] : null;

            $saleName = trim($_POST['sale_name']);
            if (empty($saleName)) throw new Exception("Sale Name is a required field.");
            
            // Validate name uniqueness (exclude self if updating)
            $sqlCheck = "SELECT id FROM token_sale_pages WHERE project_id = :project_id AND sale_name = :sale_name";
            $paramsCheck = [':project_id' => $projectUuid, ':sale_name' => $saleName];
            if ($existingSaleId) {
                $sqlCheck .= " AND id != :existing_id";
                $paramsCheck[':existing_id'] = $existingSaleId;
            }
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute($paramsCheck);
            if ($stmtCheck->fetch()) throw new Exception("A sale with the name '$saleName' already exists for this project.");

            $dataToInsert = ['project_id' => $projectUuid, 'sale_name' => $saleName];
            
            $dataToInsert['country'] = trim($_POST['country'] ?? '');
            if (empty($dataToInsert['country'])) throw new Exception("Country is a required field.");

            $selectedRoundId = trim($_POST['selected_round_id']);
            if (empty($selectedRoundId)) throw new Exception("A round must be selected.");

            // Fetch active scenario to apply to sale
            // FIX: Prioritize specific scenario ID if sent from frontend, else fallback to active
            $postedScenarioId = $_POST['scenario_version_id'] ?? null;

            if ($postedScenarioId) {
                 $stmtFetchScenarioForPost = $pdo->prepare("SELECT id, data, version_label FROM scenario_version WHERE id = :id AND projet_id = :project_id");
                 $stmtFetchScenarioForPost->execute([':id' => $postedScenarioId, ':project_id' => $projectUuid]);
            } else {
                 $stmtFetchScenarioForPost = $pdo->prepare("SELECT id, data, version_label FROM scenario_version WHERE projet_id = :project_id ORDER BY is_active DESC, created_at DESC LIMIT 1");
                 $stmtFetchScenarioForPost->execute([':project_id' => $projectUuid]);
            }

            $activeScenario = $stmtFetchScenarioForPost->fetch(PDO::FETCH_ASSOC);

            if (!$activeScenario || empty($activeScenario['data'])) throw new Exception("Could not find the scenario data.");

            $dataToInsert['scenario_version_id'] = $activeScenario['id'];
            
            // FIX: Clean JSON string before decoding (same as GET logic) to remove BOM/control characters
            $jsonString = preg_replace('/[[:cntrl:]]/', '', $activeScenario['data']);
            $jsonString = trim($jsonString, "\xEF\xBB\xBF");
            $scenarioDataForPost = json_decode($jsonString, true);
            
            if ($scenarioDataForPost === null) {
                throw new Exception("Failed to decode scenario data. Please check the scenario JSON format.");
            }
            
            $vestingDetailsMapForPost = [];
            if (isset($scenarioDataForPost['vesting'])) {
                foreach ($scenarioDataForPost['vesting'] as $vestingBlock) {
                    if (($vestingBlock['source_type'] ?? '') === 'round' && isset($vestingBlock['source_id'])) {
                        $vestingDetailsMapForPost[$vestingBlock['source_id']] = [
                            'percent_unlock_at_tge' => $vestingBlock['percent_unlock_at_tge'] ?? 0,
                            'cliff_months' => $vestingBlock['cliff_months'] ?? 0,
                            'vesting_months' => $vestingBlock['vesting_months'] ?? 0,
                        ];
                    }
                }
            }

            $selectedRoundDetails = null;
            if (isset($scenarioDataForPost['rounds'])) {
                foreach ($scenarioDataForPost['rounds'] as $round) {
                    // Fix: Trim both sides to ensure matching despite accidental whitespace in JSON
                    $jsonRoundName = trim($round['round_name'] ?? '');
                    if ($jsonRoundName === $selectedRoundId) {
                        if (isset($round['unlock_tge'])) {
                            $selectedRoundDetails = $round;
                        } else {
                            $roundId = $round['id'] ?? null;
                            $vestingData = $vestingDetailsMapForPost[$roundId] ?? [];
                            $selectedRoundDetails = array_merge($round, $vestingData);
                        }
                        break;
                    }
                }
            }
            
            if ($selectedRoundDetails === null) {
                // Debugging: Log available rounds to help diagnose
                $available = [];
                if (isset($scenarioDataForPost['rounds'])) {
                    foreach ($scenarioDataForPost['rounds'] as $r) $available[] = $r['round_name'] ?? 'unnamed';
                }
                error_log("Round Search Failed: Looking for '{$selectedRoundId}'. Available in JSON: " . implode(', ', $available));
                throw new Exception("Selected round not found in scenario.");
            }

            $stmtProjectDetails = $pdo->prepare("SELECT token_name, token_ticker, type_supply FROM projet WHERE id = :project_id");
            $stmtProjectDetails->execute([':project_id' => $projectUuid]);
            $projectDetails = $stmtProjectDetails->fetch(PDO::FETCH_ASSOC);
            
            $standardizedSaleTerms = [
                "id" => $selectedRoundDetails['id'] ?? null,
                "projet_id" => $projectUuid,
                "tranche_type" => $selectedRoundDetails['tranche_type'] ?? 'investor',
                "round_name" => $selectedRoundDetails['round_name'] ?? null,
                "percent_discount" => $selectedRoundDetails['percent_discount'] ?? "0.00",
                "percent_total_raise" => $selectedRoundDetails['percent_total_raise'] ?? "0.00",
                "round_price" => $selectedRoundDetails['round_price'] ?? null,
                "round_amount" => $selectedRoundDetails['round_amount'] ?? null,
                "percent_round_supply" => $selectedRoundDetails['percent_round_supply'] ?? null,
                "number_of_tokens" => $selectedRoundDetails['number_of_tokens'] ?? null,
                "round_status" => $selectedRoundDetails['round_status'] ?? null,
                "number_of_token" => $selectedRoundDetails['number_of_token'] ?? null,
                "percent_unlock_at_tge" => $selectedRoundDetails['percent_unlock_at_tge'] ?? ($selectedRoundDetails['unlock_tge'] ?? "0.00"),
                "cliff_months" => $selectedRoundDetails['cliff_months'] ?? 0,
                "vesting_months" => $selectedRoundDetails['vesting_months'] ?? 0,
                "scenario_label" => $activeScenario['version_label'] ?? 'Initial Version',
                "token_name" => $projectDetails['token_name'] ?? '',
                "token_ticker" => $projectDetails['token_ticker'] ?? '',
                "type_supply" => $projectDetails['type_supply'] ?? ''
            ];
            $dataToInsert['sale_terms_json'] = json_encode($standardizedSaleTerms);
            
            $duration = ($_POST['duration_select'] === 'custom') ? trim($_POST['duration_custom'] ?? '') : $_POST['duration_select'];
            if (empty($duration) || !is_numeric($duration) || (int)$duration <= 0) throw new Exception("Valid campaign duration required.");
            // UPDATED: Map to correct DB column duration_seconds
            $dataToInsert['duration_seconds'] = (int)$duration;
            $dataToInsert['soft_cap_usd'] = !empty($_POST['soft_cap']) ? str_replace(',', '', trim($_POST['soft_cap'])) : null;
            $dataToInsert['hard_cap_usd'] = !empty($_POST['target_raise']) ? str_replace(',', '', trim($_POST['target_raise'])) : null;
            
            $hostingSelection = trim($_POST['hosting'] ?? '');
            
            if ($hostingSelection === 'tookle') {
                $dataToInsert['hosting'] = 'tookle';
                // MODIFIED BLOCK: Generate token only for NEW sales
                if (!$existingSaleId) {
                    $dataToInsert['status'] = 'draft'; 
                    $dataToInsert['sale_url'] = generateUniqueSaleToken($pdo);
                }
                
                $dataToInsert['min_investment_usd'] = str_replace(',', '', trim($_POST['min_purchase']));
                
                if (!$existingSaleId) $dataToInsert['status'] = 'draft'; // Only set status to draft on creation
                $dataToInsert['min_investment_usd'] = str_replace(',', '', trim($_POST['min_purchase']));
                $dataToInsert['max_investment_usd'] = str_replace(',', '', trim($_POST['max_purchase']));
                $dataToInsert['project_description_story'] = trim($_POST['project_description'] ?? '');

                // Direct Gnosis Routing Address Settle & Verification
                $routingMode = trim($_POST['payment_routing'] ?? 'escrow');
                if ($routingMode === 'multisig') {
                    $gnosisAddr = trim($_POST['gnosis_safe_address'] ?? '');
                    if (empty($gnosisAddr)) throw new Exception("Gnosis Safe Address is mandatory.");
                    if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $gnosisAddr)) throw new Exception("Invalid Gnosis Safe Address format.");
                    $dataToInsert['gnosis_safe_address'] = $gnosisAddr;
                } else {
                    $dataToInsert['gnosis_safe_address'] = null;
                }

                // File Uploads
                $upload_dir = __DIR__ . '/../uploads/';
                $db_base_path = '/uploads';
                $projectIdSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $projectUuid);

                $videoResult = handleFileUpload($_FILES['video_file'] ?? [], $upload_dir, $db_base_path, $projectIdSafe . '/sale_media', 'video', ['video/mp4', 'video/quicktime', 'video/webm', 'video/ogg']);
                if (isset($videoResult['error'])) throw new Exception($videoResult['error']);
                $dataToInsert['video_file_path'] = $videoResult['path'] ?? $_POST['existing_video_path'] ?? null;

                $heroResult = handleFileUpload($_FILES['hero_image_file'] ?? [], $upload_dir, $db_base_path, $projectIdSafe . '/sale_media', 'hero_image', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
                if (isset($heroResult['error'])) throw new Exception($heroResult['error']);
                $heroPath = $heroResult['path'] ?? $_POST['existing_hero_image_path'] ?? null;
                $dataToInsert['general_images_json'] = $heroPath ? json_encode([$heroPath]) : '[]';

                $whitepaperResult = handleFileUpload($_FILES['whitepaper_file'] ?? [], $upload_dir, $db_base_path, $projectIdSafe . '/sale_media', 'whitepaper', ['application/pdf']);
                if (isset($whitepaperResult['error'])) throw new Exception($whitepaperResult['error']);
                $dataToInsert['whitepaper_file_path'] = $whitepaperResult['path'] ?? $_POST['existing_whitepaper_path'] ?? null;
                
                // Repeater Fields - FIX: Use new processRepeaterFiles that transposes POST columns to rows
                $dataToInsert['team_json'] = json_encode(processRepeaterFiles($_POST['team'] ?? [], $_FILES['team'] ?? [], 'picture', 'team_pic', $upload_dir, $db_base_path, $projectIdSafe . '/sale_media/team'));
                $dataToInsert['partners_json'] = json_encode(processRepeaterFiles($_POST['partners'] ?? [], $_FILES['partners'] ?? [], 'logo', 'partner_logo', $upload_dir, $db_base_path, $projectIdSafe . '/sale_media/partners'));
                
                // FIX: Transpose other array fields before saving to ensure Array of Objects, not Array of Arrays
                $dataToInsert['value_props_json'] = json_encode(transposePostData($_POST['value_props'] ?? []));
                $dataToInsert['faqs_json'] = json_encode(transposePostData($_POST['faqs'] ?? []));
                $dataToInsert['community_metrics_json'] = json_encode(transposePostData($_POST['community_metrics'] ?? []));
                $dataToInsert['socials_json'] = json_encode(transposePostData($_POST['socials'] ?? []));
                
                // Compliance
                $complianceData = [
                    'kyc_required' => isset($_POST['kyc_verification']) ? 1 : 0,
                    'exclude_sanctioned' => in_array('sanctioned', $_POST['restriction_set'] ?? []) ? 1 : 0,
                    'exclude_us_non_accredited' => in_array('us-non-accredited', $_POST['restriction_set'] ?? []) ? 1 : 0,
                    'require_eu_consent' => in_array('eu-consent', $_POST['restriction_set'] ?? []) ? 1 : 0,
                    'custom_country_disclaimer' => $_POST['custom_country_disclaimer'] ?? '[]'
                ];

                // --- Handle Uploads using existing compliance logic (not column assignment) ---
                $doc_upload_dir = __DIR__ . '/../uploads/';
                
                // AGREEMENT: If user uploaded a file directly in the sale form (fallback mode)
                if (isset($_FILES['doc_agreement']['name']) && !empty($_FILES['doc_agreement']['name'])) {
                    $agreementResult = handleFileUpload($_FILES['doc_agreement'], $doc_upload_dir, $db_base_path, $projectIdSafe . '/sale_documents', 'agreement', ['application/pdf']);
                    if (isset($agreementResult['error'])) throw new Exception($agreementResult['error']);
                    // Create a version for it so it's active
                    createNewVersion($pdo, $projectUuid, '[]', $agreementResult['path']);
                }

                $opinionResult = handleFileUpload($_FILES['doc_opinion'] ?? [], $doc_upload_dir, $db_base_path, $projectIdSafe . '/sale_documents', 'opinion', ['application/pdf']);
                if (isset($opinionResult['error'])) throw new Exception($opinionResult['error']);
                $opinionPath = $opinionResult['path'] ?? $_POST['existing_doc_opinion_path'] ?? null;
                if ($opinionPath) $complianceData['legal_opinion_url'] = $opinionPath;
                
                $tosResult = handleFileUpload($_FILES['doc_tos'] ?? [], $doc_upload_dir, $db_base_path, $projectIdSafe . '/sale_documents', 'tos', ['application/pdf']);
                if (isset($tosResult['error'])) throw new Exception($tosResult['error']);
                $tosPath = $tosResult['path'] ?? $_POST['existing_doc_tos_path'] ?? null;
                if ($tosPath) $complianceData['terms_of_service_url'] = $tosPath;

                // Update Compliance Settings (Without missing column)
                $stmtCheckCompliance = $pdo->prepare("SELECT projet_id FROM compliance_settings WHERE projet_id = :project_id");
                $stmtCheckCompliance->execute([':project_id' => $projectUuid]);
                
                if ($stmtCheckCompliance->rowCount() > 0) {
                    $updatePairs = [];
                    foreach ($complianceData as $key => $value) $updatePairs[] = "$key = :$key";
                    $sqlCompliance = "UPDATE compliance_settings SET " . implode(', ', $updatePairs) . " WHERE projet_id = :project_id";
                    
                    $params = $complianceData;
                    $params['project_id'] = $projectUuid; // For WHERE clause
                    $stmtCompliance = $pdo->prepare($sqlCompliance);
                    $stmtCompliance->execute($params);
                } else {
                    $complianceData['projet_id'] = $projectUuid;
                    $sqlCompliance = "INSERT INTO compliance_settings (" . implode(', ', array_keys($complianceData)) . ") VALUES (" . implode(', ', array_map(fn($c) => ":$c", array_keys($complianceData))) . ")";
                    $stmtCompliance = $pdo->prepare($sqlCompliance);
                    $stmtCompliance->execute($complianceData);
                }

            } elseif ($hostingSelection === 'external') {
                $dataToInsert['hosting'] = 'external';
                $dataToInsert['sale_url'] = trim($_POST['external_platform_name'] ?? '');
                if (empty($dataToInsert['sale_url'])) throw new Exception("Platform Name/URL is mandatory.");
                
                $externalStatus = trim($_POST['external_status'] ?? 'draft');
                $statusMap = ['live' => 'live', 'successful' => 'ended_successful', 'failed' => 'ended_failed'];
                $dataToInsert['status'] = $statusMap[$externalStatus] ?? 'draft';
                
                if (!empty($_POST['sale_launch_at'])) $dataToInsert['sale_launch_at'] = trim($_POST['sale_launch_at']);
                if (!empty($_POST['sale_end_at'])) $dataToInsert['sale_end_at'] = trim($_POST['sale_end_at']);
            } else {
                throw new Exception("Invalid hosting selection.");
            }

            if ($existingSaleId) {
                // UPDATE
                $updatePairs = [];
                foreach ($dataToInsert as $key => $val) {
                    if ($key === 'project_id') continue; // Don't update project_id
                    $updatePairs[] = "$key = :$key";
                }
                $sql = "UPDATE token_sale_pages SET " . implode(', ', $updatePairs) . " WHERE id = :sale_id";
                $dataToInsert['sale_id'] = $existingSaleId;
                
                // FIX: Remove project_id from execution array as it's not in the update query
                if(isset($dataToInsert['project_id'])) unset($dataToInsert['project_id']);
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dataToInsert);
                $response['message'] = "Sale updated successfully!";
                $response['data'] = ['sale_id' => $existingSaleId, 'sale_name' => $dataToInsert['sale_name']];

            } else {
                // INSERT
                $tableColumns = array_keys($dataToInsert);
                $sql = "INSERT INTO token_sale_pages (" . implode(', ', $tableColumns) . ") VALUES (" . implode(', ', array_map(fn($c) => ":$c", $tableColumns)) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dataToInsert);
                $newSaleId = $pdo->lastInsertId();
                $response['message'] = "New sale created successfully!";
                $response['data'] = ['sale_id' => $newSaleId, 'sale_name' => $dataToInsert['sale_name']];
            }

            $pdo->commit();
            $response['success'] = true;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $response['message'] = "An error occurred: " . $e->getMessage();
            error_log("Sale Creation Error: " . $e->getMessage());
        }
    } else {
        $response['message'] = "Invalid form submission identifier.";
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response, $json_flags);
    exit;
}

// Updated Helper: Handle Column-Based POST data
function processRepeaterFiles($data, $files, $fileKey, $prefix, $uploadDir, $dbBasePath, $subDir) {
    $processed = [];
    if (empty($data)) return [];
    
    // PHP POST inputs for repeaters (e.g. name="team[name][]") come as 'name' => [val1, val2]
    // We need to transpose this to: [0 => ['name'=>val1, ...], 1 => ['name'=>val2, ...]]
    $keys = array_keys($data);
    if (empty($keys)) return [];
    
    // Ensure we are dealing with arrays (columns)
    if (!isset($data[$keys[0]]) || !is_array($data[$keys[0]])) return [];
    
    $rowCount = count($data[$keys[0]]);
    
    for ($i = 0; $i < $rowCount; $i++) {
        $newItem = [];
        
        // 1. Fill Text Data
        foreach ($keys as $colName) {
            $newItem[$colName] = $data[$colName][$i] ?? null;
        }
        
        // 2. Handle File Path Logic
        $fileKeyName = $fileKey . '_file_path';
        $existingKey = 'existing_' . $fileKey . '_path';
        
        // Grab existing path if available (it should be in the transposed data now)
        $newItem[$fileKeyName] = $newItem[$existingKey] ?? null;
        // Clean up temp key
        unset($newItem[$existingKey]);
        
        // 3. Handle New Upload
        // Structure: $_FILES['team']['name'][$fileKey][$i]
        if (isset($files['name'][$fileKey][$i]) && $files['error'][$fileKey][$i] === UPLOAD_ERR_OK) {
             $fileInfo = [
                'name' => $files['name'][$fileKey][$i],
                'type' => $files['type'][$fileKey][$i],
                'tmp_name' => $files['tmp_name'][$fileKey][$i],
                'error' => $files['error'][$fileKey][$i],
                'size' => $files['size'][$fileKey][$i]
            ];
            $res = handleFileUpload($fileInfo, $uploadDir, $dbBasePath, $subDir, $prefix, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
            if (isset($res['path'])) $newItem[$fileKeyName] = $res['path'];
        }
        
        $processed[] = $newItem;
    }
    
    return $processed;
}
?>