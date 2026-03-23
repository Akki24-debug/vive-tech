DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_field_catalog_data` $$
CREATE PROCEDURE `sp_report_field_catalog_data` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_report_type VARCHAR(32)
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_report_type VARCHAR(32);

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SET v_report_type = LOWER(TRIM(COALESCE(p_report_type, 'reservation')));
  IF v_report_type NOT IN ('reservation','line_item','property') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported report_type';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  IF p_property_code IS NOT NULL AND TRIM(p_property_code) <> '' THEN
    SELECT id_property
      INTO v_property_id
    FROM property
    WHERE code = TRIM(p_property_code)
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
    rfc.id_report_field_catalog,
    rfc.report_type,
    rfc.field_group,
    rfc.field_key,
    rfc.field_label,
    rfc.data_type,
    rfc.supports_filter,
    rfc.supports_sort,
    rfc.is_default,
    rfc.default_order,
    rfc.select_expression,
    rfc.filter_expression
  FROM report_field_catalog rfc
  WHERE rfc.report_type = v_report_type
    AND rfc.is_active = 1
  ORDER BY rfc.field_group, rfc.default_order, rfc.field_label;

  SELECT
    lic.id_line_item_catalog,
    CONCAT('catalog_', lic.id_line_item_catalog) AS column_key,
    lic.item_name,
    lic.catalog_type,
    COALESCE(parent.category_name, '(sin categoria padre)') AS category_name,
    cat.category_name AS subcategory_name,
    cat.id_property AS category_property_id,
    COALESCE(prop.code, '') AS property_code,
    COALESCE(prop.name, 'Global') AS property_name,
    CONCAT(
      COALESCE(parent.category_name, '(sin categoria padre)'),
      ' / ',
      COALESCE(cat.category_name, '(sin subcategoria)')
    ) AS selection_group,
    CASE
      WHEN lic.catalog_type IN ('sale_item','tax_item','tax_rule','payment','obligation','income') THEN 1
      ELSE 0
    END AS is_supported
  FROM line_item_catalog lic
  JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
   AND cat.id_company = v_company_id
   AND cat.deleted_at IS NULL
  LEFT JOIN sale_item_category parent
    ON parent.id_sale_item_category = cat.id_parent_sale_item_category
   AND parent.deleted_at IS NULL
  LEFT JOIN property prop
    ON prop.id_property = cat.id_property
  WHERE lic.deleted_at IS NULL
    AND lic.is_active = 1
    AND (
      v_property_id IS NULL
      OR cat.id_property IS NULL
      OR cat.id_property = v_property_id
    )
  ORDER BY
    category_name,
    subcategory_name,
    lic.catalog_type,
    lic.item_name;
END $$

DELIMITER ;
