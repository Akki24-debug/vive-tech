/**
 * Procedure: sp_decision_record_apply
 * Purpose: Actualiza `decision_status` en `decision_record` hacia `applied` con auditoria e historial.
 * Tables touched: decision_record, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_decision_record_apply(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_record_apply` $$
CREATE PROCEDURE `sp_decision_record_apply` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id), d.decision_status INTO v_organization_id, v_old_status FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `decision_record` SET `decision_status` = 'applied', effective_date = COALESCE(effective_date, CURDATE()) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('decision_record', p_id, v_old_status, 'applied', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Decision applied'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'decision_record', p_id, 'decision_status', v_old_status, 'applied', COALESCE(NULLIF(p_notes, ''), 'Decision applied'));
  SELECT * FROM decision_record WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;
