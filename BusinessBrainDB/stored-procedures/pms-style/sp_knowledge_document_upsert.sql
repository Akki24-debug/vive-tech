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
