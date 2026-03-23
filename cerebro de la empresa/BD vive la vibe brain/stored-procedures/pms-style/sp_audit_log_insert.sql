/**
 * Procedure: sp_audit_log_insert
 * Purpose: Inserta un registro estandarizado en `audit_log`.
 * Tables touched: audit_log
 * Security: Helper interno. No valida actor por si mismo.
 * Output: Sin salida. Inserta una fila en auditoria.
 * Example: CALL sp_audit_log_insert(1, 'update', 'project', 10, NULL, NULL, NULL, 'Updated project');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_audit_log_insert` $$
CREATE PROCEDURE `sp_audit_log_insert` (
  IN p_user_id BIGINT UNSIGNED,
  IN p_action_type VARCHAR(80),
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_field_name VARCHAR(120),
  IN p_old_value TEXT,
  IN p_new_value TEXT,
  IN p_change_summary TEXT
)
proc:BEGIN
  INSERT INTO audit_log (
    user_id, action_type, entity_type, entity_id, field_name, old_value, new_value, change_summary
  ) VALUES (
    NULLIF(p_user_id, 0),
    COALESCE(NULLIF(p_action_type, ''), 'unknown'),
    COALESCE(NULLIF(p_entity_type, ''), 'unknown'),
    p_entity_id,
    NULLIF(p_field_name, ''),
    p_old_value,
    p_new_value,
    p_change_summary
  );
END $$

DELIMITER ;
