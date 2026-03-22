/**
 * Procedure: sp_initiative_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: initiative, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_initiative_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_initiative_upsert` $$
CREATE PROCEDURE `sp_initiative_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_project_category_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_status VARCHAR(50),
  IN p_priority_level VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_start_date DATE,
  IN p_target_date DATE,
  IN p_completion_percent DECIMAL(5,2),
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
    INSERT INTO `initiative` (`organization_id`, `project_category_id`, `title`, `description`, `status`, `priority_level`, `owner_user_id`, `start_date`, `target_date`, `completion_percent`)
    VALUES (p_organization_id, p_project_category_id, p_title, p_description, p_status, p_priority_level, p_owner_user_id, p_start_date, p_target_date, p_completion_percent);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'initiative', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_initiative_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM initiative WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `initiative` WHERE id = p_id LIMIT 1;
    UPDATE `initiative`
    SET
    `organization_id` = p_organization_id,
    `project_category_id` = p_project_category_id,
    `title` = p_title,
    `description` = p_description,
    `status` = p_status,
    `priority_level` = p_priority_level,
    `owner_user_id` = p_owner_user_id,
    `start_date` = p_start_date,
    `target_date` = p_target_date,
    `completion_percent` = p_completion_percent
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'initiative', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_initiative_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `initiative` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('initiative', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_initiative_upsert'));
  END IF;

  SELECT * FROM `initiative` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
