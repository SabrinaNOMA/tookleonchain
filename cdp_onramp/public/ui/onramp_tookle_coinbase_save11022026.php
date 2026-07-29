<?php
// onramp_tookle_coinbase.php
// Ce script reçoit les données via GET depuis wallet.php et lance la session Coinbase automatiquement.

// 1. Récupération sécurisée des paramètres envoyés par wallet.php
// Note: wallet.php envoie 'address', 'amount', 'currency'
$walletAddress = isset($_GET['address']) ? trim($_GET['address']) : '';
$amount        = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$currency      = isset($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : 'EUR';

// Valeurs par défaut pour l'API
$asset         = 'USDC'; 
$blockchain    = 'base'; // ou 'ethereum' selon votre préférence par défaut

// Mode Auto-Start : Si on a les infos, on lance le JS automatiquement
$autoStart = ($walletAddress !== '' && $amount > 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redirection Coinbase...</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <style>
    body { 
        font-family: 'Montserrat', sans-serif;
        background-color: #ffffff; 
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
        margin: 0;
        overflow: hidden;
    }
    .loader-container {
        text-align: center;
    }
    .spinner-border {
        width: 3rem;
        height: 3rem;
        color: #34D399; /* Tookle Green */
    }
    .loading-text {
        margin-top: 1rem;
        font-weight: 600;
        color: #1f2937;
    }
    .error-box {
        color: #dc2626;
        padding: 20px;
        text-align: center;
        display: none;
    }
  </style>
</head>
<body>

  <div id="loader" class="loader-container">
    <div class="spinner-border" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <div class="loading-text">Connecting to Coinbase...</div>
    <div class="small text-muted mt-2">Creating secure session for <?= htmlspecialchars($amount . ' ' . $currency) ?></div>
  </div>

  <div id="error-display" class="error-box">
      <h4>Unable to start session</h4>
      <p id="error-message"></p>
      <button onclick="window.location.reload()" class="btn btn-outline-secondary btn-sm mt-3">Retry</button>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', async () => {
      
      const autoStart = <?php echo $autoStart ? 'true' : 'false'; ?>;
      
      // Données PHP injectées dans le JS
      const payload = {
          wallet_address: "<?php echo $walletAddress; ?>", // Attention au nom du champ attendu par votre API session.php
          amount: "<?php echo $amount; ?>",
          fiat_currency: "<?php echo $currency; ?>",
          assets: ["<?php echo $asset; ?>"],
          blockchain: "<?php echo $blockchain; ?>"
      };

      const loader = document.getElementById('loader');
      const errorDisplay = document.getElementById('error-display');
      const errorMsg = document.getElementById('error-message');

      function showError(msg) {
          loader.style.display = 'none';
          errorDisplay.style.display = 'block';
          errorMsg.textContent = msg;
      }

      if (!autoStart) {
          showError("Missing parameters (Address or Amount). Please go back and try again.");
          return;
      }

      try {
          // --- APPEL API ---
          // Chemin vers votre API PHP qui génère le lien Coinbase
          // Assurez-vous que ce chemin est correct depuis l'emplacement de ce fichier
          const apiUrl = '../api/onramp/session.php'; 

          const res = await fetch(apiUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
          });

          // Gestion de la réponse
          const text = await res.text();
          let data;
          try {
              data = JSON.parse(text);
          } catch (e) {
              console.error("Non-JSON response:", text);
              throw new Error("Server Error: Invalid JSON response");
          }

          if (!res.ok) {
              throw new Error(data.error || "API Error " + res.status);
          }

          if (data.success && data.session_url) {
              // --- SUCCÈS : REDIRECTION ---
              // CORRECTION : On redirige la fenêtre PRINCIPALE (window.top) vers Coinbase.
              // Cela sort de l'iframe et contourne le blocage de sécurité.
              window.top.location.href = data.session_url;
          } else {
              throw new Error(data.error || "No session URL returned.");
          }

      } catch (err) {
          console.error(err);
          showError(err.message);
      }
  });
  </script>
</body>
</html>