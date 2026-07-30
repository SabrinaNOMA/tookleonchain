<?php
/**
 * forgot_password.php
 * - Génère reset_token + reset_expires
 * - Met à jour la table `user`
 * - Envoie un lien vers reset_password.php
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ---------- Helpers ----------
function respond(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}
function ok_generic(): void {
  // Réponse générique (évite l’énumération d’emails)
  respond(['success' => true, 'message' => 'If this email exists, a reset link has been sent.']);
}

// ---------- Read input (JSON ou form) ----------
$raw = file_get_contents('php://input') ?: '';
$isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
$data = $isJson ? (json_decode($raw, true) ?: []) : $_POST;

$email = trim($data['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  // réponse générique quand même
  ok_generic();
}

// ---------- DB ----------
$db_path = __DIR__ . '/../src/db.php'; // adapte si ton arbo est différente
if (!file_exists($db_path)) {
  error_log("[forgot_password] DB file not found: " . $db_path);
  // générique
  ok_generic();
}

/** @var PDO $pdo */
$pdo = require $db_path;

try {
  // 1) Cherche l'utilisateur
  $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $userId = $stmt->fetchColumn();

  // Toujours réponse générique si user inconnu
  if (!$userId) {
    ok_generic();
  }

  // 2) Génère token + expiration
  $token = bin2hex(random_bytes(32)); // 64 chars
  $expiresAt = (new DateTimeImmutable('now'))->modify('+60 minutes')->format('Y-m-d H:i:s');

  // 3) Met à jour la table user
  $upd = $pdo->prepare("UPDATE user SET reset_token = ?, reset_expires = ? WHERE id = ?");
  $upd->execute([$token, $expiresAt, (int)$userId]);

  // 4) Construit le lien vers reset_password.php
  $resetLink = "https://onchain.tookle.app/pages/reset_password.php?token=" . urlencode($token)
            . "&email=" . urlencode($email);

  // 5) Envoi mail (headers importants sur OVH)
  $subject = "Password Reset - Tookle";
  $body = "Hello,\n\n"
        . "Click this link to reset your password:\n\n"
        . $resetLink . "\n\n"
        . "This link expires in 1 hour.\n\n"
        . "If you did not request this, you can ignore this email.\n";

  // IMPORTANT: mets une adresse du domaine dev.tookle.app (ou ton domaine OVH)
  $from = "no-reply@support.tookle.app";

  $headers = [];
  $headers[] = "From: Tookle <{$from}>";
  $headers[] = "Reply-To: {$from}";
  $headers[] = "MIME-Version: 1.0";
  $headers[] = "Content-Type: text/plain; charset=UTF-8";

  $sent = @mail($email, $subject, $body, implode("\r\n", $headers));

  if (!$sent) {
    $last = error_get_last();
    error_log("[forgot_password] mail() failed email={$email} last_error=" . json_encode($last));
    // On renvoie quand même OK générique (sinon l'UX montre une erreur même si le token est bien créé)
    ok_generic();
  }

  ok_generic();

} catch (Throwable $e) {
  error_log("[forgot_password] " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
  // Réponse générique
  ok_generic();
}
