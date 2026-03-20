DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ota_ical_lodging_catalog_data` $$
CREATE PROCEDURE `sp_ota_ical_lodging_catalog_data` (
  IN p_company_code VARCHAR(100),
  IN p_id_property BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT DEFAULT 0;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT
    lic.id_line_item_catalog,
    lic.item_name,
    COALESCE(cat.category_name, '') AS category_name,
    lic.default_unit_price_cents
  FROM line_item_catalog lic
  JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
   AND cat.id_company = v_company_id
   AND cat.deleted_at IS NULL
   AND cat.is_active = 1
  WHERE lic.deleted_at IS NULL
    AND lic.is_active = 1
    AND lic.catalog_type = 'sale_item'
    AND (
      p_id_property IS NULL
      OR p_id_property <= 0
      OR cat.id_property = p_id_property
      OR cat.id_property IS NULL
    )
  ORDER BY category_name, lic.item_name;
END $$

DELIMITER ;
