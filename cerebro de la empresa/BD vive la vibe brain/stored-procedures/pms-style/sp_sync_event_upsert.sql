/**
 * Procedure: sp_sync_event_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: sync_event, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_sync_event_upsert(NULL, ..., ..., ..., ..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sync_event_upsert` $$
CREATE PROCEDURE `sp_sync_event_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_external_system_id BIGINT UNSIGNED,
  IN p_event_type VARCHAR(80),
  IN p_internal_entity_type VARCHAR(50),
  IN p_internal_entity_id BIGINT UNSIGNED,
  IN p_external_entity_type VARCHAR(50),
  IN p_external_entity_id VARCHAR(190),
  IN p_status VARCHAR(50),
  IN p_payload_summary TEXT,
  IN p_occurred_at DATETIME,
  IN p_processed_at DATETIME,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_new_status VARCHAR(50) DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_external_system_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'external_system_id is required';
  END IF;
  IF p_event_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_type is required';
  END IF;
    INSERT INTO `sync_event` (`external_system_id`, `event_type`, `internal_entity_type`, `internal_entity_id`, `external_entity_type`, `external_entity_id`, `status`, `payload_summary`, `occurred_at`, `processed_at`)
    VALUES (p_external_system_id, p_event_type, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, p_external_entity_id, p_status, p_payload_summary, p_occurred_at, p_processed_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'sync_event', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_sync_event_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `sync_event` WHERE id = p_id LIMIT 1;
    UPDATE `sync_event`
    SET
    `external_system_id` = p_external_system_id,
    `event_type` = p_event_type,
    `internal_entity_type` = p_internal_entity_type,
    `internal_entity_id` = p_internal_entity_id,
    `external_entity_type` = p_external_entity_type,
    `external_entity_id` = p_external_entity_id,
    `status` = p_status,
    `payload_summary` = p_payload_summary,
    `occurred_at` = p_occurred_at,
    `processed_at` = p_processed_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'sync_event', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_sync_event_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `sync_event` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('sync_event', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_sync_event_upsert'));
  END IF;

  SELECT * FROM `sync_event` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
