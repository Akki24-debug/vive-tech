/**
 * Procedure: sp_decision_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: decision_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_decision_link_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_link_upsert` $$
CREATE PROCEDURE `sp_decision_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_decision_record_id BIGINT UNSIGNED,
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_relation_type VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_decision_record_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'decision_record_id is required';
  END IF;
  IF p_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'entity_type is required';
  END IF;
  IF p_entity_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'entity_id is required';
  END IF;
    INSERT INTO `decision_link` (`decision_record_id`, `entity_type`, `entity_id`, `relation_type`)
    VALUES (p_decision_record_id, p_entity_type, p_entity_id, p_relation_type);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'decision_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_decision_link_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `decision_link`
    SET
    `decision_record_id` = p_decision_record_id,
    `entity_type` = p_entity_type,
    `entity_id` = p_entity_id,
    `relation_type` = p_relation_type
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'decision_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_decision_link_upsert'));
  END IF;

  SELECT * FROM `decision_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
