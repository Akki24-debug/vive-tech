DELIMITER $$

DROP PROCEDURE IF EXISTS sp_access_context_data $$
CREATE PROCEDURE sp_access_context_data(
  IN p_company_code VARCHAR(100),
  IN p_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_is_owner TINYINT DEFAULT 0;
  DECLARE v_mode VARCHAR(16) DEFAULT 'audit';
  DECLARE v_user_exists TINYINT DEFAULT 0;

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

  SELECT COUNT(*)
    INTO v_user_exists
  FROM app_user au
  WHERE au.id_user = p_user_id
    AND au.id_company = v_company_id
    AND au.deleted_at IS NULL
    AND au.is_active = 1;

  IF v_user_exists = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found or inactive for company';
  END IF;

  SELECT CASE WHEN COALESCE(au.is_owner, 0) = 1 THEN 1 ELSE 0 END
    INTO v_is_owner
  FROM app_user au
  WHERE au.id_user = p_user_id
    AND au.id_company = v_company_id
    AND au.deleted_at IS NULL
  LIMIT 1;

  SELECT LOWER(TRIM(COALESCE(ac.authz_mode, 'audit')))
    INTO v_mode
  FROM pms_authz_config ac
  WHERE ac.id_company = v_company_id
  LIMIT 1;

  IF v_mode NOT IN ('audit', 'enforce') THEN
    SET v_mode = 'audit';
  END IF;

  IF v_is_owner = 1 THEN
    SELECT p.code
    FROM permission p
    WHERE p.deleted_at IS NULL
      AND p.is_active = 1
    ORDER BY p.code;
  ELSE
    SELECT DISTINCT p.code
    FROM user_role ur
    JOIN role r
      ON r.id_role = ur.id_role
     AND r.deleted_at IS NULL
     AND r.is_active = 1
    LEFT JOIN property pr_role
      ON pr_role.id_property = r.id_property
    JOIN role_permission rp
      ON rp.id_role = r.id_role
     AND rp.deleted_at IS NULL
     AND rp.is_active = 1
     AND COALESCE(rp.allow, 1) = 1
    JOIN permission p
      ON p.id_permission = rp.id_permission
     AND p.deleted_at IS NULL
     AND p.is_active = 1
    WHERE ur.id_user = p_user_id
      AND ur.deleted_at IS NULL
      AND ur.is_active = 1
      AND (
        r.id_property IS NULL
        OR (
          pr_role.id_company = v_company_id
          AND pr_role.deleted_at IS NULL
        )
      )
    ORDER BY p.code;
  END IF;

  IF v_is_owner = 1 THEN
    SELECT pr.code
    FROM property pr
    WHERE pr.id_company = v_company_id
      AND pr.deleted_at IS NULL
      AND pr.is_active = 1
    ORDER BY pr.order_index, pr.name, pr.code;
  ELSE
    SELECT DISTINCT pr.code
    FROM user_property up
    JOIN property pr
      ON pr.id_property = up.id_property
     AND pr.id_company = v_company_id
     AND pr.deleted_at IS NULL
     AND pr.is_active = 1
    WHERE up.id_user = p_user_id
      AND up.deleted_at IS NULL
      AND up.is_active = 1
    ORDER BY pr.order_index, pr.name, pr.code;
  END IF;

  SELECT
    v_company_id AS id_company,
    p_user_id AS id_user,
    v_is_owner AS is_owner,
    v_mode AS authz_mode;
END $$

DELIMITER ;

