DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_definition_filter_upsert` $$
CREATE PROCEDURE `sp_report_definition_filter_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_report_config_filter BIGINT,
  IN p_id_report_config BIGINT,
  IN p_filter_key VARCHAR(160),
  IN p_operator_key VARCHAR(32),
  IN p_value_text TEXT,
  IN p_value_from_text VARCHAR(255),
  IN p_value_to_text VARCHAR(255),
  IN p_value_list_text TEXT,
  IN p_logic_join VARCHAR(8),
  IN p_order_index INT,
  IN p_is_active TINYINT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_filter_id BIGINT;
  DECLARE v_report_type VARCHAR(32);
  DECLARE v_operator VARCHAR(32);
  DECLARE v_logic VARCHAR(8);

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

  SET v_filter_id = NULL;
  IF p_id_report_config_filter IS NOT NULL AND p_id_report_config_filter > 0 THEN
    SELECT id_report_config_filter
      INTO v_filter_id
    FROM report_config_filter
    WHERE id_report_config_filter = p_id_report_config_filter
      AND id_report_config = p_id_report_config
    LIMIT 1;
  END IF;

  IF p_action = 'delete' THEN
    IF v_filter_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Filter not found';
    END IF;

    UPDATE report_config_filter
       SET is_active = 0,
           updated_at = NOW()
     WHERE id_report_config_filter = v_filter_id;

    SELECT
      id_report_config_filter,
      id_report_config,
      filter_key,
      operator_key,
      value_text,
      value_from_text,
      value_to_text,
      value_list_text,
      logic_join,
      order_index,
      is_active,
      created_at,
      created_by,
      updated_at
    FROM report_config_filter
    WHERE id_report_config_filter = v_filter_id;

    LEAVE proc;
  END IF;

  IF p_filter_key IS NULL OR TRIM(p_filter_key) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'filter_key is required';
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM report_config_column rcc
    WHERE rcc.id_report_config = p_id_report_config
      AND rcc.column_key = TRIM(p_filter_key)
      AND rcc.is_active = 1
      AND rcc.deleted_at IS NULL
    LIMIT 1
  ) AND NOT EXISTS (
    SELECT 1
    FROM report_field_catalog rfc
    WHERE rfc.report_type = v_report_type
      AND rfc.field_key = TRIM(p_filter_key)
      AND rfc.is_active = 1
    LIMIT 1
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'filter_key is not valid for report';
  END IF;

  SET v_operator = LOWER(TRIM(COALESCE(p_operator_key, 'eq')));
  IF v_operator NOT IN ('eq','neq','gt','gte','lt','lte','contains','between','is_null','is_not_null','in') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported operator_key';
  END IF;

  SET v_logic = UPPER(TRIM(COALESCE(p_logic_join, 'AND')));
  IF v_logic NOT IN ('AND','OR') THEN
    SET v_logic = 'AND';
  END IF;

  IF v_filter_id IS NULL OR v_filter_id = 0 THEN
    INSERT INTO report_config_filter (
      id_report_config,
      filter_key,
      operator_key,
      value_text,
      value_from_text,
      value_to_text,
      value_list_text,
      logic_join,
      order_index,
      is_active,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      p_id_report_config,
      TRIM(p_filter_key),
      v_operator,
      p_value_text,
      p_value_from_text,
      p_value_to_text,
      p_value_list_text,
      v_logic,
      CASE WHEN p_order_index IS NULL OR p_order_index < 1 THEN 1 ELSE p_order_index END,
      CASE WHEN COALESCE(p_is_active, 1) = 0 THEN 0 ELSE 1 END,
      NOW(),
      p_actor_user_id,
      NOW()
    );

    SET v_filter_id = LAST_INSERT_ID();
  ELSE
    UPDATE report_config_filter
       SET filter_key = TRIM(p_filter_key),
           operator_key = v_operator,
           value_text = p_value_text,
           value_from_text = p_value_from_text,
           value_to_text = p_value_to_text,
           value_list_text = p_value_list_text,
           logic_join = v_logic,
           order_index = CASE WHEN p_order_index IS NULL OR p_order_index < 1 THEN order_index ELSE p_order_index END,
           is_active = CASE WHEN COALESCE(p_is_active, is_active) = 0 THEN 0 ELSE 1 END,
           updated_at = NOW()
     WHERE id_report_config_filter = v_filter_id;
  END IF;

  SELECT
    id_report_config_filter,
    id_report_config,
    filter_key,
    operator_key,
    value_text,
    value_from_text,
    value_to_text,
    value_list_text,
    logic_join,
    order_index,
    is_active,
    created_at,
    created_by,
    updated_at
  FROM report_config_filter
  WHERE id_report_config_filter = v_filter_id;
END $$

DELIMITER ;
