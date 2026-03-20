DELIMITER $$

DROP PROCEDURE IF EXISTS sp_fix_all_folio_totals $$
CREATE PROCEDURE sp_fix_all_folio_totals()
BEGIN
  DECLARE v_done TINYINT DEFAULT 0;
  DECLARE v_id_folio BIGINT;

  DECLARE cur CURSOR FOR
    SELECT f.id_folio
    FROM folio f
    WHERE f.deleted_at IS NULL;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  OPEN cur;
  folio_loop: LOOP
    FETCH cur INTO v_id_folio;
    IF v_done = 1 THEN
      LEAVE folio_loop;
    END IF;

    CALL sp_folio_recalc(v_id_folio);
  END LOOP;
  CLOSE cur;
END $$

DELIMITER ;

-- Ejecutar una vez para reparar folios existentes:
-- CALL sp_fix_all_folio_totals();
