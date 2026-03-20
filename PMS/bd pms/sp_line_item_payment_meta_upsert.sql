DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_line_item_payment_meta_upsert` $$
CREATE PROCEDURE `sp_line_item_payment_meta_upsert` (
  IN p_id_line_item BIGINT,
  IN p_method VARCHAR(120),
  IN p_reference VARCHAR(255),
  IN p_status VARCHAR(32),
  IN p_service_date DATE,
  IN p_updated_by BIGINT
)
BEGIN
  DECLARE v_company_code VARCHAR(100) DEFAULT NULL;
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;

  IF p_id_line_item IS NULL OR p_id_line_item <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line_item id is required';
  END IF;

  SELECT c.code, p.code
    INTO v_company_code, v_property_code
  FROM line_item li
  JOIN folio f ON f.id_folio = li.id_folio
  JOIN reservation r ON r.id_reservation = f.id_reservation
  JOIN property p ON p.id_property = r.id_property
  JOIN company c ON c.id_company = p.id_company
  WHERE li.id_line_item = p_id_line_item
    AND li.deleted_at IS NULL
  LIMIT 1;

  IF v_company_code IS NULL OR v_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment line item not found';
  END IF;

  CALL sp_authz_assert(
    v_company_code,
    p_updated_by,
    'payments.edit',
    v_property_code,
    NULL
  );

  UPDATE line_item
     SET method = CASE
                    WHEN p_method IS NULL OR TRIM(p_method) = '' THEN method
                    ELSE p_method
                  END,
         reference = CASE
                       WHEN p_reference IS NULL THEN reference
                       ELSE p_reference
                     END,
         status = CASE
                    WHEN p_status IS NULL OR TRIM(p_status) = '' THEN status
                    ELSE p_status
                  END,
         service_date = COALESCE(p_service_date, service_date),
         updated_at = NOW()
   WHERE id_line_item = p_id_line_item
     AND item_type = 'payment'
     AND deleted_at IS NULL;

  SELECT
    id_line_item,
    item_type,
    id_folio,
    id_line_item_catalog,
    item_name,
    method,
    reference,
    status,
    service_date,
    updated_at
  FROM line_item
  WHERE id_line_item = p_id_line_item;
END $$

DELIMITER ;
