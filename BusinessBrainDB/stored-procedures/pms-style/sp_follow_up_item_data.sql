/**
 * Procedure: sp_follow_up_item_data
 * Purpose: Consulta registros de `follow_up_item` con filtros predecibles para IA e integraciones.
 * Tables touched: follow_up_item
 * Security: Lectura. `p_organization_id` filtra por alcance organizacional indirecto mediante joins o subconsultas.
 * Output: Result set por SELECT con filas de la entidad.
 * Example: CALL sp_follow_up_item_data(NULL, NULL, NULL, NULL, 100);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_follow_up_item_data` $$
CREATE PROCEDURE `sp_follow_up_item_data` (
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
  FROM `follow_up_item` t
  WHERE (p_id IS NULL OR p_id = 0 OR t.id = p_id)
    AND (p_organization_id IS NULL OR p_organization_id = 0 OR (EXISTS (SELECT 1 FROM meeting m WHERE m.id = t.meeting_id AND m.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM task tt JOIN project p ON p.id = tt.project_id WHERE tt.id = t.task_id AND p.organization_id = p_organization_id) OR EXISTS (SELECT 1 FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = t.decision_record_id AND (m.organization_id = p_organization_id OR p.organization_id = p_organization_id OR a.organization_id = p_organization_id))))
    AND 1 = 1
    AND (p_search IS NULL OR TRIM(p_search) = '' OR LOWER(COALESCE(t.title, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci) OR LOWER(COALESCE(t.description, '') COLLATE utf8mb4_unicode_ci) LIKE LOWER(CONCAT('%', COALESCE(p_search, ''), '%') COLLATE utf8mb4_unicode_ci))
  ORDER BY t.created_at DESC, t.id DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;
