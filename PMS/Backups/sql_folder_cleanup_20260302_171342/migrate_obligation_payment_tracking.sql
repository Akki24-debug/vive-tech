/* Obligation payment tracking + obligation payment methods catalog */

CREATE TABLE IF NOT EXISTS pms_settings_obligation_payment_method (
  id_obligation_payment_method BIGINT NOT NULL AUTO_INCREMENT,
  id_company BIGINT NOT NULL,
  method_name VARCHAR(120) NOT NULL,
  method_description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT NULL,
  PRIMARY KEY (id_obligation_payment_method),
  KEY idx_psopm_company_active (id_company, is_active, deleted_at),
  KEY idx_psopm_name (id_company, method_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obligation_payment_log (
  id_obligation_payment_log BIGINT NOT NULL AUTO_INCREMENT,
  id_company BIGINT NOT NULL,
  id_line_item BIGINT NOT NULL,
  id_folio BIGINT NOT NULL,
  id_reservation BIGINT NULL,
  id_obligation_payment_method BIGINT NOT NULL,
  payment_mode VARCHAR(16) NOT NULL,
  amount_input_cents INT NOT NULL DEFAULT 0,
  amount_applied_cents INT NOT NULL DEFAULT 0,
  paid_before_cents INT NOT NULL DEFAULT 0,
  paid_after_cents INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT NULL,
  PRIMARY KEY (id_obligation_payment_log),
  KEY idx_opl_company_created (id_company, created_at),
  KEY idx_opl_line_item (id_line_item, created_at),
  KEY idx_opl_reservation (id_reservation, created_at),
  KEY idx_opl_method (id_obligation_payment_method, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_method_description := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pms_settings_obligation_payment_method'
    AND COLUMN_NAME = 'method_description'
);

SET @sql_add_method_description := IF(
  @has_method_description = 0,
  'ALTER TABLE pms_settings_obligation_payment_method ADD COLUMN method_description VARCHAR(255) NULL AFTER method_name',
  'SELECT 1'
);

PREPARE stmt_add_method_description FROM @sql_add_method_description;
EXECUTE stmt_add_method_description;
DEALLOCATE PREPARE stmt_add_method_description;

