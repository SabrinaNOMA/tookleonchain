<?php
/**
 * Backend for the Projects/Discover Page.
 *
 * Fetches projects and separates them into live/scheduled and closed categories.
 * Updated to calculate fundraising progress on a per-sale basis.
 */

ob_start();
session_start();

require_once '../config.php';
require_once '../src/db.php';

function process_media_fields(&$project_row) {
    $project_row['media_url'] = null;
    $project_row['media_type'] = null;
    if (!empty($project_row['general_images_json'])) {
        $images = json_decode($project_row['general_images_json'], true);
        if (is_array($images) && !empty($images[0])) {
            $project_row['media_url'] = $images[0];
            $project_row['media_type'] = 'image';
        }
    }
    if (empty($project_row['media_url']) && !empty($project_row['video_file_path'])) {
        $project_row['media_url'] = $project_row['video_file_path'];
        $project_row['media_type'] = 'video';
    }
    if ($project_row['media_url']) {
        $project_row['media_url'] = str_replace('\\', '/', $project_row['media_url']);
    }
    unset($project_row['video_file_path']);
    unset($project_row['general_images_json']);
}

header('Content-Type: application/json');

$response = [
    'userId' => null,
    'userInfo' => null,
    'discoverProjects' => [
        'live' => [],
        'closed' => []
    ]
];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in.', 401);
    }
    $user_id = $_SESSION['user_id'];
    $response['userId'] = $user_id;

    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $response['userInfo'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // The logic to calculate funding is now moved into the main query below
    // to ensure it's calculated on a per-sale basis.

    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.project_name, tsp.country, p.industry_focus, 
            tsp.sale_url, p.token_name, p.selected_category, p.token_logo_path,
            tsp.status,
            tsp.video_file_path, tsp.general_images_json,
            tsp.project_description_story as project_description,
            tsp.sale_name, 
            tsp.soft_cap_usd as min_raise, 
            tsp.hard_cap_usd as max_raise, 
            tsp.sale_launch_at as sale_launch_date, 
            tsp.sale_end_at as sale_end_date,
            (SELECT COALESCE(SUM(pay.amount), 0)
             FROM payments pay
             JOIN investments inv ON pay.investment_id = inv.id
             WHERE inv.project_id = p.id AND inv.sale_name = tsp.sale_name AND pay.status = 'successful') as current_funding
        FROM projet p
        LEFT JOIN token_sale_pages tsp ON p.id = tsp.project_id
        WHERE tsp.status IN ('live', 'scheduled', 'ended_successful', 'ended_failed', 'canceled')
        ORDER BY
            CASE
                WHEN tsp.status = 'live' THEN 0
                WHEN tsp.status = 'scheduled' THEN 1
                ELSE 2
            END,
            tsp.sale_launch_at ASC,
            tsp.sale_end_at DESC
    ");
    $stmt->execute();
    $discover_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $live_projects = [];
    $closed_projects = [];

    foreach ($discover_data as &$row) {
        process_media_fields($row);
        // The 'current_funding' is now directly available from the main query.
        $row['current_funding'] = (float)($row['current_funding'] ?? 0);
        
        $status = strtolower($row['status']);
        if ($status === 'live' || $status === 'scheduled') {
            $live_projects[] = $row;
        } else {
            $closed_projects[] = $row;
        }
    }
    unset($row);

    $response['discoverProjects']['live'] = $live_projects;
    $response['discoverProjects']['closed'] = $closed_projects;

    ob_end_clean();
    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    ob_end_clean();
    $statusCode = $e->getCode() === 401 ? 401 : 500;
    http_response_code($statusCode);
    error_log("projects_backend.php Error: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while fetching projects.']);
}
?>
