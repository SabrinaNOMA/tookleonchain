<?php
/**
 * One-time fix: Link orphaned sales (id=11,12,13) to their active scenarios
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/src/db.php';
header('Content-Type: application/json');

$stmt = $pdo->prepare("
    UPDATE token_sale_pages tsp
    JOIN scenario_version sv ON sv.projet_id = tsp.project_id AND sv.is_active = 1
    SET tsp.scenario_version_id = sv.id
    WHERE tsp.id IN (11, 12, 13) AND tsp.scenario_version_id IS NULL
");
$stmt->execute();
$affected = $stmt->rowCount();

echo json_encode(['fixed' => $affected, 'message' => "$affected orphaned sale(s) linked to their active scenario"]);
