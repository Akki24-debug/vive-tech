DELIMITER $$

DROP PROCEDURE IF EXISTS sp_create_reservation $$
CREATE PROCEDURE sp_create_reservation
(
  IN p_property_code     VARCHAR(32),
  IN p_room_code         VARCHAR(64),

  -- FECHAS COMO TEXTO para aceptar múltiples formatos
  IN p_check_in_str      VARCHAR(32),
  IN p_check_out_str     VARCHAR(32),

  IN p_guest_email       VARCHAR(255),
  IN p_guest_names       VARCHAR(255),
  IN p_guest_last_name   VARCHAR(255),   -- opcional
  IN p_guest_maiden_name VARCHAR(255),   -- opcional
  IN p_guest_phone       VARCHAR(32),
  IN p_adults            INT,
  IN p_children          INT,
  IN p_lodging_catalog_id BIGINT,
  IN p_total_cents_override INT,
  IN p_fixed_child_unit_price_cents INT,
  IN p_fixed_child_total_cents INT,
  IN p_source           VARCHAR(120),
  IN p_id_ota_account   BIGINT,
  IN p_id_user           INT             -- si NULL/0/invalid -> usa 3
)
proc:BEGIN
  DECLARE v_id_company    INT;
  DECLARE v_company_code  VARCHAR(100);
  DECLARE v_id_property   INT;
  DECLARE v_id_room       INT;
  DECLARE v_id_category   INT;
  DECLARE v_base_cents    INT DEFAULT 0;
  DECLARE v_total_cents   INT DEFAULT 0;
  DECLARE v_lodging_total INT DEFAULT 0;
  DECLARE v_fixed_child_total INT DEFAULT 0;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_rate_breakdown TEXT;
  DECLARE v_use_rateplan TINYINT DEFAULT 0;
  DECLARE v_id_guest      INT;
  DECLARE v_id_res        INT;
  DECLARE v_code          VARCHAR(32);
  DECLARE v_overlap_cnt   INT DEFAULT 0;
  DECLARE v_block_overlap_cnt INT DEFAULT 0;
  DECLARE v_nights        INT DEFAULT 0;
  DECLARE v_snapshot      TEXT;
  DECLARE v_breakdown     TEXT;
  DECLARE v_last_name     VARCHAR(511);
  DECLARE v_maiden_name   VARCHAR(255);
  DECLARE v_full_name     VARCHAR(600);
  DECLARE v_id_user_eff   INT;
  DECLARE v_id_folio      BIGINT;
  DECLARE v_lodging_catalog_id BIGINT;
  DECLARE v_parent_sale_item_id BIGINT;
  DECLARE v_child_catalog_id BIGINT;
  DECLARE v_child_sale_item_id BIGINT;
  DECLARE v_parent_item_name VARCHAR(255);
  DECLARE v_child_item_name VARCHAR(255);
  DECLARE v_child_desc TEXT;
  DECLARE v_source VARCHAR(32);
  DECLARE v_id_ota_account BIGINT DEFAULT NULL;
  DECLARE v_id_reservation_source BIGINT DEFAULT NULL;
  DECLARE v_ota_platform VARCHAR(32);
  DECLARE v_source_name VARCHAR(120);
  DECLARE v_in_tx TINYINT DEFAULT 0;
  DECLARE v_check_in DATE;
  DECLARE v_check_out DATE;
  DECLARE v_has_total_override TINYINT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    IF v_in_tx = 1 THEN
      ROLLBACK;
      SET v_in_tx = 0;
    END IF;
    RESIGNAL;
  END;

  -- Normalizar email vacio a NULL para evitar duplicados con string vacio
  SET p_guest_email = NULLIF(TRIM(p_guest_email), '');

  -- 1) Parsear fechas desde cadenas probando varios formatos
  SET v_check_in = COALESCE(
    STR_TO_DATE(p_check_in_str,  '%Y-%m-%d'),
    STR_TO_DATE(p_check_in_str,  '%d/%m/%Y'),
    STR_TO_DATE(p_check_in_str,  '%m/%d/%Y'),
    STR_TO_DATE(p_check_in_str,  '%d-%m-%Y'),
    STR_TO_DATE(p_check_in_str,  '%m-%d-%Y'),
    STR_TO_DATE(p_check_in_str,  '%Y/%m/%d')
  );

  SET v_check_out = COALESCE(
    STR_TO_DATE(p_check_out_str, '%Y-%m-%d'),
    STR_TO_DATE(p_check_out_str, '%d/%m/%Y'),
    STR_TO_DATE(p_check_out_str, '%m/%d/%Y'),
    STR_TO_DATE(p_check_out_str, '%d-%m-%Y'),
    STR_TO_DATE(p_check_out_str, '%m-%d-%Y'),
    STR_TO_DATE(p_check_out_str, '%Y/%m/%d')
  );

  IF v_check_in IS NULL OR v_check_out IS NULL THEN
    SELECT 'ERROR' AS status, 'invalid date format (use YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY, etc.)' AS message; LEAVE proc;
  END IF;

  SET v_nights = DATEDIFF(v_check_out, v_check_in);
  IF v_nights <= 0 THEN
    SELECT 'ERROR' AS status, 'check_out must be after check_in' AS message; LEAVE proc;
  END IF;

  IF p_adults IS NULL OR p_adults < 1 THEN
    SELECT 'ERROR' AS status, 'at least 1 adult is required' AS message; LEAVE proc;
  END IF;

  -- Email formato (solo si se proporciona)
  IF p_guest_email IS NOT NULL AND p_guest_email <> '' THEN
    IF p_guest_email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$' THEN
      SELECT 'ERROR' AS status, 'invalid email format' AS message; LEAVE proc;
    END IF;
  END IF;

  -- 2) Hospedaje y compania
  SELECT p.id_property, p.id_company, c.code
    INTO v_id_property, v_id_company, v_company_code
  FROM property p
  JOIN company c ON c.id_company = p.id_company
  WHERE p.code = p_property_code
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SELECT 'ERROR' AS status, 'Unknown property code' AS message; LEAVE proc;
  END IF;

  IF p_total_cents_override IS NOT NULL AND p_total_cents_override >= 0 THEN
    SET v_has_total_override = 1;
  END IF;

  IF p_lodging_catalog_id IS NULL OR p_lodging_catalog_id = 0 THEN
    IF NOT (v_has_total_override = 1 AND GREATEST(p_total_cents_override, 0) = 0) THEN
      SELECT 'ERROR' AS status, 'Lodging catalog is required' AS message; LEAVE proc;
    END IF;
  END IF;

  IF p_lodging_catalog_id IS NOT NULL AND p_lodging_catalog_id > 0 THEN
    SELECT pslc.id_sale_item_catalog
      INTO v_lodging_catalog_id
    FROM pms_settings_lodging_catalog pslc
    WHERE pslc.id_company = v_id_company
      AND (pslc.id_property = v_id_property OR pslc.id_property IS NULL)
      AND pslc.id_sale_item_catalog = p_lodging_catalog_id
      AND pslc.deleted_at IS NULL
      AND pslc.is_active = 1
    LIMIT 1;

    IF v_lodging_catalog_id IS NULL THEN
      SELECT 'ERROR' AS status, 'Lodging catalog not allowed for property' AS message; LEAVE proc;
    END IF;
  END IF;

  -- 3) Habitacion + categoria + precio base
  SELECT r.id_room, r.id_category, COALESCE(rc.default_base_price_cents,0), COALESCE(r.id_rateplan, rc.id_rateplan)
    INTO v_id_room, v_id_category, v_base_cents, v_id_rateplan
  FROM room r
  JOIN roomcategory rc ON rc.id_category = r.id_category
  WHERE r.id_property = v_id_property
    AND r.code = p_room_code
  LIMIT 1;

  IF v_id_room IS NULL THEN
    SELECT 'ERROR' AS status, 'Unknown room code for that property' AS message; LEAVE proc;
  END IF;

  SET v_rate_breakdown = NULL;
  IF v_id_rateplan IS NOT NULL THEN
    CALL sp_rateplan_calc_total(
      v_id_property,
      v_id_rateplan,
      v_id_room,
      v_id_category,
      v_check_in,
      v_check_out,
      v_lodging_total,
      v_base_cents,
      v_rate_breakdown
    );
    SET v_use_rateplan = 1;
  ELSE
    SET v_lodging_total = v_base_cents * v_nights;
  END IF;
  IF p_fixed_child_total_cents IS NOT NULL AND p_fixed_child_total_cents > 0 THEN
    SET v_fixed_child_total = p_fixed_child_total_cents;
  ELSEIF p_fixed_child_unit_price_cents IS NOT NULL AND p_fixed_child_unit_price_cents > 0 THEN
    SET v_fixed_child_total = p_fixed_child_unit_price_cents * v_nights;
  ELSE
    SET v_fixed_child_total = 0;
  END IF;
  SET v_total_cents = v_lodging_total + v_fixed_child_total;
  IF v_has_total_override = 1 THEN
    SET v_total_cents = GREATEST(p_total_cents_override, 0);
    SET v_lodging_total = v_total_cents - v_fixed_child_total;
    IF v_lodging_total < 0 THEN
      SET v_lodging_total = 0;
    END IF;
  END IF;

  -- 4) Disponibilidad [in, out)
  SELECT COUNT(*) INTO v_overlap_cnt
  FROM reservation
  WHERE id_room = v_id_room
    AND (deleted_at IS NULL)
    AND (is_active IS NULL OR is_active = 1)
    AND COALESCE(status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
    AND NOT (check_out_date <= v_check_in OR check_in_date >= v_check_out);

  IF v_overlap_cnt > 0 THEN
    SELECT 'ERROR' AS status, 'Room not available for requested dates' AS message; LEAVE proc;
  END IF;

  SELECT COUNT(*) INTO v_block_overlap_cnt
  FROM room_block
  WHERE id_room = v_id_room
    AND deleted_at IS NULL
    AND is_active = 1
    AND start_date < v_check_out
    AND end_date > v_check_in;

  IF v_block_overlap_cnt > 0 THEN
    SELECT 'ERROR' AS status, 'Room is blocked for requested dates' AS message; LEAVE proc;
  END IF;

  -- 5) Resolver id_user efectivo (3 por defecto, luego alguno activo, luego cualquiera)
  SET v_id_user_eff = NULL;
  IF p_id_user IS NOT NULL AND p_id_user <> 0 THEN
    SELECT id_user INTO v_id_user_eff FROM app_user WHERE id_user = p_id_user LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SELECT id_user INTO v_id_user_eff FROM app_user WHERE id_user = 3 LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SELECT id_user INTO v_id_user_eff FROM app_user WHERE is_active=1 ORDER BY id_user LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SELECT id_user INTO v_id_user_eff FROM app_user ORDER BY id_user LIMIT 1;
  END IF;
  IF v_id_user_eff IS NULL THEN
    SELECT 'ERROR' AS status, 'no app_user available to assign' AS message; LEAVE proc;
  END IF;

  CALL sp_authz_assert(
    v_company_code,
    v_id_user_eff,
    'reservations.create',
    p_property_code,
    NULL
  );

  START TRANSACTION;
  SET v_in_tx = 1;

  -- 6) Apellidos combinados (opcionales)
  SET v_last_name = NULLIF(TRIM(COALESCE(p_guest_last_name, '')), '');
  SET v_maiden_name = NULLIF(TRIM(COALESCE(p_guest_maiden_name, '')), '');
  SET v_full_name = NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(COALESCE(p_guest_names, '')), ''), v_last_name, v_maiden_name)), '');

  SET v_source = NULLIF(TRIM(COALESCE(p_source, '')), '');
  SET v_source_name = NULL;

  SET v_id_ota_account = NULL;
  SET v_id_reservation_source = NULL;
  SET v_ota_platform = NULL;
  IF p_id_ota_account IS NOT NULL AND p_id_ota_account > 0 THEN
    SELECT oa.id_ota_account, oa.platform
      INTO v_id_ota_account, v_ota_platform
    FROM ota_account oa
    WHERE oa.id_ota_account = p_id_ota_account
      AND oa.id_company = v_id_company
      AND oa.deleted_at IS NULL
      AND oa.is_active = 1
    LIMIT 1;

    IF v_id_ota_account IS NULL OR v_id_ota_account <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid ota account for company';
    END IF;

    SET v_ota_platform = LOWER(TRIM(COALESCE(v_ota_platform, 'other')));
      SET v_source = CASE
        WHEN v_ota_platform = 'booking' THEN 'Booking'
        WHEN v_ota_platform = 'airbnb' OR v_ota_platform = 'abb' THEN 'Airbnb'
        WHEN v_ota_platform = 'expedia' THEN 'Expedia'
        ELSE 'OTA'
      END;
    SET v_id_reservation_source = NULL;
  ELSE
    SELECT oa.id_ota_account, oa.platform
      INTO v_id_ota_account, v_ota_platform
    FROM ota_account_lodging_catalog oalc
    JOIN ota_account oa
      ON oa.id_ota_account = oalc.id_ota_account
     AND oa.id_company = v_id_company
     AND oa.deleted_at IS NULL
     AND oa.is_active = 1
    WHERE oalc.id_line_item_catalog = v_lodging_catalog_id
      AND oalc.deleted_at IS NULL
      AND oalc.is_active = 1
    ORDER BY oalc.sort_order, oa.id_ota_account
    LIMIT 1;

    IF v_id_ota_account IS NOT NULL AND v_id_ota_account > 0 THEN
      SET v_ota_platform = LOWER(TRIM(COALESCE(v_ota_platform, 'other')));
      SET v_source = CASE
        WHEN v_ota_platform = 'booking' THEN 'Booking'
        WHEN v_ota_platform = 'airbnb' OR v_ota_platform = 'abb' THEN 'Airbnb'
        WHEN v_ota_platform = 'expedia' THEN 'Expedia'
        ELSE 'OTA'
      END;
      SET v_id_reservation_source = NULL;
    ELSE
      IF v_source IS NOT NULL AND v_source REGEXP '^[0-9]+$' THEN
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source, v_source_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_reservation_source = CAST(v_source AS UNSIGNED)
          AND rsc.id_company = v_id_company
          AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
          AND rsc.deleted_at IS NULL
          AND rsc.is_active = 1
        ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END
        LIMIT 1;
      ELSEIF v_source IS NOT NULL THEN
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source, v_source_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_company = v_id_company
          AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
          AND TRIM(COALESCE(rsc.source_name, '')) COLLATE utf8mb4_unicode_ci
              = TRIM(COALESCE(v_source, '')) COLLATE utf8mb4_unicode_ci
          AND rsc.deleted_at IS NULL
          AND rsc.is_active = 1
        ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END
        LIMIT 1;
      END IF;

      IF v_id_reservation_source IS NULL OR v_id_reservation_source <= 0 THEN
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source, v_source_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_company = v_id_company
          AND rsc.id_property IS NULL
          AND TRIM(COALESCE(rsc.source_name, '')) COLLATE utf8mb4_unicode_ci = 'Directo'
          AND rsc.deleted_at IS NULL
        LIMIT 1;
      END IF;

      IF v_id_reservation_source IS NULL OR v_id_reservation_source <= 0 THEN
        SET v_id_reservation_source = NULL;
        SET v_source_name = 'Directo';
      END IF;

      SET v_source = COALESCE(NULLIF(TRIM(v_source_name), ''), 'Directo');
    END IF;
  END IF;

  -- 7) Reusar guest por email (evita duplicados) si se proporciona
  IF p_guest_email IS NOT NULL AND p_guest_email <> '' THEN
    SELECT id_guest INTO v_id_guest
    FROM guest
    WHERE email = p_guest_email
    LIMIT 1;

    IF v_id_guest IS NULL THEN
      INSERT INTO guest (email, phone, names, last_name, maiden_name, full_name, language, is_active, created_at, updated_at)
      VALUES (p_guest_email, p_guest_phone, NULLIF(p_guest_names,''), v_last_name, v_maiden_name, v_full_name, 'es', 1, NOW(), NOW());
      SET v_id_guest = LAST_INSERT_ID();
    ELSE
      UPDATE guest
        SET names = COALESCE(NULLIF(p_guest_names,''), names),
            last_name = COALESCE(v_last_name, last_name),
            maiden_name = COALESCE(v_maiden_name, maiden_name),
            full_name = NULLIF(TRIM(CONCAT_WS(' ',
              COALESCE(NULLIF(p_guest_names,''), names),
              COALESCE(v_last_name, last_name),
              COALESCE(v_maiden_name, maiden_name)
            )), ''),
            phone = COALESCE(NULLIF(p_guest_phone,''), phone),
            updated_at = NOW()
      WHERE id_guest = v_id_guest;
    END IF;
  ELSE
    SET v_id_guest = NULL;
    IF (p_guest_names IS NOT NULL AND p_guest_names <> '')
      OR (p_guest_phone IS NOT NULL AND p_guest_phone <> '')
      OR (v_last_name IS NOT NULL AND v_last_name <> '') THEN
      INSERT INTO guest (email, phone, names, last_name, maiden_name, full_name, language, is_active, created_at, updated_at)
      VALUES (NULL, NULLIF(p_guest_phone,''), NULLIF(p_guest_names,''), v_last_name, v_maiden_name, v_full_name, 'es', 1, NOW(), NOW());
      SET v_id_guest = LAST_INSERT_ID();
    END IF;
  END IF;

  -- 8) Código y JSON-like strings
  SET v_code = CONCAT('SP-', UPPER(HEX(FLOOR(RAND()*99999999))));
  IF v_has_total_override = 1 THEN
    SET v_snapshot = CONCAT(
      '{',
        '"base_nightly_cents":', v_base_cents, ',',
        '"nights":', v_nights, ',',
        '"calc":"override_total"',
      '}'
    );
    SET v_breakdown = CONCAT(
      '[{"date_range":["',
        DATE_FORMAT(v_check_in,'%Y-%m-%d'), '","', DATE_FORMAT(v_check_out,'%Y-%m-%d'),
        '"],"nightly_cents":', v_base_cents, ',"nights":', v_nights, ',"amount_cents":', v_total_cents, '}]'
    );
  ELSEIF v_use_rateplan = 1 AND v_rate_breakdown IS NOT NULL AND v_rate_breakdown <> '' THEN
    SET v_snapshot = CONCAT(
      '{',
        '"base_nightly_cents":', v_base_cents, ',',
        '"nights":', v_nights, ',',
        '"calc":"rateplan",',
        '"rateplan_id":', v_id_rateplan,
      '}'
    );
    SET v_breakdown = v_rate_breakdown;
  ELSE
    SET v_snapshot = CONCAT(
      '{',
        '"base_nightly_cents":', v_base_cents, ',',
        '"nights":', v_nights, ',',
        '"calc":"default_base_price_cents"',
      '}'
    );
    SET v_breakdown = CONCAT(
      '[{"date_range":["',
        DATE_FORMAT(v_check_in,'%Y-%m-%d'), '","', DATE_FORMAT(v_check_out,'%Y-%m-%d'),
        '"],"nightly_cents":', v_base_cents, ',"nights":', v_nights, ',"amount_cents":', v_total_cents, '}]'
    );
  END IF;

  -- 9) Insert reserva con fechas YA normalizadas
  INSERT INTO reservation (
    id_user, id_guest, id_room, id_property, id_category,
    code, status, source, id_ota_account, id_reservation_source,
    check_in_date, check_out_date,
    adults, children, currency,
    total_price_cents, balance_due_cents,
    rate_snapshot_json, price_breakdown_json,
    is_active, created_at, updated_at
  ) VALUES (
    v_id_user_eff, v_id_guest, v_id_room, v_id_property, v_id_category,
    v_code, 'confirmado', v_source, v_id_ota_account, v_id_reservation_source,
    v_check_in, v_check_out,
    p_adults, COALESCE(p_children,0), 'MXN',
    v_total_cents, v_total_cents,
    v_snapshot, v_breakdown,
    1, NOW(), NOW()
  );

  SET v_id_res = LAST_INSERT_ID();

  IF v_total_cents > 0 THEN
    -- 10.1) Crear folio en cero; el total real se calcula desde line_item
    INSERT INTO folio (
      id_reservation,
      folio_name,
      status,
      currency,
      total_cents,
      balance_cents,
      due_date,
      is_active,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_id_res,
      'Principal',
      'open',
      'MXN',
      0,
      0,
      v_check_out,
      1,
      NOW(),
      p_id_user,
      NOW()
    );
    SET v_id_folio = LAST_INSERT_ID();

    CALL sp_sale_item_upsert(
      'create',
      0,
      v_id_folio,
      v_id_res,
      v_lodging_catalog_id,
      CONCAT('Reserva ', v_code),
      v_check_in,
      v_nights,
      CASE
        WHEN v_has_total_override = 1 OR v_use_rateplan = 1
          THEN CEILING(v_lodging_total / v_nights)
        ELSE v_base_cents
      END,
      CASE
        WHEN v_has_total_override = 1 OR v_use_rateplan = 1
          THEN (CEILING(v_lodging_total / v_nights) * v_nights) - v_lodging_total
        ELSE 0
      END,
      'posted',
      p_id_user
    );

    IF (p_fixed_child_total_cents IS NOT NULL AND p_fixed_child_total_cents > 0)
       OR (p_fixed_child_unit_price_cents IS NOT NULL AND p_fixed_child_unit_price_cents > 0) THEN
      SELECT id_line_item
        INTO v_parent_sale_item_id
      FROM line_item
      WHERE id_folio = v_id_folio
        AND id_line_item_catalog = v_lodging_catalog_id
        AND deleted_at IS NULL
      ORDER BY id_line_item DESC
      LIMIT 1;

      SELECT id_line_item_catalog
        INTO v_child_catalog_id
      FROM line_item_catalog_parent lcp
      JOIN line_item_catalog lic
        ON lic.id_line_item_catalog = lcp.id_sale_item_catalog
       AND lic.catalog_type IN ('sale_item','tax_rule','payment','obligation','income')
      WHERE lcp.id_parent_sale_item_catalog = v_lodging_catalog_id
        AND lcp.deleted_at IS NULL
        AND lcp.is_active = 1
        AND lcp.percent_value IS NULL
        AND lic.deleted_at IS NULL
        AND lic.is_active = 1
      ORDER BY lic.id_line_item_catalog
      LIMIT 1;

      IF v_child_catalog_id IS NOT NULL THEN
        SET v_parent_item_name = NULL;
        SET v_child_item_name = NULL;
        SET v_child_desc = NULL;

        SELECT item_name
          INTO v_parent_item_name
        FROM line_item_catalog
        WHERE id_line_item_catalog = v_lodging_catalog_id
        LIMIT 1;

        SELECT item_name
          INTO v_child_item_name
        FROM line_item_catalog
        WHERE id_line_item_catalog = v_child_catalog_id
        LIMIT 1;

        SET v_child_desc = CONCAT(
          COALESCE(NULLIF(TRIM(v_child_item_name), ''), CONCAT('Catalog#', v_child_catalog_id)),
          ' / ',
          COALESCE(NULLIF(TRIM(v_parent_item_name), ''), CONCAT('Catalog#', v_lodging_catalog_id))
        );

        SELECT id_line_item
          INTO v_child_sale_item_id
        FROM line_item
        WHERE id_folio = v_id_folio
          AND id_line_item_catalog = v_child_catalog_id
          AND (service_date <=> v_check_in)
          AND deleted_at IS NULL
        ORDER BY id_line_item DESC
        LIMIT 1;

        IF v_child_sale_item_id IS NOT NULL THEN
          CALL sp_sale_item_upsert(
            'update',
            v_child_sale_item_id,
            v_id_folio,
            v_id_res,
            v_child_catalog_id,
            v_child_desc,
            v_check_in,
            CASE
              WHEN p_fixed_child_total_cents IS NOT NULL AND p_fixed_child_total_cents > 0 THEN 1
              ELSE v_nights
            END,
            CASE
              WHEN p_fixed_child_total_cents IS NOT NULL AND p_fixed_child_total_cents > 0 THEN p_fixed_child_total_cents
              ELSE p_fixed_child_unit_price_cents
            END,
            0,
            'posted',
            p_id_user
          );
        ELSE
          CALL sp_sale_item_upsert(
            'create',
            0,
            v_id_folio,
            v_id_res,
            v_child_catalog_id,
            v_child_desc,
            v_check_in,
            CASE
              WHEN p_fixed_child_total_cents IS NOT NULL AND p_fixed_child_total_cents > 0 THEN 1
              ELSE v_nights
            END,
            CASE
              WHEN p_fixed_child_total_cents IS NOT NULL AND p_fixed_child_total_cents > 0 THEN p_fixed_child_total_cents
              ELSE p_fixed_child_unit_price_cents
            END,
            0,
            'posted',
            p_id_user
          );
        END IF;
      END IF;
    END IF;

    CALL sp_line_item_percent_derived_upsert(
      v_id_folio,
      v_id_res,
      v_lodging_catalog_id,
      v_check_in,
      p_id_user
    );
  END IF;

  COMMIT;
  SET v_in_tx = 0;

  -- 10) Salida con contexto
  SELECT
    r.*,
    g.email       AS guest_email,
    g.names       AS guest_names,
    g.last_name   AS guest_last_name,
    g.phone       AS guest_phone,
    rm.code       AS room_code,
    rm.name       AS room_name,
    rc.code       AS category_code,
    rc.name       AS category_name,
    pr.code       AS property_code,
    pr.name       AS property_name
  FROM reservation r
  LEFT JOIN guest g    ON g.id_guest     = r.id_guest
  JOIN room rm         ON rm.id_room     = r.id_room
  JOIN roomcategory rc ON rc.id_category = r.id_category
  JOIN property pr     ON pr.id_property = r.id_property
  WHERE r.id_reservation = v_id_res;
END $$

DELIMITER ;
