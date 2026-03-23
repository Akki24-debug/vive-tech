DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_list_reservations_by_property` $$
CREATE PROCEDURE `sp_list_reservations_by_property` (
  IN p_property_code VARCHAR(100),
  IN p_from DATE,
  IN p_to DATE
)
BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_from DATE;
  DECLARE v_to DATE;

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;

  SELECT id_property INTO v_id_property
  FROM property
  WHERE code = p_property_code
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  SET v_from = COALESCE(p_from, DATE_SUB(CURDATE(), INTERVAL 30 DAY));
  SET v_to = COALESCE(p_to, DATE_ADD(CURDATE(), INTERVAL 180 DAY));

  SELECT
    ev.id_reservation,
    ev.reservation_code,
    ev.status,
    ev.source,
    ev.id_ota_account,
    oa.ota_name,
    oa.platform AS ota_platform,
    ev.id_reservation_source,
    COALESCE(rsc.source_name, '') AS reservation_source_name,
    ev.check_in_date,
    ev.check_out_date,
    ev.adults,
    ev.children,
    ev.currency,
    ev.total_price_cents,
    ev.balance_due_cents,
    g.email AS guest_email,
    g.names AS guest_names,
    g.last_name AS guest_last_name,
    rm.code AS room_code,
    rm.name AS room_name,
    pr.code AS property_code,
    pr.name AS property_name,
    ev.created_at,
    ev.updated_at
  FROM (
    SELECT
      r.id_reservation,
      r.code AS reservation_code,
      r.status,
      r.source,
      r.id_ota_account,
      r.id_reservation_source,
      r.check_in_date,
      r.check_out_date,
      r.adults,
      r.children,
      r.currency,
      r.total_price_cents,
      r.balance_due_cents,
      r.id_guest,
      r.id_room,
      r.id_property,
      r.created_at,
      r.updated_at
    FROM reservation r
    WHERE r.id_property = v_id_property
      AND r.deleted_at IS NULL
      AND r.check_in_date >= v_from
      AND r.check_in_date <= v_to

    UNION ALL

    SELECT
      -rb.id_room_block AS id_reservation,
      rb.code AS reservation_code,
      'blocked' AS status,
      'block' AS source,
      NULL AS id_ota_account,
      NULL AS id_reservation_source,
      rb.start_date AS check_in_date,
      rb.end_date AS check_out_date,
      0 AS adults,
      0 AS children,
      'MXN' AS currency,
      0 AS total_price_cents,
      0 AS balance_due_cents,
      NULL AS id_guest,
      rb.id_room,
      rb.id_property,
      rb.created_at,
      rb.updated_at
    FROM room_block rb
    WHERE rb.id_property = v_id_property
      AND rb.deleted_at IS NULL
      AND rb.is_active = 1
      AND rb.start_date >= v_from
      AND rb.start_date <= v_to
  ) AS ev
  LEFT JOIN guest g ON g.id_guest = ev.id_guest
  LEFT JOIN room rm ON rm.id_room = ev.id_room
  LEFT JOIN ota_account oa
    ON oa.id_ota_account = ev.id_ota_account
   AND oa.deleted_at IS NULL
   AND oa.is_active = 1
  LEFT JOIN reservation_source_catalog rsc
    ON rsc.id_reservation_source = ev.id_reservation_source
  JOIN property pr ON pr.id_property = ev.id_property
  ORDER BY ev.check_in_date DESC, ev.id_reservation DESC;
END $$

DELIMITER ;
