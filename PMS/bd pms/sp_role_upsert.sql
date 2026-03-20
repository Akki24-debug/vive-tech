DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_role_upsert` $$
CREATE PROCEDURE `sp_role_upsert`(
  IN p_company_code VARCHAR(100),
  IN p_id_role BIGINT,
  IN p_property_code VARCHAR(100),
  IN p_name VARCHAR(120),
  IN p_description TEXT,
  IN p_is_system TINYINT,
  IN p_is_active TINYINT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT DEFAULT NULL;
  DECLARE v_role_property_id BIGINT DEFAULT NULL;
  DECLARE v_role_company_id BIGINT DEFAULT NULL;
  DECLARE v_property_code VARCHAR(100);

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_name IS NULL OR TRIM(p_name) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role name is required';
  END IF;

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
    AND c.deleted_at IS NULL
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_property_code = NULLIF(TRIM(COALESCE(p_property_code, '')), '');
  IF v_property_code IS NOT NULL THEN
    SELECT pr.id_property
      INTO v_property_id
    FROM property pr
    WHERE pr.code = v_property_code
      AND pr.id_company = v_company_id
      AND pr.deleted_at IS NULL
    LIMIT 1;

    IF v_property_id IS NULL OR v_property_id <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code for company';
    END IF;
  END IF;

  IF p_id_role IS NULL OR p_id_role <= 0 THEN
    INSERT INTO role (
      id_property,
      name,
      description,
      is_system,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_property_id,
      TRIM(p_name),
      p_description,
      COALESCE(p_is_system, 0),
      COALESCE(p_is_active, 1),
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    );
    SET p_id_role = LAST_INSERT_ID();
  ELSE
    SELECT r.id_property,
           pr.id_company
      INTO v_role_property_id,
           v_role_company_id
    FROM role r
    LEFT JOIN property pr
      ON pr.id_property = r.id_property
    WHERE r.id_role = p_id_role
      AND r.deleted_at IS NULL
    LIMIT 1;

    IF v_role_property_id IS NOT NULL AND v_role_company_id <> v_company_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role does not belong to company';
    END IF;

    UPDATE role
       SET id_property = v_property_id,
           name = TRIM(p_name),
           description = p_description,
           is_system = COALESCE(p_is_system, is_system),
           is_active = COALESCE(p_is_active, is_active),
           updated_at = NOW()
     WHERE id_role = p_id_role;
  END IF;

  SELECT
    r.id_role,
    r.name,
    r.description,
    r.id_property,
    pr.code AS property_code,
    r.is_system,
    r.is_active
  FROM role r
  LEFT JOIN property pr
    ON pr.id_property = r.id_property
  WHERE r.id_role = p_id_role
  LIMIT 1;
END $$

DELIMITER ;
