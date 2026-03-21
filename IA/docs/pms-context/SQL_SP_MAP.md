# SQL Map: Schema + Stored Procedures

Last update: 2026-03-02

## Scope
This guide maps where to look in `bd pms`, and documents how each SP behaves at IO level.

- Folder with SQL runtime assets: `PMS/bd pms`
- Rule: keep only schema files and `sp_*.sql` files there.
- Error model used by many procedures: `SIGNAL SQLSTATE '45000'` for business validation errors.

## Schema Files
- `schema_u508158532_rodbd.sql` -> main schema snapshot.
- `ota_ical_schema.sql` -> OTA iCal schema objects.
- `ota_reservation_source_schema.sql` -> OTA source schema objects.
- `ota_account_tables.sql` -> OTA account tables.
- `pms_settings_payment_catalog.sql` -> payment catalog settings table.

## Core Workflow Contracts

### Hold to Confirm Reservation
- `sp_create_reservation_hold.sql`
  Inputs: property, room, check-in/out, notes, user.
  Behavior: validates availability and blocks overlap, calculates estimate/rate snapshot, creates reservation in `apartado` without guest and without folio.
  Writes: `reservation`.
  Output: reservation row (with room/category/property join).
- `sp_reservation_confirm_hold.sql`
  Inputs: company, reservation id, guest id, lodging catalog, optional total override, adults/children, actor user.
  Behavior: only accepts reservations currently in `apartado`, requires guest + lodging catalog, recalculates totals, changes status to `confirmado`, creates principal folio and lodging charge, derives related percent line items.
  Writes: `reservation`, `folio`, `line_item` (via called procedures).
  Output: confirmed reservation row with guest and room/property details.

### Create Reservation Directly
- `sp_create_reservation.sql`
  Inputs: property/room, dates, guest fields, adults/children, lodging catalog/pricing overrides, source/OTA, user.
  Behavior: validates dates/availability, creates guest (or reuses by email), creates confirmed reservation, folio and initial lodging charges in one flow.
  Writes: guest/reservation/folio/line-item related tables.
  Output: result set describing created reservation (and error result sets in some validation branches).

### Update Reservation
- `sp_reservation_update.sql`, `sp_reservation_update_v2.sql`
  Inputs: company, reservation id, status/source/OTA/room/dates/pax/notes.
  Behavior: validates company ownership, room overlap, source resolution, and status transitions; can reassign room/property based on encoded room target format.
  Writes: primarily `reservation` (and can soft-void folio/line_item on cancellation).
  Output: updated reservation row; errors via `SIGNAL 45000`.

### Calendar and Availability
- `sp_property_room_calendar.sql`: returns occupancy grid dataset for calendar UI.
- `sp_search_availability.sql`: returns available room/category options for date range.
- `sp_create_room_block.sql`, `sp_update_room_block.sql`, `sp_get_room_block.sql`, `sp_list_room_blocks.sql`: block lifecycle CRUD/read for room availability exclusions.

## Fast Lookup (open these first)
- Create reservation: `sp_create_reservation.sql`
- Create hold (apartado): `sp_create_reservation_hold.sql`
- Confirm hold: `sp_reservation_confirm_hold.sql`
- Update reservation/status/room/dates: `sp_reservation_update.sql`
- Add folio to reservation: `sp_reservation_add_folio.sql`
- Reservation notes: `sp_reservation_note_data.sql`, `sp_reservation_note_upsert.sql`
- Reservation totals report: `sp_reservation_totals_report.sql`
- Search availability: `sp_search_availability.sql`
- Calendar room grid: `sp_property_room_calendar.sql`

## Full SP IO Catalog
Columns: SP file, main purpose, IN inputs, direct writes (detected `INSERT/UPDATE/DELETE`), called procedures, output model.

### Reservation lifecycle
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_create_reservation.sql` | Create records | p_property_code, p_room_code, p_check_in_str, p_check_out_str, p_guest_email, p_guest_names, p_guest_phone, p_adults, p_children, p_lodging_catalog_id, p_total_cents_override, p_fixed_child_unit_price_cents, p_fixed_child_total_cents, p_source, p_id_ota_account, p_id_user | folio, guest, reservation | sp_line_item_percent_derived_upsert, sp_rateplan_calc_total, sp_sale_item_upsert | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_create_reservation_hold.sql` | Create records | p_property_code, p_room_code, p_check_in, p_check_out, p_notes, p_id_user | reservation | sp_rateplan_calc_total | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_list_reservations_by_company.sql` | List records | p_company_code, p_from, p_to | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_list_reservations_by_property.sql` | List records | p_property_code, p_from, p_to | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_add_folio.sql` | Business operation | p_company_code, p_reservation_id, p_guest_id, p_lodging_catalog_id, p_total_cents_override, p_adults, p_children, p_actor_user_id | folio, reservation | sp_line_item_percent_derived_upsert, sp_rateplan_calc_total, sp_sale_item_upsert | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_confirm_hold.sql` | Confirm/transition state | p_company_code, p_reservation_id, p_guest_id, p_lodging_catalog_id, p_total_cents_override, p_adults, p_children, p_actor_user_id | folio, reservation | sp_line_item_percent_derived_upsert, sp_rateplan_calc_total, sp_sale_item_upsert | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_message_send.sql` | Business operation | p_company_code, p_reservation_id, p_message_template_id, p_sent_by, p_sent_to_phone, p_message_title, p_message_body, p_channel | reservation_message_log | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_note_data.sql` | Read/query data | p_company_code, p_id_reservation, p_note_type, p_show_inactive | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_note_upsert.sql` | Create/update records | p_action, p_id_reservation_note, p_id_reservation, p_note_type, p_note_text, p_is_active, p_company_code, p_actor_user_id | reservation_note | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_totals_report.sql` | Report/query output | p_company_code, p_reservation_ids | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_update.sql` | Update records/state | p_company_code, p_reservation_id, p_status, p_source, p_id_ota_account, p_room_code, p_check_in_date, p_check_out_date, p_adults, p_children, p_notes_internal, p_notes_guest, p_actor_user_id | folio, line_item, reservation | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_reservation_update_v2.sql` | Update records/state | p_company_code, p_reservation_id, p_status, p_source, p_id_ota_account, p_room_code, p_check_in_date, p_check_out_date, p_adults, p_children, p_reservation_code, p_notes_internal, p_notes_guest, p_actor_user_id | reservation | sp_reservation_update | Result set via SELECT |

### Calendar and room blocks
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_create_room_block.sql` | Create records | p_property_code, p_room_code, p_check_in, p_check_out, p_notes, p_actor_user | room_block | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_get_room_block.sql` | Business operation | p_room_block_id | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_list_room_blocks.sql` | List records | p_company_code, p_property_code, p_from, p_to | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_property_room_calendar.sql` | Business operation | p_property_code, p_from, p_days | tmp_calendar_days | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_search_availability.sql` | Search/availability query | p_company_code, p_check_in, p_nights, p_people | - | - | Result set via SELECT |
| `sp_update_room_block.sql` | Update records/state | p_room_block_id, p_property_code, p_room_code, p_start_date, p_end_date, p_description, p_actor_user | room_block | - | Result set via SELECT; errors via SIGNAL 45000 |

### Property room guest user
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_app_user_upsert.sql` | Create/update records | p_company_code, p_id_user, p_email, p_password, p_names, p_last_name, p_maiden_name, p_phone, p_locale, p_timezone, p_is_owner, p_is_active, p_display_name, p_notes, p_actor_user_id | app_user | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_get_company_properties.sql` | Business operation | p_company_code | - | - | Result set via SELECT |
| `sp_guest_upsert.sql` | Create/update records | p_email, p_names, p_last_name, p_maiden_name, p_phone, p_language, p_marketing_opt, p_blacklisted, p_notes | guest | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_property_upsert.sql` | Create/update records | p_company_code, p_property_code, p_name, p_description, p_email, p_phone, p_website, p_address_line1, p_address_line2, p_city, p_state, p_postal_code, p_country, p_timezone, p_currency, p_check_out_time, p_order_index, p_is_active, p_notes, p_has_wifi, p_has_parking, p_has_shared_kitchen, p_has_dining_area, p_has_cleaning_service, p_has_shared_laundry, p_has_purified_water, p_has_security_24h, p_has_self_checkin, p_has_pool, p_has_jacuzzi, p_has_garden_patio, p_has_terrace_rooftop, p_has_hammocks_loungers, p_has_bbq_area, p_has_beach_access, p_has_panoramic_views, p_has_outdoor_lounge, p_offers_airport_transfers, p_offers_tours_activities, p_has_breakfast_available, p_offers_bike_rental, p_has_luggage_storage, p_is_pet_friendly, p_has_accessible_spaces, p_id_owner_payment_obligation_catalog, p_amenities_is_active, p_actor_user_id | property, property_amenities | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_room_upsert.sql` | Create/update records | p_property_code, p_room_code, p_category_code, p_rateplan_code, p_name, p_description, p_capacity_total, p_max_adults, p_max_children, p_status, p_housekeeping_status, p_floor, p_building, p_bed_config, p_color_hex, p_order_index, p_is_active, p_id_room | room | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_roomcategory_upsert.sql` | Create/update records | p_property_code, p_category_code, p_name, p_description, p_base_occupancy, p_max_occupancy, p_order_index, p_default_base_price_cents, p_min_price_cents, p_image_url, p_rateplan_code, p_color_hex, p_has_air_conditioning, p_has_fan, p_has_tv, p_has_private_wifi, p_has_minibar, p_has_safe_box, p_has_workspace, p_includes_bedding_towels, p_has_iron_board, p_has_closet_rack, p_has_private_balcony_terrace, p_has_view, p_has_private_entrance, p_has_hot_water, p_includes_toiletries, p_has_hairdryer, p_includes_clean_towels, p_has_coffee_tea_kettle, p_has_basic_utensils, p_has_basic_food_items, p_is_private, p_is_shared, p_has_shared_bathroom, p_has_private_bathroom, p_amenities_is_active, p_calendar_display_amenities_csv, p_is_active, p_actor_user_id, p_id_category | category_amenities, category_calendar_amenity_display, roomcategory | - | Result set via SELECT; errors via SIGNAL 45000 |

### Folios charges payments
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_folio_recalc.sql` | Calculate values | p_id_folio | folio, reservation | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_folio_upsert.sql` | Create/update records | p_action, p_id_folio, p_id_reservation, p_folio_name, p_due_date, p_bill_to_type, p_bill_to_id, p_notes, p_currency, p_created_by | folio, requires | sp_folio_recalc | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_line_item_payment_meta_upsert.sql` | Create/update records | p_id_line_item, p_method, p_reference, p_status, p_updated_by | line_item | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_line_item_percent_derived_upsert.sql` | Create/update records | p_id_folio, p_id_reservation, p_parent_catalog_id, p_service_date, p_created_by | id_line_item_parent, line_item, line_item_hierarchy, tmp_lipdu_children, tmp_lipdu_queue | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_line_item_type_upsert.sql` | Create/update records | p_action, p_id_line_item, p_company_code, p_item_type, p_created_by | line_item | sp_folio_recalc | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_obligation_data.sql` | Read/query data | p_company_code, p_property_code, p_date_from, p_date_to, p_search, p_payment_status, p_show_inactive, p_id_reservation, p_id_folio, p_limit_rows | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_obligation_paid_upsert.sql` | Create/update records | p_company_code, p_id_line_item, p_mode, p_amount_cents, p_id_obligation_payment_method, p_payment_notes, p_created_by | line_item, obligation_payment_log | sp_folio_recalc | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_refund_upsert.sql` | Create/update records | p_action, p_id_refund, p_id_payment, p_amount_cents, p_reason, p_reference, p_created_by | line_item, refund | sp_folio_recalc | Result set via SELECT; errors via SIGNAL 45000 |

### Sale item catalogs
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_sale_item_catalog_calc_upsert.sql` | Create/update records | p_action, p_id_item, p_id_parent, p_component_ids_csv, p_component_signs_csv, p_created_by | is_positive, line_item_catalog_calc | - | No explicit result set; errors via SIGNAL 45000 |
| `sp_sale_item_catalog_clone.sql` | Business operation | p_company_code, p_source_item_id, p_clone_item_name, p_created_by | add_to_father_total, is_positive, line_item_catalog, line_item_catalog_calc, line_item_catalog_parent | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_sale_item_catalog_data.sql` | Read/query data | p_company_code, p_property_code, p_show_inactive, p_item_id, p_category_id | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_sale_item_catalog_parent_total_upsert.sql` | Create/update records | p_action, p_id_item, p_id_parent, p_add_to_father_total, p_show_in_folio_relation, p_created_by | add_to_father_total, line_item_catalog_parent | - | No explicit result set; errors via SIGNAL 45000 |
| `sp_sale_item_catalog_upsert.sql` | Create/update records | p_action, p_id_item, p_company_code, p_catalog_type, p_id_category, p_parent_ids, p_item_name, p_description, p_unit_price_cents, p_is_percent, p_tax_rule_ids, p_show_in_folio, p_allow_negative, p_is_active, p_add_to_father_total, p_created_by | add_to_father_total, line_item_catalog, line_item_catalog_parent | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_sale_item_category_data.sql` | Read/query data | p_company_code, p_property_code, p_show_inactive, p_category_id | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_sale_item_category_upsert.sql` | Create/update records | p_action, p_id_category, p_company_code, p_property_code, p_category_name, p_description, p_is_active, p_created_by, p_id_parent_category | sale_item_category | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_sale_item_child_upsert.sql` | Create/update records | p_action, p_id_sale_item, p_id_parent_sale_item, p_id_folio, p_id_reservation, p_id_sale_item_catalog, p_service_date, p_unit_price_cents, p_status, p_created_by | - | sp_sale_item_upsert | No explicit result set |
| `sp_sale_item_report_data.sql` | Read/query data | p_company_code, p_property_code, p_from, p_to, p_search, p_status, p_folio_status, p_parent_category_id, p_category_id, p_catalog_id, p_min_total_cents, p_max_total_cents, p_has_tax, p_show_inactive, p_show_canceled_reservations | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_sale_item_upsert.sql` | Create/update records | p_action, p_id_sale_item, p_id_folio, p_id_reservation, p_id_sale_item_catalog, p_description, p_service_date, p_unit_price_cents, p_discount_amount_cents, p_status, p_created_by | line_item, reservation, tmp_siu_recalc_parents | sp_folio_recalc, sp_line_item_percent_derived_upsert | Result set via SELECT; errors via SIGNAL 45000 |

### Rateplan pricing
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_rateplan_calc_total.sql` | Calculate values | p_property_id, p_rateplan_id, p_room_id, p_category_id, p_check_in, p_check_out | tmp_rateplan_calc, tmp_rateplan_days | - | OUT params: p_total_cents, p_avg_nightly_cents, p_breakdown_json |
| `sp_rateplan_calendar.sql` | Business operation | p_property_code, p_rateplan_code, p_category_code, p_room_code, p_from, p_days | tmp_rateplan_calc, tmp_rateplan_days | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_rateplan_override_upsert.sql` | Create/update records | p_property_code, p_rateplan_code, p_id_rateplan_override, p_id_category, p_id_room, p_override_date, p_price_cents, p_notes, p_is_active | rateplan_override | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_rateplan_pricing_upsert.sql` | Create/update records | p_property_code, p_rateplan_code, p_use_season, p_use_occupancy, p_is_active | rateplan_pricing | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_rateplan_season_upsert.sql` | Create/update records | p_property_code, p_rateplan_code, p_id_rateplan_season, p_season_name, p_start_date, p_end_date, p_priority, p_is_active | rateplan_season | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_rateplan_upsert.sql` | Create/update records | p_property_code, p_rateplan_code, p_name, p_description, p_currency, p_refundable, p_min_stay, p_max_stay, p_effective_from, p_effective_to, p_is_active, p_actor_user_id | rateplan | - | Result set via SELECT; errors via SIGNAL 45000 |

### OTA and iCal
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_ota_account_data.sql` | Read/query data | p_company_code, p_property_code, p_include_inactive, p_id_ota_account | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_ota_account_info_catalog_sync.sql` | Business operation | p_company_code, p_id_ota_account, p_catalog_ids_csv, p_actor_user_id | ota_account_info_catalog | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_ota_account_lodging_sync.sql` | Business operation | p_company_code, p_id_ota_account, p_catalog_ids_csv, p_actor_user_id | ota_account_lodging_catalog | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_ota_account_upsert.sql` | Create/update records | p_mode, p_id_ota_account, p_company_code, p_property_code, p_platform, p_ota_name, p_external_code, p_contact_email, p_timezone, p_notes, p_id_service_fee_payment_catalog, p_is_active, p_actor_user_id | ota_account, ota_account_info_catalog, ota_account_lodging_catalog | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_ota_ical_feed_lodging_data.sql` | Read/query data | p_company_code, p_id_ota_ical_feed | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_ota_ical_feed_lodging_upsert.sql` | Create/update records | p_company_code, p_id_ota_ical_feed, p_lodging_catalog_ids, p_updated_by | ota_ical_feed_lodging_catalog, sort_order, tmp_ota_lodging_ids | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_ota_ical_lodging_catalog_data.sql` | Read/query data | p_company_code, p_id_property | - | - | Result set via SELECT; errors via SIGNAL 45000 |

### Activities
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_activity_book.sql` | Business operation | p_company_id, p_activity_id, p_reservation_id, p_scheduled_at, p_num_adults, p_num_children, p_price_cents, p_currency, p_status | activity_booking, activity_booking_reservation, is_active | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_activity_booking_upsert.sql` | Create/update records | p_company_id, p_booking_id, p_activity_id, p_reservation_ids_csv, p_scheduled_at, p_num_adults, p_num_children, p_price_cents, p_currency, p_status, p_notes, p_user_id, p_action | activity_booking, activity_booking_reservation, is_active | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_activity_bookings_list.sql` | List records | p_company_id, p_company_code, p_property_id, p_property_code, p_activity_id, p_activity_code, p_from, p_to, p_booking_id | - | - | Result set via SELECT |
| `sp_activity_cancel.sql` | Business operation | p_booking_id, p_note | activity_booking | - | Result set via SELECT |
| `sp_activity_upsert.sql` | Create/update records | p_id_company, p_id_property, p_code, p_name, p_description, p_duration_minutes, p_base_price_cents, p_id_sale_item_catalog, p_currency, p_capacity_default, p_location, p_is_active | activity | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_get_company_activities.sql` | Business operation | p_company_code | - | - | Result set via SELECT |

### Reports accounting
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_accounting_report_data.sql` | Read/query data | p_company_code, p_property_code, p_from, p_to, p_lodging_ids | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_catalog_options.sql` | Report/query output | p_company_code, p_property_code | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_config_data.sql` | Read/query data | p_company_code, p_report_key | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_config_upsert.sql` | Create/update records | p_company_code, p_report_key, p_report_name, p_column_order, p_lodging_ids, p_cleaning_id, p_iva_id, p_ish_id, p_extra_ids, p_actor_user_id | display_name, report_config, report_config_column | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_definition_column_upsert.sql` | Create/update records | p_action, p_id_report_config_column, p_id_report_config, p_column_key, p_column_source, p_source_field_key, p_id_line_item_catalog, p_display_name, p_display_category, p_data_type, p_aggregation, p_format_hint, p_order_index, p_is_visible, p_is_filterable, p_filter_operator_default, p_actor_user_id | report_config_column | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_definition_data.sql` | Read/query data | p_company_code, p_id_report_config, p_report_key, p_include_inactive | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_definition_filter_upsert.sql` | Create/update records | p_action, p_id_report_config_filter, p_id_report_config, p_filter_key, p_operator_key, p_value_text, p_value_from_text, p_value_to_text, p_value_list_text, p_logic_join, p_order_index, p_is_active, p_actor_user_id | report_config_filter | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_definition_run_data.sql` | Read/query data | p_company_code, p_id_report_config, p_from, p_to, p_limit | tmp_report_cols | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_definition_upsert.sql` | Create/update records | p_action, p_company_code, p_id_report_config, p_report_key, p_report_name, p_report_type, p_line_item_type_scope, p_description, p_actor_user_id | report_config | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_report_field_catalog_data.sql` | Read/query data | p_company_code, p_property_code, p_report_type | - | - | Result set via SELECT; errors via SIGNAL 45000 |

### Settings theme portal
| SP | Purpose | Inputs | Writes | Calls | Outputs |
|---|---|---|---|---|---|
| `sp_message_template_upsert.sql` | Create/update records | p_company_code, p_property_code, p_template_code, p_title, p_body, p_is_active, p_id_message_template | message_template | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_pms_settings_data.sql` | Read/query data | p_company_code, p_property_code | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_pms_settings_upsert.sql` | Create/update records | p_company_code, p_property_code, p_lodging_catalog_ids, p_interest_catalog_ids, p_payment_catalog_ids, p_created_by | is_active, pms_settings_interest_catalog, pms_settings_lodging_catalog, pms_settings_payment_catalog | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_pms_theme_data.sql` | Read/query data | p_company_code | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_pms_theme_upsert.sql` | Create/update records | p_company_code, p_theme_code, p_actor_user_id | pms_company_theme, theme_code | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_portal_activity_data.sql` | Read/query data | p_company_code, p_property_code, p_search, p_only_active, p_activity_id | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_portal_app_user_data.sql` | Read/query data | p_company_code, p_search, p_property_code, p_only_active, p_user_id | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_portal_guest_data.sql` | Read/query data | p_company_code, p_search, p_only_active, p_guest_id, p_actor_user_id | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_portal_property_data.sql` | Read/query data | p_company_code, p_search, p_only_active, p_property_code | - | - | Result set via SELECT; errors via SIGNAL 45000 |
| `sp_portal_reservation_data.sql` | Read/query data | p_company_code, p_property_code, p_status, p_from, p_to, p_reservation_id, p_actor_user_id | - | - | Result set via SELECT; errors via SIGNAL 45000 |

## Maintenance Notes
- If a new SP is added, include it in the right domain section and ensure IO row is updated.
- Keep migration/fix/import scripts outside `bd pms` (example: `PMS/migrations`).
- This file mixes manual workflow notes + auto-extracted IO metadata for faster onboarding and AI routing.
