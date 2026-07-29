// Config de base
const clientId = window.COINBASE_CLIENT_ID;
// chainId: 1=Ethereum mainnet, 8453=Base mainnet, 84532=Base Sepolia (test)
const sdk = new window.CoinbaseEmbeddedWalletSdk.EmbeddedWalletSdk({
  clientId,
  chainId: 84532 // conseil: commencer sur Base Sepolia (testnet)
});

const btn = document.getElementById('wallet-btn');
const out = document.getElementById('wallet-address');

btn.onclick = async () => {
  try {
    // Ouvre le flux d’onboarding (login email/OTP ou social)
    const res = await sdk.connect();
    // res.address = adresse EVM de l’utilisateur
    out.textContent = "Adresse du wallet : " + res.address;

    // Envoie l'adresse à ton backend pour l'associer au user
    await fetch('/save_wallet_address.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ walletAddress: res.address })
    });

    alert('Wallet associé à votre compte Tookle ✅');
  } catch (e) {
    console.error(e);
    alert('Erreur: ' + (e?.message || e));
  }
};