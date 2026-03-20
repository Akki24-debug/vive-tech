<?php
$moduleKey = 'reservations';
require_once __DIR__ . '/../services/RateplanPricingService.php';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesi&oacute;n inv&aacute;lida.</p>';
    return;
}

$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
$reservationsRateplanPricingService = new RateplanPricingService(pms_get_connection());

if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('reservations.view');

$properties = pms_fetch_properties($companyId);
$propertiesByCode = array();
foreach ($properties as $property) {
    $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
    if ($code !== '') {
        $propertiesByCode[$code] = $property;
    }
}

$roomsCatalog = pms_fetch_rooms_for_company($companyId);
$roomsByProperty = array();
foreach ($roomsCatalog as $room) {
    $propertyCode = isset($room['property_code']) ? strtoupper((string)$room['property_code']) : '';
    if ($propertyCode === '') {
        continue;
    }
    if (!isset($roomsByProperty[$propertyCode])) {
        $roomsByProperty[$propertyCode] = array();
    }
    $roomsByProperty[$propertyCode][] = $room;
}

$otaAccountsByProperty = function_exists('pms_fetch_ota_accounts_grouped')
    ? pms_fetch_ota_accounts_grouped($companyId, false)
    : array();
$reservationSourcesByProperty = function_exists('pms_fetch_reservation_sources_grouped')
    ? pms_fetch_reservation_sources_grouped($companyId, false)
    : array('*' => array());

if (!function_exists('reservations_ota_options_for_property')) {
    function reservations_ota_options_for_property(array $otaAccountsByProperty, $propertyCode, $includeNone = true)
    {
        if (function_exists('pms_ota_options_for_property')) {
            return pms_ota_options_for_property($otaAccountsByProperty, '', $includeNone ? true : false);
        }
        if ($includeNone) {
            return array(
                array(
                    'id_ota_account' => 0,
                    'ota_name' => 'Directo',
                    'platform' => 'other',
                    'source' => 'otro'
                )
            );
        }
        return array();
    }
}

if (!function_exists('reservations_source_options_for_property')) {
    function reservations_source_options_for_property(array $reservationSourcesByProperty, $propertyCode)
    {
        if (function_exists('pms_reservation_source_options_for_property')) {
            return pms_reservation_source_options_for_property($reservationSourcesByProperty, $propertyCode, true);
        }
        return array(
            array(
                'id_reservation_source' => 0,
                'source_name' => 'Directo',
                'notes' => '',
                'id_property' => null,
                'property_code' => ''
            )
        );
    }
}

if (!function_exists('reservations_source_input_for_selection')) {
    function reservations_source_input_for_selection($selectedSourceId, array $sourceOptions)
    {
        $selectedSourceId = (int)$selectedSourceId;
        foreach ($sourceOptions as $row) {
            $id = isset($row['id_reservation_source']) ? (int)$row['id_reservation_source'] : 0;
            if ($id > 0 && $id === $selectedSourceId) {
                return (string)$id;
            }
        }
        foreach ($sourceOptions as $row) {
            $name = trim((string)(isset($row['source_name']) ? $row['source_name'] : ''));
            if ($name !== '') {
                return $name;
            }
        }
        return 'Directo';
    }
}

if (!function_exists('reservations_origin_options_for_property')) {
    function reservations_origin_options_for_property(array $reservationSourcesByProperty, array $otaAccountsByProperty, $propertyCode)
    {
        $rows = array();
        $seen = array();

        $sourceOptions = reservations_source_options_for_property($reservationSourcesByProperty, $propertyCode);
        foreach ($sourceOptions as $sourceRow) {
            $sourceId = isset($sourceRow['id_reservation_source']) ? (int)$sourceRow['id_reservation_source'] : 0;
            $sourceName = trim((string)(isset($sourceRow['source_name']) ? $sourceRow['source_name'] : ''));
            if ($sourceName === '') {
                $sourceName = $sourceId > 0 ? ('Origen #' . $sourceId) : 'Directo';
            }
            $originKey = 'source:' . $sourceId;
            if (isset($seen[$originKey])) {
                continue;
            }
            $seen[$originKey] = true;
            $rows[] = array(
                'origin_key' => $originKey,
                'origin_label' => $sourceName,
                'source_id' => $sourceId,
                'ota_account_id' => 0,
                'source_value' => $sourceId > 0 ? (string)$sourceId : $sourceName
            );
        }

        $otaOptions = reservations_ota_options_for_property($otaAccountsByProperty, $propertyCode, false);
        foreach ($otaOptions as $otaRow) {
            $otaId = isset($otaRow['id_ota_account']) ? (int)$otaRow['id_ota_account'] : 0;
            if ($otaId <= 0) {
                continue;
            }
            $otaName = trim((string)(isset($otaRow['ota_name']) ? $otaRow['ota_name'] : ''));
            if ($otaName === '') {
                $otaName = 'Origen #' . $otaId;
            }
            $originKey = 'ota:' . $otaId;
            if (isset($seen[$originKey])) {
                continue;
            }
            $seen[$originKey] = true;
            $otaSource = trim((string)(isset($otaRow['source']) ? $otaRow['source'] : ''));
            if ($otaSource === '') {
                $otaSource = 'otro';
            }
            $rows[] = array(
                'origin_key' => $originKey,
                'origin_label' => $otaName,
                'source_id' => 0,
                'ota_account_id' => $otaId,
                'source_value' => $otaSource
            );
        }

        if (!$rows) {
            $rows[] = array(
                'origin_key' => 'source:0',
                'origin_label' => 'Directo',
                'source_id' => 0,
                'ota_account_id' => 0,
                'source_value' => 'Directo'
            );
        }

        return $rows;
    }
}

if (!function_exists('reservations_origin_key_from_input')) {
    function reservations_origin_key_from_input($rawValue)
    {
        $raw = trim((string)$rawValue);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(source|ota):\\d+$/', $raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return 'source:' . (int)$raw;
        }
        return '';
    }
}

if (!function_exists('reservations_origin_row_for_key')) {
    function reservations_origin_row_for_key(array $originOptions, $originKey)
    {
        $key = reservations_origin_key_from_input($originKey);
        if ($key === '') {
            return null;
        }
        foreach ($originOptions as $row) {
            if ((string)(isset($row['origin_key']) ? $row['origin_key'] : '') === $key) {
                return $row;
            }
        }
        return null;
    }
}

$otaMetaById = array();
$otaLodgingCatalogByCatalogId = array();
$otaLodgingLabelByAccountCatalog = array();
$otaInfoCatalogsByAccount = array();
$reservationSourceInfoCatalogsBySource = array();

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            oa.id_ota_account,
            oa.ota_name,
            oa.platform,
            oalc.id_line_item_catalog,
            lic.item_name,
            cat.category_name
         FROM ota_account oa
         JOIN ota_account_lodging_catalog oalc
           ON oalc.id_ota_account = oa.id_ota_account
          AND oalc.deleted_at IS NULL
          AND oalc.is_active = 1
         JOIN line_item_catalog lic
           ON lic.id_line_item_catalog = oalc.id_line_item_catalog
          AND lic.deleted_at IS NULL
          AND lic.is_active = 1
         LEFT JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
         WHERE oa.id_company = ?
           AND oa.deleted_at IS NULL
           AND oa.is_active = 1
         ORDER BY oa.id_ota_account, oalc.sort_order, lic.item_name'
    );
    $stmt->execute(array($companyId));
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $otaId = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
        $catalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($otaId <= 0 || $catalogId <= 0) {
            continue;
        }
        if (!isset($otaMetaById[$otaId])) {
            $platform = isset($row['platform']) ? strtolower((string)$row['platform']) : 'other';
            $otaMetaById[$otaId] = array(
                'ota_name' => isset($row['ota_name']) ? (string)$row['ota_name'] : ('Origen #' . $otaId),
                'platform' => $platform,
                'source' => function_exists('pms_reservation_source_from_ota_platform')
                    ? pms_reservation_source_from_ota_platform($platform)
                    : 'otro'
            );
        }
        if (!isset($otaLodgingCatalogByCatalogId[$catalogId])) {
            $otaLodgingCatalogByCatalogId[$catalogId] = array();
        }
        $otaLodgingCatalogByCatalogId[$catalogId][$otaId] = true;
        if (!isset($otaLodgingLabelByAccountCatalog[$otaId])) {
            $otaLodgingLabelByAccountCatalog[$otaId] = array();
        }
        $itemName = isset($row['item_name']) ? (string)$row['item_name'] : ('Catalogo #' . $catalogId);
        $category = isset($row['category_name']) ? trim((string)$row['category_name']) : '';
        $otaLodgingLabelByAccountCatalog[$otaId][$catalogId] = $category !== '' ? ($category . ' / ' . $itemName) : $itemName;
    }
} catch (Exception $e) {
    $otaMetaById = array();
    $otaLodgingCatalogByCatalogId = array();
    $otaLodgingLabelByAccountCatalog = array();
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            oaic.id_ota_account,
            oaic.id_line_item_catalog,
            oaic.sort_order,
            lic.item_name,
            lic.catalog_type,
            oaic.display_alias AS display_alias,
            cat.category_name
         FROM ota_account_info_catalog oaic
         JOIN ota_account oa
           ON oa.id_ota_account = oaic.id_ota_account
          AND oa.deleted_at IS NULL
          AND oa.is_active = 1
         JOIN line_item_catalog lic
           ON lic.id_line_item_catalog = oaic.id_line_item_catalog
          AND lic.deleted_at IS NULL
          AND lic.is_active = 1
         LEFT JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
         WHERE oa.id_company = ?
           AND oaic.deleted_at IS NULL
           AND oaic.is_active = 1
         ORDER BY oaic.id_ota_account, oaic.sort_order, lic.item_name'
    );
    $stmt->execute(array($companyId));
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $otaId = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
        $catalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($otaId <= 0 || $catalogId <= 0) {
            continue;
        }
        if (!isset($otaInfoCatalogsByAccount[$otaId])) {
            $otaInfoCatalogsByAccount[$otaId] = array();
        }
        $itemName = isset($row['item_name']) ? (string)$row['item_name'] : ('Catalogo #' . $catalogId);
        $category = isset($row['category_name']) ? trim((string)$row['category_name']) : '';
        $alias = isset($row['display_alias']) ? trim((string)$row['display_alias']) : '';
        $type = strtoupper(trim(isset($row['catalog_type']) ? (string)$row['catalog_type'] : ''));
        $defaultLabel = $category !== '' ? ($category . ' / ' . $itemName) : $itemName;
        $label = $alias !== '' ? $alias : $defaultLabel;
        $otaInfoCatalogsByAccount[$otaId][] = array(
            'id_line_item_catalog' => $catalogId,
            'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
            'label' => $label,
            'catalog_type' => $type
        );
    }
} catch (Exception $e) {
    $otaInfoCatalogsByAccount = array();
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            rsic.id_reservation_source,
            rsic.id_line_item_catalog,
            rsic.sort_order,
            lic.item_name,
            lic.catalog_type,
            rsic.display_alias AS display_alias,
            cat.category_name
         FROM reservation_source_info_catalog rsic
         JOIN reservation_source_catalog rsc
           ON rsc.id_reservation_source = rsic.id_reservation_source
          AND rsc.deleted_at IS NULL
          AND COALESCE(rsc.is_active, 1) = 1
         JOIN line_item_catalog lic
           ON lic.id_line_item_catalog = rsic.id_line_item_catalog
          AND lic.deleted_at IS NULL
          AND COALESCE(lic.is_active, 1) = 1
         LEFT JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
         WHERE rsc.id_company = ?
           AND rsic.deleted_at IS NULL
           AND COALESCE(rsic.is_active, 1) = 1
         ORDER BY rsic.id_reservation_source, rsic.sort_order, lic.item_name'
    );
    $stmt->execute(array($companyId));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceId = isset($row['id_reservation_source']) ? (int)$row['id_reservation_source'] : 0;
        $catalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($sourceId <= 0 || $catalogId <= 0) {
            continue;
        }
        if (!isset($reservationSourceInfoCatalogsBySource[$sourceId])) {
            $reservationSourceInfoCatalogsBySource[$sourceId] = array();
        }
        $itemName = isset($row['item_name']) ? (string)$row['item_name'] : ('Catalogo #' . $catalogId);
        $category = isset($row['category_name']) ? trim((string)$row['category_name']) : '';
        $alias = isset($row['display_alias']) ? trim((string)$row['display_alias']) : '';
        $type = strtoupper(trim(isset($row['catalog_type']) ? (string)$row['catalog_type'] : ''));
        $defaultLabel = $category !== '' ? ($category . ' / ' . $itemName) : $itemName;
        $label = $alias !== '' ? $alias : $defaultLabel;
        $reservationSourceInfoCatalogsBySource[$sourceId][] = array(
            'id_line_item_catalog' => $catalogId,
            'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
            'label' => $label,
            'catalog_type' => $type
        );
    }
} catch (Exception $e) {
    $reservationSourceInfoCatalogsBySource = array();
}

$paymentCatalogsByProperty = array();
$paymentCatalogsById = array();
try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            lic.id_line_item_catalog AS id_payment_catalog,
            COALESCE(NULLIF(TRIM(lic.item_name), \'\'), CONCAT(\'Catalogo #\', lic.id_line_item_catalog)) AS name,
            cat.id_property,
            prop.code AS property_code,
            cat.category_name
         FROM line_item_catalog lic
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.deleted_at IS NULL
          AND COALESCE(cat.is_active, 1) = 1
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE cat.id_company = ?
           AND lic.deleted_at IS NULL
           AND COALESCE(lic.is_active, 1) = 1
           AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) IN (\'payment\', \'pago\')
         ORDER BY
           CASE WHEN prop.code IS NULL OR prop.code = \'\' THEN 0 ELSE 1 END,
           prop.code,
           cat.category_name,
           lic.item_name,
           lic.id_line_item_catalog'
    );
    $stmt->execute(array($companyId));
    $catalogRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($catalogRows as $src) {
        $pid = isset($src['id_payment_catalog']) ? (int)$src['id_payment_catalog'] : 0;
        if ($pid <= 0) {
            continue;
        }
        $name = isset($src['name']) ? trim((string)$src['name']) : ('Catalogo #' . $pid);
        $categoryName = isset($src['category_name']) ? trim((string)$src['category_name']) : '';
        $propertyCode = strtoupper(trim((string)(isset($src['property_code']) ? $src['property_code'] : '')));
        $label = $categoryName !== '' ? ($categoryName . ' / ' . $name) : $name;
        $paymentCatalogsById[$pid] = array(
            'id_payment_catalog' => $pid,
            'name' => $name,
            'label' => $label,
            'id_property' => isset($src['id_property']) ? (int)$src['id_property'] : null,
            'property_code' => $propertyCode
        );
    }

    $loadSelectedPaymentCatalogIds = function ($scopePropertyCode) use ($companyCode) {
        $scopePropertyCode = strtoupper(trim((string)$scopePropertyCode));
        try {
            $sets = pms_call_procedure('sp_pms_settings_data', array(
                $companyCode,
                $scopePropertyCode !== '' ? $scopePropertyCode : null
            ));
            $row = isset($sets[0][0]) ? $sets[0][0] : null;
            if (!$row || !isset($row['payment_catalog_ids'])) {
                return array();
            }
            $raw = trim((string)$row['payment_catalog_ids']);
            if ($raw === '') {
                return array();
            }
            return array_values(array_filter(array_map('intval', explode(',', $raw)), function ($id) {
                return $id > 0;
            }));
        } catch (Exception $e) {
            return array();
        }
    };

    $selectedByScope = array('*' => $loadSelectedPaymentCatalogIds(''));
    foreach ($propertiesByCode as $propCodeKey => $ignoreProperty) {
        $selectedByScope[$propCodeKey] = $loadSelectedPaymentCatalogIds($propCodeKey);
    }
    $hasExplicitSelection = false;
    foreach ($selectedByScope as $ids) {
        if (!empty($ids)) {
            $hasExplicitSelection = true;
            break;
        }
    }

    $appendCatalogToScope = function (array &$scopeMap, array $catalogRow) {
        $catalogId = isset($catalogRow['id_payment_catalog']) ? (int)$catalogRow['id_payment_catalog'] : 0;
        if ($catalogId <= 0) {
            return;
        }
        $scopeKey = isset($catalogRow['property_code']) ? strtoupper(trim((string)$catalogRow['property_code'])) : '';
        if ($scopeKey === '') {
            $scopeKey = '*';
        }
        if (!isset($scopeMap[$scopeKey])) {
            $scopeMap[$scopeKey] = array();
        }
        foreach ($scopeMap[$scopeKey] as $existing) {
            if ((int)(isset($existing['id_payment_catalog']) ? $existing['id_payment_catalog'] : 0) === $catalogId) {
                return;
            }
        }
        $scopeMap[$scopeKey][] = $catalogRow;
    };

    if ($hasExplicitSelection) {
        foreach ($selectedByScope as $scopeKey => $selectedIds) {
            foreach ($selectedIds as $selectedId) {
                $selectedId = (int)$selectedId;
                if ($selectedId <= 0 || !isset($paymentCatalogsById[$selectedId])) {
                    continue;
                }
                $appendCatalogToScope($paymentCatalogsByProperty, $paymentCatalogsById[$selectedId]);
            }
        }
    } else {
        foreach ($paymentCatalogsById as $catalogRow) {
            $appendCatalogToScope($paymentCatalogsByProperty, $catalogRow);
        }
    }

    foreach ($paymentCatalogsByProperty as &$scopeRows) {
        usort($scopeRows, function ($a, $b) {
            $aLabel = isset($a['label']) ? (string)$a['label'] : (isset($a['name']) ? (string)$a['name'] : '');
            $bLabel = isset($b['label']) ? (string)$b['label'] : (isset($b['name']) ? (string)$b['name'] : '');
            return strcasecmp($aLabel, $bLabel);
        });
    }
    unset($scopeRows);
} catch (Exception $e) {
    $paymentCatalogsByProperty = array();
    $paymentCatalogsById = array();
}

$serviceCatalogsByProperty = array();
$serviceCatalogsById = array();
try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            lic.id_line_item_catalog AS id_service_catalog,
            COALESCE(NULLIF(TRIM(lic.item_name), \'\'), CONCAT(\'Catalogo #\', lic.id_line_item_catalog)) AS name,
            COALESCE(lic.default_unit_price_cents, 0) AS default_unit_price_cents,
            COALESCE(lic.default_amount_cents, 0) AS default_amount_cents,
            cat.id_property,
            prop.code AS property_code,
            cat.category_name AS subcategory_name,
            parent_cat.category_name AS category_name
         FROM line_item_catalog lic
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.deleted_at IS NULL
          AND COALESCE(cat.is_active, 1) = 1
         LEFT JOIN sale_item_category parent_cat
           ON parent_cat.id_sale_item_category = cat.id_parent_sale_item_category
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE cat.id_company = ?
           AND lic.deleted_at IS NULL
           AND COALESCE(lic.is_active, 1) = 1
           AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) = \'sale_item\'
         ORDER BY
           CASE WHEN prop.code IS NULL OR prop.code = \'\' THEN 0 ELSE 1 END,
           prop.code,
           parent_cat.category_name,
           cat.category_name,
           lic.item_name,
           lic.id_line_item_catalog'
    );
    $stmt->execute(array($companyId));
    $catalogRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($catalogRows as $src) {
        $sid = isset($src['id_service_catalog']) ? (int)$src['id_service_catalog'] : 0;
        if ($sid <= 0) {
            continue;
        }
        $name = isset($src['name']) ? trim((string)$src['name']) : ('Catalogo #' . $sid);
        $categoryName = isset($src['category_name']) ? trim((string)$src['category_name']) : '';
        $subcategoryName = isset($src['subcategory_name']) ? trim((string)$src['subcategory_name']) : '';
        $propertyCodeTmp = strtoupper(trim((string)(isset($src['property_code']) ? $src['property_code'] : '')));
        $labelParts = array();
        if ($categoryName !== '') {
            $labelParts[] = $categoryName;
        }
        if ($subcategoryName !== '' && strcasecmp($subcategoryName, $categoryName) !== 0) {
            $labelParts[] = $subcategoryName;
        }
        $labelParts[] = $name;
        $serviceCatalogsById[$sid] = array(
            'id_service_catalog' => $sid,
            'name' => $name,
            'label' => implode(' / ', array_filter($labelParts)),
            'id_property' => isset($src['id_property']) ? (int)$src['id_property'] : null,
            'property_code' => $propertyCodeTmp,
            'category_name' => $categoryName,
            'subcategory_name' => $subcategoryName,
            'default_unit_price_cents' => (isset($src['default_amount_cents']) && (int)$src['default_amount_cents'] > 0)
                ? (int)$src['default_amount_cents']
                : (isset($src['default_unit_price_cents']) ? (int)$src['default_unit_price_cents'] : 0)
        );
    }

    $serviceSelectedFallbackByScope = array();
    try {
        $stmtServiceTable = $pdo->prepare(
            'SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = \'pms_settings_service_catalog\''
        );
        $stmtServiceTable->execute();
        $hasServiceTable = ((int)$stmtServiceTable->fetchColumn()) > 0;
        if ($hasServiceTable) {
            $stmtServiceSelection = $pdo->prepare(
                'SELECT DISTINCT
                    s.id_sale_item_catalog,
                    UPPER(TRIM(COALESCE(pr.code, \'\'))) AS property_code
                 FROM pms_settings_service_catalog s
                 LEFT JOIN property pr
                   ON pr.id_property = s.id_property
                  AND pr.deleted_at IS NULL
                 WHERE s.id_company = ?
                   AND s.deleted_at IS NULL
                   AND COALESCE(s.is_active, 1) = 1'
            );
            $stmtServiceSelection->execute(array($companyId));
            $selectedRows = $stmtServiceSelection->fetchAll(PDO::FETCH_ASSOC);
            foreach ($selectedRows as $selectedRow) {
                $selectedCatalogId = isset($selectedRow['id_sale_item_catalog']) ? (int)$selectedRow['id_sale_item_catalog'] : 0;
                if ($selectedCatalogId <= 0) {
                    continue;
                }
                $selectedScope = isset($selectedRow['property_code']) ? strtoupper(trim((string)$selectedRow['property_code'])) : '';
                if ($selectedScope === '') {
                    $selectedScope = '*';
                }
                if (!isset($serviceSelectedFallbackByScope[$selectedScope])) {
                    $serviceSelectedFallbackByScope[$selectedScope] = array();
                }
                $serviceSelectedFallbackByScope[$selectedScope][$selectedCatalogId] = true;
            }
        }
    } catch (Exception $e) {
        $serviceSelectedFallbackByScope = array();
    }

    $loadSelectedServiceCatalogIds = function ($scopePropertyCode) use ($companyCode, $serviceSelectedFallbackByScope) {
        $scopePropertyCode = strtoupper(trim((string)$scopePropertyCode));
        try {
            $sets = pms_call_procedure('sp_pms_settings_data', array(
                $companyCode,
                $scopePropertyCode !== '' ? $scopePropertyCode : null
            ));
            $row = isset($sets[0][0]) ? $sets[0][0] : null;
            if ($row && isset($row['service_catalog_ids'])) {
                $raw = trim((string)$row['service_catalog_ids']);
                if ($raw !== '') {
                    return array_values(array_filter(array_map('intval', explode(',', $raw)), function ($id) {
                        return $id > 0;
                    }));
                }
            }
        } catch (Exception $e) {
        }
        $scopeKey = $scopePropertyCode !== '' ? $scopePropertyCode : '*';
        if (!isset($serviceSelectedFallbackByScope[$scopeKey])) {
            return array();
        }
        return array_map('intval', array_keys($serviceSelectedFallbackByScope[$scopeKey]));
    };

    $selectedByScope = array('*' => $loadSelectedServiceCatalogIds(''));
    foreach ($propertiesByCode as $propCodeKey => $ignoreProperty) {
        $selectedByScope[$propCodeKey] = $loadSelectedServiceCatalogIds($propCodeKey);
    }

    $appendCatalogToScope = function (array &$scopeMap, array $catalogRow) {
        $catalogId = isset($catalogRow['id_service_catalog']) ? (int)$catalogRow['id_service_catalog'] : 0;
        if ($catalogId <= 0) {
            return;
        }
        $scopeKey = isset($catalogRow['property_code']) ? strtoupper(trim((string)$catalogRow['property_code'])) : '';
        if ($scopeKey === '') {
            $scopeKey = '*';
        }
        if (!isset($scopeMap[$scopeKey])) {
            $scopeMap[$scopeKey] = array();
        }
        foreach ($scopeMap[$scopeKey] as $existing) {
            if ((int)(isset($existing['id_service_catalog']) ? $existing['id_service_catalog'] : 0) === $catalogId) {
                return;
            }
        }
        $scopeMap[$scopeKey][] = $catalogRow;
    };

    foreach ($selectedByScope as $selectedIds) {
        foreach ($selectedIds as $selectedId) {
            $selectedId = (int)$selectedId;
            if ($selectedId <= 0 || !isset($serviceCatalogsById[$selectedId])) {
                continue;
            }
            $appendCatalogToScope($serviceCatalogsByProperty, $serviceCatalogsById[$selectedId]);
        }
    }

    foreach ($serviceCatalogsByProperty as &$scopeRows) {
        usort($scopeRows, function ($a, $b) {
            $aCategory = isset($a['category_name']) ? (string)$a['category_name'] : '';
            $bCategory = isset($b['category_name']) ? (string)$b['category_name'] : '';
            $cmpCategory = strcasecmp($aCategory, $bCategory);
            if ($cmpCategory !== 0) {
                return $cmpCategory;
            }
            $aSubcategory = isset($a['subcategory_name']) ? (string)$a['subcategory_name'] : '';
            $bSubcategory = isset($b['subcategory_name']) ? (string)$b['subcategory_name'] : '';
            $cmpSubcategory = strcasecmp($aSubcategory, $bSubcategory);
            if ($cmpSubcategory !== 0) {
                return $cmpSubcategory;
            }
            $aLabel = isset($a['label']) ? (string)$a['label'] : (isset($a['name']) ? (string)$a['name'] : '');
            $bLabel = isset($b['label']) ? (string)$b['label'] : (isset($b['name']) ? (string)$b['name'] : '');
            return strcasecmp($aLabel, $bLabel);
        });
    }
    unset($scopeRows);
} catch (Exception $e) {
    $serviceCatalogsByProperty = array();
    $serviceCatalogsById = array();
}

if (!function_exists('reservations_payment_catalogs_for_property')) {
    function reservations_payment_catalogs_for_property(array $map, $propertyCode)
    {
        $prop = strtoupper((string)$propertyCode);
        $out = array();
        if (isset($map['*'])) {
            $out = array_merge($out, $map['*']);
        }
        if ($prop !== '' && isset($map[$prop])) {
            $out = array_merge($out, $map[$prop]);
        }
        return $out;
    }
}

if (!function_exists('reservations_payment_catalogs_for_reservation')) {
    function reservations_payment_catalogs_for_reservation(
        array $map,
        $propertyCode,
        $companyId,
        $reservationId,
        array $blockedByReservation = array()
    ) {
        $rows = reservations_payment_catalogs_for_property($map, $propertyCode);
        $reservationId = (int)$reservationId;
        if ($reservationId <= 0) {
            return $rows;
        }
        if (isset($blockedByReservation[$reservationId]) && is_array($blockedByReservation[$reservationId])) {
            $blockedIds = $blockedByReservation[$reservationId];
        } else {
            $blockedIds = function_exists('pms_reservation_blocked_payment_catalog_ids')
                ? pms_reservation_blocked_payment_catalog_ids($companyId, $reservationId)
                : array();
        }
        if (!$blockedIds) {
            return $rows;
        }
        if (function_exists('pms_filter_payment_catalog_rows_by_blocked_ids')) {
            return pms_filter_payment_catalog_rows_by_blocked_ids($rows, $blockedIds);
        }
        return $rows;
    }
}

if (!function_exists('reservations_service_catalogs_for_property')) {
    function reservations_service_catalogs_for_property(array $map, $propertyCode)
    {
        $prop = strtoupper((string)$propertyCode);
        $out = array();
        if (isset($map['*'])) {
            $out = array_merge($out, $map['*']);
        }
        if ($prop !== '' && isset($map[$prop])) {
            $out = array_merge($out, $map[$prop]);
        }
        $dedupe = array();
        $final = array();
        foreach ($out as $row) {
            $id = isset($row['id_service_catalog']) ? (int)$row['id_service_catalog'] : 0;
            if ($id <= 0 || isset($dedupe[$id])) {
                continue;
            }
            $dedupe[$id] = true;
            $final[] = $row;
        }
        return $final;
    }
}

if (!function_exists('reservations_folio_role_by_name')) {
    function reservations_folio_role_by_name($folioName)
    {
        $name = strtolower(trim((string)$folioName));
        if ($name === '') {
            return 'lodging';
        }
        $hasServiceWord = (strpos($name, 'servicio') !== false || strpos($name, 'service') !== false);
        $hasLodgingHint = (strpos($name, 'hosped') !== false || strpos($name, 'principal') !== false || strpos($name, 'main') !== false);
        if ($hasServiceWord && !$hasLodgingHint) {
            return 'services';
        }
        return 'lodging';
    }
}

if (!function_exists('reservations_payment_method_name_by_id')) {
    function reservations_payment_method_name_by_id(array $paymentMethodsById, $methodId)
    {
        $methodId = (int)$methodId;
        if ($methodId <= 0) {
            return '';
        }
        if (!isset($paymentMethodsById[$methodId])) {
            return '';
        }
        return trim((string)(isset($paymentMethodsById[$methodId]['name']) ? $paymentMethodsById[$methodId]['name'] : ''));
    }
}

if (!function_exists('reservations_property_code_for_reservation')) {
    function reservations_property_code_for_reservation($companyId, $reservationId)
    {
        $companyId = (int)$companyId;
        $reservationId = (int)$reservationId;
        if ($companyId <= 0 || $reservationId <= 0) {
            return '';
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT p.code
                 FROM reservation r
                 JOIN property p ON p.id_property = r.id_property
                 WHERE r.id_reservation = ?
                   AND p.id_company = ?
                   AND r.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($reservationId, $companyId));
            $code = $stmt->fetchColumn();
            return $code !== false ? strtoupper((string)$code) : '';
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('reservations_extract_line_item_id_from_result_sets')) {
    function reservations_extract_line_item_id_from_result_sets($resultSets)
    {
        if (!is_array($resultSets)) {
            return 0;
        }
        foreach ($resultSets as $set) {
            if (!is_array($set)) {
                continue;
            }
            foreach ($set as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (isset($row['id_sale_item']) && (int)$row['id_sale_item'] > 0) {
                    return (int)$row['id_sale_item'];
                }
                if (isset($row['id_line_item']) && (int)$row['id_line_item'] > 0) {
                    return (int)$row['id_line_item'];
                }
            }
        }
        return 0;
    }
}

if (!function_exists('reservations_resolve_payment_line_item_catalog_id')) {
    function reservations_resolve_payment_line_item_catalog_id($companyCode, $companyId, $propertyCode, $reservationId = 0)
    {
        static $cache = array();

        $companyCode = trim((string)$companyCode);
        $companyId = (int)$companyId;
        $propertyCode = strtoupper(trim((string)$propertyCode));
        $reservationId = (int)$reservationId;
        if ($companyCode === '' || $companyId <= 0) {
            return 0;
        }

        $cacheKey = $companyId . '|' . $propertyCode;
        if (array_key_exists($cacheKey, $cache)) {
            return (int)$cache[$cacheKey];
        }

        $catalogCandidates = array();
        $loadSettingsCatalogIds = function ($scopePropertyCode) use ($companyCode) {
            try {
                $sets = pms_call_procedure('sp_pms_settings_data', array(
                    $companyCode,
                    $scopePropertyCode !== '' ? $scopePropertyCode : null
                ));
                $row = isset($sets[0][0]) ? $sets[0][0] : null;
                if (!$row || !isset($row['payment_catalog_ids'])) {
                    return array();
                }
                $raw = trim((string)$row['payment_catalog_ids']);
                if ($raw === '') {
                    return array();
                }
                return array_values(array_filter(array_map('intval', explode(',', $raw)), function ($id) {
                    return $id > 0;
                }));
            } catch (Exception $e) {
                return array();
            }
        };

        $catalogCandidates = $loadSettingsCatalogIds($propertyCode);
        if (!$catalogCandidates && $propertyCode !== '') {
            $catalogCandidates = $loadSettingsCatalogIds('');
        }

        if ($catalogCandidates) {
            try {
                $pdo = pms_get_connection();
                $placeholders = implode(',', array_fill(0, count($catalogCandidates), '?'));
                $sql = 'SELECT id_line_item_catalog
                        FROM line_item_catalog
                        WHERE id_line_item_catalog IN (' . $placeholders . ')
                          AND deleted_at IS NULL
                          AND COALESCE(is_active, 1) = 1
                          AND LOWER(TRIM(COALESCE(catalog_type, \'\'))) IN (\'payment\', \'pago\')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($catalogCandidates);
                $valid = array();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $id = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
                    if ($id > 0) {
                        $valid[$id] = true;
                    }
                }
                foreach ($catalogCandidates as $candidateId) {
                    $candidateId = (int)$candidateId;
                    if ($candidateId > 0 && isset($valid[$candidateId])) {
                        $cache[$cacheKey] = $candidateId;
                        return $candidateId;
                    }
                }
            } catch (Exception $e) {
            }
        }

        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT lic.id_line_item_catalog
                 FROM line_item_catalog lic
                 JOIN sale_item_category cat
                   ON cat.id_sale_item_category = lic.id_category
                  AND cat.deleted_at IS NULL
                  AND COALESCE(cat.is_active, 1) = 1
                 LEFT JOIN property prop
                   ON prop.id_property = cat.id_property
                 WHERE cat.id_company = ?
                   AND lic.deleted_at IS NULL
                   AND COALESCE(lic.is_active, 1) = 1
                   AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) IN (\'payment\', \'pago\')
                   AND (? = \'\' OR prop.code = ? OR prop.code IS NULL)
                 ORDER BY
                   CASE
                     WHEN prop.code = ? THEN 0
                     WHEN prop.code IS NULL THEN 1
                     ELSE 2
                   END,
                   lic.id_line_item_catalog
                 LIMIT 1'
            );
            $stmt->execute(array($companyId, $propertyCode, $propertyCode, $propertyCode));
            $catalogId = (int)$stmt->fetchColumn();
            if ($catalogId > 0) {
                $cache[$cacheKey] = $catalogId;
                return $catalogId;
            }
        } catch (Exception $e) {
        }

        if ($reservationId > 0) {
            try {
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare(
                    'SELECT li.id_line_item_catalog
                     FROM line_item li
                     JOIN folio f
                       ON f.id_folio = li.id_folio
                     LEFT JOIN line_item_catalog lic
                       ON lic.id_line_item_catalog = li.id_line_item_catalog
                     WHERE f.id_reservation = ?
                       AND li.item_type = \'payment\'
                       AND li.id_line_item_catalog IS NOT NULL
                       AND li.id_line_item_catalog > 0
                       AND li.deleted_at IS NULL
                       AND COALESCE(li.is_active, 1) = 1
                       AND (li.status IS NULL OR li.status NOT IN (\'void\', \'canceled\'))
                       AND lic.deleted_at IS NULL
                       AND COALESCE(lic.is_active, 1) = 1
                     ORDER BY li.id_line_item DESC
                     LIMIT 1'
                );
                $stmt->execute(array($reservationId));
                $catalogId = (int)$stmt->fetchColumn();
                if ($catalogId > 0) {
                    $cache[$cacheKey] = $catalogId;
                    return $catalogId;
                }
            } catch (Exception $e) {
            }
        }

        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT lic.id_line_item_catalog
                 FROM line_item_catalog lic
                 JOIN sale_item_category cat
                   ON cat.id_sale_item_category = lic.id_category
                  AND cat.deleted_at IS NULL
                  AND COALESCE(cat.is_active, 1) = 1
                 WHERE cat.id_company = ?
                   AND lic.deleted_at IS NULL
                   AND COALESCE(lic.is_active, 1) = 1
                   AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) IN (\'payment\', \'pago\')
                 ORDER BY lic.id_line_item_catalog
                 LIMIT 1'
            );
            $stmt->execute(array($companyId));
            $catalogId = (int)$stmt->fetchColumn();
            if ($catalogId > 0) {
                $cache[$cacheKey] = $catalogId;
                return $catalogId;
            }
        } catch (Exception $e) {
        }

        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->query(
                'SELECT lic.id_line_item_catalog
                 FROM line_item_catalog lic
                 WHERE lic.deleted_at IS NULL
                   AND COALESCE(lic.is_active, 1) = 1
                   AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) IN (\'payment\', \'pago\')
                 ORDER BY lic.id_line_item_catalog
                 LIMIT 1'
            );
            $catalogId = (int)$stmt->fetchColumn();
            if ($catalogId > 0) {
                $cache[$cacheKey] = $catalogId;
                return $catalogId;
            }
        } catch (Exception $e) {
        }
        $cache[$cacheKey] = 0;
        return 0;
    }
}

if (!function_exists('reservations_fetch_folio_currency')) {
    function reservations_fetch_folio_currency($folioId)
    {
        $folioId = (int)$folioId;
        if ($folioId <= 0) {
            return 'MXN';
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare('SELECT currency FROM folio WHERE id_folio = ? LIMIT 1');
            $stmt->execute(array($folioId));
            $raw = trim((string)$stmt->fetchColumn());
            if ($raw !== '') {
                return $raw;
            }
        } catch (Exception $e) {
        }
        return 'MXN';
    }
}

if (!function_exists('reservations_payment_meta_upsert_safe')) {
    function reservations_payment_meta_upsert_safe($paymentId, $methodName, $reference, $status, $actorUserId)
    {
        $paymentId = (int)$paymentId;
        if ($paymentId <= 0) {
            return;
        }
        $methodName = trim((string)$methodName);
        $reference = trim((string)$reference);
        $status = trim((string)$status);
        if ($status === '') {
            $status = 'captured';
        }
        try {
            pms_call_procedure('sp_line_item_payment_meta_upsert', array(
                $paymentId,
                $methodName !== '' ? $methodName : null,
                $reference !== '' ? $reference : null,
                $status,
                $actorUserId
            ));
            return;
        } catch (Exception $e) {
        }

        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE line_item
                 SET method = ?,
                     reference = ?,
                     status = ?,
                     updated_at = NOW()
                 WHERE id_line_item = ?
                   AND item_type = \'payment\'
                   AND deleted_at IS NULL'
            );
            $stmt->execute(array(
                $methodName !== '' ? $methodName : null,
                $reference !== '' ? $reference : null,
                $status,
                $paymentId
            ));
        } catch (Exception $e) {
        }
    }
}

if (!function_exists('reservations_create_payment_line_item')) {
    function reservations_create_payment_line_item($folioId, $reservationId, $paymentCatalogId, $serviceDate, $amountCents, $status, $actorUserId, $methodName = '', $reference = '')
    {
        $folioId = (int)$folioId;
        $reservationId = (int)$reservationId;
        $paymentCatalogId = (int)$paymentCatalogId;
        $amountCents = (int)$amountCents;
        $serviceDate = trim((string)$serviceDate);
        $status = trim((string)$status);
        if ($status === '') {
            $status = 'captured';
        }
        if ($serviceDate === '') {
            $serviceDate = date('Y-m-d');
        }
        if ($folioId <= 0) {
            throw new Exception('Selecciona un folio valido para registrar el pago.');
        }
        if ($reservationId <= 0) {
            throw new Exception('Selecciona una reserva valida para registrar el pago.');
        }
        if ($amountCents <= 0) {
            throw new Exception('El monto del pago debe ser mayor a 0.');
        }

        $createdPaymentId = 0;
        if ($paymentCatalogId > 0) {
            $createSets = pms_call_procedure('sp_sale_item_upsert', array(
                'create',
                0,
                $folioId,
                $reservationId,
                $paymentCatalogId,
                null,
                $serviceDate,
                1,
                $amountCents,
                0,
                $status,
                $actorUserId
            ));
            $createdPaymentId = reservations_extract_line_item_id_from_result_sets($createSets);
        } else {
            $currency = reservations_fetch_folio_currency($folioId);
            $db = pms_get_connection();
            $stmt = $db->prepare(
                'INSERT INTO line_item (
                    item_type,
                    id_user,
                    id_folio,
                    id_line_item_catalog,
                    description,
                    service_date,
                    quantity,
                    unit_price_cents,
                    amount_cents,
                    currency,
                    discount_amount_cents,
                    status,
                    method,
                    reference,
                    is_active,
                    created_at,
                    created_by,
                    updated_at
                 ) VALUES (
                    \'payment\',
                    ?,
                    ?,
                    NULL,
                    NULL,
                    ?,
                    1,
                    ?,
                    ?,
                    ?,
                    0,
                    ?,
                    ?,
                    ?,
                    1,
                    NOW(),
                    ?,
                    NOW()
                 )'
            );
            $stmt->execute(array(
                $actorUserId,
                $folioId,
                $serviceDate,
                $amountCents,
                $amountCents,
                $currency !== '' ? $currency : 'MXN',
                $status,
                trim((string)$methodName) !== '' ? trim((string)$methodName) : null,
                trim((string)$reference) !== '' ? trim((string)$reference) : null,
                $actorUserId
            ));
            $createdPaymentId = (int)$db->lastInsertId();
        }

        if ($createdPaymentId <= 0) {
            throw new Exception('No se pudo determinar el pago creado.');
        }

        reservations_payment_meta_upsert_safe(
            $createdPaymentId,
            $methodName,
            $reference,
            $status,
            $actorUserId
        );
        if ($paymentCatalogId > 0) {
            $propertyCodeForReservation = '';
            try {
                $pdo = pms_get_connection();
                $stmtReservationProperty = $pdo->prepare(
                    'SELECT UPPER(TRIM(COALESCE(p.code, \'\'))) AS property_code
                       FROM reservation r
                       JOIN property p ON p.id_property = r.id_property
                      WHERE r.id_reservation = ?
                      LIMIT 1'
                );
                $stmtReservationProperty->execute(array($reservationId));
                $propertyCodeForReservation = strtoupper(trim((string)$stmtReservationProperty->fetchColumn()));
            } catch (Exception $e) {
                $propertyCodeForReservation = '';
            }

            $fixedChildrenByParentMap = reservations_fetch_fixed_children_by_parent(
                isset($GLOBALS['companyCode']) ? (string)$GLOBALS['companyCode'] : '',
                $propertyCodeForReservation,
                isset($GLOBALS['companyId']) ? (int)$GLOBALS['companyId'] : 0
            );
            if (!empty($fixedChildrenByParentMap)) {
                $fixedPath = array();
                reservations_upsert_fixed_children_tree(
                    $reservationId,
                    $folioId,
                    $serviceDate,
                    $actorUserId,
                    $paymentCatalogId,
                    $fixedChildrenByParentMap,
                    $fixedPath,
                    0
                );
            }
            reservations_recalc_derived_tree_for_catalog(
                $folioId,
                $reservationId,
                $paymentCatalogId,
                $serviceDate,
                $actorUserId
            );
            if (!empty($fixedChildrenByParentMap)) {
                $percentPath = array();
                reservations_apply_fixed_children_for_percent_descendants(
                    $reservationId,
                    $folioId,
                    $serviceDate,
                    $actorUserId,
                    $paymentCatalogId,
                    $fixedChildrenByParentMap,
                    $percentPath,
                    0
                );
            }
        }
        try {
            pms_call_procedure('sp_folio_recalc', array($folioId));
        } catch (Exception $e) {
        }

        return $createdPaymentId;
    }
}

if (!function_exists('reservations_resolve_payment_transfer_target')) {
    function reservations_resolve_payment_transfer_target($companyId, $reservationId, $sourceFolioId, $requestedTargetFolioId = 0)
    {
        $companyId = (int)$companyId;
        $reservationId = (int)$reservationId;
        $sourceFolioId = (int)$sourceFolioId;
        $requestedTargetFolioId = (int)$requestedTargetFolioId;
        $context = array(
            'source_balance_cents' => 0,
            'target_folio_id' => 0,
            'target_balance_cents' => 0
        );
        if ($companyId <= 0 || $reservationId <= 0 || $sourceFolioId <= 0) {
            return $context;
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT
                    f.id_folio,
                    COALESCE(f.balance_cents, 0) AS balance_cents
                 FROM folio f
                 JOIN reservation r ON r.id_reservation = f.id_reservation
                 JOIN property p ON p.id_property = r.id_property
                 WHERE f.id_reservation = ?
                   AND p.id_company = ?
                   AND f.deleted_at IS NULL
                   AND COALESCE(f.is_active, 1) = 1
                   AND COALESCE(f.status, \'open\') = \'open\'
                 ORDER BY f.id_folio ASC'
            );
            $stmt->execute(array($reservationId, $companyId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fallbackTargetId = 0;
            $fallbackTargetBalance = 0;
            foreach ($rows as $row) {
                $folioId = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
                $balanceCents = isset($row['balance_cents']) ? max(0, (int)$row['balance_cents']) : 0;
                if ($folioId <= 0) {
                    continue;
                }
                if ($folioId === $sourceFolioId) {
                    $context['source_balance_cents'] = $balanceCents;
                    continue;
                }
                if ($balanceCents <= 0) {
                    continue;
                }
                if ($requestedTargetFolioId > 0 && $folioId === $requestedTargetFolioId) {
                    $context['target_folio_id'] = $folioId;
                    $context['target_balance_cents'] = $balanceCents;
                    continue;
                }
                if ($fallbackTargetId <= 0) {
                    $fallbackTargetId = $folioId;
                    $fallbackTargetBalance = $balanceCents;
                }
            }
            if ($context['target_folio_id'] <= 0 && $fallbackTargetId > 0) {
                $context['target_folio_id'] = $fallbackTargetId;
                $context['target_balance_cents'] = $fallbackTargetBalance;
            }
        } catch (Exception $e) {
            return $context;
        }
        return $context;
    }
}

if (!function_exists('reservations_render_filter_hiddens')) {
    function reservations_render_filter_hiddens(array $filters)
    {
        foreach ($filters as $name => $value) {
            $field = 'reservations_filter_' . $name;
            echo '<input type="hidden" name="'
                . htmlspecialchars($field, ENT_QUOTES, 'UTF-8')
                . '" value="'
                . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
                . '">';
        }
    }
}

if (!function_exists('reservations_build_return_url')) {
    function reservations_build_return_url(array $filters, $reservationId = 0)
    {
        $reservationId = (int)$reservationId;
        if ($reservationId > 0) {
            return 'index.php?view=reservations&open_reservation=' . $reservationId;
        }
        $returnView = strtolower(trim((string)(isset($filters['return_view']) ? $filters['return_view'] : '')));
        if ($returnView === 'calendar') {
            $params = array(
                'view' => 'calendar'
            );
            $returnPropertyCode = strtoupper(trim((string)(isset($filters['return_property_code']) ? $filters['return_property_code'] : '')));
            $returnStartDate = trim((string)(isset($filters['return_start_date']) ? $filters['return_start_date'] : ''));
            $returnViewMode = trim((string)(isset($filters['return_view_mode']) ? $filters['return_view_mode'] : ''));
            $returnOrderMode = trim((string)(isset($filters['return_order_mode']) ? $filters['return_order_mode'] : ''));

            if ($returnPropertyCode !== '') {
                $params['property_code'] = $returnPropertyCode;
            }
            if ($returnStartDate !== '') {
                $params['start_date'] = $returnStartDate;
            }
            if ($returnViewMode !== '') {
                $params['view_mode'] = $returnViewMode;
            }
            if ($returnOrderMode !== '') {
                $params['order_mode'] = $returnOrderMode;
            }
            return 'index.php?' . http_build_query($params);
        }

        return 'index.php?view=reservations';
    }
}

if (!function_exists('reservations_extract_action_reservation_id')) {
    function reservations_extract_action_reservation_id()
    {
        $fieldCandidates = array(
            'reservation_id',
            'sale_reservation_id',
            'payment_reservation_id',
            'refund_reservation_id',
            'interest_reservation_id',
            'folio_reservation_id',
            'tax_reservation_id'
        );
        foreach ($fieldCandidates as $fieldName) {
            if (!isset($_POST[$fieldName])) {
                continue;
            }
            $candidateId = (int)$_POST[$fieldName];
            if ($candidateId > 0) {
                return $candidateId;
            }
        }
        return 0;
    }
}

if (!function_exists('reservations_fetch_sale_item_snapshot')) {
    function reservations_fetch_sale_item_snapshot($saleItemId)
    {
        $saleItemId = (int)$saleItemId;
        if ($saleItemId <= 0) {
            return null;
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT li.id_line_item, li.id_folio, li.id_line_item_catalog, li.item_type, li.service_date, f.id_reservation
                 FROM line_item li
                 LEFT JOIN folio f ON f.id_folio = li.id_folio
                 WHERE li.id_line_item = ?
                   AND li.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($saleItemId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('reservations_collect_sale_item_descendants')) {
    function reservations_collect_sale_item_descendants($rootSaleItemId)
    {
        $rootSaleItemId = (int)$rootSaleItemId;
        if ($rootSaleItemId <= 0) {
            return array();
        }

        $descendants = array();
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'WITH RECURSIVE li_tree AS (
                    SELECT h.id_line_item_child AS child_id
                    FROM line_item_hierarchy h
                    JOIN line_item c
                      ON c.id_line_item = h.id_line_item_child
                     AND c.deleted_at IS NULL
                     AND c.is_active = 1
                    WHERE h.deleted_at IS NULL
                      AND h.is_active = 1
                      AND h.id_line_item_parent = ?
                    UNION ALL
                    SELECT h2.id_line_item_child AS child_id
                    FROM line_item_hierarchy h2
                    JOIN line_item c2
                      ON c2.id_line_item = h2.id_line_item_child
                     AND c2.deleted_at IS NULL
                     AND c2.is_active = 1
                    JOIN li_tree t
                      ON t.child_id = h2.id_line_item_parent
                    WHERE h2.deleted_at IS NULL
                      AND h2.is_active = 1
                )
                SELECT DISTINCT child_id
                FROM li_tree
                WHERE child_id IS NOT NULL'
            );
            $stmt->execute(array($rootSaleItemId));
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $childId = isset($row['child_id']) ? (int)$row['child_id'] : 0;
                if ($childId > 0 && $childId !== $rootSaleItemId) {
                    $descendants[$childId] = $childId;
                }
            }
            return array_values($descendants);
        } catch (Exception $e) {
            $descendants = array();
        }

        try {
            $pdo = pms_get_connection();
            $pending = array($rootSaleItemId);
            $seen = array($rootSaleItemId => true);
            $loopGuard = 0;
            while ($pending && $loopGuard < 200) {
                $loopGuard++;
                $batch = array_splice($pending, 0, 50);
                $batch = array_values(array_filter(array_map('intval', $batch), function ($v) {
                    return $v > 0;
                }));
                if (!$batch) {
                    continue;
                }
                $placeholders = implode(',', array_fill(0, count($batch), '?'));
                $stmt = $pdo->prepare(
                    'SELECT DISTINCT c.id_line_item AS child_id
                     FROM line_item p
                     JOIN line_item c
                       ON c.id_folio = p.id_folio
                      AND c.id_line_item <> p.id_line_item
                      AND c.deleted_at IS NULL
                      AND c.is_active = 1
                      AND (c.service_date <=> p.service_date)
                     JOIN line_item_catalog_parent lcp
                       ON lcp.id_parent_sale_item_catalog = p.id_line_item_catalog
                      AND lcp.id_sale_item_catalog = c.id_line_item_catalog
                      AND lcp.deleted_at IS NULL
                      AND lcp.is_active = 1
                     LEFT JOIN line_item_catalog child_cat
                       ON child_cat.id_line_item_catalog = c.id_line_item_catalog
                     LEFT JOIN line_item_catalog parent_cat
                       ON parent_cat.id_line_item_catalog = p.id_line_item_catalog
                     WHERE p.id_line_item IN (' . $placeholders . ')
                       AND p.deleted_at IS NULL
                       AND p.is_active = 1
                       AND (
                         COALESCE(c.description, \'\') = CONCAT(COALESCE(child_cat.item_name, \'\'), \' / \', COALESCE(parent_cat.item_name, \'\'))
                         OR COALESCE(c.description, \'\') = CONCAT(\'[AUTO-DERIVED parent_line_item=\', p.id_line_item, \']\')
                       )'
                );
                $stmt->execute($batch);
                $rows = $stmt->fetchAll();
                foreach ($rows as $row) {
                    $childId = isset($row['child_id']) ? (int)$row['child_id'] : 0;
                    if ($childId <= 0 || isset($seen[$childId])) {
                        continue;
                    }
                    $seen[$childId] = true;
                    $descendants[$childId] = $childId;
                    $pending[] = $childId;
                }
            }
        } catch (Exception $e2) {
            return array_values($descendants);
        }

        return array_values($descendants);
    }
}

if (!function_exists('reservations_find_primary_lodging_line_item_id')) {
    function reservations_find_primary_lodging_line_item_id($companyId, $reservationId)
    {
        $companyId = (int)$companyId;
        $reservationId = (int)$reservationId;
        if ($companyId <= 0 || $reservationId <= 0) {
            return 0;
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT li.id_line_item
                 FROM line_item li
                 JOIN folio f
                   ON f.id_folio = li.id_folio
                  AND f.deleted_at IS NULL
                 JOIN reservation r
                   ON r.id_reservation = f.id_reservation
                  AND r.deleted_at IS NULL
                 JOIN property p
                   ON p.id_property = r.id_property
                  AND p.deleted_at IS NULL
                 JOIN pms_settings_lodging_catalog pslc
                   ON pslc.id_company = p.id_company
                  AND (pslc.id_property IS NULL OR pslc.id_property = p.id_property)
                  AND pslc.id_sale_item_catalog = li.id_line_item_catalog
                  AND pslc.deleted_at IS NULL
                  AND pslc.is_active = 1
                 WHERE p.id_company = ?
                   AND f.id_reservation = ?
                   AND li.item_type = \'sale_item\'
                   AND li.deleted_at IS NULL
                   AND COALESCE(li.is_active, 1) = 1
                   AND (li.status IS NULL OR li.status NOT IN (\'void\', \'canceled\', \'cancelled\'))
                 ORDER BY li.service_date ASC, li.created_at ASC, li.id_line_item ASC
                 LIMIT 1'
            );
            $stmt->execute(array($companyId, $reservationId));
            $id = (int)$stmt->fetchColumn();
            return $id > 0 ? $id : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('reservations_recalc_derived_tree_for_catalog')) {
    function reservations_recalc_derived_tree_for_catalog($folioId, $reservationId, $catalogId, $serviceDate, $actorUserId)
    {
        $folioId = (int)$folioId;
        $reservationId = (int)$reservationId;
        $catalogId = (int)$catalogId;
        if ($folioId <= 0 || $reservationId <= 0 || $catalogId <= 0) {
            return;
        }
        pms_call_procedure('sp_line_item_percent_derived_upsert', array(
            $folioId,
            $reservationId,
            $catalogId,
            $serviceDate,
            $actorUserId
        ));
        try {
            pms_call_procedure('sp_folio_recalc', array($folioId));
        } catch (Exception $e) {
        }
    }
}

if (!function_exists('reservations_room_label')) {
    function reservations_room_label(array $room)
    {
        $code = isset($room['code']) ? (string)$room['code'] : '';
        $name = isset($room['name']) ? (string)$room['name'] : '';
        if ($name === '') {
            return $code;
        }
        return $code . ' - ' . $name;
    }
}

if (!function_exists('reservations_rooms_for_property')) {
    function reservations_rooms_for_property(array $roomsByProperty, $propertyCode)
    {
        if ($propertyCode !== '' && isset($roomsByProperty[$propertyCode])) {
            return $roomsByProperty[$propertyCode];
        }
        return array();
    }
}

if (!function_exists('reservations_find_room')) {
    function reservations_find_room(array $roomsByProperty, $propertyCode, $roomCode)
    {
        if ($propertyCode === '' || $roomCode === '') {
            return null;
        }
        if (!isset($roomsByProperty[$propertyCode])) {
            return null;
        }
        foreach ($roomsByProperty[$propertyCode] as $room) {
            if (isset($room['code']) && (string)$room['code'] === $roomCode) {
                return $room;
            }
        }
        return null;
    }
}

if (!function_exists('reservations_line_item_is_active_for_summary')) {
    function reservations_line_item_is_active_for_summary(array $row, $onlySaleItemType)
    {
        $rowActive = isset($row['is_active']) ? (int)$row['is_active'] : 1;
        if ($rowActive !== 1) {
            return false;
        }
        $deletedAt = isset($row['deleted_at']) ? trim((string)$row['deleted_at']) : '';
        if ($deletedAt !== '') {
            return false;
        }
        $status = strtolower(trim(
            isset($row['status'])
                ? (string)$row['status']
                : (isset($row['sale_status']) ? (string)$row['sale_status'] : '')
        ));
        if (in_array($status, array('void', 'canceled', 'cancelled'), true)) {
            return false;
        }
        if ($onlySaleItemType) {
            $itemType = strtolower(trim(isset($row['item_type']) ? (string)$row['item_type'] : ''));
            if ($itemType !== 'sale_item') {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('reservations_line_item_is_payment_like')) {
    function reservations_line_item_is_payment_like(array $row)
    {
        $itemType = strtolower(trim(isset($row['item_type']) ? (string)$row['item_type'] : ''));
        if (in_array($itemType, array('payment', 'pago'), true)) {
            return true;
        }
        $catalogType = '';
        if (isset($row['catalog_type'])) {
            $catalogType = (string)$row['catalog_type'];
        } elseif (isset($row['line_item_catalog_type'])) {
            $catalogType = (string)$row['line_item_catalog_type'];
        }
        $catalogType = strtolower(trim($catalogType));
        if (in_array($catalogType, array('payment', 'pago'), true)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('reservations_format_date')) {
    function reservations_format_date($dateString, $format = 'Y-m-d')
    {
        if (!$dateString) {
            return '';
        }
        try {
            $dt = new DateTime($dateString);
            return $dt->format($format);
        } catch (Exception $e) {
            return $dateString;
        }
    }
}

if (!function_exists('reservations_format_day_month_es')) {
    function reservations_format_day_month_es($dateString)
    {
        if (!$dateString) {
            return '';
        }
        try {
            $dt = new DateTime($dateString);
            $months = array(
                1 => 'enero',
                2 => 'febrero',
                3 => 'marzo',
                4 => 'abril',
                5 => 'mayo',
                6 => 'junio',
                7 => 'julio',
                8 => 'agosto',
                9 => 'septiembre',
                10 => 'octubre',
                11 => 'noviembre',
                12 => 'diciembre'
            );
            $monthNumber = (int)$dt->format('n');
            $monthLabel = isset($months[$monthNumber]) ? $months[$monthNumber] : $dt->format('m');
            return $dt->format('j') . ' ' . $monthLabel;
        } catch (Exception $e) {
            return (string)$dateString;
        }
    }
}

if (!function_exists('calendar_format_date')) {
    function calendar_format_date($dateString, $format = 'Y-m-d')
    {
        return reservations_format_date($dateString, $format);
    }
}

if (!function_exists('reservations_status_label')) {
    function reservations_status_label($status)
    {
        $normalized = strtolower(trim((string)$status));
        if ($normalized === 'apartado') {
            return 'sin confirmar';
        }
        if (in_array($normalized, array('no-show', 'no show', 'noshow', 'no_show'), true)) {
            return 'No-show';
        }
        return (string)$status;
    }
}

if (!function_exists('reservations_status_normalize_for_filter')) {
    function reservations_status_normalize_for_filter($status)
    {
        $normalized = strtolower(trim((string)$status));
        if ($normalized === 'encasa') {
            $normalized = 'en casa';
        }
        if (in_array($normalized, array('no-show', 'no show', 'noshow', 'no_show'), true)) {
            return 'no-show';
        }
        if (in_array($normalized, array('cancelled', 'canceled', 'cancelado', 'cancelada'), true)) {
            return 'cancelada';
        }
        return $normalized;
    }
}

if (!function_exists('reservations_status_normalize_for_update')) {
    function reservations_status_normalize_for_update($status)
    {
        $normalized = strtolower(trim((string)$status));
        if ($normalized === 'encasa') {
            $normalized = 'en casa';
        }
        if (in_array($normalized, array('no show', 'noshow', 'no_show'), true)) {
            return 'no-show';
        }
        if (in_array($normalized, array('cancelled', 'canceled', 'cancelado', 'cancelada'), true)) {
            return 'cancelada';
        }
        return $normalized;
    }
}

if (!function_exists('reservations_status_requirements_snapshot')) {
    function reservations_status_requirements_snapshot($companyCode, $reservationId)
    {
        $companyCode = trim((string)$companyCode);
        $reservationId = (int)$reservationId;
        if ($companyCode === '' || $reservationId <= 0) {
            return null;
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT
                    CASE WHEN COALESCE(r.id_guest, 0) > 0 THEN 1 ELSE 0 END AS has_guest,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM folio f
                        JOIN line_item li
                          ON li.id_folio = f.id_folio
                         AND li.deleted_at IS NULL
                         AND COALESCE(li.is_active, 1) = 1
                         AND li.item_type = \'sale_item\'
                         AND (
                           li.status IS NULL
                           OR LOWER(TRIM(li.status)) NOT IN (\'void\', \'canceled\', \'cancelled\')
                         )
                        WHERE f.id_reservation = r.id_reservation
                          AND f.deleted_at IS NULL
                          AND (
                            LOWER(TRIM(COALESCE(f.folio_name, \'\'))) NOT LIKE \'%servicio%\'
                            AND LOWER(TRIM(COALESCE(f.folio_name, \'\'))) NOT LIKE \'%service%\'
                          )
                          AND (
                            f.status IS NULL
                            OR LOWER(TRIM(f.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                          )
                    ) THEN 1 ELSE 0 END AS has_sale_item_charges
                 FROM reservation r
                 JOIN property p ON p.id_property = r.id_property
                 JOIN company c ON c.id_company = p.id_company
                 WHERE r.id_reservation = ?
                   AND c.code = ?
                   AND r.deleted_at IS NULL
                   AND p.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($reservationId, $companyCode));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return array(
                'has_guest' => isset($row['has_guest']) ? ((int)$row['has_guest'] === 1) : false,
                'has_charges' => isset($row['has_sale_item_charges']) ? ((int)$row['has_sale_item_charges'] === 1) : false
            );
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('reservations_catalog_default_cents')) {
    function reservations_catalog_default_cents(array $row)
    {
        $defaultAmount = isset($row['default_amount_cents']) ? (int)$row['default_amount_cents'] : 0;
        $defaultUnit = isset($row['default_unit_price_cents']) ? (int)$row['default_unit_price_cents'] : 0;
        return $defaultAmount > 0 ? $defaultAmount : $defaultUnit;
    }
}

if (!function_exists('reservations_fetch_fixed_children_by_parent')) {
    function reservations_fetch_fixed_children_by_parent($companyCode, $propertyCode, $companyId = 0)
    {
        static $cache = array();
        $cacheKey = strtoupper(trim((string)$companyCode)) . '|' . strtoupper(trim((string)$propertyCode));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $map = array();
        try {
            $pdo = pms_get_connection();
            $companyId = (int)$companyId;
            if ($companyId <= 0) {
                $stmtCompany = $pdo->prepare('SELECT id_company FROM company WHERE UPPER(code) = UPPER(?) LIMIT 1');
                $stmtCompany->execute(array($companyCode));
                $companyId = (int)$stmtCompany->fetchColumn();
            }
            if ($companyId <= 0) {
                $cache[$cacheKey] = $map;
                return $map;
            }

            $stmt = $pdo->prepare(
                'SELECT
                    lcp.id_parent_sale_item_catalog AS parent_catalog_id,
                    child.id_line_item_catalog AS id_sale_item_catalog,
                    child.item_name,
                    child.default_unit_price_cents,
                    child.default_amount_cents
                 FROM line_item_catalog_parent lcp
                 JOIN line_item_catalog child
                   ON child.id_line_item_catalog = lcp.id_sale_item_catalog
                  AND child.deleted_at IS NULL
                  AND child.is_active = 1
                  AND child.catalog_type IN (\'sale_item\',\'tax_rule\',\'payment\',\'obligation\',\'income\')
                 WHERE lcp.deleted_at IS NULL
                   AND lcp.is_active = 1
                   AND (lcp.percent_value IS NULL OR lcp.percent_value = 0)
                 ORDER BY lcp.id_parent_sale_item_catalog, child.item_name'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $parentId = isset($row['parent_catalog_id']) ? (int)$row['parent_catalog_id'] : 0;
                $childId = isset($row['id_sale_item_catalog']) ? (int)$row['id_sale_item_catalog'] : 0;
                if ($parentId <= 0 || $childId <= 0) {
                    continue;
                }
                if (!isset($map[$parentId])) {
                    $map[$parentId] = array();
                }
                if (isset($map[$parentId][$childId])) {
                    continue;
                }
                $map[$parentId][$childId] = array(
                    'id' => $childId,
                    'name' => isset($row['item_name']) ? (string)$row['item_name'] : '',
                    'default_cents' => reservations_catalog_default_cents($row)
                );
            }
            foreach ($map as $parentId => $children) {
                $map[$parentId] = array_values($children);
            }
        } catch (Exception $e) {
            $map = array();
        }

        $cache[$cacheKey] = $map;
        return $map;
    }
}

if (!function_exists('reservations_upsert_fixed_children_tree')) {
    function reservations_upsert_fixed_children_tree(
        $reservationId,
        $folioId,
        $serviceDate,
        $actorUserId,
        $parentCatalogId,
        array $fixedChildrenByParentMap,
        array &$path = array(),
        $depth = 0
    ) {
        $reservationId = (int)$reservationId;
        $folioId = (int)$folioId;
        $parentCatalogId = (int)$parentCatalogId;
        $depth = (int)$depth;
        if ($reservationId <= 0 || $folioId <= 0 || $parentCatalogId <= 0 || $depth > 20) {
            return;
        }
        if (isset($path[$parentCatalogId])) {
            return;
        }
        $path[$parentCatalogId] = true;

        static $catalogNameCache = array();
        $resolveCatalogName = function ($catalogId, $fallback = '') use (&$catalogNameCache) {
            $catalogId = (int)$catalogId;
            if ($catalogId <= 0) {
                return (string)$fallback;
            }
            if (isset($catalogNameCache[$catalogId])) {
                return $catalogNameCache[$catalogId];
            }
            $name = '';
            try {
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare('SELECT item_name FROM line_item_catalog WHERE id_line_item_catalog = ? LIMIT 1');
                $stmt->execute(array($catalogId));
                $name = (string)$stmt->fetchColumn();
            } catch (Exception $e) {
                $name = '';
            }
            $name = trim($name) !== '' ? trim($name) : (string)$fallback;
            $catalogNameCache[$catalogId] = $name;
            return $name;
        };
        static $catalogDefaultCentsCache = array();
        $resolveCatalogDefaultCents = function ($catalogId) use (&$catalogDefaultCentsCache) {
            $catalogId = (int)$catalogId;
            if ($catalogId <= 0) {
                return 0;
            }
            if (isset($catalogDefaultCentsCache[$catalogId])) {
                return $catalogDefaultCentsCache[$catalogId];
            }
            $defaultCents = 0;
            try {
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare(
                    'SELECT COALESCE(default_unit_price_cents, 0) AS default_unit_price_cents,
                            COALESCE(default_amount_cents, 0) AS default_amount_cents
                       FROM line_item_catalog
                      WHERE id_line_item_catalog = ?
                      LIMIT 1'
                );
                $stmt->execute(array($catalogId));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $defaultCents = reservations_catalog_default_cents($row);
                }
            } catch (Exception $e) {
                $defaultCents = 0;
            }
            $catalogDefaultCentsCache[$catalogId] = (int)$defaultCents;
            return $catalogDefaultCentsCache[$catalogId];
        };

        $children = isset($fixedChildrenByParentMap[$parentCatalogId]) && is_array($fixedChildrenByParentMap[$parentCatalogId])
            ? $fixedChildrenByParentMap[$parentCatalogId]
            : array();
        foreach ($children as $childMeta) {
            if (!is_array($childMeta)) {
                continue;
            }
            $childId = isset($childMeta['id']) ? (int)$childMeta['id'] : 0;
            if ($childId <= 0) {
                continue;
            }
            $childAmountCents = isset($childMeta['default_cents']) ? (int)$childMeta['default_cents'] : 0;
            if ($childAmountCents <= 0) {
                $childAmountCents = $resolveCatalogDefaultCents($childId);
            }
            if ($childAmountCents > 0) {
                $parentName = $resolveCatalogName($parentCatalogId, '');
                $childName = isset($childMeta['name']) ? trim((string)$childMeta['name']) : '';
                if ($childName === '') {
                    $childName = $resolveCatalogName($childId, '');
                }
                $derivedDesc = trim($childName) !== '' && trim($parentName) !== ''
                    ? ($childName . ' / ' . $parentName)
                    : null;

                $existingId = 0;
                try {
                    $pdo = pms_get_connection();
                    if ($derivedDesc !== null) {
                        $stmtExisting = $pdo->prepare(
                            'SELECT id_line_item
                               FROM line_item
                              WHERE id_folio = ?
                                AND id_line_item_catalog = ?
                                AND (service_date <=> ?)
                                AND deleted_at IS NULL
                                AND is_active = 1
                                AND COALESCE(description, \'\') = COALESCE(?, \'\')
                              ORDER BY id_line_item DESC
                              LIMIT 1'
                        );
                        $stmtExisting->execute(array($folioId, $childId, $serviceDate, $derivedDesc));
                    } else {
                        $stmtExisting = $pdo->prepare(
                            'SELECT id_line_item
                               FROM line_item
                              WHERE id_folio = ?
                                AND id_line_item_catalog = ?
                                AND (service_date <=> ?)
                                AND deleted_at IS NULL
                                AND is_active = 1
                              ORDER BY id_line_item DESC
                              LIMIT 1'
                        );
                        $stmtExisting->execute(array($folioId, $childId, $serviceDate));
                    }
                    $existingId = (int)$stmtExisting->fetchColumn();
                } catch (Exception $e) {
                    $existingId = 0;
                }

                pms_call_procedure('sp_sale_item_upsert', array(
                    $existingId > 0 ? 'update' : 'create',
                    $existingId,
                    $folioId,
                    $reservationId,
                    $childId,
                    $derivedDesc,
                    $serviceDate,
                    1,
                    $childAmountCents,
                    null,
                    'posted',
                    $actorUserId
                ));
            }

            reservations_upsert_fixed_children_tree(
                $reservationId,
                $folioId,
                $serviceDate,
                $actorUserId,
                $childId,
                $fixedChildrenByParentMap,
                $path,
                $depth + 1
            );

            try {
                pms_call_procedure('sp_line_item_percent_derived_upsert', array(
                    $folioId,
                    $reservationId,
                    $childId,
                    $serviceDate,
                    $actorUserId
                ));
            } catch (Exception $ignoreChildRecalc) {
            }
        }

        unset($path[$parentCatalogId]);
    }
}

if (!function_exists('reservations_fetch_percent_children_by_parent')) {
    function reservations_fetch_percent_children_by_parent($parentCatalogId)
    {
        static $cache = array();
        $parentCatalogId = (int)$parentCatalogId;
        if ($parentCatalogId <= 0) {
            return array();
        }
        if (isset($cache[$parentCatalogId])) {
            return $cache[$parentCatalogId];
        }
        $childIds = array();
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT DISTINCT lcp.id_sale_item_catalog
                   FROM line_item_catalog_parent lcp
                  WHERE lcp.deleted_at IS NULL
                    AND lcp.is_active = 1
                    AND lcp.id_parent_sale_item_catalog = ?
                    AND lcp.percent_value IS NOT NULL
                    AND lcp.percent_value <> 0'
            );
            $stmt->execute(array($parentCatalogId));
            foreach ((array)$stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $childId = isset($row['id_sale_item_catalog']) ? (int)$row['id_sale_item_catalog'] : 0;
                if ($childId > 0) {
                    $childIds[$childId] = true;
                }
            }
        } catch (Exception $e) {
            $childIds = array();
        }
        $cache[$parentCatalogId] = array_keys($childIds);
        return $cache[$parentCatalogId];
    }
}

if (!function_exists('reservations_apply_fixed_children_for_percent_descendants')) {
    function reservations_apply_fixed_children_for_percent_descendants(
        $reservationId,
        $folioId,
        $serviceDate,
        $actorUserId,
        $parentCatalogId,
        array $fixedChildrenByParentMap,
        array &$path = array(),
        $depth = 0
    ) {
        $reservationId = (int)$reservationId;
        $folioId = (int)$folioId;
        $parentCatalogId = (int)$parentCatalogId;
        $depth = (int)$depth;
        if ($reservationId <= 0 || $folioId <= 0 || $parentCatalogId <= 0 || $depth > 20) {
            return;
        }
        if (isset($path[$parentCatalogId])) {
            return;
        }
        $path[$parentCatalogId] = true;

        $percentChildren = reservations_fetch_percent_children_by_parent($parentCatalogId);
        foreach ($percentChildren as $percentChildCatalogId) {
            $percentChildCatalogId = (int)$percentChildCatalogId;
            if ($percentChildCatalogId <= 0) {
                continue;
            }

            if (!empty($fixedChildrenByParentMap)) {
                $fixedPath = array();
                reservations_upsert_fixed_children_tree(
                    $reservationId,
                    $folioId,
                    $serviceDate,
                    $actorUserId,
                    $percentChildCatalogId,
                    $fixedChildrenByParentMap,
                    $fixedPath,
                    0
                );
            }

            reservations_apply_fixed_children_for_percent_descendants(
                $reservationId,
                $folioId,
                $serviceDate,
                $actorUserId,
                $percentChildCatalogId,
                $fixedChildrenByParentMap,
                $path,
                $depth + 1
            );
        }

        unset($path[$parentCatalogId]);
    }
}

if (!function_exists('reservations_force_update_status')) {
    function reservations_force_update_status($companyCode, $reservationId, $status)
    {
        $companyCode = trim((string)$companyCode);
        $reservationId = (int)$reservationId;
        $status = reservations_status_normalize_for_update($status);
        if ($companyCode === '' || $reservationId <= 0 || $status === '') {
            return false;
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE reservation r
                 JOIN property p ON p.id_property = r.id_property
                 JOIN company c ON c.id_company = p.id_company
                 SET r.status = ?, r.updated_at = NOW()
                 WHERE r.id_reservation = ?
                   AND c.code = ?
                   AND r.deleted_at IS NULL
                   AND p.deleted_at IS NULL'
            );
            $stmt->execute(array($status, $reservationId, $companyCode));
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('reservations_status_filter_options')) {
    function reservations_status_filter_options()
    {
        return array(
            'apartado' => 'Apartado',
            'confirmado' => 'Confirmado',
            'en casa' => 'En casa',
            'salida' => 'Salida',
            'no-show' => 'No-show',
            'cancelada' => 'Cancelada'
        );
    }
}

if (!function_exists('reservations_status_filter_default_values')) {
    function reservations_status_filter_default_values()
    {
        $options = reservations_status_filter_options();
        $values = array_keys($options);
        return array_values(array_filter($values, function ($value) {
            return $value !== 'cancelada';
        }));
    }
}

if (!function_exists('reservations_parse_status_filter_values')) {
    function reservations_parse_status_filter_values($rawValue)
    {
        $items = array();
        if (is_array($rawValue)) {
            $items = $rawValue;
        } else {
            $raw = trim((string)$rawValue);
            if ($raw !== '') {
                $items = explode(',', $raw);
            }
        }

        $options = reservations_status_filter_options();
        $allowed = array_fill_keys(array_keys($options), true);
        $selected = array();
        foreach ($items as $item) {
            $normalized = reservations_status_normalize_for_filter($item);
            if ($normalized === '' || !isset($allowed[$normalized])) {
                continue;
            }
            $selected[$normalized] = true;
        }
        return array_keys($selected);
    }
}

if (!function_exists('reservations_status_filter_summary')) {
    function reservations_status_filter_summary(array $selectedValues, array $options)
    {
        $count = count($selectedValues);
        if ($count <= 0) {
            return 'Seleccionar estatus';
        }
        if ($count === count($options)) {
            return 'Todos los estatus';
        }

        $labels = array();
        foreach ($selectedValues as $value) {
            if (isset($options[$value])) {
                $labels[] = $options[$value];
            }
        }
        if (!$labels) {
            return 'Seleccionar estatus';
        }
        if (count($labels) > 2) {
            return implode(', ', array_slice($labels, 0, 2)) . ' +' . (count($labels) - 2);
        }
        return implode(', ', $labels);
    }
}

if (!function_exists('reservations_format_money')) {
    function reservations_format_money($cents, $currency = 'MXN')
    {
        $amount = number_format(((float)$cents) / 100, 2, '.', ',');
        return ($currency === 'MXN' ? '$' : '') . $amount . ($currency ? ' ' . $currency : '');
    }
}

if (!function_exists('reservations_to_cents')) {
    function reservations_to_cents($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $normalized = str_replace(',', '.', (string)$value);
        return (int)round((float)$normalized * 100);
    }
}
if (!function_exists('reservations_parse_ids')) {
    function reservations_parse_ids($raw)
    {
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
}

if (!function_exists('reservations_rate_column')) {
    function reservations_rate_column(PDO $pdo)
    {
        return 'percent_value';
    }
}

if (!function_exists('reservations_catalog_data_fallback')) {
    function reservations_catalog_data_fallback($companyId, $propertyCode = null, $showInactive = 0, $itemId = 0, $categoryId = 0)
    {
        $rows = array();
        try {
            $pdo = pms_get_connection();
            $rateCol = reservations_rate_column($pdo);
            $sql = 'SELECT
                        lic.id_line_item_catalog AS id_sale_item_catalog,
                        lic.catalog_type,
                        lic.id_category,
                        cat.category_name AS category,
                        parent_map.parent_first_id AS id_parent_sale_item_catalog,
                        parent_first.item_name AS parent_item_name,
                        parent_map.parent_item_ids,
                        parent_map.add_to_father_total,
                        lic.is_percent,
                        lic.' . $rateCol . ' AS percent_value,
                        lic.show_in_folio,
                        lic.item_name,
                        lic.description,
                        lic.default_unit_price_cents,
                        lic.default_amount_cents,
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
                        MIN(lcp.add_to_father_total) AS add_to_father_total
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
        } catch (Exception $e) {
            $rows = array();
        }
        return $rows;
    }
}

if (!function_exists('reservations_lodging_catalog_ids_for_property')) {
    function reservations_lodging_catalog_ids_for_property($companyCode, $propertyCode)
    {
        $companyCode = trim((string)$companyCode);
        $propertyCode = strtoupper(trim((string)$propertyCode));
        if ($companyCode === '') {
            return array();
        }
        $allowedIds = array();
        try {
            $settingSets = pms_call_procedure('sp_pms_settings_data', array(
                $companyCode,
                $propertyCode !== '' ? $propertyCode : null
            ));
            $settingRow = isset($settingSets[0][0]) ? $settingSets[0][0] : null;
            if ($settingRow && isset($settingRow['lodging_catalog_ids']) && trim((string)$settingRow['lodging_catalog_ids']) !== '') {
                $allowedIds = array_filter(array_map('intval', explode(',', (string)$settingRow['lodging_catalog_ids'])));
            }
        } catch (Exception $e) {
            $allowedIds = array();
        }
        if (!$allowedIds) {
            try {
                $fallbackSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, null));
                $fallbackRow = isset($fallbackSets[0][0]) ? $fallbackSets[0][0] : null;
                if ($fallbackRow && isset($fallbackRow['lodging_catalog_ids']) && trim((string)$fallbackRow['lodging_catalog_ids']) !== '') {
                    $allowedIds = array_filter(array_map('intval', explode(',', (string)$fallbackRow['lodging_catalog_ids'])));
                }
            } catch (Exception $e) {
                $allowedIds = array();
            }
        }
        $out = array();
        foreach ($allowedIds as $allowedId) {
            $id = (int)$allowedId;
            if ($id > 0) {
                $out[$id] = true;
            }
        }
        return array_keys($out);
    }
}

if (!function_exists('reservations_lodging_concept_options_for_property')) {
    function reservations_lodging_concept_options_for_property($companyCode, $companyId, $propertyCode)
    {
        $companyCode = trim((string)$companyCode);
        $companyId = (int)$companyId;
        $propertyCode = strtoupper(trim((string)$propertyCode));
        if ($companyCode === '' || $companyId <= 0 || $propertyCode === '') {
            return array();
        }
        $allowedIds = reservations_lodging_catalog_ids_for_property($companyCode, $propertyCode);
        if (!$allowedIds) {
            return array();
        }
        $allowedMap = array();
        foreach ($allowedIds as $allowedId) {
            $allowedMap[(int)$allowedId] = true;
        }
        try {
            $conceptSets = pms_call_procedure('sp_sale_item_catalog_data', array(
                $companyCode,
                $propertyCode,
                0,
                0,
                0
            ));
            $rows = isset($conceptSets[0]) ? $conceptSets[0] : array();
            if (!$rows) {
                $rows = reservations_catalog_data_fallback($companyId, $propertyCode, 0, 0, 0);
            }
        } catch (Exception $e) {
            $rows = reservations_catalog_data_fallback($companyId, $propertyCode, 0, 0, 0);
        }
        $options = array();
        foreach ((array)$rows as $row) {
            $catalogId = isset($row['id_sale_item_catalog']) ? (int)$row['id_sale_item_catalog'] : 0;
            if ($catalogId <= 0 || !isset($allowedMap[$catalogId])) {
                continue;
            }
            $category = isset($row['category']) ? trim((string)$row['category']) : '';
            $name = isset($row['item_name']) ? trim((string)$row['item_name']) : '';
            if ($name === '') {
                $name = 'Catalogo #' . $catalogId;
            }
            $defaultAmountCents = isset($row['default_amount_cents']) ? (int)$row['default_amount_cents'] : 0;
            $defaultUnitCents = isset($row['default_unit_price_cents']) ? (int)$row['default_unit_price_cents'] : 0;
            $options[$catalogId] = array(
                'id' => $catalogId,
                'label' => $category !== '' ? ($category . ' / ' . $name) : $name,
                'default_unit_price_cents' => $defaultAmountCents > 0 ? $defaultAmountCents : $defaultUnitCents
            );
        }
        uasort($options, function ($a, $b) {
            $aLabel = isset($a['label']) ? (string)$a['label'] : '';
            $bLabel = isset($b['label']) ? (string)$b['label'] : '';
            return strcasecmp($aLabel, $bLabel);
        });
        return $options;
    }
}

if (!function_exists('reservations_sync_reservation_origin_from_lodging')) {
    function reservations_sync_reservation_origin_from_lodging($companyCode, $companyId, $reservationId, $lodgingCatalogId, $actorUserId)
    {
        $companyCode = trim((string)$companyCode);
        $companyId = (int)$companyId;
        $reservationId = (int)$reservationId;
        $lodgingCatalogId = (int)$lodgingCatalogId;
        if ($companyCode === '' || $companyId <= 0 || $reservationId <= 0 || $lodgingCatalogId <= 0) {
            return;
        }

        $otaId = 0;
        $sourceId = 0;
        $sourceName = 'Directo';
        $sourceInput = 'Directo';
        $resolvedOrigin = false;
        try {
            $pdo = pms_get_connection();
            $reservationPropertyId = 0;

            $reservationStmt = $pdo->prepare(
                'SELECT r.id_property
                   FROM reservation r
                   JOIN property p
                     ON p.id_property = r.id_property
                    AND p.deleted_at IS NULL
                  WHERE r.id_reservation = ?
                    AND r.deleted_at IS NULL
                    AND p.id_company = ?
                  LIMIT 1'
            );
            $reservationStmt->execute(array($reservationId, $companyId));
            $reservationRow = $reservationStmt->fetch(PDO::FETCH_ASSOC);
            if ($reservationRow && isset($reservationRow['id_property'])) {
                $reservationPropertyId = (int)$reservationRow['id_property'];
            }
            if ($reservationPropertyId <= 0) {
                return;
            }

            if (function_exists('pms_reservation_source_has_column')
                && pms_reservation_source_has_column($pdo, 'id_lodging_catalog')) {
                $sourceStmt = $pdo->prepare(
                    'SELECT rsc.id_reservation_source,
                            COALESCE(NULLIF(TRIM(rsc.source_name), \'\'), \'\') AS source_name
                       FROM reservation_source_catalog rsc
                      WHERE rsc.id_company = ?
                        AND rsc.deleted_at IS NULL
                        AND rsc.is_active = 1
                        AND COALESCE(rsc.id_lodging_catalog, 0) = ?
                        AND (rsc.id_property = ? OR rsc.id_property IS NULL)
                      ORDER BY CASE WHEN rsc.id_property = ? THEN 0 ELSE 1 END,
                               rsc.id_reservation_source
                      LIMIT 1'
                );
                $sourceStmt->execute(array($companyId, $lodgingCatalogId, $reservationPropertyId, $reservationPropertyId));
                $sourceRow = $sourceStmt->fetch(PDO::FETCH_ASSOC);
                if ($sourceRow) {
                    $tmpSourceId = isset($sourceRow['id_reservation_source']) ? (int)$sourceRow['id_reservation_source'] : 0;
                    if ($tmpSourceId > 0) {
                        $sourceId = $tmpSourceId;
                        $tmpSourceName = trim((string)(isset($sourceRow['source_name']) ? $sourceRow['source_name'] : ''));
                        if ($tmpSourceName !== '') {
                            $sourceName = $tmpSourceName;
                        }
                    }
                }
            }

            $otaPlatform = '';
            $otaStmt = $pdo->prepare(
                'SELECT oa.id_ota_account,
                        oa.platform
                   FROM ota_account_lodging_catalog oalc
                   JOIN ota_account oa
                     ON oa.id_ota_account = oalc.id_ota_account
                    AND oa.id_company = ?
                    AND oa.deleted_at IS NULL
                    AND oa.is_active = 1
                    AND (oa.id_property = ? OR oa.id_property IS NULL)
                  WHERE oalc.id_line_item_catalog = ?
                    AND oalc.deleted_at IS NULL
                    AND oalc.is_active = 1
                  ORDER BY CASE WHEN oa.id_property = ? THEN 0 ELSE 1 END,
                           oalc.sort_order,
                           oa.id_ota_account
                  LIMIT 1'
            );
            $otaStmt->execute(array($companyId, $reservationPropertyId, $lodgingCatalogId, $reservationPropertyId));
            $otaRow = $otaStmt->fetch(PDO::FETCH_ASSOC);
            if ($otaRow) {
                $otaId = isset($otaRow['id_ota_account']) ? (int)$otaRow['id_ota_account'] : 0;
                $otaPlatform = strtolower(trim((string)(isset($otaRow['platform']) ? $otaRow['platform'] : '')));
            }

            if ($otaId > 0) {
                if (function_exists('pms_reservation_source_from_ota_platform')) {
                    $otaSource = (string)pms_reservation_source_from_ota_platform($otaPlatform);
                } else {
                    $otaSource = $otaPlatform === 'booking'
                        ? 'booking'
                        : (($otaPlatform === 'airbnb' || $otaPlatform === 'abb')
                            ? 'airbnb'
                            : ($otaPlatform === 'expedia' ? 'expedia' : 'otro'));
                }
                if ($otaSource !== '') {
                    $sourceInput = $otaSource;
                }
                if ($otaPlatform === 'booking') {
                    $sourceName = 'Booking';
                } elseif ($otaPlatform === 'airbnb' || $otaPlatform === 'abb') {
                    $sourceName = 'AirB&B';
                } elseif ($otaPlatform === 'expedia') {
                    $sourceName = 'Expedia';
                } else {
                    $sourceName = 'OTA';
                }
                $sourceId = 0;
                $resolvedOrigin = true;
            } elseif ($sourceId > 0) {
                $otaId = 0;
                $sourceInput = (string)$sourceId;
                $resolvedOrigin = true;
            }
        } catch (Exception $e) {
            $otaId = 0;
            $sourceId = 0;
            $sourceInput = 'Directo';
            $sourceName = 'Directo';
            $resolvedOrigin = false;
        }

        if (!$resolvedOrigin) {
            return;
        }

        pms_call_procedure('sp_reservation_update', array(
            $companyCode,
            $reservationId,
            null,
            $sourceInput,
            $otaId > 0 ? $otaId : 0,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $actorUserId
        ));

        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE reservation r
                   JOIN property p
                     ON p.id_property = r.id_property
                    AND p.deleted_at IS NULL
                   SET r.id_ota_account = ?,
                       r.id_reservation_source = ?,
                       r.source = ?,
                       r.updated_at = NOW()
                 WHERE r.id_reservation = ?
                   AND r.deleted_at IS NULL
                   AND p.id_company = ?'
            );
            $stmt->execute(array(
                $otaId > 0 ? $otaId : null,
                $sourceId > 0 ? $sourceId : null,
                $sourceName !== '' ? $sourceName : 'Directo',
                $reservationId,
                $companyId
            ));
        } catch (Exception $ignoreForceSync) {
        }
    }
}

$taxRuleRates = array();


$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d', strtotime('+30 days'));

$returnViewRaw = isset($_POST['reservations_filter_return_view'])
    ? (string)$_POST['reservations_filter_return_view']
    : (isset($_GET['reservations_filter_return_view']) ? (string)$_GET['reservations_filter_return_view'] : '');
$returnView = strtolower(trim($returnViewRaw));
if ($returnView !== 'calendar') {
    $returnView = '';
}

$returnPropertyCode = isset($_POST['reservations_filter_return_property_code'])
    ? strtoupper(trim((string)$_POST['reservations_filter_return_property_code']))
    : (isset($_GET['reservations_filter_return_property_code']) ? strtoupper(trim((string)$_GET['reservations_filter_return_property_code'])) : '');

$returnStartDate = isset($_POST['reservations_filter_return_start_date'])
    ? trim((string)$_POST['reservations_filter_return_start_date'])
    : (isset($_GET['reservations_filter_return_start_date']) ? trim((string)$_GET['reservations_filter_return_start_date']) : '');
if ($returnStartDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnStartDate)) {
    $returnStartDate = '';
}

$returnViewMode = isset($_POST['reservations_filter_return_view_mode'])
    ? trim((string)$_POST['reservations_filter_return_view_mode'])
    : (isset($_GET['reservations_filter_return_view_mode']) ? trim((string)$_GET['reservations_filter_return_view_mode']) : '');
if ($returnViewMode !== '' && !preg_match('/^[a-z0-9_-]{1,32}$/i', $returnViewMode)) {
    $returnViewMode = '';
}

$returnOrderMode = isset($_POST['reservations_filter_return_order_mode'])
    ? trim((string)$_POST['reservations_filter_return_order_mode'])
    : (isset($_GET['reservations_filter_return_order_mode']) ? trim((string)$_GET['reservations_filter_return_order_mode']) : '');
if ($returnOrderMode !== '' && !preg_match('/^[a-z0-9_-]{1,32}$/i', $returnOrderMode)) {
    $returnOrderMode = '';
}

$statusFilterOptions = reservations_status_filter_options();
$rawStatusFilterInput = null;
if (array_key_exists('reservations_filter_status', $_POST)) {
    $rawStatusFilterInput = $_POST['reservations_filter_status'];
} elseif (array_key_exists('reservations_filter_status', $_GET)) {
    $rawStatusFilterInput = $_GET['reservations_filter_status'];
}
$selectedStatusFilters = reservations_parse_status_filter_values($rawStatusFilterInput);
if (!$selectedStatusFilters) {
    $selectedStatusFilters = reservations_status_filter_default_values();
}
$selectedStatusFilterSet = array_fill_keys($selectedStatusFilters, true);
$statusFilterSummary = reservations_status_filter_summary($selectedStatusFilters, $statusFilterOptions);

$filters = array(
    'property_code' => isset($_POST['reservations_filter_property'])
        ? strtoupper((string)$_POST['reservations_filter_property'])
        : (isset($_GET['reservations_filter_property']) ? strtoupper((string)$_GET['reservations_filter_property']) : ''),
    'status'        => implode(',', $selectedStatusFilters),
    'from'          => isset($_POST['reservations_filter_from']) && $_POST['reservations_filter_from'] !== ''
        ? (string)$_POST['reservations_filter_from']
        : (isset($_GET['reservations_filter_from']) && $_GET['reservations_filter_from'] !== '' ? (string)$_GET['reservations_filter_from'] : $defaultFrom),
    'to'            => isset($_POST['reservations_filter_to']) && $_POST['reservations_filter_to'] !== ''
        ? (string)$_POST['reservations_filter_to']
        : (isset($_GET['reservations_filter_to']) && $_GET['reservations_filter_to'] !== '' ? (string)$_GET['reservations_filter_to'] : $defaultTo),
    'return_view' => $returnView,
    'return_property_code' => $returnPropertyCode,
    'return_start_date' => $returnStartDate,
    'return_view_mode' => $returnViewMode,
    'return_order_mode' => $returnOrderMode,
);
if ($filters['property_code'] !== '') {
    pms_require_property_access($filters['property_code']);
}
if ($filters['return_property_code'] !== '') {
    pms_require_property_access($filters['return_property_code']);
}

$newReservationMessage = null;
$newReservationError = null;
$updateMessages = array();
$updateErrors = array();
$financeMessages = array();
$financeErrors = array();
$interestMessages = array();
$interestErrors = array();
$notesOverride = array();
$globalError = null;
$clearDirtyTargets = array();
$redirectAfterSaveUrl = '';

$action = isset($_POST['reservations_action']) ? (string)$_POST['reservations_action'] : '';
if ($action !== '') {
    if ($action === 'create_reservation') {
        pms_require_permission('reservations.create');
    } elseif (in_array($action, array('confirm_reservation'), true)) {
        pms_require_permission('reservations.status_change');
    } elseif (in_array($action, array('update_reservation', 'add_interest', 'remove_interest'), true)) {
        pms_require_permission('reservations.edit');
    } elseif (in_array($action, array('add_note', 'delete_note'), true)) {
        pms_require_permission('reservations.note_edit');
    } elseif (in_array($action, array('create_folio', 'close_folio', 'reopen_folio', 'update_folio', 'delete_folio', 'remove_visible_folio_taxes'), true)) {
        pms_require_permission('reservations.manage_folio');
    } elseif (in_array($action, array('create_sale_item', 'update_sale_item', 'delete_sale_item'), true)) {
        pms_require_permission('reservations.post_charge');
    } elseif (in_array($action, array('create_payment', 'update_payment', 'delete_payment'), true)) {
        pms_require_permission('reservations.post_payment');
    } elseif (in_array($action, array('create_refund', 'delete_refund'), true)) {
        pms_require_permission('reservations.refund');
    }
}

$createExtraLodgingInput = isset($_POST['create_extra_lodging_catalog_id'])
    ? $_POST['create_extra_lodging_catalog_id']
    : array();
$createExtraLodgingAmountInput = isset($_POST['create_extra_lodging_amount'])
    ? $_POST['create_extra_lodging_amount']
    : array();
if (!is_array($createExtraLodgingInput)) {
    $createExtraLodgingInput = array($createExtraLodgingInput);
}
if (!is_array($createExtraLodgingAmountInput)) {
    $createExtraLodgingAmountInput = array($createExtraLodgingAmountInput);
}
$createExtraLodgingRows = array();
$extraRowsCount = max(count($createExtraLodgingInput), count($createExtraLodgingAmountInput));
for ($i = 0; $i < $extraRowsCount; $i++) {
    $extraId = isset($createExtraLodgingInput[$i]) ? (int)$createExtraLodgingInput[$i] : 0;
    $extraAmount = isset($createExtraLodgingAmountInput[$i]) ? trim((string)$createExtraLodgingAmountInput[$i]) : '';
    if ($extraId <= 0 && $extraAmount === '') {
        continue;
    }
    $createExtraLodgingRows[] = array(
        'id' => $extraId,
        'amount' => $extraAmount
    );
}

$createPaymentMethodsInput = isset($_POST['create_payment_method']) ? $_POST['create_payment_method'] : array();
$createPaymentAmountsInput = isset($_POST['create_payment_amount']) ? $_POST['create_payment_amount'] : array();
$createPaymentReferencesInput = isset($_POST['create_payment_reference']) ? $_POST['create_payment_reference'] : array();
$createPaymentDatesInput = isset($_POST['create_payment_date']) ? $_POST['create_payment_date'] : array();
if (!is_array($createPaymentMethodsInput)) {
    $createPaymentMethodsInput = array($createPaymentMethodsInput);
}
if (!is_array($createPaymentAmountsInput)) {
    $createPaymentAmountsInput = array($createPaymentAmountsInput);
}
if (!is_array($createPaymentReferencesInput)) {
    $createPaymentReferencesInput = array($createPaymentReferencesInput);
}
if (!is_array($createPaymentDatesInput)) {
    $createPaymentDatesInput = array($createPaymentDatesInput);
}
$createPaymentRows = array();
$maxPaymentRows = max(count($createPaymentMethodsInput), count($createPaymentAmountsInput), count($createPaymentReferencesInput), count($createPaymentDatesInput));
for ($i = 0; $i < $maxPaymentRows; $i++) {
    $methodRaw = isset($createPaymentMethodsInput[$i]) ? trim((string)$createPaymentMethodsInput[$i]) : '';
    $amount = isset($createPaymentAmountsInput[$i]) ? trim((string)$createPaymentAmountsInput[$i]) : '';
    $reference = isset($createPaymentReferencesInput[$i]) ? trim((string)$createPaymentReferencesInput[$i]) : '';
    $serviceDate = isset($createPaymentDatesInput[$i]) ? trim((string)$createPaymentDatesInput[$i]) : '';
    if ($amount === '' && $reference === '') {
        continue;
    }
    $methodId = (ctype_digit($methodRaw) ? (int)$methodRaw : 0);
    $createPaymentRows[] = array(
        'payment_catalog_id' => $methodId,
        'payment_method_raw' => $methodRaw,
        'amount' => $amount,
        'reference' => $reference,
        'service_date' => $serviceDate
    );
}

$phoneCountries = function_exists('pms_phone_country_rows') ? pms_phone_country_rows() : array();
$defaultPhonePrefix = function_exists('pms_phone_prefix_default') ? pms_phone_prefix_default() : '+52';
$phonePrefixDialMap = function_exists('pms_phone_prefix_dials_map')
    ? pms_phone_prefix_dials_map()
    : array($defaultPhonePrefix => true);

$newReservationValues = array(
    'property_code' => isset($_POST['create_property_code']) ? strtoupper((string)$_POST['create_property_code']) : '',
    'room_code'     => isset($_POST['create_room_code']) ? strtoupper((string)$_POST['create_room_code']) : '',
    'lodging_catalog_id' => isset($_POST['create_lodging_catalog_id']) ? (int)$_POST['create_lodging_catalog_id'] : 0,
    'extra_lodging_rows' => $createExtraLodgingRows,
    'origin_key'       => isset($_POST['create_origin_id'])
        ? trim((string)$_POST['create_origin_id'])
        : (isset($_POST['create_source_id'])
            ? ('source:' . (int)$_POST['create_source_id'])
            : (isset($_POST['create_ota_account_id']) ? ('ota:' . (int)$_POST['create_ota_account_id']) : '')),
    'source_id'        => 0,
    'ota_account_id'   => 0,
    'guest_phone_prefix' => isset($_POST['create_guest_phone_prefix']) ? trim((string)$_POST['create_guest_phone_prefix']) : '',
    'check_in'      => isset($_POST['create_check_in']) ? (string)$_POST['create_check_in'] : '',
    'check_out'     => isset($_POST['create_check_out']) ? (string)$_POST['create_check_out'] : '',
    'guest_email'   => isset($_POST['create_guest_email']) ? (string)$_POST['create_guest_email'] : '',
    'guest_names'   => isset($_POST['create_guest_names']) ? (string)$_POST['create_guest_names'] : '',
    'guest_last'    => isset($_POST['create_guest_last_name']) ? (string)$_POST['create_guest_last_name'] : '',
    'guest_maiden'  => isset($_POST['create_guest_maiden_name']) ? (string)$_POST['create_guest_maiden_name'] : '',
    'guest_phone'   => isset($_POST['create_guest_phone']) ? (string)$_POST['create_guest_phone'] : '',
    'adults'        => isset($_POST['create_adults']) ? (string)$_POST['create_adults'] : '1',
    'children'      => isset($_POST['create_children']) ? (string)$_POST['create_children'] : '0',
    'total_override' => isset($_POST['create_total_override']) ? (string)$_POST['create_total_override'] : '',
    'nightly_override' => isset($_POST['create_total_nightly']) ? (string)$_POST['create_total_nightly'] : '',
    'fixed_child_amount' => isset($_POST['create_fixed_child_amount']) ? (string)$_POST['create_fixed_child_amount'] : '',
    'fixed_child_total' => isset($_POST['create_fixed_child_total']) ? (string)$_POST['create_fixed_child_total'] : '',
    'fixed_child_catalog_id' => isset($_POST['create_fixed_child_catalog_id']) ? (int)$_POST['create_fixed_child_catalog_id'] : 0,
    'payments' => $createPaymentRows
);
$createOriginOptions = reservations_origin_options_for_property(
    $reservationSourcesByProperty,
    $otaAccountsByProperty,
    $newReservationValues['property_code']
);
$createOriginRow = reservations_origin_row_for_key($createOriginOptions, $newReservationValues['origin_key']);
if ($createOriginRow === null && !empty($createOriginOptions)) {
    $createOriginRow = $createOriginOptions[0];
}
if ($createOriginRow === null) {
    $createOriginRow = array(
        'origin_key' => 'source:0',
        'source_id' => 0,
        'ota_account_id' => 0
    );
}
$newReservationValues['origin_key'] = (string)(isset($createOriginRow['origin_key']) ? $createOriginRow['origin_key'] : 'source:0');
$newReservationValues['source_id'] = isset($createOriginRow['source_id']) ? (int)$createOriginRow['source_id'] : 0;
$newReservationValues['ota_account_id'] = isset($createOriginRow['ota_account_id']) ? (int)$createOriginRow['ota_account_id'] : 0;
if (function_exists('pms_phone_extract_dial')) {
    $newReservationValues['guest_phone_prefix'] = pms_phone_extract_dial($newReservationValues['guest_phone_prefix'], '');
}
if ($newReservationValues['guest_phone_prefix'] === '') {
    if ($newReservationValues['guest_phone'] !== '' && preg_match('/^(\\+\\d{1,4})\\s*(.*)$/', $newReservationValues['guest_phone'], $matches)) {
        $newReservationValues['guest_phone_prefix'] = $matches[1];
        $newReservationValues['guest_phone'] = trim($matches[2]);
    } else {
        $newReservationValues['guest_phone_prefix'] = $defaultPhonePrefix;
    }
}
if (!isset($phonePrefixDialMap[$newReservationValues['guest_phone_prefix']])) {
    $newReservationValues['guest_phone_prefix'] = $defaultPhonePrefix;
}

$allPaymentCatalogs = reservations_payment_catalogs_for_property(
    $paymentCatalogsByProperty,
    isset($newReservationValues['property_code']) ? $newReservationValues['property_code'] : ''
);
if (empty($allPaymentCatalogs)) {
    $allPaymentCatalogs = array();
    $seenPaymentCatalogIds = array();
    foreach ($paymentCatalogsByProperty as $scopeRows) {
        if (!is_array($scopeRows)) {
            continue;
        }
        foreach ($scopeRows as $scopeRow) {
            $scopeCatalogId = isset($scopeRow['id_payment_catalog']) ? (int)$scopeRow['id_payment_catalog'] : 0;
            if ($scopeCatalogId <= 0 || isset($seenPaymentCatalogIds[$scopeCatalogId])) {
                continue;
            }
            $seenPaymentCatalogIds[$scopeCatalogId] = true;
            $allPaymentCatalogs[] = $scopeRow;
        }
    }
}

$confirmReservationValues = array();
$confirmGuestValues = array();
if ($action === 'confirm_reservation') {
    $confirmId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    if ($confirmId > 0) {
        $hasConfirmGuestInput = array_key_exists('confirm_guest_email', $_POST)
            || array_key_exists('confirm_guest_names', $_POST)
            || array_key_exists('confirm_guest_last_name', $_POST)
            || array_key_exists('confirm_guest_maiden_name', $_POST)
            || array_key_exists('confirm_guest_phone', $_POST)
            || array_key_exists('confirm_guest_phone_prefix', $_POST);
        $confirmReservationValues[$confirmId] = array(
            'lodging_catalog_id' => isset($_POST['confirm_lodging_catalog_id']) ? (int)$_POST['confirm_lodging_catalog_id'] : 0,
            'total_override' => isset($_POST['confirm_total_override']) ? (string)$_POST['confirm_total_override'] : '',
            'nightly_override' => isset($_POST['confirm_total_nightly']) ? (string)$_POST['confirm_total_nightly'] : ''
        );
        $confirmGuestValues[$confirmId] = array(
            'names' => $hasConfirmGuestInput
                ? (isset($_POST['confirm_guest_names']) ? (string)$_POST['confirm_guest_names'] : '')
                : (isset($_POST['reservation_guest_names']) ? (string)$_POST['reservation_guest_names'] : ''),
            'email' => $hasConfirmGuestInput
                ? (isset($_POST['confirm_guest_email']) ? (string)$_POST['confirm_guest_email'] : '')
                : (isset($_POST['reservation_guest_email']) ? (string)$_POST['reservation_guest_email'] : ''),
            'last' => $hasConfirmGuestInput
                ? (isset($_POST['confirm_guest_last_name']) ? (string)$_POST['confirm_guest_last_name'] : '')
                : (isset($_POST['reservation_guest_last_name']) ? (string)$_POST['reservation_guest_last_name'] : ''),
            'maiden' => $hasConfirmGuestInput
                ? (isset($_POST['confirm_guest_maiden_name']) ? (string)$_POST['confirm_guest_maiden_name'] : '')
                : (isset($_POST['reservation_guest_maiden_name']) ? (string)$_POST['reservation_guest_maiden_name'] : ''),
            'phone_prefix' => $hasConfirmGuestInput
                ? (isset($_POST['confirm_guest_phone_prefix']) ? (string)$_POST['confirm_guest_phone_prefix'] : '')
                : '',
            'phone' => $hasConfirmGuestInput
                ? (isset($_POST['confirm_guest_phone']) ? (string)$_POST['confirm_guest_phone'] : '')
                : (isset($_POST['reservation_guest_phone']) ? (string)$_POST['reservation_guest_phone'] : '')
        );
    }
}

/* Catalogo de conceptos por propiedad para cache */
$conceptsByProperty = array();
$lodgingOptionsByProperty = array();
$lodgingAllowedIdsByProperty = array();
$interestAllowedIdsByProperty = array();

if ($action === 'prepare_create') {
    // only refresh new reservation form (no action needed)
    $action = '';
}

if ($action === 'create_reservation') {
    $propertyCode = strtoupper(trim($newReservationValues['property_code']));
    $roomCode = strtoupper(trim($newReservationValues['room_code']));
    $checkIn = (string)$newReservationValues['check_in'];
    $checkOut = (string)$newReservationValues['check_out'];
    $guestEmail = trim($newReservationValues['guest_email']);
    $guestNames = trim($newReservationValues['guest_names']);
    $guestLast = trim($newReservationValues['guest_last']);
    $guestMaiden = trim($newReservationValues['guest_maiden']);
    $guestPhoneInput = trim($newReservationValues['guest_phone']);
    $guestPhonePrefix = isset($newReservationValues['guest_phone_prefix']) ? trim((string)$newReservationValues['guest_phone_prefix']) : '';
    $guestPhone = $guestPhoneInput;
    if ($guestPhone !== '' && substr($guestPhone, 0, 1) !== '+') {
        $guestPhone = trim(($guestPhonePrefix !== '' ? ($guestPhonePrefix . ' ') : '') . $guestPhone);
    }
    $adults = $newReservationValues['adults'] !== '' ? (int)$newReservationValues['adults'] : 1;
    $children = $newReservationValues['children'] !== '' ? (int)$newReservationValues['children'] : 0;
    $originKey = isset($newReservationValues['origin_key']) ? (string)$newReservationValues['origin_key'] : '';
    $originOptionsForReservation = reservations_origin_options_for_property(
        $reservationSourcesByProperty,
        $otaAccountsByProperty,
        $propertyCode
    );
    $originRowForReservation = reservations_origin_row_for_key($originOptionsForReservation, $originKey);
    if ($originRowForReservation === null && !empty($originOptionsForReservation)) {
        $originRowForReservation = $originOptionsForReservation[0];
    }
    if ($originRowForReservation === null) {
        $originRowForReservation = array(
            'origin_key' => 'source:0',
            'source_id' => 0,
            'ota_account_id' => 0,
            'source_value' => 'Directo'
        );
    }
    $originKey = (string)(isset($originRowForReservation['origin_key']) ? $originRowForReservation['origin_key'] : 'source:0');
    $sourceId = isset($originRowForReservation['source_id']) ? (int)$originRowForReservation['source_id'] : 0;
    $otaAccountId = isset($originRowForReservation['ota_account_id']) ? (int)$originRowForReservation['ota_account_id'] : 0;
    $sourceInput = trim((string)(isset($originRowForReservation['source_value']) ? $originRowForReservation['source_value'] : ''));
    if ($sourceInput === '') {
        $sourceInput = 'Directo';
    }
    $newReservationValues['origin_key'] = $originKey;
    $newReservationValues['source_id'] = $sourceId;
    $newReservationValues['ota_account_id'] = $otaAccountId;
    $lodgingCatalogId = isset($newReservationValues['lodging_catalog_id']) ? (int)$newReservationValues['lodging_catalog_id'] : 0;
    $totalOverrideRaw = trim((string)$newReservationValues['total_override']);
    $totalOverrideCents = $totalOverrideRaw !== '' ? reservations_to_cents($totalOverrideRaw) : null;
    if ($totalOverrideCents !== null && $totalOverrideCents <= 0) {
        $totalOverrideCents = null;
    }
    $nightlyOverrideRaw = trim((string)$newReservationValues['nightly_override']);
    $nightlyOverrideCents = $nightlyOverrideRaw !== '' ? reservations_to_cents($nightlyOverrideRaw) : null;
    if ($nightlyOverrideCents !== null && $nightlyOverrideCents <= 0) {
        $nightlyOverrideCents = null;
    }

    $fixedChildAmountRaw = trim((string)$newReservationValues['fixed_child_amount']);
    $fixedChildAmountCents = $fixedChildAmountRaw !== '' ? reservations_to_cents($fixedChildAmountRaw) : null;
    if ($fixedChildAmountCents !== null && $fixedChildAmountCents <= 0) {
        $fixedChildAmountCents = null;
    }
    $fixedChildTotalRaw = trim((string)$newReservationValues['fixed_child_total']);
    $fixedChildTotalCents = $fixedChildTotalRaw !== '' ? reservations_to_cents($fixedChildTotalRaw) : null;
    if ($fixedChildTotalCents !== null && $fixedChildTotalCents <= 0) {
        $fixedChildTotalCents = null;
    }
    $extraConceptRows = isset($newReservationValues['extra_lodging_rows']) && is_array($newReservationValues['extra_lodging_rows'])
        ? $newReservationValues['extra_lodging_rows']
        : array();
    $paymentRows = isset($newReservationValues['payments']) && is_array($newReservationValues['payments'])
        ? $newReservationValues['payments']
        : array();

    $overrideNights = null;
    try {
        $start = new DateTime($checkIn);
        $end = new DateTime($checkOut);
        $diff = (int)$start->diff($end)->format('%r%a');
        if ($diff > 0) {
            $overrideNights = $diff;
        }
    } catch (Exception $e) {
        $overrideNights = null;
    }

    if ($fixedChildAmountCents === null && $fixedChildTotalCents !== null && $overrideNights) {
        $fixedChildAmountCents = (int)round($fixedChildTotalCents / $overrideNights);
    }
    if ($fixedChildTotalCents === null && $fixedChildAmountCents !== null && $overrideNights) {
        $fixedChildTotalCents = $fixedChildAmountCents * $overrideNights;
    }

    if ($totalOverrideCents !== null && $totalOverrideCents > 0) {
        if ($fixedChildTotalCents !== null) {
            $totalOverrideCents += $fixedChildTotalCents;
        }
    } elseif ($totalOverrideCents === null && $nightlyOverrideCents !== null && $overrideNights) {
        try {
            $totalOverrideCents = $nightlyOverrideCents * $overrideNights;
            if ($fixedChildTotalCents !== null) {
                $totalOverrideCents += $fixedChildTotalCents;
            }
        } catch (Exception $e) {
            // leave total override as null
        }
    }

    $hasGuestInfo = ($guestEmail !== '' || $guestNames !== '' || $guestLast !== '' || $guestMaiden !== '' || $guestPhoneInput !== '');
    if ($propertyCode === '' || $roomCode === '' || $checkIn === '' || $checkOut === '') {
        $newReservationError = 'Completa los datos obligatorios para crear la reserva.';
    } elseif ($hasGuestInfo && $guestNames === '') {
        $newReservationError = 'Completa el nombre del hu&eacute;sped o deja todos los campos vac&iacute;os.';
    } elseif ($guestEmail !== '' && !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$/', $guestEmail)) {
        $newReservationError = 'Correo de hu&eacute;sped inv&aacute;lido.';
    } elseif ($lodgingCatalogId <= 0) {
        $newReservationError = 'Selecciona un concepto de hospedaje para la reserva.';
    } else {
        try {
            $resultSets = pms_call_procedure('sp_create_reservation', array(
                $propertyCode,
                $roomCode,
                $checkIn,
                $checkOut,
                $guestEmail,
                $guestNames,
                $guestLast,
                $guestMaiden,
                $guestPhone,
                $adults,
                $children,
                $lodgingCatalogId,
                $totalOverrideCents,
                $fixedChildAmountCents,
                $fixedChildTotalCents,
                $sourceInput,
                $otaAccountId,
                $actorUserId
            ));

            $createdRow = null;
            $createdId = null;
            $firstRow = null;
            foreach ($resultSets as $set) {
                foreach ($set as $row) {
                    if ($firstRow === null) {
                        $firstRow = $row;
                    }
                    if (isset($row['id_reservation'])) {
                        $createdRow = $row;
                        $createdId = (int)$row['id_reservation'];
                        break 2;
                    }
                }
            }

            if ($createdId) {
                $newReservationMessage = 'Reserva creada correctamente.';
                $paymentQueue = array();
                $paymentRowIssues = false;
                $paymentConceptOptions = reservations_payment_catalogs_for_reservation(
                    $paymentCatalogsByProperty,
                    $propertyCode,
                    $companyId,
                    $createdId
                );
                $paymentConceptFallbackId = 0;
                $paymentConceptOptionMap = array();
                foreach ($paymentConceptOptions as $opt) {
                    $optId = isset($opt['id_payment_catalog']) ? (int)$opt['id_payment_catalog'] : 0;
                    if ($optId <= 0) {
                        continue;
                    }
                    $paymentConceptOptionMap[$optId] = true;
                }
                if (!empty($paymentConceptOptions)) {
                    $paymentConceptFallbackId = isset($paymentConceptOptions[0]['id_payment_catalog'])
                        ? (int)$paymentConceptOptions[0]['id_payment_catalog']
                        : 0;
                }
                foreach ($paymentRows as $row) {
                    $amountRaw = isset($row['amount']) ? trim((string)$row['amount']) : '';
                    $amountCents = $amountRaw !== '' ? reservations_to_cents($amountRaw) : 0;
                    if ($amountCents <= 0) {
                        continue;
                    }
                    $paymentMethodId = isset($row['payment_catalog_id']) ? (int)$row['payment_catalog_id'] : 0;
                    if ($paymentMethodId <= 0 && isset($row['payment_method_raw'])) {
                        $rawName = trim((string)$row['payment_method_raw']);
                        if ($rawName !== '') {
                            foreach ($paymentCatalogsById as $pc) {
                                $candidateName = isset($pc['name']) ? (string)$pc['name'] : '';
                                $candidateLabel = isset($pc['label']) ? (string)$pc['label'] : $candidateName;
                                if (($candidateName !== '' && strcasecmp($candidateName, $rawName) === 0)
                                    || ($candidateLabel !== '' && strcasecmp($candidateLabel, $rawName) === 0)) {
                                    $paymentMethodId = (int)$pc['id_payment_catalog'];
                                    break;
                                }
                            }
                        }
                    }
                    if ($paymentMethodId > 0 && !isset($paymentConceptOptionMap[$paymentMethodId])) {
                        $paymentMethodId = 0;
                    }
                    if ($paymentMethodId <= 0 && $paymentConceptFallbackId > 0) {
                        $paymentMethodId = $paymentConceptFallbackId;
                    }
                    if ($paymentMethodId <= 0) {
                        $paymentRowIssues = true;
                        continue;
                    }
                    $paymentMethodName = reservations_payment_method_name_by_id($paymentCatalogsById, $paymentMethodId);
                    if ($paymentMethodName === '') {
                        $paymentMethodName = 'Concepto #' . $paymentMethodId;
                    }
                    $paymentQueue[] = array(
                        'payment_catalog_id' => $paymentMethodId,
                        'payment_method_name' => $paymentMethodName,
                        'amount_cents' => $amountCents,
                        'reference' => isset($row['reference']) ? trim((string)$row['reference']) : '',
                        'service_date' => isset($row['service_date']) ? trim((string)$row['service_date']) : ''
                    );
                }

                $filteredExtraConcepts = array();
                foreach ($extraConceptRows as $row) {
                    $extraId = isset($row['id']) ? (int)$row['id'] : 0;
                    if ($extraId <= 0 || $extraId === $lodgingCatalogId) {
                        continue;
                    }
                    $filteredExtraConcepts[] = array(
                        'id' => $extraId,
                        'amount' => isset($row['amount']) ? trim((string)$row['amount']) : ''
                    );
                }

                $needsServiceFolio = !empty($paymentQueue) || !empty($filteredExtraConcepts);
                $lodgingFolioId = 0;
                $serviceFolioId = 0;
                $folioCurrency = isset($createdRow['currency']) && $createdRow['currency'] !== '' ? (string)$createdRow['currency'] : 'MXN';
                $folioDueDate = isset($createdRow['check_out_date']) && $createdRow['check_out_date'] !== '' ? (string)$createdRow['check_out_date'] : null;
                if ($createdId > 0) {
                    try {
                        $db = pms_get_connection();
                        $stmt = $db->prepare(
                            'SELECT id_folio, folio_name, currency, due_date
                             FROM folio
                             WHERE id_reservation = ?
                               AND deleted_at IS NULL
                               AND COALESCE(is_active, 1) = 1
                             ORDER BY id_folio ASC'
                        );
                        $stmt->execute(array($createdId));
                        $folioRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($folioRows as $folioRow) {
                            $candidateId = isset($folioRow['id_folio']) ? (int)$folioRow['id_folio'] : 0;
                            if ($candidateId <= 0) {
                                continue;
                            }
                            $candidateName = isset($folioRow['folio_name']) ? (string)$folioRow['folio_name'] : '';
                            $candidateRole = reservations_folio_role_by_name($candidateName);
                            if ($candidateRole === 'services') {
                                if ($serviceFolioId <= 0) {
                                    $serviceFolioId = $candidateId;
                                }
                                continue;
                            }
                            if ($lodgingFolioId <= 0) {
                                $lodgingFolioId = $candidateId;
                                if (isset($folioRow['currency']) && trim((string)$folioRow['currency']) !== '') {
                                    $folioCurrency = (string)$folioRow['currency'];
                                }
                                if (isset($folioRow['due_date']) && trim((string)$folioRow['due_date']) !== '') {
                                    $folioDueDate = (string)$folioRow['due_date'];
                                }
                            }
                        }
                        if ($lodgingFolioId <= 0 && $createdRow && isset($createdRow['id_folio'])) {
                            $lodgingFolioId = (int)$createdRow['id_folio'];
                        }
                        if ($lodgingFolioId > 0) {
                            try {
                                $stmtNormalizeLodgingName = $db->prepare(
                                    'UPDATE folio
                                     SET folio_name = ?
                                     WHERE id_folio = ?
                                       AND (
                                         folio_name IS NULL
                                         OR TRIM(folio_name) = \'\'
                                         OR LOWER(TRIM(folio_name)) IN (\'principal\', \'main\')
                                       )'
                                );
                                $stmtNormalizeLodgingName->execute(array('Hospedaje', $lodgingFolioId));
                            } catch (Exception $e) {
                            }
                        }
                        if ($lodgingFolioId <= 0) {
                            $stmtCreatePrincipal = $db->prepare(
                                'INSERT INTO folio (
                                    id_reservation, folio_name, status, currency, total_cents, balance_cents, due_date,
                                    is_active, created_at, created_by, updated_at
                                 ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
                            );
                            $stmtCreatePrincipal->execute(array(
                                $createdId,
                                'Hospedaje',
                                'open',
                                $folioCurrency,
                                0,
                                0,
                                $folioDueDate,
                                $actorUserId
                            ));
                            $lodgingFolioId = (int)$db->lastInsertId();
                        }
                        if ($serviceFolioId <= 0) {
                            $stmtCreateServices = $db->prepare(
                                'INSERT INTO folio (
                                    id_reservation, folio_name, status, currency, total_cents, balance_cents, due_date,
                                    is_active, created_at, created_by, updated_at
                                 ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
                            );
                            $stmtCreateServices->execute(array(
                                $createdId,
                                'Servicios',
                                'open',
                                $folioCurrency,
                                0,
                                0,
                                $folioDueDate,
                                $actorUserId
                            ));
                            $serviceFolioId = (int)$db->lastInsertId();
                        }
                    } catch (Exception $e) {
                        $financeErrors[$createdId] = 'No se pudieron preparar los folios de hospedaje/servicios: ' . $e->getMessage();
                    }
                }

                if ($lodgingFolioId > 0) {
                    try {
                        pms_call_procedure('sp_folio_recalc', array($lodgingFolioId));
                    } catch (Exception $e) {
                    }
                }

                $targetServiceFolioId = $serviceFolioId > 0 ? $serviceFolioId : $lodgingFolioId;

                if ($targetServiceFolioId > 0 && !empty($filteredExtraConcepts)) {
                    $createdCode = '';
                    if ($createdRow && isset($createdRow['reservation_code'])) {
                        $createdCode = (string)$createdRow['reservation_code'];
                    } elseif ($createdRow && isset($createdRow['code'])) {
                        $createdCode = (string)$createdRow['code'];
                    }
                    $descriptionBase = $createdCode !== '' ? ('Reserva ' . $createdCode) : null;
                    foreach ($filteredExtraConcepts as $extraItem) {
                        $extraId = (int)$extraItem['id'];
                        $extraAmountRaw = isset($extraItem['amount']) ? trim((string)$extraItem['amount']) : '';
                        $extraAmountCents = $extraAmountRaw !== '' ? reservations_to_cents($extraAmountRaw) : null;
                        if ($extraAmountCents !== null && $extraAmountCents <= 0) {
                            $extraAmountCents = null;
                        }
                        try {
                            pms_call_procedure('sp_sale_item_upsert', array(
                                'create',
                                0,
                                $targetServiceFolioId,
                                $createdId,
                                $extraId,
                                $descriptionBase,
                                $checkIn,
                                1,
                                $extraAmountCents,
                                0,
                                'posted',
                                $actorUserId
                            ));
                        } catch (Exception $e) {
                            $financeErrors[$createdId] = 'No se pudo registrar concepto adicional: ' . $e->getMessage();
                        }
                    }
                    try {
                        pms_call_procedure('sp_folio_recalc', array($targetServiceFolioId));
                    } catch (Exception $e) {
                    }
                } elseif ($needsServiceFolio && $targetServiceFolioId <= 0 && !isset($financeErrors[$createdId])) {
                    $financeErrors[$createdId] = 'No se encontro un folio de servicios para registrar cargos/pagos.';
                }

                if ($targetServiceFolioId > 0 && !empty($paymentQueue) && !isset($financeErrors[$createdId])) {
                    foreach ($paymentQueue as $queuedPayment) {
                        $queuedAmountCents = isset($queuedPayment['amount_cents']) ? (int)$queuedPayment['amount_cents'] : 0;
                        if ($queuedAmountCents <= 0) {
                            continue;
                        }
                        $queuedDate = isset($queuedPayment['service_date']) ? trim((string)$queuedPayment['service_date']) : '';
                        if ($queuedDate === '') {
                            $queuedDate = $checkIn !== '' ? $checkIn : date('Y-m-d');
                        }
                        $queuedMethodName = isset($queuedPayment['payment_method_name']) ? trim((string)$queuedPayment['payment_method_name']) : '';
                        $queuedReference = isset($queuedPayment['reference']) ? trim((string)$queuedPayment['reference']) : '';
                        $queuedCatalogId = isset($queuedPayment['payment_catalog_id']) ? (int)$queuedPayment['payment_catalog_id'] : 0;
                        if ($queuedCatalogId <= 0) {
                            $financeErrors[$createdId] = 'No hay concepto de pago configurado para registrar pagos.';
                            break;
                        }
                        try {
                            reservations_create_payment_line_item(
                                $targetServiceFolioId,
                                $createdId,
                                $queuedCatalogId,
                                $queuedDate,
                                $queuedAmountCents,
                                'captured',
                                $actorUserId,
                                $queuedMethodName,
                                $queuedReference
                            );
                        } catch (Exception $e) {
                            $financeErrors[$createdId] = 'No se pudo registrar pago inicial: ' . $e->getMessage();
                            break;
                        }
                    }
                } elseif (!empty($paymentRows) && empty($paymentQueue) && !isset($financeErrors[$createdId])) {
                    $financeErrors[$createdId] = $paymentRowIssues
                        ? 'Selecciona un concepto de pago valido para los pagos iniciales.'
                        : 'No se pudo registrar el pago inicial. Verifica el monto.';
                }
                /* Cerrar pestaÃƒÂ±a de Ã¢â‚¬Å“Nueva reservaÃ¢â‚¬Â y limpiar su estado */
                if (isset($_SESSION['pms_subtabs'][$moduleKey]['open'])) {
                    $_SESSION['pms_subtabs'][$moduleKey]['open'] = array_values(array_filter($_SESSION['pms_subtabs'][$moduleKey]['open'], function ($k) {
                        return $k !== 'new';
                    }));
                }
                if (isset($_SESSION['pms_subtabs'][$moduleKey]['active']) && $_SESSION['pms_subtabs'][$moduleKey]['active'] === 'dynamic:new') {
                    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'dynamic:reservation:' . $createdId;
                }
                $newReservationValues = array(
                    'property_code' => $propertyCode,
                    'room_code'     => '',
                    'lodging_catalog_id' => 0,
                    'check_in'      => '',
                    'check_out'     => '',
                    'guest_email'   => '',
                    'guest_names'   => '',
                    'guest_last'    => '',
                    'guest_maiden'  => '',
                    'guest_phone'   => '',
                    'adults'        => '1',
                    'children'      => '0',
                    'total_override' => '',
                    'nightly_override' => '',
                    'fixed_child_amount' => '',
                    'fixed_child_total' => '',
                    'fixed_child_catalog_id' => 0,
                    'extra_lodging_rows' => array(),
                    'payments' => array()
                );
                $_POST[$moduleKey . '_subtab_action'] = 'open';
                $_POST[$moduleKey . '_subtab_target'] = 'reservation:' . $createdId;
            } else {
                if (isset($firstRow['message']) && $firstRow['message'] !== '') {
                    $newReservationError = (string)$firstRow['message'];
                } elseif ($firstRow) {
                    $newReservationError = 'No se pudo crear la reserva. Detalle: ' . htmlspecialchars(json_encode($firstRow), ENT_QUOTES, 'UTF-8');
                } else {
                    $newReservationError = 'No se pudo crear la reserva.';
                }
            }
        } catch (Exception $e) {
            $newReservationError = $e->getMessage();
        }
    }
  } elseif ($action === 'confirm_reservation') {
      $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
      $lodgingCatalogId = isset($_POST['confirm_lodging_catalog_id']) ? (int)$_POST['confirm_lodging_catalog_id'] : 0;
      $totalOverrideRaw = isset($_POST['confirm_total_override']) ? trim((string)$_POST['confirm_total_override']) : '';
      $totalOverrideCents = $totalOverrideRaw !== '' ? reservations_to_cents($totalOverrideRaw) : null;
      if ($totalOverrideCents !== null && $totalOverrideCents <= 0) {
          $totalOverrideCents = null;
      }
      $nightlyOverrideRaw = isset($_POST['confirm_total_nightly']) ? trim((string)$_POST['confirm_total_nightly']) : '';
      $nightlyOverrideCents = $nightlyOverrideRaw !== '' ? reservations_to_cents($nightlyOverrideRaw) : null;
      if ($nightlyOverrideCents !== null && $nightlyOverrideCents <= 0) {
          $nightlyOverrideCents = null;
      }
      $roomCode = isset($_POST['reservation_room_code']) ? strtoupper((string)$_POST['reservation_room_code']) : '';
      $checkIn = isset($_POST['reservation_check_in']) ? (string)$_POST['reservation_check_in'] : '';
      $checkOut = isset($_POST['reservation_check_out']) ? (string)$_POST['reservation_check_out'] : '';
      $adults = isset($_POST['reservation_adults']) && $_POST['reservation_adults'] !== '' ? (int)$_POST['reservation_adults'] : null;
      $children = isset($_POST['reservation_children']) && $_POST['reservation_children'] !== '' ? (int)$_POST['reservation_children'] : null;

      if ($totalOverrideCents === null && $nightlyOverrideCents !== null && $checkIn !== '' && $checkOut !== '') {
          try {
              $start = new DateTime($checkIn);
              $end = new DateTime($checkOut);
              $diff = (int)$start->diff($end)->format('%r%a');
              if ($diff > 0) {
                  $totalOverrideCents = $nightlyOverrideCents * $diff;
              }
          } catch (Exception $e) {
              // leave total override as null
          }
      }

      $hasConfirmGuestInput = array_key_exists('confirm_guest_email', $_POST)
          || array_key_exists('confirm_guest_names', $_POST)
          || array_key_exists('confirm_guest_last_name', $_POST)
          || array_key_exists('confirm_guest_maiden_name', $_POST)
          || array_key_exists('confirm_guest_phone', $_POST)
          || array_key_exists('confirm_guest_phone_prefix', $_POST);
      $guestEmail = $hasConfirmGuestInput
          ? (isset($_POST['confirm_guest_email']) ? trim((string)$_POST['confirm_guest_email']) : '')
          : (isset($_POST['reservation_guest_email']) ? trim((string)$_POST['reservation_guest_email']) : '');
      $guestNames = $hasConfirmGuestInput
          ? (isset($_POST['confirm_guest_names']) ? trim((string)$_POST['confirm_guest_names']) : '')
          : (isset($_POST['reservation_guest_names']) ? trim((string)$_POST['reservation_guest_names']) : '');
      $guestLast = $hasConfirmGuestInput
          ? (isset($_POST['confirm_guest_last_name']) ? trim((string)$_POST['confirm_guest_last_name']) : '')
          : (isset($_POST['reservation_guest_last_name']) ? trim((string)$_POST['reservation_guest_last_name']) : '');
      $guestMaiden = $hasConfirmGuestInput
          ? (isset($_POST['confirm_guest_maiden_name']) ? trim((string)$_POST['confirm_guest_maiden_name']) : '')
          : (isset($_POST['reservation_guest_maiden_name']) ? trim((string)$_POST['reservation_guest_maiden_name']) : '');
      $guestPhoneRaw = $hasConfirmGuestInput
          ? (isset($_POST['confirm_guest_phone']) ? trim((string)$_POST['confirm_guest_phone']) : '')
          : (isset($_POST['reservation_guest_phone']) ? trim((string)$_POST['reservation_guest_phone']) : '');
      $guestPhonePrefixRaw = $hasConfirmGuestInput
          ? (isset($_POST['confirm_guest_phone_prefix']) ? trim((string)$_POST['confirm_guest_phone_prefix']) : '')
          : '';
      $guestPhone = $guestPhoneRaw;
      if ($hasConfirmGuestInput) {
          $guestPhonePrefix = function_exists('pms_phone_extract_dial')
              ? pms_phone_extract_dial($guestPhonePrefixRaw, '')
              : $guestPhonePrefixRaw;
          if ($guestPhone !== '' && preg_match('/^(\\+\\d{1,4})\\s*(.*)$/', $guestPhone, $guestPhoneMatches)) {
              if ($guestPhonePrefix === '') {
                  $guestPhonePrefix = $guestPhoneMatches[1];
              }
              $guestPhone = trim($guestPhoneMatches[2]);
          }
          if ($guestPhonePrefix !== '' && !isset($phonePrefixDialMap[$guestPhonePrefix])) {
              $guestPhonePrefix = $defaultPhonePrefix;
          }
          if ($guestPhone !== '') {
              $guestPhone = trim(($guestPhonePrefix !== '' ? ($guestPhonePrefix . ' ') : '') . $guestPhone);
          }
      }
      $confirmGuestIdInput = isset($_POST['confirm_guest_id']) ? (int)$_POST['confirm_guest_id'] : 0;
      $guestIdInput = $confirmGuestIdInput > 0
          ? $confirmGuestIdInput
          : (isset($_POST['reservation_guest_id']) ? (int)$_POST['reservation_guest_id'] : 0);

      if ($reservationId <= 0) {
          $updateErrors[$reservationId] = 'Selecciona una reserva v&aacute;lida.';
      } elseif ($guestNames === '') {
          $updateErrors[$reservationId] = 'Completa el nombre del hu&eacute;sped para confirmar.';
      } elseif ($guestEmail !== '' && !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $guestEmail)) {
          $updateErrors[$reservationId] = 'Correo de hu&eacute;sped inv&aacute;lido.';
      } elseif ($lodgingCatalogId <= 0) {
          $updateErrors[$reservationId] = 'Selecciona un concepto de hospedaje para confirmar.';
      } else {
          $guestIdValue = null;
          try {
              $pdo = pms_get_connection();
              $guestId = $guestIdInput;
              if ($guestId > 0) {
                  $stmt = $pdo->prepare('SELECT id_guest FROM guest WHERE id_guest = ? LIMIT 1');
                  $stmt->execute(array($guestId));
                  $found = $stmt->fetchColumn();
                  if ($found === false) {
                      $guestId = 0;
                  }
              }
              if ($guestId <= 0 && $guestEmail !== '') {
                  $stmt = $pdo->prepare('SELECT id_guest FROM guest WHERE email = ? LIMIT 1');
                  $stmt->execute(array($guestEmail));
                  $found = $stmt->fetchColumn();
                  $guestId = $found !== false ? (int)$found : 0;
              }
              $guestLastFull = trim($guestLast . ' ' . $guestMaiden);
              if ($guestId <= 0) {
                  $guestEmailValue = $guestEmail !== '' ? $guestEmail : null;
                  $stmt = $pdo->prepare('INSERT INTO guest (email, phone, names, last_name, language, is_active, created_at, updated_at) VALUES (?,?,?,?,?,1,NOW(),NOW())');
                  $stmt->execute(array(
                      $guestEmailValue,
                      $guestPhone !== '' ? $guestPhone : null,
                      $guestNames,
                      $guestLastFull !== '' ? $guestLastFull : null,
                      'es'
                  ));
                  $guestId = (int)$pdo->lastInsertId();
              } else {
                  $stmt = $pdo->prepare('UPDATE guest SET names = CASE WHEN ? IS NULL OR CHAR_LENGTH(?) = 0 THEN names ELSE ? END, last_name = CASE WHEN ? IS NULL OR CHAR_LENGTH(?) = 0 THEN last_name ELSE ? END, phone = CASE WHEN ? IS NULL OR CHAR_LENGTH(?) = 0 THEN phone ELSE ? END, updated_at = NOW() WHERE id_guest = ?');
                  $stmt->execute(array(
                      $guestNames,
                      $guestNames,
                      $guestNames,
                      $guestLastFull,
                      $guestLastFull,
                      $guestLastFull,
                      $guestPhone,
                      $guestPhone,
                      $guestPhone,
                      $guestId
                  ));
              }
              $guestIdValue = $guestId;
          } catch (Exception $e) {
              $updateErrors[$reservationId] = $e->getMessage();
          }

          if (!isset($updateErrors[$reservationId])) {
              try {
                  pms_call_procedure('sp_reservation_update', array(
                      $companyCode,
                      $reservationId,
                      null,
                      null,
                      null,
                      $roomCode === '' ? null : $roomCode,
                      $checkIn === '' ? null : $checkIn,
                      $checkOut === '' ? null : $checkOut,
                      $adults,
                      $children,
                      null,
                      null,
                      $actorUserId
                  ));
                  pms_call_procedure('sp_reservation_confirm_hold', array(
                      $companyCode,
                      $reservationId,
                      $guestIdValue,
                      $lodgingCatalogId,
                      $totalOverrideCents,
                      $adults,
                      $children,
                      $actorUserId
                  ));
                  $updateMessages[$reservationId] = 'Reserva confirmada.';
                  $_POST[$moduleKey . '_subtab_action'] = 'activate';
                  $_POST[$moduleKey . '_subtab_target'] = 'dynamic:reservation:' . $reservationId;
                  $clearDirtyTargets[] = 'dynamic:reservation:' . $reservationId;
              } catch (Exception $e) {
                  $updateErrors[$reservationId] = $e->getMessage();
              }
          }
      }
  } elseif ($action === 'update_reservation') {
      $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
      $reservationCodeInput = isset($_POST['reservation_code']) ? strtoupper(trim((string)$_POST['reservation_code'])) : '';
      $reservationPropertyCode = isset($_POST['reservation_property_code']) ? strtoupper(trim((string)$_POST['reservation_property_code'])) : '';
      $status = isset($_POST['reservation_status']) ? trim((string)$_POST['reservation_status']) : '';
      $originSelectionRaw = isset($_POST['reservation_origin_id'])
          ? trim((string)$_POST['reservation_origin_id'])
          : (isset($_POST['reservation_source_id'])
              ? ('source:' . (int)$_POST['reservation_source_id'])
              : (isset($_POST['reservation_ota_account_id']) ? ('ota:' . (int)$_POST['reservation_ota_account_id']) : ''));
      $originKey = reservations_origin_key_from_input($originSelectionRaw);
      $sourceId = 0;
      $otaAccountId = 0;
      $roomCode = isset($_POST['reservation_room_code']) ? strtoupper((string)$_POST['reservation_room_code']) : '';
      $checkIn = isset($_POST['reservation_check_in']) ? (string)$_POST['reservation_check_in'] : '';
      $checkOut = isset($_POST['reservation_check_out']) ? (string)$_POST['reservation_check_out'] : '';
      $adults = isset($_POST['reservation_adults']) && $_POST['reservation_adults'] !== '' ? (int)$_POST['reservation_adults'] : null;
      $children = isset($_POST['reservation_children']) && $_POST['reservation_children'] !== '' ? (int)$_POST['reservation_children'] : null;
      $guestPayloadPresent = array_key_exists('reservation_guest_email', $_POST)
          || array_key_exists('reservation_guest_names', $_POST)
          || array_key_exists('reservation_guest_last_name', $_POST)
          || array_key_exists('reservation_guest_maiden_name', $_POST)
          || array_key_exists('reservation_guest_phone', $_POST)
          || array_key_exists('reservation_guest_id', $_POST);
      $guestEmail = isset($_POST['reservation_guest_email']) ? trim((string)$_POST['reservation_guest_email']) : '';
      $guestNames = isset($_POST['reservation_guest_names']) ? trim((string)$_POST['reservation_guest_names']) : '';
      $guestLast = isset($_POST['reservation_guest_last_name']) ? trim((string)$_POST['reservation_guest_last_name']) : '';
      $guestMaiden = isset($_POST['reservation_guest_maiden_name']) ? trim((string)$_POST['reservation_guest_maiden_name']) : '';
      $guestPhone = isset($_POST['reservation_guest_phone']) ? trim((string)$_POST['reservation_guest_phone']) : '';
      $guestIdInput = isset($_POST['reservation_guest_id']) ? (int)$_POST['reservation_guest_id'] : 0;
      $guestIdValue = null;
      $guestError = null;

      if ($reservationId <= 0) {
          $updateErrors[$reservationId] = 'Selecciona una reserva v&aacute;lida.';
      } elseif ($guestPayloadPresent) {
          $hasGuestInfo = ($guestEmail !== '' || $guestNames !== '' || $guestLast !== '' || $guestMaiden !== '' || $guestPhone !== '');
          if ($hasGuestInfo && $guestNames === '') {
              $guestError = 'Completa el nombre del hu&eacute;sped o deja todos los campos vac&iacute;os.';
          } elseif ($guestEmail !== '' && !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$/', $guestEmail)) {
              $guestError = 'Correo de hu&eacute;sped inv&aacute;lido.';
          } else {
              if (!$hasGuestInfo) {
                  $guestIdValue = null;
              } else {
                  $pdo = pms_get_connection();
                  $guestId = $guestIdInput;
                  if ($guestId > 0) {
                      $stmt = $pdo->prepare('SELECT id_guest FROM guest WHERE id_guest = ? LIMIT 1');
                      $stmt->execute(array($guestId));
                      $found = $stmt->fetchColumn();
                      if ($found === false) {
                          $guestId = 0;
                      }
                  }
                  if ($guestId <= 0 && $guestEmail !== '') {
                      $stmt = $pdo->prepare('SELECT id_guest FROM guest WHERE email = ? LIMIT 1');
                      $stmt->execute(array($guestEmail));
                      $found = $stmt->fetchColumn();
                      $guestId = $found !== false ? (int)$found : 0;
                  }
                  $guestLastFull = trim($guestLast . ' ' . $guestMaiden);
                  if ($guestId <= 0) {
                      $guestEmailValue = $guestEmail !== '' ? $guestEmail : null;
                      $stmt = $pdo->prepare('INSERT INTO guest (email, phone, names, last_name, language, is_active, created_at, updated_at) VALUES (?,?,?,?,?,1,NOW(),NOW())');
                      $stmt->execute(array(
                          $guestEmailValue,
                          $guestPhone !== '' ? $guestPhone : null,
                          $guestNames,
                          $guestLastFull !== '' ? $guestLastFull : null,
                          'es'
                      ));
                      $guestId = (int)$pdo->lastInsertId();
                  } else {
                      $stmt = $pdo->prepare('UPDATE guest SET names = CASE WHEN ? IS NULL OR CHAR_LENGTH(?) = 0 THEN names ELSE ? END, last_name = CASE WHEN ? IS NULL OR CHAR_LENGTH(?) = 0 THEN last_name ELSE ? END, phone = CASE WHEN ? IS NULL OR CHAR_LENGTH(?) = 0 THEN phone ELSE ? END, updated_at = NOW() WHERE id_guest = ?');
                      $stmt->execute(array(
                          $guestNames,
                          $guestNames,
                          $guestNames,
                          $guestLastFull,
                          $guestLastFull,
                          $guestLastFull,
                          $guestPhone,
                          $guestPhone,
                          $guestPhone,
                          $guestId
                      ));
                  }
                  $guestIdValue = $guestId;
              }
          }
      }

      if ($guestError) {
          $updateErrors[$reservationId] = $guestError;
      } else {
          if ($reservationPropertyCode === '') {
              try {
                  $pdo = pms_get_connection();
                  $stmtProperty = $pdo->prepare(
                      'SELECT p.code
                       FROM reservation r
                       JOIN property p ON p.id_property = r.id_property
                       WHERE r.id_reservation = ? AND p.id_company = ?
                       LIMIT 1'
                  );
                  $stmtProperty->execute(array($reservationId, $companyId));
                  $propertyFound = $stmtProperty->fetchColumn();
                  if ($propertyFound !== false) {
                      $reservationPropertyCode = strtoupper((string)$propertyFound);
                  }
              } catch (Exception $e) {
                  $reservationPropertyCode = '';
              }
          }

          $originOptionsForReservation = reservations_origin_options_for_property(
              $reservationSourcesByProperty,
              $otaAccountsByProperty,
              $reservationPropertyCode
          );
          $originRowForReservation = reservations_origin_row_for_key($originOptionsForReservation, $originKey);
          if ($originRowForReservation === null && !empty($originOptionsForReservation)) {
              $originRowForReservation = $originOptionsForReservation[0];
          }
          if ($originRowForReservation === null) {
              $originRowForReservation = array(
                  'origin_key' => 'source:0',
                  'source_id' => 0,
                  'ota_account_id' => 0,
                  'source_value' => 'Directo'
              );
          }
          $originKey = (string)(isset($originRowForReservation['origin_key']) ? $originRowForReservation['origin_key'] : 'source:0');
          $sourceId = isset($originRowForReservation['source_id']) ? (int)$originRowForReservation['source_id'] : 0;
          $otaAccountId = isset($originRowForReservation['ota_account_id']) ? (int)$originRowForReservation['ota_account_id'] : 0;
          $sourceInput = trim((string)(isset($originRowForReservation['source_value']) ? $originRowForReservation['source_value'] : ''));
          if ($sourceInput === '') {
              $sourceInput = 'Directo';
          }
          $roomCodeForUpdate = null;
          if ($roomCode !== '') {
              $roomCodeForUpdate = $reservationPropertyCode !== ''
                  ? ($reservationPropertyCode . '|' . $roomCode)
                  : $roomCode;
          }
          try {
              $usedLegacyReservationUpdate = false;
              $statusBypassApplied = false;
              $statusForBypass = reservations_status_normalize_for_update($status);
              $canTryStatusBypass = $statusForBypass !== '';
              $tryForceStatusOnChargesBlock = function ($errorMessage) use ($canTryStatusBypass, $companyCode, $reservationId, $statusForBypass) {
                  if (!$canTryStatusBypass) {
                      return false;
                  }
                  $errorNormalized = strtolower(trim((string)$errorMessage));
                  $isChargesBlockError = (strpos($errorNormalized, 'necesita') !== false && strpos($errorNormalized, 'cargos') !== false);
                  if (!$isChargesBlockError) {
                      return false;
                  }
                  $requirements = reservations_status_requirements_snapshot($companyCode, $reservationId);
                  $canForceUpdate = $requirements
                      && !empty($requirements['has_guest'])
                      && !empty($requirements['has_charges']);
                  if (!$canForceUpdate) {
                      return false;
                  }
                  return reservations_force_update_status($companyCode, $reservationId, $statusForBypass);
              };
              try {
                  pms_call_procedure('sp_reservation_update_v2', array(
                      $companyCode,
                      $reservationId,
                      $status === '' ? null : $status,
                      $sourceInput,
                      $otaAccountId,
                      $roomCodeForUpdate,
                      $checkIn === '' ? null : $checkIn,
                      $checkOut === '' ? null : $checkOut,
                      $adults,
                      $children,
                      $reservationCodeInput === '' ? null : $reservationCodeInput,
                      null,
                      null,
                      $actorUserId
                  ));
              } catch (Exception $e) {
                  $err = $e->getMessage();
                  $missingV2 = stripos($err, 'sp_reservation_update_v2') !== false
                      || stripos($err, 'does not exist') !== false
                      || stripos($err, 'PROCEDURE') !== false;
                  if (!$missingV2) {
                      if ($tryForceStatusOnChargesBlock($err)) {
                          $statusBypassApplied = true;
                      } else {
                          throw $e;
                      }
                  } else {
                      try {
                          pms_call_procedure('sp_reservation_update', array(
                              $companyCode,
                              $reservationId,
                              $status === '' ? null : $status,
                              $sourceInput,
                              $otaAccountId,
                              $roomCodeForUpdate,
                              $checkIn === '' ? null : $checkIn,
                              $checkOut === '' ? null : $checkOut,
                              $adults,
                              $children,
                              null,
                              null,
                              $actorUserId
                          ));
                          $usedLegacyReservationUpdate = true;
                      } catch (Exception $legacyException) {
                          if ($tryForceStatusOnChargesBlock($legacyException->getMessage())) {
                              $statusBypassApplied = true;
                              $usedLegacyReservationUpdate = true;
                          } else {
                              throw $legacyException;
                          }
                      }
                  }
              }
              if ($guestPayloadPresent) {
                  $pdo = pms_get_connection();
                  $stmt = $pdo->prepare('UPDATE reservation r JOIN property p ON p.id_property = r.id_property SET r.id_guest = ? WHERE r.id_reservation = ? AND p.id_company = ?');
                  $stmt->execute(array($guestIdValue, $reservationId, $companyId));
              }
              $noteTextMap = isset($_POST['reservation_note_texts']) && is_array($_POST['reservation_note_texts']) ? $_POST['reservation_note_texts'] : array();
              $noteDeleteMap = isset($_POST['reservation_note_delete']) && is_array($_POST['reservation_note_delete']) ? $_POST['reservation_note_delete'] : array();
              foreach ($noteTextMap as $noteIdKey => $noteTextValue) {
                  $noteId = (int)$noteIdKey;
                  if ($noteId <= 0) {
                      continue;
                  }
                  $deleteFlag = isset($noteDeleteMap[$noteIdKey]) && $noteDeleteMap[$noteIdKey] !== '';
                  $noteText = trim((string)$noteTextValue);
                  if (!$deleteFlag && $noteText === '') {
                      continue;
                  }
                  try {
                      pms_call_procedure('sp_reservation_note_upsert', array(
                          $deleteFlag ? 'delete' : 'update',
                          $noteId,
                          $reservationId,
                          null,
                          $deleteFlag ? null : $noteText,
                          1,
                          $companyCode,
                          $actorUserId
                      ));
                  } catch (Exception $e) {
                      $updateErrors[$reservationId] = $e->getMessage();
                  }
              }
              $updateMessages[$reservationId] = 'Reserva actualizada.';
              if ($usedLegacyReservationUpdate && $reservationCodeInput !== '') {
                  $updateMessages[$reservationId] .= ' El codigo requiere desplegar SP V2 para aplicar cambios.';
              }
              if ($statusBypassApplied) {
                  $updateMessages[$reservationId] .= ' Se aplico ajuste de estatus por validacion directa de cargos.';
              }
              $redirectAfterSaveUrl = reservations_build_return_url($filters, $reservationId);
        } catch (Exception $e) {
            $updateErrors[$reservationId] = $e->getMessage();
        }
    }
  } elseif ($action === 'delete_note') {
      $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
      $noteId = isset($_POST['reservation_note_id']) ? (int)$_POST['reservation_note_id'] : 0;
      if ($reservationId <= 0 || $noteId <= 0) {
          $updateErrors[$reservationId] = 'No se pudo eliminar la nota.';
      } else {
          try {
              pms_call_procedure('sp_reservation_note_upsert', array(
                  'delete',
                  $noteId,
                  $reservationId,
                  null,
                  null,
                  1,
                  $companyCode,
                  $actorUserId
              ));
              $updateMessages[$reservationId] = 'Nota eliminada correctamente.';
              $redirectAfterSaveUrl = reservations_build_return_url($filters, $reservationId);
          } catch (Exception $e) {
              $updateErrors[$reservationId] = $e->getMessage();
          }
      }
  } elseif ($action === 'add_note') {
      $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
      $noteText = isset($_POST['reservation_note_text']) ? trim((string)$_POST['reservation_note_text']) : '';

      if ($reservationId <= 0) {
          $updateErrors[$reservationId] = 'Selecciona una reserva v&aacute;lida.';
      } elseif ($noteText === '') {
          $updateErrors[$reservationId] = 'Escribe una nota antes de guardar.';
      } else {
          try {
              $noteSets = pms_call_procedure('sp_reservation_note_upsert', array(
                  'create',
                  0,
                  $reservationId,
                  null,
                  $noteText,
                  1,
                  $companyCode,
                  $actorUserId
              ));
              $notesOverride[$reservationId] = isset($noteSets[0]) ? $noteSets[0] : array();
              $updateMessages[$reservationId] = 'Nota agregada correctamente.';
              $redirectAfterSaveUrl = reservations_build_return_url($filters, $reservationId);
          } catch (Exception $e) {
              $updateErrors[$reservationId] = $e->getMessage();
          }
      }
}

if ($action === 'create_folio' || $action === 'close_folio' || $action === 'reopen_folio' || $action === 'update_folio' || $action === 'delete_folio') {
    $reservationId = isset($_POST['folio_reservation_id']) ? (int)$_POST['folio_reservation_id'] : 0;
    $folioId = isset($_POST['folio_id']) ? (int)$_POST['folio_id'] : 0;
    $folioName = isset($_POST['folio_name']) ? (string)$_POST['folio_name'] : '';
    $folioDue = isset($_POST['folio_due_date']) && $_POST['folio_due_date'] !== '' ? (string)$_POST['folio_due_date'] : null;
    $folioNotes = isset($_POST['folio_notes']) ? (string)$_POST['folio_notes'] : null;

    $actionCode = 'create';
    if ($action === 'close_folio') {
        $actionCode = 'close';
    } elseif ($action === 'reopen_folio') {
        $actionCode = 'reopen';
    } elseif ($action === 'update_folio') {
        $actionCode = 'update';
    } elseif ($action === 'delete_folio') {
        $actionCode = 'delete';
    }

    if ($reservationId > 0) {
        try {
            pms_call_procedure('sp_folio_upsert', array(
                $actionCode,
                $folioId,
                $reservationId,
                $folioName,
                $folioDue,
                null,
                null,
                $folioNotes,
                'MXN',
                $actorUserId
            ));
            $financeMessages[$reservationId] = $actionCode === 'create' ? 'Folio creado.' : ($actionCode === 'delete' ? 'Folio eliminado.' : 'Folio actualizado.');
            $_POST[$moduleKey . '_subtab_action'] = 'open';
            $_POST[$moduleKey . '_subtab_target'] = 'dynamic:reservation:' . $reservationId;
        } catch (Exception $e) {
            $financeErrors[$reservationId] = $e->getMessage();
        }
    }
}

if ($action === 'remove_visible_folio_taxes') {
    $reservationId = isset($_POST['tax_reservation_id']) ? (int)$_POST['tax_reservation_id'] : 0;
    $targetFolioId = isset($_POST['tax_folio_id']) ? (int)$_POST['tax_folio_id'] : 0;

    if ($reservationId > 0) {
        try {
            $db = pms_get_connection();
            $stmtReservation = $db->prepare(
                'SELECT r.id_reservation
                   FROM reservation r
                   JOIN property p ON p.id_property = r.id_property
                  WHERE r.id_reservation = ?
                    AND p.id_company = ?
                    AND r.deleted_at IS NULL
                  LIMIT 1'
            );
            $stmtReservation->execute(array($reservationId, $companyId));
            if (!$stmtReservation->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception('La reserva no pertenece a la empresa actual.');
            }

            $portalSets = pms_call_procedure('sp_portal_reservation_data', array(
                $companyCode,
                null,
                null,
                null,
                null,
                $reservationId,
                $actorUserId
            ));
            $visibleSaleItems = isset($portalSets[3]) && is_array($portalSets[3]) ? $portalSets[3] : array();

            $visibleTaxIds = array();
            foreach ($visibleSaleItems as $row) {
                if (!reservations_line_item_is_active_for_summary($row, false)) {
                    continue;
                }
                $itemType = strtolower(trim((string)(isset($row['item_type']) ? $row['item_type'] : '')));
                $subcategoryName = strtolower(trim((string)(isset($row['subcategory_name']) ? $row['subcategory_name'] : '')));
                $itemName = strtolower(trim((string)(isset($row['item_name']) ? $row['item_name'] : '')));
                $isTaxLike = ($itemType === 'tax_item');
                if (!$isTaxLike && ($subcategoryName !== '' || $itemName !== '')) {
                    if (strpos($subcategoryName, 'impuesto') !== false
                        || strpos($subcategoryName, 'tax') !== false
                        || strpos($itemName, 'iva') !== false
                        || strpos($itemName, 'ish') !== false
                        || strpos($itemName, 'tax') !== false) {
                        $isTaxLike = true;
                    }
                }
                if (!$isTaxLike) {
                    continue;
                }
                $folioIdFromRow = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
                if ($targetFolioId > 0 && $folioIdFromRow !== $targetFolioId) {
                    continue;
                }
                $saleItemId = isset($row['id_sale_item']) ? (int)$row['id_sale_item'] : 0;
                if ($saleItemId > 0) {
                    $visibleTaxIds[$saleItemId] = $saleItemId;
                }
            }

            if (!$visibleTaxIds) {
                $financeMessages[$reservationId] = $targetFolioId > 0
                    ? 'No hay impuestos visibles para quitar en este folio.'
                    : 'No hay impuestos visibles para quitar en esta reserva.';
            } else {
                $deleteOrder = array();
                foreach ($visibleTaxIds as $rootTaxId) {
                    $descendants = reservations_collect_sale_item_descendants($rootTaxId);
                    if ($descendants) {
                        $descendants = array_reverse(array_values(array_unique(array_map('intval', $descendants))));
                        foreach ($descendants as $descId) {
                            if ($descId > 0 && isset($visibleTaxIds[$descId])) {
                                $deleteOrder[] = $descId;
                            }
                        }
                    }
                    if ((int)$rootTaxId > 0 && isset($visibleTaxIds[(int)$rootTaxId])) {
                        $deleteOrder[] = (int)$rootTaxId;
                    }
                }
                $deleteOrder = array_values(array_unique(array_filter(array_map('intval', $deleteOrder), function ($id) {
                    return $id > 0;
                })));

                $startedTx = false;
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                    $startedTx = true;
                }

                $restoreSkipPercentRecalc = false;
                $previousSkipPercentRecalc = 0;
                try {
                    $skipRow = $db->query('SELECT COALESCE(@pms_skip_percent_derived, 0) AS v')->fetch(PDO::FETCH_ASSOC);
                    if ($skipRow && isset($skipRow['v'])) {
                        $previousSkipPercentRecalc = (int)$skipRow['v'];
                    }
                    $db->exec('SET @pms_skip_percent_derived = 1');
                    $restoreSkipPercentRecalc = true;
                } catch (Exception $ignoreSkipPercent) {
                    $restoreSkipPercentRecalc = false;
                    $previousSkipPercentRecalc = 0;
                }

                $affectedFolioIds = array();
                $deletedCount = 0;
                try {
                    foreach ($deleteOrder as $deleteSaleId) {
                        if (!isset($visibleTaxIds[$deleteSaleId])) {
                            continue;
                        }
                        $snapshot = reservations_fetch_sale_item_snapshot($deleteSaleId);
                        if (!$snapshot) {
                            continue;
                        }
                        $snapshotReservationId = isset($snapshot['id_reservation']) ? (int)$snapshot['id_reservation'] : 0;
                        if ($snapshotReservationId > 0 && $snapshotReservationId !== $reservationId) {
                            continue;
                        }
                        $snapshotFolioId = isset($snapshot['id_folio']) ? (int)$snapshot['id_folio'] : 0;
                        if ($targetFolioId > 0 && $snapshotFolioId !== $targetFolioId) {
                            continue;
                        }
                        $snapshotCatalogId = isset($snapshot['id_line_item_catalog']) ? (int)$snapshot['id_line_item_catalog'] : 0;
                        $snapshotServiceDate = isset($snapshot['service_date']) && $snapshot['service_date'] !== '' ? (string)$snapshot['service_date'] : null;
                        pms_call_procedure('sp_sale_item_upsert', array(
                            'delete',
                            $deleteSaleId,
                            $snapshotFolioId > 0 ? $snapshotFolioId : $targetFolioId,
                            $reservationId,
                            $snapshotCatalogId > 0 ? $snapshotCatalogId : null,
                            null,
                            $snapshotServiceDate,
                            1,
                            null,
                            null,
                            'void',
                            $actorUserId
                        ));
                        if ($snapshotFolioId > 0) {
                            $affectedFolioIds[$snapshotFolioId] = true;
                        }
                        $deletedCount++;
                    }

                    if ($restoreSkipPercentRecalc) {
                        try {
                            $db->exec('SET @pms_skip_percent_derived = ' . (int)$previousSkipPercentRecalc);
                        } catch (Exception $ignoreRestoreSkipPercent) {
                        }
                    }

                    if ($affectedFolioIds) {
                        foreach (array_keys($affectedFolioIds) as $recalcFolioId) {
                            try {
                                pms_call_procedure('sp_folio_recalc', array((int)$recalcFolioId));
                            } catch (Exception $ignoreFolioRecalc) {
                            }
                        }
                    }

                    if ($startedTx && $db->inTransaction()) {
                        $db->commit();
                    }
                } catch (Exception $deleteTaxesError) {
                    if ($restoreSkipPercentRecalc) {
                        try {
                            $db->exec('SET @pms_skip_percent_derived = ' . (int)$previousSkipPercentRecalc);
                        } catch (Exception $ignoreRestoreSkipPercent) {
                        }
                    }
                    if ($startedTx && $db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $deleteTaxesError;
                }

                $financeMessages[$reservationId] = $deletedCount > 0
                    ? ('Impuestos quitados: ' . $deletedCount . '.')
                    : ($targetFolioId > 0
                        ? 'No hay impuestos visibles para quitar en este folio.'
                        : 'No hay impuestos visibles para quitar en esta reserva.');
            }

            $_POST[$moduleKey . '_subtab_action'] = 'open';
            $_POST[$moduleKey . '_subtab_target'] = 'dynamic:reservation:' . $reservationId;
            $clearDirtyTargets[] = 'dynamic:reservation:' . $reservationId;
        } catch (Exception $e) {
            $financeErrors[$reservationId] = $e->getMessage();
        }
    }
}

if ($action === 'create_sale_item' || $action === 'update_sale_item' || $action === 'delete_sale_item') {
    $reservationId = isset($_POST['sale_reservation_id']) ? (int)$_POST['sale_reservation_id'] : 0;
    $folioId = isset($_POST['sale_folio_id']) ? (int)$_POST['sale_folio_id'] : 0;
    $saleId = isset($_POST['sale_item_id']) ? (int)$_POST['sale_item_id'] : 0;
    $catalogId = isset($_POST['sale_catalog_id']) ? (int)$_POST['sale_catalog_id'] : 0;
    $skipPercentRecalcOnDelete = isset($_POST['sale_skip_percent_recalc']) ? (int)$_POST['sale_skip_percent_recalc'] : 0;
    $saleItemTypeRaw = isset($_POST['sale_item_type']) ? strtolower(trim((string)$_POST['sale_item_type'])) : '';
    $allowedLineItemTypes = array('sale_item', 'tax_item', 'payment', 'obligation', 'income');
    $saleItemType = in_array($saleItemTypeRaw, $allowedLineItemTypes, true) ? $saleItemTypeRaw : '';
    $itemDesc = isset($_POST['sale_item_description']) ? (string)$_POST['sale_item_description'] : null;
    $serviceDate = isset($_POST['sale_service_date']) && $_POST['sale_service_date'] !== '' ? (string)$_POST['sale_service_date'] : null;
    $quantity = isset($_POST['sale_quantity']) && $_POST['sale_quantity'] !== '' ? (float)$_POST['sale_quantity'] : 1.0;
    $unitPriceCents = isset($_POST['sale_unit_price']) && $_POST['sale_unit_price'] !== '' ? reservations_to_cents($_POST['sale_unit_price']) : null;
    $discountCents = isset($_POST['sale_discount']) && $_POST['sale_discount'] !== '' ? reservations_to_cents($_POST['sale_discount']) : null;
    $status = isset($_POST['sale_status']) ? (string)$_POST['sale_status'] : 'posted';
    $serviceMarkPaid = isset($_POST['service_mark_paid']) && (string)$_POST['service_mark_paid'] === '1';
    $servicePaymentMethodId = isset($_POST['service_payment_method']) ? (int)$_POST['service_payment_method'] : 0;

    $actionCode = $action === 'create_sale_item' ? 'create' : ($action === 'update_sale_item' ? 'update' : 'delete');

    /* Normalizar datos desde catÃƒÂ¡logo en altas */
    if ($actionCode === 'create' && $catalogId > 0) {
        try {
            $catSets = pms_call_procedure('sp_sale_item_catalog_data', array(
                $companyCode,
                null,
                1,
                $catalogId,
                0
            ));
            $catRow = isset($catSets[1][0]) ? $catSets[1][0] : null;
            if ($catRow) {
                if (($unitPriceCents === null || $unitPriceCents === 0) && isset($catRow['default_unit_price_cents'])) {
                    $unitPriceCents = (int)$catRow['default_unit_price_cents'];
                }
                if ($itemDesc === null && isset($catRow['description'])) {
                    $itemDesc = (string)$catRow['description'];
                }
            }
        } catch (Exception $e) {
            // continuar con valores enviados
        }
    }

    $saleCatalogId = 0;
    $existingLineItemType = 'sale_item';
    $existingQty = null;
    $existingUnitCents = null;
    $existingDiscountCents = null;
    $existingStatus = null;
    $existingServiceDate = null;
    $existingDesc = null;
    if ($saleId > 0) {
        try {
            $db = pms_get_connection();
            $stmt = $db->prepare(
                'SELECT id_line_item_catalog, id_folio, item_type, quantity, unit_price_cents, discount_amount_cents, status, service_date, description
                   FROM line_item
                  WHERE id_line_item = ?
                    AND deleted_at IS NULL
                  LIMIT 1'
            );
            $stmt->execute(array($saleId));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $saleCatalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
                if ($folioId <= 0 && isset($row['id_folio'])) {
                    $folioId = (int)$row['id_folio'];
                }
                if (isset($row['item_type']) && (string)$row['item_type'] !== '') {
                    $existingLineItemType = (string)$row['item_type'];
                }
                $existingQty = isset($row['quantity']) ? (float)$row['quantity'] : null;
                $existingUnitCents = isset($row['unit_price_cents']) ? (int)$row['unit_price_cents'] : null;
                $existingDiscountCents = isset($row['discount_amount_cents']) ? (int)$row['discount_amount_cents'] : null;
                $existingStatus = isset($row['status']) ? (string)$row['status'] : null;
                $existingServiceDate = isset($row['service_date']) ? (string)$row['service_date'] : null;
                $existingDesc = isset($row['description']) ? (string)$row['description'] : null;
            }
        } catch (Exception $e) {
            $saleCatalogId = 0;
        }
    }

    if ($reservationId > 0 && ($folioId > 0 || $saleId > 0)) {
        try {
            $targetCatalogId = $catalogId > 0 ? $catalogId : $saleCatalogId;
            $targetLineItemType = $saleItemType !== '' ? $saleItemType : $existingLineItemType;
            $effectiveServiceDate = $serviceDate !== null ? $serviceDate : $existingServiceDate;
            $propertyCodeForAction = reservations_property_code_for_reservation($companyId, $reservationId);
            $allowedServiceOptions = reservations_service_catalogs_for_property($serviceCatalogsByProperty, $propertyCodeForAction);
            $allowedServiceMap = array();
            $fixedChildrenByParentMap = array();
            $queueAutoPayment = false;
            $queueAutoPaymentCatalogId = 0;
            $queueAutoPaymentAmountCents = 0;
            $queueAutoPaymentServiceDate = '';
            $queueAutoPaymentMethodName = '';
            foreach ($allowedServiceOptions as $serviceOption) {
                $serviceOptionId = isset($serviceOption['id_service_catalog']) ? (int)$serviceOption['id_service_catalog'] : 0;
                if ($serviceOptionId > 0) {
                    $allowedServiceMap[$serviceOptionId] = $serviceOption;
                }
            }

            if ($actionCode === 'create') {
                if ($targetCatalogId <= 0) {
                    throw new Exception('Selecciona un concepto de servicio valido.');
                }
                if (empty($allowedServiceMap)) {
                    throw new Exception('No hay conceptos de servicio configurados para esta propiedad.');
                }
                if (!isset($allowedServiceMap[$targetCatalogId])) {
                    throw new Exception('El concepto seleccionado no esta permitido como servicio para esta propiedad.');
                }
                if (($unitPriceCents === null || $unitPriceCents <= 0) && isset($allowedServiceMap[$targetCatalogId]['default_unit_price_cents'])) {
                    $unitPriceCents = (int)$allowedServiceMap[$targetCatalogId]['default_unit_price_cents'];
                }
                $fixedChildrenByParentMap = reservations_fetch_fixed_children_by_parent($companyCode, $propertyCodeForAction, $companyId);

                if ($serviceMarkPaid) {
                    if ($servicePaymentMethodId <= 0) {
                        throw new Exception('Selecciona un tipo de pago para registrar el servicio como pagado.');
                    }

                    $paymentConceptOptions = reservations_payment_catalogs_for_reservation(
                        $paymentCatalogsByProperty,
                        $propertyCodeForAction,
                        $companyId,
                        $reservationId
                    );
                    $paymentConceptOptionMap = array();
                    foreach ($paymentConceptOptions as $opt) {
                        $optId = isset($opt['id_payment_catalog']) ? (int)$opt['id_payment_catalog'] : 0;
                        if ($optId > 0) {
                            $paymentConceptOptionMap[$optId] = true;
                        }
                    }
                    if (!isset($paymentConceptOptionMap[$servicePaymentMethodId])) {
                        throw new Exception('El tipo de pago seleccionado no esta permitido para esta propiedad.');
                    }

                    $effectiveQtyForPayment = $quantity > 0 ? (float)$quantity : 1.0;
                    $effectiveUnitPriceForPayment = $unitPriceCents !== null ? (int)$unitPriceCents : 0;
                    $effectiveDiscountForPayment = $discountCents !== null ? (int)$discountCents : 0;
                    $rawAmountForPayment = ($effectiveQtyForPayment * (float)$effectiveUnitPriceForPayment) - (float)$effectiveDiscountForPayment;
                    $queueAutoPaymentAmountCents = max(0, (int)round($rawAmountForPayment));
                    if ($queueAutoPaymentAmountCents <= 0) {
                        throw new Exception('No se pudo determinar el monto del pago automatico.');
                    }

                    $queueAutoPaymentCatalogId = $servicePaymentMethodId;
                    $queueAutoPaymentServiceDate = $effectiveServiceDate !== null && trim((string)$effectiveServiceDate) !== ''
                        ? (string)$effectiveServiceDate
                        : date('Y-m-d');
                    $queueAutoPaymentMethodName = reservations_payment_method_name_by_id($paymentCatalogsById, $queueAutoPaymentCatalogId);
                    if ($queueAutoPaymentMethodName === '') {
                        $queueAutoPaymentMethodName = 'Concepto #' . $queueAutoPaymentCatalogId;
                    }
                    $queueAutoPayment = true;
                }
            }

            $replacedPrimaryLodging = false;
            if ($actionCode === 'update'
                && $saleId > 0
                && $saleCatalogId > 0
                && $targetCatalogId > 0
                && $targetCatalogId !== $saleCatalogId) {
                $protectedPrimaryLodgingId = reservations_find_primary_lodging_line_item_id($companyId, $reservationId);
                if ($protectedPrimaryLodgingId > 0 && $saleId === $protectedPrimaryLodgingId) {
                    $lodgingConceptOptionsForProperty = reservations_lodging_concept_options_for_property(
                        $companyCode,
                        $companyId,
                        $propertyCodeForAction
                    );
                    if (!isset($lodgingConceptOptionsForProperty[$targetCatalogId])) {
                        throw new Exception('El concepto seleccionado no esta permitido como hospedaje para esta propiedad.');
                    }

                    $effectiveQtyForReplace = $quantity > 0 ? (float)$quantity : (float)($existingQty !== null ? $existingQty : 0);
                    if ($effectiveQtyForReplace <= 0) {
                        $effectiveQtyForReplace = 1;
                    }
                    $effectiveServiceDateForReplace = $effectiveServiceDate !== null && trim((string)$effectiveServiceDate) !== ''
                        ? (string)$effectiveServiceDate
                        : (trim((string)$existingServiceDate) !== '' ? (string)$existingServiceDate : date('Y-m-d'));
                    $defaultUnitForTarget = isset($lodgingConceptOptionsForProperty[$targetCatalogId]['default_unit_price_cents'])
                        ? (int)$lodgingConceptOptionsForProperty[$targetCatalogId]['default_unit_price_cents']
                        : 0;
                    $effectiveUnitPriceForReplace = $unitPriceCents !== null
                        ? (int)$unitPriceCents
                        : (int)($existingUnitCents !== null ? $existingUnitCents : 0);
                    if ($effectiveUnitPriceForReplace <= 0 && $defaultUnitForTarget > 0) {
                        $effectiveUnitPriceForReplace = $defaultUnitForTarget;
                    }
                    $effectiveDiscountForReplace = $discountCents !== null
                        ? (int)$discountCents
                        : (int)($existingDiscountCents !== null ? $existingDiscountCents : 0);
                    if ($effectiveDiscountForReplace < 0) {
                        $effectiveDiscountForReplace = 0;
                    }
                    $effectiveStatusForReplace = trim((string)$status) !== ''
                        ? (string)$status
                        : (trim((string)$existingStatus) !== '' ? (string)$existingStatus : 'posted');
                    $effectiveDescriptionForReplace = $itemDesc !== null && trim((string)$itemDesc) !== ''
                        ? (string)$itemDesc
                        : ($existingDesc !== null ? (string)$existingDesc : null);

                    $deleteOrder = array();
                    $descendants = reservations_collect_sale_item_descendants($saleId);
                    if ($descendants) {
                        $deleteOrder = array_reverse(array_values(array_unique(array_map('intval', $descendants))));
                    }
                    $deleteOrder[] = $saleId;
                    $deleteOrder = array_values(array_unique(array_map('intval', $deleteOrder)));

                    $db = pms_get_connection();
                    $startedTx = false;
                    if (!$db->inTransaction()) {
                        $db->beginTransaction();
                        $startedTx = true;
                    }
                    $restoreSkipPercentRecalc = false;
                    $previousSkipPercentRecalc = 0;
                    try {
                        try {
                            $skipRow = $db->query('SELECT COALESCE(@pms_skip_percent_derived, 0) AS v')->fetch(PDO::FETCH_ASSOC);
                            if ($skipRow && isset($skipRow['v'])) {
                                $previousSkipPercentRecalc = (int)$skipRow['v'];
                            }
                            $db->exec('SET @pms_skip_percent_derived = 1');
                            $restoreSkipPercentRecalc = true;
                        } catch (Exception $ignoreSkipPercent) {
                            $restoreSkipPercentRecalc = false;
                            $previousSkipPercentRecalc = 0;
                        }

                        foreach ($deleteOrder as $deleteSaleId) {
                            if ($deleteSaleId <= 0) {
                                continue;
                            }
                            $snapshot = reservations_fetch_sale_item_snapshot($deleteSaleId);
                            if (!$snapshot) {
                                continue;
                            }
                            $snapshotReservationId = isset($snapshot['id_reservation']) ? (int)$snapshot['id_reservation'] : 0;
                            if ($snapshotReservationId > 0 && $snapshotReservationId !== $reservationId) {
                                continue;
                            }
                            $snapshotFolioId = isset($snapshot['id_folio']) ? (int)$snapshot['id_folio'] : 0;
                            $snapshotCatalogId = isset($snapshot['id_line_item_catalog']) ? (int)$snapshot['id_line_item_catalog'] : 0;
                            $snapshotServiceDate = isset($snapshot['service_date']) && $snapshot['service_date'] !== '' ? (string)$snapshot['service_date'] : null;
                            pms_call_procedure('sp_sale_item_upsert', array(
                                'delete',
                                $deleteSaleId,
                                $snapshotFolioId > 0 ? $snapshotFolioId : $folioId,
                                $reservationId,
                                $snapshotCatalogId > 0 ? $snapshotCatalogId : null,
                                null,
                                $snapshotServiceDate,
                                1,
                                null,
                                null,
                                'void',
                                $actorUserId
                            ));
                        }

                        if ($restoreSkipPercentRecalc) {
                            try {
                                $db->exec('SET @pms_skip_percent_derived = ' . (int)$previousSkipPercentRecalc);
                            } catch (Exception $ignoreRestoreSkipPercent) {
                            }
                        }
                        if ($startedTx && $db->inTransaction()) {
                            $db->commit();
                        }
                    } catch (Exception $cascadeReplaceDeleteError) {
                        if ($restoreSkipPercentRecalc) {
                            try {
                                $db->exec('SET @pms_skip_percent_derived = ' . (int)$previousSkipPercentRecalc);
                            } catch (Exception $ignoreRestoreSkipPercent) {
                            }
                        }
                        if ($startedTx && $db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $cascadeReplaceDeleteError;
                    }

                    pms_call_procedure('sp_sale_item_upsert', array(
                        'create',
                        0,
                        $folioId,
                        $reservationId,
                        $targetCatalogId,
                        $effectiveDescriptionForReplace,
                        $effectiveServiceDateForReplace,
                        $effectiveQtyForReplace,
                        $effectiveUnitPriceForReplace,
                        $effectiveDiscountForReplace,
                        $effectiveStatusForReplace,
                        $actorUserId
                    ));
                    if (empty($fixedChildrenByParentMap)) {
                        $fixedChildrenByParentMap = reservations_fetch_fixed_children_by_parent($companyCode, $propertyCodeForAction, $companyId);
                    }
                    $fixedPath = array();
                    reservations_upsert_fixed_children_tree(
                        $reservationId,
                        $folioId,
                        $effectiveServiceDateForReplace,
                        $actorUserId,
                        $targetCatalogId,
                        $fixedChildrenByParentMap,
                        $fixedPath,
                        0
                    );
                    reservations_recalc_derived_tree_for_catalog(
                        $folioId,
                        $reservationId,
                        $targetCatalogId,
                        $effectiveServiceDateForReplace,
                        $actorUserId
                    );
                    try {
                        pms_call_procedure('sp_folio_recalc', array($folioId));
                    } catch (Exception $ignoreFolioRecalc) {
                    }
                    reservations_sync_reservation_origin_from_lodging(
                        $companyCode,
                        $companyId,
                        $reservationId,
                        $targetCatalogId,
                        $actorUserId
                    );
                    $replacedPrimaryLodging = true;
                }
            }

	            if (!$replacedPrimaryLodging && $actionCode === 'delete' && $saleId > 0) {
	                $rootSnapshot = reservations_fetch_sale_item_snapshot($saleId);
	                if (!$rootSnapshot) {
	                    throw new Exception('No se encontro el line item a eliminar.');
	                }
	                $protectedPrimaryLodgingId = reservations_find_primary_lodging_line_item_id($companyId, $reservationId);
	                if ($protectedPrimaryLodgingId > 0 && $saleId === $protectedPrimaryLodgingId) {
	                    throw new Exception('El hospedaje principal no se puede eliminar. Usa "Cambiar tipo de hospedaje".');
	                }
	                $rootReservationId = isset($rootSnapshot['id_reservation']) ? (int)$rootSnapshot['id_reservation'] : 0;
	                if ($rootReservationId > 0 && $rootReservationId !== $reservationId) {
	                    throw new Exception('El line item no pertenece a la reserva seleccionada.');
	                }
                if ($folioId <= 0 && isset($rootSnapshot['id_folio'])) {
                    $folioId = (int)$rootSnapshot['id_folio'];
                }
                $deleteOrder = array();
                $descendants = reservations_collect_sale_item_descendants($saleId);
                if ($descendants) {
                    $deleteOrder = array_reverse(array_values(array_unique(array_map('intval', $descendants))));
                }
                $deleteOrder[] = $saleId;
                $deleteOrder = array_values(array_unique(array_map('intval', $deleteOrder)));

                $db = pms_get_connection();
                $startedTx = false;
                if (!$db->inTransaction()) {
                    $db->beginTransaction();
                    $startedTx = true;
                }
                $restoreSkipPercentRecalc = false;
                $previousSkipPercentRecalc = 0;
                if ($skipPercentRecalcOnDelete === 1) {
                    try {
                        $skipRow = $db->query('SELECT COALESCE(@pms_skip_percent_derived, 0) AS v')->fetch(PDO::FETCH_ASSOC);
                        if ($skipRow && isset($skipRow['v'])) {
                            $previousSkipPercentRecalc = (int)$skipRow['v'];
                        }
                        $db->exec('SET @pms_skip_percent_derived = 1');
                        $restoreSkipPercentRecalc = true;
                    } catch (Exception $ignoreSkipPercent) {
                        $restoreSkipPercentRecalc = false;
                        $previousSkipPercentRecalc = 0;
                    }
                }
                try {
                    foreach ($deleteOrder as $deleteSaleId) {
                        if ($deleteSaleId <= 0) {
                            continue;
                        }
                        $snapshot = reservations_fetch_sale_item_snapshot($deleteSaleId);
                        if (!$snapshot) {
                            continue;
                        }
                        $snapshotReservationId = isset($snapshot['id_reservation']) ? (int)$snapshot['id_reservation'] : 0;
                        if ($snapshotReservationId > 0 && $snapshotReservationId !== $reservationId) {
                            continue;
                        }
                        $snapshotFolioId = isset($snapshot['id_folio']) ? (int)$snapshot['id_folio'] : 0;
                        $snapshotCatalogId = isset($snapshot['id_line_item_catalog']) ? (int)$snapshot['id_line_item_catalog'] : 0;
                        $snapshotServiceDate = isset($snapshot['service_date']) && $snapshot['service_date'] !== '' ? (string)$snapshot['service_date'] : null;
                        pms_call_procedure('sp_sale_item_upsert', array(
                            'delete',
                            $deleteSaleId,
                            $snapshotFolioId > 0 ? $snapshotFolioId : $folioId,
                            $reservationId,
                            $snapshotCatalogId > 0 ? $snapshotCatalogId : null,
                            null,
                            $snapshotServiceDate,
                            1,
                            null,
                            null,
                            'void',
                            $actorUserId
                        ));
                    }
                    if ($restoreSkipPercentRecalc) {
                        try {
                            $db->exec('SET @pms_skip_percent_derived = ' . (int)$previousSkipPercentRecalc);
                        } catch (Exception $ignoreRestoreSkipPercent) {
                        }
                    }
                    if ($startedTx && $db->inTransaction()) {
                        $db->commit();
                    }
                } catch (Exception $cascadeDeleteError) {
                    if ($restoreSkipPercentRecalc) {
                        try {
                            $db->exec('SET @pms_skip_percent_derived = ' . (int)$previousSkipPercentRecalc);
                        } catch (Exception $ignoreRestoreSkipPercent) {
                        }
                    }
                    if ($startedTx && $db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $cascadeDeleteError;
                }
            } elseif (!$replacedPrimaryLodging) {
                pms_call_procedure('sp_sale_item_upsert', array(
                    $actionCode,
                    $saleId,
                    $folioId,
                    $reservationId,
                    $targetCatalogId > 0 ? $targetCatalogId : null,
                    $itemDesc,
                    $serviceDate,
                    $quantity,
                    $unitPriceCents,
                    $discountCents,
                    $status,
                    $actorUserId
                ));
                if (($actionCode === 'create' || $actionCode === 'update') && $targetCatalogId > 0 && $folioId > 0) {
                    if (empty($fixedChildrenByParentMap)) {
                        $fixedChildrenByParentMap = reservations_fetch_fixed_children_by_parent($companyCode, $propertyCodeForAction, $companyId);
                    }
                    $fixedPath = array();
                    reservations_upsert_fixed_children_tree(
                        $reservationId,
                        $folioId,
                        $effectiveServiceDate,
                        $actorUserId,
                        $targetCatalogId,
                        $fixedChildrenByParentMap,
                        $fixedPath,
                        0
                    );
                    reservations_recalc_derived_tree_for_catalog(
                        $folioId,
                        $reservationId,
                        $targetCatalogId,
                        $effectiveServiceDate,
                        $actorUserId
                    );
                }
                if ($actionCode === 'update' && $saleId > 0 && $targetLineItemType !== '' && $targetLineItemType !== $existingLineItemType) {
                    pms_call_procedure('sp_line_item_type_upsert', array(
                        'update',
                        $saleId,
                        $companyCode,
                        $targetLineItemType,
                        $actorUserId
                    ));
                }
                if ($actionCode === 'update' && $saleId > 0 && $folioId > 0) {
                    $recalcTargets = array();
                    if ($saleCatalogId > 0) {
                        $recalcTargets[$saleCatalogId . '|' . (string)$existingServiceDate] = array(
                            'catalog_id' => $saleCatalogId,
                            'service_date' => $existingServiceDate
                        );
                    }
                    if ($targetCatalogId > 0) {
                        $recalcTargets[$targetCatalogId . '|' . (string)$effectiveServiceDate] = array(
                            'catalog_id' => $targetCatalogId,
                            'service_date' => $effectiveServiceDate
                        );
                    }
                    foreach ($recalcTargets as $recalcTarget) {
                        reservations_recalc_derived_tree_for_catalog(
                            $folioId,
                            $reservationId,
                            isset($recalcTarget['catalog_id']) ? (int)$recalcTarget['catalog_id'] : 0,
                            isset($recalcTarget['service_date']) ? $recalcTarget['service_date'] : null,
                            $actorUserId
                        );
                    }
                }
                if ($actionCode === 'create' && $queueAutoPayment && $folioId > 0) {
                    reservations_create_payment_line_item(
                        $folioId,
                        $reservationId,
                        $queueAutoPaymentCatalogId,
                        $queueAutoPaymentServiceDate,
                        $queueAutoPaymentAmountCents,
                        'captured',
                        $actorUserId,
                        $queueAutoPaymentMethodName,
                        ''
                    );
                }
            }

            if ($replacedPrimaryLodging) {
                $financeMessages[$reservationId] = 'Tipo de hospedaje actualizado y folio recargado.';
            } else {
                $financeMessages[$reservationId] = $actionCode === 'create'
                    ? 'Servicio agregado.'
                    : ($actionCode === 'update'
                        ? 'Servicio actualizado y derivados recalculados.'
                        : ($skipPercentRecalcOnDelete === 1 ? 'Derivado eliminado.' : 'Servicio eliminado junto con sus derivados.'));
            }
            $_POST[$moduleKey . '_subtab_action'] = 'open';
            $_POST[$moduleKey . '_subtab_target'] = 'dynamic:reservation:' . $reservationId;
            $clearDirtyTargets[] = 'dynamic:reservation:' . $reservationId;
        } catch (Exception $e) {
            $financeErrors[$reservationId] = $e->getMessage();
        }
    }
}

if ($action === 'create_payment' || $action === 'update_payment' || $action === 'delete_payment') {
    $reservationId = isset($_POST['payment_reservation_id']) ? (int)$_POST['payment_reservation_id'] : 0;
    $folioId = isset($_POST['payment_folio_id']) ? (int)$_POST['payment_folio_id'] : 0;
    $paymentId = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
    $method = isset($_POST['payment_method']) ? (int)$_POST['payment_method'] : 0;
    $amountCents = reservations_to_cents(isset($_POST['payment_amount']) ? $_POST['payment_amount'] : 0);
    $reference = isset($_POST['payment_reference']) ? (string)$_POST['payment_reference'] : '';
    $serviceDate = isset($_POST['payment_service_date']) ? (string)$_POST['payment_service_date'] : '';
    $status = isset($_POST['payment_status']) ? (string)$_POST['payment_status'] : 'captured';
    $transferRemaining = isset($_POST['payment_transfer_remaining']) && (string)$_POST['payment_transfer_remaining'] === '1';
    $transferTargetFolioId = isset($_POST['payment_transfer_target_folio_id']) ? (int)$_POST['payment_transfer_target_folio_id'] : 0;

    $actionCode = $action === 'create_payment' ? 'create' : ($action === 'update_payment' ? 'update' : 'delete');

    if ($reservationId > 0 && ($folioId > 0 || $paymentId > 0)) {
        try {
            $propertyCodeForReservation = reservations_property_code_for_reservation($companyId, $reservationId);
            $paymentConceptOptions = reservations_payment_catalogs_for_reservation(
                $paymentCatalogsByProperty,
                $propertyCodeForReservation,
                $companyId,
                $reservationId
            );
            $paymentConceptOptionMap = array();
            foreach ($paymentConceptOptions as $opt) {
                $optId = isset($opt['id_payment_catalog']) ? (int)$opt['id_payment_catalog'] : 0;
                if ($optId > 0) {
                    $paymentConceptOptionMap[$optId] = true;
                }
            }
            $methodName = reservations_payment_method_name_by_id($paymentCatalogsById, $method);
            if ($methodName === '' && $method > 0) {
                $methodName = 'Concepto #' . $method;
            }
            $selectedPaymentCatalogId = $method > 0 ? (int)$method : 0;
            if ($selectedPaymentCatalogId > 0 && !empty($paymentConceptOptionMap) && !isset($paymentConceptOptionMap[$selectedPaymentCatalogId])) {
                $selectedPaymentCatalogId = 0;
            }
            $effectiveDate = trim($serviceDate) !== '' ? $serviceDate : date('Y-m-d');
            $effectiveStatus = trim($status) !== '' ? $status : 'captured';
            $createSplitApplied = false;
            $createSplitTargetFolioId = 0;

            if ($actionCode === 'create') {
                if ($folioId <= 0) {
                    throw new Exception('Selecciona un folio valido para registrar el pago.');
                }
                if ($amountCents <= 0) {
                    throw new Exception('El monto del pago debe ser mayor a 0.');
                }

                $fallbackPaymentCatalogId = 0;
                if (!empty($paymentConceptOptions)) {
                    $fallbackPaymentCatalogId = isset($paymentConceptOptions[0]['id_payment_catalog'])
                        ? (int)$paymentConceptOptions[0]['id_payment_catalog']
                        : 0;
                }
                $paymentCatalogId = $selectedPaymentCatalogId > 0
                    ? $selectedPaymentCatalogId
                    : $fallbackPaymentCatalogId;
                if ($paymentCatalogId <= 0) {
                    throw new Exception('No hay concepto de pago configurado para esta propiedad.');
                }
                $transferCtx = reservations_resolve_payment_transfer_target(
                    $companyId,
                    $reservationId,
                    $folioId,
                    $transferTargetFolioId
                );
                $sourceBalanceCents = isset($transferCtx['source_balance_cents']) ? max(0, (int)$transferCtx['source_balance_cents']) : 0;
                $remainingCents = max(0, $amountCents - $sourceBalanceCents);
                $targetFolioForTransfer = isset($transferCtx['target_folio_id']) ? (int)$transferCtx['target_folio_id'] : 0;
                $targetPendingCents = isset($transferCtx['target_balance_cents']) ? max(0, (int)$transferCtx['target_balance_cents']) : 0;

                if ($transferRemaining && $remainingCents > 0) {
                    if ($targetFolioForTransfer <= 0 || $targetPendingCents <= 0) {
                        throw new Exception('Ya no hay saldo pendiente en otro folio para transferir el restante.');
                    }
                    $sourcePaymentCents = max(0, $amountCents - $remainingCents);
                    if ($sourcePaymentCents > 0) {
                        reservations_create_payment_line_item(
                            $folioId,
                            $reservationId,
                            $paymentCatalogId,
                            $effectiveDate,
                            $sourcePaymentCents,
                            $effectiveStatus,
                            $actorUserId,
                            $methodName,
                            $reference
                        );
                    }
                    reservations_create_payment_line_item(
                        $targetFolioForTransfer,
                        $reservationId,
                        $paymentCatalogId,
                        $effectiveDate,
                        $remainingCents,
                        $effectiveStatus,
                        $actorUserId,
                        $methodName,
                        $reference
                    );
                    $createSplitApplied = true;
                    $createSplitTargetFolioId = $targetFolioForTransfer;
                } else {
                    reservations_create_payment_line_item(
                        $folioId,
                        $reservationId,
                        $paymentCatalogId,
                        $effectiveDate,
                        $amountCents,
                        $effectiveStatus,
                        $actorUserId,
                        $methodName,
                        $reference
                    );
                }
            } elseif ($actionCode === 'update') {
                if ($paymentId <= 0) {
                    throw new Exception('Selecciona un pago valido para actualizar.');
                }
                if ($amountCents <= 0) {
                    throw new Exception('El monto del pago debe ser mayor a 0.');
                }

                if ($folioId <= 0) {
                    $snapshot = reservations_fetch_sale_item_snapshot($paymentId);
                    if ($snapshot && isset($snapshot['id_folio'])) {
                        $folioId = (int)$snapshot['id_folio'];
                    }
                }
                if ($folioId <= 0) {
                    throw new Exception('No se pudo determinar el folio del pago.');
                }
                $paymentCatalogForUpdate = $selectedPaymentCatalogId > 0 ? $selectedPaymentCatalogId : 0;

                pms_call_procedure('sp_sale_item_upsert', array(
                    'update',
                    $paymentId,
                    $folioId,
                    $reservationId,
                    $paymentCatalogForUpdate,
                    null,
                    $effectiveDate,
                    1,
                    $amountCents,
                    0,
                    $effectiveStatus,
                    $actorUserId
                ));

                pms_call_procedure('sp_line_item_payment_meta_upsert', array(
                    $paymentId,
                    $methodName !== '' ? $methodName : null,
                    trim($reference) !== '' ? $reference : null,
                    $effectiveStatus,
                    $actorUserId
                ));
            } else {
                if ($paymentId <= 0) {
                    throw new Exception('Selecciona un pago valido para eliminar.');
                }
                pms_call_procedure('sp_sale_item_upsert', array(
                    'delete',
                    $paymentId,
                    0,
                    $reservationId,
                    0,
                    null,
                    null,
                    1,
                    null,
                    0,
                    'void',
                    $actorUserId
                ));
            }

            $financeMessages[$reservationId] = $actionCode === 'create'
                ? ($createSplitApplied
                    ? ('Pago agregado y restante transferido al folio #' . $createSplitTargetFolioId . '.')
                    : 'Pago agregado.')
                : ($actionCode === 'update' ? 'Pago actualizado.' : 'Pago eliminado.');
            $_POST[$moduleKey . '_subtab_action'] = 'open';
            $_POST[$moduleKey . '_subtab_target'] = 'dynamic:reservation:' . $reservationId;
            $clearDirtyTargets[] = 'dynamic:reservation:' . $reservationId;
        } catch (Exception $e) {
            $financeErrors[$reservationId] = $e->getMessage();
        }
    }
}

if ($action === 'create_refund' || $action === 'delete_refund') {
    $reservationId = isset($_POST['refund_reservation_id']) ? (int)$_POST['refund_reservation_id'] : 0;
    $paymentId = isset($_POST['refund_payment_id']) ? (int)$_POST['refund_payment_id'] : 0;
    $refundId = isset($_POST['refund_id']) ? (int)$_POST['refund_id'] : 0;
    $amountCents = reservations_to_cents(isset($_POST['refund_amount']) ? $_POST['refund_amount'] : 0);
    $reason = isset($_POST['refund_reason']) ? (string)$_POST['refund_reason'] : null;
    $reference = isset($_POST['refund_reference']) ? (string)$_POST['refund_reference'] : null;

    $actionCode = $action === 'create_refund' ? 'create' : 'delete';

    if ($reservationId > 0 && ($paymentId > 0 || $refundId > 0)) {
        try {
            pms_call_procedure('sp_refund_upsert', array(
                $actionCode,
                $refundId,
                $paymentId,
                $amountCents,
                $reason,
                $reference,
                $actorUserId
            ));
            $financeMessages[$reservationId] = $actionCode === 'create' ? 'Reembolso registrado.' : 'Reembolso eliminado.';
            $_POST[$moduleKey . '_subtab_action'] = 'open';
            $_POST[$moduleKey . '_subtab_target'] = 'dynamic:reservation:' . $reservationId;
            $clearDirtyTargets[] = 'dynamic:reservation:' . $reservationId;
        } catch (Exception $e) {
            $financeErrors[$reservationId] = $e->getMessage();
        }
    }
}

if ($action === 'add_interest' || $action === 'remove_interest') {
    $reservationId = isset($_POST['interest_reservation_id']) ? (int)$_POST['interest_reservation_id'] : 0;
    $catalogId = isset($_POST['interest_catalog_id']) ? (int)$_POST['interest_catalog_id'] : 0;
    $actionLabel = $action === 'add_interest' ? 'agregar' : 'quitar';
    if ($reservationId <= 0 || $catalogId <= 0) {
        $interestErrors[$reservationId ?: 0] = 'Selecciona un concepto valido para ' . $actionLabel . '.';
    } else {
        try {
            $db = pms_get_connection();
            $stmt = $db->prepare(
                'SELECT r.id_reservation, r.id_property
                 FROM reservation r
                 JOIN property p ON p.id_property = r.id_property
                 WHERE r.id_reservation = ? AND p.id_company = ? AND r.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($reservationId, $companyId));
            $resRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$resRow) {
                $interestErrors[$reservationId] = 'Reserva no encontrada para registrar intereses.';
            } else {
                $stmt = $db->prepare(
                    'SELECT sic.id_line_item_catalog AS id_sale_item_catalog
                     FROM line_item_catalog sic
                     JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
                     JOIN pms_settings_interest_catalog psic
                       ON psic.id_company = cat.id_company
                      AND psic.id_sale_item_catalog = sic.id_line_item_catalog
                      AND psic.deleted_at IS NULL
                      AND psic.is_active = 1
                      AND (psic.id_property IS NULL OR psic.id_property = ?)
                     WHERE sic.catalog_type = "sale_item" AND sic.id_line_item_catalog = ? AND cat.id_company = ? AND sic.deleted_at IS NULL AND cat.deleted_at IS NULL
                     LIMIT 1'
                );
                $stmt->execute(array((int)$resRow['id_property'], $catalogId, $companyId));
                $catRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$catRow) {
                    $interestErrors[$reservationId] = 'El concepto seleccionado no esta permitido para intereses en esta propiedad.';
                } else {
                    if ($action === 'add_interest') {
                        $stmt = $db->prepare(
                            'INSERT INTO reservation_interest (id_reservation, id_sale_item_catalog, is_active, deleted_at, created_by)
                             VALUES (?, ?, 1, NULL, ?)
                             ON DUPLICATE KEY UPDATE is_active = 1, deleted_at = NULL, updated_at = NOW()'
                        );
                        $stmt->execute(array($reservationId, $catalogId, $actorUserId));
                        $interestMessages[$reservationId] = 'Interes agregado a la reserva.';
                    } else {
                        $stmt = $db->prepare(
                            'UPDATE reservation_interest
                             SET is_active = 0, deleted_at = NOW(), updated_at = NOW()
                             WHERE id_reservation = ? AND id_sale_item_catalog = ?'
                        );
                        $stmt->execute(array($reservationId, $catalogId));
                        $interestMessages[$reservationId] = 'Interes removido de la reserva.';
                    }
                    $_POST[$moduleKey . '_subtab_action'] = 'open';
                    $_POST[$moduleKey . '_subtab_target'] = 'dynamic:reservation:' . $reservationId;
                }
            }
        } catch (Exception $e) {
            $interestErrors[$reservationId] = $e->getMessage();
        }
    }
}

if ($redirectAfterSaveUrl === '' && $returnView === 'calendar') {
    $calendarReturnActions = array(
        'create_reservation',
        'confirm_reservation',
        'update_reservation',
        'delete_note',
        'add_note',
        'create_folio',
        'close_folio',
        'reopen_folio',
        'update_folio',
        'delete_folio',
        'remove_visible_folio_taxes',
        'create_sale_item',
        'update_sale_item',
        'delete_sale_item',
        'create_payment',
        'update_payment',
        'delete_payment',
        'create_refund',
        'delete_refund',
        'add_interest',
        'remove_interest'
    );
    $hasPostActionErrors = $globalError !== null
        || $newReservationError !== null
        || !empty($updateErrors)
        || !empty($financeErrors)
        || !empty($interestErrors);
    if (in_array($action, $calendarReturnActions, true) && !$hasPostActionErrors) {
        $actionReservationId = reservations_extract_action_reservation_id();
        $redirectAfterSaveUrl = reservations_build_return_url($filters, $actionReservationId);
    }
}

if ($redirectAfterSaveUrl !== '') {
    if (!headers_sent()) {
        header('Location: ' . $redirectAfterSaveUrl);
        exit;
    }
    echo '<script>window.location.replace('
        . json_encode($redirectAfterSaveUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . ');</script>';
    return;
}

if (!isset($_POST[$moduleKey . '_subtab_action']) && isset($_GET['open_reservation'])) {
    $openReservationId = (int)$_GET['open_reservation'];
    if ($openReservationId > 0) {
        $_POST[$moduleKey . '_subtab_action'] = 'open';
        $_POST[$moduleKey . '_subtab_target'] = 'reservation:' . $openReservationId;
    }
}

$subtabState = pms_subtabs_init($moduleKey);

if ($clearDirtyTargets) {
    foreach ($clearDirtyTargets as $targetKey) {
        pms_subtabs_clear_dirty($moduleKey, $targetKey);
    }
    $subtabState = $_SESSION['pms_subtabs'][$moduleKey];
}

/* Si se pidiÃƒÂ³ abrir una pestaÃƒÂ±a, forzarla como activa y agregarla a open */
$postedAction = isset($_POST[$moduleKey . '_subtab_action']) ? (string)$_POST[$moduleKey . '_subtab_action'] : '';
$postedTargetRaw = isset($_POST[$moduleKey . '_subtab_target']) ? (string)$_POST[$moduleKey . '_subtab_target'] : '';
if ($postedAction === 'open' && $postedTargetRaw !== '') {
    $postedTarget = (strpos($postedTargetRaw, 'dynamic:') === 0) ? substr($postedTargetRaw, strlen('dynamic:')) : $postedTargetRaw;
    if (!in_array($postedTarget, $subtabState['open'], true)) {
        $subtabState['open'][] = $postedTarget;
        $_SESSION['pms_subtabs'][$moduleKey]['open'][] = $postedTarget;
    }
    $subtabState['active'] = 'dynamic:' . $postedTarget;
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'dynamic:' . $postedTarget;
} elseif ($postedAction === 'close' && $postedTargetRaw !== '') {
    $postedTarget = (strpos($postedTargetRaw, 'dynamic:') === 0) ? substr($postedTargetRaw, strlen('dynamic:')) : $postedTargetRaw;
    $subtabState['open'] = array_values(array_filter($subtabState['open'], function ($key) use ($postedTarget) {
        return $key !== $postedTarget;
    }));
    $_SESSION['pms_subtabs'][$moduleKey]['open'] = $subtabState['open'];
    $subtabState['active'] = 'static:general';
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'static:general';
}

/* Normaliza pestaÃƒÂ±a activa para evitar quedarse en tabs inexistentes (ej. static:new removida) */
$activeKey = isset($subtabState['active']) ? (string)$subtabState['active'] : 'static:general';

/* Si llegamos desde el calendario pidiendo abrir la pestaÃƒÂ±a de nueva reserva */
if (isset($_POST['reservations_current_subtab']) && $_POST['reservations_current_subtab'] === 'static:new') {
    if (!in_array('new', $subtabState['open'], true)) {
        $subtabState['open'][] = 'new';
        $_SESSION['pms_subtabs'][$moduleKey]['open'][] = 'new';
    }
    $activeKey = 'dynamic:new';
    $subtabState['active'] = $activeKey;
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = $activeKey;
}

$openReservationIds = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'reservation:') === 0) {
        $resId = (int)substr($openKey, strlen('reservation:'));
        if ($resId > 0 && !in_array($resId, $openReservationIds, true)) {
            $openReservationIds[] = $resId;
        }
    }
}
if (strpos($activeKey, 'dynamic:reservation:') === 0) {
    $activeReservationId = (int)substr($activeKey, strlen('dynamic:reservation:'));
    if ($activeReservationId > 0) {
        $openReservationIds = array($activeReservationId);
    }
}

$reservationsList = array();
try {
    $generalSets = pms_call_procedure('sp_portal_reservation_data', array(
        $companyCode,
        $filters['property_code'] === '' ? null : $filters['property_code'],
        null,
        $filters['from'] === '' ? null : $filters['from'],
        $filters['to'] === '' ? null : $filters['to'],
        0,
        $actorUserId
    ));
    $reservationsList = isset($generalSets[0]) ? $generalSets[0] : array();
    if ($selectedStatusFilterSet) {
        $reservationsList = array_values(array_filter($reservationsList, function ($row) use ($selectedStatusFilterSet) {
            $rowStatus = isset($row['status']) ? $row['status'] : '';
            $normalized = reservations_status_normalize_for_filter($rowStatus);
            return isset($selectedStatusFilterSet[$normalized]);
        }));
    }
    foreach ($reservationsList as $idx => $row) {
        $checkInRaw = isset($row['check_in_date']) ? (string)$row['check_in_date'] : '';
        $checkOutRaw = isset($row['check_out_date']) ? (string)$row['check_out_date'] : '';
        $guestDisplay = trim(
            (isset($row['guest_names']) ? (string)$row['guest_names'] : '')
            . ' '
            . (isset($row['guest_last_name']) ? (string)$row['guest_last_name'] : '')
        );
        if ($guestDisplay === '') {
            $guestDisplay = trim((string)(isset($row['guest_email']) ? $row['guest_email'] : ''));
        }
        $reservationsList[$idx]['check_in_display'] = reservations_format_date($checkInRaw, 'd/m/Y');
        $reservationsList[$idx]['check_out_display'] = reservations_format_date($checkOutRaw, 'd/m/Y');
        $reservationsList[$idx]['guest_display'] = $guestDisplay;
    }
} catch (Exception $e) {
    $globalError = $globalError ? $globalError : $e->getMessage();
    $reservationsList = array();
}

$openNewTab = in_array('new', isset($subtabState['open']) ? $subtabState['open'] : array(), true);
$reservationDetails = array();
foreach ($openReservationIds as $reservationId) {
    try {
        $detailSets = pms_call_procedure('sp_portal_reservation_data', array(
            $companyCode,
            null,
            null,
            null,
            null,
            $reservationId,
            $actorUserId
        ));
        $detailRow = isset($detailSets[1][0]) ? $detailSets[1][0] : null;
        if (!$detailRow) {
            throw new Exception('No tienes acceso a la reservacion solicitada.');
        }
        $folios = isset($detailSets[2]) ? $detailSets[2] : array();
        $saleItems = isset($detailSets[3]) ? $detailSets[3] : array();
        $taxItems = isset($detailSets[4]) ? $detailSets[4] : array();
        $payments = isset($detailSets[5]) ? $detailSets[5] : array();
        $refunds = isset($detailSets[6]) ? $detailSets[6] : array();
        $activities = isset($detailSets[7]) ? $detailSets[7] : array();
        $interests = isset($detailSets[8]) ? $detailSets[8] : array();

        if (!$saleItems && $folios) {
            $folioIds = array();
            foreach ($folios as $folioRow) {
                $fid = isset($folioRow['id_folio']) ? (int)$folioRow['id_folio'] : 0;
                if ($fid > 0) {
                    $folioIds[] = $fid;
                }
            }
            $folioIds = array_values(array_unique($folioIds));
            if ($folioIds) {
                try {
                    $db = pms_get_connection();
                    $placeholders = implode(',', array_fill(0, count($folioIds), '?'));
                    $stmt = $db->prepare(
                        'SELECT
                            si.id_line_item AS id_sale_item,
                            CAST(NULL AS SIGNED) AS id_parent_sale_item,
                            si.id_line_item_catalog AS id_sale_item_catalog,
                            si.item_type,
                            CAST(NULL AS SIGNED) AS parent_sale_item_catalog_id,
                            CAST(0 AS SIGNED) AS add_to_father_total,
                            COALESCE(sic.show_in_folio,1) AS show_in_folio,
                            CAST(NULL AS SIGNED) AS show_in_folio_relation,
                            COALESCE(sic.show_in_folio,1) AS show_in_folio_effective,
                            si.id_folio,
                            f.folio_name,
                            cat.category_name AS subcategory_name,
                            sic.item_name,
                            si.description,
                            si.service_date,
                            si.quantity,
                            si.unit_price_cents,
                            si.discount_amount_cents,
                            CAST(0 AS SIGNED) AS tax_amount_cents,
                            si.amount_cents,
                            si.currency,
                            si.status,
                            si.created_at
                         FROM line_item si
                         JOIN folio f ON f.id_folio = si.id_folio
                         LEFT JOIN line_item_catalog sic
                           ON sic.id_line_item_catalog = si.id_line_item_catalog
                         LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
                         WHERE si.item_type IN (\'sale_item\',\'tax_item\',\'obligation\',\'income\')
                           AND si.deleted_at IS NULL
                           AND COALESCE(si.is_active, 1) = 1
                           AND si.id_folio IN (' . $placeholders . ')
                         ORDER BY si.service_date, si.id_line_item'
                    );
                    $stmt->execute($folioIds);
                    $saleItems = $stmt->fetchAll();
                } catch (Exception $e) {
                    $saleItems = array();
                }
            }
        }
        if ($folios) {
            $folioIds = array();
            foreach ($folios as $folioRow) {
                $fid = isset($folioRow['id_folio']) ? (int)$folioRow['id_folio'] : 0;
                if ($fid > 0) {
                    $folioIds[] = $fid;
                }
            }
            $folioIds = array_values(array_unique($folioIds));
            if ($folioIds) {
                try {
                    $db = pms_get_connection();
                    $placeholders = implode(',', array_fill(0, count($folioIds), '?'));
                    $stmt = $db->prepare(
                        'SELECT
                            si.id_line_item AS id_sale_item,
                            CAST(NULL AS SIGNED) AS id_parent_sale_item,
                            si.id_line_item_catalog AS id_sale_item_catalog,
                            si.item_type,
                            (
                              SELECT MIN(lcp.id_parent_sale_item_catalog)
                              FROM line_item_catalog_parent lcp
                              WHERE lcp.id_sale_item_catalog = si.id_line_item_catalog
                                AND lcp.deleted_at IS NULL
                                AND COALESCE(lcp.is_active, 1) = 1
                            ) AS parent_sale_item_catalog_id,
                            (
                              SELECT MIN(COALESCE(lcp.add_to_father_total, 0))
                              FROM line_item_catalog_parent lcp
                              WHERE lcp.id_sale_item_catalog = si.id_line_item_catalog
                                AND lcp.deleted_at IS NULL
                                AND COALESCE(lcp.is_active, 1) = 1
                            ) AS add_to_father_total,
                            (
                              SELECT MIN(lcp.show_in_folio_relation)
                              FROM line_item_catalog_parent lcp
                              WHERE lcp.id_sale_item_catalog = si.id_line_item_catalog
                                AND lcp.deleted_at IS NULL
                                AND COALESCE(lcp.is_active, 1) = 1
                            ) AS show_in_folio_relation,
                            COALESCE(
                              (
                                SELECT MIN(lcp.show_in_folio_relation)
                                FROM line_item_catalog_parent lcp
                                WHERE lcp.id_sale_item_catalog = si.id_line_item_catalog
                                  AND lcp.deleted_at IS NULL
                                  AND COALESCE(lcp.is_active, 1) = 1
                              ),
                              sic.show_in_folio,
                              1
                            ) AS show_in_folio_effective,
                            si.id_folio,
                            f.folio_name,
                            cat.category_name AS subcategory_name,
                            sic.item_name,
                            si.description,
                            si.service_date,
                            si.quantity,
                            si.unit_price_cents,
                            si.discount_amount_cents,
                            CAST(0 AS SIGNED) AS tax_amount_cents,
                            si.amount_cents,
                            si.currency,
                            si.status,
                            si.created_at
                         FROM line_item si
                         JOIN folio f ON f.id_folio = si.id_folio
                         LEFT JOIN line_item_catalog sic
                           ON sic.id_line_item_catalog = si.id_line_item_catalog
                         LEFT JOIN sale_item_category cat
                           ON cat.id_sale_item_category = sic.id_category
                         WHERE si.item_type IN (\'sale_item\',\'tax_item\',\'obligation\',\'income\')
                           AND si.deleted_at IS NULL
                           AND COALESCE(si.is_active, 1) = 1
                           AND si.id_folio IN (' . $placeholders . ')
                         ORDER BY si.service_date, si.id_line_item'
                    );
                    $stmt->execute($folioIds);
                    $supplementRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($supplementRows) {
                        $existingSaleItemIds = array();
                        foreach ($saleItems as $existingRow) {
                            $existingId = isset($existingRow['id_sale_item']) ? (int)$existingRow['id_sale_item'] : 0;
                            if ($existingId > 0) {
                                $existingSaleItemIds[$existingId] = true;
                            }
                        }
                        foreach ($supplementRows as $supplementRow) {
                            $supplementId = isset($supplementRow['id_sale_item']) ? (int)$supplementRow['id_sale_item'] : 0;
                            if ($supplementId <= 0 || isset($existingSaleItemIds[$supplementId])) {
                                continue;
                            }
                            $saleItems[] = $supplementRow;
                            $existingSaleItemIds[$supplementId] = true;
                        }
                    }
                } catch (Exception $e) {
                }
            }
        }

        if ($saleItems) {
            $catalogIds = array();
            foreach ($saleItems as $saleItemRow) {
                $catalogId = isset($saleItemRow['id_sale_item_catalog']) ? (int)$saleItemRow['id_sale_item_catalog'] : 0;
                if ($catalogId > 0) {
                    $catalogIds[$catalogId] = $catalogId;
                }
            }
            if ($catalogIds) {
                try {
                    $db = pms_get_connection();
                    $catalogIds = array_values($catalogIds);
                    $placeholders = implode(',', array_fill(0, count($catalogIds), '?'));
                    $stmt = $db->prepare(
                        'SELECT id_line_item_catalog, catalog_type, show_in_folio
                         FROM line_item_catalog
                         WHERE id_line_item_catalog IN (' . $placeholders . ')'
                    );
                    $stmt->execute($catalogIds);
                    $catalogMetaById = array();
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $metaRow) {
                        $metaId = isset($metaRow['id_line_item_catalog']) ? (int)$metaRow['id_line_item_catalog'] : 0;
                        if ($metaId <= 0) {
                            continue;
                        }
                        $catalogMetaById[$metaId] = array(
                            'catalog_type' => isset($metaRow['catalog_type']) ? (string)$metaRow['catalog_type'] : '',
                            'show_in_folio' => array_key_exists('show_in_folio', $metaRow) ? $metaRow['show_in_folio'] : null
                        );
                    }
                    if ($catalogMetaById) {
                        foreach ($saleItems as &$saleItemRow) {
                            $metaId = isset($saleItemRow['id_sale_item_catalog']) ? (int)$saleItemRow['id_sale_item_catalog'] : 0;
                            if ($metaId <= 0 || !isset($catalogMetaById[$metaId])) {
                                continue;
                            }
                            $meta = $catalogMetaById[$metaId];
                            $currentCatalogType = isset($saleItemRow['catalog_type']) ? trim((string)$saleItemRow['catalog_type']) : '';
                            if ($currentCatalogType === '' && isset($meta['catalog_type'])) {
                                $saleItemRow['catalog_type'] = (string)$meta['catalog_type'];
                            }
                            if (
                                (!array_key_exists('show_in_folio', $saleItemRow) || $saleItemRow['show_in_folio'] === null || $saleItemRow['show_in_folio'] === '')
                                && array_key_exists('show_in_folio', $meta)
                            ) {
                                $saleItemRow['show_in_folio'] = $meta['show_in_folio'];
                            }
                        }
                        unset($saleItemRow);
                    }
                } catch (Exception $e) {
                    // Si falla el enriquecimiento, continuamos con el payload disponible.
                }
            }
        }

        $hasNonSaleDerivedItems = false;
        if ($saleItems) {
            foreach ($saleItems as $saleItemRow) {
                $rowType = isset($saleItemRow['item_type']) ? strtolower(trim((string)$saleItemRow['item_type'])) : '';
                if ($rowType !== '' && $rowType !== 'sale_item') {
                    $hasNonSaleDerivedItems = true;
                    break;
                }
            }
        }

        if (!$taxItems && !$hasNonSaleDerivedItems && $saleItems && $folios) {
            $folioIds = array();
            foreach ($folios as $folioRow) {
                $fid = isset($folioRow['id_folio']) ? (int)$folioRow['id_folio'] : 0;
                if ($fid > 0) {
                    $folioIds[] = $fid;
                }
            }
            $folioIds = array_values(array_unique($folioIds));
            if ($folioIds) {
                try {
                    $db = pms_get_connection();
                    $rateCol = reservations_rate_column($db);
                    $placeholders = implode(',', array_fill(0, count($folioIds), '?'));
                    $stmt = $db->prepare(
                         'SELECT
                            MIN(ti.id_line_item) AS id_tax_item,
                            si.id_line_item AS id_sale_item,
                            tr.id_line_item_catalog AS id_tax_rule,
                            tr.item_name AS tax_name,
                            tr.' . $rateCol . ' AS rate_percent,
                            COALESCE(SUM(ti.amount_cents), 0) AS amount_cents,
                            MAX(ti.created_at) AS created_at
                         FROM line_item si
                         JOIN line_item_catalog_parent lcp
                           ON lcp.id_parent_sale_item_catalog = si.id_line_item_catalog
                          AND lcp.deleted_at IS NULL
                          AND COALESCE(lcp.is_active, 1) = 1
                         JOIN line_item_catalog tr
                           ON tr.id_line_item_catalog = lcp.id_sale_item_catalog
                          AND tr.catalog_type = \'tax_rule\'
                         JOIN line_item ti
                           ON ti.id_folio = si.id_folio
                          AND ti.item_type = \'tax_item\'
                          AND ti.id_line_item_catalog = tr.id_line_item_catalog
                          AND ti.deleted_at IS NULL
                          AND COALESCE(ti.is_active, 1) = 1
                          AND (ti.service_date <=> si.service_date)
                         WHERE si.item_type = \'sale_item\'
                            AND si.deleted_at IS NULL
                            AND COALESCE(si.is_active, 1) = 1
                            AND si.id_folio IN (' . $placeholders . ')
                            AND COALESCE(tr.show_in_folio,1) = 1
                         GROUP BY si.id_line_item, tr.id_line_item_catalog, tr.item_name, tr.' . $rateCol . '
                         ORDER BY si.id_line_item, tr.item_name'
                    );
                    $stmt->execute($folioIds);
                    $taxItems = $stmt->fetchAll();
                } catch (Exception $e) {
                    $taxItems = array();
                }
            }
        }

        $propCodeDetail = $detailRow && isset($detailRow['property_code']) ? strtoupper((string)$detailRow['property_code']) : '';
        if ($propCodeDetail !== '' && !isset($conceptsByProperty[$propCodeDetail])) {
            try {
                $catSets = pms_call_procedure('sp_sale_item_catalog_data', array(
                    $companyCode,
                    $propCodeDetail,
                    0,
                    0,
                    0
                ));
                $conceptsByProperty[$propCodeDetail] = isset($catSets[0]) ? $catSets[0] : array();
                if (!$conceptsByProperty[$propCodeDetail]) {
                    $conceptsByProperty[$propCodeDetail] = reservations_catalog_data_fallback($companyId, $propCodeDetail, 0, 0, 0);
                }
            } catch (Exception $e) {
                $conceptsByProperty[$propCodeDetail] = reservations_catalog_data_fallback($companyId, $propCodeDetail, 0, 0, 0);
            }
        }
        if (!$interests) {
            try {
                $db = pms_get_connection();
                $stmt = $db->prepare(
                    'SELECT
                        ri.id_reservation,
                        ri.id_sale_item_catalog,
                        sic.item_name,
                        cat.category_name,
                        ri.created_at
                     FROM reservation_interest ri
                     JOIN line_item_catalog sic ON sic.id_line_item_catalog = ri.id_sale_item_catalog AND sic.catalog_type = "sale_item"
                     JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
                     JOIN reservation r ON r.id_reservation = ri.id_reservation
                     JOIN property p ON p.id_property = r.id_property
                     WHERE ri.id_reservation = ?
                       AND ri.deleted_at IS NULL
                       AND ri.is_active = 1
                       AND p.id_company = ?
                     ORDER BY cat.category_name, sic.item_name'
                );
                $stmt->execute(array($reservationId, $companyId));
                $interests = $stmt->fetchAll();
            } catch (Exception $e) {
                $interests = array();
            }
        }
        $notes = array();
        $notesError = null;
        if (isset($notesOverride[$reservationId])) {
            $notes = $notesOverride[$reservationId];
        } else {
            try {
                $noteSets = pms_call_procedure('sp_reservation_note_data', array(
                    $companyCode,
                    $reservationId,
                    null,
                    0
                ));
                $notes = isset($noteSets[0]) ? $noteSets[0] : array();
            } catch (Exception $e) {
                $notes = array();
                $notesError = $e->getMessage();
            }
        }
    if (!$notes && $notesError === '') {
            try {
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare(
                    'SELECT id_reservation_note, id_reservation, note_type, note_text, is_active, deleted_at, created_at, created_by, updated_at
                     FROM reservation_note
                     WHERE id_reservation = ?
                       AND deleted_at IS NULL
                       AND is_active = 1
                     ORDER BY created_at DESC, id_reservation_note DESC'
                );
                $stmt->execute(array($reservationId));
                $notes = $stmt->fetchAll();
            } catch (Exception $e) {
                $notes = array();
                $notesError = $notesError ?: $e->getMessage();
            }
        }

        if (!$payments && $folios) {
            $folioIds = array();
            foreach ($folios as $folioRow) {
                if (isset($folioRow['id_folio'])) {
                    $folioIds[] = (int)$folioRow['id_folio'];
                }
            }
            $folioIds = array_values(array_filter(array_unique($folioIds)));
            if ($folioIds) {
                try {
                    $db = pms_get_connection();
                    $placeholders = implode(',', array_fill(0, count($folioIds), '?'));
                    $stmt = $db->prepare(
                        'SELECT
                            li.id_line_item AS id_payment,
                            li.id_folio,
                            f.folio_name,
                            li.id_line_item_catalog AS id_payment_catalog,
                            li.method,
                            li.amount_cents,
                            li.currency,
                            li.reference,
                            li.service_date,
                            li.status,
                            li.refunded_total_cents,
                            li.created_at
                         FROM line_item li
                         JOIN folio f ON f.id_folio = li.id_folio
                         WHERE li.item_type = \'payment\'
                           AND li.deleted_at IS NULL
                           AND li.id_folio IN (' . $placeholders . ')
                         ORDER BY li.created_at DESC'
                    );
                    $stmt->execute($folioIds);
                    $payments = $stmt->fetchAll();
                } catch (Exception $e) {
                    $payments = array();
                }
            }
        }

        if ($payments) {
            $paymentCatalogIds = array();
            foreach ($payments as $paymentRow) {
                $paymentCatalogId = isset($paymentRow['id_payment_catalog']) ? (int)$paymentRow['id_payment_catalog'] : 0;
                if ($paymentCatalogId > 0) {
                    $paymentCatalogIds[$paymentCatalogId] = $paymentCatalogId;
                }
            }
            $paymentCatalogMetaById = array();
            if ($paymentCatalogIds) {
                try {
                    $db = pms_get_connection();
                    $paymentCatalogIds = array_values($paymentCatalogIds);
                    $placeholders = implode(',', array_fill(0, count($paymentCatalogIds), '?'));
                    $stmt = $db->prepare(
                        'SELECT id_line_item_catalog, catalog_type, show_in_folio
                         FROM line_item_catalog
                         WHERE id_line_item_catalog IN (' . $placeholders . ')'
                    );
                    $stmt->execute($paymentCatalogIds);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $metaRow) {
                        $metaCatalogId = isset($metaRow['id_line_item_catalog']) ? (int)$metaRow['id_line_item_catalog'] : 0;
                        if ($metaCatalogId <= 0) {
                            continue;
                        }
                        $paymentCatalogMetaById[$metaCatalogId] = array(
                            'catalog_type' => isset($metaRow['catalog_type']) ? (string)$metaRow['catalog_type'] : '',
                            'show_in_folio' => array_key_exists('show_in_folio', $metaRow) ? $metaRow['show_in_folio'] : null
                        );
                    }
                } catch (Exception $e) {
                    $paymentCatalogMetaById = array();
                }
            }

            $visiblePayments = array();
            foreach ($payments as $paymentRow) {
                $paymentCatalogId = isset($paymentRow['id_payment_catalog']) ? (int)$paymentRow['id_payment_catalog'] : 0;
                $rowShowInFolio = null;
                if (array_key_exists('show_in_folio', $paymentRow) && $paymentRow['show_in_folio'] !== null && $paymentRow['show_in_folio'] !== '') {
                    $rowShowInFolio = !empty($paymentRow['show_in_folio']) ? 1 : 0;
                }
                if ($paymentCatalogId > 0 && isset($paymentCatalogMetaById[$paymentCatalogId])) {
                    $metaRow = $paymentCatalogMetaById[$paymentCatalogId];
                    if ((!isset($paymentRow['catalog_type']) || trim((string)$paymentRow['catalog_type']) === '') && isset($metaRow['catalog_type'])) {
                        $paymentRow['catalog_type'] = (string)$metaRow['catalog_type'];
                    }
                    if ($rowShowInFolio === null && array_key_exists('show_in_folio', $metaRow)) {
                        $rowShowInFolio = !empty($metaRow['show_in_folio']) ? 1 : 0;
                    }
                }
                if ($rowShowInFolio !== null && (int)$rowShowInFolio !== 1) {
                    continue;
                }
                $visiblePayments[] = $paymentRow;
            }
            $payments = $visiblePayments;
        }

        if ($payments && $folios) {
            $paymentsSumByFolio = array();
            foreach ($payments as $p) {
                $fid = isset($p['id_folio']) ? (int)$p['id_folio'] : 0;
                if ($fid <= 0) {
                    continue;
                }
                if (!isset($paymentsSumByFolio[$fid])) {
                    $paymentsSumByFolio[$fid] = 0;
                }
                $paymentsSumByFolio[$fid] += (int)($p['amount_cents'] ?? 0);
            }
            foreach ($folios as &$folioRow) {
                $fid = isset($folioRow['id_folio']) ? (int)$folioRow['id_folio'] : 0;
                if ($fid <= 0 || !isset($paymentsSumByFolio[$fid])) {
                    continue;
                }
                $folioRow['payments_cents'] = $paymentsSumByFolio[$fid];
            }
            unset($folioRow);
        }

        $reservationDetails[$reservationId] = array(
            'detail' => $detailRow,
            'folios' => $folios,
            'sale_items' => $saleItems,
            'payments' => $payments,
            'refunds' => $refunds,
            'activities' => $activities,
            'interests' => $interests,
            'notes' => $notes,
            'notes_error' => $notesError,
            'error' => null
        );
    } catch (Exception $e) {
        $reservationDetails[$reservationId] = array(
            'detail' => null,
            'folios' => array(),
            'sale_items' => array(),
            'payments' => array(),
            'refunds' => array(),
            'activities' => array(),
            'interests' => array(),
            'notes' => array(),
            'notes_error' => null,
            'error' => $e->getMessage()
        );
    }
}

ob_start();
?>
<div class="subtab-actions">
  <form method="post" class="form-inline">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <label>
      Propiedad
      <select name="reservations_filter_property" onchange="this.form.submit();">
        <option value="">Todas</option>
        <?php foreach ($properties as $property):
          $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
          $name = isset($property['name']) ? (string)$property['name'] : '';
          if ($code === '') {
              continue;
          }
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $filters['property_code'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="reservations-status-filter">
      <span>Estatus</span>
      <details class="reservations-status-checklist">
        <summary><?php echo htmlspecialchars($statusFilterSummary, ENT_QUOTES, 'UTF-8'); ?></summary>
        <div class="reservations-status-checklist-menu">
          <?php foreach ($statusFilterOptions as $statusValue => $statusLabel): ?>
            <label class="reservations-status-checklist-item">
              <input
                type="checkbox"
                name="reservations_filter_status[]"
                value="<?php echo htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo isset($selectedStatusFilterSet[$statusValue]) ? 'checked' : ''; ?>
              >
              <span><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </details>
    </label>
    <label>
      Desde
      <input type="date" name="reservations_filter_from" value="<?php echo htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Hasta
  <input type="date" name="reservations_filter_to" value="<?php echo htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8'); ?>">
</label>
<button type="submit">Filtrar</button>
  </form>
    <a class="button-secondary" href="index.php?view=reservation_wizard&wizard_step=1">Nueva reserva</a>
  </div>
<?php if ($globalError): ?>
  <p class="error"><?php echo htmlspecialchars($globalError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<style>
.reservation-detail-header.reservation-detail-header-enhanced {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 14px;
}
.reservation-detail-title {
  flex: 1 1 460px;
  min-width: 280px;
}
.reservation-detail-title h3 {
  margin: 0;
}
.reservation-title-main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  font-size: clamp(2.05rem, 2.95vw, 2.95rem);
  font-weight: 800;
  line-height: 1.14;
  letter-spacing: 0.01em;
  white-space: normal;
  min-width: 0;
}
.reservation-title-guest {
  color: #f4fbff;
  font-size: clamp(2.15rem, 3.15vw, 3.25rem);
  font-weight: 900;
  line-height: 1.05;
  text-shadow: 0 1px 0 rgba(0, 0, 0, 0.2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}
.reservation-title-dates,
.reservation-title-room {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(99, 212, 255, 0.45);
  background: rgba(28, 148, 196, 0.16);
  color: #bdeeff;
  font-size: 0.96rem;
  font-weight: 700;
  white-space: nowrap;
  flex: 0 0 auto;
}
.reservation-title-arrow {
  color: #71d5ff;
  font-weight: 900;
}
.reservation-detail-header-center {
  display: flex;
  justify-content: flex-start;
  flex: 1 1 420px;
  min-width: 320px;
}
.reservation-detail-header-right {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 10px;
  margin-left: auto;
  flex: 0 0 auto;
}
.reservation-detail-balances {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.reservation-detail-balances .balance-item {
  color: #d8f2ff;
  font-size: 0.9rem;
  font-weight: 700;
}
.reservation-detail-balances .balance-item strong {
  color: #ffffff;
}
.reservation-quick-actions {
  position: relative;
}
.reservation-quick-actions-toggle {
  list-style: none;
  cursor: pointer;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid rgba(99, 212, 255, 0.45);
  background: rgba(28, 148, 196, 0.12);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.reservation-quick-actions-toggle::-webkit-details-marker {
  display: none;
}
.reservation-quick-actions-toggle:hover {
  background: rgba(28, 148, 196, 0.24);
}
.reservation-quick-actions-dots {
  display: inline-flex;
  flex-direction: column;
  gap: 3px;
}
.reservation-quick-actions-dots span {
  width: 4px;
  height: 4px;
  border-radius: 999px;
  background: #d5f3ff;
  display: block;
}
.reservation-quick-actions-menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  min-width: 220px;
  padding: 8px;
  border-radius: 10px;
  border: 1px solid rgba(110, 170, 230, 0.35);
  background: rgba(7, 22, 42, 0.98);
  box-shadow: 0 12px 22px rgba(0, 0, 0, 0.35);
  z-index: 35;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.reservation-quick-actions:not([open]) .reservation-quick-actions-menu {
  display: none;
}
.reservations-status-filter {
  position: relative;
}
.reservations-status-checklist {
  position: relative;
  min-width: 230px;
}
.reservations-status-checklist > summary {
  list-style: none;
  min-height: 38px;
  border: 1px solid rgba(110, 170, 230, 0.35);
  border-radius: 10px;
  padding: 8px 12px;
  color: #e8f7ff;
  background: rgba(6, 19, 39, 0.85);
  cursor: pointer;
  display: flex;
  align-items: center;
}
.reservations-status-checklist > summary::-webkit-details-marker {
  display: none;
}
.reservations-status-checklist-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  z-index: 60;
  min-width: 240px;
  max-height: 230px;
  overflow-y: auto;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgba(110, 170, 230, 0.35);
  background: rgba(7, 22, 42, 0.98);
  box-shadow: 0 12px 22px rgba(0, 0, 0, 0.35);
  display: grid;
  gap: 8px;
}
.reservations-status-checklist:not([open]) .reservations-status-checklist-menu {
  display: none;
}
.reservations-status-checklist-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #d9f1ff;
  font-size: 0.92rem;
  margin: 0;
}
.reservations-status-checklist-item input {
  margin: 0;
}
.reservation-quick-action-form {
  margin: 0;
  display: block;
  padding: 0;
}
.reservation-quick-action-form + .reservation-quick-action-form {
  border-top: 1px solid rgba(110, 170, 230, 0.2);
  padding-top: 8px;
}
.reservation-quick-action-form .button-secondary {
  width: 100%;
  text-align: left;
  justify-content: flex-start;
}
.reservation-quick-action-danger {
  border-color: rgba(255, 125, 125, 0.45);
}
.reservation-detail-alert {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 12px;
  border: 2px solid #ffc75f;
  background: rgba(255, 115, 0, 0.26);
  box-shadow: 0 0 0 2px rgba(255, 150, 31, 0.25);
  max-width: 100%;
  width: 100%;
}
.reservation-detail-alert.is-critical {
  border-color: #ff4e4e;
  background: rgba(255, 35, 35, 0.3);
  box-shadow: 0 0 0 2px rgba(255, 35, 35, 0.25);
}
.reservation-detail-alert-text {
  font-weight: 700;
  letter-spacing: 0.01em;
  flex: 1 1 260px;
  min-width: 0;
}
.reservation-detail-alert form {
  margin: 0;
  flex: 0 0 auto;
}
.subtabs .subtab-panel {
  display: none;
}
.subtabs .subtab-panel.is-active {
  display: block;
}
.reservation-main-content {
  display: grid;
  gap: 14px;
}
.reservation-summary-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 14px;
  align-items: start;
}
.reservation-summary-column {
  display: grid;
  gap: 14px;
  align-content: start;
}
.reservation-summary-card,
.reservation-guest-card,
.reservation-interest-card,
.reservation-message-card,
.reservation-pricing-card {
  border: 1px solid rgba(110, 170, 230, 0.25);
  border-radius: 12px;
  background: rgba(8, 26, 50, 0.42);
  padding: 12px 14px;
  height: 100%;
}
.reservation-side-stack {
  display: grid;
  grid-template-rows: auto auto auto;
  gap: 14px;
  min-height: 0;
  align-content: start;
}
.reservation-guest-widgets {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 14px;
  align-items: stretch;
}
.reservation-guest-widgets > .reservation-interest-card,
.reservation-guest-widgets > .reservation-message-card {
  min-height: 178px;
}
.reservation-ota-info-card {
  border: 1px solid rgba(110, 170, 230, 0.3);
  border-radius: 12px;
  background: rgba(7, 22, 42, 0.54);
  overflow: hidden;
  margin-top: 0;
}
.reservation-ota-info-body {
  padding: 10px 14px 12px;
}
.reservation-ota-info-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}
.reservation-ota-info-title {
  font-size: 0.84rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  color: #d6eaff;
}
.reservation-ota-info-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(106, 226, 255, 0.45);
  background: rgba(30, 143, 186, 0.18);
  color: #c8f5ff;
  font-size: 0.8rem;
  font-weight: 700;
}
.reservation-ota-info-pill.is-muted {
  border-color: rgba(148, 171, 194, 0.42);
  background: rgba(68, 88, 111, 0.25);
  color: #c6d7e9;
}
.reservation-ota-info-detect {
  margin: 6px 0 10px;
}
.reservation-ota-info-table-wrap {
  max-height: 260px;
}
.reservation-ota-info-table td,
.reservation-ota-info-table th {
  white-space: nowrap;
}
.reservation-ota-info-table tr.is-empty td {
  opacity: 0.65;
}
.reservation-summary-card {
  display: flex;
  flex-direction: column;
}
.reservation-pricing-card {
  display: grid;
  gap: 10px;
}
.reservation-pricing-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}
.reservation-pricing-head .label {
  font-size: 0.84rem;
  opacity: 0.85;
}
.reservation-pricing-total {
  font-size: 1.02rem;
  font-weight: 800;
  color: #dbf5ff;
}
.reservation-nightly-breakdown-wrap {
  max-height: 210px;
}
.reservation-nightly-breakdown td,
.reservation-nightly-breakdown th {
  white-space: nowrap;
}
.reservation-summary-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 8px 14px;
}
.reservation-summary-field {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.reservation-summary-field .label {
  font-size: 0.78rem;
  opacity: 0.8;
}
.reservation-summary-field .value {
  font-weight: 700;
  word-break: break-word;
}
.reservation-summary-field [data-field-edit] {
  display: none;
}
.reservation-summary-room {
  display: flex;
  align-items: center;
  gap: 8px;
}
.reservation-summary-room-link {
  padding: 3px 8px;
  border-radius: 8px;
  border: 1px solid rgba(99, 212, 255, 0.45);
  color: #c9efff;
  text-decoration: none;
  font-size: 0.75rem;
}
.reservation-summary-room-link:hover {
  background: rgba(28, 148, 196, 0.2);
}
.reservation-summary-edit {
  margin-top: 8px;
  padding-top: 4px;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.reservation-summary-editing .reservation-summary-field [data-field-view] {
  display: none !important;
}
.reservation-summary-editing .reservation-summary-field [data-field-edit] {
  display: block !important;
}
.reservation-interest-card h4 {
  margin: 0;
}
.reservation-interest-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}
.reservation-interest-add-wrap {
  margin-left: auto;
}
.reservation-interest-add-wrap > summary {
  list-style: none;
  cursor: pointer;
}
.reservation-interest-add-wrap > summary::-webkit-details-marker {
  display: none;
}
.reservation-interest-add-wrap > .interest-form {
  margin-top: 10px;
}
.reservation-interest-add-wrap:not([open]) > .interest-form {
  display: none;
}
.reservation-interest-toggle {
  padding: 4px 10px;
  min-height: 30px;
  font-size: 0.8rem;
}
.reservation-interest-card .interest-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.reservation-interest-card [data-interest-list-wrap] {
  flex: 1;
  min-height: 0;
  overflow: auto;
}
.reservation-interest-card .interest-tag {
  margin: 0;
}
.reservation-message-card h4 {
  margin: 0 0 10px;
}
.reservation-message-body {
  flex: 1;
  min-height: 78px;
  border: 1px dashed rgba(114, 162, 210, 0.35);
  border-radius: 10px;
  padding: 10px;
  color: #9ab7d6;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  font-size: 0.85rem;
}
.reservation-guest-compact {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 10px;
  height: 100%;
}
.reservation-guest-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
  gap: 10px 14px;
  align-items: end;
}
.reservation-guest-row.name-only {
  grid-template-columns: minmax(0, 1fr);
}
.reservation-guest-field {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.reservation-guest-field .label {
  font-size: 0.78rem;
  opacity: 0.8;
}
.reservation-guest-field .value {
  font-weight: 700;
  word-break: break-word;
}
.reservation-guest-actions {
  display: flex;
  justify-content: flex-end;
}
.reservation-interest-card .interest-form {
  margin-top: 10px;
  display: flex;
  align-items: flex-end;
  gap: 10px;
}
.reservation-interest-control {
  flex: 1;
}
.reservation-interest-add {
  min-height: 40px;
  padding: 0 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.reservation-guest-link {
  border-radius: 12px;
}
.reservation-folios-card {
  margin-top: 8px;
  margin-bottom: 8px;
  border: 1px solid rgba(110, 170, 230, 0.25);
  border-radius: 12px;
  background: rgba(8, 26, 50, 0.42);
  overflow: hidden;
}
.reservation-folios-card > .reservation-folios-head {
  padding: 12px 14px;
}
.reservation-folios-card .subtab-info {
  margin: 10px;
}
.reservation-folios-card + .reservation-edit-form .subtab-info {
  margin-top: 12px;
}
.reservation-edit-form + .subtab-info {
  margin-top: 12px;
}
.reservation-folios-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.reservation-folios-metrics {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
}
.reservation-folios-metrics span {
  color: #cde5ff;
}
.folio-charge-tabs {
  display: block;
}
.folio-charge-tab-head {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}
.cargos-pagos-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}
.cargos-pagos-head h4 {
  margin: 0;
}
.cargos-pagos-metrics {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
.cargos-pagos-metrics .balance-item {
  border: 1px solid rgba(120, 180, 240, 0.35);
  border-radius: 999px;
  padding: 5px 10px;
  color: #d8f2ff;
  background: rgba(12, 34, 62, 0.45);
  font-size: 0.86rem;
  font-weight: 700;
}
.cargos-pagos-metrics .balance-item strong {
  color: #ffffff;
}
.folio-charge-tab-head .reservation-tab-trigger {
  border: 1px solid rgba(120, 180, 240, 0.35);
  background: rgba(12, 34, 62, 0.55);
  color: #cde5ff;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
}
.folio-charge-tab-head .reservation-tab-trigger.is-active {
  border-color: rgba(120, 200, 255, 0.7);
  background: rgba(28, 112, 168, 0.35);
  color: #ffffff;
}
.folio-charge-tabs [data-tab-panel] {
  display: none;
}
.folio-charge-tabs [data-tab-panel].is-active {
  display: block;
}
.folio-charge-pane {
  border: 1px solid rgba(120, 180, 240, 0.2);
  border-radius: 10px;
  background: rgba(6, 20, 40, 0.45);
  padding: 10px;
}
.folio-charge-pane h6 {
  margin: 0 0 8px;
  font-size: 0.95rem;
  font-weight: 700;
}
.folio-lodging-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.folio-lodging-list li {
  border: 1px solid rgba(120, 180, 240, 0.18);
  border-radius: 8px;
  padding: 8px 10px;
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
.folio-lodging-list .folio-lodging-date {
  display: block;
  opacity: 0.75;
  font-size: 0.82rem;
}
.folio-row-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}
.folio-section-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
[data-folio-edit-pane] .folio-edit-only {
  display: none !important;
}
[data-folio-edit-pane].is-editing .folio-edit-only {
  display: inline-block !important;
}
[data-folio-edit-pane].is-editing td.folio-edit-only,
[data-folio-edit-pane].is-editing th.folio-edit-only {
  display: table-cell !important;
}
[data-folio-edit-pane].is-editing .folio-row-actions.folio-edit-only {
  display: flex !important;
}
[data-folio-edit-pane] .folio-readonly-only {
  display: inline;
}
[data-folio-edit-pane].is-editing .folio-readonly-only {
  display: none !important;
}
.folio-tax-toggle {
  margin-top: 6px;
  padding: 2px 8px;
  font-size: 0.75rem;
  line-height: 1.2;
}
.note-delete-button {
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.06);
  color: #cddfff;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  font-size: 12px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
}
.note-delete-button:hover {
  background: rgba(255, 90, 90, 0.2);
  border-color: rgba(255, 120, 120, 0.45);
  color: #ffd5d5;
}
@media (max-width: 980px) {
  .reservation-detail-header.reservation-detail-header-enhanced {
    flex-direction: column;
    align-items: stretch;
  }
  .reservation-detail-title,
  .reservation-detail-header-center {
    flex: 1 1 auto;
    min-width: 0;
  }
  .reservation-detail-header-center {
    justify-content: flex-start;
  }
  .reservation-detail-header-right {
    margin-left: 0;
    align-items: flex-start;
  }
  .reservation-detail-balances {
    align-items: flex-start;
  }
  .reservation-title-main {
    font-size: 1.85rem;
    flex-wrap: wrap;
    white-space: normal;
    overflow: visible;
  }
  .reservation-title-guest {
    font-size: 2.05rem;
    max-width: 100%;
  }
  .reservation-summary-layout,
  .reservation-summary-column,
  .reservation-summary-grid,
  .reservation-guest-row,
  .reservation-guest-widgets {
    grid-template-columns: 1fr;
  }
  .folio-charge-tab-head {
    flex-direction: column;
    align-items: stretch;
  }
  .folio-charge-tab-head .reservation-tab-trigger {
    width: 100%;
    text-align: left;
  }
  .cargos-pagos-head {
    flex-direction: column;
    align-items: flex-start;
  }
  .cargos-pagos-metrics {
    justify-content: flex-start;
  }
  .reservation-side-stack {
    grid-template-rows: auto auto auto;
  }
  .reservation-ota-info-head {
    flex-direction: column;
    align-items: flex-start;
  }
  .reservation-interest-card .interest-form {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
<?php if ($reservationsList): ?>
  <div class="table-scroll">
    <table class="reservations-list-table">
      <thead>
        <tr>
          <th>C&oacute;digo</th>
          <th>Propiedad</th>
          <th>Habitaci&oacute;n</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Estatus</th>
          <th>Hu&eacute;sped</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reservationsList as $row):
          $rowId = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
          $isOpen = in_array($rowId, $openReservationIds, true);
        ?>
          <tr class="<?php echo $isOpen ? 'is-selected' : ''; ?>">
            <td><?php echo htmlspecialchars(isset($row['reservation_code']) ? (string)$row['reservation_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['property_code']) ? (string)$row['property_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['room_code']) ? (string)$row['room_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['check_in_display']) ? (string)$row['check_in_display'] : reservations_format_date(isset($row['check_in_date']) ? (string)$row['check_in_date'] : '', 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['check_out_display']) ? (string)$row['check_out_display'] : reservations_format_date(isset($row['check_out_date']) ? (string)$row['check_out_date'] : '', 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(reservations_status_label(isset($row['status']) ? (string)$row['status'] : ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['guest_display']) ? (string)$row['guest_display'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <form method="post">
                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                <?php reservations_render_filter_hiddens($filters); ?>
                <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="reservation:<?php echo $rowId; ?>">
                <button type="submit" class="button-secondary">Abrir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <script>
  (function () {
    function toMxDate(raw) {
      if (typeof raw !== 'string') {
        return raw;
      }
      var trimmed = raw.trim();
      var m = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!m) {
        return raw;
      }
      return m[3] + '/' + m[2] + '/' + m[1];
    }
    var cells = document.querySelectorAll('.reservations-list-table td');
    for (var i = 0; i < cells.length; i++) {
      var text = cells[i].textContent || '';
      var converted = toMxDate(text);
      if (converted !== text) {
        cells[i].textContent = converted;
      }
    }
  })();
  </script>
<?php else: ?>
  <p class="muted">No se encontraron reservas con los filtros seleccionados.</p>
<?php endif; ?>
<?php
$generalContent = ob_get_clean();

ob_start();
?>
<form id="reservations-close-new" method="post" style="display:none;">
  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
  <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
  <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:new">
</form>
<?php if ($newReservationError): ?>
  <p class="error"><?php echo htmlspecialchars($newReservationError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($newReservationMessage): ?>
  <p class="success"><?php echo htmlspecialchars($newReservationMessage, ENT_QUOTES, 'UTF-8'); ?></p>
<?php else: ?>
  <p class="muted">Ingresa la informaci&oacute;n para registrar una nueva reserva manual.</p>
<?php endif; ?>
<form method="post" class="reservation-create-form">
  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
  <?php reservations_render_filter_hiddens($filters); ?>
  <input type="hidden" name="reservations_action" value="create_reservation">
  <div class="form-section payment-section">
    <h4>Informaci&oacute;n general</h4>
    <div class="form-grid grid-3">
      <label>
        Propiedad *
        <select name="create_property_code" required onchange="this.form.reservations_action.value='prepare_create'; this.form.submit();">
          <option value="">Selecciona una opcion</option>
          <?php foreach ($properties as $property):
            $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
            $name = isset($property['name']) ? (string)$property['name'] : '';
            if ($code === '') {
                continue;
            }
          ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtoupper($code) === strtoupper($newReservationValues['property_code']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Habitaci&oacute;n *
        <select name="create_room_code" required id="create-room-code" onchange="this.form.reservations_action.value='prepare_create'; this.form.submit();">
          <option value="">Selecciona una habitaci&oacute;n</option>
          <?php
            $createProperty = strtoupper($newReservationValues['property_code']);
            $availableRooms = reservations_rooms_for_property($roomsByProperty, $createProperty);
            foreach ($availableRooms as $room):
              $code = isset($room['code']) ? (string)$room['code'] : '';
              $label = reservations_room_label($room);
              $baseCents = isset($room['default_base_price_cents']) ? (int)$room['default_base_price_cents'] : 0;
              $isSelectedRoom = strtoupper($code) === strtoupper($newReservationValues['room_code']);
              $rateNightlyCents = $isSelectedRoom && $rateplanPreviewNightly !== null ? $rateplanPreviewNightly : null;
              $rateTotalCents = $isSelectedRoom && $rateplanPreviewTotal !== null ? $rateplanPreviewTotal : null;
              if ($rateNightlyCents !== null) {
                  $baseCents = $rateNightlyCents;
              }
          ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
              data-base="<?php echo $baseCents; ?>"
              <?php echo $rateNightlyCents !== null ? 'data-rate-nightly="' . (int)$rateNightlyCents . '"' : ''; ?>
              <?php echo $rateTotalCents !== null ? ' data-rate-total="' . (int)$rateTotalCents . '"' : ''; ?>
              <?php echo $isSelectedRoom ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Check-in *
        <input type="date" name="create_check_in" id="create-check-in" value="<?php echo htmlspecialchars($newReservationValues['check_in'], ENT_QUOTES, 'UTF-8'); ?>" required onchange="this.form.reservations_action.value='prepare_create'; this.form.submit();">
      </label>
      <label>
        Check-out *
        <input type="date" name="create_check_out" id="create-check-out" value="<?php echo htmlspecialchars($newReservationValues['check_out'], ENT_QUOTES, 'UTF-8'); ?>" required onchange="this.form.reservations_action.value='prepare_create'; this.form.submit();">
      </label>
      <label>
        Origen
        <?php
          $createOriginOptionsUi = reservations_origin_options_for_property($reservationSourcesByProperty, $otaAccountsByProperty, $createProperty);
          $selectedCreateOriginKey = reservations_origin_key_from_input(isset($newReservationValues['origin_key']) ? $newReservationValues['origin_key'] : '');
          if ($selectedCreateOriginKey === '' && !empty($createOriginOptionsUi)) {
              $selectedCreateOriginKey = (string)$createOriginOptionsUi[0]['origin_key'];
          }
        ?>
        <select name="create_origin_id" id="create-origin-id">
          <?php foreach ($createOriginOptionsUi as $originOpt): ?>
            <?php
              $originKeyOpt = (string)(isset($originOpt['origin_key']) ? $originOpt['origin_key'] : 'source:0');
              $originLabelOpt = trim((string)(isset($originOpt['origin_label']) ? $originOpt['origin_label'] : ''));
              if ($originLabelOpt === '') {
                  $originLabelOpt = 'Directo';
              }
            ?>
            <option value="<?php echo htmlspecialchars($originKeyOpt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $originKeyOpt === $selectedCreateOriginKey ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($originLabelOpt, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Adultos
        <input type="number" name="create_adults" min="1" value="<?php echo htmlspecialchars($newReservationValues['adults'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Menores
        <input type="number" name="create_children" min="0" value="<?php echo htmlspecialchars($newReservationValues['children'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
    </div>
  </div>

  <div class="form-section">
    <h4>Informaci&oacute;n del hu&eacute;sped</h4>
    <div class="form-grid grid-3">
        <label>
          Nombre hu&eacute;sped
          <input type="text" name="create_guest_names" id="create-guest-names" data-guest-scope="create" data-guest-field="names" value="<?php echo htmlspecialchars($newReservationValues['guest_names'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Correo hu&eacute;sped
          <input type="email" name="create_guest_email" id="create-guest-email" data-guest-scope="create" data-guest-field="email" value="<?php echo htmlspecialchars($newReservationValues['guest_email'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Tel&eacute;fono
          <div class="phone-input">
            <select name="create_guest_phone_prefix" aria-label="Prefijo" data-guest-scope="create" data-guest-field="prefix">
              <?php $createPrefixSelected = false; ?>
              <?php foreach ($phoneCountries as $phoneCountry): ?>
                <?php
                  $prefix = isset($phoneCountry['dial']) ? (string)$phoneCountry['dial'] : '';
                  if ($prefix === '') {
                      continue;
                  }
                  $countryName = isset($phoneCountry['name_es']) ? (string)$phoneCountry['name_es'] : strtoupper((string)(isset($phoneCountry['iso2']) ? $phoneCountry['iso2'] : ''));
                  $isSelected = (!$createPrefixSelected && $prefix === $newReservationValues['guest_phone_prefix']);
                  if ($isSelected) {
                      $createPrefixSelected = true;
                  }
                ?>
                <option value="<?php echo htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($countryName . ' (' . $prefix . ')', ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="tel" name="create_guest_phone" id="create-guest-phone" data-guest-scope="create" data-guest-field="phone" value="<?php echo htmlspecialchars($newReservationValues['guest_phone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="WhatsApp">
          </div>
        </label>
        <label>
          Apellido paterno
          <input type="text" name="create_guest_last_name" data-guest-scope="create" data-guest-field="last" value="<?php echo htmlspecialchars($newReservationValues['guest_last'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Apellido materno
          <input type="text" name="create_guest_maiden_name" data-guest-scope="create" data-guest-field="maiden" value="<?php echo htmlspecialchars($newReservationValues['guest_maiden'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
      </div>
      <div class="guest-suggestions" id="guest-suggestions" data-guest-suggestions="create" style="display:none;"></div>
    </div>
  <?php
    $lodgingAllowedIds = array();
    if ($createProperty !== '') {
        try {
            $settingSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, $createProperty));
            $settingRow = isset($settingSets[0][0]) ? $settingSets[0][0] : null;
            if ($settingRow && isset($settingRow['lodging_catalog_ids']) && $settingRow['lodging_catalog_ids'] !== '') {
                $lodgingAllowedIds = array_filter(array_map('intval', explode(',', (string)$settingRow['lodging_catalog_ids'])));
            }
        } catch (Exception $e) {
            $lodgingAllowedIds = array();
        }
    }
    if (!$lodgingAllowedIds) {
        try {
            $fallbackSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, null));
            $fallbackRow = isset($fallbackSets[0][0]) ? $fallbackSets[0][0] : null;
            if ($fallbackRow && isset($fallbackRow['lodging_catalog_ids']) && $fallbackRow['lodging_catalog_ids'] !== '') {
                $lodgingAllowedIds = array_filter(array_map('intval', explode(',', (string)$fallbackRow['lodging_catalog_ids'])));
            }
        } catch (Exception $e) {
            $lodgingAllowedIds = array();
        }
    }

    if ($createProperty !== '' && !isset($conceptsByProperty[$createProperty])) {
        try {
            $conceptSets = pms_call_procedure('sp_sale_item_catalog_data', array(
                $companyCode,
                $createProperty,
                0,
                0,
                0
            ));
            $conceptsByProperty[$createProperty] = isset($conceptSets[0]) ? $conceptSets[0] : array();
            if (!$conceptsByProperty[$createProperty]) {
                $conceptsByProperty[$createProperty] = reservations_catalog_data_fallback($companyId, $createProperty, 0, 0, 0);
            }
        } catch (Exception $e) {
            $conceptsByProperty[$createProperty] = reservations_catalog_data_fallback($companyId, $createProperty, 0, 0, 0);
        }
    }

    $fixedChildByParent = array();
    if ($createProperty !== '' && isset($conceptsByProperty[$createProperty])) {
        foreach ($conceptsByProperty[$createProperty] as $concept) {
            $parentIds = reservations_parse_ids(isset($concept['parent_item_ids']) ? $concept['parent_item_ids'] : '');
            if (!$parentIds) {
                $legacyParentId = isset($concept['id_parent_sale_item_catalog']) ? (int)$concept['id_parent_sale_item_catalog'] : 0;
                if ($legacyParentId > 0) {
                    $parentIds = array($legacyParentId);
                }
            }
            if (!$parentIds) {
                continue;
            }
            if (!empty($concept['is_percent'])) {
                continue;
            }
            foreach ($parentIds as $parentId) {
                if ($parentId <= 0) {
                    continue;
                }
                if (!isset($fixedChildByParent[$parentId])) {
                    $fixedChildByParent[$parentId] = $concept;
                }
            }
        }
    }

    $lodgingOptions = array();
    $lodgingAllowedMap = array();
    foreach ($lodgingAllowedIds as $lid) {
        $lodgingAllowedMap[(int)$lid] = true;
    }
    if ($createProperty !== '' && $lodgingAllowedMap) {
        foreach ($conceptsByProperty[$createProperty] as $c) {
            $cid = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
            if ($cid <= 0 || !isset($lodgingAllowedMap[$cid])) {
                continue;
            }
            $cat = isset($c['category']) ? (string)$c['category'] : '';
            $label = isset($c['item_name']) ? (string)$c['item_name'] : '';
            $taxRuleIds = '';
            $child = isset($fixedChildByParent[$cid]) ? $fixedChildByParent[$cid] : null;
            $childId = $child ? (int)$child['id_sale_item_catalog'] : 0;
            $childName = $child ? (string)$child['item_name'] : '';
            $childDefault = $child ? reservations_catalog_default_cents($child) : 0;
            $childTaxRuleIds = '';
            $lodgingOptions[] = array(
                'id' => $cid,
                'label' => ($cat !== '' ? $cat . ' / ' : '') . $label,
                'child_id' => $childId,
                'child_name' => $childName,
                'child_default_cents' => $childDefault,
                'tax_rule_ids' => '',
                'child_tax_rule_ids' => ''
            );
        }
    }
    if ($newReservationValues['lodging_catalog_id'] > 0 && !isset($lodgingAllowedMap[(int)$newReservationValues['lodging_catalog_id']])) {
        $newReservationValues['lodging_catalog_id'] = 0;
    }
    $extraConceptOptions = array();
    $extraConceptMap = array();
    if ($createProperty !== '' && isset($conceptsByProperty[$createProperty])) {
        foreach ($conceptsByProperty[$createProperty] as $c) {
            $cid = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
            if ($cid <= 0) {
                continue;
            }
            $cat = isset($c['category']) ? (string)$c['category'] : '';
            $label = isset($c['item_name']) ? (string)$c['item_name'] : '';
            $extraConceptOptions[] = array(
                'id' => $cid,
                'label' => ($cat !== '' ? $cat . ' / ' : '') . $label,
                'default_cents' => reservations_catalog_default_cents($c)
            );
            $extraConceptMap[$cid] = true;
        }
    }
    $extraLodgingRows = isset($newReservationValues['extra_lodging_rows']) && is_array($newReservationValues['extra_lodging_rows'])
        ? $newReservationValues['extra_lodging_rows']
        : array();
    $normalizedExtraRows = array();
    $mainLodgingId = isset($newReservationValues['lodging_catalog_id']) ? (int)$newReservationValues['lodging_catalog_id'] : 0;
    foreach ($extraLodgingRows as $row) {
        $extraId = isset($row['id']) ? (int)$row['id'] : 0;
        $extraAmount = isset($row['amount']) ? trim((string)$row['amount']) : '';
        if ($extraId > 0 && !isset($extraConceptMap[$extraId])) {
            $extraId = 0;
        }
        if ($extraId === $mainLodgingId) {
            $extraId = 0;
        }
        if ($extraId <= 0 && $extraAmount === '') {
            continue;
        }
        $normalizedExtraRows[] = array(
            'id' => $extraId,
            'amount' => $extraAmount
        );
    }
    $showExtraLodging = !empty($normalizedExtraRows);
    $paymentCreateRows = isset($newReservationValues['payments']) && is_array($newReservationValues['payments'])
        ? $newReservationValues['payments']
        : array();
    $showPaymentRows = !empty($paymentCreateRows);
    $rateplanPreviewNightly = null;
    $rateplanPreviewTotal = null;
    $rateplanPreviewBreakdown = array();
    if ($createProperty !== '' && $newReservationValues['room_code'] !== '' && $newReservationValues['check_in'] !== '' && $newReservationValues['check_out'] !== '') {
        try {
            $start = new DateTime($newReservationValues['check_in']);
            $end = new DateTime($newReservationValues['check_out']);
            $nights = (int)$start->diff($end)->format('%r%a');
            if ($nights > 0) {
                $rateRows = $reservationsRateplanPricingService->getCalendarPricesByCodes(
                    $createProperty,
                    '',
                    '',
                    $newReservationValues['room_code'],
                    $start->format('Y-m-d'),
                    $nights
                );
                $sum = 0;
                foreach ($rateRows as $row) {
                    if (!isset($row['final_price_cents'])) {
                        continue;
                    }
                    $finalCents = (int)$row['final_price_cents'];
                    $sum += $finalCents;
                    if (isset($row['calendar_date'])) {
                        $rateplanPreviewBreakdown[] = array(
                            'date' => (string)$row['calendar_date'],
                            'amount_cents' => $finalCents
                        );
                    }
                }
                if ($sum > 0) {
                    $rateplanPreviewTotal = $sum;
                    $rateplanPreviewNightly = (int)ceil($sum / $nights);
                }
            }
        } catch (Exception $e) {
            $rateplanPreviewNightly = null;
            $rateplanPreviewTotal = null;
            $rateplanPreviewBreakdown = array();
        }
    }
  ?>
  <div class="form-section payment-section">
    <div class="payment-section-card">
      <div class="payment-top">
      <div class="total-preview payment-base">
        <span class="muted">Tarifa base</span>
        <strong id="estimated-nightly">--</strong>
        <span class="muted" id="estimated-total">--</span>
      </div>
      <div class="payment-head">
        <h4>Secci&oacute;n de pago</h4>
        <label>
          Concepto de hospedaje *
          <select name="create_lodging_catalog_id" required id="create-lodging-catalog">
            <option value=""><?php echo $lodgingOptions ? 'Selecciona un concepto' : 'No hay conceptos configurados'; ?></option>
            <?php foreach ($lodgingOptions as $opt): ?>
              <option value="<?php echo (int)$opt['id']; ?>"
                data-child-id="<?php echo isset($opt['child_id']) ? (int)$opt['child_id'] : 0; ?>"
                data-child-name="<?php echo htmlspecialchars(isset($opt['child_name']) ? (string)$opt['child_name'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                data-child-default="<?php echo isset($opt['child_default_cents']) ? (int)$opt['child_default_cents'] : 0; ?>"
                <?php echo ((int)$newReservationValues['lodging_catalog_id'] === (int)$opt['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>
    <div class="payment-block">
      <div class="payment-row-title">Total por hospedaje</div>
      <div class="payment-row">
        <label>
          Promedio por noche
          <input type="number" step="0.01" name="create_total_nightly" id="create-total-nightly" value="<?php echo htmlspecialchars($newReservationValues['nightly_override'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
        </label>
        <label>
          Total por <span class="nights-label" id="lodging-nights-label">-- noches</span>
          <input type="number" step="0.01" name="create_total_override" id="create-total-override" value="<?php echo htmlspecialchars($newReservationValues['total_override'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
        </label>
      </div>
    </div>
    <?php if (!empty($rateplanPreviewBreakdown)): ?>
    <div class="payment-block">
      <div class="payment-row-title">Desglose por noche</div>
      <div class="nightly-breakdown">
        <?php foreach ($rateplanPreviewBreakdown as $nightRow): ?>
          <div class="nightly-row" data-night-amount="<?php echo htmlspecialchars(number_format(((float)$nightRow['amount_cents']) / 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="nightly-date"><?php echo htmlspecialchars(reservations_format_date($nightRow['date'], 'd M Y'), ENT_QUOTES, 'UTF-8'); ?></span>
            <input
              class="nightly-amount-input"
              type="number"
              step="0.01"
              value="<?php echo htmlspecialchars(number_format(((float)$nightRow['amount_cents']) / 100, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
              data-night-date="<?php echo htmlspecialchars((string)$nightRow['date'], ENT_QUOTES, 'UTF-8'); ?>"
            >
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="payment-block" id="create-fixed-child-row" style="display:none;">
      <div class="payment-row-title" id="create-fixed-child-label">Totales concepto hijo</div>
      <div class="payment-row">
        <label>
          Precio por noche
          <input type="number" step="0.01" name="create_fixed_child_amount" id="create-fixed-child-amount" value="<?php echo htmlspecialchars($newReservationValues['fixed_child_amount'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
        </label>
        <label>
          Total por <span class="nights-label" id="fixed-child-nights-label">-- noches</span>
          <input type="number" step="0.01" name="create_fixed_child_total" id="create-fixed-child-total" value="<?php echo htmlspecialchars($newReservationValues['fixed_child_total'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
        </label>
      </div>
      <input type="hidden" name="create_fixed_child_catalog_id" id="create-fixed-child-catalog-id" value="<?php echo (int)$newReservationValues['fixed_child_catalog_id']; ?>">
    </div>
    <div class="payment-block">
      <div class="payment-row-title">Totales finales</div>
      <div class="payment-row payment-row-final">
        <label>
          Promedio por noche
          <input type="number" step="0.01" id="create-final-nightly" readonly>
        </label>
        <label>
          Total sin impuestos
          <input type="number" step="0.01" id="create-final-total" readonly>
        </label>
        <label>
          Total con impuestos
          <input type="number" step="0.01" id="create-final-total-tax" readonly>
        </label>
      </div>
    </div>
    </div>
    <div class="payment-section-card">
      <div class="payment-block">
        <div class="payment-row-title">Conceptos adicionales (opcional)</div>
        <div class="payment-row payment-row-actions">
          <button type="button" class="button-secondary" id="create-lodging-toggle" <?php echo $showExtraLodging ? 'style="display:none;"' : ''; ?>>Agregar concepto</button>
          <button type="button" class="button-secondary" id="create-lodging-add" <?php echo $showExtraLodging ? '' : 'style="display:none;"'; ?>>Agregar otro</button>
        </div>
        <div class="payment-list" id="create-lodging-list" <?php echo $showExtraLodging ? '' : 'style="display:none;"'; ?>>
          <?php foreach ($normalizedExtraRows as $extraRow): ?>
            <div class="payment-row lodging-extra-row">
              <label>
                Concepto adicional
                <select name="create_extra_lodging_catalog_id[]">
                  <option value=""><?php echo $extraConceptOptions ? 'Selecciona un concepto' : 'No hay conceptos configurados'; ?></option>
                  <?php foreach ($extraConceptOptions as $opt): ?>
                    <option value="<?php echo (int)$opt['id']; ?>" <?php echo (int)$opt['id'] === (int)$extraRow['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Total
                <input type="number" step="0.01" name="create_extra_lodging_amount[]" value="<?php echo htmlspecialchars((string)$extraRow['amount'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
              </label>
              <button type="button" class="button-secondary js-remove-lodging">Quitar</button>
            </div>
          <?php endforeach; ?>
        </div>
        <template id="create-lodging-template">
          <div class="payment-row lodging-extra-row">
            <label>
              Concepto adicional
              <select name="create_extra_lodging_catalog_id[]">
                <option value=""><?php echo $extraConceptOptions ? 'Selecciona un concepto' : 'No hay conceptos configurados'; ?></option>
                <?php foreach ($extraConceptOptions as $opt): ?>
                  <option value="<?php echo (int)$opt['id']; ?>">
                    <?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Total
              <input type="number" step="0.01" name="create_extra_lodging_amount[]" placeholder="0.00">
            </label>
            <button type="button" class="button-secondary js-remove-lodging">Quitar</button>
          </div>
        </template>
      </div>
    </div>
    <div class="payment-section-card">
    <div class="payment-block">
      <div class="payment-row-title">Pagos (opcional)</div>
      <div class="payment-row payment-row-actions">
        <button type="button" class="button-secondary" id="create-payment-toggle" <?php echo $showPaymentRows ? 'style="display:none;"' : ''; ?>>Agregar pago</button>
        <button type="button" class="button-secondary" id="create-payment-add" <?php echo $showPaymentRows ? '' : 'style="display:none;"'; ?>>Agregar otro pago</button>
      </div>
      <div class="payment-list" id="create-payment-list" <?php echo $showPaymentRows ? '' : 'style="display:none;"'; ?>>
        <?php foreach ($paymentCreateRows as $paymentRow): ?>
          <div class="payment-row payment-entry-row">
            <label>
              Concepto de pago
              <select name="create_payment_method[]">
                <?php
                  $methodValue = isset($paymentRow['payment_catalog_id']) ? (int)$paymentRow['payment_catalog_id'] : 0;
                  $createPaymentCatalogs = $allPaymentCatalogs;
                ?>
                <option value="0">(Selecciona concepto)</option>
                <?php foreach ($createPaymentCatalogs as $pc): ?>
                  <?php $pcId = (int)$pc['id_payment_catalog']; ?>
                  <option value="<?php echo $pcId; ?>" <?php echo $methodValue === $pcId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)(isset($pc['label']) ? $pc['label'] : $pc['name']), ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Monto
              <input type="number" step="0.01" name="create_payment_amount[]" value="<?php echo htmlspecialchars(isset($paymentRow['amount']) ? (string)$paymentRow['amount'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
            </label>
            <label>
              Referencia
              <input type="text" name="create_payment_reference[]" value="<?php echo htmlspecialchars(isset($paymentRow['reference']) ? (string)$paymentRow['reference'] : '', ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label>
              Fecha pago
              <?php $paymentDateValue = isset($paymentRow['service_date']) && (string)$paymentRow['service_date'] !== '' ? (string)$paymentRow['service_date'] : date('Y-m-d'); ?>
              <input type="date" name="create_payment_date[]" value="<?php echo htmlspecialchars($paymentDateValue, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <button type="button" class="button-secondary js-remove-payment">Quitar</button>
          </div>
        <?php endforeach; ?>
      </div>
      <template id="create-payment-template">
        <div class="payment-row payment-entry-row">
          <label>
            Concepto de pago
            <select name="create_payment_method[]">
              <?php $createPaymentCatalogs = $allPaymentCatalogs; ?>
              <option value="0">(Selecciona concepto)</option>
              <?php foreach ($createPaymentCatalogs as $pc): ?>
                <?php $pcId = (int)$pc['id_payment_catalog']; ?>
                <option value="<?php echo $pcId; ?>">
                  <?php echo htmlspecialchars((string)(isset($pc['label']) ? $pc['label'] : $pc['name']), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Monto
            <input type="number" step="0.01" name="create_payment_amount[]" placeholder="0.00">
          </label>
          <label>
            Referencia
            <input type="text" name="create_payment_reference[]">
          </label>
          <label>
            Fecha pago
            <input type="date" name="create_payment_date[]" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <button type="button" class="button-secondary js-remove-payment">Quitar</button>
        </div>
      </template>
    </div>
    </div>
  </div>
  <div class="form-actions full">
    <button type="submit">Crear reserva</button>
  </div>
</form>
<?php
$newContent = ob_get_clean();

$staticTabs = array(
    array(
        'id' => 'general',
        'title' => 'General',
        'content' => $generalContent
    )
);

$statusOptions = array('apartado', 'confirmado', 'en casa', 'salida', 'no-show', 'cancelada');
$dynamicTabs = array();
if ($openNewTab) {
    $dynamicTabs[] = array(
        'key' => 'new',
        'title' => 'Nueva reserva',
        'panel_id' => 'reservation-new',
        'close_form_id' => 'reservations-close-new',
        'content' => $newContent
    );
}

foreach ($openReservationIds as $reservationId) {
    $bundle = isset($reservationDetails[$reservationId]) ? $reservationDetails[$reservationId] : array('detail' => null, 'activities' => array(), 'error' => null);
    $detail = isset($bundle['detail']) ? $bundle['detail'] : null;
    $detailError = isset($bundle['error']) ? $bundle['error'] : null;
    $activities = isset($bundle['activities']) ? $bundle['activities'] : array();
    $folios = isset($bundle['folios']) ? $bundle['folios'] : array();
    $saleItems = isset($bundle['sale_items']) ? $bundle['sale_items'] : array();
    $payments = isset($bundle['payments']) ? $bundle['payments'] : array();
    $refunds = isset($bundle['refunds']) ? $bundle['refunds'] : array();
    $interests = isset($bundle['interests']) ? $bundle['interests'] : array();

    if ($reservationId > 0) {
        $hasServicesFolio = false;
        $hasLodgingFolio = false;
        $lodgingFolioToRenameId = 0;
        foreach ($folios as $folioProbe) {
            $probeId = isset($folioProbe['id_folio']) ? (int)$folioProbe['id_folio'] : 0;
            $probeName = isset($folioProbe['folio_name']) ? trim((string)$folioProbe['folio_name']) : '';
            $probeRole = reservations_folio_role_by_name(isset($folioProbe['folio_name']) ? $folioProbe['folio_name'] : '');
            if (
                $probeRole === 'lodging'
                && $probeId > 0
            ) {
                $hasLodgingFolio = true;
                if (
                    ($probeName === '' || in_array(strtolower($probeName), array('principal', 'main'), true))
                    && $lodgingFolioToRenameId <= 0
                ) {
                    $lodgingFolioToRenameId = $probeId;
                }
            }
            if ($probeRole === 'services') {
                $hasServicesFolio = true;
            }
        }
        if ($lodgingFolioToRenameId > 0) {
            try {
                $pdoRenameFolio = pms_get_connection();
                $stmtRenameFolio = $pdoRenameFolio->prepare('UPDATE folio SET folio_name = ? WHERE id_folio = ?');
                $stmtRenameFolio->execute(array('Hospedaje', $lodgingFolioToRenameId));
                foreach ($folios as &$folioProbe) {
                    $probeId = isset($folioProbe['id_folio']) ? (int)$folioProbe['id_folio'] : 0;
                    if ($probeId === $lodgingFolioToRenameId) {
                        $folioProbe['folio_name'] = 'Hospedaje';
                        break;
                    }
                }
                unset($folioProbe);
            } catch (Exception $e) {
            }
        }
        if (!$hasLodgingFolio || $lodgingFolioToRenameId > 0) {
            try {
                $pdoFolioInit = pms_get_connection();
                $folioCurrencyInit = isset($detail['currency']) && trim((string)$detail['currency']) !== ''
                    ? (string)$detail['currency']
                    : 'MXN';
                $folioDueInit = isset($detail['check_out_date']) && trim((string)$detail['check_out_date']) !== ''
                    ? (string)$detail['check_out_date']
                    : null;
                if (!$hasLodgingFolio) {
                    $stmtCreateLodgingFolio = $pdoFolioInit->prepare(
                        'INSERT INTO folio (
                            id_reservation, folio_name, status, currency, total_cents, balance_cents, due_date,
                            is_active, created_at, created_by, updated_at
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
                    );
                    $stmtCreateLodgingFolio->execute(array(
                        $reservationId,
                        'Hospedaje',
                        'open',
                        $folioCurrencyInit,
                        0,
                        0,
                        $folioDueInit,
                        $actorUserId
                    ));
                    $newLodgingFolioId = (int)$pdoFolioInit->lastInsertId();
                    if ($newLodgingFolioId > 0) {
                        $folios[] = array(
                            'id_folio' => $newLodgingFolioId,
                            'id_reservation' => $reservationId,
                            'folio_name' => 'Hospedaje',
                            'status' => 'open',
                            'currency' => $folioCurrencyInit,
                            'total_cents' => 0,
                            'payments_cents' => 0,
                            'refunds_cents' => 0,
                            'balance_cents' => 0,
                            'due_date' => $folioDueInit
                        );
                    }
                    $hasLodgingFolio = true;
                }
            } catch (Exception $e) {
            }
        }
        if (!$hasServicesFolio) {
            try {
                $pdoFolioInit = pms_get_connection();
                $folioCurrencyInit = isset($detail['currency']) && trim((string)$detail['currency']) !== ''
                    ? (string)$detail['currency']
                    : 'MXN';
                $folioDueInit = isset($detail['check_out_date']) && trim((string)$detail['check_out_date']) !== ''
                    ? (string)$detail['check_out_date']
                    : null;
                $stmtCreateServicesFolio = $pdoFolioInit->prepare(
                    'INSERT INTO folio (
                        id_reservation, folio_name, status, currency, total_cents, balance_cents, due_date,
                        is_active, created_at, created_by, updated_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
                );
                $stmtCreateServicesFolio->execute(array(
                    $reservationId,
                    'Servicios',
                    'open',
                    $folioCurrencyInit,
                    0,
                    0,
                    $folioDueInit,
                    $actorUserId
                ));
                $newServiceFolioId = (int)$pdoFolioInit->lastInsertId();
                if ($newServiceFolioId > 0) {
                    $folios[] = array(
                        'id_folio' => $newServiceFolioId,
                        'id_reservation' => $reservationId,
                        'folio_name' => 'Servicios',
                        'status' => 'open',
                        'currency' => $folioCurrencyInit,
                        'total_cents' => 0,
                        'payments_cents' => 0,
                        'refunds_cents' => 0,
                        'balance_cents' => 0,
                        'due_date' => $folioDueInit
                    );
                }
            } catch (Exception $e) {
            }
        }
    }

    // Do not render orphan derived items when they depend on parent total.
    // Independent children (add_to_father_total = 0) must still be visible.
    $paymentCatalogsByFolio = array();
    if ($saleItems) {
        $parentCandidatesByKey = array();
        $parentCandidateCursorByKey = array();
        $parentCandidatesByNameKey = array();
        $parentCandidateCursorByNameKey = array();
        $candidateCatalogById = array();
        $buildParentCandidateKey = function ($folioId, $serviceDate, $catalogId) {
            $folioId = (int)$folioId;
            $catalogId = (int)$catalogId;
            $serviceDate = trim((string)$serviceDate);
            return $folioId . '|' . $serviceDate . '|' . $catalogId;
        };
        $normalizeParentCandidateName = function ($value) {
            $normalized = strtolower(trim((string)$value));
            $normalized = preg_replace('/\s+/', ' ', $normalized);
            return trim((string)$normalized);
        };
        $buildParentCandidateNameKey = function ($folioId, $serviceDate, $name) use ($normalizeParentCandidateName) {
            $folioId = (int)$folioId;
            $serviceDate = trim((string)$serviceDate);
            $name = $normalizeParentCandidateName($name);
            return $folioId . '|' . $serviceDate . '|' . $name;
        };
        foreach ($saleItems as $candidateRow) {
            $candidateId = isset($candidateRow['id_sale_item']) ? (int)$candidateRow['id_sale_item'] : 0;
            $candidateFolioId = isset($candidateRow['id_folio']) ? (int)$candidateRow['id_folio'] : 0;
            $candidateCatalogId = isset($candidateRow['id_sale_item_catalog']) ? (int)$candidateRow['id_sale_item_catalog'] : 0;
            $candidateServiceDate = isset($candidateRow['service_date']) ? (string)$candidateRow['service_date'] : '';
            $candidateName = isset($candidateRow['item_name']) ? (string)$candidateRow['item_name'] : '';
            if ($candidateId <= 0 || $candidateFolioId <= 0 || $candidateCatalogId <= 0) {
                continue;
            }
            $candidateKey = $buildParentCandidateKey($candidateFolioId, $candidateServiceDate, $candidateCatalogId);
            if (!isset($parentCandidatesByKey[$candidateKey])) {
                $parentCandidatesByKey[$candidateKey] = array();
                $parentCandidateCursorByKey[$candidateKey] = 0;
            }
            $parentCandidatesByKey[$candidateKey][] = $candidateId;
            $candidateCatalogById[$candidateId] = $candidateCatalogId;
            $candidateNameKey = $buildParentCandidateNameKey($candidateFolioId, $candidateServiceDate, $candidateName);
            if (trim($candidateName) !== '') {
                if (!isset($parentCandidatesByNameKey[$candidateNameKey])) {
                    $parentCandidatesByNameKey[$candidateNameKey] = array();
                    $parentCandidateCursorByNameKey[$candidateNameKey] = 0;
                }
                $parentCandidatesByNameKey[$candidateNameKey][] = $candidateId;
            }
        }
        foreach ((array)$payments as $paymentProbeRow) {
            $probeFolioId = isset($paymentProbeRow['id_folio']) ? (int)$paymentProbeRow['id_folio'] : 0;
            $probeCatalogId = isset($paymentProbeRow['id_payment_catalog']) ? (int)$paymentProbeRow['id_payment_catalog'] : 0;
            $probePaymentId = isset($paymentProbeRow['id_payment']) ? (int)$paymentProbeRow['id_payment'] : 0;
            $probeLineItemId = isset($paymentProbeRow['id_line_item']) ? (int)$paymentProbeRow['id_line_item'] : 0;
            $probeParentCandidateIds = array();
            if ($probePaymentId > 0) {
                $probeParentCandidateIds[$probePaymentId] = $probePaymentId;
            }
            if ($probeLineItemId > 0) {
                $probeParentCandidateIds[$probeLineItemId] = $probeLineItemId;
            }
            $probeServiceDate = isset($paymentProbeRow['service_date']) ? (string)$paymentProbeRow['service_date'] : '';
            if ($probeFolioId <= 0 || $probeCatalogId <= 0) {
                continue;
            }
            if (!isset($paymentCatalogsByFolio[$probeFolioId])) {
                $paymentCatalogsByFolio[$probeFolioId] = array();
            }
            $paymentCatalogsByFolio[$probeFolioId][$probeCatalogId] = true;
            if ($probeParentCandidateIds) {
                $probeKey = $buildParentCandidateKey($probeFolioId, $probeServiceDate, $probeCatalogId);
                if (!isset($parentCandidatesByKey[$probeKey])) {
                    $parentCandidatesByKey[$probeKey] = array();
                    $parentCandidateCursorByKey[$probeKey] = 0;
                }
                $probeMethodName = isset($paymentProbeRow['method']) ? (string)$paymentProbeRow['method'] : '';
                foreach ($probeParentCandidateIds as $probeParentCandidateId) {
                    $parentCandidatesByKey[$probeKey][] = $probeParentCandidateId;
                    $candidateCatalogById[$probeParentCandidateId] = $probeCatalogId;
                    $probeNameKey = $buildParentCandidateNameKey($probeFolioId, $probeServiceDate, $probeMethodName);
                    if (trim($probeMethodName) !== '') {
                        if (!isset($parentCandidatesByNameKey[$probeNameKey])) {
                            $parentCandidatesByNameKey[$probeNameKey] = array();
                            $parentCandidateCursorByNameKey[$probeNameKey] = 0;
                        }
                        $parentCandidatesByNameKey[$probeNameKey][] = $probeParentCandidateId;
                    }
                }
            }
        }
        foreach ($saleItems as &$siParentLinkRow) {
            $parentSaleIdRow = isset($siParentLinkRow['id_parent_sale_item']) ? (int)$siParentLinkRow['id_parent_sale_item'] : 0;
            if ($parentSaleIdRow > 0) {
                continue;
            }
            $parentCatalogIdRow = isset($siParentLinkRow['parent_sale_item_catalog_id']) ? (int)$siParentLinkRow['parent_sale_item_catalog_id'] : 0;
            $rowCatalogId = isset($siParentLinkRow['id_sale_item_catalog']) ? (int)$siParentLinkRow['id_sale_item_catalog'] : 0;
            $rowId = isset($siParentLinkRow['id_sale_item']) ? (int)$siParentLinkRow['id_sale_item'] : 0;
            $rowFolioId = isset($siParentLinkRow['id_folio']) ? (int)$siParentLinkRow['id_folio'] : 0;
            $rowServiceDate = isset($siParentLinkRow['service_date']) ? (string)$siParentLinkRow['service_date'] : '';
            if ($rowFolioId <= 0) {
                continue;
            }
            if ($parentCatalogIdRow === $rowCatalogId) {
                continue;
            }
            $selectedParentId = 0;
            if ($parentCatalogIdRow > 0) {
                $lookupKey = $buildParentCandidateKey($rowFolioId, $rowServiceDate, $parentCatalogIdRow);
                if (isset($parentCandidatesByKey[$lookupKey]) && $parentCandidatesByKey[$lookupKey]) {
                    $candidates = $parentCandidatesByKey[$lookupKey];
                    $startIdx = isset($parentCandidateCursorByKey[$lookupKey]) ? (int)$parentCandidateCursorByKey[$lookupKey] : 0;
                    $candidateCount = count($candidates);
                    for ($offset = 0; $offset < $candidateCount; $offset++) {
                        $candidateIdx = ($startIdx + $offset) % $candidateCount;
                        $candidateId = (int)$candidates[$candidateIdx];
                        if ($candidateId > 0 && $candidateId !== $rowId) {
                            $selectedParentId = $candidateId;
                            $parentCandidateCursorByKey[$lookupKey] = $candidateIdx + 1;
                            break;
                        }
                    }
                }
            }
            if ($selectedParentId <= 0) {
                $rowDescription = isset($siParentLinkRow['description']) ? (string)$siParentLinkRow['description'] : '';
                $parentNameFromDescription = '';
                if ($rowDescription !== '' && strpos($rowDescription, ' / ') !== false) {
                    $parts = explode(' / ', $rowDescription);
                    $parentNameFromDescription = trim((string)end($parts));
                }
                if ($parentNameFromDescription !== '') {
                    $lookupNameKey = $buildParentCandidateNameKey($rowFolioId, $rowServiceDate, $parentNameFromDescription);
                    if (isset($parentCandidatesByNameKey[$lookupNameKey]) && $parentCandidatesByNameKey[$lookupNameKey]) {
                        $nameCandidates = $parentCandidatesByNameKey[$lookupNameKey];
                        $startNameIdx = isset($parentCandidateCursorByNameKey[$lookupNameKey]) ? (int)$parentCandidateCursorByNameKey[$lookupNameKey] : 0;
                        $nameCandidateCount = count($nameCandidates);
                        for ($offset = 0; $offset < $nameCandidateCount; $offset++) {
                            $candidateIdx = ($startNameIdx + $offset) % $nameCandidateCount;
                            $candidateId = (int)$nameCandidates[$candidateIdx];
                            if ($candidateId > 0 && $candidateId !== $rowId) {
                                $selectedParentId = $candidateId;
                                $parentCandidateCursorByNameKey[$lookupNameKey] = $candidateIdx + 1;
                                break;
                            }
                        }
                    }
                }
            }
            if ($selectedParentId > 0) {
                $siParentLinkRow['id_parent_sale_item'] = $selectedParentId;
                if ($parentCatalogIdRow <= 0 && isset($candidateCatalogById[$selectedParentId])) {
                    $siParentLinkRow['parent_sale_item_catalog_id'] = (int)$candidateCatalogById[$selectedParentId];
                }
            }
        }
        unset($siParentLinkRow);
        $filteredSaleItems = array();
        foreach ($saleItems as $siRow) {
            $parentSaleIdRow = isset($siRow['id_parent_sale_item']) ? (int)$siRow['id_parent_sale_item'] : 0;
            $parentCatalogIdRow = isset($siRow['parent_sale_item_catalog_id']) ? (int)$siRow['parent_sale_item_catalog_id'] : 0;
            $addToFatherTotalRow = array_key_exists('add_to_father_total', $siRow) ? (int)$siRow['add_to_father_total'] : 1;
            $rowFolioId = isset($siRow['id_folio']) ? (int)$siRow['id_folio'] : 0;
            $rowServiceDate = isset($siRow['service_date']) ? (string)$siRow['service_date'] : '';
            $parentLooksLikePaymentCatalog = $rowFolioId > 0
                && $parentCatalogIdRow > 0
                && isset($paymentCatalogsByFolio[$rowFolioId])
                && isset($paymentCatalogsByFolio[$rowFolioId][$parentCatalogIdRow]);
            $parentCatalogHasCandidate = false;
            if ($rowFolioId > 0 && $parentCatalogIdRow > 0) {
                $candidateKeyForParent = $buildParentCandidateKey($rowFolioId, $rowServiceDate, $parentCatalogIdRow);
                $parentCatalogHasCandidate = isset($parentCandidatesByKey[$candidateKeyForParent]) && !empty($parentCandidatesByKey[$candidateKeyForParent]);
            }
            if ($parentCatalogIdRow > 0 && $parentSaleIdRow <= 0 && $addToFatherTotalRow !== 0) {
                if ($parentLooksLikePaymentCatalog || $parentCatalogHasCandidate) {
                    $filteredSaleItems[] = $siRow;
                    continue;
                }
                continue;
            }
            $filteredSaleItems[] = $siRow;
        }
        $saleItems = $filteredSaleItems;
    }

    $propertyCodeDetail = $detail && isset($detail['property_code']) ? strtoupper((string)$detail['property_code']) : '';
    $roomCodeDetail = $detail && isset($detail['room_code']) ? strtoupper((string)$detail['room_code']) : '';
    $guestName = '';
    if ($detail) {
        $guestName = trim(
            (isset($detail['guest_names']) ? (string)$detail['guest_names'] : '')
            . ' '
            . (isset($detail['guest_last_name']) ? (string)$detail['guest_last_name'] : '')
        );
    }
    if ($guestName === '') {
        $guestName = $detail && isset($detail['guest_email']) ? (string)$detail['guest_email'] : ('Reserva #' . $reservationId);
    }
    $tabLabel = sprintf('%s - %s', $guestName, $detail ? reservations_format_date($detail['check_in_date'], 'd M') : '');

    $availableRooms = reservations_rooms_for_property($roomsByProperty, $propertyCodeDetail);
    $roomInfo = reservations_find_room($roomsByProperty, $propertyCodeDetail, $roomCodeDetail);
    $roomDetailOpenKey = $roomCodeDetail;
    if ($roomInfo && isset($roomInfo['id_room']) && (int)$roomInfo['id_room'] > 0) {
        $roomDetailOpenKey = (string)((int)$roomInfo['id_room']);
    }
    $panelId = 'reservation-panel-' . $reservationId;
    $closeFormId = 'reservations-close-' . $reservationId;
    $reservationActionFolioId = 0;
    if ($folios) {
        foreach ($folios as $folioRowForAction) {
            $candidateFolioId = isset($folioRowForAction['id_folio']) ? (int)$folioRowForAction['id_folio'] : 0;
            $candidateFolioRole = reservations_folio_role_by_name(isset($folioRowForAction['folio_name']) ? $folioRowForAction['folio_name'] : '');
            if ($candidateFolioId > 0 && $candidateFolioRole === 'lodging') {
                $reservationActionFolioId = $candidateFolioId;
                break;
            }
        }
    }
    if ($reservationActionFolioId <= 0 && $folios) {
        foreach ($folios as $folioRowForAction) {
            $candidateFolioId = isset($folioRowForAction['id_folio']) ? (int)$folioRowForAction['id_folio'] : 0;
            if ($candidateFolioId > 0) {
                $reservationActionFolioId = $candidateFolioId;
                break;
            }
        }
    }

    $foliosById = array();
    $folioRoleById = array();
    foreach ($folios as $f) {
        if (isset($f['id_folio'])) {
            $folioIdTmp = (int)$f['id_folio'];
            $foliosById[$folioIdTmp] = $f;
            $folioRoleById[$folioIdTmp] = reservations_folio_role_by_name(isset($f['folio_name']) ? $f['folio_name'] : '');
        }
    }
    $itemsByFolio = array();
    foreach ($saleItems as $si) {
        $fid = isset($si['id_folio']) ? (int)$si['id_folio'] : 0;
        if ($fid <= 0) {
            continue;
        }
        if (!isset($itemsByFolio[$fid])) {
            $itemsByFolio[$fid] = array();
        }
        $itemsByFolio[$fid][] = $si;
    }
    $taxItemsBySale = array();
    foreach ($taxItems as $ti) {
        $saleId = isset($ti['id_sale_item']) ? (int)$ti['id_sale_item'] : 0;
        if ($saleId <= 0) {
            continue;
        }
        if (!isset($taxItemsBySale[$saleId])) {
            $taxItemsBySale[$saleId] = array();
        }
        $taxItemsBySale[$saleId][] = $ti;
    }
    $saleItemsById = array();
    $childrenByParent = array();
    $paymentLineItemsById = array();
    foreach ((array)$payments as $paymentRow) {
        $paymentLineId = isset($paymentRow['id_payment']) ? (int)$paymentRow['id_payment'] : 0;
        $paymentLineItemId = isset($paymentRow['id_line_item']) ? (int)$paymentRow['id_line_item'] : 0;
        $paymentParentIds = array();
        if ($paymentLineId > 0) {
            $paymentParentIds[$paymentLineId] = $paymentLineId;
        }
        if ($paymentLineItemId > 0) {
            $paymentParentIds[$paymentLineItemId] = $paymentLineItemId;
        }
        if (!$paymentParentIds) {
            continue;
        }
        foreach ($paymentParentIds as $paymentParentId) {
            $paymentLineItemsById[$paymentParentId] = $paymentRow;
        }
    }
	    foreach ($saleItems as $si) {
	        $saleId = isset($si['id_sale_item']) ? (int)$si['id_sale_item'] : 0;
	        if ($saleId <= 0) {
	            continue;
        }
        $saleItemsById[$saleId] = $si;
        $parentId = isset($si['id_parent_sale_item']) ? (int)$si['id_parent_sale_item'] : 0;
        if ($parentId > 0) {
            if (!isset($childrenByParent[$parentId])) {
                $childrenByParent[$parentId] = array();
            }
	            $childrenByParent[$parentId][] = $saleId;
	        }
	    }
	    $primaryLodgingLineItemId = reservations_find_primary_lodging_line_item_id($companyId, $reservationId);
	    $addToFatherMap = array();
	    $showInFolioRelationMap = array();
    $hasDerivedFlag = false;
    foreach ($saleItems as $si) {
        if (array_key_exists('add_to_father_total', $si)) {
            $hasDerivedFlag = true;
            break;
        }
    }
    if ($hasDerivedFlag) {
        foreach ($childrenByParent as $parentId => $childIds) {
            $parentCatalogId = 0;
            if (isset($saleItemsById[$parentId]['id_sale_item_catalog'])) {
                $parentCatalogId = (int)$saleItemsById[$parentId]['id_sale_item_catalog'];
            } elseif (isset($paymentLineItemsById[$parentId])) {
                $parentCatalogId = isset($paymentLineItemsById[$parentId]['id_payment_catalog'])
                    ? (int)$paymentLineItemsById[$parentId]['id_payment_catalog']
                    : 0;
            }
            if ($parentCatalogId <= 0) {
                continue;
            }
            foreach ($childIds as $childId) {
                if (!isset($saleItemsById[$childId])) {
                    continue;
                }
                $childRow = $saleItemsById[$childId];
                $childCatalogId = isset($childRow['id_sale_item_catalog']) ? (int)$childRow['id_sale_item_catalog'] : 0;
                if ($childCatalogId <= 0) {
                    continue;
                }
                $key = $childCatalogId . ':' . $parentCatalogId;
                $addToFatherMap[$key] = isset($childRow['add_to_father_total']) ? (int)$childRow['add_to_father_total'] : 0;
                if (array_key_exists('show_in_folio_relation', $childRow) && $childRow['show_in_folio_relation'] !== null) {
                    $showInFolioRelationMap[$key] = !empty($childRow['show_in_folio_relation']) ? 1 : 0;
                } elseif (array_key_exists('show_in_folio_effective', $childRow) && $childRow['show_in_folio_effective'] !== null) {
                    $showInFolioRelationMap[$key] = !empty($childRow['show_in_folio_effective']) ? 1 : 0;
                }
            }
        }
    } else {
        $relationPairs = array();
        foreach ($childrenByParent as $parentId => $childIds) {
            $parentCatalogId = 0;
            if (isset($saleItemsById[$parentId]['id_sale_item_catalog'])) {
                $parentCatalogId = (int)$saleItemsById[$parentId]['id_sale_item_catalog'];
            } elseif (isset($paymentLineItemsById[$parentId])) {
                $parentCatalogId = isset($paymentLineItemsById[$parentId]['id_payment_catalog'])
                    ? (int)$paymentLineItemsById[$parentId]['id_payment_catalog']
                    : 0;
            }
            if ($parentCatalogId <= 0) {
                continue;
            }
            foreach ($childIds as $childId) {
                $childCatalogId = isset($saleItemsById[$childId]['id_sale_item_catalog']) ? (int)$saleItemsById[$childId]['id_sale_item_catalog'] : 0;
                if ($childCatalogId <= 0) {
                    continue;
                }
                $key = $childCatalogId . ':' . $parentCatalogId;
                $relationPairs[$key] = array($childCatalogId, $parentCatalogId);
            }
        }
        if ($relationPairs) {
            try {
                $pdo = pms_get_connection();
                $pairValues = array_values($relationPairs);
                $conditions = array();
                $params = array();
                foreach ($pairValues as $pair) {
                    $conditions[] = '(id_sale_item_catalog = ? AND id_parent_sale_item_catalog = ?)';
                    $params[] = $pair[0];
                    $params[] = $pair[1];
                }
                $sql = 'SELECT id_sale_item_catalog, id_parent_sale_item_catalog, add_to_father_total, show_in_folio_relation
                        FROM line_item_catalog_parent
                        WHERE deleted_at IS NULL AND is_active = 1
                          AND (' . implode(' OR ', $conditions) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                foreach ($rows as $row) {
                    $childCatalogId = isset($row['id_sale_item_catalog']) ? (int)$row['id_sale_item_catalog'] : 0;
                    $parentCatalogId = isset($row['id_parent_sale_item_catalog']) ? (int)$row['id_parent_sale_item_catalog'] : 0;
                    if ($childCatalogId <= 0 || $parentCatalogId <= 0) {
                        continue;
                    }
                    $key = $childCatalogId . ':' . $parentCatalogId;
                    $addToFatherMap[$key] = isset($row['add_to_father_total']) ? (int)$row['add_to_father_total'] : 1;
                    if (array_key_exists('show_in_folio_relation', $row) && $row['show_in_folio_relation'] !== null) {
                        $showInFolioRelationMap[$key] = !empty($row['show_in_folio_relation']) ? 1 : 0;
                    }
                }
            } catch (Exception $e) {
                $addToFatherMap = array();
                $showInFolioRelationMap = array();
            }
        }
    }
    $childContributesToParentTotal = function ($parentId, $childId) use ($saleItemsById, $addToFatherMap, $showInFolioRelationMap) {
        if (!isset($saleItemsById[$parentId]) || !isset($saleItemsById[$childId])) {
            return false;
        }
        $childRow = $saleItemsById[$childId];
        if (!reservations_line_item_is_active_for_summary($childRow, false)) {
            return false;
        }
        if (reservations_line_item_is_payment_like($childRow)) {
            return false;
        }
        $parentCatalogId = isset($saleItemsById[$parentId]['id_sale_item_catalog']) ? (int)$saleItemsById[$parentId]['id_sale_item_catalog'] : 0;
        $childCatalogId = isset($childRow['id_sale_item_catalog']) ? (int)$childRow['id_sale_item_catalog'] : 0;
        $relationKey = ($childCatalogId > 0 && $parentCatalogId > 0) ? ($childCatalogId . ':' . $parentCatalogId) : '';
        $shouldAdd = ($relationKey !== '' && isset($addToFatherMap[$relationKey]))
            ? (int)$addToFatherMap[$relationKey]
            : (array_key_exists('add_to_father_total', $childRow) ? (int)$childRow['add_to_father_total'] : 0);
        if ($shouldAdd !== 1) {
            return false;
        }

        $relationShowInFolio = null;
        if ($relationKey !== '' && array_key_exists($relationKey, $showInFolioRelationMap)) {
            $relationShowInFolio = (int)$showInFolioRelationMap[$relationKey];
        } elseif (array_key_exists('show_in_folio_relation', $childRow) && $childRow['show_in_folio_relation'] !== null) {
            $relationShowInFolio = !empty($childRow['show_in_folio_relation']) ? 1 : 0;
        } elseif (array_key_exists('show_in_folio_effective', $childRow) && $childRow['show_in_folio_effective'] !== null) {
            $relationShowInFolio = !empty($childRow['show_in_folio_effective']) ? 1 : 0;
        } elseif (array_key_exists('show_in_folio', $childRow) && $childRow['show_in_folio'] !== null) {
            $relationShowInFolio = !empty($childRow['show_in_folio']) ? 1 : 0;
        }

        if ($relationShowInFolio !== null && (int)$relationShowInFolio !== 1) {
            return false;
        }
        return true;
    };

    $collapsedChildrenByParent = array();
    foreach ($childrenByParent as $parentId => $childIds) {
        $parentCatalogId = isset($saleItemsById[$parentId]['id_sale_item_catalog']) ? (int)$saleItemsById[$parentId]['id_sale_item_catalog'] : 0;
        if ($parentCatalogId <= 0) {
            continue;
        }
        foreach ($childIds as $childId) {
            $childCatalogId = isset($saleItemsById[$childId]['id_sale_item_catalog']) ? (int)$saleItemsById[$childId]['id_sale_item_catalog'] : 0;
            if ($childCatalogId <= 0) {
                continue;
            }
            if ($childContributesToParentTotal($parentId, $childId)) {
                if (!isset($collapsedChildrenByParent[$parentId])) {
                    $collapsedChildrenByParent[$parentId] = array();
                }
                $collapsedChildrenByParent[$parentId][] = $childId;
            }
        }
    }
    $finalTotalCache = array();
    $finalTotalForSaleItem = function ($saleItemId) use (&$finalTotalForSaleItem, &$finalTotalCache, $saleItemsById, $childrenByParent, $childContributesToParentTotal) {
        if (isset($finalTotalCache[$saleItemId])) {
            return $finalTotalCache[$saleItemId];
        }
        $sum = 0;
        $parentFolioId = 0;
        if (isset($saleItemsById[$saleItemId]) && isset($saleItemsById[$saleItemId]['amount_cents'])) {
            $sum += (int)$saleItemsById[$saleItemId]['amount_cents'];
            $parentFolioId = isset($saleItemsById[$saleItemId]['id_folio']) ? (int)$saleItemsById[$saleItemId]['id_folio'] : 0;
        }
        if (isset($childrenByParent[$saleItemId])) {
            foreach ($childrenByParent[$saleItemId] as $childId) {
                if (!$childContributesToParentTotal($saleItemId, $childId)) {
                    continue;
                }
                if (
                    $parentFolioId > 0
                    && isset($saleItemsById[$childId])
                    && isset($saleItemsById[$childId]['id_folio'])
                    && (int)$saleItemsById[$childId]['id_folio'] !== $parentFolioId
                ) {
                    continue;
                }
                $sum += $finalTotalForSaleItem($childId);
            }
        }
        $finalTotalCache[$saleItemId] = $sum;
        return $sum;
    };
    $paymentsByFolio = array();
    $visiblePaymentIds = array();
    foreach ($payments as $p) {
        $paymentId = isset($p['id_payment']) ? (int)$p['id_payment'] : 0;
        if ($paymentId > 0) {
            $visiblePaymentIds[$paymentId] = true;
        }
        $fid = isset($p['id_folio']) ? (int)$p['id_folio'] : 0;
        if ($fid <= 0) {
            continue;
        }
        if (!isset($paymentsByFolio[$fid])) {
            $paymentsByFolio[$fid] = array();
        }
        $paymentsByFolio[$fid][] = $p;
    }
    $refundsByPayment = array();
    $refundsByFolio = array();
    foreach ($refunds as $r) {
        $pid = isset($r['id_payment']) ? (int)$r['id_payment'] : 0;
        if ($pid <= 0 || !isset($visiblePaymentIds[$pid])) {
            continue;
        }
        if (!isset($refundsByPayment[$pid])) {
            $refundsByPayment[$pid] = array();
        }
        $refundsByPayment[$pid][] = $r;
        $rFolioId = isset($r['id_folio']) ? (int)$r['id_folio'] : 0;
        if ($rFolioId > 0) {
            if (!isset($refundsByFolio[$rFolioId])) {
                $refundsByFolio[$rFolioId] = 0;
            }
            $refundsByFolio[$rFolioId] += (int)(isset($r['amount_cents']) ? $r['amount_cents'] : 0);
        }
    }

    $isVisibleSaleItemInFolio = function ($siRow) use ($saleItemsById, $paymentLineItemsById, $paymentCatalogsByFolio, $addToFatherMap, $showInFolioRelationMap) {
        if (reservations_line_item_is_payment_like($siRow)) {
            return false;
        }
        $parentSaleId = isset($siRow['id_parent_sale_item']) ? (int)$siRow['id_parent_sale_item'] : 0;
        $rowFolioId = isset($siRow['id_folio']) ? (int)$siRow['id_folio'] : 0;
        $parentCatalogIdFromRow = isset($siRow['parent_sale_item_catalog_id']) ? (int)$siRow['parent_sale_item_catalog_id'] : 0;
        $parentIsPaymentLike = ($parentSaleId > 0 && isset($paymentLineItemsById[$parentSaleId]))
            || (
                $rowFolioId > 0
                && $parentCatalogIdFromRow > 0
                && isset($paymentCatalogsByFolio[$rowFolioId])
                && isset($paymentCatalogsByFolio[$rowFolioId][$parentCatalogIdFromRow])
            );
        $childCatalogId = isset($siRow['id_sale_item_catalog']) ? (int)$siRow['id_sale_item_catalog'] : 0;
        $addToFatherRow = array_key_exists('add_to_father_total', $siRow) ? (int)$siRow['add_to_father_total'] : null;
        $defaultShowInFolio = 1;
        if (array_key_exists('show_in_folio', $siRow) && $siRow['show_in_folio'] !== null) {
            $defaultShowInFolio = !empty($siRow['show_in_folio']) ? 1 : 0;
        }
        $effectiveShowInFolio = null;
        if (array_key_exists('show_in_folio_effective', $siRow) && $siRow['show_in_folio_effective'] !== null) {
            $effectiveShowInFolio = !empty($siRow['show_in_folio_effective']) ? 1 : 0;
        }
        $relationShowInFolio = null;
        if (array_key_exists('show_in_folio_relation', $siRow) && $siRow['show_in_folio_relation'] !== null) {
            $relationShowInFolio = !empty($siRow['show_in_folio_relation']) ? 1 : 0;
        }
        if ($parentSaleId > 0 && !isset($saleItemsById[$parentSaleId]) && !$parentIsPaymentLike) {
            if ($addToFatherRow === null || $addToFatherRow !== 0) {
                return false;
            }
        }
        if ($parentSaleId > 0 || $parentCatalogIdFromRow > 0) {
            $parentCatalogId = 0;
            if (isset($saleItemsById[$parentSaleId]['id_sale_item_catalog'])) {
                $parentCatalogId = (int)$saleItemsById[$parentSaleId]['id_sale_item_catalog'];
            } elseif ($parentIsPaymentLike) {
                if ($parentSaleId > 0 && isset($paymentLineItemsById[$parentSaleId])) {
                    $parentCatalogId = isset($paymentLineItemsById[$parentSaleId]['id_payment_catalog'])
                        ? (int)$paymentLineItemsById[$parentSaleId]['id_payment_catalog']
                        : 0;
                } elseif ($parentCatalogIdFromRow > 0) {
                    $parentCatalogId = $parentCatalogIdFromRow;
                }
            }
            $relationKey = ($childCatalogId > 0 && $parentCatalogId > 0) ? ($childCatalogId . ':' . $parentCatalogId) : '';
            if ($relationKey !== '' && array_key_exists($relationKey, $showInFolioRelationMap)) {
                $relationShowInFolio = (int)$showInFolioRelationMap[$relationKey];
            }
            if ($relationShowInFolio === null && $effectiveShowInFolio !== null) {
                $relationShowInFolio = (int)$effectiveShowInFolio;
            }
            if ($relationKey !== '' && isset($addToFatherMap[$relationKey])) {
                if ((int)$addToFatherMap[$relationKey] !== 0 && !$parentIsPaymentLike) {
                    return false;
                }
            } elseif ($addToFatherRow !== null && $addToFatherRow !== 0 && !$parentIsPaymentLike) {
                return false;
            }
            if ($relationShowInFolio !== null) {
                return (int)$relationShowInFolio === 1;
            }
            return $defaultShowInFolio === 1;
        }
        if ($effectiveShowInFolio !== null) {
            return (int)$effectiveShowInFolio === 1;
        }
        return $defaultShowInFolio === 1;
    };

    $visibleItemsByFolio = array();
    $visibleChargesByFolio = array();
    $hasVisibleLodgingSaleItems = false;
    $visibleChargesGrandTotalCents = 0;
    foreach ($saleItems as $si) {
        $saleItemId = isset($si['id_sale_item']) ? (int)$si['id_sale_item'] : 0;
        $folioId = isset($si['id_folio']) ? (int)$si['id_folio'] : 0;
        if ($saleItemId <= 0 || $folioId <= 0) {
            continue;
        }
        if (!reservations_line_item_is_active_for_summary($si, false)) {
            continue;
        }
        if (reservations_line_item_is_payment_like($si)) {
            continue;
        }
        if (!$isVisibleSaleItemInFolio($si)) {
            continue;
        }
        if (!isset($visibleItemsByFolio[$folioId])) {
            $visibleItemsByFolio[$folioId] = array();
        }
        if (!isset($visibleChargesByFolio[$folioId])) {
            $visibleChargesByFolio[$folioId] = 0;
        }
        $visibleItemsByFolio[$folioId][] = $si;
        $rowFinalCents = (int)$finalTotalForSaleItem($saleItemId);
        $visibleChargesByFolio[$folioId] += $rowFinalCents;
        $visibleChargesGrandTotalCents += $rowFinalCents;
        $rowType = strtolower(trim(isset($si['item_type']) ? (string)$si['item_type'] : ''));
        if ($rowType === 'sale_item') {
            $folioRole = isset($folioRoleById[$folioId]) ? (string)$folioRoleById[$folioId] : 'lodging';
            if ($folioRole !== 'services') {
                $hasVisibleLodgingSaleItems = true;
            }
        }
    }

    $displayMetricsByFolio = array();
    $displaySummaryChargesCents = 0;
    $displaySummaryPaymentsNetCents = 0;
    $displaySummaryBalanceCents = 0;
    foreach ($folios as $folioMetric) {
        $folioMetricId = isset($folioMetric['id_folio']) ? (int)$folioMetric['id_folio'] : 0;
        if ($folioMetricId <= 0) {
            continue;
        }
        $chargeCents = isset($visibleChargesByFolio[$folioMetricId]) ? (int)$visibleChargesByFolio[$folioMetricId] : 0;
        $paymentCents = 0;
        if (isset($paymentsByFolio[$folioMetricId])) {
            foreach ($paymentsByFolio[$folioMetricId] as $p) {
                $paymentCents += (int)(isset($p['amount_cents']) ? $p['amount_cents'] : 0);
            }
        }
        $refundCents = isset($refundsByFolio[$folioMetricId]) ? (int)$refundsByFolio[$folioMetricId] : 0;
        $paymentNetCents = $paymentCents - $refundCents;
        $balanceCents = $chargeCents - $paymentNetCents;
        $displayMetricsByFolio[$folioMetricId] = array(
            'charges_cents' => $chargeCents,
            'payments_cents' => $paymentCents,
            'refunds_cents' => $refundCents,
            'payments_net_cents' => $paymentNetCents,
            'balance_cents' => $balanceCents
        );
        $displaySummaryChargesCents += $chargeCents;
        $displaySummaryPaymentsNetCents += $paymentNetCents;
        $displaySummaryBalanceCents += $balanceCents;
    }

    $notesList = isset($detailBundle['notes']) && is_array($detailBundle['notes']) ? $detailBundle['notes'] : array();
    $notesError = isset($detailBundle['notes_error']) ? (string)$detailBundle['notes_error'] : '';
    if (!$notesList) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT id_reservation_note, id_reservation, note_type, note_text, is_active, deleted_at, created_at, created_by, updated_at
                 FROM reservation_note
                 WHERE id_reservation = ?
                   AND deleted_at IS NULL
                   AND is_active = 1
                 ORDER BY created_at DESC, id_reservation_note DESC'
            );
            $stmt->execute(array($reservationId));
            $notesList = $stmt->fetchAll();
        } catch (Exception $e) {
            if ($notesError === '') {
                $notesError = $e->getMessage();
            }
        }
    }
    $latestNoteText = '';
    if ($notesList) {
        $latestNoteText = trim(isset($notesList[0]['note_text']) ? (string)$notesList[0]['note_text'] : '');
    }

    $summaryGuest = trim(
        (isset($detail['guest_names']) ? (string)$detail['guest_names'] : '')
        . ' '
        . (isset($detail['guest_last_name']) ? (string)$detail['guest_last_name'] : '')
    );
    if ($summaryGuest === '') {
        $summaryGuest = isset($detail['guest_email']) ? (string)$detail['guest_email'] : '';
    }
    if ($summaryGuest === '' && $latestNoteText !== '') {
        $summaryGuest = $latestNoteText;
    }
    if ($summaryGuest === '') {
        $summaryGuest = trim(isset($detail['notes_internal']) ? (string)$detail['notes_internal'] : '');
    }
    if ($summaryGuest === '') {
        $summaryGuest = trim(isset($detail['notes_guest']) ? (string)$detail['notes_guest'] : '');
    }
    if ($summaryGuest === '') {
        $summaryGuest = 'Sin hu&eacute;sped';
    }
    $summaryBalanceCents = isset($detail['balance_due_cents']) ? (int)$detail['balance_due_cents'] : 0;
    $summaryTotalCents = isset($detail['total_price_cents']) ? (int)$detail['total_price_cents'] : 0;
    if ($folios) {
        $summaryBalanceCents = (int)$displaySummaryBalanceCents;
        $summaryTotalCents = (int)$displaySummaryChargesCents;
    }
    $summaryCurrency = isset($detail['currency']) ? (string)$detail['currency'] : 'MXN';
    $summaryProperty = isset($detail['property_name']) ? (string)$detail['property_name'] : '';
    $summaryCategory = isset($detail['category_name']) ? (string)$detail['category_name'] : '';
    $summaryRoom = $roomInfo ? reservations_room_label($roomInfo) : (isset($detail['room_code']) ? (string)$detail['room_code'] : '');
    $summaryCheckIn = isset($detail['check_in_date']) ? (string)$detail['check_in_date'] : '';
    $summaryCheckOut = isset($detail['check_out_date']) ? (string)$detail['check_out_date'] : '';
    $summaryAdults = isset($detail['adults']) ? (int)$detail['adults'] : 0;
    $summaryChildren = isset($detail['children']) ? (int)$detail['children'] : 0;
    $summaryPeople = $summaryAdults + $summaryChildren;
    $summaryNights = 0;
    try {
        if ($summaryCheckIn !== '' && $summaryCheckOut !== '') {
            $summaryStart = new DateTime($summaryCheckIn);
            $summaryEnd = new DateTime($summaryCheckOut);
            $summaryNights = (int)$summaryStart->diff($summaryEnd)->format('%r%a');
            if ($summaryNights < 0) {
                $summaryNights = 0;
            }
        }
    } catch (Exception $e) {
        $summaryNights = 0;
    }
    $summaryHasCharges = $hasVisibleLodgingSaleItems;
    $statusRequirementsSnapshot = reservations_status_requirements_snapshot($companyCode, $reservationId);
    $summaryHasChargesForStatus = $summaryHasCharges;
    if (
        !$summaryHasChargesForStatus
        && $statusRequirementsSnapshot
        && !empty($statusRequirementsSnapshot['has_charges'])
    ) {
        $summaryHasChargesForStatus = true;
    }
    $summaryNightlyCents = ($summaryHasCharges && $summaryNights > 0) ? (int)round($summaryTotalCents / $summaryNights) : 0;
    $summaryTotalDisplay = $summaryHasCharges ? reservations_format_money($summaryTotalCents, $summaryCurrency) : '--';
    $summaryNightlyDisplay = ($summaryHasCharges && $summaryNights > 0)
        ? reservations_format_money($summaryNightlyCents, $summaryCurrency)
        : '--';
    $isHold = $detail && isset($detail['status']) && strtolower((string)$detail['status']) === 'apartado';
    $linkedGuestId = 0;
    if (isset($detail['id_guest']) && (int)$detail['id_guest'] > 0) {
        $linkedGuestId = (int)$detail['id_guest'];
    } elseif (isset($detail['guest_id']) && (int)$detail['guest_id'] > 0) {
        $linkedGuestId = (int)$detail['guest_id'];
    } elseif (isset($detail['guest_email']) && trim((string)$detail['guest_email']) !== '') {
        try {
            $pdoGuest = pms_get_connection();
            $stmtGuest = $pdoGuest->prepare('SELECT id_guest FROM guest WHERE email = ? LIMIT 1');
            $stmtGuest->execute(array(trim((string)$detail['guest_email'])));
            $guestFound = $stmtGuest->fetchColumn();
            if ($guestFound !== false) {
                $linkedGuestId = (int)$guestFound;
            }
        } catch (Exception $e) {
            $linkedGuestId = 0;
        }
    }
    $hasGuestAssigned = $linkedGuestId > 0;
    $hasGuestForStatus = $hasGuestAssigned;
    if (
        !$hasGuestForStatus
        && $statusRequirementsSnapshot
        && !empty($statusRequirementsSnapshot['has_guest'])
    ) {
        $hasGuestForStatus = true;
    }
    $statusEditLocked = (!$hasGuestForStatus || !$summaryHasChargesForStatus);
    $missingRequirementsForStatus = array();
    if (!$hasGuestForStatus) {
        $missingRequirementsForStatus[] = 'hu&eacute;sped';
    }
    if (!$summaryHasChargesForStatus) {
        $missingRequirementsForStatus[] = 'cobro';
    }
    $missingRequirementsText = implode(' y ', $missingRequirementsForStatus);
    $statusLockTitle = '';
    if ($statusEditLocked) {
        $statusLockTitle = $missingRequirementsText !== ''
            ? ('Agrega ' . $missingRequirementsText . ' para habilitar cambio de estatus')
            : 'Agrega hu&eacute;sped y cobro para habilitar cambio de estatus';
    }
    $statusOptionsForEdit = $statusEditLocked ? array('apartado') : $statusOptions;
    $summaryStatusNormalized = strtolower(trim(isset($detail['status']) ? (string)$detail['status'] : ''));
    $statusCheckoutLocked = $summaryBalanceCents > 0;
    $statusCheckoutLockTitle = $statusCheckoutLocked
        ? 'Liquida el balance pendiente para habilitar check-out'
        : '';
    $summaryCheckInShort = reservations_format_day_month_es($summaryCheckIn);
    $summaryCheckOutShort = reservations_format_day_month_es($summaryCheckOut);
    $summaryRoomHeaderLabel = '';
    if ($roomInfo && isset($roomInfo['name']) && trim((string)$roomInfo['name']) !== '') {
        $summaryRoomHeaderLabel = trim((string)$roomInfo['name']);
    } elseif (isset($detail['room_name']) && trim((string)$detail['room_name']) !== '') {
        $summaryRoomHeaderLabel = trim((string)$detail['room_name']);
    } elseif ($roomCodeDetail !== '') {
        $summaryRoomHeaderLabel = $roomCodeDetail;
    } else {
        $summaryRoomHeaderLabel = isset($detail['room_code']) ? (string)$detail['room_code'] : '';
    }
    $headerAlertVisible = false;
    $headerAlertText = '';
    $headerAlertButton = '';
    $headerAlertClass = '';
    $headerAlertWizardStep = '1';
    $headerAlertReplaceLodging = false;
    if ($isHold) {
        $headerAlertVisible = true;
        $headerAlertText = $missingRequirementsText !== ''
            ? ('Reservaci&oacute;n sin confirmar, agregue ' . $missingRequirementsText)
            : 'Reservaci&oacute;n sin confirmar';
        $headerAlertButton = 'Confirmar reserva';
        $headerAlertClass = 'is-critical';
        $headerAlertReplaceLodging = $hasGuestForStatus && !$summaryHasChargesForStatus;
        $headerAlertWizardStep = $headerAlertReplaceLodging ? '2' : '1';
    }

    $otaDetectedId = 0;
    $otaDetectedByLodging = false;
    $otaDetectionConceptLabels = array();
    $detailOtaIdForInfo = isset($detail['id_ota_account']) ? (int)$detail['id_ota_account'] : 0;
    $detailSourceIdForInfo = isset($detail['id_reservation_source']) ? (int)$detail['id_reservation_source'] : 0;
    $reservationInfoConfigRows = array();
    $otaInfoRows = array();
    $otaInfoRowIndexByCatalog = array();
    $otaInfoAllowedFolioIds = array();
    foreach ($folioRoleById as $folioIdForOtaInfo => $folioRoleForOtaInfo) {
        $folioIdForOtaInfo = (int)$folioIdForOtaInfo;
        if ($folioIdForOtaInfo <= 0) {
            continue;
        }
        if ((string)$folioRoleForOtaInfo === 'services') {
            continue;
        }
        $otaInfoAllowedFolioIds[] = $folioIdForOtaInfo;
    }
    if (!$otaInfoAllowedFolioIds && $foliosById) {
        $otaInfoAllowedFolioIds = array_map('intval', array_keys($foliosById));
    }
    $otaInfoAllowedFolioIds = array_values(array_unique(array_filter($otaInfoAllowedFolioIds, function ($v) {
        return (int)$v > 0;
    })));

    $otaDetectionScore = array();
    foreach ($saleItems as $otaDetectRow) {
        $otaDetectFolioId = isset($otaDetectRow['id_folio']) ? (int)$otaDetectRow['id_folio'] : 0;
        if ($otaInfoAllowedFolioIds && $otaDetectFolioId > 0 && !in_array($otaDetectFolioId, $otaInfoAllowedFolioIds, true)) {
            continue;
        }
        if (!reservations_line_item_is_active_for_summary($otaDetectRow, true)) {
            continue;
        }
        $catalogId = isset($otaDetectRow['id_sale_item_catalog']) ? (int)$otaDetectRow['id_sale_item_catalog'] : 0;
        if ($catalogId <= 0 || !isset($otaLodgingCatalogByCatalogId[$catalogId])) {
            continue;
        }
        foreach ($otaLodgingCatalogByCatalogId[$catalogId] as $candidateOtaId => $tmpTrue) {
            $candidateOtaId = (int)$candidateOtaId;
            if ($candidateOtaId <= 0) {
                continue;
            }
            if (!isset($otaDetectionScore[$candidateOtaId])) {
                $otaDetectionScore[$candidateOtaId] = array(
                    'match_count' => 0,
                    'catalogs' => array(),
                    'labels' => array()
                );
            }
            $otaDetectionScore[$candidateOtaId]['match_count']++;
            $otaDetectionScore[$candidateOtaId]['catalogs'][$catalogId] = true;
            $label = isset($otaLodgingLabelByAccountCatalog[$candidateOtaId][$catalogId])
                ? (string)$otaLodgingLabelByAccountCatalog[$candidateOtaId][$catalogId]
                : ('Catalogo #' . $catalogId);
            $otaDetectionScore[$candidateOtaId]['labels'][$label] = true;
        }
    }

    $bestScoreCount = -1;
    $bestScoreCatalogs = -1;
    $bestScoreOtaId = 0;
    foreach ($otaDetectionScore as $candidateOtaId => $scoreRow) {
        $candidateCount = isset($scoreRow['match_count']) ? (int)$scoreRow['match_count'] : 0;
        $candidateCatalogs = isset($scoreRow['catalogs']) && is_array($scoreRow['catalogs']) ? count($scoreRow['catalogs']) : 0;
        if (
            $candidateCount > $bestScoreCount
            || ($candidateCount === $bestScoreCount && $candidateCatalogs > $bestScoreCatalogs)
            || (
                $candidateCount === $bestScoreCount
                && $candidateCatalogs === $bestScoreCatalogs
                && ($bestScoreOtaId <= 0 || (int)$candidateOtaId < $bestScoreOtaId)
            )
        ) {
            $bestScoreCount = $candidateCount;
            $bestScoreCatalogs = $candidateCatalogs;
            $bestScoreOtaId = (int)$candidateOtaId;
        }
    }
    if ($bestScoreOtaId <= 0 && $reservationId > 0 && $otaLodgingCatalogByCatalogId) {
        try {
            $pdoOtaDetect = pms_get_connection();
            $sqlOtaDetect =
                'SELECT li.id_line_item_catalog
                 FROM line_item li
                 JOIN folio f
                   ON f.id_folio = li.id_folio
                  AND f.deleted_at IS NULL
                 WHERE f.id_reservation = ?
                   AND li.item_type = "sale_item"
                   AND li.deleted_at IS NULL
                   AND COALESCE(li.is_active, 1) = 1
                   AND (li.status IS NULL OR li.status NOT IN ("void","canceled","cancelled"))';
            $paramsOtaDetect = array((int)$reservationId);
            if ($otaInfoAllowedFolioIds) {
                $sqlOtaDetect .= ' AND li.id_folio IN (' . implode(',', array_fill(0, count($otaInfoAllowedFolioIds), '?')) . ')';
                $paramsOtaDetect = array_merge($paramsOtaDetect, array_map('intval', $otaInfoAllowedFolioIds));
            }
            $stmtOtaDetect = $pdoOtaDetect->prepare($sqlOtaDetect);
            $stmtOtaDetect->execute($paramsOtaDetect);
            $rowsOtaDetect = $stmtOtaDetect->fetchAll();
            foreach ($rowsOtaDetect as $rowOtaDetect) {
                $catalogId = isset($rowOtaDetect['id_line_item_catalog']) ? (int)$rowOtaDetect['id_line_item_catalog'] : 0;
                if ($catalogId <= 0 || !isset($otaLodgingCatalogByCatalogId[$catalogId])) {
                    continue;
                }
                foreach ($otaLodgingCatalogByCatalogId[$catalogId] as $candidateOtaId => $tmpTrue) {
                    $candidateOtaId = (int)$candidateOtaId;
                    if ($candidateOtaId <= 0) {
                        continue;
                    }
                    if (!isset($otaDetectionScore[$candidateOtaId])) {
                        $otaDetectionScore[$candidateOtaId] = array(
                            'match_count' => 0,
                            'catalogs' => array(),
                            'labels' => array()
                        );
                    }
                    $otaDetectionScore[$candidateOtaId]['match_count']++;
                    $otaDetectionScore[$candidateOtaId]['catalogs'][$catalogId] = true;
                    $label = isset($otaLodgingLabelByAccountCatalog[$candidateOtaId][$catalogId])
                        ? (string)$otaLodgingLabelByAccountCatalog[$candidateOtaId][$catalogId]
                        : ('Catalogo #' . $catalogId);
                    $otaDetectionScore[$candidateOtaId]['labels'][$label] = true;
                }
            }
            foreach ($otaDetectionScore as $candidateOtaId => $scoreRow) {
                $candidateCount = isset($scoreRow['match_count']) ? (int)$scoreRow['match_count'] : 0;
                $candidateCatalogs = isset($scoreRow['catalogs']) && is_array($scoreRow['catalogs']) ? count($scoreRow['catalogs']) : 0;
                if (
                    $candidateCount > $bestScoreCount
                    || ($candidateCount === $bestScoreCount && $candidateCatalogs > $bestScoreCatalogs)
                    || (
                        $candidateCount === $bestScoreCount
                        && $candidateCatalogs === $bestScoreCatalogs
                        && ($bestScoreOtaId <= 0 || (int)$candidateOtaId < $bestScoreOtaId)
                    )
                ) {
                    $bestScoreCount = $candidateCount;
                    $bestScoreCatalogs = $candidateCatalogs;
                    $bestScoreOtaId = (int)$candidateOtaId;
                }
            }
        } catch (Exception $e) {
            // Fallback to existing reservation origin assignment below.
        }
    }
    if ($bestScoreOtaId > 0) {
        $otaDetectedId = $bestScoreOtaId;
        $otaDetectedByLodging = true;
        $otaDetectionConceptLabels = isset($otaDetectionScore[$bestScoreOtaId]['labels'])
            ? array_keys($otaDetectionScore[$bestScoreOtaId]['labels'])
            : array();
    } else {
        if ($detailOtaIdForInfo > 0 && isset($otaMetaById[$detailOtaIdForInfo])) {
            $otaDetectedId = $detailOtaIdForInfo;
            $otaDetectedByLodging = false;
        }
    }

    if ($detailOtaIdForInfo > 0 && isset($otaInfoCatalogsByAccount[$detailOtaIdForInfo]) && $otaInfoCatalogsByAccount[$detailOtaIdForInfo]) {
        $reservationInfoConfigRows = $otaInfoCatalogsByAccount[$detailOtaIdForInfo];
    } elseif ($detailSourceIdForInfo > 0 && isset($reservationSourceInfoCatalogsBySource[$detailSourceIdForInfo]) && $reservationSourceInfoCatalogsBySource[$detailSourceIdForInfo]) {
        $reservationInfoConfigRows = $reservationSourceInfoCatalogsBySource[$detailSourceIdForInfo];
    } elseif ($otaDetectedId > 0 && isset($otaInfoCatalogsByAccount[$otaDetectedId]) && $otaInfoCatalogsByAccount[$otaDetectedId]) {
        $reservationInfoConfigRows = $otaInfoCatalogsByAccount[$otaDetectedId];
    }

    if ($reservationInfoConfigRows) {
        foreach ($reservationInfoConfigRows as $cfg) {
            $catalogId = isset($cfg['id_line_item_catalog']) ? (int)$cfg['id_line_item_catalog'] : 0;
            if ($catalogId <= 0 || isset($otaInfoRowIndexByCatalog[$catalogId])) {
                continue;
            }
            $otaInfoRowIndexByCatalog[$catalogId] = count($otaInfoRows);
            $otaInfoRows[] = array(
                'id_line_item_catalog' => $catalogId,
                'label' => isset($cfg['label']) ? (string)$cfg['label'] : ('Catalogo #' . $catalogId),
                'catalog_type' => isset($cfg['catalog_type']) ? (string)$cfg['catalog_type'] : '',
                'item_count' => 0,
                'quantity_total' => 0.0,
                'amount_total_cents' => 0,
                'first_service_date' => '',
                'last_service_date' => ''
            );
        }

        $otaInfoSourceRows = array();
        $otaInfoSourceFallback = false;
        $configuredCatalogIds = array_keys($otaInfoRowIndexByCatalog);
        if ($reservationId > 0 && $configuredCatalogIds) {
            try {
                $pdoOtaInfo = pms_get_connection();
                $placeholders = implode(',', array_fill(0, count($configuredCatalogIds), '?'));
                $stmtOtaInfo = $pdoOtaInfo->prepare(
                    'SELECT
                        li.id_line_item,
                        li.id_folio,
                        li.id_line_item_catalog,
                        li.item_type,
                        li.status,
                        li.quantity,
                        li.amount_cents,
                        li.service_date,
                        li.created_at
                     FROM line_item li
                     JOIN folio f
                       ON f.id_folio = li.id_folio
                      AND f.deleted_at IS NULL
                     WHERE f.id_reservation = ?
                       AND li.deleted_at IS NULL
                       AND COALESCE(li.is_active, 1) = 1
                       AND (li.status IS NULL OR li.status NOT IN ("void","canceled","cancelled"))
                       AND li.id_line_item_catalog IN (' . $placeholders . ')'
                       . ($otaInfoAllowedFolioIds ? (' AND li.id_folio IN (' . implode(',', array_fill(0, count($otaInfoAllowedFolioIds), '?')) . ')') : '')
                );
                $paramsOtaInfo = array_merge(array((int)$reservationId), array_map('intval', $configuredCatalogIds));
                if ($otaInfoAllowedFolioIds) {
                    $paramsOtaInfo = array_merge($paramsOtaInfo, array_map('intval', $otaInfoAllowedFolioIds));
                }
                $stmtOtaInfo->execute($paramsOtaInfo);
                $otaInfoSourceRows = $stmtOtaInfo->fetchAll();
            } catch (Exception $e) {
                $otaInfoSourceRows = array();
            }
        }
        if (!$otaInfoSourceRows) {
            $otaInfoSourceRows = array_values(array_filter($saleItems, function ($row) use ($otaInfoAllowedFolioIds) {
                if (!$otaInfoAllowedFolioIds) {
                    return true;
                }
                $folioId = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
                if ($folioId <= 0) {
                    return true;
                }
                return in_array($folioId, $otaInfoAllowedFolioIds, true);
            }));
            $otaInfoSourceFallback = true;
        }

        $otaInfoSeenLineItems = array();
        $otaInfoSeenSignatures = array();
        foreach ($otaInfoSourceRows as $otaInfoRow) {
            if ($otaInfoSourceFallback && !reservations_line_item_is_active_for_summary($otaInfoRow, false)) {
                continue;
            }
            $otaInfoLineItemId = isset($otaInfoRow['id_line_item'])
                ? (int)$otaInfoRow['id_line_item']
                : (isset($otaInfoRow['id_sale_item']) ? (int)$otaInfoRow['id_sale_item'] : 0);
            if ($otaInfoLineItemId > 0) {
                if (isset($otaInfoSeenLineItems[$otaInfoLineItemId])) {
                    continue;
                }
                $otaInfoSeenLineItems[$otaInfoLineItemId] = true;
            }
            $catalogId = isset($otaInfoRow['id_line_item_catalog'])
                ? (int)$otaInfoRow['id_line_item_catalog']
                : (isset($otaInfoRow['id_sale_item_catalog']) ? (int)$otaInfoRow['id_sale_item_catalog'] : 0);
            if ($catalogId <= 0 || !isset($otaInfoRowIndexByCatalog[$catalogId])) {
                continue;
            }
            $otaInfoServiceDate = isset($otaInfoRow['service_date']) ? trim((string)$otaInfoRow['service_date']) : '';
            $otaInfoItemType = strtolower(trim((string)(isset($otaInfoRow['item_type']) ? $otaInfoRow['item_type'] : '')));
            $otaInfoAmountForSignature = (int)(isset($otaInfoRow['amount_cents']) ? $otaInfoRow['amount_cents'] : 0);
            $otaInfoCreatedAt = trim((string)(isset($otaInfoRow['created_at']) ? $otaInfoRow['created_at'] : ''));
            $otaInfoSignature = $catalogId . '|' . $otaInfoItemType . '|' . $otaInfoServiceDate . '|' . $otaInfoAmountForSignature . '|' . $otaInfoCreatedAt;
            if (isset($otaInfoSeenSignatures[$otaInfoSignature])) {
                continue;
            }
            $otaInfoSeenSignatures[$otaInfoSignature] = true;
            $idx = (int)$otaInfoRowIndexByCatalog[$catalogId];
            if (!isset($otaInfoRows[$idx])) {
                continue;
            }
            $otaInfoRows[$idx]['item_count']++;
            $otaInfoRows[$idx]['quantity_total'] += (float)(isset($otaInfoRow['quantity']) ? $otaInfoRow['quantity'] : 0);
            $otaInfoRows[$idx]['amount_total_cents'] += (int)(isset($otaInfoRow['amount_cents']) ? $otaInfoRow['amount_cents'] : 0);
            $serviceDate = $otaInfoServiceDate;
            if ($serviceDate !== '') {
                if ($otaInfoRows[$idx]['first_service_date'] === '' || $serviceDate < $otaInfoRows[$idx]['first_service_date']) {
                    $otaInfoRows[$idx]['first_service_date'] = $serviceDate;
                }
                if ($otaInfoRows[$idx]['last_service_date'] === '' || $serviceDate > $otaInfoRows[$idx]['last_service_date']) {
                    $otaInfoRows[$idx]['last_service_date'] = $serviceDate;
                }
            }
        }
    }
    $otaInfoLodgingTotalCents = 0;
    if ($otaInfoRows) {
        foreach ($otaInfoRows as $otaInfoRowForPricing) {
            $otaInfoAmountCents = isset($otaInfoRowForPricing['amount_total_cents']) ? (int)$otaInfoRowForPricing['amount_total_cents'] : 0;
            if ($otaInfoAmountCents > 0) {
                $otaInfoLodgingTotalCents += $otaInfoAmountCents;
            }
        }
    }
    $otaInfoRowsVisible = array_values(array_filter($otaInfoRows, function ($row) {
        $amount = isset($row['amount_total_cents']) ? (int)$row['amount_total_cents'] : 0;
        return $amount !== 0;
    }));

    ob_start();
    ?>
    <form id="<?php echo htmlspecialchars($closeFormId, ENT_QUOTES, 'UTF-8'); ?>" method="post" style="display:none;">
      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
      <?php reservations_render_filter_hiddens($filters); ?>
      <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
      <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:reservation:<?php echo (int)$reservationId; ?>">
    </form>
    <div class="reservation-detail-header reservation-detail-header-enhanced">
      <div class="reservation-detail-title">
        <h3 class="reservation-title-main">
          <span class="reservation-title-guest"><?php echo htmlspecialchars($summaryGuest, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php if ($summaryCheckInShort !== '' || $summaryCheckOutShort !== ''): ?>
            <span class="reservation-title-dates">
              <?php echo htmlspecialchars($summaryCheckInShort !== '' ? $summaryCheckInShort : '--', ENT_QUOTES, 'UTF-8'); ?>
              <span class="reservation-title-arrow">&nbsp;&rarr;&nbsp;</span>
              <?php echo htmlspecialchars($summaryCheckOutShort !== '' ? $summaryCheckOutShort : '--', ENT_QUOTES, 'UTF-8'); ?>
            </span>
          <?php endif; ?>
          <?php if ($summaryRoomHeaderLabel !== ''): ?>
            <span class="reservation-title-room"><?php echo htmlspecialchars($summaryRoomHeaderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </h3>
      </div>
      <div class="reservation-detail-header-center">
        <?php if ($headerAlertVisible): ?>
          <div class="reservation-detail-alert <?php echo htmlspecialchars($headerAlertClass, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="reservation-detail-alert-text"><?php echo $headerAlertText; ?></span>
            <form method="post" action="index.php?view=reservation_wizard">
              <input type="hidden" name="wizard_reservation_id" value="<?php echo (int)$reservationId; ?>">
              <input type="hidden" name="wizard_step" value="<?php echo htmlspecialchars($headerAlertWizardStep, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="wizard_force_step" value="<?php echo htmlspecialchars($headerAlertWizardStep, ENT_QUOTES, 'UTF-8'); ?>">
              <?php if ($headerAlertReplaceLodging): ?>
                <input type="hidden" name="wizard_replace_lodging" value="1">
                <input type="hidden" name="wizard_replace_folio_id" value="<?php echo (int)$reservationActionFolioId; ?>">
              <?php endif; ?>
              <button type="submit" class="button-secondary"><?php echo htmlspecialchars($headerAlertButton, ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
          </div>
        <?php endif; ?>
      </div>
      <div class="reservation-detail-header-right">
        <div class="reservation-detail-balances" data-res-header-balances>
          <span class="balance-item" data-balance-total>Final: <strong><?php echo htmlspecialchars(reservations_format_money($summaryBalanceCents, $summaryCurrency), ENT_QUOTES, 'UTF-8'); ?></strong></span>
          <span class="balance-item" data-balance-lodging>Hospedaje: <strong><?php echo htmlspecialchars(reservations_format_money($summaryBalanceCents, $summaryCurrency), ENT_QUOTES, 'UTF-8'); ?></strong></span>
          <span class="balance-item" data-balance-services>Servicios: <strong><?php echo htmlspecialchars(reservations_format_money(0, $summaryCurrency), ENT_QUOTES, 'UTF-8'); ?></strong></span>
        </div>
        <details class="reservation-quick-actions">
          <summary class="reservation-quick-actions-toggle" title="Opciones de reserva" aria-label="Opciones de reserva">
            <span class="reservation-quick-actions-dots" aria-hidden="true">
              <span></span>
              <span></span>
              <span></span>
            </span>
	          </summary>
	          <div class="reservation-quick-actions-menu">
	            <form method="post" action="index.php?view=reservation_wizard" class="reservation-quick-action-form">
	              <input type="hidden" name="wizard_reservation_id" value="<?php echo (int)$reservationId; ?>">
	              <input type="hidden" name="wizard_step" value="2">
	              <input type="hidden" name="wizard_force_step" value="2">
	              <input type="hidden" name="wizard_replace_lodging" value="1">
	              <input type="hidden" name="wizard_replace_folio_id" value="<?php echo (int)$reservationActionFolioId; ?>">
	              <button type="submit" class="button-secondary">Cambiar tipo de hospedaje</button>
	            </form>
	            <form method="post" class="reservation-quick-action-form" onsubmit="return confirm('Se quitaran todos los impuestos visibles en folio para esta reserva. Continuar?');">
	              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
	              <?php reservations_render_filter_hiddens($filters); ?>
              <input type="hidden" name="reservations_action" value="remove_visible_folio_taxes">
              <input type="hidden" name="tax_reservation_id" value="<?php echo (int)$reservationId; ?>">
              <input type="hidden" name="tax_folio_id" value="<?php echo (int)$reservationActionFolioId; ?>">
              <button type="submit" class="button-secondary reservation-quick-action-danger">Quitar impuestos</button>
            </form>
          </div>
        </details>
      </div>
    </div>
    <?php if ($detailError): ?>
      <p class="error"><?php echo htmlspecialchars($detailError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php elseif (!$detail): ?>
      <p class="muted">No se encontr&oacute; informaci&oacute;n para la reserva.</p>
    <?php else: ?>
      <?php if (isset($updateErrors[$reservationId])): ?>
        <p class="error"><?php echo htmlspecialchars($updateErrors[$reservationId], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php elseif (isset($updateMessages[$reservationId])): ?>
        <p class="success"><?php echo htmlspecialchars($updateMessages[$reservationId], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if (isset($financeErrors[$reservationId])): ?>
        <p class="error"><?php echo htmlspecialchars($financeErrors[$reservationId], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php elseif (isset($financeMessages[$reservationId])): ?>
        <p class="success"><?php echo htmlspecialchars($financeMessages[$reservationId], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php
        $reservationCode = isset($detail['reservation_code']) && $detail['reservation_code'] !== ''
          ? (string)$detail['reservation_code']
          : (isset($detail['code']) ? (string)$detail['code'] : (string)$reservationId);
        $editDisabledAttr = $isHold ? '' : 'disabled';
        $confirmValues = isset($confirmReservationValues[$reservationId])
            ? $confirmReservationValues[$reservationId]
            : array('lodging_catalog_id' => 0, 'total_override' => '', 'nightly_override' => '');
        $confirmGuest = isset($confirmGuestValues[$reservationId]) ? $confirmGuestValues[$reservationId] : array();
        $guestNamesValue = $confirmGuest ? (string)$confirmGuest['names'] : (isset($detail['guest_names']) ? (string)$detail['guest_names'] : '');
        $guestEmailValue = $confirmGuest ? (string)$confirmGuest['email'] : (isset($detail['guest_email']) ? (string)$detail['guest_email'] : '');
        $guestLastValue = $confirmGuest ? (string)$confirmGuest['last'] : (isset($detail['guest_last_name']) ? (string)$detail['guest_last_name'] : '');
        $guestMaidenValue = $confirmGuest ? (string)$confirmGuest['maiden'] : '';
        $guestPhoneValue = $confirmGuest ? (string)$confirmGuest['phone'] : (isset($detail['guest_phone']) ? (string)$detail['guest_phone'] : '');
        $confirmGuestData = $confirmGuest
            ? $confirmGuest
            : array(
                'names' => isset($detail['guest_names']) ? (string)$detail['guest_names'] : '',
                'email' => isset($detail['guest_email']) ? (string)$detail['guest_email'] : '',
                'last' => isset($detail['guest_last_name']) ? (string)$detail['guest_last_name'] : '',
                'maiden' => '',
                'phone_prefix' => '',
                'phone' => isset($detail['guest_phone']) ? (string)$detail['guest_phone'] : ''
            );
        $confirmPhonePrefix = isset($confirmGuestData['phone_prefix']) ? (string)$confirmGuestData['phone_prefix'] : '';
        if (function_exists('pms_phone_extract_dial')) {
            $confirmPhonePrefix = pms_phone_extract_dial($confirmPhonePrefix, '');
        }
        $confirmPhoneRaw = isset($confirmGuestData['phone']) ? trim((string)$confirmGuestData['phone']) : '';
        if ($confirmPhoneRaw !== '' && preg_match('/^(\\+\\d{1,4})\\s*(.*)$/', $confirmPhoneRaw, $confirmPhoneParts)) {
            if ($confirmPhonePrefix === '') {
                $confirmPhonePrefix = $confirmPhoneParts[1];
            }
            $confirmPhoneRaw = trim($confirmPhoneParts[2]);
        }
        if ($confirmPhonePrefix === '' || !isset($phonePrefixDialMap[$confirmPhonePrefix])) {
            $confirmPhonePrefix = $defaultPhonePrefix;
        }
        $confirmGuestData['phone_prefix'] = $confirmPhonePrefix;
        $confirmGuestData['phone'] = $confirmPhoneRaw;
        $confirmOpen = $isHold && isset($confirmReservationValues[$reservationId]);
        $confirmBaseCents = $summaryNightlyCents > 0 ? $summaryNightlyCents : (isset($roomInfo['default_base_price_cents']) ? (int)$roomInfo['default_base_price_cents'] : 0);
        $lodgingConfirmOptions = array();
        if ($isHold && $propertyCodeDetail !== '') {
            if (!isset($lodgingAllowedIdsByProperty[$propertyCodeDetail])) {
                $allowedIds = array();
                try {
                    $settingSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, $propertyCodeDetail));
                    $settingRow = isset($settingSets[0][0]) ? $settingSets[0][0] : null;
                    if ($settingRow && isset($settingRow['lodging_catalog_ids']) && $settingRow['lodging_catalog_ids'] !== '') {
                        $allowedIds = array_filter(array_map('intval', explode(',', (string)$settingRow['lodging_catalog_ids'])));
                    }
                } catch (Exception $e) {
                    $allowedIds = array();
                }
                if (!$allowedIds) {
                    try {
                        $fallbackSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, null));
                        $fallbackRow = isset($fallbackSets[0][0]) ? $fallbackSets[0][0] : null;
                        if ($fallbackRow && isset($fallbackRow['lodging_catalog_ids']) && $fallbackRow['lodging_catalog_ids'] !== '') {
                            $allowedIds = array_filter(array_map('intval', explode(',', (string)$fallbackRow['lodging_catalog_ids'])));
                        }
                    } catch (Exception $e) {
                        $allowedIds = array();
                    }
                }
                $lodgingAllowedIdsByProperty[$propertyCodeDetail] = $allowedIds;
            }

            if (!isset($conceptsByProperty[$propertyCodeDetail])) {
                try {
                    $conceptSets = pms_call_procedure('sp_sale_item_catalog_data', array(
                        $companyCode,
                        $propertyCodeDetail,
                        0,
                        0,
                        0
                    ));
                    $conceptsByProperty[$propertyCodeDetail] = isset($conceptSets[0]) ? $conceptSets[0] : array();
                    if (!$conceptsByProperty[$propertyCodeDetail]) {
                        $conceptsByProperty[$propertyCodeDetail] = reservations_catalog_data_fallback($companyId, $propertyCodeDetail, 0, 0, 0);
                    }
                } catch (Exception $e) {
                    $conceptsByProperty[$propertyCodeDetail] = reservations_catalog_data_fallback($companyId, $propertyCodeDetail, 0, 0, 0);
                }
            }

            if (!isset($lodgingOptionsByProperty[$propertyCodeDetail])) {
                $lodgingOptions = array();
                $allowedMap = array();
                foreach ($lodgingAllowedIdsByProperty[$propertyCodeDetail] as $lid) {
                    $allowedMap[(int)$lid] = true;
                }
                if ($allowedMap) {
                    foreach ($conceptsByProperty[$propertyCodeDetail] as $c) {
                        $cid = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
                        if ($cid <= 0 || !isset($allowedMap[$cid])) {
                            continue;
                        }
                        $cat = isset($c['category']) ? (string)$c['category'] : '';
                        $label = isset($c['item_name']) ? (string)$c['item_name'] : '';
                        $lodgingOptions[] = array(
                            'id' => $cid,
                            'label' => ($cat !== '' ? $cat . ' / ' : '') . $label
                        );
                    }
                }
                $lodgingOptionsByProperty[$propertyCodeDetail] = $lodgingOptions;
            }
            $lodgingConfirmOptions = $lodgingOptionsByProperty[$propertyCodeDetail];
        }

        $interestOptions = array();
        if ($propertyCodeDetail !== '' && isset($conceptsByProperty[$propertyCodeDetail])) {
            $interestOptions = $conceptsByProperty[$propertyCodeDetail];
        }
        if ($propertyCodeDetail !== '' && !isset($interestAllowedIdsByProperty[$propertyCodeDetail])) {
            $interestAllowedIds = array();
            try {
                $settingSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, $propertyCodeDetail));
                $settingRow = isset($settingSets[0][0]) ? $settingSets[0][0] : null;
                if ($settingRow && isset($settingRow['interest_catalog_ids']) && $settingRow['interest_catalog_ids'] !== '') {
                    $interestAllowedIds = array_filter(array_map('intval', explode(',', (string)$settingRow['interest_catalog_ids'])));
                }
            } catch (Exception $e) {
                $interestAllowedIds = array();
            }
            if (!$interestAllowedIds) {
                try {
                    $fallbackSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, null));
                    $fallbackRow = isset($fallbackSets[0][0]) ? $fallbackSets[0][0] : null;
                    if ($fallbackRow && isset($fallbackRow['interest_catalog_ids']) && $fallbackRow['interest_catalog_ids'] !== '') {
                        $interestAllowedIds = array_filter(array_map('intval', explode(',', (string)$fallbackRow['interest_catalog_ids'])));
                    }
                } catch (Exception $e) {
                    $interestAllowedIds = array();
                }
            }
            $interestAllowedIdsByProperty[$propertyCodeDetail] = $interestAllowedIds;
        }
        $interestCatalogIds = array();
        foreach ($interests as $interestRow) {
            $iid = isset($interestRow['id_sale_item_catalog']) ? (int)$interestRow['id_sale_item_catalog'] : 0;
            if ($iid > 0) {
                $interestCatalogIds[$iid] = true;
            }
        }
        $interestAllowedMap = array();
        if ($propertyCodeDetail !== '' && isset($interestAllowedIdsByProperty[$propertyCodeDetail])) {
            foreach ($interestAllowedIdsByProperty[$propertyCodeDetail] as $iid) {
                $interestAllowedMap[(int)$iid] = true;
            }
        }
        $availableInterestOptions = array();
        foreach ($interestOptions as $opt) {
            $cid = isset($opt['id_sale_item_catalog']) ? (int)$opt['id_sale_item_catalog'] : 0;
            if ($cid <= 0 || isset($interestCatalogIds[$cid])) {
                continue;
            }
            if (!$interestAllowedMap || !isset($interestAllowedMap[$cid])) {
                continue;
            }
            $nameLabel = isset($opt['item_name']) ? (string)$opt['item_name'] : '';
            if ($nameLabel === '') {
                continue;
            }
            $availableInterestOptions[] = array(
                'id' => $cid,
                'label' => $nameLabel
            );
        }
        $guestProfileUrl = $hasGuestAssigned ? ('index.php?view=guests&guest_id=' . $linkedGuestId) : '';
        $guestCombinedName = trim($guestNamesValue . ' ' . $guestLastValue . ' ' . $guestMaidenValue);
        $hasInterests = !empty($interests);
        $interestFormInitiallyOpen = isset($interestErrors[$reservationId]);
        $nightlyAmountsByDate = array();
        $nightlyRows = array();
        $lodgingCatalogAllowedMap = array();
        if ($propertyCodeDetail !== '') {
            if (!isset($lodgingAllowedIdsByProperty[$propertyCodeDetail])) {
                $allowedIds = array();
                try {
                    $settingSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, $propertyCodeDetail));
                    $settingRow = isset($settingSets[0][0]) ? $settingSets[0][0] : null;
                    if ($settingRow && isset($settingRow['lodging_catalog_ids']) && $settingRow['lodging_catalog_ids'] !== '') {
                        $allowedIds = array_filter(array_map('intval', explode(',', (string)$settingRow['lodging_catalog_ids'])));
                    }
                } catch (Exception $e) {
                    $allowedIds = array();
                }
                if (!$allowedIds) {
                    try {
                        $fallbackSets = pms_call_procedure('sp_pms_settings_data', array($companyCode, null));
                        $fallbackRow = isset($fallbackSets[0][0]) ? $fallbackSets[0][0] : null;
                        if ($fallbackRow && isset($fallbackRow['lodging_catalog_ids']) && $fallbackRow['lodging_catalog_ids'] !== '') {
                            $allowedIds = array_filter(array_map('intval', explode(',', (string)$fallbackRow['lodging_catalog_ids'])));
                        }
                    } catch (Exception $e) {
                        $allowedIds = array();
                    }
                }
                $lodgingAllowedIdsByProperty[$propertyCodeDetail] = $allowedIds;
            }
            if (isset($lodgingAllowedIdsByProperty[$propertyCodeDetail]) && is_array($lodgingAllowedIdsByProperty[$propertyCodeDetail])) {
                foreach ($lodgingAllowedIdsByProperty[$propertyCodeDetail] as $allowedLodgingId) {
                    $allowedId = (int)$allowedLodgingId;
                    if ($allowedId > 0) {
                        $lodgingCatalogAllowedMap[$allowedId] = true;
                    }
                }
            }
        }
        $primaryLodgingCatalogId = 0;
        if ($primaryLodgingLineItemId > 0 && isset($saleItemsById[$primaryLodgingLineItemId])) {
            $primaryLodgingCatalogId = isset($saleItemsById[$primaryLodgingLineItemId]['id_sale_item_catalog'])
                ? (int)$saleItemsById[$primaryLodgingLineItemId]['id_sale_item_catalog']
                : 0;
        }
        $isLodgingSaleItem = function (array $saleRow) use ($lodgingCatalogAllowedMap, $primaryLodgingCatalogId) {
            $catalogId = isset($saleRow['id_sale_item_catalog']) ? (int)$saleRow['id_sale_item_catalog'] : 0;
            if ($catalogId <= 0) {
                return false;
            }
            if ($lodgingCatalogAllowedMap) {
                return isset($lodgingCatalogAllowedMap[$catalogId]);
            }
            if ($primaryLodgingCatalogId > 0) {
                return $catalogId === $primaryLodgingCatalogId;
            }
            return false;
        };
        $hasLodgingNightlyCharges = false;
        $lodgingBaseTotalCents = 0;
        $lodgingBaseAmountsByDate = array();
        $lodgingDatesWithAmount = array();

        if ($summaryCheckIn !== '' && $summaryCheckOut !== '') {
            try {
                $nightStart = new DateTime($summaryCheckIn);
                $nightEnd = new DateTime($summaryCheckOut);
                $guard = 0;
                while ($nightStart < $nightEnd && $guard < 400) {
                    $guard++;
                    $k = $nightStart->format('Y-m-d');
                    if (!isset($nightlyAmountsByDate[$k])) {
                        $nightlyAmountsByDate[$k] = 0;
                    }
                    $nightStart->modify('+1 day');
                }
            } catch (Exception $e) {
                $nightlyAmountsByDate = array();
            }
        }

        foreach ($saleItems as $nightSaleRow) {
            if (!reservations_line_item_is_active_for_summary($nightSaleRow, true)) {
                continue;
            }
            if (!$isLodgingSaleItem($nightSaleRow)) {
                continue;
            }
            $nightSaleId = isset($nightSaleRow['id_sale_item']) ? (int)$nightSaleRow['id_sale_item'] : 0;
            if ($nightSaleId <= 0) {
                continue;
            }
            $nightDate = isset($nightSaleRow['service_date']) ? trim((string)$nightSaleRow['service_date']) : '';
            if ($nightDate === '') {
                continue;
            }
            if (!isset($nightlyAmountsByDate[$nightDate]) && $nightlyAmountsByDate) {
                continue;
            }
            if (!isset($nightlyAmountsByDate[$nightDate])) {
                $nightlyAmountsByDate[$nightDate] = 0;
            }
            $nightBaseCents = 0;
            if (isset($nightSaleRow['amount_cents']) && $nightSaleRow['amount_cents'] !== null && $nightSaleRow['amount_cents'] !== '') {
                $nightBaseCents = (int)$nightSaleRow['amount_cents'];
            } else {
                $nightQty = isset($nightSaleRow['quantity']) ? (float)$nightSaleRow['quantity'] : 1.0;
                if ($nightQty <= 0) {
                    $nightQty = 1.0;
                }
                $nightUnit = isset($nightSaleRow['unit_price_cents']) ? (int)$nightSaleRow['unit_price_cents'] : 0;
                $nightDiscount = isset($nightSaleRow['discount_amount_cents']) ? (int)$nightSaleRow['discount_amount_cents'] : 0;
                $nightBaseCents = (int)round(($nightUnit * $nightQty) - $nightDiscount);
            }
            if ($nightBaseCents < 0) {
                $nightBaseCents = 0;
            }
            $lodgingBaseTotalCents += $nightBaseCents;
            if (!isset($lodgingBaseAmountsByDate[$nightDate])) {
                $lodgingBaseAmountsByDate[$nightDate] = 0;
            }
            $lodgingBaseAmountsByDate[$nightDate] += $nightBaseCents;
            if ($nightBaseCents > 0) {
                $lodgingDatesWithAmount[$nightDate] = true;
            }
        }

        if ($lodgingBaseTotalCents <= 0 && $otaInfoLodgingTotalCents > 0) {
            $lodgingBaseTotalCents = $otaInfoLodgingTotalCents;
        }

        $stayNightDates = array_keys($nightlyAmountsByDate);
        $stayNightCount = count($stayNightDates);
        $hasExplicitNightly = ($stayNightCount <= 1)
            ? (count($lodgingDatesWithAmount) >= 1)
            : (count($lodgingDatesWithAmount) > 1);

        if ($stayNightCount > 0 && $lodgingBaseTotalCents > 0 && !$hasExplicitNightly) {
            $distributedByDate = array();
            $basePerNight = (int)floor($lodgingBaseTotalCents / $stayNightCount);
            $remainder = $lodgingBaseTotalCents - ($basePerNight * $stayNightCount);
            foreach ($stayNightDates as $dateIdx => $stayDate) {
                $distributedByDate[$stayDate] = $basePerNight + ($dateIdx < $remainder ? 1 : 0);
            }
            $lodgingBaseAmountsByDate = $distributedByDate;
        }

        if ($stayNightCount > 0) {
            foreach ($stayNightDates as $stayDate) {
                $nightlyRows[] = array(
                    'date' => $stayDate,
                    'amount_cents' => isset($lodgingBaseAmountsByDate[$stayDate]) ? (int)$lodgingBaseAmountsByDate[$stayDate] : 0
                );
            }
        } elseif ($lodgingBaseAmountsByDate) {
            ksort($lodgingBaseAmountsByDate);
            foreach ($lodgingBaseAmountsByDate as $nightDate => $nightAmountCents) {
                $nightlyRows[] = array(
                    'date' => $nightDate,
                    'amount_cents' => (int)$nightAmountCents
                );
            }
        }

        $nightlyBreakdownTotalCents = 0;
        foreach ($nightlyRows as $nightRow) {
            $nightlyBreakdownTotalCents += (int)$nightRow['amount_cents'];
        }
        if ($lodgingBaseTotalCents > 0) {
            $pricingTotalDisplay = reservations_format_money($lodgingBaseTotalCents, $summaryCurrency);
        } elseif ($nightlyBreakdownTotalCents > 0) {
            $pricingTotalDisplay = reservations_format_money($nightlyBreakdownTotalCents, $summaryCurrency);
        } elseif ($summaryHasCharges) {
            $pricingTotalDisplay = reservations_format_money($visibleChargesGrandTotalCents, $summaryCurrency);
        } else {
            $pricingTotalDisplay = '--';
        }
        $hasLodgingNightlyCharges = ($lodgingBaseTotalCents > 0 && !empty($nightlyRows));
        $canOpenRoomDetail = ($propertyCodeDetail !== '' && $roomDetailOpenKey !== '');
        $availableRoomsForCategory = $roomsCatalog;
      ?>

      <div class="reservation-main-content">
      <div class="reservation-summary-layout">
      <div class="reservation-summary-column">
      <div class="reservation-summary-card" data-res-summary-card>
        <form method="post" class="reservation-summary-inline-form" data-res-summary-form>
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <?php reservations_render_filter_hiddens($filters); ?>
          <input type="hidden" name="reservations_action" value="update_reservation">
          <input type="hidden" name="reservation_id" value="<?php echo (int)$reservationId; ?>">
          <input type="hidden" name="reservation_guest_id" value="<?php echo isset($detail['id_guest']) ? (int)$detail['id_guest'] : 0; ?>">
          <input type="hidden" name="reservation_guest_names" value="<?php echo htmlspecialchars($guestNamesValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="reservation_guest_email" value="<?php echo htmlspecialchars($guestEmailValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="reservation_guest_phone" value="<?php echo htmlspecialchars($guestPhoneValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="reservation_guest_last_name" value="<?php echo htmlspecialchars($guestLastValue, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="reservation_guest_maiden_name" value="<?php echo htmlspecialchars($guestMaidenValue, ENT_QUOTES, 'UTF-8'); ?>">

          <div class="reservation-summary-grid">
            <div class="reservation-summary-field">
              <span class="label">C&oacute;digo de reservaci&oacute;n</span>
              <span class="value" data-field-view><?php echo htmlspecialchars($reservationCode, ENT_QUOTES, 'UTF-8'); ?></span>
              <input type="text" name="reservation_code" value="<?php echo htmlspecialchars($reservationCode, ENT_QUOTES, 'UTF-8'); ?>" data-summary-editable data-field-edit disabled>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Estatus</span>
              <span class="value" data-field-view><?php echo htmlspecialchars(reservations_status_label(isset($detail['status']) ? (string)$detail['status'] : '--'), ENT_QUOTES, 'UTF-8'); ?></span>
              <select
                name="reservation_status"
                <?php echo !$statusEditLocked ? 'data-summary-editable ' : ''; ?>
                data-field-edit
                <?php echo $statusEditLocked ? ('data-summary-lock-edit="1" title="' . $statusLockTitle . '"') : ''; ?>
                disabled
              >
                <?php if (!$statusEditLocked): ?>
                  <option value="">(sin cambio)</option>
                <?php endif; ?>
                <?php foreach ($statusOptionsForEdit as $option): ?>
                  <?php
                    $selectedStatus = false;
                    $disableStatusOption = (!$statusEditLocked && $option === 'salida' && $statusCheckoutLocked && $summaryStatusNormalized !== 'salida');
                    if ($statusEditLocked) {
                        $selectedStatus = ($option === 'apartado');
                    } else {
                        $selectedStatus = (isset($detail['status']) && (string)$detail['status'] === (string)$option);
                    }
                  ?>
                  <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedStatus ? 'selected' : ''; ?> <?php echo $disableStatusOption ? 'disabled' : ''; ?>>
                    <?php echo htmlspecialchars(reservations_status_label($option), ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (!$statusEditLocked && $statusCheckoutLocked): ?>
                <small class="muted"><?php echo $statusCheckoutLockTitle; ?></small>
              <?php endif; ?>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Propiedad</span>
              <span class="value" data-field-view><?php echo htmlspecialchars($summaryProperty !== '' ? $summaryProperty : '--', ENT_QUOTES, 'UTF-8'); ?></span>
              <select name="reservation_property_code" data-summary-editable data-field-edit data-summary-property-select disabled>
                <?php foreach ($properties as $propertyOpt):
                  $propertyOptCode = isset($propertyOpt['code']) ? strtoupper((string)$propertyOpt['code']) : '';
                  if ($propertyOptCode === '') {
                      continue;
                  }
                  $propertyOptName = isset($propertyOpt['name']) ? (string)$propertyOpt['name'] : '';
                ?>
                  <option value="<?php echo htmlspecialchars($propertyOptCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $propertyOptCode === $propertyCodeDetail ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(trim($propertyOptCode . ($propertyOptName !== '' ? (' - ' . $propertyOptName) : '')), ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Categor&iacute;a</span>
              <span class="value"><?php echo htmlspecialchars($summaryCategory !== '' ? $summaryCategory : '--', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Habitaci&oacute;n</span>
              <span class="value reservation-summary-room" data-field-view>
                <span><?php echo htmlspecialchars($summaryRoom !== '' ? $summaryRoom : '--', ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($canOpenRoomDetail): ?>
                  <button type="submit" formaction="index.php?view=rooms" class="reservation-summary-room-link" name="rooms_subtab_action" value="open">Ver</button>
                <?php endif; ?>
              </span>
              <select name="reservation_room_code" data-summary-editable data-field-edit data-summary-room-select disabled>
                <option value="">(sin cambio)</option>
                <?php foreach ($availableRoomsForCategory as $room):
                  $code = isset($room['code']) ? (string)$room['code'] : '';
                  $roomPropertyCode = isset($room['property_code']) ? strtoupper((string)$room['property_code']) : '';
                  if ($code === '' || $roomPropertyCode === '') {
                    continue;
                  }
                  $label = reservations_room_label($room);
                  $roomCategoryLabel = isset($room['category_name']) ? trim((string)$room['category_name']) : '';
                  if ($roomCategoryLabel !== '') {
                    $label .= ' / ' . $roomCategoryLabel;
                  }
                  $baseCents = isset($room['default_base_price_cents']) ? (int)$room['default_base_price_cents'] : 0;
                ?>
                  <option
                    value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                    data-base="<?php echo (int)$baseCents; ?>"
                    data-property="<?php echo htmlspecialchars($roomPropertyCode, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo ($roomPropertyCode === $propertyCodeDetail && strtoupper($code) === strtoupper($roomCodeDetail)) ? 'selected' : ''; ?>
                  >
                    <?php echo htmlspecialchars($roomPropertyCode . ' / ' . $label, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($canOpenRoomDetail): ?>
                <input type="hidden" name="rooms_subtab_target" value="room:<?php echo htmlspecialchars($roomDetailOpenKey, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="rooms_filter_property" value="<?php echo htmlspecialchars($propertyCodeDetail, ENT_QUOTES, 'UTF-8'); ?>">
              <?php endif; ?>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Origen</span>
              <?php
                $detailOtaName = isset($detail['ota_name']) ? trim((string)$detail['ota_name']) : '';
                $detailOtaId = isset($detail['id_ota_account']) ? (int)$detail['id_ota_account'] : 0;
                $detailSourceId = isset($detail['id_reservation_source']) ? (int)$detail['id_reservation_source'] : 0;
                $detailSourceName = trim((string)(isset($detail['reservation_source_name']) ? $detail['reservation_source_name'] : ''));
                if ($detailSourceName === '') {
                    $detailSourceName = trim((string)(isset($detail['source']) ? $detail['source'] : ''));
                }
                $detailOriginOptions = reservations_origin_options_for_property($reservationSourcesByProperty, $otaAccountsByProperty, $propertyCodeDetail);
                $detailOriginKey = $detailOtaId > 0 ? ('ota:' . $detailOtaId) : ('source:' . $detailSourceId);
                $detailOriginSelected = reservations_origin_row_for_key($detailOriginOptions, $detailOriginKey);
                $detailOriginDisplayRow = $detailOriginSelected;
                if ($detailOriginSelected === null && !empty($detailOriginOptions)) {
                    $detailOriginSelected = $detailOriginOptions[0];
                }
                if ($detailOriginSelected !== null) {
                    $detailOriginKey = (string)(isset($detailOriginSelected['origin_key']) ? $detailOriginSelected['origin_key'] : $detailOriginKey);
                }
                $originDisplay = $detailOriginDisplayRow !== null
                    ? trim((string)(isset($detailOriginDisplayRow['origin_label']) ? $detailOriginDisplayRow['origin_label'] : ''))
                    : ($detailSourceName !== '' ? $detailSourceName : $detailOtaName);
              ?>
              <span class="value" data-field-view><?php echo htmlspecialchars($originDisplay !== '' ? $originDisplay : '--', ENT_QUOTES, 'UTF-8'); ?></span>
              <select name="reservation_origin_id" data-summary-editable data-field-edit disabled>
                <?php foreach ($detailOriginOptions as $originOpt): ?>
                  <?php
                    $originKeyOpt = (string)(isset($originOpt['origin_key']) ? $originOpt['origin_key'] : 'source:0');
                    $originLabelOpt = trim((string)(isset($originOpt['origin_label']) ? $originOpt['origin_label'] : ''));
                    if ($originLabelOpt === '') {
                        $originLabelOpt = 'Directo';
                    }
                  ?>
                  <option value="<?php echo htmlspecialchars($originKeyOpt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $originKeyOpt === $detailOriginKey ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($originLabelOpt, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Check-in</span>
              <span class="value" data-field-view><?php echo htmlspecialchars(reservations_format_date($summaryCheckIn, 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?></span>
              <input type="date" name="reservation_check_in" value="<?php echo htmlspecialchars(reservations_format_date($detail['check_in_date'], 'Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" data-summary-editable data-field-edit disabled>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Check-out</span>
              <span class="value" data-field-view><?php echo htmlspecialchars(reservations_format_date($summaryCheckOut, 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?></span>
              <input type="date" name="reservation_check_out" value="<?php echo htmlspecialchars(reservations_format_date($detail['check_out_date'], 'Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" data-summary-editable data-field-edit disabled>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Adultos</span>
              <span class="value" data-field-view><?php echo isset($detail['adults']) ? (int)$detail['adults'] : 0; ?></span>
              <input type="number" name="reservation_adults" min="1" value="<?php echo isset($detail['adults']) ? (int)$detail['adults'] : ''; ?>" data-summary-editable data-field-edit disabled>
            </div>
            <div class="reservation-summary-field">
              <span class="label">Menores</span>
              <span class="value" data-field-view><?php echo isset($detail['children']) ? (int)$detail['children'] : 0; ?></span>
              <input type="number" name="reservation_children" min="0" value="<?php echo isset($detail['children']) ? (int)$detail['children'] : ''; ?>" data-summary-editable data-field-edit disabled>
            </div>
          </div>

          <div class="reservation-summary-edit">
            <button type="button" class="button-secondary" data-summary-edit-start>Editar</button>
            <button type="submit" class="button-secondary" data-summary-edit-save style="display:none;">Guardar</button>
            <button type="button" class="button-secondary" data-summary-edit-cancel style="display:none;">Cancelar</button>
          </div>
        </form>
      </div>
      <div class="reservation-pricing-card">
        <div class="reservation-pricing-head">
          <span class="label">Tarifa total de estancia</span>
          <span class="reservation-pricing-total"><?php echo htmlspecialchars($pricingTotalDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if ($nightlyRows && $hasLodgingNightlyCharges): ?>
          <div class="table-scroll reservation-nightly-breakdown-wrap">
            <table class="reservation-nightly-breakdown">
              <thead>
                <tr>
                  <th>Noche</th>
                  <th>Precio</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($nightlyRows as $nightRow): ?>
                  <tr>
                    <td><?php echo htmlspecialchars(reservations_format_date(isset($nightRow['date']) ? (string)$nightRow['date'] : '', 'd/m/Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(reservations_format_money(isset($nightRow['amount_cents']) ? (int)$nightRow['amount_cents'] : 0, $summaryCurrency), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      </div>
      <div class="reservation-side-stack">
        <div class="reservation-guest-card">
          <div class="reservation-guest-compact">
            <div class="reservation-guest-row name-only">
              <div class="reservation-guest-field">
                <span class="label">Nombre completo</span>
                <span class="value"><?php echo htmlspecialchars($guestCombinedName !== '' ? $guestCombinedName : '--', ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            </div>
            <div class="reservation-guest-row">
              <div class="reservation-guest-field">
                <span class="label">Correo</span>
                <span class="value"><?php echo htmlspecialchars($guestEmailValue !== '' ? $guestEmailValue : '--', ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="reservation-guest-field">
                <span class="label">Tel&eacute;fono</span>
                <span class="value"><?php echo htmlspecialchars($guestPhoneValue !== '' ? $guestPhoneValue : '--', ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="reservation-guest-actions">
                <?php if ($guestProfileUrl !== ''): ?>
                  <a class="button-secondary reservation-interest-add reservation-guest-link" href="<?php echo htmlspecialchars($guestProfileUrl, ENT_QUOTES, 'UTF-8'); ?>">Ver hu&eacute;sped</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="reservation-guest-widgets">
          <div class="reservation-interest-card" data-interest-widget data-reservation-id="<?php echo (int)$reservationId; ?>">
            <div class="reservation-interest-head">
              <h4>Intereses</h4>
              <details class="reservation-interest-add-wrap" data-interest-details <?php echo $interestFormInitiallyOpen ? 'open' : ''; ?>>
                <summary class="button-secondary reservation-interest-toggle">+ agregar</summary>
                <form method="post" class="form-inline interest-form" data-interest-form>
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <?php reservations_render_filter_hiddens($filters); ?>
                  <input type="hidden" name="reservations_action" value="add_interest">
                  <input type="hidden" name="interest_reservation_id" value="<?php echo (int)$reservationId; ?>">
                  <label class="reservation-interest-control">
                    Concepto
                    <select name="interest_catalog_id" data-interest-select>
                      <option value=""><?php echo $availableInterestOptions ? 'Selecciona un concepto' : 'No hay conceptos disponibles'; ?></option>
                      <?php foreach ($availableInterestOptions as $opt): ?>
                        <option value="<?php echo (int)$opt['id']; ?>"><?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <button type="submit" class="button-secondary reservation-interest-add" data-interest-add-btn>Agregar</button>
                </form>
              </details>
            </div>
            <?php if (isset($interestErrors[$reservationId])): ?>
              <p class="error"><?php echo htmlspecialchars($interestErrors[$reservationId], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <p class="error" data-interest-error style="display:none;"></p>

            <div data-interest-list-wrap>
              <div class="interest-tags" data-interest-list<?php echo $hasInterests ? '' : ' style="display:none;"'; ?>>
                <?php foreach ($interests as $interestRow):
                  $interestName = isset($interestRow['item_name']) ? (string)$interestRow['item_name'] : '';
                  $interestLabel = $interestName;
                  $interestId = isset($interestRow['id_sale_item_catalog']) ? (int)$interestRow['id_sale_item_catalog'] : 0;
                ?>
                  <div class="interest-tag" data-interest-item data-interest-catalog-id="<?php echo $interestId; ?>">
                    <span data-interest-label><?php echo htmlspecialchars($interestLabel !== '' ? $interestLabel : 'Concepto', ENT_QUOTES, 'UTF-8'); ?></span>
                    <form method="post" class="interest-tag-action" data-interest-remove-form>
                      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                      <?php reservations_render_filter_hiddens($filters); ?>
                      <input type="hidden" name="reservations_action" value="remove_interest">
                      <input type="hidden" name="interest_reservation_id" value="<?php echo (int)$reservationId; ?>">
                      <input type="hidden" name="interest_catalog_id" value="<?php echo $interestId; ?>">
                      <button type="submit" class="button-secondary interest-remove">Quitar</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="reservation-message-card">
            <h4>Mensajer&iacute;a</h4>
            <div class="reservation-message-body">
              Pendiente por configurar.
            </div>
          </div>
        </div>

        <div class="reservation-ota-info-card">
          <div class="reservation-ota-info-body">
            <div class="reservation-ota-info-head">
              <span class="reservation-ota-info-title">Informaci&oacute;n</span>
            </div>

            <?php if (!$reservationInfoConfigRows): ?>
              <?php if ($otaDetectedId <= 0 && $detailSourceIdForInfo <= 0): ?>
                <p class="muted">Esta reserva no tiene origen vinculado para mostrar informaci&oacute;n.</p>
              <?php else: ?>
                <p class="muted">El origen de esta reserva no tiene conceptos configurados para este cuadro.</p>
              <?php endif; ?>
            <?php elseif (!$otaInfoRowsVisible): ?>
              <p class="muted">No hay montos para mostrar en los conceptos configurados.</p>
            <?php else: ?>
            <div class="table-scroll reservation-ota-info-table-wrap">
              <table class="reservation-ota-info-table">
                <thead>
                  <tr>
                    <th>Concepto</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($otaInfoRowsVisible as $otaInfo): ?>
                    <?php
                      $amountTotal = isset($otaInfo['amount_total_cents']) ? (int)$otaInfo['amount_total_cents'] : 0;
                      $itemCount = isset($otaInfo['item_count']) ? (int)$otaInfo['item_count'] : 0;
                      $rowClass = $itemCount > 0 ? '' : 'is-empty';
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                      <td><?php echo htmlspecialchars(isset($otaInfo['label']) ? (string)$otaInfo['label'] : 'Concepto', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars(reservations_format_money($amountTotal, $summaryCurrency), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      </div>


        <?php
          $reservationCodeDisplay = isset($reservationCode) ? (string)$reservationCode : (isset($detail['reservation_code']) ? (string)$detail['reservation_code'] : '');
          $guestCombinedNameDisplay = isset($guestCombinedName) && trim((string)$guestCombinedName) !== ''
              ? (string)$guestCombinedName
              : (string)$summaryGuest;
          $detailPaymentCatalogs = reservations_payment_catalogs_for_reservation(
              $paymentCatalogsByProperty,
              $propertyCodeDetail,
              $companyId,
              $reservationId
          );
          $lodgingConceptOptionsByIdForEdit = reservations_lodging_concept_options_for_property(
              $companyCode,
              $companyId,
              $propertyCodeDetail
          );

          $folioDisplayRows = array();
          $summaryLodgingBalanceCents = 0;
          $summaryServicesBalanceCents = 0;
          $summaryTotalBalanceCents = 0;
          $summaryBalanceCurrency = $summaryCurrency;

          foreach ($folios as $folioCandidate) {
              $fid = isset($folioCandidate['id_folio']) ? (int)$folioCandidate['id_folio'] : 0;
              if ($fid <= 0) {
                  continue;
              }
              $folioCurrency = isset($folioCandidate['currency']) && trim((string)$folioCandidate['currency']) !== ''
                  ? (string)$folioCandidate['currency']
                  : $summaryCurrency;
              $metric = isset($displayMetricsByFolio[$fid]) ? $displayMetricsByFolio[$fid] : array();
              $balanceCents = isset($metric['balance_cents']) ? (int)$metric['balance_cents'] : 0;
              $folioName = isset($folioCandidate['folio_name']) ? trim((string)$folioCandidate['folio_name']) : '';
              $folioRole = reservations_folio_role_by_name($folioName);
              if ($folioRole === 'services') {
                  $summaryServicesBalanceCents += $balanceCents;
              } else {
                  $summaryLodgingBalanceCents += $balanceCents;
              }
              $summaryTotalBalanceCents += $balanceCents;
              if ($summaryBalanceCurrency === '' && $folioCurrency !== '') {
                  $summaryBalanceCurrency = $folioCurrency;
              }
              $folioDisplayRows[] = array(
                  'folio' => $folioCandidate,
                  'id' => $fid,
                  'name' => $folioName !== '' ? $folioName : ('Folio #' . $fid),
                  'role' => $folioRole,
                  'currency' => $folioCurrency,
                  'balance_cents' => $balanceCents,
                  'sort' => $folioRole === 'services' ? 1 : 0
              );
          }

          usort($folioDisplayRows, function ($a, $b) {
              $sortA = isset($a['sort']) ? (int)$a['sort'] : 0;
              $sortB = isset($b['sort']) ? (int)$b['sort'] : 0;
              if ($sortA !== $sortB) {
                  return $sortA - $sortB;
              }
              $nameA = isset($a['name']) ? (string)$a['name'] : '';
              $nameB = isset($b['name']) ? (string)$b['name'] : '';
              return strcasecmp($nameA, $nameB);
          });

          $isInLodgingTreeGlobal = function (array $saleRow) use ($saleItemsById, $isLodgingSaleItem) {
              $currentId = isset($saleRow['id_sale_item']) ? (int)$saleRow['id_sale_item'] : 0;
              if ($currentId <= 0) {
                  return false;
              }
              $seen = array();
              while ($currentId > 0 && !isset($seen[$currentId])) {
                  $seen[$currentId] = true;
                  if (!isset($saleItemsById[$currentId])) {
                      break;
                  }
                  $currentRow = $saleItemsById[$currentId];
                  if (is_callable($isLodgingSaleItem) && (bool)$isLodgingSaleItem($currentRow)) {
                      return true;
                  }
                  $currentId = isset($currentRow['id_parent_sale_item']) ? (int)$currentRow['id_parent_sale_item'] : 0;
              }
              return false;
          };
          $isInPaymentTreeGlobal = function (array $saleRow) use ($saleItemsById, $paymentLineItemsById, $paymentCatalogsByFolio) {
              $rowFolioId = isset($saleRow['id_folio']) ? (int)$saleRow['id_folio'] : 0;
              $currentId = isset($saleRow['id_sale_item']) ? (int)$saleRow['id_sale_item'] : 0;
              if ($currentId <= 0) {
                  return false;
              }
              $seen = array();
              while ($currentId > 0 && !isset($seen[$currentId])) {
                  $seen[$currentId] = true;
                  if (isset($paymentLineItemsById[$currentId])) {
                      return true;
                  }
                  if (!isset($saleItemsById[$currentId])) {
                      break;
                  }
                  $currentRow = $saleItemsById[$currentId];
                  $parentCatalogId = isset($currentRow['parent_sale_item_catalog_id']) ? (int)$currentRow['parent_sale_item_catalog_id'] : 0;
                  if ($rowFolioId > 0 && $parentCatalogId > 0
                      && isset($paymentCatalogsByFolio[$rowFolioId])
                      && isset($paymentCatalogsByFolio[$rowFolioId][$parentCatalogId])) {
                      return true;
                  }
                  $currentId = isset($currentRow['id_parent_sale_item']) ? (int)$currentRow['id_parent_sale_item'] : 0;
              }
              return false;
          };

          $allVisibleItemsFlat = array();
          foreach ($visibleItemsByFolio as $visibleRowsByFolio) {
              foreach ($visibleRowsByFolio as $visibleRow) {
                  $sid = isset($visibleRow['id_sale_item']) ? (int)$visibleRow['id_sale_item'] : 0;
                  if ($sid <= 0 || isset($allVisibleItemsFlat[$sid])) {
                      continue;
                  }
                  $allVisibleItemsFlat[$sid] = $visibleRow;
              }
          }

          $lodgingTreeItemsAll = array();
          foreach ($allVisibleItemsFlat as $sid => $visibleRow) {
              if (!$isInLodgingTreeGlobal($visibleRow)) {
                  continue;
              }
              $lodgingTreeItemsAll[$sid] = $visibleRow;
          }
        ?>
        <div class="subtab-info">
          <div
            class="cargos-pagos-head"
            data-cargos-balances
            data-balance-total="<?php echo htmlspecialchars(reservations_format_money($summaryTotalBalanceCents, $summaryBalanceCurrency), ENT_QUOTES, 'UTF-8'); ?>"
            data-balance-lodging="<?php echo htmlspecialchars(reservations_format_money($summaryLodgingBalanceCents, $summaryBalanceCurrency), ENT_QUOTES, 'UTF-8'); ?>"
            data-balance-services="<?php echo htmlspecialchars(reservations_format_money($summaryServicesBalanceCents, $summaryBalanceCurrency), ENT_QUOTES, 'UTF-8'); ?>"
          >
            <h4>Cargos y pagos</h4>
            <div class="cargos-pagos-metrics">
              <span class="balance-item">Final: <strong><?php echo reservations_format_money($summaryTotalBalanceCents, $summaryBalanceCurrency); ?></strong></span>
              <span class="balance-item">Hospedaje: <strong><?php echo reservations_format_money($summaryLodgingBalanceCents, $summaryBalanceCurrency); ?></strong></span>
              <span class="balance-item">Servicios: <strong><?php echo reservations_format_money($summaryServicesBalanceCents, $summaryBalanceCurrency); ?></strong></span>
            </div>
          </div>

          <?php if ($folioDisplayRows): ?>
            <div class="folio-charge-tabs" data-reservation-tabs>
              <div class="folio-charge-tab-head">
                <?php foreach ($folioDisplayRows as $idx => $folioInfo): ?>
                  <?php
                    $tabFid = isset($folioInfo['id']) ? (int)$folioInfo['id'] : 0;
                    $tabName = isset($folioInfo['name']) ? (string)$folioInfo['name'] : ('Folio #' . $tabFid);
                    $tabCurrency = isset($folioInfo['currency']) ? (string)$folioInfo['currency'] : $summaryBalanceCurrency;
                    $tabBalanceCents = isset($folioInfo['balance_cents']) ? (int)$folioInfo['balance_cents'] : 0;
                    $panelIdFolio = 'folio-' . (int)$reservationId . '-' . $tabFid . '-panel';
                  ?>
                  <button type="button" class="reservation-tab-trigger <?php echo $idx === 0 ? 'is-active' : ''; ?>" data-tab-target="<?php echo htmlspecialchars($panelIdFolio, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($tabName, ENT_QUOTES, 'UTF-8'); ?>: <?php echo reservations_format_money($tabBalanceCents, $tabCurrency); ?>
                  </button>
                <?php endforeach; ?>
              </div>

              <?php foreach ($folioDisplayRows as $idx => $folioInfo): ?>
                <?php
                  $folio = isset($folioInfo['folio']) ? $folioInfo['folio'] : array();
                  $fid = isset($folioInfo['id']) ? (int)$folioInfo['id'] : 0;
                  $folioName = isset($folioInfo['name']) ? (string)$folioInfo['name'] : ('Folio #' . $fid);
                  $folioRole = isset($folioInfo['role']) ? (string)$folioInfo['role'] : 'lodging';
                  $folioCurrency = isset($folioInfo['currency']) ? (string)$folioInfo['currency'] : $summaryBalanceCurrency;
                  $items = isset($visibleItemsByFolio[$fid]) ? $visibleItemsByFolio[$fid] : array();
                  $paneItems = array();
                  if ($folioRole === 'lodging') {
                      foreach ($items as $row) {
                          if ($isInLodgingTreeGlobal($row) || $isInPaymentTreeGlobal($row)) {
                              $paneItems[] = $row;
                          }
                      }
                      if (!$paneItems && $items) {
                          $paneItems = $items;
                      }
                      if (!$paneItems && $lodgingTreeItemsAll) {
                          $paneItems = array_values($lodgingTreeItemsAll);
                      }
                  } else {
                      foreach ($items as $row) {
                          if ($isInPaymentTreeGlobal($row) || !$isInLodgingTreeGlobal($row)) {
                              $paneItems[] = $row;
                          }
                      }
                  }
                  $folioPayments = isset($paymentsByFolio[$fid]) ? $paymentsByFolio[$fid] : array();
                  $sourceFolioBalanceCents = isset($folioInfo['balance_cents']) ? max(0, (int)$folioInfo['balance_cents']) : 0;
                  $otherPendingFolioId = 0;
                  $otherPendingBalanceCents = 0;
                  foreach ($folioDisplayRows as $candidateFolioInfo) {
                      $candidateId = isset($candidateFolioInfo['id']) ? (int)$candidateFolioInfo['id'] : 0;
                      if ($candidateId <= 0 || $candidateId === $fid) {
                          continue;
                      }
                      $candidateBalance = isset($candidateFolioInfo['balance_cents']) ? max(0, (int)$candidateFolioInfo['balance_cents']) : 0;
                      if ($candidateBalance <= 0) {
                          continue;
                      }
                      $otherPendingFolioId = $candidateId;
                      $otherPendingBalanceCents = $candidateBalance;
                      break;
                  }
                  $paymentFoliosContextRows = array();
                  foreach ($folioDisplayRows as $folioCtxRow) {
                      $folioCtxId = isset($folioCtxRow['id']) ? (int)$folioCtxRow['id'] : 0;
                      if ($folioCtxId <= 0) {
                          continue;
                      }
                      $paymentFoliosContextRows[] = array(
                          'id_folio' => $folioCtxId,
                          'folio_name' => isset($folioCtxRow['name']) ? (string)$folioCtxRow['name'] : ('Folio #' . $folioCtxId),
                          'balance_cents' => isset($folioCtxRow['balance_cents']) ? (int)$folioCtxRow['balance_cents'] : 0,
                          'currency' => isset($folioCtxRow['currency']) ? (string)$folioCtxRow['currency'] : $summaryBalanceCurrency,
                          'role' => isset($folioCtxRow['role']) ? (string)$folioCtxRow['role'] : ''
                      );
                  }
                  $paymentFoliosContextJson = json_encode($paymentFoliosContextRows, JSON_UNESCAPED_UNICODE);
                  if (!is_string($paymentFoliosContextJson)) {
                      $paymentFoliosContextJson = '[]';
                  }
                  $editableLodgingSaleItemIdForPane = 0;
                  if ($folioRole === 'lodging' && $paneItems) {
                      if ($primaryLodgingLineItemId > 0) {
                          foreach ($paneItems as $paneRowCandidate) {
                              $paneRowSaleId = isset($paneRowCandidate['id_sale_item']) ? (int)$paneRowCandidate['id_sale_item'] : 0;
                              if ($paneRowSaleId > 0 && $paneRowSaleId === (int)$primaryLodgingLineItemId) {
                                  $editableLodgingSaleItemIdForPane = $paneRowSaleId;
                                  break;
                              }
                          }
                      }
                      if ($editableLodgingSaleItemIdForPane <= 0) {
                          foreach ($paneItems as $paneRowCandidate) {
                              $paneRowSaleId = isset($paneRowCandidate['id_sale_item']) ? (int)$paneRowCandidate['id_sale_item'] : 0;
                              if ($paneRowSaleId <= 0) {
                                  continue;
                              }
                              if (is_callable($isInPaymentTreeGlobal) && (bool)$isInPaymentTreeGlobal($paneRowCandidate)) {
                                  continue;
                              }
                              if (is_callable($isInLodgingTreeGlobal) && !(bool)$isInLodgingTreeGlobal($paneRowCandidate)) {
                                  continue;
                              }
                              $editableLodgingSaleItemIdForPane = $paneRowSaleId;
                              break;
                          }
                      }
                      if ($editableLodgingSaleItemIdForPane <= 0) {
                          foreach ($paneItems as $paneRowCandidate) {
                              $paneRowSaleId = isset($paneRowCandidate['id_sale_item']) ? (int)$paneRowCandidate['id_sale_item'] : 0;
                              if ($paneRowSaleId <= 0) {
                                  continue;
                              }
                              if (is_callable($isInPaymentTreeGlobal) && (bool)$isInPaymentTreeGlobal($paneRowCandidate)) {
                                  continue;
                              }
                              $editableLodgingSaleItemIdForPane = $paneRowSaleId;
                              break;
                          }
                      }
                  }
                  $panelIdFolio = 'folio-' . (int)$reservationId . '-' . $fid . '-panel';
                ?>
                <div class="folio-charge-pane <?php echo $idx === 0 ? 'is-active' : ''; ?>" id="<?php echo htmlspecialchars($panelIdFolio, ENT_QUOTES, 'UTF-8'); ?>" data-tab-panel data-folio-edit-pane>
                  <div class="folio-section">
                    <div class="folio-section-header">
                      <h5><?php echo htmlspecialchars($folioName, ENT_QUOTES, 'UTF-8'); ?></h5>
                      <div class="folio-section-header-actions">
                        <?php if ($folioRole === 'services'): ?>
                          <button
                            type="button"
                            class="button-secondary js-open-service-lightbox"
                            data-service-reservation-id="<?php echo (int)$reservationId; ?>"
                            data-service-folio-id="<?php echo (int)$fid; ?>"
                            data-service-property-code="<?php echo htmlspecialchars((string)$propertyCodeDetail, ENT_QUOTES, 'UTF-8'); ?>"
                            data-service-reservation-code="<?php echo htmlspecialchars($reservationCodeDisplay, ENT_QUOTES, 'UTF-8'); ?>"
                            data-service-guest-name="<?php echo htmlspecialchars($guestCombinedNameDisplay, ENT_QUOTES, 'UTF-8'); ?>"
                            data-service-currency="<?php echo htmlspecialchars((string)$folioCurrency, ENT_QUOTES, 'UTF-8'); ?>"
                          >Nuevo servicio</button>
                        <?php endif; ?>
                        <button type="button" class="button-secondary js-folio-edit-toggle" data-folio-edit-toggle>Editar</button>
                      </div>
                    </div>

                    <?php if ($paneItems): ?>
                      <div class="table-scroll">
                        <table>
                          <thead>
                            <tr>
                              <th>Concepto</th>
                              <th>Fecha</th>
                              <th>Cantidad</th>
                              <th>Precio unitario</th>
                              <th>Total</th>
                              <th>Total final</th>
                              <th class="folio-edit-only"></th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($paneItems as $si): ?>
                              <?php
                                $saleItemId = isset($si['id_sale_item']) ? (int)$si['id_sale_item'] : 0;
                                $rowFolioId = isset($si['id_folio']) ? (int)$si['id_folio'] : $fid;
                                $parentSaleId = isset($si['id_parent_sale_item']) ? (int)$si['id_parent_sale_item'] : 0;
                                $addToFatherRow = array_key_exists('add_to_father_total', $si) ? (int)$si['add_to_father_total'] : null;
                                $parentCatalogIdFromRow = isset($si['parent_sale_item_catalog_id']) ? (int)$si['parent_sale_item_catalog_id'] : 0;
                                $parentIsPaymentLike = ($parentSaleId > 0 && isset($paymentLineItemsById[$parentSaleId]))
                                    || (
                                        $rowFolioId > 0
                                        && $parentCatalogIdFromRow > 0
                                        && isset($paymentCatalogsByFolio[$rowFolioId])
                                        && isset($paymentCatalogsByFolio[$rowFolioId][$parentCatalogIdFromRow])
                                    );
                                if ($parentSaleId > 0 && !isset($saleItemsById[$parentSaleId]) && !$parentIsPaymentLike && ($addToFatherRow === null || $addToFatherRow !== 0)) {
                                    continue;
                                }
                                if ($parentSaleId > 0) {
                                    $parentCatalogId = 0;
                                    if (isset($saleItemsById[$parentSaleId]['id_sale_item_catalog'])) {
                                        $parentCatalogId = (int)$saleItemsById[$parentSaleId]['id_sale_item_catalog'];
                                    } elseif ($parentIsPaymentLike) {
                                        if ($parentSaleId > 0 && isset($paymentLineItemsById[$parentSaleId])) {
                                            $parentCatalogId = isset($paymentLineItemsById[$parentSaleId]['id_payment_catalog'])
                                                ? (int)$paymentLineItemsById[$parentSaleId]['id_payment_catalog']
                                                : 0;
                                        } elseif ($parentCatalogIdFromRow > 0) {
                                            $parentCatalogId = $parentCatalogIdFromRow;
                                        }
                                    }
                                    $childCatalogId = isset($si['id_sale_item_catalog']) ? (int)$si['id_sale_item_catalog'] : 0;
                                    $relationKey = $childCatalogId > 0 && $parentCatalogId > 0 ? ($childCatalogId . ':' . $parentCatalogId) : '';
                                    $shouldAdd = 0;
                                    if ($relationKey !== '' && isset($addToFatherMap[$relationKey])) {
                                        $shouldAdd = (int)$addToFatherMap[$relationKey];
                                    } elseif ($addToFatherRow !== null) {
                                        $shouldAdd = $addToFatherRow;
                                    }
                                    if ($shouldAdd && $folioRole !== 'lodging' && !$parentIsPaymentLike) {
                                        continue;
                                    }
                                }
                                $subcatName = isset($si['subcategory_name']) ? trim((string)$si['subcategory_name']) : '';
                                $itemName = isset($si['item_name']) ? (string)$si['item_name'] : '';
                                $conceptLabel = $subcatName !== '' ? ($subcatName . ' / ' . $itemName) : $itemName;
                                $conceptLabelDisplay = trim((string)$conceptLabel) !== '' ? (string)$conceptLabel : ('Concepto #' . (int)$saleItemId);
                                $saleCatalogId = isset($si['id_sale_item_catalog']) ? (int)$si['id_sale_item_catalog'] : 0;
                                $updateFormId = 'sale-update-' . $rowFolioId . '-' . (int)$saleItemId;
                                $quantityValue = isset($si['quantity']) ? (float)$si['quantity'] : 0;
                                $quantityDisplay = (string)((int)round($quantityValue));
                                $lineTotalCents = isset($si['amount_cents']) ? (int)$si['amount_cents'] : 0;
                                $finalTotalCents = $finalTotalForSaleItem((int)$saleItemId);
                                $isLodgingConceptEditableRow = ($folioRole === 'lodging'
                                    && $editableLodgingSaleItemIdForPane > 0
                                    && $saleItemId === $editableLodgingSaleItemIdForPane);
                                $isProtectedPrimaryLodging = $isLodgingConceptEditableRow;
                                $isPaymentTreeRow = is_callable($isInPaymentTreeGlobal) ? (bool)$isInPaymentTreeGlobal($si) : false;
                                $derivedGroupId = 'sale-derived-' . (int)$saleItemId;
                                $collapsedChildIds = isset($collapsedChildrenByParent[$saleItemId]) ? $collapsedChildrenByParent[$saleItemId] : array();
                                $taxRows = isset($taxItemsBySale[$saleItemId]) ? $taxItemsBySale[$saleItemId] : array();
                                $hasDerivedRows = !empty($collapsedChildIds) || !empty($taxRows);
                                $conceptOptionsForRow = array();
                                if ($isLodgingConceptEditableRow) {
                                    $conceptOptionsForRow = $lodgingConceptOptionsByIdForEdit;
                                }
                                if ($isLodgingConceptEditableRow && !$conceptOptionsForRow && $saleCatalogId > 0) {
                                    $conceptOptionsForRow[$saleCatalogId] = array(
                                        'id' => $saleCatalogId,
                                        'label' => $conceptLabelDisplay
                                    );
                                }
                                if ($conceptOptionsForRow && !isset($conceptOptionsForRow[$saleCatalogId])) {
                                    $conceptOptionsForRow[$saleCatalogId] = array(
                                        'id' => $saleCatalogId,
                                        'label' => $conceptLabelDisplay
                                    );
                                }
                                $unitPriceValueRaw = ((isset($si['unit_price_cents']) ? (int)$si['unit_price_cents'] : 0) / 100);
                              ?>
                              <tr>
                                <td>
                                  <?php if ($conceptOptionsForRow): ?>
                                    <span class="folio-readonly-only"><?php echo htmlspecialchars($conceptLabelDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <select name="sale_catalog_id" form="<?php echo $updateFormId; ?>" class="folio-edit-only folio-edit-field" style="width:100%;" disabled>
                                      <?php foreach ($conceptOptionsForRow as $conceptOption): ?>
                                        <?php
                                          $conceptOptionId = isset($conceptOption['id']) ? (int)$conceptOption['id'] : 0;
                                          if ($conceptOptionId <= 0) {
                                              continue;
                                          }
                                          $conceptOptionLabel = isset($conceptOption['label']) ? (string)$conceptOption['label'] : ('Concepto #' . $conceptOptionId);
                                        ?>
                                        <option value="<?php echo (int)$conceptOptionId; ?>" <?php echo $conceptOptionId === $saleCatalogId ? 'selected' : ''; ?>>
                                          <?php echo htmlspecialchars($conceptOptionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                      <?php endforeach; ?>
                                    </select>
                                  <?php else: ?>
                                    <?php echo htmlspecialchars($conceptLabelDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                  <?php endif; ?>
                                  <?php if ($hasDerivedRows): ?>
                                    <button type="button" class="button-secondary" data-tax-toggle="<?php echo htmlspecialchars($derivedGroupId, ENT_QUOTES, 'UTF-8'); ?>" data-open="0" style="margin-left:8px;padding:2px 8px;font-size:.78rem;line-height:1.2;">Ver derivados</button>
                                  <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string)$si['service_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                  <span class="folio-readonly-only"><?php echo htmlspecialchars($quantityDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                  <input type="number" step="1" min="1" name="sale_quantity" form="<?php echo $updateFormId; ?>" class="folio-edit-only folio-edit-field" value="<?php echo htmlspecialchars($quantityDisplay, ENT_QUOTES, 'UTF-8'); ?>" style="width:80px" disabled>
                                </td>
                                <td>
                                  <span class="folio-readonly-only"><?php echo htmlspecialchars(number_format((float)$unitPriceValueRaw, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                  <input type="number" step="0.01" name="sale_unit_price" form="<?php echo $updateFormId; ?>" class="folio-edit-only folio-edit-field" value="<?php echo htmlspecialchars((string)$unitPriceValueRaw, ENT_QUOTES, 'UTF-8'); ?>" style="width:100px" disabled>
                                </td>
                                <td><?php echo reservations_format_money($lineTotalCents, $folioCurrency); ?></td>
                                <td><?php echo reservations_format_money($finalTotalCents, $folioCurrency); ?></td>
                                <td class="folio-row-actions folio-edit-only">
                                  <form method="post" class="inline-form" id="<?php echo $updateFormId; ?>" onsubmit="return confirm('Confirmar actualizacion de line item y recalculo de derivados?');">
                                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                    <?php reservations_render_filter_hiddens($filters); ?>
                                    <input type="hidden" name="reservations_action" value="update_sale_item">
                                    <input type="hidden" name="sale_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                    <input type="hidden" name="sale_folio_id" value="<?php echo $rowFolioId; ?>">
                                    <input type="hidden" name="sale_item_id" value="<?php echo (int)$saleItemId; ?>">
                                  </form>
                                  <form method="post" class="inline-form" onsubmit="return confirm('Confirmar eliminacion de line item? Tambien se eliminaran line items hijos/derivados.');">
                                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                    <?php reservations_render_filter_hiddens($filters); ?>
                                    <input type="hidden" name="reservations_action" value="delete_sale_item">
                                    <input type="hidden" name="sale_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                    <input type="hidden" name="sale_item_id" value="<?php echo (int)$saleItemId; ?>">
                                    <button
                                      type="submit"
                                      class="button-secondary"
                                      <?php
                                        if ($isProtectedPrimaryLodging) {
                                            echo 'disabled title="Este concepto de hospedaje no se elimina; cambialo desde el selector de concepto."';
                                        } elseif ($isPaymentTreeRow) {
                                            echo 'disabled title="Este concepto viene del arbol de un pago. Ajustalo desde la seccion de pagos."';
                                        }
                                      ?>
                                    >Eliminar</button>
                                  </form>
                                </td>
                              </tr>
                              <?php if ($hasDerivedRows): ?>
                                <?php foreach ($collapsedChildIds as $derivedSaleItemId): ?>
                                  <?php
                                    $derivedSaleItemId = (int)$derivedSaleItemId;
                                    if ($derivedSaleItemId <= 0 || !isset($saleItemsById[$derivedSaleItemId])) {
                                        continue;
                                    }
                                    $derivedRow = $saleItemsById[$derivedSaleItemId];
                                    $derivedSubcat = isset($derivedRow['subcategory_name']) ? trim((string)$derivedRow['subcategory_name']) : '';
                                    $derivedItem = isset($derivedRow['item_name']) ? (string)$derivedRow['item_name'] : '';
                                    $derivedLabel = $derivedSubcat !== '' ? ($derivedSubcat . ' / ' . $derivedItem) : $derivedItem;
                                    $derivedAmountCents = isset($derivedRow['amount_cents']) ? (int)$derivedRow['amount_cents'] : 0;
                                    $derivedFinalCents = $finalTotalForSaleItem($derivedSaleItemId);
                                  ?>
                                  <tr class="tax-item-row" data-tax-group="<?php echo htmlspecialchars($derivedGroupId, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;">
                                    <td class="tax-label"><?php echo htmlspecialchars('Derivado: ' . $derivedLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td><?php echo reservations_format_money($derivedAmountCents, $folioCurrency); ?></td>
                                    <td><?php echo reservations_format_money($derivedFinalCents, $folioCurrency); ?></td>
                                    <td class="folio-row-actions folio-edit-only">
                                      <form method="post" class="inline-form" onsubmit="return confirm('Confirmar eliminacion de line item derivado?');">
                                        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                        <?php reservations_render_filter_hiddens($filters); ?>
                                        <input type="hidden" name="reservations_action" value="delete_sale_item">
                                        <input type="hidden" name="sale_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                        <input type="hidden" name="sale_item_id" value="<?php echo (int)$derivedSaleItemId; ?>">
                                        <input type="hidden" name="sale_skip_percent_recalc" value="1">
                                        <button type="submit" class="button-secondary">Eliminar</button>
                                      </form>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                                <?php foreach ($taxRows as $ti): ?>
                                  <?php
                                    $taxName = isset($ti['tax_name']) ? (string)$ti['tax_name'] : 'Impuesto';
                                    $rate = isset($ti['rate_percent']) ? (float)$ti['rate_percent'] : null;
                                    $rateLabel = $rate === null ? '' : rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
                                    $taxLabel = $taxName . ($rateLabel !== '' ? (' (' . $rateLabel . '%)') : '');
                                    $taxAmount = isset($ti['amount_cents']) ? (int)$ti['amount_cents'] : 0;
                                  ?>
                                  <tr class="tax-item-row" data-tax-group="<?php echo htmlspecialchars($derivedGroupId, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;">
                                    <td class="tax-label"><?php echo htmlspecialchars('Derivado: ' . $taxLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td><?php echo reservations_format_money($taxAmount, $folioCurrency); ?></td>
                                    <td><?php echo reservations_format_money($taxAmount, $folioCurrency); ?></td>
                                    <td class="folio-edit-only"></td>
                                  </tr>
                                <?php endforeach; ?>
                              <?php endif; ?>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <p class="muted">Sin cargos en este folio.</p>
                    <?php endif; ?>
                  </div>

                  <div class="folio-section">
                    <div class="folio-section-header">
                      <h5>Pagos</h5>
                      <button
                        type="button"
                        class="button-secondary js-open-payment-lightbox"
                        data-payment-reservation-id="<?php echo (int)$reservationId; ?>"
                        data-payment-folio-id="<?php echo (int)$fid; ?>"
                        data-payment-property-code="<?php echo htmlspecialchars((string)$propertyCodeDetail, ENT_QUOTES, 'UTF-8'); ?>"
                        data-payment-reservation-code="<?php echo htmlspecialchars($reservationCodeDisplay, ENT_QUOTES, 'UTF-8'); ?>"
                        data-payment-guest-name="<?php echo htmlspecialchars($guestCombinedNameDisplay, ENT_QUOTES, 'UTF-8'); ?>"
                        data-payment-currency="<?php echo htmlspecialchars((string)$folioCurrency, ENT_QUOTES, 'UTF-8'); ?>"
                        data-payment-balance-cents="<?php echo (int)$summaryTotalBalanceCents; ?>"
                        data-payment-folios="<?php echo htmlspecialchars($paymentFoliosContextJson, ENT_QUOTES, 'UTF-8'); ?>"
                      >Nuevo pago</button>
                    </div>
                    <form method="post" class="form-inline" id="payment-form-<?php echo $fid; ?>" style="display:none;">
                      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                      <?php reservations_render_filter_hiddens($filters); ?>
                      <input type="hidden" name="reservations_action" value="create_payment">
                      <input type="hidden" name="payment_reservation_id" value="<?php echo (int)$reservationId; ?>">
                      <input type="hidden" name="payment_folio_id" value="<?php echo $fid; ?>">
                      <input type="hidden" name="payment_source_balance_cents" value="<?php echo (int)$sourceFolioBalanceCents; ?>">
                      <input type="hidden" name="payment_other_folio_pending_id" value="<?php echo (int)$otherPendingFolioId; ?>">
                      <input type="hidden" name="payment_other_folio_pending_balance_cents" value="<?php echo (int)$otherPendingBalanceCents; ?>">
                      <input type="hidden" name="payment_transfer_remaining" value="0">
                      <input type="hidden" name="payment_transfer_target_folio_id" value="">
                      <label>Concepto de pago
                        <select name="payment_method">
                          <option value="0">(Selecciona concepto)</option>
                          <?php foreach ($detailPaymentCatalogs as $pc): ?>
                            <?php $pcId = (int)$pc['id_payment_catalog']; ?>
                            <option value="<?php echo $pcId; ?>">
                              <?php echo htmlspecialchars((string)(isset($pc['label']) ? $pc['label'] : $pc['name']), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>Monto <input type="number" step="0.01" name="payment_amount" value="0"></label>
                      <label>Referencia <input type="text" name="payment_reference"></label>
                      <label>Fecha pago <input type="date" name="payment_service_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"></label>
                      <button type="submit">Guardar pago</button>
                    </form>

                    <?php
                      $showRefundedColumn = false;
                      foreach ($folioPayments as $paymentRowForRefundColumn) {
                          $paymentRefundedCents = isset($paymentRowForRefundColumn['refunded_total_cents']) ? (int)$paymentRowForRefundColumn['refunded_total_cents'] : 0;
                          if ($paymentRefundedCents !== 0) {
                              $showRefundedColumn = true;
                              break;
                          }
                      }
                    ?>
                    <?php if ($folioPayments): ?>
                      <div class="table-scroll">
                        <table>
                          <thead>
                            <tr>
                              <th>Metodo</th>
                              <th>Fecha</th>
                              <th>Ref</th>
                              <?php if ($showRefundedColumn): ?>
                                <th>Reembolsado</th>
                              <?php endif; ?>
                              <th>Monto</th>
                              <th class="folio-edit-only">Acciones</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($folioPayments as $p): ?>
                              <?php
                                $pid = isset($p['id_payment']) ? (int)$p['id_payment'] : 0;
                                $refundsForPayment = isset($refundsByPayment[$pid]) ? $refundsByPayment[$pid] : array();
                              ?>
                              <tr>
                                <td><?php echo htmlspecialchars((string)$p['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$p['service_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)$p['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php if ($showRefundedColumn): ?>
                                  <td><?php echo reservations_format_money(isset($p['refunded_total_cents']) ? $p['refunded_total_cents'] : 0, $folioCurrency); ?></td>
                                <?php endif; ?>
                                <td><?php echo reservations_format_money(isset($p['amount_cents']) ? $p['amount_cents'] : 0, $folioCurrency); ?></td>
                                <td class="form-inline folio-edit-only">
                                  <form method="post" class="inline-form">
                                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                    <?php reservations_render_filter_hiddens($filters); ?>
                                    <input type="hidden" name="reservations_action" value="delete_payment">
                                    <input type="hidden" name="payment_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                    <input type="hidden" name="payment_id" value="<?php echo $pid; ?>">
                                    <button type="submit" class="button-secondary">Eliminar</button>
                                  </form>
                                </td>
                              </tr>
                              <?php if ($refundsForPayment): ?>
                                <tr>
                                  <td colspan="<?php echo $showRefundedColumn ? '6' : '5'; ?>">
                                    <div class="table-scroll">
                                      <table>
                                        <thead>
                                          <tr>
                                            <th>Reembolso</th>
                                            <th>Monto</th>
                                            <th>Referencia</th>
                                            <th>Motivo</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                          </tr>
                                        </thead>
                                        <tbody>
                                          <?php foreach ($refundsForPayment as $r): ?>
                                            <tr>
                                              <td>#<?php echo (int)$r['id_refund']; ?></td>
                                              <td><?php echo reservations_format_money(isset($r['amount_cents']) ? $r['amount_cents'] : 0, $folioCurrency); ?></td>
                                              <td><?php echo htmlspecialchars((string)$r['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                                              <td><?php echo htmlspecialchars((string)$r['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                                              <td><?php echo htmlspecialchars((string)$r['refunded_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                              <td>
                                                <form method="post" class="inline-form">
                                                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                                  <?php reservations_render_filter_hiddens($filters); ?>
                                                  <input type="hidden" name="reservations_action" value="delete_refund">
                                                  <input type="hidden" name="refund_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                                  <input type="hidden" name="refund_id" value="<?php echo (int)$r['id_refund']; ?>">
                                                  <input type="hidden" name="refund_payment_id" value="<?php echo $pid; ?>">
                                                  <input type="hidden" name="refund_amount" value="<?php echo htmlspecialchars((string)((isset($r['amount_cents']) ? $r['amount_cents'] : 0)/100), ENT_QUOTES, 'UTF-8'); ?>">
                                                  <button type="submit" class="button-secondary">Eliminar</button>
                                                </form>
                                              </td>
                                            </tr>
                                          <?php endforeach; ?>
                                        </tbody>
                                      </table>
                                    </div>
                                  </td>
                                </tr>
                              <?php endif; ?>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <p class="muted">Sin pagos registrados.</p>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="muted">Sin folios en esta reserva.</p>
          <?php endif; ?>
        </div>
<?php $renderOldFolios = false; ?>
<?php if ($renderOldFolios && $folios): ?>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Folio</th>
                  <th>Estatus</th>
                  <th>Total</th>
                  <th>Pagos</th>
                  <th>Reembolsos</th>
                  <th>Balance</th>
                  <th>Vence</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
        <?php foreach ($folios as $folio):
          $fid = isset($folio['id_folio']) ? (int)$folio['id_folio'] : 0;
          $folioCurrency = isset($folio['currency']) ? (string)$folio['currency'] : 'MXN';
          $items = isset($itemsByFolio[$fid]) ? $itemsByFolio[$fid] : array();
          $folioPayments = isset($paymentsByFolio[$fid]) ? $paymentsByFolio[$fid] : array();
        ?>
          <tr>
            <td><?php echo htmlspecialchars((string)$folio['folio_name'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string)$folio['status'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo reservations_format_money(isset($folio['total_cents']) ? $folio['total_cents'] : 0, $folioCurrency); ?></td>
            <td><?php echo reservations_format_money(isset($folio['payments_cents']) ? $folio['payments_cents'] : 0, $folioCurrency); ?></td>
            <td><?php echo reservations_format_money(isset($folio['refunds_cents']) ? $folio['refunds_cents'] : 0, $folioCurrency); ?></td>
            <td><?php echo reservations_format_money(isset($folio['balance_cents']) ? $folio['balance_cents'] : 0, $folioCurrency); ?></td>
            <td><?php echo htmlspecialchars((string)$folio['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="form-inline">
              <form method="post">
                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                <?php reservations_render_filter_hiddens($filters); ?>
                <input type="hidden" name="reservations_action" value="<?php echo $folio['status'] === 'closed' ? 'reopen_folio' : 'close_folio'; ?>">
                <input type="hidden" name="folio_id" value="<?php echo $fid; ?>">
                <input type="hidden" name="folio_reservation_id" value="<?php echo (int)$reservationId; ?>">
                <button type="submit" class="button-secondary"><?php echo $folio['status'] === 'closed' ? 'Reabrir' : 'Cerrar'; ?></button>
              </form>
              <button type="button" class="button-secondary js-folio-toggle" data-folio-target="folio-<?php echo $fid; ?>">Ver</button>
            </td>
          </tr>
          <tr class="folio-panel" id="folio-<?php echo $fid; ?>" style="display:none;">
            <td colspan="8">
              <div class="subtab-info">
                <h5><?php echo htmlspecialchars((string)$folio['folio_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string)$folio['status'], ENT_QUOTES, 'UTF-8'); ?>)</h5>
                <p>Total: <?php echo reservations_format_money(isset($folio['total_cents']) ? $folio['total_cents'] : 0, $folioCurrency); ?> | Pagos: <?php echo reservations_format_money(isset($folio['payments_cents']) ? $folio['payments_cents'] : 0, $folioCurrency); ?> | Reembolsos: <?php echo reservations_format_money(isset($folio['refunds_cents']) ? $folio['refunds_cents'] : 0, $folioCurrency); ?> | Balance: <?php echo reservations_format_money(isset($folio['balance_cents']) ? $folio['balance_cents'] : 0, $folioCurrency); ?></p>

                <div class="folio-section-header" style="margin-bottom:10px;">
                  <h5>Servicios</h5>
                  <button
                    type="button"
                    class="button-secondary js-open-service-lightbox"
                    data-service-reservation-id="<?php echo (int)$reservationId; ?>"
                    data-service-folio-id="<?php echo (int)$fid; ?>"
                    data-service-property-code="<?php echo htmlspecialchars((string)$propertyCodeDetail, ENT_QUOTES, 'UTF-8'); ?>"
                    data-service-reservation-code="<?php echo htmlspecialchars((string)$reservationCode, ENT_QUOTES, 'UTF-8'); ?>"
                    data-service-guest-name="<?php echo htmlspecialchars((string)$guestCombinedName, ENT_QUOTES, 'UTF-8'); ?>"
                    data-service-currency="<?php echo htmlspecialchars((string)$folioCurrency, ENT_QUOTES, 'UTF-8'); ?>"
                  >Nuevo servicio</button>
                </div>

                <?php if ($items): ?>
                  <div class="table-scroll">
                    <table>
                      <thead>
                        <tr>
                          <th>Concepto</th>
                          <th>Tipo</th>
                          <th>Padre</th>
                          <th>Incl. total padre</th>
                          <th>Fecha</th>
                          <th>Cantidad</th>
                          <th>Precio unitario</th>
                          <th>Total</th>
                          <th>Total final</th>
                          <th>Estatus</th>
                          <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($items as $si): ?>
                          <?php
                            $parentSaleIdCheck = isset($si['id_parent_sale_item']) ? (int)$si['id_parent_sale_item'] : 0;
                            if ($parentSaleIdCheck > 0) {
                                if (!isset($saleItemsById) || !is_array($saleItemsById) || empty($saleItemsById)) {
                                    $saleItemsById = array();
                                    foreach ($items as $rowItem) {
                                        $rowId = isset($rowItem['id_sale_item']) ? (int)$rowItem['id_sale_item'] : 0;
                                        if ($rowId > 0) {
                                            $saleItemsById[$rowId] = $rowItem;
                                        }
                                    }
                                }
                                if (!isset($saleItemsById[$parentSaleIdCheck])) {
                                    continue;
                                }
                            }
                          ?>
                          <tr>
                            <?php
                              $subcatName = isset($si['subcategory_name']) ? trim((string)$si['subcategory_name']) : '';
                              $itemName = isset($si['item_name']) ? (string)$si['item_name'] : '';
                              $conceptLabel = $subcatName !== '' ? ($subcatName . ' / ' . $itemName) : $itemName;
                              $parentLabel = '-';
                              $addToFatherLabel = '-';
                              $parentSaleId = isset($si['id_parent_sale_item']) ? (int)$si['id_parent_sale_item'] : 0;
                              $childCatalogId = isset($si['id_sale_item_catalog']) ? (int)$si['id_sale_item_catalog'] : 0;
                              if ($parentSaleId > 0) {
                                  if (!isset($saleItemsById) || !is_array($saleItemsById) || empty($saleItemsById)) {
                                      $saleItemsById = array();
                                      foreach ($items as $rowItem) {
                                          $rowId = isset($rowItem['id_sale_item']) ? (int)$rowItem['id_sale_item'] : 0;
                                          if ($rowId > 0) {
                                              $saleItemsById[$rowId] = $rowItem;
                                          }
                                      }
                                  }
                                  if (isset($saleItemsById[$parentSaleId])) {
                                      $pSub = isset($saleItemsById[$parentSaleId]['subcategory_name']) ? trim((string)$saleItemsById[$parentSaleId]['subcategory_name']) : '';
                                      $pName = isset($saleItemsById[$parentSaleId]['item_name']) ? (string)$saleItemsById[$parentSaleId]['item_name'] : '';
                                      $parentLabel = $pSub !== '' ? ($pSub . ' / ' . $pName) : $pName;
                                      $parentCatalogId = isset($saleItemsById[$parentSaleId]['id_sale_item_catalog']) ? (int)$saleItemsById[$parentSaleId]['id_sale_item_catalog'] : 0;
                                      if ($childCatalogId > 0 && $parentCatalogId > 0 && isset($addToFatherMap) && is_array($addToFatherMap)) {
                                          $relationKey = $childCatalogId . ':' . $parentCatalogId;
                                          $addToFatherLabel = (isset($addToFatherMap[$relationKey]) && (int)$addToFatherMap[$relationKey] === 1) ? 'SÃƒÂ­' : 'No';
                                      } else {
                                          $addToFatherLabel = 'No';
                                      }
                                  } else {
                                      $parentLabel = 'ID ' . $parentSaleId;
                                      $addToFatherLabel = 'No';
                                  }
                              }
                              $updateFormId = 'sale-update-' . $fid . '-' . (int)$si['id_sale_item'];
                              $finalTotalCents = $finalTotalForSaleItem((int)$si['id_sale_item']);
                              $currentLineItemType = isset($si['item_type']) ? strtolower(trim((string)$si['item_type'])) : 'sale_item';
                              if (!in_array($currentLineItemType, array('sale_item', 'tax_item', 'payment', 'obligation', 'income'), true)) {
                                  $currentLineItemType = 'sale_item';
                              }
                            ?>
                            <td><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                              <select name="sale_item_type" form="<?php echo $updateFormId; ?>" style="width:130px">
                                <option value="sale_item" <?php echo $currentLineItemType === 'sale_item' ? 'selected' : ''; ?>>Concepto</option>
                                <option value="tax_item" <?php echo $currentLineItemType === 'tax_item' ? 'selected' : ''; ?>>Impuesto</option>
                                <option value="payment" <?php echo $currentLineItemType === 'payment' ? 'selected' : ''; ?>>Pago</option>
                                <option value="obligation" <?php echo $currentLineItemType === 'obligation' ? 'selected' : ''; ?>>Obligaci&oacute;n</option>
                                <option value="income" <?php echo $currentLineItemType === 'income' ? 'selected' : ''; ?>>Ingreso</option>
                              </select>
                            </td>
                            <td><?php echo htmlspecialchars($parentLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($addToFatherLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$si['service_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                              <input type="number" step="0.01" name="sale_quantity" form="<?php echo $updateFormId; ?>" value="<?php echo htmlspecialchars((string)$si['quantity'], ENT_QUOTES, 'UTF-8'); ?>" style="width:80px">
                            </td>
                            <td>
                              <input type="number" step="0.01" name="sale_unit_price" form="<?php echo $updateFormId; ?>" value="<?php echo htmlspecialchars((string)((isset($si['unit_price_cents']) ? $si['unit_price_cents'] : 0)/100), ENT_QUOTES, 'UTF-8'); ?>" style="width:100px">
                            </td>
                            <td><?php echo reservations_format_money(isset($si['amount_cents']) ? $si['amount_cents'] : 0, $folioCurrency); ?></td>
                            <td><?php echo reservations_format_money($finalTotalCents, $folioCurrency); ?></td>
                            <td><?php echo htmlspecialchars((string)$si['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="form-inline">
                              <form method="post" class="inline-form" id="<?php echo $updateFormId; ?>" onsubmit="return confirm('Confirmar actualizacion de line item y recalculo de derivados?');">
                                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                <?php reservations_render_filter_hiddens($filters); ?>
                                <input type="hidden" name="reservations_action" value="update_sale_item">
                                <input type="hidden" name="sale_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                <input type="hidden" name="sale_folio_id" value="<?php echo $fid; ?>">
                                <input type="hidden" name="sale_item_id" value="<?php echo (int)$si['id_sale_item']; ?>">
                                <button type="submit" class="button-secondary">Actualizar</button>
                              </form>
                              <form method="post" class="inline-form" onsubmit="return confirm('Confirmar eliminacion de line item? Tambien se eliminaran line items hijos/derivados.');">
                                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                <?php reservations_render_filter_hiddens($filters); ?>
	                                <input type="hidden" name="reservations_action" value="delete_sale_item">
	                                <input type="hidden" name="sale_reservation_id" value="<?php echo (int)$reservationId; ?>">
	                                <input type="hidden" name="sale_item_id" value="<?php echo (int)$si['id_sale_item']; ?>">
	                                <button type="submit" class="button-secondary" <?php echo ($primaryLodgingLineItemId > 0 && (int)$si['id_sale_item'] === $primaryLodgingLineItemId) ? 'disabled title="Usa Cambiar tipo de hospedaje"' : ''; ?>>Eliminar</button>
	                              </form>
                            </td>
                          </tr>
                          <?php
                            $taxRows = isset($taxItemsBySale[(int)$si['id_sale_item']]) ? $taxItemsBySale[(int)$si['id_sale_item']] : array();
                            foreach ($taxRows as $ti):
                              $taxName = isset($ti['tax_name']) ? (string)$ti['tax_name'] : 'Impuesto';
                              $rate = isset($ti['rate_percent']) ? (float)$ti['rate_percent'] : null;
                              $rateLabel = $rate === null ? '' : rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
                              $taxLabel = $taxName . ($rateLabel !== '' ? (' (' . $rateLabel . '%)') : '');
                              $taxAmount = isset($ti['amount_cents']) ? (int)$ti['amount_cents'] : 0;
                          ?>
                          <tr class="tax-item-row">
                            <td class="tax-label"><?php echo htmlspecialchars('Impuesto: ' . $taxLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><?php echo reservations_format_money($taxAmount, $folioCurrency); ?></td>
                            <td></td>
                            <td class="muted"></td>
                            <td></td>
                            <td></td>
                          </tr>
                          <?php endforeach; ?>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <p class="muted">Sin servicios en este folio.</p>
                <?php endif; ?>

                <form method="post" class="form-inline">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <?php reservations_render_filter_hiddens($filters); ?>
                  <input type="hidden" name="reservations_action" value="create_payment">
                  <input type="hidden" name="payment_reservation_id" value="<?php echo (int)$reservationId; ?>">
                  <input type="hidden" name="payment_folio_id" value="<?php echo $fid; ?>">
                  <input type="hidden" name="payment_source_balance_cents" value="<?php echo (int)$sourceFolioBalanceCents; ?>">
                  <input type="hidden" name="payment_other_folio_pending_id" value="<?php echo (int)$otherPendingFolioId; ?>">
                  <input type="hidden" name="payment_other_folio_pending_balance_cents" value="<?php echo (int)$otherPendingBalanceCents; ?>">
                  <input type="hidden" name="payment_transfer_remaining" value="0">
                  <input type="hidden" name="payment_transfer_target_folio_id" value="">
                  <label>Concepto de pago
                    <?php $detailPaymentCatalogs = reservations_payment_catalogs_for_reservation($paymentCatalogsByProperty, $propertyCodeDetail, $companyId, $reservationId); ?>
                    <select name="payment_method">
                      <option value="0">(Selecciona concepto)</option>
                      <?php foreach ($detailPaymentCatalogs as $pc): ?>
                        <?php $pcId = (int)$pc['id_payment_catalog']; ?>
                        <option value="<?php echo $pcId; ?>">
                          <?php echo htmlspecialchars((string)(isset($pc['label']) ? $pc['label'] : $pc['name']), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Monto <input type="number" step="0.01" name="payment_amount" value="0"></label>
                  <label>Referencia <input type="text" name="payment_reference"></label>
                  <label>Fecha pago <input type="date" name="payment_service_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"></label>
                  <button type="submit">Agregar pago</button>
                </form>

                <?php
                  $showRefundedColumnLegacy = false;
                  foreach ($folioPayments as $paymentRowForRefundColumnLegacy) {
                      $paymentRefundedCentsLegacy = isset($paymentRowForRefundColumnLegacy['refunded_total_cents']) ? (int)$paymentRowForRefundColumnLegacy['refunded_total_cents'] : 0;
                      if ($paymentRefundedCentsLegacy !== 0) {
                          $showRefundedColumnLegacy = true;
                          break;
                      }
                  }
                ?>
                <?php if ($folioPayments): ?>
                  <div class="table-scroll">
                    <table>
                      <thead>
                        <tr>
                          <th>Metodo</th>
                          <th>Estado</th>
                          <th>Ref</th>
                          <?php if ($showRefundedColumnLegacy): ?>
                            <th>Reembolsado</th>
                          <?php endif; ?>
                          <th>Monto</th>
                          <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($folioPayments as $p):
                          $pid = isset($p['id_payment']) ? (int)$p['id_payment'] : 0;
                          $refundsForPayment = isset($refundsByPayment[$pid]) ? $refundsByPayment[$pid] : array();
                        ?>
                          <tr>
                            <td><?php echo htmlspecialchars((string)$p['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$p['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)$p['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($showRefundedColumnLegacy): ?>
                              <td><?php echo reservations_format_money(isset($p['refunded_total_cents']) ? $p['refunded_total_cents'] : 0, $folioCurrency); ?></td>
                            <?php endif; ?>
                            <td><?php echo reservations_format_money(isset($p['amount_cents']) ? $p['amount_cents'] : 0, $folioCurrency); ?></td>
                            <td class="form-inline">
                              <form method="post" class="inline-form">
                                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                <?php reservations_render_filter_hiddens($filters); ?>
                                <input type="hidden" name="reservations_action" value="update_payment">
                                <input type="hidden" name="payment_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                <input type="hidden" name="payment_folio_id" value="<?php echo $fid; ?>">
                                <input type="hidden" name="payment_id" value="<?php echo $pid; ?>">
                                <?php
                                  $detailPaymentCatalogs = reservations_payment_catalogs_for_reservation($paymentCatalogsByProperty, $propertyCodeDetail, $companyId, $reservationId);
                                  $paymentCatalogId = isset($p['id_payment_catalog']) ? (int)$p['id_payment_catalog'] : 0;
                                  if ($paymentCatalogId <= 0 && isset($p['method'])) {
                                      foreach ($detailPaymentCatalogs as $pc) {
                                          if (strcasecmp((string)$pc['name'], (string)$p['method']) === 0) {
                                              $paymentCatalogId = (int)$pc['id_payment_catalog'];
                                              break;
                                          }
                                      }
                                  }
                                ?>
                                <select name="payment_method" style="width:220px">
                                  <option value="0">(Selecciona concepto)</option>
                                  <?php foreach ($detailPaymentCatalogs as $pc): ?>
                                    <?php $pcId = (int)$pc['id_payment_catalog']; ?>
                                    <option value="<?php echo $pcId; ?>" <?php echo $paymentCatalogId === $pcId ? 'selected' : ''; ?>>
                                      <?php echo htmlspecialchars((string)(isset($pc['label']) ? $pc['label'] : $pc['name']), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                                <input type="number" step="0.01" name="payment_amount" value="<?php echo htmlspecialchars((string)((isset($p['amount_cents']) ? $p['amount_cents'] : 0)/100), ENT_QUOTES, 'UTF-8'); ?>" style="width:90px">
                                <input type="text" name="payment_reference" value="<?php echo htmlspecialchars((string)$p['reference'], ENT_QUOTES, 'UTF-8'); ?>" style="width:110px">
                                <?php $paymentServiceDate = isset($p['service_date']) && (string)$p['service_date'] !== '' ? (string)$p['service_date'] : date('Y-m-d'); ?>
                                <input type="date" name="payment_service_date" value="<?php echo htmlspecialchars($paymentServiceDate, ENT_QUOTES, 'UTF-8'); ?>" style="width:140px">
                                <button type="submit" class="button-secondary">Actualizar</button>
                              </form>
                              <form method="post" class="inline-form">
                                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                <?php reservations_render_filter_hiddens($filters); ?>
                                <input type="hidden" name="reservations_action" value="delete_payment">
                                <input type="hidden" name="payment_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                <input type="hidden" name="payment_id" value="<?php echo $pid; ?>">
                                <button type="submit" class="button-secondary">Eliminar</button>
                              </form>
                              <form method="post" class="inline-form">
                                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                <?php reservations_render_filter_hiddens($filters); ?>
                                <input type="hidden" name="reservations_action" value="create_refund">
                                <input type="hidden" name="refund_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                <input type="hidden" name="refund_payment_id" value="<?php echo $pid; ?>">
                                <input type="number" step="0.01" name="refund_amount" placeholder="Monto" style="width:90px">
                                <input type="text" name="refund_reference" placeholder="Ref" style="width:90px">
                                <input type="text" name="refund_reason" placeholder="Motivo" style="width:110px">
                                <button type="submit" class="button-secondary">Reembolsar</button>
                              </form>
                            </td>
                          </tr>
                          <?php if ($refundsForPayment): ?>
                            <tr>
                              <td colspan="<?php echo $showRefundedColumnLegacy ? '6' : '5'; ?>">
                                <div class="table-scroll">
                                  <table>
                                    <thead>
                                      <tr>
                                        <th>Reembolso</th>
                                        <th>Monto</th>
                                        <th>Referencia</th>
                                        <th>Motivo</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php foreach ($refundsForPayment as $r): ?>
                                        <tr>
                                          <td>#<?php echo (int)$r['id_refund']; ?></td>
                                          <td><?php echo reservations_format_money(isset($r['amount_cents']) ? $r['amount_cents'] : 0, $folioCurrency); ?></td>
                                          <td><?php echo htmlspecialchars((string)$r['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td><?php echo htmlspecialchars((string)$r['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td><?php echo htmlspecialchars((string)$r['refunded_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td>
                                            <form method="post" class="inline-form">
                                              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                                              <?php reservations_render_filter_hiddens($filters); ?>
                                              <input type="hidden" name="reservations_action" value="delete_refund">
                                              <input type="hidden" name="refund_reservation_id" value="<?php echo (int)$reservationId; ?>">
                                              <input type="hidden" name="refund_id" value="<?php echo (int)$r['id_refund']; ?>">
                                              <input type="hidden" name="refund_payment_id" value="<?php echo $pid; ?>">
                                              <input type="hidden" name="refund_amount" value="<?php echo htmlspecialchars((string)((isset($r['amount_cents']) ? $r['amount_cents'] : 0)/100), ENT_QUOTES, 'UTF-8'); ?>">
                                              <button type="submit" class="button-secondary">Eliminar</button>
                                            </form>
                                          </td>
                                        </tr>
                                      <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                </div>
                              </td>
                            </tr>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <p class="muted">Sin pagos registrados.</p>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
<?php endif; ?>
      </div>

      <form method="post" class="reservation-edit-form">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <?php reservations_render_filter_hiddens($filters); ?>
        <input type="hidden" name="reservations_action" value="update_reservation">
        <input type="hidden" name="reservation_id" value="<?php echo (int)$reservationId; ?>">
        <input type="hidden" name="reservation_guest_id" value="<?php echo isset($detail['id_guest']) ? (int)$detail['id_guest'] : 0; ?>">
        <input type="hidden" name="reservation_guest_names" value="<?php echo htmlspecialchars($guestNamesValue, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="reservation_guest_email" value="<?php echo htmlspecialchars($guestEmailValue, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="reservation_guest_phone" value="<?php echo htmlspecialchars($guestPhoneValue, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="reservation_guest_last_name" value="<?php echo htmlspecialchars($guestLastValue, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="reservation_guest_maiden_name" value="<?php echo htmlspecialchars($guestMaidenValue, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="subtab-info">
          <div class="full">
            <h4>Notas</h4>
            <?php if ($notesError !== ''): ?>
              <p class="error">Error al cargar notas: <?php echo htmlspecialchars($notesError, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php elseif (!empty($notesList)): ?>
              <div class="notes-list">
                <?php foreach ($notesList as $noteRow):
                  $noteId = isset($noteRow['id_reservation_note']) ? (int)$noteRow['id_reservation_note'] : 0;
                  $noteText = isset($noteRow['note_text']) ? (string)$noteRow['note_text'] : '';
                  $noteDate = isset($noteRow['created_at']) ? (string)$noteRow['created_at'] : '';
                ?>
                    <div class="note-item">
                      <div class="note-meta">
                        <span class="note-date"><?php echo htmlspecialchars($noteDate, ENT_QUOTES, 'UTF-8'); ?></span>
                        <form method="post" class="inline-form">
                          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                          <?php reservations_render_filter_hiddens($filters); ?>
                          <input type="hidden" name="reservations_action" value="delete_note">
                          <input type="hidden" name="reservation_id" value="<?php echo (int)$reservationId; ?>">
                          <input type="hidden" name="reservation_note_id" value="<?php echo $noteId; ?>">
                          <button type="submit" class="note-delete-button" title="Eliminar nota" aria-label="Eliminar nota">&times;</button>
                        </form>
                      </div>
                      <div class="note-actions">
                        <input type="hidden" name="reservation_note_ids[]" value="<?php echo $noteId; ?>">
                        <div class="note-text"><?php echo htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8'); ?></div>
                        <textarea class="note-editor" name="reservation_note_texts[<?php echo $noteId; ?>]" rows="2" data-editable-field <?php echo $editDisabledAttr; ?>><?php echo htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <input type="hidden" name="reservation_note_delete[<?php echo $noteId; ?>]" value="">
                      </div>
                    </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="form-grid grid-2 full reservation-note-form">
            <label class="full">
              Nueva nota
              <textarea name="reservation_note_text" rows="3"></textarea>
            </label>
            <div class="full">
              <button type="submit" class="button-secondary" name="reservations_action" value="add_note">Agregar nota</button>
            </div>
          </div>
          <?php if ($isHold): ?>
            <details id="reservation-<?php echo (int)$reservationId; ?>-confirm" class="hold-confirmation" data-confirm-hold data-base-cents="<?php echo (int)$confirmBaseCents; ?>"<?php echo $confirmOpen ? ' open' : ''; ?>>
              <summary><span class="button-secondary">Confirmar reservaci&oacute;n</span></summary>
              <div class="hold-confirmation-body">
                <h4>Confirmar reservaci&oacute;n</h4>
                <p class="muted">Completa los datos necesarios para confirmar.</p>
                <?php if (!$hasGuestAssigned): ?>
                  <div class="form-grid grid-3">
                    <label>
                      Nombre hu&eacute;sped *
                      <input type="text" name="confirm_guest_names" data-guest-scope="confirm-<?php echo (int)$reservationId; ?>" data-guest-field="names" value="<?php echo htmlspecialchars((string)$confirmGuestData['names'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>
                      Correo hu&eacute;sped
                      <input type="email" name="confirm_guest_email" data-guest-scope="confirm-<?php echo (int)$reservationId; ?>" data-guest-field="email" value="<?php echo htmlspecialchars((string)$confirmGuestData['email'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>
                      Tel&eacute;fono
                      <div class="phone-input">
                        <select name="confirm_guest_phone_prefix" data-guest-scope="confirm-<?php echo (int)$reservationId; ?>" data-guest-field="prefix">
                          <?php $confirmPrefixSelected = false; ?>
                          <?php foreach ($phoneCountries as $phoneCountry): ?>
                            <?php
                              $prefix = isset($phoneCountry['dial']) ? (string)$phoneCountry['dial'] : '';
                              if ($prefix === '') {
                                  continue;
                              }
                              $countryName = isset($phoneCountry['name_es']) ? (string)$phoneCountry['name_es'] : strtoupper((string)(isset($phoneCountry['iso2']) ? $phoneCountry['iso2'] : ''));
                              $isSelected = (!$confirmPrefixSelected && $prefix === (string)$confirmGuestData['phone_prefix']);
                              if ($isSelected) {
                                  $confirmPrefixSelected = true;
                              }
                            ?>
                            <option value="<?php echo htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                              <?php echo htmlspecialchars($countryName . ' (' . $prefix . ')', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <input type="tel" name="confirm_guest_phone" data-guest-scope="confirm-<?php echo (int)$reservationId; ?>" data-guest-field="phone" value="<?php echo htmlspecialchars((string)$confirmGuestData['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                      </div>
                    </label>
                    <label>
                      Apellido paterno
                      <input type="text" name="confirm_guest_last_name" data-guest-scope="confirm-<?php echo (int)$reservationId; ?>" data-guest-field="last" value="<?php echo htmlspecialchars((string)$confirmGuestData['last'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>
                      Apellido materno
                      <input type="text" name="confirm_guest_maiden_name" data-guest-scope="confirm-<?php echo (int)$reservationId; ?>" data-guest-field="maiden" value="<?php echo htmlspecialchars((string)$confirmGuestData['maiden'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                  </div>
                  <input type="hidden" name="confirm_guest_id" data-guest-id-field="confirm-<?php echo (int)$reservationId; ?>" value="">
                  <div class="guest-suggestions full" data-guest-suggestions="confirm-<?php echo (int)$reservationId; ?>" style="display:none;"></div>
                <?php endif; ?>
                <div class="form-grid grid-3">
                  <label>
                    Concepto de hospedaje *
                    <select name="confirm_lodging_catalog_id">
                      <option value=""><?php echo $lodgingConfirmOptions ? 'Selecciona un concepto' : 'No hay conceptos configurados'; ?></option>
                      <?php foreach ($lodgingConfirmOptions as $opt): ?>
                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((int)$confirmValues['lodging_catalog_id'] === (int)$opt['id']) ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>
                    Total por noche
                    <input type="number" step="0.01" name="confirm_total_nightly" data-confirm-nightly value="<?php echo htmlspecialchars($confirmValues['nightly_override'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
                  </label>
                  <label>
                    Total a cobrar
                    <input type="number" step="0.01" name="confirm_total_override" data-confirm-total value="<?php echo htmlspecialchars($confirmValues['total_override'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0.00">
                  </label>
                  <div class="total-preview">
                  <span class="muted">Tarifa base</span>
                  <strong data-confirm-base-night><?php echo $confirmBaseCents > 0 ? htmlspecialchars(reservations_format_money($confirmBaseCents, $summaryCurrency), ENT_QUOTES, 'UTF-8') . ' por noche' : '--'; ?></strong>
                  <span class="muted" data-confirm-base-total><?php echo $confirmBaseCents > 0 && $summaryNights > 0 ? htmlspecialchars(reservations_format_money($confirmBaseCents * $summaryNights, $summaryCurrency), ENT_QUOTES, 'UTF-8') . ' total' : '--'; ?></span>
                </div>
              </div>
                <div class="confirm-actions">
                  <button type="submit" name="reservations_action" value="confirm_reservation">Confirmar reserva</button>
                </div>
              </div>
            </details>
          <?php endif; ?>
        </div>

        </form>

      <div class="subtab-info">
        <h4>Actividades</h4>
        <?php if ($activities): ?>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Actividad</th>
                  <th>Programada</th>
                  <th>Estatus</th>
                  <th>Participantes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($activities as $activity): ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string)$activity['activity_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$activity['scheduled_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$activity['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int)$activity['num_adults']; ?> + <?php echo (int)$activity['num_children']; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="muted">Sin actividades relacionadas.</p>
        <?php endif; ?>
      </div>
      </div>
    <?php endif; ?>
    <?php
    $panelContent = ob_get_clean();
    $dynamicTabs[] = array(
        'key' => 'reservation:' . $reservationId,
        'title' => $tabLabel,
        'panel_id' => $panelId,
        'close_form_id' => $closeFormId,
        'content' => $panelContent
    );
}

/* Fallback: si la pestaÃƒÂ±a activa no existe, ir a General o al primer tab disponible */
$validTabs = array('static:general');
foreach ($dynamicTabs as $tab) {
    $validTabs[] = 'dynamic:' . (isset($tab['key']) ? (string)$tab['key'] : '');
}
if (!in_array($activeKey, $validTabs, true)) {
    $subtabState['active'] = 'static:general';
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = 'static:general';
} else {
    $subtabState['active'] = $activeKey;
    $_SESSION['pms_subtabs'][$moduleKey]['active'] = $activeKey;
}

pms_render_subtabs($moduleKey, $subtabState, $staticTabs, $dynamicTabs);

$reservationPaymentCatalogsByReservation = array();
try {
    $blockedByReservation = function_exists('pms_reservation_blocked_payment_catalog_ids_bulk')
        ? pms_reservation_blocked_payment_catalog_ids_bulk($companyId, $openReservationIds)
        : array();
    foreach ($openReservationIds as $reservationIdTmp) {
        $reservationIdTmp = (int)$reservationIdTmp;
        if ($reservationIdTmp <= 0) {
            continue;
        }
        $reservationBundleTmp = isset($reservationDetails[$reservationIdTmp]) && is_array($reservationDetails[$reservationIdTmp])
            ? $reservationDetails[$reservationIdTmp]
            : array();
        $reservationDetailTmp = isset($reservationBundleTmp['detail']) && is_array($reservationBundleTmp['detail'])
            ? $reservationBundleTmp['detail']
            : array();
        $propertyCodeTmp = isset($reservationDetailTmp['property_code'])
            ? strtoupper(trim((string)$reservationDetailTmp['property_code']))
            : '';
        $reservationPaymentCatalogsByReservation[$reservationIdTmp] = reservations_payment_catalogs_for_reservation(
            $paymentCatalogsByProperty,
            $propertyCodeTmp,
            $companyId,
            $reservationIdTmp,
            $blockedByReservation
        );
    }
} catch (Exception $e) {
    $reservationPaymentCatalogsByReservation = array();
}

$serviceCatalogMapJson = json_encode($serviceCatalogsByProperty, JSON_UNESCAPED_UNICODE);
$paymentCatalogMapJson = json_encode($paymentCatalogsByProperty, JSON_UNESCAPED_UNICODE);
$paymentCatalogByReservationJson = json_encode($reservationPaymentCatalogsByReservation, JSON_UNESCAPED_UNICODE);
?>
<script>
window.pmsReservationServiceCatalogMap = <?php echo $serviceCatalogMapJson ? $serviceCatalogMapJson : '{}'; ?>;
window.pmsServiceCatalogMap = window.pmsReservationServiceCatalogMap;
window.pmsReservationPaymentCatalogMap = <?php echo $paymentCatalogMapJson ? $paymentCatalogMapJson : '{}'; ?>;
window.pmsReservationPaymentCatalogMapByReservation = <?php echo $paymentCatalogByReservationJson ? $paymentCatalogByReservationJson : '{}'; ?>;
window.pmsCalendarPaymentCatalogMap = window.pmsReservationPaymentCatalogMap;
window.pmsCalendarPaymentCatalogMapByReservation = window.pmsReservationPaymentCatalogMapByReservation;
document.addEventListener('click', function (ev) {
  var btn = ev.target.closest('[data-folio-target]');
  if (!btn) return;
  var targetId = btn.getAttribute('data-folio-target');
  if (!targetId) return;
  var panel = document.getElementById(targetId);
  if (!panel) return;

  var isVisible = panel.style.display !== 'none' && panel.style.display !== '';
  panel.style.display = isVisible ? 'none' : 'block';
  panel.classList.toggle('is-open', !isVisible);
  if (!isVisible) panel.scrollIntoView({behavior:'smooth', block:'start'});
});

(function () {
  var source = document.querySelector('[data-cargos-balances]');
  var target = document.querySelector('[data-res-header-balances]');
  if (!source || !target) return;
  var total = source.getAttribute('data-balance-total') || '';
  var lodging = source.getAttribute('data-balance-lodging') || '';
  var services = source.getAttribute('data-balance-services') || '';
  var totalNode = target.querySelector('[data-balance-total]');
  var lodgingNode = target.querySelector('[data-balance-lodging]');
  var servicesNode = target.querySelector('[data-balance-services]');
  if (totalNode && total !== '') {
    var totalStrong = totalNode.querySelector('strong');
    if (totalStrong) totalStrong.textContent = total;
  }
  if (lodgingNode && lodging !== '') {
    var lodgingStrong = lodgingNode.querySelector('strong');
    if (lodgingStrong) lodgingStrong.textContent = lodging;
  }
  if (servicesNode && services !== '') {
    var servicesStrong = servicesNode.querySelector('strong');
    if (servicesStrong) servicesStrong.textContent = services;
  }
})();

// Toggle de derivados (impuestos) por concepto dentro de Cargos
document.addEventListener('click', function (ev) {
  var btn = ev.target.closest('[data-tax-toggle]');
  if (!btn) return;
  var group = btn.getAttribute('data-tax-toggle');
  if (!group) return;
  var rows = document.querySelectorAll('tr[data-tax-group="' + group + '"]');
  if (!rows.length) return;
  var shouldShow = btn.getAttribute('data-open') !== '1';

  function resetToggleButton(toggleId) {
    var toggles = document.querySelectorAll('[data-tax-toggle="' + toggleId + '"]');
    toggles.forEach(function (t) {
      t.setAttribute('data-open', '0');
      t.textContent = 'Ver derivados';
    });
  }

  function hideGroup(groupId) {
    var groupRows = document.querySelectorAll('tr[data-tax-group="' + groupId + '"]');
    groupRows.forEach(function (row) {
      row.style.display = 'none';
    });
    var childRows = document.querySelectorAll('tr[data-tax-parent="' + groupId + '"][data-tax-group]');
    var childGroups = {};
    childRows.forEach(function (row) {
      row.style.display = 'none';
      var childGroup = row.getAttribute('data-tax-group');
      if (childGroup && childGroup !== groupId) {
        childGroups[childGroup] = true;
      }
    });
    Object.keys(childGroups).forEach(function (childGroup) {
      resetToggleButton(childGroup);
      hideGroup(childGroup);
    });
  }

  if (shouldShow) {
    rows.forEach(function (row) {
      row.style.display = 'table-row';
    });
    btn.setAttribute('data-open', '1');
    btn.textContent = 'Ocultar derivados';
  } else {
    hideGroup(group);
    btn.setAttribute('data-open', '0');
    btn.textContent = 'Ver derivados';
  }
});

(function () {
  function fieldValueForCompare(field) {
    if (!field) return '';
    if (field.type === 'checkbox' || field.type === 'radio') {
      return field.checked ? '1' : '0';
    }
    return String(field.value || '');
  }

  function collectEditFields(pane) {
    if (!pane) return [];
    return Array.prototype.slice.call(pane.querySelectorAll('.folio-edit-field[form]'));
  }

  function captureInitialFieldState(pane) {
    collectEditFields(pane).forEach(function (field) {
      field.dataset.initialValue = fieldValueForCompare(field);
    });
  }

  function collectChangedUpdateFormIds(pane) {
    var changed = {};
    collectEditFields(pane).forEach(function (field) {
      var formId = field.getAttribute('form') || '';
      if (!formId) return;
      var initial = Object.prototype.hasOwnProperty.call(field.dataset, 'initialValue')
        ? String(field.dataset.initialValue || '')
        : '';
      var current = fieldValueForCompare(field);
      if (current !== initial) {
        changed[formId] = true;
      }
    });
    return Object.keys(changed);
  }

  function appendAssociatedControlsToFormData(pane, formId, formData) {
    if (!pane || !formId || !formData) return;
    var selector = '[form="' + formId.replace(/"/g, '\\"') + '"]';
    var controls = pane.querySelectorAll(selector);
    controls.forEach(function (control) {
      if (!control || !control.name || control.disabled) return;
      var type = String(control.type || '').toLowerCase();
      if (type === 'checkbox' || type === 'radio') {
        if (control.checked) {
          formData.set(control.name, control.value);
        } else if (!formData.has(control.name)) {
          formData.set(control.name, '');
        }
        return;
      }
      formData.set(control.name, control.value);
    });
  }

  async function submitChangedFormsAndReload(pane, formIds) {
    for (var i = 0; i < formIds.length; i += 1) {
      var formId = formIds[i];
      if (!formId) continue;
      var form = document.getElementById(formId);
      if (!form) continue;
      var formData = new FormData(form);
      appendAssociatedControlsToFormData(pane, formId, formData);
      var response = await fetch(window.location.href, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      if (!response.ok) {
        throw new Error('No se pudo guardar uno de los cambios.');
      }
    }
    window.location.reload();
  }

  function setPaneEditState(pane, editing) {
    if (!pane) return;
    var isEditing = !!editing;
    pane.classList.toggle('is-editing', isEditing);
    var fields = pane.querySelectorAll('.folio-edit-field');
    fields.forEach(function (field) {
      field.disabled = !isEditing;
    });
    var toggles = pane.querySelectorAll('[data-folio-edit-toggle]');
    toggles.forEach(function (btn) {
      btn.textContent = isEditing ? 'Listo' : 'Editar';
      btn.setAttribute('aria-pressed', isEditing ? 'true' : 'false');
    });
    if (isEditing) {
      captureInitialFieldState(pane);
    }
  }

  var panes = document.querySelectorAll('[data-folio-edit-pane]');
  panes.forEach(function (pane) {
    setPaneEditState(pane, false);
  });

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-folio-edit-toggle]');
    if (!btn) return;
    var pane = btn.closest('[data-folio-edit-pane]');
    if (!pane) return;
    ev.preventDefault();
    var isEditing = pane.classList.contains('is-editing');
    if (!isEditing) {
      setPaneEditState(pane, true);
      return;
    }
    var changedFormIds = collectChangedUpdateFormIds(pane);
    if (!changedFormIds.length) {
      setPaneEditState(pane, false);
      return;
    }
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    submitChangedFormsAndReload(pane, changedFormIds).catch(function (error) {
      btn.disabled = false;
      alert(error && error.message ? error.message : 'No se pudieron guardar los cambios.');
      setPaneEditState(pane, true);
    });
  });
})();

// Toggle formularios dentro de cada folio (cargos/pagos)
document.addEventListener('click', function (ev) {
  var toggle = ev.target.closest('[data-toggle-target]');
  if (!toggle) return;
  var targetId = toggle.getAttribute('data-toggle-target');
  if (!targetId) return;
  var panel = document.getElementById(targetId);
  if (!panel) return;
  var displayMode = toggle.getAttribute('data-toggle-display') || 'block';
  var show = panel.style.display === 'none' || panel.style.display === '';
  panel.style.display = show ? displayMode : 'none';
  toggle.classList.toggle('is-open', show);
  if (show) {
    var focusable = panel.querySelector('input, select, textarea');
    if (focusable) focusable.focus();
  }
});

function pmsReservationFormatMoneyFromCents(cents) {
  var value = parseInt(cents || 0, 10) || 0;
  if (value < 0) value = 0;
  return '$' + (value / 100).toFixed(2);
}

document.addEventListener('submit', function (ev) {
  var form = ev.target;
  if (!form || form.tagName !== 'FORM') return;
  var actionInput = form.querySelector('input[name="reservations_action"]');
  if (!actionInput || actionInput.value !== 'create_payment') return;
  var fromLightboxInput = form.querySelector('input[name="payment_from_lightbox"]');
  if (fromLightboxInput && String(fromLightboxInput.value || '') === '1') return;

  var transferFlagInput = form.querySelector('input[name="payment_transfer_remaining"]');
  var transferTargetInput = form.querySelector('input[name="payment_transfer_target_folio_id"]');
  if (transferFlagInput) transferFlagInput.value = '0';
  if (transferTargetInput) transferTargetInput.value = '';

  var amountInput = form.querySelector('input[name="payment_amount"]');
  if (!amountInput) return;
  var amountRaw = amountInput.value ? amountInput.value.trim() : '';
  var amountValue = parseFloat((amountRaw || '0').replace(',', '.'));
  if (!Number.isFinite(amountValue) || amountValue <= 0) return;
  var amountCents = Math.max(0, Math.round(amountValue * 100));

  var sourceBalanceInput = form.querySelector('input[name="payment_source_balance_cents"]');
  var sourceBalanceCents = sourceBalanceInput ? (parseInt(sourceBalanceInput.value || '0', 10) || 0) : 0;
  if (sourceBalanceCents < 0) sourceBalanceCents = 0;
  if (amountCents <= sourceBalanceCents) return;

  var otherFolioIdInput = form.querySelector('input[name="payment_other_folio_pending_id"]');
  var otherFolioBalanceInput = form.querySelector('input[name="payment_other_folio_pending_balance_cents"]');
  var otherFolioId = otherFolioIdInput ? (parseInt(otherFolioIdInput.value || '0', 10) || 0) : 0;
  var otherFolioBalanceCents = otherFolioBalanceInput ? (parseInt(otherFolioBalanceInput.value || '0', 10) || 0) : 0;
  if (otherFolioBalanceCents < 0) otherFolioBalanceCents = 0;
  if (otherFolioId <= 0 || otherFolioBalanceCents <= 0) return;

  var remainingCents = amountCents - sourceBalanceCents;
  if (remainingCents <= 0) return;

  var question = 'Este pago excede el balance del folio por ' + pmsReservationFormatMoneyFromCents(remainingCents)
    + '.\n\nDeseas transferir ese restante al otro folio pendiente?';
  if (window.confirm(question)) {
    if (transferFlagInput) transferFlagInput.value = '1';
    if (transferTargetInput) transferTargetInput.value = String(otherFolioId);
  }
});

function pmsReservationBuildHiddenInput(form, name, value) {
  if (!form || !name) return;
  var input = document.createElement('input');
  input.type = 'hidden';
  input.name = name;
  input.value = value;
  form.appendChild(input);
}

function pmsReservationCurrentFilterValue(name) {
  var field = document.querySelector('input[name="' + name + '"], select[name="' + name + '"]');
  if (!field) return '';
  return field.value || '';
}

function pmsReservationOpenServiceLightbox(context) {
  if (typeof window.pmsOpenServiceLightbox !== 'function') {
    alert('No se pudo abrir el formulario de servicio.');
    return;
  }

  window.pmsOpenServiceLightbox(context, function (payload) {
    var postForm = document.createElement('form');
    postForm.method = 'post';
    postForm.action = 'index.php?view=reservations';
    pmsReservationBuildHiddenInput(postForm, 'reservations_action', 'create_sale_item');
    pmsReservationBuildHiddenInput(postForm, 'sale_reservation_id', String(context.reservationId || 0));
    pmsReservationBuildHiddenInput(postForm, 'sale_folio_id', String(context.folioId || 0));
    pmsReservationBuildHiddenInput(postForm, 'sale_catalog_id', String(payload.serviceCatalogId || 0));
    pmsReservationBuildHiddenInput(postForm, 'sale_service_date', payload.serviceDate || '');
    pmsReservationBuildHiddenInput(postForm, 'sale_quantity', payload.serviceQuantityRaw || '1');
    pmsReservationBuildHiddenInput(postForm, 'sale_unit_price', payload.serviceUnitPriceRaw || '0.00');
    pmsReservationBuildHiddenInput(postForm, 'sale_item_description', payload.serviceDescription || '');
    pmsReservationBuildHiddenInput(postForm, 'service_mark_paid', payload.serviceMarkPaid || '0');
    pmsReservationBuildHiddenInput(postForm, 'service_payment_method', payload.servicePaymentMethodId || '0');
    pmsReservationBuildHiddenInput(postForm, 'sale_status', 'posted');
    pmsReservationBuildHiddenInput(postForm, 'reservations_subtab_action', 'open');
    pmsReservationBuildHiddenInput(postForm, 'reservations_subtab_target', 'dynamic:reservation:' + String(context.reservationId || 0));

    ['reservations_filter_property', 'reservations_filter_from', 'reservations_filter_to', 'reservations_filter_status',
      'reservations_filter_return_view', 'reservations_filter_return_property_code', 'reservations_filter_return_start_date',
      'reservations_filter_return_view_mode', 'reservations_filter_return_order_mode'
    ].forEach(function (fieldName) {
      var value = pmsReservationCurrentFilterValue(fieldName);
      if (value !== '') {
        pmsReservationBuildHiddenInput(postForm, fieldName, value);
      }
    });

    document.body.appendChild(postForm);
    postForm.submit();
  });
}

function pmsReservationOpenPaymentLightbox(context) {
  if (typeof window.pmsOpenPaymentLightbox !== 'function') {
    alert('No se pudo abrir el formulario de pago.');
    return;
  }

  window.pmsOpenPaymentLightbox(context, function (payload) {
    var postForm = document.createElement('form');
    postForm.method = 'post';
    postForm.action = 'index.php?view=reservations';
    pmsReservationBuildHiddenInput(postForm, 'reservations_action', 'create_payment');
    pmsReservationBuildHiddenInput(postForm, 'payment_from_lightbox', '1');
    pmsReservationBuildHiddenInput(postForm, 'payment_reservation_id', String(context.reservationId || 0));
    pmsReservationBuildHiddenInput(postForm, 'payment_folio_id', String(payload.paymentFolioId || 0));
    pmsReservationBuildHiddenInput(postForm, 'payment_transfer_remaining', payload.paymentTransferRemaining || '0');
    pmsReservationBuildHiddenInput(postForm, 'payment_transfer_target_folio_id', payload.paymentTransferTargetFolioId || '0');
    pmsReservationBuildHiddenInput(postForm, 'payment_method', String(payload.paymentMethodId || 0));
    pmsReservationBuildHiddenInput(postForm, 'payment_amount', payload.paymentAmountRaw || '0.00');
    pmsReservationBuildHiddenInput(postForm, 'payment_reference', payload.paymentReference || '');
    pmsReservationBuildHiddenInput(postForm, 'payment_service_date', payload.paymentServiceDate || '');
    pmsReservationBuildHiddenInput(postForm, 'reservations_subtab_action', 'open');
    pmsReservationBuildHiddenInput(postForm, 'reservations_subtab_target', 'dynamic:reservation:' + String(context.reservationId || 0));

    ['reservations_filter_property', 'reservations_filter_from', 'reservations_filter_to', 'reservations_filter_status',
      'reservations_filter_return_view', 'reservations_filter_return_property_code', 'reservations_filter_return_start_date',
      'reservations_filter_return_view_mode', 'reservations_filter_return_order_mode'
    ].forEach(function (fieldName) {
      var value = pmsReservationCurrentFilterValue(fieldName);
      if (value !== '') {
        pmsReservationBuildHiddenInput(postForm, fieldName, value);
      }
    });

    document.body.appendChild(postForm);
    postForm.submit();
  });
}

document.addEventListener('click', function (ev) {
  var serviceBtn = ev.target.closest('.js-open-service-lightbox');
  if (!serviceBtn) return;
  ev.preventDefault();
  var reservationId = parseInt(serviceBtn.getAttribute('data-service-reservation-id') || '0', 10) || 0;
  var folioId = parseInt(serviceBtn.getAttribute('data-service-folio-id') || '0', 10) || 0;
  if (reservationId <= 0 || folioId <= 0) return;
  pmsReservationOpenServiceLightbox({
    reservationId: reservationId,
    folioId: folioId,
    propertyCode: serviceBtn.getAttribute('data-service-property-code') || '',
    reservationCode: serviceBtn.getAttribute('data-service-reservation-code') || '',
    guestName: serviceBtn.getAttribute('data-service-guest-name') || '',
    currency: serviceBtn.getAttribute('data-service-currency') || 'MXN',
    enableMarkPaid: true,
    serviceCatalogMap: (window.pmsReservationServiceCatalogMap && typeof window.pmsReservationServiceCatalogMap === 'object')
      ? window.pmsReservationServiceCatalogMap
      : {}
  });
});

document.addEventListener('click', function (ev) {
  var paymentBtn = ev.target.closest('.js-open-payment-lightbox');
  if (!paymentBtn) return;
  ev.preventDefault();
  var reservationId = parseInt(paymentBtn.getAttribute('data-payment-reservation-id') || '0', 10) || 0;
  var preferredFolioId = parseInt(paymentBtn.getAttribute('data-payment-folio-id') || '0', 10) || 0;
  if (reservationId <= 0) return;
  var paymentFolios = [];
  var paymentFoliosRaw = paymentBtn.getAttribute('data-payment-folios') || '';
  if (paymentFoliosRaw) {
    try {
      var parsed = JSON.parse(paymentFoliosRaw);
      if (Array.isArray(parsed)) {
        paymentFolios = parsed;
      }
    } catch (error) {
      paymentFolios = [];
    }
  }
  pmsReservationOpenPaymentLightbox({
    reservationId: reservationId,
    preferredFolioId: preferredFolioId,
    propertyCode: paymentBtn.getAttribute('data-payment-property-code') || '',
    reservationCode: paymentBtn.getAttribute('data-payment-reservation-code') || '',
    guestName: paymentBtn.getAttribute('data-payment-guest-name') || '',
    currency: paymentBtn.getAttribute('data-payment-currency') || 'MXN',
    balanceCents: parseInt(paymentBtn.getAttribute('data-payment-balance-cents') || '0', 10) || 0,
    paymentFolios: paymentFolios
  });
});

(function () {
  function setupGuestSuggestions(scope, suggestions) {
    var nameInput = document.querySelector('input[data-guest-scope="' + scope + '"][data-guest-field="names"]');
    var emailInput = document.querySelector('input[data-guest-scope="' + scope + '"][data-guest-field="email"]');
    var phoneInput = document.querySelector('input[data-guest-scope="' + scope + '"][data-guest-field="phone"]');
    var lastInput = document.querySelector('input[data-guest-scope="' + scope + '"][data-guest-field="last"]');
    var maidenInput = document.querySelector('input[data-guest-scope="' + scope + '"][data-guest-field="maiden"]');
    var prefixSelect = document.querySelector('select[data-guest-scope="' + scope + '"][data-guest-field="prefix"]');
    var idInput = document.querySelector('input[data-guest-id-field="' + scope + '"]');
    if (!nameInput || !emailInput || !phoneInput || !suggestions) return;

    var lastQuery = '';
    var debounceTimer = null;
    var activeRequest = 0;

    function clearSuggestions() {
      suggestions.innerHTML = '';
      suggestions.style.display = 'none';
    }

    function setPhone(value) {
      var raw = (value || '').trim();
      if (!raw) {
        phoneInput.value = '';
        return;
      }
      var match = raw.match(/^(\+\d{1,4})\s*(.*)$/);
      if (match && prefixSelect) {
        var prefix = match[1];
        var rest = match[2] || '';
        var hasOption = false;
        Array.prototype.slice.call(prefixSelect.options).forEach(function (opt) {
          if (opt.value === prefix) hasOption = true;
        });
        if (hasOption) {
          prefixSelect.value = prefix;
        }
        phoneInput.value = rest.trim();
      } else {
        phoneInput.value = raw;
      }
    }

    function renderSuggestions(list) {
      if (!list.length) {
        clearSuggestions();
        return;
      }
      suggestions.innerHTML = '';
      list.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'guest-suggestion';
        var info = document.createElement('div');
        var name = document.createElement('strong');
        name.textContent = (item.names || '') + (item.last_name ? (' ' + item.last_name) : '');
        var email = document.createElement('div');
        email.className = 'muted';
        email.textContent = item.email || '';
        info.appendChild(name);
        info.appendChild(email);
        var phone = document.createElement('small');
        phone.textContent = item.phone || '';
        row.appendChild(info);
        row.appendChild(phone);
        row.addEventListener('click', function () {
          nameInput.value = item.names || '';
          emailInput.value = item.email || '';
          setPhone(item.phone || '');
          if (lastInput) lastInput.value = item.last_name || '';
          if (maidenInput) maidenInput.value = item.maiden_name || '';
          if (idInput) idInput.value = item.id_guest || '';
          clearSuggestions();
        });
        suggestions.appendChild(row);
      });
      suggestions.style.display = 'block';
    }

    function fetchGuests(query) {
      if (query.length < 2) {
        clearSuggestions();
        return;
      }
      lastQuery = query;
      activeRequest += 1;
      var requestId = activeRequest;
      var guestSearchUrl = (window.pmsBuildUrl ? window.pmsBuildUrl('api/guest_search.php') : 'api/guest_search.php')
        + '?q=' + encodeURIComponent(query);
      fetch(guestSearchUrl)
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (payload) {
          if (!payload || requestId !== activeRequest) return;
          renderSuggestions(Array.isArray(payload.results) ? payload.results : []);
        })
        .catch(function () {
          if (requestId === activeRequest) clearSuggestions();
        });
    }

    function onSearchInput(ev) {
      if (idInput) idInput.value = '';
      var query = (ev.target.value || '').trim();
      if (query === lastQuery) return;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        fetchGuests(query);
      }, 250);
    }

    [nameInput, emailInput, phoneInput, lastInput, maidenInput].forEach(function (field) {
      if (!field) return;
      field.addEventListener('input', onSearchInput);
    });

    document.addEventListener('click', function (ev) {
      if (suggestions.contains(ev.target)) return;
      if (ev.target === nameInput || ev.target === emailInput || ev.target === phoneInput || ev.target === lastInput || ev.target === maidenInput || ev.target === prefixSelect) return;
      clearSuggestions();
    });
  }

  document.querySelectorAll('[data-guest-suggestions]').forEach(function (suggestions) {
    var scope = suggestions.getAttribute('data-guest-suggestions');
    if (scope) {
      setupGuestSuggestions(scope, suggestions);
    }
  });
})();


(function () {
  var roomSelect = document.getElementById('create-room-code');
  var checkIn = document.getElementById('create-check-in');
  var checkOut = document.getElementById('create-check-out');
  var section = document.querySelector('.reservation-create-form .payment-section');
  var baseNightLabel = document.getElementById('estimated-nightly');
  var baseTotalLabel = document.getElementById('estimated-total');
  var totalInput = document.getElementById('create-total-override');
  var nightlyInput = document.getElementById('create-total-nightly');
  var lodgingSelect = document.getElementById('create-lodging-catalog');
  var fixedRow = document.getElementById('create-fixed-child-row');
  var fixedLabel = document.getElementById('create-fixed-child-label');
  var fixedInput = document.getElementById('create-fixed-child-amount');
  var fixedTotalInput = document.getElementById('create-fixed-child-total');
  var fixedIdInput = document.getElementById('create-fixed-child-catalog-id');
  var lodgingNightsLabel = document.getElementById('lodging-nights-label');
  var fixedNightsLabel = document.getElementById('fixed-child-nights-label');
  var finalNightly = document.getElementById('create-final-nightly');
  var finalTotal = document.getElementById('create-final-total');
  var finalTotalTax = document.getElementById('create-final-total-tax');
  var lastFixedChildId = fixedIdInput ? parseInt(fixedIdInput.value || '0', 10) : 0;
  var currentChildDefaultCents = 0;
  if (!roomSelect || !checkIn || !checkOut || !section || !baseTotalLabel || !totalInput || !nightlyInput) return;

  var taxRuleRates = <?php echo json_encode($taxRuleRates, JSON_NUMERIC_CHECK); ?> || {};
  var ignoreSync = false;
  var lastLodgingSource = nightlyInput.value.trim() !== '' ? 'nightly' : (totalInput.value.trim() !== '' ? 'total' : '');
  var manualLodgingOverride = false;
  var manualBreakdownOverride = false;
  var lastChildSource = fixedInput && fixedInput.value.trim() !== '' ? 'nightly' : (fixedTotalInput && fixedTotalInput.value.trim() !== '' ? 'total' : '');

  function parseDate(value) {
    if (!value) return null;
    var parts = value.split('-');
    if (parts.length !== 3) return null;
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10) - 1;
    var day = parseInt(parts[2], 10);
    var date = new Date(year, month, day);
    return isNaN(date.getTime()) ? null : date;
  }

  function parseNumber(raw) {
    if (raw === null || raw === undefined) return null;
    var value = parseFloat(String(raw).replace(',', '.'));
    return isNaN(value) ? null : value;
  }

  function formatMoney(cents) {
    var amount = (cents / 100).toFixed(2);
    return '$' + amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' MXN';
  }

  function computeNights() {
    var dateIn = parseDate(checkIn.value);
    var dateOut = parseDate(checkOut.value);
    if (!dateIn || !dateOut) return 0;
    var diff = Math.round((dateOut - dateIn) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
  }

  function setFieldValue(field, value) {
    if (!field) return;
    ignoreSync = true;
    field.value = value;
    ignoreSync = false;
  }

  function setNightsLabel(label, nights) {
    if (!label) return;
    label.textContent = nights > 0 ? nights + ' noches' : '-- noches';
  }

  function getBaseCents() {
    var option = roomSelect.options[roomSelect.selectedIndex];
    return option ? parseInt(option.getAttribute('data-base') || '0', 10) : 0;
  }

  function getRatePreview() {
    var option = roomSelect.options[roomSelect.selectedIndex];
    if (!option) {
      return { nightly: 0, total: 0 };
    }
    var nightly = parseInt(option.getAttribute('data-rate-nightly') || option.getAttribute('data-base') || '0', 10);
    var total = parseInt(option.getAttribute('data-rate-total') || '0', 10);
    return {
      nightly: isNaN(nightly) ? 0 : nightly,
      total: isNaN(total) ? 0 : total
    };
  }

  function getBreakdownRows() {
    return Array.prototype.slice.call(section.querySelectorAll('.nightly-row'));
  }

  function sumBreakdown() {
    var rows = getBreakdownRows();
    if (!rows.length) return null;
    var sum = 0;
    var count = 0;
    rows.forEach(function (row) {
      var input = row.querySelector('.nightly-amount-input');
      var raw = input ? input.value : (row.getAttribute('data-night-amount') || row.textContent || '');
      var val = parseNumber(raw);
      if (val === null) return;
      sum += val;
      count += 1;
    });
    return count > 0 ? { sum: sum, count: count } : null;
  }

  function applyBreakdownCents(centsByRow) {
    var rows = getBreakdownRows();
    if (!rows.length || !centsByRow || !centsByRow.length) return false;
    rows.forEach(function (row, index) {
      var cents = index < centsByRow.length ? centsByRow[index] : 0;
      if (!isFinite(cents) || cents < 0) {
        cents = 0;
      }
      var amount = (Math.round(cents) / 100).toFixed(2);
      var input = row.querySelector('.nightly-amount-input');
      if (input) {
        setFieldValue(input, amount);
      } else {
        row.setAttribute('data-night-amount', amount);
        var amountEl = row.querySelector('.nightly-amount');
        if (amountEl) {
          amountEl.textContent = amount;
        }
      }
    });
    return true;
  }

  function applyBreakdownForTotal(totalVal) {
    var rows = getBreakdownRows();
    var count = rows.length;
    if (!count) return false;
    var totalCents = Math.round(totalVal * 100);
    if (!isFinite(totalCents) || totalCents <= 0) return false;
    var baseCents = Math.floor(totalCents / count);
    var remainder = totalCents - (baseCents * count);
    var centsByRow = [];
    for (var i = 0; i < count; i++) {
      centsByRow.push(baseCents + (i < remainder ? 1 : 0));
    }
    return applyBreakdownCents(centsByRow);
  }

  function applyBreakdownForNightly(nightlyVal, nights) {
    var rows = getBreakdownRows();
    var count = rows.length;
    if (!count || !nights) return false;
    var nightlyCents = Math.round(nightlyVal * 100);
    if (!isFinite(nightlyCents) || nightlyCents <= 0) return false;
    var targetTotalCents = nightlyCents * count;
    var existingTotalVal = parseNumber(totalInput.value);
    if (existingTotalVal !== null && existingTotalVal > 0) {
      var existingTotalCents = Math.round(existingTotalVal * 100);
      // Conserva total capturado cuando el ajuste es marginal por redondeo.
      if (Math.abs(existingTotalCents - targetTotalCents) <= 10) {
        targetTotalCents = existingTotalCents;
      }
    }
    var centsByRow = [];
    for (var i = 0; i < count; i++) {
      centsByRow.push(nightlyCents);
    }
    var diff = targetTotalCents - (nightlyCents * count);
    var idx = 0;
    var guard = 0;
    var maxSteps = Math.abs(diff) + (count * 2);
    while (diff !== 0 && guard < maxSteps) {
      if (diff > 0) {
        centsByRow[idx] += 1;
        diff -= 1;
      } else if (centsByRow[idx] > 0) {
        centsByRow[idx] -= 1;
        diff += 1;
      }
      idx = (idx + 1) % count;
      guard += 1;
    }
    return applyBreakdownCents(centsByRow);
  }

  function syncTotalsFromBreakdown(nights) {
    var breakdown = sumBreakdown();
    if (!breakdown || !nights) return false;
    var sumCents = Math.round(breakdown.sum * 100);
    setFieldValue(totalInput, (sumCents / 100).toFixed(2));
    setFieldValue(nightlyInput, (sumCents / 100 / nights).toFixed(2));
    lastLodgingSource = 'breakdown';
    return true;
  }

  function bindBreakdownInputs() {
    var rows = getBreakdownRows();
    rows.forEach(function (row) {
      var input = row.querySelector('.nightly-amount-input');
      if (!input) return;
      if (input.dataset.bound === '1') return;
      input.dataset.bound = '1';
      input.addEventListener('input', function () {
        if (ignoreSync) return;
        manualLodgingOverride = true;
        manualBreakdownOverride = true;
        var nights = computeNights();
        syncTotalsFromBreakdown(nights);
        updateFinalTotals(nights);
      });
    });
  }

  function updateBasePreview(nights) {
      var breakdown = sumBreakdown();
      var baseCents = 0;
      var totalCents = 0;
      if (breakdown && nights > 0) {
        totalCents = Math.round(breakdown.sum * 100);
        baseCents = Math.round(totalCents / nights);
      } else {
        baseCents = 0;
        totalCents = 0;
      }
      if (!baseNightLabel || !baseTotalLabel) return;
      if (nights <= 0 || baseCents <= 0) {
        baseNightLabel.textContent = '--';
        baseTotalLabel.textContent = '--';
        return;
    }
    baseNightLabel.textContent = formatMoney(baseCents) + ' por noche';
    baseTotalLabel.textContent = formatMoney(totalCents) + ' total';
  }

  function parseTaxIds(raw) {
    if (!raw) return [];
    return String(raw)
      .split(',')
      .map(function (item) { return parseInt(item.trim(), 10); })
      .filter(function (id) { return id > 0; });
  }

  function calcTaxCents(amountCents, taxIds) {
    if (!amountCents || !taxIds.length) return 0;
    var total = 0;
    taxIds.forEach(function (id) {
      var rate = taxRuleRates[id];
      if (rate === undefined || rate === null) return;
      var pct = parseFloat(rate);
      if (!pct) return;
      total += Math.round(amountCents * (pct / 100));
    });
    return total;
  }

  function getTaxIdsFromSelect(attr) {
    if (!lodgingSelect) return [];
    var opt = lodgingSelect.options[lodgingSelect.selectedIndex];
    if (!opt) return [];
    return parseTaxIds(opt.getAttribute(attr) || '');
  }

  function syncLodging(source, nights) {
    if (!nights || ignoreSync) return;
    if (source === 'nightly') {
      var nightlyVal = parseNumber(nightlyInput.value);
      if (!nightlyVal || nightlyVal <= 0) {
        setFieldValue(totalInput, '');
        return;
      }
      setFieldValue(totalInput, (nightlyVal * nights).toFixed(2));
    } else {
      var totalVal = parseNumber(totalInput.value);
      if (!totalVal || totalVal <= 0) {
        setFieldValue(nightlyInput, '');
        return;
      }
      setFieldValue(nightlyInput, (totalVal / nights).toFixed(2));
    }
  }

  function syncChild(source, nights) {
    if (!fixedInput || !fixedTotalInput || !nights || ignoreSync) return;
    if (source === 'nightly') {
      var nightlyVal = parseNumber(fixedInput.value);
      if (!nightlyVal || nightlyVal <= 0) {
        setFieldValue(fixedTotalInput, '');
        return;
      }
      setFieldValue(fixedTotalInput, (nightlyVal * nights).toFixed(2));
    } else {
      var totalVal = parseNumber(fixedTotalInput.value);
      if (!totalVal || totalVal <= 0) {
        setFieldValue(fixedInput, '');
        return;
      }
      setFieldValue(fixedInput, (totalVal / nights).toFixed(2));
    }
  }

  function updateFixedChild() {
    if (!lodgingSelect || !fixedRow || !fixedLabel || !fixedInput || !fixedIdInput || !fixedTotalInput) {
      return;
    }
    var opt = lodgingSelect.options[lodgingSelect.selectedIndex];
    var childId = opt ? parseInt(opt.getAttribute('data-child-id') || '0', 10) : 0;
    var nights = computeNights();
    if (childId > 0) {
      var childName = (opt.getAttribute('data-child-name') || '').trim();
      currentChildDefaultCents = parseInt(opt.getAttribute('data-child-default') || '0', 10);
      fixedRow.style.display = '';
      fixedRow.setAttribute('data-tax-ids', opt.getAttribute('data-child-tax-ids') || '');
      fixedLabel.textContent = childName ? ('Totales ' + childName) : 'Totales concepto hijo';
      fixedIdInput.value = String(childId);
      if (fixedInput.value.trim() === '' || childId !== lastFixedChildId) {
        setFieldValue(fixedInput, (currentChildDefaultCents / 100).toFixed(2));
        lastChildSource = 'nightly';
      }
      if (fixedTotalInput.value.trim() === '' || childId !== lastFixedChildId) {
        if (nights > 0) {
          setFieldValue(fixedTotalInput, ((currentChildDefaultCents / 100) * nights).toFixed(2));
        } else {
          setFieldValue(fixedTotalInput, '');
        }
      }
      lastFixedChildId = childId;
    } else {
      fixedRow.style.display = 'none';
      fixedRow.removeAttribute('data-tax-ids');
      fixedLabel.textContent = 'Totales concepto hijo';
      fixedInput.value = '';
      fixedTotalInput.value = '';
      fixedIdInput.value = '0';
      lastFixedChildId = 0;
      lastChildSource = '';
      currentChildDefaultCents = 0;
    }
  }

    function updateFinalTotals(nights) {
      if (!finalNightly || !finalTotal || !finalTotalTax) return;
      var breakdown = sumBreakdown();
      if (!nights || !breakdown) {
        setFieldValue(finalNightly, '');
        setFieldValue(finalTotal, '');
        setFieldValue(finalTotalTax, '');
        return;
      }
      var sumCents = Math.round(breakdown.sum * 100);
      var avgCents = Math.round(sumCents / nights);
      setFieldValue(finalNightly, (avgCents / 100).toFixed(2));
      setFieldValue(finalTotal, (sumCents / 100).toFixed(2));
      // Impuestos fuera: mostramos igual sin tax mientras no haya reglas aquÃƒÂ­.
      setFieldValue(finalTotalTax, (sumCents / 100).toFixed(2));
    }

    function updatePayment() {
      var nights = computeNights();
      setNightsLabel(lodgingNightsLabel, nights);
      setNightsLabel(fixedNightsLabel, nights);
      bindBreakdownInputs();
      if (nights <= 0) {
        setFieldValue(totalInput, '');
        setFieldValue(nightlyInput, '');
        updateFinalTotals(0);
        updateBasePreview(0);
        return;
      }

      var usedBreakdown = syncTotalsFromBreakdown(nights);
      if (usedBreakdown) {
        lastLodgingSource = 'breakdown';
        updateFinalTotals(nights);
        updateBasePreview(nights);
      } else {
        setFieldValue(totalInput, '');
        setFieldValue(nightlyInput, '');
        updateFinalTotals(0);
        updateBasePreview(0);
      }

      if (fixedRow && fixedRow.style.display !== 'none') {
        if (fixedInput.value.trim() === '' && fixedTotalInput.value.trim() === '' && currentChildDefaultCents > 0) {
          setFieldValue(fixedInput, (currentChildDefaultCents / 100).toFixed(2));
          setFieldValue(fixedTotalInput, ((currentChildDefaultCents / 100) * nights).toFixed(2));
          lastChildSource = 'nightly';
        }
        if (lastChildSource === 'total') {
          syncChild('total', nights);
        } else if (lastChildSource === 'nightly') {
          syncChild('nightly', nights);
        }
      }
    }

    totalInput.addEventListener('input', function () {
      if (ignoreSync) return;
      manualLodgingOverride = true;
      manualBreakdownOverride = true;
      lastLodgingSource = totalInput.value.trim() !== '' ? 'total' : (nightlyInput.value.trim() !== '' ? 'nightly' : '');
      var nights = computeNights();
      var totalVal = parseNumber(totalInput.value);
      if (nights > 0 && totalVal && totalVal > 0 && applyBreakdownForTotal(totalVal)) {
        syncTotalsFromBreakdown(nights);
      }
      updateFinalTotals(nights);
    });
    nightlyInput.addEventListener('input', function () {
      if (ignoreSync) return;
      manualLodgingOverride = true;
      manualBreakdownOverride = true;
      lastLodgingSource = nightlyInput.value.trim() !== '' ? 'nightly' : (totalInput.value.trim() !== '' ? 'total' : '');
      var nights = computeNights();
      var nightlyVal = parseNumber(nightlyInput.value);
      if (nights > 0 && nightlyVal && nightlyVal > 0 && applyBreakdownForNightly(nightlyVal, nights)) {
        syncTotalsFromBreakdown(nights);
      }
      updateFinalTotals(nights);
    });
  if (fixedInput) {
    fixedInput.addEventListener('input', function () {
      if (ignoreSync) return;
      lastChildSource = fixedInput.value.trim() !== '' ? 'nightly' : (fixedTotalInput && fixedTotalInput.value.trim() !== '' ? 'total' : '');
      syncChild('nightly', computeNights());
      updateFinalTotals(computeNights());
    });
  }
  if (fixedTotalInput) {
    fixedTotalInput.addEventListener('input', function () {
      if (ignoreSync) return;
      lastChildSource = fixedTotalInput.value.trim() !== '' ? 'total' : (fixedInput && fixedInput.value.trim() !== '' ? 'nightly' : '');
      syncChild('total', computeNights());
      updateFinalTotals(computeNights());
    });
  }

    roomSelect.addEventListener('change', function () {
      manualLodgingOverride = false;
      manualBreakdownOverride = false;
      lastLodgingSource = '';
      updatePayment();
    });
    checkIn.addEventListener('change', function () {
      manualLodgingOverride = false;
      manualBreakdownOverride = false;
      lastLodgingSource = '';
      updatePayment();
    });
    checkOut.addEventListener('change', function () {
      manualLodgingOverride = false;
      manualBreakdownOverride = false;
      lastLodgingSource = '';
      updatePayment();
    });
  if (lodgingSelect) {
    lodgingSelect.addEventListener('change', function () {
      updateFixedChild();
      updatePayment();
    });
  }

  updateFixedChild();
  updatePayment();
})();

(function () {
  function setupRepeater(config) {
    var toggleBtn = document.getElementById(config.toggleId);
    var addBtn = document.getElementById(config.addId);
    var list = document.getElementById(config.listId);
    var template = document.getElementById(config.templateId);
    if (!toggleBtn || !addBtn || !list || !template) {
      return;
    }

    function addRow() {
      var fragment = template.content ? template.content.cloneNode(true) : null;
      if (!fragment) {
        return;
      }
      list.appendChild(fragment);
      if (config.selectFirstNonZero) {
        var lastRow = list.querySelector(config.rowSelector + ':last-child');
        if (lastRow) {
          var selects = lastRow.querySelectorAll('select');
          selects.forEach(function (selectEl) {
            if (!selectEl || selectEl.value !== '0') return;
            var options = Array.prototype.slice.call(selectEl.options || []);
            var firstValid = options.find(function (opt) {
              return opt && opt.value && opt.value !== '0';
            });
            if (firstValid) {
              selectEl.value = firstValid.value;
            }
          });
        }
      }
    }

    function updateVisibility() {
      var hasRows = list.children.length > 0;
      list.style.display = hasRows ? 'grid' : 'none';
      addBtn.style.display = hasRows ? 'inline-flex' : 'none';
      toggleBtn.style.display = hasRows ? 'none' : 'inline-flex';
    }

    toggleBtn.addEventListener('click', function () {
      addRow();
      updateVisibility();
    });

    addBtn.addEventListener('click', function () {
      addRow();
      updateVisibility();
    });

    list.addEventListener('click', function (ev) {
      var removeBtn = ev.target.closest(config.removeSelector);
      if (!removeBtn) {
        return;
      }
      var row = removeBtn.closest(config.rowSelector);
      if (row) {
        row.remove();
        updateVisibility();
      }
    });

    updateVisibility();
  }

  setupRepeater({
    toggleId: 'create-lodging-toggle',
    addId: 'create-lodging-add',
    listId: 'create-lodging-list',
    templateId: 'create-lodging-template',
    removeSelector: '.js-remove-lodging',
    rowSelector: '.lodging-extra-row'
  });

  setupRepeater({
    toggleId: 'create-payment-toggle',
    addId: 'create-payment-add',
    listId: 'create-payment-list',
    templateId: 'create-payment-template',
    removeSelector: '.js-remove-payment',
    rowSelector: '.payment-entry-row',
    selectFirstNonZero: true
  });
})();

(function () {
  var sections = document.querySelectorAll('[data-confirm-hold]');
  if (!sections.length) return;

  function parseDate(value) {
    if (!value) return null;
    var parts = value.split('-');
    if (parts.length !== 3) return null;
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10) - 1;
    var day = parseInt(parts[2], 10);
    var date = new Date(year, month, day);
    return isNaN(date.getTime()) ? null : date;
  }

  function formatMoney(cents) {
    var amount = (cents / 100).toFixed(2);
    return '$' + amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' MXN';
  }

  sections.forEach(function (section) {
    var form = section.closest('form');
    if (!form) return;
    var checkIn = form.querySelector('input[name="reservation_check_in"]');
    var checkOut = form.querySelector('input[name="reservation_check_out"]');
    var totalInput = section.querySelector('[data-confirm-total]');
    var nightlyInput = section.querySelector('[data-confirm-nightly]');
    var baseNightLabel = section.querySelector('[data-confirm-base-night]');
    var baseTotalLabel = section.querySelector('[data-confirm-base-total]');
    var roomSelect = form.querySelector('select[name="reservation_room_code"]');

    if (!checkIn || !checkOut || !baseNightLabel || !baseTotalLabel || !totalInput || !nightlyInput) {
      return;
    }

    var manualField = '';
    var ignoreSync = false;

    if (totalInput.value.trim() !== '') {
      manualField = 'total';
    } else if (nightlyInput.value.trim() !== '') {
      manualField = 'nightly';
    }

    totalInput.addEventListener('input', function () {
      if (ignoreSync) return;
      if (totalInput.value.trim() !== '') {
        manualField = 'total';
      } else if (nightlyInput.value.trim() !== '') {
        manualField = 'nightly';
      } else {
        manualField = '';
      }
      syncFromInputs('total');
    });

    nightlyInput.addEventListener('input', function () {
      if (ignoreSync) return;
      if (nightlyInput.value.trim() !== '') {
        manualField = 'nightly';
      } else if (totalInput.value.trim() !== '') {
        manualField = 'total';
      } else {
        manualField = '';
      }
      syncFromInputs('nightly');
    });

    function computeNights() {
      var dateIn = parseDate(checkIn.value);
      var dateOut = parseDate(checkOut.value);
      if (!dateIn || !dateOut) return 0;
      var diff = Math.round((dateOut - dateIn) / (1000 * 60 * 60 * 24));
      return diff > 0 ? diff : 0;
    }

    function getBaseCents() {
      var base = parseInt(section.getAttribute('data-base-cents') || '0', 10) || 0;
      if (roomSelect && roomSelect.options.length) {
        var opt = roomSelect.options[roomSelect.selectedIndex];
        if (opt) {
          var roomBase = parseInt(opt.getAttribute('data-base') || '0', 10);
          if (!isNaN(roomBase) && roomBase > 0) {
            base = roomBase;
          }
        }
      }
      return base;
    }

    function setFieldValue(field, value) {
      ignoreSync = true;
      field.value = value;
      ignoreSync = false;
    }

    function syncFromInputs(source) {
      var nights = computeNights();
      if (nights <= 0) return;
      if (source === 'total') {
        var totalVal = parseFloat(totalInput.value.replace(',', '.'));
        if (isNaN(totalVal) || totalVal <= 0) {
          setFieldValue(nightlyInput, '');
          return;
        }
        var nightlyVal = totalVal / nights;
        setFieldValue(nightlyInput, nightlyVal.toFixed(2));
      } else if (source === 'nightly') {
        var nightlyVal = parseFloat(nightlyInput.value.replace(',', '.'));
        if (isNaN(nightlyVal) || nightlyVal <= 0) {
          setFieldValue(totalInput, '');
          return;
        }
        var totalVal = nightlyVal * nights;
        setFieldValue(totalInput, totalVal.toFixed(2));
      }
    }

    function updateEstimate() {
      var baseCents = getBaseCents();
      var nights = computeNights();
      if (!baseCents || nights <= 0) {
        baseNightLabel.textContent = '--';
        baseTotalLabel.textContent = '--';
        if (manualField === '') {
          setFieldValue(totalInput, '');
          setFieldValue(nightlyInput, '');
        }
        return;
      }
      var totalCents = baseCents * nights;
      baseNightLabel.textContent = formatMoney(baseCents) + ' por noche';
      baseTotalLabel.textContent = formatMoney(totalCents) + ' total';
      if (manualField === '') {
        setFieldValue(totalInput, (totalCents / 100).toFixed(2));
        setFieldValue(nightlyInput, (baseCents / 100).toFixed(2));
      } else if (manualField === 'total') {
        syncFromInputs('total');
      } else if (manualField === 'nightly') {
        syncFromInputs('nightly');
      }
    }

    if (roomSelect) {
      roomSelect.addEventListener('change', updateEstimate);
    }
    checkIn.addEventListener('change', updateEstimate);
    checkOut.addEventListener('change', updateEstimate);
    updateEstimate();
  });
})();

(function () {
  document.querySelectorAll('[data-interest-widget]').forEach(function (widget) {
    var reservationId = parseInt(widget.getAttribute('data-reservation-id') || '0', 10);
    var errorBox = widget.querySelector('[data-interest-error]');
    var list = widget.querySelector('[data-interest-list]');
    var addForm = widget.querySelector('[data-interest-form]') || widget.querySelector('.interest-form');
    var select = widget.querySelector('[data-interest-select]');
    var addBtn = widget.querySelector('[data-interest-add-btn]');
    var detailsWrap = widget.querySelector('[data-interest-details]');
    if (!reservationId || !addForm || !select || !addBtn || !list) return;

    function setError(message) {
      if (!errorBox) return;
      if (!message) {
        errorBox.textContent = '';
        errorBox.style.display = 'none';
      } else {
        errorBox.textContent = message;
        errorBox.style.display = '';
      }
    }

    function setFormVisible(show) {
      if (detailsWrap) {
        detailsWrap.open = !!show;
      }
      addForm.style.display = show ? 'flex' : 'none';
    }

    if (detailsWrap) {
      detailsWrap.addEventListener('toggle', function () {
        addForm.style.display = detailsWrap.open ? 'flex' : 'none';
      });
    }

    function updateEmptyState() {
      var hasItems = list.querySelectorAll('[data-interest-item]').length > 0;
      list.style.display = hasItems ? '' : 'none';
    }

    function createRemoveForm(catalogId) {
      var form = document.createElement('form');
      form.method = 'post';
      form.className = 'interest-tag-action';
      form.setAttribute('data-interest-remove-form', '');

      var actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'interest_catalog_id';
      actionInput.value = String(catalogId);
      form.appendChild(actionInput);

      var button = document.createElement('button');
      button.type = 'submit';
      button.className = 'button-secondary interest-remove';
      button.textContent = 'Quitar';
      form.appendChild(button);

      return form;
    }

    function callInterestApi(action, catalogId) {
      var body = new URLSearchParams();
      body.set('action', action);
      body.set('reservation_id', String(reservationId));
      body.set('catalog_id', String(catalogId));
      var interestApiUrl = window.pmsBuildUrl ? window.pmsBuildUrl('api/reservation_interest.php') : 'api/reservation_interest.php';
      return fetch(interestApiUrl, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: body
      }).then(function (res) {
        return res.json().then(function (payload) {
          if (!res.ok || !payload || payload.error) {
            throw new Error(payload && payload.error ? payload.error : 'No se pudo actualizar intereses.');
          }
          return payload;
        });
      });
    }

    addForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var selectedOption = select.options[select.selectedIndex];
      var catalogId = selectedOption ? parseInt(selectedOption.value || '0', 10) : 0;
      if (!catalogId) {
        setError('Selecciona un concepto para agregar.');
        return;
      }
      setError('');
      addBtn.disabled = true;
      callInterestApi('add', catalogId)
        .then(function () {
          var label = selectedOption ? selectedOption.textContent : 'Concepto';
          var item = document.createElement('div');
          item.className = 'interest-tag';
          item.setAttribute('data-interest-item', '');
          item.setAttribute('data-interest-catalog-id', String(catalogId));

          var span = document.createElement('span');
          span.setAttribute('data-interest-label', '');
          span.textContent = label;
          item.appendChild(span);
          item.appendChild(createRemoveForm(catalogId));
          list.appendChild(item);

          if (selectedOption) {
            selectedOption.remove();
          }
          select.value = '';
          updateEmptyState();
          setFormVisible(false);
        })
        .catch(function (err) {
          setError(err && err.message ? err.message : 'No se pudo agregar el interes.');
        })
        .finally(function () {
          addBtn.disabled = false;
        });
    });

    widget.addEventListener('submit', function (ev) {
      var removeForm = ev.target.closest('[data-interest-remove-form]');
      if (!removeForm) return;
      ev.preventDefault();
      var idInput = removeForm.querySelector('input[name="interest_catalog_id"]');
      var catalogId = idInput ? parseInt(idInput.value || '0', 10) : 0;
      if (!catalogId) return;
      setError('');
      var removeBtn = removeForm.querySelector('button[type="submit"]');
      if (removeBtn) removeBtn.disabled = true;
      callInterestApi('remove', catalogId)
        .then(function () {
          var item = removeForm.closest('[data-interest-item]');
          var labelEl = item ? item.querySelector('[data-interest-label]') : null;
          var label = labelEl ? labelEl.textContent : 'Concepto';
          if (item) item.remove();

          var opt = document.createElement('option');
          opt.value = String(catalogId);
          opt.textContent = label;
          select.appendChild(opt);
          updateEmptyState();
        })
        .catch(function (err) {
          setError(err && err.message ? err.message : 'No se pudo quitar el interes.');
        })
        .finally(function () {
          if (removeBtn) removeBtn.disabled = false;
        });
    });

    if (detailsWrap) {
      addForm.style.display = detailsWrap.open ? 'flex' : 'none';
    } else {
      addForm.style.display = 'none';
    }
    updateEmptyState();
  });
})();

(function () {
  document.querySelectorAll('[data-res-summary-card]').forEach(function (card) {
    var form = card.querySelector('[data-res-summary-form]');
    var startBtn = card.querySelector('[data-summary-edit-start]');
    var saveBtn = card.querySelector('[data-summary-edit-save]');
    var cancelBtn = card.querySelector('[data-summary-edit-cancel]');
    var editables = card.querySelectorAll('[data-summary-editable]');
    var propertySelect = form ? form.querySelector('select[name="reservation_property_code"]') : null;
    var roomSelect = form ? form.querySelector('select[name="reservation_room_code"]') : null;
    if (!form || !startBtn || !saveBtn || !cancelBtn) return;

    function syncRoomsByProperty() {
      if (!propertySelect || !roomSelect) return;
      var selectedProperty = (propertySelect.value || '').toUpperCase();
      var selectedRoom = (roomSelect.value || '').toUpperCase();
      var selectedRoomVisible = false;

      Array.prototype.slice.call(roomSelect.options || []).forEach(function (option, index) {
        if (!option) return;
        if (index === 0) {
          option.hidden = false;
          return;
        }
        var roomProperty = (option.getAttribute('data-property') || '').toUpperCase();
        var isVisible = selectedProperty === '' || roomProperty === selectedProperty;
        option.hidden = !isVisible;
        if (isVisible && selectedRoom !== '' && (option.value || '').toUpperCase() === selectedRoom) {
          selectedRoomVisible = true;
        }
      });

      if (selectedRoom !== '' && !selectedRoomVisible) {
        roomSelect.value = '';
      }

      if ((roomSelect.value || '') === '') {
        var firstVisible = Array.prototype.slice.call(roomSelect.options || []).find(function (option, index) {
          return index > 0 && option && !option.hidden;
        });
        if (firstVisible) {
          roomSelect.value = firstVisible.value;
        }
      }
    }

    function setEditing(on) {
      card.classList.toggle('reservation-summary-editing', on);
      editables.forEach(function (field) {
        field.disabled = !on;
      });
      startBtn.style.display = on ? 'none' : 'inline-flex';
      saveBtn.style.display = on ? 'inline-flex' : 'none';
      cancelBtn.style.display = on ? 'inline-flex' : 'none';
      if (on) {
        syncRoomsByProperty();
      }
    }

    startBtn.addEventListener('click', function () {
      setEditing(true);
    });

    cancelBtn.addEventListener('click', function () {
      form.reset();
      syncRoomsByProperty();
      setEditing(false);
    });

    if (propertySelect) {
      propertySelect.addEventListener('change', syncRoomsByProperty);
    }

    syncRoomsByProperty();
    setEditing(false);
  });
})();

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
      container.classList.toggle('is-folios-active', targetId.indexOf('-folios') !== -1);
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
  var quickMenus = Array.prototype.slice.call(document.querySelectorAll('.reservation-quick-actions'));
  if (!quickMenus.length) return;

  function closeAllExcept(allowedMenu) {
    quickMenus.forEach(function (menu) {
      if (menu !== allowedMenu) {
        menu.removeAttribute('open');
      }
    });
  }

  quickMenus.forEach(function (menu) {
    menu.addEventListener('toggle', function () {
      if (menu.open) {
        closeAllExcept(menu);
      }
    });
  });

  document.addEventListener('click', function (ev) {
    var clickedInsideMenu = ev.target && ev.target.closest
      ? ev.target.closest('.reservation-quick-actions')
      : null;
    closeAllExcept(clickedInsideMenu || null);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      closeAllExcept(null);
    }
  });
})();
</script>

