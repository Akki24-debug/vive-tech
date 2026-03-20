DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_upsert` $$
CREATE PROCEDURE `sp_sale_item_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_sale_item BIGINT,
  IN p_id_folio BIGINT,
  IN p_id_reservation BIGINT,
  IN p_id_sale_item_catalog BIGINT,
  IN p_description TEXT,
  IN p_service_date DATE,
  IN p_quantity DECIMAL(18,6),
  IN p_unit_price_cents INT,
  IN p_discount_amount_cents INT,
  IN p_status VARCHAR(32),
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_id_folio BIGINT;
  DECLARE v_currency VARCHAR(10);
  DECLARE v_catalog_id BIGINT;
  DECLARE v_default_price INT DEFAULT 0;
  DECLARE v_catalog_type VARCHAR(32);
  DECLARE v_catalog_name VARCHAR(255);
  DECLARE v_qty DECIMAL(18,6) DEFAULT 1;
  DECLARE v_unit_price INT DEFAULT 0;
  DECLARE v_discount INT DEFAULT 0;
  DECLARE v_amount_cents INT DEFAULT 0;
  DECLARE v_service_date DATE;
  DECLARE v_target_sale_item BIGINT;
  DECLARE v_item_type VARCHAR(32) DEFAULT 'sale_item';
  DECLARE v_allow_negative TINYINT DEFAULT 0;
  DECLARE v_skip_percent_recalc TINYINT DEFAULT 0;
  DECLARE v_recalc_parent_catalog_id BIGINT;
  DECLARE v_prev_skip_percent_recalc TINYINT DEFAULT 0;
  DECLARE v_reservation_id_eff BIGINT DEFAULT NULL;
  DECLARE v_payment_catalog_is_ota TINYINT DEFAULT 0;
  DECLARE v_ota_from_payment_id BIGINT DEFAULT NULL;
  DECLARE v_ota_from_lodging_id BIGINT DEFAULT NULL;
  DECLARE v_ota_platform_detected VARCHAR(32) DEFAULT NULL;
  DECLARE v_source_detected VARCHAR(120) DEFAULT NULL;
  DECLARE v_company_code VARCHAR(100) DEFAULT NULL;
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;

  IF p_action IS NULL OR TRIM(p_action) = '' THEN
    SET p_action = 'create';
  END IF;

  IF p_action NOT IN ('create','update','delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unsupported action for sale_item';
  END IF;

  SET v_skip_percent_recalc = COALESCE(@pms_skip_percent_derived, 0);

  IF p_action IN ('create','update') THEN
    IF p_id_folio IS NULL OR p_id_folio = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio id is required';
    END IF;

    SELECT f.id_folio, f.currency, c.code, p.code
      INTO v_id_folio, v_currency, v_company_code, v_property_code
    FROM folio f
    JOIN reservation r ON r.id_reservation = f.id_reservation
    JOIN property p ON p.id_property = r.id_property
    JOIN company c ON c.id_company = p.id_company
    WHERE f.id_folio = p_id_folio
      AND f.deleted_at IS NULL
    LIMIT 1;

    IF v_id_folio IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio not found';
    END IF;
  END IF;

  IF p_action = 'delete' THEN
    IF p_id_sale_item IS NULL OR p_id_sale_item = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sale_item id is required';
    END IF;
    SELECT li.id_folio
      INTO v_id_folio
    FROM line_item li
    WHERE li.id_line_item = p_id_sale_item
      AND li.deleted_at IS NULL
    LIMIT 1;
    IF v_id_folio IS NULL OR v_id_folio <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sale_item not found';
    END IF;
    SELECT f.currency, c.code, p.code
      INTO v_currency, v_company_code, v_property_code
    FROM folio f
    JOIN reservation r ON r.id_reservation = f.id_reservation
    JOIN property p ON p.id_property = r.id_property
    JOIN company c ON c.id_company = p.id_company
    WHERE f.id_folio = v_id_folio
      AND f.deleted_at IS NULL
    LIMIT 1;
  END IF;

  CALL sp_authz_assert(
    v_company_code,
    p_created_by,
    'reservations.post_charge',
    v_property_code,
    NULL
  );

  IF p_action = 'create' THEN
    IF p_id_sale_item_catalog IS NULL OR p_id_sale_item_catalog = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sale_item_catalog id is required';
    END IF;

    SELECT id_line_item_catalog, COALESCE(default_unit_price_cents, 0), catalog_type, item_name, COALESCE(allow_negative, 0)
      INTO v_catalog_id, v_default_price, v_catalog_type, v_catalog_name, v_allow_negative
    FROM line_item_catalog
    WHERE id_line_item_catalog = p_id_sale_item_catalog
      AND deleted_at IS NULL
      AND is_active = 1
    LIMIT 1;

    IF v_catalog_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'catalog item not found';
    END IF;

    SET v_item_type = CASE LOWER(TRIM(COALESCE(v_catalog_type, '')))
      WHEN 'sale_item' THEN 'sale_item'
      WHEN 'tax_rule' THEN 'tax_item'
      WHEN 'tax_item' THEN 'tax_item'
      WHEN 'tax' THEN 'tax_item'
      WHEN 'impuesto' THEN 'tax_item'
      WHEN 'impuestos' THEN 'tax_item'
      WHEN 'payment' THEN 'payment'
      WHEN 'pago' THEN 'payment'
      WHEN 'obligation' THEN 'obligation'
      WHEN 'obligacion' THEN 'obligation'
      WHEN 'obligación' THEN 'obligation'
      WHEN 'income' THEN 'income'
      WHEN 'ingreso' THEN 'income'
      ELSE ''
    END;

    IF v_item_type = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unsupported catalog type for line item';
    END IF;

    SET v_qty = COALESCE(p_quantity, 1);
    SET v_unit_price = COALESCE(p_unit_price_cents, v_default_price, 0);
    SET v_discount = COALESCE(p_discount_amount_cents, 0);
    SET v_amount_cents = (v_qty * v_unit_price) - v_discount;
    SET v_service_date = p_service_date;

    IF COALESCE(v_amount_cents, 0) <= 0
       AND COALESCE(v_allow_negative, 0) = 0 THEN
      SELECT
        CAST(NULL AS SIGNED) AS id_sale_item,
        CAST(NULL AS SIGNED) AS id_user,
        CAST(NULL AS SIGNED) AS id_folio,
        CAST(NULL AS SIGNED) AS id_reservation,
        CAST(NULL AS SIGNED) AS id_sale_item_catalog,
        CAST(NULL AS SIGNED) AS id_parent_sale_item,
        CAST(NULL AS CHAR) AS description,
        CAST(NULL AS DATE) AS service_date,
        CAST(NULL AS DECIMAL(18,6)) AS quantity,
        CAST(NULL AS SIGNED) AS unit_price_cents,
        CAST(NULL AS SIGNED) AS amount_cents,
        CAST(NULL AS CHAR) AS currency,
        CAST(NULL AS SIGNED) AS discount_amount_cents,
        CAST(NULL AS CHAR) AS revenue_account,
        CAST(NULL AS CHAR) AS status,
        CAST(NULL AS CHAR) AS external_ref,
        CAST(NULL AS SIGNED) AS is_active,
        CAST(NULL AS DATETIME) AS deleted_at,
        CAST(NULL AS DATETIME) AS created_at,
        CAST(NULL AS SIGNED) AS created_by,
        CAST(NULL AS DATETIME) AS updated_at
      LIMIT 0;
      LEAVE proc;
    END IF;

    INSERT INTO line_item (
      item_type,
      id_user,
      id_folio,
      id_line_item_catalog,
      description,
      service_date,
      quantity,
      unit_price_cents,
      amount_cents,
      currency,
      method,
      discount_amount_cents,
      revenue_account_code,
      status,
      external_ref,
      is_active,
      created_at,
      created_by,
      updated_at
    ) VALUES (
      v_item_type,
      p_created_by,
      v_id_folio,
      v_catalog_id,
      p_description,
      v_service_date,
      v_qty,
      v_unit_price,
      v_amount_cents,
      v_currency,
      CASE WHEN v_item_type = 'payment' THEN v_catalog_name ELSE NULL END,
      v_discount,
      NULL,
      COALESCE(NULLIF(p_status,''), 'posted'),
      NULL,
      1,
      NOW(),
      p_created_by,
      NOW()
    );

    SET v_target_sale_item = LAST_INSERT_ID();
  END IF;

  IF p_action = 'update' THEN
    IF p_id_sale_item IS NULL OR p_id_sale_item = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sale_item id is required';
    END IF;

    SELECT si.id_line_item_catalog,
           si.quantity,
           si.unit_price_cents,
           si.discount_amount_cents,
           si.service_date,
           si.item_type
      INTO v_catalog_id, v_qty, v_unit_price, v_discount, v_service_date, v_item_type
    FROM line_item si
    WHERE si.id_line_item = p_id_sale_item
      AND si.id_folio = v_id_folio
      AND si.deleted_at IS NULL
    LIMIT 1;

    IF v_catalog_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sale_item not found';
    END IF;

    SET v_allow_negative = 0;
    SELECT COALESCE(lic.allow_negative, 0)
      INTO v_allow_negative
    FROM line_item_catalog lic
    WHERE lic.id_line_item_catalog = v_catalog_id
    LIMIT 1;

    IF p_id_sale_item_catalog IS NOT NULL AND p_id_sale_item_catalog <> 0 THEN
      SELECT id_line_item_catalog, COALESCE(default_unit_price_cents, 0), catalog_type, item_name, COALESCE(allow_negative, 0)
        INTO v_catalog_id, v_default_price, v_catalog_type, v_catalog_name, v_allow_negative
      FROM line_item_catalog
      WHERE id_line_item_catalog = p_id_sale_item_catalog
        AND deleted_at IS NULL
        AND is_active = 1
      LIMIT 1;

      SET v_item_type = CASE LOWER(TRIM(COALESCE(v_catalog_type, '')))
        WHEN 'sale_item' THEN 'sale_item'
        WHEN 'tax_rule' THEN 'tax_item'
        WHEN 'tax_item' THEN 'tax_item'
        WHEN 'tax' THEN 'tax_item'
        WHEN 'impuesto' THEN 'tax_item'
        WHEN 'impuestos' THEN 'tax_item'
        WHEN 'payment' THEN 'payment'
        WHEN 'pago' THEN 'payment'
        WHEN 'obligation' THEN 'obligation'
        WHEN 'obligacion' THEN 'obligation'
        WHEN 'obligación' THEN 'obligation'
        WHEN 'income' THEN 'income'
        WHEN 'ingreso' THEN 'income'
        ELSE ''
      END;

      IF v_catalog_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'catalog item not found';
      END IF;
      IF v_item_type = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unsupported catalog type for line item';
      END IF;
    END IF;

    SET v_qty = COALESCE(p_quantity, v_qty);
    SET v_unit_price = COALESCE(p_unit_price_cents, v_unit_price);
    SET v_discount = COALESCE(p_discount_amount_cents, v_discount);
    SET v_amount_cents = (v_qty * v_unit_price) - v_discount;
    SET v_service_date = COALESCE(p_service_date, v_service_date);
    IF v_item_type = 'payment' AND (v_catalog_name IS NULL OR TRIM(v_catalog_name) = '') THEN
      SELECT item_name INTO v_catalog_name
      FROM line_item_catalog
      WHERE id_line_item_catalog = v_catalog_id
      LIMIT 1;
    END IF;

    IF COALESCE(v_amount_cents, 0) <= 0
       AND COALESCE(v_allow_negative, 0) = 0 THEN
      SET p_action = 'delete';
    ELSE
      UPDATE line_item
         SET id_line_item_catalog = v_catalog_id,
             item_type = v_item_type,
             method = CASE
               WHEN v_item_type = 'payment' THEN COALESCE(v_catalog_name, method)
               ELSE method
             END,
             description = COALESCE(p_description, description),
             service_date = v_service_date,
             quantity = v_qty,
             unit_price_cents = v_unit_price,
             discount_amount_cents = v_discount,
             amount_cents = v_amount_cents,
             status = COALESCE(NULLIF(p_status,''), status),
             updated_at = NOW()
       WHERE id_line_item = p_id_sale_item
         AND id_folio = v_id_folio;

      SET v_target_sale_item = p_id_sale_item;
    END IF;
  END IF;

  IF p_action = 'delete' THEN
    IF p_id_sale_item IS NULL OR p_id_sale_item = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sale_item id is required';
    END IF;

    SELECT id_folio, id_line_item_catalog, service_date
      INTO v_id_folio, v_catalog_id, v_service_date
    FROM line_item
    WHERE id_line_item = p_id_sale_item
    LIMIT 1;

    UPDATE line_item
       SET is_active = 0,
           status = 'void',
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_line_item = p_id_sale_item
       AND deleted_at IS NULL;

    IF v_skip_percent_recalc = 0
       AND v_id_folio IS NOT NULL
       AND v_catalog_id IS NOT NULL
       AND v_catalog_id > 0 THEN
      SET v_prev_skip_percent_recalc = COALESCE(@pms_skip_percent_derived, 0);
      SET @pms_skip_percent_derived = 1;

      DROP TEMPORARY TABLE IF EXISTS tmp_siu_recalc_parents;
      CREATE TEMPORARY TABLE tmp_siu_recalc_parents (
        parent_catalog_id BIGINT PRIMARY KEY,
        processed TINYINT NOT NULL DEFAULT 0
      ) ENGINE=MEMORY;

      INSERT IGNORE INTO tmp_siu_recalc_parents (parent_catalog_id)
      SELECT lcp.id_parent_sale_item_catalog
      FROM line_item_catalog_parent lcp
      WHERE lcp.id_sale_item_catalog = v_catalog_id
        AND lcp.deleted_at IS NULL
        AND lcp.is_active = 1
        AND lcp.percent_value IS NOT NULL;

      INSERT IGNORE INTO tmp_siu_recalc_parents (parent_catalog_id)
      SELECT licc.id_parent_line_item_catalog
      FROM line_item_catalog_calc licc
      WHERE licc.id_component_line_item_catalog = v_catalog_id
        AND licc.deleted_at IS NULL
        AND licc.is_active = 1;

      IF EXISTS (
        SELECT 1
        FROM line_item_catalog_parent lcp
        WHERE lcp.id_parent_sale_item_catalog = v_catalog_id
          AND lcp.deleted_at IS NULL
          AND lcp.is_active = 1
          AND lcp.percent_value IS NOT NULL
      ) THEN
        INSERT IGNORE INTO tmp_siu_recalc_parents (parent_catalog_id)
        VALUES (v_catalog_id);
      END IF;

      recalc_loop_delete: LOOP
        SET v_recalc_parent_catalog_id = NULL;
        SELECT t.parent_catalog_id
          INTO v_recalc_parent_catalog_id
        FROM tmp_siu_recalc_parents t
        WHERE t.processed = 0
        ORDER BY t.parent_catalog_id
        LIMIT 1;

        IF v_recalc_parent_catalog_id IS NULL OR v_recalc_parent_catalog_id <= 0 THEN
          LEAVE recalc_loop_delete;
        END IF;

        UPDATE tmp_siu_recalc_parents
           SET processed = 1
         WHERE parent_catalog_id = v_recalc_parent_catalog_id;

        CALL sp_line_item_percent_derived_upsert(
          v_id_folio,
          p_id_reservation,
          v_recalc_parent_catalog_id,
          v_service_date,
          p_created_by
        );
      END LOOP;

      DROP TEMPORARY TABLE IF EXISTS tmp_siu_recalc_parents;
      SET @pms_skip_percent_derived = v_prev_skip_percent_recalc;
    END IF;

    IF v_id_folio IS NOT NULL THEN
      CALL sp_folio_recalc(v_id_folio);
    END IF;

    SELECT
      id_line_item AS id_sale_item,
      id_user,
      id_folio,
      CAST(NULL AS SIGNED) AS id_reservation,
      id_line_item_catalog AS id_sale_item_catalog,
      CAST(NULL AS SIGNED) AS id_parent_sale_item,
      description,
      service_date,
      quantity,
      unit_price_cents,
      amount_cents,
      currency,
      discount_amount_cents,
      revenue_account_code AS revenue_account,
      status,
      external_ref,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    FROM line_item
    WHERE id_line_item = p_id_sale_item;

    LEAVE proc;
  END IF;

  /* No auto tax rebuild here. Tax items must be managed as regular line_item rows. */

  IF v_skip_percent_recalc = 0
     AND v_id_folio IS NOT NULL
     AND v_catalog_id IS NOT NULL
     AND v_catalog_id > 0 THEN
    SET v_prev_skip_percent_recalc = COALESCE(@pms_skip_percent_derived, 0);
    SET @pms_skip_percent_derived = 1;

    DROP TEMPORARY TABLE IF EXISTS tmp_siu_recalc_parents;
    CREATE TEMPORARY TABLE tmp_siu_recalc_parents (
      parent_catalog_id BIGINT PRIMARY KEY,
      processed TINYINT NOT NULL DEFAULT 0
    ) ENGINE=MEMORY;

    INSERT IGNORE INTO tmp_siu_recalc_parents (parent_catalog_id)
    SELECT lcp.id_parent_sale_item_catalog
    FROM line_item_catalog_parent lcp
    WHERE lcp.id_sale_item_catalog = v_catalog_id
      AND lcp.deleted_at IS NULL
      AND lcp.is_active = 1
      AND lcp.percent_value IS NOT NULL;

    INSERT IGNORE INTO tmp_siu_recalc_parents (parent_catalog_id)
    SELECT licc.id_parent_line_item_catalog
    FROM line_item_catalog_calc licc
    WHERE licc.id_component_line_item_catalog = v_catalog_id
      AND licc.deleted_at IS NULL
      AND licc.is_active = 1;

    IF EXISTS (
      SELECT 1
      FROM line_item_catalog_parent lcp
      WHERE lcp.id_parent_sale_item_catalog = v_catalog_id
        AND lcp.deleted_at IS NULL
        AND lcp.is_active = 1
        AND lcp.percent_value IS NOT NULL
    ) THEN
      INSERT IGNORE INTO tmp_siu_recalc_parents (parent_catalog_id)
      VALUES (v_catalog_id);
    END IF;

    recalc_loop_upsert: LOOP
      SET v_recalc_parent_catalog_id = NULL;
      SELECT t.parent_catalog_id
        INTO v_recalc_parent_catalog_id
      FROM tmp_siu_recalc_parents t
      WHERE t.processed = 0
      ORDER BY t.parent_catalog_id
      LIMIT 1;

      IF v_recalc_parent_catalog_id IS NULL OR v_recalc_parent_catalog_id <= 0 THEN
        LEAVE recalc_loop_upsert;
      END IF;

      UPDATE tmp_siu_recalc_parents
         SET processed = 1
       WHERE parent_catalog_id = v_recalc_parent_catalog_id;

      CALL sp_line_item_percent_derived_upsert(
        v_id_folio,
        p_id_reservation,
        v_recalc_parent_catalog_id,
        v_service_date,
        p_created_by
      );
    END LOOP;

    DROP TEMPORARY TABLE IF EXISTS tmp_siu_recalc_parents;
    SET @pms_skip_percent_derived = v_prev_skip_percent_recalc;
  END IF;

  CALL sp_folio_recalc(v_id_folio);

  /*
    Si el line item actual es de tipo payment y su catalogo esta ligado
    como concepto de pago OTA, sincroniza el source de la reservacion con
    la OTA detectada por concepto de hospedaje.
  */
  IF p_action IN ('create','update')
     AND v_item_type = 'payment'
     AND v_catalog_id IS NOT NULL
     AND v_catalog_id > 0
     AND v_id_folio IS NOT NULL
     AND v_id_folio > 0 THEN

    SET v_reservation_id_eff = NULL;
    IF p_id_reservation IS NOT NULL AND p_id_reservation > 0 THEN
      SET v_reservation_id_eff = p_id_reservation;
    ELSE
      SELECT f.id_reservation
        INTO v_reservation_id_eff
      FROM folio f
      WHERE f.id_folio = v_id_folio
      LIMIT 1;
    END IF;

    IF v_reservation_id_eff IS NOT NULL AND v_reservation_id_eff > 0 THEN
      SET v_payment_catalog_is_ota = 0;
      SET v_ota_from_payment_id = NULL;
      SET v_ota_from_lodging_id = NULL;
      SET v_ota_platform_detected = NULL;
      SET v_source_detected = NULL;

      SELECT oa.id_ota_account
        INTO v_ota_from_payment_id
      FROM reservation r
      JOIN property p
        ON p.id_property = r.id_property
      JOIN ota_account oa
        ON oa.id_company = p.id_company
       AND oa.id_property = p.id_property
       AND oa.deleted_at IS NULL
       AND oa.is_active = 1
      WHERE r.id_reservation = v_reservation_id_eff
        AND oa.id_service_fee_payment_catalog = v_catalog_id
      LIMIT 1;

      IF v_ota_from_payment_id IS NOT NULL AND v_ota_from_payment_id > 0 THEN
        SET v_payment_catalog_is_ota = 1;

        SELECT oa.id_ota_account, oa.platform
          INTO v_ota_from_lodging_id, v_ota_platform_detected
        FROM reservation r
        JOIN property p
          ON p.id_property = r.id_property
        JOIN folio f
          ON f.id_reservation = r.id_reservation
         AND f.deleted_at IS NULL
        JOIN line_item li
          ON li.id_folio = f.id_folio
         AND li.item_type = 'sale_item'
         AND li.deleted_at IS NULL
         AND li.is_active = 1
        JOIN ota_account_lodging_catalog oalc
          ON oalc.id_line_item_catalog = li.id_line_item_catalog
         AND oalc.deleted_at IS NULL
         AND oalc.is_active = 1
        JOIN ota_account oa
          ON oa.id_ota_account = oalc.id_ota_account
         AND oa.id_company = p.id_company
         AND oa.id_property = p.id_property
         AND oa.deleted_at IS NULL
         AND oa.is_active = 1
        WHERE r.id_reservation = v_reservation_id_eff
        ORDER BY oalc.sort_order, li.id_line_item, oa.id_ota_account
        LIMIT 1;

        IF v_ota_from_lodging_id IS NULL OR v_ota_from_lodging_id <= 0 THEN
          SET v_ota_from_lodging_id = v_ota_from_payment_id;
          SELECT oa.platform
            INTO v_ota_platform_detected
          FROM ota_account oa
          WHERE oa.id_ota_account = v_ota_from_lodging_id
            AND oa.deleted_at IS NULL
            AND oa.is_active = 1
          LIMIT 1;
        END IF;

        IF v_ota_from_lodging_id IS NOT NULL AND v_ota_from_lodging_id > 0 THEN
          SET v_ota_platform_detected = LOWER(TRIM(COALESCE(v_ota_platform_detected, 'other')));
          SET v_source_detected = CASE
            WHEN v_ota_platform_detected = 'booking' THEN 'Booking'
            WHEN v_ota_platform_detected = 'airbnb' OR v_ota_platform_detected = 'abb' THEN 'Airbnb'
            WHEN v_ota_platform_detected = 'expedia' THEN 'Expedia'
            ELSE 'OTA'
          END;

          UPDATE reservation
             SET id_ota_account = v_ota_from_lodging_id,
                 id_reservation_source = NULL,
                 source = v_source_detected,
                 updated_at = NOW()
           WHERE id_reservation = v_reservation_id_eff;
        END IF;
      END IF;
    END IF;
  END IF;

  SELECT
    id_line_item AS id_sale_item,
    id_user,
    id_folio,
    CAST(NULL AS SIGNED) AS id_reservation,
    id_line_item_catalog AS id_sale_item_catalog,
    CAST(NULL AS SIGNED) AS id_parent_sale_item,
    description,
    service_date,
    quantity,
    unit_price_cents,
    amount_cents,
    currency,
    discount_amount_cents,
    revenue_account_code AS revenue_account,
    status,
    external_ref,
    is_active,
    deleted_at,
    created_at,
    created_by,
    updated_at
  FROM line_item
  WHERE id_line_item = v_target_sale_item;
END $$

DELIMITER ;
