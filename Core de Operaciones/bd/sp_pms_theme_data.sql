DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_pms_theme_data` $$
CREATE PROCEDURE `sp_pms_theme_data` (
  IN p_company_code VARCHAR(100)
)
BEGIN
  DECLARE v_company_id BIGINT;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT
    v_company_id AS id_company,
    COALESCE(pct.theme_code, 'default') AS theme_code
  FROM pms_company_theme pct
  WHERE pct.id_company = v_company_id
  LIMIT 1;
END $$

DELIMITER ;
