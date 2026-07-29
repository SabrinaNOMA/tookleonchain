<?php
// lib_category.php
require_once __DIR__ . '/../src/session.php'; // Manages session start


if (!function_exists('tkl_log')) {
    /**
     * Log JSON lisible + contexte requête.
     * @param string $label
     * @param mixed  $data
     * @param string|null $file  Chemin d’un fichier dédié; sinon error_log()
     */
    function tkl_log($label, $data = [], $trace = '')
{
    // dossier logs relatif au fichier où tkl_log est défini
    $logFile = __DIR__ . '/logs/setup.log';   // <--- ICI

    // si le dossier n'existe pas ? le créer
    $dir = dirname($logFile);
    if(!is_dir($dir)) mkdir($dir, 0777, true);

    $content = date('Y-m-d H:i:s') . " [$label] " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    if ($trace) $content .= $trace . "\n";

    file_put_contents($logFile, $content, FILE_APPEND);
}
}





/**
 * Appelle l’API externe en POST et renvoie la catégorie détectée.
 *
 * @param string $wp_name Nom du projet
 * @param int $founder_id ID de l'utilisateur
 * @param string $projet_id ID du projet
 * @return string La catégorie détectée
 * @throws Exception en cas d’erreur cURL ou de réponse API invalide
 */
function fetch_category_from_api(string $wp_name, int $founder_id, string $projet_id): string {
    
    // 1. Définir l'URL de base (sans les paramètres dans l'URL)
    $service_url = "http://51.75.205.65:8002/token_category/";

    // 2. Préparer les données pour le corps de la requête POST
    $postData = [
        'wp_name'    => $wp_name,
        'id_user'    => $founder_id,
        'project_id' => $projet_id
    ];
    
    // 3. Convertir le tableau en chaîne de requête (format x-www-form-urlencoded)
    $postFields = http_build_query($postData);

    // 4. Initialiser cURL et définir les options pour le POST
    $ch = curl_init($service_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Pour récupérer la réponse
    curl_setopt($ch, CURLOPT_POST, true);           // Définir la méthode sur POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields); // Attacher les données POST

    $response = curl_exec($ch);

    // 5. AMÉLIORATION : Gestion des erreurs cURL
    //    Nous lançons une Exception au lieu d'utiliser 'exit'
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        $error_no = curl_errno($ch);
        curl_close($ch);
        // Lancer une exception permet à setup_backend.php de la capturer
        throw new Exception("Erreur cURL ({$error_no}): {$error_msg}");
    }
    curl_close($ch);

    // 6. Décoder la réponse JSON
    $data = json_decode($response, true);

    // 7. AMÉLIORATION : Gestion des erreurs JSON
    //    Nous lançons également une exception ici
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['CATEGORIE'])) {
        throw new Exception("Réponse JSON invalide ou 'CATEGORIE' manquante. Réponse: $response");
    }

    // 8. Logique de traitement (inchangée)
    $cat = $data['CATEGORIE'] ?? '';

    if ($cat === 'Autre') {
        return 'Startup Utility Tokens';
    }

    return $cat;
}


/**
 * Met à jour la catégorie d'un projet dans la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données
 * @param string $category La nouvelle catégorie
 * @param int $founder_id L'ID du fondateur (pour la clause WHERE)
 * @param int $projet_id L'ID du projet (pour la clause WHERE)
 * @return bool True si la mise à jour a réussi
 */
function update_project_category(PDO $pdo, string $category, int $founder_id, int $projet_id): bool {
	
	/*
	 tkl_log('update_project_category:enter', [
        'projectId'    => $projet_id,
        'newCategory'  => $category,
        'user_id'      => $founder_id,
        'trace'        => array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0, 5),
    ]);
	*/
    
    // 5. Correction : Le $pdo est passé en paramètre (correction du scope)
    $sql = "UPDATE projet SET recommended_category = ? WHERE founder_id = ? AND id = ?";
    $stmt = $pdo->prepare($sql);
    
    // 6. Correction : Le premier paramètre (la catégorie) est ajouté
    return $stmt->execute([
        $category, // <-- Le paramètre manquant
        $founder_id,
        $projet_id
    ]);
}

?>