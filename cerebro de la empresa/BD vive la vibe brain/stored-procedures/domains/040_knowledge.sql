/**
 * Procedure: sp_knowledge_document_data
 * Purpose: Consulta registros de `knowledge_document` con filtros predecibles para IA e integraciones.
 * Tables touched: knowledge_document
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_knowledge_document_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_knowledge_document_data` $$
CREATE PROCEDURE `sp_knowledge_document_data` (
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
  FROM `knowledge_document` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.summary, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.document_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_knowledge_document_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: knowledge_document, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_knowledge_document_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_knowledge_document_upsert` $$
CREATE PROCEDURE `sp_knowledge_document_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_document_type VARCHAR(50),
  IN p_storage_type VARCHAR(50),
  IN p_external_url VARCHAR(500),
  IN p_version_label VARCHAR(50),
  IN p_status VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_summary TEXT,
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
    INSERT INTO `knowledge_document` (`organization_id`, `business_area_id`, `project_id`, `title`, `document_type`, `storage_type`, `external_url`, `version_label`, `status`, `owner_user_id`, `summary`)
    VALUES (p_organization_id, p_business_area_id, p_project_id, p_title, p_document_type, p_storage_type, p_external_url, p_version_label, p_status, p_owner_user_id, p_summary);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'knowledge_document', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_knowledge_document_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM knowledge_document WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `knowledge_document` WHERE id = p_id LIMIT 1;
    UPDATE `knowledge_document`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `project_id` = p_project_id,
    `title` = p_title,
    `document_type` = p_document_type,
    `storage_type` = p_storage_type,
    `external_url` = p_external_url,
    `version_label` = p_version_label,
    `status` = p_status,
    `owner_user_id` = p_owner_user_id,
    `summary` = p_summary
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'knowledge_document', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_knowledge_document_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `knowledge_document` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('knowledge_document', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_knowledge_document_upsert'));
  END IF;

  SELECT * FROM `knowledge_document` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_knowledge_note_data
 * Purpose: Consulta registros de `knowledge_note` con filtros predecibles para IA e integraciones.
 * Tables touched: knowledge_note
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_knowledge_note_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_knowledge_note_data` $$
CREATE PROCEDURE `sp_knowledge_note_data` (
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
  FROM `knowledge_note` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.content, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.note_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_knowledge_note_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: knowledge_note, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_knowledge_note_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_knowledge_note_upsert` $$
CREATE PROCEDURE `sp_knowledge_note_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_note_type VARCHAR(50),
  IN p_title VARCHAR(220),
  IN p_content TEXT,
  IN p_importance_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
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
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_content IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'content is required';
  END IF;
    INSERT INTO `knowledge_note` (`organization_id`, `business_area_id`, `project_id`, `note_type`, `title`, `content`, `importance_level`, `owner_user_id`)
    VALUES (p_organization_id, p_business_area_id, p_project_id, p_note_type, p_title, p_content, p_importance_level, p_owner_user_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'knowledge_note', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_knowledge_note_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM knowledge_note WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `knowledge_note`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `project_id` = p_project_id,
    `note_type` = p_note_type,
    `title` = p_title,
    `content` = p_content,
    `importance_level` = p_importance_level,
    `owner_user_id` = p_owner_user_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'knowledge_note', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_knowledge_note_upsert'));
  END IF;

  SELECT * FROM `knowledge_note` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_policy_record_data
 * Purpose: Consulta registros de `policy_record` con filtros predecibles para IA e integraciones.
 * Tables touched: policy_record
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_policy_record_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_policy_record_data` $$
CREATE PROCEDURE `sp_policy_record_data` (
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
  FROM `policy_record` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_policy_record_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: policy_record, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_policy_record_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_policy_record_upsert` $$
CREATE PROCEDURE `sp_policy_record_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_status VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_external_document_url VARCHAR(500),
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
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
    INSERT INTO `policy_record` (`organization_id`, `business_area_id`, `title`, `description`, `status`, `owner_user_id`, `external_document_url`)
    VALUES (p_organization_id, p_business_area_id, p_title, p_description, p_status, p_owner_user_id, p_external_document_url);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'policy_record', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_policy_record_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM policy_record WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `policy_record` WHERE id = p_id LIMIT 1;
    UPDATE `policy_record`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `description` = p_description,
    `status` = p_status,
    `owner_user_id` = p_owner_user_id,
    `external_document_url` = p_external_document_url
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'policy_record', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_policy_record_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `policy_record` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('policy_record', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_policy_record_upsert'));
  END IF;

  SELECT * FROM `policy_record` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_sop_data
 * Purpose: Consulta registros de `sop` con filtros predecibles para IA e integraciones.
 * Tables touched: sop
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_sop_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sop_data` $$
CREATE PROCEDURE `sp_sop_data` (
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
  FROM `sop` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.objective, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.scope, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_sop_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: sop, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_sop_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sop_upsert` $$
CREATE PROCEDURE `sp_sop_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_objective TEXT,
  IN p_scope TEXT,
  IN p_current_status VARCHAR(50),
  IN p_external_document_url VARCHAR(500),
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
    INSERT INTO `sop` (`organization_id`, `business_area_id`, `title`, `objective`, `scope`, `current_status`, `external_document_url`, `owner_user_id`)
    VALUES (p_organization_id, p_business_area_id, p_title, p_objective, p_scope, p_current_status, p_external_document_url, p_owner_user_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'sop', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_sop_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM sop WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `sop` WHERE id = p_id LIMIT 1;
    UPDATE `sop`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `objective` = p_objective,
    `scope` = p_scope,
    `current_status` = p_current_status,
    `external_document_url` = p_external_document_url,
    `owner_user_id` = p_owner_user_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'sop', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_sop_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `sop` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('sop', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_sop_upsert'));
  END IF;

  SELECT * FROM `sop` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_hypothesis_data
 * Purpose: Consulta registros de `business_hypothesis` con filtros predecibles para IA e integraciones.
 * Tables touched: business_hypothesis
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_business_hypothesis_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_hypothesis_data` $$
CREATE PROCEDURE `sp_business_hypothesis_data` (
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
  FROM `business_hypothesis` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR EXISTS (SELECT 1 FROM business_area a WHERE a.id = t.business_area_id AND a.organization_id = p_organization_id))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_business_hypothesis_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: business_hypothesis, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_business_hypothesis_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_hypothesis_upsert` $$
CREATE PROCEDURE `sp_business_hypothesis_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_expected_result TEXT,
  IN p_current_status VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM business_area WHERE id = p_business_area_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
    INSERT INTO `business_hypothesis` (`business_area_id`, `title`, `description`, `expected_result`, `current_status`, `owner_user_id`)
    VALUES (p_business_area_id, p_title, p_description, p_expected_result, p_current_status, p_owner_user_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'business_hypothesis', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_business_hypothesis_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM business_hypothesis t JOIN business_area p ON p.id = t.business_area_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `business_hypothesis` WHERE id = p_id LIMIT 1;
    UPDATE `business_hypothesis`
    SET
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `description` = p_description,
    `expected_result` = p_expected_result,
    `current_status` = p_current_status,
    `owner_user_id` = p_owner_user_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'business_hypothesis', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_business_hypothesis_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `business_hypothesis` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('business_hypothesis', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_business_hypothesis_upsert'));
  END IF;

  SELECT * FROM `business_hypothesis` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_learning_record_data
 * Purpose: Consulta registros de `learning_record` con filtros predecibles para IA e integraciones.
 * Tables touched: learning_record
 * Security: Lectura. Tabla global o tecnica; `p_organization_id` se acepta por contrato estandar pero se ignora por diseno.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_learning_record_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_learning_record_data` $$
CREATE PROCEDURE `sp_learning_record_data` (
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
  FROM `learning_record` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND 1 = 1
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.category, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.source_type, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;

/**
 * Procedure: sp_learning_record_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: learning_record, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_learning_record_create(..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_learning_record_create` $$
CREATE PROCEDURE `sp_learning_record_create` (
  IN p_source_type VARCHAR(50),
  IN p_source_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_category VARCHAR(50),
  IN p_impact_level VARCHAR(50),
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_source_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source_type is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
  INSERT INTO `learning_record` (`source_type`, `source_id`, `title`, `description`, `category`, `impact_level`, `created_by_user_id`)
  VALUES (p_source_type, p_source_id, p_title, p_description, p_category, p_impact_level, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)));
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'learning_record', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_learning_record_create'));

  SELECT * FROM `learning_record` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;

