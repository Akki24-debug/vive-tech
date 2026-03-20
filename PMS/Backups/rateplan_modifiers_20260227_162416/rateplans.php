
<?php
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyId === 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

$moduleKey = 'rateplans';
$properties = pms_fetch_properties($companyId);
$propertyIndex = array();
$propertiesByCode = array();
foreach ($properties as $property) {
    if (isset($property['code'], $property['id_property'])) {
        $code = strtoupper((string)$property['code']);
        $propertyIndex[$code] = (int)$property['id_property'];
        $propertiesByCode[$code] = $property;
    }
}

$selectedProperty = isset($_POST['rateplans_filter_property']) ? strtoupper(trim((string)$_POST['rateplans_filter_property'])) : '';
if ($selectedProperty !== '' && !isset($propertyIndex[$selectedProperty])) {
    $selectedProperty = '';
}
$selectedPropertyId = isset($propertyIndex[$selectedProperty]) ? $propertyIndex[$selectedProperty] : null;

$message = null;
$error = null;

$action = isset($_POST['rateplans_action']) ? (string)$_POST['rateplans_action'] : '';
$cloneRateplanCode = isset($_POST['rateplans_clone_code']) ? trim((string)$_POST['rateplans_clone_code']) : '';
$rateplanTabTarget = isset($_POST['rateplan_tab_target']) ? trim((string)$_POST['rateplan_tab_target']) : '';
if ($action === 'new_rateplan') {
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'rateplan:__new__';
    $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:__new__';
} elseif ($action === 'duplicate_rateplan') {
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'rateplan:__new__';
    $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:__new__';
} elseif ($action === 'save_rateplan') {
    $propertyCode = isset($_POST['rateplan_property_code']) ? strtoupper(trim((string)$_POST['rateplan_property_code'])) : '';
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $rateplanCodeOriginal = isset($_POST['rateplan_code_original']) ? strtoupper(trim((string)$_POST['rateplan_code_original'])) : '';
    $rateplanIdForm = isset($_POST['rateplan_id']) ? (int)$_POST['rateplan_id'] : 0;
    $rateplanName = isset($_POST['rateplan_name']) ? trim((string)$_POST['rateplan_name']) : '';
    $rateplanDescription = isset($_POST['rateplan_description']) ? trim((string)$_POST['rateplan_description']) : '';
    $rateplanCurrency = isset($_POST['rateplan_currency']) ? trim((string)$_POST['rateplan_currency']) : '';
    $rateplanRefundable = isset($_POST['rateplan_refundable']) ? 1 : 0;
    $rateplanMinStay = isset($_POST['rateplan_min_stay']) && $_POST['rateplan_min_stay'] !== '' ? (int)$_POST['rateplan_min_stay'] : null;
    $rateplanMaxStay = isset($_POST['rateplan_max_stay']) && $_POST['rateplan_max_stay'] !== '' ? (int)$_POST['rateplan_max_stay'] : null;
    $rateplanEffectiveFrom = isset($_POST['rateplan_effective_from']) && $_POST['rateplan_effective_from'] !== '' ? (string)$_POST['rateplan_effective_from'] : null;
    $rateplanEffectiveTo = isset($_POST['rateplan_effective_to']) && $_POST['rateplan_effective_to'] !== '' ? (string)$_POST['rateplan_effective_to'] : null;
    $rateplanIsActive = isset($_POST['rateplan_is_active']) ? 1 : 0;
    $rateplanCategoryIds = isset($_POST['rateplan_category_ids']) ? (array)$_POST['rateplan_category_ids'] : array();
    $rateplanCategoryIds = array_values(array_filter(array_map('intval', $rateplanCategoryIds), function ($value) {
        return $value > 0;
    }));
    $categoryBasePriceRaw = isset($_POST['rateplan_category_base_price']) ? trim((string)$_POST['rateplan_category_base_price']) : '';
    $categoryMinPriceRaw = isset($_POST['rateplan_category_min_price']) ? trim((string)$_POST['rateplan_category_min_price']) : '';
    $categoryBasePriceCents = $categoryBasePriceRaw !== '' ? (int)round(((float)str_replace(',', '.', $categoryBasePriceRaw)) * 100) : null;
    $categoryMinPriceCents = $categoryMinPriceRaw !== '' ? (int)round(((float)str_replace(',', '.', $categoryMinPriceRaw)) * 100) : null;

    if ($rateplanIdForm <= 0) {
        $rateplanCodeOriginal = '';
    }

    if ($propertyCode === '' || $rateplanCode === '' || $rateplanName === '') {
        $error = 'Propiedad, codigo y nombre son obligatorios.';
    } elseif (!$rateplanCategoryIds) {
        $error = 'Selecciona al menos una categoria para el plan.';
    } else {
        try {
            $propertyId = isset($propertyIndex[$propertyCode]) ? (int)$propertyIndex[$propertyCode] : 0;
            $lookupCode = $rateplanCodeOriginal !== '' ? $rateplanCodeOriginal : $rateplanCode;

            if ($rateplanCodeOriginal !== '' && $rateplanCodeOriginal !== $rateplanCode) {
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare(
                    'SELECT id_rateplan
                     FROM rateplan
                     WHERE id_property = ? AND code = ? AND deleted_at IS NULL
                     LIMIT 1'
                );
                $stmt->execute(array($propertyId, $rateplanCode));
                if ($stmt->fetchColumn() !== false) {
                    throw new Exception('Ya existe un plan con ese codigo.');
                }
            }

            $sets = pms_call_procedure('sp_rateplan_upsert', array(
                $propertyCode,
                $lookupCode,
                $rateplanName,
                $rateplanDescription === '' ? null : $rateplanDescription,
                $rateplanCurrency === '' ? null : $rateplanCurrency,
                $rateplanRefundable,
                $rateplanMinStay,
                $rateplanMaxStay,
                $rateplanEffectiveFrom,
                $rateplanEffectiveTo,
                $rateplanIsActive,
                $actorUserId
            ));
            $rateplanId = 0;
            if (isset($sets[0][0]['id_rateplan'])) {
                $rateplanId = (int)$sets[0][0]['id_rateplan'];
            }
            if ($rateplanId <= 0) {
                $pdo = isset($pdo) ? $pdo : pms_get_connection();
                $stmt = $pdo->prepare('SELECT id_rateplan FROM rateplan WHERE id_property = ? AND code = ? AND deleted_at IS NULL LIMIT 1');
                $stmt->execute(array($propertyId, $lookupCode));
                $rateplanId = (int)$stmt->fetchColumn();
            }
            if ($rateplanId > 0 && $rateplanCodeOriginal !== '' && $rateplanCodeOriginal !== $rateplanCode) {
                $pdo = isset($pdo) ? $pdo : pms_get_connection();
                $stmt = $pdo->prepare(
                    'UPDATE rateplan
                     SET code = ?, updated_at = NOW()
                     WHERE id_rateplan = ? AND id_property = ? AND deleted_at IS NULL'
                );
                $stmt->execute(array($rateplanCode, $rateplanId, $propertyId));
            }
            if ($rateplanId > 0) {
                if ($propertyId > 0) {
                    $pdo = isset($pdo) ? $pdo : pms_get_connection();
                    $stmt = $pdo->prepare(
                        'UPDATE roomcategory
                         SET id_rateplan = NULL, updated_at = NOW()
                         WHERE id_property = ? AND id_rateplan = ? AND deleted_at IS NULL'
                    );
                    $stmt->execute(array($propertyId, $rateplanId));

                    $inList = implode(',', array_fill(0, count($rateplanCategoryIds), '?'));
                    $params = array_merge(array($rateplanId, $propertyId), $rateplanCategoryIds);
                    $stmt = $pdo->prepare(
                        'UPDATE roomcategory
                         SET id_rateplan = ?, updated_at = NOW()
                         WHERE id_property = ? AND id_category IN (' . $inList . ') AND deleted_at IS NULL'
                    );
                    $stmt->execute($params);

                    if ($categoryBasePriceCents !== null || $categoryMinPriceCents !== null) {
                        $params = array_merge(array($categoryBasePriceCents, $categoryMinPriceCents, $propertyId), $rateplanCategoryIds);
                        $stmt = $pdo->prepare(
                            'UPDATE roomcategory
                             SET default_base_price_cents = COALESCE(?, default_base_price_cents),
                                 min_price_cents = COALESCE(?, min_price_cents),
                                 updated_at = NOW()
                             WHERE id_property = ? AND id_category IN (' . $inList . ') AND deleted_at IS NULL'
                        );
                        $stmt->execute($params);
                    }
                }
            }
            $message = 'Rate plan guardado.';
            $_POST[$moduleKey . '_subtab_action'] = 'close';
            $_POST[$moduleKey . '_subtab_target'] = $rateplanTabTarget !== '' ? $rateplanTabTarget : 'rateplan:' . $rateplanCode;
            $_POST[$moduleKey . '_current_subtab'] = 'static:general';
            $selectedProperty = $propertyCode;
            $selectedPropertyId = isset($propertyIndex[$propertyCode]) ? $propertyIndex[$propertyCode] : $selectedPropertyId;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'save_pricing') {
    $propertyCode = isset($_POST['rateplan_property_code']) ? strtoupper(trim((string)$_POST['rateplan_property_code'])) : '';
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $baseAdjust = isset($_POST['pricing_base_adjust_pct']) ? (float)$_POST['pricing_base_adjust_pct'] : null;
    $useSeason = isset($_POST['pricing_use_season']) ? 1 : 0;
    $useOccupancy = isset($_POST['pricing_use_occupancy']) ? 1 : 0;
    $lowThreshold = isset($_POST['pricing_occupancy_low']) ? (float)$_POST['pricing_occupancy_low'] : null;
    $midLowThreshold = isset($_POST['pricing_occupancy_mid_low']) ? (float)$_POST['pricing_occupancy_mid_low'] : null;
    $midHighThreshold = isset($_POST['pricing_occupancy_mid_high']) ? (float)$_POST['pricing_occupancy_mid_high'] : null;
    $highThreshold = isset($_POST['pricing_occupancy_high']) ? (float)$_POST['pricing_occupancy_high'] : null;
    $lowAdjust = isset($_POST['pricing_low_adjust']) ? (float)$_POST['pricing_low_adjust'] : null;
    $midLowAdjust = isset($_POST['pricing_mid_low_adjust']) ? (float)$_POST['pricing_mid_low_adjust'] : null;
    $midHighAdjust = isset($_POST['pricing_mid_high_adjust']) ? (float)$_POST['pricing_mid_high_adjust'] : null;
    $highAdjust = isset($_POST['pricing_high_adjust']) ? (float)$_POST['pricing_high_adjust'] : null;
    $weekendAdjust = isset($_POST['pricing_weekend_adjust']) ? (float)$_POST['pricing_weekend_adjust'] : null;
    $maxDiscount = isset($_POST['pricing_max_discount']) ? (float)$_POST['pricing_max_discount'] : null;
    $maxMarkup = isset($_POST['pricing_max_markup']) ? (float)$_POST['pricing_max_markup'] : null;
    $pricingActive = isset($_POST['pricing_is_active']) ? 1 : 0;

    try {
        pms_call_procedure('sp_rateplan_pricing_upsert', array(
            $propertyCode,
            $rateplanCode,
            $baseAdjust,
            $useSeason,
            $useOccupancy,
            $lowThreshold,
            $midLowThreshold,
            $midHighThreshold,
            $highThreshold,
            $lowAdjust,
            $midLowAdjust,
            $midHighAdjust,
            $highAdjust,
            $weekendAdjust,
            $maxDiscount,
            $maxMarkup,
            $pricingActive
        ));
        $message = 'Variables de tarifa actualizadas.';
        $_POST[$moduleKey . '_subtab_action'] = 'close';
        $_POST[$moduleKey . '_subtab_target'] = $rateplanTabTarget !== '' ? $rateplanTabTarget : 'rateplan:' . $rateplanCode;
        $_POST[$moduleKey . '_current_subtab'] = 'static:general';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} elseif ($action === 'save_season') {
    $propertyCode = isset($_POST['rateplan_property_code']) ? strtoupper(trim((string)$_POST['rateplan_property_code'])) : '';
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $seasonName = isset($_POST['season_name']) ? trim((string)$_POST['season_name']) : '';
    $seasonStart = isset($_POST['season_start']) ? (string)$_POST['season_start'] : '';
    $seasonEnd = isset($_POST['season_end']) ? (string)$_POST['season_end'] : '';
    $seasonAdjust = isset($_POST['season_adjust_pct']) ? (float)$_POST['season_adjust_pct'] : 0;
    $seasonPriority = isset($_POST['season_priority']) ? (int)$_POST['season_priority'] : 0;
    $seasonActive = isset($_POST['season_is_active']) ? 1 : 0;

    try {
        pms_call_procedure('sp_rateplan_season_upsert', array(
            $propertyCode,
            $rateplanCode,
            null,
            $seasonName,
            $seasonStart !== '' ? $seasonStart : null,
            $seasonEnd !== '' ? $seasonEnd : null,
            $seasonAdjust,
            $seasonPriority,
            $seasonActive
        ));
        $message = 'Temporada guardada.';
        $_POST[$moduleKey . '_subtab_action'] = 'close';
        $_POST[$moduleKey . '_subtab_target'] = $rateplanTabTarget !== '' ? $rateplanTabTarget : 'rateplan:' . $rateplanCode;
        $_POST[$moduleKey . '_current_subtab'] = 'static:general';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} elseif ($action === 'toggle_season') {
    $seasonId = isset($_POST['season_id']) ? (int)$_POST['season_id'] : 0;
    $seasonActive = isset($_POST['season_is_active']) ? 1 : 0;
    $rateplanId = isset($_POST['rateplan_id']) ? (int)$_POST['rateplan_id'] : 0;
    $rateplanCode = isset($_POST['rateplan_code']) ? trim((string)$_POST['rateplan_code']) : '';

    if ($seasonId > 0 && $rateplanId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE rateplan_season
                 SET is_active = ?, updated_at = NOW()
                 WHERE id_rateplan_season = ? AND id_rateplan = ?'
            );
            $stmt->execute(array($seasonActive, $seasonId, $rateplanId));
            $message = 'Temporada actualizada.';
            $_POST[$moduleKey . '_subtab_action'] = 'close';
            $_POST[$moduleKey . '_subtab_target'] = $rateplanTabTarget !== '' ? $rateplanTabTarget : 'rateplan:' . $rateplanCode;
            $_POST[$moduleKey . '_current_subtab'] = 'static:general';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'save_override') {
    $propertyCode = isset($_POST['rateplan_property_code']) ? strtoupper(trim((string)$_POST['rateplan_property_code'])) : '';
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $overrideDate = isset($_POST['override_date']) ? (string)$_POST['override_date'] : '';
    $overridePriceRaw = isset($_POST['override_price']) ? trim((string)$_POST['override_price']) : '';
    $overrideNotes = isset($_POST['override_notes']) ? trim((string)$_POST['override_notes']) : '';
    $overrideRoomId = isset($_POST['override_room_id']) && $_POST['override_room_id'] !== '' ? (int)$_POST['override_room_id'] : null;
    $overrideCategoryId = isset($_POST['override_category_id']) && $_POST['override_category_id'] !== '' ? (int)$_POST['override_category_id'] : null;
    $overrideActive = isset($_POST['override_is_active']) ? 1 : 0;

    $overridePriceCents = 0;
    if ($overridePriceRaw !== '') {
        $overridePriceCents = (int)round(((float)str_replace(',', '.', $overridePriceRaw)) * 100);
    }

    try {
        pms_call_procedure('sp_rateplan_override_upsert', array(
            $propertyCode,
            $rateplanCode,
            null,
            $overrideCategoryId,
            $overrideRoomId,
            $overrideDate !== '' ? $overrideDate : null,
            $overridePriceCents,
            $overrideNotes === '' ? null : $overrideNotes,
            $overrideActive
        ));
        $message = 'Override guardado.';
        $_POST[$moduleKey . '_subtab_action'] = 'close';
        $_POST[$moduleKey . '_subtab_target'] = $rateplanTabTarget !== '' ? $rateplanTabTarget : 'rateplan:' . $rateplanCode;
        $_POST[$moduleKey . '_current_subtab'] = 'static:general';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} elseif ($action === 'toggle_override') {
    $overrideId = isset($_POST['override_id']) ? (int)$_POST['override_id'] : 0;
    $overrideActive = isset($_POST['override_is_active']) ? 1 : 0;
    $rateplanId = isset($_POST['rateplan_id']) ? (int)$_POST['rateplan_id'] : 0;
    $rateplanCode = isset($_POST['rateplan_code']) ? trim((string)$_POST['rateplan_code']) : '';

    if ($overrideId > 0 && $rateplanId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE rateplan_override
                 SET is_active = ?, updated_at = NOW()
                 WHERE id_rateplan_override = ? AND id_rateplan = ?'
            );
            $stmt->execute(array($overrideActive, $overrideId, $rateplanId));
            $message = 'Override actualizado.';
            $_POST[$moduleKey . '_subtab_action'] = 'close';
            $_POST[$moduleKey . '_subtab_target'] = $rateplanTabTarget !== '' ? $rateplanTabTarget : 'rateplan:' . $rateplanCode;
            $_POST[$moduleKey . '_current_subtab'] = 'static:general';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
$rateplans = array();
$rateplanIndex = array();
$rateplanPricingIndex = array();
$categories = array();
$rooms = array();
if ($selectedPropertyId) {
    try {
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT rp.id_rateplan,
                    rp.code AS rateplan_code,
                    rp.name AS rateplan_name,
                    rp.description,
                    rp.currency,
                    rp.refundable,
                    rp.min_stay_default,
                    rp.max_stay_default,
                    rp.effective_from,
                    rp.effective_to,
                    rp.is_active,
                    p.code AS property_code,
                    p.name AS property_name,
                    rpp.base_adjust_pct,
                    rpp.use_season,
                    rpp.use_occupancy,
                    rpp.occupancy_low_threshold,
                    rpp.occupancy_mid_low_threshold,
                    rpp.occupancy_mid_high_threshold,
                    rpp.occupancy_high_threshold,
                    rpp.low_occupancy_adjust_pct,
                    rpp.mid_low_occupancy_adjust_pct,
                    rpp.mid_high_occupancy_adjust_pct,
                    rpp.high_occupancy_adjust_pct,
                    rpp.weekend_adjust_pct,
                    rpp.max_discount_pct,
                    rpp.max_markup_pct,
                    rpp.is_active AS pricing_is_active
             FROM rateplan rp
             JOIN property p ON p.id_property = rp.id_property
             LEFT JOIN rateplan_pricing rpp ON rpp.id_rateplan = rp.id_rateplan
             WHERE rp.id_property = ?
               AND rp.deleted_at IS NULL
             ORDER BY rp.name'
        );
        $stmt->execute(array($selectedPropertyId));
        $rateplans = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }

    foreach ($rateplans as $plan) {
        if (isset($plan['rateplan_code'])) {
            $rateplanIndex[(string)$plan['rateplan_code']] = $plan;
        }
        if (isset($plan['id_rateplan'])) {
            $rateplanPricingIndex[(int)$plan['id_rateplan']] = $plan;
        }
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id_category, code, name, default_base_price_cents, min_price_cents, id_rateplan
             FROM roomcategory
             WHERE id_property = ? AND deleted_at IS NULL
             ORDER BY name'
        );
        $stmt->execute(array($selectedPropertyId));
        $categories = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id_room, code, name, id_category
             FROM room
             WHERE id_property = ? AND deleted_at IS NULL
             ORDER BY code'
        );
        $stmt->execute(array($selectedPropertyId));
        $rooms = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
} else {
    try {
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT rp.id_rateplan,
                    rp.code AS rateplan_code,
                    rp.name AS rateplan_name,
                    rp.description,
                    rp.currency,
                    rp.refundable,
                    rp.min_stay_default,
                    rp.max_stay_default,
                    rp.effective_from,
                    rp.effective_to,
                    rp.is_active,
                    p.code AS property_code,
                    p.name AS property_name,
                    rpp.base_adjust_pct,
                    rpp.use_season,
                    rpp.use_occupancy,
                    rpp.occupancy_low_threshold,
                    rpp.occupancy_mid_low_threshold,
                    rpp.occupancy_mid_high_threshold,
                    rpp.occupancy_high_threshold,
                    rpp.low_occupancy_adjust_pct,
                    rpp.mid_low_occupancy_adjust_pct,
                    rpp.mid_high_occupancy_adjust_pct,
                    rpp.high_occupancy_adjust_pct,
                    rpp.weekend_adjust_pct,
                    rpp.max_discount_pct,
                    rpp.max_markup_pct,
                    rpp.is_active AS pricing_is_active
             FROM rateplan rp
             JOIN property p ON p.id_property = rp.id_property
             LEFT JOIN rateplan_pricing rpp ON rpp.id_rateplan = rp.id_rateplan
             WHERE p.id_company = ?
               AND rp.deleted_at IS NULL
             ORDER BY p.order_index, p.name, rp.name'
        );
        $stmt->execute(array($companyId));
        $rateplans = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }

    try {
        $pdo = isset($pdo) ? $pdo : pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT rc.id_category,
                    rc.code,
                    rc.name,
                    rc.default_base_price_cents,
                    rc.min_price_cents,
                    rc.id_rateplan,
                    p.code AS property_code,
                    p.name AS property_name
             FROM roomcategory rc
             JOIN property p ON p.id_property = rc.id_property
             WHERE p.id_company = ?
               AND rc.deleted_at IS NULL
             ORDER BY p.order_index, p.name, rc.order_index, rc.name'
        );
        $stmt->execute(array($companyId));
        $categories = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
}

$categoriesByRateplan = array();
foreach ($categories as $cat) {
    $rid = isset($cat['id_rateplan']) ? (int)$cat['id_rateplan'] : 0;
    if ($rid <= 0) {
        continue;
    }
    if (!isset($categoriesByRateplan[$rid])) {
        $categoriesByRateplan[$rid] = array();
    }
    $categoriesByRateplan[$rid][] = array(
        'id_category' => isset($cat['id_category']) ? (int)$cat['id_category'] : 0,
        'code' => isset($cat['code']) ? (string)$cat['code'] : '',
        'name' => isset($cat['name']) ? (string)$cat['name'] : '',
        'property_code' => isset($cat['property_code']) ? (string)$cat['property_code'] : $selectedProperty
    );
}

$categoryInfoById = array();
foreach ($categories as $cat) {
    if (!isset($cat['id_category'])) {
        continue;
    }
    $categoryInfoById[(int)$cat['id_category']] = array(
        'default_base_price_cents' => isset($cat['default_base_price_cents']) ? (int)$cat['default_base_price_cents'] : null,
        'min_price_cents' => isset($cat['min_price_cents']) ? (int)$cat['min_price_cents'] : null
    );
}

$subtabState = pms_subtabs_init($moduleKey, 'static:general');
$openRateplanKeys = isset($subtabState['open']) && is_array($subtabState['open']) ? $subtabState['open'] : array();
$validRateplanCodes = array();
foreach ($rateplans as $plan) {
    if (isset($plan['rateplan_code'])) {
        $validRateplanCodes[(string)$plan['rateplan_code']] = true;
    }
}
$cleanOpenKeys = array();
foreach ($openRateplanKeys as $openKey) {
    if (strpos($openKey, 'rateplan:') !== 0) {
        continue;
    }
    $code = substr($openKey, strlen('rateplan:'));
    if ($code === '__new__' || isset($validRateplanCodes[$code])) {
        $cleanOpenKeys[] = $openKey;
    }
}
if ($cleanOpenKeys !== $openRateplanKeys) {
    $subtabState['open'] = $cleanOpenKeys;
    if (isset($_SESSION['pms_subtabs'][$moduleKey])) {
        $_SESSION['pms_subtabs'][$moduleKey] = $subtabState;
    }
}

$calendarStart = isset($_POST['rateplan_calendar_start']) ? (string)$_POST['rateplan_calendar_start'] : date('Y-m-d');
$calendarDays = isset($_POST['rateplan_calendar_days']) ? (int)$_POST['rateplan_calendar_days'] : 14;
if (!in_array($calendarDays, array(7, 14, 21, 30), true)) {
    $calendarDays = 14;
}
$calendarCategoryCode = isset($_POST['rateplan_calendar_category']) ? (string)$_POST['rateplan_calendar_category'] : '';
$calendarRoomId = isset($_POST['rateplan_calendar_room']) ? (int)$_POST['rateplan_calendar_room'] : 0;
$calendarRateplanTarget = isset($_POST['rateplan_calendar_code']) ? (string)$_POST['rateplan_calendar_code'] : '';

if ($calendarCategoryCode === '' && $categories) {
    $calendarCategoryCode = (string)$categories[0]['code'];
}

ob_start();
?>
<div class="tab-actions">
  <form method="post" class="form-inline">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <label>
      Propiedad
      <select name="rateplans_filter_property" onchange="this.form.submit()">
        <option value="" <?php echo $selectedProperty === '' ? 'selected' : ''; ?>>Todas</option>
        <?php foreach ($properties as $property):
          $code = isset($property['code']) ? (string)$property['code'] : '';
          $name = isset($property['name']) ? (string)$property['name'] : '';
          if ($code === '') {
              continue;
          }
          $codeValue = strtoupper($code);
        ?>
          <option value="<?php echo htmlspecialchars($codeValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $codeValue === $selectedProperty ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($codeValue . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
  <form method="post">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <input type="hidden" name="rateplans_action" value="new_rateplan">
    <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:rateplan:__new__">
    <button type="submit">Nuevo plan</button>
  </form>
</div>

<?php if ($error): ?>
  <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($message): ?>
  <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if (!$rateplans): ?>
  <p class="muted"><?php echo $selectedProperty !== '' ? 'No hay planes registrados para esta propiedad.' : 'No hay planes registrados.'; ?></p>
<?php else: ?>
  <?php
    $rateplansGrouped = array();
    foreach ($rateplans as $plan) {
        if (isset($plan['is_active']) && (int)$plan['is_active'] !== 1) {
            continue;
        }
        $propCode = isset($plan['property_code']) ? strtoupper((string)$plan['property_code']) : $selectedProperty;
        if ($propCode === '') {
            continue;
        }
        if (!isset($rateplansGrouped[$propCode])) {
            $rateplansGrouped[$propCode] = array(
                'property_name' => isset($propertiesByCode[$propCode]['name']) ? (string)$propertiesByCode[$propCode]['name'] : $propCode,
                'rows' => array()
            );
        }
        $rateplansGrouped[$propCode]['rows'][] = $plan;
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
    <?php if (!isset($rateplansGrouped[$propCode])) { continue; } ?>
    <h3 class="property-group-title"><?php echo htmlspecialchars($rateplansGrouped[$propCode]['property_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Nombre</th>
            <th>Categoria</th>
            <th>Moneda</th>
            <th>Vigencia</th>
            <th>Activo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rateplansGrouped[$propCode]['rows'] as $plan):
            $planCode = isset($plan['rateplan_code']) ? (string)$plan['rateplan_code'] : '';
            $planId = isset($plan['id_rateplan']) ? (int)$plan['id_rateplan'] : 0;
            $activeLabel = isset($plan['is_active']) && (int)$plan['is_active'] === 1 ? 'Si' : 'No';
            $dateLabel = '';
            if (!empty($plan['effective_from'])) {
                $dateLabel = (string)$plan['effective_from'];
                if (!empty($plan['effective_to'])) {
                    $dateLabel .= ' / ' . (string)$plan['effective_to'];
                }
            }
            $linkedCategories = $planId > 0 && isset($categoriesByRateplan[$planId]) ? $categoriesByRateplan[$planId] : array();
          ?>
            <tr>
              <td><?php echo htmlspecialchars($planCode, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($plan['rateplan_name']) ? (string)$plan['rateplan_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($linkedCategories): ?>
                  <div class="room-tags">
                    <?php foreach ($linkedCategories as $cat):
                      $catCode = isset($cat['code']) ? (string)$cat['code'] : '';
                      $catName = isset($cat['name']) ? (string)$cat['name'] : '';
                      if ($catCode === '') {
                          continue;
                      }
                      $catProperty = isset($cat['property_code']) && $cat['property_code'] !== '' ? (string)$cat['property_code'] : $propCode;
                    ?>
                      <form method="post" action="index.php?view=categories" class="room-tag">
                        <input type="hidden" name="categories_subtab_action" value="open">
                        <input type="hidden" name="categories_subtab_target" value="category:<?php echo htmlspecialchars($catCode, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="categories_current_subtab" value="dynamic:category:<?php echo htmlspecialchars($catCode, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="categories_filter_property" value="<?php echo htmlspecialchars(strtoupper($catProperty), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="button-secondary" <?php echo $catName !== '' ? 'title="' . htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                          <?php echo htmlspecialchars($catCode, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                      </form>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span class="muted">Sin categoria</span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars(isset($plan['currency']) ? (string)$plan['currency'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo $activeLabel; ?></td>
              <td>
                <div class="row-actions">
                  <form method="post">
                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                    <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="rateplan:<?php echo htmlspecialchars($planCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:rateplan:<?php echo htmlspecialchars($planCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="button-secondary">Abrir</button>
                  </form>
                  <form method="post">
                    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                    <input type="hidden" name="rateplans_action" value="duplicate_rateplan">
                    <input type="hidden" name="rateplans_clone_code" value="<?php echo htmlspecialchars($planCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($propCode, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8'); ?>" value="dynamic:rateplan:__new__">
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
<?php endif; ?>
<?php
$listContent = ob_get_clean();

$dynamicTabs = array();
foreach ($subtabState['open'] as $openKey) {
    if (strpos($openKey, 'rateplan:') !== 0) {
        continue;
    }
    $code = substr($openKey, strlen('rateplan:'));
    $isNew = $code === '__new__';
      $plan = $isNew ? array(
          'id_rateplan' => 0,
          'rateplan_code' => '',
          'rateplan_name' => '',
          'description' => '',
          'currency' => 'MXN',
        'refundable' => 1,
        'min_stay_default' => null,
        'max_stay_default' => null,
        'effective_from' => date('Y-m-d'),
        'effective_to' => null,
        'is_active' => 1,
        'base_adjust_pct' => 0,
        'use_season' => 1,
        'use_occupancy' => 1,
        'occupancy_low_threshold' => 40,
        'occupancy_mid_low_threshold' => 55,
        'occupancy_mid_high_threshold' => 70,
        'occupancy_high_threshold' => 80,
        'low_occupancy_adjust_pct' => -15,
        'mid_low_occupancy_adjust_pct' => -5,
        'mid_high_occupancy_adjust_pct' => 10,
        'high_occupancy_adjust_pct' => 20,
        'weekend_adjust_pct' => 0,
        'max_discount_pct' => 30,
        'max_markup_pct' => 40,
        'pricing_is_active' => 1
      ) : (isset($rateplanIndex[$code]) ? $rateplanIndex[$code] : null);
      $cloneRateplanId = 0;
      if ($isNew && $cloneRateplanCode !== '' && isset($rateplanIndex[$cloneRateplanCode])) {
          $source = $rateplanIndex[$cloneRateplanCode];
          $cloneRateplanId = isset($source['id_rateplan']) ? (int)$source['id_rateplan'] : 0;
          $plan = $source;
          $plan['id_rateplan'] = 0;
          $plan['rateplan_code'] = '';
          $plan['rateplan_name'] = trim((string)$source['rateplan_name']) !== ''
              ? ((string)$source['rateplan_name'] . ' (copia)')
              : '';
          $plan['is_active'] = 1;
      }

    if (!$plan) {
        continue;
    }

    $planId = isset($plan['id_rateplan']) ? (int)$plan['id_rateplan'] : 0;
    $planPropertyCode = '';
    if (isset($plan['property_code']) && $plan['property_code'] !== '') {
        $planPropertyCode = strtoupper((string)$plan['property_code']);
    } elseif ($selectedProperty !== '') {
        $planPropertyCode = $selectedProperty;
    }
    $seasons = array();
    $overrides = array();
    if ($planId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM rateplan_season
                 WHERE id_rateplan = ?
                 ORDER BY start_date'
            );
            $stmt->execute(array($planId));
            $seasons = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                'SELECT ro.*,
                        rc.code AS category_code,
                        rm.code AS room_code
                 FROM rateplan_override ro
                 LEFT JOIN roomcategory rc ON rc.id_category = ro.id_category
                 LEFT JOIN room rm ON rm.id_room = ro.id_room
                 WHERE ro.id_rateplan = ?
                 ORDER BY ro.override_date DESC'
            );
            $stmt->execute(array($planId));
            $overrides = $stmt->fetchAll();
        } catch (Exception $e) {
            $error = $error ? $error : $e->getMessage();
        }
    }

    $calendarRows = array();
    if ($planId > 0 && $calendarRateplanTarget === $code && $calendarCategoryCode !== '') {
        try {
            $calendarRoomCode = '';
            if ($calendarRoomId > 0) {
                foreach ($rooms as $room) {
                    if ((int)$room['id_room'] === $calendarRoomId) {
                        $calendarRoomCode = (string)$room['code'];
                        break;
                    }
                }
            }
            $calendarRows = pms_call_single('sp_rateplan_calendar', array(
                $planPropertyCode,
                $code,
                $calendarCategoryCode,
                $calendarRoomCode,
                $calendarStart,
                $calendarDays
            ));
        } catch (Exception $e) {
            $error = $error ? $error : $e->getMessage();
        }
    }

    $selectedCategoryId = 0;
    $selectedCategoryIds = array();
    if ($planId > 0 && isset($categoriesByRateplan[$planId])) {
        foreach ($categoriesByRateplan[$planId] as $linkedCat) {
            if (!empty($linkedCat['id_category'])) {
                $selectedCategoryIds[] = (int)$linkedCat['id_category'];
            }
        }
    } elseif ($cloneRateplanId > 0 && isset($categoriesByRateplan[$cloneRateplanId])) {
        foreach ($categoriesByRateplan[$cloneRateplanId] as $linkedCat) {
            if (!empty($linkedCat['id_category'])) {
                $selectedCategoryIds[] = (int)$linkedCat['id_category'];
            }
        }
    }

    $planCategories = array();
    foreach ($categories as $cat) {
        if (isset($cat['property_code']) && $cat['property_code'] !== '' && $planPropertyCode !== '') {
            if (strtoupper((string)$cat['property_code']) !== $planPropertyCode) {
                continue;
            }
        }
        $planCategories[] = $cat;
    }

    if (!$selectedCategoryIds && $planCategories) {
        $selectedCategoryIds[] = (int)$planCategories[0]['id_category'];
    }

    $categoryBaseCents = null;
    $categoryMinCents = null;
    if ($selectedCategoryIds) {
        $firstCategoryId = (int)$selectedCategoryIds[0];
        if (isset($categoryInfoById[$firstCategoryId])) {
            $categoryBaseCents = $categoryInfoById[$firstCategoryId]['default_base_price_cents'];
            $categoryMinCents = $categoryInfoById[$firstCategoryId]['min_price_cents'];
        }
    }

    $panelId = 'rateplan-panel-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '__new__' ? 'new' : $code);
    $closeFormId = 'rateplan-close-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '__new__' ? 'new' : $code);
    ob_start();
    ?>
    <div class="subtab-actions">
      <div>
        <strong><?php echo htmlspecialchars($plan['rateplan_name'] !== '' ? $plan['rateplan_name'] : 'Nuevo plan', ENT_QUOTES, 'UTF-8'); ?></strong>
      </div>
      <form method="post" id="<?php echo htmlspecialchars($closeFormId, ENT_QUOTES, 'UTF-8'); ?>">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode !== '' ? $planPropertyCode : $selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
        <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="button-secondary">Cerrar</button>
      </form>
    </div>

    <form method="post" class="form-grid grid-3">
      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
      <input type="hidden" name="rateplans_action" value="save_rateplan">
      <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode !== '' ? $planPropertyCode : $selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="rateplan_tab_target" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="rateplan_id" value="<?php echo isset($plan['id_rateplan']) ? (int)$plan['id_rateplan'] : 0; ?>">
      <input type="hidden" name="rateplan_code_original" value="<?php echo htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8'); ?>">
      <?php if (!$isNew): ?>
        <input type="hidden" name="rateplan_property_code" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
      <?php endif; ?>
      <label>
        Propiedad *
        <select name="rateplan_property_code" <?php echo $isNew ? '' : 'disabled'; ?>>
          <?php foreach ($properties as $property):
            $propCode = isset($property['code']) ? (string)$property['code'] : '';
            $propName = isset($property['name']) ? (string)$property['name'] : '';
            if ($propCode === '') {
                continue;
            }
            $propCodeValue = strtoupper($propCode);
            $selected = $propCodeValue === $planPropertyCode;
          ?>
            <option value="<?php echo htmlspecialchars($propCodeValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($propCodeValue . ' - ' . $propName, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Categorias *
        <select name="rateplan_category_ids[]" multiple required size="6">
          <?php foreach ($planCategories as $cat):
            $catLabel = (string)$cat['code'] . ' - ' . (string)$cat['name'];
            if ($planPropertyCode === '' && isset($cat['property_code']) && $cat['property_code'] !== '') {
                $catLabel = (string)$cat['property_code'] . ' - ' . $catLabel;
            }
            $catId = (int)$cat['id_category'];
          ?>
            <option value="<?php echo $catId; ?>" <?php echo in_array($catId, $selectedCategoryIds, true) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Precio base
        <input type="number" step="0.01" min="0" name="rateplan_category_base_price" value="<?php echo $categoryBaseCents !== null ? htmlspecialchars(number_format($categoryBaseCents / 100, 2, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
      </label>
      <label>
        Precio minimo
        <input type="number" step="0.01" min="0" name="rateplan_category_min_price" value="<?php echo $categoryMinCents !== null ? htmlspecialchars(number_format($categoryMinCents / 100, 2, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
      </label>
      <label>
        Codigo *
        <input type="text" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>" required>
      </label>
      <label>
        Nombre *
        <input type="text" name="rateplan_name" value="<?php echo htmlspecialchars((string)$plan['rateplan_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
      </label>
      <label>
        Moneda
        <input type="text" name="rateplan_currency" value="<?php echo htmlspecialchars((string)$plan['currency'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Vigente desde
        <input type="date" name="rateplan_effective_from" value="<?php echo htmlspecialchars((string)$plan['effective_from'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Vigente hasta
        <input type="date" name="rateplan_effective_to" value="<?php echo htmlspecialchars((string)$plan['effective_to'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Min noches
        <input type="number" name="rateplan_min_stay" min="0" value="<?php echo htmlspecialchars((string)$plan['min_stay_default'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max noches
        <input type="number" name="rateplan_max_stay" min="0" value="<?php echo htmlspecialchars((string)$plan['max_stay_default'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label class="checkbox">
        <input type="checkbox" name="rateplan_refundable" value="1" <?php echo isset($plan['refundable']) && (int)$plan['refundable'] === 1 ? 'checked' : ''; ?>>
        Reembolsable
      </label>
      <label class="checkbox">
        <input type="checkbox" name="rateplan_is_active" value="1" <?php echo isset($plan['is_active']) && (int)$plan['is_active'] === 1 ? 'checked' : ''; ?>>
        Activo
      </label>
      <label class="full">
        Descripcion
        <textarea name="rateplan_description" rows="3"><?php echo htmlspecialchars((string)$plan['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>
      <div class="form-actions full">
        <button type="submit">Guardar plan</button>
      </div>
    </form>

    <div class="subtab-info">
      <h4>Variables de precio</h4>
      <form method="post" class="form-grid grid-3">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="rateplans_action" value="save_pricing">
        <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_property_code" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_tab_target" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">

        <label>
          Ajuste base (%)
          <input type="number" step="0.01" name="pricing_base_adjust_pct" value="<?php echo htmlspecialchars((string)$plan['base_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Umbral ocupacion baja (%)
          <input type="number" step="0.01" name="pricing_occupancy_low" value="<?php echo htmlspecialchars((string)$plan['occupancy_low_threshold'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Umbral ocupacion media baja (%)
          <input type="number" step="0.01" name="pricing_occupancy_mid_low" value="<?php echo htmlspecialchars((string)$plan['occupancy_mid_low_threshold'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Umbral ocupacion media alta (%)
          <input type="number" step="0.01" name="pricing_occupancy_mid_high" value="<?php echo htmlspecialchars((string)$plan['occupancy_mid_high_threshold'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Umbral ocupacion alta (%)
          <input type="number" step="0.01" name="pricing_occupancy_high" value="<?php echo htmlspecialchars((string)$plan['occupancy_high_threshold'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Ajuste baja (%)
          <input type="number" step="0.01" name="pricing_low_adjust" value="<?php echo htmlspecialchars((string)$plan['low_occupancy_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Ajuste media baja (%)
          <input type="number" step="0.01" name="pricing_mid_low_adjust" value="<?php echo htmlspecialchars((string)$plan['mid_low_occupancy_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Ajuste media alta (%)
          <input type="number" step="0.01" name="pricing_mid_high_adjust" value="<?php echo htmlspecialchars((string)$plan['mid_high_occupancy_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Ajuste alta (%)
          <input type="number" step="0.01" name="pricing_high_adjust" value="<?php echo htmlspecialchars((string)$plan['high_occupancy_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Ajuste fin de semana (%)
          <input type="number" step="0.01" name="pricing_weekend_adjust" value="<?php echo htmlspecialchars((string)$plan['weekend_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Max descuento (%)
          <input type="number" step="0.01" name="pricing_max_discount" value="<?php echo htmlspecialchars((string)$plan['max_discount_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Max aumento (%)
          <input type="number" step="0.01" name="pricing_max_markup" value="<?php echo htmlspecialchars((string)$plan['max_markup_pct'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label class="checkbox">
          <input type="checkbox" name="pricing_use_season" value="1" <?php echo isset($plan['use_season']) && (int)$plan['use_season'] === 1 ? 'checked' : ''; ?>>
          Aplicar temporadas
        </label>
        <label class="checkbox">
          <input type="checkbox" name="pricing_use_occupancy" value="1" <?php echo isset($plan['use_occupancy']) && (int)$plan['use_occupancy'] === 1 ? 'checked' : ''; ?>>
          Ajustar por ocupacion
        </label>
        <label class="checkbox">
          <input type="checkbox" name="pricing_is_active" value="1" <?php echo isset($plan['pricing_is_active']) && (int)$plan['pricing_is_active'] === 1 ? 'checked' : ''; ?>>
          Reglas activas
        </label>
        <div class="form-actions full">
          <button type="submit">Guardar ajustes</button>
        </div>
      </form>
    </div>

    <div class="subtab-info">
      <h4>Temporadas</h4>
      <?php if ($seasons): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Rango</th>
                <th>Ajuste %</th>
                <th>Prioridad</th>
                <th>Activa</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($seasons as $season): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$season['season_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$season['start_date'] . ' / ' . (string)$season['end_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$season['adjust_pct'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo (int)$season['priority']; ?></td>
                  <td><?php echo isset($season['is_active']) && (int)$season['is_active'] === 1 ? 'Si' : 'No'; ?></td>
                  <td>
                    <form method="post">
                      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                      <input type="hidden" name="rateplans_action" value="toggle_season">
                      <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="season_id" value="<?php echo (int)$season['id_rateplan_season']; ?>">
                      <input type="hidden" name="season_is_active" value="<?php echo isset($season['is_active']) && (int)$season['is_active'] === 1 ? 0 : 1; ?>">
                      <input type="hidden" name="rateplan_id" value="<?php echo $planId; ?>">
                      <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="rateplan_tab_target" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                      <button type="submit" class="button-secondary"><?php echo isset($season['is_active']) && (int)$season['is_active'] === 1 ? 'Desactivar' : 'Activar'; ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">Sin temporadas configuradas.</p>
      <?php endif; ?>

      <form method="post" class="form-grid grid-3">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="rateplans_action" value="save_season">
        <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_property_code" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_tab_target" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">

        <label>
          Nombre
          <input type="text" name="season_name" required>
        </label>
        <label>
          Desde
          <input type="date" name="season_start" required>
        </label>
        <label>
          Hasta
          <input type="date" name="season_end" required>
        </label>
        <label>
          Ajuste (%)
          <input type="number" step="0.01" name="season_adjust_pct" value="0">
        </label>
        <label>
          Prioridad
          <input type="number" name="season_priority" value="0">
        </label>
        <label class="checkbox">
          <input type="checkbox" name="season_is_active" value="1" checked>
          Activa
        </label>
        <div class="form-actions full">
          <button type="submit">Agregar temporada</button>
        </div>
      </form>
    </div>

    <div class="subtab-info">
      <h4>Overrides de precio</h4>
      <?php if ($overrides): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Scope</th>
                <th>Precio</th>
                <th>Notas</th>
                <th>Activa</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($overrides as $override):
                $scopeLabel = 'General';
                if (!empty($override['room_code'])) {
                    $scopeLabel = 'Habitacion ' . $override['room_code'];
                } elseif (!empty($override['category_code'])) {
                    $scopeLabel = 'Categoria ' . $override['category_code'];
                }
              ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$override['override_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo number_format(((int)$override['price_cents']) / 100, 2); ?></td>
                  <td><?php echo htmlspecialchars((string)$override['notes'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo isset($override['is_active']) && (int)$override['is_active'] === 1 ? 'Si' : 'No'; ?></td>
                  <td>
                    <form method="post">
                      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                      <input type="hidden" name="rateplans_action" value="toggle_override">
                      <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="override_id" value="<?php echo (int)$override['id_rateplan_override']; ?>">
                      <input type="hidden" name="override_is_active" value="<?php echo isset($override['is_active']) && (int)$override['is_active'] === 1 ? 0 : 1; ?>">
                      <input type="hidden" name="rateplan_id" value="<?php echo $planId; ?>">
                      <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="rateplan_tab_target" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                      <button type="submit" class="button-secondary"><?php echo isset($override['is_active']) && (int)$override['is_active'] === 1 ? 'Desactivar' : 'Activar'; ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">Sin overrides configurados.</p>
      <?php endif; ?>

      <form method="post" class="form-grid grid-3">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="rateplans_action" value="save_override">
        <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_property_code" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_tab_target" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">

        <label>
          Fecha
          <input type="date" name="override_date" required>
        </label>
        <label>
          Precio fijo
          <input type="number" step="0.01" name="override_price" required>
        </label>
        <label>
          Categoria
          <select name="override_category_id">
            <option value="">General</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int)$cat['id_category']; ?>">
                <?php echo htmlspecialchars((string)$cat['code'] . ' - ' . (string)$cat['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Habitacion
          <select name="override_room_id">
            <option value="">Todas</option>
            <?php foreach ($rooms as $room): ?>
              <option value="<?php echo (int)$room['id_room']; ?>">
                <?php echo htmlspecialchars((string)$room['code'] . ' - ' . (string)$room['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="full">
          Notas
          <input type="text" name="override_notes">
        </label>
        <label class="checkbox">
          <input type="checkbox" name="override_is_active" value="1" checked>
          Activo
        </label>
        <div class="form-actions full">
          <button type="submit">Agregar override</button>
        </div>
      </form>
    </div>
    <div class="subtab-info">
      <h4>Calendario de precios</h4>
      <form method="post" class="form-grid grid-3">
        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
        <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_calendar_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="rateplan_tab_target" value="rateplan:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
        <label>
          Desde
          <input type="date" name="rateplan_calendar_start" value="<?php echo htmlspecialchars($calendarStart, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          Periodo
          <select name="rateplan_calendar_days">
            <?php foreach (array(7, 14, 21, 30) as $daysOption): ?>
              <option value="<?php echo $daysOption; ?>" <?php echo $calendarDays === $daysOption ? 'selected' : ''; ?>>
                <?php echo $daysOption; ?> dias
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Categoria
          <select name="rateplan_calendar_category">
            <?php foreach ($planCategories as $cat): ?>
              <option value="<?php echo htmlspecialchars((string)$cat['code'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $calendarCategoryCode === (string)$cat['code'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)$cat['code'] . ' - ' . (string)$cat['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Habitacion
          <select name="rateplan_calendar_room">
            <option value="">Todas</option>
            <?php foreach ($rooms as $room): ?>
              <option value="<?php echo (int)$room['id_room']; ?>" <?php echo $calendarRoomId === (int)$room['id_room'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)$room['code'] . ' - ' . (string)$room['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="form-actions full">
          <button type="submit">Ver calendario</button>
        </div>
      </form>

      <?php if ($calendarRows): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Base</th>
                <th>Temporada %</th>
                <th>Ocupacion %</th>
                <th>Ajuste ocupacion %</th>
                <th>Override</th>
                <th>Precio final</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($calendarRows as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$row['calendar_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo number_format(((int)$row['base_adjusted_cents']) / 100, 2); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['season_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['occupancy_pct'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['occupancy_adjust_pct'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo $row['override_price_cents'] !== null ? number_format(((int)$row['override_price_cents']) / 100, 2) : '-'; ?></td>
                  <td><?php echo number_format(((int)$row['final_price_cents']) / 100, 2); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">Selecciona rango y categoria para ver el calendario.</p>
      <?php endif; ?>
    </div>
    <?php
    $panelContent = ob_get_clean();
    $dynamicTabs[] = array(
        'key' => 'rateplan:' . $code,
        'title' => $isNew ? 'Nuevo' : $code,
        'panel_id' => 'rateplan-panel-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '__new__' ? 'new' : $code),
        'close_form_id' => 'rateplan-close-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '__new__' ? 'new' : $code),
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

pms_render_subtabs($moduleKey, $subtabState, $staticTabs, $dynamicTabs);
?>
