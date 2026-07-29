<?php
// /backend/get_category_api.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../src/session.php'; // Manages session start
$pdo = require __DIR__ . '/../src/db.php';

function gen_uuid_v4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * CORRECTION : Le type de $founder_id est changé en 'string' pour accepter l'UUID
 */
function fetch_category_from_api(string $wp_name, string $founder_id, string $projet_id): string {

    // --- CORRECTION : La valeur de test "Coinzix" est supprimée d'ici ---
    // $wp_name = "Coinzix"; 

    // 1. Construire l'URL avec les paramètres
    $service_url = "http://51.75.205.65:8002/token_category/?" . http_build_query([
        'wp_name' => $wp_name,
        'id_user' => $founder_id, // L'API reçoit maintenant l'UUID
        'project_id' => $projet_id
    ]);

    // 2. Initialiser cURL
    $ch = curl_init($service_url);
    
    // 3. Définir les options cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');

    $response = curl_exec($ch);

    // 5. Gérer les erreurs cURL
    if (curl_errno($ch)) {
        $error_message = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erreur cURL: " . $error_message);
    }
    curl_close($ch);

    // 6. Décoder la réponse JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['CATEGORIE'])) {
        throw new Exception("Réponse JSON invalide ou 'CATEGORIE' manquante. Réponse: $response");
    }

    $category = $data['CATEGORIE'];
    $cat = $category ?? '';

    if ($cat === 'Autre') {   
        return 'Startup Utility Tokens';
    }
	
	if ($cat === 'Marketplaces (Other)') { 
	   return 'Marketplaces';
    }

    return $cat;
}

// --- Logique principale de l'endpoint ---
try {
	
    // CORRECTION : Définir les variables de test ici
    // Vous avez dit de les garder, donc nous les définissons
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Valeurs de test (en dur comme demandé)
    $wp_name = "Coinzix"; 
    $founder_id = $data['founder_id']?? null;
    $projet_id = $data['project_id'] ?? null;
	$project_name= $data['wp_name'] ?? null;
	
    // La vérification "Données manquantes" (ligne 93) n'est plus nécessaire car nous avons des valeurs par défaut

    // --- Logique d'insertion de "Brouillon" ---
    
    
	
	if ($projet_id) {
   
      // Appeler la fonction et récupérer la catégorie
    // CORRECTION : Le cast (int) est supprimé
    $category = fetch_category_from_api($wp_name, $founder_id, $projet_id);
    
    $sql = "UPDATE projet SET selected_category = ? WHERE id = ? AND founder_id = ?";
    $stmt = $pdo->prepare($sql);
    
    // --- CORRECTION FATALE : 'execute' attend un array ---
    $stmt->execute([$category, $project_id, $founder_id]);
    
    } else {
		 $projet_id = gen_uuid_v4();
		 $_SESSION['active_project_id'] = $projet_id;
		 $_SESSION['projet_id'] = $projet_id;
		 
        $sql = "INSERT INTO projet (id, founder_id, project_name, created_at, project_described) 
                VALUES (?, ?, ?, NOW(), 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$projet_id, $founder_id, $project_name]); 
		$category = fetch_category_from_api($wp_name, $founder_id, $projet_id);
    }


    
	
    echo json_encode(['success' => true, 'category' => $category, 'project_id' => $project_id]);

} catch (Throwable $e) { // CORRECTION : Attrape 'Throwable' (Error + Exception)
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
?>