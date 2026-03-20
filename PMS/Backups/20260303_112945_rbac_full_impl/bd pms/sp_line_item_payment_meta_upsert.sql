DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_line_item_payment_meta_upsert` $$
CREATE PROCEDURE `sp_line_item_payment_meta_upsert` (
  IN p_id_line_item BIGINT,
  IN p_method VARCHAR(120),
  IN p_reference VARCHAR(255),
  IN p_status VARCHAR(32),
  IN p_updated_by BIGINT
)
BEGIN
  IF p_id_line_item IS NULL OR p_id_line_item <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'line_item id is required';
  END IF;

  UPDATE line_item
     SET method = CASE
                    WHEN p_method IS NULL OR TRIM(p_method) = '' THEN method
                    ELSE p_method
                  END,
         reference = CASE
                       WHEN p_reference IS NULL THEN reference
                       ELSE p_reference
                     END,
         status = CASE
                    WHEN p_status IS NULL OR TRIM(p_status) = '' THEN status
                    ELSE p_status
                  END,
         updated_at = NOW()
   WHERE id_line_item = p_id_line_item
     AND item_type = 'payment'
     AND deleted_at IS NULL;

  SELECT
    id_line_item,
    item_type,
    id_folio,
    id_line_item_catalog,
    method,
    reference,
    status,
    updated_at
  FROM line_item
  WHERE id_line_item = p_id_line_item;
END $$

DELIMITER ;
