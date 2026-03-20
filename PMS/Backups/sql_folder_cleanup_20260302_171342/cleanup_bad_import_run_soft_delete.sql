/* ============================================================================
   CLEANUP CORRIDA MALA DE IMPORT (SOFT DELETE)
   - Basado en tmp_reservation_import_log + fallback por code SP-* / IMP-*
   - Limpia reservation/folio/line_item/notas/intereses/mensajes y guest huerfano
   ============================================================================ */

SET @lookback_minutes := 720; -- ajusta segun necesidad

DROP TEMPORARY TABLE IF EXISTS tmp_cleanup_target_res;
CREATE TEMPORARY TABLE tmp_cleanup_target_res (
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
    'INSERT IGNORE INTO tmp_cleanup_target_res (id_reservation) ',
    'SELECT DISTINCT l.id_reservation ',
    'FROM tmp_reservation_import_log l ',
    'WHERE l.result_status = ''inserted'' ',
    '  AND l.id_reservation IS NOT NULL ',
    '  AND l.created_at >= (NOW() - INTERVAL ', @lookback_minutes, ' MINUTE)'
  ),
  'SELECT 1'
);
PREPARE stmt_ins_tmp_log FROM @sql_ins_tmp_log;
EXECUTE stmt_ins_tmp_log;
DEALLOCATE PREPARE stmt_ins_tmp_log;

INSERT IGNORE INTO tmp_cleanup_target_res (id_reservation)
SELECT r.id_reservation
FROM reservation r
WHERE r.deleted_at IS NULL
  AND r.created_at >= (NOW() - INTERVAL @lookback_minutes MINUTE)
  AND (r.code LIKE 'SP-%' OR r.code LIKE 'IMP-%');

DROP TEMPORARY TABLE IF EXISTS tmp_cleanup_target_folio;
CREATE TEMPORARY TABLE tmp_cleanup_target_folio (
  id_folio BIGINT PRIMARY KEY
) ENGINE=MEMORY;

INSERT IGNORE INTO tmp_cleanup_target_folio (id_folio)
SELECT f.id_folio
FROM folio f
JOIN tmp_cleanup_target_res t ON t.id_reservation = f.id_reservation;

DROP TEMPORARY TABLE IF EXISTS tmp_cleanup_target_line_item;
CREATE TEMPORARY TABLE tmp_cleanup_target_line_item (
  id_line_item BIGINT PRIMARY KEY
) ENGINE=MEMORY;

INSERT IGNORE INTO tmp_cleanup_target_line_item (id_line_item)
SELECT li.id_line_item
FROM line_item li
JOIN tmp_cleanup_target_folio tf ON tf.id_folio = li.id_folio;

DROP TEMPORARY TABLE IF EXISTS tmp_cleanup_target_guest;
CREATE TEMPORARY TABLE tmp_cleanup_target_guest (
  id_guest BIGINT PRIMARY KEY
) ENGINE=MEMORY;

INSERT IGNORE INTO tmp_cleanup_target_guest (id_guest)
SELECT DISTINCT r.id_guest
FROM reservation r
JOIN tmp_cleanup_target_res t ON t.id_reservation = r.id_reservation
WHERE r.id_guest IS NOT NULL;

SELECT COUNT(*) AS target_reservations FROM tmp_cleanup_target_res;

START TRANSACTION;

DELETE opl
FROM obligation_payment_log opl
LEFT JOIN tmp_cleanup_target_line_item tli ON tli.id_line_item = opl.id_line_item
LEFT JOIN tmp_cleanup_target_folio tf ON tf.id_folio = opl.id_folio
LEFT JOIN tmp_cleanup_target_res tr ON tr.id_reservation = opl.id_reservation
WHERE tli.id_line_item IS NOT NULL
   OR tf.id_folio IS NOT NULL
   OR tr.id_reservation IS NOT NULL;

UPDATE line_item li
JOIN tmp_cleanup_target_folio tf ON tf.id_folio = li.id_folio
SET
  li.is_active = 0,
  li.deleted_at = NOW(),
  li.updated_at = NOW()
WHERE li.deleted_at IS NULL;

UPDATE folio f
JOIN tmp_cleanup_target_res tr ON tr.id_reservation = f.id_reservation
SET
  f.is_active = 0,
  f.status = 'void',
  f.deleted_at = NOW(),
  f.updated_at = NOW()
WHERE f.deleted_at IS NULL;

UPDATE reservation_note rn
JOIN tmp_cleanup_target_res tr ON tr.id_reservation = rn.id_reservation
SET rn.is_active = 0, rn.deleted_at = NOW(), rn.updated_at = NOW()
WHERE rn.deleted_at IS NULL;

UPDATE reservation_interest ri
JOIN tmp_cleanup_target_res tr ON tr.id_reservation = ri.id_reservation
SET ri.is_active = 0, ri.deleted_at = NOW(), ri.updated_at = NOW()
WHERE ri.deleted_at IS NULL;

UPDATE reservation_group_member rgm
JOIN tmp_cleanup_target_res tr ON tr.id_reservation = rgm.id_reservation
SET rgm.is_active = 0, rgm.deleted_at = NOW(), rgm.updated_at = NOW()
WHERE rgm.deleted_at IS NULL;

DELETE rml
FROM reservation_message_log rml
JOIN tmp_cleanup_target_res tr ON tr.id_reservation = rml.id_reservation;

UPDATE reservation r
JOIN tmp_cleanup_target_res tr ON tr.id_reservation = r.id_reservation
SET
  r.status = 'cancelada',
  r.is_active = 0,
  r.deleted_at = NOW(),
  r.canceled_at = NOW(),
  r.updated_at = NOW()
WHERE r.deleted_at IS NULL;

UPDATE guest g
JOIN tmp_cleanup_target_guest tg ON tg.id_guest = g.id_guest
LEFT JOIN reservation r_active
  ON r_active.id_guest = g.id_guest
 AND r_active.deleted_at IS NULL
 AND COALESCE(r_active.is_active, 1) = 1
SET g.is_active = 0, g.deleted_at = NOW(), g.updated_at = NOW()
WHERE r_active.id_reservation IS NULL
  AND g.deleted_at IS NULL;

COMMIT;

SELECT
  COUNT(*) AS still_active_reservations
FROM reservation r
JOIN tmp_cleanup_target_res t ON t.id_reservation = r.id_reservation
WHERE r.deleted_at IS NULL;
