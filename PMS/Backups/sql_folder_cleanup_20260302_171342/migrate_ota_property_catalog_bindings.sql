/* OTA and Property catalog bindings for payout mapping. */

SET @has_ota_service_fee_catalog := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ota_account'
    AND COLUMN_NAME = 'id_service_fee_payment_catalog'
);

SET @sql_add_ota_service_fee_catalog := IF(
  @has_ota_service_fee_catalog = 0,
  'ALTER TABLE ota_account ADD COLUMN id_service_fee_payment_catalog BIGINT NULL AFTER notes',
  'SELECT 1'
);

PREPARE stmt_add_ota_service_fee_catalog FROM @sql_add_ota_service_fee_catalog;
EXECUTE stmt_add_ota_service_fee_catalog;
DEALLOCATE PREPARE stmt_add_ota_service_fee_catalog;

SET @has_ota_service_fee_catalog_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ota_account'
    AND INDEX_NAME = 'idx_ota_account_service_fee_catalog'
);

SET @sql_add_ota_service_fee_catalog_idx := IF(
  @has_ota_service_fee_catalog_idx = 0,
  'ALTER TABLE ota_account ADD INDEX idx_ota_account_service_fee_catalog (id_service_fee_payment_catalog)',
  'SELECT 1'
);

PREPARE stmt_add_ota_service_fee_catalog_idx FROM @sql_add_ota_service_fee_catalog_idx;
EXECUTE stmt_add_ota_service_fee_catalog_idx;
DEALLOCATE PREPARE stmt_add_ota_service_fee_catalog_idx;

SET @has_property_owner_obligation_catalog := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'property'
    AND COLUMN_NAME = 'id_owner_payment_obligation_catalog'
);

SET @sql_add_property_owner_obligation_catalog := IF(
  @has_property_owner_obligation_catalog = 0,
  'ALTER TABLE property ADD COLUMN id_owner_payment_obligation_catalog BIGINT NULL AFTER check_out_time',
  'SELECT 1'
);

PREPARE stmt_add_property_owner_obligation_catalog FROM @sql_add_property_owner_obligation_catalog;
EXECUTE stmt_add_property_owner_obligation_catalog;
DEALLOCATE PREPARE stmt_add_property_owner_obligation_catalog;

SET @has_property_owner_obligation_catalog_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'property'
    AND INDEX_NAME = 'idx_property_owner_payment_obligation_catalog'
);

SET @sql_add_property_owner_obligation_catalog_idx := IF(
  @has_property_owner_obligation_catalog_idx = 0,
  'ALTER TABLE property ADD INDEX idx_property_owner_payment_obligation_catalog (id_owner_payment_obligation_catalog)',
  'SELECT 1'
);

PREPARE stmt_add_property_owner_obligation_catalog_idx FROM @sql_add_property_owner_obligation_catalog_idx;
EXECUTE stmt_add_property_owner_obligation_catalog_idx;
DEALLOCATE PREPARE stmt_add_property_owner_obligation_catalog_idx;
