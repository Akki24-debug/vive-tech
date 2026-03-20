DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reservation_message_send` $$
CREATE PROCEDURE `sp_reservation_message_send` (
  IN p_company_code VARCHAR(100),
  IN p_reservation_id BIGINT,
  IN p_message_template_id BIGINT,
  IN p_sent_by BIGINT,
  IN p_sent_to_phone VARCHAR(32),
  IN p_message_title VARCHAR(255),
  IN p_message_body TEXT,
  IN p_channel VARCHAR(32)
)
proc:BEGIN
  DECLARE v_id_company BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_id_template BIGINT;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_now DATETIME DEFAULT NOW();

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_reservation_id IS NULL OR p_reservation_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation id is required';
  END IF;
  IF p_message_template_id IS NULL OR p_message_template_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template id is required';
  END IF;
  IF p_message_title IS NULL OR p_message_title = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Message title is required';
  END IF;
  IF p_message_body IS NULL OR p_message_body = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Message body is required';
  END IF;

  SELECT id_company
    INTO v_id_company
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_id_company IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SELECT r.id_property
    INTO v_id_property
  FROM reservation r
  JOIN property p ON p.id_property = r.id_property
  WHERE r.id_reservation = p_reservation_id
    AND r.deleted_at IS NULL
    AND p.id_company = v_id_company
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation not found';
  END IF;

  SELECT id_message_template
    INTO v_id_template
  FROM message_template
  WHERE id_message_template = p_message_template_id
    AND id_company = v_id_company
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_template IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template not found';
  END IF;

  SELECT COUNT(*)
    INTO v_exists
  FROM reservation_message_log
  WHERE id_reservation = p_reservation_id
    AND id_message_template = p_message_template_id;

  IF v_exists > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Message already sent for reservation';
  END IF;

  INSERT INTO reservation_message_log (
    id_reservation,
    id_message_template,
    sent_at,
    sent_by,
    sent_to_phone,
    message_title,
    message_body,
    channel,
    created_at
  ) VALUES (
    p_reservation_id,
    p_message_template_id,
    v_now,
    p_sent_by,
    p_sent_to_phone,
    p_message_title,
    p_message_body,
    COALESCE(NULLIF(p_channel,''), 'whatsapp'),
    v_now
  );

  SELECT
    rml.id_reservation_message_log,
    rml.id_reservation,
    rml.id_message_template,
    rml.sent_at,
    rml.sent_to_phone,
    rml.message_title,
    rml.message_body,
    rml.channel
  FROM reservation_message_log rml
  WHERE rml.id_reservation_message_log = LAST_INSERT_ID()
  LIMIT 1;
END $$

DELIMITER ;
