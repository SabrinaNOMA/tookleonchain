<?php
// onramp_tookle_coinbase.php
// Reçoit les données via GET depuis wallet.php et lance la session Coinbase automatiquement.

// 0) Session + CSRF (OBLIGATOIRE si ton API session.php vérifie session/CSRF)
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 7; // 1 semaine
    session_set_cookie_params($lifetime, '/', '', isset($_SERVER['HTTPS']), true);
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 1) Paramètres GET
$walletAddress = isset($_GET['address']) ? trim((string)$_GET['address']) : '';
$amount        = isset($_GET['amount']) ? (float)$_GET['amount'] : 0.0;
$currency      = isset($_GET['currency']) ? strtoupper(trim((string)$_GET['currency'])) : 'EUR';

// Defaults
$asset      = 'USDC';
$blockchain = 'base';

// Auto-start
$autoStart = ($walletAddress !== '' && $amount > 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redirection Coinbase...</title>

  <!-- CSRF token pour le JS -->
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>">

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
    .loader-container { text-align: center; }
    .spinner-border { width: 3rem; height: 3rem; color: #34D399; }
    .loading-text { margin-top: 1rem; font-weight: 600; color: #1f2937; }
    .error-box { color: #dc2626; padding: 20px; text-align: center; display: none; max-width: 520px; }
  </style>
</head>
<body>

  <div id="loader" class="loader-container">
    <div class="spinner-border" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <div class="loading-text">Connecting to Coinbase...</div>
    <div class="small text-muted mt-2">
      Creating secure session for <?= htmlspecialchars(number_format($amount, 2, '.', '') . ' ' . $currency, ENT_QUOTES) ?>
    </div>
  </div>

  <div id="error-display" class="error-box">
      <h4>Unable to start session</h4>
      <p id="error-message"></p>
      <button onclick="window.location.reload()" class="btn btn-outline-secondary btn-sm mt-3">Retry</button>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', async () => {
    const autoStart = <?= $autoStart ? 'true' : 'false'; ?>;

    // Données PHP injectées en JSON (plus safe que concat de strings)
    const payload = <?= json_encode([
        'wallet_address' => $walletAddress,
        'amount'         => (string)$amount,
        'fiat_currency'  => $currency,
        'assets'         => [$asset],
        'blockchain'     => $blockchain,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

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

    // CSRF
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '').trim() : '';
    if (!csrfToken) {
      showError("Security error: CSRF token missing. (meta[name='csrf-token'])");
      return;
    }

    try {
      // URL API session token
      const apiUrl = new URL('../api/onramp/session.php', window.location.href).toString();

      const res = await fetch(apiUrl, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(payload)
      });

      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error("Non-JSON response:", text);
        throw new Error("Server Error: Invalid JSON response");
      }

      if (!res.ok) {
        throw new Error(data.error || ("API Error " + res.status));
      }

      if (data.success && data.session_url) {
        // redirection hors iframe
        window.top.location.href = data.session_url;
      } else {
        throw new Error(data.error || "No session URL returned.");
      }

    } catch (err) {
      console.error(err);
      showError(err.message || "Unknown error");
    }
  });
  </script>
</body>
</html>
