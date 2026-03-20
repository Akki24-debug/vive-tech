SET @has_subdivide_by_field_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'subdivide_by_field_id'
);

SET @has_line_item_type_scope := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'line_item_type_scope'
);

SET @has_row_source := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'row_source'
);

SET @after_column := IF(
  @has_line_item_type_scope > 0,
  'line_item_type_scope',
  IF(@has_row_source > 0, 'row_source', 'description')
);

SET @ddl := IF(
  @has_subdivide_by_field_id > 0,
  'SELECT 1',
  CONCAT(
    'ALTER TABLE report_template ADD COLUMN subdivide_by_field_id BIGINT NULL DEFAULT NULL AFTER ',
    @after_column
  )
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_subdivide_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND INDEX_NAME = 'idx_report_template_subdivide_field'
);

SET @ddl_idx := IF(
  @has_subdivide_index > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD KEY idx_report_template_subdivide_field (subdivide_by_field_id)'
);

PREPARE stmt_idx FROM @ddl_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

SET @has_subdivide_by_field_id_level_2 := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'subdivide_by_field_id_level_2'
);

SET @ddl_level_2 := IF(
  @has_subdivide_by_field_id_level_2 > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN subdivide_by_field_id_level_2 BIGINT NULL DEFAULT NULL AFTER subdivide_by_field_id'
);

PREPARE stmt_level_2 FROM @ddl_level_2;
EXECUTE stmt_level_2;
DEALLOCATE PREPARE stmt_level_2;

SET @has_subdivide_by_field_id_level_3 := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'subdivide_by_field_id_level_3'
);

SET @ddl_level_3 := IF(
  @has_subdivide_by_field_id_level_3 > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN subdivide_by_field_id_level_3 BIGINT NULL DEFAULT NULL AFTER subdivide_by_field_id_level_2'
);

PREPARE stmt_level_3 FROM @ddl_level_3;
EXECUTE stmt_level_3;
DEALLOCATE PREPARE stmt_level_3;

SET @has_subdivide_show_totals_level_1 := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'subdivide_show_totals_level_1'
);

SET @ddl_totals_level_1 := IF(
  @has_subdivide_show_totals_level_1 > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN subdivide_show_totals_level_1 TINYINT(1) NOT NULL DEFAULT 1 AFTER subdivide_by_field_id_level_3'
);

PREPARE stmt_totals_level_1 FROM @ddl_totals_level_1;
EXECUTE stmt_totals_level_1;
DEALLOCATE PREPARE stmt_totals_level_1;

SET @has_subdivide_show_totals_level_2 := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'subdivide_show_totals_level_2'
);

SET @ddl_totals_level_2 := IF(
  @has_subdivide_show_totals_level_2 > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN subdivide_show_totals_level_2 TINYINT(1) NOT NULL DEFAULT 1 AFTER subdivide_show_totals_level_1'
);

PREPARE stmt_totals_level_2 FROM @ddl_totals_level_2;
EXECUTE stmt_totals_level_2;
DEALLOCATE PREPARE stmt_totals_level_2;

SET @has_subdivide_show_totals_level_3 := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND COLUMN_NAME = 'subdivide_show_totals_level_3'
);

SET @ddl_totals_level_3 := IF(
  @has_subdivide_show_totals_level_3 > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD COLUMN subdivide_show_totals_level_3 TINYINT(1) NOT NULL DEFAULT 1 AFTER subdivide_show_totals_level_2'
);

PREPARE stmt_totals_level_3 FROM @ddl_totals_level_3;
EXECUTE stmt_totals_level_3;
DEALLOCATE PREPARE stmt_totals_level_3;

SET @has_subdivide_index_level_2 := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND INDEX_NAME = 'idx_report_template_subdivide_field_level_2'
);

SET @ddl_idx_level_2 := IF(
  @has_subdivide_index_level_2 > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD KEY idx_report_template_subdivide_field_level_2 (subdivide_by_field_id_level_2)'
);

PREPARE stmt_idx_level_2 FROM @ddl_idx_level_2;
EXECUTE stmt_idx_level_2;
DEALLOCATE PREPARE stmt_idx_level_2;

SET @has_subdivide_index_level_3 := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template'
    AND INDEX_NAME = 'idx_report_template_subdivide_field_level_3'
);

SET @ddl_idx_level_3 := IF(
  @has_subdivide_index_level_3 > 0,
  'SELECT 1',
  'ALTER TABLE report_template ADD KEY idx_report_template_subdivide_field_level_3 (subdivide_by_field_id_level_3)'
);

PREPARE stmt_idx_level_3 FROM @ddl_idx_level_3;
EXECUTE stmt_idx_level_3;
DEALLOCATE PREPARE stmt_idx_level_3;
