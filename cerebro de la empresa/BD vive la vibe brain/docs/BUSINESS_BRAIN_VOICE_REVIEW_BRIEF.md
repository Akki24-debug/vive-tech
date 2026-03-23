# Business Brain Voice Review Brief

## Proposito

Este documento existe para ser leido por otra IA y usado como base de una conversacion guiada por voz con el dueño del proyecto.

El flujo esperado es:

1. otra IA lee este documento;
2. esa IA explica el estado actual de la BD `vive_la_vibe_brain` por capas;
3. el usuario corrige, aclara, agrega contexto y responde preguntas;
4. la misma IA actualiza este documento sin borrar el historial de correcciones;
5. este documento actualizado regresa a Codex para meter cambios reales a la base de datos.

## Regla principal

La IA que lea este documento no debe inventar personas, roles, owners, responsables, proyectos ni relaciones.

Debe distinguir siempre entre:

- `VERIFIED_DB`: existe hoy en la base
- `VERIFIED_DOC`: aparece de forma explicita en documentos fuente
- `USER_CONFIRMED`: el usuario lo confirmo en la conversacion
- `OPEN`: falta confirmar
- `DO_NOT_ASSUME`: seria inventar si se llena

## Instrucciones para la IA que lo va a explicar

### Objetivo de la conversacion

Explicarle al usuario el estado actual del `business_brain` de Vive La Vibe de forma clara y por capas, para que pueda:

- corregir lo que este mal,
- confirmar lo que ya esta bien,
- agregar personas, roles, owners, responsables y contexto faltante,
- y dejar este documento listo para que Codex cargue esos datos a la BD.

### Forma de explicar

- explicar primero el panorama general y luego bajar al detalle
- hacer pausas cortas
- no soltar todo de golpe
- preguntar solo lo necesario
- no asumir que un ejemplo del documento ya es un dato real cargado
- cuando haya ambiguedad, marcarla como `OPEN`
- cuando el usuario confirme algo nuevo, escribirlo como `USER_CONFIRMED`

### Orden recomendado de explicacion

1. organizacion
2. equipo actual y roles
3. areas funcionales
4. lineas de negocio
5. prioridades y objetivos
6. documentos de conocimiento
7. huecos de informacion pendientes

### Como actualizar este documento

- no borrar el snapshot original
- agregar correcciones debajo de las secciones correspondientes
- mantener trazabilidad
- dejar claro que viene de la BD y que viene del usuario

## Fuentes usadas para este brief

### Base de datos local consultada

- host: `127.0.0.1`
- puerto: `3307`
- base: `vive_la_vibe_brain`

### Documentos fuente revisados

- `C:\Users\ragnarok\Downloads\Vive_la_Vibe_Plan_Infraestructura_IA_v1.md`
- `C:\Users\ragnarok\Downloads\Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md`
- [seed_mapping.md](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\cerebro%20de%20la%20empresa\BD%20vive%20la%20vibe%20brain\docs\seed_mapping.md)
- [BUSINESS_BRAIN_SCHEMA_WALKTHROUGH.md](C:\Users\ragnarok\Documents\repos\Proyecto%20VLV\cerebro%20de%20la%20empresa\BD%20vive%20la%20vibe%20brain\docs\BUSINESS_BRAIN_SCHEMA_WALKTHROUGH.md)

## Snapshot actual de la BD

Fecha de referencia del snapshot: `2026-03-22`

### Conteos actuales

- `organization`: 1
- `user_account`: 1
- `role`: 7
- `user_role`: 1
- `business_area`: 7
- `business_line`: 8
- `business_priority`: 1
- `objective_record`: 1
- `external_system`: 1
- `knowledge_document`: 3

## Estado actual por dominio

### 1. Organizacion

Estado:

- `VERIFIED_DB`

Registro actual:

- `organization.id = 1`
- nombre: `Vive la Vibe`
- status: `active`
- pais: `Mexico`

Notas:

- la organizacion principal ya existe
- esta parte si esta sembrada

### 2. Equipo actual

Estado:

- `VERIFIED_DB`
- parcialmente `OPEN` en cuanto a semantica real del rol

Usuario actualmente cargado:

- `user_account.id = 1`
- `display_name = Ro`
- `email = rodolfoh92@outlook.com`
- `role_summary = Admin General`
- `employment_status = active`
- `is_active = 1`

Relacion de rol actual:

- `Ro -> Admin General`
- `is_primary = 1`

Notas importantes:

- este usuario existe en la BD actual
- este documento no afirma por si solo si este usuario es fundador, director o administrador principal
- eso debe confirmarlo el usuario en la conversacion

### 3. Catalogo actual de roles

Estado:

- `VERIFIED_DB`
- parte del catalogo viene de `VERIFIED_DOC`
- parte viene de datos ya presentes en `user_account.role_summary`

Roles actuales:

- `Dirección`
- `Operación`
- `Tecnología`
- `Comercial`
- `Finanzas`
- `IA supervisor`
- `Admin General`

Proveniencia:

- `Dirección`, `Operación`, `Tecnología`, `Comercial`, `Finanzas`, `IA supervisor`
  - `VERIFIED_DOC`
  - salen como ejemplos explicitos en `Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md`
- `Admin General`
  - `VERIFIED_DB`
  - formalizado a partir de `user_account.role_summary`

### 4. Areas funcionales

Estado:

- `VERIFIED_DB`
- `VERIFIED_DOC`

Areas cargadas:

- `Dirección`
- `Operación`
- `Tecnología`
- `Finanzas`
- `Marketing`
- `Captación`
- `Experiencia huésped`

Lo que falta:

- `responsible_user_id` en todas esta `NULL`
- por ahora no hay responsables asignados

### 5. Lineas de negocio

Estado:

- `VERIFIED_DB`
- `VERIFIED_DOC`

Lineas cargadas:

- `Administración de hospedajes`
- `Automatización de hospedajes`
- `Tours y taxis`
- `Entregas`
- `Actividades Vive la Vibe`
- `Plataforma de comida`
- `Reacondicionamiento y decoración`
- `Marketing y captación`

Lo que falta:

- todos los `owner_user_id` estan en `NULL`
- hace falta confirmar si estas lineas siguen vigentes tal cual

### 6. Prioridad principal

Estado:

- `VERIFIED_DB`
- `VERIFIED_DOC`

Registro actual:

- `Fortalecer la planeación del equipo y la estructura interna del negocio`
- status: `active`
- owner: `NULL`

### 7. Objetivo principal

Estado:

- `VERIFIED_DB`
- `VERIFIED_DOC`

Registro actual:

- `Convertir la IA en un asistente integral de negocio`
- status: `active`
- owner: `NULL`
- `business_area_id = NULL`

### 8. Sistema externo

Estado:

- `VERIFIED_DB`
- `VERIFIED_DOC`

Registro actual:

- `PMS actual`
- tipo: `PMS`
- activo: `1`

### 9. Documentos de conocimiento

Estado:

- `VERIFIED_DB`

Registros actuales:

- `Plan de infraestructura de inteligencia artificial — Vive la Vibe`
- `Vive la Vibe — Esqueleto Sugerido de Base de Datos de Negocio`
- `Vive la Vibe - Business Brain Schema v1`

Notas:

- todos estan en `draft`
- no tienen `owner_user_id`
- no tienen `business_area_id`

## Huecos actuales que necesitan confirmacion del usuario

### Personas

- quien es `Ro` exactamente dentro del negocio
- si `Ro` debe quedar con nombre completo visible
- si hay otros miembros reales que ya deben existir en `user_account`

### Roles

- si `Admin General` debe conservarse como rol formal
- si `Admin General` en realidad debe mapearse a otro rol canonico
- si el catalogo actual de roles es suficiente o faltan roles reales del equipo

### Ownership

- responsables por `business_area`
- owners por `business_line`
- owner de la prioridad principal
- owner del objetivo principal

### Estructura

- si las areas funcionales actuales siguen bien
- si las lineas de negocio siguen vigentes o deben reordenarse

## Lo que Codex puede cargar despues sin inventar

Si el usuario deja este documento corregido, Codex puede usarlo para:

- crear nuevos `user_account`
- actualizar nombres, emails y `role_summary`
- crear o depurar `role`
- sincronizar `user_role`
- asignar `responsible_user_id` en `business_area`
- asignar `owner_user_id` en `business_line`
- asignar owner a `business_priority`
- asignar owner o area a `objective_record`
- actualizar metadata de `knowledge_document`

## Seccion para correcciones del usuario

Instruccion para la IA:

- usa esta seccion para registrar lo que el usuario corrija o confirme
- no reemplaces el snapshot original; agrega debajo

### Correcciones del usuario

- `OPEN`

### Confirmaciones del usuario

- `OPEN`

### Datos nuevos confirmados por el usuario

- `OPEN`

## Plantilla para completar despues de la conversacion

### Equipo confirmado

- nombre completo:
- display_name:
- email:
- telefono:
- rol principal:
- roles adicionales:
- estatus laboral:
- timezone:
- notas:

### Responsables por area

- Dirección:
- Operación:
- Tecnología:
- Finanzas:
- Marketing:
- Captación:
- Experiencia huésped:

### Owners por linea de negocio

- Administración de hospedajes:
- Automatización de hospedajes:
- Tours y taxis:
- Entregas:
- Actividades Vive la Vibe:
- Plataforma de comida:
- Reacondicionamiento y decoración:
- Marketing y captación:

### Prioridades y objetivos corregidos

- prioridad principal:
- owner de prioridad:
- objetivo principal:
- owner de objetivo:
- area del objetivo:

### Cambios listos para cargar a la BD

- `OPEN`

## Cierre para la IA que lo explique

Termina la conversacion dejando una lista corta de:

1. datos ya confirmados,
2. datos que siguen abiertos,
3. cambios que Codex ya puede cargar,
4. cambios que todavia no deben cargarse.
