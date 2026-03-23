DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_message_template_upsert` $$
CREATE PROCEDURE `sp_message_template_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_template_code VARCHAR(100),
  IN p_title VARCHAR(255),
  IN p_body TEXT,
  IN p_category VARCHAR(64),
  IN p_sort_order INT,
  IN p_channel VARCHAR(32),
  IN p_is_trackable TINYINT,
  IN p_is_required TINYINT,
  IN p_id_sale_item_catalog BIGINT,
  IN p_is_active TINYINT,
  IN p_actor_user_id BIGINT,
  IN p_id_message_template BIGINT
)
proc:BEGIN
  DECLARE v_id_company BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_id_template BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;
  DECLARE v_category VARCHAR(64) DEFAULT 'general';
  DECLARE v_channel VARCHAR(32) DEFAULT 'whatsapp';
  DECLARE v_is_trackable TINYINT DEFAULT 0;
  DECLARE v_is_required TINYINT DEFAULT 0;
  DECLARE v_sale_item_catalog BIGINT DEFAULT NULL;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_actor_user_id IS NULL OR p_actor_user_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor user id is required';
  END IF;
  IF p_template_code IS NULL OR TRIM(p_template_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template code is required';
  END IF;
  IF p_title IS NULL OR TRIM(p_title) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template title is required';
  END IF;
  IF p_body IS NULL OR TRIM(p_body) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template body is required';
  END IF;

  SELECT id_company
    INTO v_id_company
  FROM company
  WHERE code = p_company_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_company IS NULL OR v_id_company <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_id_property = NULL;
  SET v_property_code = NULLIF(TRIM(COALESCE(p_property_code, '')), '');
  IF v_property_code IS NOT NULL THEN
    SELECT id_property, code
      INTO v_id_property, v_property_code
    FROM property
    WHERE code = v_property_code
      AND id_company = v_id_company
      AND deleted_at IS NULL
    LIMIT 1;

    IF v_id_property IS NULL OR v_id_property <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
    END IF;
  END IF;

  CALL sp_authz_assert(
    p_company_code,
    p_actor_user_id,
    'messages.template_edit',
    v_property_code,
    NULL
  );

  SET v_category = LOWER(TRIM(COALESCE(NULLIF(p_category, ''), 'general')));
  SET v_channel = LOWER(TRIM(COALESCE(NULLIF(p_channel, ''), 'whatsapp')));
  SET v_is_trackable = CASE WHEN COALESCE(p_is_trackable, 0) = 1 OR COALESCE(p_is_required, 0) = 1 THEN 1 ELSE 0 END;
  SET v_is_required = CASE WHEN COALESCE(p_is_required, 0) = 1 THEN 1 ELSE 0 END;
  IF v_channel = '' THEN
    SET v_channel = 'whatsapp';
  END IF;

  SET v_sale_item_catalog = NULL;
  IF p_id_sale_item_catalog IS NOT NULL AND p_id_sale_item_catalog > 0 THEN
    SELECT lic.id_line_item_catalog
      INTO v_sale_item_catalog
    FROM line_item_catalog lic
    JOIN sale_item_category cat
      ON cat.id_sale_item_category = lic.id_category
     AND cat.id_company = v_id_company
     AND cat.deleted_at IS NULL
     AND cat.is_active = 1
    WHERE lic.id_line_item_catalog = p_id_sale_item_catalog
      AND lic.catalog_type = 'sale_item'
      AND lic.deleted_at IS NULL
      AND lic.is_active = 1
      AND (
        v_id_property IS NULL
        OR cat.id_property IS NULL
        OR cat.id_property = v_id_property
      )
    LIMIT 1;

    IF v_sale_item_catalog IS NULL OR v_sale_item_catalog <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid sale item catalog for template';
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
      AND code = TRIM(p_template_code)
      AND deleted_at IS NULL
    LIMIT 1;
  END IF;

  IF v_id_template IS NULL OR v_id_template <= 0 THEN
    INSERT INTO message_template (
      id_company,
      id_property,
      code,
      title,
      body,
      category,
      sort_order,
      channel,
      is_trackable,
      is_required,
      id_sale_item_catalog,
      is_active,
      created_at,
      updated_at
    ) VALUES (
      v_id_company,
      v_id_property,
      TRIM(p_template_code),
      TRIM(p_title),
      p_body,
      v_category,
      COALESCE(p_sort_order, 0),
      v_channel,
      v_is_trackable,
      v_is_required,
      v_sale_item_catalog,
      COALESCE(p_is_active, 1),
      v_now,
      v_now
    );
    SET v_id_template = LAST_INSERT_ID();
  ELSE
    UPDATE message_template
    SET
      id_property = v_id_property,
      code = TRIM(p_template_code),
      title = TRIM(p_title),
      body = p_body,
      category = v_category,
      sort_order = COALESCE(p_sort_order, 0),
      channel = v_channel,
      is_trackable = v_is_trackable,
      is_required = v_is_required,
      id_sale_item_catalog = v_sale_item_catalog,
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
    mt.category,
    mt.sort_order,
    mt.channel,
    mt.is_trackable,
    mt.is_required,
    mt.id_sale_item_catalog,
    mt.is_active
  FROM message_template mt
  WHERE mt.id_message_template = v_id_template
    AND mt.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
