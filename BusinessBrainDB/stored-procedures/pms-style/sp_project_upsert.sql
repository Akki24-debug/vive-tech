/**
 * Procedure: sp_project_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_upsert` $$
CREATE PROCEDURE `sp_project_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_initiative_id BIGINT UNSIGNED,
  IN p_project_category_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_business_line_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_short_code VARCHAR(60),
  IN p_description TEXT,
  IN p_objective TEXT,
  IN p_current_status VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_health_status VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_sponsor_user_id BIGINT UNSIGNED,
  IN p_start_date DATE,
  IN p_target_date DATE,
  IN p_completed_at DATETIME,
  IN p_completion_percent DECIMAL(5,2),
  IN p_last_reported_at DATETIME,
  IN p_last_activity_at DATETIME,
  IN p_is_active TINYINT(1),
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
    INSERT INTO `project` (`organization_id`, `initiative_id`, `project_category_id`, `business_area_id`, `business_line_id`, `title`, `short_code`, `description`, `objective`, `current_status`, `priority_level`, `health_status`, `owner_user_id`, `sponsor_user_id`, `start_date`, `target_date`, `completed_at`, `completion_percent`, `last_reported_at`, `last_activity_at`, `is_active`, `created_by_user_id`, `updated_by_user_id`)
    VALUES (p_organization_id, p_initiative_id, p_project_category_id, p_business_area_id, p_business_line_id, p_title, p_short_code, p_description, p_objective, p_current_status, p_priority_level, p_health_status, p_owner_user_id, p_sponsor_user_id, p_start_date, p_target_date, p_completed_at, p_completion_percent, p_last_reported_at, p_last_activity_at, p_is_active, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)), COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0), COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0))));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `project` WHERE id = p_id LIMIT 1;
    UPDATE `project`
    SET
    `organization_id` = p_organization_id,
    `initiative_id` = p_initiative_id,
    `project_category_id` = p_project_category_id,
    `business_area_id` = p_business_area_id,
    `business_line_id` = p_business_line_id,
    `title` = p_title,
    `short_code` = p_short_code,
    `description` = p_description,
    `objective` = p_objective,
    `current_status` = p_current_status,
    `priority_level` = p_priority_level,
    `health_status` = p_health_status,
    `owner_user_id` = p_owner_user_id,
    `sponsor_user_id` = p_sponsor_user_id,
    `start_date` = p_start_date,
    `target_date` = p_target_date,
    `completed_at` = p_completed_at,
    `completion_percent` = p_completion_percent,
    `last_reported_at` = p_last_reported_at,
    `last_activity_at` = p_last_activity_at,
    `is_active` = p_is_active,
    updated_by_user_id = COALESCE(p_updated_by_user_id, NULLIF(p_actor_user_id, 0))
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `project` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('project', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_project_upsert'));
  END IF;

  SELECT * FROM `project` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
