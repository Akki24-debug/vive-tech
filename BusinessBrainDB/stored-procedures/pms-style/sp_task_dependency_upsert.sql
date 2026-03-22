/**
 * Procedure: sp_task_dependency_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: task_dependency, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_task_dependency_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_dependency_upsert` $$
CREATE PROCEDURE `sp_task_dependency_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_predecessor_task_id BIGINT UNSIGNED,
  IN p_successor_task_id BIGINT UNSIGNED,
  IN p_dependency_type VARCHAR(50),
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT p.organization_id INTO v_organization_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_predecessor_task_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_predecessor_task_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'predecessor_task_id is required';
  END IF;
  IF p_successor_task_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'successor_task_id is required';
  END IF;
    INSERT INTO `task_dependency` (`predecessor_task_id`, `successor_task_id`, `dependency_type`, `notes`)
    VALUES (p_predecessor_task_id, p_successor_task_id, p_dependency_type, p_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'task_dependency', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_task_dependency_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM task_dependency t JOIN task tt ON tt.id = t.predecessor_task_id JOIN project p ON p.id = tt.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `task_dependency`
    SET
    `predecessor_task_id` = p_predecessor_task_id,
    `successor_task_id` = p_successor_task_id,
    `dependency_type` = p_dependency_type,
    `notes` = p_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'task_dependency', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_task_dependency_upsert'));
  END IF;

  SELECT * FROM `task_dependency` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
