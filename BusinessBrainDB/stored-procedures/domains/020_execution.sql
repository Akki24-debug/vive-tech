/**
 * Procedure: sp_project_category_data
 * Purpose: Consulta registros de `project_category` con filtros predecibles para IA e integraciones.
 * Tables touched: project_category
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_project_category_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_category_data` $$
CREATE PROCEDURE `sp_project_category_data` (
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
  FROM `project_category` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_category_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_category, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_category_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_category_upsert` $$
CREATE PROCEDURE `sp_project_category_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_description TEXT,
  IN p_sort_order INT UNSIGNED,
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
    INSERT INTO `project_category` (`organization_id`, `business_area_id`, `name`, `description`, `sort_order`, `is_active`)
    VALUES (p_organization_id, p_business_area_id, p_name, p_description, p_sort_order, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_category', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_category_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM project_category WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_category`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `name` = p_name,
    `description` = p_description,
    `sort_order` = p_sort_order,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_category', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_category_upsert'));
  END IF;

  SELECT * FROM `project_category` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_initiative_data
 * Purpose: Consulta registros de `initiative` con filtros predecibles para IA e integraciones.
 * Tables touched: initiative
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_initiative_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_initiative_data` $$
CREATE PROCEDURE `sp_initiative_data` (
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
  FROM `initiative` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_initiative_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: initiative, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_initiative_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_initiative_upsert` $$
CREATE PROCEDURE `sp_initiative_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_project_category_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_status VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_start_date DATE,
  IN p_target_date DATE,
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
    INSERT INTO `initiative` (`organization_id`, `project_category_id`, `title`, `description`, `status`, `priority_level`, `owner_user_id`, `start_date`, `target_date`, `completion_percent`)
    VALUES (p_organization_id, p_project_category_id, p_title, p_description, p_status, p_priority_level, p_owner_user_id, p_start_date, p_target_date, p_completion_percent);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'initiative', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_initiative_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM initiative WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `initiative` WHERE id = p_id LIMIT 1;
    UPDATE `initiative`
    SET
    `organization_id` = p_organization_id,
    `project_category_id` = p_project_category_id,
    `title` = p_title,
    `description` = p_description,
    `status` = p_status,
    `priority_level` = p_priority_level,
    `owner_user_id` = p_owner_user_id,
    `start_date` = p_start_date,
    `target_date` = p_target_date,
    `completion_percent` = p_completion_percent
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'initiative', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_initiative_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `initiative` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('initiative', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_initiative_upsert'));
  END IF;

  SELECT * FROM `initiative` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_data
 * Purpose: Consulta registros de `project` con filtros predecibles para IA e integraciones.
 * Tables touched: project
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_project_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_data` $$
CREATE PROCEDURE `sp_project_data` (
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
  FROM `project` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.objective, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_upsert` $$
CREATE PROCEDURE `sp_project_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_initiative_id BIGINT UNSIGNED,
  IN p_project_category_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_business_line_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_short_code VARCHAR(60),
  IN p_description TEXT,
  IN p_objective TEXT,
  IN p_current_status VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_health_status VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_sponsor_user_id BIGINT UNSIGNED,
  IN p_start_date DATE,
  IN p_target_date DATE,
  IN p_completed_at DATETIME,
  IN p_completion_percent DECIMAL(5,2),
  IN p_last_reported_at DATETIME,
  IN p_last_activity_at DATETIME,
  IN p_is_active TINYINT(1),
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_updated_by_user_id BIGINT UNSIGNED,
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
    INSERT INTO `project` (`organization_id`, `initiative_id`, `project_category_id`, `business_area_id`, `business_line_id`, `title`, `short_code`, `description`, `objective`, `current_status`, `priority_level`, `health_status`, `owner_user_id`, `sponsor_user_id`, `start_date`, `target_date`, `completed_at`, `completion_percent`, `last_reported_at`, `last_activity_at`, `is_active`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_organization_id, p_initiative_id, p_project_category_id, p_business_area_id, p_business_line_id, p_title, p_short_code, p_description, p_objective, p_current_status, p_priority_level, p_health_status, p_owner_user_id, p_sponsor_user_id, p_start_date, p_target_date, p_completed_at, p_completion_percent, p_last_reported_at, p_last_activity_at, p_is_active, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `project` WHERE id = p_id LIMIT 1;
    UPDATE `project`
    SET
    `organization_id` = p_organization_id,
    `initiative_id` = p_initiative_id,
    `project_category_id` = p_project_category_id,
    `business_area_id` = p_business_area_id,
    `business_line_id` = p_business_line_id,
    `title` = p_title,
    `short_code` = p_short_code,
    `description` = p_description,
    `objective` = p_objective,
    `current_status` = p_current_status,
    `priority_level` = p_priority_level,
    `health_status` = p_health_status,
    `owner_user_id` = p_owner_user_id,
    `sponsor_user_id` = p_sponsor_user_id,
    `start_date` = p_start_date,
    `target_date` = p_target_date,
    `completed_at` = p_completed_at,
    `completion_percent` = p_completion_percent,
    `last_reported_at` = p_last_reported_at,
    `last_activity_at` = p_last_activity_at,
    `is_active` = p_is_active,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `project` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('project', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_project_upsert'));
  END IF;

  SELECT * FROM `project` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_member_data
 * Purpose: Consulta registros de `project_member` con filtros predecibles para IA e integraciones.
 * Tables touched: project_member
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_project_member_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_member_data` $$
CREATE PROCEDURE `sp_project_member_data` (
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
  FROM `project_member` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_member_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_member, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_member_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_member_upsert` $$
CREATE PROCEDURE `sp_project_member_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_role_in_project VARCHAR(80),
  IN p_is_primary TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
    INSERT INTO `project_member` (`project_id`, `user_id`, `role_in_project`, `is_primary`)
    VALUES (p_project_id, p_user_id, p_role_in_project, p_is_primary);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_member', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_member_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM project_member t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_member`
    SET
    `project_id` = p_project_id,
    `user_id` = p_user_id,
    `role_in_project` = p_role_in_project,
    `is_primary` = p_is_primary
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_member', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_member_upsert'));
  END IF;

  SELECT * FROM `project_member` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_member_delete
 * Purpose: Elimina fisicamente registros de `project_member` solo donde el modelo lo permite.
 * Tables touched: project_member, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_project_member_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_member_delete` $$
CREATE PROCEDURE `sp_project_member_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM project_member t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `project_member` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'project_member', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_objective_link_data
 * Purpose: Consulta registros de `project_objective_link` con filtros predecibles para IA e integraciones.
 * Tables touched: project_objective_link
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_project_objective_link_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_objective_link_data` $$
CREATE PROCEDURE `sp_project_objective_link_data` (
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
  FROM `project_objective_link` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_objective_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_objective_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_objective_link_upsert(NULL, ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_objective_link_upsert` $$
CREATE PROCEDURE `sp_project_objective_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_objective_record_id BIGINT UNSIGNED,
  IN p_relation_type VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_objective_record_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'objective_record_id is required';
  END IF;
    INSERT INTO `project_objective_link` (`project_id`, `objective_record_id`, `relation_type`)
    VALUES (p_project_id, p_objective_record_id, p_relation_type);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_objective_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_objective_link_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM project_objective_link t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_objective_link`
    SET
    `project_id` = p_project_id,
    `objective_record_id` = p_objective_record_id,
    `relation_type` = p_relation_type
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_objective_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_objective_link_upsert'));
  END IF;

  SELECT * FROM `project_objective_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_objective_link_delete
 * Purpose: Elimina fisicamente registros de `project_objective_link` solo donde el modelo lo permite.
 * Tables touched: project_objective_link, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_project_objective_link_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_objective_link_delete` $$
CREATE PROCEDURE `sp_project_objective_link_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM project_objective_link t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `project_objective_link` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'project_objective_link', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_subproject_data
 * Purpose: Consulta registros de `subproject` con filtros predecibles para IA e integraciones.
 * Tables touched: subproject
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_subproject_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_subproject_data` $$
CREATE PROCEDURE `sp_subproject_data` (
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
  FROM `subproject` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_subproject_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: subproject, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_subproject_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_subproject_upsert` $$
CREATE PROCEDURE `sp_subproject_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_current_status VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_start_date DATE,
  IN p_target_date DATE,
  IN p_completed_at DATETIME,
  IN p_completion_percent DECIMAL(5,2),
  IN p_last_reported_at DATETIME,
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_updated_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `subproject` (`project_id`, `title`, `description`, `current_status`, `priority_level`, `owner_user_id`, `start_date`, `target_date`, `completed_at`, `completion_percent`, `last_reported_at`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_project_id, p_title, p_description, p_current_status, p_priority_level, p_owner_user_id, p_start_date, p_target_date, p_completed_at, p_completion_percent, p_last_reported_at, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'subproject', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_subproject_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM subproject t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `subproject` WHERE id = p_id LIMIT 1;
    UPDATE `subproject`
    SET
    `project_id` = p_project_id,
    `title` = p_title,
    `description` = p_description,
    `current_status` = p_current_status,
    `priority_level` = p_priority_level,
    `owner_user_id` = p_owner_user_id,
    `start_date` = p_start_date,
    `target_date` = p_target_date,
    `completed_at` = p_completed_at,
    `completion_percent` = p_completion_percent,
    `last_reported_at` = p_last_reported_at,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'subproject', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_subproject_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `subproject` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('subproject', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_subproject_upsert'));
  END IF;

  SELECT * FROM `subproject` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_data
 * Purpose: Consulta registros de `task` con filtros predecibles para IA e integraciones.
 * Tables touched: task
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_task_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_data` $$
CREATE PROCEDURE `sp_task_data` (
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
  FROM `task` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: task, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_task_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_upsert` $$
CREATE PROCEDURE `sp_task_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_subproject_id BIGINT UNSIGNED,
  IN p_parent_task_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_task_type VARCHAR(50),
  IN p_current_status VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_assigned_user_id BIGINT UNSIGNED,
  IN p_reviewer_user_id BIGINT UNSIGNED,
  IN p_start_date DATE,
  IN p_due_date DATETIME,
  IN p_completed_at DATETIME,
  IN p_estimated_hours DECIMAL(8,2),
  IN p_actual_hours DECIMAL(8,2),
  IN p_completion_percent DECIMAL(5,2),
  IN p_order_index INT UNSIGNED,
  IN p_is_blocked TINYINT(1),
  IN p_last_reported_at DATETIME,
  IN p_last_activity_at DATETIME,
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_updated_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `task` (`project_id`, `subproject_id`, `parent_task_id`, `title`, `description`, `task_type`, `current_status`, `priority_level`, `owner_user_id`, `assigned_user_id`, `reviewer_user_id`, `start_date`, `due_date`, `completed_at`, `estimated_hours`, `actual_hours`, `completion_percent`, `order_index`, `is_blocked`, `last_reported_at`, `last_activity_at`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_project_id, p_subproject_id, p_parent_task_id, p_title, p_description, p_task_type, p_current_status, p_priority_level, p_owner_user_id, p_assigned_user_id, p_reviewer_user_id, p_start_date, p_due_date, p_completed_at, p_estimated_hours, p_actual_hours, p_completion_percent, p_order_index, p_is_blocked, p_last_reported_at, p_last_activity_at, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'task', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_task_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `task` WHERE id = p_id LIMIT 1;
    UPDATE `task`
    SET
    `project_id` = p_project_id,
    `subproject_id` = p_subproject_id,
    `parent_task_id` = p_parent_task_id,
    `title` = p_title,
    `description` = p_description,
    `task_type` = p_task_type,
    `current_status` = p_current_status,
    `priority_level` = p_priority_level,
    `owner_user_id` = p_owner_user_id,
    `assigned_user_id` = p_assigned_user_id,
    `reviewer_user_id` = p_reviewer_user_id,
    `start_date` = p_start_date,
    `due_date` = p_due_date,
    `completed_at` = p_completed_at,
    `estimated_hours` = p_estimated_hours,
    `actual_hours` = p_actual_hours,
    `completion_percent` = p_completion_percent,
    `order_index` = p_order_index,
    `is_blocked` = p_is_blocked,
    `last_reported_at` = p_last_reported_at,
    `last_activity_at` = p_last_activity_at,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'task', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_task_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `task` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('task', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_task_upsert'));
  END IF;

  SELECT * FROM `task` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_milestone_data
 * Purpose: Consulta registros de `milestone` con filtros predecibles para IA e integraciones.
 * Tables touched: milestone
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_milestone_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_milestone_data` $$
CREATE PROCEDURE `sp_milestone_data` (
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
  FROM `milestone` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_milestone_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: milestone, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_milestone_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_milestone_upsert` $$
CREATE PROCEDURE `sp_milestone_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_subproject_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_milestone_type VARCHAR(50),
  IN p_current_status VARCHAR(50),
  IN p_target_date DATETIME,
  IN p_completed_at DATETIME,
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_updated_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `milestone` (`project_id`, `subproject_id`, `title`, `description`, `milestone_type`, `current_status`, `target_date`, `completed_at`, `owner_user_id`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_project_id, p_subproject_id, p_title, p_description, p_milestone_type, p_current_status, p_target_date, p_completed_at, p_owner_user_id, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'milestone', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_milestone_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM milestone t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `milestone` WHERE id = p_id LIMIT 1;
    UPDATE `milestone`
    SET
    `project_id` = p_project_id,
    `subproject_id` = p_subproject_id,
    `title` = p_title,
    `description` = p_description,
    `milestone_type` = p_milestone_type,
    `current_status` = p_current_status,
    `target_date` = p_target_date,
    `completed_at` = p_completed_at,
    `owner_user_id` = p_owner_user_id,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'milestone', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_milestone_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `milestone` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('milestone', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_milestone_upsert'));
  END IF;

  SELECT * FROM `milestone` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_dependency_data
 * Purpose: Consulta registros de `task_dependency` con filtros predecibles para IA e integraciones.
 * Tables touched: task_dependency
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_task_dependency_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_dependency_data` $$
CREATE PROCEDURE `sp_task_dependency_data` (
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
  FROM `task_dependency` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM task tt JOIN project p ON p.id = tt.project_id WHERE tt.id = t.predecessor_task_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.notes, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_dependency_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: task_dependency, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_task_dependency_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_dependency_upsert` $$
CREATE PROCEDURE `sp_task_dependency_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_predecessor_task_id BIGINT UNSIGNED,
  IN p_successor_task_id BIGINT UNSIGNED,
  IN p_dependency_type VARCHAR(50),
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT p.organization_id INTO v_organization_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_predecessor_task_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_predecessor_task_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'predecessor_task_id is required';
  END IF;
  IF p_successor_task_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'successor_task_id is required';
  END IF;
    INSERT INTO `task_dependency` (`predecessor_task_id`, `successor_task_id`, `dependency_type`, `notes`)
    VALUES (p_predecessor_task_id, p_successor_task_id, p_dependency_type, p_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'task_dependency', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_task_dependency_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM task_dependency t JOIN task tt ON tt.id = t.predecessor_task_id JOIN project p ON p.id = tt.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `task_dependency`
    SET
    `predecessor_task_id` = p_predecessor_task_id,
    `successor_task_id` = p_successor_task_id,
    `dependency_type` = p_dependency_type,
    `notes` = p_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'task_dependency', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_task_dependency_upsert'));
  END IF;

  SELECT * FROM `task_dependency` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_dependency_delete
 * Purpose: Elimina fisicamente registros de `task_dependency` solo donde el modelo lo permite.
 * Tables touched: task_dependency, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_task_dependency_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_dependency_delete` $$
CREATE PROCEDURE `sp_task_dependency_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM task_dependency t JOIN task tt ON tt.id = t.predecessor_task_id JOIN project p ON p.id = tt.project_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `task_dependency` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'task_dependency', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_update_data
 * Purpose: Consulta registros de `task_update` con filtros predecibles para IA e integraciones.
 * Tables touched: task_update
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_task_update_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_update_data` $$
CREATE PROCEDURE `sp_task_update_data` (
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
  FROM `task_update` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_update_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: task_update, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_task_update_create(..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_update_create` $$
CREATE PROCEDURE `sp_task_update_create` (
  IN p_task_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_update_type VARCHAR(50),
  IN p_progress_percent_after DECIMAL(5,2),
  IN p_summary TEXT,
  IN p_blockers_summary TEXT,
  IN p_next_step TEXT,
  IN p_confidence_level VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_task_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'task_id is required';
  END IF;
  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  INSERT INTO `task_update` (`task_id`, `project_id`, `user_id`, `update_type`, `progress_percent_after`, `summary`, `blockers_summary`, `next_step`, `confidence_level`)
  VALUES (p_task_id, p_project_id, p_user_id, p_update_type, p_progress_percent_after, p_summary, p_blockers_summary, p_next_step, p_confidence_level);
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'task_update', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_task_update_create'));

  SELECT * FROM `task_update` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_update_data
 * Purpose: Consulta registros de `project_update` con filtros predecibles para IA e integraciones.
 * Tables touched: project_update
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_project_update_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_update_data` $$
CREATE PROCEDURE `sp_project_update_data` (
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
  FROM `project_update` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_update_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_update, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_update_create(..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_update_create` $$
CREATE PROCEDURE `sp_project_update_create` (
  IN p_project_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_summary TEXT,
  IN p_completion_percent_after DECIMAL(5,2),
  IN p_health_status_after VARCHAR(50),
  IN p_major_risks TEXT,
  IN p_next_actions TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  INSERT INTO `project_update` (`project_id`, `user_id`, `summary`, `completion_percent_after`, `health_status_after`, `major_risks`, `next_actions`)
  VALUES (p_project_id, p_user_id, p_summary, p_completion_percent_after, p_health_status_after, p_major_risks, p_next_actions);
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_update', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_update_create'));

  SELECT * FROM `project_update` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_blocker_data
 * Purpose: Consulta registros de `blocker` con filtros predecibles para IA e integraciones.
 * Tables touched: blocker
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_blocker_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_blocker_data` $$
CREATE PROCEDURE `sp_blocker_data` (
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
  FROM `blocker` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_blocker_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: blocker, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_blocker_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_blocker_upsert` $$
CREATE PROCEDURE `sp_blocker_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_task_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_blocker_type VARCHAR(50),
  IN p_severity_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_status VARCHAR(50),
  IN p_detected_at DATETIME,
  IN p_resolved_at DATETIME,
  IN p_resolution_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `blocker` (`project_id`, `task_id`, `title`, `description`, `blocker_type`, `severity_level`, `owner_user_id`, `status`, `detected_at`, `resolved_at`, `resolution_notes`)
    VALUES (p_project_id, p_task_id, p_title, p_description, p_blocker_type, p_severity_level, p_owner_user_id, p_status, p_detected_at, p_resolved_at, p_resolution_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'blocker', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_blocker_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM blocker t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `blocker` WHERE id = p_id LIMIT 1;
    UPDATE `blocker`
    SET
    `project_id` = p_project_id,
    `task_id` = p_task_id,
    `title` = p_title,
    `description` = p_description,
    `blocker_type` = p_blocker_type,
    `severity_level` = p_severity_level,
    `owner_user_id` = p_owner_user_id,
    `status` = p_status,
    `detected_at` = p_detected_at,
    `resolved_at` = p_resolved_at,
    `resolution_notes` = p_resolution_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'blocker', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_blocker_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `blocker` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('blocker', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_blocker_upsert'));
  END IF;

  SELECT * FROM `blocker` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_tag_data
 * Purpose: Consulta registros de `project_tag` con filtros predecibles para IA e integraciones.
 * Tables touched: project_tag
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_project_tag_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_tag_data` $$
CREATE PROCEDURE `sp_project_tag_data` (
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
  FROM `project_tag` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_tag_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_tag, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_tag_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_tag_upsert` $$
CREATE PROCEDURE `sp_project_tag_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_name VARCHAR(120),
  IN p_description TEXT,
  IN p_color_hex VARCHAR(20),
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
    INSERT INTO `project_tag` (`organization_id`, `name`, `description`, `color_hex`)
    VALUES (p_organization_id, p_name, p_description, p_color_hex);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_tag', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_tag_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM project_tag WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_tag`
    SET
    `organization_id` = p_organization_id,
    `name` = p_name,
    `description` = p_description,
    `color_hex` = p_color_hex
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_tag', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_tag_upsert'));
  END IF;

  SELECT * FROM `project_tag` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_tag_link_data
 * Purpose: Consulta registros de `project_tag_link` con filtros predecibles para IA e integraciones.
 * Tables touched: project_tag_link
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_project_tag_link_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_tag_link_data` $$
CREATE PROCEDURE `sp_project_tag_link_data` (
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
  FROM `project_tag_link` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_tag_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_tag_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_tag_link_upsert(NULL, ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_tag_link_upsert` $$
CREATE PROCEDURE `sp_project_tag_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_project_tag_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_project_tag_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_tag_id is required';
  END IF;
    INSERT INTO `project_tag_link` (`project_id`, `project_tag_id`)
    VALUES (p_project_id, p_project_tag_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_tag_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_tag_link_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM project_tag_link t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_tag_link`
    SET
    `project_id` = p_project_id,
    `project_tag_id` = p_project_tag_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_tag_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_tag_link_upsert'));
  END IF;

  SELECT * FROM `project_tag_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_tag_link_delete
 * Purpose: Elimina fisicamente registros de `project_tag_link` solo donde el modelo lo permite.
 * Tables touched: project_tag_link, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_project_tag_link_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_tag_link_delete` $$
CREATE PROCEDURE `sp_project_tag_link_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM project_tag_link t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `project_tag_link` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'project_tag_link', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_tag_link_data
 * Purpose: Consulta registros de `task_tag_link` con filtros predecibles para IA e integraciones.
 * Tables touched: task_tag_link
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_task_tag_link_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_tag_link_data` $$
CREATE PROCEDURE `sp_task_tag_link_data` (
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
  FROM `task_tag_link` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM task tt JOIN project p ON p.id = tt.project_id WHERE tt.id = t.task_id AND p.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_tag_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: task_tag_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_task_tag_link_upsert(NULL, ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_tag_link_upsert` $$
CREATE PROCEDURE `sp_task_tag_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_task_id BIGINT UNSIGNED,
  IN p_project_tag_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT p.organization_id INTO v_organization_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_task_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_task_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'task_id is required';
  END IF;
  IF p_project_tag_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_tag_id is required';
  END IF;
    INSERT INTO `task_tag_link` (`task_id`, `project_tag_id`)
    VALUES (p_task_id, p_project_tag_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'task_tag_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_task_tag_link_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM task_tag_link l JOIN task t ON t.id = l.task_id JOIN project p ON p.id = t.project_id WHERE l.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `task_tag_link`
    SET
    `task_id` = p_task_id,
    `project_tag_id` = p_project_tag_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'task_tag_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_task_tag_link_upsert'));
  END IF;

  SELECT * FROM `task_tag_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_tag_link_delete
 * Purpose: Elimina fisicamente registros de `task_tag_link` solo donde el modelo lo permite.
 * Tables touched: task_tag_link, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_task_tag_link_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_tag_link_delete` $$
CREATE PROCEDURE `sp_task_tag_link_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM task_tag_link l JOIN task t ON t.id = l.task_id JOIN project p ON p.id = t.project_id WHERE l.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `task_tag_link` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'task_tag_link', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_daily_checkin_data
 * Purpose: Consulta registros de `daily_checkin` con filtros predecibles para IA e integraciones.
 * Tables touched: daily_checkin
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_daily_checkin_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_daily_checkin_data` $$
CREATE PROCEDURE `sp_daily_checkin_data` (
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
  FROM `daily_checkin` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_daily_checkin_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: daily_checkin, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_daily_checkin_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_daily_checkin_upsert` $$
CREATE PROCEDURE `sp_daily_checkin_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_checkin_date DATE,
  IN p_status VARCHAR(50),
  IN p_summary_yesterday TEXT,
  IN p_focus_today TEXT,
  IN p_blockers TEXT,
  IN p_general_notes TEXT,
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
  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_checkin_date IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'checkin_date is required';
  END IF;
    INSERT INTO `daily_checkin` (`organization_id`, `user_id`, `checkin_date`, `status`, `summary_yesterday`, `focus_today`, `blockers`, `general_notes`)
    VALUES (p_organization_id, p_user_id, p_checkin_date, p_status, p_summary_yesterday, p_focus_today, p_blockers, p_general_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'daily_checkin', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_daily_checkin_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM daily_checkin WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `daily_checkin` WHERE id = p_id LIMIT 1;
    UPDATE `daily_checkin`
    SET
    `organization_id` = p_organization_id,
    `user_id` = p_user_id,
    `checkin_date` = p_checkin_date,
    `status` = p_status,
    `summary_yesterday` = p_summary_yesterday,
    `focus_today` = p_focus_today,
    `blockers` = p_blockers,
    `general_notes` = p_general_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'daily_checkin', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_daily_checkin_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `daily_checkin` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('daily_checkin', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_daily_checkin_upsert'));
  END IF;

  SELECT * FROM `daily_checkin` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_daily_checkin_item_data
 * Purpose: Consulta registros de `daily_checkin_item` con filtros predecibles para IA e integraciones.
 * Tables touched: daily_checkin_item
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_daily_checkin_item_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_daily_checkin_item_data` $$
CREATE PROCEDURE `sp_daily_checkin_item_data` (
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
  FROM `daily_checkin_item` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM daily_checkin d WHERE d.id = t.daily_checkin_id AND d.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.content, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_daily_checkin_item_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: daily_checkin_item, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_daily_checkin_item_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_daily_checkin_item_upsert` $$
CREATE PROCEDURE `sp_daily_checkin_item_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_daily_checkin_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_task_id BIGINT UNSIGNED,
  IN p_item_type VARCHAR(50),
  IN p_content TEXT,
  IN p_sort_order INT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM daily_checkin WHERE id = p_daily_checkin_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_daily_checkin_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'daily_checkin_id is required';
  END IF;
    INSERT INTO `daily_checkin_item` (`daily_checkin_id`, `project_id`, `task_id`, `item_type`, `content`, `sort_order`)
    VALUES (p_daily_checkin_id, p_project_id, p_task_id, p_item_type, p_content, p_sort_order);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'daily_checkin_item', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_daily_checkin_item_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM daily_checkin_item t JOIN daily_checkin p ON p.id = t.daily_checkin_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `daily_checkin_item`
    SET
    `daily_checkin_id` = p_daily_checkin_id,
    `project_id` = p_project_id,
    `task_id` = p_task_id,
    `item_type` = p_item_type,
    `content` = p_content,
    `sort_order` = p_sort_order
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'daily_checkin_item', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_daily_checkin_item_upsert'));
  END IF;

  SELECT * FROM `daily_checkin_item` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

