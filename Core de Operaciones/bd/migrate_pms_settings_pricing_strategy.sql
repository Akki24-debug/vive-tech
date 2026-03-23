SET @has_pricing_strategy := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'pricing_strategy'
);

SET @ddl_pricing_strategy := IF(
  @has_pricing_strategy > 0,
  'SELECT 1',
  'ALTER TABLE pms_settings ADD COLUMN pricing_strategy VARCHAR(32) NOT NULL DEFAULT ''use_bases'' AFTER timezone'
);

PREPARE stmt_pricing_strategy FROM @ddl_pricing_strategy;
EXECUTE stmt_pricing_strategy;
DEALLOCATE PREPARE stmt_pricing_strategy;
