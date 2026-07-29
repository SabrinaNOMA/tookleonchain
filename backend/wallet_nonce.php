<?php
/**
 * wallet_nonce.php
 * 
 * Génère un nonce unique pour signature EVM/Phantom (type SIWE) 
 * et le stocke dans la session pour vérification ultérieure.
 * 
 * Chemin attendu : /backend/wallet_nonce.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// En-tête JSON
header('Content-Type: application/json; charset=utf-8');

// Désactiver le cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    // Génération d’un nonce aléatoire (32 caractères hex)
    $nonce = bin2hex(random_bytes(16));

    // Sauvegarde en session (pour la vérification de signature)
    $_SESSION['siwe_nonce'] = $nonce;
    $_SESSION['siwe_nonce_ts'] = time(); // timestamp pour limite de validité

    echo json_encode([
        'success' => true,
        'nonce'   => $nonce
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log("wallet_nonce error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error while generating nonce.'
    ]);
    exit;
}
?>