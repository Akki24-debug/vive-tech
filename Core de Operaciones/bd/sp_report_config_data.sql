DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_config_data` $$
CREATE PROCEDURE `sp_report_config_data` (
  IN p_company_code VARCHAR(100),
  IN p_report_key VARCHAR(64)
)
BEGIN
  DECLARE v_company_id BIGINT;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_report_key IS NULL OR p_report_key = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Report key is required';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT
    rc.id_report_config,
    rc.report_name,
    rc.report_type,
    rc.line_item_type_scope,
    rc.description,
    rc.column_order,
    rc.report_key,
    rc.is_active
  FROM report_config rc
  WHERE rc.id_company = v_company_id
    AND rc.report_key = p_report_key
    AND rc.deleted_at IS NULL
    AND rc.is_active = 1
  LIMIT 1;

  SELECT
    rcc.id_line_item_catalog AS id_sale_item_catalog,
    rcc.legacy_role AS role
  FROM report_config_column rcc
  JOIN report_config rc
    ON rc.id_report_config = rcc.id_report_config
  WHERE rc.id_company = v_company_id
    AND rc.report_key = p_report_key
    AND rc.deleted_at IS NULL
    AND rc.is_active = 1
    AND rcc.deleted_at IS NULL
    AND rcc.is_active = 1
    AND rcc.legacy_role IS NOT NULL
  ORDER BY rcc.order_index, rcc.id_report_config_column;
END $$

DELIMITER ;
