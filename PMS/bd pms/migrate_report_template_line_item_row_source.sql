SET @sql_add_line_item_type_scope = (
    SELECT IF(
        EXISTS(
            SELECT 1
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'report_template'
               AND COLUMN_NAME = 'line_item_type_scope'
        ),
        'SELECT 1',
        'ALTER TABLE report_template ADD COLUMN line_item_type_scope VARCHAR(32) DEFAULT NULL AFTER row_source'
    )
);
PREPARE stmt_add_line_item_type_scope FROM @sql_add_line_item_type_scope;
EXECUTE stmt_add_line_item_type_scope;
DEALLOCATE PREPARE stmt_add_line_item_type_scope;

ALTER TABLE report_template
    MODIFY COLUMN row_source ENUM('reservation', 'line_item') NOT NULL DEFAULT 'reservation';
