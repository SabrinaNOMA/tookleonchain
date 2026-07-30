<?php
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../wizard_config.php';

echo "===================================================\n";
echo "   RUNNING WIZARD NAVIGATION & FLOW TESTS          \n";
echo "===================================================\n\n";

$config = get_wizard_config();

// 1. Check wizard_config steps
$tokenomics_substeps = array_keys($config['tokenomics']['subSteps'] ?? []);
assert(in_array('vesting', $tokenomics_substeps), "Vesting step missing in tokenomics");
echo "[PASS] Wizard Config: 'vesting' step present under Tokenomics / Funding Plan\n";

$private_sale_substeps = array_keys($config['private_sale']['subSteps'] ?? []);
assert(in_array('compliance', $private_sale_substeps), "Compliance step missing in private_sale");
echo "[PASS] Wizard Config: 'compliance' step present under Private Sale Room\n";

// 2. Check backend redirects
$fundraising_backend_content = file_get_contents(__DIR__ . '/../backend/fundraising_backend.php');
assert(strpos($fundraising_backend_content, "'/vesting'") !== false, "Fundraising backend doesn't redirect to /vesting");
echo "[PASS] Fundraising Backend: Redirects to /vesting\n";

$vesting_content = file_get_contents(__DIR__ . '/../pages/vesting.php');
assert(strpos($vesting_content, "get_url('story')") !== false, "Vesting page doesn't redirect to story");
echo "[PASS] Vesting Page: Redirects to /story\n";

$story_backend_content = file_get_contents(__DIR__ . '/../backend/story_backend.php');
assert(strpos($story_backend_content, '/founder/compliance') !== false, "Story backend doesn't redirect to /founder/compliance");
echo "[PASS] Story Backend: Redirects to /founder/compliance\n";

$compliance_backend_content = file_get_contents(__DIR__ . '/../backend/compliance_backend.php');
assert(strpos($compliance_backend_content, "'/approve'") !== false, "Compliance backend doesn't redirect to /approve");
echo "[PASS] Compliance Backend: Redirects to /approve\n";

echo "\n===================================================\n";
echo "   ALL WIZARD NAVIGATION TESTS PASSED!             \n";
echo "===================================================\n";
