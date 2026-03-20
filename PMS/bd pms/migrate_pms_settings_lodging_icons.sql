ALTER TABLE `pms_settings_lodging_catalog`
  ADD COLUMN IF NOT EXISTS `icon_html` varchar(32) DEFAULT NULL AFTER `id_sale_item_catalog`;
