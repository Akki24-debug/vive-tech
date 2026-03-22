/**
 * Procedure: sp_meeting_data
 * Purpose: Consulta registros de `meeting` con filtros predecibles para IA e integraciones.
 * Tables touched: meeting
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_meeting_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_data` $$
CREATE PROCEDURE `sp_meeting_data` (
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
  FROM `meeting` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.meeting_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_meeting_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: meeting, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_meeting_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_upsert` $$
CREATE PROCEDURE `sp_meeting_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_meeting_type VARCHAR(50),
  IN p_scheduled_start_at DATETIME,
  IN p_scheduled_end_at DATETIME,
  IN p_actual_start_at DATETIME,
  IN p_actual_end_at DATETIME,
  IN p_facilitator_user_id BIGINT UNSIGNED,
  IN p_summary TEXT,
  IN p_status VARCHAR(50),
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
    INSERT INTO `meeting` (`organization_id`, `title`, `meeting_type`, `scheduled_start_at`, `scheduled_end_at`, `actual_start_at`, `actual_end_at`, `facilitator_user_id`, `summary`, `status`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_organization_id, p_title, p_meeting_type, p_scheduled_start_at, p_scheduled_end_at, p_actual_start_at, p_actual_end_at, p_facilitator_user_id, p_summary, p_status, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'meeting', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_meeting_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM meeting WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `meeting` WHERE id = p_id LIMIT 1;
    UPDATE `meeting`
    SET
    `organization_id` = p_organization_id,
    `title` = p_title,
    `meeting_type` = p_meeting_type,
    `scheduled_start_at` = p_scheduled_start_at,
    `scheduled_end_at` = p_scheduled_end_at,
    `actual_start_at` = p_actual_start_at,
    `actual_end_at` = p_actual_end_at,
    `facilitator_user_id` = p_facilitator_user_id,
    `summary` = p_summary,
    `status` = p_status,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'meeting', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_meeting_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `meeting` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('meeting', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_meeting_upsert'));
  END IF;

  SELECT * FROM `meeting` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_meeting_participant_data
 * Purpose: Consulta registros de `meeting_participant` con filtros predecibles para IA e integraciones.
 * Tables touched: meeting_participant
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_meeting_participant_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_participant_data` $$
CREATE PROCEDURE `sp_meeting_participant_data` (
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
  FROM `meeting_participant` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id))
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_meeting_participant_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: meeting_participant, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_meeting_participant_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_participant_upsert` $$
CREATE PROCEDURE `sp_meeting_participant_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_meeting_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_participation_role VARCHAR(50),
  IN p_attended TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM meeting WHERE id = p_meeting_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_meeting_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'meeting_id is required';
  END IF;
  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
    INSERT INTO `meeting_participant` (`meeting_id`, `user_id`, `participation_role`, `attended`)
    VALUES (p_meeting_id, p_user_id, p_participation_role, p_attended);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'meeting_participant', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_meeting_participant_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM meeting_participant t JOIN meeting p ON p.id = t.meeting_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `meeting_participant`
    SET
    `meeting_id` = p_meeting_id,
    `user_id` = p_user_id,
    `participation_role` = p_participation_role,
    `attended` = p_attended
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'meeting_participant', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_meeting_participant_upsert'));
  END IF;

  SELECT * FROM `meeting_participant` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_meeting_participant_delete
 * Purpose: Elimina fisicamente registros de `meeting_participant` solo donde el modelo lo permite.
 * Tables touched: meeting_participant, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_meeting_participant_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_participant_delete` $$
CREATE PROCEDURE `sp_meeting_participant_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM meeting_participant t JOIN meeting p ON p.id = t.meeting_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `meeting_participant` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'meeting_participant', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_meeting_note_data
 * Purpose: Consulta registros de `meeting_note` con filtros predecibles para IA e integraciones.
 * Tables touched: meeting_note
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_meeting_note_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_note_data` $$
CREATE PROCEDURE `sp_meeting_note_data` (
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
  FROM `meeting_note` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.content, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.note_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_meeting_note_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: meeting_note, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_meeting_note_create(..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_note_create` $$
CREATE PROCEDURE `sp_meeting_note_create` (
  IN p_meeting_id BIGINT UNSIGNED,
  IN p_note_type VARCHAR(50),
  IN p_content TEXT,
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SELECT organization_id INTO v_organization_id FROM meeting WHERE id = p_meeting_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_meeting_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'meeting_id is required';
  END IF;
  IF p_content IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'content is required';
  END IF;
  INSERT INTO `meeting_note` (`meeting_id`, `note_type`, `content`, `created_by_user_id`)
  VALUES (p_meeting_id, p_note_type, p_content, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)));
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'meeting_note', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_meeting_note_create'));

  SELECT * FROM `meeting_note` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_decision_record_data
 * Purpose: Consulta registros de `decision_record` con filtros predecibles para IA e integraciones.
 * Tables touched: decision_record
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_decision_record_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_record_data` $$
CREATE PROCEDURE `sp_decision_record_data` (
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
  FROM `decision_record` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR (EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM project p WHERE p.id = t.project_id AND p.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id)))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_decision_record_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: decision_record, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_decision_record_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_record_upsert` $$
CREATE PROCEDURE `sp_decision_record_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_meeting_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_rationale TEXT,
  IN p_decision_status VARCHAR(50),
  IN p_impact_level VARCHAR(50),
  IN p_effective_date DATE,
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) INTO v_organization_id FROM (SELECT 1 AS anchor) x LEFT JOIN meeting m ON m.id = p_meeting_id LEFT JOIN project p ON p.id = p_project_id LEFT JOIN business_area a ON a.id = p_business_area_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
    INSERT INTO `decision_record` (`meeting_id`, `project_id`, `business_area_id`, `title`, `description`, `rationale`, `decision_status`, `impact_level`, `effective_date`, `owner_user_id`, `created_by_user_id`)
    VALUES (p_meeting_id, p_project_id, p_business_area_id, p_title, p_description, p_rationale, p_decision_status, p_impact_level, p_effective_date, p_owner_user_id, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'decision_record', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_decision_record_upsert'));
  ELSE
    SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) INTO v_organization_id FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `decision_status` INTO v_old_status FROM `decision_record` WHERE id = p_id LIMIT 1;
    UPDATE `decision_record`
    SET
    `meeting_id` = p_meeting_id,
    `project_id` = p_project_id,
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `description` = p_description,
    `rationale` = p_rationale,
    `decision_status` = p_decision_status,
    `impact_level` = p_impact_level,
    `effective_date` = p_effective_date,
    `owner_user_id` = p_owner_user_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'decision_record', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_decision_record_upsert'));
  END IF;

  SET v_new_status = (SELECT `decision_status` FROM `decision_record` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('decision_record', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_decision_record_upsert'));
  END IF;

  SELECT * FROM `decision_record` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_decision_link_data
 * Purpose: Consulta registros de `decision_link` con filtros predecibles para IA e integraciones.
 * Tables touched: decision_link
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_decision_link_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_link_data` $$
CREATE PROCEDURE `sp_decision_link_data` (
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
  FROM `decision_link` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_decision_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: decision_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_decision_link_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_link_upsert` $$
CREATE PROCEDURE `sp_decision_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_decision_record_id BIGINT UNSIGNED,
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_relation_type VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_decision_record_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'decision_record_id is required';
  END IF;
  IF p_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'entity_type is required';
  END IF;
  IF p_entity_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'entity_id is required';
  END IF;
    INSERT INTO `decision_link` (`decision_record_id`, `entity_type`, `entity_id`, `relation_type`)
    VALUES (p_decision_record_id, p_entity_type, p_entity_id, p_relation_type);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'decision_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_decision_link_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `decision_link`
    SET
    `decision_record_id` = p_decision_record_id,
    `entity_type` = p_entity_type,
    `entity_id` = p_entity_id,
    `relation_type` = p_relation_type
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'decision_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_decision_link_upsert'));
  END IF;

  SELECT * FROM `decision_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_decision_link_delete
 * Purpose: Elimina fisicamente registros de `decision_link` solo donde el modelo lo permite.
 * Tables touched: decision_link, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_decision_link_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_link_delete` $$
CREATE PROCEDURE `sp_decision_link_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `decision_link` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'decision_link', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;

/**
 * Procedure: sp_follow_up_item_data
 * Purpose: Consulta registros de `follow_up_item` con filtros predecibles para IA e integraciones.
 * Tables touched: follow_up_item
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_follow_up_item_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_follow_up_item_data` $$
CREATE PROCEDURE `sp_follow_up_item_data` (
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
  FROM `follow_up_item` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR (EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM task tt JOIN project p ON p.id = tt.project_id WHERE tt.id = t.task_id AND p.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = t.decision_record_id AND (m.organization_id = p_organization_id OR p.organization_id = p_organization_id OR a.organization_id = p_organization_id))))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_follow_up_item_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: follow_up_item, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_follow_up_item_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_follow_up_item_upsert` $$
CREATE PROCEDURE `sp_follow_up_item_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_meeting_id BIGINT UNSIGNED,
  IN p_decision_record_id BIGINT UNSIGNED,
  IN p_task_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_assigned_user_id BIGINT UNSIGNED,
  IN p_due_date DATETIME,
  IN p_status VARCHAR(50),
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
    SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) INTO v_organization_id FROM (SELECT 1 AS anchor) x LEFT JOIN meeting m ON m.id = p_meeting_id LEFT JOIN task t ON t.id = p_task_id LEFT JOIN project p ON p.id = t.project_id LEFT JOIN decision_record d ON d.id = p_decision_record_id LEFT JOIN business_area a ON a.id = d.business_area_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `follow_up_item` (`meeting_id`, `decision_record_id`, `task_id`, `title`, `description`, `assigned_user_id`, `due_date`, `status`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_meeting_id, p_decision_record_id, p_task_id, p_title, p_description, p_assigned_user_id, p_due_date, p_status, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'follow_up_item', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_follow_up_item_upsert'));
  ELSE
    SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) INTO v_organization_id FROM follow_up_item f LEFT JOIN meeting m ON m.id = f.meeting_id LEFT JOIN task t ON t.id = f.task_id LEFT JOIN project p ON p.id = t.project_id LEFT JOIN decision_record d ON d.id = f.decision_record_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE f.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `follow_up_item` WHERE id = p_id LIMIT 1;
    UPDATE `follow_up_item`
    SET
    `meeting_id` = p_meeting_id,
    `decision_record_id` = p_decision_record_id,
    `task_id` = p_task_id,
    `title` = p_title,
    `description` = p_description,
    `assigned_user_id` = p_assigned_user_id,
    `due_date` = p_due_date,
    `status` = p_status,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'follow_up_item', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_follow_up_item_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `follow_up_item` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('follow_up_item', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_follow_up_item_upsert'));
  END IF;

  SELECT * FROM `follow_up_item` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

