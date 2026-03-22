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
