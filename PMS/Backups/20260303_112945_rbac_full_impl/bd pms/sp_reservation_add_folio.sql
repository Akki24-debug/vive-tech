DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reservation_add_folio` $$
CREATE PROCEDURE `sp_reservation_add_folio` (
  IN p_company_code VARCHAR(100),
  IN p_reservation_id BIGINT,
  IN p_guest_id BIGINT,
  IN p_lodging_catalog_id BIGINT,
  IN p_total_cents_override INT,
  IN p_adults INT,
  IN p_children INT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_id_room BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_check_in DATE;
  DECLARE v_check_out DATE;
  DECLARE v_nights INT DEFAULT 0;
  DECLARE v_base_cents INT DEFAULT 0;
  DECLARE v_total_cents INT DEFAULT 0;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_rate_breakdown TEXT;
  DECLARE v_use_rateplan TINYINT DEFAULT 0;
  DECLARE v_currency VARCHAR(10);
  DECLARE v_folio_id BIGINT;
  DECLARE v_code VARCHAR(32);
  DECLARE v_lodging_catalog_id BIGINT;
  DECLARE v_unit_price INT DEFAULT 0;
  DECLARE v_discount INT DEFAULT 0;
  DECLARE v_existing_folios INT DEFAULT 0;
  DECLARE v_snapshot TEXT;
  DECLARE v_breakdown TEXT;
  DECLARE v_status VARCHAR(32);
  DECLARE v_in_tx TINYINT DEFAULT 0;
  DECLARE v_current_ota_account_id BIGINT DEFAULT NULL;
  DECLARE v_current_reservation_source_id BIGINT DEFAULT NULL;
  DECLARE v_detected_ota_account_id BIGINT DEFAULT NULL;
  DECLARE v_detected_ota_platform VARCHAR(32);
  DECLARE v_default_source_id BIGINT DEFAULT NULL;
  DECLARE v_default_source_name VARCHAR(120) DEFAULT 'Directo';

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    IF v_in_tx = 1 THEN
      ROLLBACK;
      SET v_in_tx = 0;
    END IF;
    RESIGNAL;
  END;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_reservation_id IS NULL OR p_reservation_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation id is required';
  END IF;
  IF p_guest_id IS NULL OR p_guest_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Guest id is required';
  END IF;
  IF p_lodging_catalog_id IS NULL OR p_lodging_catalog_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lodging catalog is required';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM guest WHERE id_guest = p_guest_id LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Guest not found';
  END IF;

  SELECT
    r.status,
    r.id_property,
    r.id_room,
    r.id_category,
    r.check_in_date,
    r.check_out_date,
    r.currency,
    r.code,
    r.id_ota_account,
    r.id_reservation_source
    INTO
      v_status,
      v_id_property,
      v_id_room,
      v_id_category,
      v_check_in,
      v_check_out,
      v_currency,
      v_code,
      v_current_ota_account_id,
      v_current_reservation_source_id
  FROM reservation r
  JOIN property p ON p.id_property = r.id_property
  WHERE r.id_reservation = p_reservation_id
    AND r.deleted_at IS NULL
    AND p.id_company = v_company_id
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation not found';
  END IF;
  IF v_status NOT IN ('confirmado','encasa','en casa') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation is not confirmed';
  END IF;

  SELECT COUNT(*) INTO v_existing_folios
  FROM folio
  WHERE id_reservation = p_reservation_id
    AND deleted_at IS NULL;

  IF v_existing_folios > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation already has folios';
  END IF;

  SELECT pslc.id_sale_item_catalog
    INTO v_lodging_catalog_id
  FROM pms_settings_lodging_catalog pslc
  JOIN line_item_catalog sic
    ON sic.id_line_item_catalog = pslc.id_sale_item_catalog
   AND sic.catalog_type = 'sale_item'
  JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
  WHERE pslc.id_company = v_company_id
    AND (pslc.id_property = v_id_property OR pslc.id_property IS NULL)
    AND pslc.id_sale_item_catalog = p_lodging_catalog_id
    AND pslc.deleted_at IS NULL
    AND pslc.is_active = 1
    AND sic.deleted_at IS NULL
    AND sic.is_active = 1
    AND cat.deleted_at IS NULL
    AND cat.is_active = 1
    AND cat.id_company = v_company_id
  LIMIT 1;

  IF v_lodging_catalog_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lodging catalog not allowed for property';
  END IF;

  SELECT COALESCE(rc.default_base_price_cents,0), COALESCE(rm.id_rateplan, rc.id_rateplan)
    INTO v_base_cents, v_id_rateplan
  FROM room rm
  JOIN roomcategory rc ON rc.id_category = rm.id_category
  WHERE rm.id_room = v_id_room
  LIMIT 1;

  SET v_nights = DATEDIFF(v_check_out, v_check_in);
  IF v_nights <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'check_out must be after check_in';
  END IF;

  SET v_rate_breakdown = NULL;
  IF v_id_rateplan IS NOT NULL THEN
    CALL sp_rateplan_calc_total(
      v_id_property,
      v_id_rateplan,
      v_id_room,
      v_id_category,
      v_check_in,
      v_check_out,
      v_total_cents,
      v_base_cents,
      v_rate_breakdown
    );
    SET v_use_rateplan = 1;
  ELSE
    SET v_total_cents = v_base_cents * v_nights;
  END IF;
  IF p_total_cents_override IS NOT NULL AND p_total_cents_override > 0 THEN
    SET v_total_cents = p_total_cents_override;
  END IF;

  IF v_currency IS NULL OR v_currency = '' THEN
    SET v_currency = 'MXN';
  END IF;

  START TRANSACTION;
  SET v_in_tx = 1;

  IF p_total_cents_override IS NOT NULL AND p_total_cents_override > 0 THEN
    SET v_snapshot = CONCAT(
      '{',
        '"base_nightly_cents":', v_base_cents, ',',
        '"nights":', v_nights, ',',
        '"calc":"override_total"',
      '}'
    );
    SET v_breakdown = CONCAT(
      '[{"date_range":["',
        DATE_FORMAT(v_check_in,'%Y-%m-%d'), '","', DATE_FORMAT(v_check_out,'%Y-%m-%d'),
        '"],"nightly_cents":', v_base_cents, ',"nights":', v_nights, ',"amount_cents":', v_total_cents, '}]'
    );
  ELSEIF v_use_rateplan = 1 AND v_rate_breakdown IS NOT NULL AND v_rate_breakdown <> '' THEN
    SET v_snapshot = CONCAT(
      '{',
        '"base_nightly_cents":', v_base_cents, ',',
        '"nights":', v_nights, ',',
        '"calc":"rateplan",',
        '"rateplan_id":', v_id_rateplan,
      '}'
    );
    SET v_breakdown = v_rate_breakdown;
  ELSE
    SET v_snapshot = CONCAT(
      '{',
        '"base_nightly_cents":', v_base_cents, ',',
        '"nights":', v_nights, ',',
        '"calc":"default_base_price_cents"',
      '}'
    );
    SET v_breakdown = CONCAT(
      '[{"date_range":["',
        DATE_FORMAT(v_check_in,'%Y-%m-%d'), '","', DATE_FORMAT(v_check_out,'%Y-%m-%d'),
        '"],"nightly_cents":', v_base_cents, ',"nights":', v_nights, ',"amount_cents":', v_total_cents, '}]'
    );
  END IF;

  UPDATE reservation
     SET id_guest = p_guest_id,
         adults = COALESCE(p_adults, adults),
         children = COALESCE(p_children, children),
         total_price_cents = v_total_cents,
         balance_due_cents = v_total_cents,
         rate_snapshot_json = v_snapshot,
         price_breakdown_json = v_breakdown,
         updated_at = NOW()
   WHERE id_reservation = p_reservation_id;

  INSERT INTO folio (
    id_reservation,
    folio_name,
    status,
    currency,
    total_cents,
    balance_cents,
    due_date,
    is_active,
    created_at,
    created_by,
    updated_at
  ) VALUES (
    p_reservation_id,
    'Principal',
    'open',
    v_currency,
    0,
    0,
    v_check_out,
    1,
    NOW(),
    p_actor_user_id,
    NOW()
  );
  SET v_folio_id = LAST_INSERT_ID();

  SET v_unit_price = CASE
    WHEN (p_total_cents_override IS NOT NULL AND p_total_cents_override > 0) OR v_use_rateplan = 1
      THEN CEILING(v_total_cents / v_nights)
    ELSE v_base_cents
  END;
  SET v_discount = CASE
    WHEN (p_total_cents_override IS NOT NULL AND p_total_cents_override > 0) OR v_use_rateplan = 1
      THEN (v_unit_price * v_nights) - v_total_cents
    ELSE 0
  END;

  CALL sp_sale_item_upsert(
    'create',
    0,
    v_folio_id,
    p_reservation_id,
    v_lodging_catalog_id,
    CONCAT('Reserva ', v_code),
    v_check_in,
    v_nights,
    v_unit_price,
    v_discount,
    'posted',
    p_actor_user_id
  );

  CALL sp_line_item_percent_derived_upsert(
    v_folio_id,
    p_reservation_id,
    v_lodging_catalog_id,
    v_check_in,
    p_actor_user_id
  );

  SET v_detected_ota_account_id = v_current_ota_account_id;
  SET v_detected_ota_platform = NULL;

  IF COALESCE(v_detected_ota_account_id, 0) > 0 THEN
    SELECT oa.platform
      INTO v_detected_ota_platform
    FROM ota_account oa
    WHERE oa.id_ota_account = v_detected_ota_account_id
      AND oa.id_company = v_company_id
      AND oa.deleted_at IS NULL
    LIMIT 1;
  END IF;

  IF COALESCE(v_detected_ota_account_id, 0) <= 0 THEN
    SELECT oa.id_ota_account, oa.platform
      INTO v_detected_ota_account_id, v_detected_ota_platform
    FROM ota_account_lodging_catalog oalc
    JOIN ota_account oa
      ON oa.id_ota_account = oalc.id_ota_account
     AND oa.id_company = v_company_id
     AND oa.deleted_at IS NULL
     AND oa.is_active = 1
    WHERE oalc.id_line_item_catalog = v_lodging_catalog_id
      AND oalc.deleted_at IS NULL
      AND oalc.is_active = 1
    ORDER BY oalc.sort_order, oa.id_ota_account
    LIMIT 1;
  END IF;

  IF COALESCE(v_detected_ota_account_id, 0) > 0 THEN
    UPDATE reservation
       SET id_ota_account = v_detected_ota_account_id,
           id_reservation_source = NULL,
           source = CASE LOWER(TRIM(COALESCE(v_detected_ota_platform, '')))
             WHEN 'booking' THEN 'Booking'
             WHEN 'airbnb' THEN 'Airbnb'
             WHEN 'abb' THEN 'Airbnb'
             WHEN 'expedia' THEN 'Expedia'
             ELSE 'OTA'
           END,
           updated_at = NOW()
     WHERE id_reservation = p_reservation_id;
  ELSE
    SELECT rsc.id_reservation_source, rsc.source_name
      INTO v_default_source_id, v_default_source_name
    FROM reservation_source_catalog rsc
    WHERE rsc.id_company = v_company_id
      AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
      AND LOWER(TRIM(COALESCE(rsc.source_name, ''))) = 'directo'
      AND rsc.deleted_at IS NULL
      AND rsc.is_active = 1
    ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END
    LIMIT 1;

    IF v_default_source_id IS NULL OR v_default_source_id <= 0 THEN
      SET v_default_source_id = NULL;
      SET v_default_source_name = 'Directo';
    END IF;

    UPDATE reservation
       SET id_ota_account = NULL,
           id_reservation_source = COALESCE(id_reservation_source, v_default_source_id),
           source = COALESCE(NULLIF(TRIM(v_default_source_name), ''), source, 'Directo'),
           updated_at = NOW()
     WHERE id_reservation = p_reservation_id;
  END IF;

  COMMIT;
  SET v_in_tx = 0;

  SELECT
    r.*,
    g.email       AS guest_email,
    g.names       AS guest_names,
    g.last_name   AS guest_last_name,
    g.phone       AS guest_phone,
    rm.code       AS room_code,
    rm.name       AS room_name,
    rc.code       AS category_code,
    rc.name       AS category_name,
    pr.code       AS property_code,
    pr.name       AS property_name
  FROM reservation r
  JOIN property pr ON pr.id_property = r.id_property
  JOIN room rm ON rm.id_room = r.id_room
  JOIN roomcategory rc ON rc.id_category = r.id_category
  LEFT JOIN guest g ON g.id_guest = r.id_guest
  WHERE r.id_reservation = p_reservation_id
  LIMIT 1;
END $$

DELIMITER ;
