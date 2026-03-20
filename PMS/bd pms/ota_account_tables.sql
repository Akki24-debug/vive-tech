CREATE TABLE IF NOT EXISTS ota_account (
  id_ota_account BIGINT NOT NULL AUTO_INCREMENT,
  id_company BIGINT NOT NULL,
  id_property BIGINT NOT NULL,
  platform VARCHAR(32) NOT NULL DEFAULT 'other',
  ota_name VARCHAR(150) NOT NULL,
  color_hex VARCHAR(16) NULL,
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

ALTER TABLE ota_ical_feed
  ADD COLUMN IF NOT EXISTS id_ota_account BIGINT NULL;

ALTER TABLE ota_account
  ADD COLUMN IF NOT EXISTS color_hex VARCHAR(16) NULL AFTER ota_name;

ALTER TABLE ota_ical_feed
  ADD KEY IF NOT EXISTS idx_ota_ical_feed_ota_account (id_ota_account);

-- El feed iCal ya no guarda datos generales OTA (platform/timezone): viven en ota_account.
SET @has_feed_platform := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'ota_ical_feed'
    AND column_name = 'platform'
);
SET @drop_feed_platform_sql := IF(
  @has_feed_platform > 0,
  'ALTER TABLE ota_ical_feed DROP COLUMN platform',
  'SELECT 1'
);
PREPARE stmt_drop_feed_platform FROM @drop_feed_platform_sql;
EXECUTE stmt_drop_feed_platform;
DEALLOCATE PREPARE stmt_drop_feed_platform;

SET @has_feed_timezone := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'ota_ical_feed'
    AND column_name = 'timezone'
);
SET @drop_feed_timezone_sql := IF(
  @has_feed_timezone > 0,
  'ALTER TABLE ota_ical_feed DROP COLUMN timezone',
  'SELECT 1'
);
PREPARE stmt_drop_feed_timezone FROM @drop_feed_timezone_sql;
EXECUTE stmt_drop_feed_timezone;
DEALLOCATE PREPARE stmt_drop_feed_timezone;

-- Limpieza de duplicidad heredada: los conceptos ahora viven en ota_account_lodging_catalog.
SET @has_legacy_feed_concepts := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'ota_ical_feed_lodging_catalog'
);
SET @legacy_cleanup_sql := IF(
  @has_legacy_feed_concepts > 0,
  'UPDATE ota_ical_feed_lodging_catalog flc
    JOIN ota_ical_feed f ON f.id_ota_ical_feed = flc.id_ota_ical_feed
       SET flc.is_active = 0,
           flc.deleted_at = NOW(),
           flc.updated_at = NOW()
     WHERE f.id_ota_account IS NOT NULL
       AND f.id_ota_account > 0
       AND flc.deleted_at IS NULL',
  'SELECT 1'
);
PREPARE stmt_legacy_cleanup FROM @legacy_cleanup_sql;
EXECUTE stmt_legacy_cleanup;
DEALLOCATE PREPARE stmt_legacy_cleanup;
