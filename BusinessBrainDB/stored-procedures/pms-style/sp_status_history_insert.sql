/**
 * Procedure: sp_status_history_insert
 * Purpose: Inserta un registro estandarizado en `status_history`.
 * Tables touched: status_history
 * Security: Helper interno. Se usa solo cuando cambia un campo de estado real.
 * Output: Sin salida. Inserta una fila en historial de estado.
 * Example: CALL sp_status_history_insert('task', 10, 'pending', 'done', 1, 'Manual close');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_status_history_insert` $$
CREATE PROCEDURE `sp_status_history_insert` (
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_old_status VARCHAR(50),
  IN p_new_status VARCHAR(50),
  IN p_changed_by_user_id BIGINT UNSIGNED,
  IN p_notes TEXT
)
proc:BEGIN
  IF COALESCE(p_old_status, '__NULL__') = COALESCE(p_new_status, '__NULL__') THEN
    LEAVE proc;
  END IF;
  INSERT INTO status_history (
    entity_type, entity_id, old_status, new_status, changed_by_user_id, notes
  ) VALUES (
    p_entity_type,
    p_entity_id,
    p_old_status,
    p_new_status,
    NULLIF(p_changed_by_user_id, 0),
    p_notes
  );
END $$

DELIMITER ;
