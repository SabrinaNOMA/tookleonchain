<?php
declare(strict_types=1);

/**
 * save_wallet_address.php
 * Enregistre l'adresse du wallet Coinbase Embedded Wallet
 * Utilise src/db.php via __DIR__
 */

// --- Sécurité & logs ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/save_wallet_address_error.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// --- Helper JSON ---
function json_out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // --- Méthode ---
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_out(405, ['ok' => false, 'error' => 'Method not allowed']);
    }

    // --- Session ---
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // --- Auth ---
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        json_out(401, ['ok' => false, 'error' => 'Not authenticated']);
    }

    // --- Lecture JSON ---
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        json_out(400, ['ok' => false, 'error' => 'Invalid JSON body']);
    }

    $walletAddress = trim((string)($payload['walletAddress'] ?? ''));
    $csrfToken     = (string)($payload['csrf_token'] ?? '');

    // --- Validation wallet (EVM) ---
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $walletAddress)) {
        json_out(422, ['ok' => false, 'error' => 'Invalid wallet address format']);
    }

    // --- CSRF (si activé côté front) ---
    if (!empty($_SESSION['csrf_token'])) {
        if ($csrfToken === '' || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            json_out(403, ['ok' => false, 'error' => 'Invalid CSRF token']);
        }
    }

    // ======================================================
    // ✅ ACCÈS DB CORRIGÉ — CHEMIN UNIQUE
    // ======================================================
    $dbPath = __DIR__ . '/src/db.php';

    if (!is_file($dbPath)) {
        error_log('[save_wallet_address] DB file not found: ' . $dbPath);
        json_out(500, ['ok' => false, 'error' => 'DB file not found']);
    }

    /** @var PDO $pdo */
    $pdo = require $dbPath;

    if (!$pdo instanceof PDO) {
        error_log('[save_wallet_address] db.php did not return PDO');
        json_out(500, ['ok' => false, 'error' => 'Invalid DB handler']);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ======================================================
    // ✅ UPDATE USER WALLET
    // ⚠️ Nom de colonne : coinbase_wallet_adress (1 seul "d")
    // ======================================================
    $stmt = $pdo->prepare("
        UPDATE user
        SET coinbase_wallet_adress = :wallet
        WHERE id = :uid
        LIMIT 1
    ");

    $stmt->execute([
        ':wallet' => $walletAddress,
        ':uid'    => $userId
    ]);

    // --- Succès ---
    json_out(200, [
        'ok' => true,
        'walletAddress' => $walletAddress
    ]);

} catch (Throwable $e) {
    error_log('[save_wallet_address] FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_out(500, ['ok' => false, 'error' => 'Server error']);
}