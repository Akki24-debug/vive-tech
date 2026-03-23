/**
 * Procedure: sp_reminder_data
 * Purpose: Consulta registros de `reminder` con filtros predecibles para IA e integraciones.
 * Tables touched: reminder
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_reminder_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reminder_data` $$
CREATE PROCEDURE `sp_reminder_data` (
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
  FROM `reminder` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_reminder_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: reminder, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_reminder_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reminder_upsert` $$
CREATE PROCEDURE `sp_reminder_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_remind_at DATETIME,
  IN p_status VARCHAR(50),
  IN p_delivery_channel VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_user_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_remind_at IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'remind_at is required';
  END IF;
    INSERT INTO `reminder` (`user_id`, `entity_type`, `entity_id`, `title`, `description`, `remind_at`, `status`, `delivery_channel`)
    VALUES (p_user_id, p_entity_type, p_entity_id, p_title, p_description, p_remind_at, p_status, p_delivery_channel);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'reminder', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_reminder_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM reminder t JOIN user_account p ON p.id = t.user_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `reminder` WHERE id = p_id LIMIT 1;
    UPDATE `reminder`
    SET
    `user_id` = p_user_id,
    `entity_type` = p_entity_type,
    `entity_id` = p_entity_id,
    `title` = p_title,
    `description` = p_description,
    `remind_at` = p_remind_at,
    `status` = p_status,
    `delivery_channel` = p_delivery_channel
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'reminder', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_reminder_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `reminder` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('reminder', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_reminder_upsert'));
  END IF;

  SELECT * FROM `reminder` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_alert_rule_data
 * Purpose: Consulta registros de `alert_rule` con filtros predecibles para IA e integraciones.
 * Tables touched: alert_rule
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_alert_rule_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_rule_data` $$
CREATE PROCEDURE `sp_alert_rule_data` (
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
  FROM `alert_rule` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.trigger_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_alert_rule_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: alert_rule, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_alert_rule_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_rule_upsert` $$
CREATE PROCEDURE `sp_alert_rule_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_name VARCHAR(180),
  IN p_description TEXT,
  IN p_trigger_type VARCHAR(80),
  IN p_scope_type VARCHAR(50),
  IN p_config_json LONGTEXT,
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
  IF p_trigger_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'trigger_type is required';
  END IF;
    INSERT INTO `alert_rule` (`organization_id`, `name`, `description`, `trigger_type`, `scope_type`, `config_json`, `is_active`)
    VALUES (p_organization_id, p_name, p_description, p_trigger_type, p_scope_type, p_config_json, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'alert_rule', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_alert_rule_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM alert_rule WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `alert_rule`
    SET
    `organization_id` = p_organization_id,
    `name` = p_name,
    `description` = p_description,
    `trigger_type` = p_trigger_type,
    `scope_type` = p_scope_type,
    `config_json` = p_config_json,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'alert_rule', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_alert_rule_upsert'));
  END IF;

  SELECT * FROM `alert_rule` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_alert_event_data
 * Purpose: Consulta registros de `alert_event` con filtros predecibles para IA e integraciones.
 * Tables touched: alert_event
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_alert_event_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_event_data` $$
CREATE PROCEDURE `sp_alert_event_data` (
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
  FROM `alert_event` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM alert_rule ar WHERE ar.id = t.alert_rule_id AND ar.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_alert_event_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: alert_event, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_alert_event_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_event_upsert` $$
CREATE PROCEDURE `sp_alert_event_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_alert_rule_id BIGINT UNSIGNED,
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_severity_level VARCHAR(50),
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_status VARCHAR(50),
  IN p_triggered_at DATETIME,
  IN p_acknowledged_by_user_id BIGINT UNSIGNED,
  IN p_acknowledged_at DATETIME,
  IN p_resolved_at DATETIME,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM alert_rule WHERE id = p_alert_rule_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'entity_type is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `alert_event` (`alert_rule_id`, `entity_type`, `entity_id`, `severity_level`, `title`, `description`, `status`, `triggered_at`, `acknowledged_by_user_id`, `acknowledged_at`, `resolved_at`)
    VALUES (p_alert_rule_id, p_entity_type, p_entity_id, p_severity_level, p_title, p_description, p_status, p_triggered_at, p_acknowledged_by_user_id, p_acknowledged_at, p_resolved_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'alert_event', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_alert_event_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM alert_event t JOIN alert_rule p ON p.id = t.alert_rule_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `alert_event` WHERE id = p_id LIMIT 1;
    UPDATE `alert_event`
    SET
    `alert_rule_id` = p_alert_rule_id,
    `entity_type` = p_entity_type,
    `entity_id` = p_entity_id,
    `severity_level` = p_severity_level,
    `title` = p_title,
    `description` = p_description,
    `status` = p_status,
    `triggered_at` = p_triggered_at,
    `acknowledged_by_user_id` = p_acknowledged_by_user_id,
    `acknowledged_at` = p_acknowledged_at,
    `resolved_at` = p_resolved_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'alert_event', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_alert_event_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `alert_event` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('alert_event', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_alert_event_upsert'));
  END IF;

  SELECT * FROM `alert_event` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_notification_preference_data
 * Purpose: Consulta registros de `notification_preference` con filtros predecibles para IA e integraciones.
 * Tables touched: notification_preference
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_notification_preference_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_notification_preference_data` $$
CREATE PROCEDURE `sp_notification_preference_data` (
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
  FROM `notification_preference` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM user_account u WHERE u.id = t.user_id AND u.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_notification_preference_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: notification_preference, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_notification_preference_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_notification_preference_upsert` $$
CREATE PROCEDURE `sp_notification_preference_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_channel_type VARCHAR(50),
  IN p_alert_type VARCHAR(80),
  IN p_is_enabled TINYINT(1),
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
  IF p_channel_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'channel_type is required';
  END IF;
  IF p_alert_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'alert_type is required';
  END IF;
    INSERT INTO `notification_preference` (`user_id`, `channel_type`, `alert_type`, `is_enabled`)
    VALUES (p_user_id, p_channel_type, p_alert_type, p_is_enabled);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'notification_preference', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_notification_preference_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM notification_preference t JOIN user_account p ON p.id = t.user_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `notification_preference`
    SET
    `user_id` = p_user_id,
    `channel_type` = p_channel_type,
    `alert_type` = p_alert_type,
    `is_enabled` = p_is_enabled
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'notification_preference', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_notification_preference_upsert'));
  END IF;

  SELECT * FROM `notification_preference` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_suggestion_data
 * Purpose: Consulta registros de `ai_suggestion` con filtros predecibles para IA e integraciones.
 * Tables touched: ai_suggestion
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_ai_suggestion_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_suggestion_data` $$
CREATE PROCEDURE `sp_ai_suggestion_data` (
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
  FROM `ai_suggestion` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR (EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id)))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_suggestion_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: ai_suggestion, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_ai_suggestion_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_suggestion_upsert` $$
CREATE PROCEDURE `sp_ai_suggestion_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_related_entity_type VARCHAR(50),
  IN p_related_entity_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_suggestion_type VARCHAR(80),
  IN p_impact_estimate VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_review_status VARCHAR(50),
  IN p_reviewed_by_user_id BIGINT UNSIGNED,
  IN p_reviewed_at DATETIME,
  IN p_implementation_task_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT COALESCE(p.organization_id, a.organization_id) INTO v_organization_id FROM (SELECT 1 AS anchor) x LEFT JOIN project p ON p.id = p_project_id LEFT JOIN business_area a ON a.id = p_business_area_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_related_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'related_entity_type is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
    INSERT INTO `ai_suggestion` (`related_entity_type`, `related_entity_id`, `business_area_id`, `project_id`, `title`, `description`, `suggestion_type`, `impact_estimate`, `priority_level`, `review_status`, `reviewed_by_user_id`, `reviewed_at`, `implementation_task_id`)
    VALUES (p_related_entity_type, p_related_entity_id, p_business_area_id, p_project_id, p_title, p_description, p_suggestion_type, p_impact_estimate, p_priority_level, p_review_status, p_reviewed_by_user_id, p_reviewed_at, p_implementation_task_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'ai_suggestion', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_ai_suggestion_upsert'));
  ELSE
    SELECT COALESCE(p.organization_id, a.organization_id) INTO v_organization_id FROM ai_suggestion s LEFT JOIN project p ON p.id = s.project_id LEFT JOIN business_area a ON a.id = s.business_area_id WHERE s.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `review_status` INTO v_old_status FROM `ai_suggestion` WHERE id = p_id LIMIT 1;
    UPDATE `ai_suggestion`
    SET
    `related_entity_type` = p_related_entity_type,
    `related_entity_id` = p_related_entity_id,
    `business_area_id` = p_business_area_id,
    `project_id` = p_project_id,
    `title` = p_title,
    `description` = p_description,
    `suggestion_type` = p_suggestion_type,
    `impact_estimate` = p_impact_estimate,
    `priority_level` = p_priority_level,
    `review_status` = p_review_status,
    `reviewed_by_user_id` = p_reviewed_by_user_id,
    `reviewed_at` = p_reviewed_at,
    `implementation_task_id` = p_implementation_task_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'ai_suggestion', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_ai_suggestion_upsert'));
  END IF;

  SET v_new_status = (SELECT `review_status` FROM `ai_suggestion` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('ai_suggestion', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_ai_suggestion_upsert'));
  END IF;

  SELECT * FROM `ai_suggestion` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_insight_data
 * Purpose: Consulta registros de `ai_insight` con filtros predecibles para IA e integraciones.
 * Tables touched: ai_insight
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_ai_insight_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_insight_data` $$
CREATE PROCEDURE `sp_ai_insight_data` (
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
  FROM `ai_insight` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_insight_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: ai_insight, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_ai_insight_create(..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_insight_create` $$
CREATE PROCEDURE `sp_ai_insight_create` (
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_source_scope VARCHAR(80),
  IN p_severity_level VARCHAR(50),
  IN p_confidence_score DECIMAL(5,2),
  IN p_related_entity_type VARCHAR(50),
  IN p_related_entity_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
  INSERT INTO `ai_insight` (`title`, `description`, `source_scope`, `severity_level`, `confidence_score`, `related_entity_type`, `related_entity_id`)
  VALUES (p_title, p_description, p_source_scope, p_severity_level, p_confidence_score, p_related_entity_type, p_related_entity_id);
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'ai_insight', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_ai_insight_create'));

  SELECT * FROM `ai_insight` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_action_proposal_data
 * Purpose: Consulta registros de `ai_action_proposal` con filtros predecibles para IA e integraciones.
 * Tables touched: ai_action_proposal
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_ai_action_proposal_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_action_proposal_data` $$
CREATE PROCEDURE `sp_ai_action_proposal_data` (
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
  FROM `ai_action_proposal` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_action_proposal_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: ai_action_proposal, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_ai_action_proposal_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_action_proposal_upsert` $$
CREATE PROCEDURE `sp_ai_action_proposal_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_proposal_type VARCHAR(80),
  IN p_related_entity_type VARCHAR(50),
  IN p_related_entity_id BIGINT UNSIGNED,
  IN p_payload_json LONGTEXT,
  IN p_status VARCHAR(50),
  IN p_reviewed_by_user_id BIGINT UNSIGNED,
  IN p_reviewed_at DATETIME,
  IN p_executed_at DATETIME,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_proposal_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'proposal_type is required';
  END IF;
    INSERT INTO `ai_action_proposal` (`proposal_type`, `related_entity_type`, `related_entity_id`, `payload_json`, `status`, `reviewed_by_user_id`, `reviewed_at`, `executed_at`)
    VALUES (p_proposal_type, p_related_entity_type, p_related_entity_id, p_payload_json, p_status, p_reviewed_by_user_id, p_reviewed_at, p_executed_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'ai_action_proposal', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_ai_action_proposal_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `ai_action_proposal` WHERE id = p_id LIMIT 1;
    UPDATE `ai_action_proposal`
    SET
    `proposal_type` = p_proposal_type,
    `related_entity_type` = p_related_entity_type,
    `related_entity_id` = p_related_entity_id,
    `payload_json` = p_payload_json,
    `status` = p_status,
    `reviewed_by_user_id` = p_reviewed_by_user_id,
    `reviewed_at` = p_reviewed_at,
    `executed_at` = p_executed_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'ai_action_proposal', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_ai_action_proposal_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `ai_action_proposal` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('ai_action_proposal', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_ai_action_proposal_upsert'));
  END IF;

  SELECT * FROM `ai_action_proposal` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_context_log_data
 * Purpose: Consulta registros de `ai_context_log` con filtros predecibles para IA e integraciones.
 * Tables touched: ai_context_log
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_ai_context_log_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_context_log_data` $$
CREATE PROCEDURE `sp_ai_context_log_data` (
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
  FROM `ai_context_log` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.source_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.purpose, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_context_log_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: ai_context_log, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_ai_context_log_create(..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_context_log_create` $$
CREATE PROCEDURE `sp_ai_context_log_create` (
  IN p_interaction_source VARCHAR(80),
  IN p_source_type VARCHAR(80),
  IN p_source_id BIGINT UNSIGNED,
  IN p_purpose VARCHAR(255),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_interaction_source IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'interaction_source is required';
  END IF;
  IF p_source_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source_type is required';
  END IF;
  INSERT INTO `ai_context_log` (`interaction_source`, `source_type`, `source_id`, `purpose`)
  VALUES (p_interaction_source, p_source_type, p_source_id, p_purpose);
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'ai_context_log', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_ai_context_log_create'));

  SELECT * FROM `ai_context_log` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_automation_rule_data
 * Purpose: Consulta registros de `automation_rule` con filtros predecibles para IA e integraciones.
 * Tables touched: automation_rule
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_automation_rule_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_rule_data` $$
CREATE PROCEDURE `sp_automation_rule_data` (
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
  FROM `automation_rule` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.trigger_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.action_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_automation_rule_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: automation_rule, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_automation_rule_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_rule_upsert` $$
CREATE PROCEDURE `sp_automation_rule_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_name VARCHAR(180),
  IN p_description TEXT,
  IN p_trigger_type VARCHAR(80),
  IN p_action_type VARCHAR(80),
  IN p_config_json LONGTEXT,
  IN p_requires_approval TINYINT(1),
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
  IF p_trigger_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'trigger_type is required';
  END IF;
  IF p_action_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'action_type is required';
  END IF;
    INSERT INTO `automation_rule` (`name`, `description`, `trigger_type`, `action_type`, `config_json`, `requires_approval`, `is_active`)
    VALUES (p_name, p_description, p_trigger_type, p_action_type, p_config_json, p_requires_approval, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'automation_rule', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_automation_rule_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `automation_rule`
    SET
    `name` = p_name,
    `description` = p_description,
    `trigger_type` = p_trigger_type,
    `action_type` = p_action_type,
    `config_json` = p_config_json,
    `requires_approval` = p_requires_approval,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'automation_rule', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_automation_rule_upsert'));
  END IF;

  SELECT * FROM `automation_rule` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_automation_run_data
 * Purpose: Consulta registros de `automation_run` con filtros predecibles para IA e integraciones.
 * Tables touched: automation_run
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_automation_run_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_run_data` $$
CREATE PROCEDURE `sp_automation_run_data` (
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
  FROM `automation_run` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.execution_summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_automation_run_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: automation_run, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_automation_run_upsert(NULL, ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_run_upsert` $$
CREATE PROCEDURE `sp_automation_run_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_automation_rule_id BIGINT UNSIGNED,
  IN p_status VARCHAR(50),
  IN p_execution_summary TEXT,
  IN p_triggered_at DATETIME,
  IN p_completed_at DATETIME,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_automation_rule_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'automation_rule_id is required';
  END IF;
    INSERT INTO `automation_run` (`automation_rule_id`, `status`, `execution_summary`, `triggered_at`, `completed_at`)
    VALUES (p_automation_rule_id, p_status, p_execution_summary, p_triggered_at, p_completed_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'automation_run', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_automation_run_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `automation_run` WHERE id = p_id LIMIT 1;
    UPDATE `automation_run`
    SET
    `automation_rule_id` = p_automation_rule_id,
    `status` = p_status,
    `execution_summary` = p_execution_summary,
    `triggered_at` = p_triggered_at,
    `completed_at` = p_completed_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'automation_run', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_automation_run_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `automation_run` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('automation_run', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_automation_run_upsert'));
  END IF;

  SELECT * FROM `automation_run` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

