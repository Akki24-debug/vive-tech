DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_fix_reservation_source_catalog_duplicates` $$
CREATE PROCEDURE `sp_fix_reservation_source_catalog_duplicates` ()
BEGIN
  DROP TEMPORARY TABLE IF EXISTS tmp_rsc_keep;
  CREATE TEMPORARY TABLE tmp_rsc_keep AS
  SELECT
    rsc.id_company,
    COALESCE(rsc.id_property, 0) AS scope_property_id,
    LOWER(TRIM(COALESCE(rsc.source_name, ''))) AS source_name_norm,
    MIN(rsc.id_reservation_source) AS keep_id,
    COUNT(*) AS total_rows
  FROM reservation_source_catalog rsc
  WHERE rsc.deleted_at IS NULL
  GROUP BY
    rsc.id_company,
    COALESCE(rsc.id_property, 0),
    LOWER(TRIM(COALESCE(rsc.source_name, '')))
  HAVING COUNT(*) > 1;

  DROP TEMPORARY TABLE IF EXISTS tmp_rsc_drop;
  CREATE TEMPORARY TABLE tmp_rsc_drop AS
  SELECT
    rsc.id_reservation_source AS drop_id,
    k.keep_id
  FROM reservation_source_catalog rsc
  JOIN tmp_rsc_keep k
    ON k.id_company = rsc.id_company
   AND k.scope_property_id = COALESCE(rsc.id_property, 0)
   AND k.source_name_norm = LOWER(TRIM(COALESCE(rsc.source_name, '')))
  WHERE rsc.deleted_at IS NULL
    AND rsc.id_reservation_source <> k.keep_id;

  UPDATE reservation r
  JOIN tmp_rsc_drop d
    ON d.drop_id = r.id_reservation_source
  SET r.id_reservation_source = d.keep_id,
      r.updated_at = NOW()
  WHERE r.deleted_at IS NULL;

  UPDATE reservation_source_catalog rsc
  JOIN tmp_rsc_drop d
    ON d.drop_id = rsc.id_reservation_source
  SET rsc.is_active = 0,
      rsc.deleted_at = NOW(),
      rsc.updated_at = NOW()
  WHERE rsc.deleted_at IS NULL;

  SELECT
    (SELECT COUNT(*) FROM tmp_rsc_keep) AS duplicated_groups,
    (SELECT COUNT(*) FROM tmp_rsc_drop) AS duplicated_rows_removed;
END $$

CALL `sp_fix_reservation_source_catalog_duplicates`() $$
DROP PROCEDURE IF EXISTS `sp_fix_reservation_source_catalog_duplicates` $$

DELIMITER ;
