/**
 * Procedure: sp_blocker_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: blocker, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_blocker_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_blocker_upsert` $$
CREATE PROCEDURE `sp_blocker_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_task_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_blocker_type VARCHAR(50),
  IN p_severity_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_status VARCHAR(50),
  IN p_detected_at DATETIME,
  IN p_resolved_at DATETIME,
  IN p_resolution_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `blocker` (`project_id`, `task_id`, `title`, `description`, `blocker_type`, `severity_level`, `owner_user_id`, `status`, `detected_at`, `resolved_at`, `resolution_notes`)
    VALUES (p_project_id, p_task_id, p_title, p_description, p_blocker_type, p_severity_level, p_owner_user_id, p_status, p_detected_at, p_resolved_at, p_resolution_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'blocker', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_blocker_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM blocker t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `blocker` WHERE id = p_id LIMIT 1;
    UPDATE `blocker`
    SET
    `project_id` = p_project_id,
    `task_id` = p_task_id,
    `title` = p_title,
    `description` = p_description,
    `blocker_type` = p_blocker_type,
    `severity_level` = p_severity_level,
    `owner_user_id` = p_owner_user_id,
    `status` = p_status,
    `detected_at` = p_detected_at,
    `resolved_at` = p_resolved_at,
    `resolution_notes` = p_resolution_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'blocker', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_blocker_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `blocker` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('blocker', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_blocker_upsert'));
  END IF;

  SELECT * FROM `blocker` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
