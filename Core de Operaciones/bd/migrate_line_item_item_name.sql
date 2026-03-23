SET @has_item_name := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'line_item'
    AND COLUMN_NAME = 'item_name'
);

SET @ddl := IF(
  @has_item_name > 0,
  'SELECT 1',
  'ALTER TABLE line_item ADD COLUMN item_name VARCHAR(255) NULL DEFAULT NULL AFTER id_line_item_catalog'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE line_item li
LEFT JOIN line_item_catalog lic
  ON lic.id_line_item_catalog = li.id_line_item_catalog
SET li.item_name = CASE
  WHEN NULLIF(TRIM(COALESCE(li.item_name, '')), '') IS NOT NULL THEN li.item_name
  WHEN NULLIF(TRIM(COALESCE(lic.item_name, '')), '') IS NOT NULL THEN lic.item_name
  WHEN li.item_type = 'payment' AND NULLIF(TRIM(COALESCE(li.method, '')), '') IS NOT NULL THEN li.method
  WHEN NULLIF(TRIM(COALESCE(li.description, '')), '') IS NOT NULL THEN li.description
  ELSE li.item_name
END
WHERE NULLIF(TRIM(COALESCE(li.item_name, '')), '') IS NULL;
