/**
 * Procedure: sp_blocker_resolve
 * Purpose: Actualiza `status` en `blocker` hacia `resolved` con auditoria e historial.
 * Tables touched: blocker, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_blocker_resolve(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_blocker_resolve` $$
CREATE PROCEDURE `sp_blocker_resolve` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT p.organization_id, b.status INTO v_organization_id, v_old_status FROM blocker b JOIN project p ON p.id = b.project_id WHERE b.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `blocker` SET `status` = 'resolved', resolved_at = COALESCE(resolved_at, NOW()), resolution_notes = p_notes WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('blocker', p_id, v_old_status, 'resolved', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Blocker resolved'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'blocker', p_id, 'status', v_old_status, 'resolved', COALESCE(NULLIF(p_notes, ''), 'Blocker resolved'));
  SELECT * FROM blocker WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;
