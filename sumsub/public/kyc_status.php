<?php
// sumsub/public/kyc_status.php
// API Endpoint pour vérifier et synchroniser le statut KYC
// VERSION ROBUSTE : Imite la logique du script de sauvegarde (Double Appel API)

// 1. Headers JSON et Error Handling
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0'); 
error_reporting(E_ALL);

function json_terminate(int $code, string $msg, array $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $msg], $extra));
    exit;
}

// 2. Gestion Session
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 7;
    session_set_cookie_params($lifetime, '/', '', isset($_SERVER['HTTPS']), true);
    session_start();
}

// 3. Auth Check
if (!isset($_SESSION['user_id'])) {
    json_terminate(401, 'Unauthorized');
}

$userId = $_SESSION['user_id'];
$applicantId = filter_input(INPUT_GET, 'applicantId', FILTER_SANITIZE_STRING);
$externalUserId = filter_input(INPUT_GET, 'externalUserId', FILTER_SANITIZE_STRING);
$force = filter_input(INPUT_GET, 'force', FILTER_VALIDATE_BOOLEAN);

if (!$applicantId && !$externalUserId) {
    json_terminate(400, 'Missing applicantId OR externalUserId');
}

// 4. Config DB
$dbCfgPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbCfgPath)) {
    json_terminate(500, 'DB Config missing');
}
$dbCfg = require $dbCfgPath;

try {
    // Connexion DB KYC
    $pdoKyc = new PDO($dbCfg['dsn'], $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Connexion DB App (pour mise à jour user)
    global $pdo;
    if (!isset($pdo) && file_exists(__DIR__ . '/../../src/db.php')) {
        $pdo = require __DIR__ . '/../../src/db.php';
    }

    // 5. Vérifier DB Locale d'abord (Si pas forcé)
    if (!$force && $applicantId) {
        $stmt = $pdoKyc->prepare("SELECT * FROM kyc_applicants WHERE applicant_id = ?");
        $stmt->execute([$applicantId]);
        $row = $stmt->fetch();
        
        if ($row && (time() - strtotime($row['updated_at']) < 600)) {
            echo json_encode([
                'ok' => true,
                'applicantId' => $row['applicant_id'],
                'externalUserId' => $row['external_user_id'],
                'kyc' => [
                    'reviewStatus' => $row['review_status'],
                    'reviewAnswer' => $row['review_answer'],
                    'reviewedAt' => $row['reviewed_at'],
                    'labels' => $row['reject_labels'] ? json_decode($row['reject_labels'], true) : []
                ],
                'source' => 'db_cache'
            ]);
            exit;
        }
    }

    // 6. Chargement Config Sumsub
    $secCfg = __DIR__ . '/../config/secrets.php';
    if (!file_exists($secCfg)) {
        throw new Exception("Sumsub config missing (secrets.php not found)");
    }
    $cfg = require $secCfg;

    $appToken = $cfg['SUMSUB_APP_TOKEN'] ?? '';
    $secretKey = $cfg['SUMSUB_APP_SECRET'] ?? '';
    $baseUrl = 'https://api.sumsub.com';

    if (!$appToken || !$secretKey) {
        throw new Exception("Sumsub credentials missing in secrets.php");
    }

    // Fonctions API
    function createSignature($ts, $method, $uri, $body, $secret) {
        return hash_hmac('sha256', $ts . $method . $uri . $body, $secret);
    }

    function sendRequest($method, $uri, $body = null) {
        global $appToken, $secretKey, $baseUrl;
        $ts = time();
        $sig = createSignature($ts, $method, $uri, $body, $secretKey);
        
        $ch = curl_init($baseUrl . $uri);
        $headers = [
            'X-App-Token: ' . $appToken,
            'X-App-Access-Ts: ' . $ts,
            'X-App-Access-Sig: ' . $sig
        ];
        if ($body) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ['code' => $code, 'data' => json_decode($resp, true)];
    }

    // 7. RÉSOLUTION APPLICANT ID (Étape "Œuf et Poule")
    // Si on n'a pas l'ID, on le cherche via externalUserId
    if (!$applicantId && $externalUserId) {
        $uri = "/resources/applicants/-;externalUserId=" . urlencode($externalUserId) . "/one";
        $res = sendRequest('GET', $uri);
        if ($res['code'] === 200 && isset($res['data']['id'])) {
            $applicantId = $res['data']['id'];
        } else {
            // Pas trouvé -> L'utilisateur n'a pas encore de dossier
            echo json_encode(['ok' => false, 'error' => 'No applicant found']);
            exit;
        }
    }

    // --- 8. DOUBLE APPEL API (Comme le script qui marche) ---
    
    // A. Récupérer les infos du candidat (Pour externalUserId et raw data)
    $appRes = sendRequest('GET', "/resources/applicants/" . $applicantId);
    if ($appRes['code'] !== 200) {
        throw new Exception("Sumsub Applicant API Error: " . $appRes['code']);
    }
    $applicantData = $appRes['data'];

    // B. Récupérer le STATUT (C'est là que se trouve le vrai reviewAnswer)
    $statRes = sendRequest('GET', "/resources/applicants/" . $applicantId . "/status");
    if ($statRes['code'] !== 200) {
        throw new Exception("Sumsub Status API Error: " . $statRes['code']);
    }
    $statusData = $statRes['data'];

    // --- 9. Extraction des Données (Logique du script de sauvegarde) ---
    
    $reviewStatus = $statusData['reviewStatus'] ?? 'init';
    
    // Logique exacte pour trouver GREEN/RED
    $reviewResultObj = $statusData['reviewResult'] ?? [];
    $reviewAnswer = $reviewResultObj['reviewAnswer'] 
                 ?? $statusData['reviewAnswer'] 
                 ?? null;

    $moderationComment = $statusData['moderationComment'] ?? null;
    $rejectLabels = $reviewResultObj['rejectLabels'] ?? $statusData['rejectLabels'] ?? [];
    
    // Dates
    $createdAt = isset($applicantData['createdAt']) ? date('Y-m-d H:i:s', strtotime($applicantData['createdAt'])) : date('Y-m-d H:i:s');
    $reviewedAt = isset($statusData['reviewedAt']) ? date('Y-m-d H:i:s', strtotime($statusData['reviewedAt'])) : null;

    // JSON Bruts pour l'historique
    $rawApplicantJson = json_encode($applicantData);
    $rawStatusJson = json_encode($statusData);

    $realExtId = $applicantData['externalUserId'] ?? $externalUserId ?? 'sess_'.$userId;

    // --- 10. Update DB ---
    
    $upsert = $pdoKyc->prepare("
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

    $upsert->execute([
        ':aid'  => $applicantId,
        ':ext'  => $realExtId,
        ':rs'   => $reviewStatus,
        ':ra'   => $reviewAnswer,
        ':lbl'  => json_encode($rejectLabels),
        ':com'  => $moderationComment,
        ':cat'  => $createdAt,
        ':rat'  => $reviewedAt,
        ':raws' => $rawStatusJson,
        ':rawa' => $rawApplicantJson
    ]);

    // 11. Update Table User
    if ($reviewStatus === 'completed' && $reviewAnswer === 'GREEN') {
        if (isset($pdo)) {
            $upUser = $pdo->prepare("UPDATE user SET kyc_status = 'COMPLETED' WHERE id = ?");
            $upUser->execute([$userId]);
            $_SESSION['user_info']['kyc_status'] = 'COMPLETED';
        }
    }

    // 12. Réponse JSON finale
    echo json_encode([
        'ok' => true,
        'applicantId' => $applicantId,
        'externalUserId' => $realExtId,
        'kyc' => [
            'reviewStatus' => $reviewStatus,
            'reviewAnswer' => $reviewAnswer,
            'reviewedAt'   => $reviewedAt,
            'labels'       => $rejectLabels
        ],
        'source' => 'api_sumsub_live_synced'
    ]);

} catch (Exception $e) {
    json_terminate(500, $e->getMessage());
} catch (Throwable $t) {
    json_terminate(500, "Fatal Error: " . $t->getMessage());
}
?>