DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_room_upsert` $$
CREATE PROCEDURE `sp_room_upsert` (
  IN p_property_code        VARCHAR(100),
  IN p_room_code            VARCHAR(100),
  IN p_category_code        VARCHAR(100),
  IN p_rateplan_code        VARCHAR(100),
  IN p_name                 VARCHAR(255),
  IN p_description          TEXT,
  IN p_capacity_total       INT,
  IN p_max_adults           INT,
  IN p_max_children         INT,
  IN p_status               VARCHAR(32),
  IN p_housekeeping_status  VARCHAR(32),
  IN p_floor                VARCHAR(64),
  IN p_building             VARCHAR(120),
  IN p_bed_config           VARCHAR(255),
  IN p_color_hex            VARCHAR(16),
  IN p_order_index          INT,
  IN p_is_active            TINYINT,
  IN p_id_room              BIGINT
)
proc:BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_room BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_update_category TINYINT DEFAULT 0;
  DECLARE v_update_rateplan TINYINT DEFAULT 0;
  DECLARE v_category_code_trim VARCHAR(100);
  DECLARE v_rateplan_code_trim VARCHAR(100);

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_room_code IS NULL OR p_room_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room code is required';
  END IF;

  SELECT id_property INTO v_id_property
  FROM property
  WHERE code = p_property_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  /* Normalize optional category parameter */
  IF p_category_code IS NOT NULL THEN
    SET v_update_category = 1;
    SET v_category_code_trim = TRIM(p_category_code);
    IF v_category_code_trim = '' THEN
      SET v_id_category = NULL;
    ELSE
      SELECT id_category INTO v_id_category
      FROM roomcategory
      WHERE id_property = v_id_property
        AND code = v_category_code_trim
        AND deleted_at IS NULL
      LIMIT 1;
      IF v_id_category IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown room category code for property';
      END IF;
    END IF;
  END IF;

  /* Normalize optional rate plan parameter */
  IF p_rateplan_code IS NOT NULL THEN
    SET v_update_rateplan = 1;
    SET v_rateplan_code_trim = TRIM(p_rateplan_code);
    IF v_rateplan_code_trim = '' THEN
      SET v_id_rateplan = NULL;
    ELSE
      SELECT id_rateplan INTO v_id_rateplan
      FROM rateplan
      WHERE id_property = v_id_property
        AND code = v_rateplan_code_trim
        AND deleted_at IS NULL
      LIMIT 1;
      IF v_id_rateplan IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown rate plan code for property';
      END IF;
    END IF;
  END IF;

  IF p_id_room IS NOT NULL AND p_id_room > 0 THEN
    SELECT id_room INTO v_id_room
    FROM room
    WHERE id_room = p_id_room
      AND id_property = v_id_property
      AND deleted_at IS NULL
    LIMIT 1;
  ELSE
    SELECT id_room INTO v_id_room
    FROM room
    WHERE id_property = v_id_property
      AND code = p_room_code
      AND deleted_at IS NULL
    LIMIT 1;
  END IF;

  IF v_id_room IS NULL THEN
    INSERT INTO room (
      id_property,
      id_category,
      id_rateplan,
      code,
      name,
      description,
      capacity_total,
      max_adults,
      max_children,
      status,
      housekeeping_status,
      floor,
      building,
      bed_config,
      color_hex,
      order_index,
      is_active,
      created_at,
      updated_at
    ) VALUES (
      v_id_property,
      CASE WHEN v_update_category = 1 THEN v_id_category ELSE NULL END,
      CASE WHEN v_update_rateplan = 1 THEN v_id_rateplan ELSE NULL END,
      p_room_code,
      p_name,
      NULLIF(p_description, ''),
      p_capacity_total,
      p_max_adults,
      p_max_children,
      COALESCE(NULLIF(p_status,''), 'vacant'),
      COALESCE(NULLIF(p_housekeeping_status,''), 'clean'),
      NULLIF(p_floor, ''),
      NULLIF(p_building, ''),
      NULLIF(p_bed_config, ''),
      NULLIF(p_color_hex, ''),
      p_order_index,
      COALESCE(p_is_active, 1),
      NOW(),
      NOW()
    );
    SET v_id_room = LAST_INSERT_ID();
  ELSE
    UPDATE room
    SET
      id_category = CASE WHEN v_update_category = 1 THEN v_id_category ELSE id_category END,
      id_rateplan = CASE WHEN v_update_rateplan = 1 THEN v_id_rateplan ELSE id_rateplan END,
      code = p_room_code,
      name = COALESCE(NULLIF(p_name,''), name),
      description = COALESCE(NULLIF(p_description,''), description),
      capacity_total = COALESCE(p_capacity_total, capacity_total),
      max_adults = COALESCE(p_max_adults, max_adults),
      max_children = COALESCE(p_max_children, max_children),
      status = COALESCE(NULLIF(p_status,''), status),
      housekeeping_status = COALESCE(NULLIF(p_housekeeping_status,''), housekeeping_status),
      floor = COALESCE(NULLIF(p_floor,''), floor),
      building = COALESCE(NULLIF(p_building,''), building),
      bed_config = COALESCE(NULLIF(p_bed_config,''), bed_config),
      color_hex = COALESCE(NULLIF(p_color_hex,''), color_hex),
      order_index = COALESCE(p_order_index, order_index),
      is_active = COALESCE(p_is_active, is_active),
      updated_at = NOW()
    WHERE id_room = v_id_room;
  END IF;

  SELECT
    r.*,
    p.code AS property_code,
    p.name AS property_name,
    rc.code AS category_code,
    rc.name AS category_name,
    rp.code AS rateplan_code,
    rp.name AS rateplan_name
  FROM room r
  JOIN property p ON p.id_property = r.id_property
  LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
  LEFT JOIN rateplan rp ON rp.id_rateplan = r.id_rateplan
  WHERE r.id_room = v_id_room;
END $$

DELIMITER ;
