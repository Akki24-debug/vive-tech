# BD vive la vibe brain

Base versionada de la BD cerebro operativo de Vive la Vibe para la fase temprana del proyecto.

## Estructura

- `schema/001_business_brain_schema.sql`: schema completo versionado.
- `seed/001_seed_explicit_context.sql`: carga inicial idempotente basada solo en datos explicitos.
- `docs/seed_mapping.md`: mapeo documento -> tabla -> registro.
- `docs/BUSINESS_BRAIN_SCHEMA_WALKTHROUGH.md`: explicacion humana del schema por dominios, relaciones y uso.
- `stored-procedures/`: capa versionada de SP para CRUD seguro y flujos compuestos.
- `docs/BUSINESS_BRAIN_SQL_SP_REFERENCE.md`: referencia central para IA, backend y soporte.
- `tools/generate_business_brain_sps.py`: generador de SQL y documentacion de SP.

## Fuente autorizada para la semilla inicial

- `C:/Users/ragnarok/Downloads/Vive_la_Vibe_Plan_Infraestructura_IA_v1.md`
- `C:/Users/ragnarok/Downloads/Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md`
- `C:/Users/ragnarok/Downloads/Vive_la_Vibe_Business_Brain_Schema_v1.sql`

## Paquete recomendado para entender la base

Si el objetivo es aprender como funciona `BD vive la vibe brain` o darsela a otro
modelo para explicacion guiada, este es el orden recomendado:

1. `docs/BUSINESS_BRAIN_SCHEMA_WALKTHROUGH.md`
2. `README.md`
3. `docs/seed_mapping.md`
4. `docs/BUSINESS_BRAIN_SQL_SP_REFERENCE.md`
5. `schema/001_business_brain_schema.sql`

Regla practica:

- `BUSINESS_BRAIN_SCHEMA_WALKTHROUGH.md` es la mejor entrada para humanos
- `BUSINESS_BRAIN_SQL_SP_REFERENCE.md` es la mejor entrada para IA operativa y backend
- `001_business_brain_schema.sql` es la fuente definitiva de estructura

## Instancia local recomendada

Se dejo una instancia MariaDB local dedicada a `BD vive la vibe brain`, separada del servicio principal.

- host: `127.0.0.1`
- puerto: `3307`
- usuario: `root`
- contrasena: vacia

Scripts:

- `scripts/start-local-business-brain-db.ps1`
- `scripts/check-local-business-brain-db.ps1`
- `scripts/stop-local-business-brain-db.ps1`

Ejemplo:

```powershell
cd 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain'
.\scripts\start-local-business-brain-db.ps1
.\scripts\check-local-business-brain-db.ps1
```

## Importacion base

Schema:

```powershell
& 'C:\Program Files\MariaDB 12.2\bin\mariadb.exe' --skip-ssl --host=127.0.0.1 --port=3307 --user=root --skip-password < 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\schema\001_business_brain_schema.sql'
```

Seed inicial:

```powershell
& 'C:\Program Files\MariaDB 12.2\bin\mariadb.exe' --skip-ssl --host=127.0.0.1 --port=3307 --user=root --skip-password vive_la_vibe_brain < 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\seed\001_seed_explicit_context.sql'
```

## Stored Procedures

Artefactos principales:

- `stored-procedures/000_install_all_business_brain_sps.sql`
- `stored-procedures/pms-style/sp_*.sql`
- `stored-procedures/helpers/001_core_helpers.sql`
- `stored-procedures/domains/010_core_entities.sql`
- `stored-procedures/domains/020_execution.sql`
- `stored-procedures/domains/030_meetings_and_followup.sql`
- `stored-procedures/domains/040_knowledge.sql`
- `stored-procedures/domains/050_alerts_ai.sql`
- `stored-procedures/domains/060_integrations_governance.sql`
- `stored-procedures/domains/090_composite_flows.sql`
- `docs/BUSINESS_BRAIN_SQL_SP_REFERENCE.md`

Instalacion:

```powershell
cd 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain'
.\scripts\install-business-brain-sps.ps1
```

Validacion minima:

```powershell
cd 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain'
.\scripts\validate-business-brain-sps.ps1
```

Si PowerShell bloquea scripts locales por Execution Policy, ejecuta:

```powershell
powershell -ExecutionPolicy Bypass -File '.\scripts\install-business-brain-sps.ps1'
powershell -ExecutionPolicy Bypass -File '.\scripts\validate-business-brain-sps.ps1'
```

Notas:

- La instalacion es idempotente porque cada SP se recrea con `DROP PROCEDURE IF EXISTS`.
- La carpeta `stored-procedures/pms-style` contiene un archivo completo por SP, con formato estilo PMS.
- El documento `docs/BUSINESS_BRAIN_SQL_SP_REFERENCE.md` es la referencia principal para IA y backend.
- El documento `docs/BUSINESS_BRAIN_SCHEMA_WALKTHROUGH.md` es la referencia principal para aprendizaje humano y onboarding.
- El generador `tools/generate_business_brain_sps.py` recompone los SQL y la referencia MD a partir del schema versionado.

## Verificacion minima de datos

```sql
SHOW DATABASES LIKE 'vive_la_vibe_brain';
USE vive_la_vibe_brain;
SHOW TABLES;

SELECT COUNT(*) AS organizations FROM organization;
SELECT COUNT(*) AS business_areas FROM business_area;
SELECT COUNT(*) AS business_lines FROM business_line;
SELECT COUNT(*) AS business_priorities FROM business_priority;
SELECT COUNT(*) AS objectives FROM objective_record;
SELECT COUNT(*) AS external_systems FROM external_system;
SELECT COUNT(*) AS knowledge_documents FROM knowledge_document;
```

Resultado esperado del seed inicial:

- `organization`: 1
- `business_area`: 7
- `business_line`: 8
- `business_priority`: 1
- `objective_record`: 1
- `external_system`: 1
- `knowledge_document`: 3

## Estado actual

La carpeta ya incluye schema, seed, backup schema-only, instalacion local dedicada y la capa de stored procedures versionada y validada sobre la instancia en `3307`.
