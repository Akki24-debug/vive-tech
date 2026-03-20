DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_accounting_report_data` $$
CREATE PROCEDURE `sp_accounting_report_data` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_from DATE,
  IN p_to DATE,
  IN p_lodging_ids TEXT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;

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

  DROP TEMPORARY TABLE IF EXISTS tmp_accounting_base;
  CREATE TEMPORARY TABLE tmp_accounting_base AS
  SELECT
    li.id_line_item AS id_payment,
    li.id_folio,
    li.id_line_item_catalog AS payment_catalog_id,
    li.amount_cents AS payment_amount_cents,
    li.method AS payment_method,
    COALESCE(NULLIF(payc.item_name, ''), NULLIF(li.method, ''), 'Sin concepto') AS payment_catalog_name,
    COALESCE(li.service_date, DATE(li.created_at)) AS payment_date,
    li.created_at AS payment_created_at,
    r.id_reservation,
    r.code AS reservation_code,
    r.source AS reservation_source,
    r.id_ota_account,
    oa.ota_name,
    oa.external_code AS ota_external_code,
    oa.platform AS ota_platform,
    r.check_in_date,
    r.check_out_date,
    r.nights,
    r.adults,
    r.children,
    r.currency AS reservation_currency,
    g.names AS guest_names,
    g.last_name AS guest_last_name,
    f.currency AS folio_currency,
    p.code AS property_code,
    p.name AS property_name
  FROM line_item li
  JOIN folio f ON f.id_folio = li.id_folio AND f.deleted_at IS NULL
  JOIN reservation r ON r.id_reservation = f.id_reservation AND r.deleted_at IS NULL
  JOIN property p ON p.id_property = r.id_property
  LEFT JOIN line_item_catalog payc
    ON payc.id_line_item_catalog = li.id_line_item_catalog
   AND payc.deleted_at IS NULL
  LEFT JOIN ota_account oa
    ON oa.id_ota_account = r.id_ota_account
   AND oa.deleted_at IS NULL
   AND oa.is_active = 1
  LEFT JOIN guest g ON g.id_guest = r.id_guest
  WHERE li.item_type = 'payment'
    AND li.deleted_at IS NULL
    AND li.is_active = 1
    AND p.id_company = v_company_id
    AND (v_property_id IS NULL OR p.id_property = v_property_id)
    AND (p_from IS NULL OR DATE(li.created_at) >= p_from)
    AND (p_to IS NULL OR DATE(li.created_at) <= p_to);

  SELECT *
  FROM tmp_accounting_base
  ORDER BY payment_created_at DESC, id_payment DESC;

  DROP TEMPORARY TABLE IF EXISTS tmp_accounting_folios;
  CREATE TEMPORARY TABLE tmp_accounting_folios (
    id_folio BIGINT PRIMARY KEY
  );
  INSERT IGNORE INTO tmp_accounting_folios (id_folio)
  SELECT DISTINCT id_folio
  FROM tmp_accounting_base
  WHERE id_folio IS NOT NULL;

  SELECT
    li.id_line_item,
    rel.id_parent_sale_item AS id_parent_sale_item,
    li.id_line_item_catalog AS id_sale_item_catalog,
    li.id_folio,
    li.service_date,
    li.amount_cents
  FROM line_item li
  JOIN tmp_accounting_folios tf ON tf.id_folio = li.id_folio
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
      ) AS id_parent_sale_item
    FROM line_item c
    JOIN tmp_accounting_folios tfc ON tfc.id_folio = c.id_folio
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
     AND p.item_type = 'sale_item'
     AND p.id_line_item_catalog = lcp.id_parent_sale_item_catalog
     AND p.deleted_at IS NULL
     AND p.is_active = 1
     AND p.id_line_item <> c.id_line_item
     AND (p.service_date <=> c.service_date)
    WHERE c.item_type = 'sale_item'
      AND c.deleted_at IS NULL
      AND c.is_active = 1
    GROUP BY c.id_line_item
  ) rel ON rel.child_sale_item_id = li.id_line_item
  WHERE li.item_type = 'sale_item'
    AND li.deleted_at IS NULL
    AND li.is_active = 1;

  DROP TEMPORARY TABLE IF EXISTS tmp_accounting_sale_items;
  CREATE TEMPORARY TABLE tmp_accounting_sale_items (
    id_sale_item BIGINT PRIMARY KEY
  );
  INSERT IGNORE INTO tmp_accounting_sale_items (id_sale_item)
  SELECT id_line_item
  FROM line_item li
  JOIN tmp_accounting_folios tf ON tf.id_folio = li.id_folio
  WHERE li.item_type = 'sale_item'
    AND li.deleted_at IS NULL
    AND li.is_active = 1;

  SELECT
    MIN(ti.id_line_item) AS id_line_item,
    s.id_line_item AS id_sale_item,
    tr.id_line_item_catalog AS id_tax_rule,
    COALESCE(SUM(ti.amount_cents),0) AS amount_cents
  FROM line_item s
  JOIN tmp_accounting_sale_items ts ON ts.id_sale_item = s.id_line_item
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
  WHERE s.item_type = 'sale_item'
    AND s.deleted_at IS NULL
    AND s.is_active = 1
  GROUP BY s.id_line_item, tr.id_line_item_catalog;
END $$

DELIMITER ;
