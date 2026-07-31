-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : bs2617458-001.eu.clouddb.ovh.net:35630
-- Généré le : ven. 31 juil. 2026 à 10:08
-- Version du serveur : 8.0.46-37
-- Version de PHP : 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `tooklepreprod`
--

-- --------------------------------------------------------

--
-- Structure de la table `agreement_versions`
--

CREATE TABLE `agreement_versions` (
  `id` int NOT NULL,
  `projet_id` char(36) NOT NULL,
  `version` int NOT NULL DEFAULT '1' COMMENT 'The version number (1, 2, 3...)',
  `content` text COMMENT 'The JSON content from the modal builder',
  `file_url` varchar(255) DEFAULT NULL COMMENT 'The URL if a file was uploaded instead',
  `is_active` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is this the currently active version for new investors?',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `compliance_settings`
--

CREATE TABLE `compliance_settings` (
  `projet_id` char(36) NOT NULL,
  `kyc_required` tinyint(1) DEFAULT '0',
  `exclude_sanctioned` tinyint(1) DEFAULT '0',
  `exclude_us_non_accredited` tinyint(1) DEFAULT '0',
  `require_eu_consent` tinyint(1) DEFAULT '0',
  `custom_country_disclaimer` text,
  `legal_opinion_url` varchar(255) DEFAULT NULL,
  `terms_of_service_url` varchar(255) DEFAULT NULL,
  `other_doc_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `deployed_escrows`
--

CREATE TABLE `deployed_escrows` (
  `id` int NOT NULL,
  `project_id` char(36) NOT NULL,
  `contract_address` varchar(100) NOT NULL,
  `payment_token` varchar(100) NOT NULL,
  `founder_wallet` varchar(100) NOT NULL,
  `deployment_tx` varchar(150) NOT NULL,
  `duration` int DEFAULT NULL,
  `deployed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `claim_tx` varchar(150) DEFAULT NULL,
  `claimed_amount` decimal(20,2) DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `deployed_token`
--

CREATE TABLE `deployed_token` (
  `id` int NOT NULL,
  `contract` varchar(255) NOT NULL,
  `deployment_date` date NOT NULL,
  `network` varchar(100) NOT NULL,
  `wallet` varchar(255) NOT NULL,
  `user_id` int NOT NULL,
  `projet_id` char(36) NOT NULL,
  `scenario_version_id` int DEFAULT NULL,
  `snapshot_data` json DEFAULT NULL,
  `selected_contract` enum('yes','no') DEFAULT 'no'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `investments`
--

CREATE TABLE `investments` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `project_id` char(36) NOT NULL,
  `amount_usd` decimal(12,2) NOT NULL,
  `token_quantity` decimal(20,8) DEFAULT NULL,
  `token_price_at_purchase` decimal(12,6) DEFAULT NULL,
  `cliff_months` int DEFAULT NULL,
  `vesting_months` int DEFAULT NULL,
  `percent_unlock_at_tge` decimal(30,6) DEFAULT NULL,
  `investment_round` varchar(100) DEFAULT NULL,
  `sale_name` varchar(100) DEFAULT NULL,
  `status` enum('initiated','in_escrow','released_to_creator','refund_pending','returned_to_backer','canceled') NOT NULL,
  `payment_tx_hash` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `referral_code_used` varchar(32) DEFAULT NULL,
  `campaign_id` varchar(32) DEFAULT NULL,
  `inviter_commission` decimal(10,2) DEFAULT '0.00',
  `invitee_commission` decimal(10,2) DEFAULT '0.00',
  `commission_status` varchar(20) NOT NULL DEFAULT 'not_applicable',
  `distribution_status` enum('Active','Failed','Revoked') DEFAULT NULL,
  `distributed_at` datetime DEFAULT NULL,
  `distribution_tx_hash` varchar(150) DEFAULT NULL,
  `refund_tx_hash` varchar(150) DEFAULT NULL,
  `distribution_stream_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(32) NOT NULL DEFAULT (replace(uuid(),_utf8mb3'-',_utf8mb3'')),
  `agreement_approved` tinyint(1) DEFAULT '0',
  `agreement_approved_at` datetime DEFAULT NULL,
  `agreement_version_id` int DEFAULT NULL,
  `signed_agreement_snapshot` text,
  `investor_wallet_address` varchar(100) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `invite_settings`
--

CREATE TABLE `invite_settings` (
  `id` int NOT NULL,
  `projet_id` char(36) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `inviter_reward_percent` decimal(5,2) NOT NULL DEFAULT '2.50',
  `invitee_bonus_percent` decimal(5,2) NOT NULL DEFAULT '2.50',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `campaign_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `kyc_aml`
--

CREATE TABLE `kyc_aml` (
  `id` bigint UNSIGNED NOT NULL,
  `applicant_id` varchar(64) NOT NULL,
  `sanctions_result` varchar(16) DEFAULT NULL,
  `pep_result` varchar(16) DEFAULT NULL,
  `adverse_result` varchar(16) DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `raw_verifications` json DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `kyc_applicants`
--

CREATE TABLE `kyc_applicants` (
  `applicant_id` varchar(64) NOT NULL,
  `external_user_id` varchar(128) NOT NULL,
  `review_status` varchar(32) DEFAULT NULL,
  `review_answer` varchar(16) DEFAULT NULL,
  `reject_labels` json DEFAULT NULL,
  `moderation_comment` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `raw_status` json DEFAULT NULL,
  `raw_applicant` json DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `kyc_applicant_events`
--

CREATE TABLE `kyc_applicant_events` (
  `id` bigint UNSIGNED NOT NULL,
  `applicant_id` varchar(64) NOT NULL,
  `event_type` varchar(32) NOT NULL,
  `payload` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `kyc_uploads`
--

CREATE TABLE `kyc_uploads` (
  `id` bigint UNSIGNED NOT NULL,
  `applicant_id` varchar(64) NOT NULL,
  `flow` enum('ID','POA','SELFIE') NOT NULL,
  `id_doc_type` varchar(64) DEFAULT NULL,
  `page` enum('FRONT','BACK') DEFAULT NULL,
  `country` varchar(3) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `filepath` varchar(512) DEFAULT NULL,
  `status` enum('PENDING','SUCCESS','ERROR') NOT NULL DEFAULT 'PENDING',
  `http_code` int DEFAULT NULL,
  `sumsub_code` varchar(64) DEFAULT NULL,
  `correlation_id` varchar(128) DEFAULT NULL,
  `message` text,
  `last_response_json` json DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `onramp_token_requests`
--

CREATE TABLE `onramp_token_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `origin` varchar(255) DEFAULT NULL,
  `wallet_address` varchar(64) DEFAULT NULL,
  `blockchain` varchar(32) DEFAULT NULL,
  `fiat_currency` varchar(8) DEFAULT NULL,
  `crypto_currency` varchar(8) DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `coinbase_http_status` int DEFAULT NULL,
  `coinbase_error` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `investment_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `method` varchar(32) NOT NULL,
  `status` enum('pending','successful','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reference_id` varchar(64) NOT NULL DEFAULT (replace(uuid(),_utf8mb3'-',_utf8mb3'')),
  `transaction_hash` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `preset_round`
--

CREATE TABLE `preset_round` (
  `id` int NOT NULL,
  `tranche_type` enum('investor') NOT NULL,
  `tranche_name` varchar(255) NOT NULL,
  `recommended_round_name` varchar(255) NOT NULL,
  `recommended_percent_total_raise` decimal(5,2) DEFAULT NULL,
  `recommended_percent_discount` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `preset_round`
--

INSERT INTO `preset_round` (`id`, `tranche_type`, `tranche_name`, `recommended_round_name`, `recommended_percent_total_raise`, `recommended_percent_discount`) VALUES
(1, 'investor', 'investor', 'Pre-Seed', 20.00, 70.00),
(2, 'investor', 'investor', 'Seed', 30.00, 50.00),
(3, 'investor', 'investor', 'Serie A', 40.00, 30.00),
(4, 'investor', 'investor', 'Public', 10.00, 0.00);

-- --------------------------------------------------------

--
-- Structure de la table `preset_supply`
--

CREATE TABLE `preset_supply` (
  `id` int NOT NULL,
  `category` varchar(255) NOT NULL,
  `recommended_type_supply` enum('Capped','Inflationary') NOT NULL,
  `recommended_supply_value` bigint NOT NULL,
  `recommended_inflation_type` varchar(255) DEFAULT NULL,
  `explanation` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `preset_supply`
--

INSERT INTO `preset_supply` (`id`, `category`, `recommended_type_supply`, `recommended_supply_value`, `recommended_inflation_type`, `explanation`) VALUES
(1, 'AI Agents', 'Capped', 2000000000, NULL, 'AI projects typically employ capped supplies, often between 1.4B and 2.7B, making 2B a representative figure for these utility-focused tokens integral to platform function.'),
(2, 'Centralized Exchanges', 'Capped', 200000000, NULL, 'CEX tokens are characteristically capped at low supplies (100M-200M range) and are often deflationary via burns linked to exchange success, making 200M a typical *initial* cap before burns.'),
(3, 'Decentralized Exchanges', 'Inflationary', 1000000000, 'Yearly Rate', 'DEX token models vary, with some being inflationary or becoming inflationary after an initial cap; this recommendation reflects Uniswap\'s model (1B initial, 2% yearly inflation) as a prominent example, but other structures exist.'),
(4, 'DePIN', 'Capped', 1000000000, NULL, 'DePIN projects often use capped supplies to incentivize resource provision a 1B cap is a reasonable estimate, although observed caps vary significantly (approx. 200M-2B) depending on the specific infrastructure and reward granularity.'),
(5, 'Fan Tokens', 'Capped', 10000000, NULL, 'Individual fan tokens are consistently capped at low, finite supplies (typically millions) tailored to specific teams for engagement, making 10M a representative figure for these specific assets, distinct from any platform tokens.'),
(6, 'Gaming', 'Capped', 3000000000, NULL, 'Gaming often uses capped primary tokens (with caps varying greatly from ~270M to 3B alongside potentially inflationary reward tokens in dual systems ; a 3B cap reflects larger metaverse-style projects but isn\'t a universal standard.'),
(7, 'Layer 1', 'Inflationary', 1000000000, 'Custom', 'While L1s show diverse models including uncapped inflation and varying caps, an inflationary supply with an initial supply of 1 Billion is a starting point, although other options like a capped supply (e.g., 10B) exist and actual configurations range widely based on design (e.g., security needs, consensus mechanism).'),
(8, 'Layer 2', 'Capped', 10000000000, NULL, 'L2s often use capped supplies for governance frequently around 10B, but some incorporate planned inflation or have lower caps so this figure represents a common but not universal approach.'),
(9, 'Marketplaces', 'Capped', 1000000000, NULL, 'Marketplaces generally use capped tokens, but the caps vary dramatically (25M to 3B) based on incentive design; 1B serves as a mid-range estimate within this wide spectrum.'),
(10, 'Meme Tokens', 'Capped', 1000000000, NULL, 'Meme token tokenomics are extremely varied and often defy standard logic, ranging from uncapped inflation to massive caps while some newer tokens might target 1B, this recommendation reflects a specific trend and doesn\'t capture the category\'s overall unpredictability.'),
(11, 'Payment', 'Capped', 50000000000, NULL, 'Payment tokens typically feature very high capped supplies (50B-100B) to facilitate large transaction volumes, often with pre-mined distribution making 50B a representative but lower-end figure for this category.'),
(12, 'Staking/Yield Farming', 'Capped', 15000000, NULL, 'Major DeFi governance tokens feature very low capped supplies (e.g., 10M-16M) to concentrate governance power, making 15M a representative figure for this specific niche.'),
(13, 'Startup Utility Tokens', 'Capped', 1000000000, NULL, 'Utility tokens are typically capped to ensure predictable access, but the cap size is highly variable (e.g., 100M-1B) depending on the specific service and platform design; 1B reflects a common example but isn\'t a universal rule.');

-- --------------------------------------------------------

--
-- Structure de la table `preset_tranche`
--

CREATE TABLE `preset_tranche` (
  `id` int NOT NULL,
  `category_id` varchar(255) NOT NULL,
  `tranche_name` varchar(255) NOT NULL,
  `allocation_percent` decimal(5,2) NOT NULL,
  `tranche_type` enum('investor','other') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `preset_tranche`
--

INSERT INTO `preset_tranche` (`id`, `category_id`, `tranche_name`, `allocation_percent`, `tranche_type`) VALUES
(1, 'AI Agents', 'Ecosystem', 55.00, 'other'),
(2, 'AI Agents', 'Miscellaneous', 5.00, 'other'),
(3, 'AI Agents', 'Team', 12.50, 'other'),
(4, 'AI Agents', 'Treasury', 12.50, 'other'),
(5, 'Centralized Exchanges', 'Ecosystem', 55.00, 'other'),
(6, 'Centralized Exchanges', 'Miscellaneous', 5.00, 'other'),
(7, 'Centralized Exchanges', 'Team', 12.50, 'other'),
(8, 'Centralized Exchanges', 'Treasury', 12.50, 'other'),
(9, 'Decentralized Exchanges', 'Ecosystem', 50.00, 'other'),
(10, 'Decentralized Exchanges', 'Miscellaneous', 5.00, 'other'),
(11, 'Decentralized Exchanges', 'Team', 12.50, 'other'),
(12, 'Decentralized Exchanges', 'Treasury', 12.50, 'other'),
(13, 'DePIN', 'Ecosystem', 45.00, 'other'),
(14, 'DePIN', 'Miscellaneous', 7.50, 'other'),
(15, 'DePIN', 'Team', 15.00, 'other'),
(16, 'DePIN', 'Treasury', 12.50, 'other'),
(17, 'Fan Tokens', 'Ecosystem', 55.00, 'other'),
(18, 'Fan Tokens', 'Miscellaneous', 5.00, 'other'),
(19, 'Fan Tokens', 'Team', 12.50, 'other'),
(20, 'Fan Tokens', 'Treasury', 12.50, 'other'),
(21, 'Gaming', 'Ecosystem', 47.50, 'other'),
(22, 'Gaming', 'Miscellaneous', 7.50, 'other'),
(23, 'Gaming', 'Team', 15.00, 'other'),
(24, 'Gaming', 'Treasury', 10.00, 'other'),
(25, 'Layer 1', 'Ecosystem', 35.00, 'other'),
(26, 'Layer 1', 'Miscellaneous', 7.50, 'other'),
(27, 'Layer 1', 'Team', 20.00, 'other'),
(28, 'Layer 1', 'Treasury', 12.50, 'other'),
(29, 'Layer 2', 'Ecosystem', 35.00, 'other'),
(30, 'Layer 2', 'Miscellaneous', 5.00, 'other'),
(31, 'Layer 2', 'Team', 15.00, 'other'),
(32, 'Layer 2', 'Treasury', 12.50, 'other'),
(33, 'Marketplaces', 'Ecosystem', 50.00, 'other'),
(34, 'Marketplaces', 'Miscellaneous', 5.00, 'other'),
(35, 'Marketplaces', 'Team', 12.50, 'other'),
(36, 'Marketplaces', 'Treasury', 12.50, 'other'),
(37, 'Meme Tokens', 'Ecosystem', 80.00, 'other'),
(38, 'Meme Tokens', 'Miscellaneous', 10.00, 'other'),
(39, 'Meme Tokens', 'Team', 0.00, 'other'),
(40, 'Meme Tokens', 'Treasury', 10.00, 'other'),
(41, 'Payment', 'Ecosystem', 40.00, 'other'),
(42, 'Payment', 'Miscellaneous', 7.50, 'other'),
(43, 'Payment', 'Team', 15.00, 'other'),
(44, 'Payment', 'Treasury', 15.00, 'other'),
(45, 'Staking/Yield Farming', 'Ecosystem', 45.00, 'other'),
(46, 'Staking/Yield Farming', 'Miscellaneous', 10.00, 'other'),
(47, 'Staking/Yield Farming', 'Team', 15.00, 'other'),
(48, 'Staking/Yield Farming', 'Treasury', 15.00, 'other'),
(49, 'Startup Utility Tokens', 'Ecosystem', 40.00, 'other'),
(50, 'Startup Utility Tokens', 'Miscellaneous', 10.00, 'other'),
(51, 'Startup Utility Tokens', 'Team', 15.00, 'other'),
(52, 'Startup Utility Tokens', 'Treasury', 15.00, 'other');

-- --------------------------------------------------------

--
-- Structure de la table `project_wallet`
--

CREATE TABLE `project_wallet` (
  `id` int NOT NULL,
  `projet_id` char(36) NOT NULL,
  `network` varchar(50) DEFAULT NULL,
  `wallet_address` varchar(255) DEFAULT NULL,
  `label` varchar(100) DEFAULT NULL,
  `note` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `projet`
--

CREATE TABLE `projet` (
  `id` char(36) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_website` varchar(1000) NOT NULL DEFAULT 'https://example.com',
  `token_name` varchar(255) DEFAULT NULL,
  `token_ticker` varchar(255) DEFAULT NULL,
  `token_logo_path` varchar(255) DEFAULT NULL,
  `recommended_category` varchar(255) NOT NULL DEFAULT 'Autre',
  `selected_category` varchar(255) DEFAULT NULL,
  `type_supply` enum('capped','inflationary') DEFAULT NULL,
  `supply_value` bigint DEFAULT NULL,
  `percent_supply_investor` decimal(12,6) DEFAULT NULL,
  `valuation_tge_usd` bigint DEFAULT NULL,
  `marketcap_at_tge` bigint DEFAULT NULL,
  `target_raise_usd` bigint DEFAULT NULL,
  `calculated_price_tge` decimal(18,8) DEFAULT '0.00000000',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `founder_id` int DEFAULT NULL,
  `pain_point` text,
  `solution` text,
  `main_actors` text,
  `incentives` text,
  `competitive_advantage` text,
  `project_described` tinyint(1) NOT NULL DEFAULT '0',
  `tokenomics_done` tinyint(1) NOT NULL DEFAULT '0',
  `token_sale_page_ready` tinyint(1) NOT NULL DEFAULT '0',
  `industry_focus` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `round_token`
--

CREATE TABLE `round_token` (
  `id` int NOT NULL,
  `projet_id` varchar(36) NOT NULL,
  `tranche_type` varchar(50) DEFAULT 'investor',
  `round_name` varchar(255) NOT NULL,
  `percent_discount` decimal(5,2) DEFAULT NULL,
  `percent_total_raise` decimal(5,2) DEFAULT NULL,
  `round_price` decimal(18,8) DEFAULT '0.00000000',
  `round_amount` bigint DEFAULT NULL,
  `percent_round_supply` decimal(12,6) DEFAULT NULL,
  `number_of_tokens` decimal(20,8) DEFAULT NULL,
  `round_status` varchar(50) DEFAULT NULL,
  `number_of_token` decimal(20,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `scenario_version`
--

CREATE TABLE `scenario_version` (
  `id` int NOT NULL,
  `projet_id` char(36) NOT NULL,
  `version_label` varchar(255) NOT NULL,
  `data` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `success_fee`
--

CREATE TABLE `success_fee` (
  `id` int NOT NULL,
  `sale_id` int NOT NULL,
  `amount` varchar(50) NOT NULL,
  `currency` varchar(10) DEFAULT 'ETH',
  `tx_hash` varchar(100) NOT NULL,
  `status` varchar(20) DEFAULT 'confirmed',
  `payer_address` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `token_sale_pages`
--

CREATE TABLE `token_sale_pages` (
  `id` int NOT NULL,
  `project_id` char(36) NOT NULL,
  `scenario_version_id` int DEFAULT NULL,
  `sale_name` varchar(255) DEFAULT NULL,
  `contract_address` varchar(100) DEFAULT NULL,
  `gnosis_safe_address` varchar(100) DEFAULT NULL,
  `payment_token` varchar(100) DEFAULT NULL,
  `status` enum('draft','scheduled','live','ended_successful','ended_failed','canceled') NOT NULL DEFAULT 'draft',
  `fee_settled` tinyint(1) DEFAULT '0',
  `hosting` enum('tookle','external') DEFAULT 'tookle',
  `purchase_method` varchar(50) DEFAULT 'both',
  `country` varchar(255) DEFAULT NULL,
  `soft_cap_usd` decimal(15,2) DEFAULT NULL,
  `hard_cap_usd` decimal(15,2) DEFAULT NULL,
  `min_investment_usd` decimal(15,2) DEFAULT NULL,
  `max_investment_usd` decimal(15,2) DEFAULT NULL,
  `duration_seconds` bigint DEFAULT '604800',
  `sale_launch_at` datetime DEFAULT NULL,
  `sale_end_at` datetime DEFAULT NULL,
  `canceled_at` datetime DEFAULT NULL,
  `sale_url` varchar(256) DEFAULT NULL,
  `project_description_story` text,
  `video_file_path` varchar(255) DEFAULT NULL,
  `whitepaper_file_path` varchar(255) DEFAULT NULL,
  `general_images_json` json DEFAULT NULL,
  `value_props_json` json DEFAULT NULL,
  `team_json` json DEFAULT NULL,
  `partners_json` json DEFAULT NULL,
  `faqs_json` json DEFAULT NULL,
  `community_metrics_json` json DEFAULT NULL,
  `socials_json` json DEFAULT NULL,
  `project_roadmap_json` json DEFAULT NULL,
  `sale_terms_json` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tookle_wallets`
--

CREATE TABLE `tookle_wallets` (
  `id` bigint UNSIGNED NOT NULL,
  `claim_fee_address` varchar(255) DEFAULT NULL,
  `claim_fee_name` varchar(100) NOT NULL DEFAULT 'Claim Fee Wallet',
  `success_fee_address` varchar(255) DEFAULT NULL,
  `success_fee_name` varchar(100) NOT NULL DEFAULT 'Success Fee Wallet',
  `success_fee_bps` int UNSIGNED DEFAULT NULL COMMENT 'Success fee in basis points (bps): 100 bps = 1%, 10,000 bps = 100%',
  `network` varchar(100) NOT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `tookle_wallets`
--

INSERT INTO `tookle_wallets` (`id`, `claim_fee_address`, `claim_fee_name`, `success_fee_address`, `success_fee_name`, `success_fee_bps`, `network`, `status`, `created_at`) VALUES
(1, '0xB926065F721453B707e46F19e74E1861fa5F850f', 'Phantom Fee', '0xB926065F721453B707e46F19e74E1861fa5F850f', 'Phantom Fee', 350, 'Base', 'active', '2025-10-21 07:33:27');

-- --------------------------------------------------------

--
-- Structure de la table `tranche_token`
--

CREATE TABLE `tranche_token` (
  `id` int NOT NULL,
  `projet_id` char(36) NOT NULL,
  `tranche_name` varchar(255) DEFAULT NULL,
  `tranche_type` enum('investor','other') NOT NULL,
  `allocation_percent` decimal(12,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

CREATE TABLE `user` (
  `id` int NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `profile_description` text,
  `language` varchar(10) DEFAULT NULL,
  `invite_code` varchar(255) DEFAULT NULL,
  `kyc_status` varchar(50) DEFAULT NULL,
  `wallet_address` varchar(500) DEFAULT NULL,
  `coinbase_wallet_adress` varchar(500) DEFAULT NULL,
  `phantom_pubkey` varchar(500) DEFAULT NULL,
  `signup_method` varchar(500) DEFAULT NULL,
  `activation_token` varchar(250) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `reset_token` varchar(500) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `has_membership` tinyint NOT NULL DEFAULT '0',
  `terms_accepted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_wallet`
--

CREATE TABLE `user_wallet` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `network` varchar(50) DEFAULT NULL,
  `wallet_address` varchar(255) DEFAULT NULL,
  `label` varchar(100) DEFAULT NULL,
  `note` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utility_token`
--

CREATE TABLE `utility_token` (
  `id` int NOT NULL,
  `projet_id` char(36) NOT NULL,
  `utility_name` varchar(255) NOT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT '0',
  `utility_description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utility_token`
--

INSERT INTO `utility_token` (`id`, `projet_id`, `utility_name`, `is_custom`, `utility_description`) VALUES
(1, '43ecb06e-a7c3-4d7e-a7b1-4b9d4fb60eba', 'Token Buyback', 0, 'The protocol uses a portion of its revenue to buy back tokens on the open market, reducing circulating supply.'),
(2, '43ecb06e-a7c3-4d7e-a7b1-4b9d4fb60eba', 'Governance', 0, 'Token holders can vote on protocol decisions like feature updates, treasury use, or policy changes.'),
(3, '43ecb06e-a7c3-4d7e-a7b1-4b9d4fb60eba', 'Protocol Activity Rewards', 0, 'Users receive rewards tied to their usage of the platform, such as transaction volume or feature use.'),
(4, '43ecb06e-a7c3-4d7e-a7b1-4b9d4fb60eba', 'Access', 0, 'Tokens grant access to premium features, early product access, or gated areas of the protocol.'),
(5, '43ecb06e-a7c3-4d7e-a7b1-4b9d4fb60eba', 'Fee Discounts', 0, 'Holding tokens gives users reduced fees when trading or using services on the platform.'),
(6, '0d652ea7-83b8-41ce-b183-6ff145d3fd04', 'Governance', 0, 'Token holders can vote on protocol decisions like feature updates, treasury use, or policy changes.'),
(7, '97a9771c-3e58-4783-9df2-814008ea466e', 'Protocol Activity Rewards', 0, 'Users receive rewards tied to their usage of the platform, such as transaction volume or feature use.'),
(8, '97a9771c-3e58-4783-9df2-814008ea466e', 'Rewards', 0, 'Tokens are used to reward users for actions like engagement, referrals, or usage of the platform.'),
(9, '97a9771c-3e58-4783-9df2-814008ea466e', 'Access', 0, 'Tokens grant access to premium features, early product access, or gated areas of the protocol.'),
(10, '92163ca1-8af0-4776-880d-30576f5886a9', 'Protocol Activity Rewards', 0, 'Users receive rewards tied to their usage of the platform, such as transaction volume or feature use.'),
(11, '92163ca1-8af0-4776-880d-30576f5886a9', 'Rewards', 0, 'Tokens are used to reward users for actions like engagement, referrals, or usage of the platform.'),
(12, '92163ca1-8af0-4776-880d-30576f5886a9', 'Access', 0, 'Tokens grant access to premium features, early product access, or gated areas of the protocol.'),
(13, '92163ca1-8af0-4776-880d-30576f5886a9', 'Yield', 0, 'Token holders can earn yield through mechanisms like staking or liquidity provisioning.'),
(14, 'bdfd07be-c6a4-46fd-8274-888c5ac135f8', 'Token Buyback', 0, 'The protocol uses a portion of its revenue to buy back tokens on the open market, reducing circulating supply.'),
(15, '9b4104b9-43d7-4341-8929-ff1ddd4f9c1a', 'Protocol Activity Rewards', 0, 'Users receive rewards tied to their usage of the platform, such as transaction volume or feature use.'),
(16, '9b4104b9-43d7-4341-8929-ff1ddd4f9c1a', 'Access', 0, 'Tokens grant access to premium features, early product access, or gated areas of the protocol.'),
(18, '819897cc-aa26-4272-ae9b-e8b5876e44a8', 'Governance', 0, 'Token holders can vote on protocol decisions like feature updates, treasury use, or policy changes.'),
(19, '2ef8e174-36c2-4a07-ba95-ccdd9594f296', 'Yield', 0, 'Token holders can earn yield through mechanisms like staking or liquidity provisioning.');

-- --------------------------------------------------------

--
-- Structure de la table `vesting_token`
--

CREATE TABLE `vesting_token` (
  `id` int NOT NULL,
  `projet_id` varchar(36) NOT NULL,
  `source_id` int NOT NULL,
  `source_type` text NOT NULL,
  `tranche_type` enum('investor','other') NOT NULL,
  `vesting_block_name` varchar(255) DEFAULT NULL,
  `percent_supply_vesting` decimal(12,6) DEFAULT NULL,
  `percent_unlock_at_tge` decimal(12,6) DEFAULT NULL,
  `cliff_months` int DEFAULT NULL,
  `vesting_months` int DEFAULT NULL,
  `percent_per_month` decimal(12,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `agreement_versions`
--
ALTER TABLE `agreement_versions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `compliance_settings`
--
ALTER TABLE `compliance_settings`
  ADD UNIQUE KEY `projet_id` (`projet_id`);

--
-- Index pour la table `deployed_escrows`
--
ALTER TABLE `deployed_escrows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Index pour la table `deployed_token`
--
ALTER TABLE `deployed_token`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `projet_id` (`projet_id`),
  ADD KEY `scenario_version_id` (`scenario_version_id`);

--
-- Index pour la table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_id` (`reference_id`),
  ADD KEY `idx_distribution_status` (`distribution_status`),
  ADD KEY `idx_project_status` (`project_id`,`status`),
  ADD KEY `fk_invest_agreement_ver` (`agreement_version_id`);

--
-- Index pour la table `invite_settings`
--
ALTER TABLE `invite_settings`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `kyc_aml`
--
ALTER TABLE `kyc_aml`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_applicant` (`applicant_id`),
  ADD KEY `idx_combo` (`sanctions_result`,`pep_result`,`adverse_result`);

--
-- Index pour la table `kyc_applicants`
--
ALTER TABLE `kyc_applicants`
  ADD PRIMARY KEY (`applicant_id`),
  ADD UNIQUE KEY `uniq_external_user` (`external_user_id`);

--
-- Index pour la table `kyc_applicant_events`
--
ALTER TABLE `kyc_applicant_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_applicant` (`applicant_id`),
  ADD KEY `idx_event` (`event_type`,`created_at`);

--
-- Index pour la table `kyc_uploads`
--
ALTER TABLE `kyc_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_applicant` (`applicant_id`),
  ADD KEY `idx_flow_status` (`flow`,`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `onramp_token_requests`
--
ALTER TABLE `onramp_token_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_time` (`user_id`,`created_at`),
  ADD KEY `idx_ip_time` (`ip`,`created_at`);

--
-- Index pour la table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_id` (`reference_id`),
  ADD UNIQUE KEY `transaction_hash` (`transaction_hash`),
  ADD KEY `investment_id` (`investment_id`);

--
-- Index pour la table `preset_round`
--
ALTER TABLE `preset_round`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `preset_supply`
--
ALTER TABLE `preset_supply`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_category` (`category`(191));

--
-- Index pour la table `preset_tranche`
--
ALTER TABLE `preset_tranche`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cat_tranche` (`category_id`(191),`tranche_name`(191));

--
-- Index pour la table `project_wallet`
--
ALTER TABLE `project_wallet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `projet_id` (`projet_id`),
  ADD KEY `wallet_address` (`wallet_address`(191));

--
-- Index pour la table `projet`
--
ALTER TABLE `projet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fondateur_id` (`founder_id`);

--
-- Index pour la table `round_token`
--
ALTER TABLE `round_token`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_id` (`projet_id`);

--
-- Index pour la table `scenario_version`
--
ALTER TABLE `scenario_version`
  ADD PRIMARY KEY (`id`),
  ADD KEY `projet_id` (`projet_id`);

--
-- Index pour la table `success_fee`
--
ALTER TABLE `success_fee`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `tx_hash` (`tx_hash`);

--
-- Index pour la table `token_sale_pages`
--
ALTER TABLE `token_sale_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_sale_name` (`project_id`,`sale_name`(191)),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `tookle_wallets`
--
ALTER TABLE `tookle_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_network` (`network`),
  ADD UNIQUE KEY `uniq_network_active` (`network`,`status`),
  ADD UNIQUE KEY `fee_wallet_address` (`claim_fee_address`(191));

--
-- Index pour la table `tranche_token`
--
ALTER TABLE `tranche_token`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `projet_tranche_unique` (`projet_id`,`tranche_name`(191));

--
-- Index pour la table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `invite_code` (`invite_code`(191));

--
-- Index pour la table `user_wallet`
--
ALTER TABLE `user_wallet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `wallet_address` (`wallet_address`(191));

--
-- Index pour la table `utility_token`
--
ALTER TABLE `utility_token`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `vesting_token`
--
ALTER TABLE `vesting_token`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `agreement_versions`
--
ALTER TABLE `agreement_versions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `deployed_escrows`
--
ALTER TABLE `deployed_escrows`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `deployed_token`
--
ALTER TABLE `deployed_token`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `invite_settings`
--
ALTER TABLE `invite_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `kyc_aml`
--
ALTER TABLE `kyc_aml`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `kyc_applicant_events`
--
ALTER TABLE `kyc_applicant_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `kyc_uploads`
--
ALTER TABLE `kyc_uploads`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `onramp_token_requests`
--
ALTER TABLE `onramp_token_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `preset_round`
--
ALTER TABLE `preset_round`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `preset_supply`
--
ALTER TABLE `preset_supply`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `preset_tranche`
--
ALTER TABLE `preset_tranche`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT pour la table `project_wallet`
--
ALTER TABLE `project_wallet`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `round_token`
--
ALTER TABLE `round_token`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `scenario_version`
--
ALTER TABLE `scenario_version`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `success_fee`
--
ALTER TABLE `success_fee`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `token_sale_pages`
--
ALTER TABLE `token_sale_pages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tookle_wallets`
--
ALTER TABLE `tookle_wallets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `tranche_token`
--
ALTER TABLE `tranche_token`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user`
--
ALTER TABLE `user`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_wallet`
--
ALTER TABLE `user_wallet`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utility_token`
--
ALTER TABLE `utility_token`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `vesting_token`
--
ALTER TABLE `vesting_token`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `deployed_token`
--
ALTER TABLE `deployed_token`
  ADD CONSTRAINT `deployed_token_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `deployed_token_ibfk_2` FOREIGN KEY (`projet_id`) REFERENCES `projet` (`id`),
  ADD CONSTRAINT `deployed_token_ibfk_3` FOREIGN KEY (`scenario_version_id`) REFERENCES `scenario_version` (`id`);

--
-- Contraintes pour la table `investments`
--
ALTER TABLE `investments`
  ADD CONSTRAINT `fk_invest_agreement_ver` FOREIGN KEY (`agreement_version_id`) REFERENCES `agreement_versions` (`id`);

--
-- Contraintes pour la table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_to_investment` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
