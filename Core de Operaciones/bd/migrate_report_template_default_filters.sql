SET @has_default_property_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'default_property_code'
);

SET @has_line_item_type_scope := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'line_item_type_scope'
);

SET @after_default_property_code := IF(@has_line_item_type_scope > 0, 'line_item_type_scope', 'row_source');

SET @ddl_default_property_code := IF(
  @has_default_property_code > 0,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE report_template ADD COLUMN default_property_code VARCHAR(100) NULL DEFAULT NULL AFTER ',
    @after_default_property_code
  )
);

PREPARE stmt_default_property_code FROM @ddl_default_property_code;
EXECUTE stmt_default_property_code;
DEALLOCATE PREPARE stmt_default_property_code;

SET @has_default_status := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'default_status'
);

SET @ddl_default_status := IF(
  @has_default_status > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN default_status VARCHAR(32) NULL DEFAULT NULL AFTER default_property_code'
);

PREPARE stmt_default_status FROM @ddl_default_status;
EXECUTE stmt_default_status;
DEALLOCATE PREPARE stmt_default_status;

SET @has_default_date_type := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'default_date_type'
);

SET @ddl_default_date_type := IF(
  @has_default_date_type > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN default_date_type VARCHAR(32) NULL DEFAULT NULL AFTER default_status'
);

PREPARE stmt_default_date_type FROM @ddl_default_date_type;
EXECUTE stmt_default_date_type;
DEALLOCATE PREPARE stmt_default_date_type;

SET @has_default_date_from := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'default_date_from'
);

SET @ddl_default_date_from := IF(
  @has_default_date_from > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN default_date_from DATE NULL DEFAULT NULL AFTER default_date_type'
);

PREPARE stmt_default_date_from FROM @ddl_default_date_from;
EXECUTE stmt_default_date_from;
DEALLOCATE PREPARE stmt_default_date_from;

SET @has_default_date_to := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'default_date_to'
);

SET @ddl_default_date_to := IF(
  @has_default_date_to > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN default_date_to DATE NULL DEFAULT NULL AFTER default_date_from'
);

PREPARE stmt_default_date_to FROM @ddl_default_date_to;
EXECUTE stmt_default_date_to;
DEALLOCATE PREPARE stmt_default_date_to;
