/**
 * Procedure: sp_daily_checkin_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: daily_checkin, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_daily_checkin_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_daily_checkin_upsert` $$
CREATE PROCEDURE `sp_daily_checkin_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_checkin_date DATE,
  IN p_status VARCHAR(50),
  IN p_summary_yesterday TEXT,
  IN p_focus_today TEXT,
  IN p_blockers TEXT,
  IN p_general_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_checkin_date IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'checkin_date is required';
  END IF;
    INSERT INTO `daily_checkin` (`organization_id`, `user_id`, `checkin_date`, `status`, `summary_yesterday`, `focus_today`, `blockers`, `general_notes`)
    VALUES (p_organization_id, p_user_id, p_checkin_date, p_status, p_summary_yesterday, p_focus_today, p_blockers, p_general_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'daily_checkin', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_daily_checkin_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM daily_checkin WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `daily_checkin` WHERE id = p_id LIMIT 1;
    UPDATE `daily_checkin`
    SET
    `organization_id` = p_organization_id,
    `user_id` = p_user_id,
    `checkin_date` = p_checkin_date,
    `status` = p_status,
    `summary_yesterday` = p_summary_yesterday,
    `focus_today` = p_focus_today,
    `blockers` = p_blockers,
    `general_notes` = p_general_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'daily_checkin', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_daily_checkin_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `daily_checkin` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('daily_checkin', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_daily_checkin_upsert'));
  END IF;

  SELECT * FROM `daily_checkin` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
