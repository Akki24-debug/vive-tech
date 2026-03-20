CREATE TABLE IF NOT EXISTS `reservation_source_catalog` (
  `id_reservation_source` BIGINT NOT NULL AUTO_INCREMENT,
  `id_company` BIGINT NOT NULL,
  `id_property` BIGINT NULL,
  `source_name` VARCHAR(120) NOT NULL,
  `source_code` VARCHAR(24) NULL,
  `color_hex` VARCHAR(16) NULL,
  `notes` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT NULL,
  PRIMARY KEY (`id_reservation_source`),
  UNIQUE KEY `uq_reservation_source_scope_name` (`id_company`, `id_property`, `source_name`),
  KEY `idx_reservation_source_company_property_active` (`id_company`, `id_property`, `is_active`, `deleted_at`),
  KEY `idx_reservation_source_company_code` (`id_company`, `source_code`),
  CONSTRAINT `fk_reservation_source_company`
    FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_reservation_source_property`
    FOREIGN KEY (`id_property`) REFERENCES `property` (`id_property`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
DROP PROCEDURE IF EXISTS `sp_migrate_reservation_source_catalog_struct` $$
CREATE PROCEDURE `sp_migrate_reservation_source_catalog_struct` ()
BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_fk_name VARCHAR(128) DEFAULT 'fk_reservation_source_catalog';
  DECLARE v_sql LONGTEXT;

  -- column: reservation.id_reservation_source
  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.COLUMNS c
  WHERE c.TABLE_SCHEMA = DATABASE()
    AND c.TABLE_NAME = 'reservation'
    AND c.COLUMN_NAME = 'id_reservation_source';

  IF v_exists = 0 THEN
    ALTER TABLE reservation
      ADD COLUMN id_reservation_source BIGINT NULL AFTER id_ota_account;
  END IF;

  -- index: idx_reservation_source_id
  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = DATABASE()
    AND s.TABLE_NAME = 'reservation'
    AND s.INDEX_NAME = 'idx_reservation_source_id';

  IF v_exists = 0 THEN
    ALTER TABLE reservation
      ADD KEY idx_reservation_source_id (id_reservation_source);
  END IF;

  -- source should be VARCHAR(120)
  ALTER TABLE reservation
    MODIFY COLUMN source VARCHAR(120) NULL DEFAULT NULL COLLATE utf8mb4_unicode_ci;

  -- foreign key only if reservation.id_reservation_source is not already linked
  SELECT COUNT(*)
    INTO v_exists
  FROM information_schema.KEY_COLUMN_USAGE k
  WHERE k.TABLE_SCHEMA = DATABASE()
    AND k.TABLE_NAME = 'reservation'
    AND k.COLUMN_NAME = 'id_reservation_source'
    AND k.REFERENCED_TABLE_NAME = 'reservation_source_catalog'
    AND k.REFERENCED_COLUMN_NAME = 'id_reservation_source';

  IF v_exists = 0 THEN
    -- If the standard FK name already exists anywhere in schema, use a unique fallback.
    SELECT COUNT(*)
      INTO v_exists
    FROM information_schema.TABLE_CONSTRAINTS tc
    WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
      AND tc.CONSTRAINT_NAME = v_fk_name;

    IF v_exists > 0 THEN
      SET v_fk_name = CONCAT('fk_res_source_catalog_res_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'));
    END IF;

    SET v_sql = CONCAT(
      'ALTER TABLE reservation ',
      'ADD CONSTRAINT ', v_fk_name, ' ',
      'FOREIGN KEY (id_reservation_source) ',
      'REFERENCES reservation_source_catalog (id_reservation_source) ',
      'ON UPDATE CASCADE ON DELETE SET NULL'
    );
    PREPARE stmt FROM v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$
CALL `sp_migrate_reservation_source_catalog_struct`() $$
DROP PROCEDURE IF EXISTS `sp_migrate_reservation_source_catalog_struct` $$
DELIMITER ;

INSERT IGNORE INTO reservation_source_catalog (
  id_company,
  id_property,
  source_name,
  source_code,
  color_hex,
  notes,
  is_active,
  deleted_at,
  created_by,
  updated_by
)
SELECT
  c.id_company,
  NULL,
  'Directo',
  'DIR',
  '#64748B',
  'Origen manual/directo',
  1,
  NULL,
  NULL,
  NULL
FROM company c
LEFT JOIN reservation_source_catalog rsc
  ON rsc.id_company = c.id_company
 AND rsc.id_property IS NULL
 AND LOWER(TRIM(rsc.source_name)) = 'directo'
WHERE c.deleted_at IS NULL
  AND rsc.id_reservation_source IS NULL;

INSERT IGNORE INTO reservation_source_catalog (
  id_company,
  id_property,
  source_name,
  source_code,
  color_hex,
  notes,
  is_active,
  deleted_at,
  created_by,
  updated_by
)
SELECT
  src.id_company,
  src.id_property,
  src.source_name,
  CASE
    WHEN LOWER(TRIM(COALESCE(src.source_name, ''))) = 'booking' THEN 'BKG'
    WHEN LOWER(TRIM(COALESCE(src.source_name, ''))) = 'airbnb' THEN 'ABB'
    WHEN LOWER(TRIM(COALESCE(src.source_name, ''))) = 'expedia' THEN 'EXP'
    WHEN LOWER(TRIM(COALESCE(src.source_name, ''))) = 'directo' THEN 'DIR'
    ELSE UPPER(SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(src.source_name, '')), ' ', ''), '-', ''), '_', ''), 1, 6))
  END AS source_code,
  CASE
    WHEN LOWER(TRIM(COALESCE(src.source_name, ''))) = 'booking' THEN '#16A34A'
    WHEN LOWER(TRIM(COALESCE(src.source_name, ''))) = 'airbnb' THEN '#EF4444'
    WHEN LOWER(TRIM(COALESCE(src.source_name, ''))) = 'expedia' THEN '#1D4ED8'
    ELSE '#64748B'
  END AS color_hex,
  NULL,
  1,
  NULL,
  NULL,
  NULL
FROM (
  SELECT
    p2.id_company,
    r2.id_property,
    CASE
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'booking' THEN 'Booking'
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'airbnb' THEN 'Airbnb'
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'expedia' THEN 'Expedia'
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'otro' THEN 'Directo'
      ELSE TRIM(COALESCE(r2.source, ''))
    END AS source_name
  FROM reservation r2
  JOIN property p2
    ON p2.id_property = r2.id_property
   AND p2.deleted_at IS NULL
  WHERE r2.deleted_at IS NULL
    AND COALESCE(r2.id_ota_account, 0) <= 0
  GROUP BY
    p2.id_company,
    r2.id_property,
    CASE
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'booking' THEN 'Booking'
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'airbnb' THEN 'Airbnb'
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'expedia' THEN 'Expedia'
      WHEN LOWER(TRIM(COALESCE(r2.source, ''))) = 'otro' THEN 'Directo'
      ELSE TRIM(COALESCE(r2.source, ''))
    END
) src
LEFT JOIN reservation_source_catalog rsc
  ON rsc.id_company = src.id_company
 AND (rsc.id_property <=> src.id_property)
 AND LOWER(TRIM(rsc.source_name)) = LOWER(TRIM(src.source_name))
WHERE TRIM(COALESCE(src.source_name, '')) <> ''
  AND rsc.id_reservation_source IS NULL;

UPDATE reservation r
JOIN (
  SELECT
    r1.id_reservation,
    COALESCE(
      MIN(CASE WHEN rsc.id_property = r1.id_property THEN rsc.id_reservation_source END),
      MIN(CASE WHEN rsc.id_property IS NULL THEN rsc.id_reservation_source END)
    ) AS resolved_reservation_source_id
  FROM reservation r1
  JOIN property p1
    ON p1.id_property = r1.id_property
   AND p1.deleted_at IS NULL
  JOIN (
    SELECT
      id_reservation,
      CASE
        WHEN LOWER(TRIM(COALESCE(source, ''))) = 'booking' THEN 'Booking'
        WHEN LOWER(TRIM(COALESCE(source, ''))) = 'airbnb' THEN 'Airbnb'
        WHEN LOWER(TRIM(COALESCE(source, ''))) = 'expedia' THEN 'Expedia'
        WHEN LOWER(TRIM(COALESCE(source, ''))) = 'otro' THEN 'Directo'
        ELSE TRIM(COALESCE(source, ''))
      END AS source_name
    FROM reservation
  ) src1
    ON src1.id_reservation = r1.id_reservation
  JOIN reservation_source_catalog rsc
    ON rsc.id_company = p1.id_company
   AND (rsc.id_property = r1.id_property OR rsc.id_property IS NULL)
   AND LOWER(TRIM(rsc.source_name)) = LOWER(TRIM(src1.source_name))
   AND rsc.deleted_at IS NULL
   AND rsc.is_active = 1
  WHERE r1.deleted_at IS NULL
    AND COALESCE(r1.id_ota_account, 0) <= 0
    AND COALESCE(r1.id_reservation_source, 0) <= 0
  GROUP BY r1.id_reservation
) resolved
  ON resolved.id_reservation = r.id_reservation
SET r.id_reservation_source = resolved.resolved_reservation_source_id
WHERE r.deleted_at IS NULL
  AND COALESCE(r.id_ota_account, 0) <= 0
  AND COALESCE(r.id_reservation_source, 0) <= 0
  AND COALESCE(resolved.resolved_reservation_source_id, 0) > 0;

UPDATE reservation r
JOIN property p
  ON p.id_property = r.id_property
 AND p.deleted_at IS NULL
JOIN reservation_source_catalog rsc
  ON rsc.id_company = p.id_company
 AND rsc.id_property IS NULL
 AND LOWER(TRIM(rsc.source_name)) = 'directo'
 AND rsc.deleted_at IS NULL
 AND rsc.is_active = 1
SET r.id_reservation_source = rsc.id_reservation_source
WHERE r.deleted_at IS NULL
  AND COALESCE(r.id_ota_account, 0) <= 0
  AND COALESCE(r.id_reservation_source, 0) <= 0;

UPDATE reservation
SET id_reservation_source = NULL
WHERE COALESCE(id_ota_account, 0) > 0
  AND deleted_at IS NULL;

UPDATE reservation r
JOIN reservation_source_catalog rsc
  ON rsc.id_reservation_source = r.id_reservation_source
SET r.source = rsc.source_name
WHERE r.deleted_at IS NULL
  AND COALESCE(r.id_ota_account, 0) <= 0
  AND rsc.deleted_at IS NULL
  AND rsc.is_active = 1;
