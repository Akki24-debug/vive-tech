/**
 * Procedure: sp_ai_context_log_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: ai_context_log, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_ai_context_log_create(..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_context_log_create` $$
CREATE PROCEDURE `sp_ai_context_log_create` (
  IN p_interaction_source VARCHAR(80),
  IN p_source_type VARCHAR(80),
  IN p_source_id BIGINT UNSIGNED,
  IN p_purpose VARCHAR(255),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_interaction_source IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'interaction_source is required';
  END IF;
  IF p_source_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source_type is required';
  END IF;
  INSERT INTO `ai_context_log` (`interaction_source`, `source_type`, `source_id`, `purpose`)
  VALUES (p_interaction_source, p_source_type, p_source_id, p_purpose);
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'ai_context_log', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_ai_context_log_create'));

  SELECT * FROM `ai_context_log` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
