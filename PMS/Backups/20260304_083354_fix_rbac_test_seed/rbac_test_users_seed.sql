-- RBAC test users seed
-- Run in target DB after RBAC tables/roles exist.
-- If core roles do not exist yet, run:
--   CALL sp_access_seed_defaults('<COMPANY_CODE>', <ACTOR_USER_ID_OR_NULL>);

SET @company_code = 'VLV';

SELECT c.id_company
INTO @company_id
FROM company c
WHERE c.code = @company_code
  AND c.deleted_at IS NULL
LIMIT 1;

SELECT au.id_user
INTO @actor_user_id
FROM app_user au
WHERE au.id_company = @company_id
  AND au.deleted_at IS NULL
  AND au.is_active = 1
ORDER BY au.is_owner DESC, au.id_user ASC
LIMIT 1;

SELECT p.id_property
INTO @property_primary_id
FROM property p
WHERE p.id_company = @company_id
  AND p.deleted_at IS NULL
  AND p.is_active = 1
ORDER BY p.order_index, p.name, p.id_property
LIMIT 1;

SELECT p.id_property
INTO @property_secondary_id
FROM property p
WHERE p.id_company = @company_id
  AND p.deleted_at IS NULL
  AND p.is_active = 1
ORDER BY p.order_index, p.name, p.id_property
LIMIT 1 OFFSET 1;

SELECT r.id_role INTO @role_owner_id
FROM role r
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND r.name = 'Owner/Admin'
  AND (r.id_property IS NULL OR r.id_property = 0)
ORDER BY r.id_role DESC
LIMIT 1;

SELECT r.id_role INTO @role_ops_id
FROM role r
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND r.name = 'Operaciones'
  AND (r.id_property IS NULL OR r.id_property = 0)
ORDER BY r.id_role DESC
LIMIT 1;

SELECT r.id_role INTO @role_frontdesk_id
FROM role r
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND r.name = 'Recepcion'
  AND (r.id_property IS NULL OR r.id_property = 0)
ORDER BY r.id_role DESC
LIMIT 1;

SELECT r.id_role INTO @role_finance_id
FROM role r
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND r.name = 'Finanzas'
  AND (r.id_property IS NULL OR r.id_property = 0)
ORDER BY r.id_role DESC
LIMIT 1;

SELECT r.id_role INTO @role_readonly_id
FROM role r
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND r.name = 'Solo Lectura'
  AND (r.id_property IS NULL OR r.id_property = 0)
ORDER BY r.id_role DESC
LIMIT 1;

-- 1) Owner/Admin
UPDATE app_user
SET names = 'QA Owner',
    last_name = 'RBAC',
    maiden_name = NULL,
    full_name = 'QA Owner RBAC',
    display_name = 'QA Owner',
    password_hash = 'RBAC2026!owner',
    is_owner = 1,
    locale = 'es-MX',
    timezone = 'America/Mexico_City',
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW()
WHERE id_company = @company_id
  AND email = 'qa.owner.rbac@local.test';

INSERT INTO app_user (
  id_company, email, password_hash, names, last_name, maiden_name,
  full_name, display_name, locale, timezone, is_owner,
  is_active, deleted_at, created_at, updated_at, notes
)
SELECT
  @company_id, 'qa.owner.rbac@local.test', 'RBAC2026!owner',
  'QA Owner', 'RBAC', NULL,
  'QA Owner RBAC', 'QA Owner', 'es-MX', 'America/Mexico_City', 1,
  1, NULL, NOW(), NOW(), 'RBAC test user: Owner/Admin'
FROM dual
WHERE @company_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM app_user au
    WHERE au.id_company = @company_id
      AND au.email = 'qa.owner.rbac@local.test'
  );

SELECT au.id_user INTO @u_owner
FROM app_user au
WHERE au.id_company = @company_id
  AND au.email = 'qa.owner.rbac@local.test'
ORDER BY au.id_user DESC
LIMIT 1;

UPDATE user_role
SET is_active = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_owner
  AND deleted_at IS NULL;

INSERT INTO user_role (id_user, id_role, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT @u_owner, @role_owner_id, 'RBAC QA role bind', 1, NULL, NOW(), @actor_user_id, NOW()
FROM dual
WHERE @u_owner IS NOT NULL
  AND @role_owner_id IS NOT NULL;

UPDATE user_property
SET is_active = 0,
    is_primary = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_owner
  AND deleted_at IS NULL;

INSERT INTO user_property (id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT
  @u_owner,
  p.id_property,
  CASE WHEN p.id_property = @property_primary_id THEN 1 ELSE 0 END,
  'Owner scope',
  'RBAC QA all properties',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM property p
WHERE p.id_company = @company_id
  AND p.deleted_at IS NULL
  AND p.is_active = 1;

-- 2) Operaciones
UPDATE app_user
SET names = 'QA Ops',
    last_name = 'RBAC',
    maiden_name = NULL,
    full_name = 'QA Ops RBAC',
    display_name = 'QA Ops',
    password_hash = 'RBAC2026!ops',
    is_owner = 0,
    locale = 'es-MX',
    timezone = 'America/Mexico_City',
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW()
WHERE id_company = @company_id
  AND email = 'qa.ops.rbac@local.test';

INSERT INTO app_user (
  id_company, email, password_hash, names, last_name, maiden_name,
  full_name, display_name, locale, timezone, is_owner,
  is_active, deleted_at, created_at, updated_at, notes
)
SELECT
  @company_id, 'qa.ops.rbac@local.test', 'RBAC2026!ops',
  'QA Ops', 'RBAC', NULL,
  'QA Ops RBAC', 'QA Ops', 'es-MX', 'America/Mexico_City', 0,
  1, NULL, NOW(), NOW(), 'RBAC test user: Operaciones'
FROM dual
WHERE @company_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM app_user au
    WHERE au.id_company = @company_id
      AND au.email = 'qa.ops.rbac@local.test'
  );

SELECT au.id_user INTO @u_ops
FROM app_user au
WHERE au.id_company = @company_id
  AND au.email = 'qa.ops.rbac@local.test'
ORDER BY au.id_user DESC
LIMIT 1;

UPDATE user_role
SET is_active = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_ops
  AND deleted_at IS NULL;

INSERT INTO user_role (id_user, id_role, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT @u_ops, @role_ops_id, 'RBAC QA role bind', 1, NULL, NOW(), @actor_user_id, NOW()
FROM dual
WHERE @u_ops IS NOT NULL
  AND @role_ops_id IS NOT NULL;

UPDATE user_property
SET is_active = 0,
    is_primary = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_ops
  AND deleted_at IS NULL;

INSERT INTO user_property (id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT
  @u_ops,
  p.id_property,
  CASE WHEN p.id_property = @property_primary_id THEN 1 ELSE 0 END,
  'Ops scope',
  'RBAC QA all properties',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM property p
WHERE p.id_company = @company_id
  AND p.deleted_at IS NULL
  AND p.is_active = 1;

-- 3) Recepcion (all properties)
UPDATE app_user
SET names = 'QA Frontdesk',
    last_name = 'RBAC',
    maiden_name = NULL,
    full_name = 'QA Frontdesk RBAC',
    display_name = 'QA Frontdesk',
    password_hash = 'RBAC2026!frontdesk',
    is_owner = 0,
    locale = 'es-MX',
    timezone = 'America/Mexico_City',
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW()
WHERE id_company = @company_id
  AND email = 'qa.frontdesk.rbac@local.test';

INSERT INTO app_user (
  id_company, email, password_hash, names, last_name, maiden_name,
  full_name, display_name, locale, timezone, is_owner,
  is_active, deleted_at, created_at, updated_at, notes
)
SELECT
  @company_id, 'qa.frontdesk.rbac@local.test', 'RBAC2026!frontdesk',
  'QA Frontdesk', 'RBAC', NULL,
  'QA Frontdesk RBAC', 'QA Frontdesk', 'es-MX', 'America/Mexico_City', 0,
  1, NULL, NOW(), NOW(), 'RBAC test user: Recepcion all properties'
FROM dual
WHERE @company_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM app_user au
    WHERE au.id_company = @company_id
      AND au.email = 'qa.frontdesk.rbac@local.test'
  );

SELECT au.id_user INTO @u_frontdesk
FROM app_user au
WHERE au.id_company = @company_id
  AND au.email = 'qa.frontdesk.rbac@local.test'
ORDER BY au.id_user DESC
LIMIT 1;

UPDATE user_role
SET is_active = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_frontdesk
  AND deleted_at IS NULL;

INSERT INTO user_role (id_user, id_role, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT @u_frontdesk, @role_frontdesk_id, 'RBAC QA role bind', 1, NULL, NOW(), @actor_user_id, NOW()
FROM dual
WHERE @u_frontdesk IS NOT NULL
  AND @role_frontdesk_id IS NOT NULL;

UPDATE user_property
SET is_active = 0,
    is_primary = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_frontdesk
  AND deleted_at IS NULL;

INSERT INTO user_property (id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT
  @u_frontdesk,
  p.id_property,
  CASE WHEN p.id_property = @property_primary_id THEN 1 ELSE 0 END,
  'Frontdesk scope',
  'RBAC QA all properties',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM property p
WHERE p.id_company = @company_id
  AND p.deleted_at IS NULL
  AND p.is_active = 1;

-- 4) Finanzas
UPDATE app_user
SET names = 'QA Finance',
    last_name = 'RBAC',
    maiden_name = NULL,
    full_name = 'QA Finance RBAC',
    display_name = 'QA Finance',
    password_hash = 'RBAC2026!finance',
    is_owner = 0,
    locale = 'es-MX',
    timezone = 'America/Mexico_City',
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW()
WHERE id_company = @company_id
  AND email = 'qa.finance.rbac@local.test';

INSERT INTO app_user (
  id_company, email, password_hash, names, last_name, maiden_name,
  full_name, display_name, locale, timezone, is_owner,
  is_active, deleted_at, created_at, updated_at, notes
)
SELECT
  @company_id, 'qa.finance.rbac@local.test', 'RBAC2026!finance',
  'QA Finance', 'RBAC', NULL,
  'QA Finance RBAC', 'QA Finance', 'es-MX', 'America/Mexico_City', 0,
  1, NULL, NOW(), NOW(), 'RBAC test user: Finanzas'
FROM dual
WHERE @company_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM app_user au
    WHERE au.id_company = @company_id
      AND au.email = 'qa.finance.rbac@local.test'
  );

SELECT au.id_user INTO @u_finance
FROM app_user au
WHERE au.id_company = @company_id
  AND au.email = 'qa.finance.rbac@local.test'
ORDER BY au.id_user DESC
LIMIT 1;

UPDATE user_role
SET is_active = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_finance
  AND deleted_at IS NULL;

INSERT INTO user_role (id_user, id_role, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT @u_finance, @role_finance_id, 'RBAC QA role bind', 1, NULL, NOW(), @actor_user_id, NOW()
FROM dual
WHERE @u_finance IS NOT NULL
  AND @role_finance_id IS NOT NULL;

UPDATE user_property
SET is_active = 0,
    is_primary = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_finance
  AND deleted_at IS NULL;

INSERT INTO user_property (id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT
  @u_finance,
  p.id_property,
  CASE WHEN p.id_property = @property_primary_id THEN 1 ELSE 0 END,
  'Finance scope',
  'RBAC QA all properties',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM property p
WHERE p.id_company = @company_id
  AND p.deleted_at IS NULL
  AND p.is_active = 1;

-- 5) Solo Lectura (single property)
UPDATE app_user
SET names = 'QA Readonly',
    last_name = 'RBAC',
    maiden_name = NULL,
    full_name = 'QA Readonly RBAC',
    display_name = 'QA Readonly',
    password_hash = 'RBAC2026!readonly',
    is_owner = 0,
    locale = 'es-MX',
    timezone = 'America/Mexico_City',
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW()
WHERE id_company = @company_id
  AND email = 'qa.readonly.rbac@local.test';

INSERT INTO app_user (
  id_company, email, password_hash, names, last_name, maiden_name,
  full_name, display_name, locale, timezone, is_owner,
  is_active, deleted_at, created_at, updated_at, notes
)
SELECT
  @company_id, 'qa.readonly.rbac@local.test', 'RBAC2026!readonly',
  'QA Readonly', 'RBAC', NULL,
  'QA Readonly RBAC', 'QA Readonly', 'es-MX', 'America/Mexico_City', 0,
  1, NULL, NOW(), NOW(), 'RBAC test user: Solo Lectura single property'
FROM dual
WHERE @company_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM app_user au
    WHERE au.id_company = @company_id
      AND au.email = 'qa.readonly.rbac@local.test'
  );

SELECT au.id_user INTO @u_readonly
FROM app_user au
WHERE au.id_company = @company_id
  AND au.email = 'qa.readonly.rbac@local.test'
ORDER BY au.id_user DESC
LIMIT 1;

UPDATE user_role
SET is_active = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_readonly
  AND deleted_at IS NULL;

INSERT INTO user_role (id_user, id_role, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT @u_readonly, @role_readonly_id, 'RBAC QA role bind', 1, NULL, NOW(), @actor_user_id, NOW()
FROM dual
WHERE @u_readonly IS NOT NULL
  AND @role_readonly_id IS NOT NULL;

UPDATE user_property
SET is_active = 0,
    is_primary = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_readonly
  AND deleted_at IS NULL;

INSERT INTO user_property (id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT
  @u_readonly,
  @property_primary_id,
  1,
  'Readonly scope',
  'RBAC QA primary property only',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM dual
WHERE @u_readonly IS NOT NULL
  AND @property_primary_id IS NOT NULL;

-- 6) Recepcion restricted scope (single property)
UPDATE app_user
SET names = 'QA Frontdesk Scope',
    last_name = 'RBAC',
    maiden_name = NULL,
    full_name = 'QA Frontdesk Scope RBAC',
    display_name = 'QA Frontdesk Scope',
    password_hash = 'RBAC2026!scope',
    is_owner = 0,
    locale = 'es-MX',
    timezone = 'America/Mexico_City',
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW()
WHERE id_company = @company_id
  AND email = 'qa.frontdesk.scope.rbac@local.test';

INSERT INTO app_user (
  id_company, email, password_hash, names, last_name, maiden_name,
  full_name, display_name, locale, timezone, is_owner,
  is_active, deleted_at, created_at, updated_at, notes
)
SELECT
  @company_id, 'qa.frontdesk.scope.rbac@local.test', 'RBAC2026!scope',
  'QA Frontdesk Scope', 'RBAC', NULL,
  'QA Frontdesk Scope RBAC', 'QA Frontdesk Scope', 'es-MX', 'America/Mexico_City', 0,
  1, NULL, NOW(), NOW(), 'RBAC test user: Recepcion single property'
FROM dual
WHERE @company_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM app_user au
    WHERE au.id_company = @company_id
      AND au.email = 'qa.frontdesk.scope.rbac@local.test'
  );

SELECT au.id_user INTO @u_frontdesk_scope
FROM app_user au
WHERE au.id_company = @company_id
  AND au.email = 'qa.frontdesk.scope.rbac@local.test'
ORDER BY au.id_user DESC
LIMIT 1;

UPDATE user_role
SET is_active = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_frontdesk_scope
  AND deleted_at IS NULL;

INSERT INTO user_role (id_user, id_role, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT @u_frontdesk_scope, @role_frontdesk_id, 'RBAC QA role bind', 1, NULL, NOW(), @actor_user_id, NOW()
FROM dual
WHERE @u_frontdesk_scope IS NOT NULL
  AND @role_frontdesk_id IS NOT NULL;

UPDATE user_property
SET is_active = 0,
    is_primary = 0,
    deleted_at = NOW(),
    updated_at = NOW()
WHERE id_user = @u_frontdesk_scope
  AND deleted_at IS NULL;

INSERT INTO user_property (id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at)
SELECT
  @u_frontdesk_scope,
  @property_primary_id,
  1,
  'Frontdesk scoped',
  'RBAC QA primary property only',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM dual
WHERE @u_frontdesk_scope IS NOT NULL
  AND @property_primary_id IS NOT NULL;

-- Verification output
SELECT
  au.id_user,
  au.email,
  au.is_owner,
  au.is_active,
  GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles,
  COUNT(DISTINCT CASE WHEN up.deleted_at IS NULL AND up.is_active = 1 THEN up.id_property END) AS active_properties
FROM app_user au
LEFT JOIN user_role ur
  ON ur.id_user = au.id_user
 AND ur.deleted_at IS NULL
 AND ur.is_active = 1
LEFT JOIN role r
  ON r.id_role = ur.id_role
LEFT JOIN user_property up
  ON up.id_user = au.id_user
WHERE au.id_company = @company_id
  AND au.email IN (
    'qa.owner.rbac@local.test',
    'qa.ops.rbac@local.test',
    'qa.frontdesk.rbac@local.test',
    'qa.finance.rbac@local.test',
    'qa.readonly.rbac@local.test',
    'qa.frontdesk.scope.rbac@local.test'
  )
GROUP BY au.id_user, au.email, au.is_owner, au.is_active
ORDER BY au.email;
