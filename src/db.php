<?php
/**
 * src/db.php
 *
 * Provides the PDO database connection object by including the config file.
 * This is a standalone file that can be included by other PHP scripts
 * to get a database connection without re-defining the configuration.
 */

// Use __DIR__ to get the correct path relative to this file's location.
require_once __DIR__ . '/../config.php';

// --- Data Source Name (DSN) ---
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// --- PDO Connection Options ---
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Create PDO Instance ---
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Auto-migrate missing column for smooth deployments
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM vesting_token LIKE 'percent_supply_vesting'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE vesting_token ADD COLUMN percent_supply_vesting DECIMAL(12,6) NULL AFTER vesting_block_name");
        }
    } catch (\PDOException $e) {
        // Ignore errors if table doesn't exist yet
    }
} catch (\PDOException $e) {
    // In a real application, you would log this error and show a user-friendly message.
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

return $pdo;
