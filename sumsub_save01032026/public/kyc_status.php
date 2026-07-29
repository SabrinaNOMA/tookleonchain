<?php
declare(strict_types=1);

// On force l'affichage des erreurs pour le debug, mais on les capture pour le JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Fonction de sortie JSON d'erreur
function json_terminate(int $code, string $msg, array $extra = []): never {
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $msg], $extra), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(array $data): never {
    http_response_code(200);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

// --- BLOC TRY GLOBAL POUR ÉVITER L'ERREUR 500 ---
try {

    // 1. Vérification paramètres
    $applicantId = isset($_GET['applicantId']) ? trim((string)$_GET['applicantId']) : '';
    if ($applicantId === '') {
        json_terminate(400, 'Missing applicantId');
    }

    //$forceRefresh = isset($_GET['force']) && $_GET['force'] === '1';
	$forceRefresh = true; // isset($_GET['force']) && $_GET['force'] === '1';

    // 2. Connexion DB
    $dbCfg = __DIR__ . '/../config/db.php';
    if (!is_file($dbCfg)) {
        json_terminate(500, 'DB config missing');
    }
    $db = require $dbCfg;

    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 3. Cache Local (si pas force)
    $row = null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM kyc_applicants WHERE applicant_id = :aid LIMIT 1");
        $stmt->execute([':aid' => $applicantId]);
        $row = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        // Table n'existe peut-être pas encore, on continue
    }

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
            'source' => 'cache',
        ]);
    }

    // 4. Appel Sumsub Live
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/SumsubClient.php';

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
    
    if (!$appToken || !$appSecret) {
        json_terminate(500, 'Missing SUMSUB credentials');
    }

    $client = new SumsubClient($appToken, $appSecret);
    $status = $client->getApplicantStatus($applicantId);
    $applicant = $client->getApplicant($applicantId);

    // 5. Extraction sécurisée (C'est ici que ça plantait avant)
    if (!is_array($status)) {
        // Si l'API ne renvoie pas un tableau, on arrête proprement
        json_terminate(502, 'Invalid response from Sumsub API', ['response' => $status]);
    }

    $reviewStatus = $status['reviewStatus'] ?? null;

    // --- CORRECTION CRITIQUE POUR "GREEN" ---
    // On utilise une variable temporaire pour éviter les erreurs d'accès
    $reviewResultObj = $status['reviewResult'] ?? [];
    
    // On cherche d'abord dans reviewResult['reviewAnswer']
    // Sinon à la racine (cas legacy)
    $reviewAnswer = $reviewResultObj['reviewAnswer'] 
                 ?? $status['reviewAnswer'] 
                 ?? null;

    // Idem pour les labels
    $labels = $reviewResultObj['rejectLabels'] 
           ?? $status['rejectLabels'] 
           ?? [];
           
    if (!is_array($labels)) $labels = [];

    $moderationComment = $status['moderationComment'] ?? null;
    // Gestion des dates (parfois absentes)
    $createdAt  = $status['createdAt'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s'));
    $reviewedAt = $status['reviewedAt'] ?? null;

    // Récupération ID Externe
    $extId = null;
    if (is_array($applicant)) {
        $extId = $applicant['externalUserId'] ?? null;
    }
    // Fallback sur la DB si l'API ne renvoie pas l'info
    if (!$extId && $row) {
        $extId = $row['external_user_id'];
    }

    // 6. Mise à jour BDD (Upsert)
    $stmt = $pdo->prepare("
        INSERT INTO kyc_applicants (
            applicant_id, external_user_id, review_status, review_answer, reject_labels, 
            moderation_comment, created_at, reviewed_at, raw_status, raw_applicant, updated_at
        ) VALUES (
            :aid, :ext, :rs, :ra, :lbl, :com, :cat, :rat, :raws, :rawa, NOW()
        )
        ON DUPLICATE KEY UPDATE
            review_status      = VALUES(review_status),
            review_answer      = VALUES(review_answer),
            reject_labels      = VALUES(reject_labels),
            moderation_comment = VALUES(moderation_comment),
            reviewed_at        = VALUES(reviewed_at),
            raw_status         = VALUES(raw_status),
            raw_applicant      = VALUES(raw_applicant),
            updated_at         = NOW()
    ");

    // Formatage des dates pour MySQL
    $fmtDate = fn($d) => $d ? date('Y-m-d H:i:s', strtotime($d)) : null;

    $stmt->execute([
        ':aid'  => $applicantId,
        ':ext'  => $extId,
        ':rs'   => $reviewStatus,
        ':ra'   => $reviewAnswer,
        ':lbl'  => json_encode($labels),
        ':com'  => $moderationComment,
        ':cat'  => $fmtDate($createdAt),
        ':rat'  => $fmtDate($reviewedAt),
        ':raws' => json_encode($status),
        ':rawa' => json_encode($applicant),
    ]);

    // 7. Réponse Finale
    json_ok([
        'ok'            => true,
        'applicantId'   => $applicantId,
        'externalUserId'=> $extId,
        'kyc' => [
            'reviewStatus' => $reviewStatus,
            'reviewAnswer' => $reviewAnswer, // Doit être "GREEN" maintenant
            'reviewedAt'   => $reviewedAt,
            'labels'       => $labels
        ],
        'source' => 'sumsub_live_updated',
    ]);

} catch (Throwable $e) {
    // Capture de l'erreur fatale 500 et affichage propre
    json_terminate(500, 'Server Error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}