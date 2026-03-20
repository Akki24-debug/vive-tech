# Report Grid Filters Standard

## Objetivo
Definir un sistema unico de filtros reutilizable para grids del PMS.

La idea es separar dos capas:

1. `Scope filters`
Filtran el dataset antes de renderizar el grid.

2. `Grid filters`
Filtran solamente las filas ya cargadas en pantalla, sin navegar a otra vista ni abrir otra pagina.

## Scope Filters
Estos controles viven arriba del grid y siempre deben existir.

### 1. Seccion de fechas
Siempre debe incluir:

- `Tipo de fecha`
- `Desde`
- `Hasta`

Tipos de fecha base:

- `Fecha de creacion`
- `Fecha de servicio`
- `Fecha de check in`
- `Fecha de check out`

Reglas:

- los dos calendarios usan solo fecha, no hora
- si `Desde` y `Hasta` son el mismo dia, la busqueda significa solo ese dia
- si uno de los dos viene vacio, se interpreta como rango abierto

Semantica por objeto de fila:

- si la fila representa `reservation`, `Fecha de creacion` usa `reservation.created_at`
- si la fila representa `reservation`, `Fecha de servicio` debe evaluar contra line items activos relacionados a la reservacion
- si la fila representa `line_item`, `Fecha de creacion` usa `line_item.created_at`
- si la fila representa `line_item`, `Fecha de servicio` usa `line_item.service_date`
- `Check in` y `Check out` siempre usan la reservacion ligada a la fila

### 2. Busqueda global
Siempre debe existir una barra de busqueda libre.

Reglas:

- busca contra todo el texto visible del grid
- no reemplaza filtros por columna
- debe poder limpiarse rapido

### 3. Otros scope filters del modulo
Cada modulo puede agregar sus propios filtros base.

Ejemplos:

- propiedad
- estatus
- plantilla
- tipo de folio

## Grid Filters
Estos filtros viven dentro del grid y operan sobre las filas ya renderizadas.

### Ordenamiento por columna
Cada columna del grid debe tener flechas clasicas de ordenamiento en el header.

Comportamiento:

- el ordenamiento es client-side sobre las filas ya cargadas
- cada click rota entre `asc`, `desc` y `sin orden`
- `sin orden` regresa al orden original con el que se renderizo el dataset
- el estado visual debe dejar claro si la columna esta ordenada ascendente o descendente
- el ordenamiento convive con filtros, subdivision y busqueda global

Reglas:

- columnas numericas ordenan por valor crudo numerico
- columnas de fecha ordenan por fecha real
- columnas de texto ordenan alfabeticamente
- valores vacios quedan al final
- si el grid esta subdividido, el ordenamiento se aplica dentro de cada subdivision

### Filtro por columna
Cada columna del grid puede tener un icono de filtro en el header.

Comportamiento:

- al presionar el icono se abre un `lightbox` ligero sobre la misma pagina
- el fondo no navega a otra vista
- el lightbox muestra los valores distintos actuales de esa columna
- cada valor aparece como checkbox
- hay acciones `Marcar todos` y `Desmarcar todos`
- el usuario puede cerrar sin perder el estado ya aplicado

Estado visual:

- si la columna no tiene filtro activo, el icono se ve neutro
- si tiene filtro activo, el icono cambia de estado para que sea evidente

Regla de filtro:

- una fila pasa si el valor de la celda pertenece al conjunto seleccionado para esa columna
- columnas sin filtro activo no restringen

### Valores vacios
Los vacios deben tratarse como un valor seleccionable:

- etiqueta visible: `Sin valor`
- valor interno estable: `__EMPTY__`

## Totales
Cada columna puede marcarse como `calcular total`.

Reglas:

- solo aplica a columnas numericas
- si el grid esta subdividido, cada subdivision calcula su subtotal
- al final siempre existe un bloque de `Totales generales`
- los totales se recalculan usando solo las filas visibles despues de aplicar filtros

## Subdivisiones
Si la plantilla define `Subdividir por`, el resultado se parte en subreportes por valor distinto de esa columna.

Reglas:

- cada subdivision hereda los mismos filtros globales y por columna
- si una subdivision queda sin filas visibles, se puede ocultar completa
- cada subdivision recalcula sus subtotales sobre filas visibles

### Subdivision runtime por columna
Ademas de la subdivision fija de la plantilla, el grid puede permitir subdivision runtime desde el header.

Comportamiento:

- con click derecho sobre una columna se abre un menu ligero
- el menu ofrece `Dividir reporte por <nombre de la columna>`
- esa subdivision runtime reemplaza cualquier subdivision activa previa
- la subdivision runtime se considera parte del estado de filtros del grid
- al usar `Limpiar filtros`, la subdivision runtime se elimina

Regla de precedencia:

- si existe subdivision runtime, gana sobre la subdivision definida en plantilla
- si no existe subdivision runtime, aplica la subdivision fija de la plantilla

## Reglas de UX

- los filtros globales deben quedar arriba del grid
- los filtros por columna viven en el header de cada columna
- el ordenamiento por columna tambien vive en el header
- limpiar filtros debe ser facil
- `Limpiar filtros` tambien debe quitar subdivision runtime
- nunca se debe perder el contexto visual del usuario al abrir un filtro de columna
- el sistema debe ser metadata-driven: el grid decide controles y comportamiento usando la definicion de columnas

## Aplicacion inicial en Reportes V2

Primera implementacion:

- `Scope filters`
  - tipo de fecha
  - desde
  - hasta
  - propiedad
  - estatus
  - plantilla

- `Grid filters`
  - busqueda global client-side sobre todo el texto del grid
  - ordenamiento client-side por columna con flechas en el header
  - filtro por columna via lightbox con checklist
  - recálculo client-side de subtotales y totales generales

## Futuro

Cuando se extienda a otros grids:

- el dataset debe exponer metadata por columna
- cada fila debe renderizar atributos crudos para filtrar sin reconsultar
- los footers deben poder recalcularse sobre filas visibles
