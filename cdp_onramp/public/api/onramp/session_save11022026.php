<?php
require __DIR__ . '/../../../vendor/autoload.php';

use App\CdpJwt;
use GuzzleHttp\Client;
use Dotenv\Dotenv;

error_reporting(E_ALL);
// On évite d'afficher les erreurs PHP en HTML dans la réponse JSON
ini_set('display_errors', 0);

// Chargement du .env à la racine du projet (/tookle2/cdp_onramp)
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->safeLoad();

header('Content-Type: application/json');

// Vérification minimale des variables d'environnement
if (empty($_ENV['CDP_API_KEY_NAME']) || empty($_ENV['CDP_API_KEY_SECRET'])) {
    http_response_code(500);
    echo json_encode([
        'error'    => 'CDP_API_KEY_NAME ou CDP_API_KEY_SECRET manquant dans .env',
        'env_keys' => array_keys($_ENV),
    ]);
    exit;
}

// CORS basique (si tu appelles depuis un autre domaine)
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $_ENV['CORS_ALLOW_ORIGIN'] ?? '';
if ($allowed && $origin === $allowed) {
    header('Access-Control-Allow-Origin: ' . $allowed);
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}

// Préflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // 1) Lecture du JSON en entrée
    $rawBody = file_get_contents('php://input');
    $input   = json_decode($rawBody, true);

    // Champs envoyés par ton widget Tookle
    $wallet     = $input['wallet_address']   ?? null;
    $amount     = $input['amount']           ?? '50.00';  // string
    $fiat       = $input['fiat_currency']    ?? 'EUR';    // "EUR" ou "USD"
    $crypto     = $input['crypto_currency']  ?? 'USDC';   // "USDC" ou "USDT"
    $blockchain = $input['blockchain']       ?? 'base';   // "base" ou "ethereum"

    if (!$wallet) {
        throw new Exception("Missing wallet_address");
    }

    // Normalisation simple des valeurs autorisées
    $fiat = strtoupper($fiat);
    if (!in_array($fiat, ['EUR', 'USD'], true)) {
        $fiat = 'EUR';
    }

    $crypto = strtoupper($crypto);
    if (!in_array($crypto, ['USDC', 'USDT'], true)) {
        $crypto = 'USDC';
    }

    $blockchain = strtolower($blockchain);
    if (!in_array($blockchain, ['base', 'ethereum'], true)) {
        $blockchain = 'base';
    }

    // Actifs que l'on autorise pour la session Onramp
    $assets = [$crypto]; // ex: ["USDC"] ou ["USDT"]

    // IP client (simplifiée)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // 2) Préparation de l'appel /onramp/v1/token
    $apiBase = $_ENV['CDP_API_BASE'] ?? 'https://api.developer.coinbase.com';
    $host    = parse_url($apiBase, PHP_URL_HOST) ?: 'api.developer.coinbase.com';
    $path    = '/onramp/v1/token';

    // Corps JSON conforme à la doc Coinbase Onramp
    $bodyArray = [
        'addresses' => [[
            'address'     => $wallet,
            'blockchains' => [$blockchain],
        ]],
        'assets'   => $assets,
        'clientIp' => $clientIp,
    ];
    $bodyJson = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

    // 3) Générer le JWT ES256 (CDP_API_KEY_NAME + CDP_API_KEY_SECRET)
    $jwt = CdpJwt::generateJwt('POST', $host, $path);

    // 4) Appeler l'API Session Token CDP
    $client   = new Client();
    $response = $client->post($apiBase . $path, [
        'headers' => [
            'Authorization' => "Bearer {$jwt}",
            'Content-Type'  => 'application/json',
        ],
        'body' => $bodyJson,
    ]);

    $respBody = json_decode($response->getBody(), true);
    if (empty($respBody['token'])) {
        throw new Exception('Missing session token in response: ' . $response->getBody());
    }
    $sessionToken = $respBody['token'];

    // 5) Construire l'URL Onramp (avec devise fiat et montant pré-rempli)
    $presetFiatAmount = (float) $amount;

    $query = http_build_query([
        'sessionToken'      => $sessionToken,
        'defaultNetwork'    => $blockchain,   // base / ethereum
        'presetFiatAmount'  => $presetFiatAmount,
        'fiatCurrency'      => $fiat,         // EUR / USD
        'defaultExperience' => 'buy',
    ]);

    $onrampUrl = 'https://pay.coinbase.com/buy/select-asset?' . $query;

    echo json_encode([
        'success'       => true,
        'session_url'   => $onrampUrl,
        'session_token' => $sessionToken,
        'fiat_currency' => $fiat,
        'crypto'        => $crypto,
        'blockchain'    => $blockchain,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        // 'trace' => $e->getTraceAsString(), // à activer si tu veux du debug détaillé
    ]);
}
