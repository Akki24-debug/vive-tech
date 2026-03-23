---
doc_type: ai_playbook
domain: PMS SQL
source_folder: ../bd
updated_at: 2026-03-02
owner: equipo PMS
---

# SQL AI Playbook (Router de Stored Procedures)

## Objetivo
Guia rapida para que una persona o IA encuentre el SP correcto en segundos segun la intencion funcional.

## Contrato de uso para IA
1. Identifica intencion (crear, editar, mover, cobrar, reportar, OTA, etc.).
2. Abre primero el `primary_sp` del bloque de ruteo.
3. Si necesitas contexto, abre despues los `secondary_sp` del mismo bloque.
4. Si la accion cruza modulos (ej. reservacion + folio), usa ambos bloques.

## Router rapido (intencion -> archivo)

```yaml
routes:
  - intent: crear_reservacion
    triggers: ["crear reservacion", "nueva reserva", "reserva rapida"]
    primary_sp: sp_create_reservation.sql
    secondary_sp: [sp_create_reservation_hold.sql, sp_reservation_confirm_hold.sql]

  - intent: actualizar_reservacion
    triggers: ["editar reservacion", "cambiar estatus", "mover reservacion", "cambiar habitacion", "check-in", "check-out"]
    primary_sp: sp_reservation_update.sql
    secondary_sp: [sp_reservation_update_v2.sql, sp_reservation_add_folio.sql]

  - intent: notas_y_mensajes_reservacion
    triggers: ["nota de reservacion", "mensajeria", "mensaje a huesped"]
    primary_sp: sp_reservation_note_upsert.sql
    secondary_sp: [sp_reservation_note_data.sql, sp_reservation_message_send.sql]

  - intent: calendario_disponibilidad
    triggers: ["calendario", "disponibilidad", "ocupacion", "grid de habitaciones"]
    primary_sp: sp_property_room_calendar.sql
    secondary_sp: [sp_search_availability.sql, sp_list_reservations_by_property.sql, sp_list_reservations_by_company.sql]

  - intent: bloqueos_habitacion
    triggers: ["bloquear habitacion", "room block", "editar bloqueo"]
    primary_sp: sp_create_room_block.sql
    secondary_sp: [sp_update_room_block.sql, sp_get_room_block.sql, sp_list_room_blocks.sql]

  - intent: folios_cargos_pagos
    triggers: ["folio", "cargo", "pago", "balance", "recalcular folio"]
    primary_sp: sp_folio_upsert.sql
    secondary_sp: [sp_folio_recalc.sql, sp_line_item_type_upsert.sql, sp_line_item_payment_meta_upsert.sql, sp_refund_upsert.sql, sp_obligation_data.sql, sp_obligation_paid_upsert.sql]

  - intent: catalogo_conceptos_venta
    triggers: ["sale item", "concepto", "catalogo", "categoria de concepto"]
    primary_sp: sp_sale_item_catalog_data.sql
    secondary_sp: [sp_sale_item_catalog_upsert.sql, sp_sale_item_catalog_calc_upsert.sql, sp_sale_item_catalog_parent_total_upsert.sql, sp_sale_item_category_data.sql, sp_sale_item_category_upsert.sql, sp_sale_item_upsert.sql, sp_sale_item_child_upsert.sql]

  - intent: rateplan_precios
    triggers: ["rateplan", "tarifas por noche", "temporadas", "override"]
    primary_sp: sp_rateplan_upsert.sql
    secondary_sp: [sp_rateplan_calendar.sql, sp_rateplan_calc_total.sql, sp_rateplan_pricing_upsert.sql, sp_rateplan_season_upsert.sql, sp_rateplan_override_upsert.sql]

  - intent: ota_integraciones
    triggers: ["ota", "airbnb", "booking", "ical", "sincronizacion ota"]
    primary_sp: sp_ota_account_upsert.sql
    secondary_sp: [sp_ota_account_data.sql, sp_ota_account_info_catalog_sync.sql, sp_ota_account_lodging_sync.sql, sp_ota_ical_feed_lodging_upsert.sql, sp_ota_ical_feed_lodging_data.sql, sp_ota_ical_lodging_catalog_data.sql]

  - intent: actividades
    triggers: ["actividad", "reservar actividad", "cancelar actividad"]
    primary_sp: sp_activity_upsert.sql
    secondary_sp: [sp_activity_book.sql, sp_activity_booking_upsert.sql, sp_activity_bookings_list.sql, sp_activity_cancel.sql, sp_get_company_activities.sql]

  - intent: propiedades_habitaciones_huespedes_usuarios
    triggers: ["propiedad", "habitacion", "categoria de habitacion", "huesped", "usuario"]
    primary_sp: sp_property_upsert.sql
    secondary_sp: [sp_room_upsert.sql, sp_roomcategory_upsert.sql, sp_guest_upsert.sql, sp_app_user_upsert.sql, sp_get_company_properties.sql]

  - intent: reportes_contabilidad
    triggers: ["reporte", "accounting", "corrida de reporte", "configurar reporte"]
    primary_sp: sp_report_definition_run_data.sql
    secondary_sp: [sp_accounting_report_data.sql, sp_report_catalog_options.sql, sp_report_config_data.sql, sp_report_config_upsert.sql, sp_report_definition_data.sql, sp_report_definition_upsert.sql, sp_report_definition_column_upsert.sql, sp_report_definition_filter_upsert.sql, sp_report_field_catalog_data.sql]

  - intent: tema_config_portal
    triggers: ["settings", "tema", "plantilla de mensaje", "portal"]
    primary_sp: sp_pms_settings_upsert.sql
    secondary_sp: [sp_pms_settings_data.sql, sp_pms_theme_upsert.sql, sp_pms_theme_data.sql, sp_message_template_upsert.sql, sp_portal_reservation_data.sql, sp_portal_property_data.sql, sp_portal_guest_data.sql, sp_portal_app_user_data.sql, sp_portal_activity_data.sql]
```

## Mapa por prefijo (heuristica rapida)
- `sp_reservation_*` -> flujo de reservaciones (crear/editar/notas/mensajes/totales)
- `sp_folio_*`, `sp_line_item_*`, `sp_obligation_*`, `sp_refund_*` -> cobros/pagos/obligaciones
- `sp_rateplan_*` -> tarifas y calculo de hospedaje
- `sp_ota_*` -> integraciones OTA / iCal
- `sp_report_*`, `sp_accounting_*` -> reporteria/contabilidad
- `sp_property_*`, `sp_room*`, `sp_guest_*`, `sp_app_user_*` -> catalogos base operativos

## Entry points recomendados por problema
- Error de cambio de estatus de reservacion: `sp_reservation_update.sql`
- Problema de disponibilidad en calendario: `sp_property_room_calendar.sql`
- Totales de hospedaje inconsistentes: `sp_rateplan_calc_total.sql`, `sp_folio_recalc.sql`
- Datos OTA no reflejados: `sp_ota_account_*` y `sp_ota_ical_*`
- Grid/listado de reservaciones: `sp_list_reservations_by_company.sql`, `sp_list_reservations_by_property.sql`

## Reglas de mantenimiento
- Si agregas un SP nuevo, actualiza este archivo en:
  - bloque `routes`
  - bloque de prefijo o entry points (si aplica)
- Mantener `bd` solo con schema y `sp_*.sql`.
- Scripts de migracion/fix/import van en `Core de Operaciones/PMS/migrations` (o carpeta equivalente), no aqui.
