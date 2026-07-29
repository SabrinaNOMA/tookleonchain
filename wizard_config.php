<?php
// wizard_config.php

/**
 * Returns the definitive configuration for the entire project setup wizard.
 * This acts as the "single source of truth" for navigation, titles, and URLs.
 *
 * @return array The wizard configuration array.
 */
function get_wizard_config() {
    return [
        'describe' => [
            'mainStep' => 'Project Overview', // Renamed from 'Describe Project'
            'icon' => 'file-text',
            'subSteps' => [
                'setup' => ['title' => 'Project Details', 'url' => '/setup'],
            ]
        ],
        'tokenomics' => [
            'mainStep' => 'Funding Plan', // Renamed from 'Design Tokenomics'
            'icon' => 'drafting-compass',
            'subSteps' => [
                'token_name' => ['title' => 'Token Name', 'url' => '/tokenname'],
                'token_supply' => ['title' => 'Token Supply', 'url' => '/tokensupply'],
                'fundraising' => ['title' => 'Fundraising', 'url' => '/fundraising'],
                'vesting' => ['title' => 'Vesting', 'url' => '/vesting'],
                'validate' => ['title' => 'Validate', 'url' => '/validate'],

            ]
        ],
        'private_sale' => [
            'mainStep' => 'Private Sale Room', // Renamed from 'Private Sale'
            'icon' => 'rocket',
            'subSteps' => [
                'story' => ['title' => 'Story', 'url' => '/story'],
                'parameter' => ['title' => 'Parameter', 'url' => '/parameter'],
                'compliance' => ['title' => 'Compliance', 'url' => '/compliance'],
                'approve' => ['title' => 'Validate', 'url' => '/approve'],

            ]
        ]
        // Removed 'escrow_kyc' step to match the 3-step dashboard layout
    ];
}