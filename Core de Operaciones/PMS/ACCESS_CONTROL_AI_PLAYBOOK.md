# ACCESS_CONTROL_AI_PLAYBOOK

## Objetivo
Guía rápida para IA/engineers: dónde tocar cuando cambias permisos, alcance por propiedad o autorización backend.

## Router rápido (qué archivo tocar)
- Cambiar permisos de vista por módulo:
  - `public_html/includes/db.php` -> `pms_module_view_permission()`
  - `public_html/includes/layout.php` -> ocultar/mostrar navegación
  - `public_html/index.php` -> guard de carga de módulo
- Cambiar permiso de una acción en módulo:
  - módulo correspondiente en `public_html/modules/*.php`
  - usar `pms_require_permission('<permiso>')`
- Cambiar scope por propiedad:
  - módulo/API correspondiente con `pms_require_property_access($propertyCode)`
  - helpers comunes en `public_html/includes/db.php`
- Cambiar enforcement en SP:
- SP objetivo en `../bd/*.sql`
  - agregar/ajustar `CALL sp_authz_assert(...)`

## Patrón obligatorio en módulo (write action)
1. Resolver acción (`$_POST` / `$_REQUEST`).
2. Guard de permiso (`pms_require_permission(... )`).
3. Si hay propiedad: guard de propiedad (`pms_require_property_access(... )`).
4. Ejecutar operación/SP.

## Patrón obligatorio en API JSON
1. Validar sesión/token.
2. `pms_require_permission(..., null, true)`.
3. `pms_require_property_access(..., true)` si aplica.
4. Responder JSON consistente con `403` cuando no autorizado.

## Patrón obligatorio en SP crítico
1. Resolver `company_code` + `actor_user_id` + `property_code` objetivo.
2. `CALL sp_authz_assert(company_code, actor_user_id, permission_code, property_code, mode)`.
3. Continuar lógica de negocio.

## Lista corta de permisos por dominio
- Calendar: `calendar.*`
- Reservations: `reservations.*`
- Guests: `guests.*`
- Rateplans: `rateplans.*`
- Payments/Incomes/Obligations: `payments.*`, `incomes.*`, `obligations.*`
- Settings/Users: `settings.*`, `users.*`

## Reglas de negocio de acceso
- `is_owner=1` siempre bypass.
- No-owner siempre restringido por `user_property`.
- Nunca asumir propiedad por compañía completa en writes.

## Checklist mínimo para cualquier cambio de seguridad
1. UI oculta acción sin permiso.
2. Backend bloquea acción sin permiso.
3. Backend bloquea propiedad fuera de alcance.
4. SP crítico bloquea (enforce) o audita (audit).
5. Mensaje de error claro y consistente.

## Anti-patrones prohibidos
- Validar permiso solo en frontend.
- Usar `id_company` como único filtro en writes multi-propiedad.
- Saltar `sp_authz_assert` en SPs críticos.
- Responder HTML en endpoints JSON al negar acceso.
