/**
 * Procedure: sp_external_entity_link_sync
 * Purpose: Sincroniza los external ids asociados a una entidad interna dentro de un sistema externo.
 * Tables touched: external_entity_link, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_external_entity_link_sync(1, 'project', 10, 'deal', 'A1,B2', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_entity_link_sync` $$
CREATE PROCEDURE `sp_external_entity_link_sync` (
  IN p_external_system_id BIGINT UNSIGNED,
  IN p_internal_entity_type VARCHAR(50),
  IN p_internal_entity_id BIGINT UNSIGNED,
  IN p_external_entity_type VARCHAR(50),
  IN p_external_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_csv TEXT;
  DECLARE v_token TEXT;
  DECLARE v_pos INT DEFAULT 0;
  CALL sp_actor_assert(p_actor_user_id, NULL, 0);
  SET v_csv = CONCAT(REPLACE(COALESCE(p_external_ids_csv, ''), ' ', ''), ',');
  DELETE FROM external_entity_link WHERE external_system_id = p_external_system_id AND internal_entity_type = p_internal_entity_type AND internal_entity_id = p_internal_entity_id AND external_entity_type = p_external_entity_type AND (TRIM(COALESCE(p_external_ids_csv, '')) = '' OR FIND_IN_SET(external_entity_id, REPLACE(COALESCE(p_external_ids_csv, ''), ' ', '')) = 0);
  WHILE LOCATE(',', v_csv) > 0 DO
    SET v_pos = LOCATE(',', v_csv);
    SET v_token = TRIM(SUBSTRING(v_csv, 1, v_pos - 1));
    SET v_csv = SUBSTRING(v_csv, v_pos + 1);
    IF v_token <> '' THEN
      INSERT INTO external_entity_link (external_system_id, internal_entity_type, internal_entity_id, external_entity_type, external_entity_id)
      SELECT p_external_system_id, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, v_token
      FROM DUAL
      WHERE NOT EXISTS (
        SELECT 1 FROM external_entity_link x WHERE x.external_system_id = p_external_system_id AND x.internal_entity_type = p_internal_entity_type AND x.internal_entity_id = p_internal_entity_id AND x.external_entity_type = p_external_entity_type AND x.external_entity_id = v_token
      );
    END IF;
  END WHILE;
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'external_entity_link', p_internal_entity_id, NULL, NULL, NULL, 'Synced external ids');
  SELECT * FROM external_entity_link WHERE external_system_id = p_external_system_id AND internal_entity_type = p_internal_entity_type AND internal_entity_id = p_internal_entity_id AND external_entity_type = p_external_entity_type ORDER BY id;
END $$

DELIMITER ;
