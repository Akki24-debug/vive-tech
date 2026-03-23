/**
 * Procedure: sp_follow_up_complete
 * Purpose: Actualiza `status` en `follow_up_item` hacia `completed` con auditoria e historial.
 * Tables touched: follow_up_item, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_follow_up_complete(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_follow_up_complete` $$
CREATE PROCEDURE `sp_follow_up_complete` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id), f.status INTO v_organization_id, v_old_status FROM follow_up_item f LEFT JOIN meeting m ON m.id = f.meeting_id LEFT JOIN task t ON t.id = f.task_id LEFT JOIN project p ON p.id = t.project_id LEFT JOIN decision_record d ON d.id = f.decision_record_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE f.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `follow_up_item` SET `status` = 'completed', updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('follow_up_item', p_id, v_old_status, 'completed', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Follow up completed'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'follow_up_item', p_id, 'status', v_old_status, 'completed', COALESCE(NULLIF(p_notes, ''), 'Follow up completed'));
  SELECT * FROM follow_up_item WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;
