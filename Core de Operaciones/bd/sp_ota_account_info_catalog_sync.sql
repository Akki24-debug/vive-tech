DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ota_account_info_catalog_sync` $$
CREATE PROCEDURE `sp_ota_account_info_catalog_sync` (
  IN p_company_code VARCHAR(100),
  IN p_id_ota_account BIGINT,
  IN p_catalog_ids_csv TEXT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_ota_company_id BIGINT;
  DECLARE v_csv TEXT;
  DECLARE v_token VARCHAR(64);
  DECLARE v_id BIGINT;
  DECLARE v_pos INT;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'company code is required';
  END IF;
  IF p_id_ota_account IS NULL OR p_id_ota_account <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota account id is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unknown company';
  END IF;

  SELECT id_company
    INTO v_ota_company_id
  FROM ota_account
  WHERE id_ota_account = p_id_ota_account
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_ota_company_id IS NULL OR v_ota_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota account not found';
  END IF;
  IF v_ota_company_id <> v_company_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota account does not belong to company';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_ota_info_catalog_ids;
  CREATE TEMPORARY TABLE tmp_ota_info_catalog_ids (
    id_line_item_catalog BIGINT PRIMARY KEY
  ) ENGINE=MEMORY;

  SET v_csv = REPLACE(TRIM(COALESCE(p_catalog_ids_csv, '')), ' ', '');
  SET v_csv = TRIM(BOTH ',' FROM v_csv);

  parse_loop: WHILE v_csv IS NOT NULL AND v_csv <> '' DO
    SET v_pos = INSTR(v_csv, ',');
    IF v_pos > 0 THEN
      SET v_token = SUBSTRING(v_csv, 1, v_pos - 1);
      SET v_csv = SUBSTRING(v_csv, v_pos + 1);
    ELSE
      SET v_token = v_csv;
      SET v_csv = '';
    END IF;

    SET v_id = CAST(TRIM(v_token) AS UNSIGNED);
    IF v_id IS NOT NULL AND v_id > 0 THEN
      INSERT IGNORE INTO tmp_ota_info_catalog_ids (id_line_item_catalog)
      VALUES (v_id);
    END IF;
  END WHILE parse_loop;

  UPDATE ota_account_info_catalog
     SET is_active = 0,
         deleted_at = NOW(),
         updated_at = NOW()
   WHERE id_ota_account = p_id_ota_account
     AND deleted_at IS NULL
     AND id_line_item_catalog NOT IN (
       SELECT id_line_item_catalog FROM tmp_ota_info_catalog_ids
     );

  UPDATE ota_account_info_catalog oaic
  JOIN tmp_ota_info_catalog_ids t
    ON t.id_line_item_catalog = oaic.id_line_item_catalog
   SET oaic.is_active = 1,
       oaic.deleted_at = NULL,
       oaic.updated_at = NOW()
 WHERE oaic.id_ota_account = p_id_ota_account;

  INSERT INTO ota_account_info_catalog (
    id_ota_account,
    id_line_item_catalog,
    sort_order,
    is_active,
    deleted_at
  )
  SELECT
    p_id_ota_account,
    t.id_line_item_catalog,
    0,
    1,
    NULL
  FROM tmp_ota_info_catalog_ids t
  JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = t.id_line_item_catalog
   AND lic.deleted_at IS NULL
   AND lic.is_active = 1
  JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
   AND cat.deleted_at IS NULL
   AND cat.is_active = 1
   AND cat.id_company = v_company_id
  LEFT JOIN ota_account_info_catalog existing
    ON existing.id_ota_account = p_id_ota_account
   AND existing.id_line_item_catalog = t.id_line_item_catalog
  WHERE existing.id_ota_account_info_catalog IS NULL;

  SELECT
    oaic.id_ota_account_info_catalog,
    oaic.id_ota_account,
    oaic.id_line_item_catalog,
    lic.item_name,
    lic.catalog_type,
    cat.category_name,
    oaic.sort_order,
    oaic.is_active,
    oaic.deleted_at
  FROM ota_account_info_catalog oaic
  JOIN line_item_catalog lic
    ON lic.id_line_item_catalog = oaic.id_line_item_catalog
  LEFT JOIN sale_item_category cat
    ON cat.id_sale_item_category = lic.id_category
  WHERE oaic.id_ota_account = p_id_ota_account
    AND oaic.deleted_at IS NULL
    AND oaic.is_active = 1
  ORDER BY oaic.sort_order, lic.item_name, oaic.id_line_item_catalog;

  DROP TEMPORARY TABLE IF EXISTS tmp_ota_info_catalog_ids;
END $$

DELIMITER ;
