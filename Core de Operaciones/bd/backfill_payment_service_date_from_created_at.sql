UPDATE line_item
   SET service_date = DATE(created_at),
       updated_at = NOW()
 WHERE item_type = 'payment'
   AND service_date IS NULL
   AND deleted_at IS NULL;
