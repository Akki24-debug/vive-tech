# Mapa de Esquema: Base de Datos Operativa del PMS

Este documento aplica especificamente a la base de datos operativa del PMS de Vive La Vibe.

Su objetivo es servir como mapa rapido para navegar la estructura relacional real del PMS y evitar suposiciones incorrectas al trabajar en modulos, reportes, AI Tunnel, integraciones o automatizaciones.

## Alcance

Este mapa describe la capa operativa principal del PMS:

- propiedades
- categorias
- amenidades
- habitaciones
- reservaciones
- huespedes
- folios
- line items
- pagos y catalogos relacionados
- planes de precio

No pretende sustituir:

- [SQL_SP_REFERENCE_DETAILED.md](C:/Users/ragnarok/Documents/repos/Proyecto%20VLV/PMS/SQL_SP_REFERENCE_DETAILED.md)
- los `.sql` de schema/migraciones
- la lectura directa de `SPs`

Mas bien complementa esa documentacion con una vista practica de "donde vive cada cosa".

## Fuente base para validar estructura

Las referencias canonicas para esta base son:

- [schema_u508158532_rodbd.sql](C:/Users/ragnarok/Documents/repos/Proyecto%20VLV/PMS/bd%20pms/schema_u508158532_rodbd.sql)
- [migration200323.sql](C:/Users/ragnarok/Documents/repos/Proyecto%20VLV/PMS/bd%20pms/migration200323.sql)
- [sp_portal_property_data.sql](C:/Users/ragnarok/Documents/repos/Proyecto%20VLV/PMS/bd%20pms/sp_portal_property_data.sql)
- [sp_roomcategory_upsert.sql](C:/Users/ragnarok/Documents/repos/Proyecto%20VLV/PMS/bd%20pms/sp_roomcategory_upsert.sql)
- [SQL_SP_REFERENCE_DETAILED.md](C:/Users/ragnarok/Documents/repos/Proyecto%20VLV/PMS/SQL_SP_REFERENCE_DETAILED.md)

## Regla operativa clave

Antes de asumir que un dato vive en una tabla "obvia", validar siempre:

1. si vive en la tabla principal
2. si vive en tabla satelite
3. si el `SP` ya lo trae resuelto
4. si la UI lo esta leyendo desde un `recordset` parcial

Ejemplo real importante:

- `roomcategory` define la categoria
- `category_amenities` guarda las amenidades de la categoria
- `category_calendar_amenity_display` guarda cuales amenidades de esa categoria se muestran como iconos en calendario

No asumir que las amenidades de categoria viven directamente en `roomcategory`.

## Bloques principales

### 1. Empresa y seguridad

Tablas base:

- `company`
- `app_user`
- `role`
- `permission`
- `role_permission`
- `user_role`
- `user_property`

Uso:

- delimitan empresa actual
- controlan permisos
- limitan acceso por propiedad

## 2. Propiedades

Tabla principal:

- `property`

Tablas satelite:

- `property_amenities`
- `pms_company_theme`
- `pms_settings`

Regla:

- `property` guarda identidad y datos base de la propiedad
- `property_amenities` guarda amenidades de propiedad, no de categoria

Ejemplos de datos que viven aqui:

- codigo de propiedad
- nombre
- direccion
- checkout time
- amenidades generales del alojamiento

## 3. Categorias de habitacion

Tabla principal:

- `roomcategory`

Tablas satelite:

- `category_amenities`
- `category_bed_config`
- `category_calendar_amenity_display`

Distribucion real:

- `roomcategory`
  - codigo de categoria
  - nombre
  - descripcion
  - ocupacion base/maxima
  - orden
  - precio base
  - precio minimo
  - `id_rateplan`
  - estado
- `category_amenities`
  - amenidades booleanas de la categoria
  - aire acondicionado
  - wifi privado
  - minibar
  - bano compartido/privado
  - etc.
- `category_bed_config`
  - configuracion de camas
- `category_calendar_amenity_display`
  - seleccion de amenidades que deben mostrarse como iconos/capsulas en calendario

### Caso clave: categoria vs amenidades

Si una vista necesita mostrar capsulas de amenidades de categoria:

- no leer solo `roomcategory`
- leer `category_amenities`
- o usar un `SP` que ya haga ese join

### SP relacionado

- `sp_roomcategory_upsert`

Ese `SP` no solo toca `roomcategory`; tambien actualiza:

- `category_amenities`
- `category_calendar_amenity_display`

## 4. Habitaciones

Tabla principal:

- `room`

Tablas satelite relacionadas:

- `room_block`
- `roomcategory`
- `property`

Regla:

- `room` representa la unidad fisica o comercial concreta
- la categoria de la habitacion se referencia por `id_category`

Ejemplos:

- codigo de habitacion
- nombre
- estatus operativo
- orden
- propiedad a la que pertenece
- categoria asignada

## 5. Reservaciones

Tabla principal:

- `reservation`

Tablas relacionadas:

- `reservation_group`
- `reservation_group_member`
- `reservation_interest`
- `reservation_note`
- `reservation_message_log`
- `reservation_source_catalog`
- `reservation_source_info_catalog`

Relaciones operativas:

- una reservacion apunta a una propiedad y normalmente a una habitacion
- una reservacion puede tener huesped principal y otros datos comerciales
- una reservacion puede generar folio y line items

### SPs clave

- `sp_create_reservation_hold`
- `sp_reservation_confirm_hold`
- `sp_reservation_update_v2`
- `sp_portal_reservation_data`

## 6. Huespedes

Tabla principal:

- `guest`

Uso:

- identidad del huesped
- contacto
- metadatos de perfil

Relacion operativa:

- `reservation` y vistas derivadas suelen traer nombre del huesped principal
- algunas lecturas de portal/dashboard lo proyectan ya unido

## 7. Folios, cargos y pagos

Tabla principal de folio:

- `folio`

Tabla principal de movimientos:

- `line_item`

Catalogos relacionados:

- `line_item_catalog`
- `line_item_catalog_calc`
- `line_item_catalog_parent`
- `line_item_hierarchy`
- `sale_item_category`
- `pms_settings_payment_catalog`
- `pms_settings_payment_method`
- `pms_settings_obligation_payment_method`

Regla importante:

- `folio` es el contenedor contable/comercial
- `line_item` representa cargos, impuestos, pagos, descuentos, ajustes, etc.
- no todos los `line_item` son pagos

### Regla operativa para pagos

Cuando una vista o IA necesite "pagos":

- no inferir pagos por nombre libre si existe bloque/consulta mas precisa
- distinguir pagos de cargos de hospedaje o impuestos
- revisar catalogo/tipo del `line_item`

## 8. Precios y planes

Tabla principal:

- `rateplan`

Tablas satelite:

- `rateplan_pricing`
- `rateplan_override`
- `rateplan_season`
- `rateplan_modifier`
- `rateplan_modifier_condition`
- `rateplan_modifier_schedule`
- `rateplan_modifier_scope`

Relacion con categorias:

- `roomcategory.id_rateplan`

SP clave:

- `sp_rateplan_calc_total`

## 9. Disponibilidad

Fuentes principales:

- `reservation`
- `room`
- `room_block`
- `rateplan_pricing`
- `rateplan_override`
- `occupancy_snapshot` en escenarios de snapshot/soporte

SP clave:

- `sp_search_availability`

Regla:

- disponibilidad no sale de una sola tabla
- se compone contra reservaciones, bloqueos, habitaciones activas y precio aplicable

## 10. OTAs

Tablas principales:

- `ota_account`
- `ota_account_info_catalog`
- `ota_account_lodging_catalog`
- `ota_price_override`
- `ota_ical_feed`
- `ota_ical_event`
- `ota_ical_event_map`

Regla:

- OTAs no son parte del core de reservacion, pero afectan precios, sincronizacion y calendario

## Relaciones practicas mas importantes

### Propiedad -> Categoria -> Habitacion

- `property.id_property`
- `roomcategory.id_property`
- `room.id_property`
- `room.id_category`

### Categoria -> Amenidades

- `roomcategory.id_category`
- `category_amenities.id_category`

### Categoria -> Visual de amenidades en calendario

- `roomcategory.id_category`
- `category_calendar_amenity_display.id_category`

### Reservacion -> Habitacion -> Propiedad

- `reservation.id_room`
- `room.id_room`
- `room.id_property`

### Reservacion -> Folio -> Line item

- `reservation.id_reservation`
- `folio.id_reservation`
- `line_item.id_folio`

## Dónde vive cada cosa

### "Nombre y codigo de categoria"

- `roomcategory`

### "Amenidades palomeadas de categoria"

- `category_amenities`

### "Amenidades de categoria que se muestran en calendario"

- `category_calendar_amenity_display`

### "Amenidades de propiedad"

- `property_amenities`

### "Configuracion de camas de categoria"

- `category_bed_config`

### "Precio base y precio minimo de categoria"

- `roomcategory`

### "Plan de precios de categoria"

- `roomcategory.id_rateplan`
- `rateplan`

### "Habitaciones de una categoria"

- `room.id_category`

### "Pagos"

- normalmente `line_item`, diferenciados por tipo/catalogo

### "Folio principal"

- `folio`

## SPs y tablas que tocan conceptualmente

### `sp_portal_property_data`

Se usa para vistas ligeras de propiedades/categorias/habitaciones.

Trae `recordsets` separados y ya resuelve parte de joins operativos.

No asumir que siempre trae todas las columnas auxiliares si una pantalla necesita extras muy especificos.

### `sp_portal_reservation_data`

Sirve para grids y lecturas operativas de reservaciones.

Base para:

- dashboard
- listados
- detalles parciales

### `sp_roomcategory_upsert`

Actualiza:

- `roomcategory`
- `category_amenities`
- `category_calendar_amenity_display`

### `sp_search_availability`

Consulta disponibilidad compuesta.

### `sp_rateplan_calc_total`

Calcula total de estancia y breakdown de precio.

## Advertencias para desarrollo

### 1. No asumir que el nombre de la tabla "obvia" contiene todo

Ejemplo:

- las amenidades de categoria no viven solo en `roomcategory`

### 2. No asumir que un `SP` trae todos los detalles que necesita una UI

Algunas pantallas requieren:

- rehidratar campos adicionales
- consultar tabla satelite
- o extender el `SP`

### 3. Cuando algo no aparece en pantalla, revisar en este orden

1. la UI si intenta renderizarlo
2. el dataset real que llega a la UI
3. la tabla satelite correcta
4. el `SP` que alimenta la vista
5. el estado `is_active` / `deleted_at`

## Checklist rapido por dominio

### Si trabajas en categorias

Revisar:

- `roomcategory`
- `category_amenities`
- `category_bed_config`
- `category_calendar_amenity_display`
- `sp_roomcategory_upsert`
- `sp_portal_property_data`

### Si trabajas en habitaciones

Revisar:

- `room`
- `roomcategory`
- `room_block`
- `sp_portal_property_data`

### Si trabajas en reservaciones

Revisar:

- `reservation`
- `guest`
- `folio`
- `line_item`
- `sp_portal_reservation_data`
- `sp_create_reservation_hold`
- `sp_reservation_confirm_hold`
- `sp_reservation_update_v2`

### Si trabajas en precios/disponibilidad

Revisar:

- `rateplan`
- `rateplan_pricing`
- `rateplan_override`
- `room`
- `room_block`
- `reservation`
- `sp_search_availability`
- `sp_rateplan_calc_total`

## Nota final

Este documento aplica a la base de datos operativa del PMS.

Si una decision de desarrollo depende de "donde vive realmente un dato", validar primero aqui y luego confirmar en:

- schema
- migraciones
- `SP` especifico

Eso evita errores de suposicion como el de amenidades de categoria vs `roomcategory`.
