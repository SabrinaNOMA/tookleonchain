-- ============================================================================
-- TOOKLE DIRECT GNOSIS ROUTING & CLAIM GATING MIGRATION
-- Run this script on your live OVH database (tooklepreprod) after your demo!
-- ============================================================================

-- 1. Add Gnosis Safe Address column to store the destination address
ALTER TABLE token_sale_pages 
    ADD COLUMN gnosis_safe_address VARCHAR(100) DEFAULT NULL AFTER contract_address;

-- 2. Add fee_settled status column to track post-sale fee invoice payment status
ALTER TABLE token_sale_pages 
    ADD COLUMN fee_settled TINYINT(1) DEFAULT 0 AFTER status;

-- 3. Add payment_tx_hash to investments table to track the direct blockchain receipt
ALTER TABLE investments 
    ADD COLUMN payment_tx_hash VARCHAR(100) DEFAULT NULL AFTER status;
