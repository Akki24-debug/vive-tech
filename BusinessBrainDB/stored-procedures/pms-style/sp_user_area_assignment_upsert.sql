/**
 * Procedure: sp_user_area_assignment_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_area_assignment, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_area_assignment_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_area_assignment_upsert` $$
CREATE PROCEDURE `sp_user_area_assignment_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_responsibility_level VARCHAR(50),
  IN p_is_primary TINYINT(1),
  IN p_start_date DATE,
  IN p_end_date DATE,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM business_area WHERE id = p_business_area_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_business_area_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'business_area_id is required';
  END IF;
    INSERT INTO `user_area_assignment` (`user_id`, `business_area_id`, `responsibility_level`, `is_primary`, `start_date`, `end_date`)
    VALUES (p_user_id, p_business_area_id, p_responsibility_level, p_is_primary, p_start_date, p_end_date);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_area_assignment', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_area_assignment_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM user_area_assignment t JOIN business_area p ON p.id = t.business_area_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `user_area_assignment`
    SET
    `user_id` = p_user_id,
    `business_area_id` = p_business_area_id,
    `responsibility_level` = p_responsibility_level,
    `is_primary` = p_is_primary,
    `start_date` = p_start_date,
    `end_date` = p_end_date
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_area_assignment', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_area_assignment_upsert'));
  END IF;

  SELECT * FROM `user_area_assignment` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
