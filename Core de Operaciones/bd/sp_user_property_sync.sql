DELIMITER $$

DROP PROCEDURE IF EXISTS sp_user_property_sync $$
CREATE PROCEDURE sp_user_property_sync(
  IN p_company_code VARCHAR(100),
  IN p_user_id BIGINT,
  IN p_property_codes_csv TEXT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_user_company_id BIGINT;
  DECLARE v_codes_csv TEXT;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_user_id IS NULL OR p_user_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User id is required';
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

  SELECT au.id_company
    INTO v_user_company_id
  FROM app_user au
  WHERE au.id_user = p_user_id
    AND au.deleted_at IS NULL
  LIMIT 1;

  IF v_user_company_id IS NULL OR v_user_company_id <> v_company_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User does not belong to company';
  END IF;

  SET v_codes_csv = REPLACE(TRIM(COALESCE(p_property_codes_csv, '')), ' ', '');

  DELETE up
  FROM user_property up
  JOIN property pr
    ON pr.id_property = up.id_property
  WHERE up.id_user = p_user_id
    AND pr.id_company = v_company_id;

  IF v_codes_csv IS NOT NULL AND v_codes_csv <> '' THEN
    INSERT INTO user_property (
      id_user,
      id_property,
      is_primary,
      title,
      notes,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      p_user_id,
      pr.id_property,
      0,
      NULL,
      NULL,
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM property pr
    WHERE pr.id_company = v_company_id
      AND pr.deleted_at IS NULL
      AND FIND_IN_SET(pr.code, v_codes_csv) > 0;
  END IF;

  SELECT
    up.id_user_property,
    up.id_user,
    up.id_property,
    pr.code AS property_code,
    pr.name AS property_name
  FROM user_property up
  JOIN property pr
    ON pr.id_property = up.id_property
   AND pr.id_company = v_company_id
  WHERE up.id_user = p_user_id
    AND up.deleted_at IS NULL
    AND up.is_active = 1
  ORDER BY pr.order_index, pr.name;
END $$

DELIMITER ;

