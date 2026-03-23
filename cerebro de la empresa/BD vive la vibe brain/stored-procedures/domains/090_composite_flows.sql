/**
 * Procedure: sp_user_role_sync
 * Purpose: Sincroniza completamente los roles de un usuario.
 * Tables touched: user_account, user_role, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_user_role_sync(1, '1,2,3', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_user_role_sync` $$
CREATE PROCEDURE `sp_user_role_sync` (
  IN p_user_id BIGINT UNSIGNED,
  IN p_role_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_csv TEXT;

  SELECT organization_id INTO v_organization_id FROM user_account WHERE id = p_user_id LIMIT 1;
  IF v_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
  END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 1);
  SET v_csv = REPLACE(COALESCE(p_role_ids_csv, ''), ' ', '');
  DELETE FROM user_role
  WHERE user_id = p_user_id
    AND (v_csv = '' OR FIND_IN_SET(CAST(role_id AS CHAR), v_csv) = 0);
  INSERT INTO user_role (user_id, role_id, is_primary)
  SELECT p_user_id, r.id, 0
  FROM role r
  WHERE v_csv <> ''
    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0
    AND NOT EXISTS (
      SELECT 1 FROM user_role ur WHERE ur.user_id = p_user_id AND ur.role_id = r.id
    );
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'user_role', p_user_id, NULL, NULL, NULL, 'Synced user roles');
  SELECT * FROM user_role WHERE user_id = p_user_id ORDER BY id;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_member_sync
 * Purpose: Sincroniza miembros de un proyecto.
 * Tables touched: project_member, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_project_member_sync(1, '1,2,3', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_member_sync` $$
CREATE PROCEDURE `sp_project_member_sync` (
  IN p_project_id BIGINT UNSIGNED,
  IN p_related_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_csv TEXT;

  SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
  IF v_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent record not found';
  END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  SET v_csv = REPLACE(COALESCE(p_related_ids_csv, ''), ' ', '');
  DELETE FROM `project_member`
  WHERE `project_id` = p_project_id
    AND (v_csv = '' OR FIND_IN_SET(CAST(`user_id` AS CHAR), v_csv) = 0);
  INSERT INTO `project_member` (`project_id`, `user_id`)
  SELECT p_project_id, r.id
  FROM `user_account` r
  WHERE v_csv <> ''
    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0
    AND NOT EXISTS (
      SELECT 1 FROM `project_member` x WHERE x.`project_id` = p_project_id AND x.`user_id` = r.id
    );
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'project_member', p_project_id, NULL, NULL, NULL, 'Synced relation set');
  SELECT * FROM `project_member` WHERE `project_id` = p_project_id ORDER BY id;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_objective_link_sync
 * Purpose: Sincroniza objetivos vinculados a un proyecto.
 * Tables touched: project_objective_link, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_project_objective_link_sync(1, '1,2,3', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_objective_link_sync` $$
CREATE PROCEDURE `sp_project_objective_link_sync` (
  IN p_project_id BIGINT UNSIGNED,
  IN p_related_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_csv TEXT;

  SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
  IF v_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent record not found';
  END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  SET v_csv = REPLACE(COALESCE(p_related_ids_csv, ''), ' ', '');
  DELETE FROM `project_objective_link`
  WHERE `project_id` = p_project_id
    AND (v_csv = '' OR FIND_IN_SET(CAST(`objective_record_id` AS CHAR), v_csv) = 0);
  INSERT INTO `project_objective_link` (`project_id`, `objective_record_id`)
  SELECT p_project_id, r.id
  FROM `objective_record` r
  WHERE v_csv <> ''
    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0
    AND NOT EXISTS (
      SELECT 1 FROM `project_objective_link` x WHERE x.`project_id` = p_project_id AND x.`objective_record_id` = r.id
    );
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'project_objective_link', p_project_id, NULL, NULL, NULL, 'Synced relation set');
  SELECT * FROM `project_objective_link` WHERE `project_id` = p_project_id ORDER BY id;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_tag_sync
 * Purpose: Sincroniza tags de un proyecto.
 * Tables touched: project_tag_link, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_project_tag_sync(1, '1,2,3', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_tag_sync` $$
CREATE PROCEDURE `sp_project_tag_sync` (
  IN p_project_id BIGINT UNSIGNED,
  IN p_related_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_csv TEXT;

  SELECT organization_id INTO v_organization_id FROM project WHERE id = p_project_id LIMIT 1;
  IF v_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent record not found';
  END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  SET v_csv = REPLACE(COALESCE(p_related_ids_csv, ''), ' ', '');
  DELETE FROM `project_tag_link`
  WHERE `project_id` = p_project_id
    AND (v_csv = '' OR FIND_IN_SET(CAST(`project_tag_id` AS CHAR), v_csv) = 0);
  INSERT INTO `project_tag_link` (`project_id`, `project_tag_id`)
  SELECT p_project_id, r.id
  FROM `project_tag` r
  WHERE v_csv <> ''
    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0
    AND NOT EXISTS (
      SELECT 1 FROM `project_tag_link` x WHERE x.`project_id` = p_project_id AND x.`project_tag_id` = r.id
    );
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'project_tag_link', p_project_id, NULL, NULL, NULL, 'Synced relation set');
  SELECT * FROM `project_tag_link` WHERE `project_id` = p_project_id ORDER BY id;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_tag_sync
 * Purpose: Sincroniza tags de una tarea.
 * Tables touched: task_tag_link, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_task_tag_sync(1, '1,2,3', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_tag_sync` $$
CREATE PROCEDURE `sp_task_tag_sync` (
  IN p_task_id BIGINT UNSIGNED,
  IN p_related_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_csv TEXT;

  SELECT p.organization_id INTO v_organization_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_task_id LIMIT 1;
  IF v_organization_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Parent record not found';
  END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  SET v_csv = REPLACE(COALESCE(p_related_ids_csv, ''), ' ', '');
  DELETE FROM `task_tag_link`
  WHERE `task_id` = p_task_id
    AND (v_csv = '' OR FIND_IN_SET(CAST(`project_tag_id` AS CHAR), v_csv) = 0);
  INSERT INTO `task_tag_link` (`task_id`, `project_tag_id`)
  SELECT p_task_id, r.id
  FROM `project_tag` r
  WHERE v_csv <> ''
    AND FIND_IN_SET(CAST(r.id AS CHAR), v_csv) > 0
    AND NOT EXISTS (
      SELECT 1 FROM `task_tag_link` x WHERE x.`task_id` = p_task_id AND x.`project_tag_id` = r.id
    );
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'task_tag_link', p_task_id, NULL, NULL, NULL, 'Synced relation set');
  SELECT * FROM `task_tag_link` WHERE `task_id` = p_task_id ORDER BY id;
END $$

DELIMITER ;

/**
 * Procedure: sp_task_status_update
 * Purpose: Actualiza estado de tarea, registra auditoria e historial y opcionalmente crea un `task_update`.
 * Tables touched: task, task_update, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_task_status_update(1, 'done', 100.00, 'Closed', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_task_status_update` $$
CREATE PROCEDURE `sp_task_status_update` (
  IN p_task_id BIGINT UNSIGNED,
  IN p_new_status VARCHAR(50),
  IN p_completion_percent DECIMAL(5,2),
  IN p_summary TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  DECLARE v_project_id BIGINT UNSIGNED DEFAULT NULL;
  SELECT p.organization_id, t.current_status, t.project_id INTO v_organization_id, v_old_status, v_project_id FROM task t JOIN project p ON p.id = t.project_id WHERE t.id = p_task_id LIMIT 1;
  IF v_organization_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Task not found'; END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  UPDATE task SET current_status = p_new_status, completion_percent = COALESCE(p_completion_percent, completion_percent), completed_at = CASE WHEN p_new_status IN ('done', 'completed', 'closed') THEN COALESCE(completed_at, NOW()) ELSE completed_at END, last_activity_at = NOW(), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_task_id;
  CALL sp_status_history_insert('task', p_task_id, v_old_status, p_new_status, NULLIF(p_actor_user_id, 0), p_summary);
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'task', p_task_id, 'current_status', v_old_status, p_new_status, COALESCE(NULLIF(p_summary, ''), 'Task status update'));
  IF p_summary IS NOT NULL AND TRIM(p_summary) <> '' THEN INSERT INTO task_update (task_id, project_id, user_id, update_type, progress_percent_after, summary) VALUES (p_task_id, v_project_id, NULLIF(p_actor_user_id, 0), 'status_update', COALESCE(p_completion_percent, 0), p_summary); END IF;
  SELECT * FROM task WHERE id = p_task_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_project_status_update
 * Purpose: Actualiza estado de proyecto, registra auditoria e historial y opcionalmente crea un `project_update`.
 * Tables touched: project, project_update, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_project_status_update(1, 'active', 20.00, 'Kickoff done', 'green', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_project_status_update` $$
CREATE PROCEDURE `sp_project_status_update` (
  IN p_project_id BIGINT UNSIGNED,
  IN p_new_status VARCHAR(50),
  IN p_completion_percent DECIMAL(5,2),
  IN p_summary TEXT,
  IN p_health_status VARCHAR(50),
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, current_status INTO v_organization_id, v_old_status FROM project WHERE id = p_project_id LIMIT 1;
  IF v_organization_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Project not found'; END IF;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  UPDATE project SET current_status = p_new_status, completion_percent = COALESCE(p_completion_percent, completion_percent), health_status = COALESCE(NULLIF(p_health_status, ''), health_status), completed_at = CASE WHEN p_new_status IN ('done', 'completed', 'closed') THEN COALESCE(completed_at, NOW()) ELSE completed_at END, last_activity_at = NOW(), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_project_id;
  CALL sp_status_history_insert('project', p_project_id, v_old_status, p_new_status, NULLIF(p_actor_user_id, 0), p_summary);
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'project', p_project_id, 'current_status', v_old_status, p_new_status, COALESCE(NULLIF(p_summary, ''), 'Project status update'));
  IF p_summary IS NOT NULL AND TRIM(p_summary) <> '' THEN INSERT INTO project_update (project_id, user_id, summary, completion_percent_after, health_status_after) VALUES (p_project_id, NULLIF(p_actor_user_id, 0), p_summary, COALESCE(p_completion_percent, 0), COALESCE(NULLIF(p_health_status, ''), 'green')); END IF;
  SELECT * FROM project WHERE id = p_project_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_blocker_resolve
 * Purpose: Actualiza `status` en `blocker` hacia `resolved` con auditoria e historial.
 * Tables touched: blocker, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_blocker_resolve(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_blocker_resolve` $$
CREATE PROCEDURE `sp_blocker_resolve` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT p.organization_id, b.status INTO v_organization_id, v_old_status FROM blocker b JOIN project p ON p.id = b.project_id WHERE b.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `blocker` SET `status` = 'resolved', resolved_at = COALESCE(resolved_at, NOW()), resolution_notes = p_notes WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('blocker', p_id, v_old_status, 'resolved', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Blocker resolved'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'blocker', p_id, 'status', v_old_status, 'resolved', COALESCE(NULLIF(p_notes, ''), 'Blocker resolved'));
  SELECT * FROM blocker WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_meeting_close
 * Purpose: Actualiza `status` en `meeting` hacia `completed` con auditoria e historial.
 * Tables touched: meeting, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_meeting_close(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_meeting_close` $$
CREATE PROCEDURE `sp_meeting_close` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, status INTO v_organization_id, v_old_status FROM meeting WHERE id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `meeting` SET `status` = 'completed', actual_end_at = COALESCE(actual_end_at, NOW()), summary = COALESCE(NULLIF(p_notes, ''), summary), updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('meeting', p_id, v_old_status, 'completed', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Meeting closed'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'meeting', p_id, 'status', v_old_status, 'completed', COALESCE(NULLIF(p_notes, ''), 'Meeting closed'));
  SELECT * FROM meeting WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_decision_record_apply
 * Purpose: Actualiza `decision_status` en `decision_record` hacia `applied` con auditoria e historial.
 * Tables touched: decision_record, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_decision_record_apply(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_decision_record_apply` $$
CREATE PROCEDURE `sp_decision_record_apply` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id), d.decision_status INTO v_organization_id, v_old_status FROM decision_record d LEFT JOIN meeting m ON m.id = d.meeting_id LEFT JOIN project p ON p.id = d.project_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE d.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `decision_record` SET `decision_status` = 'applied', effective_date = COALESCE(effective_date, CURDATE()) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('decision_record', p_id, v_old_status, 'applied', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Decision applied'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'decision_record', p_id, 'decision_status', v_old_status, 'applied', COALESCE(NULLIF(p_notes, ''), 'Decision applied'));
  SELECT * FROM decision_record WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_follow_up_complete
 * Purpose: Actualiza `status` en `follow_up_item` hacia `completed` con auditoria e historial.
 * Tables touched: follow_up_item, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_follow_up_complete(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_follow_up_complete` $$
CREATE PROCEDURE `sp_follow_up_complete` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT COALESCE(m.organization_id, p.organization_id, a.organization_id), f.status INTO v_organization_id, v_old_status FROM follow_up_item f LEFT JOIN meeting m ON m.id = f.meeting_id LEFT JOIN task t ON t.id = f.task_id LEFT JOIN project p ON p.id = t.project_id LEFT JOIN decision_record d ON d.id = f.decision_record_id LEFT JOIN business_area a ON a.id = d.business_area_id WHERE f.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `follow_up_item` SET `status` = 'completed', updated_by_user_id = NULLIF(p_actor_user_id, 0) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('follow_up_item', p_id, v_old_status, 'completed', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Follow up completed'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'follow_up_item', p_id, 'status', v_old_status, 'completed', COALESCE(NULLIF(p_notes, ''), 'Follow up completed'));
  SELECT * FROM follow_up_item WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_knowledge_document_publish
 * Purpose: Actualiza `status` en `knowledge_document` hacia `published` con auditoria e historial.
 * Tables touched: knowledge_document, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_knowledge_document_publish(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_knowledge_document_publish` $$
CREATE PROCEDURE `sp_knowledge_document_publish` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, status INTO v_organization_id, v_old_status FROM knowledge_document WHERE id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `knowledge_document` SET `status` = 'published' WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('knowledge_document', p_id, v_old_status, 'published', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Knowledge document published'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'knowledge_document', p_id, 'status', v_old_status, 'published', COALESCE(NULLIF(p_notes, ''), 'Knowledge document published'));
  SELECT * FROM knowledge_document WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_policy_record_activate
 * Purpose: Actualiza `status` en `policy_record` hacia `active` con auditoria e historial.
 * Tables touched: policy_record, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_policy_record_activate(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_policy_record_activate` $$
CREATE PROCEDURE `sp_policy_record_activate` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, status INTO v_organization_id, v_old_status FROM policy_record WHERE id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `policy_record` SET `status` = 'active' WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('policy_record', p_id, v_old_status, 'active', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Policy activated'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'policy_record', p_id, 'status', v_old_status, 'active', COALESCE(NULLIF(p_notes, ''), 'Policy activated'));
  SELECT * FROM policy_record WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_sop_publish
 * Purpose: Actualiza `current_status` en `sop` hacia `published` con auditoria e historial.
 * Tables touched: sop, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_sop_publish(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_sop_publish` $$
CREATE PROCEDURE `sp_sop_publish` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT organization_id, current_status INTO v_organization_id, v_old_status FROM sop WHERE id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `sop` SET `current_status` = 'published' WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('sop', p_id, v_old_status, 'published', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'SOP published'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'sop', p_id, 'current_status', v_old_status, 'published', COALESCE(NULLIF(p_notes, ''), 'SOP published'));
  SELECT * FROM sop WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_alert_event_resolve
 * Purpose: Actualiza `status` en `alert_event` hacia `resolved` con auditoria e historial.
 * Tables touched: alert_event, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_alert_event_resolve(1, NULL, 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_alert_event_resolve` $$
CREATE PROCEDURE `sp_alert_event_resolve` (
  IN p_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT ar.organization_id, ae.status INTO v_organization_id, v_old_status FROM alert_event ae LEFT JOIN alert_rule ar ON ar.id = ae.alert_rule_id WHERE ae.id = p_id LIMIT 1;
  IF v_organization_id IS NOT NULL THEN CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0); ELSE CALL sp_actor_assert(p_actor_user_id, NULL, 0); END IF;
  UPDATE `alert_event` SET `status` = 'resolved', resolved_at = COALESCE(resolved_at, NOW()), acknowledged_by_user_id = COALESCE(acknowledged_by_user_id, NULLIF(p_actor_user_id, 0)), acknowledged_at = COALESCE(acknowledged_at, NOW()) WHERE id = p_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record not found'; END IF;
  CALL sp_status_history_insert('alert_event', p_id, v_old_status, 'resolved', NULLIF(p_actor_user_id, 0), COALESCE(NULLIF(p_notes, ''), 'Alert resolved'));
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'status_update', 'alert_event', p_id, 'status', v_old_status, 'resolved', COALESCE(NULLIF(p_notes, ''), 'Alert resolved'));
  SELECT * FROM alert_event WHERE id = p_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_ai_suggestion_review
 * Purpose: Marca una sugerencia de IA como revisada y opcionalmente la vincula a una tarea de implementacion.
 * Tables touched: ai_suggestion, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_ai_suggestion_review(1, 'accepted', 10, 'Accepted', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_ai_suggestion_review` $$
CREATE PROCEDURE `sp_ai_suggestion_review` (
  IN p_ai_suggestion_id BIGINT UNSIGNED,
  IN p_review_status VARCHAR(50),
  IN p_implementation_task_id BIGINT UNSIGNED,
  IN p_notes TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_organization_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  SELECT COALESCE(p.organization_id, a.organization_id), s.review_status INTO v_organization_id, v_old_status FROM ai_suggestion s LEFT JOIN project p ON p.id = s.project_id LEFT JOIN business_area a ON a.id = s.business_area_id WHERE s.id = p_ai_suggestion_id LIMIT 1;
  CALL sp_actor_assert(p_actor_user_id, v_organization_id, 0);
  UPDATE ai_suggestion SET review_status = p_review_status, reviewed_by_user_id = NULLIF(p_actor_user_id, 0), reviewed_at = NOW(), implementation_task_id = p_implementation_task_id WHERE id = p_ai_suggestion_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AI suggestion not found'; END IF;
  CALL sp_status_history_insert('ai_suggestion', p_ai_suggestion_id, v_old_status, p_review_status, NULLIF(p_actor_user_id, 0), p_notes);
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'review', 'ai_suggestion', p_ai_suggestion_id, 'review_status', v_old_status, p_review_status, COALESCE(NULLIF(p_notes, ''), 'AI suggestion reviewed'));
  SELECT * FROM ai_suggestion WHERE id = p_ai_suggestion_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_automation_run_finalize
 * Purpose: Finaliza una ejecucion de automatizacion.
 * Tables touched: automation_run, audit_log, status_history
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_automation_run_finalize(1, 'completed', 'ok', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_automation_run_finalize` $$
CREATE PROCEDURE `sp_automation_run_finalize` (
  IN p_automation_run_id BIGINT UNSIGNED,
  IN p_status VARCHAR(50),
  IN p_execution_summary TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_old_status VARCHAR(50) DEFAULT NULL;
  CALL sp_actor_assert(p_actor_user_id, NULL, 0);
  SELECT status INTO v_old_status FROM automation_run WHERE id = p_automation_run_id LIMIT 1;
  UPDATE automation_run SET status = p_status, execution_summary = p_execution_summary, completed_at = COALESCE(completed_at, NOW()) WHERE id = p_automation_run_id;
  IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Automation run not found'; END IF;
  CALL sp_status_history_insert('automation_run', p_automation_run_id, v_old_status, p_status, NULLIF(p_actor_user_id, 0), p_execution_summary);
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'finalize', 'automation_run', p_automation_run_id, 'status', v_old_status, p_status, COALESCE(NULLIF(p_execution_summary, ''), 'Automation run finalized'));
  SELECT * FROM automation_run WHERE id = p_automation_run_id LIMIT 1;
END $$

DELIMITER ;

/**
 * Procedure: sp_external_entity_link_sync
 * Purpose: Sincroniza los external ids asociados a una entidad interna dentro de un sistema externo.
 * Tables touched: external_entity_link, audit_log
 * Security: Write seguro. Requiere actor valido salvo donde el bootstrap del helper lo permita.
 * Output: Result set por SELECT con el estado final del flujo.
 * Example: CALL sp_external_entity_link_sync(1, 'project', 10, 'deal', 'A1,B2', 1);
 */
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_external_entity_link_sync` $$
CREATE PROCEDURE `sp_external_entity_link_sync` (
  IN p_external_system_id BIGINT UNSIGNED,
  IN p_internal_entity_type VARCHAR(50),
  IN p_internal_entity_id BIGINT UNSIGNED,
  IN p_external_entity_type VARCHAR(50),
  IN p_external_ids_csv TEXT,
  IN p_actor_user_id BIGINT UNSIGNED
)
proc:BEGIN
  DECLARE v_csv TEXT;
  DECLARE v_token TEXT;
  DECLARE v_pos INT DEFAULT 0;
  CALL sp_actor_assert(p_actor_user_id, NULL, 0);
  SET v_csv = CONCAT(REPLACE(COALESCE(p_external_ids_csv, ''), ' ', ''), ',');
  DELETE FROM external_entity_link WHERE external_system_id = p_external_system_id AND internal_entity_type = p_internal_entity_type AND internal_entity_id = p_internal_entity_id AND external_entity_type = p_external_entity_type AND (TRIM(COALESCE(p_external_ids_csv, '')) = '' OR FIND_IN_SET(external_entity_id, REPLACE(COALESCE(p_external_ids_csv, ''), ' ', '')) = 0);
  WHILE LOCATE(',', v_csv) > 0 DO
    SET v_pos = LOCATE(',', v_csv);
    SET v_token = TRIM(SUBSTRING(v_csv, 1, v_pos - 1));
    SET v_csv = SUBSTRING(v_csv, v_pos + 1);
    IF v_token <> '' THEN
      INSERT INTO external_entity_link (external_system_id, internal_entity_type, internal_entity_id, external_entity_type, external_entity_id)
      SELECT p_external_system_id, p_internal_entity_type, p_internal_entity_id, p_external_entity_type, v_token
      FROM DUAL
      WHERE NOT EXISTS (
        SELECT 1 FROM external_entity_link x WHERE x.external_system_id = p_external_system_id AND x.internal_entity_type = p_internal_entity_type AND x.internal_entity_id = p_internal_entity_id AND x.external_entity_type = p_external_entity_type AND x.external_entity_id = v_token
      );
    END IF;
  END WHILE;
  CALL sp_audit_log_insert(NULLIF(p_actor_user_id, 0), 'sync', 'external_entity_link', p_internal_entity_id, NULL, NULL, NULL, 'Synced external ids');
  SELECT * FROM external_entity_link WHERE external_system_id = p_external_system_id AND internal_entity_type = p_internal_entity_type AND internal_entity_id = p_internal_entity_id AND external_entity_type = p_external_entity_type ORDER BY id;
END $$

DELIMITER ;

