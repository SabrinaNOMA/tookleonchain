<?php
// onramp_tookle_coinbase.php

// Si tu veux utiliser une session Tookle :
// session_start();

// Exemple : préremplir l'adresse depuis une variable PHP ou un GET
$prefillWallet = '';
if (!empty($_GET['wallet'])) {
    $prefillWallet = $_GET['wallet'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Onramp Tookle CDP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f9fafb; }
    .card { max-width: 480px; margin: 40px auto; border-radius: 1rem; }
    /* Petit effet pour montrer que le bouton est actif */
    .btn-loading { cursor: not-allowed; opacity: 0.8; }
  </style>
</head>
<body>
  <div class="container mt-5">
    <div class="card shadow-sm p-4">
      <h4 class="mb-3 text-center">💶 Onramp Tookle</h4>

      <div class="mb-3">
        <label class="form-label">Recipient's wallet address</label>
        <input
          type="text"
          class="form-control"
          id="wallet_address"
          placeholder="0x1234abcd..."
          value="<?php echo htmlspecialchars($prefillWallet, ENT_QUOTES, 'UTF-8'); ?>"
          required
        >
      </div>

      <div class="mb-3">
        <label class="form-label">Amount to pay</label>
        <div class="input-group">
          <input
            type="number"
            class="form-control"
            id="amount"
            value="10"
            min="1"
            step="0.01"
          >
          <select class="form-select" id="fiat_currency" style="max-width: 100px;">
            <option value="EUR" selected>EUR</option>
            <option value="USD">USD</option>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Stablecoin to receive</label>
        <select class="form-select" id="asset">
          <option value="USDC" selected>USDC</option>
          <option value="USDT">USDT</option>
        </select>
      </div>
      
      <div class="mb-3">
        <label class="form-label">Blockchain network</label>
        <select class="form-select" id="blockchain">
          <option value="base">Base</option>
          <option value="ethereum">Ethereum</option>
        </select>
      </div>

      <button id="startBtn" class="btn btn-primary w-100 py-2 fw-bold">
        🔄 Stablecoin Conversion
      </button>

      <div id="result" class="alert mt-4 d-none"></div>
    </div>
  </div>

  <script>
  document.getElementById('startBtn').addEventListener('click', async () => {
    // Récupération des éléments
    const btn        = document.getElementById('startBtn');
    const wallet     = document.getElementById('wallet_address').value.trim();
    const amount     = document.getElementById('amount').value.trim();
    const blockchain = document.getElementById('blockchain').value;
    const fiat       = document.getElementById('fiat_currency').value;
    const asset      = document.getElementById('asset').value;
    const resultDiv  = document.getElementById('result');

    // Reset de l'affichage
    resultDiv.className = 'alert d-none';
    
    // Validation basique
    if (!wallet || !amount) {
      alert("Merci de saisir une adresse wallet et un montant.");
      return;
    }

    // 1. UI : Passer le bouton en état de chargement
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Redirecting to Coinbase...';

    const apiUrl = '../api/onramp/session.php';

    try {
      // 2. Appel API
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          wallet_address: wallet,
          amount: amount,
          fiat_currency: fiat,
          assets: [asset],
          blockchain: blockchain
        })
      });

      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        data = { error: 'Réponse non JSON : ' + text };
      }

      if (!res.ok) {
        throw new Error(data.error || text);
      }

      // 3. SUCCÈS : Redirection automatique
      if (data.success && data.session_url) {
        // Option A : Ouvrir dans la même fenêtre (recommandé pour mobile/flux fluide)
        //window.location.href = data.session_url;
		window.top.location.href = data.session_url;
        
        // Option B : Ouvrir dans un nouvel onglet (décommentez la ligne ci-dessous et commentez l'Option A si vous préférez)
        // window.open(data.session_url, '_blank'); 
        // Note : window.open peut parfois être bloqué par les navigateurs s'il y a un délai trop long.
        return;
      }

      // Cas où success est false mais pas d'erreur HTTP
      throw new Error(data.error || 'Erreur inconnue.');

    } catch (err) {
      // GESTION ERREUR : Réactiver le bouton
      btn.disabled = false;
      btn.innerHTML = originalText;
      
      resultDiv.className = 'alert alert-danger';
      resultDiv.textContent = 'Erreur : ' + err.message;
      resultDiv.classList.remove('d-none');
    }
  });
  </script>
</body>
</html>