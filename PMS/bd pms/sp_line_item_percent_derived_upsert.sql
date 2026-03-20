DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_line_item_percent_derived_upsert` $$
CREATE PROCEDURE `sp_line_item_percent_derived_upsert` (
  IN p_id_folio BIGINT,
  IN p_id_reservation BIGINT,
  IN p_parent_catalog_id BIGINT,
  IN p_service_date DATE,
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_queue_parent_catalog_id BIGINT;
  DECLARE v_queue_depth INT DEFAULT 0;
  DECLARE v_parent_sale_item_id BIGINT;
  DECLARE v_parent_amount_cents INT DEFAULT 0;
  DECLARE v_parent_catalog_type VARCHAR(32);
  DECLARE v_parent_item_type VARCHAR(32) DEFAULT 'sale_item';
  DECLARE v_child_catalog_id BIGINT;
  DECLARE v_child_catalog_type VARCHAR(32);
  DECLARE v_child_allow_negative TINYINT DEFAULT 0;
  DECLARE v_percent_value DECIMAL(9,4);
  DECLARE v_component_cents INT DEFAULT 0;
  DECLARE v_base_cents INT DEFAULT 0;
  DECLARE v_child_amount_cents INT DEFAULT 0;
  DECLARE v_existing_child_sale_item_id BIGINT DEFAULT 0;
  DECLARE v_loop_guard INT DEFAULT 0;
  DECLARE v_pass INT DEFAULT 0;
  DECLARE v_derived_desc TEXT;
  DECLARE v_parent_item_name VARCHAR(255);
  DECLARE v_child_item_name VARCHAR(255);
  DECLARE v_child_item_type VARCHAR(32) DEFAULT 'sale_item';
  DECLARE v_folio_currency VARCHAR(10) DEFAULT 'MXN';
  DECLARE v_company_code VARCHAR(100) DEFAULT NULL;
  DECLARE v_property_code VARCHAR(100) DEFAULT NULL;

  IF p_id_folio IS NULL OR p_id_folio <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio id is required';
  END IF;
  IF p_parent_catalog_id IS NULL OR p_parent_catalog_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'parent catalog id is required';
  END IF;

  SELECT f.currency
    INTO v_folio_currency
  FROM folio f
  WHERE f.id_folio = p_id_folio
    AND f.deleted_at IS NULL
  LIMIT 1;

  SET v_folio_currency = COALESCE(NULLIF(TRIM(v_folio_currency), ''), 'MXN');

  SELECT c.code, p.code
    INTO v_company_code, v_property_code
  FROM folio f
  JOIN reservation r ON r.id_reservation = f.id_reservation
  JOIN property p ON p.id_property = r.id_property
  JOIN company c ON c.id_company = p.id_company
  WHERE f.id_folio = p_id_folio
    AND f.deleted_at IS NULL
  LIMIT 1;

  CALL sp_authz_assert(
    v_company_code,
    p_created_by,
    'reservations.post_charge',
    v_property_code,
    NULL
  );

  DROP TEMPORARY TABLE IF EXISTS tmp_lipdu_queue;
  CREATE TEMPORARY TABLE tmp_lipdu_queue (
    parent_catalog_id BIGINT PRIMARY KEY,
    depth INT NOT NULL DEFAULT 0,
    processed TINYINT NOT NULL DEFAULT 0
  ) ENGINE=MEMORY;

  DROP TEMPORARY TABLE IF EXISTS tmp_lipdu_children;
  CREATE TEMPORARY TABLE tmp_lipdu_children (
    child_catalog_id BIGINT PRIMARY KEY,
    child_item_name VARCHAR(255),
    child_catalog_type VARCHAR(32),
    child_allow_negative TINYINT NOT NULL DEFAULT 0,
    percent_value DECIMAL(9,4),
    processed TINYINT NOT NULL DEFAULT 0
  ) ENGINE=MEMORY;

  SET v_pass = 1;
  pass_loop: LOOP
    IF v_pass > 3 THEN
      LEAVE pass_loop;
    END IF;

    TRUNCATE TABLE tmp_lipdu_queue;
    INSERT IGNORE INTO tmp_lipdu_queue (parent_catalog_id, depth, processed)
    VALUES (p_parent_catalog_id, 0, 0);

    SET v_loop_guard = 0;

    queue_loop: LOOP
      SET v_loop_guard = v_loop_guard + 1;
      IF v_loop_guard > 1000 THEN
        LEAVE queue_loop;
      END IF;

    SET v_queue_parent_catalog_id = NULL;
    SET v_queue_depth = 0;

    SELECT q.parent_catalog_id, q.depth
      INTO v_queue_parent_catalog_id, v_queue_depth
    FROM tmp_lipdu_queue q
    WHERE q.processed = 0
    ORDER BY q.depth, q.parent_catalog_id
    LIMIT 1;

      IF v_queue_parent_catalog_id IS NULL THEN
        LEAVE queue_loop;
      END IF;

      UPDATE tmp_lipdu_queue
         SET processed = 1
       WHERE parent_catalog_id = v_queue_parent_catalog_id;

      IF v_queue_depth >= 25 THEN
        ITERATE queue_loop;
      END IF;

      SET v_parent_catalog_type = NULL;
      SELECT lic.catalog_type, lic.item_name
        INTO v_parent_catalog_type, v_parent_item_name
      FROM line_item_catalog lic
      WHERE lic.id_line_item_catalog = v_queue_parent_catalog_id
        AND lic.deleted_at IS NULL
      LIMIT 1;

      SET v_parent_item_type = CASE LOWER(TRIM(COALESCE(v_parent_catalog_type, 'sale_item')))
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
      ELSE 'sale_item'
    END;

      SET v_parent_sale_item_id = NULL;
      SET v_parent_amount_cents = 0;

      SELECT li.id_line_item, li.amount_cents
        INTO v_parent_sale_item_id, v_parent_amount_cents
      FROM line_item li
      WHERE li.id_folio = p_id_folio
        AND li.item_type = v_parent_item_type
        AND li.id_line_item_catalog = v_queue_parent_catalog_id
        AND li.deleted_at IS NULL
        AND li.is_active = 1
        AND (li.service_date <=> p_service_date)
      ORDER BY li.id_line_item DESC
      LIMIT 1;

      IF v_parent_sale_item_id IS NULL OR v_parent_sale_item_id <= 0 THEN
        SELECT li.id_line_item, li.amount_cents
          INTO v_parent_sale_item_id, v_parent_amount_cents
        FROM line_item li
        WHERE li.id_folio = p_id_folio
          AND li.id_line_item_catalog = v_queue_parent_catalog_id
          AND li.deleted_at IS NULL
          AND li.is_active = 1
          AND (li.service_date <=> p_service_date)
        ORDER BY li.id_line_item DESC
        LIMIT 1;
      END IF;

      IF v_parent_sale_item_id IS NULL OR v_parent_sale_item_id <= 0 THEN
        ITERATE queue_loop;
      END IF;

      /* Parent base stays as direct line item amount to avoid circular growth. */
      SET v_parent_amount_cents = COALESCE(v_parent_amount_cents, 0);

      TRUNCATE TABLE tmp_lipdu_children;

      INSERT IGNORE INTO tmp_lipdu_children (child_catalog_id, child_item_name, child_catalog_type, child_allow_negative, percent_value, processed)
      SELECT
        child.id_line_item_catalog,
        child.item_name,
        child.catalog_type,
        COALESCE(child.allow_negative, 0),
        COALESCE(lcp.percent_value, 0),
        0
      FROM line_item_catalog_parent lcp
      JOIN line_item_catalog child
        ON child.id_line_item_catalog = lcp.id_sale_item_catalog
       AND child.deleted_at IS NULL
       AND child.is_active = 1
       AND child.catalog_type IN ('sale_item','tax_rule','payment','obligation','income')
      WHERE lcp.id_parent_sale_item_catalog = v_queue_parent_catalog_id
        AND lcp.id_sale_item_catalog <> v_queue_parent_catalog_id
        AND lcp.deleted_at IS NULL
        AND lcp.is_active = 1
        AND lcp.percent_value IS NOT NULL;

      child_loop: LOOP
      SET v_child_catalog_id = NULL;
      SET v_child_item_name = NULL;
      SET v_child_catalog_type = NULL;
      SET v_child_allow_negative = 0;
      SET v_percent_value = 0;

      SELECT c.child_catalog_id, c.child_item_name, c.child_catalog_type, c.child_allow_negative, c.percent_value
        INTO v_child_catalog_id, v_child_item_name, v_child_catalog_type, v_child_allow_negative, v_percent_value
      FROM tmp_lipdu_children c
      WHERE c.processed = 0
      ORDER BY c.child_catalog_id
      LIMIT 1;

      SET v_child_item_type = CASE LOWER(TRIM(COALESCE(v_child_catalog_type, 'sale_item')))
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
        ELSE 'sale_item'
      END;

      IF v_child_catalog_id IS NULL THEN
        LEAVE child_loop;
      END IF;

      UPDATE tmp_lipdu_children
         SET processed = 1
       WHERE child_catalog_id = v_child_catalog_id;

      /* Advanced components: pick one best line per component catalog for this relation. */
      WITH RECURSIVE component_roots AS (
        SELECT
          picked.root_id,
          picked.root_sign
        FROM (
          SELECT
            (
              SELECT li_pick.id_line_item
              FROM line_item li_pick
              JOIN line_item_catalog comp_cat_pick
                ON comp_cat_pick.id_line_item_catalog = lcc.id_component_line_item_catalog
               AND comp_cat_pick.deleted_at IS NULL
              JOIN line_item_catalog parent_cat_pick
                ON parent_cat_pick.id_line_item_catalog = v_queue_parent_catalog_id
               AND parent_cat_pick.deleted_at IS NULL
              WHERE li_pick.id_folio = p_id_folio
                AND li_pick.id_line_item_catalog = lcc.id_component_line_item_catalog
                AND li_pick.deleted_at IS NULL
                AND li_pick.is_active = 1
              ORDER BY
                CASE
                  WHEN COALESCE(li_pick.description, '') = CONCAT(
                    COALESCE(comp_cat_pick.item_name, ''),
                    ' / ',
                    COALESCE(parent_cat_pick.item_name, '')
                  ) THEN 0
                  WHEN COALESCE(li_pick.description, '') = CONCAT('[AUTO-DERIVED parent_line_item=', v_parent_sale_item_id, ']') THEN 1
                  WHEN (li_pick.service_date <=> p_service_date) THEN 2
                  WHEN p_service_date IS NOT NULL AND li_pick.service_date IS NULL THEN 3
                  ELSE 4
                END,
                li_pick.id_line_item DESC
              LIMIT 1
            ) AS root_id,
            CASE WHEN COALESCE(lcc.is_positive, 1) = 1 THEN 1 ELSE -1 END AS root_sign
          FROM line_item_catalog_calc lcc
          WHERE lcc.id_line_item_catalog = v_child_catalog_id
            AND lcc.id_parent_line_item_catalog = v_queue_parent_catalog_id
            AND lcc.id_component_line_item_catalog <> v_child_catalog_id
            AND lcc.deleted_at IS NULL
            AND lcc.is_active = 1
        ) picked
        WHERE picked.root_id IS NOT NULL
      ),
      component_tree AS (
        SELECT
          cr.root_id,
          cr.root_sign,
          li.id_line_item,
          li.id_line_item_catalog,
          COALESCE(li.amount_cents, 0) AS amount_cents,
          0 AS depth,
          CAST(li.id_line_item AS CHAR(2000)) AS path
        FROM component_roots cr
        JOIN line_item li
          ON li.id_line_item = cr.root_id
        UNION ALL
        SELECT
          ct.root_id,
          ct.root_sign,
          ch.id_line_item,
          ch.id_line_item_catalog,
          COALESCE(ch.amount_cents, 0) AS amount_cents,
          ct.depth + 1 AS depth,
          CONCAT(ct.path, ',', ch.id_line_item) AS path
        FROM component_tree ct
        JOIN line_item_catalog_parent lcp
          ON lcp.id_parent_sale_item_catalog = ct.id_line_item_catalog
         AND lcp.deleted_at IS NULL
         AND lcp.is_active = 1
         AND COALESCE(lcp.add_to_father_total, 0) = 1
        JOIN line_item_catalog child_cat
          ON child_cat.id_line_item_catalog = lcp.id_sale_item_catalog
         AND child_cat.deleted_at IS NULL
         AND child_cat.is_active = 1
        JOIN line_item_catalog parent_cat
          ON parent_cat.id_line_item_catalog = ct.id_line_item_catalog
         AND parent_cat.deleted_at IS NULL
        JOIN line_item ch
          ON ch.id_folio = p_id_folio
         AND ch.id_line_item_catalog = lcp.id_sale_item_catalog
         AND ch.id_line_item_catalog <> v_child_catalog_id
         AND ch.deleted_at IS NULL
         AND ch.is_active = 1
         AND (ch.service_date <=> p_service_date)
         AND (
           COALESCE(ch.description, '') = CONCAT(COALESCE(child_cat.item_name, ''), ' / ', COALESCE(parent_cat.item_name, ''))
           OR COALESCE(ch.description, '') = CONCAT('[AUTO-DERIVED parent_line_item=', ct.id_line_item, ']')
         )
        WHERE ct.depth < 25
          AND FIND_IN_SET(CAST(ch.id_line_item AS CHAR), ct.path) = 0
      )
      SELECT COALESCE(SUM(x.root_sign * x.root_total), 0)
        INTO v_component_cents
      FROM (
        SELECT
          ct.root_id,
          ct.root_sign,
          COALESCE(SUM(ct.amount_cents), 0) AS root_total
        FROM component_tree ct
        GROUP BY ct.root_id, ct.root_sign
      ) x;

      SET v_base_cents = COALESCE(v_parent_amount_cents, 0) + COALESCE(v_component_cents, 0);
      SET v_child_amount_cents = ROUND(v_base_cents * (COALESCE(v_percent_value, 0) / 100));
      SET v_derived_desc = CONCAT(
        COALESCE(NULLIF(TRIM(v_child_item_name), ''), CONCAT('Catalog#', v_child_catalog_id)),
        ' / ',
        COALESCE(NULLIF(TRIM(v_parent_item_name), ''), CONCAT('Catalog#', v_queue_parent_catalog_id))
      );

      SET v_existing_child_sale_item_id = 0;
      SELECT li.id_line_item
        INTO v_existing_child_sale_item_id
      FROM line_item li
      WHERE li.id_folio = p_id_folio
        AND li.id_line_item_catalog = v_child_catalog_id
        AND li.deleted_at IS NULL
        AND li.is_active = 1
        AND (li.service_date <=> p_service_date)
        AND (
          COALESCE(li.description, '') = COALESCE(v_derived_desc, '')
          OR COALESCE(li.description, '') = CONCAT('[AUTO-DERIVED parent_line_item=', v_parent_sale_item_id, ']')
          OR (
            (COALESCE(li.description, '') = '' OR INSTR(COALESCE(li.description, ''), ' / ') = 0)
            AND (
              SELECT COUNT(*)
              FROM line_item_catalog_parent lcp_cnt
              WHERE lcp_cnt.id_sale_item_catalog = li.id_line_item_catalog
                AND lcp_cnt.deleted_at IS NULL
                AND lcp_cnt.is_active = 1
            ) = 1
          )
        )
      ORDER BY li.id_line_item DESC
      LIMIT 1;

      IF COALESCE(v_child_amount_cents, 0) <= 0
         AND COALESCE(v_child_allow_negative, 0) = 0 THEN
        IF v_existing_child_sale_item_id IS NOT NULL AND v_existing_child_sale_item_id > 0 THEN
          UPDATE line_item
             SET is_active = 0,
                 status = 'void',
                 deleted_at = NOW(),
                 updated_at = NOW()
           WHERE id_line_item = v_existing_child_sale_item_id
             AND deleted_at IS NULL;

          UPDATE line_item_hierarchy
             SET is_active = 0,
                 deleted_at = NOW(),
                 updated_at = NOW(),
                 updated_by = p_created_by
           WHERE id_line_item_child = v_existing_child_sale_item_id
             AND deleted_at IS NULL
             AND is_active = 1;
        END IF;
        ITERATE child_loop;
      END IF;

      IF v_existing_child_sale_item_id IS NULL OR v_existing_child_sale_item_id <= 0 THEN
        INSERT INTO line_item (
          item_type,
          id_user,
          id_folio,
          id_line_item_catalog,
          item_name,
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
          v_child_item_type,
          p_created_by,
          p_id_folio,
          v_child_catalog_id,
          v_child_item_name,
          v_derived_desc,
          p_service_date,
          1,
          v_child_amount_cents,
          v_child_amount_cents,
          v_folio_currency,
          CASE WHEN v_child_item_type = 'payment' THEN v_child_item_name ELSE NULL END,
          0,
          NULL,
          'posted',
          NULL,
          1,
          NOW(),
          p_created_by,
          NOW()
        );
        SET v_existing_child_sale_item_id = LAST_INSERT_ID();
      ELSE
        UPDATE line_item
           SET item_type = v_child_item_type,
               item_name = COALESCE(v_child_item_name, item_name),
               description = v_derived_desc,
               service_date = p_service_date,
               quantity = 1,
               unit_price_cents = v_child_amount_cents,
               amount_cents = v_child_amount_cents,
               currency = v_folio_currency,
               method = CASE
                 WHEN v_child_item_type = 'payment' THEN COALESCE(v_child_item_name, method)
                 ELSE method
               END,
               discount_amount_cents = 0,
               status = 'posted',
               is_active = 1,
               deleted_at = NULL,
               updated_at = NOW()
         WHERE id_line_item = v_existing_child_sale_item_id;
      END IF;

      INSERT INTO line_item_hierarchy (
        id_line_item_child,
        id_line_item_parent,
        relation_kind,
        is_active,
        deleted_at,
        created_by,
        updated_by
      ) VALUES (
        v_existing_child_sale_item_id,
        v_parent_sale_item_id,
        'derived_percent',
        1,
        NULL,
        p_created_by,
        p_created_by
      )
      ON DUPLICATE KEY UPDATE
        id_line_item_parent = VALUES(id_line_item_parent),
        relation_kind = VALUES(relation_kind),
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW(),
        updated_by = VALUES(updated_by);

      INSERT IGNORE INTO tmp_lipdu_queue (parent_catalog_id, depth, processed)
      VALUES (v_child_catalog_id, v_queue_depth + 1, 0);
      END LOOP;
    END LOOP;

    SET v_pass = v_pass + 1;
  END LOOP;

  DROP TEMPORARY TABLE IF EXISTS tmp_lipdu_children;
  DROP TEMPORARY TABLE IF EXISTS tmp_lipdu_queue;
END $$

DELIMITER ;
