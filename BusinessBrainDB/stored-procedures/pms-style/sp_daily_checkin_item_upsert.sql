/**
 * Procedure: sp_daily_checkin_item_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: daily_checkin_item, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_daily_checkin_item_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_daily_checkin_item_upsert` $$
CREATE PROCEDURE `sp_daily_checkin_item_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_daily_checkin_id BIGINT UNSIGNED,
  IN p_project_id BIGINT UNSIGNED,
  IN p_task_id BIGINT UNSIGNED,
  IN p_item_type VARCHAR(50),
  IN p_content TEXT,
  IN p_sort_order INT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM daily_checkin WHERE id = p_daily_checkin_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_daily_checkin_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'daily_checkin_id is required';
  END IF;
    INSERT INTO `daily_checkin_item` (`daily_checkin_id`, `project_id`, `task_id`, `item_type`, `content`, `sort_order`)
    VALUES (p_daily_checkin_id, p_project_id, p_task_id, p_item_type, p_content, p_sort_order);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'daily_checkin_item', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_daily_checkin_item_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM daily_checkin_item t JOIN daily_checkin p ON p.id = t.daily_checkin_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `daily_checkin_item`
    SET
    `daily_checkin_id` = p_daily_checkin_id,
    `project_id` = p_project_id,
    `task_id` = p_task_id,
    `item_type` = p_item_type,
    `content` = p_content,
    `sort_order` = p_sort_order
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'daily_checkin_item', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_daily_checkin_item_upsert'));
  END IF;

  SELECT * FROM `daily_checkin_item` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
