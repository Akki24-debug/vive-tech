-- Phase 1: migrate legacy percentages from line_item_catalog to line_item_catalog_parent.
-- Safe to run multiple times.

-- 1) Ensure destination column exists.
SET @has_parent_percent := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'line_item_catalog_parent'
    AND COLUMN_NAME = 'percent_value'
);

SET @sql_add_parent_percent := IF(
  @has_parent_percent = 0,
  'ALTER TABLE line_item_catalog_parent ADD COLUMN percent_value DECIMAL(12,6) NULL DEFAULT NULL AFTER add_to_father_total',
  'SELECT ''line_item_catalog_parent.percent_value already exists'' AS msg'
);
PREPARE stmt_add_parent_percent FROM @sql_add_parent_percent;
EXECUTE stmt_add_parent_percent;
DEALLOCATE PREPARE stmt_add_parent_percent;

-- 2) Resolve which legacy percent column exists in line_item_catalog.
SET @has_rate_percent := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'line_item_catalog'
    AND COLUMN_NAME = 'rate_percent'
);

SET @has_percent_value := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'line_item_catalog'
    AND COLUMN_NAME = 'percent_value'
);

SET @legacy_percent_col := CASE
  WHEN @has_rate_percent > 0 THEN 'rate_percent'
  WHEN @has_percent_value > 0 THEN 'percent_value'
  ELSE NULL
END;

-- 3) Backfill per-parent percent only where still null.
--    Rule: if child was legacy "percent", copy legacy percent to all parent links of that child.
SET @sql_backfill := IF(
  @legacy_percent_col IS NULL,
  'SELECT ''No legacy percent column found in line_item_catalog; skipping backfill'' AS msg',
  CONCAT(
    'UPDATE line_item_catalog_parent lcp ',
    'JOIN line_item_catalog child ON child.id_line_item_catalog = lcp.id_sale_item_catalog ',
    'SET lcp.percent_value = CASE ',
    '  WHEN COALESCE(child.is_percent,0) = 1 THEN child.', @legacy_percent_col, ' ',
    '  ELSE lcp.percent_value ',
    'END, ',
    'lcp.updated_at = NOW() ',
    'WHERE lcp.deleted_at IS NULL ',
    '  AND lcp.is_active = 1 ',
    '  AND lcp.percent_value IS NULL'
  )
);
PREPARE stmt_backfill FROM @sql_backfill;
EXECUTE stmt_backfill;
DEALLOCATE PREPARE stmt_backfill;

-- 4) Optional normalization:
--    if child is explicitly non-percent, keep parent percent null.
UPDATE line_item_catalog_parent lcp
JOIN line_item_catalog child
  ON child.id_line_item_catalog = lcp.id_sale_item_catalog
SET lcp.percent_value = NULL,
    lcp.updated_at = NOW()
WHERE lcp.deleted_at IS NULL
  AND lcp.is_active = 1
  AND COALESCE(child.is_percent, 0) = 0
  AND lcp.percent_value IS NOT NULL;

SELECT
  COUNT(*) AS active_parent_links,
  SUM(CASE WHEN percent_value IS NOT NULL THEN 1 ELSE 0 END) AS links_with_percent
FROM line_item_catalog_parent
WHERE deleted_at IS NULL
  AND is_active = 1;

