/**
 * Procedure: sp_project_status_update
 * Purpose: Actualiza estado de proyecto, registra auditoria e historial y opcionalmente crea un `project_update`.
 * Tables touched: project, project_update, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_project_status_update(1, 'active', 20.00, 'Kickoff done', 'green', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_status_update` $$
CREATE PROCEDURE `sp_project_status_update` (
  IN p_project_id BIGINT UNSIGNED,
  IN p_new_status VARCHAR(50),
  IN p_completion_percent DECIMAL(5,2),
  IN p_summary TEXT,
  IN p_health_status VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, current_status INTO v_organization_id, v_old_status FROM project WHERE id = p_project_id LIMIT 1;
  IF v_organization_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Project not found'; END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  UPDATE project SET current_status = p_new_status, completion_percent = COALESCE(p_completion_percent, completion_percent), health_status = COALESCE(NULLIF(p_health_status, ''), health_status), completed_at = CASE WHEN p_new_status IN ('done', 'completed', 'closed') THEN COALESCE(completed_at, NOW()) ELSE completed_at END, last_activity_at = NOW(), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_project_id;
  CALL sp_status_history_insert('project', p_project_id, v_old_status, p_new_status, NULLIF(p_actor_user_id, 0), p_summary);
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'project', p_project_id, 'current_status', v_old_status, p_new_status, COALESCE(NULLIF(p_summary, ''), 'Project status update'));
  IF p_summary IS NOT NULL AND TRIM(p_summary) <> '' THEN INSERT INTO project_update (project_id, user_id, summary, completion_percent_after, health_status_after) VALUES (p_project_id, NULLIF(p_actor_user_id, 0), p_summary, COALESCE(p_completion_percent, 0), COALESCE(NULLIF(p_health_status, ''), 'green')); END IF;
  SELECT * FROM project WHERE id = p_project_id LIMIT 1;
END $$

DELIMITER ;
