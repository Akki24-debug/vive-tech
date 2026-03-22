# Seed Mapping

## Organización

| Documento fuente | Fragmento usado | Tabla | Registro |
| --- | --- | --- | --- |
| `Vive_la_Vibe_Plan_Infraestructura_IA_v1.md` | "infraestructura de inteligencia artificial propuesta para Vive la Vibe" | `organization` | `Vive la Vibe` |
| `Vive_la_Vibe_Plan_Infraestructura_IA_v1.md` | "cerebro operativo y estratégico del negocio" | `organization.vision_summary` | visión principal |

## Áreas funcionales

Fuente: `Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md`, sección `6.2 Tabla: business_area`.

| Tabla | Registros |
| --- | --- |
| `business_area` | Dirección, Operación, Tecnología, Finanzas, Marketing, Captación, Experiencia huésped |

## Líneas de negocio

Fuente: `Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md`, sección `6.3 Tabla: business_line`.

| Tabla | Registros |
| --- | --- |
| `business_line` | Administración de hospedajes, Automatización de hospedajes, Tours y taxis, Entregas, Actividades Vive la Vibe, Plataforma de comida, Reacondicionamiento y decoración, Marketing y captación |

## Prioridad y objetivo estratégico

| Documento fuente | Fragmento usado | Tabla | Registro |
| --- | --- | --- | --- |
| `Vive_la_Vibe_Plan_Infraestructura_IA_v1.md` | "Fortalecer la planeación del equipo y la estructura interna del negocio." | `business_priority` | prioridad principal de fase temprana |
| `Vive_la_Vibe_Plan_Infraestructura_IA_v1.md` | "Que la IA se convierta en un asistente integral de negocio" | `objective_record` | objetivo estratégico central |
| `Vive_la_Vibe_Plan_Infraestructura_IA_v1.md` | "cerebro operativo y estratégico del negocio" | `objective_record.description` | visión objetivo |

## Sistema externo

| Documento fuente | Fragmento usado | Tabla | Registro |
| --- | --- | --- | --- |
| `Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md` | "No incluye como fuente primaria reservas del PMS... Sí puede incluir referencias al PMS" | `external_system` | PMS actual |

## Documentos de conocimiento

| Archivo | Tabla | Título | Version | Procedencia |
| --- | --- | --- | --- | --- |
| `Vive_la_Vibe_Plan_Infraestructura_IA_v1.md` | `knowledge_document` | `Plan de infraestructura de inteligencia artificial — Vive la Vibe` | `v1` | ruta local del archivo |
| `Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md` | `knowledge_document` | `Vive la Vibe — Esqueleto Sugerido de Base de Datos de Negocio` | `v1` | ruta local del archivo |
| `Vive_la_Vibe_Business_Brain_Schema_v1.sql` | `knowledge_document` | `Vive la Vibe - Business Brain Schema v1` | `v1` | ruta local del archivo |

## Fuera de alcance en esta semilla

- `user_account`
- `project`
- `subproject`
- `task`
- `meeting`
- `decision_record`
- `follow_up_item`
- `alert_*`
- `ai_*`
- `reminder`
- cualquier dato operativo del PMS como fuente primaria
