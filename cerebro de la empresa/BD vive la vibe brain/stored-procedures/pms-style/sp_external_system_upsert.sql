/**
 * Procedure: sp_external_system_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: external_system, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_external_system_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_system_upsert` $$
CREATE PROCEDURE `sp_external_system_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_system_type VARCHAR(80),
  IN p_description TEXT,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
  IF p_system_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'system_type is required';
  END IF;
    INSERT INTO `external_system` (`name`, `system_type`, `description`, `is_active`)
    VALUES (p_name, p_system_type, p_description, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'external_system', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_external_system_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `external_system`
    SET
    `name` = p_name,
    `system_type` = p_system_type,
    `description` = p_description,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'external_system', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_external_system_upsert'));
  END IF;

  SELECT * FROM `external_system` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
