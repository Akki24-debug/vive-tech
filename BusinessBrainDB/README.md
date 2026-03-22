# BusinessBrainDB

Base versionada de la BD cerebro operativo de Vive la Vibe para la fase temprana del proyecto.

## Estructura

- `schema/001_business_brain_schema.sql`: schema completo versionado.
- `seed/001_seed_explicit_context.sql`: carga inicial idempotente basada solo en datos explicitos.
- `docs/seed_mapping.md`: mapeo documento -> tabla -> registro.
- `stored-procedures/`: capa versionada de SP para CRUD seguro y flujos compuestos.
- `docs/BUSINESS_BRAIN_SQL_SP_REFERENCE.md`: referencia central para IA, backend y soporte.
- `tools/generate_business_brain_sps.py`: generador de SQL y documentacion de SP.

## Fuente autorizada para la semilla inicial

- `C:/Users/ragnarok/Downloads/Vive_la_Vibe_Plan_Infraestructura_IA_v1.md`
- `C:/Users/ragnarok/Downloads/Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md`
- `C:/Users/ragnarok/Downloads/Vive_la_Vibe_Business_Brain_Schema_v1.sql`

## Instancia local recomendada

Se dejo una instancia MariaDB local dedicada a `BusinessBrainDB`, separada del servicio principal.

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
cd 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\BusinessBrainDB'
.\scripts\start-local-business-brain-db.ps1
.\scripts\check-local-business-brain-db.ps1
```

## Importacion base

Schema:

```powershell
& 'C:\Program Files\MariaDB 12.2\bin\mariadb.exe' --skip-ssl --host=127.0.0.1 --port=3307 --user=root --skip-password < 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\BusinessBrainDB\schema\001_business_brain_schema.sql'
```

Seed inicial:

```powershell
& 'C:\Program Files\MariaDB 12.2\bin\mariadb.exe' --skip-ssl --host=127.0.0.1 --port=3307 --user=root --skip-password vive_la_vibe_brain < 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\BusinessBrainDB\seed\001_seed_explicit_context.sql'
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
cd 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\BusinessBrainDB'
.\scripts\install-business-brain-sps.ps1
```

Validacion minima:

```powershell
cd 'C:\Users\ragnarok\Documents\repos\Proyecto VLV\BusinessBrainDB'
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
