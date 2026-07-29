<?php
// /tookle2/backend/phantom_nonce.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
// Éviter tout cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // génère un nonce aléatoire
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['phantom_nonce'] = $nonce;

    // (optionnel) trace
    // error_log("[phantom_nonce] sid=" . session_id() . " nonce=" . $nonce);

    echo json_encode(['success' => true, 'nonce' => $nonce], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("phantom_nonce error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error'], JSON_UNESCAPED_UNICODE);
}
?>