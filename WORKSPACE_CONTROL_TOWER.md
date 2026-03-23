# Workspace Control Tower

## Resumen ejecutivo

Este repo es ahora la raiz unica de trabajo para Vive La Vibe. La meta es evitar confusion entre repos hermanos, copias historicas y carpetas locales de soporte.

## Tabla de control

| Area | Ruta canonica | Que vive ahi | Como se usa hoy | Riesgo actual | Siguiente paso |
|---|---|---|---|---|---|
| Core de Operaciones | `Core de Operaciones/` | PMS, base operativa del PMS y pagina publica de Vive La Vibe | Desarrollo y pruebas operativas del core del negocio | La base SQL del PMS vive fuera de `PMS/`, asi que la documentacion debe recordar esa separacion | Mantener sincronizados `PMS/`, `bd/` y `pagina de vive la vibe/` |
| AI Assistant Platform | `AI Assistant platform/` | Backend Node, admin UI y docs de runtime | Backend de IA multi-dominio para PMS y cerebro empresarial | La ruta cambio y algunos docs viejos podrian seguir mencionando `IA/` | Mantener una sola ruta para la plataforma y limpiar referencias viejas |
| Cerebro de la Empresa | `cerebro de la empresa/` | BD del cerebro y documentos anexos | Modelado del negocio, SPs y memoria estructurada | Puede mezclarse documentacion historica con la operativa | Mantener `BD vive la vibe brain/` como fuente canonica y `otros documentos/` como apoyo |
| Tools | `tools/` | PHP portable, ngrok, composer, scripts Windows, apps internas | Soporte local, diagnostico y utilidades compartidas | Mezcla natural de binarios y utilidades | Mantener solo herramientas reutilizables |
| Vive la Food | `vive la food/` | Venta de comida, entregas y partners | Espacio reservado para crecimiento | Aun no tiene estructura operativa interna | Crear README y poblar por lineas |
| Marketing | `marketing/` | Activos y documentos de marketing | Espacio reservado para crecimiento | Aun no tiene estructura interna | Crear README y mover materiales cuando existan |

## Estado local actual

| Recurso | Estado | Ruta / valor |
|---|---|---|
| Repo Git raiz | Activo | `C:\Users\ragnarok\Documents\repos\Proyecto VLV` |
| MariaDB local | Activo | `11.8.3-MariaDB` en `127.0.0.1:3306` |
| Base PMS local | Lista | `vlv_pms_local` |
| PMS local | Arranque listo | `Core de Operaciones\\PMS\\run local\\run-pms-local.bat` |
| Tools Windows | Consolidados | `tools\\` |
| HeidiSQL | Instalado localmente | Sesiones locales configuradas fuera del repo |

## Flujo recomendado

1. Definir primero la carpeta canonica del cambio.
2. Trabajar siempre desde una rama del repo raiz `Proyecto VLV`.
3. Probar localmente antes de tocar cualquier despliegue.
4. Si un cambio en `Core de Operaciones/` afecta `AI Assistant platform/` o `cerebro de la empresa/`, actualizar el contrato cruzado el mismo dia.
5. No recrear repos hermanos fuera de esta raiz.

## Backups de consolidacion

- Respaldo de carpetas movidas:
  - `C:\Users\ragnarok\Documents\repos\_repo_consolidation_backups\20260321-103022`

## Prioridades recomendadas

1. Mantener actualizadas las rutas nuevas en todos los `.md`.
2. Mantener sincronizacion entre `Core de Operaciones/bd/`, `Core de Operaciones/PMS/` y `AI Assistant platform/docs/domains/pms/`.
3. Mantener sincronizacion entre `cerebro de la empresa/BD vive la vibe brain/` y `AI Assistant platform/docs/domains/business_brain/`.
4. Agregar checklist de release por area.
