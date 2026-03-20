DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_definition_data` $$
CREATE PROCEDURE `sp_report_definition_data` (
  IN p_company_code VARCHAR(100),
  IN p_id_report_config BIGINT,
  IN p_report_key VARCHAR(64),
  IN p_include_inactive TINYINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_report_id BIGINT;
  DECLARE v_include_inactive TINYINT DEFAULT 0;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SET v_include_inactive = CASE WHEN COALESCE(p_include_inactive, 0) = 0 THEN 0 ELSE 1 END;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT
    rc.id_report_config,
    rc.id_company,
    rc.report_key,
    rc.report_name,
    rc.report_type,
    rc.line_item_type_scope,
    rc.description,
    rc.column_order,
    rc.is_active,
    rc.deleted_at,
    rc.created_at,
    rc.created_by,
    rc.updated_at
  FROM report_config rc
  WHERE rc.id_company = v_company_id
    AND (v_include_inactive = 1 OR (rc.is_active = 1 AND rc.deleted_at IS NULL))
  ORDER BY rc.report_name, rc.id_report_config;

  SET v_report_id = NULL;

  IF p_id_report_config IS NOT NULL AND p_id_report_config > 0 THEN
    SELECT rc.id_report_config
      INTO v_report_id
    FROM report_config rc
    WHERE rc.id_report_config = p_id_report_config
      AND rc.id_company = v_company_id
      AND (v_include_inactive = 1 OR (rc.is_active = 1 AND rc.deleted_at IS NULL))
    LIMIT 1;
  ELSEIF p_report_key IS NOT NULL AND TRIM(p_report_key) <> '' THEN
    SELECT rc.id_report_config
      INTO v_report_id
    FROM report_config rc
    WHERE rc.id_company = v_company_id
      AND rc.report_key = TRIM(p_report_key)
      AND (v_include_inactive = 1 OR (rc.is_active = 1 AND rc.deleted_at IS NULL))
    LIMIT 1;
  END IF;

  SELECT
    rc.id_report_config,
    rc.id_company,
    rc.report_key,
    rc.report_name,
    rc.report_type,
    rc.line_item_type_scope,
    rc.description,
    rc.column_order,
    rc.is_active,
    rc.deleted_at,
    rc.created_at,
    rc.created_by,
    rc.updated_at
  FROM report_config rc
  WHERE rc.id_report_config = v_report_id
  LIMIT 1;

  SELECT
    rcc.id_report_config_column,
    rcc.id_report_config,
    rcc.column_key,
    rcc.column_source,
    rcc.source_field_key,
    rcc.id_line_item_catalog,
    rcc.display_name,
    rcc.display_category,
    rcc.data_type,
    rcc.aggregation,
    rcc.format_hint,
    rcc.order_index,
    rcc.is_visible,
    rcc.is_filterable,
    rcc.filter_operator_default,
    rcc.legacy_role,
    rcc.is_active,
    rcc.deleted_at,
    rcc.created_at,
    rcc.created_by,
    rcc.updated_at
  FROM report_config_column rcc
  WHERE rcc.id_report_config = v_report_id
    AND (v_include_inactive = 1 OR (rcc.is_active = 1 AND rcc.deleted_at IS NULL))
  ORDER BY rcc.order_index, rcc.id_report_config_column;

  SELECT
    rcf.id_report_config_filter,
    rcf.id_report_config,
    rcf.filter_key,
    rcf.operator_key,
    rcf.value_text,
    rcf.value_from_text,
    rcf.value_to_text,
    rcf.value_list_text,
    rcf.logic_join,
    rcf.order_index,
    rcf.is_active,
    rcf.created_at,
    rcf.created_by,
    rcf.updated_at
  FROM report_config_filter rcf
  WHERE rcf.id_report_config = v_report_id
    AND (v_include_inactive = 1 OR rcf.is_active = 1)
  ORDER BY rcf.order_index, rcf.id_report_config_filter;
END $$

DELIMITER ;
