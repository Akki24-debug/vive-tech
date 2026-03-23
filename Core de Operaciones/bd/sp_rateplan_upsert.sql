DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_rateplan_upsert` $$
CREATE PROCEDURE `sp_rateplan_upsert` (
  IN p_property_code VARCHAR(100),
  IN p_rateplan_code VARCHAR(100),
  IN p_name          VARCHAR(255),
  IN p_description   TEXT,
  IN p_currency      VARCHAR(10),
  IN p_refundable    TINYINT,
  IN p_min_stay      INT,
  IN p_max_stay      INT,
  IN p_effective_from DATE,
  IN p_effective_to   DATE,
  IN p_is_active     TINYINT,
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_id_property BIGINT;
  DECLARE v_id_rateplan BIGINT;
  DECLARE v_now DATETIME DEFAULT NOW();

  IF p_property_code IS NULL OR p_property_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Property code is required';
  END IF;
  IF p_rateplan_code IS NULL OR p_rateplan_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rate plan code is required';
  END IF;
  IF p_name IS NULL OR p_name = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rate plan name is required';
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
    INSERT INTO rateplan (
      id_property,
      code,
      name,
      description,
      currency,
      refundable,
      min_stay_default,
      max_stay_default,
      effective_from,
      effective_to,
      is_active,
      created_at,
      updated_at,
      created_by
    ) VALUES (
      v_id_property,
      p_rateplan_code,
      p_name,
      NULLIF(p_description, ''),
      COALESCE(NULLIF(p_currency, ''), 'MXN'),
      COALESCE(p_refundable, 1),
      p_min_stay,
      p_max_stay,
      COALESCE(p_effective_from, CURDATE()),
      p_effective_to,
      COALESCE(p_is_active, 1),
      v_now,
      v_now,
      p_actor_user_id
    );
    SET v_id_rateplan = LAST_INSERT_ID();
  ELSE
    UPDATE rateplan
    SET
      name = p_name,
      description = NULLIF(p_description, ''),
      currency = COALESCE(NULLIF(p_currency, ''), currency),
      refundable = COALESCE(p_refundable, refundable),
      min_stay_default = p_min_stay,
      max_stay_default = p_max_stay,
      effective_from = COALESCE(p_effective_from, effective_from),
      effective_to = p_effective_to,
      is_active = COALESCE(p_is_active, is_active),
      updated_at = v_now
    WHERE id_rateplan = v_id_rateplan;
  END IF;

  SELECT
    rp.id_rateplan,
    rp.id_property,
    rp.code AS rateplan_code,
    rp.name AS rateplan_name,
    rp.description,
    rp.currency,
    rp.refundable,
    rp.min_stay_default,
    rp.max_stay_default,
    rp.effective_from,
    rp.effective_to,
    rp.is_active
  FROM rateplan rp
  WHERE rp.id_rateplan = v_id_rateplan
    AND rp.deleted_at IS NULL
  LIMIT 1;
END $$

DELIMITER ;
