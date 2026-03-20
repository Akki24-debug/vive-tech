DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ota_ical_feed_lodging_upsert` $$
CREATE PROCEDURE `sp_ota_ical_feed_lodging_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_id_ota_ical_feed BIGINT,
  IN p_lodging_catalog_ids TEXT,
  IN p_updated_by BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT DEFAULT 0;
  DECLARE v_id_property BIGINT DEFAULT 0;
  DECLARE v_csv TEXT;
  DECLARE v_token VARCHAR(64);
  DECLARE v_catalog_id BIGINT;
  DECLARE v_sort INT DEFAULT 0;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_id_ota_ical_feed IS NULL OR p_id_ota_ical_feed <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Feed id is required';
  END IF;

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT f.id_property
    INTO v_id_property
  FROM ota_ical_feed f
  WHERE f.id_ota_ical_feed = p_id_ota_ical_feed
    AND f.id_company = v_company_id
    AND f.deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL OR v_id_property <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Feed not found for company';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_ota_lodging_ids;
  CREATE TEMPORARY TABLE tmp_ota_lodging_ids (
    id_line_item_catalog BIGINT PRIMARY KEY,
    sort_order INT NOT NULL DEFAULT 0
  ) ENGINE=MEMORY;

  SET v_csv = TRIM(COALESCE(p_lodging_catalog_ids, ''));
  WHILE v_csv <> '' DO
    SET v_token = TRIM(SUBSTRING_INDEX(v_csv, ',', 1));
    SET v_csv = IF(INSTR(v_csv, ',') > 0, SUBSTRING(v_csv, INSTR(v_csv, ',') + 1), '');
    SET v_catalog_id = CAST(v_token AS UNSIGNED);
    IF v_catalog_id IS NOT NULL AND v_catalog_id > 0 THEN
      INSERT INTO tmp_ota_lodging_ids (id_line_item_catalog, sort_order)
      VALUES (v_catalog_id, v_sort)
      ON DUPLICATE KEY UPDATE sort_order = LEAST(sort_order, VALUES(sort_order));
      SET v_sort = v_sort + 1;
    END IF;
  END WHILE;

  DELETE t
  FROM tmp_ota_lodging_ids t
  LEFT JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = t.id_line_item_catalog
   AND lic.catalog_type = 'sale_item'
   AND lic.deleted_at IS NULL
   AND lic.is_active = 1
  LEFT JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
   AND cat.id_company = v_company_id
   AND cat.deleted_at IS NULL
   AND cat.is_active = 1
   AND (cat.id_property = v_id_property OR cat.id_property IS NULL)
  WHERE lic.id_line_item_catalog IS NULL
     OR cat.id_sale_item_category IS NULL;

  UPDATE ota_ical_feed_lodging_catalog
     SET is_active = 0,
         deleted_at = NOW(),
         updated_at = NOW()
   WHERE id_ota_ical_feed = p_id_ota_ical_feed
     AND deleted_at IS NULL;

  INSERT INTO ota_ical_feed_lodging_catalog (
    id_ota_ical_feed,
    id_line_item_catalog,
    sort_order,
    is_active,
    deleted_at,
    created_at,
    updated_at
  )
  SELECT
    p_id_ota_ical_feed,
    t.id_line_item_catalog,
    t.sort_order,
    1,
    NULL,
    NOW(),
    NOW()
  FROM tmp_ota_lodging_ids t
  ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  DROP TEMPORARY TABLE IF EXISTS tmp_ota_lodging_ids;

  SELECT
    flc.id_ota_ical_feed,
    flc.id_line_item_catalog
  FROM ota_ical_feed_lodging_catalog flc
  WHERE flc.id_ota_ical_feed = p_id_ota_ical_feed
    AND flc.is_active = 1
    AND flc.deleted_at IS NULL
  ORDER BY flc.sort_order, flc.id_line_item_catalog;
END $$

DELIMITER ;
