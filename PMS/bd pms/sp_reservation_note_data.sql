DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reservation_note_data` $$
CREATE PROCEDURE `sp_reservation_note_data` (
  IN p_company_code VARCHAR(100),
  IN p_id_reservation BIGINT,
  IN p_note_type VARCHAR(16),
  IN p_show_inactive TINYINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_reservation_id BIGINT;
  DECLARE v_note_type VARCHAR(16);

  IF p_id_reservation IS NULL OR p_id_reservation = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation id is required';
  END IF;
  SET v_reservation_id = p_id_reservation;

  SET v_note_type = LOWER(TRIM(COALESCE(p_note_type,'')));
  IF v_note_type NOT IN ('internal','guest','system') THEN
    SET v_note_type = '';
  END IF;

  SELECT
    rn.id_reservation_note,
    rn.id_reservation,
    rn.note_type,
    rn.note_text,
    rn.is_active,
    rn.deleted_at,
    rn.created_at,
    rn.created_by,
    rn.updated_at
  FROM reservation_note rn
  WHERE rn.id_reservation = v_reservation_id
    AND (v_note_type = '' OR rn.note_type = v_note_type)
    AND (
      p_show_inactive IS NOT NULL AND p_show_inactive <> 0
      OR (rn.deleted_at IS NULL AND rn.is_active = 1)
    )
  ORDER BY rn.created_at DESC, rn.id_reservation_note DESC;
END $$

DELIMITER ;
