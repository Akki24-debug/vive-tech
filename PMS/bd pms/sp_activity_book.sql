DELIMITER $$

DROP PROCEDURE IF EXISTS sp_activity_book $$
CREATE PROCEDURE sp_activity_book
(
  IN p_company_id     BIGINT,
  IN p_activity_id    BIGINT,
  IN p_reservation_id BIGINT,
  IN p_scheduled_at   DATETIME,
  IN p_num_adults     INT,
  IN p_num_children   INT,
  IN p_price_cents    INT,
  IN p_currency       VARCHAR(10),
  IN p_status         VARCHAR(32)
)
proc:BEGIN
  DECLARE v_activity BIGINT;
  DECLARE v_reservation BIGINT;
  DECLARE v_price INT;
  DECLARE v_currency VARCHAR(10);
  DECLARE v_created_by BIGINT;

  IF p_company_id IS NULL OR p_company_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company id is required';
  END IF;
  IF p_activity_id IS NULL OR p_activity_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Activity id is required';
  END IF;
  IF p_reservation_id IS NULL OR p_reservation_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation id is required';
  END IF;
  IF p_scheduled_at IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Scheduled date is required';
  END IF;

  SELECT id_activity, base_price_cents, currency
    INTO v_activity, v_price, v_currency
  FROM activity
  WHERE id_activity = p_activity_id
    AND id_company = p_company_id
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_activity IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown activity';
  END IF;

  SELECT r.id_reservation
    INTO v_reservation
  FROM reservation r
  JOIN property p ON p.id_property = r.id_property
  WHERE r.id_reservation = p_reservation_id
    AND p.id_company = p_company_id
    AND r.deleted_at IS NULL
  LIMIT 1;

  IF v_reservation IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown reservation for company';
  END IF;

  SET v_price = COALESCE(p_price_cents, v_price, 0);
  SET v_currency = COALESCE(NULLIF(p_currency, ''), v_currency, 'MXN');
  SELECT MIN(u.id_user)
    INTO v_created_by
  FROM app_user u
  WHERE u.id_company = p_company_id
    AND u.deleted_at IS NULL;

  INSERT INTO activity_booking
    (id_activity, id_reservation, scheduled_at, status, num_adults, num_children, price_cents, currency, created_by, created_at, updated_at)
  VALUES
    (v_activity, v_reservation, p_scheduled_at, COALESCE(NULLIF(p_status, ''), 'confirmed'),
     COALESCE(p_num_adults, 0), COALESCE(p_num_children, 0), v_price, v_currency, v_created_by, NOW(), NOW());

  INSERT INTO activity_booking_reservation
    (id_booking, id_reservation, is_active, deleted_at, created_at, created_by, updated_at)
  VALUES
    (LAST_INSERT_ID(), v_reservation, 1, NULL, NOW(), NULL, NOW())
  ON DUPLICATE KEY UPDATE
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  SELECT
    b.id_booking,
    b.scheduled_at,
    b.status,
    b.num_adults,
    b.num_children,
    b.price_cents,
    b.currency,
    a.code AS activity_code,
    a.name AS activity_name,
    r.code AS reservation_code
  FROM activity_booking b
  JOIN activity a ON a.id_activity = b.id_activity
  JOIN reservation r ON r.id_reservation = b.id_reservation
  WHERE b.id_booking = LAST_INSERT_ID();
END $$

DELIMITER ;
