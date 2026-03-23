DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_category_data` $$
CREATE PROCEDURE `sp_sale_item_category_data` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_show_inactive TINYINT,
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

  /* listado */
  SELECT
    sic.id_sale_item_category,
    sic.id_parent_sale_item_category,
    parent.category_name AS parent_category_name,
    sic.category_name,
    sic.description,
    sic.is_active,
    p.code AS property_code
  FROM sale_item_category sic
  LEFT JOIN sale_item_category parent ON parent.id_sale_item_category = sic.id_parent_sale_item_category
  LEFT JOIN property p ON p.id_property = sic.id_property
  WHERE sic.id_company = v_company_id
    AND (v_property_id IS NULL OR sic.id_property IS NULL OR sic.id_property = v_property_id)
    AND sic.deleted_at IS NULL
    AND (p_show_inactive IS NOT NULL AND p_show_inactive <> 0 OR sic.is_active = 1)
  ORDER BY sic.category_name;

  /* detalle */
  IF p_category_id IS NULL OR p_category_id = 0 THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_sale_item_category,
      CAST(NULL AS SIGNED) AS id_parent_sale_item_category,
      CAST(NULL AS CHAR) AS parent_category_name,
      CAST(NULL AS CHAR) AS category_name,
      CAST(NULL AS CHAR) AS description,
      CAST(NULL AS SIGNED) AS is_active,
      CAST(NULL AS CHAR) AS property_code
    LIMIT 0;
  ELSE
    SELECT
      sic.id_sale_item_category,
      sic.id_parent_sale_item_category,
      parent.category_name AS parent_category_name,
      sic.category_name,
      sic.description,
      sic.is_active,
      p.code AS property_code
    FROM sale_item_category sic
    LEFT JOIN sale_item_category parent ON parent.id_sale_item_category = sic.id_parent_sale_item_category
    LEFT JOIN property p ON p.id_property = sic.id_property
    WHERE sic.id_sale_item_category = p_category_id
      AND sic.id_company = v_company_id
    LIMIT 1;
  END IF;
END $$

DELIMITER ;
