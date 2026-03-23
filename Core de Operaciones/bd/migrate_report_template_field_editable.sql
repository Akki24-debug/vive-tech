SET @has_is_editable := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'is_editable'
);

SET @ddl_is_editable := IF(
  @has_is_editable = 0,
  'ALTER TABLE report_template_field ADD COLUMN is_editable TINYINT(1) NOT NULL DEFAULT 0 AFTER default_value',
  'SELECT 1'
);

PREPARE stmt_is_editable FROM @ddl_is_editable;
EXECUTE stmt_is_editable;
DEALLOCATE PREPARE stmt_is_editable;
