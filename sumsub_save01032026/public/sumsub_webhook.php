<?php
declare(strict_types=1);
if (isset($_GET['debug'])) { ini_set('display_errors','1'); error_reporting(E_ALL); }
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/KycAmlRepo.php';

$db = require __DIR__ . '/../config/db.php';
$repo = new KycAmlRepo($db);

$raw = file_get_contents('php://input');
$event = json_decode($raw, true);
file_put_contents(__DIR__.'/../logs/webhook.log', date('c')." ".$raw."\n", FILE_APPEND);

if (!is_array($event) || empty($event['type'])) { http_response_code(400); echo 'invalid payload'; exit; }

$type = (string)$event['type'];
$data = $event['data'] ?? [];
$applicantId = $data['applicantId'] ?? ($data['applicant']['id'] ?? null);

if ($type === 'applicantReviewed' && $applicantId) {
  $status = [
    'reviewStatus' => $data['reviewStatus'] ?? null,
    'reviewAnswer' => $data['reviewResult'] ?? ($data['reviewAnswer'] ?? null),
    'rejectLabels' => $data['rejectLabels'] ?? null,
    'moderationComment' => $data['moderationComment'] ?? null,
    'reviewedAt' => $data['reviewedAt'] ?? null,
  ];
  $repo->upsertKycStatus($applicantId, null, $status, null);
}
echo 'ok';