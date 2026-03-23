CREATE TABLE IF NOT EXISTS `pms_settings_lodging_folio_catalog` (
  `id_setting_lodging_folio` BIGINT NOT NULL AUTO_INCREMENT,
  `id_company` BIGINT NOT NULL,
  `id_property` BIGINT NULL,
  `id_sale_item_catalog` BIGINT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_setting_lodging_folio`),
  UNIQUE KEY `uq_pslfc_company_property_catalog` (`id_company`, `id_property`, `id_sale_item_catalog`),
  KEY `idx_pslfc_company_property` (`id_company`, `id_property`),
  KEY `idx_pslfc_catalog` (`id_sale_item_catalog`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
