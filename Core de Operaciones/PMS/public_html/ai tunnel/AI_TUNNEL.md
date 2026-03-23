# AI Tunnel

## Intencion

`ai tunnel` es una zona de prueba para exponer vistas externas de solo lectura usando URLs autenticadas y los SP ya existentes del PMS.

La version actual ya no depende de una sola vista grande. Ahora trabaja con endpoints especializados y filtros por URL para que un GPT consulte solo el dominio y la ventana exacta que necesita.

## Regla base

- autenticacion por `credential` en la URL;
- respuestas HTML tabulares legibles para humano y LLM;
- nombres y codigos canonicos visibles;
- filtros consistentes entre endpoints;
- errores claros si un parametro no existe o una fecha es invalida.

## Contrato de filtros compartido

Todos los endpoints aceptan:

- `credential` obligatorio
- `property_code` CSV exacto canonico, case-insensitive
- `category_code` CSV exacto canonico, case-insensitive
- `room_code` CSV exacto canonico, case-insensitive
- `property_id` opcional como compatibilidad legacy

Filtros adicionales segun endpoint:

- `status`
- `reservation_code`
- `folio_id`
- `guest_query`
- `date_from`
- `date_to`
- `date_at`

Semantica:

- varios valores en el mismo parametro CSV = `OR`
- distintos parametros = `AND`
- si llega solo `date_from` o solo `date_to`, el otro extremo se iguala
- parametro no soportado o fecha invalida = `400`
- filtros validos sin resultados = `200` con tablas vacias

## Bootstrap principal

`solicitar_catalogo_operativo.php`

Este endpoint debe ser el punto de partida del GPT cuando necesite:

- descubrir propiedades, categorias o habitaciones
- confirmar codigos canonicos
- entender que filtros puede aplicar en otro endpoint

Trae:

- ficha de propiedad
- categorias
- habitaciones
- actividad de apoyo para el rango pedido
- contrato visible de filtros y ejemplos de URL

## Endpoints

### `solicitar_catalogo_operativo.php`

Bootstrap canonico.

### `solicitar_disponibilidad_30_dias.php`

Disponibilidad filtrada por `date_from` y `date_to`.
El nombre del archivo se conserva, pero funcionalmente ya no esta amarrado a 30 dias.

Trae:

- disponibilidad iniciando en `date_from`
- disponibilidad iniciando en el siguiente dia visible
- matriz diaria ocupada/libre con precio

### `solicitar_huespedes_en_casa.php`

Huespedes hospedados en `date_at`.

### `solicitar_reservaciones_detalle.php`

Reservaciones, folios, pagos y line items para la ventana pedida.

### `solicitar_estado_actual.php`

Vista integral de respaldo. Usa exactamente el mismo parser de filtros compartidos.

## Defaults

- disponibilidad: sin fechas = `hoy .. hoy+29`
- reservaciones detalle: sin fechas = ventana operativa de `config.php`
- huespedes en casa: sin `date_at` = hoy
- catalogo operativo: sin fechas = actividad de apoyo para hoy

## Regla operativa para GPTs

Orden recomendado:

1. usar `solicitar_catalogo_operativo.php` si necesita descubrir nombres, codigos o filtros
2. luego elegir el endpoint mas angosto posible
3. usar `solicitar_estado_actual.php` solo si realmente necesita mezclar dominios

## Riesgos conocidos

- `credential` en URL sigue siendo suficiente solo para testing
- `property_id` sigue vivo como compatibilidad legacy, pero el contrato canonico ya es por `property_code`
- el nombre `solicitar_disponibilidad_30_dias.php` se mantiene por compatibilidad, aunque ahora ya acepta fechas exactas
