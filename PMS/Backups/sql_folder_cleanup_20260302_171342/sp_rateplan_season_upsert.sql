DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_rateplan_season_upsert` $$
CREATE PROCEDURE `sp_rateplan_season_upsert` (
  IN p_property_code VARCHAR(100),
  IN p_rateplan_code VARCHAR(100),
  IN p_id_rateplan_season BIGINT,
  IN p_season_name VARCHAR(100),
  IN p_start_date DATE,
  IN p_end_date DATE,
  IN p_adjust_pct DECIMAL(6,2),
  IN p_priority INT,
  IN p_is_active TINYINT
)
proc:BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_id_season BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_rateplan_code IS NULL OR p_rateplan_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rateplan code is required';
  END IF;
  IF p_season_name IS NULL OR p_season_name = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Season name is required';
  END IF;
  IF p_start_date IS NULL OR p_end_date IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Season date range is required';
  END IF;
  IF p_start_date > p_end_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Season start date must be before end date';
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

  SET v_id_season = NULL;
  IF p_id_rateplan_season IS NOT NULL AND p_id_rateplan_season > 0 THEN
    SELECT id_rateplan_season
      INTO v_id_season
    FROM rateplan_season
    WHERE id_rateplan_season = p_id_rateplan_season
      AND id_rateplan = v_id_rateplan
    LIMIT 1;
  END IF;

  IF v_id_season IS NULL THEN
    INSERT INTO rateplan_season (
      id_rateplan,
      season_name,
      start_date,
      end_date,
      adjust_pct,
      priority,
      is_active,
      created_at,
      updated_at
    ) VALUES (
      v_id_rateplan,
      p_season_name,
      p_start_date,
      p_end_date,
      COALESCE(p_adjust_pct, 0),
      COALESCE(p_priority, 0),
      COALESCE(p_is_active, 1),
      v_now,
      v_now
    );
    SET v_id_season = LAST_INSERT_ID();
  ELSE
    UPDATE rateplan_season
    SET
      season_name = p_season_name,
      start_date = p_start_date,
      end_date = p_end_date,
      adjust_pct = COALESCE(p_adjust_pct, adjust_pct),
      priority = COALESCE(p_priority, priority),
      is_active = COALESCE(p_is_active, is_active),
      updated_at = v_now
    WHERE id_rateplan_season = v_id_season;
  END IF;

  SELECT
    rs.id_rateplan_season,
    rs.id_rateplan,
    rs.season_name,
    rs.start_date,
    rs.end_date,
    rs.adjust_pct,
    rs.priority,
    rs.is_active
  FROM rateplan_season rs
  WHERE rs.id_rateplan_season = v_id_season
  LIMIT 1;
END $$

DELIMITER ;
