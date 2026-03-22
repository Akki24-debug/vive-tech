/**
 * Procedure: sp_reminder_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: reminder, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_reminder_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reminder_upsert` $$
CREATE PROCEDURE `sp_reminder_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_remind_at DATETIME,
  IN p_status VARCHAR(50),
  IN p_delivery_channel VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_user_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_remind_at IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'remind_at is required';
  END IF;
    INSERT INTO `reminder` (`user_id`, `entity_type`, `entity_id`, `title`, `description`, `remind_at`, `status`, `delivery_channel`)
    VALUES (p_user_id, p_entity_type, p_entity_id, p_title, p_description, p_remind_at, p_status, p_delivery_channel);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'reminder', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_reminder_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM reminder t JOIN user_account p ON p.id = t.user_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `reminder` WHERE id = p_id LIMIT 1;
    UPDATE `reminder`
    SET
    `user_id` = p_user_id,
    `entity_type` = p_entity_type,
    `entity_id` = p_entity_id,
    `title` = p_title,
    `description` = p_description,
    `remind_at` = p_remind_at,
    `status` = p_status,
    `delivery_channel` = p_delivery_channel
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'reminder', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_reminder_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `reminder` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('reminder', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_reminder_upsert'));
  END IF;

  SELECT * FROM `reminder` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
