/**
 * Procedure: sp_meeting_participant_delete
 * Purpose: Elimina fisicamente registros de `meeting_participant` solo donde el modelo lo permite.
 * Tables touched: meeting_participant, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_meeting_participant_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_participant_delete` $$
CREATE PROCEDURE `sp_meeting_participant_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM meeting_participant t JOIN meeting p ON p.id = t.meeting_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `meeting_participant` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'meeting_participant', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;
