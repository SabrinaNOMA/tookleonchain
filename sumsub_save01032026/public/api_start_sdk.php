<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (isset($_GET['debug'])) { ini_set('display_errors','1'); error_reporting(E_ALL); }

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';

$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
  $cfg = require $configFile;
  $_SERVER['SUMSUB_APP_TOKEN']  = $_SERVER['SUMSUB_APP_TOKEN']  ?? ($cfg['SUMSUB_APP_TOKEN']  ?? '');
  $_SERVER['SUMSUB_APP_SECRET'] = $_SERVER['SUMSUB_APP_SECRET'] ?? ($cfg['SUMSUB_APP_SECRET'] ?? '');
  $_SERVER['SUMSUB_LEVEL']      = $_SERVER['SUMSUB_LEVEL']      ?? ($cfg['SUMSUB_LEVEL']      ?? 'leveltookle');
}
$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: ($_SERVER['SUMSUB_APP_TOKEN'] ?? '');
$appSecret = getenv('SUMSUB_APP_SECRET') ?: ($_SERVER['SUMSUB_APP_SECRET'] ?? '');
$level     = $_GET['level'] ?? (getenv('SUMSUB_LEVEL') ?: ($_SERVER['SUMSUB_LEVEL'] ?? 'leveltookle'));
if (!$appToken || !$appSecret) { http_response_code(500); echo json_encode(['error'=>'Missing SUMSUB credentials']); exit; }

$user = $_GET['user'] ?? '';
$email= $_GET['email'] ?? null;
$phone= $_GET['phone'] ?? null;
if (!$user) { http_response_code(400); echo json_encode(['error'=>'Provide ?user=externalUserId']); exit; }

$client = new SumsubClient($appToken, $appSecret);
try {
  try { $applicant = $client->getApplicantByExternalUserId($user); }
  catch (SumsubApiException $e) {
    if ($e->statusCode === 404) {
      $payload = ['externalUserId'=>$user, 'email'=>$email, 'phone'=>$phone, 'fixedInfo'=>['country'=>'FRA']];
      $applicant = $client->createApplicant($payload, $level);
    } else { throw $e; }
  }
  $token = $client->generateSdkAccessToken($user, $level, 900, array_filter(['email'=>$email,'phone'=>$phone]));
  $link  = $client->generateWebsdkLink($user, $level, 1800, array_filter(['email'=>$email,'phone'=>$phone]));
  echo json_encode(['ok'=>true,'applicantId'=>$applicant['id']??null,'token'=>$token['token']??null,'websdk'=>$link['url']??null]);
} catch (SumsubApiException $e) {
  http_response_code(max(400,$e->statusCode?:500));
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'details'=>$e->responseJson]);
}