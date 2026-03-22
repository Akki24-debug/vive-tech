/**
 * Procedure: sp_notification_preference_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: notification_preference, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_notification_preference_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_notification_preference_upsert` $$
CREATE PROCEDURE `sp_notification_preference_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_channel_type VARCHAR(50),
  IN p_alert_type VARCHAR(80),
  IN p_is_enabled TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_user_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_channel_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'channel_type is required';
  END IF;
  IF p_alert_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'alert_type is required';
  END IF;
    INSERT INTO `notification_preference` (`user_id`, `channel_type`, `alert_type`, `is_enabled`)
    VALUES (p_user_id, p_channel_type, p_alert_type, p_is_enabled);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'notification_preference', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_notification_preference_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM notification_preference t JOIN user_account p ON p.id = t.user_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `notification_preference`
    SET
    `user_id` = p_user_id,
    `channel_type` = p_channel_type,
    `alert_type` = p_alert_type,
    `is_enabled` = p_is_enabled
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'notification_preference', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_notification_preference_upsert'));
  END IF;

  SELECT * FROM `notification_preference` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
