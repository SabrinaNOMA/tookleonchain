<?php
/**
 * Login / Register backend Ã¢â‚¬â€ JSON + form POST
 * Table `user` :
 *  id, first_name, last_name, email, password, country, profile_description,
 *  language, invite_code, wallet_address, coinbase_wallet_adress, phantom_pubkey,
 *  signup_method, activation_token, is_active, created_at
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- DB ----------
$db_path = __DIR__ . '/../src/db.php';
if (!file_exists($db_path)) {
    header('Content-Type: application/json');
    error_log("DB file not found: " . $db_path);
    echo json_encode(['success'=>false,'error' => 'Server configuration error.']);
    exit;
}
/** @var PDO $pdo */
$pdo = require $db_path;
header('Content-Type: application/json');


//Importer les classes de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- Chargement de la librairie ---

// Option A : Si vous avez utilisÃƒÂ© Composer
//require 'vendor/autoload.php';

// Option B : Si vous avez tÃƒÂ©lÃƒÂ©chargÃƒÂ© manuellement
//  (Assurez-vous que le chemin est correct)
 require __DIR__ . '/../phpmailer/Exception.php';
 require __DIR__ . '/../phpmailer/PHPMailer.php';
 require __DIR__ . '/../phpmailer/SMTP.php';

// ----------------------------------
// --- Ãƒâ‚¬ CONFIGURER (5 variables) ---
// ----------------------------------

// Brevo SMTP Configuration (Enforced)
$email_expediteur   = 'noreply@tookle.app'; 
$smtp_username      = 'b3c1d2001@smtp-brevo.com';
$mot_de_passe_email = base64_decode('eHNtdHBzaWItNzk1OTJhMGRlNjliNzEwYmIzMGU0NzYxYzRjMWQ4MzJmMjRiZjk3MjhiNGVjYjZjYmI4YzhiMjhhYmVjNGZlYi0wWWY5SGdpQk9ScGNHUjR1');
$serveur_smtp       = 'smtp-relay.brevo.com';
$port_smtp          = 465;

// Mettez l'email oÃƒÂ¹ vous voulez RECEVOIR le test (ex: votre Gmail)
//$email_destinataire = 'philippe@ifabe.fr';

// ----------------------------------

$mail = new PHPMailer(true); // 'true' active les exceptions

// ---------- Helpers ----------
function reply(array $payload, int $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function respond_success(array $extra = []) { reply(array_merge(['success'=>true], $extra)); }
function respond_error(string $msg, int $code = 200, array $extra = []) { reply(array_merge(['success'=>false,'error'=>$msg], $extra), $code); }

function is_valid_email(string $s): bool {
    return (bool)filter_var($s, FILTER_VALIDATE_EMAIL);
}
function is_valid_evm(string $s): bool {
    return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $s);
}
function is_valid_b58(string $s): bool {
    return (bool)preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $s);
}
function generate_unique_invite_code(PDO $pdo): string {
    do {
        $random_part = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
        $invite_code = 'TKL-' . $random_part;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE invite_code = ?");
        $stmt->execute([$invite_code]);
        $count = (int)$stmt->fetchColumn();
    } while ($count > 0);
    return $invite_code;
}
function b64url_decode(string $data): string {
    $replaced = strtr($data, '-_', '+/');
    $pad = strlen($replaced) % 4;
    if ($pad) $replaced .= str_repeat('=', 4 - $pad);
    return base64_decode($replaced) ?: '';
}

// ---------- Read Input ----------
$rawBody = file_get_contents('php://input') ?: '';
$isJson  = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
$data    = $isJson ? (json_decode($rawBody, true) ?: []) : $_POST;

// action
$action  = $data['action'] ?? ($_POST['action'] ?? '');

// trace minimal
error_log(sprintf("[login_backend] %s %s action=%s", $_SERVER['REQUEST_METHOD'] ?? 'N/A', $_SERVER['CONTENT_TYPE'] ?? 'N/A', $action));

// ---------- ROUTER ----------
try {
    // -------- REGISTER (email/password) --------
    if ($action === 'register') {
        $name     = trim($data['name'] ?? '');
        $email    = trim($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            respond_error('All fields are required.');
        }

        // Terms of Service & Privacy consent (required)
        $accept_terms = $data['accept_terms'] ?? ($data['terms_accept'] ?? ($data['agree_terms'] ?? ''));
        $accept_terms = is_bool($accept_terms) ? ($accept_terms ? '1' : '') : (string)$accept_terms;
        if (!in_array($accept_terms, ['1','true','on','yes'], true)) {
            echo json_encode(['success'=>false,'error'=>'You must agree to the Terms of Service and Privacy Policy.']);
            exit;
        }

        $parts = preg_split('/\s+/', $name, 2);
        $first = $parts[0] ?? '';
        $last  = $parts[1] ?? '';

        $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            respond_error('An account with this email already exists.', 200, ['code' => 'EMAIL_EXISTS', 'email' => $email]);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $invite = generate_unique_invite_code($pdo);
		$activation_token = bin2hex(random_bytes(32));


        $stmt = $pdo->prepare("
            INSERT INTO user (first_name, last_name, email, password, invite_code, activation_token, is_active, created_at, terms_accepted_at)
            VALUES (?, ?, ?, ?, ?, ?, (CASE WHEN ? = 1 THEN 1 ELSE 0 END), NOW(), NOW())
        ");
        $is_dev_env = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false || PHP_SAPI === 'cli');
        $stmt->execute([$first, $last, $email, $hashed, $invite, $activation_token, $is_dev_env ? 1 : 0]);
		
	 // Mail dÃ¢â‚¬â„¢activation non requis si tu veux activer direct, sinon dÃƒÂ©commente :
     $activation_link = "https://preprod.tookle.app/pages/activate.php?token=" . $activation_token ."&email=".$email;
	 
	 
	    // --- Configuration du serveur SMTP (Ne pas toucher) ---
 try {   
    // Active le mode DEBUG. TRÃƒË†S IMPORTANT pour les tests !
     //SMTP::DEBUG_OFF (0) = Pas de debug
    // SMTP::DEBUG_SERVER (2) = Affiche toute la conversation avec le serveur
    //$mail->SMTPDebug = SMTP::DEBUG_SERVER; 
                                          
    $mail->isSMTP();
    $mail->Host       = $serveur_smtp;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = $mot_de_passe_email;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Port 465 SSL (OVH compatible)
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    // --- Configuration de l'email ---

    // Expéditeur
    $mail->setFrom($email_expediteur, 'TOOKLE');
    
    // Destinataire
    $mail->addAddress($email);
    
    // Contenu de l'email

	$mail->isHTML(true);
    $mail->Subject = 'Activate your Tookle account';

 $mail->Body = "
<p>Hello $first $last,</p>

<p><strong>Welcome to Tookle!</strong></p>

<p>
To get started, please click the link below to activate your account:
</p>

<p>
<a href='{$activation_link}' 
   style='display:inline-block;padding:10px 18px;
          background:#8e52ff;color:#ffffff;
          text-decoration:none;border-radius:6px;
          font-weight:600;'>
Activate my account
</a>
</p>

<p>
If you have any questions, our team is always happy to help.
</p>

<p>
Best regards,<br>
<strong>The Tookle Team</strong>
</p>
";


    // Envoi
    if ($is_dev_env) {
        respond_success(['message' => 'Registration successful! Please log in.', 'activation_link' => $activation_link]);
    } else {
        $mail->send();
    }
} catch (Exception $e) {
    error_log('PHPMailer activation email error: ' . $e->getMessage());
    respond_error('Unable to send activation email. Please try again or contact support.');
}	 
	 
     //mail($email, "Activate your account", "Click this link: $activation_link");

        respond_success(['message' => 'Registration successful! Please log in.']);
    }

    // -------- LOGIN (email/password) --------
    if ($action === 'login') {
        $email    = trim($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            respond_error('Email and password are required.');
        }

        $stmt = $pdo->prepare("SELECT id, password, is_active FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. VÃƒÂ©rification du mot de passe
        if ($user && password_verify($password, $user['password'])) {
            
            // 3. NOUVELLE VÃƒâ€°RIFICATION : Le compte est-il actif ?
            if ((int)$user['is_active'] !== 1) {
                respond_error('Your account is not activated. Please check your emails.');
            }

            // Si tout est bon, on connecte
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            respond_success();
        }
        respond_error('Invalid email or password.');
    }

    // -------- LOGIN GOOGLE --------
    if ($action === 'login_google') {
        $idToken = trim($data['id_token'] ?? '');
        if ($idToken === '') respond_error('Google token is missing. Please try signing in again.');

        // parse local du JWT
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) respond_error('Invalid Google authentication token. Please try again.');
        $payloadJson = b64url_decode($parts[1]);
        $payload = json_decode($payloadJson, true) ?: [];

        $email       = trim($payload['email'] ?? '');
        $given_name  = trim($payload['given_name'] ?? '');
        $family_name = trim($payload['family_name'] ?? '');
        if ($email === '') respond_error('Your Google account is missing an email address. Please try another account.');

        $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $uid = $stmt->fetchColumn();

        if ($uid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$uid;
            respond_success();
        }

        // create user (google)
        $randPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $token    = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("
            INSERT INTO user (first_name, last_name, email, password, activation_token, signup_method, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 'google', 1, NOW())
        ");
        $stmt->execute([$given_name, $family_name, $email, $randPass, $token]);

        $newId = (int)$pdo->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newId;
        respond_success();
    }

   
    // -------- LOGIN WALLET (EVM) --------
    if ($action === 'login_wallet') {

        // --- PHASE 2: ComplÃƒÂ©ter l'inscription (Popup Modal) ---
        if (($data['phase'] ?? '') === 'complete_signup') {
            
            // RÃƒÂ©cupÃƒÂ©rer l'adresse vÃƒÂ©rifiÃƒÂ©e de la session (placÃƒÂ©e en Phase 1)
            $ver = $_SESSION['evm_verified'] ?? null;
            if (!$ver || empty($ver['address']) || empty($ver['ts']) || (time() - (int)$ver['ts'] > 300)) {
                respond_error('Session not verified for EVM or expired. Please sign again.');
            }
            
            $wallet_address = $ver['address']; // Utiliser l'adresse sÃƒÂ©curisÃƒÂ©e de la session
            $first = trim($data['first_name'] ?? '');
            $last  = trim($data['last_name'] ?? '');
            $email = trim($data['email'] ?? '');

            if ($first === '' || $last === '' || !is_valid_email($email)) {
                respond_error('Missing or invalid required fields.');
            }

            // ** CORRECTION DEMANDÃƒâ€°E : VÃƒâ€°RIFICATION DE L'EMAIL **
            $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                respond_error('This email address is already in use'); // Message d'erreur
            }
            // ** FIN CORRECTION **
            
            // L'email est unique, crÃƒÂ©er le compte
            $token  = bin2hex(random_bytes(32));
            $invite = generate_unique_invite_code($pdo);
            $randPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO user (first_name, last_name, email, password, wallet_address, signup_method, activation_token, invite_code, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 'wallet', ?, ?, 1, NOW())
            ");
            $stmt->execute([$first, $last, $email, $randPass, $wallet_address, $token, $invite]);

            $newId = (int)$pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newId;
            unset($_SESSION['evm_verified']); 

            respond_success();
        }

        // --- PHASE 1: VÃƒÂ©rification de l'adresse (connexion initiale) ---
        $wallet = strtolower(trim($data['wallet_address'] ?? ''));
        if (!is_valid_evm($wallet)) {
            respond_error('Missing or invalid wallet address.');
        }

        // (!!) AVERTISSEMENT DE SÃƒâ€°CURITÃƒâ€° :
        // Votre script original NE VÃƒâ€°RIFIE PAS LA SIGNATURE (SIWE).
        // Un attaquant peut usurper n'importe quelle adresse.
        // Je m'en tiens ÃƒÂ  la logique de votre fichier (sans vÃƒÂ©rification) 
        // mais je vous recommande fortement d'implÃƒÂ©menter la vÃƒÂ©rification de signature.

        // L'utilisateur existe-t-il dÃƒÂ©jÃƒÂ  ?
        $stmt = $pdo->prepare("SELECT id FROM user WHERE wallet_address = ?");
        $stmt->execute([$wallet]);
        $uid = $stmt->fetchColumn();

        if ($uid) {
            // Oui: Connexion
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$uid;
            respond_success();
        }

        // Non: Marquer la session comme vÃƒÂ©rifiÃƒÂ©e et demander l'inscription (Phase 2)
        $_SESSION['evm_verified'] = ['address' => $wallet, 'ts' => time()];
        respond_error('Wallet not found, signup required.', 200, ['need_signup' => true]);
    }

         // -------- LOGIN PHANTOM (Solana) --------
   	
		// ---- VÃƒÂ©rification Ed25519 (Phantom / Solana) avec libsodium ----

    // -------- LOGIN PHANTOM (Solana) --------
    if ($action === 'login_phantom') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // PHASE 2: ComplÃƒÂ©ter inscription depuis la popup, sans re-signer
        if (($data['phase'] ?? '') === 'complete_signup') {
            // Doit venir juste aprÃƒÂ¨s une phase 1 validÃƒÂ©e
            $ver = $_SESSION['phantom_verified'] ?? null;
            if (!$ver || empty($ver['pubkey']) || empty($ver['ts']) || (time() - (int)$ver['ts'] > 300)) {
                respond_error('Session not verified for Phantom or expired. Please sign again.');
            }

            $first = trim($data['first_name'] ?? '');
            $last  = trim($data['last_name'] ?? '');
            $email = trim($data['email'] ?? '');
            
            // Validation amÃƒÂ©liorÃƒÂ©e
            if ($first === '' || $last === '' || !is_valid_email($email)) {
                respond_error('Missing or invalid required fields.');
            }
            
            // ** CORRECTION DEMANDÃƒâ€°E : VÃƒâ€°RIFICATION DE L'EMAIL **
            $stmt = $pdo->prepare("SELECT id FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                respond_error('This email address is already in use'); // Message d'erreur
            }
            // ** FIN CORRECTION **

            // Utiliser la pubkey de la session (sÃƒÂ©curisÃƒÂ©)
            $pubkey = $ver['pubkey']; 
            
            // CrÃƒÂ©er lÃ¢â‚¬â„¢utilisateur
            $token = bin2hex(random_bytes(32));
            $randPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $invite = generate_unique_invite_code($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO user (first_name, last_name, email, password, phantom_pubkey, signup_method, activation_token, invite_code, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 'phantom', ?, ?, 1, NOW())
            ");
            $stmt->execute([$first, $last, $email, $randPass, $pubkey, $token, $invite]);

            // Connexion + cleanup
            $newId = (int)$pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newId;
            unset($_SESSION['phantom_verified']);

            respond_success();
        }

        // PHASE 1: VÃƒÂ©rifier signature+nonce (SIWS). 
        // (Le reste de votre code de Phase 1 reste inchangÃƒÂ©)
        $pubkey  = trim($data['phantom_pubkey'] ?? '');
        $message = (string)($data['message'] ?? '');
        $sig_hex = strtolower(trim($data['signature'] ?? ''));

        if ($pubkey === '' || $message === '' || $sig_hex === '') {
            respond_error('Missing parameters for Phantom login.');
        }

        // Nonce serveur
        $serverNonce = $_SESSION['phantom_nonce'] ?? null;
        if (!$serverNonce) {
            respond_error('Missing server nonce. Please try again.');
        }

        // Message doit contenir exactement la ligne "Nonce: <nonce>"
        $expectedLine = "Nonce: " . $serverNonce;
        if (strpos($message, $expectedLine) === false) {
            respond_error('Nonce mismatch in message.');
        }

        // Consommer le nonce (anti-replay)
        unset($_SESSION['phantom_nonce']);

        // VÃƒÂ©rification ed25519 (libsodium)
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            respond_error('Sodium not available on server.');
        }

        $hex2bin_safely = function(string $hex): string {
            $bin = @hex2bin($hex);
            return $bin === false ? '' : $bin;
        };
        $base58_decode = function (string $b58): string {
            $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
            $indexes = array_flip(str_split($alphabet));
            $num = gmp_init(0, 10);
            $base = gmp_init(58, 10);
            for ($i = 0, $l = strlen($b58); $i < $l; $i++) {
                $char = $b58[$i];
                if (!isset($indexes[$char])) return '';
                $num = gmp_add(gmp_mul($num, $base), $indexes[$char]);
            }
            $bytes = '';
            while (gmp_cmp($num, 0) > 0) {
                $mod = gmp_intval(gmp_mod($num, 256));
                $bytes = chr($mod) . $bytes;
                $num = gmp_div_q($num, 256);
            }
            for ($i = 0; $i < strlen($b58) && $b58[$i] === '1'; $i++) $bytes = "\x00" . $bytes;
            return $bytes;
        };

        $sig_bin = $hex2bin_safely($sig_hex);
        $pk_bin  = $base58_decode($pubkey);
        if ($sig_bin === '' || $pk_bin === '' || strlen($pk_bin) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            respond_error('Invalid signature or public key.');
        }

        // Le message signÃƒÂ© est EXACTEMENT $message
        if (!sodium_crypto_sign_verify_detached($sig_bin, $message, $pk_bin)) {
            respond_error('Invalid Solana signature.');
        }

        // DÃƒÂ©jÃƒÂ  inscrit ?
        $stmt = $pdo->prepare("SELECT id FROM user WHERE phantom_pubkey = ?");
        $stmt->execute([$pubkey]);
        $uid = $stmt->fetchColumn();
        if ($uid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$uid;
            respond_success();
        }

        // Pas inscrit : marquer la session comme "clÃƒÂ© vÃƒÂ©rifiÃƒÂ©e" pour 5 min
        $_SESSION['phantom_verified'] = ['pubkey' => $pubkey, 'ts' => time()];
        
        // (Le reste de votre code avec l'ancienne logique est maintenant inatteignable
        // grÃƒÂ¢ce ÃƒÂ  la ligne ci-dessous, ce qui est correct)
        
        respond_error('Phantom key not found, signup required.', 200, ['need_signup'=>true]);
    }

    // -------- Default --------
    respond_error('Invalid action specified.', 400);

} catch (Throwable $e) {
    error_log("login_phantom phase1 fatal: ".$e->getMessage()." @".$e->getFile().":".$e->getLine());
    respond_error('Internal server error.', 500);
}
?>

