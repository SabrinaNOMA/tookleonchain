<?php
/**
 * Stripe Webhook Handler
 * Endpoint: /backend/stripe_webhook.php
 */

require_once __DIR__ . '/../src/db.php';

// Try loading config if it exists
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

// Clé secrète du webhook Stripe
$endpoint_secret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : 'whsec_g1x346XaqAGHckQJ15bOoDYpBunmSEPT';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Vérification de la signature Stripe sans SDK
$sig_parts = explode(',', $sig_header);
$t = '';
$v1 = '';
foreach ($sig_parts as $part) {
    $kv = explode('=', $part, 2);
    if (count($kv) === 2) {
        if ($kv[0] === 't') $t = $kv[1];
        if ($kv[0] === 'v1') $v1 = $kv[1];
    }
}

if (empty($t) || empty($v1) || empty($endpoint_secret)) {
    http_response_code(400);
    echo "Missing signature or secret.";
    exit;
}

$signed_payload = $t . '.' . $payload;
$signature = hash_hmac('sha256', $signed_payload, $endpoint_secret);

if (!hash_equals($signature, $v1)) {
    http_response_code(400);
    echo "Invalid signature.";
    exit;
}

// Analyse du JSON
$event = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo "Invalid JSON payload.";
    exit;
}

// On gère uniquement l'événement de paiement réussi
if ($event['type'] === 'checkout.session.completed') {
    $session = $event['data']['object'];
    
    // On récupère l'ID utilisateur passé dans le lien
    $user_id = $session['client_reference_id'] ?? null;

    if ($user_id) {
        // Mise à jour de l'utilisateur pour lui donner les droits Founder
        $stmt = $pdo->prepare("UPDATE user SET has_membership = 1 WHERE id = ?");
        $stmt->execute([(int)$user_id]);
        error_log("Webhook Stripe: Membership activé pour user_id " . $user_id);
    } else {
        error_log("Webhook Stripe: Paiement réussi mais pas de client_reference_id trouvé.");
    }
}

http_response_code(200);
echo "OK";
