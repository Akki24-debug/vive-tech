DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_app_user_upsert` $$
CREATE PROCEDURE `sp_app_user_upsert` (
  IN p_company_code  VARCHAR(100),
  IN p_id_user       BIGINT,
  IN p_email         VARCHAR(255),
  IN p_password      VARCHAR(255),
  IN p_names         VARCHAR(255),
  IN p_last_name     VARCHAR(255),
  IN p_maiden_name   VARCHAR(255),
  IN p_phone         VARCHAR(100),
  IN p_locale        VARCHAR(20),
  IN p_timezone      VARCHAR(64),
  IN p_is_owner      TINYINT,
  IN p_is_active     TINYINT,
  IN p_display_name  VARCHAR(255),
  IN p_notes         TEXT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_existing_id BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();
  DECLARE v_full_name VARCHAR(600);

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_email IS NULL OR p_email = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email is required';
  END IF;
  IF p_names IS NULL OR p_names = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Names are required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  /* Check for conflicting email inside the company */
  SELECT id_user
    INTO v_existing_id
  FROM app_user
  WHERE email = p_email
    AND id_company = v_company_id
    AND deleted_at IS NULL
    AND (p_id_user IS NULL OR p_id_user = 0 OR id_user <> p_id_user)
  LIMIT 1;

  IF v_existing_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email already in use for this company';
  END IF;

  SET v_full_name = TRIM(CONCAT(
    COALESCE(NULLIF(p_names, ''), ''),
    ' ',
    COALESCE(NULLIF(p_last_name, ''), ''),
    ' ',
    COALESCE(NULLIF(p_maiden_name, ''), '')
  ));

  IF p_id_user IS NULL OR p_id_user = 0 THEN
    INSERT INTO app_user (
      id_company,
      id_reg,
      email,
      password_hash,
      phone,
      names,
      last_name,
      maiden_name,
      full_name,
      display_name,
      locale,
      timezone,
      is_owner,
      is_active,
      notes,
      created_at,
      updated_at
    ) VALUES (
      v_company_id,
      COALESCE(NULLIF(p_actor_user_id, 0), NULL),
      p_email,
      NULLIF(p_password, ''),
      NULLIF(p_phone, ''),
      p_names,
      NULLIF(p_last_name, ''),
      NULLIF(p_maiden_name, ''),
      NULLIF(v_full_name, ''),
      COALESCE(NULLIF(p_display_name, ''), NULLIF(v_full_name, ''), p_email),
      COALESCE(NULLIF(p_locale, ''), 'es-MX'),
      COALESCE(NULLIF(p_timezone, ''), 'America/Mexico_City'),
      COALESCE(p_is_owner, 0),
      COALESCE(p_is_active, 1),
      NULLIF(p_notes, ''),
      v_now,
      v_now
    );
    SET p_id_user = LAST_INSERT_ID();
  ELSE
    UPDATE app_user
    SET
      email = p_email,
      password_hash = CASE WHEN p_password IS NOT NULL AND p_password <> '' THEN p_password ELSE password_hash END,
      phone = COALESCE(NULLIF(p_phone, ''), phone),
      names = p_names,
      last_name = NULLIF(p_last_name, ''),
      maiden_name = NULLIF(p_maiden_name, ''),
      full_name = NULLIF(v_full_name, ''),
      display_name = COALESCE(NULLIF(p_display_name, ''), NULLIF(v_full_name, ''), p_email),
      locale = COALESCE(NULLIF(p_locale, ''), locale),
      timezone = COALESCE(NULLIF(p_timezone, ''), timezone),
      is_owner = COALESCE(p_is_owner, is_owner),
      is_active = COALESCE(p_is_active, is_active),
      notes = COALESCE(NULLIF(p_notes, ''), notes),
      updated_at = v_now
    WHERE id_user = p_id_user
      AND id_company = v_company_id
      AND deleted_at IS NULL;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found or belongs to another company';
    END IF;
  END IF;

  SELECT
    au.id_user,
    au.email,
    au.names,
    au.last_name,
    au.maiden_name,
    au.display_name,
    au.phone,
    au.locale,
    au.timezone,
    au.is_owner,
    au.is_active,
    au.notes
  FROM app_user au
  WHERE au.id_user = p_id_user
    AND au.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
