# Canonical Sources

## Regla general

Cada tema debe tener una sola fuente canonica. Las copias derivadas pueden existir solo si su sincronizacion esta definida y documentada.

## Fuentes canonicas por dominio

| Dominio | Ruta canonica | Notas |
|---|---|---|
| PMS PHP | `Core de Operaciones/PMS/public_html/` | Interfaz, includes, modulos y servicios del PMS |
| SQL y stored procedures del PMS | `Core de Operaciones/bd/` | Fuente principal de procedimientos, dumps y consultas auxiliares |
| Migraciones PMS | `Core de Operaciones/PMS/migrations/` | Cambios incrementales de BD |
| Documentacion operativa del PMS | `Core de Operaciones/PMS/*.md` | Mapas, playbooks, RBAC, filtros y setup local |
| Plataforma IA | `AI Assistant platform/` | Backend Node, admin UI y docs de runtime |
| Sitio publico Vive La Vibe | `Core de Operaciones/pagina de vive la vibe/` | Frontend y endpoints del dominio publico |
| Cerebro empresarial | `cerebro de la empresa/BD vive la vibe brain/` | Base versionada y documentacion del cerebro de la empresa |
| Documentos historicos o anexos del cerebro | `cerebro de la empresa/otros documentos/` | Material de apoyo, historico o contexto no operativo |
| Tools locales | `tools/` | Scripts Windows, binarios portables y herramientas internas |
| Iniciativas Vive la Food | `vive la food/` | Carpetas reservadas por linea operativa |
| Marketing | `marketing/` | Materiales y activos de marketing |

## Copias derivadas o heredadas

| Ruta | Estado | Accion recomendada |
|---|---|---|
| `cerebro de la empresa/otros documentos/README_IA_historico.md` | Referencia historica | Conservar solo como contexto, no como ruta activa |
| `Core de Operaciones/PMS/Backups/` | Backup local | Mantener fuera de Git o sacar del arbol activo si crece demasiado |
| `AI Assistant platform/storage/runtime/` | Runtime local | Mantener como estado local, no como fuente documental canonica |

## Regla para mover o borrar

Antes de borrar una copia:

1. identificar la ruta canonica;
2. mover solo lo faltante si la copia aun tiene material util;
3. respaldar la copia fuera del repo si hay duda;
4. documentar la decision en `WORKSPACE_CONTROL_TOWER.md`.
