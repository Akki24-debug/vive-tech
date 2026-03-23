/**
 * Procedure: sp_automation_run_finalize
 * Purpose: Finaliza una ejecucion de automatizacion.
 * Tables touched: automation_run, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_automation_run_finalize(1, 'completed', 'ok', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_run_finalize` $$
CREATE PROCEDURE `sp_automation_run_finalize` (
  IN p_automation_run_id BIGINT UNSIGNED,
  IN p_status VARCHAR(50),
  IN p_execution_summary TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  CALL sp_actor_assert(p_actor_user_id, NULL, 0);
  SELECT status INTO v_old_status FROM automation_run WHERE id = p_automation_run_id LIMIT 1;
  UPDATE automation_run SET status = p_status, execution_summary = p_execution_summary, completed_at = COALESCE(completed_at, NOW()) WHERE id = p_automation_run_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Automation run not found'; END IF;
  CALL sp_status_history_insert('automation_run', p_automation_run_id, v_old_status, p_status, NULLIF(p_actor_user_id, 0), p_execution_summary);
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'finalize', 'automation_run', p_automation_run_id, 'status', v_old_status, p_status, COALESCE(NULLIF(p_execution_summary, ''), 'Automation run finalized'));
  SELECT * FROM automation_run WHERE id = p_automation_run_id LIMIT 1;
END $$

DELIMITER ;
