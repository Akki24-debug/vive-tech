ALTER TABLE line_item_catalog_parent
  ADD COLUMN percent_value DECIMAL(12,6) NULL DEFAULT NULL AFTER add_to_father_total;

