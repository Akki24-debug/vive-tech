/**
 * Procedure: sp_ai_insight_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: ai_insight, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_ai_insight_create(..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_insight_create` $$
CREATE PROCEDURE `sp_ai_insight_create` (
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_source_scope VARCHAR(80),
  IN p_severity_level VARCHAR(50),
  IN p_confidence_score DECIMAL(5,2),
  IN p_related_entity_type VARCHAR(50),
  IN p_related_entity_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
  INSERT INTO `ai_insight` (`title`, `description`, `source_scope`, `severity_level`, `confidence_score`, `related_entity_type`, `related_entity_id`)
  VALUES (p_title, p_description, p_source_scope, p_severity_level, p_confidence_score, p_related_entity_type, p_related_entity_id);
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'ai_insight', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_ai_insight_create'));

  SELECT * FROM `ai_insight` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
