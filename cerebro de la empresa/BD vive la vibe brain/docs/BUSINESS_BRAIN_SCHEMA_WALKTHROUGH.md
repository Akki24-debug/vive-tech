# Business Brain Schema Walkthrough

## Objetivo de este documento

Este documento existe para explicar `vive_la_vibe_brain` de forma humana.

No reemplaza el schema SQL ni la referencia de stored procedures. Su trabajo es
explicar:

- que es esta base y que no es
- que partes estan activas hoy
- como se divide por dominios
- cuales son las tablas mas importantes para aprender primero
- como se usa desde el assistant
- como estudiarla sin perderse en las 57 tablas

Si alguien quiere entender la base por voz con ChatGPT, este es el mejor punto
de entrada.

## Que es esta base

`vive_la_vibe_brain` es la base de datos del cerebro operativo de Vive la Vibe.

Su proposito no es administrar reservas ni reemplazar el PMS. Su proposito es
guardar la memoria estructurada del negocio para que la IA pueda operar con
contexto, continuidad y trazabilidad.

Piensala asi:

- el PMS guarda la operacion transaccional de hospitalidad
- `BD vive la vibe brain` guarda estructura interna, prioridades, objetivos,
  proyectos, seguimiento, conocimiento e integraciones

En esta fase, la base es el andamio operativo del negocio, no el sistema total
de toda la empresa.

## Que no es esta base

Es importante no confundirla con otras capas:

- no es la fuente primaria de reservas, huespedes o disponibilidad
- no es un data warehouse historico
- no es un CRM
- no es un reemplazo del PMS
- no es todavia la fotografia completa de toda la operacion de Vive la Vibe

Es una base temprana, estructurada para crecer, pero sembrada hoy solo en el
nucleo contextual necesario para que la IA empiece a entender la empresa.

## Idea central del diseno

La pregunta que esta base intenta resolver es esta:

- como hacemos que la IA deje de ser solo un chatbot y se vuelva una capa
  operativa con memoria y seguimiento real

Por eso el schema no se limita a una sola cosa. Mezcla varios dominios:

- organizacion y personas
- estructura del negocio
- ejecucion de proyectos y tareas
- reuniones, decisiones y seguimiento
- conocimiento interno
- alertas, sugerencias e IA
- integraciones, auditoria y gobierno

La idea no es usar todo de golpe. La idea es tener un modelo coherente que pueda
absorber mas operacion sin rehacer la base despues.

## Estado actual: que esta activo hoy

La base completa ya existe, pero hoy solo esta sembrada la capa contextual
minima. Eso significa que el schema es amplio, pero los datos reales actuales
estan concentrados en pocas tablas.

### Tablas con datos reales hoy

- `organization`
- `business_area`
- `business_line`
- `business_priority`
- `objective_record`
- `external_system`
- `knowledge_document`

### Que significa eso en lenguaje simple

Hoy la base ya sabe:

- quien es Vive la Vibe como organizacion
- como se divide funcionalmente
- cuales son sus lineas de negocio actuales
- cual es la prioridad principal de esta fase
- cual es el objetivo estrategico central
- que sistema externo existe como referencia
- que documentos fundacionales sirven como memoria inicial

### Que sigue reservado para despues

El schema ya contempla, pero aun no depende de forma fuerte, de estos dominios:

- usuarios operativos y roles completos
- proyectos, subproyectos, tareas y milestones
- reuniones, decisiones y follow-up
- notas, politicas, SOPs, hipotesis y aprendizajes
- recordatorios, alertas y preferencias de notificacion
- sugerencias, insights y propuestas de IA
- automatizaciones
- integraciones y sincronizacion mas profundas

Eso no es sobreingenieria gratuita. Es una reserva de capacidad.

## Mapa por dominios

La forma correcta de estudiar esta base es por dominios, no tabla por tabla en
orden alfabetico.

### 1. Nucleo organizacional

Tablas clave:

- `organization`
- `user_account`
- `role`
- `user_role`
- `business_area`
- `user_area_assignment`
- `user_capacity_profile`
- `business_line`
- `business_priority`
- `objective_record`

Este dominio define:

- quien es la empresa
- como se estructura
- que areas existen
- que lineas de negocio existen
- quienes participan
- como se distribuye el trabajo
- que foco y objetivos tiene la organizacion

Si quieres entender primero "como esta armado el negocio", este es el dominio
inicial.

### 2. Ejecucion

Tablas clave:

- `project_category`
- `initiative`
- `project`
- `project_member`
- `project_objective_link`
- `subproject`
- `task`
- `milestone`
- `task_dependency`
- `task_update`
- `project_update`
- `blocker`
- `project_tag`
- `project_tag_link`
- `task_tag_link`
- `daily_checkin`
- `daily_checkin_item`

Este dominio representa el trabajo que se ejecuta.

Aqui vive todo lo relacionado con:

- iniciativas y proyectos
- descomposicion del trabajo
- ownership
- dependencias
- reportes de avance
- bloqueos
- seguimiento diario

Hoy este dominio esta mas preparado que poblado, pero es el camino natural de
crecimiento del schema.

### 3. Reuniones y seguimiento

Tablas clave:

- `meeting`
- `meeting_participant`
- `meeting_note`
- `decision_record`
- `decision_link`
- `follow_up_item`

Este dominio guarda memoria operativa de conversaciones y acuerdos.

La logica es:

- hubo una reunion
- participaron ciertas personas
- se tomaron notas
- se registraron decisiones
- esas decisiones quedaron vinculadas a proyectos, areas u otras entidades
- se generaron pendientes de seguimiento

Esto sirve para que la empresa no pierda contexto entre reuniones y ejecucion.

### 4. Conocimiento interno

Tablas clave:

- `knowledge_document`
- `knowledge_note`
- `policy_record`
- `sop`
- `business_hypothesis`
- `learning_record`

Este dominio convierte documentos y experiencia en memoria reutilizable.

No solo guarda archivos. Tambien guarda:

- notas de negocio
- politicas
- procedimientos
- hipotesis
- aprendizajes extraidos de la operacion

En la fase actual, `knowledge_document` ya esta sembrada con los documentos
fundacionales que dieron origen al schema y al plan inicial.

### 5. Alertas e IA

Tablas clave:

- `reminder`
- `alert_rule`
- `alert_event`
- `notification_preference`
- `ai_suggestion`
- `ai_insight`
- `ai_action_proposal`
- `ai_context_log`
- `automation_rule`
- `automation_run`

Este dominio existe para que la IA pueda ir mas alla de consultar datos.

Fue pensado para:

- detectar condiciones relevantes
- proponer acciones
- registrar insights
- guardar trazabilidad del contexto usado por la IA
- ejecutar automatizaciones controladas

Hoy no es el centro del uso real, pero si es parte del modelo futuro.

### 6. Integraciones y gobierno

Tablas clave:

- `external_system`
- `external_entity_link`
- `sync_event`
- `integration_note`
- `audit_log`
- `status_history`
- `lookup_catalog`
- `lookup_value`

Este dominio resuelve cuatro problemas:

- con que sistemas externos se conecta la empresa
- como se mapea una entidad interna con una externa
- como se auditan cambios
- como se registra historial de estados

En una base que pretende servir como cerebro operativo, esta capa es obligatoria.

## Tablas que conviene entender primero

Si vas a aprender esta base con alguien o con un modelo de voz, no empieces por
las 57 tablas. Empieza por estas.

### `organization`

Es la raiz conceptual del negocio. Define la entidad principal: Vive la Vibe.

Muchos dominios cuelgan directa o indirectamente de aqui.

### `business_area`

Es una de las tablas mas importantes para entender el reparto del trabajo. Define
las grandes areas funcionales del negocio.

Hoy estan sembradas 7 areas:

- Direccion
- Operacion
- Tecnologia
- Finanzas
- Marketing
- Captacion
- Experiencia huesped

### `business_line`

Baja la estructura del negocio a unidades de trabajo o monetizacion concretas.

Hoy estan sembradas 8 lineas:

- Administracion de hospedajes
- Automatizacion de hospedajes
- Tours y taxis
- Entregas
- Actividades Vive la Vibe
- Plataforma de comida
- Reacondicionamiento y decoracion
- Marketing y captacion

### `business_priority`

Representa el foco principal de la fase actual.

No es lo mismo que una tarea o proyecto. Es una prioridad de negocio.

### `objective_record`

Representa el resultado estrategico que se quiere perseguir.

Piensalo asi:

- prioridad = donde se esta poniendo foco hoy
- objetivo = que resultado se quiere construir o alcanzar

### `knowledge_document`

Guarda los documentos que alimentan la memoria inicial del sistema.

Hoy esta sembrada con los tres documentos base del proyecto.

### `external_system`

Por ahora se usa para registrar sistemas externos relevantes, como el PMS actual.

Es importante porque deja claro que la base reconoce dependencias externas sin
querer reemplazarlas.

## Relaciones que importan de verdad

### `organization` como raiz

Aunque no todas las tablas tengan `organization_id` directo, conceptualmente la
organizacion es la raiz del modelo.

En terminos de negocio:

- una organizacion tiene muchas areas
- una organizacion tiene muchas lineas de negocio
- una organizacion tiene muchas prioridades
- una organizacion tiene muchos objetivos
- una organizacion puede tener muchos proyectos, reuniones y documentos

En el assistant actual esto se simplifica todavia mas:

- el panel opera en modo single-organization
- esa organizacion es Vive la Vibe
- el usuario no debe proporcionar `organizationId`

### `business_area` como eje intermedio

`business_area` conecta estructura y ejecucion.

Sirve para agrupar y contextualizar:

- lineas de negocio
- objetivos
- categorias de proyecto
- algunos documentos y piezas de conocimiento

Si alguien pregunta por "reparto de trabajo", casi siempre conviene empezar aqui.

### `business_line` como unidad de enfoque

`business_line` representa frentes concretos del negocio.

Es un nivel mas operativo que `business_area`, pero aun no llega al nivel de
proyecto o tarea.

### Prioridades y objetivos

`business_priority` y `objective_record` se complementan:

- `business_priority` responde: en que esta enfocada la empresa ahora
- `objective_record` responde: que resultado importante quiere alcanzar

### Proyectos y tareas

La ruta de crecimiento del dominio de ejecucion es esta:

- una organizacion define iniciativas
- las iniciativas agrupan proyectos
- los proyectos pueden tener subproyectos
- los proyectos y subproyectos se descomponen en tareas
- las tareas generan updates, blockers y dependencias

Eso permite que el brain no sea solo memoria, sino seguimiento operativo.

### Reuniones, decisiones y follow-up

Este dominio no esta para guardar minutas sueltas. Esta para capturar causalidad:

- que reunion ocurrio
- quienes participaron
- que se dijo
- que se decidio
- que pendiente se genero
- con que entidad se relaciona esa decision

Eso permite reconstruir por que paso algo, no solo ver que paso.

## Como se opera esta base hoy

La IA no genera SQL libre contra esta base.

El flujo actual es:

1. el backend Node recibe una solicitud
2. elige una accion permitida del catalogo
3. esa accion llama uno o varios stored procedures autorizados
4. el backend devuelve resultados estructurados
5. la IA responde con base en esos resultados y en los documentos cargados

### Implicacion practica

Para entender como se usa la base en runtime no basta con leer el schema. Tambien
hay que leer la capa de SP y la documentacion operativa del assistant.

## Stored procedures mas importantes para empezar

Si hoy quieres inspeccionar la parte realmente viva del Business Brain, estos SP
son los mas utiles:

- `sp_organization_data`
- `sp_business_area_data`
- `sp_business_line_data`
- `sp_business_priority_data`
- `sp_objective_record_data`
- `sp_external_system_data`
- `sp_knowledge_document_data`

Y la accion compuesta mas importante del backend es:

- `brain.current_context`

Esa accion consolida el panorama actual del dominio activo.

## Como estudiar esta base sin abrumarte

Usa este orden:

1. lee este walkthrough completo
2. revisa el `README` de `BD vive la vibe brain`
3. revisa `seed_mapping.md` para entender que datos reales existen hoy
4. abre el schema SQL y estudia primero:
   - `organization`
   - `business_area`
   - `business_line`
   - `business_priority`
   - `objective_record`
   - `knowledge_document`
   - `external_system`
5. despues revisa la referencia de SP
6. al final recorre los dominios aun no poblados para entender hacia donde puede
   crecer la base

## Paquete recomendado para ChatGPT voz

Si quieres una conversacion de voz fluida, no empieces con el SQL crudo. Usa este
paquete y este orden.

### 1. Vision general y aprendizaje humano

- `C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\docs\BUSINESS_BRAIN_SCHEMA_WALKTHROUGH.md`

### 2. Contexto operativo y estructura del proyecto

- `C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\README.md`

### 3. Que datos reales existen hoy

- `C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\docs\seed_mapping.md`

### 4. Como se opera la base por stored procedures

- `C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\docs\BUSINESS_BRAIN_SQL_SP_REFERENCE.md`

### 5. Estructura SQL completa

- `C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\schema\001_business_brain_schema.sql`

### 6. Contexto del assistant que hoy consume esta base

- `C:\Users\ragnarok\Documents\repos\Proyecto VLV\AI Assistant platform\docs\domains\business_brain\stored_procedures.md`
- `C:\Users\ragnarok\Documents\repos\Proyecto VLV\AI Assistant platform\docs\domains\business_brain\company_context.md`

## Preguntas utiles para estudiar esta base por voz

Estas preguntas suelen dar conversaciones mucho mejores que pedir "explicame todo".

- explicame esta base como si fueras mi arquitecto de datos
- ayudame a entender primero el dominio organizacional
- explicame la diferencia entre `business_area`, `business_line`,
  `business_priority` y `objective_record`
- explicame como creceria esta base cuando empecemos a usar proyectos y tareas
- explicame como se conecta esta base con el backend del assistant
- ayudame a leer las relaciones importantes sin meterme aun en todas las tablas

## Prompt recomendado para una conversacion de voz

```text
Quiero que me expliques esta base de datos en espanol, como arquitecto de datos y de producto. No me expliques todo de golpe. Guiame por capas: 1) proposito general, 2) dominios, 3) tablas clave, 4) relaciones importantes, 5) como la usa el assistant, 6) que esta activo hoy y que esta reservado para despues. Quiero una conversacion fluida y pedagogica. Haz pausas cortas y verifica que entendi antes de seguir.
```

## Conclusiones practicas

La forma correcta de pensar `BD vive la vibe brain` hoy es esta:

- no es una base transaccional de hospitalidad
- no reemplaza al PMS
- no intenta capturar toda la empresa desde el dia uno
- si captura la estructura minima para que la IA entienda el negocio
- ya tiene reservado el camino para crecer hacia ejecucion, seguimiento,
  conocimiento, automatizacion e integraciones

Si alguien solo recuerda una idea, deberia ser esta:

- `BD vive la vibe brain` es la memoria estructurada del negocio que permite que la IA
  deje de responder sin contexto y empiece a operar con continuidad.
