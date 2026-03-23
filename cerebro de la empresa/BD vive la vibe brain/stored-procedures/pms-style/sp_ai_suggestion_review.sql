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
