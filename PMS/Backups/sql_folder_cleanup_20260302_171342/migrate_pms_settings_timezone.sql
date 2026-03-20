/* Adds company-level software timezone support in pms_settings. */

SET @has_timezone := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'timezone'
);

SET @sql_add_timezone := IF(
  @has_timezone = 0,
  'ALTER TABLE pms_settings ADD COLUMN timezone VARCHAR(64) NULL AFTER id_property',
  'SELECT 1'
);

PREPARE stmt_add_timezone FROM @sql_add_timezone;
EXECUTE stmt_add_timezone;
DEALLOCATE PREPARE stmt_add_timezone;

SET @has_scope_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND INDEX_NAME = 'idx_pms_settings_scope'
);

SET @sql_add_scope_index := IF(
  @has_scope_index = 0,
  'ALTER TABLE pms_settings ADD INDEX idx_pms_settings_scope (id_company, id_property, id_setting)',
  'SELECT 1'
);

PREPARE stmt_add_scope_index FROM @sql_add_scope_index;
EXECUTE stmt_add_scope_index;
DEALLOCATE PREPARE stmt_add_scope_index;

