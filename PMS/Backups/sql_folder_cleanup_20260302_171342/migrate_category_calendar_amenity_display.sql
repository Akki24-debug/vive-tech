-- Seleccion de amenidades de categoria para mostrar como iconos en calendario.
-- Ejecutar una sola vez por base de datos.

CREATE TABLE IF NOT EXISTS category_calendar_amenity_display (
  id_category_calendar_amenity_display BIGINT NOT NULL AUTO_INCREMENT,
  id_category BIGINT NOT NULL,
  amenity_key VARCHAR(64) NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT current_timestamp(),
  updated_at DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id_category_calendar_amenity_display),
  UNIQUE KEY uq_category_calendar_amenity (id_category, amenity_key),
  KEY idx_category_calendar_order (id_category, display_order),
  CONSTRAINT fk_category_calendar_display_category
    FOREIGN KEY (id_category)
    REFERENCES roomcategory (id_category)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
