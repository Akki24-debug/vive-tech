# Modules Map (PMS)

Last update: 2026-03-03

## Scope
Guia breve de modulos PHP en `public_html/modules` para ubicar rapido donde tocar segun la funcionalidad.

## Grouping

### Operacion (reservas y calendario)
- `calendar.php`: calendario principal, acciones rapidas (crear hold, registrar pago, room blocks, mover/editar reserva).
- `reservations.php`: vista individual/listado de reservaciones, folios, cargos, pagos, notas, estatus.
- `reservation_wizard.php`: wizard de creacion/edicion/confirmacion y carga de conceptos + pagos iniciales.
- `reservation_wizard_mobile.php`: variante mobile del wizard.
- `dashboard.php`: KPIs operativos y accesos rapidos.
- `dashboard_mobile.php`: variante mobile del dashboard.

### Catalogos y configuracion operativa
- `properties.php`: CRUD de propiedades.
- `rooms.php`: CRUD de habitaciones y relacion con categorias/rateplan.
- `categories.php`: CRUD de categorias (amenidades y comportamiento en calendario).
- `rateplans.php`: configuracion de tarifas/rateplan/overrides.
- `guests.php`: CRUD de huespedes.
- `app_users.php`: usuarios internos del PMS.
- `sale_items.php`: catalogo de conceptos (sale_item/payment/padres/derivados).
- `settings.php`: settings por empresa/propiedad (conceptos permitidos, metodos, theme, etc.).
- `otas.php`: cuentas OTA y sincronizaciones relacionadas.
- `ota_ical.php`: feeds iCal OTA.

### Finanzas
- `payments.php`: grid y operaciones de pagos.
- `incomes.php`: ingresos y conciliaciones.
- `obligations.php`: obligaciones y pagos asociados.
- `line_item_report.php`: reporte detallado de line items.
- `sale_item_report.php`: reporte de cargos por concepto.

### Reporteria y comunicacion
- `reports.php`: builder/runner de reportes y reportes contables.
- `reports_builder_tab.php`: UI auxiliar del builder.
- `messages.php`: plantillas y envio de mensajes (WhatsApp).
- `activities.php`: actividades (catalogo/reservas/cancelaciones).

## Quick Lookup (task -> module)
- Calendario y operacion dia a dia: `calendar.php`
- Crear/editar reservacion paso a paso: `reservation_wizard.php`
- Ver/operar una reservacion completa: `reservations.php`
- Cobros/pagos en detalle: `reservations.php`, `payments.php`, `incomes.php`, `obligations.php`
- Catalogo de conceptos y derivados: `sale_items.php`
- Configuracion general por propiedad: `settings.php`, `properties.php`, `rooms.php`, `categories.php`, `rateplans.php`
- OTA: `otas.php`, `ota_ical.php`
- Reportes: `reports.php`, `line_item_report.php`, `sale_item_report.php`

## SP Footprint Snapshot (por modulo)
Referencia rapida de uso de SPs detectado en codigo.

- `activities.php`: `sp_activity_*`, `sp_portal_activity_data`
- `app_users.php`: `sp_app_user_upsert`, `sp_portal_app_user_data`
- `calendar.php`: `sp_property_room_calendar`, `sp_reservation_update`, `sp_sale_item_upsert`, `sp_create_room_block`, etc.
- `categories.php`: `sp_roomcategory_upsert`, `sp_portal_property_data`
- `dashboard.php`: `sp_obligation_data`, `sp_obligation_paid_upsert`, `sp_reservation_update`
- `guests.php`: `sp_guest_upsert`, `sp_portal_guest_data`
- `incomes.php`: `sp_folio_recalc`
- `messages.php`: `sp_message_template_upsert`, `sp_reservation_message_send`
- `obligations.php`: `sp_obligation_data`, `sp_obligation_paid_upsert`
- `otas.php`: `sp_ota_account_data`, `sp_ota_account_upsert`, `sp_ota_account_lodging_sync`
- `properties.php`: `sp_property_upsert`, `sp_portal_property_data`
- `rateplans.php`: `sp_rateplan_upsert`, `sp_rateplan_override_upsert`
- `reports.php`: `sp_report_*`, `sp_accounting_report_data`, `sp_reservation_totals_report`
- `reservations.php`: `sp_reservation_update*`, `sp_folio_*`, `sp_sale_item_*`, `sp_refund_upsert`
- `reservation_wizard.php`: `sp_create_reservation*`, `sp_reservation_confirm_hold`, `sp_reservation_update`, `sp_sale_item_upsert`, etc.
- `rooms.php`: `sp_room_upsert`, `sp_portal_property_data`
- `sale_items.php`: `sp_sale_item_catalog_*`, `sp_sale_item_category_*`, `sp_line_item_type_upsert`
- `sale_item_report.php`: `sp_sale_item_report_data`
- `settings.php`: `sp_pms_settings_*`, `sp_pms_theme_*`, `sp_sale_item_*`, `sp_folio_recalc`

## Notes
- Este mapa es funcional (para navegar codigo), no un contrato de BD.
- Para detalle IO de SPs, seguir usando `SQL_SP_MAP.md` y `SQL_SP_AI_PLAYBOOK.md`.
