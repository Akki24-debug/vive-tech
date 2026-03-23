/**
 * Procedure: sp_alert_rule_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: alert_rule, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_alert_rule_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_rule_upsert` $$
CREATE PROCEDURE `sp_alert_rule_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_name VARCHAR(180),
  IN p_description TEXT,
  IN p_trigger_type VARCHAR(80),
  IN p_scope_type VARCHAR(50),
  IN p_config_json LONGTEXT,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
  IF p_trigger_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'trigger_type is required';
  END IF;
    INSERT INTO `alert_rule` (`organization_id`, `name`, `description`, `trigger_type`, `scope_type`, `config_json`, `is_active`)
    VALUES (p_organization_id, p_name, p_description, p_trigger_type, p_scope_type, p_config_json, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'alert_rule', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_alert_rule_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM alert_rule WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `alert_rule`
    SET
    `organization_id` = p_organization_id,
    `name` = p_name,
    `description` = p_description,
    `trigger_type` = p_trigger_type,
    `scope_type` = p_scope_type,
    `config_json` = p_config_json,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'alert_rule', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_alert_rule_upsert'));
  END IF;

  SELECT * FROM `alert_rule` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
