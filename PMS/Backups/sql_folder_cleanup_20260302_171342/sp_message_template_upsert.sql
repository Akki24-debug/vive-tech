DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_message_template_upsert` $$
CREATE PROCEDURE `sp_message_template_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_template_code VARCHAR(100),
  IN p_title VARCHAR(255),
  IN p_body TEXT,
  IN p_is_active TINYINT,
  IN p_id_message_template BIGINT
)
proc:BEGIN
  DECLARE v_id_company BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_id_template BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_template_code IS NULL OR p_template_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template code is required';
  END IF;
  IF p_title IS NULL OR p_title = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template title is required';
  END IF;
  IF p_body IS NULL OR p_body = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template body is required';
  END IF;

  SELECT id_company
    INTO v_id_company
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_id_company IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_id_property = NULL;
  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property
      INTO v_id_property
    FROM property
    WHERE code = p_property_code
      AND id_company = v_id_company
      AND deleted_at IS NULL
    LIMIT 1;

    IF v_id_property IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
    END IF;
  END IF;

  SET v_id_template = NULL;
  IF p_id_message_template IS NOT NULL AND p_id_message_template > 0 THEN
    SELECT id_message_template
      INTO v_id_template
    FROM message_template
    WHERE id_message_template = p_id_message_template
      AND id_company = v_id_company
      AND deleted_at IS NULL
    LIMIT 1;
  ELSE
    SELECT id_message_template
      INTO v_id_template
    FROM message_template
    WHERE id_company = v_id_company
      AND code = p_template_code
      AND deleted_at IS NULL
    LIMIT 1;
  END IF;

  IF v_id_template IS NULL THEN
    INSERT INTO message_template (
      id_company,
      id_property,
      code,
      title,
      body,
      is_active,
      created_at,
      updated_at
    ) VALUES (
      v_id_company,
      v_id_property,
      p_template_code,
      p_title,
      p_body,
      COALESCE(p_is_active, 1),
      v_now,
      v_now
    );
    SET v_id_template = LAST_INSERT_ID();
  ELSE
    UPDATE message_template
    SET
      id_property = v_id_property,
      code = p_template_code,
      title = p_title,
      body = p_body,
      is_active = COALESCE(p_is_active, is_active),
      updated_at = v_now
    WHERE id_message_template = v_id_template;
  END IF;

  SELECT
    mt.id_message_template,
    mt.id_company,
    mt.id_property,
    mt.code AS template_code,
    mt.title,
    mt.body,
    mt.is_active
  FROM message_template mt
  WHERE mt.id_message_template = v_id_template
    AND mt.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
