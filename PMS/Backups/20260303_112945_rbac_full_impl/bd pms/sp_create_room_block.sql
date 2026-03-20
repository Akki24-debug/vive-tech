DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_create_room_block` $$
CREATE PROCEDURE `sp_create_room_block` (
  IN p_property_code VARCHAR(100),
  IN p_room_code     VARCHAR(100),
  IN p_check_in      VARCHAR(32),
  IN p_check_out     VARCHAR(32),
  IN p_notes         TEXT,
  IN p_actor_user    BIGINT
)
proc:BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_room BIGINT;
  DECLARE v_check_in_text VARCHAR(32);
  DECLARE v_check_out_text VARCHAR(32);
  DECLARE v_check_in DATE;
  DECLARE v_check_out DATE;
  DECLARE v_code VARCHAR(32);
  DECLARE v_actor BIGINT;
  DECLARE v_existing_block BIGINT DEFAULT NULL;
  DECLARE v_res_overlap INT DEFAULT 0;
  DECLARE v_block_overlap INT DEFAULT 0;

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_room_code IS NULL OR p_room_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room code is required';
  END IF;

  SET v_check_in_text = TRIM(COALESCE(p_check_in, ''));
  SET v_check_out_text = TRIM(COALESCE(p_check_out, ''));

  IF v_check_in_text = '' OR v_check_out_text = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Start and end dates are required';
  END IF;

  SET v_check_in = COALESCE(
    STR_TO_DATE(v_check_in_text, '%Y-%m-%d'),
    STR_TO_DATE(v_check_in_text, '%d/%m/%Y'),
    STR_TO_DATE(v_check_in_text, '%m/%d/%Y'),
    STR_TO_DATE(v_check_in_text, '%d-%m-%Y'),
    STR_TO_DATE(v_check_in_text, '%m-%d-%Y'),
    STR_TO_DATE(v_check_in_text, '%Y/%m/%d')
  );

  SET v_check_out = COALESCE(
    STR_TO_DATE(v_check_out_text, '%Y-%m-%d'),
    STR_TO_DATE(v_check_out_text, '%d/%m/%Y'),
    STR_TO_DATE(v_check_out_text, '%m/%d/%Y'),
    STR_TO_DATE(v_check_out_text, '%d-%m-%Y'),
    STR_TO_DATE(v_check_out_text, '%m-%d-%Y'),
    STR_TO_DATE(v_check_out_text, '%Y/%m/%d')
  );

  IF v_check_in IS NULL OR v_check_out IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid date format (use YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY, etc.)';
  END IF;
  IF v_check_out <= v_check_in THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Check-out must be after check-in';
  END IF;

  SELECT id_property
    INTO v_id_property
  FROM property
  WHERE code = p_property_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  SELECT id_room
    INTO v_id_room
  FROM room
  WHERE id_property = v_id_property
    AND code = p_room_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_room IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown room code for that property';
  END IF;

  IF p_actor_user IS NOT NULL AND p_actor_user <> 0 THEN
    SELECT id_user INTO v_actor FROM app_user WHERE id_user = p_actor_user LIMIT 1;
  END IF;

  SELECT id_room_block
    INTO v_existing_block
  FROM room_block
  WHERE id_room = v_id_room
    AND start_date = v_check_in
    AND end_date = v_check_out
    AND deleted_at IS NULL
    AND is_active = 1
  ORDER BY id_room_block DESC
  LIMIT 1;

  IF v_existing_block IS NOT NULL THEN
    UPDATE room_block
       SET description = COALESCE(NULLIF(p_notes, ''), description),
           id_user = COALESCE(v_actor, id_user),
           updated_at = NOW()
     WHERE id_room_block = v_existing_block;

    SELECT
      rb.id_room_block,
      rb.code,
      rb.description,
      rb.start_date,
      rb.end_date,
      rb.is_active,
      rb.deleted_at,
      rb.created_at,
      rb.updated_at,
      rb.id_room,
      rm.code AS room_code,
      rm.name AS room_name,
      rb.id_property,
      pr.code AS property_code,
      pr.name AS property_name,
      rb.id_user
    FROM room_block rb
    JOIN room rm ON rm.id_room = rb.id_room
    JOIN property pr ON pr.id_property = rb.id_property
    WHERE rb.id_room_block = v_existing_block;
    LEAVE proc;
  END IF;

  SELECT COUNT(*)
    INTO v_res_overlap
  FROM reservation
  WHERE id_room = v_id_room
    AND deleted_at IS NULL
    AND COALESCE(status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
    AND check_in_date < v_check_out
    AND check_out_date > v_check_in;

  IF v_res_overlap > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room already has reservations for those dates';
  END IF;

  SELECT COUNT(*)
    INTO v_block_overlap
  FROM room_block
  WHERE id_room = v_id_room
    AND deleted_at IS NULL
    AND is_active = 1
    AND start_date < v_check_out
    AND end_date > v_check_in;

  IF v_block_overlap > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room already has blocks for those dates';
  END IF;

  SET v_code = CONCAT('BLK-', UPPER(HEX(FLOOR(RAND() * 9999999))));

  INSERT INTO room_block (
    id_room,
    id_property,
    id_user,
    code,
    description,
    start_date,
    end_date,
    is_active,
    created_at,
    updated_at
  ) VALUES (
    v_id_room,
    v_id_property,
    v_actor,
    v_code,
    NULLIF(p_notes, ''),
    v_check_in,
    v_check_out,
    1,
    NOW(),
    NOW()
  );

  SELECT
    rb.id_room_block,
    rb.code,
    rb.description,
    rb.start_date,
    rb.end_date,
    rb.is_active,
    rb.deleted_at,
    rb.created_at,
    rb.updated_at,
    rb.id_room,
    rm.code AS room_code,
    rm.name AS room_name,
    rb.id_property,
    pr.code AS property_code,
    pr.name AS property_name,
    rb.id_user
  FROM room_block rb
  JOIN room rm ON rm.id_room = rb.id_room
  JOIN property pr ON pr.id_property = rb.id_property
  WHERE rb.id_room_block = LAST_INSERT_ID();
END $$

DELIMITER ;
