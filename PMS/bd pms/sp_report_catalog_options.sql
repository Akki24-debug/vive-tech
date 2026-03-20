DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_catalog_options` $$
CREATE PROCEDURE `sp_report_catalog_options` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100)
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
    sic.id_line_item_catalog,
    sic.catalog_type,
    sic.item_name,
    parent_map.percent_value AS rate_percent,
    cat.id_property AS category_property_id,
    cat.category_name AS subcategory_name,
    parent.category_name AS category_name
  FROM line_item_catalog sic
  LEFT JOIN (
    SELECT
      id_sale_item_catalog,
      MIN(percent_value) AS percent_value
    FROM line_item_catalog_parent
    WHERE deleted_at IS NULL
      AND is_active = 1
    GROUP BY id_sale_item_catalog
  ) parent_map
    ON parent_map.id_sale_item_catalog = sic.id_line_item_catalog
  JOIN sale_item_category cat
    ON cat.id_sale_item_category = sic.id_category
   AND cat.id_company = v_company_id
   AND cat.deleted_at IS NULL
  LEFT JOIN sale_item_category parent
    ON parent.id_sale_item_category = cat.id_parent_sale_item_category
  LEFT JOIN property prop
    ON prop.id_property = cat.id_property
  WHERE sic.deleted_at IS NULL
    AND sic.is_active = 1
    AND sic.catalog_type IN ('sale_item','tax_rule')
    AND (
      v_property_id IS NULL
      OR cat.id_property IS NULL
      OR cat.id_property = v_property_id
      OR prop.code = p_property_code
    )
  ORDER BY category_name, subcategory_name, sic.item_name;
END $$

DELIMITER ;
