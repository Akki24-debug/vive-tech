DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_get_room_block` $$
CREATE PROCEDURE `sp_get_room_block` (
  IN p_room_block_id BIGINT
)
BEGIN
  IF p_room_block_id IS NULL OR p_room_block_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Block id is required';
  END IF;

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
    rm.id_room,
    rm.code AS room_code,
    rm.name AS room_name,
    pr.id_property,
    pr.code AS property_code,
    pr.name AS property_name
  FROM room_block rb
  JOIN room rm ON rm.id_room = rb.id_room
  JOIN property pr ON pr.id_property = rb.id_property
  WHERE rb.id_room_block = p_room_block_id
    AND rb.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
