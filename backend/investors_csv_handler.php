<?php
/**
 * investors_csv_handler.php
 * Handles the validation and review of an uploaded investor CSV.
 * This file does NOT import data. It only validates and returns a report.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$project_id = $_SESSION['active_project_id'] ?? null;
$action = $_POST['action'] ?? null;

if ($method !== 'POST' || $action !== 'review_csv' || !$project_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload error. Please try again.']);
    exit;
}

$file_path = $_FILES['csv_file']['tmp_name'];
$file_size = $_FILES['csv_file']['size'];

// --- Security & Validation ---
if ($file_size > 5 * 1024 * 1024) { // 5MB
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit;
}

$mime_type = mime_content_type($file_path);
if ($mime_type !== 'text/plain' && $mime_type !== 'text/csv' && $mime_type !== 'application/csv') {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => "Invalid file type ($mime_type). Only text/csv is allowed."]);
    exit;
}
// --- End Security ---


try {
    // 1. Fetch valid sale names for this project
    $stmt_sales = $pdo->prepare("SELECT sale_name FROM token_sale_pages WHERE project_id = :project_id");
    $stmt_sales->execute([':project_id' => $project_id]);
    // Store in a simple lookup array (lowercase for case-insensitive check)
    $valid_sale_names = [];
    while ($row = $stmt_sales->fetch(PDO::FETCH_ASSOC)) {
        $valid_sale_names[strtolower($row['sale_name'])] = $row['sale_name']; // Store original case
    }

    if (empty($valid_sale_names)) {
        throw new Exception("No 'Sale Names' are configured for this project. Please create a sale page before importing investors.");
    }

    // 2. Define valid options
    $valid_payment_methods = ['bank_transfer', 'stablecoin'];
    $valid_payment_statuses = ['pending', 'successful', 'failed'];
    $valid_kyc_statuses = ['pending', 'verified', 'failed', 'in review', 'n/a', '']; // Allow empty/NA

    // 3. Process the CSV
    $csv_file = fopen($file_path, 'r');
    if ($csv_file === false) {
        throw new Exception("Failed to open uploaded file.");
    }

    $headers = fgetcsv($csv_file);
    if ($headers === false) {
        throw new Exception("Could not read CSV headers. File might be empty or corrupt.");
    }
    
    // Normalize headers (trim whitespace, lowercase)
    $normalized_headers = array_map(function($h) {
        return strtolower(trim($h));
    }, $headers);

    // Find header keys (e.g., 'contact' could be 'contact', 'email', 'contact email')
    // This makes the import much more user-friendly
    $header_map = [
        'firstName' => array_search_multiple(['firstname', 'first_name', 'first'], $normalized_headers),
        'lastName' => array_search_multiple(['lastname', 'last_name', 'last', 'name'], $normalized_headers),
        'contact' => array_search_multiple(['contact', 'email', 'contact_email', 'contact email'], $normalized_headers),
        'amount_usd' => array_search_multiple(['amount_usd', 'amount (usd)', 'amount', 'usd'], $normalized_headers),
        'sale_name' => array_search_multiple(['sale_name', 'sale name', 'sale', 'round'], $normalized_headers),
        'payment_method' => array_search_multiple(['payment_method', 'payment method'], $normalized_headers),
        'payment_status' => array_search_multiple(['payment_status', 'payment status'], $normalized_headers),
        'kyc_status' => array_search_multiple(['kyc_status', 'kyc status', 'kyc'], $normalized_headers),
        'wallet_address' => array_search_multiple(['wallet_address', 'wallet', 'address'], $normalized_headers),
    ];

    // --- Validate Headers ---
    $required_headers = ['lastName', 'contact', 'amount_usd', 'sale_name', 'payment_method', 'payment_status'];
    $missing_headers = [];
    foreach ($required_headers as $key) {
        if ($header_map[$key] === null) {
            $missing_headers[] = $key;
        }
    }
    if (!empty($missing_headers)) {
        throw new Exception("Missing required CSV columns: " . implode(', ', $missing_headers));
    }


    $valid_rows = [];
    $invalid_rows = [];
    $row_number = 1; // Start at 1 for header

    while (($row_data = fgetcsv($csv_file)) !== false) {
        $row_number++;
        $errors = [];
        $investor_data = [];

        // Map data from CSV row to our internal keys
        foreach ($header_map as $key => $csv_index) {
            if ($csv_index !== null && isset($row_data[$csv_index])) {
                $investor_data[$key] = trim($row_data[$csv_index]);
            } else {
                $investor_data[$key] = null; // Ensure key exists
            }
        }
        
        // --- Row-level Validation ---
        
        // lastName
        if (empty($investor_data['lastName'])) { $errors[] = "lastName is required."; }
        
        // contact (Email)
        if (empty($investor_data['contact'])) {
            $errors[] = "contact (email) is required.";
        } elseif (!filter_var($investor_data['contact'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "contact ('{$investor_data['contact']}') is not a valid email.";
        }
        
        // amount_usd
        if (empty($investor_data['amount_usd'])) {
            $errors[] = "amount_usd is required.";
        } elseif (!is_numeric($investor_data['amount_usd']) || $investor_data['amount_usd'] <= 0) {
            $errors[] = "amount_usd ('{$investor_data['amount_usd']}') must be a positive number.";
        }
        
        // sale_name
        if (empty($investor_data['sale_name'])) {
            $errors[] = "sale_name is required.";
        } else {
            $lowercased_sale_name = strtolower($investor_data['sale_name']);
            if (!isset($valid_sale_names[$lowercased_sale_name])) {
                $errors[] = "sale_name ('{$investor_data['sale_name']}') is not a valid sale name for this project.";
            } else {
                // Use the correct-cased name from our DB lookup
                $investor_data['sale_name'] = $valid_sale_names[$lowercased_sale_name];
            }
        }

        // payment_method
        $lowercased_pm = strtolower($investor_data['payment_method']);
        if (empty($lowercased_pm)) {
            $errors[] = "payment_method is required.";
        } elseif (!in_array($lowercased_pm, $valid_payment_methods)) {
            $errors[] = "payment_method ('{$investor_data['payment_method']}') is invalid. Must be 'bank_transfer' or 'stablecoin'.";
        } else {
            $investor_data['payment_method'] = $lowercased_pm; // Use normalized value
        }
        
        // payment_status
        $lowercased_ps = strtolower($investor_data['payment_status']);
        if (empty($lowercased_ps)) {
            $errors[] = "payment_status is required.";
        } elseif (!in_array($lowercased_ps, $valid_payment_statuses)) {
            $errors[] = "payment_status ('{$investor_data['payment_status']}') is invalid. Must be 'pending', 'successful', or 'failed'.";
        } else {
            $investor_data['payment_status'] = $lowercased_ps; // Use normalized value
        }

        // kyc_status (Optional)
        $lowercased_ks = strtolower($investor_data['kyc_status']);
        if (!empty($lowercased_ks) && !in_array($lowercased_ks, $valid_kyc_statuses)) {
            $errors[] = "kyc_status ('{$investor_data['kyc_status']}') is invalid. Must be 'Pending', 'Verified', 'Failed', 'In Review', or 'N/A'.";
        } else {
            // Capitalize 'In Review' for consistency
            if ($lowercased_ks === 'in review') $investor_data['kyc_status'] = 'In Review';
            elseif ($lowercased_ks === 'n/a' || $lowercased_ks === '') $investor_data['kyc_status'] = 'N/A';
            else $investor_data['kyc_status'] = ucfirst($lowercased_ks);
        }

        // wallet_address (Optional) - basic validation
        if (!empty($investor_data['wallet_address']) && !preg_match('/^0x[a-fA-F0-9]{40}$/', $investor_data['wallet_address'])) {
            $errors[] = "wallet_address ('{$investor_data['wallet_address']}') is not a valid 0x address.";
        }

        // Add source
        $investor_data['source'] = 'csv_import';

        // --- Final Decision ---
        if (empty($errors)) {
            $valid_rows[] = $investor_data;
        } else {
            $invalid_rows[] = [
                'row_number' => $row_number,
                'data' => [
                    'firstName' => $investor_data['firstName'],
                    'lastName' => $investor_data['lastName'],
                    'contact' => $investor_data['contact']
                ],
                'errors' => $errors
            ];
        }
    }
    fclose($csv_file);

    // 4. Send Response
    $summary = [
        'total_rows' => $row_number - 1,
        'valid_count' => count($valid_rows),
        'invalid_count' => count($invalid_rows)
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'validRows' => $valid_rows,
            'invalidRows' => $invalid_rows,
            'summary' => $summary
        ]
    ]);


} catch (Exception $e) {
    http_response_code(500);
    error_log("CSV Review Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


/**
 * Helper function to find the first matching header index.
 */
function array_search_multiple($needles, $haystack) {
    foreach ($needles as $needle) {
        $key = array_search($needle, $haystack);
        if ($key !== false) {
            return $key;
        }
    }
    return null;
}
?>

