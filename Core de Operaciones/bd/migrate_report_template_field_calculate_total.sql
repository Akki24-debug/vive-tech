SET @has_calculate_total := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'calculate_total'
);

SET @has_is_editable := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'is_editable'
);

SET @has_default_value := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'default_value'
);

SET @after_column := IF(
  @has_is_editable > 0,
  'is_editable',
  IF(@has_default_value > 0, 'default_value', 'display_name')
);

SET @ddl := IF(
  @has_calculate_total > 0,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE report_template_field ADD COLUMN calculate_total TINYINT(1) NOT NULL DEFAULT 0 AFTER ',
    @after_column
  )
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
