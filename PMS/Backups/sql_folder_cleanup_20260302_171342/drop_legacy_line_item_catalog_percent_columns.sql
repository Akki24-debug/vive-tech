-- Phase 2: remove legacy percent fields from line_item_catalog
-- Run this ONLY after code/SP migration to per-parent percent is deployed.

SET @has_is_percent := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'line_item_catalog'
    AND COLUMN_NAME = 'is_percent'
);

SET @has_percent_value := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'line_item_catalog'
    AND COLUMN_NAME = 'percent_value'
);

SET @has_rate_percent := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'line_item_catalog'
    AND COLUMN_NAME = 'rate_percent'
);

SET @sql_drop_is_percent := IF(
  @has_is_percent > 0,
  'ALTER TABLE line_item_catalog DROP COLUMN is_percent',
  'SELECT ''line_item_catalog.is_percent already removed'' AS msg'
);
PREPARE stmt_drop_is_percent FROM @sql_drop_is_percent;
EXECUTE stmt_drop_is_percent;
DEALLOCATE PREPARE stmt_drop_is_percent;

SET @sql_drop_percent_value := IF(
  @has_percent_value > 0,
  'ALTER TABLE line_item_catalog DROP COLUMN percent_value',
  'SELECT ''line_item_catalog.percent_value already removed'' AS msg'
);
PREPARE stmt_drop_percent_value FROM @sql_drop_percent_value;
EXECUTE stmt_drop_percent_value;
DEALLOCATE PREPARE stmt_drop_percent_value;

SET @sql_drop_rate_percent := IF(
  @has_rate_percent > 0,
  'ALTER TABLE line_item_catalog DROP COLUMN rate_percent',
  'SELECT ''line_item_catalog.rate_percent already removed'' AS msg'
);
PREPARE stmt_drop_rate_percent FROM @sql_drop_rate_percent;
EXECUTE stmt_drop_rate_percent;
DEALLOCATE PREPARE stmt_drop_rate_percent;

