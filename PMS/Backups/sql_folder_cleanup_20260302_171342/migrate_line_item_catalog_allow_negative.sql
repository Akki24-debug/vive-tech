SET @has_allow_negative := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'line_item_catalog'
    AND column_name = 'allow_negative'
);

SET @sql_allow_negative := IF(
  @has_allow_negative = 0,
  'ALTER TABLE line_item_catalog ADD COLUMN allow_negative TINYINT(1) NOT NULL DEFAULT 0 AFTER show_in_folio',
  'SELECT ''line_item_catalog.allow_negative already exists'' AS msg'
);

PREPARE stmt_allow_negative FROM @sql_allow_negative;
EXECUTE stmt_allow_negative;
DEALLOCATE PREPARE stmt_allow_negative;

