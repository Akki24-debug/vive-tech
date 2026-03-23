DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ota_account_upsert` $$
CREATE PROCEDURE `sp_ota_account_upsert` (
  IN p_mode VARCHAR(16),
  IN p_id_ota_account BIGINT,
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_platform VARCHAR(32),
  IN p_ota_name VARCHAR(150),
  IN p_external_code VARCHAR(120),
  IN p_contact_email VARCHAR(190),
  IN p_timezone VARCHAR(64),
  IN p_notes TEXT,
  IN p_id_service_fee_payment_catalog BIGINT,
  IN p_is_active TINYINT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_mode VARCHAR(16);
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_effective_id BIGINT;
  DECLARE v_platform VARCHAR(32);
  DECLARE v_ota_name VARCHAR(150);
  DECLARE v_timezone VARCHAR(64);
  DECLARE v_current_company_id BIGINT;
  DECLARE v_service_fee_payment_catalog BIGINT DEFAULT NULL;

  SET v_mode = LOWER(TRIM(COALESCE(p_mode, '')));
  IF v_mode NOT IN ('create', 'update', 'delete') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid mode';
  END IF;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'company code is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unknown company';
  END IF;

  SET v_effective_id = COALESCE(p_id_ota_account, 0);

  IF v_mode IN ('update', 'delete') THEN
    IF v_effective_id <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota account id is required';
    END IF;

    SELECT id_company
      INTO v_current_company_id
    FROM ota_account
    WHERE id_ota_account = v_effective_id
      AND deleted_at IS NULL
    LIMIT 1;

    IF v_current_company_id IS NULL OR v_current_company_id <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota account not found';
    END IF;

    IF v_current_company_id <> v_company_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ota account does not belong to company';
    END IF;
  END IF;

  IF v_mode = 'delete' THEN
    UPDATE ota_account
       SET is_active = 0,
           deleted_at = NOW(),
           updated_by = p_actor_user_id,
           updated_at = NOW()
     WHERE id_ota_account = v_effective_id
       AND deleted_at IS NULL;

    UPDATE ota_account_lodging_catalog
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_ota_account = v_effective_id
       AND deleted_at IS NULL;

    UPDATE ota_account_info_catalog
       SET is_active = 0,
           deleted_at = NOW(),
           updated_at = NOW()
     WHERE id_ota_account = v_effective_id
       AND deleted_at IS NULL;

    SELECT
      oa.id_ota_account,
      oa.id_company,
      oa.id_property,
      p.code AS property_code,
      oa.platform,
      oa.ota_name,
      oa.external_code,
      oa.contact_email,
      oa.timezone,
      oa.notes,
      oa.id_service_fee_payment_catalog,
      COALESCE(sfp.item_name, '') AS service_fee_payment_catalog_name,
      oa.is_active,
      oa.deleted_at,
      oa.created_at,
      oa.updated_at
    FROM ota_account oa
    JOIN property p ON p.id_property = oa.id_property
    LEFT JOIN line_item_catalog sfp
      ON sfp.id_line_item_catalog = oa.id_service_fee_payment_catalog
    WHERE oa.id_ota_account = v_effective_id
    LIMIT 1;
    LEAVE proc;
  END IF;

  IF p_property_code IS NULL OR TRIM(p_property_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'property code is required';
  END IF;

  SELECT id_property
    INTO v_property_id
  FROM property
  WHERE code = p_property_code
    AND id_company = v_company_id
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_property_id IS NULL OR v_property_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'unknown property';
  END IF;

  SET v_service_fee_payment_catalog = NULL;
  IF p_id_service_fee_payment_catalog IS NOT NULL
     AND p_id_service_fee_payment_catalog > 0 THEN
    SELECT lic.id_line_item_catalog
      INTO v_service_fee_payment_catalog
    FROM line_item_catalog lic
    JOIN sale_item_category cat
      ON cat.id_sale_item_category = lic.id_category
     AND cat.id_company = v_company_id
     AND cat.deleted_at IS NULL
     AND cat.is_active = 1
    WHERE lic.id_line_item_catalog = p_id_service_fee_payment_catalog
      AND lic.deleted_at IS NULL
      AND lic.is_active = 1
      AND lic.catalog_type = 'obligation'
    LIMIT 1;

    IF v_service_fee_payment_catalog IS NULL OR v_service_fee_payment_catalog <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid service fee obligation catalog';
    END IF;
  END IF;

  SET v_platform = LOWER(TRIM(COALESCE(p_platform, 'other')));
  IF v_platform IN ('abb') THEN
    SET v_platform = 'airbnb';
  END IF;
  IF v_platform NOT IN ('airbnb', 'booking', 'expedia', 'other') THEN
    SET v_platform = 'other';
  END IF;

  SET v_ota_name = TRIM(COALESCE(p_ota_name, ''));
  IF v_ota_name = '' THEN
    SET v_ota_name = CASE v_platform
      WHEN 'airbnb' THEN 'Airbnb'
      WHEN 'booking' THEN 'Booking'
      WHEN 'expedia' THEN 'Expedia'
      ELSE 'Directo/Otro'
    END;
  END IF;

  SET v_timezone = TRIM(COALESCE(p_timezone, ''));
  IF v_timezone = '' THEN
    SET v_timezone = 'America/Mexico_City';
  END IF;

  IF v_mode = 'create' THEN
    INSERT INTO ota_account (
      id_company,
      id_property,
      platform,
      ota_name,
      external_code,
      contact_email,
      timezone,
      notes,
      id_service_fee_payment_catalog,
      is_active,
      deleted_at,
      created_by,
      updated_by
    ) VALUES (
      v_company_id,
      v_property_id,
      v_platform,
      v_ota_name,
      NULLIF(TRIM(p_external_code), ''),
      NULLIF(TRIM(p_contact_email), ''),
      v_timezone,
      NULLIF(TRIM(p_notes), ''),
      v_service_fee_payment_catalog,
      CASE WHEN COALESCE(p_is_active, 1) <> 0 THEN 1 ELSE 0 END,
      NULL,
      p_actor_user_id,
      p_actor_user_id
    );
    SET v_effective_id = LAST_INSERT_ID();
  ELSE
    UPDATE ota_account
       SET id_property = v_property_id,
           platform = v_platform,
           ota_name = v_ota_name,
           external_code = NULLIF(TRIM(p_external_code), ''),
           contact_email = NULLIF(TRIM(p_contact_email), ''),
           timezone = v_timezone,
           notes = NULLIF(TRIM(p_notes), ''),
           id_service_fee_payment_catalog = v_service_fee_payment_catalog,
           is_active = CASE WHEN COALESCE(p_is_active, 1) <> 0 THEN 1 ELSE 0 END,
           deleted_at = CASE WHEN COALESCE(p_is_active, 1) <> 0 THEN NULL ELSE deleted_at END,
           updated_by = p_actor_user_id,
           updated_at = NOW()
     WHERE id_ota_account = v_effective_id;
  END IF;

  SELECT
    oa.id_ota_account,
    oa.id_company,
    oa.id_property,
    p.code AS property_code,
    oa.platform,
    oa.ota_name,
    oa.external_code,
    oa.contact_email,
    oa.timezone,
    oa.notes,
    oa.id_service_fee_payment_catalog,
    COALESCE(sfp.item_name, '') AS service_fee_payment_catalog_name,
    oa.is_active,
    oa.deleted_at,
    oa.created_at,
    oa.updated_at
  FROM ota_account oa
  JOIN property p ON p.id_property = oa.id_property
  LEFT JOIN line_item_catalog sfp
    ON sfp.id_line_item_catalog = oa.id_service_fee_payment_catalog
  WHERE oa.id_ota_account = v_effective_id
  LIMIT 1;
END $$

DELIMITER ;
