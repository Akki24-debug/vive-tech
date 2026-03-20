DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_folio_upsert` $$
CREATE PROCEDURE `sp_folio_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_folio BIGINT,
  IN p_id_reservation BIGINT,
  IN p_folio_name VARCHAR(255),
  IN p_due_date DATE,
  IN p_bill_to_type VARCHAR(64),
  IN p_bill_to_id BIGINT,
  IN p_notes TEXT,
  IN p_currency VARCHAR(10),
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_id_res BIGINT;
  DECLARE v_currency VARCHAR(10);
  DECLARE v_id_folio BIGINT;
  DECLARE v_item_count INT DEFAULT 0;
  DECLARE v_company_code VARCHAR(100) DEFAULT NULL;
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;

  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'create';
  END IF;

  IF p_action NOT IN ('create','close','reopen','update','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unsupported action for folio';
  END IF;

  IF p_action IN ('create') THEN
    IF p_id_reservation IS NULL OR p_id_reservation = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation id is required';
    END IF;
    SELECT r.id_reservation, r.currency, c.code, p.code
      INTO v_id_res, v_currency, v_company_code, v_property_code
    FROM reservation r
    JOIN property p ON p.id_property = r.id_property
    JOIN company c ON c.id_company = p.id_company
    WHERE r.id_reservation = p_id_reservation
      AND r.deleted_at IS NULL
    LIMIT 1;
    IF v_id_res IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation not found';
    END IF;

    CALL sp_authz_assert(
      v_company_code,
      p_created_by,
      'reservations.manage_folio',
      v_property_code,
      NULL
    );

    SET v_currency = COALESCE(NULLIF(p_currency,''), v_currency, 'MXN');

    INSERT INTO folio (
      id_reservation,
      folio_name,
      status,
      currency,
      total_cents,
      balance_cents,
      due_date,
      bill_to_type,
      bill_to_id,
      notes,
      is_active,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_id_res,
      COALESCE(NULLIF(p_folio_name,''), 'Principal'),
      'open',
      v_currency,
      0,
      0,
      p_due_date,
      NULLIF(p_bill_to_type,''),
      p_bill_to_id,
      p_notes,
      1,
      NOW(),
      p_created_by,
      NOW()
    );
    SET v_id_folio = LAST_INSERT_ID();
    CALL sp_folio_recalc(v_id_folio);
    SELECT * FROM folio WHERE id_folio = v_id_folio;
    LEAVE proc;
  END IF;

  /* close / reopen / update requires a folio id */
  IF p_id_folio IS NULL OR p_id_folio = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio id is required';
  END IF;

  SELECT id_folio INTO v_id_folio
  FROM folio
  WHERE id_folio = p_id_folio
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_folio IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio not found';
  END IF;

  SELECT c.code, p.code
    INTO v_company_code, v_property_code
  FROM folio f
  JOIN reservation r
    ON r.id_reservation = f.id_reservation
  JOIN property p
    ON p.id_property = r.id_property
  JOIN company c
    ON c.id_company = p.id_company
  WHERE f.id_folio = v_id_folio
  LIMIT 1;

  CALL sp_authz_assert(
    v_company_code,
    p_created_by,
    'reservations.manage_folio',
    v_property_code,
    NULL
  );

  IF p_action = 'close' THEN
    UPDATE folio
       SET status = 'closed',
           is_active = 0,
           updated_at = NOW()
     WHERE id_folio = v_id_folio;
  ELSEIF p_action = 'reopen' THEN
    UPDATE folio
       SET status = 'open',
           is_active = 1,
           updated_at = NOW()
     WHERE id_folio = v_id_folio;
  ELSEIF p_action = 'update' THEN
    UPDATE folio
       SET folio_name = COALESCE(NULLIF(p_folio_name,''), folio_name),
           due_date = COALESCE(p_due_date, due_date),
           bill_to_type = COALESCE(NULLIF(p_bill_to_type,''), bill_to_type),
           bill_to_id = COALESCE(p_bill_to_id, bill_to_id),
           notes = COALESCE(p_notes, notes),
           updated_at = NOW()
     WHERE id_folio = v_id_folio;
  ELSEIF p_action = 'delete' THEN
    SELECT COUNT(*) INTO v_item_count
      FROM line_item si
     WHERE si.item_type = 'sale_item'
       AND si.id_folio = v_id_folio
       AND si.deleted_at IS NULL;
    IF v_item_count > 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio has charges and cannot be deleted';
    END IF;
    UPDATE folio
       SET status = 'deleted',
           is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_folio = v_id_folio;
    SELECT * FROM folio WHERE id_folio = v_id_folio;
    LEAVE proc;
  END IF;

  CALL sp_folio_recalc(v_id_folio);
  SELECT * FROM folio WHERE id_folio = v_id_folio;
END $$

DELIMITER ;
