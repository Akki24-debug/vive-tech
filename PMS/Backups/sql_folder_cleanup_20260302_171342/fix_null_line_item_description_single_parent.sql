-- Backfill descriptions for derived line items that were saved with NULL/blank description.
-- Rule: only catalogs with exactly one active parent relation are updated.

UPDATE line_item li
JOIN (
  SELECT
    lcp.id_sale_item_catalog,
    MIN(lcp.id_parent_sale_item_catalog) AS id_parent_sale_item_catalog
  FROM line_item_catalog_parent lcp
  WHERE lcp.deleted_at IS NULL
    AND lcp.is_active = 1
  GROUP BY lcp.id_sale_item_catalog
  HAVING COUNT(*) = 1
) rel
  ON rel.id_sale_item_catalog = li.id_line_item_catalog
JOIN line_item_catalog child_cat
  ON child_cat.id_line_item_catalog = li.id_line_item_catalog
LEFT JOIN line_item_catalog parent_cat
  ON parent_cat.id_line_item_catalog = rel.id_parent_sale_item_catalog
SET li.description = CONCAT(
  COALESCE(NULLIF(TRIM(child_cat.item_name), ''), CONCAT('Catalog#', li.id_line_item_catalog)),
  ' / ',
  COALESCE(NULLIF(TRIM(parent_cat.item_name), ''), CONCAT('Catalog#', rel.id_parent_sale_item_catalog))
)
WHERE li.deleted_at IS NULL
  AND li.is_active = 1
  AND li.item_type IN ('sale_item', 'tax_item', 'obligation', 'income')
  AND (li.description IS NULL OR TRIM(li.description) = '');
