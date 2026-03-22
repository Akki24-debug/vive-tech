/**
 * Procedure: sp_organization_data
 * Purpose: Consulta registros de `organization` con filtros predecibles para IA e integraciones.
 * Tables touched: organization
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_organization_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_organization_data` $$
CREATE PROCEDURE `sp_organization_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `organization` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.legal_name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.notes, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.vision_summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_organization_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: organization, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_organization_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_organization_upsert` $$
CREATE PROCEDURE `sp_organization_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_legal_name VARCHAR(200),
  IN p_description TEXT,
  IN p_base_city VARCHAR(120),
  IN p_base_state VARCHAR(120),
  IN p_country VARCHAR(120),
  IN p_status VARCHAR(50),
  IN p_current_stage VARCHAR(100),
  IN p_vision_summary TEXT,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);

  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `organization` (`name`, `legal_name`, `description`, `base_city`, `base_state`, `country`, `status`, `current_stage`, `vision_summary`, `notes`)
    VALUES (p_name, p_legal_name, p_description, p_base_city, p_base_state, p_country, p_status, p_current_stage, p_vision_summary, p_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'organization', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_organization_upsert'));
  ELSE
    SET v_organization_id = p_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
    SELECT `status` INTO v_old_status FROM `organization` WHERE id = p_id LIMIT 1;
    UPDATE `organization`
    SET
    `name` = p_name,
    `legal_name` = p_legal_name,
    `description` = p_description,
    `base_city` = p_base_city,
    `base_state` = p_base_state,
    `country` = p_country,
    `status` = p_status,
    `current_stage` = p_current_stage,
    `vision_summary` = p_vision_summary,
    `notes` = p_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'organization', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_organization_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `organization` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('organization', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_organization_upsert'));
  END IF;

  SELECT * FROM `organization` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_account_data
 * Purpose: Consulta registros de `user_account` con filtros predecibles para IA e integraciones.
 * Tables touched: user_account
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_user_account_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_account_data` $$
CREATE PROCEDURE `sp_user_account_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `user_account` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.display_name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.email, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.role_summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.notes, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_account_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_account, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_account_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_account_upsert` $$
CREATE PROCEDURE `sp_user_account_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_first_name VARCHAR(120),
  IN p_last_name VARCHAR(120),
  IN p_display_name VARCHAR(180),
  IN p_email VARCHAR(190),
  IN p_phone VARCHAR(40),
  IN p_role_summary VARCHAR(255),
  IN p_employment_status VARCHAR(50),
  IN p_timezone VARCHAR(80),
  IN p_notes TEXT,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_first_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'first_name is required';
  END IF;
  IF p_display_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'display_name is required';
  END IF;
    INSERT INTO `user_account` (`organization_id`, `first_name`, `last_name`, `display_name`, `email`, `phone`, `role_summary`, `employment_status`, `timezone`, `notes`, `is_active`)
    VALUES (p_organization_id, p_first_name, p_last_name, p_display_name, p_email, p_phone, p_role_summary, p_employment_status, p_timezone, p_notes, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_account', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_account_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
    SELECT `employment_status` INTO v_old_status FROM `user_account` WHERE id = p_id LIMIT 1;
    UPDATE `user_account`
    SET
    `organization_id` = p_organization_id,
    `first_name` = p_first_name,
    `last_name` = p_last_name,
    `display_name` = p_display_name,
    `email` = p_email,
    `phone` = p_phone,
    `role_summary` = p_role_summary,
    `employment_status` = p_employment_status,
    `timezone` = p_timezone,
    `notes` = p_notes,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_account', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_account_upsert'));
  END IF;

  SET v_new_status = (SELECT `employment_status` FROM `user_account` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('user_account', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_user_account_upsert'));
  END IF;

  SELECT * FROM `user_account` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_role_data
 * Purpose: Consulta registros de `role` con filtros predecibles para IA e integraciones.
 * Tables touched: role
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_role_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_role_data` $$
CREATE PROCEDURE `sp_role_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `role` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_role_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: role, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_role_upsert(NULL, ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_role_upsert` $$
CREATE PROCEDURE `sp_role_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_name VARCHAR(120),
  IN p_description TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);

  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `role` (`name`, `description`)
    VALUES (p_name, p_description);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'role', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_role_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
    UPDATE `role`
    SET
    `name` = p_name,
    `description` = p_description
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'role', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_role_upsert'));
  END IF;

  SELECT * FROM `role` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_role_data
 * Purpose: Consulta registros de `user_role` con filtros predecibles para IA e integraciones.
 * Tables touched: user_role
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_user_role_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_role_data` $$
CREATE PROCEDURE `sp_user_role_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `user_role` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_role_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_role, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_role_upsert(NULL, ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_role_upsert` $$
CREATE PROCEDURE `sp_user_role_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_role_id BIGINT UNSIGNED,
  IN p_is_primary TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_role_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'role_id is required';
  END IF;
    INSERT INTO `user_role` (`user_id`, `role_id`, `is_primary`)
    VALUES (p_user_id, p_role_id, p_is_primary);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_role', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_role_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
    UPDATE `user_role`
    SET
    `user_id` = p_user_id,
    `role_id` = p_role_id,
    `is_primary` = p_is_primary
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_role', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_role_upsert'));
  END IF;

  SELECT * FROM `user_role` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_role_delete
 * Purpose: Elimina fisicamente registros de `user_role` solo donde el modelo lo permite.
 * Tables touched: user_role, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_user_role_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_role_delete` $$
CREATE PROCEDURE `sp_user_role_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `user_role` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'user_role', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_area_data
 * Purpose: Consulta registros de `business_area` con filtros predecibles para IA e integraciones.
 * Tables touched: business_area
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_business_area_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_area_data` $$
CREATE PROCEDURE `sp_business_area_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `business_area` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.code, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_area_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: business_area, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_business_area_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_area_upsert` $$
CREATE PROCEDURE `sp_business_area_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_code VARCHAR(50),
  IN p_description TEXT,
  IN p_priority_level VARCHAR(50),
  IN p_responsible_user_id BIGINT UNSIGNED,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `business_area` (`organization_id`, `name`, `code`, `description`, `priority_level`, `responsible_user_id`, `is_active`)
    VALUES (p_organization_id, p_name, p_code, p_description, p_priority_level, p_responsible_user_id, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'business_area', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_business_area_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM business_area WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `business_area`
    SET
    `organization_id` = p_organization_id,
    `name` = p_name,
    `code` = p_code,
    `description` = p_description,
    `priority_level` = p_priority_level,
    `responsible_user_id` = p_responsible_user_id,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'business_area', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_business_area_upsert'));
  END IF;

  SELECT * FROM `business_area` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_area_assignment_data
 * Purpose: Consulta registros de `user_area_assignment` con filtros predecibles para IA e integraciones.
 * Tables touched: user_area_assignment
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_user_area_assignment_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_area_assignment_data` $$
CREATE PROCEDURE `sp_user_area_assignment_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `user_area_assignment` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_area_assignment_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_area_assignment, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_area_assignment_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_area_assignment_upsert` $$
CREATE PROCEDURE `sp_user_area_assignment_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_responsibility_level VARCHAR(50),
  IN p_is_primary TINYINT(1),
  IN p_start_date DATE,
  IN p_end_date DATE,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM business_area WHERE id = p_business_area_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_business_area_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'business_area_id is required';
  END IF;
    INSERT INTO `user_area_assignment` (`user_id`, `business_area_id`, `responsibility_level`, `is_primary`, `start_date`, `end_date`)
    VALUES (p_user_id, p_business_area_id, p_responsibility_level, p_is_primary, p_start_date, p_end_date);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_area_assignment', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_area_assignment_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM user_area_assignment t JOIN business_area p ON p.id = t.business_area_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `user_area_assignment`
    SET
    `user_id` = p_user_id,
    `business_area_id` = p_business_area_id,
    `responsibility_level` = p_responsibility_level,
    `is_primary` = p_is_primary,
    `start_date` = p_start_date,
    `end_date` = p_end_date
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_area_assignment', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_area_assignment_upsert'));
  END IF;

  SELECT * FROM `user_area_assignment` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_area_assignment_delete
 * Purpose: Elimina fisicamente registros de `user_area_assignment` solo donde el modelo lo permite.
 * Tables touched: user_area_assignment, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_user_area_assignment_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_area_assignment_delete` $$
CREATE PROCEDURE `sp_user_area_assignment_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM user_area_assignment t JOIN business_area p ON p.id = t.business_area_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `user_area_assignment` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'user_area_assignment', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_capacity_profile_data
 * Purpose: Consulta registros de `user_capacity_profile` con filtros predecibles para IA e integraciones.
 * Tables touched: user_capacity_profile
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_user_capacity_profile_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_capacity_profile_data` $$
CREATE PROCEDURE `sp_user_capacity_profile_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `user_capacity_profile` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.notes, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_user_capacity_profile_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_capacity_profile, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_capacity_profile_upsert(NULL, ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_capacity_profile_upsert` $$
CREATE PROCEDURE `sp_user_capacity_profile_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_weekly_capacity_hours DECIMAL(6,2),
  IN p_max_parallel_projects INT UNSIGNED,
  IN p_max_parallel_tasks INT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_user_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
    INSERT INTO `user_capacity_profile` (`user_id`, `weekly_capacity_hours`, `max_parallel_projects`, `max_parallel_tasks`, `notes`)
    VALUES (p_user_id, p_weekly_capacity_hours, p_max_parallel_projects, p_max_parallel_tasks, p_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_capacity_profile', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_capacity_profile_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM user_capacity_profile t JOIN user_account p ON p.id = t.user_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `user_capacity_profile`
    SET
    `user_id` = p_user_id,
    `weekly_capacity_hours` = p_weekly_capacity_hours,
    `max_parallel_projects` = p_max_parallel_projects,
    `max_parallel_tasks` = p_max_parallel_tasks,
    `notes` = p_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_capacity_profile', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_capacity_profile_upsert'));
  END IF;

  SELECT * FROM `user_capacity_profile` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_line_data
 * Purpose: Consulta registros de `business_line` con filtros predecibles para IA e integraciones.
 * Tables touched: business_line
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_business_line_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_line_data` $$
CREATE PROCEDURE `sp_business_line_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `business_line` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.business_model_summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.monetization_notes, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_line_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: business_line, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_business_line_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_line_upsert` $$
CREATE PROCEDURE `sp_business_line_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_name VARCHAR(180),
  IN p_description TEXT,
  IN p_business_model_summary TEXT,
  IN p_current_status VARCHAR(50),
  IN p_monetization_notes TEXT,
  IN p_strategic_priority VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `business_line` (`organization_id`, `business_area_id`, `name`, `description`, `business_model_summary`, `current_status`, `monetization_notes`, `strategic_priority`, `owner_user_id`, `is_active`)
    VALUES (p_organization_id, p_business_area_id, p_name, p_description, p_business_model_summary, p_current_status, p_monetization_notes, p_strategic_priority, p_owner_user_id, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'business_line', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_business_line_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM business_line WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `business_line` WHERE id = p_id LIMIT 1;
    UPDATE `business_line`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `name` = p_name,
    `description` = p_description,
    `business_model_summary` = p_business_model_summary,
    `current_status` = p_current_status,
    `monetization_notes` = p_monetization_notes,
    `strategic_priority` = p_strategic_priority,
    `owner_user_id` = p_owner_user_id,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'business_line', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_business_line_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `business_line` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('business_line', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_business_line_upsert'));
  END IF;

  SELECT * FROM `business_line` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_priority_data
 * Purpose: Consulta registros de `business_priority` con filtros predecibles para IA e integraciones.
 * Tables touched: business_priority
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_business_priority_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_priority_data` $$
CREATE PROCEDURE `sp_business_priority_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `business_priority` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_priority_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: business_priority, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_business_priority_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_priority_upsert` $$
CREATE PROCEDURE `sp_business_priority_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_scope_type VARCHAR(50),
  IN p_scope_id BIGINT UNSIGNED,
  IN p_priority_order INT UNSIGNED,
  IN p_status VARCHAR(50),
  IN p_target_period VARCHAR(100),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `business_priority` (`organization_id`, `title`, `description`, `scope_type`, `scope_id`, `priority_order`, `status`, `target_period`, `owner_user_id`)
    VALUES (p_organization_id, p_title, p_description, p_scope_type, p_scope_id, p_priority_order, p_status, p_target_period, p_owner_user_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'business_priority', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_business_priority_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM business_priority WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `business_priority` WHERE id = p_id LIMIT 1;
    UPDATE `business_priority`
    SET
    `organization_id` = p_organization_id,
    `title` = p_title,
    `description` = p_description,
    `scope_type` = p_scope_type,
    `scope_id` = p_scope_id,
    `priority_order` = p_priority_order,
    `status` = p_status,
    `target_period` = p_target_period,
    `owner_user_id` = p_owner_user_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'business_priority', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_business_priority_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `business_priority` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('business_priority', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_business_priority_upsert'));
  END IF;

  SELECT * FROM `business_priority` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_objective_record_data
 * Purpose: Consulta registros de `objective_record` con filtros predecibles para IA e integraciones.
 * Tables touched: objective_record
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_objective_record_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_objective_record_data` $$
CREATE PROCEDURE `sp_objective_record_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `objective_record` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_objective_record_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: objective_record, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_objective_record_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_objective_record_upsert` $$
CREATE PROCEDURE `sp_objective_record_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_objective_type VARCHAR(50),
  IN p_status VARCHAR(50),
  IN p_target_date DATE,
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_completion_percent DECIMAL(5,2),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `objective_record` (`organization_id`, `business_area_id`, `title`, `description`, `objective_type`, `status`, `target_date`, `owner_user_id`, `completion_percent`)
    VALUES (p_organization_id, p_business_area_id, p_title, p_description, p_objective_type, p_status, p_target_date, p_owner_user_id, p_completion_percent);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'objective_record', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_objective_record_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM objective_record WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `objective_record` WHERE id = p_id LIMIT 1;
    UPDATE `objective_record`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `description` = p_description,
    `objective_type` = p_objective_type,
    `status` = p_status,
    `target_date` = p_target_date,
    `owner_user_id` = p_owner_user_id,
    `completion_percent` = p_completion_percent
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'objective_record', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_objective_record_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `objective_record` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('objective_record', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_objective_record_upsert'));
  END IF;

  SELECT * FROM `objective_record` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

