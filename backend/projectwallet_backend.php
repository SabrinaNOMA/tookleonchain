<?php
/**
 * Backend: Fetch and Save Project Wallets
 * Filepath: /backend/projectwallet_backend.php
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pdo = require __DIR__ . '/../src/db.php';

// --- AUTHENTICATION & INPUT VALIDATION ---
$project_id = $_SESSION['active_project_id'] ?? null;
$founder_id = $_SESSION['user_id'] ?? null;

if (!$project_id || !$founder_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required or no project selected.']);
    exit;
}

// --- ROUTE BASED ON HTTP METHOD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- HANDLE SAVE LOGIC ---
    $labels = $_POST['walletName'] ?? [];
    $addresses = $_POST['walletAddress'] ?? [];
    $networks = $_POST['walletNetwork'] ?? [];
    $notes = $_POST['walletNote'] ?? [];

    if (count($labels) !== count($addresses) || count($labels) !== count($networks) || count($labels) !== count($notes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Form data is inconsistent. Mismatched field counts.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Verify founder owns the project before making any changes
        $authStmt = $pdo->prepare("SELECT id FROM projet WHERE id = ? AND founder_id = ?");
        $authStmt->execute([$project_id, $founder_id]);
        if ($authStmt->fetch() === false) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['error' => 'You do not have permission to modify this project.']);
            exit;
        }

        // 1. Delete all existing wallets for this project to ensure a clean slate
        $deleteStmt = $pdo->prepare("DELETE FROM project_wallet WHERE projet_id = ?");
        $deleteStmt->execute([$project_id]);

        // 2. Insert the new set of wallets
        $insertStmt = $pdo->prepare(
            "INSERT INTO project_wallet (projet_id, label, wallet_address, network, note) VALUES (?, ?, ?, ?, ?)"
        );

        for ($i = 0; $i < count($labels); $i++) {
            $label = trim($labels[$i]);
            $address = trim($addresses[$i]);
            $network = trim($networks[$i]);
            $note = trim($notes[$i]);

            // Only insert if essential fields are not empty
            if (!empty($label) && !empty($address) && !empty($network)) {
                $insertStmt->execute([$project_id, $label, $address, $network, $note]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Project wallets updated successfully.']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Project Wallet Save Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'A database error occurred while saving the wallets.']);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- HANDLE FETCH LOGIC ---
    try {
        $authStmt = $pdo->prepare("SELECT id FROM projet WHERE id = ? AND founder_id = ?");
        $authStmt->execute([$project_id, $founder_id]);
        if ($authStmt->fetch() === false) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not have permission to view these wallets.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT pw.label, pw.wallet_address, pw.network, pw.note, 
                   (SELECT tsp.sale_name FROM token_sale_pages tsp WHERE tsp.project_id = pw.projet_id AND LOWER(tsp.gnosis_safe_address) = LOWER(pw.wallet_address) LIMIT 1) as token_sale_name
            FROM project_wallet pw 
            WHERE pw.projet_id = ?
        ");
        $stmt->execute([$project_id]);
        $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['wallets' => $wallets]);

    } catch (PDOException $e) {
        error_log("Project Wallet Fetch Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'A database error occurred while fetching wallets.']);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>

