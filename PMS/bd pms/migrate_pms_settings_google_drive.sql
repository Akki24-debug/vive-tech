SET @has_google_drive_enabled := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'google_drive_enabled'
);

SET @ddl_google_drive_enabled := IF(
  @has_google_drive_enabled > 0,
  'SELECT 1',
  'ALTER TABLE pms_settings ADD COLUMN google_drive_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER timezone'
);

PREPARE stmt_google_drive_enabled FROM @ddl_google_drive_enabled;
EXECUTE stmt_google_drive_enabled;
DEALLOCATE PREPARE stmt_google_drive_enabled;

SET @has_google_drive_client_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'google_drive_client_id'
);

SET @ddl_google_drive_client_id := IF(
  @has_google_drive_client_id > 0,
  'SELECT 1',
  'ALTER TABLE pms_settings ADD COLUMN google_drive_client_id VARCHAR(255) DEFAULT NULL AFTER google_drive_enabled'
);

PREPARE stmt_google_drive_client_id FROM @ddl_google_drive_client_id;
EXECUTE stmt_google_drive_client_id;
DEALLOCATE PREPARE stmt_google_drive_client_id;

SET @has_google_drive_client_secret := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'google_drive_client_secret'
);

SET @ddl_google_drive_client_secret := IF(
  @has_google_drive_client_secret > 0,
  'SELECT 1',
  'ALTER TABLE pms_settings ADD COLUMN google_drive_client_secret TEXT DEFAULT NULL AFTER google_drive_client_id'
);

PREPARE stmt_google_drive_client_secret FROM @ddl_google_drive_client_secret;
EXECUTE stmt_google_drive_client_secret;
DEALLOCATE PREPARE stmt_google_drive_client_secret;

SET @has_google_drive_refresh_token := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'google_drive_refresh_token'
);

SET @ddl_google_drive_refresh_token := IF(
  @has_google_drive_refresh_token > 0,
  'SELECT 1',
  'ALTER TABLE pms_settings ADD COLUMN google_drive_refresh_token TEXT DEFAULT NULL AFTER google_drive_client_secret'
);

PREPARE stmt_google_drive_refresh_token FROM @ddl_google_drive_refresh_token;
EXECUTE stmt_google_drive_refresh_token;
DEALLOCATE PREPARE stmt_google_drive_refresh_token;

SET @has_google_drive_folder_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'google_drive_folder_id'
);

SET @ddl_google_drive_folder_id := IF(
  @has_google_drive_folder_id > 0,
  'SELECT 1',
  'ALTER TABLE pms_settings ADD COLUMN google_drive_folder_id VARCHAR(255) DEFAULT NULL AFTER google_drive_refresh_token'
);

PREPARE stmt_google_drive_folder_id FROM @ddl_google_drive_folder_id;
EXECUTE stmt_google_drive_folder_id;
DEALLOCATE PREPARE stmt_google_drive_folder_id;

SET @has_google_drive_spreadsheet_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings'
    AND COLUMN_NAME = 'google_drive_spreadsheet_id'
);

SET @ddl_google_drive_spreadsheet_id := IF(
  @has_google_drive_spreadsheet_id > 0,
  'SELECT 1',
  'ALTER TABLE pms_settings ADD COLUMN google_drive_spreadsheet_id VARCHAR(255) DEFAULT NULL AFTER google_drive_folder_id'
);

PREPARE stmt_google_drive_spreadsheet_id FROM @ddl_google_drive_spreadsheet_id;
EXECUTE stmt_google_drive_spreadsheet_id;
DEALLOCATE PREPARE stmt_google_drive_spreadsheet_id;
