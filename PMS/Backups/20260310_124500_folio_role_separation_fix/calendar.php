<?php
$moduleKey = 'calendar';
require_once __DIR__ . '/../services/RateplanPricingService.php';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$rateplanPricingService = new RateplanPricingService(pms_get_connection());

if ($companyId <= 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('calendar.view');

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
$roomMapPayload = array();
foreach ($roomsCatalog as $room) {
    $propertyCode = isset($room['property_code']) ? strtoupper((string)$room['property_code']) : '';
    if ($propertyCode === '') {
        continue;
    }
    if (!isset($roomsByProperty[$propertyCode])) {
        $roomsByProperty[$propertyCode] = array();
    }
    $roomsByProperty[$propertyCode][] = $room;
    $roomMapPayload[$propertyCode][] = array(
        'code' => isset($room['code']) ? (string)$room['code'] : '',
        'label' => calendar_room_label($room)
    );
}

if (!function_exists('calendar_source_color_key')) {
    function calendar_source_color_key($value)
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/\[[^\]]+\]/', '', $raw);
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/\s+/', ' ', $raw);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($raw, 'UTF-8');
        }
        return strtolower($raw);
    }
}

if (!function_exists('calendar_category_amenity_catalog')) {
    function calendar_category_amenity_catalog()
    {
        return array(
            'has_air_conditioning' => array('label' => 'Aire acondicionado', 'icon_html' => '&#10052;'),
            'has_fan' => array('label' => 'Ventilador', 'icon_html' => '&#126980;'),
            'has_tv' => array('label' => 'Television', 'icon_html' => '&#128250;'),
            'has_private_wifi' => array('label' => 'Wi-Fi privado', 'icon_html' => '&#128246;'),
            'has_minibar' => array('label' => 'Minibar', 'icon_html' => '&#127864;'),
            'has_safe_box' => array('label' => 'Caja fuerte', 'icon_html' => '&#128274;'),
            'has_workspace' => array('label' => 'Espacio de trabajo', 'icon_html' => '&#128187;'),
            'includes_bedding_towels' => array('label' => 'Ropa de cama y toallas', 'icon_html' => '&#128719;'),
            'has_iron_board' => array('label' => 'Plancha/Tabla', 'icon_html' => '&#128087;'),
            'has_closet_rack' => array('label' => 'Closet/Perchero', 'icon_html' => '&#128085;'),
            'has_private_balcony_terrace' => array('label' => 'Balcon/Terraza privada', 'icon_html' => '&#127748;'),
            'has_view' => array('label' => 'Vista', 'icon_html' => '&#128065;'),
            'has_private_entrance' => array('label' => 'Entrada independiente', 'icon_html' => '&#128682;'),
            'has_hot_water' => array('label' => 'Agua caliente', 'icon_html' => '&#9832;'),
            'includes_toiletries' => array('label' => 'Articulos de aseo', 'icon_html' => '&#129533;'),
            'has_hairdryer' => array('label' => 'Secadora de cabello', 'icon_html' => '&#128135;'),
            'includes_clean_towels' => array('label' => 'Toallas limpias', 'icon_html' => '&#129530;'),
            'has_coffee_tea_kettle' => array('label' => 'Cafetera/Tetera', 'icon_html' => '&#9749;'),
            'has_basic_utensils' => array('label' => 'Utensilios basicos', 'icon_html' => '&#127860;'),
            'has_basic_food_items' => array('label' => 'Basicos alimentos', 'icon_html' => '&#127859;'),
            'is_private' => array('label' => 'Privada', 'icon_html' => '&#128273;'),
            'is_shared' => array('label' => 'Compartida', 'icon_html' => '&#128101;'),
            'has_shared_bathroom' => array('label' => 'Bano compartido', 'icon_html' => '&#128701;'),
            'has_private_bathroom' => array('label' => 'Bano privado', 'icon_html' => '&#128705;')
        );
    }
}

if (!function_exists('calendar_parse_category_amenities_csv')) {
    function calendar_parse_category_amenities_csv($value)
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return array();
        }
        $catalog = calendar_category_amenity_catalog();
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
            if ($key === '' || !isset($catalog[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $key;
        }
        return $out;
    }
}

if (!function_exists('calendar_category_icon_capsules_html')) {
    function calendar_category_icon_capsules_html(array $amenityKeys)
    {
        if (!$amenityKeys) {
            return '';
        }
        $catalog = calendar_category_amenity_catalog();
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
        $normalized = array();
        $seen = array();
        foreach ($amenityKeys as $key) {
            $key = trim((string)$key);
            if (isset($aliases[$key])) {
                $key = $aliases[$key];
            }
            if (isset($seen[$key])) {
                continue;
            }
            if (isset($catalog[$key])) {
                $seen[$key] = true;
                $normalized[] = $key;
            }
        }
        if (!$normalized) {
            return '';
        }
        $html = '<span class="category-icon-capsules">';
        foreach ($normalized as $key) {
            $iconHtml = isset($catalog[$key]['icon_html']) ? (string)$catalog[$key]['icon_html'] : '&#9679;';
            if (preg_match('/(&#x?[0-9A-Fa-f]+;|&[A-Za-z][A-Za-z0-9]+;)/', $iconHtml, $iconMatch)) {
                $iconHtml = (string)$iconMatch[1];
            }
            $label = isset($catalog[$key]['label']) ? (string)$catalog[$key]['label'] : $key;
            $html .= '<span class="category-icon" aria-hidden="true" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . $iconHtml . '</span>';
        }
        $html .= '</span>';
        return $html;
    }
}

$otaAccountsByProperty = function_exists('pms_fetch_ota_accounts_grouped')
    ? pms_fetch_ota_accounts_grouped($companyId, false)
    : array();
$reservationSourcesByProperty = function_exists('pms_fetch_reservation_sources_grouped')
    ? pms_fetch_reservation_sources_grouped($companyId, false)
    : array('*' => array());
$calendarOtaOptionsByProperty = array();
$calendarSourceOptionsByProperty = array();
$calendarOtaColorByProperty = array();
$calendarOtaColorById = array();
$calendarOtaExternalCodeByProperty = array();
$calendarSourceColorByProperty = array();
$calendarSourceColorById = array();
$calendarSourceColorByName = array();
$calendarSourceCodeByProperty = array();
$calendarOtaLodgingCatalogByCatalogId = array();
$calendarOtaInfoCatalogsByAccount = array();
$calendarSourceInfoCatalogsBySource = array();
if (function_exists('pms_ota_options_for_property')) {
    foreach ($properties as $propertyRow) {
        $propertyCodeTmp = strtoupper((string)(isset($propertyRow['code']) ? $propertyRow['code'] : ''));
        if ($propertyCodeTmp === '') {
            continue;
        }
        $calendarOtaOptionsByProperty[$propertyCodeTmp] = pms_ota_options_for_property($otaAccountsByProperty, $propertyCodeTmp, true);
        if (function_exists('pms_reservation_source_options_for_property')) {
            $calendarSourceOptionsByProperty[$propertyCodeTmp] = pms_reservation_source_options_for_property($reservationSourcesByProperty, $propertyCodeTmp, true);
        } else {
            $calendarSourceOptionsByProperty[$propertyCodeTmp] = array(
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
}
if ($otaAccountsByProperty) {
    foreach ($otaAccountsByProperty as $propertyCodeTmp => $otaRowsTmp) {
        $propertyCodeTmp = strtoupper((string)$propertyCodeTmp);
        if ($propertyCodeTmp === '' || !is_array($otaRowsTmp)) {
            continue;
        }
        foreach ($otaRowsTmp as $otaRowTmp) {
            $otaIdTmp = isset($otaRowTmp['id_ota_account']) ? (int)$otaRowTmp['id_ota_account'] : 0;
            if ($otaIdTmp <= 0) {
                continue;
            }
            $externalCodeTmp = strtoupper(trim((string)(isset($otaRowTmp['external_code']) ? $otaRowTmp['external_code'] : '')));
            $externalCodeTmp = preg_replace('/[^A-Z0-9]+/', '', $externalCodeTmp);
            if ($externalCodeTmp !== '') {
                if (function_exists('mb_substr')) {
                    $externalCodeTmp = mb_substr($externalCodeTmp, 0, 6);
                } else {
                    $externalCodeTmp = substr($externalCodeTmp, 0, 6);
                }
                if (!isset($calendarOtaExternalCodeByProperty[$propertyCodeTmp])) {
                    $calendarOtaExternalCodeByProperty[$propertyCodeTmp] = array();
                }
                $calendarOtaExternalCodeByProperty[$propertyCodeTmp][$otaIdTmp] = $externalCodeTmp;
            }
            $colorTmp = '';
            if (function_exists('ota_normalize_color_hex')) {
                $colorTmp = ota_normalize_color_hex(isset($otaRowTmp['color_hex']) ? (string)$otaRowTmp['color_hex'] : '');
            } else {
                $rawColorTmp = strtoupper(trim((string)(isset($otaRowTmp['color_hex']) ? $otaRowTmp['color_hex'] : '')));
                if ($rawColorTmp !== '') {
                    if (strpos($rawColorTmp, '#') !== 0) {
                        $rawColorTmp = '#' . $rawColorTmp;
                    }
                    if (preg_match('/^#[0-9A-F]{6}$/', $rawColorTmp)) {
                        $colorTmp = $rawColorTmp;
                    }
                }
            }
            if ($colorTmp !== '') {
                if (!isset($calendarOtaColorByProperty[$propertyCodeTmp])) {
                    $calendarOtaColorByProperty[$propertyCodeTmp] = array();
                }
                $calendarOtaColorByProperty[$propertyCodeTmp][$otaIdTmp] = $colorTmp;
                $calendarOtaColorById[$otaIdTmp] = $colorTmp;
            }
        }
    }
}

if ($calendarSourceOptionsByProperty) {
    foreach ($calendarSourceOptionsByProperty as $propertyCodeTmp => $sourceRowsTmp) {
        $propertyCodeTmp = strtoupper((string)$propertyCodeTmp);
        if ($propertyCodeTmp === '' || !is_array($sourceRowsTmp)) {
            continue;
        }
        foreach ($sourceRowsTmp as $sourceRowTmp) {
            $sourceIdTmp = isset($sourceRowTmp['id_reservation_source']) ? (int)$sourceRowTmp['id_reservation_source'] : 0;
            if ($sourceIdTmp <= 0) {
                continue;
            }
            $sourceCodeTmp = strtoupper(trim((string)(isset($sourceRowTmp['source_code']) ? $sourceRowTmp['source_code'] : '')));
            $sourceCodeTmp = preg_replace('/[^A-Z0-9]+/', '', $sourceCodeTmp);
            if ($sourceCodeTmp !== '') {
                if (function_exists('mb_substr')) {
                    $sourceCodeTmp = mb_substr($sourceCodeTmp, 0, 6);
                } else {
                    $sourceCodeTmp = substr($sourceCodeTmp, 0, 6);
                }
                if (!isset($calendarSourceCodeByProperty[$propertyCodeTmp])) {
                    $calendarSourceCodeByProperty[$propertyCodeTmp] = array();
                }
                $calendarSourceCodeByProperty[$propertyCodeTmp][$sourceIdTmp] = $sourceCodeTmp;
            }
            $sourceColorTmp = '';
            if (function_exists('calendar_normalize_hex_color')) {
                $sourceColorTmp = calendar_normalize_hex_color(isset($sourceRowTmp['color_hex']) ? (string)$sourceRowTmp['color_hex'] : '');
            } elseif (function_exists('pms_reservation_source_normalize_color_hex')) {
                $sourceColorTmp = pms_reservation_source_normalize_color_hex(isset($sourceRowTmp['color_hex']) ? (string)$sourceRowTmp['color_hex'] : '');
            } else {
                $rawSourceColorTmp = strtoupper(trim((string)(isset($sourceRowTmp['color_hex']) ? $sourceRowTmp['color_hex'] : '')));
                if ($rawSourceColorTmp !== '') {
                    if (strpos($rawSourceColorTmp, '#') !== 0) {
                        $rawSourceColorTmp = '#' . $rawSourceColorTmp;
                    }
                    if (preg_match('/^#[0-9A-F]{6}$/', $rawSourceColorTmp)) {
                        $sourceColorTmp = $rawSourceColorTmp;
                    }
                }
            }
            if ($sourceColorTmp !== '') {
                if (!isset($calendarSourceColorByProperty[$propertyCodeTmp])) {
                    $calendarSourceColorByProperty[$propertyCodeTmp] = array();
                }
                $calendarSourceColorByProperty[$propertyCodeTmp][$sourceIdTmp] = $sourceColorTmp;
                $calendarSourceColorById[$sourceIdTmp] = $sourceColorTmp;
                $sourceNameKeyTmp = calendar_source_color_key(isset($sourceRowTmp['source_name']) ? (string)$sourceRowTmp['source_name'] : '');
                if ($sourceNameKeyTmp !== '' && !isset($calendarSourceColorByName[$sourceNameKeyTmp])) {
                    $calendarSourceColorByName[$sourceNameKeyTmp] = $sourceColorTmp;
                }
            }
        }
    }
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            oa.id_ota_account,
            oalc.id_line_item_catalog
         FROM ota_account oa
         JOIN ota_account_lodging_catalog oalc
           ON oalc.id_ota_account = oa.id_ota_account
          AND oalc.deleted_at IS NULL
          AND oalc.is_active = 1
         JOIN line_item_catalog lic
           ON lic.id_line_item_catalog = oalc.id_line_item_catalog
          AND lic.deleted_at IS NULL
          AND lic.is_active = 1
         WHERE oa.id_company = ?
           AND oa.deleted_at IS NULL
           AND oa.is_active = 1'
    );
    $stmt->execute(array($companyId));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $otaId = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
        $catalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($otaId <= 0 || $catalogId <= 0) {
            continue;
        }
        if (!isset($calendarOtaLodgingCatalogByCatalogId[$catalogId])) {
            $calendarOtaLodgingCatalogByCatalogId[$catalogId] = array();
        }
        $calendarOtaLodgingCatalogByCatalogId[$catalogId][$otaId] = true;
    }
} catch (Exception $e) {
    $calendarOtaLodgingCatalogByCatalogId = array();
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            oaic.id_ota_account,
            oaic.id_line_item_catalog,
            oaic.sort_order,
            lic.item_name,
            oaic.display_alias,
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
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $otaId = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
        $catalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($otaId <= 0 || $catalogId <= 0) {
            continue;
        }
        if (!isset($calendarOtaInfoCatalogsByAccount[$otaId])) {
            $calendarOtaInfoCatalogsByAccount[$otaId] = array();
        }
        $itemName = isset($row['item_name']) ? trim((string)$row['item_name']) : ('Catalogo #' . $catalogId);
        $category = isset($row['category_name']) ? trim((string)$row['category_name']) : '';
        $alias = isset($row['display_alias']) ? trim((string)$row['display_alias']) : '';
        $defaultLabel = $category !== '' ? ($category . ' / ' . $itemName) : $itemName;
        $label = $alias !== '' ? $alias : $defaultLabel;
        $calendarOtaInfoCatalogsByAccount[$otaId][] = array(
            'id_line_item_catalog' => $catalogId,
            'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
            'label' => $label
        );
    }
} catch (Exception $e) {
    $calendarOtaInfoCatalogsByAccount = array();
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            rsic.id_reservation_source,
            rsic.id_line_item_catalog,
            rsic.sort_order,
            lic.item_name,
            rsic.display_alias,
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
        if (!isset($calendarSourceInfoCatalogsBySource[$sourceId])) {
            $calendarSourceInfoCatalogsBySource[$sourceId] = array();
        }
        $itemName = isset($row['item_name']) ? trim((string)$row['item_name']) : ('Catalogo #' . $catalogId);
        $category = isset($row['category_name']) ? trim((string)$row['category_name']) : '';
        $alias = isset($row['display_alias']) ? trim((string)$row['display_alias']) : '';
        $defaultLabel = $category !== '' ? ($category . ' / ' . $itemName) : $itemName;
        $label = $alias !== '' ? $alias : $defaultLabel;
        $calendarSourceInfoCatalogsBySource[$sourceId][] = array(
            'id_line_item_catalog' => $catalogId,
            'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
            'label' => $label
        );
    }
} catch (Exception $e) {
    $calendarSourceInfoCatalogsBySource = array();
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
        $propertyCodeTmp = strtoupper(trim((string)(isset($src['property_code']) ? $src['property_code'] : '')));
        $label = $categoryName !== '' ? ($categoryName . ' / ' . $name) : $name;
        $paymentCatalogsById[$pid] = array(
            'id_payment_catalog' => $pid,
            'name' => $name,
            'label' => $label,
            'id_property' => isset($src['id_property']) ? (int)$src['id_property'] : null,
            'property_code' => $propertyCodeTmp
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
            'default_unit_price_cents' => isset($src['default_unit_price_cents']) ? (int)$src['default_unit_price_cents'] : 0
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

if (!function_exists('calendar_render_context_hiddens')) {
    function calendar_render_context_hiddens($propertyCode, $startDate, $viewModeKey, $orderMode)
    {
        echo '<input type="hidden" name="property_code" value="'
            . htmlspecialchars((string)$propertyCode, ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<input type="hidden" name="start_date" value="'
            . htmlspecialchars((string)$startDate, ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<input type="hidden" name="order_mode" value="'
            . htmlspecialchars((string)$orderMode, ENT_QUOTES, 'UTF-8')
            . '">';
    }
}

if (!function_exists('calendar_render_wizard_return_hiddens')) {
    function calendar_render_wizard_return_hiddens($propertyCode, $startDate, $viewModeKey, $orderMode, $currentSubtab, $dirtyTabs)
    {
        echo '<input type="hidden" name="wizard_return_view" value="calendar">';
        echo '<input type="hidden" name="wizard_return_restore" value="1">';
        echo '<input type="hidden" name="wizard_return_property_code" value="'
            . htmlspecialchars((string)$propertyCode, ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<input type="hidden" name="wizard_return_start_date" value="'
            . htmlspecialchars((string)$startDate, ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<input type="hidden" name="wizard_return_view_mode" value="'
            . htmlspecialchars((string)$viewModeKey, ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<input type="hidden" name="wizard_return_order_mode" value="'
            . htmlspecialchars((string)$orderMode, ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<input type="hidden" name="wizard_return_calendar_current_subtab" value="'
            . htmlspecialchars((string)$currentSubtab, ENT_QUOTES, 'UTF-8')
            . '">';
        echo '<input type="hidden" name="wizard_return_calendar_dirty_tabs" value="'
            . htmlspecialchars((string)$dirtyTabs, ENT_QUOTES, 'UTF-8')
            . '">';
    }
}

if (!function_exists('calendar_status_class')) {
    function calendar_status_class($status)
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9_]/', '', (string)$status));
        if ($normalized === '') {
            return 'status-unknown';
        }
        return 'status-' . $normalized;
    }
}

if (!function_exists('pms_calendar_status_class')) {
    function pms_calendar_status_class($status)
    {
        return calendar_status_class($status);
    }
}

if (!function_exists('calendar_format_date')) {
    function calendar_format_date($dateString, $format = 'Y-m-d')
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

if (!function_exists('calendar_format_money')) {
    function calendar_format_money($cents, $currency = 'MXN')
    {
        $amount = number_format(((float)$cents) / 100, 2, '.', ',');
        return ($currency === 'MXN' ? '$' : '') . $amount . ($currency ? ' ' . $currency : '');
    }
}

if (!function_exists('calendar_to_cents')) {
    function calendar_to_cents($value)
    {
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            return (int)round($value * 100);
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return 0;
        }
        $normalized = str_replace(array(',', '$', ' '), array('.', '', ''), $raw);
        if (!is_numeric($normalized)) {
            return 0;
        }
        return (int)round(((float)$normalized) * 100);
    }
}

if (!function_exists('calendar_extract_line_item_id_from_result_sets')) {
    function calendar_extract_line_item_id_from_result_sets($resultSets)
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

if (!function_exists('calendar_payment_catalogs_for_property')) {
    function calendar_payment_catalogs_for_property(array $map, $propertyCode)
    {
        $prop = strtoupper(trim((string)$propertyCode));
        $out = array();
        if (isset($map['*']) && is_array($map['*'])) {
            $out = array_merge($out, $map['*']);
        }
        if ($prop !== '' && isset($map[$prop]) && is_array($map[$prop])) {
            $out = array_merge($out, $map[$prop]);
        }
        $dedupe = array();
        $final = array();
        foreach ($out as $row) {
            $id = isset($row['id_payment_catalog']) ? (int)$row['id_payment_catalog'] : 0;
            if ($id <= 0 || isset($dedupe[$id])) {
                continue;
            }
            $dedupe[$id] = true;
            $final[] = $row;
        }
        return $final;
    }
}

if (!function_exists('calendar_payment_catalogs_for_reservation')) {
    function calendar_payment_catalogs_for_reservation(
        array $map,
        $propertyCode,
        $companyId,
        $reservationId,
        array $blockedByReservation = array()
    ) {
        $rows = calendar_payment_catalogs_for_property($map, $propertyCode);
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

if (!function_exists('calendar_service_catalogs_for_property')) {
    function calendar_service_catalogs_for_property(array $map, $propertyCode)
    {
        $prop = strtoupper(trim((string)$propertyCode));
        $out = array();
        if (isset($map['*']) && is_array($map['*'])) {
            $out = array_merge($out, $map['*']);
        }
        if ($prop !== '' && isset($map[$prop]) && is_array($map[$prop])) {
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

if (!function_exists('calendar_folio_role_by_name')) {
    function calendar_folio_role_by_name($folioName)
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

if (!function_exists('calendar_build_service_balance_map')) {
    function calendar_build_service_balance_map(PDO $pdo, array $reservationPropertyById, array $serviceCatalogsByProperty)
    {
        $result = array();
        if (!$reservationPropertyById) {
            return $result;
        }

        $reservationIds = array();
        foreach ($reservationPropertyById as $reservationId => $propertyCode) {
            $rid = (int)$reservationId;
            if ($rid > 0) {
                $reservationIds[$rid] = $rid;
            }
        }
        if (!$reservationIds) {
            return $result;
        }

        $reservationIdChunks = array_chunk(array_values($reservationIds), 500);
        foreach ($reservationIdChunks as $reservationIdChunk) {
            if (!$reservationIdChunk) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($reservationIdChunk), '?'));
            $stmtFolios = $pdo->prepare(
                'SELECT
                    f.id_folio,
                    f.id_reservation,
                    COALESCE(f.total_cents, 0) AS total_cents,
                    COALESCE(f.balance_cents, 0) AS balance_cents,
                    COALESCE(f.folio_name, \'\') AS folio_name
                 FROM folio f
                 WHERE f.deleted_at IS NULL
                   AND COALESCE(f.is_active, 1) = 1
                   AND f.id_reservation IN (' . $placeholders . ')'
            );
            $stmtFolios->execute($reservationIdChunk);
            $lodgingFolioIds = array();
            foreach ($stmtFolios->fetchAll(PDO::FETCH_ASSOC) as $folioRow) {
                $rid = isset($folioRow['id_reservation']) ? (int)$folioRow['id_reservation'] : 0;
                if ($rid <= 0) {
                    continue;
                }
                $fid = isset($folioRow['id_folio']) ? (int)$folioRow['id_folio'] : 0;
                if (!isset($result[$rid])) {
                    $result[$rid] = array(
                        'total_balance_cents' => 0,
                        'lodging_balance_cents' => 0,
                        'service_balance_cents' => 0,
                        'has_lodging_charges' => false
                    );
                }
                $folioRole = calendar_folio_role_by_name(isset($folioRow['folio_name']) ? $folioRow['folio_name'] : '');
                $totalCents = isset($folioRow['total_cents']) ? (int)$folioRow['total_cents'] : 0;
                $balanceCents = isset($folioRow['balance_cents']) ? (int)$folioRow['balance_cents'] : 0;
                if ($balanceCents < 0) {
                    $balanceCents = 0;
                }
                if ($totalCents < 0) {
                    $totalCents = 0;
                }
                $result[$rid]['total_balance_cents'] += $balanceCents;
                if ($folioRole === 'services') {
                    $result[$rid]['service_balance_cents'] += $balanceCents;
                } else {
                    $result[$rid]['lodging_balance_cents'] += $balanceCents;
                    if ($fid > 0) {
                        $lodgingFolioIds[$fid] = $fid;
                    }
                }
            }
            if ($lodgingFolioIds) {
                $folioPlaceholders = implode(',', array_fill(0, count($lodgingFolioIds), '?'));
                $stmtLodgingCharges = $pdo->prepare(
                    'SELECT
                        f.id_reservation,
                        COUNT(*) AS charge_count
                     FROM line_item li
                     JOIN folio f
                       ON f.id_folio = li.id_folio
                      AND f.deleted_at IS NULL
                     WHERE li.id_folio IN (' . $folioPlaceholders . ')
                       AND li.deleted_at IS NULL
                       AND COALESCE(li.is_active, 1) = 1
                       AND LOWER(TRIM(COALESCE(li.item_type, \'\'))) = \'sale_item\'
                       AND (
                         li.status IS NULL
                         OR LOWER(TRIM(li.status)) NOT IN (\'void\', \'canceled\', \'cancelled\')
                       )
                     GROUP BY f.id_reservation'
                );
                $stmtLodgingCharges->execute(array_values($lodgingFolioIds));
                foreach ($stmtLodgingCharges->fetchAll(PDO::FETCH_ASSOC) as $chargeRow) {
                    $chargeReservationId = isset($chargeRow['id_reservation']) ? (int)$chargeRow['id_reservation'] : 0;
                    $chargeCount = isset($chargeRow['charge_count']) ? (int)$chargeRow['charge_count'] : 0;
                    if ($chargeReservationId <= 0 || $chargeCount <= 0 || !isset($result[$chargeReservationId])) {
                        continue;
                    }
                    $result[$chargeReservationId]['has_lodging_charges'] = true;
                }
            }
        }

        return $result;
    }
}

if (!function_exists('calendar_build_ota_info_preview_map')) {
    function calendar_build_ota_info_preview_map(
        PDO $pdo,
        array $reservationOtaIdByReservation,
        array $reservationSourceIdByReservation,
        array $otaLodgingCatalogByCatalogId,
        array $otaInfoCatalogsByAccount,
        array $sourceInfoCatalogsBySource
    ) {
        $result = array();
        if (!$otaInfoCatalogsByAccount && !$sourceInfoCatalogsBySource) {
            return $result;
        }

        $reservationIds = array();
        foreach ($reservationOtaIdByReservation as $reservationId => $otaAccountId) {
            $rid = (int)$reservationId;
            if ($rid > 0) {
                $reservationIds[$rid] = $rid;
            }
        }
        foreach ($reservationSourceIdByReservation as $reservationId => $sourceId) {
            $rid = (int)$reservationId;
            if ($rid > 0) {
                $reservationIds[$rid] = $rid;
            }
        }
        if (!$reservationIds) {
            return $result;
        }

        $lineItemsByReservation = array();
        $reservationIdChunks = array_chunk(array_values($reservationIds), 500);
        foreach ($reservationIdChunks as $reservationIdChunk) {
            if (!$reservationIdChunk) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($reservationIdChunk), '?'));
            $stmtLineItems = $pdo->prepare(
                'SELECT
                    f.id_reservation,
                    li.id_line_item_catalog,
                    li.item_type,
                    li.status,
                    COALESCE(li.amount_cents, 0) AS amount_cents
                 FROM folio f
                 JOIN line_item li
                   ON li.id_folio = f.id_folio
                 WHERE f.deleted_at IS NULL
                   AND COALESCE(f.is_active, 1) = 1
                   AND li.deleted_at IS NULL
                   AND COALESCE(li.is_active, 1) = 1
                   AND (li.status IS NULL OR li.status NOT IN ("void","canceled","cancelled"))
                   AND f.id_reservation IN (' . $placeholders . ')'
            );
            $stmtLineItems->execute($reservationIdChunk);
            foreach ($stmtLineItems->fetchAll(PDO::FETCH_ASSOC) as $lineItemRow) {
                $rid = isset($lineItemRow['id_reservation']) ? (int)$lineItemRow['id_reservation'] : 0;
                if ($rid <= 0) {
                    continue;
                }
                if (!isset($lineItemsByReservation[$rid])) {
                    $lineItemsByReservation[$rid] = array();
                }
                $lineItemsByReservation[$rid][] = $lineItemRow;
            }
        }

        foreach ($reservationIds as $rid) {
            $rid = (int)$rid;
            if ($rid <= 0) {
                continue;
            }

            $lineItems = isset($lineItemsByReservation[$rid]) ? $lineItemsByReservation[$rid] : array();
            $otaDetectedId = isset($reservationOtaIdByReservation[$rid]) ? (int)$reservationOtaIdByReservation[$rid] : 0;
            $explicitOtaId = $otaDetectedId;
            $sourceId = isset($reservationSourceIdByReservation[$rid]) ? (int)$reservationSourceIdByReservation[$rid] : 0;
            $infoConfigRows = array();

            if ($explicitOtaId > 0 && isset($otaInfoCatalogsByAccount[$explicitOtaId]) && $otaInfoCatalogsByAccount[$explicitOtaId]) {
                $infoConfigRows = $otaInfoCatalogsByAccount[$explicitOtaId];
            } elseif ($sourceId > 0 && isset($sourceInfoCatalogsBySource[$sourceId]) && $sourceInfoCatalogsBySource[$sourceId]) {
                $infoConfigRows = $sourceInfoCatalogsBySource[$sourceId];
            }

            if (!$infoConfigRows && ($otaDetectedId <= 0 || !isset($otaInfoCatalogsByAccount[$otaDetectedId])) && $otaLodgingCatalogByCatalogId && $lineItems) {
                $otaDetectionScore = array();
                foreach ($lineItems as $lineItemRow) {
                    $itemType = strtolower(trim((string)(isset($lineItemRow['item_type']) ? $lineItemRow['item_type'] : '')));
                    if ($itemType !== 'sale_item') {
                        continue;
                    }
                    $catalogId = isset($lineItemRow['id_line_item_catalog']) ? (int)$lineItemRow['id_line_item_catalog'] : 0;
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
                                'catalogs' => array()
                            );
                        }
                        $otaDetectionScore[$candidateOtaId]['match_count']++;
                        $otaDetectionScore[$candidateOtaId]['catalogs'][$catalogId] = true;
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
                if ($bestScoreOtaId > 0) {
                    $otaDetectedId = $bestScoreOtaId;
                }
            }

            if (!$infoConfigRows && $otaDetectedId > 0 && isset($otaInfoCatalogsByAccount[$otaDetectedId]) && $otaInfoCatalogsByAccount[$otaDetectedId]) {
                $infoConfigRows = $otaInfoCatalogsByAccount[$otaDetectedId];
            }
            if (!$infoConfigRows) {
                continue;
            }

            $otaInfoRows = array();
            $otaInfoRowIndexByCatalog = array();
            foreach ($infoConfigRows as $cfg) {
                $catalogId = isset($cfg['id_line_item_catalog']) ? (int)$cfg['id_line_item_catalog'] : 0;
                if ($catalogId <= 0 || isset($otaInfoRowIndexByCatalog[$catalogId])) {
                    continue;
                }
                $otaInfoRowIndexByCatalog[$catalogId] = count($otaInfoRows);
                $otaInfoRows[] = array(
                    'label' => isset($cfg['label']) ? (string)$cfg['label'] : ('Catalogo #' . $catalogId),
                    'amount_total_cents' => 0
                );
            }
            if (!$otaInfoRows) {
                continue;
            }

            foreach ($lineItems as $lineItemRow) {
                $catalogId = isset($lineItemRow['id_line_item_catalog']) ? (int)$lineItemRow['id_line_item_catalog'] : 0;
                if ($catalogId <= 0 || !isset($otaInfoRowIndexByCatalog[$catalogId])) {
                    continue;
                }
                $idx = (int)$otaInfoRowIndexByCatalog[$catalogId];
                if (!isset($otaInfoRows[$idx])) {
                    continue;
                }
                $otaInfoRows[$idx]['amount_total_cents'] += (int)(isset($lineItemRow['amount_cents']) ? $lineItemRow['amount_cents'] : 0);
            }

            $result[$rid] = $otaInfoRows;
        }

        return $result;
    }
}

if (!function_exists('calendar_payment_method_name_by_id')) {
    function calendar_payment_method_name_by_id(array $paymentMethodsById, $methodId)
    {
        $methodId = (int)$methodId;
        if ($methodId <= 0 || !isset($paymentMethodsById[$methodId])) {
            return '';
        }
        return trim((string)(isset($paymentMethodsById[$methodId]['name']) ? $paymentMethodsById[$methodId]['name'] : ''));
    }
}

if (!function_exists('calendar_resolve_payment_transfer_target')) {
    function calendar_resolve_payment_transfer_target($companyId, $reservationId, $sourceFolioId, $requestedTargetFolioId = 0)
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

if (!function_exists('calendar_find_first_open_folio_id')) {
    function calendar_find_first_open_folio_id($companyId, $reservationId)
    {
        $companyId = (int)$companyId;
        $reservationId = (int)$reservationId;
        if ($companyId <= 0 || $reservationId <= 0) {
            return 0;
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT f.id_folio
                 FROM folio f
                 JOIN reservation r ON r.id_reservation = f.id_reservation
                 JOIN property p ON p.id_property = r.id_property
                 WHERE f.id_reservation = ?
                   AND p.id_company = ?
                   AND r.deleted_at IS NULL
                   AND p.deleted_at IS NULL
                   AND f.deleted_at IS NULL
                   AND COALESCE(f.is_active, 1) = 1
                   AND LOWER(TRIM(COALESCE(f.status, \'open\'))) <> \'closed\'
                 ORDER BY f.id_folio ASC
                 LIMIT 1'
            );
            $stmt->execute(array($reservationId, $companyId));
            $folioId = $stmt->fetchColumn();
            return $folioId !== false ? (int)$folioId : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('calendar_find_open_folio_id_by_role')) {
    function calendar_find_open_folio_id_by_role($companyId, $reservationId, $role = 'lodging', $actorUserId = null)
    {
        $companyId = (int)$companyId;
        $reservationId = (int)$reservationId;
        $role = strtolower(trim((string)$role));
        if ($companyId <= 0 || $reservationId <= 0) {
            return 0;
        }
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT
                    f.id_folio,
                    COALESCE(f.folio_name, \'\') AS folio_name,
                    COALESCE(NULLIF(TRIM(f.currency), \'\'), \'MXN\') AS currency,
                    f.due_date
                 FROM folio f
                 JOIN reservation r ON r.id_reservation = f.id_reservation
                 JOIN property p ON p.id_property = r.id_property
                 WHERE f.id_reservation = ?
                   AND p.id_company = ?
                   AND r.deleted_at IS NULL
                   AND p.deleted_at IS NULL
                   AND f.deleted_at IS NULL
                   AND COALESCE(f.is_active, 1) = 1
                   AND LOWER(TRIM(COALESCE(f.status, \'open\'))) <> \'closed\'
                 ORDER BY f.id_folio ASC'
            );
            $stmt->execute(array($reservationId, $companyId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                foreach ($rows as $row) {
                    $folioId = isset($row['id_folio']) ? (int)$row['id_folio'] : 0;
                    if ($folioId <= 0) {
                        continue;
                    }
                    $folioRole = calendar_folio_role_by_name(isset($row['folio_name']) ? $row['folio_name'] : '');
                    if ($folioRole === $role) {
                        return $folioId;
                    }
                }
                if ($role !== 'services') {
                    $fallbackId = isset($rows[0]['id_folio']) ? (int)$rows[0]['id_folio'] : 0;
                    if ($fallbackId > 0) {
                        return $fallbackId;
                    }
                }
            }

            if ($role !== 'services' && !$rows) {
                $currency = 'MXN';
                $dueDate = null;
                $stmtReservation = $pdo->prepare(
                    'SELECT COALESCE(NULLIF(TRIM(r.currency), \'\'), \'MXN\') AS currency, r.check_out_date
                     FROM reservation r
                     JOIN property p ON p.id_property = r.id_property
                     WHERE r.id_reservation = ?
                       AND p.id_company = ?
                       AND r.deleted_at IS NULL
                       AND p.deleted_at IS NULL
                     LIMIT 1'
                );
                $stmtReservation->execute(array($reservationId, $companyId));
                $reservationRow = $stmtReservation->fetch(PDO::FETCH_ASSOC);
                if ($reservationRow) {
                    $currency = isset($reservationRow['currency']) ? (string)$reservationRow['currency'] : 'MXN';
                    $dueDate = isset($reservationRow['check_out_date']) && trim((string)$reservationRow['check_out_date']) !== ''
                        ? (string)$reservationRow['check_out_date']
                        : null;
                }
                $stmtCreatePrincipal = $pdo->prepare(
                    'INSERT INTO folio (
                        id_reservation, folio_name, status, currency, total_cents, balance_cents, due_date,
                        is_active, created_at, created_by, updated_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
                );
                $stmtCreatePrincipal->execute(array(
                    $reservationId,
                    'Hospedaje',
                    'open',
                    $currency !== '' ? $currency : 'MXN',
                    0,
                    0,
                    $dueDate,
                    $actorUserId
                ));
                return (int)$pdo->lastInsertId();
            }

            if ($role === 'services') {
                $currency = 'MXN';
                $dueDate = null;
                if ($rows) {
                    $currency = isset($rows[0]['currency']) ? (string)$rows[0]['currency'] : 'MXN';
                    $dueDate = isset($rows[0]['due_date']) && trim((string)$rows[0]['due_date']) !== ''
                        ? (string)$rows[0]['due_date']
                        : null;
                } else {
                    $stmtReservation = $pdo->prepare(
                        'SELECT COALESCE(NULLIF(TRIM(r.currency), \'\'), \'MXN\') AS currency, r.check_out_date
                         FROM reservation r
                         JOIN property p ON p.id_property = r.id_property
                         WHERE r.id_reservation = ?
                           AND p.id_company = ?
                           AND r.deleted_at IS NULL
                           AND p.deleted_at IS NULL
                         LIMIT 1'
                    );
                    $stmtReservation->execute(array($reservationId, $companyId));
                    $reservationRow = $stmtReservation->fetch(PDO::FETCH_ASSOC);
                    if ($reservationRow) {
                        $currency = isset($reservationRow['currency']) ? (string)$reservationRow['currency'] : 'MXN';
                        $dueDate = isset($reservationRow['check_out_date']) && trim((string)$reservationRow['check_out_date']) !== ''
                            ? (string)$reservationRow['check_out_date']
                            : null;
                    }
                }

                $stmtCreate = $pdo->prepare(
                    'INSERT INTO folio (
                        id_reservation, folio_name, status, currency, total_cents, balance_cents, due_date,
                        is_active, created_at, created_by, updated_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW())'
                );
                $stmtCreate->execute(array(
                    $reservationId,
                    'Servicios',
                    'open',
                    $currency !== '' ? $currency : 'MXN',
                    0,
                    0,
                    $dueDate,
                    $actorUserId
                ));
                return (int)$pdo->lastInsertId();
            }
        } catch (Exception $e) {
            return 0;
        }
        return 0;
    }
}

if (!function_exists('calendar_recalc_derived_tree_for_catalog')) {
    function calendar_recalc_derived_tree_for_catalog($folioId, $reservationId, $catalogId, $serviceDate, $actorUserId)
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

if (!function_exists('calendar_fetch_fixed_children_by_parent')) {
    function calendar_fetch_fixed_children_by_parent($companyCode, $propertyCode, $companyId = 0)
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
                $defaultAmountCents = isset($row['default_amount_cents']) ? (int)$row['default_amount_cents'] : 0;
                $defaultUnitPriceCents = isset($row['default_unit_price_cents']) ? (int)$row['default_unit_price_cents'] : 0;
                $defaultCents = $defaultAmountCents > 0 ? $defaultAmountCents : $defaultUnitPriceCents;
                $map[$parentId][$childId] = array(
                    'id' => $childId,
                    'name' => isset($row['item_name']) ? (string)$row['item_name'] : '',
                    'default_cents' => $defaultCents
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

if (!function_exists('calendar_upsert_fixed_children_tree')) {
    function calendar_upsert_fixed_children_tree(
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
        static $catalogDefaultCentsCache = array();
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
        $resolveCatalogDefaultCents = function ($catalogId) use (&$catalogDefaultCentsCache) {
            $catalogId = (int)$catalogId;
            if ($catalogId <= 0) {
                return 0;
            }
            if (isset($catalogDefaultCentsCache[$catalogId])) {
                return (int)$catalogDefaultCentsCache[$catalogId];
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
                if ($row && is_array($row)) {
                    $defaultAmount = isset($row['default_amount_cents']) ? (int)$row['default_amount_cents'] : 0;
                    $defaultUnit = isset($row['default_unit_price_cents']) ? (int)$row['default_unit_price_cents'] : 0;
                    $defaultCents = $defaultAmount > 0 ? $defaultAmount : $defaultUnit;
                }
            } catch (Exception $e) {
                $defaultCents = 0;
            }
            $catalogDefaultCentsCache[$catalogId] = (int)$defaultCents;
            return (int)$defaultCents;
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

            calendar_upsert_fixed_children_tree(
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

if (!function_exists('calendar_reservation_status_requirements_snapshot')) {
    function calendar_reservation_status_requirements_snapshot($companyCode, $reservationId)
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
            $hasGuest = isset($row['has_guest']) ? ((int)$row['has_guest'] === 1) : false;
            $hasSaleItemCharges = isset($row['has_sale_item_charges']) ? ((int)$row['has_sale_item_charges'] === 1) : false;
            return array(
                'has_guest' => $hasGuest,
                'has_charges' => $hasSaleItemCharges
            );
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('calendar_force_update_reservation_status')) {
    function calendar_force_update_reservation_status($companyCode, $reservationId, $status)
    {
        $companyCode = trim((string)$companyCode);
        $reservationId = (int)$reservationId;
        $status = trim((string)$status);
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

if (!function_exists('calendar_normalize_hex_color')) {
    function calendar_normalize_hex_color($value)
    {
        $hex = strtoupper(trim((string)$value));
        if ($hex === '') {
            return '';
        }
        if (strpos($hex, '#') !== 0) {
            $hex = '#' . $hex;
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            return '';
        }
        return $hex;
    }
}

if (!function_exists('calendar_hex_to_rgba')) {
    function calendar_hex_to_rgba($hex, $alpha)
    {
        $normalized = calendar_normalize_hex_color($hex);
        if ($normalized === '') {
            return '';
        }
        $r = hexdec(substr($normalized, 1, 2));
        $g = hexdec(substr($normalized, 3, 2));
        $b = hexdec(substr($normalized, 5, 2));
        $a = (float)$alpha;
        if ($a < 0) {
            $a = 0;
        } elseif ($a > 1) {
            $a = 1;
        }
        return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . rtrim(rtrim(number_format($a, 2, '.', ''), '0'), '.') . ')';
    }
}

if (!function_exists('calendar_text_color_for_hex')) {
    function calendar_text_color_for_hex($hex)
    {
        $normalized = calendar_normalize_hex_color($hex);
        if ($normalized === '') {
            return '#FFFFFF';
        }
        $r = hexdec(substr($normalized, 1, 2));
        $g = hexdec(substr($normalized, 3, 2));
        $b = hexdec(substr($normalized, 5, 2));
        $luma = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
        return $luma >= 160 ? '#0B1020' : '#FFFFFF';
    }
}

if (!function_exists('calendar_property_tone_style')) {
    function calendar_property_tone_style($hex)
    {
        $normalized = calendar_normalize_hex_color($hex);
        if ($normalized === '') {
            return '';
        }
        $vars = array(
            '--calendar-property-accent:' . $normalized,
            '--calendar-property-accent-strong:' . calendar_hex_to_rgba($normalized, 0.32),
            '--calendar-property-accent-soft:' . calendar_hex_to_rgba($normalized, 0.1),
            '--calendar-property-accent-header:' . calendar_hex_to_rgba($normalized, 0.14),
            '--calendar-property-accent-border:' . calendar_hex_to_rgba($normalized, 0.28),
            '--calendar-property-accent-fill:' . calendar_hex_to_rgba($normalized, 0.06)
        );
        return implode(';', array_filter($vars)) . ';';
    }
}

if (!function_exists('calendar_state_icon_svg')) {
    function calendar_state_icon_svg($iconType)
    {
        switch ((string)$iconType) {
            case 'noshow':
                return '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4.2 3L8 6.8 11.8 3 13 4.2 9.2 8 13 11.8 11.8 13 8 9.2 4.2 13 3 11.8 6.8 8 3 4.2 4.2 3z"/></svg>';
            case 'checkin':
                return '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M1.5 7h8.6L8.2 5.1l1.3-1.3L14 8l-4.5 4.2-1.3-1.3 1.9-1.9H1.5V7z"/></svg>';
            case 'house':
                return '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M1.6 7.3L8 2l6.4 5.3-1.3 1.6L12 8v6H9.3v-3.1H6.7V14H4V8L2.9 8.9 1.6 7.3z"/></svg>';
            case 'checkout':
                return '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2 2h3v2H4v8h1v2H2V2zm4 5h5l-1.8-1.8L10.6 4 15 8l-4.4 4-1.4-1.2L11 9H6V7z"/></svg>';
            case 'alert':
            default:
                return '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M7 3h2l-.4 7H7.4L7 3zm1 11a1.2 1.2 0 110-2.4A1.2 1.2 0 018 14z"/></svg>';
        }
    }
}

if (!function_exists('pms_calendar_format_date')) {
    function pms_calendar_format_date($dateString, $format = 'Y-m-d')
    {
        return calendar_format_date($dateString, $format);
    }
}

if (!function_exists('calendar_render_empty_cell')) {
    function calendar_render_empty_cell($dayInfo, $roomCode, $roomName, $propertyCode, $categoryCode = '', $isMonthDivider = false)
    {
        $dayIndex = isset($dayInfo['day_index']) ? (int)$dayInfo['day_index'] : -1;
        $dateKey = isset($dayInfo['date_key']) ? (string)$dayInfo['date_key'] : '';
        $isToday = isset($dayInfo['is_today']) && (int)$dayInfo['is_today'] === 1;
        $className = 'calendar-cell is-empty' . ($isMonthDivider ? ' is-month-divider' : '') . ($isToday ? ' is-today' : '');
        echo '<td class="' . $className . '"'
            . ' data-day-index="' . (int)$dayIndex . '"'
            . ' data-date="' . htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-room-code="' . htmlspecialchars((string)$roomCode, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-room-name="' . htmlspecialchars((string)$roomName, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-property-code="' . htmlspecialchars((string)$propertyCode, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-category-code="' . htmlspecialchars((string)$categoryCode, ENT_QUOTES, 'UTF-8') . '"'
            . '></td>';
    }
}


function calendar_room_label(array $room)
{
    $code = isset($room['code']) ? (string)$room['code'] : '';
    $name = isset($room['name']) ? (string)$room['name'] : '';
    if ($name === '') {
        return $code;
    }
    return $code . ' - ' . $name;
}

function calendar_rooms_for_property(array $roomsByProperty, $propertyCode)
{
    if ($propertyCode !== '' && isset($roomsByProperty[$propertyCode])) {
        return $roomsByProperty[$propertyCode];
    }
    return array();
}

function calendar_find_room(array $roomsByProperty, $propertyCode, $roomCode)
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

function calendar_sort_rooms(array $rooms, $orderMode)
{
    $mode = $orderMode === 'category' ? 'category' : 'room';
    usort($rooms, function ($a, $b) use ($mode) {
        $aRoomOrder = isset($a['room_order_index']) ? (int)$a['room_order_index'] : (isset($a['order_index']) ? (int)$a['order_index'] : 0);
        $bRoomOrder = isset($b['room_order_index']) ? (int)$b['room_order_index'] : (isset($b['order_index']) ? (int)$b['order_index'] : 0);
        $aCode = isset($a['room_code']) ? (string)$a['room_code'] : (isset($a['code']) ? (string)$a['code'] : '');
        $bCode = isset($b['room_code']) ? (string)$b['room_code'] : (isset($b['code']) ? (string)$b['code'] : '');

        if ($mode === 'category') {
            $aCatOrder = isset($a['category_order_index']) ? (int)$a['category_order_index'] : 0;
            $bCatOrder = isset($b['category_order_index']) ? (int)$b['category_order_index'] : 0;
            if ($aCatOrder !== $bCatOrder) {
                return $aCatOrder <=> $bCatOrder;
            }
            $aCatName = isset($a['category_name']) ? (string)$a['category_name'] : '';
            $bCatName = isset($b['category_name']) ? (string)$b['category_name'] : '';
            if ($aCatName !== $bCatName) {
                return strcmp($aCatName, $bCatName);
            }
        }

        if ($aRoomOrder !== $bRoomOrder) {
            return $aRoomOrder <=> $bRoomOrder;
        }

        return strcmp($aCode, $bCode);
    });

    return $rooms;
}

$defaultProperty = ''; // Default: todas las propiedades
$calendarPrefKey = 'pms_calendar_preferences';
$calendarUserScope = (string)$companyCode . ':' . (string)(isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : 0);
$calendarPrefs = array();
if (isset($_SESSION[$calendarPrefKey]) && is_array($_SESSION[$calendarPrefKey]) && isset($_SESSION[$calendarPrefKey][$calendarUserScope]) && is_array($_SESSION[$calendarPrefKey][$calendarUserScope])) {
    $calendarPrefs = $_SESSION[$calendarPrefKey][$calendarUserScope];
}

$hasPropertyPost = array_key_exists('property_code', $_POST);
$hasPropertyGet = array_key_exists('property_code', $_GET);
$propertyCode = '';
if ($hasPropertyPost) {
    $propertyCode = strtoupper(trim((string)$_POST['property_code']));
} elseif ($hasPropertyGet) {
    $propertyCode = strtoupper(trim((string)$_GET['property_code']));
} else {
    $propertyCode = $defaultProperty;
}
if ($propertyCode !== '' && !isset($propertiesByCode[$propertyCode])) {
    $propertyCode = $defaultProperty;
}

$viewModeKey = '';

$orderOptions = array(
    'room' => 'Habitaciones',
    'category' => 'Categorias',
    'category_availability' => 'Categorias (disponibilidad)'
);
$orderMode = '';
if (array_key_exists('order_mode', $_POST)) {
    $orderMode = (string)$_POST['order_mode'];
} elseif (array_key_exists('order_mode', $_GET)) {
    $orderMode = (string)$_GET['order_mode'];
} elseif (isset($calendarPrefs['order_mode'])) {
    $orderMode = (string)$calendarPrefs['order_mode'];
} else {
    $orderMode = 'room';
}
if (!isset($orderOptions[$orderMode])) {
    $orderMode = 'room';
}

$calendarFocusReservationId = isset($_POST['calendar_focus_reservation_id'])
    ? (int)$_POST['calendar_focus_reservation_id']
    : (isset($_GET['calendar_focus_reservation_id']) ? (int)$_GET['calendar_focus_reservation_id'] : 0);
$calendarFocusPropertyCode = isset($_POST['calendar_focus_property_code'])
    ? strtoupper(trim((string)$_POST['calendar_focus_property_code']))
    : (isset($_GET['calendar_focus_property_code']) ? strtoupper(trim((string)$_GET['calendar_focus_property_code'])) : '');
$calendarFocusRoomCode = isset($_POST['calendar_focus_room_code'])
    ? strtoupper(trim((string)$_POST['calendar_focus_room_code']))
    : (isset($_GET['calendar_focus_room_code']) ? strtoupper(trim((string)$_GET['calendar_focus_room_code'])) : '');
$calendarFocusCheckIn = isset($_POST['calendar_focus_check_in'])
    ? trim((string)$_POST['calendar_focus_check_in'])
    : (isset($_GET['calendar_focus_check_in']) ? trim((string)$_GET['calendar_focus_check_in']) : '');
if ($calendarFocusPropertyCode !== '' && !isset($propertiesByCode[$calendarFocusPropertyCode])) {
    $calendarFocusPropertyCode = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $calendarFocusCheckIn)) {
    $calendarFocusCheckIn = '';
}
if ($propertyCode !== '' && $calendarFocusPropertyCode !== '' && $propertyCode !== $calendarFocusPropertyCode) {
    $propertyCode = $calendarFocusPropertyCode;
}

$rawBaseDate = isset($_POST['start_date']) && $_POST['start_date'] !== ''
    ? (string)$_POST['start_date']
    : (isset($_GET['start_date']) && $_GET['start_date'] !== '' ? (string)$_GET['start_date'] : date('Y-m-d'));
$baseDateObj = DateTime::createFromFormat('Y-m-d', $rawBaseDate);
if (!$baseDateObj) {
    $baseDateObj = new DateTime('today');
}
$navAction = isset($_POST['nav_action']) ? (string)$_POST['nav_action'] : '';
if ($navAction === 'today') {
    $baseDateObj = new DateTime('today');
}
if ($calendarFocusCheckIn !== '') {
    $focusDateObj = DateTime::createFromFormat('Y-m-d', $calendarFocusCheckIn);
    if ($focusDateObj) {
        $visibleStartObj = clone $baseDateObj;
        $visibleStartObj->modify('-21 days');
        $visibleEndObj = clone $baseDateObj;
        $visibleEndObj->modify('+2 months');
        if ($focusDateObj < $visibleStartObj || $focusDateObj > $visibleEndObj) {
            $baseDateObj = clone $focusDateObj;
        }
    }
}
$baseDate = $baseDateObj->format('Y-m-d');
if (!isset($_SESSION[$calendarPrefKey]) || !is_array($_SESSION[$calendarPrefKey])) {
    $_SESSION[$calendarPrefKey] = array();
}
$_SESSION[$calendarPrefKey][$calendarUserScope] = array(
    'property_code' => $propertyCode,
    'order_mode' => $orderMode
);
$startDateObj = clone $baseDateObj;
$startDateObj->modify('-21 days');
$endDateObj = clone $baseDateObj;
$endDateObj->modify('+2 months');
$startDate = $startDateObj->format('Y-m-d');
$rangeDays = max(1, (int)$startDateObj->diff($endDateObj)->days + 1);

$calendarAction = isset($_POST['calendar_action']) ? (string)$_POST['calendar_action'] : '';

if ($calendarAction !== '') {
    if (in_array($calendarAction, array('prepare_new_block', 'refresh_block'), true)) {
        // no-op actions
    } elseif (in_array($calendarAction, array('create_block', 'bulk_create_blocks', 'bulk_delete_blocks', 'update_block'), true)) {
        pms_require_permission('calendar.manage_block');
    } elseif ($calendarAction === 'quick_reservation') {
        pms_require_permission('calendar.create_hold');
        if (isset($_POST['quick_mark_paid']) && (string)$_POST['quick_mark_paid'] === '1') {
            pms_require_permission('reservations.post_payment');
        }
    } elseif ($calendarAction === 'create_reservation_payment') {
        pms_require_permission('calendar.register_payment');
    } elseif ($calendarAction === 'create_reservation_service') {
        pms_require_permission('reservations.post_charge');
    } elseif ($calendarAction === 'move_reservation') {
        pms_require_permission('calendar.move_reservation');
    } elseif (in_array($calendarAction, array('advance_reservation_status', 'mark_reservation_no_show', 'cancel_reservations'), true)) {
        pms_require_permission('reservations.status_change');
    } elseif ($calendarAction === 'rateplan_override_quick') {
        pms_require_permission('rateplans.edit');
    }
}

$newBlockMessage = null;
$newBlockError = null;
$blockUpdateMessages = array();
$blockUpdateErrors = array();
$bulkBlockMessages = array();
$bulkBlockErrors = array();
$advanceMessage = null;
$advanceError = null;
$paymentApplyMessage = null;
$paymentApplyError = null;
$serviceApplyMessage = null;
$serviceApplyError = null;
$moveReservationMessage = null;
$moveReservationError = null;
$cancelMessages = array();
$cancelErrors = array();
$rateplanMessages = array();
$rateplanErrors = array();
$quickReservationMessage = null;
$quickReservationError = null;
$quickReservationActionReservationId = 0;
$quickReservationActionFolioId = 0;
$clearDirtyTargets = array();

$newBlockValues = array(
    'property_code' => isset($_POST['block_property_code']) ? strtoupper((string)$_POST['block_property_code']) : $propertyCode,
    'room_code'     => isset($_POST['block_room_code']) ? strtoupper((string)$_POST['block_room_code']) : '',
    'start_date'    => isset($_POST['block_start_date']) ? (string)$_POST['block_start_date'] : $baseDate,
    'end_date'      => isset($_POST['block_end_date']) ? (string)$_POST['block_end_date'] : $baseDate,
    'notes'         => isset($_POST['block_notes']) ? (string)$_POST['block_notes'] : ''
);

if ($calendarAction === 'prepare_new_block') {
    $calendarAction = '';
} elseif ($calendarAction === 'refresh_block') {
    $calendarAction = '';
} elseif ($calendarAction === 'create_block') {
    $blockProperty = strtoupper(trim($newBlockValues['property_code']));
    $blockRoom = strtoupper(trim($newBlockValues['room_code']));
    $blockStart = (string)$newBlockValues['start_date'];
    $blockEnd = (string)$newBlockValues['end_date'];
    $blockNotes = trim($newBlockValues['notes']);
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
    if ($blockProperty !== '') {
        pms_require_property_access($blockProperty);
    }

    if ($blockProperty === '' || $blockRoom === '' || $blockStart === '' || $blockEnd === '') {
        $newBlockError = 'Completa los datos del bloqueo.';
    } else {
        try {
            $sets = pms_call_procedure('sp_create_room_block', array(
                $blockProperty,
                $blockRoom,
                $blockStart,
                $blockEnd,
                $blockNotes,
                $actorUser
            ));
            $blockRow = isset($sets[0][0]) ? $sets[0][0] : null;
            if ($blockRow && isset($blockRow['id_room_block'])) {
                $newBlockMessage = 'Bloqueo registrado correctamente.';
                $newBlockValues['room_code'] = '';
                $newBlockValues['notes'] = '';
                $_POST[$moduleKey . '_subtab_action'] = 'open';
                $_POST[$moduleKey . '_subtab_target'] = 'block:' . (int)$blockRow['id_room_block'];
            } else {
                $newBlockError = 'No se pudo registrar el bloqueo.';
            }
        } catch (Exception $e) {
            $newBlockError = $e->getMessage();
        }
    }
} elseif ($calendarAction === 'quick_reservation') {
    $quickProperty = isset($_POST['quick_property_code']) ? strtoupper(trim((string)$_POST['quick_property_code'])) : '';
    $quickRoom = isset($_POST['quick_room_code']) ? strtoupper(trim((string)$_POST['quick_room_code'])) : '';
    $quickCheckIn = isset($_POST['quick_check_in']) ? (string)$_POST['quick_check_in'] : '';
    $quickCheckOut = isset($_POST['quick_check_out']) ? (string)$_POST['quick_check_out'] : '';
    $quickGuestFirstName = isset($_POST['quick_guest_first_name']) ? trim((string)$_POST['quick_guest_first_name']) : '';
    $quickGuestLastName = isset($_POST['quick_guest_last_name']) ? trim((string)$_POST['quick_guest_last_name']) : '';
    $quickGuestMaidenName = isset($_POST['quick_guest_maiden_name']) ? trim((string)$_POST['quick_guest_maiden_name']) : '';
    $quickNotes = isset($_POST['quick_notes']) ? trim((string)$_POST['quick_notes']) : '';
    $quickPriceRaw = isset($_POST['quick_price']) ? trim((string)$_POST['quick_price']) : '';
    $quickSourceId = isset($_POST['quick_source_id']) ? (int)$_POST['quick_source_id'] : 0;
    $quickSourceRaw = isset($_POST['quick_source']) ? trim((string)$_POST['quick_source']) : '';
    $quickOtaAccountId = isset($_POST['quick_ota_account_id']) ? (int)$_POST['quick_ota_account_id'] : 0;
    $quickMarkPaid = isset($_POST['quick_mark_paid']) && (string)$_POST['quick_mark_paid'] === '1';
    $quickPaymentMethodId = isset($_POST['quick_payment_method']) ? (int)$_POST['quick_payment_method'] : 0;
    $quickPaymentDate = isset($_POST['quick_payment_date']) ? trim((string)$_POST['quick_payment_date']) : '';
    $quickPaymentReference = isset($_POST['quick_payment_reference']) ? trim((string)$_POST['quick_payment_reference']) : '';
    $quickPriceCents = 0;
    if ($quickPriceRaw !== '') {
        $quickPriceCents = calendar_to_cents($quickPriceRaw);
        if ($quickPriceCents < 0) {
            $quickPriceCents = 0;
        }
    }
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
    if ($quickProperty !== '') {
        pms_require_property_access($quickProperty);
    }

    if ($quickProperty === '' || $quickRoom === '' || $quickCheckIn === '' || $quickCheckOut === '') {
        $quickReservationError = 'Completa propiedad, habitacion y fechas para la reserva rapida.';
    } elseif ($quickGuestFirstName === '') {
        $quickReservationError = 'Captura el nombre del huesped para la reserva rapida.';
    } else {
        try {
            $sets = pms_call_create_reservation_hold(
                $quickProperty,
                $quickRoom,
                $quickCheckIn,
                $quickCheckOut,
                $quickPriceCents,
                $quickNotes,
                $actorUser
            );
            $reservationRow = isset($sets[0][0]) ? $sets[0][0] : null;
            $reservationId = $reservationRow && isset($reservationRow['id_reservation'])
                ? (int)$reservationRow['id_reservation']
                : 0;
            if ($reservationId <= 0) {
                $quickReservationError = 'No se pudo crear la reserva rapida.';
            } else {
                $quickOtaOptions = function_exists('pms_ota_options_for_property')
                    ? pms_ota_options_for_property($otaAccountsByProperty, $quickProperty, true)
                    : array();
                $quickOtaIds = array();
                foreach ($quickOtaOptions as $otaRow) {
                    $tmpOtaId = isset($otaRow['id_ota_account']) ? (int)$otaRow['id_ota_account'] : 0;
                    $quickOtaIds[$tmpOtaId] = true;
                }
                if (!isset($quickOtaIds[$quickOtaAccountId])) {
                    $quickOtaAccountId = 0;
                }

                $quickSourceOptions = function_exists('pms_reservation_source_options_for_property')
                    ? pms_reservation_source_options_for_property($reservationSourcesByProperty, $quickProperty, true)
                    : array(array(
                        'id_reservation_source' => 0,
                        'source_name' => 'Directo'
                    ));
                $quickSourceIds = array();
                foreach ($quickSourceOptions as $sourceRow) {
                    $tmpSourceId = isset($sourceRow['id_reservation_source']) ? (int)$sourceRow['id_reservation_source'] : 0;
                    if ($tmpSourceId > 0) {
                        $quickSourceIds[$tmpSourceId] = true;
                    }
                }
                if ($quickSourceId > 0 && !isset($quickSourceIds[$quickSourceId])) {
                    $quickSourceId = 0;
                }
                if ($quickSourceId <= 0 && !empty($quickSourceOptions)) {
                    $quickSourceId = isset($quickSourceOptions[0]['id_reservation_source'])
                        ? (int)$quickSourceOptions[0]['id_reservation_source']
                        : 0;
                }

                $quickSourceInput = null;
                if ($quickOtaAccountId <= 0) {
                    if ($quickSourceId > 0) {
                        $quickSourceInput = (string)$quickSourceId;
                    } elseif ($quickSourceRaw !== '') {
                        $quickSourceInput = $quickSourceRaw;
                    } elseif (!empty($quickSourceOptions)) {
                        $firstSourceName = trim((string)(isset($quickSourceOptions[0]['source_name']) ? $quickSourceOptions[0]['source_name'] : ''));
                        $quickSourceInput = $firstSourceName !== '' ? $firstSourceName : 'Directo';
                    } else {
                        $quickSourceInput = 'Directo';
                    }
                }
                pms_call_procedure('sp_reservation_update', array(
                    $companyCode,
                    $reservationId,
                    null,
                    $quickSourceInput,
                    $quickOtaAccountId > 0 ? $quickOtaAccountId : 0,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $actorUser
                ));
                $db = pms_get_connection();
                if ($quickPriceCents !== null && $quickPriceCents > 0) {
                    $quickLodgingCatalogId = 0;

                    if ($quickOtaAccountId > 0) {
                        $stmtQuickOtaLodging = $db->prepare(
                            'SELECT oalc.id_line_item_catalog
                               FROM ota_account_lodging_catalog oalc
                               JOIN ota_account oa
                                 ON oa.id_ota_account = oalc.id_ota_account
                                AND oa.id_company = ?
                                AND oa.deleted_at IS NULL
                                AND COALESCE(oa.is_active, 1) = 1
                               JOIN line_item_catalog lic
                                 ON lic.id_line_item_catalog = oalc.id_line_item_catalog
                                AND lic.deleted_at IS NULL
                                AND COALESCE(lic.is_active, 1) = 1
                                AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) = \'sale_item\'
                              WHERE oalc.id_ota_account = ?
                                AND oalc.deleted_at IS NULL
                                AND COALESCE(oalc.is_active, 1) = 1
                              ORDER BY oalc.sort_order, oalc.id_ota_account_lodging_catalog
                              LIMIT 1'
                        );
                        $stmtQuickOtaLodging->execute(array($companyId, $quickOtaAccountId));
                        $quickLodgingCatalogId = (int)$stmtQuickOtaLodging->fetchColumn();
                    }

                    if ($quickLodgingCatalogId <= 0 && $quickSourceId > 0
                        && function_exists('pms_reservation_source_has_column')
                        && pms_reservation_source_has_column($db, 'id_lodging_catalog')
                    ) {
                        $stmtQuickSourceLodging = $db->prepare(
                            'SELECT rsc.id_lodging_catalog
                               FROM reservation_source_catalog rsc
                               JOIN line_item_catalog lic
                                 ON lic.id_line_item_catalog = rsc.id_lodging_catalog
                                AND lic.deleted_at IS NULL
                                AND COALESCE(lic.is_active, 1) = 1
                                AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) = \'sale_item\'
                              WHERE rsc.id_reservation_source = ?
                                AND rsc.id_company = ?
                                AND rsc.deleted_at IS NULL
                                AND COALESCE(rsc.is_active, 1) = 1
                              LIMIT 1'
                        );
                        $stmtQuickSourceLodging->execute(array($quickSourceId, $companyId));
                        $quickLodgingCatalogId = (int)$stmtQuickSourceLodging->fetchColumn();
                    }

                    if ($quickLodgingCatalogId <= 0) {
                        $stmtQuickDefaultLodging = $db->prepare(
                            'SELECT pslc.id_sale_item_catalog
                               FROM pms_settings_lodging_catalog pslc
                               LEFT JOIN property pr
                                 ON pr.id_property = pslc.id_property
                                AND pr.deleted_at IS NULL
                               JOIN line_item_catalog lic
                                 ON lic.id_line_item_catalog = pslc.id_sale_item_catalog
                                AND lic.deleted_at IS NULL
                                AND COALESCE(lic.is_active, 1) = 1
                                AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) = \'sale_item\'
                              WHERE pslc.id_company = ?
                                AND pslc.deleted_at IS NULL
                                AND COALESCE(pslc.is_active, 1) = 1
                                AND (UPPER(TRIM(COALESCE(pr.code, \'\'))) = ? OR pslc.id_property IS NULL)
                              ORDER BY CASE WHEN pslc.id_property IS NULL THEN 1 ELSE 0 END,
                                       pslc.id_setting_lodging
                              LIMIT 1'
                        );
                        $stmtQuickDefaultLodging->execute(array($companyId, $quickProperty));
                        $quickLodgingCatalogId = (int)$stmtQuickDefaultLodging->fetchColumn();
                    }

                    if ($quickLodgingCatalogId <= 0) {
                        throw new Exception('No hay concepto de hospedaje configurado para el origen seleccionado.');
                    }

                    $quickLodgingFolioId = calendar_find_open_folio_id_by_role($companyId, $reservationId, 'lodging', $actorUser);
                    if ($quickLodgingFolioId <= 0) {
                        throw new Exception('No se encontro un folio abierto de hospedaje para la reserva rapida.');
                    }

                    pms_call_procedure('sp_sale_item_upsert', array(
                        'create',
                        0,
                        $quickLodgingFolioId,
                        $reservationId,
                        $quickLodgingCatalogId,
                        null,
                        $quickCheckIn,
                        1,
                        $quickPriceCents,
                        0,
                        'posted',
                        $actorUser
                    ));

                    $fixedChildrenByParentMap = calendar_fetch_fixed_children_by_parent($companyCode, $quickProperty, $companyId);
                    if (!empty($fixedChildrenByParentMap)) {
                        $fixedPath = array();
                        calendar_upsert_fixed_children_tree(
                            $reservationId,
                            $quickLodgingFolioId,
                            $quickCheckIn,
                            $actorUser,
                            $quickLodgingCatalogId,
                            $fixedChildrenByParentMap,
                            $fixedPath,
                            0
                        );
                    }

                    calendar_recalc_derived_tree_for_catalog(
                        $quickLodgingFolioId,
                        $reservationId,
                        $quickLodgingCatalogId,
                        $quickCheckIn,
                        $actorUser
                    );
                    try {
                        pms_call_procedure('sp_folio_recalc', array($quickLodgingFolioId));
                    } catch (Exception $e) {
                    }

                    if ($quickMarkPaid && $quickPriceCents > 0) {
                        $paymentConceptOptions = calendar_payment_catalogs_for_reservation(
                            $paymentCatalogsByProperty,
                            $quickProperty,
                            $companyId,
                            $reservationId
                        );
                        $paymentConceptMap = array();
                        foreach ($paymentConceptOptions as $opt) {
                            $optId = isset($opt['id_payment_catalog']) ? (int)$opt['id_payment_catalog'] : 0;
                            if ($optId > 0) {
                                $paymentConceptMap[$optId] = true;
                            }
                        }
                        $selectedPaymentCatalogId = $quickPaymentMethodId > 0 ? $quickPaymentMethodId : 0;
                        if ($selectedPaymentCatalogId > 0 && !isset($paymentConceptMap[$selectedPaymentCatalogId])) {
                            $selectedPaymentCatalogId = 0;
                        }
                        if ($selectedPaymentCatalogId <= 0 && !empty($paymentConceptOptions)) {
                            $selectedPaymentCatalogId = isset($paymentConceptOptions[0]['id_payment_catalog'])
                                ? (int)$paymentConceptOptions[0]['id_payment_catalog']
                                : 0;
                        }
                        if ($selectedPaymentCatalogId <= 0) {
                            throw new Exception('No hay concepto de pago configurado para registrar la reserva como pagada.');
                        }

                        if ($quickPaymentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $quickPaymentDate)) {
                            $quickPaymentDate = date('Y-m-d');
                        }

                        try {
                            pms_call_procedure('sp_folio_recalc', array($quickLodgingFolioId));
                        } catch (Exception $ignoreRecalc) {
                        }
                        $stmtQuickBalance = $db->prepare(
                            'SELECT COALESCE(balance_cents, 0)
                               FROM folio
                              WHERE id_folio = ?
                                AND deleted_at IS NULL
                              LIMIT 1'
                        );
                        $stmtQuickBalance->execute(array($quickLodgingFolioId));
                        $quickBalanceCents = (int)$stmtQuickBalance->fetchColumn();
                        if ($quickBalanceCents <= 0) {
                            throw new Exception('No hay saldo pendiente para registrar la reserva como pagada.');
                        }

                        $quickPaymentMethodName = calendar_payment_method_name_by_id($paymentCatalogsById, $selectedPaymentCatalogId);
                        if ($quickPaymentMethodName === '') {
                            $quickPaymentMethodName = 'Concepto #' . $selectedPaymentCatalogId;
                        }

                        $quickPaymentSets = pms_call_procedure('sp_sale_item_upsert', array(
                            'create',
                            0,
                            $quickLodgingFolioId,
                            $reservationId,
                            $selectedPaymentCatalogId,
                            null,
                            $quickPaymentDate,
                            1,
                            $quickBalanceCents,
                            0,
                            'captured',
                            $actorUser
                        ));
                        $quickPaymentId = calendar_extract_line_item_id_from_result_sets($quickPaymentSets);
                        if ($quickPaymentId > 0) {
                            try {
                                pms_call_procedure('sp_line_item_payment_meta_upsert', array(
                                    $quickPaymentId,
                                    $quickPaymentMethodName !== '' ? $quickPaymentMethodName : null,
                                    $quickPaymentReference !== '' ? $quickPaymentReference : null,
                                    'captured',
                                    $actorUser
                                ));
                            } catch (Exception $ignoreMeta) {
                            }
                        }
                        if (!empty($fixedChildrenByParentMap)) {
                            $fixedPath = array();
                            calendar_upsert_fixed_children_tree(
                                $reservationId,
                                $quickLodgingFolioId,
                                $quickPaymentDate,
                                $actorUser,
                                $selectedPaymentCatalogId,
                                $fixedChildrenByParentMap,
                                $fixedPath,
                                0
                            );
                        }
                        calendar_recalc_derived_tree_for_catalog(
                            $quickLodgingFolioId,
                            $reservationId,
                            $selectedPaymentCatalogId,
                            $quickPaymentDate,
                            $actorUser
                        );
                        try {
                            pms_call_procedure('sp_folio_recalc', array($quickLodgingFolioId));
                        } catch (Exception $ignoreRecalcEnd) {
                        }
                    }
                }

                $quickGuestSets = pms_call_procedure('sp_guest_upsert', array(
                    null,
                    $quickGuestFirstName,
                    $quickGuestLastName !== '' ? $quickGuestLastName : null,
                    $quickGuestMaidenName !== '' ? $quickGuestMaidenName : null,
                    null,
                    null,
                    0,
                    0,
                    null
                ));
                $quickGuestRow = isset($quickGuestSets[0][0]) ? $quickGuestSets[0][0] : null;
                $quickGuestId = $quickGuestRow && isset($quickGuestRow['id_guest'])
                    ? (int)$quickGuestRow['id_guest']
                    : 0;
                if ($quickGuestId <= 0) {
                    throw new Exception('No se pudo registrar el huesped para la reserva rapida.');
                }

                $stmtGuestLink = $db->prepare(
                    'UPDATE reservation
                        SET id_guest = ?,
                            updated_at = NOW()
                      WHERE id_reservation = ?'
                );
                $stmtGuestLink->execute(array($quickGuestId, $reservationId));

                if (trim($quickNotes) !== '') {
                    pms_call_procedure('sp_reservation_note_upsert', array(
                        'create',
                        0,
                        $reservationId,
                        'internal',
                        $quickNotes,
                        1,
                        $companyCode,
                        $actorUser
                    ));
                }
                if ($quickPriceCents > 0) {
                    pms_call_procedure('sp_reservation_update', array(
                        $companyCode,
                        $reservationId,
                        'confirmado',
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        $actorUser
                    ));
                    $quickStatusNormalized = '';
                    $stmtQuickStatus = $db->prepare(
                        'SELECT LOWER(TRIM(COALESCE(status, \'\'))) AS status_normalized
                           FROM reservation
                          WHERE id_reservation = ?
                          LIMIT 1'
                    );
                    $stmtQuickStatus->execute(array($reservationId));
                    $quickStatusRaw = (string)$stmtQuickStatus->fetchColumn();
                    if ($quickStatusRaw === 'confirmada') {
                        $quickStatusRaw = 'confirmado';
                    } elseif ($quickStatusRaw === 'encasa') {
                        $quickStatusRaw = 'en casa';
                    }
                    $quickStatusNormalized = $quickStatusRaw;
                    if ($quickStatusNormalized !== 'confirmado') {
                        calendar_force_update_reservation_status($companyCode, $reservationId, 'confirmado');
                        $stmtQuickStatus->execute(array($reservationId));
                        $quickStatusRaw = (string)$stmtQuickStatus->fetchColumn();
                        if ($quickStatusRaw === 'confirmada') {
                            $quickStatusRaw = 'confirmado';
                        } elseif ($quickStatusRaw === 'encasa') {
                            $quickStatusRaw = 'en casa';
                        }
                        $quickStatusNormalized = $quickStatusRaw;
                    }
                    if ($quickStatusNormalized !== 'confirmado') {
                        throw new Exception('La reserva rapida se creo, pero no se pudo confirmar automaticamente.');
                    }
                }
                calendar_find_open_folio_id_by_role($companyId, $reservationId, 'lodging', $actorUser);
                calendar_find_open_folio_id_by_role($companyId, $reservationId, 'services', $actorUser);
                $calendarFocusReservationId = $reservationId;
                $calendarFocusPropertyCode = $quickProperty;
                $calendarFocusRoomCode = $quickRoom;
                $calendarFocusCheckIn = preg_match('/^\d{4}-\d{2}-\d{2}$/', $quickCheckIn) ? $quickCheckIn : '';
                if ($quickPriceCents > 0) {
                    $quickReservationMessage = 'Reserva rapida creada correctamente.';
                } else {
                    $quickReservationMessage = 'Reserva rapida creada sin precio. Agrega el hospedaje para confirmar la reservacion.';
                    $quickReservationActionReservationId = $reservationId;
                    $quickReservationActionFolioId = 0;
                }
            }
        } catch (Exception $e) {
            $quickReservationError = $e->getMessage();
        }
    }
} elseif ($calendarAction === 'create_reservation_payment') {
    $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $paymentFolioId = isset($_POST['payment_folio_id']) ? (int)$_POST['payment_folio_id'] : 0;
    $methodId = isset($_POST['payment_method']) ? (int)$_POST['payment_method'] : 0;
    $amountRaw = isset($_POST['payment_amount']) ? trim((string)$_POST['payment_amount']) : '';
    $reference = isset($_POST['payment_reference']) ? trim((string)$_POST['payment_reference']) : '';
    $serviceDate = isset($_POST['payment_service_date']) ? trim((string)$_POST['payment_service_date']) : '';
    $transferRemaining = isset($_POST['payment_transfer_remaining']) && (string)$_POST['payment_transfer_remaining'] === '1';
    $transferTargetFolioId = isset($_POST['payment_transfer_target_folio_id']) ? (int)$_POST['payment_transfer_target_folio_id'] : 0;
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

    if ($reservationId <= 0) {
        $paymentApplyError = 'Selecciona una reservacion valida.';
    } else {
        try {
            $db = pms_get_connection();
            $stmtReservation = $db->prepare(
                'SELECT r.id_reservation, p.code AS property_code
                   FROM reservation r
                   JOIN property p ON p.id_property = r.id_property
                  WHERE r.id_reservation = ?
                    AND p.id_company = ?
                    AND r.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                  LIMIT 1'
            );
            $stmtReservation->execute(array($reservationId, $companyId));
            $reservationRow = $stmtReservation->fetch(PDO::FETCH_ASSOC);

            if (!$reservationRow) {
                throw new Exception('Selecciona una reservacion valida.');
            }

            $propertyCodeForReservation = strtoupper(trim((string)(isset($reservationRow['property_code']) ? $reservationRow['property_code'] : '')));
            if ($propertyCodeForReservation !== '') {
                pms_require_property_access($propertyCodeForReservation);
            }
            $folioId = 0;
            if ($paymentFolioId > 0) {
                $stmtSelectedFolio = $db->prepare(
                    'SELECT f.id_folio
                       FROM folio f
                       JOIN reservation r ON r.id_reservation = f.id_reservation
                       JOIN property p ON p.id_property = r.id_property
                      WHERE f.id_folio = ?
                        AND f.id_reservation = ?
                        AND p.id_company = ?
                        AND f.deleted_at IS NULL
                        AND COALESCE(f.is_active, 1) = 1
                        AND COALESCE(f.status, \'open\') = \'open\'
                      LIMIT 1'
                );
                $stmtSelectedFolio->execute(array($paymentFolioId, $reservationId, $companyId));
                $selectedFolioId = $stmtSelectedFolio->fetchColumn();
                $folioId = $selectedFolioId !== false ? (int)$selectedFolioId : 0;
                if ($folioId <= 0) {
                    throw new Exception('Selecciona un folio valido para registrar el pago.');
                }
            } else {
                $folioId = calendar_find_open_folio_id_by_role($companyId, $reservationId, 'lodging', $actorUser);
                if ($folioId <= 0) {
                    throw new Exception('No se encontro un folio abierto para registrar el pago.');
                }
            }

            $amountCents = calendar_to_cents($amountRaw);
            if ($amountCents <= 0) {
                throw new Exception('El monto del pago debe ser mayor a 0.');
            }

            $paymentConceptOptions = calendar_payment_catalogs_for_reservation(
                $paymentCatalogsByProperty,
                $propertyCodeForReservation,
                $companyId,
                $reservationId
            );
            $paymentConceptMap = array();
            foreach ($paymentConceptOptions as $opt) {
                $optId = isset($opt['id_payment_catalog']) ? (int)$opt['id_payment_catalog'] : 0;
                if ($optId > 0) {
                    $paymentConceptMap[$optId] = true;
                }
            }

            $selectedPaymentCatalogId = $methodId > 0 ? $methodId : 0;
            if ($selectedPaymentCatalogId > 0 && !isset($paymentConceptMap[$selectedPaymentCatalogId])) {
                $selectedPaymentCatalogId = 0;
            }
            if ($selectedPaymentCatalogId <= 0 && !empty($paymentConceptOptions)) {
                $selectedPaymentCatalogId = isset($paymentConceptOptions[0]['id_payment_catalog'])
                    ? (int)$paymentConceptOptions[0]['id_payment_catalog']
                    : 0;
            }
            if ($selectedPaymentCatalogId <= 0) {
                throw new Exception('No hay concepto de pago configurado para esta propiedad.');
            }

            if ($serviceDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate)) {
                $serviceDate = date('Y-m-d');
            }

            $paymentMethodName = calendar_payment_method_name_by_id($paymentCatalogsById, $selectedPaymentCatalogId);
            if ($paymentMethodName === '') {
                $paymentMethodName = 'Concepto #' . $selectedPaymentCatalogId;
            }
            $fixedChildrenByParentMap = calendar_fetch_fixed_children_by_parent($companyCode, $propertyCodeForReservation, $companyId);

            $transferCtx = calendar_resolve_payment_transfer_target(
                $companyId,
                $reservationId,
                $folioId,
                $transferTargetFolioId
            );
            $sourceBalanceCents = isset($transferCtx['source_balance_cents']) ? max(0, (int)$transferCtx['source_balance_cents']) : 0;
            $remainingCents = max(0, $amountCents - $sourceBalanceCents);
            $targetFolioForTransfer = isset($transferCtx['target_folio_id']) ? (int)$transferCtx['target_folio_id'] : 0;
            $targetPendingCents = isset($transferCtx['target_balance_cents']) ? max(0, (int)$transferCtx['target_balance_cents']) : 0;
            $splitApplied = false;

            $createPaymentInFolio = function ($targetFolioId, $targetAmountCents) use ($reservationId, $selectedPaymentCatalogId, $serviceDate, $actorUser, $paymentMethodName, $reference, $db, $fixedChildrenByParentMap) {
                $createSetsInner = pms_call_procedure('sp_sale_item_upsert', array(
                    'create',
                    0,
                    $targetFolioId,
                    $reservationId,
                    $selectedPaymentCatalogId,
                    null,
                    $serviceDate,
                    1,
                    $targetAmountCents,
                    0,
                    'captured',
                    $actorUser
                ));
                $paymentIdInner = calendar_extract_line_item_id_from_result_sets($createSetsInner);
                if ($paymentIdInner <= 0) {
                    throw new Exception('No se pudo determinar el pago creado.');
                }
                try {
                    pms_call_procedure('sp_line_item_payment_meta_upsert', array(
                        $paymentIdInner,
                        $paymentMethodName !== '' ? $paymentMethodName : null,
                        $reference !== '' ? $reference : null,
                        'captured',
                        $actorUser
                    ));
                } catch (Exception $e) {
                    try {
                        $stmtMetaFallbackInner = $db->prepare(
                            'UPDATE line_item
                                SET method = ?,
                                    reference = ?,
                                    status = ?,
                                    updated_at = NOW()
                              WHERE id_line_item = ?
                                AND item_type = \'payment\'
                                AND deleted_at IS NULL'
                        );
                        $stmtMetaFallbackInner->execute(array(
                            $paymentMethodName !== '' ? $paymentMethodName : null,
                            $reference !== '' ? $reference : null,
                            'captured',
                            $paymentIdInner
                        ));
                    } catch (Exception $inner) {
                    }
                }
                if (!empty($fixedChildrenByParentMap)) {
                    $fixedPath = array();
                    calendar_upsert_fixed_children_tree(
                        $reservationId,
                        $targetFolioId,
                        $serviceDate,
                        $actorUser,
                        $selectedPaymentCatalogId,
                        $fixedChildrenByParentMap,
                        $fixedPath,
                        0
                    );
                }
                calendar_recalc_derived_tree_for_catalog(
                    $targetFolioId,
                    $reservationId,
                    $selectedPaymentCatalogId,
                    $serviceDate,
                    $actorUser
                );
            };

            if ($transferRemaining && $remainingCents > 0) {
                if ($targetFolioForTransfer <= 0 || $targetPendingCents <= 0) {
                    throw new Exception('Ya no hay saldo pendiente en otro folio para transferir el restante.');
                }
                $sourcePaymentCents = max(0, $amountCents - $remainingCents);
                if ($sourcePaymentCents > 0) {
                    $createPaymentInFolio($folioId, $sourcePaymentCents);
                }
                $createPaymentInFolio($targetFolioForTransfer, $remainingCents);
                $splitApplied = true;
            } else {
                $createPaymentInFolio($folioId, $amountCents);
            }

            try {
                pms_call_procedure('sp_folio_recalc', array($folioId));
            } catch (Exception $e) {
            }
            if ($splitApplied && $targetFolioForTransfer > 0 && $targetFolioForTransfer !== $folioId) {
                try {
                    pms_call_procedure('sp_folio_recalc', array($targetFolioForTransfer));
                } catch (Exception $e) {
                }
            }

            $paymentApplyMessage = $splitApplied
                ? ('Pago dividido entre folios #' . $folioId . ' y #' . $targetFolioForTransfer . '.')
                : ('Pago registrado en el folio #' . $folioId . '.');
        } catch (Exception $e) {
            $paymentApplyError = $e->getMessage();
        }
    }
} elseif ($calendarAction === 'create_reservation_service') {
    $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $serviceCatalogId = isset($_POST['service_catalog_id']) ? (int)$_POST['service_catalog_id'] : 0;
    $serviceQuantityRaw = isset($_POST['service_quantity']) ? trim((string)$_POST['service_quantity']) : '';
    $serviceUnitPriceRaw = isset($_POST['service_unit_price']) ? trim((string)$_POST['service_unit_price']) : '';
    $serviceDescription = isset($_POST['service_description']) ? trim((string)$_POST['service_description']) : '';
    $serviceDate = isset($_POST['service_date']) ? trim((string)$_POST['service_date']) : '';
    $serviceMarkPaid = isset($_POST['service_mark_paid']) && (string)$_POST['service_mark_paid'] === '1';
    $servicePaymentMethodId = isset($_POST['service_payment_method']) ? (int)$_POST['service_payment_method'] : 0;
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

    if ($reservationId <= 0) {
        $serviceApplyError = 'Selecciona una reservacion valida.';
    } else {
        try {
            $db = pms_get_connection();
            $stmtReservation = $db->prepare(
                'SELECT r.id_reservation, p.code AS property_code
                   FROM reservation r
                   JOIN property p ON p.id_property = r.id_property
                  WHERE r.id_reservation = ?
                    AND p.id_company = ?
                    AND r.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                  LIMIT 1'
            );
            $stmtReservation->execute(array($reservationId, $companyId));
            $reservationRow = $stmtReservation->fetch(PDO::FETCH_ASSOC);

            if (!$reservationRow) {
                throw new Exception('Selecciona una reservacion valida.');
            }

            $propertyCodeForReservation = strtoupper(trim((string)(isset($reservationRow['property_code']) ? $reservationRow['property_code'] : '')));
            if ($propertyCodeForReservation !== '') {
                pms_require_property_access($propertyCodeForReservation);
            }

            $folioId = calendar_find_open_folio_id_by_role($companyId, $reservationId, 'services', $actorUser);
            if ($folioId <= 0) {
                throw new Exception('No se encontro un folio abierto para registrar el servicio.');
            }

            $serviceConceptOptions = calendar_service_catalogs_for_property($serviceCatalogsByProperty, $propertyCodeForReservation);
            if (empty($serviceConceptOptions)) {
                throw new Exception('No hay conceptos de servicio configurados para esta propiedad.');
            }
            $serviceConceptMap = array();
            foreach ($serviceConceptOptions as $opt) {
                $optId = isset($opt['id_service_catalog']) ? (int)$opt['id_service_catalog'] : 0;
                if ($optId > 0) {
                    $serviceConceptMap[$optId] = $opt;
                }
            }
            if ($serviceCatalogId <= 0 || !isset($serviceConceptMap[$serviceCatalogId])) {
                throw new Exception('Selecciona un concepto de servicio valido.');
            }

            $quantity = $serviceQuantityRaw !== '' ? (float)str_replace(',', '.', $serviceQuantityRaw) : 1.0;
            if ($quantity <= 0) {
                throw new Exception('La cantidad del servicio debe ser mayor a 0.');
            }

            $unitPriceCents = 0;
            if ($serviceUnitPriceRaw === '') {
                $unitPriceCents = isset($serviceConceptMap[$serviceCatalogId]['default_unit_price_cents'])
                    ? (int)$serviceConceptMap[$serviceCatalogId]['default_unit_price_cents']
                    : 0;
            } else {
                $unitPriceCents = calendar_to_cents($serviceUnitPriceRaw);
            }
            if ($unitPriceCents <= 0) {
                throw new Exception('El precio unitario del servicio debe ser mayor a 0.');
            }

            if ($serviceDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate)) {
                $serviceDate = date('Y-m-d');
            }
            $fixedChildrenByParentMap = calendar_fetch_fixed_children_by_parent($companyCode, $propertyCodeForReservation, $companyId);

            $autoPaymentAmountCents = max(0, (int)round(((float)$quantity * (float)$unitPriceCents)));
            $autoPaymentCatalogId = 0;
            $autoPaymentMethodName = '';
            if ($serviceMarkPaid) {
                if ($servicePaymentMethodId <= 0) {
                    throw new Exception('Selecciona un tipo de pago para registrar el servicio como pagado.');
                }
                $paymentConceptOptions = calendar_payment_catalogs_for_reservation(
                    $paymentCatalogsByProperty,
                    $propertyCodeForReservation,
                    $companyId,
                    $reservationId
                );
                $paymentConceptMap = array();
                foreach ($paymentConceptOptions as $opt) {
                    $optId = isset($opt['id_payment_catalog']) ? (int)$opt['id_payment_catalog'] : 0;
                    if ($optId > 0) {
                        $paymentConceptMap[$optId] = true;
                    }
                }
                if (!isset($paymentConceptMap[$servicePaymentMethodId])) {
                    throw new Exception('El tipo de pago seleccionado no esta permitido para esta propiedad.');
                }
                if ($autoPaymentAmountCents <= 0) {
                    throw new Exception('No se pudo determinar el monto del pago automatico.');
                }
                $autoPaymentCatalogId = $servicePaymentMethodId;
                $autoPaymentMethodName = calendar_payment_method_name_by_id($paymentCatalogsById, $autoPaymentCatalogId);
                if ($autoPaymentMethodName === '') {
                    $autoPaymentMethodName = 'Concepto #' . $autoPaymentCatalogId;
                }
            }

            pms_call_procedure('sp_sale_item_upsert', array(
                'create',
                0,
                $folioId,
                $reservationId,
                $serviceCatalogId,
                $serviceDescription !== '' ? $serviceDescription : null,
                $serviceDate,
                $quantity,
                $unitPriceCents,
                0,
                'posted',
                $actorUser
            ));

            if ($serviceMarkPaid) {
                $createPaymentSets = pms_call_procedure('sp_sale_item_upsert', array(
                    'create',
                    0,
                    $folioId,
                    $reservationId,
                    $autoPaymentCatalogId,
                    null,
                    $serviceDate,
                    1,
                    $autoPaymentAmountCents,
                    0,
                    'captured',
                    $actorUser
                ));
                $paymentId = calendar_extract_line_item_id_from_result_sets($createPaymentSets);
                if ($paymentId > 0) {
                    try {
                        pms_call_procedure('sp_line_item_payment_meta_upsert', array(
                            $paymentId,
                            $autoPaymentMethodName !== '' ? $autoPaymentMethodName : null,
                            null,
                            'captured',
                            $actorUser
                        ));
                    } catch (Exception $e) {
                    }
                }
                if (!empty($fixedChildrenByParentMap)) {
                    $fixedPath = array();
                    calendar_upsert_fixed_children_tree(
                        $reservationId,
                        $folioId,
                        $serviceDate,
                        $actorUser,
                        $autoPaymentCatalogId,
                        $fixedChildrenByParentMap,
                        $fixedPath,
                        0
                    );
                }
                calendar_recalc_derived_tree_for_catalog(
                    $folioId,
                    $reservationId,
                    $autoPaymentCatalogId,
                    $serviceDate,
                    $actorUser
                );
            }

            if (!empty($fixedChildrenByParentMap)) {
                $fixedPath = array();
                calendar_upsert_fixed_children_tree(
                    $reservationId,
                    $folioId,
                    $serviceDate,
                    $actorUser,
                    $serviceCatalogId,
                    $fixedChildrenByParentMap,
                    $fixedPath,
                    0
                );
            }
            calendar_recalc_derived_tree_for_catalog(
                $folioId,
                $reservationId,
                $serviceCatalogId,
                $serviceDate,
                $actorUser
            );

            try {
                pms_call_procedure('sp_folio_recalc', array($folioId));
            } catch (Exception $e) {
            }

            $serviceApplyMessage = 'Servicio registrado en el folio #' . $folioId . '.';
        } catch (Exception $e) {
            $serviceApplyError = $e->getMessage();
        }
    }
} elseif ($calendarAction === 'move_reservation') {
    $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $targetPropertyCode = isset($_POST['target_property_code']) ? strtoupper(trim((string)$_POST['target_property_code'])) : '';
    $targetRoomCode = isset($_POST['target_room_code']) ? strtoupper(trim((string)$_POST['target_room_code'])) : '';
    $targetCheckIn = isset($_POST['target_check_in']) ? trim((string)$_POST['target_check_in']) : '';
    $targetCheckOut = isset($_POST['target_check_out']) ? trim((string)$_POST['target_check_out']) : '';
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
    if ($targetPropertyCode !== '') {
        pms_require_property_access($targetPropertyCode);
    }

    if ($reservationId <= 0) {
        $moveReservationError = 'Selecciona una reservacion valida.';
    } elseif ($targetPropertyCode === '' || $targetRoomCode === '' || $targetCheckIn === '' || $targetCheckOut === '') {
        $moveReservationError = 'Completa propiedad, habitacion y fechas destino para mover la reservacion.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetCheckIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetCheckOut)) {
        $moveReservationError = 'Las fechas destino no son validas.';
    } elseif ($targetCheckOut <= $targetCheckIn) {
        $moveReservationError = 'La fecha de salida debe ser mayor a la fecha de entrada.';
    } else {
        $targetRoomToken = $targetPropertyCode . '|' . $targetRoomCode;
        try {
            pms_call_procedure('sp_reservation_update', array(
                $companyCode,
                $reservationId,
                null,
                null,
                null,
                $targetRoomToken,
                $targetCheckIn,
                $targetCheckOut,
                null,
                null,
                null,
                null,
                $actorUser
            ));
            $moveReservationMessage = 'Reservacion movida correctamente.';
        } catch (Exception $e) {
            $moveReservationError = $e->getMessage();
        }
    }
} elseif ($calendarAction === 'advance_reservation_status') {
    $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $currentStatus = isset($_POST['reservation_status']) ? strtolower(trim((string)$_POST['reservation_status'])) : '';
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

    if ($reservationId <= 0) {
        $advanceError = 'Selecciona una reservacion valida.';
    } else {
        $normalized = $currentStatus;
        if ($normalized === 'encasa') {
            $normalized = 'en casa';
        }

        if ($normalized === 'apartado') {
            $advanceMessage = 'Confirma la reserva desde el flujo de confirmacion.';
        } else {
            $nextStatus = null;
            if ($normalized === 'confirmado') {
                $nextStatus = 'en casa';
            } elseif ($normalized === 'en casa') {
                $nextStatus = 'salida';
            } elseif ($normalized === 'salida') {
                $advanceMessage = 'La reservacion ya se encuentra en salida.';
            } else {
                $advanceMessage = 'Este estatus no tiene avance automatico.';
            }

            if ($nextStatus !== null) {
                try {
                    pms_call_procedure('sp_reservation_update', array(
                        $companyCode,
                        $reservationId,
                        $nextStatus,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        $actorUser
                    ));
                    $advanceMessage = 'Estatus actualizado a ' . $nextStatus . '.';
                } catch (Exception $e) {
                    $advanceError = $e->getMessage();
                    $advanceErrorNormalized = strtolower(trim((string)$advanceError));
                    $isChargesBlockError = (strpos($advanceErrorNormalized, 'necesita') !== false && strpos($advanceErrorNormalized, 'cargos') !== false);
                    if ($isChargesBlockError) {
                        $requirements = calendar_reservation_status_requirements_snapshot($companyCode, $reservationId);
                        $canForceAdvance = $requirements
                            && !empty($requirements['has_guest'])
                            && !empty($requirements['has_charges']);
                        if ($canForceAdvance && calendar_force_update_reservation_status($companyCode, $reservationId, $nextStatus)) {
                            $advanceError = '';
                            $advanceMessage = 'Estatus actualizado a ' . $nextStatus . '.';
                        }
                    }
                }
            }
        }
    }
} elseif ($calendarAction === 'mark_reservation_no_show') {
    $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

    if ($reservationId <= 0) {
        $advanceError = 'Selecciona una reservacion valida.';
    } else {
        try {
            $db = pms_get_connection();
            $stmt = $db->prepare(
                'SELECT r.status, r.check_in_date
                   FROM reservation r
                   JOIN property p ON p.id_property = r.id_property
                   JOIN company c ON c.id_company = p.id_company
                  WHERE r.id_reservation = ?
                    AND r.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    AND c.code = ?
                  LIMIT 1'
            );
            $stmt->execute(array($reservationId, $companyCode));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $advanceError = 'Selecciona una reservacion valida.';
            } else {
                $statusNormalized = strtolower(trim((string)(isset($row['status']) ? $row['status'] : '')));
                if ($statusNormalized === 'encasa') {
                    $statusNormalized = 'en casa';
                }
                $checkInDate = isset($row['check_in_date']) ? substr((string)$row['check_in_date'], 0, 10) : '';
                $today = date('Y-m-d');

                if ($statusNormalized === 'confirmada') {
                    $statusNormalized = 'confirmado';
                }

                if ($statusNormalized !== 'confirmado') {
                    $advanceError = 'Solo se puede marcar no-show desde estatus confirmada.';
                } elseif ($checkInDate === '' || $checkInDate >= $today) {
                    $advanceError = 'No aplica no-show: la reservacion debe tener check-in menor a hoy.';
                } else {
                    pms_call_procedure('sp_reservation_update', array(
                        $companyCode,
                        $reservationId,
                        'no-show',
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        $actorUser
                    ));
                    $advanceMessage = 'Estatus actualizado a no-show.';
                }
            }
        } catch (Exception $e) {
            $advanceError = $e->getMessage();
        }
    }
} elseif ($calendarAction === 'cancel_reservations') {
    $rawIds = isset($_POST['reservation_ids']) ? (string)$_POST['reservation_ids'] : '';
    $payload = json_decode($rawIds, true);
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
    if (!is_array($payload)) {
        $cancelErrors[] = 'Seleccion invalida para cancelar.';
    } else {
        foreach ($payload as $rid) {
            $reservationId = (int)$rid;
            if ($reservationId <= 0) {
                continue;
            }
            try {
                pms_call_procedure('sp_reservation_update', array(
                    $companyCode,
                    $reservationId,
                    'cancelada',
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $actorUser
                ));
                $cancelMessages[] = 'Reservacion cancelada: #' . $reservationId;
            } catch (Exception $e) {
                $cancelErrors[] = $e->getMessage();
            }
        }
    }
} elseif ($calendarAction === 'rateplan_override_quick') {
    $propertyCodeOverride = isset($_POST['rateplan_property_code']) ? strtoupper((string)$_POST['rateplan_property_code']) : '';
    $rateplanCodeOverride = isset($_POST['rateplan_code']) ? (string)$_POST['rateplan_code'] : '';
    $categoryIdOverride = isset($_POST['override_category_id']) && $_POST['override_category_id'] !== '' ? (int)$_POST['override_category_id'] : null;
    $roomIdOverride = isset($_POST['override_room_id']) && $_POST['override_room_id'] !== '' ? (int)$_POST['override_room_id'] : null;
    $overrideDate = isset($_POST['override_date']) ? (string)$_POST['override_date'] : '';
    $overridePriceRaw = isset($_POST['override_price']) ? trim((string)$_POST['override_price']) : '';
    $overrideNotes = isset($_POST['override_notes']) ? trim((string)$_POST['override_notes']) : '';
    $overrideActive = isset($_POST['override_is_active']) ? (int)$_POST['override_is_active'] : 1;

    $overridePriceCents = 0;
    if ($overridePriceRaw !== '') {
        $overridePriceCents = (int)round(((float)str_replace(',', '.', $overridePriceRaw)) * 100);
    }
    if ($propertyCodeOverride !== '') {
        pms_require_property_access($propertyCodeOverride);
    }

    if ($propertyCodeOverride === '' || $rateplanCodeOverride === '' || $overrideDate === '' || $overridePriceRaw === '') {
        $rateplanErrors[] = 'Completa fecha y precio para el override.';
    } else {
        try {
            pms_call_procedure('sp_rateplan_override_upsert', array(
                $propertyCodeOverride,
                $rateplanCodeOverride,
                null,
                $categoryIdOverride,
                $roomIdOverride,
                $overrideDate,
                $overridePriceCents,
                $overrideNotes === '' ? null : $overrideNotes,
                $overrideActive
            ));
            $rateplanMessages[] = 'Override guardado para ' . $rateplanCodeOverride . ' (' . $overrideDate . ').';
        } catch (Exception $e) {
            $rateplanErrors[] = $e->getMessage();
        }
    }
} elseif ($calendarAction === 'bulk_create_blocks') {
    $rawPayload = isset($_POST['bulk_block_payload']) ? (string)$_POST['bulk_block_payload'] : '';
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
    $payload = json_decode($rawPayload, true);
    if (!is_array($payload)) {
        $bulkBlockErrors[] = 'No se recibio una seleccion valida para bloquear.';
    } else {
        foreach ($payload as $blockSpec) {
            $blockProperty = isset($blockSpec['property_code']) ? strtoupper((string)$blockSpec['property_code']) : '';
            $blockRoom = isset($blockSpec['room_code']) ? strtoupper((string)$blockSpec['room_code']) : '';
            $blockStart = isset($blockSpec['start_date']) ? (string)$blockSpec['start_date'] : '';
            $blockEnd = isset($blockSpec['end_date']) ? (string)$blockSpec['end_date'] : '';
            $blockNotes = isset($blockSpec['notes']) ? trim((string)$blockSpec['notes']) : '';
            if ($blockProperty !== '') {
                pms_require_property_access($blockProperty);
            }
            if ($blockProperty === '' || $blockRoom === '' || $blockStart === '' || $blockEnd === '') {
                $bulkBlockErrors[] = 'Bloque incompleto (propiedad/habitacion/fechas).';
                continue;
            }
            try {
                pms_call_procedure('sp_create_room_block', array(
                    $blockProperty,
                    $blockRoom,
                    $blockStart,
                    $blockEnd,
                    $blockNotes,
                    $actorUser
                ));
                $bulkBlockMessages[] = sprintf(
                    'Bloqueo creado: %s %s (%s -> %s)',
                    $blockProperty,
                    $blockRoom,
                    calendar_format_date($blockStart, 'd M Y'),
                    calendar_format_date($blockEnd, 'd M Y')
                );
            } catch (Exception $e) {
                $bulkBlockErrors[] = sprintf(
                    '%s %s (%s -> %s): %s',
                    $blockProperty ?: '[propiedad]',
                    $blockRoom ?: '[habitacion]',
                    calendar_format_date($blockStart, 'd M Y'),
                    calendar_format_date($blockEnd, 'd M Y'),
                    $e->getMessage()
                );
            }
        }
    }
} elseif ($calendarAction === 'bulk_delete_blocks') {
    $rawPayload = isset($_POST['bulk_delete_block_ids']) ? (string)$_POST['bulk_delete_block_ids'] : '';
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
    $payload = json_decode($rawPayload, true);
    if (!is_array($payload)) {
        $bulkBlockErrors[] = 'Seleccion invalida para eliminar.';
    } else {
        $db = pms_get_connection();
        foreach ($payload as $blockId) {
            $bid = (int)$blockId;
            if ($bid <= 0) {
                continue;
            }
            try {
                $stmt = $db->prepare('UPDATE room_block SET deleted_at = NOW(), is_active = 0, updated_at = NOW() WHERE id_room_block = ? AND deleted_at IS NULL');
                $stmt->execute(array($bid));
                if ($stmt->rowCount() > 0) {
                    $bulkBlockMessages[] = 'Bloque eliminado: #' . $bid;
                } else {
                    $bulkBlockErrors[] = 'No se pudo eliminar el bloqueo #' . $bid;
                }
            } catch (Exception $e) {
                $bulkBlockErrors[] = $e->getMessage();
            }
        }
    }
} elseif ($calendarAction === 'update_block') {
    $blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
    $propertyCodePosted = isset($_POST['block_edit_property_code']) ? strtoupper((string)$_POST['block_edit_property_code']) : '';
    $roomCodePosted = isset($_POST['block_edit_room_code']) ? strtoupper((string)$_POST['block_edit_room_code']) : '';
    $startPosted = isset($_POST['block_edit_start']) ? (string)$_POST['block_edit_start'] : '';
    $endPosted = isset($_POST['block_edit_end']) ? (string)$_POST['block_edit_end'] : '';
    $descPosted = isset($_POST['block_edit_description']) ? (string)$_POST['block_edit_description'] : '';
    $actorUser = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
    if ($propertyCodePosted !== '') {
        pms_require_property_access($propertyCodePosted);
    }

    if ($blockId <= 0) {
        $blockUpdateErrors[$blockId] = 'Bloque invalido.';
    } else {
        try {
            pms_call_procedure('sp_update_room_block', array(
                $blockId,
                $propertyCodePosted,
                $roomCodePosted,
                $startPosted,
                $endPosted,
                $descPosted,
                $actorUser
            ));
            $blockUpdateMessages[$blockId] = 'Bloque actualizado.';
            $clearDirtyTargets[] = 'dynamic:block:' . $blockId;
            $_POST[$moduleKey . '_subtab_action'] = 'activate';
            $_POST[$moduleKey . '_subtab_target'] = 'static:general';
            $_POST[$moduleKey . '_subtab_target_close'] = 'block:' . $blockId;
        } catch (Exception $e) {
            $blockUpdateErrors[$blockId] = $e->getMessage();
        }
    }
}

$subtabState = pms_subtabs_init($moduleKey);
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

if ($clearDirtyTargets) {
    foreach ($clearDirtyTargets as $targetKey) {
        pms_subtabs_clear_dirty($moduleKey, $targetKey);
    }
    $subtabState = $_SESSION['pms_subtabs'][$moduleKey];
}

$openBlockIds = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'block:') === 0) {
        $blockId = (int)substr($openKey, strlen('block:'));
        if ($blockId > 0 && !in_array($blockId, $openBlockIds, true)) {
            $openBlockIds[] = $blockId;
        }
    }
}

$targetProperties = $propertyCode === '' ? array_keys($propertiesByCode) : array($propertyCode);
$calendarBundles = array();
$todayKey = date('Y-m-d');

foreach ($targetProperties as $propCode) {
    $propertyColorHex = isset($propertiesByCode[$propCode]['color_hex'])
        ? calendar_normalize_hex_color($propertiesByCode[$propCode]['color_hex'])
        : '';
    $bundle = array(
        'property_code' => $propCode,
        'property_name' => isset($propertiesByCode[$propCode]['name']) ? $propertiesByCode[$propCode]['name'] : $propCode,
        'property_color_hex' => $propertyColorHex,
        'property_tone_style' => calendar_property_tone_style($propertyColorHex),
        'error' => null,
        'rooms' => array(),
        'calendarDays' => array(),
        'reservations' => array(),
        'occupancySummary' => array(),
        'stats' => array(
            'total_rooms' => 0,
            'day_count' => 0,
            'average_occupancy_pct' => 0.0,
            'peak_pct' => 0.0,
            'busiest_date' => null,
            'today' => array('occupied' => 0, 'arrivals' => 0, 'departures' => 0),
            'calendarDayMap' => array()
        )
    );

    try {
        $sets = pms_call_procedure('sp_property_room_calendar', array($propCode, $startDate, $rangeDays));
        $bundle['rooms'] = isset($sets[0]) ? $sets[0] : array();
        $bundle['calendarDays'] = isset($sets[1]) ? $sets[1] : array();
        $bundle['reservations'] = isset($sets[2]) ? $sets[2] : array();
        $bundle['occupancySummary'] = isset($sets[3]) ? $sets[3] : array();
    } catch (Exception $e) {
        $bundle['error'] = $e->getMessage();
    }

    $bundle['rooms'] = calendar_sort_rooms($bundle['rooms'], $orderMode);

    $calendarDays = $bundle['calendarDays'];
    $reservations = $bundle['reservations'];
    $dayCount = count($calendarDays);
    $calendarDayMap = array();
    foreach ($calendarDays as $dayRow) {
        $idx = isset($dayRow['day_index']) ? (int)$dayRow['day_index'] : null;
        if ($idx !== null) {
            $calendarDayMap[$idx] = $dayRow;
        }
    }

    $reservationsByRoom = array();
    foreach ($reservations as $reservation) {
        $eventType = isset($reservation['event_type']) ? (string)$reservation['event_type'] : 'reservation';
        if ($eventType === 'block') {
            $checkIn = isset($reservation['check_in_date']) ? (string)$reservation['check_in_date'] : '';
            $checkOut = isset($reservation['check_out_date']) ? (string)$reservation['check_out_date'] : '';
            if ($checkIn !== '' && $checkOut !== '') {
                try {
                    $dtIn = new DateTime($checkIn);
                    $dtOut = new DateTime($checkOut);
                    $nights = max(1, (int)$dtIn->diff($dtOut)->days);
                    $reservation['range_nights'] = $nights;
                } catch (Exception $e) {
                    // ignore
                }
            }
        }
        $roomId = isset($reservation['id_room']) ? (int)$reservation['id_room'] : null;
        if ($roomId === null) {
            continue;
        }
        if (!isset($reservationsByRoom[$roomId])) {
            $reservationsByRoom[$roomId] = array();
        }
        $reservationsByRoom[$roomId][] = $reservation;
    }
    foreach ($reservationsByRoom as &$roomReservations) {
        usort($roomReservations, function ($a, $b) {
            $offsetA = isset($a['range_start_offset']) ? (int)$a['range_start_offset'] : 0;
            $offsetB = isset($b['range_start_offset']) ? (int)$b['range_start_offset'] : 0;
            if ($offsetA === $offsetB) {
                $checkInA = isset($a['check_in_date']) ? $a['check_in_date'] : '';
                $checkInB = isset($b['check_in_date']) ? $b['check_in_date'] : '';
                return strcmp($checkInA, $checkInB);
            }
            return $offsetA <=> $offsetB;
        });
    }
    unset($roomReservations);

    $totalRooms = count($bundle['rooms']);
    $totalDays = max($dayCount, 1);
    $totalRoomNights = $totalRooms * $totalDays;
    $occupiedRoomNights = 0;
    $peakOccupancyPct = 0.0;
    $busiestDate = null;
    $todayStats = array('occupied' => 0, 'arrivals' => 0, 'departures' => 0);

    foreach ($bundle['occupancySummary'] as $summaryRow) {
        $occupiedRooms = isset($summaryRow['occupied_rooms']) ? (int)$summaryRow['occupied_rooms'] : 0;
        $occupiedRoomNights += $occupiedRooms;
        $pct = isset($summaryRow['occupancy_pct']) ? (float)$summaryRow['occupancy_pct'] : 0.0;
        if ($pct > $peakOccupancyPct) {
            $peakOccupancyPct = $pct;
            $busiestDate = isset($summaryRow['calendar_date']) ? $summaryRow['calendar_date'] : null;
        }
        if (isset($summaryRow['date_key']) && $summaryRow['date_key'] === $todayKey) {
            $todayStats['occupied'] = $occupiedRooms;
            $todayStats['arrivals'] = isset($summaryRow['arrivals']) ? (int)$summaryRow['arrivals'] : 0;
            $todayStats['departures'] = isset($summaryRow['departures']) ? (int)$summaryRow['departures'] : 0;
        }
    }

    $averageOccupancyPct = ($totalRoomNights > 0)
        ? round(($occupiedRoomNights / $totalRoomNights) * 100, 1)
        : 0.0;

    $bundle['reservationsByRoom'] = $reservationsByRoom;
    $bundle['stats'] = array(
        'total_rooms' => $totalRooms,
        'day_count' => $dayCount,
        'average_occupancy_pct' => $averageOccupancyPct,
        'peak_pct' => $peakOccupancyPct,
        'busiest_date' => $busiestDate,
        'today' => $todayStats,
        'calendarDayMap' => $calendarDayMap,
        'range_start_label' => $dayCount ? (isset($calendarDays[0]['calendar_date']) ? $calendarDays[0]['calendar_date'] : $startDate) : $startDate,
        'range_end_label' => $dayCount ? (isset($calendarDays[$dayCount - 1]['calendar_date']) ? $calendarDays[$dayCount - 1]['calendar_date'] : $startDate) : $startDate
    );

    if ($orderMode === 'category_availability') {
        $categoryRows = array();
        $roomRows = array();
        $rateplanRows = array();
        $rateplanIndex = array();
        $categoryView = array();
        $hasCategoryCalendarDisplay = false;

        try {
            $pdo = pms_get_connection();
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

        try {
            $pdo = pms_get_connection();
            $calendarSelectSql = $hasCategoryCalendarDisplay
                ? "COALESCE(cad.calendar_amenities_csv, '') AS calendar_amenities_csv"
                : "'' AS calendar_amenities_csv";
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
                'SELECT
                    rc.id_category,
                    rc.code,
                    rc.name,
                    rc.order_index,
                    rc.id_rateplan,
                    ' . $calendarSelectSql . '
                 FROM roomcategory rc
                 ' . $calendarJoinSql . '
                 WHERE rc.id_property = ? AND rc.deleted_at IS NULL
                 ORDER BY order_index, name'
            );
            $stmt->execute(array($propertiesByCode[$propCode]['id_property']));
            $categoryRows = $stmt->fetchAll();
        } catch (Exception $e) {
            $categoryRows = array();
        }

        try {
            $pdo = isset($pdo) ? $pdo : pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT id_room, id_category, id_rateplan, code AS room_code, name
                 FROM room
                 WHERE id_property = ? AND deleted_at IS NULL AND is_active = 1'
            );
            $stmt->execute(array($propertiesByCode[$propCode]['id_property']));
            $roomRows = $stmt->fetchAll();
        } catch (Exception $e) {
            $roomRows = array();
        }

        try {
            $pdo = isset($pdo) ? $pdo : pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT id_rateplan, code, name
                 FROM rateplan
                 WHERE id_property = ? AND deleted_at IS NULL
                 ORDER BY name'
            );
            $stmt->execute(array($propertiesByCode[$propCode]['id_property']));
            $rateplanRows = $stmt->fetchAll();
        } catch (Exception $e) {
            $rateplanRows = array();
        }

        foreach ($rateplanRows as $rp) {
            $rid = isset($rp['id_rateplan']) ? (int)$rp['id_rateplan'] : 0;
            if ($rid <= 0) {
                continue;
            }
            $rateplanIndex[$rid] = array(
                'id_rateplan' => $rid,
                'rateplan_code' => isset($rp['code']) ? (string)$rp['code'] : '',
                'rateplan_name' => isset($rp['name']) ? (string)$rp['name'] : ''
            );
        }

        $categoryIndex = array();
        foreach ($categoryRows as $cat) {
            $cid = isset($cat['id_category']) ? (int)$cat['id_category'] : 0;
            if ($cid <= 0) {
                continue;
            }
            $categoryIndex[$cid] = array(
                'id_category' => $cid,
                'category_code' => isset($cat['code']) ? (string)$cat['code'] : '',
                'category_name' => isset($cat['name']) ? (string)$cat['name'] : '',
                'order_index' => isset($cat['order_index']) ? (int)$cat['order_index'] : 0,
                'id_rateplan' => isset($cat['id_rateplan']) ? (int)$cat['id_rateplan'] : 0,
                'calendar_amenities_csv' => isset($cat['calendar_amenities_csv']) ? (string)$cat['calendar_amenities_csv'] : ''
            );
        }

        if (!$categoryIndex) {
            foreach ($bundle['rooms'] as $room) {
                $cid = isset($room['id_category']) ? (int)$room['id_category'] : 0;
                if ($cid <= 0 || isset($categoryIndex[$cid])) {
                    continue;
                }
                $categoryIndex[$cid] = array(
                    'id_category' => $cid,
                    'category_code' => isset($room['category_code']) ? (string)$room['category_code'] : '',
                    'category_name' => isset($room['category_name']) ? (string)$room['category_name'] : '',
                    'order_index' => 0,
                    'id_rateplan' => 0,
                    'calendar_amenities_csv' => isset($room['calendar_amenities_csv']) ? (string)$room['calendar_amenities_csv'] : ''
                );
            }
        }

        $roomsByCategory = array();
        $roomCodesByCategory = array();
        foreach ($roomRows as $roomRow) {
            $rid = isset($roomRow['id_room']) ? (int)$roomRow['id_room'] : 0;
            $cid = isset($roomRow['id_category']) ? (int)$roomRow['id_category'] : 0;
            if ($rid <= 0 || $cid <= 0) {
                continue;
            }
            if (!isset($roomsByCategory[$cid])) {
                $roomsByCategory[$cid] = array();
            }
            $roomsByCategory[$cid][] = $rid;
            $roomCode = isset($roomRow['room_code']) ? trim((string)$roomRow['room_code']) : '';
            if ($roomCode === '') {
                $roomCode = isset($roomRow['code']) ? trim((string)$roomRow['code']) : '';
            }
            if ($roomCode === '') {
                $roomCode = isset($roomRow['name']) ? trim((string)$roomRow['name']) : '';
            }
            if ($roomCode !== '') {
                if (!isset($roomCodesByCategory[$cid])) {
                    $roomCodesByCategory[$cid] = array();
                }
                $roomCodesByCategory[$cid][] = $roomCode;
            }
        }
        if ($propCode !== '') {
            foreach (calendar_rooms_for_property($roomsByProperty, $propCode) as $roomRow) {
                $cid = isset($roomRow['id_category']) ? (int)$roomRow['id_category'] : 0;
                if ($cid <= 0 || isset($roomCodesByCategory[$cid])) {
                    continue;
                }
                $roomCode = isset($roomRow['code']) ? trim((string)$roomRow['code']) : '';
                if ($roomCode === '') {
                    $roomCode = isset($roomRow['name']) ? trim((string)$roomRow['name']) : '';
                }
                if ($roomCode !== '') {
                    $roomCodesByCategory[$cid] = array($roomCode);
                }
            }
        }

        $occupiedByRoom = array();
        foreach ($reservations as $reservation) {
            $rid = isset($reservation['id_room']) ? (int)$reservation['id_room'] : 0;
            if ($rid <= 0) {
                continue;
            }
            $startOffset = isset($reservation['range_start_offset']) ? (int)$reservation['range_start_offset'] : 0;
            $span = isset($reservation['range_nights']) ? (int)$reservation['range_nights'] : 0;
            if ($span <= 0) {
                continue;
            }
            $startOffset = max(0, $startOffset);
            $end = min($dayCount, $startOffset + $span);
            for ($i = $startOffset; $i < $end; $i++) {
                if (!isset($occupiedByRoom[$rid])) {
                    $occupiedByRoom[$rid] = array();
                }
                $occupiedByRoom[$rid][$i] = true;
            }
        }

        foreach ($categoryIndex as $cid => $cat) {
            $roomIds = isset($roomsByCategory[$cid]) ? $roomsByCategory[$cid] : array();
            $totalRoomsCat = count($roomIds);
            $availability = array();
            for ($i = 0; $i < $dayCount; $i++) {
                $occupiedCount = 0;
                foreach ($roomIds as $rid) {
                    if (isset($occupiedByRoom[$rid]) && isset($occupiedByRoom[$rid][$i])) {
                        $occupiedCount++;
                    }
                }
                $availability[$i] = max(0, $totalRoomsCat - $occupiedCount);
            }

            $rateplanIds = array();
            if (!empty($cat['id_rateplan'])) {
                $rateplanIds[$cat['id_rateplan']] = true;
            }
            $rateplanList = array();
            foreach (array_keys($rateplanIds) as $rpid) {
                if (!isset($rateplanIndex[$rpid])) {
                    continue;
                }
                $rp = $rateplanIndex[$rpid];
                $calendarRows = array();
                if (!empty($cat['category_code'])) {
                    try {
                        $calendarRows = $rateplanPricingService->getCalendarPricesByCodes(
                            $propCode,
                            $rp['rateplan_code'],
                            $cat['category_code'],
                            null,
                            $startDate,
                            $rangeDays
                        );
                    } catch (Exception $e) {
                        $calendarRows = array();
                    }
                }
                $rateplanList[] = array(
                    'id_rateplan' => $rpid,
                    'rateplan_code' => $rp['rateplan_code'],
                    'rateplan_name' => $rp['rateplan_name'],
                    'calendar_rows' => $calendarRows
                );
            }

            $categoryView[] = array(
                'id_category' => $cid,
                'category_code' => $cat['category_code'],
                'category_name' => $cat['category_name'],
                'order_index' => $cat['order_index'],
                'calendar_amenities_csv' => isset($cat['calendar_amenities_csv']) ? (string)$cat['calendar_amenities_csv'] : '',
                'total_rooms' => $totalRoomsCat,
                'availability' => $availability,
                'rateplans' => $rateplanList
            );
        }

        usort($categoryView, function ($a, $b) {
            if ($a['order_index'] !== $b['order_index']) {
                return $a['order_index'] <=> $b['order_index'];
            }
            return strcmp((string)$a['category_name'], (string)$b['category_name']);
        });

        $bundle['categoryView'] = $categoryView;
        $bundle['roomCodesByCategory'] = $roomCodesByCategory;
      }

    $calendarBundles[] = $bundle;
}

$calendarFolioStatsByReservation = array();
$calendarPaymentFoliosByReservation = array();
$calendarReservationPropertyById = array();
$calendarReservationOtaIdByReservation = array();
$calendarReservationSourceIdByReservation = array();
try {
    $reservationIdsForFolioStats = array();
    foreach ($calendarBundles as $bundleForFolioStats) {
        $bundlePropertyCode = isset($bundleForFolioStats['property_code']) ? strtoupper((string)$bundleForFolioStats['property_code']) : '';
        $bundleReservations = isset($bundleForFolioStats['reservations']) && is_array($bundleForFolioStats['reservations'])
            ? $bundleForFolioStats['reservations']
            : array();
        foreach ($bundleReservations as $reservationRowForFolioStats) {
            $eventTypeTmp = isset($reservationRowForFolioStats['event_type']) ? (string)$reservationRowForFolioStats['event_type'] : 'reservation';
            if ($eventTypeTmp === 'block') {
                continue;
            }
            $reservationIdTmp = isset($reservationRowForFolioStats['id_reservation']) ? (int)$reservationRowForFolioStats['id_reservation'] : 0;
            if ($reservationIdTmp > 0) {
                $reservationIdsForFolioStats[$reservationIdTmp] = $reservationIdTmp;
                if (!isset($calendarReservationPropertyById[$reservationIdTmp])) {
                    $calendarReservationPropertyById[$reservationIdTmp] = $bundlePropertyCode;
                }
                if (!isset($calendarReservationOtaIdByReservation[$reservationIdTmp])) {
                    $calendarReservationOtaIdByReservation[$reservationIdTmp] = isset($reservationRowForFolioStats['id_ota_account'])
                        ? (int)$reservationRowForFolioStats['id_ota_account']
                        : 0;
                }
                if (!isset($calendarReservationSourceIdByReservation[$reservationIdTmp])) {
                    $calendarReservationSourceIdByReservation[$reservationIdTmp] = isset($reservationRowForFolioStats['id_reservation_source'])
                        ? (int)$reservationRowForFolioStats['id_reservation_source']
                        : 0;
                }
            }
        }
    }
    if ($reservationIdsForFolioStats) {
        $pdoFolioStats = pms_get_connection();
        $reservationIdChunks = array_chunk(array_values($reservationIdsForFolioStats), 500);
        foreach ($reservationIdChunks as $reservationIdChunk) {
            if (!$reservationIdChunk) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($reservationIdChunk), '?'));
            $stmtFolioStats = $pdoFolioStats->prepare(
                'SELECT
                    f.id_reservation,
                    COUNT(*) AS folio_count_all,
                    SUM(COALESCE(f.total_cents, 0)) AS total_cents_all,
                    SUM(COALESCE(f.balance_cents, 0)) AS balance_cents_all
                 FROM folio f
                 WHERE f.deleted_at IS NULL
                   AND f.id_reservation IN (' . $placeholders . ')
                 GROUP BY f.id_reservation'
            );
            $stmtFolioStats->execute($reservationIdChunk);
            foreach ($stmtFolioStats->fetchAll(PDO::FETCH_ASSOC) as $folioStatsRow) {
                $statsReservationId = isset($folioStatsRow['id_reservation']) ? (int)$folioStatsRow['id_reservation'] : 0;
                if ($statsReservationId <= 0) {
                    continue;
                }
                $calendarFolioStatsByReservation[$statsReservationId] = array(
                    'folio_count_all' => isset($folioStatsRow['folio_count_all']) ? (int)$folioStatsRow['folio_count_all'] : 0,
                    'total_cents_all' => isset($folioStatsRow['total_cents_all']) ? (int)$folioStatsRow['total_cents_all'] : 0,
                    'balance_cents_all' => isset($folioStatsRow['balance_cents_all']) ? (int)$folioStatsRow['balance_cents_all'] : 0
                );
            }

            $stmtPaymentFolios = $pdoFolioStats->prepare(
                'SELECT
                    f.id_reservation,
                    f.id_folio,
                    COALESCE(f.folio_name, \'\') AS folio_name,
                    COALESCE(f.balance_cents, 0) AS balance_cents,
                    COALESCE(f.currency, \'MXN\') AS currency
                 FROM folio f
                 WHERE f.deleted_at IS NULL
                   AND COALESCE(f.is_active, 1) = 1
                   AND COALESCE(f.status, \'open\') = \'open\'
                   AND f.id_reservation IN (' . $placeholders . ')
                 ORDER BY f.id_reservation ASC, f.id_folio ASC'
            );
            $stmtPaymentFolios->execute($reservationIdChunk);
            foreach ($stmtPaymentFolios->fetchAll(PDO::FETCH_ASSOC) as $folioRow) {
                $folioReservationId = isset($folioRow['id_reservation']) ? (int)$folioRow['id_reservation'] : 0;
                $folioIdTmp = isset($folioRow['id_folio']) ? (int)$folioRow['id_folio'] : 0;
                if ($folioReservationId <= 0 || $folioIdTmp <= 0) {
                    continue;
                }
                if (!isset($calendarPaymentFoliosByReservation[$folioReservationId])) {
                    $calendarPaymentFoliosByReservation[$folioReservationId] = array();
                }
                $folioNameTmp = isset($folioRow['folio_name']) ? trim((string)$folioRow['folio_name']) : '';
                $calendarPaymentFoliosByReservation[$folioReservationId][] = array(
                    'id_folio' => $folioIdTmp,
                    'folio_name' => $folioNameTmp,
                    'role' => calendar_folio_role_by_name($folioNameTmp),
                    'balance_cents' => max(0, isset($folioRow['balance_cents']) ? (int)$folioRow['balance_cents'] : 0),
                    'currency' => isset($folioRow['currency']) ? (string)$folioRow['currency'] : 'MXN'
                );
            }
        }
    }
} catch (Exception $e) {
    $calendarFolioStatsByReservation = array();
    $calendarPaymentFoliosByReservation = array();
}

$calendarBlockedPaymentCatalogIdsByReservation = array();
$calendarPaymentCatalogsByReservation = array();
if ($calendarReservationPropertyById) {
    try {
        if (function_exists('pms_reservation_blocked_payment_catalog_ids_bulk')) {
            $calendarBlockedPaymentCatalogIdsByReservation = pms_reservation_blocked_payment_catalog_ids_bulk(
                $companyId,
                array_keys($calendarReservationPropertyById)
            );
        }
    } catch (Exception $e) {
        $calendarBlockedPaymentCatalogIdsByReservation = array();
    }
    foreach ($calendarReservationPropertyById as $reservationIdTmp => $propertyCodeTmp) {
        $reservationIdTmp = (int)$reservationIdTmp;
        if ($reservationIdTmp <= 0) {
            continue;
        }
        $calendarPaymentCatalogsByReservation[$reservationIdTmp] = calendar_payment_catalogs_for_reservation(
            $paymentCatalogsByProperty,
            (string)$propertyCodeTmp,
            $companyId,
            $reservationIdTmp,
            $calendarBlockedPaymentCatalogIdsByReservation
        );
    }
}

$calendarBalanceBreakdownByReservation = array();
if ($calendarReservationPropertyById) {
    try {
        $pdoCalendarServiceBalance = pms_get_connection();
        $calendarBalanceBreakdownByReservation = calendar_build_service_balance_map(
            $pdoCalendarServiceBalance,
            $calendarReservationPropertyById,
            $serviceCatalogsByProperty
        );
    } catch (Exception $e) {
        $calendarBalanceBreakdownByReservation = array();
    }
}

$calendarInfoPreviewByReservation = array();
if (($calendarReservationOtaIdByReservation || $calendarReservationSourceIdByReservation) && ($calendarOtaInfoCatalogsByAccount || $calendarSourceInfoCatalogsBySource)) {
    try {
        $pdoOtaInfoPreview = pms_get_connection();
        $calendarInfoPreviewByReservation = calendar_build_ota_info_preview_map(
            $pdoOtaInfoPreview,
            $calendarReservationOtaIdByReservation,
            $calendarReservationSourceIdByReservation,
            $calendarOtaLodgingCatalogByCatalogId,
            $calendarOtaInfoCatalogsByAccount,
            $calendarSourceInfoCatalogsBySource
        );
    } catch (Exception $e) {
        $calendarInfoPreviewByReservation = array();
    }
}

$blockRangeTo = date('Y-m-d', strtotime($startDate . ' + ' . $rangeDays . ' days'));
$blockList = array();
$blockListError = null;
try {
    $blockSets = pms_call_procedure('sp_list_room_blocks', array(
        $companyCode,
        $propertyCode === '' ? null : $propertyCode,
        $startDate,
        $blockRangeTo
    ));
    $blockList = isset($blockSets[0]) ? $blockSets[0] : array();
} catch (Exception $e) {
    $blockListError = $e->getMessage();
    $blockList = array();
}

$blockDetails = array();
foreach ($openBlockIds as $blockId) {
    try {
        $detailSets = pms_call_procedure('sp_get_room_block', array($blockId));
        $detailRow = isset($detailSets[0][0]) ? $detailSets[0][0] : null;
        $blockDetails[$blockId] = array(
            'detail' => $detailRow,
            'error' => null
        );
    } catch (Exception $e) {
        $blockDetails[$blockId] = array(
            'detail' => null,
            'error' => $e->getMessage()
        );
    }
}

ob_start();
?>
<section class="calendar-card">
  <form method="post" class="calendar-controls" id="calendar-controls-form">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <?php calendar_render_context_hiddens($propertyCode, $baseDate, $viewModeKey, $orderMode); ?>
    <?php
      $wizardUrl = 'index.php?view=reservation_wizard'
        . ($propertyCode !== '' ? '&wizard_property=' . urlencode($propertyCode) : '')
        . ($baseDate !== '' ? '&wizard_check_in=' . urlencode($baseDate) . '&wizard_check_out=' . urlencode(date('Y-m-d', strtotime($baseDate . ' +1 day'))) : '');
    ?>
    <div class="calendar-action-bar">
      <div class="calendar-action-left">
        <button type="submit" name="nav_action" value="today" class="button-secondary calendar-today-btn">Hoy</button>
      </div>
      <div class="calendar-filters">
        <label>
          Propiedad
          <select name="property_code" onchange="this.form.submit();">
            <option value="" <?php echo $propertyCode === '' ? 'selected' : ''; ?>>(Todas)</option>
            <?php foreach ($properties as $property):
              $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
              $name = isset($property['name']) ? (string)$property['name'] : '';
              if ($code === '') {
                  continue;
              }
            ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $propertyCode ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Fecha base
          <input type="date" name="start_date" value="<?php echo htmlspecialchars($baseDate, ENT_QUOTES, 'UTF-8'); ?>" required onchange="this.form.submit();">
        </label>
        <label>
          Orden
          <select name="order_mode" onchange="this.form.submit();">
            <?php foreach ($orderOptions as $key => $label): ?>
              <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $key === $orderMode ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="calendar-action-right">
        <div id="calendar-selection-actions-slot" class="calendar-selection-actions-slot"></div>
        <div id="calendar-desktop-menu-slot" class="calendar-desktop-menu-slot"></div>
      </div>
    </div>
  </form>
  <?php if ($calendarFocusReservationId > 0 || $calendarFocusRoomCode !== '' || $calendarFocusCheckIn !== ''): ?>
    <div
      id="calendar-focus-context"
      hidden
      data-reservation-id="<?php echo (int)$calendarFocusReservationId; ?>"
      data-property-code="<?php echo htmlspecialchars($calendarFocusPropertyCode, ENT_QUOTES, 'UTF-8'); ?>"
      data-room-code="<?php echo htmlspecialchars($calendarFocusRoomCode, ENT_QUOTES, 'UTF-8'); ?>"
      data-check-in="<?php echo htmlspecialchars($calendarFocusCheckIn, ENT_QUOTES, 'UTF-8'); ?>"
    ></div>
  <?php endif; ?>

  <?php if ($bulkBlockMessages): ?>
    <div class="success">
      <ul>
        <?php foreach ($bulkBlockMessages as $msg): ?>
          <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($cancelMessages): ?>
    <div class="success">
      <ul>
        <?php foreach ($cancelMessages as $msg): ?>
          <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($rateplanMessages): ?>
    <div class="success">
      <ul>
        <?php foreach ($rateplanMessages as $msg): ?>
          <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($bulkBlockErrors): ?>
    <div class="error">
      <ul>
        <?php foreach ($bulkBlockErrors as $msg): ?>
          <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($cancelErrors): ?>
    <div class="error">
      <ul>
        <?php foreach ($cancelErrors as $msg): ?>
          <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($rateplanErrors): ?>
    <div class="error">
      <ul>
        <?php foreach ($rateplanErrors as $msg): ?>
          <li><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($quickReservationError): ?>
    <p class="error"><?php echo htmlspecialchars($quickReservationError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($advanceMessage): ?>
    <p class="success"><?php echo htmlspecialchars($advanceMessage, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($quickReservationMessage): ?>
    <div class="success">
      <p><?php echo htmlspecialchars($quickReservationMessage, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($quickReservationActionReservationId > 0): ?>
        <form method="post" action="index.php?view=reservation_wizard" style="margin-top:10px; display:inline-flex; gap:8px; align-items:center;">
          <input type="hidden" name="wizard_reservation_id" value="<?php echo (int)$quickReservationActionReservationId; ?>">
          <input type="hidden" name="wizard_step" value="2">
          <input type="hidden" name="wizard_force_step" value="2">
          <input type="hidden" name="wizard_replace_lodging" value="1">
          <input type="hidden" name="wizard_replace_folio_id" value="<?php echo (int)$quickReservationActionFolioId; ?>">
          <?php calendar_render_wizard_return_hiddens(
              $propertyCode,
              $baseDate,
              $viewModeKey,
              $orderMode,
              isset($_REQUEST['calendar_current_subtab']) ? (string)$_REQUEST['calendar_current_subtab'] : '',
              isset($_REQUEST['calendar_dirty_tabs']) ? (string)$_REQUEST['calendar_dirty_tabs'] : ''
          ); ?>
          <button type="submit" class="button-secondary">Cambiar tipo de hospedaje</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($paymentApplyMessage): ?>
    <p class="success"><?php echo htmlspecialchars($paymentApplyMessage, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($serviceApplyMessage): ?>
    <p class="success"><?php echo htmlspecialchars($serviceApplyMessage, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($moveReservationMessage): ?>
    <p class="success"><?php echo htmlspecialchars($moveReservationMessage, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($advanceError): ?>
    <p class="error"><?php echo htmlspecialchars($advanceError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($paymentApplyError): ?>
    <p class="error"><?php echo htmlspecialchars($paymentApplyError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($serviceApplyError): ?>
    <p class="error"><?php echo htmlspecialchars($serviceApplyError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($moveReservationError): ?>
    <p class="error"><?php echo htmlspecialchars($moveReservationError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($calendarBundles): ?>
    <?php foreach ($calendarBundles as $bundleIndex => $bundle): ?>
      <?php
        $rooms = isset($bundle['rooms']) ? $bundle['rooms'] : array();
        $calendarDays = isset($bundle['calendarDays']) ? $bundle['calendarDays'] : array();
        $reservationsByRoom = isset($bundle['reservationsByRoom']) ? $bundle['reservationsByRoom'] : array();
        $stats = isset($bundle['stats']) ? $bundle['stats'] : array();
        $occupancySummary = isset($bundle['occupancySummary']) ? $bundle['occupancySummary'] : array();
        $propertyCodeRow = isset($bundle['property_code']) ? (string)$bundle['property_code'] : '';
        $propertyNameRow = isset($bundle['property_name']) ? (string)$bundle['property_name'] : $propertyCodeRow;
        $calendarDayMap = isset($stats['calendarDayMap']) ? $stats['calendarDayMap'] : array();
        $rangeStartLabel = isset($stats['range_start_label']) ? $stats['range_start_label'] : $startDate;
        $rangeEndLabel = isset($stats['range_end_label']) ? $stats['range_end_label'] : $startDate;
        $averageOccupancyPct = isset($stats['average_occupancy_pct']) ? (float)$stats['average_occupancy_pct'] : 0.0;
        $peakOccupancyPct = isset($stats['peak_pct']) ? (float)$stats['peak_pct'] : 0.0;
        $busiestDate = isset($stats['busiest_date']) ? $stats['busiest_date'] : null;
        $todayStats = isset($stats['today']) ? $stats['today'] : array('occupied' => 0, 'arrivals' => 0, 'departures' => 0);
        $totalRooms = isset($stats['total_rooms']) ? (int)$stats['total_rooms'] : 0;
        $dayCount = isset($stats['day_count']) ? (int)$stats['day_count'] : count($calendarDays);
        $propertyColorHexRow = isset($bundle['property_color_hex']) ? calendar_normalize_hex_color($bundle['property_color_hex']) : '';
        $propertyToneStyleRow = isset($bundle['property_tone_style']) ? (string)$bundle['property_tone_style'] : '';
      ?>
      <div class="calendar-property-bundle<?php echo $propertyColorHexRow !== '' ? ' has-property-accent' : ''; ?>"<?php echo $propertyToneStyleRow !== '' ? ' style="' . htmlspecialchars($propertyToneStyleRow, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
      <?php if (isset($bundle['error']) && $bundle['error']): ?>
        <p class="error"><?php echo htmlspecialchars($bundle['error'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
        <?php if ($orderMode === 'category_availability'): ?>
          <?php
            $categoryView = isset($bundle['categoryView']) ? $bundle['categoryView'] : array();
            $roomCodesByCategory = isset($bundle['roomCodesByCategory']) ? $bundle['roomCodesByCategory'] : array();
          ?>
        <?php if ($categoryView && $calendarDays): ?>
          <?php
            $categoryDayWidth = 120;
            $categoryRoomWidth = 220;
            $categoryTableWidth = $categoryRoomWidth + ($dayCount * $categoryDayWidth);
          ?>
          <div class="calendar-scroll" data-scroll-date="<?php echo htmlspecialchars($baseDate, ENT_QUOTES, 'UTF-8'); ?>" data-today="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
            <table class="calendar-table calendar-category-availability" data-property-code="<?php echo htmlspecialchars((string)$propertyCodeRow, ENT_QUOTES, 'UTF-8'); ?>" style="--calendar-day-count: <?php echo (int)$dayCount; ?>; --calendar-room-width: <?php echo (int)$categoryRoomWidth; ?>px; --calendar-day-width: <?php echo (int)$categoryDayWidth; ?>px; width: <?php echo (int)$categoryTableWidth; ?>px; min-width: <?php echo (int)$categoryTableWidth; ?>px;">
              <thead>
                <?php
                  $monthNames = array(
                      1 => 'Enero',
                      2 => 'Febrero',
                      3 => 'Marzo',
                      4 => 'Abril',
                      5 => 'Mayo',
                      6 => 'Junio',
                      7 => 'Julio',
                      8 => 'Agosto',
                      9 => 'Septiembre',
                      10 => 'Octubre',
                      11 => 'Noviembre',
                      12 => 'Diciembre'
                  );
                  $monthSpans = array();
                  $currentMonthKey = '';
                  $currentMonthLabel = '';
                  $currentSpan = 0;
                  foreach ($calendarDays as $day) {
                      $dateValue = isset($day['date_key']) ? (string)$day['date_key'] : (isset($day['calendar_date']) ? (string)$day['calendar_date'] : '');
                      $dateObj = null;
                      if ($dateValue !== '') {
                          try {
                              $dateObj = new DateTime($dateValue);
                          } catch (Exception $e) {
                              $dateObj = null;
                          }
                      }
                      $monthKey = $dateObj ? $dateObj->format('Y-m') : $dateValue;
                      $monthLabel = $dateObj ? ($monthNames[(int)$dateObj->format('n')] . ' ' . $dateObj->format('Y')) : $dateValue;
                      if ($currentMonthKey === '') {
                          $currentMonthKey = $monthKey;
                          $currentMonthLabel = $monthLabel;
                          $currentSpan = 1;
                          continue;
                      }
                      if ($monthKey !== $currentMonthKey) {
                          $monthSpans[] = array('label' => $currentMonthLabel, 'span' => $currentSpan);
                          $currentMonthKey = $monthKey;
                          $currentMonthLabel = $monthLabel;
                          $currentSpan = 1;
                          continue;
                      }
                      $currentSpan++;
                  }
                  if ($currentMonthKey !== '') {
                      $monthSpans[] = array('label' => $currentMonthLabel, 'span' => $currentSpan);
                  }
                  $monthDividerIndices = array();
                  $prevMonthKey = '';
                  foreach ($calendarDays as $day) {
                      $dateValue = isset($day['date_key']) ? (string)$day['date_key'] : (isset($day['calendar_date']) ? (string)$day['calendar_date'] : '');
                      $dayIndex = isset($day['day_index']) ? (int)$day['day_index'] : null;
                      if ($dayIndex === null) {
                          continue;
                      }
                      $dateObj = null;
                      if ($dateValue !== '') {
                          try {
                              $dateObj = new DateTime($dateValue);
                          } catch (Exception $e) {
                              $dateObj = null;
                          }
                      }
                      $monthKey = $dateObj ? $dateObj->format('Y-m') : $dateValue;
                      if ($prevMonthKey !== '' && $monthKey !== $prevMonthKey) {
                          $monthDividerIndices[$dayIndex] = true;
                      }
                      $prevMonthKey = $monthKey;
                  }
                  $dayNames = array(
                      1 => 'Lunes',
                      2 => 'Martes',
                      3 => 'Miercoles',
                      4 => 'Jueves',
                      5 => 'Viernes',
                      6 => 'Sabado',
                      7 => 'Domingo'
                  );
                ?>
                <tr class="calendar-month-row">
                  <th class="room-header calendar-property-header" rowspan="2">
                    <span class="calendar-property-dot" aria-hidden="true"></span>
                    <span class="calendar-property-name"><?php echo htmlspecialchars($propertyNameRow, ENT_QUOTES, 'UTF-8'); ?></span>
                  </th>
                  <?php foreach ($monthSpans as $span): ?>
                    <th class="month-header" colspan="<?php echo (int)$span['span']; ?>">
                      <span class="month-label month-label-start"><?php echo htmlspecialchars($span['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <span class="month-label month-label-center"><?php echo htmlspecialchars($span['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                      <span class="month-label month-label-end"><?php echo htmlspecialchars($span['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </th>
                  <?php endforeach; ?>
                </tr>
                <tr class="calendar-day-row">
                  <?php foreach ($calendarDays as $day):
                    $dayKey = isset($day['date_key']) ? (string)$day['date_key'] : '';
                    $dayLabel = isset($day['day_label']) ? (string)$day['day_label'] : '';
                    $dayName = isset($day['day_short_name']) ? (string)$day['day_short_name'] : '';
                    $isToday = isset($day['is_today']) && (int)$day['is_today'] === 1;
                    $weekday = isset($day['day_of_week']) ? (int)$day['day_of_week'] : 0;
                    $dateObj = null;
                    if ($dayKey !== '') {
                        try {
                            $dateObj = new DateTime($dayKey);
                        } catch (Exception $e) {
                            $dateObj = null;
                        }
                    }
                    if ($dateObj) {
                        $weekday = (int)$dateObj->format('N');
                        $dayName = isset($dayNames[$weekday]) ? $dayNames[$weekday] : $dayName;
                        $dayLabel = $dateObj->format('j');
                    }
                    $dayClass = array('day-header');
                    if ($isToday) {
                        $dayClass[] = 'is-today';
                    }
                    if ($weekday >= 6) {
                        $dayClass[] = 'is-weekend';
                    }
                    $dayIndex = isset($day['day_index']) ? (int)$day['day_index'] : 0;
                    if (isset($monthDividerIndices[$dayIndex])) {
                        $dayClass[] = 'is-month-divider';
                    }
                  ?>
                    <th class="<?php echo implode(' ', $dayClass); ?>" title="<?php echo htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8'); ?>" data-day-index="<?php echo (int)$dayIndex; ?>" data-date="<?php echo htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8'); ?>">
                      <span class="day-name"><?php echo htmlspecialchars($dayName, ENT_QUOTES, 'UTF-8'); ?></span>
                      <span class="day-date"><?php echo htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($categoryView as $cat):
                  $catName = $cat['category_code'] !== '' ? $cat['category_code'] : ($cat['category_name'] !== '' ? $cat['category_name'] : 'Sin categoria');
                  $totalRoomsCat = isset($cat['total_rooms']) ? (int)$cat['total_rooms'] : 0;
                  $availability = isset($cat['availability']) ? $cat['availability'] : array();
                  $catId = isset($cat['id_category']) ? (int)$cat['id_category'] : 0;
                  $roomCodes = isset($roomCodesByCategory[$catId]) ? $roomCodesByCategory[$catId] : array();
                  $roomCodeLabel = $roomCodes ? implode(', ', $roomCodes) : 'Sin habitaciones';
                  $catAmenityKeys = calendar_parse_category_amenities_csv(isset($cat['calendar_amenities_csv']) ? (string)$cat['calendar_amenities_csv'] : '');
                  $catAmenityCapsulesHtml = calendar_category_icon_capsules_html($catAmenityKeys);
                ?>
                  <tr class="calendar-category-availability-row">
                    <th class="room-cell category-cell">
                      <strong><?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?></strong>
                      <?php if ($catAmenityCapsulesHtml !== ''): ?>
                        <?php echo $catAmenityCapsulesHtml; ?>
                      <?php endif; ?>
                      <div class="room-subtitle" title="<?php echo htmlspecialchars($roomCodeLabel, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($roomCodeLabel, ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                    </th>
                    <?php for ($i = 0; $i < $dayCount; $i++):
                      $dayInfo = isset($calendarDayMap[$i]) ? $calendarDayMap[$i] : null;
                      $dateKey = $dayInfo && isset($dayInfo['date_key']) ? (string)$dayInfo['date_key'] : '';
                      $availableCount = isset($availability[$i]) ? (int)$availability[$i] : 0;
                      $cellClass = 'calendar-cell availability';
                      if ($dayInfo && isset($dayInfo['is_today']) && (int)$dayInfo['is_today'] === 1) {
                          $cellClass .= ' is-today';
                      }
                      if ($availableCount <= 0) {
                          $cellClass .= ' is-soldout';
                      }
                      if (isset($monthDividerIndices[$i])) {
                          $cellClass .= ' is-month-divider';
                      }
                    ?>
                      <td class="<?php echo $cellClass; ?>" data-date="<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="availability-count"><?php echo (int)$availableCount; ?></span>
                      </td>
                    <?php endfor; ?>
                  </tr>
                  <?php
                    $rateplans = isset($cat['rateplans']) ? $cat['rateplans'] : array();
                  ?>
                  <?php if ($rateplans): ?>
                    <?php foreach ($rateplans as $rp):
                      $rpName = isset($rp['rateplan_name']) && $rp['rateplan_name'] !== '' ? $rp['rateplan_name'] : (isset($rp['rateplan_code']) ? $rp['rateplan_code'] : 'Rateplan');
                      $rpCode = isset($rp['rateplan_code']) ? (string)$rp['rateplan_code'] : '';
                      $priceByIndex = array();
                      foreach (isset($rp['calendar_rows']) ? $rp['calendar_rows'] : array() as $row) {
                          $idx = isset($row['day_index']) ? (int)$row['day_index'] : null;
                          if ($idx !== null) {
                              $priceByIndex[$idx] = $row;
                          }
                      }
                    ?>
                      <tr class="calendar-rateplan-row">
                        <th class="room-cell rateplan-cell">
                          <div class="rateplan-name"><?php echo htmlspecialchars($rpName, ENT_QUOTES, 'UTF-8'); ?></div>
                          <?php if ($rpCode !== ''): ?>
                            <div class="room-subtitle"><?php echo htmlspecialchars($rpCode, ENT_QUOTES, 'UTF-8'); ?></div>
                          <?php endif; ?>
                        </th>
                        <?php for ($i = 0; $i < $dayCount; $i++):
                          $dayInfo = isset($calendarDayMap[$i]) ? $calendarDayMap[$i] : null;
                          $dateKey = $dayInfo && isset($dayInfo['date_key']) ? (string)$dayInfo['date_key'] : '';
                          $row = isset($priceByIndex[$i]) ? $priceByIndex[$i] : null;
                          $finalCents = $row && isset($row['final_price_cents']) ? (int)$row['final_price_cents'] : 0;
                          $overrideCents = $row && array_key_exists('override_price_cents', $row) ? $row['override_price_cents'] : null;
                          $priceDisplay = number_format($finalCents / 100, 2, '.', '');
                          $cellClasses = 'calendar-cell rateplan-price js-rateplan-price';
                          if ($dayInfo && isset($dayInfo['is_today']) && (int)$dayInfo['is_today'] === 1) {
                              $cellClasses .= ' is-today';
                          }
                          if ($overrideCents !== null) {
                              $cellClasses .= ' has-override';
                          }
                          if (isset($monthDividerIndices[$i])) {
                              $cellClasses .= ' is-month-divider';
                          }
                        ?>
                          <td class="<?php echo $cellClasses; ?>"
                              data-property-code="<?php echo htmlspecialchars((string)$propertyCodeRow, ENT_QUOTES, 'UTF-8'); ?>"
                              data-rateplan-code="<?php echo htmlspecialchars($rpCode, ENT_QUOTES, 'UTF-8'); ?>"
                              data-category-id="<?php echo (int)$catId; ?>"
                              data-date="<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>"
                              data-price="<?php echo htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="rateplan-price-amount"><?php echo htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                          </td>
                        <?php endfor; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr class="calendar-rateplan-row is-empty">
                      <th class="room-cell rateplan-cell">
                        <div class="rateplan-name">Sin rateplans</div>
                      </th>
                      <?php for ($i = 0; $i < $dayCount; $i++):
                        $dayInfo = isset($calendarDayMap[$i]) ? $calendarDayMap[$i] : null;
                        $todayClass = ($dayInfo && isset($dayInfo['is_today']) && (int)$dayInfo['is_today'] === 1) ? ' is-today' : '';
                      ?>
                        <td class="calendar-cell rateplan-price is-empty<?php echo isset($monthDividerIndices[$i]) ? ' is-month-divider' : ''; ?><?php echo $todayClass; ?>"></td>
                      <?php endfor; ?>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="muted">No hay categorias para mostrar.</p>
        <?php endif; ?>
      <?php elseif ($rooms && $calendarDays): ?>
        <?php if ($propertyCode !== ''): ?>
        <div class="calendar-summary">
          <div class="summary-tile">
            <span class="summary-label">Habitaciones</span>
            <strong class="summary-value"><?php echo (int)$totalRooms; ?></strong>
          </div>
          <div class="summary-tile">
            <span class="summary-label">Hoy</span>
            <strong class="summary-value">
              <?php echo (int)$todayStats['occupied']; ?> ocupadas /
              <?php echo (int)$todayStats['arrivals']; ?> llegadas /
              <?php echo (int)$todayStats['departures']; ?> salidas
            </strong>
          </div>
        </div>

        <div class="calendar-legend">
          <span class="legend-item status-apartado">Apartado</span>
          <span class="legend-item status-confirmado">Confirmada</span>
          <span class="legend-item status-encasa">En casa</span>
          <span class="legend-item status-salida">Salida</span>
          <span class="legend-item status-noshow">No-show</span>
          <span class="legend-item status-blocked">Bloqueo</span>
        </div>
        <?php endif; ?>
        <?php
          $roomDayWidth = 120;
          $roomLabelWidth = 200;
          $roomTableWidth = $roomLabelWidth + ($dayCount * $roomDayWidth);
        ?>
        <div class="calendar-scroll" data-scroll-date="<?php echo htmlspecialchars($baseDate, ENT_QUOTES, 'UTF-8'); ?>" data-today="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
          <table class="calendar-table" data-property-code="<?php echo htmlspecialchars((string)$propertyCodeRow, ENT_QUOTES, 'UTF-8'); ?>" style="--calendar-day-count: <?php echo (int)$dayCount; ?>; --calendar-room-width: <?php echo (int)$roomLabelWidth; ?>px; --calendar-day-width: <?php echo (int)$roomDayWidth; ?>px; width: <?php echo (int)$roomTableWidth; ?>px; min-width: <?php echo (int)$roomTableWidth; ?>px;">
            <thead>
              <?php
                $monthNames = array(
                    1 => 'Enero',
                    2 => 'Febrero',
                    3 => 'Marzo',
                    4 => 'Abril',
                    5 => 'Mayo',
                    6 => 'Junio',
                    7 => 'Julio',
                    8 => 'Agosto',
                    9 => 'Septiembre',
                    10 => 'Octubre',
                    11 => 'Noviembre',
                    12 => 'Diciembre'
                );
                $monthSpans = array();
                $currentMonthKey = '';
                $currentMonthLabel = '';
                $currentSpan = 0;
                foreach ($calendarDays as $day) {
                    $dateValue = isset($day['date_key']) ? (string)$day['date_key'] : (isset($day['calendar_date']) ? (string)$day['calendar_date'] : '');
                    $dateObj = null;
                    if ($dateValue !== '') {
                        try {
                            $dateObj = new DateTime($dateValue);
                        } catch (Exception $e) {
                            $dateObj = null;
                        }
                    }
                    $monthKey = $dateObj ? $dateObj->format('Y-m') : $dateValue;
                    $monthLabel = $dateObj ? ($monthNames[(int)$dateObj->format('n')] . ' ' . $dateObj->format('Y')) : $dateValue;
                    if ($currentMonthKey === '') {
                        $currentMonthKey = $monthKey;
                        $currentMonthLabel = $monthLabel;
                        $currentSpan = 1;
                        continue;
                    }
                    if ($monthKey !== $currentMonthKey) {
                        $monthSpans[] = array('label' => $currentMonthLabel, 'span' => $currentSpan);
                        $currentMonthKey = $monthKey;
                        $currentMonthLabel = $monthLabel;
                        $currentSpan = 1;
                        continue;
                    }
                    $currentSpan++;
                }
                if ($currentMonthKey !== '') {
                    $monthSpans[] = array('label' => $currentMonthLabel, 'span' => $currentSpan);
                }
                $monthDividerIndices = array();
                $prevMonthKey = '';
                foreach ($calendarDays as $day) {
                    $dateValue = isset($day['date_key']) ? (string)$day['date_key'] : (isset($day['calendar_date']) ? (string)$day['calendar_date'] : '');
                    $dayIndex = isset($day['day_index']) ? (int)$day['day_index'] : null;
                    if ($dayIndex === null) {
                        continue;
                    }
                    $dateObj = null;
                    if ($dateValue !== '') {
                        try {
                            $dateObj = new DateTime($dateValue);
                        } catch (Exception $e) {
                            $dateObj = null;
                        }
                    }
                    $monthKey = $dateObj ? $dateObj->format('Y-m') : $dateValue;
                    if ($prevMonthKey !== '' && $monthKey !== $prevMonthKey) {
                        $monthDividerIndices[$dayIndex] = true;
                    }
                    $prevMonthKey = $monthKey;
                }
                $dayNames = array(
                    1 => 'Lunes',
                    2 => 'Martes',
                    3 => 'Miercoles',
                    4 => 'Jueves',
                    5 => 'Viernes',
                    6 => 'Sabado',
                    7 => 'Domingo'
                );
              ?>
              <tr class="calendar-month-row">
                <th class="room-header calendar-property-header" rowspan="2">
                  <span class="calendar-property-dot" aria-hidden="true"></span>
                  <span class="calendar-property-name"><?php echo htmlspecialchars($propertyNameRow, ENT_QUOTES, 'UTF-8'); ?></span>
                </th>
                <?php foreach ($monthSpans as $span): ?>
                  <th class="month-header" colspan="<?php echo (int)$span['span']; ?>">
                    <span class="month-label month-label-start"><?php echo htmlspecialchars($span['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="month-label month-label-center"><?php echo htmlspecialchars($span['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="month-label month-label-end"><?php echo htmlspecialchars($span['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </th>
                <?php endforeach; ?>
              </tr>
              <tr class="calendar-day-row">
                <?php foreach ($calendarDays as $day):
                  $dayKey = isset($day['date_key']) ? (string)$day['date_key'] : '';
                  $dayLabel = isset($day['day_label']) ? (string)$day['day_label'] : '';
                  $dayName = isset($day['day_short_name']) ? (string)$day['day_short_name'] : '';
                  $isToday = isset($day['is_today']) && (int)$day['is_today'] === 1;
                  $weekday = isset($day['day_of_week']) ? (int)$day['day_of_week'] : 0;
                  $dateObj = null;
                  if ($dayKey !== '') {
                      try {
                          $dateObj = new DateTime($dayKey);
                      } catch (Exception $e) {
                          $dateObj = null;
                      }
                  }
                  if ($dateObj) {
                      $weekday = (int)$dateObj->format('N');
                      $dayName = isset($dayNames[$weekday]) ? $dayNames[$weekday] : $dayName;
                      $dayLabel = $dateObj->format('j');
                  }
                  $dayClass = array('day-header');
                  if ($isToday) {
                      $dayClass[] = 'is-today';
                  }
                    if ($weekday >= 6) {
                        $dayClass[] = 'is-weekend';
                    }
                    $dayIndex = isset($day['day_index']) ? (int)$day['day_index'] : 0;
                    if (isset($monthDividerIndices[$dayIndex])) {
                        $dayClass[] = 'is-month-divider';
                    }
                  ?>
                  <th class="<?php echo implode(' ', $dayClass); ?>" title="<?php echo htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8'); ?>" data-day-index="<?php echo (int)$dayIndex; ?>" data-date="<?php echo htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="day-name"><?php echo htmlspecialchars($dayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="day-date"><?php echo htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php
                $roomNamesByCategoryKey = array();
                foreach ($rooms as $roomMeta) {
                    $roomMetaCategoryName = isset($roomMeta['category_name']) ? (string)$roomMeta['category_name'] : '';
                    $roomMetaCategoryCode = isset($roomMeta['category_code']) ? (string)$roomMeta['category_code'] : '';
                    $roomMetaCategoryLabel = $roomMetaCategoryCode !== '' ? $roomMetaCategoryCode : ($roomMetaCategoryName !== '' ? $roomMetaCategoryName : 'Sin categoria');
                    $roomMetaCategoryKey = $roomMetaCategoryCode !== '' ? $roomMetaCategoryCode : $roomMetaCategoryLabel;
                    $roomMetaCode = isset($roomMeta['room_code']) ? trim((string)$roomMeta['room_code']) : '';
                    $roomMetaName = isset($roomMeta['room_name']) ? trim((string)$roomMeta['room_name']) : '';
                    $roomMetaLabel = $roomMetaCode !== '' ? $roomMetaCode : $roomMetaName;
                    if ($roomMetaLabel === '') {
                        continue;
                    }
                    if (!isset($roomNamesByCategoryKey[$roomMetaCategoryKey])) {
                        $roomNamesByCategoryKey[$roomMetaCategoryKey] = array();
                    }
                    if (!in_array($roomMetaLabel, $roomNamesByCategoryKey[$roomMetaCategoryKey], true)) {
                        $roomNamesByCategoryKey[$roomMetaCategoryKey][] = $roomMetaLabel;
                    }
                }
                $currentCategoryKey = null;
                foreach ($rooms as $room):
                $roomId = isset($room['id_room']) ? (int)$room['id_room'] : 0;
                $roomCode = isset($room['room_code']) ? (string)$room['room_code'] : '';
                $roomName = isset($room['room_name']) && $room['room_name'] !== '' ? (string)$room['room_name'] : '';
                $categoryName = isset($room['category_name']) ? (string)$room['category_name'] : '';
                $categoryCode = isset($room['category_code']) ? (string)$room['category_code'] : '';
                $categoryLabel = $categoryCode !== '' ? $categoryCode : ($categoryName !== '' ? $categoryName : 'Sin categoria');
                $categoryDisplay = 'Sin categoria';
                if ($categoryCode !== '' && $categoryName !== '' && strcasecmp($categoryCode, $categoryName) !== 0) {
                    $categoryDisplay = $categoryCode . ' - ' . $categoryName;
                } elseif ($categoryCode !== '') {
                    $categoryDisplay = $categoryCode;
                } elseif ($categoryName !== '') {
                    $categoryDisplay = $categoryName;
                }
                $roomAmenityKeys = calendar_parse_category_amenities_csv(isset($room['calendar_amenities_csv']) ? (string)$room['calendar_amenities_csv'] : '');
                $roomAmenityCapsulesHtml = calendar_category_icon_capsules_html($roomAmenityKeys);
                $categoryKey = $categoryCode !== '' ? $categoryCode : $categoryLabel;
                if ($orderMode === 'category' && $categoryKey !== $currentCategoryKey):
                    $currentCategoryKey = $categoryKey;
                    $categoryRoomNames = isset($roomNamesByCategoryKey[$categoryKey]) ? $roomNamesByCategoryKey[$categoryKey] : array();
                    $categoryRoomNamesText = $categoryRoomNames ? implode(', ', $categoryRoomNames) : 'Sin habitaciones';
              ?>
                <tr class="calendar-category-row">
                  <th
                    class="calendar-category-cell room-cell"
                    title="<?php echo htmlspecialchars('Categoria: ' . $categoryDisplay . ' - Habitaciones: ' . $categoryRoomNamesText, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="calendar-category-badge">
                      <span class="calendar-category-prefix">Categoria:</span>
                      <span class="calendar-category-name"><?php echo htmlspecialchars($categoryDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                      <span class="calendar-category-separator"> - </span>
                      <span class="calendar-category-prefix">Habitaciones:</span>
                      <span class="calendar-category-rooms"><?php echo htmlspecialchars($categoryRoomNamesText, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                  </th>
                  <td class="calendar-category-fill-cell" colspan="<?php echo (int)$dayCount; ?>"></td>
                </tr>
              <?php endif;
                $rowReservations = isset($reservationsByRoom[$roomId]) ? $reservationsByRoom[$roomId] : array();
                $cursor = 0;
              ?>
                <tr>
                  <th class="room-cell">
                    <div class="room-code"><?php echo htmlspecialchars($roomCode, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if ($categoryCode !== ''): ?>
                      <div class="room-tag"><?php echo htmlspecialchars($categoryCode, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($roomAmenityCapsulesHtml !== ''): ?>
                      <?php echo $roomAmenityCapsulesHtml; ?>
                    <?php endif; ?>
                  </th>
                  <?php foreach ($rowReservations as $reservation):
                    $startOffset = isset($reservation['range_start_offset']) ? (int)$reservation['range_start_offset'] : 0;
                    $span = isset($reservation['range_nights']) ? (int)$reservation['range_nights'] : 0;
                    if ($span <= 0) {
                        continue;
                    }
                    if ($startOffset < $cursor) {
                        $overlapDelta = $cursor - $startOffset;
                        $startOffset = $cursor;
                        $span -= $overlapDelta;
                    }
                    if ($span <= 0) {
                        continue;
                    }
                    if ($startOffset > $cursor) {
                        for ($gap = $cursor; $gap < $startOffset && $gap < $dayCount; $gap++) {
                            $gapDay = isset($calendarDayMap[$gap]) ? $calendarDayMap[$gap] : null;
                            $isMonthDivider = isset($monthDividerIndices[$gap]);
                            calendar_render_empty_cell($gapDay, $roomCode, $roomName, $propertyCodeRow, $categoryCode, $isMonthDivider);
                        }
                        $cursor = $startOffset;
                    }
                    $span = min($span, $dayCount - $cursor);
                    if ($span <= 0) {
                        break;
                    }

                    $status = isset($reservation['status']) ? (string)$reservation['status'] : '';
                    $statusNormalized = strtolower(trim($status));
                    if ($statusNormalized === 'encasa') {
                        $statusNormalized = 'en casa';
                    } elseif ($statusNormalized === 'confirmada') {
                        $statusNormalized = 'confirmado';
                    } elseif (in_array($statusNormalized, array('sin confirmar', 's/confirmar', 'pendiente', 'pending', 'hold'), true)) {
                        $statusNormalized = 'apartado';
                    } elseif (in_array($statusNormalized, array('no show', 'noshow', 'no_show'), true)) {
                        $statusNormalized = 'no-show';
                    }
                    $statusClass = calendar_status_class($statusNormalized !== '' ? $statusNormalized : $status);
                    $eventType = isset($reservation['event_type']) ? (string)$reservation['event_type'] : 'reservation';
                    $isBlock = $eventType === 'block';
                    $reservationId = isset($reservation['id_reservation']) ? (int)$reservation['id_reservation'] : 0;
                    $canOpenReservation = !$isBlock && $reservationId > 0;

                      $guestName = isset($reservation['guest_full_name']) ? (string)$reservation['guest_full_name'] : '';
                      $guestName = trim($guestName) !== '' ? $guestName : (isset($reservation['guest_email']) ? (string)$reservation['guest_email'] : '');
                      $guestNameTrim = trim($guestName);
                      $guestFirstName = $guestNameTrim;
                      if ($guestNameTrim !== '') {
                          $guestParts = preg_split('/\s+/', $guestNameTrim);
                          if ($guestParts && $guestParts[0] !== '') {
                              $guestFirstName = $guestParts[0];
                          }
                      }
                      $blockNote = isset($reservation['description']) ? trim((string)$reservation['description']) : '';
                      $guestNote = isset($reservation['notes_guest']) ? trim((string)$reservation['notes_guest']) : '';
                      $latestReservationNote = isset($reservation['latest_note_text']) ? trim((string)$reservation['latest_note_text']) : '';
                      $reservationNotePreview = $blockNote;
                      if (!$isBlock && $reservationNotePreview === '' && $guestNote !== '') {
                          $reservationNotePreview = $guestNote;
                      }
                      if (!$isBlock && $reservationNotePreview === '' && $latestReservationNote !== '') {
                          $reservationNotePreview = $latestReservationNote;
                      }
                      if ($reservationNotePreview !== '') {
                          $reservationNotePreview = str_replace(array("\r\n", "\r"), "\n", $reservationNotePreview);
                          $firstLine = strtok($reservationNotePreview, "\n");
                          if ($firstLine !== false) {
                              $reservationNotePreview = trim((string)$firstLine);
                          }
                          $reservationNotePreview = trim((string)preg_replace('/\s+/', ' ', $reservationNotePreview));
                          if ($reservationNotePreview !== '') {
                              $maxNoteLen = 34;
                              if (function_exists('mb_strlen')) {
                                  if (mb_strlen($reservationNotePreview) > $maxNoteLen) {
                                      $reservationNotePreview = rtrim(mb_substr($reservationNotePreview, 0, $maxNoteLen - 3)) . '...';
                                  }
                              } elseif (strlen($reservationNotePreview) > $maxNoteLen) {
                                  $reservationNotePreview = rtrim(substr($reservationNotePreview, 0, $maxNoteLen - 3)) . '...';
                              }
                          }
                      }
                      $isQuickApartado = !$isBlock && $statusNormalized === 'apartado' && $guestNameTrim === '' && $blockNote !== '';
                    $checkIn = isset($reservation['check_in_date']) ? (string)$reservation['check_in_date'] : '';
                    $checkOut = isset($reservation['check_out_date']) ? (string)$reservation['check_out_date'] : '';
                    $reservationCode = isset($reservation['reservation_code']) ? (string)$reservation['reservation_code'] : '';
                    $currency = isset($reservation['currency']) ? (string)$reservation['currency'] : 'MXN';
                    $totalCents = isset($reservation['total_price_cents']) ? (int)$reservation['total_price_cents'] : 0;
                    $balanceCents = isset($reservation['balance_due_cents']) ? (int)$reservation['balance_due_cents'] : 0;
                    $folioCountRaw = isset($reservation['folio_count']) ? (int)$reservation['folio_count'] : 0;
                    $fallbackFolioStats = ($reservationId > 0 && isset($calendarFolioStatsByReservation[$reservationId]))
                        ? $calendarFolioStatsByReservation[$reservationId]
                        : null;
                    $folioCount = $folioCountRaw;
                    $fallbackFolioCount = 0;
                    if ($folioCount <= 0 && $fallbackFolioStats) {
                        $fallbackFolioCount = isset($fallbackFolioStats['folio_count_all']) ? (int)$fallbackFolioStats['folio_count_all'] : 0;
                        if ($fallbackFolioCount > 0) {
                            $folioCount = $fallbackFolioCount;
                        }
                    }
                    $hasNoFolio = !$isBlock && $folioCount === 0 && $fallbackFolioCount === 0;
                    $isNoFolio = $hasNoFolio && in_array($statusNormalized, array('confirmado', 'en casa'), true);
                    $needsConfirm = !$isBlock && $statusNormalized === 'apartado';
                    $sourceRaw = trim((string)(isset($reservation['source']) ? $reservation['source'] : ''));
                    $sourceRawLower = strtolower($sourceRaw);
                    $otaAccountId = isset($reservation['id_ota_account']) ? (int)$reservation['id_ota_account'] : 0;
                    $otaPlatform = strtolower(trim((string)(isset($reservation['ota_platform']) ? $reservation['ota_platform'] : '')));
                    $otaName = trim((string)(isset($reservation['ota_name']) ? $reservation['ota_name'] : ''));
                    $reservationSourceId = isset($reservation['id_reservation_source']) ? (int)$reservation['id_reservation_source'] : 0;
                    $reservationSourceName = trim((string)(isset($reservation['reservation_source_name']) ? $reservation['reservation_source_name'] : ''));
                    $sourceClass = 'source-otro';
                    $sourceLabel = $reservationSourceName !== '' ? $reservationSourceName : ($sourceRaw !== '' ? $sourceRaw : 'Directo');
                    if ($otaPlatform === 'booking') {
                        $sourceClass = 'source-booking';
                        $sourceLabel = $otaName !== '' ? $otaName : 'Booking';
                    } elseif ($otaPlatform === 'airbnb' || $otaPlatform === 'abb') {
                        $sourceClass = 'source-airbnb';
                        $sourceLabel = $otaName !== '' ? $otaName : 'Airbnb';
                    } elseif ($otaPlatform === 'expedia') {
                        $sourceClass = 'source-expedia';
                        $sourceLabel = $otaName !== '' ? $otaName : 'Expedia';
                    } elseif ($otaName !== '') {
                        $sourceLabel = $otaName;
                    } elseif (in_array($sourceRawLower, array('booking', 'airbnb', 'expedia'), true)) {
                        $sourceClass = 'source-' . $sourceRawLower;
                        $sourceLabel = ucfirst($sourceRawLower);
                    }
                    $otaColorHex = '';
                    if (!$isBlock && $otaAccountId > 0) {
                        if (isset($calendarOtaColorById[$otaAccountId])) {
                            $otaColorHex = calendar_normalize_hex_color($calendarOtaColorById[$otaAccountId]);
                        } elseif (isset($calendarOtaColorByProperty[$propertyCodeRow][$otaAccountId])) {
                            $otaColorHex = calendar_normalize_hex_color($calendarOtaColorByProperty[$propertyCodeRow][$otaAccountId]);
                        }
                    }
                    $sourceColorHex = '';
                    if (!$isBlock && $otaAccountId <= 0 && $reservationSourceId > 0) {
                        if (isset($calendarSourceColorById[$reservationSourceId])) {
                            $sourceColorHex = calendar_normalize_hex_color($calendarSourceColorById[$reservationSourceId]);
                        } elseif (isset($calendarSourceColorByProperty[$propertyCodeRow][$reservationSourceId])) {
                            $sourceColorHex = calendar_normalize_hex_color($calendarSourceColorByProperty[$propertyCodeRow][$reservationSourceId]);
                        }
                    }
                    if (!$isBlock && $sourceColorHex === '') {
                        $sourceNameKey = calendar_source_color_key(
                            $reservationSourceName !== '' ? $reservationSourceName : $sourceRaw
                        );
                        if ($sourceNameKey !== '' && isset($calendarSourceColorByName[$sourceNameKey])) {
                            $sourceColorHex = calendar_normalize_hex_color($calendarSourceColorByName[$sourceNameKey]);
                        }
                    }
                    $visualColorHex = $otaColorHex !== '' ? $otaColorHex : $sourceColorHex;
                    $reservationToneStyle = '';
                    if ($visualColorHex !== '') {
                        $cellBackground = calendar_hex_to_rgba($visualColorHex, 0.18);
                        $cellBorder = calendar_hex_to_rgba($visualColorHex, 0.72);
                        $cellGlow = calendar_hex_to_rgba($visualColorHex, 0.1);
                        if ($cellBackground !== '' && $cellBorder !== '') {
                            $reservationToneStyle = '--reservation-accent-bg:' . $cellBackground . ';'
                                . '--reservation-accent-border:' . $cellBorder . ';';
                            if ($cellGlow !== '') {
                                $reservationToneStyle .= '--reservation-accent-glow:' . $cellGlow . ';';
                            }
                        }
                    }
                    if ($balanceCents < 0) {
                        $balanceCents = 0;
                    }
                    $displayTotalCents = ($folioCount > 0) ? $totalCents : 0;
                    $displayBalanceCents = ($folioCount > 0) ? $balanceCents : 0;
                    if (($displayTotalCents <= 0 && $displayBalanceCents <= 0) && $fallbackFolioStats) {
                        $fallbackTotalCents = isset($fallbackFolioStats['total_cents_all']) ? (int)$fallbackFolioStats['total_cents_all'] : 0;
                        $fallbackBalanceCents = isset($fallbackFolioStats['balance_cents_all']) ? (int)$fallbackFolioStats['balance_cents_all'] : 0;
                        if ($fallbackTotalCents > 0 || $fallbackBalanceCents > 0) {
                            $displayTotalCents = max(0, $fallbackTotalCents);
                            $displayBalanceCents = max(0, $fallbackBalanceCents);
                        }
                    }
                    $balanceBreakdown = ($reservationId > 0 && isset($calendarBalanceBreakdownByReservation[$reservationId]))
                        ? $calendarBalanceBreakdownByReservation[$reservationId]
                        : null;
                    $serviceBalanceCents = $balanceBreakdown && isset($balanceBreakdown['service_balance_cents'])
                        ? (int)$balanceBreakdown['service_balance_cents']
                        : 0;
                    $lodgingBalanceCents = $balanceBreakdown && isset($balanceBreakdown['lodging_balance_cents'])
                        ? (int)$balanceBreakdown['lodging_balance_cents']
                        : max(0, $displayBalanceCents - $serviceBalanceCents);
                    if ($serviceBalanceCents < 0) {
                        $serviceBalanceCents = 0;
                    }
                    if ($lodgingBalanceCents < 0) {
                        $lodgingBalanceCents = 0;
                    }
                    if ($balanceBreakdown && isset($balanceBreakdown['total_balance_cents'])) {
                        $displayBalanceCents = max(0, (int)$balanceBreakdown['total_balance_cents']);
                    } else {
                        $displayBalanceCents = $lodgingBalanceCents + $serviceBalanceCents;
                    }
                    $hasLodgingCharges = $balanceBreakdown
                        ? !empty($balanceBreakdown['has_lodging_charges'])
                        : ($displayTotalCents > 0);
                    $paidCents = $displayTotalCents > 0 ? max(0, $displayTotalCents - $displayBalanceCents) : 0;
                    $summaryBalanceLabel = '$' . number_format(max(0, $displayBalanceCents) / 100, 2, '.', ',');
                    $summaryLodgingBalanceLabel = '$' . number_format(max(0, $lodgingBalanceCents) / 100, 2, '.', ',');
                    $summaryServiceBalanceLabel = '$' . number_format(max(0, $serviceBalanceCents) / 100, 2, '.', ',');
                    $hasFinancialCapsules = !$isBlock && $statusNormalized !== 'apartado';
                    $otaInfoPreviewRows = ($reservationId > 0 && isset($calendarInfoPreviewByReservation[$reservationId]))
                        ? $calendarInfoPreviewByReservation[$reservationId]
                        : array();
                    $otaInfoPreviewRowsVisible = array();
                    if ($otaInfoPreviewRows) {
                        foreach ($otaInfoPreviewRows as $otaInfoPreviewRowTmp) {
                            $otaInfoAmountTmp = isset($otaInfoPreviewRowTmp['amount_total_cents']) ? (int)$otaInfoPreviewRowTmp['amount_total_cents'] : 0;
                            if ($otaInfoAmountTmp === 0) {
                                continue;
                            }
                            $otaInfoPreviewRowsVisible[] = $otaInfoPreviewRowTmp;
                        }
                    }
                    $missingGuest = !$isBlock && !$isQuickApartado && $guestNameTrim === '';
                    $hasCheckInToday = !$isBlock && $checkIn !== '' && $todayKey !== '' && $checkIn === $todayKey;
                    $isConfirmedStatus = in_array($statusNormalized, array('confirmado', 'confirmada', 'confirmed'), true);
                    $isInHouseStatus = $statusNormalized === 'en casa';
                    $isCheckoutStatus = in_array($statusNormalized, array('check out', 'checkout', 'salida'), true);
                    $showCheckoutPaidCheck = !$isBlock && $isCheckoutStatus && $displayBalanceCents <= 0;
                    $isNoShowStatus = $statusNormalized === 'no-show';
                    $isCheckoutOverdue = $isInHouseStatus && $checkOut !== '' && $todayKey !== '' && $checkOut < $todayKey;
                    $stateIcons = array();
                    if (!$isBlock) {
                        if ($isNoShowStatus) {
                            $stateIcons[] = array(
                                'type' => 'noshow',
                                'class' => 'is-no-show',
                                'title' => 'No-show'
                            );
                        } elseif ($statusNormalized === 'apartado') {
                            $stateIcons[] = array(
                                'type' => 'alert',
                                'class' => 'is-alert',
                                'title' => 'Reservacion sin confirmar'
                            );
                        } elseif ($missingGuest) {
                            $stateIcons[] = array(
                                'type' => 'alert',
                                'class' => 'is-alert',
                                'title' => 'Falta huesped'
                            );
                        }
                        if (!$stateIcons) {
                            if ($isCheckoutStatus) {
                                $stateIcons[] = array('type' => 'checkin', 'class' => 'is-muted', 'title' => 'Check-in completado');
                                $stateIcons[] = array('type' => 'house', 'class' => 'is-muted', 'title' => 'Estancia completada');
                                $stateIcons[] = array('type' => 'checkout', 'class' => 'is-active', 'title' => 'Check-out');
                            } elseif ($isInHouseStatus) {
                                $stateIcons[] = array('type' => 'checkin', 'class' => 'is-muted', 'title' => 'Check-in completado');
                                $stateIcons[] = array('type' => 'house', 'class' => 'is-active', 'title' => 'En casa');
                                if ($isCheckoutOverdue) {
                                    $stateIcons[] = array('type' => 'alert', 'class' => 'is-alert', 'title' => 'Check-out pendiente');
                                }
                            } else {
                                $stateIcons[] = array(
                                    'type' => 'checkin',
                                    'class' => ($isConfirmedStatus && $hasCheckInToday) ? 'is-active' : 'is-muted',
                                    'title' => ($isConfirmedStatus && $hasCheckInToday) ? 'Check-in hoy' : 'Check-in pendiente'
                                );
                            }
                        }
                    }
                      if ($isBlock) {
                          $chipLabel = $blockNote !== '' ? $blockNote : 'Bloqueo';
                          $summaryLabel = $chipLabel;
                          $detailLabel = $chipLabel;
                          $reservationTitle = sprintf(
                              'Bloqueo #%s (%s -> %s)%s',
                              $reservationCode,
                            calendar_format_date($checkIn, 'd M Y'),
                            calendar_format_date($checkOut, 'd M Y'),
                            $blockNote !== '' ? ' | ' . $blockNote : ''
                        );
                      } elseif ($isQuickApartado) {
                          $detailLabel = $blockNote;
                          $summaryLabel = $blockNote;
                          $chipLabel = $blockNote;
                          $reservationTitle = sprintf(
                              '#%s | %s (%s -> %s)',
                              $reservationCode,
                              $chipLabel,
                              calendar_format_date($checkIn, 'd M Y'),
                              calendar_format_date($checkOut, 'd M Y')
                          );
                      } else {
                          $detailLabel = $guestNameTrim !== '' ? $guestNameTrim : ($reservationNotePreview !== '' ? $reservationNotePreview : '');
                          if ($detailLabel === '') {
                              $detailLabel = $reservationCode !== '' ? $reservationCode : 'Reserva';
                          }
                          if ($hasNoFolio && $guestNameTrim !== '' && $reservationNotePreview !== '') {
                              $displayGuestName = $guestFirstName !== '' ? $guestFirstName : $guestNameTrim;
                              $detailLabel = $displayGuestName . ' - ' . $reservationNotePreview;
                          }
                          $summaryLabel = $detailLabel;
                          $chipLabel = $detailLabel;
                          $reservationTitle = sprintf(
                              '#%s | %s (%s -> %s)%s',
                              $reservationCode,
                              $chipLabel,
                              calendar_format_date($checkIn, 'd M Y'),
                              calendar_format_date($checkOut, 'd M Y'),
                              $displayTotalCents > 0 ? (' | Balance: ' . calendar_format_money($displayBalanceCents, $currency)) : ''
                        );
                    }
                  ?>
                    <?php
                      $blockIdAttr = $isBlock && isset($reservation['id_room_block']) ? (int)$reservation['id_room_block'] : 0;
                      $selectionTypeAttr = $isBlock ? 'block' : 'reservation';
                    ?>
                    <?php
                      $rangeStartDate = $calendarDays && isset($calendarDays[0]['calendar_date']) ? (string)$calendarDays[0]['calendar_date'] : $startDate;
                      $rangeEndDate = $calendarDays && isset($calendarDays[$dayCount - 1]['calendar_date']) ? (string)$calendarDays[$dayCount - 1]['calendar_date'] : $startDate;
                      $endDate = '';
                      if ($checkOut !== '') {
                          $endDate = date('Y-m-d', strtotime($checkOut . ' -1 day'));
                      }
                      $showStart = !$isBlock && $checkIn !== '' && $rangeStartDate !== '' && $checkIn >= $rangeStartDate;
                      $showEnd = !$isBlock && $endDate !== '' && $rangeEndDate !== '' && $endDate <= $rangeEndDate;
                      $rangeClasses = trim(($showStart ? 'is-start ' : '') . ($showEnd ? 'is-end' : ''));
                      if (isset($monthDividerIndices[$startOffset])) {
                          $rangeClasses = trim($rangeClasses . ' is-month-divider');
                      }
                      $isTodayRange = ($checkIn !== '' && $checkOut !== '' && $todayKey !== '')
                          ? ($todayKey >= $checkIn && $todayKey < $checkOut)
                          : false;
                    ?>
                    <td class="calendar-cell reservation <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?> <?php echo $isBlock ? 'is-block' : ('is-booking ' . $sourceClass); ?> <?php echo $isQuickApartado ? 'is-quick' : ''; ?> <?php echo $isNoFolio ? 'is-no-folio' : ''; ?> <?php echo $needsConfirm ? 'is-warning' : ''; ?> <?php echo $isTodayRange ? 'is-today' : ''; ?> <?php echo htmlspecialchars($rangeClasses, ENT_QUOTES, 'UTF-8'); ?> js-calendar-reservation" colspan="<?php echo (int)$span; ?>" title="<?php echo htmlspecialchars($reservationTitle, ENT_QUOTES, 'UTF-8'); ?>"
                      <?php echo $reservationToneStyle !== '' ? ('style="' . htmlspecialchars($reservationToneStyle, ENT_QUOTES, 'UTF-8') . '"') : ''; ?>
                      data-reservation-id="<?php echo (int)$reservationId; ?>"
                      data-block-id="<?php echo $blockIdAttr; ?>"
                      data-selection-type="<?php echo $selectionTypeAttr; ?>"
                      data-reservation-code="<?php echo htmlspecialchars($reservationCode, ENT_QUOTES, 'UTF-8'); ?>"
                      data-reservation-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                      data-reservation-folio-count="<?php echo (int)$folioCount; ?>"
                      data-reservation-balance-cents="<?php echo (int)$displayBalanceCents; ?>"
                      data-reservation-service-balance-cents="<?php echo (int)$serviceBalanceCents; ?>"
                      data-reservation-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>"
                      data-room-code="<?php echo htmlspecialchars($roomCode, ENT_QUOTES, 'UTF-8'); ?>"
                      data-property-code="<?php echo htmlspecialchars($propertyCodeRow, ENT_QUOTES, 'UTF-8'); ?>"
                      data-check-in="<?php echo htmlspecialchars($checkIn, ENT_QUOTES, 'UTF-8'); ?>"
                      data-check-out="<?php echo htmlspecialchars($checkOut, ENT_QUOTES, 'UTF-8'); ?>"
                      data-guest-name="<?php echo htmlspecialchars($detailLabel, ENT_QUOTES, 'UTF-8'); ?>"
                      >
                        <div class="reservation-chip">
                          <div class="reservation-body">
                            <div class="reservation-summary">
                              <?php if (!$isBlock && $stateIcons): ?>
                                <div class="reservation-state-icons">
                                  <?php foreach ($stateIcons as $stateIcon): ?>
                                    <span class="reservation-state-icon <?php echo htmlspecialchars($stateIcon['class'], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($stateIcon['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                      <?php echo calendar_state_icon_svg($stateIcon['type']); ?>
                                    </span>
                                  <?php endforeach; ?>
                                </div>
                              <?php endif; ?>
                              <span class="reservation-guest"><?php echo htmlspecialchars($summaryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                              <?php if (!$isBlock && !$isQuickApartado && ($statusNormalized === 'apartado' || $hasFinancialCapsules)): ?>
                                <?php if ($statusNormalized === 'apartado'): ?>
                                  <span class="reservation-total is-hold">PENDIENTE</span>
                                <?php else: ?>
                                  <span class="reservation-total-row">
                                    <span
                                      class="reservation-total <?php echo $lodgingBalanceCents <= 0 ? 'is-paid' : 'is-pending'; ?><?php echo $showCheckoutPaidCheck ? ' is-checkout-ok' : ''; ?>"
                                      title="<?php echo htmlspecialchars($showCheckoutPaidCheck ? 'Check-out liquidado' : ('Balance hospedaje: ' . $summaryLodgingBalanceLabel), ENT_QUOTES, 'UTF-8'); ?>">
                                      <?php echo $showCheckoutPaidCheck ? '&#10003;' : htmlspecialchars($summaryLodgingBalanceLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <?php if ($serviceBalanceCents > 0): ?>
                                      <span
                                        class="reservation-total is-services"
                                        title="<?php echo htmlspecialchars('Balance servicios: ' . $summaryServiceBalanceLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($summaryServiceBalanceLabel, ENT_QUOTES, 'UTF-8'); ?>
                                      </span>
                                    <?php endif; ?>
                                  </span>
                                <?php endif; ?>
                              <?php endif; ?>
                            </div>
                            <div class="reservation-detail">
                              <span class="reservation-guest"><?php echo htmlspecialchars($detailLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                              <?php if (!$isBlock && !$isQuickApartado && ($statusNormalized === 'apartado' || $hasFinancialCapsules || $otaInfoPreviewRowsVisible)): ?>
                                <div class="reservation-meta">
                                  <?php if ($statusNormalized === 'apartado'): ?>
                                    <span class="reservation-hold">PENDIENTE</span>
                                  <?php elseif ($otaInfoPreviewRowsVisible): ?>
                                    <?php foreach ($otaInfoPreviewRowsVisible as $otaInfoPreviewRow): ?>
                                      <?php
                                        $otaInfoLabel = isset($otaInfoPreviewRow['label']) ? trim((string)$otaInfoPreviewRow['label']) : 'Concepto';
                                        $otaInfoAmountCents = isset($otaInfoPreviewRow['amount_total_cents']) ? (int)$otaInfoPreviewRow['amount_total_cents'] : 0;
                                      ?>
                                      <span class="reservation-price"><?php echo htmlspecialchars($otaInfoLabel . ' ' . calendar_format_money($otaInfoAmountCents, $currency), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                  <?php else: ?>
                                    <span class="reservation-price">Total <?php echo htmlspecialchars(calendar_format_money($displayTotalCents, $currency), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($showCheckoutPaidCheck): ?>
                                      <span class="reservation-price is-checkout-ok">Check-out &#10003;</span>
                                    <?php else: ?>
                                      <span class="reservation-price">Balance <?php echo htmlspecialchars(calendar_format_money($displayBalanceCents, $currency), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($displayBalanceCents <= 0): ?>
                                      <span class="reservation-paid">Pagado</span>
                                    <?php else: ?>
                                      <span class="reservation-price">Pagos <?php echo htmlspecialchars(calendar_format_money($paidCents, $currency), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                  <?php endif; ?>
                                </div>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                    </td>
                  <?php
                    $cursor += $span;
                  endforeach;

                  for ($rest = $cursor; $rest < $dayCount; $rest++) {
                      $restDay = isset($calendarDayMap[$rest]) ? $calendarDayMap[$rest] : null;
                      $isMonthDivider = isset($monthDividerIndices[$rest]);
                      calendar_render_empty_cell($restDay, $roomCode, $roomName, $propertyCodeRow, $categoryCode, $isMonthDivider);
                  }
                  ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($occupancySummary && $propertyCode !== ''): ?>
          <div class="calendar-occupancy">
            <h3>Ocupacion diaria</h3>
            <div class="occupancy-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Ocupadas</th>
                    <th>Llegadas</th>
                    <th>Salidas</th>
                    <th>% Ocupacion</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($occupancySummary as $summaryRow):
                    $dateKey = isset($summaryRow['date_key']) ? (string)$summaryRow['date_key'] : '';
                    $isToday = $dateKey === $todayKey;
                  ?>
                    <tr class="<?php echo $isToday ? 'is-today-row' : ''; ?>">
                      <td><?php echo htmlspecialchars(calendar_format_date($dateKey, 'd M Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo isset($summaryRow['occupied_rooms']) ? (int)$summaryRow['occupied_rooms'] : 0; ?>/<?php echo (int)$totalRooms; ?></td>
                      <td><?php echo isset($summaryRow['arrivals']) ? (int)$summaryRow['arrivals'] : 0; ?></td>
                      <td><?php echo isset($summaryRow['departures']) ? (int)$summaryRow['departures'] : 0; ?></td>
                      <td><?php echo isset($summaryRow['occupancy_pct']) ? htmlspecialchars(number_format((float)$summaryRow['occupancy_pct'], 1), ENT_QUOTES, 'UTF-8') : '0.0'; ?>%</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <p class="muted">No hay habitaciones activas o reservas para el rango seleccionado.</p>
      <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="muted">No se encontraron datos de calendario.</p>
  <?php endif; ?>
</section>
<?php
$generalContent = ob_get_clean();

ob_start();
?>
<section class="card">
  <h2>Nuevo bloqueo de habitacion</h2>
  <?php if ($newBlockError): ?>
    <p class="error"><?php echo htmlspecialchars($newBlockError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php elseif ($newBlockMessage): ?>
    <p class="success"><?php echo htmlspecialchars($newBlockMessage, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
    <p class="muted">Registra un nuevo bloqueo para el rango seleccionado.</p>
  <?php endif; ?>
  <form method="post" class="form-grid grid-2" data-room-filter="block-new">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <?php calendar_render_context_hiddens($propertyCode, $baseDate, $viewModeKey, $orderMode); ?>
    <input type="hidden" name="calendar_action" value="create_block">
    <label>
      Propiedad
      <select name="block_property_code" required data-room-filter-prop>
        <option value="">Selecciona una opcion</option>
        <?php foreach ($properties as $property):
          $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
          $name = isset($property['name']) ? (string)$property['name'] : '';
          if ($code === '') {
              continue;
          }
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtoupper($code) === strtoupper($newBlockValues['property_code']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Habitacion
      <select name="block_room_code" required data-room-filter-room data-current-value="<?php echo htmlspecialchars($newBlockValues['room_code'], ENT_QUOTES, 'UTF-8'); ?>">
        <option value="">Selecciona una habitacion</option>
        <?php
          $newBlockPropertyUpper = strtoupper($newBlockValues['property_code']);
          $availableRooms = calendar_rooms_for_property($roomsByProperty, $newBlockPropertyUpper);
          foreach ($availableRooms as $room):
            $code = isset($room['code']) ? (string)$room['code'] : '';
            $label = calendar_room_label($room);
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtoupper($code) === strtoupper($newBlockValues['room_code']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Inicio
      <input type="date" name="block_start_date" value="<?php echo htmlspecialchars($newBlockValues['start_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
    </label>
    <label>
      Fin
      <input type="date" name="block_end_date" value="<?php echo htmlspecialchars($newBlockValues['end_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
    </label>
    <label class="full">
      Descripcion
      <textarea name="block_notes" rows="3"><?php echo htmlspecialchars($newBlockValues['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
    </label>
    <div class="form-actions full">
      <button type="submit">Crear bloqueo</button>
    </div>
  </form>
</section>
<?php
$newBlockContent = ob_get_clean();

ob_start();
?>
<section class="card">
  <h2>Bloqueos vigentes</h2>
  <?php if ($blockListError): ?>
    <p class="error"><?php echo htmlspecialchars($blockListError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($blockList): ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Propiedad</th>
            <th>Habitacion</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Descripcion</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($blockList as $block):
            $blockId = isset($block['id_room_block']) ? (int)$block['id_room_block'] : 0;
          ?>
            <tr>
              <td><?php echo htmlspecialchars(isset($block['code']) ? (string)$block['code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($block['property_code']) ? (string)$block['property_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($block['room_code']) ? (string)$block['room_code'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($block['start_date']) ? (string)$block['start_date'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($block['end_date']) ? (string)$block['end_date'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($block['description']) ? (string)$block['description'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <form method="post">
                  <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                  <?php calendar_render_context_hiddens($propertyCode, $baseDate, $viewModeKey, $orderMode); ?>
                  <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                  <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="block:<?php echo $blockId; ?>">
                  <button type="submit" class="button-secondary">Abrir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted">No hay bloqueos en el rango actual.</p>
  <?php endif; ?>
</section>
<?php
$blockListContent = ob_get_clean();

$staticTabs = array(
    array(
        'id' => 'general',
        'title' => 'General',
        'content' => $generalContent
    ),
    array(
        'id' => 'new',
        'title' => 'Nuevo bloqueo',
        'content' => $newBlockContent
    ),
    array(
        'id' => 'blocks',
        'title' => 'Bloqueos',
        'content' => $blockListContent
    )
);

$dynamicTabs = array();
foreach ($openBlockIds as $blockId) {
    $bundle = isset($blockDetails[$blockId]) ? $blockDetails[$blockId] : array('detail' => null, 'error' => null);
    $detail = isset($bundle['detail']) ? $bundle['detail'] : null;
    $detailError = isset($bundle['error']) ? $bundle['error'] : null;

    $detailPropertyCode = $detail && isset($detail['property_code']) ? strtoupper((string)$detail['property_code']) : (isset($_POST['block_edit_property_code']) ? strtoupper((string)$_POST['block_edit_property_code']) : '');
    $detailRoomCode = $detail && isset($detail['room_code']) ? strtoupper((string)$detail['room_code']) : (isset($_POST['block_edit_room_code']) ? strtoupper((string)$_POST['block_edit_room_code']) : '');

    if (isset($_POST['block_edit_property_code']) && isset($_POST['block_id']) && (int)$_POST['block_id'] === $blockId) {
        $detailPropertyCode = (string)$_POST['block_edit_property_code'];
    }
    if (isset($_POST['block_edit_room_code']) && isset($_POST['block_id']) && (int)$_POST['block_id'] === $blockId) {
        $detailRoomCode = (string)$_POST['block_edit_room_code'];
    }

    $availableRooms = calendar_rooms_for_property($roomsByProperty, strtoupper($detailPropertyCode));
    $roomInfo = calendar_find_room($roomsByProperty, strtoupper($detailPropertyCode), strtoupper($detailRoomCode));

    $tabLabel = $detail
        ? sprintf('%s %s - %s',
            isset($detail['room_code']) ? (string)$detail['room_code'] : '#',
            calendar_format_date($detail['start_date'], 'd M'),
            calendar_format_date($detail['end_date'], 'd M'))
        : ('Bloque ' . $blockId);
    $panelId = 'calendar-block-' . $blockId;
    $closeFormId = 'calendar-close-block-' . $blockId;

    ob_start();
    ?>
    <div class="subtab-actions">
      <div>
        <h3>Bloque <?php echo htmlspecialchars('#' . $tabLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
      </div>
      <div class="subtab-actions">
        <button class="button-secondary js-toggle-edit" data-label-view="Cancelar" data-label-edit="Editar">Editar</button>
        <form method="post" id="<?php echo htmlspecialchars($closeFormId, ENT_QUOTES, 'UTF-8'); ?>">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <?php calendar_render_context_hiddens($propertyCode, $baseDate, $viewModeKey, $orderMode); ?>
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="block:<?php echo $blockId; ?>">
          <button type="submit" class="button-secondary">Cerrar</button>
        </form>
      </div>
    </div>
    <?php if ($detailError): ?>
      <p class="error"><?php echo htmlspecialchars($detailError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php elseif (!$detail): ?>
      <p class="muted">No se encontro informacion para el bloqueo.</p>
    <?php else: ?>
      <?php if (isset($blockUpdateErrors[$blockId])): ?>
        <p class="error"><?php echo htmlspecialchars($blockUpdateErrors[$blockId], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php elseif (isset($blockUpdateMessages[$blockId])): ?>
        <p class="success"><?php echo htmlspecialchars($blockUpdateMessages[$blockId], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <form method="post" class="form-grid grid-2 block-edit-form">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <?php calendar_render_context_hiddens($propertyCode, $baseDate, $viewModeKey, $orderMode); ?>
        <input type="hidden" name="calendar_action" value="update_block">
        <input type="hidden" name="block_id" value="<?php echo (int)$blockId; ?>">

        <label>
          Propiedad
          <select name="block_edit_property_code" data-editable-field data-room-filter-prop disabled>
            <?php foreach ($properties as $property):
              $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
              $name = isset($property['name']) ? (string)$property['name'] : '';
              if ($code === '') {
                  continue;
              }
            ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtoupper($code) === strtoupper($detailPropertyCode) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Habitacion
          <select name="block_edit_room_code" data-editable-field data-room-filter-room data-current-value="<?php echo htmlspecialchars($detailRoomCode, ENT_QUOTES, 'UTF-8'); ?>" disabled>
            <?php foreach ($availableRooms as $room):
              $code = isset($room['code']) ? (string)$room['code'] : '';
              $label = calendar_room_label($room);
            ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtoupper($code) === strtoupper($detailRoomCode) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Inicio
          <input type="date" name="block_edit_start" value="<?php echo htmlspecialchars(calendar_format_date($detail['start_date'], 'Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" data-editable-field disabled>
        </label>
        <label>
          Fin
          <input type="date" name="block_edit_end" value="<?php echo htmlspecialchars(calendar_format_date($detail['end_date'], 'Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" data-editable-field disabled>
        </label>
        <label class="full">
          Descripcion
          <textarea name="block_edit_description" rows="3" data-editable-field disabled><?php echo htmlspecialchars((string)$detail['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        <div class="form-actions full">
          <button type="submit">Guardar cambios</button>
        </div>
      </form>

      <div class="subtab-info">
        <h4>Detalles</h4>
        <p>Propiedad: <?php echo htmlspecialchars((string)$detail['property_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string)$detail['property_code'], ENT_QUOTES, 'UTF-8'); ?>)</p>
        <p>Habitacion: <?php echo htmlspecialchars((string)$detail['room_code'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Creado: <?php echo htmlspecialchars(calendar_format_date($detail['created_at'], 'd M Y H:i'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Actualizado: <?php echo htmlspecialchars(calendar_format_date($detail['updated_at'], 'd M Y H:i'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($roomInfo): ?>
          <p>Categoria: <?php echo htmlspecialchars(isset($roomInfo['category_code']) && $roomInfo['category_code'] !== '' ? (string)$roomInfo['category_code'] : (isset($roomInfo['category_name']) ? (string)$roomInfo['category_name'] : ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    $panelContent = ob_get_clean();
    $dynamicTabs[] = array(
        'key' => 'block:' . $blockId,
        'title' => $tabLabel,
        'panel_id' => $panelId,
        'close_form_id' => $closeFormId,
        'content' => $panelContent
    );
}

$calendarDesktopActiveTab = isset($subtabState['active']) ? (string)$subtabState['active'] : 'static:general';
?>
<div class="calendar-desktop-toolbar">
  <details class="calendar-desktop-menu">
    <summary class="calendar-desktop-menu-toggle" aria-label="Abrir opciones del calendario" title="Opciones del calendario">
      <span></span>
      <span></span>
      <span></span>
    </summary>
    <div class="calendar-desktop-menu-panel">
      <div class="calendar-desktop-menu-group">
        <span class="calendar-desktop-menu-heading">Vistas</span>
        <div class="calendar-desktop-menu-actions">
          <?php foreach ($staticTabs as $desktopTab): ?>
            <?php
              $desktopTabId = isset($desktopTab['id']) ? (string)$desktopTab['id'] : '';
              $desktopTabTitle = isset($desktopTab['title']) ? (string)$desktopTab['title'] : $desktopTabId;
              $desktopTabKey = 'static:' . $desktopTabId;
            ?>
            <form method="post" class="calendar-desktop-menu-form">
              <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
              <?php calendar_render_context_hiddens($propertyCode, $baseDate, $viewModeKey, $orderMode); ?>
              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="activate">
              <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($desktopTabKey, ENT_QUOTES, 'UTF-8'); ?>">
              <button type="submit" class="calendar-desktop-menu-action <?php echo $calendarDesktopActiveTab === $desktopTabKey ? 'is-active' : ''; ?>">
                <?php echo htmlspecialchars($desktopTabTitle, ENT_QUOTES, 'UTF-8'); ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if ($dynamicTabs): ?>
        <div class="calendar-desktop-menu-group">
          <span class="calendar-desktop-menu-heading">Bloqueos abiertos</span>
          <div class="calendar-desktop-menu-actions">
            <?php foreach ($dynamicTabs as $desktopDynamicTab): ?>
              <?php
                $desktopDynamicKeyRaw = isset($desktopDynamicTab['key']) ? (string)$desktopDynamicTab['key'] : '';
                $desktopDynamicTitle = isset($desktopDynamicTab['title']) ? (string)$desktopDynamicTab['title'] : $desktopDynamicKeyRaw;
                $desktopDynamicKey = 'dynamic:' . $desktopDynamicKeyRaw;
              ?>
              <form method="post" class="calendar-desktop-menu-form">
                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                <?php calendar_render_context_hiddens($propertyCode, $baseDate, $viewModeKey, $orderMode); ?>
                <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="activate">
                <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($desktopDynamicKey, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="calendar-desktop-menu-action <?php echo $calendarDesktopActiveTab === $desktopDynamicKey ? 'is-active' : ''; ?>">
                  <?php echo htmlspecialchars($desktopDynamicTitle, ENT_QUOTES, 'UTF-8'); ?>
                </button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <div class="calendar-desktop-menu-group">
        <span class="calendar-desktop-menu-heading">Vista</span>
        <div class="calendar-daywidth-control">
          <label for="calendar-daywidth-slider" class="calendar-daywidth-label">Ancho celdas</label>
          <input
            id="calendar-daywidth-slider"
            class="calendar-daywidth-slider js-calendar-daywidth-slider"
            type="range"
            min="-50"
            max="50"
            step="5"
            value="0"
            aria-label="Ajustar ancho de columnas del calendario"
          >
          <span class="calendar-daywidth-value js-calendar-daywidth-value">0%</span>
        </div>
        <div class="calendar-rowheight-control">
          <label for="calendar-rowheight-slider" class="calendar-rowheight-label">Alto filas</label>
          <input
            id="calendar-rowheight-slider"
            class="calendar-rowheight-slider js-calendar-rowheight-slider"
            type="range"
            min="-50"
            max="50"
            step="5"
            value="0"
            aria-label="Ajustar altura de filas del calendario"
          >
          <span class="calendar-rowheight-value js-calendar-rowheight-value">0%</span>
        </div>
      </div>
    </div>
  </details>
</div>
<div class="calendar-subtabs-shell<?php echo $dynamicTabs ? ' has-dynamic-tabs' : ''; ?>">
<?php
pms_render_subtabs($moduleKey, $subtabState, $staticTabs, $dynamicTabs);
?>
</div>
<?php

$roomMapJson = json_encode($roomMapPayload, JSON_UNESCAPED_UNICODE);
$otaMapJson = json_encode($calendarOtaOptionsByProperty, JSON_UNESCAPED_UNICODE);
$sourceMapJson = json_encode($calendarSourceOptionsByProperty, JSON_UNESCAPED_UNICODE);
$paymentMapJson = json_encode($paymentCatalogsByProperty, JSON_UNESCAPED_UNICODE);
$paymentMapByReservationJson = json_encode($calendarPaymentCatalogsByReservation, JSON_UNESCAPED_UNICODE);
$serviceMapJson = json_encode($serviceCatalogsByProperty, JSON_UNESCAPED_UNICODE);
$paymentFoliosMapJson = json_encode($calendarPaymentFoliosByReservation, JSON_UNESCAPED_UNICODE);
?>
<script>
  window.pmsRoomMap = <?php echo $roomMapJson ? $roomMapJson : '{}'; ?>;
  window.pmsOtaMap = <?php echo $otaMapJson ? $otaMapJson : '{}'; ?>;
  window.pmsReservationSourceMap = <?php echo $sourceMapJson ? $sourceMapJson : '{}'; ?>;
  window.pmsCalendarPaymentCatalogMap = <?php echo $paymentMapJson ? $paymentMapJson : '{}'; ?>;
  window.pmsCalendarPaymentCatalogMapByReservation = <?php echo $paymentMapByReservationJson ? $paymentMapByReservationJson : '{}'; ?>;
  window.pmsCalendarServiceCatalogMap = <?php echo $serviceMapJson ? $serviceMapJson : '{}'; ?>;
  window.pmsCalendarPaymentFoliosByReservation = <?php echo $paymentFoliosMapJson ? $paymentFoliosMapJson : '{}'; ?>;
</script>
<?php

// Fin de calendario
?>
