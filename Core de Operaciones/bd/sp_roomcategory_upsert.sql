DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_roomcategory_upsert` $$
CREATE PROCEDURE `sp_roomcategory_upsert` (
  IN p_property_code   VARCHAR(100),
  IN p_category_code   VARCHAR(100),
  IN p_name            VARCHAR(255),
  IN p_description     TEXT,
  IN p_base_occupancy  INT,
  IN p_max_occupancy   INT,
  IN p_order_index     INT,
  IN p_default_base_price_cents INT,
  IN p_min_price_cents INT,
  IN p_image_url       VARCHAR(255),
  IN p_rateplan_code   VARCHAR(100),
  IN p_color_hex       VARCHAR(16),
  IN p_has_air_conditioning TINYINT,
  IN p_has_fan TINYINT,
  IN p_has_tv TINYINT,
  IN p_has_private_wifi TINYINT,
  IN p_has_minibar TINYINT,
  IN p_has_safe_box TINYINT,
  IN p_has_workspace TINYINT,
  IN p_includes_bedding_towels TINYINT,
  IN p_has_iron_board TINYINT,
  IN p_has_closet_rack TINYINT,
  IN p_has_private_balcony_terrace TINYINT,
  IN p_has_view TINYINT,
  IN p_has_private_entrance TINYINT,
  IN p_has_hot_water TINYINT,
  IN p_includes_toiletries TINYINT,
  IN p_has_hairdryer TINYINT,
  IN p_includes_clean_towels TINYINT,
  IN p_has_coffee_tea_kettle TINYINT,
  IN p_has_basic_utensils TINYINT,
  IN p_has_basic_food_items TINYINT,
  IN p_is_private TINYINT,
  IN p_is_shared TINYINT,
  IN p_has_shared_bathroom TINYINT,
  IN p_has_private_bathroom TINYINT,
  IN p_amenities_is_active TINYINT,
  IN p_calendar_display_amenities_csv TEXT,
  IN p_is_active       TINYINT,
  IN p_actor_user_id   BIGINT,
  IN p_id_category     BIGINT
)
BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_id_category_amenities BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();
  DECLARE v_calendar_display_csv TEXT;
  DECLARE v_calendar_display_item VARCHAR(64);
  DECLARE v_calendar_display_pos INT DEFAULT 0;
  DECLARE v_calendar_display_order INT DEFAULT 0;
  DECLARE v_calendar_display_allowed TINYINT DEFAULT 0;

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_category_code IS NULL OR p_category_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category code is required';
  END IF;
  IF p_name IS NULL OR p_name = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category name is required';
  END IF;

  SELECT id_property
    INTO v_id_property
  FROM property
  WHERE code = p_property_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  IF p_rateplan_code IS NOT NULL AND p_rateplan_code <> '' THEN
    SELECT id_rateplan
      INTO v_id_rateplan
    FROM rateplan
    WHERE id_property = v_id_property
      AND code = p_rateplan_code
      AND deleted_at IS NULL
    LIMIT 1;

    IF v_id_rateplan IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown rate plan code for property';
    END IF;
  ELSE
    SET v_id_rateplan = NULL;
  END IF;

  IF p_id_category IS NOT NULL AND p_id_category > 0 THEN
    SELECT id_category
      INTO v_id_category
    FROM roomcategory
    WHERE id_category = p_id_category
      AND id_property = v_id_property
      AND deleted_at IS NULL
    LIMIT 1;
  ELSE
    SELECT id_category
      INTO v_id_category
    FROM roomcategory
    WHERE id_property = v_id_property
      AND code = p_category_code
      AND deleted_at IS NULL
    LIMIT 1;
  END IF;

  IF v_id_category IS NULL THEN
    INSERT INTO roomcategory (
      id_property,
      id_rateplan,
      code,
      name,
      description,
      base_occupancy,
      max_occupancy,
      order_index,
      default_base_price_cents,
      min_price_cents,
      image_url,
      color_hex,
      is_active,
      created_at,
      updated_at,
      created_by
    ) VALUES (
      v_id_property,
      v_id_rateplan,
      p_category_code,
      p_name,
      NULLIF(p_description, ''),
      p_base_occupancy,
      p_max_occupancy,
      COALESCE(p_order_index, 0),
      NULLIF(p_default_base_price_cents, 0),
      NULLIF(p_min_price_cents, 0),
      NULLIF(p_image_url, ''),
      NULLIF(p_color_hex, ''),
      COALESCE(p_is_active, 1),
      v_now,
      v_now,
      p_actor_user_id
    );
    SET v_id_category = LAST_INSERT_ID();
  ELSE
    UPDATE roomcategory
    SET
      code = p_category_code,
      id_rateplan = v_id_rateplan,
      name = p_name,
      description = NULLIF(p_description, ''),
      base_occupancy = p_base_occupancy,
      max_occupancy = p_max_occupancy,
      order_index = COALESCE(p_order_index, order_index),
      default_base_price_cents = NULLIF(p_default_base_price_cents, 0),
      min_price_cents = NULLIF(p_min_price_cents, 0),
      image_url = NULLIF(p_image_url, ''),
      color_hex = COALESCE(NULLIF(p_color_hex, ''), color_hex),
      is_active = COALESCE(p_is_active, is_active),
      updated_at = v_now
    WHERE id_category = v_id_category;
  END IF;

  /* Upsert category amenities */
  SELECT id_category_amenities
    INTO v_id_category_amenities
  FROM category_amenities
  WHERE id_category = v_id_category
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_category_amenities IS NULL THEN
    INSERT INTO category_amenities (
      id_category,
      has_air_conditioning,
      has_fan,
      has_tv,
      has_private_wifi,
      has_minibar,
      has_safe_box,
      has_workspace,
      includes_bedding_towels,
      has_iron_board,
      has_closet_rack,
      has_private_balcony_terrace,
      has_view,
      has_private_entrance,
      has_hot_water,
      includes_toiletries,
      has_hairdryer,
      includes_clean_towels,
      has_coffee_tea_kettle,
      has_basic_utensils,
      has_basic_food_items,
      is_private,
      is_shared,
      has_shared_bathroom,
      has_private_bathroom,
      is_active,
      created_by,
      created_at,
      updated_at
    ) VALUES (
      v_id_category,
      COALESCE(p_has_air_conditioning, 0),
      COALESCE(p_has_fan, 0),
      COALESCE(p_has_tv, 0),
      COALESCE(p_has_private_wifi, 0),
      COALESCE(p_has_minibar, 0),
      COALESCE(p_has_safe_box, 0),
      COALESCE(p_has_workspace, 0),
      COALESCE(p_includes_bedding_towels, 0),
      COALESCE(p_has_iron_board, 0),
      COALESCE(p_has_closet_rack, 0),
      COALESCE(p_has_private_balcony_terrace, 0),
      COALESCE(p_has_view, 0),
      COALESCE(p_has_private_entrance, 0),
      COALESCE(p_has_hot_water, 0),
      COALESCE(p_includes_toiletries, 0),
      COALESCE(p_has_hairdryer, 0),
      COALESCE(p_includes_clean_towels, 0),
      COALESCE(p_has_coffee_tea_kettle, 0),
      COALESCE(p_has_basic_utensils, 0),
      COALESCE(p_has_basic_food_items, 0),
      COALESCE(p_is_private, 0),
      COALESCE(p_is_shared, 0),
      COALESCE(p_has_shared_bathroom, 0),
      COALESCE(p_has_private_bathroom, 0),
      COALESCE(p_amenities_is_active, 1),
      p_actor_user_id,
      v_now,
      v_now
    );
  ELSE
    UPDATE category_amenities
    SET
      has_air_conditioning = COALESCE(p_has_air_conditioning, has_air_conditioning),
      has_fan = COALESCE(p_has_fan, has_fan),
      has_tv = COALESCE(p_has_tv, has_tv),
      has_private_wifi = COALESCE(p_has_private_wifi, has_private_wifi),
      has_minibar = COALESCE(p_has_minibar, has_minibar),
      has_safe_box = COALESCE(p_has_safe_box, has_safe_box),
      has_workspace = COALESCE(p_has_workspace, has_workspace),
      includes_bedding_towels = COALESCE(p_includes_bedding_towels, includes_bedding_towels),
      has_iron_board = COALESCE(p_has_iron_board, has_iron_board),
      has_closet_rack = COALESCE(p_has_closet_rack, has_closet_rack),
      has_private_balcony_terrace = COALESCE(p_has_private_balcony_terrace, has_private_balcony_terrace),
      has_view = COALESCE(p_has_view, has_view),
      has_private_entrance = COALESCE(p_has_private_entrance, has_private_entrance),
      has_hot_water = COALESCE(p_has_hot_water, has_hot_water),
      includes_toiletries = COALESCE(p_includes_toiletries, includes_toiletries),
      has_hairdryer = COALESCE(p_has_hairdryer, has_hairdryer),
      includes_clean_towels = COALESCE(p_includes_clean_towels, includes_clean_towels),
      has_coffee_tea_kettle = COALESCE(p_has_coffee_tea_kettle, has_coffee_tea_kettle),
      has_basic_utensils = COALESCE(p_has_basic_utensils, has_basic_utensils),
      has_basic_food_items = COALESCE(p_has_basic_food_items, has_basic_food_items),
      is_private = COALESCE(p_is_private, is_private),
      is_shared = COALESCE(p_is_shared, is_shared),
      has_shared_bathroom = COALESCE(p_has_shared_bathroom, has_shared_bathroom),
      has_private_bathroom = COALESCE(p_has_private_bathroom, has_private_bathroom),
      is_active = COALESCE(p_amenities_is_active, is_active),
      updated_at = v_now,
      created_by = COALESCE(created_by, p_actor_user_id)
    WHERE id_category = v_id_category;
  END IF;

  /* Upsert amenity icons to display in calendar (selection among checked amenities) */
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'category_calendar_amenity_display'
  ) THEN
    DELETE FROM category_calendar_amenity_display
    WHERE id_category = v_id_category;

    SET v_calendar_display_csv = TRIM(COALESCE(p_calendar_display_amenities_csv, ''));
    SET v_calendar_display_order = 0;

    calendar_display_loop: WHILE v_calendar_display_csv <> '' DO
      SET v_calendar_display_pos = LOCATE(',', v_calendar_display_csv);
      IF v_calendar_display_pos > 0 THEN
        SET v_calendar_display_item = TRIM(SUBSTRING(v_calendar_display_csv, 1, v_calendar_display_pos - 1));
        SET v_calendar_display_csv = TRIM(SUBSTRING(v_calendar_display_csv, v_calendar_display_pos + 1));
      ELSE
        SET v_calendar_display_item = TRIM(v_calendar_display_csv);
        SET v_calendar_display_csv = '';
      END IF;

      SET v_calendar_display_allowed = 0;
      CASE v_calendar_display_item
        WHEN 'has_air_conditioning' THEN SET v_calendar_display_allowed = COALESCE(p_has_air_conditioning, 0);
        WHEN 'has_fan' THEN SET v_calendar_display_allowed = COALESCE(p_has_fan, 0);
        WHEN 'has_tv' THEN SET v_calendar_display_allowed = COALESCE(p_has_tv, 0);
        WHEN 'has_private_wifi' THEN SET v_calendar_display_allowed = COALESCE(p_has_private_wifi, 0);
        WHEN 'has_minibar' THEN SET v_calendar_display_allowed = COALESCE(p_has_minibar, 0);
        WHEN 'has_safe_box' THEN SET v_calendar_display_allowed = COALESCE(p_has_safe_box, 0);
        WHEN 'has_workspace' THEN SET v_calendar_display_allowed = COALESCE(p_has_workspace, 0);
        WHEN 'includes_bedding_towels' THEN SET v_calendar_display_allowed = COALESCE(p_includes_bedding_towels, 0);
        WHEN 'has_iron_board' THEN SET v_calendar_display_allowed = COALESCE(p_has_iron_board, 0);
        WHEN 'has_closet_rack' THEN SET v_calendar_display_allowed = COALESCE(p_has_closet_rack, 0);
        WHEN 'has_private_balcony_terrace' THEN SET v_calendar_display_allowed = COALESCE(p_has_private_balcony_terrace, 0);
        WHEN 'has_view' THEN SET v_calendar_display_allowed = COALESCE(p_has_view, 0);
        WHEN 'has_private_entrance' THEN SET v_calendar_display_allowed = COALESCE(p_has_private_entrance, 0);
        WHEN 'has_hot_water' THEN SET v_calendar_display_allowed = COALESCE(p_has_hot_water, 0);
        WHEN 'includes_toiletries' THEN SET v_calendar_display_allowed = COALESCE(p_includes_toiletries, 0);
        WHEN 'has_hairdryer' THEN SET v_calendar_display_allowed = COALESCE(p_has_hairdryer, 0);
        WHEN 'includes_clean_towels' THEN SET v_calendar_display_allowed = COALESCE(p_includes_clean_towels, 0);
        WHEN 'has_coffee_tea_kettle' THEN SET v_calendar_display_allowed = COALESCE(p_has_coffee_tea_kettle, 0);
        WHEN 'has_basic_utensils' THEN SET v_calendar_display_allowed = COALESCE(p_has_basic_utensils, 0);
        WHEN 'has_basic_food_items' THEN SET v_calendar_display_allowed = COALESCE(p_has_basic_food_items, 0);
        WHEN 'is_private' THEN SET v_calendar_display_allowed = COALESCE(p_is_private, 0);
        WHEN 'is_shared' THEN SET v_calendar_display_allowed = COALESCE(p_is_shared, 0);
        WHEN 'has_shared_bathroom' THEN SET v_calendar_display_allowed = COALESCE(p_has_shared_bathroom, 0);
        WHEN 'has_private_bathroom' THEN SET v_calendar_display_allowed = COALESCE(p_has_private_bathroom, 0);
      END CASE;

      IF v_calendar_display_item IN (
        'has_air_conditioning',
        'has_fan',
        'has_tv',
        'has_private_wifi',
        'has_minibar',
        'has_safe_box',
        'has_workspace',
        'includes_bedding_towels',
        'has_iron_board',
        'has_closet_rack',
        'has_private_balcony_terrace',
        'has_view',
        'has_private_entrance',
        'has_hot_water',
        'includes_toiletries',
        'has_hairdryer',
        'includes_clean_towels',
        'has_coffee_tea_kettle',
        'has_basic_utensils',
        'has_basic_food_items',
        'is_private',
        'is_shared',
        'has_shared_bathroom',
        'has_private_bathroom'
      ) AND v_calendar_display_allowed = 1 THEN
        INSERT IGNORE INTO category_calendar_amenity_display (
          id_category,
          amenity_key,
          display_order,
          is_active,
          created_by,
          created_at,
          updated_at
        ) VALUES (
          v_id_category,
          v_calendar_display_item,
          v_calendar_display_order,
          1,
          p_actor_user_id,
          v_now,
          v_now
        );
        SET v_calendar_display_order = v_calendar_display_order + 1;
      END IF;
    END WHILE calendar_display_loop;
  END IF;

  SELECT
    rc.id_category,
    rc.id_property,
    rc.id_rateplan,
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
    rc.is_active
  FROM roomcategory rc
  WHERE rc.id_category = v_id_category
    AND rc.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
