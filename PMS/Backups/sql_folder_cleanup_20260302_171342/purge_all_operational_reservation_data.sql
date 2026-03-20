/* ============================================================================
   PURGA TOTAL OPERATIVA (RESERVAS / HUESPEDES / FOLIOS / CARGOS / LOGS)
   ----------------------------------------------------------------------------
   Este script BORRA datos operativos. NO toca settings ni catalogos.

   Incluye:
   - reservation, guest, folio, line_item, refund, room_block
   - reservation_note, reservation_interest, reservation_message_log
   - reservation_group, reservation_group_member
   - activity_booking, activity_booking_reservation
   - obligation_payment_log, line_item_hierarchy
   - ota_ical_event, ota_ical_event_map
   - tablas de import masivo creadas manualmente (si existen)

   NO incluye:
   - line_item_catalog / sale_item_catalog / reservation_source_catalog
   - pms_settings* / ota_account* / ota_ical_feed / message_template
   ============================================================================ */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @purge_started_at := NOW();
SET @old_sql_safe_updates := @@SQL_SAFE_UPDATES;
SET SQL_SAFE_UPDATES = 0;

/* --------------------------------------------------------------------------
   PREVIEW (antes de borrar)
   -------------------------------------------------------------------------- */
SELECT 'activity_booking_reservation' AS table_name, COUNT(*) AS rows_before FROM activity_booking_reservation
UNION ALL SELECT 'activity_booking', COUNT(*) FROM activity_booking
UNION ALL SELECT 'reservation_group_member', COUNT(*) FROM reservation_group_member
UNION ALL SELECT 'reservation_interest', COUNT(*) FROM reservation_interest
UNION ALL SELECT 'reservation_note', COUNT(*) FROM reservation_note
UNION ALL SELECT 'reservation_message_log', COUNT(*) FROM reservation_message_log
UNION ALL SELECT 'obligation_payment_log', COUNT(*) FROM obligation_payment_log
UNION ALL SELECT 'line_item_hierarchy', COUNT(*) FROM line_item_hierarchy
UNION ALL SELECT 'refund', COUNT(*) FROM refund
UNION ALL SELECT 'line_item', COUNT(*) FROM line_item
UNION ALL SELECT 'folio', COUNT(*) FROM folio
UNION ALL SELECT 'ota_ical_event_map', COUNT(*) FROM ota_ical_event_map
UNION ALL SELECT 'ota_ical_event', COUNT(*) FROM ota_ical_event
UNION ALL SELECT 'room_block', COUNT(*) FROM room_block
UNION ALL SELECT 'reservation', COUNT(*) FROM reservation
UNION ALL SELECT 'reservation_group', COUNT(*) FROM reservation_group
UNION ALL SELECT 'guest', COUNT(*) FROM guest;

/* --------------------------------------------------------------------------
   EJECUCION
   -------------------------------------------------------------------------- */
START TRANSACTION;

DELETE FROM activity_booking_reservation;
SET @del_activity_booking_reservation := ROW_COUNT();

DELETE FROM reservation_group_member;
SET @del_reservation_group_member := ROW_COUNT();

DELETE FROM reservation_interest;
SET @del_reservation_interest := ROW_COUNT();

DELETE FROM reservation_note;
SET @del_reservation_note := ROW_COUNT();

DELETE FROM reservation_message_log;
SET @del_reservation_message_log := ROW_COUNT();

DELETE FROM obligation_payment_log;
SET @del_obligation_payment_log := ROW_COUNT();

DELETE FROM line_item_hierarchy;
SET @del_line_item_hierarchy := ROW_COUNT();

DELETE FROM refund;
SET @del_refund := ROW_COUNT();

DELETE FROM line_item;
SET @del_line_item := ROW_COUNT();

DELETE FROM folio;
SET @del_folio := ROW_COUNT();

DELETE FROM activity_booking;
SET @del_activity_booking := ROW_COUNT();

DELETE FROM ota_ical_event_map;
SET @del_ota_ical_event_map := ROW_COUNT();

DELETE FROM ota_ical_event;
SET @del_ota_ical_event := ROW_COUNT();

DELETE FROM room_block;
SET @del_room_block := ROW_COUNT();

DELETE FROM reservation;
SET @del_reservation := ROW_COUNT();

DELETE FROM reservation_group;
SET @del_reservation_group := ROW_COUNT();

DELETE FROM guest;
SET @del_guest := ROW_COUNT();

COMMIT;

/* Limpieza de tablas de import masivo (si existen) */
DROP TABLE IF EXISTS tmp_reservation_import_log;
DROP TABLE IF EXISTS tmp_reservation_import_input;

/* Reset AUTO_INCREMENT de tablas operativas */
ALTER TABLE activity_booking AUTO_INCREMENT = 1;
ALTER TABLE folio AUTO_INCREMENT = 1;
ALTER TABLE guest AUTO_INCREMENT = 1;
ALTER TABLE line_item AUTO_INCREMENT = 1;
ALTER TABLE line_item_hierarchy AUTO_INCREMENT = 1;
ALTER TABLE obligation_payment_log AUTO_INCREMENT = 1;
ALTER TABLE ota_ical_event AUTO_INCREMENT = 1;
ALTER TABLE ota_ical_event_map AUTO_INCREMENT = 1;
ALTER TABLE refund AUTO_INCREMENT = 1;
ALTER TABLE reservation AUTO_INCREMENT = 1;
ALTER TABLE reservation_group AUTO_INCREMENT = 1;
ALTER TABLE reservation_group_member AUTO_INCREMENT = 1;
ALTER TABLE reservation_message_log AUTO_INCREMENT = 1;
ALTER TABLE reservation_note AUTO_INCREMENT = 1;
ALTER TABLE room_block AUTO_INCREMENT = 1;

SET SQL_SAFE_UPDATES = @old_sql_safe_updates;

/* --------------------------------------------------------------------------
   RESUMEN
   -------------------------------------------------------------------------- */
SELECT
  @purge_started_at AS purge_started_at,
  NOW() AS purge_finished_at;

SELECT 'activity_booking_reservation' AS table_name, @del_activity_booking_reservation AS rows_deleted
UNION ALL SELECT 'activity_booking', @del_activity_booking
UNION ALL SELECT 'reservation_group_member', @del_reservation_group_member
UNION ALL SELECT 'reservation_interest', @del_reservation_interest
UNION ALL SELECT 'reservation_note', @del_reservation_note
UNION ALL SELECT 'reservation_message_log', @del_reservation_message_log
UNION ALL SELECT 'obligation_payment_log', @del_obligation_payment_log
UNION ALL SELECT 'line_item_hierarchy', @del_line_item_hierarchy
UNION ALL SELECT 'refund', @del_refund
UNION ALL SELECT 'line_item', @del_line_item
UNION ALL SELECT 'folio', @del_folio
UNION ALL SELECT 'ota_ical_event_map', @del_ota_ical_event_map
UNION ALL SELECT 'ota_ical_event', @del_ota_ical_event
UNION ALL SELECT 'room_block', @del_room_block
UNION ALL SELECT 'reservation', @del_reservation
UNION ALL SELECT 'reservation_group', @del_reservation_group
UNION ALL SELECT 'guest', @del_guest;

/* Validacion final: deberian quedar en 0 */
SELECT 'activity_booking_reservation' AS table_name, COUNT(*) AS rows_after FROM activity_booking_reservation
UNION ALL SELECT 'activity_booking', COUNT(*) FROM activity_booking
UNION ALL SELECT 'reservation_group_member', COUNT(*) FROM reservation_group_member
UNION ALL SELECT 'reservation_interest', COUNT(*) FROM reservation_interest
UNION ALL SELECT 'reservation_note', COUNT(*) FROM reservation_note
UNION ALL SELECT 'reservation_message_log', COUNT(*) FROM reservation_message_log
UNION ALL SELECT 'obligation_payment_log', COUNT(*) FROM obligation_payment_log
UNION ALL SELECT 'line_item_hierarchy', COUNT(*) FROM line_item_hierarchy
UNION ALL SELECT 'refund', COUNT(*) FROM refund
UNION ALL SELECT 'line_item', COUNT(*) FROM line_item
UNION ALL SELECT 'folio', COUNT(*) FROM folio
UNION ALL SELECT 'ota_ical_event_map', COUNT(*) FROM ota_ical_event_map
UNION ALL SELECT 'ota_ical_event', COUNT(*) FROM ota_ical_event
UNION ALL SELECT 'room_block', COUNT(*) FROM room_block
UNION ALL SELECT 'reservation', COUNT(*) FROM reservation
UNION ALL SELECT 'reservation_group', COUNT(*) FROM reservation_group
UNION ALL SELECT 'guest', COUNT(*) FROM guest;
