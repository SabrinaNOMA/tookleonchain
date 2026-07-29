<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Psr\Http\Message\RequestInterface;

/**
 * Middleware de signature Sumsub.
 * Signature HMAC-SHA256 (hex minuscule) sur :
 *   <ts>.<METHOD>.<path?query>.<body>  (NB: concaténation avec ".")
 * En réalité Sumsub documente: ts + method + pathWithQuery + body.
 * Ici on utilise strictement la concaténation PHP ".".
 */
final class SumsubSignatureMiddleware
{
    public function __construct(
        private readonly string $appToken,
        private readonly string $appSecret
    ) {}

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $ts = (string) time();

            // Corps réel envoyé (IMPORTANT: signer le body tel qu'il part)
            $bodyStream = $request->getBody();
            $body = (string) $bodyStream;
            if ($bodyStream->isSeekable()) {
                $bodyStream->rewind();
            }

            // Méthode + path (forcer leading slash) + query
            $method = strtoupper($request->getMethod());
            $uri    = $request->getUri();

            $path = $uri->getPath();
            if ($path === '' || $path[0] !== '/') {
                $path = '/' . $path; // <— jamais de "+"
            }
            $query = $uri->getQuery();
            $pathWithQuery = $path . ($query !== '' ? '?' . $query : '');

            // ✅ Concaténation avec "."
            $toSign = $ts . $method . $pathWithQuery . $body;
            $sig = strtolower(hash_hmac('sha256', $toSign, $this->appSecret));

            // En-têtes Sumsub requis
            $request = $request
                ->withHeader('X-App-Token', $this->appToken)
                ->withHeader('X-App-Access-Ts', $ts)
                ->withHeader('X-App-Access-Sig', $sig);

            // Content-Type JSON par défaut pour POST/PUT/PATCH
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && !$request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/json');
            }

            return $handler($request, $options);
        };
    }
}

/** Exception API homogène */
final class SumsubApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?array $responseJson,
        string $message = 'Sumsub API error'
    ) {
        parent::__construct($message . ' (HTTP ' . $statusCode . ')');
    }
}

/** Client API Sumsub (Guzzle + retry + options réseau) */
final class SumsubClient
{
    private Client $http;

    public function __construct(
        string $appToken,
        string $appSecret,
        string $baseUri = 'https://api.sumsub.com',
        ?\Psr\Log\LoggerInterface $logger = null,
        int $maxRetries = 3
    ) {
        $stack = HandlerStack::create();

        // Retry 429/5xx et erreurs réseau
        $stack->push(Middleware::retry(
            function ($retries, $request, $response = null, $exception = null) use ($maxRetries) {
                if ($retries >= $maxRetries) return false;
                if ($exception instanceof \Throwable) return true; // erreurs réseau
                if ($response) {
                    $code = $response->getStatusCode();
                    return $code === 429 || ($code >= 500 && $code <= 599);
                }
                return false;
            },
            function ($retries, $response) {
                $base = 250; // ms
                return (int) (pow(2, $retries) * $base + random_int(0, 200));
            }
        ));

        // Signature
        $stack->push(new SumsubSignatureMiddleware($appToken, $appSecret), 'sumsub_sign');

        // Logging optionnel
        if ($logger) {
            $stack->push(Middleware::log(
                $logger,
                new MessageFormatter('{method} {uri} - {code} {res_body}')
            ));
        }

        // Options réseau (ENV)
        $clientOptions = [
            'base_uri'    => rtrim($baseUri, '/') . '/',
            'handler'     => $stack,
            'timeout'     => 30,
            'http_errors' => false, // on remonte le JSON d'erreur 4xx/5xx
        ];

        if (filter_var(getenv('SUMSUB_FORCE_IPV4') ?: '', FILTER_VALIDATE_BOOL)) {
            $clientOptions['curl'][CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        $caFile = getenv('SUMSUB_CAINFO') ?: null;
        $clientOptions['verify'] = $caFile ?: true;

        $this->http = new Client($clientOptions);
    }

function normalizeExternalUserId(string $id): string {
    // garde uniquement a-zA-Z0-9_- ; remplace le reste par _
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $id);
    // optionnel: limite longueur
    return substr($id, 0, 128);
}


    /** POST /resources/applicants */
public function createApplicant(string $extUserId, ?string $email = null, ?string $phone = null): array
{
    $payload = [
        'externalUserId' => $extUserId,
    ];

    if ($email !== null && $email !== '') {
        $payload['email'] = $email;
    }
    if ($phone !== null && $phone !== '') {
        $payload['phone'] = $phone;
    }

    return $this->requestJson('POST', 'resources/applicants', [
        'body'    => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'headers' => ['Content-Type' => 'application/json'],
    ]);
}

/** POST /resources/applicants?levelName=... */
public function createApplicantWithLevel(string $extUserId, string $levelName, ?string $email = null, ?string $phone = null): array
{
    $payload = [
        'externalUserId' => $extUserId,
    ];
    if ($email !== null && $email !== '') $payload['email'] = $email;
    if ($phone !== null && $phone !== '') $payload['phone'] = $phone;

    $uri = 'resources/applicants?levelName=' . rawurlencode($levelName);

    return $this->requestJson('POST', $uri, [
        'body'    => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'headers' => ['Content-Type' => 'application/json'],
    ]);
}



    /** POST /resources/accessTokens/sdk */
    public function generateSdkAccessToken(
        string $externalUserId,
        string $levelName,
        int $ttlInSecs = 600,
        array $applicantIdentifiers = []
    ): array {
        $payload = [
            'userId'    => $externalUserId,
            'levelName' => $levelName,
            'ttlInSecs' => $ttlInSecs,
        ];
        if ($applicantIdentifiers) {
            $payload['applicantIdentifiers'] = $applicantIdentifiers;
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->requestJson('POST', 'resources/accessTokens/sdk', [
            'body'    => $body,
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    /** POST /resources/sdkIntegrations/levels/-/websdkLink */
    public function generateWebsdkLink(
        string $externalUserId,
        string $levelName,
        int $ttlInSecs = 1800,
        array $applicantIdentifiers = []
    ): array {
        $payload = [
            'levelName' => $levelName,
            'userId'    => $externalUserId,
            'ttlInSecs' => $ttlInSecs,
        ];
        if ($applicantIdentifiers) {
            $payload['applicantIdentifiers'] = $applicantIdentifiers;
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->requestJson('POST', 'resources/sdkIntegrations/levels/-/websdkLink', [
            'body'    => $body,
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    /* ===========================
     *         MÉTHODES GET
     * =========================== */

    /** GET /resources/applicants/{applicantId} — Objet applicant complet */
    public function getApplicant(string $applicantId): array
    {
        // Certains environnements / SDKs utilisent /one; la doc standard accepte /{id}
        return $this->requestJson('GET', 'resources/applicants/' . rawurlencode($applicantId));
    }

    /** GET /resources/applicants/{applicantId}/status — Statut/décision */
    public function getApplicantStatus(string $applicantId): array
    {
        return $this->requestJson('GET', 'resources/applicants/' . rawurlencode($applicantId) . '/status');
    }

    /** 
 * GET /resources/applicants/-;externalUserId={externalUserId}/one
 * IMPORTANT: Certains comptes Sumsub refusent cet endpoint (HTTP 403).
 * On gère 403/404 en retournant null pour permettre au code appelant de faire un fallback (createApplicant).
 */
public function getApplicantByExternalUserId(string $externalUserId): ?array
{
    $uri = 'resources/applicants/-;externalUserId=' . rawurlencode($externalUserId) . '/one';

    try {
        return $this->requestJson('GET', $uri);
    } catch (SumsubApiException $e) {
        // ✅ Sur ton compte, cet endpoint peut être interdit -> 403
        if ($e->statusCode === 403 || $e->statusCode === 404) {
            return null;
        }
        throw $e;
    }
}

  
    /** GET /resources/applicants/{applicantId}/verifications — Détails AML */
    public function getApplicantVerifications(string $applicantId): array
    {
        return $this->requestJson('GET', 'resources/applicants/' . rawurlencode($applicantId) . '/verifications');
    }

    /** Helper JSON générique (avec endpoint dans les erreurs) */
    private function requestJson(string $method, string $uri, array $options = []): array
    {
        try {
            $res = $this->http->request($method, ltrim($uri, '/'), $options);
        } catch (GuzzleException $e) {
            throw new SumsubApiException(0, null, 'Transport error: ' . $e->getMessage());
        }

        $status = $res->getStatusCode();
        $body   = (string) $res->getBody();
        $json   = $body !== '' ? json_decode($body, true) : null;

        if ($status < 200 || $status >= 300) {
            $msg = (is_array($json) && isset($json['message'])) ? (string)$json['message'] : 'Sumsub error';
            $msg .= ' [' . $method . ' /' . ltrim($uri, '/') . ']';
            throw new SumsubApiException($status, is_array($json) ? $json : null, $msg);
        }

        return is_array($json) ? $json : [];
    }
}
?>