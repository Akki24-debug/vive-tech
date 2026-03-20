DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_definition_upsert` $$
CREATE PROCEDURE `sp_report_definition_upsert` (
  IN p_action VARCHAR(16),
  IN p_company_code VARCHAR(100),
  IN p_id_report_config BIGINT,
  IN p_report_key VARCHAR(64),
  IN p_report_name VARCHAR(160),
  IN p_report_type VARCHAR(32),
  IN p_line_item_type_scope VARCHAR(32),
  IN p_description TEXT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_report_id BIGINT;
  DECLARE v_report_key VARCHAR(64);
  DECLARE v_report_type VARCHAR(32);
  DECLARE v_line_item_scope VARCHAR(32);

  SET p_action = LOWER(TRIM(COALESCE(p_action, 'create')));
  IF p_action NOT IN ('create','update','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
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

  IF p_id_report_config IS NOT NULL AND p_id_report_config > 0 THEN
    SELECT id_report_config
      INTO v_report_id
    FROM report_config
    WHERE id_report_config = p_id_report_config
      AND id_company = v_company_id
    LIMIT 1;
  ELSEIF p_report_key IS NOT NULL AND TRIM(p_report_key) <> '' THEN
    SELECT id_report_config
      INTO v_report_id
    FROM report_config
    WHERE id_company = v_company_id
      AND report_key = TRIM(p_report_key)
    LIMIT 1;
  ELSE
    SET v_report_id = NULL;
  END IF;

  IF p_action = 'delete' THEN
    IF v_report_id IS NULL OR v_report_id = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Report not found';
    END IF;

    UPDATE report_config
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_report_config = v_report_id;

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
    WHERE rc.id_report_config = v_report_id;

    LEAVE proc;
  END IF;

  IF p_report_name IS NULL OR TRIM(p_report_name) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Report name is required';
  END IF;

  SET v_report_type = LOWER(TRIM(COALESCE(p_report_type, 'reservation')));
  IF v_report_type NOT IN ('reservation','line_item','property') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported report_type';
  END IF;

  SET v_line_item_scope = NULL;
  IF v_report_type = 'line_item' THEN
    SET v_line_item_scope = LOWER(TRIM(COALESCE(p_line_item_type_scope, 'all')));
    IF v_line_item_scope NOT IN ('sale_item','tax_item','payment','obligation','income','all') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported line_item_type_scope';
    END IF;
  END IF;

  SET v_report_key = LOWER(TRIM(COALESCE(p_report_key, '')));
  IF v_report_key = '' THEN
    SET v_report_key = LOWER(TRIM(p_report_name));
  END IF;

  SET v_report_key = REPLACE(v_report_key, ' ', '_');
  SET v_report_key = REPLACE(v_report_key, '-', '_');
  SET v_report_key = REPLACE(v_report_key, '/', '_');
  SET v_report_key = REPLACE(v_report_key, '.', '_');
  SET v_report_key = REPLACE(v_report_key, ',', '_');
  SET v_report_key = REPLACE(v_report_key, ':', '_');
  SET v_report_key = REPLACE(v_report_key, ';', '_');
  SET v_report_key = REPLACE(v_report_key, '(', '_');
  SET v_report_key = REPLACE(v_report_key, ')', '_');

  WHILE INSTR(v_report_key, '__') > 0 DO
    SET v_report_key = REPLACE(v_report_key, '__', '_');
  END WHILE;

  SET v_report_key = TRIM(BOTH '_' FROM v_report_key);
  IF v_report_key = '' THEN
    SET v_report_key = CONCAT('report_', UNIX_TIMESTAMP());
  END IF;

  IF v_report_id IS NULL OR v_report_id = 0 THEN
    IF EXISTS (
      SELECT 1
      FROM report_config rc
      WHERE rc.id_company = v_company_id
        AND rc.report_key = v_report_key
        AND rc.deleted_at IS NULL
      LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report_key already exists';
    END IF;

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
      v_report_key,
      TRIM(p_report_name),
      v_report_type,
      v_line_item_scope,
      p_description,
      NULL,
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    );

    SET v_report_id = LAST_INSERT_ID();
  ELSE
    IF EXISTS (
      SELECT 1
      FROM report_config rc
      WHERE rc.id_company = v_company_id
        AND rc.report_key = v_report_key
        AND rc.id_report_config <> v_report_id
        AND rc.deleted_at IS NULL
      LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report_key already exists';
    END IF;

    UPDATE report_config
       SET report_key = v_report_key,
           report_name = TRIM(p_report_name),
           report_type = v_report_type,
           line_item_type_scope = v_line_item_scope,
           description = p_description,
           is_active = 1,
           deleted_at = NULL,
           updated_at = NOW()
     WHERE id_report_config = v_report_id
       AND id_company = v_company_id;
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
  WHERE rc.id_report_config = v_report_id;
END $$

DELIMITER ;
