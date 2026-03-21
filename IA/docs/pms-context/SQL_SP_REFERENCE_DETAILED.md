# Referencia Detallada de Stored Procedures PMS

Ultima actualizacion: 2026-03-16

## Objetivo
Este documento concentra los stored procedures de `bd pms`, los organiza por dominio funcional y resume:

- que hace cada SP
- como se llama
- que devuelve o que modifica
- cuando conviene usarlo

Sirve como referencia operativa para desarrollo, soporte, integraciones y onboarding.

## Convenciones de lectura
- Todos los SP estan en [bd pms](c:\Users\ragnarok\Documents\repos\Proyecto VLV\PMS\bd pms).
- En los ejemplos se usa `CALL sp_xxx(...)`.
- Cuando un SP devuelve datos, normalmente lo hace con uno o varios `SELECT`.
- Cuando un SP valida reglas de negocio, suele fallar con `SIGNAL SQLSTATE '45000'`.
- Los parametros listados aqui siguen el orden declarado en cada SP.

## Indice por dominio
1. Acceso y autorizacion
2. Reservaciones
3. Calendario, disponibilidad y bloqueos
4. Folios, cargos, pagos y obligaciones
5. Propiedades, habitaciones, huespedes y usuarios
6. Catalogos de conceptos de venta
7. Rateplans y precios
8. OTA e iCal
9. Actividades
10. Reporteria
11. Configuracion, tema y portal

## 1. Acceso y autorizacion
Estos SP soportan el modelo de permisos, roles, alcance por propiedad y semillas base de RBAC.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_access_context_data` | Devuelve el contexto de acceso de un usuario para una compania: permisos efectivos, propiedades asignadas y resumen de modo de autorizacion. | `CALL sp_access_context_data(p_company_code, p_user_id);` | Regresa 3 result sets: permisos, propiedades y resumen (`authz_mode`, `is_owner`). | Util para construir sesion, menus y guardas de UI/API. |
| `sp_access_seed_defaults` | Siembra configuracion base de autorizacion, permisos iniciales, roles por defecto y asignaciones minimas. | `CALL sp_access_seed_defaults(p_company_code, p_actor_user_id);` | Inserta defaults en tablas RBAC y responde estado `ok`. | Conviene correrlo al habilitar una compania o al migrar seguridad. |
| `sp_authz_assert` | Valida si un usuario tiene permiso y, opcionalmente, alcance sobre una propiedad. | `CALL sp_authz_assert(p_company_code, p_user_id, p_permission_code, p_property_code, p_enforce_mode);` | Si deniega, registra auditoria y puede lanzar `AUTHZ_DENIED`. | Debe usarse como guardia central antes de operaciones sensibles. |
| `sp_role_upsert` | Crea o actualiza un rol. | `CALL sp_role_upsert(p_company_code, p_role_id, p_role_code, p_role_name, p_description, p_is_active, p_actor_user_id);` | Inserta/actualiza `role` y devuelve el rol final. | Base para admin de roles. |
| `sp_role_permission_sync` | Reemplaza la lista de permisos de un rol. | `CALL sp_role_permission_sync(p_company_code, p_role_id, p_permission_codes_csv, p_actor_user_id);` | Sincroniza `role_permission` y devuelve permisos finales del rol. | Es sincronizacion completa, no append incremental. |
| `sp_user_property_sync` | Reemplaza las propiedades asignadas a un usuario. | `CALL sp_user_property_sync(p_company_code, p_user_id, p_property_codes_csv, p_actor_user_id);` | Sincroniza `user_property` y devuelve asignaciones finales. | Clave para alcance por propiedad. |
| `sp_user_role_sync` | Reemplaza los roles asignados a un usuario. | `CALL sp_user_role_sync(p_company_code, p_user_id, p_role_codes_csv, p_actor_user_id);` | Sincroniza `user_role` y devuelve roles finales. | Tambien es sincronizacion total. |

## 2. Reservaciones
Aqui esta el ciclo principal de creacion, confirmacion, consulta, notas, mensajes y actualizacion de reservaciones.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_create_reservation` | Crea una reservacion confirmada de punta a punta. Resuelve/crea huesped, valida disponibilidad, calcula hospedaje, crea folio y cargos iniciales. | `CALL sp_create_reservation(p_property_code, p_room_code, p_check_in_str, p_check_out_str, p_guest_email, p_guest_names, p_guest_phone, p_adults, p_children, p_lodging_catalog_id, p_total_cents_override, p_fixed_child_unit_price_cents, p_fixed_child_total_cents, p_source, p_id_ota_account, p_id_user);` | Inserta en `guest`, `reservation`, `folio`, `line_item` y devuelve la reservacion creada. | Usalo cuando no quieres pasar por flujo de apartado. |
| `sp_create_reservation_hold` | Crea una reservacion en estado de apartado, sin folio ni cargo final. | `CALL sp_create_reservation_hold(p_property_code, p_room_code, p_check_in, p_check_out, p_notes, p_id_user);` | Inserta `reservation` y devuelve el registro con joins utiles. | Paso previo a confirmacion comercial/manual. |
| `sp_reservation_confirm_hold` | Convierte un apartado en reservacion confirmada. Asigna huesped, cargo de hospedaje y folio principal. | `CALL sp_reservation_confirm_hold(p_company_code, p_reservation_id, p_guest_id, p_lodging_catalog_id, p_total_cents_override, p_adults, p_children, p_actor_user_id);` | Actualiza `reservation`, crea `folio` y `line_item`, y devuelve la reservacion confirmada. | Solo aplica si la reservacion sigue en `apartado`. |
| `sp_reservation_add_folio` | Agrega un folio principal o adicional a una reservacion existente y deriva cargos asociados. | `CALL sp_reservation_add_folio(p_company_code, p_reservation_id, p_guest_id, p_lodging_catalog_id, p_total_cents_override, p_adults, p_children, p_actor_user_id);` | Inserta `folio`, actualiza `reservation` y crea cargos. | Reutiliza parte del flujo de confirmacion. |
| `sp_reservation_update` | Actualiza estado, fuente, OTA, habitacion, fechas, ocupacion y notas. | `CALL sp_reservation_update(p_company_code, p_reservation_id, p_status, p_source, p_id_ota_account, p_room_code, p_check_in_date, p_check_out_date, p_adults, p_children, p_notes_internal, p_notes_guest, p_actor_user_id);` | Actualiza `reservation`; en cancelaciones puede afectar `folio` y `line_item`. | SP principal para cambios operativos. |
| `sp_reservation_update_v2` | Envoltura de actualizacion que agrega `p_reservation_code` y canaliza al flujo base. | `CALL sp_reservation_update_v2(p_company_code, p_reservation_id, p_status, p_source, p_id_ota_account, p_room_code, p_check_in_date, p_check_out_date, p_adults, p_children, p_reservation_code, p_notes_internal, p_notes_guest, p_actor_user_id);` | Devuelve la reservacion actualizada. | Util si ya trabajas con codigo de reservacion ademas del id. |
| `sp_list_reservations_by_company` | Lista reservaciones por compania en un rango. | `CALL sp_list_reservations_by_company(p_company_code, p_from, p_to);` | Devuelve dataset de reservaciones. | Para dashboards o exploracion multi-propiedad. |
| `sp_list_reservations_by_property` | Lista reservaciones por propiedad en un rango. | `CALL sp_list_reservations_by_property(p_property_code, p_from, p_to);` | Devuelve dataset filtrado a una propiedad. | Base de vistas operativas por propiedad. |
| `sp_reservation_note_data` | Consulta notas de reservacion por tipo y estatus. | `CALL sp_reservation_note_data(p_company_code, p_id_reservation, p_note_type, p_show_inactive);` | Devuelve notas asociadas. | Para timeline o panel interno. |
| `sp_reservation_note_upsert` | Crea o actualiza una nota de reservacion. | `CALL sp_reservation_note_upsert(p_action, p_id_reservation_note, p_id_reservation, p_note_type, p_note_text, p_is_active, p_company_code, p_actor_user_id);` | Inserta/actualiza `reservation_note` y devuelve el registro. | `p_action` define alta o edicion. |
| `sp_reservation_message_send` | Registra el envio de un mensaje ligado a una reservacion. | `CALL sp_reservation_message_send(p_company_code, p_reservation_id, p_message_template_id, p_sent_by, p_sent_to_phone, p_message_title, p_message_body, p_channel);` | Inserta en `reservation_message_log` y devuelve confirmacion. | Es log de envio; no necesariamente integra al proveedor de mensajeria. |
| `sp_reservation_totals_report` | Genera un dataset resumido de totales para una lista de reservaciones. | `CALL sp_reservation_totals_report(p_company_code, p_reservation_ids);` | Devuelve totales y montos agrupados por reservacion. | Util para conciliacion o vistas resumen. |
| `sp_guest_backfill_full_name` | Reconstruye `full_name` de huespedes a partir de nombres y apellidos. | `CALL sp_guest_backfill_full_name();` | Actualiza `guest.full_name` y devuelve `affected_rows`. | Mantenimiento correctivo o post-migracion. |

## 3. Calendario, disponibilidad y bloqueos
Este bloque cubre ocupacion, busqueda de disponibilidad y bloqueos manuales de habitaciones.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_property_room_calendar` | Construye el grid calendario de habitaciones y ocupacion de una propiedad. | `CALL sp_property_room_calendar(p_property_code, p_from, p_days);` | Devuelve dataset de calendario; usa tablas temporales internas. | Base de la vista de calendario. |
| `sp_search_availability` | Busca habitaciones o categorias disponibles para una estancia dada. | `CALL sp_search_availability(p_company_code, p_check_in, p_nights, p_people);` | Devuelve opciones disponibles y metadatos de capacidad/precio. | Entrada comun para motor de reservacion. |
| `sp_create_room_block` | Crea un bloqueo manual para impedir disponibilidad en una habitacion. | `CALL sp_create_room_block(p_property_code, p_room_code, p_check_in, p_check_out, p_notes, p_actor_user);` | Inserta en `room_block` y devuelve el bloqueo. | Util para mantenimiento, uso interno o contingencias. |
| `sp_update_room_block` | Edita fechas, descripcion o destino de un bloqueo existente. | `CALL sp_update_room_block(p_room_block_id, p_property_code, p_room_code, p_start_date, p_end_date, p_description, p_actor_user);` | Actualiza `room_block` y devuelve el registro final. | Revalida solapamientos y consistencia. |
| `sp_get_room_block` | Recupera el detalle de un bloqueo. | `CALL sp_get_room_block(p_room_block_id);` | Devuelve el bloqueo solicitado. | Lectura puntual para formulario de edicion. |
| `sp_list_room_blocks` | Lista bloqueos por compania/propiedad y rango de fechas. | `CALL sp_list_room_blocks(p_company_code, p_property_code, p_from, p_to);` | Devuelve listado de bloqueos. | Vista administrativa e historica. |

## 4. Folios, cargos, pagos y obligaciones
Estos SP operan la contabilidad transaccional de una reservacion: folios, line items, pagos, devoluciones y obligaciones.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_folio_upsert` | Crea o actualiza un folio. | `CALL sp_folio_upsert(p_action, p_id_folio, p_id_reservation, p_folio_name, p_due_date, p_bill_to_type, p_bill_to_id, p_notes, p_currency, p_created_by);` | Inserta/actualiza `folio` y recalcula totales via `sp_folio_recalc`. | Punto central de alta/edicion de folios. |
| `sp_folio_recalc` | Recalcula totales de un folio y puede propagar resumen a la reservacion. | `CALL sp_folio_recalc(p_id_folio);` | Actualiza `folio` y `reservation`, devuelve el estado recalculado. | Debe ejecutarse tras cambios monetarios relevantes. |
| `sp_line_item_type_upsert` | Crea o actualiza un line item y su tipo/estado basico. | `CALL sp_line_item_type_upsert(p_action, p_id_line_item, p_company_code, p_item_type, p_created_by);` | Modifica `line_item` y recalcula folio. | Util para clasificacion o correccion de cargos. |
| `sp_line_item_payment_meta_upsert` | Actualiza metadatos de pago de un line item. | `CALL sp_line_item_payment_meta_upsert(p_id_line_item, p_method, p_reference, p_status, p_updated_by);` | Actualiza `line_item` con metodo, referencia y estado de pago. | Complementa el cargo; no sustituye el registro contable de pago. |
| `sp_line_item_percent_derived_upsert` | Genera o sincroniza line items derivados porcentuales a partir de un cargo padre. | `CALL sp_line_item_percent_derived_upsert(p_id_folio, p_id_reservation, p_parent_catalog_id, p_service_date, p_created_by);` | Crea/actualiza hijos y jerarquias de `line_item`. | Se usa en impuestos, comisiones y cargos calculados. |
| `sp_obligation_data` | Consulta obligaciones o saldos pendientes con filtros por fecha, propiedad, estatus y texto. | `CALL sp_obligation_data(p_company_code, p_property_code, p_date_from, p_date_to, p_search, p_payment_status, p_show_inactive, p_id_reservation, p_id_folio, p_limit_rows);` | Devuelve dataset de obligaciones. | Base para cuentas por pagar/cobrar segun modelado interno. |
| `sp_obligation_paid_upsert` | Registra pago parcial o total sobre una obligacion/line item. | `CALL sp_obligation_paid_upsert(p_company_code, p_id_line_item, p_mode, p_amount_cents, p_id_obligation_payment_method, p_payment_notes, p_created_by);` | Actualiza `line_item`, inserta log de pago y recalcula folio. | `p_mode` suele controlar total/parcial. |
| `sp_refund_upsert` | Registra una devolucion de un pago. | `CALL sp_refund_upsert(p_action, p_id_refund, p_id_payment, p_amount_cents, p_reason, p_reference, p_created_by);` | Inserta/actualiza `refund`, ajusta `line_item` y recalcula folio. | Importante para trazabilidad de reembolsos. |

## 5. Propiedades, habitaciones, huespedes y usuarios
Catalogos operativos base del PMS.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_property_upsert` | Crea o actualiza una propiedad con datos generales, contacto, orden y amenidades. | `CALL sp_property_upsert(p_company_code, p_property_code, p_name, p_description, p_email, p_phone, p_website, p_address_line1, p_address_line2, p_city, p_state, p_postal_code, p_country, p_timezone, p_currency, p_check_out_time, p_order_index, p_is_active, p_notes, p_has_wifi, p_has_parking, p_has_shared_kitchen, p_has_dining_area, p_has_cleaning_service, p_has_shared_laundry, p_has_purified_water, p_has_security_24h, p_has_self_checkin, p_has_pool, p_has_jacuzzi, p_has_garden_patio, p_has_terrace_rooftop, p_has_hammocks_loungers, p_has_bbq_area, p_has_beach_access, p_has_panoramic_views, p_has_outdoor_lounge, p_offers_airport_transfers, p_offers_tours_activities, p_has_breakfast_available, p_offers_bike_rental, p_has_luggage_storage, p_is_pet_friendly, p_has_accessible_spaces, p_id_owner_payment_obligation_catalog, p_amenities_is_active, p_actor_user_id);` | Inserta/actualiza `property` y `property_amenities`. | SP grande porque centraliza metadata operacional y comercial. |
| `sp_get_company_properties` | Lista propiedades de una compania. | `CALL sp_get_company_properties(p_company_code);` | Devuelve propiedades disponibles. | Muy util para combos de seleccion. |
| `sp_roomcategory_upsert` | Crea o actualiza una categoria de habitacion con amenidades y configuracion visual de calendario. | `CALL sp_roomcategory_upsert(p_property_code, p_category_code, p_name, p_description, p_base_occupancy, p_max_occupancy, p_order_index, p_default_base_price_cents, p_min_price_cents, p_image_url, p_rateplan_code, p_color_hex, p_has_air_conditioning, p_has_fan, p_has_tv, p_has_private_wifi, p_has_minibar, p_has_safe_box, p_has_workspace, p_includes_bedding_towels, p_has_iron_board, p_has_closet_rack, p_has_private_balcony_terrace, p_has_view, p_has_private_entrance, p_has_hot_water, p_includes_toiletries, p_has_hairdryer, p_includes_clean_towels, p_has_coffee_tea_kettle, p_has_basic_utensils, p_has_basic_food_items, p_is_private, p_is_shared, p_has_shared_bathroom, p_has_private_bathroom, p_amenities_is_active, p_calendar_display_amenities_csv, p_is_active, p_actor_user_id, p_id_category);` | Inserta/actualiza `roomcategory`, amenidades y despliegue en calendario. | Catalogo maestro de tipos de habitacion. |
| `sp_room_upsert` | Crea o actualiza una habitacion fisica. | `CALL sp_room_upsert(p_property_code, p_room_code, p_category_code, p_rateplan_code, p_name, p_description, p_capacity_total, p_max_adults, p_max_children, p_status, p_housekeeping_status, p_floor, p_building, p_bed_config, p_color_hex, p_order_index, p_is_active, p_id_room);` | Inserta/actualiza `room`. | Define inventario reservable real. |
| `sp_guest_upsert` | Crea o actualiza un huesped. | `CALL sp_guest_upsert(p_email, p_names, p_last_name, p_maiden_name, p_phone, p_language, p_marketing_opt, p_blacklisted, p_notes);` | Inserta/actualiza `guest` y devuelve el registro. | Util para CRM basico y captacion de datos. |
| `sp_app_user_upsert` | Crea o actualiza un usuario interno del sistema. | `CALL sp_app_user_upsert(p_company_code, p_id_user, p_email, p_password, p_names, p_last_name, p_maiden_name, p_phone, p_locale, p_timezone, p_is_owner, p_is_active, p_display_name, p_notes, p_actor_user_id);` | Inserta/actualiza `app_user`. | Se complementa con `sp_user_role_sync` y `sp_user_property_sync`. |

## 6. Catalogos de conceptos de venta
Este dominio administra categorias, catalogos, relaciones padre-hijo y cargos efectivos dentro de folios.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_sale_item_category_data` | Consulta categorias de conceptos de venta. | `CALL sp_sale_item_category_data(p_company_code, p_property_code, p_show_inactive, p_category_id);` | Devuelve categorias filtradas. | Catalogo de lectura para administracion. |
| `sp_sale_item_category_upsert` | Crea o actualiza una categoria de conceptos. | `CALL sp_sale_item_category_upsert(p_action, p_id_category, p_company_code, p_property_code, p_category_name, p_description, p_is_active, p_created_by, p_id_parent_category);` | Inserta/actualiza `sale_item_category`. | Soporta jerarquia por categoria padre. |
| `sp_sale_item_catalog_data` | Consulta el catalogo de conceptos/cargos. | `CALL sp_sale_item_catalog_data(p_company_code, p_property_code, p_show_inactive, p_item_id, p_category_id);` | Devuelve conceptos con sus metadatos. | Base de configuracion de cargos e impuestos. |
| `sp_sale_item_catalog_upsert` | Crea o actualiza un concepto del catalogo. | `CALL sp_sale_item_catalog_upsert(p_action, p_id_item, p_company_code, p_catalog_type, p_id_category, p_parent_ids, p_item_name, p_description, p_unit_price_cents, p_is_percent, p_tax_rule_ids, p_show_in_folio, p_allow_negative, p_is_active, p_add_to_father_total, p_created_by);` | Inserta/actualiza `line_item_catalog` y relaciones padre. | Soporta precio fijo, porcentual y reglas fiscales. |
| `sp_sale_item_catalog_parent_total_upsert` | Configura la relacion de acumulacion de un catalogo hijo hacia un padre. | `CALL sp_sale_item_catalog_parent_total_upsert(p_action, p_id_item, p_id_parent, p_add_to_father_total, p_show_in_folio_relation, p_created_by);` | Inserta/actualiza `line_item_catalog_parent`. | Controla si el hijo suma al padre y como se presenta. |
| `sp_sale_item_catalog_calc_upsert` | Define un catalogo calculado a partir de componentes y signos. | `CALL sp_sale_item_catalog_calc_upsert(p_action, p_id_item, p_id_parent, p_component_ids_csv, p_component_signs_csv, p_created_by);` | Inserta/actualiza `line_item_catalog_calc`. | Para conceptos formula, no solo precio fijo. |
| `sp_sale_item_catalog_clone` | Clona un concepto del catalogo junto con relaciones padre y calculos. | `CALL sp_sale_item_catalog_clone(p_company_code, p_source_item_id, p_clone_item_name, p_created_by);` | Crea nuevo `line_item_catalog` con estructura equivalente. | Muy util para duplicar conceptos complejos. |
| `sp_sale_item_upsert` | Crea o actualiza un cargo real dentro de un folio/reservacion. | `CALL sp_sale_item_upsert(p_action, p_id_sale_item, p_id_folio, p_id_reservation, p_id_sale_item_catalog, p_description, p_service_date, p_unit_price_cents, p_discount_amount_cents, p_status, p_created_by);` | Inserta/actualiza `line_item`, deriva hijos porcentuales y recalcula folio. | SP operativo principal para cargos. |
| `sp_sale_item_child_upsert` | Crea o actualiza un cargo hijo delegando al SP general de cargos. | `CALL sp_sale_item_child_upsert(p_action, p_id_sale_item, p_id_parent_sale_item, p_id_folio, p_id_reservation, p_id_sale_item_catalog, p_service_date, p_unit_price_cents, p_status, p_created_by);` | Encapsula llamado a `sp_sale_item_upsert`. | Conveniente cuando ya conoces el padre. |
| `sp_sale_item_report_data` | Genera un reporte de cargos con multiples filtros. | `CALL sp_sale_item_report_data(p_company_code, p_property_code, p_from, p_to, p_search, p_status, p_folio_status, p_parent_category_id, p_category_id, p_catalog_id, p_min_total_cents, p_max_total_cents, p_has_tax, p_show_inactive, p_show_canceled_reservations);` | Devuelve dataset analitico de cargos. | Muy util para conciliacion y auditoria de conceptos. |

## 7. Rateplans y precios
Estos SP modelan tarifas, temporadas, overrides y calculo final de hospedaje.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_rateplan_upsert` | Crea o actualiza un rateplan. | `CALL sp_rateplan_upsert(p_property_code, p_rateplan_code, p_name, p_description, p_currency, p_refundable, p_min_stay, p_max_stay, p_effective_from, p_effective_to, p_is_active, p_actor_user_id);` | Inserta/actualiza `rateplan`. | Cabecera de configuracion tarifaria. |
| `sp_rateplan_pricing_upsert` | Define configuracion de pricing del rateplan, incluyendo uso de temporada u ocupacion. | `CALL sp_rateplan_pricing_upsert(p_property_code, p_rateplan_code, p_use_season, p_use_occupancy, p_is_active);` | Inserta/actualiza `rateplan_pricing`. | Ajusta como se compone el precio. |
| `sp_rateplan_season_upsert` | Crea o actualiza una temporada dentro de un rateplan. | `CALL sp_rateplan_season_upsert(p_property_code, p_rateplan_code, p_id_rateplan_season, p_season_name, p_start_date, p_end_date, p_priority, p_is_active);` | Inserta/actualiza `rateplan_season`. | Permite prioridad entre temporadas. |
| `sp_rateplan_override_upsert` | Crea o actualiza un override de precio para fecha, categoria o habitacion. | `CALL sp_rateplan_override_upsert(p_property_code, p_rateplan_code, p_id_rateplan_override, p_id_category, p_id_room, p_override_date, p_price_cents, p_notes, p_is_active);` | Inserta/actualiza `rateplan_override`. | Gana sobre configuracion base cuando aplica. |
| `sp_rateplan_calendar` | Devuelve el calendario tarifario de un rateplan. | `CALL sp_rateplan_calendar(p_property_code, p_rateplan_code, p_category_code, p_room_code, p_from, p_days);` | Devuelve desglose diario calculado. | Muy util para UI de edicion y diagnostico. |
| `sp_rateplan_calc_total` | Calcula total, promedio nocturno y breakdown para una estancia. | `CALL sp_rateplan_calc_total(p_property_id, p_rateplan_id, p_room_id, p_category_id, p_check_in, p_check_out, @p_total_cents, @p_avg_nightly_cents, @p_breakdown_json);` | Llena parametros OUT con total, promedio y JSON de desglose. | SP clave para cotizacion y cargos de hospedaje. |

## 8. OTA e iCal
Integraciones de cuentas OTA, mapeos de catalogos y alimentacion por iCal.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_ota_account_data` | Consulta cuentas OTA por compania, propiedad o id. | `CALL sp_ota_account_data(p_company_code, p_property_code, p_include_inactive, p_id_ota_account);` | Devuelve cuentas OTA y metadatos. | Lectura principal para administracion. |
| `sp_ota_account_upsert` | Crea o actualiza una cuenta OTA. | `CALL sp_ota_account_upsert(p_mode, p_id_ota_account, p_company_code, p_property_code, p_platform, p_ota_name, p_external_code, p_contact_email, p_timezone, p_notes, p_id_service_fee_payment_catalog, p_is_active, p_actor_user_id);` | Inserta/actualiza `ota_account` y tablas relacionadas. | Punto central para Airbnb, Booking u otras plataformas. |
| `sp_ota_account_info_catalog_sync` | Sincroniza catalogos informativos ligados a una cuenta OTA. | `CALL sp_ota_account_info_catalog_sync(p_company_code, p_id_ota_account, p_catalog_ids_csv, p_actor_user_id);` | Reemplaza registros en `ota_account_info_catalog`. | Uso administrativo de mapeos auxiliares. |
| `sp_ota_account_lodging_sync` | Sincroniza catalogos de hospedaje asociados a una cuenta OTA. | `CALL sp_ota_account_lodging_sync(p_company_code, p_id_ota_account, p_catalog_ids_csv, p_actor_user_id);` | Reemplaza `ota_account_lodging_catalog`. | Importante para enlazar cargos de hospedaje OTA. |
| `sp_ota_ical_feed_lodging_data` | Devuelve mapeos de hospedaje para un feed iCal. | `CALL sp_ota_ical_feed_lodging_data(p_company_code, p_id_ota_ical_feed);` | Devuelve catalogos ligados al feed. | Soporte de edicion/config de feeds. |
| `sp_ota_ical_feed_lodging_upsert` | Sincroniza catalogos de hospedaje de un feed iCal. | `CALL sp_ota_ical_feed_lodging_upsert(p_company_code, p_id_ota_ical_feed, p_lodging_catalog_ids, p_updated_by);` | Reemplaza `ota_ical_feed_lodging_catalog` y orden. | Es sincronizacion total del conjunto. |
| `sp_ota_ical_lodging_catalog_data` | Lista catalogos elegibles de hospedaje para integracion iCal. | `CALL sp_ota_ical_lodging_catalog_data(p_company_code, p_id_property);` | Devuelve opciones de catalogo. | Alimenta selectores de configuracion. |

## 9. Actividades
Gestion de catalogo de actividades y reservas de actividades ligadas a reservaciones.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_activity_upsert` | Crea o actualiza una actividad ofrecida por la propiedad o compania. | `CALL sp_activity_upsert(p_id_company, p_id_property, p_code, p_name, p_description, p_duration_minutes, p_base_price_cents, p_id_sale_item_catalog, p_currency, p_capacity_default, p_location, p_is_active);` | Inserta/actualiza `activity`. | Une operacion y catalogo de cobro. |
| `sp_get_company_activities` | Lista actividades de una compania. | `CALL sp_get_company_activities(p_company_code);` | Devuelve actividades disponibles. | Uso frecuente en formularios y reportes. |
| `sp_activity_book` | Reserva una actividad para una reservacion en una fecha/hora dada. | `CALL sp_activity_book(p_company_id, p_activity_id, p_reservation_id, p_scheduled_at, p_num_adults, p_num_children, p_price_cents, p_currency, p_status);` | Inserta booking y relacion con reservacion. | Flujo simple de reserva de actividad. |
| `sp_activity_booking_upsert` | Crea o actualiza una reservacion de actividad con soporte multi-reservacion via CSV. | `CALL sp_activity_booking_upsert(p_company_id, p_booking_id, p_activity_id, p_reservation_ids_csv, p_scheduled_at, p_num_adults, p_num_children, p_price_cents, p_currency, p_status, p_notes, p_user_id, p_action);` | Inserta/actualiza `activity_booking` y relaciones. | Es la variante mas completa de booking. |
| `sp_activity_bookings_list` | Lista bookings de actividades con filtros por compania, propiedad, actividad, rango o booking puntual. | `CALL sp_activity_bookings_list(p_company_id, p_company_code, p_property_id, p_property_code, p_activity_id, p_activity_code, p_from, p_to, p_booking_id);` | Devuelve bookings con joins operativos. | Ideal para agenda o reporte de actividades. |
| `sp_activity_cancel` | Cancela una reservacion de actividad. | `CALL sp_activity_cancel(p_booking_id, p_note);` | Actualiza `activity_booking`. | Mantiene trazabilidad del motivo via nota. |

## 10. Reporteria
Incluye reportes contables clasicos y el motor mas configurable de definiciones, columnas y filtros.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_accounting_report_data` | Genera un dataset contable de hospedaje/cargos para una corrida simple. | `CALL sp_accounting_report_data(p_company_code, p_property_code, p_from, p_to, p_lodging_ids);` | Devuelve filas de reporte contable. | Reporte util para salidas predeterminadas. |
| `sp_report_catalog_options` | Devuelve opciones de catalogo necesarias para configurar reportes. | `CALL sp_report_catalog_options(p_company_code, p_property_code);` | Devuelve combos y opciones relacionadas a reportes. | Alimenta UI de configuracion. |
| `sp_report_config_data` | Recupera configuracion de un reporte por `report_key`. | `CALL sp_report_config_data(p_company_code, p_report_key);` | Devuelve cabecera/configuracion del reporte. | Enfocado al esquema anterior de `report_config`. |
| `sp_report_config_upsert` | Crea o actualiza una configuracion de reporte y sus columnas base. | `CALL sp_report_config_upsert(p_company_code, p_report_key, p_report_name, p_column_order, p_lodging_ids, p_cleaning_id, p_iva_id, p_ish_id, p_extra_ids, p_actor_user_id);` | Inserta/actualiza `report_config` y `report_config_column`. | Forma parte del modelo previo de reportes configurables. |
| `sp_report_definition_upsert` | Crea o actualiza la definicion principal de un reporte configurable. | `CALL sp_report_definition_upsert(p_action, p_company_code, p_id_report_config, p_report_key, p_report_name, p_report_type, p_line_item_type_scope, p_description, p_actor_user_id);` | Inserta/actualiza `report_config`. | Punto de entrada de definiciones nuevas. |
| `sp_report_definition_data` | Lee una definicion de reporte y su estructura. | `CALL sp_report_definition_data(p_company_code, p_id_report_config, p_report_key, p_include_inactive);` | Devuelve metadata de definicion. | Util para editor de plantillas/reportes. |
| `sp_report_definition_column_upsert` | Crea o actualiza una columna dentro de una definicion de reporte. | `CALL sp_report_definition_column_upsert(p_action, p_id_report_config_column, p_id_report_config, p_column_key, p_column_source, p_source_field_key, p_id_line_item_catalog, p_display_name, p_display_category, p_data_type, p_aggregation, p_format_hint, p_order_index, p_is_visible, p_is_filterable, p_filter_operator_default, p_actor_user_id);` | Inserta/actualiza `report_config_column`. | Controla visibilidad, orden y filtrabilidad. |
| `sp_report_definition_filter_upsert` | Crea o actualiza un filtro persistente de una definicion. | `CALL sp_report_definition_filter_upsert(p_action, p_id_report_config_filter, p_id_report_config, p_filter_key, p_operator_key, p_value_text, p_value_from_text, p_value_to_text, p_value_list_text, p_logic_join, p_order_index, p_is_active, p_actor_user_id);` | Inserta/actualiza `report_config_filter`. | Para defaults o filtros guardados del reporte. |
| `sp_report_definition_run_data` | Ejecuta una definicion de reporte con rango y limite de filas. | `CALL sp_report_definition_run_data(p_company_code, p_id_report_config, p_from, p_to, p_limit);` | Devuelve dataset ejecutado; usa temporales para columnas. | SP clave del motor de corridas de reporte. |
| `sp_report_field_catalog_data` | Devuelve campos/catalogos disponibles para construir reportes. | `CALL sp_report_field_catalog_data(p_company_code, p_property_code, p_report_type);` | Devuelve lista de campos elegibles. | Base para selectores de columnas y subdivisiones. |

## 11. Configuracion, tema y portal
Configuracion general del PMS, tema visual, plantillas y consultas para modulos de portal.

| SP | Funcionamiento | Como llamarlo | Salida / efecto | Notas |
|---|---|---|---|---|
| `sp_pms_settings_data` | Lee la configuracion principal del PMS por compania/propiedad. | `CALL sp_pms_settings_data(p_company_code, p_property_code);` | Devuelve settings activos. | Punto de lectura de configuracion operativa. |
| `sp_pms_settings_upsert` | Guarda catalogos de hospedaje, intereses y medios de pago en settings PMS. | `CALL sp_pms_settings_upsert(p_company_code, p_property_code, p_lodging_catalog_ids, p_interest_catalog_ids, p_payment_catalog_ids, p_created_by);` | Sincroniza tablas `pms_settings_*`. | Se usa como guardado global de settings funcionales. |
| `sp_pms_theme_data` | Consulta el tema configurado para una compania. | `CALL sp_pms_theme_data(p_company_code);` | Devuelve `theme_code` y metadata asociada. | Sirve para inicializar branding. |
| `sp_pms_theme_upsert` | Actualiza el tema visual activo de una compania. | `CALL sp_pms_theme_upsert(p_company_code, p_theme_code, p_actor_user_id);` | Inserta/actualiza `pms_company_theme`. | Cambio de branding por compania. |
| `sp_message_template_upsert` | Crea o actualiza plantillas de mensaje. | `CALL sp_message_template_upsert(p_company_code, p_property_code, p_template_code, p_title, p_body, p_is_active, p_id_message_template);` | Inserta/actualiza `message_template`. | Luego pueden usarse en envio/log de reservaciones. |
| `sp_portal_property_data` | Consulta propiedades para el portal o vistas administrativas ligeras. | `CALL sp_portal_property_data(p_company_code, p_search, p_only_active, p_property_code);` | Devuelve propiedades filtradas. | Endpoint de lectura/listado. |
| `sp_portal_guest_data` | Consulta huespedes para portal o backoffice. | `CALL sp_portal_guest_data(p_company_code, p_search, p_only_active, p_guest_id, p_actor_user_id);` | Devuelve huespedes filtrados. | Puede respetar actor para seguridad/auditoria. |
| `sp_portal_app_user_data` | Consulta usuarios internos del portal. | `CALL sp_portal_app_user_data(p_company_code, p_search, p_property_code, p_only_active, p_user_id);` | Devuelve usuarios filtrados. | Lectura administrativa de usuarios. |
| `sp_portal_reservation_data` | Consulta reservaciones para portal por propiedad, estatus, rango o id. | `CALL sp_portal_reservation_data(p_company_code, p_property_code, p_status, p_from, p_to, p_reservation_id, p_actor_user_id);` | Devuelve dataset de reservaciones. | Util para grids y dashboards del portal. |
| `sp_portal_activity_data` | Consulta actividades para portal o panel operativo. | `CALL sp_portal_activity_data(p_company_code, p_property_code, p_search, p_only_active, p_activity_id);` | Devuelve actividades filtradas. | Lectura ligera del modulo de actividades. |

## Recomendaciones practicas de uso

### Flujos mas comunes
- Crear reservacion directa: `sp_create_reservation`
- Apartar y luego confirmar: `sp_create_reservation_hold` -> `sp_reservation_confirm_hold`
- Cambiar fechas, habitacion o estado: `sp_reservation_update`
- Agregar cargos manuales: `sp_sale_item_upsert`
- Recalcular totales despues de ajustes: `sp_folio_recalc`
- Consultar disponibilidad: `sp_search_availability`
- Configurar precios: `sp_rateplan_upsert` + `sp_rateplan_season_upsert` + `sp_rateplan_override_upsert`
- Ejecutar reporte configurable: `sp_report_definition_run_data`

### Reglas de implementacion recomendadas
- No dupliques logica de negocio en PHP/JS si ya existe en un SP que valida y persiste.
- Si un SP sincroniza listas CSV (`*_sync`), asume reemplazo total del conjunto.
- Cuando actualices cargos o pagos, considera correr o reutilizar rutas que terminen en `sp_folio_recalc`.
- Si la operacion depende de permisos, valida antes con `sp_authz_assert`.
- Para diagnosticar errores funcionales, revisa primero los `SIGNAL 45000` del SP fuente.

### Orden de lectura recomendado para onboarding
1. [SQL_SP_AI_PLAYBOOK.md](c:\Users\ragnarok\Documents\repos\Proyecto VLV\PMS\SQL_SP_AI_PLAYBOOK.md)
2. [SQL_SP_MAP.md](c:\Users\ragnarok\Documents\repos\Proyecto VLV\PMS\SQL_SP_MAP.md)
3. Este archivo
4. El `sp_*.sql` puntual del dominio que vas a modificar

### Mantenimiento de esta referencia
- Si agregas un SP nuevo, registralo en el dominio correcto.
- Si cambias la firma de un SP, actualiza primero el ejemplo `CALL`.
- Si cambia de comportamiento transaccional, actualiza la columna "Salida / efecto".
