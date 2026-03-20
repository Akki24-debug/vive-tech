DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_migrate_reservation_source_visuals` $$
CREATE PROCEDURE `sp_migrate_reservation_source_visuals` ()
BEGIN
  DECLARE v_exists INT DEFAULT 0;

  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.TABLES t
  WHERE t.TABLE_SCHEMA = DATABASE()
    AND t.TABLE_NAME = 'reservation_source_catalog';

  IF v_exists = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'reservation_source_catalog does not exist. Run migrate_reservation_source_catalog.sql first.';
  END IF;

  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.COLUMNS c
  WHERE c.TABLE_SCHEMA = DATABASE()
    AND c.TABLE_NAME = 'reservation_source_catalog'
    AND c.COLUMN_NAME = 'source_code';

  IF v_exists = 0 THEN
    ALTER TABLE reservation_source_catalog
      ADD COLUMN source_code VARCHAR(24) NULL AFTER source_name;
  END IF;

  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.COLUMNS c
  WHERE c.TABLE_SCHEMA = DATABASE()
    AND c.TABLE_NAME = 'reservation_source_catalog'
    AND c.COLUMN_NAME = 'color_hex';

  IF v_exists = 0 THEN
    ALTER TABLE reservation_source_catalog
      ADD COLUMN color_hex VARCHAR(16) NULL AFTER source_code;
  END IF;

  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = DATABASE()
    AND s.TABLE_NAME = 'reservation_source_catalog'
    AND s.INDEX_NAME = 'idx_reservation_source_company_code';

  IF v_exists = 0 THEN
    ALTER TABLE reservation_source_catalog
      ADD KEY idx_reservation_source_company_code (id_company, source_code);
  END IF;
END $$

CALL `sp_migrate_reservation_source_visuals`() $$
DROP PROCEDURE IF EXISTS `sp_migrate_reservation_source_visuals` $$

DELIMITER ;

UPDATE reservation_source_catalog
SET source_code = CASE
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'booking' THEN 'BKG'
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'airbnb' THEN 'ABB'
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'expedia' THEN 'EXP'
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'directo' THEN 'DIR'
  ELSE UPPER(SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(source_name, '')), ' ', ''), '-', ''), '_', ''), 1, 6))
END
WHERE COALESCE(TRIM(source_code), '') = '';

UPDATE reservation_source_catalog
SET color_hex = CASE
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'booking' THEN '#16A34A'
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'airbnb' THEN '#EF4444'
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'expedia' THEN '#1D4ED8'
  WHEN LOWER(TRIM(COALESCE(source_name, ''))) = 'directo' THEN '#64748B'
  ELSE '#64748B'
END
WHERE COALESCE(TRIM(color_hex), '') = '';
