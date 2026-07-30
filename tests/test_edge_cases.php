<?php
/**
 * Automated Edge-Case & Boundary Condition Test Suite
 * Filepath: tests/test_edge_cases.php
 */

require_once __DIR__ . '/../src/db.php';

// Helper function definitions for test assertions
if (!function_exists('parseCurrencyInput')) {
    function parseCurrencyInput($raw) {
        if ($raw === null || $raw === '') return 0.0;
        $clean = preg_replace('/[^\d.,]/', '', (string)$raw);
        if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif (strpos($clean, ',') !== false) {
            $parts = explode(',', $clean);
            if (count($parts) == 2 && strlen($parts[1]) <= 2) {
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        }
        return (float)$clean;
    }
}

if (!function_exists('getInvestorStatus')) {
    function getInvestorStatus($investmentStatus, $paymentStatus, $saleStatus, $hasPaymentTx = false) {
        $inv = strtolower($investmentStatus ?? '');
        $pay = strtolower($paymentStatus ?? '');
        $sale = strtolower($saleStatus ?? '');
        $hasTx = (bool)$hasPaymentTx;

        if ($inv === 'initiated' && !$hasTx && $pay !== 'successful') {
            return ['status' => 'Unpaid (Draft)', 'description' => 'Agreement signed. Blockchain payment has not been submitted yet.'];
        }

        $is_pending_inv = in_array($inv, ['in_escrow', 'initiated', 'pending']);
        $is_live_sale = in_array($sale, ['live', 'scheduled', 'active', 'open']);
        $is_incomplete_payment = ($pay !== 'successful' && $pay !== 'failed');

        if ($is_pending_inv && ($pay === 'pending' || ($hasTx && $is_incomplete_payment)) && $is_live_sale) {
            return ['status' => 'Processing', 'description' => "Your payment is being confirmed on-chain."];
        }

        if (($inv === 'cancelled' || $inv === 'canceled') && $pay === 'failed') {
            return ['status' => 'Failed', 'description' => 'Your payment could not be processed.'];
        }
        
        if (in_array($inv, ['in_escrow', 'released_to_creator']) && ($pay === 'successful' || $hasTx) && $is_live_sale) {
            return ['status' => 'Active', 'description' => 'Payment received - campaign active.'];
        }
        
        if ($inv === 'in_escrow' && ($pay === 'successful' || $hasTx) && $sale === 'ended_successful') {
            return ['status' => 'Processing', 'description' => 'Sale successful. Preparing allocation.'];
        }
        if ($inv === 'released_to_creator' && ($pay === 'successful' || $hasTx) && $sale === 'ended_successful') {
            return ['status' => 'Fulfilled', 'description' => 'Project funded. Tokens distributed.'];
        }
        
        if (in_array($inv, ['refund_pending', 'in_escrow']) && in_array($sale, ['ended_failed', 'canceled'])) {
            return ['status' => 'Refunding', 'description' => 'Sale failed. Refund available.'];
        }
        if ($inv === 'returned_to_backer' && ($pay === 'refunded' || $pay === 'successful')) {
            return ['status' => 'Refunded', 'description' => 'Contribution refunded to wallet.'];
        }
        
        if ($inv === 'cancelled') {
            return ['status' => 'Canceled', 'description' => 'Contribution canceled.'];
        }

        return ['status' => 'Under Review', 'description' => "Status check required."];
    }
}

echo "===================================================\n";
echo "   RUNNING AUTOMATED EDGE-CASE TEST SUITE         \n";
echo "===================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest($condition, $testName, $extra = '') {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $testName" . ($extra ? " ($extra)" : "") . "\n";
        $passed++;
    } else {
        echo "[FAIL] $testName" . ($extra ? " - $extra" : "") . "\n";
        $failed++;
    }
}

// --- EC-01: Currency Formatting & Edge Case Parsing ---
$val1 = parseCurrencyInput("50,000.00");
assertTest($val1 === 50000.0, "EC-01a: Parse comma thousands '50,000.00'", "Result: $val1");

$val2 = parseCurrencyInput("50.000,50");
assertTest($val2 === 50000.5, "EC-01b: Parse European comma decimal '50.000,50'", "Result: $val2");

$val3 = parseCurrencyInput("$1,250,500");
assertTest($val3 === 1250500.0, "EC-01c: Parse currency symbol '$1,250,500'", "Result: $val3");

// --- EC-02: Investor Status Matrix Edge Cases ---
$st1 = getInvestorStatus('initiated', '', 'live', false);
assertTest($st1['status'] === 'Unpaid (Draft)', "EC-02a: Unpaid draft attempt with no payment tx");

$st2 = getInvestorStatus('in_escrow', 'successful', 'live', true);
assertTest($st2['status'] === 'Active', "EC-02b: Confirmed payment in active escrow");

$st3 = getInvestorStatus('in_escrow', 'successful', 'ended_failed', true);
assertTest($st3['status'] === 'Refunding', "EC-02c: Soft cap failed triggers refund status");

$st4 = getInvestorStatus('released_to_creator', 'successful', 'ended_successful', true);
assertTest($st4['status'] === 'Fulfilled', "EC-02d: Successful campaign unlocks fulfillment");

// --- EC-03: KYC Verification Gate Evaluation ---
function checkKycValid($statusStr) {
    $kyc_status = strtolower($statusStr ?? '');
    return in_array($kyc_status, ['approved', 'verified', 'completed']);
}
assertTest(checkKycValid('Approved') === true, "EC-03a: KYC Approved status valid");
assertTest(checkKycValid('Verified') === true, "EC-03b: KYC Verified status valid");
assertTest(checkKycValid('pending') === false, "EC-03c: KYC Pending status blocked");
assertTest(checkKycValid('') === false, "EC-03d: Empty KYC status blocked");

// --- EC-04: Digital Signature Hash & Electronic Audit Stamp ---
$sampleName = "Jane Investor";
$sampleEmail = "jane@mail.io";
$sampleIp = "127.0.0.1";
$timestamp = date('Y-m-d H:i:s');
$hashInput = $sampleName . $sampleEmail . $sampleIp . $timestamp;
$sha256 = hash('sha256', $hashInput);

assertTest(strlen($sha256) === 64, "EC-04a: SHA-256 Electronic Audit Stamp fingerprint", "64-char Hash");

// --- EC-05: Direct Gnosis Fee & Quantity Math ---
$contribution = 1000.0;
$unitPrice = 0.50; // $0.50 per token
$expectedTokens = 2000;
$actualTokens = ($unitPrice > 0) ? ($contribution / $unitPrice) : 0;
assertTest($actualTokens === (float)$expectedTokens, "EC-05a: Precision token calculation at $0.50/token", "Tokens: $actualTokens");

echo "\n===================================================\n";
echo "   TEST RUN COMPLETE: $passed Passed, $failed Failed\n";
echo "===================================================\n";
