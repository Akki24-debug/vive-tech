DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_catalog_data` $$
CREATE PROCEDURE `sp_sale_item_catalog_data` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_show_inactive TINYINT,
  IN p_item_id BIGINT,
  IN p_category_id BIGINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;

    IF v_property_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property';
    END IF;
  ELSE
    SET v_property_id = NULL;
  END IF;

  SELECT
    lic.id_line_item_catalog AS id_sale_item_catalog,
    lic.catalog_type,
    lic.id_category,
    cat.category_name AS category,
    parent_map.parent_first_id AS id_parent_sale_item_catalog,
    parent_first.item_name AS parent_item_name,
    parent_map.parent_item_ids,
    parent_map.add_to_father_total,
    parent_map.is_percent,
    parent_map.percent_value,
    lic.show_in_folio,
    lic.allow_negative,
    lic.item_name,
    lic.description,
    lic.default_unit_price_cents,
    lic.is_active,
    p.code AS property_code,
    CAST(NULL AS CHAR) AS tax_rule_ids
  FROM line_item_catalog lic
  JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
   AND cat.id_company = v_company_id
   AND cat.deleted_at IS NULL
  LEFT JOIN (
    SELECT
      lcp.id_sale_item_catalog,
      GROUP_CONCAT(DISTINCT lcp.id_parent_sale_item_catalog ORDER BY lcp.id_parent_sale_item_catalog) AS parent_item_ids,
      MIN(lcp.id_parent_sale_item_catalog) AS parent_first_id,
      MIN(lcp.add_to_father_total) AS add_to_father_total,
      MAX(CASE WHEN lcp.percent_value IS NOT NULL THEN 1 ELSE 0 END) AS is_percent,
      MIN(lcp.percent_value) AS percent_value
    FROM line_item_catalog_parent lcp
    JOIN line_item_catalog parent
      ON parent.id_line_item_catalog = lcp.id_parent_sale_item_catalog
     AND parent.deleted_at IS NULL
     AND parent.is_active = 1
    WHERE lcp.deleted_at IS NULL
      AND lcp.is_active = 1
    GROUP BY lcp.id_sale_item_catalog
  ) parent_map ON parent_map.id_sale_item_catalog = lic.id_line_item_catalog
  LEFT JOIN line_item_catalog parent_first
    ON parent_first.id_line_item_catalog = parent_map.parent_first_id
  LEFT JOIN property p ON p.id_property = cat.id_property
  WHERE lic.deleted_at IS NULL
    AND lic.catalog_type IN ('sale_item','payment','obligation','income','tax_rule')
    AND (p_show_inactive IS NOT NULL AND p_show_inactive <> 0 OR lic.is_active = 1)
    AND (p_show_inactive IS NOT NULL AND p_show_inactive <> 0 OR cat.is_active = 1)
    AND (v_property_id IS NULL OR cat.id_property IS NULL OR cat.id_property = v_property_id)
    AND (p_category_id IS NULL OR p_category_id = 0 OR lic.id_category = p_category_id)
  ORDER BY cat.category_name, lic.item_name;

  IF p_item_id IS NULL OR p_item_id = 0 THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_sale_item_catalog,
      CAST(NULL AS CHAR) AS catalog_type,
      CAST(NULL AS SIGNED) AS id_category,
      CAST(NULL AS CHAR) AS category,
      CAST(NULL AS SIGNED) AS id_parent_sale_item_catalog,
      CAST(NULL AS CHAR) AS parent_item_name,
      CAST(NULL AS CHAR) AS parent_item_ids,
      CAST(NULL AS SIGNED) AS add_to_father_total,
      CAST(NULL AS SIGNED) AS is_percent,
      CAST(NULL AS DECIMAL(12,6)) AS percent_value,
      CAST(NULL AS SIGNED) AS show_in_folio,
      CAST(NULL AS SIGNED) AS allow_negative,
      CAST(NULL AS CHAR) AS item_name,
      CAST(NULL AS CHAR) AS description,
      CAST(NULL AS SIGNED) AS default_unit_price_cents,
      CAST(NULL AS SIGNED) AS is_active,
      CAST(NULL AS CHAR) AS property_code,
      CAST(NULL AS CHAR) AS tax_rule_ids
    LIMIT 0;
  ELSE
    SELECT
      lic.id_line_item_catalog AS id_sale_item_catalog,
      lic.catalog_type,
      lic.id_category,
      cat.category_name AS category,
      parent_map.parent_first_id AS id_parent_sale_item_catalog,
      parent_first.item_name AS parent_item_name,
      parent_map.parent_item_ids,
      parent_map.add_to_father_total,
      parent_map.is_percent,
      parent_map.percent_value,
      lic.show_in_folio,
      lic.allow_negative,
      lic.item_name,
      lic.description,
      lic.default_unit_price_cents,
      lic.is_active,
      p.code AS property_code,
      CAST(NULL AS CHAR) AS tax_rule_ids
    FROM line_item_catalog lic
    JOIN sale_item_category cat
      ON cat.id_sale_item_category = lic.id_category
     AND cat.id_company = v_company_id
     AND cat.deleted_at IS NULL
    LEFT JOIN (
      SELECT
        lcp.id_sale_item_catalog,
        GROUP_CONCAT(DISTINCT lcp.id_parent_sale_item_catalog ORDER BY lcp.id_parent_sale_item_catalog) AS parent_item_ids,
        MIN(lcp.id_parent_sale_item_catalog) AS parent_first_id,
        MIN(lcp.add_to_father_total) AS add_to_father_total,
        MAX(CASE WHEN lcp.percent_value IS NOT NULL THEN 1 ELSE 0 END) AS is_percent,
        MIN(lcp.percent_value) AS percent_value
      FROM line_item_catalog_parent lcp
      JOIN line_item_catalog parent
        ON parent.id_line_item_catalog = lcp.id_parent_sale_item_catalog
       AND parent.deleted_at IS NULL
       AND parent.is_active = 1
      WHERE lcp.deleted_at IS NULL
        AND lcp.is_active = 1
      GROUP BY lcp.id_sale_item_catalog
    ) parent_map ON parent_map.id_sale_item_catalog = lic.id_line_item_catalog
    LEFT JOIN line_item_catalog parent_first
      ON parent_first.id_line_item_catalog = parent_map.parent_first_id
    LEFT JOIN property p ON p.id_property = cat.id_property
    WHERE lic.id_line_item_catalog = p_item_id
    LIMIT 1;
  END IF;
END $$

DELIMITER ;
