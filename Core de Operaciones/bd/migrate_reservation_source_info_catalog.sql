CREATE TABLE IF NOT EXISTS `reservation_source_info_catalog` (
  `id_reservation_source_info_catalog` BIGINT NOT NULL AUTO_INCREMENT,
  `id_reservation_source` BIGINT NOT NULL,
  `id_line_item_catalog` BIGINT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `display_alias` VARCHAR(160) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reservation_source_info_catalog`),
  UNIQUE KEY `uq_reservation_source_info_catalog` (`id_reservation_source`, `id_line_item_catalog`),
  KEY `idx_reservation_source_info_catalog_source` (`id_reservation_source`, `is_active`, `deleted_at`),
  KEY `idx_reservation_source_info_catalog_catalog` (`id_line_item_catalog`, `is_active`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_sort_order := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservation_source_info_catalog'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql_add_sort_order := IF(
  @has_sort_order = 0,
  'ALTER TABLE reservation_source_info_catalog ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER id_line_item_catalog',
  'SELECT 1'
);
PREPARE stmt_add_sort_order FROM @sql_add_sort_order;
EXECUTE stmt_add_sort_order;
DEALLOCATE PREPARE stmt_add_sort_order;

SET @has_display_alias := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservation_source_info_catalog'
    AND COLUMN_NAME = 'display_alias'
);
SET @sql_add_display_alias := IF(
  @has_display_alias = 0,
  'ALTER TABLE reservation_source_info_catalog ADD COLUMN display_alias VARCHAR(160) NULL AFTER sort_order',
  'SELECT 1'
);
PREPARE stmt_add_display_alias FROM @sql_add_display_alias;
EXECUTE stmt_add_display_alias;
DEALLOCATE PREPARE stmt_add_display_alias;

SET @has_unique_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservation_source_info_catalog'
    AND INDEX_NAME = 'uq_reservation_source_info_catalog'
);
SET @sql_add_unique_idx := IF(
  @has_unique_idx = 0,
  'ALTER TABLE reservation_source_info_catalog ADD UNIQUE KEY uq_reservation_source_info_catalog (id_reservation_source, id_line_item_catalog)',
  'SELECT 1'
);
PREPARE stmt_add_unique_idx FROM @sql_add_unique_idx;
EXECUTE stmt_add_unique_idx;
DEALLOCATE PREPARE stmt_add_unique_idx;

SET @has_source_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservation_source_info_catalog'
    AND INDEX_NAME = 'idx_reservation_source_info_catalog_source'
);
SET @sql_add_source_idx := IF(
  @has_source_idx = 0,
  'ALTER TABLE reservation_source_info_catalog ADD KEY idx_reservation_source_info_catalog_source (id_reservation_source, is_active, deleted_at)',
  'SELECT 1'
);
PREPARE stmt_add_source_idx FROM @sql_add_source_idx;
EXECUTE stmt_add_source_idx;
DEALLOCATE PREPARE stmt_add_source_idx;

SET @has_catalog_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reservation_source_info_catalog'
    AND INDEX_NAME = 'idx_reservation_source_info_catalog_catalog'
);
SET @sql_add_catalog_idx := IF(
  @has_catalog_idx = 0,
  'ALTER TABLE reservation_source_info_catalog ADD KEY idx_reservation_source_info_catalog_catalog (id_line_item_catalog, is_active, deleted_at)',
  'SELECT 1'
);
PREPARE stmt_add_catalog_idx FROM @sql_add_catalog_idx;
EXECUTE stmt_add_catalog_idx;
DEALLOCATE PREPARE stmt_add_catalog_idx;
