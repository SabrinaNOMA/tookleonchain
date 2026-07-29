<?php
session_start();
$CDP_PROJECT_ID = '6f36302b-a2c4-44a6-9128-88886b78a809';
$CDP_ENV = 'prod';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Test Embedded Wallet</title>
  <style>
    body{font-family:system-ui,Arial,sans-serif;padding:24px}
    button{font-size:16px;padding:10px 16px;cursor:pointer}
    #wallet-address{margin-top:12px;font-family:ui-monospace,Menlo,monospace}
  </style>
</head>
<body>
  <h1>Test Embedded Wallet (CDP)</h1>
  <button id="wallet-btn">Créer / Se connecter</button>
  <p id="wallet-address"></p>

  <script>
    window.TOOKLE = {
      CDP_PROJECT_ID: "<?= htmlspecialchars($CDP_PROJECT_ID, ENT_QUOTES) ?>",
      CDP_ENV: "<?= htmlspecialchars($CDP_ENV, ENT_QUOTES) ?>",
      CSRF_TOKEN: "<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>"
    };
  </script>

  <!-- Uniquement ton bundle -->
  <script type="module" src="/main.js"></script>
</body>
</html>