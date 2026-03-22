/**
 * Procedure: sp_organization_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: organization, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id`, salvo bootstrap para tablas base.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_organization_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_organization_upsert` $$
CREATE PROCEDURE `sp_organization_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_name VARCHAR(150),
  IN p_legal_name VARCHAR(200),
  IN p_description TEXT,
  IN p_base_city VARCHAR(120),
  IN p_base_state VARCHAR(120),
  IN p_country VARCHAR(120),
  IN p_status VARCHAR(50),
  IN p_current_stage VARCHAR(100),
  IN p_vision_summary TEXT,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);

  IF p_name IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'name is required';
  END IF;
    INSERT INTO `organization` (`name`, `legal_name`, `description`, `base_city`, `base_state`, `country`, `status`, `current_stage`, `vision_summary`, `notes`)
    VALUES (p_name, p_legal_name, p_description, p_base_city, p_base_state, p_country, p_status, p_current_stage, p_vision_summary, p_notes);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'organization', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_organization_upsert'));
  ELSE
    SET v_organization_id = p_id;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
    SELECT `status` INTO v_old_status FROM `organization` WHERE id = p_id LIMIT 1;
    UPDATE `organization`
    SET
    `name` = p_name,
    `legal_name` = p_legal_name,
    `description` = p_description,
    `base_city` = p_base_city,
    `base_state` = p_base_state,
    `country` = p_country,
    `status` = p_status,
    `current_stage` = p_current_stage,
    `vision_summary` = p_vision_summary,
    `notes` = p_notes
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'organization', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_organization_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `organization` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('organization', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_organization_upsert'));
  END IF;

  SELECT * FROM `organization` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
