/**
 * Procedure: sp_user_role_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: user_role, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_user_role_upsert(NULL, ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_role_upsert` $$
CREATE PROCEDURE `sp_user_role_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_role_id BIGINT UNSIGNED,
  IN p_is_primary TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);

  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
  IF p_role_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'role_id is required';
  END IF;
    INSERT INTO `user_role` (`user_id`, `role_id`, `is_primary`)
    VALUES (p_user_id, p_role_id, p_is_primary);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'user_role', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_user_role_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
    UPDATE `user_role`
    SET
    `user_id` = p_user_id,
    `role_id` = p_role_id,
    `is_primary` = p_is_primary
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'user_role', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_user_role_upsert'));
  END IF;

  SELECT * FROM `user_role` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
