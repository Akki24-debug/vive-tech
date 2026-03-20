ALTER TABLE `ota_account`
  ADD COLUMN IF NOT EXISTS `secondary_price_adjustment_pct` decimal(9,3) DEFAULT NULL AFTER `price_adjustment_value`,
  ADD INDEX IF NOT EXISTS `idx_ota_account_secondary_price_adjustment` (`secondary_price_adjustment_pct`,`is_active`,`deleted_at`);
