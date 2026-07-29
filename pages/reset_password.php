<?php
// /pages/reset_password.php

declare(strict_types=1);

ini_set('display_errors', '0'); // évite d'afficher les erreurs en prod
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- DB (adapte si ton db.php est ailleurs) ---
$db_path = __DIR__ . '/../src/db.php';
if (!file_exists($db_path)) {
    http_response_code(500);
    echo "Server configuration error (db.php not found).";
    exit;
}
/** @var PDO $pdo */
$pdo = require $db_path;

// --- Helpers ---
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function render_form(string $email, string $token, string $error = '', string $success = ''): void {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reset password - Tookle</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-purple-50 to-indigo-50 p-6">
        <div class="w-full max-w-md bg-white rounded-xl shadow p-6">
            <h1 class="text-xl font-bold mb-4 text-center">Reset your password</h1>

            <?php if ($error): ?>
                <div class="mb-4 text-red-600 text-sm text-center"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-4 text-green-600 text-sm text-center"><?= h($success) ?></div>
            <?php endif; ?>

            <form method="post" class="space-y-4" autocomplete="off">
                <input type="hidden" name="email" value="<?= h($email) ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <div>
                    <label class="block text-sm font-medium mb-1">New password</label>
                    <input type="password" name="password" class="w-full border rounded-lg px-3 py-2" required minlength="8">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Repeat new password</label>
                    <input type="password" name="password2" class="w-full border rounded-lg px-3 py-2" required minlength="8">
                </div>

                <button type="submit" class="w-full py-2 rounded-lg font-semibold text-white bg-gradient-to-r from-purple-500 via-indigo-500 to-cyan-500">
                    Update password
                </button>
            </form>

            <div class="mt-4 text-center text-sm">
                <a href="<?= get_url('login') ?>" class="text-purple-600 hover:underline">Back to login</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// --- Inputs ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = trim((string)($_GET['email'] ?? ''));
    $token = trim((string)($_GET['token'] ?? ''));

    if ($email === '' || $token === '') {
        render_form($email, $token, "Missing token or email.");
        exit;
    }

    // Vérifie existence + validité token
    try {
        $stmt = $pdo->prepare("SELECT id, reset_expires FROM user WHERE email = ? AND reset_token = ? LIMIT 1");
        $stmt->execute([$email, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            render_form($email, $token, "Invalid reset link.");
            exit;
        }

        $expiresRaw = (string)($row['reset_expires'] ?? '');
if ($expiresRaw === '') {
    $error = "Invalid or expired link.";
} else {
    // Si c'est une DATE (YYYY-MM-DD) sans heure, on considère la fin de journée
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresRaw)) {
        $expiresRaw .= ' 23:59:59';
    }

    $expiresTs = strtotime($expiresRaw);
    if (!$expiresTs || $expiresTs < time()) {
        $error = "This reset link has expired. Please request a new one.";
    }
}


        render_form($email, $token);
        exit;

    } catch (Throwable $e) {
        error_log("reset_password GET error: ".$e->getMessage());
        render_form($email, $token, "Internal server error.");
        exit;
    }
}

// POST
$email = trim((string)($_POST['email'] ?? ''));
$token = trim((string)($_POST['token'] ?? ''));
$pass1 = (string)($_POST['password'] ?? '');
$pass2 = (string)($_POST['password2'] ?? '');

if ($email === '' || $token === '') {
    render_form($email, $token, "Missing token or email.");
    exit;
}
if ($pass1 === '' || $pass2 === '') {
    render_form($email, $token, "Please fill both password fields.");
    exit;
}
if ($pass1 !== $pass2) {
    render_form($email, $token, "Passwords do not match.");
    exit;
}
if (strlen($pass1) < 8) {
    render_form($email, $token, "Password must be at least 8 characters.");
    exit;
}

try {
    // Re-valide token + expiry
    $stmt = $pdo->prepare("SELECT id, reset_expires FROM user WHERE email = ? AND reset_token = ? LIMIT 1");
    $stmt->execute([$email, $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        render_form($email, $token, "Invalid reset link.");
        exit;
    }
    if (!empty($row['reset_expires']) && strtotime($row['reset_expires']) < time()) {
        render_form($email, $token, "This reset link has expired. Please request a new one.");
        exit;
    }

    $hash = password_hash($pass1, PASSWORD_DEFAULT);

    // Update password + clear token
    $upd = $pdo->prepare("UPDATE user SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $upd->execute([$hash, (int)$row['id']]);

    render_form($email, $token, '', "Password updated. You can now log in.");
    exit;

} catch (Throwable $e) {
    error_log("reset_password POST error: ".$e->getMessage());
    render_form($email, $token, "Internal server error.");
    exit;
}