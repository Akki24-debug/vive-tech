DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ota_ical_feed_lodging_data` $$
CREATE PROCEDURE `sp_ota_ical_feed_lodging_data` (
  IN p_company_code VARCHAR(100),
  IN p_id_ota_ical_feed BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT DEFAULT 0;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  IF p_id_ota_ical_feed IS NULL OR p_id_ota_ical_feed <= 0 THEN
    SELECT
      flc.id_ota_ical_feed,
      flc.id_line_item_catalog
    FROM ota_ical_feed_lodging_catalog flc
    JOIN ota_ical_feed f
      ON f.id_ota_ical_feed = flc.id_ota_ical_feed
     AND f.id_company = v_company_id
     AND f.deleted_at IS NULL
    WHERE flc.deleted_at IS NULL
      AND flc.is_active = 1
    ORDER BY flc.id_ota_ical_feed, flc.sort_order, flc.id_line_item_catalog;
  ELSE
    SELECT
      flc.id_ota_ical_feed,
      flc.id_line_item_catalog
    FROM ota_ical_feed_lodging_catalog flc
    JOIN ota_ical_feed f
      ON f.id_ota_ical_feed = flc.id_ota_ical_feed
     AND f.id_company = v_company_id
     AND f.deleted_at IS NULL
    WHERE flc.id_ota_ical_feed = p_id_ota_ical_feed
      AND flc.deleted_at IS NULL
      AND flc.is_active = 1
    ORDER BY flc.sort_order, flc.id_line_item_catalog;
  END IF;
END $$

DELIMITER ;
