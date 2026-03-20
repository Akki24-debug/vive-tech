SET @supports_combined_row_source := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'row_source'
    AND COLUMN_TYPE LIKE '%combined%'
);

SET @ddl_combined_row_source := IF(
  @supports_combined_row_source > 0,
  'SELECT 1',
  'ALTER TABLE report_template MODIFY COLUMN row_source ENUM(''reservation'',''line_item'',''combined'') NOT NULL DEFAULT ''reservation'''
);

PREPARE stmt_combined_row_source FROM @ddl_combined_row_source;
EXECUTE stmt_combined_row_source;
DEALLOCATE PREPARE stmt_combined_row_source;
