/**
 * Procedure: sp_external_system_data
 * Purpose: Consulta registros de `external_system` con filtros predecibles para IA e integraciones.
 * Tables touched: external_system
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_external_system_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_system_data` $$
CREATE PROCEDURE `sp_external_system_data` (
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
  FROM `external_system` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.system_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_external_system_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: external_system, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_external_system_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_system_upsert` $$
CREATE PROCEDURE `sp_external_system_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_system_type VARCHAR(80),
  IN p_description TEXT,
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
  IF p_system_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'system_type is required';
  END IF;
    INSERT INTO `external_system` (`name`, `system_type`, `description`, `is_active`)
    VALUES (p_name, p_system_type, p_description, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'external_system', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_external_system_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `external_system`
    SET
    `name` = p_name,
    `system_type` = p_system_type,
    `description` = p_description,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'external_system', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_external_system_upsert'));
  END IF;

  SELECT * FROM `external_system` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_external_entity_link_data
 * Purpose: Consulta registros de `external_entity_link` con filtros predecibles para IA e integraciones.
 * Tables touched: external_entity_link
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_external_entity_link_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_entity_link_data` $$
CREATE PROCEDURE `sp_external_entity_link_data` (
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
  FROM `external_entity_link` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.external_entity_id, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.reference_label, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_external_entity_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: external_entity_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_external_entity_link_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_entity_link_upsert` $$
CREATE PROCEDURE `sp_external_entity_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_external_system_id BIGINT UNSIGNED,
  IN p_internal_entity_type VARCHAR(50),
  IN p_internal_entity_id BIGINT UNSIGNED,
  IN p_external_entity_type VARCHAR(50),
  IN p_external_entity_id VARCHAR(190),
  IN p_reference_label VARCHAR(190),
  IN p_metadata_json LONGTEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_external_system_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_system_id is required';
  END IF;
  IF p_internal_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'internal_entity_type is required';
  END IF;
  IF p_internal_entity_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'internal_entity_id is required';
  END IF;
  IF p_external_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_entity_type is required';
  END IF;
  IF p_external_entity_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_entity_id is required';
  END IF;
    INSERT INTO `external_entity_link` (`external_system_id`, `internal_entity_type`, `internal_entity_id`, `external_entity_type`, `external_entity_id`, `reference_label`, `metadata_json`)
    VALUES (p_external_system_id, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, p_external_entity_id, p_reference_label, p_metadata_json);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'external_entity_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_external_entity_link_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `external_entity_link`
    SET
    `external_system_id` = p_external_system_id,
    `internal_entity_type` = p_internal_entity_type,
    `internal_entity_id` = p_internal_entity_id,
    `external_entity_type` = p_external_entity_type,
    `external_entity_id` = p_external_entity_id,
    `reference_label` = p_reference_label,
    `metadata_json` = p_metadata_json
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'external_entity_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_external_entity_link_upsert'));
  END IF;

  SELECT * FROM `external_entity_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_external_entity_link_delete
 * Purpose: Elimina fisicamente registros de `external_entity_link` solo donde el modelo lo permite.
 * Tables touched: external_entity_link, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_external_entity_link_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_entity_link_delete` $$
CREATE PROCEDURE `sp_external_entity_link_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `external_entity_link` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'external_entity_link', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_sync_event_data
 * Purpose: Consulta registros de `sync_event` con filtros predecibles para IA e integraciones.
 * Tables touched: sync_event
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_sync_event_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sync_event_data` $$
CREATE PROCEDURE `sp_sync_event_data` (
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
  FROM `sync_event` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.external_entity_id, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.event_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.payload_summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_sync_event_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: sync_event, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_sync_event_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sync_event_upsert` $$
CREATE PROCEDURE `sp_sync_event_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_external_system_id BIGINT UNSIGNED,
  IN p_event_type VARCHAR(80),
  IN p_internal_entity_type VARCHAR(50),
  IN p_internal_entity_id BIGINT UNSIGNED,
  IN p_external_entity_type VARCHAR(50),
  IN p_external_entity_id VARCHAR(190),
  IN p_status VARCHAR(50),
  IN p_payload_summary TEXT,
  IN p_occurred_at DATETIME,
  IN p_processed_at DATETIME,
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

  IF p_external_system_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_system_id is required';
  END IF;
  IF p_event_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_type is required';
  END IF;
    INSERT INTO `sync_event` (`external_system_id`, `event_type`, `internal_entity_type`, `internal_entity_id`, `external_entity_type`, `external_entity_id`, `status`, `payload_summary`, `occurred_at`, `processed_at`)
    VALUES (p_external_system_id, p_event_type, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, p_external_entity_id, p_status, p_payload_summary, p_occurred_at, p_processed_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'sync_event', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_sync_event_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `sync_event` WHERE id = p_id LIMIT 1;
    UPDATE `sync_event`
    SET
    `external_system_id` = p_external_system_id,
    `event_type` = p_event_type,
    `internal_entity_type` = p_internal_entity_type,
    `internal_entity_id` = p_internal_entity_id,
    `external_entity_type` = p_external_entity_type,
    `external_entity_id` = p_external_entity_id,
    `status` = p_status,
    `payload_summary` = p_payload_summary,
    `occurred_at` = p_occurred_at,
    `processed_at` = p_processed_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'sync_event', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_sync_event_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `sync_event` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('sync_event', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_sync_event_upsert'));
  END IF;

  SELECT * FROM `sync_event` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_integration_note_data
 * Purpose: Consulta registros de `integration_note` con filtros predecibles para IA e integraciones.
 * Tables touched: integration_note
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_integration_note_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_integration_note_data` $$
CREATE PROCEDURE `sp_integration_note_data` (
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
  FROM `integration_note` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.note_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_integration_note_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: integration_note, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_integration_note_create(..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_integration_note_create` $$
CREATE PROCEDURE `sp_integration_note_create` (
  IN p_external_system_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_note_type VARCHAR(50),
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_external_system_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_system_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
  INSERT INTO `integration_note` (`external_system_id`, `title`, `description`, `note_type`, `created_by_user_id`)
  VALUES (p_external_system_id, p_title, p_description, p_note_type, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)));
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'integration_note', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_integration_note_create'));

  SELECT * FROM `integration_note` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_audit_log_data
 * Purpose: Consulta registros de `audit_log` con filtros predecibles para IA e integraciones.
 * Tables touched: audit_log
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_audit_log_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_audit_log_data` $$
CREATE PROCEDURE `sp_audit_log_data` (
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
  FROM `audit_log` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.action_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.change_summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_status_history_data
 * Purpose: Consulta registros de `status_history` con filtros predecibles para IA e integraciones.
 * Tables touched: status_history
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_status_history_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_status_history_data` $$
CREATE PROCEDURE `sp_status_history_data` (
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
  FROM `status_history` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.notes, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.id DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_lookup_catalog_data
 * Purpose: Consulta registros de `lookup_catalog` con filtros predecibles para IA e integraciones.
 * Tables touched: lookup_catalog
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_lookup_catalog_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_lookup_catalog_data` $$
CREATE PROCEDURE `sp_lookup_catalog_data` (
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
  FROM `lookup_catalog` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.name, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_lookup_catalog_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: lookup_catalog, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_lookup_catalog_upsert(NULL, ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_lookup_catalog_upsert` $$
CREATE PROCEDURE `sp_lookup_catalog_upsert` (
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
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `lookup_catalog` (`name`, `description`)
    VALUES (p_name, p_description);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'lookup_catalog', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_lookup_catalog_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `lookup_catalog`
    SET
    `name` = p_name,
    `description` = p_description
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'lookup_catalog', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_lookup_catalog_upsert'));
  END IF;

  SELECT * FROM `lookup_catalog` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_lookup_value_data
 * Purpose: Consulta registros de `lookup_value` con filtros predecibles para IA e integraciones.
 * Tables touched: lookup_value
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_lookup_value_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_lookup_value_data` $$
CREATE PROCEDURE `sp_lookup_value_data` (
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
  FROM `lookup_value` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND (COALESCE(p_only_active, 0) = 0 OR t.is_active = 1)
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.code, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.label, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_lookup_value_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: lookup_value, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_lookup_value_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_lookup_value_upsert` $$
CREATE PROCEDURE `sp_lookup_value_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_lookup_catalog_id BIGINT UNSIGNED,
  IN p_code VARCHAR(80),
  IN p_label VARCHAR(150),
  IN p_description TEXT,
  IN p_sort_order INT UNSIGNED,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM lookup_catalog WHERE id = p_lookup_catalog_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_lookup_catalog_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lookup_catalog_id is required';
  END IF;
  IF p_code IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'code is required';
  END IF;
  IF p_label IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'label is required';
  END IF;
    INSERT INTO `lookup_value` (`lookup_catalog_id`, `code`, `label`, `description`, `sort_order`, `is_active`)
    VALUES (p_lookup_catalog_id, p_code, p_label, p_description, p_sort_order, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'lookup_value', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_lookup_value_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM lookup_value t JOIN lookup_catalog p ON p.id = t.lookup_catalog_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `lookup_value`
    SET
    `lookup_catalog_id` = p_lookup_catalog_id,
    `code` = p_code,
    `label` = p_label,
    `description` = p_description,
    `sort_order` = p_sort_order,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'lookup_value', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_lookup_value_upsert'));
  END IF;

  SELECT * FROM `lookup_value` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_lookup_value_delete
 * Purpose: Elimina fisicamente registros de `lookup_value` solo donde el modelo lo permite.
 * Tables touched: lookup_value, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_lookup_value_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_lookup_value_delete` $$
CREATE PROCEDURE `sp_lookup_value_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM lookup_value t JOIN lookup_catalog p ON p.id = t.lookup_catalog_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `lookup_value` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'lookup_value', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

