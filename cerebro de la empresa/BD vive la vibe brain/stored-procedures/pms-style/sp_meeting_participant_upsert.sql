/**
 * Procedure: sp_meeting_participant_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: meeting_participant, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_meeting_participant_upsert(NULL, ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_participant_upsert` $$
CREATE PROCEDURE `sp_meeting_participant_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_meeting_id BIGINT UNSIGNED,
  IN p_user_id BIGINT UNSIGNED,
  IN p_participation_role VARCHAR(50),
  IN p_attended TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM meeting WHERE id = p_meeting_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_meeting_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'meeting_id is required';
  END IF;
  IF p_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id is required';
  END IF;
    INSERT INTO `meeting_participant` (`meeting_id`, `user_id`, `participation_role`, `attended`)
    VALUES (p_meeting_id, p_user_id, p_participation_role, p_attended);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'meeting_participant', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_meeting_participant_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM meeting_participant t JOIN meeting p ON p.id = t.meeting_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `meeting_participant`
    SET
    `meeting_id` = p_meeting_id,
    `user_id` = p_user_id,
    `participation_role` = p_participation_role,
    `attended` = p_attended
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'meeting_participant', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_meeting_participant_upsert'));
  END IF;

  SELECT * FROM `meeting_participant` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
