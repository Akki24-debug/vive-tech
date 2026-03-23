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
