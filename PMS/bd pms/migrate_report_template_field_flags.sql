SET @has_default_value := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'default_value'
);

SET @base_after := IF(@has_default_value > 0, 'default_value', 'display_name');

SET @has_is_editable := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'is_editable'
);

SET @ddl_is_editable := IF(
  @has_is_editable > 0,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE report_template_field ADD COLUMN is_editable TINYINT(1) NOT NULL DEFAULT 0 AFTER ',
    @base_after
  )
);

PREPARE stmt_is_editable FROM @ddl_is_editable;
EXECUTE stmt_is_editable;
DEALLOCATE PREPARE stmt_is_editable;

SET @has_calculate_total := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'calculate_total'
);

SET @after_calculate_total := IF(
  (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_template_field'
      AND COLUMN_NAME = 'is_editable'
  ) > 0,
  'is_editable',
  @base_after
);

SET @ddl_calculate_total := IF(
  @has_calculate_total > 0,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE report_template_field ADD COLUMN calculate_total TINYINT(1) NOT NULL DEFAULT 0 AFTER ',
    @after_calculate_total
  )
);

PREPARE stmt_calculate_total FROM @ddl_calculate_total;
EXECUTE stmt_calculate_total;
DEALLOCATE PREPARE stmt_calculate_total;
