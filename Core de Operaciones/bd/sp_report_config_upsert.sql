DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_config_upsert` $$
CREATE PROCEDURE `sp_report_config_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_report_key VARCHAR(64),
  IN p_report_name VARCHAR(120),
  IN p_column_order TEXT,
  IN p_lodging_ids TEXT,
  IN p_cleaning_id BIGINT,
  IN p_iva_id BIGINT,
  IN p_ish_id BIGINT,
  IN p_extra_ids TEXT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_report_id BIGINT;

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

  SELECT id_report_config INTO v_report_id
  FROM report_config
  WHERE id_company = v_company_id
    AND report_key = p_report_key
  LIMIT 1;

  IF v_report_id IS NULL OR v_report_id = 0 THEN
    INSERT INTO report_config (
      id_company,
      report_key,
      report_name,
      report_type,
      line_item_type_scope,
      description,
      column_order,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_company_id,
      p_report_key,
      COALESCE(NULLIF(p_report_name, ''), p_report_key),
      'reservation',
      NULL,
      NULL,
      p_column_order,
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    );
    SET v_report_id = LAST_INSERT_ID();
  ELSE
    UPDATE report_config
       SET report_name = COALESCE(NULLIF(p_report_name, ''), report_name),
           report_type = COALESCE(NULLIF(report_type, ''), 'reservation'),
           column_order = p_column_order,
           is_active = 1,
           deleted_at = NULL,
           updated_at = NOW()
     WHERE id_report_config = v_report_id;
  END IF;

  UPDATE report_config_column
     SET is_active = 0,
         deleted_at = NOW(),
         updated_at = NOW()
   WHERE id_report_config = v_report_id
     AND legacy_role IS NOT NULL;

  IF p_lodging_ids IS NOT NULL AND p_lodging_ids <> '' THEN
    INSERT INTO report_config_column (
      id_report_config,
      column_key,
      column_source,
      source_field_key,
      id_line_item_catalog,
      display_name,
      display_category,
      data_type,
      aggregation,
      format_hint,
      order_index,
      is_visible,
      is_filterable,
      filter_operator_default,
      legacy_role,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      v_report_id,
      CONCAT('catalog_', lic.id_line_item_catalog, '_lodging'),
      'line_item_catalog',
      NULL,
      lic.id_line_item_catalog,
      lic.item_name,
      'lodging',
      'money',
      'sum',
      NULL,
      150,
      1,
      1,
      'eq',
      'lodging',
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM line_item_catalog lic
    JOIN sale_item_category cat
      ON cat.id_sale_item_category = lic.id_category
     AND cat.deleted_at IS NULL
     AND cat.id_company = v_company_id
    WHERE lic.deleted_at IS NULL
      AND lic.is_active = 1
      AND lic.catalog_type IN ('sale_item','tax_rule')
      AND FIND_IN_SET(lic.id_line_item_catalog, p_lodging_ids)
    ON DUPLICATE KEY UPDATE
      display_name = VALUES(display_name),
      display_category = VALUES(display_category),
      data_type = VALUES(data_type),
      aggregation = VALUES(aggregation),
      order_index = VALUES(order_index),
      is_visible = 1,
      is_filterable = 1,
      filter_operator_default = 'eq',
      legacy_role = 'lodging',
      is_active = 1,
      deleted_at = NULL,
      updated_at = NOW();
  END IF;

  IF p_cleaning_id IS NOT NULL AND p_cleaning_id > 0 THEN
    INSERT INTO report_config_column (
      id_report_config,
      column_key,
      column_source,
      source_field_key,
      id_line_item_catalog,
      display_name,
      display_category,
      data_type,
      aggregation,
      format_hint,
      order_index,
      is_visible,
      is_filterable,
      filter_operator_default,
      legacy_role,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      v_report_id,
      CONCAT('catalog_', lic.id_line_item_catalog, '_cleaning'),
      'line_item_catalog',
      NULL,
      lic.id_line_item_catalog,
      lic.item_name,
      'cleaning',
      'money',
      'sum',
      NULL,
      200,
      1,
      1,
      'eq',
      'cleaning',
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM line_item_catalog lic
    WHERE lic.id_line_item_catalog = p_cleaning_id
      AND lic.deleted_at IS NULL
      AND lic.is_active = 1
    ON DUPLICATE KEY UPDATE
      display_name = VALUES(display_name),
      display_category = VALUES(display_category),
      data_type = VALUES(data_type),
      aggregation = VALUES(aggregation),
      order_index = VALUES(order_index),
      is_visible = 1,
      is_filterable = 1,
      filter_operator_default = 'eq',
      legacy_role = 'cleaning',
      is_active = 1,
      deleted_at = NULL,
      updated_at = NOW();
  END IF;

  IF p_iva_id IS NOT NULL AND p_iva_id > 0 THEN
    INSERT INTO report_config_column (
      id_report_config,
      column_key,
      column_source,
      source_field_key,
      id_line_item_catalog,
      display_name,
      display_category,
      data_type,
      aggregation,
      format_hint,
      order_index,
      is_visible,
      is_filterable,
      filter_operator_default,
      legacy_role,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      v_report_id,
      CONCAT('catalog_', lic.id_line_item_catalog, '_iva'),
      'line_item_catalog',
      NULL,
      lic.id_line_item_catalog,
      lic.item_name,
      'iva',
      'money',
      'sum',
      NULL,
      250,
      1,
      1,
      'eq',
      'iva',
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM line_item_catalog lic
    WHERE lic.id_line_item_catalog = p_iva_id
      AND lic.deleted_at IS NULL
      AND lic.is_active = 1
    ON DUPLICATE KEY UPDATE
      display_name = VALUES(display_name),
      display_category = VALUES(display_category),
      data_type = VALUES(data_type),
      aggregation = VALUES(aggregation),
      order_index = VALUES(order_index),
      is_visible = 1,
      is_filterable = 1,
      filter_operator_default = 'eq',
      legacy_role = 'iva',
      is_active = 1,
      deleted_at = NULL,
      updated_at = NOW();
  END IF;

  IF p_ish_id IS NOT NULL AND p_ish_id > 0 THEN
    INSERT INTO report_config_column (
      id_report_config,
      column_key,
      column_source,
      source_field_key,
      id_line_item_catalog,
      display_name,
      display_category,
      data_type,
      aggregation,
      format_hint,
      order_index,
      is_visible,
      is_filterable,
      filter_operator_default,
      legacy_role,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      v_report_id,
      CONCAT('catalog_', lic.id_line_item_catalog, '_ish'),
      'line_item_catalog',
      NULL,
      lic.id_line_item_catalog,
      lic.item_name,
      'ish',
      'money',
      'sum',
      NULL,
      300,
      1,
      1,
      'eq',
      'ish',
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM line_item_catalog lic
    WHERE lic.id_line_item_catalog = p_ish_id
      AND lic.deleted_at IS NULL
      AND lic.is_active = 1
    ON DUPLICATE KEY UPDATE
      display_name = VALUES(display_name),
      display_category = VALUES(display_category),
      data_type = VALUES(data_type),
      aggregation = VALUES(aggregation),
      order_index = VALUES(order_index),
      is_visible = 1,
      is_filterable = 1,
      filter_operator_default = 'eq',
      legacy_role = 'ish',
      is_active = 1,
      deleted_at = NULL,
      updated_at = NOW();
  END IF;

  IF p_extra_ids IS NOT NULL AND p_extra_ids <> '' THEN
    INSERT INTO report_config_column (
      id_report_config,
      column_key,
      column_source,
      source_field_key,
      id_line_item_catalog,
      display_name,
      display_category,
      data_type,
      aggregation,
      format_hint,
      order_index,
      is_visible,
      is_filterable,
      filter_operator_default,
      legacy_role,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      v_report_id,
      CONCAT('catalog_', lic.id_line_item_catalog, '_extra'),
      'line_item_catalog',
      NULL,
      lic.id_line_item_catalog,
      lic.item_name,
      'extra',
      'money',
      'sum',
      NULL,
      400,
      1,
      1,
      'eq',
      'extra',
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM line_item_catalog lic
    JOIN sale_item_category cat
      ON cat.id_sale_item_category = lic.id_category
     AND cat.deleted_at IS NULL
     AND cat.id_company = v_company_id
    WHERE lic.deleted_at IS NULL
      AND lic.is_active = 1
      AND lic.catalog_type IN ('sale_item','tax_rule')
      AND FIND_IN_SET(lic.id_line_item_catalog, p_extra_ids)
    ON DUPLICATE KEY UPDATE
      display_name = VALUES(display_name),
      display_category = VALUES(display_category),
      data_type = VALUES(data_type),
      aggregation = VALUES(aggregation),
      order_index = VALUES(order_index),
      is_visible = 1,
      is_filterable = 1,
      filter_operator_default = 'eq',
      legacy_role = 'extra',
      is_active = 1,
      deleted_at = NULL,
      updated_at = NOW();
  END IF;

  SELECT v_report_id AS id_report_config;
END $$

DELIMITER ;
