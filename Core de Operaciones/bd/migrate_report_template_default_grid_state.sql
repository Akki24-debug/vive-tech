SET @has_default_grid_state_json := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'default_grid_state_json'
);

SET @ddl_default_grid_state_json := IF(
  @has_default_grid_state_json > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN default_grid_state_json LONGTEXT NULL DEFAULT NULL AFTER default_date_to'
);

PREPARE stmt_default_grid_state_json FROM @ddl_default_grid_state_json;
EXECUTE stmt_default_grid_state_json;
DEALLOCATE PREPARE stmt_default_grid_state_json;
