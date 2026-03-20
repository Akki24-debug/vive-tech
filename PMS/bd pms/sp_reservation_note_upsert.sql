DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reservation_note_upsert` $$
CREATE PROCEDURE `sp_reservation_note_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_reservation_note BIGINT,
  IN p_id_reservation BIGINT,
  IN p_note_type VARCHAR(16),
  IN p_note_text TEXT,
  IN p_is_active TINYINT,
  IN p_company_code VARCHAR(100),
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_reservation_id BIGINT;
  DECLARE v_note_id BIGINT;
  DECLARE v_note_type VARCHAR(16);

  IF p_action IS NULL OR p_action = '' THEN
    SET p_action = 'create';
  END IF;
  IF p_action NOT IN ('create','update','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported action';
  END IF;

  IF p_company_code IS NOT NULL AND p_company_code <> '' THEN
    SELECT id_company INTO v_company_id
    FROM company
    WHERE code = p_company_code
    LIMIT 1;
  END IF;

  SET v_note_type = LOWER(TRIM(COALESCE(p_note_type,'internal')));
  IF v_note_type NOT IN ('internal','guest','system') THEN
    SET v_note_type = 'internal';
  END IF;

  IF p_action = 'create' THEN
    IF p_id_reservation IS NULL OR p_id_reservation = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation id is required';
    END IF;
    IF p_note_text IS NULL OR TRIM(p_note_text) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Note text is required';
    END IF;

    IF v_company_id IS NOT NULL THEN
      SELECT r.id_reservation INTO v_reservation_id
      FROM reservation r
      JOIN property p ON p.id_property = r.id_property
      WHERE r.id_reservation = p_id_reservation
        AND r.deleted_at IS NULL
        AND p.id_company = v_company_id
        AND p.deleted_at IS NULL
      LIMIT 1;
    END IF;
    IF v_reservation_id IS NULL THEN
      SELECT r.id_reservation INTO v_reservation_id
      FROM reservation r
      WHERE r.id_reservation = p_id_reservation
        AND r.deleted_at IS NULL
      LIMIT 1;
    END IF;
    IF v_reservation_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation not found';
    END IF;

    INSERT INTO reservation_note (
      id_reservation,
      note_type,
      note_text,
      is_active,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_reservation_id,
      v_note_type,
      p_note_text,
      COALESCE(p_is_active,1),
      NOW(),
      p_actor_user_id,
      NOW()
    );

    SET v_note_id = LAST_INSERT_ID();
    SET v_reservation_id = p_id_reservation;
  ELSE
    IF p_id_reservation_note IS NULL OR p_id_reservation_note = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Note id is required';
    END IF;

    IF v_company_id IS NOT NULL THEN
      SELECT rn.id_reservation, rn.id_reservation_note
        INTO v_reservation_id, v_note_id
      FROM reservation_note rn
      JOIN reservation r ON r.id_reservation = rn.id_reservation
      JOIN property p ON p.id_property = r.id_property
      WHERE rn.id_reservation_note = p_id_reservation_note
        AND p.id_company = v_company_id
      LIMIT 1;
    END IF;
    IF v_note_id IS NULL THEN
      SELECT rn.id_reservation, rn.id_reservation_note
        INTO v_reservation_id, v_note_id
      FROM reservation_note rn
      WHERE rn.id_reservation_note = p_id_reservation_note
      LIMIT 1;
    END IF;

    IF v_note_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Note not found';
    END IF;

    IF p_action = 'delete' THEN
      UPDATE reservation_note
         SET is_active = 0,
             deleted_at = NOW(),
             updated_at = NOW()
       WHERE id_reservation_note = v_note_id;
    ELSE
      UPDATE reservation_note
         SET note_type = v_note_type,
             note_text = COALESCE(NULLIF(p_note_text,''), note_text),
             is_active = COALESCE(p_is_active, is_active),
             deleted_at = NULL,
             updated_at = NOW()
       WHERE id_reservation_note = v_note_id;
    END IF;
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
  ORDER BY rn.created_at DESC, rn.id_reservation_note DESC;
END $$

DELIMITER ;
