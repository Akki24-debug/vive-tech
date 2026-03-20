DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reservation_update` $$
CREATE PROCEDURE `sp_reservation_update` (
  IN p_company_code     VARCHAR(100),
  IN p_reservation_id   BIGINT,
  IN p_status           VARCHAR(32),
  IN p_source           VARCHAR(120),
  IN p_id_ota_account   BIGINT,
  IN p_room_code        VARCHAR(64),
  IN p_check_in_date    DATE,
  IN p_check_out_date   DATE,
  IN p_adults           INT,
  IN p_children         INT,
  IN p_notes_internal   TEXT,
  IN p_notes_guest      TEXT,
  IN p_actor_user_id    BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_current_room BIGINT;
  DECLARE v_new_room BIGINT;
  DECLARE v_room_candidate BIGINT;
  DECLARE v_room_code_requested VARCHAR(64);
  DECLARE v_new_category BIGINT;
  DECLARE v_overlap_cnt INT DEFAULT 0;
  DECLARE v_block_overlap_cnt INT DEFAULT 0;
  DECLARE v_check_in DATE;
  DECLARE v_check_out DATE;
  DECLARE v_status_input VARCHAR(32);
  DECLARE v_status_normalized VARCHAR(32);
  DECLARE v_skip_overlap TINYINT DEFAULT 0;
  DECLARE v_source_input VARCHAR(120);
  DECLARE v_source_name VARCHAR(120);
  DECLARE v_id_ota_account BIGINT DEFAULT NULL;
  DECLARE v_id_reservation_source BIGINT DEFAULT NULL;
  DECLARE v_ota_platform VARCHAR(32);
  DECLARE v_has_ota_update TINYINT DEFAULT 0;
  DECLARE v_has_source_update TINYINT DEFAULT 0;
  DECLARE v_current_source VARCHAR(120);
  DECLARE v_current_id_ota_account BIGINT DEFAULT NULL;
  DECLARE v_current_id_reservation_source BIGINT DEFAULT NULL;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_reservation_id IS NULL OR p_reservation_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation id is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SELECT
    r.id_property,
    r.id_room,
    r.source,
    r.id_ota_account,
    r.id_reservation_source
    INTO
      v_id_property,
      v_current_room,
      v_current_source,
      v_current_id_ota_account,
      v_current_id_reservation_source
  FROM reservation r
  WHERE r.id_reservation = p_reservation_id
    AND r.deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation not found';
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM property
    WHERE id_property = v_id_property
      AND id_company = v_company_id
      AND deleted_at IS NULL
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation does not belong to company';
  END IF;

  SET v_check_in = COALESCE(p_check_in_date, (
    SELECT check_in_date FROM reservation WHERE id_reservation = p_reservation_id
  ));
  SET v_check_out = COALESCE(p_check_out_date, (
    SELECT check_out_date FROM reservation WHERE id_reservation = p_reservation_id
  ));

  IF v_check_out <= v_check_in THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Check-out must be after check-in';
  END IF;

  SET v_status_input = NULLIF(TRIM(p_status), '');
  SET v_status_normalized = LOWER(COALESCE(v_status_input, ''));
  IF v_status_normalized IN ('cancelled','canceled','cancelado','cancelada') THEN
    SET v_status_input = 'cancelada';
    SET v_skip_overlap = 1;
  END IF;

  SET v_source_input = NULLIF(TRIM(COALESCE(p_source, '')), '');
  SET v_source_name = NULL;
  SET v_has_source_update = 0;
  SET v_id_reservation_source = v_current_id_reservation_source;
  IF p_source IS NOT NULL THEN
    SET v_has_source_update = 1;
  END IF;

  SET v_has_ota_update = 0;
  SET v_id_ota_account = NULL;
  SET v_ota_platform = NULL;
  IF p_id_ota_account IS NOT NULL THEN
    SET v_has_ota_update = 1;
    IF p_id_ota_account > 0 THEN
      SELECT oa.id_ota_account, oa.platform
        INTO v_id_ota_account, v_ota_platform
      FROM ota_account oa
      WHERE oa.id_ota_account = p_id_ota_account
        AND oa.id_company = v_company_id
        AND oa.deleted_at IS NULL
        AND oa.is_active = 1
      LIMIT 1;

      IF v_id_ota_account IS NULL OR v_id_ota_account <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid ota account for company';
      END IF;

      SET v_ota_platform = LOWER(TRIM(COALESCE(v_ota_platform, 'other')));
      SET v_source_name = CASE
        WHEN v_ota_platform = 'booking' THEN 'Booking'
        WHEN v_ota_platform = 'airbnb' OR v_ota_platform = 'abb' THEN 'Airbnb'
        WHEN v_ota_platform = 'expedia' THEN 'Expedia'
        ELSE 'OTA'
      END;
      SET v_has_source_update = 1;
      SET v_id_reservation_source = NULL;
    ELSE
      SET v_id_ota_account = NULL;
      SET v_has_source_update = 1;
    END IF;
  END IF;

  IF v_has_source_update = 1 AND COALESCE(v_id_ota_account, 0) <= 0 THEN
    IF v_source_input IS NOT NULL AND v_source_input REGEXP '^[0-9]+$' THEN
      SELECT rsc.id_reservation_source, rsc.source_name
        INTO v_id_reservation_source, v_source_name
      FROM reservation_source_catalog rsc
      WHERE rsc.id_reservation_source = CAST(v_source_input AS UNSIGNED)
        AND rsc.id_company = v_company_id
        AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
        AND rsc.deleted_at IS NULL
        AND rsc.is_active = 1
      ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END
      LIMIT 1;
    ELSE
      SET v_source_name = CASE LOWER(TRIM(COALESCE(v_source_input, '')))
        WHEN 'booking' THEN 'Booking'
        WHEN 'airbnb' THEN 'Airbnb'
        WHEN 'abb' THEN 'Airbnb'
        WHEN 'expedia' THEN 'Expedia'
        WHEN 'otro' THEN 'Directo'
        ELSE NULLIF(TRIM(COALESCE(v_source_input, '')), '')
      END;
      IF v_source_name IS NOT NULL THEN
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source, v_source_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_company = v_company_id
          AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
          AND TRIM(COALESCE(rsc.source_name, '')) COLLATE utf8mb4_unicode_ci
              = TRIM(v_source_name) COLLATE utf8mb4_unicode_ci
          AND rsc.deleted_at IS NULL
          AND rsc.is_active = 1
        ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END
        LIMIT 1;
      END IF;
    END IF;

    IF v_id_reservation_source IS NULL OR v_id_reservation_source <= 0 THEN
      IF v_source_input IS NOT NULL
         AND TRIM(v_source_input) <> ''
         AND LOWER(TRIM(v_source_input)) NOT IN ('directo','otro') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown reservation source. Create it first in Settings';
      END IF;

      SELECT rsc.id_reservation_source, rsc.source_name
        INTO v_id_reservation_source, v_source_name
      FROM reservation_source_catalog rsc
      WHERE rsc.id_company = v_company_id
        AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
        AND LOWER(TRIM(COALESCE(rsc.source_name, ''))) = 'directo'
        AND rsc.deleted_at IS NULL
        AND rsc.is_active = 1
      ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END
      LIMIT 1;

      IF v_id_reservation_source IS NULL OR v_id_reservation_source <= 0 THEN
        SET v_id_reservation_source = v_current_id_reservation_source;
        SET v_source_name = COALESCE(NULLIF(TRIM(v_current_source), ''), 'Directo');
        IF v_source_input IS NOT NULL
           AND TRIM(v_source_input) <> ''
           AND LOWER(TRIM(v_source_input)) IN ('directo','otro') THEN
          SET v_source_name = 'Directo';
        END IF;
      END IF;
    END IF;
  END IF;

  SET v_room_code_requested = NULLIF(TRIM(p_room_code), '');
  SET v_new_room = NULL;
  SET v_new_category = NULL;

  IF v_room_code_requested IS NOT NULL THEN
    SET v_room_code_requested = UPPER(v_room_code_requested);
    SELECT rm.id_room, rm.id_category
      INTO v_new_room, v_new_category
    FROM room rm
    WHERE rm.code = v_room_code_requested
      AND rm.id_property = v_id_property
      AND rm.deleted_at IS NULL
    LIMIT 1;

    IF v_new_room IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown room code for that property';
    END IF;
  END IF;

  SET v_room_candidate = COALESCE(v_new_room, v_current_room);

  IF v_room_candidate IS NOT NULL AND v_skip_overlap = 0 THEN
  SELECT COUNT(*) INTO v_overlap_cnt
  FROM reservation
  WHERE id_room = v_room_candidate
    AND id_reservation <> p_reservation_id
    AND (deleted_at IS NULL)
    AND (is_active IS NULL OR is_active = 1)
    AND COALESCE(status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
    AND NOT (check_out_date <= v_check_in OR check_in_date >= v_check_out);

    IF v_overlap_cnt > 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room not available for requested dates';
    END IF;

    SELECT COUNT(*) INTO v_block_overlap_cnt
    FROM room_block
    WHERE id_room = v_room_candidate
      AND deleted_at IS NULL
      AND is_active = 1
      AND start_date < v_check_out
      AND end_date > v_check_in;

    IF v_block_overlap_cnt > 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room is blocked for requested dates';
    END IF;
  END IF;

  UPDATE reservation
  SET
    status = COALESCE(v_status_input, status),
    source = CASE
      WHEN v_has_source_update = 1 THEN COALESCE(v_source_name, source)
      ELSE source
    END,
    id_ota_account = CASE
      WHEN v_has_ota_update = 1 THEN v_id_ota_account
      WHEN v_has_source_update = 1 AND COALESCE(v_id_reservation_source, 0) > 0 THEN NULL
      ELSE id_ota_account
    END,
    id_reservation_source = CASE
      WHEN v_has_ota_update = 1 AND COALESCE(v_id_ota_account, 0) > 0 THEN NULL
      WHEN v_has_source_update = 1 THEN v_id_reservation_source
      ELSE id_reservation_source
    END,
    id_room = COALESCE(v_room_candidate, id_room),
    id_category = COALESCE(v_new_category, id_category),
    check_in_date = v_check_in,
    check_out_date = v_check_out,
    adults = COALESCE(p_adults, adults),
    children = COALESCE(p_children, children),
    notes_internal = COALESCE(NULLIF(p_notes_internal, ''), notes_internal),
    notes_guest = COALESCE(NULLIF(p_notes_guest, ''), notes_guest),
    updated_at = NOW()
  WHERE id_reservation = p_reservation_id;

  IF v_status_input = 'cancelada' THEN
    DROP TEMPORARY TABLE IF EXISTS tmp_cancel_folios;
    CREATE TEMPORARY TABLE tmp_cancel_folios (
      id_folio BIGINT PRIMARY KEY
    );
    INSERT IGNORE INTO tmp_cancel_folios (id_folio)
    SELECT id_folio
    FROM folio
    WHERE id_reservation = p_reservation_id
      AND deleted_at IS NULL;

    DROP TEMPORARY TABLE IF EXISTS tmp_cancel_sale_items;
    CREATE TEMPORARY TABLE tmp_cancel_sale_items (
      id_sale_item BIGINT PRIMARY KEY
    );
    INSERT IGNORE INTO tmp_cancel_sale_items (id_sale_item)
    SELECT id_line_item
    FROM line_item
    WHERE item_type = 'sale_item'
      AND id_folio IN (SELECT id_folio FROM tmp_cancel_folios)
      AND deleted_at IS NULL;

    /* Anular folios */
    UPDATE folio
       SET is_active = 0,
           status = 'void',
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_folio IN (SELECT id_folio FROM tmp_cancel_folios)
       AND deleted_at IS NULL;

    /* Desactivar cargos, pagos y conceptos relacionados del folio */
    UPDATE line_item
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_folio IN (SELECT id_folio FROM tmp_cancel_folios)
       AND item_type IN ('sale_item','tax_item','payment','obligation','income')
       AND deleted_at IS NULL;
  END IF;

  SELECT
    r.id_reservation,
    r.code AS reservation_code,
    r.status,
    r.check_in_date,
    r.check_out_date,
    r.adults,
    r.children,
    r.notes_internal,
    r.notes_guest
  FROM reservation r
  WHERE r.id_reservation = p_reservation_id
    AND r.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
