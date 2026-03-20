DELIMITER $$

DROP PROCEDURE IF EXISTS sp_list_reservations_by_company $$
CREATE PROCEDURE sp_list_reservations_by_company
(
  IN p_company_id   INT,
  IN p_company_code VARCHAR(32)
)
proc:BEGIN
  DECLARE v_id_company INT;

  IF p_company_id IS NOT NULL AND p_company_id <> 0 THEN
    SET v_id_company = p_company_id;
  ELSE
    SELECT id_company INTO v_id_company
    FROM company
    WHERE code = p_company_code
    LIMIT 1;
  END IF;

  IF v_id_company IS NULL THEN
    SELECT 'ERROR' AS status, 'Unknown company (id/code)' AS message; LEAVE proc;
  END IF;

  SELECT
    g.names          AS guest_names,
    g.last_name      AS guest_last_name,
    p.name           AS hospedaje,
    r.check_in_date  AS check_in,
    r.check_out_date AS check_out,
    rm.code          AS room_code
  FROM reservation r
  JOIN property p ON p.id_property = r.id_property
  JOIN room rm    ON rm.id_room = r.id_room
  JOIN guest g    ON g.id_guest = r.id_guest
  WHERE p.id_company = v_id_company
  ORDER BY p.name, r.check_in_date, r.id_reservation;
END $$

DELIMITER ;
