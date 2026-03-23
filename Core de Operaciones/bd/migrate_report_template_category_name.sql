SET @has_category_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'category_name'
);

SET @has_report_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'report_name'
);

SET @after_column := IF(@has_report_name > 0, 'report_name', 'report_key');

SET @ddl := IF(
  @has_category_name > 0,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE report_template ADD COLUMN category_name VARCHAR(120) NULL DEFAULT NULL AFTER ',
    @after_column
  )
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
