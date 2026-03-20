DELIMITER $$

DROP PROCEDURE IF EXISTS sp_user_role_sync $$
CREATE PROCEDURE sp_user_role_sync(
  IN p_company_code VARCHAR(100),
  IN p_user_id BIGINT,
  IN p_role_ids_csv TEXT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_user_company_id BIGINT;
  DECLARE v_role_ids_csv TEXT;

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

  SET v_role_ids_csv = REPLACE(TRIM(COALESCE(p_role_ids_csv, '')), ' ', '');

  DELETE ur
  FROM user_role ur
  JOIN role r
    ON r.id_role = ur.id_role
  LEFT JOIN property pr
    ON pr.id_property = r.id_property
  WHERE ur.id_user = p_user_id
    AND (
      r.id_property IS NULL
      OR pr.id_company = v_company_id
    );

  IF v_role_ids_csv IS NOT NULL AND v_role_ids_csv <> '' THEN
    INSERT INTO user_role (
      id_user,
      id_role,
      notes,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      p_user_id,
      r.id_role,
      NULL,
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM role r
    LEFT JOIN property pr
      ON pr.id_property = r.id_property
    WHERE r.deleted_at IS NULL
      AND r.is_active = 1
      AND (
        r.id_property IS NULL
        OR (
          pr.id_company = v_company_id
          AND pr.deleted_at IS NULL
        )
      )
      AND FIND_IN_SET(CAST(r.id_role AS CHAR), v_role_ids_csv) > 0;
  END IF;

  SELECT
    ur.id_user_role,
    ur.id_user,
    ur.id_role,
    r.name AS role_name,
    pr.code AS property_code
  FROM user_role ur
  JOIN role r
    ON r.id_role = ur.id_role
  LEFT JOIN property pr
    ON pr.id_property = r.id_property
  WHERE ur.id_user = p_user_id
    AND ur.deleted_at IS NULL
    AND ur.is_active = 1
    AND (
      r.id_property IS NULL
      OR (
        pr.id_company = v_company_id
        AND pr.deleted_at IS NULL
      )
    )
  ORDER BY r.name, pr.code;
END $$

DELIMITER ;

