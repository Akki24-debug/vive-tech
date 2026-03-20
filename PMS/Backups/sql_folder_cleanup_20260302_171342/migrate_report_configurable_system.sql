DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_migrate_report_configurable_system` $$
CREATE PROCEDURE `sp_migrate_report_configurable_system` ()
BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_has_report_item INT DEFAULT 0;
  DECLARE v_has_legacy INT DEFAULT 0;

  SELECT COUNT(*) INTO v_exists
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_config';

  IF v_exists > 0 THEN
    SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_config'
      AND COLUMN_NAME = 'report_type';
    IF v_exists = 0 THEN
      ALTER TABLE report_config
        ADD COLUMN report_type VARCHAR(32) NOT NULL DEFAULT 'reservation' AFTER report_name;
    END IF;

    SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_config'
      AND COLUMN_NAME = 'line_item_type_scope';
    IF v_exists = 0 THEN
      ALTER TABLE report_config
        ADD COLUMN line_item_type_scope VARCHAR(32) DEFAULT NULL AFTER report_type;
    END IF;

    SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_config'
      AND COLUMN_NAME = 'description';
    IF v_exists = 0 THEN
      ALTER TABLE report_config
        ADD COLUMN description TEXT DEFAULT NULL AFTER line_item_type_scope;
    END IF;

    SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_config'
      AND COLUMN_NAME = 'deleted_at';
    IF v_exists = 0 THEN
      ALTER TABLE report_config
        ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER is_active;
    END IF;
  END IF;

  CREATE TABLE IF NOT EXISTS report_config_column (
    id_report_config_column BIGINT NOT NULL AUTO_INCREMENT,
    id_report_config BIGINT NOT NULL,
    column_key VARCHAR(160) NOT NULL,
    column_source VARCHAR(32) NOT NULL DEFAULT 'field',
    source_field_key VARCHAR(120) DEFAULT NULL,
    id_line_item_catalog BIGINT DEFAULT NULL,
    display_name VARCHAR(160) NOT NULL,
    display_category VARCHAR(80) DEFAULT NULL,
    data_type VARCHAR(32) NOT NULL DEFAULT 'text',
    aggregation VARCHAR(32) NOT NULL DEFAULT 'none',
    format_hint VARCHAR(64) DEFAULT NULL,
    order_index INT NOT NULL DEFAULT 1,
    is_visible TINYINT NOT NULL DEFAULT 1,
    is_filterable TINYINT NOT NULL DEFAULT 1,
    filter_operator_default VARCHAR(32) DEFAULT NULL,
    legacy_role VARCHAR(32) DEFAULT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_report_config_column),
    UNIQUE KEY uk_report_config_column_key (id_report_config, column_key),
    KEY idx_report_config_column_report (id_report_config),
    KEY idx_report_config_column_catalog (id_line_item_catalog),
    KEY idx_report_config_column_active (id_report_config, is_active, order_index),
    CONSTRAINT fk_report_config_column_report
      FOREIGN KEY (id_report_config) REFERENCES report_config(id_report_config)
      ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE IF NOT EXISTS report_config_filter (
    id_report_config_filter BIGINT NOT NULL AUTO_INCREMENT,
    id_report_config BIGINT NOT NULL,
    filter_key VARCHAR(160) NOT NULL,
    operator_key VARCHAR(32) NOT NULL DEFAULT 'eq',
    value_text TEXT DEFAULT NULL,
    value_from_text VARCHAR(255) DEFAULT NULL,
    value_to_text VARCHAR(255) DEFAULT NULL,
    value_list_text TEXT DEFAULT NULL,
    logic_join VARCHAR(8) NOT NULL DEFAULT 'AND',
    order_index INT NOT NULL DEFAULT 1,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_report_config_filter),
    KEY idx_report_config_filter_report (id_report_config, is_active, order_index),
    KEY idx_report_config_filter_key (id_report_config, filter_key),
    CONSTRAINT fk_report_config_filter_report
      FOREIGN KEY (id_report_config) REFERENCES report_config(id_report_config)
      ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE IF NOT EXISTS report_field_catalog (
    id_report_field_catalog BIGINT NOT NULL AUTO_INCREMENT,
    report_type VARCHAR(32) NOT NULL,
    field_key VARCHAR(120) NOT NULL,
    field_label VARCHAR(160) NOT NULL,
    field_group VARCHAR(80) NOT NULL,
    data_type VARCHAR(32) NOT NULL DEFAULT 'text',
    supports_filter TINYINT NOT NULL DEFAULT 1,
    supports_sort TINYINT NOT NULL DEFAULT 1,
    is_default TINYINT NOT NULL DEFAULT 0,
    default_order INT NOT NULL DEFAULT 0,
    select_expression VARCHAR(255) NOT NULL,
    filter_expression VARCHAR(255) DEFAULT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_report_field_catalog),
    UNIQUE KEY uk_report_field_catalog (report_type, field_key),
    KEY idx_report_field_catalog_group (report_type, field_group, is_active)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  INSERT INTO report_field_catalog (
    report_type,
    field_key,
    field_label,
    field_group,
    data_type,
    supports_filter,
    supports_sort,
    is_default,
    default_order,
    select_expression,
    filter_expression,
    is_active
  ) VALUES
    ('reservation','id_reservation','ID reservacion','Identificacion','number',1,1,0,1,'base.id_reservation','base.id_reservation',1),
    ('reservation','reservation_code','Codigo reservacion','Identificacion','text',1,1,1,2,'base.reservation_code','base.reservation_code',1),
    ('reservation','reservation_status','Estatus reservacion','Identificacion','text',1,1,1,3,'base.reservation_status','base.reservation_status',1),
    ('reservation','source','Origen','Identificacion','text',1,1,1,4,'base.source','base.source',1),
    ('reservation','check_in_date','Check-in','Fechas','date',1,1,1,5,'base.check_in_date','base.check_in_date',1),
    ('reservation','check_out_date','Check-out','Fechas','date',1,1,1,6,'base.check_out_date','base.check_out_date',1),
    ('reservation','nights','Noches','Fechas','number',1,1,1,7,'base.nights','base.nights',1),
    ('reservation','adults','Adultos','Ocupacion','number',1,1,0,8,'base.adults','base.adults',1),
    ('reservation','children','Menores','Ocupacion','number',1,1,0,9,'base.children','base.children',1),
    ('reservation','guest_full_name','Huesped','Huesped','text',1,1,1,10,'base.guest_full_name','base.guest_full_name',1),
    ('reservation','guest_email','Email huesped','Huesped','text',1,1,0,11,'base.guest_email','base.guest_email',1),
    ('reservation','room_code','Habitacion codigo','Habitacion','text',1,1,0,12,'base.room_code','base.room_code',1),
    ('reservation','room_name','Habitacion nombre','Habitacion','text',1,1,0,13,'base.room_name','base.room_name',1),
    ('reservation','category_name','Categoria habitacion','Habitacion','text',1,1,0,14,'base.category_name','base.category_name',1),
    ('reservation','property_code','Propiedad codigo','Propiedad','text',1,1,1,15,'base.property_code','base.property_code',1),
    ('reservation','property_name','Propiedad nombre','Propiedad','text',1,1,1,16,'base.property_name','base.property_name',1),
    ('reservation','total_price_cents','Tarifa total','Totales','money',1,1,1,17,'base.total_price_cents','base.total_price_cents',1),
    ('reservation','balance_due_cents','Balance pendiente','Totales','money',1,1,1,18,'base.balance_due_cents','base.balance_due_cents',1),
    ('reservation','charges_cents','Cargos','Totales','money',1,1,0,19,'base.charges_cents','base.charges_cents',1),
    ('reservation','taxes_cents','Impuestos','Totales','money',1,1,0,20,'base.taxes_cents','base.taxes_cents',1),
    ('reservation','payments_cents','Pagos','Totales','money',1,1,0,21,'base.payments_cents','base.payments_cents',1),
    ('reservation','obligations_cents','Obligaciones','Totales','money',1,1,0,22,'base.obligations_cents','base.obligations_cents',1),
    ('reservation','incomes_cents','Ingresos','Totales','money',1,1,0,23,'base.incomes_cents','base.incomes_cents',1),
    ('reservation','net_cents','Neto','Totales','money',1,1,1,24,'base.net_cents','base.net_cents',1),
    ('reservation','created_at','Creado el','Auditoria','datetime',1,1,0,25,'base.created_at','base.created_at',1),

    ('line_item','id_line_item','ID line item','Identificacion','number',1,1,0,1,'base.id_line_item','base.id_line_item',1),
    ('line_item','item_type','Tipo line item','Identificacion','text',1,1,1,2,'base.item_type','base.item_type',1),
    ('line_item','line_item_status','Estatus line item','Identificacion','text',1,1,1,3,'base.line_item_status','base.line_item_status',1),
    ('line_item','service_date','Fecha servicio','Fechas','date',1,1,1,4,'base.service_date','base.service_date',1),
    ('line_item','quantity','Cantidad','Importe','number',1,1,0,5,'base.quantity','base.quantity',1),
    ('line_item','unit_price_cents','Unitario','Importe','money',1,1,0,6,'base.unit_price_cents','base.unit_price_cents',1),
    ('line_item','discount_amount_cents','Descuento','Importe','money',1,1,0,7,'base.discount_amount_cents','base.discount_amount_cents',1),
    ('line_item','amount_cents','Importe','Importe','money',1,1,1,8,'base.amount_cents','base.amount_cents',1),
    ('line_item','currency','Moneda','Importe','text',1,1,0,9,'base.currency','base.currency',1),
    ('line_item','catalog_id','ID catalogo','Catalogo','number',1,1,0,10,'base.id_line_item_catalog','base.id_line_item_catalog',1),
    ('line_item','catalog_name','Catalogo','Catalogo','text',1,1,1,11,'base.catalog_name','base.catalog_name',1),
    ('line_item','subcategory_name','Subcategoria','Catalogo','text',1,1,0,12,'base.subcategory_name','base.subcategory_name',1),
    ('line_item','category_name','Categoria','Catalogo','text',1,1,0,13,'base.category_name','base.category_name',1),
    ('line_item','id_folio','ID folio','Relacion','number',1,1,0,14,'base.id_folio','base.id_folio',1),
    ('line_item','id_reservation','ID reservacion','Relacion','number',1,1,0,15,'base.id_reservation','base.id_reservation',1),
    ('line_item','reservation_code','Reservacion','Relacion','text',1,1,1,16,'base.reservation_code','base.reservation_code',1),
    ('line_item','property_code','Propiedad codigo','Relacion','text',1,1,1,17,'base.property_code','base.property_code',1),
    ('line_item','property_name','Propiedad nombre','Relacion','text',1,1,1,18,'base.property_name','base.property_name',1),
    ('line_item','guest_full_name','Huesped','Relacion','text',1,1,0,19,'base.guest_full_name','base.guest_full_name',1),
    ('line_item','created_at','Creado el','Auditoria','datetime',1,1,0,20,'base.created_at','base.created_at',1),

    ('property','id_property','ID propiedad','Identificacion','number',1,1,0,1,'base.id_property','base.id_property',1),
    ('property','property_code','Codigo propiedad','Identificacion','text',1,1,1,2,'base.property_code','base.property_code',1),
    ('property','property_name','Nombre propiedad','Identificacion','text',1,1,1,3,'base.property_name','base.property_name',1),
    ('property','reservation_count','Reservaciones','Reservaciones','number',1,1,1,4,'base.reservation_count','base.reservation_count',1),
    ('property','reservation_nights','Noches reservadas','Reservaciones','number',1,1,0,5,'base.reservation_nights','base.reservation_nights',1),
    ('property','reservation_guests','Huespedes','Reservaciones','number',1,1,0,6,'base.reservation_guests','base.reservation_guests',1),
    ('property','total_price_cents','Tarifa total','Totales','money',1,1,1,7,'base.total_price_cents','base.total_price_cents',1),
    ('property','balance_due_cents','Balance pendiente','Totales','money',1,1,1,8,'base.balance_due_cents','base.balance_due_cents',1),
    ('property','charges_cents','Cargos','Totales','money',1,1,0,9,'base.charges_cents','base.charges_cents',1),
    ('property','taxes_cents','Impuestos','Totales','money',1,1,0,10,'base.taxes_cents','base.taxes_cents',1),
    ('property','payments_cents','Pagos','Totales','money',1,1,0,11,'base.payments_cents','base.payments_cents',1),
    ('property','obligations_cents','Obligaciones','Totales','money',1,1,0,12,'base.obligations_cents','base.obligations_cents',1),
    ('property','incomes_cents','Ingresos','Totales','money',1,1,0,13,'base.incomes_cents','base.incomes_cents',1),
    ('property','net_cents','Neto','Totales','money',1,1,1,14,'base.net_cents','base.net_cents',1)
  ON DUPLICATE KEY UPDATE
    field_label = VALUES(field_label),
    field_group = VALUES(field_group),
    data_type = VALUES(data_type),
    supports_filter = VALUES(supports_filter),
    supports_sort = VALUES(supports_sort),
    is_default = VALUES(is_default),
    default_order = VALUES(default_order),
    select_expression = VALUES(select_expression),
    filter_expression = VALUES(filter_expression),
    is_active = VALUES(is_active),
    updated_at = NOW();

  SELECT COUNT(*) INTO v_has_report_item
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'report_config_item';

  IF v_has_report_item > 0 THEN
    INSERT INTO report_config_column (
      id_report_config,
      column_key,
      column_source,
      source_field_key,
      id_line_item_catalog,
      display_name,
      display_category,
      data_type,
      aggregation,
      format_hint,
      order_index,
      is_visible,
      is_filterable,
      filter_operator_default,
      legacy_role,
      is_active,
      deleted_at,
      created_at,
      created_by,
      updated_at
    )
    SELECT
      rci.id_report_config,
      CONCAT('catalog_', rci.id_sale_item_catalog, '_', LOWER(COALESCE(rci.role, 'extra'))),
      'line_item_catalog',
      NULL,
      rci.id_sale_item_catalog,
      COALESCE(lic.item_name, CONCAT('Catalogo #', rci.id_sale_item_catalog)),
      COALESCE(rci.role, 'extra'),
      'money',
      'sum',
      NULL,
      CASE COALESCE(rci.role, '')
        WHEN 'lodging' THEN 150
        WHEN 'cleaning' THEN 200
        WHEN 'iva' THEN 250
        WHEN 'ish' THEN 300
        ELSE 400
      END,
      1,
      1,
      'eq',
      rci.role,
      1,
      NULL,
      COALESCE(rci.created_at, NOW()),
      rci.created_by,
      NOW()
    FROM report_config_item rci
    LEFT JOIN line_item_catalog lic
      ON lic.id_line_item_catalog = rci.id_sale_item_catalog
    WHERE rci.deleted_at IS NULL
      AND rci.is_active = 1
    ON DUPLICATE KEY UPDATE
      id_line_item_catalog = VALUES(id_line_item_catalog),
      display_name = VALUES(display_name),
      display_category = VALUES(display_category),
      data_type = VALUES(data_type),
      aggregation = VALUES(aggregation),
      order_index = VALUES(order_index),
      is_visible = VALUES(is_visible),
      is_filterable = VALUES(is_filterable),
      filter_operator_default = VALUES(filter_operator_default),
      legacy_role = VALUES(legacy_role),
      is_active = 1,
      deleted_at = NULL,
      updated_at = NOW();

    SELECT COUNT(*) INTO v_has_legacy
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'report_config_item_legacy';

    IF v_has_legacy = 0 THEN
      RENAME TABLE report_config_item TO report_config_item_legacy;
    END IF;
  END IF;
END $$

CALL `sp_migrate_report_configurable_system`() $$
DROP PROCEDURE IF EXISTS `sp_migrate_report_configurable_system` $$

DELIMITER ;
