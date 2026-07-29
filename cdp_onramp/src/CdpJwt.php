<?php
declare(strict_types=1);

namespace App;

use Firebase\JWT\JWT;

class CdpJwt
{
    /**
     * Génère un JWT ES256 conforme aux exemples CDP.
     *
     * Claims :
     *   sub = key_name (organizations/.../apiKeys/...)
     *   iss = "cdp"
     *   nbf = now
     *   exp = now + 120
     *   uri = "METHOD host/path" (selon doc CDP)
     *
     * Headers :
     *   kid   = key_name (mis via keyId de JWT::encode)
     *   nonce = random hex
     *
     * NOTE: $bodyJson est optionnel pour compatibilité avec ton code,
     * mais CDP demande surtout uri (pas de signature du body).
     */
    public static function generateJwt(string $method, string $host, string $path, ?string $bodyJson = null): string
    {
        $keyName   = $_ENV['CDP_API_KEY_NAME']   ?? '';
        $keySecret = $_ENV['CDP_API_KEY_SECRET'] ?? '';

        $keyName = trim($keyName);
        $keySecret = (string)$keySecret;

        if ($keyName === '') {
            throw new \Exception('CDP_API_KEY_NAME manquant dans .env (format attendu: organizations/.../apiKeys/...)');
        }
        if (trim($keySecret) === '') {
            throw new \Exception('CDP_API_KEY_SECRET manquant/vide dans .env');
        }

        // 1) Nettoyage du secret (cas .env: quotes + \n littéraux)
        $privateKeyPem = self::normalizePem($keySecret);

        // 2) Validation OpenSSL explicite (donne un vrai message d’erreur)
        $pkey = openssl_pkey_get_private($privateKeyPem);
        if ($pkey === false) {
            $err = self::collectOpenSslErrors();
            throw new \Exception('OpenSSL unable to validate key. ' . ($err ?: 'Vérifie que la clé est bien une EC PRIVATE KEY (ES256).'));
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

        // Coinbase recommande un nonce dans le header
        $headers = [
            'nonce' => bin2hex(random_bytes(16)),
        ];

        // kid = keyName (mis proprement via param keyId)
        return JWT::encode($payload, $privateKeyPem, 'ES256', $keyName, $headers);
    }

    /**
     * Normalise un PEM stocké dans .env (souvent en une ligne avec \n).
     */
    private static function normalizePem(string $raw): string
    {
        $s = trim($raw);

        // Retire guillemets externes si présents
        if ((str_starts_with($s, '"') && str_ends_with($s, '"')) ||
            (str_starts_with($s, "'") && str_ends_with($s, "'"))) {
            $s = substr($s, 1, -1);
        }

        // Convertit "\n" littéraux en vrais retours
        $s = str_replace(["\\r\\n", "\\n", "\\r"], ["\n", "\n", "\n"], $s);

        // Normalise les retours Windows -> \n
        $s = str_replace(["\r\n", "\r"], "\n", $s);

        // Trim de sécurité
        $s = trim($s);

        // Vérif basique format PEM
        if (!str_contains($s, 'BEGIN') || !str_contains($s, 'PRIVATE KEY')) {
            throw new \Exception('CDP_API_KEY_SECRET ne ressemble pas à une clé PEM (BEGIN ... PRIVATE KEY).');
        }

        return $s;
    }

    /**
     * Récupère les erreurs OpenSSL (utile pour debug prod sans afficher la clé).
     */
    private static function collectOpenSslErrors(): string
    {
        $errs = [];
        while ($msg = openssl_error_string()) {
            $errs[] = $msg;
            if (count($errs) >= 5) break;
        }
        return implode(' | ', $errs);
    }
}
