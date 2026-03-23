DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_catalog_clone` $$
CREATE PROCEDURE `sp_sale_item_catalog_clone` (
  IN p_company_code VARCHAR(100),
  IN p_source_item_id BIGINT,
  IN p_clone_item_name VARCHAR(255),
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_source_catalog_type VARCHAR(32);
  DECLARE v_source_category_id BIGINT;
  DECLARE v_source_item_name VARCHAR(255);
  DECLARE v_source_description TEXT;
  DECLARE v_source_unit_price_cents INT;
  DECLARE v_source_default_amount_cents INT;
  DECLARE v_source_show_in_folio TINYINT;
  DECLARE v_source_allow_negative TINYINT;
  DECLARE v_source_is_active TINYINT;
  DECLARE v_new_item_id BIGINT;
  DECLARE v_new_item_name VARCHAR(255);

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'company code is required';
  END IF;
  IF p_source_item_id IS NULL OR p_source_item_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source item id is required';
  END IF;

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unknown company';
  END IF;

  SELECT
    lic.catalog_type,
    lic.id_category,
    lic.item_name,
    lic.description,
    COALESCE(lic.default_unit_price_cents, 0),
    COALESCE(lic.default_amount_cents, 0),
    COALESCE(lic.show_in_folio, 1),
    COALESCE(lic.allow_negative, 0),
    COALESCE(lic.is_active, 1)
    INTO
      v_source_catalog_type,
      v_source_category_id,
      v_source_item_name,
      v_source_description,
      v_source_unit_price_cents,
      v_source_default_amount_cents,
      v_source_show_in_folio,
      v_source_allow_negative,
      v_source_is_active
  FROM line_item_catalog lic
  JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
   AND cat.deleted_at IS NULL
   AND cat.id_company = v_company_id
  WHERE lic.id_line_item_catalog = p_source_item_id
    AND lic.deleted_at IS NULL
  LIMIT 1;

  IF v_source_category_id IS NULL OR v_source_category_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'source concept not found';
  END IF;

  SET v_new_item_name = NULLIF(TRIM(p_clone_item_name), '');
  IF v_new_item_name IS NULL THEN
    SET v_new_item_name = LEFT(
      CONCAT(COALESCE(NULLIF(TRIM(v_source_item_name), ''), CONCAT('Concepto #', p_source_item_id)), ' (copia)'),
      255
    );
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
    deleted_at,
    created_at,
    created_by,
    updated_at
  ) VALUES (
    v_source_catalog_type,
    v_source_category_id,
    v_new_item_name,
    v_source_description,
    v_source_unit_price_cents,
    v_source_show_in_folio,
    v_source_allow_negative,
    v_source_default_amount_cents,
    v_source_is_active,
    NULL,
    NOW(),
    p_created_by,
    NOW()
  );

  SET v_new_item_id = LAST_INSERT_ID();

  /* Clona relaciones donde el origen era hijo (nuevo concepto conserva mismos padres). */
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
  )
  SELECT
    v_new_item_id,
    lcp.id_parent_sale_item_catalog,
    COALESCE(lcp.add_to_father_total, 1),
    lcp.show_in_folio_relation,
    lcp.percent_value,
    1,
    NULL,
    NOW(),
    p_created_by,
    NOW()
  FROM line_item_catalog_parent lcp
  JOIN line_item_catalog parent_lic
    ON parent_lic.id_line_item_catalog = lcp.id_parent_sale_item_catalog
   AND parent_lic.deleted_at IS NULL
  JOIN sale_item_category parent_cat
    ON parent_cat.id_sale_item_category = parent_lic.id_category
   AND parent_cat.deleted_at IS NULL
   AND parent_cat.id_company = v_company_id
  WHERE lcp.id_sale_item_catalog = p_source_item_id
    AND lcp.deleted_at IS NULL
    AND lcp.is_active = 1
  ON DUPLICATE KEY UPDATE
    add_to_father_total = VALUES(add_to_father_total),
    show_in_folio_relation = VALUES(show_in_folio_relation),
    percent_value = VALUES(percent_value),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  /* Clona relaciones donde el origen era padre (nuevo concepto hereda hijos). */
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
  )
  SELECT
    lcp.id_sale_item_catalog,
    v_new_item_id,
    COALESCE(lcp.add_to_father_total, 1),
    lcp.show_in_folio_relation,
    lcp.percent_value,
    1,
    NULL,
    NOW(),
    p_created_by,
    NOW()
  FROM line_item_catalog_parent lcp
  JOIN line_item_catalog child_lic
    ON child_lic.id_line_item_catalog = lcp.id_sale_item_catalog
   AND child_lic.deleted_at IS NULL
  JOIN sale_item_category child_cat
    ON child_cat.id_sale_item_category = child_lic.id_category
   AND child_cat.deleted_at IS NULL
   AND child_cat.id_company = v_company_id
  WHERE lcp.id_parent_sale_item_catalog = p_source_item_id
    AND lcp.deleted_at IS NULL
    AND lcp.is_active = 1
  ON DUPLICATE KEY UPDATE
    add_to_father_total = VALUES(add_to_father_total),
    show_in_folio_relation = VALUES(show_in_folio_relation),
    percent_value = VALUES(percent_value),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  /* Clona calculos donde el origen era hijo. */
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
  )
  SELECT
    v_new_item_id,
    licc.id_parent_line_item_catalog,
    licc.id_component_line_item_catalog,
    COALESCE(licc.is_positive, 1),
    1,
    NULL,
    NOW(),
    p_created_by,
    NOW()
  FROM line_item_catalog_calc licc
  JOIN line_item_catalog parent_lic
    ON parent_lic.id_line_item_catalog = licc.id_parent_line_item_catalog
   AND parent_lic.deleted_at IS NULL
  JOIN sale_item_category parent_cat
    ON parent_cat.id_sale_item_category = parent_lic.id_category
   AND parent_cat.deleted_at IS NULL
   AND parent_cat.id_company = v_company_id
  WHERE licc.id_line_item_catalog = p_source_item_id
    AND licc.deleted_at IS NULL
    AND licc.is_active = 1
  ON DUPLICATE KEY UPDATE
    is_positive = VALUES(is_positive),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  /* Clona calculos donde el origen era padre. */
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
  )
  SELECT
    licc.id_line_item_catalog,
    v_new_item_id,
    licc.id_component_line_item_catalog,
    COALESCE(licc.is_positive, 1),
    1,
    NULL,
    NOW(),
    p_created_by,
    NOW()
  FROM line_item_catalog_calc licc
  JOIN line_item_catalog child_lic
    ON child_lic.id_line_item_catalog = licc.id_line_item_catalog
   AND child_lic.deleted_at IS NULL
  JOIN sale_item_category child_cat
    ON child_cat.id_sale_item_category = child_lic.id_category
   AND child_cat.deleted_at IS NULL
   AND child_cat.id_company = v_company_id
  WHERE licc.id_parent_line_item_catalog = p_source_item_id
    AND licc.deleted_at IS NULL
    AND licc.is_active = 1
  ON DUPLICATE KEY UPDATE
    is_positive = VALUES(is_positive),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  SELECT
    lic.id_line_item_catalog AS id_sale_item_catalog,
    lic.catalog_type,
    lic.id_category,
    lic.item_name,
    lic.description,
    lic.default_unit_price_cents,
    lic.default_amount_cents,
    lic.show_in_folio,
    lic.allow_negative,
    lic.is_active,
    lic.created_at,
    lic.updated_at
  FROM line_item_catalog lic
  WHERE lic.id_line_item_catalog = v_new_item_id
  LIMIT 1;
END $$

DELIMITER ;
