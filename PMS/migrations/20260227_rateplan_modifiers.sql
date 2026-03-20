/* ============================================================================
   RATEPLAN MODIFIERS V1
   - Crea tablas nuevas para motor de pricing por modificadores.
   - Migra datos desde rateplan_season y rateplan_pricing.
   - Mantiene tablas legacy (no borra nada).
   - Marca rateplan.rules_json.new_pricing_enabled = 1.
   ============================================================================ */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;

CREATE TABLE IF NOT EXISTS rateplan_modifier (
  id_rateplan_modifier BIGINT NOT NULL AUTO_INCREMENT,
  id_rateplan BIGINT NOT NULL,
  modifier_name VARCHAR(160) NOT NULL,
  description TEXT DEFAULT NULL,
  priority INT NOT NULL DEFAULT 0,
  apply_mode ENUM('stack','best_for_guest','best_for_property','override') NOT NULL DEFAULT 'stack',
  price_action ENUM('add_pct','add_cents','set_price') NOT NULL DEFAULT 'add_pct',
  add_pct DECIMAL(8,3) DEFAULT NULL,
  add_cents INT DEFAULT NULL,
  set_price_cents INT DEFAULT NULL,
  clamp_min_cents INT DEFAULT NULL,
  clamp_max_cents INT DEFAULT NULL,
  respect_category_min TINYINT(1) NOT NULL DEFAULT 1,
  is_always_on TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_rateplan_modifier),
  KEY idx_rpm_rateplan (id_rateplan),
  KEY idx_rpm_active (is_active, deleted_at),
  KEY idx_rpm_priority (priority),
  CONSTRAINT fk_rpm_rateplan FOREIGN KEY (id_rateplan)
    REFERENCES rateplan (id_rateplan)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateplan_modifier_schedule (
  id_rateplan_modifier_schedule BIGINT NOT NULL AUTO_INCREMENT,
  id_rateplan_modifier BIGINT NOT NULL,
  schedule_type ENUM('range','rrule') NOT NULL DEFAULT 'range',
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  schedule_rrule VARCHAR(255) DEFAULT NULL,
  exdates_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_rateplan_modifier_schedule),
  KEY idx_rpms_modifier (id_rateplan_modifier),
  KEY idx_rpms_type_dates (schedule_type, start_date, end_date),
  CONSTRAINT fk_rpms_modifier FOREIGN KEY (id_rateplan_modifier)
    REFERENCES rateplan_modifier (id_rateplan_modifier)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT chk_rpms_exdates_json CHECK (exdates_json IS NULL OR JSON_VALID(exdates_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateplan_modifier_condition (
  id_rateplan_modifier_condition BIGINT NOT NULL AUTO_INCREMENT,
  id_rateplan_modifier BIGINT NOT NULL,
  condition_type VARCHAR(64) NOT NULL,
  operator_key VARCHAR(16) NOT NULL DEFAULT 'eq',
  value_number DECIMAL(12,4) DEFAULT NULL,
  value_number_to DECIMAL(12,4) DEFAULT NULL,
  value_text VARCHAR(255) DEFAULT NULL,
  value_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_rateplan_modifier_condition),
  KEY idx_rpmc_modifier (id_rateplan_modifier),
  KEY idx_rpmc_type (condition_type, operator_key),
  CONSTRAINT fk_rpmc_modifier FOREIGN KEY (id_rateplan_modifier)
    REFERENCES rateplan_modifier (id_rateplan_modifier)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT chk_rpmc_value_json CHECK (value_json IS NULL OR JSON_VALID(value_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateplan_modifier_scope (
  id_rateplan_modifier_scope BIGINT NOT NULL AUTO_INCREMENT,
  id_rateplan_modifier BIGINT NOT NULL,
  id_category BIGINT DEFAULT NULL,
  id_room BIGINT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_rateplan_modifier_scope),
  KEY idx_rpmsc_modifier (id_rateplan_modifier),
  KEY idx_rpmsc_category (id_category),
  KEY idx_rpmsc_room (id_room),
  CONSTRAINT fk_rpmsc_modifier FOREIGN KEY (id_rateplan_modifier)
    REFERENCES rateplan_modifier (id_rateplan_modifier)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_rpmsc_category FOREIGN KEY (id_category)
    REFERENCES roomcategory (id_category)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_rpmsc_room FOREIGN KEY (id_room)
    REFERENCES room (id_room)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS occupancy_snapshot (
  id_occupancy_snapshot BIGINT NOT NULL AUTO_INCREMENT,
  id_property BIGINT NOT NULL,
  snapshot_date DATE NOT NULL,
  id_category BIGINT DEFAULT NULL,
  rooms_total INT NOT NULL DEFAULT 0,
  rooms_sold INT NOT NULL DEFAULT 0,
  occupancy_pct DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  as_of_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_occupancy_snapshot),
  KEY idx_os_property_date (id_property, snapshot_date),
  KEY idx_os_property_category_date (id_property, id_category, snapshot_date),
  CONSTRAINT fk_os_property FOREIGN KEY (id_property)
    REFERENCES property (id_property)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_os_category FOREIGN KEY (id_category)
    REFERENCES roomcategory (id_category)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------------------------------------------------------------
   MIGRACION: rateplan_season -> rateplan_modifier (+ schedule range)
   -------------------------------------------------------------------------- */

INSERT INTO rateplan_modifier (
  id_rateplan,
  modifier_name,
  description,
  priority,
  apply_mode,
  price_action,
  add_pct,
  add_cents,
  set_price_cents,
  respect_category_min,
  is_always_on,
  is_active,
  created_at,
  created_by,
  updated_at
)
SELECT
  rs.id_rateplan,
  COALESCE(NULLIF(TRIM(rs.season_name), ''), CONCAT('Legacy season #', rs.id_rateplan_season)),
  CONCAT('MIGRATED_FROM_RATEPLAN_SEASON:', rs.id_rateplan_season),
  COALESCE(rs.priority, 0),
  'stack',
  'add_pct',
  COALESCE(rs.adjust_pct, 0),
  NULL,
  NULL,
  1,
  0,
  COALESCE(rs.is_active, 1),
  COALESCE(rs.created_at, NOW()),
  NULL,
  COALESCE(rs.updated_at, NOW())
FROM rateplan_season rs
WHERE NOT EXISTS (
  SELECT 1
  FROM rateplan_modifier rm
  WHERE rm.id_rateplan = rs.id_rateplan
    AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_SEASON:', rs.id_rateplan_season)
);

INSERT INTO rateplan_modifier_schedule (
  id_rateplan_modifier,
  schedule_type,
  start_date,
  end_date,
  schedule_rrule,
  exdates_json,
  is_active,
  created_at,
  created_by,
  updated_at
)
SELECT
  rm.id_rateplan_modifier,
  'range',
  rs.start_date,
  rs.end_date,
  NULL,
  NULL,
  COALESCE(rs.is_active, 1),
  COALESCE(rs.created_at, NOW()),
  NULL,
  COALESCE(rs.updated_at, NOW())
FROM rateplan_season rs
JOIN rateplan_modifier rm
  ON rm.id_rateplan = rs.id_rateplan
 AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_SEASON:', rs.id_rateplan_season)
WHERE NOT EXISTS (
  SELECT 1
  FROM rateplan_modifier_schedule s
  WHERE s.id_rateplan_modifier = rm.id_rateplan_modifier
    AND s.schedule_type = 'range'
    AND s.start_date = rs.start_date
    AND s.end_date = rs.end_date
    AND s.deleted_at IS NULL
);

/* --------------------------------------------------------------------------
   MIGRACION: rateplan_pricing -> rateplan_modifier
   -------------------------------------------------------------------------- */

/* Base adjust */
INSERT INTO rateplan_modifier (
  id_rateplan,
  modifier_name,
  description,
  priority,
  apply_mode,
  price_action,
  add_pct,
  respect_category_min,
  is_always_on,
  is_active,
  created_at,
  created_by,
  updated_at
)
SELECT
  rpp.id_rateplan,
  'Legacy base adjust',
  CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_BASE:', rpp.id_rateplan_pricing),
  100,
  'stack',
  'add_pct',
  COALESCE(rpp.base_adjust_pct, 0),
  1,
  1,
  COALESCE(rpp.is_active, 1),
  COALESCE(rpp.created_at, NOW()),
  NULL,
  COALESCE(rpp.updated_at, NOW())
FROM rateplan_pricing rpp
WHERE ABS(COALESCE(rpp.base_adjust_pct, 0)) > 0.0001
  AND NOT EXISTS (
    SELECT 1
    FROM rateplan_modifier rm
    WHERE rm.id_rateplan = rpp.id_rateplan
      AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_BASE:', rpp.id_rateplan_pricing)
  );

/* Weekend adjust */
INSERT INTO rateplan_modifier (
  id_rateplan,
  modifier_name,
  description,
  priority,
  apply_mode,
  price_action,
  add_pct,
  respect_category_min,
  is_always_on,
  is_active,
  created_at,
  created_by,
  updated_at
)
SELECT
  rpp.id_rateplan,
  'Legacy weekend adjust',
  CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_WEEKEND:', rpp.id_rateplan_pricing),
  90,
  'stack',
  'add_pct',
  COALESCE(rpp.weekend_adjust_pct, 0),
  1,
  0,
  COALESCE(rpp.is_active, 1),
  COALESCE(rpp.created_at, NOW()),
  NULL,
  COALESCE(rpp.updated_at, NOW())
FROM rateplan_pricing rpp
WHERE ABS(COALESCE(rpp.weekend_adjust_pct, 0)) > 0.0001
  AND NOT EXISTS (
    SELECT 1
    FROM rateplan_modifier rm
    WHERE rm.id_rateplan = rpp.id_rateplan
      AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_WEEKEND:', rpp.id_rateplan_pricing)
  );

INSERT INTO rateplan_modifier_schedule (
  id_rateplan_modifier,
  schedule_type,
  start_date,
  end_date,
  schedule_rrule,
  exdates_json,
  is_active,
  created_at,
  updated_at
)
SELECT
  rm.id_rateplan_modifier,
  'rrule',
  NULL,
  NULL,
  'FREQ=WEEKLY;BYDAY=SA,SU',
  NULL,
  rm.is_active,
  rm.created_at,
  rm.updated_at
FROM rateplan_modifier rm
WHERE rm.description LIKE 'MIGRATED_FROM_RATEPLAN_PRICING_WEEKEND:%'
  AND NOT EXISTS (
    SELECT 1
    FROM rateplan_modifier_schedule s
    WHERE s.id_rateplan_modifier = rm.id_rateplan_modifier
      AND s.schedule_type = 'rrule'
      AND s.schedule_rrule = 'FREQ=WEEKLY;BYDAY=SA,SU'
      AND s.deleted_at IS NULL
  );

/* Occupancy tranche: low */
INSERT INTO rateplan_modifier (
  id_rateplan,
  modifier_name,
  description,
  priority,
  apply_mode,
  price_action,
  add_pct,
  respect_category_min,
  is_always_on,
  is_active,
  created_at,
  updated_at
)
SELECT
  rpp.id_rateplan,
  'Legacy occupancy low',
  CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_LOW:', rpp.id_rateplan_pricing),
  80,
  'stack',
  'add_pct',
  COALESCE(rpp.low_occupancy_adjust_pct, 0),
  1,
  1,
  COALESCE(rpp.is_active, 1),
  COALESCE(rpp.created_at, NOW()),
  COALESCE(rpp.updated_at, NOW())
FROM rateplan_pricing rpp
WHERE COALESCE(rpp.use_occupancy, 1) = 1
  AND ABS(COALESCE(rpp.low_occupancy_adjust_pct, 0)) > 0.0001
  AND NOT EXISTS (
    SELECT 1
    FROM rateplan_modifier rm
    WHERE rm.id_rateplan = rpp.id_rateplan
      AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_LOW:', rpp.id_rateplan_pricing)
  );

INSERT INTO rateplan_modifier_condition (
  id_rateplan_modifier,
  condition_type,
  operator_key,
  value_number,
  value_number_to,
  value_text,
  value_json,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  rm.id_rateplan_modifier,
  'occupancy_pct_property',
  'lt',
  COALESCE(rpp.occupancy_low_threshold, 40),
  NULL,
  NULL,
  NULL,
  1,
  1,
  NOW(),
  NOW()
FROM rateplan_pricing rpp
JOIN rateplan_modifier rm
  ON rm.id_rateplan = rpp.id_rateplan
 AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_LOW:', rpp.id_rateplan_pricing)
WHERE NOT EXISTS (
  SELECT 1
  FROM rateplan_modifier_condition c
  WHERE c.id_rateplan_modifier = rm.id_rateplan_modifier
    AND c.condition_type = 'occupancy_pct_property'
    AND c.operator_key = 'lt'
    AND c.deleted_at IS NULL
);

/* Occupancy tranche: mid_low */
INSERT INTO rateplan_modifier (
  id_rateplan,
  modifier_name,
  description,
  priority,
  apply_mode,
  price_action,
  add_pct,
  respect_category_min,
  is_always_on,
  is_active,
  created_at,
  updated_at
)
SELECT
  rpp.id_rateplan,
  'Legacy occupancy mid low',
  CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_MID_LOW:', rpp.id_rateplan_pricing),
  79,
  'stack',
  'add_pct',
  COALESCE(rpp.mid_low_occupancy_adjust_pct, 0),
  1,
  1,
  COALESCE(rpp.is_active, 1),
  COALESCE(rpp.created_at, NOW()),
  COALESCE(rpp.updated_at, NOW())
FROM rateplan_pricing rpp
WHERE COALESCE(rpp.use_occupancy, 1) = 1
  AND ABS(COALESCE(rpp.mid_low_occupancy_adjust_pct, 0)) > 0.0001
  AND NOT EXISTS (
    SELECT 1
    FROM rateplan_modifier rm
    WHERE rm.id_rateplan = rpp.id_rateplan
      AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_MID_LOW:', rpp.id_rateplan_pricing)
  );

INSERT INTO rateplan_modifier_condition (
  id_rateplan_modifier,
  condition_type,
  operator_key,
  value_number,
  value_number_to,
  value_text,
  value_json,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  rm.id_rateplan_modifier,
  'occupancy_pct_property',
  'between',
  COALESCE(rpp.occupancy_low_threshold, 40),
  COALESCE(rpp.occupancy_mid_low_threshold, 55),
  NULL,
  NULL,
  1,
  1,
  NOW(),
  NOW()
FROM rateplan_pricing rpp
JOIN rateplan_modifier rm
  ON rm.id_rateplan = rpp.id_rateplan
 AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_MID_LOW:', rpp.id_rateplan_pricing)
WHERE NOT EXISTS (
  SELECT 1
  FROM rateplan_modifier_condition c
  WHERE c.id_rateplan_modifier = rm.id_rateplan_modifier
    AND c.condition_type = 'occupancy_pct_property'
    AND c.operator_key = 'between'
    AND c.deleted_at IS NULL
);

/* Occupancy tranche: mid_high */
INSERT INTO rateplan_modifier (
  id_rateplan,
  modifier_name,
  description,
  priority,
  apply_mode,
  price_action,
  add_pct,
  respect_category_min,
  is_always_on,
  is_active,
  created_at,
  updated_at
)
SELECT
  rpp.id_rateplan,
  'Legacy occupancy mid high',
  CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_MID_HIGH:', rpp.id_rateplan_pricing),
  78,
  'stack',
  'add_pct',
  COALESCE(rpp.mid_high_occupancy_adjust_pct, 0),
  1,
  1,
  COALESCE(rpp.is_active, 1),
  COALESCE(rpp.created_at, NOW()),
  COALESCE(rpp.updated_at, NOW())
FROM rateplan_pricing rpp
WHERE COALESCE(rpp.use_occupancy, 1) = 1
  AND ABS(COALESCE(rpp.mid_high_occupancy_adjust_pct, 0)) > 0.0001
  AND NOT EXISTS (
    SELECT 1
    FROM rateplan_modifier rm
    WHERE rm.id_rateplan = rpp.id_rateplan
      AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_MID_HIGH:', rpp.id_rateplan_pricing)
  );

INSERT INTO rateplan_modifier_condition (
  id_rateplan_modifier,
  condition_type,
  operator_key,
  value_number,
  value_number_to,
  value_text,
  value_json,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  rm.id_rateplan_modifier,
  'occupancy_pct_property',
  'between',
  COALESCE(rpp.occupancy_mid_low_threshold, 55),
  COALESCE(rpp.occupancy_mid_high_threshold, 70),
  NULL,
  NULL,
  1,
  1,
  NOW(),
  NOW()
FROM rateplan_pricing rpp
JOIN rateplan_modifier rm
  ON rm.id_rateplan = rpp.id_rateplan
 AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_MID_HIGH:', rpp.id_rateplan_pricing)
WHERE NOT EXISTS (
  SELECT 1
  FROM rateplan_modifier_condition c
  WHERE c.id_rateplan_modifier = rm.id_rateplan_modifier
    AND c.condition_type = 'occupancy_pct_property'
    AND c.operator_key = 'between'
    AND c.deleted_at IS NULL
);

/* Occupancy tranche: high */
INSERT INTO rateplan_modifier (
  id_rateplan,
  modifier_name,
  description,
  priority,
  apply_mode,
  price_action,
  add_pct,
  respect_category_min,
  is_always_on,
  is_active,
  created_at,
  updated_at
)
SELECT
  rpp.id_rateplan,
  'Legacy occupancy high',
  CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_HIGH:', rpp.id_rateplan_pricing),
  77,
  'stack',
  'add_pct',
  COALESCE(rpp.high_occupancy_adjust_pct, 0),
  1,
  1,
  COALESCE(rpp.is_active, 1),
  COALESCE(rpp.created_at, NOW()),
  COALESCE(rpp.updated_at, NOW())
FROM rateplan_pricing rpp
WHERE COALESCE(rpp.use_occupancy, 1) = 1
  AND ABS(COALESCE(rpp.high_occupancy_adjust_pct, 0)) > 0.0001
  AND NOT EXISTS (
    SELECT 1
    FROM rateplan_modifier rm
    WHERE rm.id_rateplan = rpp.id_rateplan
      AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_HIGH:', rpp.id_rateplan_pricing)
  );

INSERT INTO rateplan_modifier_condition (
  id_rateplan_modifier,
  condition_type,
  operator_key,
  value_number,
  value_number_to,
  value_text,
  value_json,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  rm.id_rateplan_modifier,
  'occupancy_pct_property',
  'gte',
  COALESCE(rpp.occupancy_high_threshold, 80),
  NULL,
  NULL,
  NULL,
  1,
  1,
  NOW(),
  NOW()
FROM rateplan_pricing rpp
JOIN rateplan_modifier rm
  ON rm.id_rateplan = rpp.id_rateplan
 AND rm.description = CONCAT('MIGRATED_FROM_RATEPLAN_PRICING_OCC_HIGH:', rpp.id_rateplan_pricing)
WHERE NOT EXISTS (
  SELECT 1
  FROM rateplan_modifier_condition c
  WHERE c.id_rateplan_modifier = rm.id_rateplan_modifier
    AND c.condition_type = 'occupancy_pct_property'
    AND c.operator_key = 'gte'
    AND c.deleted_at IS NULL
);

/* --------------------------------------------------------------------------
   ACTIVAR FLAG DE NUEVO MOTOR EN RATEPLAN
   -------------------------------------------------------------------------- */
UPDATE rateplan
SET rules_json = CASE
  WHEN rules_json IS NULL OR TRIM(rules_json) = '' THEN JSON_OBJECT('new_pricing_enabled', 1)
  WHEN JSON_VALID(rules_json) THEN JSON_SET(rules_json, '$.new_pricing_enabled', 1)
  ELSE rules_json
END,
updated_at = NOW()
WHERE deleted_at IS NULL;

/* --------------------------------------------------------------------------
   Resumen rapido
   -------------------------------------------------------------------------- */
SELECT
  COUNT(*) AS total_modifiers
FROM rateplan_modifier
WHERE deleted_at IS NULL;

SELECT
  id_rateplan,
  COUNT(*) AS modifiers_per_rateplan
FROM rateplan_modifier
WHERE deleted_at IS NULL
GROUP BY id_rateplan
ORDER BY modifiers_per_rateplan DESC, id_rateplan;
