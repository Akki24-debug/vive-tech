DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_guest_upsert` $$
CREATE PROCEDURE `sp_guest_upsert` (
  IN p_email          VARCHAR(255),
  IN p_names          VARCHAR(255),
  IN p_last_name      VARCHAR(255),
  IN p_maiden_name    VARCHAR(255),
  IN p_phone          VARCHAR(100),
  IN p_language       VARCHAR(10),
  IN p_marketing_opt  TINYINT,
  IN p_blacklisted    TINYINT,
  IN p_notes          TEXT
)
proc:BEGIN
  DECLARE v_id_guest BIGINT;

  IF (p_email IS NULL OR p_email = '') AND (p_names IS NULL OR p_names = '') AND (p_phone IS NULL OR p_phone = '') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Guest name or phone is required';
  END IF;

  IF p_email IS NOT NULL AND p_email <> '' THEN
    SELECT id_guest INTO v_id_guest
    FROM guest
    WHERE email = p_email
    LIMIT 1;
  END IF;

  IF v_id_guest IS NULL THEN
    INSERT INTO guest (
      id_user,
      email,
      phone,
      names,
      last_name,
      maiden_name,
      full_name,
      language,
      marketing_opt_in,
      blacklisted,
      notes_internal,
      created_at,
      updated_at
    ) VALUES (
      NULL,
      NULLIF(p_email, ''),
      NULLIF(p_phone, ''),
      p_names,
      NULLIF(p_last_name, ''),
      NULLIF(p_maiden_name, ''),
      TRIM(CONCAT(p_names, ' ', COALESCE(p_last_name,''), ' ', COALESCE(p_maiden_name,''))),
      COALESCE(NULLIF(p_language,''), 'es'),
      COALESCE(p_marketing_opt, 0),
      COALESCE(p_blacklisted, 0),
      NULLIF(p_notes, ''),
      NOW(),
      NOW()
    );
    SET v_id_guest = LAST_INSERT_ID();
  ELSE
    UPDATE guest
    SET
      phone = COALESCE(NULLIF(p_phone,''), phone),
      names = COALESCE(NULLIF(p_names,''), names),
      last_name = COALESCE(NULLIF(p_last_name,''), last_name),
      maiden_name = COALESCE(NULLIF(p_maiden_name,''), maiden_name),
      full_name = TRIM(CONCAT(COALESCE(NULLIF(p_names,''), names), ' ', COALESCE(NULLIF(p_last_name,''), last_name), ' ', COALESCE(NULLIF(p_maiden_name,''), maiden_name))),
      language = COALESCE(NULLIF(p_language,''), language),
      marketing_opt_in = COALESCE(p_marketing_opt, marketing_opt_in),
      blacklisted = COALESCE(p_blacklisted, blacklisted),
      notes_internal = COALESCE(NULLIF(p_notes,''), notes_internal),
      updated_at = NOW()
    WHERE id_guest = v_id_guest;
  END IF;

  SELECT * FROM guest WHERE id_guest = v_id_guest;
END $$

DELIMITER ;
