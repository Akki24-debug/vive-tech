# GUI Baseline

## Proposito

Este documento define el comportamiento minimo obligatorio para todos los GUIs del ecosistema Vive La Vibe.

Aplica a:

- drawers
- modales
- pantallas CRUD
- dashboards
- wizards
- paneles laterales
- tablas con detalle
- vistas admin y operativas

No es una sugerencia visual. Es un contrato de comportamiento.

## Regla raiz

Ninguna accion importante del usuario puede terminar en un estado ambiguo.

Toda accion de guardar, crear, editar, borrar, publicar, sincronizar, aprobar, cerrar o cancelar debe terminar en uno de estos estados visibles:

- completada
- fallida
- bloqueada por validacion
- en espera de aprobacion
- en progreso

Si la UI no deja claro cual de esos estados ocurrio, la UI esta incompleta.

## Reglas de acciones async

### 1. Inicio visible

Cuando una accion arranca, la UI debe mostrarlo de inmediato.

Minimo obligatorio:

- deshabilitar el CTA principal mientras corre
- cambiar el label del CTA a un estado activo
- mostrar spinner o indicador equivalente
- evitar doble submit

Ejemplos aceptables:

- `Save` -> `Saving...`
- `Publish` -> `Publishing...`
- `Sync roles` -> `Syncing...`

### 2. Exito visible

Cuando la accion termina bien, la UI debe cambiar de forma evidente. Nunca puede quedar igual que antes.

Minimo obligatorio:

- mostrar confirmacion visible en el mismo contexto donde ocurrio la accion
- refrescar datos desde la respuesta guardada del backend
- limpiar estado dirty
- actualizar sello visible de estado si existe

Al menos uno de estos cambios debe ser obvio:

- banner inline de exito
- toast
- cambio de modo `edit` a `view`
- cierre del modal/drawer con highlight del registro afectado
- actualizacion de `updated_at`, status, chip o label

### 3. Error visible

Si falla, el usuario debe saber:

- que fallo
- que no se guardo
- que puede hacer despues

Minimo obligatorio:

- mensaje visible en el mismo panel
- CTA vuelve a estar disponible
- mantener los datos capturados
- no cerrar automaticamente

### 4. Validacion antes de salir

Si el error es de validacion, debe marcar:

- campo
- mensaje
- regla incumplida

No se vale solo decir `Request failed`.

## Reglas de guardar

### 5. Save no puede ser silencioso

Despues de `Save`, la UI debe hacer una de estas rutas, de forma consistente:

#### Ruta A: mantener contexto abierto

Usar por defecto en drawers CRUD de detalle.

Despues de guardar:

- refrescar con la respuesta oficial del backend
- cambiar a `view` mode
- mostrar mensaje `Guardado`
- mantener el drawer abierto
- dejar visible el dato ya persistido

#### Ruta B: cerrar y regresar al origen

Usar en modales cortos o quick-create.

Despues de guardar:

- cerrar modal
- regresar al usuario a la lista o pantalla origen
- hacer highlight temporal del registro afectado
- mantener filtros, pagina y scroll

Cada GUI debe elegir una ruta por tipo de contenedor. No mezclar comportamientos al azar.

### 6. Siempre rehidratar desde backend

Despues de crear o editar:

- no confiar en los valores locales del form como estado final
- usar la respuesta del backend como verdad final
- reflejar normalizaciones, ids, timestamps, labels y defaults server-side

### 7. Guardado debe preservar contexto

Despues de guardar o publicar, la UI debe recordar:

- modulo actual
- tab actual
- pagina actual
- sort actual
- filtros actuales
- columnas visibles
- seleccion actual
- scroll razonable

Si el registro deja de verse por un filtro o sort, la UI debe explicarlo.

## Reglas de cerrar, cancelar y backdrop

### 8. Cerrar no puede destruir trabajo silenciosamente

Si hay cambios sin guardar:

- `Close`
- `Cancel`
- `Esc`
- click en backdrop

deben pedir confirmacion antes de descartar.

### 9. Durante save no se debe poder cerrar accidentalmente

Mientras una accion write corre:

- bloquear cierre por backdrop
- bloquear `Esc` si perderia trazabilidad
- deshabilitar CTAs conflictivos

### 10. Al cerrar, regresar al mismo origen

Cuando se cierra un drawer o modal, el usuario debe volver al mismo lugar funcional:

- misma lista
- misma fila o card, cuando aplique
- mismo scroll o scroll cercano
- mismo foco razonable

No mandar al usuario al top sin necesidad.

## Reglas de listas, tablas y detalle

### 11. Highlight temporal del registro afectado

Si una accion cambia un registro y el usuario vuelve a la lista:

- hacer highlight temporal de 2 a 5 segundos
- o mostrar badge `Updated`
- o reposicionar el viewport al registro actualizado

### 12. Persistencia local de preferencias

Las vistas con tabla o grid deben persistir cuando aplique:

- filtros
- sort
- page size
- columnas visibles
- modulo o seccion abierta

Persistir en `localStorage` o equivalente estable por pantalla.

### 13. No resetear formularios al azar

El form solo puede resetearse cuando:

- el usuario confirma descarte
- la accion termina con exito y el patron definido lo requiere
- se carga un registro distinto

## Reglas de feedback y mensajes

### 14. El feedback debe vivir cerca de la accion

No basta con un mensaje global perdido fuera de viewport.

Para drawers y modales:

- mostrar feedback dentro del mismo contenedor
- y opcionalmente duplicarlo con toast global

### 15. Los mensajes deben ser operativos

Preferir:

- `Registro guardado.`
- `No se pudo guardar. Falta el campo nombre.`
- `Documento publicado.`
- `No se pudo cerrar porque hay cambios sin guardar.`

Evitar:

- `OK`
- `Done`
- `Error`

## Reglas de dirty state

### 16. Dirty state explicito

Cuando el usuario modifica algo, la UI debe poder distinguir:

- limpio
- modificado
- guardando
- guardado
- error

Eso debe impactar:

- CTA principal
- mensajes
- cierre
- confirmaciones

### 17. Dirty state se limpia solo con exito real

No limpiar dirty state:

- por click en save
- por respuesta parcial
- por optimistic update sin confirmacion

Solo cuando el backend confirma exito.

## Reglas de QoL

### 18. Recuperacion de contexto

Si el usuario vuelve a una pantalla operativa frecuente, la UI debe intentar recordar:

- ultimo modulo abierto
- ultimo drawer razonable
- ultima busqueda
- ultima pagina
- ultima posicion util

Esto aplica especialmente a consolas admin, PMS y tableros CRUD.

### 19. CTAs coherentes

El CTA principal debe ser unico y claro.

Orden recomendado:

- CTA principal de escritura
- CTA secundario de accion alternativa
- CTA de cerrar o cancelar

No poner `Save` y `Close` con el mismo peso visual si una accion esta pendiente.

### 20. Operaciones destructivas

Delete, remove, unpublish, reject o similares deben:

- pedir confirmacion
- explicar impacto
- requerir razon si el dominio lo necesita
- dejar rastro visible

## Reglas de accesibilidad minima

### 21. Teclado

Minimo obligatorio:

- foco visible
- tab order estable
- `Esc` con comportamiento definido
- enter no debe disparar submits inesperados

### 22. Estados deshabilitados claros

Si un boton no puede usarse:

- debe verse deshabilitado
- debe quedar claro por que, cuando sea importante

## Reglas de implementacion

### 23. Contrato por componente

Cada GUI operativa debe definir explicitamente:

- patron de exito
- patron de error
- patron de cierre
- politica de dirty state
- politica de persistencia local

### 24. Checklist de aceptacion

Ningun GUI CRUD se considera listo si no responde `si` a todo esto:

- al guardar, el usuario ve que algo paso
- el CTA no permite doble submit
- el cierre no descarta cambios por accidente
- el estado final viene del backend
- el contexto se conserva
- el mensaje de error es accionable
- la accion terminada deja evidencia visible

## Aplicacion inmediata al Brain Control

Para `AI Assistant platform/apps/admin-ui/src/features/brain-control/BrainControlPanel.tsx`, el patron obligatorio por defecto debe ser:

- `Save` cambia a `Saving...`
- el drawer no se puede cerrar accidentalmente mientras guarda
- al exito, el drawer permanece abierto
- el drawer cambia a `view` mode
- se muestra confirmacion inline dentro del drawer
- se rehidrata con la respuesta del backend
- la lista debajo conserva filtros, sort, pagina y contexto

Si no cumple eso, el panel todavia no cumple el baseline.
