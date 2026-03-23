DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_guest_backfill_full_name` $$
CREATE PROCEDURE `sp_guest_backfill_full_name` ()
BEGIN
  UPDATE guest
     SET full_name = NULLIF(TRIM(CONCAT_WS(' ',
           NULLIF(TRIM(COALESCE(names, '')), ''),
           NULLIF(TRIM(COALESCE(last_name, '')), ''),
           NULLIF(TRIM(COALESCE(maiden_name, '')), '')
         )), ''),
         updated_at = NOW()
   WHERE deleted_at IS NULL;

  SELECT ROW_COUNT() AS affected_rows;
END $$

DELIMITER ;
