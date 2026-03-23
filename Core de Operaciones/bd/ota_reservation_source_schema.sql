CREATE TABLE IF NOT EXISTS ota_account (
  id_ota_account BIGINT NOT NULL AUTO_INCREMENT,
  id_company BIGINT NOT NULL,
  id_property BIGINT NOT NULL,
  platform VARCHAR(32) NOT NULL DEFAULT 'other',
  ota_name VARCHAR(150) NOT NULL,
  external_code VARCHAR(120) NULL,
  contact_email VARCHAR(190) NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Mexico_City',
  notes TEXT NULL,
  id_service_fee_payment_catalog BIGINT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT NULL,
  PRIMARY KEY (id_ota_account),
  KEY idx_ota_account_company (id_company, is_active, deleted_at),
  KEY idx_ota_account_property (id_property, is_active, deleted_at),
  KEY idx_ota_account_platform (platform, is_active, deleted_at),
  KEY idx_ota_account_service_fee_catalog (id_service_fee_payment_catalog)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ota_account_lodging_catalog (
  id_ota_account_lodging_catalog BIGINT NOT NULL AUTO_INCREMENT,
  id_ota_account BIGINT NOT NULL,
  id_line_item_catalog BIGINT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ota_account_lodging_catalog),
  UNIQUE KEY uq_ota_account_lodging (id_ota_account, id_line_item_catalog),
  KEY idx_ota_account_lodging_ota (id_ota_account, is_active, deleted_at),
  KEY idx_ota_account_lodging_catalog (id_line_item_catalog, is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_reservation_ota_col = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'reservation'
    AND column_name = 'id_ota_account'
);
SET @sql_reservation_ota_col = IF(
  @has_reservation_ota_col = 0,
  'ALTER TABLE reservation ADD COLUMN id_ota_account BIGINT NULL AFTER source',
  'SELECT 1'
);
PREPARE stmt_reservation_ota_col FROM @sql_reservation_ota_col;
EXECUTE stmt_reservation_ota_col;
DEALLOCATE PREPARE stmt_reservation_ota_col;

SET @has_reservation_ota_idx = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'reservation'
    AND index_name = 'idx_reservation_ota_account'
);
SET @sql_reservation_ota_idx = IF(
  @has_reservation_ota_idx = 0,
  'ALTER TABLE reservation ADD KEY idx_reservation_ota_account (id_ota_account)',
  'SELECT 1'
);
PREPARE stmt_reservation_ota_idx FROM @sql_reservation_ota_idx;
EXECUTE stmt_reservation_ota_idx;
DEALLOCATE PREPARE stmt_reservation_ota_idx;

INSERT INTO ota_account (
  id_company,
  id_property,
  platform,
  ota_name,
  timezone,
  is_active,
  created_by
)
SELECT DISTINCT
  p.id_company,
  r.id_property,
  CASE
    WHEN LOWER(COALESCE(r.source, 'otro')) = 'airbnb' THEN 'airbnb'
    WHEN LOWER(COALESCE(r.source, 'otro')) = 'booking' THEN 'booking'
    WHEN LOWER(COALESCE(r.source, 'otro')) = 'expedia' THEN 'expedia'
    ELSE 'other'
  END AS platform,
  CASE
    WHEN LOWER(COALESCE(r.source, 'otro')) = 'airbnb' THEN 'Airbnb'
    WHEN LOWER(COALESCE(r.source, 'otro')) = 'booking' THEN 'Booking'
    WHEN LOWER(COALESCE(r.source, 'otro')) = 'expedia' THEN 'Expedia'
    ELSE 'Directo/Otro'
  END AS ota_name,
  'America/Mexico_City',
  1,
  NULL
FROM reservation r
JOIN property p
  ON p.id_property = r.id_property
 AND p.deleted_at IS NULL
LEFT JOIN ota_account oa
  ON oa.id_company = p.id_company
 AND oa.id_property = r.id_property
 AND oa.deleted_at IS NULL
 AND oa.is_active = 1
 AND oa.platform = CASE
                     WHEN LOWER(COALESCE(r.source, 'otro')) = 'airbnb' THEN 'airbnb'
                     WHEN LOWER(COALESCE(r.source, 'otro')) = 'booking' THEN 'booking'
                     WHEN LOWER(COALESCE(r.source, 'otro')) = 'expedia' THEN 'expedia'
                     ELSE 'other'
                   END
WHERE r.deleted_at IS NULL
  AND oa.id_ota_account IS NULL;

UPDATE reservation r
JOIN property p
  ON p.id_property = r.id_property
 AND p.deleted_at IS NULL
JOIN ota_account oa
  ON oa.id_company = p.id_company
 AND oa.id_property = r.id_property
 AND oa.deleted_at IS NULL
 AND oa.is_active = 1
 AND oa.platform = CASE
                     WHEN LOWER(COALESCE(r.source, 'otro')) = 'airbnb' THEN 'airbnb'
                     WHEN LOWER(COALESCE(r.source, 'otro')) = 'booking' THEN 'booking'
                     WHEN LOWER(COALESCE(r.source, 'otro')) = 'expedia' THEN 'expedia'
                     ELSE 'other'
                   END
SET r.id_ota_account = oa.id_ota_account
WHERE r.deleted_at IS NULL
  AND (r.id_ota_account IS NULL OR r.id_ota_account <= 0);
