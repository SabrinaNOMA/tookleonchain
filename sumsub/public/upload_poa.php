<?php
declare(strict_types=1);
if (isset($_GET['debug'])) { ini_set('display_errors','1'); error_reporting(E_ALL); }
require __DIR__ . '/../vendor/autoload.php';

$db = require __DIR__ . '/../config/db.php';
$pdo = new PDO($db['dsn'],$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $applicantId = $_POST['applicantId'] ?? '';
  if (!$applicantId || !isset($_FILES['file'])) { http_response_code(400); echo 'Missing applicantId or file'; exit; }
  $dir = __DIR__ . '/../uploads/poa';
  if (!is_dir($dir)) mkdir($dir, 0777, true);
  $safe = basename($_FILES['file']['name']);
  $path = $dir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_', $safe);
  if (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) { http_response_code(500); echo 'Save failed'; exit; }
  $st = $pdo->prepare("INSERT INTO kyc_uploads(applicant_id, flow, id_doc_type, page, country, filename, filepath, status, attempts, created_at) VALUES (:a,:f,:t,:p,:c,:n,:p2,'PENDING',0,NOW())");
  $st->execute([':a'=>$applicantId, ':f'=>'POA', ':t'=>($_POST['id_doc_type'] ?? null), ':p'=>($_POST['page'] ?? null), ':c'=>($_POST['country'] ?? null), ':n'=>$safe, ':p2'=>$path]);
  echo 'OK'; exit;
}
?>
<!doctype html><html><body>
  <h3>Manual upload (POA) — placeholder</h3>
  <form method="post" enctype="multipart/form-data">
    ApplicantId: <input name="applicantId" required><br>
    DocType: <input name="id_doc_type"><br>
    Page: <select name="page"><option value="">—</option><option>FRONT</option><option>BACK</option></select><br>
    Country (ISO-3): <input name="country" value="FRA"><br>
    File: <input type="file" name="file" required><br>
    <button>Upload</button>
  </form>
  <p>Note: This stores locally + logs to DB. For production, prefer WebSDK.</p>
</body></html>