<?php
$moduleKey = 'reports';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyCode = (string)$currentUser['company_code'];
$companyId = (int)$currentUser['company_id'];
if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

$properties = pms_fetch_properties($companyId);

if (!function_exists('reports_format_money')) {
    function reports_format_money($cents, $currency = 'MXN')
    {
        $amount = ((int)$cents) / 100;
        return '$' . number_format($amount, 2) . ' ' . $currency;
    }
}

if (!function_exists('reports_safe_expr_eval')) {
    function reports_safe_expr_eval($expr)
    {
        $expr = trim((string)$expr);
        if ($expr === '') {
            return null;
        }
        if (!preg_match('/^[0-9\s\+\-\*\/\(\)\.]+$/', $expr)) {
            return null;
        }
        $value = null;
        try {
            $value = eval('return (' . $expr . ');');
        } catch (Throwable $e) {
            $value = null;
        }
        if ($value === null || !is_numeric($value)) {
            return null;
        }
        return (float)$value;
    }
}

if (!function_exists('reports_build_placeholders')) {
    function reports_build_placeholders($count)
    {
        return implode(',', array_fill(0, $count, '?'));
    }
}

if (!function_exists('reports_load_report_config')) {
    function reports_load_report_config($companyCode, $reportKey)
    {
        $config = array(
            'id' => 0,
            'report_name' => '',
            'column_order' => '',
            'lodging_catalog_ids' => array(),
            'cleaning_catalog_id' => 0,
            'iva_catalog_id' => 0,
            'ish_catalog_id' => 0,
            'resico_catalog_id' => 0,
            'extra_catalog_ids' => array()
        );

        $sets = pms_call_procedure('sp_report_config_data', array($companyCode, $reportKey));
        $header = isset($sets[0][0]) ? $sets[0][0] : null;
        if (!$header) {
            return $config;
        }
        $config['id'] = isset($header['id_report_config']) ? (int)$header['id_report_config'] : 0;
        $config['report_name'] = isset($header['report_name']) ? (string)$header['report_name'] : '';
        $config['column_order'] = isset($header['column_order']) ? (string)$header['column_order'] : '';

        $items = isset($sets[1]) ? $sets[1] : array();
        foreach ($items as $item) {
            $catalogId = isset($item['id_sale_item_catalog']) ? (int)$item['id_sale_item_catalog'] : 0;
            $role = isset($item['role']) ? (string)$item['role'] : '';
            if ($catalogId <= 0) {
                continue;
            }
            if ($role === 'lodging') {
                $config['lodging_catalog_ids'][] = $catalogId;
            } elseif ($role === 'cleaning') {
                $config['cleaning_catalog_id'] = $catalogId;
            } elseif ($role === 'iva') {
                $config['iva_catalog_id'] = $catalogId;
            } elseif ($role === 'ish') {
                $config['ish_catalog_id'] = $catalogId;
                $config['resico_catalog_id'] = $catalogId;
            } elseif ($role === 'resico') {
                $config['resico_catalog_id'] = $catalogId;
            } elseif ($role === 'extra') {
                $config['extra_catalog_ids'][] = $catalogId;
            }
        }

        $config['lodging_catalog_ids'] = array_values(array_unique($config['lodging_catalog_ids']));
        $config['extra_catalog_ids'] = array_values(array_unique($config['extra_catalog_ids']));
        return $config;
    }
}

if (!function_exists('reports_save_report_config')) {
    function reports_save_report_config($companyCode, $reportKey, $reportName, $columnOrder, array $items, $actorUserId)
    {
        $lodgingIds = array();
        $extraIds = array();
        $cleaningId = 0;
        $ivaId = 0;
        $ishId = 0;

        foreach ($items as $item) {
            if (!isset($item['id']) || (int)$item['id'] <= 0) {
                continue;
            }
            $role = isset($item['role']) ? (string)$item['role'] : '';
            if ($role === 'lodging') {
                $lodgingIds[] = (int)$item['id'];
            } elseif ($role === 'cleaning') {
                $cleaningId = (int)$item['id'];
            } elseif ($role === 'iva') {
                $ivaId = (int)$item['id'];
            } elseif ($role === 'ish') {
                $ishId = (int)$item['id'];
            } elseif ($role === 'extra') {
                $extraIds[] = (int)$item['id'];
            }
        }

        $lodgingIds = array_values(array_unique(array_filter($lodgingIds)));
        $extraIds = array_values(array_unique(array_filter($extraIds)));

        $lodgingCsv = $lodgingIds ? implode(',', $lodgingIds) : '';
        $extraCsv = $extraIds ? implode(',', $extraIds) : '';

        try {
            pms_call_procedure('sp_report_config_upsert', array(
                $companyCode,
                $reportKey,
                $reportName,
                $columnOrder !== '' ? $columnOrder : null,
                $lodgingCsv !== '' ? $lodgingCsv : null,
                $cleaningId,
                $ivaId,
                $ishId,
                $extraCsv !== '' ? $extraCsv : null,
                $actorUserId
            ));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('reports_fetch_sale_item_catalogs')) {
    function reports_fetch_sale_item_catalogs($companyCode, $propertyCode)
    {
        try {
            $sets = pms_call_procedure('sp_report_catalog_options', array(
                $companyCode,
                $propertyCode === '' ? null : $propertyCode
            ));
            $rows = isset($sets[0]) ? $sets[0] : array();
            if ($rows) {
                return $rows;
            }
        } catch (Exception $e) {
        }
        try {
            $pdo = pms_get_connection();
            $sql = 'SELECT
                        sic.id_line_item_catalog,
                        sic.catalog_type,
                        sic.item_name,
                        sic.percent_value AS rate_percent,
                        cat.id_property AS category_property_id,
                        cat.category_name AS subcategory_name,
                        parent.category_name AS category_name
                    FROM line_item_catalog sic
                    JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
                    JOIN company comp ON comp.id_company = cat.id_company
                    LEFT JOIN sale_item_category parent ON parent.id_sale_item_category = cat.id_parent_sale_item_category
                    LEFT JOIN property prop ON prop.id_property = cat.id_property
                    WHERE comp.code = ?
                      AND sic.deleted_at IS NULL
                      AND sic.is_active = 1
                      AND cat.deleted_at IS NULL
                      AND sic.catalog_type IN (\'sale_item\',\'tax_rule\')
                      AND (? IS NULL OR ? = \'\' OR cat.id_property IS NULL OR prop.code = ?)
                    ORDER BY category_name, subcategory_name, sic.item_name';
            $stmt = $pdo->prepare($sql);
            $pcode = $propertyCode === '' ? null : $propertyCode;
            $stmt->execute(array($companyCode, $pcode, $pcode, $pcode));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array();
        }
    }
}

if (!function_exists('reports_build_extra_column_key')) {
    function reports_build_extra_column_key($parentCatalogId, $childCatalogId, $displayName = '')
    {
        $parentCatalogId = (int)$parentCatalogId;
        $childCatalogId = (int)$childCatalogId;
        $displayName = trim((string)$displayName);
        $base = 'extra:' . $parentCatalogId . ':' . $childCatalogId;
        if ($displayName === '') {
            return $base;
        }
        return $base . ':' . rawurlencode($displayName);
    }
}

if (!function_exists('reports_build_multi_extra_column_key')) {
    function reports_build_multi_extra_column_key(array $relationPairs, $displayName = '')
    {
        $normalized = array();
        foreach ($relationPairs as $pair) {
            $parent = isset($pair['parent_catalog_id']) ? (int)$pair['parent_catalog_id'] : 0;
            $catalog = isset($pair['catalog_id']) ? (int)$pair['catalog_id'] : 0;
            if ($catalog <= 0) {
                continue;
            }
            $normalized[$parent . ':' . $catalog] = $parent . ':' . $catalog;
        }
        if (empty($normalized)) {
            return '';
        }
        $pairs = array_values($normalized);
        sort($pairs, SORT_NATURAL);
        $base = 'extra:multi:' . rawurlencode(implode('|', $pairs));
        $displayName = trim((string)$displayName);
        if ($displayName === '') {
            return $base;
        }
        return $base . ':' . rawurlencode($displayName);
    }
}

if (!function_exists('reports_parse_extra_column_key')) {
    function reports_parse_extra_column_key($key)
    {
        $raw = trim((string)$key);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^extra:(\d+)$/', $raw, $mLegacy)) {
            return array(
                'key' => $raw,
                'is_multi' => 0,
                'parent_catalog_id' => 0,
                'catalog_id' => (int)$mLegacy[1],
                'relation_pairs' => array(
                    array('parent_catalog_id' => 0, 'catalog_id' => (int)$mLegacy[1])
                ),
                'relation_signature' => '0:' . (int)$mLegacy[1],
                'display_name' => ''
            );
        }
        if (preg_match('/^extra:multi:([^:]+)(?::(.*))?$/', $raw, $mMulti)) {
            $token = rawurldecode((string)$mMulti[1]);
            $pairsRaw = array_values(array_filter(array_map('trim', explode('|', $token))));
            $pairs = array();
            $pairMap = array();
            foreach ($pairsRaw as $pair) {
                if (!preg_match('/^(\d+):(\d+)$/', $pair, $mPair)) {
                    continue;
                }
                $parentId = (int)$mPair[1];
                $catalogId = (int)$mPair[2];
                if ($catalogId <= 0) {
                    continue;
                }
                $pairKey = $parentId . ':' . $catalogId;
                if (isset($pairMap[$pairKey])) {
                    continue;
                }
                $pairMap[$pairKey] = true;
                $pairs[] = array(
                    'parent_catalog_id' => $parentId,
                    'catalog_id' => $catalogId
                );
            }
            if (empty($pairs)) {
                return null;
            }
            usort($pairs, function ($a, $b) {
                $aKey = (int)$a['parent_catalog_id'] . ':' . (int)$a['catalog_id'];
                $bKey = (int)$b['parent_catalog_id'] . ':' . (int)$b['catalog_id'];
                return strcmp($aKey, $bKey);
            });
            $encodedDisplay = isset($mMulti[2]) ? (string)$mMulti[2] : '';
            return array(
                'key' => $raw,
                'is_multi' => 1,
                'parent_catalog_id' => (int)$pairs[0]['parent_catalog_id'],
                'catalog_id' => (int)$pairs[0]['catalog_id'],
                'relation_pairs' => $pairs,
                'relation_signature' => implode('|', array_map(function ($pair) {
                    return ((int)$pair['parent_catalog_id']) . ':' . ((int)$pair['catalog_id']);
                }, $pairs)),
                'display_name' => $encodedDisplay !== '' ? rawurldecode($encodedDisplay) : ''
            );
        }
        if (!preg_match('/^extra:(\d+):(\d+)(?::(.*))?$/', $raw, $m)) {
            return null;
        }
        $encodedDisplay = isset($m[3]) ? (string)$m[3] : '';
        return array(
            'key' => $raw,
            'is_multi' => 0,
            'parent_catalog_id' => (int)$m[1],
            'catalog_id' => (int)$m[2],
            'relation_pairs' => array(
                array('parent_catalog_id' => (int)$m[1], 'catalog_id' => (int)$m[2])
            ),
            'relation_signature' => ((int)$m[1]) . ':' . ((int)$m[2]),
            'display_name' => $encodedDisplay !== '' ? rawurldecode($encodedDisplay) : ''
        );
    }
}

if (!function_exists('reports_pick_multi_relation_value')) {
    function reports_pick_multi_relation_value(array $values)
    {
        if (empty($values)) {
            return null;
        }
        $normalized = array();
        foreach ($values as $value) {
            $normalized[] = (int)$value;
        }
        $allZero = true;
        foreach ($normalized as $value) {
            if ($value !== 0) {
                $allZero = false;
                break;
            }
        }
        if ($allZero) {
            return 0;
        }
        $uniqueAll = array_values(array_unique($normalized, SORT_NUMERIC));
        if (count($uniqueAll) === 1) {
            return (int)$uniqueAll[0];
        }
        $nonZero = array_values(array_filter($normalized, function ($value) {
            return (int)$value !== 0;
        }));
        if (!empty($nonZero)) {
            $uniqueNonZero = array_values(array_unique($nonZero, SORT_NUMERIC));
            if (count($uniqueNonZero) === 1) {
                return (int)$uniqueNonZero[0];
            }
        }
        return null;
    }
}

if (!function_exists('reports_fetch_catalog_relations')) {
    function reports_fetch_catalog_relations($companyCode, $propertyCode = '')
    {
        $rows = array();
        try {
            $pdo = pms_get_connection();
            $sql = 'SELECT
                        lcp.id_parent_sale_item_catalog AS parent_catalog_id,
                        lcp.id_sale_item_catalog AS child_catalog_id
                    FROM line_item_catalog_parent lcp
                    JOIN line_item_catalog child
                      ON child.id_line_item_catalog = lcp.id_sale_item_catalog
                     AND child.deleted_at IS NULL
                     AND child.is_active = 1
                     AND child.catalog_type IN (\'sale_item\',\'tax_rule\')
                    JOIN line_item_catalog parent
                      ON parent.id_line_item_catalog = lcp.id_parent_sale_item_catalog
                     AND parent.deleted_at IS NULL
                     AND parent.is_active = 1
                    JOIN sale_item_category cat
                      ON cat.id_sale_item_category = child.id_category
                     AND cat.deleted_at IS NULL
                    JOIN company comp
                      ON comp.id_company = cat.id_company
                     AND comp.code = ?
                    LEFT JOIN property prop
                      ON prop.id_property = cat.id_property
                    WHERE lcp.deleted_at IS NULL
                      AND lcp.is_active = 1
                      AND (? IS NULL OR ? = \'\' OR cat.id_property IS NULL OR prop.code = ?)
                    ORDER BY lcp.id_parent_sale_item_catalog, lcp.id_sale_item_catalog';
            $pcode = $propertyCode === '' ? null : $propertyCode;
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($companyCode, $pcode, $pcode, $pcode));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $rows = array();
        }
        return $rows;
    }
}

if (!function_exists('reports_build_descendant_sums')) {
    function reports_build_descendant_sums(array $baseIds, array $saleItems, array $taxItems)
    {
        $itemsById = array();
        $childrenMap = array();
        $parentMap = array();
        foreach ($saleItems as $item) {
            $id = (int)$item['id_line_item'];
            if ($id <= 0) {
                continue;
            }
            $itemsById[$id] = $item;
            $parent = isset($item['id_parent_sale_item']) ? (int)$item['id_parent_sale_item'] : 0;
            if ($parent > 0) {
                $parentMap[$id] = $parent;
                if (!isset($childrenMap[$parent])) {
                    $childrenMap[$parent] = array();
                }
                $childrenMap[$parent][] = $id;
            }
        }

        $taxBySaleItem = array();
        foreach ($taxItems as $tax) {
            $saleId = isset($tax['id_sale_item']) ? (int)$tax['id_sale_item'] : 0;
            if ($saleId <= 0) {
                continue;
            }
            if (!isset($taxBySaleItem[$saleId])) {
                $taxBySaleItem[$saleId] = array();
            }
            $taxBySaleItem[$saleId][] = $tax;
        }

        $descendantMap = array();
        foreach ($baseIds as $baseId) {
            $baseId = (int)$baseId;
            if ($baseId <= 0) {
                continue;
            }
            $seen = array($baseId => true);

            $downStack = array($baseId);
            while ($downStack) {
                $current = array_pop($downStack);
                if (empty($childrenMap[$current])) {
                    continue;
                }
                foreach ($childrenMap[$current] as $childId) {
                    if (isset($seen[$childId])) {
                        continue;
                    }
                    $seen[$childId] = true;
                    $downStack[] = $childId;
                }
            }

            $up = $baseId;
            while (isset($parentMap[$up])) {
                $up = (int)$parentMap[$up];
                if ($up <= 0 || isset($seen[$up])) {
                    break;
                }
                $seen[$up] = true;
            }

            $descendantMap[$baseId] = array_keys($seen);
        }

        return array($itemsById, $taxBySaleItem, $descendantMap);
    }
}

if (!function_exists('reports_fetch_totals_by_reservation')) {
    function reports_fetch_totals_by_reservation($companyCode, $reservationIds)
    {
        $totals = array();
        if (!$reservationIds) {
            return $totals;
        }
        $idList = implode(',', array_map('intval', $reservationIds));
        $sets = pms_call_procedure('sp_reservation_totals_report', array($companyCode, $idList));
        $rows = isset($sets[0]) ? $sets[0] : array();
        foreach ($rows as $row) {
            $rid = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
            if ($rid <= 0) {
                continue;
            }
            $totals[$rid] = array(
                'charges' => isset($row['charges_cents']) ? (int)$row['charges_cents'] : 0,
                'taxes' => isset($row['taxes_cents']) ? (int)$row['taxes_cents'] : 0,
                'payments' => isset($row['payments_cents']) ? (int)$row['payments_cents'] : 0,
                'obligations' => isset($row['obligations_cents']) ? (int)$row['obligations_cents'] : 0,
                'incomes' => isset($row['incomes_cents']) ? (int)$row['incomes_cents'] : 0
            );
        }

        return $totals;
    }
}

$activeTab = 'charges';
if (isset($_POST['reports_active_tab'])) {
    $activeTab = (string)$_POST['reports_active_tab'];
} elseif (isset($_GET['tab'])) {
    $activeTab = (string)$_GET['tab'];
}
if (!in_array($activeTab, array('charges','line_items','accounting','incomes','reservations','custom','builder'), true)) {
    $activeTab = 'charges';
}

$tabLinks = array(
    'charges' => 'Cargos',
    'line_items' => 'Line items',
    'accounting' => 'Contabilidad',
    'builder' => 'Configurables',
    'reservations' => 'Reservas',
    'custom' => 'Personalizado'
);

$reportTabGroups = array(
    'fixed' => array(
        'label' => 'Reportes fijos',
        'tabs' => array('charges','line_items','accounting')
    ),
    'config' => array(
        'label' => 'Configuracion',
        'tabs' => array('builder')
    ),
    'custom' => array(
        'label' => 'Reportes personalizados',
        'tabs' => array('reservations','custom')
    )
);

$availableColumns = array(
    'reservation_code' => 'Reserva',
    'status' => 'Estatus',
    'source' => 'Fuente',
    'check_in_date' => 'Check-in',
    'check_out_date' => 'Check-out',
    'guest_name' => 'Huesped',
    'guest_email' => 'Email',
    'room_name' => 'Habitacion',
    'property_name' => 'Propiedad',
    'adults' => 'Adultos',
    'children' => 'Ninos',
    'currency' => 'Moneda',
    'total_price_cents' => 'Total reserva',
    'balance_due_cents' => 'Saldo reserva',
    'charges' => 'Total cargos',
    'taxes' => 'Total impuestos',
    'payments' => 'Total pagos',
    'obligations' => 'Total obligaciones',
    'incomes' => 'Total ingresos',
    'net_total' => 'Neto'
);

$defaultColumns = array('reservation_code','guest_name','property_name','check_in_date','check_out_date','status','charges','taxes','payments','obligations','incomes','net_total');

$resFilters = array(
    'property_code' => isset($_POST['res_report_property']) ? strtoupper((string)$_POST['res_report_property']) : '',
    'date_from' => isset($_POST['res_report_from']) ? (string)$_POST['res_report_from'] : '',
    'date_to' => isset($_POST['res_report_to']) ? (string)$_POST['res_report_to'] : '',
    'status' => isset($_POST['res_report_status']) ? trim((string)$_POST['res_report_status']) : '',
    'source' => isset($_POST['res_report_source']) ? trim((string)$_POST['res_report_source']) : '',
    'search' => isset($_POST['res_report_search']) ? trim((string)$_POST['res_report_search']) : '',
    'columns' => isset($_POST['res_report_columns']) && is_array($_POST['res_report_columns']) ? array_values($_POST['res_report_columns']) : $defaultColumns,
    'formula' => isset($_POST['res_report_formula']) ? trim((string)$_POST['res_report_formula']) : ''
);

$customFilters = array(
    'property_code' => isset($_POST['custom_report_property']) ? strtoupper((string)$_POST['custom_report_property']) : '',
    'date_from' => isset($_POST['custom_report_from']) ? (string)$_POST['custom_report_from'] : '',
    'date_to' => isset($_POST['custom_report_to']) ? (string)$_POST['custom_report_to'] : '',
    'status' => isset($_POST['custom_report_status']) ? trim((string)$_POST['custom_report_status']) : '',
    'source' => isset($_POST['custom_report_source']) ? trim((string)$_POST['custom_report_source']) : '',
    'search' => isset($_POST['custom_report_search']) ? trim((string)$_POST['custom_report_search']) : '',
    'columns' => isset($_POST['custom_report_columns']) && is_array($_POST['custom_report_columns']) ? array_values($_POST['custom_report_columns']) : $defaultColumns,
    'formula_lines' => isset($_POST['custom_report_formulas']) ? trim((string)$_POST['custom_report_formulas']) : ''
);

$defaultFrom = (new DateTime('first day of this month'))->format('Y-m-d');
$defaultTo = (new DateTime('last day of this month'))->format('Y-m-d');

$builderMessage = '';
$builderError = '';
$builderReports = array();
$builderConfigurableReports = array();
$builderFixedReports = array();
$builderSelectedReport = array();
$builderSelectedReportIsFixed = 0;
$builderColumns = array();
$builderFilters = array();
$builderFieldRows = array();
$builderCatalogRows = array();
$builderRunRows = array();
$builderRunColumns = array();
$builderFixedReportKeys = array('accounting');
$builderFilterOperators = array(
    'eq' => 'Igual',
    'neq' => 'Diferente',
    'contains' => 'Contiene',
    'gt' => 'Mayor que',
    'gte' => 'Mayor o igual',
    'lt' => 'Menor que',
    'lte' => 'Menor o igual',
    'between' => 'Entre',
    'is_null' => 'Es null',
    'is_not_null' => 'No es null'
);
$builderCatalogMetricOptions = array(
    'sum_amount' => array(
        'label' => 'Monto total',
        'data_type' => 'money',
        'aggregation' => 'sum_amount',
        'operator' => 'eq'
    ),
    'sum_quantity' => array(
        'label' => 'Cantidad total',
        'data_type' => 'number',
        'aggregation' => 'sum_quantity',
        'operator' => 'eq'
    ),
    'avg_unit_price' => array(
        'label' => 'Unitario promedio',
        'data_type' => 'money',
        'aggregation' => 'avg_unit_price',
        'operator' => 'eq'
    ),
    'sum_discount' => array(
        'label' => 'Descuento total',
        'data_type' => 'money',
        'aggregation' => 'sum_discount',
        'operator' => 'eq'
    ),
    'sum_paid' => array(
        'label' => 'Pagado total (paid_cents)',
        'data_type' => 'money',
        'aggregation' => 'sum_paid',
        'operator' => 'eq'
    ),
    'count_items' => array(
        'label' => 'Cantidad de line items',
        'data_type' => 'number',
        'aggregation' => 'count_items',
        'operator' => 'eq'
    ),
    'min_service_date' => array(
        'label' => 'Primera fecha servicio',
        'data_type' => 'date',
        'aggregation' => 'min_service_date',
        'operator' => 'eq'
    ),
    'max_service_date' => array(
        'label' => 'Ultima fecha servicio',
        'data_type' => 'date',
        'aggregation' => 'max_service_date',
        'operator' => 'eq'
    )
);

$builderState = array(
    'selected_report_id' => isset($_POST['builder_report_id'])
        ? (int)$_POST['builder_report_id']
        : (isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0),
    'report_key' => isset($_POST['builder_report_key']) ? trim((string)$_POST['builder_report_key']) : '',
    'report_name' => isset($_POST['builder_report_name']) ? trim((string)$_POST['builder_report_name']) : '',
    'report_type' => isset($_POST['builder_report_type']) ? trim((string)$_POST['builder_report_type']) : 'reservation',
    'line_item_type_scope' => isset($_POST['builder_line_item_type_scope']) ? trim((string)$_POST['builder_line_item_type_scope']) : 'all',
    'description' => isset($_POST['builder_report_description']) ? trim((string)$_POST['builder_report_description']) : '',
    'property_code' => isset($_POST['builder_property_code']) ? strtoupper(trim((string)$_POST['builder_property_code'])) : '',
    'date_from' => isset($_POST['builder_run_from']) ? (string)$_POST['builder_run_from'] : $defaultFrom,
    'date_to' => isset($_POST['builder_run_to']) ? (string)$_POST['builder_run_to'] : $defaultTo,
    'limit' => isset($_POST['builder_run_limit']) ? (int)$_POST['builder_run_limit'] : 500
);
if ($builderState['limit'] < 1) {
    $builderState['limit'] = 500;
}
if ($builderState['limit'] > 5000) {
    $builderState['limit'] = 5000;
}
if (!in_array($builderState['report_type'], array('reservation','line_item','property'), true)) {
    $builderState['report_type'] = 'reservation';
}
if (!in_array($builderState['line_item_type_scope'], array('sale_item','tax_item','payment','obligation','income','all'), true)) {
    $builderState['line_item_type_scope'] = 'all';
}

if ($activeTab === 'builder') {
    $builderAction = isset($_POST['builder_action']) ? trim((string)$_POST['builder_action']) : '';
    if ($builderAction !== '') {
        try {
            $builderMutatingActions = array(
                'save_report','delete_report',
                'add_field_column','add_catalog_column',
                'update_column','delete_column',
                'add_filter','update_filter','delete_filter'
            );
            if (
                $builderState['selected_report_id'] > 0
                && in_array($builderAction, $builderMutatingActions, true)
            ) {
                $preSets = pms_call_procedure('sp_report_definition_data', array(
                    $companyCode,
                    $builderState['selected_report_id'],
                    null,
                    0
                ));
                $preHeader = isset($preSets[1][0]) ? $preSets[1][0] : array();
                $preKey = isset($preHeader['report_key']) ? strtolower(trim((string)$preHeader['report_key'])) : '';
                if ($preKey !== '' && in_array($preKey, $builderFixedReportKeys, true)) {
                    throw new RuntimeException('Este reporte es fijo. Usa su vista dedicada y no el configurador.');
                }
            }

            if ($builderAction === 'save_report') {
                if ($builderState['report_name'] === '') {
                    throw new RuntimeException('El nombre del reporte es obligatorio.');
                }
                $headerSets = pms_call_procedure('sp_report_definition_upsert', array(
                    $builderState['selected_report_id'] > 0 ? 'update' : 'create',
                    $companyCode,
                    $builderState['selected_report_id'] > 0 ? $builderState['selected_report_id'] : null,
                    $builderState['report_key'] !== '' ? $builderState['report_key'] : null,
                    $builderState['report_name'],
                    $builderState['report_type'],
                    $builderState['report_type'] === 'line_item' ? $builderState['line_item_type_scope'] : null,
                    $builderState['description'] !== '' ? $builderState['description'] : null,
                    (int)$currentUser['id_user']
                ));
                $savedHeader = isset($headerSets[0][0]) ? $headerSets[0][0] : array();
                if (isset($savedHeader['id_report_config'])) {
                    $builderState['selected_report_id'] = (int)$savedHeader['id_report_config'];
                }
                $builderMessage = 'Reporte guardado.';
            } elseif ($builderAction === 'delete_report') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte para eliminar.');
                }
                pms_call_procedure('sp_report_definition_upsert', array(
                    'delete',
                    $companyCode,
                    $builderState['selected_report_id'],
                    null,
                    'deleted',
                    'reservation',
                    null,
                    null,
                    (int)$currentUser['id_user']
                ));
                $builderState['selected_report_id'] = 0;
                $builderMessage = 'Reporte eliminado.';
            } elseif ($builderAction === 'add_field_column') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte antes de agregar columnas.');
                }
                $fieldKey = isset($_POST['builder_add_field_key']) ? trim((string)$_POST['builder_add_field_key']) : '';
                if ($fieldKey === '') {
                    throw new RuntimeException('Selecciona un campo base.');
                }
                pms_call_procedure('sp_report_definition_column_upsert', array(
                    'create',
                    null,
                    $builderState['selected_report_id'],
                    null,
                    'field',
                    $fieldKey,
                    null,
                    isset($_POST['builder_add_field_display']) ? trim((string)$_POST['builder_add_field_display']) : null,
                    isset($_POST['builder_add_field_group']) ? trim((string)$_POST['builder_add_field_group']) : null,
                    isset($_POST['builder_add_field_type']) ? trim((string)$_POST['builder_add_field_type']) : null,
                    isset($_POST['builder_add_field_aggregation']) ? trim((string)$_POST['builder_add_field_aggregation']) : null,
                    null,
                    isset($_POST['builder_add_field_order']) ? (int)$_POST['builder_add_field_order'] : 1,
                    isset($_POST['builder_add_field_visible']) ? 1 : 0,
                    isset($_POST['builder_add_field_filterable']) ? 1 : 0,
                    isset($_POST['builder_add_field_operator']) ? trim((string)$_POST['builder_add_field_operator']) : null,
                    (int)$currentUser['id_user']
                ));
                $builderMessage = 'Columna base agregada.';
            } elseif ($builderAction === 'add_catalog_column') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte antes de agregar columnas.');
                }
                $catalogId = isset($_POST['builder_add_catalog_id']) ? (int)$_POST['builder_add_catalog_id'] : 0;
                if ($catalogId <= 0) {
                    throw new RuntimeException('Selecciona un line item catalog.');
                }
                $metricKey = isset($_POST['builder_add_catalog_metric']) ? trim((string)$_POST['builder_add_catalog_metric']) : 'sum_amount';
                if (!isset($builderCatalogMetricOptions[$metricKey])) {
                    $metricKey = 'sum_amount';
                }
                $metricCfg = $builderCatalogMetricOptions[$metricKey];
                $columnKey = 'catalog_' . $catalogId . '_' . $metricKey;
                $catalogDisplay = isset($_POST['builder_add_catalog_display']) ? trim((string)$_POST['builder_add_catalog_display']) : '';
                if ($catalogDisplay === '') {
                    $catalogDisplay = 'Catalogo #' . $catalogId . ' | ' . $metricCfg['label'];
                }
                pms_call_procedure('sp_report_definition_column_upsert', array(
                    'create',
                    null,
                    $builderState['selected_report_id'],
                    $columnKey,
                    'line_item_catalog',
                    null,
                    $catalogId,
                    $catalogDisplay,
                    isset($_POST['builder_add_catalog_group']) ? trim((string)$_POST['builder_add_catalog_group']) : null,
                    isset($metricCfg['data_type']) ? (string)$metricCfg['data_type'] : 'money',
                    isset($metricCfg['aggregation']) ? (string)$metricCfg['aggregation'] : 'sum_amount',
                    null,
                    isset($_POST['builder_add_catalog_order']) ? (int)$_POST['builder_add_catalog_order'] : 1,
                    isset($_POST['builder_add_catalog_visible']) ? 1 : 0,
                    isset($_POST['builder_add_catalog_filterable']) ? 1 : 0,
                    isset($metricCfg['operator']) ? (string)$metricCfg['operator'] : 'eq',
                    (int)$currentUser['id_user']
                ));
                $builderMessage = 'Columna de catalogo agregada.';
            } elseif ($builderAction === 'update_column') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte.');
                }
                $columnId = isset($_POST['builder_column_id']) ? (int)$_POST['builder_column_id'] : 0;
                if ($columnId <= 0) {
                    throw new RuntimeException('Columna invalida.');
                }
                pms_call_procedure('sp_report_definition_column_upsert', array(
                    'update',
                    $columnId,
                    $builderState['selected_report_id'],
                    isset($_POST['builder_column_key']) ? trim((string)$_POST['builder_column_key']) : null,
                    isset($_POST['builder_column_source']) ? trim((string)$_POST['builder_column_source']) : null,
                    isset($_POST['builder_column_source_field_key']) ? trim((string)$_POST['builder_column_source_field_key']) : null,
                    isset($_POST['builder_column_catalog_id']) ? (int)$_POST['builder_column_catalog_id'] : null,
                    isset($_POST['builder_column_display']) ? trim((string)$_POST['builder_column_display']) : null,
                    isset($_POST['builder_column_group']) ? trim((string)$_POST['builder_column_group']) : null,
                    isset($_POST['builder_column_data_type']) ? trim((string)$_POST['builder_column_data_type']) : null,
                    isset($_POST['builder_column_aggregation']) ? trim((string)$_POST['builder_column_aggregation']) : null,
                    isset($_POST['builder_column_format_hint']) ? trim((string)$_POST['builder_column_format_hint']) : null,
                    isset($_POST['builder_column_order']) ? (int)$_POST['builder_column_order'] : 1,
                    isset($_POST['builder_column_visible']) ? 1 : 0,
                    isset($_POST['builder_column_filterable']) ? 1 : 0,
                    isset($_POST['builder_column_operator']) ? trim((string)$_POST['builder_column_operator']) : null,
                    (int)$currentUser['id_user']
                ));
                $builderMessage = 'Columna actualizada.';
            } elseif ($builderAction === 'delete_column') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte.');
                }
                $columnId = isset($_POST['builder_column_id']) ? (int)$_POST['builder_column_id'] : 0;
                if ($columnId <= 0) {
                    throw new RuntimeException('Columna invalida.');
                }
                pms_call_procedure('sp_report_definition_column_upsert', array(
                    'delete',
                    $columnId,
                    $builderState['selected_report_id'],
                    null,
                    'field',
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    1,
                    1,
                    1,
                    null,
                    (int)$currentUser['id_user']
                ));
                $builderMessage = 'Columna eliminada.';
            } elseif ($builderAction === 'add_filter') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte antes de agregar filtros.');
                }
                $filterKey = isset($_POST['builder_add_filter_key']) ? trim((string)$_POST['builder_add_filter_key']) : '';
                if ($filterKey === '') {
                    throw new RuntimeException('Selecciona un campo para el filtro.');
                }
                pms_call_procedure('sp_report_definition_filter_upsert', array(
                    'create',
                    null,
                    $builderState['selected_report_id'],
                    $filterKey,
                    isset($_POST['builder_add_filter_operator']) ? trim((string)$_POST['builder_add_filter_operator']) : 'eq',
                    isset($_POST['builder_add_filter_value']) ? trim((string)$_POST['builder_add_filter_value']) : null,
                    isset($_POST['builder_add_filter_from']) ? trim((string)$_POST['builder_add_filter_from']) : null,
                    isset($_POST['builder_add_filter_to']) ? trim((string)$_POST['builder_add_filter_to']) : null,
                    isset($_POST['builder_add_filter_list']) ? trim((string)$_POST['builder_add_filter_list']) : null,
                    isset($_POST['builder_add_filter_logic']) ? trim((string)$_POST['builder_add_filter_logic']) : 'AND',
                    isset($_POST['builder_add_filter_order']) ? (int)$_POST['builder_add_filter_order'] : 1,
                    1,
                    (int)$currentUser['id_user']
                ));
                $builderMessage = 'Filtro agregado.';
            } elseif ($builderAction === 'update_filter') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte.');
                }
                $filterId = isset($_POST['builder_filter_id']) ? (int)$_POST['builder_filter_id'] : 0;
                if ($filterId <= 0) {
                    throw new RuntimeException('Filtro invalido.');
                }
                pms_call_procedure('sp_report_definition_filter_upsert', array(
                    'update',
                    $filterId,
                    $builderState['selected_report_id'],
                    isset($_POST['builder_filter_key']) ? trim((string)$_POST['builder_filter_key']) : '',
                    isset($_POST['builder_filter_operator']) ? trim((string)$_POST['builder_filter_operator']) : 'eq',
                    isset($_POST['builder_filter_value']) ? trim((string)$_POST['builder_filter_value']) : null,
                    isset($_POST['builder_filter_from']) ? trim((string)$_POST['builder_filter_from']) : null,
                    isset($_POST['builder_filter_to']) ? trim((string)$_POST['builder_filter_to']) : null,
                    isset($_POST['builder_filter_list']) ? trim((string)$_POST['builder_filter_list']) : null,
                    isset($_POST['builder_filter_logic']) ? trim((string)$_POST['builder_filter_logic']) : 'AND',
                    isset($_POST['builder_filter_order']) ? (int)$_POST['builder_filter_order'] : 1,
                    isset($_POST['builder_filter_active']) ? 1 : 0,
                    (int)$currentUser['id_user']
                ));
                $builderMessage = 'Filtro actualizado.';
            } elseif ($builderAction === 'delete_filter') {
                if ($builderState['selected_report_id'] <= 0) {
                    throw new RuntimeException('Selecciona un reporte.');
                }
                $filterId = isset($_POST['builder_filter_id']) ? (int)$_POST['builder_filter_id'] : 0;
                if ($filterId <= 0) {
                    throw new RuntimeException('Filtro invalido.');
                }
                pms_call_procedure('sp_report_definition_filter_upsert', array(
                    'delete',
                    $filterId,
                    $builderState['selected_report_id'],
                    'reservation_code',
                    'eq',
                    null,
                    null,
                    null,
                    null,
                    'AND',
                    1,
                    0,
                    (int)$currentUser['id_user']
                ));
                $builderMessage = 'Filtro eliminado.';
            }
        } catch (Exception $e) {
            $builderError = $e->getMessage();
        }
    }

    try {
        $definitionSets = pms_call_procedure('sp_report_definition_data', array(
            $companyCode,
            $builderState['selected_report_id'] > 0 ? $builderState['selected_report_id'] : null,
            null,
            0
        ));
        $builderReports = isset($definitionSets[0]) ? $definitionSets[0] : array();
        $builderConfigurableReports = array();
        $builderFixedReports = array();
        foreach ($builderReports as $reportRow) {
            $reportKey = isset($reportRow['report_key']) ? strtolower(trim((string)$reportRow['report_key'])) : '';
            if ($reportKey !== '' && in_array($reportKey, $builderFixedReportKeys, true)) {
                $builderFixedReports[] = $reportRow;
            } else {
                $builderConfigurableReports[] = $reportRow;
            }
        }
        $builderSelectedReport = isset($definitionSets[1][0]) ? $definitionSets[1][0] : array();
        $builderColumns = isset($definitionSets[2]) ? $definitionSets[2] : array();
        $builderFilters = isset($definitionSets[3]) ? $definitionSets[3] : array();

        if (empty($builderSelectedReport) && !empty($builderConfigurableReports)) {
            $builderState['selected_report_id'] = isset($builderConfigurableReports[0]['id_report_config']) ? (int)$builderConfigurableReports[0]['id_report_config'] : 0;
            if ($builderState['selected_report_id'] > 0) {
                $definitionSets = pms_call_procedure('sp_report_definition_data', array(
                    $companyCode,
                    $builderState['selected_report_id'],
                    null,
                    0
                ));
                $builderReports = isset($definitionSets[0]) ? $definitionSets[0] : array();
                $builderConfigurableReports = array();
                $builderFixedReports = array();
                foreach ($builderReports as $reportRow) {
                    $reportKey = isset($reportRow['report_key']) ? strtolower(trim((string)$reportRow['report_key'])) : '';
                    if ($reportKey !== '' && in_array($reportKey, $builderFixedReportKeys, true)) {
                        $builderFixedReports[] = $reportRow;
                    } else {
                        $builderConfigurableReports[] = $reportRow;
                    }
                }
                $builderSelectedReport = isset($definitionSets[1][0]) ? $definitionSets[1][0] : array();
                $builderColumns = isset($definitionSets[2]) ? $definitionSets[2] : array();
                $builderFilters = isset($definitionSets[3]) ? $definitionSets[3] : array();
            }
        }
    } catch (Exception $e) {
        $builderError = $e->getMessage();
        $builderReports = array();
        $builderConfigurableReports = array();
        $builderFixedReports = array();
        $builderSelectedReport = array();
        $builderColumns = array();
        $builderFilters = array();
    }

    if (!empty($builderSelectedReport)) {
        $builderState['selected_report_id'] = isset($builderSelectedReport['id_report_config']) ? (int)$builderSelectedReport['id_report_config'] : $builderState['selected_report_id'];
        $builderState['report_key'] = isset($builderSelectedReport['report_key']) ? (string)$builderSelectedReport['report_key'] : $builderState['report_key'];
        $builderState['report_name'] = isset($builderSelectedReport['report_name']) ? (string)$builderSelectedReport['report_name'] : $builderState['report_name'];
        $builderState['report_type'] = isset($builderSelectedReport['report_type']) ? (string)$builderSelectedReport['report_type'] : $builderState['report_type'];
        $builderState['line_item_type_scope'] = isset($builderSelectedReport['line_item_type_scope']) && (string)$builderSelectedReport['line_item_type_scope'] !== ''
            ? (string)$builderSelectedReport['line_item_type_scope']
            : $builderState['line_item_type_scope'];
        $builderState['description'] = isset($builderSelectedReport['description']) ? (string)$builderSelectedReport['description'] : $builderState['description'];
        $builderSelectedReportIsFixed = in_array(strtolower(trim($builderState['report_key'])), $builderFixedReportKeys, true) ? 1 : 0;
    }

    try {
        $fieldSets = pms_call_procedure('sp_report_field_catalog_data', array(
            $companyCode,
            $builderState['property_code'] === '' ? null : $builderState['property_code'],
            $builderState['report_type']
        ));
        $builderFieldRows = isset($fieldSets[0]) ? $fieldSets[0] : array();
        $builderCatalogRows = isset($fieldSets[1]) ? $fieldSets[1] : array();
    } catch (Exception $e) {
        if ($builderError === '') {
            $builderError = $e->getMessage();
        }
        $builderFieldRows = array();
        $builderCatalogRows = array();
    }

    if ($builderState['selected_report_id'] > 0) {
        try {
            $runSets = pms_call_procedure('sp_report_definition_run_data', array(
                $companyCode,
                $builderState['selected_report_id'],
                $builderState['date_from'] !== '' ? $builderState['date_from'] : null,
                $builderState['date_to'] !== '' ? $builderState['date_to'] : null,
                $builderState['limit']
            ));
            $builderRunColumns = isset($runSets[1]) ? $runSets[1] : array();
            $builderRunRows = isset($runSets[3]) ? $runSets[3] : array();
        } catch (Exception $e) {
            if ($builderError === '') {
                $builderError = $e->getMessage();
            }
            $builderRunColumns = array();
            $builderRunRows = array();
        }
    }
}

$accountingConfig = reports_load_report_config($companyCode, 'accounting');
$accountingSaveConfig = isset($_POST['accounting_save_config']) && (string)$_POST['accounting_save_config'] === '1';
$accountingSortKey = isset($_POST['accounting_sort']) ? (string)$_POST['accounting_sort'] : 'payment_date';
$accountingSortDir = isset($_POST['accounting_sort_dir']) ? (string)$_POST['accounting_sort_dir'] : 'desc';
$accountingGroupBy = isset($_POST['accounting_group_by']) ? (string)$_POST['accounting_group_by'] : 'payment_method';
$accountingColumnFilters = array();
foreach ($_POST as $key => $val) {
    if (strpos($key, 'accounting_filter_') === 0) {
        $filterKey = substr($key, strlen('accounting_filter_'));
        $accountingColumnFilters[$filterKey] = is_array($val) ? '' : trim((string)$val);
    }
}
$accountingFilters = array(
    'property_code' => isset($_POST['accounting_report_property']) ? strtoupper((string)$_POST['accounting_report_property']) : '',
    'date_from' => isset($_POST['accounting_report_from']) ? (string)$_POST['accounting_report_from'] : '',
    'date_to' => isset($_POST['accounting_report_to']) ? (string)$_POST['accounting_report_to'] : '',
    'resico_catalog_id' => isset($_POST['accounting_resico_catalog_id'])
        ? (int)$_POST['accounting_resico_catalog_id']
        : (int)$accountingConfig['resico_catalog_id'],
    'column_order' => isset($_POST['accounting_column_order']) ? (string)$_POST['accounting_column_order'] : $accountingConfig['column_order'],
    'lodging_catalog_ids' => isset($_POST['accounting_lodging_ids']) && is_array($_POST['accounting_lodging_ids'])
        ? array_values(array_map('intval', $_POST['accounting_lodging_ids']))
        : ($accountingSaveConfig ? array() : $accountingConfig['lodging_catalog_ids']),
    'extra_catalog_ids' => isset($_POST['accounting_extra_ids']) && is_array($_POST['accounting_extra_ids'])
        ? array_values(array_map('intval', $_POST['accounting_extra_ids']))
        : ($accountingSaveConfig ? array() : $accountingConfig['extra_catalog_ids']),
    'save_config' => $accountingSaveConfig ? 1 : 0,
    'sort_key' => $accountingSortKey !== '' ? $accountingSortKey : 'payment_date',
    'sort_dir' => $accountingSortDir === 'asc' ? 'asc' : 'desc',
    'group_by' => $accountingGroupBy !== '' ? $accountingGroupBy : 'payment_method',
    'column_filters' => $accountingColumnFilters,
    'hide_zero_columns' => isset($_POST['accounting_hide_zero_columns']) ? 1 : 0
);

if ($accountingFilters['date_from'] === '') {
    $accountingFilters['date_from'] = $defaultFrom;
}
if ($accountingFilters['date_to'] === '') {
    $accountingFilters['date_to'] = $defaultTo;
}

$catalogOptions = reports_fetch_sale_item_catalogs($companyCode, $accountingFilters['property_code']);
$saleItemCatalogs = array();
$componentCatalogs = array();
$saleItemCategoryOptions = array();
$saleItemSubcategoryOptions = array();
foreach ($catalogOptions as $opt) {
    $catalogId = isset($opt['id_line_item_catalog']) ? (int)$opt['id_line_item_catalog'] : 0;
    if ($catalogId <= 0) {
        continue;
    }
    $catalogType = isset($opt['catalog_type']) ? (string)$opt['catalog_type'] : '';
    $categoryName = isset($opt['category_name']) ? (string)$opt['category_name'] : '';
    $subName = isset($opt['subcategory_name']) ? (string)$opt['subcategory_name'] : '';
    $itemName = (string)$opt['item_name'];
    $labelParts = array();
    if ($catalogType === 'sale_item') {
        $labelParts[] = $categoryName;
        $labelParts[] = $subName;
        $labelParts[] = $itemName;
        $labelParts = array_map('trim', $labelParts);
        $labelParts = array_filter($labelParts, function ($val) { return $val !== ''; });
        $label = implode('/', $labelParts);
    } else {
        $label = $itemName;
        $label = preg_replace('/^Impuesto\s*:\s*/i', '', $label);
        $label = preg_replace('/\s*\([^)]*\)\s*$/', '', $label);
        $label = trim($label);
    }
    $componentCatalogs[$catalogId] = array(
        'id' => $catalogId,
        'type' => $catalogType,
        'label' => $label,
        'category' => trim((string)$categoryName),
        'subcategory' => trim((string)$subName)
    );
    if ($catalogType === 'sale_item') {
        $saleItemCatalogs[$catalogId] = array(
            'id' => $catalogId,
            'label' => $label,
            'category' => trim((string)$categoryName),
            'subcategory' => trim((string)$subName)
        );
        if (trim((string)$categoryName) !== '') {
            $saleItemCategoryOptions[trim((string)$categoryName)] = true;
        }
        if (trim((string)$subName) !== '') {
            $saleItemSubcategoryOptions[trim((string)$subName)] = true;
        }
    }
}
if (!empty($saleItemCatalogs)) {
    uasort($saleItemCatalogs, function ($a, $b) {
        return strcasecmp(
            isset($a['label']) ? (string)$a['label'] : '',
            isset($b['label']) ? (string)$b['label'] : ''
        );
    });
}
$saleItemCategoryOptions = array_keys($saleItemCategoryOptions);
$saleItemSubcategoryOptions = array_keys($saleItemSubcategoryOptions);
sort($saleItemCategoryOptions, SORT_NATURAL | SORT_FLAG_CASE);
sort($saleItemSubcategoryOptions, SORT_NATURAL | SORT_FLAG_CASE);
if (
    !empty($accountingFilters['resico_catalog_id'])
    && !isset($componentCatalogs[(int)$accountingFilters['resico_catalog_id']])
) {
    $accountingFilters['resico_catalog_id'] = 0;
}

$catalogRelations = reports_fetch_catalog_relations($companyCode, $accountingFilters['property_code']);
$catalogRelationOptions = array();
foreach ($catalogRelations as $rel) {
    $parentCatalogId = isset($rel['parent_catalog_id']) ? (int)$rel['parent_catalog_id'] : 0;
    $childCatalogId = isset($rel['child_catalog_id']) ? (int)$rel['child_catalog_id'] : 0;
    if ($parentCatalogId <= 0 || $childCatalogId <= 0) {
        continue;
    }
    if (!isset($componentCatalogs[$parentCatalogId]) || !isset($componentCatalogs[$childCatalogId])) {
        continue;
    }
    $relationKey = $parentCatalogId . ':' . $childCatalogId;
    if (isset($catalogRelationOptions[$relationKey])) {
        continue;
    }
    $parentLabel = isset($componentCatalogs[$parentCatalogId]['label']) ? (string)$componentCatalogs[$parentCatalogId]['label'] : ('Catalogo #' . $parentCatalogId);
    $childLabel = isset($componentCatalogs[$childCatalogId]['label']) ? (string)$componentCatalogs[$childCatalogId]['label'] : ('Catalogo #' . $childCatalogId);
    $catalogRelationOptions[$relationKey] = array(
        'key' => $relationKey,
        'parent_catalog_id' => $parentCatalogId,
        'catalog_id' => $childCatalogId,
        'source_label' => $parentLabel . ' / ' . $childLabel,
        'child_category' => isset($componentCatalogs[$childCatalogId]['category']) ? (string)$componentCatalogs[$childCatalogId]['category'] : '',
        'child_subcategory' => isset($componentCatalogs[$childCatalogId]['subcategory']) ? (string)$componentCatalogs[$childCatalogId]['subcategory'] : ''
    );
}
if (!empty($catalogRelationOptions)) {
    uasort($catalogRelationOptions, function ($a, $b) {
        return strcasecmp(
            isset($a['source_label']) ? (string)$a['source_label'] : '',
            isset($b['source_label']) ? (string)$b['source_label'] : ''
        );
    });
}
$relationCategoryOptions = array();
$relationSubcategoryOptions = array();
foreach ($catalogRelationOptions as $relationOpt) {
    $rCategory = trim((string)(isset($relationOpt['child_category']) ? $relationOpt['child_category'] : ''));
    $rSubcategory = trim((string)(isset($relationOpt['child_subcategory']) ? $relationOpt['child_subcategory'] : ''));
    if ($rCategory !== '') {
        $relationCategoryOptions[$rCategory] = true;
    }
    if ($rSubcategory !== '') {
        $relationSubcategoryOptions[$rSubcategory] = true;
    }
}
$relationCategoryOptions = array_keys($relationCategoryOptions);
$relationSubcategoryOptions = array_keys($relationSubcategoryOptions);
sort($relationCategoryOptions, SORT_NATURAL | SORT_FLAG_CASE);
sort($relationSubcategoryOptions, SORT_NATURAL | SORT_FLAG_CASE);

$accountingExtraColumns = array();
$extraByKey = array();
$storedOrderKeys = array_values(array_filter(array_map('trim', explode(',', (string)$accountingFilters['column_order']))));
foreach ($storedOrderKeys as $columnKey) {
    $parsedExtra = reports_parse_extra_column_key($columnKey);
    if (!$parsedExtra) {
        continue;
    }
    $isMulti = !empty($parsedExtra['is_multi']);
    $relationPairs = isset($parsedExtra['relation_pairs']) && is_array($parsedExtra['relation_pairs'])
        ? $parsedExtra['relation_pairs']
        : array();
    if (empty($relationPairs)) {
        $relationPairs = array(array(
            'parent_catalog_id' => isset($parsedExtra['parent_catalog_id']) ? (int)$parsedExtra['parent_catalog_id'] : 0,
            'catalog_id' => isset($parsedExtra['catalog_id']) ? (int)$parsedExtra['catalog_id'] : 0
        ));
    }
    $normalizedPairs = array();
    $pairMap = array();
    foreach ($relationPairs as $pair) {
        $pairParent = isset($pair['parent_catalog_id']) ? (int)$pair['parent_catalog_id'] : 0;
        $pairCatalog = isset($pair['catalog_id']) ? (int)$pair['catalog_id'] : 0;
        if ($pairCatalog <= 0 || !isset($componentCatalogs[$pairCatalog])) {
            continue;
        }
        $pairKey = $pairParent . ':' . $pairCatalog;
        if (isset($pairMap[$pairKey])) {
            continue;
        }
        $pairMap[$pairKey] = true;
        $normalizedPairs[] = array(
            'parent_catalog_id' => $pairParent,
            'catalog_id' => $pairCatalog
        );
    }
    if (empty($normalizedPairs)) {
        continue;
    }
    usort($normalizedPairs, function ($a, $b) {
        $aKey = (int)$a['parent_catalog_id'] . ':' . (int)$a['catalog_id'];
        $bKey = (int)$b['parent_catalog_id'] . ':' . (int)$b['catalog_id'];
        return strcmp($aKey, $bKey);
    });
    $sourceParts = array();
    foreach ($normalizedPairs as $pair) {
        $pairParent = (int)$pair['parent_catalog_id'];
        $pairCatalog = (int)$pair['catalog_id'];
        $relationKey = $pairParent . ':' . $pairCatalog;
        if (isset($catalogRelationOptions[$relationKey])) {
            $sourceParts[] = (string)$catalogRelationOptions[$relationKey]['source_label'];
        } elseif ($pairParent > 0 && isset($componentCatalogs[$pairParent])) {
            $sourceParts[] = (string)$componentCatalogs[$pairParent]['label'] . ' / ' . (string)$componentCatalogs[$pairCatalog]['label'];
        } else {
            $sourceParts[] = (string)$componentCatalogs[$pairCatalog]['label'];
        }
    }
    $sourceParts = array_values(array_unique(array_filter(array_map('trim', $sourceParts), function ($val) { return $val !== ''; })));
    $sourceLabel = implode(' | ', $sourceParts);
    $displayName = trim((string)$parsedExtra['display_name']);
    if ($displayName === '') {
        $displayName = $sourceLabel;
    }
    $normalizedKey = $isMulti
        ? reports_build_multi_extra_column_key($normalizedPairs, $displayName)
        : reports_build_extra_column_key(
            (int)$normalizedPairs[0]['parent_catalog_id'],
            (int)$normalizedPairs[0]['catalog_id'],
            $displayName
        );
    if ($normalizedKey === '') {
        continue;
    }
    $primaryParentCatalogId = (int)$normalizedPairs[0]['parent_catalog_id'];
    $primaryCatalogId = (int)$normalizedPairs[0]['catalog_id'];
    $relationSignature = implode('|', array_map(function ($pair) {
        return ((int)$pair['parent_catalog_id']) . ':' . ((int)$pair['catalog_id']);
    }, $normalizedPairs));
    if (isset($extraByKey[$normalizedKey])) {
        continue;
    }
    $extraByKey[$normalizedKey] = array(
        'key' => $normalizedKey,
        'is_multi' => $isMulti ? 1 : 0,
        'parent_catalog_id' => $primaryParentCatalogId,
        'catalog_id' => $primaryCatalogId,
        'relation_pairs' => $normalizedPairs,
        'relation_signature' => $relationSignature,
        'display_name' => $displayName,
        'source_label' => $sourceLabel
    );
}

if (!$extraByKey && !empty($accountingFilters['extra_catalog_ids'])) {
    foreach ($accountingFilters['extra_catalog_ids'] as $legacyCatalogId) {
        $legacyCatalogId = (int)$legacyCatalogId;
        if ($legacyCatalogId <= 0 || !isset($componentCatalogs[$legacyCatalogId])) {
            continue;
        }
        $matchedRelation = null;
        foreach ($catalogRelationOptions as $relationOpt) {
            if ((int)$relationOpt['catalog_id'] === $legacyCatalogId) {
                $matchedRelation = $relationOpt;
                break;
            }
        }
        $parentCatalogId = $matchedRelation ? (int)$matchedRelation['parent_catalog_id'] : 0;
        $sourceLabel = $matchedRelation
            ? (string)$matchedRelation['source_label']
            : (string)$componentCatalogs[$legacyCatalogId]['label'];
        $key = reports_build_extra_column_key($parentCatalogId, $legacyCatalogId, $sourceLabel);
        if (isset($extraByKey[$key])) {
            continue;
        }
        $extraByKey[$key] = array(
            'key' => $key,
            'is_multi' => 0,
            'parent_catalog_id' => $parentCatalogId,
            'catalog_id' => $legacyCatalogId,
            'relation_pairs' => array(
                array('parent_catalog_id' => $parentCatalogId, 'catalog_id' => $legacyCatalogId)
            ),
            'relation_signature' => $parentCatalogId . ':' . $legacyCatalogId,
            'display_name' => $sourceLabel,
            'source_label' => $sourceLabel
        );
    }
}
$accountingExtraColumns = array_values($extraByKey);

// Fallback defensivo: si no hay conceptos de hospedaje configurados ni enviados en POST,
// usar todos los conceptos sale_item disponibles para evitar reportes vacios en contabilidad.
if (
    empty($accountingFilters['lodging_catalog_ids'])
    && !isset($_POST['accounting_lodging_ids'])
    && !$accountingSaveConfig
) {
    $accountingFilters['lodging_catalog_ids'] = array_values(array_map('intval', array_keys($saleItemCatalogs)));
}

if ($accountingFilters['save_config']) {
    $itemsToSave = array();
    foreach ($accountingFilters['lodging_catalog_ids'] as $id) {
        $itemsToSave[] = array('id' => (int)$id, 'role' => 'lodging');
    }
    foreach ($accountingExtraColumns as $extraDef) {
        $relationPairs = isset($extraDef['relation_pairs']) && is_array($extraDef['relation_pairs'])
            ? $extraDef['relation_pairs']
            : array();
        if (!empty($relationPairs)) {
            foreach ($relationPairs as $pair) {
                $pairCatalogId = isset($pair['catalog_id']) ? (int)$pair['catalog_id'] : 0;
                if ($pairCatalogId <= 0) {
                    continue;
                }
                $itemsToSave[] = array(
                    'id' => $pairCatalogId,
                    'role' => 'extra'
                );
            }
        } else {
            $itemsToSave[] = array(
                'id' => (int)$extraDef['catalog_id'],
                'role' => 'extra'
            );
        }
    }
    if (!empty($accountingFilters['resico_catalog_id'])) {
        $itemsToSave[] = array(
            'id' => (int)$accountingFilters['resico_catalog_id'],
            'role' => 'ish'
        );
    }
    reports_save_report_config(
        $companyCode,
        'accounting',
        'Reporte de contabilidad',
        isset($accountingFilters['column_order']) ? (string)$accountingFilters['column_order'] : '',
        $itemsToSave,
        $currentUser['id_user']
    );
}

$incomeReportFilters = array(
    'property_code' => isset($_POST['income_report_property']) ? strtoupper((string)$_POST['income_report_property']) : '',
    'date_from' => isset($_POST['income_report_from']) ? (string)$_POST['income_report_from'] : $defaultFrom,
    'date_to' => isset($_POST['income_report_to']) ? (string)$_POST['income_report_to'] : $defaultTo,
    'status' => isset($_POST['income_report_status']) ? trim((string)$_POST['income_report_status']) : '',
    'type' => isset($_POST['income_report_type']) ? trim((string)$_POST['income_report_type']) : '',
    'catalog_id' => isset($_POST['income_report_catalog_id']) ? (int)$_POST['income_report_catalog_id'] : 0,
    'search' => isset($_POST['income_report_search']) ? trim((string)$_POST['income_report_search']) : '',
    'min_amount' => isset($_POST['income_report_min']) ? (string)$_POST['income_report_min'] : '',
    'max_amount' => isset($_POST['income_report_max']) ? (string)$_POST['income_report_max'] : '',
    'show_inactive' => isset($_POST['income_report_show_inactive']) ? 1 : 0
);

$incomeCatalogs = array();

function reports_filter_reservations($rows, $filters)
{
    $filtered = array();
    $search = mb_strtolower($filters['search']);
    foreach ($rows as $row) {
        if ($filters['property_code'] !== '' && strtoupper((string)$row['property_code']) !== $filters['property_code']) {
            continue;
        }
        if ($filters['status'] !== '' && strtolower((string)$row['status']) !== strtolower($filters['status'])) {
            continue;
        }
        if ($filters['source'] !== '' && strtolower((string)$row['source']) !== strtolower($filters['source'])) {
            continue;
        }
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array(
                (string)$row['reservation_code'],
                (string)$row['guest_names'],
                (string)$row['guest_last_name'],
                (string)$row['guest_email'],
                (string)$row['room_name'],
                (string)$row['property_name']
            )));
            if (strpos($haystack, $search) === false) {
                continue;
            }
        }
        $filtered[] = $row;
    }
    return $filtered;
}

$reservations = array();
$totalsByReservation = array();
if ($activeTab === 'reservations' || $activeTab === 'custom') {
    try {
        $reservationSets = pms_call_procedure('sp_list_reservations_by_company', array(
            $companyCode,
            $activeTab === 'custom'
                ? ($customFilters['date_from'] !== '' ? $customFilters['date_from'] : null)
                : ($resFilters['date_from'] !== '' ? $resFilters['date_from'] : null),
            $activeTab === 'custom'
                ? ($customFilters['date_to'] !== '' ? $customFilters['date_to'] : null)
                : ($resFilters['date_to'] !== '' ? $resFilters['date_to'] : null)
        ));
        $rows = isset($reservationSets[0]) ? $reservationSets[0] : array();
        $filters = $activeTab === 'custom' ? $customFilters : $resFilters;
        $reservations = reports_filter_reservations($rows, $filters);
    } catch (Exception $e) {
        $reservations = array();
    }

    $reservationIds = array();
    foreach ($reservations as $row) {
        if (isset($row['id_reservation'])) {
            $reservationIds[] = (int)$row['id_reservation'];
        }
    }
    $reservationIds = array_values(array_filter($reservationIds, function ($val) { return $val > 0; }));
    $totalsByReservation = reports_fetch_totals_by_reservation($companyCode, $reservationIds);
}

$accountingRows = array();
$accountingDescendantMap = array();
$accountingSaleItemsById = array();
$accountingTaxBySaleItem = array();
$accountingSaleItemsByFolio = array();
$accountingBaseIdsByFolio = array();
if ($activeTab === 'accounting') {
    $lodgingIds = array_values(array_filter($accountingFilters['lodging_catalog_ids'], function ($val) { return (int)$val > 0; }));
    $lodgingIds = array_values(array_unique($lodgingIds));
    $lodgingCsv = $lodgingIds ? implode(',', $lodgingIds) : null;
    try {
        $sets = pms_call_procedure('sp_accounting_report_data', array(
            $companyCode,
            $accountingFilters['property_code'] === '' ? null : $accountingFilters['property_code'],
            $accountingFilters['date_from'] === '' ? null : $accountingFilters['date_from'],
            $accountingFilters['date_to'] === '' ? null : $accountingFilters['date_to'],
            $lodgingCsv
        ));
        $accountingRows = isset($sets[0]) ? $sets[0] : array();
        $allSaleItems = isset($sets[1]) ? $sets[1] : array();
        $taxItems = isset($sets[2]) ? $sets[2] : array();
    } catch (Exception $e) {
        $accountingRows = array();
        $allSaleItems = array();
        $taxItems = array();
    }

    foreach ($allSaleItems as $item) {
        $folioId = isset($item['id_folio']) ? (int)$item['id_folio'] : 0;
        if ($folioId <= 0) {
            continue;
        }
        if (!isset($accountingSaleItemsByFolio[$folioId])) {
            $accountingSaleItemsByFolio[$folioId] = array();
        }
        $accountingSaleItemsByFolio[$folioId][] = $item;
    }

    $baseIds = array();
    if ($lodgingIds) {
        foreach ($accountingSaleItemsByFolio as $folioId => $items) {
            foreach ($items as $item) {
                $catalogId = isset($item['id_sale_item_catalog']) ? (int)$item['id_sale_item_catalog'] : 0;
                if ($catalogId <= 0 || !in_array($catalogId, $lodgingIds, true)) {
                    continue;
                }
                $baseIds[] = (int)$item['id_line_item'];
                if (!isset($accountingBaseIdsByFolio[$folioId])) {
                    $accountingBaseIdsByFolio[$folioId] = array();
                }
                $accountingBaseIdsByFolio[$folioId][] = (int)$item['id_line_item'];
            }
        }
    }
    foreach ($accountingBaseIdsByFolio as $folioId => $ids) {
        $accountingBaseIdsByFolio[$folioId] = array_values(array_unique(array_filter($ids, function ($val) { return $val > 0; })));
    }
    $baseIds = array_values(array_unique(array_filter($baseIds, function ($val) { return $val > 0; })));

    list($accountingSaleItemsById, $accountingTaxBySaleItem, $accountingDescendantMap) =
        reports_build_descendant_sums($baseIds, $allSaleItems, $taxItems);

    if (!function_exists('reports_value_matches')) {
        function reports_value_matches($value, $needle)
        {
            $needle = trim((string)$needle);
            if ($needle === '') {
                return true;
            }
            $str = (string)$value;
            if (is_numeric($value)) {
                $num = (float)$value;
                if (preg_match('/^\s*(>=|<=|>|<)\s*(-?\d+(?:\.\d+)?)\s*$/', $needle, $m)) {
                    $target = (float)$m[2];
                    switch ($m[1]) {
                        case '>=': return $num >= $target;
                        case '<=': return $num <= $target;
                        case '>': return $num > $target;
                        case '<': return $num < $target;
                    }
                }
            }
            return stripos($str, $needle) !== false;
        }
    }

    $accountingRenderRows = array();
    foreach ($accountingRows as $row) {
        $folioId = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
        $baseIds = isset($accountingBaseIdsByFolio[$folioId]) ? $accountingBaseIdsByFolio[$folioId] : array();
        $treeIdsMap = array();
        $roomSum = 0;
        foreach ($baseIds as $baseId) {
            if (isset($accountingSaleItemsById[$baseId])) {
                $roomSum += (int)$accountingSaleItemsById[$baseId]['amount_cents'];
            }
            $desc = isset($accountingDescendantMap[$baseId]) ? $accountingDescendantMap[$baseId] : array($baseId);
            foreach ($desc as $id) {
                $treeIdsMap[$id] = true;
            }
        }
        $treeIds = array_keys($treeIdsMap);
        $currency = isset($row['folio_currency']) && $row['folio_currency'] !== '' ? $row['folio_currency'] : (isset($row['reservation_currency']) ? $row['reservation_currency'] : 'MXN');
        $guestName = trim((string)$row['guest_names'] . ' ' . (string)$row['guest_last_name']);
        $people = (int)$row['adults'] + (int)$row['children'];
        $paymentAmount = isset($row['payment_amount_cents']) ? (int)$row['payment_amount_cents'] : 0;
        $paymentMethod = isset($row['payment_method']) ? (string)$row['payment_method'] : '';
        $paymentDate = isset($row['payment_date']) ? (string)$row['payment_date'] : '';
        $rowSource = isset($row['reservation_source']) ? trim((string)$row['reservation_source']) : '';
        $otaAccountId = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
        $otaExternalCode = isset($row['ota_external_code']) ? trim((string)$row['ota_external_code']) : '';
        $otaName = isset($row['ota_name']) ? trim((string)$row['ota_name']) : '';
        $otaPlatform = isset($row['ota_platform']) ? trim((string)$row['ota_platform']) : '';
        $paymentCatalogName = isset($row['payment_catalog_name']) ? trim((string)$row['payment_catalog_name']) : '';
        $channelSortLabel = '';
        if ($otaAccountId > 0) {
            $channelSortLabel = $otaExternalCode !== '' ? $otaExternalCode : ($otaName !== '' ? $otaName : ($otaPlatform !== '' ? strtoupper($otaPlatform) : 'OTA'));
        } else {
            $channelSortLabel = $paymentCatalogName !== '' ? $paymentCatalogName : ($paymentMethod !== '' ? $paymentMethod : ($rowSource !== '' ? $rowSource : 'Sin categoria'));
        }
        $treeCatalogs = array();
        foreach ($treeIds as $saleItemIdInTree) {
            if (!isset($accountingSaleItemsById[$saleItemIdInTree])) {
                continue;
            }
            $cid = isset($accountingSaleItemsById[$saleItemIdInTree]['id_sale_item_catalog'])
                ? (int)$accountingSaleItemsById[$saleItemIdInTree]['id_sale_item_catalog']
                : 0;
            if ($cid > 0) {
                $treeCatalogs[$cid] = true;
            }
        }
        $taxCategory = (
            !empty($accountingFilters['resico_catalog_id'])
            && isset($treeCatalogs[(int)$accountingFilters['resico_catalog_id']])
        ) ? 'ISR RESICO' : 'ISR';
        $extraSums = array();
        foreach ($accountingExtraColumns as $extraDef) {
            $extraSums[(string)$extraDef['key']] = null;
        }
        if (!empty($accountingExtraColumns)) {
            $sumByCatalog = array();
            $sumByRelation = array();
            foreach ($treeIds as $saleItemId) {
                if (!isset($accountingSaleItemsById[$saleItemId])) {
                    continue;
                }
                $saleItem = $accountingSaleItemsById[$saleItemId];
                $catalogId = isset($saleItem['id_sale_item_catalog']) ? (int)$saleItem['id_sale_item_catalog'] : 0;
                if ($catalogId <= 0) {
                    continue;
                }
                $parentSaleItemId = isset($saleItem['id_parent_sale_item']) ? (int)$saleItem['id_parent_sale_item'] : 0;
                $parentCatalogId = 0;
                if ($parentSaleItemId > 0 && isset($accountingSaleItemsById[$parentSaleItemId])) {
                    $parentCatalogId = isset($accountingSaleItemsById[$parentSaleItemId]['id_sale_item_catalog'])
                        ? (int)$accountingSaleItemsById[$parentSaleItemId]['id_sale_item_catalog']
                        : 0;
                }
                $amountCents = isset($saleItem['amount_cents']) ? (int)$saleItem['amount_cents'] : 0;
                if (!isset($sumByCatalog[$catalogId])) {
                    $sumByCatalog[$catalogId] = 0;
                }
                $sumByCatalog[$catalogId] += $amountCents;
                $relationKey = $parentCatalogId . ':' . $catalogId;
                if (!isset($sumByRelation[$relationKey])) {
                    $sumByRelation[$relationKey] = 0;
                }
                $sumByRelation[$relationKey] += $amountCents;
            }
            foreach ($accountingExtraColumns as $extraDef) {
                $extraKey = isset($extraDef['key']) ? (string)$extraDef['key'] : '';
                if ($extraKey === '') {
                    continue;
                }
                $isMulti = !empty($extraDef['is_multi']);
                $relationPairs = isset($extraDef['relation_pairs']) && is_array($extraDef['relation_pairs'])
                    ? $extraDef['relation_pairs']
                    : array();
                if ($isMulti) {
                    $candidateValues = array();
                    foreach ($relationPairs as $pair) {
                        $pairParent = isset($pair['parent_catalog_id']) ? (int)$pair['parent_catalog_id'] : 0;
                        $pairCatalog = isset($pair['catalog_id']) ? (int)$pair['catalog_id'] : 0;
                        if ($pairCatalog <= 0) {
                            continue;
                        }
                        $pairKey = $pairParent . ':' . $pairCatalog;
                        $candidateValues[] = isset($sumByRelation[$pairKey]) ? (int)$sumByRelation[$pairKey] : 0;
                    }
                    $extraSums[$extraKey] = reports_pick_multi_relation_value($candidateValues);
                } else {
                    $requiredParentCatalog = isset($extraDef['parent_catalog_id']) ? (int)$extraDef['parent_catalog_id'] : 0;
                    $requiredCatalog = isset($extraDef['catalog_id']) ? (int)$extraDef['catalog_id'] : 0;
                    if ($requiredCatalog <= 0) {
                        continue;
                    }
                    if ($requiredParentCatalog > 0) {
                        $requiredKey = $requiredParentCatalog . ':' . $requiredCatalog;
                        $extraSums[$extraKey] = isset($sumByRelation[$requiredKey]) ? (int)$sumByRelation[$requiredKey] : 0;
                    } else {
                        $extraSums[$extraKey] = isset($sumByCatalog[$requiredCatalog]) ? (int)$sumByCatalog[$requiredCatalog] : 0;
                    }
                }
            }
        }

        $match = true;
        foreach ($accountingFilters['column_filters'] as $fKey => $fVal) {
            if ($fVal === '') {
                continue;
            }
            $value = '';
            if ($fKey === 'payment_date') {
                $value = $paymentDate;
            } elseif ($fKey === 'payment_method') {
                $value = $paymentMethod;
            } elseif ($fKey === 'payment_amount') {
                $value = $paymentAmount / 100;
            } elseif ($fKey === 'guest') {
                $value = $guestName;
            } elseif ($fKey === 'reservation') {
                $value = isset($row['reservation_code']) ? (string)$row['reservation_code'] : '';
            } elseif ($fKey === 'check_in') {
                $value = isset($row['check_in_date']) ? (string)$row['check_in_date'] : '';
            } elseif ($fKey === 'check_out') {
                $value = isset($row['check_out_date']) ? (string)$row['check_out_date'] : '';
            } elseif ($fKey === 'nights') {
                $value = isset($row['nights']) ? (int)$row['nights'] : 0;
            } elseif ($fKey === 'people') {
                $value = $people;
            } elseif ($fKey === 'room_price') {
                $value = $roomSum / 100;
            } elseif (strpos($fKey, 'extra:') === 0) {
                $extraVal = isset($extraSums[$fKey]) ? $extraSums[$fKey] : null;
                $value = is_numeric($extraVal) ? (((int)$extraVal) / 100) : '';
            }
            if (!reports_value_matches($value, $fVal)) {
                $match = false;
                break;
            }
        }
        if (!$match) {
            continue;
        }

        $accountingRenderRows[] = array(
            'row' => $row,
            'currency' => $currency,
            'guest' => $guestName,
            'people' => $people,
            'payment_amount' => $paymentAmount,
            'payment_method' => $paymentMethod,
            'payment_date' => $paymentDate,
            'room_price' => $roomSum,
            'tax_category' => $taxCategory,
            'channel_category' => $channelSortLabel,
            'tree_ids' => $treeIds,
            'extra_sums' => $extraSums
        );
    }

    $sortKey = $accountingFilters['sort_key'];
    $sortDir = $accountingFilters['sort_dir'];
    usort($accountingRenderRows, function ($a, $b) use ($sortKey, $sortDir) {
        $dir = $sortDir === 'asc' ? 1 : -1;
        $getValue = function ($item) use ($sortKey) {
            $row = $item['row'];
            if ($sortKey === 'payment_date') return $item['payment_date'];
            if ($sortKey === 'payment_method') return $item['payment_method'];
            if ($sortKey === 'payment_amount') return $item['payment_amount'];
            if ($sortKey === 'guest') return $item['guest'];
            if ($sortKey === 'reservation') return isset($row['reservation_code']) ? (string)$row['reservation_code'] : '';
            if ($sortKey === 'check_in') return isset($row['check_in_date']) ? (string)$row['check_in_date'] : '';
            if ($sortKey === 'check_out') return isset($row['check_out_date']) ? (string)$row['check_out_date'] : '';
            if ($sortKey === 'nights') return isset($row['nights']) ? (int)$row['nights'] : 0;
            if ($sortKey === 'people') return $item['people'];
            if ($sortKey === 'room_price') return $item['room_price'];
            if (strpos($sortKey, 'extra:') === 0) {
                return isset($item['extra_sums'][$sortKey]) ? $item['extra_sums'][$sortKey] : 0;
            }
            return '';
        };
        $va = $getValue($a);
        $vb = $getValue($b);
        if (is_numeric($va) && is_numeric($vb)) {
            if ($va == $vb) return 0;
            return ($va < $vb ? -1 : 1) * $dir;
        }
        return strcmp((string)$va, (string)$vb) * $dir;
    });

    $groupedAccountingRows = array(
        'ISR RESICO' => array(),
        'ISR' => array()
    );
    foreach ($accountingRenderRows as $item) {
        $taxCategory = isset($item['tax_category']) ? (string)$item['tax_category'] : 'ISR';
        if ($taxCategory !== 'ISR RESICO') {
            $taxCategory = 'ISR';
        }
        $channelCategory = isset($item['channel_category']) ? trim((string)$item['channel_category']) : '';
        if ($channelCategory === '') {
            $channelCategory = 'Sin categoria';
        }
        if (!isset($groupedAccountingRows[$taxCategory][$channelCategory])) {
            $groupedAccountingRows[$taxCategory][$channelCategory] = array();
        }
        $groupedAccountingRows[$taxCategory][$channelCategory][] = $item;
    }
    foreach ($groupedAccountingRows as $sectionKey => $sectionGroups) {
        if (empty($sectionGroups)) {
            continue;
        }
        uksort($sectionGroups, function ($a, $b) {
            return strcasecmp((string)$a, (string)$b);
        });
        $groupedAccountingRows[$sectionKey] = $sectionGroups;
    }
}

$incomeReportRows = array();
if ($activeTab === 'incomes') {
    $minVal = trim($incomeReportFilters['min_amount']) !== '' ? (float)str_replace(',', '', $incomeReportFilters['min_amount']) : 0;
    $maxVal = trim($incomeReportFilters['max_amount']) !== '' ? (float)str_replace(',', '', $incomeReportFilters['max_amount']) : 0;
    $minCents = $minVal > 0 ? (int)round($minVal * 100) : 0;
    $maxCents = $maxVal > 0 ? (int)round($maxVal * 100) : 0;
    $incomeReportRows = array();
}

function reports_render_report_table($rows, $columns, $totalsByReservation, $formulaLabel = '', $formulaExpr = '')
{
    echo '<div class="table-scroll"><table><thead><tr>';
    foreach ($columns as $col) {
        echo '<th>' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    if ($formulaLabel !== '' && $formulaExpr !== '') {
        echo '<th>' . htmlspecialchars($formulaLabel, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $rid = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
        $totals = isset($totalsByReservation[$rid]) ? $totalsByReservation[$rid] : array(
            'charges' => 0,
            'taxes' => 0,
            'payments' => 0,
            'obligations' => 0,
            'incomes' => 0
        );
        $net = $totals['charges'] + $totals['taxes'] - $totals['payments'] - $totals['obligations'] + $totals['incomes'];

        echo '<tr>';
        foreach ($columns as $col) {
            $value = '';
            switch ($col) {
                case 'Reserva':
                case 'reservation_code':
                    $value = (string)$row['reservation_code'];
                    break;
                case 'Estatus':
                case 'status':
                    $value = (string)$row['status'];
                    break;
                case 'Fuente':
                case 'source':
                    $value = (string)$row['source'];
                    break;
                case 'Check-in':
                case 'check_in_date':
                    $value = (string)$row['check_in_date'];
                    break;
                case 'Check-out':
                case 'check_out_date':
                    $value = (string)$row['check_out_date'];
                    break;
                case 'Huesped':
                case 'guest_name':
                    $value = trim((string)$row['guest_names'] . ' ' . (string)$row['guest_last_name']);
                    break;
                case 'Email':
                case 'guest_email':
                    $value = (string)$row['guest_email'];
                    break;
                case 'Habitacion':
                case 'room_name':
                    $value = (string)$row['room_name'];
                    break;
                case 'Propiedad':
                case 'property_name':
                    $value = (string)$row['property_name'];
                    break;
                case 'Adultos':
                case 'adults':
                    $value = (string)$row['adults'];
                    break;
                case 'Ninos':
                case 'children':
                    $value = (string)$row['children'];
                    break;
                case 'Moneda':
                case 'currency':
                    $value = (string)$row['currency'];
                    break;
                case 'Total reserva':
                case 'total_price_cents':
                    $value = reports_format_money($row['total_price_cents'], $row['currency']);
                    break;
                case 'Saldo reserva':
                case 'balance_due_cents':
                    $value = reports_format_money($row['balance_due_cents'], $row['currency']);
                    break;
                case 'Total cargos':
                case 'charges':
                    $value = reports_format_money($totals['charges'], $row['currency']);
                    break;
                case 'Total impuestos':
                case 'taxes':
                    $value = reports_format_money($totals['taxes'], $row['currency']);
                    break;
                case 'Total pagos':
                case 'payments':
                    $value = reports_format_money($totals['payments'], $row['currency']);
                    break;
                case 'Total obligaciones':
                case 'obligations':
                    $value = reports_format_money($totals['obligations'], $row['currency']);
                    break;
                case 'Total ingresos':
                case 'incomes':
                    $value = reports_format_money($totals['incomes'], $row['currency']);
                    break;
                case 'Neto':
                case 'net_total':
                    $value = reports_format_money($net, $row['currency']);
                    break;
                default:
                    $value = '';
            }
            echo '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
        }

        if ($formulaLabel !== '' && $formulaExpr !== '') {
            $expr = $formulaExpr;
            $tokens = array(
                'charges' => $totals['charges'] / 100,
                'taxes' => $totals['taxes'] / 100,
                'payments' => $totals['payments'] / 100,
                'obligations' => $totals['obligations'] / 100,
                'incomes' => $totals['incomes'] / 100,
                'total_price' => ((int)$row['total_price_cents']) / 100,
                'balance_due' => ((int)$row['balance_due_cents']) / 100,
                'net' => $net / 100
            );
            foreach ($tokens as $key => $val) {
                $expr = preg_replace('/\b' . preg_quote($key, '/') . '\b/i', (string)$val, $expr);
            }
            $calc = reports_safe_expr_eval($expr);
            $formatted = $calc === null ? '-' : '$' . number_format($calc, 2) . ' ' . $row['currency'];
            echo '<td>' . htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function reports_component_sum($componentId, $componentType, array $treeSaleIds, array $saleItemsById, array $taxBySaleItem)
{
    $sum = 0;
    if ($componentId <= 0) {
        return 0;
    }
    foreach ($treeSaleIds as $saleId) {
        if (!isset($saleItemsById[$saleId])) {
            continue;
        }
        $item = $saleItemsById[$saleId];
        if ((int)$item['id_sale_item_catalog'] === $componentId) {
            $sum += (int)$item['amount_cents'];
        }
    }
    return $sum;
}

?>

<section class="card">
  <h2>Reportes</h2>
  <p class="muted">Vistas separadas para reportes fijos, configuracion y reportes personalizados.</p>
  <div class="reports-tab-groups">
    <?php foreach ($reportTabGroups as $group): ?>
      <?php
        $groupTabs = isset($group['tabs']) && is_array($group['tabs']) ? $group['tabs'] : array();
        $groupIsActive = in_array($activeTab, $groupTabs, true);
      ?>
      <div class="reports-tab-group <?php echo $groupIsActive ? 'is-active' : ''; ?>">
        <div class="reports-tab-group-label"><?php echo htmlspecialchars((string)$group['label'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="subtabs-nav">
          <?php foreach ($groupTabs as $key): ?>
            <?php if (!isset($tabLinks[$key])) { continue; } ?>
            <a class="subtab-trigger <?php echo $activeTab === $key ? 'is-active' : ''; ?>" href="index.php?view=reports&tab=<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars((string)$tabLinks[$key], ENT_QUOTES, 'UTF-8'); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <style>
    .reports-tab-groups {
      display: grid;
      gap: 10px;
      margin-top: 10px;
    }
    .reports-tab-group {
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      padding: 10px;
      background: rgba(8,16,28,0.5);
    }
    .reports-tab-group.is-active {
      border-color: rgba(58, 208, 234, 0.55);
      box-shadow: 0 0 0 1px rgba(58, 208, 234, 0.15) inset;
    }
    .reports-tab-group-label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #8fb6d4;
      margin-bottom: 8px;
    }
    .reports-tab-group .subtabs-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .reports-tab-group .subtab-trigger {
      min-width: 108px;
      text-align: center;
    }
    @media (max-width: 900px) {
      .reports-tab-group .subtab-trigger {
        min-width: 0;
      }
    }
  </style>
</section>

<?php if ($activeTab === 'charges'): ?>
  <?php require __DIR__ . '/sale_item_report.php'; ?>
<?php elseif ($activeTab === 'line_items'): ?>
  <?php require __DIR__ . '/line_item_report.php'; ?>
<?php elseif ($activeTab === 'accounting'): ?>
  <section class="card">
    <h2>Reporte de contabilidad</h2>
    <p class="muted">Selecciona conceptos de hospedaje y configura los campos a mostrar.</p>
    <?php
      $baseColumnOptions = array(
          'payment_date' => 'Fecha pago',
          'payment_method' => 'Metodo',
          'payment_amount' => 'Monto pago',
          'guest' => 'Huesped',
          'reservation' => 'Reserva',
          'check_in' => 'Check-in',
          'check_out' => 'Check-out',
          'nights' => 'Noches',
          'people' => 'Personas',
          'room_price' => 'Precio habitacion'
      );
      $extraColumnOptions = array();
      foreach ($accountingExtraColumns as $extraDef) {
          $extraKey = isset($extraDef['key']) ? (string)$extraDef['key'] : '';
          if ($extraKey === '') {
              continue;
          }
          $display = isset($extraDef['display_name']) ? trim((string)$extraDef['display_name']) : '';
          if ($display === '') {
              $display = isset($extraDef['source_label']) ? (string)$extraDef['source_label'] : $extraKey;
          }
          $extraColumnOptions[$extraKey] = $display;
      }
      $availableColumns = $baseColumnOptions + $extraColumnOptions;
      $defaultOrder = array_merge(array_keys($baseColumnOptions), array_keys($extraColumnOptions));
      $storedOrder = array();
      if (isset($accountingFilters['column_order']) && $accountingFilters['column_order'] !== '') {
          $storedOrder = array_values(array_filter(array_map('trim', explode(',', $accountingFilters['column_order']))));
      }
      if (!$storedOrder) {
          $storedOrder = $defaultOrder;
      }
      $finalOrder = array();
      foreach ($storedOrder as $key) {
          if (isset($availableColumns[$key]) && !in_array($key, $finalOrder, true)) {
              $finalOrder[] = $key;
          }
      }
      foreach ($defaultOrder as $key) {
          if (isset($availableColumns[$key]) && !in_array($key, $finalOrder, true)) {
              $finalOrder[] = $key;
          }
      }
      $accountingColumnOrder = $finalOrder;
      $accountingColumnOrderCsv = implode(',', $accountingColumnOrder);
      $accountingDisplayColumnOrder = $accountingColumnOrder;
      if (!empty($accountingFilters['hide_zero_columns']) && !empty($accountingRenderRows)) {
          $numericColumnHasValue = array();
          foreach ($accountingColumnOrder as $colKey) {
              if ($colKey === 'payment_amount' || $colKey === 'room_price' || strpos($colKey, 'extra:') === 0) {
                  $numericColumnHasValue[$colKey] = false;
              }
          }
          foreach ($accountingRenderRows as $renderItem) {
              foreach ($numericColumnHasValue as $colKey => $hasValue) {
                  if ($hasValue) {
                      continue;
                  }
                  $val = 0;
                  if ($colKey === 'payment_amount') {
                      $val = isset($renderItem['payment_amount']) ? (int)$renderItem['payment_amount'] : 0;
                  } elseif ($colKey === 'room_price') {
                      $val = isset($renderItem['room_price']) ? (int)$renderItem['room_price'] : 0;
                  } else {
                      $rawExtraVal = isset($renderItem['extra_sums'][$colKey]) ? $renderItem['extra_sums'][$colKey] : null;
                      $val = is_numeric($rawExtraVal) ? (int)$rawExtraVal : 0;
                  }
                  if ($val !== 0) {
                      $numericColumnHasValue[$colKey] = true;
                  }
              }
          }
          $accountingDisplayColumnOrder = array_values(array_filter($accountingColumnOrder, function ($colKey) use ($numericColumnHasValue) {
              if (!array_key_exists($colKey, $numericColumnHasValue)) {
                  return true;
              }
              return !empty($numericColumnHasValue[$colKey]);
          }));
      }
      if (empty($accountingDisplayColumnOrder)) {
          $accountingDisplayColumnOrder = $accountingColumnOrder;
      }
      $accountingFirstColumnKey = !empty($accountingDisplayColumnOrder) ? (string)$accountingDisplayColumnOrder[0] : '';
    ?>

    <form method="post">
      <input type="hidden" name="reports_active_tab" value="accounting">
      <input type="hidden" name="accounting_column_order" value="<?php echo htmlspecialchars($accountingColumnOrderCsv, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="accounting_save_config" value="0" id="accounting-save-flag">
      <input type="hidden" name="accounting_sort" value="<?php echo htmlspecialchars($accountingFilters['sort_key'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="accounting_sort_dir" value="<?php echo htmlspecialchars($accountingFilters['sort_dir'], ENT_QUOTES, 'UTF-8'); ?>">
      <div class="form-inline">
        <label>
          Propiedad
          <select name="accounting_report_property">
            <option value="">(Todas)</option>
            <?php foreach ($properties as $prop): ?>
              <?php $code = isset($prop['code']) ? (string)$prop['code'] : ''; ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $accountingFilters['property_code'] === $code ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Desde
          <input type="date" name="accounting_report_from" value="<?php echo htmlspecialchars($accountingFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Hasta
          <input type="date" name="accounting_report_to" value="<?php echo htmlspecialchars($accountingFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Catalogo RESICO
          <select name="accounting_resico_catalog_id">
            <option value="0">(No definido)</option>
            <?php foreach ($componentCatalogs as $catalogMeta): ?>
              <?php
                $catalogId = isset($catalogMeta['id']) ? (int)$catalogMeta['id'] : 0;
                if ($catalogId <= 0) { continue; }
                $catalogType = isset($catalogMeta['type']) ? (string)$catalogMeta['type'] : '';
                $catalogLabel = isset($catalogMeta['label']) ? (string)$catalogMeta['label'] : ('Catalogo #' . $catalogId);
                $displayLabel = strtoupper($catalogType) . ' / ' . $catalogLabel;
              ?>
              <option value="<?php echo $catalogId; ?>" <?php echo (int)$accountingFilters['resico_catalog_id'] === $catalogId ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="pill">
          <input type="checkbox" name="accounting_hide_zero_columns" value="1" <?php echo !empty($accountingFilters['hide_zero_columns']) ? 'checked' : ''; ?>>
          <span>Ocultar columnas en 0</span>
        </label>
        <button type="submit" class="btn">Actualizar</button>
        <button type="button" class="btn btn-ghost report-settings-open" id="accounting-settings-open" aria-expanded="false" aria-controls="accounting-settings-overlay">⚙ Configurar reporte</button>
      </div>

      <div id="accounting-settings-overlay" class="report-settings-overlay" hidden>
        <div class="report-settings-backdrop"></div>
        <div class="report-settings-shell" role="dialog" aria-modal="true" aria-labelledby="accounting-settings-title">
      <div id="accounting-settings-workspace" class="report-settings-workspace">
        <div class="report-settings-header">
          <strong id="accounting-settings-title">Configuracion del reporte</strong>
          <button type="button" class="btn btn-ghost report-settings-close" id="accounting-settings-close">Volver</button>
        </div>
        <div class="report-settings-grid">
          <div class="card card-inner">
            <div class="form-row">
              <label class="block-label">Conceptos de hospedaje</label>
              <div class="form-inline compact-filters">
                <label>
                  Categoria
                  <select id="accounting-lodging-category-filter">
                    <option value="">(Todas)</option>
                    <?php foreach ($saleItemCategoryOptions as $categoryName): ?>
                      <option value="<?php echo htmlspecialchars((string)$categoryName, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string)$categoryName, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>
                  Subcategoria
                  <select id="accounting-lodging-subcategory-filter">
                    <option value="">(Todas)</option>
                    <?php foreach ($saleItemSubcategoryOptions as $subName): ?>
                      <option value="<?php echo htmlspecialchars((string)$subName, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string)$subName, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>
              <div class="pill-grid">
                <?php foreach ($saleItemCatalogs as $catalogId => $catalogMeta): ?>
                  <?php
                    $label = isset($catalogMeta['label']) ? (string)$catalogMeta['label'] : ('Catalogo #' . (int)$catalogId);
                    $categoryName = isset($catalogMeta['category']) ? (string)$catalogMeta['category'] : '';
                    $subName = isset($catalogMeta['subcategory']) ? (string)$catalogMeta['subcategory'] : '';
                  ?>
                  <?php $checked = in_array((int)$catalogId, $accountingFilters['lodging_catalog_ids'], true); ?>
                  <label class="pill lodging-pill"
                         data-category="<?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>"
                         data-subcategory="<?php echo htmlspecialchars($subName, ENT_QUOTES, 'UTF-8'); ?>"
                         data-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="checkbox" name="accounting_lodging_ids[]" value="<?php echo (int)$catalogId; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="card card-inner">
            <div class="form-row">
              <label class="block-label">Agregar campo adicional</label>
              <div class="form-inline">
                <label>
                  Categoria
                  <select id="accounting-relation-category-filter">
                    <option value="">(Todas)</option>
                    <?php foreach ($relationCategoryOptions as $categoryName): ?>
                      <option value="<?php echo htmlspecialchars((string)$categoryName, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string)$categoryName, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>
                  Subcategoria
                  <select id="accounting-relation-subcategory-filter">
                    <option value="">(Todas)</option>
                    <?php foreach ($relationSubcategoryOptions as $subName): ?>
                      <option value="<?php echo htmlspecialchars((string)$subName, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string)$subName, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>
              <div class="form-inline">
                <label class="inline-check" style="min-width:220px;">
                  <input type="checkbox" id="accounting-extra-multi-mode">
                  <span>Combinar varias relaciones</span>
                </label>
              </div>
              <div class="form-inline" id="accounting-extra-single-wrap">
                <label style="min-width:260px;flex:1;">
                  Relacion padre / concepto
                  <select id="accounting-extra-relation">
                    <option value="">Selecciona una relacion</option>
                    <?php foreach ($catalogRelationOptions as $relation): ?>
                      <?php
                        $relationValue = (string)$relation['key'];
                        $relationLabel = (string)$relation['source_label'];
                        $relationCategory = isset($relation['child_category']) ? (string)$relation['child_category'] : '';
                        $relationSubcategory = isset($relation['child_subcategory']) ? (string)$relation['child_subcategory'] : '';
                      ?>
                      <option value="<?php echo htmlspecialchars($relationValue, ENT_QUOTES, 'UTF-8'); ?>"
                              data-label="<?php echo htmlspecialchars($relationLabel, ENT_QUOTES, 'UTF-8'); ?>"
                              data-category="<?php echo htmlspecialchars($relationCategory, ENT_QUOTES, 'UTF-8'); ?>"
                              data-subcategory="<?php echo htmlspecialchars($relationSubcategory, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($relationLabel, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>
              <div class="form-row" id="accounting-extra-multi-wrap" hidden>
                <label class="block-label">Relaciones padre / concepto</label>
                <div id="accounting-extra-relation-list" class="relation-checklist">
                  <?php foreach ($catalogRelationOptions as $relation): ?>
                    <?php
                      $relationValue = (string)$relation['key'];
                      $relationLabel = (string)$relation['source_label'];
                      $relationCategory = isset($relation['child_category']) ? (string)$relation['child_category'] : '';
                      $relationSubcategory = isset($relation['child_subcategory']) ? (string)$relation['child_subcategory'] : '';
                    ?>
                    <label class="relation-check-item"
                           data-value="<?php echo htmlspecialchars($relationValue, ENT_QUOTES, 'UTF-8'); ?>"
                           data-label="<?php echo htmlspecialchars($relationLabel, ENT_QUOTES, 'UTF-8'); ?>"
                           data-category="<?php echo htmlspecialchars($relationCategory, ENT_QUOTES, 'UTF-8'); ?>"
                           data-subcategory="<?php echo htmlspecialchars($relationSubcategory, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="checkbox" value="<?php echo htmlspecialchars($relationValue, ENT_QUOTES, 'UTF-8'); ?>">
                      <span><?php echo htmlspecialchars($relationLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="form-inline">
                <label style="min-width:260px;flex:1;">
                  Display name
                  <input type="text" id="accounting-extra-display" placeholder="Ej. Comision OTA">
                </label>
                <button type="button" class="btn btn-ghost" id="accounting-add-extra">Agregar</button>
              </div>
            </div>

            <div class="form-row">
              <label class="block-label">Campos adicionales seleccionados</label>
              <div id="accounting-extra-list" class="extra-selected-list">
                <?php foreach ($accountingExtraColumns as $extraDef): ?>
                  <?php
                    $extraKey = (string)$extraDef['key'];
                    $sourceLabel = isset($extraDef['source_label']) ? (string)$extraDef['source_label'] : '';
                    $displayName = isset($extraDef['display_name']) ? (string)$extraDef['display_name'] : $sourceLabel;
                    $parentCatalogId = isset($extraDef['parent_catalog_id']) ? (int)$extraDef['parent_catalog_id'] : 0;
                    $catalogId = isset($extraDef['catalog_id']) ? (int)$extraDef['catalog_id'] : 0;
                    $relationPairs = isset($extraDef['relation_pairs']) && is_array($extraDef['relation_pairs'])
                        ? $extraDef['relation_pairs']
                        : array(array('parent_catalog_id' => $parentCatalogId, 'catalog_id' => $catalogId));
                    $relationSignature = isset($extraDef['relation_signature']) ? (string)$extraDef['relation_signature'] : ($parentCatalogId . ':' . $catalogId);
                    $isMultiExtra = !empty($extraDef['is_multi']);
                  ?>
                  <div class="extra-selected-item"
                       data-extra-key="<?php echo htmlspecialchars($extraKey, ENT_QUOTES, 'UTF-8'); ?>"
                       data-extra-id="<?php echo htmlspecialchars(($isMultiExtra ? 'multi:' : 'single:') . $relationSignature, ENT_QUOTES, 'UTF-8'); ?>"
                       data-is-multi="<?php echo $isMultiExtra ? '1' : '0'; ?>"
                       data-parent-id="<?php echo $parentCatalogId; ?>"
                       data-catalog-id="<?php echo $catalogId; ?>"
                       data-relation-key="<?php echo htmlspecialchars($relationSignature, ENT_QUOTES, 'UTF-8'); ?>"
                       data-relations="<?php echo htmlspecialchars(json_encode($relationPairs), ENT_QUOTES, 'UTF-8'); ?>"
                       data-source-label="<?php echo htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="extra-selected-main">
                      <input type="text" class="extra-display-input" value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Display name">
                    </div>
                    <button type="button" class="btn btn-ghost extra-remove">Quitar</button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="form-row">
              <label class="block-label">Orden de columnas</label>
              <div class="order-list" data-order-input="accounting_column_order">
                <?php foreach ($accountingColumnOrder as $key): ?>
                  <?php if (!isset($availableColumns[$key])) { continue; } ?>
                  <?php
                    $parsedOrderExtra = reports_parse_extra_column_key($key);
                    $isExtra = $parsedOrderExtra ? '1' : '0';
                    $orderIsMulti = ($parsedOrderExtra && !empty($parsedOrderExtra['is_multi'])) ? '1' : '0';
                    $orderParentCatalogId = $parsedOrderExtra ? (int)$parsedOrderExtra['parent_catalog_id'] : 0;
                    $orderCatalogId = $parsedOrderExtra ? (int)$parsedOrderExtra['catalog_id'] : 0;
                    $orderRelationKey = $parsedOrderExtra
                        ? (isset($parsedOrderExtra['relation_signature']) ? (string)$parsedOrderExtra['relation_signature'] : ($orderParentCatalogId . ':' . $orderCatalogId))
                        : '';
                    $orderRelationPairs = ($parsedOrderExtra && isset($parsedOrderExtra['relation_pairs']) && is_array($parsedOrderExtra['relation_pairs']))
                        ? $parsedOrderExtra['relation_pairs']
                        : array();
                    $orderExtraId = $parsedOrderExtra ? (($orderIsMulti === '1' ? 'multi:' : 'single:') . $orderRelationKey) : '';
                  ?>
                  <div class="order-item"
                       data-key="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                       data-is-extra="<?php echo $isExtra; ?>"
                       data-is-multi="<?php echo $orderIsMulti; ?>"
                       data-extra-id="<?php echo htmlspecialchars($orderExtraId, ENT_QUOTES, 'UTF-8'); ?>"
                       data-parent-id="<?php echo $orderParentCatalogId; ?>"
                       data-catalog-id="<?php echo $orderCatalogId; ?>"
                       data-relation-key="<?php echo htmlspecialchars($orderRelationKey, ENT_QUOTES, 'UTF-8'); ?>"
                       data-relations="<?php echo htmlspecialchars(json_encode($orderRelationPairs), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="order-label"><?php echo htmlspecialchars($availableColumns[$key], ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="order-actions">
                      <button type="button" class="btn btn-ghost order-up">Subir</button>
                      <button type="button" class="btn btn-ghost order-down">Bajar</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="form-row report-settings-actions">
              <button type="submit" class="btn js-accounting-save">Guardar configuracion</button>
            </div>
          </div>
        </div>
      </div>
        </div>
      </div>
    </form>

    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <?php foreach ($accountingDisplayColumnOrder as $key): ?>
              <?php if (!isset($availableColumns[$key])) { continue; } ?>
              <th>
                <div class="table-head">
                  <span><?php echo htmlspecialchars($availableColumns[$key], ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="sort-controls">
                    <button type="button" class="sort-btn" data-sort="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-dir="asc">&#9650;</button>
                    <button type="button" class="sort-btn" data-sort="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" data-dir="desc">&#9660;</button>
                  </span>
                </div>
              </th>
            <?php endforeach; ?>
          </tr>
          <tr class="filter-row">
            <?php foreach ($accountingDisplayColumnOrder as $key): ?>
              <?php if (!isset($availableColumns[$key])) { continue; } ?>
              <th>
                <input type="text" name="accounting_filter_<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars(isset($accountingFilters['column_filters'][$key]) ? $accountingFilters['column_filters'][$key] : '', ENT_QUOTES, 'UTF-8'); ?>">
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
            $numericKeys = array('payment_amount', 'room_price');
            foreach ($accountingExtraColumns as $extraDef) {
                $extraKey = isset($extraDef['key']) ? (string)$extraDef['key'] : '';
                if ($extraKey !== '') {
                    $numericKeys[] = $extraKey;
                }
            }
            $numericKeys = array_values(array_unique($numericKeys));
            $grandTotals = array();
            $grandSeen = array();
            foreach ($numericKeys as $nkey) { $grandTotals[$nkey] = 0; }
            foreach ($numericKeys as $nkey) { $grandSeen[$nkey] = false; }
          ?>
          <?php foreach (array('ISR RESICO', 'ISR') as $sectionLabel): ?>
            <?php $sectionGroups = isset($groupedAccountingRows[$sectionLabel]) ? $groupedAccountingRows[$sectionLabel] : array(); ?>
            <?php if (empty($sectionGroups)) { continue; } ?>
            <tr class="group-row total-row">
              <?php foreach ($accountingDisplayColumnOrder as $key): ?>
                <?php if (!isset($availableColumns[$key])) { continue; } ?>
                <td><?php echo $accountingFirstColumnKey !== '' && $key === $accountingFirstColumnKey ? htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8') : ''; ?></td>
              <?php endforeach; ?>
            </tr>
            <?php foreach ($sectionGroups as $groupLabel => $items): ?>
              <?php
                $groupTotals = $grandTotals;
                $groupSeen = array();
                foreach ($numericKeys as $nkey) { $groupSeen[$nkey] = false; }
                foreach ($items as $item) {
                    $groupTotals['payment_amount'] += (int)$item['payment_amount'];
                    $groupTotals['room_price'] += (int)$item['room_price'];
                    $groupSeen['payment_amount'] = true;
                    $groupSeen['room_price'] = true;
                    $grandSeen['payment_amount'] = true;
                    $grandSeen['room_price'] = true;
                    foreach ($item['extra_sums'] as $extraKey => $sum) {
                        if (!is_numeric($sum)) {
                            continue;
                        }
                        if (!isset($groupTotals[$extraKey])) {
                            $groupTotals[$extraKey] = 0;
                        }
                        if (!isset($grandTotals[$extraKey])) {
                            $grandTotals[$extraKey] = 0;
                        }
                        if (!isset($groupSeen[$extraKey])) {
                            $groupSeen[$extraKey] = false;
                        }
                        if (!isset($grandSeen[$extraKey])) {
                            $grandSeen[$extraKey] = false;
                        }
                        $groupTotals[$extraKey] += (int)$sum;
                        $groupSeen[$extraKey] = true;
                        $grandSeen[$extraKey] = true;
                    }
                }
                foreach ($groupTotals as $k => $v) {
                    if (isset($grandTotals[$k])) {
                        $grandTotals[$k] += $v;
                    }
                }
              ?>
              <tr class="group-row">
                <?php foreach ($accountingDisplayColumnOrder as $key): ?>
                  <?php if (!isset($availableColumns[$key])) { continue; } ?>
                  <?php
                    $cell = '';
                    if ($accountingFirstColumnKey !== '' && $key === $accountingFirstColumnKey) {
                        $cell = $groupLabel;
                    } elseif (isset($groupTotals[$key])) {
                        if ($key === 'payment_amount' || $key === 'room_price' || !empty($groupSeen[$key])) {
                            $cell = reports_format_money($groupTotals[$key], isset($items[0]['currency']) ? $items[0]['currency'] : 'MXN');
                        }
                    }
                  ?>
                  <td><?php echo htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8'); ?></td>
                <?php endforeach; ?>
              </tr>
              <?php foreach ($items as $item): ?>
                <?php
                  $row = $item['row'];
                  $currency = $item['currency'];
                  $guestName = $item['guest'];
                  $people = $item['people'];
                ?>
                <tr>
                  <?php foreach ($accountingDisplayColumnOrder as $key): ?>
                    <?php if (!isset($availableColumns[$key])) { continue; } ?>
                    <?php
                      $cellValue = '';
                      if ($key === 'payment_date') {
                          $cellValue = $item['payment_date'];
                      } elseif ($key === 'payment_method') {
                          $cellValue = $item['payment_method'];
                      } elseif ($key === 'payment_amount') {
                          $cellValue = reports_format_money($item['payment_amount'], $currency);
                      } elseif ($key === 'guest') {
                          $cellValue = $guestName;
                      } elseif ($key === 'reservation') {
                          $cellValue = isset($row['reservation_code']) ? (string)$row['reservation_code'] : '';
                      } elseif ($key === 'check_in') {
                          $cellValue = isset($row['check_in_date']) ? (string)$row['check_in_date'] : '';
                      } elseif ($key === 'check_out') {
                          $cellValue = isset($row['check_out_date']) ? (string)$row['check_out_date'] : '';
                      } elseif ($key === 'nights') {
                          $cellValue = isset($row['nights']) ? (string)$row['nights'] : '';
                      } elseif ($key === 'people') {
                          $cellValue = (string)$people;
                      } elseif ($key === 'room_price') {
                          $cellValue = reports_format_money($item['room_price'], $currency);
                      } elseif (strpos($key, 'extra:') === 0) {
                          $extraSum = isset($item['extra_sums'][$key]) ? $item['extra_sums'][$key] : null;
                          if (is_numeric($extraSum)) {
                              $cellValue = reports_format_money($extraSum, $currency);
                          } else {
                              $cellValue = '';
                          }
                      }
                    ?>
                    <td><?php echo htmlspecialchars((string)$cellValue, ENT_QUOTES, 'UTF-8'); ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endforeach; ?>
          <?php if (!empty($accountingDisplayColumnOrder)): ?>
            <tr class="group-row total-row">
              <?php foreach ($accountingDisplayColumnOrder as $key): ?>
                <?php if (!isset($availableColumns[$key])) { continue; } ?>
                <?php
                  $cell = '';
                  if ($accountingFirstColumnKey !== '' && $key === $accountingFirstColumnKey) {
                      $cell = 'Total general';
                  } elseif (isset($grandTotals[$key])) {
                      if ($key === 'payment_amount' || $key === 'room_price' || !empty($grandSeen[$key])) {
                          $cell = reports_format_money($grandTotals[$key], 'MXN');
                      }
                  }
                ?>
                <td><?php echo htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8'); ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

      <style>
        .report-settings-overlay[hidden] {
          display: none !important;
        }
        .report-settings-overlay {
          position: fixed;
          inset: 0;
          z-index: 70;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .report-settings-backdrop {
          position: absolute;
          inset: 0;
          background: rgba(3, 9, 20, 0.78);
        }
        .report-settings-shell {
          position: relative;
          width: min(1500px, calc(100vw - 40px));
          max-height: calc(100vh - 56px);
          overflow: auto;
          z-index: 1;
        }
        .report-settings-workspace {
          margin-top: 0;
          border: 1px solid rgba(255,255,255,0.08);
          border-radius: 14px;
          padding: 14px;
          background: rgba(7,15,27,0.97);
        }
        .report-settings-header {
          display: flex;
          flex-direction: row;
          justify-content: space-between;
          width: 100%;
          align-items: flex-start;
          gap: 4px;
          margin-bottom: 12px;
        }
        .report-settings-header strong {
          font-size: 1.05rem;
        }
        .report-settings-header .muted {
          max-width: 740px;
        }
        .report-settings-grid {
          display: grid;
          grid-template-columns: minmax(320px, 1fr) minmax(420px, 1.2fr);
          gap: 14px;
          align-items: start;
        }
        @media (max-width: 1180px) {
          .report-settings-grid {
            grid-template-columns: 1fr;
          }
        }
        .card-inner {
          margin-top: 0;
          border: 1px solid rgba(255,255,255,0.08);
          border-radius: 12px;
          padding: 12px;
          background: rgba(12,20,32,0.6);
        }
        .block-label {
          display: block;
          margin-bottom: 8px;
        }
        .compact-filters {
          margin-bottom: 8px;
          gap: 10px;
        }
        .compact-filters label {
          min-width: 180px;
          flex: 1;
        }
        .report-settings-actions {
          margin-top: 8px;
          display: flex;
          justify-content: flex-end;
        }
        .order-list {
          display: grid;
          gap: 8px;
        }
        .order-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 12px;
          padding: 8px 10px;
          border-radius: 10px;
          border: 1px solid rgba(255,255,255,0.08);
          background: rgba(16,24,36,0.7);
        }
        .order-actions {
          display: flex;
          gap: 6px;
        }
        .table-head {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 6px;
        }
        .sort-controls {
          display: inline-flex;
          gap: 4px;
        }
        .sort-btn {
          border: 1px solid rgba(255,255,255,0.2);
          background: rgba(12,20,32,0.6);
          color: #cfe9ff;
          width: 20px;
          height: 20px;
          border-radius: 6px;
          cursor: pointer;
          line-height: 18px;
          font-size: 11px;
          padding: 0;
        }
        .filter-row input {
          width: 100%;
          min-width: 90px;
          padding: 6px 8px;
          border-radius: 8px;
          border: 1px solid rgba(255,255,255,0.12);
          background: rgba(8,16,28,0.6);
          color: #fff;
        }
        .group-row td {
          background: rgba(18,30,44,0.7);
          font-weight: 600;
        }
        .total-row td {
          background: rgba(24,38,56,0.9);
          font-weight: 700;
        }
        .pill-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
          grid-auto-rows: minmax(54px, auto);
          gap: 8px;
          max-height: 360px;
          overflow: auto;
          padding-right: 4px;
        }
        .pill-grid .pill {
          display: flex;
          align-items: flex-start;
          justify-content: flex-start;
          gap: 8px;
          min-height: 54px;
          text-align: left;
          padding: 8px 12px;
        }
        .pill-grid .pill input {
          margin: 0;
        }
        .pill-grid .pill span {
          flex: 1;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          white-space: normal;
        }
        .extra-selected-list {
          display: grid;
          gap: 8px;
          max-height: 260px;
          overflow: auto;
          padding-right: 4px;
        }
        .extra-selected-item {
          display: flex;
          align-items: center;
          gap: 8px;
          border: 1px solid rgba(255,255,255,0.08);
          border-radius: 10px;
          padding: 8px;
          background: rgba(14,22,36,0.7);
        }
        .extra-selected-main {
          flex: 1;
          min-width: 0;
          display: grid;
          gap: 6px;
        }
        .extra-selected-main small {
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        .extra-display-input {
          width: 100%;
        }
        .inline-check {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          font-size: .95rem;
          color: #cfe9ff;
        }
        .inline-check input {
          margin: 0;
        }
        .relation-checklist {
          display: grid;
          gap: 8px;
          max-height: 220px;
          overflow: auto;
          padding-right: 4px;
        }
        .relation-check-item {
          display: flex;
          align-items: flex-start;
          gap: 8px;
          border: 1px solid rgba(255,255,255,0.08);
          border-radius: 10px;
          padding: 8px 10px;
          background: rgba(14,22,36,0.7);
        }
        .relation-check-item input {
          margin-top: 2px;
        }
        .relation-check-item span {
          flex: 1;
          min-width: 0;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
      </style>
      <script>
        (function () {
          var tabInput = document.querySelector('form input[name="reports_active_tab"][value="accounting"]');
          if (!tabInput) {
            return;
          }
          var form = tabInput.closest('form');
          if (!form) {
            return;
          }

          var saveFlag = document.getElementById('accounting-save-flag');
          var saveBtn = form.querySelector('.js-accounting-save');
          if (saveBtn && saveFlag) {
            saveBtn.addEventListener('click', function () {
              saveFlag.value = '1';
            });
          }

          var orderList = form.querySelector('.order-list[data-order-input="accounting_column_order"]');
          var orderInput = form.querySelector('input[name="accounting_column_order"]');
          var extraList = document.getElementById('accounting-extra-list');
          var relationSelect = document.getElementById('accounting-extra-relation');
          var relationMultiToggle = document.getElementById('accounting-extra-multi-mode');
          var relationSingleWrap = document.getElementById('accounting-extra-single-wrap');
          var relationMultiWrap = document.getElementById('accounting-extra-multi-wrap');
          var relationChecklist = document.getElementById('accounting-extra-relation-list');
          var displayInput = document.getElementById('accounting-extra-display');
          var addExtraBtn = document.getElementById('accounting-add-extra');
          var settingsOverlay = document.getElementById('accounting-settings-overlay');
          var settingsBackdrop = settingsOverlay ? settingsOverlay.querySelector('.report-settings-backdrop') : null;
          var settingsOpenBtn = document.getElementById('accounting-settings-open');
          var settingsCloseBtn = document.getElementById('accounting-settings-close');
          var lodgingCategoryFilter = document.getElementById('accounting-lodging-category-filter');
          var lodgingSubcategoryFilter = document.getElementById('accounting-lodging-subcategory-filter');
          var relationCategoryFilter = document.getElementById('accounting-relation-category-filter');
          var relationSubcategoryFilter = document.getElementById('accounting-relation-subcategory-filter');

          var escapeHtml = function (value) {
            return String(value || '')
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
          };

          var safeCssAttr = function (value) {
            return String(value || '')
              .replace(/\\/g, '\\\\')
              .replace(/"/g, '\\"');
          };

          var buildExtraKey = function (parentId, catalogId, displayName) {
            var parent = parseInt(parentId || '0', 10) || 0;
            var catalog = parseInt(catalogId || '0', 10) || 0;
            if (!catalog) {
              return '';
            }
            var cleanDisplay = String(displayName || '').trim();
            var base = 'extra:' + String(parent) + ':' + String(catalog);
            if (!cleanDisplay) {
              return base;
            }
            return base + ':' + encodeURIComponent(cleanDisplay);
          };

          var normalizeRelationPairs = function (pairs) {
            var map = {};
            (pairs || []).forEach(function (pair) {
              if (!pair) {
                return;
              }
              var parent = parseInt(pair.parent_catalog_id || pair.parentId || pair.parent || '0', 10) || 0;
              var catalog = parseInt(pair.catalog_id || pair.catalogId || pair.catalog || '0', 10) || 0;
              if (!catalog) {
                return;
              }
              var key = String(parent) + ':' + String(catalog);
              map[key] = { parent_catalog_id: parent, catalog_id: catalog };
            });
            return Object.keys(map).sort(function (a, b) { return a.localeCompare(b); }).map(function (key) {
              return map[key];
            });
          };

          var buildRelationSignature = function (pairs) {
            return normalizeRelationPairs(pairs).map(function (pair) {
              return String(pair.parent_catalog_id) + ':' + String(pair.catalog_id);
            }).join('|');
          };

          var buildMultiExtraKey = function (pairs, displayName) {
            var signature = buildRelationSignature(pairs);
            if (!signature) {
              return '';
            }
            var base = 'extra:multi:' + encodeURIComponent(signature);
            var cleanDisplay = String(displayName || '').trim();
            if (!cleanDisplay) {
              return base;
            }
            return base + ':' + encodeURIComponent(cleanDisplay);
          };

          var buildExtraId = function (isMulti, pairs) {
            var signature = buildRelationSignature(pairs);
            if (!signature) {
              return '';
            }
            return (isMulti ? 'multi:' : 'single:') + signature;
          };

          var parseRelationsAttr = function (rawValue) {
            if (!rawValue) {
              return [];
            }
            try {
              var parsed = JSON.parse(String(rawValue));
              if (!Array.isArray(parsed)) {
                return [];
              }
              return normalizeRelationPairs(parsed);
            } catch (err) {
              return [];
            }
          };

          var setSettingsOpen = function (isOpen) {
            if (!settingsOverlay) {
              return;
            }
            settingsOverlay.hidden = !isOpen;
            if (settingsOpenBtn) {
              settingsOpenBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
            document.body.style.overflow = isOpen ? 'hidden' : '';
          };

          var updateOrderInput = function () {
            if (!orderList || !orderInput) {
              return;
            }
            var keys = [];
            orderList.querySelectorAll('.order-item').forEach(function (item) {
              var key = item.getAttribute('data-key');
              if (key) {
                keys.push(key);
              }
            });
            orderInput.value = keys.join(',');
          };

          var findOrderExtraById = function (extraId) {
            if (!orderList || !extraId) {
              return null;
            }
            return orderList.querySelector('.order-item[data-is-extra="1"][data-extra-id="' + safeCssAttr(extraId) + '"]');
          };

          var createOrderItem = function (extraId, relationSignature, relationPairs, isMulti, parentId, catalogId, columnKey, label) {
            if (!orderList || !columnKey) {
              return null;
            }
            var item = document.createElement('div');
            item.className = 'order-item';
            item.setAttribute('data-key', columnKey);
            item.setAttribute('data-is-extra', '1');
            item.setAttribute('data-is-multi', isMulti ? '1' : '0');
            item.setAttribute('data-extra-id', extraId);
            item.setAttribute('data-relation-key', relationSignature);
            item.setAttribute('data-relations', JSON.stringify(normalizeRelationPairs(relationPairs)));
            item.setAttribute('data-parent-id', String(parentId));
            item.setAttribute('data-catalog-id', String(catalogId));
            item.innerHTML =
              '<span class="order-label">' + escapeHtml(label) + '</span>' +
              '<div class="order-actions">' +
                '<button type="button" class="btn btn-ghost order-up">Subir</button>' +
                '<button type="button" class="btn btn-ghost order-down">Bajar</button>' +
              '</div>';
            orderList.appendChild(item);
            return item;
          };

          var ensureExtraEmptyState = function () {
            if (!extraList) {
              return;
            }
            var items = extraList.querySelectorAll('.extra-selected-item');
            var empty = extraList.querySelector('.extra-empty');
            if (!items.length && !empty) {
              var node = document.createElement('div');
              node.className = 'muted extra-empty';
              node.textContent = 'Sin campos adicionales.';
              extraList.appendChild(node);
            } else if (items.length && empty) {
              empty.remove();
            }
          };

          var syncExtrasToOrder = function () {
            if (!extraList || !orderList) {
              updateOrderInput();
              return;
            }
            var activeExtraIds = {};
            extraList.querySelectorAll('.extra-selected-item').forEach(function (extraItem) {
              var isMulti = extraItem.getAttribute('data-is-multi') === '1';
              var relationPairs = parseRelationsAttr(extraItem.getAttribute('data-relations'));
              if (!relationPairs.length) {
                relationPairs = normalizeRelationPairs([{
                  parent_catalog_id: parseInt(extraItem.getAttribute('data-parent-id') || '0', 10) || 0,
                  catalog_id: parseInt(extraItem.getAttribute('data-catalog-id') || '0', 10) || 0
                }]);
              }
              if (!relationPairs.length) {
                return;
              }
              var parentId = relationPairs[0].parent_catalog_id;
              var catalogId = relationPairs[0].catalog_id;
              var relationSignature = buildRelationSignature(relationPairs);
              var extraId = buildExtraId(isMulti, relationPairs);
              if (!extraId) {
                return;
              }
              activeExtraIds[extraId] = true;
              var sourceLabel = extraItem.getAttribute('data-source-label') || '';
              var input = extraItem.querySelector('.extra-display-input');
              var displayName = input ? String(input.value || '').trim() : '';
              var key = isMulti
                ? buildMultiExtraKey(relationPairs, displayName || sourceLabel)
                : buildExtraKey(parentId, catalogId, displayName || sourceLabel);
              if (!key) {
                return;
              }
              extraItem.setAttribute('data-extra-key', key);
              extraItem.setAttribute('data-extra-id', extraId);
              extraItem.setAttribute('data-relation-key', relationSignature);
              extraItem.setAttribute('data-parent-id', String(parentId));
              extraItem.setAttribute('data-catalog-id', String(catalogId));
              extraItem.setAttribute('data-relations', JSON.stringify(relationPairs));
              var label = displayName || sourceLabel || key;
              var orderItem = findOrderExtraById(extraId);
              if (!orderItem) {
                orderItem = createOrderItem(extraId, relationSignature, relationPairs, isMulti, parentId, catalogId, key, label);
              } else {
                orderItem.setAttribute('data-key', key);
                orderItem.setAttribute('data-extra-id', extraId);
                orderItem.setAttribute('data-is-multi', isMulti ? '1' : '0');
                orderItem.setAttribute('data-parent-id', String(parentId));
                orderItem.setAttribute('data-catalog-id', String(catalogId));
                orderItem.setAttribute('data-relation-key', relationSignature);
                orderItem.setAttribute('data-relations', JSON.stringify(relationPairs));
                var labelNode = orderItem.querySelector('.order-label');
                if (labelNode) {
                  labelNode.textContent = label;
                }
              }
            });

            orderList.querySelectorAll('.order-item[data-is-extra="1"]').forEach(function (orderItem) {
              var extraId = orderItem.getAttribute('data-extra-id') || '';
              if (!extraId || activeExtraIds[extraId]) {
                return;
              }
              orderItem.remove();
            });

            ensureExtraEmptyState();
            updateOrderInput();
          };

          var fillSelectValues = function (selectEl, values, previousValue) {
            if (!selectEl) {
              return;
            }
            selectEl.innerHTML = '<option value="">(Todas)</option>';
            values.forEach(function (val) {
              var opt = document.createElement('option');
              opt.value = val;
              opt.textContent = val;
              if (previousValue && String(previousValue).toLowerCase() === String(val).toLowerCase()) {
                opt.selected = true;
              }
              selectEl.appendChild(opt);
            });
          };

          var refreshLodgingSubcategoryOptions = function () {
            if (!lodgingSubcategoryFilter) {
              return;
            }
            var selectedCategory = lodgingCategoryFilter ? String(lodgingCategoryFilter.value || '').trim().toLowerCase() : '';
            var prevSub = String(lodgingSubcategoryFilter.value || '').trim();
            var set = {};
            form.querySelectorAll('.lodging-pill').forEach(function (pill) {
              var category = String(pill.getAttribute('data-category') || '').trim().toLowerCase();
              var sub = String(pill.getAttribute('data-subcategory') || '').trim();
              if (!sub) {
                return;
              }
              if (selectedCategory && category !== selectedCategory) {
                return;
              }
              set[sub] = true;
            });
            var vals = Object.keys(set).sort(function (a, b) { return a.localeCompare(b); });
            fillSelectValues(lodgingSubcategoryFilter, vals, prevSub);
          };

          var refreshRelationSubcategoryOptions = function () {
            if (!relationSubcategoryFilter) {
              return;
            }
            var selectedCategory = relationCategoryFilter ? String(relationCategoryFilter.value || '').trim().toLowerCase() : '';
            var prevSub = String(relationSubcategoryFilter.value || '').trim();
            var set = {};
            var markSub = function (categoryValue, subValue) {
              var category = String(categoryValue || '').trim().toLowerCase();
              var sub = String(subValue || '').trim();
              if (!sub) {
                return;
              }
              if (selectedCategory && category !== selectedCategory) {
                return;
              }
              set[sub] = true;
            };
            if (relationSelect) {
              Array.prototype.forEach.call(relationSelect.options, function (opt, idx) {
                if (idx === 0) {
                  return;
                }
                markSub(opt.getAttribute('data-category'), opt.getAttribute('data-subcategory'));
              });
            }
            if (relationChecklist) {
              relationChecklist.querySelectorAll('.relation-check-item').forEach(function (item) {
                markSub(item.getAttribute('data-category'), item.getAttribute('data-subcategory'));
              });
            }
            var vals = Object.keys(set).sort(function (a, b) { return a.localeCompare(b); });
            fillSelectValues(relationSubcategoryFilter, vals, prevSub);
          };

          var applyLodgingFilters = function () {
            refreshLodgingSubcategoryOptions();
            var selectedCategory = lodgingCategoryFilter ? String(lodgingCategoryFilter.value || '').trim().toLowerCase() : '';
            var selectedSubcategory = lodgingSubcategoryFilter ? String(lodgingSubcategoryFilter.value || '').trim().toLowerCase() : '';
            form.querySelectorAll('.lodging-pill').forEach(function (pill) {
              var category = String(pill.getAttribute('data-category') || '').trim().toLowerCase();
              var subcategory = String(pill.getAttribute('data-subcategory') || '').trim().toLowerCase();
              var okCategory = !selectedCategory || category === selectedCategory;
              var okSubcategory = !selectedSubcategory || subcategory === selectedSubcategory;
              pill.style.display = (okCategory && okSubcategory) ? '' : 'none';
            });
          };

          var applyRelationFilters = function () {
            refreshRelationSubcategoryOptions();
            var selectedCategory = relationCategoryFilter ? String(relationCategoryFilter.value || '').trim().toLowerCase() : '';
            var selectedSubcategory = relationSubcategoryFilter ? String(relationSubcategoryFilter.value || '').trim().toLowerCase() : '';
            if (relationSelect) {
              var visibleSelected = false;
              Array.prototype.forEach.call(relationSelect.options, function (opt, idx) {
                if (idx === 0) {
                  opt.hidden = false;
                  opt.disabled = false;
                  return;
                }
                var category = String(opt.getAttribute('data-category') || '').trim().toLowerCase();
                var subcategory = String(opt.getAttribute('data-subcategory') || '').trim().toLowerCase();
                var okCategory = !selectedCategory || category === selectedCategory;
                var okSubcategory = !selectedSubcategory || subcategory === selectedSubcategory;
                var isVisible = okCategory && okSubcategory;
                opt.hidden = !isVisible;
                opt.disabled = !isVisible;
                if (isVisible && opt.selected) {
                  visibleSelected = true;
                }
              });
              if (!visibleSelected) {
                relationSelect.value = '';
              }
            }
            if (relationChecklist) {
              relationChecklist.querySelectorAll('.relation-check-item').forEach(function (item) {
                var category = String(item.getAttribute('data-category') || '').trim().toLowerCase();
                var subcategory = String(item.getAttribute('data-subcategory') || '').trim().toLowerCase();
                var okCategory = !selectedCategory || category === selectedCategory;
                var okSubcategory = !selectedSubcategory || subcategory === selectedSubcategory;
                item.style.display = (okCategory && okSubcategory) ? '' : 'none';
              });
            }
          };

          var isMultiMode = function () {
            return !!(relationMultiToggle && relationMultiToggle.checked);
          };

          var updateRelationInputMode = function () {
            var multi = isMultiMode();
            if (relationSingleWrap) {
              relationSingleWrap.hidden = multi;
            }
            if (relationMultiWrap) {
              relationMultiWrap.hidden = !multi;
            }
          };

          var addExtraFromControls = function () {
            if (!extraList) {
              return;
            }
            var useMulti = isMultiMode();
            var relationPairs = [];
            var sourceLabel = '';

            if (useMulti) {
              if (!relationChecklist) {
                return;
              }
              var labels = [];
              relationChecklist.querySelectorAll('.relation-check-item input[type="checkbox"]:checked').forEach(function (input) {
                var value = String(input.value || '').trim();
                if (!value || value.indexOf(':') < 0) {
                  return;
                }
                var parts = value.split(':');
                var parentId = parseInt(parts[0] || '0', 10) || 0;
                var catalogId = parseInt(parts[1] || '0', 10) || 0;
                if (!catalogId) {
                  return;
                }
                relationPairs.push({ parent_catalog_id: parentId, catalog_id: catalogId });
                var row = input.closest('.relation-check-item');
                labels.push(row ? String(row.getAttribute('data-label') || value) : value);
              });
              relationPairs = normalizeRelationPairs(relationPairs);
              if (!relationPairs.length) {
                return;
              }
              sourceLabel = labels.filter(function (label) { return String(label || '').trim() !== ''; }).join(' | ');
              if (!sourceLabel) {
                sourceLabel = buildRelationSignature(relationPairs);
              }
            } else {
              if (!relationSelect) {
                return;
              }
              var relationValue = String(relationSelect.value || '').trim();
              if (!relationValue || relationValue.indexOf(':') < 0) {
                return;
              }
              var parts = relationValue.split(':');
              var parentId = parseInt(parts[0] || '0', 10) || 0;
              var catalogId = parseInt(parts[1] || '0', 10) || 0;
              if (!catalogId) {
                return;
              }
              relationPairs = normalizeRelationPairs([{ parent_catalog_id: parentId, catalog_id: catalogId }]);
              var selectedOption = relationSelect.options[relationSelect.selectedIndex];
              sourceLabel = selectedOption ? String(selectedOption.getAttribute('data-label') || selectedOption.text || '') : relationValue;
            }

            if (!relationPairs.length) {
              return;
            }
            var desiredDisplay = displayInput ? String(displayInput.value || '').trim() : '';
            if (!desiredDisplay) {
              desiredDisplay = sourceLabel;
            }
            var relationSignature = buildRelationSignature(relationPairs);
            var extraId = buildExtraId(useMulti, relationPairs);
            var parentId = relationPairs[0].parent_catalog_id;
            var catalogId = relationPairs[0].catalog_id;

            var existing = extraList.querySelector('.extra-selected-item[data-extra-id="' + safeCssAttr(extraId) + '"]');
            if (existing) {
              var existingInput = existing.querySelector('.extra-display-input');
              if (existingInput) {
                existingInput.value = desiredDisplay;
              }
              existing.setAttribute('data-source-label', sourceLabel);
              existing.setAttribute('data-relations', JSON.stringify(relationPairs));
              syncExtrasToOrder();
              return;
            }

            var item = document.createElement('div');
            item.className = 'extra-selected-item';
            item.setAttribute('data-extra-id', extraId);
            item.setAttribute('data-is-multi', useMulti ? '1' : '0');
            item.setAttribute('data-parent-id', String(parentId));
            item.setAttribute('data-catalog-id', String(catalogId));
            item.setAttribute('data-relation-key', relationSignature);
            item.setAttribute('data-relations', JSON.stringify(relationPairs));
            item.setAttribute('data-source-label', sourceLabel);
            item.innerHTML =
              '<div class="extra-selected-main">' +
                '<input type="text" class="extra-display-input" value="' + escapeHtml(desiredDisplay) + '" placeholder="Display name">' +
              '</div>' +
              '<button type="button" class="btn btn-ghost extra-remove">Quitar</button>';
            extraList.appendChild(item);

            if (displayInput) {
              displayInput.value = '';
            }
            syncExtrasToOrder();
          };

          if (settingsOpenBtn) {
            settingsOpenBtn.addEventListener('click', function () {
              setSettingsOpen(true);
            });
          }
          if (settingsCloseBtn) {
            settingsCloseBtn.addEventListener('click', function () {
              setSettingsOpen(false);
            });
          }
          if (settingsBackdrop) {
            settingsBackdrop.addEventListener('click', function () {
              setSettingsOpen(false);
            });
          }
          document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && settingsOverlay && !settingsOverlay.hidden) {
              setSettingsOpen(false);
            }
          });

          if (lodgingCategoryFilter) {
            lodgingCategoryFilter.addEventListener('change', applyLodgingFilters);
          }
          if (lodgingSubcategoryFilter) {
            lodgingSubcategoryFilter.addEventListener('change', applyLodgingFilters);
          }
          if (relationCategoryFilter) {
            relationCategoryFilter.addEventListener('change', applyRelationFilters);
          }
          if (relationSubcategoryFilter) {
            relationSubcategoryFilter.addEventListener('change', applyRelationFilters);
          }
          if (relationMultiToggle) {
            relationMultiToggle.addEventListener('change', function () {
              updateRelationInputMode();
            });
          }

          if (addExtraBtn) {
            addExtraBtn.addEventListener('click', addExtraFromControls);
          }

          if (extraList) {
            extraList.addEventListener('click', function (event) {
              var target = event.target;
              if (!(target instanceof HTMLElement)) {
                return;
              }
              if (target.classList.contains('extra-remove')) {
                var item = target.closest('.extra-selected-item');
                if (item) {
                  item.remove();
                  syncExtrasToOrder();
                }
              }
            });
            extraList.addEventListener('input', function (event) {
              var target = event.target;
              if (!(target instanceof HTMLElement)) {
                return;
              }
              if (target.classList.contains('extra-display-input')) {
                syncExtrasToOrder();
              }
            });
          }

          if (orderList) {
            orderList.addEventListener('click', function (event) {
              var target = event.target;
              if (!(target instanceof HTMLElement)) {
                return;
              }
              if (target.classList.contains('order-up') || target.classList.contains('order-down')) {
                var item = target.closest('.order-item');
                if (!item || !item.parentElement) {
                  return;
                }
                if (target.classList.contains('order-up')) {
                  var prev = item.previousElementSibling;
                  if (prev) {
                    item.parentElement.insertBefore(item, prev);
                  }
                } else {
                  var next = item.nextElementSibling;
                  if (next) {
                    item.parentElement.insertBefore(next, item);
                  }
                }
                updateOrderInput();
              }
            });
          }

          form.querySelectorAll('.sort-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var sortKey = btn.getAttribute('data-sort') || '';
              var sortDir = btn.getAttribute('data-dir') || 'asc';
              var sortInput = form.querySelector('input[name="accounting_sort"]');
              var dirInput = form.querySelector('input[name="accounting_sort_dir"]');
              if (sortInput) {
                sortInput.value = sortKey;
              }
              if (dirInput) {
                dirInput.value = sortDir;
              }
              form.submit();
            });
          });

          syncExtrasToOrder();
          updateOrderInput();
          applyLodgingFilters();
          applyRelationFilters();
          updateRelationInputMode();
          setSettingsOpen(false);
        })();
      </script>
  </section>
<?php elseif ($activeTab === 'builder'): ?>
  <?php require __DIR__ . '/reports_builder_tab.php'; ?>
<?php elseif ($activeTab === 'incomes'): ?>
  <section class="card">
    <h2>Reporte de ingresos</h2>
    <p class="muted">Consulta ingresos generados por concepto o impuesto.</p>

    <form method="post">
      <input type="hidden" name="reports_active_tab" value="incomes">
      <div class="form-inline">
        <label>
          Propiedad
          <select name="income_report_property">
            <option value="">(Todas)</option>
            <?php foreach ($properties as $prop): ?>
              <?php $code = isset($prop['code']) ? (string)$prop['code'] : ''; ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $incomeReportFilters['property_code'] === $code ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Desde
          <input type="date" name="income_report_from" value="<?php echo htmlspecialchars($incomeReportFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Hasta
          <input type="date" name="income_report_to" value="<?php echo htmlspecialchars($incomeReportFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Estatus
          <select name="income_report_status">
            <option value="">(Todos)</option>
            <option value="pendiente" <?php echo $incomeReportFilters['status'] === 'pendiente' ? 'selected' : ''; ?>>pendiente</option>
            <option value="parcial" <?php echo $incomeReportFilters['status'] === 'parcial' ? 'selected' : ''; ?>>parcial</option>
            <option value="pagado" <?php echo $incomeReportFilters['status'] === 'pagado' ? 'selected' : ''; ?>>pagado</option>
          </select>
        </label>
        <label>
          Tipo
          <select name="income_report_type">
            <option value="">(Todos)</option>
            <option value="sale_item" <?php echo $incomeReportFilters['type'] === 'sale_item' ? 'selected' : ''; ?>>Concepto</option>
            <option value="manual" <?php echo $incomeReportFilters['type'] === 'manual' ? 'selected' : ''; ?>>Manual</option>
          </select>
        </label>
        <label>
          Catalogo
          <select name="income_report_catalog_id">
            <option value="0">(Todos)</option>
            <?php foreach ($incomeCatalogs as $income): ?>
              <?php $catalogId = isset($income['id_income_catalog']) ? (int)$income['id_income_catalog'] : 0; ?>
              <option value="<?php echo $catalogId; ?>" <?php echo $incomeReportFilters['catalog_id'] === $catalogId ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)$income['income_name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Buscar
          <input type="text" name="income_report_search" value="<?php echo htmlspecialchars($incomeReportFilters['search'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Min
          <input type="text" name="income_report_min" value="<?php echo htmlspecialchars($incomeReportFilters['min_amount'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Max
          <input type="text" name="income_report_max" value="<?php echo htmlspecialchars($incomeReportFilters['max_amount'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label class="pill">
          <input type="checkbox" name="income_report_show_inactive" value="1" <?php echo $incomeReportFilters['show_inactive'] ? 'checked' : ''; ?>>
          <span>Mostrar inactivos</span>
        </label>
        <button type="submit" class="btn">Actualizar</button>
      </div>
    </form>

    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Propiedad</th>
            <th>Reserva</th>
            <th>Huesped</th>
            <th>Ingreso</th>
            <th>Tipo</th>
            <th>Concepto</th>
            <th>Impuesto</th>
            <th>Monto</th>
            <th>Pendiente</th>
            <th>Estatus</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incomeReportRows as $row): ?>
            <?php
              $currency = isset($row['currency']) ? (string)$row['currency'] : 'MXN';
              $typeLabel = $row['source_type'] === 'sale_item' ? 'Concepto' : 'Manual';
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['guest_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['income_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['sale_item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['income_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(reports_format_money($row['amount_cents'], $currency), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(reports_format_money($row['pending_cents'], $currency), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php elseif ($activeTab === 'reservations'): ?>
  <section class="card">
    <h2>Reporte por reserva</h2>
    <p class="muted">Cada fila es una reservacion. Selecciona los datos a mostrar y agrega calculos.</p>

    <form method="post">
      <input type="hidden" name="reports_active_tab" value="reservations">
      <div class="form-inline">
        <label>
          Propiedad
          <select name="res_report_property">
            <option value="">(Todas)</option>
            <?php foreach ($properties as $prop): ?>
              <?php $code = isset($prop['code']) ? (string)$prop['code'] : ''; ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $resFilters['property_code'] === $code ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Desde
          <input type="date" name="res_report_from" value="<?php echo htmlspecialchars($resFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Hasta
          <input type="date" name="res_report_to" value="<?php echo htmlspecialchars($resFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Estatus
          <input type="text" name="res_report_status" placeholder="booked, canceled" value="<?php echo htmlspecialchars($resFilters['status'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Fuente
          <input type="text" name="res_report_source" placeholder="booking, direct" value="<?php echo htmlspecialchars($resFilters['source'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Buscar
          <input type="text" name="res_report_search" placeholder="Reserva, huesped, propiedad" value="<?php echo htmlspecialchars($resFilters['search'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <button type="submit" class="btn">Actualizar</button>
      </div>

      <div class="form-row">
        <label class="block-label">Columnas a mostrar</label>
        <div class="pill-row">
          <?php foreach ($availableColumns as $key => $label): ?>
            <?php $checked = in_array($key, $resFilters['columns'], true); ?>
            <label class="pill">
              <input type="checkbox" name="res_report_columns[]" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $checked ? 'checked' : ''; ?>>
              <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-row">
        <label>
          Calculo adicional (usa tokens: charges, taxes, payments, obligations, incomes, total_price, balance_due, net)
          <input type="text" name="res_report_formula" placeholder="(charges + taxes) - payments" value="<?php echo htmlspecialchars($resFilters['formula'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
      </div>
    </form>

    <?php
      $columnsToRender = array();
      foreach ($resFilters['columns'] as $key) {
          if (isset($availableColumns[$key])) {
              $columnsToRender[$key] = $availableColumns[$key];
          }
      }
    ?>
    <?php if ($columnsToRender): ?>
      <?php reports_render_report_table($reservations, array_values($columnsToRender), $totalsByReservation, $resFilters['formula'] !== '' ? 'Calculo' : '', $resFilters['formula']); ?>
    <?php else: ?>
      <p class="muted">Selecciona al menos una columna.</p>
    <?php endif; ?>
  </section>
<?php else: ?>
  <section class="card">
    <h2>Reportes personalizados</h2>
    <p class="muted">Define las columnas y formulas para crear reportes a la medida.</p>

    <form method="post">
      <input type="hidden" name="reports_active_tab" value="custom">
      <div class="form-inline">
        <label>
          Propiedad
          <select name="custom_report_property">
            <option value="">(Todas)</option>
            <?php foreach ($properties as $prop): ?>
              <?php $code = isset($prop['code']) ? (string)$prop['code'] : ''; ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $customFilters['property_code'] === $code ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Desde
          <input type="date" name="custom_report_from" value="<?php echo htmlspecialchars($customFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Hasta
          <input type="date" name="custom_report_to" value="<?php echo htmlspecialchars($customFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Estatus
          <input type="text" name="custom_report_status" value="<?php echo htmlspecialchars($customFilters['status'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Fuente
          <input type="text" name="custom_report_source" value="<?php echo htmlspecialchars($customFilters['source'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Buscar
          <input type="text" name="custom_report_search" value="<?php echo htmlspecialchars($customFilters['search'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <button type="submit" class="btn">Generar</button>
      </div>

      <div class="form-row">
        <label class="block-label">Columnas</label>
        <div class="pill-row">
          <?php foreach ($availableColumns as $key => $label): ?>
            <?php $checked = in_array($key, $customFilters['columns'], true); ?>
            <label class="pill">
              <input type="checkbox" name="custom_report_columns[]" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $checked ? 'checked' : ''; ?>>
              <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-row">
        <label>
          Formulas personalizadas (una por linea, formato: Nombre = expresion)
          <textarea name="custom_report_formulas" rows="3" placeholder="Total Neto = net
Comision = (charges + taxes) * 0.1"><?php echo htmlspecialchars($customFilters['formula_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
      </div>
    </form>

    <?php
      $columnsToRender = array();
      foreach ($customFilters['columns'] as $key) {
          if (isset($availableColumns[$key])) {
              $columnsToRender[$key] = $availableColumns[$key];
          }
      }
      $formulaRows = array();
      if ($customFilters['formula_lines'] !== '') {
          $lines = preg_split('/\r?\n/', $customFilters['formula_lines']);
          foreach ($lines as $line) {
              $line = trim($line);
              if ($line === '' || strpos($line, '=') === false) {
                  continue;
              }
              list($name, $expr) = array_map('trim', explode('=', $line, 2));
              if ($name !== '' && $expr !== '') {
                  $formulaRows[] = array('name' => $name, 'expr' => $expr);
              }
          }
      }
    ?>

    <?php if ($columnsToRender): ?>
      <?php
        if ($formulaRows) {
            foreach ($formulaRows as $formula) {
                echo '<h4>' . htmlspecialchars($formula['name'], ENT_QUOTES, 'UTF-8') . '</h4>';
                reports_render_report_table($reservations, array_values($columnsToRender), $totalsByReservation, $formula['name'], $formula['expr']);
            }
        } else {
            reports_render_report_table($reservations, array_values($columnsToRender), $totalsByReservation);
        }
      ?>
    <?php else: ?>
      <p class="muted">Selecciona al menos una columna.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
