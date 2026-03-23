DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_report_data` $$
CREATE PROCEDURE `sp_sale_item_report_data` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_from DATE,
  IN p_to DATE,
  IN p_search TEXT,
  IN p_status VARCHAR(32),
  IN p_folio_status VARCHAR(32),
  IN p_parent_category_id BIGINT,
  IN p_category_id BIGINT,
  IN p_catalog_id BIGINT,
  IN p_min_total_cents INT,
  IN p_max_total_cents INT,
  IN p_has_tax TINYINT,
  IN p_show_inactive TINYINT,
  IN p_show_canceled_reservations TINYINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_search TEXT;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
    IF v_property_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property';
    END IF;
  ELSE
    SET v_property_id = NULL;
  END IF;

  SET v_search = NULLIF(TRIM(p_search), '');

  DROP TEMPORARY TABLE IF EXISTS tmp_sale_items;
  CREATE TEMPORARY TABLE tmp_sale_items AS
  SELECT
    si.id_line_item AS id_sale_item,
    si.id_folio,
    f.folio_name,
    f.status AS folio_status,
    r.id_reservation,
    r.code AS reservation_code,
    p.code AS property_code,
    p.name AS property_name,
    CONCAT_WS(' ', COALESCE(g.names, ''), COALESCE(g.last_name, '')) AS guest_name,
    g.email AS guest_email,
    g.phone AS guest_phone,
    COALESCE(si.service_date, DATE(si.created_at)) AS service_date,
    si.quantity,
    si.unit_price_cents,
    si.discount_amount_cents,
    si.amount_cents,
    COALESCE(tax.tax_amount_cents,0) AS tax_amount_cents,
    (si.amount_cents + COALESCE(tax.tax_amount_cents,0)) AS total_with_tax_cents,
    si.currency,
    si.status AS sale_status,
    si.description,
    si.created_at,
    sic.id_line_item_catalog AS id_sale_item_catalog,
    sic.item_name AS concept_name,
    cat.id_sale_item_category AS subcategory_id,
    cat.category_name AS subcategory_name,
    parent.id_sale_item_category AS category_id,
    parent.category_name AS category_name
  FROM line_item si
  JOIN folio f ON f.id_folio = si.id_folio
  LEFT JOIN reservation r ON r.id_reservation = f.id_reservation
  LEFT JOIN property p ON p.id_property = r.id_property
  JOIN line_item_catalog sic
    ON sic.id_line_item_catalog = si.id_line_item_catalog
   AND sic.catalog_type = 'sale_item'
  JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
  LEFT JOIN sale_item_category parent ON parent.id_sale_item_category = cat.id_parent_sale_item_category
  LEFT JOIN guest g ON g.id_guest = r.id_guest
  LEFT JOIN (
    SELECT
      s.id_line_item AS id_sale_item,
      COALESCE(SUM(t.amount_cents),0) AS tax_amount_cents
    FROM line_item s
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
    WHERE s.item_type = 'sale_item'
      AND s.deleted_at IS NULL
      AND s.is_active = 1
    GROUP BY s.id_line_item
  ) tax ON tax.id_sale_item = si.id_line_item
  WHERE cat.id_company = v_company_id
    AND f.deleted_at IS NULL
    AND (r.id_reservation IS NULL OR (r.deleted_at IS NULL AND r.is_active = 1))
    AND (
      p_show_canceled_reservations IS NOT NULL AND p_show_canceled_reservations <> 0
      OR r.id_reservation IS NULL
      OR COALESCE(LOWER(TRIM(r.status)), '') NOT IN ('cancelled','canceled','cancelado','cancelada')
    )
    AND si.item_type = 'sale_item'
    AND (v_property_id IS NULL OR r.id_property = v_property_id)
    AND (p_from IS NULL OR COALESCE(si.service_date, DATE(si.created_at)) >= p_from)
    AND (p_to IS NULL OR COALESCE(si.service_date, DATE(si.created_at)) <= p_to)
    AND (p_status IS NULL OR p_status = '' OR si.status = p_status)
    AND (p_folio_status IS NULL OR p_folio_status = '' OR f.status = p_folio_status)
    AND (p_category_id IS NULL OR p_category_id = 0 OR cat.id_sale_item_category = p_category_id)
    AND (
      p_parent_category_id IS NULL
      OR p_parent_category_id = 0
      OR cat.id_parent_sale_item_category = p_parent_category_id
      OR cat.id_sale_item_category = p_parent_category_id
    )
    AND (p_catalog_id IS NULL OR p_catalog_id = 0 OR sic.id_line_item_catalog = p_catalog_id)
    AND (
      p_min_total_cents IS NULL
      OR p_min_total_cents <= 0
      OR (si.amount_cents + COALESCE(tax.tax_amount_cents,0)) >= p_min_total_cents
    )
    AND (
      p_max_total_cents IS NULL
      OR p_max_total_cents <= 0
      OR (si.amount_cents + COALESCE(tax.tax_amount_cents,0)) <= p_max_total_cents
    )
    AND (p_has_tax IS NULL OR p_has_tax = 0 OR COALESCE(tax.tax_amount_cents,0) > 0)
    AND (
      v_search IS NULL
      OR r.code LIKE CONCAT('%', v_search, '%')
      OR f.folio_name LIKE CONCAT('%', v_search, '%')
      OR g.names LIKE CONCAT('%', v_search, '%')
      OR g.last_name LIKE CONCAT('%', v_search, '%')
      OR g.email LIKE CONCAT('%', v_search, '%')
      OR g.phone LIKE CONCAT('%', v_search, '%')
      OR sic.item_name LIKE CONCAT('%', v_search, '%')
      OR si.description LIKE CONCAT('%', v_search, '%')
    )
    AND (
      p_show_inactive IS NOT NULL AND p_show_inactive <> 0
      OR (si.deleted_at IS NULL AND si.is_active = 1)
    )
    AND (
      p_show_inactive IS NOT NULL AND p_show_inactive <> 0
      OR f.is_active = 1
    );

  SELECT
    id_sale_item,
    id_folio,
    folio_name,
    folio_status,
    id_reservation,
    reservation_code,
    property_code,
    property_name,
    guest_name,
    guest_email,
    guest_phone,
    service_date,
    quantity,
    unit_price_cents,
    discount_amount_cents,
    amount_cents,
    tax_amount_cents,
    total_with_tax_cents,
    currency,
    sale_status,
    description,
    created_at,
    id_sale_item_catalog,
    concept_name,
    subcategory_id,
    subcategory_name,
    category_id,
    category_name
  FROM tmp_sale_items
  ORDER BY service_date DESC, id_sale_item DESC;

  SELECT
    MIN(ti.id_line_item) AS id_tax_item,
    tsi.id_sale_item,
    tsi.currency,
    tr.id_line_item_catalog AS id_tax_rule,
    tr.item_name AS tax_name,
    COALESCE(lcp.percent_value, 0) AS rate_percent,
    COALESCE(SUM(ti.amount_cents),0) AS amount_cents,
    MAX(ti.created_at) AS created_at
  FROM tmp_sale_items tsi
  JOIN line_item s
    ON s.id_line_item = tsi.id_sale_item
   AND s.item_type = 'sale_item'
  JOIN line_item_catalog_parent lcp
    ON lcp.id_parent_sale_item_catalog = s.id_line_item_catalog
   AND lcp.deleted_at IS NULL
   AND lcp.is_active = 1
  JOIN line_item_catalog tr
    ON tr.id_line_item_catalog = lcp.id_sale_item_catalog
   AND tr.catalog_type = 'tax_rule'
  LEFT JOIN line_item ti
    ON ti.id_folio = s.id_folio
   AND ti.item_type = 'tax_item'
   AND ti.id_line_item_catalog = tr.id_line_item_catalog
   AND ti.deleted_at IS NULL
   AND ti.is_active = 1
   AND (ti.service_date <=> s.service_date)
  WHERE (
    p_show_inactive IS NOT NULL AND p_show_inactive <> 0
    OR (ti.deleted_at IS NULL AND ti.is_active = 1)
  )
  GROUP BY tsi.id_sale_item, tsi.currency, tr.id_line_item_catalog, tr.item_name, COALESCE(lcp.percent_value, 0)
  HAVING COALESCE(SUM(ti.amount_cents),0) <> 0 OR COUNT(ti.id_line_item) > 0
  ORDER BY tsi.id_sale_item, tr.item_name;
END $$

DELIMITER ;
