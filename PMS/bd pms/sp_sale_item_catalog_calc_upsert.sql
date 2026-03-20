DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_catalog_calc_upsert` $$
CREATE PROCEDURE `sp_sale_item_catalog_calc_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_item BIGINT,
  IN p_id_parent BIGINT,
  IN p_component_ids_csv TEXT,
  IN p_component_signs_csv TEXT,
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_idx INT DEFAULT 1;
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_comp BIGINT;
  DECLARE v_sign_raw VARCHAR(16);
  DECLARE v_sign INT DEFAULT 1;

  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'replace';
  END IF;
  IF p_action NOT IN ('replace','clear') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  IF p_id_item IS NULL OR p_id_item <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'item id is required';
  END IF;
  IF p_id_parent IS NULL OR p_id_parent <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'parent id is required';
  END IF;

  UPDATE line_item_catalog_calc
     SET is_active = 0,
         deleted_at = NOW(),
         updated_at = NOW()
   WHERE id_line_item_catalog = p_id_item
     AND id_parent_line_item_catalog = p_id_parent
     AND deleted_at IS NULL;

  IF p_action = 'clear' THEN
    LEAVE proc;
  END IF;

  IF p_component_ids_csv IS NULL OR TRIM(p_component_ids_csv) = '' THEN
    LEAVE proc;
  END IF;

  SET v_count = 1 + LENGTH(p_component_ids_csv) - LENGTH(REPLACE(p_component_ids_csv, ',', ''));

  WHILE v_idx <= v_count DO
    SET v_comp = CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_component_ids_csv, ',', v_idx), ',', -1)) AS UNSIGNED);
    SET v_sign_raw = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(COALESCE(p_component_signs_csv,''), ',', v_idx), ',', -1));
    SET v_sign = IF(v_sign_raw = '-1', -1, 1);

    IF v_comp IS NOT NULL AND v_comp > 0 THEN
      INSERT INTO line_item_catalog_calc (
        id_line_item_catalog,
        id_parent_line_item_catalog,
        id_component_line_item_catalog,
        is_positive,
        is_active,
        deleted_at,
        created_at,
        created_by,
        updated_at
      ) VALUES (
        p_id_item,
        p_id_parent,
        v_comp,
        IF(v_sign >= 0, 1, 0),
        1,
        NULL,
        NOW(),
        p_created_by,
        NOW()
      )
      ON DUPLICATE KEY UPDATE
        is_positive = VALUES(is_positive),
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW();
    END IF;

    SET v_idx = v_idx + 1;
  END WHILE;
END $$

DELIMITER ;

