DELIMITER $$

DROP PROCEDURE IF EXISTS sp_activity_booking_upsert $$
CREATE PROCEDURE sp_activity_booking_upsert
(
  IN p_company_id            BIGINT,
  IN p_booking_id            BIGINT,
  IN p_activity_id           BIGINT,
  IN p_reservation_ids_csv   TEXT,
  IN p_scheduled_at          DATETIME,
  IN p_num_adults            INT,
  IN p_num_children          INT,
  IN p_price_cents           INT,
  IN p_currency              VARCHAR(10),
  IN p_status                VARCHAR(32),
  IN p_notes                 TEXT,
  IN p_user_id               BIGINT,
  IN p_action                VARCHAR(16) -- save | cancel | delete
)
proc:BEGIN
  DECLARE v_action VARCHAR(16);
  DECLARE v_booking BIGINT;
  DECLARE v_activity BIGINT;
  DECLARE v_price INT;
  DECLARE v_currency VARCHAR(10);
  DECLARE v_status VARCHAR(32);
  DECLARE v_user BIGINT;
  DECLARE v_primary_reservation BIGINT;
  DECLARE v_valid_res_count INT DEFAULT 0;
  DECLARE v_csv LONGTEXT;
  DECLARE v_token VARCHAR(64);
  DECLARE v_pos INT;

  IF p_company_id IS NULL OR p_company_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company id is required';
  END IF;

  SET v_action = LOWER(COALESCE(NULLIF(TRIM(p_action), ''), 'save'));
  IF v_action NOT IN ('save', 'cancel', 'delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported activity booking action';
  END IF;

  SET v_user = NULLIF(p_user_id, 0);
  IF v_user IS NULL THEN
    SELECT MIN(u.id_user)
      INTO v_user
    FROM app_user u
    WHERE u.id_company = p_company_id
      AND u.deleted_at IS NULL;
  END IF;

  IF p_booking_id IS NOT NULL AND p_booking_id > 0 THEN
    SELECT b.id_booking
      INTO v_booking
    FROM activity_booking b
    JOIN activity a
      ON a.id_activity = b.id_activity
    WHERE b.id_booking = p_booking_id
      AND b.deleted_at IS NULL
      AND a.id_company = p_company_id
      AND a.deleted_at IS NULL
    LIMIT 1;
  ELSE
    SET v_booking = NULL;
  END IF;

  IF v_action IN ('cancel', 'delete') AND v_booking IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Booking not found for company';
  END IF;

  IF v_action = 'delete' THEN
    UPDATE activity_booking
       SET deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_booking = v_booking;

    UPDATE activity_booking_reservation
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_booking = v_booking
       AND deleted_at IS NULL;

    SELECT
      v_booking AS id_booking,
      'deleted' AS action,
      'ok' AS status;
    LEAVE proc;
  END IF;

  IF v_action = 'cancel' THEN
    UPDATE activity_booking
       SET status = 'cancelled',
           notes = TRIM(CONCAT_WS(' | ', NULLIF(notes, ''), NULLIF(TRIM(p_notes), ''))),
           updated_at = NOW()
     WHERE id_booking = v_booking;

    SELECT
      b.id_booking,
      b.status,
      b.scheduled_at,
      b.num_adults,
      b.num_children,
      b.price_cents,
      b.currency
    FROM activity_booking b
    WHERE b.id_booking = v_booking
    LIMIT 1;
    LEAVE proc;
  END IF;

  IF p_activity_id IS NULL OR p_activity_id = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Activity id is required';
  END IF;
  IF p_scheduled_at IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Scheduled date is required';
  END IF;

  SELECT a.id_activity, a.base_price_cents, a.currency
    INTO v_activity, v_price, v_currency
  FROM activity a
  WHERE a.id_activity = p_activity_id
    AND a.id_company = p_company_id
    AND a.deleted_at IS NULL
  LIMIT 1;

  IF v_activity IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown activity for company';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_activity_booking_res_ids;
  CREATE TEMPORARY TABLE tmp_activity_booking_res_ids (
    seq INT NOT NULL AUTO_INCREMENT,
    id_reservation BIGINT NOT NULL,
    PRIMARY KEY (seq),
    UNIQUE KEY uk_tmp_activity_booking_res (id_reservation)
  ) ENGINE = MEMORY;

  SET v_csv = COALESCE(p_reservation_ids_csv, '');
  SET v_csv = REPLACE(v_csv, ';', ',');
  SET v_csv = REPLACE(v_csv, '|', ',');
  SET v_csv = REPLACE(v_csv, ' ', '');

  csv_loop: WHILE v_csv <> '' DO
    SET v_pos = LOCATE(',', v_csv);
    IF v_pos = 0 THEN
      SET v_token = v_csv;
      SET v_csv = '';
    ELSE
      SET v_token = SUBSTRING(v_csv, 1, v_pos - 1);
      SET v_csv = SUBSTRING(v_csv, v_pos + 1);
    END IF;

    IF v_token IS NOT NULL AND v_token <> '' AND v_token REGEXP '^[0-9]+$' THEN
      INSERT IGNORE INTO tmp_activity_booking_res_ids (id_reservation)
      VALUES (CAST(v_token AS UNSIGNED));
    END IF;
  END WHILE csv_loop;

  DELETE t
  FROM tmp_activity_booking_res_ids t
  LEFT JOIN reservation r
    ON r.id_reservation = t.id_reservation
   AND r.deleted_at IS NULL
  LEFT JOIN property pr
    ON pr.id_property = r.id_property
  WHERE r.id_reservation IS NULL
     OR pr.id_company <> p_company_id
     OR pr.deleted_at IS NOT NULL;

  SELECT COUNT(*)
    INTO v_valid_res_count
  FROM tmp_activity_booking_res_ids;

  IF v_valid_res_count = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'At least one valid reservation is required';
  END IF;

  SELECT t.id_reservation
    INTO v_primary_reservation
  FROM tmp_activity_booking_res_ids t
  ORDER BY t.seq
  LIMIT 1;

  SET v_price = COALESCE(p_price_cents, v_price, 0);
  SET v_currency = COALESCE(NULLIF(TRIM(p_currency), ''), v_currency, 'MXN');
  SET v_status = COALESCE(NULLIF(TRIM(p_status), ''), 'confirmed');

  IF v_booking IS NULL THEN
    INSERT INTO activity_booking
      (id_activity, id_reservation, scheduled_at, status, num_adults, num_children, price_cents, currency, notes, created_by, created_at, updated_at)
    VALUES
      (v_activity, v_primary_reservation, p_scheduled_at, v_status, COALESCE(p_num_adults, 0), COALESCE(p_num_children, 0), v_price, v_currency, NULLIF(p_notes, ''), v_user, NOW(), NOW());
    SET v_booking = LAST_INSERT_ID();
  ELSE
    UPDATE activity_booking
       SET id_activity = v_activity,
           id_reservation = v_primary_reservation,
           scheduled_at = p_scheduled_at,
           status = v_status,
           num_adults = COALESCE(p_num_adults, num_adults),
           num_children = COALESCE(p_num_children, num_children),
           price_cents = v_price,
           currency = v_currency,
           notes = NULLIF(p_notes, ''),
           updated_at = NOW()
     WHERE id_booking = v_booking;
  END IF;

  UPDATE activity_booking_reservation abr
  LEFT JOIN tmp_activity_booking_res_ids t
    ON t.id_reservation = abr.id_reservation
  SET abr.is_active = 0,
      abr.deleted_at = NOW(),
      abr.updated_at = NOW()
  WHERE abr.id_booking = v_booking
    AND abr.deleted_at IS NULL
    AND COALESCE(abr.is_active, 1) = 1
    AND t.id_reservation IS NULL;

  INSERT INTO activity_booking_reservation
    (id_booking, id_reservation, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT
    v_booking,
    t.id_reservation,
    1,
    NULL,
    NOW(),
    v_user,
    NOW()
  FROM tmp_activity_booking_res_ids t
  ON DUPLICATE KEY UPDATE
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  SELECT
    b.id_booking,
    b.id_activity,
    b.id_reservation,
    b.scheduled_at,
    b.status,
    b.num_adults,
    b.num_children,
    b.price_cents,
    b.currency,
    b.notes,
    COALESCE(linked.linked_reservation_count, 0) AS linked_reservation_count,
    COALESCE(linked.linked_reservation_ids_csv, '') AS linked_reservation_ids_csv
  FROM activity_booking b
  LEFT JOIN (
    SELECT
      abr.id_booking,
      COUNT(*) AS linked_reservation_count,
      GROUP_CONCAT(CAST(abr.id_reservation AS CHAR) ORDER BY abr.id_reservation SEPARATOR ',') AS linked_reservation_ids_csv
    FROM activity_booking_reservation abr
    WHERE abr.deleted_at IS NULL
      AND COALESCE(abr.is_active, 1) = 1
    GROUP BY abr.id_booking
  ) linked ON linked.id_booking = b.id_booking
  WHERE b.id_booking = v_booking
  LIMIT 1;
END $$

DELIMITER ;
