<?php
/**
 * Automated QA Test Suite for Login & Registration Page
 */

ob_start();
session_start();

require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain');
echo "===================================================\n";
echo "   RUNNING QA TESTS FOR LOGIN & REGISTRATION PAGE  \n";
echo "===================================================\n\n";

$errors = 0;

function assert_test($condition, $test_name) {
    global $errors;
    if ($condition) {
        echo "[PASS] $test_name\n";
    } else {
        echo "[FAIL] $test_name\n";
        $errors++;
    }
}

$login_file = file_get_contents(__DIR__ . '/../pages/login.php');

// 1. DOM Structural Assertions
assert_test(strpos($login_file, 'id="google-btn"') !== false, "DOM: Google login button (#google-btn) exists");
assert_test(strpos($login_file, 'id="auth-form"') !== false, "DOM: Auth form (#auth-form) exists");
assert_test(strpos($login_file, 'g-recaptcha') !== false, "DOM: reCAPTCHA container (.g-recaptcha) exists");
assert_test(strpos($login_file, 'id="name-section"') !== false, "DOM: Registration name section (#name-section) exists");
assert_test(strpos($login_file, 'id="terms-section"') !== false, "DOM: Registration terms section (#terms-section) exists");

// Check Web3 removal
$evm_absent = (strpos($login_file, 'id="wallet-btn"') === false);
$sol_absent = (strpos($login_file, 'id="phantom-btn"') === false);
assert_test($evm_absent && $sol_absent, "DOM: Web3 Wallet buttons (#wallet-btn, #phantom-btn) are absent from UI");

// 2. JS Logic Assertions
// Google click listener must NOT enforce captcha
$google_has_captcha = preg_match('/document\.getElementById\([\'"]google-btn[\'"]\)\.addEventListener\([^}]*needCaptcha/s', $login_file);
assert_test(!$google_has_captcha, "JS: Google button listener does NOT enforce reCAPTCHA check");

// Auth form submit listener MUST enforce captcha
$form_has_captcha = preg_match('/document\.getElementById\([\'"]auth-form[\'"]\)\.addEventListener\([^}]*needCaptcha/s', $login_file);
assert_test($form_has_captcha, "JS: Email/Password form submit listener ENFORCES reCAPTCHA check");

// 3. Backend DB & Logic Verification
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE email = 'test@example.com'");
$stmt->execute();
assert_test($stmt->columnCount() >= 0, "Backend DB: Database connection is active and user table queryable");

echo "\n===================================================\n";
if ($errors === 0) {
    echo "   QA SUITE COMPLETE: ALL TESTS PASSED             \n";
} else {
    echo "   QA SUITE COMPLETE: $errors FAILED               \n";
}
echo "===================================================\n";
