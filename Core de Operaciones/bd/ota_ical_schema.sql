-- OTA iCal schema (idempotent)

CREATE TABLE IF NOT EXISTS ota_ical_feed (
  id_ota_ical_feed BIGINT NOT NULL AUTO_INCREMENT,
  id_company BIGINT NOT NULL,
  id_property BIGINT NOT NULL,
  scope_type VARCHAR(16) NOT NULL DEFAULT 'room',
  id_room BIGINT NULL,
  id_category BIGINT NULL,
  platform VARCHAR(32) NOT NULL DEFAULT 'other',
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Mexico_City',
  import_url TEXT NULL,
  import_enabled TINYINT(1) NOT NULL DEFAULT 0,
  import_ignore_our_uids TINYINT(1) NOT NULL DEFAULT 1,
  sync_interval_minutes INT NOT NULL DEFAULT 15,
  export_enabled TINYINT(1) NOT NULL DEFAULT 1,
  export_token VARCHAR(64) NOT NULL,
  export_summary_mode VARCHAR(32) NOT NULL DEFAULT 'busy',
  export_include_reservations TINYINT(1) NOT NULL DEFAULT 1,
  export_include_blocks TINYINT(1) NOT NULL DEFAULT 1,
  http_etag VARCHAR(255) NULL,
  http_last_modified VARCHAR(255) NULL,
  last_sync_at DATETIME NULL,
  last_success_at DATETIME NULL,
  last_error TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT NULL,
  PRIMARY KEY (id_ota_ical_feed),
  UNIQUE KEY uq_ota_ical_feed_token (export_token),
  KEY idx_ota_ical_feed_company (id_company),
  KEY idx_ota_ical_feed_property (id_property),
  KEY idx_ota_ical_feed_room (id_room),
  KEY idx_ota_ical_feed_category (id_category),
  KEY idx_ota_ical_feed_import (import_enabled, is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ota_ical_event (
  id_ota_ical_event BIGINT NOT NULL AUTO_INCREMENT,
  id_ota_ical_feed BIGINT NOT NULL,
  uid VARCHAR(255) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(32) NULL,
  summary VARCHAR(255) NULL,
  description TEXT NULL,
  last_modified_raw VARCHAR(64) NULL,
  dtstamp_raw VARCHAR(64) NULL,
  sequence_no INT NOT NULL DEFAULT 0,
  raw_event MEDIUMTEXT NULL,
  hash_sha256 CHAR(64) NOT NULL,
  last_seen_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ota_ical_event),
  UNIQUE KEY uq_ota_ical_event_uid (id_ota_ical_feed, uid),
  KEY idx_ota_ical_event_feed_active (id_ota_ical_feed, is_active, deleted_at),
  KEY idx_ota_ical_event_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ota_ical_event_map (
  id_ota_ical_event_map BIGINT NOT NULL AUTO_INCREMENT,
  id_ota_ical_event BIGINT NOT NULL,
  entity_type VARCHAR(32) NULL,
  entity_id BIGINT NULL,
  link_status VARCHAR(32) NOT NULL DEFAULT 'linked',
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ota_ical_event_map),
  UNIQUE KEY uq_ota_ical_event_map_event (id_ota_ical_event),
  KEY idx_ota_ical_event_map_entity (entity_type, entity_id),
  KEY idx_ota_ical_event_map_active (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ota_ical_sync_log (
  id_ota_ical_sync_log BIGINT NOT NULL AUTO_INCREMENT,
  id_ota_ical_feed BIGINT NOT NULL,
  run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ok TINYINT(1) NOT NULL DEFAULT 0,
  http_status INT NULL,
  events_total INT NOT NULL DEFAULT 0,
  events_created INT NOT NULL DEFAULT 0,
  events_updated INT NOT NULL DEFAULT 0,
  events_deleted INT NOT NULL DEFAULT 0,
  blocks_created INT NOT NULL DEFAULT 0,
  blocks_updated INT NOT NULL DEFAULT 0,
  blocks_deleted INT NOT NULL DEFAULT 0,
  error_text TEXT NULL,
  PRIMARY KEY (id_ota_ical_sync_log),
  KEY idx_ota_ical_sync_log_feed (id_ota_ical_feed, run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW vw_room_busy_period AS
SELECT
  'reservation' AS source_type,
  r.id_reservation AS source_id,
  r.id_property,
  r.id_room,
  r.check_in_date AS start_date,
  r.check_out_date AS end_date,
  COALESCE(NULLIF(r.code,''), CONCAT('RSV-', r.id_reservation)) AS title,
  COALESCE(r.notes_internal, r.notes_guest, '') AS detail,
  COALESCE(r.updated_at, r.created_at, NOW()) AS last_modified
FROM reservation r
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND COALESCE(r.status, 'confirmado') NOT IN ('cancelada','cancelado','cancelled','canceled')
  AND r.id_room IS NOT NULL
  AND r.check_out_date > r.check_in_date
UNION ALL
SELECT
  'room_block' AS source_type,
  rb.id_room_block AS source_id,
  rb.id_property,
  rb.id_room,
  rb.start_date AS start_date,
  rb.end_date AS end_date,
  COALESCE(NULLIF(rb.code,''), CONCAT('BLK-', rb.id_room_block)) AS title,
  COALESCE(rb.description, '') AS detail,
  COALESCE(rb.updated_at, rb.created_at, NOW()) AS last_modified
FROM room_block rb
WHERE rb.deleted_at IS NULL
  AND rb.is_active = 1
  AND rb.end_date > rb.start_date;

CREATE OR REPLACE VIEW vw_ota_ical_export_events AS
SELECT
  f.id_ota_ical_feed,
  f.export_token,
  f.timezone,
  f.platform,
  CONCAT('OTA ', UPPER(f.platform), ' ',
    CASE WHEN f.scope_type = 'room' THEN COALESCE(rm.code, CONCAT('ROOM#', f.id_room))
         ELSE COALESCE(ct.name, CONCAT('CAT#', f.id_category))
    END
  ) AS calendar_name,
  b.source_type,
  b.source_id,
  b.id_property,
  b.id_room,
  b.start_date,
  b.end_date,
  CASE WHEN COALESCE(f.export_summary_mode, 'busy') = 'detailed' THEN b.title ELSE 'Blocked' END AS summary,
  CASE WHEN COALESCE(f.export_summary_mode, 'busy') = 'detailed' THEN b.detail ELSE '' END AS description,
  'CONFIRMED' AS status,
  b.last_modified,
  CONCAT('ota-feed-', f.id_ota_ical_feed, '-', b.source_type, '-', b.source_id, '@vivelavibe-pms') AS uid
FROM ota_ical_feed f
JOIN vw_room_busy_period b ON b.id_property = f.id_property
LEFT JOIN room rm ON rm.id_room = f.id_room
LEFT JOIN roomcategory ct ON ct.id_category = f.id_category
WHERE f.deleted_at IS NULL
  AND f.is_active = 1
  AND f.export_enabled = 1
  AND (
    (f.scope_type = 'room' AND f.id_room IS NOT NULL AND b.id_room = f.id_room)
    OR
    (f.scope_type = 'category' AND f.id_category IS NOT NULL AND b.id_room IN (
      SELECT r2.id_room
      FROM room r2
      WHERE r2.id_property = f.id_property
        AND r2.id_category = f.id_category
        AND r2.deleted_at IS NULL
        AND r2.is_active = 1
    ))
  )
  AND (
    (b.source_type = 'reservation' AND COALESCE(f.export_include_reservations, 1) = 1)
    OR
    (b.source_type = 'room_block' AND COALESCE(f.export_include_blocks, 1) = 1)
  );
