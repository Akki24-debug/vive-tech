DELIMITER $$

ALTER TABLE role
  MODIFY COLUMN id_property BIGINT NULL $$

CREATE TABLE IF NOT EXISTS pms_authz_config (
  id_company BIGINT NOT NULL,
  authz_mode ENUM('audit','enforce') NOT NULL DEFAULT 'audit',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT NULL,
  PRIMARY KEY (id_company),
  KEY idx_authz_mode (authz_mode),
  CONSTRAINT fk_authzcfg_company FOREIGN KEY (id_company) REFERENCES company(id_company),
  CONSTRAINT fk_authzcfg_user FOREIGN KEY (updated_by) REFERENCES app_user(id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci $$

CREATE TABLE IF NOT EXISTS pms_authz_audit (
  id_authz_audit BIGINT NOT NULL AUTO_INCREMENT,
  id_company BIGINT NULL,
  id_user BIGINT NULL,
  permission_code VARCHAR(100) NOT NULL,
  property_code VARCHAR(100) NULL,
  authz_mode ENUM('audit','enforce') NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  context_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_authz_audit),
  KEY idx_authzaudit_company_created (id_company, created_at),
  KEY idx_authzaudit_user_created (id_user, created_at),
  KEY idx_authzaudit_perm_created (permission_code, created_at),
  CONSTRAINT fk_authzaudit_company FOREIGN KEY (id_company) REFERENCES company(id_company),
  CONSTRAINT fk_authzaudit_user FOREIGN KEY (id_user) REFERENCES app_user(id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci $$

DELIMITER ;

