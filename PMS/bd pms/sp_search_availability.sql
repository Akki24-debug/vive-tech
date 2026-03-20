DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_search_availability` $$
CREATE PROCEDURE `sp_search_availability` (
    IN p_company_code VARCHAR(100),
    IN p_check_in DATE,
    IN p_nights INT,
    IN p_people INT
)
BEGIN
    DECLARE v_check_out DATE;
    SET v_check_out = DATE_ADD(p_check_in, INTERVAL p_nights DAY);

    /* One row per active room category with at least 1 room available */
    SELECT
      p.id_property,
      p.code  AS property_code,
      p.name  AS property_name,
      p.currency,
      rc.id_category,
      rc.code AS category_code,
      rc.name AS category_name,
      rc.max_occupancy,
      /* totals per category */
      (
        SELECT COUNT(*)
        FROM room r
        WHERE r.id_category = rc.id_category
          AND r.is_active = 1
          AND r.deleted_at IS NULL
      ) AS total_rooms,
      (
        SELECT COUNT(DISTINCT r2.id_room)
        FROM room r2
        WHERE r2.id_category = rc.id_category
          AND r2.is_active = 1
          AND r2.deleted_at IS NULL
          AND (
            EXISTS (
              SELECT 1
              FROM reservation res
              WHERE res.id_room = r2.id_room
                AND res.deleted_at IS NULL
                AND COALESCE(res.status,'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
                AND res.check_in_date  < v_check_out
                AND res.check_out_date > p_check_in
            )
            OR EXISTS (
              SELECT 1
              FROM room_block rb
              WHERE rb.id_room = r2.id_room
                AND rb.deleted_at IS NULL
                AND rb.is_active = 1
                AND rb.start_date < v_check_out
                AND DATE_ADD(rb.end_date, INTERVAL 1 DAY) > p_check_in
            )
          )
      ) AS booked_rooms,
      GREATEST(
        (
          SELECT COUNT(*) FROM room r
          WHERE r.id_category = rc.id_category AND r.is_active=1 AND r.deleted_at IS NULL
        ) - (
          SELECT COUNT(DISTINCT r2.id_room) FROM room r2
          WHERE r2.id_category = rc.id_category AND r2.is_active=1 AND r2.deleted_at IS NULL
            AND (
              EXISTS (
                SELECT 1 FROM reservation res
                WHERE res.id_room = r2.id_room
                  AND res.deleted_at IS NULL
                  AND COALESCE(res.status,'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
                  AND res.check_in_date  < v_check_out AND res.check_out_date > p_check_in
              )
              OR EXISTS (
                SELECT 1 FROM room_block rb
                WHERE rb.id_room = r2.id_room
                  AND rb.deleted_at IS NULL
                  AND rb.is_active = 1
                  AND rb.start_date < v_check_out
                  AND DATE_ADD(rb.end_date, INTERVAL 1 DAY) > p_check_in
              )
            )
        ), 0
      ) AS available_rooms,
      rc.default_floor_cents,
      rc.default_ceil_cents,
      rc.default_base_price_cents,
      rc.image_url
    FROM roomcategory rc
    INNER JOIN property p ON p.id_property = rc.id_property
    INNER JOIN company  c ON c.id_company = p.id_company
    WHERE c.code = p_company_code
      AND c.deleted_at IS NULL
      AND p.is_active = 1 AND p.deleted_at IS NULL
      AND rc.is_active = 1 AND rc.deleted_at IS NULL
      AND (rc.max_occupancy IS NULL OR rc.max_occupancy >= p_people)
      /* at least one available */
      AND GREATEST(
        (
          SELECT COUNT(*) FROM room r
          WHERE r.id_category = rc.id_category AND r.is_active=1 AND r.deleted_at IS NULL
        ) - (
          SELECT COUNT(DISTINCT r2.id_room) FROM room r2
          WHERE r2.id_category = rc.id_category AND r2.is_active=1 AND r2.deleted_at IS NULL
            AND (
              EXISTS (
                SELECT 1 FROM reservation res
                WHERE res.id_room = r2.id_room
                  AND res.deleted_at IS NULL
                  AND COALESCE(res.status,'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
                  AND res.check_in_date  < v_check_out AND res.check_out_date > p_check_in
              )
              OR EXISTS (
                SELECT 1 FROM room_block rb
                WHERE rb.id_room = r2.id_room
                  AND rb.deleted_at IS NULL
                  AND rb.is_active = 1
                  AND rb.start_date < v_check_out AND DATE_ADD(rb.end_date, INTERVAL 1 DAY) > p_check_in
              )
            )
        ), 0
      ) > 0
    ORDER BY p.name, rc.name;
END $$

DELIMITER ;
