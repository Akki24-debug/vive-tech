# Canonical Sources

## Regla general

Cada tema debe tener una sola fuente canonica. Las copias derivadas pueden existir solo si su sincronizacion esta definida y documentada.

## Fuentes canonicas por dominio

| Dominio | Ruta canonica | Notas |
|---|---|---|
| PMS PHP | `PMS/public_html/` | Interfaz, includes, modulos y servicios del PMS |
| SQL y stored procedures | `PMS/bd pms/` | Fuente principal de procedimientos, dumps y consultas auxiliares |
| Migraciones PMS | `PMS/migrations/` | Cambios incrementales de BD |
| Documentacion operativa del PMS | `PMS/*.md` | Mapas, playbooks, RBAC, filtros y setup local |
| Plataforma IA | `IA/ai-assistant-platform/` | Backend Node, admin UI y docs de runtime |
| GPTs e instrucciones de IA | `IA/GPTS/` | Prompt packs y configuraciones del GPT |
| Contexto documental de IA | `IA/docs/` | Debe derivarse de cambios del PMS cuando aplique |
| Tools locales | `tools/` | Scripts Windows, binarios portables y herramientas internas |
| Sitio publico | `vivelavibe.pxm.com.mx/` | Frontend y endpoints del dominio publico |

## Copias derivadas o heredadas

| Ruta | Estado | Accion recomendada |
|---|---|---|
| `PMS/ai-assistant-platform/` | Duplicado heredado | Revisar contra `IA/ai-assistant-platform/` y retirar una vez validado |
| `IA/docs/pms-context/` | Copia derivada del PMS | Mantener sincronizada o automatizar su generacion |
| `PMS/Backups/` | Backup local | Mantener fuera de Git |
| `tools/windows/mariadb-backups/` | Artefacto local | Mantener fuera de Git |

## Regla para mover o borrar

Antes de borrar una copia:

1. identificar la ruta canonica;
2. mover solo lo faltante si la copia aun tiene material util;
3. respaldar la copia fuera del repo si hay duda;
4. documentar la decision en `WORKSPACE_CONTROL_TOWER.md`.
