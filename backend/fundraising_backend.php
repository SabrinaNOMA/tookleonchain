<?php
/**
 * Backend: Save Fundraising and Allocation Data
 * Filepath: /backend/fundraising_backend.php
 *
 * Description: Handles form submission from fundraising.php, validates data,
 * performs server-side calculations, and updates the database within a transaction.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Configuration & Dependencies ---
require_once __DIR__ . '/../src/db.php';
$form_page_url = '/fundraising';
$next_page_url = '/vesting'; // Placeholder for the next step

// --- Security and Initialization ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die('Method Not Allowed');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit();
}

// --- Helper Functions ---
function parse_submitted_number($valueString): float {
    if ($valueString === null || $valueString === '') return 0.0;
    $cleaned = preg_replace('/[$,\s]/', '', $valueString);
    return is_numeric($cleaned) ? (float)$cleaned : 0.0;
}

function calculate_tge_price(float $fdv, float $supply): float {
    return ($supply > 0) ? $fdv / $supply : 0.0;
}

// --- Main Logic ---
$pdo = require __DIR__ . '/../src/db.php';
$errors = [];
$form_data = $_POST;

try {
    // === 1. Data Collection & Validation ===
    $project_id = $_POST['project_id'] ?? null;
    $founder_id = $_SESSION['user_id'];
    if (empty($project_id)) {
        throw new Exception("Project ID is missing.");
    }
    
    // Verify user owns the project
    $check_stmt = $pdo->prepare("SELECT id FROM projet WHERE id = ? AND founder_id = ?");
    $check_stmt->execute([$project_id, $founder_id]);
    if (!$check_stmt->fetch()) {
        throw new Exception("Permission denied or project not found.");
    }

    $target_raise = parse_submitted_number($_POST['target_raise'] ?? '0');
    $target_fdv = parse_submitted_number($_POST['target_fdv'] ?? '0');
    $total_supply = parse_submitted_number($_POST['total_supply'] ?? '0');

    $round_names = $_POST['round_name'] ?? [];
    $total_raise_percents = $_POST['total_raise_percent'] ?? [];
    $discount_percents = $_POST['discount_percent'] ?? [];

    $alloc_tranches = $_POST['alloc_tranche'] ?? [];
    $alloc_percents = $_POST['alloc_percent'] ?? [];
    // --- FIX: Read the readonly flags from the form submission ---
    $alloc_readonly_flags = $_POST['alloc_readonly'] ?? [];

    // Basic data integrity checks
    if (count($round_names) !== count($total_raise_percents) || count($round_names) !== count($discount_percents)) {
        throw new Exception("Fundraising round data is inconsistent.");
    }
    // --- FIX: Validate all allocation arrays have the same count ---
    if (count($alloc_tranches) !== count($alloc_percents)) {
        throw new Exception("Allocation tranche data is inconsistent.");
    }

    // === 2. Server-side Recalculation ===
    $tge_price = calculate_tge_price($target_fdv, $total_supply);
    $rounds_for_db = [];
    $server_total_investor_supply_percent = 0.0;

    for ($i = 0; $i < count($round_names); $i++) {
        $name = trim($round_names[$i]);
        if (empty($name)) continue;
        $raise_percent = (float)($total_raise_percents[$i] ?? 0);
        $discount = (strtoupper($name) === 'PUBLIC') ? 0.0 : (float)($discount_percents[$i] ?? 0);
        $amount = $target_raise * ($raise_percent / 100);
        $price = $tge_price * (1 - ($discount / 100));
        $tokens = ($price > 0) ? $amount / $price : 0;
        $supply_percent = ($total_supply > 0) ? ($tokens / $total_supply) * 100 : 0;
        
        $rounds_for_db[] = [
            'round_name' => $name, 'percent_total_raise' => $raise_percent, 'percent_discount' => $discount,
            'round_amount' => $amount, 'round_price' => $price, 'percent_round_supply' => $supply_percent,
            'number_of_tokens' => $tokens
        ];
        $server_total_investor_supply_percent += $supply_percent;
    }

    // === 3. Database Transaction ===
    $pdo->beginTransaction();

    // Step 3a: Update projet table
    $stmt_update_projet = $pdo->prepare("UPDATE projet SET target_raise_usd = ?, valuation_tge_usd = ?, calculated_price_tge = ?, percent_supply_investor = ? WHERE id = ?");
    $stmt_update_projet->execute([$target_raise, $target_fdv, $tge_price, $server_total_investor_supply_percent, $project_id]);

    // Step 3b: Clear old data
    $pdo->prepare("DELETE FROM vesting_token WHERE projet_id = ?")->execute([$project_id]);
    $pdo->prepare("DELETE FROM tranche_token WHERE projet_id = ?")->execute([$project_id]);
    $pdo->prepare("DELETE FROM round_token WHERE projet_id = ?")->execute([$project_id]);

    // Step 3c: Insert new rounds
    $sql_insert_round = "INSERT INTO round_token (projet_id, round_name, percent_total_raise, percent_discount, round_amount, round_price, percent_round_supply, number_of_tokens, tranche_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'investor')";
    $stmt_insert_round = $pdo->prepare($sql_insert_round);
    foreach ($rounds_for_db as $round) {
        $stmt_insert_round->execute([$project_id, ...array_values($round)]);
    }

    // Step 3d: Insert new tranches
    $sql_insert_tranche = "INSERT INTO tranche_token (projet_id, tranche_name, tranche_type, allocation_percent) VALUES (?, ?, ?, ?)";
    $stmt_insert_tranche = $pdo->prepare($sql_insert_tranche);
    
    // Handle the investor tranche first, based on server-side calculations
    // This inserts the 'Investors' tranche. We must ensure we don't insert it again in the loop below.
    $stmt_insert_tranche->execute([$project_id, 'Investors', 'investor', $server_total_investor_supply_percent]);

    // --- FIX: Loop through submitted tranches ---
    for ($i = 0; $i < count($alloc_tranches); $i++) {
        $tranche_name = trim($alloc_tranches[$i]);
        
        // Skip empty names
        if (empty($tranche_name)) continue;

        // CRITICAL FIX: The 'Investors' tranche was already inserted above.
        // Even if the frontend sends it as a custom tranche (non-readonly), we MUST skip it here
        // to prevent the "Duplicate entry" 1062 error.
        // We use strcasecmp because MySQL collation is usually case-insensitive.
        if (strcasecmp($tranche_name, 'Investors') === 0) {
            continue;
        }

        $is_readonly = ($alloc_readonly_flags[$i] ?? '0') === '1';

        // We only care about the tranches that are NOT readonly.
        // (Though strictly speaking, the check above for 'Investors' handles the main risk, 
        // checking readonly is good practice).
        if (!$is_readonly) {
            $percent = (float)($alloc_percents[$i] ?? 0);
            $stmt_insert_tranche->execute([$project_id, $tranche_name, 'other', $percent]);
        }
    }

    $pdo->commit();

    // --- 4. Success Redirect ---
    header('Location: ' . $next_page_url, true, 303);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in fundraising_backend.php: " . $e->getMessage());
    // Redirect back to form with the actual error message
    $_SESSION['form_errors'] = ['global' => 'A server error occurred: ' . $e->getMessage()];
    // We don't save form data anymore as the frontend doesn't use it to repopulate yet.
    // $_SESSION['form_data'] = $form_data;
    header('Location: ' . $form_page_url);
    exit();
}