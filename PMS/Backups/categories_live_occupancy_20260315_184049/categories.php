<?php
$moduleKey = 'categories';
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyId === 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('categories.view');

$amenityFields = array(
    'has_air_conditioning' => 'Aire acondicionado',
    'has_fan' => 'Ventilador',
    'has_tv' => 'Television',
    'has_private_wifi' => 'Wi-Fi privado',
    'has_minibar' => 'Minibar',
    'has_safe_box' => 'Caja fuerte',
    'has_workspace' => 'Espacio de trabajo',
    'includes_bedding_towels' => 'Ropa de cama y toallas',
    'has_iron_board' => 'Plancha/Tabla',
    'has_closet_rack' => 'Closet/Perchero',
    'has_private_balcony_terrace' => 'Balcon/Terraza privada',
    'has_view' => 'Vista',
    'has_private_entrance' => 'Entrada independiente',
    'has_hot_water' => 'Agua caliente',
    'includes_toiletries' => 'Articulos de aseo',
    'has_hairdryer' => 'Secadora de cabello',
    'includes_clean_towels' => 'Toallas limpias',
    'has_coffee_tea_kettle' => 'Cafetera/Tetera',
    'has_basic_utensils' => 'Utensilios basicos',
    'has_basic_food_items' => 'Basicos alimentos',
    'is_private' => 'Privada',
    'is_shared' => 'Compartida',
    'has_shared_bathroom' => 'Bano compartido',
    'has_private_bathroom' => 'Bano privado',
);

$propertyAmenityFields = array(
    'has_wifi' => 'Wi-Fi',
    'has_parking' => 'Estacionamiento',
    'has_shared_kitchen' => 'Cocina compartida',
    'has_dining_area' => 'Area de comedor',
    'has_cleaning_service' => 'Limpieza',
    'has_shared_laundry' => 'Lavanderia',
    'has_pool' => 'Piscina',
    'has_jacuzzi' => 'Jacuzzi',
    'has_terrace_rooftop' => 'Terraza',
    'has_bbq_area' => 'BBQ',
    'has_beach_access' => 'Acceso playa',
    'is_pet_friendly' => 'Pet friendly'
);

$amenityIcons = array(
    'has_air_conditioning' => '&#10052;',
    'has_fan' => '&#126980;',
    'has_tv' => '&#128250;',
    'has_private_wifi' => '&#128246;',
    'has_minibar' => '&#127864;',
    'has_safe_box' => '&#128274;',
    'has_workspace' => '&#128187;',
    'includes_bedding_towels' => '&#128719;',
    'has_iron_board' => '&#128087;',
    'has_closet_rack' => '&#128085;',
    'has_private_balcony_terrace' => '&#127748;',
    'has_view' => '&#128065;',
    'has_private_entrance' => '&#128682;',
    'has_hot_water' => '&#9832;',
    'includes_toiletries' => '&#129533;',
    'has_hairdryer' => '&#128135;',
    'includes_clean_towels' => '&#129530;',
    'has_coffee_tea_kettle' => '&#9749;',
    'has_basic_utensils' => '&#127860;',
    'has_basic_food_items' => '&#127859;',
    'is_private' => '&#128273;',
    'is_shared' => '&#128101;',
    'has_shared_bathroom' => '&#128701;',
    'has_private_bathroom' => '&#128705;',
);

$bedTypeOptions = array(
    'individual' => 'Individual',
    'matrimonial' => 'Matrimonial',
    'queen' => 'Queen',
    'king' => 'King'
);

$properties = pms_fetch_properties($companyId);
$propertiesByCode = array();
foreach ($properties as $property) {
    $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
    if ($code !== '') {
        $propertiesByCode[$code] = $property;
    }
}
$selectedProperty = isset($_POST['categories_filter_property']) ? strtoupper(trim((string)$_POST['categories_filter_property'])) : '';

$message = null;
$error = null;
$showInactive = isset($_POST['categories_filter_show_inactive']) ? (int)$_POST['categories_filter_show_inactive'] : 0;
$showOrderField = isset($_POST['categories_filter_show_order']) ? (int)$_POST['categories_filter_show_order'] : 0;
$isGetRequest = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
if ($isGetRequest) {
    $filterPropertyFromGet = isset($_GET['categories_filter_property']) ? strtoupper(trim((string)$_GET['categories_filter_property'])) : '';
    $openCategory = isset($_GET['open_category']) ? strtoupper(trim((string)$_GET['open_category'])) : '';
    if ($filterPropertyFromGet !== '' && isset($propertiesByCode[$filterPropertyFromGet])) {
        $_POST['categories_filter_property'] = $filterPropertyFromGet;
        $selectedProperty = $filterPropertyFromGet;
    }
    if ($openCategory !== '') {
        $_POST[$moduleKey . '_subtab_action'] = 'open';
        $_POST[$moduleKey . '_subtab_target'] = 'category:' . $openCategory;
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:category:' . $openCategory;
    }
}
if ($selectedProperty !== '' && !isset($propertiesByCode[$selectedProperty])) {
    $selectedProperty = '';
}

$subtabState = pms_subtabs_init($moduleKey, 'static:list');
$openCategoryCodes = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'category:') === 0) {
        $code = substr($openKey, strlen('category:'));
        if ($code !== '' && !in_array($code, $openCategoryCodes, true)) {
            $openCategoryCodes[] = $code;
        }
    }
}
if ($isGetRequest && !isset($_POST[$moduleKey . '_subtab_action'])) {
    $subtabState['open'] = array();
    $subtabState['dirty'] = array();
    $subtabState['active'] = 'static:list';
    $_SESSION['pms_subtabs'][$moduleKey] = $subtabState;
    $openCategoryCodes = array();
}

$action = isset($_POST['categories_action']) ? (string)$_POST['categories_action'] : '';
$cloneCategoryCode = isset($_POST['categories_clone_code']) ? strtoupper(trim((string)$_POST['categories_clone_code'])) : '';
$cloneCategoryProperty = isset($_POST['categories_clone_property']) ? strtoupper(trim((string)$_POST['categories_clone_property'])) : '';
if (in_array($action, array('new_category', 'duplicate_category'), true)) {
    pms_require_permission('categories.create');
} elseif ($action === 'save_category') {
    $incomingCategoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    pms_require_permission($incomingCategoryId > 0 ? 'categories.edit' : 'categories.create');
} elseif (in_array($action, array('add_bed_config', 'remove_bed_config', 'update_order'), true)) {
    pms_require_permission('categories.edit');
}
if ($action === 'new_category') {
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'category:__new__';
} elseif ($action === 'duplicate_category') {
    if ($cloneCategoryProperty !== '') {
        $selectedProperty = $cloneCategoryProperty;
    }
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'category:__new__';
} elseif ($action === 'save_category') {
    $propertyCode = isset($_POST['category_property_code']) ? strtoupper(trim((string)$_POST['category_property_code'])) : '';
    $categoryCode = isset($_POST['category_code']) ? strtoupper(trim((string)$_POST['category_code'])) : '';
    $originalCode = isset($_POST['category_code_original']) ? strtoupper(trim((string)$_POST['category_code_original'])) : '';
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $name = isset($_POST['category_name']) ? trim((string)$_POST['category_name']) : '';
    $description = isset($_POST['category_description']) ? trim((string)$_POST['category_description']) : '';
    $baseOcc = isset($_POST['category_base_occupancy']) && $_POST['category_base_occupancy'] !== ''
        ? (int)$_POST['category_base_occupancy']
        : null;
    $maxOcc = isset($_POST['category_max_occupancy']) && $_POST['category_max_occupancy'] !== ''
        ? (int)$_POST['category_max_occupancy']
        : null;
    $orderIndex = isset($_POST['category_order_index']) && $_POST['category_order_index'] !== ''
        ? (int)$_POST['category_order_index']
        : null;
    $basePriceRaw = isset($_POST['category_base_price']) ? trim((string)$_POST['category_base_price']) : '';
    $basePrice = $basePriceRaw !== '' ? (int)round(((float)str_replace(',', '.', $basePriceRaw)) * 100) : null;
    $minPriceRaw = isset($_POST['category_min_price']) ? trim((string)$_POST['category_min_price']) : '';
    $minPrice = $minPriceRaw !== '' ? (int)round(((float)str_replace(',', '.', $minPriceRaw)) * 100) : null;
    $imageUrl = '';
    $colorHex = '';
    $rateplanCode = '';
    $isActive = isset($_POST['category_is_active']) ? 1 : 0;
    $amenitiesActive = 1;
    $amenityValues = array();
    foreach ($amenityFields as $key => $_label) {
        $amenityValues[$key] = isset($_POST[$key]) ? 1 : 0;
    }
    $postedCalendarDisplayAmenities = isset($_POST['category_calendar_display_amenities']) && is_array($_POST['category_calendar_display_amenities'])
        ? $_POST['category_calendar_display_amenities']
        : array();
    $postedCalendarDisplayMap = array();
    foreach ($postedCalendarDisplayAmenities as $postedAmenityKey) {
        $amenityKey = trim((string)$postedAmenityKey);
        if ($amenityKey !== '') {
            $postedCalendarDisplayMap[$amenityKey] = true;
        }
    }
    $calendarDisplayAmenityKeys = array();
    foreach ($amenityFields as $amenityKey => $_label) {
        if (!isset($postedCalendarDisplayMap[$amenityKey])) {
            continue;
        }
        if ((int)$amenityValues[$amenityKey] !== 1) {
            continue;
        }
        $calendarDisplayAmenityKeys[] = $amenityKey;
    }
    $calendarDisplayAmenitiesCsv = implode(',', $calendarDisplayAmenityKeys);

    if ($propertyCode === '' || $categoryCode === '' || $name === '') {
        $error = 'Propiedad, codigo y nombre son obligatorios.';
    } else {
        try {
            if ($categoryCode !== '') {
                $pdo = pms_get_connection();
                $duplicateSql = 'SELECT rc.id_category
                    FROM roomcategory rc
                    JOIN property p ON p.id_property = rc.id_property
                    WHERE p.code = ? AND rc.code = ? AND rc.deleted_at IS NULL AND rc.is_active = 1';
                $duplicateParams = array($propertyCode, $categoryCode);
                if ($categoryId > 0) {
                    $duplicateSql .= ' AND rc.id_category <> ?';
                    $duplicateParams[] = $categoryId;
                }
                $duplicateSql .= ' LIMIT 1';
                $stmt = $pdo->prepare($duplicateSql);
                $stmt->execute($duplicateParams);
                $exists = $stmt->fetchColumn();
                if ($exists !== false) {
                    throw new Exception('Ya existe una categoria activa con ese codigo.');
                }
            }

            pms_call_procedure('sp_roomcategory_upsert', array(
                $propertyCode,
                $categoryCode,
                $name,
                $description === '' ? null : $description,
                $baseOcc,
                $maxOcc,
                $orderIndex,
                $basePrice,
                $minPrice,
                $imageUrl === '' ? null : $imageUrl,
                $rateplanCode === '' ? null : $rateplanCode,
                $colorHex === '' ? null : $colorHex,
                $amenityValues['has_air_conditioning'],
                $amenityValues['has_fan'],
                $amenityValues['has_tv'],
                $amenityValues['has_private_wifi'],
                $amenityValues['has_minibar'],
                $amenityValues['has_safe_box'],
                $amenityValues['has_workspace'],
                $amenityValues['includes_bedding_towels'],
                $amenityValues['has_iron_board'],
                $amenityValues['has_closet_rack'],
                $amenityValues['has_private_balcony_terrace'],
                $amenityValues['has_view'],
                $amenityValues['has_private_entrance'],
                $amenityValues['has_hot_water'],
                $amenityValues['includes_toiletries'],
                $amenityValues['has_hairdryer'],
                $amenityValues['includes_clean_towels'],
                $amenityValues['has_coffee_tea_kettle'],
                $amenityValues['has_basic_utensils'],
                $amenityValues['has_basic_food_items'],
                $amenityValues['is_private'],
                $amenityValues['is_shared'],
                $amenityValues['has_shared_bathroom'],
                $amenityValues['has_private_bathroom'],
                $amenitiesActive,
                $calendarDisplayAmenitiesCsv,
                $isActive,
                $actorUserId,
                $categoryId > 0 ? $categoryId : null
            ));
            $message = 'Categoria guardada.';
            // Cerrar la pestaña y volver a la lista
            $_POST[$moduleKey . '_subtab_action'] = 'activate';
            $_POST[$moduleKey . '_subtab_target'] = 'static:list';
            $_POST[$moduleKey . '_subtab_target_close'] = 'category:' . ($originalCode !== '' ? $originalCode : $categoryCode);
            $selectedProperty = $propertyCode;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'add_bed_config') {
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $bedType = isset($_POST['bed_type']) ? trim((string)$_POST['bed_type']) : '';
    $bedCount = isset($_POST['bed_count']) ? (int)$_POST['bed_count'] : 0;

    if ($categoryId <= 0) {
        $error = 'Selecciona una categoria valida para configurar camas.';
    } elseif (!isset($bedTypeOptions[$bedType])) {
        $error = 'Selecciona un tipo de cama valido.';
    } elseif ($bedCount < 1) {
        $error = 'La cantidad de camas debe ser mayor a cero.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT rc.id_category
                 FROM roomcategory rc
                 JOIN property p ON p.id_property = rc.id_property
                 WHERE rc.id_category = ? AND p.id_company = ? AND rc.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($categoryId, $companyId));
            if ($stmt->fetchColumn() === false) {
                throw new Exception('Categoria no encontrada para esta empresa.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO category_bed_config (
                    id_category,
                    bed_type,
                    bed_count,
                    is_active,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, 1, ?, NOW(), NOW())'
            );
            $stmt->execute(array($categoryId, $bedType, $bedCount, $actorUserId));
            $message = 'Configuracion de camas agregada.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'remove_bed_config') {
    $bedConfigId = isset($_POST['bed_config_id']) ? (int)$_POST['bed_config_id'] : 0;
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

    if ($bedConfigId <= 0 || $categoryId <= 0) {
        $error = 'Selecciona una configuracion valida para eliminar.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE category_bed_config bc
                 JOIN roomcategory rc ON rc.id_category = bc.id_category
                 JOIN property p ON p.id_property = rc.id_property
                 SET bc.deleted_at = NOW(),
                     bc.is_active = 0,
                     bc.updated_at = NOW()
                 WHERE bc.id_bed_config = ?
                   AND bc.id_category = ?
                   AND p.id_company = ?
                   AND bc.deleted_at IS NULL'
            );
            $stmt->execute(array($bedConfigId, $categoryId, $companyId));
            if ($stmt->rowCount() === 0) {
                throw new Exception('No fue posible eliminar la configuracion de camas.');
            }
            $message = 'Configuracion de camas eliminada.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'update_order') {
    $categoryId = isset($_POST['category_order_id']) ? (int)$_POST['category_order_id'] : 0;
    $orderValue = isset($_POST['category_order_index']) && $_POST['category_order_index'] !== ''
        ? (int)$_POST['category_order_index']
        : 0;
    if ($categoryId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE roomcategory rc
                 JOIN property p ON p.id_property = rc.id_property
                 SET rc.order_index = ?, rc.updated_at = NOW()
                 WHERE rc.id_category = ? AND p.id_company = ? AND rc.deleted_at IS NULL'
            );
            $stmt->execute(array($orderValue, $categoryId, $companyId));
            $message = 'Orden actualizado.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Refresh subtab state after actions
$subtabState = pms_subtabs_init($moduleKey, 'static:list');
// If we injected a target to close, remove it from open list
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
$openCategoryCodes = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'category:') === 0) {
        $code = substr($openKey, strlen('category:'));
        if ($code !== '' && !in_array($code, $openCategoryCodes, true)) {
            $openCategoryCodes[] = $code;
        }
    }
}

// Fetch property data for selected property
$ratePlans = array();
$categories = array();
$roomsList = array();
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
        $roomsList = isset($sets[4]) ? $sets[4] : array();
        $bedConfigs = isset($sets[5]) ? $sets[5] : array();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
} else {
    try {
        $pdo = pms_get_connection();
        $hasCategoryCalendarDisplay = false;
        try {
            $stmtTable = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?'
            );
            $stmtTable->execute(array('category_calendar_amenity_display'));
            $hasCategoryCalendarDisplay = ((int)$stmtTable->fetchColumn() > 0);
        } catch (Exception $e) {
            $hasCategoryCalendarDisplay = false;
        }
        $calendarSelectSql = $hasCategoryCalendarDisplay
            ? "COALESCE(cad.calendar_amenities_csv, '') AS calendar_amenities_csv,"
            : "'' AS calendar_amenities_csv,";
        $calendarJoinSql = $hasCategoryCalendarDisplay
            ? ' LEFT JOIN (
                    SELECT
                        t.id_category,
                        GROUP_CONCAT(t.amenity_key ORDER BY t.display_order, t.id_category_calendar_amenity_display SEPARATOR \',\') AS calendar_amenities_csv
                    FROM category_calendar_amenity_display t
                    WHERE t.is_active = 1
                    GROUP BY t.id_category
               ) cad ON cad.id_category = rc.id_category '
            : '';
        $stmt = $pdo->prepare(
            'SELECT rc.id_category,
                    rc.code AS category_code,
                    rc.name AS category_name,
                    rc.description,
                    rc.base_occupancy,
                    rc.max_occupancy,
                    rc.default_base_price_cents,
                    rc.min_price_cents,
                    rc.image_url,
                    rc.color_hex,
                    rc.is_active,
                    rc.order_index,
                    rp.code AS rateplan_code,
                    rp.name AS rateplan_name,
                    ' . $calendarSelectSql . '
                    p.code AS property_code,
                    p.name AS property_name
             FROM roomcategory rc
             JOIN property p ON p.id_property = rc.id_property
             LEFT JOIN rateplan rp ON rp.id_rateplan = rc.id_rateplan
             ' . $calendarJoinSql . '
             WHERE p.id_company = ? AND rc.deleted_at IS NULL
             ORDER BY p.order_index, p.name, rc.order_index, rc.name'
        );
        $stmt->execute(array($companyId));
        $categories = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT rm.id_room,
                    rm.code AS room_code,
                    rm.name AS room_name,
                    rm.status,
                    rm.is_active,
                    rc.code AS category_code,
                    rc.name AS category_name,
                    p.code AS property_code,
                    p.name AS property_name
             FROM room rm
             JOIN property p ON p.id_property = rm.id_property
             LEFT JOIN roomcategory rc ON rc.id_category = rm.id_category
             WHERE p.id_company = ? AND rm.deleted_at IS NULL
             ORDER BY p.order_index, p.name, rc.order_index, rm.order_index, rm.code'
        );
        $stmt->execute(array($companyId));
        $roomsList = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
}

if ($selectedProperty !== '' && empty($roomsList)) {
    try {
        $pdo = isset($pdo) ? $pdo : pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT rm.id_room,
                    rm.code AS room_code,
                    rm.name AS room_name,
                    rm.status,
                    rm.is_active,
                    rc.code AS category_code,
                    rc.name AS category_name,
                    p.code AS property_code,
                    p.name AS property_name
             FROM room rm
             JOIN property p ON p.id_property = rm.id_property
             LEFT JOIN roomcategory rc ON rc.id_category = rm.id_category
             WHERE p.code = ? AND p.id_company = ? AND rm.deleted_at IS NULL
             ORDER BY rc.order_index, rm.order_index, rm.code'
        );
        $stmt->execute(array($selectedProperty, $companyId));
        $roomsList = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
}

if ($categories) {
    $uniqueCategories = array();
    $seenCategoryKeys = array();
    foreach ($categories as $cat) {
        $id = isset($cat['id_category']) ? (int)$cat['id_category'] : 0;
        $code = isset($cat['category_code']) ? (string)$cat['category_code'] : '';
        $key = $id > 0 ? 'id:' . $id : 'code:' . $code;
        if (isset($seenCategoryKeys[$key])) {
            continue;
        }
        $seenCategoryKeys[$key] = true;
        $uniqueCategories[] = $cat;
    }
    $categories = $uniqueCategories;
}

$propertyMetaByCode = array();
foreach ($properties as $propertyRow) {
    $propertyCodeRow = isset($propertyRow['code']) ? strtoupper(trim((string)$propertyRow['code'])) : '';
    if ($propertyCodeRow === '') {
        continue;
    }
    $propertyMetaByCode[$propertyCodeRow] = array(
        'code' => $propertyCodeRow,
        'name' => isset($propertyRow['name']) ? (string)$propertyRow['name'] : $propertyCodeRow,
        'check_out_time' => '',
        'address_line1' => '',
        'address_line2' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => '',
        'amenities' => array()
    );
}
if ($propertyMetaByCode) {
    try {
        $pdo = isset($pdo) ? $pdo : pms_get_connection();
        $propertyCodes = array_keys($propertyMetaByCode);
        $propertyPlaceholders = implode(',', array_fill(0, count($propertyCodes), '?'));
        $propertyAmenitySelect = array();
        foreach ($propertyAmenityFields as $amenityKeyTmp => $_amenityLabelTmp) {
            $propertyAmenitySelect[] = 'COALESCE(pa.' . $amenityKeyTmp . ', 0) AS ' . $amenityKeyTmp;
        }
        $stmt = $pdo->prepare(
            'SELECT
                p.code,
                p.name,
                p.check_out_time,
                p.address_line1,
                p.address_line2,
                p.city,
                p.state,
                p.postal_code,
                p.country,
                ' . implode(",\n                ", $propertyAmenitySelect) . '
             FROM property p
             LEFT JOIN property_amenities pa
               ON pa.id_property = p.id_property
              AND pa.deleted_at IS NULL
              AND pa.is_active = 1
             WHERE p.id_company = ?
               AND p.deleted_at IS NULL
               AND p.code IN (' . $propertyPlaceholders . ')'
        );
        $stmt->execute(array_merge(array($companyId), $propertyCodes));
        $propertyRows = $stmt->fetchAll();
        foreach ($propertyRows as $propertyRow) {
            $propertyCodeRow = isset($propertyRow['code']) ? strtoupper(trim((string)$propertyRow['code'])) : '';
            if ($propertyCodeRow === '' || !isset($propertyMetaByCode[$propertyCodeRow])) {
                continue;
            }
            $amenitiesTmp = array();
            foreach ($propertyAmenityFields as $amenityKeyTmp => $amenityLabelTmp) {
                if (!empty($propertyRow[$amenityKeyTmp])) {
                    $amenitiesTmp[] = $amenityLabelTmp;
                }
            }
            $propertyMetaByCode[$propertyCodeRow] = array(
                'code' => $propertyCodeRow,
                'name' => isset($propertyRow['name']) ? (string)$propertyRow['name'] : $propertyMetaByCode[$propertyCodeRow]['name'],
                'check_out_time' => isset($propertyRow['check_out_time']) ? (string)$propertyRow['check_out_time'] : '',
                'address_line1' => isset($propertyRow['address_line1']) ? (string)$propertyRow['address_line1'] : '',
                'address_line2' => isset($propertyRow['address_line2']) ? (string)$propertyRow['address_line2'] : '',
                'city' => isset($propertyRow['city']) ? (string)$propertyRow['city'] : '',
                'state' => isset($propertyRow['state']) ? (string)$propertyRow['state'] : '',
                'postal_code' => isset($propertyRow['postal_code']) ? (string)$propertyRow['postal_code'] : '',
                'country' => isset($propertyRow['country']) ? (string)$propertyRow['country'] : '',
                'amenities' => $amenitiesTmp
            );
        }
    } catch (Exception $e) {
    }
}

$bedSummaryByCategory = array();
if ($categories) {
    $categoryIds = array();
    foreach ($categories as $categoryRow) {
        $categoryId = isset($categoryRow['id_category']) ? (int)$categoryRow['id_category'] : 0;
        if ($categoryId > 0) {
            $categoryIds[$categoryId] = true;
        }
    }
    if ($categoryIds) {
        try {
            $pdo = isset($pdo) ? $pdo : pms_get_connection();
            $categoryIdList = array_keys($categoryIds);
            $placeholders = implode(',', array_fill(0, count($categoryIdList), '?'));
            $stmt = $pdo->prepare(
                'SELECT
                    bc.id_category,
                    COUNT(*) AS bed_config_count,
                    SUM(COALESCE(bc.bed_count, 0)) AS bed_total_count
                 FROM category_bed_config bc
                 WHERE bc.deleted_at IS NULL
                   AND bc.is_active = 1
                   AND bc.id_category IN (' . $placeholders . ')
                 GROUP BY bc.id_category'
            );
            $stmt->execute($categoryIdList);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $categoryId = isset($row['id_category']) ? (int)$row['id_category'] : 0;
                if ($categoryId <= 0) {
                    continue;
                }
                $bedSummaryByCategory[$categoryId] = array(
                    'config_count' => isset($row['bed_config_count']) ? (int)$row['bed_config_count'] : 0,
                    'bed_total_count' => isset($row['bed_total_count']) ? (int)$row['bed_total_count'] : 0
                );
            }
        } catch (Exception $e) {
        }
    }
}

$bedConfigsByCategory = array();
if (!empty($bedConfigs) && is_array($bedConfigs)) {
    foreach ($bedConfigs as $config) {
        if (isset($config['is_active']) && (int)$config['is_active'] !== 1) {
            continue;
        }
        $categoryId = isset($config['id_category']) ? (int)$config['id_category'] : 0;
        if ($categoryId <= 0) {
            continue;
        }
        if (!isset($bedConfigsByCategory[$categoryId])) {
            $bedConfigsByCategory[$categoryId] = array();
        }
        $bedConfigsByCategory[$categoryId][] = $config;
    }
}

$validCategoryCodes = array();
foreach ($categories as $cat) {
    $code = isset($cat['category_code']) ? (string)$cat['category_code'] : '';
    if ($code !== '') {
        $validCategoryCodes[$code] = true;
    }
}
$cleanOpenCategoryCodes = array();
foreach ($openCategoryCodes as $code) {
    if ($code === '__new__' || isset($validCategoryCodes[$code])) {
        $cleanOpenCategoryCodes[] = $code;
    }
}
$activeKey = isset($subtabState['active']) ? (string)$subtabState['active'] : 'static:list';
if (strpos($activeKey, 'dynamic:') === 0) {
    $activeRaw = substr($activeKey, strlen('dynamic:'));
    if (strpos($activeRaw, 'category:') === 0) {
        $activeCode = substr($activeRaw, strlen('category:'));
        if ($activeCode !== '__new__' && !isset($validCategoryCodes[$activeCode])) {
            $subtabState['active'] = 'static:list';
        }
    }
}
if ($cleanOpenCategoryCodes !== $openCategoryCodes) {
    $openCategoryCodes = $cleanOpenCategoryCodes;
    $subtabState['open'] = array();
    foreach ($openCategoryCodes as $code) {
        $subtabState['open'][] = 'category:' . $code;
    }
}
$_SESSION['pms_subtabs'][$moduleKey] = $subtabState;

function categories_parse_calendar_amenities_csv($value, array $allowedKeys)
{
    $raw = trim((string)$value);
    if ($raw === '' || !$allowedKeys) {
        return array();
    }
    $allowedMap = array();
    foreach ($allowedKeys as $key) {
        $allowedMap[(string)$key] = true;
    }
    $aliases = array(
        'has_private_balcony' => 'has_private_balcony_terrace',
        'has_balcony' => 'has_private_balcony_terrace',
        'has_balcony_terrace' => 'has_private_balcony_terrace',
        'has_private_terrace' => 'has_private_balcony_terrace',
        'private_balcony_terrace' => 'has_private_balcony_terrace',
        'has_private_balcony_terace' => 'has_private_balcony_terrace',
        'has_private_balcon_terrace' => 'has_private_balcony_terrace',
        'has_private_balcon_terraza' => 'has_private_balcony_terrace'
    );
    $out = array();
    $seen = array();
    $parts = explode(',', $raw);
    foreach ($parts as $part) {
        $key = trim((string)$part);
        if (isset($aliases[$key])) {
            $key = $aliases[$key];
        }
        if ($key === '' || !isset($allowedMap[$key]) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $key;
    }
    return $out;
}

function categories_find_detail($categories, $code)
{
    foreach ($categories as $cat) {
        if (isset($cat['category_code']) && (string)$cat['category_code'] === (string)$code) {
            if (!isset($cat['order_index'])) {
                $cat['order_index'] = 0;
            }
            return $cat;
        }
    }
    return null;
}

function categories_compose_property_address(array $meta)
{
    $parts = array();
    foreach (array('address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country') as $field) {
        if (!isset($meta[$field])) {
            continue;
        }
        $value = trim((string)$meta[$field]);
        if ($value !== '') {
            $parts[] = $value;
        }
    }
    if (!$parts) {
        return 'Sin direccion';
    }
    return implode(', ', $parts);
}

// Static tab content (list)
ob_start();
?>
<div class="tab-actions">
  <form method="post" class="form-inline">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <label>
      Propiedad
      <select name="categories_filter_property" onchange="this.form.submit()">
        <option value="" <?php echo $selectedProperty === '' ? 'selected' : ''; ?>>Todas</option>
        <?php foreach ($properties as $property):
          $code = isset($property['code']) ? (string)$property['code'] : '';
          $name = isset($property['name']) ? (string)$property['name'] : '';
          if ($code === '') {
              continue;
          }
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $selectedProperty ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="categories_filter_show_inactive" value="1" <?php echo $showInactive ? 'checked' : ''; ?> onchange="this.form.submit()">
      Mostrar inactivas
    </label>
    <label class="checkbox">
      <input type="checkbox" name="categories_filter_show_order" value="1" <?php echo $showOrderField ? 'checked' : ''; ?> onchange="this.form.submit()">
      Mostrar orden
    </label>
  </form>
  <form method="post">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <input type="hidden" name="categories_action" value="new_category">
    <input type="hidden" name="categories_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="categories_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
    <input type="hidden" name="categories_filter_show_order" value="<?php echo (int)$showOrderField; ?>">
    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:category:__new__">
    <button type="submit">Nueva categoria</button>
  </form>
</div>

<?php if ($error): ?>
  <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($message): ?>
  <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($categories): ?>
  <?php
    $isAllPropertiesView = ($selectedProperty === '');
    // Precompute occupancy per category using rooms list (status = occupied)
    $categoryOccupancy = array();
    $categoryRooms = array();
    foreach ($roomsList as $room) {
        if (isset($room['is_active']) && (int)$room['is_active'] !== 1) {
            continue;
        }
        $cat = isset($room['category_code']) ? (string)$room['category_code'] : '';
        $prop = isset($room['property_code']) ? strtoupper((string)$room['property_code']) : $selectedProperty;
        if ($cat === '' || $prop === '') {
            continue;
        }
        $catKey = $prop . '::' . $cat;
        if (!isset($categoryOccupancy[$catKey])) {
            $categoryOccupancy[$catKey] = array('total' => 0, 'occupied' => 0);
        }
        if (!isset($categoryRooms[$catKey])) {
            $categoryRooms[$catKey] = array();
        }
        $categoryOccupancy[$catKey]['total']++;
        $status = isset($room['status']) ? strtolower(trim((string)$room['status'])) : '';
        $isOccupied = ($status === 'occupied');
        if ($isOccupied) {
            $categoryOccupancy[$catKey]['occupied']++;
        }
        $categoryRooms[$catKey][] = array(
            'id_room' => isset($room['id_room']) ? (int)$room['id_room'] : 0,
            'room_code' => isset($room['room_code']) ? (string)$room['room_code'] : '',
            'room_name' => isset($room['room_name']) ? (string)$room['room_name'] : '',
            'status' => $status,
            'is_occupied' => $isOccupied ? 1 : 0
        );
    }

    $categoriesGrouped = array();
    foreach ($categories as $cat) {
        if (!$showInactive && isset($cat['is_active']) && (int)$cat['is_active'] !== 1) {
            continue;
        }
        $propCode = isset($cat['property_code']) ? strtoupper((string)$cat['property_code']) : $selectedProperty;
        if ($propCode === '') {
            continue;
        }
        if (!isset($categoriesGrouped[$propCode])) {
            $categoriesGrouped[$propCode] = array(
                'property_name' => isset($propertiesByCode[$propCode]['name']) ? (string)$propertiesByCode[$propCode]['name'] : $propCode,
                'property_meta' => isset($propertyMetaByCode[$propCode]) ? $propertyMetaByCode[$propCode] : array(),
                'rows' => array()
            );
        }
        $categoriesGrouped[$propCode]['rows'][] = $cat;
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
  <div class="category-properties-grid<?php echo $isAllPropertiesView ? ' is-all-properties' : ''; ?>">
  <?php foreach ($propertyOrder as $propCode): ?>
    <?php if (!isset($categoriesGrouped[$propCode])) { continue; } ?>
    <?php
      $propertyName = isset($categoriesGrouped[$propCode]['property_name']) ? (string)$categoriesGrouped[$propCode]['property_name'] : $propCode;
      $propertyMeta = isset($categoriesGrouped[$propCode]['property_meta']) ? $categoriesGrouped[$propCode]['property_meta'] : array();
      $propertyAddress = categories_compose_property_address($propertyMeta);
      $propertyCheckOutRaw = isset($propertyMeta['check_out_time']) ? trim((string)$propertyMeta['check_out_time']) : '';
      $propertyCheckOutLabel = '--';
      if ($propertyCheckOutRaw !== '') {
          $checkOutTs = strtotime($propertyCheckOutRaw);
          if ($checkOutTs !== false) {
              $propertyCheckOutLabel = date('H:i', $checkOutTs);
          } else {
              $propertyCheckOutLabel = $propertyCheckOutRaw;
          }
      }
      $propertyAmenities = isset($propertyMeta['amenities']) && is_array($propertyMeta['amenities'])
          ? $propertyMeta['amenities']
          : array();
      $propertyCategoryCount = count($categoriesGrouped[$propCode]['rows']);
      $propertyOccupiedTotal = 0;
      $propertyRoomTotal = 0;
      foreach ($categoriesGrouped[$propCode]['rows'] as $catTmp) {
          $catTmpCode = isset($catTmp['category_code']) ? (string)$catTmp['category_code'] : '';
          $catTmpKey = $propCode . '::' . $catTmpCode;
          $occTmp = isset($categoryOccupancy[$catTmpKey]) ? $categoryOccupancy[$catTmpKey] : array('occupied' => 0, 'total' => 0);
          $propertyOccupiedTotal += (int)$occTmp['occupied'];
          $propertyRoomTotal += (int)$occTmp['total'];
      }
    ?>
    <section class="category-property-section<?php echo $isAllPropertiesView ? ' is-compact' : ''; ?>">
      <div class="category-property-head">
        <div class="category-property-head-title">
          <form method="post" action="index.php?view=properties" class="property-title-link-form">
            <input type="hidden" name="properties_action" value="">
            <input type="hidden" name="properties_subtab_action" value="open">
            <input type="hidden" name="properties_subtab_target" value="property:<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="properties_current_subtab" value="dynamic:property:<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="property-title-link"><?php echo htmlspecialchars($propertyName . ' (' . $propCode . ')', ENT_QUOTES, 'UTF-8'); ?></button>
          </form>
        </div>
        <div class="category-property-head-side">
          <div class="category-property-head-info">
            <span><strong>Checkout:</strong> <?php echo htmlspecialchars($propertyCheckOutLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <span><strong>Direccion:</strong> <?php echo htmlspecialchars($propertyAddress, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="category-property-head-metrics">
            <span class="pill"><?php echo (int)$propertyCategoryCount; ?> categorias</span>
            <span class="pill"><?php echo (int)$propertyOccupiedTotal; ?>/<?php echo (int)$propertyRoomTotal; ?> ocupadas</span>
          </div>
        </div>
      </div>
      <?php if ($propertyAmenities): ?>
        <div class="category-capsules">
          <?php foreach ($propertyAmenities as $propertyAmenityLabel): ?>
            <span class="capsule"><?php echo htmlspecialchars(strtoupper((string)$propertyAmenityLabel), ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="category-card-grid">
        <?php foreach ($categoriesGrouped[$propCode]['rows'] as $cat): ?>
          <?php
            $code = isset($cat['category_code']) ? (string)$cat['category_code'] : '';
            $name = isset($cat['category_name']) ? (string)$cat['category_name'] : '';
            $isOpen = in_array($code, $openCategoryCodes, true);
            $categoryOpenFormId = 'categories-open-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $propCode . '-' . $code);
            $occKey = $propCode . '::' . $code;
            $occ = isset($categoryOccupancy[$occKey]) ? $categoryOccupancy[$occKey] : array('occupied' => 0, 'total' => 0);
            $basePrice = isset($cat['default_base_price_cents']) ? '$' . number_format((int)$cat['default_base_price_cents'] / 100, 2) : '$0.00';
            $minPrice = isset($cat['min_price_cents']) ? '$' . number_format((int)$cat['min_price_cents'] / 100, 2) : '$0.00';
            $rateplanCode = isset($cat['rateplan_code']) ? (string)$cat['rateplan_code'] : '';
            $rateplanName = isset($cat['rateplan_name']) ? (string)$cat['rateplan_name'] : '';
            $rateplanLabel = $rateplanName !== '' ? $rateplanName : $rateplanCode;
            $roomsForCategory = isset($categoryRooms[$occKey]) ? $categoryRooms[$occKey] : array();
            $categoryId = isset($cat['id_category']) ? (int)$cat['id_category'] : 0;
            $bedSummary = isset($bedSummaryByCategory[$categoryId]) ? $bedSummaryByCategory[$categoryId] : array('config_count' => 0, 'bed_total_count' => 0);
            $categoryAmenityLabels = array();
            foreach ($amenityFields as $amenityKey => $amenityLabel) {
                if (!empty($cat[$amenityKey])) {
                    $categoryAmenityLabels[] = $amenityLabel;
                }
            }
          ?>
          <article
            class="category-card<?php echo $isOpen ? ' is-open' : ''; ?>"
            data-open-form-id="<?php echo htmlspecialchars($categoryOpenFormId, ENT_QUOTES, 'UTF-8'); ?>"
            tabindex="0">
            <form method="post" id="<?php echo htmlspecialchars($categoryOpenFormId, ENT_QUOTES, 'UTF-8'); ?>" class="visually-hidden">
              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
              <input type="hidden" name="categories_action" value="">
              <input type="hidden" name="categories_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="categories_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
              <input type="hidden" name="categories_filter_show_order" value="<?php echo (int)$showOrderField; ?>">
              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="category:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:category:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
            </form>
            <header class="category-card-head">
              <h4><?php echo htmlspecialchars($name . ' | ' . $code, ENT_QUOTES, 'UTF-8'); ?></h4>
              <div class="category-card-head-right">
                <span class="category-occupancy"><?php echo (int)$occ['occupied']; ?>/<?php echo (int)$occ['total']; ?></span>
                <details class="category-card-menu category-quick-actions">
                  <summary class="category-card-menu-toggle" aria-label="Opciones de categoria">
                    <span class="category-card-menu-dots" aria-hidden="true">
                      <span></span>
                      <span></span>
                      <span></span>
                    </span>
                  </summary>
                  <div class="category-card-menu-panel">
                    <form method="post" class="category-card-menu-form">
                      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                      <input type="hidden" name="categories_action" value="duplicate_category">
                      <input type="hidden" name="categories_clone_code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="categories_clone_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="categories_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="categories_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
                      <input type="hidden" name="categories_filter_show_order" value="<?php echo (int)$showOrderField; ?>">
                      <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:category:__new__">
                      <button type="submit" class="button-secondary">Duplicar</button>
                    </form>
                  </div>
                </details>
              </div>
            </header>

            <div class="category-metrics">
              <div><span class="muted">Precio base</span><strong><?php echo htmlspecialchars($basePrice, ENT_QUOTES, 'UTF-8'); ?></strong></div>
              <div><span class="muted">Precio minimo</span><strong><?php echo htmlspecialchars($minPrice, ENT_QUOTES, 'UTF-8'); ?></strong></div>
              <div>
                <span class="muted">Plan de precios</span>
                <?php if ($rateplanCode !== ''): ?>
                  <form method="post" action="index.php?view=rateplans" class="inline-form">
                    <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rateplans_subtab_action" value="open">
                    <input type="hidden" name="rateplans_subtab_target" value="rateplan:<?php echo htmlspecialchars($rateplanCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rateplans_current_subtab" value="dynamic:rateplan:<?php echo htmlspecialchars($rateplanCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="button-secondary category-rateplan-link"><?php echo htmlspecialchars($rateplanLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                  </form>
                <?php else: ?>
                  <strong>Sin plan</strong>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($showOrderField): ?>
              <div class="category-order-box">
                <span class="muted">Orden</span>
                <form method="post" class="inline-form category-order-form">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <input type="hidden" name="categories_action" value="update_order">
                  <input type="hidden" name="categories_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="categories_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
                  <input type="hidden" name="categories_filter_show_order" value="<?php echo (int)$showOrderField; ?>">
                  <input type="hidden" name="category_order_id" value="<?php echo $categoryId; ?>">
                  <input type="number" name="category_order_index" min="0" value="<?php echo isset($cat['order_index']) ? (int)$cat['order_index'] : 0; ?>" onchange="this.form.submit()">
                </form>
              </div>
            <?php endif; ?>

            <?php if ($categoryAmenityLabels): ?>
              <div class="category-capsules">
                <?php foreach ($categoryAmenityLabels as $amenityLabel): ?>
                  <span class="capsule"><?php echo htmlspecialchars(strtoupper((string)$amenityLabel), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="category-rooms-header">
              <span>Habitaciones</span>
              <div class="category-rooms-header-right">
                <span class="muted"><?php echo count($roomsForCategory); ?> registradas</span>
                <form method="post" action="index.php?view=rooms" class="category-add-room-form">
                  <input type="hidden" name="rooms_action" value="new_room">
                  <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="rooms_new_property_code" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="rooms_new_category_code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="rooms_current_subtab" value="dynamic:room:__new__">
                  <button type="submit" class="category-add-room-button" title="Agregar habitacion">+</button>
                </form>
              </div>
            </div>
            <?php if ($roomsForCategory): ?>
              <div class="category-room-list">
                <?php foreach ($roomsForCategory as $room): ?>
                  <?php
                    $roomId = isset($room['id_room']) ? (int)$room['id_room'] : 0;
                    $roomCode = isset($room['room_code']) ? (string)$room['room_code'] : '';
                    if ($roomCode === '') {
                        continue;
                    }
                    $roomName = isset($room['room_name']) ? (string)$room['room_name'] : '';
                    $roomOpenKey = $roomId > 0 ? (string)$roomId : $roomCode;
                    $roomIsOccupied = !empty($room['is_occupied']);
                    $roomStatusRaw = isset($room['status']) ? trim((string)$room['status']) : '';
                    if ($roomStatusRaw === '') {
                        $roomStatusRaw = $roomIsOccupied ? 'occupied' : 'available';
                    }
                    $roomStatusText = $roomIsOccupied ? 'Ocupada' : 'Disponible';
                    if (!$roomIsOccupied && strtolower($roomStatusRaw) !== 'available') {
                        $roomStatusText = ucfirst($roomStatusRaw);
                    }
                  ?>
                  <form method="post" action="index.php?view=rooms" class="category-room-card <?php echo $roomIsOccupied ? 'is-occupied' : 'is-available'; ?>">
                    <input type="hidden" name="rooms_subtab_action" value="open">
                    <input type="hidden" name="rooms_subtab_target" value="room:<?php echo htmlspecialchars($roomOpenKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rooms_current_subtab" value="dynamic:room:<?php echo htmlspecialchars($roomOpenKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="category-room-link">
                      <span class="category-room-title"><?php echo htmlspecialchars($roomCode . ($roomName !== '' ? ' | ' . $roomName : ''), ENT_QUOTES, 'UTF-8'); ?></span>
                      <span class="category-room-status"><?php echo htmlspecialchars($roomStatusText, ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="muted">Sin habitaciones en esta categoria.</p>
            <?php endif; ?>

            <?php if (!$isAllPropertiesView): ?>
              <div class="category-extra-meta">
                <span><strong>Estado:</strong> <?php echo !empty($cat['is_active']) ? 'Activa' : 'Inactiva'; ?></span>
                <span><strong>Configs cama:</strong> <?php echo (int)$bedSummary['config_count']; ?></span>
                <span><strong>Camas totales:</strong> <?php echo (int)$bedSummary['bed_total_count']; ?></span>
                <?php if (isset($cat['description']) && trim((string)$cat['description']) !== ''): ?>
                  <span><strong>Descripcion:</strong> <?php echo htmlspecialchars((string)$cat['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="muted">No hay categorias para esta propiedad.</p>
<?php endif; ?>
<?php
$listContent = ob_get_clean();

// Dynamic tabs content
$dynamicTabs = array();
foreach ($openCategoryCodes as $code) {
    $detail = null;
    $tabLabel = $code;
      if ($code === '__new__') {
          $tabLabel = 'Nueva';
          $detail = array(
              'category_code' => '',
              'category_name' => '',
              'description' => '',
              'base_occupancy' => null,
              'max_occupancy' => null,
              'order_index' => 0,
              'default_base_price_cents' => null,
              'min_price_cents' => null,
              'image_url' => '',
              'color_hex' => '',
              'rateplan_code' => '',
              'calendar_amenities_csv' => '',
              'is_active' => 1
          );
          foreach ($amenityFields as $k => $_label) {
              $detail[$k] = 0;
          }

          if ($cloneCategoryCode !== '') {
              $source = categories_find_detail($categories, $cloneCategoryCode);
              if ($source) {
                  $detail = $source;
                  $detail['id_category'] = 0;
                  $detail['category_code'] = '';
                  $detail['category_name'] = trim((string)$source['category_name']) !== ''
                      ? ((string)$source['category_name'] . ' (copia)')
                      : '';
                  $detail['is_active'] = 1;
                  foreach ($amenityFields as $k => $_label) {
                      if (!isset($detail[$k])) {
                          $detail[$k] = 0;
                      }
                  }
                  if ($cloneCategoryProperty !== '') {
                      $selectedProperty = $cloneCategoryProperty;
                  }
              }
          }
      } else {
          $detail = categories_find_detail($categories, $code);
      }
    if (!$detail) {
        continue;
    }
    $detailPropertyCode = isset($detail['property_code']) ? strtoupper(trim((string)$detail['property_code'])) : '';
    if ($detailPropertyCode === '' && $selectedProperty !== '') {
        $detailPropertyCode = $selectedProperty;
    }
    $detailCategoryCode = isset($detail['category_code']) ? strtoupper(trim((string)$detail['category_code'])) : '';
    $canCreateRoomFromCategory = ($code !== '__new__' && $detailPropertyCode !== '' && $detailCategoryCode !== '');

    $panelId = 'category-panel-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '__new__' ? 'new' : $code);
    $closeFormId = 'categories-close-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '__new__' ? 'new' : $code);

    ob_start();
    ?>
    <div class="subtab-actions">
      <div>
        <h3><?php echo htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
      </div>
      <div class="subtab-actions category-header-actions">
        <?php if ($canCreateRoomFromCategory): ?>
          <details class="category-quick-actions">
            <summary class="category-quick-actions-toggle" title="Opciones de categoria" aria-label="Opciones de categoria">
              <span class="category-quick-actions-dots" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
              </span>
            </summary>
            <div class="category-quick-actions-menu">
              <form method="post" action="index.php?view=rooms" class="category-quick-action-form">
                <input type="hidden" name="rooms_action" value="new_room">
                <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($detailPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="rooms_new_property_code" value="<?php echo htmlspecialchars($detailPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="rooms_new_category_code" value="<?php echo htmlspecialchars($detailCategoryCode, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="rooms_current_subtab" value="dynamic:room:__new__">
                <button type="submit" class="button-secondary">Crear nueva habitacion en esta categoria</button>
              </form>
            </div>
          </details>
        <?php endif; ?>
        <form method="post" id="<?php echo htmlspecialchars($closeFormId, ENT_QUOTES, 'UTF-8'); ?>">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="category:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="categories_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="categories_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
          <input type="hidden" name="categories_filter_show_order" value="<?php echo (int)$showOrderField; ?>">
          <button type="submit" class="button-secondary">Cerrar</button>
        </form>
      </div>
    </div>

    <form method="post" class="form-grid grid-3 category-detail-form">
      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
      <input type="hidden" name="categories_action" value="save_category">
      <input type="hidden" name="bed_config_id" value="0">
      <input type="hidden" name="categories_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="categories_filter_show_inactive" value="<?php echo (int)$showInactive; ?>">
      <input type="hidden" name="categories_filter_show_order" value="<?php echo (int)$showOrderField; ?>">
      <input type="hidden" name="category_code_original" value="<?php echo htmlspecialchars((string)$detail['category_code'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="category_id" value="<?php echo isset($detail['id_category']) ? (int)$detail['id_category'] : 0; ?>">

      <label>
        Propiedad *
        <select name="category_property_code" required>
          <?php foreach ($properties as $property):
            $pCode = isset($property['code']) ? (string)$property['code'] : '';
            $pName = isset($property['name']) ? (string)$property['name'] : '';
            if ($pCode === '') { continue; }
          ?>
            <option value="<?php echo htmlspecialchars($pCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $pCode === $selectedProperty ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($pCode . ' - ' . $pName, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Codigo *
        <input type="text" name="category_code" required value="<?php echo htmlspecialchars((string)$detail['category_code'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Nombre *
        <input type="text" name="category_name" required value="<?php echo htmlspecialchars((string)$detail['category_name'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Orden
        <input type="number" name="category_order_index" min="0" value="<?php echo isset($detail['order_index']) ? (int)$detail['order_index'] : 0; ?>">
      </label>
      <label>
        Ocupacion base
        <input type="number" name="category_base_occupancy" min="0" value="<?php echo isset($detail['base_occupancy']) ? (int)$detail['base_occupancy'] : ''; ?>">
      </label>
      <label>
        Ocupacion maxima
        <input type="number" name="category_max_occupancy" min="0" value="<?php echo isset($detail['max_occupancy']) ? (int)$detail['max_occupancy'] : ''; ?>">
      </label>
      <label>
        Precio base
        <input type="number" step="0.01" name="category_base_price" min="0" value="<?php echo isset($detail['default_base_price_cents']) ? htmlspecialchars(number_format(((int)$detail['default_base_price_cents']) / 100, 2, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
      </label>
      <label>
        Precio minimo
        <input type="number" step="0.01" name="category_min_price" min="0" value="<?php echo isset($detail['min_price_cents']) ? htmlspecialchars(number_format(((int)$detail['min_price_cents']) / 100, 2, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
      </label>
      <label class="checkbox">
        <input type="checkbox" name="category_is_active" value="1" <?php echo isset($detail['is_active']) && (int)$detail['is_active'] === 1 ? 'checked' : ''; ?>>
        Activa
      </label>
      <label class="full">
        Descripcion
        <textarea name="category_description" rows="3"><?php echo htmlspecialchars((string)$detail['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>

      <div class="full category-amenities-layout">
        <fieldset class="amenities-grid">
          <legend>Amenidades categoria</legend>
          <?php foreach ($amenityFields as $amenityKey => $amenityLabel): ?>
            <label class="checkbox amenity-item">
              <input type="checkbox" name="<?php echo htmlspecialchars($amenityKey, ENT_QUOTES, 'UTF-8'); ?>" value="1" <?php echo !empty($detail[$amenityKey]) ? 'checked' : ''; ?>>
              <?php echo htmlspecialchars($amenityLabel, ENT_QUOTES, 'UTF-8'); ?>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <?php
          $currentCategoryId = isset($detail['id_category']) ? (int)$detail['id_category'] : 0;
          $detailCalendarDisplay = categories_parse_calendar_amenities_csv(
              isset($detail['calendar_amenities_csv']) ? (string)$detail['calendar_amenities_csv'] : '',
              array_keys($amenityFields)
          );
          if ($action === 'save_category' && isset($_POST['category_calendar_display_amenities']) && is_array($_POST['category_calendar_display_amenities'])) {
              $detailCalendarDisplay = categories_parse_calendar_amenities_csv(
                  implode(',', $_POST['category_calendar_display_amenities']),
                  array_keys($amenityFields)
              );
          }
          $detailCalendarDisplayMap = array();
          foreach ($detailCalendarDisplay as $amenityKeyTmp) {
              $detailCalendarDisplayMap[$amenityKeyTmp] = true;
          }
          $availableCalendarAmenityKeys = array();
          foreach ($amenityFields as $amenityKeyTmp => $_amenityLabelTmp) {
              if (!empty($detail[$amenityKeyTmp])) {
                  $availableCalendarAmenityKeys[] = $amenityKeyTmp;
              }
          }
          $selectedCalendarDisplayCount = 0;
          foreach ($availableCalendarAmenityKeys as $amenityKeyTmp) {
              if (isset($detailCalendarDisplayMap[$amenityKeyTmp])) {
                  $selectedCalendarDisplayCount++;
              }
          }
          $bedConfigsForCategory = ($currentCategoryId > 0 && isset($bedConfigsByCategory[$currentCategoryId]))
              ? $bedConfigsByCategory[$currentCategoryId]
              : array();
        ?>
        <?php
          $postedCategoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
          $bedConfigOpen = $action === 'add_bed_config' && $postedCategoryId === $currentCategoryId && !empty($error);
          $calendarDisplayOpen = $selectedCalendarDisplayCount > 0;
          if ($action === 'save_category' && $postedCategoryId === $currentCategoryId && !empty($error)) {
              $calendarDisplayOpen = true;
          }
        ?>
        <div class="category-calendar-display-section<?php echo $calendarDisplayOpen ? ' is-open' : ''; ?>">
          <div class="category-calendar-display-header">
            <button
              type="button"
              class="button-secondary category-calendar-display-toggle"
              aria-expanded="<?php echo $calendarDisplayOpen ? 'true' : 'false'; ?>">
              Mas opciones
            </button>
            <span class="muted">Iconos en calendario</span>
          </div>
          <div class="category-calendar-display-panel">
            <?php if (!$availableCalendarAmenityKeys): ?>
              <p class="muted">Primero marca amenidades de la categoria; despues podras elegir cuales mostrar en calendario.</p>
            <?php else: ?>
              <div class="category-calendar-display-grid">
                <?php foreach ($availableCalendarAmenityKeys as $amenityKey): ?>
                  <?php
                    $amenityLabel = isset($amenityFields[$amenityKey]) ? (string)$amenityFields[$amenityKey] : $amenityKey;
                    $amenityIconHtml = isset($amenityIcons[$amenityKey]) ? (string)$amenityIcons[$amenityKey] : '&#9679;';
                  ?>
                  <label class="checkbox amenity-item category-calendar-display-item">
                    <input
                      type="checkbox"
                      name="category_calendar_display_amenities[]"
                      value="<?php echo htmlspecialchars($amenityKey, ENT_QUOTES, 'UTF-8'); ?>"
                      <?php echo isset($detailCalendarDisplayMap[$amenityKey]) ? 'checked' : ''; ?>>
                    <span class="category-calendar-icon-preview" aria-hidden="true"><?php echo $amenityIconHtml; ?></span>
                    <span><?php echo htmlspecialchars($amenityLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="muted category-calendar-display-help">En calendario se muestra un icono por cada amenidad seleccionada.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="bed-config-section<?php echo $bedConfigOpen ? ' is-editing' : ''; ?>">
          <div class="bed-config-header">
            <h4>Configuracion de camas</h4>
            <?php if ($currentCategoryId > 0): ?>
              <button type="button" class="button-secondary bed-config-toggle" aria-expanded="<?php echo $bedConfigOpen ? 'true' : 'false'; ?>">+</button>
            <?php endif; ?>
          </div>
          <?php if ($currentCategoryId <= 0): ?>
            <p class="muted">Guarda la categoria para agregar configuracion de camas.</p>
          <?php else: ?>
            <?php if ($bedConfigsForCategory): ?>
              <div class="bed-config-list">
                <?php foreach ($bedConfigsForCategory as $config):
                  $bedType = isset($config['bed_type']) ? (string)$config['bed_type'] : '';
                  $bedLabel = isset($bedTypeOptions[$bedType]) ? $bedTypeOptions[$bedType] : $bedType;
                  $bedCount = isset($config['bed_count']) ? (int)$config['bed_count'] : 0;
                  $bedConfigId = isset($config['id_bed_config']) ? (int)$config['id_bed_config'] : 0;
                ?>
                  <div class="bed-config-row">
                    <span><?php echo htmlspecialchars($bedCount . ' ' . $bedLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <button type="submit" class="button-secondary" formnovalidate onclick="this.form.categories_action.value='remove_bed_config'; this.form.bed_config_id.value='<?php echo $bedConfigId; ?>';">
                      Quitar
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="muted">Sin configuracion de camas.</p>
            <?php endif; ?>

            <div class="bed-config-editor">
              <div class="bed-config-form">
                <label>
                  Tipo de cama
                  <select name="bed_type" required>
                    <?php foreach ($bedTypeOptions as $typeValue => $typeLabel): ?>
                      <option value="<?php echo htmlspecialchars($typeValue, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>
                  Cantidad
                  <input type="number" name="bed_count" min="1" value="1" required>
                </label>
                <button type="submit" class="button-secondary" onclick="this.form.categories_action.value='add_bed_config'; this.form.bed_config_id.value='0';">
                  Agregar
                </button>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-actions full">
        <button type="submit" onclick="this.form.categories_action.value='save_category'; this.form.bed_config_id.value='0';">Guardar categoria</button>
      </div>
    </form>
    <?php
    $panelContent = ob_get_clean();
    $dynamicTabs[] = array(
        'key' => 'category:' . $code,
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

// Datalist for rateplans (property-scoped)
if ($ratePlans) {
    echo '<datalist id="categoriesRateplans">';
    foreach ($ratePlans as $plan) {
        $code = isset($plan['rateplan_code']) ? (string)$plan['rateplan_code'] : '';
        $name = isset($plan['rateplan_name']) ? (string)$plan['rateplan_name'] : '';
        if ($code === '') {
            continue;
        }
        echo '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</datalist>';
}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.bed-config-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      var section = button.closest('.bed-config-section');
      if (!section) {
        return;
      }
      var isOpen = section.classList.toggle('is-editing');
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        var firstInput = section.querySelector('.bed-config-editor select, .bed-config-editor input');
        if (firstInput) {
          firstInput.focus();
        }
      }
    });
  });

  document.querySelectorAll('.category-calendar-display-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      var section = button.closest('.category-calendar-display-section');
      if (!section) {
        return;
      }
      var isOpen = section.classList.toggle('is-open');
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        var firstInput = section.querySelector('.category-calendar-display-panel input[type=\"checkbox\"]');
        if (firstInput) {
          firstInput.focus();
        }
      }
    });
  });

  var quickMenus = Array.prototype.slice.call(document.querySelectorAll('.category-quick-actions'));
  if (quickMenus.length) {
    var closeAllExcept = function (allowedMenu) {
      quickMenus.forEach(function (menu) {
        if (menu !== allowedMenu) {
          menu.removeAttribute('open');
        }
      });
    };

    quickMenus.forEach(function (menu) {
      menu.addEventListener('toggle', function () {
        if (menu.open) {
          closeAllExcept(menu);
        }
      });
    });

    document.addEventListener('click', function (ev) {
      var clickedInsideMenu = ev.target && ev.target.closest
        ? ev.target.closest('.category-quick-actions')
        : null;
      closeAllExcept(clickedInsideMenu || null);
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') {
        closeAllExcept(null);
      }
    });
  }

  document.querySelectorAll('.category-card[data-open-form-id]').forEach(function (card) {
    var submitCardOpen = function () {
      var formId = card.getAttribute('data-open-form-id');
      if (!formId) {
        return;
      }
      var openForm = document.getElementById(formId);
      if (openForm) {
        openForm.submit();
      }
    };

    card.addEventListener('click', function (ev) {
      if (!ev.target || !ev.target.closest) {
        return;
      }
      if (ev.target.closest('button, input, select, textarea, a, label, summary, details, form, .category-room-card')) {
        return;
      }
      submitCardOpen();
    });

    card.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter' && ev.key !== ' ') {
        return;
      }
      if (ev.target && ev.target !== card) {
        return;
      }
      ev.preventDefault();
      submitCardOpen();
    });
  });
});
</script>
<style>
.category-header-actions {
  position: relative;
}
.category-quick-actions {
  position: relative;
}
.category-quick-actions-toggle {
  list-style: none;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1px solid rgba(99, 212, 255, 0.45);
  background: rgba(28, 148, 196, 0.12);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  cursor: pointer;
}
.category-quick-actions-toggle::-webkit-details-marker {
  display: none;
}
.category-quick-actions-toggle:hover {
  background: rgba(28, 148, 196, 0.24);
}
.category-quick-actions-dots {
  display: inline-flex;
  flex-direction: column;
  gap: 3px;
}
.category-quick-actions-dots span {
  width: 4px;
  height: 4px;
  border-radius: 999px;
  background: #d5f3ff;
  display: block;
}
.category-quick-actions-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  min-width: 270px;
  padding: 8px;
  border-radius: 10px;
  border: 1px solid rgba(110, 170, 230, 0.35);
  background: rgba(7, 22, 42, 0.98);
  box-shadow: 0 12px 22px rgba(0, 0, 0, 0.35);
  z-index: 35;
}
.category-quick-actions:not([open]) .category-quick-actions-menu {
  display: none;
}
.category-quick-action-form {
  margin: 0;
}
.category-quick-action-form .button-secondary {
  width: 100%;
  text-align: left;
  justify-content: flex-start;
}
.visually-hidden {
  position: absolute !important;
  width: 1px !important;
  height: 1px !important;
  padding: 0 !important;
  margin: -1px !important;
  overflow: hidden !important;
  clip: rect(0, 0, 0, 0) !important;
  white-space: nowrap !important;
  border: 0 !important;
}
.category-properties-grid {
  display: grid;
  gap: 12px;
}
.category-properties-grid.is-all-properties {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
}
.category-property-section {
  margin-top: 14px;
  border: 1px solid rgba(122, 173, 218, 0.22);
  border-radius: 14px;
  padding: 12px;
  background: linear-gradient(180deg, rgba(11, 28, 48, 0.64), rgba(9, 23, 40, 0.84));
}
.category-property-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.category-property-head-title {
  min-width: 0;
}
.property-title-link-form {
  margin: 0;
}
.property-title-link {
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: #dff3ff;
  font-weight: 700;
  font-size: 30px;
  line-height: 1.15;
  text-align: left;
  cursor: pointer;
  transition: color 0.18s ease, text-shadow 0.18s ease;
}
.property-title-link:hover,
.property-title-link:focus-visible {
  color: #9fe4ff;
  text-shadow: 0 0 14px rgba(74, 192, 244, 0.35);
  outline: none;
}
.category-property-head-side {
  display: grid;
  gap: 8px;
  justify-items: end;
}
.category-property-head-info {
  display: grid;
  gap: 4px;
  text-align: right;
}
.category-property-head-info span {
  font-size: 14px;
  color: rgba(221, 237, 252, 0.9);
}
.category-property-head-metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.category-card-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 12px;
}
.category-card {
  border: 1px solid rgba(124, 184, 236, 0.28);
  border-radius: 12px;
  background: rgba(4, 18, 35, 0.64);
  padding: 12px;
  display: grid;
  gap: 10px;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
}
.category-card:hover {
  transform: translateY(-2px);
  border-color: rgba(129, 204, 255, 0.55);
  box-shadow: 0 12px 26px rgba(3, 16, 30, 0.35);
  background: rgba(7, 24, 44, 0.76);
}
.category-card.is-open {
  border-color: rgba(102, 209, 255, 0.64);
  box-shadow: 0 0 0 1px rgba(92, 196, 255, 0.22) inset;
}
.category-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.category-card-head h4 {
  margin: 0;
}
.category-card-head-right {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.category-occupancy {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 64px;
  height: 30px;
  border-radius: 999px;
  border: 1px solid rgba(106, 208, 255, 0.5);
  background: rgba(20, 86, 124, 0.42);
  font-weight: 700;
}
.category-card-menu {
  position: relative;
}
.category-card-menu-toggle {
  list-style: none;
  width: 30px;
  height: 30px;
  border-radius: 999px;
  border: 1px solid rgba(105, 173, 230, 0.45);
  background: rgba(18, 62, 93, 0.35);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.category-card-menu-toggle::-webkit-details-marker {
  display: none;
}
.category-card-menu-toggle:hover {
  background: rgba(25, 94, 135, 0.48);
}
.category-card-menu-dots {
  display: inline-flex;
  flex-direction: column;
  gap: 3px;
}
.category-card-menu-dots span {
  width: 3px;
  height: 3px;
  border-radius: 999px;
  background: #d8f4ff;
  display: block;
}
.category-card-menu-panel {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  min-width: 150px;
  padding: 8px;
  border-radius: 10px;
  border: 1px solid rgba(110, 170, 230, 0.35);
  background: rgba(7, 22, 42, 0.98);
  box-shadow: 0 12px 22px rgba(0, 0, 0, 0.35);
  z-index: 36;
}
.category-card-menu:not([open]) .category-card-menu-panel {
  display: none;
}
.category-card-menu-form {
  margin: 0;
}
.category-card-menu-form .button-secondary {
  width: 100%;
  text-align: left;
  justify-content: flex-start;
}
.category-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.category-metrics > div {
  border: 1px solid rgba(123, 173, 217, 0.22);
  border-radius: 10px;
  padding: 8px 10px;
  display: grid;
  gap: 3px;
}
.category-rateplan-link {
  width: 100%;
  justify-content: center;
}
.category-order-box {
  border: 1px dashed rgba(140, 190, 229, 0.34);
  border-radius: 10px;
  padding: 8px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.category-order-form input[type="number"] {
  width: 100px;
}
.category-capsules {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}
.capsule {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid rgba(116, 178, 224, 0.44);
  background: rgba(24, 78, 113, 0.36);
  color: rgba(225, 242, 255, 0.95);
  font-size: 12px;
  letter-spacing: 0.04em;
  padding: 4px 9px;
}
.category-rooms-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-weight: 600;
}
.category-rooms-header-right {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.category-add-room-form {
  margin: 0;
}
.category-add-room-button {
  width: 26px;
  height: 26px;
  border-radius: 999px;
  border: 1px solid rgba(96, 211, 142, 0.72);
  background: rgba(29, 125, 64, 0.82);
  color: #ecfff2;
  font-weight: 700;
  font-size: 18px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.category-add-room-button:hover {
  transform: translateY(-1px) scale(1.03);
  background: rgba(36, 148, 77, 0.95);
  box-shadow: 0 0 14px rgba(84, 211, 134, 0.45);
}
.category-room-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 8px;
}
.category-room-card {
  margin: 0;
}
.category-room-link {
  width: 100%;
  border: 1px solid rgba(118, 178, 224, 0.28);
  border-radius: 11px;
  background: rgba(7, 24, 44, 0.68);
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: inherit;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.category-room-card.is-occupied .category-room-link {
  border-color: rgba(255, 112, 112, 0.48);
  background: rgba(88, 25, 25, 0.3);
}
.category-room-card.is-available .category-room-link {
  border-color: rgba(116, 214, 157, 0.42);
  background: rgba(16, 80, 51, 0.26);
}
.category-room-link:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 18px rgba(8, 20, 37, 0.32);
}
.category-room-title {
  text-align: left;
  font-weight: 600;
}
.category-room-status {
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 12px;
  background: rgba(11, 20, 29, 0.48);
}
.category-room-card.is-occupied .category-room-status {
  color: #ffd4d4;
}
.category-room-card.is-available .category-room-status {
  color: #cef6df;
}
.category-extra-meta {
  border-top: 1px dashed rgba(132, 181, 228, 0.24);
  padding-top: 8px;
  display: grid;
  gap: 5px;
}
.category-property-section.is-compact {
  margin-top: 0;
  padding: 10px;
}
.category-property-section.is-compact .property-title-link {
  font-size: 24px;
}
.category-property-section.is-compact .category-property-head-info span {
  font-size: 13px;
}
.category-property-section.is-compact .category-card-grid {
  grid-template-columns: 1fr;
  gap: 8px;
}
.category-property-section.is-compact .category-card {
  padding: 10px;
  gap: 8px;
}
.category-property-section.is-compact .category-card-head h4 {
  font-size: 20px;
}
.category-property-section.is-compact .category-metrics > div {
  padding: 6px 8px;
}
.category-property-section.is-compact .category-room-link {
  padding: 8px 10px;
}
.category-property-section.is-compact .category-room-list {
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
}
@media (max-width: 900px) {
  .category-property-head {
    flex-direction: column;
  }
  .category-property-head-side {
    justify-items: start;
  }
  .category-property-head-info {
    text-align: left;
  }
  .category-property-head-metrics {
    justify-content: flex-start;
  }
  .property-title-link {
    font-size: 24px;
  }
  .category-card-grid {
    grid-template-columns: 1fr;
  }
  .category-metrics {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 1280px) {
  .category-properties-grid.is-all-properties {
    grid-template-columns: 1fr;
  }
}
</style>
