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
