<?php
/**
 * Sumsub Central Database Configuration Provider
 * Dynamically binds to the active environment config (Local / Preprod / Prod)
 */
require_once __DIR__ . '/../../config.php';

return [
  'dsn'  => "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
  'user' => DB_USER,
  'pass' => DB_PASS,
];