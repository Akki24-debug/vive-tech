DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_rateplan_pricing_upsert` $$
CREATE PROCEDURE `sp_rateplan_pricing_upsert` (
  IN p_property_code VARCHAR(100),
  IN p_rateplan_code VARCHAR(100),
  IN p_base_adjust_pct DECIMAL(6,2),
  IN p_use_season TINYINT,
  IN p_use_occupancy TINYINT,
  IN p_occupancy_low_threshold DECIMAL(6,2),
  IN p_occupancy_mid_low_threshold DECIMAL(6,2),
  IN p_occupancy_mid_high_threshold DECIMAL(6,2),
  IN p_occupancy_high_threshold DECIMAL(6,2),
  IN p_low_occupancy_adjust_pct DECIMAL(6,2),
  IN p_mid_low_occupancy_adjust_pct DECIMAL(6,2),
  IN p_mid_high_occupancy_adjust_pct DECIMAL(6,2),
  IN p_high_occupancy_adjust_pct DECIMAL(6,2),
  IN p_weekend_adjust_pct DECIMAL(6,2),
  IN p_max_discount_pct DECIMAL(6,2),
  IN p_max_markup_pct DECIMAL(6,2),
  IN p_is_active TINYINT
)
proc:BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_id_pricing BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_rateplan_code IS NULL OR p_rateplan_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rateplan code is required';
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

  SELECT id_rateplan
    INTO v_id_rateplan
  FROM rateplan
  WHERE id_property = v_id_property
    AND code = p_rateplan_code
    AND deleted_at IS NULL
  LIMIT 1;

  IF v_id_rateplan IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown rateplan code for property';
  END IF;

  SELECT id_rateplan_pricing
    INTO v_id_pricing
  FROM rateplan_pricing
  WHERE id_rateplan = v_id_rateplan
  LIMIT 1;

  IF v_id_pricing IS NULL THEN
    INSERT INTO rateplan_pricing (
      id_rateplan,
      base_adjust_pct,
      use_season,
      use_occupancy,
      occupancy_low_threshold,
      occupancy_mid_low_threshold,
      occupancy_mid_high_threshold,
      occupancy_high_threshold,
      low_occupancy_adjust_pct,
      mid_low_occupancy_adjust_pct,
      mid_high_occupancy_adjust_pct,
      high_occupancy_adjust_pct,
      weekend_adjust_pct,
      max_discount_pct,
      max_markup_pct,
      is_active,
      created_at,
      updated_at
    ) VALUES (
      v_id_rateplan,
      COALESCE(p_base_adjust_pct, 0),
      COALESCE(p_use_season, 1),
      COALESCE(p_use_occupancy, 1),
      COALESCE(p_occupancy_low_threshold, 40),
      COALESCE(p_occupancy_mid_low_threshold, 55),
      COALESCE(p_occupancy_mid_high_threshold, 70),
      COALESCE(p_occupancy_high_threshold, 80),
      COALESCE(p_low_occupancy_adjust_pct, -15),
      COALESCE(p_mid_low_occupancy_adjust_pct, -5),
      COALESCE(p_mid_high_occupancy_adjust_pct, 10),
      COALESCE(p_high_occupancy_adjust_pct, 20),
      COALESCE(p_weekend_adjust_pct, 0),
      COALESCE(p_max_discount_pct, 30),
      COALESCE(p_max_markup_pct, 40),
      COALESCE(p_is_active, 1),
      v_now,
      v_now
    );
    SET v_id_pricing = LAST_INSERT_ID();
  ELSE
    UPDATE rateplan_pricing
    SET
      base_adjust_pct = COALESCE(p_base_adjust_pct, base_adjust_pct),
      use_season = COALESCE(p_use_season, use_season),
      use_occupancy = COALESCE(p_use_occupancy, use_occupancy),
      occupancy_low_threshold = COALESCE(p_occupancy_low_threshold, occupancy_low_threshold),
      occupancy_mid_low_threshold = COALESCE(p_occupancy_mid_low_threshold, occupancy_mid_low_threshold),
      occupancy_mid_high_threshold = COALESCE(p_occupancy_mid_high_threshold, occupancy_mid_high_threshold),
      occupancy_high_threshold = COALESCE(p_occupancy_high_threshold, occupancy_high_threshold),
      low_occupancy_adjust_pct = COALESCE(p_low_occupancy_adjust_pct, low_occupancy_adjust_pct),
      mid_low_occupancy_adjust_pct = COALESCE(p_mid_low_occupancy_adjust_pct, mid_low_occupancy_adjust_pct),
      mid_high_occupancy_adjust_pct = COALESCE(p_mid_high_occupancy_adjust_pct, mid_high_occupancy_adjust_pct),
      high_occupancy_adjust_pct = COALESCE(p_high_occupancy_adjust_pct, high_occupancy_adjust_pct),
      weekend_adjust_pct = COALESCE(p_weekend_adjust_pct, weekend_adjust_pct),
      max_discount_pct = COALESCE(p_max_discount_pct, max_discount_pct),
      max_markup_pct = COALESCE(p_max_markup_pct, max_markup_pct),
      is_active = COALESCE(p_is_active, is_active),
      updated_at = v_now
    WHERE id_rateplan_pricing = v_id_pricing;
  END IF;

  SELECT
    rpp.id_rateplan_pricing,
    rpp.id_rateplan,
    rpp.base_adjust_pct,
    rpp.use_season,
    rpp.use_occupancy,
    rpp.occupancy_low_threshold,
    rpp.occupancy_mid_low_threshold,
    rpp.occupancy_mid_high_threshold,
    rpp.occupancy_high_threshold,
    rpp.low_occupancy_adjust_pct,
    rpp.mid_low_occupancy_adjust_pct,
    rpp.mid_high_occupancy_adjust_pct,
    rpp.high_occupancy_adjust_pct,
    rpp.weekend_adjust_pct,
    rpp.max_discount_pct,
    rpp.max_markup_pct,
    rpp.is_active
  FROM rateplan_pricing rpp
  WHERE rpp.id_rateplan_pricing = v_id_pricing
  LIMIT 1;
END $$

DELIMITER ;
