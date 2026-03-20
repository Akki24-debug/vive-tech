DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_property_upsert` $$
CREATE PROCEDURE `sp_property_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_property_code VARCHAR(100),
  IN p_name          VARCHAR(255),
  IN p_color_hex     VARCHAR(16),
  IN p_description   TEXT,
  IN p_email         VARCHAR(255),
  IN p_phone         VARCHAR(100),
  IN p_website       VARCHAR(255),
  IN p_address_line1 VARCHAR(255),
  IN p_address_line2 VARCHAR(255),
  IN p_city          VARCHAR(120),
  IN p_state         VARCHAR(120),
  IN p_postal_code   VARCHAR(30),
  IN p_country       VARCHAR(120),
  IN p_timezone      VARCHAR(64),
  IN p_currency      VARCHAR(10),
  IN p_check_out_time VARCHAR(32),
  IN p_order_index   INT,
  IN p_is_active     TINYINT,
  IN p_notes         TEXT,
  IN p_has_wifi TINYINT,
  IN p_has_parking TINYINT,
  IN p_has_shared_kitchen TINYINT,
  IN p_has_dining_area TINYINT,
  IN p_has_cleaning_service TINYINT,
  IN p_has_shared_laundry TINYINT,
  IN p_has_purified_water TINYINT,
  IN p_has_security_24h TINYINT,
  IN p_has_self_checkin TINYINT,
  IN p_has_pool TINYINT,
  IN p_has_jacuzzi TINYINT,
  IN p_has_garden_patio TINYINT,
  IN p_has_terrace_rooftop TINYINT,
  IN p_has_hammocks_loungers TINYINT,
  IN p_has_bbq_area TINYINT,
  IN p_has_beach_access TINYINT,
  IN p_has_panoramic_views TINYINT,
  IN p_has_outdoor_lounge TINYINT,
  IN p_offers_airport_transfers TINYINT,
  IN p_offers_tours_activities TINYINT,
  IN p_has_breakfast_available TINYINT,
  IN p_offers_bike_rental TINYINT,
  IN p_has_luggage_storage TINYINT,
  IN p_is_pet_friendly TINYINT,
  IN p_has_accessible_spaces TINYINT,
  IN p_id_owner_payment_obligation_catalog BIGINT,
  IN p_amenities_is_active TINYINT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_id_property_amenities BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();
  DECLARE v_check_out_time_val DATETIME;
  DECLARE v_owner_payment_obligation_catalog BIGINT DEFAULT NULL;
  DECLARE v_color_hex VARCHAR(16) DEFAULT NULL;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;
  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_name IS NULL OR p_name = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property name is required';
  END IF;

  SET v_check_out_time_val = NULL;
  IF p_check_out_time IS NOT NULL AND TRIM(p_check_out_time) <> '' THEN
    SET v_check_out_time_val = STR_TO_DATE(CONCAT('1970-01-01 ', TIME(p_check_out_time)), '%Y-%m-%d %H:%i:%s');
  END IF;

  SET v_color_hex = NULL;
  IF p_color_hex IS NOT NULL AND TRIM(p_color_hex) <> '' THEN
    SET v_color_hex = UPPER(TRIM(p_color_hex));
    IF LEFT(v_color_hex, 1) <> '#' THEN
      SET v_color_hex = CONCAT('#', v_color_hex);
    END IF;
    IF v_color_hex NOT REGEXP '^#[0-9A-F]{6}$' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid property color';
    END IF;
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_owner_payment_obligation_catalog = NULL;
  IF p_id_owner_payment_obligation_catalog IS NOT NULL
     AND p_id_owner_payment_obligation_catalog > 0 THEN
    SELECT lic.id_line_item_catalog
      INTO v_owner_payment_obligation_catalog
    FROM line_item_catalog lic
    JOIN sale_item_category cat
      ON cat.id_sale_item_category = lic.id_category
     AND cat.id_company = v_company_id
     AND cat.deleted_at IS NULL
     AND cat.is_active = 1
    WHERE lic.id_line_item_catalog = p_id_owner_payment_obligation_catalog
      AND lic.deleted_at IS NULL
      AND lic.is_active = 1
      AND lic.catalog_type = 'obligation'
    LIMIT 1;

    IF v_owner_payment_obligation_catalog IS NULL
       OR v_owner_payment_obligation_catalog <= 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid owner payment obligation catalog';
    END IF;
  END IF;

  SELECT id_property
    INTO v_id_property
  FROM property
  WHERE code = p_property_code
    AND id_company = v_company_id
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    INSERT INTO property (
      id_company,
      code,
      name,
      color_hex,
      description,
      email,
      phone,
      website,
      address_line1,
      address_line2,
      city,
      state,
      postal_code,
      country,
      timezone,
      currency,
      check_out_time,
      order_index,
      id_owner_payment_obligation_catalog,
      is_active,
      notes,
      created_at,
      updated_at
    ) VALUES (
      v_company_id,
      p_property_code,
      p_name,
      v_color_hex,
      NULLIF(p_description, ''),
      NULLIF(p_email, ''),
      NULLIF(p_phone, ''),
      NULLIF(p_website, ''),
      NULLIF(p_address_line1, ''),
      NULLIF(p_address_line2, ''),
      NULLIF(p_city, ''),
      NULLIF(p_state, ''),
      NULLIF(p_postal_code, ''),
      NULLIF(p_country, ''),
      COALESCE(NULLIF(p_timezone, ''), 'America/Mexico_City'),
      COALESCE(NULLIF(p_currency, ''), 'MXN'),
      v_check_out_time_val,
      COALESCE(p_order_index, 0),
      v_owner_payment_obligation_catalog,
      COALESCE(p_is_active, 1),
      NULLIF(p_notes, ''),
      v_now,
      v_now
    );
    SET v_id_property = LAST_INSERT_ID();
  ELSE
    UPDATE property
    SET
      name = p_name,
      color_hex = v_color_hex,
      description = NULLIF(p_description, ''),
      email = NULLIF(p_email, ''),
      phone = NULLIF(p_phone, ''),
      website = NULLIF(p_website, ''),
      address_line1 = NULLIF(p_address_line1, ''),
      address_line2 = NULLIF(p_address_line2, ''),
      city = NULLIF(p_city, ''),
      state = NULLIF(p_state, ''),
      postal_code = NULLIF(p_postal_code, ''),
      country = NULLIF(p_country, ''),
      timezone = COALESCE(NULLIF(p_timezone, ''), timezone),
      currency = COALESCE(NULLIF(p_currency, ''), currency),
      check_out_time = v_check_out_time_val,
      order_index = COALESCE(p_order_index, order_index),
      id_owner_payment_obligation_catalog = v_owner_payment_obligation_catalog,
      is_active = COALESCE(p_is_active, is_active),
      notes = COALESCE(NULLIF(p_notes, ''), notes),
      updated_at = v_now
    WHERE id_property = v_id_property;
  END IF;

  /* Upsert amenities */
  SELECT id_property_amenities
    INTO v_id_property_amenities
  FROM property_amenities
  WHERE id_property = v_id_property
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_property_amenities IS NULL THEN
    INSERT INTO property_amenities (
      id_property,
      has_wifi,
      has_parking,
      has_shared_kitchen,
      has_dining_area,
      has_cleaning_service,
      has_shared_laundry,
      has_purified_water,
      has_security_24h,
      has_self_checkin,
      has_pool,
      has_jacuzzi,
      has_garden_patio,
      has_terrace_rooftop,
      has_hammocks_loungers,
      has_bbq_area,
      has_beach_access,
      has_panoramic_views,
      has_outdoor_lounge,
      offers_airport_transfers,
      offers_tours_activities,
      has_breakfast_available,
      offers_bike_rental,
      has_luggage_storage,
      is_pet_friendly,
      has_accessible_spaces,
      is_active,
      created_by,
      created_at,
      updated_at
    ) VALUES (
      v_id_property,
      COALESCE(p_has_wifi, 0),
      COALESCE(p_has_parking, 0),
      COALESCE(p_has_shared_kitchen, 0),
      COALESCE(p_has_dining_area, 0),
      COALESCE(p_has_cleaning_service, 0),
      COALESCE(p_has_shared_laundry, 0),
      COALESCE(p_has_purified_water, 0),
      COALESCE(p_has_security_24h, 0),
      COALESCE(p_has_self_checkin, 0),
      COALESCE(p_has_pool, 0),
      COALESCE(p_has_jacuzzi, 0),
      COALESCE(p_has_garden_patio, 0),
      COALESCE(p_has_terrace_rooftop, 0),
      COALESCE(p_has_hammocks_loungers, 0),
      COALESCE(p_has_bbq_area, 0),
      COALESCE(p_has_beach_access, 0),
      COALESCE(p_has_panoramic_views, 0),
      COALESCE(p_has_outdoor_lounge, 0),
      COALESCE(p_offers_airport_transfers, 0),
      COALESCE(p_offers_tours_activities, 0),
      COALESCE(p_has_breakfast_available, 0),
      COALESCE(p_offers_bike_rental, 0),
      COALESCE(p_has_luggage_storage, 0),
      COALESCE(p_is_pet_friendly, 0),
      COALESCE(p_has_accessible_spaces, 0),
      COALESCE(p_amenities_is_active, 1),
      p_actor_user_id,
      v_now,
      v_now
    );
  ELSE
    UPDATE property_amenities
    SET
      has_wifi = COALESCE(p_has_wifi, has_wifi),
      has_parking = COALESCE(p_has_parking, has_parking),
      has_shared_kitchen = COALESCE(p_has_shared_kitchen, has_shared_kitchen),
      has_dining_area = COALESCE(p_has_dining_area, has_dining_area),
      has_cleaning_service = COALESCE(p_has_cleaning_service, has_cleaning_service),
      has_shared_laundry = COALESCE(p_has_shared_laundry, has_shared_laundry),
      has_purified_water = COALESCE(p_has_purified_water, has_purified_water),
      has_security_24h = COALESCE(p_has_security_24h, has_security_24h),
      has_self_checkin = COALESCE(p_has_self_checkin, has_self_checkin),
      has_pool = COALESCE(p_has_pool, has_pool),
      has_jacuzzi = COALESCE(p_has_jacuzzi, has_jacuzzi),
      has_garden_patio = COALESCE(p_has_garden_patio, has_garden_patio),
      has_terrace_rooftop = COALESCE(p_has_terrace_rooftop, has_terrace_rooftop),
      has_hammocks_loungers = COALESCE(p_has_hammocks_loungers, has_hammocks_loungers),
      has_bbq_area = COALESCE(p_has_bbq_area, has_bbq_area),
      has_beach_access = COALESCE(p_has_beach_access, has_beach_access),
      has_panoramic_views = COALESCE(p_has_panoramic_views, has_panoramic_views),
      has_outdoor_lounge = COALESCE(p_has_outdoor_lounge, has_outdoor_lounge),
      offers_airport_transfers = COALESCE(p_offers_airport_transfers, offers_airport_transfers),
      offers_tours_activities = COALESCE(p_offers_tours_activities, offers_tours_activities),
      has_breakfast_available = COALESCE(p_has_breakfast_available, has_breakfast_available),
      offers_bike_rental = COALESCE(p_offers_bike_rental, offers_bike_rental),
      has_luggage_storage = COALESCE(p_has_luggage_storage, has_luggage_storage),
      is_pet_friendly = COALESCE(p_is_pet_friendly, is_pet_friendly),
      has_accessible_spaces = COALESCE(p_has_accessible_spaces, has_accessible_spaces),
      is_active = COALESCE(p_amenities_is_active, is_active),
      updated_at = v_now,
      created_by = COALESCE(created_by, p_actor_user_id)
    WHERE id_property = v_id_property;
  END IF;

  SELECT
    p.id_property,
    p.code AS property_code,
    p.name AS property_name,
    p.description,
    p.email,
    p.phone,
    p.website,
    p.address_line1,
    p.address_line2,
    p.city,
    p.state,
    p.postal_code,
    p.country,
    p.timezone,
    p.currency,
    p.check_out_time,
    p.order_index,
    p.id_owner_payment_obligation_catalog,
    p.is_active,
    p.notes
  FROM property p
  WHERE p.id_property = v_id_property
    AND p.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
