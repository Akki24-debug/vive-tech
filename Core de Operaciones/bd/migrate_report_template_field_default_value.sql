SET @has_default_value := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'default_value'
);

SET @ddl := IF(
  @has_default_value > 0,
  'SELECT 1',
  'ALTER TABLE report_template_field ADD COLUMN default_value VARCHAR(255) NULL AFTER display_name'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
