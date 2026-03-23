/**
 * Procedure: sp_project_objective_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_objective_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_objective_link_upsert(NULL, ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_objective_link_upsert` $$
CREATE PROCEDURE `sp_project_objective_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_objective_record_id BIGINT UNSIGNED,
  IN p_relation_type VARCHAR(50),
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
  IF p_objective_record_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'objective_record_id is required';
  END IF;
    INSERT INTO `project_objective_link` (`project_id`, `objective_record_id`, `relation_type`)
    VALUES (p_project_id, p_objective_record_id, p_relation_type);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_objective_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_objective_link_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM project_objective_link t JOIN project p ON p.id = t.project_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_objective_link`
    SET
    `project_id` = p_project_id,
    `objective_record_id` = p_objective_record_id,
    `relation_type` = p_relation_type
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_objective_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_objective_link_upsert'));
  END IF;

  SELECT * FROM `project_objective_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
