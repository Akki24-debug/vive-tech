# ACTION CONTROL MASTER

Status: draft v1 generated from current PMS code audit
Audit date: 2026-03-23
Scope: `public_html/index.php`, routed modules in `public_html/modules/`, and active JSON endpoints in `public_html/api/`

## Objetivo

Este documento define el control general de acciones del PMS para que el mismo permiso sirva igual en:

- PMS web
- IA operativa
- app movil futura

La regla base propuesta es:

- los permisos deben ser de negocio, no de pantalla
- si la misma accion existe en web, IA o movil, debe reutilizar el mismo `permission_code`
- cada accion visible para el usuario debe tener una descripcion UI clara

## Convencion propuesta

- Vista de modulo: `<dominio>.view`
- Accion simple: `<dominio>.<accion>`
- Subrecurso: `<dominio>.<subrecurso>.<accion>`
- Accion masiva: sufijo claro como `.bulk_create`, `.bulk_delete`, `.apply_all_visible`

Ejemplos:

- `reservations.view`
- `reservations.folio.close`
- `calendar.block.bulk_delete`
- `reports.template.clone`

## Roles base sugeridos

- `Owner/Admin`: acceso total
- `Operaciones`: operacion diaria, disponibilidad, bloques, reservas, actividades, catalogos operativos
- `Recepcion`: frontdesk, huespedes, reservas, check-in/check-out, pagos y mensajes
- `Finanzas`: pagos, ingresos, obligaciones, refunds, reportes
- `Solo Lectura`: solo vistas, corrida de reportes y exportaciones si se desea mantener el comportamiento actual

## Alcance del inventario

Incluye:

- acciones CRUD
- acciones operativas con boton o submit dedicado
- endpoints actuales usados por el PMS

No incluye:

- filtros locales
- abrir/cerrar tabs
- resets de formularios
- navegacion sin efecto de negocio

## Dashboard

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver dashboard | Ver metricas operativas, actividad y resumen de obligaciones | `dashboard.view` | Owner/Admin, Operaciones, Recepcion, Finanzas, Solo Lectura | `dashboard.view` por routing |
| `check_in` | Marcar una reservacion como check-in realizado | `reservations.check_in` | Owner/Admin, Operaciones, Recepcion | Sin guard especifico en `dashboard.php` |
| `check_out` | Marcar una reservacion como check-out realizado | `reservations.check_out` | Owner/Admin, Operaciones, Recepcion | Sin guard especifico en `dashboard.php` |
| `obligation_apply_add` | Abonar parcialmente una obligacion desde dashboard | `obligations.payment_add` | Owner/Admin, Finanzas, Operaciones | Sin guard especifico en `dashboard.php` |
| `obligation_apply_full` / `obligation_pay_full` | Liquidar por completo una obligacion desde dashboard | `obligations.payment_full` | Owner/Admin, Finanzas, Operaciones | Sin guard especifico en `dashboard.php` |
| `obligation_apply_all` | Pagar todas las obligaciones visibles del tablero | `obligations.payment_apply_all_visible` | Owner/Admin, Finanzas | Sin guard especifico en `dashboard.php` |

## Calendar

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver calendario | Ver ocupacion, disponibilidad y reservaciones por habitacion | `calendar.view` | Owner/Admin, Operaciones, Recepcion, Solo Lectura | `calendar.view` |
| `create_block` | Crear un bloqueo de habitacion | `calendar.block.create` | Owner/Admin, Operaciones | Agrupado en `calendar.manage_block` |
| `update_block` | Editar un bloqueo de habitacion | `calendar.block.edit` | Owner/Admin, Operaciones | Agrupado en `calendar.manage_block` |
| `bulk_create_blocks` | Crear varios bloqueos en lote | `calendar.block.bulk_create` | Owner/Admin, Operaciones | Agrupado en `calendar.manage_block` |
| `bulk_delete_blocks` | Eliminar varios bloqueos en lote | `calendar.block.bulk_delete` | Owner/Admin, Operaciones | Agrupado en `calendar.manage_block` |
| `quick_reservation` | Crear apartado rapido desde el calendario | `reservations.hold.create` | Owner/Admin, Operaciones, Recepcion | `calendar.create_hold` |
| `create_reservation_payment` | Registrar un pago rapido desde el calendario | `reservations.payment.create` | Owner/Admin, Operaciones, Recepcion, Finanzas | `calendar.register_payment` |
| `create_reservation_service` | Agregar un cargo o servicio a una reservacion desde calendario | `reservations.charge.create` | Owner/Admin, Operaciones, Recepcion | `reservations.post_charge` |
| `move_reservation` | Mover una reservacion entre habitaciones o fechas desde el calendario | `reservations.move` | Owner/Admin, Operaciones, Recepcion | `calendar.move_reservation` |
| `duplicate_reservation` | Duplicar una reservacion existente | `reservations.duplicate` | Owner/Admin, Operaciones, Recepcion | `reservations.create` |
| `advance_reservation_status` | Avanzar el estatus operativo de una reservacion | `reservations.status.advance` | Owner/Admin, Operaciones, Recepcion | Agrupado en `reservations.status_change` |
| `mark_reservation_no_show` | Marcar una reservacion como no-show | `reservations.status.no_show` | Owner/Admin, Operaciones, Recepcion | Agrupado en `reservations.status_change` |
| `cancel_reservations` | Cancelar una o varias reservaciones desde calendario | `reservations.status.cancel` | Owner/Admin, Operaciones, Recepcion | Agrupado en `reservations.status_change` |
| `rateplan_override_quick` | Cambiar disponibilidad o precio manual rapido para una celda del calendario | `calendar.availability.override_set` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `bulk_rateplan_override_set` | Aplicar override de disponibilidad o precio en lote | `calendar.availability.override_bulk_set` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `bulk_rateplan_override_clear` | Limpiar overrides de disponibilidad o precio en lote | `calendar.availability.override_bulk_clear` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `developer_clear_ota_overrides` | Limpiar overrides OTA de forma administrativa | `calendar.availability.override_clear_ota` | Owner/Admin | Hoy cae en `rateplans.edit` |

## Reservations

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver reservaciones | Ver listado y detalle operativo de reservaciones | `reservations.view` | Owner/Admin, Operaciones, Recepcion, Finanzas, Solo Lectura | `reservations.view` |
| `create_reservation` | Crear una nueva reservacion | `reservations.create` | Owner/Admin, Operaciones, Recepcion | `reservations.create` |
| `confirm_reservation` | Confirmar una reservacion o apartado | `reservations.confirm` | Owner/Admin, Operaciones, Recepcion | Hoy cae en `reservations.status_change` |
| `update_reservation` | Editar datos generales de una reservacion | `reservations.edit` | Owner/Admin, Operaciones, Recepcion | `reservations.edit` |
| `add_interest` | Agregar un concepto de interes a la reservacion | `reservations.interest.add` | Owner/Admin, Operaciones, Finanzas | Hoy cae en `reservations.edit` |
| `remove_interest` | Quitar un concepto de interes de la reservacion | `reservations.interest.remove` | Owner/Admin, Operaciones, Finanzas | Hoy cae en `reservations.edit` |
| `add_note` | Agregar una nota interna a la reservacion | `reservations.note.create` | Owner/Admin, Operaciones, Recepcion | Hoy cae en `reservations.note_edit` |
| `delete_note` | Eliminar una nota interna de la reservacion | `reservations.note.delete` | Owner/Admin, Operaciones | Hoy cae en `reservations.note_edit` |
| `create_folio` | Crear un nuevo folio para la reservacion | `reservations.folio.create` | Owner/Admin, Operaciones | Hoy cae en `reservations.manage_folio` |
| `update_folio` | Editar nombre, vencimiento o notas de un folio | `reservations.folio.edit` | Owner/Admin, Operaciones | Hoy cae en `reservations.manage_folio` |
| `close_folio` | Cerrar un folio | `reservations.folio.close` | Owner/Admin, Operaciones, Finanzas | Hoy cae en `reservations.manage_folio` |
| `reopen_folio` | Reabrir un folio cerrado | `reservations.folio.reopen` | Owner/Admin, Operaciones, Finanzas | Hoy cae en `reservations.manage_folio` |
| `delete_folio` | Eliminar o desactivar un folio | `reservations.folio.delete` | Owner/Admin | Hoy cae en `reservations.manage_folio` |
| `remove_visible_folio_taxes` | Quitar impuestos visibles del folio | `reservations.folio.tax.remove_visible` | Owner/Admin, Finanzas | Hoy cae en `reservations.manage_folio` |
| `create_sale_item` | Agregar un cargo o concepto al folio | `reservations.charge.create` | Owner/Admin, Operaciones, Recepcion | Hoy cae en `reservations.post_charge` |
| `update_sale_item` | Editar un cargo o concepto del folio | `reservations.charge.edit` | Owner/Admin, Operaciones, Recepcion | Hoy cae en `reservations.post_charge` |
| `delete_sale_item` | Eliminar un cargo o concepto del folio | `reservations.charge.delete` | Owner/Admin, Operaciones | Hoy cae en `reservations.post_charge` |
| `create_payment` | Registrar un pago en la reservacion | `reservations.payment.create` | Owner/Admin, Operaciones, Recepcion, Finanzas | Hoy cae en `reservations.post_payment` |
| `update_payment` | Editar un pago registrado | `reservations.payment.edit` | Owner/Admin, Finanzas | Hoy cae en `reservations.post_payment` |
| `delete_payment` | Eliminar o revertir un pago registrado | `reservations.payment.delete` | Owner/Admin, Finanzas | Hoy cae en `reservations.post_payment` |
| `create_refund` | Registrar un refund sobre un pago | `reservations.refund.create` | Owner/Admin, Finanzas | Hoy cae en `reservations.refund` |
| `delete_refund` | Eliminar o revertir un refund | `reservations.refund.delete` | Owner/Admin, Finanzas | Hoy cae en `reservations.refund` |

## Reservation Wizard

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| entrar al wizard | Abrir el flujo guiado de creacion o edicion de reservacion | `reservations.view` | Owner/Admin, Operaciones, Recepcion | `reservations.view` por routing |
| `wizard_create_now` | Crear la reservacion desde el wizard y continuar su flujo operativo | `reservations.create` | Owner/Admin, Operaciones, Recepcion | `reservations.create` o `reservations.status_change` segun contexto |
| `wizard_confirm` | Confirmar el wizard y generar hospedaje, folio y pago inicial opcional | `reservations.confirm` | Owner/Admin, Operaciones, Recepcion | Hoy compone `reservations.manage_folio` + `reservations.post_charge` + opcional `reservations.post_payment` + `reservations.status_change` |
| `wizard_replace_lodging` | Cambiar el tipo de hospedaje o el concepto principal de hospedaje | `reservations.lodging_type.change` | Owner/Admin, Operaciones | Hoy se resuelve dentro de `reservations.edit` |

## Guests

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver huespedes | Ver listado y detalle de huespedes | `guests.view` | Owner/Admin, Operaciones, Recepcion, Solo Lectura | `guests.view` |
| `new_guest` | Abrir formulario para nuevo huesped | `guests.create` | Owner/Admin, Operaciones, Recepcion | `guests.create` |
| `save_guest` (nuevo) | Crear un nuevo huesped | `guests.create` | Owner/Admin, Operaciones, Recepcion | `guests.create` |
| `save_guest` (edicion) | Editar un huesped existente | `guests.edit` | Owner/Admin, Operaciones, Recepcion | `guests.edit` |
| `api/guest_search` | Buscar huespedes para autocompletado o seleccion rapida | `guests.search` | Owner/Admin, Operaciones, Recepcion | Hoy permite `guests.view` o `reservations.create` o `reservations.edit` |

## Messages

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver mensajes | Ver dashboard de mensajes y plantillas | `messages.view` | Owner/Admin, Operaciones, Recepcion, Solo Lectura | `messages.view` |
| `send_message` | Enviar mensaje por WhatsApp al huesped | `messages.send` | Owner/Admin, Operaciones, Recepcion | `messages.send` |
| `mark_message_sent` | Marcar un mensaje como enviado manualmente | `messages.mark_sent` | Owner/Admin, Operaciones, Recepcion | Hoy comparte `messages.send` |
| `edit_template` | Abrir una plantilla para editarla | `messages.template.edit` | Owner/Admin, Operaciones | Hoy controlado por `messages.template_edit` |
| `save_template` | Crear o editar una plantilla de mensaje | `messages.template.save` | Owner/Admin, Operaciones | `messages.template_edit` |

## Activities

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver actividades | Ver catalogo de actividades y bookings asociados | `activities.view` | Owner/Admin, Operaciones, Recepcion, Solo Lectura | `activities.view` |
| `new_activity` | Abrir formulario para nueva actividad | `activities.create` | Owner/Admin, Operaciones | `activities.create` |
| `save_activity` (nueva) | Crear una nueva actividad | `activities.create` | Owner/Admin, Operaciones | `activities.create` |
| `save_activity` (edicion) | Editar una actividad existente | `activities.edit` | Owner/Admin, Operaciones | `activities.edit` |
| `deactivate_activity` | Desactivar una actividad para que no se pueda vender o agendar | `activities.deactivate` | Owner/Admin, Operaciones | Boton presente; sin guard especifico fino |
| `restore_activity` | Reactivar una actividad desactivada | `activities.restore` | Owner/Admin, Operaciones | Boton presente; sin guard especifico fino |
| `schedule_activity` / `save_activity_booking` | Crear booking de actividad para una reservacion | `activities.booking.create` | Owner/Admin, Operaciones, Recepcion | Hoy cae en `activities.book` |
| `edit_activity_booking` | Editar booking de actividad ya existente | `activities.booking.edit` | Owner/Admin, Operaciones, Recepcion | Hoy cae en `activities.book` |
| `cancel_activity_booking` | Cancelar booking de actividad sin borrarlo | `activities.booking.cancel` | Owner/Admin, Operaciones | Hoy cae en `activities.cancel` |
| `delete_activity_booking` | Eliminar booking de actividad | `activities.booking.delete` | Owner/Admin | Hoy cae en `activities.cancel` |

## Properties

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver propiedades | Ver propiedades y su resumen operativo | `properties.view` | Owner/Admin, Operaciones, Solo Lectura | `properties.view` |
| `new_property` | Abrir formulario para nueva propiedad | `properties.create` | Owner/Admin | `properties.create` |
| `save_property` (nueva) | Crear una nueva propiedad | `properties.create` | Owner/Admin | `properties.create` |
| `save_property` (edicion) | Editar configuracion de una propiedad existente | `properties.edit` | Owner/Admin, Operaciones | `properties.edit` |
| `update_order` | Cambiar el orden visual de las propiedades | `properties.reorder` | Owner/Admin | Hoy cae en `properties.edit` |

## Rooms

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver habitaciones | Ver habitaciones, categoria y estado operativo | `rooms.view` | Owner/Admin, Operaciones, Solo Lectura | `rooms.view` |
| `new_room` | Abrir formulario para nueva habitacion | `rooms.create` | Owner/Admin, Operaciones | `rooms.create` |
| `save_room` (nueva) | Crear una nueva habitacion | `rooms.create` | Owner/Admin, Operaciones | `rooms.create` |
| `save_room` (edicion) | Editar una habitacion existente | `rooms.edit` | Owner/Admin, Operaciones | `rooms.edit` |
| `duplicate_room` | Duplicar una habitacion existente | `rooms.duplicate` | Owner/Admin, Operaciones | Hoy cae en `rooms.create` |
| `update_order` | Cambiar el orden visual de habitaciones | `rooms.reorder` | Owner/Admin, Operaciones | Hoy cae en `rooms.edit` |

## Categories

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver categorias | Ver categorias y configuracion de hospedaje | `categories.view` | Owner/Admin, Operaciones, Solo Lectura | `categories.view` |
| `new_category` | Abrir formulario para nueva categoria | `categories.create` | Owner/Admin, Operaciones | `categories.create` |
| `save_category` (nueva) | Crear una nueva categoria | `categories.create` | Owner/Admin, Operaciones | `categories.create` |
| `save_category` (edicion) | Editar una categoria existente | `categories.edit` | Owner/Admin, Operaciones | `categories.edit` |
| `duplicate_category` | Duplicar una categoria existente | `categories.duplicate` | Owner/Admin, Operaciones | Hoy cae en `categories.create` |
| `update_order` | Cambiar el orden visual de categorias | `categories.reorder` | Owner/Admin, Operaciones | Hoy cae en `categories.edit` |
| `add_bed_config` | Agregar configuracion de camas a una categoria | `categories.bed_config.create` | Owner/Admin, Operaciones | Hoy cae en `categories.edit` |
| `remove_bed_config` | Eliminar configuracion de camas de una categoria | `categories.bed_config.delete` | Owner/Admin, Operaciones | Hoy cae en `categories.edit` |

## Rateplans and Availability Rules

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver rateplans | Ver estrategia de precios y reglas activas | `rateplans.view` | Owner/Admin, Operaciones, Solo Lectura | `rateplans.view` |
| `save_pricing_strategy` | Guardar la estrategia de precios de una propiedad | `rateplans.strategy.edit` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `api/rateplan_modifiers:list_modifiers` | Ver modifiers activos de un rateplan | `rateplans.modifier.view` | Owner/Admin, Operaciones, Solo Lectura | Hoy cae en `rateplans.view` |
| `api/rateplan_modifiers:preview_night` | Previsualizar impacto de modifiers en una noche | `rateplans.modifier.preview_night` | Owner/Admin, Operaciones | Hoy cae en `rateplans.view` |
| `api/rateplan_modifiers:preview_calendar` | Previsualizar impacto de modifiers en calendario | `rateplans.modifier.preview_calendar` | Owner/Admin, Operaciones | Hoy cae en `rateplans.view` |
| `api/rateplan_modifiers:upsert_modifier` | Crear o editar un modifier de rateplan | `rateplans.modifier.save` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `api/rateplan_modifiers:upsert_schedule` | Crear o editar un schedule de modifier | `rateplans.modifier.schedule.save` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `api/rateplan_modifiers:delete_schedule` | Eliminar un schedule de modifier | `rateplans.modifier.schedule.delete` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `api/rateplan_modifiers:upsert_condition` | Crear o editar una condicion de modifier | `rateplans.modifier.condition.save` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `api/rateplan_modifiers:delete_condition` | Eliminar una condicion de modifier | `rateplans.modifier.condition.delete` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `api/rateplan_modifiers:upsert_scope` | Crear o editar un scope de modifier | `rateplans.modifier.scope.save` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |
| `api/rateplan_modifiers:delete_scope` | Eliminar un scope de modifier | `rateplans.modifier.scope.delete` | Owner/Admin, Operaciones | Hoy cae en `rateplans.edit` |

## OTAs and iCal

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver cuentas OTA | Ver cuentas OTA configuradas | `otas.view` | Owner/Admin, Operaciones, Solo Lectura | `otas.view` |
| `save_ota_account` | Crear o editar una cuenta OTA | `otas.account.save` | Owner/Admin, Operaciones | Hoy cae en `otas.edit` |
| `delete_ota_account` | Eliminar una cuenta OTA | `otas.account.delete` | Owner/Admin | Hoy cae en `otas.edit` |
| ver feeds iCal | Ver feeds iCal por propiedad | `ota_ical.view` | Owner/Admin, Operaciones, Solo Lectura | `ota_ical.view` |
| `save_feed` | Crear o editar un feed iCal | `ota_ical.feed.save` | Owner/Admin, Operaciones | Hoy cae en `ota_ical.edit` |
| `delete_feed` | Eliminar un feed iCal | `ota_ical.feed.delete` | Owner/Admin | Hoy cae en `ota_ical.edit` |
| `sync_feed` | Sincronizar un feed iCal especifico | `ota_ical.feed.sync` | Owner/Admin, Operaciones | Hoy cae en `ota_ical.sync` |
| `sync_property` | Sincronizar todos los feeds iCal de una propiedad | `ota_ical.property.sync` | Owner/Admin, Operaciones | Hoy cae en `ota_ical.sync` |
| `api/ota_ical_sync.php` | Ejecutar sincronizacion via endpoint JSON autenticado | `ota_ical.sync` | Owner/Admin, Operaciones, sistema | `ota_ical.sync` |

## Sale Items

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver conceptos y catalogos | Ver catalogo de conceptos, categorias y relaciones | `sale_items.view` | Owner/Admin, Operaciones, Solo Lectura | `sale_items.view` |
| `create_category` | Crear una categoria de conceptos | `sale_items.category.create` | Owner/Admin, Operaciones | Hoy cae en `sale_items.create` |
| `update_category` | Editar una categoria de conceptos | `sale_items.category.edit` | Owner/Admin, Operaciones | Hoy cae en `sale_items.edit` |
| `delete_category` | Eliminar una categoria de conceptos | `sale_items.category.delete` | Owner/Admin | Hoy cae en `sale_items.edit` |
| `create_item` | Crear un concepto o item de cargo | `sale_items.item.create` | Owner/Admin, Operaciones | Hoy cae en `sale_items.create` |
| `update_item` | Editar un concepto o item de cargo | `sale_items.item.edit` | Owner/Admin, Operaciones | Hoy cae en `sale_items.edit` |
| `delete_item` | Eliminar un concepto o item de cargo | `sale_items.item.delete` | Owner/Admin | Hoy cae en `sale_items.edit` |
| `clone_item` | Clonar un concepto o item existente | `sale_items.item.clone` | Owner/Admin, Operaciones | Hoy cae en `sale_items.create` |
| `update_child_links` | Editar los links padre-hijo entre conceptos derivados | `sale_items.relation.links_edit` | Owner/Admin, Operaciones | Hoy cae en `sale_items.relations_edit` |
| `update_child_relation` | Editar reglas de una relacion padre-hijo especifica | `sale_items.relation.edit` | Owner/Admin, Operaciones | Hoy cae en `sale_items.relations_edit` |
| `update_line_item_type` | Cambiar el tipo funcional de un concepto o line item type | `sale_items.line_item_type.edit` | Owner/Admin, Operaciones | Hoy cae en `sale_items.edit` |

## Settings

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver settings | Ver configuraciones generales, pagos y conceptos | `settings.view` | Owner/Admin, Operaciones, Solo Lectura | `settings.view` por routing |
| `save_timezone` | Guardar zona horaria del PMS | `settings.timezone.edit` | Owner/Admin | Hoy todo cae en `settings.edit` |
| `save_theme` | Guardar tema visual del PMS | `settings.theme.edit` | Owner/Admin | Hoy todo cae en `settings.edit` |
| `save_google_drive_export` | Guardar configuracion de exportacion a Google Drive | `settings.export.google_drive.edit` | Owner/Admin | Hoy todo cae en `settings.edit` |
| `save_interests` | Guardar catalogos o reglas de intereses aplicables | `settings.interests.edit` | Owner/Admin, Finanzas | Hoy todo cae en `settings.edit` |
| `save_lodging` | Guardar configuracion base de hospedaje | `settings.lodging.edit` | Owner/Admin, Operaciones | Hoy todo cae en `settings.edit` |
| `save_lodging_folio_concepts` | Definir que conceptos de hospedaje van al folio | `settings.lodging_folio_concepts.edit` | Owner/Admin, Operaciones | Hoy todo cae en `settings.edit` |
| `save_lodging_payment_blocks` | Definir bloqueos de metodos de pago para hospedaje | `settings.lodging_payment_blocks.edit` | Owner/Admin, Operaciones, Finanzas | Hoy todo cae en `settings.edit` |
| `save_payment_concepts` | Configurar conceptos usados para registrar pagos | `settings.payment_concepts.edit` | Owner/Admin, Finanzas | Hoy todo cae en `settings.edit` |
| `save_service_concepts` | Configurar conceptos de servicios o cargos manuales | `settings.service_concepts.edit` | Owner/Admin, Operaciones | Hoy todo cae en `settings.edit` |
| `save_payment_method` | Crear o editar metodo de pago general | `settings.payment_method.save` | Owner/Admin, Finanzas | Hoy todo cae en `settings.edit` |
| `delete_payment_method` | Eliminar metodo de pago general | `settings.payment_method.delete` | Owner/Admin | Hoy todo cae en `settings.edit` |
| `save_reservation_source` | Crear o editar origen o fuente de reservacion | `settings.reservation_source.save` | Owner/Admin, Operaciones | Hoy todo cae en `settings.edit` |
| `delete_reservation_source` | Eliminar origen o fuente de reservacion | `settings.reservation_source.delete` | Owner/Admin | Hoy todo cae en `settings.edit` |
| `save_obligation_payment_method` | Crear o editar metodo de pago para obligaciones | `settings.obligation_payment_method.save` | Owner/Admin, Finanzas | Hoy todo cae en `settings.edit` |
| `delete_obligation_payment_method` | Eliminar metodo de pago para obligaciones | `settings.obligation_payment_method.delete` | Owner/Admin | Hoy todo cae en `settings.edit` |
| `save_income_payment_method` | Crear o editar metodo de pago para ingresos | `settings.income_payment_method.save` | Owner/Admin, Finanzas | Hoy todo cae en `settings.edit` |
| `delete_income_payment_method` | Eliminar metodo de pago para ingresos | `settings.income_payment_method.delete` | Owner/Admin | Hoy todo cae en `settings.edit` |
| `recalc_folio_nodes` | Recalcular nodos y consistencia de folios para reservaciones seleccionadas | `settings.folio_nodes.recalculate` | Owner/Admin | Hoy todo cae en `settings.edit` |

## Payments, Incomes and Obligations

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver pagos | Ver grid y detalle de pagos | `payments.view` | Owner/Admin, Finanzas, Solo Lectura | `payments.view` |
| ver ingresos | Ver grid de ingresos y conciliacion | `incomes.view` | Owner/Admin, Finanzas, Solo Lectura | `incomes.view` |
| `apply_add` en ingresos | Abonar parcialmente un ingreso | `incomes.reconcile_add` | Owner/Admin, Finanzas | Hoy cae en `incomes.reconcile` |
| `apply_set` en ingresos | Fijar manualmente el monto pagado de un ingreso | `incomes.reconcile_set` | Owner/Admin, Finanzas | Hoy cae en `incomes.reconcile` |
| `apply_full` en ingresos | Liquidar totalmente un ingreso | `incomes.reconcile_full` | Owner/Admin, Finanzas | Hoy cae en `incomes.reconcile` |
| `apply_all` en ingresos | Confirmar todos los ingresos visibles | `incomes.reconcile_all_visible` | Owner/Admin, Finanzas | Hoy cae en `incomes.reconcile` |
| ver obligaciones | Ver grid de obligaciones por pagar | `obligations.view` | Owner/Admin, Finanzas, Solo Lectura | `obligations.view` |
| `apply_add` en obligaciones | Abonar parcialmente una obligacion | `obligations.payment_add` | Owner/Admin, Finanzas | Hoy cae en `obligations.pay` |
| `apply_set` en obligaciones | Fijar manualmente el monto pagado de una obligacion | `obligations.payment_set` | Owner/Admin, Finanzas | Hoy cae en `obligations.pay` |
| `apply_full` en obligaciones | Liquidar totalmente una obligacion | `obligations.payment_full` | Owner/Admin, Finanzas | Hoy cae en `obligations.pay` |
| `apply_all` en obligaciones | Pagar todas las obligaciones visibles | `obligations.payment_apply_all_visible` | Owner/Admin, Finanzas | Hoy cae en `obligations.pay` |

## Reports

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver reportes | Ver modulo de reportes | `reports.view` | Owner/Admin, Operaciones, Finanzas, Solo Lectura | `reports.view` |
| correr reporte | Ejecutar una plantilla de reporte con filtros | `reports.run` | Owner/Admin, Operaciones, Finanzas, Solo Lectura | `reports.run` por `pms_user_can` |
| exportar reporte | Exportar reporte visible a CSV o Excel | `reports.export` | Owner/Admin, Operaciones, Finanzas, Solo Lectura | `reports.view` + `reports.run` en `api/report_v2_export.php` |
| `save_report_row` | Guardar configuracion general de fila base o layout de plantilla | `reports.template.layout_save` | Owner/Admin, Finanzas | Hoy cae en `reports.design` |
| `save_template` | Crear o editar una plantilla de reporte | `reports.template.save` | Owner/Admin, Finanzas | Hoy cae en `reports.design` |
| `delete_template` | Archivar una plantilla de reporte | `reports.template.delete` | Owner/Admin | Hoy cae en `reports.design` |
| `clone_template` | Clonar una plantilla de reporte | `reports.template.clone` | Owner/Admin, Finanzas | Hoy cae en `reports.design` |
| `save_template_run_filters` | Guardar filtros de corrida dentro de la plantilla | `reports.template.run_filters.save` | Owner/Admin, Finanzas | Hoy cae en `reports.design` |
| `save_field` | Crear o editar un campo de plantilla | `reports.field.save` | Owner/Admin, Finanzas | Hoy cae en `reports.design` |
| `delete_field` | Archivar un campo de plantilla | `reports.field.delete` | Owner/Admin | Hoy cae en `reports.design` |
| `save_calculation` | Crear o editar una formula calculada | `reports.calculation.save` | Owner/Admin, Finanzas | Hoy cae en `reports.design` |
| `delete_calculation` | Archivar una formula calculada | `reports.calculation.delete` | Owner/Admin | Hoy cae en `reports.design` |

## Users and Roles

| Accion actual | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| ver usuarios | Ver usuarios internos del PMS | `users.view` | Owner/Admin | `users.view` |
| `new_user` | Abrir formulario para nuevo usuario interno | `users.create` | Owner/Admin | `users.create` |
| `save_user` (nuevo) | Crear usuario interno del PMS | `users.create` | Owner/Admin | `users.create` |
| `save_user` (edicion) | Editar datos de usuario interno | `users.edit` | Owner/Admin | `users.edit` |
| `assigned_properties[]` dentro de `save_user` | Asignar propiedades permitidas al usuario | `users.assign_properties` | Owner/Admin | `users.assign_properties` |
| `assigned_roles[]` dentro de `save_user` | Asignar roles al usuario | `users.assign_roles` | Owner/Admin | `users.assign_roles` |
| `is_active` dentro de `save_user` | Activar o desactivar un usuario | `users.status_change` | Owner/Admin | Hoy va dentro de `users.edit` |
| ver editor de roles | Ver editor de roles y su catalogo de permisos | `roles.view` | Owner/Admin | Hoy el modulo usa `users.manage_roles` |
| `new_role_editor` | Abrir editor para nuevo rol | `roles.create` | Owner/Admin | Hoy el modulo usa `users.manage_roles` |
| `save_role_editor` (nuevo) | Crear un rol nuevo | `roles.create` | Owner/Admin | Hoy el modulo usa `users.manage_roles` |
| `save_role_editor` (edicion) | Editar nombre, descripcion o scope del rol | `roles.edit` | Owner/Admin | Hoy el modulo usa `users.manage_roles` |
| `role_permission_codes[]` dentro de `save_role_editor` | Cambiar permisos asignados a un rol | `roles.permissions.edit` | Owner/Admin | Hoy el modulo usa `users.manage_roles` |
| `duplicate_role` | Duplicar un rol existente con sus permisos | `roles.duplicate` | Owner/Admin | Hoy el modulo usa `users.manage_roles` |

## Endpoints transversales usados por PMS

| Endpoint / accion | Descripcion UI sugerida | Permiso propuesto | Rol sugerido | Cobertura actual |
| --- | --- | --- | --- | --- |
| `api/reservation_interest.php` -> `add` | Agregar interes permitido a una reservacion via UI asincrona | `reservations.interest.add` | Owner/Admin, Operaciones, Finanzas | Hoy cae en `reservations.edit` |
| `api/reservation_interest.php` -> `remove` | Quitar interes permitido de una reservacion via UI asincrona | `reservations.interest.remove` | Owner/Admin, Operaciones, Finanzas | Hoy cae en `reservations.edit` |
| `api/report_v2_export.php` | Exportar reporte por endpoint autenticado | `reports.export` | Owner/Admin, Operaciones, Finanzas, Solo Lectura | Hoy exige `reports.view` y `reports.run` |

## Huecos detectados en el codigo actual

1. `dashboard.php` ejecuta `check_in`, `check_out` y pagos de obligaciones sin `pms_require_permission(...)` fino por accion.
2. `calendar.php` y `reservations.php` ya tienen guards, pero varios permisos siguen agrupados y no distinguen cada accion concreta.
3. `messages.php` separa `messages.send` y `messages.template_edit`, pero todavia no separa `mark_sent` como permiso propio.
4. `activities.php` tiene botones de `deactivate_activity` y `restore_activity`; se deben blindar con permisos propios.
5. `settings.php` hoy mete practicamente todas las mutaciones bajo `settings.edit`; conviene romperlo en permisos por subdominio.
6. `user_roles.php` sigue usando `users.manage_roles` como permiso unico; para IA/movil conviene separar `roles.view`, `roles.create`, `roles.edit`, `roles.permissions.edit`, `roles.duplicate`.
7. `payments.php` es de solo lectura, pero no existe todavia una capa de exportacion o accion fina asociada a pagos.

## Recomendacion de implementacion

1. Tomar este documento como catalogo maestro v1.
2. Crear la tabla canonica de permisos usando los `permission_code` propuestos.
3. Refactorizar los guards actuales para sustituir permisos agrupados por permisos de accion.
4. Mantener la misma descripcion UI en web, IA y movil para evitar ambiguedad.
5. Cuando una accion sea compuesta, evaluar si debe pedir un permiso principal o varios permisos atomicos.

## Fuentes auditadas

- `public_html/index.php`
- `public_html/includes/db.php`
- `public_html/modules/dashboard.php`
- `public_html/modules/calendar.php`
- `public_html/modules/reservations.php`
- `public_html/modules/reservation_wizard.php`
- `public_html/modules/guests.php`
- `public_html/modules/messages.php`
- `public_html/modules/activities.php`
- `public_html/modules/properties.php`
- `public_html/modules/rooms.php`
- `public_html/modules/categories.php`
- `public_html/modules/rateplans.php`
- `public_html/modules/otas.php`
- `public_html/modules/ota_ical.php`
- `public_html/modules/sale_items.php`
- `public_html/modules/settings.php`
- `public_html/modules/payments.php`
- `public_html/modules/incomes.php`
- `public_html/modules/obligations.php`
- `public_html/modules/reports.php`
- `public_html/modules/reports_v2_render.php`
- `public_html/modules/reports_v2_sections.php`
- `public_html/modules/app_users.php`
- `public_html/modules/user_roles.php`
- `public_html/api/guest_search.php`
- `public_html/api/rateplan_modifiers.php`
- `public_html/api/report_v2_export.php`
- `public_html/api/reservation_interest.php`
- `public_html/api/ota_ical_sync.php`
