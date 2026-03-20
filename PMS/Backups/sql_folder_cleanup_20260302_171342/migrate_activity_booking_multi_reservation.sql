DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_migrate_activity_booking_multi_reservation` $$
CREATE PROCEDURE `sp_migrate_activity_booking_multi_reservation` ()
BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_name_taken INT DEFAULT 0;
  DECLARE v_fk_name VARCHAR(128);
  DECLARE v_sql LONGTEXT;

  CREATE TABLE IF NOT EXISTS activity_booking_reservation (
    id_booking BIGINT NOT NULL,
    id_reservation BIGINT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_booking, id_reservation),
    KEY idx_abr_booking (id_booking),
    KEY idx_abr_reservation (id_reservation),
    KEY idx_abr_active (is_active, deleted_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = DATABASE()
    AND s.TABLE_NAME = 'activity_booking_reservation'
    AND s.INDEX_NAME = 'idx_abr_booking';
  IF v_exists = 0 THEN
    ALTER TABLE activity_booking_reservation
      ADD KEY idx_abr_booking (id_booking);
  END IF;

  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = DATABASE()
    AND s.TABLE_NAME = 'activity_booking_reservation'
    AND s.INDEX_NAME = 'idx_abr_reservation';
  IF v_exists = 0 THEN
    ALTER TABLE activity_booking_reservation
      ADD KEY idx_abr_reservation (id_reservation);
  END IF;

  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = DATABASE()
    AND s.TABLE_NAME = 'activity_booking_reservation'
    AND s.INDEX_NAME = 'idx_abr_active';
  IF v_exists = 0 THEN
    ALTER TABLE activity_booking_reservation
      ADD KEY idx_abr_active (is_active, deleted_at);
  END IF;

  /* FK id_booking -> activity_booking.id_booking */
  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.KEY_COLUMN_USAGE k
  WHERE k.TABLE_SCHEMA = DATABASE()
    AND k.TABLE_NAME = 'activity_booking_reservation'
    AND k.COLUMN_NAME = 'id_booking'
    AND k.REFERENCED_TABLE_NAME = 'activity_booking'
    AND k.REFERENCED_COLUMN_NAME = 'id_booking';
  IF v_exists = 0 THEN
    SET v_fk_name = 'fk_abr_booking';
    SELECT COUNT(*)
      INTO v_name_taken
    FROM information_schema.TABLE_CONSTRAINTS tc
    WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
      AND tc.CONSTRAINT_NAME = v_fk_name;
    IF v_name_taken > 0 THEN
      SET v_fk_name = CONCAT('fk_abr_booking_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'));
    END IF;
    SET v_sql = CONCAT(
      'ALTER TABLE activity_booking_reservation ',
      'ADD CONSTRAINT ', v_fk_name, ' ',
      'FOREIGN KEY (id_booking) REFERENCES activity_booking (id_booking) ',
      'ON UPDATE CASCADE ON DELETE CASCADE'
    );
    PREPARE stmt FROM v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;

  /* FK id_reservation -> reservation.id_reservation */
  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.KEY_COLUMN_USAGE k
  WHERE k.TABLE_SCHEMA = DATABASE()
    AND k.TABLE_NAME = 'activity_booking_reservation'
    AND k.COLUMN_NAME = 'id_reservation'
    AND k.REFERENCED_TABLE_NAME = 'reservation'
    AND k.REFERENCED_COLUMN_NAME = 'id_reservation';
  IF v_exists = 0 THEN
    SET v_fk_name = 'fk_abr_reservation';
    SELECT COUNT(*)
      INTO v_name_taken
    FROM information_schema.TABLE_CONSTRAINTS tc
    WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
      AND tc.CONSTRAINT_NAME = v_fk_name;
    IF v_name_taken > 0 THEN
      SET v_fk_name = CONCAT('fk_abr_reservation_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'));
    END IF;
    SET v_sql = CONCAT(
      'ALTER TABLE activity_booking_reservation ',
      'ADD CONSTRAINT ', v_fk_name, ' ',
      'FOREIGN KEY (id_reservation) REFERENCES reservation (id_reservation) ',
      'ON UPDATE CASCADE ON DELETE CASCADE'
    );
    PREPARE stmt FROM v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;

  /* FK created_by -> app_user.id_user */
  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.KEY_COLUMN_USAGE k
  WHERE k.TABLE_SCHEMA = DATABASE()
    AND k.TABLE_NAME = 'activity_booking_reservation'
    AND k.COLUMN_NAME = 'created_by'
    AND k.REFERENCED_TABLE_NAME = 'app_user'
    AND k.REFERENCED_COLUMN_NAME = 'id_user';
  IF v_exists = 0 THEN
    SET v_fk_name = 'fk_abr_created_by';
    SELECT COUNT(*)
      INTO v_name_taken
    FROM information_schema.TABLE_CONSTRAINTS tc
    WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
      AND tc.CONSTRAINT_NAME = v_fk_name;
    IF v_name_taken > 0 THEN
      SET v_fk_name = CONCAT('fk_abr_created_by_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'));
    END IF;
    SET v_sql = CONCAT(
      'ALTER TABLE activity_booking_reservation ',
      'ADD CONSTRAINT ', v_fk_name, ' ',
      'FOREIGN KEY (created_by) REFERENCES app_user (id_user) ',
      'ON UPDATE CASCADE ON DELETE SET NULL'
    );
    PREPARE stmt FROM v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

CALL `sp_migrate_activity_booking_multi_reservation`() $$
DROP PROCEDURE IF EXISTS `sp_migrate_activity_booking_multi_reservation` $$

DELIMITER ;

INSERT IGNORE INTO activity_booking_reservation (
  id_booking,
  id_reservation,
  is_active,
  deleted_at,
  created_at,
  created_by,
  updated_at
)
SELECT
  ab.id_booking,
  ab.id_reservation,
  1,
  NULL,
  COALESCE(ab.created_at, NOW()),
  ab.created_by,
  NOW()
FROM activity_booking ab
WHERE ab.id_reservation IS NOT NULL
  AND ab.deleted_at IS NULL;
