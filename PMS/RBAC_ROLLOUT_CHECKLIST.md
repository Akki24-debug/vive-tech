# RBAC_ROLLOUT_CHECKLIST

## Pre-deploy
- [ ] Respaldo creado en `Backups/<timestamp>_rbac_*`.
- [ ] SQL nuevos listos:
  - [ ] `rbac_tables.sql`
  - [ ] `sp_access_seed_defaults.sql`
  - [ ] `sp_access_context_data.sql`
  - [ ] `sp_authz_assert.sql`
  - [ ] `sp_user_role_sync.sql`
  - [ ] `sp_user_property_sync.sql`
  - [ ] `sp_role_upsert.sql`
  - [ ] `sp_role_permission_sync.sql`
- [ ] SPs críticos recompilados.
- [ ] Config inicial `pms_authz_config.mode = audit`.

## Deploy (Audit)
- [ ] Publicar cambios PHP (`includes/db.php`, `index.php`, `layout.php`, módulos/APIs).
- [ ] Validar login y carga de sesión con `is_owner` + contexto RBAC.
- [ ] Validar navegación: módulos ocultos según permiso.
- [ ] Ejecutar smoke tests por rol (Owner/Operaciones/Recepcion/Finanzas/Solo Lectura).

## Validaciones funcionales
- [ ] Usuario sin propiedad => acceso denegado.
- [ ] Usuario sin permiso de módulo => no lo ve + URL directa denegada.
- [ ] Usuario sin permiso de acción => botón oculto + POST/API denegado.
- [ ] Usuario sin acceso a propiedad => operación denegada.
- [ ] SP crítico sin permiso => audit log (audit mode).

## Revisión de auditoría
- [ ] Revisar tabla `pms_authz_audit` por 24-72h.
- [ ] Ajustar mapeos de rol/permisos y asignaciones `user_property`.
- [ ] Confirmar que no hay falsos positivos críticos.

## Switch a Enforce
- [ ] Cambiar `pms_authz_config.mode` a `enforce` por empresa.
- [ ] Repetir smoke tests por rol.
- [ ] Validar que denegaciones ahora bloquean (`SQLSTATE 45000` / 403 JSON).

## Post-deploy
- [ ] Documentar incidencias y ajustes.
- [ ] Mantener script de rollback SQL/PHP.
- [ ] Confirmar que nuevos módulos/features siguen patrón RBAC.

## Rollback rápido
- [ ] Volver a `audit` (si aplica).
- [ ] Restaurar PHP desde backup de fase.
- [ ] Restaurar SPs/tablas según script de rollback.
