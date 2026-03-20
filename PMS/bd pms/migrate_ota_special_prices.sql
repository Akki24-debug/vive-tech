ALTER TABLE `ota_account`
  ADD COLUMN IF NOT EXISTS `price_adjustment_mode` enum('none','percent','fixed') NOT NULL DEFAULT 'none' AFTER `color_hex`,
  ADD COLUMN IF NOT EXISTS `price_adjustment_value` decimal(12,3) DEFAULT NULL AFTER `price_adjustment_mode`,
  ADD INDEX IF NOT EXISTS `idx_ota_account_price_adjustment` (`price_adjustment_mode`,`is_active`,`deleted_at`);

CREATE TABLE IF NOT EXISTS `ota_price_override` (
  `id_ota_price_override` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_ota_account` bigint(20) NOT NULL,
  `id_category` bigint(20) DEFAULT NULL,
  `id_room` bigint(20) DEFAULT NULL,
  `override_date` date NOT NULL,
  `price_cents` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ota_price_override`),
  KEY `idx_ota_price_override_lookup` (`id_ota_account`,`override_date`,`is_active`),
  KEY `idx_ota_price_override_category` (`id_category`,`override_date`),
  KEY `idx_ota_price_override_room` (`id_room`,`override_date`),
  CONSTRAINT `fk_ota_price_override_category` FOREIGN KEY (`id_category`) REFERENCES `roomcategory` (`id_category`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ota_price_override_ota_account` FOREIGN KEY (`id_ota_account`) REFERENCES `ota_account` (`id_ota_account`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ota_price_override_room` FOREIGN KEY (`id_room`) REFERENCES `room` (`id_room`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
