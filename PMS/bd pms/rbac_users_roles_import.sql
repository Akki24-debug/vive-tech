-- RBAC users + roles import
-- Purpose:
--   1) Ensure base RBAC permissions/roles exist and are synchronized.
--   2) Create a coherent operational user set for testing or first production rollout.
--   3) Bind users to roles and property scope.
--
-- Prerequisites:
--   - RBAC schema/procedures already installed.
--   - sp_access_seed_defaults must exist.
--   - Company must already exist.
--
-- Safe to re-run:
--   - Users are upserted by company + email.
--   - Role bindings for these seeded users are refreshed.
--   - Property scope for these seeded users is refreshed.
--
-- Recommended usage:
--   SET @company_code = 'VLV';
--   SET @email_domain = 'example.com';     -- Use local.test for QA if desired
--   SET @authz_mode = 'audit';             -- Change to 'enforce' when ready
--   SOURCE this_file.sql;

SET @company_code = 'VLV';
SET @email_domain = 'example.com';
SET @authz_mode = 'audit';

-- Passwords are intentionally explicit for controlled rollout/testing.
-- Change them immediately in real production usage.
SET @pwd_owner = 'PMS2026!Owner';
SET @pwd_ops = 'PMS2026!Ops';
SET @pwd_frontdesk = 'PMS2026!Frontdesk';
SET @pwd_finance = 'PMS2026!Finance';
SET @pwd_readonly = 'PMS2026!Readonly';
SET @pwd_frontdesk_scoped = 'PMS2026!FrontdeskScoped';

-- Refresh only the users managed by this script.
SET @reset_existing_user_roles = 1;
SET @reset_existing_user_properties = 1;

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

-- 1) Ensure permissions + core roles exist and keep their permissions coherent.
CALL sp_access_seed_defaults(@company_code, @actor_user_id);

-- 2) Set authorization mode for this company.
UPDATE pms_authz_config
SET authz_mode = CASE
      WHEN LOWER(TRIM(COALESCE(@authz_mode, 'audit'))) = 'enforce' THEN 'enforce'
      ELSE 'audit'
    END,
    updated_by = @actor_user_id,
    updated_at = NOW()
WHERE id_company = @company_id;

DROP TEMPORARY TABLE IF EXISTS tmp_rbac_seed_users;
CREATE TEMPORARY TABLE tmp_rbac_seed_users (
  email_local VARCHAR(120) NOT NULL,
  role_name VARCHAR(120) NOT NULL,
  names VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  maiden_name VARCHAR(120) NULL,
  display_name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  scope_mode ENUM('all','primary','secondary') NOT NULL DEFAULT 'all',
  locale VARCHAR(20) NOT NULL DEFAULT 'es-MX',
  timezone VARCHAR(100) NOT NULL DEFAULT 'America/Mexico_City',
  notes VARCHAR(255) NULL,
  PRIMARY KEY (email_local)
) ENGINE=Memory;

INSERT INTO tmp_rbac_seed_users (
  email_local, role_name, names, last_name, maiden_name, display_name,
  password_hash, is_owner, scope_mode, locale, timezone, notes
) VALUES
  ('admin.pms', 'Owner/Admin', 'Admin', 'PMS', NULL, 'Admin PMS', @pwd_owner, 1, 'all', 'es-MX', 'America/Mexico_City', 'Bootstrap owner/admin account'),
  ('operaciones.pms', 'Operaciones', 'Operaciones', 'PMS', NULL, 'Operaciones PMS', @pwd_ops, 0, 'all', 'es-MX', 'America/Mexico_City', 'Operations manager account'),
  ('recepcion.pms', 'Recepcion', 'Recepcion', 'PMS', NULL, 'Recepcion PMS', @pwd_frontdesk, 0, 'all', 'es-MX', 'America/Mexico_City', 'Frontdesk account with all properties'),
  ('finanzas.pms', 'Finanzas', 'Finanzas', 'PMS', NULL, 'Finanzas PMS', @pwd_finance, 0, 'all', 'es-MX', 'America/Mexico_City', 'Finance account'),
  ('lectura.pms', 'Solo Lectura', 'Solo', 'Lectura', NULL, 'Solo Lectura PMS', @pwd_readonly, 0, 'primary', 'es-MX', 'America/Mexico_City', 'Audit/readonly account scoped to primary property'),
  ('recepcion.scope', 'Recepcion', 'Recepcion', 'Scope', NULL, 'Recepcion Scope', @pwd_frontdesk_scoped, 0, 'primary', 'es-MX', 'America/Mexico_City', 'Frontdesk account scoped to primary property');

-- 3) Upsert users.
UPDATE app_user au
JOIN tmp_rbac_seed_users s
  ON au.id_company = @company_id
 AND au.email = CONCAT(s.email_local, '@', @email_domain)
SET au.password_hash = s.password_hash,
    au.names = s.names,
    au.last_name = s.last_name,
    au.maiden_name = s.maiden_name,
    au.full_name = TRIM(CONCAT(s.names, ' ', s.last_name, IF(s.maiden_name IS NULL OR s.maiden_name = '', '', CONCAT(' ', s.maiden_name)))),
    au.display_name = s.display_name,
    au.locale = s.locale,
    au.timezone = s.timezone,
    au.is_owner = s.is_owner,
    au.is_active = 1,
    au.deleted_at = NULL,
    au.notes = s.notes,
    au.updated_at = NOW();

INSERT INTO app_user (
  id_company, email, password_hash, names, last_name, maiden_name,
  full_name, display_name, locale, timezone, is_owner,
  is_active, deleted_at, created_at, updated_at, notes
)
SELECT
  @company_id,
  CONCAT(s.email_local, '@', @email_domain),
  s.password_hash,
  s.names,
  s.last_name,
  s.maiden_name,
  TRIM(CONCAT(s.names, ' ', s.last_name, IF(s.maiden_name IS NULL OR s.maiden_name = '', '', CONCAT(' ', s.maiden_name)))),
  s.display_name,
  s.locale,
  s.timezone,
  s.is_owner,
  1,
  NULL,
  NOW(),
  NOW(),
  s.notes
FROM tmp_rbac_seed_users s
WHERE @company_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM app_user au
    WHERE au.id_company = @company_id
      AND au.email = CONCAT(s.email_local, '@', @email_domain)
  );

DROP TEMPORARY TABLE IF EXISTS tmp_rbac_seed_users_resolved;
CREATE TEMPORARY TABLE tmp_rbac_seed_users_resolved AS
SELECT
  s.*,
  CONCAT(s.email_local, '@', @email_domain) AS email,
  au.id_user,
  r.id_role
FROM tmp_rbac_seed_users s
JOIN app_user au
  ON au.id_company = @company_id
 AND au.email = CONCAT(s.email_local, '@', @email_domain)
 AND au.deleted_at IS NULL
JOIN role r
  ON r.name = s.role_name
 AND (r.id_property IS NULL OR r.id_property = 0)
 AND r.deleted_at IS NULL
 AND r.is_active = 1;

-- 4) Refresh role bindings for seeded users only.
UPDATE user_role ur
JOIN tmp_rbac_seed_users_resolved x
  ON x.id_user = ur.id_user
SET ur.is_active = CASE WHEN @reset_existing_user_roles = 1 THEN 0 ELSE ur.is_active END,
    ur.deleted_at = CASE WHEN @reset_existing_user_roles = 1 THEN NOW() ELSE ur.deleted_at END,
    ur.updated_at = NOW()
WHERE ur.deleted_at IS NULL
  AND @reset_existing_user_roles = 1;

INSERT INTO user_role (
  id_user, id_role, notes, is_active, deleted_at, created_at, created_by, updated_at
)
SELECT
  x.id_user,
  x.id_role,
  CONCAT('RBAC import role bind: ', x.role_name),
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM tmp_rbac_seed_users_resolved x
WHERE NOT EXISTS (
  SELECT 1
  FROM user_role ur
  WHERE ur.id_user = x.id_user
    AND ur.id_role = x.id_role
    AND ur.deleted_at IS NULL
    AND ur.is_active = 1
);

-- 5) Refresh property scope for seeded users only.
UPDATE user_property up
JOIN tmp_rbac_seed_users_resolved x
  ON x.id_user = up.id_user
SET up.is_active = CASE WHEN @reset_existing_user_properties = 1 THEN 0 ELSE up.is_active END,
    up.is_primary = CASE WHEN @reset_existing_user_properties = 1 THEN 0 ELSE up.is_primary END,
    up.deleted_at = CASE WHEN @reset_existing_user_properties = 1 THEN NOW() ELSE up.deleted_at END,
    up.updated_at = NOW()
WHERE up.deleted_at IS NULL
  AND @reset_existing_user_properties = 1;

INSERT INTO user_property (
  id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at
)
SELECT
  x.id_user,
  p.id_property,
  CASE WHEN p.id_property = @property_primary_id THEN 1 ELSE 0 END,
  CONCAT(x.role_name, ' scope'),
  CASE
    WHEN x.scope_mode = 'all' THEN 'Seeded RBAC scope: all properties'
    WHEN x.scope_mode = 'secondary' THEN 'Seeded RBAC scope: secondary property'
    ELSE 'Seeded RBAC scope: primary property'
  END,
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM tmp_rbac_seed_users_resolved x
JOIN property p
  ON p.id_company = @company_id
 AND p.deleted_at IS NULL
 AND p.is_active = 1
WHERE x.scope_mode = 'all'
  AND NOT EXISTS (
    SELECT 1
    FROM user_property up
    WHERE up.id_user = x.id_user
      AND up.id_property = p.id_property
      AND up.deleted_at IS NULL
      AND up.is_active = 1
  );

INSERT INTO user_property (
  id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at
)
SELECT
  x.id_user,
  @property_primary_id,
  1,
  CONCAT(x.role_name, ' scope'),
  'Seeded RBAC scope: primary property',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM tmp_rbac_seed_users_resolved x
WHERE x.scope_mode = 'primary'
  AND @property_primary_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM user_property up
    WHERE up.id_user = x.id_user
      AND up.id_property = @property_primary_id
      AND up.deleted_at IS NULL
      AND up.is_active = 1
  );

INSERT INTO user_property (
  id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at
)
SELECT
  x.id_user,
  @property_secondary_id,
  1,
  CONCAT(x.role_name, ' scope'),
  'Seeded RBAC scope: secondary property',
  1,
  NULL,
  NOW(),
  @actor_user_id,
  NOW()
FROM tmp_rbac_seed_users_resolved x
WHERE x.scope_mode = 'secondary'
  AND @property_secondary_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM user_property up
    WHERE up.id_user = x.id_user
      AND up.id_property = @property_secondary_id
      AND up.deleted_at IS NULL
      AND up.is_active = 1
  );

-- 6) Verification: users + role + property scope.
SELECT
  au.id_user,
  au.email,
  au.display_name,
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
    CONCAT('admin.pms', '@', @email_domain),
    CONCAT('operaciones.pms', '@', @email_domain),
    CONCAT('recepcion.pms', '@', @email_domain),
    CONCAT('finanzas.pms', '@', @email_domain),
    CONCAT('lectura.pms', '@', @email_domain),
    CONCAT('recepcion.scope', '@', @email_domain)
  )
GROUP BY au.id_user, au.email, au.display_name, au.is_owner, au.is_active
ORDER BY au.email;

-- 7) Verification: role permission counts.
SELECT
  r.name AS role_name,
  COUNT(DISTINCT rp.id_permission) AS granted_permissions
FROM role r
LEFT JOIN role_permission rp
  ON rp.id_role = r.id_role
 AND rp.deleted_at IS NULL
 AND rp.is_active = 1
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND (r.id_property IS NULL OR r.id_property = 0)
  AND r.name IN ('Owner/Admin', 'Operaciones', 'Recepcion', 'Finanzas', 'Solo Lectura')
GROUP BY r.id_role, r.name
ORDER BY FIELD(r.name, 'Owner/Admin', 'Operaciones', 'Recepcion', 'Finanzas', 'Solo Lectura');

-- 8) Verification: company authz mode.
SELECT
  c.code AS company_code,
  ac.authz_mode,
  ac.updated_by,
  ac.updated_at
FROM pms_authz_config ac
JOIN company c
  ON c.id_company = ac.id_company
WHERE ac.id_company = @company_id;
