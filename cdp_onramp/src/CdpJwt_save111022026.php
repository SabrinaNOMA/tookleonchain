<?php
namespace App;

use Firebase\JWT\JWT;

class CdpJwt
{
    /**
     * Génère un JWT ES256 comme dans l'exemple Coinbase support.
     *
     * Payload :
     *   sub = key_name (organizations/.../apiKeys/...)
     *   iss = "cdp"
     *   nbf = now
     *   exp = now + 120
     *   uri = "METHOD host/path"
     *
     * Header :
     *   kid   = key_name
     *   nonce = random hex
     */
    public static function generateJwt(string $method, string $host, string $path): string
    {
        $keyName   = $_ENV['CDP_API_KEY_NAME']   ?? null;
        $keySecret = $_ENV['CDP_API_KEY_SECRET'] ?? null;

        if (!$keyName) {
            throw new \Exception('CDP_API_KEY_NAME manquant dans .env');
        }
        if (!$keySecret) {
            throw new \Exception('CDP_API_KEY_SECRET manquant dans .env');
        }

        // On convertit les "\n" littéraux en vrais sauts de ligne pour obtenir le PEM correct
        $privateKeyPem = str_replace(["\\r", "\\n"], ["\r", "\n"], $keySecret);

        if (trim($privateKeyPem) === '') {
            throw new \Exception('Clé privée vide après conversion du CDP_API_KEY_SECRET');
        }

        $now = time();
        $uri = sprintf('%s %s%s', strtoupper($method), $host, $path);

        $payload = [
            'sub' => $keyName,
            'iss' => 'cdp',
            'nbf' => $now,
            'exp' => $now + 120,
            'uri' => $uri,
        ];

        $headers = [
            'kid'   => $keyName,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        // ES256 avec la clé EC PEM
        return JWT::encode($payload, $privateKeyPem, 'ES256', null, $headers);
    }
}
