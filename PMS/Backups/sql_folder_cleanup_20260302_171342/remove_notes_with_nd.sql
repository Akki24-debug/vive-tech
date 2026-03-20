/* ============================================================================
   LIMPIEZA DE NOTAS CON "N/D"
   Objetivo:
   - Eliminar notas en reservation_note cuyo texto contenga "N/D"
   - Limpiar (poner NULL) notas internas en reservation/guest cuando contengan "N/D"

   Uso:
   1) Selecciona tu base (USE tu_db;).
   2) Ejecuta este script completo.
   ============================================================================ */

SET @needle := 'N/D';

START TRANSACTION;

/* ---- Preview antes de aplicar ---- */
SELECT 'reservation_note' AS target, COUNT(*) AS rows_matching
FROM reservation_note
WHERE note_text LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;

SELECT 'reservation.notes_internal' AS target, COUNT(*) AS rows_matching
FROM reservation
WHERE notes_internal LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;

SELECT 'reservation.notes_guest' AS target, COUNT(*) AS rows_matching
FROM reservation
WHERE notes_guest LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;

SELECT 'guest.notes_internal' AS target, COUNT(*) AS rows_matching
FROM guest
WHERE notes_internal LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;

/* ---- 1) Borrar notas registradas ---- */
DELETE FROM reservation_note
WHERE note_text LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;
SET @deleted_reservation_note := ROW_COUNT();

/* ---- 2) Limpiar notas internas incrustadas ---- */
UPDATE reservation
SET
  notes_internal = NULL,
  updated_at = NOW()
WHERE notes_internal LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;
SET @updated_res_notes_internal := ROW_COUNT();

UPDATE reservation
SET
  notes_guest = NULL,
  updated_at = NOW()
WHERE notes_guest LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;
SET @updated_res_notes_guest := ROW_COUNT();

UPDATE guest
SET
  notes_internal = NULL,
  updated_at = NOW()
WHERE notes_internal LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;
SET @updated_guest_notes_internal := ROW_COUNT();

/* ---- Resumen ---- */
SELECT 'reservation_note deleted' AS action, @deleted_reservation_note AS affected_rows
UNION ALL
SELECT 'reservation.notes_internal cleaned', @updated_res_notes_internal
UNION ALL
SELECT 'reservation.notes_guest cleaned', @updated_res_notes_guest
UNION ALL
SELECT 'guest.notes_internal cleaned', @updated_guest_notes_internal;

/* ---- Verificacion final ---- */
SELECT 'reservation_note' AS target, COUNT(*) AS rows_remaining
FROM reservation_note
WHERE note_text LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL
UNION ALL
SELECT 'reservation.notes_internal', COUNT(*)
FROM reservation
WHERE notes_internal LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL
UNION ALL
SELECT 'reservation.notes_guest', COUNT(*)
FROM reservation
WHERE notes_guest LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL
UNION ALL
SELECT 'guest.notes_internal', COUNT(*)
FROM guest
WHERE notes_internal LIKE CONCAT('%', @needle, '%')
  AND deleted_at IS NULL;

COMMIT;

