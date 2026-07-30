<?php
// (session déjà démarrée par le routeur principal si besoin)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Tookle</title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Google Identity (GSI) -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>

  <!-- Logo Config -->
  <script src="/config_logo.js"></script>

  <!-- reCAPTCHA -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <style>
    :root {
      --primary-purple: #8e52ff;
      --primary-hover: #7b3fe8;
      --border-color: #E5E7EB;
      --error-color: #DC2626;
      --success-color: #16A34A;
      --font-family: 'Montserrat', sans-serif;
    }
    body {
      background: linear-gradient(135deg, #f5efff 0%, #e0e7ff 100%);
      font-family: var(--font-family);
      color: #111827;
      display: flex; 
      align-items: center; 
      justify-content: center;
      min-height: 100vh; 
      padding: 1.5rem;
    }
    .auth-card {
      background: #ffffff; 
      padding: 2.5rem; 
      border-radius: 1rem;
      box-shadow: 0 20px 25px -5px rgba(142, 82, 255, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
      width: 100%; 
      max-width: 26rem; 
      position: relative;
      border: 1px solid rgba(255, 255, 255, 0.8);
    }
    .input-standard {
      width: 100%; 
      padding: 0.75rem 1rem; 
      border: 1px solid var(--border-color); 
      border-radius: 0.6rem; 
      box-sizing: border-box;
      transition: all 0.2s ease;
      background-color: #F9FAFB;
    }
    .input-standard:focus {
      outline: none;
      border-color: var(--primary-purple);
      background-color: #ffffff;
      box-shadow: 0 0 0 3px rgba(142, 82, 255, 0.15);
    }
    .btn-submit {
      width: 100%; 
      padding: 0.85rem 1rem; 
      border-radius: 0.6rem; 
      font-weight: 600; 
      color: #ffffff;
      background: linear-gradient(135deg, #8e52ff 0%, #6366f1 100%); 
      border: none;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .btn-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(142, 82, 255, 0.25);
    }
    .btn-google {
      display: flex; 
      align-items: center; 
      justify-content: center; 
      gap: 0.75rem; 
      background: #ffffff; 
      border: 1px solid #D1D5DB;
      border-radius: 0.6rem; 
      font-size: 0.95rem; 
      font-weight: 600; 
      color: #374151; 
      padding: 0.75rem 1.2rem; 
      width: 100%; 
      cursor: pointer; 
      box-sizing: border-box;
      transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-google:hover {
      background-color: #F9FAFB;
      border-color: #9CA3AF;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    .btn-google svg { 
      width: 22px; 
      height: 22px; 
      flex-shrink: 0; 
    }
    .message-area { 
      font-size: 0.85rem; 
      text-align: center; 
      min-height: 1.25rem; 
      margin: 0.5rem 0 0; 
    }
    .message-area.error { color: var(--error-color); }
    .message-area.success { color: var(--success-color); }

    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 1.5rem 0;
      color: #9CA3AF;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.05em;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #E5E7EB;
    }
    .divider::before { margin-right: 0.75em; }
    .divider::after { margin-left: 0.75em; }

    .hide { display: none !important; }

    /* Centered reCAPTCHA alignment */
    .captcha-container {
      display: flex;
      justify-content: center;
      margin-bottom: 0.75rem;
    }
  </style>
</head>
<body>
  <div class="auth-card">
    <!-- Brand Logo -->
    <div class="mb-5 text-center">
      <img id="logo" alt="Tookle Logo" class="h-20 w-auto mx-auto">
    </div>

    <h2 id="form-title" class="text-xl font-bold text-center mb-6 text-gray-900">Welcome to TOOKLE</h2>

    <!-- Google SSO Option (Direct 1-Click Login, no Captcha required) -->
    <div class="mb-2">
      <button class="btn-google" id="google-btn" type="button" aria-label="Continue with Google" title="Continue with Google">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
        </svg>
        <span>Continue with Google</span>
      </button>
    </div>

    <!-- Visual Separator -->
    <div class="divider">OR</div>

    <!-- Email / Password Form (Login & Register) -->
    <form id="auth-form" novalidate>
      <!-- Name Section (REGISTER mode only) -->
      <div id="name-section" class="mb-3" style="display:none;">
        <label for="name" class="block text-xs font-semibold text-gray-700 mb-1">Full Name</label>
        <input type="text" id="name" name="name" class="input-standard" placeholder="John Doe">
      </div>

      <!-- Email Field -->
      <div class="mb-3">
        <label for="email" class="block text-xs font-semibold text-gray-700 mb-1">Email Address</label>
        <input type="email" id="email" name="email" required class="input-standard" placeholder="name@example.com">
      </div>

      <!-- Password Field -->
      <div class="mb-3">
        <label for="password" class="block text-xs font-semibold text-gray-700 mb-1">Password</label>
        <input type="password" id="password" name="password" required class="input-standard" placeholder="••••••••">
      </div>

      <!-- Repeat Password Section (REGISTER mode only) -->
      <div id="repeat-password-section" class="mb-3" style="display:none;">
        <label for="repeat-password" class="block text-xs font-semibold text-gray-700 mb-1">Repeat Password</label>
        <input type="password" id="repeat-password" name="repeatPassword" class="input-standard" placeholder="••••••••">
      </div>

      <!-- Terms & Privacy consent (REGISTER mode only) -->
      <div id="terms-section" class="mb-3" style="display:none;">
        <label class="flex items-start gap-2 text-xs text-gray-600">
          <input type="checkbox" id="accept_terms" name="accept_terms" value="1" class="mt-0.5 rounded text-[var(--primary-purple)]">
          <span>
            I agree to the <a href="/pages/terms.php" target="_blank" class="font-semibold text-[var(--primary-purple)] hover:underline">Terms of Service</a>
            and <a href="/pages/privacy.php" target="_blank" class="font-semibold text-[var(--primary-purple)] hover:underline">Privacy Policy</a>.
          </span>
        </label>
      </div>

      <!-- reCAPTCHA Container (Required for Email/Password form) -->
      <div class="captcha-container">
        <div class="g-recaptcha" data-sitekey="6LfswIwrAAAAABsemKnqo0lZd9rMjP7lnK9x6ali"></div>
      </div>

      <p id="message-area" class="message-area" role="status" aria-live="polite"></p>

      <div class="mt-4">
        <button type="submit" id="submit-button" class="btn-submit">Login</button>
      </div>

      <!-- Forgot Password Link -->
      <div class="mt-3 text-right">
        <a href="#" id="forgot-link" class="text-xs font-medium text-[var(--primary-purple)] hover:underline">Forgot Password?</a>
      </div>

      <!-- Mode Switcher (Login <-> Register) -->
      <div class="mt-5 text-center text-xs text-gray-600">
        <span id="switch-text">Don't have an account?</span>
        <a href="#" id="switch-link" class="font-semibold text-[var(--primary-purple)] hover:underline ml-1">Register Now</a>
      </div>
    </form>
  </div>

  <!-- Forgot Password Modal -->
  <div id="forgot-modal" class="hide" style="background:#fff;padding:1.75em;border-radius:1rem;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);position:fixed;top:40%;left:50%;transform:translate(-50%,-50%);z-index:2200;width:90%;max-width:360px;border:1px solid #E5E7EB;">
    <h3 class="text-base font-bold text-gray-900 mb-2">Reset Password</h3>
    <p class="text-xs text-gray-600 mb-3">Enter your email address to receive a password reset link:</p>
    <input type="email" id="forgot-email" placeholder="name@example.com" class="input-standard text-sm mb-3">
    <button id="forgot-send" type="button" class="btn-submit text-sm">Send Reset Link</button>
    <div id="forgot-message" class="message-area mt-2"></div>
    <div class="mt-3 text-center"><a href="#" id="forgot-close" class="text-xs text-gray-500 hover:underline">Cancel</a></div>
  </div>
  <div id="forgot-backdrop" class="hide" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:2100;"></div>

  <script>
    const messageArea = document.getElementById('message-area');

    function showMsg(txt, kind = 'error') { 
      messageArea.textContent = txt; 
      messageArea.className = 'message-area ' + (kind === 'success' ? 'success' : 'error'); 
    }

    // Hide captcha container on local dev to prevent Google domain error message
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
      const captchaEl = document.querySelector('.captcha-container');
      if (captchaEl) captchaEl.style.display = 'none';
    }

    // Captcha Validator for Email/Password form
    function needCaptcha() {
      if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        return 'localhost-dev-bypass';
      }
      const token = (typeof grecaptcha !== 'undefined') ? grecaptcha.getResponse() : '';
      if (!token) { 
        showMsg('Veuillez cocher le captcha avant de continuer.'); 
        return null; 
      }
      return token;
    }

    // Login / Register Mode Toggle
    let isLoginMode = true;
    (function() {
      const switchLinkEl = document.getElementById('switch-link');
      const switchTextEl = document.getElementById('switch-text');
      const nameSection = document.getElementById('name-section');
      const repeatSection = document.getElementById('repeat-password-section');
      const termsSection = document.getElementById('terms-section');
      const submitButton = document.getElementById('submit-button');
      const formTitle = document.getElementById('form-title');

      switchLinkEl.addEventListener('click', (e) => {
        e.preventDefault();
        isLoginMode = !isLoginMode;
        messageArea.textContent = '';

        if (isLoginMode) {
          formTitle.textContent = 'Welcome to TOOKLE';
          submitButton.textContent = 'Login';
          switchTextEl.textContent = "Don't have an account?";
          switchLinkEl.textContent = 'Register Now';
          nameSection.style.display = 'none';
          repeatSection.style.display = 'none';
          if (termsSection) termsSection.style.display = 'none';
        } else {
          formTitle.textContent = 'Create your Account';
          submitButton.textContent = 'Register';
          switchTextEl.textContent = 'Already have an account?';
          switchLinkEl.textContent = 'Log In';
          nameSection.style.display = 'block';
          repeatSection.style.display = 'block';
          if (termsSection) termsSection.style.display = 'block';
        }
      });
    })();

    // Email/Password Form Submit Handler (Enforces Captcha)
    document.getElementById('auth-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const token = needCaptcha(); 
      if (!token) return;

      // Validate Terms in Register mode
      if (!isLoginMode) {
        const cb = document.getElementById('accept_terms');
        if (!cb || !cb.checked) { 
          showMsg('Please accept the Terms of Service and Privacy Policy to continue.'); 
          return; 
        }

        const pass = document.getElementById('password').value;
        const repeatPass = document.getElementById('repeat-password').value;
        if (pass !== repeatPass) {
          showMsg('Passwords do not match.');
          return;
        }
      }

      const fd = new FormData(e.currentTarget);
      const cbTerms = document.getElementById('accept_terms');
      if (cbTerms && cbTerms.checked) fd.set('accept_terms', '1');
      fd.append('action', isLoginMode ? 'login' : 'register');

      try {
        const res = await fetch('/backend/login_backend.php', { 
          method: 'POST', 
          body: fd, 
          credentials: 'include' 
        });
        const txt = await res.text(); 
        let j = null; 
        try { j = JSON.parse(txt); } catch {}

        if (j && j.success) { 
          location.href = '<?= get_url('settings') ?>'; 
          return; 
        }

        // Tier 1 UX: If user tries to register with an existing email, auto-switch to Login mode
        if (j && (j.code === 'EMAIL_EXISTS' || (j.error && j.error.includes('already exists')))) {
          showMsg('An account with this email already exists. Please enter your password to log in.');
          if (!isLoginMode && switchLinkEl) {
            switchLinkEl.click();
          }
          const pwdField = document.getElementById('password');
          if (pwdField) pwdField.focus();
          return;
        }

        showMsg((j && j.error) ? j.error : 'Unexpected server response.');
      } catch (err) { 
        showMsg('Could not connect to the server.'); 
      }
    });

    // GOOGLE SSO Handler (Direct 1-Click Login, NO Captcha Required)
    document.getElementById('google-btn').addEventListener('click', () => {
      if (!window.google || !google.accounts || !google.accounts.id) {
        showMsg('Google SDK not loaded.'); 
        return;
      }
      google.accounts.id.initialize({
        client_id: '1027942193546-34todkfaofhbhh2iigj4dhhl1ufs26oc.apps.googleusercontent.com',
        callback: async (resp) => {
          try {
            const r = await fetch('/backend/login_backend.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'include',
              body: JSON.stringify({ action: 'login_google', id_token: resp.credential })
            });
            const t = await r.text(); 
            let j = null; 
            try { j = JSON.parse(t); } catch {}

            if (j && j.success) { 
              location.href = '<?= get_url('settings') ?>'; 
              return; 
            }
            showMsg((j && j.error) ? j.error : 'Google login failed.');
          } catch (e) { 
            showMsg('Network error during Google login.'); 
          }
        }
      });
      google.accounts.id.prompt();
    });

    // Forgot Password Modal Handlers
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

    if (forgotLink) forgotLink.addEventListener('click', (e) => { e.preventDefault(); openForgot(); });
    if (forgotClose) forgotClose.addEventListener('click', (e) => { e.preventDefault(); closeForgot(); });
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

    // Logo Initialization
    if (typeof TOOKLE_LOGO_BASE64 !== 'undefined') {
      document.getElementById('logo').src = TOOKLE_LOGO_BASE64;
    }
  </script>
</body>
</html>
