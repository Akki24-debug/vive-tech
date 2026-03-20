/* ============================================================================
   IMPORT MASIVO DE RESERVAS RAPIDAS (SIN FOLIO / SIN CARGOS)
   Formato de entrada:
   id_property, id_room, guest_name, amount_raw, check_in_raw, check_out_raw, origin_raw

   Reglas:
   - NO usa sp_create_reservation (para no crear folio/line_item/cargos).
   - Crea solo: guest + reservation.
   - Reserva queda en status "apartado" (incompleto).
   - amount_raw se guarda como nota en reservation_note.
   - Origen:
     1) match origin_raw vs ota_account.ota_name
     2) si no hay match OTA, match vs reservation_source_catalog.source_name
     3) si no hay match, usa "Mapas"
   - Valida empalmes con reservation y room_block.
   - Si una fila falla o se empalma, se registra en log y continua.
   ============================================================================ */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;

DROP TABLE IF EXISTS tmp_reservation_import_input;
CREATE TABLE tmp_reservation_import_input (
  id_import BIGINT NOT NULL AUTO_INCREMENT,
  id_property BIGINT NOT NULL,
  id_room BIGINT NOT NULL,
  guest_name VARCHAR(255) NOT NULL,
  amount_raw VARCHAR(64) DEFAULT NULL,
  check_in_raw VARCHAR(20) NOT NULL,
  check_out_raw VARCHAR(20) NOT NULL,
  origin_raw VARCHAR(120) NOT NULL,
  PRIMARY KEY (id_import),
  KEY idx_input_property_room (id_property, id_room),
  KEY idx_input_dates (check_in_raw, check_out_raw)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS tmp_reservation_import_log;
CREATE TABLE tmp_reservation_import_log (
  id_log BIGINT NOT NULL AUTO_INCREMENT,
  id_import BIGINT NOT NULL,
  result_status ENUM('inserted','skipped','error') NOT NULL,
  reason VARCHAR(500) DEFAULT NULL,
  id_property BIGINT DEFAULT NULL,
  id_room BIGINT DEFAULT NULL,
  resolved_room_id BIGINT DEFAULT NULL,
  guest_name VARCHAR(255) DEFAULT NULL,
  check_in_date DATE DEFAULT NULL,
  check_out_date DATE DEFAULT NULL,
  origin_raw VARCHAR(120) DEFAULT NULL,
  source_used VARCHAR(120) DEFAULT NULL,
  id_ota_account BIGINT DEFAULT NULL,
  id_reservation_source BIGINT DEFAULT NULL,
  total_override_cents INT DEFAULT NULL,
  id_reservation BIGINT DEFAULT NULL,
  reservation_code VARCHAR(120) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  KEY idx_log_import (id_import),
  KEY idx_log_status (result_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ============================================================================
   PEGA AQUI TUS DATOS
   IMPORTANTE:
   - amount_raw puede venir con "$" o vacio
   - fechas en YYYY-MM-DD
   ============================================================================ */
/* El dataset operativo se carga abajo en @raw_import y luego se ejecuta:
   CALL sp_load_reservation_import_input_from_tsv(@raw_import); */

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_load_reservation_import_input_from_tsv $$
CREATE PROCEDURE sp_load_reservation_import_input_from_tsv (IN p_raw LONGTEXT)
BEGIN
  DECLARE v_text LONGTEXT;
  DECLARE v_line LONGTEXT;
  DECLARE v_pos INT DEFAULT 0;
  DECLARE v_f1 VARCHAR(64);
  DECLARE v_f2 VARCHAR(64);
  DECLARE v_f3 VARCHAR(255);
  DECLARE v_f4 VARCHAR(64);
  DECLARE v_f5 VARCHAR(20);
  DECLARE v_f6 VARCHAR(20);
  DECLARE v_f7 VARCHAR(120);

  TRUNCATE TABLE tmp_reservation_import_input;

  SET v_text = REPLACE(REPLACE(COALESCE(p_raw, ''), '\r\n', '\n'), '\r', '\n');

  parse_loop: LOOP
    IF v_text IS NULL OR v_text = '' THEN
      LEAVE parse_loop;
    END IF;

    SET v_pos = LOCATE('\n', v_text);
    IF v_pos = 0 THEN
      SET v_line = v_text;
      SET v_text = '';
    ELSE
      SET v_line = SUBSTRING(v_text, 1, v_pos - 1);
      SET v_text = SUBSTRING(v_text, v_pos + 1);
    END IF;

    SET v_line = TRIM(COALESCE(v_line, ''));
    IF v_line = '' THEN
      ITERATE parse_loop;
    END IF;

    SET v_f1 = TRIM(SUBSTRING_INDEX(v_line, '\t', 1));
    SET v_f2 = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(v_line, '\t', 2), '\t', -1));
    SET v_f3 = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(v_line, '\t', 3), '\t', -1));
    SET v_f4 = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(v_line, '\t', 4), '\t', -1));
    SET v_f5 = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(v_line, '\t', 5), '\t', -1));
    SET v_f6 = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(v_line, '\t', 6), '\t', -1));
    SET v_f7 = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(v_line, '\t', 7), '\t', -1));

    IF v_f1 REGEXP '^[0-9]+$'
       AND v_f2 REGEXP '^[0-9]+$'
       AND v_f3 <> ''
       AND v_f5 <> ''
       AND v_f6 <> ''
       AND v_f7 <> '' THEN
      INSERT INTO tmp_reservation_import_input (
        id_property,
        id_room,
        guest_name,
        amount_raw,
        check_in_raw,
        check_out_raw,
        origin_raw
      ) VALUES (
        CAST(v_f1 AS UNSIGNED),
        CAST(v_f2 AS UNSIGNED),
        v_f3,
        NULLIF(v_f4, ''),
        v_f5,
        v_f6,
        v_f7
      );
    END IF;
  END LOOP;
END $$

DROP PROCEDURE IF EXISTS sp_import_reservations_quick_mass_by_room_id $$
CREATE PROCEDURE sp_import_reservations_quick_mass_by_room_id ()
BEGIN
  DECLARE done INT DEFAULT 0;

  DECLARE v_id_import BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_id_room BIGINT;
  DECLARE v_guest_name VARCHAR(255);
  DECLARE v_amount_raw VARCHAR(64);
  DECLARE v_check_in_raw VARCHAR(20);
  DECLARE v_check_out_raw VARCHAR(20);
  DECLARE v_origin_raw VARCHAR(120);

  DECLARE v_id_company BIGINT;
  DECLARE v_id_user BIGINT;
  DECLARE v_room_id_db BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_check_in DATE;
  DECLARE v_check_out DATE;
  DECLARE v_amount_clean VARCHAR(64);
  DECLARE v_total_override_cents INT;
  DECLARE v_source_input VARCHAR(120);
  DECLARE v_origin_norm VARCHAR(160);
  DECLARE v_source_norm VARCHAR(160);
  DECLARE v_id_ota_account BIGINT;
  DECLARE v_ota_name VARCHAR(150);
  DECLARE v_ota_platform VARCHAR(32);
  DECLARE v_id_reservation_source_match BIGINT;
  DECLARE v_source_related_name VARCHAR(120);
  DECLARE v_mapas_source_id BIGINT;
  DECLARE v_overlap_cnt INT DEFAULT 0;
  DECLARE v_block_overlap_cnt INT DEFAULT 0;
  DECLARE v_new_guest_id BIGINT;
  DECLARE v_note_text TEXT;
  DECLARE v_new_reservation_id BIGINT;
  DECLARE v_new_reservation_code VARCHAR(120);
  DECLARE v_reason VARCHAR(500);
  DECLARE v_status VARCHAR(16);
  DECLARE v_rows_input INT DEFAULT 0;
  DECLARE v_sql_error TINYINT DEFAULT 0;
  DECLARE v_sql_message TEXT DEFAULT '';

  DECLARE cur CURSOR FOR
    SELECT
      id_import,
      id_property,
      id_room,
      TRIM(COALESCE(guest_name, '')),
      TRIM(COALESCE(amount_raw, '')),
      TRIM(COALESCE(check_in_raw, '')),
      TRIM(COALESCE(check_out_raw, '')),
      TRIM(COALESCE(origin_raw, ''))
    FROM tmp_reservation_import_input
    ORDER BY id_import;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
  BEGIN
    SET v_sql_error = 1;
    GET DIAGNOSTICS CONDITION 1 v_sql_message = MESSAGE_TEXT;
  END;

  TRUNCATE TABLE tmp_reservation_import_log;

  SELECT COUNT(*) INTO v_rows_input
  FROM tmp_reservation_import_input;

  IF v_rows_input = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'tmp_reservation_import_input esta vacia.';
  END IF;

  OPEN cur;
  read_loop: LOOP
    SET done = 0;
    FETCH cur
      INTO v_id_import, v_id_property, v_id_room, v_guest_name, v_amount_raw, v_check_in_raw, v_check_out_raw, v_origin_raw;
    IF done = 1 THEN
      LEAVE read_loop;
    END IF;

    SET v_sql_error = 0;
    SET v_sql_message = '';
    SET v_reason = NULL;
    SET v_status = 'inserted';
    SET v_id_company = NULL;
    SET v_id_user = NULL;
    SET v_room_id_db = NULL;
    SET v_id_category = NULL;
    SET v_check_in = NULL;
    SET v_check_out = NULL;
    SET v_amount_clean = NULL;
    SET v_total_override_cents = NULL;
    SET v_source_input = NULL;
    SET v_origin_norm = NULL;
    SET v_source_norm = NULL;
    SET v_id_ota_account = NULL;
    SET v_ota_name = NULL;
    SET v_ota_platform = NULL;
    SET v_id_reservation_source_match = NULL;
    SET v_source_related_name = NULL;
    SET v_mapas_source_id = NULL;
    SET v_overlap_cnt = 0;
    SET v_block_overlap_cnt = 0;
    SET v_new_guest_id = NULL;
    SET v_note_text = NULL;
    SET v_new_reservation_id = NULL;
    SET v_new_reservation_code = NULL;

    row_proc: BEGIN
      SET v_check_in = STR_TO_DATE(v_check_in_raw, '%Y-%m-%d');
      SET v_check_out = STR_TO_DATE(v_check_out_raw, '%Y-%m-%d');
      IF v_check_in IS NULL OR v_check_out IS NULL OR v_check_out <= v_check_in THEN
        SET v_reason = CONCAT('fechas invalidas: ', v_check_in_raw, ' -> ', v_check_out_raw);
        LEAVE row_proc;
      END IF;

      IF v_guest_name IS NULL OR v_guest_name = '' THEN
        SET v_reason = 'guest_name vacio';
        LEAVE row_proc;
      END IF;

      SELECT p.id_company
        INTO v_id_company
      FROM property p
      WHERE p.id_property = v_id_property
        AND p.deleted_at IS NULL
      LIMIT 1;

      IF v_id_company IS NULL OR v_id_company <= 0 THEN
        SET v_reason = CONCAT('propiedad no valida: ', COALESCE(v_id_property, 0));
        LEAVE row_proc;
      END IF;

      SELECT r.id_room, r.id_category
        INTO v_room_id_db, v_id_category
      FROM room r
      WHERE r.id_room = v_id_room
        AND r.id_property = v_id_property
        AND r.deleted_at IS NULL
        AND COALESCE(r.is_active, 1) = 1
      LIMIT 1;

      IF v_room_id_db IS NULL OR v_room_id_db <= 0 THEN
        SELECT r.id_room, r.id_category
          INTO v_room_id_db, v_id_category
        FROM room r
        WHERE r.id_property = v_id_property
          AND r.deleted_at IS NULL
          AND COALESCE(r.is_active, 1) = 1
          AND UPPER(TRIM(COALESCE(r.code, ''))) = UPPER(TRIM(CAST(v_id_room AS CHAR)))
        LIMIT 1;
      END IF;

      IF v_room_id_db IS NULL OR v_room_id_db <= 0 THEN
        SET v_reason = CONCAT('cuarto no valido para propiedad (id/code): ', COALESCE(v_id_room, 0));
        LEAVE row_proc;
      END IF;

      SELECT u.id_user
        INTO v_id_user
      FROM app_user u
      WHERE u.id_company = v_id_company
        AND u.deleted_at IS NULL
        AND COALESCE(u.is_active, 1) = 1
      ORDER BY COALESCE(u.is_owner, 0) DESC, u.id_user
      LIMIT 1;

      IF v_id_user IS NULL THEN
        SELECT u.id_user
          INTO v_id_user
        FROM app_user u
        WHERE u.deleted_at IS NULL
          AND COALESCE(u.is_active, 1) = 1
        ORDER BY u.id_user
        LIMIT 1;
      END IF;

      IF v_id_user IS NULL OR v_id_user <= 0 THEN
        SET v_reason = 'sin usuario activo para crear reservation/guest';
        LEAVE row_proc;
      END IF;

      SET v_amount_clean = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(v_amount_raw, '')), '$', ''), ',', ''), 'MXN', ''), 'mxn', ''), ' ', '');
      IF v_amount_clean REGEXP '^-?[0-9]+(\\.[0-9]+)?$' THEN
        SET v_total_override_cents = CAST(ROUND(CAST(v_amount_clean AS DECIMAL(12,2)) * 100, 0) AS SIGNED);
      ELSE
        SET v_total_override_cents = NULL;
      END IF;

      SET v_origin_norm = LOWER(
        REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(v_origin_raw, '')), '&', ''), ' ', ''), '.', ''), '-', '')
      );

      SET v_source_input = 'Mapas';
      SET v_id_ota_account = NULL;
      SET v_ota_name = NULL;
      SET v_ota_platform = NULL;
      SET v_id_reservation_source_match = NULL;
      SET v_source_related_name = NULL;

      SELECT oa.id_ota_account, oa.ota_name, oa.platform
        INTO v_id_ota_account, v_ota_name, v_ota_platform
      FROM ota_account oa
      WHERE oa.id_company = v_id_company
        AND oa.deleted_at IS NULL
        AND oa.is_active = 1
        AND (
          TRIM(COALESCE(oa.ota_name, '')) COLLATE utf8mb4_unicode_ci =
            TRIM(COALESCE(v_origin_raw, '')) COLLATE utf8mb4_unicode_ci
          OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(oa.ota_name, '')), '&', ''), ' ', ''), '.', ''), '-', '')) = v_origin_norm
        )
      ORDER BY CASE WHEN oa.id_property = v_id_property THEN 0 ELSE 1 END, oa.id_ota_account
      LIMIT 1;

      IF v_id_ota_account IS NOT NULL AND v_id_ota_account > 0 THEN
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source_match, v_source_related_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_company = v_id_company
          AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
          AND rsc.deleted_at IS NULL
          AND rsc.is_active = 1
          AND (
            TRIM(COALESCE(rsc.source_name, '')) COLLATE utf8mb4_unicode_ci =
              TRIM(COALESCE(v_ota_name, '')) COLLATE utf8mb4_unicode_ci
            OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(rsc.source_name, '')), '&', ''), ' ', ''), '.', ''), '-', '')) =
               LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(v_ota_name, '')), '&', ''), ' ', ''), '.', ''), '-', ''))
          )
        ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END, rsc.id_reservation_source
        LIMIT 1;

        IF v_id_reservation_source_match IS NULL OR v_id_reservation_source_match <= 0 THEN
          SET v_source_norm = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(v_ota_platform, '')), '&', ''), ' ', ''), '.', ''), '-', ''));
          SELECT rsc.id_reservation_source, rsc.source_name
            INTO v_id_reservation_source_match, v_source_related_name
          FROM reservation_source_catalog rsc
          WHERE rsc.id_company = v_id_company
            AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
            AND rsc.deleted_at IS NULL
            AND rsc.is_active = 1
            AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(rsc.source_name, '')), '&', ''), ' ', ''), '.', ''), '-', '')) = v_source_norm
          ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END, rsc.id_reservation_source
          LIMIT 1;
        END IF;

        SET v_source_input = COALESCE(NULLIF(TRIM(v_source_related_name), ''), NULLIF(TRIM(v_ota_name), ''), 'Mapas');
      ELSE
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source_match, v_source_related_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_company = v_id_company
          AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
          AND rsc.deleted_at IS NULL
          AND rsc.is_active = 1
          AND (
            TRIM(COALESCE(rsc.source_name, '')) COLLATE utf8mb4_unicode_ci =
              TRIM(COALESCE(v_origin_raw, '')) COLLATE utf8mb4_unicode_ci
            OR LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(rsc.source_name, '')), '&', ''), ' ', ''), '.', ''), '-', '')) = v_origin_norm
          )
        ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END, rsc.id_reservation_source
        LIMIT 1;

        IF v_id_reservation_source_match IS NOT NULL AND v_id_reservation_source_match > 0 THEN
          SET v_source_input = v_source_related_name;
        ELSE
          SELECT rsc.id_reservation_source, rsc.source_name
            INTO v_mapas_source_id, v_source_related_name
          FROM reservation_source_catalog rsc
          WHERE rsc.id_company = v_id_company
            AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
            AND rsc.deleted_at IS NULL
            AND rsc.is_active = 1
            AND LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(rsc.source_name, '')), '&', ''), ' ', ''), '.', ''), '-', '')) = 'mapas'
          ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END, rsc.id_reservation_source
          LIMIT 1;

          SET v_id_reservation_source_match = v_mapas_source_id;
          SET v_source_input = COALESCE(NULLIF(TRIM(v_source_related_name), ''), 'Mapas');
        END IF;
      END IF;

      SELECT COUNT(*)
        INTO v_overlap_cnt
      FROM reservation r
      WHERE r.id_room = v_room_id_db
        AND r.deleted_at IS NULL
        AND COALESCE(r.is_active, 1) = 1
        AND COALESCE(LOWER(TRIM(r.status)), 'confirmado') NOT IN ('cancelled', 'canceled', 'cancelado', 'cancelada')
        AND NOT (r.check_out_date <= v_check_in OR r.check_in_date >= v_check_out);

      IF v_overlap_cnt > 0 THEN
        SET v_reason = 'empalme con reservacion existente';
        LEAVE row_proc;
      END IF;

      SELECT COUNT(*)
        INTO v_block_overlap_cnt
      FROM room_block rb
      WHERE rb.id_room = v_room_id_db
        AND rb.deleted_at IS NULL
        AND rb.is_active = 1
        AND rb.start_date < v_check_out
        AND rb.end_date > v_check_in;

      IF v_block_overlap_cnt > 0 THEN
        SET v_reason = 'empalme con bloqueo de cuarto';
        LEAVE row_proc;
      END IF;

      SET v_note_text = CONCAT(
        'Monto capturado: ',
        COALESCE(NULLIF(TRIM(v_amount_raw), ''), 'N/D')
      );

      INSERT INTO guest (
        id_user,
        names,
        language,
        is_active,
        created_at,
        created_by,
        updated_at
      ) VALUES (
        v_id_user,
        v_guest_name,
        'es',
        1,
        NOW(),
        v_id_user,
        NOW()
      );

      SET v_new_guest_id = LAST_INSERT_ID();

      SET v_new_reservation_code = CONCAT(
        'IMP-',
        DATE_FORMAT(NOW(), '%y%m%d'),
        '-',
        LPAD(v_id_import, 6, '0'),
        '-',
        UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 4))
      );

      INSERT INTO reservation (
        id_user,
        id_guest,
        id_room,
        id_property,
        id_category,
        code,
        status,
        source,
        id_ota_account,
        id_reservation_source,
        check_in_date,
        check_out_date,
        adults,
        children,
        currency,
        total_price_cents,
        balance_due_cents,
        notes_internal,
        is_active,
        created_at,
        created_by,
        updated_at
      ) VALUES (
        v_id_user,
        v_new_guest_id,
        v_room_id_db,
        v_id_property,
        v_id_category,
        v_new_reservation_code,
        'apartado',
        v_source_input,
        v_id_ota_account,
        v_id_reservation_source_match,
        v_check_in,
        v_check_out,
        1,
        0,
        'MXN',
        0,
        0,
        v_note_text,
        1,
        NOW(),
        v_id_user,
        NOW()
      );

      SET v_new_reservation_id = LAST_INSERT_ID();

      IF v_new_reservation_id IS NULL OR v_new_reservation_id <= 0 THEN
        SET v_reason = 'no se pudo insertar reservation';
        LEAVE row_proc;
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
        v_new_reservation_id,
        'internal',
        v_note_text,
        1,
        NOW(),
        v_id_user,
        NOW()
      );
    END row_proc;

    IF v_sql_error = 1 THEN
      SET v_status = 'error';
      SET v_reason = CONCAT('SQL ERROR: ', COALESCE(v_sql_message, 'error'));
    ELSEIF v_reason IS NOT NULL AND v_reason <> '' THEN
      SET v_status = 'skipped';
    ELSE
      SET v_status = 'inserted';
    END IF;

    INSERT INTO tmp_reservation_import_log (
      id_import,
      result_status,
      reason,
      id_property,
      id_room,
      resolved_room_id,
      guest_name,
      check_in_date,
      check_out_date,
      origin_raw,
      source_used,
      id_ota_account,
      id_reservation_source,
      total_override_cents,
      id_reservation,
      reservation_code,
      created_at
    ) VALUES (
      v_id_import,
      v_status,
      v_reason,
      v_id_property,
      v_id_room,
      v_room_id_db,
      v_guest_name,
      v_check_in,
      v_check_out,
      v_origin_raw,
      v_source_input,
      v_id_ota_account,
      v_id_reservation_source_match,
      v_total_override_cents,
      v_new_reservation_id,
      v_new_reservation_code,
      NOW()
    );
  END LOOP;
  CLOSE cur;

  SELECT result_status, COUNT(*) AS total_rows
  FROM tmp_reservation_import_log
  GROUP BY result_status
  ORDER BY result_status;

  SELECT
    l.id_import,
    l.result_status,
    l.reason,
    l.id_property,
    l.id_room,
    l.guest_name,
    l.check_in_date,
    l.check_out_date,
    l.origin_raw,
    l.source_used,
    l.id_ota_account,
    l.id_reservation_source,
    l.total_override_cents,
    l.id_reservation,
    l.reservation_code
  FROM tmp_reservation_import_log l
  WHERE l.result_status <> 'inserted'
  ORDER BY l.id_import;
END $$

DELIMITER ;

/* ============================================================================
   CARGA TABULADA PRECONFIGURADA (todos los registros compartidos)
   ============================================================================ */
SET @raw_import = '
1	187	Gustavo	$4000	2026-01-01	2026-01-03	Expedia
1	187	Nancy	$700	2026-01-03	2026-01-04	Booking
1	187	Christopher	$2600	2026-01-04	2026-01-07	Booking
1	187	Brenda	$700	2026-01-07	2026-01-08	Booking
1	187	Estrella	$3200	2026-01-08	2026-01-12	AirB&B
1	187	Larissa	$2400	2026-01-13	2026-01-16	Booking
1	187	Christin	$800	2026-01-16	2026-01-17	Expedia
1	187	Julián	$4800	2026-01-17	2026-01-23	Mapas
1	187	Marco Emilio	$1600	2026-01-23	2026-01-25	Mapas
1	187	Roberta	$700	2026-01-25	2026-01-26	Booking
1	187	Blair	$3150	2026-01-26	2026-01-31	Booking
1	187	Kennet	$750	2026-01-31	2026-02-01	Booking
1	186	Daniela cancelando	$6500	2026-01-01	2026-01-05	Expedia
1	186	Alicia	$2200	2026-01-05	2026-01-08	Booking
1	186	Alicia	$700	2026-01-08	2026-01-09	Booking
1	186	Carlos	$1530	2026-01-09	2026-01-11	Expedia
1	186	Miruna	$800	2026-01-11	2026-01-12	AirB&B
1	186	Nicoleta	$7500	2026-01-12	2026-01-19	Booking
1	186	Rafael y Daniel facturan	$1400	2026-01-21	2026-01-22	Mapas
1	186	Emilio	$2500	2026-01-22	2026-01-25	Booking
1	186	Emilio	$2500	2026-01-25	2026-01-28	Booking
1	186	Reina	$1400	2026-01-28	2026-01-30	Booking
1	186	Roberto	$5785	2026-01-30	2026-02-01	Expedia
1	188	Louis		2026-01-01	2026-01-02	Expedia
1	188	Aube	$5900	2026-01-02	2026-01-07	AirB&B
1	188	Arturo	$4207	2026-01-09	2026-01-15	AirB&B
1	188	Pierre	$700	2026-01-15	2026-01-16	Booking
1	188	Briselda	$1600	2026-01-16	2026-01-17	Booking
1	188	Daw	$800	2026-01-17	2026-01-18	Booking
1	188	Gustavo	$700	2026-01-19	2026-01-20	Mapas
1	188	Cristina	$700	2026-01-20	2026-01-21	Booking
1	188	Gustavo	$1400	2026-01-22	2026-01-24	Mapas
1	188	Raul	$3100	2026-01-27	2026-01-31	Booking
1	188	Daniel	$2000	2026-01-31	2026-02-01	Booking
1	189	Adriana		2026-01-01	2026-01-04	Booking
1	189	Yuna	$3737	2026-01-04	2026-01-09	Booking
1	189	Dilaria	$2100	2026-01-09	2026-01-12	Booking
1	189	Señora aurora		2026-01-12	2026-01-13	Mapas
1	189	José	$1200	2026-01-14	2026-01-16	Mapas
1	189	Monica	$700	2026-01-16	2026-01-17	Mapas
1	189	Pam	$1400	2026-01-17	2026-01-19	Mapas
1	189	José	$6600	2026-01-19	2026-01-22	Mapas
1	189	Aidé	$1500	2026-01-22	2026-01-24	Booking
1	189	Midori	$2300	2026-01-24	2026-01-27	Booking
1	189	Lena		2026-01-27	2026-01-28	Booking
1	189	Merlin	$600	2026-01-29	2026-01-30	Mapas
1	189	Clement	$1500	2026-01-30	2026-02-01	Booking
1	190	Adriana		2026-01-01	2026-01-04	Booking
1	190	Jesús	$3900	2026-01-04	2026-01-09	Expedia
1	190	Jennifer	$5331	2026-01-09	2026-01-16	Expedia
1	190	Dulce	$700	2026-01-16	2026-01-17	AirB&B
1	190	Jessica	$4100	2026-01-17	2026-01-22	Booking
1	190	José		2026-01-22	2026-01-31	Mapas
1	190	Octavio	$1600	2026-01-31	2026-02-01	AirB&B
1	191	Abril cancelando pero dar 5041 a aurora	$5500	2026-01-01	2026-01-06	Booking
1	191	Gómez	$3200	2026-01-06	2026-01-10	Mapas
1	191	Maribel	$4000	2026-01-10	2026-01-15	Expedia
1	191	Briselda		2026-01-16	2026-01-17	Booking
1	191	Antonio	$800	2026-01-17	2026-01-18	Booking
1	191	Aarón	$1600	2026-01-18	2026-01-20	Expedia
1	191	Daniela	$3000	2026-01-20	2026-01-24	Booking
1	191	Carlos	$750	2026-01-24	2026-01-25	Booking
1	191	María	$750	2026-01-26	2026-01-27	Booking
1	191	Daisy	$1400	2026-01-29	2026-01-31	Booking
1	191	Antonio	$900	2026-01-31	2026-02-01	Booking
1	192	Isarel		2026-01-01	2026-01-02	Expedia
1	192	Eliot	$2400	2026-01-02	2026-01-04	Mapas
1	192	Jose	$4900	2026-01-05	2026-01-12	Mapas
1	192	José	$1400	2026-01-12	2026-01-14	Mapas
1	192	Chiara	$850	2026-01-14	2026-01-15	Booking
1	192	Miguel Ángel	$2400	2026-01-16	2026-01-19	Mapas
1	192	Alberede	$1400	2026-01-19	2026-01-21	Booking
1	192	Amber	$5090	2026-01-21	2026-01-28	Booking
1	192	Lena	$3200	2026-01-28	2026-01-31	Booking
1	192	Maria Magdalena *200*	$1600	2026-01-31	2026-02-01	Mapas
3	170	Anely	$2700	2026-01-01	2026-01-02	Booking
3	170	Lorena *1056*	$8450	2026-01-02	2026-01-06	Booking
3	170	Fer	$1000	2026-01-06	2026-01-07	Mapas
3	170	Douglas	$700	2026-01-07	2026-01-08	Expedia
3	170	Douglas extension	$700	2026-01-08	2026-01-09	Expedia
3	170	Regalado *882*	$2646	2026-01-09	2026-01-12	AirB&B
3	170	Regalado	$882	2026-01-12	2026-01-13	AirB&B
3	170	Macarena	$2400	2026-01-14	2026-01-16	AirB&B
3	170	Annie	$500	2026-01-16	2026-01-17	Booking
3	170	Hayde	$1600	2026-01-17	2026-01-19	Booking
3	170	Daniel	$600	2026-01-20	2026-01-21	Mapas
3	170	Claudia		2026-01-22	2026-01-26	Booking
3	170	Alexander *857*	$5141	2026-01-26	2026-02-01	Booking
3	175	Sara	$1600	2026-01-01	2026-01-02	Mapas
3	175	Dennis *1213*	$3641	2026-01-02	2026-01-05	Booking
3	175	Rose	$700	2026-01-05	2026-01-06	Expedia
3	175	Eric	$700	2026-01-07	2026-01-08	AirB&B
3	175	Brisa **	$800	2026-01-08	2026-01-11	AirB&B
3	175	Guo	$600	2026-01-11	2026-01-12	Mapas
3	175	Luz	$1150	2026-01-15	2026-01-16	Booking
3	175	Salome		2026-01-16	2026-01-21	AirB&B
3	175	Eibh	$600	2026-01-21	2026-01-22	Booking
3	175	Jorge	$600	2026-01-22	2026-01-23	Booking
3	175	Jorge	$600	2026-01-23	2026-01-24	Booking
3	175	Laura **	$620	2026-01-24	2026-01-25	Booking
3	175	Mariana **	$997	2026-01-25	2026-01-26	AirB&B
3	175	Mariela *714*	$3572	2026-01-27	2026-02-01	AirB&B
3	172	Miguel*	$1500	2026-01-01	2026-01-02	AirB&B
3	172	Giadalh	$1600	2026-01-02	2026-01-03	Mapas
3	172	Pau	$800	2026-01-03	2026-01-04	Mapas
3	172	Monn	$1600	2026-01-04	2026-01-06	AirB&B
3	172	Timo	$700	2026-01-06	2026-01-07	Expedia
3	172	Brisa	$3200	2026-01-07	2026-01-08	AirB&B
3	172	NICOLAS	$1000	2026-01-08	2026-01-09	Mapas
3	172	Néstor	$700	2026-01-09	2026-01-10	Mapas
3	172	Gerardo	$1700	2026-01-10	2026-01-11	Mapas
3	172	X	$1800	2026-01-15	2026-01-17	Mapas
3	172	Gladis	$700	2026-01-17	2026-01-18	Mapas
3	172	Ailyn	$800	2026-01-18	2026-01-19	Mapas
3	172	Miri	$600	2026-01-22	2026-01-23	Mapas
3	172	Jazmin	$700	2026-01-23	2026-01-24	Mapas
3	172	Escarlet	$4600	2026-01-24	2026-01-31	Booking
3	172	Juan Diego	$900	2026-01-31	2026-02-01	AirB&B
3	173	Amir **	$1397	2026-01-01	2026-01-02	Booking
3	173	Lorena		2026-01-02	2026-01-03	Booking
3	173	Darwin liz	$800	2026-01-03	2026-01-04	Mapas
3	173	Angel	$2100	2026-01-04	2026-01-07	AirB&B
3	173	Estrella	$800	2026-01-07	2026-01-08	AirB&B
3	173	Douglas		2026-01-09	2026-01-10	Expedia
3	173	Olesia	$8400	2026-01-10	2026-01-24	AirB&B
3	173	Annie	$3700	2026-01-24	2026-01-30	Booking
3	173	María	$1360	2026-01-30	2026-02-01	Booking
3	174	Alejandro *933*	$2800	2026-01-01	2026-01-04	AirB&B
3	174	Max	$3200	2026-01-04	2026-01-08	AirB&B
3	174	Luis	$700	2026-01-09	2026-01-10	Mapas
3	174	Laura	$700	2026-01-10	2026-01-11	Mapas
3	174	Luz	$575	2026-01-15	2026-01-16	Booking
3	174	Pam	$650	2026-01-16	2026-01-17	Mapas
3	174	Itzel	$600	2026-01-17	2026-01-18	Mapas
3	174	Antonio ****	$25841980	2026-01-18	2026-01-21	AirB&B
3	174	Aldo	$600	2026-01-21	2026-01-22	Booking
3	174	Claudia Flores *750*	$9000	2026-01-22	2026-01-26	Booking
3	174	Cinthya	$700	2026-01-26	2026-01-27	Mapas
3	174	Olesia *600*	$3000	2026-01-27	2026-02-01	AirB&B
3	171	monica	$4000	2026-01-01	2026-01-03	Booking
3	171	Lorena		2026-01-03	2026-01-06	Booking
3	171	Uriela	$1000	2026-01-06	2026-01-07	Mapas
3	171	Nicolas	$1000	2026-01-08	2026-01-09	Mapas
3	171	jose	$1100	2026-01-09	2026-01-10	Mapas
3	171	Gerardo		2026-01-10	2026-01-11	Mapas
3	171	Gerardo	$1000	2026-01-11	2026-01-12	Mapas
3	171	Salome	$5000	2026-01-15	2026-01-16	AirB&B
3	171	Raúl	$600	2026-01-16	2026-01-17	Booking
3	171	Duke	$800	2026-01-17	2026-01-18	Booking
3	171	Diana ****	$2100	2026-01-18	2026-01-21	Mapas
3	171	Diana	$700	2026-01-21	2026-01-22	Mapas
3	171	Armando*1000	$2000	2026-01-27	2026-01-29	Mapas
3	171	Mon	$700	2026-01-29	2026-01-30	Mapas
3	171	Graham	$1760	2026-01-30	2026-02-01	Booking
5	226	Sara	$3846	2026-01-01	2026-01-03	Booking
5	226	Angel	$1000	2026-01-03	2026-01-04	AirB&B
5	226	Evelyn	$4069	2026-01-04	2026-01-10	Booking
5	226	Emmanuelle	$4200	2026-01-10	2026-01-16	Booking
5	226	Imad	$1200	2026-01-17	2026-01-19	AirB&B
5	226	Darwin	$600	2026-01-27	2026-01-28	Mapas
5	226	Juliana	$600	2026-01-29	2026-01-30	Mapas
5	227	David		2026-01-01	2026-01-20	Expedia
5	227	David	$1400	2026-01-21	2026-01-23	Mapas
5	227	Jonathan	$2600	2026-01-23	2026-01-27	AirB&B
5	227	Felipe	$600	2026-01-29	2026-01-30	AirB&B
5	227	Raúl	$4000	2026-01-31	2026-02-01	Booking
5	228	Alexia	$2000	2026-01-01	2026-01-03	Mapas
5	228	Dennis	$800	2026-01-03	2026-01-04	AirB&B
5	228	Emily	$700	2026-01-04	2026-01-05	AirB&B
5	228	Sheyla	$600	2026-01-05	2026-01-06	Mapas
5	228	Tscholks	$1300	2026-01-07	2026-01-09	AirB&B
5	228	Cecilia	$700	2026-01-10	2026-01-11	Expedia
5	228	T	$650	2026-01-14	2026-01-15	AirB&B
5	228	louse	$600	2026-01-17	2026-01-18	Booking
5	228	Andreas	$3500	2026-01-23	2026-01-30	AirB&B
5	228	Anderson	$1600	2026-01-30	2026-02-01	AirB&B
5	229	Daniela	$3600	2026-01-01	2026-01-04	AirB&B
5	229	Manuel	$1000	2026-01-05	2026-01-06	Mapas
5	229	Roberto	$2000	2026-01-07	2026-01-09	Mapas
5	229	Carlos	$900	2026-01-10	2026-01-11	Mapas
5	229	Mario	$1000	2026-01-13	2026-01-14	Mapas
5	229	Francisco	$700	2026-01-14	2026-01-15	Mapas
5	229	Juan	$800	2026-01-27	2026-01-28	Mapas
5	229	Nayeli	$3000	2026-01-28	2026-01-31	Mapas
5	229	Eric	$1800	2026-01-31	2026-02-01	AirB&B
4	184	Laura	$1600	2026-01-13	2026-01-15	AirB&B
4	184	Claudia 500 depósito	$4400	2026-01-29	2026-02-01	Mapas
4	185	Jenna	$18000	2026-01-01	2026-01-21	AirB&B
4	185	Jenna	$18000	2026-01-21	2026-02-01	AirB&B
6	177	Mathia		2026-01-01	2026-01-04	Booking
6	177	Belén	$7300	2026-01-04	2026-01-09	AirB&B
6	177	Belén	$1500	2026-01-09	2026-01-10	AirB&B
6	177	Benjamin	$3398	2026-01-10	2026-01-12	Booking
6	177	Daniel	$4500	2026-01-18	2026-01-21	Mapas
6	177	Jeffrey	$4624	2026-01-22	2026-01-25	AirB&B
6	177	Guillermina	$7000	2026-01-25	2026-01-29	AirB&B
6	177	Abigail	$5685	2026-01-29	2026-02-01	AirB&B
6	178	Mariana		2026-01-01	2026-01-02	Booking
6	178	isac	$3400	2026-01-02	2026-01-04	AirB&B
6	178	Antonio	$1800	2026-01-04	2026-01-05	AirB&B
6	178	Fracisco	$4313	2026-01-08	2026-01-11	Booking
6	178	Jaime	$2670	2026-01-11	2026-01-13	Expedia
6	178	Sebastián	$5200	2026-01-16	2026-01-20	Expedia
6	178	Viktor	$1300	2026-01-21	2026-01-22	AirB&B
6	178	Omar	$7000	2026-01-23	2026-01-27	Booking
6	178	Michaus Samantha	$2600	2026-01-27	2026-01-29	Booking
6	178	Idania	$5300	2026-01-29	2026-02-01	Booking
6	230	Sharon		2026-01-01	2026-01-02	AirB&B
6	230	Ana	$1500	2026-01-02	2026-01-03	Booking
6	231	Braulio	$3000	2026-01-01	2026-01-02	AirB&B
6	231	Sandra	$10000	2026-01-02	2026-01-06	AirB&B
6	231	Extension Sandra	$7500	2026-01-06	2026-01-09	AirB&B
6	231	Nicolas	$7817	2026-01-09	2026-01-14	Booking
9	211	laura**		2026-01-01	2026-01-02	Booking
9	211	Ana	$1200	2026-01-02	2026-01-03	Mapas
9	211	Jaciel	$1300	2026-01-03	2026-01-04	Mapas
9	211	Jaime	$1500	2026-01-04	2026-01-05	Mapas
9	211	Yuli	$700	2026-01-05	2026-01-06	Mapas
9	211	Zury	$2700	2026-01-08	2026-01-09	Booking
9	211	Luz	$650	2026-01-26	2026-01-27	Mapas
9	211	Jonathan	$800	2026-01-31	2026-02-01	Mapas
9	212	Rafael **		2026-01-01	2026-01-04	Mapas
9	212	Carlos	$1500	2026-01-04	2026-01-05	Mapas
9	212	Edith	$2000	2026-01-10	2026-01-12	Booking
9	212	Manuel	$450	2026-01-16	2026-01-17	Mapas
9	212	Eduardo	$700	2026-01-17	2026-01-18	Mapas
9	212	Hernesto	$800	2026-01-25	2026-01-26	Booking
9	212	Cayetano	$700	2026-01-27	2026-01-28	Mapas
9	212	Cayetano	$700	2026-01-28	2026-01-29	Mapas
9	212	Antonione	$800	2026-01-31	2026-02-01	AirB&B
9	213	Rafael **		2026-01-01	2026-01-04	Mapas
9	213	Julio		2026-01-04	2026-01-07	Booking
9	213	nancy		2026-01-18	2026-01-19	Booking
9	213	Leonardo	$700	2026-01-23	2026-01-24	Mapas
9	213	Diana	$3600	2026-01-25	2026-01-29	AirB&B
9	213	Miriam	$2000	2026-01-31	2026-02-01	Mapas
9	214	Rafael **		2026-01-01	2026-01-04	Mapas
9	214	Julio	$10000	2026-01-04	2026-01-07	Booking
9	214	María mercedes	$4600	2026-01-07	2026-01-11	AirB&B
9	214	Miguel		2026-01-14	2026-01-15	Booking
9	214	Manuel	$680	2026-01-16	2026-01-17	Mapas
9	214	Vidal	$700	2026-01-17	2026-01-18	Mapas
9	214	Iván	$650	2026-01-21	2026-01-22	Mapas
9	214	Ivan	$650	2026-01-22	2026-01-23	Mapas
9	214	Iván	$650	2026-01-23	2026-01-24	Mapas
9	214	Dante	$3300	2026-01-26	2026-01-30	Booking
9	214	Dante	$2475	2026-01-30	2026-02-01	Booking
9	215	Lis	$4500	2026-01-01	2026-01-04	AirB&B
9	215	Julio		2026-01-04	2026-01-07	Booking
9	215	Minatti	$800	2026-01-07	2026-01-08	Booking
9	215	Isaías	$1000	2026-01-09	2026-01-10	Mapas
9	215	Jan	$1000	2026-01-10	2026-01-11	Mapas
9	215	Vidal	$900	2026-01-17	2026-01-18	Mapas
9	215	Feliz	$830	2026-01-21	2026-01-22	Booking
9	215	Feliz	$830	2026-01-22	2026-01-23	Booking
9	215	Leonardo	$2000	2026-01-25	2026-01-27	Mapas
9	215	Gabriel	$1600	2026-01-29	2026-01-31	Mapas
9	215	Luis	$700	2026-01-31	2026-02-01	Mapas
9	216	Chloe **		2026-01-01	2026-01-03	Booking
9	216	Mildred	$1900	2026-01-03	2026-01-04	AirB&B
9	216	Daniel	$1000	2026-01-04	2026-01-05	Mapas
9	216	Vicente	$800	2026-01-05	2026-01-06	Expedia
9	216	Francisco	$2000	2026-01-06	2026-01-07	Expedia
9	216	Fabián	$800	2026-01-07	2026-01-08	Booking
9	216	José	$1200	2026-01-08	2026-01-09	Mapas
9	216	Sergio	$1100	2026-01-09	2026-01-10	Mapas
9	216	Sergio	$1100	2026-01-10	2026-01-11	Mapas
9	216	Jan	$1000	2026-01-11	2026-01-12	Mapas
9	216	Helga		2026-01-12	2026-01-13	Booking
9	216	Karen	$800	2026-01-17	2026-01-18	Mapas
9	216	Roberto	$2000	2026-01-24	2026-01-26	Mapas
9	216	Tomas	$1000	2026-01-26	2026-01-27	Mapas
9	216	Luz	$1000	2026-01-27	2026-01-28	Mapas
9	216	Gaby	$1900	2026-01-31	2026-02-01	AirB&B
9	217	Felix **		2026-01-01	2026-01-02	Booking
9	217	Miguel **		2026-01-02	2026-01-04	AirB&B
9	217	Jaime	$1300	2026-01-04	2026-01-05	Mapas
9	217	Steven	$700	2026-01-09	2026-01-10	Expedia
9	217	Ángela	$700	2026-01-11	2026-01-12	Mapas
9	217	Alison	$700	2026-01-16	2026-01-17	Mapas
9	217	Iván	$650	2026-01-21	2026-01-22	Mapas
9	217	Amado	$650	2026-01-23	2026-01-24	Mapas
9	217	María de posada	$600	2026-01-27	2026-01-28	Booking
9	217	Alexa	$900	2026-01-28	2026-01-29	Mapas
9	217	Menaly	$1300	2026-01-31	2026-02-01	Mapas
9	218	Julie**		2026-01-01	2026-01-05	AirB&B
9	218	Minatti	$700	2026-01-06	2026-01-07	Booking
9	218	Maayan	$700	2026-01-09	2026-01-10	AirB&B
9	218	Rosario	$700	2026-01-10	2026-01-11	Mapas
9	218	Eva	$700	2026-01-13	2026-01-14	Booking
9	218	Ard	$2064	2026-01-16	2026-01-19	Booking
9	218	Feliz	$600	2026-01-23	2026-01-24	Booking
9	218	Elisa	$2400	2026-01-26	2026-01-30	Mapas
9	218	Jivany	$700	2026-01-31	2026-02-01	Mapas
9	210	Elizabeth**		2026-01-01	2026-01-04	AirB&B
9	210	Jaime		2026-01-04	2026-01-05	Mapas
9	210	Zury		2026-01-08	2026-01-09	Booking
9	210	Lorena	$2000	2026-01-10	2026-01-12	Mapas
9	210	Eduarda	$3500	2026-01-21	2026-01-26	Mapas
9	210	Alejandro	$650	2026-01-27	2026-01-28	Mapas
9	210	Merlin	$1800	2026-01-31	2026-02-01	Mapas
11	222	Monica		2026-01-01	2026-01-03	AirB&B
11	222	Arturo	$12000	2026-01-03	2026-01-13	AirB&B
11	222	Erika	$8400	2026-01-15	2026-01-18	AirB&B
11	221	Jonathan	$3600	2026-01-03	2026-01-06	AirB&B
11	221	Valeria	$5000	2026-01-06	2026-01-10	AirB&B
11	221	Jaime	$1300	2026-01-10	2026-01-11	Expedia
11	221	Dani	$4800	2026-01-11	2026-01-15	AirB&B
11	221	Dayana		2026-01-19	2026-01-23	AirB&B
11	221	Dafne		2026-01-28	2026-02-01	AirB&B						
1	187	Alex		2026-02-01	2026-02-06	Booking
1	187	Briselda		2026-02-06	2026-02-07	Booking
1	187	Munsilf 878 Airb&b 2195 efectivo	$3073	2026-02-07	2026-02-11	AirB&B
1	187	Or	$800	2026-02-14	2026-02-15	Booking
1	187	Arrieta		2026-02-16	2026-02-17	Booking
1	187	Emmanuel	$800	2026-02-17	2026-02-18	Mapas
1	187	Carlos	$3600	2026-02-18	2026-02-23	Booking
1	187	Carlos	$1600	2026-02-23	2026-02-25	Booking
1	187	Melissa	$700	2026-02-25	2026-02-26	Booking
1	187	Omar	$2100	2026-02-26	2026-03-01	Booking
1	186	Roberto		2026-02-01	2026-02-02	Expedia
1	186	Christian	$2600	2026-02-02	2026-02-03	Booking
1	186	Ziva	$750	2026-02-04	2026-02-05	Booking
1	186	Cameron	$700	2026-02-08	2026-02-09	AirB&B
1	186	Julie Hirtz	$3053	2026-02-10	2026-02-14	AirB&B
1	186	Laura	$1440	2026-02-14	2026-02-16	Booking
1	186	jose	$700	2026-02-16	2026-02-17	Booking
1	186	Eliza	$700	2026-02-18	2026-02-19	Booking
1	186	Martín	$1500	2026-02-19	2026-02-21	Booking
1	186	Kevin	$1400	2026-02-21	2026-02-23	Mapas
1	186	Kevin	$700	2026-02-23	2026-02-24	Mapas
1	186	Alberto	$1400	2026-02-24	2026-02-25	Booking
1	186	Nico	$650	2026-02-25	2026-02-26	Booking
1	186	Tonatiuh	$750	2026-02-26	2026-02-27	AirB&B
1	186	Kennet		2026-02-28	2026-03-02	Booking
1	188	Daniel		2026-02-01	2026-02-02	Booking
1	188	Roberto		2026-02-02	2026-02-07	Expedia
1	188	Arreba		2026-02-07	2026-02-08	Booking
1	188	Garza Juanita checar		2026-02-10	2026-02-11	Mapas
1	188	Bert *	$1321	2026-02-12	2026-02-14	Booking
1	188	Munsfin	$2250	2026-02-14	2026-02-17	AirB&B
1	188	Munsfin	$750	2026-02-17	2026-02-18	AirB&B
1	188	Claudia	$700	2026-02-18	2026-02-19	AirB&B
1	188	Daniel	$1500	2026-02-19	2026-02-20	Booking
1	188	Diana	$2400	2026-02-20	2026-02-23	AirB&B
1	188	Martín	$750	2026-02-23	2026-02-24	Mapas
1	188	Enric del #	$6	2026-02-24	2026-02-26	Booking
1	188	Marcos	$700	2026-02-26	2026-02-27	Booking
1	189	simon	$1600	2026-02-01	2026-02-03	Booking
1	189	César	$2400	2026-02-06	2026-02-09	Mapas
1	189	Thomas	$700	2026-02-10	2026-02-11	Booking
1	189	Alejandro	$700	2026-02-11	2026-02-12	Booking
1	189	Arturo	$2500	2026-02-12	2026-02-15	Booking
1	189	Christian	$1400	2026-02-15	2026-02-17	Booking
1	189	Daniel	$650	2026-02-17	2026-02-18	Mapas
1	189	Matthew		2026-02-18	2026-02-19	Booking
1	189	Pedro	$2025	2026-02-20	2026-02-23	Booking
1	189	Leonor	$650	2026-02-24	2026-02-25	Booking
1	189	Luc		2026-02-25	2026-02-26	Booking
1	189	Cecilia		2026-02-27	2026-02-28	Booking
1	190	Octavio		2026-02-01	2026-02-02	AirB&B
1	190	Alfred	$1600	2026-02-02	2026-02-04	Booking
1	190	Nicholas	$1870	2026-02-04	2026-02-07	Booking
1	190	Fabián Nicola	$3200	2026-02-07	2026-02-11	Booking
1	190	Munsfin	$2040	2026-02-11	2026-02-14	AirB&B
1	190	Rijsbergen Sharon	$1250	2026-02-14	2026-02-16	Booking
1	190	Carolina		2026-02-16	2026-02-17	Booking
1	190	Niell	$670	2026-02-17	2026-02-18	Booking
1	190	Ricardo	$2025	2026-02-18	2026-02-21	Booking
1	190	Thomas	$630	2026-02-22	2026-02-23	Booking
1	190	Jonna	$650	2026-02-23	2026-02-24	Booking
1	190	ORIA & MICHAEL ( total) (500 pagados por ABB)	$13650	2026-02-24	2026-03-04	AirB&B
1	191	Mattew	$4700	2026-02-01	2026-02-07	Booking
1	191	Nina	$800	2026-02-07	2026-02-08	Mapas
1	191	Richard	$3100	2026-02-09	2026-02-13	Expedia
1	191	Luis *	$1115	2026-02-13	2026-02-15	Booking
1	191	Melisa Acosta	$2100	2026-02-17	2026-02-20	Mapas
1	191	Jose manuel	$700	2026-02-21	2026-02-22	Mapas
1	191	Enric		2026-02-22	2026-02-24	Booking
1	191	Pedro	$700	2026-02-24	2026-02-25	Booking
1	191	Alberto del #	$2	2026-02-25	2026-02-26	Booking
1	191	Alberto	$700	2026-02-26	2026-02-27	Booking
1	191	Cecilia		2026-02-27	2026-02-28	Booking
1	192	Maria magdalena		2026-02-01	2026-02-02	Mapas
1	192	Christian		2026-02-03	2026-02-05	Booking
1	192	Luis	$1000	2026-02-06	2026-02-07	Mapas
1	192	Noel Mineli	$1400	2026-02-07	2026-02-09	Mapas
1	192	Daniel	$7700	2026-02-09	2026-02-20	AirB&B
1	192	Eric	$6000	2026-02-22	2026-02-26	Booking
1	192	Enric	$750	2026-02-26	2026-02-27	Booking
3	170	Alexander	$857	2026-02-01	2026-02-02	Booking
3	170	María		2026-02-02	2026-02-04	Booking
3	170	Evelyn	$700	2026-02-04	2026-02-05	Mapas
3	170	Sonia	$2200	2026-02-07	2026-02-09	AirB&B
3	170	Sofía	$700	2026-02-10	2026-02-11	Mapas
3	170	Margarita	$700	2026-02-12	2026-02-13	Mapas
3	170	Cesar	$1800	2026-02-13	2026-02-15	Mapas
3	170	Xime *555 Airb&b	$2070	2026-02-17	2026-02-20	AirB&B
3	170	Ximena	$3863	2026-02-20	2026-02-24	AirB&B
3	170	Robin	$700	2026-02-24	2026-02-25	AirB&B
3	170	Sergio	$700	2026-02-26	2026-02-27	Mapas
3	170	Liz Santos	$2700	2026-02-28	2026-03-03	AirB&B
3	175	Mariela	$714	2026-02-01	2026-02-02	AirB&B
3	175	Francesco *	$5480	2026-02-02	2026-02-09	AirB&B
3	175	David	$500	2026-02-09	2026-02-10	Booking
3	175	Anja	$600	2026-02-10	2026-02-11	Mapas
3	175	Kevin salgado	$650	2026-02-11	2026-02-12	Mapas
3	175	Bryan	$600	2026-02-13	2026-02-14	Mapas
3	175	Mara	$1500	2026-02-14	2026-02-16	Booking
3	175	Eduardo	$1200	2026-02-17	2026-02-19	Mapas
3	175	Saffron	$2100	2026-02-20	2026-02-23	AirB&B
3	175	Saffron	$700	2026-02-23	2026-02-24	AirB&B
3	175	Guadalupe	$1600	2026-02-24	2026-02-26	Mapas
3	175	Guadalupe	$800	2026-02-26	2026-02-27	Mapas
3	172	Chong	$3050	2026-02-01	2026-02-06	Booking
3	172	Didier	$2400	2026-02-07	2026-02-10	Mapas
3	172	Kevin	$2800	2026-02-11	2026-02-15	Mapas
3	172	Sandra	$1400	2026-02-16	2026-02-17	Booking
3	172	Citlalli	$1100	2026-02-17	2026-02-18	Mapas
3	172	lucio	$1400	2026-02-20	2026-02-21	Mapas
3	172	Elsa	$800	2026-02-21	2026-02-22	Mapas
3	172	Gabby paggado****	$2800	2026-02-22	2026-02-26	Mapas
3	172	Ricardo		2026-02-26	2026-03-01	AirB&B
3	173	María	$2040	2026-02-01	2026-02-02	Booking
3	173	ALEX *642*	$4500	2026-02-02	2026-02-09	Booking
3	173	Jannet Garcia	$1400	2026-02-10	2026-02-12	AirB&B
3	173	Eduardo	$2000	2026-02-13	2026-02-15	Mapas
3	173	Peter	$1400	2026-02-17	2026-02-19	Booking
3	173	Lidia	$800	2026-02-21	2026-02-22	Mapas
3	173	Jonna	$700	2026-02-22	2026-02-23	Booking
3	173	David	$1400	2026-02-23	2026-02-25	AirB&B
3	173	David	$2100	2026-02-25	2026-02-28	Booking
3	174	Olesia *600*	$2400	2026-02-01	2026-02-05	AirB&B
3	174	Markle	$4000	2026-02-05	2026-02-08	Booking
3	174	Dulce	$800	2026-02-08	2026-02-09	Mapas
3	174	Alejandro	$1500	2026-02-09	2026-02-11	Mapas
3	174	Daniel	$2400	2026-02-11	2026-02-14	Mapas
3	174	Ery resta +786 Airb&b	$2000	2026-02-14	2026-02-17	AirB&B
3	174	Erika extensión	$500	2026-02-17	2026-02-18	AirB&B
3	174	Miguel	$1800	2026-02-18	2026-02-21	AirB&B
3	174	José	$1500	2026-02-21	2026-02-23	AirB&B
3	174	Gustavo	$2500	2026-02-23	2026-02-27	Booking
3	174	Alex	$2000	2026-02-27	2026-03-02	Mapas
3	171	Graham *880*	$7040	2026-02-01	2026-02-09	Booking
3	171	Sara	$5727	2026-02-10	2026-02-18	AirB&B
3	171	Edgar	$3559	2026-02-18	2026-02-22	AirB&B
3	171	Edgar	$950	2026-02-22	2026-02-23	AirB&B
3	171	Edgar	$950	2026-02-23	2026-02-24	AirB&B
3	171	José Manuel	$750	2026-02-26	2026-02-27	Mapas
5	226	Keila	$500	2026-02-04	2026-02-05	AirB&B
5	226	Raúl	$2100	2026-02-05	2026-02-08	AirB&B
5	226	Celeste	$1600	2026-02-14	2026-02-17	AirB&B
5	226	Lidia	$600	2026-02-22	2026-02-23	Mapas
5	226	Rena	$5400	2026-02-24	2026-03-04	Expedia
5	227	Raúl		2026-02-01	2026-02-06	Booking
5	227	Lauren	$1700	2026-02-08	2026-02-11	AirB&B
5	227	Rommel	$700	2026-02-12	2026-02-13	Booking
5	227	Juan Carlos	$700	2026-02-14	2026-02-15	AirB&B
5	227	Michael	$620	2026-02-15	2026-02-16	Booking
5	227	David	$700	2026-02-22	2026-02-23	AirB&B
5	227	Jaime / efectivo 2309	$4200	2026-02-23	2026-03-02	AirB&B
5	228	Claude	$600	2026-02-02	2026-02-03	Booking
5	228	Gunda	$900	2026-02-09	2026-02-11	AirB&B
5	228	Marine	$1000	2026-02-13	2026-02-15	AirB&B
5	228	Julia	$550	2026-02-16	2026-02-17	AirB&B
5	228	Julia	$550	2026-02-17	2026-02-18	AirB&B
5	228	Diego	$600	2026-02-22	2026-02-23	AirB&B
5	228	Celeste	$550	2026-02-23	2026-02-24	Mapas
5	228	Julia	$550	2026-02-25	2026-02-26	Mapas
5	229	Eric		2026-02-01	2026-02-02	AirB&B
5	229	Van efectivo +528airb&b 2128	$1600	2026-02-14	2026-02-17	AirB&B
5	229	Van	$1407	2026-02-17	2026-02-19	AirB&B
5	229	Castillo Bautista		2026-02-27	2026-03-01	AirB&B
4	184	Ximena	$900	2026-02-07	2026-02-08	Mapas
4	184	Rick	$800	2026-02-14	2026-02-15	Booking
4	184	Janet	$700	2026-02-16	2026-02-17	Mapas
4	184	Jorge	$3000	2026-02-20	2026-02-23	Mapas
4	184	Jorge	$2000	2026-02-23	2026-02-25	Mapas
4	185	Janet		2026-02-01	2026-02-12	AirB&B
4	185	Maritza	$2700	2026-02-14	2026-02-17	AirB&B
4	185	Carlos	$2100	2026-02-20	2026-02-23	AirB&B
6	177	Heriberta	$3000	2026-02-01	2026-02-03	Booking
6	177	Mitzi	$1300	2026-02-03	2026-02-04	AirB&B
6	177	Luis	$4541	2026-02-04	2026-02-07	AirB&B
6	177	Mario	$3000	2026-02-07	2026-02-09	Booking
6	177	Camille	$5400	2026-02-09	2026-02-13	AirB&B
6	177	Dennis	$6000	2026-02-13	2026-02-17	Booking
6	177	Luisa *916 Airb&b 6,000 kike	$7700	2026-02-19	2026-02-24	AirB&B
6	177	Marisol efectivo	$2137	2026-02-24	2026-02-26	AirB&B
6	177	Bloqueado Kike		2026-02-26	2026-02-28	Mapas
6	177	Szabolcs		2026-02-03	2026-02-04	AirB&B
6	178	Idania		2026-02-01	2026-02-02	Booking
6	178	Idania	$1400	2026-02-02	2026-02-03	Booking
6	178	Oscar *resta 5600	$6300	2026-02-05	2026-02-09	AirB&B
6	178	Saúl (pago ABB, faltan cash) faltan 300 pesos, para revisarlo	$400	2026-02-09	2026-02-11	AirB&B
6	178	Andrea	$6800	2026-02-11	2026-02-15	AirB&B
6	178	Valentin (342 AB&B -2600 cash)	$2942	2026-02-15	2026-02-17	AirB&B
6	178	Yoselin 366airb&b	$6400	2026-02-19	2026-02-24	AirB&B
6	178	Samantha *5500 tranferencia	$7000	2026-02-24	2026-02-28	AirB&B
6	178	Fracisco		2026-02-28	2026-03-04	AirB&B
9	211	Miriam		2026-02-01	2026-02-03	Mapas
9	211	Miriam	$3000	2026-02-03	2026-02-08	Mapas
9	211	Miriam	$3000	2026-02-08	2026-02-13	Mapas
9	212	Félix	$1200	2026-02-01	2026-02-03	Mapas
9	212	Sandra	$6400	2026-02-07	2026-02-08	Mapas
9	212	Juan Carlos	$2600	2026-02-26	2026-03-02	Expedia
9	213	Leydy	$700	2026-02-01	2026-02-02	Mapas
9	213	Corine	$1500	2026-02-05	2026-02-07	Mapas
9	213	Ivan	$700	2026-02-14	2026-02-15	AirB&B
9	214	Dante		2026-02-01	2026-02-02	Booking
9	214	Gustavo	$1000	2026-02-14	2026-02-15	Mapas
9	214	Rosasiela	$1800	2026-02-23	2026-02-25	Mapas
9	214	Renato	$600	2026-02-25	2026-02-26	AirB&B
9	215	Laura	$2100	2026-02-01	2026-02-04	Booking
9	215	Erick	$1800	2026-02-04	2026-02-06	Expedia
9	215	Carlos	$2813	2026-02-06	2026-02-09	AirB&B
9	215	Julia	$800	2026-02-13	2026-02-14	Mapas
9	215	Annika		2026-02-14	2026-02-17	AirB&B
9	215	Romo + 1800	$2100	2026-02-21	2026-02-26	Mapas
9	216	Gaby		2026-02-01	2026-02-02	AirB&B
9	216	Erick	$1800	2026-02-04	2026-02-06	Expedia
9	216	Sandra		2026-02-07	2026-02-08	Mapas
9	216	Ezequiel	$800	2026-02-09	2026-02-10	Mapas
9	216	Ezequiel	$800	2026-02-10	2026-02-11	Mapas
9	216	Sam	$1900	2026-02-13	2026-02-15	Booking
9	216	Alan	$2400	2026-02-19	2026-02-22	AirB&B
9	216	Eliseo	$1000	2026-02-23	2026-02-24	Mapas
9	217	Melany		2026-02-01	2026-02-02	Mapas
9	217	Capucini		2026-02-09	2026-02-12	Booking
9	217	Julia	$500	2026-02-13	2026-02-14	AirB&B
9	217	Saraí	$600	2026-02-14	2026-02-15	Mapas
9	217	Alejandro	$600	2026-02-15	2026-02-16	Mapas
9	217	Mirza	$1800	2026-02-20	2026-02-23	Mapas
9	217	Eduard	$5066	2026-02-23	2026-02-27	Booking
9	218	Yovani	$400	2026-02-01	2026-02-02	Mapas
9	218	Elad	$600	2026-02-02	2026-02-03	Mapas
9	218	Dulce	$1300	2026-02-03	2026-02-05	Booking
9	218	Abner	$600	2026-02-10	2026-02-11	AirB&B
9	218	Ezequiel	$600	2026-02-11	2026-02-12	Mapas
9	218	Erika Victor	$1200	2026-02-12	2026-02-14	AirB&B
9	218	Rosaisela	$1800	2026-02-23	2026-02-25	Mapas
9	210	Merlin		2026-02-01	2026-02-03	Mapas
9	210	Jonathan	$600	2026-02-13	2026-02-14	Mapas
9	210	Alejandro	$600	2026-02-16	2026-02-17	Mapas
9	210	Eduard		2026-02-23	2026-02-27	Booking				
1	187	Alejandro	$3500	2026-03-02	2026-03-07	AirB&B
1	188	Liliana	$1600	2026-03-03	2026-03-05	Mapas
1	188	Fernanda	$3200	2026-03-06	2026-03-10	AirB&B
1	188	Judith	$2400	2026-03-11	2026-03-14	Mapas
1	190	ORIA & MICHAEL		2026-03-01	2026-03-18	AirB&B
1	192	Leyber	$1400	2026-03-14	2026-03-16	Mapas
3	170	Santos Liz		2026-03-01	2026-03-03	AirB&B
3	170	Alejandra		2026-03-03	2026-03-04	AirB&B
3	170	Cutberto	$2200	2026-03-06	2026-03-08	AirB&B
3	175	Mariana	$2400	2026-03-07	2026-03-10	Mapas
3	174	Yair	$3300	2026-03-30	2026-04-01	AirB&B
3	171	Pablo efectivo	$2870	2026-03-29	2026-04-01	AirB&B
9	215	Mariana Airb&b	$18000	2026-03-02	2026-04-01	AirB&B
';

CALL sp_load_reservation_import_input_from_tsv(@raw_import);

/* Verifica que haya filas cargadas */
SELECT COUNT(*) AS rows_loaded_in_input
FROM tmp_reservation_import_input;

/* Ejecuta import masivo */
CALL sp_import_reservations_quick_mass_by_room_id();

/* Resumen post-import */
SELECT result_status, COUNT(*) AS total_rows
FROM tmp_reservation_import_log
GROUP BY result_status
ORDER BY result_status;

SELECT reason, COUNT(*) AS total_rows
FROM tmp_reservation_import_log
WHERE result_status <> 'inserted'
GROUP BY reason
ORDER BY total_rows DESC, reason;

/* Diagnostico rapido por propiedad */
SELECT
  id_property,
  result_status,
  COUNT(*) AS total_rows
FROM tmp_reservation_import_log
GROUP BY id_property, result_status
ORDER BY id_property, result_status;

/* Listado final: SOLO reservaciones no agregadas */
SELECT
  l.id_import,
  l.id_property,
  l.id_room,
  l.resolved_room_id,
  l.guest_name,
  l.check_in_date,
  l.check_out_date,
  l.result_status,
  l.origin_raw,
  l.source_used,
  l.id_ota_account,
  l.id_reservation_source,
  l.total_override_cents,
  l.reason
FROM tmp_reservation_import_log l
WHERE l.result_status <> 'inserted'
ORDER BY l.id_import
LIMIT 5000;



