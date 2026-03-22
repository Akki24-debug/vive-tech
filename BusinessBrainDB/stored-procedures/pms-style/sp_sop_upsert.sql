/**
 * Procedure: sp_sop_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: sop, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_sop_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sop_upsert` $$
CREATE PROCEDURE `sp_sop_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_business_area_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_objective TEXT,
  IN p_scope TEXT,
  IN p_current_status VARCHAR(50),
  IN p_external_document_url VARCHAR(500),
  IN p_owner_user_id BIGINT UNSIGNED,
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
    INSERT INTO `sop` (`organization_id`, `business_area_id`, `title`, `objective`, `scope`, `current_status`, `external_document_url`, `owner_user_id`)
    VALUES (p_organization_id, p_business_area_id, p_title, p_objective, p_scope, p_current_status, p_external_document_url, p_owner_user_id);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'sop', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_sop_upsert'));
  ELSE
    SELECT organization_id INTO v_organization_id FROM sop WHERE id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `current_status` INTO v_old_status FROM `sop` WHERE id = p_id LIMIT 1;
    UPDATE `sop`
    SET
    `organization_id` = p_organization_id,
    `business_area_id` = p_business_area_id,
    `title` = p_title,
    `objective` = p_objective,
    `scope` = p_scope,
    `current_status` = p_current_status,
    `external_document_url` = p_external_document_url,
    `owner_user_id` = p_owner_user_id
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'sop', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_sop_upsert'));
  END IF;

  SET v_new_status = (SELECT `current_status` FROM `sop` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('sop', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_sop_upsert'));
  END IF;

  SELECT * FROM `sop` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
