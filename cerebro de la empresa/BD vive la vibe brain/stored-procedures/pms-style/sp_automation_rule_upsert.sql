/**
 * Procedure: sp_automation_rule_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: automation_rule, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_automation_rule_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_rule_upsert` $$
CREATE PROCEDURE `sp_automation_rule_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_name VARCHAR(180),
  IN p_description TEXT,
  IN p_trigger_type VARCHAR(80),
  IN p_action_type VARCHAR(80),
  IN p_config_json LONGTEXT,
  IN p_requires_approval TINYINT(1),
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
  IF p_trigger_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'trigger_type is required';
  END IF;
  IF p_action_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'action_type is required';
  END IF;
    INSERT INTO `automation_rule` (`name`, `description`, `trigger_type`, `action_type`, `config_json`, `requires_approval`, `is_active`)
    VALUES (p_name, p_description, p_trigger_type, p_action_type, p_config_json, p_requires_approval, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'automation_rule', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_automation_rule_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `automation_rule`
    SET
    `name` = p_name,
    `description` = p_description,
    `trigger_type` = p_trigger_type,
    `action_type` = p_action_type,
    `config_json` = p_config_json,
    `requires_approval` = p_requires_approval,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'automation_rule', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_automation_rule_upsert'));
  END IF;

  SELECT * FROM `automation_rule` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
