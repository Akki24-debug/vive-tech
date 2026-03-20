DELIMITER $$

DROP PROCEDURE IF EXISTS sp_role_permission_sync $$
CREATE PROCEDURE sp_role_permission_sync(
  IN p_company_code VARCHAR(100),
  IN p_id_role BIGINT,
  IN p_permission_codes_csv TEXT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_role_property_id BIGINT;
  DECLARE v_role_company_id BIGINT;
  DECLARE v_codes_csv TEXT;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_id_role IS NULL OR p_id_role <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role id is required';
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

  SET v_codes_csv = REPLACE(TRIM(COALESCE(p_permission_codes_csv, '')), ' ', '');

  DELETE FROM role_permission
  WHERE id_role = p_id_role;

  IF v_codes_csv IS NOT NULL AND v_codes_csv <> '' THEN
    INSERT INTO role_permission (
      id_role,
      id_permission,
      allow,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      p_id_role,
      p.id_permission,
      1,
      1,
      NULL,
      NOW(),
      p_actor_user_id,
      NOW()
    FROM permission p
    WHERE p.deleted_at IS NULL
      AND p.is_active = 1
      AND FIND_IN_SET(p.code, v_codes_csv) > 0;
  END IF;

  SELECT
    rp.id_role_permission,
    rp.id_role,
    p.id_permission,
    p.code AS permission_code
  FROM role_permission rp
  JOIN permission p
    ON p.id_permission = rp.id_permission
  WHERE rp.id_role = p_id_role
    AND rp.deleted_at IS NULL
    AND rp.is_active = 1
  ORDER BY p.code;
END $$

DELIMITER ;

