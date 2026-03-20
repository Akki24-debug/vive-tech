DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_portal_guest_data` $$
CREATE PROCEDURE `sp_portal_guest_data` (
  IN p_company_code VARCHAR(100),
  IN p_search       VARCHAR(255),
  IN p_only_active  TINYINT,
  IN p_guest_id     BIGINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_search VARCHAR(255);

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

  SET v_search = NULLIF(TRIM(p_search), '');

  /* Result set 1: guests linked to the company */
  SELECT
    g.id_guest,
    g.email,
    g.names,
    g.last_name,
    g.maiden_name,
    g.full_name,
    g.phone,
    g.is_active,
    COUNT(DISTINCT CASE WHEN pr.id_company = v_company_id THEN r.id_reservation END) AS reservation_count,
    MIN(CASE WHEN pr.id_company = v_company_id THEN r.check_in_date END) AS first_check_in,
    MAX(CASE WHEN pr.id_company = v_company_id THEN r.check_out_date END) AS last_check_out
  FROM guest g
  LEFT JOIN reservation r
    ON r.id_guest = g.id_guest
   AND r.deleted_at IS NULL
  LEFT JOIN property pr
    ON pr.id_property = r.id_property
  LEFT JOIN app_user au
    ON au.id_user = g.created_by
  WHERE g.deleted_at IS NULL
    AND (
      (pr.id_property IS NOT NULL AND pr.id_company = v_company_id) OR
      (au.id_user IS NOT NULL AND au.id_company = v_company_id)
    )
    AND (p_only_active IS NULL OR p_only_active = 0 OR g.is_active = 1)
    AND (
      v_search IS NULL OR
      g.email LIKE CONCAT('%', v_search, '%') OR
      g.names LIKE CONCAT('%', v_search, '%') OR
      g.last_name LIKE CONCAT('%', v_search, '%') OR
      g.maiden_name LIKE CONCAT('%', v_search, '%') OR
      g.full_name LIKE CONCAT('%', v_search, '%') OR
      g.phone LIKE CONCAT('%', v_search, '%')
    )
  GROUP BY
    g.id_guest,
    g.email,
    g.names,
    g.last_name,
    g.maiden_name,
    g.full_name,
    g.phone,
    g.is_active
  ORDER BY g.names, g.last_name, g.maiden_name, g.email;

  /* Result set 2: guest detail */
  IF p_guest_id IS NULL OR p_guest_id = 0 THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_guest,
      CAST(NULL AS CHAR) AS email,
      CAST(NULL AS CHAR) AS names,
      CAST(NULL AS CHAR) AS last_name,
      CAST(NULL AS CHAR) AS maiden_name,
      CAST(NULL AS CHAR) AS phone,
      CAST(NULL AS CHAR) AS nationality,
      CAST(NULL AS CHAR) AS country,
      CAST(NULL AS CHAR) AS language,
      CAST(NULL AS CHAR) AS notes_internal,
      CAST(NULL AS CHAR) AS notes_guest,
      CAST(NULL AS SIGNED) AS is_active
    LIMIT 0;
  ELSE
    SELECT
      g.id_guest,
      g.email,
      g.names,
      g.last_name,
      g.maiden_name,
      g.phone,
      g.nationality,
      g.country,
      g.language,
      g.marketing_opt_in,
      g.blacklisted,
      g.blacklist_reason,
      g.notes_internal,
      g.notes_guest,
      g.is_active
    FROM guest g
    LEFT JOIN reservation r ON r.id_guest = g.id_guest AND r.deleted_at IS NULL
    LEFT JOIN property pr ON pr.id_property = r.id_property
    LEFT JOIN app_user au ON au.id_user = g.created_by
    WHERE g.id_guest = p_guest_id
      AND g.deleted_at IS NULL
      AND (
        (pr.id_company = v_company_id) OR
        (pr.id_company IS NULL AND au.id_company = v_company_id)
      )
    LIMIT 1;
  END IF;

  /* Result set 3: reservations for guest belonging to the company */
  SELECT
    r.id_reservation,
    r.code AS reservation_code,
    r.status,
    r.source,
    r.check_in_date,
    r.check_out_date,
    r.adults,
    r.children,
    r.total_price_cents,
    r.balance_due_cents,
    pr.code AS property_code,
    pr.name AS property_name,
    rm.code AS room_code,
    rc.name AS category_name
  FROM reservation r
  JOIN property pr ON pr.id_property = r.id_property
  LEFT JOIN room rm ON rm.id_room = r.id_room
  LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
  WHERE r.id_guest = p_guest_id
    AND pr.id_company = v_company_id
    AND r.deleted_at IS NULL
  ORDER BY r.check_in_date DESC, r.id_reservation DESC
  LIMIT 500;

  /* Result set 4: activity bookings for guest */
  SELECT
    ab.id_booking,
    ab.scheduled_at,
    ab.status,
    ab.num_adults,
    ab.num_children,
    ab.price_cents,
    ab.currency,
    act.code AS activity_code,
    act.name AS activity_name,
    pr.code AS property_code,
    pr.name AS property_name
  FROM activity_booking ab
  JOIN activity act ON act.id_activity = ab.id_activity
  JOIN (
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
  ) abr_link ON abr_link.id_booking = ab.id_booking
  JOIN reservation r ON r.id_reservation = abr_link.id_reservation
  LEFT JOIN property pr ON pr.id_property = act.id_property
  WHERE r.id_guest = p_guest_id
    AND ab.deleted_at IS NULL
    AND (
      pr.id_property IS NULL OR pr.id_company = v_company_id
    )
  ORDER BY ab.scheduled_at DESC
  LIMIT 500;
END $$

DELIMITER ;
