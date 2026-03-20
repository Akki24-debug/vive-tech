<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/ota_ical.php';

header('Content-Type: application/json; charset=utf-8');

$tokenProvided = trim((string)(isset($_GET['token']) ? $_GET['token'] : ''));
$tokenExpected = trim((string)getenv('PMS_ICAL_SYNC_TOKEN'));
$currentUser = pms_current_user();

$companyId = 0;
$propertyId = 0;
$actorUserId = null;

if ($currentUser) {
    $companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
    $actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
} else {
    if ($tokenExpected === '' || $tokenProvided === '' || !hash_equals($tokenExpected, $tokenProvided)) {
        http_response_code(403);
        echo json_encode(array(
            'ok' => false,
            'error' => 'Forbidden'
        ));
        exit;
    }

    $companyCode = trim((string)(isset($_GET['company_code']) ? $_GET['company_code'] : ''));
    if ($companyCode === '') {
        http_response_code(400);
        echo json_encode(array(
            'ok' => false,
            'error' => 'company_code is required when using token auth'
        ));
        exit;
    }

    $db = pms_get_connection();
    $stmtCompany = $db->prepare(
        'SELECT id_company
         FROM company
         WHERE code = ?
           AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmtCompany->execute(array($companyCode));
    $companyId = (int)$stmtCompany->fetchColumn();
    if ($companyId <= 0) {
        http_response_code(404);
        echo json_encode(array(
            'ok' => false,
            'error' => 'Company not found for company_code'
        ));
        exit;
    }

    $propertyCode = trim((string)(isset($_GET['property_code']) ? $_GET['property_code'] : ''));
    if ($propertyCode !== '') {
        $propertyId = (int)pms_lookup_property_id_for_company($companyId, $propertyCode);
    }
}

if ($companyId <= 0) {
    http_response_code(400);
    echo json_encode(array(
        'ok' => false,
        'error' => 'Unable to resolve company context'
    ));
    exit;
}

try {
    $db = pms_get_connection();
    if ($propertyId <= 0) {
        $propertyCode = trim((string)(isset($_GET['property_code']) ? $_GET['property_code'] : ''));
        if ($propertyCode !== '') {
            $propertyId = (int)pms_lookup_property_id_for_company($companyId, $propertyCode);
        }
    }
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $result = pms_ota_ical_sync_due_feeds($db, $companyId, $propertyId, $actorUserId, $limit);

    echo json_encode(array(
        'ok' => true,
        'company_id' => $companyId,
        'property_id' => $propertyId > 0 ? $propertyId : null,
        'result' => $result
    ));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'error' => $e->getMessage()
    ));
}
