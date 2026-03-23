/**
 * Procedure: sp_external_entity_link_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: external_entity_link, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_external_entity_link_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_entity_link_upsert` $$
CREATE PROCEDURE `sp_external_entity_link_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_external_system_id BIGINT UNSIGNED,
  IN p_internal_entity_type VARCHAR(50),
  IN p_internal_entity_id BIGINT UNSIGNED,
  IN p_external_entity_type VARCHAR(50),
  IN p_external_entity_id VARCHAR(190),
  IN p_reference_label VARCHAR(190),
  IN p_metadata_json LONGTEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_external_system_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_system_id is required';
  END IF;
  IF p_internal_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'internal_entity_type is required';
  END IF;
  IF p_internal_entity_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'internal_entity_id is required';
  END IF;
  IF p_external_entity_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_entity_type is required';
  END IF;
  IF p_external_entity_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_entity_id is required';
  END IF;
    INSERT INTO `external_entity_link` (`external_system_id`, `internal_entity_type`, `internal_entity_id`, `external_entity_type`, `external_entity_id`, `reference_label`, `metadata_json`)
    VALUES (p_external_system_id, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, p_external_entity_id, p_reference_label, p_metadata_json);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'external_entity_link', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_external_entity_link_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `external_entity_link`
    SET
    `external_system_id` = p_external_system_id,
    `internal_entity_type` = p_internal_entity_type,
    `internal_entity_id` = p_internal_entity_id,
    `external_entity_type` = p_external_entity_type,
    `external_entity_id` = p_external_entity_id,
    `reference_label` = p_reference_label,
    `metadata_json` = p_metadata_json
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'external_entity_link', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_external_entity_link_upsert'));
  END IF;

  SELECT * FROM `external_entity_link` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
