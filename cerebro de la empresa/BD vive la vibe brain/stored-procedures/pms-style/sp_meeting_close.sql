/**
 * Procedure: sp_meeting_close
 * Purpose: Actualiza `status` en `meeting` hacia `completed` con auditoria e historial.
 * Tables touched: meeting, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_meeting_close(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_close` $$
CREATE PROCEDURE `sp_meeting_close` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, status INTO v_organization_id, v_old_status FROM meeting WHERE id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `meeting` SET `status` = 'completed', actual_end_at = COALESCE(actual_end_at, NOW()), summary = COALESCE(NULLIF(p_notes, ''), summary), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('meeting', p_id, v_old_status, 'completed', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Meeting closed'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'meeting', p_id, 'status', v_old_status, 'completed', COALESCE(NULLIF(p_notes, ''), 'Meeting closed'));
  SELECT * FROM meeting WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;
