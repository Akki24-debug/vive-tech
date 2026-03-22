/**
 * Procedure: sp_project_tag_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_tag, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_tag_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_tag_upsert` $$
CREATE PROCEDURE `sp_project_tag_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_name VARCHAR(120),
  IN p_description TEXT,
  IN p_color_hex VARCHAR(20),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = p_organization_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'organization_id is required';
  END IF;
  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `project_tag` (`organization_id`, `name`, `description`, `color_hex`)
    VALUES (p_organization_id, p_name, p_description, p_color_hex);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_tag', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_tag_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM project_tag WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_tag`
    SET
    `organization_id` = p_organization_id,
    `name` = p_name,
    `description` = p_description,
    `color_hex` = p_color_hex
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_tag', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_tag_upsert'));
  END IF;

  SELECT * FROM `project_tag` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
