DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_property_room_calendar` $$
CREATE PROCEDURE `sp_property_room_calendar` (
  IN p_property_code VARCHAR(100),
  IN p_from DATE,
  IN p_days INT
)
BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_to DATE;
  DECLARE v_day_count INT;
  DECLARE v_index INT DEFAULT 0;
  DECLARE v_total_rooms INT DEFAULT 0;
  DECLARE v_has_category_calendar_display TINYINT DEFAULT 0;

  SET v_day_count = CASE
    WHEN p_days IS NULL OR p_days < 1 THEN 1
    WHEN p_days > 120 THEN 120
    ELSE p_days
  END;

  SELECT id_property
    INTO v_id_property
  FROM property
  WHERE code = p_property_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  SET v_to = DATE_ADD(p_from, INTERVAL v_day_count DAY);
  SELECT CASE WHEN EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'category_calendar_amenity_display'
  ) THEN 1 ELSE 0 END
    INTO v_has_category_calendar_display;

  SELECT COUNT(*)
    INTO v_total_rooms
  FROM room
  WHERE id_property = v_id_property
    AND is_active = 1
    AND deleted_at IS NULL;

  DROP TEMPORARY TABLE IF EXISTS tmp_calendar_days;
  CREATE TEMPORARY TABLE tmp_calendar_days (
    day_index INT PRIMARY KEY,
    calendar_date DATE NOT NULL
  ) ENGINE = MEMORY;

  day_loop: WHILE v_index < v_day_count DO
    INSERT INTO tmp_calendar_days (day_index, calendar_date)
    VALUES (v_index, DATE_ADD(p_from, INTERVAL v_index DAY));
    SET v_index = v_index + 1;
  END WHILE day_loop;

  DROP TEMPORARY TABLE IF EXISTS tmp_room_events;
  CREATE TEMPORARY TABLE tmp_room_events AS
  SELECT
    'reservation' AS event_type,
    r.id_reservation,
    NULL AS id_room_block,
    r.id_room,
    rm.id_property,
    COALESCE(r.id_category, rm.id_category) AS id_category,
    r.id_guest,
    r.id_user AS id_user,
    r.code,
    r.status,
    CASE
      WHEN COALESCE(r.id_ota_account, 0) > 0 THEN 'ota'
      ELSE COALESCE(NULLIF(TRIM(r.source), ''), COALESCE(rsc.source_name, 'Directo'))
    END AS source,
    r.id_ota_account,
    COALESCE(oa.ota_name, '') AS ota_name,
    COALESCE(oa.platform, '') AS ota_platform,
    r.id_reservation_source,
    COALESCE(rsc.source_name, '') AS reservation_source_name,
    r.check_in_date,
    r.check_out_date,
    r.check_out_date AS end_date_exclusive,
    r.adults,
    r.children,
    r.currency,
    CASE
      WHEN COALESCE(fsum.folio_count, 0) > 0 THEN COALESCE(fsum.total_cents, 0)
      ELSE 0
    END AS total_price_cents,
    CASE
      WHEN COALESCE(fsum.folio_count, 0) > 0 THEN COALESCE(fsum.balance_cents, 0)
      ELSE 0
    END AS balance_due_cents,
    COALESCE(fsum.folio_count, 0) AS folio_count,
    r.notes_guest AS notes_guest,
    (
      SELECT rn.note_text
      FROM reservation_note rn
      WHERE rn.id_reservation = r.id_reservation
        AND rn.deleted_at IS NULL
        AND COALESCE(rn.is_active, 1) = 1
      ORDER BY rn.created_at DESC, rn.id_reservation_note DESC
      LIMIT 1
    ) AS latest_note_text,
    r.notes_internal AS description
  FROM reservation r
  JOIN room rm ON rm.id_room = r.id_room
  LEFT JOIN ota_account oa
    ON oa.id_ota_account = r.id_ota_account
   AND oa.deleted_at IS NULL
   AND oa.is_active = 1
  LEFT JOIN reservation_source_catalog rsc
    ON rsc.id_reservation_source = r.id_reservation_source
  LEFT JOIN (
    SELECT
      f.id_reservation,
      SUM(CASE WHEN COALESCE(f.is_active, 1) = 1 THEN 1 ELSE 0 END) AS folio_count,
      SUM(CASE WHEN COALESCE(f.is_active, 1) = 1 THEN f.total_cents ELSE 0 END) AS total_cents,
      SUM(CASE WHEN COALESCE(f.is_active, 1) = 1 THEN f.balance_cents ELSE 0 END) AS balance_cents
    FROM folio f
    WHERE f.deleted_at IS NULL
    GROUP BY f.id_reservation
  ) fsum ON fsum.id_reservation = r.id_reservation
  WHERE rm.id_property = v_id_property
    AND r.deleted_at IS NULL
    AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
    AND r.check_in_date < v_to
    AND r.check_out_date > p_from
  UNION ALL
  SELECT
    'block' AS event_type,
    NULL AS id_reservation,
    rb.id_room_block,
    rb.id_room,
    rb.id_property,
    rm.id_category,
    NULL AS id_guest,
    rb.id_user AS id_user,
    rb.code,
    'blocked' AS status,
    'block'  AS source,
    NULL AS id_ota_account,
    '' AS ota_name,
    '' AS ota_platform,
    NULL AS id_reservation_source,
    '' AS reservation_source_name,
    rb.start_date AS check_in_date,
    rb.end_date   AS check_out_date,
    DATE_ADD(rb.end_date, INTERVAL 1 DAY) AS end_date_exclusive,
    0 AS adults,
    0 AS children,
    'MXN' AS currency,
    0 AS total_price_cents,
    0 AS balance_due_cents,
    0 AS folio_count,
    '' AS notes_guest,
    '' AS latest_note_text,
    rb.description AS description
  FROM room_block rb
  JOIN room rm ON rm.id_room = rb.id_room
  WHERE rb.id_property = v_id_property
    AND rb.deleted_at IS NULL
    AND rb.is_active = 1
    AND rb.start_date < v_to
    AND DATE_ADD(rb.end_date, INTERVAL 1 DAY) > p_from;

  /* Result set 1: room catalog for the property */
  IF v_has_category_calendar_display = 1 THEN
    SELECT
      r.id_room,
      r.code AS room_code,
      r.name AS room_name,
      NULL AS floor_label,
      rc.id_category,
      rc.code AS category_code,
      rc.name AS category_name,
      rc.order_index AS category_order_index,
      rc.max_occupancy,
      COALESCE(rc.color_hex, '') AS category_color,
      COALESCE(r.max_adults, rc.max_occupancy) AS room_max_adults,
      COALESCE(cad.calendar_amenities_csv, '') AS calendar_amenities_csv,
      r.order_index AS room_order_index
    FROM room r
    JOIN roomcategory rc ON rc.id_category = r.id_category
    LEFT JOIN (
      SELECT
        t.id_category,
        GROUP_CONCAT(t.amenity_key ORDER BY t.display_order, t.id_category_calendar_amenity_display SEPARATOR ',') AS calendar_amenities_csv
      FROM category_calendar_amenity_display t
      WHERE t.is_active = 1
      GROUP BY t.id_category
    ) cad ON cad.id_category = rc.id_category
    WHERE r.id_property = v_id_property
      AND r.is_active = 1
      AND r.deleted_at IS NULL
    ORDER BY rc.order_index, rc.name, r.order_index, r.code;
  ELSE
    SELECT
      r.id_room,
      r.code AS room_code,
      r.name AS room_name,
      NULL AS floor_label,
      rc.id_category,
      rc.code AS category_code,
      rc.name AS category_name,
      rc.order_index AS category_order_index,
      rc.max_occupancy,
      COALESCE(rc.color_hex, '') AS category_color,
      COALESCE(r.max_adults, rc.max_occupancy) AS room_max_adults,
      '' AS calendar_amenities_csv,
      r.order_index AS room_order_index
    FROM room r
    JOIN roomcategory rc ON rc.id_category = r.id_category
    WHERE r.id_property = v_id_property
      AND r.is_active = 1
      AND r.deleted_at IS NULL
    ORDER BY rc.order_index, rc.name, r.order_index, r.code;
  END IF;

  /* Result set 2: calendar days metadata */
  SELECT
    d.day_index,
    d.calendar_date,
    DATE_FORMAT(d.calendar_date, '%Y-%m-%d') AS date_key,
    DATE_FORMAT(d.calendar_date, '%a') AS day_short_name,
    DATE_FORMAT(d.calendar_date, '%W') AS day_name,
    DATE_FORMAT(d.calendar_date, '%d/%m') AS day_label,
    DAYOFWEEK(d.calendar_date) AS day_of_week,
    WEEKOFYEAR(d.calendar_date) AS iso_week,
    MONTH(d.calendar_date) AS month,
    YEAR(d.calendar_date) AS year,
    CASE WHEN d.calendar_date = CURDATE() THEN 1 ELSE 0 END AS is_today
  FROM tmp_calendar_days d
  ORDER BY d.day_index;

  /* Result set 3: reservations + blocks overlapping the range */
  SELECT
    CASE
      WHEN ev.event_type = 'block' THEN -ev.id_room_block
      ELSE ev.id_reservation
    END AS id_reservation,
    ev.id_room_block,
    ev.event_type,
    ev.code AS reservation_code,
    ev.status,
    ev.source,
    ev.id_ota_account,
    ev.ota_name,
    ev.ota_platform,
    ev.id_reservation_source,
    ev.reservation_source_name,
    ev.check_in_date,
    ev.check_out_date,
    GREATEST(0, DATEDIFF(GREATEST(ev.check_in_date, p_from), p_from)) AS range_start_offset,
    GREATEST(1,
      DATEDIFF(
        LEAST(ev.end_date_exclusive, v_to),
        GREATEST(ev.check_in_date, p_from)
      )
    ) AS range_nights,
    DATEDIFF(ev.end_date_exclusive, ev.check_in_date) AS total_nights,
    ev.adults,
    ev.children,
    ev.currency,
    ev.total_price_cents,
    ev.balance_due_cents,
    ev.folio_count,
    ev.notes_guest,
    ev.latest_note_text,
    ev.description,
    g.email AS guest_email,
    CONCAT_WS(' ', COALESCE(g.names, ''), COALESCE(g.last_name, '')) AS guest_full_name,
    g.names AS guest_names,
    g.last_name AS guest_last_name,
    ev.id_user,
    rm.id_room,
    rm.code AS room_code,
    rm.name AS room_name,
    rc.code AS category_code,
    rc.name AS category_name
  FROM tmp_room_events ev
  JOIN room rm ON rm.id_room = ev.id_room
  JOIN roomcategory rc ON rc.id_category = ev.id_category
  LEFT JOIN guest g ON g.id_guest = ev.id_guest
  WHERE ev.id_property = v_id_property
  ORDER BY rm.code, ev.check_in_date,
    CASE
      WHEN ev.event_type = 'block' THEN -ev.id_room_block
      ELSE ev.id_reservation
    END;

  /* Result set 4: daily occupancy summary (reservations + blocks) */
  SELECT
    d.day_index,
    d.calendar_date,
    DATE_FORMAT(d.calendar_date, '%Y-%m-%d') AS date_key,
    v_total_rooms AS total_rooms,
    COUNT(DISTINCT ev.id_room) AS occupied_rooms,
    SUM(
      CASE
        WHEN ev.id_room IS NOT NULL AND ev.event_type = 'reservation' AND ev.check_in_date = d.calendar_date THEN 1
        ELSE 0
      END
    ) AS arrivals,
    SUM(
      CASE
        WHEN ev.id_room IS NOT NULL AND ev.event_type = 'reservation' AND ev.check_out_date = d.calendar_date THEN 1
        ELSE 0
      END
    ) AS departures,
    CASE
      WHEN v_total_rooms = 0 THEN 0
      ELSE ROUND(COUNT(DISTINCT ev.id_room) / v_total_rooms * 100, 1)
    END AS occupancy_pct
  FROM tmp_calendar_days d
  LEFT JOIN tmp_room_events ev
    ON ev.check_in_date <= d.calendar_date
   AND ev.end_date_exclusive > d.calendar_date
  GROUP BY d.day_index, d.calendar_date
  ORDER BY d.day_index;
END $$

DELIMITER ;
