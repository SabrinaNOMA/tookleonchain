<?php
/**
 * AJAX endpoint to unlock a private sale token in the user's session.
 * Used by the "Access by Invitation" form on the Private Sale Rooms page.
 */
ob_start();
session_start();

require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

try {
    $token_input = trim($_GET['token'] ?? $_POST['token'] ?? '');

    // Extract token if user pasted full URL (e.g., https://tookle.io/p/ab12cd or /p/ab12cd)
    if (preg_match('#/p/([A-Za-z0-9]{6,64})#', $token_input, $m)) {
        $token_input = $m[1];
    } elseif (preg_match('#^[A-Za-z0-9]{6,64}$#', $token_input)) {
        // Clean token string
        $token_input = $token_input;
    } else {
        throw new Exception('Invalid invitation code or URL format.', 400);
    }

    // Verify token exists in database
    $stmt = $pdo->prepare("SELECT id, sale_name, status FROM token_sale_pages WHERE sale_url = ? LIMIT 1");
    $stmt->execute([$token_input]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception('Invitation link or code not found.', 404);
    }

    // Unlock in session
    if (!isset($_SESSION['my_unlocked_sales']) || !is_array($_SESSION['my_unlocked_sales'])) {
        $_SESSION['my_unlocked_sales'] = [];
    }
    if (!in_array($token_input, $_SESSION['my_unlocked_sales'])) {
        $_SESSION['my_unlocked_sales'][] = $token_input;
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'token' => $token_input,
        'redirect_url' => '/p/' . $token_input,
        'message' => 'Private sale room unlocked successfully.'
    ]);
} catch (Exception $e) {
    ob_end_clean();
    $code = ($e->getCode() >= 400 && $e->getCode() <= 500) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
