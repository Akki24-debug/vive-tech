DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reservation_update_v2` $$
CREATE PROCEDURE `sp_reservation_update_v2` (
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
  IN p_reservation_code VARCHAR(100),
  IN p_notes_internal   TEXT,
  IN p_notes_guest      TEXT,
  IN p_actor_user_id    BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;

  CALL sp_reservation_update(
    p_company_code,
    p_reservation_id,
    p_status,
    p_source,
    p_id_ota_account,
    p_room_code,
    p_check_in_date,
    p_check_out_date,
    p_adults,
    p_children,
    p_notes_internal,
    p_notes_guest,
    p_actor_user_id
  );

  IF p_reservation_code IS NOT NULL AND TRIM(p_reservation_code) <> '' THEN
    SELECT id_company
      INTO v_company_id
    FROM company
    WHERE code = p_company_code
    LIMIT 1;

    UPDATE reservation r
    JOIN property p ON p.id_property = r.id_property
       SET r.code = UPPER(TRIM(p_reservation_code)),
           r.updated_at = NOW()
     WHERE r.id_reservation = p_reservation_id
       AND p.id_company = v_company_id
       AND r.deleted_at IS NULL;
  END IF;
END $$

DELIMITER ;
