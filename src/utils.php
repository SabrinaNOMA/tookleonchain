<?php
/**
 * Utility functions for Token Generation and Helpers
 */

/**
 * Generates a secure, unique Sale URL Token.
 * Matches Router Regex: #^p/([A-Za-z0-9]{6,64})$#
 * * @param PDO $pdo The database connection
 * @return string The unique token
 * @throws Exception If uniqueness cannot be established after retries
 */
function generateUniqueSaleToken(PDO $pdo): string {
    $maxRetries = 5;
    $attempts = 0;

    do {
        // 1. Generate 32 characters of random hex (0-9, a-f)
        // This fits perfectly within your 6-64 char limit.
        // random_bytes is cryptographically secure.
        $token = bin2hex(random_bytes(16)); 

        // 2. Check for collisions in the database
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM token_sale_pages WHERE sale_url = ?");
        $stmt->execute([$token]);
        $exists = $stmt->fetchColumn();

        $attempts++;
    } while ($exists > 0 && $attempts < $maxRetries);

    if ($exists > 0) {
        throw new Exception("Failed to generate unique sale token after $maxRetries attempts.");
    }

    return $token;
}