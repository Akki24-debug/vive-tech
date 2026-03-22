/**
 * Procedure: sp_alert_event_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: alert_event, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_alert_event_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_event_upsert` $$
CREATE PROCEDURE `sp_alert_event_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_alert_rule_id BIGINT UNSIGNED,
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_severity_level VARCHAR(50),
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_status VARCHAR(50),
  IN p_triggered_at DATETIME,
  IN p_acknowledged_by_user_id BIGINT UNSIGNED,
  IN p_acknowledged_at DATETIME,
  IN p_resolved_at DATETIME,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM alert_rule WHERE id = p_alert_rule_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'entity_type is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `alert_event` (`alert_rule_id`, `entity_type`, `entity_id`, `severity_level`, `title`, `description`, `status`, `triggered_at`, `acknowledged_by_user_id`, `acknowledged_at`, `resolved_at`)
    VALUES (p_alert_rule_id, p_entity_type, p_entity_id, p_severity_level, p_title, p_description, p_status, p_triggered_at, p_acknowledged_by_user_id, p_acknowledged_at, p_resolved_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'alert_event', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_alert_event_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM alert_event t JOIN alert_rule p ON p.id = t.alert_rule_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `alert_event` WHERE id = p_id LIMIT 1;
    UPDATE `alert_event`
    SET
    `alert_rule_id` = p_alert_rule_id,
    `entity_type` = p_entity_type,
    `entity_id` = p_entity_id,
    `severity_level` = p_severity_level,
    `title` = p_title,
    `description` = p_description,
    `status` = p_status,
    `triggered_at` = p_triggered_at,
    `acknowledged_by_user_id` = p_acknowledged_by_user_id,
    `acknowledged_at` = p_acknowledged_at,
    `resolved_at` = p_resolved_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'alert_event', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_alert_event_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `alert_event` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('alert_event', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_alert_event_upsert'));
  END IF;

  SELECT * FROM `alert_event` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
