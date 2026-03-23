DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_list_room_blocks` $$
CREATE PROCEDURE `sp_list_room_blocks` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_from DATE,
  IN p_to DATE
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_from DATE;
  DECLARE v_to DATE;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property
      INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_property_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
    END IF;
  ELSE
    SET v_property_id = NULL;
  END IF;

  SET v_from = COALESCE(p_from, DATE_SUB(CURDATE(), INTERVAL 30 DAY));
  SET v_to = COALESCE(p_to, DATE_ADD(CURDATE(), INTERVAL 365 DAY));
  IF v_to < v_from THEN
    SET v_to = DATE_ADD(v_from, INTERVAL 1 DAY);
  END IF;

  SELECT
    rb.id_room_block,
    rb.code,
    rb.description,
    rb.start_date,
    rb.end_date,
    rb.is_active,
    rb.created_at,
    rb.updated_at,
    rm.id_room,
    rm.code AS room_code,
    rm.name AS room_name,
    pr.id_property,
    pr.code AS property_code,
    pr.name AS property_name,
    rb.id_user
  FROM room_block rb
  JOIN room rm ON rm.id_room = rb.id_room
  JOIN property pr ON pr.id_property = rb.id_property
  WHERE pr.id_company = v_company_id
    AND rb.deleted_at IS NULL
    AND rb.is_active = 1
    AND (v_property_id IS NULL OR rb.id_property = v_property_id)
    AND rb.start_date < v_to
    AND DATE_ADD(rb.end_date, INTERVAL 1 DAY) > v_from
  ORDER BY rb.start_date DESC, rb.id_room_block DESC;
END $$

DELIMITER ;
