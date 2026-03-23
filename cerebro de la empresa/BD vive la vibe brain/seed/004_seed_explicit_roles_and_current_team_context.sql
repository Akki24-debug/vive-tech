USE `vive_la_vibe_brain`;

SET NAMES utf8mb4;

START TRANSACTION;

-- Roles explicitamente listados en:
-- C:\Users\ragnarok\Downloads\Vive_la_Vibe_Esqueleto_Base_de_Datos_de_Negocio_v1.md
-- Seccion 7.2 Tabla: role
INSERT INTO `role` (`name`, `description`)
SELECT 'Dirección', NULL
WHERE NOT EXISTS (
  SELECT 1
  FROM `role`
  WHERE `name` = 'Dirección'
);

INSERT INTO `role` (`name`, `description`)
SELECT 'Operación', NULL
WHERE NOT EXISTS (
  SELECT 1
  FROM `role`
  WHERE `name` = 'Operación'
);

INSERT INTO `role` (`name`, `description`)
SELECT 'Tecnología', NULL
WHERE NOT EXISTS (
  SELECT 1
  FROM `role`
  WHERE `name` = 'Tecnología'
);

INSERT INTO `role` (`name`, `description`)
SELECT 'Comercial', NULL
WHERE NOT EXISTS (
  SELECT 1
  FROM `role`
  WHERE `name` = 'Comercial'
);

INSERT INTO `role` (`name`, `description`)
SELECT 'Finanzas', NULL
WHERE NOT EXISTS (
  SELECT 1
  FROM `role`
  WHERE `name` = 'Finanzas'
);

INSERT INTO `role` (`name`, `description`)
SELECT 'IA supervisor', NULL
WHERE NOT EXISTS (
  SELECT 1
  FROM `role`
  WHERE `name` = 'IA supervisor'
);

-- Elevar a catalogo cualquier role_summary explicito ya capturado
-- en user_account. Esto no inventa nuevos roles: solo formaliza
-- etiquetas ya presentes en la base.
INSERT INTO `role` (`name`, `description`)
SELECT DISTINCT TRIM(u.`role_summary`), NULL
FROM `user_account` u
WHERE TRIM(COALESCE(u.`role_summary`, '')) <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM `role` r
    WHERE r.`name` = TRIM(u.`role_summary`)
  );

-- Vincular usuarios existentes con el role que ya declaran en
-- role_summary cuando haya match exacto.
INSERT INTO `user_role` (`user_id`, `role_id`, `is_primary`)
SELECT u.`id`, r.`id`, 1
FROM `user_account` u
INNER JOIN `role` r
  ON r.`name` = TRIM(u.`role_summary`)
LEFT JOIN `user_role` ur
  ON ur.`user_id` = u.`id`
 AND ur.`role_id` = r.`id`
WHERE TRIM(COALESCE(u.`role_summary`, '')) <> ''
  AND ur.`id` IS NULL;

-- Si el vinculo ya existia, marcarlo como principal cuando venga
-- del role_summary actual del usuario.
UPDATE `user_role` ur
INNER JOIN `user_account` u
  ON u.`id` = ur.`user_id`
INNER JOIN `role` r
  ON r.`id` = ur.`role_id`
SET ur.`is_primary` = 1
WHERE TRIM(COALESCE(u.`role_summary`, '')) <> ''
  AND r.`name` = TRIM(u.`role_summary`);

COMMIT;
