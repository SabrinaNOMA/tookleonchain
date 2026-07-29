<?php
/**
 * backend/trigger_blockchain_check.php
 * VERSION DEBUG - Affiche les erreurs fatales
 */

// 1. ACTIVATION AFFICHAGE ERREURS (Pour comprendre le 500)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Sécurité de base
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Access Denied']));
}

header('Content-Type: application/json');

// 3. Configuration
$watcher_silent_mode = true; 
$cronPath = __DIR__ . '/../cron/purchase_blockchain_watcher.php';

// 4. Test d'existence
if (!file_exists($cronPath)) {
    // Si le fichier n'est pas trouvé, on donne le chemin cherché pour aider
    echo json_encode(['success' => false, 'error' => 'File not found at: ' . realpath(__DIR__ . '/../') . '/cron/purchase_blockchain_watcher.php']);
    exit;
}

// 5. Tentative d'inclusion protégée
try {
    // On inclut le watcher. S'il y a une erreur de syntaxe dedans, le script s'arrêtera ici 
    // et PHP affichera l'erreur grâce au ini_set plus haut.
    require_once $cronPath;
    
    echo json_encode(['success' => true, 'message' => 'Check executed.']);

} catch (Throwable $e) {
    // Capture les erreurs fatales PHP 7+ et les Exceptions
    echo json_encode(['success' => false, 'error' => 'PHP Error: ' . $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}
?>