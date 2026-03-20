
<?php
require_once __DIR__ . '/../services/RateplanPricingService.php';
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyId === 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('rateplans.view');

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

$isGetRequest = isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'GET';
if ($isGetRequest) {
    $filterPropertyFromGet = isset($_GET['rateplans_filter_property']) ? strtoupper(trim((string)$_GET['rateplans_filter_property'])) : '';
    $openRateplan = isset($_GET['open_rateplan']) ? strtoupper(trim((string)$_GET['open_rateplan'])) : '';
    if ($filterPropertyFromGet !== '' && isset($propertyIndex[$filterPropertyFromGet])) {
        $_POST['rateplans_filter_property'] = $filterPropertyFromGet;
        $selectedProperty = $filterPropertyFromGet;
        $selectedPropertyId = isset($propertyIndex[$selectedProperty]) ? $propertyIndex[$selectedProperty] : null;
    }
    if ($openRateplan !== '') {
        $_POST[$moduleKey . '_subtab_action'] = 'open';
        $_POST[$moduleKey . '_subtab_target'] = 'rateplan:' . $openRateplan;
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:' . $openRateplan;
    }
}

$message = null;
$error = null;

$action = isset($_POST['rateplans_action']) ? (string)$_POST['rateplans_action'] : '';
$cloneRateplanCode = isset($_POST['rateplans_clone_code']) ? trim((string)$_POST['rateplans_clone_code']) : '';
$rateplanTabTarget = isset($_POST['rateplan_tab_target']) ? trim((string)$_POST['rateplan_tab_target']) : '';
if (in_array($action, array('new_rateplan', 'duplicate_rateplan'), true)) {
    pms_require_permission('rateplans.create');
} elseif ($action === 'save_rateplan') {
    $incomingRateplanId = isset($_POST['rateplan_id']) ? (int)$_POST['rateplan_id'] : 0;
    pms_require_permission($incomingRateplanId > 0 ? 'rateplans.edit' : 'rateplans.create');
} elseif (in_array($action, array(
    'save_modifier',
    'toggle_modifier',
    'save_modifier_schedule',
    'save_modifier_condition',
    'save_modifier_scope',
    'save_override',
    'toggle_override'
), true)) {
    pms_require_permission('rateplans.edit');
}
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
} elseif ($action === 'save_modifier') {
    $rateplanId = isset($_POST['rateplan_id']) ? (int)$_POST['rateplan_id'] : 0;
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $propertyCode = isset($_POST['rateplan_property_code']) ? strtoupper(trim((string)$_POST['rateplan_property_code'])) : '';
    $modifierId = isset($_POST['modifier_id']) ? (int)$_POST['modifier_id'] : 0;
    $modifierName = trim((string)(isset($_POST['modifier_name']) ? $_POST['modifier_name'] : ''));
    $priority = isset($_POST['modifier_priority']) ? (int)$_POST['modifier_priority'] : 0;
    $applyMode = strtolower(trim((string)(isset($_POST['modifier_apply_mode']) ? $_POST['modifier_apply_mode'] : 'stack')));
    $priceAction = strtolower(trim((string)(isset($_POST['modifier_price_action']) ? $_POST['modifier_price_action'] : 'add_pct')));
    $addPct = isset($_POST['modifier_add_pct']) && $_POST['modifier_add_pct'] !== '' ? (float)$_POST['modifier_add_pct'] : null;
    $addCents = isset($_POST['modifier_add_cents']) && $_POST['modifier_add_cents'] !== '' ? (int)$_POST['modifier_add_cents'] : null;
    $setPrice = isset($_POST['modifier_set_price_cents']) && $_POST['modifier_set_price_cents'] !== '' ? (int)$_POST['modifier_set_price_cents'] : null;
    $clampMin = isset($_POST['modifier_clamp_min_cents']) && $_POST['modifier_clamp_min_cents'] !== '' ? (int)$_POST['modifier_clamp_min_cents'] : null;
    $clampMax = isset($_POST['modifier_clamp_max_cents']) && $_POST['modifier_clamp_max_cents'] !== '' ? (int)$_POST['modifier_clamp_max_cents'] : null;
    $isAlwaysOn = isset($_POST['modifier_is_always_on']) ? 1 : 0;
    $respectCategoryMin = isset($_POST['modifier_respect_category_min']) ? 1 : 0;
    $isActive = isset($_POST['modifier_is_active']) ? 1 : 0;
    $description = trim((string)(isset($_POST['modifier_description']) ? $_POST['modifier_description'] : ''));

    try {
        if ($rateplanId <= 0 && $propertyCode !== '' && $rateplanCode !== '') {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT rp.id_rateplan
                 FROM rateplan rp
                 JOIN property p ON p.id_property = rp.id_property
                 WHERE p.code = ? AND rp.code = ? AND p.id_company = ? AND rp.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($propertyCode, $rateplanCode, $companyId));
            $rateplanId = (int)$stmt->fetchColumn();
        }
        if ($rateplanId <= 0) {
            throw new Exception('No se encontro el rateplan para guardar modificador.');
        }
        if ($modifierName === '') {
            throw new Exception('El nombre del modificador es obligatorio.');
        }

        $allowedModes = array('stack', 'best_for_guest', 'best_for_property', 'override');
        $allowedActions = array('add_pct', 'add_cents', 'set_price');
        if (!in_array($applyMode, $allowedModes, true)) {
            $applyMode = 'stack';
        }
        if (!in_array($priceAction, $allowedActions, true)) {
            $priceAction = 'add_pct';
        }

        $pdo = isset($pdo) ? $pdo : pms_get_connection();
        if ($modifierId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE rateplan_modifier
                 SET modifier_name = ?,
                     description = ?,
                     priority = ?,
                     apply_mode = ?,
                     price_action = ?,
                     add_pct = ?,
                     add_cents = ?,
                     set_price_cents = ?,
                     clamp_min_cents = ?,
                     clamp_max_cents = ?,
                     respect_category_min = ?,
                     is_always_on = ?,
                     is_active = ?,
                     updated_at = NOW()
                 WHERE id_rateplan_modifier = ? AND id_rateplan = ?'
            );
            $stmt->execute(array(
                $modifierName,
                $description !== '' ? $description : null,
                $priority,
                $applyMode,
                $priceAction,
                $addPct,
                $addCents,
                $setPrice,
                $clampMin,
                $clampMax,
                $respectCategoryMin,
                $isAlwaysOn,
                $isActive,
                $modifierId,
                $rateplanId
            ));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO rateplan_modifier (
                    id_rateplan,
                    modifier_name,
                    description,
                    priority,
                    apply_mode,
                    price_action,
                    add_pct,
                    add_cents,
                    set_price_cents,
                    clamp_min_cents,
                    clamp_max_cents,
                    respect_category_min,
                    is_always_on,
                    is_active,
                    created_at,
                    created_by,
                    updated_at
                 ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW()
                 )'
            );
            $stmt->execute(array(
                $rateplanId,
                $modifierName,
                $description !== '' ? $description : null,
                $priority,
                $applyMode,
                $priceAction,
                $addPct,
                $addCents,
                $setPrice,
                $clampMin,
                $clampMax,
                $respectCategoryMin,
                $isAlwaysOn,
                $isActive,
                $actorUserId
            ));
        }

        $message = 'Modificador guardado.';
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:' . $rateplanCode;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} elseif ($action === 'toggle_modifier') {
    $modifierId = isset($_POST['modifier_id']) ? (int)$_POST['modifier_id'] : 0;
    $rateplanId = isset($_POST['rateplan_id']) ? (int)$_POST['rateplan_id'] : 0;
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $isActive = isset($_POST['modifier_is_active']) ? (int)$_POST['modifier_is_active'] : 0;
    try {
        if ($modifierId <= 0 || $rateplanId <= 0) {
            throw new Exception('Faltan datos para actualizar modificador.');
        }
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'UPDATE rateplan_modifier
             SET is_active = ?, updated_at = NOW()
             WHERE id_rateplan_modifier = ? AND id_rateplan = ?'
        );
        $stmt->execute(array($isActive, $modifierId, $rateplanId));
        $message = 'Estado de modificador actualizado.';
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:' . $rateplanCode;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} elseif ($action === 'save_modifier_schedule') {
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $modifierId = isset($_POST['schedule_modifier_id']) ? (int)$_POST['schedule_modifier_id'] : 0;
    $scheduleType = strtolower(trim((string)(isset($_POST['schedule_type']) ? $_POST['schedule_type'] : 'range')));
    $scheduleStart = isset($_POST['schedule_start_date']) && $_POST['schedule_start_date'] !== '' ? (string)$_POST['schedule_start_date'] : null;
    $scheduleEnd = isset($_POST['schedule_end_date']) && $_POST['schedule_end_date'] !== '' ? (string)$_POST['schedule_end_date'] : null;
    $scheduleRrule = isset($_POST['schedule_rrule']) && trim((string)$_POST['schedule_rrule']) !== '' ? trim((string)$_POST['schedule_rrule']) : null;
    $scheduleExdates = isset($_POST['schedule_exdates_json']) && trim((string)$_POST['schedule_exdates_json']) !== '' ? trim((string)$_POST['schedule_exdates_json']) : null;
    $scheduleExdatesText = isset($_POST['schedule_exdates_text']) ? trim((string)$_POST['schedule_exdates_text']) : '';
    $rruleFreq = strtoupper(trim((string)(isset($_POST['schedule_rrule_freq']) ? $_POST['schedule_rrule_freq'] : 'WEEKLY')));
    $rruleInterval = isset($_POST['schedule_rrule_interval']) && (int)$_POST['schedule_rrule_interval'] > 0 ? (int)$_POST['schedule_rrule_interval'] : 1;
    $rruleUntil = isset($_POST['schedule_rrule_until']) && trim((string)$_POST['schedule_rrule_until']) !== '' ? trim((string)$_POST['schedule_rrule_until']) : null;
    $rruleByday = isset($_POST['schedule_rrule_byday']) ? (array)$_POST['schedule_rrule_byday'] : array();
    $rruleByday = array_values(array_filter(array_map(function ($v) {
        $v = strtoupper(trim((string)$v));
        return in_array($v, array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'), true) ? $v : null;
    }, $rruleByday)));
    $rruleBymonthday = isset($_POST['schedule_rrule_bymonthday']) && $_POST['schedule_rrule_bymonthday'] !== '' ? (int)$_POST['schedule_rrule_bymonthday'] : null;
    $rruleBymonth = isset($_POST['schedule_rrule_bymonth']) && $_POST['schedule_rrule_bymonth'] !== '' ? (int)$_POST['schedule_rrule_bymonth'] : null;

    try {
        if ($modifierId <= 0) {
            throw new Exception('Selecciona un modificador para agregar horario.');
        }
        if (!in_array($scheduleType, array('range', 'rrule'), true)) {
            $scheduleType = 'range';
        }
        if ($scheduleType === 'range' && ($scheduleStart === null || $scheduleEnd === null)) {
            throw new Exception('Rango invalido: define inicio y fin.');
        }
        if ($scheduleType === 'rrule' && $scheduleRrule === null) {
            $parts = array();
            if (!in_array($rruleFreq, array('WEEKLY', 'MONTHLY', 'YEARLY'), true)) {
                $rruleFreq = 'WEEKLY';
            }
            $parts[] = 'FREQ=' . $rruleFreq;
            if ($rruleInterval > 1) {
                $parts[] = 'INTERVAL=' . $rruleInterval;
            }
            if ($rruleFreq === 'WEEKLY' && $rruleByday) {
                $parts[] = 'BYDAY=' . implode(',', $rruleByday);
            }
            if ($rruleFreq === 'MONTHLY' && $rruleBymonthday !== null && $rruleBymonthday >= 1 && $rruleBymonthday <= 31) {
                $parts[] = 'BYMONTHDAY=' . $rruleBymonthday;
            }
            if ($rruleFreq === 'YEARLY') {
                if ($rruleBymonth !== null && $rruleBymonth >= 1 && $rruleBymonth <= 12) {
                    $parts[] = 'BYMONTH=' . $rruleBymonth;
                }
                if ($rruleBymonthday !== null && $rruleBymonthday >= 1 && $rruleBymonthday <= 31) {
                    $parts[] = 'BYMONTHDAY=' . $rruleBymonthday;
                }
            }
            if ($rruleUntil !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rruleUntil)) {
                $parts[] = 'UNTIL=' . str_replace('-', '', $rruleUntil);
            }
            $scheduleRrule = implode(';', $parts);
        }
        if ($scheduleType === 'rrule' && $scheduleRrule === null) {
            throw new Exception('Define una recurrencia valida (semanal, mensual o anual).');
        }

        if ($scheduleExdates === null && $scheduleExdatesText !== '') {
            $rawDates = preg_split('/[\s,;]+/', $scheduleExdatesText);
            $cleanDates = array();
            foreach ((array)$rawDates as $rawDate) {
                $d = trim((string)$rawDate);
                if ($d === '') {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    $cleanDates[] = $d;
                }
            }
            if ($cleanDates) {
                $scheduleExdates = json_encode(array_values(array_unique($cleanDates)), JSON_UNESCAPED_UNICODE);
            }
        } elseif ($scheduleExdates !== null) {
            $decoded = json_decode($scheduleExdates, true);
            if (!is_array($decoded)) {
                throw new Exception('Exdates invalido. Usa JSON de arreglo de fechas o el campo de fechas separadas.');
            }
        }

        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'INSERT INTO rateplan_modifier_schedule (
                id_rateplan_modifier,
                schedule_type,
                start_date,
                end_date,
                schedule_rrule,
                exdates_json,
                is_active,
                created_at,
                created_by,
                updated_at
             ) VALUES (
                ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW()
             )'
        );
        $stmt->execute(array(
            $modifierId,
            $scheduleType,
            $scheduleStart,
            $scheduleEnd,
            $scheduleRrule,
            $scheduleExdates,
            $actorUserId
        ));
        $message = 'Horario agregado al modificador.';
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:' . $rateplanCode;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} elseif ($action === 'save_modifier_condition') {
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $modifierId = isset($_POST['condition_modifier_id']) ? (int)$_POST['condition_modifier_id'] : 0;
    $conditionType = trim((string)(isset($_POST['condition_type']) ? $_POST['condition_type'] : ''));
    $operatorKey = strtolower(trim((string)(isset($_POST['condition_operator']) ? $_POST['condition_operator'] : 'eq')));
    $valueNumber = isset($_POST['condition_value_number']) && $_POST['condition_value_number'] !== '' ? (float)$_POST['condition_value_number'] : null;
    $valueNumberTo = isset($_POST['condition_value_number_to']) && $_POST['condition_value_number_to'] !== '' ? (float)$_POST['condition_value_number_to'] : null;
    $valueText = isset($_POST['condition_value_text']) && trim((string)$_POST['condition_value_text']) !== '' ? trim((string)$_POST['condition_value_text']) : null;
    $valueJson = isset($_POST['condition_value_json']) && trim((string)$_POST['condition_value_json']) !== '' ? trim((string)$_POST['condition_value_json']) : null;
    $valueList = isset($_POST['condition_value_list']) && trim((string)$_POST['condition_value_list']) !== '' ? trim((string)$_POST['condition_value_list']) : null;
    $dowValues = isset($_POST['condition_dow_values']) ? (array)$_POST['condition_dow_values'] : array();
    $channelValues = isset($_POST['condition_channel_values']) ? (array)$_POST['condition_channel_values'] : array();
    $pickupLookbackDays = isset($_POST['condition_pickup_lookback_days']) && (int)$_POST['condition_pickup_lookback_days'] > 0
        ? (int)$_POST['condition_pickup_lookback_days']
        : 7;
    $sortOrder = isset($_POST['condition_sort_order']) ? (int)$_POST['condition_sort_order'] : 0;

    try {
        if ($modifierId <= 0 || $conditionType === '') {
            throw new Exception('Selecciona modificador y tipo de condicion.');
        }
        if ($valueText === null && $valueList !== null) {
            $valueText = $valueList;
        }
        if ($conditionType === 'dow_in' && $dowValues) {
            $clean = array();
            foreach ($dowValues as $value) {
                $value = strtoupper(trim((string)$value));
                if (in_array($value, array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'), true)) {
                    $clean[] = $value;
                }
            }
            if ($clean) {
                $valueText = implode(',', $clean);
                $operatorKey = 'in';
            }
        }
        if ($conditionType === 'channel_in' && $channelValues) {
            $clean = array();
            foreach ($channelValues as $value) {
                $value = strtolower(trim((string)$value));
                if ($value !== '') {
                    $clean[] = $value;
                }
            }
            if ($clean) {
                $valueText = implode(',', array_values(array_unique($clean)));
                $operatorKey = 'in';
            }
        }
        if ($conditionType === 'pickup_reservations' && $valueJson === null) {
            $valueJson = json_encode(array('lookback_days' => $pickupLookbackDays), JSON_UNESCAPED_UNICODE);
        }
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'INSERT INTO rateplan_modifier_condition (
                id_rateplan_modifier,
                condition_type,
                operator_key,
                value_number,
                value_number_to,
                value_text,
                value_json,
                sort_order,
                is_active,
                created_at,
                created_by,
                updated_at
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW()
             )'
        );
        $stmt->execute(array(
            $modifierId,
            $conditionType,
            $operatorKey,
            $valueNumber,
            $valueNumberTo,
            $valueText,
            $valueJson,
            $sortOrder,
            $actorUserId
        ));
        $message = 'Condicion agregada al modificador.';
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:' . $rateplanCode;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} elseif ($action === 'save_modifier_scope') {
    $rateplanCode = isset($_POST['rateplan_code']) ? strtoupper(trim((string)$_POST['rateplan_code'])) : '';
    $modifierId = isset($_POST['scope_modifier_id']) ? (int)$_POST['scope_modifier_id'] : 0;
    $scopeCategoryId = isset($_POST['scope_category_id']) && $_POST['scope_category_id'] !== '' ? (int)$_POST['scope_category_id'] : null;
    $scopeRoomId = isset($_POST['scope_room_id']) && $_POST['scope_room_id'] !== '' ? (int)$_POST['scope_room_id'] : null;

    try {
        if ($modifierId <= 0) {
            throw new Exception('Selecciona modificador para agregar scope.');
        }
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'INSERT INTO rateplan_modifier_scope (
                id_rateplan_modifier,
                id_category,
                id_room,
                is_active,
                created_at,
                created_by,
                updated_at
             ) VALUES (
                ?, ?, ?, 1, NOW(), ?, NOW()
             )'
        );
        $stmt->execute(array(
            $modifierId,
            $scopeCategoryId,
            $scopeRoomId,
            $actorUserId
        ));
        $message = 'Scope agregado al modificador.';
        $_POST[$moduleKey . '_current_subtab'] = 'dynamic:rateplan:' . $rateplanCode;
    } catch (Exception $e) {
        $error = $e->getMessage();
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
                    p.name AS property_name
             FROM rateplan rp
             JOIN property p ON p.id_property = rp.id_property
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
                    p.name AS property_name
             FROM rateplan rp
             JOIN property p ON p.id_property = rp.id_property
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
<style>
  .rp-section {
    margin-top: 16px;
    padding: 14px;
    border: 1px solid rgba(80, 160, 220, 0.3);
    border-radius: 10px;
    background: rgba(8, 20, 42, 0.35);
  }
  .rp-section h5 {
    margin: 0 0 6px 0;
    font-size: 14px;
  }
  .rp-section .rp-help {
    margin: 0 0 10px 0;
    font-size: 12px;
    opacity: 0.9;
  }
  .rp-inline-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .rp-inline-checks label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin: 0;
  }
</style>
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
        'is_active' => 1
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
    $modifiers = array();
    $schedulesByModifier = array();
    $conditionsByModifier = array();
    $scopesByModifier = array();
    $overrides = array();
    if ($planId > 0) {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM rateplan_modifier
                 WHERE id_rateplan = ?
                   AND deleted_at IS NULL
                 ORDER BY priority DESC, id_rateplan_modifier ASC'
            );
            $stmt->execute(array($planId));
            $modifiers = $stmt->fetchAll();

            if ($modifiers) {
                $modifierIds = array();
                foreach ($modifiers as $modifierRow) {
                    $modifierIds[] = (int)$modifierRow['id_rateplan_modifier'];
                }

                $inClause = implode(',', array_fill(0, count($modifierIds), '?'));
                $stmt = $pdo->prepare(
                    'SELECT *
                     FROM rateplan_modifier_schedule
                     WHERE id_rateplan_modifier IN (' . $inClause . ')
                       AND deleted_at IS NULL
                     ORDER BY id_rateplan_modifier_schedule ASC'
                );
                $stmt->execute($modifierIds);
                foreach ($stmt->fetchAll() as $scheduleRow) {
                    $mid = (int)$scheduleRow['id_rateplan_modifier'];
                    if (!isset($schedulesByModifier[$mid])) {
                        $schedulesByModifier[$mid] = array();
                    }
                    $schedulesByModifier[$mid][] = $scheduleRow;
                }

                $stmt = $pdo->prepare(
                    'SELECT *
                     FROM rateplan_modifier_condition
                     WHERE id_rateplan_modifier IN (' . $inClause . ')
                       AND deleted_at IS NULL
                     ORDER BY sort_order ASC, id_rateplan_modifier_condition ASC'
                );
                $stmt->execute($modifierIds);
                foreach ($stmt->fetchAll() as $conditionRow) {
                    $mid = (int)$conditionRow['id_rateplan_modifier'];
                    if (!isset($conditionsByModifier[$mid])) {
                        $conditionsByModifier[$mid] = array();
                    }
                    $conditionsByModifier[$mid][] = $conditionRow;
                }

                $stmt = $pdo->prepare(
                    'SELECT *
                     FROM rateplan_modifier_scope
                     WHERE id_rateplan_modifier IN (' . $inClause . ')
                       AND deleted_at IS NULL
                     ORDER BY id_rateplan_modifier_scope ASC'
                );
                $stmt->execute($modifierIds);
                foreach ($stmt->fetchAll() as $scopeRow) {
                    $mid = (int)$scopeRow['id_rateplan_modifier'];
                    if (!isset($scopesByModifier[$mid])) {
                        $scopesByModifier[$mid] = array();
                    }
                    $scopesByModifier[$mid][] = $scopeRow;
                }
            }

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
            $pricingService = new RateplanPricingService(isset($pdo) ? $pdo : pms_get_connection());
            $calendarRows = $pricingService->getCalendarPricesByCodes(
                $planPropertyCode,
                $code,
                $calendarCategoryCode,
                $calendarRoomCode,
                $calendarStart,
                $calendarDays
            );
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

    $categoryCodeById = array();
    foreach ($planCategories as $cat) {
        if (!isset($cat['id_category'])) {
            continue;
        }
        $categoryCodeById[(int)$cat['id_category']] = isset($cat['code']) ? (string)$cat['code'] : ('#' . (int)$cat['id_category']);
    }
    $roomCodeById = array();
    foreach ($rooms as $room) {
        if (!isset($room['id_room'])) {
            continue;
        }
        $roomCodeById[(int)$room['id_room']] = isset($room['code']) ? (string)$room['code'] : ('#' . (int)$room['id_room']);
    }
    $selectedModifierId = isset($_POST['modifier_focus_id']) ? (int)$_POST['modifier_focus_id'] : 0;
    if ($selectedModifierId <= 0 && $modifiers) {
        $selectedModifierId = (int)$modifiers[0]['id_rateplan_modifier'];
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
      <h4>Modifiers</h4>
      <?php if ($planId <= 0): ?>
        <p class="muted">Guarda el plan para habilitar modificadores.</p>
      <?php else: ?>
        <?php if ($modifiers): ?>
          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Prioridad</th>
                  <th>Nombre</th>
                  <th>Accion</th>
                  <th>Schedule</th>
                  <th>Conditions</th>
                  <th>Scope</th>
                  <th>Activo</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($modifiers as $modifier):
                  $mid = (int)$modifier['id_rateplan_modifier'];
                  $modifierSchedules = isset($schedulesByModifier[$mid]) ? $schedulesByModifier[$mid] : array();
                  $modifierConditions = isset($conditionsByModifier[$mid]) ? $conditionsByModifier[$mid] : array();
                  $modifierScopes = isset($scopesByModifier[$mid]) ? $scopesByModifier[$mid] : array();
                  $scheduleSummary = 'Siempre';
                  if ($modifierSchedules) {
                      $summaryParts = array();
                      foreach ($modifierSchedules as $schedule) {
                          if ((string)$schedule['schedule_type'] === 'range') {
                              $summaryParts[] = (string)$schedule['start_date'] . ' -> ' . (string)$schedule['end_date'];
                          } else {
                              $summaryParts[] = (string)$schedule['schedule_rrule'];
                          }
                      }
                      $scheduleSummary = implode(' | ', $summaryParts);
                  } elseif (!empty($modifier['is_always_on'])) {
                      $scheduleSummary = 'Always On';
                  }
                  $conditionSummary = $modifierConditions ? ('AND x ' . count($modifierConditions)) : '-';
                  $scopeSummary = 'Global';
                  if ($modifierScopes) {
                      $scopeParts = array();
                      foreach ($modifierScopes as $scopeRow) {
                          $scopeCat = isset($scopeRow['id_category']) && (int)$scopeRow['id_category'] > 0 ? (int)$scopeRow['id_category'] : 0;
                          $scopeRoom = isset($scopeRow['id_room']) && (int)$scopeRow['id_room'] > 0 ? (int)$scopeRow['id_room'] : 0;
                          if ($scopeRoom > 0) {
                              $scopeParts[] = 'HAB ' . (isset($roomCodeById[$scopeRoom]) ? $roomCodeById[$scopeRoom] : ('#' . $scopeRoom));
                          } elseif ($scopeCat > 0) {
                              $scopeParts[] = 'CAT ' . (isset($categoryCodeById[$scopeCat]) ? $categoryCodeById[$scopeCat] : ('#' . $scopeCat));
                          } else {
                              $scopeParts[] = 'Global';
                          }
                      }
                      $scopeSummary = implode(', ', $scopeParts);
                  }
                ?>
                  <tr>
                    <td><?php echo (int)$modifier['priority']; ?></td>
                    <td><?php echo htmlspecialchars((string)$modifier['modifier_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)$modifier['price_action'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($scheduleSummary, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($conditionSummary, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($scopeSummary, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int)$modifier['is_active'] === 1 ? 'Si' : 'No'; ?></td>
                    <td>
                      <form method="post">
                        <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                        <input type="hidden" name="rateplans_action" value="toggle_modifier">
                        <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="rateplan_id" value="<?php echo $planId; ?>">
                        <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="modifier_id" value="<?php echo $mid; ?>">
                        <input type="hidden" name="modifier_is_active" value="<?php echo (int)$modifier['is_active'] === 1 ? 0 : 1; ?>">
                        <input type="hidden" name="modifier_focus_id" value="<?php echo $mid; ?>">
                        <button type="submit" class="button-secondary"><?php echo (int)$modifier['is_active'] === 1 ? 'Desactivar' : 'Activar'; ?></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="muted">Sin modificadores configurados.</p>
        <?php endif; ?>

        <div class="rp-section">
          <h5>1) Crear modificador de precio</h5>
          <p class="rp-help">Define como cambia el precio (porcentaje, monto fijo o precio directo) y su prioridad.</p>
          <form method="post" class="form-grid grid-3">
            <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
            <input type="hidden" name="rateplans_action" value="save_modifier">
            <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="rateplan_property_code" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="rateplan_id" value="<?php echo $planId; ?>">
            <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="modifier_id" value="0">
            <label>
              Nombre *
              <input type="text" name="modifier_name" required>
            </label>
            <label>
              Prioridad
              <input type="number" name="modifier_priority" value="0">
            </label>
            <label>
              Modo de aplicacion
              <select name="modifier_apply_mode">
                <option value="stack">Acumulativo (stack)</option>
                <option value="best_for_guest">Mejor para huesped</option>
                <option value="best_for_property">Mejor para propiedad</option>
                <option value="override">Sobrescribir precio</option>
              </select>
            </label>
            <label>
              Accion de precio
              <select name="modifier_price_action">
                <option value="add_pct">Ajustar por %</option>
                <option value="add_cents">Ajustar por monto</option>
                <option value="set_price">Fijar precio</option>
              </select>
            </label>
            <label>
              Porcentaje (add_pct)
              <input type="number" step="0.001" name="modifier_add_pct">
            </label>
            <label>
              Monto en centavos (add_cents)
              <input type="number" name="modifier_add_cents">
            </label>
            <label>
              Precio fijo centavos (set_price)
              <input type="number" name="modifier_set_price_cents">
            </label>
            <label>
              Piso en centavos
              <input type="number" name="modifier_clamp_min_cents">
            </label>
            <label>
              Techo en centavos
              <input type="number" name="modifier_clamp_max_cents">
            </label>
            <label class="checkbox">
              <input type="checkbox" name="modifier_is_always_on" value="1">
              Always on
            </label>
            <label class="checkbox">
              <input type="checkbox" name="modifier_respect_category_min" value="1" checked>
              Respetar minimo de categoria
            </label>
            <label class="checkbox">
              <input type="checkbox" name="modifier_is_active" value="1" checked>
              Activo
            </label>
            <label class="full">
              Descripcion
              <input type="text" name="modifier_description">
            </label>
            <div class="form-actions full">
              <button type="submit">Agregar modificador</button>
            </div>
          </form>
        </div>

        <?php if ($modifiers): ?>
        <div class="rp-section">
          <h5>2) Fechas y recurrencia (Schedule)</h5>
          <p class="rp-help">Configura cuando aplica el modificador: por rango o repeticion semanal/mensual/anual.</p>
          <form method="post" class="form-grid grid-3">
            <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
            <input type="hidden" name="rateplans_action" value="save_modifier_schedule">
            <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
            <label>
              Modificador
              <select name="schedule_modifier_id" required>
                <?php foreach ($modifiers as $modifier): ?>
                  <option value="<?php echo (int)$modifier['id_rateplan_modifier']; ?>" <?php echo $selectedModifierId === (int)$modifier['id_rateplan_modifier'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)$modifier['modifier_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Tipo de schedule
              <select name="schedule_type">
                <option value="range">Rango de fechas</option>
                <option value="rrule">Recurrente</option>
              </select>
            </label>
            <label>
              Frecuencia recurrente
              <select name="schedule_rrule_freq">
                <option value="WEEKLY">Semanal</option>
                <option value="MONTHLY">Mensual</option>
                <option value="YEARLY">Anual</option>
              </select>
            </label>
            <label>
              Fecha inicio (range)
              <input type="date" name="schedule_start_date">
            </label>
            <label>
              Fecha fin (range)
              <input type="date" name="schedule_end_date">
            </label>
            <label>
              Intervalo
              <input type="number" name="schedule_rrule_interval" min="1" value="1">
            </label>
            <label>
              Hasta fecha (recurrente)
              <input type="date" name="schedule_rrule_until">
            </label>
            <label>
              Dia del mes (1-31)
              <input type="number" name="schedule_rrule_bymonthday" min="1" max="31">
            </label>
            <label>
              Mes (anual)
              <select name="schedule_rrule_bymonth">
                <option value="">--</option>
                <?php for ($m=1; $m<=12; $m++): ?>
                  <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                <?php endfor; ?>
              </select>
            </label>
            <label class="full">
              Dias de semana (semanal)
              <span class="rp-inline-checks">
                <label><input type="checkbox" name="schedule_rrule_byday[]" value="MO">LU</label>
                <label><input type="checkbox" name="schedule_rrule_byday[]" value="TU">MA</label>
                <label><input type="checkbox" name="schedule_rrule_byday[]" value="WE">MI</label>
                <label><input type="checkbox" name="schedule_rrule_byday[]" value="TH">JU</label>
                <label><input type="checkbox" name="schedule_rrule_byday[]" value="FR">VI</label>
                <label><input type="checkbox" name="schedule_rrule_byday[]" value="SA">SA</label>
                <label><input type="checkbox" name="schedule_rrule_byday[]" value="SU">DO</label>
              </span>
            </label>
            <label class="full">
              Excluir fechas (una por linea)
              <textarea name="schedule_exdates_text" rows="2" placeholder="2026-12-24&#10;2026-12-31"></textarea>
            </label>
            <label class="full">
              RRULE avanzado (opcional)
              <input type="text" name="schedule_rrule" placeholder="FREQ=WEEKLY;BYDAY=FR,SA;INTERVAL=1">
            </label>
            <label class="full">
              Exdates JSON avanzado (opcional)
              <input type="text" name="schedule_exdates_json" placeholder="[&quot;2026-12-24&quot;,&quot;2026-12-31&quot;]">
            </label>
            <div class="form-actions full">
              <button type="submit">Agregar schedule</button>
            </div>
          </form>
        </div>

        <div class="rp-section">
          <h5>3) Condiciones de aplicacion</h5>
          <p class="rp-help">Agrega condiciones AND para que el modificador solo se aplique cuando se cumplan.</p>
          <form method="post" class="form-grid grid-3">
            <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
            <input type="hidden" name="rateplans_action" value="save_modifier_condition">
            <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
            <label>
              Modificador
              <select name="condition_modifier_id" required>
                <?php foreach ($modifiers as $modifier): ?>
                  <option value="<?php echo (int)$modifier['id_rateplan_modifier']; ?>" <?php echo $selectedModifierId === (int)$modifier['id_rateplan_modifier'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)$modifier['modifier_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Tipo de condicion
              <select name="condition_type" required>
                <option value="occupancy_pct_property">Ocupacion propiedad (%)</option>
                <option value="occupancy_pct_category">Ocupacion categoria (%)</option>
                <option value="days_to_arrival">Dias a llegada</option>
                <option value="pickup_reservations">Pickup reservas</option>
                <option value="min_los">Min LOS</option>
                <option value="max_los">Max LOS</option>
                <option value="dow_in">Dia de semana</option>
                <option value="channel_in">Canal</option>
              </select>
            </label>
            <label>
              Operador
              <select name="condition_operator">
                <option value="lt">&lt;</option>
                <option value="lte">&lt;=</option>
                <option value="gt">&gt;</option>
                <option value="gte">&gt;=</option>
                <option value="eq">=</option>
                <option value="neq">!=</option>
                <option value="between">between</option>
                <option value="in">in</option>
              </select>
            </label>
            <label>
              Valor numero
              <input type="number" step="0.01" name="condition_value_number">
            </label>
            <label>
              Valor numero hasta
              <input type="number" step="0.01" name="condition_value_number_to">
            </label>
            <label>
              Valor texto
              <input type="text" name="condition_value_text" placeholder="booking,expedia">
            </label>
            <label>
              Lista valores (CSV)
              <input type="text" name="condition_value_list" placeholder="MO,TU,WE o booking,airbnb">
            </label>
            <label>
              Pickup lookback (dias)
              <input type="number" name="condition_pickup_lookback_days" min="1" value="7">
            </label>
            <label>
              sort_order
              <input type="number" name="condition_sort_order" value="0">
            </label>
            <label class="full">
              Dias de semana (dow_in)
              <span class="rp-inline-checks">
                <label><input type="checkbox" name="condition_dow_values[]" value="MO">LU</label>
                <label><input type="checkbox" name="condition_dow_values[]" value="TU">MA</label>
                <label><input type="checkbox" name="condition_dow_values[]" value="WE">MI</label>
                <label><input type="checkbox" name="condition_dow_values[]" value="TH">JU</label>
                <label><input type="checkbox" name="condition_dow_values[]" value="FR">VI</label>
                <label><input type="checkbox" name="condition_dow_values[]" value="SA">SA</label>
                <label><input type="checkbox" name="condition_dow_values[]" value="SU">DO</label>
              </span>
            </label>
            <label class="full">
              Canales (channel_in)
              <span class="rp-inline-checks">
                <label><input type="checkbox" name="condition_channel_values[]" value="booking">Booking</label>
                <label><input type="checkbox" name="condition_channel_values[]" value="expedia">Expedia</label>
                <label><input type="checkbox" name="condition_channel_values[]" value="airbnb">AirB&B</label>
                <label><input type="checkbox" name="condition_channel_values[]" value="mapas">Mapas</label>
                <label><input type="checkbox" name="condition_channel_values[]" value="direct">Directo</label>
              </span>
            </label>
            <label class="full">
              JSON avanzado (opcional)
              <input type="text" name="condition_value_json" placeholder="{&quot;lookback_days&quot;:7}">
            </label>
            <div class="form-actions full">
              <button type="submit">Agregar condicion</button>
            </div>
          </form>
        </div>

        <div class="rp-section">
          <h5>4) Alcance (Scope)</h5>
          <p class="rp-help">Limita el modificador a una categoria o a una habitacion especifica. Si dejas ambos vacios aplica global.</p>
          <form method="post" class="form-grid grid-3">
            <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
            <input type="hidden" name="rateplans_action" value="save_modifier_scope">
            <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($planPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="rateplan_code" value="<?php echo htmlspecialchars((string)$plan['rateplan_code'], ENT_QUOTES, 'UTF-8'); ?>">
            <label>
              Modificador
              <select name="scope_modifier_id" required>
                <?php foreach ($modifiers as $modifier): ?>
                  <option value="<?php echo (int)$modifier['id_rateplan_modifier']; ?>" <?php echo $selectedModifierId === (int)$modifier['id_rateplan_modifier'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)$modifier['modifier_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Categoria (opcional)
              <select name="scope_category_id">
                <option value="">Global</option>
                <?php foreach ($planCategories as $cat): ?>
                  <option value="<?php echo (int)$cat['id_category']; ?>">
                    <?php echo htmlspecialchars((string)$cat['code'] . ' - ' . (string)$cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Habitacion (opcional)
              <select name="scope_room_id">
                <option value="">Todas</option>
                <?php foreach ($rooms as $room): ?>
                  <option value="<?php echo (int)$room['id_room']; ?>">
                    <?php echo htmlspecialchars((string)$room['code'] . ' - ' . (string)$room['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="form-actions full">
              <button type="submit">Agregar scope</button>
            </div>
          </form>
        </div>
        <?php else: ?>
          <p class="muted">Crea al menos un modificador para configurar schedule, conditions y scope.</p>
        <?php endif; ?>
      <?php endif; ?>
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
