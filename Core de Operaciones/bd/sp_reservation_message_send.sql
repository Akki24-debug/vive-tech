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
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;
  DECLARE v_id_template BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();
  DECLARE v_channel VARCHAR(32) DEFAULT 'whatsapp';
  DECLARE v_is_trackable TINYINT DEFAULT 0;
  DECLARE v_is_required TINYINT DEFAULT 0;
  DECLARE v_log_id BIGINT DEFAULT NULL;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_reservation_id IS NULL OR p_reservation_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation id is required';
  END IF;
  IF p_message_template_id IS NULL OR p_message_template_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template id is required';
  END IF;
  IF p_sent_by IS NULL OR p_sent_by <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Actor user id is required';
  END IF;
  IF p_message_title IS NULL OR TRIM(p_message_title) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Message title is required';
  END IF;
  IF p_message_body IS NULL OR TRIM(p_message_body) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Message body is required';
  END IF;

  SELECT id_company
    INTO v_id_company
  FROM company
  WHERE code = p_company_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_company IS NULL OR v_id_company <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SELECT r.id_property, p.code
    INTO v_id_property, v_property_code
  FROM reservation r
  JOIN property p
    ON p.id_property = r.id_property
   AND p.id_company = v_id_company
   AND p.deleted_at IS NULL
  WHERE r.id_reservation = p_reservation_id
    AND r.deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL OR v_id_property <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation not found';
  END IF;

  CALL sp_authz_assert(
    p_company_code,
    p_sent_by,
    'messages.send',
    v_property_code,
    NULL
  );

  SELECT
    mt.id_message_template,
    COALESCE(mt.is_trackable, 0),
    COALESCE(mt.is_required, 0),
    LOWER(TRIM(COALESCE(NULLIF(mt.channel, ''), 'whatsapp')))
    INTO
      v_id_template,
      v_is_trackable,
      v_is_required,
      v_channel
  FROM message_template mt
  WHERE mt.id_message_template = p_message_template_id
    AND mt.id_company = v_id_company
    AND mt.deleted_at IS NULL
    AND mt.is_active = 1
    AND (
      mt.id_property IS NULL
      OR mt.id_property = v_id_property
    )
  LIMIT 1;

  IF v_id_template IS NULL OR v_id_template <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Template not found for reservation';
  END IF;

  SET v_channel = LOWER(TRIM(COALESCE(NULLIF(p_channel, ''), v_channel, 'whatsapp')));
  IF v_channel <> 'manual' AND TRIM(COALESCE(p_sent_to_phone, '')) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Destination phone is required';
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
    NULLIF(TRIM(COALESCE(p_sent_to_phone, '')), ''),
    TRIM(p_message_title),
    p_message_body,
    v_channel,
    v_now
  );

  SET v_log_id = LAST_INSERT_ID();

  IF v_is_trackable = 1 OR v_is_required = 1 THEN
    INSERT INTO reservation_message_status (
      id_reservation,
      id_message_template,
      tracking_status,
      is_trackable,
      is_required,
      last_sent_at,
      last_sent_by,
      last_channel,
      last_phone,
      last_message_title,
      last_message_body,
      last_id_reservation_message_log,
      created_at,
      created_by,
      updated_at,
      updated_by
    ) VALUES (
      p_reservation_id,
      p_message_template_id,
      'sent',
      v_is_trackable,
      v_is_required,
      v_now,
      p_sent_by,
      v_channel,
      NULLIF(TRIM(COALESCE(p_sent_to_phone, '')), ''),
      TRIM(p_message_title),
      p_message_body,
      v_log_id,
      v_now,
      p_sent_by,
      v_now,
      p_sent_by
    )
    ON DUPLICATE KEY UPDATE
      tracking_status = 'sent',
      is_trackable = VALUES(is_trackable),
      is_required = VALUES(is_required),
      last_sent_at = VALUES(last_sent_at),
      last_sent_by = VALUES(last_sent_by),
      last_channel = VALUES(last_channel),
      last_phone = VALUES(last_phone),
      last_message_title = VALUES(last_message_title),
      last_message_body = VALUES(last_message_body),
      last_id_reservation_message_log = VALUES(last_id_reservation_message_log),
      updated_at = VALUES(updated_at),
      updated_by = VALUES(updated_by);
  END IF;

  SELECT
    rml.id_reservation_message_log,
    rml.id_reservation,
    rml.id_message_template,
    rml.sent_at,
    rml.sent_by,
    rml.sent_to_phone,
    rml.message_title,
    rml.message_body,
    rml.channel
  FROM reservation_message_log rml
  WHERE rml.id_reservation_message_log = v_log_id
  LIMIT 1;
END $$

DELIMITER ;
