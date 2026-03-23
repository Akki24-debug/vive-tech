DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_folio_recalc` $$
CREATE PROCEDURE `sp_folio_recalc` (
  IN p_id_folio BIGINT
)
BEGIN
  DECLARE v_total_cents INT DEFAULT 0;
  DECLARE v_items_cents INT DEFAULT 0;
  DECLARE v_tax_cents INT DEFAULT 0;
  DECLARE v_discount_cents INT DEFAULT 0;
  DECLARE v_payments_cents INT DEFAULT 0;
  DECLARE v_refunds_cents INT DEFAULT 0;
  DECLARE v_balance_cents INT DEFAULT 0;
  DECLARE v_reservation_id BIGINT DEFAULT 0;

  IF p_id_folio IS NULL OR p_id_folio = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio id is required';
  END IF;

  /* Visible tree in folio: roots + independent children + descendants add_to_father_total=1 of visible nodes */
  WITH RECURSIVE visible_tree AS (
    SELECT
      si.id_line_item,
      si.id_line_item_catalog,
      si.item_type,
      COALESCE(si.amount_cents, 0) AS amount_cents,
      COALESCE(si.discount_amount_cents, 0) AS discount_amount_cents,
      COALESCE(si.service_date, DATE(si.created_at)) AS service_key,
      CAST(si.id_line_item AS CHAR(3000)) AS visit_path
    FROM line_item si
    LEFT JOIN line_item_catalog sic
      ON sic.id_line_item_catalog = si.id_line_item_catalog
    WHERE si.id_folio = p_id_folio
      AND si.item_type IN ('sale_item', 'tax_item')
      AND si.deleted_at IS NULL
      AND si.is_active = 1
      AND (si.status IS NULL OR si.status NOT IN ('void','canceled'))
      AND (
        sic.id_line_item_catalog IS NULL
        OR COALESCE(
          (
            SELECT lcp_vis.show_in_folio_relation
            FROM line_item_catalog_parent lcp_vis
            JOIN line_item p_vis
              ON p_vis.id_folio = si.id_folio
             AND p_vis.id_line_item_catalog = lcp_vis.id_parent_sale_item_catalog
             AND p_vis.deleted_at IS NULL
             AND p_vis.is_active = 1
             AND (p_vis.status IS NULL OR p_vis.status NOT IN ('void','canceled'))
             AND (p_vis.service_date <=> si.service_date)
            LEFT JOIN line_item_catalog plic_vis
              ON plic_vis.id_line_item_catalog = p_vis.id_line_item_catalog
            WHERE lcp_vis.id_sale_item_catalog = si.id_line_item_catalog
              AND lcp_vis.deleted_at IS NULL
              AND lcp_vis.is_active = 1
              AND (
                COALESCE(si.description, '') = CONCAT(COALESCE(sic.item_name, ''), ' / ', COALESCE(plic_vis.item_name, ''))
                OR (
                  (COALESCE(si.description, '') = '' OR INSTR(COALESCE(si.description, ''), ' / ') = 0)
                  AND (
                    SELECT COUNT(*)
                    FROM line_item_catalog_parent lcp_cnt_vis
                    WHERE lcp_cnt_vis.id_sale_item_catalog = si.id_line_item_catalog
                      AND lcp_cnt_vis.deleted_at IS NULL
                      AND lcp_cnt_vis.is_active = 1
                  ) = 1
                )
              )
            ORDER BY p_vis.id_line_item DESC
            LIMIT 1
          ),
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
          WHERE lcp_rel.id_sale_item_catalog = si.id_line_item_catalog
            AND lcp_rel.deleted_at IS NULL
            AND lcp_rel.is_active = 1
            AND COALESCE(lcp_rel.add_to_father_total, 1) = 0
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
            AND COALESCE(lcp_rel.add_to_father_total, 1) = 0
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
        )
      )

    UNION ALL

    SELECT
      ch.id_line_item,
      ch.id_line_item_catalog,
      ch.item_type,
      COALESCE(ch.amount_cents, 0) AS amount_cents,
      COALESCE(ch.discount_amount_cents, 0) AS discount_amount_cents,
      COALESCE(ch.service_date, DATE(ch.created_at)) AS service_key,
      CONCAT(vt.visit_path, ',', ch.id_line_item) AS visit_path
    FROM visible_tree vt
    JOIN line_item parent_li
      ON parent_li.id_line_item = vt.id_line_item
    JOIN line_item_catalog_parent lcp_rel
      ON lcp_rel.id_parent_sale_item_catalog = parent_li.id_line_item_catalog
     AND lcp_rel.deleted_at IS NULL
     AND lcp_rel.is_active = 1
     AND COALESCE(lcp_rel.add_to_father_total, 1) = 1
    JOIN line_item ch
      ON ch.id_folio = parent_li.id_folio
     AND ch.id_line_item_catalog = lcp_rel.id_sale_item_catalog
     AND ch.item_type IN ('sale_item', 'tax_item')
     AND ch.deleted_at IS NULL
     AND ch.is_active = 1
     AND (ch.status IS NULL OR ch.status NOT IN ('void','canceled'))
     AND (COALESCE(ch.service_date, DATE(ch.created_at)) <=> COALESCE(parent_li.service_date, DATE(parent_li.created_at)))
    LEFT JOIN line_item_catalog ch_cat
      ON ch_cat.id_line_item_catalog = ch.id_line_item_catalog
    LEFT JOIN line_item_catalog p_cat
      ON p_cat.id_line_item_catalog = parent_li.id_line_item_catalog
    WHERE (
        ch_cat.id_line_item_catalog IS NULL
        OR (
          ch_cat.deleted_at IS NULL
          AND ch_cat.is_active = 1
          AND COALESCE(lcp_rel.show_in_folio_relation, ch_cat.show_in_folio, 1) = 1
        )
      )
      AND (
        COALESCE(ch.description, '') = CONCAT(COALESCE(ch_cat.item_name, ''), ' / ', COALESCE(p_cat.item_name, ''))
        OR (
          (COALESCE(ch.description, '') = '' OR INSTR(COALESCE(ch.description, ''), ' / ') = 0)
          AND (
            SELECT COUNT(*)
            FROM line_item_catalog_parent lcp_cnt
            WHERE lcp_cnt.id_sale_item_catalog = ch.id_line_item_catalog
              AND lcp_cnt.deleted_at IS NULL
              AND lcp_cnt.is_active = 1
          ) = 1
        )
      )
      AND FIND_IN_SET(CAST(ch.id_line_item AS CHAR), vt.visit_path) = 0
  )
  SELECT
    COALESCE(SUM(CASE WHEN z.item_type = 'sale_item' THEN z.amount_cents ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN z.item_type = 'sale_item' THEN z.discount_amount_cents ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN z.item_type = 'tax_item' THEN z.amount_cents ELSE 0 END), 0)
    INTO v_items_cents, v_discount_cents, v_tax_cents
  FROM (
    SELECT
      vt.id_line_item,
      MAX(vt.item_type) AS item_type,
      MAX(vt.amount_cents) AS amount_cents,
      MAX(vt.discount_amount_cents) AS discount_amount_cents
    FROM visible_tree vt
    GROUP BY vt.id_line_item
  ) z;

  SET v_total_cents = v_items_cents + v_tax_cents - v_discount_cents;

  SELECT COALESCE(SUM(p.amount_cents), 0)
    INTO v_payments_cents
  FROM line_item p
  LEFT JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = p.id_line_item_catalog
  WHERE p.item_type = 'payment'
    AND p.id_folio = p_id_folio
    AND p.deleted_at IS NULL
    AND p.is_active = 1
    AND (p.status IS NULL OR p.status NOT IN ('void','canceled'))
    AND (
      p.id_line_item_catalog IS NULL
      OR COALESCE(lic.show_in_folio, 1) = 1
    );

  SELECT COALESCE(SUM(r.amount_cents), 0)
    INTO v_refunds_cents
  FROM refund r
  JOIN line_item p
    ON p.id_line_item = r.id_payment
   AND p.item_type = 'payment'
  LEFT JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = p.id_line_item_catalog
  WHERE p.id_folio = p_id_folio
    AND r.deleted_at IS NULL
    AND r.is_active = 1
    AND (
      p.id_line_item_catalog IS NULL
      OR COALESCE(lic.show_in_folio, 1) = 1
    );

  SET v_balance_cents = v_total_cents - v_payments_cents + v_refunds_cents;

  UPDATE folio
     SET total_cents = v_total_cents,
         balance_cents = v_balance_cents,
         updated_at = NOW()
   WHERE id_folio = p_id_folio;

  SELECT f.id_reservation
    INTO v_reservation_id
  FROM folio f
  WHERE f.id_folio = p_id_folio
  LIMIT 1;

  IF v_reservation_id IS NOT NULL AND v_reservation_id > 0 THEN
    UPDATE reservation r
    JOIN (
      SELECT
        f.id_reservation,
        COALESCE(SUM(f.total_cents), 0) AS reservation_total_cents,
        COALESCE(SUM(f.balance_cents), 0) AS reservation_balance_cents
      FROM folio f
      WHERE f.id_reservation = v_reservation_id
        AND f.deleted_at IS NULL
        AND COALESCE(f.is_active, 1) = 1
      GROUP BY f.id_reservation
    ) x ON x.id_reservation = r.id_reservation
       SET r.total_price_cents = x.reservation_total_cents,
           r.balance_due_cents = x.reservation_balance_cents,
           r.updated_at = NOW()
     WHERE r.id_reservation = v_reservation_id;
  END IF;

  SELECT v_total_cents AS total_cents,
         v_payments_cents AS payments_cents,
         v_refunds_cents AS refunds_cents,
         v_balance_cents AS balance_cents;
END $$

DELIMITER ;
