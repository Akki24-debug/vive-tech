DELIMITER $$

DROP PROCEDURE IF EXISTS sp_create_reservation_hold $$
CREATE PROCEDURE sp_create_reservation_hold
(
  IN p_property_code VARCHAR(32),
  IN p_room_code     VARCHAR(64),
  IN p_check_in      DATE,
  IN p_check_out     DATE,
  IN p_total_cents_override INT,
  IN p_notes         TEXT,
  IN p_id_user       INT
)
proc:BEGIN
  DECLARE v_id_company    INT;
  DECLARE v_company_code  VARCHAR(100);
  DECLARE v_id_property   INT;
  DECLARE v_id_room       INT;
  DECLARE v_id_category   INT;
  DECLARE v_currency      VARCHAR(10);
  DECLARE v_base_cents    INT DEFAULT 0;
  DECLARE v_total_cents   INT DEFAULT 0;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_rate_breakdown TEXT;
  DECLARE v_use_rateplan TINYINT DEFAULT 0;
  DECLARE v_id_res        INT;
  DECLARE v_code          VARCHAR(32);
  DECLARE v_overlap_cnt   INT DEFAULT 0;
  DECLARE v_block_overlap_cnt INT DEFAULT 0;
  DECLARE v_nights        INT DEFAULT 0;
  DECLARE v_snapshot      TEXT;
  DECLARE v_breakdown     TEXT;
  DECLARE v_id_user_eff   INT;
  DECLARE v_id_reservation_source BIGINT DEFAULT NULL;
  DECLARE v_source_name VARCHAR(120) DEFAULT 'Directo';
  DECLARE v_has_total_override TINYINT DEFAULT 0;

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_room_code IS NULL OR p_room_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room code is required';
  END IF;
  IF p_check_in IS NULL OR p_check_out IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Check-in/out dates are required';
  END IF;

  SET v_nights = DATEDIFF(p_check_out, p_check_in);
  IF v_nights <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'check_out must be after check_in';
  END IF;

  SELECT p.id_property, p.id_company, p.currency, c.code
    INTO v_id_property, v_id_company, v_currency, v_company_code
  FROM property p
  JOIN company c ON c.id_company = p.id_company
  WHERE p.code = p_property_code
    AND p.deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  SELECT r.id_room, r.id_category, COALESCE(rc.default_base_price_cents,0), COALESCE(r.id_rateplan, rc.id_rateplan)
    INTO v_id_room, v_id_category, v_base_cents, v_id_rateplan
  FROM room r
  JOIN roomcategory rc ON rc.id_category = r.id_category
  WHERE r.id_property = v_id_property
    AND r.code = p_room_code
    AND r.deleted_at IS NULL
  LIMIT 1;

  IF v_id_room IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown room code for that property';
  END IF;

  IF p_total_cents_override IS NOT NULL AND p_total_cents_override >= 0 THEN
    SET v_has_total_override = 1;
  END IF;

  SET v_rate_breakdown = NULL;
  IF v_id_rateplan IS NOT NULL THEN
    CALL sp_rateplan_calc_total(
      v_id_property,
      v_id_rateplan,
      v_id_room,
      v_id_category,
      p_check_in,
      p_check_out,
      v_total_cents,
      v_base_cents,
      v_rate_breakdown
    );
    SET v_use_rateplan = 1;
  ELSE
    SET v_total_cents = v_base_cents * v_nights;
  END IF;
  IF v_has_total_override = 1 THEN
    SET v_total_cents = GREATEST(p_total_cents_override, 0);
  END IF;
  IF v_currency IS NULL OR v_currency = '' THEN
    SET v_currency = 'MXN';
  END IF;

  SELECT COUNT(*) INTO v_overlap_cnt
  FROM reservation
  WHERE id_room = v_id_room
    AND deleted_at IS NULL
    AND (is_active IS NULL OR is_active = 1)
    AND COALESCE(status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
    AND NOT (check_out_date <= p_check_in OR check_in_date >= p_check_out);

  IF v_overlap_cnt > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room not available for requested dates';
  END IF;

  SELECT COUNT(*) INTO v_block_overlap_cnt
  FROM room_block
  WHERE id_room = v_id_room
    AND deleted_at IS NULL
    AND is_active = 1
    AND start_date < p_check_out
    AND end_date > p_check_in;

  IF v_block_overlap_cnt > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room is blocked for requested dates';
  END IF;

  SET v_id_user_eff = NULL;
  IF p_id_user IS NOT NULL AND p_id_user <> 0 THEN
    SELECT id_user INTO v_id_user_eff FROM app_user WHERE id_user = p_id_user LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SELECT id_user INTO v_id_user_eff FROM app_user WHERE id_user = 3 LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SELECT id_user INTO v_id_user_eff FROM app_user WHERE is_active=1 ORDER BY id_user LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SELECT id_user INTO v_id_user_eff FROM app_user ORDER BY id_user LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'no app_user available to assign';
  END IF;

  CALL sp_authz_assert(
    v_company_code,
    v_id_user_eff,
    'calendar.create_hold',
    p_property_code,
    NULL
  );

  SELECT rsc.id_reservation_source, rsc.source_name
    INTO v_id_reservation_source, v_source_name
  FROM reservation_source_catalog rsc
  WHERE rsc.id_company = v_id_company
    AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
    AND LOWER(TRIM(COALESCE(rsc.source_name, ''))) = 'directo'
    AND rsc.deleted_at IS NULL
    AND rsc.is_active = 1
  ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END
  LIMIT 1;

  IF v_id_reservation_source IS NULL OR v_id_reservation_source <= 0 THEN
    SET v_id_reservation_source = NULL;
    SET v_source_name = 'Directo';
  END IF;

  SET v_code = CONCAT('AP-', UPPER(HEX(FLOOR(RAND()*99999999))));
  IF v_has_total_override = 1 THEN
    SET v_snapshot = CONCAT(
      '{',
        '"base_nightly_cents":', v_base_cents, ',',
        '"nights":', v_nights, ',',
        '"calc":"override_total"',
      '}'
    );
    SET v_breakdown = CONCAT(
      '[{"date_range":["',
        DATE_FORMAT(p_check_in,'%Y-%m-%d'), '","', DATE_FORMAT(p_check_out,'%Y-%m-%d'),
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
        DATE_FORMAT(p_check_in,'%Y-%m-%d'), '","', DATE_FORMAT(p_check_out,'%Y-%m-%d'),
        '"],"nightly_cents":', v_base_cents, ',"nights":', v_nights, ',"amount_cents":', v_total_cents, '}]'
    );
  END IF;

  INSERT INTO reservation (
    id_user, id_guest, id_room, id_property, id_category,
    code, status, source, id_reservation_source,
    check_in_date, check_out_date,
    adults, children, currency,
    total_price_cents, balance_due_cents,
    rate_snapshot_json, price_breakdown_json,
    notes_internal,
    is_active, created_at, updated_at
  ) VALUES (
    v_id_user_eff, NULL, v_id_room, v_id_property, v_id_category,
    v_code, 'apartado', COALESCE(v_source_name, 'Directo'), v_id_reservation_source,
    p_check_in, p_check_out,
    1, 0, v_currency,
    v_total_cents, v_total_cents,
    v_snapshot, v_breakdown,
    p_notes,
    1, NOW(), NOW()
  );

  SET v_id_res = LAST_INSERT_ID();

  SELECT
    r.*,
    rm.code AS room_code,
    rm.name AS room_name,
    rc.code AS category_code,
    rc.name AS category_name,
    pr.code AS property_code,
    pr.name AS property_name
  FROM reservation r
  JOIN room rm         ON rm.id_room     = r.id_room
  JOIN roomcategory rc ON rc.id_category = r.id_category
  JOIN property pr     ON pr.id_property = r.id_property
  WHERE r.id_reservation = v_id_res;
END $$

DELIMITER ;
