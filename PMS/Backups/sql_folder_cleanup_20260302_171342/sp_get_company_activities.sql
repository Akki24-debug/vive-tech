DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_get_company_activities` $$
CREATE PROCEDURE `sp_get_company_activities` (
    IN p_company_code VARCHAR(100)
)
BEGIN
    SELECT
        a.id_activity,
        a.code             AS activity_code,
        a.name             AS activity_name,
        a.type             AS activity_type,
        a.description,
        a.duration_minutes,
        a.base_price_cents,
        a.currency,
        a.capacity_default,
        a.location,
        a.is_active,
        a.updated_at,
        a.id_sale_item_catalog,
        sic.item_name      AS sale_item_name,
        sc.category_name   AS sale_item_category,
        p.id_property,
        p.code             AS property_code,
        p.name             AS property_name
    FROM activity AS a
    INNER JOIN company AS c ON c.id_company = a.id_company
    LEFT JOIN property AS p ON p.id_property = a.id_property
    LEFT JOIN line_item_catalog sic
      ON sic.id_line_item_catalog = a.id_sale_item_catalog
     AND sic.catalog_type = 'sale_item'
    LEFT JOIN sale_item_category sc ON sc.id_sale_item_category = sic.id_category
      AND sc.id_company = a.id_company
      AND sc.deleted_at IS NULL
    WHERE c.code = p_company_code
      AND c.deleted_at IS NULL
      AND a.deleted_at IS NULL
      AND a.is_active = 1
    ORDER BY a.type, a.name;
END $$

DELIMITER ;
