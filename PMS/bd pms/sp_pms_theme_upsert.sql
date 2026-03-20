DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_pms_theme_upsert` $$
CREATE PROCEDURE `sp_pms_theme_upsert` (
  IN p_company_code VARCHAR(100),
  IN p_theme_code VARCHAR(32),
  IN p_actor_user_id BIGINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_theme_code VARCHAR(32);

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

  SET v_theme_code = LOWER(TRIM(COALESCE(p_theme_code, 'default')));
  IF v_theme_code NOT IN ('default','ocean') THEN
    SET v_theme_code = 'default';
  END IF;

  INSERT INTO pms_company_theme (
    id_company,
    theme_code,
    created_at,
    updated_at
  ) VALUES (
    v_company_id,
    v_theme_code,
    NOW(),
    NOW()
  )
  ON DUPLICATE KEY UPDATE
    theme_code = VALUES(theme_code),
    updated_at = NOW();

  SELECT v_company_id AS id_company, v_theme_code AS theme_code;
END $$

DELIMITER ;
