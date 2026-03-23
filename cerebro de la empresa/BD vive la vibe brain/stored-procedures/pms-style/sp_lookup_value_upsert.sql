/**
 * Procedure: sp_lookup_value_upsert
 * Purpose: Crea o actualiza registros. Mantiene trazabilidad en `audit_log` y usa `sp_actor_assert`.
 * Tables touched: lookup_value, audit_log
 * Security: Write seguro. Requiere `p_actor_user_id` valido.
 * Output: Result set por SELECT con el registro final.
 * Example: CALL sp_lookup_value_upsert(NULL, ..., ..., ..., ..., ..., ..., 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_lookup_value_upsert` $$
CREATE PROCEDURE `sp_lookup_value_upsert` (
  IN p_id BIGINT UNSIGNED,
  IN p_lookup_catalog_id BIGINT UNSIGNED,
  IN p_code VARCHAR(80),
  IN p_label VARCHAR(150),
  IN p_description TEXT,
  IN p_sort_order INT UNSIGNED,
  IN p_is_active TINYINT(1),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_target_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;

  IF p_id IS NULL OR p_id = 0 THEN
    SELECT organization_id INTO v_organization_id FROM lookup_catalog WHERE id = p_lookup_catalog_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);

  IF p_lookup_catalog_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lookup_catalog_id is required';
  END IF;
  IF p_code IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'code is required';
  END IF;
  IF p_label IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'label is required';
  END IF;
    INSERT INTO `lookup_value` (`lookup_catalog_id`, `code`, `label`, `description`, `sort_order`, `is_active`)
    VALUES (p_lookup_catalog_id, p_code, p_label, p_description, p_sort_order, p_is_active);
    SET v_target_id = LAST_INSERT_ID();
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'create', 'lookup_value', v_target_id, NULL, NULL, NULL, CONCAT('Created via sp_lookup_value_upsert'));
  ELSE
    SELECT p.organization_id INTO v_organization_id FROM lookup_value t JOIN lookup_catalog p ON p.id = t.lookup_catalog_id WHERE t.id = p_id LIMIT 1;
    CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
    UPDATE `lookup_value`
    SET
    `lookup_catalog_id` = p_lookup_catalog_id,
    `code` = p_code,
    `label` = p_label,
    `description` = p_description,
    `sort_order` = p_sort_order,
    `is_active` = p_is_active
    WHERE id = p_id;

    IF ROW_COUNT() = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found';
    END IF;
    SET v_target_id = p_id;
    CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'update', 'lookup_value', v_target_id, NULL, NULL, NULL, CONCAT('Updated via sp_lookup_value_upsert'));
  END IF;

  SELECT * FROM `lookup_value` WHERE id = v_target_id LIMIT 1;
END $$

DELIMITER ;
