/**
 * Procedure: sp_meeting_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: meeting, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_meeting_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_upsert` $$
CREATE PROCEDURE `sp_meeting_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_meeting_type VARCHAR(50),
  IN p_scheduled_start_at DATETIME,
  IN p_scheduled_end_at DATETIME,
  IN p_actual_start_at DATETIME,
  IN p_actual_end_at DATETIME,
  IN p_facilitator_user_id BIGINT UNSIGNED,
  IN p_summary TEXT,
  IN p_status VARCHAR(50),
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_updated_by_user_id BIGINT UNSIGNED,
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
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `meeting` (`organization_id`, `title`, `meeting_type`, `scheduled_start_at`, `scheduled_end_at`, `actual_start_at`, `actual_end_at`, `facilitator_user_id`, `summary`, `status`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_organization_id, p_title, p_meeting_type, p_scheduled_start_at, p_scheduled_end_at, p_actual_start_at, p_actual_end_at, p_facilitator_user_id, p_summary, p_status, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'meeting', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_meeting_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM meeting WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `meeting` WHERE id = p_id LIMIT 1;
    UPDATE `meeting`
    SET
    `organization_id` = p_organization_id,
    `title` = p_title,
    `meeting_type` = p_meeting_type,
    `scheduled_start_at` = p_scheduled_start_at,
    `scheduled_end_at` = p_scheduled_end_at,
    `actual_start_at` = p_actual_start_at,
    `actual_end_at` = p_actual_end_at,
    `facilitator_user_id` = p_facilitator_user_id,
    `summary` = p_summary,
    `status` = p_status,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'meeting', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_meeting_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `meeting` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('meeting', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_meeting_upsert'));
  END IF;

  SELECT * FROM `meeting` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
