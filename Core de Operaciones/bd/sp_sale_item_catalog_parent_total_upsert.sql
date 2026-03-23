DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_catalog_parent_total_upsert` $$
CREATE PROCEDURE `sp_sale_item_catalog_parent_total_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_item BIGINT,
  IN p_id_parent BIGINT,
  IN p_add_to_father_total TINYINT,
  IN p_show_in_folio_relation TINYINT,
  IN p_percent_value DECIMAL(12,6),
  IN p_created_by BIGINT
)
BEGIN
  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'upsert';
  END IF;
  IF p_action NOT IN ('upsert') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  IF p_id_item IS NULL OR p_id_item <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'item id is required';
  END IF;
  IF p_id_parent IS NULL OR p_id_parent <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'parent id is required';
  END IF;

  INSERT INTO line_item_catalog_parent (
    id_sale_item_catalog,
    id_parent_sale_item_catalog,
    add_to_father_total,
    show_in_folio_relation,
    percent_value,
    is_active,
    deleted_at,
    created_at,
    created_by,
    updated_at
  ) VALUES (
    p_id_item,
    p_id_parent,
    IF(COALESCE(p_add_to_father_total, 1) = 1, 1, 0),
    CASE
      WHEN p_show_in_folio_relation IS NULL THEN NULL
      WHEN COALESCE(p_show_in_folio_relation, 0) = 1 THEN 1
      ELSE 0
    END,
    p_percent_value,
    1,
    NULL,
    NOW(),
    p_created_by,
    NOW()
  )
  ON DUPLICATE KEY UPDATE
    add_to_father_total = VALUES(add_to_father_total),
    show_in_folio_relation = VALUES(show_in_folio_relation),
    percent_value = VALUES(percent_value),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();
END $$

DELIMITER ;
