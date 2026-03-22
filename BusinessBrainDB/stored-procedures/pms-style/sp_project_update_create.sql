/**
 * Procedure: sp_project_update_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_update, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_update_create(..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_update_create` $$
CREATE PROCEDURE `sp_project_update_create` (
  IN p_project_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_summary TEXT,
  IN p_completion_percent_after DECIMAL(5,2),
  IN p_health_status_after VARCHAR(50),
  IN p_major_risks TEXT,
  IN p_next_actions TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_project_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'project_id is required';
  END IF;
  INSERT INTO `project_update` (`project_id`, `user_id`, `summary`, `completion_percent_after`, `health_status_after`, `major_risks`, `next_actions`)
  VALUES (p_project_id, p_user_id, p_summary, p_completion_percent_after, p_health_status_after, p_major_risks, p_next_actions);
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_update', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_update_create'));

  SELECT * FROM `project_update` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
