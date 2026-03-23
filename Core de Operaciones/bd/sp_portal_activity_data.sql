DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_portal_activity_data` $$
CREATE PROCEDURE `sp_portal_activity_data` (
  IN p_company_code  VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_search        VARCHAR(255),
  IN p_only_active   TINYINT,
  IN p_activity_id   BIGINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
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

  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property
      INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_property_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
    END IF;
  ELSE
    SET v_property_id = NULL;
  END IF;

  SET v_search = NULLIF(TRIM(p_search), '');

  /* Result set 1: list of activities */
  SELECT
    act.id_activity,
    act.code AS activity_code,
    act.name AS activity_name,
    act.type,
    act.duration_minutes,
    act.base_price_cents,
    act.currency,
    act.capacity_default,
    act.location,
    act.is_active,
    act.id_sale_item_catalog,
    sic.item_name AS sale_item_name,
    cat.category_name AS sale_item_category,
    pr.code AS property_code,
    pr.name AS property_name
  FROM activity act
  LEFT JOIN property pr ON pr.id_property = act.id_property
  LEFT JOIN line_item_catalog sic
    ON sic.id_line_item_catalog = act.id_sale_item_catalog
   AND sic.catalog_type = 'sale_item'
  LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
    AND cat.id_company = act.id_company
    AND cat.deleted_at IS NULL
  WHERE act.id_company = v_company_id
    AND act.deleted_at IS NULL
    AND (p_only_active IS NULL OR p_only_active = 0 OR act.is_active = 1)
    AND (v_property_id IS NULL OR act.id_property = v_property_id)
    AND (
      v_search IS NULL OR
      act.code LIKE CONCAT('%', v_search, '%') OR
      act.name LIKE CONCAT('%', v_search, '%') OR
      act.type LIKE CONCAT('%', v_search, '%')
    )
  ORDER BY act.name
  LIMIT 500;

  /* Result set 2: activity detail */
  IF p_activity_id IS NULL OR p_activity_id = 0 THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_activity,
      CAST(NULL AS CHAR) AS activity_code,
      CAST(NULL AS CHAR) AS activity_name,
      CAST(NULL AS CHAR) AS type,
      CAST(NULL AS CHAR) AS description,
      CAST(NULL AS SIGNED) AS duration_minutes,
      CAST(NULL AS SIGNED) AS base_price_cents,
      CAST(NULL AS CHAR) AS currency,
      CAST(NULL AS SIGNED) AS capacity_default,
      CAST(NULL AS CHAR) AS location,
      CAST(NULL AS SIGNED) AS is_active,
      CAST(NULL AS SIGNED) AS id_sale_item_catalog,
      CAST(NULL AS CHAR) AS sale_item_name,
      CAST(NULL AS CHAR) AS sale_item_category,
      CAST(NULL AS CHAR) AS property_code
    LIMIT 0;
  ELSE
    SELECT
      act.id_activity,
      act.code AS activity_code,
      act.name AS activity_name,
      act.type,
      act.description,
      act.duration_minutes,
      act.base_price_cents,
      act.currency,
      act.capacity_default,
      act.location,
      act.is_active,
      act.id_sale_item_catalog,
      sic.item_name AS sale_item_name,
      cat.category_name AS sale_item_category,
      pr.code AS property_code,
      pr.name AS property_name
    FROM activity act
    LEFT JOIN property pr ON pr.id_property = act.id_property
    LEFT JOIN line_item_catalog sic
      ON sic.id_line_item_catalog = act.id_sale_item_catalog
     AND sic.catalog_type = 'sale_item'
    LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
      AND cat.id_company = act.id_company
      AND cat.deleted_at IS NULL
    WHERE act.id_activity = p_activity_id
      AND act.id_company = v_company_id
      AND act.deleted_at IS NULL
    LIMIT 1;
  END IF;

  /* Result set 3: recent bookings for the activity */
  SELECT
    ab.id_booking,
    ab.scheduled_at,
    ab.status,
    ab.num_adults,
    ab.num_children,
    ab.price_cents,
    ab.currency,
    COALESCE(g.names, g0.names) AS guest_names,
    COALESCE(g.last_name, g0.last_name) AS guest_last_name,
    COALESCE(g.email, g0.email) AS guest_email,
    COALESCE(r.code, r0.code) AS reservation_code,
    COALESCE(linked.first_reservation_id, ab.id_reservation) AS id_reservation,
    COALESCE(linked.linked_reservation_count, CASE WHEN ab.id_reservation IS NULL THEN 0 ELSE 1 END) AS linked_reservation_count,
    COALESCE(linked.linked_reservation_ids_csv, CASE WHEN ab.id_reservation IS NULL THEN '' ELSE CAST(ab.id_reservation AS CHAR) END) AS linked_reservation_ids_csv,
    COALESCE(linked.linked_reservation_codes, COALESCE(r0.code, '')) AS linked_reservation_codes,
    COALESCE(linked.linked_guest_names, NULLIF(TRIM(CONCAT_WS(' ', COALESCE(g0.names, ''), COALESCE(g0.last_name, ''))), '')) AS linked_guest_names
  FROM activity_booking ab
  LEFT JOIN reservation r0 ON r0.id_reservation = ab.id_reservation
  LEFT JOIN guest g0 ON g0.id_guest = r0.id_guest
  LEFT JOIN (
    SELECT
      x.id_booking,
      MIN(x.id_reservation) AS first_reservation_id,
      COUNT(DISTINCT x.id_reservation) AS linked_reservation_count,
      GROUP_CONCAT(DISTINCT CAST(x.id_reservation AS CHAR) ORDER BY x.id_reservation SEPARATOR ',') AS linked_reservation_ids_csv,
      GROUP_CONCAT(DISTINCT COALESCE(r.code, '') ORDER BY r.code SEPARATOR ' | ') AS linked_reservation_codes,
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
  ) linked ON linked.id_booking = ab.id_booking
  LEFT JOIN reservation r ON r.id_reservation = linked.first_reservation_id
  LEFT JOIN guest g ON g.id_guest = r.id_guest
  WHERE ab.id_activity = p_activity_id
    AND ab.deleted_at IS NULL
  ORDER BY ab.scheduled_at DESC
  LIMIT 200;
END $$

DELIMITER ;
