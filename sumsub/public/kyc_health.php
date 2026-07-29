<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// 1) Charger (facultatif) les secrets depuis ../config/secrets.php
$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
    $cfg = require $configFile;
    if (is_array($cfg)) {
        if (!getenv('SUMSUB_APP_TOKEN') && !isset($_SERVER['SUMSUB_APP_TOKEN']) && !empty($cfg['SUMSUB_APP_TOKEN'])) {
            $_SERVER['SUMSUB_APP_TOKEN'] = $cfg['SUMSUB_APP_TOKEN'];
        }
        if (!getenv('SUMSUB_APP_SECRET') && !isset($_SERVER['SUMSUB_APP_SECRET']) && !empty($cfg['SUMSUB_APP_SECRET'])) {
            $_SERVER['SUMSUB_APP_SECRET'] = $cfg['SUMSUB_APP_SECRET'];
        }
    }
}

// 2) Bâtir les checks
$checks = [
  'php_version' => PHP_VERSION,
  'sapi'        => PHP_SAPI,
  'vendor_autoload_exists' => file_exists(__DIR__ . '/../vendor/autoload.php'),
  'sumsub_client_exists'   => file_exists(__DIR__ . '/../src/SumsubClient.php'),
  'ext' => [
    'curl'     => extension_loaded('curl'),
    'openssl'  => extension_loaded('openssl'),
    'json'     => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
  ],
  'env_vars_present' => [
    'SUMSUB_APP_TOKEN'  => (getenv('SUMSUB_APP_TOKEN')  !== false) || isset($_SERVER['SUMSUB_APP_TOKEN']),
    'SUMSUB_APP_SECRET' => (getenv('SUMSUB_APP_SECRET') !== false) || isset($_SERVER['SUMSUB_APP_SECRET']),
  ],
];

// 3) Ping réseau vers api.sumsub.com (HEAD)
$ch = curl_init('https://api.sumsub.com/');
curl_setopt_array($ch, [
  CURLOPT_NOBODY => true,
  CURLOPT_TIMEOUT => 5,
  CURLOPT_RETURNTRANSFER => true,
]);
curl_exec($ch);
$checks['network_to_sumsub'] = [
  'ok'   => curl_errno($ch) === 0,
  'code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
  'err'  => curl_error($ch),
];
curl_close($ch);

// 4) Sortie JSON
echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
