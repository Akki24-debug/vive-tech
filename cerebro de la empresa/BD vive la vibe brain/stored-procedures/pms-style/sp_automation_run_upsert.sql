/**
 * Procedure: sp_automation_run_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: automation_run, audit_log, status_history
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_automation_run_upsert(NULL, ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_run_upsert` $$
CREATE PROCEDURE `sp_automation_run_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_automation_rule_id BIGINT UNSIGNED,
  IN p_status VARCHAR(50),
  IN p_execution_summary TEXT,
  IN p_triggered_at DATETIME,
  IN p_completed_at DATETIME,
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

  IF p_automation_rule_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'automation_rule_id is required';
  END IF;
    INSERT INTO `automation_run` (`automation_rule_id`, `status`, `execution_summary`, `triggered_at`, `completed_at`)
    VALUES (p_automation_rule_id, p_status, p_execution_summary, p_triggered_at, p_completed_at);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'automation_run', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_automation_run_upsert'));
  ELSE
    SET v_organization_id = NULL;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    SELECT `status` INTO v_old_status FROM `automation_run` WHERE id = p_id LIMIT 1;
    UPDATE `automation_run`
    SET
    `automation_rule_id` = p_automation_rule_id,
    `status` = p_status,
    `execution_summary` = p_execution_summary,
    `triggered_at` = p_triggered_at,
    `completed_at` = p_completed_at
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'automation_run', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_automation_run_upsert'));
  END IF;

  SET v_new_status = (SELECT `status` FROM `automation_run` WHERE id = v_target_id LIMIT 1);
  IF COALESCE(v_old_status, '__NULL__') <> COALESCE(v_new_status, '__NULL__') THEN
    CALL sp_status_history_insert('automation_run', v_target_id, v_old_status, v_new_status, NULLIF(p_actor_user_id, 0), CONCAT('Status change via sp_automation_run_upsert'));
  END IF;

  SELECT * FROM `automation_run` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
