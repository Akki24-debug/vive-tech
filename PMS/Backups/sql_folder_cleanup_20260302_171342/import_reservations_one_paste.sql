/* ============================================================================
   IMPORT RESERVACIONES - ONE PASTE (MariaDB)
   - Sin folios
   - Monto se guarda como nota
   - Crea un huesped nuevo por cada fila
   - Sin empalmes
   - Si una fila falla, sigue con la siguiente
   ============================================================================ */

DROP TABLE IF EXISTS tmp_reservation_import_input;
CREATE TABLE tmp_reservation_import_input (
  id_import BIGINT NOT NULL AUTO_INCREMENT,
  id_property BIGINT NOT NULL,
  room_name VARCHAR(255) NOT NULL,
  guest_name VARCHAR(255) NOT NULL,
  amount_raw VARCHAR(64) DEFAULT NULL,
  check_in_raw VARCHAR(20) NOT NULL,
  check_out_raw VARCHAR(20) NOT NULL,
  origin_raw VARCHAR(120) NOT NULL,
  PRIMARY KEY (id_import)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS tmp_reservation_import_log;
CREATE TABLE tmp_reservation_import_log (
  id_log BIGINT NOT NULL AUTO_INCREMENT,
  id_import BIGINT NOT NULL,
  result_status ENUM('inserted','skipped','error') NOT NULL,
  reason VARCHAR(500) DEFAULT NULL,
  id_reservation BIGINT DEFAULT NULL,
  reservation_code VARCHAR(120) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  KEY idx_log_import (id_import),
  KEY idx_log_status (result_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ============================================================================
   PEGA AQUI TUS DATOS EN VALUES (id_property, room_name, guest_name, amount_raw,
   check_in_raw, check_out_raw, origin_raw)
   ============================================================================
*/
INSERT INTO tmp_reservation_import_input
  (id_property, room_name, guest_name, amount_raw, check_in_raw, check_out_raw, origin_raw)
VALUES
  (6, 'Depa 1', 'Belén', '7300', '2025-01-04', '2025-01-09', 'AirB&B'),
  (6, 'Depa 1', 'Belén', '1500', '2025-01-09', '2025-01-10', 'AirB&B'),
  (6, 'Depa 1', 'Benjamin', '3398', '2025-01-10', '2025-01-12', 'Booking'),
  (6, 'Depa 1', 'Daniel', '4500', '2025-01-18', '2025-01-21', 'Mapas'),
  (6, 'Depa 1', 'Jeffrey', '4624', '2025-01-22', '2025-01-25', 'AirB&B'),
  (6, 'Depa 1', 'Guillermina', '7000', '2025-01-25', '2025-01-29', 'AirB&B'),
  (6, 'Depa 1', 'Abigail', '5685', '2025-01-29', '2025-02-01', 'AirB&B'),
  (6, 'Depa 1', 'Mathia', NULL, '2026-01-01', '2025-01-04', 'Booking'),
  (6, 'Depa 2', 'Antonio', '1800', '2025-01-04', '2025-01-05', 'AirB&B'),
  (6, 'Depa 2', 'Fracisco', '4313', '2025-01-08', '2025-01-11', 'Booking'),
  (6, 'Depa 2', 'Jaime', '2670', '2025-01-11', '2025-01-13', 'Expedia'),
  (6, 'Depa 2', 'Sebastián', '5200', '2025-01-16', '2025-01-20', 'Expedia'),
  (6, 'Depa 2', 'Viktor', '1300', '2025-01-21', '2025-01-22', 'AirB&B'),
  (6, 'Depa 2', 'Omar', '7000', '2025-01-23', '2025-01-27', 'Booking'),
  (6, 'Depa 2', 'Michaus Samantha', '2600', '2025-01-27', '2025-01-29', 'Booking'),
  (6, 'Depa 2', 'Idania', '5300', '2025-01-29', '2025-02-01', 'Booking'),
  (6, 'Depa 2', 'Mariana', NULL, '2026-01-01', '2026-01-02', 'Booking'),
  (6, 'Depa 2', 'isac', '3400', '2026-01-02', '2025-01-04', 'AirB&B'),
  (6, 'Depa 3', 'Sharon', NULL, '2026-01-01', '2026-01-02', 'AirB&B'),
  (6, 'Depa 3', 'Ana', '1500', '2026-01-02', '2026-01-03', 'Booking'),
  (6, 'Depa 4', 'Extension Sandra', '7500', '2025-01-06', '2025-01-09', 'AirB&B'),
  (6, 'Depa 4', 'Nicolas', '7817', '2025-01-09', '2025-01-14', 'Booking'),
  (6, 'Depa 4', 'Braulio', '3000', '2026-01-01', '2026-01-02', 'AirB&B'),
  (6, 'Depa 4', 'Sandra', '10000', '2026-01-02', '2025-01-06', 'AirB&B'),
  (11, 'Habitación privada', 'Arturo', '12000', '2025-01-03', '2025-01-13', 'AirB&B'),
  (11, 'Habitación privada con AC', 'Jonathan', '3600', '2025-01-03', '2025-01-06', 'AirB&B'),
  (11, 'Habitación privada con AC', 'Valeria', '5000', '2025-01-06', '2025-01-10', 'AirB&B'),
  (11, 'Habitación privada con AC', 'Jaime', '1300', '2025-01-10', '2025-01-11', '#b4a7d6'),
  (11, 'Habitación privada con AC', 'Dani', '4800', '2025-01-11', '2025-01-15', 'AirB&B'),
  (11, 'Habitación privada con AC', 'Dayana', NULL, '2025-01-19', '2025-01-23', 'AirB&B'),
  (11, 'Habitación privada con AC', 'Dafne', NULL, '2025-01-28', '2025-02-01', 'AirB&B'),
  (10, 'Cuarto 1', 'Mariana', '1600', '2025-01-08', '2025-01-10', 'AirB&B'),
  (10, 'Cuarto 1', 'Angeles', NULL, '2025-01-14', '2025-01-27', 'AirB&B'),
  (10, 'Cuarto 1', 'Fernanda', NULL, '2026-01-01', '2025-01-04', 'AirB&B'),
  (10, 'Cuarto 22', 'Valeria', NULL, '2025-01-06', '2025-01-10', 'AirB&B'),
  (10, 'Cuarto 22', 'Paul', NULL, '2026-01-01', '2025-01-06', 'Booking'),
  (10, 'Cuarto 33', 'Paco', '1200', '2025-01-07', '2025-01-09', 'AirB&B'),
  (10, 'Cuarto 33', 'Jesus', '4000', '2025-01-10', '2025-01-17', 'AirB&B'),
  (5, 'Caoba', 'David', '1400', '2025-01-21', '2025-01-23', 'Mapas'),
  (5, 'Caoba', 'Jonathan', '2600', '2025-01-23', '2025-01-27', 'AirB&B'),
  (5, 'Caoba', 'Felipe', '600', '2025-01-29', '2025-01-30', 'AirB&B'),
  (5, 'Caoba', 'Raúl', '4000', '2025-01-31', '2025-02-01', 'Booking'),
  (5, 'Caoba', 'David', NULL, '2026-01-01', '2025-01-20', 'Expedia'),
  (5, 'Guaje', 'Angel', '1000', '2025-01-03', '2025-01-04', 'AirB&B'),
  (5, 'Guaje', 'Evelyn', '4069', '2025-01-04', '2025-01-10', 'Booking'),
  (5, 'Guaje', 'Emmanuelle', '4200', '2025-01-10', '2025-01-16', 'Booking'),
  (5, 'Guaje', 'Imad', '1200', '2025-01-17', '2025-01-19', 'AirB&B'),
  (5, 'Guaje', 'Darwin', '600', '2025-01-27', '2025-01-28', 'Mapas'),
  (5, 'Guaje', 'Juliana', '600', '2025-01-29', '2025-01-30', 'Mapas'),
  (5, 'Guaje', 'Sara', '3846', '2026-01-01', '2025-01-03', 'Booking'),
  (5, 'Macuil', 'Manuel', '1000', '2025-01-05', '2025-01-06', 'Mapas'),
  (5, 'Macuil', 'Roberto', '2000', '2025-01-07', '2025-01-09', 'Mapas'),
  (5, 'Macuil', 'Carlos', '900', '2025-01-10', '2025-01-11', 'Mapas'),
  (5, 'Macuil', 'Mario', '1000', '2025-01-13', '2025-01-14', 'Mapas'),
  (5, 'Macuil', 'Francisco', '700', '2025-01-14', '2025-01-15', 'Mapas'),
  (5, 'Macuil', 'Juan', '800', '2025-01-27', '2025-01-28', 'Mapas'),
  (5, 'Macuil', 'Nayeli', '3000', '2025-01-28', '2025-01-31', 'Mapas'),
  (5, 'Macuil', 'Eric', '1800', '2025-01-31', '2025-02-01', 'AirB&B'),
  (5, 'Macuil', 'Daniela', '3600', '2026-01-01', '2025-01-04', 'AirB&B'),
  (5, 'Parota', 'Dennis', '800', '2025-01-03', '2025-01-04', 'AirB&B'),
  (5, 'Parota', 'Emily', '700', '2025-01-04', '2025-01-05', 'AirB&B'),
  (5, 'Parota', 'Sheyla', '600', '2025-01-05', '2025-01-06', 'Mapas'),
  (5, 'Parota', 'Tscholks', '1300', '2025-01-07', '2025-01-09', 'AirB&B'),
  (5, 'Parota', 'Cecilia', '700', '2025-01-10', '2025-01-11', 'Expedia'),
  (5, 'Parota', 'T', '650', '2025-01-14', '2025-01-15', 'AirB&B'),
  (5, 'Parota', 'louse', '600', '2025-01-17', '2025-01-18', 'Booking'),
  (5, 'Parota', 'Andreas', '3500', '2025-01-23', '2025-01-30', 'AirB&B'),
  (5, 'Parota', 'Anderson', '1600', '2025-01-30', '2025-02-01', 'AirB&B'),
  (5, 'Parota', 'Alexia', '2000', '2026-01-01', '2025-01-03', 'Mapas'),
  (4, 'Depa 1', 'Laura', '1600', '2025-01-13', '2025-01-15', 'AirB&B'),
  (4, 'Depa 1', 'Claudia', '4400', '2025-01-29', '2025-02-01', 'Mapas'),
  (4, 'Depa 2', 'Jenna', '18000', '2025-01-21', '2025-02-01', '#ea9999'),
  (4, 'Depa 2', 'Jenna', '18000', '2026-01-01', '2025-01-21', '#ea9999'),
  (3, 'Cuarto 1', 'Fer', '1000', '2025-01-06', '2025-01-07', 'Mapas'),
  (3, 'Cuarto 1', 'Douglas', '700', '2025-01-07', '2025-01-08', 'Expedia'),
  (3, 'Cuarto 1', 'Douglas', '700', '2025-01-08', '2025-01-09', 'Expedia'),
  (3, 'Cuarto 1', 'Regalado', '2646', '2025-01-09', '2025-01-12', 'AirB&B'),
  (3, 'Cuarto 1', 'Regalado', '882', '2025-01-12', '2025-01-13', 'AirB&B'),
  (3, 'Cuarto 1', 'Macarena', '2400', '2025-01-14', '2025-01-16', 'AirB&B'),
  (3, 'Cuarto 1', 'Annie', '500', '2025-01-16', '2025-01-17', 'Booking'),
  (3, 'Cuarto 1', 'Hayde', '1600', '2025-01-17', '2025-01-19', '#c9daf8'),
  (3, 'Cuarto 1', 'Daniel', '600', '2025-01-20', '2025-01-21', 'Mapas'),
  (3, 'Cuarto 1', 'Claudia', NULL, '2025-01-22', '2025-01-26', 'Booking'),
  (3, 'Cuarto 1', 'Alexander', '5141', '2025-01-26', '2025-02-01', 'Booking'),
  (3, 'Cuarto 1', 'Anely', '2700', '2026-01-01', '2026-01-02', 'Booking'),
  (3, 'Cuarto 1', 'Lorena', '8450', '2026-01-02', '2025-01-06', 'Booking'),
  (3, 'Cuarto 2', 'Rose', '700', '2025-01-05', '2025-01-06', 'Expedia'),
  (3, 'Cuarto 2', 'Eric', '700', '2025-01-07', '2025-01-08', 'AirB&B'),
  (3, 'Cuarto 2', 'Brisa *', '800', '2025-01-08', '2025-01-11', 'AirB&B'),
  (3, 'Cuarto 2', 'Guo', '600', '2025-01-11', '2025-01-12', 'Mapas'),
  (3, 'Cuarto 2', 'Luz', '1150', '2025-01-15', '2025-01-16', 'Booking'),
  (3, 'Cuarto 2', 'Salome', NULL, '2025-01-16', '2025-01-21', 'AirB&B'),
  (3, 'Cuarto 2', 'Eibh', '600', '2025-01-21', '2025-01-22', 'Booking'),
  (3, 'Cuarto 2', 'Jorge', '600', '2025-01-22', '2025-01-23', 'Booking'),
  (3, 'Cuarto 2', 'Jorge', '600', '2025-01-23', '2025-01-24', 'Booking'),
  (3, 'Cuarto 2', 'Laura', '620', '2025-01-24', '2025-01-25', 'Booking'),
  (3, 'Cuarto 2', 'Mariana', '997', '2025-01-25', '2025-01-26', 'AirB&B'),
  (3, 'Cuarto 2', 'Mariela', '3572', '2025-01-27', '2025-02-01', 'AirB&B'),
  (3, 'Cuarto 2', 'Sara', '1600', '2026-01-01', '2026-01-02', 'Mapas'),
  (3, 'Cuarto 2', 'Dennis', '3641', '2026-01-02', '2025-01-05', 'Booking'),
  (3, 'Cuarto 3', 'Pau', '800', '2025-01-03', '2025-01-04', 'Mapas'),
  (3, 'Cuarto 3', 'Monn', '1600', '2025-01-04', '2025-01-06', 'AirB&B'),
  (3, 'Cuarto 3', 'Timo', '700', '2025-01-06', '2025-01-07', 'Expedia'),
  (3, 'Cuarto 3', 'Brisa', '3200', '2025-01-07', '2025-01-08', 'AirB&B'),
  (3, 'Cuarto 3', 'NICOLAS', '1000', '2025-01-08', '2025-01-09', 'Mapas'),
  (3, 'Cuarto 3', 'Néstor', '700', '2025-01-09', '2025-01-10', 'Mapas'),
  (3, 'Cuarto 3', 'Gerardo', '1700', '2025-01-10', '2025-01-11', 'Mapas'),
  (3, 'Cuarto 3', 'X', '1800', '2025-01-15', '2025-01-17', 'Mapas'),
  (3, 'Cuarto 3', 'Gladis', '700', '2025-01-17', '2025-01-18', 'Mapas'),
  (3, 'Cuarto 3', 'Ailyn', '800', '2025-01-18', '2025-01-19', 'Mapas'),
  (3, 'Cuarto 3', 'Miri', '600', '2025-01-22', '2025-01-23', 'Mapas'),
  (3, 'Cuarto 3', 'Jazmin', '700', '2025-01-23', '2025-01-24', 'Mapas'),
  (3, 'Cuarto 3', 'Escarlet', '4600', '2025-01-24', '2025-01-31', 'Booking'),
  (3, 'Cuarto 3', 'Juan Diego', '900', '2025-01-31', '2025-02-01', 'AirB&B'),
  (3, 'Cuarto 3', 'Miguel*', '1500', '2026-01-01', '2026-01-02', 'AirB&B'),
  (3, 'Cuarto 3', 'Giadalh', '1600', '2026-01-02', '2026-01-03', 'Mapas'),
  (3, 'Cuarto 4', 'Darwin', '800', '2025-01-03', '2025-01-04', 'Mapas'),
  (3, 'Cuarto 4', 'Angel', '2100', '2025-01-04', '2025-01-07', 'AirB&B'),
  (3, 'Cuarto 4', 'Estrella', '800', '2025-01-07', '2025-01-08', 'AirB&B'),
  (3, 'Cuarto 4', 'Douglas', NULL, '2025-01-09', '2025-01-10', 'Expedia'),
  (3, 'Cuarto 4', 'Olesia', '8400', '2025-01-10', '2025-01-24', 'AirB&B'),
  (3, 'Cuarto 4', 'Annie', '3700', '2025-01-24', '2025-01-30', 'Booking'),
  (3, 'Cuarto 4', 'María', '1360', '2025-01-30', '2025-02-01', 'Booking'),
  (3, 'Cuarto 4', 'Amir *', '1397', '2026-01-01', '2026-01-02', 'Booking'),
  (3, 'Cuarto 4', 'Lorena', NULL, '2026-01-02', '2026-01-03', 'Booking'),
  (3, 'Cuarto 5', 'Max', '3200', '2025-01-04', '2025-01-08', 'AirB&B'),
  (3, 'Cuarto 5', 'Luis', '700', '2025-01-09', '2025-01-10', 'Mapas'),
  (3, 'Cuarto 5', 'Laura', '700', '2025-01-10', '2025-01-11', 'Mapas'),
  (3, 'Cuarto 5', 'Luz', '575', '2025-01-15', '2025-01-16', 'Booking'),
  (3, 'Cuarto 5', 'Pam', '650', '2025-01-16', '2025-01-17', 'Mapas'),
  (3, 'Cuarto 5', 'Itzel', '600', '2025-01-17', '2025-01-18', 'Mapas'),
  (3, 'Cuarto 5', 'Antonio', '2584', '2025-01-18', '2025-01-21', 'AirB&B'),
  (3, 'Cuarto 5', 'Aldo', '600', '2025-01-21', '2025-01-22', 'Booking'),
  (3, 'Cuarto 5', 'Cinthya', '700', '2025-01-26', '2025-01-27', 'Mapas'),
  (3, 'Cuarto 5', 'Olesia', '3000', '2025-01-27', '2025-02-01', 'AirB&B'),
  (3, 'Cuarto 5', 'Alejandro', '2800', '2026-01-01', '2025-01-04', 'AirB&B'),
  (3, 'Cuarto 6', 'Lorena', NULL, '2025-01-03', '2025-01-06', 'Booking'),
  (3, 'Cuarto 6', 'Uriela', '1000', '2025-01-06', '2025-01-07', 'Mapas'),
  (3, 'Cuarto 6', 'Nicolas', '1000', '2025-01-08', '2025-01-09', 'Mapas'),
  (3, 'Cuarto 6', 'jose', '1100', '2025-01-09', '2025-01-10', 'Mapas'),
  (3, 'Cuarto 6', 'Gerardo', NULL, '2025-01-10', '2025-01-11', 'Mapas'),
  (3, 'Cuarto 6', 'Gerardo', '1000', '2025-01-11', '2025-01-12', 'Mapas'),
  (3, 'Cuarto 6', 'Salome', '5000', '2025-01-15', '2025-01-16', 'AirB&B'),
  (3, 'Cuarto 6', 'Raúl', '600', '2025-01-16', '2025-01-17', 'Booking'),
  (3, 'Cuarto 6', 'Duke', '800', '2025-01-17', '2025-01-18', 'Booking'),
  (3, 'Cuarto 6', 'Diana', '2100', '2025-01-18', '2025-01-21', 'Mapas'),
  (3, 'Cuarto 6', 'Diana', '700', '2025-01-21', '2025-01-22', 'Mapas'),
  (3, 'Cuarto 6', 'Armando', '2000', '2025-01-27', '2025-01-29', 'Mapas'),
  (3, 'Cuarto 6', 'Mon', '700', '2025-01-29', '2025-01-30', 'Mapas'),
  (3, 'Cuarto 6', 'Graham', '1760', '2025-01-30', '2025-02-01', 'Booking'),
  (3, 'Cuarto 6', 'monica', '4000', '2026-01-01', '2025-01-03', 'Booking'),
  (7, 'Cabaña privada', 'Jarka', NULL, '2025-01-06', '2025-02-01', '#ea9999'),
  (7, 'Cabaña privada', 'Mario', NULL, '2026-01-01', '2025-01-06', '#ea9999'),
  (7, 'Cabaña privada', 'Sofia', NULL, '2025-01-03', '2025-02-01', '#ea9999'),
  (7, 'Cabaña privada', 'Jake', NULL, '2026-01-01', '2025-01-03', '#e06666'),
  (7, 'Cabaña privada', 'Sabina', NULL, '2025-01-11', '2025-01-16', '#ea9999'),
  (7, 'Cabaña privada', 'Jared', NULL, '2026-01-01', '2025-01-10', '#ea9999'),
  (7, 'Camping', 'Alberto', NULL, '2026-01-01', '2025-01-03', '#b6d7a8'),
  (7, 'Camping', 'Sabrina', NULL, '2026-01-01', '2025-01-04', '#b6d7a8'),
  (7, 'Cama en dormitorio', 'Ivan', NULL, '2026-01-01', '2026-01-02', '#b6d7a8'),
  (9, 'Habitación 10', 'Minatti', '700', '2025-01-06', '2025-01-07', 'Booking'),
  (9, 'Habitación 10', 'Maayan', '700', '2025-01-09', '2025-01-10', 'AirB&B'),
  (9, 'Habitación 10', 'Rosario', '700', '2025-01-10', '2025-01-11', 'Mapas'),
  (9, 'Habitación 10', 'Eva', '700', '2025-01-13', '2025-01-14', 'Booking'),
  (9, 'Habitación 10', 'Ard', '2064', '2025-01-16', '2025-01-19', 'Booking'),
  (9, 'Habitación 10', 'Feliz', '600', '2025-01-23', '2025-01-24', 'Booking'),
  (9, 'Habitación 10', 'Elisa', '2400', '2025-01-26', '2025-01-30', 'Mapas'),
  (9, 'Habitación 10', 'Jivany', '700', '2025-01-31', '2025-02-01', 'Mapas'),
  (9, 'Habitación 10', 'Julie**', NULL, '2026-01-01', '2025-01-05', '#ea9999'),
  (9, 'Habitación doble', 'Jaime', NULL, '2025-01-04', '2025-01-05', 'Mapas'),
  (9, 'Habitación doble', 'Zury', NULL, '2025-01-08', '2025-01-09', 'Booking'),
  (9, 'Habitación doble', 'Lorena', '2000', '2025-01-10', '2025-01-12', 'Mapas'),
  (9, 'Habitación doble', 'Eduarda', '3500', '2025-01-21', '2025-01-26', 'Mapas'),
  (9, 'Habitación doble', 'Alejandro', '650', '2025-01-27', '2025-01-28', 'Mapas'),
  (9, 'Habitación doble', 'Merlin', '1800', '2025-01-31', '2025-02-01', 'Mapas'),
  (9, 'Habitación doble', 'Elizabeth**', NULL, '2026-01-01', '2025-01-04', 'AirB&B'),
  (9, 'Habitación doble', 'Jaciel', '1300', '2025-01-03', '2025-01-04', 'Mapas'),
  (9, 'Habitación doble', 'Jaime', '1500', '2025-01-04', '2025-01-05', 'Mapas'),
  (9, 'Habitación doble', 'Yuli', '700', '2025-01-05', '2025-01-06', 'Mapas'),
  (9, 'Habitación doble', 'Luz', '650', '2025-01-26', '2025-01-27', 'Mapas'),
  (9, 'Habitación doble', 'Jonathan', '800', '2025-01-31', '2025-02-01', 'Mapas'),
  (9, 'Habitación doble', 'laura**', NULL, '2026-01-01', '2026-01-02', 'Booking'),
  (9, 'Habitación doble', 'Ana', '1200', '2026-01-02', '2026-01-03', 'Mapas'),
  (9, 'Habitación doble', 'Carlos', '1500', '2025-01-04', '2025-01-05', 'Mapas'),
  (9, 'Habitación doble', 'Edith', '2000', '2025-01-10', '2025-01-12', 'Booking'),
  (9, 'Habitación doble', 'Manuel', '450', '2025-01-16', '2025-01-17', 'Mapas'),
  (9, 'Habitación doble', 'Eduardo', '700', '2025-01-17', '2025-01-18', 'Mapas'),
  (9, 'Habitación doble', 'Hernesto', '800', '2025-01-25', '2025-01-26', 'Booking'),
  (9, 'Habitación doble', 'Cayetano', '700', '2025-01-27', '2025-01-28', 'Mapas'),
  (9, 'Habitación doble', 'Cayetano', '700', '2025-01-28', '2025-01-29', 'Mapas'),
  (9, 'Habitación doble', 'Antonione', '800', '2025-01-31', '2025-02-01', 'AirB&B'),
  (9, 'Habitación doble', 'Rafael **', NULL, '2026-01-01', '2025-01-04', 'Mapas'),
  (9, 'Habitación doble', 'Julio', NULL, '2025-01-04', '2025-01-07', 'Booking'),
  (9, 'Habitación doble', 'nancy', NULL, '2025-01-18', '2025-01-19', 'Booking'),
  (9, 'Habitación doble', 'Leonardo', '700', '2025-01-23', '2025-01-24', 'Mapas'),
  (9, 'Habitación doble', 'Diana', '3600', '2025-01-25', '2025-01-29', 'AirB&B'),
  (9, 'Habitación doble', 'Miriam', '2000', '2025-01-31', '2025-02-01', 'Mapas'),
  (9, 'Habitación doble', 'Rafael **', NULL, '2026-01-01', '2025-01-04', 'Mapas'),
  (9, 'Habitación doble', 'Julio', '10000', '2025-01-04', '2025-01-07', 'Booking'),
  (9, 'Habitación doble', 'María mercedes', '4600', '2025-01-07', '2025-01-11', 'AirB&B'),
  (9, 'Habitación doble', 'Miguel', NULL, '2025-01-14', '2025-01-15', 'Booking'),
  (9, 'Habitación doble', 'Manuel', '680', '2025-01-16', '2025-01-17', 'Mapas'),
  (9, 'Habitación doble', 'Vidal', '700', '2025-01-17', '2025-01-18', 'Mapas'),
  (9, 'Habitación doble', 'Iván', '650', '2025-01-21', '2025-01-22', 'Mapas'),
  (9, 'Habitación doble', 'Ivan', '650', '2025-01-22', '2025-01-23', 'Mapas'),
  (9, 'Habitación doble', 'Iván', '650', '2025-01-23', '2025-01-24', 'Mapas'),
  (9, 'Habitación doble', 'Dante', '3300', '2025-01-26', '2025-01-30', 'Booking'),
  (9, 'Habitación doble', 'Dante', '2475', '2025-01-30', '2025-02-01', 'Booking'),
  (9, 'Habitación doble', 'Rafael **', NULL, '2026-01-01', '2025-01-04', 'Mapas'),
  (9, 'Doble con AC', 'Julio', NULL, '2025-01-04', '2025-01-07', 'Booking'),
  (9, 'Doble con AC', 'Minatti', '800', '2025-01-07', '2025-01-08', 'Booking'),
  (9, 'Doble con AC', 'Isaías', '1000', '2025-01-09', '2025-01-10', 'Mapas'),
  (9, 'Doble con AC', 'Jan', '1000', '2025-01-10', '2025-01-11', 'Mapas'),
  (9, 'Doble con AC', 'Vidal', '900', '2025-01-17', '2025-01-18', 'Mapas'),
  (9, 'Doble con AC', 'Feliz', '830', '2025-01-21', '2025-01-22', 'Booking'),
  (9, 'Doble con AC', 'Feliz', '830', '2025-01-22', '2025-01-23', 'Booking'),
  (9, 'Doble con AC', 'Leonardo', '2000', '2025-01-25', '2025-01-27', 'Mapas'),
  (9, 'Doble con AC', 'Gabriel', '1600', '2025-01-29', '2025-01-31', 'Mapas'),
  (9, 'Doble con AC', 'Luis', '700', '2025-01-31', '2025-02-01', 'Mapas'),
  (9, 'Doble con AC', 'Lis', '4500', '2026-01-01', '2025-01-04', 'AirB&B'),
  (9, 'Doble con AC', 'Mildred', '1900', '2025-01-03', '2025-01-04', 'AirB&B'),
  (9, 'Doble con AC', 'Daniel', '1000', '2025-01-04', '2025-01-05', 'Mapas'),
  (9, 'Doble con AC', 'Vicente', '800', '2025-01-05', '2025-01-06', 'Expedia'),
  (9, 'Doble con AC', 'Fabián', '800', '2025-01-07', '2025-01-08', 'Booking'),
  (9, 'Doble con AC', 'José', '1200', '2025-01-08', '2025-01-09', 'Mapas'),
  (9, 'Doble con AC', 'Sergio', '1100', '2025-01-09', '2025-01-10', 'Mapas'),
  (9, 'Doble con AC', 'Sergio', '1100', '2025-01-10', '2025-01-11', 'Mapas'),
  (9, 'Doble con AC', 'Jan', '1000', '2025-01-11', '2025-01-12', 'Mapas'),
  (9, 'Doble con AC', 'Helga', NULL, '2025-01-12', '2025-01-13', 'Booking'),
  (9, 'Doble con AC', 'Karen', '800', '2025-01-17', '2025-01-18', 'Mapas'),
  (9, 'Doble con AC', 'Roberto', '2000', '2025-01-24', '2025-01-26', 'Mapas'),
  (9, 'Doble con AC', 'Tomas', '1000', '2025-01-26', '2025-01-27', 'Mapas'),
  (9, 'Doble con AC', 'Luz', '1000', '2025-01-27', '2025-01-28', 'Mapas'),
  (9, 'Doble con AC', 'Gaby', '1900', '2025-01-31', '2025-02-01', 'AirB&B'),
  (9, 'Doble con AC', 'Chloe **', NULL, '2026-01-01', '2025-01-03', 'Booking'),
  (9, 'Habitación 9', 'Jaime', '1300', '2025-01-04', '2025-01-05', 'Mapas'),
  (9, 'Habitación 9', 'Steven', '700', '2025-01-09', '2025-01-10', 'Expedia'),
  (9, 'Habitación 9', 'Ángela', '700', '2025-01-11', '2025-01-12', 'Mapas'),
  (9, 'Habitación 9', 'Alison', '700', '2025-01-16', '2025-01-17', 'Mapas'),
  (9, 'Habitación 9', 'Iván', '650', '2025-01-21', '2025-01-22', 'Mapas'),
  (9, 'Habitación 9', 'Amado', '650', '2025-01-23', '2025-01-24', 'Mapas'),
  (9, 'Habitación 9', 'María de posada', '600', '2025-01-27', '2025-01-28', 'Booking'),
  (9, 'Habitación 9', 'Alexa', '900', '2025-01-28', '2025-01-29', 'Mapas'),
  (9, 'Habitación 9', 'Menaly', '1300', '2025-01-31', '2025-02-01', 'Mapas'),
  (9, 'Habitación 9', 'Felix **', NULL, '2026-01-01', '2026-01-02', 'Booking'),
  (9, 'Habitación 9', 'Miguel **', NULL, '2026-01-02', '2025-01-04', 'AirB&B'),
  (1, 'Habitación 1', 'Nancy', '700', '2025-01-03', '2025-01-04', 'Booking'),
  (1, 'Habitación 1', 'Christopher', '2600', '2025-01-04', '2025-01-07', 'Booking'),
  (1, 'Habitación 1', 'Brenda', '700', '2025-01-07', '2025-01-08', 'Booking'),
  (1, 'Habitación 1', 'Estrella', '3200', '2025-01-08', '2025-01-12', 'AirB&B'),
  (1, 'Habitación 1', 'Larissa', '2400', '2025-01-13', '2025-01-16', 'Booking'),
  (1, 'Habitación 1', 'Christin', '800', '2025-01-16', '2025-01-17', 'Expedia'),
  (1, 'Habitación 1', 'Julián', '4800', '2025-01-17', '2025-01-23', 'Mapas'),
  (1, 'Habitación 1', 'Marco Emilio', '1600', '2025-01-23', '2025-01-25', 'Mapas'),
  (1, 'Habitación 1', 'Roberta', '700', '2025-01-25', '2025-01-26', 'Booking'),
  (1, 'Habitación 1', 'Blair', '3150', '2025-01-26', '2025-01-31', 'Booking'),
  (1, 'Habitación 1', 'Kennet', '750', '2025-01-31', '2025-02-01', 'Booking'),
  (1, 'Habitación 1', 'Gustavo', '4000', '2026-01-01', '2025-01-03', 'Expedia'),
  (1, 'Habitación 2', 'Alicia', '2200', '2025-01-05', '2025-01-08', 'Booking'),
  (1, 'Habitación 2', 'Alicia', '700', '2025-01-08', '2025-01-09', 'Booking'),
  (1, 'Habitación 2', 'Carlos', '1530', '2025-01-09', '2025-01-11', 'Expedia'),
  (1, 'Habitación 2', 'Miruna', '800', '2025-01-11', '2025-01-12', 'AirB&B'),
  (1, 'Habitación 2', 'Nicoleta', '7500', '2025-01-12', '2025-01-19', 'Booking'),
  (1, 'Habitación 2', 'Emilio', '2500', '2025-01-22', '2025-01-25', 'Booking'),
  (1, 'Habitación 2', 'Emilio', '2500', '2025-01-25', '2025-01-28', 'Booking'),
  (1, 'Habitación 2', 'Reina', '1400', '2025-01-28', '2025-01-30', 'Booking'),
  (1, 'Habitación 2', 'Roberto', '5785', '2025-01-30', '2025-02-01', 'Expedia'),
  (1, 'Habitación 2', 'Daniela', '6500', '2026-01-01', '2025-01-05', 'Expedia'),
  (1, 'Habitación 3', 'Arturo', '4207', '2025-01-09', '2025-01-15', 'AirB&B'),
  (1, 'Habitación 3', 'Pierre', '700', '2025-01-15', '2025-01-16', 'Booking'),
  (1, 'Habitación 3', 'Briselda', '1600', '2025-01-16', '2025-01-17', 'Booking'),
  (1, 'Habitación 3', 'Daw', '800', '2025-01-17', '2025-01-18', 'Booking'),
  (1, 'Habitación 3', 'Gustavo', '700', '2025-01-19', '2025-01-20', 'Mapas'),
  (1, 'Habitación 3', 'Cristina', '700', '2025-01-20', '2025-01-21', 'Booking'),
  (1, 'Habitación 3', 'Gustavo', '1400', '2025-01-22', '2025-01-24', 'Mapas'),
  (1, 'Habitación 3', 'Raul', '3100', '2025-01-27', '2025-01-31', 'Booking'),
  (1, 'Habitación 3', 'Daniel', '2000', '2025-01-31', '2025-02-01', 'Booking'),
  (1, 'Habitación 3', 'Louis', NULL, '2026-01-01', '2026-01-02', 'Expedia'),
  (1, 'Habitación 3', 'Aube', '5900', '2026-01-02', '2025-01-07', 'AirB&B'),
  (1, 'Habitación 4', 'Yuna', '3737', '2025-01-04', '2025-01-09', 'Booking'),
  (1, 'Habitación 4', 'Dilaria', '2100', '2025-01-09', '2025-01-12', 'Booking'),
  (1, 'Habitación 4', 'Señora aurora', NULL, '2025-01-12', '2025-01-13', '#fff2cc'),
  (1, 'Habitación 4', 'José', '1200', '2025-01-14', '2025-01-16', 'Mapas'),
  (1, 'Habitación 4', 'Monica', '700', '2025-01-16', '2025-01-17', 'Mapas'),
  (1, 'Habitación 4', 'Pam', '1400', '2025-01-17', '2025-01-19', 'Mapas'),
  (1, 'Habitación 4', 'José', '6600', '2025-01-19', '2025-01-22', 'Mapas'),
  (1, 'Habitación 4', 'Aidé', '1500', '2025-01-22', '2025-01-24', 'Booking'),
  (1, 'Habitación 4', 'Midori', '2300', '2025-01-24', '2025-01-27', 'Booking'),
  (1, 'Habitación 4', 'Lena', NULL, '2025-01-27', '2025-01-28', 'Booking'),
  (1, 'Habitación 4', 'Merlin', '600', '2025-01-29', '2025-01-30', 'Mapas'),
  (1, 'Habitación 4', 'Clement', '1500', '2025-01-30', '2025-02-01', 'Booking'),
  (1, 'Habitación 4', 'Adriana', NULL, '2026-01-01', '2025-01-04', 'Booking'),
  (1, 'Habitación 5', 'Jesús', '3900', '2025-01-04', '2025-01-09', 'Expedia'),
  (1, 'Habitación 5', 'Jennifer', '5331', '2025-01-09', '2025-01-16', 'Expedia'),
  (1, 'Habitación 5', 'Dulce', '700', '2025-01-16', '2025-01-17', 'AirB&B'),
  (1, 'Habitación 5', 'Jessica', '4100', '2025-01-17', '2025-01-22', 'Booking'),
  (1, 'Habitación 5', 'José', NULL, '2025-01-22', '2025-01-31', 'Mapas'),
  (1, 'Habitación 5', 'Octavio', '1600', '2025-01-31', '2025-02-01', 'AirB&B'),
  (1, 'Habitación 5', 'Adriana', NULL, '2026-01-01', '2025-01-04', 'Booking'),
  (1, 'Habitación 6', 'Gómez', '3200', '2025-01-06', '2025-01-10', 'Mapas'),
  (1, 'Habitación 6', 'Maribel', '4000', '2025-01-10', '2025-01-15', 'Expedia'),
  (1, 'Habitación 6', 'Briselda', NULL, '2025-01-16', '2025-01-17', 'Booking'),
  (1, 'Habitación 6', 'Antonio', '800', '2025-01-17', '2025-01-18', 'Booking'),
  (1, 'Habitación 6', 'Aarón', '1600', '2025-01-18', '2025-01-20', 'Expedia'),
  (1, 'Habitación 6', 'Daniela', '3000', '2025-01-20', '2025-01-24', 'Booking'),
  (1, 'Habitación 6', 'Carlos', '750', '2025-01-24', '2025-01-25', 'Booking'),
  (1, 'Habitación 6', 'María', '750', '2025-01-26', '2025-01-27', 'Booking'),
  (1, 'Habitación 6', 'Daisy', '1400', '2025-01-29', '2025-01-31', 'Booking'),
  (1, 'Habitación 6', 'Antonio', '900', '2025-01-31', '2025-02-01', 'Booking'),
  (1, 'Habitación 6', 'Abril', '5500', '2026-01-01', '2025-01-06', 'Booking'),
  (1, 'Habitación 7', 'Jose', '4900', '2025-01-05', '2025-01-12', 'Mapas'),
  (1, 'Habitación 7', 'José', '1400', '2025-01-12', '2025-01-14', 'Mapas'),
  (1, 'Habitación 7', 'Chiara', '850', '2025-01-14', '2025-01-15', 'Booking'),
  (1, 'Habitación 7', 'Miguel Ángel', '2400', '2025-01-16', '2025-01-19', 'Mapas'),
  (1, 'Habitación 7', 'Alberede', '1400', '2025-01-19', '2025-01-21', 'Booking'),
  (1, 'Habitación 7', 'Amber', '5090', '2025-01-21', '2025-01-28', 'Booking'),
  (1, 'Habitación 7', 'Lena', '3200', '2025-01-28', '2025-01-31', 'Booking'),
  (1, 'Habitación 7', 'Maria Magdalena', '1600', '2025-01-31', '2025-02-01', 'Mapas'),
  (1, 'Habitación 7', 'Isarel', NULL, '2026-01-01', '2026-01-02', 'Expedia'),
  (1, 'Habitación 7', 'Eliot', '2400', '2026-01-02', '2025-01-04', 'Mapas'),
  (6, 'Depa 1', 'Heriberta', '3000', '2026-02-01', '2026-02-03', 'Booking'),
  (6, 'Depa 1', 'Mitzi', '1300', '2026-02-03', '2026-02-04', 'AirB&B'),
  (6, 'Depa 1', 'Luis', '4541', '2026-02-04', '2026-02-07', 'AirB&B'),
  (6, 'Depa 1', 'Mario', '3000', '2026-02-07', '2026-02-09', 'Booking'),
  (6, 'Depa 1', 'Camille', '5400', '2026-02-09', '2026-02-13', 'AirB&B'),
  (6, 'Depa 1', 'Dennis', '6000', '2026-02-13', '2026-02-17', 'Booking'),
  (6, 'Depa 1', 'Luisa', '7700', '2026-02-19', '2026-02-24', 'AirB&B'),
  (6, 'Depa 1', 'Marisol', '2137', '2026-02-24', '2026-02-26', 'AirB&B'),
  (6, 'Depa 1', 'Bloqueado Kike', NULL, '2026-02-27', '2026-03-03', '#ffe599'),
  (6, 'Depa 1', 'Szabolcs', NULL, '2026-03-03', '2026-03-04', 'AirB&B'),
  (6, 'Depa 2', 'Idania', NULL, '2026-02-01', '2026-02-02', 'Booking'),
  (6, 'Depa 2', 'Idania', '1400', '2026-02-02', '2026-02-03', 'Booking'),
  (6, 'Depa 2', 'Oscar', '6300', '2026-02-05', '2026-02-09', 'AirB&B'),
  (6, 'Depa 2', 'Saúl (pago AirB&B, faltan', '400', '2026-02-09', '2026-02-11', 'AirB&B'),
  (6, 'Depa 2', 'Andrea', '6800', '2026-02-11', '2026-02-15', 'AirB&B'),
  (6, 'Depa 2', 'Valentin', '2942', '2026-02-15', '2026-02-17', 'AirB&B'),
  (6, 'Depa 2', 'Yoselin', '6400', '2026-02-19', '2026-02-24', 'AirB&B'),
  (6, 'Depa 2', 'Samantha', '7000', '2026-02-24', '2026-02-28', 'AirB&B'),
  (6, 'Depa 2', 'Fracisco', NULL, '2026-02-28', '2026-03-04', 'AirB&B'),
  (5, 'Caoba', 'Raúl', NULL, '2026-02-01', '2026-02-06', 'Booking'),
  (5, 'Caoba', 'Lauren', '1700', '2026-02-08', '2026-02-11', 'AirB&B'),
  (5, 'Caoba', 'Rommel', '700', '2026-02-12', '2026-02-13', 'Booking'),
  (5, 'Caoba', 'Juan Carlos', '700', '2026-02-14', '2026-02-15', 'AirB&B'),
  (5, 'Caoba', 'Michael', '620', '2026-02-15', '2026-02-16', 'Booking'),
  (5, 'Caoba', 'David', '700', '2026-02-22', '2026-02-23', 'AirB&B'),
  (5, 'Caoba', 'Jaime', '4200', '2026-02-23', '2026-03-02', 'AirB&B'),
  (5, 'Guaje', 'Keila', '500', '2026-02-04', '2026-02-05', 'AirB&B'),
  (5, 'Guaje', 'Raúl', '2100', '2026-02-05', '2026-02-08', 'AirB&B'),
  (5, 'Guaje', 'Celeste', '1600', '2026-02-14', '2026-02-17', 'AirB&B'),
  (5, 'Guaje', 'Lidia', '600', '2026-02-22', '2026-02-23', 'Mapas'),
  (5, 'Guaje', 'Rena', '5400', '2026-02-24', '2026-03-04', 'Expedia'),
  (5, 'Macuil', 'Eric', NULL, '2026-02-01', '2026-02-02', 'AirB&B'),
  (5, 'Macuil', 'Van', '1600', '2026-02-14', '2026-02-17', 'AirB&B'),
  (5, 'Macuil', 'Van', '1407', '2026-02-17', '2026-02-19', 'AirB&B'),
  (5, 'Macuil', 'Castillo Bautista', NULL, '2026-02-27', '2026-03-01', 'AirB&B'),
  (5, 'Parota', 'Claude', '600', '2026-02-02', '2026-02-03', 'Booking'),
  (5, 'Parota', 'Gunda', '900', '2026-02-09', '2026-02-11', 'AirB&B'),
  (5, 'Parota', 'Marine', '1000', '2026-02-13', '2026-02-15', 'AirB&B'),
  (5, 'Parota', 'Julia', '550', '2026-02-16', '2026-02-17', 'AirB&B'),
  (5, 'Parota', 'Julia', '550', '2026-02-17', '2026-02-18', 'AirB&B'),
  (5, 'Parota', 'Diego', '600', '2026-02-22', '2026-02-23', 'AirB&B'),
  (5, 'Parota', 'Celeste', '550', '2026-02-23', '2026-02-24', 'Mapas'),
  (5, 'Parota', 'Julia', '550', '2026-02-25', '2026-02-26', 'Mapas'),
  (5, 'Parota', 'Gunda', '500', '2026-02-26', '2026-02-27', 'AirB&B'),
  (4, 'Depa 1', 'Ximena', '900', '2026-02-07', '2026-02-08', 'Mapas'),
  (4, 'Depa 1', 'Rick', '800', '2026-02-14', '2026-02-15', 'Booking'),
  (4, 'Depa 1', 'Janet', '700', '2026-02-16', '2026-02-17', 'Mapas'),
  (4, 'Depa 1', 'Jorge', '3000', '2026-02-20', '2026-02-23', 'Mapas'),
  (4, 'Depa 1', 'Jorge', '2000', '2026-02-23', '2026-02-25', 'Mapas'),
  (4, 'Depa 2', 'Janet', NULL, '2026-02-01', '2026-02-12', 'AirB&B'),
  (4, 'Depa 2', 'Maritza', '2700', '2026-02-14', '2026-02-17', 'AirB&B'),
  (4, 'Depa 2', 'Carlos', '2100', '2026-02-20', '2026-02-23', 'AirB&B'),
  (3, 'Cuarto 1', 'Alexander', '857', '2026-02-01', '2026-02-02', 'Booking'),
  (3, 'Cuarto 1', 'María', NULL, '2026-02-02', '2026-02-04', 'Booking'),
  (3, 'Cuarto 1', 'Evelyn', '700', '2026-02-04', '2026-02-05', 'Mapas'),
  (3, 'Cuarto 1', 'Sonia', '2200', '2026-02-07', '2026-02-09', 'AirB&B'),
  (3, 'Cuarto 1', 'Sofía', '700', '2026-02-10', '2026-02-11', 'Mapas'),
  (3, 'Cuarto 1', 'Margarita', '700', '2026-02-12', '2026-02-13', 'Mapas'),
  (3, 'Cuarto 1', 'Cesar', '1800', '2026-02-13', '2026-02-15', 'Mapas'),
  (3, 'Cuarto 1', 'Xime', '2070', '2026-02-17', '2026-02-20', 'AirB&B'),
  (3, 'Cuarto 1', 'Ximena', '3863', '2026-02-20', '2026-02-24', 'AirB&B'),
  (3, 'Cuarto 1', 'Robin', '700', '2026-02-24', '2026-02-25', 'AirB&B'),
  (3, 'Cuarto 1', 'Liz Santos', '2700', '2026-02-28', '2026-03-03', 'AirB&B'),
  (3, 'Cuarto 2', 'Mariela', '714', '2026-02-01', '2026-02-02', 'AirB&B'),
  (3, 'Cuarto 2', 'Francesco', '5480', '2026-02-02', '2026-02-09', 'AirB&B'),
  (3, 'Cuarto 2', 'David', '500', '2026-02-09', '2026-02-10', 'Booking'),
  (3, 'Cuarto 2', 'Anja', '600', '2026-02-10', '2026-02-11', 'Mapas'),
  (3, 'Cuarto 2', 'Kevin salgado', '650', '2026-02-11', '2026-02-12', 'Mapas'),
  (3, 'Cuarto 2', 'Bryan', '600', '2026-02-13', '2026-02-14', 'Mapas'),
  (3, 'Cuarto 2', 'Mara', '1500', '2026-02-14', '2026-02-16', 'Booking'),
  (3, 'Cuarto 2', 'Eduardo', '1200', '2026-02-17', '2026-02-19', 'Mapas'),
  (3, 'Cuarto 2', 'Saffron', '2100', '2026-02-20', '2026-02-23', 'AirB&B'),
  (3, 'Cuarto 2', 'Saffron', '700', '2026-02-23', '2026-02-24', 'AirB&B'),
  (3, 'Cuarto 2', 'Guadalupe', '1600', '2026-02-24', '2026-02-26', 'Mapas'),
  (3, 'Cuarto 2', 'Sergio', '700', '2026-02-26', '2026-02-27', 'Mapas'),
  (3, 'Cuarto 3', 'Chong', '3050', '2026-02-01', '2026-02-06', 'Booking'),
  (3, 'Cuarto 3', 'Didier', '2400', '2026-02-07', '2026-02-10', 'Mapas'),
  (3, 'Cuarto 3', 'Kevin', '2800', '2026-02-11', '2026-02-15', 'Mapas'),
  (3, 'Cuarto 3', 'Citlalli', '1100', '2026-02-17', '2026-02-18', 'Mapas'),
  (3, 'Cuarto 3', 'Elsa', '800', '2026-02-21', '2026-02-22', 'Mapas'),
  (3, 'Cuarto 3', 'GAirB&By', '2800', '2026-02-22', '2026-02-26', 'Mapas'),
  (3, 'Cuarto 4', 'María', '2040', '2026-02-01', '2026-02-02', 'Booking'),
  (3, 'Cuarto 4', 'ALEX', '4500', '2026-02-02', '2026-02-09', 'Booking'),
  (3, 'Cuarto 4', 'Jannet Garcia', '1400', '2026-02-10', '2026-02-12', 'AirB&B'),
  (3, 'Cuarto 4', 'Eduardo', '2000', '2026-02-13', '2026-02-15', 'Mapas'),
  (3, 'Cuarto 4', 'Peter', '1400', '2026-02-17', '2026-02-19', 'Booking'),
  (3, 'Cuarto 4', 'Lidia', '800', '2026-02-21', '2026-02-22', 'Mapas'),
  (3, 'Cuarto 4', 'Jonna', '700', '2026-02-22', '2026-02-23', 'Booking'),
  (3, 'Cuarto 4', 'David', '1400', '2026-02-23', '2026-02-25', 'AirB&B'),
  (3, 'Cuarto 4', 'David', '2100', '2026-02-25', '2026-02-28', 'Booking'),
  (3, 'Cuarto 5', 'Olesia', '2400', '2026-02-01', '2026-02-05', 'AirB&B'),
  (3, 'Cuarto 5', 'Markle', '4000', '2026-02-05', '2026-02-08', 'Booking'),
  (3, 'Cuarto 5', 'Dulce', '800', '2026-02-08', '2026-02-09', 'Mapas'),
  (3, 'Cuarto 5', 'Alejandro', '1500', '2026-02-09', '2026-02-11', 'Mapas'),
  (3, 'Cuarto 5', 'Daniel', '2400', '2026-02-11', '2026-02-14', 'Mapas'),
  (3, 'Cuarto 5', 'Ery resta', '2000', '2026-02-14', '2026-02-17', 'AirB&B'),
  (3, 'Cuarto 5', 'Erika extensión', '500', '2026-02-17', '2026-02-18', 'AirB&B'),
  (3, 'Cuarto 5', 'Miguel', '1800', '2026-02-18', '2026-02-21', 'AirB&B'),
  (3, 'Cuarto 5', 'José', '1500', '2026-02-21', '2026-02-23', 'AirB&B'),
  (3, 'Cuarto 5', 'Gustavo', '2500', '2026-02-23', '2026-02-27', 'Booking'),
  (3, 'Cuarto 6', 'Graham', '7040', '2026-02-01', '2026-02-09', 'Booking'),
  (3, 'Cuarto 6', 'Sara', '5727', '2026-02-10', '2026-02-18', 'AirB&B'),
  (3, 'Cuarto 6', 'Edgar', '3559', '2026-02-18', '2026-02-22', 'AirB&B'),
  (3, 'Cuarto 6', 'Edgar', '950', '2026-02-22', '2026-02-23', 'AirB&B'),
  (3, 'Cuarto 6', 'Edgar', '950', '2026-02-23', '2026-02-24', 'AirB&B'),
  (7, 'Camping', 'Miriam checar', NULL, '2026-02-10', '2026-02-11', '#ffffff'),
  (9, 'Habitación 10', 'Yovani', '400', '2026-02-01', '2026-02-02', 'Mapas'),
  (9, 'Habitación 10', 'Elad', '600', '2026-02-02', '2026-02-03', 'Mapas'),
  (9, 'Habitación 10', 'Dulce', '1300', '2026-02-03', '2026-02-05', 'Booking'),
  (9, 'Habitación 10', 'Abner', '600', '2026-02-10', '2026-02-11', 'AirB&B'),
  (9, 'Habitación 10', 'Ezequiel', '600', '2026-02-11', '2026-02-12', 'Mapas'),
  (9, 'Habitación 10', 'Erika Victor', '1200', '2026-02-12', '2026-02-14', '#ea9999'),
  (9, 'Habitación 10', 'Rosaisela', '1800', '2026-02-23', '2026-02-25', 'Mapas'),
  (9, 'Habitación doble', 'Merlin', NULL, '2026-02-01', '2026-02-03', 'Mapas'),
  (9, 'Habitación doble', 'Jonathan', '600', '2026-02-13', '2026-02-14', 'Mapas'),
  (9, 'Habitación doble', 'Alejandro', '600', '2026-02-16', '2026-02-17', 'Mapas'),
  (9, 'Habitación doble', 'Eduard', NULL, '2026-02-23', '2026-02-27', 'Booking'),
  (9, 'Habitación doble', 'Miriam', NULL, '2026-02-01', '2026-02-03', 'Mapas'),
  (9, 'Habitación doble', 'Miriam', '3000', '2026-02-03', '2026-02-08', 'Mapas'),
  (9, 'Habitación doble', 'Miriam', '3000', '2026-02-08', '2026-02-13', 'Mapas'),
  (9, 'Habitación doble', 'Félix', '1200', '2026-02-01', '2026-02-03', 'Mapas'),
  (9, 'Habitación doble', 'Leydy', '700', '2026-02-01', '2026-02-02', 'Mapas'),
  (9, 'Habitación doble', 'Corine', '1500', '2026-02-05', '2026-02-07', 'Mapas'),
  (9, 'Habitación doble', 'Ivan', '700', '2026-02-14', '2026-02-15', 'AirB&B'),
  (9, 'Habitación doble', 'Juan Carlos', NULL, '2026-02-28', '2026-03-04', 'Expedia'),
  (9, 'Habitación doble', 'Dante', NULL, '2026-02-01', '2026-02-02', 'Booking'),
  (9, 'Habitación doble', 'Gustavo', '1000', '2026-02-14', '2026-02-15', 'Mapas'),
  (9, 'Habitación doble', 'Rosasiela', '1800', '2026-02-23', '2026-02-25', 'Mapas'),
  (9, 'Doble con AC', 'Laura', '2100', '2026-02-01', '2026-02-04', 'Booking'),
  (9, 'Doble con AC', 'Erick', '1800', '2026-02-04', '2026-02-06', 'Expedia'),
  (9, 'Doble con AC', 'Carlos', '2813', '2026-02-06', '2026-02-09', 'AirB&B'),
  (9, 'Doble con AC', 'Julia', '800', '2026-02-13', '2026-02-14', 'Mapas'),
  (9, 'Doble con AC', 'Annika', NULL, '2026-02-14', '2026-02-17', '#ea9999'),
  (9, 'Doble con AC', 'Romo', '2100', '2026-02-21', '2026-02-26', 'Mapas'),
  (9, 'Doble con AC', 'Gaby', NULL, '2026-02-01', '2026-02-02', 'AirB&B'),
  (9, 'Doble con AC', 'Erick', '1800', '2026-02-04', '2026-02-06', 'Expedia'),
  (9, 'Doble con AC', 'Ezequiel', '800', '2026-02-09', '2026-02-10', 'Mapas'),
  (9, 'Doble con AC', 'Ezequiel', '800', '2026-02-10', '2026-02-11', 'Mapas'),
  (9, 'Doble con AC', 'Sam', '1900', '2026-02-13', '2026-02-15', 'Booking'),
  (9, 'Doble con AC', 'Alan', '2400', '2026-02-19', '2026-02-22', 'AirB&B'),
  (9, 'Doble con AC', 'Eliseo', '1000', '2026-02-23', '2026-02-24', 'Mapas'),
  (9, 'Habitación 9', 'Melany', NULL, '2026-02-01', '2026-02-02', 'Mapas'),
  (9, 'Habitación 9', 'Capucini', NULL, '2026-02-09', '2026-02-12', 'Booking'),
  (9, 'Habitación 9', 'Julia', '500', '2026-02-13', '2026-02-14', '#ea9999'),
  (9, 'Habitación 9', 'Saraí', '600', '2026-02-14', '2026-02-15', 'Mapas'),
  (9, 'Habitación 9', 'Alejandro', '600', '2026-02-15', '2026-02-16', 'Mapas'),
  (9, 'Habitación 9', 'Mirza', '1800', '2026-02-20', '2026-02-23', 'Mapas'),
  (9, 'Habitación 9', 'Eduard', '5066', '2026-02-23', '2026-02-27', 'Booking'),
  (1, 'Habitación 1', 'Alex', NULL, '2026-02-01', '2026-02-06', 'Booking'),
  (1, 'Habitación 1', 'Munsilf', '3073', '2026-02-07', '2026-02-11', 'AirB&B'),
  (1, 'Habitación 1', 'Or', '800', '2026-02-14', '2026-02-15', 'Booking'),
  (1, 'Habitación 1', 'Arrieta', NULL, '2026-02-16', '2026-02-17', 'Booking'),
  (1, 'Habitación 1', 'Emmanuel', '800', '2026-02-17', '2026-02-18', 'Mapas'),
  (1, 'Habitación 1', 'Carlos', '3600', '2026-02-18', '2026-02-23', 'Booking'),
  (1, 'Habitación 1', 'Carlos', '1600', '2026-02-23', '2026-02-25', 'Booking'),
  (1, 'Habitación 1', 'Melissa', '700', '2026-02-25', '2026-02-26', 'Booking'),
  (1, 'Habitación 2', 'Roberto', NULL, '2026-02-01', '2026-02-02', 'Expedia'),
  (1, 'Habitación 2', 'Christian', '2600', '2026-02-02', '2026-02-03', 'Booking'),
  (1, 'Habitación 2', 'Ziva', '750', '2026-02-04', '2026-02-05', 'Booking'),
  (1, 'Habitación 2', 'Cameron', '700', '2026-02-08', '2026-02-09', 'AirB&B'),
  (1, 'Habitación 2', 'Julie Hirtz', '3053', '2026-02-10', '2026-02-14', 'AirB&B'),
  (1, 'Habitación 2', 'Laura', '1440', '2026-02-14', '2026-02-16', 'Booking'),
  (1, 'Habitación 2', 'jose', '700', '2026-02-16', '2026-02-17', 'Booking'),
  (1, 'Habitación 2', 'Eliza', '700', '2026-02-18', '2026-02-19', 'Booking'),
  (1, 'Habitación 2', 'Martín', '1500', '2026-02-19', '2026-02-21', 'Booking'),
  (1, 'Habitación 2', 'Kevin', '1400', '2026-02-21', '2026-02-23', 'Mapas'),
  (1, 'Habitación 2', 'Kevin', '700', '2026-02-23', '2026-02-24', 'Mapas'),
  (1, 'Habitación 2', 'Alberto', '1400', '2026-02-24', '2026-02-25', 'Booking'),
  (1, 'Habitación 2', 'Nico', NULL, '2026-02-25', '2026-02-26', 'Booking'),
  (1, 'Habitación 2', 'Kennet', NULL, '2026-02-28', '2026-03-02', 'Booking'),
  (1, 'Habitación 3', 'Daniel', NULL, '2026-02-01', '2026-02-02', 'Booking'),
  (1, 'Habitación 3', 'Roberto', NULL, '2026-02-02', '2026-02-07', 'Expedia'),
  (1, 'Habitación 3', 'Arreba', NULL, '2026-02-07', '2026-02-08', 'Booking'),
  (1, 'Habitación 3', 'Garza Juanita checar', NULL, '2026-02-10', '2026-02-11', '#fff2cc'),
  (1, 'Habitación 3', 'Bert', '1321', '2026-02-12', '2026-02-14', 'Booking'),
  (1, 'Habitación 3', 'Munsfin', '2250', '2026-02-14', '2026-02-17', 'AirB&B'),
  (1, 'Habitación 3', 'Munsfin', '750', '2026-02-17', '2026-02-18', 'AirB&B'),
  (1, 'Habitación 3', 'Claudia', '700', '2026-02-18', '2026-02-19', 'AirB&B'),
  (1, 'Habitación 3', 'Diana', '2400', '2026-02-20', '2026-02-23', 'AirB&B'),
  (1, 'Habitación 3', 'Martín', '750', '2026-02-23', '2026-02-24', 'Mapas'),
  (1, 'Habitación 3', 'Enric del #', '6', '2026-02-24', '2026-02-26', 'Booking'),
  (1, 'Habitación 4', 'simon', '1600', '2026-02-01', '2026-02-03', 'Booking'),
  (1, 'Habitación 4', 'César', '2400', '2026-02-06', '2026-02-09', 'Mapas'),
  (1, 'Habitación 4', 'Thomas', '700', '2026-02-10', '2026-02-11', 'Booking'),
  (1, 'Habitación 4', 'Alejandro', '700', '2026-02-11', '2026-02-12', 'Booking'),
  (1, 'Habitación 4', 'Arturo', '2500', '2026-02-12', '2026-02-15', 'Booking'),
  (1, 'Habitación 4', 'Christian', '1400', '2026-02-15', '2026-02-17', 'Booking'),
  (1, 'Habitación 4', 'Daniel', '650', '2026-02-17', '2026-02-18', 'Mapas'),
  (1, 'Habitación 4', 'Matthew', NULL, '2026-02-18', '2026-02-19', 'Booking'),
  (1, 'Habitación 4', 'Pedro', '2025', '2026-02-20', '2026-02-23', 'Booking'),
  (1, 'Habitación 4', 'Leonor', '650', '2026-02-24', '2026-02-25', 'Booking'),
  (1, 'Habitación 4', 'Luc', NULL, '2026-02-25', '2026-02-26', 'Booking'),
  (1, 'Habitación 4', 'Cecilia', NULL, '2026-02-27', '2026-02-28', 'Booking'),
  (1, 'Habitación 5', 'Octavio', NULL, '2026-02-01', '2026-02-02', 'AirB&B'),
  (1, 'Habitación 5', 'Alfred', '1600', '2026-02-02', '2026-02-04', 'Booking'),
  (1, 'Habitación 5', 'Nicholas', '1870', '2026-02-04', '2026-02-07', 'Booking'),
  (1, 'Habitación 5', 'Fabián Nicola', '3200', '2026-02-07', '2026-02-11', 'Booking'),
  (1, 'Habitación 5', 'Munsfin', '2040', '2026-02-11', '2026-02-14', 'AirB&B'),
  (1, 'Habitación 5', 'Rijsbergen Sharon', '1250', '2026-02-14', '2026-02-16', 'Booking'),
  (1, 'Habitación 5', 'Carolina', NULL, '2026-02-16', '2026-02-17', 'Booking'),
  (1, 'Habitación 5', 'Niell', '670', '2026-02-17', '2026-02-18', 'Booking'),
  (1, 'Habitación 5', 'Ricardo', '2025', '2026-02-18', '2026-02-21', 'Booking'),
  (1, 'Habitación 5', 'Thomas', '630', '2026-02-22', '2026-02-23', 'Booking'),
  (1, 'Habitación 5', 'Jonna', '650', '2026-02-23', '2026-02-24', 'Booking'),
  (1, 'Habitación 5', 'ORIA & MICHAEL (', '16650', '2026-02-24', '2026-03-04', 'AirB&B'),
  (1, 'Habitación 6', 'Mattew', '4700', '2026-02-01', '2026-02-07', 'Booking'),
  (1, 'Habitación 6', 'Nina', '800', '2026-02-07', '2026-02-08', 'Mapas'),
  (1, 'Habitación 6', 'Richard', '3100', '2026-02-09', '2026-02-13', 'Expedia'),
  (1, 'Habitación 6', 'Luis', '1115', '2026-02-13', '2026-02-15', 'Booking'),
  (1, 'Habitación 6', 'Melisa Acosta', '2100', '2026-02-17', '2026-02-20', 'Mapas'),
  (1, 'Habitación 6', 'Jose manuel', '700', '2026-02-21', '2026-02-22', 'Mapas'),
  (1, 'Habitación 6', 'Enric', NULL, '2026-02-22', '2026-02-24', 'Booking'),
  (1, 'Habitación 6', 'Pedro', '700', '2026-02-24', '2026-02-25', 'Booking'),
  (1, 'Habitación 6', 'Alberto del #', '2', '2026-02-25', '2026-02-26', 'Booking'),
  (1, 'Habitación 6', 'Cecilia', NULL, '2026-02-27', '2026-02-28', 'Booking'),
  (1, 'Habitación 7', 'Maria magdalena', NULL, '2026-02-01', '2026-02-02', 'Mapas'),
  (1, 'Habitación 7', 'Christian', NULL, '2026-02-03', '2026-02-05', 'Booking'),
  (1, 'Habitación 7', 'Luis', '1000', '2026-02-06', '2026-02-07', 'Mapas'),
  (1, 'Habitación 7', 'Noel Mineli', '1400', '2026-02-07', '2026-02-09', 'Mapas'),
  (1, 'Habitación 7', 'Daniel', '7700', '2026-02-09', '2026-02-20', 'AirB&B'),
  (1, 'Habitación 7', 'Eric', '6000', '2026-02-22', '2026-02-26', 'Booking'),
  (1, 'Habitación 7', 'Marcos', NULL, '2026-02-26', '2026-02-27', 'Booking'),
  (3, 'Cuarto 1', 'Santos Liz', NULL, '2026-03-01', '2026-03-03', 'AirB&B'),
  (3, 'Cuarto 1', 'Samuel', NULL, '2026-03-03', '2026-03-05', 'AirB&B'),
  (3, 'Cuarto 1', 'Cutberto', '2200', '2026-03-06', '2026-03-08', '#ea9999'),
  (3, 'Cuarto 2', 'Mariana', '2400', '2026-03-07', '2026-03-10', 'Mapas'),
  (3, 'Cuarto 6', 'Alejandra', NULL, '2026-03-03', '2026-03-04', 'AirB&B'),
  (3, 'Cuarto 6', 'Pablo', '2870', '2026-03-29', '2026-04-01', 'AirB&B'),
  (9, 'Doble con AC', 'Mariana Airbnb', '18000', '2026-03-02', '2026-04-01', 'AirB&B'),
  (1, 'Habitación 3', 'Liliana', '1600', '2026-03-03', '2026-03-05', 'Mapas'),
  (1, 'Habitación 3', 'Fernanda', '3200', '2026-03-06', '2026-03-10', 'AirB&B'),
  (1, 'Habitación 3', 'Judith', '2400', '2026-03-11', '2026-03-14', 'Mapas'),
  (1, 'Habitación 5', 'ORIA & MICHAEL', NULL, '2026-03-01', '2026-03-18', 'AirB&B'),
  (1, 'Habitación 7', 'Leyber', '1400', '2026-03-14', '2026-03-16', 'Mapas')
;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_import_reservations_one_paste $$
CREATE PROCEDURE sp_import_reservations_one_paste ()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_id_import BIGINT;
  DECLARE v_id_property BIGINT;
  DECLARE v_room_name VARCHAR(255);
  DECLARE v_guest_name VARCHAR(255);
  DECLARE v_amount_raw VARCHAR(64);
  DECLARE v_check_in_raw VARCHAR(20);
  DECLARE v_check_out_raw VARCHAR(20);
  DECLARE v_origin_raw VARCHAR(120);

  DECLARE v_check_in DATE;
  DECLARE v_check_out DATE;
  DECLARE v_amount_decimal DECIMAL(12,2);
  DECLARE v_id_company BIGINT;
  DECLARE v_id_user BIGINT;
  DECLARE v_id_room BIGINT;
  DECLARE v_id_category BIGINT;
  DECLARE v_id_guest BIGINT;
  DECLARE v_id_ota_account BIGINT;
  DECLARE v_id_reservation_source BIGINT;
  DECLARE v_source_name VARCHAR(120);
  DECLARE v_overlap_cnt INT DEFAULT 0;
  DECLARE v_block_overlap_cnt INT DEFAULT 0;
  DECLARE v_code VARCHAR(120);
  DECLARE v_new_reservation_id BIGINT;
  DECLARE v_note_text TEXT;
  DECLARE v_reason VARCHAR(500);
  DECLARE v_status VARCHAR(16);
  DECLARE v_origin_norm VARCHAR(120);
  DECLARE v_input_rows INT DEFAULT 0;

  DECLARE v_sql_error TINYINT DEFAULT 0;
  DECLARE v_sql_message TEXT DEFAULT '';

  DECLARE cur CURSOR FOR
    SELECT id_import, id_property, TRIM(room_name), TRIM(guest_name), TRIM(COALESCE(amount_raw,'')),
           TRIM(check_in_raw), TRIM(check_out_raw), TRIM(origin_raw)
    FROM tmp_reservation_import_input
    ORDER BY id_import;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
  BEGIN
    SET v_sql_error = 1;
    GET DIAGNOSTICS CONDITION 1 v_sql_message = MESSAGE_TEXT;
  END;

  TRUNCATE TABLE tmp_reservation_import_log;

  SELECT COUNT(*) INTO v_input_rows
  FROM tmp_reservation_import_input;

  IF v_input_rows = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'tmp_reservation_import_input esta vacia. No hay filas para importar.';
  END IF;

  OPEN cur;
  read_loop: LOOP
    /* Evita que NOT FOUND de SELECT ... INTO corte el cursor */
    SET done = 0;
    FETCH cur INTO v_id_import, v_id_property, v_room_name, v_guest_name, v_amount_raw, v_check_in_raw, v_check_out_raw, v_origin_raw;
    IF done = 1 THEN
      LEAVE read_loop;
    END IF;

    SET v_reason = NULL;
    SET v_status = 'inserted';
    SET v_sql_error = 0;
    SET v_sql_message = '';
    SET v_new_reservation_id = NULL;
    SET v_code = NULL;
    SET v_id_ota_account = NULL;
    SET v_id_reservation_source = NULL;
    SET v_source_name = NULL;

    row_proc: BEGIN
      SET v_check_in = STR_TO_DATE(v_check_in_raw, '%Y-%m-%d');
      SET v_check_out = STR_TO_DATE(v_check_out_raw, '%Y-%m-%d');
      IF v_check_in IS NULL OR v_check_out IS NULL OR v_check_out <= v_check_in THEN
        SET v_reason = CONCAT('fechas invalidas: ', v_check_in_raw, ' -> ', v_check_out_raw);
        LEAVE row_proc;
      END IF;

      IF v_amount_raw <> '' AND REPLACE(v_amount_raw, ',', '') REGEXP '^-?[0-9]+(\\.[0-9]+)?$' THEN
        SET v_amount_decimal = CAST(REPLACE(v_amount_raw, ',', '') AS DECIMAL(12,2));
      ELSE
        SET v_amount_decimal = NULL;
      END IF;

      SELECT p.id_company INTO v_id_company
      FROM property p
      WHERE p.id_property = v_id_property
        AND p.deleted_at IS NULL
      LIMIT 1;
      IF v_id_company IS NULL THEN
        SET v_reason = 'propiedad no existe';
        LEAVE row_proc;
      END IF;

      SELECT u.id_user INTO v_id_user
      FROM app_user u
      WHERE u.id_company = v_id_company
        AND u.deleted_at IS NULL
        AND COALESCE(u.is_active,1)=1
      ORDER BY COALESCE(u.is_owner,0) DESC, u.id_user
      LIMIT 1;
      IF v_id_user IS NULL THEN
        SET v_reason = 'sin usuario activo para empresa';
        LEAVE row_proc;
      END IF;

      SELECT r.id_room, r.id_category
        INTO v_id_room, v_id_category
      FROM room r
      WHERE r.id_property = v_id_property
        AND r.deleted_at IS NULL
        AND COALESCE(r.is_active,1)=1
        AND (
          TRIM(COALESCE(r.name,'')) COLLATE utf8mb4_unicode_ci = v_room_name COLLATE utf8mb4_unicode_ci
          OR TRIM(COALESCE(r.code,'')) COLLATE utf8mb4_unicode_ci = v_room_name COLLATE utf8mb4_unicode_ci
        )
      ORDER BY
        CASE WHEN TRIM(COALESCE(r.name,'')) COLLATE utf8mb4_unicode_ci = v_room_name COLLATE utf8mb4_unicode_ci THEN 0 ELSE 1 END,
        r.id_room
      LIMIT 1;
      IF v_id_room IS NULL THEN
        SET v_reason = CONCAT('habitacion no encontrada: ', v_room_name);
        LEAVE row_proc;
      END IF;

      INSERT INTO guest (id_user, names, language, is_active, created_at, created_by, updated_at)
      VALUES (v_id_user, v_guest_name, 'es', 1, NOW(), v_id_user, NOW());
      SET v_id_guest = LAST_INSERT_ID();

      SET v_origin_norm = LOWER(REPLACE(REPLACE(TRIM(COALESCE(v_origin_raw,'')), '&', ''), ' ', ''));
      IF v_origin_norm IN ('airbnb','airbb') THEN
        SELECT oa.id_ota_account INTO v_id_ota_account
        FROM ota_account oa
        WHERE oa.id_company = v_id_company
          AND oa.id_property = v_id_property
          AND oa.deleted_at IS NULL
          AND oa.is_active = 1
          AND (LOWER(TRIM(COALESCE(oa.platform,'')))='airbnb' OR LOWER(TRIM(COALESCE(oa.ota_name,''))) LIKE '%airbnb%')
        ORDER BY oa.id_ota_account LIMIT 1;
        SET v_source_name = 'Airbnb';
      ELSEIF v_origin_norm = 'booking' THEN
        SELECT oa.id_ota_account INTO v_id_ota_account
        FROM ota_account oa
        WHERE oa.id_company = v_id_company
          AND oa.id_property = v_id_property
          AND oa.deleted_at IS NULL
          AND oa.is_active = 1
          AND (LOWER(TRIM(COALESCE(oa.platform,'')))='booking' OR LOWER(TRIM(COALESCE(oa.ota_name,''))) LIKE '%booking%')
        ORDER BY oa.id_ota_account LIMIT 1;
        SET v_source_name = 'Booking';
      ELSEIF v_origin_norm = 'expedia' THEN
        SELECT oa.id_ota_account INTO v_id_ota_account
        FROM ota_account oa
        WHERE oa.id_company = v_id_company
          AND oa.id_property = v_id_property
          AND oa.deleted_at IS NULL
          AND oa.is_active = 1
          AND (LOWER(TRIM(COALESCE(oa.platform,'')))='expedia' OR LOWER(TRIM(COALESCE(oa.ota_name,''))) LIKE '%expedia%')
        ORDER BY oa.id_ota_account LIMIT 1;
        SET v_source_name = 'Expedia';
      ELSE
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source, v_source_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_company = v_id_company
          AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
          AND rsc.deleted_at IS NULL
          AND rsc.is_active = 1
          AND LOWER(TRIM(COALESCE(rsc.source_name,''))) COLLATE utf8mb4_unicode_ci =
              LOWER(TRIM(COALESCE(v_origin_raw,''))) COLLATE utf8mb4_unicode_ci
        ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END, rsc.id_reservation_source
        LIMIT 1;
      END IF;

      /* Fallback: si no hay OTA/source configurada, no bloquear la reserva. */
      IF (v_id_reservation_source IS NULL OR v_id_reservation_source <= 0) THEN
        SELECT rsc.id_reservation_source, rsc.source_name
          INTO v_id_reservation_source, v_source_name
        FROM reservation_source_catalog rsc
        WHERE rsc.id_company = v_id_company
          AND (rsc.id_property = v_id_property OR rsc.id_property IS NULL)
          AND rsc.deleted_at IS NULL
          AND rsc.is_active = 1
          AND LOWER(TRIM(COALESCE(rsc.source_name,''))) COLLATE utf8mb4_unicode_ci IN (
            LOWER(TRIM(COALESCE(v_source_name,''))) COLLATE utf8mb4_unicode_ci,
            LOWER(TRIM(COALESCE(v_origin_raw,''))) COLLATE utf8mb4_unicode_ci
          )
        ORDER BY CASE WHEN rsc.id_property = v_id_property THEN 0 ELSE 1 END, rsc.id_reservation_source
        LIMIT 1;
      END IF;

      IF v_source_name IS NULL OR v_source_name = '' THEN
        SET v_source_name = COALESCE(NULLIF(TRIM(v_origin_raw), ''), 'Directo');
      END IF;

      SELECT COUNT(*) INTO v_overlap_cnt
      FROM reservation r
      WHERE r.id_room = v_id_room
        AND r.deleted_at IS NULL
        AND COALESCE(r.is_active,1)=1
        AND COALESCE(LOWER(TRIM(r.status)), 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
        AND NOT (r.check_out_date <= v_check_in OR r.check_in_date >= v_check_out);
      IF v_overlap_cnt > 0 THEN
        SET v_reason = 'empalme con reservacion existente';
        LEAVE row_proc;
      END IF;

      SELECT COUNT(*) INTO v_block_overlap_cnt
      FROM room_block rb
      WHERE rb.id_room = v_id_room
        AND rb.deleted_at IS NULL
        AND rb.is_active = 1
        AND rb.start_date < v_check_out
        AND rb.end_date > v_check_in;
      IF v_block_overlap_cnt > 0 THEN
        SET v_reason = 'empalme con bloqueo de habitacion';
        LEAVE row_proc;
      END IF;

      SET v_note_text = CONCAT(
        'Import masivo (sin folio). Monto reportado: ',
        COALESCE(CASE WHEN v_amount_decimal IS NULL THEN NULL ELSE FORMAT(v_amount_decimal, 2) END, 'N/D'),
        ' MXN. Origen: ',
        v_source_name
      );

      SET v_code = CONCAT('IMP-', DATE_FORMAT(NOW(), '%y%m%d'), '-', LPAD(v_id_import, 6, '0'), '-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 4)));

      INSERT INTO reservation (
        id_user, id_guest, id_room, id_property, id_category, code, status,
        source, id_ota_account, id_reservation_source,
        check_in_date, check_out_date,
        adults, children, currency,
        total_price_cents, balance_due_cents, notes_internal,
        is_active, created_at, created_by, updated_at
      ) VALUES (
        v_id_user, v_id_guest, v_id_room, v_id_property, v_id_category, v_code, 'confirmado',
        v_source_name, v_id_ota_account, v_id_reservation_source,
        v_check_in, v_check_out,
        1, 0, 'MXN',
        0, 0, v_note_text,
        1, NOW(), v_id_user, NOW()
      );
      SET v_new_reservation_id = LAST_INSERT_ID();

      INSERT INTO reservation_note (
        id_reservation, note_type, note_text, is_active, created_at, created_by, updated_at
      ) VALUES (
        v_new_reservation_id, 'internal', v_note_text, 1, NOW(), v_id_user, NOW()
      );
    END row_proc;

    IF v_sql_error = 1 THEN
      SET v_status = 'error';
      SET v_reason = CONCAT('SQL ERROR: ', COALESCE(v_sql_message, 'error'));
    ELSEIF v_reason IS NOT NULL AND v_reason <> '' THEN
      SET v_status = 'skipped';
    ELSE
      SET v_status = 'inserted';
    END IF;

    INSERT INTO tmp_reservation_import_log (
      id_import, result_status, reason, id_reservation, reservation_code, created_at
    ) VALUES (
      v_id_import, v_status, v_reason, v_new_reservation_id, v_code, NOW()
    );
  END LOOP;
  CLOSE cur;

  SELECT result_status, COUNT(*) AS total_rows
  FROM tmp_reservation_import_log
  GROUP BY result_status;

  SELECT
    l.id_import, i.id_property, i.room_name, i.guest_name, i.amount_raw, i.check_in_raw, i.check_out_raw, i.origin_raw,
    l.result_status, l.reason, l.id_reservation, l.reservation_code, l.created_at
  FROM tmp_reservation_import_log l
  JOIN tmp_reservation_import_input i ON i.id_import = l.id_import
  ORDER BY l.id_import;
END $$

DELIMITER ;

/* Verifica cuantas filas se cargaron en la tabla de entrada */
SELECT COUNT(*) AS rows_loaded_in_input
FROM tmp_reservation_import_input;

/* Ejecuta */
CALL sp_import_reservations_one_paste();

/* Verificacion rapida post-import */
SELECT result_status, COUNT(*) AS total_rows
FROM tmp_reservation_import_log
GROUP BY result_status
ORDER BY result_status;

SELECT
  (SELECT COUNT(*) FROM tmp_reservation_import_input) AS input_rows,
  (SELECT COUNT(*) FROM tmp_reservation_import_log) AS processed_rows;

SELECT reason, COUNT(*) AS total_rows
FROM tmp_reservation_import_log
WHERE result_status <> 'inserted'
GROUP BY reason
ORDER BY total_rows DESC, reason
LIMIT 30;

SELECT
  l.id_import,
  i.id_property,
  i.room_name,
  i.guest_name,
  i.check_in_raw,
  i.check_out_raw,
  l.id_reservation,
  l.reservation_code,
  l.result_status,
  l.reason
FROM tmp_reservation_import_log l
JOIN tmp_reservation_import_input i ON i.id_import = l.id_import
WHERE l.result_status = 'inserted'
ORDER BY l.id_import
LIMIT 200;

SELECT
  r.id_reservation,
  r.code,
  r.id_property,
  r.id_room,
  r.check_in_date,
  r.check_out_date,
  r.source,
  r.created_at
FROM reservation r
WHERE r.code LIKE 'IMP-%'
ORDER BY r.id_reservation DESC
LIMIT 200;

SELECT
  r.id_property,
  p.code AS property_code,
  COUNT(*) AS imported_rows,
  MIN(r.check_in_date) AS min_check_in,
  MAX(r.check_out_date) AS max_check_out
FROM reservation r
JOIN property p ON p.id_property = r.id_property
WHERE r.code LIKE 'IMP-%'
GROUP BY r.id_property, p.code
ORDER BY imported_rows DESC, r.id_property;


