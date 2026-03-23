DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sale_item_child_upsert` $$
CREATE PROCEDURE `sp_sale_item_child_upsert` (
  IN p_action VARCHAR(16),
  IN p_id_sale_item BIGINT,
  IN p_id_parent_sale_item BIGINT,
  IN p_id_folio BIGINT,
  IN p_id_reservation BIGINT,
  IN p_id_sale_item_catalog BIGINT,
  IN p_service_date DATE,
  IN p_quantity DECIMAL(18,6),
  IN p_unit_price_cents INT,
  IN p_status VARCHAR(32),
  IN p_created_by BIGINT
)
BEGIN
  /*
    Legacy compatibility wrapper.
    Parent-child linkage in line_item was removed from schema, so child upsert now
    delegates to generic line_item-based sale item upsert.
  */
  IF p_action IS NULL OR TRIM(p_action) = '' THEN
    SET p_action = 'create';
  END IF;

  IF p_action = 'delete' THEN
    CALL sp_sale_item_upsert(
      'delete',
      p_id_sale_item,
      p_id_folio,
      p_id_reservation,
      NULL,
      NULL,
      p_service_date,
      p_quantity,
      p_unit_price_cents,
      0,
      p_status,
      p_created_by
    );
  ELSEIF p_action = 'update' THEN
    CALL sp_sale_item_upsert(
      'update',
      p_id_sale_item,
      p_id_folio,
      p_id_reservation,
      p_id_sale_item_catalog,
      NULL,
      p_service_date,
      p_quantity,
      p_unit_price_cents,
      0,
      p_status,
      p_created_by
    );
  ELSE
    CALL sp_sale_item_upsert(
      'create',
      NULL,
      p_id_folio,
      p_id_reservation,
      p_id_sale_item_catalog,
      NULL,
      p_service_date,
      p_quantity,
      p_unit_price_cents,
      0,
      p_status,
      p_created_by
    );
  END IF;
END $$

DELIMITER ;
