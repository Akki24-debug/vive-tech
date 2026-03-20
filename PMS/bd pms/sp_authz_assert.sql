DELIMITER $$

DROP PROCEDURE IF EXISTS sp_authz_assert $$
CREATE PROCEDURE sp_authz_assert(
  IN p_company_code VARCHAR(100),
  IN p_actor_user_id BIGINT,
  IN p_permission_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_mode VARCHAR(16)
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_user_company_id BIGINT;
  DECLARE v_mode VARCHAR(16) DEFAULT '';
  DECLARE v_is_owner TINYINT DEFAULT 0;
  DECLARE v_has_permission TINYINT DEFAULT 0;
  DECLARE v_has_property TINYINT DEFAULT 1;
  DECLARE v_property_exists TINYINT DEFAULT 0;
  DECLARE v_allowed TINYINT DEFAULT 0;
  DECLARE v_reason VARCHAR(255) DEFAULT '';
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_actor_user_id IS NULL OR p_actor_user_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor user id is required';
  END IF;
  IF p_permission_code IS NULL OR TRIM(p_permission_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Permission code is required';
  END IF;

  SET v_property_code = NULLIF(TRIM(COALESCE(p_property_code, '')), '');

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
    AND c.deleted_at IS NULL
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_mode = LOWER(TRIM(COALESCE(NULLIF(p_mode, ''), '')));
  IF v_mode NOT IN ('audit', 'enforce') THEN
    SELECT LOWER(TRIM(COALESCE(ac.authz_mode, 'audit')))
      INTO v_mode
    FROM pms_authz_config ac
    WHERE ac.id_company = v_company_id
    LIMIT 1;
  END IF;
  IF v_mode NOT IN ('audit', 'enforce') THEN
    SET v_mode = 'audit';
  END IF;

  SELECT au.id_company, COALESCE(au.is_owner, 0)
    INTO v_user_company_id, v_is_owner
  FROM app_user au
  WHERE au.id_user = p_actor_user_id
    AND au.deleted_at IS NULL
    AND au.is_active = 1
  LIMIT 1;

  IF v_user_company_id IS NULL OR v_user_company_id <> v_company_id THEN
    SET v_reason = 'actor_not_in_company_or_inactive';
    SET v_allowed = 0;
  ELSE
    IF v_is_owner = 1 THEN
      SET v_has_permission = 1;
      SET v_has_property = 1;
    ELSE
      SELECT CASE WHEN EXISTS (
        SELECT 1
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
        WHERE ur.id_user = p_actor_user_id
          AND ur.deleted_at IS NULL
          AND ur.is_active = 1
          AND p.code = p_permission_code
          AND (
            r.id_property IS NULL
            OR (
              pr_role.id_company = v_company_id
              AND pr_role.deleted_at IS NULL
            )
          )
      ) THEN 1 ELSE 0 END
        INTO v_has_permission;

      IF v_property_code IS NOT NULL THEN
        SELECT CASE WHEN EXISTS (
          SELECT 1
          FROM property pr
          WHERE pr.code = v_property_code
            AND pr.id_company = v_company_id
            AND pr.deleted_at IS NULL
        ) THEN 1 ELSE 0 END
          INTO v_property_exists;

        IF v_property_exists = 0 THEN
          SET v_has_property = 0;
        ELSE
          SELECT CASE WHEN EXISTS (
            SELECT 1
            FROM user_property up
            JOIN property pr
              ON pr.id_property = up.id_property
             AND pr.id_company = v_company_id
             AND pr.deleted_at IS NULL
            WHERE up.id_user = p_actor_user_id
              AND up.deleted_at IS NULL
              AND up.is_active = 1
              AND pr.code = v_property_code
          ) THEN 1 ELSE 0 END
            INTO v_has_property;
        END IF;
      ELSE
        SET v_has_property = 1;
      END IF;
    END IF;

    IF v_has_permission = 0 THEN
      SET v_reason = 'permission_denied';
    ELSEIF v_has_property = 0 THEN
      SET v_reason = 'property_scope_denied';
    ELSE
      SET v_reason = '';
    END IF;

    SET v_allowed = CASE WHEN v_has_permission = 1 AND v_has_property = 1 THEN 1 ELSE 0 END;
  END IF;

  IF v_allowed = 0 THEN
    INSERT INTO pms_authz_audit (
      id_company,
      id_user,
      permission_code,
      property_code,
      authz_mode,
      allowed,
      reason,
      context_json,
      created_at
    ) VALUES (
      v_company_id,
      p_actor_user_id,
      p_permission_code,
      v_property_code,
      v_mode,
      0,
      v_reason,
      CONCAT(
        '{\"company_code\":\"', REPLACE(COALESCE(p_company_code, ''), '\"', '\\\"'),
        '\",\"permission_code\":\"', REPLACE(COALESCE(p_permission_code, ''), '\"', '\\\"'),
        '\",\"property_code\":\"', REPLACE(COALESCE(v_property_code, ''), '\"', '\\\"'),
        '\",\"mode\":\"', REPLACE(COALESCE(v_mode, ''), '\"', '\\\"'),
        '\"}'
      ),
      NOW()
    );

    IF v_mode = 'enforce' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AUTHZ_DENIED';
    END IF;
  END IF;
END $$

DELIMITER ;

