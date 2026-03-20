# SQL Map: Schema + Stored Procedures

Last update: 2026-03-02

## Scope
This guide maps where to look fast in `bd pms` when you need a specific behavior.

- Folder with SQL runtime assets: `PMS/bd pms`
- Rule: keep only schema files and `sp_*.sql` files there.

## Schema Files
- `schema_u508158532_rodbd.sql` -> main schema snapshot.
- `ota_ical_schema.sql` -> OTA iCal schema objects.
- `ota_reservation_source_schema.sql` -> OTA source schema objects.
- `ota_account_tables.sql` -> OTA account tables.
- `pms_settings_payment_catalog.sql` -> payment catalog settings table.

## Fast Lookup (what to open first)
- Create reservation: `sp_create_reservation.sql`
- Create hold (apartado): `sp_create_reservation_hold.sql`
- Confirm hold: `sp_reservation_confirm_hold.sql`
- Update reservation/status/room/dates: `sp_reservation_update.sql`
- Alternate update flow/version: `sp_reservation_update_v2.sql`
- Add folio to reservation: `sp_reservation_add_folio.sql`
- Reservation notes: `sp_reservation_note_data.sql`, `sp_reservation_note_upsert.sql`
- Reservation totals report: `sp_reservation_totals_report.sql`
- Search availability: `sp_search_availability.sql`
- Calendar room grid: `sp_property_room_calendar.sql`
- Room blocks: `sp_create_room_block.sql`, `sp_update_room_block.sql`, `sp_get_room_block.sql`, `sp_list_room_blocks.sql`

## Stored Procedures by Domain

### 1) Reservation lifecycle
- `sp_create_reservation.sql`
- `sp_create_reservation_hold.sql`
- `sp_reservation_confirm_hold.sql`
- `sp_reservation_update.sql`
- `sp_reservation_update_v2.sql`
- `sp_reservation_add_folio.sql`
- `sp_reservation_message_send.sql`
- `sp_reservation_note_data.sql`
- `sp_reservation_note_upsert.sql`
- `sp_reservation_totals_report.sql`
- `sp_list_reservations_by_company.sql`
- `sp_list_reservations_by_property.sql`

### 2) Calendar, availability, and room blocks
- `sp_property_room_calendar.sql`
- `sp_search_availability.sql`
- `sp_create_room_block.sql`
- `sp_update_room_block.sql`
- `sp_get_room_block.sql`
- `sp_list_room_blocks.sql`

### 3) Property, room, category, guest, user
- `sp_property_upsert.sql`
- `sp_room_upsert.sql`
- `sp_roomcategory_upsert.sql`
- `sp_guest_upsert.sql`
- `sp_app_user_upsert.sql`
- `sp_get_company_properties.sql`

### 4) Folios, charges, payments, obligations, refunds
- `sp_folio_upsert.sql`
- `sp_folio_recalc.sql`
- `sp_line_item_type_upsert.sql`
- `sp_line_item_payment_meta_upsert.sql`
- `sp_line_item_percent_derived_upsert.sql`
- `sp_obligation_data.sql`
- `sp_obligation_paid_upsert.sql`
- `sp_refund_upsert.sql`

### 5) Sale items and catalogs
- `sp_sale_item_upsert.sql`
- `sp_sale_item_child_upsert.sql`
- `sp_sale_item_report_data.sql`
- `sp_sale_item_category_data.sql`
- `sp_sale_item_category_upsert.sql`
- `sp_sale_item_catalog_data.sql`
- `sp_sale_item_catalog_upsert.sql`
- `sp_sale_item_catalog_clone.sql`
- `sp_sale_item_catalog_calc_upsert.sql`
- `sp_sale_item_catalog_parent_total_upsert.sql`

### 6) Rateplan and pricing
- `sp_rateplan_upsert.sql`
- `sp_rateplan_calendar.sql`
- `sp_rateplan_calc_total.sql`
- `sp_rateplan_pricing_upsert.sql`
- `sp_rateplan_season_upsert.sql`
- `sp_rateplan_override_upsert.sql`

### 7) OTA and iCal
- `sp_ota_account_data.sql`
- `sp_ota_account_upsert.sql`
- `sp_ota_account_info_catalog_sync.sql`
- `sp_ota_account_lodging_sync.sql`
- `sp_ota_ical_feed_lodging_data.sql`
- `sp_ota_ical_feed_lodging_upsert.sql`
- `sp_ota_ical_lodging_catalog_data.sql`

### 8) Activities
- `sp_activity_upsert.sql`
- `sp_activity_book.sql`
- `sp_activity_booking_upsert.sql`
- `sp_activity_bookings_list.sql`
- `sp_activity_cancel.sql`
- `sp_get_company_activities.sql`

### 9) Reports and accounting
- `sp_accounting_report_data.sql`
- `sp_report_catalog_options.sql`
- `sp_report_config_data.sql`
- `sp_report_config_upsert.sql`
- `sp_report_definition_data.sql`
- `sp_report_definition_upsert.sql`
- `sp_report_definition_column_upsert.sql`
- `sp_report_definition_filter_upsert.sql`
- `sp_report_definition_run_data.sql`
- `sp_report_field_catalog_data.sql`

### 10) PMS settings, theme, templates, portal
- `sp_pms_settings_data.sql`
- `sp_pms_settings_upsert.sql`
- `sp_pms_theme_data.sql`
- `sp_pms_theme_upsert.sql`
- `sp_message_template_upsert.sql`
- `sp_portal_activity_data.sql`
- `sp_portal_app_user_data.sql`
- `sp_portal_guest_data.sql`
- `sp_portal_property_data.sql`
- `sp_portal_reservation_data.sql`

## Maintenance Notes
- If a new SP is added, classify it in one domain section above.
- Keep migration/fix/import scripts outside `bd pms` (example: `PMS/migrations`).
- Keep this file updated when adding/removing SP files.
