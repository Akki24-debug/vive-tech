/**
 * Procedure: sp_policy_record_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: policy_record, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_policy_record_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_policy_record_upsert` $$
CREATE PROCEDURE `sp_policy_record_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_status VARCHAR(50),
  IN p_owner_user_id BIGINT UNSIGNED,
  IN p_external_document_url VARCHAR(500),
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
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
    INSERT INTO `policy_record` (`organization_id`, `business_area_id`, `title`, `description`, `status`, `owner_user_id`, `external_document_url`)
    VALUES (p_organization_id, p_business_area_id, p_title, p_description, p_status, p_owner_user_id, p_external_document_url);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'policy_record', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_policy_record_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM policy_record WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `policy_record` WHERE id = p_id LIMIT 1;
    UPDATE `policy_record`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `description` = p_description,
    `status` = p_status,
    `owner_user_id` = p_owner_user_id,
    `external_document_url` = p_external_document_url
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'policy_record', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_policy_record_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `policy_record` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('policy_record', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_policy_record_upsert'));
  END IF;

  SELECT * FROM `policy_record` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
