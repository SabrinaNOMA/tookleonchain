<?php

$pdo = require __DIR__ . '/../src/db.php';       // Manages DB connection
$message = "";

if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = $_GET['email'];
    $token = $_GET['token'];

    // Vérifier que le compte existe et n'est pas encore activé
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = :email AND activation_token = :token AND is_active = 0");
    $stmt->execute(['email' => $email, 'token' => $token]);

    if ($stmt->rowCount() === 1) {
        // Activer le compte
        $update = $pdo->prepare("UPDATE user SET is_active = 1, activation_token = NULL WHERE email = :email");
        $update->execute(['email' => $email]);
        $message = "Our account has been successfully activated! You can now log in";
    } else {
        $message = "Invalid activation link or account already activated.";
    }
} else {
    $message = "Missing parameters in the link.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Account Activation</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="flex items-center justify-center h-screen bg-gradient-to-br from-purple-100 to-indigo-100">
    <div class="bg-white p-6 rounded-lg shadow-md max-w-md text-center">
        <h1 class="text-xl font-bold mb-4">Account Activation</h1>
        <p class="text-gray-700"><?= htmlspecialchars($message) ?></p>
    </div>
</body>
</html>
