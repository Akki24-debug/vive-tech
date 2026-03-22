/**
 * Procedure: sp_user_area_assignment_delete
 * Purpose: Elimina fisicamente registros de `user_area_assignment` solo donde el modelo lo permite.
 * Tables touched: user_area_assignment, audit_log
 * Security: Requiere actor valido y aplica solo a tablas puente o tecnicas.
 * Output: Result set de confirmacion de borrado.
 * Example: CALL sp_user_area_assignment_delete(1, 1, 'cleanup');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_area_assignment_delete` $$
CREATE PROCEDURE `sp_user_area_assignment_delete` (
  IN p_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_reason TEXT
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  SELECT p.organization_id INTO v_organization_id FROM user_area_assignment t JOIN business_area p ON p.id = t.business_area_id WHERE t.id = p_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  DELETE FROM `user_area_assignment` WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
  END IF;

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'delete', 'user_area_assignment', p_id, NULL, NULL, NULL, COALESCE(NULLIF(p_reason, ''), 'Deleted via stored procedure'));

  SELECT 'deleted' AS operation_status, p_id AS deleted_id;
END $$

DELIMITER ;
