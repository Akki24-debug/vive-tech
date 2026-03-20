/* ============================================================================
   FIX MOJIBAKE UTF-8 (Ã¡, Ã±, etc.) en datos importados recientemente
   - Corrige texto mal guardado como latin1/utf8 mezclado.
   - Objetivo: guests y notas ligados a reservas del import.
   - NO toca folios/cargos.
   ============================================================================ */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @lookback_days := 30;

DROP TEMPORARY TABLE IF EXISTS tmp_fix_target_res;
CREATE TEMPORARY TABLE tmp_fix_target_res (
  id_reservation BIGINT PRIMARY KEY
) ENGINE=MEMORY;

SET @has_tmp_log := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'tmp_reservation_import_log'
);

SET @sql_ins_tmp_log := IF(
  @has_tmp_log > 0,
  CONCAT(
    'INSERT IGNORE INTO tmp_fix_target_res (id_reservation) ',
    'SELECT DISTINCT l.id_reservation ',
    'FROM tmp_reservation_import_log l ',
    'WHERE l.result_status = ''inserted'' ',
    '  AND l.id_reservation IS NOT NULL ',
    '  AND l.created_at >= (NOW() - INTERVAL ', @lookback_days, ' DAY)'
  ),
  'SELECT 1'
);
PREPARE stmt_ins_tmp_log FROM @sql_ins_tmp_log;
EXECUTE stmt_ins_tmp_log;
DEALLOCATE PREPARE stmt_ins_tmp_log;

INSERT IGNORE INTO tmp_fix_target_res (id_reservation)
SELECT r.id_reservation
FROM reservation r
WHERE r.deleted_at IS NULL
  AND r.created_at >= (NOW() - INTERVAL @lookback_days DAY)
  AND (r.code LIKE 'IMP-%' OR r.code LIKE 'SP-%');

DROP TEMPORARY TABLE IF EXISTS tmp_fix_target_guest;
CREATE TEMPORARY TABLE tmp_fix_target_guest (
  id_guest BIGINT PRIMARY KEY
) ENGINE=MEMORY;

INSERT IGNORE INTO tmp_fix_target_guest (id_guest)
SELECT DISTINCT r.id_guest
FROM reservation r
JOIN tmp_fix_target_res t ON t.id_reservation = r.id_reservation
WHERE r.id_guest IS NOT NULL;

/* Preview */
SELECT COUNT(*) AS target_reservations FROM tmp_fix_target_res;
SELECT COUNT(*) AS target_guests FROM tmp_fix_target_guest;

SELECT
  g.id_guest,
  g.names AS current_names,
  CONVERT(CAST(CONVERT(g.names USING latin1) AS BINARY) USING utf8mb4) AS fixed_names
FROM guest g
JOIN tmp_fix_target_guest tg ON tg.id_guest = g.id_guest
WHERE g.names LIKE '%Ã%' OR g.names LIKE '%Â%'
ORDER BY g.id_guest DESC
LIMIT 200;

START TRANSACTION;

UPDATE guest g
JOIN tmp_fix_target_guest tg ON tg.id_guest = g.id_guest
SET
  g.names = CASE
    WHEN g.names LIKE '%Ã%' OR g.names LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(g.names USING latin1) AS BINARY) USING utf8mb4)
    ELSE g.names
  END,
  g.last_name = CASE
    WHEN g.last_name LIKE '%Ã%' OR g.last_name LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(g.last_name USING latin1) AS BINARY) USING utf8mb4)
    ELSE g.last_name
  END,
  g.full_name = CASE
    WHEN g.full_name LIKE '%Ã%' OR g.full_name LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(g.full_name USING latin1) AS BINARY) USING utf8mb4)
    ELSE g.full_name
  END,
  g.notes_internal = CASE
    WHEN g.notes_internal LIKE '%Ã%' OR g.notes_internal LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(g.notes_internal USING latin1) AS BINARY) USING utf8mb4)
    ELSE g.notes_internal
  END,
  g.notes_guest = CASE
    WHEN g.notes_guest LIKE '%Ã%' OR g.notes_guest LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(g.notes_guest USING latin1) AS BINARY) USING utf8mb4)
    ELSE g.notes_guest
  END,
  g.updated_at = NOW();

UPDATE reservation r
JOIN tmp_fix_target_res tr ON tr.id_reservation = r.id_reservation
SET
  r.notes_internal = CASE
    WHEN r.notes_internal LIKE '%Ã%' OR r.notes_internal LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(r.notes_internal USING latin1) AS BINARY) USING utf8mb4)
    ELSE r.notes_internal
  END,
  r.notes_guest = CASE
    WHEN r.notes_guest LIKE '%Ã%' OR r.notes_guest LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(r.notes_guest USING latin1) AS BINARY) USING utf8mb4)
    ELSE r.notes_guest
  END,
  r.source = CASE
    WHEN r.source LIKE '%Ã%' OR r.source LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(r.source USING latin1) AS BINARY) USING utf8mb4)
    ELSE r.source
  END,
  r.updated_at = NOW();

UPDATE reservation_note rn
JOIN tmp_fix_target_res tr ON tr.id_reservation = rn.id_reservation
SET
  rn.note_text = CASE
    WHEN rn.note_text LIKE '%Ã%' OR rn.note_text LIKE '%Â%'
      THEN CONVERT(CAST(CONVERT(rn.note_text USING latin1) AS BINARY) USING utf8mb4)
    ELSE rn.note_text
  END,
  rn.updated_at = NOW();

COMMIT;

SELECT
  g.id_guest,
  g.names AS fixed_names
FROM guest g
JOIN tmp_fix_target_guest tg ON tg.id_guest = g.id_guest
WHERE g.names LIKE '%Ã%' OR g.names LIKE '%Â%'
ORDER BY g.id_guest DESC
LIMIT 50;
