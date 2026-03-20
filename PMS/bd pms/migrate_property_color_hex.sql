ALTER TABLE property
  ADD COLUMN IF NOT EXISTS color_hex VARCHAR(16) NULL AFTER name;

UPDATE property
SET color_hex = UPPER(TRIM(color_hex))
WHERE color_hex IS NOT NULL
  AND TRIM(color_hex) <> '';

UPDATE property
SET color_hex = CONCAT('#', color_hex)
WHERE color_hex IS NOT NULL
  AND TRIM(color_hex) <> ''
  AND LEFT(TRIM(color_hex), 1) <> '#';

UPDATE property
SET color_hex = NULL
WHERE color_hex IS NOT NULL
  AND TRIM(color_hex) <> ''
  AND UPPER(TRIM(color_hex)) NOT REGEXP '^#[0-9A-F]{6}$';
