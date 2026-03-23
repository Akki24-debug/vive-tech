---
doc_type: ai_playbook
domain: PMS Modules
source_folder: public_html/modules
updated_at: 2026-03-03
owner: equipo PMS
---

# Modules AI Playbook (Router de Modulos)

## Objetivo
Ayudar a una IA/persona a decidir rapido en que modulo tocar cambios funcionales.

## Router rapido (intent -> module)

```yaml
routes:
  - intent: calendario_operacion
    triggers: ["calendario", "drag/drop", "room block", "quick payment", "quick reservation"]
    primary_module: calendar.php
    secondary_modules: [reservations.php, reservation_wizard.php]

  - intent: crear_o_editar_reserva
    triggers: ["wizard", "nueva reservacion", "confirmar apartado", "agregar cargos hospedaje"]
    primary_module: reservation_wizard.php
    secondary_modules: [reservations.php, calendar.php]

  - intent: detalle_reservacion
    triggers: ["folio", "line items", "notas", "estatus", "check-in", "check-out"]
    primary_module: reservations.php
    secondary_modules: [calendar.php, reservation_wizard.php]

  - intent: pagos_ingresos_obligaciones
    triggers: ["pago", "income", "obligacion", "conciliacion", "balance"]
    primary_module: payments.php
    secondary_modules: [incomes.php, obligations.php, reservations.php]

  - intent: catalogo_conceptos
    triggers: ["sale items", "conceptos", "derivados", "parent/child", "catalog_type"]
    primary_module: sale_items.php
    secondary_modules: [settings.php, reservations.php, reservation_wizard.php]

  - intent: configuracion_sistema
    triggers: ["settings", "configuracion", "metodos de pago", "theme", "defaults"]
    primary_module: settings.php
    secondary_modules: [properties.php, rooms.php, categories.php, rateplans.php]

  - intent: estructura_hospedaje
    triggers: ["propiedades", "habitaciones", "categorias", "amenidades", "rateplan"]
    primary_module: properties.php
    secondary_modules: [rooms.php, categories.php, rateplans.php]

  - intent: ota_integracion
    triggers: ["ota", "airbnb", "booking", "ical", "sync"]
    primary_module: otas.php
    secondary_modules: [ota_ical.php, reservations.php, calendar.php]

  - intent: reporteria
    triggers: ["reporte", "analytics", "builder", "accounting report"]
    primary_module: reports.php
    secondary_modules: [line_item_report.php, sale_item_report.php, dashboard.php]

  - intent: comunicacion
    triggers: ["whatsapp", "templates", "mensajes"]
    primary_module: messages.php
    secondary_modules: [reservations.php]

  - intent: actividades
    triggers: ["activities", "booking de actividad", "cancel activity"]
    primary_module: activities.php
    secondary_modules: [dashboard.php]
```

## Heuristicas rapidas
- Si el cambio impacta timeline/drag/hover del calendario: empieza en `calendar.php`.
- Si impacta flujo paso a paso de crear/confirmar: `reservation_wizard.php`.
- Si impacta estado financiero de una reserva existente: `reservations.php`.
- Si impacta disponibilidad de conceptos en UI: revisar `settings.php` + modulo consumidor (`calendar.php`, `reservations.php`, `reservation_wizard.php`).

## Convenciones utiles
- SP calls: `pms_call_procedure('sp_xxx', [...])`
- DB directa: `pms_get_connection()` + `prepare/query/exec`
- Evitar cambios aislados en un solo modulo cuando el flujo cruza `calendar` <-> `reservations` <-> `wizard`.

## Checklist para cambios
1. Identificar modulo primario por intent.
2. Buscar modulos espejo (calendar vs reservations vs wizard).
3. Verificar si la fuente de datos viene de SP o SQL directo.
4. Si toca UI + backend, validar mensaje de error visible y consistencia de labels.
5. Revisar persistencia de contexto de navegacion (return params/calendar state).
