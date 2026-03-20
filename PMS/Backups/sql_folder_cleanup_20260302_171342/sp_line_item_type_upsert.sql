DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_line_item_type_upsert` $$
CREATE PROCEDURE `sp_line_item_type_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_line_item BIGINT,
  IN p_company_code VARCHAR(100),
  IN p_item_type VARCHAR(32),
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_id_folio BIGINT;
  DECLARE v_catalog_type VARCHAR(32);
  DECLARE v_expected_catalog_type VARCHAR(32);

  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'update';
  END IF;
  IF p_action <> 'update' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'company code is required';
  END IF;
  IF p_id_line_item IS NULL OR p_id_line_item <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line item id is required';
  END IF;
  IF p_item_type NOT IN ('sale_item','tax_item','payment','obligation','income') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid line item type';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;
  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT li.id_folio, lic.catalog_type
    INTO v_id_folio, v_catalog_type
  FROM line_item li
  JOIN folio f ON f.id_folio = li.id_folio
  JOIN reservation r ON r.id_reservation = f.id_reservation
  JOIN property p ON p.id_property = r.id_property
  LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = li.id_line_item_catalog
  WHERE li.id_line_item = p_id_line_item
    AND li.deleted_at IS NULL
    AND p.id_company = v_company_id
  LIMIT 1;

  IF v_id_folio IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line item not found';
  END IF;

  SET v_expected_catalog_type = CASE p_item_type
    WHEN 'sale_item' THEN 'sale_item'
    WHEN 'tax_item' THEN 'tax_rule'
    WHEN 'payment' THEN 'payment'
    WHEN 'obligation' THEN 'obligation'
    WHEN 'income' THEN 'income'
    ELSE ''
  END;

  IF v_catalog_type IS NOT NULL AND v_catalog_type <> '' AND v_expected_catalog_type <> '' AND v_catalog_type <> v_expected_catalog_type THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line item type does not match catalog type';
  END IF;

  UPDATE line_item
     SET item_type = p_item_type,
         updated_at = NOW()
   WHERE id_line_item = p_id_line_item
     AND deleted_at IS NULL;

  CALL sp_folio_recalc(v_id_folio);

  SELECT
    id_line_item,
    id_folio,
    item_type,
    updated_at
  FROM line_item
  WHERE id_line_item = p_id_line_item;
END $$

DELIMITER ;

