CREATE TABLE IF NOT EXISTS `pms_settings_lodging_payment_block` (
  `id_setting_lodging_payment_block` BIGINT NOT NULL AUTO_INCREMENT,
  `id_company` BIGINT NOT NULL,
  `id_property` BIGINT NULL,
  `id_lodging_catalog` BIGINT NOT NULL,
  `id_payment_catalog` BIGINT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT NULL,
  PRIMARY KEY (`id_setting_lodging_payment_block`),
  UNIQUE KEY `uq_pslpb_company_property_lodging_payment` (`id_company`, `id_property`, `id_lodging_catalog`, `id_payment_catalog`),
  KEY `idx_pslpb_scope` (`id_company`, `id_property`, `is_active`, `deleted_at`),
  KEY `idx_pslpb_lodging` (`id_lodging_catalog`),
  KEY `idx_pslpb_payment` (`id_payment_catalog`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
