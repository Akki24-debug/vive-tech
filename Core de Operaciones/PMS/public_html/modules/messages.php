<?php
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : 0;

if ($companyId <= 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

pms_require_permission('messages.view');

$canSendMessages = pms_user_can('messages.send');
$canEditTemplates = pms_user_can('messages.template_edit');
$pdo = pms_get_connection();
$properties = pms_fetch_properties($companyId);

function mhub_text($value)
{
    return trim((string)$value);
}

function mhub_phone($value)
{
    return preg_replace('/\D+/', '', (string)$value);
}

function mhub_date($value)
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00') {
        return '';
    }
    $time = strtotime($raw);
    return $time ? date('d/m/Y', $time) : $raw;
}

function mhub_datetime($value)
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return '';
    }
    $time = strtotime($raw);
    return $time ? date('d/m/Y H:i', $time) : $raw;
}

function mhub_money($cents, $currency)
{
    $currencyCode = strtoupper(trim((string)$currency));
    if ($currencyCode === '') {
        $currencyCode = 'MXN';
    }
    return $currencyCode . ' ' . number_format(((int)$cents) / 100, 2);
}

function mhub_categories()
{
    return array(
        'general' => 'General',
        'pre_arrival' => 'Pre llegada',
        'arrival' => 'Llegada',
        'stay' => 'Estancia',
        'checkout' => 'Salida',
        'services' => 'Servicios',
        'offers' => 'Ofertas',
        'follow_up' => 'Seguimiento'
    );
}

function mhub_tokens()
{
    return array(
        'Huesped' => array(
            '{{guest.names}}' => 'Nombres',
            '{{guest.last_name}}' => 'Apellido paterno',
            '{{guest.maiden_name}}' => 'Apellido materno',
            '{{guest.full_name}}' => 'Nombre completo',
            '{{guest.phone}}' => 'Telefono',
            '{{guest.email}}' => 'Email'
        ),
        'Reservacion' => array(
            '{{reservation.code}}' => 'Codigo',
            '{{reservation.status}}' => 'Estatus',
            '{{reservation.source}}' => 'Origen',
            '{{reservation.check_in_date}}' => 'Check-in',
            '{{reservation.check_out_date}}' => 'Check-out',
            '{{reservation.nights}}' => 'Noches',
            '{{reservation.total_price}}' => 'Total',
            '{{reservation.balance_due}}' => 'Balance'
        ),
        'Propiedad' => array(
            '{{property.code}}' => 'Codigo',
            '{{property.name}}' => 'Nombre',
            '{{property.phone}}' => 'Telefono',
            '{{property.email}}' => 'Email',
            '{{property.city}}' => 'Ciudad',
            '{{property.check_out_time}}' => 'Hora salida'
        ),
        'Habitacion' => array(
            '{{room.code}}' => 'Codigo',
            '{{room.name}}' => 'Nombre',
            '{{room.floor}}' => 'Piso',
            '{{room.building}}' => 'Edificio'
        ),
        'Categoria' => array(
            '{{roomcategory.code}}' => 'Codigo',
            '{{roomcategory.name}}' => 'Nombre'
        )
    );
}

function mhub_guest_name(array $row)
{
    $fullName = mhub_text(isset($row['guest_full_name']) ? $row['guest_full_name'] : '');
    if ($fullName !== '') {
        return $fullName;
    }
    return trim(
        mhub_text(isset($row['guest_names']) ? $row['guest_names'] : '') . ' ' .
        mhub_text(isset($row['guest_last_name']) ? $row['guest_last_name'] : '') . ' ' .
        mhub_text(isset($row['guest_maiden_name']) ? $row['guest_maiden_name'] : '')
    );
}

function mhub_context(array $row)
{
    $guestName = mhub_guest_name($row);
    $currency = mhub_text(isset($row['reservation_currency']) ? $row['reservation_currency'] : '');
    if ($currency === '') {
        $currency = mhub_text(isset($row['property_currency']) ? $row['property_currency'] : 'MXN');
    }

    $context = array(
        'guest.names' => mhub_text(isset($row['guest_names']) ? $row['guest_names'] : ''),
        'guest.last_name' => mhub_text(isset($row['guest_last_name']) ? $row['guest_last_name'] : ''),
        'guest.maiden_name' => mhub_text(isset($row['guest_maiden_name']) ? $row['guest_maiden_name'] : ''),
        'guest.full_name' => $guestName,
        'guest.phone' => mhub_text(isset($row['guest_phone']) ? $row['guest_phone'] : ''),
        'guest.email' => mhub_text(isset($row['guest_email']) ? $row['guest_email'] : ''),
        'reservation.code' => mhub_text(isset($row['reservation_code']) ? $row['reservation_code'] : ''),
        'reservation.status' => mhub_text(isset($row['reservation_status']) ? $row['reservation_status'] : ''),
        'reservation.source' => mhub_text(isset($row['reservation_source']) ? $row['reservation_source'] : ''),
        'reservation.check_in_date' => mhub_date(isset($row['check_in_date']) ? $row['check_in_date'] : ''),
        'reservation.check_out_date' => mhub_date(isset($row['check_out_date']) ? $row['check_out_date'] : ''),
        'reservation.nights' => (string)max(0, (int)(isset($row['nights']) ? $row['nights'] : 0)),
        'reservation.total_price' => mhub_money(isset($row['total_price_cents']) ? $row['total_price_cents'] : 0, $currency),
        'reservation.balance_due' => mhub_money(isset($row['balance_due_cents']) ? $row['balance_due_cents'] : 0, $currency),
        'property.code' => mhub_text(isset($row['property_code']) ? $row['property_code'] : ''),
        'property.name' => mhub_text(isset($row['property_name']) ? $row['property_name'] : ''),
        'property.phone' => mhub_text(isset($row['property_phone']) ? $row['property_phone'] : ''),
        'property.email' => mhub_text(isset($row['property_email']) ? $row['property_email'] : ''),
        'property.city' => mhub_text(isset($row['property_city']) ? $row['property_city'] : ''),
        'property.check_out_time' => mhub_text(isset($row['property_check_out_time']) ? $row['property_check_out_time'] : ''),
        'room.code' => mhub_text(isset($row['room_code']) ? $row['room_code'] : ''),
        'room.name' => mhub_text(isset($row['room_name']) ? $row['room_name'] : ''),
        'room.floor' => mhub_text(isset($row['room_floor']) ? $row['room_floor'] : ''),
        'room.building' => mhub_text(isset($row['room_building']) ? $row['room_building'] : ''),
        'roomcategory.code' => mhub_text(isset($row['category_code']) ? $row['category_code'] : ''),
        'roomcategory.name' => mhub_text(isset($row['category_name']) ? $row['category_name'] : '')
    );

    $context['guest_name'] = $context['guest.full_name'];
    $context['guest_phone'] = $context['guest.phone'];
    $context['property_name'] = $context['property.name'];
    $context['property_code'] = $context['property.code'];
    $context['check_in'] = $context['reservation.check_in_date'];
    $context['check_out'] = $context['reservation.check_out_date'];
    $context['reservation_code'] = $context['reservation.code'];
    $context['room_code'] = $context['room.code'];
    $context['category_name'] = $context['roomcategory.name'];

    return $context;
}

function mhub_render($text, array $context)
{
    return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($matches) use ($context) {
        $needle = strtolower(trim((string)$matches[1]));
        foreach ($context as $key => $value) {
            if (strtolower(trim((string)$key)) === $needle) {
                return (string)$value;
            }
        }
        return '';
    }, (string)$text);
}

function mhub_hidden($activeTab, $propertyCode, $search, $reservationId, $statusFilter = '', $phoneFilter = '', $pendingFilter = '', $checkInFrom = '', $checkInTo = '')
{
    echo '<input type="hidden" name="messages_active_tab" value="' . htmlspecialchars((string)$activeTab, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_filter_property" value="' . htmlspecialchars((string)$propertyCode, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_search" value="' . htmlspecialchars((string)$search, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_filter_status" value="' . htmlspecialchars((string)$statusFilter, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_filter_phone" value="' . htmlspecialchars((string)$phoneFilter, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_filter_pending" value="' . htmlspecialchars((string)$pendingFilter, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_filter_checkin_from" value="' . htmlspecialchars((string)$checkInFrom, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_filter_checkin_to" value="' . htmlspecialchars((string)$checkInTo, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="messages_selected_reservation_id" value="' . (int)$reservationId . '">';
}

$propertyCodeMap = array();
$allowedPropertyIds = array();
foreach ($properties as $property) {
    $propertyCodeMap[(string)$property['code']] = (int)$property['id_property'];
    $allowedPropertyIds[] = (int)$property['id_property'];
}
$allowedPropertyIds = array_values(array_unique(array_filter($allowedPropertyIds)));

$selectedPropertyCode = isset($_REQUEST['messages_filter_property']) ? trim((string)$_REQUEST['messages_filter_property']) : '';
if ($selectedPropertyCode !== '' && !isset($propertyCodeMap[$selectedPropertyCode])) {
    $selectedPropertyCode = '';
}
$searchTerm = isset($_REQUEST['messages_search']) ? trim((string)$_REQUEST['messages_search']) : '';
$selectedStatusFilter = isset($_REQUEST['messages_filter_status']) ? strtolower(trim((string)$_REQUEST['messages_filter_status'])) : '';
$selectedPhoneFilter = isset($_REQUEST['messages_filter_phone']) ? strtolower(trim((string)$_REQUEST['messages_filter_phone'])) : '';
$selectedPendingFilter = isset($_REQUEST['messages_filter_pending']) ? strtolower(trim((string)$_REQUEST['messages_filter_pending'])) : '';
$selectedCheckInFrom = isset($_REQUEST['messages_filter_checkin_from']) ? trim((string)$_REQUEST['messages_filter_checkin_from']) : '';
$selectedCheckInTo = isset($_REQUEST['messages_filter_checkin_to']) ? trim((string)$_REQUEST['messages_filter_checkin_to']) : '';
$allowedPhoneFilters = array('with_phone', 'without_phone');
if (!in_array($selectedPhoneFilter, $allowedPhoneFilters, true)) {
    $selectedPhoneFilter = '';
}
$allowedPendingFilters = array('pending_only', 'sent_only');
if (!in_array($selectedPendingFilter, $allowedPendingFilters, true)) {
    $selectedPendingFilter = '';
}
$selectedReservationId = isset($_REQUEST['messages_selected_reservation_id']) ? (int)$_REQUEST['messages_selected_reservation_id'] : 0;
$activeTab = isset($_REQUEST['messages_active_tab']) ? (string)$_REQUEST['messages_active_tab'] : 'messages-tab-dashboard';
if (!$canEditTemplates && $activeTab === 'messages-tab-templates') {
    $activeTab = 'messages-tab-dashboard';
}
$message = null;
$error = null;
$openWhatsappUrl = '';

$templateForm = array(
    'template_id' => 0,
    'template_property_code' => '',
    'template_code' => '',
    'template_title' => '',
    'template_body' => '',
    'template_category' => 'general',
    'template_sort_order' => 0,
    'template_channel' => 'whatsapp',
    'template_is_trackable' => 0,
    'template_is_required' => 0,
    'template_id_sale_item_catalog' => 0,
    'template_is_active' => 1
);

$templateSql = '
    SELECT
        mt.id_message_template,
        mt.id_property,
        mt.code,
        mt.title,
        mt.body,
        COALESCE(mt.category, "general") AS category,
        COALESCE(mt.sort_order, 0) AS sort_order,
        COALESCE(NULLIF(mt.channel, ""), "whatsapp") AS channel,
        COALESCE(mt.is_trackable, 0) AS is_trackable,
        COALESCE(mt.is_required, 0) AS is_required,
        mt.id_sale_item_catalog,
        mt.is_active,
        p.code AS property_code,
        p.name AS property_name,
        lic.item_name AS sale_item_name,
        cat.category_name AS sale_item_category_name
    FROM message_template mt
    LEFT JOIN property p ON p.id_property = mt.id_property
    LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = mt.id_sale_item_catalog
    LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = lic.id_category
    WHERE mt.id_company = ?
      AND mt.deleted_at IS NULL';
$templateParams = array($companyId);
if (!pms_is_owner_user()) {
    if ($allowedPropertyIds) {
        $templateSql .= ' AND (mt.id_property IS NULL OR mt.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . '))';
        foreach ($allowedPropertyIds as $allowedPropertyId) {
            $templateParams[] = (int)$allowedPropertyId;
        }
    } else {
        $templateSql .= ' AND mt.id_property IS NULL';
    }
}
$templateSql .= ' ORDER BY COALESCE(mt.category, "general"), COALESCE(mt.sort_order, 0), mt.title';
$stmt = $pdo->prepare($templateSql);
$stmt->execute($templateParams);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
$templateIndex = array();
foreach ($templates as $template) {
    $templateIndex[(int)$template['id_message_template']] = $template;
}

$action = isset($_POST['messages_action']) ? trim((string)$_POST['messages_action']) : '';
if ($action === 'save_template' && $canEditTemplates) {
    foreach ($templateForm as $key => $defaultValue) {
        if (isset($_POST[$key])) {
            $templateForm[$key] = is_int($defaultValue) ? (int)$_POST[$key] : trim((string)$_POST[$key]);
        }
    }
    $templateForm['template_is_trackable'] = isset($_POST['template_is_trackable']) ? 1 : 0;
    $templateForm['template_is_required'] = isset($_POST['template_is_required']) ? 1 : 0;
    $templateForm['template_is_active'] = isset($_POST['template_is_active']) ? 1 : 0;

    if ($templateForm['template_code'] === '' || $templateForm['template_title'] === '' || $templateForm['template_body'] === '') {
        $error = 'Codigo, titulo y cuerpo son obligatorios.';
    } else {
        try {
            pms_call_procedure('sp_message_template_upsert', array(
                $companyCode,
                $templateForm['template_property_code'] === '' ? null : $templateForm['template_property_code'],
                $templateForm['template_code'],
                $templateForm['template_title'],
                $templateForm['template_body'],
                $templateForm['template_category'],
                $templateForm['template_sort_order'],
                $templateForm['template_channel'],
                $templateForm['template_is_trackable'],
                $templateForm['template_is_required'],
                $templateForm['template_id_sale_item_catalog'] > 0 ? $templateForm['template_id_sale_item_catalog'] : null,
                $templateForm['template_is_active'],
                $actorUserId,
                $templateForm['template_id'] > 0 ? $templateForm['template_id'] : null
            ));
            $message = 'Plantilla guardada.';
            $activeTab = 'messages-tab-templates';
            $templateForm = array(
                'template_id' => 0,
                'template_property_code' => '',
                'template_code' => '',
                'template_title' => '',
                'template_body' => '',
                'template_category' => 'general',
                'template_sort_order' => 0,
                'template_channel' => 'whatsapp',
                'template_is_trackable' => 0,
                'template_is_required' => 0,
                'template_id_sale_item_catalog' => 0,
                'template_is_active' => 1
            );
            $stmt = $pdo->prepare($templateSql);
            $stmt->execute($templateParams);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $templateIndex = array();
            foreach ($templates as $template) {
                $templateIndex[(int)$template['id_message_template']] = $template;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'edit_template' && $canEditTemplates) {
    $editTemplateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    if ($editTemplateId > 0 && isset($templateIndex[$editTemplateId])) {
        $editRow = $templateIndex[$editTemplateId];
        $templateForm = array(
            'template_id' => (int)$editRow['id_message_template'],
            'template_property_code' => (string)$editRow['property_code'],
            'template_code' => (string)$editRow['code'],
            'template_title' => (string)$editRow['title'],
            'template_body' => (string)$editRow['body'],
            'template_category' => (string)$editRow['category'],
            'template_sort_order' => (int)$editRow['sort_order'],
            'template_channel' => (string)$editRow['channel'],
            'template_is_trackable' => (int)$editRow['is_trackable'],
            'template_is_required' => (int)$editRow['is_required'],
            'template_id_sale_item_catalog' => (int)$editRow['id_sale_item_catalog'],
            'template_is_active' => (int)$editRow['is_active']
        );
        $activeTab = 'messages-tab-templates';
    }
}

$reservationBaseSql = '
    SELECT
        r.id_reservation,
        r.code AS reservation_code,
        r.status AS reservation_status,
        r.source AS reservation_source,
        r.check_in_date,
        r.check_out_date,
        DATEDIFF(r.check_out_date, r.check_in_date) AS nights,
        r.currency AS reservation_currency,
        r.total_price_cents,
        r.balance_due_cents,
        r.id_property,
        g.names AS guest_names,
        g.last_name AS guest_last_name,
        g.maiden_name AS guest_maiden_name,
        g.full_name AS guest_full_name,
        g.phone AS guest_phone,
        g.email AS guest_email,
        p.code AS property_code,
        p.name AS property_name,
        p.phone AS property_phone,
        p.email AS property_email,
        p.city AS property_city,
        p.currency AS property_currency,
        TIME_FORMAT(p.check_out_time, "%H:%i") AS property_check_out_time,
        rm.code AS room_code,
        COALESCE(NULLIF(rm.name, ""), rm.code) AS room_name,
        rm.floor AS room_floor,
        rm.building AS room_building,
        rc.code AS category_code,
        rc.name AS category_name
    FROM reservation r
    JOIN property p
      ON p.id_property = r.id_property
     AND p.id_company = ?
     AND p.deleted_at IS NULL
    LEFT JOIN guest g ON g.id_guest = r.id_guest
    LEFT JOIN room rm ON rm.id_room = r.id_room
    LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
    WHERE r.deleted_at IS NULL
      AND COALESCE(r.is_active, 1) = 1
      AND LOWER(COALESCE(r.status, "")) NOT IN ("cancelada", "cancelled", "canceled", "cancelado")';
$reservationParams = array($companyId);
if ($selectedPropertyCode !== '') {
    $reservationBaseSql .= ' AND p.code = ?';
    $reservationParams[] = $selectedPropertyCode;
} elseif (!pms_is_owner_user()) {
    if ($allowedPropertyIds) {
        $reservationBaseSql .= ' AND p.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')';
        foreach ($allowedPropertyIds as $allowedPropertyId) {
            $reservationParams[] = (int)$allowedPropertyId;
        }
    } else {
        $reservationBaseSql .= ' AND 1 = 0';
    }
}
if ($searchTerm !== '') {
    $like = '%' . $searchTerm . '%';
    $reservationBaseSql .= ' AND (
        r.code LIKE ?
        OR COALESCE(g.full_name, "") LIKE ?
        OR COALESCE(g.names, "") LIKE ?
        OR COALESCE(g.phone, "") LIKE ?
        OR COALESCE(p.name, "") LIKE ?
        OR COALESCE(rm.code, "") LIKE ?
    )';
    array_push($reservationParams, $like, $like, $like, $like, $like, $like);
}
if ($selectedStatusFilter !== '') {
    $reservationBaseSql .= ' AND LOWER(COALESCE(r.status, "")) = ?';
    $reservationParams[] = $selectedStatusFilter;
}
if ($selectedPhoneFilter === 'with_phone') {
    $reservationBaseSql .= ' AND TRIM(COALESCE(g.phone, "")) <> ""';
} elseif ($selectedPhoneFilter === 'without_phone') {
    $reservationBaseSql .= ' AND TRIM(COALESCE(g.phone, "")) = ""';
}
if ($selectedCheckInFrom !== '') {
    $reservationBaseSql .= ' AND r.check_in_date >= ?';
    $reservationParams[] = $selectedCheckInFrom;
}
if ($selectedCheckInTo !== '') {
    $reservationBaseSql .= ' AND r.check_in_date <= ?';
    $reservationParams[] = $selectedCheckInTo;
}
$reservationBaseSql .= ' ORDER BY r.check_in_date ASC, r.id_reservation DESC LIMIT 600';
$stmt = $pdo->prepare($reservationBaseSql);
$stmt->execute($reservationParams);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$reservationIndex = array();
$reservationIds = array();
foreach ($reservations as $reservation) {
    $reservationIndex[(int)$reservation['id_reservation']] = $reservation;
    $reservationIds[] = (int)$reservation['id_reservation'];
}

if (($action === 'send_message' || $action === 'mark_message_sent') && $canSendMessages) {
    $selectedReservationId = isset($_POST['messages_selected_reservation_id']) ? (int)$_POST['messages_selected_reservation_id'] : $selectedReservationId;
    $templateId = isset($_POST['message_template_id']) ? (int)$_POST['message_template_id'] : 0;
    if (isset($reservationIndex[$selectedReservationId]) && isset($templateIndex[$templateId])) {
        $reservationRow = $reservationIndex[$selectedReservationId];
        $templateRow = $templateIndex[$templateId];
        $context = mhub_context($reservationRow);
        $renderedTitle = mhub_render((string)$templateRow['title'], $context);
        $renderedBody = mhub_render((string)$templateRow['body'], $context);
        $channel = $action === 'send_message' ? 'whatsapp' : 'manual';
        $phone = $channel === 'whatsapp' ? mhub_phone(isset($reservationRow['guest_phone']) ? $reservationRow['guest_phone'] : '') : null;
        if ($channel === 'whatsapp' && $phone === '') {
            $error = 'El huesped principal no tiene telefono registrado.';
        } else {
            try {
                pms_call_procedure('sp_reservation_message_send', array(
                    $companyCode,
                    $selectedReservationId,
                    $templateId,
                    $actorUserId,
                    $phone,
                    $renderedTitle,
                    $renderedBody,
                    $channel
                ));
                $message = $channel === 'whatsapp'
                    ? 'Mensaje registrado y listo para abrir en WhatsApp.'
                    : 'Mensaje marcado como enviado manualmente.';
                $activeTab = 'messages-tab-dashboard';
                if ($channel === 'whatsapp') {
                    $openWhatsappUrl = 'https://wa.me/' . rawurlencode($phone) . '?text=' . rawurlencode(trim($renderedTitle . "\n\n" . $renderedBody));
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        $error = 'Reservacion o plantilla no valida.';
    }
}

$statusMap = array();
$logSummaryMap = array();
if ($reservationIds) {
    $sql = 'SELECT * FROM reservation_message_status WHERE id_reservation IN (' . implode(',', array_fill(0, count($reservationIds), '?')) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($reservationIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statusMap[(int)$row['id_reservation']][(int)$row['id_message_template']] = $row;
    }

    $sql = 'SELECT id_reservation, id_message_template, COUNT(*) AS total_sent, MAX(sent_at) AS last_sent_at
            FROM reservation_message_log
            WHERE id_reservation IN (' . implode(',', array_fill(0, count($reservationIds), '?')) . ')
            GROUP BY id_reservation, id_message_template';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($reservationIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $logSummaryMap[(int)$row['id_reservation']][(int)$row['id_message_template']] = $row;
    }
}

$dashboardRows = array();
$pendingReservationCount = 0;
$pendingTemplateCount = 0;
$trackedSentCount = 0;
foreach ($reservations as $reservation) {
    $reservationId = (int)$reservation['id_reservation'];
    $propertyId = (int)$reservation['id_property'];
    $pending = 0;
    $sent = 0;
    $offers = 0;
    foreach ($templates as $template) {
        $templatePropertyId = (int)$template['id_property'];
        if ($templatePropertyId > 0 && $templatePropertyId !== $propertyId) {
            continue;
        }
        if ((int)$template['is_active'] !== 1) {
            continue;
        }
        $templateId = (int)$template['id_message_template'];
        $isTrackable = (int)$template['is_trackable'] === 1;
        $isRequired = (int)$template['is_required'] === 1;
        $isSent = isset($statusMap[$reservationId][$templateId]) && strtolower((string)$statusMap[$reservationId][$templateId]['tracking_status']) === 'sent';
        if ($isTrackable && $isSent) {
            $sent++;
        }
        if ($isTrackable && $isRequired && !$isSent) {
            $pending++;
        }
        if ((int)$template['id_sale_item_catalog'] > 0 && isset($logSummaryMap[$reservationId][$templateId])) {
            $offers++;
        }
    }
    $reservation['dashboard_pending_required'] = $pending;
    $reservation['dashboard_tracked_sent'] = $sent;
    $reservation['dashboard_offers_sent'] = $offers;
    $dashboardRows[] = $reservation;
    if ($pending > 0) {
        $pendingReservationCount++;
        $pendingTemplateCount += $pending;
    }
    $trackedSentCount += $sent;
}

if ($selectedPendingFilter !== '') {
    $dashboardRows = array_values(array_filter($dashboardRows, function ($row) use ($selectedPendingFilter) {
        $pending = isset($row['dashboard_pending_required']) ? (int)$row['dashboard_pending_required'] : 0;
        $sent = isset($row['dashboard_tracked_sent']) ? (int)$row['dashboard_tracked_sent'] : 0;
        if ($selectedPendingFilter === 'pending_only') {
            return $pending > 0;
        }
        if ($selectedPendingFilter === 'sent_only') {
            return $sent > 0;
        }
        return true;
    }));

    $pendingReservationCount = 0;
    $pendingTemplateCount = 0;
    $trackedSentCount = 0;
    foreach ($dashboardRows as $row) {
        $pending = isset($row['dashboard_pending_required']) ? (int)$row['dashboard_pending_required'] : 0;
        $sent = isset($row['dashboard_tracked_sent']) ? (int)$row['dashboard_tracked_sent'] : 0;
        if ($pending > 0) {
            $pendingReservationCount++;
            $pendingTemplateCount += $pending;
        }
        $trackedSentCount += $sent;
    }
}

usort($dashboardRows, function ($a, $b) {
    if ((int)$a['dashboard_pending_required'] !== (int)$b['dashboard_pending_required']) {
        return (int)$b['dashboard_pending_required'] <=> (int)$a['dashboard_pending_required'];
    }
    return strcmp((string)$a['check_in_date'], (string)$b['check_in_date']);
});

$selectedReservation = null;
foreach ($dashboardRows as $row) {
    if ((int)$row['id_reservation'] === $selectedReservationId) {
        $selectedReservation = $row;
    }
}
if (!$selectedReservation && $dashboardRows) {
    foreach ($dashboardRows as $row) {
        if ((int)$row['dashboard_pending_required'] > 0) {
            $selectedReservation = $row;
            break;
        }
    }
}
if (!$selectedReservation && $dashboardRows) {
    $selectedReservation = $dashboardRows[0];
}
if ($selectedReservation) {
    $selectedReservationId = (int)$selectedReservation['id_reservation'];
}

$selectedHistory = array();
$selectedInterests = array();
$selectedInterestMap = array();
$selectedTemplates = array();
if ($selectedReservation) {
    $stmt = $pdo->prepare(
        'SELECT
            rml.id_reservation_message_log,
            rml.id_message_template,
            rml.sent_at,
            rml.sent_to_phone,
            rml.message_title,
            rml.channel,
            mt.code AS template_code,
            au.display_name AS sent_by_name,
            au.email AS sent_by_email
         FROM reservation_message_log rml
         JOIN message_template mt ON mt.id_message_template = rml.id_message_template
         LEFT JOIN app_user au ON au.id_user = rml.sent_by
         WHERE rml.id_reservation = ?
         ORDER BY rml.sent_at DESC, rml.id_reservation_message_log DESC'
    );
    $stmt->execute(array($selectedReservationId));
    $selectedHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT
            ri.id_sale_item_catalog,
            ri.created_at,
            lic.item_name,
            cat.category_name
         FROM reservation_interest ri
         JOIN line_item_catalog lic
           ON lic.id_line_item_catalog = ri.id_sale_item_catalog
          AND lic.catalog_type = "sale_item"
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.id_company = ?
         WHERE ri.id_reservation = ?
           AND ri.deleted_at IS NULL
           AND ri.is_active = 1
         ORDER BY cat.category_name, lic.item_name'
    );
    $stmt->execute(array($companyId, $selectedReservationId));
    $selectedInterests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($selectedInterests as $interest) {
        $selectedInterestMap[(int)$interest['id_sale_item_catalog']] = $interest;
    }

    foreach ($templates as $template) {
        $templatePropertyId = (int)$template['id_property'];
        if ($templatePropertyId > 0 && $templatePropertyId !== (int)$selectedReservation['id_property']) {
            continue;
        }
        if ((int)$template['is_active'] !== 1) {
            continue;
        }
        $selectedTemplates[] = $template;
    }
}

$interestCatalogOptions = array();
if ($canEditTemplates) {
    $interestSql = 'SELECT
            lic.id_line_item_catalog,
            lic.item_name,
            cat.category_name,
            p.name AS property_name
         FROM line_item_catalog lic
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.id_company = ?
          AND cat.deleted_at IS NULL
          AND cat.is_active = 1
         LEFT JOIN property p ON p.id_property = cat.id_property
         WHERE lic.catalog_type = "sale_item"
           AND lic.deleted_at IS NULL
           AND lic.is_active = 1';
    $interestParams = array($companyId);
    if (!pms_is_owner_user()) {
        if ($allowedPropertyIds) {
            $interestSql .= ' AND (cat.id_property IS NULL OR cat.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . '))';
            foreach ($allowedPropertyIds as $allowedPropertyId) {
                $interestParams[] = (int)$allowedPropertyId;
            }
        } else {
            $interestSql .= ' AND cat.id_property IS NULL';
        }
    }
    $interestSql .= ' ORDER BY COALESCE(p.name, ""), cat.category_name, lic.item_name';
    $stmt = $pdo->prepare($interestSql);
    $stmt->execute($interestParams);
    $interestCatalogOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categoryOptions = mhub_categories();
$tokenSections = mhub_tokens();
?>

<div class="reservation-tabs message-tabs" data-reservation-tabs="messages-hub">
  <div class="reservation-tab-nav">
    <button type="button" class="reservation-tab-trigger <?php echo $activeTab === 'messages-tab-dashboard' ? 'is-active' : ''; ?>" data-tab-target="messages-tab-dashboard">Tracking</button>
    <?php if ($canEditTemplates): ?>
      <button type="button" class="reservation-tab-trigger <?php echo $activeTab === 'messages-tab-templates' ? 'is-active' : ''; ?>" data-tab-target="messages-tab-templates">Plantillas</button>
    <?php endif; ?>
  </div>

  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php elseif ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($openWhatsappUrl !== ''): ?>
    <div id="message-hub-whatsapp-launch" data-url="<?php echo htmlspecialchars($openWhatsappUrl, ENT_QUOTES, 'UTF-8'); ?>"></div>
  <?php endif; ?>

  <div class="reservation-tab-panel <?php echo $activeTab === 'messages-tab-dashboard' ? 'is-active' : ''; ?>" id="messages-tab-dashboard" data-tab-panel>
    <section class="card message-hub-summary">
      <div class="message-hub-summary-card"><span class="message-hub-summary-label">Reservaciones con pendientes</span><strong><?php echo (int)$pendingReservationCount; ?></strong></div>
      <div class="message-hub-summary-card"><span class="message-hub-summary-label">Mensajes requeridos pendientes</span><strong><?php echo (int)$pendingTemplateCount; ?></strong></div>
      <div class="message-hub-summary-card"><span class="message-hub-summary-label">Trackeables enviados</span><strong><?php echo (int)$trackedSentCount; ?></strong></div>
    </section>

    <section class="card">
      <form method="post" class="form-grid grid-4 message-hub-filters">
        <input type="hidden" name="messages_active_tab" value="messages-tab-dashboard">
        <label>
          Propiedad
          <select name="messages_filter_property">
            <option value="">Todas</option>
            <?php foreach ($properties as $property): ?>
              <option value="<?php echo htmlspecialchars((string)$property['code'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string)$property['code'] === $selectedPropertyCode ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)$property['code'] . ' - ' . (string)$property['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="full">
          Buscar reservacion o huesped
          <input type="text" name="messages_search" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Codigo, huesped, telefono, propiedad o habitacion">
        </label>
        <label>
          Estatus
          <select name="messages_filter_status">
            <option value="">Todos</option>
            <option value="apartado" <?php echo $selectedStatusFilter === 'apartado' ? 'selected' : ''; ?>>Apartado</option>
            <option value="confirmado" <?php echo $selectedStatusFilter === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
            <option value="en casa" <?php echo $selectedStatusFilter === 'en casa' ? 'selected' : ''; ?>>En casa</option>
            <option value="salida" <?php echo $selectedStatusFilter === 'salida' ? 'selected' : ''; ?>>Salida</option>
          </select>
        </label>
        <label>
          Telefono
          <select name="messages_filter_phone">
            <option value="">Todos</option>
            <option value="with_phone" <?php echo $selectedPhoneFilter === 'with_phone' ? 'selected' : ''; ?>>Con telefono</option>
            <option value="without_phone" <?php echo $selectedPhoneFilter === 'without_phone' ? 'selected' : ''; ?>>Sin telefono</option>
          </select>
        </label>
        <label>
          Dashboard
          <select name="messages_filter_pending">
            <option value="">Todos</option>
            <option value="pending_only" <?php echo $selectedPendingFilter === 'pending_only' ? 'selected' : ''; ?>>Solo pendientes</option>
            <option value="sent_only" <?php echo $selectedPendingFilter === 'sent_only' ? 'selected' : ''; ?>>Con enviados</option>
          </select>
        </label>
        <label>
          Check-in desde
          <input type="date" name="messages_filter_checkin_from" value="<?php echo htmlspecialchars($selectedCheckInFrom, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Check-in hasta
          <input type="date" name="messages_filter_checkin_to" value="<?php echo htmlspecialchars($selectedCheckInTo, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <div class="form-actions full">
          <button type="submit">Filtrar</button>
        </div>
      </form>
    </section>

    <div class="message-hub-layout">
      <section class="card">
        <div class="message-hub-section-header">
          <div>
            <h2>Dashboard de pendientes</h2>
            <p class="muted">Las plantillas requeridas y trackeables quedan pendientes hasta registrarse como enviadas.</p>
          </div>
        </div>

        <?php if ($dashboardRows): ?>
          <div class="table-scroll">
            <table class="message-dashboard-table">
              <thead>
                <tr>
                  <th>Reservacion</th>
                  <th>Huesped</th>
                  <th>Telefono</th>
                  <th>Propiedad</th>
                  <th>Fechas</th>
                  <th>Pendientes</th>
                  <th>Enviados</th>
                  <th>Ofertas</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dashboardRows as $row): ?>
                  <?php $rowId = (int)$row['id_reservation']; ?>
                  <tr class="<?php echo $selectedReservationId === $rowId ? 'is-selected' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars((string)$row['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></strong><div class="muted"><?php echo htmlspecialchars((string)$row['reservation_status'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                    <td><?php echo htmlspecialchars(mhub_guest_name($row) !== '' ? mhub_guest_name($row) : 'Sin huesped', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(mhub_text(isset($row['guest_phone']) ? $row['guest_phone'] : '') !== '' ? (string)$row['guest_phone'] : 'Sin telefono', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$row['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(mhub_date($row['check_in_date']) . ' - ' . mhub_date($row['check_out_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="message-state-chip is-pending"><?php echo (int)$row['dashboard_pending_required']; ?></span></td>
                    <td><span class="message-state-chip is-sent"><?php echo (int)$row['dashboard_tracked_sent']; ?></span></td>
                    <td><span class="message-state-chip"><?php echo (int)$row['dashboard_offers_sent']; ?></span></td>
                    <td><form method="post"><?php mhub_hidden('messages-tab-dashboard', $selectedPropertyCode, $searchTerm, $rowId, $selectedStatusFilter, $selectedPhoneFilter, $selectedPendingFilter, $selectedCheckInFrom, $selectedCheckInTo); ?><button type="submit" class="button-secondary">Abrir</button></form></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="muted">No hay reservaciones visibles para los filtros actuales.</p>
        <?php endif; ?>
      </section>

      <section class="card">
        <?php if ($selectedReservation): ?>
          <?php $selectedPhone = mhub_phone(isset($selectedReservation['guest_phone']) ? $selectedReservation['guest_phone'] : ''); ?>
          <div class="message-hub-section-header">
            <div>
              <h2><?php echo htmlspecialchars((string)$selectedReservation['reservation_code'] . ' - ' . (mhub_guest_name($selectedReservation) !== '' ? mhub_guest_name($selectedReservation) : 'Sin huesped'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <p class="muted"><?php echo htmlspecialchars((string)$selectedReservation['property_name'] . ' - ' . mhub_date($selectedReservation['check_in_date']) . ' - ' . mhub_date($selectedReservation['check_out_date']) . ' - Balance ' . mhub_money($selectedReservation['balance_due_cents'], $selectedReservation['reservation_currency']), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="message-hub-contact"><span><?php echo htmlspecialchars($selectedPhone !== '' ? $selectedPhone : 'Sin telefono', ENT_QUOTES, 'UTF-8'); ?></span></div>
          </div>

          <?php if ($selectedTemplates): ?>
            <div class="message-template-card-list">
              <?php foreach ($selectedTemplates as $template): ?>
                <?php
                $templateId = (int)$template['id_message_template'];
                $context = mhub_context($selectedReservation);
                $renderedTitle = mhub_render((string)$template['title'], $context);
                $renderedBody = mhub_render((string)$template['body'], $context);
                $statusRow = isset($statusMap[$selectedReservationId][$templateId]) ? $statusMap[$selectedReservationId][$templateId] : null;
                $logSummary = isset($logSummaryMap[$selectedReservationId][$templateId]) ? $logSummaryMap[$selectedReservationId][$templateId] : null;
                $isTrackable = (int)$template['is_trackable'] === 1;
                $isRequired = (int)$template['is_required'] === 1;
                $isSent = is_array($statusRow) && strtolower((string)$statusRow['tracking_status']) === 'sent';
                $cardClass = $isTrackable && $isRequired && !$isSent ? 'is-pending' : ($isSent ? 'is-complete' : '');
                $linkedCatalogId = (int)$template['id_sale_item_catalog'];
                ?>
                <article class="message-template-card <?php echo htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="message-template-card-header">
                    <div>
                      <h4><?php echo htmlspecialchars((string)$template['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                      <p class="muted"><?php echo htmlspecialchars((string)$template['code'] . ' - ' . (isset($categoryOptions[$template['category']]) ? $categoryOptions[$template['category']] : (string)$template['category']), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="message-template-card-badges">
                      <?php if ($isRequired): ?><span class="message-flag is-required">Requerida</span><?php endif; ?>
                      <?php if ($isTrackable): ?><span class="message-flag is-trackable">Trackeable</span><?php else: ?><span class="message-flag">Libre</span><?php endif; ?>
                      <?php if ($linkedCatalogId > 0): ?><span class="message-flag is-offer">Oferta/servicio</span><?php endif; ?>
                      <?php if ($isSent): ?><span class="message-flag is-sent">Enviado</span><?php endif; ?>
                    </div>
                  </div>
                  <div class="message-template-preview">
                    <strong><?php echo htmlspecialchars($renderedTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <p><?php echo nl2br(htmlspecialchars($renderedBody, ENT_QUOTES, 'UTF-8')); ?></p>
                  </div>
                  <div class="message-template-meta">
                    <?php if ($linkedCatalogId > 0): ?><span>Vinculado: <?php echo htmlspecialchars((string)$template['sale_item_name'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                    <?php if ($linkedCatalogId > 0 && isset($selectedInterestMap[$linkedCatalogId])): ?><span class="message-flag is-interest">Interes registrado</span><?php endif; ?>
                    <?php if ($logSummary): ?><span>Historial: <?php echo (int)$logSummary['total_sent']; ?></span><?php endif; ?>
                    <?php if (is_array($statusRow) && !empty($statusRow['last_sent_at'])): ?><span>Ultimo: <?php echo htmlspecialchars(mhub_datetime($statusRow['last_sent_at']) . (!empty($statusRow['last_channel']) ? ' - ' . (string)$statusRow['last_channel'] : ''), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                  </div>
                  <?php if ($canSendMessages): ?>
                    <form method="post" class="message-template-actions">
                      <?php mhub_hidden('messages-tab-dashboard', $selectedPropertyCode, $searchTerm, $selectedReservationId, $selectedStatusFilter, $selectedPhoneFilter, $selectedPendingFilter, $selectedCheckInFrom, $selectedCheckInTo); ?>
                      <input type="hidden" name="message_template_id" value="<?php echo $templateId; ?>">
                      <button type="submit" name="messages_action" value="send_message" <?php echo $selectedPhone === '' ? 'disabled' : ''; ?>>Enviar por WhatsApp</button>
                      <button type="submit" name="messages_action" value="mark_message_sent" class="button-secondary">Marcar enviado</button>
                    </form>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="muted">No hay plantillas activas para esta reservacion.</p>
          <?php endif; ?>

          <div class="message-side-by-side">
            <section class="message-mini-panel">
              <h3>Plantillas ya enviadas</h3>
              <?php if ($selectedHistory): ?>
                <div class="table-scroll">
                  <table>
                    <thead><tr><th>Enviado</th><th>Plantilla</th><th>Canal</th><th>Telefono</th><th>Usuario</th></tr></thead>
                    <tbody>
                      <?php foreach ($selectedHistory as $history): ?>
                        <tr>
                          <td><?php echo htmlspecialchars(mhub_datetime($history['sent_at']), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?php echo htmlspecialchars((string)$history['template_code'], ENT_QUOTES, 'UTF-8'); ?><div class="muted"><?php echo htmlspecialchars((string)$history['message_title'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                          <td><?php echo htmlspecialchars((string)$history['channel'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?php echo htmlspecialchars((string)$history['sent_to_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?php echo htmlspecialchars((string)($history['sent_by_name'] ?: $history['sent_by_email']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="muted">Sin historial registrado.</p>
              <?php endif; ?>
            </section>

            <section class="message-mini-panel">
              <h3>Intereses y servicios activos</h3>
              <?php if ($selectedInterests): ?>
                <ul class="message-interest-list">
                  <?php foreach ($selectedInterests as $interest): ?>
                    <li><strong><?php echo htmlspecialchars((string)$interest['item_name'], ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo htmlspecialchars((string)$interest['category_name'], ENT_QUOTES, 'UTF-8'); ?></span><span class="muted"><?php echo htmlspecialchars(mhub_datetime($interest['created_at']), ENT_QUOTES, 'UTF-8'); ?></span></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="muted">No hay intereses activos registrados para esta reservacion.</p>
              <?php endif; ?>
            </section>
          </div>
        <?php else: ?>
          <p class="muted">Selecciona una reservacion para ver su hub de mensajes.</p>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <?php if ($canEditTemplates): ?>
    <div class="reservation-tab-panel <?php echo $activeTab === 'messages-tab-templates' ? 'is-active' : ''; ?>" id="messages-tab-templates" data-tab-panel>
      <section class="card">
        <div class="message-hub-section-header">
          <div>
            <h2>Plantillas de mensaje</h2>
            <p class="muted">Las plantillas libres se pueden enviar todas las veces que haga falta. Las trackeables usan estado actual por reservacion y mantienen historial completo.</p>
          </div>
        </div>
        <?php if ($templates): ?>
          <div class="table-scroll">
            <table>
              <thead><tr><th>Codigo</th><th>Titulo</th><th>Categoria</th><th>Propiedad</th><th>Tracking</th><th>Oferta</th><th>Activa</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($templates as $template): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)$template['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$template['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(isset($categoryOptions[$template['category']]) ? $categoryOptions[$template['category']] : (string)$template['category'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(mhub_text(isset($template['property_name']) ? $template['property_name'] : '') !== '' ? (string)$template['property_name'] : 'Global', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(((int)$template['is_trackable'] === 1 ? 'Trackeable' : 'Libre') . ((int)$template['is_required'] === 1 ? ' / Requerida' : ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$template['sale_item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int)$template['is_active'] === 1 ? 'Si' : 'No'; ?></td>
                    <td><form method="post"><?php mhub_hidden('messages-tab-templates', $selectedPropertyCode, $searchTerm, $selectedReservationId, $selectedStatusFilter, $selectedPhoneFilter, $selectedPendingFilter, $selectedCheckInFrom, $selectedCheckInTo); ?><input type="hidden" name="messages_action" value="edit_template"><input type="hidden" name="template_id" value="<?php echo (int)$template['id_message_template']; ?>"><button type="submit" class="button-secondary">Editar</button></form></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div class="message-template-editor-layout">
          <form method="post" class="form-grid grid-2 message-template-form">
            <?php mhub_hidden('messages-tab-templates', $selectedPropertyCode, $searchTerm, $selectedReservationId, $selectedStatusFilter, $selectedPhoneFilter, $selectedPendingFilter, $selectedCheckInFrom, $selectedCheckInTo); ?>
            <input type="hidden" name="messages_action" value="save_template">
            <input type="hidden" name="template_id" value="<?php echo (int)$templateForm['template_id']; ?>">
            <label>Propiedad<select name="template_property_code"><option value="">Global</option><?php foreach ($properties as $property): ?><option value="<?php echo htmlspecialchars((string)$property['code'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $templateForm['template_property_code'] === (string)$property['code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$property['code'] . ' - ' . (string)$property['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
            <label>Codigo *<input type="text" name="template_code" required value="<?php echo htmlspecialchars((string)$templateForm['template_code'], ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label>Categoria<select name="template_category"><?php foreach ($categoryOptions as $key => $label): ?><option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $templateForm['template_category'] === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
            <label>Orden<input type="number" name="template_sort_order" value="<?php echo (int)$templateForm['template_sort_order']; ?>"></label>
            <label>Canal default<select name="template_channel"><option value="whatsapp" <?php echo $templateForm['template_channel'] === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option><option value="manual" <?php echo $templateForm['template_channel'] === 'manual' ? 'selected' : ''; ?>>Manual</option></select></label>
            <label>Oferta o servicio<select name="template_id_sale_item_catalog"><option value="">Ninguno</option><?php foreach ($interestCatalogOptions as $catalog): ?><option value="<?php echo (int)$catalog['id_line_item_catalog']; ?>" <?php echo (int)$templateForm['template_id_sale_item_catalog'] === (int)$catalog['id_line_item_catalog'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$catalog['item_name'] . ' - ' . (string)$catalog['category_name'] . (mhub_text(isset($catalog['property_name']) ? $catalog['property_name'] : '') !== '' ? ' - ' . (string)$catalog['property_name'] : ''), ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
            <label class="full">Titulo *<input type="text" name="template_title" required data-message-token-target value="<?php echo htmlspecialchars((string)$templateForm['template_title'], ENT_QUOTES, 'UTF-8'); ?>"></label>
            <label class="full">Cuerpo *<textarea name="template_body" rows="8" required data-message-token-target><?php echo htmlspecialchars((string)$templateForm['template_body'], ENT_QUOTES, 'UTF-8'); ?></textarea></label>
            <div class="message-template-flags full">
              <label class="checkbox"><input type="checkbox" name="template_is_trackable" value="1" <?php echo (int)$templateForm['template_is_trackable'] === 1 ? 'checked' : ''; ?>> Trackeable por reservacion</label>
              <label class="checkbox"><input type="checkbox" name="template_is_required" value="1" <?php echo (int)$templateForm['template_is_required'] === 1 ? 'checked' : ''; ?>> Requerida en dashboard</label>
              <label class="checkbox"><input type="checkbox" name="template_is_active" value="1" <?php echo (int)$templateForm['template_is_active'] === 1 ? 'checked' : ''; ?>> Activa</label>
            </div>
            <div class="form-actions full"><button type="submit"><?php echo (int)$templateForm['template_id'] > 0 ? 'Actualizar plantilla' : 'Guardar plantilla'; ?></button></div>
          </form>

          <aside class="message-token-sidebar">
            <h3>Variables insertables</h3>
            <p class="muted">Haz clic en un token para insertarlo en el campo con foco.</p>
            <?php foreach ($tokenSections as $sectionLabel => $tokens): ?>
              <div class="message-token-group">
                <strong><?php echo htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                <div class="message-token-list">
                  <?php foreach ($tokens as $token => $label): ?>
                    <button type="button" class="button-secondary message-token-button" data-message-token="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </aside>
        </div>
      </section>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var container = document.querySelector('[data-reservation-tabs="messages-hub"]');
  if (container) {
    var triggers = container.querySelectorAll('.reservation-tab-trigger');
    var panels = container.querySelectorAll('[data-tab-panel]');
    function activate(targetId) {
      Array.prototype.forEach.call(triggers, function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-tab-target') === targetId);
      });
      Array.prototype.forEach.call(panels, function (panel) {
        panel.classList.toggle('is-active', panel.id === targetId);
      });
    }
    Array.prototype.forEach.call(triggers, function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-tab-target');
        if (targetId) {
          activate(targetId);
        }
      });
    });
  }

  var launch = document.getElementById('message-hub-whatsapp-launch');
  if (launch && launch.getAttribute('data-url')) {
    window.open(launch.getAttribute('data-url'), '_blank');
  }

  var currentTarget = null;
  Array.prototype.forEach.call(document.querySelectorAll('[data-message-token-target]'), function (target) {
    target.addEventListener('focus', function () { currentTarget = target; });
    target.addEventListener('click', function () { currentTarget = target; });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-message-token]'), function (button) {
    button.addEventListener('click', function () {
      if (!currentTarget) {
        currentTarget = document.querySelector('[data-message-token-target]');
      }
      if (!currentTarget) {
        return;
      }
      var token = button.getAttribute('data-message-token') || '';
      var start = currentTarget.selectionStart || 0;
      var end = currentTarget.selectionEnd || 0;
      var value = currentTarget.value || '';
      currentTarget.value = value.slice(0, start) + token + value.slice(end);
      currentTarget.focus();
      currentTarget.selectionStart = currentTarget.selectionEnd = start + token.length;
    });
  });
})();
</script>
