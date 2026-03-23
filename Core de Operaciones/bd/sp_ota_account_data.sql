DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ota_account_data` $$
CREATE PROCEDURE `sp_ota_account_data` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_include_inactive TINYINT,
  IN p_id_ota_account BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'company code is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unknown company';
  END IF;

  IF p_property_code IS NOT NULL AND TRIM(p_property_code) <> '' THEN
    SELECT id_property
      INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;

    IF v_property_id IS NULL OR v_property_id <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unknown property';
    END IF;
  ELSE
    SET v_property_id = NULL;
  END IF;

  SELECT
    oa.id_ota_account,
    oa.id_company,
    oa.id_property,
    p.code AS property_code,
    p.name AS property_name,
    oa.platform,
    oa.ota_name,
    oa.external_code,
    oa.contact_email,
    oa.timezone,
    oa.notes,
    oa.id_service_fee_payment_catalog,
    COALESCE(sfp.item_name, '') AS service_fee_payment_catalog_name,
    oa.is_active,
    oa.deleted_at,
    oa.created_at,
    oa.updated_at,
    COALESCE(SUM(
      CASE
        WHEN oalc.deleted_at IS NULL AND oalc.is_active = 1 THEN 1
        ELSE 0
      END
    ), 0) AS lodging_catalog_count
  FROM ota_account oa
  JOIN property p
    ON p.id_property = oa.id_property
   AND p.deleted_at IS NULL
  LEFT JOIN ota_account_lodging_catalog oalc
    ON oalc.id_ota_account = oa.id_ota_account
  LEFT JOIN line_item_catalog sfp
    ON sfp.id_line_item_catalog = oa.id_service_fee_payment_catalog
   AND sfp.deleted_at IS NULL
  WHERE oa.id_company = v_company_id
    AND (v_property_id IS NULL OR oa.id_property = v_property_id)
    AND (p_id_ota_account IS NULL OR p_id_ota_account <= 0 OR oa.id_ota_account = p_id_ota_account)
    AND (
      COALESCE(p_include_inactive, 0) <> 0
      OR (oa.deleted_at IS NULL AND oa.is_active = 1)
    )
  GROUP BY
    oa.id_ota_account,
    oa.id_company,
    oa.id_property,
    p.code,
    p.name,
    oa.platform,
    oa.ota_name,
    oa.external_code,
    oa.contact_email,
    oa.timezone,
    oa.notes,
    oa.id_service_fee_payment_catalog,
    sfp.item_name,
    oa.is_active,
    oa.deleted_at,
    oa.created_at,
    oa.updated_at
  ORDER BY p.code, oa.platform, oa.ota_name, oa.id_ota_account;

  SELECT
    oa.id_ota_account,
    oa.id_property,
    p.code AS property_code,
    oalc.id_line_item_catalog,
    lic.item_name,
    cat.category_name,
    oalc.sort_order
  FROM ota_account oa
  JOIN property p
    ON p.id_property = oa.id_property
   AND p.deleted_at IS NULL
  JOIN ota_account_lodging_catalog oalc
    ON oalc.id_ota_account = oa.id_ota_account
   AND oalc.deleted_at IS NULL
   AND oalc.is_active = 1
  JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = oalc.id_line_item_catalog
   AND lic.deleted_at IS NULL
  LEFT JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
  WHERE oa.id_company = v_company_id
    AND (v_property_id IS NULL OR oa.id_property = v_property_id)
    AND (p_id_ota_account IS NULL OR p_id_ota_account <= 0 OR oa.id_ota_account = p_id_ota_account)
    AND (
      COALESCE(p_include_inactive, 0) <> 0
      OR (oa.deleted_at IS NULL AND oa.is_active = 1)
    )
  ORDER BY p.code, oa.ota_name, oalc.sort_order, cat.category_name, lic.item_name;
END $$

DELIMITER ;
