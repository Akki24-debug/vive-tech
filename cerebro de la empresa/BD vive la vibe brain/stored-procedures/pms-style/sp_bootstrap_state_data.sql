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
