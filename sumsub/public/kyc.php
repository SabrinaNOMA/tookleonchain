<?php
declare(strict_types=1);

if (isset($_GET['debug'])) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';

/** charge secrets si présents en fichier */
$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
  $cfg = require $configFile;
  if (is_array($cfg)) {
    $_SERVER['SUMSUB_APP_TOKEN']  = $_SERVER['SUMSUB_APP_TOKEN']  ?? ($cfg['SUMSUB_APP_TOKEN']  ?? '');
    $_SERVER['SUMSUB_APP_SECRET'] = $_SERVER['SUMSUB_APP_SECRET'] ?? ($cfg['SUMSUB_APP_SECRET'] ?? '');
    $_SERVER['SUMSUB_LEVEL']      = $_SERVER['SUMSUB_LEVEL']      ?? ($cfg['SUMSUB_LEVEL']      ?? 'leveltookle');
  }
}

$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: ($_SERVER['SUMSUB_APP_TOKEN'] ?? '');
$appSecret = getenv('SUMSUB_APP_SECRET') ?: ($_SERVER['SUMSUB_APP_SECRET'] ?? '');
$levelName = $_GET['level'] ?? (getenv('SUMSUB_LEVEL') ?: ($_SERVER['SUMSUB_LEVEL'] ?? 'leveltookle'));

if (!$appToken || !$appSecret) {
  http_response_code(500);
  echo json_encode(['error' => 'Missing SUMSUB_APP_TOKEN / SUMSUB_APP_SECRET']);
  exit;
}

// Paramètres test (query string)
$level    = $levelName;
$email    = $_GET['email'] ?? 'john.doe@example.com';
$phone    = $_GET['phone'] ?? '+33123456789';

try {
  $client = new SumsubClient($appToken, $appSecret);

  // 1) Create applicant
  $externalUserId = 'user_' . bin2hex(random_bytes(6));
  $applicant = $client->createApplicant([
    'externalUserId' => $externalUserId,
    'email' => $email,
    'phone' => $phone,
    'fixedInfo' => [
      'country' => 'FRA',
      'placeOfBirth' => 'Paris',
    ],
  ], $level);

  $applicantId = $applicant['id'] ?? null;

  // 2) Generate SDK access token (optionnel ici)
  $tokenResp = $client->generateSdkAccessToken($externalUserId, $level, 600, [
    'email' => $email,
    'phone' => $phone,
  ]);

  // 3) Generate websdk permalink
  $linkResp = $client->generateWebsdkLink($externalUserId, $level, 1800, [
    'email' => $email,
    'phone' => $phone,
  ]);

  echo json_encode([
    'status'         => 'ok',
    'externalUserId' => $externalUserId,
    'applicantId'    => $applicantId,
    'accessToken'    => $tokenResp['token'] ?? null,
    'websdkUrl'      => $linkResp['url'] ?? null,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (SumsubApiException $e) {
  http_response_code(max(400, $e->statusCode ?: 500));
  echo json_encode([
    'error'      => $e->getMessage(),
    'statusCode' => $e->statusCode,
    'response'   => $e->responseJson,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
?>