DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_portal_reservation_data` $$
CREATE PROCEDURE `sp_portal_reservation_data` (
  IN p_company_code   VARCHAR(100),
  IN p_property_code  VARCHAR(100),
  IN p_status         VARCHAR(32),
  IN p_from           DATE,
  IN p_to             DATE,
  IN p_reservation_id BIGINT,
  IN p_actor_user_id  BIGINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_status_filter VARCHAR(32);
  DECLARE v_from DATE;
  DECLARE v_to DATE;
  DECLARE v_rate_col VARCHAR(32) DEFAULT 'percent_value';
  DECLARE v_sql_tax_items LONGTEXT;
  DECLARE v_actor_user_id BIGINT DEFAULT 0;
  DECLARE v_actor_is_owner TINYINT DEFAULT 0;
  DECLARE v_scope_by_property TINYINT DEFAULT 0;
  DECLARE v_target_reservation_id BIGINT DEFAULT NULL;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_actor_user_id = COALESCE(p_actor_user_id, 0);
  IF v_actor_user_id > 0 THEN
    SELECT COALESCE(au.is_owner, 0)
      INTO v_actor_is_owner
    FROM app_user au
    WHERE au.id_user = v_actor_user_id
      AND au.id_company = v_company_id
      AND au.deleted_at IS NULL
      AND COALESCE(au.is_active, 1) = 1
    LIMIT 1;

    IF v_actor_is_owner IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid actor user for company';
    END IF;

    IF v_actor_is_owner <> 1 THEN
      SET v_scope_by_property = 1;
    END IF;
  END IF;

  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property
      INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_property_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
    END IF;
  ELSE
    SET v_property_id = NULL;
  END IF;

  SET v_status_filter = NULLIF(TRIM(p_status), '');
  SET v_from = COALESCE(p_from, DATE_SUB(CURDATE(), INTERVAL 30 DAY));
  SET v_to = COALESCE(p_to, DATE_ADD(CURDATE(), INTERVAL 180 DAY));
  IF v_to < v_from THEN
    SET v_to = DATE_ADD(v_from, INTERVAL 1 DAY);
  END IF;

  SET v_rate_col = 'percent_value';

  IF p_reservation_id IS NOT NULL AND p_reservation_id > 0 THEN
    SELECT r.id_reservation
      INTO v_target_reservation_id
    FROM reservation r
    JOIN property pr ON pr.id_property = r.id_property
    WHERE r.id_reservation = p_reservation_id
      AND pr.id_company = v_company_id
      AND r.deleted_at IS NULL
      AND (
        v_scope_by_property = 0 OR EXISTS (
          SELECT 1
          FROM user_property up
          WHERE up.id_user = v_actor_user_id
            AND up.id_property = r.id_property
            AND up.deleted_at IS NULL
            AND COALESCE(up.is_active, 1) = 1
        )
      )
    LIMIT 1;
  ELSE
    SET v_target_reservation_id = NULL;
  END IF;

  /* Result set 1: reservation list */
  SELECT
    r.id_reservation,
    r.code AS reservation_code,
    r.status,
    r.source,
    r.id_ota_account,
    r.id_reservation_source,
    COALESCE(rsc.source_name, '') AS reservation_source_name,
    oa.ota_name,
    oa.platform AS ota_platform,
    r.check_in_date,
    r.check_out_date,
    r.adults,
    r.children,
    COALESCE(fsum.total_cents, r.total_price_cents, 0) AS total_price_cents,
    COALESCE(fsum.balance_cents, r.balance_due_cents, 0) AS balance_due_cents,
    g.names AS guest_names,
    g.last_name AS guest_last_name,
    g.email AS guest_email,
    pr.code AS property_code,
    pr.name AS property_name,
    rm.code AS room_code
  FROM reservation r
  JOIN property pr ON pr.id_property = r.id_property
  LEFT JOIN room rm ON rm.id_room = r.id_room
  LEFT JOIN guest g ON g.id_guest = r.id_guest
  LEFT JOIN ota_account oa
    ON oa.id_ota_account = r.id_ota_account
   AND oa.deleted_at IS NULL
   AND oa.is_active = 1
  LEFT JOIN reservation_source_catalog rsc
    ON rsc.id_reservation_source = r.id_reservation_source
  LEFT JOIN (
    SELECT
      f.id_reservation,
      SUM(f.total_cents) AS total_cents,
      SUM(f.balance_cents) AS balance_cents
    FROM folio f
    WHERE f.deleted_at IS NULL
      AND COALESCE(f.is_active, 1) = 1
    GROUP BY f.id_reservation
  ) fsum ON fsum.id_reservation = r.id_reservation
  WHERE pr.id_company = v_company_id
    AND r.deleted_at IS NULL
    AND r.check_in_date >= v_from
    AND r.check_in_date <= v_to
    AND (v_property_id IS NULL OR r.id_property = v_property_id)
    AND (
      v_scope_by_property = 0 OR EXISTS (
        SELECT 1
        FROM user_property up
        WHERE up.id_user = v_actor_user_id
          AND up.id_property = r.id_property
          AND up.deleted_at IS NULL
          AND COALESCE(up.is_active, 1) = 1
      )
    )
    AND (v_status_filter IS NULL OR r.status = v_status_filter)
  ORDER BY r.check_in_date DESC, r.id_reservation DESC
  LIMIT 500;

  /* Result set 2: reservation detail */
  IF v_target_reservation_id IS NULL OR v_target_reservation_id = 0 THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_reservation,
      CAST(NULL AS CHAR) AS reservation_code,
      CAST(NULL AS CHAR) AS status,
      CAST(NULL AS CHAR) AS source,
      CAST(NULL AS SIGNED) AS id_ota_account,
      CAST(NULL AS SIGNED) AS id_reservation_source,
      CAST(NULL AS CHAR) AS reservation_source_name,
      CAST(NULL AS CHAR) AS ota_name,
      CAST(NULL AS CHAR) AS ota_platform,
      CAST(NULL AS DATE) AS check_in_date,
      CAST(NULL AS DATE) AS check_out_date,
      CAST(NULL AS SIGNED) AS adults,
      CAST(NULL AS SIGNED) AS children,
      CAST(NULL AS SIGNED) AS infants,
      CAST(NULL AS SIGNED) AS id_guest,
      CAST(NULL AS CHAR) AS currency,
      CAST(NULL AS CHAR) AS notes_guest,
      CAST(NULL AS CHAR) AS notes_internal,
      CAST(NULL AS SIGNED) AS total_price_cents,
      CAST(NULL AS SIGNED) AS balance_due_cents,
      CAST(NULL AS CHAR) AS property_code,
      CAST(NULL AS CHAR) AS property_name,
      CAST(NULL AS CHAR) AS room_code,
      CAST(NULL AS CHAR) AS category_name,
      CAST(NULL AS CHAR) AS rateplan_name,
      CAST(NULL AS CHAR) AS guest_names,
      CAST(NULL AS CHAR) AS guest_last_name,
      CAST(NULL AS CHAR) AS guest_email,
      CAST(NULL AS CHAR) AS guest_phone
    LIMIT 0;
  ELSE
    SELECT
      r.id_reservation,
      r.code AS reservation_code,
      r.status,
      r.source,
      r.id_ota_account,
      r.id_reservation_source,
      COALESCE(rsc.source_name, '') AS reservation_source_name,
      oa.ota_name,
      oa.platform AS ota_platform,
      r.channel_ref,
      r.check_in_date,
      r.check_out_date,
      r.adults,
      r.children,
      r.infants,
      r.id_guest,
      r.currency,
      r.notes_guest,
      r.notes_internal,
      COALESCE(fsum.total_cents, r.total_price_cents, 0) AS total_price_cents,
      COALESCE(fsum.balance_cents, r.balance_due_cents, 0) AS balance_due_cents,
      pr.code AS property_code,
      pr.name AS property_name,
      rm.code AS room_code,
      rc.name AS category_name,
      rp.name AS rateplan_name,
      g.names AS guest_names,
      g.last_name AS guest_last_name,
      g.email AS guest_email,
      g.phone AS guest_phone
    FROM reservation r
    JOIN property pr ON pr.id_property = r.id_property
    LEFT JOIN room rm ON rm.id_room = r.id_room
    LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
    LEFT JOIN rateplan rp ON rp.id_rateplan = r.id_rateplan
    LEFT JOIN guest g ON g.id_guest = r.id_guest
    LEFT JOIN ota_account oa
      ON oa.id_ota_account = r.id_ota_account
     AND oa.deleted_at IS NULL
     AND oa.is_active = 1
    LEFT JOIN reservation_source_catalog rsc
      ON rsc.id_reservation_source = r.id_reservation_source
    LEFT JOIN (
      SELECT
        f.id_reservation,
        SUM(f.total_cents) AS total_cents,
        SUM(f.balance_cents) AS balance_cents
      FROM folio f
      WHERE f.deleted_at IS NULL
        AND COALESCE(f.is_active, 1) = 1
      GROUP BY f.id_reservation
    ) fsum ON fsum.id_reservation = r.id_reservation
    WHERE r.id_reservation = v_target_reservation_id
      AND pr.id_company = v_company_id
      AND r.deleted_at IS NULL
    LIMIT 1;
  END IF;

  /* Result set 3: folios + totales */
  SELECT
    f.id_folio,
    f.folio_name,
    f.status,
    f.currency,
    f.total_cents,
    f.balance_cents,
    f.due_date,
    f.bill_to_type,
    f.bill_to_id,
    f.notes,
    f.created_at,
    COALESCE(pay.payments_cents,0) AS payments_cents,
    COALESCE(ref.refunds_cents,0) AS refunds_cents
  FROM folio f
  LEFT JOIN (
    SELECT li.id_folio, SUM(li.amount_cents) AS payments_cents
    FROM line_item li
    LEFT JOIN line_item_catalog lic
      ON lic.id_line_item_catalog = li.id_line_item_catalog
    WHERE li.item_type = 'payment'
      AND li.deleted_at IS NULL
      AND li.is_active = 1
      AND (li.status IS NULL OR li.status NOT IN ('void','canceled'))
      AND (
        li.id_line_item_catalog IS NULL
        OR COALESCE(lic.show_in_folio, 1) = 1
      )
    GROUP BY li.id_folio
  ) pay ON pay.id_folio = f.id_folio
  LEFT JOIN (
    SELECT p.id_folio, SUM(r.amount_cents) AS refunds_cents
    FROM refund r
    JOIN line_item p
      ON p.id_line_item = r.id_payment
     AND p.item_type = 'payment'
    LEFT JOIN line_item_catalog lic
      ON lic.id_line_item_catalog = p.id_line_item_catalog
    WHERE r.deleted_at IS NULL AND r.is_active = 1
      AND (
        p.id_line_item_catalog IS NULL
        OR COALESCE(lic.show_in_folio, 1) = 1
      )
    GROUP BY p.id_folio
  ) ref ON ref.id_folio = f.id_folio
  WHERE f.id_reservation = v_target_reservation_id
    AND f.deleted_at IS NULL
  ORDER BY f.created_at;

  /* Result set 4: sale items por folio/reserva (compat layer with new line_item schema) */
  SELECT
    si.id_line_item AS id_sale_item,
    rel.id_parent_sale_item AS id_parent_sale_item,
    si.id_line_item_catalog AS id_sale_item_catalog,
    si.item_type,
    rel.parent_sale_item_catalog_id AS parent_sale_item_catalog_id,
    COALESCE(rel.add_to_father_total, 0) AS add_to_father_total,
    rel.show_in_folio_relation AS show_in_folio_relation,
    COALESCE(
      CASE
        WHEN rel.parent_sale_item_catalog_id IS NOT NULL THEN rel.show_in_folio_relation
        ELSE NULL
      END,
      sic.show_in_folio,
      1
    ) AS show_in_folio_effective,
    si.id_folio,
    f.folio_name,
    cat.category_name AS subcategory_name,
    COALESCE(si.item_name, sic.item_name) AS item_name,
    si.description,
    si.service_date,
    si.quantity,
    si.unit_price_cents,
    si.discount_amount_cents,
    COALESCE(tax.tax_amount_cents,0) AS tax_amount_cents,
    si.amount_cents,
    si.currency,
    si.status,
    si.created_at
  FROM line_item si
  JOIN folio f ON f.id_folio = si.id_folio
  LEFT JOIN line_item_catalog sic
    ON sic.id_line_item_catalog = si.id_line_item_catalog
  LEFT JOIN (
    SELECT
      c.id_line_item AS child_sale_item_id,
      COALESCE(
        MIN(
          CASE
            WHEN COALESCE(c.description,'') = CONCAT(COALESCE(cc.item_name,''), ' / ', COALESCE(pc.item_name,''))
            THEN p.id_line_item
            ELSE NULL
          END
        ),
        MIN(p.id_line_item)
      ) AS id_parent_sale_item,
      COALESCE(
        MIN(
          CASE
            WHEN COALESCE(c.description,'') = CONCAT(COALESCE(cc.item_name,''), ' / ', COALESCE(pc.item_name,''))
            THEN p.id_line_item_catalog
            ELSE NULL
          END
        ),
        MIN(lcp.id_parent_sale_item_catalog)
      ) AS parent_sale_item_catalog_id,
      COALESCE(
        MIN(
          CASE
            WHEN COALESCE(c.description,'') = CONCAT(COALESCE(cc.item_name,''), ' / ', COALESCE(pc.item_name,''))
            THEN lcp.add_to_father_total
            ELSE NULL
          END
        ),
        MIN(lcp.add_to_father_total),
        0
      ) AS add_to_father_total,
      COALESCE(
        MIN(
          CASE
            WHEN COALESCE(c.description,'') = CONCAT(COALESCE(cc.item_name,''), ' / ', COALESCE(pc.item_name,''))
            THEN lcp.show_in_folio_relation
            ELSE NULL
          END
        ),
        MIN(lcp.show_in_folio_relation),
        NULL
      ) AS show_in_folio_relation
    FROM line_item c
    JOIN folio cf ON cf.id_folio = c.id_folio
    LEFT JOIN line_item_catalog cc
      ON cc.id_line_item_catalog = c.id_line_item_catalog
    JOIN line_item_catalog_parent lcp
      ON lcp.id_sale_item_catalog = c.id_line_item_catalog
     AND lcp.deleted_at IS NULL
     AND lcp.is_active = 1
    LEFT JOIN line_item_catalog pc
      ON pc.id_line_item_catalog = lcp.id_parent_sale_item_catalog
    JOIN line_item p
      ON p.id_folio = c.id_folio
     AND p.item_type IN ('sale_item','tax_item','payment','obligation','income')
     AND p.id_line_item_catalog = lcp.id_parent_sale_item_catalog
     AND p.deleted_at IS NULL
     AND p.is_active = 1
     AND p.id_line_item <> c.id_line_item
     AND (p.service_date <=> c.service_date)
    WHERE cf.id_reservation = v_target_reservation_id
      AND c.item_type IN ('sale_item','tax_item','obligation','income')
      AND c.deleted_at IS NULL
     AND c.is_active = 1
    GROUP BY c.id_line_item
  ) rel ON rel.child_sale_item_id = si.id_line_item
  LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
  LEFT JOIN (
    SELECT
      s.id_line_item AS id_sale_item,
      COALESCE(SUM(t.amount_cents),0) AS tax_amount_cents
    FROM line_item s
    JOIN folio sf ON sf.id_folio = s.id_folio
    LEFT JOIN line_item_catalog_parent lcp
      ON lcp.id_parent_sale_item_catalog = s.id_line_item_catalog
     AND lcp.deleted_at IS NULL
     AND lcp.is_active = 1
    LEFT JOIN line_item t
      ON t.id_folio = s.id_folio
     AND t.item_type = 'tax_item'
     AND t.id_line_item_catalog = lcp.id_sale_item_catalog
     AND t.deleted_at IS NULL
     AND t.is_active = 1
     AND (t.service_date <=> s.service_date)
    WHERE sf.id_reservation = v_target_reservation_id
      AND s.item_type IN ('sale_item','tax_item','obligation','income')
      AND s.deleted_at IS NULL
      AND s.is_active = 1
    GROUP BY s.id_line_item
  ) tax ON tax.id_sale_item = si.id_line_item
  WHERE f.id_reservation = v_target_reservation_id
    AND si.item_type IN ('sale_item','tax_item','obligation','income')
    AND si.deleted_at IS NULL
    AND si.is_active = 1
    AND (
      sic.id_line_item_catalog IS NULL
      OR COALESCE(
        CASE
          WHEN rel.parent_sale_item_catalog_id IS NOT NULL THEN rel.show_in_folio_relation
          ELSE NULL
        END,
        sic.show_in_folio,
        1
      ) = 1
    )
    AND (
      NOT EXISTS (
        SELECT 1
        FROM line_item_catalog_parent lcp_rel
        WHERE lcp_rel.id_sale_item_catalog = si.id_line_item_catalog
          AND lcp_rel.deleted_at IS NULL
          AND lcp_rel.is_active = 1
      )
      OR EXISTS (
        SELECT 1
        FROM line_item_catalog_parent lcp_rel
        JOIN line_item p
          ON p.id_folio = si.id_folio
         AND p.id_line_item_catalog = lcp_rel.id_parent_sale_item_catalog
         AND p.deleted_at IS NULL
         AND p.is_active = 1
         AND (p.status IS NULL OR p.status NOT IN ('void','canceled'))
         AND (p.service_date <=> si.service_date)
        LEFT JOIN line_item_catalog plic
          ON plic.id_line_item_catalog = p.id_line_item_catalog
        WHERE lcp_rel.id_sale_item_catalog = si.id_line_item_catalog
          AND lcp_rel.deleted_at IS NULL
          AND lcp_rel.is_active = 1
          AND (
            COALESCE(si.description, '') = CONCAT(COALESCE(sic.item_name, ''), ' / ', COALESCE(plic.item_name, ''))
            OR (
              (COALESCE(si.description, '') = '' OR INSTR(COALESCE(si.description, ''), ' / ') = 0)
              AND (
                SELECT COUNT(*)
                FROM line_item_catalog_parent lcp_cnt
                WHERE lcp_cnt.id_sale_item_catalog = si.id_line_item_catalog
                  AND lcp_cnt.deleted_at IS NULL
                  AND lcp_cnt.is_active = 1
              ) = 1
            )
          )
          AND (
            COALESCE(lcp_rel.add_to_father_total, 1) = 0
            OR plic.id_line_item_catalog IS NULL
            OR (
              plic.deleted_at IS NULL
              AND plic.is_active = 1
              AND COALESCE(plic.show_in_folio, 1) = 1
            )
          )
      )
      OR EXISTS (
        SELECT 1
        FROM line_item_catalog_parent lcp_rel
        WHERE lcp_rel.id_sale_item_catalog = si.id_line_item_catalog
          AND lcp_rel.deleted_at IS NULL
          AND lcp_rel.is_active = 1
          AND COALESCE(lcp_rel.add_to_father_total, 1) = 0
      )
    )
  ORDER BY si.service_date, si.id_line_item;

  /* Result set 5 (legacy): no tax-specific dataset. Derivados now come from result set 4. */
  SELECT
    CAST(NULL AS SIGNED) AS id_tax_item,
    CAST(NULL AS SIGNED) AS id_sale_item,
    CAST(NULL AS SIGNED) AS id_tax_rule,
    CAST(NULL AS CHAR) AS tax_name,
    CAST(NULL AS DECIMAL(9,4)) AS rate_percent,
    CAST(NULL AS SIGNED) AS amount_cents,
    CAST(NULL AS DATETIME) AS created_at
  LIMIT 0;

  /* Result set 6: pagos */
  SELECT
    p.id_line_item AS id_payment,
    p.id_folio,
    f.folio_name,
    p.id_line_item_catalog AS id_payment_catalog,
    p.method,
    p.amount_cents,
    p.currency,
    p.reference,
    p.service_date,
    p.status,
    p.refunded_total_cents,
    p.created_at
  FROM line_item p
  JOIN folio f ON f.id_folio = p.id_folio
  LEFT JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = p.id_line_item_catalog
  WHERE f.id_reservation = v_target_reservation_id
    AND p.item_type = 'payment'
    AND p.deleted_at IS NULL
    AND (
      p.id_line_item_catalog IS NULL
      OR COALESCE(lic.show_in_folio, 1) = 1
    )
  ORDER BY p.created_at DESC;

  /* Result set 7: reembolsos */
  SELECT
    r.id_refund,
    r.id_payment,
    p.id_folio,
    f.folio_name,
    r.amount_cents,
    r.currency,
    r.reference,
    r.reason,
    r.refunded_at
  FROM refund r
  JOIN line_item p
    ON p.id_line_item = r.id_payment
   AND p.item_type = 'payment'
  LEFT JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = p.id_line_item_catalog
  JOIN folio f ON f.id_folio = p.id_folio
  WHERE f.id_reservation = v_target_reservation_id
    AND r.deleted_at IS NULL
    AND (
      p.id_line_item_catalog IS NULL
      OR COALESCE(lic.show_in_folio, 1) = 1
    )
  ORDER BY r.refunded_at DESC, r.id_refund DESC;

  /* Result set 8: activity bookings for the reservation */
  SELECT
    ab.id_booking,
    ab.scheduled_at,
    ab.status,
    ab.num_adults,
    ab.num_children,
    ab.price_cents,
    ab.currency,
    act.code AS activity_code,
    act.name AS activity_name
  FROM activity_booking ab
  JOIN activity act ON act.id_activity = ab.id_activity
  JOIN (
    SELECT abr.id_booking, abr.id_reservation
    FROM activity_booking_reservation abr
    WHERE abr.deleted_at IS NULL
      AND COALESCE(abr.is_active, 1) = 1

    UNION ALL

    SELECT b2.id_booking, b2.id_reservation
    FROM activity_booking b2
    WHERE b2.id_reservation IS NOT NULL
      AND NOT EXISTS (
        SELECT 1
        FROM activity_booking_reservation abr2
        WHERE abr2.id_booking = b2.id_booking
          AND abr2.deleted_at IS NULL
          AND COALESCE(abr2.is_active, 1) = 1
      )
  ) abr_link ON abr_link.id_booking = ab.id_booking
  WHERE abr_link.id_reservation = v_target_reservation_id
    AND ab.deleted_at IS NULL
  ORDER BY ab.scheduled_at DESC
  LIMIT 200;

  /* Result set 9: conceptos de interes por reserva */
  SELECT
    ri.id_reservation,
    ri.id_sale_item_catalog,
    sic.item_name,
    cat.category_name,
    ri.created_at
  FROM reservation_interest ri
  JOIN line_item_catalog sic
    ON sic.id_line_item_catalog = ri.id_sale_item_catalog
   AND sic.catalog_type = 'sale_item'
  JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
  WHERE ri.id_reservation = v_target_reservation_id
    AND ri.deleted_at IS NULL
    AND ri.is_active = 1
    AND cat.id_company = v_company_id
    AND cat.deleted_at IS NULL
  ORDER BY cat.category_name, sic.item_name;
END $$

DELIMITER ;

