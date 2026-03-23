/**
 * Procedure: sp_ai_action_proposal_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: ai_action_proposal, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_ai_action_proposal_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_action_proposal_upsert` $$
CREATE PROCEDURE `sp_ai_action_proposal_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_proposal_type VARCHAR(80),
  IN p_related_entity_type VARCHAR(50),
  IN p_related_entity_id BIGINT UNSIGNED,
  IN p_payload_json LONGTEXT,
  IN p_status VARCHAR(50),
  IN p_reviewed_by_user_id BIGINT UNSIGNED,
  IN p_reviewed_at DATETIME,
  IN p_executed_at DATETIME,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_proposal_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'proposal_type is required';
  END IF;
    INSERT INTO `ai_action_proposal` (`proposal_type`, `related_entity_type`, `related_entity_id`, `payload_json`, `status`, `reviewed_by_user_id`, `reviewed_at`, `executed_at`)
    VALUES (p_proposal_type, p_related_entity_type, p_related_entity_id, p_payload_json, p_status, p_reviewed_by_user_id, p_reviewed_at, p_executed_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'ai_action_proposal', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_ai_action_proposal_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `ai_action_proposal` WHERE id = p_id LIMIT 1;
    UPDATE `ai_action_proposal`
    SET
    `proposal_type` = p_proposal_type,
    `related_entity_type` = p_related_entity_type,
    `related_entity_id` = p_related_entity_id,
    `payload_json` = p_payload_json,
    `status` = p_status,
    `reviewed_by_user_id` = p_reviewed_by_user_id,
    `reviewed_at` = p_reviewed_at,
    `executed_at` = p_executed_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'ai_action_proposal', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_ai_action_proposal_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `ai_action_proposal` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('ai_action_proposal', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_ai_action_proposal_upsert'));
  END IF;

  SELECT * FROM `ai_action_proposal` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
