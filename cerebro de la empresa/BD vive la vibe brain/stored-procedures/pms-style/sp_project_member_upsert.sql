/**
 * Procedure: sp_project_member_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_member, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_member_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_member_upsert` $$
CREATE PROCEDURE `sp_project_member_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_role_in_project VARCHAR(80),
  IN p_is_primary TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
    INSERT INTO `project_member` (`project_id`, `user_id`, `role_in_project`, `is_primary`)
    VALUES (p_project_id, p_user_id, p_role_in_project, p_is_primary);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_member', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_member_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM project_member t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_member`
    SET
    `project_id` = p_project_id,
    `user_id` = p_user_id,
    `role_in_project` = p_role_in_project,
    `is_primary` = p_is_primary
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_member', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_member_upsert'));
  END IF;

  SELECT * FROM `project_member` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
