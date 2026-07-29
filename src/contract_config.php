<?php
/**
 * src/contract_config.php
 * Single Source of Truth for Blockchain Constants.
 */

return [
    // NEW Factory Address
    'FACTORY_ADDRESS' => strtolower('0x4f434FF04F9300B821c98599318044aB22A1e869'), 
    
    // Event Topic 0: Keccak256("CampaignCreated(address,address,address,address,uint256,uint256,uint256,uint256)")
    'TOPIC_CAMPAIGN_CREATED' => '0x975c74eb738f9a9972d42d3b66df261be49191e98827725dc39f8d95b9557a62',
    
    // Updated ABI - Using NOWDOC (<<<'JSON') to prevent syntax errors
    'FACTORY_ABI_JSON' => <<<'JSON'
[
    {"inputs":[{"internalType":"address","name":"_usdc","type":"address"},{"internalType":"address","name":"_usdt","type":"address"}],"stateMutability":"nonpayable","type":"constructor"},
    {"inputs":[],"name":"DurationTooLong","type":"error"},
    {"inputs":[],"name":"InvalidAddress","type":"error"},
    {"inputs":[],"name":"UnsupportedToken","type":"error"},
    {"inputs":[],"name":"ZeroDuration","type":"error"},
    {"inputs":[],"name":"ZeroGoal","type":"error"},
    {"inputs":[],"name":"ZeroMaxContribution","type":"error"},
    {"anonymous":false,"inputs":[{"indexed":true,"internalType":"address","name":"campaignAddress","type":"address"},{"indexed":true,"internalType":"address","name":"projectWallet","type":"address"},{"indexed":false,"internalType":"address","name":"paymentToken","type":"address"},{"indexed":true,"internalType":"address","name":"owner","type":"address"},{"indexed":false,"internalType":"uint256","name":"goal","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"startTimestamp","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"deadline","type":"uint256"},{"indexed":false,"internalType":"uint256","name":"saleId","type":"uint256"}],"name":"CampaignCreated","type":"event"},
    {"inputs":[{"internalType":"address","name":"paymentToken","type":"address"},{"internalType":"address","name":"projectWallet","type":"address"},{"internalType":"uint256","name":"goal","type":"uint256"},{"internalType":"uint256","name":"startTimestamp","type":"uint256"},{"internalType":"uint256","name":"durationInSeconds","type":"uint256"},{"internalType":"uint256","name":"maxContributionPerWallet","type":"uint256"},{"internalType":"uint256","name":"saleId","type":"uint256"},{"internalType":"address","name":"onBehalfOf","type":"address"}],"name":"createDeterministicCampaign","outputs":[{"internalType":"address","name":"campaignAddress","type":"address"}],"stateMutability":"nonpayable","type":"function"},
    {"inputs":[{"internalType":"address","name":"paymentToken","type":"address"},{"internalType":"address","name":"projectWallet","type":"address"},{"internalType":"uint256","name":"goal","type":"uint256"},{"internalType":"uint256","name":"startTimestamp","type":"uint256"},{"internalType":"uint256","name":"durationInSeconds","type":"uint256"},{"internalType":"uint256","name":"maxContributionPerWallet","type":"uint256"},{"internalType":"uint256","name":"saleId","type":"uint256"},{"internalType":"address","name":"deployer","type":"address"}],"name":"predictCampaignAddress","outputs":[{"internalType":"address","name":"campaignAddress","type":"address"}],"stateMutability":"view","type":"function"},
    {"inputs":[],"name":"getAllowedTokens","outputs":[{"internalType":"address[]","name":"","type":"address[]"}],"stateMutability":"view","type":"function"},
    {"inputs":[],"name":"usdc","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"},
    {"inputs":[],"name":"usdt","outputs":[{"internalType":"address","name":"","type":"address"}],"stateMutability":"view","type":"function"}
]
JSON
    ,

    'RPC_ENDPOINTS' => [
        "https://mainnet.base.org",
        "https://base.publicnode.com",
        "https://1rpc.io/base"
    ]
];
?>