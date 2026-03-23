DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_obligation_paid_upsert` $$
CREATE PROCEDURE `sp_obligation_paid_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_id_line_item BIGINT,
  IN p_mode VARCHAR(16),
  IN p_amount_cents INT,
  IN p_id_obligation_payment_method BIGINT,
  IN p_payment_notes TEXT,
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_line_company_id BIGINT;
  DECLARE v_id_folio BIGINT;
  DECLARE v_id_reservation BIGINT;
  DECLARE v_amount_cents INT DEFAULT 0;
  DECLARE v_paid_cents INT DEFAULT 0;
  DECLARE v_new_paid_cents INT DEFAULT 0;
  DECLARE v_applied_cents INT DEFAULT 0;
  DECLARE v_mode VARCHAR(16);
  DECLARE v_payment_method_id BIGINT DEFAULT 0;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'company code is required';
  END IF;
  IF p_id_line_item IS NULL OR p_id_line_item <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line item id is required';
  END IF;
  IF p_id_obligation_payment_method IS NULL OR p_id_obligation_payment_method <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'obligation payment method is required';
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
    p.id_company,
    li.id_folio,
    f.id_reservation,
    COALESCE(li.amount_cents, 0),
    COALESCE(li.paid_cents, 0)
    INTO v_line_company_id, v_id_folio, v_id_reservation, v_amount_cents, v_paid_cents
  FROM line_item li
  JOIN folio f
    ON f.id_folio = li.id_folio
   AND f.deleted_at IS NULL
   AND f.is_active = 1
  JOIN reservation r
    ON r.id_reservation = f.id_reservation
   AND r.deleted_at IS NULL
  JOIN property p
    ON p.id_property = r.id_property
   AND p.deleted_at IS NULL
  WHERE li.id_line_item = p_id_line_item
    AND li.item_type = 'obligation'
    AND li.deleted_at IS NULL
    AND li.is_active = 1
  LIMIT 1;

  IF v_id_folio IS NULL OR v_id_folio <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'obligation line item not found';
  END IF;

  IF v_line_company_id <> v_company_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line item does not belong to company';
  END IF;

  SELECT m.id_obligation_payment_method
    INTO v_payment_method_id
  FROM pms_settings_obligation_payment_method m
  WHERE m.id_obligation_payment_method = p_id_obligation_payment_method
    AND m.id_company = v_company_id
    AND m.deleted_at IS NULL
    AND m.is_active = 1
  LIMIT 1;

  IF v_payment_method_id IS NULL OR v_payment_method_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid obligation payment method';
  END IF;

  SET v_mode = LOWER(TRIM(COALESCE(p_mode, 'add')));
  IF v_mode NOT IN ('add', 'set') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid mode';
  END IF;

  SET p_amount_cents = COALESCE(p_amount_cents, 0);
  IF p_amount_cents < 0 THEN
    SET p_amount_cents = 0;
  END IF;

  IF v_mode = 'set' THEN
    SET v_new_paid_cents = p_amount_cents;
  ELSE
    SET v_new_paid_cents = COALESCE(v_paid_cents, 0) + p_amount_cents;
  END IF;

  IF v_new_paid_cents < 0 THEN
    SET v_new_paid_cents = 0;
  END IF;
  IF v_amount_cents >= 0 THEN
    SET v_new_paid_cents = LEAST(v_new_paid_cents, v_amount_cents);
  END IF;
  SET v_applied_cents = v_new_paid_cents - COALESCE(v_paid_cents, 0);

  UPDATE line_item
     SET paid_cents = v_new_paid_cents,
         updated_at = NOW()
   WHERE id_line_item = p_id_line_item
     AND deleted_at IS NULL
     AND is_active = 1;

  IF v_applied_cents <> 0 THEN
    INSERT INTO obligation_payment_log (
      id_company,
      id_line_item,
      id_folio,
      id_reservation,
      id_obligation_payment_method,
      payment_mode,
      amount_input_cents,
      amount_applied_cents,
      paid_before_cents,
      paid_after_cents,
      notes,
      created_by
    ) VALUES (
      v_company_id,
      p_id_line_item,
      v_id_folio,
      v_id_reservation,
      v_payment_method_id,
      v_mode,
      COALESCE(p_amount_cents, 0),
      v_applied_cents,
      COALESCE(v_paid_cents, 0),
      v_new_paid_cents,
      NULLIF(TRIM(COALESCE(p_payment_notes, '')), ''),
      p_created_by
    );
  END IF;

  CALL sp_folio_recalc(v_id_folio);

  SELECT
    li.id_line_item,
    li.id_folio,
    li.id_line_item_catalog,
    li.amount_cents,
    li.paid_cents,
    GREATEST(COALESCE(li.amount_cents, 0) - COALESCE(li.paid_cents, 0), 0) AS remaining_cents,
    CASE
      WHEN COALESCE(li.amount_cents, 0) <= 0 THEN 'paid'
      WHEN COALESCE(li.paid_cents, 0) <= 0 THEN 'pending'
      WHEN COALESCE(li.paid_cents, 0) >= COALESCE(li.amount_cents, 0) THEN 'paid'
      ELSE 'partial'
    END AS payment_status,
    li.updated_at
  FROM line_item li
  WHERE li.id_line_item = p_id_line_item
  LIMIT 1;
END $$

DELIMITER ;
