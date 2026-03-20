DELIMITER $$

DROP PROCEDURE IF EXISTS sp_access_seed_defaults $$
CREATE PROCEDURE sp_access_seed_defaults(
  IN p_company_code VARCHAR(100),
  IN p_actor_user_id BIGINT
)
proc:BEGIN
  DECLARE v_company_id BIGINT;
  DECLARE v_owner_role_id BIGINT;
  DECLARE v_ops_role_id BIGINT;
  DECLARE v_front_role_id BIGINT;
  DECLARE v_fin_role_id BIGINT;
  DECLARE v_ro_role_id BIGINT;

  IF p_company_code IS NULL OR TRIM(p_company_code) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT c.id_company
    INTO v_company_id
  FROM company c
  WHERE c.code = p_company_code
    AND c.deleted_at IS NULL
  LIMIT 1;

  IF v_company_id IS NULL OR v_company_id <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company code';
  END IF;

  INSERT INTO pms_authz_config (id_company, authz_mode, updated_by, updated_at)
  VALUES (v_company_id, 'audit', p_actor_user_id, NOW())
  ON DUPLICATE KEY UPDATE
    updated_by = VALUES(updated_by),
    updated_at = NOW();

  INSERT INTO permission (code, permission_name, description, resource, action, is_active, deleted_at, created_at, created_by, updated_at)
  VALUES
    ('dashboard.view','Dashboard view','View dashboard','dashboard','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.view','Calendar view','View calendar module','calendar','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.view','Reservations view','View reservations module','reservations','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('guests.view','Guests view','View guests module','guests','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.view','Activities view','View activities module','activities','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.view','Properties view','View properties module','properties','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.view','Rooms view','View rooms module','rooms','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.view','Categories view','View categories module','categories','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.view','Rateplans view','View rateplans module','rateplans','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('messages.view','Messages view','View messages module','messages','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('otas.view','OTAs view','View otas module','otas','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('ota_ical.view','OTA iCal view','View ota iCal module','ota_ical','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.view','Sale items view','View sale items module','sale_items','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('payments.view','Payments view','View payments module','payments','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('incomes.view','Incomes view','View incomes module','incomes','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('obligations.view','Obligations view','View obligations module','obligations','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reports.view','Reports view','View reports module','reports','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('settings.view','Settings view','View settings module','settings','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.view','Users view','View users module','users','view',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.create_hold','Create hold','Create reservation hold from calendar','calendar','create_hold',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.move_reservation','Move reservation','Move reservation in calendar','calendar','move_reservation',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.manage_block','Manage room blocks','Create/update/delete room blocks','calendar','manage_block',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('calendar.register_payment','Register payment from calendar','Create payments from calendar','calendar','register_payment',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.create','Create reservation','Create reservation/wizard','reservations','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.edit','Edit reservation','Edit reservation details','reservations','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.status_change','Change reservation status','Change reservation status','reservations','status_change',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.move_property','Move reservation property','Move reservation across properties','reservations','move_property',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.manage_folio','Manage folios','Create/update/delete folios','reservations','manage_folio',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.post_charge','Post charges','Create/update/delete charges','reservations','post_charge',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.post_payment','Post payments','Create/update/delete payments','reservations','post_payment',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.refund','Create refunds','Create/delete refunds','reservations','refund',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reservations.note_edit','Edit notes','Add/delete reservation notes','reservations','note_edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('guests.create','Create guest','Create guest records','guests','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('guests.edit','Edit guest','Update guest records','guests','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.create','Create activity','Create activities','activities','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.edit','Edit activity','Edit activities','activities','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.book','Book activity','Book activities','activities','book',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('activities.cancel','Cancel activity','Cancel activities','activities','cancel',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.create','Create property','Create properties','properties','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.edit','Edit property','Edit properties','properties','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('properties.delete','Delete property','Delete properties','properties','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.create','Create room','Create rooms','rooms','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.edit','Edit room','Edit rooms','rooms','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rooms.delete','Delete room','Delete rooms','rooms','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.create','Create category','Create categories','categories','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.edit','Edit category','Edit categories','categories','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('categories.delete','Delete category','Delete categories','categories','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.create','Create rateplan','Create rateplans','rateplans','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.edit','Edit rateplan','Edit rateplans','rateplans','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('rateplans.delete','Delete rateplan','Delete rateplans','rateplans','delete',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('messages.template_edit','Edit templates','Edit message templates','messages','template_edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('messages.send','Send messages','Send guest messages','messages','send',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('otas.edit','Edit ota settings','Edit OTA settings','otas','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('ota_ical.edit','Edit ota ical settings','Edit OTA iCal feeds','ota_ical','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('ota_ical.sync','Sync ota ical','Run OTA iCal sync','ota_ical','sync',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.create','Create sale item','Create sale item catalog','sale_items','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.edit','Edit sale item','Edit sale item catalog','sale_items','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('sale_items.relations_edit','Edit sale item relations','Edit derived relations','sale_items','relations_edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('payments.post','Post payment','Post payment operations','payments','post',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('payments.edit','Edit payment','Edit payment operations','payments','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('incomes.reconcile','Reconcile incomes','Reconcile incomes','incomes','reconcile',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('obligations.pay','Pay obligations','Pay obligations','obligations','pay',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reports.run','Run reports','Run reports','reports','run',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('reports.design','Design reports','Design reports','reports','design',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('settings.edit','Edit settings','Edit settings','settings','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.create','Create user','Create users','users','create',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.edit','Edit user','Edit users','users','edit',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.assign_roles','Assign roles','Assign roles to users','users','assign_roles',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.assign_properties','Assign properties','Assign properties to users','users','assign_properties',1,NULL,NOW(),p_actor_user_id,NOW()),
    ('users.manage_roles','Manage roles','Create/edit role definitions','users','manage_roles',1,NULL,NOW(),p_actor_user_id,NOW())
  ON DUPLICATE KEY UPDATE
    permission_name = VALUES(permission_name),
    description = VALUES(description),
    resource = VALUES(resource),
    action = VALUES(action),
    is_active = 1,
    deleted_at = NULL,
    updated_at = NOW();

  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Owner/Admin', 'Global owner/admin role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Owner/Admin' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Operaciones', 'Global operations role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Operaciones' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Recepcion', 'Global frontdesk role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Recepcion' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Finanzas', 'Global finance role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Finanzas' AND r.deleted_at IS NULL
  );
  INSERT INTO role (id_property, name, description, is_system, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT NULL, 'Solo Lectura', 'Global read-only role', 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM dual
  WHERE NOT EXISTS (
    SELECT 1 FROM role r WHERE r.id_property IS NULL AND r.name = 'Solo Lectura' AND r.deleted_at IS NULL
  );

  SELECT id_role INTO v_owner_role_id FROM role WHERE id_property IS NULL AND name = 'Owner/Admin' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_ops_role_id FROM role WHERE id_property IS NULL AND name = 'Operaciones' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_front_role_id FROM role WHERE id_property IS NULL AND name = 'Recepcion' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_fin_role_id FROM role WHERE id_property IS NULL AND name = 'Finanzas' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;
  SELECT id_role INTO v_ro_role_id FROM role WHERE id_property IS NULL AND name = 'Solo Lectura' AND deleted_at IS NULL ORDER BY id_role DESC LIMIT 1;

  DELETE FROM role_permission
  WHERE id_role IN (v_owner_role_id, v_ops_role_id, v_front_role_id, v_fin_role_id, v_ro_role_id);

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_owner_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL AND p.is_active = 1;

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_ops_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND p.code NOT IN (
      'users.view','users.create','users.edit','users.assign_roles','users.assign_properties','users.manage_roles',
      'settings.edit'
    );

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_front_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND p.code IN (
      'dashboard.view','calendar.view','reservations.view','guests.view','messages.view',
      'payments.view','calendar.create_hold','calendar.move_reservation','calendar.register_payment',
      'reservations.create','reservations.edit','reservations.status_change','reservations.manage_folio',
      'reservations.post_charge','reservations.post_payment','reservations.note_edit',
      'guests.create','guests.edit','messages.send','payments.post','payments.edit'
    );

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_fin_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND p.code IN (
      'dashboard.view','calendar.view','reservations.view','payments.view','incomes.view','obligations.view','reports.view',
      'payments.post','payments.edit','incomes.reconcile','obligations.pay','reports.run'
    );

  INSERT INTO role_permission (id_role, id_permission, allow, is_active, deleted_at, created_at, created_by, updated_at)
  SELECT v_ro_role_id, p.id_permission, 1, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM permission p
  WHERE p.deleted_at IS NULL
    AND p.is_active = 1
    AND (p.code LIKE '%.view' OR p.code = 'reports.run');

  INSERT INTO user_property (
    id_user, id_property, is_primary, title, notes, is_active, deleted_at, created_at, created_by, updated_at
  )
  SELECT
    au.id_user, pr.id_property, 0, NULL, NULL, 1, NULL, NOW(), p_actor_user_id, NOW()
  FROM app_user au
  JOIN property pr
    ON pr.id_company = au.id_company
   AND pr.deleted_at IS NULL
   AND pr.is_active = 1
  WHERE au.id_company = v_company_id
    AND au.deleted_at IS NULL
    AND au.is_active = 1
    AND COALESCE(au.is_owner, 0) = 0
    AND NOT EXISTS (
      SELECT 1
      FROM user_property up_any
      JOIN property pr_any ON pr_any.id_property = up_any.id_property
      WHERE up_any.id_user = au.id_user
        AND up_any.deleted_at IS NULL
        AND up_any.is_active = 1
        AND pr_any.id_company = v_company_id
        AND pr_any.deleted_at IS NULL
    )
    AND NOT EXISTS (
      SELECT 1
      FROM user_property up_exists
      WHERE up_exists.id_user = au.id_user
        AND up_exists.id_property = pr.id_property
        AND up_exists.deleted_at IS NULL
    );

  SELECT 'ok' AS status, v_company_id AS id_company, 'seed_complete' AS message;
END $$

DELIMITER ;

