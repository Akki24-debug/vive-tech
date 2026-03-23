/**
 * Procedure: sp_user_capacity_profile_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_capacity_profile, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_capacity_profile_upsert(NULL, ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_capacity_profile_upsert` $$
CREATE PROCEDURE `sp_user_capacity_profile_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_weekly_capacity_hours DECIMAL(6,2),
  IN p_max_parallel_projects INT UNSIGNED,
  IN p_max_parallel_tasks INT UNSIGNED,
  IN p_notes TEXT,
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
    INSERT INTO `user_capacity_profile` (`user_id`, `weekly_capacity_hours`, `max_parallel_projects`, `max_parallel_tasks`, `notes`)
    VALUES (p_user_id, p_weekly_capacity_hours, p_max_parallel_projects, p_max_parallel_tasks, p_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_capacity_profile', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_capacity_profile_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM user_capacity_profile t JOIN user_account p ON p.id = t.user_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `user_capacity_profile`
    SET
    `user_id` = p_user_id,
    `weekly_capacity_hours` = p_weekly_capacity_hours,
    `max_parallel_projects` = p_max_parallel_projects,
    `max_parallel_tasks` = p_max_parallel_tasks,
    `notes` = p_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_capacity_profile', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_capacity_profile_upsert'));
  END IF;

  SELECT * FROM `user_capacity_profile` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
