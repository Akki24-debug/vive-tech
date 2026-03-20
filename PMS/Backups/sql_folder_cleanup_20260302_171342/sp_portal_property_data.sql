DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_portal_property_data` $$
CREATE PROCEDURE `sp_portal_property_data` (
  IN p_company_code VARCHAR(100),
  IN p_search       VARCHAR(255),
  IN p_only_active  TINYINT,
  IN p_property_code VARCHAR(100)
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_property_id BIGINT;
  DECLARE v_search VARCHAR(255);
  DECLARE v_has_category_calendar_display TINYINT DEFAULT 0;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  IF p_property_code IS NOT NULL AND p_property_code <> '' THEN
    SELECT id_property
      INTO v_property_id
    FROM property
    WHERE code = p_property_code
      AND id_company = v_company_id
      AND deleted_at IS NULL
    LIMIT 1;
  ELSE
    SET v_property_id = NULL;
  END IF;

  SET v_search = NULLIF(TRIM(p_search), '');
  SELECT CASE WHEN EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'category_calendar_amenity_display'
  ) THEN 1 ELSE 0 END
    INTO v_has_category_calendar_display;

  /* Result set 1: property list with aggregates */
  SELECT
    p.id_property,
    p.code AS property_code,
    p.name AS property_name,
    p.city,
    p.state,
    p.country,
    p.currency,
    p.check_out_time,
    p.order_index AS property_order_index,
    p.is_active,
    COUNT(DISTINCT rm.id_room) AS room_count,
    COUNT(DISTINCT rc.id_category) AS category_count,
    COUNT(DISTINCT rp.id_rateplan) AS rateplan_count
  FROM property p
  LEFT JOIN room rm
    ON rm.id_property = p.id_property
   AND rm.deleted_at IS NULL
  LEFT JOIN roomcategory rc
    ON rc.id_property = p.id_property
   AND rc.deleted_at IS NULL
  LEFT JOIN rateplan rp
    ON rp.id_property = p.id_property
   AND rp.deleted_at IS NULL
  WHERE p.id_company = v_company_id
    AND p.deleted_at IS NULL
    AND (p_only_active IS NULL OR p_only_active = 0 OR p.is_active = 1)
    AND (
      v_search IS NULL OR
      p.code LIKE CONCAT('%', v_search, '%') OR
      p.name LIKE CONCAT('%', v_search, '%') OR
      p.city LIKE CONCAT('%', v_search, '%') OR
      p.state LIKE CONCAT('%', v_search, '%')
    )
  GROUP BY
    p.id_property,
    p.code,
    p.name,
    p.city,
    p.state,
    p.country,
    p.currency,
    p.order_index,
    p.is_active
  ORDER BY p.order_index, p.name;

  /* Result set 2: property detail */
  IF v_property_id IS NULL THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_property,
      CAST(NULL AS CHAR)   AS property_code,
      CAST(NULL AS CHAR)   AS property_name,
      CAST(NULL AS CHAR)   AS description,
      CAST(NULL AS CHAR)   AS email,
      CAST(NULL AS CHAR)   AS phone,
      CAST(NULL AS CHAR)   AS website,
      CAST(NULL AS CHAR)   AS address_line1,
      CAST(NULL AS CHAR)   AS address_line2,
      CAST(NULL AS CHAR)   AS city,
      CAST(NULL AS CHAR)   AS state,
      CAST(NULL AS CHAR)   AS postal_code,
      CAST(NULL AS CHAR)   AS country,
      CAST(NULL AS CHAR)   AS timezone,
      CAST(NULL AS CHAR)   AS currency,
      CAST(NULL AS DATETIME) AS check_out_time,
      CAST(NULL AS SIGNED) AS order_index,
      CAST(NULL AS SIGNED) AS id_owner_payment_obligation_catalog,
      CAST(NULL AS CHAR)   AS owner_payment_obligation_catalog_name,
      CAST(NULL AS SIGNED) AS is_active,
      CAST(NULL AS CHAR)   AS notes,
      CAST(NULL AS SIGNED) AS has_wifi,
      CAST(NULL AS SIGNED) AS has_parking,
      CAST(NULL AS SIGNED) AS has_shared_kitchen,
      CAST(NULL AS SIGNED) AS has_dining_area,
      CAST(NULL AS SIGNED) AS has_cleaning_service,
      CAST(NULL AS SIGNED) AS has_shared_laundry,
      CAST(NULL AS SIGNED) AS has_purified_water,
      CAST(NULL AS SIGNED) AS has_security_24h,
      CAST(NULL AS SIGNED) AS has_self_checkin,
      CAST(NULL AS SIGNED) AS has_pool,
      CAST(NULL AS SIGNED) AS has_jacuzzi,
      CAST(NULL AS SIGNED) AS has_garden_patio,
      CAST(NULL AS SIGNED) AS has_terrace_rooftop,
      CAST(NULL AS SIGNED) AS has_hammocks_loungers,
      CAST(NULL AS SIGNED) AS has_bbq_area,
      CAST(NULL AS SIGNED) AS has_beach_access,
      CAST(NULL AS SIGNED) AS has_panoramic_views,
      CAST(NULL AS SIGNED) AS has_outdoor_lounge,
      CAST(NULL AS SIGNED) AS offers_airport_transfers,
      CAST(NULL AS SIGNED) AS offers_tours_activities,
      CAST(NULL AS SIGNED) AS has_breakfast_available,
      CAST(NULL AS SIGNED) AS offers_bike_rental,
      CAST(NULL AS SIGNED) AS has_luggage_storage,
      CAST(NULL AS SIGNED) AS is_pet_friendly,
      CAST(NULL AS SIGNED) AS has_accessible_spaces,
      CAST(NULL AS SIGNED) AS amenities_is_active
    LIMIT 0;
  ELSE
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
      COALESCE(owner_lic.item_name, '') AS owner_payment_obligation_catalog_name,
      p.is_active,
      p.notes,
      COALESCE(pa.has_wifi, 0) AS has_wifi,
      COALESCE(pa.has_parking, 0) AS has_parking,
      COALESCE(pa.has_shared_kitchen, 0) AS has_shared_kitchen,
      COALESCE(pa.has_dining_area, 0) AS has_dining_area,
      COALESCE(pa.has_cleaning_service, 0) AS has_cleaning_service,
      COALESCE(pa.has_shared_laundry, 0) AS has_shared_laundry,
      COALESCE(pa.has_purified_water, 0) AS has_purified_water,
      COALESCE(pa.has_security_24h, 0) AS has_security_24h,
      COALESCE(pa.has_self_checkin, 0) AS has_self_checkin,
      COALESCE(pa.has_pool, 0) AS has_pool,
      COALESCE(pa.has_jacuzzi, 0) AS has_jacuzzi,
      COALESCE(pa.has_garden_patio, 0) AS has_garden_patio,
      COALESCE(pa.has_terrace_rooftop, 0) AS has_terrace_rooftop,
      COALESCE(pa.has_hammocks_loungers, 0) AS has_hammocks_loungers,
      COALESCE(pa.has_bbq_area, 0) AS has_bbq_area,
      COALESCE(pa.has_beach_access, 0) AS has_beach_access,
      COALESCE(pa.has_panoramic_views, 0) AS has_panoramic_views,
      COALESCE(pa.has_outdoor_lounge, 0) AS has_outdoor_lounge,
      COALESCE(pa.offers_airport_transfers, 0) AS offers_airport_transfers,
      COALESCE(pa.offers_tours_activities, 0) AS offers_tours_activities,
      COALESCE(pa.has_breakfast_available, 0) AS has_breakfast_available,
      COALESCE(pa.offers_bike_rental, 0) AS offers_bike_rental,
      COALESCE(pa.has_luggage_storage, 0) AS has_luggage_storage,
      COALESCE(pa.is_pet_friendly, 0) AS is_pet_friendly,
      COALESCE(pa.has_accessible_spaces, 0) AS has_accessible_spaces,
      COALESCE(pa.is_active, 1) AS amenities_is_active
    FROM property p
    LEFT JOIN property_amenities pa
      ON pa.id_property = p.id_property
     AND pa.deleted_at IS NULL
     AND pa.is_active = 1
    LEFT JOIN line_item_catalog owner_lic
      ON owner_lic.id_line_item_catalog = p.id_owner_payment_obligation_catalog
     AND owner_lic.deleted_at IS NULL
    WHERE p.id_property = v_property_id
      AND p.id_company = v_company_id
      AND p.deleted_at IS NULL
    LIMIT 1;
  END IF;

  /* Result set 3: rate plans for selected property */
  SELECT
    rp.id_rateplan,
    rp.code AS rateplan_code,
    rp.name AS rateplan_name,
    rp.currency,
    rp.refundable,
    rp.min_stay_default,
    rp.max_stay_default,
    rp.effective_from,
    rp.effective_to,
    rp.is_active
  FROM rateplan rp
  WHERE rp.id_property = v_property_id
    AND rp.deleted_at IS NULL
  ORDER BY rp.name
  LIMIT 500;

  /* Result set 4: room categories */
  IF v_has_category_calendar_display = 1 THEN
    SELECT
      rc.id_category,
      rc.code AS category_code,
      rc.name AS category_name,
      rc.description,
      rc.base_occupancy,
      rc.max_occupancy,
      rc.order_index,
      rc.default_base_price_cents,
      rc.min_price_cents,
      rc.image_url,
      rc.color_hex,
      rc.is_active,
      rp.code AS rateplan_code,
      rp.name AS rateplan_name,
      COALESCE(ca.has_air_conditioning,0) AS has_air_conditioning,
      COALESCE(ca.has_fan,0) AS has_fan,
      COALESCE(ca.has_tv,0) AS has_tv,
      COALESCE(ca.has_private_wifi,0) AS has_private_wifi,
      COALESCE(ca.has_minibar,0) AS has_minibar,
      COALESCE(ca.has_safe_box,0) AS has_safe_box,
      COALESCE(ca.has_workspace,0) AS has_workspace,
      COALESCE(ca.includes_bedding_towels,0) AS includes_bedding_towels,
      COALESCE(ca.has_iron_board,0) AS has_iron_board,
      COALESCE(ca.has_closet_rack,0) AS has_closet_rack,
      COALESCE(ca.has_private_balcony_terrace,0) AS has_private_balcony_terrace,
      COALESCE(ca.has_view,0) AS has_view,
      COALESCE(ca.has_private_entrance,0) AS has_private_entrance,
      COALESCE(ca.has_hot_water,0) AS has_hot_water,
      COALESCE(ca.includes_toiletries,0) AS includes_toiletries,
      COALESCE(ca.has_hairdryer,0) AS has_hairdryer,
      COALESCE(ca.includes_clean_towels,0) AS includes_clean_towels,
      COALESCE(ca.has_coffee_tea_kettle,0) AS has_coffee_tea_kettle,
      COALESCE(ca.has_basic_utensils,0) AS has_basic_utensils,
      COALESCE(ca.has_basic_food_items,0) AS has_basic_food_items,
      COALESCE(ca.is_private,0) AS is_private,
      COALESCE(ca.is_shared,0) AS is_shared,
      COALESCE(ca.has_shared_bathroom,0) AS has_shared_bathroom,
      COALESCE(ca.has_private_bathroom,0) AS has_private_bathroom,
      COALESCE(ca.is_active,1) AS amenities_is_active,
      COALESCE(cad.calendar_amenities_csv, '') AS calendar_amenities_csv
    FROM roomcategory rc
    LEFT JOIN rateplan rp ON rp.id_rateplan = rc.id_rateplan
    LEFT JOIN category_amenities ca ON ca.id_category = rc.id_category AND ca.deleted_at IS NULL
    LEFT JOIN (
      SELECT
        t.id_category,
        GROUP_CONCAT(t.amenity_key ORDER BY t.display_order, t.id_category_calendar_amenity_display SEPARATOR ',') AS calendar_amenities_csv
      FROM category_calendar_amenity_display t
      WHERE t.is_active = 1
      GROUP BY t.id_category
    ) cad ON cad.id_category = rc.id_category
    WHERE rc.id_property = v_property_id
      AND rc.deleted_at IS NULL
    ORDER BY rc.order_index, rc.name
    LIMIT 500;
  ELSE
    SELECT
      rc.id_category,
      rc.code AS category_code,
      rc.name AS category_name,
      rc.description,
      rc.base_occupancy,
      rc.max_occupancy,
      rc.order_index,
      rc.default_base_price_cents,
      rc.min_price_cents,
      rc.image_url,
      rc.color_hex,
      rc.is_active,
      rp.code AS rateplan_code,
      rp.name AS rateplan_name,
      COALESCE(ca.has_air_conditioning,0) AS has_air_conditioning,
      COALESCE(ca.has_fan,0) AS has_fan,
      COALESCE(ca.has_tv,0) AS has_tv,
      COALESCE(ca.has_private_wifi,0) AS has_private_wifi,
      COALESCE(ca.has_minibar,0) AS has_minibar,
      COALESCE(ca.has_safe_box,0) AS has_safe_box,
      COALESCE(ca.has_workspace,0) AS has_workspace,
      COALESCE(ca.includes_bedding_towels,0) AS includes_bedding_towels,
      COALESCE(ca.has_iron_board,0) AS has_iron_board,
      COALESCE(ca.has_closet_rack,0) AS has_closet_rack,
      COALESCE(ca.has_private_balcony_terrace,0) AS has_private_balcony_terrace,
      COALESCE(ca.has_view,0) AS has_view,
      COALESCE(ca.has_private_entrance,0) AS has_private_entrance,
      COALESCE(ca.has_hot_water,0) AS has_hot_water,
      COALESCE(ca.includes_toiletries,0) AS includes_toiletries,
      COALESCE(ca.has_hairdryer,0) AS has_hairdryer,
      COALESCE(ca.includes_clean_towels,0) AS includes_clean_towels,
      COALESCE(ca.has_coffee_tea_kettle,0) AS has_coffee_tea_kettle,
      COALESCE(ca.has_basic_utensils,0) AS has_basic_utensils,
      COALESCE(ca.has_basic_food_items,0) AS has_basic_food_items,
      COALESCE(ca.is_private,0) AS is_private,
      COALESCE(ca.is_shared,0) AS is_shared,
      COALESCE(ca.has_shared_bathroom,0) AS has_shared_bathroom,
      COALESCE(ca.has_private_bathroom,0) AS has_private_bathroom,
      COALESCE(ca.is_active,1) AS amenities_is_active,
      '' AS calendar_amenities_csv
    FROM roomcategory rc
    LEFT JOIN rateplan rp ON rp.id_rateplan = rc.id_rateplan
    LEFT JOIN category_amenities ca ON ca.id_category = rc.id_category AND ca.deleted_at IS NULL
    WHERE rc.id_property = v_property_id
      AND rc.deleted_at IS NULL
    ORDER BY rc.order_index, rc.name
    LIMIT 500;
  END IF;

  /* Result set 5: rooms */
  SELECT
    rm.id_room,
    rm.code AS room_code,
    rm.name AS room_name,
    rm.status,
    rm.housekeeping_status,
    rm.capacity_total,
    rm.max_adults,
    rm.max_children,
    rm.floor,
    rm.building,
    rm.order_index,
    rm.is_active,
    rc.code AS category_code,
    rc.name AS category_name,
    rp.code AS rateplan_code,
    rp.name AS rateplan_name
  FROM room rm
  LEFT JOIN roomcategory rc ON rc.id_category = rm.id_category
  LEFT JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan
  WHERE rm.id_property = v_property_id
    AND rm.deleted_at IS NULL
  ORDER BY rm.order_index, rm.code
  LIMIT 1000;

  /* Result set 6: bed configurations for categories */
  SELECT
    bc.id_bed_config,
    bc.id_category,
    bc.bed_type,
    bc.bed_count,
    bc.is_active
  FROM category_bed_config bc
  JOIN roomcategory rc ON rc.id_category = bc.id_category
  WHERE rc.id_property = v_property_id
    AND bc.deleted_at IS NULL
  ORDER BY rc.code, bc.id_bed_config;
END $$

DELIMITER ;
