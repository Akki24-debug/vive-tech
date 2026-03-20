DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_portal_app_user_data` $$
CREATE PROCEDURE `sp_portal_app_user_data` (
  IN p_company_code   VARCHAR(100),
  IN p_search         VARCHAR(255),
  IN p_property_code  VARCHAR(100),
  IN p_only_active    TINYINT,
  IN p_user_id        BIGINT
)
BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_search VARCHAR(255);

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company
    INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;

  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  SET v_search = NULLIF(TRIM(p_search), '');

  /* Result set 1: list of users with aggregates */
  SELECT
    au.id_user,
    au.email,
    au.names,
    au.last_name,
    au.display_name,
    au.phone,
    au.is_owner,
    au.is_active,
    au.last_login_at,
    COUNT(DISTINCT up.id_property) AS property_count,
    GROUP_CONCAT(DISTINCT pr.code ORDER BY pr.code SEPARATOR ', ') AS property_codes,
    GROUP_CONCAT(DISTINCT rl.name ORDER BY rl.name SEPARATOR ', ') AS role_names
  FROM app_user au
  LEFT JOIN user_property up
    ON up.id_user = au.id_user
   AND up.deleted_at IS NULL
  LEFT JOIN property pr
    ON pr.id_property = up.id_property
  LEFT JOIN user_role ur
    ON ur.id_user = au.id_user
   AND ur.deleted_at IS NULL
  LEFT JOIN role rl
    ON rl.id_role = ur.id_role
  WHERE au.id_company = v_company_id
    AND au.deleted_at IS NULL
    AND (p_only_active IS NULL OR p_only_active = 0 OR au.is_active = 1)
    AND (
      v_search IS NULL OR
      au.email LIKE CONCAT('%', v_search, '%') OR
      au.names LIKE CONCAT('%', v_search, '%') OR
      au.last_name LIKE CONCAT('%', v_search, '%') OR
      au.display_name LIKE CONCAT('%', v_search, '%') OR
      au.full_name LIKE CONCAT('%', v_search, '%')
    )
    AND (
      p_property_code IS NULL OR p_property_code = '' OR EXISTS (
        SELECT 1
        FROM user_property up2
        JOIN property pr2 ON pr2.id_property = up2.id_property
        WHERE up2.id_user = au.id_user
          AND up2.deleted_at IS NULL
          AND pr2.code = p_property_code
      )
    )
  GROUP BY
    au.id_user,
    au.email,
    au.names,
    au.last_name,
    au.display_name,
    au.phone,
    au.is_owner,
    au.is_active,
    au.last_login_at
  ORDER BY au.names, au.email;

  /* Result set 2: selected user detail */
  IF p_user_id IS NULL OR p_user_id = 0 THEN
    SELECT
      CAST(NULL AS SIGNED) AS id_user,
      CAST(NULL AS CHAR) AS email,
      CAST(NULL AS CHAR) AS names,
      CAST(NULL AS CHAR) AS last_name,
      CAST(NULL AS CHAR) AS maiden_name,
      CAST(NULL AS CHAR) AS display_name,
      CAST(NULL AS CHAR) AS phone,
      CAST(NULL AS CHAR) AS locale,
      CAST(NULL AS CHAR) AS timezone,
      CAST(NULL AS SIGNED) AS is_owner,
      CAST(NULL AS SIGNED) AS is_active,
      CAST(NULL AS CHAR) AS notes
    LIMIT 0;
  ELSE
    SELECT
      au.id_user,
      au.email,
      au.names,
      au.last_name,
      au.maiden_name,
      au.display_name,
      au.phone,
      au.locale,
      au.timezone,
      au.is_owner,
      au.is_active,
      au.notes
    FROM app_user au
    WHERE au.id_user = p_user_id
      AND au.id_company = v_company_id
      AND au.deleted_at IS NULL
    LIMIT 1;
  END IF;

  /* Result set 3: properties of the company with assignment flag */
  SELECT
    pr.id_property,
    pr.code        AS property_code,
    pr.name        AS property_name,
    IF(up.id_user IS NULL, 0, 1) AS is_assigned
  FROM property pr
  LEFT JOIN user_property up
    ON up.id_property = pr.id_property
   AND up.deleted_at IS NULL
   AND up.id_user = p_user_id
  WHERE pr.id_company = v_company_id
    AND pr.deleted_at IS NULL
    AND pr.is_active = 1
  ORDER BY pr.name;

  /* Result set 4: roles grouped by property with assignment flag */
  SELECT
    rl.id_role,
    rl.name,
    rl.description,
    pr.code AS property_code,
    pr.name AS property_name,
    IF(ur.id_user_role IS NULL, 0, 1) AS is_assigned
  FROM role rl
  JOIN property pr ON pr.id_property = rl.id_property
  LEFT JOIN user_role ur
    ON ur.id_role = rl.id_role
   AND ur.deleted_at IS NULL
   AND ur.id_user = p_user_id
  WHERE pr.id_company = v_company_id
    AND pr.deleted_at IS NULL
    AND rl.deleted_at IS NULL
    AND rl.is_active = 1
  ORDER BY pr.name, rl.name;
END $$

DELIMITER ;
