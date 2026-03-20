SET @has_allow_multiple_catalogs := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_template_field'
    AND COLUMN_NAME = 'allow_multiple_catalogs'
);

SET @ddl_allow_multiple_catalogs := IF(
  @has_allow_multiple_catalogs > 0,
  'SELECT 1',
  'ALTER TABLE report_template_field ADD COLUMN allow_multiple_catalogs TINYINT(1) NOT NULL DEFAULT 0 AFTER display_name'
);

PREPARE stmt_allow_multiple_catalogs FROM @ddl_allow_multiple_catalogs;
EXECUTE stmt_allow_multiple_catalogs;
DEALLOCATE PREPARE stmt_allow_multiple_catalogs;

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

INSERT INTO report_template_field_catalog (
  id_report_template_field,
  id_line_item_catalog,
  sort_order,
  created_at,
  created_by
)
SELECT
  rtf.id_report_template_field,
  rtf.id_line_item_catalog,
  1,
  NOW(),
  rtf.created_by
FROM report_template_field rtf
LEFT JOIN report_template_field_catalog rtfc
  ON rtfc.id_report_template_field = rtf.id_report_template_field
 AND rtfc.id_line_item_catalog = rtf.id_line_item_catalog
WHERE rtf.field_type = 'line_item'
  AND rtf.id_line_item_catalog IS NOT NULL
  AND rtf.id_line_item_catalog > 0
  AND rtfc.id_report_template_field_catalog IS NULL;
