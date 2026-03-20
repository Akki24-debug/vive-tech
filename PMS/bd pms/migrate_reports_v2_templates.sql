CREATE TABLE IF NOT EXISTS `report_template` (
  `id_report_template` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_company` bigint(20) NOT NULL,
  `report_key` varchar(64) NOT NULL,
  `report_name` varchar(160) NOT NULL,
  `category_name` varchar(120) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `row_source` enum('reservation','line_item','combined') NOT NULL DEFAULT 'reservation',
  `line_item_type_scope` varchar(32) DEFAULT NULL,
  `default_property_code` varchar(100) DEFAULT NULL,
  `default_status` varchar(32) DEFAULT NULL,
  `default_date_type` varchar(32) DEFAULT NULL,
  `default_date_from` date DEFAULT NULL,
  `default_date_to` date DEFAULT NULL,
  `default_grid_state_json` longtext DEFAULT NULL,
  `subdivide_by_field_id` bigint(20) DEFAULT NULL,
  `subdivide_by_field_id_level_2` bigint(20) DEFAULT NULL,
  `subdivide_by_field_id_level_3` bigint(20) DEFAULT NULL,
  `subdivide_show_totals_level_1` tinyint(1) NOT NULL DEFAULT 1,
  `subdivide_show_totals_level_2` tinyint(1) NOT NULL DEFAULT 1,
  `subdivide_show_totals_level_3` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_report_template`),
  UNIQUE KEY `uk_report_template_company_key` (`id_company`,`report_key`),
  KEY `idx_report_template_company_active` (`id_company`,`is_active`,`report_name`),
  KEY `idx_report_template_subdivide_field` (`subdivide_by_field_id`),
  KEY `idx_report_template_subdivide_field_level_2` (`subdivide_by_field_id_level_2`),
  KEY `idx_report_template_subdivide_field_level_3` (`subdivide_by_field_id_level_3`),
  CONSTRAINT `fk_report_template_company`
    FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_calculation` (
  `id_report_calculation` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_company` bigint(20) NOT NULL,
  `calc_code` varchar(64) NOT NULL,
  `calc_name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `expression_text` text NOT NULL,
  `format_hint` enum('number','integer','currency') NOT NULL DEFAULT 'number',
  `decimal_places` int(11) NOT NULL DEFAULT 2,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_report_calculation`),
  UNIQUE KEY `uk_report_calculation_company_code` (`id_company`,`calc_code`),
  KEY `idx_report_calculation_company_active` (`id_company`,`is_active`,`calc_name`),
  CONSTRAINT `fk_report_calculation_company`
    FOREIGN KEY (`id_company`) REFERENCES `company` (`id_company`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_template_field` (
  `id_report_template_field` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_report_template` bigint(20) NOT NULL,
  `field_type` enum('reservation','line_item','calculated') NOT NULL,
  `display_name` varchar(160) NOT NULL,
  `default_value` varchar(255) DEFAULT NULL,
  `is_editable` tinyint(1) NOT NULL DEFAULT 0,
  `calculate_total` tinyint(1) NOT NULL DEFAULT 0,
  `allow_multiple_catalogs` tinyint(1) NOT NULL DEFAULT 0,
  `reservation_field_code` varchar(120) DEFAULT NULL,
  `id_line_item_catalog` bigint(20) DEFAULT NULL,
  `id_report_calculation` bigint(20) DEFAULT NULL,
  `source_metric` varchar(64) DEFAULT NULL,
  `format_hint` enum('auto','text','date','datetime','number','integer','currency') NOT NULL DEFAULT 'auto',
  `order_index` int(11) NOT NULL DEFAULT 1,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_report_template_field`),
  KEY `idx_report_template_field_template_active` (`id_report_template`,`is_active`,`order_index`),
  KEY `idx_report_template_field_calc` (`id_report_calculation`),
  KEY `idx_report_template_field_catalog` (`id_line_item_catalog`),
  CONSTRAINT `fk_report_template_field_template`
    FOREIGN KEY (`id_report_template`) REFERENCES `report_template` (`id_report_template`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_report_template_field_calc`
    FOREIGN KEY (`id_report_calculation`) REFERENCES `report_calculation` (`id_report_calculation`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_report_template_field_catalog`
    FOREIGN KEY (`id_line_item_catalog`) REFERENCES `line_item_catalog` (`id_line_item_catalog`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_template_field_catalog` (
  `id_report_template_field_catalog` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_report_template_field` bigint(20) NOT NULL,
  `id_line_item_catalog` bigint(20) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id_report_template_field_catalog`),
  UNIQUE KEY `uk_report_template_field_catalog_unique` (`id_report_template_field`,`id_line_item_catalog`),
  KEY `idx_report_template_field_catalog_field` (`id_report_template_field`,`sort_order`),
  KEY `idx_report_template_field_catalog_catalog` (`id_line_item_catalog`),
  CONSTRAINT `fk_report_template_field_catalog_field`
    FOREIGN KEY (`id_report_template_field`) REFERENCES `report_template_field` (`id_report_template_field`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_report_template_field_catalog_catalog`
    FOREIGN KEY (`id_line_item_catalog`) REFERENCES `line_item_catalog` (`id_line_item_catalog`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
