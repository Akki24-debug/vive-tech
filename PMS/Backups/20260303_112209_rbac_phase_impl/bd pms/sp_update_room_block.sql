DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_update_room_block` $$
CREATE PROCEDURE `sp_update_room_block` (
  IN p_room_block_id BIGINT,
  IN p_property_code VARCHAR(100),
  IN p_room_code     VARCHAR(100),
  IN p_start_date    VARCHAR(32),
  IN p_end_date      VARCHAR(32),
  IN p_description   TEXT,
  IN p_actor_user    BIGINT
)
proc:BEGIN
  DECLARE v_id_room BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_current_property_code VARCHAR(100);
  DECLARE v_current_room_code VARCHAR(100);
  DECLARE v_target_property_code VARCHAR(100);
  DECLARE v_target_room_code VARCHAR(100);
  DECLARE v_new_property BIGINT;
  DECLARE v_new_room BIGINT;
  DECLARE v_check_in DATE;
  DECLARE v_check_out DATE;
  DECLARE v_actor BIGINT;
  DECLARE v_overlap_res INT DEFAULT 0;
  DECLARE v_overlap_block INT DEFAULT 0;
  DECLARE v_end_exclusive DATE;
  DECLARE v_start_text VARCHAR(32);
  DECLARE v_end_text VARCHAR(32);

  IF p_room_block_id IS NULL OR p_room_block_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Block id is required';
  END IF;

  SELECT rb.id_room, rb.id_property, pr.code, rm.code
    INTO v_id_room, v_id_property, v_current_property_code, v_current_room_code
  FROM room_block rb
  JOIN property pr ON pr.id_property = rb.id_property
  JOIN room rm ON rm.id_room = rb.id_room
  WHERE rb.id_room_block = p_room_block_id
    AND rb.deleted_at IS NULL
  LIMIT 1;

  IF v_id_room IS NULL OR v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Block not found';
  END IF;

  SET v_current_property_code = UPPER(v_current_property_code);
  SET v_current_room_code = UPPER(v_current_room_code);

  SET v_target_property_code = NULLIF(TRIM(p_property_code), '');
  IF v_target_property_code IS NULL THEN
    SET v_target_property_code = v_current_property_code;
  ELSE
    SET v_target_property_code = UPPER(v_target_property_code);
  END IF;

  SELECT id_property
    INTO v_new_property
  FROM property
  WHERE code = v_target_property_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_new_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  SET v_target_room_code = NULLIF(TRIM(p_room_code), '');
  IF v_target_room_code IS NULL THEN
    SET v_target_room_code = v_current_room_code;
  ELSE
    SET v_target_room_code = UPPER(v_target_room_code);
  END IF;

  SELECT id_room
    INTO v_new_room
  FROM room
  WHERE code = v_target_room_code
    AND id_property = v_new_property
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_new_room IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown room code for that property';
  END IF;

  SET v_start_text = TRIM(COALESCE(p_start_date, ''));
  SET v_end_text = TRIM(COALESCE(p_end_date, ''));

  SET v_check_in = COALESCE(
    STR_TO_DATE(v_start_text, '%Y-%m-%d'),
    STR_TO_DATE(v_start_text, '%d/%m/%Y'),
    STR_TO_DATE(v_start_text, '%m/%d/%Y'),
    STR_TO_DATE(v_start_text, '%d-%m-%Y'),
    STR_TO_DATE(v_start_text, '%m-%d-%Y'),
    STR_TO_DATE(v_start_text, '%Y/%m/%d')
  );

  SET v_check_out = COALESCE(
    STR_TO_DATE(v_end_text, '%Y-%m-%d'),
    STR_TO_DATE(v_end_text, '%d/%m/%Y'),
    STR_TO_DATE(v_end_text, '%m/%d/%Y'),
    STR_TO_DATE(v_end_text, '%d-%m-%Y'),
    STR_TO_DATE(v_end_text, '%m-%d-%Y'),
    STR_TO_DATE(v_end_text, '%Y/%m/%d')
  );

  IF v_check_in IS NULL OR v_check_out IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid date format';
  END IF;

  IF v_check_out <= v_check_in THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'End date must be after start date';
  END IF;

  SET v_end_exclusive = DATE_ADD(v_check_out, INTERVAL 1 DAY);

  IF p_actor_user IS NOT NULL AND p_actor_user <> 0 THEN
    SELECT id_user INTO v_actor FROM app_user WHERE id_user = p_actor_user LIMIT 1;
  END IF;

  SELECT COUNT(*)
    INTO v_overlap_res
  FROM reservation
  WHERE id_room = v_new_room
    AND deleted_at IS NULL
    AND COALESCE(status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
    AND check_in_date < v_end_exclusive
    AND check_out_date > v_check_in;

  IF v_overlap_res > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room already has reservations for those dates';
  END IF;

  SELECT COUNT(*)
    INTO v_overlap_block
  FROM room_block
  WHERE id_room = v_new_room
    AND deleted_at IS NULL
    AND is_active = 1
    AND id_room_block <> p_room_block_id
    AND start_date < v_end_exclusive
    AND DATE_ADD(end_date, INTERVAL 1 DAY) > v_check_in;

  IF v_overlap_block > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room already has blocks for those dates';
  END IF;

  UPDATE room_block
     SET id_property = v_new_property,
         id_room = v_new_room,
         start_date = v_check_in,
         end_date = v_check_out,
         description = COALESCE(NULLIF(p_description, ''), description),
         id_user = COALESCE(v_actor, id_user),
         updated_at = NOW()
   WHERE id_room_block = p_room_block_id;

  SELECT
    rb.id_room_block,
    rb.code,
    rb.description,
    rb.start_date,
    rb.end_date,
    rb.is_active,
    rb.created_at,
    rb.updated_at,
    rb.id_user,
    rb.id_room,
    rm.code AS room_code,
    rm.name AS room_name,
    rb.id_property,
    pr.code AS property_code,
    pr.name AS property_name
  FROM room_block rb
  JOIN room rm ON rm.id_room = rb.id_room
  JOIN property pr ON pr.id_property = rb.id_property
  WHERE rb.id_room_block = p_room_block_id;
END $$

DELIMITER ;
