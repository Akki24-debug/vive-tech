<?php
$moduleKey = 'rooms';
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyId === 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('rooms.view');

$properties = pms_fetch_properties($companyId);
$propertiesByCode = array();
foreach ($properties as $property) {
    $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
    if ($code !== '') {
        $propertiesByCode[$code] = $property;
    }
}
$selectedProperty = isset($_POST['rooms_filter_property']) ? strtoupper(trim((string)$_POST['rooms_filter_property'])) : '';
$showInactive = isset($_POST['rooms_filter_show_inactive']) ? (int)$_POST['rooms_filter_show_inactive'] : 0;
if ($selectedProperty !== '' && !isset($propertiesByCode[$selectedProperty])) {
    $selectedProperty = '';
}

$isGetRequest = isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'GET';
if ($isGetRequest) {
    $openRoom = isset($_GET['open_room']) ? trim((string)$_GET['open_room']) : '';
    $filterPropertyFromGet = isset($_GET['rooms_filter_property']) ? strtoupper(trim((string)$_GET['rooms_filter_property'])) : '';
    if ($filterPropertyFromGet !== '' && isset($propertiesByCode[$filterPropertyFromGet])) {
        $_POST['rooms_filter_property'] = $filterPropertyFromGet;
        $selectedProperty = $filterPropertyFromGet;
    }
    if ($openRoom !== '') {
        $_POST[$moduleKey . '_subtab_action'] = 'open';
        $_POST[$moduleKey . '_subtab_target'] = 'room:' . $openRoom;
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:room:' . $openRoom;
    }
}

$message = null;
$error = null;
$roomCategoryCatalog = array();
$roomCategoryCodesByProperty = array();

try {
    $pdoCategoryCatalog = pms_get_connection();
    $stmtCategoryCatalog = $pdoCategoryCatalog->prepare(
        'SELECT p.code AS property_code,
                rc.code AS category_code,
                rc.name AS category_name,
                rc.is_active
         FROM roomcategory rc
         JOIN property p ON p.id_property = rc.id_property
         WHERE p.id_company = ? AND rc.deleted_at IS NULL
         ORDER BY p.order_index, p.name, rc.order_index, rc.name'
    );
    $stmtCategoryCatalog->execute(array($companyId));
    $categoryRows = $stmtCategoryCatalog->fetchAll();
    foreach ($categoryRows as $catRow) {
        $propertyCode = isset($catRow['property_code']) ? strtoupper(trim((string)$catRow['property_code'])) : '';
        $categoryCode = isset($catRow['category_code']) ? trim((string)$catRow['category_code']) : '';
        $categoryCodeKey = strtoupper($categoryCode);
        $categoryName = isset($catRow['category_name']) ? trim((string)$catRow['category_name']) : '';
        $isActive = !isset($catRow['is_active']) || (int)$catRow['is_active'] === 1 ? 1 : 0;
        if ($propertyCode === '' || $categoryCode === '' || $categoryCodeKey === '') {
            continue;
        }
        if (!isset($roomCategoryCatalog[$propertyCode])) {
            $roomCategoryCatalog[$propertyCode] = array();
        }
        $roomCategoryCatalog[$propertyCode][] = array(
            'code' => $categoryCode,
            'name' => $categoryName,
            'is_active' => $isActive
        );
        if (!isset($roomCategoryCodesByProperty[$propertyCode])) {
            $roomCategoryCodesByProperty[$propertyCode] = array();
        }
        $roomCategoryCodesByProperty[$propertyCode][$categoryCodeKey] = true;
    }
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
}

// Subtabs state
$subtabState = pms_subtabs_init($moduleKey, 'static:list');
$openRoomKeys = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'room:') === 0) {
        $key = substr($openKey, strlen('room:'));
        if ($key !== '' && !in_array($key, $openRoomKeys, true)) {
            $openRoomKeys[] = $key;
        }
    }
}

$action = isset($_POST['rooms_action']) ? (string)$_POST['rooms_action'] : '';
$cloneRoomCode = isset($_POST['rooms_clone_code']) ? strtoupper(trim((string)$_POST['rooms_clone_code'])) : '';
$cloneRoomProperty = isset($_POST['rooms_clone_property']) ? strtoupper(trim((string)$_POST['rooms_clone_property'])) : '';
$newRoomCategoryCode = isset($_POST['rooms_new_category_code']) ? strtoupper(trim((string)$_POST['rooms_new_category_code'])) : '';
$newRoomPropertyCode = isset($_POST['rooms_new_property_code']) ? strtoupper(trim((string)$_POST['rooms_new_property_code'])) : '';
$postedSubtabAction = isset($_POST[$moduleKey . '_subtab_action']) ? (string)$_POST[$moduleKey . '_subtab_action'] : '';
$postedSubtabTarget = isset($_POST[$moduleKey . '_subtab_target']) ? (string)$_POST[$moduleKey . '_subtab_target'] : '';
if (in_array($action, array('new_room', 'duplicate_room'), true)) {
    pms_require_permission('rooms.create');
} elseif ($action === 'save_room') {
    $incomingRoomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    pms_require_permission($incomingRoomId > 0 ? 'rooms.edit' : 'rooms.create');
} elseif ($action === 'update_order') {
    pms_require_permission('rooms.edit');
}
if ($postedSubtabAction === 'open' && strpos($postedSubtabTarget, 'room:') === 0) {
    $requestedRoomKey = substr($postedSubtabTarget, strlen('room:'));
    if ($requestedRoomKey !== '' && $requestedRoomKey !== '__new__') {
        try {
            $requestedRoom = rooms_fetch_detail($companyId, $requestedRoomKey, $selectedProperty);
            if ($requestedRoom && isset($requestedRoom['property_code'])) {
                $requestedPropertyCode = strtoupper(trim((string)$requestedRoom['property_code']));
                if ($requestedPropertyCode !== '' && isset($propertiesByCode[$requestedPropertyCode])) {
                    $selectedProperty = $requestedPropertyCode;
                }
            }
        } catch (Exception $e) {
            // ignore hydration issues here; normal flow will surface errors later if needed
        }
    }
}
if ($action === 'new_room') {
    if ($newRoomPropertyCode !== '' && isset($propertiesByCode[$newRoomPropertyCode])) {
        $selectedProperty = $newRoomPropertyCode;
    }
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'room:__new__';
} elseif ($action === 'duplicate_room') {
    if ($cloneRoomProperty !== '') {
        $selectedProperty = $cloneRoomProperty;
    }
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'room:__new__';
} elseif ($action === 'update_order') {
    $roomId = isset($_POST['room_order_id']) ? (int)$_POST['room_order_id'] : 0;
    $orderValue = isset($_POST['room_order_index']) && $_POST['room_order_index'] !== ''
        ? (int)$_POST['room_order_index']
        : 0;
    if ($roomId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE room r
                 JOIN property p ON p.id_property = r.id_property
                 SET r.order_index = ?, r.updated_at = NOW()
                 WHERE r.id_room = ? AND p.id_company = ? AND r.deleted_at IS NULL'
            );
            $stmt->execute(array($orderValue, $roomId, $companyId));
            $message = 'Orden actualizado.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'save_room') {
    $propertyCode = isset($_POST['room_property_code']) ? strtoupper(trim((string)$_POST['room_property_code'])) : $selectedProperty;
    $roomCode = isset($_POST['room_code']) ? strtoupper(trim((string)$_POST['room_code'])) : '';
    $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $name = isset($_POST['room_name']) ? trim((string)$_POST['room_name']) : '';
    $categoryCode = isset($_POST['room_category_code']) ? trim((string)$_POST['room_category_code']) : '';
    $categoryCodeKey = strtoupper($categoryCode);
    $rateplanCode = isset($_POST['room_rateplan_code']) ? trim((string)$_POST['room_rateplan_code']) : '';
    $description = isset($_POST['room_description']) ? trim((string)$_POST['room_description']) : '';
    $status = isset($_POST['room_status']) ? trim((string)$_POST['room_status']) : '';
    $hkStatus = isset($_POST['room_hk_status']) ? trim((string)$_POST['room_hk_status']) : '';
    $capacity = isset($_POST['room_capacity_total']) && $_POST['room_capacity_total'] !== '' ? (int)$_POST['room_capacity_total'] : null;
    $maxAdults = isset($_POST['room_max_adults']) && $_POST['room_max_adults'] !== '' ? (int)$_POST['room_max_adults'] : null;
    $maxChildren = isset($_POST['room_max_children']) && $_POST['room_max_children'] !== '' ? (int)$_POST['room_max_children'] : null;
    $floor = isset($_POST['room_floor']) ? trim((string)$_POST['room_floor']) : '';
    $building = isset($_POST['room_building']) ? trim((string)$_POST['room_building']) : '';
    $bedConfig = isset($_POST['room_bed_config']) ? trim((string)$_POST['room_bed_config']) : '';
    $colorHex = isset($_POST['room_color_hex']) ? trim((string)$_POST['room_color_hex']) : '';
    $orderIndex = isset($_POST['room_order_index']) && $_POST['room_order_index'] !== '' ? (int)$_POST['room_order_index'] : null;
    $isActive = isset($_POST['room_is_active']) ? 1 : 0;

    if ($propertyCode === '' || $roomCode === '' || $name === '') {
        $error = 'Propiedad, codigo y nombre son obligatorios.';
    } else {
        try {
            $pdo = pms_get_connection();
            $duplicateSql = 'SELECT r.id_room
                FROM room r
                JOIN property p ON p.id_property = r.id_property
                WHERE p.code = ? AND r.code = ? AND r.deleted_at IS NULL AND r.is_active = 1';
            $duplicateParams = array($propertyCode, $roomCode);
            if ($roomId > 0) {
                $duplicateSql .= ' AND r.id_room <> ?';
                $duplicateParams[] = $roomId;
            }
            $duplicateSql .= ' LIMIT 1';
            $stmt = $pdo->prepare($duplicateSql);
            $stmt->execute($duplicateParams);
            if ($stmt->fetchColumn() !== false) {
                throw new Exception('Ya existe una habitacion activa con ese codigo.');
            }
            if ($categoryCode !== '') {
                $categoryExists = isset($roomCategoryCodesByProperty[$propertyCode])
                    && isset($roomCategoryCodesByProperty[$propertyCode][$categoryCodeKey]);
                if (!$categoryExists) {
                    throw new Exception('Selecciona una categoria valida del catalogo de la propiedad.');
                }
            }

            pms_call_procedure('sp_room_upsert', array(
                $propertyCode,
                $roomCode,
                $categoryCode === '' ? null : $categoryCode,
                $rateplanCode === '' ? null : $rateplanCode,
                $name,
                $description === '' ? null : $description,
                $capacity,
                $maxAdults,
                $maxChildren,
                $status === '' ? null : $status,
                $hkStatus === '' ? null : $hkStatus,
                $floor === '' ? null : $floor,
                $building === '' ? null : $building,
                $bedConfig === '' ? null : $bedConfig,
                $colorHex === '' ? null : $colorHex,
                $orderIndex,
                $isActive,
                $roomId > 0 ? $roomId : null
            ));
            $message = 'Habitacion guardada.';
            // cerrar y volver a lista
            $targetCloseKey = $roomCode;
            $currentSubtabPosted = isset($_POST[$moduleKey . '_current_subtab']) ? (string)$_POST[$moduleKey . '_current_subtab'] : '';
            if (strpos($currentSubtabPosted, 'dynamic:room:') === 0) {
                $parsedKey = substr($currentSubtabPosted, strlen('dynamic:room:'));
                if ($parsedKey !== '') {
                    $targetCloseKey = $parsedKey;
                }
            }
            $_POST[$moduleKey . '_subtab_action'] = 'activate';
            $_POST[$moduleKey . '_subtab_target'] = 'static:list';
            $_POST[$moduleKey . '_subtab_target_close'] = 'room:' . $targetCloseKey;
            $selectedProperty = $propertyCode;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Refresh subtab state after actions
$subtabState = pms_subtabs_init($moduleKey, 'static:list');
if (isset($_POST[$moduleKey . '_subtab_target_close']) && $_POST[$moduleKey . '_subtab_target_close'] !== '') {
    $toClose = (string)$_POST[$moduleKey . '_subtab_target_close'];
    if (isset($subtabState['open']) && is_array($subtabState['open'])) {
        $subtabState['open'] = array_values(array_filter($subtabState['open'], function ($item) use ($toClose) {
            return $item !== $toClose;
        }));
    }
    $subtabState['active'] = 'static:list';
    $_SESSION['pms_subtabs'][$moduleKey] = $subtabState;
}

$openRoomKeys = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'room:') === 0) {
        $key = substr($openKey, strlen('room:'));
        if ($key !== '' && !in_array($key, $openRoomKeys, true)) {
            $openRoomKeys[] = $key;
        }
    }
}

$ratePlans = array();
$categories = array();
$roomsList = array();
$roomsListFallback = array();

if ($selectedProperty !== '') {
    try {
        $sets = pms_call_procedure('sp_portal_property_data', array(
            $companyCode,
            null,
            0,
            $selectedProperty
        ));
        $ratePlans = isset($sets[2]) ? $sets[2] : array();
        $categories = isset($sets[3]) ? $sets[3] : array();
        $roomsListFallback = isset($sets[4]) ? $sets[4] : array();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
    try {
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT rm.id_room,
                    rm.code AS room_code,
                    rm.name AS room_name,
                    rm.status,
                    rm.capacity_total,
                    rm.order_index,
                    rm.is_active,
                    rc.code AS category_code,
                    rc.name AS category_name,
                    rp.code AS rateplan_code,
                    rp.name AS rateplan_name,
                    p.code AS property_code,
                    p.name AS property_name
             FROM room rm
             JOIN property p ON p.id_property = rm.id_property
             LEFT JOIN roomcategory rc ON rc.id_category = rm.id_category
             LEFT JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan
             WHERE p.code = ? AND p.id_company = ? AND rm.deleted_at IS NULL
             ORDER BY rc.order_index, rm.order_index, rm.code'
        );
        $stmt->execute(array($selectedProperty, $companyId));
        $roomsList = $stmt->fetchAll();
    } catch (Exception $e) {
        if ($roomsListFallback) {
            $roomsList = $roomsListFallback;
        } else {
            $error = $error ? $error : $e->getMessage();
        }
    }
} else {
    try {
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT rm.id_room,
                    rm.code AS room_code,
                    rm.name AS room_name,
                    rm.status,
                    rm.capacity_total,
                    rm.order_index,
                    rm.is_active,
                    rc.code AS category_code,
                    rc.name AS category_name,
                    rp.code AS rateplan_code,
                    rp.name AS rateplan_name,
                    p.code AS property_code,
                    p.name AS property_name
             FROM room rm
             JOIN property p ON p.id_property = rm.id_property
             LEFT JOIN roomcategory rc ON rc.id_category = rm.id_category
             LEFT JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan
             WHERE p.id_company = ? AND rm.deleted_at IS NULL
             ORDER BY p.order_index, p.name, rc.order_index, rm.order_index, rm.code'
        );
        $stmt->execute(array($companyId));
        $roomsList = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
}

if ($selectedProperty !== '' && $roomsList) {
    $propertyLabel = isset($propertiesByCode[$selectedProperty]['name']) ? (string)$propertiesByCode[$selectedProperty]['name'] : $selectedProperty;
    foreach ($roomsList as &$roomRow) {
        if (!isset($roomRow['property_code']) || $roomRow['property_code'] === '') {
            $roomRow['property_code'] = $selectedProperty;
        }
        if (!isset($roomRow['property_name']) || $roomRow['property_name'] === '') {
            $roomRow['property_name'] = $propertyLabel;
        }
    }
    unset($roomRow);
}

if ($openRoomKeys) {
    $roomIdsByKey = array();
    $roomRowsByCode = array();
    $ambiguousLegacyKeys = array();
    foreach ($roomsList as $roomRow) {
        $roomId = isset($roomRow['id_room']) ? (int)$roomRow['id_room'] : 0;
        $roomCode = isset($roomRow['room_code']) ? (string)$roomRow['room_code'] : '';
        if ($roomId > 0) {
            $roomIdsByKey[(string)$roomId] = true;
        }
        if ($roomCode !== '') {
            if (!isset($roomRowsByCode[$roomCode])) {
                $roomRowsByCode[$roomCode] = array();
            }
            $roomRowsByCode[$roomCode][] = $roomRow;
        }
    }

    $openKeyMap = array();
    $remappedOpenRoomKeys = array();
    foreach ($openRoomKeys as $openKey) {
        $rawKey = (string)$openKey;
        $mappedKey = $rawKey;
        if ($rawKey !== '__new__') {
            if (isset($roomIdsByKey[$rawKey])) {
                $mappedKey = $rawKey;
            } elseif (isset($roomRowsByCode[$rawKey])) {
                $candidates = $roomRowsByCode[$rawKey];
                $resolved = null;
                if ($selectedProperty !== '') {
                    foreach ($candidates as $candidateRow) {
                        $candidateProperty = isset($candidateRow['property_code']) ? strtoupper((string)$candidateRow['property_code']) : '';
                        if ($candidateProperty === $selectedProperty) {
                            $resolved = $candidateRow;
                            break;
                        }
                    }
                }
                if ($resolved === null && count($candidates) === 1) {
                    $resolved = $candidates[0];
                }
                if ($resolved !== null) {
                    $resolvedId = isset($resolved['id_room']) ? (int)$resolved['id_room'] : 0;
                    $mappedKey = $resolvedId > 0 ? (string)$resolvedId : $rawKey;
                } else {
                    $mappedKey = '';
                    if (count($candidates) > 1) {
                        $ambiguousLegacyKeys[] = $rawKey;
                    }
                }
            } elseif (ctype_digit($rawKey)) {
                // Keep numeric keys even if not in current filtered list; detail is resolved by id later.
                $mappedKey = $rawKey;
            } else {
                $mappedKey = '';
            }
        }
        $openKeyMap[$rawKey] = $mappedKey;
        if ($mappedKey !== '' && !in_array($mappedKey, $remappedOpenRoomKeys, true)) {
            $remappedOpenRoomKeys[] = $mappedKey;
        }
    }

    if ($remappedOpenRoomKeys !== $openRoomKeys) {
        $openRoomKeys = $remappedOpenRoomKeys;
        $subtabState['open'] = array();
        foreach ($openRoomKeys as $mappedKey) {
            $subtabState['open'][] = 'room:' . $mappedKey;
        }
        $activeKey = isset($subtabState['active']) ? (string)$subtabState['active'] : '';
        if (strpos($activeKey, 'dynamic:room:') === 0) {
            $activeRawKey = substr($activeKey, strlen('dynamic:room:'));
            $activeMapped = isset($openKeyMap[$activeRawKey]) ? (string)$openKeyMap[$activeRawKey] : $activeRawKey;
            if ($activeMapped === '' || !in_array($activeMapped, $openRoomKeys, true)) {
                $subtabState['active'] = 'static:list';
            } else {
                $subtabState['active'] = 'dynamic:room:' . $activeMapped;
            }
        }
        $_SESSION['pms_subtabs'][$moduleKey] = $subtabState;
    }
    if ($ambiguousLegacyKeys && !$message) {
        $message = 'Se limpiaron pestanas antiguas de habitaciones con codigo duplicado. Abrelas de nuevo desde la lista.';
    }
}

function rooms_find_detail(array $rooms, $key, $propertyCode = '')
{
    $searchKey = (string)$key;
    $searchProperty = strtoupper(trim((string)$propertyCode));
    $codeMatches = array();
    foreach ($rooms as $room) {
        $code = isset($room['room_code']) ? (string)$room['room_code'] : '';
        $id = isset($room['id_room']) ? (string)$room['id_room'] : '';
        if ($searchKey === $id) {
            return $room;
        }
        if ($searchKey === $code) {
            $codeMatches[] = $room;
            if ($searchProperty !== '') {
                $roomProperty = isset($room['property_code']) ? strtoupper((string)$room['property_code']) : '';
                if ($roomProperty === $searchProperty) {
                    return $room;
                }
            }
        }
    }
    if ($codeMatches) {
        return $codeMatches[0];
    }
    return null;
}

function rooms_fetch_detail($companyId, $key, $propertyCode = '')
{
    $roomKey = trim((string)$key);
    if ($roomKey === '' || $roomKey === '__new__') {
        return null;
    }

    $pdo = pms_get_connection();
    $baseSql = 'SELECT rm.id_room,
                       rm.code AS room_code,
                       rm.name AS room_name,
                       rm.description,
                       rm.status,
                       rm.housekeeping_status,
                       rm.capacity_total,
                       rm.max_adults,
                       rm.max_children,
                       rm.floor,
                       rm.building,
                       rm.bed_config,
                       rm.color_hex,
                       rm.order_index,
                       rm.is_active,
                       rc.code AS category_code,
                       rc.name AS category_name,
                       rp.code AS rateplan_code,
                       rp.name AS rateplan_name,
                       p.code AS property_code,
                       p.name AS property_name
                FROM room rm
                JOIN property p ON p.id_property = rm.id_property
                LEFT JOIN roomcategory rc ON rc.id_category = rm.id_category
                LEFT JOIN rateplan rp ON rp.id_rateplan = rm.id_rateplan
                WHERE p.id_company = ? AND rm.deleted_at IS NULL';
    $params = array((int)$companyId);

    if (ctype_digit($roomKey)) {
        $baseSql .= ' AND rm.id_room = ?';
        $params[] = (int)$roomKey;
    } else {
        $baseSql .= ' AND rm.code = ?';
        $params[] = $roomKey;
        $propertyCodeTrim = strtoupper(trim((string)$propertyCode));
        if ($propertyCodeTrim !== '') {
            $baseSql .= ' AND p.code = ?';
            $params[] = $propertyCodeTrim;
        }
    }

    $baseSql .= ' ORDER BY rm.updated_at DESC, rm.id_room DESC LIMIT 1';
    $stmt = $pdo->prepare($baseSql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? $row : null;
}

// Static tab content (list)
ob_start();
?>
<div class="tab-actions">
  <form method="post" class="form-inline">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <label>
      Propiedad
      <select name="rooms_filter_property" onchange="this.form.submit()">
        <option value="" <?php echo $selectedProperty === '' ? 'selected' : ''; ?>>Todas</option>
        <?php foreach ($properties as $property):
          $code = isset($property['code']) ? (string)$property['code'] : '';
          $name = isset($property['name']) ? (string)$property['name'] : '';
          if ($code === '') { continue; }
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $selectedProperty ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="rooms_filter_show_inactive" value="1" <?php echo $showInactive ? 'checked' : ''; ?> onchange="this.form.submit()">
      Mostrar inactivas
    </label>
  </form>
  <form method="post">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState, true); ?>
    <input type="hidden" name="rooms_action" value="new_room">
    <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:room:__new__">
    <button type="submit">Nueva habitacion</button>
  </form>
</div>

<?php if ($error): ?>
  <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($message): ?>
  <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($roomsList): ?>
  <?php
    $roomsGrouped = array();
    foreach ($roomsList as $room) {
        if (!$showInactive && isset($room['is_active']) && (int)$room['is_active'] !== 1) {
            continue;
        }
        $propCode = isset($room['property_code']) ? strtoupper((string)$room['property_code']) : $selectedProperty;
        if ($propCode === '') {
            continue;
        }
        if (!isset($roomsGrouped[$propCode])) {
            $roomsGrouped[$propCode] = array(
                'property_name' => isset($propertiesByCode[$propCode]['name']) ? (string)$propertiesByCode[$propCode]['name'] : $propCode,
                'rows' => array()
            );
        }
        $roomsGrouped[$propCode]['rows'][] = $room;
    }

    $propertyOrder = array();
    if ($selectedProperty !== '') {
        $propertyOrder[] = $selectedProperty;
    } else {
        foreach ($properties as $property) {
            $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
            if ($code !== '') {
                $propertyOrder[] = $code;
            }
        }
    }
  ?>
  <?php foreach ($propertyOrder as $propCode): ?>
    <?php if (!isset($roomsGrouped[$propCode])) { continue; } ?>
    <h3 class="property-group-title"><?php echo htmlspecialchars($roomsGrouped[$propCode]['property_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Nombre</th>
            <th>Categoria</th>
            <th>Plan</th>
            <th>Capacidad</th>
            <th>Orden</th>
            <th>Estatus</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roomsGrouped[$propCode]['rows'] as $room):
            $roomCode = isset($room['room_code']) ? (string)$room['room_code'] : '';
            $roomId = isset($room['id_room']) ? (int)$room['id_room'] : 0;
            $roomOpenKey = $roomId > 0 ? (string)$roomId : $roomCode;
            $isOpen = in_array($roomOpenKey, $openRoomKeys, true) || in_array($roomCode, $openRoomKeys, true);
          ?>
            <tr class="<?php echo $isOpen ? 'is-selected' : ''; ?>">
              <td><?php echo htmlspecialchars($roomCode, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($room['room_name']) ? (string)$room['room_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($room['category_name']) ? (string)$room['category_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($room['rateplan_name']) ? (string)$room['rateplan_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo isset($room['capacity_total']) ? (int)$room['capacity_total'] : 0; ?></td>
              <td>
                <form method="post" class="inline-form">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <input type="hidden" name="rooms_action" value="update_order">
                  <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="rooms_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
                  <input type="hidden" name="room_order_id" value="<?php echo isset($room['id_room']) ? (int)$room['id_room'] : 0; ?>">
                  <input type="number" name="room_order_index" min="0" value="<?php echo isset($room['order_index']) ? (int)$room['order_index'] : 0; ?>" onchange="this.form.submit()">
                </form>
              </td>
              <td><?php echo htmlspecialchars(isset($room['status']) ? (string)$room['status'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <div class="row-actions">
                  <form method="post">
                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState, true); ?>
                    <input type="hidden" name="rooms_action" value="">
                    <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rooms_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="room:<?php echo htmlspecialchars($roomOpenKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:room:<?php echo htmlspecialchars($roomOpenKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="button-secondary">Abrir</button>
                  </form>
                  <form method="post">
                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState, true); ?>
                    <input type="hidden" name="rooms_action" value="duplicate_room">
                    <input type="hidden" name="rooms_clone_code" value="<?php echo htmlspecialchars($roomCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rooms_clone_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rooms_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:room:__new__">
                    <button type="submit" class="button-secondary">Duplicar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <p class="muted">No hay habitaciones registradas para esta propiedad.</p>
<?php endif; ?>
<?php
$listContent = ob_get_clean();

// Dynamic tabs content
$dynamicTabs = array();
$prefillNewRoomCategoryCode = '';
if (
    $action === 'new_room'
    && $newRoomCategoryCode !== ''
    && $selectedProperty !== ''
    && isset($roomCategoryCodesByProperty[$selectedProperty])
    && isset($roomCategoryCodesByProperty[$selectedProperty][$newRoomCategoryCode])
) {
    $prefillNewRoomCategoryCode = $newRoomCategoryCode;
}
foreach ($openRoomKeys as $key) {
      if ($key === '__new__') {
          $detail = array(
              'property_code' => $selectedProperty,
              'room_code' => '',
              'room_name' => '',
              'category_code' => $prefillNewRoomCategoryCode,
              'rateplan_code' => '',
              'status' => '',
              'housekeeping_status' => '',
              'capacity_total' => '',
              'max_adults' => '',
              'max_children' => '',
              'floor' => '',
              'building' => '',
              'bed_config' => '',
              'color_hex' => '',
              'order_index' => 0,
              'description' => '',
              'is_active' => 1
          );
          $tabLabel = 'Nueva';

          if ($cloneRoomCode !== '') {
              $cloneSourceProperty = $cloneRoomProperty !== '' ? $cloneRoomProperty : $selectedProperty;
              $source = rooms_fetch_detail($companyId, $cloneRoomCode, $cloneSourceProperty);
              if (!$source) {
                  $source = rooms_find_detail($roomsList, $cloneRoomCode, $cloneSourceProperty);
              }
              if ($source) {
                  $detail = $source;
                  $detail['id_room'] = 0;
                  $detail['room_code'] = '';
                  $detail['room_name'] = trim((string)$source['room_name']) !== ''
                      ? ((string)$source['room_name'] . ' (copia)')
                      : '';
                  $detail['is_active'] = 1;
                  if ($cloneRoomProperty !== '') {
                      $selectedProperty = $cloneRoomProperty;
                  }
              }
          }
      } else {
          $detail = rooms_fetch_detail($companyId, $key, $selectedProperty);
          if (!$detail) {
              $detail = rooms_find_detail($roomsList, $key, $selectedProperty);
          }
          if (!$detail) {
              continue;
        }
        $tabLabel = isset($detail['room_code']) ? (string)$detail['room_code'] : $key;
    }
    $formPropertyCode = isset($detail['property_code']) ? strtoupper(trim((string)$detail['property_code'])) : '';
    if ($formPropertyCode === '' || !isset($propertiesByCode[$formPropertyCode])) {
        $formPropertyCode = $selectedProperty;
    }
    if (($formPropertyCode === '' || !isset($propertiesByCode[$formPropertyCode])) && $properties) {
        $firstProperty = reset($properties);
        $formPropertyCode = isset($firstProperty['code']) ? strtoupper(trim((string)$firstProperty['code'])) : '';
    }
    $detail['property_code'] = $formPropertyCode;
    $selectedCategoryCode = isset($detail['category_code']) ? trim((string)$detail['category_code']) : '';
    $detail['category_code'] = $selectedCategoryCode;
    $categoryOptionsForProperty = isset($roomCategoryCatalog[$formPropertyCode]) ? $roomCategoryCatalog[$formPropertyCode] : array();

    $panelId = 'room-panel-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $key === '__new__' ? 'new' : $key);
    $closeFormId = 'rooms-close-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $key === '__new__' ? 'new' : $key);

    ob_start();
    ?>
    <div class="subtab-actions">
      <div>
        <h3><?php echo htmlspecialchars($tabLabel === '' ? 'Habitacion' : $tabLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
      </div>
      <div class="subtab-actions">
        <form method="post" id="<?php echo htmlspecialchars($closeFormId, ENT_QUOTES, 'UTF-8'); ?>">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="room:<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="rooms_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
          <button type="submit" class="button-secondary">Cerrar</button>
        </form>
      </div>
    </div>
    <?php if ($action === 'save_room' && $error): ?>
      <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php elseif ($action === 'save_room' && $message): ?>
      <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" class="form-grid grid-3 js-room-form">
      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState, true); ?>
      <input type="hidden" name="rooms_action" value="save_room">
      <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="rooms_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
      <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:room:<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="room_id" value="<?php echo isset($detail['id_room']) ? (int)$detail['id_room'] : 0; ?>">

      <label>
        Propiedad *
        <select name="room_property_code" class="js-room-property" required>
          <?php foreach ($properties as $property):
            $pCode = isset($property['code']) ? (string)$property['code'] : '';
            $pName = isset($property['name']) ? (string)$property['name'] : '';
            if ($pCode === '') { continue; }
          ?>
            <option value="<?php echo htmlspecialchars($pCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $pCode === $formPropertyCode ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($pCode . ' - ' . $pName, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Codigo *
        <input type="text" name="room_code" required value="<?php echo htmlspecialchars((string)$detail['room_code'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Nombre *
        <input type="text" name="room_name" required value="<?php echo htmlspecialchars((string)$detail['room_name'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Categoria
        <select name="room_category_code" class="js-room-category" data-selected="<?php echo htmlspecialchars((string)$selectedCategoryCode, ENT_QUOTES, 'UTF-8'); ?>">
          <option value="">Sin categoria</option>
          <?php foreach ($categoryOptionsForProperty as $catOpt):
            $optCode = isset($catOpt['code']) ? (string)$catOpt['code'] : '';
            $optName = isset($catOpt['name']) ? (string)$catOpt['name'] : '';
            $optLabel = $optCode;
            if ($optName !== '') {
                $optLabel .= ' - ' . $optName;
            }
            if (isset($catOpt['is_active']) && (int)$catOpt['is_active'] !== 1) {
                $optLabel .= ' (inactiva)';
            }
            if ($optCode === '') { continue; }
          ?>
            <option value="<?php echo htmlspecialchars($optCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $optCode === $selectedCategoryCode ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Plan tarifario
        <input type="text" name="room_rateplan_code" list="roomRateplans" value="<?php echo htmlspecialchars((string)$detail['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Codigo de plan">
      </label>
      <label>
        Estado
        <input type="text" name="room_status" value="<?php echo htmlspecialchars((string)$detail['status'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="vacant">
      </label>
      <label>
        Estado housekeeping
        <input type="text" name="room_hk_status" value="<?php echo htmlspecialchars((string)$detail['housekeeping_status'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="clean">
      </label>
      <label>
        Capacidad total
        <input type="number" name="room_capacity_total" min="0" value="<?php echo isset($detail['capacity_total']) ? (int)$detail['capacity_total'] : ''; ?>">
      </label>
      <label>
        Max adultos
        <input type="number" name="room_max_adults" min="0" value="<?php echo isset($detail['max_adults']) ? (int)$detail['max_adults'] : ''; ?>">
      </label>
      <label>
        Max menores
        <input type="number" name="room_max_children" min="0" value="<?php echo isset($detail['max_children']) ? (int)$detail['max_children'] : ''; ?>">
      </label>
      <label>
        Piso
        <input type="text" name="room_floor" value="<?php echo htmlspecialchars((string)$detail['floor'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Edificio
        <input type="text" name="room_building" value="<?php echo htmlspecialchars((string)$detail['building'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Configuracion camas
        <input type="text" name="room_bed_config" value="<?php echo htmlspecialchars((string)$detail['bed_config'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Color
        <input type="text" name="room_color_hex" value="<?php echo htmlspecialchars((string)$detail['color_hex'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="#123456">
      </label>
      <label>
        Orden
        <input type="number" name="room_order_index" min="0" value="<?php echo isset($detail['order_index']) ? (int)$detail['order_index'] : ''; ?>">
      </label>
      <label class="checkbox">
        <input type="checkbox" name="room_is_active" value="1" <?php echo isset($detail['is_active']) && (int)$detail['is_active'] === 1 ? 'checked' : ''; ?>>
        Activa
      </label>
      <label class="full">
        Descripcion
        <textarea name="room_description" rows="3"><?php echo htmlspecialchars((string)$detail['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>

      <div class="form-actions full">
        <button type="submit">Guardar habitacion</button>
      </div>
    </form>
    <?php
    $panelContent = ob_get_clean();
    $dynamicTabs[] = array(
        'key' => 'room:' . $key,
        'title' => $tabLabel,
        'panel_id' => $panelId,
        'close_form_id' => $closeFormId,
        'content' => $panelContent
    );
}

$staticTabs = array(
    array(
        'id' => 'list',
        'title' => 'General',
        'content' => $listContent
    )
);

pms_render_subtabs($moduleKey, $subtabState, $staticTabs, $dynamicTabs);

if ($roomCategoryCatalog) {
    $roomCategoryCatalogForJs = array();
    foreach ($roomCategoryCatalog as $propertyCode => $items) {
        $roomCategoryCatalogForJs[$propertyCode] = array();
        foreach ($items as $item) {
            $code = isset($item['code']) ? (string)$item['code'] : '';
            if ($code === '') {
                continue;
            }
            $name = isset($item['name']) ? (string)$item['name'] : '';
            $label = $code;
            if ($name !== '') {
                $label .= ' - ' . $name;
            }
            if (isset($item['is_active']) && (int)$item['is_active'] !== 1) {
                $label .= ' (inactiva)';
            }
            $roomCategoryCatalogForJs[$propertyCode][] = array(
                'code' => $code,
                'label' => $label
            );
        }
    }
    echo '<script>';
    echo '(function(){';
    echo 'var roomCategoryCatalog=' . json_encode($roomCategoryCatalogForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
    echo 'function renderRoomCategories(propertyCode, selectEl, preferredCode){';
    echo 'if(!selectEl){return;}';
    echo 'var options=roomCategoryCatalog[propertyCode]||[];';
    echo 'var selectedCode=(preferredCode||\'\').toUpperCase();';
    echo 'while(selectEl.options.length){selectEl.remove(0);}';
    echo 'selectEl.add(new Option(\'Sin categoria\', \'\'));';
    echo 'var hasSelected=false;';
    echo 'for(var i=0;i<options.length;i++){';
    echo 'var opt=options[i]||{};';
    echo 'var code=(opt.code||\'\').toString();';
    echo 'if(!code){continue;}';
    echo 'var text=(opt.label||code).toString();';
    echo 'var option=new Option(text, code);';
    echo 'if(selectedCode!==\'\'&&code.toUpperCase()===selectedCode){option.selected=true;hasSelected=true;}';
    echo 'selectEl.add(option);';
    echo '}';
    echo 'if(!hasSelected){selectEl.value=\'\';}';
    echo '}';
    echo 'var forms=document.querySelectorAll(\'form.js-room-form\');';
    echo 'for(var f=0;f<forms.length;f++){';
    echo 'var form=forms[f];';
    echo 'var propertySelect=form.querySelector(\'select.js-room-property\');';
    echo 'var categorySelect=form.querySelector(\'select.js-room-category\');';
    echo 'if(!propertySelect||!categorySelect){continue;}';
    echo 'var initialCategory=(categorySelect.getAttribute(\'data-selected\')||categorySelect.value||\'\').toUpperCase();';
    echo 'renderRoomCategories(propertySelect.value, categorySelect, initialCategory);';
    echo 'propertySelect.addEventListener(\'change\', (function(propSel, catSel){';
    echo 'return function(){renderRoomCategories(propSel.value, catSel, \'\');};';
    echo '})(propertySelect, categorySelect));';
    echo '}';
    echo '})();';
    echo '</script>';
}
if ($ratePlans) {
    echo '<datalist id="roomRateplans">';
    foreach ($ratePlans as $plan) {
        $code = isset($plan['rateplan_code']) ? (string)$plan['rateplan_code'] : '';
        $name = isset($plan['rateplan_name']) ? (string)$plan['rateplan_name'] : '';
        if ($code === '') { continue; }
        echo '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</datalist>';
}
