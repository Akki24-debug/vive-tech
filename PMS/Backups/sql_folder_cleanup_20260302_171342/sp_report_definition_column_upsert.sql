DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_definition_column_upsert` $$
CREATE PROCEDURE `sp_report_definition_column_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_report_config_column BIGINT,
  IN p_id_report_config BIGINT,
  IN p_column_key VARCHAR(160),
  IN p_column_source VARCHAR(32),
  IN p_source_field_key VARCHAR(120),
  IN p_id_line_item_catalog BIGINT,
  IN p_display_name VARCHAR(160),
  IN p_display_category VARCHAR(80),
  IN p_data_type VARCHAR(32),
  IN p_aggregation VARCHAR(32),
  IN p_format_hint VARCHAR(64),
  IN p_order_index INT,
  IN p_is_visible TINYINT,
  IN p_is_filterable TINYINT,
  IN p_filter_operator_default VARCHAR(32),
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_col_id BIGINT;
  DECLARE v_column_source VARCHAR(32);
  DECLARE v_report_type VARCHAR(32);
  DECLARE v_column_key VARCHAR(160);

  SET p_action = LOWER(TRIM(COALESCE(p_action, 'create')));
  IF p_action NOT IN ('create','update','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  IF p_id_report_config IS NULL OR p_id_report_config <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'id_report_config is required';
  END IF;

  SELECT report_type
    INTO v_report_type
  FROM report_config
  WHERE id_report_config = p_id_report_config
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_report_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Report not found';
  END IF;

  SET v_col_id = NULL;
  IF p_id_report_config_column IS NOT NULL AND p_id_report_config_column > 0 THEN
    SELECT id_report_config_column
      INTO v_col_id
    FROM report_config_column
    WHERE id_report_config_column = p_id_report_config_column
      AND id_report_config = p_id_report_config
    LIMIT 1;
  END IF;

  IF p_action = 'delete' THEN
    IF v_col_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Column not found';
    END IF;

    UPDATE report_config_column
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_report_config_column = v_col_id;

    SELECT
      id_report_config_column,
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
    FROM report_config_column
    WHERE id_report_config_column = v_col_id;

    LEAVE proc;
  END IF;

  SET v_column_source = LOWER(TRIM(COALESCE(p_column_source, 'field')));
  IF v_column_source NOT IN ('field','line_item_catalog') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported column_source';
  END IF;

  IF v_column_source = 'field' THEN
    IF p_source_field_key IS NULL OR TRIM(p_source_field_key) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source_field_key is required for field columns';
    END IF;

    IF NOT EXISTS (
      SELECT 1
      FROM report_field_catalog rfc
      WHERE rfc.report_type = v_report_type
        AND rfc.field_key = TRIM(p_source_field_key)
        AND rfc.is_active = 1
      LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source_field_key is not valid for report_type';
    END IF;
  ELSE
    IF p_id_line_item_catalog IS NULL OR p_id_line_item_catalog <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'id_line_item_catalog is required for line_item_catalog columns';
    END IF;

    IF NOT EXISTS (
      SELECT 1
      FROM line_item_catalog lic
      WHERE lic.id_line_item_catalog = p_id_line_item_catalog
        AND lic.deleted_at IS NULL
        AND lic.is_active = 1
      LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line_item_catalog not found';
    END IF;
  END IF;

  SET v_column_key = LOWER(TRIM(COALESCE(p_column_key, '')));
  IF v_column_key = '' THEN
    IF v_column_source = 'field' THEN
      SET v_column_key = LOWER(TRIM(p_source_field_key));
    ELSE
      SET v_column_key = CONCAT('catalog_', p_id_line_item_catalog);
    END IF;
  END IF;

  SET v_column_key = REPLACE(v_column_key, ' ', '_');
  SET v_column_key = REPLACE(v_column_key, '-', '_');
  SET v_column_key = REPLACE(v_column_key, '/', '_');
  WHILE INSTR(v_column_key, '__') > 0 DO
    SET v_column_key = REPLACE(v_column_key, '__', '_');
  END WHILE;

  IF v_col_id IS NULL THEN
    SELECT id_report_config_column
      INTO v_col_id
    FROM report_config_column
    WHERE id_report_config = p_id_report_config
      AND column_key = v_column_key
    LIMIT 1;
  END IF;

  IF v_col_id IS NULL OR v_col_id = 0 THEN
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
    ) VALUES (
      p_id_report_config,
      v_column_key,
      v_column_source,
      CASE WHEN v_column_source = 'field' THEN TRIM(p_source_field_key) ELSE NULL END,
      CASE WHEN v_column_source = 'line_item_catalog' THEN p_id_line_item_catalog ELSE NULL END,
      COALESCE(NULLIF(TRIM(p_display_name), ''), v_column_key),
      NULLIF(TRIM(COALESCE(p_display_category, '')), ''),
      COALESCE(NULLIF(TRIM(p_data_type), ''), CASE WHEN v_column_source = 'line_item_catalog' THEN 'money' ELSE 'text' END),
      COALESCE(NULLIF(TRIM(p_aggregation), ''), CASE WHEN v_column_source = 'line_item_catalog' THEN 'sum' ELSE 'none' END),
      NULLIF(TRIM(COALESCE(p_format_hint, '')), ''),
      CASE WHEN p_order_index IS NULL OR p_order_index < 1 THEN 1 ELSE p_order_index END,
      CASE WHEN COALESCE(p_is_visible, 1) = 0 THEN 0 ELSE 1 END,
      CASE WHEN COALESCE(p_is_filterable, 1) = 0 THEN 0 ELSE 1 END,
      NULLIF(TRIM(COALESCE(p_filter_operator_default, '')), ''),
      NULL,
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    );

    SET v_col_id = LAST_INSERT_ID();
  ELSE
    UPDATE report_config_column
       SET column_key = v_column_key,
           column_source = v_column_source,
           source_field_key = CASE WHEN v_column_source = 'field' THEN TRIM(p_source_field_key) ELSE NULL END,
           id_line_item_catalog = CASE WHEN v_column_source = 'line_item_catalog' THEN p_id_line_item_catalog ELSE NULL END,
           display_name = COALESCE(NULLIF(TRIM(p_display_name), ''), display_name),
           display_category = NULLIF(TRIM(COALESCE(p_display_category, display_category, '')), ''),
           data_type = COALESCE(NULLIF(TRIM(p_data_type), ''), data_type),
           aggregation = COALESCE(NULLIF(TRIM(p_aggregation), ''), aggregation),
           format_hint = NULLIF(TRIM(COALESCE(p_format_hint, format_hint, '')), ''),
           order_index = CASE WHEN p_order_index IS NULL OR p_order_index < 1 THEN order_index ELSE p_order_index END,
           is_visible = CASE WHEN COALESCE(p_is_visible, is_visible) = 0 THEN 0 ELSE 1 END,
           is_filterable = CASE WHEN COALESCE(p_is_filterable, is_filterable) = 0 THEN 0 ELSE 1 END,
           filter_operator_default = NULLIF(TRIM(COALESCE(p_filter_operator_default, filter_operator_default, '')), ''),
           is_active = 1,
           deleted_at = NULL,
           updated_at = NOW()
     WHERE id_report_config_column = v_col_id;
  END IF;

  SELECT
    id_report_config_column,
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
  FROM report_config_column
  WHERE id_report_config_column = v_col_id;
END $$

DELIMITER ;
