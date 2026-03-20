DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_migrate_reservation_status_no_show` $$
CREATE PROCEDURE `sp_migrate_reservation_status_no_show` ()
BEGIN
  DECLARE v_column_type LONGTEXT DEFAULT '';
  DECLARE v_sql LONGTEXT;

  SELECT c.COLUMN_TYPE
    INTO v_column_type
  FROM information_schema.COLUMNS c
  WHERE c.TABLE_SCHEMA = DATABASE()
    AND c.TABLE_NAME = 'reservation'
    AND c.COLUMN_NAME = 'status'
  LIMIT 1;

  IF COALESCE(v_column_type, '') = '' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'reservation.status column not found';
  END IF;

  IF LOCATE('''no-show''', LOWER(v_column_type)) = 0 THEN
    IF LOWER(LEFT(v_column_type, 5)) = 'enum(' AND RIGHT(v_column_type, 1) = ')' THEN
      SET v_column_type = CONCAT(
        SUBSTRING(v_column_type, 1, CHAR_LENGTH(v_column_type) - 1),
        ',''no-show'')'
      );
    ELSE
      SET v_column_type = 'ENUM(''apartado'',''confirmado'',''en casa'',''salida'',''no-show'',''cancelada'')';
    END IF;

    SET v_sql = CONCAT(
      'ALTER TABLE reservation ',
      'MODIFY COLUMN status ',
      v_column_type,
      ' NOT NULL DEFAULT ''confirmado'''
    );

    PREPARE stmt FROM v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

CALL `sp_migrate_reservation_status_no_show`() $$
DROP PROCEDURE IF EXISTS `sp_migrate_reservation_status_no_show` $$

DELIMITER ;
