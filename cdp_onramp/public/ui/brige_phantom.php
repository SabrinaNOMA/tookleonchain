<?php
session_start();

// Optionnel : si tu as l'utilisateur connecté Tookle
$userEmail = $_SESSION['user_email'] ?? '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Tookle • Transférer vers Phantom (Solana)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body{font-family:system-ui,Arial,sans-serif;max-width:880px;margin:0 auto;padding:24px;line-height:1.45}
    .card{border:1px solid #e5e5e5;border-radius:14px;padding:18px;margin:14px 0}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    input{padding:10px 12px;border:1px solid #ddd;border-radius:10px;min-width:320px;font-size:14px}
    button{padding:10px 14px;border-radius:10px;border:0;background:#111;color:#fff;font-size:14px;cursor:pointer}
    button.secondary{background:#f2f2f2;color:#111;border:1px solid #e5e5e5}
    code{background:#f6f6f6;padding:2px 6px;border-radius:6px}
    .muted{color:#666;font-size:13px}
    .ok{color:#0a7a2f}
    .warn{color:#8a5b00}
  </style>
</head>
<body>
  <h1>Transférer tes USDC (Base) vers Phantom (Solana)</h1>
  <p class="muted">
    Ici, Tookle ne fait pas le bridge. Tu le fais directement dans <strong>Phantom</strong> (cross-chain swap/bridge).
  </p>

  <div class="card">
    <h3>1) Renseigne ton adresse Phantom (Solana)</h3>
    <div class="row">
      <input id="sol" placeholder="Adresse Solana (Phantom) — ex: 4h3... (base58)" />
      <button id="copySol" class="secondary">Copier</button>
    </div>
    <p class="muted">Astuce : dans Phantom → <em>Receive</em> → copie ton adresse Solana.</p>
    <div id="solStatus" class="muted"></div>
  </div>

  <div class="card">
    <h3>2) Dans Phantom : fais le bridge one-shot</h3>
    <ol>
      <li>Ouvre <strong>Phantom</strong> (mobile ou extension).</li>
      <li>Va dans <strong>Swap</strong> / <strong>Bridge</strong> (Cross-Chain).</li>
      <li>Choisis :
        <ul>
          <li><strong>From chain</strong> : Base</li>
          <li><strong>To chain</strong> : Solana</li>
          <li><strong>Asset</strong> : USDC</li>
        </ul>
      </li>
      <li>Quand Phantom demande le <strong>recipient</strong>, colle l’adresse ci-dessus.</li>
      <li>Valide le montant et confirme.</li>
    </ol>
    <p class="muted">
      ⚠️ Si Phantom ne propose pas Base→Solana pour ton compte, vérifie que tu utilises la dernière version,
      et que tu as bien des USDC sur Base.
    </p>
  </div>

  <div class="card">
    <h3>3) Après le transfert : vérification</h3>
    <p class="muted">
      Quand c’est terminé, tu dois voir des <strong>USDC sur Solana</strong> dans Phantom.
      Si tu veux, tu peux coller ici le TX hash Solana (optionnel) pour garder une trace dans Tookle.
    </p>
    <div class="row">
      <input id="tx" placeholder="TX hash Solana (optionnel)" />
      <button id="save" class="secondary">Enregistrer dans Tookle (optionnel)</button>
    </div>
    <div id="saveStatus" class="muted"></div>
  </div>

<script>
  const sol = document.getElementById('sol');
  const solStatus = document.getElementById('solStatus');
  const copySol = document.getElementById('copySol');
  const tx = document.getElementById('tx');
  const save = document.getElementById('save');
  const saveStatus = document.getElementById('saveStatus');

  // Validation Solana (base58 “raisonnable”)
  function isSolanaAddress(s){
    s = (s||'').trim();
    return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(s);
  }

  sol.addEventListener('input', () => {
    if (!sol.value.trim()) { solStatus.textContent=''; return; }
    if (isSolanaAddress(sol.value)) {
      solStatus.innerHTML = '<span class="ok">Adresse Solana valide ✅</span>';
    } else {
      solStatus.innerHTML = '<span class="warn">Adresse Solana invalide (format base58 attendu)</span>';
    }
  });

  copySol.addEventListener('click', async () => {
    const v = sol.value.trim();
    if (!v) return alert('Renseigne ton adresse Phantom d’abord.');
    try {
      await navigator.clipboard.writeText(v);
      solStatus.innerHTML = '<span class="ok">Adresse copiée ✅</span>';
    } catch(e) {
      alert("Impossible de copier (permissions navigateur). Copie manuellement.");
    }
  });

  // Optionnel: sauvegarder l’info côté Tookle (si tu veux tracer)
  save.addEventListener('click', async () => {
    const solAddr = sol.value.trim();
    const txHash = tx.value.trim();

    if (!isSolanaAddress(solAddr)) return alert("Adresse Solana invalide.");
    saveStatus.textContent = "Enregistrement…";

    try {
      const r = await fetch('/save_phantom_bridge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ solAddress: solAddr, solTx: txHash })
      });
      const data = await r.json().catch(()=>({}));
      if (!r.ok) throw new Error(data.error || ('HTTP '+r.status));
      saveStatus.innerHTML = '<span class="ok">Enregistré ✅</span>';
    } catch(e) {
      console.error(e);
      saveStatus.innerHTML = '<span class="warn">Erreur: '+(e.message||'unknown')+'</span>';
    }
  });
</script>
</body>
</html>