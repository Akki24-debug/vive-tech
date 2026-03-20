# Consultador de informacion

Documento vivo para entender el estado actual de este GPT, registrar hallazgos y alinear los siguientes cambios.

## Proposito actual

El GPT esta planteado para:

- interpretar la solicitud del usuario dentro del contexto de Vive La Vibe;
- construir una URL autorizada usando solo `credential`;
- consultar esa fuente autorizada;
- responder solo con lo que regrese la fuente;
- filtrar dentro de la respuesta general los datos de propiedades, reservaciones, huespedes, folios, line items, precios y demas informacion operativa.

## Archivos actuales

- `instrucciones.txt`: marco general del GPT y lista de archivos auxiliares.
- `00_configuracion_actual.txt`: variables vigentes. Hoy incluye una `credential` de testing.
- `01_reglas_base.txt`: reglas duras, prohibiciones y criterio general de seguridad.
- `02_fuentes_y_variables.txt`: fuente autorizada unica y reglas para construir la consulta.
- `03_mapa_propiedades.txt`: ya no contiene mapa fijo; ahora define como resolver entidades dentro de la consulta general.
- `04_flujo_operativo.txt`: secuencia operativa obligatoria antes de responder.
- `05_errores_estilo_y_ejemplos.txt`: mensajes esperados, estilo y ejemplos.

## Flujo que hoy intenta cubrir

1. Entender que dato del negocio pide el usuario.
2. Resolver referencias relativas de tiempo con la hora local del negocio en la zona centro de Mexico.
3. Confirmar que exista `credential`.
4. Armar la URL general autorizada.
5. Consultar la fuente.
6. Localizar dentro de la respuesta general las tablas o filas relevantes.
7. Responder solo con los datos devueltos.
8. Si falta informacion o hay error, decirlo sin inventar nada.

## Opinion actual

La base conceptual sigue siendo buena. La separacion por archivos tiene sentido, el enfoque de seguridad esta claro y el sistema ya marca bien la regla mas importante: no inventar ni mezclar informacion externa.

Con la limpieza de esta ronda, el sistema ya quedo mejor alineado. Los puntos que siguen importando son estos:

### 1. La prioridad ya quedo mejor definida

Ahora ya esta separado algo importante:

- las instrucciones mandan sobre el comportamiento;
- la fuente autorizada manda sobre los datos del negocio.

### 2. La configuracion actual no esta lista para operar

La configuracion ya vuelve a incluir la `credential` de testing `change-me-ai-tunnel`, alineada con el tunel actual. Eso permite probar el flujo mientras siga vigente en el backend.

Riesgo:

- esa credencial no deberia considerarse definitiva ni de produccion;
- cuando cambie en el tunel, tambien debe actualizarse aqui.

### 3. El mayor hueco sigue siendo el contrato de la fuente

Ya sabemos que la fuente devuelve tablas, lo cual ayuda, pero todavia falta documentar con precision que secciones y campos aparecen en cada bloque. Sin eso, el GPT sigue interpretando la respuesta con contexto incompleto.

Conviene documentar:

- formato de respuesta;
- bloques o tablas esperadas;
- campos esperados;
- significado de cada campo;
- que hacer si faltan campos;
- ejemplos reales o anonimizados.

### 4. El archivo 3 ya no depende de un mapa fijo

Se elimino el mapa fijo de propiedades y cualquier mecanismo de consulta separada por entidad. Ahora ese archivo define una regla mas general: identificar entidades directamente dentro de la respuesta de la consulta general.

### 5. El formato actual funciona, pero no es el ideal para IA

Las tablas HTML se pueden usar, pero para una IA y para cualquier automatizacion el formato mas conveniente seria JSON estructurado.

Ventajas de JSON frente a tablas HTML:

- nombres de campo estables;
- menos ambiguedad al leer bloques;
- mejor manejo de valores vacios o nulos;
- filtrado mas simple por propiedad, reservacion, folio o line item;
- menos fragilidad si cambia el front visual.

Si no quieren migrar todavia, el siguiente mejor paso es documentar muy bien el contrato actual de las tablas.

### 6. Sigue faltando criterio para varias intenciones

El flujo ya no depende de "una propiedad" contra "todas", pero siguen faltando criterios explicitos para casos como:

- comparar dos propiedades concretas;
- pedir ranking o filtros complejos;
- pedir informacion historica;
- preguntas con fechas relativas como "hoy", "manana" o "este fin de semana";
- preguntas donde varias filas o bloques puedan coincidir con la misma entidad.

### 7. Habia una falla clara de comportamiento conversacional

En pruebas, el GPT todavia tendia a:

- decir que no pudo consultar sin intentar antes la URL final;
- explicar la mecanica del link en vez de responder el dato del negocio;
- reaccionar demasiado al intercambio conversacional en lugar de seguir el flujo.

Esta ronda endurecio precisamente esos puntos con reglas y ejemplos directos.

## Recomendacion de estructura

Si quieren que esto escale, yo lo dejaria asi:

- `instrucciones.txt`: constitucion corta, alcance, prohibiciones y regla de verdad.
- `00_configuracion_actual.txt`: variables activas por ambiente.
- `02_fuentes_y_variables.txt`: plantilla autorizada unica y parametros requeridos.
- `03_mapa_propiedades.txt`: reglas de resolucion de entidades dentro de la consulta general.
- `04_flujo_operativo.txt`: decision tree operativo.
- `05_errores_estilo_y_ejemplos.txt`: estilo y fallback.
- `06_contrato_fuente.txt`: esquema esperado de la respuesta y ejemplos.
- `07_intenciones_y_enrutamiento.txt`: como resolver preguntas comunes del negocio.

## Preguntas abiertas

Estas son las preguntas que hoy me hacen falta para afinar bien el sistema:

1. Este GPT exactamente como consulta la URL: con Actions, browsing, un middleware propio o algun conector externo?
2. Pueden compartir un ejemplo completo de respuesta de la fuente para documentar bien el contrato real?
3. `credential` sera fija por ambiente o piensan rotarla con frecuencia?
4. Cuando el usuario pide comparar dos o mas propiedades, que formato de respuesta prefieren?
5. Si mas adelante automatizan fechas, quieren fijar una zona horaria tecnica exacta como `America/Mexico_City` o solo la regla de "hora centro de Mexico"?
6. Hay campos sensibles en las tablas que deban ocultarse o resumirse antes de responder al usuario?

## Siguientes cambios recomendados

- Agregar un archivo con el contrato real de la fuente.
- Definir casos de intencion para comparaciones, filtros y agregados.
- Decidir si la fuente se mantiene en tablas HTML o se migra a JSON.
- Cargar una `credential` real cuando quieran activar el flujo.

## Bitacora

### 2026-03-18

Primera revision del material actual. La arquitectura conceptual va bien encaminada, pero todavia faltan definiciones operativas para volverla robusta y mantenible.

### 2026-03-18 - decision de alcance

Se elimino el mapa fijo de propiedades y se fijo como criterio unico la consulta general. El alcance ahora cubre todo lo que venga en las tablas de la fuente, incluyendo reservaciones, folios, line items, precios y demas datos operativos.

### 2026-03-18 - credencial de testing restaurada

Se restauro la `credential` de testing `change-me-ai-tunnel` en la configuracion del GPT porque el tunel actual sigue esperandola para las pruebas.

### 2026-03-18 - refuerzo de primera respuesta

Se reforzo el prompt para obligar al GPT a sustituir la `credential` en la plantilla, intentar la URL final antes de cualquier fallback y responder primero el dato del negocio en lugar de explicar el procedimiento.
