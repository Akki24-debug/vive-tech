DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_refund_upsert` $$
CREATE PROCEDURE `sp_refund_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_refund BIGINT,
  IN p_id_payment BIGINT,
  IN p_amount_cents INT,
  IN p_reason TEXT,
  IN p_reference VARCHAR(255),
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_id_payment BIGINT;
  DECLARE v_id_folio BIGINT;
  DECLARE v_currency VARCHAR(10);
  DECLARE v_available INT;
  DECLARE v_company_code VARCHAR(100) DEFAULT NULL;
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;

  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'create';
  END IF;

  IF p_action NOT IN ('create','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unsupported action for refund';
  END IF;

  IF p_action = 'create' THEN
    IF p_id_payment IS NULL OR p_id_payment = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment id is required';
    END IF;
    SELECT li.id_line_item,
           li.id_folio,
           li.currency,
           li.amount_cents - li.refunded_total_cents,
           c.code,
           p.code
      INTO v_id_payment,
           v_id_folio,
           v_currency,
           v_available,
           v_company_code,
           v_property_code
    FROM line_item li
    JOIN folio f ON f.id_folio = li.id_folio
    JOIN reservation rs ON rs.id_reservation = f.id_reservation
    JOIN property p ON p.id_property = rs.id_property
    JOIN company c ON c.id_company = p.id_company
    WHERE li.id_line_item = p_id_payment
      AND li.item_type = 'payment'
      AND li.deleted_at IS NULL
    LIMIT 1;
    IF v_id_payment IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment not found';
    END IF;
    IF p_amount_cents IS NULL OR p_amount_cents <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refund amount must be positive';
    END IF;
    IF p_amount_cents > v_available THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refund exceeds remaining amount';
    END IF;

    CALL sp_authz_assert(
      v_company_code,
      p_created_by,
      'reservations.refund',
      v_property_code,
      NULL
    );

    INSERT INTO refund (
      id_payment,
      id_user,
      amount_cents,
      currency,
      reference,
      reason,
      refunded_at,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_id_payment,
      p_created_by,
      p_amount_cents,
      v_currency,
      NULLIF(p_reference,''),
      p_reason,
      NOW(),
      1,
      NULL,
      NOW(),
      p_created_by,
      NOW()
    );
    SET p_id_refund = LAST_INSERT_ID();

    UPDATE line_item
       SET refunded_total_cents = refunded_total_cents + p_amount_cents,
           updated_at = NOW()
     WHERE id_line_item = v_id_payment
       AND item_type = 'payment';

    CALL sp_folio_recalc(v_id_folio);
    SELECT * FROM refund WHERE id_refund = p_id_refund;
    LEAVE proc;
  END IF;

  /* delete refund */
  IF p_action = 'delete' THEN
    IF p_id_refund IS NULL OR p_id_refund = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refund id is required';
    END IF;
    SELECT r.id_payment, p.id_folio, c.code, pr.code
      INTO v_id_payment, v_id_folio, v_company_code, v_property_code
    FROM refund r
    JOIN line_item p
      ON p.id_line_item = r.id_payment
     AND p.item_type = 'payment'
    JOIN folio f
      ON f.id_folio = p.id_folio
    JOIN reservation rs
      ON rs.id_reservation = f.id_reservation
    JOIN property pr
      ON pr.id_property = rs.id_property
    JOIN company c
      ON c.id_company = pr.id_company
    WHERE r.id_refund = p_id_refund
    LIMIT 1;

    CALL sp_authz_assert(
      v_company_code,
      p_created_by,
      'reservations.refund',
      v_property_code,
      NULL
    );
    UPDATE refund
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_refund = p_id_refund;
    IF v_id_payment IS NOT NULL THEN
      UPDATE line_item
         SET refunded_total_cents = GREATEST(refunded_total_cents - COALESCE(p_amount_cents,0), 0),
             updated_at = NOW()
       WHERE id_line_item = v_id_payment
         AND item_type = 'payment';
    END IF;
    IF v_id_folio IS NOT NULL THEN
      CALL sp_folio_recalc(v_id_folio);
    END IF;
    SELECT * FROM refund WHERE id_refund = p_id_refund;
  END IF;
END $$

DELIMITER ;
