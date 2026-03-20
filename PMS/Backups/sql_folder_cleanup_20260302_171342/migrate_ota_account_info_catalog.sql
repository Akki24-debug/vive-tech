CREATE TABLE IF NOT EXISTS ota_account_info_catalog (
  id_ota_account_info_catalog BIGINT NOT NULL AUTO_INCREMENT,
  id_ota_account BIGINT NOT NULL,
  id_line_item_catalog BIGINT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  display_alias VARCHAR(160) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ota_account_info_catalog),
  UNIQUE KEY uq_ota_account_info_catalog (id_ota_account, id_line_item_catalog),
  KEY idx_ota_account_info_catalog_ota (id_ota_account, is_active, deleted_at),
  KEY idx_ota_account_info_catalog_catalog (id_line_item_catalog, is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_display_alias := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ota_account_info_catalog'
    AND COLUMN_NAME = 'display_alias'
);

SET @sql_add_display_alias := IF(
  @has_display_alias = 0,
  'ALTER TABLE ota_account_info_catalog ADD COLUMN display_alias VARCHAR(160) NULL AFTER sort_order',
  'SELECT 1'
);

PREPARE stmt_add_display_alias FROM @sql_add_display_alias;
EXECUTE stmt_add_display_alias;
DEALLOCATE PREPARE stmt_add_display_alias;
