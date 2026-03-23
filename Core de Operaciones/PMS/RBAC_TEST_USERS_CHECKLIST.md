# RBAC Test Users Checklist

## 1) Preparation
1. Run SQL file: `../bd/rbac_test_users_seed.sql`.
2. In the SQL, set `@company_code` to your test company code before running.
3. Confirm output table at end shows all 6 users with role + property counts.

## 2) Test Users
- Owner/Admin
  - Email: `qa.owner.rbac@local.test`
  - Password: `RBAC2026!owner`
  - Expected: full access, all modules, all actions.

- Operaciones
  - Email: `qa.ops.rbac@local.test`
  - Password: `RBAC2026!ops`
  - Expected: daily operation modules and actions, but no user admin and no settings edit.

- Recepcion (all properties)
  - Email: `qa.frontdesk.rbac@local.test`
  - Password: `RBAC2026!frontdesk`
  - Expected: reservations + guests + messages + payments actions; restricted admin modules.

- Finanzas
  - Email: `qa.finance.rbac@local.test`
  - Password: `RBAC2026!finance`
  - Expected: payments/incomes/obligations/reports run; no reservation editing flows.

- Solo Lectura (single property)
  - Email: `qa.readonly.rbac@local.test`
  - Password: `RBAC2026!readonly`
  - Expected: view-only modules + reports run; no mutating actions.

- Recepcion scoped (single property)
  - Email: `qa.frontdesk.scope.rbac@local.test`
  - Password: `RBAC2026!scope`
  - Expected: same action set as Recepcion, but only one property visible.

## 3) High-level Tests By Role

### Owner/Admin
1. Open all modules from sidebar.
2. Create/edit in: Properties, Rooms, Categories, Rateplans, Sale Items.
3. Manage users and roles.
4. Expected: no permission denials.

### Operaciones
1. Open Calendar/Reservations and create or move reservation.
2. Try Settings edit action.
3. Try Users module.
4. Expected: operations work, settings/users denied.

### Recepcion (all properties)
1. Create reservation from calendar and from wizard.
2. Edit guest and send message.
3. Register payment from calendar.
4. Try opening Properties or Settings edit.
5. Expected: frontdesk actions work; admin modules/actions denied.

### Finanzas
1. Open Payments, Incomes, Obligations, Reports.
2. Apply obligation payment (`apply_add` or `apply_full`).
3. Run reports.
4. Try editing reservation in wizard.
5. Expected: finance actions work; reservation editing denied.

### Solo Lectura
1. Open modules and verify data can be viewed.
2. Try any save/create action (example: save guest, save room, save template).
3. Expected: view works, all writes denied.

### Recepcion scoped (single property)
1. Go to Reservations and Guests grids.
2. Verify only one property appears in filters/options.
3. Try POST action with another property code (devtools/manual form change).
4. Expected: blocked by backend property access guard.

## 4) Quick Negative Matrix (must fail)
- `qa.readonly...`: any `save_*`, `create_*`, `delete_*` action.
- `qa.finance...`: reservation wizard create/edit/status change.
- `qa.frontdesk...`: role management and settings edit.
- `qa.ops...`: user create/edit/assign roles.

## 5) Rollback (optional)
If you want to remove test users only:
```sql
UPDATE app_user
SET is_active = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE email IN (
  'qa.owner.rbac@local.test',
  'qa.ops.rbac@local.test',
  'qa.frontdesk.rbac@local.test',
  'qa.finance.rbac@local.test',
  'qa.readonly.rbac@local.test',
  'qa.frontdesk.scope.rbac@local.test'
);
```
