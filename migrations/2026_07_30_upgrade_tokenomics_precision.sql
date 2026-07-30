-- ====================================================================
-- Migration: Upgrade Tokenomics Precision to DECIMAL(12,6)
-- Date: 2026-07-30
-- Description: Upgrades allocation, vesting, and supply percentage fields
-- to DECIMAL(12,6) to preserve high precision for multi-billion token supplies
-- and prevent rounding offset errors.
-- ====================================================================

-- 1. Vesting Token Table Precision
ALTER TABLE `vesting_token` 
  MODIFY `percent_supply_vesting` DECIMAL(12,6) DEFAULT NULL,
  MODIFY `percent_unlock_at_tge` DECIMAL(12,6) DEFAULT NULL,
  MODIFY `percent_per_month` DECIMAL(12,6) DEFAULT NULL;

-- 2. Tranche Token Allocation Precision
ALTER TABLE `tranche_token` 
  MODIFY `allocation_percent` DECIMAL(12,6) DEFAULT NULL;

-- 3. Round Token Supply Precision
ALTER TABLE `round_token` 
  MODIFY `percent_round_supply` DECIMAL(12,6) DEFAULT NULL;

-- 4. Project Table Investor Supply Precision
ALTER TABLE `projet` 
  MODIFY `percent_supply_investor` DECIMAL(12,6) DEFAULT NULL;
