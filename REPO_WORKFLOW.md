# Repo Workflow

## Decision principal

Para este proyecto conviene usar **un solo repositorio** con raiz en `Proyecto VLV/`.

No recomiendo separar por Git en este momento porque:

- `Core de Operaciones/`, `AI Assistant platform/`, `cerebro de la empresa/` y `tools/` estan fuertemente conectados;
- necesitas cambiar contratos entre carpetas con frecuencia;
- tu friccion actual no viene por "demasiado codigo en un repo", sino por copias, rutas duplicadas y falta de proceso;
- un solo repo te deja ver el cambio completo cuando una mejora toca varias areas.

## Estructura Git recomendada

- `main`
  - rama estable
  - debe representar lo ultimo aprobado
- `task/<slug>`
  - rama temporal por tarea o bloque de trabajo
  - ejemplo: `task/guest-improvements`
- `hotfix/<slug>`
  - solo para arreglos urgentes
  - ejemplo: `hotfix/login-local-bom`

## Como pensar las versiones

Tu idea de que "una version sea un prompt con cambios" es valida.

La forma correcta de hacerlo en Git no es crear una rama por cada prompt. Lo correcto es:

- una rama por tarea general;
- un commit por checkpoint o por prompt aprobado dentro de esa tarea.

Ejemplo:

- rama: `task/guest-improvements`
- commits:
  - `guests: improve full-name search`
  - `guests: fix guest form spacing`
  - `guests: normalize phone validation`
  - `guests: adjust reservation link in guest panel`

Asi recuerdas cada paso sin llenar el repo de ramas permanentes.

## Que pasa cuando la tarea queda aprobada

Cuando una tarea ya quedo bien:

1. se mergea a `main`;
2. se borra la rama `task/...`;
3. el historial de commits queda guardado en `main`.

Eso significa:

- **no pierdes la historia**;
- **si puedes borrar la rama**;
- **no hace falta que las ramas temporales vivan para siempre**.

## Tipo de merge recomendado

Para tu caso recomiendo:

- `merge --no-ff` para tareas normales

Porque:

- conserva todos los commits internos;
- deja claro que varios commits pertenecian a una sola tarea;
- te permite borrar la rama despues sin perder el detalle.

No recomiendo `squash merge` como regla general, porque tu prioridad es recordar cada version intermedia.

## Flujo diario recomendado

1. Tu me pides una tarea.
2. Yo reviso `git status`.
3. Si la tarea no es trivial, creo una rama `task/<slug>`.
4. Trabajo en commits pequenos y logicos.
5. Cuando una subseccion quede bien, hago commit.
6. Cuando me confirmes que la tarea completa esta aprobada, hago merge a `main`.
7. Borro la rama temporal.

## Regla para commits

Un commit debe representar una unidad clara de trabajo.

Buenos ejemplos:

- `guests: fix search by full name`
- `guests: add local setup docs`
- `tools: add windows search repair script`
- `pms: wire local config example`

Malos ejemplos:

- `changes`
- `fix stuff`
- `more updates`

## Cuando no hace falta una rama

Puedes trabajar directo en `main` solo si:

- el cambio es muy pequeno;
- no toca logica sensible;
- es puro texto o documentacion menor;
- no abre una linea de trabajo de varias iteraciones.

Aun asi, por seguridad, mi recomendacion por defecto sera usar rama para casi cualquier cambio real.

## Instrucciones operativas para Codex

Cuando trabajes en este repo:

1. revisa primero `git status` y la rama actual;
2. no mezcles tareas distintas en una misma rama;
3. gestiona Git de forma automatica siempre que sea posible;
4. si el trabajo es mas que un ajuste minimo, propone abrir una rama `task/<slug>` y, si el usuario confirma, creala sin pedir pasos adicionales;
5. una vez dentro de la rama temporal, haz commits pequenos por checkpoint aprobado sin esperar instrucciones extra sobre Git;
6. no hagas merge a `main` sin confirmacion explicita del usuario;
7. cuando el usuario confirme cierre de tarea, haz el merge y despues elimina la rama temporal local;
8. no uses `git reset --hard` ni operaciones destructivas salvo instruccion explicita;
9. si encuentras cambios del usuario que no son tuyos, no los reviertas.

## Automatizacion esperada de la IA

La IA debe encargarse de la gestion cotidiana de Git y no dejarle al usuario los pasos tecnicos.

Eso significa:

- revisar estado del repo automaticamente al iniciar una tarea;
- detectar si conviene trabajar en `main` o en una rama temporal;
- sugerir el nombre de la rama si hace falta abrir una nueva;
- crear la rama temporal despues de una confirmacion breve del usuario;
- hacer commits pequenos cuando una subseccion ya quedo aprobada;
- preparar el merge cuando la tarea este completa;
- preguntar solo para:
  - abrir una nueva rama temporal;
  - confirmar que una subseccion ya puede guardarse en commit;
  - cerrar la tarea, mergear a `main` y borrar la rama temporal.

La IA no debe pedir al usuario que ejecute comandos de Git manualmente salvo que exista un bloqueo tecnico real.

## Preguntas minimas que Codex debe hacer

Para que el flujo sea casi automatico, las preguntas deben reducirse a estas:

1. `Quieres que abra una nueva rama temporal para esta tarea? Sugerencia: task/<slug>`
2. `Esta subseccion ya quedo aprobada para hacer commit?`
3. `Quieres que cierre la tarea, haga merge a main y borre la rama temporal?`

Fuera de esas preguntas, Codex debe intentar gestionar Git solo.

## Nombres recomendados para ramas

- `task/guest-improvements`
- `task/rbac-cleanup`
- `task/local-pms-setup`
- `task/website-booking-flow`
- `hotfix/search-crash-docs`

## Releases

No necesitas hacer tags por cada cambio chico.

Usa tags solo para hitos relevantes, por ejemplo:

- `pms-local-ready`
- `ai-platform-v1`
- `website-booking-stable`

## Resumen corto

- un solo repo: **si**
- ramas temporales por tarea: **si**
- commits pequenos por cada prompt o checkpoint valido: **si**
- borrar ramas despues del merge: **si**
- perder historial al borrar ramas: **no**, si ya fueron mergeadas
- subrepos o submodulos: **no por ahora**
