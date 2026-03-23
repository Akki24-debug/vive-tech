/**
 * Procedure: sp_task_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: task, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_task_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_upsert` $$
CREATE PROCEDURE `sp_task_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_subproject_id BIGINT UNSIGNED,
  IN p_parent_task_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_task_type VARCHAR(50),
  IN p_current_status VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_assigned_user_id BIGINT UNSIGNED,
  IN p_reviewer_user_id BIGINT UNSIGNED,
  IN p_start_date DATE,
  IN p_due_date DATETIME,
  IN p_completed_at DATETIME,
  IN p_estimated_hours DECIMAL(8,2),
  IN p_actual_hours DECIMAL(8,2),
  IN p_completion_percent DECIMAL(5,2),
  IN p_order_index INT UNSIGNED,
  IN p_is_blocked TINYINT(1),
  IN p_last_reported_at DATETIME,
  IN p_last_activity_at DATETIME,
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
    INSERT INTO `task` (`project_id`, `subproject_id`, `parent_task_id`, `title`, `description`, `task_type`, `current_status`, `priority_level`, `owner_user_id`, `assigned_user_id`, `reviewer_user_id`, `start_date`, `due_date`, `completed_at`, `estimated_hours`, `actual_hours`, `completion_percent`, `order_index`, `is_blocked`, `last_reported_at`, `last_activity_at`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_project_id, p_subproject_id, p_parent_task_id, p_title, p_description, p_task_type, p_current_status, p_priority_level, p_owner_user_id, p_assigned_user_id, p_reviewer_user_id, p_start_date, p_due_date, p_completed_at, p_estimated_hours, p_actual_hours, p_completion_percent, p_order_index, p_is_blocked, p_last_reported_at, p_last_activity_at, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'task', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_task_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `task` WHERE id = p_id LIMIT 1;
    UPDATE `task`
    SET
    `project_id` = p_project_id,
    `subproject_id` = p_subproject_id,
    `parent_task_id` = p_parent_task_id,
    `title` = p_title,
    `description` = p_description,
    `task_type` = p_task_type,
    `current_status` = p_current_status,
    `priority_level` = p_priority_level,
    `owner_user_id` = p_owner_user_id,
    `assigned_user_id` = p_assigned_user_id,
    `reviewer_user_id` = p_reviewer_user_id,
    `start_date` = p_start_date,
    `due_date` = p_due_date,
    `completed_at` = p_completed_at,
    `estimated_hours` = p_estimated_hours,
    `actual_hours` = p_actual_hours,
    `completion_percent` = p_completion_percent,
    `order_index` = p_order_index,
    `is_blocked` = p_is_blocked,
    `last_reported_at` = p_last_reported_at,
    `last_activity_at` = p_last_activity_at,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'task', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_task_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `task` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('task', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_task_upsert'));
  END IF;

  SELECT * FROM `task` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
