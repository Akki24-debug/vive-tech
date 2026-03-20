DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_category_upsert` $$
CREATE PROCEDURE `sp_sale_item_category_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_category BIGINT,
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_category_name VARCHAR(120),
  IN p_description TEXT,
  IN p_is_active TINYINT,
  IN p_created_by BIGINT,
  IN p_id_parent_category BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_id BIGINT;
  DECLARE v_parent_id BIGINT;
  DECLARE v_parent_set TINYINT DEFAULT 0;
  DECLARE v_dup_id BIGINT;
  DECLARE v_category_name VARCHAR(120);

  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'create';
  END IF;
  IF p_action NOT IN ('create','update','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  SET v_category_name = TRIM(COALESCE(p_category_name, ''));

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;
  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_property_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property';
    END IF;
  ELSE
    SET v_property_id = NULL;
  END IF;

  IF p_id_parent_category IS NOT NULL THEN
    SET v_parent_set = 1;
    IF p_id_parent_category = 0 THEN
      SET v_parent_id = NULL;
    ELSE
      SELECT id_sale_item_category INTO v_parent_id
      FROM sale_item_category
      WHERE id_sale_item_category = p_id_parent_category
        AND id_company = v_company_id
        AND id_property <=> v_property_id
        AND deleted_at IS NULL
      LIMIT 1;
      IF v_parent_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent category not found';
      END IF;
    END IF;
  END IF;

  IF p_action = 'create' THEN
    IF v_category_name = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category name is required';
    END IF;
    SET v_dup_id = NULL;
    SELECT id_sale_item_category INTO v_dup_id
    FROM sale_item_category
    WHERE id_company = v_company_id
      AND id_property <=> v_property_id
      AND id_parent_sale_item_category <=> v_parent_id
      AND TRIM(category_name) = v_category_name
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_dup_id IS NOT NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category already exists for this parent category';
    END IF;
    INSERT INTO sale_item_category (
      id_company,
      id_property,
      id_parent_sale_item_category,
      category_name,
      description,
      is_active,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_company_id,
      v_property_id,
      v_parent_id,
      v_category_name,
      p_description,
      COALESCE(p_is_active,1),
      NOW(),
      p_created_by,
      NOW()
    );
    SET v_id = LAST_INSERT_ID();
    SELECT * FROM sale_item_category WHERE id_sale_item_category = v_id;
    LEAVE proc;
  END IF;

  IF p_id_category IS NULL OR p_id_category = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category id is required';
  END IF;

  SELECT id_sale_item_category INTO v_id
  FROM sale_item_category
  WHERE id_sale_item_category = p_id_category
    AND id_company = v_company_id
  LIMIT 1;

  IF v_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category not found';
  END IF;

  IF v_parent_set = 1 AND v_parent_id IS NOT NULL AND v_parent_id = v_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent category cannot be self';
  END IF;

  IF p_action = 'delete' THEN
    UPDATE sale_item_category
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_sale_item_category = v_id;
  ELSE
    SET v_dup_id = NULL;
    SELECT id_sale_item_category INTO v_dup_id
    FROM sale_item_category
    WHERE id_company = v_company_id
      AND id_sale_item_category <> v_id
      AND id_property <=> COALESCE(v_property_id, id_property)
      AND id_parent_sale_item_category <=> CASE
        WHEN v_parent_set = 0 THEN id_parent_sale_item_category
        ELSE v_parent_id
      END
      AND TRIM(category_name) = CASE
        WHEN v_category_name = '' THEN TRIM(category_name)
        ELSE v_category_name
      END
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_dup_id IS NOT NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category already exists for this parent category';
    END IF;
    UPDATE sale_item_category
       SET category_name = CASE
             WHEN v_category_name = '' THEN category_name
             ELSE v_category_name
           END,
           id_parent_sale_item_category = CASE
             WHEN v_parent_set = 0 THEN id_parent_sale_item_category
             ELSE v_parent_id
           END,
           description = COALESCE(p_description, description),
           id_property = COALESCE(v_property_id, id_property),
           is_active = COALESCE(p_is_active, is_active),
           updated_at = NOW()
     WHERE id_sale_item_category = v_id;
  END IF;

  SELECT * FROM sale_item_category WHERE id_sale_item_category = v_id;
END $$

DELIMITER ;
