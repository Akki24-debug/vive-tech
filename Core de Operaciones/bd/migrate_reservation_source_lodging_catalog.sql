SET @has_column := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservation_source_catalog'
    AND COLUMN_NAME = 'id_lodging_catalog'
);

SET @sql_add_column := IF(
  @has_column = 0,
  'ALTER TABLE reservation_source_catalog ADD COLUMN id_lodging_catalog BIGINT NULL AFTER notes',
  'SELECT 1'
);
PREPARE stmt_add_column FROM @sql_add_column;
EXECUTE stmt_add_column;
DEALLOCATE PREPARE stmt_add_column;

SET @has_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservation_source_catalog'
    AND INDEX_NAME = 'idx_rsc_lodging_catalog'
);

SET @sql_add_index := IF(
  @has_index = 0,
  'ALTER TABLE reservation_source_catalog ADD KEY idx_rsc_lodging_catalog (id_lodging_catalog, is_active, deleted_at)',
  'SELECT 1'
);
PREPARE stmt_add_index FROM @sql_add_index;
EXECUTE stmt_add_index;
DEALLOCATE PREPARE stmt_add_index;
