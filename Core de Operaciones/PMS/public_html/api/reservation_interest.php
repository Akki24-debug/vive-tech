<?php
require __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = pms_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(array('error' => 'unauthorized'));
    exit;
}
pms_require_permission('reservations.edit', null, true);

$companyId = isset($user['company_id']) ? (int)$user['company_id'] : 0;
$actorUserId = isset($user['id_user']) ? (int)$user['id_user'] : null;
$action = isset($_POST['action']) ? strtolower(trim((string)$_POST['action'])) : '';
$reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
$catalogId = isset($_POST['catalog_id']) ? (int)$_POST['catalog_id'] : 0;

if (!in_array($action, array('add', 'remove'), true)) {
    http_response_code(400);
    echo json_encode(array('error' => 'invalid_action'));
    exit;
}
if ($companyId <= 0 || $reservationId <= 0 || $catalogId <= 0) {
    http_response_code(400);
    echo json_encode(array('error' => 'invalid_payload'));
    exit;
}

try {
    $db = pms_get_connection();

    $stmt = $db->prepare(
        'SELECT r.id_reservation, r.id_property, p.code AS property_code
         FROM reservation r
         JOIN property p ON p.id_property = r.id_property
         WHERE r.id_reservation = ? AND p.id_company = ? AND r.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(array($reservationId, $companyId));
    $reservationRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reservationRow) {
        http_response_code(404);
        echo json_encode(array('error' => 'reservation_not_found'));
        exit;
    }

    $reservationPropertyId = isset($reservationRow['id_property']) ? (int)$reservationRow['id_property'] : 0;
    $reservationPropertyCode = isset($reservationRow['property_code']) ? (string)$reservationRow['property_code'] : '';
    if ($reservationPropertyCode !== '') {
        pms_require_property_access($reservationPropertyCode, true);
    }

    $stmt = $db->prepare(
        'SELECT sic.id_line_item_catalog
         FROM line_item_catalog sic
         JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
         JOIN pms_settings_interest_catalog psic
           ON psic.id_company = cat.id_company
          AND psic.id_sale_item_catalog = sic.id_line_item_catalog
          AND psic.deleted_at IS NULL
          AND psic.is_active = 1
          AND (psic.id_property IS NULL OR psic.id_property = ?)
         WHERE sic.catalog_type = "sale_item"
           AND sic.id_line_item_catalog = ?
           AND cat.id_company = ?
           AND sic.deleted_at IS NULL
           AND cat.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(array($reservationPropertyId, $catalogId, $companyId));
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(400);
        echo json_encode(array('error' => 'invalid_or_not_allowed_catalog'));
        exit;
    }

    if ($action === 'add') {
        $stmt = $db->prepare(
            'INSERT INTO reservation_interest (id_reservation, id_sale_item_catalog, is_active, deleted_at, created_by)
             VALUES (?, ?, 1, NULL, ?)
             ON DUPLICATE KEY UPDATE is_active = 1, deleted_at = NULL, updated_at = NOW()'
        );
        $stmt->execute(array($reservationId, $catalogId, $actorUserId));
    } else {
        $stmt = $db->prepare(
            'UPDATE reservation_interest
             SET is_active = 0, deleted_at = NOW(), updated_at = NOW()
             WHERE id_reservation = ? AND id_sale_item_catalog = ?'
        );
        $stmt->execute(array($reservationId, $catalogId));
    }

    echo json_encode(array('ok' => true));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}
