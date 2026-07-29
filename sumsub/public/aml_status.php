<?php
declare(strict_types=1);
if (isset($_GET['debug'])) { ini_set('display_errors','1'); error_reporting(E_ALL); }
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
$db = require __DIR__ . '/../config/db.php';
$pdo = new PDO($db['dsn'],$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

$applicantId = $_GET['applicantId'] ?? null;
if (!$applicantId) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'applicantId required']); exit; }

if (isset($_GET['refresh'])) { require __DIR__ . '/sync_kyc_aml.php'; exit; }

$st = $pdo->prepare("SELECT applicant_id, sanctions_result, pep_result, adverse_result, checked_at, updated_at FROM kyc_aml WHERE applicant_id=:id");
$st->execute([':id'=>$applicantId]);
$row = $st->fetch();

echo json_encode(['ok'=> (bool)$row, 'data'=>$row ?: null], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
