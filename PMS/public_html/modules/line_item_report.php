<?php
if (!function_exists('line_report_format_money')) {
    function line_report_format_money($cents, $currency = 'MXN')
    {
        $value = ((int)$cents) / 100;
        return '$' . number_format($value, 2) . ' ' . $currency;
    }
}

if (!function_exists('line_report_to_cents')) {
    function line_report_to_cents($raw)
    {
        $txt = trim((string)$raw);
        if ($txt === '') {
            return 0;
        }
        $txt = str_replace(',', '', $txt);
        if (!is_numeric($txt)) {
            return 0;
        }
        return (int)round(((float)$txt) * 100);
    }
}

if (!function_exists('line_report_parse_int_list')) {
    function line_report_parse_int_list($raw)
    {
        $values = is_array($raw) ? $raw : array($raw);
        $seen = array();
        $out = array();
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id <= 0) {
                continue;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
        }
        return $out;
    }
}

if (!function_exists('line_report_parse_enum_list')) {
    function line_report_parse_enum_list($raw, array $allowed)
    {
        $values = is_array($raw) ? $raw : array($raw);
        $allowedMap = array();
        foreach ($allowed as $item) {
            $allowedMap[(string)$item] = true;
        }
        $seen = array();
        $out = array();
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value === '' || !isset($allowedMap[$value]) || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $out[] = $value;
        }
        return $out;
    }
}

if (!isset($currentUser) || !is_array($currentUser)) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

$lineReportProperties = isset($properties) && is_array($properties) ? $properties : pms_fetch_properties($companyId);
$lineReportReset = isset($_POST['line_report_reset']) && (string)$_POST['line_report_reset'] === '1';

$lineReportFilters = array(
    'property_code' => isset($_POST['line_report_property']) ? strtoupper(trim((string)$_POST['line_report_property'])) : '',
    'date_field' => isset($_POST['line_report_date_field']) ? (string)$_POST['line_report_date_field'] : 'created_at',
    'date_from' => isset($_POST['line_report_from']) ? (string)$_POST['line_report_from'] : '',
    'date_to' => isset($_POST['line_report_to']) ? (string)$_POST['line_report_to'] : '',
    'search' => isset($_POST['line_report_search']) ? trim((string)$_POST['line_report_search']) : '',
    'reservation_id' => isset($_POST['line_report_reservation_id']) ? (int)$_POST['line_report_reservation_id'] : 0,
    'folio_id' => isset($_POST['line_report_folio_id']) ? (int)$_POST['line_report_folio_id'] : 0,
    'room_id' => isset($_POST['line_report_room_id']) ? (int)$_POST['line_report_room_id'] : 0,
    'guest_id' => isset($_POST['line_report_guest_id']) ? (int)$_POST['line_report_guest_id'] : 0,
    'item_type_mode' => isset($_POST['line_report_item_type_mode']) ? trim((string)$_POST['line_report_item_type_mode']) : 'dropdown',
    'parent_category_mode' => isset($_POST['line_report_parent_category_mode']) ? trim((string)$_POST['line_report_parent_category_mode']) : 'dropdown',
    'category_mode' => isset($_POST['line_report_category_mode']) ? trim((string)$_POST['line_report_category_mode']) : 'dropdown',
    'catalog_mode' => isset($_POST['line_report_catalog_mode']) ? trim((string)$_POST['line_report_catalog_mode']) : 'dropdown',
    'item_types' => isset($_POST['line_report_item_types']) ? (array)$_POST['line_report_item_types'] : array(),
    'parent_category_ids' => isset($_POST['line_report_parent_category_ids']) ? (array)$_POST['line_report_parent_category_ids'] : array(),
    'category_ids' => isset($_POST['line_report_category_ids']) ? (array)$_POST['line_report_category_ids'] : array(),
    'catalog_ids' => isset($_POST['line_report_catalog_ids']) ? (array)$_POST['line_report_catalog_ids'] : array(),
    'catalog_id' => isset($_POST['line_report_catalog_id']) ? (int)$_POST['line_report_catalog_id'] : 0,
    'category_id' => isset($_POST['line_report_category_id']) ? (int)$_POST['line_report_category_id'] : 0,
    'parent_category_id' => isset($_POST['line_report_parent_category_id']) ? (int)$_POST['line_report_parent_category_id'] : 0,
    'item_type' => isset($_POST['line_report_item_type']) ? (string)$_POST['line_report_item_type'] : '',
    'status' => isset($_POST['line_report_status']) ? trim((string)$_POST['line_report_status']) : '',
    'folio_status' => isset($_POST['line_report_folio_status']) ? trim((string)$_POST['line_report_folio_status']) : '',
    'reservation_status' => isset($_POST['line_report_res_status']) ? trim((string)$_POST['line_report_res_status']) : '',
    'show_canceled_reservations' => isset($_POST['line_report_show_canceled_reservations']) ? 1 : 0,
    'source' => isset($_POST['line_report_source']) ? trim((string)$_POST['line_report_source']) : '',
    'currency' => isset($_POST['line_report_currency']) ? strtoupper(trim((string)$_POST['line_report_currency'])) : '',
    'method' => isset($_POST['line_report_method']) ? trim((string)$_POST['line_report_method']) : '',
    'has_tax' => isset($_POST['line_report_has_tax']) ? (string)$_POST['line_report_has_tax'] : '',
    'has_catalog' => isset($_POST['line_report_has_catalog']) ? (string)$_POST['line_report_has_catalog'] : '',
    'derived_only' => isset($_POST['line_report_derived_only']) ? 1 : 0,
    'show_inactive' => isset($_POST['line_report_show_inactive']) ? 1 : 0,
    'min_amount' => isset($_POST['line_report_min_amount']) ? (string)$_POST['line_report_min_amount'] : '',
    'max_amount' => isset($_POST['line_report_max_amount']) ? (string)$_POST['line_report_max_amount'] : '',
    'min_final' => isset($_POST['line_report_min_final']) ? (string)$_POST['line_report_min_final'] : '',
    'max_final' => isset($_POST['line_report_max_final']) ? (string)$_POST['line_report_max_final'] : '',
    'min_tax' => isset($_POST['line_report_min_tax']) ? (string)$_POST['line_report_min_tax'] : '',
    'max_tax' => isset($_POST['line_report_max_tax']) ? (string)$_POST['line_report_max_tax'] : '',
    'min_paid' => isset($_POST['line_report_min_paid']) ? (string)$_POST['line_report_min_paid'] : '',
    'max_paid' => isset($_POST['line_report_max_paid']) ? (string)$_POST['line_report_max_paid'] : '',
    'min_qty' => isset($_POST['line_report_min_qty']) ? trim((string)$_POST['line_report_min_qty']) : '',
    'max_qty' => isset($_POST['line_report_max_qty']) ? trim((string)$_POST['line_report_max_qty']) : '',
    'min_unit' => isset($_POST['line_report_min_unit']) ? (string)$_POST['line_report_min_unit'] : '',
    'max_unit' => isset($_POST['line_report_max_unit']) ? (string)$_POST['line_report_max_unit'] : '',
    'sort_by' => isset($_POST['line_report_sort_by']) ? (string)$_POST['line_report_sort_by'] : 'created_at',
    'sort_dir' => isset($_POST['line_report_sort_dir']) ? (string)$_POST['line_report_sort_dir'] : 'desc',
    'limit' => isset($_POST['line_report_limit']) ? (int)$_POST['line_report_limit'] : 500
);

if ($lineReportReset) {
    $lineReportFilters = array(
        'property_code' => '',
        'date_field' => 'created_at',
        'date_from' => '',
        'date_to' => '',
        'search' => '',
        'reservation_id' => 0,
        'folio_id' => 0,
        'room_id' => 0,
        'guest_id' => 0,
        'item_type_mode' => 'dropdown',
        'parent_category_mode' => 'dropdown',
        'category_mode' => 'dropdown',
        'catalog_mode' => 'dropdown',
        'item_types' => array(),
        'parent_category_ids' => array(),
        'category_ids' => array(),
        'catalog_ids' => array(),
        'catalog_id' => 0,
        'category_id' => 0,
        'parent_category_id' => 0,
        'item_type' => '',
        'status' => '',
        'folio_status' => '',
        'reservation_status' => '',
        'show_canceled_reservations' => 0,
        'source' => '',
        'currency' => '',
        'method' => '',
        'has_tax' => '',
        'has_catalog' => '',
        'derived_only' => 0,
        'show_inactive' => 0,
        'min_amount' => '',
        'max_amount' => '',
        'min_final' => '',
        'max_final' => '',
        'min_tax' => '',
        'max_tax' => '',
        'min_paid' => '',
        'max_paid' => '',
        'min_qty' => '',
        'max_qty' => '',
        'min_unit' => '',
        'max_unit' => '',
        'sort_by' => 'created_at',
        'sort_dir' => 'desc',
        'limit' => 500
    );
}

if (!in_array($lineReportFilters['date_field'], array('created_at', 'service_date'), true)) {
    $lineReportFilters['date_field'] = 'created_at';
}
if (!in_array($lineReportFilters['item_type'], array('', 'sale_item', 'tax_item', 'payment', 'obligation', 'income'), true)) {
    $lineReportFilters['item_type'] = '';
}
$lineReportModes = array('dropdown', 'checklist');
if (!in_array($lineReportFilters['item_type_mode'], $lineReportModes, true)) {
    $lineReportFilters['item_type_mode'] = 'dropdown';
}
if (!in_array($lineReportFilters['parent_category_mode'], $lineReportModes, true)) {
    $lineReportFilters['parent_category_mode'] = 'dropdown';
}
if (!in_array($lineReportFilters['category_mode'], $lineReportModes, true)) {
    $lineReportFilters['category_mode'] = 'dropdown';
}
if (!in_array($lineReportFilters['catalog_mode'], $lineReportModes, true)) {
    $lineReportFilters['catalog_mode'] = 'dropdown';
}
$lineReportFilters['item_types'] = line_report_parse_enum_list(
    $lineReportFilters['item_types'],
    array('sale_item', 'tax_item', 'payment', 'obligation', 'income')
);
$lineReportFilters['parent_category_ids'] = line_report_parse_int_list($lineReportFilters['parent_category_ids']);
$lineReportFilters['category_ids'] = line_report_parse_int_list($lineReportFilters['category_ids']);
$lineReportFilters['catalog_ids'] = line_report_parse_int_list($lineReportFilters['catalog_ids']);
if (!in_array($lineReportFilters['has_tax'], array('', 'with', 'without'), true)) {
    $lineReportFilters['has_tax'] = '';
}
if (!in_array($lineReportFilters['has_catalog'], array('', 'yes', 'no'), true)) {
    $lineReportFilters['has_catalog'] = '';
}
if (!in_array($lineReportFilters['sort_by'], array('created_at', 'service_date', 'property', 'reservation', 'folio', 'item_type', 'status', 'amount', 'tax', 'final', 'paid', 'quantity', 'unit_price'), true)) {
    $lineReportFilters['sort_by'] = 'created_at';
}
$lineReportFilters['sort_dir'] = strtolower($lineReportFilters['sort_dir']) === 'asc' ? 'asc' : 'desc';
if (!in_array($lineReportFilters['limit'], array(200, 500, 1000, 2000), true)) {
    $lineReportFilters['limit'] = 500;
}

$minAmountCents = line_report_to_cents($lineReportFilters['min_amount']);
$maxAmountCents = line_report_to_cents($lineReportFilters['max_amount']);
$minFinalCents = line_report_to_cents($lineReportFilters['min_final']);
$maxFinalCents = line_report_to_cents($lineReportFilters['max_final']);
$minTaxCents = line_report_to_cents($lineReportFilters['min_tax']);
$maxTaxCents = line_report_to_cents($lineReportFilters['max_tax']);
$minPaidCents = line_report_to_cents($lineReportFilters['min_paid']);
$maxPaidCents = line_report_to_cents($lineReportFilters['max_paid']);
$minUnitCents = line_report_to_cents($lineReportFilters['min_unit']);
$maxUnitCents = line_report_to_cents($lineReportFilters['max_unit']);
$minQty = $lineReportFilters['min_qty'] !== '' && is_numeric($lineReportFilters['min_qty']) ? (float)$lineReportFilters['min_qty'] : null;
$maxQty = $lineReportFilters['max_qty'] !== '' && is_numeric($lineReportFilters['max_qty']) ? (float)$lineReportFilters['max_qty'] : null;

$lineReportRows = array();
$lineReportError = '';
$lineReportCatalogs = array();
$lineReportParentCategories = array();
$lineReportSubcategories = array();
$lineReportReservationOptions = array();
$lineReportMethodOptions = array();

try {
    $pdo = pms_get_connection();
    $propertyId = null;
    if ($lineReportFilters['property_code'] !== '') {
        $stmtProperty = $pdo->prepare('SELECT id_property FROM property WHERE id_company = ? AND code = ? AND deleted_at IS NULL LIMIT 1');
        $stmtProperty->execute(array($companyId, $lineReportFilters['property_code']));
        $propertyId = $stmtProperty->fetchColumn();
        $propertyId = $propertyId !== false ? (int)$propertyId : null;
    }

    $sqlCategory = 'SELECT id_sale_item_category, id_parent_sale_item_category, category_name, id_property
                    FROM sale_item_category
                    WHERE id_company = ?
                      AND deleted_at IS NULL
                    ORDER BY category_name';
    $stmtCategory = $pdo->prepare($sqlCategory);
    $stmtCategory->execute(array($companyId));
    $categoryRows = $stmtCategory->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categoryRows as $cat) {
        $catPropertyId = isset($cat['id_property']) ? (int)$cat['id_property'] : 0;
        if ($propertyId !== null && $catPropertyId !== 0 && $catPropertyId !== $propertyId) {
            continue;
        }
        $parentId = isset($cat['id_parent_sale_item_category']) ? (int)$cat['id_parent_sale_item_category'] : 0;
        if ($parentId > 0) {
            $lineReportSubcategories[] = $cat;
        } else {
            $lineReportParentCategories[] = $cat;
        }
    }

    $sqlCatalog = 'SELECT
                     lic.id_line_item_catalog,
                     lic.catalog_type,
                     lic.item_name,
                     lic.id_category,
                     cat.category_name AS subcategory_name,
                     parent.category_name AS parent_category_name,
                     cat.id_property
                   FROM line_item_catalog lic
                   LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = lic.id_category
                   LEFT JOIN sale_item_category parent ON parent.id_sale_item_category = cat.id_parent_sale_item_category
                   WHERE lic.deleted_at IS NULL
                     AND lic.is_active = 1
                     AND (
                       cat.id_company = ?
                       OR lic.id_category IS NULL
                     )
                   ORDER BY parent.category_name, cat.category_name, lic.item_name';
    $stmtCatalog = $pdo->prepare($sqlCatalog);
    $stmtCatalog->execute(array($companyId));
    $catalogRows = $stmtCatalog->fetchAll(PDO::FETCH_ASSOC);
    foreach ($catalogRows as $row) {
        $rowPropertyId = isset($row['id_property']) ? (int)$row['id_property'] : 0;
        if ($propertyId !== null && $rowPropertyId !== 0 && $rowPropertyId !== $propertyId) {
            continue;
        }
        $lineReportCatalogs[] = $row;
    }

    $sqlReservation = 'SELECT
                         r.id_reservation,
                         r.code AS reservation_code,
                         r.status AS reservation_status,
                         p.code AS property_code,
                         rm.code AS room_code,
                         CONCAT_WS(" ", COALESCE(g.names, ""), COALESCE(g.last_name, "")) AS guest_name
                       FROM reservation r
                       JOIN property p ON p.id_property = r.id_property
                       LEFT JOIN room rm ON rm.id_room = r.id_room
                       LEFT JOIN guest g ON g.id_guest = r.id_guest
                       WHERE p.id_company = ?
                         AND r.deleted_at IS NULL
                         AND r.is_active = 1';
    $reservationParams = array($companyId);
    if ($lineReportFilters['property_code'] !== '') {
        $sqlReservation .= ' AND p.code = ?';
        $reservationParams[] = $lineReportFilters['property_code'];
    }
    if (empty($lineReportFilters['show_canceled_reservations'])) {
        $sqlReservation .= " AND COALESCE(LOWER(TRIM(r.status)), '') NOT IN ('cancelled','canceled','cancelado','cancelada')";
    }
    $sqlReservation .= ' ORDER BY COALESCE(r.updated_at, r.created_at) DESC, r.id_reservation DESC LIMIT 500';
    $stmtReservation = $pdo->prepare($sqlReservation);
    $stmtReservation->execute($reservationParams);
    $lineReportReservationOptions = $stmtReservation->fetchAll(PDO::FETCH_ASSOC);

    $sqlMethods = 'SELECT DISTINCT li.method
                   FROM line_item li
                   JOIN folio f ON f.id_folio = li.id_folio
                   JOIN reservation r ON r.id_reservation = f.id_reservation
                   JOIN property p ON p.id_property = r.id_property
                   WHERE p.id_company = ?
                     AND li.method IS NOT NULL
                     AND TRIM(li.method) <> ""';
    $methodParams = array($companyId);
    if ($lineReportFilters['property_code'] !== '') {
        $sqlMethods .= ' AND p.code = ?';
        $methodParams[] = $lineReportFilters['property_code'];
    }
    $sqlMethods .= ' ORDER BY li.method';
    $stmtMethods = $pdo->prepare($sqlMethods);
    $stmtMethods->execute($methodParams);
    $lineReportMethodOptions = $stmtMethods->fetchAll(PDO::FETCH_COLUMN);

    $sortColumnMap = array(
        'created_at' => 'li.created_at',
        'service_date' => 'COALESCE(li.service_date, DATE(li.created_at))',
        'property' => 'p.code',
        'reservation' => 'r.code',
        'folio' => 'f.folio_name',
        'item_type' => 'li.item_type',
        'status' => 'li.status',
        'amount' => 'li.amount_cents',
        'tax' => 'COALESCE(tax.tax_amount_cents,0)',
        'final' => '(li.amount_cents + COALESCE(tax.tax_amount_cents,0))',
        'paid' => 'COALESCE(li.paid_cents,0)',
        'quantity' => 'li.quantity',
        'unit_price' => 'li.unit_price_cents'
    );
    $sortSql = isset($sortColumnMap[$lineReportFilters['sort_by']]) ? $sortColumnMap[$lineReportFilters['sort_by']] : $sortColumnMap['created_at'];

    $sql = 'SELECT
              li.id_line_item,
              li.id_folio,
              li.id_line_item_catalog,
              li.item_type,
              li.method,
              li.reference,
              li.description,
              li.service_date,
              li.created_at,
              li.updated_at,
              li.quantity,
              li.unit_price_cents,
              li.discount_amount_cents,
              li.amount_cents,
              COALESCE(li.paid_cents, 0) AS paid_cents,
              li.currency,
              li.status,
              li.is_active,
              li.deleted_at,
              f.folio_name,
              f.status AS folio_status,
              r.id_reservation,
              r.code AS reservation_code,
              r.status AS reservation_status,
              r.source AS reservation_source,
              r.check_in_date,
              r.check_out_date,
              p.code AS property_code,
              p.name AS property_name,
              g.id_guest,
              g.names AS guest_names,
              g.last_name AS guest_last_name,
              g.maiden_name AS guest_maiden_name,
              g.email AS guest_email,
              rm.id_room,
              rm.code AS room_code,
              rm.name AS room_name,
              lic.catalog_type,
              COALESCE(li.item_name, lic.item_name, '') AS catalog_item_name,
              cat.id_sale_item_category AS subcategory_id,
              cat.category_name AS subcategory_name,
              parent.id_sale_item_category AS category_id,
              parent.category_name AS category_name,
              COALESCE(tax.tax_amount_cents, 0) AS tax_amount_cents,
              (li.amount_cents + COALESCE(tax.tax_amount_cents, 0)) AS final_amount_cents
            FROM line_item li
            JOIN folio f ON f.id_folio = li.id_folio
            JOIN reservation r ON r.id_reservation = f.id_reservation
            JOIN property p ON p.id_property = r.id_property
            LEFT JOIN guest g ON g.id_guest = r.id_guest
            LEFT JOIN room rm ON rm.id_room = r.id_room
            LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = li.id_line_item_catalog
            LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = lic.id_category
            LEFT JOIN sale_item_category parent ON parent.id_sale_item_category = cat.id_parent_sale_item_category
            LEFT JOIN (
                SELECT
                  s.id_line_item AS id_sale_item,
                  COALESCE(SUM(t.amount_cents), 0) AS tax_amount_cents
                FROM line_item s
                LEFT JOIN line_item_catalog_parent lcp
                  ON lcp.id_parent_sale_item_catalog = s.id_line_item_catalog
                 AND lcp.deleted_at IS NULL
                 AND lcp.is_active = 1
                LEFT JOIN line_item t
                  ON t.id_folio = s.id_folio
                 AND t.item_type = "tax_item"
                 AND t.id_line_item_catalog = lcp.id_sale_item_catalog
                 AND t.deleted_at IS NULL
                 AND t.is_active = 1
                 AND (t.service_date <=> s.service_date)
                WHERE s.item_type = "sale_item"
                  AND s.deleted_at IS NULL
                  AND s.is_active = 1
                GROUP BY s.id_line_item
            ) tax ON tax.id_sale_item = li.id_line_item
            WHERE p.id_company = ?';
    $params = array($companyId);
    $sql .= ' AND r.deleted_at IS NULL AND r.is_active = 1';
    if (empty($lineReportFilters['show_canceled_reservations'])) {
        $sql .= " AND COALESCE(LOWER(TRIM(r.status)), '') NOT IN ('cancelled','canceled','cancelado','cancelada')";
    }
    $itemTypeFilterValues = ($lineReportFilters['item_type_mode'] === 'checklist' && !empty($lineReportFilters['item_types']))
        ? $lineReportFilters['item_types']
        : (($lineReportFilters['item_type'] !== '') ? array($lineReportFilters['item_type']) : array());
    $parentCategoryFilterValues = ($lineReportFilters['parent_category_mode'] === 'checklist' && !empty($lineReportFilters['parent_category_ids']))
        ? $lineReportFilters['parent_category_ids']
        : (($lineReportFilters['parent_category_id'] > 0) ? array((int)$lineReportFilters['parent_category_id']) : array());
    $subcategoryFilterValues = ($lineReportFilters['category_mode'] === 'checklist' && !empty($lineReportFilters['category_ids']))
        ? $lineReportFilters['category_ids']
        : (($lineReportFilters['category_id'] > 0) ? array((int)$lineReportFilters['category_id']) : array());
    $catalogFilterValues = ($lineReportFilters['catalog_mode'] === 'checklist' && !empty($lineReportFilters['catalog_ids']))
        ? $lineReportFilters['catalog_ids']
        : (($lineReportFilters['catalog_id'] > 0) ? array((int)$lineReportFilters['catalog_id']) : array());

    if ($lineReportFilters['property_code'] !== '') {
        $sql .= ' AND p.code = ?';
        $params[] = $lineReportFilters['property_code'];
    }
    if ($lineReportFilters['reservation_id'] > 0) {
        $sql .= ' AND r.id_reservation = ?';
        $params[] = $lineReportFilters['reservation_id'];
    }
    if ($lineReportFilters['folio_id'] > 0) {
        $sql .= ' AND li.id_folio = ?';
        $params[] = $lineReportFilters['folio_id'];
    }
    if ($lineReportFilters['room_id'] > 0) {
        $sql .= ' AND r.id_room = ?';
        $params[] = $lineReportFilters['room_id'];
    }
    if ($lineReportFilters['guest_id'] > 0) {
        $sql .= ' AND r.id_guest = ?';
        $params[] = $lineReportFilters['guest_id'];
    }
    if (!empty($itemTypeFilterValues)) {
        $sql .= ' AND li.item_type IN (' . implode(',', array_fill(0, count($itemTypeFilterValues), '?')) . ')';
        foreach ($itemTypeFilterValues as $filterItemType) {
            $params[] = $filterItemType;
        }
    }
    if ($lineReportFilters['has_catalog'] === 'yes') {
        $sql .= ' AND li.id_line_item_catalog IS NOT NULL';
    } elseif ($lineReportFilters['has_catalog'] === 'no') {
        $sql .= ' AND li.id_line_item_catalog IS NULL';
    }
    if ($lineReportFilters['status'] !== '') {
        $sql .= ' AND li.status = ?';
        $params[] = $lineReportFilters['status'];
    }
    if ($lineReportFilters['folio_status'] !== '') {
        $sql .= ' AND f.status = ?';
        $params[] = $lineReportFilters['folio_status'];
    }
    if ($lineReportFilters['reservation_status'] !== '') {
        $sql .= ' AND r.status = ?';
        $params[] = $lineReportFilters['reservation_status'];
    }
    if ($lineReportFilters['source'] !== '') {
        $sql .= ' AND r.source = ?';
        $params[] = $lineReportFilters['source'];
    }
    if ($lineReportFilters['currency'] !== '') {
        $sql .= ' AND UPPER(COALESCE(li.currency, "")) = ?';
        $params[] = $lineReportFilters['currency'];
    }
    if ($lineReportFilters['method'] !== '') {
        $sql .= ' AND li.method = ?';
        $params[] = $lineReportFilters['method'];
    }
    if (!empty($catalogFilterValues)) {
        $sql .= ' AND li.id_line_item_catalog IN (' . implode(',', array_fill(0, count($catalogFilterValues), '?')) . ')';
        foreach ($catalogFilterValues as $catalogFilterValue) {
            $params[] = $catalogFilterValue;
        }
    }
    if (!empty($subcategoryFilterValues)) {
        $sql .= ' AND cat.id_sale_item_category IN (' . implode(',', array_fill(0, count($subcategoryFilterValues), '?')) . ')';
        foreach ($subcategoryFilterValues as $subcategoryFilterValue) {
            $params[] = $subcategoryFilterValue;
        }
    }
    if (!empty($parentCategoryFilterValues)) {
        $placeholders = implode(',', array_fill(0, count($parentCategoryFilterValues), '?'));
        $sql .= ' AND (cat.id_parent_sale_item_category IN (' . $placeholders . ') OR cat.id_sale_item_category IN (' . $placeholders . '))';
        foreach ($parentCategoryFilterValues as $parentCategoryFilterValue) {
            $params[] = $parentCategoryFilterValue;
        }
        foreach ($parentCategoryFilterValues as $parentCategoryFilterValue) {
            $params[] = $parentCategoryFilterValue;
        }
    }

    $dateExpr = $lineReportFilters['date_field'] === 'service_date'
        ? 'COALESCE(li.service_date, DATE(li.created_at))'
        : 'DATE(li.created_at)';
    if ($lineReportFilters['date_from'] !== '') {
        $sql .= ' AND ' . $dateExpr . ' >= ?';
        $params[] = $lineReportFilters['date_from'];
    }
    if ($lineReportFilters['date_to'] !== '') {
        $sql .= ' AND ' . $dateExpr . ' <= ?';
        $params[] = $lineReportFilters['date_to'];
    }

    if ($lineReportFilters['search'] !== '') {
        $sql .= ' AND (
            r.code LIKE ?
            OR f.folio_name LIKE ?
            OR g.names LIKE ?
            OR g.last_name LIKE ?
            OR g.email LIKE ?
            OR rm.code LIKE ?
            OR rm.name LIKE ?
            OR COALESCE(li.item_name, lic.item_name, "") LIKE ?
            OR COALESCE(li.description, "") LIKE ?
            OR COALESCE(li.reference, "") LIKE ?
            OR COALESCE(li.method, "") LIKE ?
            OR COALESCE(li.item_type, "") LIKE ?
        )';
        $needle = '%' . $lineReportFilters['search'] . '%';
        for ($i = 0; $i < 12; $i++) {
            $params[] = $needle;
        }
    }

    if ($minAmountCents > 0) {
        $sql .= ' AND li.amount_cents >= ?';
        $params[] = $minAmountCents;
    }
    if ($maxAmountCents > 0) {
        $sql .= ' AND li.amount_cents <= ?';
        $params[] = $maxAmountCents;
    }
    if ($minFinalCents > 0) {
        $sql .= ' AND (li.amount_cents + COALESCE(tax.tax_amount_cents, 0)) >= ?';
        $params[] = $minFinalCents;
    }
    if ($maxFinalCents > 0) {
        $sql .= ' AND (li.amount_cents + COALESCE(tax.tax_amount_cents, 0)) <= ?';
        $params[] = $maxFinalCents;
    }
    if ($minTaxCents > 0) {
        $sql .= ' AND COALESCE(tax.tax_amount_cents, 0) >= ?';
        $params[] = $minTaxCents;
    }
    if ($maxTaxCents > 0) {
        $sql .= ' AND COALESCE(tax.tax_amount_cents, 0) <= ?';
        $params[] = $maxTaxCents;
    }
    if ($minPaidCents > 0) {
        $sql .= ' AND COALESCE(li.paid_cents, 0) >= ?';
        $params[] = $minPaidCents;
    }
    if ($maxPaidCents > 0) {
        $sql .= ' AND COALESCE(li.paid_cents, 0) <= ?';
        $params[] = $maxPaidCents;
    }
    if ($minUnitCents > 0) {
        $sql .= ' AND COALESCE(li.unit_price_cents, 0) >= ?';
        $params[] = $minUnitCents;
    }
    if ($maxUnitCents > 0) {
        $sql .= ' AND COALESCE(li.unit_price_cents, 0) <= ?';
        $params[] = $maxUnitCents;
    }
    if ($minQty !== null) {
        $sql .= ' AND COALESCE(li.quantity, 0) >= ?';
        $params[] = $minQty;
    }
    if ($maxQty !== null) {
        $sql .= ' AND COALESCE(li.quantity, 0) <= ?';
        $params[] = $maxQty;
    }
    if ($lineReportFilters['has_tax'] === 'with') {
        $sql .= ' AND COALESCE(tax.tax_amount_cents, 0) > 0';
    } elseif ($lineReportFilters['has_tax'] === 'without') {
        $sql .= ' AND COALESCE(tax.tax_amount_cents, 0) = 0';
    }
    if (!empty($lineReportFilters['derived_only'])) {
        $sql .= ' AND li.item_type = "sale_item" AND EXISTS (
                    SELECT 1
                    FROM line_item_catalog_parent lcp
                    WHERE lcp.id_sale_item_catalog = li.id_line_item_catalog
                      AND lcp.deleted_at IS NULL
                      AND lcp.is_active = 1
                 )';
    }
    if (empty($lineReportFilters['show_inactive'])) {
        $sql .= ' AND li.deleted_at IS NULL
                  AND li.is_active = 1
                  AND f.deleted_at IS NULL
                  AND f.is_active = 1
                  AND r.deleted_at IS NULL';
    }

    $sql .= ' ORDER BY ' . $sortSql . ' ' . strtoupper($lineReportFilters['sort_dir']) . ', li.id_line_item DESC';
    $sql .= ' LIMIT ' . (int)$lineReportFilters['limit'];

    $stmtMain = $pdo->prepare($sql);
    $stmtMain->execute($params);
    $lineReportRows = $stmtMain->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lineReportRows = array();
    $lineReportError = $e->getMessage();
}

$sumAmount = 0;
$sumTax = 0;
$sumFinal = 0;
$sumPaid = 0;
foreach ($lineReportRows as $row) {
    $sumAmount += isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0;
    $sumTax += isset($row['tax_amount_cents']) ? (int)$row['tax_amount_cents'] : 0;
    $sumFinal += isset($row['final_amount_cents']) ? (int)$row['final_amount_cents'] : 0;
    $sumPaid += isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0;
}
?>
<section class="card">
  <h2>Reporte de line items</h2>
  <p class="muted">Vista global de line items con filtros avanzados para auditoria y conciliacion.</p>
  <?php if ($lineReportError !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($lineReportError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <style>
    .line-report-filter-shell {
      border: 1px solid rgba(56, 189, 248, 0.2);
      border-radius: 12px;
      padding: 12px;
      margin-top: 12px;
      background: rgba(8, 22, 44, 0.45);
    }
    .line-report-filter-shell h4 {
      margin: 0 0 8px 0;
      font-size: 0.98rem;
    }
    .line-report-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px 12px;
    }
    .line-report-grid .line-report-wide {
      grid-column: span 2;
    }
    .line-report-multi-control {
      border: 1px solid rgba(148, 163, 184, 0.22);
      border-radius: 10px;
      padding: 10px;
      background: rgba(15, 23, 42, 0.45);
    }
    .line-report-multi-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 8px;
    }
    .line-report-multi-head label {
      margin: 0;
      font-weight: 700;
      color: #dbeafe;
    }
    .line-report-multi-head select {
      width: 130px;
      min-width: 130px;
    }
    .line-report-checklist {
      border: 1px solid rgba(148, 163, 184, 0.25);
      border-radius: 8px;
      padding: 8px;
      max-height: 148px;
      overflow: auto;
      display: grid;
      gap: 6px;
      background: rgba(2, 6, 23, 0.45);
    }
    .line-report-checklist label {
      display: flex;
      gap: 8px;
      align-items: flex-start;
      margin: 0;
      font-size: 0.86rem;
      line-height: 1.25;
    }
  </style>

  <form method="post">
    <input type="hidden" name="reports_active_tab" value="line_items">

    <div class="form-inline">
      <label>
        Propiedad
        <select name="line_report_property">
          <option value="">(Todas)</option>
          <?php foreach ($lineReportProperties as $prop): ?>
            <?php $pcode = isset($prop['code']) ? (string)$prop['code'] : ''; ?>
            <option value="<?php echo htmlspecialchars($pcode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $lineReportFilters['property_code'] === $pcode ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($pcode . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Tipo fecha
        <select name="line_report_date_field">
          <option value="created_at" <?php echo $lineReportFilters['date_field'] === 'created_at' ? 'selected' : ''; ?>>Creacion</option>
          <option value="service_date" <?php echo $lineReportFilters['date_field'] === 'service_date' ? 'selected' : ''; ?>>Servicio</option>
        </select>
      </label>
      <label>
        Desde
        <input type="date" name="line_report_from" value="<?php echo htmlspecialchars($lineReportFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Hasta
        <input type="date" name="line_report_to" value="<?php echo htmlspecialchars($lineReportFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Buscar
        <input type="text" name="line_report_search" placeholder="Reserva, huesped, folio, concepto, ref" value="<?php echo htmlspecialchars($lineReportFilters['search'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
    </div>

    <div class="line-report-filter-shell">
      <h4>Contexto</h4>
      <div class="line-report-grid">
        <label class="line-report-wide">
          Reserva
          <select name="line_report_reservation_id">
            <option value="0">(Todas)</option>
            <?php foreach ($lineReportReservationOptions as $opt): ?>
              <?php $rid = isset($opt['id_reservation']) ? (int)$opt['id_reservation'] : 0; ?>
              <?php if ($rid <= 0) { continue; } ?>
              <?php $resLabel = (string)$opt['reservation_code'] . ' - ' . (string)$opt['guest_name'] . ' (' . (string)$opt['property_code'] . '/' . (string)$opt['room_code'] . ')'; ?>
              <option value="<?php echo $rid; ?>" <?php echo $lineReportFilters['reservation_id'] === $rid ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($resLabel, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Folio ID
          <input type="number" min="0" name="line_report_folio_id" value="<?php echo (int)$lineReportFilters['folio_id']; ?>">
        </label>
        <label>
          Room ID
          <input type="number" min="0" name="line_report_room_id" value="<?php echo (int)$lineReportFilters['room_id']; ?>">
        </label>
        <label>
          Guest ID
          <input type="number" min="0" name="line_report_guest_id" value="<?php echo (int)$lineReportFilters['guest_id']; ?>">
        </label>
      </div>
    </div>

    <div class="line-report-filter-shell">
      <h4>Clasificacion</h4>
      <div class="line-report-grid">
        <div class="line-report-multi-control">
          <div class="line-report-multi-head">
            <label for="line-report-item-type-single">Tipo line item</label>
            <select name="line_report_item_type_mode" class="line-report-mode-select" data-line-report-mode="item-type">
              <option value="dropdown" <?php echo $lineReportFilters['item_type_mode'] === 'dropdown' ? 'selected' : ''; ?>>Dropdown</option>
              <option value="checklist" <?php echo $lineReportFilters['item_type_mode'] === 'checklist' ? 'selected' : ''; ?>>Checklist</option>
            </select>
          </div>
          <div data-line-report-target="item-type" data-line-report-value="dropdown">
            <select id="line-report-item-type-single" name="line_report_item_type">
              <option value="">(Todos)</option>
              <?php foreach (array('sale_item', 'tax_item', 'payment', 'obligation', 'income') as $it): ?>
                <option value="<?php echo $it; ?>" <?php echo $lineReportFilters['item_type'] === $it ? 'selected' : ''; ?>><?php echo htmlspecialchars($it, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div data-line-report-target="item-type" data-line-report-value="checklist">
            <div class="line-report-checklist">
              <?php foreach (array('sale_item', 'tax_item', 'payment', 'obligation', 'income') as $it): ?>
                <label>
                  <input type="checkbox" name="line_report_item_types[]" value="<?php echo $it; ?>" <?php echo in_array($it, $lineReportFilters['item_types'], true) ? 'checked' : ''; ?>>
                  <span><?php echo htmlspecialchars($it, ENT_QUOTES, 'UTF-8'); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="line-report-multi-control">
          <div class="line-report-multi-head">
            <label for="line-report-parent-cat-single">Categoria</label>
            <select name="line_report_parent_category_mode" class="line-report-mode-select" data-line-report-mode="parent-category">
              <option value="dropdown" <?php echo $lineReportFilters['parent_category_mode'] === 'dropdown' ? 'selected' : ''; ?>>Dropdown</option>
              <option value="checklist" <?php echo $lineReportFilters['parent_category_mode'] === 'checklist' ? 'selected' : ''; ?>>Checklist</option>
            </select>
          </div>
          <div data-line-report-target="parent-category" data-line-report-value="dropdown">
            <select id="line-report-parent-cat-single" name="line_report_parent_category_id">
              <option value="0">(Todas)</option>
              <?php foreach ($lineReportParentCategories as $pc): ?>
                <?php $pcid = isset($pc['id_sale_item_category']) ? (int)$pc['id_sale_item_category'] : 0; ?>
                <?php if ($pcid <= 0) { continue; } ?>
                <option value="<?php echo $pcid; ?>" <?php echo $lineReportFilters['parent_category_id'] === $pcid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$pc['category_name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div data-line-report-target="parent-category" data-line-report-value="checklist">
            <div class="line-report-checklist">
              <?php foreach ($lineReportParentCategories as $pc): ?>
                <?php $pcid = isset($pc['id_sale_item_category']) ? (int)$pc['id_sale_item_category'] : 0; ?>
                <?php if ($pcid <= 0) { continue; } ?>
                <label>
                  <input type="checkbox" name="line_report_parent_category_ids[]" value="<?php echo $pcid; ?>" <?php echo in_array($pcid, $lineReportFilters['parent_category_ids'], true) ? 'checked' : ''; ?>>
                  <span><?php echo htmlspecialchars((string)$pc['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="line-report-multi-control">
          <div class="line-report-multi-head">
            <label for="line-report-subcat-single">Subcategoria</label>
            <select name="line_report_category_mode" class="line-report-mode-select" data-line-report-mode="subcategory">
              <option value="dropdown" <?php echo $lineReportFilters['category_mode'] === 'dropdown' ? 'selected' : ''; ?>>Dropdown</option>
              <option value="checklist" <?php echo $lineReportFilters['category_mode'] === 'checklist' ? 'selected' : ''; ?>>Checklist</option>
            </select>
          </div>
          <div data-line-report-target="subcategory" data-line-report-value="dropdown">
            <select id="line-report-subcat-single" name="line_report_category_id">
              <option value="0">(Todas)</option>
              <?php foreach ($lineReportSubcategories as $sc): ?>
                <?php $scid = isset($sc['id_sale_item_category']) ? (int)$sc['id_sale_item_category'] : 0; ?>
                <?php if ($scid <= 0) { continue; } ?>
                <option value="<?php echo $scid; ?>" <?php echo $lineReportFilters['category_id'] === $scid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$sc['category_name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div data-line-report-target="subcategory" data-line-report-value="checklist">
            <div class="line-report-checklist">
              <?php foreach ($lineReportSubcategories as $sc): ?>
                <?php $scid = isset($sc['id_sale_item_category']) ? (int)$sc['id_sale_item_category'] : 0; ?>
                <?php if ($scid <= 0) { continue; } ?>
                <label>
                  <input type="checkbox" name="line_report_category_ids[]" value="<?php echo $scid; ?>" <?php echo in_array($scid, $lineReportFilters['category_ids'], true) ? 'checked' : ''; ?>>
                  <span><?php echo htmlspecialchars((string)$sc['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="line-report-multi-control">
          <div class="line-report-multi-head">
            <label for="line-report-catalog-single">Catalogo</label>
            <select name="line_report_catalog_mode" class="line-report-mode-select" data-line-report-mode="catalog">
              <option value="dropdown" <?php echo $lineReportFilters['catalog_mode'] === 'dropdown' ? 'selected' : ''; ?>>Dropdown</option>
              <option value="checklist" <?php echo $lineReportFilters['catalog_mode'] === 'checklist' ? 'selected' : ''; ?>>Checklist</option>
            </select>
          </div>
          <div data-line-report-target="catalog" data-line-report-value="dropdown">
            <select id="line-report-catalog-single" name="line_report_catalog_id">
              <option value="0">(Todos)</option>
              <?php foreach ($lineReportCatalogs as $cat): ?>
                <?php $cid = isset($cat['id_line_item_catalog']) ? (int)$cat['id_line_item_catalog'] : 0; ?>
                <?php if ($cid <= 0) { continue; } ?>
                <?php
                  $catalogLabel = (string)$cat['item_name'];
                  if (!empty($cat['parent_category_name']) || !empty($cat['subcategory_name'])) {
                      $catalogLabel .= ' (' . trim((string)$cat['parent_category_name'] . ' / ' . (string)$cat['subcategory_name'], ' /') . ')';
                  }
                ?>
                <option value="<?php echo $cid; ?>" <?php echo $lineReportFilters['catalog_id'] === $cid ? 'selected' : ''; ?>><?php echo htmlspecialchars($catalogLabel, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div data-line-report-target="catalog" data-line-report-value="checklist">
            <div class="line-report-checklist">
              <?php foreach ($lineReportCatalogs as $cat): ?>
                <?php $cid = isset($cat['id_line_item_catalog']) ? (int)$cat['id_line_item_catalog'] : 0; ?>
                <?php if ($cid <= 0) { continue; } ?>
                <?php
                  $catalogLabel = (string)$cat['item_name'];
                  if (!empty($cat['parent_category_name']) || !empty($cat['subcategory_name'])) {
                      $catalogLabel .= ' (' . trim((string)$cat['parent_category_name'] . ' / ' . (string)$cat['subcategory_name'], ' /') . ')';
                  }
                ?>
                <label>
                  <input type="checkbox" name="line_report_catalog_ids[]" value="<?php echo $cid; ?>" <?php echo in_array($cid, $lineReportFilters['catalog_ids'], true) ? 'checked' : ''; ?>>
                  <span><?php echo htmlspecialchars($catalogLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="form-inline" style="margin-top:10px;">
      <label>
        Estatus line item
        <input type="text" name="line_report_status" placeholder="posted, pendiente, pagado..." value="<?php echo htmlspecialchars($lineReportFilters['status'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Estatus folio
        <input type="text" name="line_report_folio_status" placeholder="open, closed..." value="<?php echo htmlspecialchars($lineReportFilters['folio_status'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Estatus reserva
        <input type="text" name="line_report_res_status" placeholder="booked, canceled..." value="<?php echo htmlspecialchars($lineReportFilters['reservation_status'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label class="inline" style="align-self:flex-end;">
        <input type="checkbox" name="line_report_show_canceled_reservations" value="1" <?php echo !empty($lineReportFilters['show_canceled_reservations']) ? 'checked' : ''; ?>>
        Ver reservaciones canceladas
      </label>
      <label>
        Fuente
        <input type="text" name="line_report_source" placeholder="booking, expedia..." value="<?php echo htmlspecialchars($lineReportFilters['source'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Moneda
        <input type="text" name="line_report_currency" placeholder="MXN" value="<?php echo htmlspecialchars($lineReportFilters['currency'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Metodo
        <select name="line_report_method">
          <option value="">(Todos)</option>
          <?php foreach ($lineReportMethodOptions as $method): ?>
            <?php $method = trim((string)$method); ?>
            <?php if ($method === '') { continue; } ?>
            <option value="<?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $lineReportFilters['method'] === $method ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="form-inline" style="margin-top:10px;">
      <label>
        Min subtotal
        <input type="text" name="line_report_min_amount" value="<?php echo htmlspecialchars($lineReportFilters['min_amount'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max subtotal
        <input type="text" name="line_report_max_amount" value="<?php echo htmlspecialchars($lineReportFilters['max_amount'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Min impuestos
        <input type="text" name="line_report_min_tax" value="<?php echo htmlspecialchars($lineReportFilters['min_tax'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max impuestos
        <input type="text" name="line_report_max_tax" value="<?php echo htmlspecialchars($lineReportFilters['max_tax'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Min total final
        <input type="text" name="line_report_min_final" value="<?php echo htmlspecialchars($lineReportFilters['min_final'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max total final
        <input type="text" name="line_report_max_final" value="<?php echo htmlspecialchars($lineReportFilters['max_final'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Min pagado
        <input type="text" name="line_report_min_paid" value="<?php echo htmlspecialchars($lineReportFilters['min_paid'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max pagado
        <input type="text" name="line_report_max_paid" value="<?php echo htmlspecialchars($lineReportFilters['max_paid'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Min precio unitario
        <input type="text" name="line_report_min_unit" value="<?php echo htmlspecialchars($lineReportFilters['min_unit'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max precio unitario
        <input type="text" name="line_report_max_unit" value="<?php echo htmlspecialchars($lineReportFilters['max_unit'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Min cantidad
        <input type="text" name="line_report_min_qty" value="<?php echo htmlspecialchars($lineReportFilters['min_qty'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max cantidad
        <input type="text" name="line_report_max_qty" value="<?php echo htmlspecialchars($lineReportFilters['max_qty'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
    </div>

    <div class="form-inline" style="margin-top:10px;">
      <label>
        Tiene impuestos
        <select name="line_report_has_tax">
          <option value="">(Todos)</option>
          <option value="with" <?php echo $lineReportFilters['has_tax'] === 'with' ? 'selected' : ''; ?>>Con impuestos</option>
          <option value="without" <?php echo $lineReportFilters['has_tax'] === 'without' ? 'selected' : ''; ?>>Sin impuestos</option>
        </select>
      </label>
      <label>
        Tiene catalogo
        <select name="line_report_has_catalog">
          <option value="">(Todos)</option>
          <option value="yes" <?php echo $lineReportFilters['has_catalog'] === 'yes' ? 'selected' : ''; ?>>Con catalogo</option>
          <option value="no" <?php echo $lineReportFilters['has_catalog'] === 'no' ? 'selected' : ''; ?>>Sin catalogo</option>
        </select>
      </label>
      <label>
        Ordenar por
        <select name="line_report_sort_by">
          <option value="created_at" <?php echo $lineReportFilters['sort_by'] === 'created_at' ? 'selected' : ''; ?>>Fecha creacion</option>
          <option value="service_date" <?php echo $lineReportFilters['sort_by'] === 'service_date' ? 'selected' : ''; ?>>Fecha servicio</option>
          <option value="property" <?php echo $lineReportFilters['sort_by'] === 'property' ? 'selected' : ''; ?>>Propiedad</option>
          <option value="reservation" <?php echo $lineReportFilters['sort_by'] === 'reservation' ? 'selected' : ''; ?>>Reserva</option>
          <option value="folio" <?php echo $lineReportFilters['sort_by'] === 'folio' ? 'selected' : ''; ?>>Folio</option>
          <option value="item_type" <?php echo $lineReportFilters['sort_by'] === 'item_type' ? 'selected' : ''; ?>>Tipo line item</option>
          <option value="status" <?php echo $lineReportFilters['sort_by'] === 'status' ? 'selected' : ''; ?>>Estatus</option>
          <option value="amount" <?php echo $lineReportFilters['sort_by'] === 'amount' ? 'selected' : ''; ?>>Subtotal</option>
          <option value="tax" <?php echo $lineReportFilters['sort_by'] === 'tax' ? 'selected' : ''; ?>>Impuestos</option>
          <option value="final" <?php echo $lineReportFilters['sort_by'] === 'final' ? 'selected' : ''; ?>>Total final</option>
          <option value="paid" <?php echo $lineReportFilters['sort_by'] === 'paid' ? 'selected' : ''; ?>>Pagado</option>
          <option value="quantity" <?php echo $lineReportFilters['sort_by'] === 'quantity' ? 'selected' : ''; ?>>Cantidad</option>
          <option value="unit_price" <?php echo $lineReportFilters['sort_by'] === 'unit_price' ? 'selected' : ''; ?>>Precio unitario</option>
        </select>
      </label>
      <label>
        Direccion
        <select name="line_report_sort_dir">
          <option value="desc" <?php echo $lineReportFilters['sort_dir'] === 'desc' ? 'selected' : ''; ?>>Desc</option>
          <option value="asc" <?php echo $lineReportFilters['sort_dir'] === 'asc' ? 'selected' : ''; ?>>Asc</option>
        </select>
      </label>
      <label>
        Limite
        <select name="line_report_limit">
          <?php foreach (array(200, 500, 1000, 2000) as $lim): ?>
            <option value="<?php echo $lim; ?>" <?php echo (int)$lineReportFilters['limit'] === $lim ? 'selected' : ''; ?>><?php echo $lim; ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="inline">
        <input type="checkbox" name="line_report_derived_only" value="1" <?php echo !empty($lineReportFilters['derived_only']) ? 'checked' : ''; ?>>
        Solo derivados
      </label>
      <label class="inline">
        <input type="checkbox" name="line_report_show_inactive" value="1" <?php echo !empty($lineReportFilters['show_inactive']) ? 'checked' : ''; ?>>
        Mostrar inactivos
      </label>
      <button type="submit" class="button-primary">Filtrar</button>
      <button type="submit" class="button-secondary" name="line_report_reset" value="1">Limpiar</button>
    </div>
  </form>
</section>
<script>
(function () {
  function syncLineReportModes() {
    var modeSelects = document.querySelectorAll('.line-report-mode-select');
    modeSelects.forEach(function (select) {
      var modeKey = select.getAttribute('data-line-report-mode');
      if (!modeKey) return;
      var selectedMode = select.value === 'checklist' ? 'checklist' : 'dropdown';
      var targets = document.querySelectorAll('[data-line-report-target="' + modeKey + '"]');
      targets.forEach(function (panel) {
        var panelMode = panel.getAttribute('data-line-report-value');
        panel.style.display = panelMode === selectedMode ? '' : 'none';
      });
    });
  }

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (!target || !target.classList || !target.classList.contains('line-report-mode-select')) {
      return;
    }
    syncLineReportModes();
  });

  syncLineReportModes();
})();
</script>

<section class="card">
  <h3>Resultados</h3>
  <p class="muted">
    Registros: <?php echo (int)count($lineReportRows); ?>
    | Subtotal: <?php echo line_report_format_money($sumAmount, 'MXN'); ?>
    | Impuestos: <?php echo line_report_format_money($sumTax, 'MXN'); ?>
    | Total final: <?php echo line_report_format_money($sumFinal, 'MXN'); ?>
    | Pagado: <?php echo line_report_format_money($sumPaid, 'MXN'); ?>
  </p>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha creacion</th>
          <th>Fecha servicio</th>
          <th>Propiedad</th>
          <th>Reserva</th>
          <th>Huesped</th>
          <th>Room</th>
          <th>Folio</th>
          <th>Tipo line item</th>
          <th>Tipo catalogo</th>
          <th>Categoria</th>
          <th>Subcategoria</th>
          <th>Concepto</th>
          <th>Metodo</th>
          <th>Referencia</th>
          <th>Estatus</th>
          <th>Cantidad</th>
          <th>Precio unitario</th>
          <th>Subtotal</th>
          <th>Impuestos</th>
          <th>Total final</th>
          <th>Pagado</th>
          <th>Pendiente</th>
          <th>Moneda</th>
          <th>Activo</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$lineReportRows): ?>
          <tr>
            <td colspan="25" class="muted">Sin resultados para los filtros seleccionados.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($lineReportRows as $row): ?>
            <?php
              $currency = isset($row['currency']) && $row['currency'] !== '' ? (string)$row['currency'] : 'MXN';
              $guestName = trim((string)($row['guest_names'] ?? '') . ' ' . (string)($row['guest_last_name'] ?? ''));
              if ($guestName === '') {
                  $guestName = isset($row['guest_email']) ? (string)$row['guest_email'] : '';
              }
              $folioLabel = (string)($row['folio_name'] ?? '');
              if (!empty($row['folio_status'])) {
                  $folioLabel .= ' (' . (string)$row['folio_status'] . ')';
              }
              $activeLabel = (!empty($row['is_active']) && empty($row['deleted_at'])) ? 'si' : 'no';
              $pendingCents = (int)($row['final_amount_cents'] ?? 0) - (int)($row['paid_cents'] ?? 0);
            ?>
            <tr>
              <td><?php echo (int)$row['id_line_item']; ?></td>
              <td><?php echo htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['service_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['property_code'] . ' - ' . (string)$row['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['room_code'] . ' ' . (string)$row['room_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($folioLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['item_type'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['catalog_type'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['subcategory_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['catalog_item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['method'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['quantity'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo line_report_format_money((int)$row['unit_price_cents'], $currency); ?></td>
              <td><?php echo line_report_format_money((int)$row['amount_cents'], $currency); ?></td>
              <td><?php echo line_report_format_money((int)$row['tax_amount_cents'], $currency); ?></td>
              <td><?php echo line_report_format_money((int)$row['final_amount_cents'], $currency); ?></td>
              <td><?php echo line_report_format_money((int)$row['paid_cents'], $currency); ?></td>
              <td><?php echo line_report_format_money($pendingCents, $currency); ?></td>
              <td><?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($activeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
