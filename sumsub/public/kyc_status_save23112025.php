<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// --------- Helpers basiques ----------
function json_error(int $code, string $msg, array $extra = []): never {
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $msg], $extra), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(array $data): never {
    http_response_code(200);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

// --------- Paramètre obligatoire : applicantId ----------
$applicantId = isset($_GET['applicantId']) ? trim((string)$_GET['applicantId']) : '';
if ($applicantId === '') {
    json_error(400, 'Missing applicantId');
}

// Option: ?force=1 pour forcer un refresh depuis Sumsub
$forceRefresh = isset($_GET['force']) && $_GET['force'] === '1';

// --------- Connexion DB ----------
$dbCfg = __DIR__ . '/../config/db.php';
if (!is_file($dbCfg)) {
    json_error(500, 'DB config missing');
}
$db = require $dbCfg;

try {
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    json_error(500, 'DB connection error', ['detail' => $e->getMessage()]);
}

// --------- Lecture éventuelle en cache local ----------
try {
    $stmt = $pdo->prepare("SELECT * FROM kyc_applicants WHERE applicant_id = :aid LIMIT 1");
    $stmt->execute([':aid' => $applicantId]);
    $row = $stmt->fetch() ?: null;
} catch (Throwable $e) {
    $row = null;
}

// Si on trouve un enregistrement avec review_status NON NULL et qu’on ne force pas → on renvoie le cache
if ($row && !$forceRefresh && !empty($row['review_status'])) {
    json_ok([
        'ok'            => true,
        'applicantId'   => $row['applicant_id'],
        'externalUserId'=> $row['external_user_id'] ?? null,
        'kyc' => [
            'reviewStatus'      => $row['review_status'],
            'reviewAnswer'      => $row['review_answer'],
            'labels'            => $row['reject_labels'] ? json_decode((string)$row['reject_labels'], true) ?: [] : [],
            'moderationComment' => $row['moderation_comment'] ?? null,
            'createdAt'         => $row['created_at'] ?? null,
            'reviewedAt'        => $row['reviewed_at'] ?? null,
        ],
        'raw' => [
            'status'    => $row['raw_status'] ? json_decode((string)$row['raw_status'], true) ?: null : null,
            'applicant' => $row['raw_applicant'] ? json_decode((string)$row['raw_applicant'], true) ?: null : null,
        ],
        'source' => 'cache',
    ]);
}

// --------- Sinon : appel live à Sumsub et mise à jour DB ----------
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/SumsubClient.php';

// Secrets Sumsub
$secCfg = __DIR__ . '/../config/secrets.php';
$appToken = '';
$appSecret = '';
if (is_file($secCfg)) {
    $cfg = require $secCfg;
    if (is_array($cfg)) {
        $appToken  = (string)($cfg['SUMSUB_APP_TOKEN']  ?? '');
        $appSecret = (string)($cfg['SUMSUB_APP_SECRET'] ?? '');
    }
}
$appToken  = getenv('SUMSUB_APP_TOKEN')  ?: $appToken;
$appSecret = getenv('SUMSUB_APP_SECRET') ?: $appSecret;

if (!$appToken || !$appSecret) {
    json_error(500, 'Missing SUMSUB credentials');
}

try {
    $client   = new SumsubClient($appToken, $appSecret);
    $status   = $client->getApplicantStatus($applicantId);
    $applicant= $client->getApplicant($applicantId);
} catch (SumsubApiException $e) {
    json_error(
        max(400, $e->statusCode ?: 500),
        'Sumsub error: '.$e->getMessage(),
        ['sumsub' => $e->responseJson]
    );
} catch (Throwable $e) {
    json_error(500, 'Unexpected error calling Sumsub', ['detail' => $e->getMessage()]);
}

// --------- Extraction des champs utiles ----------
$reviewStatus = $status['reviewStatus'] ?? null;
$reviewAnswer = $status['reviewAnswer'] ?? null;
$labels       = $status['rejectLabels'] ?? [];
if (!is_array($labels)) $labels = [];

$moderationComment = $status['moderationComment'] ?? null;
$createdAt         = $status['createdAt'] ?? ($row['created_at'] ?? null);
$reviewedAt        = $status['reviewedAt'] ?? null;

$externalUserId = $applicant['externalUserId'] ?? ($row['external_user_id'] ?? null);

// --------- Upsert dans kyc_applicants ----------
try {
    $stmt = $pdo->prepare("
        INSERT INTO kyc_applicants (
            applicant_id,
            external_user_id,
            review_status,
            review_answer,
            reject_labels,
            moderation_comment,
            created_at,
            reviewed_at,
            raw_status,
            raw_applicant
        )
        VALUES (
            :aid,
            :ext,
            :rev_status,
            :rev_answer,
            :rej_labels,
            :moder_comment,
            :created_at,
            :reviewed_at,
            :raw_status,
            :raw_applicant
        )
        ON DUPLICATE KEY UPDATE
            external_user_id   = VALUES(external_user_id),
            review_status      = VALUES(review_status),
            review_answer      = VALUES(review_answer),
            reject_labels      = VALUES(reject_labels),
            moderation_comment = VALUES(moderation_comment),
            created_at         = COALESCE(kyc_applicants.created_at, VALUES(created_at)),
            reviewed_at        = VALUES(reviewed_at),
            raw_status         = VALUES(raw_status),
            raw_applicant      = VALUES(raw_applicant),
            updated_at         = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        ':aid'           => $applicantId,
        ':ext'           => $externalUserId,
        ':rev_status'    => $reviewStatus,
        ':rev_answer'    => $reviewAnswer,
        ':rej_labels'    => json_encode($labels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ':moder_comment' => $moderationComment,
        ':created_at'    => $createdAt ? date('Y-m-d H:i:s', strtotime($createdAt)) : date('Y-m-d H:i:s'),
        ':reviewed_at'   => $reviewedAt ? date('Y-m-d H:i:s', strtotime($reviewedAt)) : null,
        ':raw_status'    => json_encode($status, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ':raw_applicant' => json_encode($applicant, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    ]);
} catch (Throwable $e) {
    // On logue l’erreur mais on renvoie quand même la réponse live
    // (Si tu as un logger, remplace par tkl_log() / error_log() etc.)
    error_log('kyc_status.php upsert error: '.$e->getMessage());
}

// --------- Réponse JSON finale (live) ----------
json_ok([
    'ok'            => true,
    'applicantId'   => $applicantId,
    'externalUserId'=> $externalUserId,
    'kyc' => [
        'reviewStatus'      => $reviewStatus,
        'reviewAnswer'      => $reviewAnswer,
        'labels'            => $labels,
        'moderationComment' => $moderationComment,
        'createdAt'         => $createdAt,
        'reviewedAt'        => $reviewedAt,
    ],
    'raw' => [
        'status'    => $status,
        'applicant' => $applicant,
    ],
    'source' => 'sumsub_live',
]);
