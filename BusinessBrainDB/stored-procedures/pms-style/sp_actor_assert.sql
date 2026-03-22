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
