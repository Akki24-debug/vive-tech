<?php
$moduleKey = 'sale_items';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyCode = (string)$currentUser['company_code'];
$companyId   = (int)$currentUser['company_id'];
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

$properties = pms_fetch_properties($companyId);

if (!function_exists('sale_items_catalog_data_fallback')) {
    function sale_items_catalog_data_fallback($companyId, $propertyCode = null, $showInactive = 0, $itemId = 0, $categoryId = 0)
    {
        $out = array(array(), array());
        try {
            $pdo = pms_get_connection();
            $sql = 'SELECT
                        lic.id_line_item_catalog AS id_sale_item_catalog,
                        lic.catalog_type,
                        lic.id_category,
                        cat.category_name AS category,
                        parent_map.parent_first_id AS id_parent_sale_item_catalog,
                        parent_first.item_name AS parent_item_name,
                        parent_map.parent_item_ids,
                        parent_map.add_to_father_total,
                        parent_map.is_percent,
                        parent_map.percent_value,
                        lic.show_in_folio,
                        lic.allow_negative,
                        lic.item_name,
                        lic.description,
                        lic.default_unit_price_cents,
                        lic.is_active,
                        prop.code AS property_code,
                        CAST(NULL AS CHAR) AS tax_rule_ids
                    FROM line_item_catalog lic
                    JOIN sale_item_category cat
                      ON cat.id_sale_item_category = lic.id_category
                     AND cat.id_company = ?
                     AND cat.deleted_at IS NULL
                    LEFT JOIN (
                      SELECT
                        lcp.id_sale_item_catalog,
                        GROUP_CONCAT(DISTINCT lcp.id_parent_sale_item_catalog ORDER BY lcp.id_parent_sale_item_catalog) AS parent_item_ids,
                        MIN(lcp.id_parent_sale_item_catalog) AS parent_first_id,
                        MIN(lcp.add_to_father_total) AS add_to_father_total,
                        MAX(CASE WHEN lcp.percent_value IS NOT NULL THEN 1 ELSE 0 END) AS is_percent,
                        MIN(lcp.percent_value) AS percent_value
                      FROM line_item_catalog_parent lcp
                      WHERE lcp.deleted_at IS NULL
                        AND lcp.is_active = 1
                      GROUP BY lcp.id_sale_item_catalog
                    ) parent_map ON parent_map.id_sale_item_catalog = lic.id_line_item_catalog
                    LEFT JOIN line_item_catalog parent_first
                      ON parent_first.id_line_item_catalog = parent_map.parent_first_id
                    LEFT JOIN property prop ON prop.id_property = cat.id_property
                    WHERE lic.deleted_at IS NULL
                      AND lic.catalog_type IN (\'sale_item\',\'payment\',\'obligation\',\'income\',\'tax_rule\')
                      AND (? <> 0 OR lic.is_active = 1)
                      AND (? <> 0 OR cat.is_active = 1)
                      AND (? IS NULL OR ? = \'\' OR prop.code IS NULL OR prop.code = ?)
                      AND (? = 0 OR lic.id_category = ?)';
            $params = array(
                (int)$companyId,
                (int)$showInactive,
                (int)$showInactive,
                $propertyCode,
                $propertyCode,
                $propertyCode,
                (int)$categoryId,
                (int)$categoryId
            );
            if ((int)$itemId > 0) {
                $sql .= ' AND lic.id_line_item_catalog = ?';
                $params[] = (int)$itemId;
            }
            $sql .= ' ORDER BY cat.category_name, lic.item_name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out[0] = $rows;
            if ((int)$itemId > 0) {
                $out[1] = $rows ? array($rows[0]) : array();
            }
        } catch (Exception $e) {
            $out = array(array(), array());
        }
        return $out;
    }
}

$filters = array(
    'property_code' => isset($_POST['sale_items_filter_property']) ? strtoupper((string)$_POST['sale_items_filter_property']) : '',
    'show_inactive' => isset($_POST['sale_items_filter_inactive']) ? (int)$_POST['sale_items_filter_inactive'] : 0
);
$conceptFilters = array(
    'search' => isset($_POST['sale_items_filter_search']) ? trim((string)$_POST['sale_items_filter_search']) : '',
    'derived_only' => isset($_POST['sale_items_filter_derived']) ? 1 : 0
);
$lineItemFilters = array(
    'property' => isset($_POST['sale_items_line_property']) ? strtoupper((string)$_POST['sale_items_line_property']) : '',
    'reservation_id' => isset($_POST['sale_items_line_reservation_id']) ? (int)$_POST['sale_items_line_reservation_id'] : 0,
    'date_from' => isset($_POST['sale_items_line_from']) ? (string)$_POST['sale_items_line_from'] : '',
    'date_to' => isset($_POST['sale_items_line_to']) ? (string)$_POST['sale_items_line_to'] : '',
    'date_field' => isset($_POST['sale_items_line_date_field']) ? (string)$_POST['sale_items_line_date_field'] : 'created_at',
    'item_type' => isset($_POST['sale_items_line_item_type']) ? (string)$_POST['sale_items_line_item_type'] : '',
    'catalog_type' => isset($_POST['sale_items_line_catalog_type']) ? (string)$_POST['sale_items_line_catalog_type'] : '',
    'folio_status' => isset($_POST['sale_items_line_folio_status']) ? (string)$_POST['sale_items_line_folio_status'] : '',
    'status' => isset($_POST['sale_items_line_status']) ? (string)$_POST['sale_items_line_status'] : '',
    'currency' => isset($_POST['sale_items_line_currency']) ? strtoupper(trim((string)$_POST['sale_items_line_currency'])) : '',
    'limit' => isset($_POST['sale_items_line_limit']) ? (int)$_POST['sale_items_line_limit'] : 200,
    'derived_only' => isset($_POST['sale_items_line_derived']) ? 1 : 0,
    'hierarchy_mode' => isset($_POST['sale_items_line_hierarchy']) ? 1 : 0,
    'search' => isset($_POST['sale_items_line_search']) ? trim((string)$_POST['sale_items_line_search']) : '',
    'view_line_item_id' => isset($_POST['sale_items_view_line_item_id']) ? (int)$_POST['sale_items_view_line_item_id'] : 0
);
if ($lineItemFilters['reservation_id'] < 0) {
    $lineItemFilters['reservation_id'] = 0;
}
if (!in_array($lineItemFilters['date_field'], array('service_date', 'created_at'), true)) {
    $lineItemFilters['date_field'] = 'created_at';
}
if (!in_array($lineItemFilters['item_type'], array('', 'sale_item', 'tax_item', 'payment', 'obligation', 'income'), true)) {
    $lineItemFilters['item_type'] = '';
}
if (!in_array($lineItemFilters['catalog_type'], array('', 'sale_item', 'tax_rule', 'payment', 'obligation', 'income', 'none'), true)) {
    $lineItemFilters['catalog_type'] = '';
}
if (!in_array($lineItemFilters['folio_status'], array('', 'open', 'closed', 'void', 'paid', 'overdue'), true)) {
    $lineItemFilters['folio_status'] = '';
}
if (!in_array($lineItemFilters['limit'], array(100, 200, 500, 1000), true)) {
    $lineItemFilters['limit'] = 200;
}
if ($lineItemFilters['view_line_item_id'] < 0) {
    $lineItemFilters['view_line_item_id'] = 0;
}
$lineItemFilters['hierarchy_mode'] = !empty($lineItemFilters['hierarchy_mode']) ? 1 : 0;

$action      = isset($_POST['sale_items_action']) ? (string)$_POST['sale_items_action'] : '';
$itemMessage = null;
$itemError   = null;

/* subtabs */
$subtabState = pms_subtabs_init($moduleKey);
if (!function_exists('pms_subtabs_close_active')) {
    function pms_subtabs_close_active($moduleKey, $defaultActive = 'static:general') {
        return function_exists('pms_subtabs_init')
            ? pms_subtabs_init($moduleKey, $defaultActive)
            : array('open' => array(), 'active' => $defaultActive, 'dirty' => array());
    }
}
if (!function_exists('sale_items_close_posted_tab_to_general')) {
    function sale_items_close_posted_tab_to_general($moduleKey, array $state) {
        $currentKey = $moduleKey . '_current_subtab';
        $current = isset($_POST[$currentKey]) ? (string)$_POST[$currentKey] : '';
        if (strpos($current, 'dynamic:') === 0) {
            $target = substr($current, strlen('dynamic:'));
            $state['open'] = array_values(array_filter(
                isset($state['open']) ? $state['open'] : array(),
                function ($item) use ($target) { return $item !== $target; }
            ));
        }
        $state['active'] = 'static:general';
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['pms_subtabs'])) {
            $_SESSION['pms_subtabs'] = array();
        }
        $_SESSION['pms_subtabs'][$moduleKey] = $state;
        return $state;
    }
}
$postedAction = isset($_POST[$moduleKey . '_subtab_action']) ? (string)$_POST[$moduleKey . '_subtab_action'] : '';
$postedTarget = isset($_POST[$moduleKey . '_subtab_target']) ? (string)$_POST[$moduleKey . '_subtab_target'] : '';
if ($postedAction === 'open' && $postedTarget !== '') {
    $key = strpos($postedTarget, 'dynamic:') === 0 ? substr($postedTarget, 8) : $postedTarget;
    if (!in_array($key, $subtabState['open'], true)) {
        $subtabState['open'][] = $key;
        $_SESSION['pms_subtabs'][$moduleKey]['open'][] = $key;
    }
    $subtabState['active'] = 'dynamic:' . $key;
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'dynamic:' . $key;
}
if ($postedAction === 'close' && $postedTarget !== '') {
    $key = strpos($postedTarget, 'dynamic:') === 0 ? substr($postedTarget, 8) : $postedTarget;
    $subtabState['open'] = array_values(array_filter($subtabState['open'], function($v) use ($key){ return $v !== $key; }));
    $_SESSION['pms_subtabs'][$moduleKey]['open'] = $subtabState['open'];
    $subtabState['active'] = 'static:general';
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'static:general';
}
// Si solo se enviaron filtros (sin acciones ni subtab_action), regresa a la pestana general
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postedAction === '' && $action === '') {
    $subtabState['active'] = 'static:general';
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'static:general';
}

$subtabState['open'] = array_values(array_filter(
    $subtabState['open'],
    function ($v) { return $v !== 'category:new' && strpos($v, 'category:') !== 0; }
));
$_SESSION['pms_subtabs'][$moduleKey]['open'] = $subtabState['open'];
if (strpos($subtabState['active'], 'dynamic:category:') === 0 || $subtabState['active'] === 'dynamic:category:new') {
    $subtabState['active'] = 'static:general';
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'static:general';
}

/* actions */
if (in_array($action, array('create_category','update_category','delete_category'), true)) {
    $catId    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $propCode = isset($_POST['category_property_code']) ? strtoupper((string)$_POST['category_property_code']) : '';
    $catName  = isset($_POST['category_name']) ? (string)$_POST['category_name'] : '';
    $catDesc  = isset($_POST['category_description']) ? (string)$_POST['category_description'] : null;
    $isActive = isset($_POST['category_is_active']) ? 1 : 0;
    $parentId = isset($_POST['category_parent_id']) ? (int)$_POST['category_parent_id'] : 0;
    $actionCode = $action === 'create_category' ? 'create' : ($action === 'update_category' ? 'update' : 'delete');
    $shouldRun = true;
    if ($actionCode === 'create') {
        $nonce = isset($_POST['sale_items_nonce']) ? (string)$_POST['sale_items_nonce'] : '';
        if ($nonce === '' || !sale_items_consume_nonce($nonce)) {
            $shouldRun = false;
            $itemError = 'Solicitud duplicada o expirada. Recarga la pagina e intenta de nuevo.';
        }
    }
    $blockDelete = false;
    if ($actionCode === 'delete') {
        if ($parentId > 0) {
            $blockDelete = sale_items_category_has_concepts($catId);
        } else {
            $blockDelete = sale_items_category_has_children($catId) || sale_items_category_has_concepts($catId);
        }
        if ($blockDelete) {
            $itemError = 'No se puede eliminar: existen subcategorias o conceptos asignados.';
        }
    }
    if ($shouldRun && !$blockDelete) {
        try {
            pms_call_procedure('sp_sale_item_category_upsert', array(
                $actionCode,
                $catId,
                $companyCode,
                $propCode === '' ? null : $propCode,
                $catName,
                $catDesc,
                $isActive,
                $actorUserId,
                $parentId > 0 ? $parentId : 0
            ));
            $itemMessage = $actionCode === 'create' ? 'Categoria creada.' : ($actionCode === 'update' ? 'Categoria actualizada.' : 'Categoria eliminada.');
            if ($actionCode === 'delete') {
                $subtabState['open'] = array_values(array_filter(
                    $subtabState['open'],
                    function ($v) use ($catId) { return $v !== 'category:' . $catId; }
                ));
                $_SESSION['pms_subtabs'][$moduleKey]['open'] = $subtabState['open'];
            }
            $subtabState = sale_items_close_posted_tab_to_general($moduleKey, $subtabState);
        } catch (Exception $e) {
            $itemError = $e->getMessage();
        }
    }
}

if ($action === 'clone_item') {
    $sourceItemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $cloneName = isset($_POST['clone_item_name']) ? trim((string)$_POST['clone_item_name']) : '';
    if ($sourceItemId <= 0) {
        $itemError = 'Concepto invalido para clonar.';
    } else {
        try {
            $resultSets = pms_call_procedure('sp_sale_item_catalog_clone', array(
                $companyCode,
                $sourceItemId,
                $cloneName !== '' ? $cloneName : null,
                $actorUserId
            ));
            $newItemId = isset($resultSets[0][0]['id_sale_item_catalog'])
                ? (int)$resultSets[0][0]['id_sale_item_catalog']
                : 0;
            if ($newItemId <= 0) {
                throw new Exception('No se pudo obtener el concepto clonado.');
            }
            $itemMessage = 'Concepto clonado correctamente.';
            $cloneTabKey = 'concept:' . $newItemId;
            if (!in_array($cloneTabKey, $subtabState['open'], true)) {
                $subtabState['open'][] = $cloneTabKey;
            }
            $subtabState['active'] = 'dynamic:' . $cloneTabKey;
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['pms_subtabs'])) {
                $_SESSION['pms_subtabs'] = array();
            }
            $_SESSION['pms_subtabs'][$moduleKey] = $subtabState;
        } catch (Exception $e) {
            $itemError = $e->getMessage();
        }
    }
}

if (in_array($action, array('create_item','update_item','delete_item'), true)) {
    $itemId   = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $catId    = isset($_POST['item_category_id']) ? (int)$_POST['item_category_id'] : 0;
    $catalogType = isset($_POST['item_catalog_type']) ? (string)$_POST['item_catalog_type'] : 'sale_item';
    $name     = isset($_POST['item_name']) ? (string)$_POST['item_name'] : '';
    $desc     = isset($_POST['item_description']) ? (string)$_POST['item_description'] : null;
    $priceCts = isset($_POST['item_price']) ? (int)round(((float)str_replace(',', '.', $_POST['item_price'])) * 100) : 0;
    $parentIdsInput = isset($_POST['item_parent_ids']) ? $_POST['item_parent_ids'] : array();
    $parentIds = sale_items_parse_ids($parentIdsInput);
    if ($itemId > 0 && $parentIds) {
        $parentIds = array_values(array_diff($parentIds, array($itemId)));
    }
    $parentIdsCsv = $parentIds ? implode(',', $parentIds) : '';
    $isPercent = isset($_POST['item_is_percent']) ? 1 : 0;
    $percentValue = isset($_POST['item_percent_value']) && $_POST['item_percent_value'] !== ''
        ? (float)str_replace(',', '.', $_POST['item_percent_value'])
        : 0;
    $taxRuleIds = '';
    $showInFolio = isset($_POST['item_show_in_folio']) ? 1 : 0;
    $allowNegative = isset($_POST['item_allow_negative']) ? 1 : 0;
    $isActive = isset($_POST['item_is_active']) ? 1 : 0;
    $addToFatherTotal = isset($_POST['item_add_to_father_total'])
        ? (int)$_POST['item_add_to_father_total']
        : 1;
    $parentTotalByParent = array();
    $parentPercentByParent = array();
    $parentShowInFolioByParent = array();
    if (!empty($_POST['parent_total_state_json'])) {
        $decodedParentTotal = json_decode((string)$_POST['parent_total_state_json'], true);
        if (is_array($decodedParentTotal)) {
            foreach ($decodedParentTotal as $pid => $flag) {
                $parentId = (int)$pid;
                if ($parentId <= 0) {
                    continue;
                }
                if ($parentIds && !in_array($parentId, $parentIds, true)) {
                    continue;
                }
                $parentTotalByParent[$parentId] = !empty($flag) ? 1 : 0;
            }
        }
    }
    if (!empty($_POST['parent_percent_state_json'])) {
        $decodedParentPercent = json_decode((string)$_POST['parent_percent_state_json'], true);
        if (is_array($decodedParentPercent)) {
            foreach ($decodedParentPercent as $pid => $value) {
                $parentId = (int)$pid;
                if ($parentId <= 0) {
                    continue;
                }
                if ($parentIds && !in_array($parentId, $parentIds, true)) {
                    continue;
                }
                if ($value === '' || $value === null) {
                    $parentPercentByParent[$parentId] = null;
                } else {
                    $parentPercentByParent[$parentId] = (float)str_replace(',', '.', (string)$value);
                }
            }
        }
    }
    if (!empty($_POST['parent_show_in_folio_state_json'])) {
        $decodedParentShow = json_decode((string)$_POST['parent_show_in_folio_state_json'], true);
        if (is_array($decodedParentShow)) {
            foreach ($decodedParentShow as $pid => $flag) {
                $parentId = (int)$pid;
                if ($parentId <= 0) {
                    continue;
                }
                if ($parentIds && !in_array($parentId, $parentIds, true)) {
                    continue;
                }
                $parentShowInFolioByParent[$parentId] = !empty($flag) ? 1 : 0;
            }
        }
    }
    if (!$parentTotalByParent && $parentIds) {
        foreach ($parentIds as $pid) {
            $parentTotalByParent[(int)$pid] = $addToFatherTotal ? 1 : 0;
        }
    }
    if (!$parentPercentByParent && $parentIds) {
        foreach ($parentIds as $pid) {
            $pid = (int)$pid;
            $parentPercentByParent[$pid] = $isPercent ? (float)$percentValue : null;
        }
    }
    if (!$parentShowInFolioByParent && $parentIds) {
        foreach ($parentIds as $pid) {
            $parentShowInFolioByParent[(int)$pid] = $showInFolio ? 1 : 0;
        }
    }
    if ($parentPercentByParent) {
        $hasAnyPercent = false;
        foreach ($parentPercentByParent as $pv) {
            if ($pv !== null) {
                $hasAnyPercent = true;
                break;
            }
        }
        $isPercent = $hasAnyPercent ? 1 : 0;
    }
    $existingParentTotalByParent = array();
    $existingParentPercentByParent = array();
    $existingParentShowInFolioByParent = array();
    if ($action === 'update_item' && $itemId > 0) {
        try {
            $existingParentTotalByParent = sale_items_load_parent_total_map($itemId);
            $existingParentPercentByParent = sale_items_load_parent_percent_map($itemId);
            $existingParentShowInFolioByParent = sale_items_load_parent_show_in_folio_map($itemId);
        } catch (Exception $e) {
            $existingParentTotalByParent = array();
            $existingParentPercentByParent = array();
            $existingParentShowInFolioByParent = array();
        }
    }
    $calcUpdate = isset($_POST['calc_update']) ? 1 : 0;
    if (!$calcUpdate && (!empty($_POST['calc_state_json']) || !empty($_POST['calc_components_csv']) || !empty($_POST['calc_sign_json']))) {
        $calcUpdate = 1;
    }
    $calcComponents = array();
    if (!empty($_POST['calc_components_csv'])) {
        $calcComponents = sale_items_parse_ids($_POST['calc_components_csv']);
    }
    if (!$calcComponents && isset($_POST['calc_components'])) {
        $calcComponents = sale_items_parse_ids($_POST['calc_components']);
    }
    $calcSigns = array();
    $calcStateByParent = array();
    if (!empty($_POST['calc_state_json'])) {
        $decoded = json_decode((string)$_POST['calc_state_json'], true);
        if (is_array($decoded)) {
            $isParentMap = false;
            foreach ($decoded as $k => $v) {
                if (is_array($v)) {
                    $isParentMap = true;
                    break;
                }
            }
            if ($isParentMap) {
                $calcStateByParent = $decoded;
            } else {
                $calcSigns = $decoded;
                if (!$calcComponents) {
                    $calcComponents = array_map('intval', array_keys($decoded));
                }
            }
        }
    }
    if (!empty($_POST['calc_sign_json']) && !$calcStateByParent) {
        $decoded = json_decode((string)$_POST['calc_sign_json'], true);
        if (is_array($decoded)) {
            $calcSigns = $decoded;
        }
    }
    if (!$calcSigns && !$calcStateByParent && isset($_POST['calc_sign']) && is_array($_POST['calc_sign'])) {
        $calcSigns = $_POST['calc_sign'];
    }
    if (!$calcStateByParent && !$calcComponents && $calcSigns) {
        $calcComponents = array_map('intval', array_keys($calcSigns));
    }
    $actionCode = $action === 'create_item' ? 'create' : ($action === 'update_item' ? 'update' : 'delete');
    $shouldRun = true;
    if ($actionCode === 'create') {
        $nonce = isset($_POST['sale_items_nonce']) ? (string)$_POST['sale_items_nonce'] : '';
        if ($nonce === '' || !sale_items_consume_nonce($nonce)) {
            $shouldRun = false;
            $itemError = 'Solicitud duplicada ignorada.';
        }
    }
    if ($shouldRun) {
        try {
            $resultSets = pms_call_procedure('sp_sale_item_catalog_upsert', array(
                $actionCode,
                $itemId,
                $companyCode,
                $catalogType,
                $catId,
                $parentIdsCsv,
                $name,
                $desc,
                $priceCts,
                $isPercent,
                $percentValue,
                $taxRuleIds,
                $showInFolio,
                $allowNegative,
                $isActive,
                $addToFatherTotal,
                $actorUserId
            ));
            $targetItemId = $actionCode === 'create'
                ? (isset($resultSets[0][0]['id_sale_item_catalog']) ? (int)$resultSets[0][0]['id_sale_item_catalog'] : 0)
                : $itemId;
            if ($calcUpdate && $targetItemId > 0) {
                if ($calcStateByParent) {
                    foreach ($calcStateByParent as $parentId => $signMap) {
                        $pid = (int)$parentId;
                        if ($pid <= 0 || !is_array($signMap)) {
                            continue;
                        }
                        $componentIds = array_map('intval', array_keys($signMap));
                        sale_items_save_calc_map($targetItemId, $pid, $componentIds, $signMap, $actorUserId);
                    }
                } else {
                    $calcParentId = isset($_POST['calc_parent_id']) ? (int)$_POST['calc_parent_id'] : 0;
                    if ($calcParentId <= 0 && !empty($parentIds)) {
                        $calcParentId = (int)reset($parentIds);
                    }
                    sale_items_save_calc_map($targetItemId, $calcParentId, $calcComponents, $calcSigns, $actorUserId);
                }
            }
            if ($targetItemId > 0) {
                if ($actionCode === 'update' && empty($_POST['parent_total_state_json']) && $existingParentTotalByParent) {
                    $filteredMap = array();
                    foreach ($parentIds as $pid) {
                        $pid = (int)$pid;
                        if ($pid <= 0) {
                            continue;
                        }
                        if (array_key_exists($pid, $existingParentTotalByParent)) {
                            $filteredMap[$pid] = !empty($existingParentTotalByParent[$pid]) ? 1 : 0;
                        }
                    }
                    if ($filteredMap) {
                        $parentTotalByParent = $filteredMap;
                    }
                }
                if ($actionCode === 'update' && empty($_POST['parent_percent_state_json']) && $existingParentPercentByParent) {
                    $filteredPercentMap = array();
                    foreach ($parentIds as $pid) {
                        $pid = (int)$pid;
                        if ($pid <= 0) {
                            continue;
                        }
                        if (array_key_exists($pid, $existingParentPercentByParent)) {
                            $filteredPercentMap[$pid] = $existingParentPercentByParent[$pid];
                        }
                    }
                    if ($filteredPercentMap) {
                        $parentPercentByParent = $filteredPercentMap;
                    }
                }
                if ($actionCode === 'update' && empty($_POST['parent_show_in_folio_state_json']) && $existingParentShowInFolioByParent) {
                    $filteredShowMap = array();
                    foreach ($parentIds as $pid) {
                        $pid = (int)$pid;
                        if ($pid <= 0) {
                            continue;
                        }
                        if (array_key_exists($pid, $existingParentShowInFolioByParent)) {
                            $filteredShowMap[$pid] = !empty($existingParentShowInFolioByParent[$pid]) ? 1 : 0;
                        }
                    }
                    if ($filteredShowMap) {
                        $parentShowInFolioByParent = $filteredShowMap;
                    }
                }
                if ($parentTotalByParent) {
                    sale_items_save_parent_total_map(
                        $targetItemId,
                        $parentTotalByParent,
                        $parentPercentByParent,
                        $parentShowInFolioByParent,
                        $showInFolio,
                        $actorUserId
                    );
                }
            }
            $itemMessage = $actionCode === 'create' ? 'Concepto creado.' : ($actionCode === 'update' ? 'Concepto actualizado.' : 'Concepto eliminado.');
            if ($actionCode === 'create' && $catId > 0) {
                $subtabState['open'] = array_values(array_filter(
                    $subtabState['open'],
                    function ($v) use ($catId) { return $v !== 'concept:new:' . $catId; }
                ));
                $_SESSION['pms_subtabs'][$moduleKey]['open'] = $subtabState['open'];
            }
            if ($actionCode === 'delete' && $itemId > 0) {
                $subtabState['open'] = array_values(array_filter(
                    $subtabState['open'],
                    function ($v) use ($itemId) { return $v !== 'concept:' . $itemId; }
                ));
                $_SESSION['pms_subtabs'][$moduleKey]['open'] = $subtabState['open'];
            }
            $subtabState = sale_items_close_posted_tab_to_general($moduleKey, $subtabState);
        } catch (Exception $e) {
            $itemError = $e->getMessage();
        }
    }
}

if ($action === 'update_child_relation') {
    $parentId = isset($_POST['relation_parent_id']) ? (int)$_POST['relation_parent_id'] : 0;
    $childId = isset($_POST['relation_child_id']) ? (int)$_POST['relation_child_id'] : 0;
    $addToFatherTotal = isset($_POST['relation_add_to_father_total']) ? 1 : 0;
    $relationShowInFolio = isset($_POST['relation_show_in_folio']) ? 1 : 0;
    $percentValue = null;
    if (isset($_POST['relation_percent_value'])) {
        $rawPercent = trim((string)$_POST['relation_percent_value']);
        if ($rawPercent !== '') {
            $percentValue = (float)str_replace(',', '.', $rawPercent);
        }
    }
    $componentIds = array();
    if (isset($_POST['relation_components'])) {
        $componentIds = sale_items_parse_ids($_POST['relation_components']);
    }
    $signs = array();
    if (isset($_POST['relation_sign']) && is_array($_POST['relation_sign'])) {
        foreach ($_POST['relation_sign'] as $cid => $sign) {
            $componentId = (int)$cid;
            if ($componentId <= 0) {
                continue;
            }
            $signs[$componentId] = ((int)$sign < 0) ? -1 : 1;
        }
    }

    if ($parentId <= 0 || $childId <= 0) {
        $itemError = 'Relacion padre-hijo invalida.';
    } else {
        try {
            pms_call_procedure('sp_sale_item_catalog_parent_total_upsert', array(
                'upsert',
                $childId,
                $parentId,
                $addToFatherTotal,
                $relationShowInFolio,
                $percentValue,
                $actorUserId
            ));
            sale_items_save_calc_map($childId, $parentId, $componentIds, $signs, $actorUserId);
            $itemMessage = 'Relacion padre-hijo actualizada.';
        } catch (Exception $e) {
            $itemError = $e->getMessage();
        }
    }
}

if ($action === 'update_child_links') {
    $parentId = isset($_POST['relation_parent_id']) ? (int)$_POST['relation_parent_id'] : 0;
    $childIdsInput = isset($_POST['relation_child_ids']) ? $_POST['relation_child_ids'] : array();
    $selectedChildIds = sale_items_parse_ids($childIdsInput);
    if ($parentId > 0 && $selectedChildIds) {
        $selectedChildIds = array_values(array_diff($selectedChildIds, array($parentId)));
    }

    if ($parentId <= 0) {
        $itemError = 'Concepto padre invalido.';
    } else {
        try {
            $pdo = pms_get_connection();

            $parentStmt = $pdo->prepare(
                'SELECT cat.id_property
                   FROM line_item_catalog lic
                   JOIN sale_item_category cat
                     ON cat.id_sale_item_category = lic.id_category
                    AND cat.deleted_at IS NULL
                    AND cat.id_company = ?
                  WHERE lic.id_line_item_catalog = ?
                    AND lic.deleted_at IS NULL
                  LIMIT 1'
            );
            $parentStmt->execute(array($companyId, $parentId));
            $parentRow = $parentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$parentRow) {
                throw new Exception('El concepto padre no existe o no pertenece a la empresa.');
            }
            $parentPropertyId = isset($parentRow['id_property']) && $parentRow['id_property'] !== null
                ? (int)$parentRow['id_property']
                : 0;

            $existingStmt = $pdo->prepare(
                'SELECT
                    lcp.id_sale_item_catalog,
                    lcp.add_to_father_total,
                    lcp.percent_value,
                    lcp.show_in_folio_relation,
                    child.show_in_folio AS child_default_show_in_folio
                   FROM line_item_catalog_parent lcp
                   JOIN line_item_catalog child
                     ON child.id_line_item_catalog = lcp.id_sale_item_catalog
                    AND child.deleted_at IS NULL
                  WHERE lcp.id_parent_sale_item_catalog = ?'
            );
            $existingStmt->execute(array($parentId));
            $existingMap = array();
            foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cid = isset($row['id_sale_item_catalog']) ? (int)$row['id_sale_item_catalog'] : 0;
                if ($cid <= 0) {
                    continue;
                }
                $existingMap[$cid] = array(
                    'add_to_father_total' => !empty($row['add_to_father_total']) ? 1 : 0,
                    'percent_value' => (isset($row['percent_value']) && $row['percent_value'] !== null)
                        ? (float)$row['percent_value']
                        : null,
                    'show_in_folio_relation' => (isset($row['show_in_folio_relation']) && $row['show_in_folio_relation'] !== null)
                        ? (!empty($row['show_in_folio_relation']) ? 1 : 0)
                        : null,
                    'child_default_show_in_folio' => !empty($row['child_default_show_in_folio']) ? 1 : 0
                );
            }

            $validChildIds = array();
            if ($selectedChildIds) {
                $ph = implode(',', array_fill(0, count($selectedChildIds), '?'));
                $sql = 'SELECT lic.id_line_item_catalog
                          FROM line_item_catalog lic
                          JOIN sale_item_category cat
                            ON cat.id_sale_item_category = lic.id_category
                           AND cat.deleted_at IS NULL
                           AND cat.id_company = ?
                         WHERE lic.deleted_at IS NULL
                           AND lic.id_line_item_catalog <> ?
                           AND lic.id_line_item_catalog IN (' . $ph . ')';
                $params = array($companyId, $parentId);
                foreach ($selectedChildIds as $cid) {
                    $params[] = (int)$cid;
                }
                if ($parentPropertyId > 0) {
                    $sql .= ' AND (cat.id_property IS NULL OR cat.id_property = ?)';
                    $params[] = $parentPropertyId;
                }
                $stmtValid = $pdo->prepare($sql);
                $stmtValid->execute($params);
                foreach ($stmtValid->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $cid = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
                    if ($cid > 0) {
                        $validChildIds[] = $cid;
                    }
                }
                $validChildIds = array_values(array_unique($validChildIds));
            }

            if ($validChildIds) {
                $phKeep = implode(',', array_fill(0, count($validChildIds), '?'));
                $paramsDeactivate = array_merge(array($parentId), $validChildIds);
                $stmtDeactivateLinks = $pdo->prepare(
                    'UPDATE line_item_catalog_parent
                        SET is_active = 0,
                            deleted_at = NOW(),
                            updated_at = NOW()
                      WHERE id_parent_sale_item_catalog = ?
                        AND is_active = 1
                        AND deleted_at IS NULL
                        AND id_sale_item_catalog NOT IN (' . $phKeep . ')'
                );
                $stmtDeactivateLinks->execute($paramsDeactivate);

                $stmtDeactivateCalc = $pdo->prepare(
                    'UPDATE line_item_catalog_calc
                        SET is_active = 0,
                            deleted_at = NOW(),
                            updated_at = NOW()
                      WHERE id_parent_line_item_catalog = ?
                        AND is_active = 1
                        AND deleted_at IS NULL
                        AND id_line_item_catalog NOT IN (' . $phKeep . ')'
                );
                $stmtDeactivateCalc->execute($paramsDeactivate);
            } else {
                $stmtDeactivateLinksAll = $pdo->prepare(
                    'UPDATE line_item_catalog_parent
                        SET is_active = 0,
                            deleted_at = NOW(),
                            updated_at = NOW()
                      WHERE id_parent_sale_item_catalog = ?
                        AND is_active = 1
                        AND deleted_at IS NULL'
                );
                $stmtDeactivateLinksAll->execute(array($parentId));

                $stmtDeactivateCalcAll = $pdo->prepare(
                    'UPDATE line_item_catalog_calc
                        SET is_active = 0,
                            deleted_at = NOW(),
                            updated_at = NOW()
                      WHERE id_parent_line_item_catalog = ?
                        AND is_active = 1
                        AND deleted_at IS NULL'
                );
                $stmtDeactivateCalcAll->execute(array($parentId));
            }

            foreach ($validChildIds as $childId) {
                $childId = (int)$childId;
                if ($childId <= 0) {
                    continue;
                }
                $existing = isset($existingMap[$childId]) ? $existingMap[$childId] : array();
                $addToFather = isset($existing['add_to_father_total']) ? (int)$existing['add_to_father_total'] : 1;
                $percentValue = array_key_exists('percent_value', $existing) ? $existing['percent_value'] : null;
                $showInFolioRelation = array_key_exists('show_in_folio_relation', $existing)
                    ? $existing['show_in_folio_relation']
                    : (isset($existing['child_default_show_in_folio']) ? (int)$existing['child_default_show_in_folio'] : null);
                pms_call_procedure('sp_sale_item_catalog_parent_total_upsert', array(
                    'upsert',
                    $childId,
                    $parentId,
                    $addToFather,
                    $showInFolioRelation,
                    $percentValue,
                    $actorUserId
                ));
            }

            $itemMessage = 'Lista de hijos actualizada.';
        } catch (Exception $e) {
            $itemError = $e->getMessage();
        }
    }
}

if ($action === 'update_line_item_type') {
    $lineItemId = isset($_POST['line_item_id']) ? (int)$_POST['line_item_id'] : 0;
    $lineItemTypeRaw = isset($_POST['line_item_type']) ? strtolower(trim((string)$_POST['line_item_type'])) : '';
    $allowedLineItemTypes = array('sale_item', 'tax_item', 'payment', 'obligation', 'income');
    $lineItemType = in_array($lineItemTypeRaw, $allowedLineItemTypes, true) ? $lineItemTypeRaw : '';

    if ($lineItemId <= 0) {
        $itemError = 'Line item inválido.';
    } elseif ($lineItemType === '') {
        $itemError = 'Tipo de line item inválido.';
    } else {
        try {
            pms_call_procedure('sp_line_item_type_upsert', array(
                'update',
                $lineItemId,
                $companyCode,
                $lineItemType,
                $actorUserId
            ));
            $itemMessage = 'Tipo de line item actualizado.';
        } catch (Exception $e) {
            $itemError = $e->getMessage();
        }
    }
}

if ($action === 'clear_line_item_view') {
    $lineItemFilters['view_line_item_id'] = 0;
}
if ($action === 'view_line_item') {
    $lineItemFilters['view_line_item_id'] = isset($_POST['view_line_item_id']) ? (int)$_POST['view_line_item_id'] : 0;
}

/* data */
$categoryList = array();
try {
    $sets = pms_call_procedure('sp_sale_item_category_data', array(
        $companyCode,
        $filters['property_code'] === '' ? null : $filters['property_code'],
        $filters['show_inactive'],
        0
    ));
    $categoryList = isset($sets[0]) ? $sets[0] : array();
} catch (Exception $e) {
    $itemError = $itemError ?: $e->getMessage();
}

/* map categories by id for quick lookup */
$categoriesById = array();
foreach ($categoryList as $c) {
    $cid = isset($c['id_sale_item_category']) ? (int)$c['id_sale_item_category'] : 0;
    if ($cid > 0) $categoriesById[$cid] = $c;
}

/* split parent categories and subcategories */
$parentCategories = array();
$subcategoriesByParent = array();
$subcategoryList = array();
foreach ($categoryList as $c) {
    $cid = isset($c['id_sale_item_category']) ? (int)$c['id_sale_item_category'] : 0;
    $parentId = isset($c['id_parent_sale_item_category']) ? (int)$c['id_parent_sale_item_category'] : 0;
    if ($parentId > 0) {
        if (!isset($subcategoriesByParent[$parentId])) {
            $subcategoriesByParent[$parentId] = array();
        }
        $subcategoriesByParent[$parentId][] = $c;
        $subcategoryList[] = $c;
    } else {
        $parentCategories[] = $c;
    }
}

/* load open categories detail (tabs) */
$openCategoryIds = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $key) {
    if (strpos($key, 'category:') === 0) {
        $id = (int)substr($key, strlen('category:'));
        if ($id > 0 && !in_array($id, $openCategoryIds, true)) {
            $openCategoryIds[] = $id;
        }
    }
}

$categoryDetail = array();
foreach ($openCategoryIds as $cid) {
    try {
        $d = pms_call_procedure('sp_sale_item_category_data', array($companyCode, null, 1, $cid));
        $categoryDetail[$cid] = isset($d[1][0]) ? $d[1][0] : null;
    } catch (Exception $e) {
        $categoryDetail[$cid] = null;
    }
}

$conceptsByCategory = array();
$catalogList = array();
try {
    $csets = pms_call_procedure('sp_sale_item_catalog_data', array(
        $companyCode,
        $filters['property_code'] === '' ? null : $filters['property_code'],
        $filters['show_inactive'],
        0,
        0
    ));
    $catalogList = isset($csets[0]) ? $csets[0] : array();
} catch (Exception $e) {
    $catalogList = array();
}
if (!$catalogList) {
    $csets = sale_items_catalog_data_fallback($companyId, $filters['property_code'] === '' ? null : $filters['property_code'], $filters['show_inactive'], 0, 0);
    $catalogList = isset($csets[0]) ? $csets[0] : array();
}
foreach ($catalogList as $c) {
    $catId = isset($c['id_category']) ? (int)$c['id_category'] : 0;
    if ($catId <= 0) {
        continue;
    }
    if (!isset($conceptsByCategory[$catId])) {
        $conceptsByCategory[$catId] = array();
    }
    $conceptsByCategory[$catId][] = $c;
}
$parentConceptOptions = $catalogList;
$parentConceptLabelMap = array();
foreach ($catalogList as $c) {
    $cid = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
    if ($cid <= 0) {
        continue;
    }
    $parentConceptLabelMap[$cid] = sale_items_parent_label($c, $categoriesById);
}
$derivedParentChildMap = sale_items_build_derived_parent_map($catalogList, $categoriesById);
$calcParentChildMap = sale_items_build_calc_parent_map_all($catalogList, $categoriesById, $obligationBySaleItem, $incomeBySaleItem);
$conceptListFiltered = array();
foreach ($catalogList as $c) {
    $parents = sale_items_parse_ids(isset($c['parent_item_ids']) ? $c['parent_item_ids'] : '');
    if ($conceptFilters['derived_only'] && !$parents) {
        continue;
    }
    if ($conceptFilters['search'] !== '') {
        $parentLabels = array();
        foreach ($parents as $pid) {
            if (isset($parentConceptLabelMap[$pid])) {
                $parentLabels[] = $parentConceptLabelMap[$pid];
            }
        }
        $hay = strtolower(
            (string)($c['item_name'] ?? '')
            . ' ' . (string)($c['category'] ?? '')
            . ' ' . (string)($c['category_name'] ?? '')
            . ' ' . (string)($c['property_code'] ?? '')
            . ' ' . implode(' ', $parentLabels)
        );
        if (strpos($hay, strtolower($conceptFilters['search'])) === false) {
            continue;
        }
    }
    $conceptListFiltered[] = $c;
}
usort($conceptListFiltered, function ($a, $b) {
    return strcmp((string)($a['item_name'] ?? ''), (string)($b['item_name'] ?? ''));
});

$conceptTreeNodes = array();
$conceptTreeEdges = array();
$catalogById = array();
foreach ($catalogList as $catalogRow) {
    $catalogId = isset($catalogRow['id_sale_item_catalog']) ? (int)$catalogRow['id_sale_item_catalog'] : 0;
    if ($catalogId <= 0) {
        continue;
    }
    $catalogById[$catalogId] = $catalogRow;
    $conceptTreeNodes[] = array(
        'id' => $catalogId,
        'label' => isset($catalogRow['item_name']) && trim((string)$catalogRow['item_name']) !== ''
            ? (string)$catalogRow['item_name']
            : ('Concepto #' . $catalogId),
        'catalog_type' => isset($catalogRow['catalog_type']) ? (string)$catalogRow['catalog_type'] : '',
        'category' => isset($catalogRow['category']) ? (string)$catalogRow['category'] : '',
        'property_code' => isset($catalogRow['property_code']) ? strtoupper((string)$catalogRow['property_code']) : '',
        'default_unit_price_cents' => isset($catalogRow['default_unit_price_cents']) ? (int)$catalogRow['default_unit_price_cents'] : 0,
        'is_active' => isset($catalogRow['is_active']) ? (int)$catalogRow['is_active'] : 1,
        'show_in_folio' => isset($catalogRow['show_in_folio']) ? (int)$catalogRow['show_in_folio'] : 1,
        'allow_negative' => isset($catalogRow['allow_negative']) ? (int)$catalogRow['allow_negative'] : 0
    );
}

if ($catalogById) {
    try {
        $treePdo = pms_get_connection();
        $catalogIds = array_map('intval', array_keys($catalogById));
        $placeholders = implode(',', array_fill(0, count($catalogIds), '?'));

        $relationSql = 'SELECT
                lcp.id_parent_sale_item_catalog AS parent_id,
                lcp.id_sale_item_catalog AS child_id,
                COALESCE(lcp.add_to_father_total, 1) AS add_to_father_total,
                lcp.percent_value,
                lcp.show_in_folio_relation
            FROM line_item_catalog_parent lcp
            WHERE lcp.deleted_at IS NULL
              AND lcp.is_active = 1
              AND lcp.id_parent_sale_item_catalog IN (' . $placeholders . ')
              AND lcp.id_sale_item_catalog IN (' . $placeholders . ')';
        $relationStmt = $treePdo->prepare($relationSql);
        $relationStmt->execute(array_merge($catalogIds, $catalogIds));
        $relationRows = $relationStmt->fetchAll(PDO::FETCH_ASSOC);

        $calcSql = 'SELECT
                lcc.id_parent_line_item_catalog AS parent_id,
                lcc.id_line_item_catalog AS child_id,
                lcc.id_component_line_item_catalog AS component_id,
                COALESCE(lcc.is_positive, 1) AS is_positive
            FROM line_item_catalog_calc lcc
            WHERE lcc.deleted_at IS NULL
              AND lcc.is_active = 1
              AND lcc.id_parent_line_item_catalog IN (' . $placeholders . ')
              AND lcc.id_line_item_catalog IN (' . $placeholders . ')';
        $calcStmt = $treePdo->prepare($calcSql);
        $calcStmt->execute(array_merge($catalogIds, $catalogIds));
        $calcRows = $calcStmt->fetchAll(PDO::FETCH_ASSOC);

        $calcByRelation = array();
        foreach ($calcRows as $calcRow) {
            $parentId = isset($calcRow['parent_id']) ? (int)$calcRow['parent_id'] : 0;
            $childId = isset($calcRow['child_id']) ? (int)$calcRow['child_id'] : 0;
            $componentId = isset($calcRow['component_id']) ? (int)$calcRow['component_id'] : 0;
            if ($parentId <= 0 || $childId <= 0 || $componentId <= 0) {
                continue;
            }
            $relKey = $parentId . ':' . $childId;
            if (!isset($calcByRelation[$relKey])) {
                $calcByRelation[$relKey] = array();
            }
            $componentLabel = isset($catalogById[$componentId])
                ? sale_items_parent_label($catalogById[$componentId], $categoriesById)
                : ('Componente #' . $componentId);
            $calcByRelation[$relKey][] = array(
                'component_id' => $componentId,
                'component_label' => $componentLabel,
                'is_positive' => isset($calcRow['is_positive']) ? ((int)$calcRow['is_positive'] === 1 ? 1 : -1) : 1
            );
        }

        foreach ($relationRows as $relationRow) {
            $parentId = isset($relationRow['parent_id']) ? (int)$relationRow['parent_id'] : 0;
            $childId = isset($relationRow['child_id']) ? (int)$relationRow['child_id'] : 0;
            if ($parentId <= 0 || $childId <= 0) {
                continue;
            }
            if (!isset($catalogById[$parentId]) || !isset($catalogById[$childId])) {
                continue;
            }
            $relKey = $parentId . ':' . $childId;
            $percentValue = null;
            if (array_key_exists('percent_value', $relationRow) && $relationRow['percent_value'] !== null && $relationRow['percent_value'] !== '') {
                $percentValue = (float)$relationRow['percent_value'];
            }
            $showInFolioRelation = null;
            if (array_key_exists('show_in_folio_relation', $relationRow) && $relationRow['show_in_folio_relation'] !== null && $relationRow['show_in_folio_relation'] !== '') {
                $showInFolioRelation = (int)$relationRow['show_in_folio_relation'];
            }
            $conceptTreeEdges[] = array(
                'parent_id' => $parentId,
                'child_id' => $childId,
                'add_to_father_total' => isset($relationRow['add_to_father_total']) ? (int)$relationRow['add_to_father_total'] : 1,
                'percent_value' => $percentValue,
                'show_in_folio_relation' => $showInFolioRelation,
                'components' => isset($calcByRelation[$relKey]) ? $calcByRelation[$relKey] : array()
            );
        }
    } catch (Exception $e) {
        $conceptTreeEdges = array();
    }
}

$conceptTreeStats = array(
    'node_count' => count($conceptTreeNodes),
    'edge_count' => count($conceptTreeEdges)
);

$lineItems = array();
try {
    $pdo = pms_get_connection();
    $sql = 'SELECT li.id_line_item, rel.id_parent_sale_item, li.id_folio, f.id_reservation, li.service_date, '
        . 'li.item_type, li.method, li.reference, li.amount_cents, li.quantity, li.unit_price_cents, li.status, li.created_at, li.currency, '
        . 'lic.item_name, cat.category_name, p.code AS property_code, p.name AS property_name, '
        . 'lic.catalog_type, r.code AS reservation_code, f.folio_name, f.status AS folio_status, '
        . 'g.names AS guest_names, g.last_name AS guest_last_name, g.maiden_name AS guest_maiden_name, '
        . 'rm.code AS room_code, rm.name AS room_name '
        . 'FROM line_item li '
        . 'JOIN folio f ON f.id_folio = li.id_folio '
        . 'JOIN reservation r ON r.id_reservation = f.id_reservation '
        . 'JOIN property p ON p.id_property = r.id_property '
        . 'LEFT JOIN guest g ON g.id_guest = r.id_guest '
        . 'LEFT JOIN room rm ON rm.id_room = r.id_room '
        . 'LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = li.id_line_item_catalog '
        . 'LEFT JOIN (SELECT lcp.id_sale_item_catalog, MIN(lcp.id_parent_sale_item_catalog) AS id_parent_sale_item '
        . '           FROM line_item_catalog_parent lcp '
        . '           WHERE lcp.deleted_at IS NULL AND lcp.is_active = 1 '
        . '           GROUP BY lcp.id_sale_item_catalog) rel ON rel.id_sale_item_catalog = li.id_line_item_catalog '
        . 'LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = lic.id_category '
        . 'WHERE li.deleted_at IS NULL ';
    $params = array();
    if ($lineItemFilters['property'] !== '') {
        $sql .= 'AND p.code = ? ';
        $params[] = $lineItemFilters['property'];
    }
    if (!empty($lineItemFilters['reservation_id'])) {
        $sql .= 'AND r.id_reservation = ? ';
        $params[] = (int)$lineItemFilters['reservation_id'];
    }
    if ($lineItemFilters['item_type'] !== '') {
        $sql .= 'AND li.item_type = ? ';
        $params[] = $lineItemFilters['item_type'];
    }
    if ($lineItemFilters['catalog_type'] !== '') {
        if ($lineItemFilters['catalog_type'] === 'none') {
            $sql .= 'AND li.id_line_item_catalog IS NULL ';
        } else {
            $sql .= 'AND lic.catalog_type = ? ';
            $params[] = $lineItemFilters['catalog_type'];
        }
    }
    if ($lineItemFilters['folio_status'] !== '') {
        $sql .= 'AND f.status = ? ';
        $params[] = $lineItemFilters['folio_status'];
    }
    if ($lineItemFilters['status'] !== '') {
        $sql .= 'AND li.status = ? ';
        $params[] = $lineItemFilters['status'];
    }
    if ($lineItemFilters['currency'] !== '') {
        $sql .= 'AND UPPER(COALESCE(li.currency, \'\')) = ? ';
        $params[] = $lineItemFilters['currency'];
    }
    if ($lineItemFilters['derived_only']) {
        $sql .= 'AND li.item_type = \'sale_item\' AND EXISTS (
                    SELECT 1
                    FROM line_item_catalog_parent lcp
                    WHERE lcp.id_sale_item_catalog = li.id_line_item_catalog
                      AND lcp.deleted_at IS NULL
                      AND lcp.is_active = 1
                 ) ';
    }
    $dateColumn = $lineItemFilters['date_field'] === 'created_at'
        ? 'DATE(li.created_at)'
        : 'DATE(li.service_date)';
    if ($lineItemFilters['date_from'] !== '') {
        $sql .= 'AND ' . $dateColumn . ' >= ? ';
        $params[] = $lineItemFilters['date_from'];
    }
    if ($lineItemFilters['date_to'] !== '') {
        $sql .= 'AND ' . $dateColumn . ' <= ? ';
        $params[] = $lineItemFilters['date_to'];
    }
    if ($lineItemFilters['search'] !== '') {
        $sql .= 'AND (lic.item_name LIKE ? OR cat.category_name LIKE ? OR r.code LIKE ? OR f.folio_name LIKE ? OR g.names LIKE ? OR g.last_name LIKE ? OR rm.code LIKE ? OR rm.name LIKE ? OR li.item_type LIKE ? OR li.method LIKE ? OR li.reference LIKE ? OR li.description LIKE ?) ';
        $needle = '%' . $lineItemFilters['search'] . '%';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }
    $sql .= 'ORDER BY li.created_at DESC, li.id_line_item DESC LIMIT ' . (int)$lineItemFilters['limit'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $lineItems = array();
    $seenLineItem = array();
    foreach ($rows as $r) {
        $lid = isset($r['id_line_item']) ? (int)$r['id_line_item'] : 0;
        if ($lid > 0 && isset($seenLineItem[$lid])) {
            continue;
        }
        if ($lid > 0) {
            $seenLineItem[$lid] = 1;
        }
        $lineItems[] = $r;
    }
} catch (Exception $e) {
    $lineItems = array();
}
$lineItemReservationOptions = array();
try {
    $pdoReservation = pms_get_connection();
    $sqlReservationOptions = 'SELECT
            r.id_reservation,
            r.code AS reservation_code,
            g.names AS guest_names,
            g.last_name AS guest_last_name,
            g.maiden_name AS guest_maiden_name,
            rm.code AS room_code,
            rm.name AS room_name,
            p.code AS property_code
        FROM reservation r
        JOIN property p ON p.id_property = r.id_property
        LEFT JOIN guest g ON g.id_guest = r.id_guest
        LEFT JOIN room rm ON rm.id_room = r.id_room
        WHERE p.id_company = ?
          AND r.deleted_at IS NULL
          AND r.is_active = 1 ';
    $reservationOptionParams = array($companyId);
    if ($lineItemFilters['property'] !== '') {
        $sqlReservationOptions .= 'AND p.code = ? ';
        $reservationOptionParams[] = $lineItemFilters['property'];
    }
    $sqlReservationOptions .= 'ORDER BY COALESCE(r.updated_at, r.created_at) DESC, r.id_reservation DESC LIMIT 500';
    $stmtReservationOptions = $pdoReservation->prepare($sqlReservationOptions);
    $stmtReservationOptions->execute($reservationOptionParams);
    $lineItemReservationOptions = $stmtReservationOptions->fetchAll();
} catch (Exception $e) {
    $lineItemReservationOptions = array();
}
$lineItemFinalTotals = array();
$lineItemVisibleFinalTotals = array();
$lineItemParentById = array();
$lineItemDepthById = array();
$lineItemHasChildrenById = array();
$lineItemVisibleChildrenById = array();
$lineItemById = array();
$childrenByParent = array();
$addToFatherByLine = array();
$lineItemRenderRows = $lineItems;
if ($lineItems) {
    $catalogParentEdges = array();
    try {
        $pdo = pms_get_connection();
        $stmt = $pdo->query(
            'SELECT id_sale_item_catalog, id_parent_sale_item_catalog, add_to_father_total
             FROM line_item_catalog_parent
             WHERE deleted_at IS NULL
               AND is_active = 1'
        );
        $edgeRows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
        foreach ($edgeRows as $edge) {
            $childCatalogId = isset($edge['id_sale_item_catalog']) ? (int)$edge['id_sale_item_catalog'] : 0;
            $parentCatalogId = isset($edge['id_parent_sale_item_catalog']) ? (int)$edge['id_parent_sale_item_catalog'] : 0;
            if ($childCatalogId <= 0 || $parentCatalogId <= 0) {
                continue;
            }
            if (!isset($catalogParentEdges[$childCatalogId])) {
                $catalogParentEdges[$childCatalogId] = array();
            }
            $catalogParentEdges[$childCatalogId][$parentCatalogId] = array(
                'add_to_father_total' => !empty($edge['add_to_father_total']) ? 1 : 0
            );
        }
    } catch (Exception $e) {
        $catalogParentEdges = array();
    }

    $viewLineItemIds = array();
    $viewBucketSet = array();
    $viewFolioIds = array();
    foreach ($lineItems as $row) {
        $lineId = isset($row['id_line_item']) ? (int)$row['id_line_item'] : 0;
        if ($lineId > 0) {
            $viewLineItemIds[$lineId] = 1;
        }
        $folioId = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
        if ($folioId > 0) {
            $viewFolioIds[$folioId] = 1;
        }
        $serviceKey = isset($row['service_date']) && $row['service_date'] !== '' ? (string)$row['service_date'] : '';
        if ($serviceKey === '' && !empty($row['created_at'])) {
            $serviceKey = substr((string)$row['created_at'], 0, 10);
        }
        if ($folioId > 0) {
            $viewBucketSet[$folioId . '|' . $serviceKey] = 1;
        }
    }

    $calcRows = $lineItems;
    if (!empty($viewFolioIds)) {
        try {
            $pdo = pms_get_connection();
            $folioIds = array_values(array_map('intval', array_keys($viewFolioIds)));
            $placeholders = implode(',', array_fill(0, count($folioIds), '?'));
            $sqlCalc = 'SELECT li.id_line_item, li.id_folio, li.id_line_item_catalog, li.item_type, li.description, '
                . 'li.service_date, li.created_at, li.quantity, li.unit_price_cents, li.amount_cents, li.discount_amount_cents, '
                . 'li.currency, li.status, lic.item_name '
                . 'FROM line_item li '
                . 'LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = li.id_line_item_catalog '
                . 'WHERE li.deleted_at IS NULL '
                . '  AND li.is_active = 1 '
                . '  AND li.item_type IN (\'sale_item\',\'tax_item\',\'payment\',\'obligation\',\'income\') '
                . '  AND li.id_folio IN (' . $placeholders . ')';
            $stmtCalc = $pdo->prepare($sqlCalc);
            $stmtCalc->execute($folioIds);
            $extraRows = $stmtCalc->fetchAll(PDO::FETCH_ASSOC);
            foreach ($extraRows as $rr) {
                $lid = isset($rr['id_line_item']) ? (int)$rr['id_line_item'] : 0;
                if ($lid <= 0 || isset($viewLineItemIds[$lid])) {
                    continue;
                }
                $fId = isset($rr['id_folio']) ? (int)$rr['id_folio'] : 0;
                $svc = isset($rr['service_date']) && $rr['service_date'] !== '' ? (string)$rr['service_date'] : '';
                if ($svc === '' && !empty($rr['created_at'])) {
                    $svc = substr((string)$rr['created_at'], 0, 10);
                }
                if (!isset($viewBucketSet[$fId . '|' . $svc])) {
                    continue;
                }
                $calcRows[] = $rr;
            }
        } catch (Exception $e) {
            $calcRows = $lineItems;
        }
    }

    $lineItemById = array();
    $lineItemsByFolioService = array();
    foreach ($calcRows as $idx => $row) {
        $lineId = isset($row['id_line_item']) ? (int)$row['id_line_item'] : 0;
        if ($lineId <= 0) {
            continue;
        }
        $folioId = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
        $serviceKey = isset($row['service_date']) && $row['service_date'] !== '' ? (string)$row['service_date'] : '';
        if ($serviceKey === '' && !empty($row['created_at'])) {
            $serviceKey = substr((string)$row['created_at'], 0, 10);
        }
        $bucket = $folioId . '|' . $serviceKey;
        if (!isset($lineItemsByFolioService[$bucket])) {
            $lineItemsByFolioService[$bucket] = array();
        }
        $lineItemsByFolioService[$bucket][] = $lineId;
        $lineItemById[$lineId] = $row + array('_idx' => (isset($viewLineItemIds[$lineId]) ? $idx : null));
        $lineItemParentById[$lineId] = 0;
    }

    foreach ($lineItemById as $lineId => $row) {
        $childCatalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($childCatalogId <= 0) {
            continue;
        }

        $parentCatalogIds = isset($catalogParentEdges[$childCatalogId]) ? array_keys($catalogParentEdges[$childCatalogId]) : array();
        $folioId = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
        $serviceKey = isset($row['service_date']) && $row['service_date'] !== '' ? (string)$row['service_date'] : '';
        if ($serviceKey === '' && !empty($row['created_at'])) {
            $serviceKey = substr((string)$row['created_at'], 0, 10);
        }
        $bucket = $folioId . '|' . $serviceKey;
        $candidateIds = isset($lineItemsByFolioService[$bucket]) ? $lineItemsByFolioService[$bucket] : array();
        $childName = trim((string)($row['item_name'] ?? ''));
        $desc = trim((string)($row['description'] ?? ''));
        $descParentName = '';
        if ($desc !== '' && strpos($desc, ' / ') !== false) {
            $parts = explode(' / ', $desc, 2);
            if (count($parts) === 2) {
                $descParentName = trim((string)$parts[1]);
            }
        }

        $bestParentId = 0;
        $bestScore = -1;
        if ($desc !== '' && preg_match('/^\[AUTO-DERIVED parent_line_item=(\d+)\]$/', $desc, $mAuto)) {
            $explicitParentId = (int)$mAuto[1];
            if ($explicitParentId > 0 && isset($lineItemById[$explicitParentId])) {
                $bestParentId = $explicitParentId;
                $bestScore = 1000000;
            }
        }
        foreach ($candidateIds as $candidateId) {
            $candidateId = (int)$candidateId;
            if ($candidateId <= 0 || $candidateId === $lineId || !isset($lineItemById[$candidateId])) {
                continue;
            }
            $parentRow = $lineItemById[$candidateId];
            $parentCatalogId = isset($parentRow['id_line_item_catalog']) ? (int)$parentRow['id_line_item_catalog'] : 0;
            if ($parentCatalogId <= 0) {
                continue;
            }
            $parentName = trim((string)($parentRow['item_name'] ?? ''));
            $hasCatalogEdge = !empty($parentCatalogIds) && in_array($parentCatalogId, $parentCatalogIds, true);
            $score = 0;
            if ($hasCatalogEdge) {
                $score += 50;
            }
            if ($descParentName !== '' && strcasecmp($descParentName, $parentName) === 0) {
                $score += 120;
            }
            if ($desc !== '' && $childName !== '' && $parentName !== '' && strcasecmp($desc, $childName . ' / ' . $parentName) === 0) {
                $score += 100;
            }
            if ($candidateId < $lineId) {
                $score += 10;
            }
            if ($candidateId > $bestParentId) {
                $score += 2;
            }
            if (!$hasCatalogEdge && $descParentName === '') {
                continue;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestParentId = $candidateId;
            }
        }

        if ($bestParentId > 0 && isset($lineItemById[$bestParentId])) {
            $parentCatalogId = isset($lineItemById[$bestParentId]['id_line_item_catalog']) ? (int)$lineItemById[$bestParentId]['id_line_item_catalog'] : 0;
            $lineItemParentById[$lineId] = $bestParentId;
            if (isset($row['_idx']) && $row['_idx'] !== null && isset($lineItems[$row['_idx']])) {
                $lineItems[$row['_idx']]['id_parent_sale_item'] = $bestParentId;
                $lineItems[$row['_idx']]['add_to_father_total'] = isset($catalogParentEdges[$childCatalogId][$parentCatalogId]['add_to_father_total'])
                    ? (int)$catalogParentEdges[$childCatalogId][$parentCatalogId]['add_to_father_total']
                    : 0;
            }
        }
    }

    $childrenByParent = array();
    $addToFatherByLine = array();
    foreach ($lineItemById as $lineId => $row) {
        if ($lineId <= 0) {
            continue;
        }
        $parentId = isset($lineItemParentById[$lineId]) ? (int)$lineItemParentById[$lineId] : 0;
        $lineItemParentById[$lineId] = $parentId;
        $addToFather = 0;
        if ($parentId > 0 && isset($lineItemById[$parentId])) {
            $childCatalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
            $parentCatalogId = isset($lineItemById[$parentId]['id_line_item_catalog']) ? (int)$lineItemById[$parentId]['id_line_item_catalog'] : 0;
            if ($childCatalogId > 0 && $parentCatalogId > 0 && isset($catalogParentEdges[$childCatalogId][$parentCatalogId])) {
                $addToFather = !empty($catalogParentEdges[$childCatalogId][$parentCatalogId]['add_to_father_total']) ? 1 : 0;
            } elseif (isset($row['add_to_father_total'])) {
                $addToFather = !empty($row['add_to_father_total']) ? 1 : 0;
            }
        }
        $addToFatherByLine[$lineId] = $addToFather;
        if ($parentId > 0) {
            if (!isset($childrenByParent[$parentId])) {
                $childrenByParent[$parentId] = array();
            }
            $childrenByParent[$parentId][] = $lineId;
            $lineItemHasChildrenById[$parentId] = true;
        }
    }

    $memoTotals = array();
    $computeFinal = function ($lineId, $path) use (&$computeFinal, &$memoTotals, $lineItemById, $childrenByParent, $addToFatherByLine) {
        $lineId = (int)$lineId;
        if ($lineId <= 0 || !isset($lineItemById[$lineId])) {
            return 0;
        }
        if (isset($memoTotals[$lineId])) {
            return (int)$memoTotals[$lineId];
        }
        if (isset($path[$lineId])) {
            return (int)($lineItemById[$lineId]['amount_cents'] ?? 0);
        }
        $path[$lineId] = 1;
        $total = (int)($lineItemById[$lineId]['amount_cents'] ?? 0);
        $children = isset($childrenByParent[$lineId]) ? $childrenByParent[$lineId] : array();
        foreach ($children as $childId) {
            $childId = (int)$childId;
            if ($childId <= 0) {
                continue;
            }
            if (!empty($addToFatherByLine[$childId])) {
                $total += (int)$computeFinal($childId, $path);
            }
        }
        $memoTotals[$lineId] = (int)$total;
        return (int)$total;
    };

    foreach ($lineItemById as $lineId => $row) {
        $lineItemFinalTotals[$lineId] = (int)$computeFinal($lineId, array());
    }

    if (!empty($lineItemFilters['hierarchy_mode'])) {
        $viewIdSet = $viewLineItemIds;
        foreach ($childrenByParent as $parentId => $children) {
            usort($children, function ($a, $b) use ($lineItemById) {
                $ra = isset($lineItemById[$a]) ? $lineItemById[$a] : array();
                $rb = isset($lineItemById[$b]) ? $lineItemById[$b] : array();
                $ca = isset($ra['created_at']) ? (string)$ra['created_at'] : '';
                $cb = isset($rb['created_at']) ? (string)$rb['created_at'] : '';
                if ($ca !== $cb) {
                    return strcmp($cb, $ca);
                }
                return (int)$b - (int)$a;
            });
            $childrenByParent[$parentId] = $children;
        }

        $roots = array();
        foreach ($lineItemById as $lineId => $row) {
            if (!isset($viewIdSet[$lineId])) {
                continue;
            }
            $parentId = isset($lineItemParentById[$lineId]) ? (int)$lineItemParentById[$lineId] : 0;
            if ($parentId <= 0 || !isset($lineItemById[$parentId]) || !isset($viewIdSet[$parentId])) {
                $roots[] = $lineId;
            }
        }
        usort($roots, function ($a, $b) use ($lineItemById) {
            $ra = isset($lineItemById[$a]) ? $lineItemById[$a] : array();
            $rb = isset($lineItemById[$b]) ? $lineItemById[$b] : array();
            $ca = isset($ra['created_at']) ? (string)$ra['created_at'] : '';
            $cb = isset($rb['created_at']) ? (string)$rb['created_at'] : '';
            if ($ca !== $cb) {
                return strcmp($cb, $ca);
            }
            return (int)$b - (int)$a;
        });

        $flattenedIds = array();
        $flattenTree = function ($lineId, $depth, $path) use (&$flattenTree, &$flattenedIds, &$lineItemDepthById, $childrenByParent, $viewIdSet) {
            $lineId = (int)$lineId;
            if ($lineId <= 0 || isset($path[$lineId]) || !isset($viewIdSet[$lineId])) {
                return;
            }
            $path[$lineId] = 1;
            $lineItemDepthById[$lineId] = (int)$depth;
            $flattenedIds[] = $lineId;
            $children = isset($childrenByParent[$lineId]) ? $childrenByParent[$lineId] : array();
            foreach ($children as $childId) {
                $flattenTree((int)$childId, $depth + 1, $path);
            }
        };

        foreach ($roots as $rootId) {
            $flattenTree((int)$rootId, 0, array());
        }
        foreach ($viewIdSet as $lineId => $one) {
            $lineId = (int)$lineId;
            if (!isset($lineItemDepthById[$lineId])) {
                $flattenTree((int)$lineId, 0, array());
            }
        }

        $lineItemRenderRows = array();
        foreach ($flattenedIds as $lineId) {
            if (!isset($lineItemById[$lineId])) {
                continue;
            }
            $row = $lineItemById[$lineId];
            unset($row['_idx']);
            $lineItemRenderRows[] = $row;
        }
    }

    $visibleLineIdSet = array();
    foreach ($lineItemRenderRows as $rr) {
        $lid = isset($rr['id_line_item']) ? (int)$rr['id_line_item'] : 0;
        if ($lid > 0) {
            $visibleLineIdSet[$lid] = 1;
        }
    }
    foreach ($visibleLineIdSet as $lineId => $one) {
        $parentId = isset($lineItemParentById[$lineId]) ? (int)$lineItemParentById[$lineId] : 0;
        if ($parentId > 0 && isset($visibleLineIdSet[$parentId])) {
            if (!isset($lineItemVisibleChildrenById[$parentId])) {
                $lineItemVisibleChildrenById[$parentId] = array();
            }
            $lineItemVisibleChildrenById[$parentId][] = (int)$lineId;
            $lineItemHasChildrenById[$parentId] = true;
        }
    }

    $visibleTotalsMemo = array();
    $computeVisibleFinal = function ($lineId, $path) use (&$computeVisibleFinal, &$visibleTotalsMemo, $lineItemById, $lineItemVisibleChildrenById, $addToFatherByLine) {
        $lineId = (int)$lineId;
        if ($lineId <= 0 || !isset($lineItemById[$lineId])) {
            return 0;
        }
        if (isset($visibleTotalsMemo[$lineId])) {
            return (int)$visibleTotalsMemo[$lineId];
        }
        if (isset($path[$lineId])) {
            return (int)($lineItemById[$lineId]['amount_cents'] ?? 0);
        }
        $path[$lineId] = 1;
        $total = (int)($lineItemById[$lineId]['amount_cents'] ?? 0);
        $children = isset($lineItemVisibleChildrenById[$lineId]) ? $lineItemVisibleChildrenById[$lineId] : array();
        foreach ($children as $childId) {
            $childId = (int)$childId;
            if ($childId <= 0) {
                continue;
            }
            if (!empty($addToFatherByLine[$childId])) {
                $total += (int)$computeVisibleFinal($childId, $path);
            }
        }
        $visibleTotalsMemo[$lineId] = (int)$total;
        return (int)$total;
    };
    foreach ($visibleLineIdSet as $lineId => $one) {
        $lineId = (int)$lineId;
        if ($lineId > 0) {
            $lineItemVisibleFinalTotals[$lineId] = (int)$computeVisibleFinal($lineId, array());
        }
    }
}
$lineItemViewDetail = null;
$lineItemViewParents = array();
$lineItemViewChildren = array();
if (!empty($lineItemFilters['view_line_item_id'])) {
    try {
        $pdo = pms_get_connection();
        $detailSql = 'SELECT li.id_line_item, li.id_folio, li.id_user, li.id_line_item_catalog, li.item_type, li.method, li.reference, '
            . 'li.description, li.service_date, li.quantity, li.unit_price_cents, li.amount_cents, li.discount_amount_cents, '
            . 'li.currency, li.status, li.created_at, li.updated_at, '
            . 'lic.item_name, lic.catalog_type, cat.category_name, '
            . 'f.folio_name, f.status AS folio_status, f.id_reservation, '
            . 'r.code AS reservation_code, p.code AS property_code, p.name AS property_name '
            . 'FROM line_item li '
            . 'JOIN folio f ON f.id_folio = li.id_folio '
            . 'JOIN reservation r ON r.id_reservation = f.id_reservation '
            . 'JOIN property p ON p.id_property = r.id_property '
            . 'LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = li.id_line_item_catalog '
            . 'LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = lic.id_category '
            . 'WHERE li.id_line_item = ? AND li.deleted_at IS NULL '
            . 'LIMIT 1';
        $stmt = $pdo->prepare($detailSql);
        $stmt->execute(array((int)$lineItemFilters['view_line_item_id']));
        $lineItemViewDetail = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($lineItemViewDetail) {
            $detailId = isset($lineItemViewDetail['id_line_item']) ? (int)$lineItemViewDetail['id_line_item'] : 0;
            $resolvedParentId = ($detailId > 0 && isset($lineItemParentById[$detailId])) ? (int)$lineItemParentById[$detailId] : 0;
            if ($resolvedParentId > 0 && isset($lineItemById[$resolvedParentId])) {
                $prow = $lineItemById[$resolvedParentId];
                $lineItemViewParents[] = array(
                    'id_line_item' => (int)$resolvedParentId,
                    'item_type' => (string)($prow['item_type'] ?? ''),
                    'service_date' => (string)($prow['service_date'] ?? ''),
                    'quantity' => (string)($prow['quantity'] ?? '1'),
                    'amount_cents' => (int)($prow['amount_cents'] ?? 0),
                    'currency' => (string)($prow['currency'] ?? 'MXN'),
                    'id_line_item_catalog' => (int)($prow['id_line_item_catalog'] ?? 0),
                    'description' => (string)($prow['description'] ?? ''),
                    'status' => (string)($prow['status'] ?? ''),
                    'item_name' => (string)($prow['item_name'] ?? ''),
                    'catalog_type' => (string)($prow['catalog_type'] ?? '')
                );
            }
            $childIds = ($detailId > 0 && !empty($childrenByParent[$detailId])) ? $childrenByParent[$detailId] : array();
            foreach ($childIds as $childId) {
                $childId = (int)$childId;
                if ($childId <= 0 || !isset($lineItemById[$childId])) {
                    continue;
                }
                $crow = $lineItemById[$childId];
                $lineItemViewChildren[] = array(
                    'id_line_item' => (int)$childId,
                    'item_type' => (string)($crow['item_type'] ?? ''),
                    'service_date' => (string)($crow['service_date'] ?? ''),
                    'quantity' => (string)($crow['quantity'] ?? '1'),
                    'amount_cents' => (int)($crow['amount_cents'] ?? 0),
                    'currency' => (string)($crow['currency'] ?? 'MXN'),
                    'id_line_item_catalog' => (int)($crow['id_line_item_catalog'] ?? 0),
                    'description' => (string)($crow['description'] ?? ''),
                    'status' => (string)($crow['status'] ?? ''),
                    'item_name' => (string)($crow['item_name'] ?? ''),
                    'catalog_type' => (string)($crow['catalog_type'] ?? ''),
                    'add_to_father_total' => isset($addToFatherByLine[$childId]) ? (int)$addToFatherByLine[$childId] : 0
                );
            }
            usort($lineItemViewChildren, function ($a, $b) {
                return (int)($b['id_line_item'] ?? 0) - (int)($a['id_line_item'] ?? 0);
            });
        }
    } catch (Exception $e) {
        $lineItemViewDetail = null;
    }
}
$obligationBySaleItem = array();
$incomeBySaleItem = array();
$paymentCatalogsByProperty = array();
$paymentByParent = array();
/*
  Legacy obligation/income/payment catalog wiring was removed from the schema.
  Keep these maps empty to avoid loading obsolete columns and tables.
*/

$paymentParentOptions = array();
foreach ($paymentCatalogsByProperty as $propCode => $items) {
    foreach ($items as $item) {
        $pid = isset($item['id']) ? (int)$item['id'] : 0;
        if ($pid <= 0) {
            continue;
        }
        $label = isset($item['label']) ? (string)$item['label'] : '';
        if ($propCode !== '*' && $propCode !== '') {
            $label = $propCode . ' - ' . $label;
        }
        $paymentParentOptions[] = array(
            'id_sale_item_catalog' => $pid,
            'catalog_type' => 'payment',
            'name' => $label
        );
    }
}
if ($paymentParentOptions) {
    $parentConceptOptions = array_merge($parentConceptOptions, $paymentParentOptions);
}

/* helpers */
function sale_items_payment_parent_label($parent, $parentId) {
    $name = '';
    if (isset($parent['name']) && trim((string)$parent['name']) !== '') {
        $name = trim((string)$parent['name']);
    } elseif (isset($parent['item_name']) && trim((string)$parent['item_name']) !== '') {
        $name = trim((string)$parent['item_name']);
    } elseif (isset($parent['label']) && trim((string)$parent['label']) !== '') {
        $name = trim((string)$parent['label']);
    }
    if ($name === '') {
        $name = 'Pago ' . (int)$parentId;
    }
    return $name;
}

function sale_items_catalog_type_meta($catalogTypeRaw) {
    $catalogType = strtolower(trim((string)$catalogTypeRaw));
    if ($catalogType === 'payment' || $catalogType === 'pago') {
        return array('Pago', 'type-payment');
    }
    if ($catalogType === 'tax_rule' || $catalogType === 'tax_item' || $catalogType === 'tax' || $catalogType === 'impuesto' || $catalogType === 'impuestos') {
        return array('Impuesto', 'type-tax');
    }
    if ($catalogType === 'obligation' || $catalogType === 'obligacion' || $catalogType === 'obligación') {
        return array('Obligación', 'type-obligation');
    }
    if ($catalogType === 'income' || $catalogType === 'ingreso') {
        return array('Ingreso', 'type-income');
    }
    return array('Concepto', 'type-concept');
}

function render_parent_checkboxes($options, $selected, $categoriesById, $excludeId = 0, $inputName = 'item_parent_ids[]') {
    $selectedMap = array_flip(array_map('strval', $selected));
    $hasOptions = false;
    echo '<div class="checklist-grid">';
    foreach ($options as $parent) {
        $parentId = isset($parent['id_sale_item_catalog']) ? (int)$parent['id_sale_item_catalog'] : 0;
        if ($parentId <= 0 || $parentId === (int)$excludeId) {
            continue;
        }
        $hasOptions = true;
        $catalogType = isset($parent['catalog_type']) ? (string)$parent['catalog_type'] : 'sale_item';
        $label = '';
        list($typeLabel, $typeClass) = sale_items_catalog_type_meta($catalogType);
        if (strtolower(trim($catalogType)) === 'payment') {
            $label = sale_items_payment_parent_label($parent, $parentId);
        } else {
            $label = sale_items_parent_label($parent, $categoriesById);
        }
        $checked = isset($selectedMap[(string)$parentId]) ? 'checked' : '';
        echo '<label class="checklist-item">';
        echo '<input type="checkbox" name="' . htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') . '" value="' . $parentId . '" ' . $checked . '>';
        echo '<span class="type-pill ' . $typeClass . '">' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="type-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</label>';
    }
    if (!$hasOptions) {
        echo '<div class="checklist-empty muted">Sin conceptos disponibles.</div>';
    }
    echo '</div>';
}
function sale_items_parent_label($concept, $categoriesById) {
    $itemName = isset($concept['item_name']) ? (string)$concept['item_name'] : '';
    $catId = isset($concept['id_category']) ? (int)$concept['id_category'] : 0;
    $catName = isset($concept['category']) ? (string)$concept['category'] : '';
    $propCode = isset($concept['property_code']) ? (string)$concept['property_code'] : '';
    $parentName = '';
    if ($catId > 0 && isset($categoriesById[$catId])) {
        $parentId = isset($categoriesById[$catId]['id_parent_sale_item_category']) ? (int)$categoriesById[$catId]['id_parent_sale_item_category'] : 0;
        if ($parentId > 0 && isset($categoriesById[$parentId])) {
            $parentName = (string)$categoriesById[$parentId]['category_name'];
        }
    }
    $prefix = trim($parentName . ($parentName !== '' && $catName !== '' ? ' / ' : '') . $catName);
    $propLabel = $propCode !== '' ? $propCode : 'Todas';
    $suffixParts = array();
    if ($prefix !== '') {
        $suffixParts[] = $prefix;
    }
    if ($propLabel !== '') {
        $suffixParts[] = $propLabel;
    }
    $suffix = $suffixParts ? (' (' . implode(' - ', $suffixParts) . ')') : '';
    return $itemName . $suffix;
}

function sale_items_payment_items_for_property($paymentMap, $propertyCode)
{
    $prop = strtoupper((string)$propertyCode);
    $items = array();
    if (isset($paymentMap['*'])) {
        $items = array_merge($items, $paymentMap['*']);
    }
    if ($prop !== '' && isset($paymentMap[$prop])) {
        $items = array_merge($items, $paymentMap[$prop]);
    }
    if ($items) {
        usort($items, function($a, $b){
            return strcmp((string)$a['label'], (string)$b['label']);
        });
    }
    return $items;
}
function sale_items_parse_ids($raw) {
    $values = is_array($raw) ? $raw : explode(',', (string)$raw);
    $out = array();
    foreach ($values as $value) {
        $id = (int)trim((string)$value);
        if ($id > 0) {
            $out[$id] = true;
        }
    }
    return array_keys($out);
}
function sale_items_format_money($cents, $currency = 'MXN') {
    $amount = ((int)$cents) / 100;
    return number_format($amount, 2) . ' ' . $currency;
}
function sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters) {
    echo '<input type="hidden" name="sale_items_filter_property" value="' . htmlspecialchars((string)$filters['property_code'], ENT_QUOTES, 'UTF-8') . '">';
    if (!empty($filters['show_inactive'])) {
        echo '<input type="hidden" name="sale_items_filter_inactive" value="1">';
    }
    echo '<input type="hidden" name="sale_items_filter_search" value="' . htmlspecialchars((string)$conceptFilters['search'], ENT_QUOTES, 'UTF-8') . '">';
    if (!empty($conceptFilters['derived_only'])) {
        echo '<input type="hidden" name="sale_items_filter_derived" value="1">';
    }

    echo '<input type="hidden" name="sale_items_line_property" value="' . htmlspecialchars((string)$lineItemFilters['property'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_reservation_id" value="' . (int)$lineItemFilters['reservation_id'] . '">';
    echo '<input type="hidden" name="sale_items_line_from" value="' . htmlspecialchars((string)$lineItemFilters['date_from'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_to" value="' . htmlspecialchars((string)$lineItemFilters['date_to'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_date_field" value="' . htmlspecialchars((string)$lineItemFilters['date_field'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_item_type" value="' . htmlspecialchars((string)$lineItemFilters['item_type'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_catalog_type" value="' . htmlspecialchars((string)$lineItemFilters['catalog_type'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_folio_status" value="' . htmlspecialchars((string)$lineItemFilters['folio_status'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_status" value="' . htmlspecialchars((string)$lineItemFilters['status'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_currency" value="' . htmlspecialchars((string)$lineItemFilters['currency'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_line_limit" value="' . (int)$lineItemFilters['limit'] . '">';
    echo '<input type="hidden" name="sale_items_line_search" value="' . htmlspecialchars((string)$lineItemFilters['search'], ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="sale_items_view_line_item_id" value="' . (int)$lineItemFilters['view_line_item_id'] . '">';
    if (!empty($lineItemFilters['derived_only'])) {
        echo '<input type="hidden" name="sale_items_line_derived" value="1">';
    }
    if (!empty($lineItemFilters['hierarchy_mode'])) {
        echo '<input type="hidden" name="sale_items_line_hierarchy" value="1">';
    }
}
function sale_items_build_derived_parent_map($catalogList, $categoriesById) {
    $map = array();
    foreach ($catalogList as $c) {
        $childId = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
        if ($childId <= 0) {
            continue;
        }
        $parents = sale_items_parse_ids(isset($c['parent_item_ids']) ? $c['parent_item_ids'] : '');
        if (!$parents) {
            continue;
        }
        $label = sale_items_parent_label($c, $categoriesById);
        foreach ($parents as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) {
                continue;
            }
            if (!isset($map[$pid])) {
                $map[$pid] = array();
            }
            $map[$pid][$childId] = array(
                'id' => $childId,
                'label' => $label
            );
        }
    }
    foreach ($map as $pid => $items) {
        $items = array_values($items);
        usort($items, function($a, $b){
            return strcmp($a['label'], $b['label']);
        });
        $map[$pid] = $items;
    }
    return $map;
}

function sale_items_collect_catalog_tree_nodes($startId, $parentToChildren, $childToParents) {
    $startId = (int)$startId;
    if ($startId <= 0) {
        return array();
    }
    $visited = array();
    $stack = array($startId);
    while ($stack) {
        $nodeId = (int)array_pop($stack);
        if ($nodeId <= 0 || isset($visited[$nodeId])) {
            continue;
        }
        $visited[$nodeId] = 1;
        if (isset($parentToChildren[$nodeId]) && is_array($parentToChildren[$nodeId])) {
            foreach ($parentToChildren[$nodeId] as $childId => $one) {
                $childId = (int)$childId;
                if ($childId > 0 && !isset($visited[$childId])) {
                    $stack[] = $childId;
                }
            }
        }
        if (isset($childToParents[$nodeId]) && is_array($childToParents[$nodeId])) {
            foreach ($childToParents[$nodeId] as $parentId => $one) {
                $parentId = (int)$parentId;
                if ($parentId > 0 && !isset($visited[$parentId])) {
                    $stack[] = $parentId;
                }
            }
        }
    }
    return $visited;
}

function sale_items_build_calc_parent_map_all($catalogList, $categoriesById, $obligationBySaleItem, $incomeBySaleItem) {
    $directMap = array();
    $parentToChildren = array();
    $childToParents = array();
    foreach ($catalogList as $c) {
        $childId = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
        if ($childId <= 0) {
            continue;
        }
        $parents = sale_items_parse_ids(isset($c['parent_item_ids']) ? $c['parent_item_ids'] : '');
        if (!$parents) {
            continue;
        }
        $label = 'Concepto: ' . sale_items_parent_label($c, $categoriesById);
        foreach ($parents as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) {
                continue;
            }
            if (!isset($directMap[$pid])) {
                $directMap[$pid] = array();
            }
            if (!isset($parentToChildren[$pid])) {
                $parentToChildren[$pid] = array();
            }
            if (!isset($childToParents[$childId])) {
                $childToParents[$childId] = array();
            }
            $parentToChildren[$pid][$childId] = 1;
            $childToParents[$childId][$pid] = 1;
            $directMap[$pid][$childId] = array(
                'id' => $childId,
                'label' => $label
            );
        }
    }
    foreach ($obligationBySaleItem as $parentId => $items) {
        $parentId = (int)$parentId;
        if ($parentId <= 0) {
            continue;
        }
        if (!isset($directMap[$parentId])) {
            $directMap[$parentId] = array();
        }
        foreach ($items as $item) {
            $cid = isset($item['id']) ? (int)$item['id'] : 0;
            if ($cid <= 0) {
                continue;
            }
            $label = isset($item['label']) ? (string)$item['label'] : ('Obligacion ' . $cid);
            if (!isset($parentToChildren[$parentId])) {
                $parentToChildren[$parentId] = array();
            }
            if (!isset($childToParents[$cid])) {
                $childToParents[$cid] = array();
            }
            $parentToChildren[$parentId][$cid] = 1;
            $childToParents[$cid][$parentId] = 1;
            $directMap[$parentId][$cid] = array(
                'id' => $cid,
                'label' => 'Obligaci&oacute;n: ' . $label
            );
        }
    }
    foreach ($incomeBySaleItem as $parentId => $items) {
        $parentId = (int)$parentId;
        if ($parentId <= 0) {
            continue;
        }
        if (!isset($directMap[$parentId])) {
            $directMap[$parentId] = array();
        }
        foreach ($items as $item) {
            $cid = isset($item['id']) ? (int)$item['id'] : 0;
            if ($cid <= 0) {
                continue;
            }
            $label = isset($item['label']) ? (string)$item['label'] : ('Ingreso ' . $cid);
            if (!isset($parentToChildren[$parentId])) {
                $parentToChildren[$parentId] = array();
            }
            if (!isset($childToParents[$cid])) {
                $childToParents[$cid] = array();
            }
            $parentToChildren[$parentId][$cid] = 1;
            $childToParents[$cid][$parentId] = 1;
            $directMap[$parentId][$cid] = array(
                'id' => $cid,
                'label' => 'Ingreso: ' . $label
            );
        }
    }

    $allNodes = array();
    foreach ($parentToChildren as $nodeId => $children) {
        $nodeId = (int)$nodeId;
        if ($nodeId > 0) {
            $allNodes[$nodeId] = 1;
        }
    }
    foreach ($childToParents as $nodeId => $parents) {
        $nodeId = (int)$nodeId;
        if ($nodeId > 0) {
            $allNodes[$nodeId] = 1;
        }
    }
    foreach ($directMap as $nodeId => $items) {
        $nodeId = (int)$nodeId;
        if ($nodeId > 0) {
            $allNodes[$nodeId] = 1;
        }
    }

    $map = array();
    foreach ($allNodes as $nodeId => $one) {
        $nodeId = (int)$nodeId;
        if ($nodeId <= 0) {
            continue;
        }
        $treeNodes = sale_items_collect_catalog_tree_nodes($nodeId, $parentToChildren, $childToParents);
        if (!$treeNodes) {
            $treeNodes = array($nodeId => 1);
        }
        $items = array();
        foreach ($treeNodes as $treeNodeId => $v) {
            $treeNodeId = (int)$treeNodeId;
            if ($treeNodeId <= 0 || !isset($directMap[$treeNodeId])) {
                continue;
            }
            foreach ($directMap[$treeNodeId] as $childId => $childData) {
                $childId = (int)$childId;
                if ($childId <= 0) {
                    continue;
                }
                $items[$childId] = $childData;
            }
        }
        $items = array_values($items);
        usort($items, function($a, $b){
            return strcmp($a['label'], $b['label']);
        });
        $map[$nodeId] = $items;
    }
    return $map;
}
function sale_items_load_calc_map($itemId) {
    if ($itemId <= 0) {
        return array();
    }
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT id_parent_line_item_catalog, id_component_line_item_catalog, is_positive
         FROM line_item_catalog_calc
         WHERE id_line_item_catalog = ?
           AND deleted_at IS NULL
           AND is_active = 1'
    );
    $stmt->execute(array($itemId));
    $map = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $parentId = isset($row['id_parent_line_item_catalog']) ? (int)$row['id_parent_line_item_catalog'] : 0;
        $compId = isset($row['id_component_line_item_catalog']) ? (int)$row['id_component_line_item_catalog'] : 0;
        if ($parentId <= 0 || $compId <= 0) {
            continue;
        }
        if (!isset($map[$parentId])) {
            $map[$parentId] = array();
        }
        $map[$parentId][$compId] = !empty($row['is_positive']) ? 1 : -1;
    }
    return $map;
}
function sale_items_calc_selection_from_map($calcMap) {
    $selected = array();
    $signs = array();
    foreach ($calcMap as $parentId => $components) {
        $parentId = (int)$parentId;
        if ($parentId <= 0) {
            continue;
        }
        $selected[$parentId] = array();
        $signs[$parentId] = array();
        foreach ($components as $componentId => $sign) {
            $componentId = (int)$componentId;
            if ($componentId <= 0) {
                continue;
            }
            $selected[$parentId][] = $componentId;
            $signs[$parentId][$componentId] = $sign;
        }
    }
    return array(
        'selected' => $selected,
        'signs' => $signs
    );
}
function render_parent_picklist($options, $selected, $categoriesById, $excludeId = 0, $inputName = 'item_parent_ids[]', $idPrefix = 'parent-picklist') {
    $selectedMap = array_flip(array_map('strval', $selected));
    $selectedOptions = array();
    $availableOptions = array();
    foreach ($options as $parent) {
        $parentId = isset($parent['id_sale_item_catalog']) ? (int)$parent['id_sale_item_catalog'] : 0;
        if ($parentId <= 0 || $parentId === (int)$excludeId) {
            continue;
        }
        $catalogType = isset($parent['catalog_type']) ? (string)$parent['catalog_type'] : 'sale_item';
        list($typeLabel, $typeClassIgnore) = sale_items_catalog_type_meta($catalogType);
        if (strtolower(trim($catalogType)) === 'payment') {
            $label = sale_items_payment_parent_label($parent, $parentId);
        } else {
            $label = sale_items_parent_label($parent, $categoriesById);
        }
        $fullLabel = $typeLabel . ': ' . $label;
        $entry = array('id' => $parentId, 'label' => $fullLabel);
        if (isset($selectedMap[(string)$parentId])) {
            $selectedOptions[] = $entry;
        } else {
            $availableOptions[] = $entry;
        }
    }
    usort($selectedOptions, function($a, $b){ return strcmp($a['label'], $b['label']); });
    usort($availableOptions, function($a, $b){ return strcmp($a['label'], $b['label']); });
    $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$idPrefix);
    echo '<div class="parent-picklist" data-parent-picklist="1" data-input-name="' . htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') . '" data-picklist-id="' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="picklist-column">';
    echo '<div class="picklist-label">Seleccionados</div>';
    echo '<select multiple size="8" class="picklist-select" data-picklist-selected>';
    foreach ($selectedOptions as $opt) {
        echo '<option value="' . (int)$opt['id'] . '" data-label="' . htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="picklist-actions">';
    echo '<button type="button" class="button-secondary picklist-btn" data-picklist-move="remove">&gt;</button>';
    echo '<button type="button" class="button-secondary picklist-btn" data-picklist-move="add">&lt;</button>';
    echo '</div>';
    echo '<div class="picklist-column">';
    echo '<div class="picklist-label">Disponibles</div>';
    echo '<select multiple size="8" class="picklist-select" data-picklist-available>';
    foreach ($availableOptions as $opt) {
        echo '<option value="' . (int)$opt['id'] . '" data-label="' . htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="picklist-hidden" data-parent-selected></div>';
    echo '</div>';
}
function sale_items_save_calc_map($itemId, $parentId, $componentIds, $signs, $actorUserId) {
    $itemId = (int)$itemId;
    $parentId = (int)$parentId;
    if ($itemId <= 0 || $parentId <= 0) {
        return;
    }
    $componentIds = array_values(array_unique(array_map('intval', $componentIds)));
    $componentsOut = array();
    $signsOut = array();
    foreach ($componentIds as $componentId) {
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            continue;
        }
        $componentsOut[] = $componentId;
        $sign = isset($signs[$componentId]) ? (int)$signs[$componentId] : 1;
        $signsOut[] = ($sign >= 0 ? '1' : '-1');
    }
    $componentCsv = $componentsOut ? implode(',', $componentsOut) : '';
    $signCsv = $signsOut ? implode(',', $signsOut) : '';
    pms_call_procedure('sp_sale_item_catalog_calc_upsert', array(
        'replace',
        $itemId,
        $parentId,
        $componentCsv,
        $signCsv,
        $actorUserId
    ));
}
function sale_items_load_parent_total_map($itemId) {
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        return array();
    }
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT id_parent_sale_item_catalog, add_to_father_total
           FROM line_item_catalog_parent
          WHERE id_sale_item_catalog = ?
            AND deleted_at IS NULL
            AND is_active = 1'
    );
    $stmt->execute(array($itemId));
    $rows = $stmt->fetchAll();
    $out = array();
    foreach ($rows as $row) {
        $parentId = isset($row['id_parent_sale_item_catalog']) ? (int)$row['id_parent_sale_item_catalog'] : 0;
        if ($parentId <= 0) {
            continue;
        }
        $out[$parentId] = !empty($row['add_to_father_total']) ? 1 : 0;
    }
    return $out;
}
function sale_items_load_parent_percent_map($itemId) {
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        return array();
    }
    $pdo = pms_get_connection();
    $out = array();
    try {
        $stmt = $pdo->prepare(
            'SELECT id_parent_sale_item_catalog, percent_value
               FROM line_item_catalog_parent
              WHERE id_sale_item_catalog = ?
                AND deleted_at IS NULL
                AND is_active = 1'
        );
        $stmt->execute(array($itemId));
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $parentId = isset($row['id_parent_sale_item_catalog']) ? (int)$row['id_parent_sale_item_catalog'] : 0;
            if ($parentId <= 0) {
                continue;
            }
            $out[$parentId] = isset($row['percent_value']) && $row['percent_value'] !== null
                ? (float)$row['percent_value']
                : null;
        }
    } catch (Exception $e) {
        return array();
    }
    return $out;
}
function sale_items_load_parent_show_in_folio_map($itemId) {
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        return array();
    }
    $pdo = pms_get_connection();
    $out = array();
    try {
        $stmt = $pdo->prepare(
            'SELECT id_parent_sale_item_catalog, show_in_folio_relation
               FROM line_item_catalog_parent
              WHERE id_sale_item_catalog = ?
                AND deleted_at IS NULL
                AND is_active = 1'
        );
        $stmt->execute(array($itemId));
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $parentId = isset($row['id_parent_sale_item_catalog']) ? (int)$row['id_parent_sale_item_catalog'] : 0;
            if ($parentId <= 0) {
                continue;
            }
            if (!array_key_exists('show_in_folio_relation', $row) || $row['show_in_folio_relation'] === null) {
                continue;
            }
            $out[$parentId] = !empty($row['show_in_folio_relation']) ? 1 : 0;
        }
    } catch (Exception $e) {
        return array();
    }
    return $out;
}
function sale_items_save_parent_total_map($itemId, $parentTotalMap, $parentPercentMap, $parentShowMap, $defaultShowInFolio, $actorUserId) {
    $itemId = (int)$itemId;
    if ($itemId <= 0 || !is_array($parentTotalMap)) {
        return;
    }
    $defaultShow = !empty($defaultShowInFolio) ? 1 : 0;
    foreach ($parentTotalMap as $parentId => $flag) {
        $pid = (int)$parentId;
        if ($pid <= 0) {
            continue;
        }
        $percentValue = null;
        if (is_array($parentPercentMap) && array_key_exists($pid, $parentPercentMap)) {
            $rawPercent = $parentPercentMap[$pid];
            if ($rawPercent !== '' && $rawPercent !== null) {
                $percentValue = (float)$rawPercent;
            }
        }
        $showInFolioRelation = $defaultShow;
        if (is_array($parentShowMap) && array_key_exists($pid, $parentShowMap)) {
            $showInFolioRelation = !empty($parentShowMap[$pid]) ? 1 : 0;
        }
        pms_call_procedure('sp_sale_item_catalog_parent_total_upsert', array(
            'upsert',
            $itemId,
            $pid,
            !empty($flag) ? 1 : 0,
            $showInFolioRelation,
            $percentValue,
            $actorUserId
        ));
    }
}
function sale_items_build_calc_parent_tabs($options, $selectedParents, $categoriesById) {
    $tabs = array();
    if (!$selectedParents) {
        return $tabs;
    }
    $optionMap = array();
    foreach ($options as $opt) {
        $pid = isset($opt['id_sale_item_catalog']) ? (int)$opt['id_sale_item_catalog'] : 0;
        if ($pid > 0) {
            $optionMap[$pid] = $opt;
        }
    }
    foreach ($selectedParents as $pid) {
        $pid = (int)$pid;
        if ($pid <= 0 || !isset($optionMap[$pid])) {
            continue;
        }
        $parent = $optionMap[$pid];
        $catalogType = isset($parent['catalog_type']) ? (string)$parent['catalog_type'] : 'sale_item';
        list($typeLabel, $typeClassIgnore) = sale_items_catalog_type_meta($catalogType);
        if (strtolower(trim($catalogType)) === 'payment') {
            $label = sale_items_payment_parent_label($parent, $pid);
            $tabs[] = array('id' => $pid, 'label' => $typeLabel . ': ' . $label);
        } else {
            $tabs[] = array('id' => $pid, 'label' => $typeLabel . ': ' . sale_items_parent_label($parent, $categoriesById));
        }
    }
    return $tabs;
}
function sale_items_load_child_relations_for_parent($parentId, $companyId) {
    $parentId = (int)$parentId;
    $companyId = (int)$companyId;
    if ($parentId <= 0 || $companyId <= 0) {
        return array();
    }
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            lcp.id_parent_sale_item_catalog AS id_parent_sale_item_catalog,
            lcp.id_sale_item_catalog AS id_child_sale_item_catalog,
            COALESCE(lcp.add_to_father_total, 1) AS add_to_father_total,
            lcp.show_in_folio_relation,
            lcp.percent_value,
            child.item_name AS child_item_name,
            child.catalog_type AS child_catalog_type,
            child.show_in_folio AS child_default_show_in_folio,
            cat.category_name AS child_subcategory_name,
            parent_cat.category_name AS child_category_name,
            prop.code AS child_property_code
         FROM line_item_catalog_parent lcp
         JOIN line_item_catalog child
           ON child.id_line_item_catalog = lcp.id_sale_item_catalog
          AND child.deleted_at IS NULL
          AND child.is_active = 1
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = child.id_category
          AND cat.deleted_at IS NULL
          AND cat.id_company = ?
         LEFT JOIN sale_item_category parent_cat
           ON parent_cat.id_sale_item_category = cat.id_parent_sale_item_category
          AND parent_cat.deleted_at IS NULL
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE lcp.id_parent_sale_item_catalog = ?
           AND lcp.deleted_at IS NULL
           AND lcp.is_active = 1
         ORDER BY parent_cat.category_name, cat.category_name, child.item_name'
    );
    $stmt->execute(array($companyId, $parentId));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return array();
    }

    $calcStmt = $pdo->prepare(
        'SELECT id_component_line_item_catalog, is_positive
           FROM line_item_catalog_calc
          WHERE id_line_item_catalog = ?
            AND id_parent_line_item_catalog = ?
            AND deleted_at IS NULL
            AND is_active = 1'
    );

    foreach ($rows as &$row) {
        $childId = isset($row['id_child_sale_item_catalog']) ? (int)$row['id_child_sale_item_catalog'] : 0;
        $row['calc_components'] = array();
        $row['calc_signs'] = array();
        if ($childId <= 0) {
            continue;
        }
        $calcStmt->execute(array($childId, $parentId));
        while ($calcRow = $calcStmt->fetch(PDO::FETCH_ASSOC)) {
            $componentId = isset($calcRow['id_component_line_item_catalog']) ? (int)$calcRow['id_component_line_item_catalog'] : 0;
            if ($componentId <= 0) {
                continue;
            }
            $row['calc_components'][] = $componentId;
            $row['calc_signs'][$componentId] = !empty($calcRow['is_positive']) ? 1 : -1;
        }
    }
    unset($row);

    return $rows;
}
function render_calc_components_section($calcParentChildMap, $selectedByParent, $signsByParent, $parentTabs, $excludeId = 0, $activeParentId = 0, $relatedChildMap = array(), $currentItemId = 0, $parentTotalByParent = array(), $parentPercentByParent = array(), $parentShowInFolioByParent = array(), $defaultShowInFolio = 1) {
    $mapJson = htmlspecialchars(json_encode($calcParentChildMap), ENT_QUOTES, 'UTF-8');
    $selectedJson = htmlspecialchars(json_encode($selectedByParent), ENT_QUOTES, 'UTF-8');
    $signsJson = htmlspecialchars(json_encode($signsByParent), ENT_QUOTES, 'UTF-8');
    $tabsJson = htmlspecialchars(json_encode($parentTabs), ENT_QUOTES, 'UTF-8');
    $childJson = htmlspecialchars(json_encode($relatedChildMap), ENT_QUOTES, 'UTF-8');
    $parentTotalJson = htmlspecialchars(json_encode($parentTotalByParent), ENT_QUOTES, 'UTF-8');
    $parentPercentJson = htmlspecialchars(json_encode($parentPercentByParent), ENT_QUOTES, 'UTF-8');
    $parentShowJson = htmlspecialchars(json_encode($parentShowInFolioByParent), ENT_QUOTES, 'UTF-8');
    $defaultShowInFolio = !empty($defaultShowInFolio) ? 1 : 0;
    $activeParentId = (int)$activeParentId;
    $currentItemId = (int)$currentItemId;
    if ($activeParentId <= 0 && !empty($parentTabs)) {
        $first = reset($parentTabs);
        $activeParentId = isset($first['id']) ? (int)$first['id'] : 0;
    }
    echo '<div class="calc-panel full" data-calc-panel="1" data-calc-map="' . $mapJson . '" data-calc-selected="' . $selectedJson . '" data-calc-signs="' . $signsJson . '" data-calc-tabs="' . $tabsJson . '" data-calc-exclude="' . (int)$excludeId . '" data-calc-children="' . $childJson . '" data-calc-current="' . $currentItemId . '" data-parent-total="' . $parentTotalJson . '" data-parent-percent="' . $parentPercentJson . '" data-parent-show="' . $parentShowJson . '" data-default-show="' . $defaultShowInFolio . '">';
    echo '<input type="hidden" name="calc_update" value="1">';
    echo '<div class="calc-header">';
    echo '<h5>C&aacute;lculos avanzados</h5>';
    echo '<p class="muted">Selecciona los componentes que se suman o restan al valor base del padre.</p>';
    if (!empty($parentTabs)) {
        echo '<div class="calc-tabs">';
        foreach ($parentTabs as $tab) {
            $pid = isset($tab['id']) ? (int)$tab['id'] : 0;
            $label = isset($tab['label']) ? (string)$tab['label'] : '';
            if ($pid <= 0) {
                continue;
            }
            $activeClass = $pid === $activeParentId ? ' is-active' : '';
            echo '<button type="button" class="calc-tab' . $activeClass . '" data-calc-parent="' . $pid . '">';
            echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            echo '</button>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '<div class="calc-empty muted">Selecciona un padre para habilitar c&aacute;lculos.</div>';
    echo '<div class="calc-parent-total">';
    echo '<label class="checkbox-inline">';
    echo '<input type="checkbox" class="js-parent-add-to-father" checked> Incluir en total del padre';
    echo '</label>';
    echo '<label class="checkbox-inline">';
    echo '<input type="checkbox" class="js-parent-independent"> Concepto independiente';
    echo '</label>';
    echo '<label class="checkbox-inline">';
    echo '<input type="checkbox" class="js-parent-show-in-folio"> Mostrar en folio (relaci&oacute;n)';
    echo '</label>';
    echo '<label class="calc-percent-label">% padre <input type="number" step="any" class="js-parent-percent" value=""></label>';
    echo '</div>';
    echo '<div class="calc-grid"></div>';
    echo '<div class="calc-related">';
    echo '<div class="calc-related-title">Conceptos hijos relacionados</div>';
    echo '<div class="calc-related-body"></div>';
    echo '</div>';
    echo '<input type="hidden" name="calc_parent_id" value="' . $activeParentId . '">';
    echo '<input type="hidden" name="calc_components_csv" value="">';
    echo '<input type="hidden" name="calc_sign_json" value="">';
    echo '<input type="hidden" name="calc_state_json" value="">';
    echo '<input type="hidden" name="parent_total_state_json" value="">';
    echo '<input type="hidden" name="parent_percent_state_json" value="">';
    echo '<input type="hidden" name="parent_show_in_folio_state_json" value="">';
    echo '</div>';
}
function sale_items_make_nonce() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['sale_items_nonce_pool']) || !is_array($_SESSION['sale_items_nonce_pool'])) {
        $_SESSION['sale_items_nonce_pool'] = array();
    }
    $nonce = '';
    if (function_exists('random_bytes')) {
        $nonce = bin2hex(random_bytes(16));
    } else {
        $nonce = md5(uniqid('', true));
    }
    $_SESSION['sale_items_nonce_pool'][$nonce] = time();
    return $nonce;
}
function sale_items_filter_by_property($items, $conceptProperty) {
    if (!$items) {
        return array();
    }
    $conceptProperty = strtoupper((string)$conceptProperty);
    $filtered = array();
    foreach ($items as $item) {
        $prop = isset($item['property_code']) ? strtoupper((string)$item['property_code']) : '';
        if ($conceptProperty === '') {
            $filtered[] = $item;
            continue;
        }
        if ($prop === '' || $prop === $conceptProperty) {
            $filtered[] = $item;
        }
    }
    return $filtered;
}
function sale_items_collect_related($mapByKey, $keys, $conceptProperty) {
    $out = array();
    foreach ($keys as $key) {
        if (!isset($mapByKey[$key])) {
            continue;
        }
        $items = sale_items_filter_by_property(array_values($mapByKey[$key]), $conceptProperty);
        foreach ($items as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $out[$id] = $item;
        }
    }
    return array_values($out);
}
function sale_items_render_child_group($title, $items, $emptyLabel = '') {
    echo '<div class="child-group">';
    echo '<div class="child-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
    if (!$items) {
        echo '<div class="child-empty">' . htmlspecialchars($emptyLabel !== '' ? $emptyLabel : 'Sin registros', ENT_QUOTES, 'UTF-8') . '</div>';
    } else {
        echo '<div class="child-chips">';
        foreach ($items as $item) {
            $label = isset($item['label']) ? (string)$item['label'] : '';
            if ($label === '') {
                $label = 'Item';
            }
            echo '<span class="child-chip">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</div>';
    }
    echo '</div>';
}
function sale_items_consume_nonce($nonce) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['sale_items_nonce_pool']) || !is_array($_SESSION['sale_items_nonce_pool'])) {
        return false;
    }
    $now = time();
    foreach ($_SESSION['sale_items_nonce_pool'] as $key => $ts) {
        if (!is_int($ts) || $ts < ($now - 900)) {
            unset($_SESSION['sale_items_nonce_pool'][$key]);
        }
    }
    if (!isset($_SESSION['sale_items_nonce_pool'][$nonce])) {
        return false;
    }
    unset($_SESSION['sale_items_nonce_pool'][$nonce]);
    return true;
}
function sale_items_category_has_children($categoryId) {
    if ($categoryId <= 0) {
        return false;
    }
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare('SELECT 1 FROM sale_item_category WHERE id_parent_sale_item_category = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(array($categoryId));
    return $stmt->fetchColumn() !== false;
}
function sale_items_category_has_concepts($categoryId) {
    if ($categoryId <= 0) {
        return false;
    }
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare('SELECT 1 FROM line_item_catalog WHERE catalog_type = "sale_item" AND id_category = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(array($categoryId));
    return $stmt->fetchColumn() !== false;
}

$deletableParentCategories = array();
foreach ($parentCategories as $row) {
    $cid = isset($row['id_sale_item_category']) ? (int)$row['id_sale_item_category'] : 0;
    if ($cid <= 0) {
        continue;
    }
    $hasChildren = sale_items_category_has_children($cid);
    $hasConcepts = sale_items_category_has_concepts($cid);
    if (!$hasChildren && !$hasConcepts) {
        $deletableParentCategories[] = $row;
    }
}

/* General tab */
ob_start();
?>
<div class="page-header">
  <h2>Cat&aacute;logo de conceptos</h2>
  <p class="muted">Administra categor&iacute;as y conceptos de cobro.</p>
</div>
<div class="filters">
  <form method="post" class="form-inline">
    <label>Propiedad
      <select name="sale_items_filter_property" onchange="this.form.submit();">
        <option value="">(Todas)</option>
        <?php foreach ($properties as $property):
          $code = strtoupper((string)$property['code']);
          $name = (string)$property['name'];
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $filters['property_code'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="checkbox-inline">
      <input type="checkbox" name="sale_items_filter_inactive" value="1" <?php echo $filters['show_inactive'] ? 'checked' : ''; ?> onchange="this.form.submit();">
      Mostrar inactivos
    </label>
    <label>Buscar
      <input type="text" name="sale_items_filter_search" value="<?php echo htmlspecialchars($conceptFilters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Concepto, subcategoría, padre">
    </label>
    <label class="checkbox-inline">
      <input type="checkbox" name="sale_items_filter_derived" value="1" <?php echo $conceptFilters['derived_only'] ? 'checked' : ''; ?>>
      Solo derivados
    </label>
    <button type="submit" class="button-secondary">Aplicar</button>
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
  </form>
</div>
<?php if ($itemError): ?><p class="error"><?php echo htmlspecialchars($itemError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php if ($itemMessage): ?><p class="success"><?php echo htmlspecialchars($itemMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>


<div class="panel">
  <div class="panel-header">
    <h3>Categor&iacute;as</h3>
    <div class="header-actions">
      <details class="inline-details">
        <summary class="button-secondary">Nueva categor&iacute;a</summary>
        <form method="post" class="form-grid grid-2">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <input type="hidden" name="sale_items_action" value="create_category">
          <input type="hidden" name="sale_items_nonce" value="<?php echo htmlspecialchars(sale_items_make_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="category_parent_id" value="0">
          <input type="hidden" name="category_is_active" value="1">
          <label>Propiedad
            <select name="category_property_code">
              <option value="">(Todas)</option>
              <?php foreach ($properties as $property):
                $code = strtoupper((string)$property['code']);
                $name = (string)$property['name'];
              ?>
                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Categor&iacute;a * <input type="text" name="category_name" required></label>
          <label class="full">Descripci&oacute;n <textarea name="category_description" rows="3"></textarea></label>
          <div class="form-actions full"><button type="submit">Guardar categor&iacute;a</button></div>
        </form>
      </details>
      <details class="inline-details">
        <summary class="button-secondary">Eliminar categor&iacute;a</summary>
        <?php if (!$deletableParentCategories): ?>
          <p class="muted">Sin categor&iacute;as eliminables.</p>
        <?php else: ?>
          <form method="post" class="form-inline delete-category-form">
            <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
            <input type="hidden" name="sale_items_action" value="delete_category">
            <input type="hidden" name="category_parent_id" value="0">
            <select name="category_id" required aria-label="Categoria a eliminar">
              <?php foreach ($deletableParentCategories as $row):
                $deleteId = isset($row['id_sale_item_category']) ? (int)$row['id_sale_item_category'] : 0;
                $deleteName = isset($row['category_name']) ? (string)$row['category_name'] : '';
                $deleteProp = isset($row['property_code']) ? strtoupper((string)$row['property_code']) : '';
                $deleteLabel = $deleteName;
                if ($deleteProp !== '') {
                  $deleteLabel .= ' (' . $deleteProp . ')';
                }
              ?>
                <option value="<?php echo $deleteId; ?>"><?php echo htmlspecialchars($deleteLabel, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="button-secondary small">Eliminar</button>
          </form>
        <?php endif; ?>
      </details>
    </div>
  </div>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Categor&iacute;a</th>
          <th>Propiedad</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$parentCategories): ?>
          <tr><td colspan="3" class="muted">Sin categor&iacute;as.</td></tr>
        <?php else: foreach ($parentCategories as $row):
          $cid = (int)$row['id_sale_item_category'];
          $subcategories = isset($subcategoriesByParent[$cid]) ? $subcategoriesByParent[$cid] : array();
          $parentPropertyCode = isset($row['property_code']) ? strtoupper((string)$row['property_code']) : '';
        ?>
          <tr class="category-row">
            <td><?php echo htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$row['property_code'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td></td>
          </tr>
          <tr class="category-details-row">
            <td colspan="3">
              <details class="category-details">
                <summary><?php echo htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8'); ?></summary>
                <div class="category-details-content">
                  <div class="panel">
                    <?php if (!$subcategories): ?>
                      <p class="muted">Sin subcategor&iacute;as.</p>
                    <?php else: ?>
                      <div class="table-scroll">
                        <table>
                          <thead>
                            <tr>
                              <th>Subcategor&iacute;a</th>
                              <th>Conceptos</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($subcategories as $subcat):
                              $sid = isset($subcat['id_sale_item_category']) ? (int)$subcat['id_sale_item_category'] : 0;
                              $subName = isset($subcat['category_name']) ? (string)$subcat['category_name'] : '';
                              $subConcepts = isset($conceptsByCategory[$sid]) ? $conceptsByCategory[$sid] : array();
                            ?>
                              <tr>
                                <td><?php echo htmlspecialchars($subName, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                  <div class="concept-list">
                                  <div class="concept-list-actions">
                                    <form method="post" class="form-inline">
                                      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                      <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                                      <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="concept:new:<?php echo (int)$sid; ?>">
                                      <button type="submit" class="button-secondary small">Nuevo concepto</button>
                                    </form>
                                    <?php $canDeleteSubcategory = empty($subConcepts); ?>
                                    <form method="post" class="form-inline delete-inline">
                                      <input type="hidden" name="sale_items_action" value="delete_category">
                                      <input type="hidden" name="category_id" value="<?php echo (int)$sid; ?>">
                                      <input type="hidden" name="category_parent_id" value="<?php echo (int)$cid; ?>">
                                      <button type="submit" class="button-secondary small" <?php echo $canDeleteSubcategory ? '' : 'disabled'; ?> title="<?php echo $canDeleteSubcategory ? 'Eliminar subcategor&iacute;a' : 'No se puede eliminar: tiene conceptos.'; ?>">
                                        Eliminar subcategor&iacute;a
                                      </button>
                                    </form>
                                  </div>
                                    <div class="concept-list-rows">
                                      <?php if (!$subConcepts): ?>
                                        <div class="concept-empty">Sin conceptos.</div>
                                      <?php else: ?>
                                        <?php foreach ($subConcepts as $concept):
                                          $conceptId = isset($concept['id_sale_item_catalog']) ? (int)$concept['id_sale_item_catalog'] : 0;
                                          $conceptName = isset($concept['item_name']) ? (string)$concept['item_name'] : '';
                                          if ($conceptName === '' && isset($concept['catalog_type']) && $concept['catalog_type'] === 'tax_rule') {
                                              $fallbackName = isset($concept['name']) ? (string)$concept['name'] : '';
                                              $conceptName = $fallbackName !== '' ? $fallbackName : ('Impuesto #' . $conceptId);
                                          }
                                          $conceptProp = isset($concept['property_code']) ? (string)$concept['property_code'] : '';
                                          $derivedItems = isset($derivedParentChildMap[$conceptId]) ? $derivedParentChildMap[$conceptId] : array();
                                          $derivedItems = sale_items_filter_by_property($derivedItems, $conceptProp);
                                          $obligationItems = array();
                                          $incomeItems = array();
                                          $obligationItems = sale_items_collect_related($obligationBySaleItem, array($conceptId), $conceptProp);
                                          $incomeItems = sale_items_collect_related($incomeBySaleItem, array($conceptId), $conceptProp);
                                          if ($obligationItems) {
                                              usort($obligationItems, function($a, $b){
                                                  return strcmp((string)$a['label'], (string)$b['label']);
                                              });
                                          }
                                          if ($incomeItems) {
                                              usort($incomeItems, function($a, $b){
                                                  return strcmp((string)$a['label'], (string)$b['label']);
                                              });
                                          }
                                          $paymentItems = sale_items_collect_related($paymentByParent, array($conceptId), $conceptProp);
                                        ?>
                                          <div class="concept-row">
                                            <form method="post" class="form-inline concept-open-form">
                                              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                              <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
                                              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                                              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="concept:<?php echo (int)$conceptId; ?>">
                                              <div class="concept-item">
                                                <div class="concept-item-head">
                                                  <button type="submit" class="link-button"><?php echo htmlspecialchars($conceptName, ENT_QUOTES, 'UTF-8'); ?></button>
                                                </div>
                                                <div class="concept-children">
                                                  <?php sale_items_render_child_group('Conceptos derivados', $derivedItems, 'Sin conceptos derivados'); ?>
                                                  <?php sale_items_render_child_group('Obligaciones', $obligationItems, 'Sin obligaciones'); ?>
                                                  <?php sale_items_render_child_group('Ingresos', $incomeItems, 'Sin ingresos'); ?>
                                                  <?php sale_items_render_child_group('Pagos', $paymentItems, 'Sin cat&aacute;logo'); ?>
                                                </div>
                                              </div>
                                            </form>
                                            <form method="post" class="form-inline concept-clone-form">
                                              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                              <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
                                              <input type="hidden" name="sale_items_action" value="clone_item">
                                              <input type="hidden" name="item_id" value="<?php echo (int)$conceptId; ?>">
                                              <button type="submit" class="button-secondary small">Clonar</button>
                                            </form>
                                          </div>
                                        <?php endforeach; ?>
                                      <?php endif; ?>
                                    </div>
                                  </div>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="subcategory-actions">
                    <details class="subcategory-create-toggle">
                      <summary class="button-secondary small">Nueva subcategor&iacute;a</summary>
                      <form method="post" class="form-grid grid-2 subcategory-create">
                        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                        <input type="hidden" name="sale_items_action" value="create_category">
                        <input type="hidden" name="sale_items_nonce" value="<?php echo htmlspecialchars(sale_items_make_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="category_parent_id" value="<?php echo (int)$cid; ?>">
                        <input type="hidden" name="category_property_code" value="<?php echo htmlspecialchars($parentPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="category_is_active" value="1">
                        <label>Subcategor&iacute;a * <input type="text" name="category_name" required></label>
                        <div class="form-actions full"><button type="submit">Guardar subcategor&iacute;a</button></div>
                      </form>
                    </details>
                  </div>
                </div>
              </details>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="panel">
  <div class="panel-header spaced">
    <div>
      <h3>Registros de conceptos</h3>
      <p class="muted">Line items de folios: conceptos, impuestos, pagos, obligaciones e ingresos.</p>
    </div>
    <form method="post" class="form-inline">
      <label>Propiedad
        <select name="sale_items_line_property">
          <option value="">(Todas)</option>
          <?php foreach ($properties as $property):
            $code = strtoupper((string)$property['code']);
            $name = (string)$property['name'];
          ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $lineItemFilters['property'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Reserva
        <select name="sale_items_line_reservation_id">
          <option value="0">(Todas)</option>
          <?php foreach ($lineItemReservationOptions as $resOpt):
            $resId = isset($resOpt['id_reservation']) ? (int)$resOpt['id_reservation'] : 0;
            if ($resId <= 0) { continue; }
            $resCode = isset($resOpt['reservation_code']) && $resOpt['reservation_code'] !== '' ? (string)$resOpt['reservation_code'] : ('Reserva #' . $resId);
            $guestName = trim(
              (string)($resOpt['guest_names'] ?? '')
              . ' ' . (string)($resOpt['guest_last_name'] ?? '')
              . ' ' . (string)($resOpt['guest_maiden_name'] ?? '')
            );
            $roomCode = trim((string)($resOpt['room_code'] ?? ''));
            $roomName = trim((string)($resOpt['room_name'] ?? ''));
            if ($roomName !== '') {
              $roomCode = $roomCode !== '' ? ($roomCode . ' - ' . $roomName) : $roomName;
            }
            $resLabel = $guestName !== '' ? $guestName : $resCode;
            if ($roomCode !== '') {
              $resLabel .= ' | ' . $roomCode;
            }
            if (!empty($resOpt['property_code'])) {
              $resLabel .= ' | ' . (string)$resOpt['property_code'];
            }
          ?>
            <option value="<?php echo (int)$resId; ?>" <?php echo (int)$lineItemFilters['reservation_id'] === (int)$resId ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($resLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Tipo line item
        <select name="sale_items_line_item_type">
          <option value="">(Todos)</option>
          <?php foreach (array('sale_item','tax_item','payment','obligation','income') as $it): ?>
            <option value="<?php echo $it; ?>" <?php echo $lineItemFilters['item_type'] === $it ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($it, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Tipo de cat&aacute;logo
        <select name="sale_items_line_catalog_type">
          <option value="">(Todos)</option>
          <option value="sale_item" <?php echo $lineItemFilters['catalog_type'] === 'sale_item' ? 'selected' : ''; ?>>sale_item</option>
          <option value="tax_rule" <?php echo $lineItemFilters['catalog_type'] === 'tax_rule' ? 'selected' : ''; ?>>tax_rule</option>
          <option value="payment" <?php echo $lineItemFilters['catalog_type'] === 'payment' ? 'selected' : ''; ?>>payment</option>
          <option value="obligation" <?php echo $lineItemFilters['catalog_type'] === 'obligation' ? 'selected' : ''; ?>>obligation</option>
          <option value="income" <?php echo $lineItemFilters['catalog_type'] === 'income' ? 'selected' : ''; ?>>income</option>
          <option value="none" <?php echo $lineItemFilters['catalog_type'] === 'none' ? 'selected' : ''; ?>>(Sin cat&aacute;logo)</option>
        </select>
      </label>
      <label>Desde
        <input type="date" name="sale_items_line_from" value="<?php echo htmlspecialchars($lineItemFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>Hasta
        <input type="date" name="sale_items_line_to" value="<?php echo htmlspecialchars($lineItemFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>Tipo de fecha
        <select name="sale_items_line_date_field">
          <option value="service_date" <?php echo $lineItemFilters['date_field'] === 'service_date' ? 'selected' : ''; ?>>Fecha de servicio</option>
          <option value="created_at" <?php echo $lineItemFilters['date_field'] === 'created_at' ? 'selected' : ''; ?>>Fecha de creaci&oacute;n</option>
        </select>
      </label>
      <label>Estatus folio
        <select name="sale_items_line_folio_status">
          <option value="">(Todos)</option>
          <?php foreach (array('open','closed','void','paid','overdue') as $fst): ?>
            <option value="<?php echo $fst; ?>" <?php echo $lineItemFilters['folio_status'] === $fst ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($fst, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Estatus
        <select name="sale_items_line_status">
          <option value="">(Todos)</option>
          <?php foreach (array('posted','void','pending','captured') as $st): ?>
            <option value="<?php echo $st; ?>" <?php echo $lineItemFilters['status'] === $st ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Moneda
        <input type="text" name="sale_items_line_currency" value="<?php echo htmlspecialchars($lineItemFilters['currency'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="MXN" maxlength="10">
      </label>
      <label class="checkbox-inline">
        <input type="checkbox" name="sale_items_line_derived" value="1" <?php echo $lineItemFilters['derived_only'] ? 'checked' : ''; ?>>
        Solo subconceptos
      </label>
      <label class="checkbox-inline">
        <input type="checkbox" name="sale_items_line_hierarchy" value="1" <?php echo !empty($lineItemFilters['hierarchy_mode']) ? 'checked' : ''; ?>>
        Vista jer&aacute;rquica
      </label>
      <label>Limite
        <select name="sale_items_line_limit">
          <?php foreach (array(100,200,500,1000) as $lim): ?>
            <option value="<?php echo $lim; ?>" <?php echo (int)$lineItemFilters['limit'] === (int)$lim ? 'selected' : ''; ?>><?php echo $lim; ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
      <button type="submit" class="button-secondary">Aplicar</button>
    </form>
  </div>
  <?php if (!empty($lineItemFilters['view_line_item_id'])): ?>
    <div class="card" style="margin-bottom:12px;">
      <div class="card-head" style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
        <strong>Detalle line item #<?php echo (int)$lineItemFilters['view_line_item_id']; ?></strong>
        <form method="post" class="inline-form">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
          <input type="hidden" name="sale_items_action" value="clear_line_item_view">
          <button type="submit" class="button-secondary small">Cerrar detalle</button>
        </form>
      </div>
      <?php if (!$lineItemViewDetail): ?>
        <p class="muted">No se encontró el line item o ya no está activo.</p>
      <?php else: ?>
        <?php
          $vdCurrency = isset($lineItemViewDetail['currency']) && $lineItemViewDetail['currency'] !== '' ? (string)$lineItemViewDetail['currency'] : 'MXN';
          $vdConcept = trim((string)($lineItemViewDetail['item_name'] ?? ''));
          if ($vdConcept === '') { $vdConcept = trim((string)($lineItemViewDetail['description'] ?? '')); }
          if ($vdConcept === '') { $vdConcept = '(line_item)'; }
        ?>
        <div class="grid-3" style="margin-top:8px;">
          <div><span class="muted">Concepto</span><div><?php echo htmlspecialchars($vdConcept, ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Tipo line item</span><div><?php echo htmlspecialchars((string)$lineItemViewDetail['item_type'], ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Tipo catálogo</span><div><?php echo htmlspecialchars((string)($lineItemViewDetail['catalog_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Monto</span><div><?php echo htmlspecialchars(sale_items_format_money((int)$lineItemViewDetail['amount_cents'], $vdCurrency), ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Unitario</span><div><?php echo htmlspecialchars(sale_items_format_money((int)$lineItemViewDetail['unit_price_cents'], $vdCurrency), ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Cantidad</span><div><?php echo htmlspecialchars((string)$lineItemViewDetail['quantity'], ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Método</span><div><?php echo htmlspecialchars((string)($lineItemViewDetail['method'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Referencia</span><div><?php echo htmlspecialchars((string)($lineItemViewDetail['reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Estatus</span><div><?php echo htmlspecialchars((string)$lineItemViewDetail['status'], ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Fecha servicio</span><div><?php echo htmlspecialchars((string)$lineItemViewDetail['service_date'], ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Creado</span><div><?php echo htmlspecialchars((string)$lineItemViewDetail['created_at'], ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div><span class="muted">Actualizado</span><div><?php echo htmlspecialchars((string)$lineItemViewDetail['updated_at'], ENT_QUOTES, 'UTF-8'); ?></div></div>
          <div class="full"><span class="muted">Descripción</span><div><?php echo htmlspecialchars((string)($lineItemViewDetail['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div>
        </div>
        <div class="grid-2" style="margin-top:10px;">
          <div>
            <div><strong>Padre</strong></div>
            <?php if (!$lineItemViewParents): ?>
              <p class="muted">Sin padres en este nodo.</p>
            <?php else: ?>
              <?php foreach ($lineItemViewParents as $prow): ?>
                <?php
                  $pCurrency = isset($prow['currency']) && $prow['currency'] !== '' ? (string)$prow['currency'] : 'MXN';
                  $pQty = isset($prow['quantity']) ? (string)$prow['quantity'] : '1';
                  $pAmount = isset($prow['amount_cents']) ? sale_items_format_money((int)$prow['amount_cents'], $pCurrency) : ('0.00 ' . $pCurrency);
                ?>
                <form method="post" class="inline-form" style="margin-top:6px;">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
                  <input type="hidden" name="sale_items_action" value="view_line_item">
                  <input type="hidden" name="view_line_item_id" value="<?php echo (int)$prow['id_line_item']; ?>">
                  <button type="submit" class="button-secondary small">
                    #<?php echo (int)$prow['id_line_item']; ?> - <?php echo htmlspecialchars((string)($prow['item_name'] ?: $prow['description']), ENT_QUOTES, 'UTF-8'); ?>
                    | qty: <?php echo htmlspecialchars($pQty, ENT_QUOTES, 'UTF-8'); ?>
                    | <?php echo htmlspecialchars($pAmount, ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                </form>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div>
            <div><strong>Hijos</strong></div>
            <?php if (!$lineItemViewChildren): ?>
              <p class="muted">Sin hijos en este nodo.</p>
            <?php else: ?>
              <?php foreach ($lineItemViewChildren as $crow): ?>
                <?php
                  $cCurrency = isset($crow['currency']) && $crow['currency'] !== '' ? (string)$crow['currency'] : 'MXN';
                  $cQty = isset($crow['quantity']) ? (string)$crow['quantity'] : '1';
                  $cAmount = isset($crow['amount_cents']) ? sale_items_format_money((int)$crow['amount_cents'], $cCurrency) : ('0.00 ' . $cCurrency);
                ?>
                <form method="post" class="inline-form" style="margin-top:6px;">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
                  <input type="hidden" name="sale_items_action" value="view_line_item">
                  <input type="hidden" name="view_line_item_id" value="<?php echo (int)$crow['id_line_item']; ?>">
                  <button type="submit" class="button-secondary small">
                    #<?php echo (int)$crow['id_line_item']; ?> - <?php echo htmlspecialchars((string)($crow['item_name'] ?: $crow['description']), ENT_QUOTES, 'UTF-8'); ?>
                    | qty: <?php echo htmlspecialchars($cQty, ENT_QUOTES, 'UTF-8'); ?>
                    | <?php echo htmlspecialchars($cAmount, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($crow['add_to_father_total'])): ?> (incluye en padre)<?php endif; ?>
                  </button>
                </form>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!$lineItems): ?>
    <p class="muted">Sin registros para los filtros seleccionados.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th><?php echo $lineItemFilters['date_field'] === 'created_at' ? 'Fecha de creaci&oacute;n' : 'Fecha de servicio'; ?></th>
            <th>Tipo</th>
            <th>Concepto</th>
            <th>Subcategor&iacute;a</th>
            <th>Reserva</th>
            <th>Folio</th>
            <th>Moneda</th>
            <th>Monto</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lineItemRenderRows as $row): ?>
            <?php
              $currency = isset($row['currency']) && $row['currency'] !== '' ? (string)$row['currency'] : 'MXN';
              $reservationCode = isset($row['reservation_code']) && $row['reservation_code'] !== '' ? (string)$row['reservation_code'] : 'Reserva';
              $folioName = isset($row['folio_name']) && $row['folio_name'] !== '' ? (string)$row['folio_name'] : 'Folio';
              $folioStatus = isset($row['folio_status']) && $row['folio_status'] !== '' ? (string)$row['folio_status'] : '';
              $folioLabel = $folioName . ($folioStatus !== '' ? (' (' . $folioStatus . ')') : '');
              $guestFullName = trim(
                  (string)($row['guest_names'] ?? '')
                  . ' ' . (string)($row['guest_last_name'] ?? '')
                  . ' ' . (string)($row['guest_maiden_name'] ?? '')
              );
              $roomLabel = trim((string)($row['room_code'] ?? ''));
              $roomName = trim((string)($row['room_name'] ?? ''));
              if ($roomName !== '') {
                  $roomLabel = $roomLabel !== '' ? ($roomLabel . ' - ' . $roomName) : $roomName;
              }
              $reservationLabel = trim($guestFullName);
              if ($reservationLabel === '') {
                  $reservationLabel = $reservationCode;
              }
              if ($roomLabel !== '') {
                  $reservationLabel .= ' + ' . $roomLabel;
              }
              $dateValue = $lineItemFilters['date_field'] === 'created_at'
                  ? (string)($row['created_at'] ?? '')
                  : (string)($row['service_date'] ?? '');
              $reservationUrl = 'index.php?view=reservations&reservation_id=' . (int)$row['id_reservation'];
              $folioUrl = $reservationUrl . '#folio-' . (int)$row['id_folio'];
              $saleId = isset($row['id_line_item']) ? (int)$row['id_line_item'] : 0;
              $itemType = isset($row['item_type']) ? (string)$row['item_type'] : '';
              $lineDepth = isset($lineItemDepthById[$saleId]) ? (int)$lineItemDepthById[$saleId] : 0;
              $hasChildren = !empty($lineItemVisibleChildrenById[$saleId]);
              $parentLineId = isset($lineItemParentById[$saleId]) ? (int)$lineItemParentById[$saleId] : 0;
              $isHierarchyMode = !empty($lineItemFilters['hierarchy_mode']);
              $descLabel = trim((string)($row['description'] ?? ''));
              $conceptLabel = trim((string)($row['item_name'] ?? ''));
              if ($isDerived && $descLabel !== '') {
                  $conceptLabel = $descLabel;
              } elseif ($conceptLabel === '') {
                  $conceptLabel = $descLabel;
              }
              if ($conceptLabel === '') {
                  $conceptLabel = '(' . ($itemType !== '' ? $itemType : 'line_item') . ')';
              }
              $indentPx = $lineDepth > 0 ? min(22 + ($lineDepth * 28), 280) : 0;
            ?>
            <tr
              <?php if ($isHierarchyMode): ?>
                class="line-item-row-hierarchy"
                data-line-id="<?php echo (int)$saleId; ?>"
                data-parent-id="<?php echo (int)$parentLineId; ?>"
                data-depth="<?php echo (int)$lineDepth; ?>"
              <?php endif; ?>
            >
              <td><?php echo htmlspecialchars($dateValue, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($itemType !== '' ? $itemType : '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($isHierarchyMode): ?>
                  <div class="line-item-concept-wrap" style="padding-left: <?php echo (int)$indentPx; ?>px;">
                    <?php if ($hasChildren): ?>
                      <button
                        type="button"
                        class="button-secondary small js-line-item-toggle"
                        data-target-line-id="<?php echo (int)$saleId; ?>"
                        style="margin-right:6px;"
                      >Ocultar derivados</button>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                <?php else: ?>
                  <?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars((string)($row['category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <a class="link-button" href="<?php echo htmlspecialchars($reservationUrl, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($reservationLabel, ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </td>
              <td>
                <a class="link-button" href="<?php echo htmlspecialchars($folioUrl, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($folioLabel, ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </td>
              <td><?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(sale_items_format_money((int)$row['amount_cents'], $currency), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <form method="post" class="inline-form">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
                  <input type="hidden" name="sale_items_action" value="view_line_item">
                  <input type="hidden" name="view_line_item_id" value="<?php echo (int)$saleId; ?>">
                  <button type="submit" class="button-secondary small">Ver</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
$generalContent = ob_get_clean();

$conceptTreeTypes = array();
foreach ($conceptTreeNodes as $treeNode) {
    $treeType = isset($treeNode['catalog_type']) && trim((string)$treeNode['catalog_type']) !== ''
        ? (string)$treeNode['catalog_type']
        : '(sin tipo)';
    if (!isset($conceptTreeTypes[$treeType])) {
        $conceptTreeTypes[$treeType] = 0;
    }
    $conceptTreeTypes[$treeType]++;
}
ksort($conceptTreeTypes);

$conceptTreeNodesJson = htmlspecialchars(json_encode($conceptTreeNodes), ENT_QUOTES, 'UTF-8');
$conceptTreeEdgesJson = htmlspecialchars(json_encode($conceptTreeEdges), ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div
  class="panel concept-tree-panel"
  data-concept-tree
  data-tree-nodes="<?php echo $conceptTreeNodesJson; ?>"
  data-tree-edges="<?php echo $conceptTreeEdgesJson; ?>"
>
  <div class="panel-header spaced">
    <div>
      <h3>Arbol de relaciones de conceptos</h3>
      <p class="muted">
        Nodos: <?php echo (int)$conceptTreeStats['node_count']; ?>
        | Relaciones: <?php echo (int)$conceptTreeStats['edge_count']; ?>
      </p>
    </div>
    <div class="concept-tree-toolbar">
      <label>Buscar
        <input type="text" data-tree-search placeholder="Concepto, categoria o propiedad">
      </label>
      <label>Tipo
        <select data-tree-type>
          <option value="">(Todos)</option>
          <?php foreach ($conceptTreeTypes as $typeKey => $typeCount): ?>
            <option value="<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($typeKey . ' (' . $typeCount . ')', ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="checkbox-inline">
        <input type="checkbox" value="1" data-tree-hide-isolated>
        Ocultar nodos sin relacion
      </label>
      <div class="concept-tree-zoom-actions">
        <button type="button" class="button-secondary small" data-tree-zoom-out>-</button>
        <button type="button" class="button-secondary small" data-tree-zoom-reset>100%</button>
        <button type="button" class="button-secondary small" data-tree-zoom-in>+</button>
        <button type="button" class="button-secondary small" data-tree-fit>Ajustar</button>
      </div>
    </div>
  </div>

  <?php if (!$conceptTreeNodes): ?>
    <p class="muted">No hay conceptos para graficar con los filtros actuales.</p>
  <?php else: ?>
    <div class="concept-tree-layout">
      <div class="concept-tree-canvas-wrap">
        <svg class="concept-tree-svg" data-tree-svg>
          <g data-tree-viewport>
            <g data-tree-edges></g>
            <g data-tree-nodes></g>
          </g>
        </svg>
      </div>
      <aside class="concept-tree-side">
        <h4>Detalle</h4>
        <div class="concept-tree-detail muted" data-tree-detail>
          Selecciona un nodo para ver sus relaciones padre/hijo y los detalles de cada enlace.
        </div>
        <div class="concept-tree-relations" data-tree-relations></div>
      </aside>
    </div>
  <?php endif; ?>
</div>
<?php
$treeContent = ob_get_clean();

/* dynamic tabs */
$dynamicTabs = array();

/* new category tab */
if (in_array('category:new', $subtabState['open'], true)) {
    ob_start();
    ?>
    <div class="subtab-actions">
      <form method="post" id="sale-cat-close-new">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="category:new">
        <button type="submit" class="button-secondary">Cerrar</button>
      </form>
    </div>
    <h3>Nueva categor&iacute;a</h3>
    <form method="post" class="form-grid grid-2 sale-item-form">
      <input type="hidden" name="sale_items_action" value="create_category">
      <input type="hidden" name="sale_items_nonce" value="<?php echo htmlspecialchars(sale_items_make_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="category_parent_id" value="0">
      <input type="hidden" name="category_is_active" value="1">
      <label>Propiedad
        <select name="category_property_code">
          <option value="">(Todas)</option>
          <?php foreach ($properties as $property):
            $code = strtoupper((string)$property['code']);
            $name = (string)$property['name'];
          ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Categor&iacute;a * <input type="text" name="category_name" required></label>
      <label class="full">Descripci&oacute;n <textarea name="category_description" rows="3"></textarea></label>
      <div class="form-actions full"><button type="submit">Guardar categor&iacute;a</button></div>
    </form>
    <?php
    $dynamicTabs[] = array(
        'key' => 'category:new',
        'title' => 'Nueva categoria',
        'panel_id' => 'sale-cat-new',
        'close_form_id' => 'sale-cat-close-new',
        'content' => ob_get_clean()
    );
}

/* category detail tabs */
foreach ($openCategoryIds as $cid) {
    $detail = isset($categoryDetail[$cid]) ? $categoryDetail[$cid] : null;
    $subcategories = isset($subcategoriesByParent[$cid]) ? $subcategoriesByParent[$cid] : array();
    ob_start();
    ?>
    <div class="subtab-actions">
      <div><h3><?php echo htmlspecialchars($detail ? (string)$detail['category_name'] : ('Categoria ' . $cid), ENT_QUOTES, 'UTF-8'); ?></h3></div>
      <form method="post" id="sale-cat-close-<?php echo (int)$cid; ?>">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="category:<?php echo (int)$cid; ?>">
        <button type="submit" class="button-secondary">Cerrar</button>
      </form>
    </div>
    <?php if (!$detail): ?>
      <p class="muted">No se encontr&oacute; la categor&iacute;a.</p>
    <?php else: ?>
      <?php
        $parentPropertyCode = isset($detail['property_code']) ? strtoupper((string)$detail['property_code']) : '';
      ?>
      <form method="post" class="form-grid grid-2 sale-item-form">
        <input type="hidden" name="sale_items_action" value="update_category">
        <input type="hidden" name="category_id" value="<?php echo (int)$cid; ?>">
        <input type="hidden" name="category_parent_id" value="0">
        <label>Propiedad
          <select name="category_property_code">
            <option value="">(Todas)</option>
            <?php foreach ($properties as $property):
              $code = strtoupper((string)$property['code']);
              $name = (string)$property['name'];
            ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo isset($detail['property_code']) && strtoupper($detail['property_code']) === $code ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Categor&iacute;a * <input type="text" name="category_name" value="<?php echo htmlspecialchars((string)$detail['category_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
        <label class="checkbox-inline"><input type="checkbox" name="category_is_active" <?php echo !empty($detail['is_active']) ? 'checked' : ''; ?>> Activa</label>
        <label class="full">Descripci&oacute;n <textarea name="category_description" rows="3"><?php echo htmlspecialchars((string)$detail['description'], ENT_QUOTES, 'UTF-8'); ?></textarea></label>
        <div class="form-actions full">
          <button type="submit">Guardar</button>
        </div>
      </form>
      <form method="post" class="form-inline">
        <input type="hidden" name="sale_items_action" value="delete_category">
        <input type="hidden" name="category_id" value="<?php echo (int)$cid; ?>">
        <input type="hidden" name="category_parent_id" value="0">
        <button type="submit" class="button-secondary">Eliminar categor&iacute;a</button>
      </form>

      <div class="panel">
        <div class="panel-header spaced">
          <h4>Subcategor&iacute;as</h4>
        </div>
        <form method="post" class="form-grid grid-2">
          <input type="hidden" name="sale_items_action" value="create_category">
          <input type="hidden" name="sale_items_nonce" value="<?php echo htmlspecialchars(sale_items_make_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="category_parent_id" value="<?php echo (int)$cid; ?>">
          <input type="hidden" name="category_property_code" value="<?php echo htmlspecialchars($parentPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
          <label>Subcategor&iacute;a * <input type="text" name="category_name" required></label>
          <label class="checkbox-inline"><input type="checkbox" name="category_is_active" checked> Activa</label>
          <label class="full">Descripci&oacute;n <textarea name="category_description" rows="2"></textarea></label>
          <div class="form-actions full"><button type="submit">Guardar subcategor&iacute;a</button></div>
        </form>
        <?php if (!$subcategories): ?>
          <p class="muted">Sin subcategor&iacute;as.</p>
        <?php endif; ?>
      </div>

      <?php foreach ($subcategories as $subcat):
        $sid = isset($subcat['id_sale_item_category']) ? (int)$subcat['id_sale_item_category'] : 0;
        $subName = isset($subcat['category_name']) ? (string)$subcat['category_name'] : '';
        $subConcepts = isset($conceptsByCategory[$sid]) ? $conceptsByCategory[$sid] : array();
        $subPropertyCode = isset($subcat['property_code']) ? strtoupper((string)$subcat['property_code']) : '';
      ?>
        <div class="panel subcategory-panel">
          <div class="panel-header spaced">
            <h4>Subcategor&iacute;a: <?php echo htmlspecialchars($subName, ENT_QUOTES, 'UTF-8'); ?></h4>
            <form method="post" class="form-inline">
              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="concept:new:<?php echo (int)$sid; ?>">
              <button type="submit" class="button-secondary">Nuevo concepto</button>
            </form>
          </div>
          <form method="post" class="form-grid grid-2">
            <input type="hidden" name="sale_items_action" value="update_category">
            <input type="hidden" name="category_id" value="<?php echo (int)$sid; ?>">
            <input type="hidden" name="category_parent_id" value="<?php echo (int)$cid; ?>">
            <label>Propiedad
              <select name="category_property_code">
                <option value="">(Todas)</option>
                <?php foreach ($properties as $property):
                  $code = strtoupper((string)$property['code']);
                  $name = (string)$property['name'];
                ?>
                  <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $subPropertyCode !== '' && $subPropertyCode === $code ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Subcategor&iacute;a * <input type="text" name="category_name" value="<?php echo htmlspecialchars($subName, ENT_QUOTES, 'UTF-8'); ?>" required></label>
            <label class="checkbox-inline"><input type="checkbox" name="category_is_active" <?php echo !empty($subcat['is_active']) ? 'checked' : ''; ?>> Activa</label>
            <label class="full">Descripci&oacute;n <textarea name="category_description" rows="2"><?php echo htmlspecialchars((string)$subcat['description'], ENT_QUOTES, 'UTF-8'); ?></textarea></label>
            <div class="form-actions full">
              <button type="submit">Guardar subcategor&iacute;a</button>
            </div>
          </form>
          <form method="post" class="form-inline">
            <input type="hidden" name="sale_items_action" value="delete_category">
            <input type="hidden" name="category_id" value="<?php echo (int)$sid; ?>">
            <input type="hidden" name="category_parent_id" value="<?php echo (int)$cid; ?>">
            <button type="submit" class="button-secondary">Eliminar subcategor&iacute;a</button>
          </form>

          <div class="panel">
            <div class="panel-header spaced">
              <h5>Conceptos</h5>
            </div>
            <?php if (!$subConcepts): ?>
              <p class="muted">Sin conceptos.</p>
            <?php else: ?>
              <div class="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Concepto</th>
                      <th>Padre</th>
                      <th>Precio base</th>
                      <th>Tipo</th>
                      <th>En folio</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($subConcepts as $r):
                      $rid = (int)$r['id_sale_item_catalog'];
                      $parentIds = sale_items_parse_ids(isset($r['parent_item_ids']) ? $r['parent_item_ids'] : '');
                      $parentLabels = array();
                      foreach ($parentIds as $pid) {
                        if (isset($parentConceptLabelMap[$pid])) {
                          $parentLabels[] = $parentConceptLabelMap[$pid];
                        } else {
                          $parentLabels[] = (string)$pid;
                        }
                      }
                      $parentName = $parentLabels ? implode(', ', $parentLabels) : '';
                      $isPercent = !empty($r['is_percent']);
                      $percentValue = isset($r['percent_value']) ? (float)$r['percent_value'] : 0;
                      $addToFatherTotal = isset($r['add_to_father_total']) ? (int)$r['add_to_father_total'] : 1;
                      $showInFolio = !empty($r['show_in_folio']);
                    ?>
                      <tr>
                        <td><?php echo htmlspecialchars((string)$r['item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($parentName !== '' ? $parentName : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars('$' . number_format(((int)$r['default_unit_price_cents'])/100, 2), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($isPercent ? ('%' . number_format($percentValue, 2)) : 'Fijo', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $showInFolio ? 'Si' : 'No'; ?></td>
                        <td>
                          <details>
                            <summary>Editar</summary>
                            <form method="post" class="form-grid grid-2 sale-item-form">
                              <input type="hidden" name="sale_items_action" value="update_item">
                              <input type="hidden" name="item_id" value="<?php echo $rid; ?>">
                              <input type="hidden" name="item_category_id" value="<?php echo (int)$sid; ?>">
                              <label>Tipo de line item
                                <select name="item_catalog_type" class="js-item-catalog-type">
                                  <?php $typeValue = isset($r['catalog_type']) ? (string)$r['catalog_type'] : 'sale_item'; ?>
                                  <option value="sale_item" <?php echo $typeValue === 'sale_item' ? 'selected' : ''; ?>>Concepto</option>
                                  <option value="payment" <?php echo $typeValue === 'payment' ? 'selected' : ''; ?>>Pago</option>
                                  <option value="obligation" <?php echo $typeValue === 'obligation' ? 'selected' : ''; ?>>Obligaci&oacute;n</option>
                                  <option value="income" <?php echo $typeValue === 'income' ? 'selected' : ''; ?>>Ingreso</option>
                                  <option value="tax_rule" <?php echo $typeValue === 'tax_rule' ? 'selected' : ''; ?>>Impuesto</option>
                                </select>
                              </label>
                              <label>Concepto <input type="text" name="item_name" value="<?php echo htmlspecialchars((string)$r['item_name'], ENT_QUOTES, 'UTF-8'); ?>"></label>
                              <label>Precio base <input type="number" step="0.01" name="item_price" value="<?php echo htmlspecialchars(number_format(((int)$r['default_unit_price_cents'])/100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"></label>
                              <label class="checkbox-inline"><input type="checkbox" name="item_show_in_folio" <?php echo !empty($r['show_in_folio']) ? 'checked' : ''; ?>> Mostrar en folio</label>
                              <label class="checkbox-inline"><input type="checkbox" name="item_allow_negative" <?php echo !empty($r['allow_negative']) ? 'checked' : ''; ?>> Permitir negativo</label>
                              <label class="checkbox-inline"><input type="checkbox" name="item_is_active" <?php echo !empty($r['is_active']) ? 'checked' : ''; ?>> Activo</label>
                              <label class="full">Descripci&oacute;n <textarea name="item_description" rows="2"><?php echo htmlspecialchars((string)$r['description'], ENT_QUOTES, 'UTF-8'); ?></textarea></label>

                              <details class="form-section full concept-derivative">
                                <summary class="section-summary">Concepto derivado</summary>
                                <div class="form-grid grid-2">
                                  <label class="full section-label">Padre</label>
                                  <div class="full">
                                    <?php
                                      $selectedParents = sale_items_parse_ids(isset($r['parent_item_ids']) ? $r['parent_item_ids'] : '');
                                      render_parent_picklist($parentConceptOptions, $selectedParents, $categoriesById, $rid, 'item_parent_ids[]', 'parent-picklist-' . $rid);
                                    ?>
                                  </div>
                                  <input type="hidden" name="item_add_to_father_total" value="<?php echo $addToFatherTotal ? '1' : '0'; ?>">
                                </div>
                              </details>

                              <div class="form-actions full inline-actions">
                                <button type="submit" class="button-secondary">Guardar</button>
                                <button type="submit" class="button-secondary" form="concept-delete-<?php echo $rid; ?>">Eliminar</button>
                              </div>
                            </form>
                            <form method="post" id="concept-delete-<?php echo $rid; ?>" class="form-inline inline-hidden">
                              <input type="hidden" name="sale_items_action" value="delete_item">
                              <input type="hidden" name="item_id" value="<?php echo $rid; ?>">
                              <input type="hidden" name="item_category_id" value="<?php echo (int)$sid; ?>">
                            </form>
                          </details>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php
    $dynamicTabs[] = array(
        'key' => 'category:' . $cid,
        'title' => $detail ? (string)$detail['category_name'] : ('Categoria ' . $cid),
        'panel_id' => 'sale-cat-' . $cid,
        'close_form_id' => 'sale-cat-close-' . $cid,
        'content' => ob_get_clean()
    );
}

/* concept new tabs (if opened) */
$openConceptTabs = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $key) {
    if (strpos($key, 'concept:new:') === 0) {
        $openConceptTabs[] = substr($key, strlen('concept:new:'));
    }
}
foreach ($openConceptTabs as $slug) {
    $parts = explode(':', $slug);
    $catId = isset($parts[0]) ? (int)$parts[0] : 0;
    $formNonce = sale_items_make_nonce();
    ob_start();
    ?>
    <div class="subtab-actions">
      <form method="post" id="concept-new-close-<?php echo (int)$catId; ?>">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="concept:new:<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="button-secondary">Cerrar</button>
      </form>
    </div>
    <h3>Nuevo concepto</h3>
    <div class="concept-editor-tabs js-concept-editor-tabs" data-default-tab="parents">
      <div class="concept-editor-tab-nav-wrap">
        <div class="concept-editor-tab-nav" role="tablist" aria-label="Nuevo concepto">
          <button type="button" class="button-secondary small concept-editor-tab-btn" data-concept-tab="parents">Padres</button>
          <button type="button" class="button-secondary small concept-editor-tab-btn" data-concept-tab="children">Hijos</button>
        </div>
        <div class="concept-editor-top-actions">
          <button type="submit" class="button-secondary" form="concept-create-main-<?php echo (int)$catId; ?>">Guardar concepto</button>
        </div>
      </div>
      <form method="post" class="form-grid grid-2 sale-item-form" id="concept-create-main-<?php echo (int)$catId; ?>">
        <input type="hidden" name="sale_items_action" value="create_item">
        <input type="hidden" name="sale_items_nonce" value="<?php echo htmlspecialchars($formNonce, ENT_QUOTES, 'UTF-8'); ?>">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>

        <div class="concept-editor-static-panel full">
          <div class="form-grid grid-2">
            <label>Subcategor&iacute;a
              <select name="item_category_id" class="js-item-category">
                <option value="">Selecciona</option>
                <?php foreach ($subcategoryList as $cat):
                  $cid = (int)$cat['id_sale_item_category'];
                  $parentId = isset($cat['id_parent_sale_item_category']) ? (int)$cat['id_parent_sale_item_category'] : 0;
                  $parentName = ($parentId > 0 && isset($categoriesById[$parentId])) ? (string)$categoriesById[$parentId]['category_name'] : '';
                  $propLabel = isset($cat['property_code']) && $cat['property_code'] !== '' ? (string)$cat['property_code'] : '(Todas)';
                  $label = ($parentName !== '' ? ($parentName . ' / ') : '') . (string)$cat['category_name'] . ' - ' . $propLabel;
                ?>
                  <option value="<?php echo $cid; ?>" <?php echo $cid === $catId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Tipo de line item
              <select name="item_catalog_type" class="js-item-catalog-type">
                <option value="sale_item">Concepto</option>
                <option value="payment">Pago</option>
                <option value="obligation">Obligaci&oacute;n</option>
                <option value="income">Ingreso</option>
                <option value="tax_rule">Impuesto</option>
              </select>
            </label>
            <label>Concepto * <input type="text" name="item_name" required></label>
            <label>Precio base <input type="number" step="0.01" name="item_price" value="0"></label>
            <label class="checkbox-inline"><input type="checkbox" name="item_show_in_folio" checked> Mostrar en folio</label>
            <label class="checkbox-inline"><input type="checkbox" name="item_allow_negative"> Permitir negativo</label>
            <label class="checkbox-inline"><input type="checkbox" name="item_is_active" checked> Activo</label>
            <label class="full">Descripci&oacute;n <textarea name="item_description" rows="2"></textarea></label>
          </div>
        </div>

        <div class="concept-editor-tab-panel full" data-concept-tab-panel="parents">
          <div class="form-section full">
            <h4>Relacion con conceptos padre</h4>
            <div class="form-grid grid-2">
              <label class="full section-label">Padre</label>
              <div class="full">
                <?php render_parent_picklist($parentConceptOptions, array(), $categoriesById, 0, 'item_parent_ids[]', 'parent-picklist-new-' . $catId); ?>
              </div>
              <input type="hidden" name="item_add_to_father_total" value="1">
            </div>
          </div>
          <?php render_calc_components_section($calcParentChildMap, array(), array(), array(), 0, 0, $derivedParentChildMap, 0, array(), array(), array(), 1); ?>
        </div>

        <div class="concept-editor-tab-panel full" data-concept-tab-panel="children">
          <div class="form-section full">
            <h4>Relacion con conceptos hijo</h4>
            <p class="muted">Para agregar hijos primero guarda el concepto. Despues podras administrarlos en esta misma vista desde la pesta&ntilde;a Hijos del concepto creado.</p>
          </div>
        </div>
      </form>
    </div>
    <?php
    $dynamicTabs[] = array(
        'key' => 'concept:new:' . $slug,
        'title' => 'Nuevo concepto',
        'panel_id' => 'concept-new-' . $catId,
        'close_form_id' => 'concept-new-close-' . $catId,
        'content' => ob_get_clean()
    );
}

/* concept detail tabs */
$openConceptDetailIds = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $key) {
    if (strpos($key, 'concept:') === 0 && strpos($key, 'concept:new:') !== 0) {
        $id = (int)substr($key, strlen('concept:'));
        if ($id > 0 && !in_array($id, $openConceptDetailIds, true)) {
            $openConceptDetailIds[] = $id;
        }
    }
}
foreach ($openConceptDetailIds as $conceptId) {
    $detail = null;
    try {
        $dsets = pms_call_procedure('sp_sale_item_catalog_data', array($companyCode, null, 1, $conceptId, 0));
        $detail = isset($dsets[1][0]) ? $dsets[1][0] : null;
    } catch (Exception $e) {
        $detail = null;
    }
    if (!$detail) {
        $dsets = sale_items_catalog_data_fallback($companyId, null, 1, $conceptId, 0);
        $detail = isset($dsets[1][0]) ? $dsets[1][0] : null;
    }

    $catId = $detail && isset($detail['id_category']) ? (int)$detail['id_category'] : 0;
    $conceptName = $detail && isset($detail['item_name']) ? (string)$detail['item_name'] : ('Concepto ' . $conceptId);
    $isPercent = $detail && !empty($detail['is_percent']);
    $percentValue = $detail && isset($detail['percent_value']) ? (float)$detail['percent_value'] : 0;
    $catalogType = $detail && isset($detail['catalog_type']) ? (string)$detail['catalog_type'] : 'sale_item';
    $addToFatherTotal = $detail && isset($detail['add_to_father_total']) ? (int)$detail['add_to_father_total'] : 1;
    $parentIdsCsv = $detail && isset($detail['parent_item_ids']) ? (string)$detail['parent_item_ids'] : '';
    $selectedParents = $parentIdsCsv !== '' ? sale_items_parse_ids($parentIdsCsv) : array();
    $parentOptions = $parentConceptOptions;
    $calcMap = sale_items_load_calc_map($conceptId);
    $calcSelection = sale_items_calc_selection_from_map($calcMap);
    $calcSelected = $calcSelection['selected'];
    $calcSigns = $calcSelection['signs'];
    $calcParentTabs = sale_items_build_calc_parent_tabs($parentOptions, $selectedParents, $categoriesById);
    $parentTotalByParent = sale_items_load_parent_total_map($conceptId);
    $parentPercentByParent = sale_items_load_parent_percent_map($conceptId);
    $parentShowInFolioByParent = sale_items_load_parent_show_in_folio_map($conceptId);
    $detailProp = $detail && isset($detail['property_code']) ? (string)$detail['property_code'] : '';
    $detailPropNormalized = strtoupper(trim($detailProp));
    $categoryOptionsForDetail = array();
    foreach ($categoryList as $catOpt) {
        $optId = isset($catOpt['id_sale_item_category']) ? (int)$catOpt['id_sale_item_category'] : 0;
        if ($optId <= 0) {
            continue;
        }
        $optProp = isset($catOpt['property_code']) ? strtoupper(trim((string)$catOpt['property_code'])) : '';
        if ($detailPropNormalized !== '' && $optProp !== '' && $optProp !== $detailPropNormalized) {
            continue;
        }
        $optParentId = isset($catOpt['id_parent_sale_item_category']) ? (int)$catOpt['id_parent_sale_item_category'] : 0;
        $optParentName = ($optParentId > 0 && isset($categoriesById[$optParentId]['category_name']))
            ? (string)$categoriesById[$optParentId]['category_name']
            : '';
        $optScope = $optProp !== '' ? $optProp : '(Todas)';
        $optLabel = ($optParentName !== '' ? ($optParentName . ' / ') : '')
            . (isset($catOpt['category_name']) ? (string)$catOpt['category_name'] : ('Categoria #' . $optId))
            . ' - '
            . $optScope;
        $categoryOptionsForDetail[] = array(
            'id' => $optId,
            'label' => $optLabel
        );
    }
    if ($categoryOptionsForDetail) {
        usort($categoryOptionsForDetail, function ($a, $b) {
            return strcmp((string)$a['label'], (string)$b['label']);
        });
    }
    $hasCurrentCategory = false;
    foreach ($categoryOptionsForDetail as $opt) {
        if ((int)$opt['id'] === (int)$catId) {
            $hasCurrentCategory = true;
            break;
        }
    }
    if (!$hasCurrentCategory && $catId > 0 && isset($categoriesById[$catId])) {
        $catCurrent = $categoriesById[$catId];
        $catCurrentName = isset($catCurrent['category_name']) ? (string)$catCurrent['category_name'] : ('Categoria #' . $catId);
        $catCurrentParentId = isset($catCurrent['id_parent_sale_item_category']) ? (int)$catCurrent['id_parent_sale_item_category'] : 0;
        $catCurrentParentName = ($catCurrentParentId > 0 && isset($categoriesById[$catCurrentParentId]['category_name']))
            ? (string)$categoriesById[$catCurrentParentId]['category_name']
            : '';
        $catCurrentProp = isset($catCurrent['property_code']) && trim((string)$catCurrent['property_code']) !== ''
            ? strtoupper(trim((string)$catCurrent['property_code']))
            : '(Todas)';
        $categoryOptionsForDetail[] = array(
            'id' => $catId,
            'label' => ($catCurrentParentName !== '' ? ($catCurrentParentName . ' / ') : '') . $catCurrentName . ' - ' . $catCurrentProp
        );
    }
    $detailDerived = isset($derivedParentChildMap[$conceptId]) ? $derivedParentChildMap[$conceptId] : array();
    $detailDerived = sale_items_filter_by_property($detailDerived, $detailProp);
    $detailParentItems = array();
    foreach ($selectedParents as $pid) {
        $pid = (int)$pid;
        if ($pid <= 0) {
            continue;
        }
        $detailParentItems[] = array(
            'id' => $pid,
            'label' => isset($parentConceptLabelMap[$pid]) ? $parentConceptLabelMap[$pid] : ('Catalogo #' . $pid)
        );
    }
    $detailChildRelationsAll = sale_items_load_child_relations_for_parent($conceptId, $companyId);
    $detailChildRelations = array();
    foreach ($detailChildRelationsAll as $relRow) {
        $childProp = isset($relRow['child_property_code']) ? strtoupper(trim((string)$relRow['child_property_code'])) : '';
        if ($detailProp === '' || $childProp === '' || $childProp === strtoupper($detailProp)) {
            $detailChildRelations[] = $relRow;
        }
    }
    $selectedChildIds = array();
    foreach ($detailChildRelations as $relRow) {
        $childId = isset($relRow['id_child_sale_item_catalog']) ? (int)$relRow['id_child_sale_item_catalog'] : 0;
        if ($childId > 0) {
            $selectedChildIds[] = $childId;
        }
    }
    $selectedChildIds = array_values(array_unique($selectedChildIds));
    $childPicklistOptions = sale_items_filter_by_property($parentOptions, $detailProp);

    ob_start();
    ?>
    <div class="subtab-actions">
      <form method="post" id="concept-detail-close-<?php echo (int)$conceptId; ?>">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="concept:<?php echo (int)$conceptId; ?>">
        <button type="submit" class="button-secondary">Cerrar</button>
      </form>
    </div>
    <?php if (!$detail): ?>
      <p class="muted">No se encontr&oacute; el concepto.</p>
    <?php else: ?>
      <h3><?php echo htmlspecialchars($conceptName, ENT_QUOTES, 'UTF-8'); ?></h3>
      <div class="concept-editor-tabs js-concept-editor-tabs" data-default-tab="parents">
        <div class="concept-editor-tab-nav-wrap">
          <div class="concept-editor-tab-nav" role="tablist" aria-label="Detalle de concepto">
            <button type="button" class="button-secondary small concept-editor-tab-btn" data-concept-tab="parents">Padres</button>
            <button type="button" class="button-secondary small concept-editor-tab-btn" data-concept-tab="children">Hijos</button>
          </div>
          <div class="concept-editor-top-actions">
            <button type="submit" class="button-secondary" form="concept-clone-main-<?php echo (int)$conceptId; ?>">Clonar</button>
            <button type="submit" class="button-secondary" form="concept-update-main-<?php echo (int)$conceptId; ?>">Guardar</button>
            <button type="submit" class="button-secondary" form="concept-delete-main-<?php echo (int)$conceptId; ?>">Eliminar</button>
          </div>
        </div>
        <form method="post" class="form-grid grid-2 sale-item-form" id="concept-update-main-<?php echo (int)$conceptId; ?>">
          <input type="hidden" name="sale_items_action" value="update_item">
          <input type="hidden" name="item_id" value="<?php echo (int)$conceptId; ?>">

          <div class="concept-editor-static-panel full">
            <div class="form-grid grid-2">
              <label>Tipo de line item
                <select name="item_catalog_type" class="js-item-catalog-type">
                  <option value="sale_item" <?php echo $catalogType === 'sale_item' ? 'selected' : ''; ?>>Concepto</option>
                  <option value="payment" <?php echo $catalogType === 'payment' ? 'selected' : ''; ?>>Pago</option>
                  <option value="obligation" <?php echo $catalogType === 'obligation' ? 'selected' : ''; ?>>Obligaci&oacute;n</option>
                  <option value="income" <?php echo $catalogType === 'income' ? 'selected' : ''; ?>>Ingreso</option>
                  <option value="tax_rule" <?php echo $catalogType === 'tax_rule' ? 'selected' : ''; ?>>Impuesto</option>
                </select>
              </label>
              <label>Categoria / Subcategoria
                <select name="item_category_id" class="js-item-category" required>
                  <option value="">Selecciona</option>
                  <?php foreach ($categoryOptionsForDetail as $opt): ?>
                    <?php
                      $optId = isset($opt['id']) ? (int)$opt['id'] : 0;
                      if ($optId <= 0) {
                          continue;
                      }
                      $optLabel = isset($opt['label']) ? (string)$opt['label'] : ('Categoria #' . $optId);
                    ?>
                    <option value="<?php echo (int)$optId; ?>" <?php echo $optId === (int)$catId ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Concepto * <input type="text" name="item_name" value="<?php echo htmlspecialchars($conceptName, ENT_QUOTES, 'UTF-8'); ?>" required></label>
              <label>Precio base <input type="number" step="0.01" name="item_price" value="<?php echo htmlspecialchars(number_format(((int)$detail['default_unit_price_cents'])/100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"></label>
              <label class="checkbox-inline"><input type="checkbox" name="item_show_in_folio" <?php echo !empty($detail['show_in_folio']) ? 'checked' : ''; ?>> Mostrar en folio</label>
              <label class="checkbox-inline"><input type="checkbox" name="item_allow_negative" <?php echo !empty($detail['allow_negative']) ? 'checked' : ''; ?>> Permitir negativo</label>
              <label class="checkbox-inline"><input type="checkbox" name="item_is_active" <?php echo !empty($detail['is_active']) ? 'checked' : ''; ?>> Activo</label>
              <label class="full">Descripci&oacute;n <textarea name="item_description" rows="2"><?php echo htmlspecialchars((string)$detail['description'], ENT_QUOTES, 'UTF-8'); ?></textarea></label>
            </div>
          </div>

          <div class="concept-editor-tab-panel full" data-concept-tab-panel="parents">
            <div class="form-section full">
              <h4>Relacion con conceptos padre</h4>
              <div class="form-grid grid-2">
                <label class="full section-label">Padre</label>
                <div class="full">
                  <?php render_parent_picklist($parentOptions, $selectedParents, $categoriesById, $conceptId, 'item_parent_ids[]', 'parent-picklist-detail-' . $conceptId); ?>
                </div>
                <input type="hidden" name="item_add_to_father_total" value="<?php echo $addToFatherTotal ? '1' : '0'; ?>">
              </div>
            </div>
            <?php render_calc_components_section($calcParentChildMap, $calcSelected, $calcSigns, $calcParentTabs, $conceptId, 0, $derivedParentChildMap, $conceptId, $parentTotalByParent, $parentPercentByParent, $parentShowInFolioByParent, !empty($detail['show_in_folio']) ? 1 : 0); ?>
          </div>
        </form>
        <form method="post" id="concept-clone-main-<?php echo (int)$conceptId; ?>" class="form-inline inline-hidden">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
          <input type="hidden" name="sale_items_action" value="clone_item">
          <input type="hidden" name="item_id" value="<?php echo (int)$conceptId; ?>">
        </form>
        <form method="post" id="concept-delete-main-<?php echo (int)$conceptId; ?>" class="form-inline inline-hidden">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
          <input type="hidden" name="sale_items_action" value="delete_item">
          <input type="hidden" name="item_id" value="<?php echo (int)$conceptId; ?>">
          <input type="hidden" name="item_category_id" value="<?php echo (int)$catId; ?>">
        </form>
        <div class="concept-editor-tab-panel full" data-concept-tab-panel="children">
          <div class="form-section full">
            <h4>Relacion con conceptos hijo</h4>
            <form method="post" class="form-grid grid-2">
              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
              <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
              <input type="hidden" name="sale_items_action" value="update_child_links">
              <input type="hidden" name="relation_parent_id" value="<?php echo (int)$conceptId; ?>">
              <label class="full section-label">Hijos</label>
              <div class="full">
                <?php render_parent_picklist($childPicklistOptions, $selectedChildIds, $categoriesById, $conceptId, 'relation_child_ids[]', 'child-picklist-detail-' . $conceptId); ?>
              </div>
              <div class="form-actions full">
                <button type="submit" class="button-secondary">Guardar lista de hijos</button>
              </div>
            </form>
          </div>
          <div class="form-section full child-relations-editor">
            <h4>Edicion de relacion padre-hijo</h4>
            <p class="muted">Aqui editas cada hijo de este concepto y los campos especificos de esa relacion (incluir en padre, porcentaje y calculos avanzados).</p>
            <?php if (!$detailChildRelations): ?>
              <p class="muted">Este concepto no tiene hijos relacionados.</p>
            <?php else: ?>
              <div class="child-relation-list">
                <?php foreach ($detailChildRelations as $childRel): ?>
                  <?php
                    $childId = isset($childRel['id_child_sale_item_catalog']) ? (int)$childRel['id_child_sale_item_catalog'] : 0;
                    if ($childId <= 0) {
                        continue;
                    }
                    $childName = isset($childRel['child_item_name']) ? (string)$childRel['child_item_name'] : ('Catalogo #' . $childId);
                    $childType = isset($childRel['child_catalog_type']) ? (string)$childRel['child_catalog_type'] : 'sale_item';
                    list($childTypeLabel, $childTypeClass) = sale_items_catalog_type_meta($childType);
                    $childSubcategory = isset($childRel['child_subcategory_name']) ? trim((string)$childRel['child_subcategory_name']) : '';
                    $childCategory = isset($childRel['child_category_name']) ? trim((string)$childRel['child_category_name']) : '';
                    $childPropertyCode = isset($childRel['child_property_code']) ? trim((string)$childRel['child_property_code']) : '';
                    $childBreadcrumb = trim($childCategory . ($childCategory !== '' && $childSubcategory !== '' ? ' / ' : '') . $childSubcategory);
                    $childContext = $childBreadcrumb;
                    if ($childPropertyCode !== '') {
                        $childContext .= ($childContext !== '' ? ' - ' : '') . $childPropertyCode;
                    }
                    $childAddToFather = !empty($childRel['add_to_father_total']) ? 1 : 0;
                    $childShowInFolio = (isset($childRel['show_in_folio_relation']) && $childRel['show_in_folio_relation'] !== null)
                        ? (!empty($childRel['show_in_folio_relation']) ? 1 : 0)
                        : (!empty($childRel['child_default_show_in_folio']) ? 1 : 0);
                    $childPercentRaw = (isset($childRel['percent_value']) && $childRel['percent_value'] !== null)
                        ? (string)$childRel['percent_value']
                        : '';
                    $childCalcComponents = isset($childRel['calc_components']) && is_array($childRel['calc_components'])
                        ? array_values(array_unique(array_map('intval', $childRel['calc_components'])))
                        : array();
                    $childCalcSigns = isset($childRel['calc_signs']) && is_array($childRel['calc_signs'])
                        ? $childRel['calc_signs']
                        : array();
                    $componentOptions = array();
                    if (isset($calcParentChildMap[$conceptId]) && is_array($calcParentChildMap[$conceptId])) {
                        foreach ($calcParentChildMap[$conceptId] as $opt) {
                            $componentId = isset($opt['id']) ? (int)$opt['id'] : 0;
                            if ($componentId <= 0 || $componentId === $childId || $componentId === (int)$conceptId) {
                                continue;
                            }
                            $componentOptions[$componentId] = isset($opt['label']) ? (string)$opt['label'] : ('Componente ' . $componentId);
                        }
                    }
                    foreach ($childCalcComponents as $componentId) {
                        $componentId = (int)$componentId;
                        if ($componentId <= 0 || isset($componentOptions[$componentId])) {
                            continue;
                        }
                        if (isset($parentConceptLabelMap[$componentId])) {
                            $componentOptions[$componentId] = 'Concepto: ' . (string)$parentConceptLabelMap[$componentId];
                        } else {
                            $componentOptions[$componentId] = 'Componente #' . $componentId;
                        }
                    }
                    asort($componentOptions);
                  ?>
                  <form method="post" class="form-grid grid-2 child-relation-form">
                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                    <?php sale_items_render_filter_hiddens($filters, $conceptFilters, $lineItemFilters); ?>
                    <input type="hidden" name="sale_items_action" value="update_child_relation">
                    <input type="hidden" name="relation_parent_id" value="<?php echo (int)$conceptId; ?>">
                    <input type="hidden" name="relation_child_id" value="<?php echo (int)$childId; ?>">
                    <div class="full child-relation-head">
                      <span class="type-pill <?php echo htmlspecialchars($childTypeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($childTypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                      <strong><?php echo htmlspecialchars($childName, ENT_QUOTES, 'UTF-8'); ?></strong>
                      <?php if ($childContext !== ''): ?>
                        <span class="muted"><?php echo htmlspecialchars($childContext, ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </div>
                    <label class="checkbox-inline">
                      <input type="checkbox" name="relation_add_to_father_total" value="1" <?php echo $childAddToFather ? 'checked' : ''; ?>>
                      Incluir en total del padre
                    </label>
                    <label class="checkbox-inline">
                      <input type="checkbox" name="relation_show_in_folio" value="1" <?php echo $childShowInFolio ? 'checked' : ''; ?>>
                      Mostrar en folio (relaci&oacute;n)
                    </label>
                    <label>% padre
                      <input type="number" step="any" name="relation_percent_value" value="<?php echo htmlspecialchars($childPercentRaw, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Vacio = no porcentual">
                    </label>
                    <details class="full child-relation-calc">
                      <summary class="section-summary">Calculos avanzados de esta relacion</summary>
                      <?php if (!$componentOptions): ?>
                        <p class="muted">No hay componentes disponibles para esta relacion.</p>
                      <?php else: ?>
                        <div class="calc-grid child-calc-grid">
                          <div class="calc-group">
                            <div class="calc-group-title">Componentes</div>
                            <?php foreach ($componentOptions as $componentId => $componentLabel): ?>
                              <?php
                                $componentId = (int)$componentId;
                                if ($componentId <= 0) {
                                    continue;
                                }
                                $checked = in_array($componentId, $childCalcComponents, true);
                                $signValue = isset($childCalcSigns[$componentId]) && (int)$childCalcSigns[$componentId] < 0 ? -1 : 1;
                              ?>
                              <div class="calc-row">
                                <label class="calc-item">
                                  <input type="checkbox" name="relation_components[]" value="<?php echo (int)$componentId; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                  <span><?php echo htmlspecialchars($componentLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </label>
                                <select class="calc-sign" name="relation_sign[<?php echo (int)$componentId; ?>]">
                                  <option value="1" <?php echo $signValue >= 0 ? 'selected' : ''; ?>>+</option>
                                  <option value="-1" <?php echo $signValue < 0 ? 'selected' : ''; ?>>-</option>
                                </select>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endif; ?>
                    </details>
                    <div class="form-actions full">
                      <button type="submit" class="button-secondary">Guardar relacion</button>
                    </div>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <?php
    $dynamicTabs[] = array(
        'key' => 'concept:' . $conceptId,
        'title' => $conceptName,
        'panel_id' => 'concept-detail-' . $conceptId,
        'close_form_id' => 'concept-detail-close-' . $conceptId,
        'content' => ob_get_clean()
    );
}

$staticTabs = array(
    array(
        'id' => 'general',
        'title' => 'General',
        'content' => $generalContent
    ),
    array(
        'id' => 'tree',
        'title' => 'Arbol',
        'content' => $treeContent
    )
);

pms_render_subtabs($moduleKey, $subtabState, $staticTabs, $dynamicTabs);
?>
<style>
.page-header h2 { margin: 0 0 4px 0; }
.page-header p { margin: 0; }
.filters { margin: 12px 0; }
.panel { border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 14px; margin-top: 12px; }
.panel-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.panel-header .header-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.inline-details { margin-left: 0; }
.inline-details summary { list-style: none; display: inline-flex; align-items: center; }
.inline-details summary::-webkit-details-marker { display: none; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
details summary { cursor: pointer; }
details summary:not(.button-secondary) { color: #8ecaff; }
.inline-details summary.button-secondary { padding: 6px 12px; border-radius: 10px; line-height: 1.2; }
.tax-panel { grid-column: 1 / -1; }
.sale-item-form { gap: 14px; }
.sale-item-form .full { grid-column: 1 / -1; }
.sale-item-form .form-section { grid-column: 1 / -1; }
.sale-item-form label.checkbox-inline { display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: flex-start !important; gap: 8px; }
.sale-item-form label.checkbox-inline input { margin: 0; }
.concept-editor-tabs { display: grid; gap: 10px; }
.concept-editor-tab-nav-wrap { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; flex-wrap: wrap; border-bottom: 1px solid rgba(255,255,255,0.12); padding-bottom: 0; }
.concept-editor-tab-nav { display: flex; align-items: flex-end; gap: 0; flex-wrap: wrap; }
.concept-editor-tab-btn { min-width: 110px; border-radius: 10px 10px 0 0; margin-right: 4px; border-bottom-color: rgba(255,255,255,0.18); background: rgba(7,18,35,0.78); }
.concept-editor-tab-btn.is-active { border-color: rgba(88,199,255,0.72); border-bottom-color: rgba(8,16,28,0.95); background: rgba(8,16,28,0.95); color: #dff5ff; }
.concept-editor-top-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding-bottom: 6px; }
.concept-editor-static-panel { border: 1px solid rgba(255,255,255,0.1); border-top: none; border-radius: 0 8px 8px 8px; padding: 12px; background: rgba(8,16,28,0.45); }
.concept-editor-tab-panel { display: none; border: 1px solid rgba(255,255,255,0.1); border-top: none; border-radius: 0 8px 8px 8px; padding: 12px; background: rgba(8,16,28,0.45); }
.concept-editor-tab-panel.is-active { display: block; }
.concept-editor-static-panel > .form-grid { margin-top: 0; }
.concept-editor-tab-panel > .form-grid { margin-top: 0; }
.inline-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.inline-hidden { display: none; }
.form-section { border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 12px 14px; background: linear-gradient(160deg, rgba(255,255,255,0.03), rgba(0,0,0,0.08)); }
.form-section h4 { margin: 0 0 8px; font-size: 13px; letter-spacing: 0.6px; text-transform: uppercase; color: rgba(255,255,255,0.82); }
.form-section .form-grid { margin-top: 8px; }
.section-label { font-size: 12px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255,255,255,0.65); }
.advanced-placeholder { border: 1px dashed rgba(255,255,255,0.2); border-radius: 8px; padding: 10px 12px; color: rgba(255,255,255,0.6); font-style: italic; background: rgba(255,255,255,0.02); }
.calc-panel { border: 1px solid rgba(120,190,255,0.2); border-radius: 10px; padding: 10px 12px; background: rgba(15,25,40,0.45); }
.calc-panel .calc-header { display: flex; flex-direction: column; gap: 4px; margin-bottom: 8px; }
.calc-panel .calc-header h5 { margin: 0; font-size: 13px; letter-spacing: 0.6px; text-transform: uppercase; color: rgba(255,255,255,0.82); }
.calc-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.calc-tab { border: 1px solid rgba(120,190,255,0.2); background: rgba(12,20,34,0.6); color: rgba(255,255,255,0.85); padding: 4px 10px; border-radius: 999px; cursor: pointer; font-size: 12px; }
.calc-tab.is-active { background: rgba(88,199,255,0.2); border-color: rgba(88,199,255,0.6); }
.calc-panel .calc-grid { display: grid; gap: 8px; max-height: 260px; overflow: auto; padding-right: 4px; }
.calc-panel .calc-group { border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 8px; background: rgba(0,0,0,0.14); }
.calc-panel .calc-group-title { font-size: 12px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255,255,255,0.65); margin-bottom: 6px; }
.calc-panel .calc-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 8px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.03); }
.calc-panel .calc-row + .calc-row { margin-top: 6px; }
.calc-panel .calc-item { display: flex !important; align-items: center !important; gap: 8px; flex: 1; }
.calc-panel .calc-item input { width: 16px; height: 16px; margin: 0; accent-color: #58c7ff; }
.calc-panel .calc-sign { min-width: 56px; max-width: 72px; }
.calc-panel .calc-empty { padding: 6px 10px; margin-bottom: 8px; }
.calc-parent-total { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 10px; padding: 6px 10px; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; background: rgba(255,255,255,0.03); }
.calc-panel.is-disabled { opacity: 0.7; }
.calc-panel.is-disabled .calc-grid { display: none; }
.calc-related { margin-top: 10px; border: 1px solid rgba(88,199,255,0.18); border-radius: 8px; padding: 8px; background: rgba(8,16,28,0.4); }
.calc-related-title { font-size: 12px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255,255,255,0.7); margin-bottom: 6px; }
.calc-related-body { display: flex; flex-wrap: wrap; gap: 6px; }
.calc-related .child-chip { border: 1px solid rgba(255,255,255,0.18); border-radius: 999px; padding: 4px 10px; font-size: 12px; color: rgba(255,255,255,0.9); background: rgba(255,255,255,0.05); }
.concept-derivative > summary { list-style: none; font-size: 13px; letter-spacing: 0.6px; text-transform: uppercase; color: rgba(255,255,255,0.82); }
.concept-derivative > summary::-webkit-details-marker { display: none; }
.concept-derivative > summary.section-summary { display: flex; align-items: center; gap: 8px; }
.concept-derivative > summary.section-summary::after { content: '▼'; font-size: 11px; opacity: 0.6; margin-left: auto; transition: transform 0.2s ease; }
.concept-derivative[open] > summary.section-summary::after { transform: rotate(180deg); }
.parent-picklist { display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center; }
.parent-picklist .picklist-column { display: flex; flex-direction: column; gap: 6px; }
.parent-picklist .picklist-label { font-size: 12px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255,255,255,0.65); }
.parent-picklist .picklist-select { min-height: 180px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.9); border-radius: 8px; padding: 6px; }
.parent-picklist .picklist-select option { padding: 4px 6px; }
.parent-picklist .picklist-actions { display: flex; flex-direction: column; gap: 8px; align-items: center; }
.parent-picklist .picklist-btn { min-width: 42px; }
.parent-picklist .picklist-hidden { display: none; }
.checklist-grid { display: grid; grid-template-columns: 1fr; gap: 6px; max-height: 220px; overflow: auto; padding: 6px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.12); }
.checklist-grid::-webkit-scrollbar { width: 10px; }
.checklist-grid::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 10px; }
.checklist-grid::-webkit-scrollbar-thumb { background: rgba(130,200,255,0.6); border-radius: 10px; border: 2px solid rgba(0,0,0,0.2); }
.checklist-grid { scrollbar-width: thin; scrollbar-color: rgba(130,200,255,0.6) rgba(0,0,0,0.2); }
.checklist-item { display: flex !important; flex-direction: row !important; align-items: center !important; gap: 10px; padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.03); transition: background 0.2s ease, border-color 0.2s ease; }
.checklist-item:hover { background: rgba(255,255,255,0.08); border-color: rgba(140,210,255,0.5); }
.checklist-item input { width: 16px; height: 16px; accent-color: #58c7ff; margin: 0; align-self: center; }
.checklist-item span { line-height: 1.3; }
.checklist-item .type-pill { display: inline-flex; align-items: center; justify-content: center; padding: 2px 8px; border-radius: 999px; font-size: 11px; letter-spacing: 0.3px; text-transform: uppercase; border: 1px solid transparent; color: rgba(255,255,255,0.92); }
.checklist-item .type-text { color: rgba(255,255,255,0.92); }
.checklist-item .type-concept { background: rgba(76,178,255,0.16); border-color: rgba(76,178,255,0.4); }
.checklist-item .type-payment { background: rgba(78,210,150,0.16); border-color: rgba(78,210,150,0.45); }
.checklist-item .type-tax { background: rgba(255,181,71,0.16); border-color: rgba(255,181,71,0.45); }
.checklist-item .type-obligation { background: rgba(231,107,217,0.16); border-color: rgba(231,107,217,0.45); }
.checklist-item .type-income { background: rgba(164,122,255,0.16); border-color: rgba(164,122,255,0.45); }
.checklist-empty { padding: 6px 10px; color: rgba(255,255,255,0.6); }
.panel-header.spaced form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.panel-header.spaced input[type="text"] { min-width: 200px; }
.button-secondary.small { padding: 4px 8px; font-size: 12px; }
.tax-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: center; gap: 6px 12px; }
.tax-grid label { display: flex; align-items: center; gap: 6px; margin: 2px 0; }
.subcat-actions { margin: 8px 0 4px; }
.concept-list { display: flex; flex-direction: column; gap: 8px; width: 100%; }
.concept-list-actions { display: flex; justify-content: flex-end; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 6px; width: 100%; }
.concept-list-actions .form-inline { margin: 0; }
.concept-list-rows { display: flex; flex-direction: column; gap: 6px; }
.concept-list-rows form { margin: 0; }
.concept-row { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: start; margin-bottom: 0; width: 100%; }
.concept-row .link-button { text-align: left; }
.concept-open-form { width: 100%; margin: 0; }
.concept-clone-form { margin: 0; display: flex; align-items: flex-start; }
.concept-item-head { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.concept-item { display: flex; flex-direction: column; gap: 6px; width: 100%; }
.concept-children { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-top: 4px; }
.child-group { border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 6px 8px; background: rgba(0,0,0,0.12); }
.child-title { font-size: 11px; letter-spacing: 0.4px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 4px; }
.child-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.child-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px; border-radius: 999px; background: rgba(120,190,255,0.15); border: 1px solid rgba(120,190,255,0.35); font-size: 12px; color: rgba(255,255,255,0.9); }
.child-empty { font-size: 12px; color: rgba(255,255,255,0.55); }
.child-relations-editor .child-relation-list { display: grid; gap: 10px; }
.child-relation-form { border: 1px solid rgba(120,190,255,0.22); border-radius: 10px; padding: 10px; background: rgba(8,16,28,0.42); }
.child-relation-form .child-relation-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.child-relation-form .child-relation-head .muted { margin-left: auto; font-size: 12px; }
.child-relation-form .child-relation-calc { margin-top: 4px; }
.child-relation-form .child-calc-grid { max-height: 220px; overflow: auto; padding-right: 4px; }
.concept-empty { grid-column: 1 / -1; color: rgba(255,255,255,0.65); }
.link-button { background: none; border: none; padding: 0; color: #8ecaff; cursor: pointer; }
.link-button:hover { text-decoration: underline; }
.subcategory-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 12px; }
.delete-category-form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; }
.delete-inline { margin: 0; }
.category-details-row td { padding-top: 0; }
.category-details { margin-top: 8px; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 12px; background: rgba(255,255,255,0.02); }
.category-details table td { vertical-align: top; }
.category-details > summary { font-weight: 600; }
.category-details-content { margin-top: 12px; }
.subcategory-create-toggle { margin-top: 12px; }
.subcategory-create-toggle > summary { display: inline-block; }
.subcategory-panel { margin-top: 12px; }
.subcategory-panel .panel { margin-top: 12px; }
.line-item-concept-wrap { display: flex; align-items: center; min-height: 26px; }
.concept-tree-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.concept-tree-toolbar label { margin: 0; }
.concept-tree-zoom-actions { display: flex; gap: 6px; align-items: center; }
.concept-tree-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 12px; align-items: stretch; }
.concept-tree-canvas-wrap { min-height: 620px; border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; background: radial-gradient(circle at 20% 10%, rgba(105,183,255,0.12), rgba(7,15,25,0.92)); overflow: hidden; position: relative; }
.concept-tree-svg { width: 100%; height: 100%; min-height: 620px; display: block; cursor: grab; user-select: none; }
.concept-tree-svg.is-panning { cursor: grabbing; }
.concept-tree-side { border: 1px solid rgba(255,255,255,0.12); border-radius: 10px; padding: 10px; background: rgba(8,16,28,0.6); overflow: auto; max-height: 620px; }
.concept-tree-side h4 { margin: 0 0 8px 0; }
.concept-tree-detail { display: grid; gap: 6px; margin-bottom: 10px; }
.concept-tree-relations { display: grid; gap: 8px; }
.concept-tree-rel-card { border: 1px solid rgba(120,190,255,0.25); border-radius: 8px; padding: 8px; background: rgba(11,20,34,0.68); }
.concept-tree-rel-card strong { display: block; margin-bottom: 4px; }
.concept-tree-rel-card .muted { font-size: 12px; }
.concept-tree-node rect { fill: rgba(15,27,44,0.95); stroke: rgba(130,204,255,0.55); stroke-width: 1.3; }
.concept-tree-node.is-inactive rect { fill: rgba(30,30,30,0.85); stroke: rgba(200,200,200,0.35); stroke-dasharray: 4 3; }
.concept-tree-node.is-selected rect { stroke: #59deff; stroke-width: 2.6; }
.concept-tree-node.is-context rect { stroke: #7fddff; stroke-width: 2; }
.concept-tree-node.is-dimmed { opacity: 0.16; }
.concept-tree-node text { fill: #dff3ff; font-size: 12px; pointer-events: none; }
.concept-tree-node .node-title { font-size: 13px; font-weight: 700; }
.concept-tree-edge { stroke: rgba(141,212,255,0.6); stroke-width: 1.4; fill: none; }
.concept-tree-edge.is-muted { opacity: 0.22; }
.concept-tree-edge.is-dimmed { opacity: 0.08; }
.concept-tree-edge.is-selected { stroke: #5ee2ff; stroke-width: 2.3; opacity: 1; }
.concept-tree-edge-label { fill: #b8e9ff; font-size: 10px; pointer-events: auto; cursor: pointer; }
.concept-tree-edge-label.is-dimmed { opacity: 0.1; }
.concept-tree-edge-label.is-selected { fill: #ecfcff; font-weight: 700; opacity: 1; }
.concept-tree-empty { padding: 12px; border: 1px dashed rgba(255,255,255,0.2); border-radius: 8px; color: rgba(255,255,255,0.75); }
@media (max-width: 1180px) {
  .concept-tree-layout { grid-template-columns: 1fr; }
  .concept-tree-side { max-height: none; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.js-concept-editor-tabs').forEach(function (container) {
    var buttons = Array.from(container.querySelectorAll('.concept-editor-tab-btn[data-concept-tab]'));
    var panels = Array.from(container.querySelectorAll('.concept-editor-tab-panel[data-concept-tab-panel]'));
    if (!buttons.length || !panels.length) return;

    var activateTab = function (tabKey) {
      var normalized = (tabKey || '').toString().trim();
      if (!normalized && buttons.length) {
        normalized = buttons[0].getAttribute('data-concept-tab') || '';
      }
      buttons.forEach(function (btn) {
        var key = btn.getAttribute('data-concept-tab') || '';
        var isActive = key === normalized;
        btn.classList.toggle('is-active', isActive);
      });
      panels.forEach(function (panel) {
        var key = panel.getAttribute('data-concept-tab-panel') || '';
        panel.classList.toggle('is-active', key === normalized);
      });
    };

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        activateTab(btn.getAttribute('data-concept-tab') || '');
      });
    });

    activateTab(container.getAttribute('data-default-tab') || '');
  });

  document.querySelectorAll('.sale-item-form').forEach(function (form) {
    var typeSelect = form.querySelector('.js-item-catalog-type');
    var categorySelect = form.querySelector('.js-item-category');
    if (!typeSelect || !categorySelect) return;
    var syncCategoryRequired = function () {
      if (typeSelect.value === 'sale_item') {
        categorySelect.required = true;
      } else {
        categorySelect.required = false;
      }
    };
    typeSelect.addEventListener('change', syncCategoryRequired);
    syncCategoryRequired();
  });

  document.querySelectorAll('.parent-picklist').forEach(function (picklist) {
    var selected = picklist.querySelector('[data-picklist-selected]');
    var available = picklist.querySelector('[data-picklist-available]');
    var hiddenWrap = picklist.querySelector('[data-parent-selected]');
    var inputName = picklist.dataset.inputName || 'item_parent_ids[]';

    var rebuildHidden = function () {
      if (!hiddenWrap || !selected) return;
      hiddenWrap.innerHTML = '';
      var frag = document.createDocumentFragment();
      Array.from(selected.options).forEach(function (opt) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = inputName;
        input.value = opt.value;
        input.dataset.parentLabel = opt.dataset.label || (opt.textContent || '').trim();
        frag.appendChild(input);
      });
      hiddenWrap.appendChild(frag);
    };

    var moveOptions = function (from, to) {
      if (!from || !to) return;
      Array.from(from.selectedOptions).forEach(function (opt) {
        opt.selected = false;
        to.appendChild(opt);
      });
      rebuildHidden();
      picklist.dispatchEvent(new CustomEvent('parent-picklist:change', { bubbles: true }));
    };

    picklist.addEventListener('click', function (event) {
      var btn = event.target.closest('[data-picklist-move]');
      if (!btn) return;
      var action = btn.dataset.picklistMove;
      if (action === 'add') {
        moveOptions(available, selected);
      } else if (action === 'remove') {
        moveOptions(selected, available);
      }
    });

    if (available) {
      available.addEventListener('dblclick', function () {
        moveOptions(available, selected);
      });
    }
    if (selected) {
      selected.addEventListener('dblclick', function () {
        moveOptions(selected, available);
      });
    }
    rebuildHidden();
  });

  document.querySelectorAll('.sale-item-form').forEach(function (form) {
    var panel = form.querySelector('[data-calc-panel]');
    if (!panel) return;
    var getParentInputs = function () {
      return form.querySelectorAll('.parent-picklist [data-parent-selected] input[name="item_parent_ids[]"]');
    };
    var calcMap = {};
    var selectedMap = {};
    var signsMap = {};
    var parentTotalMap = {};
    var parentPercentMap = {};
    var parentShowMap = {};
    var tabs = [];
    var childMap = {};
    var defaultShowInFolio = 1;
    try {
      calcMap = JSON.parse(panel.dataset.calcMap || '{}');
      selectedMap = JSON.parse(panel.dataset.calcSelected || '{}');
      signsMap = JSON.parse(panel.dataset.calcSigns || '{}');
      parentTotalMap = JSON.parse(panel.dataset.parentTotal || '{}');
      parentPercentMap = JSON.parse(panel.dataset.parentPercent || '{}');
      parentShowMap = JSON.parse(panel.dataset.parentShow || '{}');
      tabs = JSON.parse(panel.dataset.calcTabs || '[]');
      childMap = JSON.parse(panel.dataset.calcChildren || '{}');
      defaultShowInFolio = parseInt(panel.dataset.defaultShow || '1', 10) ? 1 : 0;
    } catch (err) {
      calcMap = {};
      selectedMap = {};
      signsMap = {};
      parentTotalMap = {};
      parentPercentMap = {};
      parentShowMap = {};
      tabs = [];
      childMap = {};
      defaultShowInFolio = 1;
    }
    var excludeId = parseInt(panel.dataset.calcExclude || '0', 10) || 0;
    var currentItemId = parseInt(panel.dataset.calcCurrent || '0', 10) || 0;
    var activeParentId = 0;
    var activeFromHidden = 0;
    if (tabs && tabs.length) activeParentId = parseInt(tabs[0].id || '0', 10) || 0;
    var parentHidden = panel.querySelector('input[name="calc_parent_id"]');
    var parentTotalHidden = panel.querySelector('input[name="parent_total_state_json"]');
    var parentPercentHidden = panel.querySelector('input[name="parent_percent_state_json"]');
    var parentShowHidden = panel.querySelector('input[name="parent_show_in_folio_state_json"]');
    var parentAddBox = panel.querySelector('.js-parent-add-to-father');
    var parentIndependentBox = panel.querySelector('.js-parent-independent');
    var parentShowBox = panel.querySelector('.js-parent-show-in-folio');
    var parentPercentInput = panel.querySelector('.js-parent-percent');
    if (parentHidden && parseInt(parentHidden.value || '0', 10) > 0) {
      activeFromHidden = parseInt(parentHidden.value || '0', 10) || 0;
      activeParentId = activeFromHidden || activeParentId;
    }

    var tabsWrap = panel.querySelector('.calc-tabs');
    if (!tabsWrap) {
      var header = panel.querySelector('.calc-header');
      if (header) {
        tabsWrap = document.createElement('div');
        tabsWrap.className = 'calc-tabs';
        header.appendChild(tabsWrap);
      }
    }

    var parentLabelFromInput = function (input) {
      if (!input) return '';
      var label = (input.dataset.parentLabel || input.dataset.label || '').trim();
      if (label) return label;
      var item = input.closest('.checklist-item');
      if (!item) return '';
      var pill = item.querySelector('.type-pill');
      var text = item.querySelector('.type-text');
      if (!pill || !text) return '';
      var typeText = (pill.textContent || '').trim();
      var labelText = (text.textContent || '').trim();
      if (!typeText || !labelText) return '';
      return typeText + ': ' + labelText;
    };

    var collectParentTabs = function () {
      var out = [];
      getParentInputs().forEach(function (input) {
        var pid = parseInt(input.value || '0', 10) || 0;
        if (pid <= 0) return;
        out.push({
          id: pid,
          label: parentLabelFromInput(input) || ('Concepto: ' + pid)
        });
      });
      return out;
    };

    var renderTabs = function () {
      if (!tabsWrap) return;
      tabsWrap.innerHTML = '';
      tabs.forEach(function (tab) {
        var pid = parseInt(tab.id || '0', 10) || 0;
        if (pid <= 0) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'calc-tab' + (pid === activeParentId ? ' is-active' : '');
        btn.dataset.calcParent = String(pid);
        btn.textContent = tab.label || ('Concepto: ' + pid);
        tabsWrap.appendChild(btn);
      });
      tabsWrap.style.display = tabs.length ? 'flex' : 'none';
    };

    var syncTabsFromParents = function () {
      snapshotCurrent();
      tabs = collectParentTabs();
      var allowedParent = {};
      tabs.forEach(function (tab) {
        var pid = parseInt(tab.id || '0', 10) || 0;
        if (!pid) return;
        allowedParent[pid] = true;
        if (parentTotalMap[pid] == null) {
          parentTotalMap[pid] = 1;
        }
        if (!(pid in parentPercentMap)) {
          parentPercentMap[pid] = null;
        }
        if (!(pid in parentShowMap)) {
          parentShowMap[pid] = defaultShowInFolio ? 1 : 0;
        }
      });
      Object.keys(parentTotalMap).forEach(function (key) {
        var pid = parseInt(key, 10) || 0;
        if (!pid || !allowedParent[pid]) {
          delete parentTotalMap[key];
        }
      });
      Object.keys(parentPercentMap).forEach(function (key) {
        var pid = parseInt(key, 10) || 0;
        if (!pid || !allowedParent[pid]) {
          delete parentPercentMap[key];
        }
      });
      Object.keys(parentShowMap).forEach(function (key) {
        var pid = parseInt(key, 10) || 0;
        if (!pid || !allowedParent[pid]) {
          delete parentShowMap[key];
        }
      });
      if (!tabs.length) {
        activeParentId = 0;
      } else {
        var hasActive = tabs.some(function (tab) { return (parseInt(tab.id, 10) || 0) === activeParentId; });
        if (!hasActive) {
          if (activeFromHidden > 0 && tabs.some(function (tab) { return (parseInt(tab.id, 10) || 0) === activeFromHidden; })) {
            activeParentId = activeFromHidden;
          } else {
            activeParentId = parseInt(tabs[0].id || '0', 10) || 0;
          }
        }
      }
      renderTabs();
      updateState();
    }
    var pendingSync = false;
    var requestSyncTabs = function () {
      if (pendingSync) return;
      pendingSync = true;
      requestAnimationFrame(function () {
        pendingSync = false;
        syncTabsFromParents();
      });
    };

    var snapshotCurrent = function () {
      if (!activeParentId) return;
      var hasInputs = panel.querySelector('input[name="calc_components[]"]');
      if (!hasInputs) return;
      var selected = [];
      var signs = {};
      panel.querySelectorAll('input[name="calc_components[]"]').forEach(function (cb) {
        var id = parseInt(cb.value, 10) || 0;
        if (id <= 0) return;
        if (cb.checked) selected.push(id);
      });
      panel.querySelectorAll('select.calc-sign').forEach(function (sel) {
        var match = sel.name.match(/calc_sign\[(\d+)\]/);
        if (!match) return;
        var id = parseInt(match[1], 10) || 0;
        if (id <= 0) return;
        signs[id] = parseInt(sel.value, 10) || 1;
      });
      if (selected.length) {
        selectedMap[activeParentId] = selected.slice();
        signsMap[activeParentId] = signs;
      } else {
        selectedMap[activeParentId] = [];
        signsMap[activeParentId] = {};
      }
      if (parentAddBox && parentIndependentBox) {
        parentTotalMap[activeParentId] = parentAddBox.checked ? 1 : 0;
      }
      if (parentPercentInput) {
        var raw = (parentPercentInput.value || '').trim();
        if (raw === '') {
          parentPercentMap[activeParentId] = null;
        } else {
          var parsed = parseFloat(raw.replace(',', '.'));
          parentPercentMap[activeParentId] = isNaN(parsed) ? null : parsed;
        }
      }
    };

    var renderParentTotalToggles = function () {
      if (!parentAddBox || !parentIndependentBox || !parentPercentInput || !parentShowBox) return;
      if (!activeParentId) {
        parentAddBox.checked = true;
        parentIndependentBox.checked = false;
        parentShowBox.checked = !!defaultShowInFolio;
        parentPercentInput.value = '';
        parentAddBox.disabled = true;
        parentIndependentBox.disabled = true;
        parentShowBox.disabled = true;
        parentPercentInput.disabled = true;
        return;
      }
      var value = parentTotalMap[activeParentId];
      if (value == null) value = 1;
      parentAddBox.checked = !!value;
      parentIndependentBox.checked = !value;
      var showValue = (activeParentId in parentShowMap) ? parentShowMap[activeParentId] : defaultShowInFolio;
      parentShowBox.checked = !!showValue;
      var percentValue = (activeParentId in parentPercentMap) ? parentPercentMap[activeParentId] : null;
      parentPercentInput.value = (percentValue === null || typeof percentValue === 'undefined' || percentValue === '') ? '' : String(percentValue);
      parentAddBox.disabled = false;
      parentIndependentBox.disabled = false;
      parentShowBox.disabled = false;
      parentPercentInput.disabled = false;
    };

    var renderCalcRows = function (parentId) {
      var currentSelected = selectedMap[parentId] || [];
      var currentSigns = signsMap[parentId] || {};
      var allowed = {};
      if (parentId && calcMap[parentId]) {
        var items = Array.isArray(calcMap[parentId]) ? calcMap[parentId] : Object.values(calcMap[parentId] || {});
        items.forEach(function (item) {
          if (!item || !item.id) return;
          allowed[item.id] = item;
        });
      }

      var grid = panel.querySelector('.calc-grid');
      grid.innerHTML = '';
      if (excludeId > 0 && allowed[excludeId]) {
        delete allowed[excludeId];
      }
      var allowedItems = Object.keys(allowed).map(function (key) {
        return allowed[key];
      });
      allowedItems.sort(function (a, b) {
        return (a.label || '').localeCompare(b.label || '');
      });
      if (!allowedItems.length) {
        return;
      }
      var group = document.createElement('div');
      group.className = 'calc-group';
      var title = document.createElement('div');
      title.className = 'calc-group-title';
      title.textContent = 'Componentes';
      group.appendChild(title);
      allowedItems.forEach(function (item) {
        var cid = parseInt(item.id, 10) || 0;
        if (cid <= 0) return;
        var row = document.createElement('div');
        row.className = 'calc-row';
        var label = document.createElement('label');
        label.className = 'calc-item';
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'calc_components[]';
        checkbox.value = String(cid);
        checkbox.checked = currentSelected.indexOf(cid) >= 0;
        var text = document.createElement('span');
        text.textContent = item.label || ('Concepto ' + cid);
        label.appendChild(checkbox);
        label.appendChild(text);
        var select = document.createElement('select');
        select.className = 'calc-sign';
        select.name = 'calc_sign[' + cid + ']';
        var optPlus = document.createElement('option');
        optPlus.value = '1';
        optPlus.textContent = '+';
        var optMinus = document.createElement('option');
        optMinus.value = '-1';
        optMinus.textContent = '-';
        var signValue = currentSigns[cid] != null ? currentSigns[cid] : 1;
        if (signValue < 0) {
          optMinus.selected = true;
        } else {
          optPlus.selected = true;
        }
        select.appendChild(optPlus);
        select.appendChild(optMinus);
        row.appendChild(label);
        row.appendChild(select);
        group.appendChild(row);
      });
      grid.appendChild(group);
    };

    var renderRelatedChildren = function () {
      var wrap = panel.querySelector('.calc-related');
      var body = panel.querySelector('.calc-related-body');
      if (!wrap || !body) return;
      body.innerHTML = '';
      if (!currentItemId || !childMap[currentItemId] || !childMap[currentItemId].length) {
        wrap.style.display = 'none';
        return;
      }
      wrap.style.display = 'block';
      childMap[currentItemId].forEach(function (item) {
        var chip = document.createElement('span');
        chip.className = 'child-chip';
        chip.textContent = item.label || ('Concepto ' + item.id);
        body.appendChild(chip);
      });
    };

    var updateHidden = function () {
      snapshotCurrent();
      var currentSelected = selectedMap[activeParentId] || [];
      var currentSigns = signsMap[activeParentId] || {};
      var ids = [];
      var checkedMap = {};
      var signsOut = {};
      currentSelected.forEach(function (id) {
        ids.push(id);
        checkedMap[id] = true;
        var sign = currentSigns[id];
        signsOut[id] = (sign == null ? 1 : sign);
      });
      var csv = panel.querySelector('input[name="calc_components_csv"]');
      if (csv) csv.value = ids.join(',');
      var json = panel.querySelector('input[name="calc_sign_json"]');
      if (json) json.value = JSON.stringify(signsOut);
      var state = panel.querySelector('input[name="calc_state_json"]');
      if (state) {
        var mapOut = {};
        Object.keys(selectedMap).forEach(function (parentKey) {
          var pid = parseInt(parentKey, 10) || 0;
          if (!pid) return;
          var pSelected = selectedMap[parentKey] || [];
          var pSigns = signsMap[parentKey] || {};
          if (!pSelected.length) {
            mapOut[pid] = {};
            return;
          }
          var pOut = {};
          pSelected.forEach(function (id) {
            var cid = parseInt(id, 10) || 0;
            if (!cid) return;
            var sign = pSigns[cid];
            pOut[cid] = (sign == null ? 1 : sign);
          });
          mapOut[pid] = pOut;
        });
        state.value = JSON.stringify(mapOut);
      }
      if (parentTotalHidden) {
        var totalsOut = {};
        Object.keys(parentTotalMap).forEach(function (parentKey) {
          var pid = parseInt(parentKey, 10) || 0;
          if (!pid) return;
          totalsOut[pid] = parentTotalMap[parentKey] ? 1 : 0;
        });
        parentTotalHidden.value = JSON.stringify(totalsOut);
      }
      if (parentPercentHidden) {
        var percentOut = {};
        Object.keys(parentPercentMap).forEach(function (parentKey) {
          var pid = parseInt(parentKey, 10) || 0;
          if (!pid) return;
          var value = parentPercentMap[parentKey];
          if (value === '' || value === null || typeof value === 'undefined') {
            percentOut[pid] = null;
          } else {
            var parsed = parseFloat(value);
            percentOut[pid] = isNaN(parsed) ? null : parsed;
          }
        });
        parentPercentHidden.value = JSON.stringify(percentOut);
      }
      if (parentShowHidden) {
        var showOut = {};
        Object.keys(parentShowMap).forEach(function (parentKey) {
          var pid = parseInt(parentKey, 10) || 0;
          if (!pid) return;
          showOut[pid] = parentShowMap[parentKey] ? 1 : 0;
        });
        parentShowHidden.value = JSON.stringify(showOut);
      }
      if (parentHidden) parentHidden.value = activeParentId ? String(activeParentId) : '0';
    };

    var updateState = function () {
      var hasParent = activeParentId > 0;
      panel.classList.toggle('is-disabled', !hasParent);
      var empty = panel.querySelector('.calc-empty');
      if (empty) empty.style.display = hasParent ? 'none' : 'block';
      panel.querySelectorAll('input, select').forEach(function (el) {
        if (el.name === 'calc_update' || el.name === 'calc_components_csv' || el.name === 'calc_sign_json' || el.name === 'calc_state_json' || el.name === 'calc_parent_id' || el.name === 'parent_total_state_json' || el.name === 'parent_percent_state_json' || el.name === 'parent_show_in_folio_state_json') return;
        el.disabled = !hasParent;
      });
      renderParentTotalToggles();
      if (hasParent) {
        renderCalcRows(activeParentId);
        renderRelatedChildren();
        updateHidden();
      } else {
        var grid = panel.querySelector('.calc-grid');
        if (grid) grid.innerHTML = '';
        renderRelatedChildren();
        updateHidden();
      }
    };

    panel.addEventListener('change', function (event) {
      if (event.target && (event.target.matches('input[name="calc_components[]"]') || event.target.matches('select.calc-sign'))) {
        updateHidden();
      }
      if (event.target && (event.target.matches('.js-parent-add-to-father') || event.target.matches('.js-parent-independent'))) {
        if (!activeParentId) return;
        if (event.target.matches('.js-parent-add-to-father') && parentAddBox.checked) {
          parentIndependentBox.checked = false;
        } else if (event.target.matches('.js-parent-independent') && parentIndependentBox.checked) {
          parentAddBox.checked = false;
        }
        if (!parentAddBox.checked && !parentIndependentBox.checked) {
          parentAddBox.checked = true;
        }
        parentTotalMap[activeParentId] = parentAddBox.checked ? 1 : 0;
        updateHidden();
      }
      if (event.target && event.target.matches('.js-parent-show-in-folio')) {
        if (!activeParentId) return;
        parentShowMap[activeParentId] = parentShowBox.checked ? 1 : 0;
        updateHidden();
      }
      if (event.target && event.target.matches('.js-parent-percent')) {
        if (!activeParentId) return;
        var raw = (event.target.value || '').trim();
        if (raw === '') {
          parentPercentMap[activeParentId] = null;
        } else {
          var parsed = parseFloat(raw.replace(',', '.'));
          parentPercentMap[activeParentId] = isNaN(parsed) ? null : parsed;
        }
        updateHidden();
      }
    });
    panel.addEventListener('click', function (event) {
      var btn = event.target.closest('.calc-tab');
      if (!btn || !panel.contains(btn)) return;
      var pid = parseInt(btn.dataset.calcParent || '0', 10) || 0;
      if (!pid || pid === activeParentId) return;
      snapshotCurrent();
      activeParentId = pid;
      renderTabs();
      updateState();
    });

    form.addEventListener('parent-picklist:change', requestSyncTabs);

    syncTabsFromParents();
    form.addEventListener('submit', updateHidden);
  });

  (function initConceptTreeView() {
    var clamp = function (value, min, max) {
      if (value < min) return min;
      if (value > max) return max;
      return value;
    };
    var normalize = function (value) {
      return (value == null ? '' : String(value)).toLowerCase().trim();
    };
    var escapeHtml = function (value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    };
    var formatMoney = function (cents) {
      var n = parseInt(cents || 0, 10) || 0;
      return '$' + (n / 100).toFixed(2);
    };
    var shorten = function (text, maxLen) {
      var value = String(text == null ? '' : text);
      if (value.length <= maxLen) return value;
      return value.slice(0, Math.max(0, maxLen - 1)) + '…';
    };
    var relationLabel = function (edge) {
      var parts = [];
      parts.push(edge.add_to_father_total === 1 ? '+total' : 'indep');
      if (edge.percent_value !== null && edge.percent_value !== undefined) {
        parts.push('pct ' + edge.percent_value + '%');
      }
      if (edge.show_in_folio_relation !== null && edge.show_in_folio_relation !== undefined) {
        parts.push('folio ' + (parseInt(edge.show_in_folio_relation, 10) === 1 ? 'on' : 'off'));
      }
      if (edge.components && edge.components.length) {
        parts.push('calc ' + edge.components.length);
      }
      return parts.join(' | ');
    };
    var edgeKey = function (parentId, childId) {
      var p = parseInt(parentId, 10) || 0;
      var c = parseInt(childId, 10) || 0;
      return p + ':' + c;
    };

    document.querySelectorAll('[data-concept-tree]').forEach(function (treeRoot) {
      var svg = treeRoot.querySelector('[data-tree-svg]');
      var viewport = treeRoot.querySelector('[data-tree-viewport]');
      var edgesLayer = treeRoot.querySelector('[data-tree-edges]');
      var nodesLayer = treeRoot.querySelector('[data-tree-nodes]');
      var detail = treeRoot.querySelector('[data-tree-detail]');
      var relationPanel = treeRoot.querySelector('[data-tree-relations]');
      var searchInput = treeRoot.querySelector('[data-tree-search]');
      var typeSelect = treeRoot.querySelector('[data-tree-type]');
      var hideIsolated = treeRoot.querySelector('[data-tree-hide-isolated]');
      var btnFit = treeRoot.querySelector('[data-tree-fit]');
      var btnZoomIn = treeRoot.querySelector('[data-tree-zoom-in]');
      var btnZoomOut = treeRoot.querySelector('[data-tree-zoom-out]');
      var btnZoomReset = treeRoot.querySelector('[data-tree-zoom-reset]');
      if (!svg || !viewport || !edgesLayer || !nodesLayer) return;

      var allNodes = [];
      var allEdges = [];
      try {
        allNodes = JSON.parse(treeRoot.dataset.treeNodes || '[]');
        allEdges = JSON.parse(treeRoot.dataset.treeEdges || '[]');
      } catch (err) {
        allNodes = [];
        allEdges = [];
      }
      if (!Array.isArray(allNodes)) allNodes = [];
      if (!Array.isArray(allEdges)) allEdges = [];

      var nodesById = {};
      allNodes.forEach(function (node) {
        var id = parseInt(node && node.id, 10) || 0;
        if (!id) return;
        nodesById[id] = node;
      });

      var state = {
        search: '',
        type: '',
        hideIsolated: false,
        selectedId: 0,
        selectedEdgeKey: '',
        filtered: { nodes: [], edges: [] },
        layout: { positions: {}, bounds: null },
        scale: 1,
        tx: 0,
        ty: 0,
        isPanning: false,
        panStartX: 0,
        panStartY: 0
      };

      var nodeWidth = 220;
      var nodeHeight = 96;
      var xGap = 280;
      var yGap = 180;

      var applyTransform = function () {
        viewport.setAttribute('transform', 'translate(' + state.tx + ',' + state.ty + ') scale(' + state.scale + ')');
      };

      var filterData = function () {
        var visibleNodes = allNodes.filter(function (node) {
          var id = parseInt(node && node.id, 10) || 0;
          if (!id) return false;
          if (state.type && normalize(node.catalog_type) !== normalize(state.type)) return false;
          if (state.search) {
            var hay = normalize((node.label || '') + ' ' + (node.category || '') + ' ' + (node.property_code || '') + ' ' + (node.catalog_type || ''));
            if (hay.indexOf(state.search) === -1) return false;
          }
          return true;
        });

        var visibleSet = {};
        visibleNodes.forEach(function (node) {
          var id = parseInt(node.id, 10) || 0;
          if (id) visibleSet[id] = true;
        });

        var visibleEdges = allEdges.filter(function (edge) {
          var parentId = parseInt(edge && edge.parent_id, 10) || 0;
          var childId = parseInt(edge && edge.child_id, 10) || 0;
          return !!visibleSet[parentId] && !!visibleSet[childId];
        });

        if (state.hideIsolated) {
          var hasRelation = {};
          visibleEdges.forEach(function (edge) {
            var parentId = parseInt(edge.parent_id, 10) || 0;
            var childId = parseInt(edge.child_id, 10) || 0;
            if (parentId) hasRelation[parentId] = true;
            if (childId) hasRelation[childId] = true;
          });
          visibleNodes = visibleNodes.filter(function (node) {
            var id = parseInt(node.id, 10) || 0;
            return !!hasRelation[id];
          });
          visibleSet = {};
          visibleNodes.forEach(function (node) {
            var id = parseInt(node.id, 10) || 0;
            if (id) visibleSet[id] = true;
          });
          visibleEdges = visibleEdges.filter(function (edge) {
            return !!visibleSet[parseInt(edge.parent_id, 10) || 0] && !!visibleSet[parseInt(edge.child_id, 10) || 0];
          });
        }

        state.filtered = { nodes: visibleNodes, edges: visibleEdges };
      };

      var buildLayout = function () {
        var positions = {};
        var bounds = null;
        var ids = state.filtered.nodes
          .map(function (node) { return parseInt(node.id, 10) || 0; })
          .filter(function (id) { return id > 0; });
        if (!ids.length) {
          state.layout = { positions: positions, bounds: bounds };
          return;
        }

        var childrenByParent = {};
        var indegree = {};
        ids.forEach(function (id) { indegree[id] = 0; });
        state.filtered.edges.forEach(function (edge) {
          var parentId = parseInt(edge.parent_id, 10) || 0;
          var childId = parseInt(edge.child_id, 10) || 0;
          if (!indegree.hasOwnProperty(parentId) || !indegree.hasOwnProperty(childId)) return;
          if (!childrenByParent[parentId]) childrenByParent[parentId] = [];
          childrenByParent[parentId].push(childId);
          indegree[childId] = (indegree[childId] || 0) + 1;
        });

        var roots = ids.filter(function (id) { return (indegree[id] || 0) === 0; });
        if (!roots.length) {
          roots = ids.slice(0, 1);
        }
        roots.sort(function (a, b) {
          return String((nodesById[a] && nodesById[a].label) || '').localeCompare(String((nodesById[b] && nodesById[b].label) || ''));
        });

        var levels = {};
        var queue = roots.slice();
        roots.forEach(function (id) { levels[id] = 0; });
        var guard = 0;
        while (queue.length && guard < 6000) {
          guard++;
          var parentId = queue.shift();
          var baseLevel = levels[parentId] || 0;
          (childrenByParent[parentId] || []).forEach(function (childId) {
            var nextLevel = baseLevel + 1;
            if (levels[childId] == null || nextLevel > levels[childId]) {
              levels[childId] = nextLevel;
              queue.push(childId);
            }
          });
        }
        ids.forEach(function (id) {
          if (levels[id] == null) levels[id] = 0;
        });

        var byLevel = {};
        ids.forEach(function (id) {
          var level = levels[id] || 0;
          if (!byLevel[level]) byLevel[level] = [];
          byLevel[level].push(id);
        });
        Object.keys(byLevel).forEach(function (levelKey) {
          byLevel[levelKey].sort(function (a, b) {
            return String((nodesById[a] && nodesById[a].label) || '').localeCompare(String((nodesById[b] && nodesById[b].label) || ''));
          });
        });

        var minX = Infinity;
        var minY = Infinity;
        var maxX = -Infinity;
        var maxY = -Infinity;
        Object.keys(byLevel).forEach(function (levelKey) {
          var level = parseInt(levelKey, 10) || 0;
          var row = byLevel[levelKey] || [];
          var startX = -((Math.max(0, row.length - 1) * xGap) / 2);
          row.forEach(function (id, idx) {
            var x = startX + (idx * xGap);
            var y = level * yGap;
            positions[id] = { x: x, y: y };
            minX = Math.min(minX, x);
            minY = Math.min(minY, y);
            maxX = Math.max(maxX, x + nodeWidth);
            maxY = Math.max(maxY, y + nodeHeight);
          });
        });

        if (minX !== Infinity) {
          bounds = {
            minX: minX,
            minY: minY,
            maxX: maxX,
            maxY: maxY,
            width: maxX - minX,
            height: maxY - minY
          };
        }
        state.layout = { positions: positions, bounds: bounds };
      };

      var renderDetail = function () {
        if (!detail || !relationPanel) return;
        var selectedId = parseInt(state.selectedId, 10) || 0;
        var selectedEdgeKey = (state.selectedEdgeKey || '').trim();
        var visibleNodeMap = {};
        state.filtered.nodes.forEach(function (node) {
          var id = parseInt(node.id, 10) || 0;
          if (id) visibleNodeMap[id] = node;
        });

        var renderRelationCard = function (title, edge, isParent, forceNodeId) {
          var parentId = parseInt(edge.parent_id, 10) || 0;
          var childId = parseInt(edge.child_id, 10) || 0;
          var otherId = forceNodeId ? forceNodeId : (isParent ? parentId : childId);
          var otherNode = nodesById[otherId] || null;
          var otherLabel = otherNode && otherNode.label ? otherNode.label : ('Concepto #' + otherId);
          var compList = '';
          var components = Array.isArray(edge.components) ? edge.components : [];
          if (components.length) {
            compList = '<div class="muted">Componentes:</div><ul>';
            components.forEach(function (comp) {
              var sign = (parseInt(comp.is_positive, 10) === -1) ? '-' : '+';
              compList += '<li>' + escapeHtml(sign + ' ' + (comp.component_label || ('Componente #' + comp.component_id))) + '</li>';
            });
            compList += '</ul>';
          } else {
            compList = '<div class="muted">Sin calculos avanzados en esta relacion.</div>';
          }
          var percentText = (edge.percent_value !== null && edge.percent_value !== undefined)
            ? String(edge.percent_value) + '%'
            : 'N/A';
          var showRel = (edge.show_in_folio_relation === null || edge.show_in_folio_relation === undefined)
            ? 'N/A'
            : (parseInt(edge.show_in_folio_relation, 10) === 1 ? '1' : '0');
          return ''
            + '<div class="concept-tree-rel-card">'
            + '<strong>' + escapeHtml(title + ': ' + otherLabel) + '</strong>'
            + '<div class="muted">Padre #' + parentId + ' -> Hijo #' + childId + '</div>'
            + '<div class="muted">add_to_father_total: ' + (parseInt(edge.add_to_father_total, 10) === 1 ? '1' : '0')
            + ' | percent_value: ' + escapeHtml(percentText)
            + ' | show_in_folio_relation: ' + showRel + '</div>'
            + compList
            + '</div>';
        };

        if (selectedEdgeKey !== '') {
          var selectedEdge = null;
          state.filtered.edges.some(function (edge) {
            var key = edgeKey(edge.parent_id, edge.child_id);
            if (key === selectedEdgeKey) {
              selectedEdge = edge;
              return true;
            }
            return false;
          });
          if (selectedEdge) {
            var edgeParentId = parseInt(selectedEdge.parent_id, 10) || 0;
            var edgeChildId = parseInt(selectedEdge.child_id, 10) || 0;
            var edgeParentNode = nodesById[edgeParentId] || null;
            var edgeChildNode = nodesById[edgeChildId] || null;
            detail.className = 'concept-tree-detail';
            detail.innerHTML = ''
              + '<div><strong>Relacion seleccionada</strong></div>'
              + '<div class="muted">Padre #' + edgeParentId + ': ' + escapeHtml(edgeParentNode && edgeParentNode.label ? edgeParentNode.label : ('Concepto #' + edgeParentId)) + '</div>'
              + '<div class="muted">Hijo #' + edgeChildId + ': ' + escapeHtml(edgeChildNode && edgeChildNode.label ? edgeChildNode.label : ('Concepto #' + edgeChildId)) + '</div>'
              + '<div class="muted">Solo esta relacion y sus nodos quedan resaltados para mejorar legibilidad.</div>';
            relationPanel.innerHTML = renderRelationCard('Relacion', selectedEdge, false, edgeChildId);
            return;
          }
        }

        if (!selectedId || !visibleNodeMap[selectedId]) {
          detail.className = 'concept-tree-detail muted';
          detail.innerHTML = 'Selecciona un nodo o una relacion para inspeccionar calculos y conexiones.';
          relationPanel.innerHTML = '';
          return;
        }
        var node = visibleNodeMap[selectedId];
        var nodeType = node.catalog_type || '(sin tipo)';
        var nodeCategory = node.category || '(sin categoria)';
        var nodeProperty = node.property_code || '(sin propiedad)';
        detail.className = 'concept-tree-detail';
        detail.innerHTML = ''
          + '<div><strong>' + escapeHtml(node.label || ('Concepto #' + selectedId)) + '</strong></div>'
          + '<div class="muted">ID: ' + selectedId + ' | Tipo: ' + escapeHtml(nodeType) + '</div>'
          + '<div class="muted">Categoria: ' + escapeHtml(nodeCategory) + '</div>'
          + '<div class="muted">Propiedad: ' + escapeHtml(nodeProperty) + '</div>'
          + '<div class="muted">Precio base: ' + escapeHtml(formatMoney(node.default_unit_price_cents || 0)) + '</div>'
          + '<div class="muted">show_in_folio: ' + (parseInt(node.show_in_folio, 10) === 1 ? '1' : '0')
          + ' | allow_negative: ' + (parseInt(node.allow_negative, 10) === 1 ? '1' : '0')
          + ' | activo: ' + (parseInt(node.is_active, 10) === 1 ? '1' : '0') + '</div>';

        var relHtml = '';
        var parents = state.filtered.edges.filter(function (edge) {
          return (parseInt(edge.child_id, 10) || 0) === selectedId;
        });
        var children = state.filtered.edges.filter(function (edge) {
          return (parseInt(edge.parent_id, 10) || 0) === selectedId;
        });

        parents.forEach(function (edge) {
          relHtml += renderRelationCard('Padre', edge, true);
        });
        children.forEach(function (edge) {
          relHtml += renderRelationCard('Hijo', edge, false);
        });
        if (!relHtml) {
          relHtml = '<div class="muted">Este nodo no tiene relaciones visibles con los filtros actuales.</div>';
        }
        relationPanel.innerHTML = relHtml;
      };

      var updateHighlightClasses = function () {
        var selectedId = parseInt(state.selectedId, 10) || 0;
        var selectedEdgeKey = (state.selectedEdgeKey || '').trim();
        var hasSelection = selectedId > 0 || selectedEdgeKey !== '';
        var focusNodeMap = {};
        var focusEdgeMap = {};

        if (selectedEdgeKey !== '') {
          var keyParts = selectedEdgeKey.split(':');
          var edgeParentId = parseInt(keyParts[0] || '0', 10) || 0;
          var edgeChildId = parseInt(keyParts[1] || '0', 10) || 0;
          if (edgeParentId > 0) focusNodeMap[edgeParentId] = true;
          if (edgeChildId > 0) focusNodeMap[edgeChildId] = true;
          if (edgeParentId > 0 && edgeChildId > 0) {
            focusEdgeMap[selectedEdgeKey] = true;
          }
        } else if (selectedId > 0) {
          focusNodeMap[selectedId] = true;
          state.filtered.edges.forEach(function (edge) {
            var parentId = parseInt(edge.parent_id, 10) || 0;
            var childId = parseInt(edge.child_id, 10) || 0;
            if (parentId === selectedId || childId === selectedId) {
              var eKey = edgeKey(parentId, childId);
              focusEdgeMap[eKey] = true;
              if (parentId > 0) focusNodeMap[parentId] = true;
              if (childId > 0) focusNodeMap[childId] = true;
            }
          });
        }

        nodesLayer.querySelectorAll('.concept-tree-node').forEach(function (nodeEl) {
          var nodeId = parseInt(nodeEl.getAttribute('data-node-id') || '0', 10) || 0;
          nodeEl.classList.toggle('is-selected', selectedId > 0 && nodeId === selectedId);
          nodeEl.classList.toggle('is-context', !!focusNodeMap[nodeId]);
          nodeEl.classList.toggle('is-dimmed', hasSelection && !focusNodeMap[nodeId]);
        });
        edgesLayer.querySelectorAll('.concept-tree-edge').forEach(function (edgeEl) {
          var parentId = parseInt(edgeEl.getAttribute('data-parent-id') || '0', 10) || 0;
          var childId = parseInt(edgeEl.getAttribute('data-child-id') || '0', 10) || 0;
          var eKey = edgeKey(parentId, childId);
          var isSelectedEdge = selectedEdgeKey !== '' ? (eKey === selectedEdgeKey) : !!focusEdgeMap[eKey];
          var isContextEdge = !!focusEdgeMap[eKey];
          edgeEl.classList.toggle('is-selected', isSelectedEdge);
          edgeEl.classList.toggle('is-muted', hasSelection && !isContextEdge);
          edgeEl.classList.toggle('is-dimmed', hasSelection && !isContextEdge);
        });
        edgesLayer.querySelectorAll('.concept-tree-edge-label').forEach(function (labelEl) {
          var parentId = parseInt(labelEl.getAttribute('data-parent-id') || '0', 10) || 0;
          var childId = parseInt(labelEl.getAttribute('data-child-id') || '0', 10) || 0;
          var eKey = edgeKey(parentId, childId);
          var isSelectedEdge = selectedEdgeKey !== '' ? (eKey === selectedEdgeKey) : !!focusEdgeMap[eKey];
          var isContextEdge = !!focusEdgeMap[eKey];
          labelEl.classList.toggle('is-selected', isSelectedEdge);
          labelEl.classList.toggle('is-dimmed', hasSelection && !isContextEdge);
        });
      };

      var fitToViewport = function () {
        var bounds = state.layout.bounds;
        if (!bounds) return;
        var width = Math.max(360, svg.clientWidth || 0);
        var height = Math.max(320, svg.clientHeight || 0);
        var scaleX = (width - 60) / Math.max(1, bounds.width);
        var scaleY = (height - 60) / Math.max(1, bounds.height);
        state.scale = clamp(Math.min(scaleX, scaleY), 0.2, 1.6);
        state.tx = (width / 2) - (state.scale * (bounds.minX + (bounds.width / 2)));
        state.ty = (height / 2) - (state.scale * (bounds.minY + (bounds.height / 2)));
        applyTransform();
      };

      var render = function (autoFit) {
        filterData();
        buildLayout();
        edgesLayer.innerHTML = '';
        nodesLayer.innerHTML = '';

        var positions = state.layout.positions || {};
        if (!state.filtered.nodes.length) {
          state.selectedId = 0;
          renderDetail();
          var emptyText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
          emptyText.setAttribute('x', '24');
          emptyText.setAttribute('y', '32');
          emptyText.setAttribute('class', 'concept-tree-edge-label');
          emptyText.textContent = 'Sin resultados para los filtros seleccionados.';
          nodesLayer.appendChild(emptyText);
          applyTransform();
          return;
        }

        state.filtered.edges.forEach(function (edge) {
          var parentId = parseInt(edge.parent_id, 10) || 0;
          var childId = parseInt(edge.child_id, 10) || 0;
          var parentPos = positions[parentId];
          var childPos = positions[childId];
          if (!parentPos || !childPos) return;

          var startX = parentPos.x + (nodeWidth / 2);
          var startY = parentPos.y + nodeHeight;
          var endX = childPos.x + (nodeWidth / 2);
          var endY = childPos.y;
          var ctrlY1 = startY + 42;
          var ctrlY2 = endY - 42;
          var currentEdgeKey = edgeKey(parentId, childId);
          var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
          path.setAttribute('class', 'concept-tree-edge');
          path.setAttribute('data-parent-id', String(parentId));
          path.setAttribute('data-child-id', String(childId));
          path.setAttribute('data-edge-key', currentEdgeKey);
          path.setAttribute('d', 'M ' + startX + ' ' + startY + ' C ' + startX + ' ' + ctrlY1 + ', ' + endX + ' ' + ctrlY2 + ', ' + endX + ' ' + endY);
          path.addEventListener('click', function (event) {
            event.stopPropagation();
            state.selectedEdgeKey = currentEdgeKey;
            state.selectedId = 0;
            updateHighlightClasses();
            renderDetail();
          });
          edgesLayer.appendChild(path);

          var label = relationLabel(edge);
          if (label !== '') {
            var labelText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            labelText.setAttribute('class', 'concept-tree-edge-label');
            labelText.setAttribute('data-parent-id', String(parentId));
            labelText.setAttribute('data-child-id', String(childId));
            labelText.setAttribute('x', String((startX + endX) / 2));
            labelText.setAttribute('y', String((startY + endY) / 2 - 6));
            labelText.setAttribute('text-anchor', 'middle');
            labelText.textContent = label;
            labelText.addEventListener('click', function (event) {
              event.stopPropagation();
              state.selectedEdgeKey = currentEdgeKey;
              state.selectedId = 0;
              updateHighlightClasses();
              renderDetail();
            });
            edgesLayer.appendChild(labelText);
          }
        });

        state.filtered.nodes.forEach(function (node) {
          var nodeId = parseInt(node.id, 10) || 0;
          var pos = positions[nodeId];
          if (!pos) return;

          var group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
          var classes = ['concept-tree-node'];
          if (parseInt(node.is_active, 10) !== 1) classes.push('is-inactive');
          if (state.selectedId === nodeId) classes.push('is-selected');
          group.setAttribute('class', classes.join(' '));
          group.setAttribute('data-node-id', String(nodeId));
          group.setAttribute('transform', 'translate(' + pos.x + ',' + pos.y + ')');

          var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
          rect.setAttribute('x', '0');
          rect.setAttribute('y', '0');
          rect.setAttribute('width', String(nodeWidth));
          rect.setAttribute('height', String(nodeHeight));
          rect.setAttribute('rx', '10');
          rect.setAttribute('ry', '10');
          group.appendChild(rect);

          var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
          text.setAttribute('x', '10');
          text.setAttribute('y', '19');

          var title = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
          title.setAttribute('x', '10');
          title.setAttribute('class', 'node-title');
          title.textContent = shorten((node.label || ('Concepto #' + nodeId)), 30);
          text.appendChild(title);

          var meta1 = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
          meta1.setAttribute('x', '10');
          meta1.setAttribute('dy', '17');
          meta1.textContent = shorten((node.catalog_type || '(sin tipo)') + ' | ' + (node.category || '(sin categoria)'), 36);
          text.appendChild(meta1);

          var meta2 = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
          meta2.setAttribute('x', '10');
          meta2.setAttribute('dy', '16');
          meta2.textContent = shorten((node.property_code || '(sin propiedad)') + ' | ' + formatMoney(node.default_unit_price_cents || 0), 36);
          text.appendChild(meta2);

          var meta3 = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
          meta3.setAttribute('x', '10');
          meta3.setAttribute('dy', '16');
          meta3.textContent = 'folio:' + (parseInt(node.show_in_folio, 10) === 1 ? '1' : '0')
            + ' | neg:' + (parseInt(node.allow_negative, 10) === 1 ? '1' : '0')
            + ' | act:' + (parseInt(node.is_active, 10) === 1 ? '1' : '0');
          text.appendChild(meta3);

          group.appendChild(text);
          group.addEventListener('click', function () {
            state.selectedId = nodeId;
            state.selectedEdgeKey = '';
            updateHighlightClasses();
            renderDetail();
          });
          nodesLayer.appendChild(group);
        });

        if (state.selectedEdgeKey) {
          var selectedEdgeVisible = state.filtered.edges.some(function (edge) {
            return edgeKey(edge.parent_id, edge.child_id) === state.selectedEdgeKey;
          });
          if (!selectedEdgeVisible) {
            state.selectedEdgeKey = '';
          }
        }
        if (state.selectedId) {
          var stillVisible = state.filtered.nodes.some(function (node) {
            return (parseInt(node.id, 10) || 0) === state.selectedId;
          });
          if (!stillVisible) state.selectedId = 0;
        }
        updateHighlightClasses();
        renderDetail();
        if (autoFit) {
          fitToViewport();
        } else {
          applyTransform();
        }
      };

      var zoomAt = function (factor, clientX, clientY) {
        var rect = svg.getBoundingClientRect();
        var x = clientX - rect.left;
        var y = clientY - rect.top;
        var nextScale = clamp(state.scale * factor, 0.2, 2.4);
        if (nextScale === state.scale) return;
        state.tx = x - ((x - state.tx) * (nextScale / state.scale));
        state.ty = y - ((y - state.ty) * (nextScale / state.scale));
        state.scale = nextScale;
        applyTransform();
      };

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          state.search = normalize(searchInput.value || '');
          render(true);
        });
      }
      if (typeSelect) {
        typeSelect.addEventListener('change', function () {
          state.type = normalize(typeSelect.value || '');
          render(true);
        });
      }
      if (hideIsolated) {
        hideIsolated.addEventListener('change', function () {
          state.hideIsolated = !!hideIsolated.checked;
          render(true);
        });
      }
      if (btnFit) {
        btnFit.addEventListener('click', function () {
          fitToViewport();
        });
      }
      if (btnZoomIn) {
        btnZoomIn.addEventListener('click', function () {
          var rect = svg.getBoundingClientRect();
          zoomAt(1.15, rect.left + (rect.width / 2), rect.top + (rect.height / 2));
        });
      }
      if (btnZoomOut) {
        btnZoomOut.addEventListener('click', function () {
          var rect = svg.getBoundingClientRect();
          zoomAt(1 / 1.15, rect.left + (rect.width / 2), rect.top + (rect.height / 2));
        });
      }
      if (btnZoomReset) {
        btnZoomReset.addEventListener('click', function () {
          state.scale = 1;
          state.tx = 0;
          state.ty = 0;
          applyTransform();
        });
      }

      svg.addEventListener('wheel', function (event) {
        event.preventDefault();
        var factor = event.deltaY < 0 ? 1.08 : (1 / 1.08);
        zoomAt(factor, event.clientX, event.clientY);
      }, { passive: false });

      svg.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.closest && (
          target.closest('.concept-tree-node')
          || target.closest('.concept-tree-edge')
          || target.closest('.concept-tree-edge-label')
        )) {
          return;
        }
        if (state.selectedId || state.selectedEdgeKey) {
          state.selectedId = 0;
          state.selectedEdgeKey = '';
          updateHighlightClasses();
          renderDetail();
        }
      });

      svg.addEventListener('mousedown', function (event) {
        if (event.button !== 0) return;
        state.isPanning = true;
        state.panStartX = event.clientX - state.tx;
        state.panStartY = event.clientY - state.ty;
        svg.classList.add('is-panning');
      });
      window.addEventListener('mousemove', function (event) {
        if (!state.isPanning) return;
        state.tx = event.clientX - state.panStartX;
        state.ty = event.clientY - state.panStartY;
        applyTransform();
      });
      window.addEventListener('mouseup', function () {
        state.isPanning = false;
        svg.classList.remove('is-panning');
      });
      window.addEventListener('resize', function () {
        fitToViewport();
      });

      render(true);
    });
  })();

  (function initLineItemHierarchyToggle() {
    var hierarchyRows = Array.from(document.querySelectorAll('tr.line-item-row-hierarchy[data-line-id]'));
    if (!hierarchyRows.length) return;

    var rowById = {};
    var childrenByParent = {};
    hierarchyRows.forEach(function (row) {
      var id = parseInt(row.getAttribute('data-line-id') || '0', 10) || 0;
      var parentId = parseInt(row.getAttribute('data-parent-id') || '0', 10) || 0;
      if (!id) return;
      rowById[id] = row;
      if (!childrenByParent[parentId]) childrenByParent[parentId] = [];
      childrenByParent[parentId].push(id);
    });

    var hiddenParents = {};

    var hideBranch = function (parentId) {
      var children = childrenByParent[parentId] || [];
      children.forEach(function (childId) {
        hiddenParents[childId] = true;
        var childRow = rowById[childId];
        if (childRow) childRow.style.display = 'none';
        hideBranch(childId);
      });
    };

    var isAncestorHidden = function (lineId) {
      var row = rowById[lineId];
      if (!row) return false;
      var parentId = parseInt(row.getAttribute('data-parent-id') || '0', 10) || 0;
      while (parentId > 0) {
        if (hiddenParents[parentId]) return true;
        var parentRow = rowById[parentId];
        if (!parentRow) return false;
        parentId = parseInt(parentRow.getAttribute('data-parent-id') || '0', 10) || 0;
      }
      return false;
    };

    var showBranch = function (parentId) {
      var children = childrenByParent[parentId] || [];
      children.forEach(function (childId) {
        var childRow = rowById[childId];
        if (!childRow) return;
        if (!isAncestorHidden(childId)) {
          childRow.style.display = '';
        }
        if (!hiddenParents[childId]) {
          showBranch(childId);
        } else {
          hideBranch(childId);
        }
      });
    };

    document.querySelectorAll('.js-line-item-toggle[data-target-line-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = parseInt(btn.getAttribute('data-target-line-id') || '0', 10) || 0;
        if (!targetId) return;
        var willCollapse = !hiddenParents[targetId];
        hiddenParents[targetId] = willCollapse ? true : false;
        if (willCollapse) {
          hideBranch(targetId);
          btn.textContent = 'Ver derivados';
        } else {
          showBranch(targetId);
          btn.textContent = 'Ocultar derivados';
        }
      });
    });
  })();
});
</script>
