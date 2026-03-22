/**
 * Procedure: sp_decision_record_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: decision_record, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_decision_record_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_record_upsert` $$
CREATE PROCEDURE `sp_decision_record_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_meeting_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_rationale TEXT,
  IN p_decision_status VARCHAR(50),
  IN p_impact_level VARCHAR(50),
  IN p_effective_date DATE,
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) INTO v_organization_id FROM (SELECT 1 AS anchor) x LEFT JOIN meeting m ON m.id = p_meeting_id LEFT JOIN project p ON p.id = p_project_id LEFT JOIN business_area a ON a.id = p_business_area_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
    INSERT INTO `decision_record` (`meeting_id`, `project_id`, `business_area_id`, `title`, `description`, `rationale`, `decision_status`, `impact_level`, `effective_date`, `owner_user_id`, `created_by_user_id`)
    VALUES (p_meeting_id, p_project_id, p_business_area_id, p_title, p_description, p_rationale, p_decision_status, p_impact_level, p_effective_date, p_owner_user_id, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)));
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'decision_record', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_decision_record_upsert'));
  ELSE
    SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id) INTO v_organization_id FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `decision_status` INTO v_old_status FROM `decision_record` WHERE id = p_id LIMIT 1;
    UPDATE `decision_record`
    SET
    `meeting_id` = p_meeting_id,
    `project_id` = p_project_id,
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `description` = p_description,
    `rationale` = p_rationale,
    `decision_status` = p_decision_status,
    `impact_level` = p_impact_level,
    `effective_date` = p_effective_date,
    `owner_user_id` = p_owner_user_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'decision_record', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_decision_record_upsert'));
  END IF;

  SET v_new_status = (SELECT `decision_status` FROM `decision_record` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('decision_record', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_decision_record_upsert'));
  END IF;

  SELECT * FROM `decision_record` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
