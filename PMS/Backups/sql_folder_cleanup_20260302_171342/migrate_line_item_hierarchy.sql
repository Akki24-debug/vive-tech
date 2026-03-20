CREATE TABLE IF NOT EXISTS `line_item_hierarchy` (
  `id_line_item_hierarchy` BIGINT NOT NULL AUTO_INCREMENT,
  `id_line_item_child` BIGINT NOT NULL,
  `id_line_item_parent` BIGINT NOT NULL,
  `relation_kind` ENUM('derived_percent', 'legacy_backfill', 'manual', 'derived') NOT NULL DEFAULT 'derived_percent',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT DEFAULT NULL,
  PRIMARY KEY (`id_line_item_hierarchy`),
  UNIQUE KEY `uq_line_item_hierarchy_child` (`id_line_item_child`),
  KEY `idx_line_item_hierarchy_parent` (`id_line_item_parent`),
  KEY `idx_line_item_hierarchy_active` (`is_active`, `deleted_at`),
  CONSTRAINT `fk_lih_child_line_item`
    FOREIGN KEY (`id_line_item_child`) REFERENCES `line_item` (`id_line_item`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_lih_parent_line_item`
    FOREIGN KEY (`id_line_item_parent`) REFERENCES `line_item` (`id_line_item`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE h
FROM line_item_hierarchy h
LEFT JOIN line_item c
  ON c.id_line_item = h.id_line_item_child
LEFT JOIN line_item p
  ON p.id_line_item = h.id_line_item_parent
WHERE c.id_line_item IS NULL
   OR p.id_line_item IS NULL
   OR h.id_line_item_child = h.id_line_item_parent;

INSERT INTO `line_item_hierarchy` (
  `id_line_item_child`,
  `id_line_item_parent`,
  `relation_kind`,
  `is_active`,
  `deleted_at`,
  `created_by`,
  `updated_by`
)
SELECT
  c.id_line_item AS id_line_item_child,
  MAX(p.id_line_item) AS id_line_item_parent,
  'legacy_backfill' AS relation_kind,
  1 AS is_active,
  NULL AS deleted_at,
  NULL AS created_by,
  NULL AS updated_by
FROM line_item c
JOIN line_item_catalog_parent lcp
  ON lcp.id_sale_item_catalog = c.id_line_item_catalog
 AND lcp.deleted_at IS NULL
 AND lcp.is_active = 1
JOIN line_item p
  ON p.id_folio = c.id_folio
 AND p.id_line_item_catalog = lcp.id_parent_sale_item_catalog
 AND p.id_line_item <> c.id_line_item
 AND p.deleted_at IS NULL
 AND p.is_active = 1
LEFT JOIN line_item_catalog cc
  ON cc.id_line_item_catalog = c.id_line_item_catalog
LEFT JOIN line_item_catalog pc
  ON pc.id_line_item_catalog = lcp.id_parent_sale_item_catalog
WHERE c.deleted_at IS NULL
  AND c.is_active = 1
  AND c.item_type IN ('sale_item', 'tax_item', 'payment', 'obligation', 'income')
  AND (
    COALESCE(c.description, '') = CONCAT(COALESCE(cc.item_name, ''), ' / ', COALESCE(pc.item_name, ''))
    OR COALESCE(c.description, '') = CONCAT('[AUTO-DERIVED parent_line_item=', p.id_line_item, ']')
  )
GROUP BY c.id_line_item
ON DUPLICATE KEY UPDATE
  id_line_item_parent = VALUES(id_line_item_parent),
  relation_kind = VALUES(relation_kind),
  is_active = 1,
  deleted_at = NULL,
  updated_at = CURRENT_TIMESTAMP;

SET @schema_name = DATABASE();

DELETE h1
FROM line_item_hierarchy h1
JOIN line_item_hierarchy h2
  ON h1.id_line_item_child = h2.id_line_item_child
 AND h1.id_line_item_hierarchy < h2.id_line_item_hierarchy;

ALTER TABLE `line_item_hierarchy`
  MODIFY COLUMN `relation_kind` ENUM('derived_percent', 'legacy_backfill', 'manual', 'derived')
  NOT NULL DEFAULT 'derived_percent';

SELECT COUNT(*)
  INTO @uq_lih_child_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME = 'line_item_hierarchy'
  AND INDEX_NAME = 'uq_line_item_hierarchy_child'
  AND NON_UNIQUE = 0;

SET @sql_uq_lih_child = IF(
  @uq_lih_child_exists = 0,
  'ALTER TABLE `line_item_hierarchy`
     ADD UNIQUE KEY `uq_line_item_hierarchy_child` (`id_line_item_child`)',
  'SELECT 1'
);
PREPARE stmt_uq_lih_child FROM @sql_uq_lih_child;
EXECUTE stmt_uq_lih_child;
DEALLOCATE PREPARE stmt_uq_lih_child;

SELECT COUNT(*)
  INTO @idx_lih_parent_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME = 'line_item_hierarchy'
  AND INDEX_NAME = 'idx_line_item_hierarchy_parent';

SET @sql_idx_lih_parent = IF(
  @idx_lih_parent_exists = 0,
  'ALTER TABLE `line_item_hierarchy`
     ADD KEY `idx_line_item_hierarchy_parent` (`id_line_item_parent`)',
  'SELECT 1'
);
PREPARE stmt_idx_lih_parent FROM @sql_idx_lih_parent;
EXECUTE stmt_idx_lih_parent;
DEALLOCATE PREPARE stmt_idx_lih_parent;

SELECT COUNT(*)
  INTO @fk_lih_child_exists
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME = 'line_item_hierarchy'
  AND CONSTRAINT_NAME = 'fk_lih_child_line_item'
  AND CONSTRAINT_TYPE = 'FOREIGN KEY';

SET @sql_fk_lih_child = IF(
  @fk_lih_child_exists = 0,
  'ALTER TABLE `line_item_hierarchy`
     ADD CONSTRAINT `fk_lih_child_line_item`
     FOREIGN KEY (`id_line_item_child`) REFERENCES `line_item` (`id_line_item`)
     ON UPDATE CASCADE ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_lih_child FROM @sql_fk_lih_child;
EXECUTE stmt_fk_lih_child;
DEALLOCATE PREPARE stmt_fk_lih_child;

SELECT COUNT(*)
  INTO @fk_lih_parent_exists
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME = 'line_item_hierarchy'
  AND CONSTRAINT_NAME = 'fk_lih_parent_line_item'
  AND CONSTRAINT_TYPE = 'FOREIGN KEY';

SET @sql_fk_lih_parent = IF(
  @fk_lih_parent_exists = 0,
  'ALTER TABLE `line_item_hierarchy`
     ADD CONSTRAINT `fk_lih_parent_line_item`
     FOREIGN KEY (`id_line_item_parent`) REFERENCES `line_item` (`id_line_item`)
     ON UPDATE CASCADE ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_lih_parent FROM @sql_fk_lih_parent;
EXECUTE stmt_fk_lih_parent;
DEALLOCATE PREPARE stmt_fk_lih_parent;
