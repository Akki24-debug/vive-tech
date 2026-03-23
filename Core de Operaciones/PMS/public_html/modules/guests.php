<?php
$moduleKey = 'guests';
$currentUser = pms_current_user();
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : 0;

if ($companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('guests.view');
$canViewReservations = function_exists('pms_user_can') ? pms_user_can('reservations.view') : true;
$canViewMessages = function_exists('pms_user_can') ? pms_user_can('messages.view') : true;
$companyId = 0;
$propertiesByCode = array();
$allowedPropertyCodes = function_exists('pms_allowed_property_codes') ? pms_allowed_property_codes() : array();
$allowedPropertyCodeSet = array();
foreach ($allowedPropertyCodes as $allowedPropertyCode) {
    $allowedPropertyCode = strtoupper(trim((string)$allowedPropertyCode));
    if ($allowedPropertyCode !== '') {
        $allowedPropertyCodeSet[$allowedPropertyCode] = true;
    }
}
$allowedPropertyIds = array();

try {
    $pdo = pms_get_connection();
    $companyStmt = $pdo->prepare('SELECT id_company FROM company WHERE code = ? LIMIT 1');
    $companyStmt->execute(array($companyCode));
    $companyRow = $companyStmt->fetch();
    $companyId = $companyRow && isset($companyRow['id_company']) ? (int)$companyRow['id_company'] : 0;

    if ($companyId > 0) {
        foreach (pms_fetch_properties($companyId) as $propertyRow) {
            $propertyCode = strtoupper(trim((string)(isset($propertyRow['code']) ? $propertyRow['code'] : '')));
            if ($propertyCode === '') {
                continue;
            }
            $propertiesByCode[$propertyCode] = $propertyRow;
            $propertyId = isset($propertyRow['id_property']) ? (int)$propertyRow['id_property'] : 0;
            if ($propertyId > 0) {
                $allowedPropertyIds[] = $propertyId;
            }
        }
        $allowedPropertyIds = array_values(array_unique($allowedPropertyIds));
    }
} catch (Exception $e) {
    echo '<p class="error">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    return;
}

if ($companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa activa para huespedes.</p>';
    return;
}
if (!pms_is_owner_user() && empty($allowedPropertyIds)) {
    echo '<p class="error">No tienes propiedades asignadas para ver huespedes.</p>';
    return;
}

if (!function_exists('pms_guest_return_context')) {
    function pms_guest_return_context()
    {
        $returnView = '';
        if (isset($_POST['guest_return_view'])) {
            $returnView = trim((string)$_POST['guest_return_view']);
        } elseif (isset($_GET['guest_return_view'])) {
            $returnView = trim((string)$_GET['guest_return_view']);
        }

        $returnReservationId = 0;
        if (isset($_POST['guest_return_reservation_id'])) {
            $returnReservationId = (int)$_POST['guest_return_reservation_id'];
        } elseif (isset($_GET['guest_return_reservation_id'])) {
            $returnReservationId = (int)$_GET['guest_return_reservation_id'];
        }

        return array(
            'view' => strtolower($returnView),
            'reservation_id' => $returnReservationId,
        );
    }
}

if (!function_exists('pms_guest_return_url')) {
    function pms_guest_return_url(array $returnContext)
    {
        $returnView = isset($returnContext['view']) ? strtolower((string)$returnContext['view']) : '';
        $returnReservationId = isset($returnContext['reservation_id']) ? (int)$returnContext['reservation_id'] : 0;

        if ($returnView === 'reservations' && $returnReservationId > 0) {
            return 'index.php?view=reservations&open_reservation=' . $returnReservationId;
        }

        return '';
    }
}

if (!function_exists('pms_guest_render_return_hidden_fields')) {
    function pms_guest_render_return_hidden_fields(array $returnContext)
    {
        $returnView = isset($returnContext['view']) ? (string)$returnContext['view'] : '';
        $returnReservationId = isset($returnContext['reservation_id']) ? (int)$returnContext['reservation_id'] : 0;
        if ($returnView !== '') {
            echo '<input type="hidden" name="guest_return_view" value="' . htmlspecialchars($returnView, ENT_QUOTES, 'UTF-8') . '">';
        }
        if ($returnReservationId > 0) {
            echo '<input type="hidden" name="guest_return_reservation_id" value="' . $returnReservationId . '">';
        }
    }
}

if (!function_exists('pms_guest_render_filter_hidden_fields')) {
    function pms_guest_render_filter_hidden_fields(array $filters)
    {
        echo '<input type="hidden" name="guests_filter_search" value="' . htmlspecialchars(isset($filters['search']) ? (string)$filters['search'] : '', ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="guests_filter_only_active" value="' . (int)(isset($filters['only_active']) ? $filters['only_active'] : 0) . '">';
        echo '<input type="hidden" name="guests_filter_property_code" value="' . htmlspecialchars(isset($filters['property_code']) ? (string)$filters['property_code'] : '', ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="guests_filter_only_in_house" value="' . (int)(isset($filters['only_in_house']) ? $filters['only_in_house'] : 0) . '">';
    }
}

if (!function_exists('pms_guest_self_url')) {
    function pms_guest_self_url($guestId, array $filters, array $returnContext = array())
    {
        $params = array('view' => 'guests');
        if ((int)$guestId > 0) {
            $params['guest_id'] = (int)$guestId;
        }
        if (isset($filters['search']) && trim((string)$filters['search']) !== '') {
            $params['guests_filter_search'] = trim((string)$filters['search']);
        }
        if (!empty($filters['only_active'])) {
            $params['guests_filter_only_active'] = 1;
        }
        if (!empty($filters['property_code'])) {
            $params['guests_filter_property_code'] = (string)$filters['property_code'];
        }
        if (!empty($filters['only_in_house'])) {
            $params['guests_filter_only_in_house'] = 1;
        }
        if (!empty($returnContext['view'])) {
            $params['guest_return_view'] = (string)$returnContext['view'];
        }
        if (!empty($returnContext['reservation_id'])) {
            $params['guest_return_reservation_id'] = (int)$returnContext['reservation_id'];
        }
        return 'index.php?' . http_build_query($params);
    }
}

if (!function_exists('pms_guest_full_name')) {
    function pms_guest_full_name(array $row)
    {
        $parts = array(
            isset($row['names']) ? trim((string)$row['names']) : '',
            isset($row['last_name']) ? trim((string)$row['last_name']) : '',
            isset($row['maiden_name']) ? trim((string)$row['maiden_name']) : '',
        );
        $fullName = trim(implode(' ', array_filter($parts, function ($part) {
            return $part !== '';
        })));
        if ($fullName !== '') {
            return $fullName;
        }
        return isset($row['full_name']) ? trim((string)$row['full_name']) : '';
    }
}

if (!function_exists('pms_guest_initials')) {
    function pms_guest_initials($fullName)
    {
        $chunks = preg_split('/\s+/', trim((string)$fullName));
        if (!$chunks) {
            return 'H';
        }
        $initials = '';
        foreach ($chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $initials .= strtoupper(substr($chunk, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }
        return $initials !== '' ? $initials : 'H';
    }
}

if (!function_exists('pms_guest_date')) {
    function pms_guest_date($value)
    {
        $raw = trim((string)$value);
        if ($raw === '' || $raw === '0000-00-00') {
            return '';
        }
        $time = strtotime($raw);
        return $time ? date('d/m/Y', $time) : $raw;
    }
}

if (!function_exists('pms_guest_datetime')) {
    function pms_guest_datetime($value)
    {
        $raw = trim((string)$value);
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return '';
        }
        $time = strtotime($raw);
        return $time ? date('d/m/Y H:i', $time) : $raw;
    }
}

if (!function_exists('pms_guest_money')) {
    function pms_guest_money($cents, $currency)
    {
        $currencyCode = strtoupper(trim((string)$currency));
        if ($currencyCode === '') {
            $currencyCode = 'MXN';
        }
        return $currencyCode . ' ' . number_format(((int)$cents) / 100, 2);
    }
}

if (!function_exists('pms_guest_excerpt')) {
    function pms_guest_excerpt($text, $limit = 220)
    {
        $value = trim((string)$text);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($value, 0, (int)$limit, '...');
        }
        if (strlen($value) <= (int)$limit) {
            return $value;
        }
        return substr($value, 0, (int)$limit - 3) . '...';
    }
}

if (!function_exists('pms_guest_status_tone')) {
    function pms_guest_status_tone($status)
    {
        $normalized = strtolower(trim((string)$status));
        switch ($normalized) {
            case 'en casa':
                return 'is-inhouse';
            case 'confirmado':
            case 'apartado':
                return 'is-upcoming';
            case 'salida':
            case 'completed':
                return 'is-complete';
            case 'cancelada':
            case 'cancelled':
                return 'is-cancelled';
            case 'no-show':
            case 'no_show':
                return 'is-noshow';
            default:
                return 'is-neutral';
        }
    }
}

if (!function_exists('pms_guest_status_label')) {
    function pms_guest_status_label($status)
    {
        $label = trim((string)$status);
        return $label !== '' ? ucfirst($label) : 'Sin estatus';
    }
}

if (!function_exists('pms_guest_is_in_house_reservation')) {
    function pms_guest_is_in_house_reservation(array $reservation, $todayDate)
    {
        $status = strtolower(trim((string)(isset($reservation['status']) ? $reservation['status'] : '')));
        if ($status === 'en casa') {
            return true;
        }
        $checkIn = isset($reservation['check_in_date']) ? trim((string)$reservation['check_in_date']) : '';
        $checkOut = isset($reservation['check_out_date']) ? trim((string)$reservation['check_out_date']) : '';
        if ($checkIn === '' || $checkOut === '') {
            return false;
        }
        if (!in_array($status, array('confirmado', 'apartado', 'en casa', 'salida'), true)) {
            return false;
        }
        return $todayDate >= $checkIn && $todayDate <= $checkOut;
    }
}

$filters = array(
    'search' => isset($_REQUEST['guests_filter_search']) ? (string)$_REQUEST['guests_filter_search'] : '',
    'only_active' => isset($_REQUEST['guests_filter_only_active']) ? (int)$_REQUEST['guests_filter_only_active'] : 1,
    'property_code' => isset($_REQUEST['guests_filter_property_code']) ? strtoupper(trim((string)$_REQUEST['guests_filter_property_code'])) : '',
    'only_in_house' => isset($_REQUEST['guests_filter_only_in_house']) ? (int)$_REQUEST['guests_filter_only_in_house'] : 0,
);
$phoneCountries = function_exists('pms_phone_country_rows') ? pms_phone_country_rows() : array();
$defaultPhonePrefix = function_exists('pms_phone_prefix_default') ? pms_phone_prefix_default() : '+52';
$phonePrefixDialMap = function_exists('pms_phone_prefix_dials_map') ? pms_phone_prefix_dials_map() : array($defaultPhonePrefix => true);
if ($filters['property_code'] !== '' && !isset($propertiesByCode[$filters['property_code']])) {
    $filters['property_code'] = '';
}

$selectedGuestId = isset($_POST['selected_guest_id']) ? (int)$_POST['selected_guest_id'] : 0;
if (isset($_GET['guest_id'])) {
    $selectedGuestId = (int)$_GET['guest_id'];
}
$isGetRequest = isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'GET';
if ($isGetRequest && $selectedGuestId > 0) {
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'guest:' . $selectedGuestId;
    $_POST[$moduleKey . '_current_subtab'] = 'dynamic:guest:' . $selectedGuestId;
}
$subtabState = pms_subtabs_init($moduleKey, 'static:general');
$returnContext = pms_guest_return_context();
$returnUrl = pms_guest_return_url($returnContext);
$redirectAfterSaveUrl = '';

$message = null;
$error = null;

$action = isset($_POST['guests_action']) ? (string)$_POST['guests_action'] : '';
if ($action === 'new_guest') {
    pms_require_permission('guests.create');
} elseif ($action === 'save_guest') {
    pms_require_permission($selectedGuestId > 0 ? 'guests.edit' : 'guests.create');
}
if ($action === 'new_guest') {
    $selectedGuestId = 0;
} elseif ($action === 'save_guest') {
    $email = isset($_POST['guest_email']) ? trim((string)$_POST['guest_email']) : '';
    $names = isset($_POST['guest_names']) ? trim((string)$_POST['guest_names']) : '';
    $lastName = isset($_POST['guest_last_name']) ? trim((string)$_POST['guest_last_name']) : '';
    $maidenName = isset($_POST['guest_maiden_name']) ? trim((string)$_POST['guest_maiden_name']) : '';
    $phoneRaw = isset($_POST['guest_phone']) ? trim((string)$_POST['guest_phone']) : '';
    $phonePrefixInput = isset($_POST['guest_phone_prefix']) ? trim((string)$_POST['guest_phone_prefix']) : '';
    if (function_exists('pms_phone_normalize_parts')) {
        $phoneParts = pms_phone_normalize_parts($phoneRaw, $phonePrefixInput, $defaultPhonePrefix);
        $phone = isset($phoneParts['full']) ? (string)$phoneParts['full'] : '';
    } else {
        $phonePrefix = function_exists('pms_phone_extract_dial')
            ? pms_phone_extract_dial($phonePrefixInput, $defaultPhonePrefix)
            : $defaultPhonePrefix;
        if ($phoneRaw !== '' && preg_match('/^(\+\d{1,4})\s*(.*)$/', $phoneRaw, $matches)) {
            $candidatePrefix = isset($matches[1]) ? (string)$matches[1] : '';
            if ($candidatePrefix !== '' && isset($phonePrefixDialMap[$candidatePrefix])) {
                $phonePrefix = $candidatePrefix;
                $phoneRaw = trim((string)$matches[2]);
            }
        }
        $phone = $phoneRaw === '' ? '' : trim($phonePrefix . ' ' . $phoneRaw);
    }
    $language = isset($_POST['guest_language']) ? trim((string)$_POST['guest_language']) : '';
    $marketing = isset($_POST['guest_marketing']) ? 1 : 0;
    $blacklisted = isset($_POST['guest_blacklisted']) ? 1 : 0;
    $notes = isset($_POST['guest_notes']) ? trim((string)$_POST['guest_notes']) : '';

    if ($names === '') {
        $error = 'Nombre es obligatorio.';
    } else {
        try {
            if ($selectedGuestId > 0) {
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare(
                    'UPDATE guest
                     SET
                       email = NULLIF(?, \'\'),
                       phone = NULLIF(?, \'\'),
                       names = ?,
                       last_name = NULLIF(?, \'\'),
                       maiden_name = NULLIF(?, \'\'),
                       full_name = TRIM(CONCAT(?, \' \', COALESCE(NULLIF(?, \'\'), \'\'), \' \', COALESCE(NULLIF(?, \'\'), \'\'))),
                       language = COALESCE(NULLIF(?, \'\'), \'es\'),
                       marketing_opt_in = ?,
                       blacklisted = ?,
                       notes_internal = NULLIF(?, \'\'),
                       updated_at = NOW()
                     WHERE id_guest = ?'
                );
                $stmt->execute(array(
                    $email,
                    $phone,
                    $names,
                    $lastName,
                    $maidenName,
                    $names,
                    $lastName,
                    $maidenName,
                    $language,
                    $marketing,
                    $blacklisted,
                    $notes,
                    $selectedGuestId
                ));

                if ($stmt->rowCount() >= 0) {
                    $verify = $pdo->prepare('SELECT id_guest FROM guest WHERE id_guest = ? LIMIT 1');
                    $verify->execute(array($selectedGuestId));
                    $row = $verify->fetch();
                    if ($row && isset($row['id_guest'])) {
                        if ($returnUrl !== '') {
                            $redirectAfterSaveUrl = $returnUrl;
                            $message = 'Huesped guardado. Regresando...';
                        } else {
                            $message = 'Huesped guardado.';
                        }
                    } else {
                        $error = 'No se encontro el huesped para actualizar.';
                    }
                } else {
                    $error = 'No se pudo guardar el huesped.';
                }
            } else {
                $resultSets = pms_call_procedure('sp_guest_upsert', array(
                    $email,
                    $names,
                    $lastName === '' ? null : $lastName,
                    $maidenName === '' ? null : $maidenName,
                    $phone === '' ? null : $phone,
                    $language === '' ? null : $language,
                    $marketing,
                    $blacklisted,
                    $notes === '' ? null : $notes
                ));
                $row = isset($resultSets[0][0]) ? $resultSets[0][0] : null;
                if ($row && isset($row['id_guest'])) {
                    $selectedGuestId = (int)$row['id_guest'];
                    if ($returnUrl !== '') {
                        $redirectAfterSaveUrl = $returnUrl;
                        $message = 'Huesped guardado. Regresando...';
                    } else {
                        $message = 'Huesped guardado.';
                    }
                } else {
                    $error = 'No se pudo guardar el huesped.';
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

try {
    $sets = pms_call_procedure('sp_portal_guest_data', array(
        $companyCode,
        $filters['search'] === '' ? null : $filters['search'],
        $filters['only_active'],
        $selectedGuestId,
        $actorUserId
    ));
    $guestsList = isset($sets[0]) ? $sets[0] : array();
    $guestDetailSet = isset($sets[1]) ? $sets[1] : array();
    $guestReservations = isset($sets[2]) ? $sets[2] : array();
    $guestActivities = isset($sets[3]) ? $sets[3] : array();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
    $guestsList = array();
    $guestDetailSet = array();
    $guestReservations = array();
    $guestActivities = array();
}

$guestsList = array_values(array_filter($guestsList, function ($row) use ($allowedPropertyCodeSet) {
    if (pms_is_owner_user() || !$allowedPropertyCodeSet) {
        return true;
    }
    $propertyCode = strtoupper(trim((string)(isset($row['property_code']) ? $row['property_code'] : '')));
    if ($propertyCode === '') {
        return true;
    }
    return isset($allowedPropertyCodeSet[$propertyCode]);
}));

$guestDetail = $guestDetailSet && isset($guestDetailSet[0]['id_guest']) ? $guestDetailSet[0] : null;
$selectedGuestId = $guestDetail && isset($guestDetail['id_guest']) ? (int)$guestDetail['id_guest'] : $selectedGuestId;
$guestPhoneSource = $guestDetail ? (string)$guestDetail['phone'] : '';
$guestPhonePrefixSource = '';
if ($action === 'save_guest' && $error !== null) {
    $guestPhoneSource = isset($_POST['guest_phone']) ? trim((string)$_POST['guest_phone']) : $guestPhoneSource;
    $guestPhonePrefixSource = isset($_POST['guest_phone_prefix']) ? trim((string)$_POST['guest_phone_prefix']) : '';
}
if (function_exists('pms_phone_normalize_parts')) {
    $guestPhoneParts = pms_phone_normalize_parts($guestPhoneSource, $guestPhonePrefixSource, $defaultPhonePrefix);
    $guestPhonePrefixForm = isset($guestPhoneParts['prefix']) ? (string)$guestPhoneParts['prefix'] : $defaultPhonePrefix;
    $guestPhoneForm = isset($guestPhoneParts['phone']) ? (string)$guestPhoneParts['phone'] : '';
} else {
    $guestPhonePrefixForm = $defaultPhonePrefix;
    $guestPhoneForm = $guestPhoneSource;
}

$todayDate = date('Y-m-d');
$inHouseReservations = array();
$guestIds = array_values(array_unique(array_filter(array_map(function ($row) {
    return isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
}, $guestsList))));
$insightGuestIds = $guestIds;
if ($selectedGuestId > 0 && !in_array($selectedGuestId, $insightGuestIds, true)) {
    $insightGuestIds[] = $selectedGuestId;
}
$currentStayByGuest = array();
$latestStayByGuest = array();
$guestInterestsByGuest = array();
$guestMessageStatsByGuest = array();
$selectedGuestMessages = array();

if ($insightGuestIds) {
    try {
        $pdo = pms_get_connection();
        $placeholders = implode(',', array_fill(0, count($insightGuestIds), '?'));

        $stayStmt = $pdo->prepare(
            'SELECT
                r.id_guest,
                r.id_reservation,
                r.code AS reservation_code,
                r.status,
                r.source,
                r.check_in_date,
                r.check_out_date,
                r.total_price_cents,
                r.balance_due_cents,
                r.currency,
                pr.code AS property_code,
                pr.name AS property_name,
                rm.code AS room_code,
                rm.name AS room_name,
                rc.code AS category_code,
                rc.name AS category_name
             FROM reservation r
             JOIN property pr ON pr.id_property = r.id_property
             LEFT JOIN room rm ON rm.id_room = r.id_room
             LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
             WHERE r.id_guest IN (' . $placeholders . ')
               AND pr.id_company = ?
               AND pr.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')
               AND r.deleted_at IS NULL
             ORDER BY
               r.id_guest ASC,
               CASE WHEN r.status = \'en casa\' THEN 0 ELSE 1 END,
               r.check_out_date DESC,
               r.id_reservation DESC'
        );
        $stayStmt->execute(array_merge($insightGuestIds, array($companyId), $allowedPropertyIds));
        foreach ($stayStmt->fetchAll() as $stayRow) {
            $guestId = isset($stayRow['id_guest']) ? (int)$stayRow['id_guest'] : 0;
            if ($guestId <= 0) {
                continue;
            }
            if (!isset($latestStayByGuest[$guestId])) {
                $latestStayByGuest[$guestId] = $stayRow;
            }
            if (!isset($currentStayByGuest[$guestId]) && pms_guest_is_in_house_reservation($stayRow, $todayDate)) {
                $currentStayByGuest[$guestId] = $stayRow;
            }
        }

        $interestStmt = $pdo->prepare(
            'SELECT
                r.id_guest,
                COALESCE(NULLIF(lic.item_name, \'\'), CONCAT(\'Interes #\', ri.id_sale_item_catalog)) AS interest_name
             FROM reservation_interest ri
             JOIN reservation r ON r.id_reservation = ri.id_reservation
             JOIN property pr ON pr.id_property = r.id_property
             LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = ri.id_sale_item_catalog
             WHERE r.id_guest IN (' . $placeholders . ')
               AND pr.id_company = ?
               AND pr.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')
               AND r.deleted_at IS NULL
               AND ri.deleted_at IS NULL
               AND COALESCE(ri.is_active, 1) = 1
             ORDER BY interest_name ASC'
        );
        $interestStmt->execute(array_merge($insightGuestIds, array($companyId), $allowedPropertyIds));
        foreach ($interestStmt->fetchAll() as $interestRow) {
            $guestId = isset($interestRow['id_guest']) ? (int)$interestRow['id_guest'] : 0;
            $interestName = trim((string)(isset($interestRow['interest_name']) ? $interestRow['interest_name'] : ''));
            if ($guestId <= 0 || $interestName === '') {
                continue;
            }
            if (!isset($guestInterestsByGuest[$guestId])) {
                $guestInterestsByGuest[$guestId] = array();
            }
            if (!in_array($interestName, $guestInterestsByGuest[$guestId], true)) {
                $guestInterestsByGuest[$guestId][] = $interestName;
            }
        }

        $messageStmt = $pdo->prepare(
            'SELECT
                r.id_guest,
                COUNT(*) AS message_count,
                MAX(rml.sent_at) AS last_message_at
             FROM reservation_message_log rml
             JOIN reservation r ON r.id_reservation = rml.id_reservation
             JOIN property pr ON pr.id_property = r.id_property
             WHERE r.id_guest IN (' . $placeholders . ')
               AND pr.id_company = ?
               AND pr.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')
               AND r.deleted_at IS NULL
             GROUP BY r.id_guest'
        );
        $messageStmt->execute(array_merge($insightGuestIds, array($companyId), $allowedPropertyIds));
        foreach ($messageStmt->fetchAll() as $messageRow) {
            $guestId = isset($messageRow['id_guest']) ? (int)$messageRow['id_guest'] : 0;
            if ($guestId <= 0) {
                continue;
            }
            $guestMessageStatsByGuest[$guestId] = array(
                'message_count' => isset($messageRow['message_count']) ? (int)$messageRow['message_count'] : 0,
                'last_message_at' => isset($messageRow['last_message_at']) ? (string)$messageRow['last_message_at'] : '',
            );
        }

        if ($selectedGuestId > 0) {
            $selectedMessageStmt = $pdo->prepare(
                'SELECT
                    rml.id_reservation_message_log,
                    rml.sent_at,
                    rml.channel,
                    rml.message_title,
                    rml.message_body,
                    rml.sent_to_phone,
                    r.id_reservation,
                    r.code AS reservation_code,
                    pr.name AS property_name
                 FROM reservation_message_log rml
                 JOIN reservation r ON r.id_reservation = rml.id_reservation
                 JOIN property pr ON pr.id_property = r.id_property
                 WHERE r.id_guest = ?
                   AND pr.id_company = ?
                   AND pr.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')
                   AND r.deleted_at IS NULL
                 ORDER BY rml.sent_at DESC, rml.id_reservation_message_log DESC
                 LIMIT 24'
            );
            $selectedMessageStmt->execute(array_merge(array($selectedGuestId, $companyId), $allowedPropertyIds));
            $selectedGuestMessages = $selectedMessageStmt->fetchAll();
        }
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
}

try {
    $pdo = isset($pdo) ? $pdo : pms_get_connection();
    $inHouseSql = 'SELECT
            r.id_reservation,
            r.id_guest,
            r.code AS reservation_code,
            r.status,
            r.source,
            r.check_in_date,
            r.check_out_date,
            r.adults,
            r.children,
            r.total_price_cents,
            r.balance_due_cents,
            r.currency,
            pr.code AS property_code,
            pr.name AS property_name,
            rm.code AS room_code,
            rm.name AS room_name,
            rc.name AS category_name,
            g.full_name AS guest_full_name,
            g.names AS guest_names,
            g.last_name AS guest_last_name,
            g.maiden_name AS guest_maiden_name,
            g.email AS guest_email,
            g.phone AS guest_phone,
            g.is_active AS guest_is_active,
            g.blacklisted AS guest_blacklisted
        FROM reservation r
        JOIN property pr ON pr.id_property = r.id_property
        LEFT JOIN room rm ON rm.id_room = r.id_room
        LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
        LEFT JOIN guest g ON g.id_guest = r.id_guest
        WHERE pr.id_company = ?
          AND pr.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')
          AND r.deleted_at IS NULL
          AND r.status IN (\'apartado\', \'confirmado\', \'en casa\', \'salida\')
          AND r.check_in_date <= ?
          AND r.check_out_date >= ?';
    $inHouseParams = array_merge(array($companyId), $allowedPropertyIds, array($todayDate, $todayDate));
    if (!empty($filters['property_code'])) {
        $inHouseSql .= ' AND pr.code = ?';
        $inHouseParams[] = $filters['property_code'];
    }
    $inHouseSql .= ' ORDER BY pr.name ASC, r.check_out_date ASC, r.id_reservation DESC';
    $inHouseStmt = $pdo->prepare($inHouseSql);
    $inHouseStmt->execute($inHouseParams);
    $inHouseReservations = $inHouseStmt->fetchAll();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
}

foreach ($guestsList as $index => $row) {
    $guestId = isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
    $guestsList[$index]['_full_name'] = pms_guest_full_name($row);
    $guestsList[$index]['_current_stay'] = isset($currentStayByGuest[$guestId]) ? $currentStayByGuest[$guestId] : null;
    $guestsList[$index]['_latest_stay'] = isset($latestStayByGuest[$guestId]) ? $latestStayByGuest[$guestId] : null;
    $guestsList[$index]['_interests'] = isset($guestInterestsByGuest[$guestId]) ? $guestInterestsByGuest[$guestId] : array();
    $guestsList[$index]['_message_stats'] = isset($guestMessageStatsByGuest[$guestId]) ? $guestMessageStatsByGuest[$guestId] : array(
        'message_count' => 0,
        'last_message_at' => '',
    );
}
unset($row);

$visibleGuestsList = array();
foreach ($guestsList as $row) {
    $currentStay = isset($row['_current_stay']) ? $row['_current_stay'] : null;
    $latestStay = isset($row['_latest_stay']) ? $row['_latest_stay'] : null;
    if (!empty($filters['property_code'])) {
        $currentPropertyCode = $currentStay && isset($currentStay['property_code']) ? strtoupper(trim((string)$currentStay['property_code'])) : '';
        $latestPropertyCode = $latestStay && isset($latestStay['property_code']) ? strtoupper(trim((string)$latestStay['property_code'])) : '';
        $matchesProperty = ($currentPropertyCode === $filters['property_code']) || ($latestPropertyCode === $filters['property_code']);
        if (!$matchesProperty) {
            continue;
        }
    }
    if (!empty($filters['only_in_house']) && !$currentStay) {
        continue;
    }
    $visibleGuestsList[] = $row;
}

if ($selectedGuestId > 0) {
    $selectedGuestIsVisible = false;
    foreach ($visibleGuestsList as $visibleGuestRow) {
        if ((int)(isset($visibleGuestRow['id_guest']) ? $visibleGuestRow['id_guest'] : 0) === $selectedGuestId) {
            $selectedGuestIsVisible = true;
            break;
        }
    }
    if (!$selectedGuestIsVisible) {
        $selectedGuestId = 0;
        $guestDetail = null;
        $guestReservations = array();
        $guestActivities = array();
        $selectedGuestMessages = array();
    }
}

$guestSummaryStats = array(
    'total' => count($visibleGuestsList),
    'active' => 0,
    'in_house' => 0,
    'check_out_today' => 0,
    'with_messages' => 0,
    'properties_live' => 0,
);
$livePropertyCodes = array();
foreach ($visibleGuestsList as $row) {
    if (isset($row['is_active']) && (int)$row['is_active'] === 1) {
        $guestSummaryStats['active']++;
    }
    $messageCount = isset($row['_message_stats']['message_count']) ? (int)$row['_message_stats']['message_count'] : 0;
    if ($messageCount > 0) {
        $guestSummaryStats['with_messages']++;
    }
}
$inHouseGuests = array_map(function ($reservationRow) use ($guestInterestsByGuest, $guestMessageStatsByGuest) {
    $guestId = isset($reservationRow['id_guest']) ? (int)$reservationRow['id_guest'] : 0;
    $reservationRow['_full_name'] = pms_guest_full_name(array(
        'full_name' => isset($reservationRow['guest_full_name']) ? $reservationRow['guest_full_name'] : '',
        'names' => isset($reservationRow['guest_names']) ? $reservationRow['guest_names'] : '',
        'last_name' => isset($reservationRow['guest_last_name']) ? $reservationRow['guest_last_name'] : '',
        'maiden_name' => isset($reservationRow['guest_maiden_name']) ? $reservationRow['guest_maiden_name'] : '',
    ));
    $reservationRow['_interests'] = $guestId > 0 && isset($guestInterestsByGuest[$guestId]) ? $guestInterestsByGuest[$guestId] : array();
    $reservationRow['_message_stats'] = $guestId > 0 && isset($guestMessageStatsByGuest[$guestId]) ? $guestMessageStatsByGuest[$guestId] : array(
        'message_count' => 0,
        'last_message_at' => '',
    );
    $reservationRow['_current_stay'] = $reservationRow;
    $reservationRow['is_active'] = isset($reservationRow['guest_is_active']) ? (int)$reservationRow['guest_is_active'] : 0;
    $reservationRow['phone'] = isset($reservationRow['guest_phone']) ? (string)$reservationRow['guest_phone'] : '';
    $reservationRow['email'] = isset($reservationRow['guest_email']) ? (string)$reservationRow['guest_email'] : '';
    $reservationRow['reservation_count'] = 1;
    return $reservationRow;
}, $inHouseReservations);
foreach ($inHouseGuests as $row) {
    $guestSummaryStats['in_house']++;
    $propertyCode = isset($row['property_code']) ? (string)$row['property_code'] : '';
    if ($propertyCode !== '') {
        $livePropertyCodes[$propertyCode] = true;
    }
    if (isset($row['check_out_date']) && (string)$row['check_out_date'] === $todayDate) {
        $guestSummaryStats['check_out_today']++;
    }
}
$guestSummaryStats['properties_live'] = count($livePropertyCodes);

usort($inHouseGuests, function ($a, $b) {
    $propertyCompare = strcmp(
        isset($a['property_name']) ? (string)$a['property_name'] : '',
        isset($b['property_name']) ? (string)$b['property_name'] : ''
    );
    if ($propertyCompare !== 0) {
        return $propertyCompare;
    }
    $checkoutCompare = strcmp(
        isset($a['check_out_date']) ? (string)$a['check_out_date'] : '',
        isset($b['check_out_date']) ? (string)$b['check_out_date'] : ''
    );
    if ($checkoutCompare !== 0) {
        return $checkoutCompare;
    }
    return strcmp(
        isset($a['_full_name']) ? (string)$a['_full_name'] : '',
        isset($b['_full_name']) ? (string)$b['_full_name'] : ''
    );
});

$selectedGuestCurrentStay = $selectedGuestId > 0 && isset($currentStayByGuest[$selectedGuestId]) ? $currentStayByGuest[$selectedGuestId] : null;
$selectedGuestLatestStay = $selectedGuestId > 0 && isset($latestStayByGuest[$selectedGuestId]) ? $latestStayByGuest[$selectedGuestId] : null;
$selectedGuestInterests = $selectedGuestId > 0 && isset($guestInterestsByGuest[$selectedGuestId]) ? $guestInterestsByGuest[$selectedGuestId] : array();
$selectedGuestFullName = $guestDetail ? pms_guest_full_name($guestDetail) : '';
$guestDetailActiveTab = 'static:profile';
if (isset($_POST['guest_detail_current_tab'])) {
    $candidateGuestTab = trim((string)$_POST['guest_detail_current_tab']);
    if (in_array($candidateGuestTab, array('static:profile', 'static:stays', 'static:interests', 'static:messages', 'static:activities'), true)) {
        $guestDetailActiveTab = $candidateGuestTab;
    }
}

$showGuestCreateTab = $action === 'new_guest' || ($action === 'save_guest' && $selectedGuestId <= 0 && $error !== null);
$showGuestPanel = $selectedGuestId > 0 || $showGuestCreateTab;
$activeGuestTabTarget = '';
if ($selectedGuestId > 0) {
    $activeGuestTabTarget = 'guest:' . $selectedGuestId;
} elseif ($showGuestCreateTab) {
    $activeGuestTabTarget = 'guest:new';
}

if ($activeGuestTabTarget !== '') {
    $subtabState['open'] = array($activeGuestTabTarget);
    $subtabState['active'] = 'dynamic:' . $activeGuestTabTarget;
} else {
    $subtabState['open'] = array();
    $subtabState['active'] = 'static:general';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['pms_subtabs'])) {
    $_SESSION['pms_subtabs'] = array();
}
$_SESSION['pms_subtabs'][$moduleKey] = $subtabState;

if ($redirectAfterSaveUrl !== '') {
    echo '<script>window.location.replace(' . json_encode($redirectAfterSaveUrl) . ');</script>';
}
?>
<style>
.guest-hub { display: grid; gap: 18px; }
.guest-toolbar-card,
.guest-live-section,
.guest-directory-section,
.guest-profile-card,
.guest-stays-section,
.guest-insights-grid > .card,
.guest-activities-section {
  border: 1px solid rgba(71, 85, 105, 0.45);
  background:
    radial-gradient(circle at top right, rgba(14, 165, 233, 0.08), transparent 28%),
    linear-gradient(180deg, rgba(15, 23, 42, 0.98) 0%, rgba(15, 23, 42, 0.92) 100%);
}
.guest-toolbar-card { padding: 18px; }
.guest-toolbar-head,
.guest-section-head,
.guest-stay-card-head,
.guest-message-card-head,
.guest-activity-card-head,
.guest-card-foot,
.guest-card-head {
  display: flex;
  gap: 12px;
}
.guest-toolbar-head,
.guest-section-head,
.guest-stay-card-head,
.guest-message-card-head,
.guest-activity-card-head,
.guest-card-foot {
  justify-content: space-between;
  align-items: flex-start;
}
.guest-toolbar-head { margin-bottom: 16px; }
.guest-toolbar-head h2,
.guest-section-head h2,
.guest-profile-summary h2,
.guest-profile-form h2 { margin: 0 0 4px; font-size: 1.1rem; }
.guest-toolbar-head p,
.guest-section-head p,
.guest-profile-summary p,
.guest-card-title p,
.guest-stay-card p,
.guest-message-card p,
.guest-activity-card p { margin: 0; color: #94a3b8; }
.guest-inline-note { color: #38bdf8; font-size: 0.82rem; font-weight: 700; }
.guest-toolbar-row,
.guest-profile-shell,
.guest-insights-grid { display: grid; gap: 18px; }
.guest-toolbar-row { grid-template-columns: minmax(280px, 1.2fr) minmax(220px, 0.8fr); }
.guest-toolbar-form { display: grid; grid-template-columns: minmax(220px, 1.2fr) minmax(180px, 0.9fr) auto auto auto; gap: 12px; align-items: end; }
.guest-toolbar-form label,
.guest-profile-form label { display: grid; gap: 6px; color: #cbd5e1; }
.guest-toolbar-actions { display: flex; justify-content: flex-end; align-items: end; }
.guest-toolbar-actions form,
.guest-toolbar-form { margin: 0; }
.guest-stats-grid,
.guest-live-grid,
.guest-directory-grid,
.guest-stays-grid,
.guest-activities-grid { display: grid; gap: 14px; }
.guest-stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 14px; }
.guest-stat-card,
.guest-live-card,
.guest-directory-card-item,
.guest-stay-card,
.guest-activity-card,
.guest-message-card,
.guest-profile-summary,
.guest-profile-form,
.guest-empty-state,
.guest-info-item {
  border: 1px solid rgba(51, 65, 85, 0.72);
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.72);
}
.guest-stat-card { padding: 14px 16px; }
.guest-stat-card span { display: block; color: #94a3b8; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; }
.guest-stat-card strong { display: block; margin-top: 6px; font-size: 1.5rem; color: #f8fafc; }
.guest-live-grid,
.guest-directory-grid,
.guest-stays-grid,
.guest-activities-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.guest-live-card,
.guest-directory-card-item,
.guest-stay-card,
.guest-activity-card,
.guest-message-card { padding: 16px; box-shadow: 0 14px 30px rgba(2, 6, 23, 0.24); }
.guest-live-card.is-inhouse,
.guest-stay-card.is-inhouse {
  border-color: rgba(34, 197, 94, 0.52);
  background:
    linear-gradient(135deg, rgba(34, 197, 94, 0.12), transparent 40%),
    rgba(15, 23, 42, 0.8);
}
.guest-avatar {
  width: 48px; height: 48px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(56, 189, 248, 0.35), rgba(37, 99, 235, 0.25));
  color: #f8fafc; font-weight: 800; letter-spacing: 0.04em; flex: 0 0 auto;
}
.guest-card-title { min-width: 0; flex: 1 1 auto; }
.guest-card-title h3,
.guest-directory-card-item h3,
.guest-stay-card h3,
.guest-activity-card h3,
.guest-message-card h3 { margin: 0; font-size: 1rem; color: #f8fafc; }
.guest-badges,
.guest-interest-chips,
.guest-quick-links,
.guest-meta-grid,
.guest-contact-list,
.guest-property-list,
.guest-info-list { display: flex; flex-wrap: wrap; gap: 8px; }
.guest-meta-grid,
.guest-contact-list,
.guest-property-list,
.guest-interest-chips,
.guest-info-list { margin-top: 14px; }
.guest-contact-list,
.guest-property-list,
.guest-info-list { display: grid; }
.guest-badge,
.guest-interest-chip,
.guest-quick-link,
.guest-data-pill {
  display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 10px;
  font-size: 0.74rem; font-weight: 700; line-height: 1.1;
}
.guest-badge { border: 1px solid rgba(96, 165, 250, 0.28); background: rgba(30, 41, 59, 0.88); color: #dbeafe; }
.guest-badge.is-inhouse { border-color: rgba(34, 197, 94, 0.42); background: rgba(20, 83, 45, 0.65); color: #bbf7d0; }
.guest-badge.is-upcoming { border-color: rgba(56, 189, 248, 0.42); background: rgba(12, 74, 110, 0.58); color: #bae6fd; }
.guest-badge.is-complete,
.guest-badge.is-neutral,
.guest-badge.is-muted { border-color: rgba(148, 163, 184, 0.38); background: rgba(30, 41, 59, 0.82); color: #cbd5e1; }
.guest-badge.is-cancelled,
.guest-badge.is-noshow,
.guest-badge.is-danger { border-color: rgba(248, 113, 113, 0.45); background: rgba(127, 29, 29, 0.55); color: #fecaca; }
.guest-badge.is-active { color: #bbf7d0; background: rgba(20, 83, 45, 0.58); border-color: rgba(34, 197, 94, 0.35); }
.guest-interest-chip { color: #e0f2fe; background: rgba(8, 47, 73, 0.75); border: 1px solid rgba(56, 189, 248, 0.24); }
.guest-quick-link {
  color: #e2e8f0;
  background: rgba(30, 41, 59, 0.9);
  border: 1px solid rgba(96, 165, 250, 0.2);
  text-decoration: none;
  cursor: pointer;
}
.guest-quick-link:hover { color: #f8fafc; border-color: rgba(56, 189, 248, 0.55); background: rgba(12, 74, 110, 0.62); }
.guest-data-pill { color: #e2e8f0; background: rgba(30, 41, 59, 0.74); border: 1px solid rgba(51, 65, 85, 0.72); }
.guest-data-pill strong { margin-left: 6px; color: #f8fafc; }
.guest-contact-list a,
.guest-property-list a { color: #dbeafe; text-decoration: none; }
.guest-contact-list a:hover,
.guest-property-list a:hover { color: #f8fafc; }
.guest-directory-card-item.is-selected { border-color: rgba(56, 189, 248, 0.65); box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.42), 0 14px 30px rgba(2, 6, 23, 0.28); }
.guest-profile-shell { grid-template-columns: minmax(280px, 0.9fr) minmax(340px, 1.1fr); }
.guest-profile-summary,
.guest-profile-form { padding: 18px; border-radius: 18px; }
.guest-profile-summary { background: radial-gradient(circle at top left, rgba(56, 189, 248, 0.12), transparent 35%), rgba(15, 23, 42, 0.86); }
.guest-profile-form { background: rgba(15, 23, 42, 0.7); }
.guest-info-item { padding: 12px 14px; }
.guest-info-item span { display: block; color: #94a3b8; font-size: 0.76rem; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.06em; }
.guest-info-item strong { color: #f8fafc; font-size: 0.96rem; }
.guest-insights-grid { grid-template-columns: minmax(240px, 0.75fr) minmax(280px, 1.25fr); }
.guest-message-list { display: grid; gap: 12px; }
.guest-message-excerpt { margin-top: 10px; color: #cbd5e1; line-height: 1.45; }
.guest-empty-state { padding: 18px; color: #94a3b8; border-style: dashed; background: rgba(15, 23, 42, 0.52); }
.guest-top-subtabs { margin-bottom: 18px; }
.guest-top-subtabs .subtabs-nav { margin-bottom: 0; }
.guest-top-subtabs .subtab-trigger.is-disabled { opacity: 1; cursor: default; }
.subtabs[data-module="guest-detail"] .subtabs-nav { display: none; }
.subtabs[data-module="guest-detail"] .subtabs-panels { display: grid; gap: 18px; }
.subtabs[data-module="guest-detail"] .subtab-panel { display: block !important; padding: 0; border: 0; background: transparent; }
@media (max-width: 1100px) {
  .guest-toolbar-row, .guest-profile-shell, .guest-insights-grid { grid-template-columns: 1fr; }
  .guest-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .guest-toolbar-form { grid-template-columns: 1fr; }
  .guest-stats-grid,
  .guest-live-grid,
  .guest-directory-grid,
  .guest-stays-grid,
  .guest-activities-grid { grid-template-columns: 1fr; }
  .guest-toolbar-head,
  .guest-section-head,
  .guest-stay-card-head,
  .guest-message-card-head,
  .guest-activity-card-head,
  .guest-card-foot { flex-direction: column; align-items: stretch; }
}
</style>

<div class="guest-top-subtabs">
  <div class="subtabs-nav">
    <a class="subtab-trigger<?php echo !$showGuestPanel ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars(pms_guest_self_url(0, $filters, $returnContext), ENT_QUOTES, 'UTF-8'); ?>">General</a>
    <?php if ($selectedGuestId > 0): ?>
      <a class="subtab-trigger is-active is-dynamic" href="<?php echo htmlspecialchars(pms_guest_self_url($selectedGuestId, $filters, $returnContext), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($selectedGuestFullName !== '' ? $selectedGuestFullName : 'Huesped', ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php elseif ($showGuestCreateTab): ?>
      <span class="subtab-trigger is-active is-dynamic is-disabled">Nuevo huesped</span>
    <?php endif; ?>
  </div>
</div>

<?php if (!$showGuestPanel): ?>
<div class="guest-hub">
  <section class="card guest-toolbar-card">
    <div class="guest-toolbar-head">
      <div>
        <h2>Hub de huespedes</h2>
        <p>Empieza por quienes estan hospedados ahora y luego explora el directorio completo con contexto util.</p>
      </div>
      <div class="guest-inline-note"><?php echo $guestSummaryStats['in_house']; ?> hospedados ahora</div>
    </div>
    <div class="guest-toolbar-row">
      <form method="post" class="guest-toolbar-form">
        <?php pms_guest_render_return_hidden_fields($returnContext); ?>
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="static:general">
        <input type="hidden" name="selected_guest_id" value="0">
        <label>
          Buscar huesped
          <input type="text" name="guests_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre, telefono o correo">
        </label>
        <label>
          Propiedad
          <select name="guests_filter_property_code">
            <option value="">Todas</option>
            <?php foreach ($propertiesByCode as $propertyCode => $propertyRow): ?>
              <option value="<?php echo htmlspecialchars($propertyCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['property_code'] === $propertyCode ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)(isset($propertyRow['name']) ? $propertyRow['name'] : $propertyCode), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="checkbox">
          <input type="checkbox" name="guests_filter_only_active" value="1" <?php echo $filters['only_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
          Solo activos
        </label>
        <label class="checkbox">
          <input type="checkbox" name="guests_filter_only_in_house" value="1" <?php echo !empty($filters['only_in_house']) ? 'checked' : ''; ?> onchange="this.form.submit()">
          Solo en casa
        </label>
        <button type="submit">Actualizar vista</button>
      </form>
      <div class="guest-toolbar-actions">
        <form method="post">
          <?php pms_guest_render_return_hidden_fields($returnContext); ?>
          <?php pms_guest_render_filter_hidden_fields($filters); ?>
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:guest:new">
          <input type="hidden" name="guests_action" value="new_guest">
          <button type="submit">Nuevo huesped</button>
        </form>
      </div>
    </div>
    <div class="guest-stats-grid">
      <div class="guest-stat-card"><span>Huespedes visibles</span><strong><?php echo (int)$guestSummaryStats['total']; ?></strong></div>
      <div class="guest-stat-card"><span>Activos</span><strong><?php echo (int)$guestSummaryStats['active']; ?></strong></div>
      <div class="guest-stat-card"><span>Check-out hoy</span><strong><?php echo (int)$guestSummaryStats['check_out_today']; ?></strong></div>
      <div class="guest-stat-card"><span>Propiedades con huespedes</span><strong><?php echo (int)$guestSummaryStats['properties_live']; ?></strong></div>
    </div>
  </section>

  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php elseif ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <section class="card guest-live-section">
    <div class="guest-section-head">
      <div>
        <h2>Huespedes hospedados actualmente</h2>
        <p>Resumen rapido de quienes estan en casa, donde estan y lo ultimo util para operarlos.</p>
      </div>
      <div class="guest-inline-note">Actualizado al <?php echo htmlspecialchars(pms_guest_date($todayDate), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <?php if ($inHouseGuests): ?>
      <div class="guest-live-grid">
        <?php foreach ($inHouseGuests as $row):
          $guestId = isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
          $fullName = isset($row['_full_name']) ? (string)$row['_full_name'] : '';
          $currentStay = $row;
          $interests = isset($row['_interests']) ? $row['_interests'] : array();
          $messageStats = isset($row['_message_stats']) ? $row['_message_stats'] : array('message_count' => 0, 'last_message_at' => '');
          $guestUrl = $guestId > 0 ? pms_guest_self_url($guestId, $filters, $returnContext) : '';
        ?>
          <article class="guest-live-card is-inhouse">
            <div class="guest-card-head">
              <div class="guest-avatar"><?php echo htmlspecialchars(pms_guest_initials($fullName), ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="guest-card-title">
                <h3><?php echo htmlspecialchars($fullName !== '' ? $fullName : 'Huesped sin nombre', ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars((string)(isset($row['property_name']) ? $row['property_name'] : 'Propiedad sin nombre'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="guest-badges" style="margin-top:8px;">
                  <span class="guest-badge is-inhouse">En casa</span>
                  <?php if (isset($row['is_active']) && (int)$row['is_active'] === 1): ?><span class="guest-badge is-active">Activo</span><?php endif; ?>
                  <?php if ($guestId <= 0): ?><span class="guest-badge is-muted">Sin ficha vinculada</span><?php endif; ?>
                </div>
              </div>
            </div>
            <div class="guest-meta-grid">
              <?php if (!empty($row['room_code']) || !empty($row['room_name'])): ?><span class="guest-data-pill">Habitacion<strong><?php echo htmlspecialchars(trim((string)$row['room_code']) !== '' ? (string)$row['room_code'] : (string)$row['room_name'], ENT_QUOTES, 'UTF-8'); ?></strong></span><?php endif; ?>
              <?php if (!empty($row['category_name'])): ?><span class="guest-data-pill">Categoria<strong><?php echo htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8'); ?></strong></span><?php endif; ?>
              <?php if (!empty($row['reservation_code'])): ?><span class="guest-data-pill">Reserva<strong><?php echo htmlspecialchars((string)$row['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></strong></span><?php endif; ?>
              <span class="guest-data-pill">Sale<strong><?php echo htmlspecialchars(pms_guest_date(isset($row['check_out_date']) ? $row['check_out_date'] : ''), ENT_QUOTES, 'UTF-8'); ?></strong></span>
            </div>
            <div class="guest-contact-list">
              <?php if (!empty($row['phone'])): ?><a href="tel:<?php echo htmlspecialchars((string)$row['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$row['phone'], ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?>
              <?php if (!empty($row['email'])): ?><a href="mailto:<?php echo htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?>
            </div>
            <?php if ($interests): ?><div class="guest-interest-chips"><?php foreach (array_slice($interests, 0, 4) as $interestName): ?><span class="guest-interest-chip"><?php echo htmlspecialchars($interestName, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?></div><?php endif; ?>
            <div class="guest-card-foot">
              <div class="guest-badges">
                <span class="guest-badge"><?php echo (int)(isset($messageStats['message_count']) ? $messageStats['message_count'] : 0); ?> mensajes</span>
                <?php if (!empty($messageStats['last_message_at'])): ?><span class="guest-badge is-muted"><?php echo htmlspecialchars(pms_guest_datetime($messageStats['last_message_at']), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              </div>
              <div class="guest-quick-links">
                <?php if ($guestUrl !== ''): ?><a class="guest-quick-link" href="<?php echo htmlspecialchars($guestUrl, ENT_QUOTES, 'UTF-8'); ?>">Ficha</a><?php endif; ?>
                <?php if ($canViewReservations && !empty($row['id_reservation'])): ?><a class="guest-quick-link" href="index.php?view=reservations&open_reservation=<?php echo (int)$row['id_reservation']; ?>">Reserva</a><?php endif; ?>
                <?php if ($canViewMessages && !empty($row['id_reservation'])): ?><a class="guest-quick-link" href="index.php?view=messages&messages_selected_reservation_id=<?php echo (int)$row['id_reservation']; ?>">Mensajes</a><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="guest-empty-state">No hay huespedes en casa con los filtros actuales.</div>
    <?php endif; ?>
  </section>

  <section class="card guest-directory-section">
    <div class="guest-section-head">
      <div>
        <h2>Directorio general de huespedes</h2>
        <p>Mas contexto de contacto, estancias, intereses y actividad reciente sin entrar a cada ficha.</p>
      </div>
      <div class="guest-inline-note"><?php echo (int)$guestSummaryStats['with_messages']; ?> con mensajes registrados</div>
    </div>
    <?php if ($visibleGuestsList): ?>
      <div class="guest-directory-grid">
        <?php foreach ($visibleGuestsList as $row):
          $guestId = isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
          $fullName = isset($row['_full_name']) ? (string)$row['_full_name'] : '';
          $currentStay = isset($row['_current_stay']) ? $row['_current_stay'] : null;
          $latestStay = isset($row['_latest_stay']) ? $row['_latest_stay'] : null;
          $interests = isset($row['_interests']) ? $row['_interests'] : array();
          $messageStats = isset($row['_message_stats']) ? $row['_message_stats'] : array('message_count' => 0, 'last_message_at' => '');
          $isSelected = $guestId === $selectedGuestId;
          $guestUrl = $guestId > 0 ? pms_guest_self_url($guestId, $filters, $returnContext) : '';
        ?>
          <article class="guest-directory-card-item<?php echo $isSelected ? ' is-selected' : ''; ?>">
            <div class="guest-card-head">
              <div class="guest-avatar"><?php echo htmlspecialchars(pms_guest_initials($fullName), ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="guest-card-title">
                <h3><?php echo htmlspecialchars($fullName !== '' ? $fullName : 'Huesped sin nombre', ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars((string)(isset($row['email']) ? $row['email'] : 'Sin correo'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="guest-badges" style="margin-top:8px;">
                  <?php if ($currentStay): ?><span class="guest-badge is-inhouse">En casa</span><?php endif; ?>
                  <?php if (isset($row['is_active']) && (int)$row['is_active'] === 1): ?><span class="guest-badge is-active">Activo</span><?php else: ?><span class="guest-badge is-muted">Inactivo</span><?php endif; ?>
                </div>
              </div>
            </div>
            <div class="guest-contact-list">
              <?php if (!empty($row['phone'])): ?><a href="tel:<?php echo htmlspecialchars((string)$row['phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$row['phone'], ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?>
              <?php if (!empty($row['email'])): ?><a href="mailto:<?php echo htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?>
            </div>
            <div class="guest-meta-grid">
              <span class="guest-data-pill">Reservas<strong><?php echo isset($row['reservation_count']) ? (int)$row['reservation_count'] : 0; ?></strong></span>
              <span class="guest-data-pill">Ultima estancia<strong><?php echo htmlspecialchars(pms_guest_date(isset($row['last_check_out']) ? $row['last_check_out'] : ''), ENT_QUOTES, 'UTF-8'); ?></strong></span>
              <span class="guest-data-pill">Mensajes<strong><?php echo (int)(isset($messageStats['message_count']) ? $messageStats['message_count'] : 0); ?></strong></span>
            </div>
            <?php if ($currentStay || $latestStay): ?>
              <div class="guest-property-list">
                <?php if ($currentStay): ?><span>En casa en <strong><?php echo htmlspecialchars((string)$currentStay['property_name'], ENT_QUOTES, 'UTF-8'); ?></strong> hasta <?php echo htmlspecialchars(pms_guest_date($currentStay['check_out_date']), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php elseif ($latestStay): ?><span>Ultimo hospedaje: <strong><?php echo htmlspecialchars((string)$latestStay['property_name'], ENT_QUOTES, 'UTF-8'); ?></strong></span><?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($interests): ?><div class="guest-interest-chips"><?php foreach (array_slice($interests, 0, 3) as $interestName): ?><span class="guest-interest-chip"><?php echo htmlspecialchars($interestName, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?></div><?php endif; ?>
            <div class="guest-card-foot">
              <div class="guest-badges">
                <?php if (!empty($messageStats['last_message_at'])): ?><span class="guest-badge is-muted"><?php echo htmlspecialchars(pms_guest_datetime($messageStats['last_message_at']), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              </div>
              <div class="guest-quick-links">
                <?php if ($guestUrl !== ''): ?><a class="guest-quick-link" href="<?php echo htmlspecialchars($guestUrl, ENT_QUOTES, 'UTF-8'); ?>">Abrir ficha</a><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="guest-empty-state">No se encontraron huespedes con los filtros actuales.</div>
    <?php endif; ?>
  </section>

<?php endif; ?>
<?php if ($showGuestPanel): ?>
  <?php if ($selectedGuestId): ?>
    <div class="subtabs" data-module="guest-detail" data-active="<?php echo htmlspecialchars($guestDetailActiveTab, ENT_QUOTES, 'UTF-8'); ?>">
      <div class="subtabs-nav">
        <button type="button" class="subtab-trigger<?php echo $guestDetailActiveTab === 'static:general' ? ' is-active' : ''; ?>" data-target="static:general" data-static="1">General</button>
        <button type="button" class="subtab-trigger<?php echo $guestDetailActiveTab === 'static:stays' ? ' is-active' : ''; ?>" data-target="static:stays" data-static="1">Hospedajes</button>
        <button type="button" class="subtab-trigger<?php echo $guestDetailActiveTab === 'static:interests' ? ' is-active' : ''; ?>" data-target="static:interests" data-static="1">Intereses</button>
        <button type="button" class="subtab-trigger<?php echo $guestDetailActiveTab === 'static:messages' ? ' is-active' : ''; ?>" data-target="static:messages" data-static="1">Mensajes</button>
        <button type="button" class="subtab-trigger<?php echo $guestDetailActiveTab === 'static:activities' ? ' is-active' : ''; ?>" data-target="static:activities" data-static="1">Actividades</button>
      </div>
      <div class="subtabs-panels">
        <section class="subtab-panel<?php echo $guestDetailActiveTab === 'static:general' ? ' is-active' : ''; ?>" data-tab-key="static:general">
  <?php endif; ?>

  <section class="card guest-profile-card">
    <div class="guest-profile-shell">
      <div class="guest-profile-summary">
        <?php if ($selectedGuestId && $guestDetail): ?>
          <div class="guest-card-head">
            <div class="guest-avatar"><?php echo htmlspecialchars(pms_guest_initials($selectedGuestFullName), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="guest-card-title">
              <h2><?php echo htmlspecialchars($selectedGuestFullName !== '' ? $selectedGuestFullName : 'Huesped sin nombre', ENT_QUOTES, 'UTF-8'); ?></h2>
              <p><?php echo htmlspecialchars((string)(isset($guestDetail['email']) ? $guestDetail['email'] : 'Sin correo registrado'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          </div>
          <div class="guest-badges" style="margin-top:14px;">
            <?php if ($selectedGuestCurrentStay): ?><span class="guest-badge is-inhouse">En casa</span><?php endif; ?>
            <?php if (isset($guestDetail['is_active']) && (int)$guestDetail['is_active'] === 1): ?><span class="guest-badge is-active">Activo</span><?php else: ?><span class="guest-badge is-muted">Inactivo</span><?php endif; ?>
            <?php if (!empty($guestDetail['blacklisted'])): ?><span class="guest-badge is-danger">Lista negra</span><?php endif; ?>
            <?php if (!empty($guestDetail['marketing_opt_in'])): ?><span class="guest-badge">Acepta comunicaciones</span><?php endif; ?>
          </div>
          <div class="guest-meta-grid">
            <span class="guest-data-pill">Reservas<strong><?php echo count($guestReservations); ?></strong></span>
            <span class="guest-data-pill">Actividades<strong><?php echo count($guestActivities); ?></strong></span>
            <span class="guest-data-pill">Mensajes<strong><?php echo isset($guestMessageStatsByGuest[$selectedGuestId]['message_count']) ? (int)$guestMessageStatsByGuest[$selectedGuestId]['message_count'] : 0; ?></strong></span>
          </div>
          <div class="guest-info-list">
            <?php if ($selectedGuestCurrentStay): ?>
              <div class="guest-info-item">
                <span>Hospedaje actual</span>
                <strong><?php echo htmlspecialchars((string)$selectedGuestCurrentStay['property_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <p><?php echo htmlspecialchars(trim((string)($selectedGuestCurrentStay['room_code'] ?: $selectedGuestCurrentStay['category_name'])), ENT_QUOTES, 'UTF-8'); ?> / sale <?php echo htmlspecialchars(pms_guest_date($selectedGuestCurrentStay['check_out_date']), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            <?php elseif ($selectedGuestLatestStay): ?>
              <div class="guest-info-item">
                <span>Ultimo hospedaje</span>
                <strong><?php echo htmlspecialchars((string)$selectedGuestLatestStay['property_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <p><?php echo htmlspecialchars(pms_guest_date($selectedGuestLatestStay['check_in_date']) . ' - ' . pms_guest_date($selectedGuestLatestStay['check_out_date']), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            <?php endif; ?>
            <?php if (!empty($guestDetail['phone'])): ?>
              <div class="guest-info-item">
                <span>Telefono</span>
                <strong><?php echo htmlspecialchars((string)$guestDetail['phone'], ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
            <?php endif; ?>
            <?php if (!empty($guestDetail['nationality']) || !empty($guestDetail['country'])): ?>
              <div class="guest-info-item">
                <span>Origen</span>
                <strong><?php echo htmlspecialchars(trim((string)(isset($guestDetail['nationality']) ? $guestDetail['nationality'] : '') . ' ' . (isset($guestDetail['country']) ? $guestDetail['country'] : '')), ENT_QUOTES, 'UTF-8'); ?></strong>
              </div>
            <?php endif; ?>
            <div class="guest-info-item">
              <span>Idioma</span>
              <strong><?php echo htmlspecialchars((string)(isset($guestDetail['language']) && trim((string)$guestDetail['language']) !== '' ? $guestDetail['language'] : 'es'), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
          </div>
          <?php if ($selectedGuestInterests): ?>
            <div class="guest-interest-chips">
              <?php foreach ($selectedGuestInterests as $interestName): ?>
                <span class="guest-interest-chip"><?php echo htmlspecialchars($interestName, ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($selectedGuestCurrentStay || $selectedGuestLatestStay): ?>
            <?php $focusStay = $selectedGuestCurrentStay ? $selectedGuestCurrentStay : $selectedGuestLatestStay; ?>
            <div class="guest-quick-links" style="margin-top:14px;">
              <?php if ($canViewReservations && !empty($focusStay['id_reservation'])): ?><a class="guest-quick-link" href="index.php?view=reservations&open_reservation=<?php echo (int)$focusStay['id_reservation']; ?>">Abrir reservacion</a><?php endif; ?>
              <?php if ($canViewMessages && !empty($focusStay['id_reservation'])): ?><a class="guest-quick-link" href="index.php?view=messages&messages_selected_reservation_id=<?php echo (int)$focusStay['id_reservation']; ?>">Ver mensajes</a><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <h2>Nuevo huesped o ficha</h2>
          <p>Selecciona un huesped del directorio para ver su historial completo o crea uno nuevo desde aqui.</p>
          <div class="guest-meta-grid">
            <span class="guest-data-pill">Huespedes visibles<strong><?php echo (int)$guestSummaryStats['total']; ?></strong></span>
            <span class="guest-data-pill">En casa<strong><?php echo (int)$guestSummaryStats['in_house']; ?></strong></span>
          </div>
          <div class="guest-empty-state" style="margin-top:14px;">La ficha individual mostrara hospedajes, intereses y mensajes recientes cuando selecciones un huesped.</div>
        <?php endif; ?>
      </div>

      <div class="guest-profile-form">
        <h2><?php echo $selectedGuestId ? 'Editar huesped' : 'Nuevo huesped'; ?></h2>
        <form method="post" class="form-grid grid-2 guest-edit-form">
          <?php pms_guest_render_return_hidden_fields($returnContext); ?>
          <?php if ($activeGuestTabTarget !== ''): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars('dynamic:' . $activeGuestTabTarget, ENT_QUOTES, 'UTF-8'); ?>">
          <?php endif; ?>
          <input type="hidden" name="guests_action" value="save_guest">
          <input type="hidden" name="selected_guest_id" value="<?php echo (int)$selectedGuestId; ?>">
          <input type="hidden" name="guests_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="guests_filter_only_active" value="<?php echo (int)$filters['only_active']; ?>">
          <input type="hidden" name="guests_filter_property_code" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="guests_filter_only_in_house" value="<?php echo (int)$filters['only_in_house']; ?>">

          <label>
            Correo
            <input type="email" name="guest_email" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['email'] : '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>
            Nombre(s) *
            <input type="text" name="guest_names" required value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['names'] : '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>
            Apellido paterno
            <input type="text" name="guest_last_name" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['last_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>
            Apellido materno
            <input type="text" name="guest_maiden_name" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['maiden_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>
            Telefono
            <div class="phone-input">
              <select name="guest_phone_prefix" aria-label="Prefijo telefono huesped">
                <?php
                  $guestPrefixSelected = false;
                  foreach ($phoneCountries as $phoneCountry):
                      $prefix = isset($phoneCountry['dial']) ? (string)$phoneCountry['dial'] : '';
                      if ($prefix === '') {
                          continue;
                      }
                      $countryName = isset($phoneCountry['name_es']) ? (string)$phoneCountry['name_es'] : '';
                      $isSelected = (!$guestPrefixSelected && $prefix === $guestPhonePrefixForm);
                      if ($isSelected) {
                          $guestPrefixSelected = true;
                      }
                ?>
                  <option value="<?php echo htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($countryName . ' (' . $prefix . ')', ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="tel" name="guest_phone" value="<?php echo htmlspecialchars($guestPhoneForm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="WhatsApp">
            </div>
          </label>
          <label>
            Idioma
            <input type="text" name="guest_language" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['language'] : 'es', ENT_QUOTES, 'UTF-8'); ?>" placeholder="es">
          </label>
          <label class="checkbox">
            <input type="checkbox" name="guest_marketing" value="1" <?php echo $guestDetail && isset($guestDetail['marketing_opt_in']) && (int)$guestDetail['marketing_opt_in'] === 1 ? 'checked' : ''; ?>>
            Acepta comunicaciones
          </label>
          <label class="checkbox">
            <input type="checkbox" name="guest_blacklisted" value="1" <?php echo $guestDetail && isset($guestDetail['blacklisted']) && (int)$guestDetail['blacklisted'] === 1 ? 'checked' : ''; ?>>
            Lista negra
          </label>
          <label class="full">
            Notas internas
            <textarea name="guest_notes" rows="3"><?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['notes_internal'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
          </label>
          <label class="full">
            Notas para el huesped
            <textarea rows="2" disabled><?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['notes_guest'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
          </label>
          <div class="form-actions full">
            <button type="submit">Guardar huesped</button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <?php if ($selectedGuestId): ?>
        </section>

        <section class="subtab-panel<?php echo $guestDetailActiveTab === 'static:stays' ? ' is-active' : ''; ?>" data-tab-key="static:stays">
          <section class="card guest-stays-section">
            <div class="guest-section-head">
              <div>
                <h2>Hospedajes relacionados</h2>
                <p>Historial completo de estancias y reservaciones asociadas a este huesped.</p>
              </div>
              <div class="guest-inline-note"><?php echo count($guestReservations); ?> hospedajes</div>
            </div>
            <?php if ($guestReservations): ?>
              <div class="guest-stays-grid">
                <?php foreach ($guestReservations as $reservation):
                  $isInHouseStay = pms_guest_is_in_house_reservation($reservation, $todayDate);
                  $statusTone = pms_guest_status_tone(isset($reservation['status']) ? $reservation['status'] : '');
                ?>
                  <article class="guest-stay-card<?php echo $isInHouseStay ? ' is-inhouse' : ''; ?>">
                    <div class="guest-stay-card-head">
                      <div>
                        <h3><?php echo htmlspecialchars((string)$reservation['property_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars(trim((string)(isset($reservation['room_code']) ? $reservation['room_code'] : '') . ' ' . (isset($reservation['category_name']) ? $reservation['category_name'] : '')), ENT_QUOTES, 'UTF-8'); ?></p>
                      </div>
                      <span class="guest-badge <?php echo htmlspecialchars($statusTone, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(pms_guest_status_label(isset($reservation['status']) ? $reservation['status'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="guest-meta-grid">
                      <span class="guest-data-pill">Codigo<strong><?php echo htmlspecialchars((string)$reservation['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
                      <span class="guest-data-pill">Entrada<strong><?php echo htmlspecialchars(pms_guest_date((string)$reservation['check_in_date']), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                      <span class="guest-data-pill">Salida<strong><?php echo htmlspecialchars(pms_guest_date((string)$reservation['check_out_date']), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                      <span class="guest-data-pill">Huespedes<strong><?php echo (int)$reservation['adults']; ?> + <?php echo (int)$reservation['children']; ?></strong></span>
                      <span class="guest-data-pill">Total<strong><?php echo htmlspecialchars(pms_guest_money(isset($reservation['total_price_cents']) ? $reservation['total_price_cents'] : 0, isset($reservation['currency']) ? $reservation['currency'] : 'MXN'), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                      <span class="guest-data-pill">Balance<strong><?php echo htmlspecialchars(pms_guest_money(isset($reservation['balance_due_cents']) ? $reservation['balance_due_cents'] : 0, isset($reservation['currency']) ? $reservation['currency'] : 'MXN'), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                    </div>
                    <div class="guest-card-foot">
                      <div class="guest-badges">
                        <?php if (!empty($reservation['source'])): ?><span class="guest-badge"><?php echo htmlspecialchars((string)$reservation['source'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                        <?php if ($isInHouseStay): ?><span class="guest-badge is-inhouse">Actualmente en casa</span><?php endif; ?>
                      </div>
                      <div class="guest-quick-links">
                        <?php if ($canViewReservations): ?><a class="guest-quick-link" href="index.php?view=reservations&open_reservation=<?php echo (int)$reservation['id_reservation']; ?>">Abrir</a><?php endif; ?>
                        <?php if ($canViewMessages): ?><a class="guest-quick-link" href="index.php?view=messages&messages_selected_reservation_id=<?php echo (int)$reservation['id_reservation']; ?>">Mensajes</a><?php endif; ?>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="guest-empty-state">Este huesped no tiene hospedajes asociados.</div>
            <?php endif; ?>
          </section>
        </section>

        <section class="subtab-panel<?php echo $guestDetailActiveTab === 'static:interests' ? ' is-active' : ''; ?>" data-tab-key="static:interests">
          <section class="guest-insights-grid" style="grid-template-columns: 1fr;">
            <section class="card">
              <div class="guest-section-head">
                <div>
                  <h2>Intereses registrados</h2>
                  <p>Intereses o conceptos vinculados en sus reservaciones.</p>
                </div>
              </div>
              <?php if ($selectedGuestInterests): ?>
                <div class="guest-interest-chips">
                  <?php foreach ($selectedGuestInterests as $interestName): ?>
                    <span class="guest-interest-chip"><?php echo htmlspecialchars($interestName, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="guest-empty-state">No hay intereses registrados para este huesped.</div>
              <?php endif; ?>
            </section>
          </section>
        </section>

        <section class="subtab-panel<?php echo $guestDetailActiveTab === 'static:messages' ? ' is-active' : ''; ?>" data-tab-key="static:messages">
          <section class="guest-insights-grid" style="grid-template-columns: 1fr;">
            <section class="card">
              <div class="guest-section-head">
                <div>
                  <h2>Mensajes recientes</h2>
                  <p>Ultimos contactos enviados a este huesped desde el PMS.</p>
                </div>
              </div>
              <?php if ($selectedGuestMessages): ?>
                <div class="guest-message-list">
                  <?php foreach ($selectedGuestMessages as $messageRow): ?>
                    <article class="guest-message-card">
                      <div class="guest-message-card-head">
                        <div>
                          <h3><?php echo htmlspecialchars((string)$messageRow['message_title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                          <p><?php echo htmlspecialchars((string)$messageRow['property_name'], ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string)$messageRow['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <span class="guest-badge"><?php echo htmlspecialchars(pms_guest_datetime((string)$messageRow['sent_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                      </div>
                      <div class="guest-meta-grid">
                        <?php if (!empty($messageRow['channel'])): ?><span class="guest-data-pill">Canal<strong><?php echo htmlspecialchars((string)$messageRow['channel'], ENT_QUOTES, 'UTF-8'); ?></strong></span><?php endif; ?>
                        <?php if (!empty($messageRow['sent_to_phone'])): ?><span class="guest-data-pill">Destino<strong><?php echo htmlspecialchars((string)$messageRow['sent_to_phone'], ENT_QUOTES, 'UTF-8'); ?></strong></span><?php endif; ?>
                      </div>
                      <?php if (!empty($messageRow['message_body'])): ?>
                        <div class="guest-message-excerpt"><?php echo htmlspecialchars(pms_guest_excerpt((string)$messageRow['message_body'], 220), ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php endif; ?>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="guest-empty-state">No hay mensajes registrados para este huesped.</div>
              <?php endif; ?>
            </section>
          </section>
        </section>

        <section class="subtab-panel<?php echo $guestDetailActiveTab === 'static:activities' ? ' is-active' : ''; ?>" data-tab-key="static:activities">
          <section class="card guest-activities-section">
            <div class="guest-section-head">
              <div>
                <h2>Actividades reservadas</h2>
                <p>Experiencias y actividades vinculadas a las reservaciones del huesped.</p>
              </div>
            </div>
            <?php if ($guestActivities): ?>
              <div class="guest-activities-grid">
                <?php foreach ($guestActivities as $activity): ?>
                  <article class="guest-activity-card">
                    <div class="guest-activity-card-head">
                      <div>
                        <h3><?php echo htmlspecialchars((string)$activity['activity_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars((string)(isset($activity['property_name']) ? $activity['property_name'] : ''), ENT_QUOTES, 'UTF-8'); ?></p>
                      </div>
                      <span class="guest-badge <?php echo htmlspecialchars(pms_guest_status_tone(isset($activity['status']) ? $activity['status'] : ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(pms_guest_status_label(isset($activity['status']) ? $activity['status'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="guest-meta-grid">
                      <span class="guest-data-pill">Programada<strong><?php echo htmlspecialchars(pms_guest_datetime((string)$activity['scheduled_at']), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                      <span class="guest-data-pill">Participantes<strong><?php echo (int)$activity['num_adults']; ?> + <?php echo (int)$activity['num_children']; ?></strong></span>
                      <span class="guest-data-pill">Precio<strong><?php echo htmlspecialchars(pms_guest_money(isset($activity['price_cents']) ? $activity['price_cents'] : 0, isset($activity['currency']) ? $activity['currency'] : 'MXN'), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="guest-empty-state">Sin actividades asociadas.</div>
            <?php endif; ?>
          </section>
        </section>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
