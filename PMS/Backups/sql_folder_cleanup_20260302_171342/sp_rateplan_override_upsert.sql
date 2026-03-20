DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_rateplan_override_upsert` $$
CREATE PROCEDURE `sp_rateplan_override_upsert` (
  IN p_property_code VARCHAR(100),
  IN p_rateplan_code VARCHAR(100),
  IN p_id_rateplan_override BIGINT,
  IN p_id_category BIGINT,
  IN p_id_room BIGINT,
  IN p_override_date DATE,
  IN p_price_cents INT,
  IN p_notes VARCHAR(255),
  IN p_is_active TINYINT
)
proc:BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_id_override BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_id_room BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_rateplan_code IS NULL OR p_rateplan_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rateplan code is required';
  END IF;
  IF p_override_date IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Override date is required';
  END IF;
  IF p_price_cents IS NULL OR p_price_cents <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Override price must be greater than zero';
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

  SET v_id_room = NULL;
  SET v_id_category = NULL;
  IF p_id_room IS NOT NULL AND p_id_room > 0 THEN
    SELECT r.id_room
      INTO v_id_room
    FROM room r
    WHERE r.id_room = p_id_room
      AND r.id_property = v_id_property
      AND r.deleted_at IS NULL
    LIMIT 1;
    IF v_id_room IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room not found for property';
    END IF;
  ELSEIF p_id_category IS NOT NULL AND p_id_category > 0 THEN
    SELECT rc.id_category
      INTO v_id_category
    FROM roomcategory rc
    WHERE rc.id_category = p_id_category
      AND rc.id_property = v_id_property
      AND rc.deleted_at IS NULL
    LIMIT 1;
    IF v_id_category IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category not found for property';
    END IF;
  END IF;

  SET v_id_override = NULL;
  IF p_id_rateplan_override IS NOT NULL AND p_id_rateplan_override > 0 THEN
    SELECT id_rateplan_override
      INTO v_id_override
    FROM rateplan_override
    WHERE id_rateplan_override = p_id_rateplan_override
      AND id_rateplan = v_id_rateplan
    LIMIT 1;
  END IF;

  IF v_id_override IS NULL THEN
    INSERT INTO rateplan_override (
      id_rateplan,
      id_category,
      id_room,
      override_date,
      price_cents,
      notes,
      is_active,
      created_at,
      updated_at
    ) VALUES (
      v_id_rateplan,
      v_id_category,
      v_id_room,
      p_override_date,
      p_price_cents,
      NULLIF(p_notes, ''),
      COALESCE(p_is_active, 1),
      v_now,
      v_now
    );
    SET v_id_override = LAST_INSERT_ID();
  ELSE
    UPDATE rateplan_override
    SET
      id_category = v_id_category,
      id_room = v_id_room,
      override_date = p_override_date,
      price_cents = p_price_cents,
      notes = NULLIF(p_notes, ''),
      is_active = COALESCE(p_is_active, is_active),
      updated_at = v_now
    WHERE id_rateplan_override = v_id_override;
  END IF;

  SELECT
    ro.id_rateplan_override,
    ro.id_rateplan,
    ro.id_category,
    ro.id_room,
    ro.override_date,
    ro.price_cents,
    ro.notes,
    ro.is_active
  FROM rateplan_override ro
  WHERE ro.id_rateplan_override = v_id_override
  LIMIT 1;
END $$

DELIMITER ;
