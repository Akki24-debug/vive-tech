/**
 * Procedure: sp_daily_checkin_data
 * Purpose: Consulta registros de `daily_checkin` con filtros predecibles para IA e integraciones.
 * Tables touched: daily_checkin
 * Security: Lectura. `p_organization_id` filtra de forma directa cuando la tabla tiene alcance organizacional.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_daily_checkin_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_daily_checkin_data` $$
CREATE PROCEDURE `sp_daily_checkin_data` (
  IN p_id BIGINT UNSIGNED,
  IN p_organization_id BIGINT UNSIGNED,
  IN p_search VARCHAR(255),
  IN p_only_active TINYINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_limit_rows INT DEFAULT 100;

  SET v_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 100);
  IF v_limit_rows < 1 THEN
    SET v_limit_rows = 100;
  END IF;

  SELECT t.*
  FROM `daily_checkin` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR t.organization_id = p_organization_id)
    AND 1 = 1
    AND 1 = 1
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;
