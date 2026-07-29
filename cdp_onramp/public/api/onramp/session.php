<?php
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use App\CdpJwt;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// --- PROD: pas d'erreurs HTML ---
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// =====================================================
// 1) SESSION AUTH (obligatoire)
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

// =====================================================
// 2) LOAD .env
// =====================================================
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->safeLoad();

// =====================================================
// 3) CORS STRICT + CREDENTIALS (allowlist)
//    Coinbase: pas de "*", jamais.
// =====================================================
$allowedOrigins = [
    'https://dev.tookle.app',
    'https://preprod.tookle.app',
    'https://tookle.app',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '') {
    if (!in_array($origin, $allowedOrigins, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'origin_not_allowed']);
        exit;
    }

    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type, Accept, X-CSRF-Token");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Vary: Origin");
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =====================================================
// 4) CSRF obligatoire (header X-CSRF-Token)
// =====================================================
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSess   = $_SESSION['csrf_token'] ?? '';

if ($csrfSess === '' || $csrfHeader === '' || !hash_equals((string)$csrfSess, (string)$csrfHeader)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf_invalid']);
    exit;
}

// =====================================================
// 5) ENV CHECK (CDP)
// =====================================================
if (empty($_ENV['CDP_API_KEY_NAME']) || empty($_ENV['CDP_API_KEY_SECRET'])) {
    http_response_code(500);
    echo json_encode(['error' => 'CDP_API_KEY_NAME ou CDP_API_KEY_SECRET manquant dans .env']);
    exit;
}

$apiBase = $_ENV['CDP_API_BASE'] ?? 'https://api.developer.coinbase.com';
$host    = parse_url($apiBase, PHP_URL_HOST) ?: 'api.developer.coinbase.com';
$path    = '/onramp/v1/token';

// =====================================================
// 6) PDO + Rate limit + Logs
// =====================================================
function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn  = $_ENV['DB_DSN']  ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';

    if ($dsn === '' || $user === '') {
        throw new RuntimeException('DB_DSN/DB_USER missing in .env');
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function rateLimit(PDO $pdo, int $userId, string $ip): void {
    $minSec = (int)($_ENV['ONRAMP_RL_MIN_SECONDS_BETWEEN'] ?? 5); // ex 5s
    $winSec = (int)($_ENV['ONRAMP_RL_WINDOW_SECONDS'] ?? 60);     // ex 60s
    $maxWin = (int)($_ENV['ONRAMP_RL_MAX_PER_WINDOW'] ?? 10);     // ex 10/min

    // dernier call user
    $st = $pdo->prepare("SELECT created_at FROM onramp_token_requests WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$userId]);
    if ($r = $st->fetch()) {
        $last = strtotime((string)$r['created_at']);
        if ($last && (time() - $last) < $minSec) {
            http_response_code(429);
            echo json_encode(['error' => 'rate_limited', 'retry_after' => $minSec]);
            exit;
        }
    }

    // quota fenêtre user OU ip
    $st2 = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM onramp_token_requests
        WHERE (user_id = :uid OR ip = :ip)
          AND created_at >= (NOW() - INTERVAL :win SECOND)
    ");
    $st2->execute([':uid'=>$userId, ':ip'=>$ip, ':win'=>$winSec]);
    $c = (int)($st2->fetch()['c'] ?? 0);

    if ($c >= $maxWin) {
        http_response_code(429);
        echo json_encode(['error' => 'rate_limited', 'retry_after' => $winSec]);
        exit;
    }
}

function logReq(PDO $pdo, array $row): void {
    // On log sans jamais casser l'API si log échoue
    try {
        $st = $pdo->prepare("
            INSERT INTO onramp_token_requests
            (user_id, ip, origin, wallet_address, blockchain, fiat_currency, crypto_currency, amount, token_hash, coinbase_http_status, coinbase_error)
            VALUES
            (:uid,:ip,:origin,:wallet,:chain,:fiat,:crypto,:amount,:th,:status,:err)
        ");
        $st->execute([
            ':uid'    => $row['user_id'],
            ':ip'     => $row['ip'],
            ':origin' => $row['origin'],
            ':wallet' => $row['wallet'],
            ':chain'  => $row['chain'],
            ':fiat'   => $row['fiat'],
            ':crypto' => $row['crypto'],
            ':amount' => $row['amount'],
            ':th'     => $row['token_hash'],
            ':status' => $row['status'],
            ':err'    => $row['err'],
        ]);
    } catch (Throwable $e) {
        // optionnel: error_log("onramp log fail: ".$e->getMessage());
    }
}

try {
    $pdo = pdo();
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rateLimit($pdo, $userId, $ip);

    // =====================================================
    // 7) Input validation
    // =====================================================
    $rawBody = file_get_contents('php://input') ?: '';
    $input   = json_decode($rawBody, true);
    if (!is_array($input)) $input = [];

    $wallet     = trim((string)($input['wallet_address'] ?? ''));
    $amountStr  = trim((string)($input['amount'] ?? ''));
    $fiat       = strtoupper(trim((string)($input['fiat_currency'] ?? 'EUR')));
    $crypto     = strtoupper(trim((string)($input['crypto_currency'] ?? 'USDC')));
    $blockchain = strtolower(trim((string)($input['blockchain'] ?? 'base')));

    if ($wallet === '' || !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        throw new RuntimeException('Invalid wallet_address');
    }

    $amount = (float)$amountStr;
    if ($amount <= 0) {
        throw new RuntimeException('Invalid amount');
    }

    if (!in_array($fiat, ['EUR','USD'], true)) $fiat = 'EUR';
    if (!in_array($crypto, ['USDC','USDT'], true)) $crypto = 'USDC';
    if (!in_array($blockchain, ['base','ethereum'], true)) $blockchain = 'base';

    // =====================================================
    // 8) Build body /onramp/v1/token
    // =====================================================
    $assets = [$crypto];

    $bodyArray = [
        'addresses' => [[
            'address'     => $wallet,
            'blockchains' => [$blockchain],
        ]],
        'assets'   => $assets,
        'clientIp' => $ip,
    ];
    $bodyJson = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

    // =====================================================
    // 9) JWT ES256 (IMPORTANT: inclure le body dans le JWT si ton CdpJwt le supporte)
    //    -> ici: on passe $bodyJson (recommandé)
    // =====================================================
    $jwt = CdpJwt::generateJwt('POST', $host, $path, $bodyJson);

    $client = new Client(['timeout' => 15]);

    $sessionToken = null;
    $cbStatus = 0;
    $cbErr    = null;

    try {
        $resp = $client->post($apiBase . $path, [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Content-Type'  => 'application/json',
            ],
            'body' => $bodyJson,
        ]);

        $cbStatus = $resp->getStatusCode();
        $respBody = json_decode((string)$resp->getBody(), true);

        if (empty($respBody['token'])) {
            throw new RuntimeException('Missing token in Coinbase response');
        }
        $sessionToken = (string)$respBody['token'];

    } catch (RequestException $e) {
        $cbStatus = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
        $cbErr    = $e->getResponse() ? (string)$e->getResponse()->getBody() : $e->getMessage();
    }

    // =====================================================
    // 10) Log MySQL (hash uniquement)
    // =====================================================
    $tokenHash = $sessionToken ? hash('sha256', $sessionToken) : null;

    logReq($pdo, [
        'user_id'    => $userId,
        'ip'         => $ip,
        'origin'     => $origin ?: null,
        'wallet'     => $wallet,
        'chain'      => $blockchain,
        'fiat'       => $fiat,
        'crypto'     => $crypto,
        'amount'     => $amount,
        'token_hash' => $tokenHash,
        'status'     => $cbStatus,
        'err'        => $cbErr,
    ]);

    if (!$sessionToken) {
        http_response_code(502);
        echo json_encode(['error' => 'coinbase_error', 'details' => 'Failed to create session token']);
        exit;
    }

    // =====================================================
    // 11) Build Onramp URL (fiat + amount pré-remplis)
    // =====================================================
    $query = http_build_query([
        'sessionToken'      => $sessionToken,
        'defaultNetwork'    => $blockchain,
        'presetFiatAmount'  => $amount,
        'fiatCurrency'      => $fiat,
        'defaultExperience' => 'buy',
    ]);

    $onrampUrl = 'https://pay.coinbase.com/buy/select-asset?' . $query;

    // =====================================================
    // 12) Response (NE PAS renvoyer session_token)
    // =====================================================
    echo json_encode([
        'success'       => true,
        'session_url'   => $onrampUrl,
        'fiat_currency' => $fiat,
        'crypto'        => $crypto,
        'blockchain'    => $blockchain,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
