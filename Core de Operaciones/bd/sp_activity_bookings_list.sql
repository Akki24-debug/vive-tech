DELIMITER $$

DROP PROCEDURE IF EXISTS sp_activity_bookings_list $$
CREATE PROCEDURE sp_activity_bookings_list
(
  IN p_type            VARCHAR(10),     -- NULL = ambos; 'tour' o 'vibe'
  IN p_company_id      BIGINT,
  IN p_company_code    VARCHAR(32),
  IN p_property_id     BIGINT,
  IN p_property_code   VARCHAR(32),
  IN p_activity_id     BIGINT,
  IN p_activity_code   VARCHAR(32),
  IN p_from            DATETIME,
  IN p_to              DATETIME,
  IN p_booking_id      BIGINT
)
BEGIN
  DECLARE v_company BIGINT;
  DECLARE v_property BIGINT;
  DECLARE v_activity BIGINT;

  -- Company
  IF p_company_id IS NOT NULL AND p_company_id <> 0 THEN
    SET v_company = p_company_id;
  ELSEIF p_company_code IS NOT NULL AND p_company_code <> '' THEN
    SELECT id_company INTO v_company FROM company WHERE code = p_company_code LIMIT 1;
  END IF;

  -- Property
  IF p_property_id IS NOT NULL AND p_property_id <> 0 THEN
    SET v_property = p_property_id;
  ELSEIF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property INTO v_property FROM property WHERE code = p_property_code LIMIT 1;
  END IF;

  -- Activity
  IF p_activity_id IS NOT NULL AND p_activity_id <> 0 THEN
    SET v_activity = p_activity_id;
  ELSEIF p_activity_code IS NOT NULL AND p_activity_code <> '' THEN
    SELECT id_activity INTO v_activity FROM activity WHERE code = p_activity_code LIMIT 1;
  END IF;

  SELECT
    b.id_booking,
    b.id_activity,
    a.type            AS activity_type,
    a.code            AS activity_code,
    a.name            AS activity_name,
    p.name            AS property_name,
    c.code            AS company_code,
    b.scheduled_at,
    (b.num_adults + b.num_children) AS pax,
    b.status,
    b.price_cents,
    b.currency,
    b.num_adults,
    b.num_children,
    b.notes,
    COALESCE(br.linked_count, CASE WHEN b.id_reservation IS NULL THEN 0 ELSE 1 END) AS linked_reservation_count,
    COALESCE(br.linked_ids_csv, CASE WHEN b.id_reservation IS NULL THEN '' ELSE CAST(b.id_reservation AS CHAR) END) AS linked_reservation_ids_csv,
    COALESCE(br.linked_codes, COALESCE(r_primary.code, '')) AS linked_reservation_codes,
    COALESCE(br.linked_guest_names, NULLIF(TRIM(CONCAT_WS(' ', COALESCE(g_primary.names, ''), COALESCE(g_primary.last_name, ''))), '')) AS linked_guest_names,
    COALESCE(br.first_reservation_id, b.id_reservation) AS id_reservation,
    COALESCE(r_first.code, r_primary.code) AS reservation_code,
    COALESCE(g_first.names, g_primary.names) AS guest_names,
    COALESCE(g_first.last_name, g_primary.last_name) AS guest_last_name,
    COALESCE(g_first.email, g_primary.email) AS guest_email,
    b.id_reservation AS id_primary_reservation,
    r_primary.code   AS primary_reservation_code
  FROM activity_booking b
  JOIN activity a ON a.id_activity = b.id_activity
  JOIN company  c ON c.id_company   = a.id_company
  LEFT JOIN property p ON p.id_property = a.id_property
  LEFT JOIN reservation r_primary ON r_primary.id_reservation = b.id_reservation
  LEFT JOIN guest g_primary ON g_primary.id_guest = r_primary.id_guest
  LEFT JOIN (
    SELECT
      x.id_booking,
      COUNT(DISTINCT x.id_reservation) AS linked_count,
      MIN(x.id_reservation) AS first_reservation_id,
      GROUP_CONCAT(DISTINCT CAST(x.id_reservation AS CHAR) ORDER BY x.id_reservation SEPARATOR ',') AS linked_ids_csv,
      GROUP_CONCAT(DISTINCT COALESCE(r.code, '') ORDER BY r.code SEPARATOR ' | ') AS linked_codes,
      GROUP_CONCAT(
        DISTINCT NULLIF(TRIM(CONCAT_WS(' ', COALESCE(g.names, ''), COALESCE(g.last_name, ''))), '')
        ORDER BY r.code
        SEPARATOR ' | '
      ) AS linked_guest_names
    FROM (
      SELECT abr.id_booking, abr.id_reservation
      FROM activity_booking_reservation abr
      WHERE abr.deleted_at IS NULL
        AND COALESCE(abr.is_active, 1) = 1

      UNION ALL

      SELECT b2.id_booking, b2.id_reservation
      FROM activity_booking b2
      WHERE b2.id_reservation IS NOT NULL
        AND NOT EXISTS (
          SELECT 1
          FROM activity_booking_reservation abr2
          WHERE abr2.id_booking = b2.id_booking
            AND abr2.deleted_at IS NULL
            AND COALESCE(abr2.is_active, 1) = 1
        )
    ) x
    JOIN reservation r
      ON r.id_reservation = x.id_reservation
     AND r.deleted_at IS NULL
    LEFT JOIN guest g ON g.id_guest = r.id_guest
    GROUP BY x.id_booking
  ) br ON br.id_booking = b.id_booking
  LEFT JOIN reservation r_first ON r_first.id_reservation = br.first_reservation_id
  LEFT JOIN guest g_first ON g_first.id_guest = r_first.id_guest
  WHERE (p_type IS NULL OR a.type = p_type)
    AND (v_company IS NULL OR a.id_company = v_company)
    AND (v_property IS NULL OR a.id_property = v_property)
    AND (v_activity IS NULL OR a.id_activity = v_activity)
    AND (p_booking_id IS NULL OR p_booking_id = 0 OR b.id_booking = p_booking_id)
    AND (p_from IS NULL OR b.scheduled_at >= p_from)
    AND (p_to   IS NULL OR b.scheduled_at <  p_to)
    AND (b.deleted_at IS NULL)
  ORDER BY b.scheduled_at, a.name, b.id_booking;
END $$
DELIMITER ;
