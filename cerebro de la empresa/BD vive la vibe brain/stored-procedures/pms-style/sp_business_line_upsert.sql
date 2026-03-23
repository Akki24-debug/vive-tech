/**
 * Procedure: sp_business_line_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: business_line, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_business_line_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_line_upsert` $$
CREATE PROCEDURE `sp_business_line_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_name VARCHAR(180),
  IN p_description TEXT,
  IN p_business_model_summary TEXT,
  IN p_current_status VARCHAR(50),
  IN p_monetization_notes TEXT,
  IN p_strategic_priority VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_is_active TINYINT(1),
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
  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `business_line` (`organization_id`, `business_area_id`, `name`, `description`, `business_model_summary`, `current_status`, `monetization_notes`, `strategic_priority`, `owner_user_id`, `is_active`)
    VALUES (p_organization_id, p_business_area_id, p_name, p_description, p_business_model_summary, p_current_status, p_monetization_notes, p_strategic_priority, p_owner_user_id, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'business_line', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_business_line_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM business_line WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `business_line` WHERE id = p_id LIMIT 1;
    UPDATE `business_line`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `name` = p_name,
    `description` = p_description,
    `business_model_summary` = p_business_model_summary,
    `current_status` = p_current_status,
    `monetization_notes` = p_monetization_notes,
    `strategic_priority` = p_strategic_priority,
    `owner_user_id` = p_owner_user_id,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'business_line', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_business_line_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `business_line` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('business_line', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_business_line_upsert'));
  END IF;

  SELECT * FROM `business_line` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
