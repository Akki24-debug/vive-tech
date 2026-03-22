/**
 * Procedure: sp_bootstrap_state_data
 * Purpose: Expone si el modo bootstrap sigue abierto por organizacion y a nivel global.
 * Tables touched: organization, user_account
 * Security: Lectura. Sin actor.
 * Output: Result set con conteo de usuarios y banderas de bootstrap.
 * Example: CALL sp_bootstrap_state_data(NULL);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_bootstrap_state_data` $$
CREATE PROCEDURE `sp_bootstrap_state_data` (
  IN p_organization_id BIGINT UNSIGNED
)
proc:BEGIN
  SELECT
    p_organization_id AS organization_id,
    (SELECT COUNT(*) FROM user_account) AS total_users_global,
    CASE
      WHEN p_organization_id IS NULL OR p_organization_id = 0 THEN NULL
      ELSE (SELECT COUNT(*) FROM user_account WHERE organization_id = p_organization_id)
    END AS total_users_in_organization,
    CASE WHEN (SELECT COUNT(*) FROM user_account) = 0 THEN 1 ELSE 0 END AS bootstrap_open_global,
    CASE
      WHEN p_organization_id IS NULL OR p_organization_id = 0 THEN NULL
      WHEN (SELECT COUNT(*) FROM user_account WHERE organization_id = p_organization_id) = 0 THEN 1 ELSE 0
    END AS bootstrap_open_for_organization;
END $$

DELIMITER ;

/**
 * Procedure: sp_actor_assert
 * Purpose: Valida actor y pertenencia organizacional, con soporte controlado para bootstrap.
 * Tables touched: user_account
 * Security: Helper interno de seguridad para todos los writes.
 * Output: No devuelve dataset. Lanza `SIGNAL 45000` si la validacion falla.
 * Example: CALL sp_actor_assert(1, 1, 0);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_actor_assert` $$
CREATE PROCEDURE `sp_actor_assert` (
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_allow_bootstrap TINYINT
)
proc:BEGIN
  DECLARE v_actor_org_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_total_users INT DEFAULT 0;
  DECLARE v_org_users INT DEFAULT 0;

  IF COALESCE(p_actor_user_id, 0) = 0 THEN
    IF COALESCE(p_allow_bootstrap, 0) = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor user id is required';
    END IF;
    SELECT COUNT(*) INTO v_total_users FROM user_account;
    IF p_organization_id IS NULL OR p_organization_id = 0 THEN
      IF v_total_users > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bootstrap closed: actor required';
      END IF;
      LEAVE proc;
    END IF;
    SELECT COUNT(*) INTO v_org_users FROM user_account WHERE organization_id = p_organization_id;
    IF v_org_users > 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bootstrap closed for this organization';
    END IF;
    LEAVE proc;
  END IF;

  SELECT organization_id INTO v_actor_org_id
  FROM user_account
  WHERE id = p_actor_user_id AND is_active = 1
  LIMIT 1;
  IF v_actor_org_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor user not found or inactive';
  END IF;
  IF p_organization_id IS NOT NULL AND p_organization_id <> 0 AND v_actor_org_id <> p_organization_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor does not belong to the target organization';
  END IF;
END $$

DELIMITER ;

/**
 * Procedure: sp_audit_log_insert
 * Purpose: Inserta un registro estandarizado en `audit_log`.
 * Tables touched: audit_log
 * Security: Helper interno. No valida actor por si mismo.
 * Output: Sin salida. Inserta una fila en auditoria.
 * Example: CALL sp_audit_log_insert(1, 'update', 'project', 10, NULL, NULL, NULL, 'Updated project');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_audit_log_insert` $$
CREATE PROCEDURE `sp_audit_log_insert` (
  IN p_user_id BIGINT UNSIGNED,
  IN p_action_type VARCHAR(80),
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_field_name VARCHAR(120),
  IN p_old_value TEXT,
  IN p_new_value TEXT,
  IN p_change_summary TEXT
)
proc:BEGIN
  INSERT INTO audit_log (
    user_id, action_type, entity_type, entity_id, field_name, old_value, new_value, change_summary
  ) VALUES (
    NULLIF(p_user_id, 0),
    COALESCE(NULLIF(p_action_type, ''), 'unknown'),
    COALESCE(NULLIF(p_entity_type, ''), 'unknown'),
    p_entity_id,
    NULLIF(p_field_name, ''),
    p_old_value,
    p_new_value,
    p_change_summary
  );
END $$

DELIMITER ;

/**
 * Procedure: sp_status_history_insert
 * Purpose: Inserta un registro estandarizado en `status_history`.
 * Tables touched: status_history
 * Security: Helper interno. Se usa solo cuando cambia un campo de estado real.
 * Output: Sin salida. Inserta una fila en historial de estado.
 * Example: CALL sp_status_history_insert('task', 10, 'pending', 'done', 1, 'Manual close');
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_status_history_insert` $$
CREATE PROCEDURE `sp_status_history_insert` (
  IN p_entity_type VARCHAR(50),
  IN p_entity_id BIGINT UNSIGNED,
  IN p_old_status VARCHAR(50),
  IN p_new_status VARCHAR(50),
  IN p_changed_by_user_id BIGINT UNSIGNED,
  IN p_notes TEXT
)
proc:BEGIN
  IF COALESCE(p_old_status, '__NULL__') = COALESCE(p_new_status, '__NULL__') THEN
    LEAVE proc;
  END IF;
  INSERT INTO status_history (
    entity_type, entity_id, old_status, new_status, changed_by_user_id, notes
  ) VALUES (
    p_entity_type,
    p_entity_id,
    p_old_status,
    p_new_status,
    NULLIF(p_changed_by_user_id, 0),
    p_notes
  );
END $$

DELIMITER ;

