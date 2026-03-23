DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_obligation_data` $$
CREATE PROCEDURE `sp_obligation_data` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_date_from DATE,
  IN p_date_to DATE,
  IN p_search VARCHAR(255),
  IN p_payment_status VARCHAR(16),
  IN p_show_inactive TINYINT,
  IN p_id_reservation BIGINT,
  IN p_id_folio BIGINT,
  IN p_limit_rows INT
)
proc:BEGIN
  DECLARE v_company_id BIGINT DEFAULT 0;
  DECLARE v_payment_status VARCHAR(16) DEFAULT '';
  DECLARE v_limit_rows INT DEFAULT 500;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'company code is required';
  END IF;

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unknown company';
  END IF;

  SET v_payment_status = LOWER(TRIM(COALESCE(p_payment_status, '')));
  IF v_payment_status NOT IN ('', 'pending', 'partial', 'paid') THEN
    SET v_payment_status = '';
  END IF;

  SET v_limit_rows = COALESCE(p_limit_rows, 500);
  IF v_limit_rows <= 0 THEN
    SET v_limit_rows = 500;
  END IF;
  IF v_limit_rows > 5000 THEN
    SET v_limit_rows = 5000;
  END IF;

  WITH RECURSIVE obligation_base AS (
    SELECT
      li.id_line_item,
      li.id_folio,
      r.id_reservation,
      r.id_ota_account AS reservation_ota_account_id,
      li.id_line_item_catalog,
      prop.id_company AS company_id,
      prop.id_property AS property_id,
      prop.code AS property_code,
      prop.name AS property_name,
      r.code AS reservation_code,
      r.check_in_date,
      f.folio_name,
      f.status AS folio_status,
      TRIM(CONCAT_WS(' ', COALESCE(g.names, ''), COALESCE(g.last_name, ''))) AS guest_name,
      COALESCE(rm.code, '') AS room_code,
      COALESCE(li.item_name, lic.item_name, '') AS catalog_item_name,
      COALESCE(parent_cat.category_name, '') AS category_name,
      COALESCE(sub_cat.category_name, '') AS subcategory_name,
      COALESCE(li.description, '') AS description,
      COALESCE(li.reference, '') AS reference,
      COALESCE(li.method, '') AS method,
      COALESCE(li.service_date, DATE(li.created_at)) AS service_date,
      li.created_at,
      DATE(li.created_at) AS created_date,
      COALESCE(li.amount_cents, 0) AS amount_cents,
      COALESCE(li.paid_cents, 0) AS paid_cents,
      GREATEST(COALESCE(li.amount_cents, 0) - COALESCE(li.paid_cents, 0), 0) AS remaining_cents,
      COALESCE(li.currency, 'MXN') AS currency,
      COALESCE(li.status, '') AS status,
      CASE
        WHEN COALESCE(li.amount_cents, 0) <= 0 THEN 'paid'
        WHEN COALESCE(li.paid_cents, 0) <= 0 THEN 'pending'
        WHEN COALESCE(li.paid_cents, 0) >= COALESCE(li.amount_cents, 0) THEN 'paid'
        ELSE 'partial'
      END AS payment_status
    FROM line_item li
    JOIN folio f
      ON f.id_folio = li.id_folio
    JOIN reservation r
      ON r.id_reservation = f.id_reservation
    JOIN property prop
      ON prop.id_property = r.id_property
    LEFT JOIN guest g
      ON g.id_guest = r.id_guest
    LEFT JOIN room rm
      ON rm.id_room = r.id_room
    LEFT JOIN line_item_catalog lic
      ON lic.id_line_item_catalog = li.id_line_item_catalog
    LEFT JOIN sale_item_category sub_cat
      ON sub_cat.id_sale_item_category = lic.id_category
    LEFT JOIN sale_item_category parent_cat
      ON parent_cat.id_sale_item_category = sub_cat.id_parent_sale_item_category
    WHERE li.item_type = 'obligation'
      AND prop.id_company = v_company_id
      AND (
        p_property_code IS NULL
        OR TRIM(p_property_code) = ''
        OR prop.code = p_property_code
      )
      AND (
        p_id_reservation IS NULL
        OR p_id_reservation <= 0
        OR r.id_reservation = p_id_reservation
      )
      AND (
        p_id_folio IS NULL
        OR p_id_folio <= 0
        OR li.id_folio = p_id_folio
      )
      AND (
        p_show_inactive = 1
        OR (
          li.deleted_at IS NULL
          AND li.is_active = 1
          AND (li.status IS NULL OR li.status NOT IN ('void', 'canceled'))
          AND f.deleted_at IS NULL
          AND f.is_active = 1
          AND r.deleted_at IS NULL
        )
      )
      AND (
        p_date_from IS NULL
        OR COALESCE(li.service_date, DATE(li.created_at)) >= p_date_from
      )
      AND (
        p_date_to IS NULL
        OR COALESCE(li.service_date, DATE(li.created_at)) <= p_date_to
      )
      AND (
        p_search IS NULL
        OR TRIM(p_search) = ''
        OR r.code LIKE CONCAT('%', TRIM(p_search), '%')
        OR f.folio_name LIKE CONCAT('%', TRIM(p_search), '%')
        OR COALESCE(g.names, '') LIKE CONCAT('%', TRIM(p_search), '%')
        OR COALESCE(g.last_name, '') LIKE CONCAT('%', TRIM(p_search), '%')
        OR COALESCE(g.email, '') LIKE CONCAT('%', TRIM(p_search), '%')
        OR COALESCE(rm.code, '') LIKE CONCAT('%', TRIM(p_search), '%')
        OR COALESCE(lic.item_name, '') LIKE CONCAT('%', TRIM(p_search), '%')
        OR COALESCE(li.description, '') LIKE CONCAT('%', TRIM(p_search), '%')
        OR COALESCE(li.reference, '') LIKE CONCAT('%', TRIM(p_search), '%')
      )
  ),
  line_item_scope AS (
    SELECT DISTINCT
      li.id_line_item,
      li.id_folio,
      li.id_line_item_catalog,
      li.item_type,
      li.service_date,
      COALESCE(li.description, '') AS description
    FROM line_item li
    JOIN obligation_base ob
      ON ob.id_folio = li.id_folio
    WHERE (
      p_show_inactive = 1
      OR (
        li.deleted_at IS NULL
        AND li.is_active = 1
        AND (li.status IS NULL OR li.status NOT IN ('void', 'canceled'))
      )
    )
  ),
  line_item_hierarchy_rel AS (
    SELECT
      h.id_line_item_child AS child_line_item_id,
      h.id_line_item_parent AS parent_line_item_id,
      p.id_line_item_catalog AS parent_catalog_id,
      p.item_type AS parent_item_type
    FROM line_item_hierarchy h
    JOIN line_item_scope p
      ON p.id_line_item = h.id_line_item_parent
    WHERE h.deleted_at IS NULL
      AND h.is_active = 1
  ),
  line_item_ancestor AS (
    SELECT
      ob.id_line_item AS root_line_item_id,
      hr.parent_line_item_id AS ancestor_line_item_id,
      hr.parent_catalog_id AS ancestor_catalog_id,
      hr.parent_item_type AS ancestor_item_type,
      1 AS depth,
      CAST(CONCAT(ob.id_line_item, ',', hr.parent_line_item_id) AS CHAR(4000)) AS path
    FROM obligation_base ob
    JOIN line_item_hierarchy_rel hr
      ON hr.child_line_item_id = ob.id_line_item
    UNION ALL
    SELECT
      la.root_line_item_id,
      hr.parent_line_item_id,
      hr.parent_catalog_id,
      hr.parent_item_type,
      la.depth + 1 AS depth,
      CONCAT(la.path, ',', hr.parent_line_item_id) AS path
    FROM line_item_ancestor la
    JOIN line_item_hierarchy_rel hr
      ON hr.child_line_item_id = la.ancestor_line_item_id
    WHERE la.depth < 15
      AND FIND_IN_SET(CAST(hr.parent_line_item_id AS CHAR), la.path) = 0
  ),
  ota_candidates AS (
    SELECT
      la.root_line_item_id AS id_line_item,
      oa.id_ota_account,
      oa.ota_name,
      oa.platform AS ota_platform,
      oa.id_service_fee_payment_catalog,
      MIN(la.depth) AS min_depth,
      MIN(
        CASE
          WHEN ob.reservation_ota_account_id IS NOT NULL
               AND oa.id_ota_account = ob.reservation_ota_account_id
          THEN 0
          ELSE 1
        END
      ) AS match_priority
    FROM line_item_ancestor la
    JOIN obligation_base ob
      ON ob.id_line_item = la.root_line_item_id
    JOIN ota_account_lodging_catalog oalc
      ON oalc.id_line_item_catalog = la.ancestor_catalog_id
     AND oalc.deleted_at IS NULL
     AND oalc.is_active = 1
    JOIN ota_account oa
      ON oa.id_ota_account = oalc.id_ota_account
     AND oa.deleted_at IS NULL
     AND oa.is_active = 1
     AND oa.id_company = ob.company_id
     AND oa.id_property = ob.property_id
    GROUP BY
      la.root_line_item_id,
      oa.id_ota_account,
      oa.ota_name,
      oa.platform,
      oa.id_service_fee_payment_catalog
    UNION ALL
    SELECT
      ob.id_line_item,
      oa.id_ota_account,
      oa.ota_name,
      oa.platform AS ota_platform,
      oa.id_service_fee_payment_catalog,
      999 AS min_depth,
      0 AS match_priority
    FROM obligation_base ob
    JOIN ota_account oa
      ON oa.id_ota_account = ob.reservation_ota_account_id
     AND oa.deleted_at IS NULL
     AND oa.is_active = 1
     AND oa.id_company = ob.company_id
     AND oa.id_property = ob.property_id
  ),
  ota_best_priority AS (
    SELECT
      oc.id_line_item,
      MIN(oc.match_priority) AS best_priority
    FROM ota_candidates oc
    GROUP BY oc.id_line_item
  ),
  ota_best_depth AS (
    SELECT
      oc.id_line_item,
      MIN(oc.min_depth) AS best_depth
    FROM ota_candidates oc
    JOIN ota_best_priority bp
      ON bp.id_line_item = oc.id_line_item
     AND bp.best_priority = oc.match_priority
    GROUP BY oc.id_line_item
  ),
  ota_choice AS (
    SELECT
      oc.id_line_item,
      MIN(oc.id_ota_account) AS id_ota_account
    FROM ota_candidates oc
    JOIN ota_best_priority bp
      ON bp.id_line_item = oc.id_line_item
     AND bp.best_priority = oc.match_priority
    JOIN ota_best_depth bd
      ON bd.id_line_item = oc.id_line_item
     AND bd.best_depth = oc.min_depth
    GROUP BY oc.id_line_item
  ),
  ota_fee_catalog AS (
    SELECT
      ob.id_line_item,
      MIN(oa.id_ota_account) AS fee_ota_account_id
    FROM obligation_base ob
    JOIN ota_account oa
      ON oa.id_company = ob.company_id
     AND oa.id_property = ob.property_id
     AND oa.deleted_at IS NULL
     AND oa.is_active = 1
     AND oa.id_service_fee_payment_catalog = ob.id_line_item_catalog
    GROUP BY ob.id_line_item
  ),
  tax_candidates AS (
    SELECT
      la.root_line_item_id AS id_line_item,
      la.depth,
      NULLIF(TRIM(tax_lic.item_name), '') AS tax_parent_catalog_name
    FROM line_item_ancestor la
    JOIN line_item_catalog tax_lic
      ON tax_lic.id_line_item_catalog = la.ancestor_catalog_id
     AND tax_lic.deleted_at IS NULL
     AND tax_lic.is_active = 1
    WHERE LOWER(TRIM(COALESCE(la.ancestor_item_type, ''))) IN ('tax', 'tax_item')
  ),
  tax_best_depth AS (
    SELECT
      tc.id_line_item,
      MIN(tc.depth) AS best_depth
    FROM tax_candidates tc
    GROUP BY tc.id_line_item
  ),
  tax_parent AS (
    SELECT
      tc.id_line_item,
      1 AS has_tax_parent,
      COALESCE(MIN(tc.tax_parent_catalog_name), '') AS tax_parent_catalog_name
    FROM tax_candidates tc
    JOIN tax_best_depth td
      ON td.id_line_item = tc.id_line_item
     AND td.best_depth = tc.depth
    GROUP BY tc.id_line_item
  ),
  parent_name_candidates AS (
    SELECT
      la.root_line_item_id AS id_line_item,
      la.depth,
      NULLIF(TRIM(parent_lic.item_name), '') AS parent_catalog_name
    FROM line_item_ancestor la
    JOIN line_item_catalog parent_lic
      ON parent_lic.id_line_item_catalog = la.ancestor_catalog_id
     AND parent_lic.deleted_at IS NULL
     AND parent_lic.is_active = 1
    WHERE TRIM(COALESCE(parent_lic.item_name, '')) <> ''
  ),
  parent_name_depth AS (
    SELECT
      pnc.id_line_item,
      MIN(pnc.depth) AS best_depth
    FROM parent_name_candidates pnc
    GROUP BY pnc.id_line_item
  ),
  parent_name AS (
    SELECT
      pnc.id_line_item,
      COALESCE(MIN(pnc.parent_catalog_name), '') AS parent_catalog_name
    FROM parent_name_candidates pnc
    JOIN parent_name_depth pnd
      ON pnd.id_line_item = pnc.id_line_item
     AND pnd.best_depth = pnc.depth
    GROUP BY pnc.id_line_item
  )
  SELECT
    ob.id_line_item,
    ob.id_folio,
    ob.id_reservation,
    ob.id_line_item_catalog,
    ob.property_code,
    ob.property_name,
    ob.reservation_code,
    ob.check_in_date,
    ob.folio_name,
    ob.folio_status,
    ob.guest_name,
    ob.room_code,
    ob.catalog_item_name,
    ob.category_name,
    ob.subcategory_name,
    CASE
      WHEN COALESCE(tp.has_tax_parent, 0) = 1
           OR LOWER(TRIM(COALESCE(tp.tax_parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(pn.parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(ob.catalog_item_name, ''))) LIKE 'pago de impuestos%'
      THEN CONCAT(
        COALESCE(NULLIF(TRIM(ob.catalog_item_name), ''), CONCAT('Concepto #', COALESCE(ob.id_line_item_catalog, 0))),
        ' - ',
        COALESCE(NULLIF(TRIM(pn.parent_catalog_name), ''), NULLIF(TRIM(tp.tax_parent_catalog_name), ''), 'Impuesto')
      )
      WHEN (
        (oa.id_ota_account IS NOT NULL
         AND oa.id_service_fee_payment_catalog IS NOT NULL
         AND oa.id_service_fee_payment_catalog = ob.id_line_item_catalog)
        OR ofc.fee_ota_account_id IS NOT NULL
      )
      THEN CONCAT(
        COALESCE(NULLIF(TRIM(ob.catalog_item_name), ''), CONCAT('Concepto #', COALESCE(ob.id_line_item_catalog, 0))),
        ' - ',
        COALESCE(
          NULLIF(TRIM(res_oa.ota_name), ''),
          NULLIF(TRIM(oa.ota_name), ''),
          NULLIF(TRIM(oaf.ota_name), ''),
          'OTA'
        )
      )
      ELSE COALESCE(NULLIF(TRIM(ob.catalog_item_name), ''), CONCAT('Concepto #', COALESCE(ob.id_line_item_catalog, 0)))
    END AS concept_display_name,
    COALESCE(pn.parent_catalog_name, '') AS parent_concept_name,
    ob.description,
    ob.reference,
    ob.method,
    ob.service_date,
    ob.created_at,
    ob.created_date AS obligation_date,
    ob.amount_cents,
    ob.paid_cents,
    ob.remaining_cents,
    ob.currency,
    ob.status,
    ob.payment_status,
    CASE
      WHEN COALESCE(tp.has_tax_parent, 0) = 1
           OR LOWER(TRIM(COALESCE(tp.tax_parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(pn.parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(ob.catalog_item_name, ''))) LIKE 'pago de impuestos%'
      THEN 1
      ELSE 0
    END AS has_tax_parent_line_item_type,
    CASE
      WHEN COALESCE(tp.has_tax_parent, 0) = 1
           OR LOWER(TRIM(COALESCE(tp.tax_parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(pn.parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(ob.catalog_item_name, ''))) LIKE 'pago de impuestos%'
      THEN 'tax_payment'
      WHEN oa.id_ota_account IS NOT NULL
           AND oa.id_service_fee_payment_catalog IS NOT NULL
           AND oa.id_service_fee_payment_catalog = ob.id_line_item_catalog
      THEN 'ota_payment'
      WHEN ofc.fee_ota_account_id IS NOT NULL
      THEN 'ota_payment'
      ELSE 'property_payment'
    END AS obligation_type_key,
    CASE
      WHEN COALESCE(tp.has_tax_parent, 0) = 1
           OR LOWER(TRIM(COALESCE(tp.tax_parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(pn.parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(ob.catalog_item_name, ''))) LIKE 'pago de impuestos%'
      THEN 'Pago de impuesto'
      WHEN oa.id_ota_account IS NOT NULL
           AND oa.id_service_fee_payment_catalog IS NOT NULL
           AND oa.id_service_fee_payment_catalog = ob.id_line_item_catalog
      THEN 'Pago a OTA'
      WHEN ofc.fee_ota_account_id IS NOT NULL
      THEN 'Pago a OTA'
      ELSE 'Pago a propiedad'
    END AS obligation_type_label,
    CASE
      WHEN COALESCE(tp.has_tax_parent, 0) = 1
           OR LOWER(TRIM(COALESCE(tp.tax_parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(pn.parent_catalog_name, ''))) LIKE '%impuesto%'
           OR LOWER(TRIM(COALESCE(ob.catalog_item_name, ''))) LIKE 'pago de impuestos%'
      THEN COALESCE(
             NULLIF(TRIM(tp.tax_parent_catalog_name), ''),
             NULLIF(TRIM(pn.parent_catalog_name), ''),
             'Impuesto'
           )
      WHEN oa.id_ota_account IS NOT NULL
           AND oa.id_service_fee_payment_catalog IS NOT NULL
           AND oa.id_service_fee_payment_catalog = ob.id_line_item_catalog
      THEN COALESCE(NULLIF(TRIM(oa.ota_name), ''), NULLIF(TRIM(oaf.ota_name), ''), 'OTA')
      WHEN ofc.fee_ota_account_id IS NOT NULL
      THEN COALESCE(NULLIF(TRIM(oaf.ota_name), ''), NULLIF(TRIM(oa.ota_name), ''), 'OTA')
      ELSE COALESCE(NULLIF(TRIM(ob.property_name), ''), 'Propiedad')
    END AS obligation_target_name,
    COALESCE(tp.tax_parent_catalog_name, '') AS tax_parent_catalog_name,
    COALESCE(oa.id_ota_account, ofc.fee_ota_account_id, 0) AS resolved_ota_account_id,
    COALESCE(oa.ota_name, oaf.ota_name, '') AS ota_name,
    COALESCE(oa.ota_platform, oaf.ota_platform, '') AS ota_platform
  FROM obligation_base ob
  LEFT JOIN tax_parent tp
    ON tp.id_line_item = ob.id_line_item
  LEFT JOIN parent_name pn
    ON pn.id_line_item = ob.id_line_item
  LEFT JOIN ota_choice oc
    ON oc.id_line_item = ob.id_line_item
  LEFT JOIN ota_fee_catalog ofc
    ON ofc.id_line_item = ob.id_line_item
  LEFT JOIN ota_account res_oa
    ON res_oa.id_ota_account = ob.reservation_ota_account_id
   AND res_oa.deleted_at IS NULL
  LEFT JOIN (
    SELECT
      id_ota_account,
      ota_name,
      platform AS ota_platform,
      id_service_fee_payment_catalog
    FROM ota_account
    WHERE deleted_at IS NULL
      AND is_active = 1
  ) oa
    ON oa.id_ota_account = oc.id_ota_account
  LEFT JOIN (
    SELECT
      id_ota_account,
      ota_name,
      platform AS ota_platform
    FROM ota_account
    WHERE deleted_at IS NULL
      AND is_active = 1
  ) oaf
    ON oaf.id_ota_account = ofc.fee_ota_account_id
  WHERE (v_payment_status = '' OR ob.payment_status = v_payment_status)
  ORDER BY
    CASE ob.payment_status
      WHEN 'pending' THEN 1
      WHEN 'partial' THEN 2
      WHEN 'paid' THEN 3
      ELSE 4
    END,
    ob.created_at DESC,
    ob.id_line_item DESC
  LIMIT v_limit_rows;
END $$

DELIMITER ;
