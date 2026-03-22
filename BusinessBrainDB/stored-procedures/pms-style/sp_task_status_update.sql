/**
 * Procedure: sp_task_status_update
 * Purpose: Actualiza estado de tarea, registra auditoria e historial y opcionalmente crea un `task_update`.
 * Tables touched: task, task_update, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_task_status_update(1, 'done', 100.00, 'Closed', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_status_update` $$
CREATE PROCEDURE `sp_task_status_update` (
  IN p_task_id BIGINT UNSIGNED,
  IN p_new_status VARCHAR(50),
  IN p_completion_percent DECIMAL(5,2),
  IN p_summary TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_project_id BIGINT UNSIGNED DEFAULT NULL;
  SELECT p.organization_id, t.current_status, t.project_id INTO v_organization_id, v_old_status, v_project_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_task_id LIMIT 1;
  IF v_organization_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Task not found'; END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  UPDATE task SET current_status = p_new_status, completion_percent = COALESCE(p_completion_percent, completion_percent), completed_at = CASE WHEN p_new_status IN ('done', 'completed', 'closed') THEN COALESCE(completed_at, NOW()) ELSE completed_at END, last_activity_at = NOW(), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_task_id;
  CALL sp_status_history_insert('task', p_task_id, v_old_status, p_new_status, NULLIF(p_actor_user_id, 0), p_summary);
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'task', p_task_id, 'current_status', v_old_status, p_new_status, COALESCE(NULLIF(p_summary, ''), 'Task status update'));
  IF p_summary IS NOT NULL AND TRIM(p_summary) <> '' THEN INSERT INTO task_update (task_id, project_id, user_id, update_type, progress_percent_after, summary) VALUES (p_task_id, v_project_id, NULLIF(p_actor_user_id, 0), 'status_update', COALESCE(p_completion_percent, 0), p_summary); END IF;
  SELECT * FROM task WHERE id = p_task_id LIMIT 1;
END $$

DELIMITER ;
