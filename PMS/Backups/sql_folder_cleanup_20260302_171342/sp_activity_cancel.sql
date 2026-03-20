DELIMITER $$

DROP PROCEDURE IF EXISTS sp_activity_cancel $$
CREATE PROCEDURE sp_activity_cancel
(
  IN p_booking_id  BIGINT,
  IN p_note        TEXT
)
proc:BEGIN
  DECLARE v_exists BIGINT;
  SELECT id_booking INTO v_exists FROM activity_booking WHERE id_booking = p_booking_id LIMIT 1;
  IF v_exists IS NULL THEN
    SELECT 'ERROR' AS status, 'Unknown booking id' AS message; LEAVE proc;
  END IF;

  UPDATE activity_booking
     SET status='cancelled',
         notes = CONCAT(COALESCE(notes,''), CASE WHEN p_note IS NULL OR p_note='' THEN '' ELSE CONCAT(' | ', p_note) END),
         updated_at = NOW()
   WHERE id_booking = p_booking_id;

  SELECT
    b.id_booking, b.status, b.scheduled_at, b.price_cents, b.currency,
    a.type AS activity_type, a.code AS activity_code, a.name AS activity_name,
    g.names AS guest_names, g.last_name AS guest_last_name, g.email AS guest_email
  FROM activity_booking b
  JOIN activity a ON a.id_activity=b.id_activity
  LEFT JOIN (
    SELECT
      abr.id_booking,
      MIN(abr.id_reservation) AS id_reservation
    FROM activity_booking_reservation abr
    WHERE abr.deleted_at IS NULL
      AND COALESCE(abr.is_active, 1) = 1
    GROUP BY abr.id_booking
  ) abr0 ON abr0.id_booking = b.id_booking
  JOIN reservation r
    ON r.id_reservation = COALESCE(abr0.id_reservation, b.id_reservation)
  LEFT JOIN guest g ON g.id_guest = r.id_guest
  WHERE b.id_booking = p_booking_id;
END $$
DELIMITER ;
