DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_get_company_properties` $$
CREATE PROCEDURE `sp_get_company_properties` (
    IN p_company_code VARCHAR(100)
)
BEGIN
    -- Active properties for the given company
    SELECT
        p.id_property,
        p.code AS property_code,
        p.name AS property_name,
        p.description,
        p.city,
        p.state,
        p.country,
        p.currency
    FROM property AS p
    INNER JOIN company AS c ON c.id_company = p.id_company
    WHERE c.code = p_company_code
      AND c.deleted_at IS NULL
      AND p.is_active = 1
      AND p.deleted_at IS NULL
    ORDER BY p.name;

    -- Active room categories per property
    SELECT
        rc.id_category,
        rc.id_property,
        p.code AS property_code,
        rc.code AS category_code,
        rc.name AS category_name,
        rc.description,
        rc.max_occupancy,
        rc.default_floor_cents,
        rc.default_ceil_cents,
        rc.image_url
    FROM roomcategory AS rc
    INNER JOIN property AS p ON p.id_property = rc.id_property
    INNER JOIN company AS c ON c.id_company = p.id_company
    WHERE c.code = p_company_code
      AND c.deleted_at IS NULL
      AND rc.is_active = 1
      AND rc.deleted_at IS NULL
    ORDER BY rc.id_property, rc.name;
END $$

DELIMITER ;
