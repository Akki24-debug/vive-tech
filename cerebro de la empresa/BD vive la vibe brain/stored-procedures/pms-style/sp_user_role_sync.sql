/**
 * Procedure: sp_user_role_sync
 * Purpose: Sincroniza completamente los roles de un usuario.
 * Tables touched: user_account, user_role, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_user_role_sync(1, '1,2,3', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_role_sync` $$
CREATE PROCEDURE `sp_user_role_sync` (
  IN p_user_id BIGINT UNSIGNED,
  IN p_role_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_csv TEXT;

  SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_user_id LIMIT 1;
  IF v_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
  END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
  SET v_csv = REPLACE(COALESCE(p_role_ids_csv, ''), ' ', '');
  DELETE FROM user_role
  WHERE user_id = p_user_id
    AND (v_csv = '' OR FIND_IN_SET(CAST(role_id AS CHAR), v_csv) = 0);
  INSERT INTO user_role (user_id, role_id, is_primary)
  SELECT p_user_id, r.id, 0
  FROM role r
  WHERE v_csv <> ''
    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0
    AND NOT EXISTS (
      SELECT 1 FROM user_role ur WHERE ur.user_id = p_user_id AND ur.role_id = r.id
    );
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'user_role', p_user_id, NULL, NULL, NULL, 'Synced user roles');
  SELECT * FROM user_role WHERE user_id = p_user_id ORDER BY id;
END $$

DELIMITER ;
