<?php
session_start();
// Simule un user connecté (à adapter à ton auth)
//if (!isset($_SESSION['user_id'])) ;

// Mets ton Client ID dans une variable PHP (ou .env)
$COINBASE_CLIENT_ID = 'VDolplRsKiGex7zxMIfmUTeEQBsz55qX';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Tookle – Mon Wallet</title>
  <!-- SDK UMD côté navigateur -->
  <script src="https://unpkg.com/@coinbase/embedded-wallet-sdk/dist/index.umd.js"></script>
</head>
<body>
  <h1>Créer/Connecter mon Embedded Wallet</h1>

  <button id="wallet-btn">Créer / Se connecter</button>
  <p id="wallet-address" style="margin-top:10px;"></p>

  <script>
    // Injecte le Client ID depuis PHP
    window.COINBASE_CLIENT_ID = "<?= htmlspecialchars($COINBASE_CLIENT_ID, ENT_QUOTES) ?>";
  </script>
  <script src="/wallet-embed.js"></script>
</body>
</html>