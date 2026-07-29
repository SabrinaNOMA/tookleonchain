-- ============================================================================
-- TOOKLE HIGH-PRECISION TOKENOMICS MIGRATION SCRIPT
-- Run this script on your live OVH database (tooklepreprod) AFTER your demo!
-- ============================================================================

-- 1. Upgrade Round Supply Percentage to 4 Decimals (0.0001% = 1 basis point precision)
ALTER TABLE round_token 
    MODIFY percent_round_supply DECIMAL(8,4) DEFAULT 0.0000;

-- 2. Upgrade Round Price to 8 Decimals (supports micro-cent prices down to $0.00000001)
ALTER TABLE round_token 
    MODIFY round_price DECIMAL(18,8) DEFAULT 0.00000000;

-- 3. Upgrade Tranche Allocation Percentage to 4 Decimals (guarantees exact 100.0000% sums)
ALTER TABLE tranche_token 
    MODIFY allocation_percent DECIMAL(8,4) DEFAULT 0.0000;

-- 4. Upgrade Calculated TGE Token Price to 8 Decimals
ALTER TABLE projet 
    MODIFY calculated_price_tge DECIMAL(18,8) DEFAULT 0.00000000;
