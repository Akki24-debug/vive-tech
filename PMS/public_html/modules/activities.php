<?php
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
$moduleKey = 'activities_admin';

if ($companyId === 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('activities.view');

$parseReservationIds = static function ($raw) {
    $ids = array();
    if (is_array($raw)) {
        $values = $raw;
    } elseif ($raw === null) {
        $values = array();
    } else {
        $values = preg_split('/[,\s;|]+/', (string)$raw);
    }
    foreach ($values as $value) {
        $id = (int)$value;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
};
$reservationIdsToCsv = static function (array $ids) {
    if (!$ids) {
        return '';
    }
    return implode(',', array_map('intval', $ids));
};

$properties = pms_fetch_properties($companyId);
$propertyIndex = array();
foreach ($properties as $property) {
    if (isset($property['code'], $property['id_property'])) {
        $propertyIndex[$property['code']] = (int)$property['id_property'];
    }
}

$saleItems = array();
try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT sic.id_line_item_catalog AS id_sale_item_catalog,
                sic.item_name,
                cat.category_name
         FROM line_item_catalog sic
         JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
         WHERE sic.catalog_type = "sale_item"
           AND cat.id_company = ?
           AND cat.deleted_at IS NULL
           AND sic.deleted_at IS NULL
           AND sic.is_active = 1
         ORDER BY cat.category_name, sic.item_name'
    );
    $stmt->execute(array($companyId));
    $saleItems = $stmt->fetchAll();
} catch (Exception $e) {
    $error = isset($error) && $error ? $error : 'No fue posible cargar los conceptos de venta: ' . $e->getMessage();
}

$filters = array(
    'property_code' => isset($_POST['activities_filter_property']) ? (string)$_POST['activities_filter_property'] : '',
    'show_inactive' => isset($_POST['activities_show_inactive']) ? (int)$_POST['activities_show_inactive'] : 0,
);

$selectedActivityId = isset($_POST['selected_activity_id']) ? (int)$_POST['selected_activity_id'] : 0;
if (isset($_GET['activity_id'])) {
    $selectedActivityId = (int)$_GET['activity_id'];
}

$message = null;
$error = null;
$forceAdminTab = false;

$action = isset($_POST['activities_action']) ? (string)$_POST['activities_action'] : '';
if ($action === 'new_activity') {
    pms_require_permission('activities.create');
} elseif (in_array($action, array('restore_activity', 'deactivate_activity'), true)) {
    pms_require_permission('activities.edit');
} elseif ($action === 'save_activity') {
    $incomingActivityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    pms_require_permission($incomingActivityId > 0 ? 'activities.edit' : 'activities.create');
} elseif (in_array($action, array('schedule_activity', 'save_activity_booking', 'edit_activity_booking'), true)) {
    pms_require_permission('activities.book');
} elseif (in_array($action, array('cancel_activity_booking', 'delete_activity_booking'), true)) {
    pms_require_permission('activities.cancel');
}
if ($action === 'new_activity') {
    $selectedActivityId = 0;
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'activity:__new__';
    $forceAdminTab = true;
} elseif ($action === 'restore_activity') {
    $activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    if ($activityId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE activity SET is_active = 1, updated_at = NOW()
                 WHERE id_activity = ? AND id_company = ?'
            );
            $stmt->execute(array($activityId, $companyId));
            $message = 'Actividad reactivada.';
            $selectedActivityId = $activityId;
            $_POST[$moduleKey . '_subtab_action'] = 'open';
            $_POST[$moduleKey . '_subtab_target'] = 'activity:' . $activityId;
            $forceAdminTab = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'deactivate_activity') {
    $activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    if ($activityId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE activity SET is_active = 0, updated_at = NOW()
                 WHERE id_activity = ? AND id_company = ?'
            );
            $stmt->execute(array($activityId, $companyId));
            $message = 'Actividad desactivada.';
            $selectedActivityId = $activityId;
            $_POST[$moduleKey . '_subtab_action'] = 'open';
            $_POST[$moduleKey . '_subtab_target'] = 'activity:' . $activityId;
            $forceAdminTab = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'save_activity') {
    $activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    $code = isset($_POST['activity_code']) ? trim((string)$_POST['activity_code']) : '';
    $name = isset($_POST['activity_name']) ? trim((string)$_POST['activity_name']) : '';
    $type = isset($_POST['activity_type']) ? trim((string)$_POST['activity_type']) : 'tour';
    $propertyCode = isset($_POST['activity_property_code']) ? trim((string)$_POST['activity_property_code']) : '';
    $description = isset($_POST['activity_description']) ? trim((string)$_POST['activity_description']) : '';
    $duration = isset($_POST['activity_duration']) && $_POST['activity_duration'] !== '' ? (int)$_POST['activity_duration'] : null;
    $basePrice = isset($_POST['activity_base_price']) && $_POST['activity_base_price'] !== '' ? (int)$_POST['activity_base_price'] : 0;
    $saleItemCatalogId = isset($_POST['activity_sale_item_catalog']) && $_POST['activity_sale_item_catalog'] !== ''
        ? (int)$_POST['activity_sale_item_catalog']
        : null;
    $location = isset($_POST['activity_location']) ? trim((string)$_POST['activity_location']) : '';
    $isActive = isset($_POST['activity_is_active']) ? 1 : 0;

    if ($code === '' || $name === '') {
        $error = 'Codigo y nombre son obligatorios.';
    } elseif (!in_array($type, array('tour', 'vibe'), true)) {
        $error = 'El tipo debe ser tour o vibe.';
    } else {
        $propertyId = ($propertyCode !== '' && isset($propertyIndex[$propertyCode])) ? $propertyIndex[$propertyCode] : null;
        try {
            pms_call_procedure('sp_activity_upsert', array(
                $companyId,
                $propertyId,
                $code,
                $name,
                $type,
                $description === '' ? null : $description,
                $duration,
                $basePrice,
                $saleItemCatalogId,
                null,
                null,
                $location === '' ? null : $location,
                $isActive
            ));
            $message = $activityId > 0 ? 'Actividad actualizada.' : 'Actividad creada.';
            $selectedActivityId = $activityId;
            if ($selectedActivityId === 0) {
                // intentar recuperar id creada por codigo
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare(
                    'SELECT id_activity FROM activity WHERE code = ? AND id_company = ? ORDER BY id_activity DESC LIMIT 1'
                );
                $stmt->execute(array($code, $companyId));
                $selectedActivityId = (int)$stmt->fetchColumn();
            }
            $_POST[$moduleKey . '_subtab_action'] = 'activate';
            $_POST[$moduleKey . '_subtab_target'] = 'static:general';
            $_POST[$moduleKey . '_subtab_target_close'] = 'activity:' . ($activityId > 0 ? $activityId : '__new__');
            $forceAdminTab = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif (in_array($action, array('schedule_activity', 'save_activity_booking', 'edit_activity_booking', 'cancel_activity_booking', 'delete_activity_booking'), true)) {
    $bookingId = isset($_POST['activity_booking_id']) ? (int)$_POST['activity_booking_id'] : 0;
    $activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    $scheduledAtRaw = isset($_POST['activity_scheduled_at']) ? trim((string)$_POST['activity_scheduled_at']) : '';
    $numAdults = isset($_POST['activity_num_adults']) ? (int)$_POST['activity_num_adults'] : 0;
    $numChildren = isset($_POST['activity_num_children']) ? (int)$_POST['activity_num_children'] : 0;
    $bookingStatus = isset($_POST['activity_booking_status']) ? trim((string)$_POST['activity_booking_status']) : 'confirmed';
    $bookingNotes = isset($_POST['activity_booking_notes']) ? trim((string)$_POST['activity_booking_notes']) : '';
    $reservationIds = isset($_POST['activity_reservation_ids']) ? $parseReservationIds($_POST['activity_reservation_ids']) : array();
    if (!$reservationIds) {
        $legacyReservationId = isset($_POST['activity_reservation_id']) ? (int)$_POST['activity_reservation_id'] : 0;
        if ($legacyReservationId > 0) {
            $reservationIds[] = $legacyReservationId;
        }
    }

    if ($action === 'edit_activity_booking') {
        if ($bookingId > 0) {
            try {
                $bookingSets = pms_call_procedure('sp_activity_bookings_list', array(
                    null,
                    $companyId,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $bookingId
                ));
                $bookingRows = isset($bookingSets[0]) ? $bookingSets[0] : array();
                if ($bookingRows) {
                    $bookingRow = $bookingRows[0];
                    $selectedActivityId = isset($bookingRow['id_activity']) ? (int)$bookingRow['id_activity'] : $selectedActivityId;
                    $_POST['activity_booking_id'] = (string)$bookingId;
                    $_POST['activity_id'] = isset($bookingRow['id_activity']) ? (string)$bookingRow['id_activity'] : '';
                    $_POST['activity_scheduled_at'] = isset($bookingRow['scheduled_at']) ? str_replace(' ', 'T', substr((string)$bookingRow['scheduled_at'], 0, 16)) : '';
                    $_POST['activity_num_adults'] = isset($bookingRow['num_adults']) ? (string)$bookingRow['num_adults'] : '0';
                    $_POST['activity_num_children'] = isset($bookingRow['num_children']) ? (string)$bookingRow['num_children'] : '0';
                    $_POST['activity_booking_status'] = isset($bookingRow['status']) ? (string)$bookingRow['status'] : 'confirmed';
                    $_POST['activity_booking_notes'] = isset($bookingRow['notes']) ? (string)$bookingRow['notes'] : '';
                    $_POST['activity_reservation_ids'] = $parseReservationIds(isset($bookingRow['linked_reservation_ids_csv']) ? (string)$bookingRow['linked_reservation_ids_csv'] : '');
                    $message = 'Booking cargado para edicion.';
                } else {
                    $error = 'No fue posible cargar el booking solicitado.';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        } else {
            $_POST['activity_booking_id'] = '0';
            $_POST['activity_reservation_ids'] = array();
            $_POST['activity_booking_notes'] = '';
            $_POST['activity_booking_status'] = 'confirmed';
            $message = 'Editor listo para nuevo booking.';
        }
    } elseif ($action === 'cancel_activity_booking' || $action === 'delete_activity_booking') {
        if ($bookingId <= 0) {
            $error = 'Selecciona un booking valido.';
        } else {
            try {
                pms_call_procedure('sp_activity_booking_upsert', array(
                    $companyId,
                    $bookingId,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $bookingNotes === '' ? null : $bookingNotes,
                    $actorUserId,
                    $action === 'cancel_activity_booking' ? 'cancel' : 'delete'
                ));
                $message = $action === 'cancel_activity_booking'
                    ? 'Booking cancelado.'
                    : 'Booking eliminado.';
                $_POST['activity_booking_id'] = '0';
                $_POST['activity_reservation_ids'] = array();
                $_POST['activity_booking_notes'] = '';
                $_POST['activity_booking_status'] = 'confirmed';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        if ($activityId <= 0) {
            $error = 'Selecciona una actividad valida.';
        } elseif (!$reservationIds) {
            $error = 'Debes seleccionar al menos una reservacion.';
        } elseif ($scheduledAtRaw === '') {
            $error = 'Selecciona la fecha y hora.';
        } else {
            $scheduledAtFormatted = str_replace('T', ' ', $scheduledAtRaw);
            if (strlen($scheduledAtFormatted) === 16) {
                $scheduledAtFormatted .= ':00';
            }
            $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAtFormatted);
            if (!$dateObj) {
                $error = 'La fecha no es valida.';
            } else {
                try {
                    $pdo = pms_get_connection();
                    $stmt = $pdo->prepare(
                        'SELECT type FROM activity WHERE id_activity = ? AND id_company = ? AND deleted_at IS NULL LIMIT 1'
                    );
                    $stmt->execute(array($activityId, $companyId));
                    $activityType = (string)$stmt->fetchColumn();
                    if ($activityType !== 'vibe' && $activityType !== 'tour') {
                        $error = 'El tipo de actividad no es valido.';
                    } else {
                        pms_call_procedure('sp_activity_booking_upsert', array(
                            $companyId,
                            $bookingId > 0 ? $bookingId : null,
                            $activityId,
                            $reservationIdsToCsv($reservationIds),
                            $dateObj->format('Y-m-d H:i:s'),
                            $numAdults,
                            $numChildren,
                            null,
                            null,
                            $bookingStatus,
                            $bookingNotes === '' ? null : $bookingNotes,
                            $actorUserId,
                            'save'
                        ));
                        $message = $bookingId > 0 ? 'Booking actualizado.' : 'Booking creado.';
                        $selectedActivityId = $activityId;
                        $_POST['activity_booking_id'] = (string)($bookingId > 0 ? $bookingId : 0);
                        $_POST['activity_reservation_ids'] = $reservationIds;
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

try {
    $sets = pms_call_procedure('sp_portal_activity_data', array(
        $companyCode, // company code is expected
        $filters['property_code'] === '' ? null : $filters['property_code'],
        null,
        $filters['show_inactive'] ? 0 : 1,
        0
    ));
    $activitiesList = isset($sets[0]) ? $sets[0] : array();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
    $activitiesList = array();
}
$vibeActivities = array();
$tourActivities = array();
foreach ($activitiesList as $row) {
    $type = isset($row['type']) ? (string)$row['type'] : '';
    if ($type === 'vibe') {
        $vibeActivities[] = $row;
    } elseif ($type === 'tour') {
        $tourActivities[] = $row;
    }
}
$bookableActivities = $activitiesList;
$subtabState = pms_subtabs_init($moduleKey, 'static:general');
if (isset($_POST[$moduleKey . '_subtab_target_close']) && $_POST[$moduleKey . '_subtab_target_close'] !== '') {
    $toClose = (string)$_POST[$moduleKey . '_subtab_target_close'];
    if (isset($subtabState['open']) && is_array($subtabState['open'])) {
        $subtabState['open'] = array_values(array_filter($subtabState['open'], function ($item) use ($toClose) {
            return $item !== $toClose;
        }));
    }
    $subtabState['active'] = 'static:general';
    $_SESSION['pms_subtabs'][$moduleKey] = $subtabState;
}
if (!$forceAdminTab) {
    $subtabActionKey = $moduleKey . '_subtab_action';
    $subtabCurrentKey = $moduleKey . '_current_subtab';
    if ((isset($_POST[$subtabActionKey]) && $_POST[$subtabActionKey] !== '')
        || (isset($_POST[$subtabCurrentKey]) && $_POST[$subtabCurrentKey] !== '')
    ) {
        $forceAdminTab = true;
    }
}

$openActivityIds = array();
$openActivityKeys = isset($subtabState['open']) && is_array($subtabState['open']) ? $subtabState['open'] : array();
foreach ($openActivityKeys as $openKey) {
    if (strpos($openKey, 'activity:') !== 0) {
        continue;
    }
    $suffix = substr($openKey, strlen('activity:'));
    if ($suffix === '' || $suffix === '__new__') {
        continue;
    }
    $activityId = (int)$suffix;
    if ($activityId > 0 && !in_array($activityId, $openActivityIds, true)) {
        $openActivityIds[] = $activityId;
    }
}

$activityDetailsById = array();
$activityBookingsById = array();
foreach ($openActivityIds as $activityId) {
    try {
        $detailSets = pms_call_procedure('sp_portal_activity_data', array(
            $companyCode,
            $filters['property_code'] === '' ? null : $filters['property_code'],
            null,
            $filters['show_inactive'] ? 0 : 1,
            $activityId
        ));
        $detailRows = isset($detailSets[1]) ? $detailSets[1] : array();
        if ($detailRows && isset($detailRows[0]['id_activity'])) {
            $activityDetailsById[$activityId] = $detailRows[0];
        }
        $activityBookingsById[$activityId] = isset($detailSets[2]) ? $detailSets[2] : array();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
}
$activeTab = 'activities-tab-calendar';
$postedActiveTab = isset($_POST['activities_active_tab']) ? (string)$_POST['activities_active_tab'] : '';
if (in_array($postedActiveTab, array('activities-tab-calendar', 'activities-tab-tours', 'activities-tab-admin'), true)) {
    $activeTab = $postedActiveTab;
}
if ($forceAdminTab) {
    $activeTab = 'activities-tab-admin';
} elseif (in_array($action, array('schedule_activity', 'save_activity_booking', 'edit_activity_booking', 'cancel_activity_booking', 'delete_activity_booking'), true)) {
    $activeTab = 'activities-tab-calendar';
}

$calendarStartInput = '';
if (isset($_POST['activity_calendar_start'])) {
    $calendarStartInput = (string)$_POST['activity_calendar_start'];
} elseif (isset($_GET['activity_calendar_start'])) {
    $calendarStartInput = (string)$_GET['activity_calendar_start'];
}
$calendarDays = 14;
$calendarDaysInput = '';
if (isset($_POST['activity_calendar_days'])) {
    $calendarDaysInput = (string)$_POST['activity_calendar_days'];
} elseif (isset($_GET['activity_calendar_days'])) {
    $calendarDaysInput = (string)$_GET['activity_calendar_days'];
}
if ($calendarDaysInput !== '') {
    $daysCandidate = (int)$calendarDaysInput;
    if ($daysCandidate >= 7 && $daysCandidate <= 60) {
        $calendarDays = $daysCandidate;
    }
}
$calendarStartObj = DateTime::createFromFormat('Y-m-d', substr($calendarStartInput, 0, 10));
if (!$calendarStartObj) {
    $calendarStartObj = new DateTime('today');
}
$calendarStart = $calendarStartObj->format('Y-m-d');
$calendarEndObj = clone $calendarStartObj;
$calendarEndObj->modify('+' . $calendarDays . ' days');
$calendarEnd = $calendarEndObj->format('Y-m-d');
$calendarDisplayEndObj = clone $calendarStartObj;
$calendarDisplayEndObj->modify('+' . max($calendarDays - 1, 0) . ' days');
$calendarRangeLabel = 'Del ' . $calendarStartObj->format('d M') . ' al ' . $calendarDisplayEndObj->format('d M') . '.';
$scheduleReservationIds = array();
if (isset($_POST['activity_reservation_ids'])) {
    $scheduleReservationIds = $parseReservationIds($_POST['activity_reservation_ids']);
} elseif (isset($_POST['activity_reservation_id'])) {
    $legacyScheduleReservationId = (int)$_POST['activity_reservation_id'];
    if ($legacyScheduleReservationId > 0) {
        $scheduleReservationIds[] = $legacyScheduleReservationId;
    }
}
$scheduleForm = array(
    'booking_id' => isset($_POST['activity_booking_id']) ? (int)$_POST['activity_booking_id'] : 0,
    'activity_id' => isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : ($selectedActivityId > 0 ? $selectedActivityId : 0),
    'reservation_ids' => $scheduleReservationIds,
    'scheduled_at' => isset($_POST['activity_scheduled_at']) ? trim((string)$_POST['activity_scheduled_at']) : '',
    'num_adults' => isset($_POST['activity_num_adults']) ? (int)$_POST['activity_num_adults'] : 1,
    'num_children' => isset($_POST['activity_num_children']) ? (int)$_POST['activity_num_children'] : 0,
    'status' => isset($_POST['activity_booking_status']) ? trim((string)$_POST['activity_booking_status']) : 'confirmed',
    'notes' => isset($_POST['activity_booking_notes']) ? trim((string)$_POST['activity_booking_notes']) : ''
);
if ($scheduleForm['scheduled_at'] === '') {
    $scheduleForm['scheduled_at'] = $calendarStartObj->format('Y-m-d') . 'T09:00';
}
$hasBookableActivities = count($bookableActivities) > 0;
if ($scheduleForm['activity_id'] === 0 && $hasBookableActivities) {
    $firstActivity = $bookableActivities[0];
    $scheduleForm['activity_id'] = isset($firstActivity['id_activity']) ? (int)$firstActivity['id_activity'] : 0;
}
$reservationOptions = array();
try {
    $pdo = pms_get_connection();
    $params = array($companyId, $calendarEnd, $calendarStart);
    $propertyFilterSql = '';
    if ($filters['property_code'] !== '') {
        $propertyFilterSql = ' AND p.code = ?';
        $params[] = $filters['property_code'];
    }
    $stmt = $pdo->prepare(
        'SELECT r.id_reservation,
                r.code AS reservation_code,
                r.check_in_date,
                r.check_out_date,
                g.names AS guest_names,
                g.last_name AS guest_last_name,
                g.phone AS guest_phone,
                p.code AS property_code,
                p.name AS property_name
         FROM reservation r
         JOIN property p ON p.id_property = r.id_property
         LEFT JOIN guest g ON g.id_guest = r.id_guest
         WHERE p.id_company = ?
           AND r.deleted_at IS NULL
           AND COALESCE(r.status, \'confirmed\') NOT IN (\'cancelled\', \'canceled\', \'cancelado\', \'cancelada\')
           AND r.check_in_date <= ?
           AND r.check_out_date >= ?' . $propertyFilterSql . '
         ORDER BY r.check_in_date DESC, r.id_reservation DESC
         LIMIT 300'
    );
    $stmt->execute($params);
    $reservationOptions = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
}

$listContent = '';
ob_start();
?>
<div class="tab-actions">
  <form method="post">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <input type="hidden" name="activities_active_tab" value="activities-tab-admin">
    <label>
      Propiedad
      <select name="activities_filter_property" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach ($properties as $property):
          $code = isset($property['code']) ? (string)$property['code'] : '';
          $name = isset($property['name']) ? (string)$property['name'] : '';
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $filters['property_code'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="activities_show_inactive" value="1" <?php echo $filters['show_inactive'] ? 'checked' : ''; ?> onchange="this.form.submit()">
      Mostrar inactivas
    </label>
  </form>
  <form method="post">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <input type="hidden" name="activities_action" value="new_activity">
    <input type="hidden" name="activities_active_tab" value="activities-tab-admin">
    <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
    <button type="submit">Nueva actividad</button>
  </form>
</div>

<?php if ($activitiesList): ?>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Codigo</th>
          <th>Nombre</th>
          <th>Propiedad</th>
          <th>Tipo</th>
          <th>Concepto</th>
          <th>Precio</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($activitiesList as $row):
          $idActivity = isset($row['id_activity']) ? (int)$row['id_activity'] : 0;
          $saleItemName = isset($row['sale_item_name']) ? (string)$row['sale_item_name'] : '';
          $saleItemCategory = isset($row['sale_item_category']) ? (string)$row['sale_item_category'] : '';
          $conceptLabel = $saleItemName !== ''
              ? ($saleItemCategory !== '' ? $saleItemCategory . ' - ' . $saleItemName : $saleItemName)
              : 'Sin concepto';
        ?>
          <tr>
            <td><?php echo htmlspecialchars(isset($row['activity_code']) ? (string)$row['activity_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['activity_name']) ? (string)$row['activity_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['property_name']) ? (string)$row['property_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['type']) ? (string)$row['type'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo isset($row['base_price_cents']) ? number_format($row['base_price_cents'] / 100, 2) : '0.00'; ?></td>
            <td>
              <div class="list-actions">
                <form method="post">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <input type="hidden" name="activities_active_tab" value="activities-tab-admin">
                  <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
                  <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                  <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="activity:<?php echo $idActivity; ?>">
                  <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:activity:<?php echo $idActivity; ?>">
                  <button type="submit" class="button-secondary">Abrir</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="muted">No hay actividades registradas.</p>
<?php endif; ?>
<?php
$listContent = ob_get_clean();

$dynamicTabs = array();
foreach ($openActivityKeys as $openKey) {
    if (strpos($openKey, 'activity:') !== 0) {
        continue;
    }
    $suffix = substr($openKey, strlen('activity:'));
    $detail = null;
    $tabLabel = '';
    $activityId = 0;
    if ($suffix === '__new__') {
        $tabLabel = 'Nueva';
        $detail = array(
            'id_activity' => 0,
            'activity_code' => '',
            'activity_name' => '',
            'type' => 'tour',
            'description' => '',
            'duration_minutes' => null,
            'base_price_cents' => 0,
            'location' => '',
            'is_active' => 1,
            'id_sale_item_catalog' => null,
            'property_code' => ''
        );
    } else {
        $activityId = (int)$suffix;
        if ($activityId > 0 && isset($activityDetailsById[$activityId])) {
            $detail = $activityDetailsById[$activityId];
            $tabLabel = isset($detail['activity_code']) && $detail['activity_code'] !== '' ? $detail['activity_code'] : 'Actividad';
        }
    }
    if (!$detail) {
        continue;
    }

    $panelId = 'activity-panel-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $suffix === '__new__' ? 'new' : (string)$suffix);
    $closeFormId = 'activities-close-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $suffix === '__new__' ? 'new' : (string)$suffix);
    $isActive = isset($detail['is_active']) ? (int)$detail['is_active'] === 1 : true;
    $activityBookings = $activityId > 0 && isset($activityBookingsById[$activityId]) ? $activityBookingsById[$activityId] : array();

    ob_start();
    ?>
    <div class="subtab-actions">
      <div>
        <h3><?php echo htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
      </div>
      <div class="subtab-actions">
        <form method="post" id="<?php echo htmlspecialchars($closeFormId, ENT_QUOTES, 'UTF-8'); ?>">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="activity:<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="activities_active_tab" value="activities-tab-admin">
          <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
          <button type="submit" class="button-secondary">Cerrar</button>
        </form>
      </div>
    </div>

    <form method="post" class="form-grid grid-3 activity-detail-form">
      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
      <input type="hidden" name="activities_action" value="save_activity">
      <input type="hidden" name="activity_id" value="<?php echo isset($detail['id_activity']) ? (int)$detail['id_activity'] : 0; ?>">
      <input type="hidden" name="activities_active_tab" value="activities-tab-admin">
      <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">

      <label>
        Codigo *
        <input type="text" name="activity_code" required value="<?php echo htmlspecialchars((string)$detail['activity_code'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Nombre *
        <input type="text" name="activity_name" required value="<?php echo htmlspecialchars((string)$detail['activity_name'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Tipo
        <select name="activity_type">
          <option value="tour" <?php echo isset($detail['type']) && $detail['type'] === 'tour' ? 'selected' : ''; ?>>tour</option>
          <option value="vibe" <?php echo isset($detail['type']) && $detail['type'] === 'vibe' ? 'selected' : ''; ?>>vibe</option>
        </select>
      </label>
      <label>
        Concepto de venta
        <select name="activity_sale_item_catalog">
          <option value="">Sin concepto</option>
          <?php foreach ($saleItems as $saleItem):
            $itemId = isset($saleItem['id_sale_item_catalog']) ? (int)$saleItem['id_sale_item_catalog'] : 0;
            $itemName = isset($saleItem['item_name']) ? (string)$saleItem['item_name'] : '';
            $categoryName = isset($saleItem['category_name']) ? (string)$saleItem['category_name'] : '';
            $selected = isset($detail['id_sale_item_catalog']) && (int)$detail['id_sale_item_catalog'] === $itemId;
            $label = $categoryName !== '' ? $categoryName . ' - ' . $itemName : $itemName;
          ?>
            <option value="<?php echo $itemId; ?>" <?php echo $selected ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Propiedad
        <select name="activity_property_code">
          <option value="">Todas</option>
          <?php foreach ($properties as $property):
            $code = isset($property['code']) ? (string)$property['code'] : '';
            $name = isset($property['name']) ? (string)$property['name'] : '';
            $selected = isset($detail['property_code']) && $detail['property_code'] === $code;
          ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Duracion (min)
        <input type="number" name="activity_duration" min="0" value="<?php echo isset($detail['duration_minutes']) ? (int)$detail['duration_minutes'] : ''; ?>">
      </label>
      <label>
        Precio base (centavos)
        <input type="number" name="activity_base_price" min="0" value="<?php echo isset($detail['base_price_cents']) ? (int)$detail['base_price_cents'] : 0; ?>">
      </label>
      <label>
        Ubicacion
        <input type="text" name="activity_location" value="<?php echo htmlspecialchars((string)$detail['location'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label class="checkbox">
        <input type="checkbox" name="activity_is_active" value="1" <?php echo $isActive ? 'checked' : ''; ?>>
        Activa
      </label>
      <label class="full">
        Descripcion
        <textarea name="activity_description" rows="3"><?php echo htmlspecialchars((string)$detail['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>
      <div class="form-actions full">
        <button type="submit" onclick="this.form.activities_action.value='save_activity';">Guardar actividad</button>
        <?php if (!empty($detail['id_activity'])): ?>
          <?php if ($isActive): ?>
            <button type="submit" class="button-secondary" onclick="this.form.activities_action.value='deactivate_activity';">Desactivar</button>
          <?php else: ?>
            <button type="submit" class="button-secondary" onclick="this.form.activities_action.value='restore_activity';">Restaurar</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </form>

    <?php if (!empty($detail['id_activity'])): ?>
      <div class="subtab-info">
        <h4>Reservaciones recientes</h4>
        <?php if ($activityBookings): ?>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Huesped</th>
                  <th>Programada</th>
                  <th>Estatus</th>
                  <th>Participantes</th>
                  <th>Reservaciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($activityBookings as $booking):
                  $guestName = isset($booking['linked_guest_names']) ? trim((string)$booking['linked_guest_names']) : '';
                  if ($guestName === '') {
                    $guestName = trim((isset($booking['guest_names']) ? $booking['guest_names'] : '') . ' ' . (isset($booking['guest_last_name']) ? $booking['guest_last_name'] : ''));
                  }
                  $linkedCodes = isset($booking['linked_reservation_codes']) ? trim((string)$booking['linked_reservation_codes']) : '';
                  $linkedIds = $parseReservationIds(isset($booking['linked_reservation_ids_csv']) ? (string)$booking['linked_reservation_ids_csv'] : '');
                  $firstReservationId = $linkedIds ? (int)$linkedIds[0] : (isset($booking['id_reservation']) ? (int)$booking['id_reservation'] : 0);
                ?>
                  <tr>
                    <td><?php echo htmlspecialchars($guestName !== '' ? $guestName : 'Sin huesped', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(isset($booking['scheduled_at']) ? (string)$booking['scheduled_at'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(isset($booking['status']) ? (string)$booking['status'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int)$booking['num_adults']; ?> + <?php echo (int)$booking['num_children']; ?></td>
                    <td>
                      <?php if ($firstReservationId > 0): ?>
                        <a class="button-link" href="index.php?view=reservations&reservation_id=<?php echo $firstReservationId; ?>">Ver reserva</a>
                        <?php if ($linkedCodes !== ''): ?>
                          <span class="muted"><?php echo htmlspecialchars($linkedCodes, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="muted">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="muted">La actividad no tiene reservaciones recientes.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    $panelContent = ob_get_clean();
    $dynamicTabs[] = array(
        'key' => 'activity:' . $suffix,
        'title' => $tabLabel !== '' ? $tabLabel : 'Actividad',
        'panel_id' => $panelId,
        'close_form_id' => $closeFormId,
        'content' => $panelContent
    );
}

$staticTabs = array(
    array(
        'id' => 'general',
        'title' => 'General',
        'content' => $listContent
    )
);
$calendarDaysData = array();
$calendarBookings = array();
try {
    $calendarSets = pms_call_procedure('sp_activity_bookings_list', array(
        null,
        $companyId,
        null,
        null,
        $filters['property_code'] === '' ? null : $filters['property_code'],
        null,
        null,
        $calendarStart,
        $calendarEnd,
        null
    ));
    $calendarBookings = isset($calendarSets[0]) ? $calendarSets[0] : array();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
}

$bookingsByDate = array();
foreach ($calendarBookings as $booking) {
    $scheduledAt = isset($booking['scheduled_at']) ? (string)$booking['scheduled_at'] : '';
    if ($scheduledAt === '') {
        continue;
    }
    $dateKey = substr($scheduledAt, 0, 10);
    if (!isset($bookingsByDate[$dateKey])) {
        $bookingsByDate[$dateKey] = array();
    }
    $bookingsByDate[$dateKey][] = $booking;
}

for ($i = 0; $i < $calendarDays; $i++) {
    $dayObj = clone $calendarStartObj;
    if ($i > 0) {
        $dayObj->modify('+' . $i . ' days');
    }
    $dateKey = $dayObj->format('Y-m-d');
    $calendarDaysData[] = array(
        'date_key' => $dateKey,
        'label' => $dayObj->format('d M'),
        'bookings' => isset($bookingsByDate[$dateKey]) ? $bookingsByDate[$dateKey] : array()
    );
}
$tourBookings = array();
try {
    $tourSets = pms_call_procedure('sp_activity_bookings_list', array(
        'tour',
        $companyId,
        null,
        null,
        $filters['property_code'] === '' ? null : $filters['property_code'],
        null,
        null,
        $calendarStart . ' 00:00:00',
        $calendarEnd . ' 00:00:00',
        null
    ));
    $tourBookings = isset($tourSets[0]) ? $tourSets[0] : array();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
}
?>
<div class="reservation-tabs activities-tabs" data-reservation-tabs="activities">
  <div class="reservation-tab-nav">
    <button type="button" class="reservation-tab-trigger <?php echo $activeTab === 'activities-tab-calendar' ? 'is-active' : ''; ?>" data-tab-target="activities-tab-calendar">Calendario</button>
    <button type="button" class="reservation-tab-trigger <?php echo $activeTab === 'activities-tab-tours' ? 'is-active' : ''; ?>" data-tab-target="activities-tab-tours">Tours</button>
    <button type="button" class="reservation-tab-trigger <?php echo $activeTab === 'activities-tab-admin' ? 'is-active' : ''; ?>" data-tab-target="activities-tab-admin">Actividades</button>
  </div>
  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php elseif ($message): ?>
    <p<?php echo ' class="success"'; ?>><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <div class="reservation-tab-panel <?php echo $activeTab === 'activities-tab-calendar' ? 'is-active' : ''; ?>" id="activities-tab-calendar" data-tab-panel>
    <section class="card activity-calendar-card">
      <div class="activity-calendar-header">
        <div class="activity-calendar-title">
          <h2>Calendario de actividad</h2>
          <p class="muted"><?php echo htmlspecialchars($calendarRangeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <form method="post" class="activity-calendar-controls">
          <input type="hidden" name="activities_active_tab" value="activities-tab-calendar">
          <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
          <label>
            Propiedad
            <select name="activities_filter_property">
              <option value="">Todas</option>
              <?php foreach ($properties as $property):
                $code = isset($property['code']) ? (string)$property['code'] : '';
                $name = isset($property['name']) ? (string)$property['name'] : '';
              ?>
                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $filters['property_code'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Desde
            <input type="date" name="activity_calendar_start" value="<?php echo htmlspecialchars($calendarStart, ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>
            Periodo
            <select name="activity_calendar_days">
              <?php foreach (array(7, 14, 21, 30) as $daysOption): ?>
                <option value="<?php echo $daysOption; ?>" <?php echo $calendarDays === $daysOption ? 'selected' : ''; ?>>
                  <?php echo $daysOption; ?> dias
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="form-actions">
            <button type="submit">Actualizar</button>
          </div>
        </form>
      </div>
      <div class="activity-calendar-layout">
          <div class="activity-calendar-grid">
            <?php foreach ($calendarDaysData as $day):
              $bookings = isset($day['bookings']) ? $day['bookings'] : array();
              $bookingCount = count($bookings);
            ?>
              <div class="activity-calendar-day">
                <div class="activity-calendar-day-header">
                  <span><?php echo htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="activity-calendar-count"><?php echo $bookingCount; ?> reservas</span>
                </div>
                <?php if ($bookingCount > 0): ?>
                  <ul class="activity-calendar-list">
                    <?php foreach ($bookings as $booking):
                      $scheduledAt = isset($booking['scheduled_at']) ? (string)$booking['scheduled_at'] : '';
                      $timeLabel = $scheduledAt !== '' ? substr($scheduledAt, 11, 5) : '';
                      $bookingId = isset($booking['id_booking']) ? (int)$booking['id_booking'] : 0;
                      $linkedCodes = isset($booking['linked_reservation_codes']) ? trim((string)$booking['linked_reservation_codes']) : '';
                      $linkedGuests = isset($booking['linked_guest_names']) ? trim((string)$booking['linked_guest_names']) : '';
                      $linkedReservationCount = isset($booking['linked_reservation_count']) ? (int)$booking['linked_reservation_count'] : 0;
                      $linkedIds = $parseReservationIds(isset($booking['linked_reservation_ids_csv']) ? (string)$booking['linked_reservation_ids_csv'] : '');
                      $reservationId = $linkedIds ? (int)$linkedIds[0] : (isset($booking['id_reservation']) ? (int)$booking['id_reservation'] : 0);
                      $activityName = isset($booking['activity_name']) ? (string)$booking['activity_name'] : '';
                      $activityProperty = isset($booking['property_name']) ? (string)$booking['property_name'] : '';
                      $guestName = $linkedGuests !== ''
                        ? $linkedGuests
                        : trim((isset($booking['guest_names']) ? $booking['guest_names'] : '') . ' ' . (isset($booking['guest_last_name']) ? $booking['guest_last_name'] : ''));
                    ?>
                      <li class="activity-calendar-item">
                        <span class="activity-calendar-time"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="activity-calendar-activity">
                          <?php echo htmlspecialchars($activityName !== '' ? $activityName : 'Actividad', ENT_QUOTES, 'UTF-8'); ?>
                          <?php if ($activityProperty !== ''): ?>
                            <span class="muted">- <?php echo htmlspecialchars($activityProperty, ENT_QUOTES, 'UTF-8'); ?></span>
                          <?php endif; ?>
                        </span>
                        <span class="activity-calendar-reservation"><?php echo htmlspecialchars($linkedCodes !== '' ? $linkedCodes : 'Sin reserva', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="activity-calendar-guest"><?php echo htmlspecialchars($guestName !== '' ? $guestName : 'Sin huesped', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($linkedReservationCount > 1): ?>
                          <span class="muted"><?php echo $linkedReservationCount; ?> reservaciones ligadas</span>
                        <?php endif; ?>
                        <?php if ($reservationId > 0): ?>
                          <a class="button-link" href="index.php?view=reservations&reservation_id=<?php echo $reservationId; ?>">Ver reserva</a>
                        <?php endif; ?>
                        <div class="activity-calendar-item-actions">
                          <form method="post">
                            <input type="hidden" name="activities_action" value="edit_activity_booking">
                            <input type="hidden" name="activities_active_tab" value="activities-tab-calendar">
                            <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
                            <input type="hidden" name="activity_calendar_start" value="<?php echo htmlspecialchars($calendarStart, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="activity_calendar_days" value="<?php echo (int)$calendarDays; ?>">
                            <input type="hidden" name="activity_booking_id" value="<?php echo $bookingId; ?>">
                            <button type="submit" class="button-secondary">Editar</button>
                          </form>
                          <form method="post">
                            <input type="hidden" name="activities_action" value="cancel_activity_booking">
                            <input type="hidden" name="activities_active_tab" value="activities-tab-calendar">
                            <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
                            <input type="hidden" name="activity_calendar_start" value="<?php echo htmlspecialchars($calendarStart, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="activity_calendar_days" value="<?php echo (int)$calendarDays; ?>">
                            <input type="hidden" name="activity_booking_id" value="<?php echo $bookingId; ?>">
                            <button type="submit" class="button-secondary" onclick="return confirm('Cancelar este booking?');">Cancelar</button>
                          </form>
                          <form method="post">
                            <input type="hidden" name="activities_action" value="delete_activity_booking">
                            <input type="hidden" name="activities_active_tab" value="activities-tab-calendar">
                            <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
                            <input type="hidden" name="activity_calendar_start" value="<?php echo htmlspecialchars($calendarStart, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="activity_calendar_days" value="<?php echo (int)$calendarDays; ?>">
                            <input type="hidden" name="activity_booking_id" value="<?php echo $bookingId; ?>">
                            <button type="submit" class="button-secondary" onclick="return confirm('Eliminar este booking?');">Eliminar</button>
                          </form>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <p class="muted">Sin reservaciones.</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="activity-calendar-panel">
            <h3><?php echo $scheduleForm['booking_id'] > 0 ? ('Editar booking #' . (int)$scheduleForm['booking_id']) : 'Programar booking'; ?></h3>
            <form method="post" class="form-grid">
              <input type="hidden" name="activities_action" value="save_activity_booking">
              <input type="hidden" name="activities_active_tab" value="activities-tab-calendar">
              <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
              <input type="hidden" name="activity_calendar_start" value="<?php echo htmlspecialchars($calendarStart, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="activity_calendar_days" value="<?php echo (int)$calendarDays; ?>">
              <input type="hidden" name="activity_booking_id" value="<?php echo (int)$scheduleForm['booking_id']; ?>">
              <label>
                Actividad
                <select name="activity_id" <?php echo $hasBookableActivities ? 'required' : 'disabled'; ?>>
                  <option value="">Selecciona una actividad</option>
                  <?php foreach ($bookableActivities as $row):
                    $idActivity = isset($row['id_activity']) ? (int)$row['id_activity'] : 0;
                    $activityName = isset($row['activity_name']) ? (string)$row['activity_name'] : '';
                    $propertyName = isset($row['property_name']) ? (string)$row['property_name'] : '';
                    $label = $propertyName !== '' ? $activityName . ' - ' . $propertyName : $activityName;
                    $selected = $scheduleForm['activity_id'] === $idActivity;
                  ?>
                    <option value="<?php echo $idActivity; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label !== '' ? $label : 'Actividad', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="full">
                Buscar reservacion (huesped o telefono)
                <input type="text" id="activity-reservation-search" placeholder="Ej. Ana Perez o 5551234567">
              </label>
              <label class="full">
                Reservaciones (multiseleccion)
                <select name="activity_reservation_ids[]" id="activity-reservation-select" <?php echo $reservationOptions ? 'required' : 'disabled'; ?> multiple size="10">
                  <?php if ($reservationOptions): ?>
                    <?php foreach ($reservationOptions as $reservation):
                      $reservationId = isset($reservation['id_reservation']) ? (int)$reservation['id_reservation'] : 0;
                      $reservationCode = isset($reservation['reservation_code']) ? (string)$reservation['reservation_code'] : '';
                      $guestName = trim((isset($reservation['guest_names']) ? $reservation['guest_names'] : '') . ' ' . (isset($reservation['guest_last_name']) ? $reservation['guest_last_name'] : ''));
                      $guestPhone = isset($reservation['guest_phone']) ? (string)$reservation['guest_phone'] : '';
                      $propertyCode = isset($reservation['property_code']) ? (string)$reservation['property_code'] : '';
                      $checkIn = isset($reservation['check_in_date']) ? (string)$reservation['check_in_date'] : '';
                      $checkOut = isset($reservation['check_out_date']) ? (string)$reservation['check_out_date'] : '';
                      $labelParts = array_filter(array(
                        $reservationCode,
                        $guestName !== '' ? $guestName : null,
                        $propertyCode !== '' ? $propertyCode : null,
                        $checkIn !== '' && $checkOut !== '' ? ($checkIn . ' / ' . $checkOut) : null
                      ));
                      $searchText = strtolower(trim($reservationCode . ' ' . $guestName . ' ' . $guestPhone));
                      $selected = in_array($reservationId, $scheduleForm['reservation_ids'], true);
                    ?>
                      <option value="<?php echo $reservationId; ?>"
                        data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $selected ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(implode(' - ', $labelParts), ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="">No hay reservaciones en este periodo.</option>
                  <?php endif; ?>
                </select>
                <small id="activity-reservation-selected-count" class="muted"></small>
              </label>
              <label>
                Fecha y hora
                <input type="datetime-local" name="activity_scheduled_at" value="<?php echo htmlspecialchars($scheduleForm['scheduled_at'], ENT_QUOTES, 'UTF-8'); ?>" required>
              </label>
              <label>
                Adultos
                <input type="number" name="activity_num_adults" min="0" value="<?php echo (int)$scheduleForm['num_adults']; ?>">
              </label>
              <label>
                Ninos
                <input type="number" name="activity_num_children" min="0" value="<?php echo (int)$scheduleForm['num_children']; ?>">
              </label>
              <label>
                Estatus
                <select name="activity_booking_status">
                  <?php foreach (array('pending', 'confirmed', 'cancelled', 'no_show', 'completed') as $statusOption): ?>
                    <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $scheduleForm['status'] === $statusOption ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="full">
                Notas
                <textarea name="activity_booking_notes" rows="2"><?php echo htmlspecialchars($scheduleForm['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              </label>
              <?php if (!$hasBookableActivities): ?>
                <p class="muted">No hay actividades activas para programar.</p>
              <?php endif; ?>
              <div class="form-actions">
                <button type="submit" <?php echo $hasBookableActivities && $reservationOptions ? '' : 'disabled'; ?>>
                  <?php echo $scheduleForm['booking_id'] > 0 ? 'Guardar cambios' : 'Programar'; ?>
                </button>
                <?php if ($scheduleForm['booking_id'] > 0): ?>
                  <button type="submit" class="button-secondary" onclick="this.form.activities_action.value='edit_activity_booking'; this.form.activity_booking_id.value='0';">Nuevo</button>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
    </section>

    <section class="card">
      <h2>Actividades vibe</h2>
      <?php if ($vibeActivities): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Nombre</th>
                <th>Propiedad</th>
                <th>Concepto</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vibeActivities as $row):
                $idActivity = isset($row['id_activity']) ? (int)$row['id_activity'] : 0;
                $isSelected = $idActivity === $selectedActivityId;
                $saleItemName = isset($row['sale_item_name']) ? (string)$row['sale_item_name'] : '';
                $saleItemCategory = isset($row['sale_item_category']) ? (string)$row['sale_item_category'] : '';
                $conceptLabel = $saleItemName !== ''
                    ? ($saleItemCategory !== '' ? $saleItemCategory . ' - ' . $saleItemName : $saleItemName)
                    : 'Sin concepto';
              ?>
                <tr class="<?php echo $isSelected ? 'is-selected' : ''; ?>">
                  <td><?php echo htmlspecialchars(isset($row['activity_code']) ? (string)$row['activity_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(isset($row['activity_name']) ? (string)$row['activity_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(isset($row['property_name']) ? (string)$row['property_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <div class="list-actions">
                      <form method="post">
                        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                        <input type="hidden" name="activities_active_tab" value="activities-tab-admin">
                        <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
                        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="activity:<?php echo $idActivity; ?>">
                        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:activity:<?php echo $idActivity; ?>">
                        <button type="submit">Editar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">No hay actividades vibe registradas.</p>
      <?php endif; ?>
    </section>
  </div>

  <div class="reservation-tab-panel <?php echo $activeTab === 'activities-tab-tours' ? 'is-active' : ''; ?>" id="activities-tab-tours" data-tab-panel>
    <form method="post" class="activity-calendar-controls activity-tours-controls">
      <input type="hidden" name="activities_active_tab" value="activities-tab-tours">
      <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
      <label>
        Propiedad
        <select name="activities_filter_property">
          <option value="">Todas</option>
          <?php foreach ($properties as $property):
            $code = isset($property['code']) ? (string)$property['code'] : '';
            $name = isset($property['name']) ? (string)$property['name'] : '';
          ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $filters['property_code'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Desde
        <input type="date" name="activity_calendar_start" value="<?php echo htmlspecialchars($calendarStart, ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Periodo
        <select name="activity_calendar_days">
          <?php foreach (array(7, 14, 21, 30) as $daysOption): ?>
            <option value="<?php echo $daysOption; ?>" <?php echo $calendarDays === $daysOption ? 'selected' : ''; ?>>
              <?php echo $daysOption; ?> dias
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="form-actions">
        <button type="submit">Actualizar</button>
      </div>
    </form>
    <section class="card">
      <h2>Tours</h2>
      <?php if ($tourActivities): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Nombre</th>
                <th>Propiedad</th>
                <th>Concepto</th>
                <th>Precio</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tourActivities as $row):
                $idActivity = isset($row['id_activity']) ? (int)$row['id_activity'] : 0;
                $isSelected = $idActivity === $selectedActivityId;
                $saleItemName = isset($row['sale_item_name']) ? (string)$row['sale_item_name'] : '';
                $saleItemCategory = isset($row['sale_item_category']) ? (string)$row['sale_item_category'] : '';
                $conceptLabel = $saleItemName !== ''
                    ? ($saleItemCategory !== '' ? $saleItemCategory . ' - ' . $saleItemName : $saleItemName)
                    : 'Sin concepto';
              ?>
                <tr class="<?php echo $isSelected ? 'is-selected' : ''; ?>">
                  <td><?php echo htmlspecialchars(isset($row['activity_code']) ? (string)$row['activity_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(isset($row['activity_name']) ? (string)$row['activity_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(isset($row['property_name']) ? (string)$row['property_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo isset($row['base_price_cents']) ? number_format($row['base_price_cents'] / 100, 2) : '0.00'; ?></td>
                  <td>
                    <div class="list-actions">
                      <form method="post">
                        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                        <input type="hidden" name="activities_active_tab" value="activities-tab-admin">
                        <input type="hidden" name="activities_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="activities_show_inactive" value="<?php echo (int)$filters['show_inactive']; ?>">
                        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="activity:<?php echo $idActivity; ?>">
                        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:activity:<?php echo $idActivity; ?>">
                        <button type="submit">Editar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">No hay tours registrados.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Reservaciones con tours (<?php echo htmlspecialchars($calendarRangeLabel, ENT_QUOTES, 'UTF-8'); ?>)</h2>
      <?php if ($tourBookings): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Programada</th>
                <th>Actividad</th>
                <th>Huesped</th>
                <th>Reserva</th>
                <th>Propiedad</th>
                <th>Pax</th>
                <th>Estatus</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tourBookings as $booking):
                $guestName = isset($booking['linked_guest_names']) ? trim((string)$booking['linked_guest_names']) : '';
                if ($guestName === '') {
                  $guestName = trim((isset($booking['guest_names']) ? $booking['guest_names'] : '') . ' ' . (isset($booking['guest_last_name']) ? $booking['guest_last_name'] : ''));
                }
                $reservationCodes = isset($booking['linked_reservation_codes']) && trim((string)$booking['linked_reservation_codes']) !== ''
                  ? (string)$booking['linked_reservation_codes']
                  : (isset($booking['reservation_code']) ? (string)$booking['reservation_code'] : '');
              ?>
                <tr>
                  <td><?php echo htmlspecialchars(isset($booking['scheduled_at']) ? (string)$booking['scheduled_at'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(isset($booking['activity_name']) ? (string)$booking['activity_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($guestName !== '' ? $guestName : 'Sin huesped', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($reservationCodes, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(isset($booking['property_name']) ? (string)$booking['property_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo isset($booking['pax']) ? (int)$booking['pax'] : 0; ?></td>
                  <td><?php echo htmlspecialchars(isset($booking['status']) ? (string)$booking['status'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">No hay reservaciones de tours en este periodo.</p>
      <?php endif; ?>
    </section>
  </div>

  <div class="reservation-tab-panel <?php echo $activeTab === 'activities-tab-admin' ? 'is-active' : ''; ?>" id="activities-tab-admin" data-tab-panel>
  <?php pms_render_subtabs($moduleKey, $subtabState, $staticTabs, $dynamicTabs); ?>
</div>
</div>

<script>
(function () {
  document.querySelectorAll('[data-reservation-tabs]').forEach(function (container) {
    var triggers = container.querySelectorAll('.reservation-tab-trigger');
    var panels = container.querySelectorAll('[data-tab-panel]');
    if (!triggers.length || !panels.length) return;

    function activate(targetId) {
      triggers.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-tab-target') === targetId);
      });
      panels.forEach(function (panel) {
        panel.classList.toggle('is-active', panel.id === targetId);
      });
    }

    triggers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-tab-target');
        if (targetId) activate(targetId);
      });
    });

    var initial = container.querySelector('.reservation-tab-trigger.is-active');
    if (initial) {
      activate(initial.getAttribute('data-tab-target'));
    }
  });
})();

(function () {
  var searchInput = document.getElementById('activity-reservation-search');
  var select = document.getElementById('activity-reservation-select');
  var selectedCount = document.getElementById('activity-reservation-selected-count');
  if (!searchInput || !select) return;

  function filterOptions() {
    var query = searchInput.value.trim().toLowerCase();
    var options = select.querySelectorAll('option');
    options.forEach(function (opt) {
      if (query === '') {
        opt.hidden = false;
        return;
      }
      var haystack = (opt.getAttribute('data-search') || '').toLowerCase();
      opt.hidden = haystack.indexOf(query) === -1;
    });
  }

  function updateSelectedCount() {
    if (!selectedCount) return;
    var total = 0;
    Array.prototype.forEach.call(select.options, function (opt) {
      if (opt.selected) total += 1;
    });
    selectedCount.textContent = total > 0
      ? (total + ' reservaciones seleccionadas')
      : 'Sin reservaciones seleccionadas';
  }

  function filterAndCount() {
    filterOptions();
    updateSelectedCount();
  }

  if (searchInput) {
    searchInput.addEventListener('input', filterAndCount);
  }
  select.addEventListener('change', updateSelectedCount);
  select.addEventListener('blur', updateSelectedCount);
  window.setTimeout(function () {
    if (!select.options.length) {
      return;
    }
    filterAndCount();
  }, 0);
})();
</script>
