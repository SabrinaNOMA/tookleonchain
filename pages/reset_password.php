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
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
        <script src="/config_logo.js"></script>
        <style>
            body { font-family: 'Montserrat', sans-serif; background-color: #F8FAFC; }
            .auth-card {
                width: 100%; max-width: 420px; padding: 2rem 2.5rem;
                border-radius: 1rem; background-color: #ffffff;
                box-shadow: 0 0 0 3px rgba(142, 82, 255, 0.15);
            }
            .btn-submit {
                width: 100%; padding: 0.85rem 1rem; border-radius: 0.6rem;
                font-weight: 600; color: #ffffff; border: none; cursor: pointer;
                background: linear-gradient(135deg, #8e52ff 0%, #6366f1 100%);
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }
            .btn-submit:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(142, 82, 255, 0.25);
            }
            .input-standard {
                width: 100%; padding: 0.65rem 0.8rem; border-radius: 0.5rem;
                border: 1px solid #D1D5DB; font-size: 0.95rem; color: #1F2937;
                background-color: #F9FAFB; transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }
            .input-standard:focus {
                outline: none; border-color: #8e52ff; background-color: #ffffff;
                box-shadow: 0 0 0 3px rgba(142, 82, 255, 0.2);
            }
        </style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-6">
        <div class="auth-card">
            <!-- Brand Logo -->
            <div class="mb-5 text-center">
              <img id="logo" alt="Tookle Logo" class="h-20 w-auto mx-auto">
            </div>

            <h1 class="text-xl font-bold mb-6 text-center text-gray-900">Reset your password</h1>

            <?php if ($error): ?>
                <div class="mb-4 text-red-600 text-sm text-center font-medium bg-red-50 p-3 rounded-lg"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-4 text-green-600 text-sm text-center font-medium bg-green-50 p-3 rounded-lg"><?= h($success) ?></div>
            <?php endif; ?>

            <form method="post" class="space-y-4" autocomplete="off">
                <input type="hidden" name="email" value="<?= h($email) ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">New password</label>
                    <input type="password" name="password" class="input-standard" required minlength="8" placeholder="••••••••">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Repeat new password</label>
                    <input type="password" name="password2" class="input-standard" required minlength="8" placeholder="••••••••">
                </div>

                <div class="pt-2">
                    <button type="submit" class="btn-submit">
                        Update password
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center text-sm">
                <a href="/login" class="text-indigo-600 font-medium hover:underline">Back to login</a>
            </div>
        </div>
        <script>
            // Appliquer le config_logo.js
            if (typeof getLogoUrl === 'function') {
                document.getElementById('logo').src = getLogoUrl();
            } else {
                document.getElementById('logo').src = '/favicon.png';
            }
        </script>
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