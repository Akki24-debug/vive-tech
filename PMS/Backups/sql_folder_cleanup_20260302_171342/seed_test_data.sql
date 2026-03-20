-- Seed test data for availability and property listing.
-- Adjust table or column names if your schema differs.

START TRANSACTION;

-- Ensure company exists
INSERT INTO company (code, legal_name, deleted_at)
SELECT 'VIBE', 'Vive la Vibe', NULL
WHERE NOT EXISTS (SELECT 1 FROM company WHERE code = 'VIBE');

-- Create two properties under VIBE
INSERT INTO property (id_company, code, name, description, city, state, country, currency, is_active, deleted_at)
SELECT c.id_company, 'CST', 'Casa Triz', 'Demo property', 'Puerto Escondido', 'Oaxaca', 'MX', 'MXN', 1, NULL
FROM company c WHERE c.code = 'VIBE'
AND NOT EXISTS (SELECT 1 FROM property p WHERE p.code='CST');

INSERT INTO property (id_company, code, name, description, city, state, country, currency, is_active, deleted_at)
SELECT c.id_company, 'PSJ', 'Posada San Jeronimo', 'Demo property', 'Puerto Escondido', 'Oaxaca', 'MX', 'MXN', 1, NULL
FROM company c WHERE c.code = 'VIBE'
AND NOT EXISTS (SELECT 1 FROM property p WHERE p.code='PSJ');

-- Categories per property
-- Casa Triz
INSERT INTO roomcategory (id_property, code, name, description, max_occupancy, default_floor_cents, default_ceil_cents, image_url, is_active, deleted_at)
SELECT p.id_property, 'PS2', 'Habitacion Doble', NULL, 2, 120000, 160000, NULL, 1, NULL
FROM property p WHERE p.code='CST'
AND NOT EXISTS (SELECT 1 FROM roomcategory rc WHERE rc.code='PS2' AND rc.id_property=p.id_property);

INSERT INTO roomcategory (id_property, code, name, description, max_occupancy, default_floor_cents, default_ceil_cents, image_url, is_active, deleted_at)
SELECT p.id_property, 'CS1', 'Habitacion Sencilla', NULL, 2, 80000, 120000, NULL, 1, NULL
FROM property p WHERE p.code='CST'
AND NOT EXISTS (SELECT 1 FROM roomcategory rc WHERE rc.code='CS1' AND rc.id_property=p.id_property);

-- Posada San Jeronimo
INSERT INTO roomcategory (id_property, code, name, description, max_occupancy, default_floor_cents, default_ceil_cents, image_url, is_active, deleted_at)
SELECT p.id_property, 'DBL', 'Habitacion Doble', NULL, 2, 100000, 140000, NULL, 1, NULL
FROM property p WHERE p.code='PSJ'
AND NOT EXISTS (SELECT 1 FROM roomcategory rc WHERE rc.code='DBL' AND rc.id_property=p.id_property);

-- Rooms per category (2 rooms each)
INSERT INTO room (id_property, id_category, code, name, is_active, deleted_at)
SELECT p.id_property, rc.id_category, CONCAT(rc.code,'-101'), CONCAT(rc.name,' 101'), 1, NULL
FROM property p
JOIN roomcategory rc ON rc.id_property=p.id_property
LEFT JOIN room r ON r.id_category=rc.id_category AND r.code=CONCAT(rc.code,'-101')
WHERE r.id_room IS NULL;

INSERT INTO room (id_property, id_category, code, name, is_active, deleted_at)
SELECT p.id_property, rc.id_category, CONCAT(rc.code,'-102'), CONCAT(rc.name,' 102'), 1, NULL
FROM property p
JOIN roomcategory rc ON rc.id_property=p.id_property
LEFT JOIN room r ON r.id_category=rc.id_category AND r.code=CONCAT(rc.code,'-102')
WHERE r.id_room IS NULL;

-- Guests (single `names` column)
INSERT INTO guest (names, email, phone)
SELECT 'Juan Perez','juan.perez@example.com','+525500000001'
WHERE NOT EXISTS (SELECT 1 FROM guest WHERE email='juan.perez@example.com');

INSERT INTO guest (names, email, phone)
SELECT 'Ana Lopez','ana.lopez@example.com','+525500000002'
WHERE NOT EXISTS (SELECT 1 FROM guest WHERE email='ana.lopez@example.com');

-- Sample reservations to create overlaps
-- Reserve room PS2-101 for next weekend for 3 nights
INSERT INTO reservation (id_room, check_in_date, check_out_date, status, deleted_at)
SELECT r.id_room, DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'confirmed', NULL
FROM room r
JOIN roomcategory rc ON rc.id_category=r.id_category AND rc.code='PS2'
JOIN property p ON p.id_property=r.id_property AND p.code='CST'
LEFT JOIN reservation res ON res.id_room=r.id_room AND res.check_in_date < DATE_ADD(CURDATE(), INTERVAL 10 DAY) AND res.check_out_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND res.deleted_at IS NULL
WHERE res.id_reservation IS NULL
LIMIT 1;

-- Reserve room CS1-101 overlapping in 2 weeks (2 nights)
INSERT INTO reservation (id_room, check_in_date, check_out_date, status, deleted_at)
SELECT r.id_room, DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 16 DAY), 'confirmed', NULL
FROM room r
JOIN roomcategory rc ON rc.id_category=r.id_category AND rc.code='CS1'
JOIN property p ON p.id_property=r.id_property AND p.code='CST'
LEFT JOIN reservation res ON res.id_room=r.id_room AND res.check_in_date < DATE_ADD(CURDATE(), INTERVAL 16 DAY) AND res.check_out_date > DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND res.deleted_at IS NULL
WHERE res.id_reservation IS NULL
LIMIT 1;

-- Sample activities
INSERT INTO activity (id_company, id_property, code, name, type, description, duration_minutes, base_price_cents, currency, capacity_default, location, is_active, deleted_at, created_at, updated_at)
SELECT c.id_company,
       p.id_property,
       'LANCHA',
       'Paseo en lancha',
       'tour',
       'Recorrido privado en lancha para explorar la costa y disfrutar de avistamientos marinos.',
       180,
       80000,
       'MXN',
       8,
       'Bahia Principal',
       1,
       NULL,
       NOW(),
       NOW()
FROM company c
LEFT JOIN property p ON p.id_company = c.id_company AND p.code = 'CST'
WHERE c.code = 'VIBE'
  AND NOT EXISTS (SELECT 1 FROM activity WHERE code = 'LANCHA');

INSERT INTO activity (id_company, id_property, code, name, type, description, duration_minutes, base_price_cents, currency, capacity_default, location, is_active, deleted_at, created_at, updated_at)
SELECT c.id_company,
       p.id_property,
       'BIO',
       'Laguna de bioluminiscencia',
       'tour',
       'Navegacion nocturna para presenciar el fenomeno de bioluminiscencia en Manialtepec.',
       210,
       95000,
       'MXN',
       10,
       'Laguna de Manialtepec',
       1,
       NULL,
       NOW(),
       NOW()
FROM company c
LEFT JOIN property p ON p.id_company = c.id_company AND p.code = 'PSJ'
WHERE c.code = 'VIBE'
  AND NOT EXISTS (SELECT 1 FROM activity WHERE code = 'BIO');

INSERT INTO activity (id_company, id_property, code, name, type, description, duration_minutes, base_price_cents, currency, capacity_default, location, is_active, deleted_at, created_at, updated_at)
SELECT c.id_company,
       NULL,
       'CHEF',
       'Cena privada con chef invitado',
       'vibe',
       'Experiencia gastronomica personalizada con ingredientes locales y servicio completo en la propiedad.',
       180,
       250000,
       'MXN',
       12,
       'A domicilio',
       1,
       NULL,
       NOW(),
       NOW()
FROM company c
WHERE c.code = 'VIBE'
  AND NOT EXISTS (SELECT 1 FROM activity WHERE code = 'CHEF');

INSERT INTO activity (id_company, id_property, code, name, type, description, duration_minutes, base_price_cents, currency, capacity_default, location, is_active, deleted_at, created_at, updated_at)
SELECT c.id_company,
       NULL,
       'WELLNESS',
       'Sunrise wellness session',
       'vibe',
       'Sesion privada de yoga frente al mar con masaje relajante y jugos cold press.',
       90,
       180000,
       'MXN',
       6,
       'Playa Bacocho',
       1,
       NULL,
       NOW(),
       NOW()
FROM company c
WHERE c.code = 'VIBE'
  AND NOT EXISTS (SELECT 1 FROM activity WHERE code = 'WELLNESS');

COMMIT;
