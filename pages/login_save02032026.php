<?php
// (session déjà démarrée par le routeur principal si besoin)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Tookle</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind (facultatif si déjà global) -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Google Identity (GSI) -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>

  <!-- Ton logo (config) -->
  <script src="/config_logo.js"></script>

  <!-- reCAPTCHA -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <style>
    :root {
      --primary-purple: #8e52ff;
      --border-color: #D1D5DB;
      --error-color: #DC2626;
      --success-color: #16A34A;
      --font-family: 'Montserrat', sans-serif;
    }
    body {
      background: linear-gradient(to bottom right, #f3e8ff, #e0e7ff);
      font-family: var(--font-family);
      color: #111827;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 2rem;
    }
    .auth-card {
      background: #fff; padding: 2.5rem; border-radius: .75rem;
      box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1);
      width: 100%; max-width: 28rem; position: relative;
    }
    .input-standard {
      width: 100%; padding: .875rem 1rem; border: 1px solid var(--border-color); border-radius: .5rem; box-sizing: border-box;
    }
    .btn-submit {
      width: 100%; padding: .875rem 1rem; border-radius: .5rem; font-weight: 600; color: #fff;
      background-image: linear-gradient(to right, #8e52ff, #528eff, #33cccc); border: none;
    }
    .message-area { font-size:.875rem; text-align:center; min-height:1.25rem; margin:.75rem 0 0; }
    .message-area.error { color: var(--error-color); }
    .message-area.success { color: var(--success-color); }
    .social-login { display:flex; flex-direction:column; gap:.9em; margin:1.4em 0 0 0; }
    .btn-icon {
      display:flex; align-items:center; justify-content:center; gap:.85em; background:#fff; border:1px solid var(--border-color);
      border-radius:.8em; font-size:1.05em; font-weight:500; color:#222; padding:.9em 1.2em; width:100%; cursor:pointer; box-sizing:border-box;
    }
    .btn-icon svg { width:28px; height:28px; display:block; flex-shrink:0; }

    .hide{display:none!important;}

    /* Modal simple */
    #signup-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.25); z-index: 2000; }
    #signup-modal .modal-card { background:#fff; width:100%; max-width:420px; border-radius: .75rem; padding: 1.25rem; box-shadow: 0 10px 25px rgba(0,0,0,.15); }
    #signup-modal h3 { font-size:1.125rem; font-weight:700; margin-bottom: .75rem; color:#111827; }
    #signup-modal .modal-field { margin-bottom: .75rem; }
    #signup-modal .modal-field label { display:block; font-size:.9rem; margin-bottom:.25rem; }
    #signup-modal .modal-actions { display:flex; gap:.5rem; margin-top:.5rem; }
    #signup-modal .btn-secondary { background:#f3f4f6; color:#111827; border:1px solid #e5e7eb; padding:.625rem 1rem; border-radius:.5rem; }
  </style>
</head>
<body>
  <div class="auth-card">
    <div class="mb-6 text-center">
      <img id="logo" alt="Tookle Logo" class="h-24 w-auto mx-auto">
    </div>

    <h2 id="form-title" class="text-2xl font-bold text-center mb-6">Welcome to TOOKLE</h2>

    <form id="auth-form" novalidate>
      <div id="name-section" class="mb-4" style="display:none;">
        <label for="name" class="block text-sm font-medium mb-1">Name</label>
        <input type="text" id="name" name="name" class="input-standard" placeholder="Enter your name">
      </div>

      <div class="mb-4">
        <label for="email" class="block text-sm font-medium mb-1">Email</label>
        <input type="email" id="email" name="email" required class="input-standard" placeholder="Enter your email">
      </div>

      <div class="mb-4">
        <label for="password" class="block text-sm font-medium mb-1">Password</label>
        <input type="password" id="password" name="password" required class="input-standard" placeholder="Password">
      </div>

      <div id="repeat-password-section" class="mb-4" style="display:none;">
        <label for="repeat-password" class="block text-sm font-medium mb-1">Repeat Password</label>
        <input type="password" id="repeat-password" name="repeatPassword" class="input-standard" placeholder="Repeat Password">
      </div>

      <!-- reCAPTCHA (obligatoire avant Google / EVM / Solana) -->
      <div class="g-recaptcha mb-2" data-sitekey="6LfswIwrAAAAABsemKnqo0lZd9rMjP7lnK9x6ali"></div>

      <p id="message-area" class="message-area" role="status" aria-live="polite"></p>

      <div class="mt-6">
        <button type="submit" id="submit-button" class="btn-submit">Login</button>
      </div>

      <!-- Le lien Forgot Password -->
      <div class="mt-2 text-right">
        <a href="#" id="forgot-link" style="font-size:.875rem;color:#8e52ff;text-decoration:none;">Forgot Password?</a>
      </div>

      <div class="social-login">
        <!-- Google -->
        <button class="btn-icon" id="google-btn" type="button" aria-label="Continue with Google" title="Continue with Google">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5.7448 8.857C6.32721 7.69727 7.22066 6.72236 8.3253 6.04123C9.42994 5.3601 10.7022 4.99959 12 5C13.8865 5 15.4713 5.693 16.683 6.8235L14.6761 8.8311C13.9502 8.1374 13.0276 7.7839 12 7.7839C10.1765 7.7839 8.633 9.0159 8.0835 10.67C7.9435 11.09 7.8637 11.538 7.8637 12C7.8637 12.462 7.9435 12.91 8.0835 13.33C8.6337 14.9848 10.1765 16.2161 12 16.2161C12.9415 16.2161 13.743 15.9676 14.3702 15.5476C14.7338 15.3082 15.0451 14.9976 15.2852 14.6344C15.5254 14.2713 15.6894 13.8633 15.7674 13.435H12V10.7274H18.5926C18.6752 11.1852 18.72 11.6626 18.72 12.1589C18.72 14.2911 17.957 16.0859 16.6326 17.3039C15.4748 18.3735 13.89 19 12 19C11.0806 19.0004 10.1702 18.8196 9.32078 18.4679C8.47134 18.1163 7.69952 17.6007 7.04943 16.9506C6.39935 16.3005 5.88375 15.5287 5.53209 14.6792C5.18044 13.8298 4.99963 12.9194 5 12C5 10.8702 5.2702 9.802 5.7448 8.857Z" fill="currentColor"/>
          </svg>
          <span>Continue with Google</span>
        </button>

        <!-- EVM Wallet -->
        <button class="btn-icon" id="wallet-btn" type="button" aria-label="Connect Wallet EVM"
                title="Connect with Metamask, Rabby or compatible EVM wallet">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12.3112 4.5V10.0437L16.9969 12.1375L12.3112 4.5Z" fill="currentColor"></path>
            <path d="M12.3112 4.5L7.625 12.1375L12.3112 10.0437V4.5Z" fill="currentColor"></path>
            <path d="M12.3112 15.73V19.4969L17 13.01L12.3112 15.73Z" fill="currentColor"></path>
            <path d="M12.3112 19.4969V15.7294L7.625 13.01L12.3112 19.4969Z" fill="currentColor"></path>
            <path d="M12.3112 14.858L16.9969 12.1374L12.3112 10.0449V14.858Z" fill="currentColor"></path>
            <path d="M7.625 12.1374L12.3112 14.858V10.0449L7.625 12.1374Z" fill="currentColor"></path>
          </svg>
          <span>EVM wallet</span>
        </button>

        <!-- Solana Wallet -->
        <button class="btn-icon" id="phantom-btn" type="button" aria-label="Connect Phantom Wallet (Solana)"
                title="Connect with Phantom Wallet (Solana)">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M8.19926 14.3691C8.26855 14.2998 8.36384 14.2594 8.4649 14.2594H17.6295C17.7969 14.2594 17.8807 14.4615 17.7623 14.5799L15.9519 16.3903C15.8826 16.4596 15.7873 16.5 15.6863 16.5H6.52168C6.35421 16.5 6.27048 16.2979 6.38886 16.1795L8.19926 14.3691Z" fill="currentColor"></path>
            <path d="M8.19926 7.60972C8.27144 7.54042 8.36673 7.5 8.4649 7.5H17.6295C17.7969 7.5 17.8807 7.70212 17.7623 7.8205L15.9519 9.6309C15.8826 9.70019 15.7873 9.74062 15.6863 9.74062H6.52168C6.35421 9.74062 6.27048 9.5385 6.38886 9.42012L8.19926 7.60972Z" fill="currentColor"></path>
            <path d="M15.9519 10.9678C15.8826 10.8985 15.7873 10.858 15.6863 10.858H6.52168C6.35421 10.858 6.27048 11.0602 6.38886 11.1785L8.19926 12.9889C8.26855 13.0582 8.36384 13.0987 8.4649 13.0987H17.6295C17.7969 13.0987 17.8807 12.8965 17.7623 12.7782L15.9519 10.9678Z" fill="currentColor"></path>
          </svg>
          <span>Solana wallet</span>
        </button>

        <p class="mt-1 text-center text-sm">Don't have an account? <a href="#" id="switch-link" class="font-medium text-[var(--primary-purple)]">Register Now</a></p>
      </div>
    </form>
  </div>

  <!-- Modal Signup (ouvert uniquement si need_signup=true) -->
  <div id="signup-modal">
    <div class="modal-card">
      <h3>Complete your signup</h3>
      <div class="modal-field">
        <label for="first_name_modal">First name</label>
        <input type="text" id="first_name_modal" class="input-standard" placeholder="Your first name">
      </div>
      <div class="modal-field">
        <label for="last_name_modal">Name</label>
        <input type="text" id="last_name_modal" class="input-standard" placeholder="Your last name">
      </div>
      <div class="modal-field">
        <label for="email_modal">Email</label>
        <input type="email" id="email_modal" class="input-standard" placeholder="email@example.com">
      </div>
      <div class="modal-actions">
        <button id="modal-cancel" type="button" class="btn-secondary">Cancel</button>
        <button id="modal-submit" type="button" class="btn-submit" style="min-width: 10rem;">Create & continue</button>
      </div>
      <p id="modal-message" class="message-area" style="margin-top:.5rem;"></p>
    </div>
  </div>

  <!-- Forgot Password modal -->
  <div id="forgot-modal" class="hide" style="background:#fff;padding:1.5em;border-radius:.5em;box-shadow:0 0 12px #0001;position:fixed;top:40%;left:50%;transform:translate(-50%,-50%);z-index:2200;width:90%;max-width:350px;">
    <div style="margin-bottom:.8em;">Enter your email to reset your password:</div>
    <input type="email" id="forgot-email" placeholder="email@example.com" style="width:100%;padding:.7em;border:1px solid #ddd;border-radius:.5em;">
    <button id="forgot-send" type="button" style="margin:1em 0 0 0;background:#8e52ff;color:#fff;padding:.7em 1.2em;border:none;border-radius:.5em;width:100%;">Send Reset Link</button>
    <div id="forgot-message" style="margin:.8em 0 0 0;text-align:center;font-size:.95em;"></div>
    <div style="margin-top:.7em;text-align:center;"><a href="#" id="forgot-close" style="color:#8e52ff;">Close</a></div>
  </div>
  <div id="forgot-backdrop" class="hide" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.15);z-index:2100;"></div>

  <script>
    // Utils
    const messageArea = document.getElementById('message-area');
    function showMsg(txt, kind='error'){ messageArea.textContent = txt; messageArea.className = 'message-area '+(kind==='success'?'success':'error'); }
    function needCaptcha(){
      const token = (typeof grecaptcha!=='undefined') ? grecaptcha.getResponse() : '';
      if (!token){ showMsg('Veuillez cocher le captcha avant de continuer.'); return null; }
      return token;
    }
    function toHex(u8){ return Array.from(u8).map(b=>b.toString(16).padStart(2,'0')).join(''); }

    // Switch login/register (si tu l'utilises)
    let isLoginMode = true;
    (function(){
      const switchLinkEl = document.getElementById('switch-link');
      const nameSection = document.getElementById('name-section');
      const repeatSection = document.getElementById('repeat-password-section');
      const submitButton = document.getElementById('submit-button');
      switchLinkEl.addEventListener('click', (e)=>{
        e.preventDefault();
        isLoginMode = !isLoginMode;
        if (isLoginMode){ submitButton.textContent='Login'; nameSection.style.display='none'; repeatSection.style.display='none'; }
        else { submitButton.textContent='Register'; nameSection.style.display='block'; repeatSection.style.display='block'; }
      });
    })();

    // Form email/password
    document.getElementById('auth-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const token = needCaptcha(); if (!token) return;

      const fd = new FormData(e.currentTarget);
      fd.append('action', isLoginMode ? 'login' : 'register');

      try{
        const res = await fetch('/backend/login_backend.php', { method:'POST', body: fd, credentials:'include' });
        const txt = await res.text(); let j=null; try{ j=JSON.parse(txt);}catch{}
        if (j && j.success){ location.href='/settings'; return; }
        showMsg((j && j.error) ? j.error : 'Unexpected server response.');
      }catch(err){ showMsg('Could not connect to the server.'); }
    });

    // GOOGLE (captcha gating + credentials: include au backend)
    document.getElementById('google-btn').addEventListener('click', () => {
      const token = needCaptcha(); if (!token) return;

      if (!window.google || !google.accounts || !google.accounts.id){
        showMsg('Google SDK not loaded.'); return;
      }
      google.accounts.id.initialize({
        client_id: '1027942193546-34todkfaofhbhh2iigj4dhhl1ufs26oc.apps.googleusercontent.com',
        callback: async (resp) => {
          try{
            const r = await fetch('/backend/login_backend.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'include',
              body: JSON.stringify({ action: 'login_google', id_token: resp.credential })
            });
            const t = await r.text(); let j=null; try{ j=JSON.parse(t);}catch{}
            if (j && j.success){ location.href='/settings'; return; }
            showMsg((j && j.error) ? j.error : 'Google login failed.');
          }catch(e){ showMsg('Network error during Google login.'); }
        }
      });
      google.accounts.id.prompt();
    });

    // ----- Modal state (commune EVM & Solana) -----
    const modal = document.getElementById('signup-modal');
    const modalCancel = document.getElementById('modal-cancel');
    const modalSubmit = document.getElementById('modal-submit');
    const modalMsg = document.getElementById('modal-message');
    const firstNameEl = document.getElementById('first_name_modal');
    const lastNameEl  = document.getElementById('last_name_modal');
    const emailEl     = document.getElementById('email_modal');

    modalCancel.addEventListener('click', ()=>{ modal.style.display='none'; modalMsg.textContent=''; });

    modalSubmit.addEventListener('click', async ()=>{
      const first_name = firstNameEl.value.trim();
      const last_name  = lastNameEl.value.trim();
      const email      = emailEl.value.trim();

      if (!first_name || !last_name || !email){
        modalMsg.textContent = 'Please fill all fields.'; modalMsg.className='message-area error'; return;
      }

      try{
        if (modal.dataset.flow === 'evm'){
          const res = await fetch('/backend/login_backend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              action: 'login_wallet',
              phase: 'complete_signup', // <--- C'EST CETTE LIGNE QUI MANQUE
              wallet_address: modal.dataset.address,
              message: modal.dataset.message,
              signature: modal.dataset.signature,
              first_name, last_name, email
            })
          });
          const j = await res.json().catch(()=>({}));
          if (j && j.success){ location.href='/settings'; return; }
          modalMsg.textContent = (j && j.error) ? j.error : 'Signup (wallet) failed.'; modalMsg.className='message-area error'; return;
        }

        if (modal.dataset.flow === 'solana'){
          const res = await fetch('/backend/login_backend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              action: 'login_phantom',
              phase: 'complete_signup', // <--- AJOUTER ICI AUSSI
              phantom_pubkey: modal.dataset.phantom,
              message: modal.dataset.siwsMsg,
              signature: modal.dataset.siwsSig,
              first_name, last_name, email
            })
          });
          const j = await res.json().catch(()=>({}));
          if (j && j.success){ location.href='/settings'; return; }
          modalMsg.textContent = (j && j.error) ? j.error : 'Signup (phantom) failed.'; modalMsg.className='message-area error'; return;
        }

        modalMsg.textContent = 'Unknown flow.'; modalMsg.className='message-area error';
      }catch(e){ modalMsg.textContent='Network error.'; modalMsg.className='message-area error'; }
    });

    // ----- EVM (SIWE-like) -----
    document.getElementById('wallet-btn').addEventListener('click', async () => {
      const token = needCaptcha(); if (!token) return;

      if (!window.ethereum){ showMsg('No EVM wallet detected (Metamask/Rabby).'); return; }

      let accounts;
      try{
        accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
      }catch(e){
        showMsg('Wallet request rejected.'); return;
      }
      const address = (accounts && accounts[0]) ? accounts[0] : '';
      if (!address){ showMsg('Wallet not connected.'); return; }

      try{
        const nr = await fetch('/backend/wallet_nonce.php', { credentials: 'include' });
        const nj = await nr.json().catch(()=>({}));
        if (!nj || !nj.success || !nj.nonce){ showMsg('Could not get nonce from server.'); return; }
        const nonce = nj.nonce;

        const message = `Sign in to Tookle (EVM)\nNonce: ${nonce}`;

        const signature = await window.ethereum.request({
          method: 'personal_sign',
          params: [ message, address ]
        });

        const r = await fetch('/backend/login_backend.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            action: 'login_wallet',
            wallet_address: address,
            message,
            signature
          })
        });
        const j = await r.json().catch(()=>({}));

        if (j && j.success){ location.href='/settings'; return; }

        if (j && j.need_signup){
          modal.dataset.flow = 'evm';
          modal.dataset.address = address;
          modal.dataset.message = message;
          modal.dataset.signature = signature;
          firstNameEl.value=''; lastNameEl.value=''; emailEl.value='';
          modalMsg.textContent='';
          modal.style.display = 'flex';
          showMsg("We couldn't find this wallet. Please provide your details to create your account.", 'error');
          return;
        }

        showMsg((j && j.error) ? j.error : 'Wallet login failed.');
      }catch(e){
        showMsg('EVM login failed.');
      }
    });

    // ----- PHANTOM (Solana) SIWS -----
    document.getElementById('phantom-btn').addEventListener('click', async () => {
      const token = needCaptcha(); if (!token) return;

      if (!(window.solana && window.solana.isPhantom)){
        showMsg('Phantom Wallet not detected.'); return;
      }
      if (typeof window.solana.signMessage !== 'function'){
        showMsg('Phantom does not support signMessage on this device/version.'); return;
      }

      try{
        const resp = await window.solana.connect();
        const pubkey = resp.publicKey && resp.publicKey.toString ? resp.publicKey.toString() : '';
        if (!pubkey){ showMsg('Phantom public key unavailable.'); return; }

        const nr = await fetch('/backend/phantom_nonce.php', { credentials: 'include' });
        const nj = await nr.json().catch(()=>({}));
        if (!nj || !nj.success || !nj.nonce){ showMsg('Could not get nonce from server.'); return; }
        const nonce = nj.nonce;

        const message = `Sign in to Tookle with Solana\nNonce: ${nonce}`;
        const encoded = new TextEncoder().encode(message);
        const sigRes = await window.solana.signMessage(encoded, 'utf8');
        const signatureHex = toHex(sigRes.signature);

        const r = await fetch('/backend/login_backend.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            action: 'login_phantom',
            phantom_pubkey: pubkey,
            message,
            signature: signatureHex
          })
        });
        const j = await r.json().catch(()=>({}));

        if (j && j.success){ location.href='/settings'; return; }

        if (j && j.need_signup){
          modal.dataset.flow = 'solana';
          modal.dataset.phantom = pubkey;
          modal.dataset.siwsMsg = message;
          modal.dataset.siwsSig = signatureHex;
          firstNameEl.value=''; lastNameEl.value=''; emailEl.value='';
          modalMsg.textContent='';
          modal.style.display = 'flex';
          showMsg("We couldn't find this wallet. Please provide your details to create your account.", 'error');
          return;
        }

        showMsg((j && j.error) ? j.error : 'Phantom login failed.');
      }catch(e){
        showMsg('Phantom login cancelled or failed.');
      }
    });

    // ----- Forgot Password modal -----
    const forgotLink = document.getElementById('forgot-link');
    const forgotModal = document.getElementById('forgot-modal');
    const forgotBackdrop = document.getElementById('forgot-backdrop');
    const forgotClose = document.getElementById('forgot-close');
    const forgotSend = document.getElementById('forgot-send');
    const forgotEmail = document.getElementById('forgot-email');
    const forgotMsg = document.getElementById('forgot-message');

    function openForgot() {
      if (!forgotModal || !forgotBackdrop) return;
      forgotModal.classList.remove('hide');
      forgotBackdrop.classList.remove('hide');
      if (forgotMsg) forgotMsg.textContent = '';
      if (forgotEmail) forgotEmail.value = (document.getElementById('email')?.value || '').trim();
    }
    function closeForgot() {
      if (forgotModal) forgotModal.classList.add('hide');
      if (forgotBackdrop) forgotBackdrop.classList.add('hide');
      if (forgotMsg) forgotMsg.textContent = '';
    }

    if (forgotLink) forgotLink.addEventListener('click', (e) => {
      e.preventDefault();
      openForgot();
    });
    if (forgotClose) forgotClose.addEventListener('click', (e) => {
      e.preventDefault();
      closeForgot();
    });
    if (forgotBackdrop) forgotBackdrop.addEventListener('click', () => closeForgot());

    if (forgotSend) forgotSend.addEventListener('click', async () => {
      const email = (forgotEmail?.value || '').trim();
      if (!email) {
        if (forgotMsg) forgotMsg.textContent = 'Please enter your email.';
        return;
      }
      if (forgotMsg) forgotMsg.textContent = 'Sending...';

      try {
        const r = await fetch('/pages/forgot_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ email })
        });
        const j = await r.json().catch(() => ({}));
        if (j && j.success) {
          if (forgotMsg) forgotMsg.textContent = j.message || 'Reset link sent. Check your inbox.';
        } else {
          if (forgotMsg) forgotMsg.textContent = (j && j.error) ? j.error : 'Could not send reset link.';
        }
      } catch (err) {
        if (forgotMsg) forgotMsg.textContent = 'Network error. Please try again.';
      }
    });

    // Logo
    if (typeof TOOKLE_LOGO_BASE64 !== 'undefined'){
      document.getElementById('logo').src = TOOKLE_LOGO_BASE64;
    }
  </script>
</body>
</html>
