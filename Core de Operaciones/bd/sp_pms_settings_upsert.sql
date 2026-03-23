DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_pms_settings_upsert` $$
CREATE PROCEDURE `sp_pms_settings_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_lodging_catalog_ids TEXT,
  IN p_interest_catalog_ids TEXT,
  IN p_payment_catalog_ids TEXT,
  IN p_service_catalog_ids TEXT,
  IN p_created_by BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_has_service_table TINYINT DEFAULT 0;

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

  SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
    INTO v_has_service_table
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings_service_catalog';

  IF p_lodging_catalog_ids IS NOT NULL THEN
    UPDATE pms_settings_lodging_catalog
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_company = v_company_id
       AND ((v_property_id IS NULL AND id_property IS NULL) OR id_property = v_property_id)
       AND deleted_at IS NULL;

    IF p_lodging_catalog_ids <> '' THEN
      INSERT INTO pms_settings_lodging_catalog (
        id_company,
        id_property,
        id_sale_item_catalog,
        is_active,
        deleted_at,
        created_at,
        created_by,
        updated_at
      )
      SELECT
        v_company_id,
        v_property_id,
        sic.id_line_item_catalog,
        1,
        NULL,
        NOW(),
        p_created_by,
        NOW()
      FROM line_item_catalog sic
      JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
      WHERE cat.id_company = v_company_id
        AND cat.deleted_at IS NULL
        AND cat.is_active = 1
        AND sic.catalog_type = 'sale_item'
        AND sic.deleted_at IS NULL
        AND sic.is_active = 1
        AND (v_property_id IS NULL OR cat.id_property IS NULL OR cat.id_property = v_property_id)
        AND FIND_IN_SET(sic.id_line_item_catalog, p_lodging_catalog_ids)
      ON DUPLICATE KEY UPDATE
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW();
    END IF;
  END IF;

  IF p_interest_catalog_ids IS NOT NULL THEN
    UPDATE pms_settings_interest_catalog
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_company = v_company_id
       AND ((v_property_id IS NULL AND id_property IS NULL) OR id_property = v_property_id)
       AND deleted_at IS NULL;

    IF p_interest_catalog_ids <> '' THEN
      INSERT INTO pms_settings_interest_catalog (
        id_company,
        id_property,
        id_sale_item_catalog,
        is_active,
        deleted_at,
        created_at,
        created_by,
        updated_at
      )
      SELECT
        v_company_id,
        v_property_id,
        sic.id_line_item_catalog,
        1,
        NULL,
        NOW(),
        p_created_by,
        NOW()
      FROM line_item_catalog sic
      JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
      WHERE cat.id_company = v_company_id
        AND cat.deleted_at IS NULL
        AND cat.is_active = 1
        AND sic.catalog_type = 'sale_item'
        AND sic.deleted_at IS NULL
        AND sic.is_active = 1
        AND (v_property_id IS NULL OR cat.id_property IS NULL OR cat.id_property = v_property_id)
        AND FIND_IN_SET(sic.id_line_item_catalog, p_interest_catalog_ids)
      ON DUPLICATE KEY UPDATE
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW();
    END IF;
  END IF;

  IF p_payment_catalog_ids IS NOT NULL THEN
    UPDATE pms_settings_payment_catalog
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_company = v_company_id
       AND ((v_property_id IS NULL AND id_property IS NULL) OR id_property = v_property_id)
       AND deleted_at IS NULL;

    IF p_payment_catalog_ids <> '' THEN
      INSERT INTO pms_settings_payment_catalog (
        id_company,
        id_property,
        id_sale_item_catalog,
        is_active,
        deleted_at,
        created_at,
        created_by,
        updated_at
      )
      SELECT
        v_company_id,
        v_property_id,
        sic.id_line_item_catalog,
        1,
        NULL,
        NOW(),
        p_created_by,
        NOW()
      FROM line_item_catalog sic
      JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
      WHERE cat.id_company = v_company_id
        AND cat.deleted_at IS NULL
        AND cat.is_active = 1
        AND sic.catalog_type = 'payment'
        AND sic.deleted_at IS NULL
        AND sic.is_active = 1
        AND (v_property_id IS NULL OR cat.id_property IS NULL OR cat.id_property = v_property_id)
        AND FIND_IN_SET(sic.id_line_item_catalog, p_payment_catalog_ids)
      ON DUPLICATE KEY UPDATE
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW();
    END IF;
  END IF;

  IF p_service_catalog_ids IS NOT NULL THEN
    IF v_has_service_table = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Missing table pms_settings_service_catalog. Run catalog table migration first.';
    END IF;

    UPDATE pms_settings_service_catalog
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_company = v_company_id
       AND ((v_property_id IS NULL AND id_property IS NULL) OR id_property = v_property_id)
       AND deleted_at IS NULL;

    IF p_service_catalog_ids <> '' THEN
      INSERT INTO pms_settings_service_catalog (
        id_company,
        id_property,
        id_sale_item_catalog,
        is_active,
        deleted_at,
        created_at,
        created_by,
        updated_at
      )
      SELECT
        v_company_id,
        v_property_id,
        sic.id_line_item_catalog,
        1,
        NULL,
        NOW(),
        p_created_by,
        NOW()
      FROM line_item_catalog sic
      JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
      WHERE cat.id_company = v_company_id
        AND cat.deleted_at IS NULL
        AND cat.is_active = 1
        AND sic.catalog_type = 'sale_item'
        AND sic.deleted_at IS NULL
        AND sic.is_active = 1
        AND (v_property_id IS NULL OR cat.id_property IS NULL OR cat.id_property = v_property_id)
        AND FIND_IN_SET(sic.id_line_item_catalog, p_service_catalog_ids)
      ON DUPLICATE KEY UPDATE
        is_active = 1,
        deleted_at = NULL,
        updated_at = NOW();
    END IF;
  END IF;

  SELECT
    v_company_id AS id_company,
    v_property_id AS id_property,
    IFNULL((
      SELECT GROUP_CONCAT(DISTINCT l.id_sale_item_catalog ORDER BY l.id_sale_item_catalog)
      FROM pms_settings_lodging_catalog l
      WHERE l.id_company = v_company_id
        AND ((v_property_id IS NULL AND l.id_property IS NULL) OR l.id_property = v_property_id)
        AND l.deleted_at IS NULL
        AND l.is_active = 1
    ), '') AS lodging_catalog_ids,
    IFNULL((
      SELECT GROUP_CONCAT(DISTINCT i.id_sale_item_catalog ORDER BY i.id_sale_item_catalog)
      FROM pms_settings_interest_catalog i
      WHERE i.id_company = v_company_id
        AND ((v_property_id IS NULL AND i.id_property IS NULL) OR i.id_property = v_property_id)
        AND i.deleted_at IS NULL
        AND i.is_active = 1
    ), '') AS interest_catalog_ids,
    IFNULL((
      SELECT GROUP_CONCAT(DISTINCT p.id_sale_item_catalog ORDER BY p.id_sale_item_catalog)
      FROM pms_settings_payment_catalog p
      WHERE p.id_company = v_company_id
        AND ((v_property_id IS NULL AND p.id_property IS NULL) OR p.id_property = v_property_id)
        AND p.deleted_at IS NULL
        AND p.is_active = 1
    ), '') AS payment_catalog_ids,
    CASE
      WHEN v_has_service_table = 1 THEN IFNULL((
        SELECT GROUP_CONCAT(DISTINCT s.id_sale_item_catalog ORDER BY s.id_sale_item_catalog)
        FROM pms_settings_service_catalog s
        WHERE s.id_company = v_company_id
          AND ((v_property_id IS NULL AND s.id_property IS NULL) OR s.id_property = v_property_id)
          AND s.deleted_at IS NULL
          AND s.is_active = 1
      ), '')
      ELSE ''
    END AS service_catalog_ids;
END $$

DELIMITER ;
