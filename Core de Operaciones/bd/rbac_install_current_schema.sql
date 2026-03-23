-- RBAC installer (schema-agnostic)
-- Ejecuta este archivo estando seleccionada la BD destino.
-- No usa nombre de schema fijo; todo corre en DATABASE() actual.

SELECT DATABASE() AS current_database;

DELIMITER ;

-- ==== BEGIN RBAC OBJECTS ====

-- >>> BEGIN FILE: rbac_tables.sql

DELIMITER $$

ALTER TABLE role
  MODIFY COLUMN id_property BIGINT NULL $$

CREATE TABLE IF NOT EXISTS pms_authz_config (
  id_company BIGINT NOT NULL,
  authz_mode ENUM('audit','enforce') NOT NULL DEFAULT 'audit',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT NULL,
  PRIMARY KEY (id_company),
  KEY idx_authz_mode (authz_mode),
  CONSTRAINT fk_authzcfg_company FOREIGN KEY (id_company) REFERENCES company(id_company),
  CONSTRAINT fk_authzcfg_user FOREIGN KEY (updated_by) REFERENCES app_user(id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci $$

CREATE TABLE IF NOT EXISTS pms_authz_audit (
  id_authz_audit BIGINT NOT NULL AUTO_INCREMENT,
  id_company BIGINT NULL,
  id_user BIGINT NULL,
  permission_code VARCHAR(100) NOT NULL,
  property_code VARCHAR(100) NULL,
  authz_mode ENUM('audit','enforce') NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  context_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_authz_audit),
  KEY idx_authzaudit_company_created (id_company, created_at),
  KEY idx_authzaudit_user_created (id_user, created_at),
  KEY idx_authzaudit_perm_created (permission_code, created_at),
  CONSTRAINT fk_authzaudit_company FOREIGN KEY (id_company) REFERENCES company(id_company),
  CONSTRAINT fk_authzaudit_user FOREIGN KEY (id_user) REFERENCES app_user(id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci $$

DELIMITER ;


-- <<< END FILE: rbac_tables.sql


-- >>> BEGIN FILE: hotfix_20260303_authz_tables.sql

-- Hotfix: RBAC authz objects required by sp_access_context_data / sp_authz_assert
-- Safe to run multiple times

ALTER TABLE `role`
  MODIFY COLUMN `id_property` BIGINT NULL;

CREATE TABLE IF NOT EXISTS `pms_authz_config` (
  `id_company` BIGINT NOT NULL,
  `authz_mode` ENUM('audit','enforce') NOT NULL DEFAULT 'audit',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT NULL,
  PRIMARY KEY (`id_company`),
  KEY `idx_authz_mode` (`authz_mode`),
  CONSTRAINT `fk_authzcfg_company` FOREIGN KEY (`id_company`) REFERENCES `company`(`id_company`),
  CONSTRAINT `fk_authzcfg_user` FOREIGN KEY (`updated_by`) REFERENCES `app_user`(`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pms_authz_audit` (
  `id_authz_audit` BIGINT NOT NULL AUTO_INCREMENT,
  `id_company` BIGINT NULL,
  `id_user` BIGINT NULL,
  `permission_code` VARCHAR(100) NOT NULL,
  `property_code` VARCHAR(100) NULL,
  `authz_mode` ENUM('audit','enforce') NOT NULL,
  `allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NULL,
  `context_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_authz_audit`),
  KEY `idx_authzaudit_company_created` (`id_company`, `created_at`),
  KEY `idx_authzaudit_user_created` (`id_user`, `created_at`),
  KEY `idx_authzaudit_perm_created` (`permission_code`, `created_at`),
  CONSTRAINT `fk_authzaudit_company` FOREIGN KEY (`id_company`) REFERENCES `company`(`id_company`),
  CONSTRAINT `fk_authzaudit_user` FOREIGN KEY (`id_user`) REFERENCES `app_user`(`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pms_authz_config` (`id_company`, `authz_mode`, `updated_by`, `updated_at`)
SELECT c.id_company, 'audit', 1, NOW()
FROM `company` c
ON DUPLICATE KEY UPDATE
  `authz_mode` = VALUES(`authz_mode`),
  `updated_by` = VALUES(`updated_by`),
  `updated_at` = NOW();

-- <<< END FILE: hotfix_20260303_authz_tables.sql


-- >>> BEGIN FILE: sp_access_context_data.sql

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


-- <<< END FILE: sp_access_context_data.sql


-- >>> BEGIN FILE: sp_authz_assert.sql

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


-- <<< END FILE: sp_authz_assert.sql


-- >>> BEGIN FILE: sp_user_role_sync.sql

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


-- <<< END FILE: sp_user_role_sync.sql


-- >>> BEGIN FILE: sp_user_property_sync.sql

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


-- <<< END FILE: sp_user_property_sync.sql


-- >>> BEGIN FILE: sp_role_upsert.sql

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

-- <<< END FILE: sp_role_upsert.sql


-- >>> BEGIN FILE: sp_role_permission_sync.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_role_permission_sync` $$
CREATE PROCEDURE `sp_role_permission_sync`(
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

-- <<< END FILE: sp_role_permission_sync.sql


-- >>> BEGIN FILE: sp_access_seed_defaults.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_access_seed_defaults $$
CREATE PROCEDURE sp_access_seed_defaults(
  IN p_company_code VARCHAR(100),
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_owner_role_id BIGINT;
  DECLARE v_ops_role_id BIGINT;
  DECLARE v_front_role_id BIGINT;
  DECLARE v_fin_role_id BIGINT;
  DECLARE v_ro_role_id BIGINT;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
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

  INSERT INTO pms_authz_config (id_company, authz_mode, updated_by, updated_at)
  VALUES (v_company_id, 'audit', p_actor_user_id, NOW())
  ON DUPLICATE KEY UPDATE
    updated_by = VALUES(updated_by),
    updated_at = NOW();

  INSERT INTO permission (code, permission_name, description, resource, action, is_active, deleted_at, created_at, created_by, updated_at)
  VALUES
    ('dashboard.view','Dashboard view','View dashboard','dashboard','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.view','Calendar view','View calendar module','calendar','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.view','Reservations view','View reservations module','reservations','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('guests.view','Guests view','View guests module','guests','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.view','Activities view','View activities module','activities','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.view','Properties view','View properties module','properties','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.view','Rooms view','View rooms module','rooms','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.view','Categories view','View categories module','categories','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.view','Rateplans view','View rateplans module','rateplans','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('messages.view','Messages view','View messages module','messages','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('otas.view','OTAs view','View otas module','otas','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('ota_ical.view','OTA iCal view','View ota iCal module','ota_ical','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.view','Sale items view','View sale items module','sale_items','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('payments.view','Payments view','View payments module','payments','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('incomes.view','Incomes view','View incomes module','incomes','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('obligations.view','Obligations view','View obligations module','obligations','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reports.view','Reports view','View reports module','reports','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('settings.view','Settings view','View settings module','settings','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.view','Users view','View users module','users','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.create_hold','Create hold','Create reservation hold from calendar','calendar','create_hold',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.move_reservation','Move reservation','Move reservation in calendar','calendar','move_reservation',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.manage_block','Manage room blocks','Create/update/delete room blocks','calendar','manage_block',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.register_payment','Register payment from calendar','Create payments from calendar','calendar','register_payment',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.create','Create reservation','Create reservation/wizard','reservations','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.edit','Edit reservation','Edit reservation details','reservations','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.status_change','Change reservation status','Change reservation status','reservations','status_change',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.move_property','Move reservation property','Move reservation across properties','reservations','move_property',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.manage_folio','Manage folios','Create/update/delete folios','reservations','manage_folio',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.post_charge','Post charges','Create/update/delete charges','reservations','post_charge',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.post_payment','Post payments','Create/update/delete payments','reservations','post_payment',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.refund','Create refunds','Create/delete refunds','reservations','refund',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.note_edit','Edit notes','Add/delete reservation notes','reservations','note_edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('guests.create','Create guest','Create guest records','guests','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('guests.edit','Edit guest','Update guest records','guests','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.create','Create activity','Create activities','activities','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.edit','Edit activity','Edit activities','activities','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.book','Book activity','Book activities','activities','book',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.cancel','Cancel activity','Cancel activities','activities','cancel',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.create','Create property','Create properties','properties','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.edit','Edit property','Edit properties','properties','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.delete','Delete property','Delete properties','properties','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.create','Create room','Create rooms','rooms','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.edit','Edit room','Edit rooms','rooms','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.delete','Delete room','Delete rooms','rooms','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.create','Create category','Create categories','categories','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.edit','Edit category','Edit categories','categories','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.delete','Delete category','Delete categories','categories','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.create','Create rateplan','Create rateplans','rateplans','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.edit','Edit rateplan','Edit rateplans','rateplans','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.delete','Delete rateplan','Delete rateplans','rateplans','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('messages.template_edit','Edit templates','Edit message templates','messages','template_edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('messages.send','Send messages','Send guest messages','messages','send',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('otas.edit','Edit ota settings','Edit OTA settings','otas','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('ota_ical.edit','Edit ota ical settings','Edit OTA iCal feeds','ota_ical','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('ota_ical.sync','Sync ota ical','Run OTA iCal sync','ota_ical','sync',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.create','Create sale item','Create sale item catalog','sale_items','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.edit','Edit sale item','Edit sale item catalog','sale_items','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.relations_edit','Edit sale item relations','Edit derived relations','sale_items','relations_edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('payments.post','Post payment','Post payment operations','payments','post',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('payments.edit','Edit payment','Edit payment operations','payments','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('incomes.reconcile','Reconcile incomes','Reconcile incomes','incomes','reconcile',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('obligations.pay','Pay obligations','Pay obligations','obligations','pay',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reports.run','Run reports','Run reports','reports','run',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reports.design','Design reports','Design reports','reports','design',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('settings.edit','Edit settings','Edit settings','settings','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.create','Create user','Create users','users','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.edit','Edit user','Edit users','users','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.assign_roles','Assign roles','Assign roles to users','users','assign_roles',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.assign_properties','Assign properties','Assign properties to users','users','assign_properties',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.manage_roles','Manage roles','Create/edit role definitions','users','manage_roles',1,NULL,NOW(),p_actor_user_id,NOW())
  ON DUPLICATE KEY UPDATE
    permission_name = VALUES(permission_name),
    description = VALUES(description),
    resource = VALUES(resource),
    action = VALUES(action),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Owner/Admin', 'Global owner/admin role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Owner/Admin' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Operaciones', 'Global operations role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Operaciones' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Recepcion', 'Global frontdesk role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Recepcion' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Finanzas', 'Global finance role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Finanzas' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Solo Lectura', 'Global read-only role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Solo Lectura' AND r.deleted_at IS NULL
  );

  SELECT id_role INTO v_owner_role_id FROM role WHERE id_property IS NULL AND name = 'Owner/Admin' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_ops_role_id FROM role WHERE id_property IS NULL AND name = 'Operaciones' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_front_role_id FROM role WHERE id_property IS NULL AND name = 'Recepcion' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_fin_role_id FROM role WHERE id_property IS NULL AND name = 'Finanzas' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_ro_role_id FROM role WHERE id_property IS NULL AND name = 'Solo Lectura' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;

  DELETE FROM role_permission
  WHERE id_role IN (v_owner_role_id, v_ops_role_id, v_front_role_id, v_fin_role_id, v_ro_role_id);

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_owner_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL AND p.is_active = 1;

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_ops_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND p.code NOT IN (
      'users.view','users.create','users.edit','users.assign_roles','users.assign_properties','users.manage_roles',
      'settings.edit'
    );

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_front_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND p.code IN (
      'dashboard.view','calendar.view','reservations.view','guests.view','messages.view',
      'payments.view','calendar.create_hold','calendar.move_reservation','calendar.register_payment',
      'reservations.create','reservations.edit','reservations.status_change','reservations.manage_folio',
      'reservations.post_charge','reservations.post_payment','reservations.note_edit',
      'guests.create','guests.edit','messages.send','payments.post','payments.edit'
    );

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_fin_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND p.code IN (
      'dashboard.view','calendar.view','reservations.view','payments.view','incomes.view','obligations.view','reports.view',
      'payments.post','payments.edit','incomes.reconcile','obligations.pay','reports.run'
    );

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_ro_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND (p.code LIKE '%.view' OR p.code = 'reports.run');

  INSERT INTO user_property (
    id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at
  )
  SELECT
    au.id_user, pr.id_property, 0, NULL, NULL, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM app_user au
  JOIN property pr
    ON pr.id_company = au.id_company
   AND pr.deleted_at IS NULL
   AND pr.is_active = 1
  WHERE au.id_company = v_company_id
    AND au.deleted_at IS NULL
    AND au.is_active = 1
    AND COALESCE(au.is_owner, 0) = 0
    AND NOT EXISTS (
      SELECT 1
      FROM user_property up_any
      JOIN property pr_any ON pr_any.id_property = up_any.id_property
      WHERE up_any.id_user = au.id_user
        AND up_any.deleted_at IS NULL
        AND up_any.is_active = 1
        AND pr_any.id_company = v_company_id
        AND pr_any.deleted_at IS NULL
    )
    AND NOT EXISTS (
      SELECT 1
      FROM user_property up_exists
      WHERE up_exists.id_user = au.id_user
        AND up_exists.id_property = pr.id_property
        AND up_exists.deleted_at IS NULL
    );

  SELECT 'ok' AS status, v_company_id AS id_company, 'seed_complete' AS message;
END $$

DELIMITER ;


-- <<< END FILE: sp_access_seed_defaults.sql


-- >>> BEGIN FILE: sp_portal_app_user_data.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_portal_app_user_data` $$
CREATE PROCEDURE `sp_portal_app_user_data` (
  IN p_company_code   VARCHAR(100),
  IN p_search         VARCHAR(255),
  IN p_property_code  VARCHAR(100),
  IN p_only_active    TINYINT,
  IN p_user_id        BIGINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_search VARCHAR(255);

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_search = NULLIF(TRIM(p_search), '');

  /* Result set 1: list of users with aggregates */
  SELECT
    au.id_user,
    au.email,
    au.names,
    au.last_name,
    au.display_name,
    au.phone,
    au.is_owner,
    au.is_active,
    au.last_login_at,
    COUNT(DISTINCT up.id_property) AS property_count,
    GROUP_CONCAT(DISTINCT pr.code ORDER BY pr.code SEPARATOR ', ') AS property_codes,
    GROUP_CONCAT(DISTINCT rl.name ORDER BY rl.name SEPARATOR ', ') AS role_names
  FROM app_user au
  LEFT JOIN user_property up
    ON up.id_user = au.id_user
   AND up.deleted_at IS NULL
  LEFT JOIN property pr
    ON pr.id_property = up.id_property
   AND pr.id_company = v_company_id
   AND pr.deleted_at IS NULL
  LEFT JOIN user_role ur
    ON ur.id_user = au.id_user
   AND ur.deleted_at IS NULL
  LEFT JOIN role rl
    ON rl.id_role = ur.id_role
   AND rl.deleted_at IS NULL
   AND rl.is_active = 1
  LEFT JOIN property rlpr
    ON rlpr.id_property = rl.id_property
  WHERE au.id_company = v_company_id
    AND au.deleted_at IS NULL
    AND (p_only_active IS NULL OR p_only_active = 0 OR au.is_active = 1)
    AND (
      v_search IS NULL OR
      au.email LIKE CONCAT('%', v_search, '%') OR
      au.names LIKE CONCAT('%', v_search, '%') OR
      au.last_name LIKE CONCAT('%', v_search, '%') OR
      au.display_name LIKE CONCAT('%', v_search, '%') OR
      au.full_name LIKE CONCAT('%', v_search, '%')
    )
    AND (
      p_property_code IS NULL OR p_property_code = '' OR EXISTS (
        SELECT 1
        FROM user_property up2
        JOIN property pr2 ON pr2.id_property = up2.id_property
        WHERE up2.id_user = au.id_user
          AND up2.deleted_at IS NULL
          AND pr2.id_company = v_company_id
          AND pr2.deleted_at IS NULL
          AND pr2.code = p_property_code
      )
    )
    AND (
      rl.id_role IS NULL
      OR rl.id_property IS NULL
      OR (
        rlpr.id_company = v_company_id
        AND rlpr.deleted_at IS NULL
      )
    )
  GROUP BY
    au.id_user,
    au.email,
    au.names,
    au.last_name,
    au.display_name,
    au.phone,
    au.is_owner,
    au.is_active,
    au.last_login_at
  ORDER BY au.names, au.email;

  /* Result set 2: selected user detail */
  IF p_user_id IS NULL OR p_user_id = 0 THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_user,
      CAST(NULL AS CHAR) AS email,
      CAST(NULL AS CHAR) AS names,
      CAST(NULL AS CHAR) AS last_name,
      CAST(NULL AS CHAR) AS maiden_name,
      CAST(NULL AS CHAR) AS display_name,
      CAST(NULL AS CHAR) AS phone,
      CAST(NULL AS CHAR) AS locale,
      CAST(NULL AS CHAR) AS timezone,
      CAST(NULL AS SIGNED) AS is_owner,
      CAST(NULL AS SIGNED) AS is_active,
      CAST(NULL AS CHAR) AS notes
    LIMIT 0;
  ELSE
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
    WHERE au.id_user = p_user_id
      AND au.id_company = v_company_id
      AND au.deleted_at IS NULL
    LIMIT 1;
  END IF;

  /* Result set 3: properties of the company with assignment flag */
  SELECT
    pr.id_property,
    pr.code        AS property_code,
    pr.name        AS property_name,
    IF(up.id_user IS NULL, 0, 1) AS is_assigned
  FROM property pr
  LEFT JOIN user_property up
    ON up.id_property = pr.id_property
   AND up.deleted_at IS NULL
   AND up.id_user = p_user_id
  WHERE pr.id_company = v_company_id
    AND pr.deleted_at IS NULL
    AND pr.is_active = 1
  ORDER BY pr.name;

  /* Result set 4: global + property roles with assignment flag */
  SELECT
    rl.id_role,
    rl.name,
    rl.description,
    CASE WHEN rl.id_property IS NULL THEN '*' ELSE pr.code END AS property_code,
    CASE WHEN rl.id_property IS NULL THEN 'Global' ELSE pr.name END AS property_name,
    IF(ur.id_user_role IS NULL, 0, 1) AS is_assigned
  FROM role rl
  LEFT JOIN property pr
    ON pr.id_property = rl.id_property
  LEFT JOIN user_role ur
    ON ur.id_role = rl.id_role
   AND ur.deleted_at IS NULL
   AND ur.id_user = p_user_id
  WHERE rl.deleted_at IS NULL
    AND rl.is_active = 1
    AND (
      rl.id_property IS NULL
      OR (
        pr.id_company = v_company_id
        AND pr.deleted_at IS NULL
      )
    )
  ORDER BY
    CASE WHEN rl.id_property IS NULL THEN 0 ELSE 1 END,
    pr.name,
    rl.name;
END $$

DELIMITER ;

-- <<< END FILE: sp_portal_app_user_data.sql

