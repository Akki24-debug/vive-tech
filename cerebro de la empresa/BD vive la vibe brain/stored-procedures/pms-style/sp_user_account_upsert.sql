/**
 * Procedure: sp_user_account_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_account, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_account_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_account_upsert` $$
CREATE PROCEDURE `sp_user_account_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_first_name VARCHAR(120),
  IN p_last_name VARCHAR(120),
  IN p_display_name VARCHAR(180),
  IN p_email VARCHAR(190),
  IN p_phone VARCHAR(40),
  IN p_role_summary VARCHAR(255),
  IN p_employment_status VARCHAR(50),
  IN p_timezone VARCHAR(80),
  IN p_notes TEXT,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_first_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'first_name is required';
  END IF;
  IF p_display_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'display_name is required';
  END IF;
    INSERT INTO `user_account` (`organization_id`, `first_name`, `last_name`, `display_name`, `email`, `phone`, `role_summary`, `employment_status`, `timezone`, `notes`, `is_active`)
    VALUES (p_organization_id, p_first_name, p_last_name, p_display_name, p_email, p_phone, p_role_summary, p_employment_status, p_timezone, p_notes, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_account', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_account_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
    SELECT `employment_status` INTO v_old_status FROM `user_account` WHERE id = p_id LIMIT 1;
    UPDATE `user_account`
    SET
    `organization_id` = p_organization_id,
    `first_name` = p_first_name,
    `last_name` = p_last_name,
    `display_name` = p_display_name,
    `email` = p_email,
    `phone` = p_phone,
    `role_summary` = p_role_summary,
    `employment_status` = p_employment_status,
    `timezone` = p_timezone,
    `notes` = p_notes,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_account', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_account_upsert'));
  END IF;

  SET v_new_status = (SELECT `employment_status` FROM `user_account` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('user_account', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_user_account_upsert'));
  END IF;

  SELECT * FROM `user_account` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
