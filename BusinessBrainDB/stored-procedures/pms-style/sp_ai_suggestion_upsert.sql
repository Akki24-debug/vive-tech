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
