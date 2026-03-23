/**
 * Procedure: sp_milestone_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: milestone, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_milestone_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_milestone_upsert` $$
CREATE PROCEDURE `sp_milestone_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_subproject_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_milestone_type VARCHAR(50),
  IN p_current_status VARCHAR(50),
  IN p_target_date DATETIME,
  IN p_completed_at DATETIME,
  IN p_owner_user_id BIGINT UNSIGNED,
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
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
    INSERT INTO `milestone` (`project_id`, `subproject_id`, `title`, `description`, `milestone_type`, `current_status`, `target_date`, `completed_at`, `owner_user_id`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_project_id, p_subproject_id, p_title, p_description, p_milestone_type, p_current_status, p_target_date, p_completed_at, p_owner_user_id, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'milestone', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_milestone_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM milestone t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `milestone` WHERE id = p_id LIMIT 1;
    UPDATE `milestone`
    SET
    `project_id` = p_project_id,
    `subproject_id` = p_subproject_id,
    `title` = p_title,
    `description` = p_description,
    `milestone_type` = p_milestone_type,
    `current_status` = p_current_status,
    `target_date` = p_target_date,
    `completed_at` = p_completed_at,
    `owner_user_id` = p_owner_user_id,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'milestone', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_milestone_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `milestone` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('milestone', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_milestone_upsert'));
  END IF;

  SELECT * FROM `milestone` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
