# Workspace Control Tower

## Resumen ejecutivo

Este repo es ahora la raiz unica de trabajo para Vive La Vibe. La meta es evitar confusion entre repos hermanos, copias historicas y carpetas locales de soporte.

## Tabla de control

| Area | Ruta canonica | Que vive ahi | Como se usa hoy | Riesgo actual | Siguiente paso |
|---|---|---|---|---|---|
| PMS | `PMS/` | PHP, SQL, migraciones, mapas, RBAC, scripts locales | Desarrollo y pruebas locales del PMS con MariaDB 11.8.3 | Hay contenido historico mezclado y al menos un duplicado viejo de `ai-assistant-platform` dentro de `PMS/` | Depurar duplicados internos y dejar una sola ruta para IA |
| IA | `IA/` | `ai-assistant-platform/`, `docs/`, `GPTS/` | Backend Node, admin UI, prompts y contexto documental | Hay contenido que tambien existe en `PMS/ai-assistant-platform` | Confirmar canon definitivo y eliminar la copia heredada |
| Tools | `tools/` | PHP portable, ngrok, composer, scripts Windows, apps internas | Soporte local, diagnostico, MariaDB, reparaciones Windows | Mezcla de binarios y utilidades locales; ahora ya consolidado | Mantener solo herramientas reutilizables y dejar fuera artefactos temporales |
| Website | `vivelavibe.pxm.com.mx/` | Sitio publico y endpoints del dominio | Frontend y PHP publico | Contratos con PMS no siempre estan documentados en un solo lugar | Mantener contratos cruzados con PMS e IA |

## Estado local actual

| Recurso | Estado | Ruta / valor |
|---|---|---|
| Repo Git raiz | Activo | `C:\Users\ragnarok\Documents\repos\Proyecto VLV` |
| MariaDB local | Activo | `11.8.3-MariaDB` en `127.0.0.1:3306` |
| Base PMS local | Lista | `vlv_pms_local` |
| PMS local | Arranque listo | `PMS\\run-pms-local.bat` |
| Tools Windows | Consolidados | `tools\\windows\\` |
| HeidiSQL | Instalado localmente | Sesiones locales configuradas fuera del repo |

## Flujo recomendado

1. Definir primero la carpeta canonica del cambio.
2. Trabajar siempre desde una rama del repo raiz `Proyecto VLV`.
3. Probar localmente antes de tocar cualquier despliegue.
4. Si un cambio en `PMS/` afecta `IA/` o `vivelavibe.pxm.com.mx/`, actualizar el contrato cruzado el mismo dia.
5. No recrear repos hermanos fuera de esta raiz.

## Duplicados heredados detectados

- `PMS/ai-assistant-platform/` y `IA/ai-assistant-platform/`
- mapas y playbooks del PMS replicados en `IA/docs/pms-context/`

## Backups de consolidacion

- Respaldo de carpetas movidas:
  - `C:\Users\ragnarok\Documents\repos\_repo_consolidation_backups\20260321-103022`

## Prioridades recomendadas

1. Confirmar si `IA/ai-assistant-platform/` sera la unica ruta canonica del backend de IA.
2. Limpiar duplicados internos entre `PMS/` e `IA/`.
3. Automatizar sincronizacion de contexto PMS -> IA.
4. Agregar checklist de release por area.
