<?php
declare(strict_types=1);
if (isset($_GET['debug'])) { ini_set('display_errors','1'); error_reporting(E_ALL); }
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';
require __DIR__ . '/../src/KycAmlRepo.php';

$configFile = __DIR__ . '/../config/secrets.php';
if (is_file($configFile)) {
  $cfg = require $configFile;
  $_SERVER['SUMSUB_APP_TOKEN']  = $_SERVER['SUMSUB_APP_TOKEN']  ?? ($cfg['SUMSUB_APP_TOKEN']  ?? '');
  $_SERVER['SUMSUB_APP_SECRET'] = $_SERVER['SUMSUB_APP_SECRET'] ?? ($cfg['SUMSUB_APP_SECRET'] ?? '');
}
$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: ($_SERVER['SUMSUB_APP_TOKEN'] ?? '');
$appSecret = getenv('SUMSUB_APP_SECRET') ?: ($_SERVER['SUMSUB_APP_SECRET'] ?? '');
if (!$appToken || !$appSecret) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Missing SUMSUB credentials']); exit; }

$db = require __DIR__ . '/../config/db.php';
$repo  = new KycAmlRepo($db);
$client= new SumsubClient($appToken, $appSecret);

$applicantId = $_GET['applicantId'] ?? null;
$externalId  = $_GET['user'] ?? null;

try {
  if (!$applicantId && $externalId) {
    $applicant = $client->getApplicantByExternalUserId($externalId);
    $applicantId = $applicant['id'] ?? null;
  }
  if (!$applicantId) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Provide applicantId or user']); exit; }

  $status     = $client->getApplicantStatus($applicantId);
  $applicant  = $client->getApplicant($applicantId);
  $verifs     = $client->getApplicantVerifications($applicantId);
  $extUserId  = $applicant['externalUserId'] ?? null;

  $repo->upsertKycStatus($applicantId, $extUserId, $status, $applicant);
  $repo->upsertAmlStatus($applicantId, $verifs);

  echo json_encode(['ok'=>true,'applicantId'=>$applicantId,'kyc'=>$status,'verifications'=>$verifs], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
} catch (SumsubApiException $e) {
  http_response_code(max(400,$e->statusCode?:500));
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'details'=>$e->responseJson], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}