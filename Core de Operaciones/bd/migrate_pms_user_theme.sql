CREATE TABLE IF NOT EXISTS `pms_user_theme` (
  `id_user` bigint(20) NOT NULL,
  `theme_code` varchar(32) NOT NULL DEFAULT 'default',
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_user`),
  KEY `idx_pms_user_theme_updated_by` (`updated_by`),
  CONSTRAINT `fk_pms_user_theme_user`
    FOREIGN KEY (`id_user`) REFERENCES `app_user` (`id_user`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_pms_user_theme_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `app_user` (`id_user`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

