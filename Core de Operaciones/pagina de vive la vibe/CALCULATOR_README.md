# Calculadora de viaje (v13.2)

## Version resumida
- El cuadro lateral de la portada pide **personas, fecha de llegada y noches** y ofrece un listado plegable de **servicios adicionales**.
- Al presionar **Calcular** se abre un panel con el total en grande y chips que resumen noches, fechas y moneda.
- Dentro del panel hay tres bloques expandibles: hospedaje, actividades y servicios extra.
- Las actividades muestran descripcion, precio por persona y un control para ajustar cantidades; los servicios extra funcionan como checks opcionales.
- Siempre se propone un hospedaje representativo y, si el usuario quiere, puede mandarlo al carrito o ir directo al checkout.

## Detalle tecnico

### Objetivos
- Mantener un calculo inmediato combinando noches, personas, actividades y servicios.
- Mostrar totales claros mediante chips y bloques colapsables.
- Conservar la propuesta de hospedaje como cierre natural del flujo y permitir enviar la cotizacion al carrito o al checkout.

### Estructura del lightbox
1. **Resumen**: total en grande y chips (`.calc-chip`) para personas, noches, llegada, salida y moneda.
2. **Hospedaje**: selector de bandas (Ahorro, Balanceado, Premium) con estimados por noche y detalle de la combinacion de habitaciones.
3. **Actividades**: lista de tours (`window.VIVE_RESOURCES.activities`) con checkbox, descripcion, metadatos (duracion / ubicacion) y stepper de personas.
4. **Servicios adicionales**: catalogo plegable que refleja los checks del aside; admite data proveniente de `window.VIVE_RESOURCES.extras` o usa placeholders.
5. **Footer**: botones `Agregar hospedaje al viaje` (usa `VIVE_CART.addItem`) e `Ir al checkout` (envia un `quote` a `VIVE_CHECKOUT.open`).

Los bloques interactivos utilizan `<details>` para conservar accesibilidad de teclado y lectores de pantalla.

### Fuentes de informacion y fallback
- **Disponibilidad en vivo**: `VIVE_API.searchAvailability` alimenta las sugerencias. El endpoint devuelve montos en centavos y la calculadora los normaliza a pesos con dos decimales.
- **Referencias locales**: si la API responde vacio o falla, se reutiliza `VIVE_RESOURCES.lodgings`, descartando habitaciones sin tarifa numerica.
- **Actividades**: siempre se toman tours (`type === 'tour'`) del mismo objeto `VIVE_RESOURCES`.
- **Servicios extra**: se normaliza `VIVE_RESOURCES.extras`; si no hay datos, se cargan los placeholders del aside.
- Todo opera en la moneda base `R().currency` (por defecto MXN); no se aplican conversiones FX.

### Modelo de calculo
1. **Hospedaje**: con las habitaciones disponibles se construyen tres referencias: Ahorro usa el precio minimo, Balanceado toma el promedio redondeado a $50 y Premium utiliza la tarifa maxima. Cada boton busca la combinacion de habitaciones mas cercana a ese objetivo.
2. **Actividades**: suma de `precio x personas` para cada actividad seleccionada.
3. **Servicios extra**: suma de los precios unitarios marcados; si no hay precio, se etiqueta como "Precio a confirmar" y no impacta los totales.
4. **Total general**: hospedaje (si existe una cifra), mas actividades y mas extras. El total por persona se divide entre el numero de viajeros.

Cuando la disponibilidad no devuelve tarifas se genera una sugerencia generica (por ejemplo, "Habitacion para 2 personas") y se omiten datos de categoria o descripcion para dejar claro que es un estimado.

### Integraciones y payloads
- **Carrito (`VIVE_CART`)**: se envia un item con subtotal fijo, cantidad 1 y titulo `{hotel} - {habitacion}`.
- **Checkout (`VIVE_CHECKOUT`)**: se envia un objeto con `currency`, `checkIn`, `checkOut`, `nights`, el bloque opcional de hospedaje, arrays de actividades y extras (incluyendo subtotales), y un resumen `totals` que agrega actividades + extras y combina el hospedaje cuando hay cifra fija.
- **Debug**: `window.OPEN_VIBE_CALC()` abre el modal usando el ultimo estado calculado.

### Hooks reutilizados del index
- `#btnSearch` dispara el modal.
- Inputs dentro de `#bookerCard`: fecha (`input[type="date"]`), noches (`#lblCheckOut` + `<input type="number">`) y personas (`#lblPeople`).
- Checkboxes `#act_{id}` para tours y `#extra_{id}` para servicios adicionales.
- `openLightbox` permite ver la galeria del hospedaje sugerido.

### Flujo sugerido para QA
1. Completar el mini booker (personas, fecha, noches) y, opcionalmente, seleccionar tours/servicios.
2. Abrir la calculadora y confirmar que los chips del resumen muestren la informacion correcta.
3. Cambiar la banda de hospedaje y las cantidades de personas/tours para validar el recalculo de totales.
4. Probar la seleccion de servicios adicionales y verificar que aparezcan en el apartado "Servicios adicionales" y en el desglose.
5. Confirmar que "Agregar hospedaje al viaje" e "Ir al checkout" funcionen cuando `VIVE_CART`/`VIVE_CHECKOUT` estan disponibles.
6. Simular un fallo de disponibilidad para comprobar que se use la data de referencia y se muestre la sugerencia generica.

El script opera 100% del lado del cliente; solo toca `VIVE_CART`, `VIVE_CHECKOUT` o `OPEN_VIBE_CALC` si existen en `window`.
