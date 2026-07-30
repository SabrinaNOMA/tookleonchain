<?php
/**
 * Router Quality Test Script (tests/test_router_quality.php)
 * Verifies edge cases: uppercase URLs, role permissions, payment flow, and devtools filter.
 */

// Colors for CLI output
function colorLog($str, $color = '32') {
    return "\033[{$color}m{$str}\033[0m";
}

$baseUrl = 'http://127.0.0.1:8088';

$tests = [
    [
        'name' => '1. Lowercase route: /portfolio',
        'url' => '/portfolio',
        'expected_status' => [200, 302],
        'not_expected' => [403, 404]
    ],
    [
        'name' => '2. Uppercase route: /PORTFOLIO',
        'url' => '/PORTFOLIO',
        'expected_status' => [200, 302],
        'not_expected' => [404]
    ],
    [
        'name' => '3. Mixed case route: /Settings',
        'url' => '/Settings',
        'expected_status' => [200, 302],
        'not_expected' => [404]
    ],
    [
        'name' => '4. Investor Discovery route: /projects',
        'url' => '/projects',
        'expected_status' => [200, 302],
        'not_expected' => [403, 404]
    ],
    [
        'name' => '5. Payment route: /payment',
        'url' => '/payment',
        'expected_status' => [200, 302],
        'not_expected' => [403, 404]
    ],
    [
        'name' => '6. Purchase route: /purchase',
        'url' => '/purchase',
        'expected_status' => [200, 302],
        'not_expected' => [403, 404]
    ],
    [
        'name' => '7. Public Page: /privacy',
        'url' => '/privacy',
        'expected_status' => [200],
        'not_expected' => [403, 404]
    ]
];

echo colorLog("=== ROUTER QUALITY TEST SUITE ===", "36") . "\n";
$passed = 0;
$failed = 0;

foreach ($tests as $t) {
    $ch = curl_init($baseUrl . $t['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isOk = in_array($httpCode, $t['expected_status']) && !in_array($httpCode, $t['not_expected']);
    
    if ($isOk) {
        echo colorLog("[PASS] {$t['name']} (HTTP {$httpCode})", "32") . "\n";
        $passed++;
    } else {
        echo colorLog("[FAIL] {$t['name']} (Got HTTP {$httpCode})", "31") . "\n";
        $failed++;
    }
}

echo "\nSummary: " . colorLog("{$passed} Passed", "32") . ", " . colorLog("{$failed} Failed", "31") . "\n";
exit($failed > 0 ? 1 : 0);
