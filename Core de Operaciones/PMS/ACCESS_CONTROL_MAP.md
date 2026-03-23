# ACCESS_CONTROL_MAP

## 1) Modelo RBAC
- Tipo: RBAC con alcance por propiedad.
- Niveles de rol: global (`role.id_property IS NULL`) y por propiedad.
- Bypass total: `app_user.is_owner = 1`.
- Modo de enforcement por empresa: `pms_authz_config.mode` (`audit` | `enforce`).

## 2) Fuentes de verdad
- Usuarios: `app_user`
- Roles: `role`
- Permisos: `permission`
- Relación rol-permiso: `role_permission`
- Relación usuario-rol: `user_role`
- Relación usuario-propiedad: `user_property`
- Auditoría authz: `pms_authz_audit`

## 3) Permisos (convención)
- Convención: `<modulo>.<accion>`
- Vistas: `*.view` (ej. `calendar.view`, `reservations.view`, `settings.view`, `users.view`).
- Acciones clave:
  - Calendario: `calendar.create_hold`, `calendar.move_reservation`, `calendar.manage_block`, `calendar.register_payment`
  - Reservas: `reservations.create`, `reservations.edit`, `reservations.status_change`, `reservations.manage_folio`, `reservations.post_charge`, `reservations.post_payment`, `reservations.refund`, `reservations.note_edit`, `reservations.move_property`
  - Settings: `settings.edit`
  - Usuarios: `users.create`, `users.edit`, `users.assign_roles`, `users.assign_properties`, `users.manage_roles`
  - Rateplans: `rateplans.view`, `rateplans.edit`
  - Ingresos: `incomes.view`, `incomes.reconcile`
  - iCal: `ota_ical.view`, `ota_ical.sync`

## 4) Helpers PHP (enforcement)
Archivo: `public_html/includes/db.php`
- `pms_access_context($forceRefresh=false)`
- `pms_user_can($permissionCode, $propertyCode=null)`
- `pms_require_permission($permissionCode, $propertyCode=null, $asJson=false)`
- `pms_allowed_property_codes()`
- `pms_require_property_access($propertyCode, $asJson=false)`
- `pms_resolve_allowed_property_or_fail($postedPropertyCode)`
- `pms_module_view_permission($viewKey)`

## 5) Carga de contexto en sesión
- `$_SESSION['pms_user']`: incluye `is_owner`.
- `$_SESSION['pms_access']`: snapshot de permisos efectivos + propiedades permitidas + modo (`audit|enforce`).
- Refresh de contexto al autenticar / force login.

## 6) Doble protección
- Frontend/UI:
  - Menús y botones ocultos según permiso.
  - Módulo sin permiso => pantalla de acceso denegado.
- Backend:
  - Guards por módulo/API con `pms_require_permission`.
  - Guards por propiedad con `pms_require_property_access`.
  - SPs críticos llaman `sp_authz_assert`.

## 7) SPs de acceso
Carpeta: `../bd/`
- `sp_access_seed_defaults.sql`
- `sp_access_context_data.sql`
- `sp_authz_assert.sql`
- `sp_user_role_sync.sql`
- `sp_user_property_sync.sql`
- `sp_role_upsert.sql`
- `sp_role_permission_sync.sql`
- `rbac_tables.sql` (soporte de tablas/config/audit)

## 8) SPs críticos blindados (primera ola)
- `sp_create_reservation.sql`
- `sp_create_reservation_hold.sql`
- `sp_reservation_confirm_hold.sql`
- `sp_reservation_update.sql`
- `sp_reservation_update_v2.sql`
- `sp_reservation_add_folio.sql`
- `sp_folio_upsert.sql`
- `sp_sale_item_upsert.sql`
- `sp_line_item_percent_derived_upsert.sql`
- `sp_line_item_payment_meta_upsert.sql`
- `sp_refund_upsert.sql`
- `sp_create_room_block.sql`
- `sp_update_room_block.sql`
- `sp_app_user_upsert.sql`

## 9) Roles seed (core)
- Owner/Admin
- Operaciones
- Recepcion
- Finanzas
- Solo Lectura

## 10) Reglas de alcance por propiedad
- Owner: acceso a todas las propiedades de la empresa.
- No-owner: solo propiedades en `user_property`.
- Cualquier request con `property_code`: se valida contra whitelist.
- Usuario sin propiedades: acceso denegado en navegación principal.

## 11) Modo Audit vs Enforce
- `audit`: registra denegaciones en `pms_authz_audit`, no bloquea en SP.
- `enforce`: bloquea (`SQLSTATE 45000`, `AUTHZ_DENIED`).

## 12) Puntos ya aplicados (código)
- Routing/navigation: `index.php`, `includes/layout.php`.
- Módulos con guardas fuertes: `calendar.php`, `reservations.php`, `app_users.php`, `reservation_wizard.php`, `settings.php`, `incomes.php`.
- APIs con guardas: `api/guest_search.php`, `api/ota_ical_sync.php`, `api/rateplan_modifiers.php`, `api/reservation_interest.php`.

## 13) Recomendación de operación
1. Aplicar SQL de RBAC y seeds en staging.
2. Ejecutar en `audit` y revisar `pms_authz_audit`.
3. Ajustar asignaciones de roles/propiedades.
4. Cambiar a `enforce`.
