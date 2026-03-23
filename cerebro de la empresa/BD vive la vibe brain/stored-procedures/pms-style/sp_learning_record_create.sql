/**
 * Procedure: sp_learning_record_create
 * Purpose: Inserta registros append-only. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: learning_record, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_learning_record_create(..., ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_learning_record_create` $$
CREATE PROCEDURE `sp_learning_record_create` (
  IN p_source_type VARCHAR(50),
  IN p_source_id BIGINT UNSIGNED,
  IN p_title VARCHAR(220),
  IN p_description TEXT,
  IN p_category VARCHAR(50),
  IN p_impact_level VARCHAR(50),
  IN p_created_by_user_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

SET v_organization_id = NULL;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_source_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source_type is required';
  END IF;
  IF p_title IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'title is required';
  END IF;
  IF p_description IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'description is required';
  END IF;
  INSERT INTO `learning_record` (`source_type`, `source_id`, `title`, `description`, `category`, `impact_level`, `created_by_user_id`)
  VALUES (p_source_type, p_source_id, p_title, p_description, p_category, p_impact_level, COALESCE(p_created_by_user_id, NULLIF(p_actor_user_id, 0)));
  SET v_target_id = LAST_INSERT_ID();

  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'learning_record', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_learning_record_create'));

  SELECT * FROM `learning_record` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
