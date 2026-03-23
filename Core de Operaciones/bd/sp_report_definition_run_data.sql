DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_report_definition_run_data` $$
CREATE PROCEDURE `sp_report_definition_run_data` (
  IN p_company_code VARCHAR(100),
  IN p_id_report_config BIGINT,
  IN p_from DATE,
  IN p_to DATE,
  IN p_limit INT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_report_type VARCHAR(32);
  DECLARE v_line_item_scope VARCHAR(32);
  DECLARE v_limit INT DEFAULT 500;
  DECLARE v_select_list LONGTEXT;
  DECLARE v_where_clause LONGTEXT;
  DECLARE v_having_clause LONGTEXT;
  DECLARE v_sql LONGTEXT;
  DECLARE v_from_filter TEXT DEFAULT '';
  DECLARE v_to_filter TEXT DEFAULT '';

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_id_report_config IS NULL OR p_id_report_config <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'id_report_config is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT rc.report_type, COALESCE(rc.line_item_type_scope, 'all')
    INTO v_report_type, v_line_item_scope
  FROM report_config rc
  WHERE rc.id_report_config = p_id_report_config
    AND rc.id_company = v_company_id
    AND rc.deleted_at IS NULL
    AND rc.is_active = 1
  LIMIT 1;

  IF v_report_type IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Report not found or inactive';
  END IF;

  SET v_limit = CASE
    WHEN p_limit IS NULL OR p_limit < 1 THEN 500
    WHEN p_limit > 5000 THEN 5000
    ELSE p_limit
  END;

  IF p_from IS NOT NULL THEN
    SET v_from_filter = CONCAT(' AND COALESCE(li2.service_date, DATE(li2.created_at)) >= ''', DATE_FORMAT(p_from, '%Y-%m-%d'), '''');
  END IF;
  IF p_to IS NOT NULL THEN
    SET v_to_filter = CONCAT(' AND COALESCE(li2.service_date, DATE(li2.created_at)) <= ''', DATE_FORMAT(p_to, '%Y-%m-%d'), '''');
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_report_base;

  IF v_report_type = 'reservation' THEN
    CREATE TEMPORARY TABLE tmp_report_base AS
    SELECT
      r.id_reservation,
      r.id_guest,
      r.id_room,
      r.id_category,
      r.id_rateplan,
      r.code AS reservation_code,
      r.status AS reservation_status,
      r.source,
      r.channel_ref,
      r.check_in_date,
      r.check_out_date,
      r.nights,
      r.eta,
      r.etd,
      r.checkin_at,
      r.checkout_at,
      r.adults,
      r.children,
      r.infants,
      r.currency,
      r.deposit_due_cents,
      r.deposit_due_at,
      r.hold_until,
      r.cancel_reason,
      r.canceled_at,
      r.notes_guest,
      r.notes_internal,
      g.names AS guest_names,
      g.last_name AS guest_last_name,
      g.email AS guest_email,
      g.phone AS guest_phone,
      g.nationality AS guest_nationality,
      g.country_residence AS guest_country_residence,
      g.language AS guest_language,
      CONCAT_WS(' ', COALESCE(g.names, ''), COALESCE(g.last_name, '')) AS guest_full_name,
      rm.id_room AS room_id,
      rm.code AS room_code,
      rm.name AS room_name,
      rm.floor AS room_floor,
      rm.building AS room_building,
      rm.capacity_total AS room_capacity_total,
      rm.max_adults AS room_max_adults,
      rm.max_children AS room_max_children,
      rc.id_category AS category_id,
      rc.code AS category_code,
      rc.name AS category_name,
      rc.max_occupancy AS category_max_occupancy,
      p.id_property,
      p.code AS property_code,
      p.name AS property_name,
      p.city AS property_city,
      p.state AS property_state,
      p.country AS property_country,
      p.timezone AS property_timezone,
      p.currency AS property_currency,
      COALESCE(r.total_price_cents, 0) AS total_price_cents,
      COALESCE(r.balance_due_cents, 0) AS balance_due_cents,
      COALESCE(li_sum.charges_cents, 0) AS charges_cents,
      COALESCE(li_sum.taxes_cents, 0) AS taxes_cents,
      COALESCE(li_sum.payments_cents, 0) AS payments_cents,
      COALESCE(li_sum.obligations_cents, 0) AS obligations_cents,
      COALESCE(li_sum.incomes_cents, 0) AS incomes_cents,
      (
        COALESCE(li_sum.charges_cents, 0)
        + COALESCE(li_sum.taxes_cents, 0)
        + COALESCE(li_sum.obligations_cents, 0)
        - COALESCE(li_sum.payments_cents, 0)
        - COALESCE(li_sum.incomes_cents, 0)
      ) AS net_cents,
      r.created_at,
      COALESCE(r.check_in_date, DATE(r.created_at)) AS sort_date
    FROM reservation r
    JOIN property p
      ON p.id_property = r.id_property
     AND p.deleted_at IS NULL
     AND p.id_company = v_company_id
    LEFT JOIN room rm
      ON rm.id_room = r.id_room
    LEFT JOIN roomcategory rc
      ON rc.id_category = r.id_category
    LEFT JOIN guest g
      ON g.id_guest = r.id_guest
    LEFT JOIN (
      SELECT
        f.id_reservation,
        SUM(CASE WHEN li.item_type = 'sale_item' THEN li.amount_cents ELSE 0 END) AS charges_cents,
        SUM(CASE WHEN li.item_type = 'tax_item' THEN li.amount_cents ELSE 0 END) AS taxes_cents,
        SUM(CASE WHEN li.item_type = 'payment' THEN li.amount_cents ELSE 0 END) AS payments_cents,
        SUM(CASE WHEN li.item_type = 'obligation' THEN li.amount_cents ELSE 0 END) AS obligations_cents,
        SUM(CASE WHEN li.item_type = 'income' THEN li.amount_cents ELSE 0 END) AS incomes_cents
      FROM folio f
      JOIN line_item li
        ON li.id_folio = f.id_folio
       AND li.deleted_at IS NULL
       AND li.is_active = 1
      WHERE f.deleted_at IS NULL
      GROUP BY f.id_reservation
    ) li_sum
      ON li_sum.id_reservation = r.id_reservation
    WHERE r.deleted_at IS NULL
      AND (p_from IS NULL OR r.check_in_date >= p_from)
      AND (p_to IS NULL OR r.check_in_date <= p_to);

  ELSEIF v_report_type = 'line_item' THEN
    CREATE TEMPORARY TABLE tmp_report_base AS
    SELECT
      li.id_line_item,
      li.item_type,
      li.status AS line_item_status,
      li.reference AS line_item_reference,
      li.external_ref AS line_item_external_ref,
      li.method AS line_item_method,
      li.notes AS line_item_notes,
      COALESCE(li.service_date, DATE(li.created_at)) AS service_date,
      li.quantity,
      li.unit_price_cents,
      li.discount_amount_cents,
      li.amount_cents,
      li.paid_cents,
      li.currency,
      li.id_folio,
      li.id_line_item_catalog,
      lic.item_name AS catalog_name,
      cat.category_name AS subcategory_name,
      parent.category_name AS category_name,
      r.status AS reservation_status,
      r.source AS reservation_source,
      r.check_in_date,
      r.check_out_date,
      r.nights,
      r.adults,
      r.children,
      r.infants,
      r.id_guest,
      g.names AS guest_names,
      g.last_name AS guest_last_name,
      g.email AS guest_email,
      g.phone AS guest_phone,
      g.nationality AS guest_nationality,
      g.country_residence AS guest_country_residence,
      g.language AS guest_language,
      r.id_reservation,
      r.code AS reservation_code,
      p.id_property,
      p.code AS property_code,
      p.name AS property_name,
      p.city AS property_city,
      p.state AS property_state,
      p.country AS property_country,
      p.timezone AS property_timezone,
      p.currency AS property_currency,
      rm.id_room,
      rm.code AS room_code,
      rm.name AS room_name,
      rm.floor AS room_floor,
      rm.building AS room_building,
      rm.capacity_total AS room_capacity_total,
      rm.max_adults AS room_max_adults,
      rm.max_children AS room_max_children,
      rc.id_category,
      rc.code AS category_code,
      rc.name AS category_name_room,
      rc.max_occupancy AS category_max_occupancy,
      CONCAT_WS(' ', COALESCE(g.names, ''), COALESCE(g.last_name, '')) AS guest_full_name,
      li.created_at,
      COALESCE(li.service_date, DATE(li.created_at)) AS sort_date
    FROM line_item li
    JOIN folio f
      ON f.id_folio = li.id_folio
     AND f.deleted_at IS NULL
    JOIN reservation r
      ON r.id_reservation = f.id_reservation
     AND r.deleted_at IS NULL
    JOIN property p
      ON p.id_property = r.id_property
     AND p.deleted_at IS NULL
     AND p.id_company = v_company_id
    LEFT JOIN guest g
      ON g.id_guest = r.id_guest
    LEFT JOIN room rm
      ON rm.id_room = r.id_room
    LEFT JOIN roomcategory rc
      ON rc.id_category = r.id_category
    LEFT JOIN line_item_catalog lic
      ON lic.id_line_item_catalog = li.id_line_item_catalog
    LEFT JOIN sale_item_category cat
      ON cat.id_sale_item_category = lic.id_category
    LEFT JOIN sale_item_category parent
      ON parent.id_sale_item_category = cat.id_parent_sale_item_category
    WHERE li.deleted_at IS NULL
      AND li.is_active = 1
      AND (v_line_item_scope = 'all' OR li.item_type = v_line_item_scope)
      AND (p_from IS NULL OR COALESCE(li.service_date, DATE(li.created_at)) >= p_from)
      AND (p_to IS NULL OR COALESCE(li.service_date, DATE(li.created_at)) <= p_to);

  ELSE
    CREATE TEMPORARY TABLE tmp_report_base AS
    SELECT
      p.id_property,
      p.code AS property_code,
      p.name AS property_name,
      p.city AS property_city,
      p.state AS property_state,
      p.country AS property_country,
      p.timezone AS property_timezone,
      p.currency AS property_currency,
      COALESCE(r_sum.reservation_count, 0) AS reservation_count,
      COALESCE(r_sum.reservation_nights, 0) AS reservation_nights,
      COALESCE(r_sum.reservation_guests, 0) AS reservation_guests,
      COALESCE(r_sum.total_price_cents, 0) AS total_price_cents,
      COALESCE(r_sum.balance_due_cents, 0) AS balance_due_cents,
      COALESCE(li_sum.charges_cents, 0) AS charges_cents,
      COALESCE(li_sum.taxes_cents, 0) AS taxes_cents,
      COALESCE(li_sum.payments_cents, 0) AS payments_cents,
      COALESCE(li_sum.obligations_cents, 0) AS obligations_cents,
      COALESCE(li_sum.incomes_cents, 0) AS incomes_cents,
      (
        COALESCE(li_sum.charges_cents, 0)
        + COALESCE(li_sum.taxes_cents, 0)
        + COALESCE(li_sum.obligations_cents, 0)
        - COALESCE(li_sum.payments_cents, 0)
        - COALESCE(li_sum.incomes_cents, 0)
      ) AS net_cents,
      NOW() AS sort_date
    FROM property p
    LEFT JOIN (
      SELECT
        r.id_property,
        COUNT(*) AS reservation_count,
        SUM(COALESCE(r.nights, 0)) AS reservation_nights,
        SUM(COALESCE(r.adults, 0) + COALESCE(r.children, 0)) AS reservation_guests,
        SUM(COALESCE(r.total_price_cents, 0)) AS total_price_cents,
        SUM(COALESCE(r.balance_due_cents, 0)) AS balance_due_cents
      FROM reservation r
      WHERE r.deleted_at IS NULL
        AND (p_from IS NULL OR r.check_in_date >= p_from)
        AND (p_to IS NULL OR r.check_in_date <= p_to)
      GROUP BY r.id_property
    ) r_sum
      ON r_sum.id_property = p.id_property
    LEFT JOIN (
      SELECT
        r.id_property,
        SUM(CASE WHEN li.item_type = 'sale_item' THEN li.amount_cents ELSE 0 END) AS charges_cents,
        SUM(CASE WHEN li.item_type = 'tax_item' THEN li.amount_cents ELSE 0 END) AS taxes_cents,
        SUM(CASE WHEN li.item_type = 'payment' THEN li.amount_cents ELSE 0 END) AS payments_cents,
        SUM(CASE WHEN li.item_type = 'obligation' THEN li.amount_cents ELSE 0 END) AS obligations_cents,
        SUM(CASE WHEN li.item_type = 'income' THEN li.amount_cents ELSE 0 END) AS incomes_cents
      FROM reservation r
      JOIN folio f
        ON f.id_reservation = r.id_reservation
       AND f.deleted_at IS NULL
      JOIN line_item li
        ON li.id_folio = f.id_folio
       AND li.deleted_at IS NULL
       AND li.is_active = 1
      WHERE r.deleted_at IS NULL
        AND (p_from IS NULL OR COALESCE(li.service_date, DATE(li.created_at)) >= p_from)
        AND (p_to IS NULL OR COALESCE(li.service_date, DATE(li.created_at)) <= p_to)
      GROUP BY r.id_property
    ) li_sum
      ON li_sum.id_property = p.id_property
    WHERE p.deleted_at IS NULL
      AND p.id_company = v_company_id;
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_report_cols;
  CREATE TEMPORARY TABLE tmp_report_cols (
    id_report_config_column BIGINT,
    id_report_config BIGINT,
    column_key VARCHAR(160),
    column_source VARCHAR(32),
    source_field_key VARCHAR(120),
    id_line_item_catalog BIGINT,
    display_name VARCHAR(160),
    display_category VARCHAR(80),
    data_type VARCHAR(32),
    aggregation VARCHAR(32),
    format_hint VARCHAR(64),
    order_index INT,
    is_visible TINYINT,
    is_filterable TINYINT,
    filter_operator_default VARCHAR(32)
  ) ENGINE=MEMORY;

  INSERT INTO tmp_report_cols
  SELECT
    rcc.id_report_config_column,
    rcc.id_report_config,
    rcc.column_key,
    rcc.column_source,
    rcc.source_field_key,
    rcc.id_line_item_catalog,
    rcc.display_name,
    rcc.display_category,
    rcc.data_type,
    rcc.aggregation,
    rcc.format_hint,
    rcc.order_index,
    rcc.is_visible,
    rcc.is_filterable,
    rcc.filter_operator_default
  FROM report_config_column rcc
  WHERE rcc.id_report_config = p_id_report_config
    AND rcc.deleted_at IS NULL
    AND rcc.is_active = 1
  ORDER BY rcc.order_index, rcc.id_report_config_column;

  IF (SELECT COUNT(*) FROM tmp_report_cols) = 0 THEN
    INSERT INTO tmp_report_cols (
      id_report_config_column,
      id_report_config,
      column_key,
      column_source,
      source_field_key,
      id_line_item_catalog,
      display_name,
      display_category,
      data_type,
      aggregation,
      format_hint,
      order_index,
      is_visible,
      is_filterable,
      filter_operator_default
    )
    SELECT
      NULL,
      p_id_report_config,
      rfc.field_key,
      'field',
      rfc.field_key,
      NULL,
      rfc.field_label,
      rfc.field_group,
      rfc.data_type,
      'none',
      NULL,
      rfc.default_order,
      1,
      rfc.supports_filter,
      'eq'
    FROM report_field_catalog rfc
    WHERE rfc.report_type = v_report_type
      AND rfc.is_active = 1
      AND rfc.is_default = 1
    ORDER BY rfc.default_order, rfc.field_label;
  END IF;

  SET @prev_group_concat_max_len = @@SESSION.group_concat_max_len;
  SET SESSION group_concat_max_len = 1024 * 1024;

  SELECT GROUP_CONCAT(
    CASE
      WHEN c.column_source = 'field' THEN
        CONCAT('base.`', c.source_field_key, '` AS `', c.column_key, '`')
      WHEN c.column_source = 'line_item_catalog' THEN
        CASE
          WHEN v_report_type = 'line_item' THEN
            CASE LOWER(COALESCE(c.aggregation, 'sum_amount'))
              WHEN 'sum_quantity' THEN
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN base.quantity ELSE 0 END AS `', c.column_key, '`')
              WHEN 'avg_unit_price' THEN
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN base.unit_price_cents ELSE 0 END AS `', c.column_key, '`')
              WHEN 'sum_discount' THEN
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN base.discount_amount_cents ELSE 0 END AS `', c.column_key, '`')
              WHEN 'sum_paid' THEN
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN base.paid_cents ELSE 0 END AS `', c.column_key, '`')
              WHEN 'count_items' THEN
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN 1 ELSE 0 END AS `', c.column_key, '`')
              WHEN 'min_service_date' THEN
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN base.service_date ELSE NULL END AS `', c.column_key, '`')
              WHEN 'max_service_date' THEN
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN base.service_date ELSE NULL END AS `', c.column_key, '`')
              ELSE
                CONCAT('CASE WHEN base.id_line_item_catalog = ', c.id_line_item_catalog, ' THEN base.amount_cents ELSE 0 END AS `', c.column_key, '`')
            END
          WHEN v_report_type = 'reservation' THEN
            CASE LOWER(COALESCE(c.aggregation, 'sum_amount'))
              WHEN 'sum_quantity' THEN
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.quantity), 0) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'avg_unit_price' THEN
                CONCAT(
                  '(SELECT COALESCE(ROUND(AVG(li2.unit_price_cents)), 0) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'sum_discount' THEN
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.discount_amount_cents), 0) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'sum_paid' THEN
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.paid_cents), 0) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'count_items' THEN
                CONCAT(
                  '(SELECT COALESCE(COUNT(*), 0) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'min_service_date' THEN
                CONCAT(
                  '(SELECT MIN(COALESCE(li2.service_date, DATE(li2.created_at))) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'max_service_date' THEN
                CONCAT(
                  '(SELECT MAX(COALESCE(li2.service_date, DATE(li2.created_at))) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              ELSE
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.amount_cents), 0) ',
                  'FROM folio f2 ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE f2.deleted_at IS NULL ',
                  'AND f2.id_reservation = base.id_reservation ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
            END
          ELSE
            CASE LOWER(COALESCE(c.aggregation, 'sum_amount'))
              WHEN 'sum_quantity' THEN
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.quantity), 0) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'avg_unit_price' THEN
                CONCAT(
                  '(SELECT COALESCE(ROUND(AVG(li2.unit_price_cents)), 0) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'sum_discount' THEN
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.discount_amount_cents), 0) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'sum_paid' THEN
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.paid_cents), 0) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'count_items' THEN
                CONCAT(
                  '(SELECT COALESCE(COUNT(*), 0) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'min_service_date' THEN
                CONCAT(
                  '(SELECT MIN(COALESCE(li2.service_date, DATE(li2.created_at))) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              WHEN 'max_service_date' THEN
                CONCAT(
                  '(SELECT MAX(COALESCE(li2.service_date, DATE(li2.created_at))) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
              ELSE
                CONCAT(
                  '(SELECT COALESCE(SUM(li2.amount_cents), 0) ',
                  'FROM reservation r2 ',
                  'JOIN folio f2 ON f2.id_reservation = r2.id_reservation AND f2.deleted_at IS NULL ',
                  'JOIN line_item li2 ON li2.id_folio = f2.id_folio AND li2.deleted_at IS NULL AND li2.is_active = 1 ',
                  'WHERE r2.deleted_at IS NULL ',
                  'AND r2.id_property = base.id_property ',
                  'AND li2.id_line_item_catalog = ', c.id_line_item_catalog,
                  v_from_filter,
                  v_to_filter,
                  ') AS `', c.column_key, '`'
                )
            END
        END
      ELSE NULL
    END
    ORDER BY c.order_index, c.column_key SEPARATOR ', '
  ) INTO v_select_list
  FROM tmp_report_cols c
  WHERE c.is_visible = 1;

  IF v_select_list IS NULL OR v_select_list = '' THEN
    SET v_select_list = 'base.*';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_report_filter_map;
  CREATE TEMPORARY TABLE tmp_report_filter_map AS
  SELECT
    rcf.id_report_config_filter,
    rcf.filter_key,
    rcf.operator_key,
    rcf.value_text,
    rcf.value_from_text,
    rcf.value_to_text,
    rcf.value_list_text,
    rcf.order_index,
    COALESCE(rcc.column_source, 'field') AS column_source,
    COALESCE(rcc.source_field_key, rcf.filter_key) AS source_field_key,
    COALESCE(rcc.column_key, rcf.filter_key) AS column_key
  FROM report_config_filter rcf
  LEFT JOIN report_config_column rcc
    ON rcc.id_report_config = rcf.id_report_config
   AND rcc.column_key = rcf.filter_key
   AND rcc.deleted_at IS NULL
   AND rcc.is_active = 1
  WHERE rcf.id_report_config = p_id_report_config
    AND rcf.is_active = 1;

  SELECT GROUP_CONCAT(filter_sql ORDER BY order_index SEPARATOR ' AND ')
    INTO v_where_clause
  FROM (
    SELECT
      tfm.order_index,
      CASE LOWER(COALESCE(tfm.operator_key, 'eq'))
        WHEN 'eq' THEN CONCAT('base.`', tfm.source_field_key, '` = ', QUOTE(COALESCE(tfm.value_text, '')))
        WHEN 'neq' THEN CONCAT('base.`', tfm.source_field_key, '` <> ', QUOTE(COALESCE(tfm.value_text, '')))
        WHEN 'gt' THEN CONCAT('base.`', tfm.source_field_key, '` > ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'gte' THEN CONCAT('base.`', tfm.source_field_key, '` >= ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'lt' THEN CONCAT('base.`', tfm.source_field_key, '` < ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'lte' THEN CONCAT('base.`', tfm.source_field_key, '` <= ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'contains' THEN CONCAT('base.`', tfm.source_field_key, '` LIKE ', QUOTE(CONCAT('%', COALESCE(tfm.value_text, ''), '%')))
        WHEN 'between' THEN CONCAT('base.`', tfm.source_field_key, '` BETWEEN ', QUOTE(COALESCE(tfm.value_from_text, '')), ' AND ', QUOTE(COALESCE(tfm.value_to_text, '')))
        WHEN 'is_null' THEN CONCAT('base.`', tfm.source_field_key, '` IS NULL')
        WHEN 'is_not_null' THEN CONCAT('base.`', tfm.source_field_key, '` IS NOT NULL')
        ELSE NULL
      END AS filter_sql
    FROM tmp_report_filter_map tfm
    WHERE tfm.column_source = 'field'
  ) q
  WHERE q.filter_sql IS NOT NULL AND q.filter_sql <> '';

  SELECT GROUP_CONCAT(filter_sql ORDER BY order_index SEPARATOR ' AND ')
    INTO v_having_clause
  FROM (
    SELECT
      tfm.order_index,
      CASE LOWER(COALESCE(tfm.operator_key, 'eq'))
        WHEN 'eq' THEN CONCAT('`', tfm.column_key, '` = ', QUOTE(COALESCE(tfm.value_text, '')))
        WHEN 'neq' THEN CONCAT('`', tfm.column_key, '` <> ', QUOTE(COALESCE(tfm.value_text, '')))
        WHEN 'gt' THEN CONCAT('`', tfm.column_key, '` > ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'gte' THEN CONCAT('`', tfm.column_key, '` >= ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'lt' THEN CONCAT('`', tfm.column_key, '` < ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'lte' THEN CONCAT('`', tfm.column_key, '` <= ', QUOTE(COALESCE(tfm.value_text, '0')))
        WHEN 'contains' THEN CONCAT('`', tfm.column_key, '` LIKE ', QUOTE(CONCAT('%', COALESCE(tfm.value_text, ''), '%')))
        WHEN 'between' THEN CONCAT('`', tfm.column_key, '` BETWEEN ', QUOTE(COALESCE(tfm.value_from_text, '')), ' AND ', QUOTE(COALESCE(tfm.value_to_text, '')))
        WHEN 'is_null' THEN CONCAT('`', tfm.column_key, '` IS NULL')
        WHEN 'is_not_null' THEN CONCAT('`', tfm.column_key, '` IS NOT NULL')
        ELSE NULL
      END AS filter_sql
    FROM tmp_report_filter_map tfm
    WHERE tfm.column_source = 'line_item_catalog'
  ) q
  WHERE q.filter_sql IS NOT NULL AND q.filter_sql <> '';

  SELECT
    rc.id_report_config,
    rc.report_key,
    rc.report_name,
    rc.report_type,
    rc.line_item_type_scope,
    rc.description,
    rc.is_active,
    rc.created_at,
    rc.updated_at
  FROM report_config rc
  WHERE rc.id_report_config = p_id_report_config
  LIMIT 1;

  SELECT
    c.id_report_config_column,
    c.id_report_config,
    c.column_key,
    c.column_source,
    c.source_field_key,
    c.id_line_item_catalog,
    c.display_name,
    c.display_category,
    c.data_type,
    c.aggregation,
    c.format_hint,
    c.order_index,
    c.is_visible,
    c.is_filterable,
    c.filter_operator_default
  FROM tmp_report_cols c
  ORDER BY c.order_index, c.column_key;

  SELECT
    rcf.id_report_config_filter,
    rcf.id_report_config,
    rcf.filter_key,
    rcf.operator_key,
    rcf.value_text,
    rcf.value_from_text,
    rcf.value_to_text,
    rcf.value_list_text,
    rcf.logic_join,
    rcf.order_index,
    rcf.is_active,
    rcf.created_at,
    rcf.created_by,
    rcf.updated_at
  FROM report_config_filter rcf
  WHERE rcf.id_report_config = p_id_report_config
    AND rcf.is_active = 1
  ORDER BY rcf.order_index, rcf.id_report_config_filter;

  SET v_sql = CONCAT('SELECT ', v_select_list, ' FROM tmp_report_base base');

  IF v_where_clause IS NOT NULL AND v_where_clause <> '' THEN
    SET v_sql = CONCAT(v_sql, ' WHERE ', v_where_clause);
  END IF;

  IF v_having_clause IS NOT NULL AND v_having_clause <> '' THEN
    SET v_sql = CONCAT(v_sql, ' HAVING ', v_having_clause);
  END IF;

  SET v_sql = CONCAT(v_sql, ' ORDER BY base.sort_date DESC LIMIT ', v_limit);

  PREPARE stmt_report_run FROM v_sql;
  EXECUTE stmt_report_run;
  DEALLOCATE PREPARE stmt_report_run;

  SET SESSION group_concat_max_len = @prev_group_concat_max_len;
END $$

DELIMITER ;
