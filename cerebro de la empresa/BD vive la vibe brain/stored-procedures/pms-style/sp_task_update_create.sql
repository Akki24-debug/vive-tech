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
