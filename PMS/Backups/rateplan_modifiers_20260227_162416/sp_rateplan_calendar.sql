DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_rateplan_calendar` $$
CREATE PROCEDURE `sp_rateplan_calendar` (
  IN p_property_code VARCHAR(100),
  IN p_rateplan_code VARCHAR(100),
  IN p_category_code VARCHAR(100),
  IN p_room_code VARCHAR(100),
  IN p_from DATE,
  IN p_days INT
)
proc:BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_id_room BIGINT;
  DECLARE v_base_cents INT DEFAULT 0;
  DECLARE v_min_cents INT DEFAULT 0;
  DECLARE v_day_count INT DEFAULT 0;
  DECLARE v_to DATE;
  DECLARE v_index INT DEFAULT 0;
  DECLARE v_total_rooms INT DEFAULT 0;
  DECLARE v_use_season TINYINT DEFAULT 1;
  DECLARE v_use_occupancy TINYINT DEFAULT 1;
  DECLARE v_base_adjust_pct DECIMAL(6,2) DEFAULT 0;
  DECLARE v_low_threshold DECIMAL(6,2) DEFAULT 40;
  DECLARE v_mid_low_threshold DECIMAL(6,2) DEFAULT 55;
  DECLARE v_mid_high_threshold DECIMAL(6,2) DEFAULT 70;
  DECLARE v_high_threshold DECIMAL(6,2) DEFAULT 80;
  DECLARE v_low_adjust_pct DECIMAL(6,2) DEFAULT -15;
  DECLARE v_mid_low_adjust_pct DECIMAL(6,2) DEFAULT -5;
  DECLARE v_mid_high_adjust_pct DECIMAL(6,2) DEFAULT 10;
  DECLARE v_high_adjust_pct DECIMAL(6,2) DEFAULT 20;
  DECLARE v_weekend_adjust_pct DECIMAL(6,2) DEFAULT 0;
  DECLARE v_max_discount_pct DECIMAL(6,2) DEFAULT 30;
  DECLARE v_max_markup_pct DECIMAL(6,2) DEFAULT 40;

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_from IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'From date is required';
  END IF;

  SET v_day_count = CASE
    WHEN p_days IS NULL OR p_days < 1 THEN 1
    WHEN p_days > 120 THEN 120
    ELSE p_days
  END;

  SELECT id_property
    INTO v_id_property
  FROM property
  WHERE code = p_property_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_property IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown property code';
  END IF;

  SET v_id_room = NULL;
  SET v_id_category = NULL;
  SET v_id_rateplan = NULL;

  IF p_room_code IS NOT NULL AND p_room_code <> '' THEN
    SELECT r.id_room,
           r.id_category,
           COALESCE(rc.default_base_price_cents,0),
           COALESCE(NULLIF(rc.min_price_cents,0), COALESCE(rc.default_base_price_cents,0)),
           COALESCE(r.id_rateplan, rc.id_rateplan)
      INTO v_id_room, v_id_category, v_base_cents, v_min_cents, v_id_rateplan
    FROM room r
    JOIN roomcategory rc ON rc.id_category = r.id_category
    WHERE r.id_property = v_id_property
      AND r.code = p_room_code
      AND r.deleted_at IS NULL
    LIMIT 1;
  ELSEIF p_category_code IS NOT NULL AND p_category_code <> '' THEN
    SELECT rc.id_category,
           COALESCE(rc.default_base_price_cents,0),
           COALESCE(NULLIF(rc.min_price_cents,0), COALESCE(rc.default_base_price_cents,0)),
           rc.id_rateplan
      INTO v_id_category, v_base_cents, v_min_cents, v_id_rateplan
    FROM roomcategory rc
    WHERE rc.id_property = v_id_property
      AND rc.code = p_category_code
      AND rc.deleted_at IS NULL
    LIMIT 1;
  END IF;

  IF v_id_category IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category or room is required';
  END IF;

  IF p_rateplan_code IS NOT NULL AND p_rateplan_code <> '' THEN
    SELECT id_rateplan
      INTO v_id_rateplan
    FROM rateplan
    WHERE id_property = v_id_property
      AND code = p_rateplan_code
      AND deleted_at IS NULL
    LIMIT 1;
  END IF;

  IF v_id_rateplan IS NOT NULL THEN
    SELECT
      COALESCE(rpp.base_adjust_pct, 0),
      COALESCE(rpp.use_season, 1),
      COALESCE(rpp.use_occupancy, 1),
      COALESCE(rpp.occupancy_low_threshold, 40),
      COALESCE(rpp.occupancy_mid_low_threshold, 55),
      COALESCE(rpp.occupancy_mid_high_threshold, 70),
      COALESCE(rpp.occupancy_high_threshold, 80),
      COALESCE(rpp.low_occupancy_adjust_pct, -15),
      COALESCE(rpp.mid_low_occupancy_adjust_pct, -5),
      COALESCE(rpp.mid_high_occupancy_adjust_pct, 10),
      COALESCE(rpp.high_occupancy_adjust_pct, 20),
      COALESCE(rpp.weekend_adjust_pct, 0),
      COALESCE(rpp.max_discount_pct, 30),
      COALESCE(rpp.max_markup_pct, 40)
      INTO v_base_adjust_pct,
           v_use_season,
           v_use_occupancy,
           v_low_threshold,
           v_mid_low_threshold,
           v_mid_high_threshold,
           v_high_threshold,
           v_low_adjust_pct,
           v_mid_low_adjust_pct,
           v_mid_high_adjust_pct,
           v_high_adjust_pct,
           v_weekend_adjust_pct,
           v_max_discount_pct,
           v_max_markup_pct
    FROM rateplan_pricing rpp
    WHERE rpp.id_rateplan = v_id_rateplan
    LIMIT 1;
  END IF;

  SELECT COUNT(*)
    INTO v_total_rooms
  FROM room
  WHERE id_property = v_id_property
    AND is_active = 1
    AND deleted_at IS NULL;

  SET v_to = DATE_ADD(p_from, INTERVAL v_day_count DAY);

  DROP TEMPORARY TABLE IF EXISTS tmp_rateplan_days;
  CREATE TEMPORARY TABLE tmp_rateplan_days (
    day_index INT PRIMARY KEY,
    calendar_date DATE NOT NULL
  ) ENGINE = MEMORY;

  rateplan_day_loop: WHILE v_index < v_day_count DO
    INSERT INTO tmp_rateplan_days (day_index, calendar_date)
    VALUES (v_index, DATE_ADD(p_from, INTERVAL v_index DAY));
    SET v_index = v_index + 1;
  END WHILE rateplan_day_loop;

  DROP TEMPORARY TABLE IF EXISTS tmp_rateplan_occupancy;
  CREATE TEMPORARY TABLE tmp_rateplan_occupancy AS
  SELECT
    d.calendar_date,
    COUNT(DISTINCT CASE
      WHEN res.id_reservation IS NOT NULL OR rb.id_room_block IS NOT NULL THEN r.id_room
    END) AS occupied_rooms
  FROM tmp_rateplan_days d
  JOIN room r
    ON r.id_property = v_id_property
   AND r.is_active = 1
   AND r.deleted_at IS NULL
  LEFT JOIN reservation res
    ON res.id_room = r.id_room
   AND res.deleted_at IS NULL
   AND COALESCE(res.status, 'confirmed') NOT IN ('cancelled','canceled','cancelado','cancelada')
   AND res.check_in_date <= d.calendar_date
   AND res.check_out_date > d.calendar_date
  LEFT JOIN room_block rb
    ON rb.id_room = r.id_room
   AND rb.deleted_at IS NULL
   AND rb.is_active = 1
   AND rb.start_date <= d.calendar_date
   AND DATE_ADD(rb.end_date, INTERVAL 1 DAY) > d.calendar_date
  GROUP BY d.calendar_date;

  DROP TEMPORARY TABLE IF EXISTS tmp_rateplan_calc;
  CREATE TEMPORARY TABLE tmp_rateplan_calc AS
  SELECT
    d.day_index,
    d.calendar_date,
    v_base_cents AS base_cents,
    v_min_cents AS min_cents,
    v_base_adjust_pct AS base_adjust_pct,
    CASE WHEN DAYOFWEEK(d.calendar_date) IN (1,7) THEN 1 ELSE 0 END AS is_weekend,
    IF(v_total_rooms = 0, 0,
      ROUND(COALESCE(o.occupied_rooms,0) / v_total_rooms * 100, 1)
    ) AS occupancy_pct,
    CASE
      WHEN v_use_occupancy = 0 OR v_total_rooms = 0 THEN 0
      WHEN (COALESCE(o.occupied_rooms,0) / NULLIF(v_total_rooms,0) * 100) <= v_low_threshold THEN v_low_adjust_pct
      WHEN (COALESCE(o.occupied_rooms,0) / NULLIF(v_total_rooms,0) * 100) <= v_mid_low_threshold THEN v_mid_low_adjust_pct
      WHEN (COALESCE(o.occupied_rooms,0) / NULLIF(v_total_rooms,0) * 100) >= v_high_threshold THEN v_high_adjust_pct
      WHEN (COALESCE(o.occupied_rooms,0) / NULLIF(v_total_rooms,0) * 100) >= v_mid_high_threshold THEN v_mid_high_adjust_pct
      ELSE 0
    END AS occupancy_adjust_pct,
    CASE
      WHEN v_use_season = 1 AND v_id_rateplan IS NOT NULL THEN COALESCE((
        SELECT rs.adjust_pct
        FROM rateplan_season rs
        WHERE rs.id_rateplan = v_id_rateplan
          AND rs.is_active = 1
          AND d.calendar_date BETWEEN rs.start_date AND rs.end_date
        ORDER BY rs.priority DESC, rs.adjust_pct DESC
        LIMIT 1
      ), 0)
      ELSE 0
    END AS season_adjust_pct,
    CASE
      WHEN v_id_rateplan IS NULL THEN NULL
      ELSE (
        SELECT ro.price_cents
        FROM rateplan_override ro
        WHERE ro.id_rateplan = v_id_rateplan
          AND ro.is_active = 1
          AND ro.override_date = d.calendar_date
          AND (
            (v_id_room IS NOT NULL AND ro.id_room = v_id_room)
            OR (v_id_room IS NULL AND v_id_category IS NOT NULL AND ro.id_category = v_id_category)
            OR (ro.id_room IS NULL AND ro.id_category IS NULL)
          )
        ORDER BY ro.id_room IS NOT NULL DESC, ro.id_category IS NOT NULL DESC, ro.id_rateplan_override DESC
        LIMIT 1
      )
    END AS override_price_cents,
    0 AS base_adjusted_cents,
    0 AS calc_cents,
    0 AS final_price_cents
  FROM tmp_rateplan_days d
  LEFT JOIN tmp_rateplan_occupancy o ON o.calendar_date = d.calendar_date;

  UPDATE tmp_rateplan_calc
  SET
    base_adjusted_cents = ROUND(base_cents * (1 + base_adjust_pct / 100)),
    calc_cents = ROUND(base_cents * (1 + base_adjust_pct / 100))
      + ROUND(ROUND(base_cents * (1 + base_adjust_pct / 100)) * (season_adjust_pct / 100))
      + ROUND(ROUND(base_cents * (1 + base_adjust_pct / 100)) * (occupancy_adjust_pct / 100))
      + ROUND(ROUND(base_cents * (1 + base_adjust_pct / 100)) * (CASE WHEN is_weekend = 1 THEN v_weekend_adjust_pct ELSE 0 END / 100));

  UPDATE tmp_rateplan_calc
  SET
    calc_cents = CASE
      WHEN v_max_discount_pct > 0 AND v_max_markup_pct > 0 THEN LEAST(
        ROUND(base_adjusted_cents * (1 + v_max_markup_pct / 100)),
        GREATEST(
          ROUND(base_adjusted_cents * (1 - v_max_discount_pct / 100)),
          calc_cents
        )
      )
      WHEN v_max_discount_pct > 0 THEN GREATEST(
        ROUND(base_adjusted_cents * (1 - v_max_discount_pct / 100)),
        calc_cents
      )
      WHEN v_max_markup_pct > 0 THEN LEAST(
        ROUND(base_adjusted_cents * (1 + v_max_markup_pct / 100)),
        calc_cents
      )
      ELSE calc_cents
    END,
    final_price_cents = CASE
      WHEN override_price_cents IS NOT NULL THEN override_price_cents
      ELSE GREATEST(calc_cents, min_cents)
    END;

  SELECT
    day_index,
    calendar_date,
    base_cents,
    min_cents,
    base_adjust_pct,
    base_adjusted_cents,
    season_adjust_pct,
    occupancy_pct,
    occupancy_adjust_pct,
    override_price_cents,
    final_price_cents
  FROM tmp_rateplan_calc
  ORDER BY day_index;
END $$

DELIMITER ;
