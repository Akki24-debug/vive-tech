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
