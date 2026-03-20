<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../services/RateplanPricingService.php';

header('Content-Type: application/json; charset=utf-8');

function rpm_json($payload, $status = 200)
{
    http_response_code((int)$status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function rpm_one(PDO $pdo, $sql, array $params = array())
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rpm_all(PDO $pdo, $sql, array $params = array())
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $rows ?: array();
}

$user = pms_current_user();
if (!$user) {
    rpm_json(array('error' => 'unauthorized'), 401);
}
$companyId = isset($user['company_id']) ? (int)$user['company_id'] : 0;
$actorUserId = isset($user['id_user']) ? (int)$user['id_user'] : null;
if ($companyId <= 0) {
    rpm_json(array('error' => 'invalid_company'), 400);
}

$action = isset($_REQUEST['action']) ? strtolower(trim((string)$_REQUEST['action'])) : '';
$db = pms_get_connection();

try {
    if ($action === 'list_modifiers') {
        $idRateplan = isset($_REQUEST['id_rateplan']) ? (int)$_REQUEST['id_rateplan'] : 0;
        if ($idRateplan <= 0) rpm_json(array('error' => 'id_rateplan_required'), 400);
        $owned = rpm_one($db, 'SELECT rp.id_rateplan FROM rateplan rp JOIN property p ON p.id_property = rp.id_property WHERE rp.id_rateplan = ? AND p.id_company = ? AND rp.deleted_at IS NULL LIMIT 1', array($idRateplan, $companyId));
        if (!$owned) rpm_json(array('error' => 'rateplan_not_found'), 404);
        $mods = rpm_all($db, 'SELECT * FROM rateplan_modifier WHERE id_rateplan = ? AND deleted_at IS NULL ORDER BY priority DESC, id_rateplan_modifier ASC', array($idRateplan));
        $ids = array();
        foreach ($mods as $m) $ids[] = (int)$m['id_rateplan_modifier'];
        if (!$ids) rpm_json(array('items' => array()));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $schedules = rpm_all($db, 'SELECT * FROM rateplan_modifier_schedule WHERE id_rateplan_modifier IN (' . $in . ') AND deleted_at IS NULL ORDER BY id_rateplan_modifier_schedule', $ids);
        $conditions = rpm_all($db, 'SELECT * FROM rateplan_modifier_condition WHERE id_rateplan_modifier IN (' . $in . ') AND deleted_at IS NULL ORDER BY sort_order, id_rateplan_modifier_condition', $ids);
        $scopes = rpm_all($db, 'SELECT * FROM rateplan_modifier_scope WHERE id_rateplan_modifier IN (' . $in . ') AND deleted_at IS NULL ORDER BY id_rateplan_modifier_scope', $ids);
        $byId = array();
        foreach ($mods as $m) {
            $id = (int)$m['id_rateplan_modifier'];
            $m['schedules'] = array();
            $m['conditions'] = array();
            $m['scopes'] = array();
            $byId[$id] = $m;
        }
        foreach ($schedules as $r) { $byId[(int)$r['id_rateplan_modifier']]['schedules'][] = $r; }
        foreach ($conditions as $r) { $byId[(int)$r['id_rateplan_modifier']]['conditions'][] = $r; }
        foreach ($scopes as $r) { $byId[(int)$r['id_rateplan_modifier']]['scopes'][] = $r; }
        rpm_json(array('items' => array_values($byId)));
    }

    if ($action === 'upsert_modifier') {
        $idModifier = isset($_POST['id_rateplan_modifier']) ? (int)$_POST['id_rateplan_modifier'] : 0;
        $idRateplan = isset($_POST['id_rateplan']) ? (int)$_POST['id_rateplan'] : 0;
        $name = trim((string)(isset($_POST['modifier_name']) ? $_POST['modifier_name'] : ''));
        if ($idModifier <= 0 && ($idRateplan <= 0 || $name === '')) rpm_json(array('error' => 'missing_required_fields'), 400);
        $mode = strtolower(trim((string)(isset($_POST['apply_mode']) ? $_POST['apply_mode'] : 'stack')));
        $actionKey = strtolower(trim((string)(isset($_POST['price_action']) ? $_POST['price_action'] : 'add_pct')));
        $allowedMode = array('stack', 'best_for_guest', 'best_for_property', 'override');
        $allowedAction = array('add_pct', 'add_cents', 'set_price');
        if (!in_array($mode, $allowedMode, true)) $mode = 'stack';
        if (!in_array($actionKey, $allowedAction, true)) $actionKey = 'add_pct';
        $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $isAlwaysOn = isset($_POST['is_always_on']) ? (int)$_POST['is_always_on'] : 0;
        $respectMin = isset($_POST['respect_category_min']) ? (int)$_POST['respect_category_min'] : 1;
        $addPct = isset($_POST['add_pct']) && $_POST['add_pct'] !== '' ? (float)$_POST['add_pct'] : null;
        $addCents = isset($_POST['add_cents']) && $_POST['add_cents'] !== '' ? (int)$_POST['add_cents'] : null;
        $setPrice = isset($_POST['set_price_cents']) && $_POST['set_price_cents'] !== '' ? (int)$_POST['set_price_cents'] : null;
        $clampMin = isset($_POST['clamp_min_cents']) && $_POST['clamp_min_cents'] !== '' ? (int)$_POST['clamp_min_cents'] : null;
        $clampMax = isset($_POST['clamp_max_cents']) && $_POST['clamp_max_cents'] !== '' ? (int)$_POST['clamp_max_cents'] : null;
        $description = isset($_POST['description']) ? trim((string)$_POST['description']) : null;
        if ($idModifier > 0) {
            $row = rpm_one($db, 'SELECT rm.id_rateplan_modifier FROM rateplan_modifier rm JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan JOIN property p ON p.id_property = rp.id_property WHERE rm.id_rateplan_modifier = ? AND p.id_company = ? LIMIT 1', array($idModifier, $companyId));
            if (!$row) rpm_json(array('error' => 'modifier_not_found'), 404);
            $sql = 'UPDATE rateplan_modifier SET modifier_name = COALESCE(?, modifier_name), description = ?, priority = ?, apply_mode = ?, price_action = ?, add_pct = ?, add_cents = ?, set_price_cents = ?, clamp_min_cents = ?, clamp_max_cents = ?, respect_category_min = ?, is_always_on = ?, is_active = ?, updated_at = NOW() WHERE id_rateplan_modifier = ?';
            $db->prepare($sql)->execute(array($name !== '' ? $name : null, $description, $priority, $mode, $actionKey, $addPct, $addCents, $setPrice, $clampMin, $clampMax, $respectMin ? 1 : 0, $isAlwaysOn ? 1 : 0, $isActive ? 1 : 0, $idModifier));
            rpm_json(array('ok' => true, 'id_rateplan_modifier' => $idModifier));
        }
        $owned = rpm_one($db, 'SELECT rp.id_rateplan FROM rateplan rp JOIN property p ON p.id_property = rp.id_property WHERE rp.id_rateplan = ? AND p.id_company = ? AND rp.deleted_at IS NULL LIMIT 1', array($idRateplan, $companyId));
        if (!$owned) rpm_json(array('error' => 'rateplan_not_found'), 404);
        $sql = 'INSERT INTO rateplan_modifier (id_rateplan, modifier_name, description, priority, apply_mode, price_action, add_pct, add_cents, set_price_cents, clamp_min_cents, clamp_max_cents, respect_category_min, is_always_on, is_active, created_at, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())';
        $db->prepare($sql)->execute(array($idRateplan, $name, $description, $priority, $mode, $actionKey, $addPct, $addCents, $setPrice, $clampMin, $clampMax, $respectMin ? 1 : 0, $isAlwaysOn ? 1 : 0, $isActive ? 1 : 0, $actorUserId));
        rpm_json(array('ok' => true, 'id_rateplan_modifier' => (int)$db->lastInsertId()));
    }

    if ($action === 'upsert_schedule' || $action === 'delete_schedule') {
        $idSchedule = isset($_POST['id_rateplan_modifier_schedule']) ? (int)$_POST['id_rateplan_modifier_schedule'] : 0;
        $idModifier = isset($_POST['id_rateplan_modifier']) ? (int)$_POST['id_rateplan_modifier'] : 0;
        if ($action === 'upsert_schedule' && $idSchedule <= 0 && $idModifier <= 0) rpm_json(array('error' => 'id_rateplan_modifier_required'), 400);
        $ownerSql = 'SELECT s.id_rateplan_modifier_schedule AS id_schedule, rm.id_rateplan_modifier AS id_modifier
                     FROM rateplan_modifier rm
                     JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan
                     JOIN property p ON p.id_property = rp.id_property
                     LEFT JOIN rateplan_modifier_schedule s ON s.id_rateplan_modifier_schedule = ? AND s.id_rateplan_modifier = rm.id_rateplan_modifier
                     WHERE (rm.id_rateplan_modifier = ? OR s.id_rateplan_modifier_schedule = ?)
                       AND p.id_company = ?
                     LIMIT 1';
        $owned = rpm_one($db, $ownerSql, array($idSchedule, $idModifier, $idSchedule, $companyId));
        if (!$owned) rpm_json(array('error' => 'schedule_not_found'), 404);
        if ($action === 'delete_schedule') {
            $db->prepare('UPDATE rateplan_modifier_schedule SET is_active = 0, deleted_at = NOW(), updated_at = NOW() WHERE id_rateplan_modifier_schedule = ?')->execute(array($idSchedule));
            rpm_json(array('ok' => true));
        }
        $type = strtolower(trim((string)(isset($_POST['schedule_type']) ? $_POST['schedule_type'] : 'range')));
        if (!in_array($type, array('range', 'rrule'), true)) $type = 'range';
        $start = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? (string)$_POST['start_date'] : null;
        $end = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? (string)$_POST['end_date'] : null;
        $rrule = isset($_POST['schedule_rrule']) && $_POST['schedule_rrule'] !== '' ? (string)$_POST['schedule_rrule'] : null;
        $exdates = isset($_POST['exdates_json']) && $_POST['exdates_json'] !== '' ? (string)$_POST['exdates_json'] : null;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        if ($idSchedule > 0) {
            $db->prepare('UPDATE rateplan_modifier_schedule SET schedule_type = ?, start_date = ?, end_date = ?, schedule_rrule = ?, exdates_json = ?, is_active = ?, updated_at = NOW() WHERE id_rateplan_modifier_schedule = ?')->execute(array($type, $start, $end, $rrule, $exdates, $isActive ? 1 : 0, $idSchedule));
            rpm_json(array('ok' => true, 'id_rateplan_modifier_schedule' => $idSchedule));
        }
        $db->prepare('INSERT INTO rateplan_modifier_schedule (id_rateplan_modifier, schedule_type, start_date, end_date, schedule_rrule, exdates_json, is_active, created_at, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())')->execute(array($idModifier, $type, $start, $end, $rrule, $exdates, $isActive ? 1 : 0, $actorUserId));
        rpm_json(array('ok' => true, 'id_rateplan_modifier_schedule' => (int)$db->lastInsertId()));
    }

    if ($action === 'upsert_condition' || $action === 'delete_condition') {
        $idCondition = isset($_POST['id_rateplan_modifier_condition']) ? (int)$_POST['id_rateplan_modifier_condition'] : 0;
        $idModifier = isset($_POST['id_rateplan_modifier']) ? (int)$_POST['id_rateplan_modifier'] : 0;
        if ($action === 'upsert_condition' && $idCondition <= 0 && $idModifier <= 0) rpm_json(array('error' => 'id_rateplan_modifier_required'), 400);
        $ownerSql = 'SELECT c.id_rateplan_modifier_condition AS id_condition, rm.id_rateplan_modifier AS id_modifier
                     FROM rateplan_modifier rm
                     JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan
                     JOIN property p ON p.id_property = rp.id_property
                     LEFT JOIN rateplan_modifier_condition c ON c.id_rateplan_modifier_condition = ? AND c.id_rateplan_modifier = rm.id_rateplan_modifier
                     WHERE (rm.id_rateplan_modifier = ? OR c.id_rateplan_modifier_condition = ?)
                       AND p.id_company = ?
                     LIMIT 1';
        $owned = rpm_one($db, $ownerSql, array($idCondition, $idModifier, $idCondition, $companyId));
        if (!$owned) rpm_json(array('error' => 'condition_not_found'), 404);
        if ($action === 'delete_condition') {
            $db->prepare('UPDATE rateplan_modifier_condition SET is_active = 0, deleted_at = NOW(), updated_at = NOW() WHERE id_rateplan_modifier_condition = ?')->execute(array($idCondition));
            rpm_json(array('ok' => true));
        }
        $type = isset($_POST['condition_type']) ? trim((string)$_POST['condition_type']) : '';
        $op = isset($_POST['operator_key']) ? trim((string)$_POST['operator_key']) : 'eq';
        $v1 = isset($_POST['value_number']) && $_POST['value_number'] !== '' ? (float)$_POST['value_number'] : null;
        $v2 = isset($_POST['value_number_to']) && $_POST['value_number_to'] !== '' ? (float)$_POST['value_number_to'] : null;
        $vText = isset($_POST['value_text']) && $_POST['value_text'] !== '' ? (string)$_POST['value_text'] : null;
        $vJson = isset($_POST['value_json']) && $_POST['value_json'] !== '' ? (string)$_POST['value_json'] : null;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        if ($idCondition > 0) {
            $db->prepare('UPDATE rateplan_modifier_condition SET condition_type = ?, operator_key = ?, value_number = ?, value_number_to = ?, value_text = ?, value_json = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id_rateplan_modifier_condition = ?')->execute(array($type, $op, $v1, $v2, $vText, $vJson, $sortOrder, $isActive ? 1 : 0, $idCondition));
            rpm_json(array('ok' => true, 'id_rateplan_modifier_condition' => $idCondition));
        }
        $db->prepare('INSERT INTO rateplan_modifier_condition (id_rateplan_modifier, condition_type, operator_key, value_number, value_number_to, value_text, value_json, sort_order, is_active, created_at, created_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())')->execute(array($idModifier, $type, $op, $v1, $v2, $vText, $vJson, $sortOrder, $isActive ? 1 : 0, $actorUserId));
        rpm_json(array('ok' => true, 'id_rateplan_modifier_condition' => (int)$db->lastInsertId()));
    }

    if ($action === 'upsert_scope' || $action === 'delete_scope') {
        $idScope = isset($_POST['id_rateplan_modifier_scope']) ? (int)$_POST['id_rateplan_modifier_scope'] : 0;
        $idModifier = isset($_POST['id_rateplan_modifier']) ? (int)$_POST['id_rateplan_modifier'] : 0;
        if ($action === 'upsert_scope' && $idScope <= 0 && $idModifier <= 0) rpm_json(array('error' => 'id_rateplan_modifier_required'), 400);
        $ownerSql = 'SELECT sc.id_rateplan_modifier_scope AS id_scope, rm.id_rateplan_modifier AS id_modifier
                     FROM rateplan_modifier rm
                     JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan
                     JOIN property p ON p.id_property = rp.id_property
                     LEFT JOIN rateplan_modifier_scope sc ON sc.id_rateplan_modifier_scope = ? AND sc.id_rateplan_modifier = rm.id_rateplan_modifier
                     WHERE (rm.id_rateplan_modifier = ? OR sc.id_rateplan_modifier_scope = ?)
                       AND p.id_company = ?
                     LIMIT 1';
        $owned = rpm_one($db, $ownerSql, array($idScope, $idModifier, $idScope, $companyId));
        if (!$owned) rpm_json(array('error' => 'scope_not_found'), 404);
        if ($action === 'delete_scope') {
            $db->prepare('UPDATE rateplan_modifier_scope SET is_active = 0, deleted_at = NOW(), updated_at = NOW() WHERE id_rateplan_modifier_scope = ?')->execute(array($idScope));
            rpm_json(array('ok' => true));
        }
        $idCategory = isset($_POST['id_category']) && $_POST['id_category'] !== '' ? (int)$_POST['id_category'] : null;
        $idRoom = isset($_POST['id_room']) && $_POST['id_room'] !== '' ? (int)$_POST['id_room'] : null;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        if ($idScope > 0) {
            $db->prepare('UPDATE rateplan_modifier_scope SET id_category = ?, id_room = ?, is_active = ?, updated_at = NOW() WHERE id_rateplan_modifier_scope = ?')->execute(array($idCategory, $idRoom, $isActive ? 1 : 0, $idScope));
            rpm_json(array('ok' => true, 'id_rateplan_modifier_scope' => $idScope));
        }
        $db->prepare('INSERT INTO rateplan_modifier_scope (id_rateplan_modifier, id_category, id_room, is_active, created_at, created_by, updated_at) VALUES (?, ?, ?, ?, NOW(), ?, NOW())')->execute(array($idModifier, $idCategory, $idRoom, $isActive ? 1 : 0, $actorUserId));
        rpm_json(array('ok' => true, 'id_rateplan_modifier_scope' => (int)$db->lastInsertId()));
    }

    if ($action === 'preview_night' || $action === 'preview_calendar') {
        $idRateplan = isset($_REQUEST['id_rateplan']) ? (int)$_REQUEST['id_rateplan'] : 0;
        $idProperty = isset($_REQUEST['id_property']) ? (int)$_REQUEST['id_property'] : 0;
        $idCategory = isset($_REQUEST['id_category']) ? (int)$_REQUEST['id_category'] : null;
        $idRoom = isset($_REQUEST['id_room']) ? (int)$_REQUEST['id_room'] : null;
        $owned = rpm_one($db, 'SELECT rp.id_rateplan FROM rateplan rp JOIN property p ON p.id_property = rp.id_property WHERE rp.id_rateplan = ? AND p.id_company = ? LIMIT 1', array($idRateplan, $companyId));
        if (!$owned) rpm_json(array('error' => 'rateplan_not_found'), 404);
        $svc = new RateplanPricingService($db);
        if ($action === 'preview_night') {
            $date = isset($_REQUEST['date']) ? (string)$_REQUEST['date'] : '';
            $out = $svc->getNightlyBreakdown($idRateplan, $idProperty, $date, $idCategory, $idRoom, array(
                'channel' => isset($_REQUEST['channel']) ? (string)$_REQUEST['channel'] : null,
                'arrival_date' => isset($_REQUEST['arrival_date']) ? (string)$_REQUEST['arrival_date'] : null,
                'nights' => isset($_REQUEST['nights']) ? (int)$_REQUEST['nights'] : null
            ));
            rpm_json(array('item' => $out));
        }
        $from = isset($_REQUEST['date_from']) ? (string)$_REQUEST['date_from'] : '';
        $to = isset($_REQUEST['date_to']) ? (string)$_REQUEST['date_to'] : '';
        if ($to === '' && isset($_REQUEST['days'])) {
            $days = max(1, min(120, (int)$_REQUEST['days']));
            $to = date('Y-m-d', strtotime($from . ' +' . ($days - 1) . ' day'));
        }
        $rows = $svc->getCalendarPrices($idRateplan, $idProperty, $from, $to, $idCategory, $idRoom, array(
            'channel' => isset($_REQUEST['channel']) ? (string)$_REQUEST['channel'] : null,
            'arrival_date' => isset($_REQUEST['arrival_date']) ? (string)$_REQUEST['arrival_date'] : null,
            'nights' => isset($_REQUEST['nights']) ? (int)$_REQUEST['nights'] : null
        ));
        rpm_json(array('items' => $rows));
    }

    rpm_json(array('error' => 'unsupported_action'), 400);
} catch (Throwable $e) {
    rpm_json(array('error' => $e->getMessage()), 500);
}
