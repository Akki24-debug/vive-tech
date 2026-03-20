DELIMITER $$

DROP PROCEDURE IF EXISTS sp_activity_upsert $$
CREATE PROCEDURE sp_activity_upsert
(
  IN p_id_company       BIGINT,
  IN p_id_property      BIGINT,
  IN p_code             VARCHAR(32),
  IN p_name             VARCHAR(120),
  IN p_type             VARCHAR(10),      -- 'tour' o 'vibe'
  IN p_description      TEXT,
  IN p_duration_minutes INT,
  IN p_base_price_cents INT,
  IN p_id_sale_item_catalog BIGINT,
  IN p_currency         VARCHAR(10),
  IN p_capacity_default INT,
  IN p_location         VARCHAR(255),
  IN p_is_active        TINYINT
)
proc:BEGIN
  DECLARE v_id_activity BIGINT;
  DECLARE v_sale_item_catalog BIGINT;

  IF p_type NOT IN ('tour','vibe') THEN
    SELECT 'ERROR' AS status, 'type must be tour or vibe' AS message; LEAVE proc;
  END IF;

  IF p_id_sale_item_catalog IS NOT NULL AND p_id_sale_item_catalog <> 0 THEN
    SELECT sic.id_line_item_catalog
      INTO v_sale_item_catalog
    FROM line_item_catalog sic
    JOIN sale_item_category cat
      ON cat.id_sale_item_category = sic.id_category
    WHERE sic.id_line_item_catalog = p_id_sale_item_catalog
      AND sic.catalog_type = 'sale_item'
      AND sic.deleted_at IS NULL
      AND sic.is_active = 1
      AND cat.id_company = p_id_company
      AND cat.deleted_at IS NULL
    LIMIT 1;

    IF v_sale_item_catalog IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown sale item catalog for company';
    END IF;
  ELSE
    SET v_sale_item_catalog = NULL;
  END IF;

  SELECT id_activity INTO v_id_activity
  FROM activity
  WHERE code = p_code
    AND id_company = p_id_company
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_activity IS NULL THEN
    INSERT INTO activity
    (id_company, id_property, id_sale_item_catalog, code, name, type, description, duration_minutes,
     base_price_cents, currency, capacity_default, location, is_active, created_at, updated_at)
    VALUES
    (p_id_company, NULLIF(p_id_property,0), v_sale_item_catalog, p_code, p_name, p_type, p_description, p_duration_minutes,
     COALESCE(p_base_price_cents,0), COALESCE(p_currency,'MXN'), p_capacity_default, p_location,
     COALESCE(p_is_active,1), NOW(), NOW());
    SET v_id_activity = LAST_INSERT_ID();
  ELSE
    UPDATE activity
      SET id_company = p_id_company,
          id_property = NULLIF(p_id_property,0),
          id_sale_item_catalog = v_sale_item_catalog,
          name = p_name,
          type = p_type,
          description = p_description,
          duration_minutes = p_duration_minutes,
          base_price_cents = COALESCE(p_base_price_cents, base_price_cents),
          currency = COALESCE(p_currency, currency),
          capacity_default = p_capacity_default,
          location = p_location,
          is_active = COALESCE(p_is_active, is_active),
          updated_at = NOW()
    WHERE id_activity = v_id_activity;
  END IF;

  SELECT a.*,
         c.code AS company_code, c.legal_name AS company_name,
         p.code AS property_code, p.name AS property_name
  FROM activity a
  JOIN company  c ON c.id_company  = a.id_company
  LEFT JOIN property p ON p.id_property = a.id_property
  WHERE a.id_activity = v_id_activity;
END $$
DELIMITER ;
