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
            'mainStep' => 'Project Overview', // Part 1: Project Overview
            'icon' => 'file-text',
            'subSteps' => [
                'setup' => ['title' => 'Project Details', 'url' => '/setup'],
            ]
        ],
        'tokenomics' => [
            'mainStep' => 'Funding Plan', // Part 2: Funding Plan
            'icon' => 'drafting-compass',
            'subSteps' => [
                'token_name' => ['title' => 'Token & Supply', 'url' => '/tokenname'],
                'token_supply' => ['title' => 'Supply & Inflation', 'url' => '/tokensupply'],
                'fundraising' => ['title' => 'Fundraising Plan', 'url' => '/fundraising'],
            ]
        ],
        'private_sale' => [
            'mainStep' => 'Private Sale Room', // Part 3: Private Sale Room
            'icon' => 'rocket',
            'subSteps' => [
                'story' => ['title' => 'Project Story', 'url' => '/story'],
                'approve' => ['title' => 'Review & Launch', 'url' => '/approve'],
            ]
        ]
    ];
}