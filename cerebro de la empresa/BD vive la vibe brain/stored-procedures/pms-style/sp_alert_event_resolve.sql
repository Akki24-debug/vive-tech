/**
 * Procedure: sp_alert_event_resolve
 * Purpose: Actualiza `status` en `alert_event` hacia `resolved` con auditoria e historial.
 * Tables touched: alert_event, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_alert_event_resolve(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_event_resolve` $$
CREATE PROCEDURE `sp_alert_event_resolve` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT ar.organization_id, ae.status INTO v_organization_id, v_old_status FROM alert_event ae LEFT JOIN alert_rule ar ON ar.id = ae.alert_rule_id WHERE ae.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `alert_event` SET `status` = 'resolved', resolved_at = COALESCE(resolved_at, NOW()), acknowledged_by_user_id = COALESCE(acknowledged_by_user_id, NULLIF(p_actor_user_id, 0)), acknowledged_at = COALESCE(acknowledged_at, NOW()) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('alert_event', p_id, v_old_status, 'resolved', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Alert resolved'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'alert_event', p_id, 'status', v_old_status, 'resolved', COALESCE(NULLIF(p_notes, ''), 'Alert resolved'));
  SELECT * FROM alert_event WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;
