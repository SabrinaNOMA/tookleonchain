<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/src/db.php';
header('Content-Type: application/json');
$id = $_GET['id'] ?? null;
if (!$id) { echo json_encode(['error' => 'No ID']); exit; }
$stmt = $pdo->prepare('SELECT * FROM token_sale_pages WHERE id = :id');
$stmt->execute(['id' => $id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) { echo json_encode(['error' => 'Sale not found']); exit; }
$resp = ['sale' => $sale, 'scenario' => null, 'vesting' => [], 'round' => []];
$scen = $sale['scenario_version_id'];
if ($scen) {
    $stmt2 = $pdo->prepare('SELECT id, is_active, data FROM scenario_version WHERE id = :id');
    $stmt2->execute(['id' => $scen]);
    $s = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($s) {
        $s['data'] = json_decode($s['data'], true);
        $resp['scenario'] = $s;
    }
}
$stmt3 = $pdo->prepare('SELECT * FROM vesting_token WHERE projet_id = :pid');
$stmt3->execute(['pid' => $sale['project_id']]);
$resp['vesting'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
$stmt4 = $pdo->prepare('SELECT * FROM round_token WHERE projet_id = :pid');
$stmt4->execute(['pid' => $sale['project_id']]);
$resp['round'] = $stmt4->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($resp, JSON_PRETTY_PRINT);
