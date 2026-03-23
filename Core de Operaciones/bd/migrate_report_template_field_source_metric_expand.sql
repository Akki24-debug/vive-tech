SET @has_source_metric := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'source_metric'
);

SET @ddl_source_metric := IF(
  @has_source_metric = 0,
  'ALTER TABLE report_template_field ADD COLUMN source_metric VARCHAR(64) DEFAULT NULL AFTER id_report_calculation',
  'ALTER TABLE report_template_field MODIFY COLUMN source_metric VARCHAR(64) DEFAULT NULL'
);

PREPARE stmt_source_metric FROM @ddl_source_metric;
EXECUTE stmt_source_metric;
DEALLOCATE PREPARE stmt_source_metric;

UPDATE report_template_field
   SET source_metric = NULL
 WHERE TRIM(COALESCE(source_metric, '')) = '';
