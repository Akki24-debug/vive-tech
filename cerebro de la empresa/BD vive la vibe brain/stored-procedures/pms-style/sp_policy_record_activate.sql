/**
 * Procedure: sp_policy_record_activate
 * Purpose: Actualiza `status` en `policy_record` hacia `active` con auditoria e historial.
 * Tables touched: policy_record, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_policy_record_activate(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_policy_record_activate` $$
CREATE PROCEDURE `sp_policy_record_activate` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, status INTO v_organization_id, v_old_status FROM policy_record WHERE id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `policy_record` SET `status` = 'active' WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('policy_record', p_id, v_old_status, 'active', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Policy activated'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'policy_record', p_id, 'status', v_old_status, 'active', COALESCE(NULLIF(p_notes, ''), 'Policy activated'));
  SELECT * FROM policy_record WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;
