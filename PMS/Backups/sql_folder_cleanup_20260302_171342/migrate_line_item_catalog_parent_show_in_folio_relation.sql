SET @has_show_in_folio_relation := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'line_item_catalog_parent'
    AND column_name = 'show_in_folio_relation'
);

SET @sql_show_in_folio_relation := IF(
  @has_show_in_folio_relation = 0,
  'ALTER TABLE line_item_catalog_parent ADD COLUMN show_in_folio_relation TINYINT(1) NULL DEFAULT NULL AFTER add_to_father_total',
  'SELECT ''line_item_catalog_parent.show_in_folio_relation already exists'' AS msg'
);

PREPARE stmt_show_in_folio_relation FROM @sql_show_in_folio_relation;
EXECUTE stmt_show_in_folio_relation;
DEALLOCATE PREPARE stmt_show_in_folio_relation;
