/**
 * Procedure: sp_meeting_note_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: meeting_note, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_meeting_note_create(..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_note_create` $$
CREATE PROCEDURE `sp_meeting_note_create` (
  IN p_meeting_id BIGINT UNSIGNED,
  IN p_note_type VARCHAR(50),
  IN p_content TEXT,
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SELECT organization_id INTO v_organization_id FROM meeting WHERE id = p_meeting_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_meeting_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'meeting_id is required';
  END IF;
  IF p_content IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'content is required';
  END IF;
  INSERT INTO `meeting_note` (`meeting_id`, `note_type`, `content`, `created_by_user_id`)
  VALUES (p_meeting_id, p_note_type, p_content, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)));
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'meeting_note', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_meeting_note_create'));

  SELECT * FROM `meeting_note` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
