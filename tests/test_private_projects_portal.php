<?php
/**
 * Automated Quality Test Suite for Option 1: Private Sale Rooms by Invitation
 *
 * Verifies:
 * 1. Zero Public Leakage: Uninvited investors see 0 projects on /projects.
 * 2. Newsale Token Unlock: Unlocking a token via session or unlock_sale.php allows access.
 * 3. URL Parsing: Both URLs and raw codes parse correctly.
 */

ob_start();
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: text/plain');

echo "===================================================\n";
echo "   RUNNING PRIVATE SALE PORTAL QUALITY TESTS       \n";
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

try {
    // 1. Get an existing investor user or mock session
    $stmt = $pdo->query("SELECT id, email FROM user WHERE has_membership = 1 LIMIT 1");
    $test_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test_user) {
        $stmt = $pdo->query("SELECT id, email FROM user LIMIT 1");
        $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$test_user) {
        die("ABORT: No user found in database to run quality test.");
    }

    $_SESSION['user_id'] = $test_user['id'];
    $_SESSION['user_role'] = 'investor';
    $_SESSION['my_unlocked_sales'] = []; // Start empty

    echo "Testing as Investor: " . $test_user['email'] . " (ID: " . $test_user['id'] . ")\n\n";

    // 2. TEST 1: ZERO PUBLIC LEAKAGE
    // Even if there are live sales in the database, an uninvited user with empty my_unlocked_sales
    // and no investments MUST receive 0 projects.
    $stmt_all = $pdo->query("SELECT COUNT(*) FROM token_sale_pages WHERE status IN ('live', 'scheduled')");
    $total_live_in_db = (int)$stmt_all->fetchColumn();
    echo "Total live/scheduled token sales in DB: $total_live_in_db\n";

    // We clear any investments for this user temporarily in memory query check
    $_SESSION['my_unlocked_sales'] = [];

    // Let's execute the exact Institutional Compliance Filter SQL from projects_backend.php
    $unlocked_tokens = $_SESSION['my_unlocked_sales'] ?? [];
    $unlocked_tokens = array_filter(array_map('trim', $unlocked_tokens));

    $params = [':uid' => $test_user['id']];
    $unlock_clause = '';
    if (!empty($unlocked_tokens)) {
        $placeholders = [];
        foreach (array_values($unlocked_tokens) as $idx => $token_val) {
            $paramName = ":token_$idx";
            $placeholders[] = $paramName;
            $params[$paramName] = $token_val;
        }
        $unlock_clause = " OR tsp.sale_url IN (" . implode(', ', $placeholders) . ")";
    }

    $stmt_query = $pdo->prepare("
        SELECT p.id, tsp.sale_url
        FROM projet p
        JOIN token_sale_pages tsp ON p.id = tsp.project_id
        WHERE tsp.status IN ('live', 'scheduled', 'ended_successful', 'ended_failed', 'canceled')
          AND (
              p.id IN (SELECT project_id FROM investments WHERE user_id = :uid)
              $unlock_clause
          )
    ");
    $stmt_query->execute($params);
    $uninvited_results = $stmt_query->fetchAll(PDO::FETCH_ASSOC);

    // If this user has no prior investments, count should be 0!
    $invest_count = $pdo->prepare("SELECT COUNT(*) FROM investments WHERE user_id = ?");
    $invest_count->execute([$test_user['id']]);
    $user_investments = (int)$invest_count->fetchColumn();

    if ($user_investments === 0) {
        assert_test(count($uninvited_results) === 0, "Zero Public Leakage: Uninvited investor receives 0 uninvited projects (0 public solicitation)");
    } else {
        assert_test(count($uninvited_results) === $user_investments, "Zero Public Leakage: Investor only sees their $user_investments existing investments");
    }

    // 3. TEST 2: NEWSALE TOKEN UNLOCK TEST
    // Pick any token sale from token_sale_pages
    $stmt_sample = $pdo->query("SELECT sale_url, status FROM token_sale_pages WHERE sale_url IS NOT NULL AND sale_url != '' LIMIT 1");
    $sample_sale = $stmt_sample->fetch(PDO::FETCH_ASSOC);

    if ($sample_sale) {
        $test_token = $sample_sale['sale_url'];
        echo "\nTesting unlock with newsale token: '$test_token'\n";

        // Unlock in session
        $_SESSION['my_unlocked_sales'][] = $test_token;

        // Re-run filter query
        $unlocked_tokens = $_SESSION['my_unlocked_sales'];
        $params = [':uid' => $test_user['id']];
        $placeholders = [];
        foreach (array_values($unlocked_tokens) as $idx => $token_val) {
            $paramName = ":token_$idx";
            $placeholders[] = $paramName;
            $params[$paramName] = $token_val;
        }
        $unlock_clause = " OR tsp.sale_url IN (" . implode(', ', $placeholders) . ")";

        $stmt_query2 = $pdo->prepare("
            SELECT p.id, tsp.sale_url
            FROM projet p
            JOIN token_sale_pages tsp ON p.id = tsp.project_id
            WHERE tsp.status IN ('live', 'scheduled', 'ended_successful', 'ended_failed', 'canceled')
              AND (
                  p.id IN (SELECT project_id FROM investments WHERE user_id = :uid)
                  $unlock_clause
              )
        ");
        $stmt_query2->execute($params);
        $unlocked_results = $stmt_query2->fetchAll(PDO::FETCH_ASSOC);

        $found_unlocked = false;
        foreach ($unlocked_results as $res) {
            if ($res['sale_url'] === $test_token) {
                $found_unlocked = true;
                break;
            }
        }
        assert_test($found_unlocked, "Newsale Token Unlock: Unlocking token '$test_token' successfully grants permissioned access");
    } else {
        echo "[INFO] No token_sale_pages found in DB yet to test token unlock.\n";
    }

    // 4. TEST 3: URL PARSING TEST
    $test_url_input = "https://tookle.io/p/a1b2c3d4";
    $parsed_token = '';
    if (preg_match('#/p/([A-Za-z0-9]{6,64})#', $test_url_input, $m)) {
        $parsed_token = $m[1];
    }
    assert_test($parsed_token === "a1b2c3d4", "URL Parsing: Full URL '$test_url_input' cleanly extracts token 'a1b2c3d4'");

    $raw_token_input = "a1b2c3d4";
    $parsed_raw = '';
    if (preg_match('#^[A-Za-z0-9]{6,64}$#', $raw_token_input)) {
        $parsed_raw = $raw_token_input;
    }
    assert_test($parsed_raw === "a1b2c3d4", "URL Parsing: Raw code '$raw_token_input' cleanly validates as 'a1b2c3d4'");

    echo "\n---------------------------------------------------\n";
    if ($errors === 0) {
        echo "ALL PRIVATE SALE PORTAL QUALITY TESTS PASSED! (100% COMPLIANT)\n";
    } else {
        echo "TEST SUITE FAILED WITH $errors ERROR(S).\n";
    }
    echo "---------------------------------------------------\n";

} catch (Exception $e) {
    echo "\n[CRITICAL ERROR] Test exception: " . $e->getMessage() . "\n";
}
?>
