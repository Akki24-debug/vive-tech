/**
 * Procedure: sp_project_category_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: project_category, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_project_category_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_category_upsert` $$
CREATE PROCEDURE `sp_project_category_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_description TEXT,
  IN p_sort_order INT UNSIGNED,
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
    INSERT INTO `project_category` (`organization_id`, `business_area_id`, `name`, `description`, `sort_order`, `is_active`)
    VALUES (p_organization_id, p_business_area_id, p_name, p_description, p_sort_order, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'project_category', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_project_category_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM project_category WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `project_category`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `name` = p_name,
    `description` = p_description,
    `sort_order` = p_sort_order,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'project_category', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_project_category_upsert'));
  END IF;

  SELECT * FROM `project_category` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
