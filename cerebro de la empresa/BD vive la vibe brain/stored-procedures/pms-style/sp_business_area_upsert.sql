/**
 * Procedure: sp_business_area_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: business_area, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_business_area_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_business_area_upsert` $$
CREATE PROCEDURE `sp_business_area_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_code VARCHAR(50),
  IN p_description TEXT,
  IN p_priority_level VARCHAR(50),
  IN p_responsible_user_id BIGINT UNSIGNED,
  IN p_is_active TINYINT(1),
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
    INSERT INTO `business_area` (`organization_id`, `name`, `code`, `description`, `priority_level`, `responsible_user_id`, `is_active`)
    VALUES (p_organization_id, p_name, p_code, p_description, p_priority_level, p_responsible_user_id, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'business_area', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_business_area_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM business_area WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `business_area`
    SET
    `organization_id` = p_organization_id,
    `name` = p_name,
    `code` = p_code,
    `description` = p_description,
    `priority_level` = p_priority_level,
    `responsible_user_id` = p_responsible_user_id,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'business_area', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_business_area_upsert'));
  END IF;

  SELECT * FROM `business_area` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
