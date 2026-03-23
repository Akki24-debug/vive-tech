DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_catalog_upsert` $$
CREATE PROCEDURE `sp_sale_item_catalog_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_item BIGINT,
  IN p_company_code VARCHAR(100),
  IN p_catalog_type VARCHAR(32),
  IN p_id_category BIGINT,
  IN p_parent_ids TEXT,
  IN p_item_name VARCHAR(255),
  IN p_description TEXT,
  IN p_unit_price_cents INT,
  IN p_is_percent TINYINT,
  IN p_percent_value DECIMAL(9,4),
  IN p_tax_rule_ids TEXT,
  IN p_show_in_folio TINYINT,
  IN p_allow_negative TINYINT,
  IN p_is_active TINYINT,
  IN p_add_to_father_total TINYINT,
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_item_id BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_catalog_property_id BIGINT;
  DECLARE v_catalog_type VARCHAR(32);

  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'create';
  END IF;
  IF p_action NOT IN ('create','update','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;
  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SET v_catalog_type = CASE
    WHEN p_catalog_type IS NULL OR CHAR_LENGTH(p_catalog_type) = 0 THEN 'sale_item'
    ELSE p_catalog_type
  END;
  IF v_catalog_type NOT IN ('sale_item','payment','obligation','income','tax_rule') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported catalog type';
  END IF;

  IF p_action = 'create' THEN
    IF p_item_name IS NULL OR p_item_name = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Item name is required';
    END IF;
    IF p_id_category IS NULL OR p_id_category = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category is required';
    END IF;

    SELECT id_sale_item_category, id_property
      INTO v_id_category, v_catalog_property_id
    FROM sale_item_category
    WHERE id_sale_item_category = p_id_category
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_id_category IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category not found';
    END IF;

    INSERT INTO line_item_catalog (
      catalog_type,
      id_category,
      item_name,
      description,
      default_unit_price_cents,
      show_in_folio,
      allow_negative,
      default_amount_cents,
      is_active,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_catalog_type,
      v_id_category,
      p_item_name,
      p_description,
      COALESCE(p_unit_price_cents,0),
      COALESCE(p_show_in_folio,1),
      COALESCE(p_allow_negative,0),
      COALESCE(p_unit_price_cents,0),
      COALESCE(p_is_active,1),
      NOW(),
      p_created_by,
      NOW()
    );
    SET v_item_id = LAST_INSERT_ID();

    IF p_parent_ids IS NOT NULL AND p_parent_ids <> '' THEN
      INSERT INTO line_item_catalog_parent (
        id_sale_item_catalog,
        id_parent_sale_item_catalog,
        add_to_father_total,
        show_in_folio_relation,
        percent_value,
        is_active,
        created_at,
        created_by,
        updated_at
      )
      SELECT
        v_item_id,
        parent.id_line_item_catalog,
        COALESCE(p_add_to_father_total,1),
        COALESCE(p_show_in_folio,1),
        CASE WHEN COALESCE(p_is_percent,0) = 1 THEN p_percent_value ELSE NULL END,
        1,
        NOW(),
        p_created_by,
        NOW()
      FROM line_item_catalog parent
      LEFT JOIN sale_item_category cat_parent ON cat_parent.id_sale_item_category = parent.id_category
      WHERE parent.deleted_at IS NULL
        AND FIND_IN_SET(parent.id_line_item_catalog, p_parent_ids)
        AND cat_parent.id_company = v_company_id
        AND cat_parent.deleted_at IS NULL
        AND (cat_parent.id_property IS NULL OR v_catalog_property_id IS NULL OR cat_parent.id_property = v_catalog_property_id)
      ON DUPLICATE KEY UPDATE
        add_to_father_total = VALUES(add_to_father_total),
        show_in_folio_relation = VALUES(show_in_folio_relation),
        percent_value = VALUES(percent_value),
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW();
    END IF;

    SELECT
      lic.id_line_item_catalog AS id_sale_item_catalog,
      lic.catalog_type,
      lic.id_category,
      parent_map.parent_first_id AS id_parent_sale_item_catalog,
      parent_first.item_name AS parent_item_name,
      parent_map.parent_item_ids,
      parent_map.add_to_father_total,
      lic.item_name,
      lic.description,
      lic.default_unit_price_cents,
      parent_map.is_percent AS is_percent,
      parent_map.percent_value AS percent_value,
      lic.show_in_folio,
      lic.allow_negative,
      lic.is_active,
      lic.deleted_at,
      lic.created_at,
      lic.created_by,
      lic.updated_at
    FROM line_item_catalog lic
    LEFT JOIN (
      SELECT
        lcp.id_sale_item_catalog,
        GROUP_CONCAT(DISTINCT lcp.id_parent_sale_item_catalog ORDER BY lcp.id_parent_sale_item_catalog) AS parent_item_ids,
        MIN(lcp.id_parent_sale_item_catalog) AS parent_first_id,
        MIN(lcp.add_to_father_total) AS add_to_father_total,
        MAX(CASE WHEN lcp.percent_value IS NOT NULL THEN 1 ELSE 0 END) AS is_percent,
        MIN(lcp.percent_value) AS percent_value
      FROM line_item_catalog_parent lcp
      WHERE lcp.deleted_at IS NULL
        AND lcp.is_active = 1
      GROUP BY lcp.id_sale_item_catalog
    ) parent_map ON parent_map.id_sale_item_catalog = lic.id_line_item_catalog
    LEFT JOIN line_item_catalog parent_first
      ON parent_first.id_line_item_catalog = parent_map.parent_first_id
    WHERE lic.id_line_item_catalog = v_item_id;

    LEAVE proc;
  END IF;

  IF p_id_item IS NULL OR p_id_item = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Item id is required';
  END IF;

  SELECT lic.id_line_item_catalog, lic.catalog_type, lic.id_category, cat.id_property
    INTO v_item_id, v_catalog_type, v_id_category, v_catalog_property_id
  FROM line_item_catalog lic
  LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = lic.id_category
  WHERE lic.id_line_item_catalog = p_id_item
    AND cat.id_company = v_company_id
    AND cat.deleted_at IS NULL
  LIMIT 1;

  IF v_item_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Item not found';
  END IF;

  SET v_catalog_type = CASE
    WHEN p_catalog_type IS NULL OR CHAR_LENGTH(p_catalog_type) = 0 THEN v_catalog_type
    ELSE p_catalog_type
  END;
  IF v_catalog_type NOT IN ('sale_item','payment','obligation','income','tax_rule') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported catalog type';
  END IF;

  IF p_parent_ids IS NOT NULL AND p_parent_ids <> '' AND FIND_IN_SET(v_item_id, p_parent_ids) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent item cannot be self';
  END IF;

  IF p_action = 'delete' THEN
    UPDATE line_item_catalog
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_line_item_catalog = v_item_id;
  ELSE
    IF p_id_category IS NULL OR p_id_category = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category is required';
    END IF;

    SELECT id_sale_item_category, id_property
      INTO v_id_category, v_catalog_property_id
    FROM sale_item_category
    WHERE id_sale_item_category = p_id_category
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_id_category IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category not found';
    END IF;

    UPDATE line_item_catalog
       SET catalog_type = v_catalog_type,
           id_category = v_id_category,
           item_name = CASE
             WHEN p_item_name IS NULL OR CHAR_LENGTH(p_item_name) = 0 THEN item_name
             ELSE p_item_name
           END,
           description = COALESCE(p_description, description),
           default_unit_price_cents = COALESCE(p_unit_price_cents, default_unit_price_cents),
           show_in_folio = COALESCE(p_show_in_folio, show_in_folio),
           allow_negative = COALESCE(p_allow_negative, allow_negative),
           default_amount_cents = COALESCE(p_unit_price_cents, default_amount_cents),
           is_active = COALESCE(p_is_active, is_active),
           updated_at = NOW()
     WHERE id_line_item_catalog = v_item_id;
  END IF;

  IF p_parent_ids IS NOT NULL THEN
    UPDATE line_item_catalog_parent
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_sale_item_catalog = v_item_id;

    IF p_parent_ids <> '' THEN
      INSERT INTO line_item_catalog_parent (
        id_sale_item_catalog,
        id_parent_sale_item_catalog,
        add_to_father_total,
        show_in_folio_relation,
        percent_value,
        is_active,
        created_at,
        created_by,
        updated_at
      )
      SELECT
        v_item_id,
        parent.id_line_item_catalog,
        COALESCE(p_add_to_father_total,1),
        COALESCE(p_show_in_folio,1),
        CASE WHEN COALESCE(p_is_percent,0) = 1 THEN p_percent_value ELSE NULL END,
        1,
        NOW(),
        p_created_by,
        NOW()
      FROM line_item_catalog parent
      LEFT JOIN sale_item_category cat_parent ON cat_parent.id_sale_item_category = parent.id_category
      WHERE parent.deleted_at IS NULL
        AND FIND_IN_SET(parent.id_line_item_catalog, p_parent_ids)
        AND cat_parent.id_company = v_company_id
        AND cat_parent.deleted_at IS NULL
        AND (cat_parent.id_property IS NULL OR v_catalog_property_id IS NULL OR cat_parent.id_property = v_catalog_property_id)
      ON DUPLICATE KEY UPDATE
        add_to_father_total = VALUES(add_to_father_total),
        show_in_folio_relation = VALUES(show_in_folio_relation),
        percent_value = VALUES(percent_value),
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW();
    END IF;
  END IF;

  SELECT
    lic.id_line_item_catalog AS id_sale_item_catalog,
    lic.catalog_type,
    lic.id_category,
    parent_map.parent_first_id AS id_parent_sale_item_catalog,
    parent_first.item_name AS parent_item_name,
    parent_map.parent_item_ids,
    parent_map.add_to_father_total,
    lic.item_name,
    lic.description,
    lic.default_unit_price_cents,
    parent_map.is_percent AS is_percent,
    parent_map.percent_value AS percent_value,
    lic.show_in_folio,
    lic.allow_negative,
    lic.is_active,
    lic.deleted_at,
    lic.created_at,
    lic.created_by,
    lic.updated_at
  FROM line_item_catalog lic
  LEFT JOIN (
    SELECT
      lcp.id_sale_item_catalog,
      GROUP_CONCAT(DISTINCT lcp.id_parent_sale_item_catalog ORDER BY lcp.id_parent_sale_item_catalog) AS parent_item_ids,
      MIN(lcp.id_parent_sale_item_catalog) AS parent_first_id,
      MIN(lcp.add_to_father_total) AS add_to_father_total,
      MAX(CASE WHEN lcp.percent_value IS NOT NULL THEN 1 ELSE 0 END) AS is_percent,
      MIN(lcp.percent_value) AS percent_value
    FROM line_item_catalog_parent lcp
    WHERE lcp.deleted_at IS NULL
      AND lcp.is_active = 1
    GROUP BY lcp.id_sale_item_catalog
  ) parent_map ON parent_map.id_sale_item_catalog = lic.id_line_item_catalog
  LEFT JOIN line_item_catalog parent_first
    ON parent_first.id_line_item_catalog = parent_map.parent_first_id
  WHERE lic.id_line_item_catalog = v_item_id;
END $$

DELIMITER ;
