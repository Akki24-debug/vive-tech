SET @schema_name = DATABASE();

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND COLUMN_NAME = 'category'
    ),
    'SELECT 1',
    "ALTER TABLE message_template
       ADD COLUMN category VARCHAR(64) NOT NULL DEFAULT 'general' AFTER body"
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND COLUMN_NAME = 'sort_order'
    ),
    'SELECT 1',
    "ALTER TABLE message_template
       ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER category"
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND COLUMN_NAME = 'channel'
    ),
    'SELECT 1',
    "ALTER TABLE message_template
       ADD COLUMN channel VARCHAR(32) NOT NULL DEFAULT 'whatsapp' AFTER sort_order"
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND COLUMN_NAME = 'is_trackable'
    ),
    'SELECT 1',
    'ALTER TABLE message_template
       ADD COLUMN is_trackable TINYINT(1) NOT NULL DEFAULT 0 AFTER channel'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND COLUMN_NAME = 'is_required'
    ),
    'SELECT 1',
    'ALTER TABLE message_template
       ADD COLUMN is_required TINYINT(1) NOT NULL DEFAULT 0 AFTER is_trackable'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND COLUMN_NAME = 'id_sale_item_catalog'
    ),
    'SELECT 1',
    'ALTER TABLE message_template
       ADD COLUMN id_sale_item_catalog BIGINT(20) DEFAULT NULL AFTER is_required'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND INDEX_NAME = 'idx_message_template_tracking'
    ),
    'SELECT 1',
    'ALTER TABLE message_template
       ADD KEY idx_message_template_tracking (id_company, id_property, is_active, is_trackable, is_required, category, sort_order)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'message_template'
        AND INDEX_NAME = 'idx_message_template_sale_item'
    ),
    'SELECT 1',
    'ALTER TABLE message_template
       ADD KEY idx_message_template_sale_item (id_sale_item_catalog)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'reservation_message_log'
        AND INDEX_NAME = 'uk_reservation_message_template'
    ),
    'ALTER TABLE reservation_message_log DROP INDEX uk_reservation_message_template',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'reservation_message_log'
        AND INDEX_NAME = 'idx_reservation_message_log_history'
    ),
    'SELECT 1',
    'ALTER TABLE reservation_message_log
       ADD KEY idx_reservation_message_log_history (id_reservation, id_message_template, sent_at)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS reservation_message_status (
  id_reservation BIGINT(20) NOT NULL,
  id_message_template BIGINT(20) NOT NULL,
  tracking_status VARCHAR(32) NOT NULL DEFAULT 'pending',
  is_trackable TINYINT(1) NOT NULL DEFAULT 1,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  last_sent_at DATETIME DEFAULT NULL,
  last_sent_by BIGINT(20) DEFAULT NULL,
  last_channel VARCHAR(32) DEFAULT NULL,
  last_phone VARCHAR(32) DEFAULT NULL,
  last_message_title VARCHAR(255) DEFAULT NULL,
  last_message_body TEXT DEFAULT NULL,
  last_id_reservation_message_log BIGINT(20) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT current_timestamp(),
  created_by BIGINT(20) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  updated_by BIGINT(20) DEFAULT NULL,
  PRIMARY KEY (id_reservation, id_message_template),
  KEY idx_reservation_message_status_template (id_message_template),
  KEY idx_reservation_message_status_tracking (tracking_status, is_required, is_trackable),
  KEY idx_reservation_message_status_last_log (last_id_reservation_message_log),
  KEY idx_reservation_message_status_last_sent_by (last_sent_by),
  CONSTRAINT fk_reservation_message_status_reservation FOREIGN KEY (id_reservation) REFERENCES reservation (id_reservation) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reservation_message_status_template FOREIGN KEY (id_message_template) REFERENCES message_template (id_message_template) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reservation_message_status_last_log FOREIGN KEY (last_id_reservation_message_log) REFERENCES reservation_message_log (id_reservation_message_log) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reservation_message_status_last_sent_by FOREIGN KEY (last_sent_by) REFERENCES app_user (id_user) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reservation_message_status_created_by FOREIGN KEY (created_by) REFERENCES app_user (id_user) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reservation_message_status_updated_by FOREIGN KEY (updated_by) REFERENCES app_user (id_user) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO reservation_message_status (
  id_reservation,
  id_message_template,
  tracking_status,
  is_trackable,
  is_required,
  last_sent_at,
  last_sent_by,
  last_channel,
  last_phone,
  last_message_title,
  last_message_body,
  last_id_reservation_message_log,
  created_at,
  created_by,
  updated_at,
  updated_by
)
SELECT
  rml.id_reservation,
  rml.id_message_template,
  'sent',
  COALESCE(mt.is_trackable, 0),
  COALESCE(mt.is_required, 0),
  rml.sent_at,
  rml.sent_by,
  rml.channel,
  rml.sent_to_phone,
  rml.message_title,
  rml.message_body,
  rml.id_reservation_message_log,
  COALESCE(rml.created_at, rml.sent_at, NOW()),
  rml.sent_by,
  COALESCE(rml.created_at, rml.sent_at, NOW()),
  rml.sent_by
FROM reservation_message_log rml
JOIN (
  SELECT
    id_reservation,
    id_message_template,
    MAX(id_reservation_message_log) AS last_log_id
  FROM reservation_message_log
  GROUP BY id_reservation, id_message_template
) latest
  ON latest.last_log_id = rml.id_reservation_message_log
JOIN message_template mt
  ON mt.id_message_template = rml.id_message_template
 AND (COALESCE(mt.is_trackable, 0) = 1 OR COALESCE(mt.is_required, 0) = 1)
ON DUPLICATE KEY UPDATE
  tracking_status = VALUES(tracking_status),
  is_trackable = VALUES(is_trackable),
  is_required = VALUES(is_required),
  last_sent_at = VALUES(last_sent_at),
  last_sent_by = VALUES(last_sent_by),
  last_channel = VALUES(last_channel),
  last_phone = VALUES(last_phone),
  last_message_title = VALUES(last_message_title),
  last_message_body = VALUES(last_message_body),
  last_id_reservation_message_log = VALUES(last_id_reservation_message_log),
  updated_at = VALUES(updated_at),
  updated_by = VALUES(updated_by);
