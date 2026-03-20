<?php
$user = pms_current_user();
$companyId = isset($user['company_id']) ? (int)$user['company_id'] : 0;
$companyCode = isset($user['company_code']) ? (string)$user['company_code'] : '';
$actorUserId = isset($user['id_user']) ? (int)$user['id_user'] : null;
$allowedPropertyCodes = function_exists('pms_allowed_property_codes') ? pms_allowed_property_codes() : array();
$allowedPropertyCodeSet = array();
foreach ($allowedPropertyCodes as $allowedPropertyCode) {
    $allowedPropertyCode = strtoupper(trim((string)$allowedPropertyCode));
    if ($allowedPropertyCode !== '') {
        $allowedPropertyCodeSet[$allowedPropertyCode] = true;
    }
}

$pdo = pms_get_connection();
$dashboardError = null;
$activitiesError = null;
$availabilityError = null;
$dashboardActionMessage = null;
$dashboardActionError = null;
if (!function_exists('dashboard_format_money')) {
    function dashboard_format_money($cents, $currency = 'MXN') {
        $value = (float)$cents / 100;
        return '$' . number_format($value, 2) . ' ' . $currency;
    }
}
if (!function_exists('dashboard_to_cents')) {
    function dashboard_to_cents($raw)
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
if (!function_exists('dashboard_format_reservation_title')) {
    function dashboard_format_reservation_title($guestName, $checkInDate)
    {
        $guestLabel = trim((string)$guestName);
        if ($guestLabel === '') {
            $guestLabel = 'Reserva';
        }
        $dateLabel = '';
        $rawDate = substr(trim((string)$checkInDate), 0, 10);
        if ($rawDate !== '') {
            $dateObj = DateTime::createFromFormat('Y-m-d', $rawDate);
            if ($dateObj instanceof DateTime) {
                $dateLabel = $dateObj->format('d M');
            }
        }
        if ($dateLabel !== '') {
            return $guestLabel . ' - ' . $dateLabel;
        }
        return $guestLabel;
    }
}

$rawAvailabilityProperty = '';
if (isset($_POST['availability_property'])) {
    $rawAvailabilityProperty = (string)$_POST['availability_property'];
} elseif (isset($_GET['availability_property'])) {
    $rawAvailabilityProperty = (string)$_GET['availability_property'];
}
$availabilityPropertyCode = '';
$availabilityPropertyName = '';
$availabilityPropertyId = null;
$availabilityAllKey = '__all__';
$isAllProperties = false;

$dashboardDate = date('Y-m-d');
$rawDashboardDate = '';
if (isset($_POST['dashboard_date'])) {
    $rawDashboardDate = (string)$_POST['dashboard_date'];
} elseif (isset($_GET['dashboard_date'])) {
    $rawDashboardDate = (string)$_GET['dashboard_date'];
}
if ($rawDashboardDate !== '') {
    $dateCandidate = substr($rawDashboardDate, 0, 10);
    $dateObj = DateTime::createFromFormat('Y-m-d', $dateCandidate);
    if ($dateObj) {
        $dashboardDate = $dateObj->format('Y-m-d');
    }
}
$dashboardDateObj = DateTime::createFromFormat('Y-m-d', $dashboardDate);
if (!$dashboardDateObj) {
    $dashboardDateObj = new DateTime('today');
    $dashboardDate = $dashboardDateObj->format('Y-m-d');
}
$dashboardDateLabel = $dashboardDateObj->format('d M Y');
$dashboardDateInput = $dashboardDateObj->format('Y-m-d\T00:00');
$dashboardPeriod = 14;
$rawDashboardPeriod = '';
if (isset($_POST['dashboard_period'])) {
    $rawDashboardPeriod = (string)$_POST['dashboard_period'];
} elseif (isset($_GET['dashboard_period'])) {
    $rawDashboardPeriod = (string)$_GET['dashboard_period'];
}
if ($rawDashboardPeriod !== '') {
    $periodCandidate = (int)$rawDashboardPeriod;
    if ($periodCandidate >= 7 && $periodCandidate <= 60) {
        $dashboardPeriod = $periodCandidate;
    }
}
$dashboardActiveTabOptions = array(
    'dashboard-activities-checkins',
    'dashboard-activities-inhouse',
    'dashboard-activities-checkouts',
    'dashboard-activities-balances',
    'dashboard-activities-guest',
    'dashboard-activities-obligations'
);
$dashboardActiveTab = 'dashboard-activities-checkins';
$rawDashboardActiveTab = '';
if (isset($_POST['dashboard_active_tab'])) {
    $rawDashboardActiveTab = (string)$_POST['dashboard_active_tab'];
} elseif (isset($_GET['dashboard_active_tab'])) {
    $rawDashboardActiveTab = (string)$_GET['dashboard_active_tab'];
}
if (in_array($rawDashboardActiveTab, $dashboardActiveTabOptions, true)) {
    $dashboardActiveTab = $rawDashboardActiveTab;
}

$dashboardObligationTabOptions = array(
    'dashboard-obligations-past',
    'dashboard-obligations-today',
    'dashboard-obligations-future'
);
$dashboardObligationTypeLabels = array(
    'property_payment' => 'Pago a propiedad',
    'ota_payment' => 'Pago a OTA',
    'tax_payment' => 'Pago de impuesto'
);
$dashboardObligationType = 'property_payment';
$rawDashboardObligationType = '';
if (isset($_POST['dashboard_obligation_type'])) {
    $rawDashboardObligationType = (string)$_POST['dashboard_obligation_type'];
} elseif (isset($_GET['dashboard_obligation_type'])) {
    $rawDashboardObligationType = (string)$_GET['dashboard_obligation_type'];
}
if (isset($dashboardObligationTypeLabels[$rawDashboardObligationType])) {
    $dashboardObligationType = $rawDashboardObligationType;
}
$dashboardObligationTab = 'dashboard-obligations-today';
$rawDashboardObligationTab = '';
if (isset($_POST['dashboard_obligation_tab'])) {
    $rawDashboardObligationTab = (string)$_POST['dashboard_obligation_tab'];
} elseif (isset($_GET['dashboard_obligation_tab'])) {
    $rawDashboardObligationTab = (string)$_GET['dashboard_obligation_tab'];
}
if (in_array($rawDashboardObligationTab, $dashboardObligationTabOptions, true)) {
    $dashboardObligationTab = $rawDashboardObligationTab;
}
$dashboardPeriodStart = clone $dashboardDateObj;
$dashboardPeriodEnd = clone $dashboardDateObj;
$dashboardPeriodEnd->modify('+' . max(0, $dashboardPeriod - 1) . ' days');
$dashboardPeriodStartLabel = $dashboardPeriodStart->format('d M');
$dashboardPeriodEndLabel = $dashboardPeriodEnd->format('d M');

$activityBuckets = array(
    'checkins' => array(),
    'in_house' => array(),
    'checkouts' => array(),
    'balances' => array()
);
$activityPropertyName = '';
$availabilityByProperty = array();
$dashboardObligationBuckets = array(
    'property_payment' => array(
        'past' => array(),
        'today' => array(),
        'future' => array()
    ),
    'ota_payment' => array(
        'past' => array(),
        'today' => array(),
        'future' => array()
    ),
    'tax_payment' => array(
        'past' => array(),
        'today' => array(),
        'future' => array()
    )
);
$dashboardObligationTypeCounts = array(
    'property_payment' => 0,
    'ota_payment' => 0,
    'tax_payment' => 0
);
$dashboardObligationError = null;
$obligationPaymentMethods = array();
$properties = array();
$allowedPropertyIds = array();
$upcomingArrivals = array();
$occupancyPeriodData = array();
$occupancyPeriodMaxPct = 0;
$occupancyPeriodAvgPct = 0;

$metrics = array(
    'rooms' => 0,
    'guests' => 0,
    'reservations_upcoming' => 0,
    'revenue_30' => 0.0,
    'occupied_today' => 0,
    'occupancy_pct' => 0.0,
    'arrivals_today' => 0,
    'departures_today' => 0,
    'in_house' => 0,
    'balances_count' => 0,
    'balance_due_total' => 0.0,
    'availability_today' => 0,
);

if ($companyId <= 0) {
    $dashboardError = 'No fue posible identificar la empresa del usuario para generar estadisticas.';
    $activitiesError = 'No fue posible identificar la empresa para generar actividades.';
    $availabilityError = 'No fue posible identificar la empresa para generar disponibilidad.';
} else {
    $propertiesByCode = array();
    try {
        $properties = pms_fetch_properties($companyId);
        foreach ($properties as $property) {
            $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
            if ($code !== '') {
                $propertiesByCode[$code] = $property;
            }
            $propertyId = isset($property['id_property']) ? (int)$property['id_property'] : 0;
            if ($propertyId > 0) {
                $allowedPropertyIds[] = $propertyId;
            }
        }
        $allowedPropertyIds = array_values(array_unique($allowedPropertyIds));
    } catch (Exception $e) {
        $properties = array();
        $activitiesError = 'No fue posible cargar las propiedades: ' . $e->getMessage();
        $availabilityError = 'No fue posible cargar las propiedades: ' . $e->getMessage();
    }

    if ($rawAvailabilityProperty !== '') {
        $candidateRaw = strtolower(trim($rawAvailabilityProperty));
        if ($candidateRaw === $availabilityAllKey) {
            $availabilityPropertyCode = $availabilityAllKey;
        } else {
            $candidate = strtoupper(trim($rawAvailabilityProperty));
            if ($candidate !== '' && isset($propertiesByCode[$candidate])) {
                $availabilityPropertyCode = $candidate;
            }
        }
    }
    if ($availabilityPropertyCode === '') {
        $availabilityPropertyCode = $availabilityAllKey;
    }
    if (
        !pms_is_owner_user()
        && $availabilityPropertyCode === $availabilityAllKey
        && empty($allowedPropertyIds)
    ) {
        $dashboardError = 'No tienes propiedades asignadas para ver el dashboard.';
        $activitiesError = 'No tienes propiedades asignadas para ver actividades.';
        $availabilityError = 'No tienes propiedades asignadas para ver disponibilidad.';
    }
    if ($availabilityPropertyCode === $availabilityAllKey) {
        $isAllProperties = true;
        $availabilityPropertyName = 'Todas las propiedades';
    } elseif (isset($propertiesByCode[$availabilityPropertyCode])) {
        $availabilityPropertyName = isset($propertiesByCode[$availabilityPropertyCode]['name'])
            ? (string)$propertiesByCode[$availabilityPropertyCode]['name']
            : '';
        $availabilityPropertyId = isset($propertiesByCode[$availabilityPropertyCode]['id_property'])
            ? (int)$propertiesByCode[$availabilityPropertyCode]['id_property']
            : null;
    }

    $dashboardAction = isset($_POST['dashboard_action']) ? (string)$_POST['dashboard_action'] : '';
    if ($dashboardAction !== '' && in_array($dashboardAction, array('obligation_apply_add', 'obligation_apply_full', 'obligation_pay_full', 'obligation_apply_all'), true)) {
        $dashboardActiveTab = 'dashboard-activities-obligations';
    }
    if ($dashboardAction === 'check_in' || $dashboardAction === 'check_out') {
        $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        if ($companyCode === '') {
            $dashboardActionError = 'No fue posible determinar la empresa para actualizar la reserva.';
        } elseif ($reservationId <= 0) {
            $dashboardActionError = 'Selecciona una reserva valida.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'SELECT r.status, p.code AS property_code
                     FROM reservation r
                     JOIN property p ON p.id_property = r.id_property
                     WHERE r.id_reservation = ? AND p.id_company = ? AND r.deleted_at IS NULL
                     LIMIT 1'
                );
                $stmt->execute(array($reservationId, $companyId));
                $reservationRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentStatus = $reservationRow && isset($reservationRow['status']) ? $reservationRow['status'] : false;
                $reservationPropertyCode = $reservationRow && isset($reservationRow['property_code'])
                    ? strtoupper(trim((string)$reservationRow['property_code']))
                    : '';
                if ($currentStatus === false) {
                    $dashboardActionError = 'Reserva no encontrada para esta empresa.';
                } elseif (!pms_is_owner_user() && $reservationPropertyCode !== '' && !isset($allowedPropertyCodeSet[$reservationPropertyCode])) {
                    $dashboardActionError = 'No tienes acceso a la propiedad de esta reserva.';
                } else {
                    $normalized = strtolower(trim((string)$currentStatus));
                    $normalized = preg_replace('/\s+/', ' ', $normalized);
                    if ($normalized === 'encasa') {
                        $normalized = 'en casa';
                    }

                    $targetStatus = null;
                    if ($dashboardAction === 'check_in') {
                        if ($normalized === 'confirmado' || $normalized === 'confirmed') {
                            $targetStatus = 'en casa';
                        } else {
                            $dashboardActionError = 'Solo las reservas confirmadas pueden hacer check-in.';
                        }
                    } else {
                        if ($normalized === 'en casa' || $normalized === 'checkedin') {
                            $targetStatus = 'salida';
                        } else {
                            $dashboardActionError = 'Solo las reservas en casa pueden hacer check-out.';
                        }
                    }

                    if ($targetStatus) {
                        pms_call_procedure('sp_reservation_update', array(
                            $companyCode,
                            $reservationId,
                            $targetStatus,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            null,
                            $actorUserId
                        ));
                        $dashboardActionMessage = $dashboardAction === 'check_in'
                            ? 'Check-in aplicado correctamente.'
                            : 'Check-out aplicado correctamente.';
                    }
                }
            } catch (Exception $e) {
                $dashboardActionError = 'No fue posible actualizar la reserva: ' . $e->getMessage();
            }
        }
    }

    if (in_array($dashboardAction, array('obligation_apply_add', 'obligation_apply_full', 'obligation_pay_full'), true)) {
        $obligationLineItemId = isset($_POST['obligation_line_item_id']) ? (int)$_POST['obligation_line_item_id'] : 0;
        $obligationFullAmountCents = isset($_POST['obligation_full_amount_cents']) ? (int)$_POST['obligation_full_amount_cents'] : 0;
        $applyAmountRaw = isset($_POST['obligation_apply_amount']) ? (string)$_POST['obligation_apply_amount'] : '';
        $obligationPaymentMethodId = isset($_POST['obligation_payment_method_id']) ? (int)$_POST['obligation_payment_method_id'] : 0;
        $obligationPaymentNotes = isset($_POST['obligation_payment_notes']) ? trim((string)$_POST['obligation_payment_notes']) : '';
        $mode = 'set';
        $amountCents = 0;

        if ($dashboardAction === 'obligation_apply_add') {
            $mode = 'add';
            $amountCents = dashboard_to_cents($applyAmountRaw);
            $dashboardObligationTab = isset($_POST['dashboard_obligation_tab']) ? (string)$_POST['dashboard_obligation_tab'] : $dashboardObligationTab;
        } else {
            $mode = 'set';
            $amountCents = $obligationFullAmountCents;
            $dashboardObligationTab = isset($_POST['dashboard_obligation_tab']) ? (string)$_POST['dashboard_obligation_tab'] : $dashboardObligationTab;
        }

        if ($companyCode === '') {
            $dashboardActionError = 'No fue posible determinar la empresa para actualizar la obligacion.';
        } elseif ($obligationLineItemId <= 0) {
            $dashboardActionError = 'Selecciona una obligacion valida.';
        } elseif ($obligationPaymentMethodId <= 0) {
            $dashboardActionError = 'Selecciona un metodo de pago para la obligacion.';
        } elseif ($mode === 'add' && $amountCents <= 0) {
            $dashboardActionError = 'Indica un monto valido para aplicar.';
        } else {
            if ($amountCents < 0) {
                $amountCents = 0;
            }
            try {
                $resultSets = pms_call_procedure('sp_obligation_paid_upsert', array(
                    $companyCode,
                    $obligationLineItemId,
                    $mode,
                    $amountCents,
                    $obligationPaymentMethodId,
                    $obligationPaymentNotes,
                    $actorUserId
                ));
                $updated = isset($resultSets[0][0]) ? $resultSets[0][0] : null;
                if ($updated) {
                    $updatedPaid = isset($updated['paid_cents']) ? (int)$updated['paid_cents'] : 0;
                    $updatedRemaining = isset($updated['remaining_cents']) ? (int)$updated['remaining_cents'] : 0;
                    $dashboardActionMessage = 'Obligacion actualizada. Pagado: '
                        . dashboard_format_money($updatedPaid, 'MXN')
                        . ' | Pendiente: '
                        . dashboard_format_money($updatedRemaining, 'MXN');
                } else {
                    $dashboardActionMessage = 'Obligacion actualizada.';
                }
            } catch (Exception $e) {
                $dashboardActionError = 'No fue posible actualizar la obligacion: ' . $e->getMessage();
            }
        }
    }
    if ($dashboardAction === 'obligation_apply_all') {
        $obligationPaymentMethodId = isset($_POST['obligation_payment_method_id']) ? (int)$_POST['obligation_payment_method_id'] : 0;
        $obligationPaymentNotes = isset($_POST['obligation_payment_notes']) ? trim((string)$_POST['obligation_payment_notes']) : '';
        $dashboardObligationTab = isset($_POST['dashboard_obligation_tab']) ? (string)$_POST['dashboard_obligation_tab'] : $dashboardObligationTab;
        $rawBulkIds = isset($_POST['obligation_bulk_line_item_id']) ? $_POST['obligation_bulk_line_item_id'] : array();
        $rawBulkAmounts = isset($_POST['obligation_bulk_amount_cents']) ? $_POST['obligation_bulk_amount_cents'] : array();
        $bulkIds = is_array($rawBulkIds) ? $rawBulkIds : array($rawBulkIds);
        $bulkAmounts = is_array($rawBulkAmounts) ? $rawBulkAmounts : array($rawBulkAmounts);
        $bulkMap = array();

        foreach ($bulkIds as $idx => $rawId) {
            $lineItemId = (int)$rawId;
            $amountCents = isset($bulkAmounts[$idx]) ? (int)$bulkAmounts[$idx] : 0;
            if ($lineItemId <= 0 || $amountCents <= 0) {
                continue;
            }
            if (!isset($bulkMap[$lineItemId]) || $amountCents > $bulkMap[$lineItemId]) {
                $bulkMap[$lineItemId] = $amountCents;
            }
        }

        if ($companyCode === '') {
            $dashboardActionError = 'No fue posible determinar la empresa para actualizar obligaciones.';
        } elseif ($obligationPaymentMethodId <= 0) {
            $dashboardActionError = 'Selecciona un metodo de pago para pagar todas las obligaciones visibles.';
        } elseif (empty($bulkMap)) {
            $dashboardActionError = 'No hay obligaciones visibles validas para pagar.';
        } else {
            $appliedCount = 0;
            try {
                foreach ($bulkMap as $lineItemId => $amountCents) {
                    pms_call_procedure('sp_obligation_paid_upsert', array(
                        $companyCode,
                        (int)$lineItemId,
                        'set',
                        (int)$amountCents,
                        $obligationPaymentMethodId,
                        $obligationPaymentNotes,
                        $actorUserId
                    ));
                    $appliedCount++;
                }
                $dashboardActionMessage = 'Pago full aplicado a ' . $appliedCount . ' obligaciones visibles.';
            } catch (Exception $e) {
                $dashboardActionError = 'No fue posible pagar todas las obligaciones visibles: ' . $e->getMessage();
            }
        }
    }
    if (!in_array($dashboardObligationTab, $dashboardObligationTabOptions, true)) {
        $dashboardObligationTab = 'dashboard-obligations-today';
    }

    try {
        $metricsPropertyClause = '';
        $metricsPropertyParams = array();
        if (!$isAllProperties && $availabilityPropertyId !== null) {
            $metricsPropertyClause = ' AND p.id_property = ?';
            $metricsPropertyParams[] = $availabilityPropertyId;
        } elseif (!$isAllProperties && $availabilityPropertyId === null && !empty($allowedPropertyIds)) {
            $metricsPropertyClause = ' AND p.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')';
            $metricsPropertyParams = $allowedPropertyIds;
        } elseif ($isAllProperties && !pms_is_owner_user() && !empty($allowedPropertyIds)) {
            $metricsPropertyClause = ' AND p.id_property IN (' . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ')';
            $metricsPropertyParams = $allowedPropertyIds;
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM room r
             JOIN property p ON p.id_property = r.id_property
             WHERE p.id_company = ?
               AND p.deleted_at IS NULL
               AND p.is_active = 1
               AND r.deleted_at IS NULL" . $metricsPropertyClause
        );
        $stmt->execute(array_merge(array($companyId), $metricsPropertyParams));
        $metrics['rooms'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT g.id_guest)
             FROM guest g
             JOIN reservation r ON r.id_guest = g.id_guest
             JOIN property p ON p.id_property = r.id_property
             WHERE p.id_company = ?
               AND p.deleted_at IS NULL
               AND p.is_active = 1
               AND g.deleted_at IS NULL" . $metricsPropertyClause
        );
        $stmt->execute(array_merge(array($companyId), $metricsPropertyParams));
        $metrics['guests'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reservation r
             JOIN property p ON p.id_property = r.id_property
             WHERE p.id_company = ?
               AND p.deleted_at IS NULL
               AND p.is_active = 1
               AND r.deleted_at IS NULL
               AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
               AND r.check_in_date >= CURDATE()" . $metricsPropertyClause
        );
        $stmt->execute(array_merge(array($companyId), $metricsPropertyParams));
        $metrics['reservations_upcoming'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(r.total_price_cents),0) FROM reservation r
             JOIN property p ON p.id_property = r.id_property
             WHERE p.id_company = ?
               AND p.deleted_at IS NULL
               AND p.is_active = 1
               AND r.deleted_at IS NULL
               AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
               AND r.check_in_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)" . $metricsPropertyClause
        );
        $stmt->execute(array_merge(array($companyId), $metricsPropertyParams));
        $metrics['revenue_30'] = ((int)$stmt->fetchColumn()) / 100;

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT r.id_room) FROM reservation r
             JOIN property p ON p.id_property = r.id_property
             WHERE p.id_company = ?
               AND p.deleted_at IS NULL
               AND p.is_active = 1
               AND r.deleted_at IS NULL
               AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
               AND r.check_in_date <= CURDATE()
               AND r.check_out_date > CURDATE()" . $metricsPropertyClause
        );
        $stmt->execute(array_merge(array($companyId), $metricsPropertyParams));
        $metrics['occupied_today'] = (int)$stmt->fetchColumn();

        if ($metrics['rooms'] > 0) {
            $metrics['occupancy_pct'] = round(($metrics['occupied_today'] / $metrics['rooms']) * 100, 1);
        }

        $upcomingSql = "SELECT
                r.id_reservation,
                r.code AS reservation_code,
                r.check_in_date,
                r.check_out_date,
                r.adults,
                r.children,
                g.names,
                g.last_name,
                p.name AS property_name,
                GROUP_CONCAT(DISTINCT sic.item_name ORDER BY sic.item_name SEPARATOR ', ') AS interest_list
             FROM reservation r
             JOIN property p ON p.id_property = r.id_property
             LEFT JOIN guest g ON g.id_guest = r.id_guest
             LEFT JOIN reservation_interest ri ON ri.id_reservation = r.id_reservation
               AND ri.deleted_at IS NULL
               AND ri.is_active = 1
             LEFT JOIN line_item_catalog sic ON sic.id_line_item_catalog = ri.id_sale_item_catalog AND sic.catalog_type = 'sale_item'
             LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
               AND cat.id_company = p.id_company
               AND cat.deleted_at IS NULL
             WHERE p.id_company = ?
               AND p.deleted_at IS NULL
               AND p.is_active = 1
               AND r.deleted_at IS NULL
               AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
               AND r.check_in_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $upcomingParams = array($companyId);
        if (!$isAllProperties && $availabilityPropertyId !== null) {
            $upcomingSql .= " AND p.id_property = ?";
            $upcomingParams[] = $availabilityPropertyId;
        } elseif ($isAllProperties && !pms_is_owner_user() && !empty($allowedPropertyIds)) {
            $upcomingSql .= " AND p.id_property IN (" . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ")";
            $upcomingParams = array_merge($upcomingParams, $allowedPropertyIds);
        }
        $upcomingSql .= "
             GROUP BY r.id_reservation, r.code, r.check_in_date, r.check_out_date, r.adults, r.children, g.names, g.last_name, p.name
             ORDER BY r.check_in_date ASC, r.id_reservation ASC
             LIMIT 6";
        $stmt = $pdo->prepare($upcomingSql);
        $stmt->execute($upcomingParams);
        $upcomingArrivals = $stmt->fetchAll();

        $occupancyPeriodStart = $dashboardPeriodStart->format('Y-m-d');
        $occupancyPeriodEnd = $dashboardPeriodEnd->format('Y-m-d');
        $occupancyPeriodEndExclusiveObj = clone $dashboardPeriodEnd;
        $occupancyPeriodEndExclusiveObj->modify('+1 day');
        $occupancyPeriodEndExclusive = $occupancyPeriodEndExclusiveObj->format('Y-m-d');

        $occupancySql = "SELECT r.id_room, r.check_in_date, r.check_out_date
            FROM reservation r
            JOIN property p ON p.id_property = r.id_property
            WHERE p.id_company = ?
              AND p.deleted_at IS NULL
              AND p.is_active = 1
              AND r.deleted_at IS NULL
              AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
              AND r.check_in_date < ?
              AND r.check_out_date > ?";
        $occupancyParams = array($companyId, $occupancyPeriodEndExclusive, $occupancyPeriodStart);
        if (!$isAllProperties && $availabilityPropertyId !== null) {
            $occupancySql .= " AND p.id_property = ?";
            $occupancyParams[] = $availabilityPropertyId;
        } elseif ($isAllProperties && !pms_is_owner_user() && !empty($allowedPropertyIds)) {
            $occupancySql .= " AND p.id_property IN (" . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ")";
            $occupancyParams = array_merge($occupancyParams, $allowedPropertyIds);
        }
        $stmt = $pdo->prepare($occupancySql);
        $stmt->execute($occupancyParams);
        $occupancyRows = $stmt->fetchAll();

        $occupiedRoomsByDate = array();
        foreach ($occupancyRows as $row) {
            $roomId = isset($row['id_room']) ? (int)$row['id_room'] : 0;
            $checkIn = isset($row['check_in_date']) ? (string)$row['check_in_date'] : '';
            $checkOut = isset($row['check_out_date']) ? (string)$row['check_out_date'] : '';
            if ($roomId <= 0 || $checkIn === '' || $checkOut === '') {
                continue;
            }
            $rangeStart = $checkIn > $occupancyPeriodStart ? $checkIn : $occupancyPeriodStart;
            $rangeEnd = $checkOut < $occupancyPeriodEndExclusive ? $checkOut : $occupancyPeriodEndExclusive;
            $rangeStartObj = DateTime::createFromFormat('Y-m-d', $rangeStart);
            $rangeEndObj = DateTime::createFromFormat('Y-m-d', $rangeEnd);
            if (!$rangeStartObj || !$rangeEndObj) {
                continue;
            }
            while ($rangeStartObj < $rangeEndObj) {
                $dateKey = $rangeStartObj->format('Y-m-d');
                if (!isset($occupiedRoomsByDate[$dateKey])) {
                    $occupiedRoomsByDate[$dateKey] = array();
                }
                $occupiedRoomsByDate[$dateKey][$roomId] = true;
                $rangeStartObj->modify('+1 day');
            }
        }

        $occupancyPeriodData = array();
        $occupancyPeriodMaxPct = 0;
        $occupancyPeriodTotalPct = 0;
        $totalRooms = isset($metrics['rooms']) ? (int)$metrics['rooms'] : 0;
        for ($i = 0; $i < $dashboardPeriod; $i++) {
            $dateObj = clone $dashboardPeriodStart;
            if ($i > 0) {
                $dateObj->modify('+' . $i . ' days');
            }
            $dateKey = $dateObj->format('Y-m-d');
            $occupiedCount = isset($occupiedRoomsByDate[$dateKey]) ? count($occupiedRoomsByDate[$dateKey]) : 0;
            $pct = $totalRooms > 0 ? (int)round(($occupiedCount / $totalRooms) * 100) : 0;
            $occupancyPeriodTotalPct += $pct;
            if ($pct > $occupancyPeriodMaxPct) {
                $occupancyPeriodMaxPct = $pct;
            }
            $occupancyPeriodData[] = array(
                'date_key' => $dateKey,
                'label' => $dateObj->format('d M'),
                'count' => $occupiedCount,
                'pct' => $pct,
                'is_selected' => ($dateKey === $dashboardDate)
            );
        }
        $occupancyPeriodAvgPct = $dashboardPeriod > 0 ? (int)round($occupancyPeriodTotalPct / $dashboardPeriod) : 0;
    } catch (Exception $e) {
        $dashboardError = 'No fue posible calcular las estadisticas: ' . $e->getMessage();
    }

    try {
        $activityPropertyName = $availabilityPropertyName;
        $activitySql = "SELECT
                r.id_reservation,
                r.check_in_date,
                r.check_out_date,
                r.status,
                r.source,
                r.balance_due_cents,
                r.currency,
                rm.code AS room_code,
                g.names,
                g.last_name,
                p.code AS property_code,
                p.name AS property_name,
                GROUP_CONCAT(DISTINCT sic.item_name ORDER BY sic.item_name SEPARATOR ', ') AS interest_list
            FROM reservation r
            JOIN property p ON p.id_property = r.id_property
            LEFT JOIN room rm ON rm.id_room = r.id_room
            LEFT JOIN guest g ON g.id_guest = r.id_guest
            LEFT JOIN reservation_interest ri ON ri.id_reservation = r.id_reservation
              AND ri.deleted_at IS NULL
              AND ri.is_active = 1
            LEFT JOIN line_item_catalog sic ON sic.id_line_item_catalog = ri.id_sale_item_catalog AND sic.catalog_type = 'sale_item'
            LEFT JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
              AND cat.id_company = p.id_company
              AND cat.deleted_at IS NULL
            WHERE p.id_company = ?
              AND r.deleted_at IS NULL
              AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
              AND r.check_in_date <= ?
              AND r.check_out_date >= ?";
        $activityParams = array($companyId, $dashboardDate, $dashboardDate);
        if (!$isAllProperties && $availabilityPropertyId !== null) {
            $activitySql .= " AND p.id_property = ?";
            $activityParams[] = $availabilityPropertyId;
        } elseif ($isAllProperties && !pms_is_owner_user() && !empty($allowedPropertyIds)) {
            $activitySql .= " AND p.id_property IN (" . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ")";
            $activityParams = array_merge($activityParams, $allowedPropertyIds);
        }
        $activitySql .= " GROUP BY r.id_reservation, r.check_in_date, r.check_out_date, r.status, r.source,
                r.balance_due_cents, r.currency, rm.code, g.names, g.last_name, p.code, p.name
            ORDER BY p.name, r.check_in_date, r.check_out_date, r.id_reservation";
        $stmt = $pdo->prepare($activitySql);
        $stmt->execute($activityParams);
        $activityRows = $stmt->fetchAll();

        foreach ($activityRows as $row) {
            if ($activityPropertyName === '' && isset($row['property_name'])) {
                $activityPropertyName = (string)$row['property_name'];
            }

            $nameParts = array();
            if (isset($row['names']) && $row['names'] !== '') {
                $nameParts[] = $row['names'];
            }
            if (isset($row['last_name']) && $row['last_name'] !== '') {
                $nameParts[] = $row['last_name'];
            }
            $guestName = trim(implode(' ', $nameParts));
            if ($guestName === '') {
                $guestName = 'Sin huesped';
            }

            $roomLabel = isset($row['room_code']) ? (string)$row['room_code'] : '';
            if ($roomLabel === '') {
                $roomLabel = 'Sin habitacion';
            }

            $propertyCode = isset($row['property_code']) ? strtoupper((string)$row['property_code']) : '';
            $propertyName = isset($row['property_name']) ? (string)$row['property_name'] : '';
            $propertyLabel = '';
            if ($propertyCode !== '' && $propertyName !== '') {
                $propertyLabel = $propertyCode . ' - ' . $propertyName;
            } elseif ($propertyCode !== '') {
                $propertyLabel = $propertyCode;
            } elseif ($propertyName !== '') {
                $propertyLabel = $propertyName;
            }

            $statusLabel = isset($row['status']) ? trim((string)$row['status']) : '';
            if ($statusLabel === '') {
                $statusLabel = 'confirmado';
            }
            $statusRaw = isset($row['status']) ? (string)$row['status'] : '';
            $statusNorm = strtolower(trim($statusRaw !== '' ? $statusRaw : $statusLabel));
            $statusNorm = preg_replace('/\s+/', ' ', $statusNorm);
            if ($statusNorm === 'encasa') {
                $statusNorm = 'en casa';
            }

            $sourceLabel = isset($row['source']) ? trim((string)$row['source']) : '';
            if ($sourceLabel === '') {
                $sourceLabel = 'directo';
            }

            $nights = '';
            $checkInObj = !empty($row['check_in_date']) ? DateTime::createFromFormat('Y-m-d', (string)$row['check_in_date']) : null;
            $checkOutObj = !empty($row['check_out_date']) ? DateTime::createFromFormat('Y-m-d', (string)$row['check_out_date']) : null;
            if ($checkInObj && $checkOutObj) {
                $nights = max(1, (int)$checkInObj->diff($checkOutObj)->days);
            }

            $entry = array(
                'id_reservation' => isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0,
                'guest_name' => $guestName,
                'room_label' => $roomLabel,
                'property_label' => $propertyLabel,
                'check_in_date' => isset($row['check_in_date']) ? (string)$row['check_in_date'] : '',
                'check_out_date' => isset($row['check_out_date']) ? (string)$row['check_out_date'] : '',
                'nights' => $nights,
                'status_label' => $statusLabel,
                'status_raw' => $statusRaw,
                'source_label' => $sourceLabel,
                'balance_due_cents' => isset($row['balance_due_cents']) ? (int)$row['balance_due_cents'] : 0,
                'currency' => isset($row['currency']) ? (string)$row['currency'] : 'MXN',
                'interest_list' => isset($row['interest_list']) ? (string)$row['interest_list'] : ''
            );

            $checkInDate = isset($row['check_in_date']) ? (string)$row['check_in_date'] : '';
            $checkOutDate = isset($row['check_out_date']) ? (string)$row['check_out_date'] : '';
            $isConfirmed = ($statusNorm === 'confirmado' || $statusNorm === 'confirmed');
            $isInHouse = ($statusNorm === 'en casa' || $statusNorm === 'checkedin');

            if ($isConfirmed && $checkInDate === $dashboardDate) {
                $activityBuckets['checkins'][] = $entry;
            }
            if ($isInHouse) {
                if ($checkOutDate === $dashboardDate) {
                    $activityBuckets['checkouts'][] = $entry;
                } elseif ($checkOutDate === '' || $checkOutDate > $dashboardDate) {
                    $activityBuckets['in_house'][] = $entry;
                }
            }
            if ((int)$entry['balance_due_cents'] > 0) {
                $activityBuckets['balances'][] = $entry;
            }
        }
    } catch (Exception $e) {
        $activitiesError = 'No fue posible cargar las actividades del dia: ' . $e->getMessage();
    }

    try {
        $availabilitySql = "SELECT
                p.code AS property_code,
                p.name AS property_name,
                rm.id_room,
                rm.code AS room_code,
                rm.name AS room_name,
                rc.name AS category_name,
                (
                    SELECT MIN(r2.check_in_date)
                    FROM reservation r2
                    WHERE r2.id_room = rm.id_room
                      AND r2.deleted_at IS NULL
                      AND COALESCE(r2.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
                      AND r2.check_in_date > ?
                ) AS next_reservation_start,
                (
                    SELECT MIN(rb2.start_date)
                    FROM room_block rb2
                    WHERE rb2.id_room = rm.id_room
                      AND rb2.deleted_at IS NULL
                      AND rb2.is_active = 1
                      AND rb2.start_date > ?
                ) AS next_block_start
             FROM room rm
             JOIN property p ON p.id_property = rm.id_property
             LEFT JOIN roomcategory rc ON rc.id_category = rm.id_category
             WHERE p.id_company = ?
               AND p.is_active = 1
               AND p.deleted_at IS NULL
               AND rm.is_active = 1
               AND rm.deleted_at IS NULL";
        $availabilityParams = array(
            $dashboardDate,
            $dashboardDate,
            $companyId
        );
        if ($availabilityPropertyId !== null) {
            $availabilitySql .= " AND p.id_property = ?";
            $availabilityParams[] = $availabilityPropertyId;
        } elseif ($isAllProperties && !pms_is_owner_user() && !empty($allowedPropertyIds)) {
            $availabilitySql .= " AND p.id_property IN (" . implode(',', array_fill(0, count($allowedPropertyIds), '?')) . ")";
            $availabilityParams = array_merge($availabilityParams, $allowedPropertyIds);
        }
        $availabilitySql .= " AND NOT EXISTS (
                    SELECT 1
                    FROM reservation r
                    WHERE r.id_room = rm.id_room
                      AND r.deleted_at IS NULL
                      AND COALESCE(r.status, 'confirmado') NOT IN ('cancelled','canceled','cancelado','cancelada')
                      AND r.check_in_date <= ?
                      AND r.check_out_date > ?
               )
               AND NOT EXISTS (
                    SELECT 1
                    FROM room_block rb
                    WHERE rb.id_room = rm.id_room
                      AND rb.deleted_at IS NULL
                      AND rb.is_active = 1
                      AND rb.start_date <= ?
                      AND rb.end_date >= ?
               )
             ORDER BY p.name, rm.code";
        $availabilityParams[] = $dashboardDate;
        $availabilityParams[] = $dashboardDate;
        $availabilityParams[] = $dashboardDate;
        $availabilityParams[] = $dashboardDate;

        $stmt = $pdo->prepare($availabilitySql);
        $stmt->execute($availabilityParams);
        $availabilityRows = $stmt->fetchAll();

        foreach ($availabilityRows as $row) {
            $propertyCode = isset($row['property_code']) ? strtoupper((string)$row['property_code']) : '';
            if ($propertyCode === '') {
                continue;
            }
            if (!isset($availabilityByProperty[$propertyCode])) {
                $availabilityByProperty[$propertyCode] = array(
                    'property_name' => isset($row['property_name']) ? (string)$row['property_name'] : '',
                    'rooms' => array()
                );
            }

            $roomLabel = isset($row['room_code']) ? (string)$row['room_code'] : '';
            if ($roomLabel === '') {
                $roomLabel = 'Sin habitacion';
            }

            $nextRes = isset($row['next_reservation_start']) ? (string)$row['next_reservation_start'] : '';
            $nextBlock = isset($row['next_block_start']) ? (string)$row['next_block_start'] : '';
            $nextDate = '';
            $nextType = '';
            if ($nextRes !== '' && $nextBlock !== '') {
                if ($nextRes <= $nextBlock) {
                    $nextDate = $nextRes;
                    $nextType = 'Reserva';
                } else {
                    $nextDate = $nextBlock;
                    $nextType = 'Bloqueo';
                }
            } elseif ($nextRes !== '') {
                $nextDate = $nextRes;
                $nextType = 'Reserva';
            } elseif ($nextBlock !== '') {
                $nextDate = $nextBlock;
                $nextType = 'Bloqueo';
            }

            $availableNights = null;
            if ($nextDate !== '') {
                $nextDateObj = DateTime::createFromFormat('Y-m-d', $nextDate);
                if ($nextDateObj) {
                    $availableNights = max(1, (int)$dashboardDateObj->diff($nextDateObj)->days);
                }
            }

            $availabilityByProperty[$propertyCode]['rooms'][] = array(
                'room_label' => $roomLabel,
                'category_name' => isset($row['category_name']) ? (string)$row['category_name'] : '',
                'available_nights' => $availableNights,
                'next_date' => $nextDate,
                'next_type' => $nextType
            );
        }
    } catch (Exception $e) {
        $availabilityError = 'No fue posible cargar la disponibilidad: ' . $e->getMessage();
    }

    try {
        $obligationPropertyCodeFilter = (!$isAllProperties && $availabilityPropertyCode !== '' && $availabilityPropertyCode !== $availabilityAllKey)
            ? $availabilityPropertyCode
            : null;

        $obligationSets = pms_call_procedure('sp_obligation_data', array(
            $companyCode,
            $obligationPropertyCodeFilter,
            null,
            null,
            null,
            null,
            0,
            0,
            0,
            2000
        ));
        $obligationRowsRaw = isset($obligationSets[0]) ? $obligationSets[0] : array();

        $dashboardObligationBuckets = array(
            'property_payment' => array(
                'past' => array(),
                'today' => array(),
                'future' => array()
            ),
            'ota_payment' => array(
                'past' => array(),
                'today' => array(),
                'future' => array()
            ),
            'tax_payment' => array(
                'past' => array(),
                'today' => array(),
                'future' => array()
            )
        );
        $dashboardObligationTypeCounts = array(
            'property_payment' => 0,
            'ota_payment' => 0,
            'tax_payment' => 0
        );
        foreach ($obligationRowsRaw as $obligationRow) {
            $obligationPropertyCode = strtoupper(trim((string)(isset($obligationRow['property_code']) ? $obligationRow['property_code'] : '')));
            if (!$isAllProperties && $availabilityPropertyCode !== '' && $availabilityPropertyCode !== $availabilityAllKey && $obligationPropertyCode !== $availabilityPropertyCode) {
                continue;
            }
            if ($isAllProperties && !pms_is_owner_user() && $allowedPropertyCodeSet && !isset($allowedPropertyCodeSet[$obligationPropertyCode])) {
                continue;
            }
            $amountCents = isset($obligationRow['amount_cents']) ? (int)$obligationRow['amount_cents'] : 0;
            $paidCents = isset($obligationRow['paid_cents']) ? (int)$obligationRow['paid_cents'] : 0;
            $remainingCents = max(0, $amountCents - $paidCents);
            if ($amountCents <= 0 || $remainingCents <= 0) {
                continue;
            }
            $rowType = isset($obligationRow['obligation_type_key']) ? (string)$obligationRow['obligation_type_key'] : 'property_payment';
            $hasTaxParentLineItemType = isset($obligationRow['has_tax_parent_line_item_type'])
                ? ((int)$obligationRow['has_tax_parent_line_item_type'] === 1)
                : false;
            $parentConceptText = strtolower(trim((string)($obligationRow['parent_concept_name'] ?? '')));
            $conceptText = strtolower(trim((string)($obligationRow['concept_display_name'] ?? '')));
            if ($hasTaxParentLineItemType) {
                $rowType = 'tax_payment';
            } elseif (
                ($parentConceptText !== '' && strpos($parentConceptText, 'impuesto') !== false)
                || ($conceptText !== '' && strpos($conceptText, 'pago de impuestos') !== false)
            ) {
                $rowType = 'tax_payment';
            }
            if (!isset($dashboardObligationTypeCounts[$rowType])) {
                $rowType = 'property_payment';
            }
            $dashboardObligationTypeCounts[$rowType]++;

            $currency = isset($obligationRow['currency']) && trim((string)$obligationRow['currency']) !== ''
                ? (string)$obligationRow['currency']
                : 'MXN';

            $obligationDate = isset($obligationRow['obligation_date']) ? substr(trim((string)$obligationRow['obligation_date']), 0, 10) : '';
            if ($obligationDate === '') {
                $obligationDate = isset($obligationRow['service_date']) ? substr(trim((string)$obligationRow['service_date']), 0, 10) : '';
            }
            $reservationCheckInDate = isset($obligationRow['check_in_date']) ? substr(trim((string)$obligationRow['check_in_date']), 0, 10) : '';
            $bucketDate = $obligationDate;
            if (
                $reservationCheckInDate !== ''
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $reservationCheckInDate)
                && (
                    $bucketDate === ''
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bucketDate)
                    || $reservationCheckInDate > $bucketDate
                )
            ) {
                $bucketDate = $reservationCheckInDate;
            }
            $bucketKey = 'today';
            if ($bucketDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bucketDate)) {
                if ($bucketDate < $dashboardDate) {
                    $bucketKey = 'past';
                } elseif ($bucketDate > $dashboardDate) {
                    $bucketKey = 'future';
                }
            }

            $dashboardObligationBuckets[$rowType][$bucketKey][] = array(
                'id_line_item' => isset($obligationRow['id_line_item']) ? (int)$obligationRow['id_line_item'] : 0,
                'id_reservation' => isset($obligationRow['id_reservation']) ? (int)$obligationRow['id_reservation'] : 0,
                'reservation_code' => isset($obligationRow['reservation_code']) ? (string)$obligationRow['reservation_code'] : '',
                'check_in_date' => isset($obligationRow['check_in_date']) ? (string)$obligationRow['check_in_date'] : '',
                'property_code' => isset($obligationRow['property_code']) ? (string)$obligationRow['property_code'] : '',
                'property_name' => trim((string)(isset($obligationRow['property_name']) ? $obligationRow['property_name'] : '')) !== ''
                    ? (string)$obligationRow['property_name']
                    : 'Sin propiedad',
                'folio_name' => isset($obligationRow['folio_name']) ? (string)$obligationRow['folio_name'] : '',
                'guest_name' => isset($obligationRow['guest_name']) ? (string)$obligationRow['guest_name'] : '',
                'catalog_item_name' => isset($obligationRow['catalog_item_name']) ? (string)$obligationRow['catalog_item_name'] : '',
                'concept_display_name' => isset($obligationRow['concept_display_name']) ? (string)$obligationRow['concept_display_name'] : '',
                'parent_concept_name' => isset($obligationRow['parent_concept_name']) ? (string)$obligationRow['parent_concept_name'] : '',
                'obligation_date' => $obligationDate,
                'bucket_date' => $bucketDate,
                'amount_cents' => $amountCents,
                'paid_cents' => $paidCents,
                'remaining_cents' => $remainingCents,
                'default_add_value' => number_format(((float)$remainingCents) / 100, 2, '.', ''),
                'currency' => $currency,
                'obligation_type_key' => $rowType
            );
        }

        $sortByServiceDateAsc = function ($a, $b) {
            $dateA = isset($a['bucket_date']) ? (string)$a['bucket_date'] : '';
            $dateB = isset($b['bucket_date']) ? (string)$b['bucket_date'] : '';
            if ($dateA === $dateB) {
                return (int)($a['id_line_item'] ?? 0) <=> (int)($b['id_line_item'] ?? 0);
            }
            return strcmp($dateA, $dateB);
        };
        $sortByServiceDateDesc = function ($a, $b) use ($sortByServiceDateAsc) {
            return 0 - $sortByServiceDateAsc($a, $b);
        };
        foreach (array('property_payment', 'ota_payment', 'tax_payment') as $obligationTypeKey) {
            usort($dashboardObligationBuckets[$obligationTypeKey]['past'], $sortByServiceDateDesc);
            usort($dashboardObligationBuckets[$obligationTypeKey]['today'], $sortByServiceDateAsc);
            usort($dashboardObligationBuckets[$obligationTypeKey]['future'], $sortByServiceDateAsc);
        }
    } catch (Exception $e) {
        $dashboardObligationBuckets = array(
            'property_payment' => array(
                'past' => array(),
                'today' => array(),
                'future' => array()
            ),
            'ota_payment' => array(
                'past' => array(),
                'today' => array(),
                'future' => array()
            ),
            'tax_payment' => array(
                'past' => array(),
                'today' => array(),
                'future' => array()
            )
        );
        $dashboardObligationTypeCounts = array(
            'property_payment' => 0,
            'ota_payment' => 0,
            'tax_payment' => 0
        );
        $dashboardObligationError = 'No fue posible cargar obligaciones: ' . $e->getMessage();
    }

    try {
        $stmtObligationMethods = $pdo->prepare(
            'SELECT
                m.id_obligation_payment_method,
                m.method_name,
                COALESCE(m.method_description, \'\') AS method_description
             FROM pms_settings_obligation_payment_method m
             WHERE m.id_company = ?
               AND m.deleted_at IS NULL
               AND m.is_active = 1
               AND COALESCE(m.method_description, \'\') NOT LIKE \'[scope:income]%%\'
             ORDER BY m.method_name, m.id_obligation_payment_method'
        );
        $stmtObligationMethods->execute(array($companyId));
        $obligationPaymentMethods = $stmtObligationMethods->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $obligationPaymentMethods = array();
        if ($dashboardObligationError === null) {
            $dashboardObligationError = 'No fue posible cargar metodos de pago de obligaciones: ' . $e->getMessage();
        }
    }
}

function pms_dashboard_render_activity_list($items, $context, $dashboardDateInput, $availabilityPropertyCode, $emptyMessage, $showProperty = false)
{
    $panelTabByContext = array(
        'checkin' => 'dashboard-activities-checkins',
        'inhouse' => 'dashboard-activities-inhouse',
        'checkout' => 'dashboard-activities-checkouts',
        'balance' => 'dashboard-activities-balances'
    );
    $panelTabId = isset($panelTabByContext[$context]) ? $panelTabByContext[$context] : 'dashboard-activities-checkins';
    if (!$items) {
        echo '<p class="muted">' . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '</p>';
        return;
    }
    echo '<div class="daily-list">';
    foreach ($items as $item) {
        $statusRaw = isset($item['status_raw']) && $item['status_raw'] !== ''
            ? (string)$item['status_raw']
            : (string)$item['status_label'];
        $statusNorm = strtolower(trim($statusRaw));
        $statusNorm = preg_replace('/\s+/', ' ', $statusNorm);
        if ($statusNorm === 'encasa') {
            $statusNorm = 'en casa';
        }
        $canCheckIn = ($statusNorm === 'confirmado' || $statusNorm === 'confirmed');
        $isInHouse = ($statusNorm === 'en casa' || $statusNorm === 'checkedin');
        $canCheckOut = $isInHouse;
        $balanceCents = isset($item['balance_due_cents']) ? (int)$item['balance_due_cents'] : 0;
        $balanceCurrency = isset($item['currency']) ? (string)$item['currency'] : 'MXN';
        $balanceLabel = ($balanceCurrency === 'MXN' ? '$' : '')
            . number_format($balanceCents / 100, 2, '.', ',')
            . ($balanceCurrency ? ' ' . $balanceCurrency : '');
        echo '<div class="daily-row">';
        echo '<div class="daily-row-main">';
        echo '<div class="daily-field"><span class="label">Huesped</span><span>'
            . htmlspecialchars($item['guest_name'], ENT_QUOTES, 'UTF-8')
            . '</span></div>';
        if ($showProperty && !empty($item['property_label'])) {
            echo '<div class="daily-field"><span class="label">Propiedad</span><span>'
                . htmlspecialchars($item['property_label'], ENT_QUOTES, 'UTF-8')
                . '</span></div>';
        }
        echo '<div class="daily-field"><span class="label">Habitacion</span><span>'
            . htmlspecialchars($item['room_label'], ENT_QUOTES, 'UTF-8')
            . '</span></div>';
        echo '<div class="daily-field"><span class="label">Entrada</span><span>'
            . htmlspecialchars($item['check_in_date'], ENT_QUOTES, 'UTF-8')
            . '</span></div>';
        echo '<div class="daily-field"><span class="label">Salida</span><span>'
            . htmlspecialchars($item['check_out_date'], ENT_QUOTES, 'UTF-8')
            . '</span></div>';
        echo '<div class="daily-field"><span class="label">Noches</span><span>'
            . htmlspecialchars((string)$item['nights'], ENT_QUOTES, 'UTF-8')
            . '</span></div>';
        echo '<div class="daily-field"><span class="label">Fuente</span><span>'
            . htmlspecialchars($item['source_label'], ENT_QUOTES, 'UTF-8')
            . '</span></div>';
        $interestRaw = isset($item['interest_list']) ? trim((string)$item['interest_list']) : '';
        $interestItems = $interestRaw !== '' ? array_filter(array_map('trim', explode(',', $interestRaw))) : array();
        echo '<div class="daily-field"><span class="label">Intereses</span>';
        if ($interestItems) {
            echo '<div class="interest-tags is-compact">';
            foreach ($interestItems as $interestLabel) {
                echo '<span class="interest-pill">' . htmlspecialchars($interestLabel, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '</div>';
        } else {
            echo '<span class="muted">Sin intereses</span>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="daily-row-actions"><div class="daily-action-stack">';
        echo '<div class="daily-balance"><span class="label">Balance</span><strong>'
            . htmlspecialchars($balanceLabel, ENT_QUOTES, 'UTF-8')
            . '</strong></div>';
        if (!empty($item['id_reservation'])) {
            echo '<form method="post" action="index.php?view=reservations" class="daily-action-form">';
            echo '<input type="hidden" name="reservations_subtab_action" value="open">';
            echo '<input type="hidden" name="reservations_subtab_target" value="reservation:' . (int)$item['id_reservation'] . '">';
            echo '<button type="submit" class="button-secondary">Aplicar pago</button>';
            echo '</form>';
        }
        if ($context === 'checkin') {
            if ($isInHouse) {
                echo '<span class="status-pill is-inhouse">En casa</span>';
            } elseif ($canCheckIn && !empty($item['id_reservation'])) {
                echo '<form method="post" class="daily-action-form">';
                echo '<input type="hidden" name="dashboard_action" value="check_in">';
                echo '<input type="hidden" name="reservation_id" value="' . (int)$item['id_reservation'] . '">';
                echo '<input type="hidden" name="dashboard_date" value="' . htmlspecialchars($dashboardDateInput, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_period" value="' . (int)$GLOBALS['dashboardPeriod'] . '">';
                echo '<input type="hidden" name="availability_property" value="' . htmlspecialchars($availabilityPropertyCode, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_active_tab" value="' . htmlspecialchars($panelTabId, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_obligation_tab" value="' . htmlspecialchars((string)$GLOBALS['dashboardObligationTab'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_obligation_type" value="' . htmlspecialchars((string)$GLOBALS['dashboardObligationType'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<button type="submit" class="button-secondary">Check-in</button>';
                echo '</form>';
            } else {
                echo '<span class="muted">No disponible</span>';
            }
        } elseif ($context === 'checkout') {
            if ($canCheckOut && !empty($item['id_reservation'])) {
                echo '<form method="post" class="daily-action-form">';
                echo '<input type="hidden" name="dashboard_action" value="check_out">';
                echo '<input type="hidden" name="reservation_id" value="' . (int)$item['id_reservation'] . '">';
                echo '<input type="hidden" name="dashboard_date" value="' . htmlspecialchars($dashboardDateInput, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_period" value="' . (int)$GLOBALS['dashboardPeriod'] . '">';
                echo '<input type="hidden" name="availability_property" value="' . htmlspecialchars($availabilityPropertyCode, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_active_tab" value="' . htmlspecialchars($panelTabId, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_obligation_tab" value="' . htmlspecialchars((string)$GLOBALS['dashboardObligationTab'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_obligation_type" value="' . htmlspecialchars((string)$GLOBALS['dashboardObligationType'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<button type="submit" class="button-secondary">Check-out</button>';
                echo '</form>';
            } else {
                echo '<span class="muted">No disponible</span>';
            }
        } else {
            if ($isInHouse) {
                echo '<span class="status-pill is-inhouse">En casa</span>';
            } elseif ($context === 'balance' && $canCheckIn && !empty($item['id_reservation'])) {
                echo '<form method="post" class="daily-action-form">';
                echo '<input type="hidden" name="dashboard_action" value="check_in">';
                echo '<input type="hidden" name="reservation_id" value="' . (int)$item['id_reservation'] . '">';
                echo '<input type="hidden" name="dashboard_date" value="' . htmlspecialchars($dashboardDateInput, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_period" value="' . (int)$GLOBALS['dashboardPeriod'] . '">';
                echo '<input type="hidden" name="availability_property" value="' . htmlspecialchars($availabilityPropertyCode, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_active_tab" value="' . htmlspecialchars($panelTabId, ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_obligation_tab" value="' . htmlspecialchars((string)$GLOBALS['dashboardObligationTab'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<input type="hidden" name="dashboard_obligation_type" value="' . htmlspecialchars((string)$GLOBALS['dashboardObligationType'], ENT_QUOTES, 'UTF-8') . '">';
                echo '<button type="submit" class="button-secondary">Check-in</button>';
                echo '</form>';
            } else {
                echo '<span class="muted">No disponible</span>';
            }
        }
        if (!empty($item['id_reservation'])) {
            echo '<form method="post" action="index.php?view=reservations" class="daily-action-form daily-action-footer">';
            echo '<input type="hidden" name="reservations_subtab_action" value="open">';
            echo '<input type="hidden" name="reservations_subtab_target" value="reservation:' . (int)$item['id_reservation'] . '">';
            echo '<button type="submit" class="button-secondary">Ver reservacion</button>';
            echo '</form>';
        }
        echo '</div></div></div>';
    }
    echo '</div>';
}

function pms_dashboard_render_obligation_list($items, $emptyMessage, $dashboardDateInput, $availabilityPropertyCode, $dashboardPeriod, $dashboardActiveTab, $dashboardObligationTab, $dashboardObligationType, $obligationPaymentMethods = array())
{
    if (!$items) {
        echo '<p class="muted">' . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '</p>';
        return;
    }
    $defaultMethodId = 0;
    if (!empty($obligationPaymentMethods) && isset($obligationPaymentMethods[0]['id_obligation_payment_method'])) {
        $defaultMethodId = (int)$obligationPaymentMethods[0]['id_obligation_payment_method'];
    }
    $bulkCount = 0;
    foreach ($items as $bulkRow) {
        $bulkLineItemId = isset($bulkRow['id_line_item']) ? (int)$bulkRow['id_line_item'] : 0;
        $bulkAmountCents = isset($bulkRow['amount_cents']) ? (int)$bulkRow['amount_cents'] : 0;
        $bulkRemainingCents = isset($bulkRow['remaining_cents']) ? (int)$bulkRow['remaining_cents'] : 0;
        if ($bulkLineItemId > 0 && $bulkAmountCents > 0 && $bulkRemainingCents > 0) {
            $bulkCount++;
        }
    }

    echo '<form method="post" class="form-inline" style="gap:6px; flex-wrap:wrap; margin-bottom:10px;" onsubmit="return confirm(\'Se pagaran todas las obligaciones visibles. Deseas continuar?\');">';
    echo '<input type="hidden" name="dashboard_date" value="' . htmlspecialchars($dashboardDateInput, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="dashboard_period" value="' . (int)$dashboardPeriod . '">';
    echo '<input type="hidden" name="availability_property" value="' . htmlspecialchars($availabilityPropertyCode, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="dashboard_active_tab" value="' . htmlspecialchars($dashboardActiveTab, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="dashboard_obligation_tab" value="' . htmlspecialchars($dashboardObligationTab, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="dashboard_obligation_type" value="' . htmlspecialchars($dashboardObligationType, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($items as $bulkRow) {
        $bulkLineItemId = isset($bulkRow['id_line_item']) ? (int)$bulkRow['id_line_item'] : 0;
        $bulkAmountCents = isset($bulkRow['amount_cents']) ? (int)$bulkRow['amount_cents'] : 0;
        $bulkRemainingCents = isset($bulkRow['remaining_cents']) ? (int)$bulkRow['remaining_cents'] : 0;
        if ($bulkLineItemId <= 0 || $bulkAmountCents <= 0 || $bulkRemainingCents <= 0) {
            continue;
        }
        echo '<input type="hidden" name="obligation_bulk_line_item_id[]" value="' . $bulkLineItemId . '">';
        echo '<input type="hidden" name="obligation_bulk_amount_cents[]" value="' . $bulkAmountCents . '">';
    }
    echo '<span class="muted">Visibles: ' . (int)$bulkCount . '</span>';
    echo '<select name="obligation_payment_method_id" style="min-width:150px;" title="Metodo de pago" required' . (empty($obligationPaymentMethods) ? ' disabled' : '') . '>';
    echo '<option value="0">Metodo...</option>';
    foreach ($obligationPaymentMethods as $paymentMethod) {
        $methodId = isset($paymentMethod['id_obligation_payment_method']) ? (int)$paymentMethod['id_obligation_payment_method'] : 0;
        if ($methodId <= 0) {
            continue;
        }
        $methodName = isset($paymentMethod['method_name']) ? (string)$paymentMethod['method_name'] : ('Metodo #' . $methodId);
        echo '<option value="' . $methodId . '"' . ($methodId === $defaultMethodId ? ' selected' : '') . '>'
            . htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8')
            . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="obligation_payment_notes" value="" style="min-width:190px;" maxlength="500" placeholder="Notas de pago para todas">';
    echo '<button type="submit" class="button-primary" name="dashboard_action" value="obligation_apply_all"' . ((empty($obligationPaymentMethods) || $bulkCount <= 0) ? ' disabled' : '') . '>Pagar todas (visibles)</button>';
    echo '</form>';

    echo '<div class="table-scroll">';
    echo '<table class="compact-table">';
    echo '<thead><tr>'
        . '<th>Fecha de creacion</th>'
        . '<th>Propiedad</th>'
        . '<th>Reservacion</th>'
        . '<th>Huesped</th>'
        . '<th>Concepto</th>'
        . '<th>Monto</th>'
        . '<th>Pagado</th>'
        . '<th>Pendiente</th>'
        . '<th>Acciones</th>'
        . '</tr></thead><tbody>';
    foreach ($items as $row) {
        $lineItemId = isset($row['id_line_item']) ? (int)$row['id_line_item'] : 0;
        $reservationId = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
        $amountCents = isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0;
        $paidCents = isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0;
        $remainingCents = isset($row['remaining_cents']) ? (int)$row['remaining_cents'] : 0;
        $currency = isset($row['currency']) ? (string)$row['currency'] : 'MXN';
        $checkInDate = isset($row['check_in_date']) ? (string)$row['check_in_date'] : '';
        $reservationTitle = dashboard_format_reservation_title(
            isset($row['guest_name']) ? (string)$row['guest_name'] : '',
            $checkInDate
        );
        $conceptLabel = isset($row['concept_display_name']) ? trim((string)$row['concept_display_name']) : '';
        if ($conceptLabel === '') {
            $baseConcept = trim((string)($row['catalog_item_name'] ?? ''));
            $parentConcept = trim((string)($row['parent_concept_name'] ?? ''));
            if ($baseConcept === '') {
                $baseConcept = 'Concepto';
            }
            if ($parentConcept !== '') {
                $conceptLabel = $baseConcept . ' - ' . $parentConcept;
            } else {
                $conceptLabel = $baseConcept;
            }
        }
        $defaultAddValue = isset($row['default_add_value']) ? (string)$row['default_add_value'] : number_format(((float)$remainingCents) / 100, 2, '.', '');
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string)($row['obligation_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string)($row['property_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        if ($reservationId > 0) {
            $reservationHref = 'index.php?view=reservations&open_reservation=' . $reservationId;
            echo '<td><a href="'
                . htmlspecialchars($reservationHref, ENT_QUOTES, 'UTF-8')
                . '">'
                . htmlspecialchars($reservationTitle, ENT_QUOTES, 'UTF-8')
                . '</a></td>';
        } else {
            echo '<td>' . htmlspecialchars($reservationTitle, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '<td>' . htmlspecialchars((string)($row['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(dashboard_format_money($amountCents, $currency), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(dashboard_format_money($paidCents, $currency), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(dashboard_format_money($remainingCents, $currency), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>';
        echo '<form method="post" class="form-inline" style="gap:6px; flex-wrap:wrap;">';
        echo '<input type="hidden" name="dashboard_date" value="' . htmlspecialchars($dashboardDateInput, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="dashboard_period" value="' . (int)$dashboardPeriod . '">';
        echo '<input type="hidden" name="availability_property" value="' . htmlspecialchars($availabilityPropertyCode, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="dashboard_active_tab" value="' . htmlspecialchars($dashboardActiveTab, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="dashboard_obligation_tab" value="' . htmlspecialchars($dashboardObligationTab, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="dashboard_obligation_type" value="' . htmlspecialchars($dashboardObligationType, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="obligation_line_item_id" value="' . $lineItemId . '">';
        echo '<input type="hidden" name="obligation_full_amount_cents" value="' . $amountCents . '">';
        echo '<select name="obligation_payment_method_id" style="min-width:150px;" title="Metodo de pago" required>';
        echo '<option value="0">Metodo...</option>';
        foreach ($obligationPaymentMethods as $paymentMethod) {
            $methodId = isset($paymentMethod['id_obligation_payment_method']) ? (int)$paymentMethod['id_obligation_payment_method'] : 0;
            if ($methodId <= 0) {
                continue;
            }
            $methodName = isset($paymentMethod['method_name']) ? (string)$paymentMethod['method_name'] : ('Metodo #' . $methodId);
            echo '<option value="' . $methodId . '"' . ($methodId === $defaultMethodId ? ' selected' : '') . '>'
                . htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }
        echo '</select>';
        echo '<input type="text" name="obligation_payment_notes" value="" style="min-width:170px;" maxlength="500" placeholder="Notas de pago">';
        echo '<span style="display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">';
        echo '<input type="text" name="obligation_apply_amount" value="' . htmlspecialchars($defaultAddValue, ENT_QUOTES, 'UTF-8') . '" style="width:90px;" title="Monto a abonar">';
        echo '<button type="submit" class="button-secondary" name="dashboard_action" value="obligation_apply_add">Abonar</button>';
        echo '</span>';
        echo '<button type="submit" class="button-primary" name="dashboard_action" value="obligation_apply_full">Pago full</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
?>
<script>
window.addEventListener('DOMContentLoaded', function () {
  function getActiveObligationType() {
    var activeTypeRadio = document.querySelector('.dashboard-obligation-type-nav .dashboard-obligation-type-radio:checked');
    if (!activeTypeRadio) return '';
    return activeTypeRadio.getAttribute('data-obligation-type-target') || activeTypeRadio.value || '';
  }

  function updateDashboardStateInputs() {
    var activitiesActive = document.querySelector('.dashboard-activities-tabs .reservation-tab-trigger.is-active');
    var activitiesTarget = activitiesActive ? (activitiesActive.getAttribute('data-tab-target') || '') : '';
    var activeObligationType = getActiveObligationType();
    var obligationsTarget = '';

    if (activeObligationType) {
      var activeTypePanel = document.querySelector('[data-obligation-type-panel][data-obligation-type=\"' + activeObligationType + '\"]');
      if (activeTypePanel) {
        var obligationsActive = activeTypePanel.querySelector('.dashboard-obligations-tabs .reservation-tab-trigger.is-active');
        if (obligationsActive) {
          obligationsTarget = obligationsActive.getAttribute('data-obligation-tab-value') || obligationsActive.getAttribute('data-tab-target') || '';
        }
      }
    }

    if (activitiesTarget) {
      document.querySelectorAll('input[name=\"dashboard_active_tab\"]').forEach(function (input) {
        input.value = activitiesTarget;
      });
    }
    if (activeObligationType) {
      document.querySelectorAll('input[name=\"dashboard_obligation_type\"]').forEach(function (input) {
        input.value = activeObligationType;
      });
    }
    if (obligationsTarget) {
      document.querySelectorAll('input[name=\"dashboard_obligation_tab\"]').forEach(function (input) {
        input.value = obligationsTarget;
      });
    }
  }

  (function setupObligationTypeSwitcher() {
    var nav = document.querySelector('[data-obligation-type-nav]');
    if (!nav) return;

    var triggers = Array.prototype.slice.call(nav.querySelectorAll('.dashboard-obligation-type-radio'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-obligation-type-panel]'));
    if (!triggers.length || !panels.length) return;

    function activate(typeKey) {
      var safeType = '';
      triggers.forEach(function (input) {
        var currentType = input.getAttribute('data-obligation-type-target') || input.value || '';
        if (!safeType && currentType === typeKey) {
          safeType = currentType;
        }
      });
      if (!safeType) {
        safeType = triggers[0].getAttribute('data-obligation-type-target') || triggers[0].value || '';
      }
      if (!safeType) return;

      triggers.forEach(function (input) {
        var isActiveInput = ((input.getAttribute('data-obligation-type-target') || input.value || '') === safeType);
        input.checked = isActiveInput;
        var option = input.closest('.dashboard-obligation-type-option');
        if (option) {
          option.classList.toggle('is-active', isActiveInput);
        }
      });
      panels.forEach(function (panel) {
        var isActive = (panel.getAttribute('data-obligation-type') || '') === safeType;
        panel.classList.toggle('is-active', isActive);
        panel.hidden = !isActive;
      });
    }

    triggers.forEach(function (input) {
      input.addEventListener('change', function () {
        activate(input.getAttribute('data-obligation-type-target') || input.value || '');
        updateDashboardStateInputs();
      });
    });

    var initialType = '';
    triggers.some(function (input) {
      if (!input.checked) return false;
      initialType = input.getAttribute('data-obligation-type-target') || input.value || '';
      return initialType !== '';
    });
    if (!initialType) {
      initialType = triggers[0].getAttribute('data-obligation-type-target') || triggers[0].value || '';
    }
    activate(initialType);
  })();

  document.querySelectorAll('[data-reservation-tabs]').forEach(function (container) {
    var triggers = Array.prototype.slice.call(container.querySelectorAll('.reservation-tab-trigger')).filter(function (btn) {
      var nav = btn.closest('.reservation-tab-nav');
      return nav && nav.parentElement === container;
    });
    var panels = Array.prototype.slice.call(container.querySelectorAll('[data-tab-panel]')).filter(function (panel) {
      return panel.parentElement === container;
    });
    if (!triggers.length || !panels.length) return;

    var panelMap = {};
    panels.forEach(function (panel) {
      panelMap[panel.id] = panel;
    });

    function activate(targetId) {
      var safeTarget = panelMap[targetId] ? targetId : (panels[0] ? panels[0].id : '');
      if (!safeTarget) return;
      triggers.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-tab-target') === safeTarget);
      });
      panels.forEach(function (panel) {
        panel.classList.toggle('is-active', panel.id === safeTarget);
      });
    }

    triggers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-tab-target') || '';
        if (targetId) {
          activate(targetId);
          updateDashboardStateInputs();
        }
      });
    });

    var initialTarget = '';
    triggers.some(function (btn) {
      if (!btn.classList.contains('is-active')) return false;
      var targetId = btn.getAttribute('data-tab-target') || '';
      if (targetId && panelMap[targetId]) {
        initialTarget = targetId;
        return true;
      }
      return false;
    });
    if (!initialTarget) {
      triggers.some(function (btn) {
        var targetId = btn.getAttribute('data-tab-target') || '';
        if (targetId && panelMap[targetId]) {
          initialTarget = targetId;
          return true;
        }
        return false;
      });
    }
    if (initialTarget) {
      activate(initialTarget);
    }
  });
  updateDashboardStateInputs();

  document.querySelectorAll('[data-availability-collapsible]').forEach(function (section) {
    var toggle = section.querySelector('[data-availability-toggle]');
    var body = section.querySelector('[data-availability-body]');
    if (!toggle || !body) return;

    function syncState(isCollapsed) {
      section.classList.toggle('is-collapsed', isCollapsed);
      body.hidden = isCollapsed;
      toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
      toggle.textContent = isCollapsed ? 'Mostrar disponibilidad' : 'Ocultar disponibilidad';
    }

    syncState(section.classList.contains('is-collapsed'));
    toggle.addEventListener('click', function () {
      syncState(!section.classList.contains('is-collapsed'));
    });
  });
});
</script>

<?php
  $checkInCount = count($activityBuckets['checkins']);
  $inHouseCount = count($activityBuckets['in_house']);
  $checkOutCount = count($activityBuckets['checkouts']);
  $balanceCount = count($activityBuckets['balances']);
  $obligationTotalCount = (int)array_sum($dashboardObligationTypeCounts);
  if ($isAllProperties) {
      $activityHeaderName = 'Todas las propiedades';
      $activityHeaderCode = '';
  } else {
      $activityHeaderName = $activityPropertyName !== '' ? $activityPropertyName : $availabilityPropertyName;
      if ($activityHeaderName === '') {
          $activityHeaderName = $availabilityPropertyCode;
      }
      $activityHeaderCode = $availabilityPropertyCode !== '' ? $availabilityPropertyCode : '';
  }
  $balanceDueTotal = 0;
  foreach ($activityBuckets['balances'] as $balanceItem) {
      $balanceDueTotal += isset($balanceItem['balance_due_cents']) ? (int)$balanceItem['balance_due_cents'] : 0;
  }
  $availabilityTotal = 0;
  if ($isAllProperties && $availabilityByProperty) {
      foreach ($availabilityByProperty as $availabilityGroup) {
          $availabilityTotal += isset($availabilityGroup['rooms']) ? count($availabilityGroup['rooms']) : 0;
      }
  } elseif (!$isAllProperties && $availabilityPropertyCode !== '' && isset($availabilityByProperty[$availabilityPropertyCode]['rooms'])) {
      $availabilityTotal = count($availabilityByProperty[$availabilityPropertyCode]['rooms']);
  }
  $metrics['arrivals_today'] = $checkInCount;
  $metrics['departures_today'] = $checkOutCount;
  $metrics['in_house'] = $inHouseCount;
  $metrics['balances_count'] = $balanceCount;
  $metrics['balance_due_total'] = $balanceDueTotal / 100;
  $metrics['availability_today'] = $availabilityTotal;
?>

<section class="card">
  <div class="dashboard-day-header">
    <div class="dashboard-property-summary">
      <h2><?php echo htmlspecialchars($activityHeaderName !== '' ? $activityHeaderName : 'Propiedad', ENT_QUOTES, 'UTF-8'); ?></h2>
      <?php if ($activityHeaderCode !== ''): ?>
        <span class="muted"><?php echo htmlspecialchars($activityHeaderCode, ENT_QUOTES, 'UTF-8'); ?></span>
      <?php endif; ?>
    </div>
    <form method="get" class="dashboard-date-form dashboard-activities-form">
      <input type="hidden" name="view" value="dashboard">
      <label>
        Fecha y hora
        <input type="datetime-local" name="dashboard_date" value="<?php echo htmlspecialchars($dashboardDateInput, ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label class="dashboard-property-select">
        Propiedad
        <select name="availability_property" onchange="this.form.submit();">
          <option value="<?php echo htmlspecialchars($availabilityAllKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isAllProperties ? 'selected' : ''; ?>>Todas las propiedades</option>
          <?php foreach ($properties as $property):
            $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
            $name = isset($property['name']) ? (string)$property['name'] : '';
            if ($code === '') {
                continue;
            }
          ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $availabilityPropertyCode ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="hidden" name="dashboard_period" value="<?php echo $dashboardPeriod; ?>">
      <input type="hidden" name="dashboard_active_tab" value="<?php echo htmlspecialchars($dashboardActiveTab, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="dashboard_obligation_tab" value="<?php echo htmlspecialchars($dashboardObligationTab, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="dashboard_obligation_type" value="<?php echo htmlspecialchars($dashboardObligationType, ENT_QUOTES, 'UTF-8'); ?>">
      <button type="submit">Actualizar</button>
    </form>
  </div>
  <?php if ($dashboardActionError): ?>
    <p class="error"><?php echo htmlspecialchars($dashboardActionError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php elseif ($dashboardActionMessage): ?>
    <p class="success"><?php echo htmlspecialchars($dashboardActionMessage, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($dashboardError): ?>
    <p class="error"><?php echo htmlspecialchars($dashboardError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($activitiesError): ?>
    <p class="error"><?php echo htmlspecialchars($activitiesError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <div class="analytics-grid">
    <div class="analytics-card">
      <small>Habitaciones</small>
      <strong><?php echo $metrics['rooms']; ?></strong>
      <span><?php echo $metrics['occupied_today']; ?> ocupadas hoy (<?php echo number_format($metrics['occupancy_pct'], 1); ?>%).</span>
    </div>
    <div class="analytics-card">
      <small>Disponibles hoy</small>
      <strong><?php echo $metrics['availability_today']; ?></strong>
      <span>Habitaciones libres en la fecha seleccionada.</span>
    </div>
    <div class="analytics-card">
      <small>Check-ins hoy</small>
      <strong><?php echo $metrics['arrivals_today']; ?></strong>
      <span>Reservas con entrada hoy.</span>
    </div>
    <div class="analytics-card">
      <small>Check-outs hoy</small>
      <strong><?php echo $metrics['departures_today']; ?></strong>
      <span>Salidas programadas para hoy.</span>
    </div>
    <div class="analytics-card">
      <small>En casa</small>
      <strong><?php echo $metrics['in_house']; ?></strong>
      <span>Huespedes actualmente hospedados.</span>
    </div>
    <div class="analytics-card">
      <small>Balance pendiente</small>
      <strong><?php echo $metrics['balances_count']; ?></strong>
      <span>MXN <?php echo number_format($metrics['balance_due_total'], 2); ?> por cobrar.</span>
    </div>
    <div class="analytics-card">
      <small>Reservas proximos 30 dias</small>
      <strong><?php echo $metrics['reservations_upcoming']; ?></strong>
      <span>Entradas confirmadas.</span>
    </div>
    <div class="analytics-card">
      <small>Ingresos estimados 30 dias</small>
      <strong>MXN <?php echo number_format($metrics['revenue_30'], 2); ?></strong>
      <span>Basado en reservas actuales.</span>
    </div>
  </div>
  <div class="dashboard-arrivals-chart">
    <div class="dashboard-arrivals-header">
      <div>
        <h3>Ocupacion del periodo</h3>
        <p class="muted">Del <?php echo htmlspecialchars($dashboardPeriodStartLabel, ENT_QUOTES, 'UTF-8'); ?> al <?php echo htmlspecialchars($dashboardPeriodEndLabel, ENT_QUOTES, 'UTF-8'); ?>.</p>
      </div>
      <form method="get" class="dashboard-arrivals-filter">
        <input type="hidden" name="view" value="dashboard">
        <input type="hidden" name="dashboard_date" value="<?php echo htmlspecialchars($dashboardDateInput, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="availability_property" value="<?php echo htmlspecialchars($availabilityPropertyCode, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="dashboard_active_tab" value="<?php echo htmlspecialchars($dashboardActiveTab, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="dashboard_obligation_tab" value="<?php echo htmlspecialchars($dashboardObligationTab, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="dashboard_obligation_type" value="<?php echo htmlspecialchars($dashboardObligationType, ENT_QUOTES, 'UTF-8'); ?>">
        <label>
          Periodo
          <select name="dashboard_period" onchange="this.form.submit();">
            <?php foreach (array(7, 14, 21, 30, 45, 60) as $periodOption): ?>
              <option value="<?php echo $periodOption; ?>" <?php echo $dashboardPeriod === $periodOption ? 'selected' : ''; ?>>
                <?php echo $periodOption; ?> dias
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
      <div class="pill-row">
        <span class="pill">Promedio <?php echo $occupancyPeriodAvgPct; ?>%</span>
        <span class="pill">Max <?php echo $occupancyPeriodMaxPct; ?>%</span>
      </div>
    </div>
    <?php if ($occupancyPeriodData): ?>
      <div class="arrivals-chart">
        <div class="arrivals-chart-bars">
          <?php foreach ($occupancyPeriodData as $day):
            $heightPct = $day['pct'];
            $barTitle = $day['count'] . ' de ' . (int)$metrics['rooms'] . ' habitaciones';
          ?>
            <div class="arrivals-bar<?php echo $day['is_selected'] ? ' is-selected' : ''; ?>" title="<?php echo htmlspecialchars($barTitle, ENT_QUOTES, 'UTF-8'); ?>">
              <div class="arrivals-bar-fill" style="height: <?php echo $heightPct; ?>%;"></div>
              <span class="arrivals-bar-value"><?php echo $day['pct']; ?>%</span>
              <span class="arrivals-bar-label"><?php echo htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <p class="muted">Sin datos de ocupacion para este periodo.</p>
    <?php endif; ?>
  </div>
  <?php if ($availabilityPropertyCode === ''): ?>
    <p class="muted">Selecciona una propiedad para ver actividades.</p>
  <?php else: ?>
    <div class="dashboard-activities-block">
      <div class="daily-property">
	      <div class="reservation-tabs dashboard-activities-tabs" data-reservation-tabs="dashboard-activities">
	        <div class="reservation-tab-nav">
	          <button type="button" class="reservation-tab-trigger <?php echo $dashboardActiveTab === 'dashboard-activities-checkins' ? 'is-active' : ''; ?>" data-tab-target="dashboard-activities-checkins">Check-ins pendientes (<?php echo $checkInCount; ?>)</button>
	          <button type="button" class="reservation-tab-trigger <?php echo $dashboardActiveTab === 'dashboard-activities-inhouse' ? 'is-active' : ''; ?>" data-tab-target="dashboard-activities-inhouse">En casa (<?php echo $inHouseCount; ?>)</button>
	          <button type="button" class="reservation-tab-trigger <?php echo $dashboardActiveTab === 'dashboard-activities-checkouts' ? 'is-active' : ''; ?>" data-tab-target="dashboard-activities-checkouts">Check-outs pendientes (<?php echo $checkOutCount; ?>)</button>
	          <button type="button" class="reservation-tab-trigger <?php echo $dashboardActiveTab === 'dashboard-activities-balances' ? 'is-active' : ''; ?>" data-tab-target="dashboard-activities-balances">Balance pendiente (<?php echo $balanceCount; ?>)</button>
	          <button type="button" class="reservation-tab-trigger <?php echo $dashboardActiveTab === 'dashboard-activities-guest' ? 'is-active' : ''; ?>" data-tab-target="dashboard-activities-guest">Actividades del huesped</button>
	          <button type="button" class="reservation-tab-trigger <?php echo $dashboardActiveTab === 'dashboard-activities-obligations' ? 'is-active' : ''; ?>" data-tab-target="dashboard-activities-obligations">Obligaciones (<?php echo $obligationTotalCount; ?>)</button>
	        </div>
	        <div class="reservation-tab-panel <?php echo $dashboardActiveTab === 'dashboard-activities-checkins' ? 'is-active' : ''; ?>" id="dashboard-activities-checkins" data-tab-panel>
	          <?php pms_dashboard_render_activity_list($activityBuckets['checkins'], 'checkin', $dashboardDateInput, $availabilityPropertyCode, 'Sin check-ins pendientes para esta fecha.', $isAllProperties); ?>
	        </div>
	        <div class="reservation-tab-panel <?php echo $dashboardActiveTab === 'dashboard-activities-inhouse' ? 'is-active' : ''; ?>" id="dashboard-activities-inhouse" data-tab-panel>
	          <?php pms_dashboard_render_activity_list($activityBuckets['in_house'], 'inhouse', $dashboardDateInput, $availabilityPropertyCode, 'Sin huespedes en casa para esta fecha.', $isAllProperties); ?>
	        </div>
	        <div class="reservation-tab-panel <?php echo $dashboardActiveTab === 'dashboard-activities-checkouts' ? 'is-active' : ''; ?>" id="dashboard-activities-checkouts" data-tab-panel>
	          <?php pms_dashboard_render_activity_list($activityBuckets['checkouts'], 'checkout', $dashboardDateInput, $availabilityPropertyCode, 'Sin check-outs pendientes para esta fecha.', $isAllProperties); ?>
	        </div>
	        <div class="reservation-tab-panel <?php echo $dashboardActiveTab === 'dashboard-activities-balances' ? 'is-active' : ''; ?>" id="dashboard-activities-balances" data-tab-panel>
	          <?php pms_dashboard_render_activity_list($activityBuckets['balances'], 'balance', $dashboardDateInput, $availabilityPropertyCode, 'Sin balances pendientes para esta fecha.', $isAllProperties); ?>
	        </div>
	        <div class="reservation-tab-panel <?php echo $dashboardActiveTab === 'dashboard-activities-guest' ? 'is-active' : ''; ?>" id="dashboard-activities-guest" data-tab-panel>
	          <div class="dashboard-placeholder">
	            <p class="muted">Placeholder: aqui se mostraran las actividades programadas por huesped para este dia.</p>
	            <p class="muted">Conecta este panel con el modulo de experiencias cuando este listo.</p>
	          </div>
	        </div>
	        <div class="reservation-tab-panel <?php echo $dashboardActiveTab === 'dashboard-activities-obligations' ? 'is-active' : ''; ?>" id="dashboard-activities-obligations" data-tab-panel>
	          <?php if ($dashboardObligationError): ?>
	            <p class="error"><?php echo htmlspecialchars($dashboardObligationError, ENT_QUOTES, 'UTF-8'); ?></p>
	          <?php endif; ?>
              <?php if (!$dashboardObligationError && !$obligationPaymentMethods): ?>
                <p class="muted">Configura primero los metodos de pago de obligaciones en Configuraciones.</p>
              <?php endif; ?>
              <div class="dashboard-obligation-type-selector">
                <div class="dashboard-obligation-type-nav" data-obligation-type-nav role="radiogroup" aria-label="Tipo de obligacion">
                  <?php foreach ($dashboardObligationTypeLabels as $typeKey => $typeLabel): ?>
                    <label class="dashboard-obligation-type-option <?php echo $dashboardObligationType === $typeKey ? 'is-active' : ''; ?>">
                      <input
                        type="radio"
                        class="dashboard-obligation-type-radio"
                        name="dashboard_obligation_type_ui"
                        value="<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>"
                        data-obligation-type-target="<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $dashboardObligationType === $typeKey ? 'checked' : ''; ?>
                      >
                      <span class="dashboard-obligation-type-label">
                        <?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                        (<?php echo (int)(isset($dashboardObligationTypeCounts[$typeKey]) ? $dashboardObligationTypeCounts[$typeKey] : 0); ?>)
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="dashboard-obligation-type-panels">
                <?php foreach ($dashboardObligationTypeLabels as $typeKey => $typeLabel): ?>
                  <?php
                    $typeBuckets = isset($dashboardObligationBuckets[$typeKey]) ? $dashboardObligationBuckets[$typeKey] : array(
                        'past' => array(),
                        'today' => array(),
                        'future' => array()
                    );
                    $typePastCount = isset($typeBuckets['past']) ? count($typeBuckets['past']) : 0;
                    $typeTodayCount = isset($typeBuckets['today']) ? count($typeBuckets['today']) : 0;
                    $typeFutureCount = isset($typeBuckets['future']) ? count($typeBuckets['future']) : 0;
                  ?>
                  <div
                    class="dashboard-obligation-type-panel <?php echo $dashboardObligationType === $typeKey ? 'is-active' : ''; ?>"
                    data-obligation-type-panel
                    data-obligation-type="<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $dashboardObligationType === $typeKey ? '' : 'hidden'; ?>
                  >
	                <div class="reservation-tabs dashboard-obligations-tabs" data-reservation-tabs="dashboard-obligations-<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>">
	                  <div class="reservation-tab-nav">
	                    <button
                          type="button"
                          class="reservation-tab-trigger <?php echo $dashboardObligationTab === 'dashboard-obligations-past' ? 'is-active' : ''; ?>"
                          data-tab-target="dashboard-obligations-<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>-past"
                          data-obligation-tab-value="dashboard-obligations-past"
                        >Pasadas (<?php echo $typePastCount; ?>)</button>
	                    <button
                          type="button"
                          class="reservation-tab-trigger <?php echo $dashboardObligationTab === 'dashboard-obligations-today' ? 'is-active' : ''; ?>"
                          data-tab-target="dashboard-obligations-<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>-today"
                          data-obligation-tab-value="dashboard-obligations-today"
                        >Hoy (<?php echo $typeTodayCount; ?>)</button>
	                    <button
                          type="button"
                          class="reservation-tab-trigger <?php echo $dashboardObligationTab === 'dashboard-obligations-future' ? 'is-active' : ''; ?>"
                          data-tab-target="dashboard-obligations-<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>-future"
                          data-obligation-tab-value="dashboard-obligations-future"
                        >Futuras (<?php echo $typeFutureCount; ?>)</button>
	                  </div>
	                  <div class="reservation-tab-panel <?php echo $dashboardObligationTab === 'dashboard-obligations-past' ? 'is-active' : ''; ?>" id="dashboard-obligations-<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>-past" data-tab-panel>
	                    <?php pms_dashboard_render_obligation_list(
	                        isset($typeBuckets['past']) ? $typeBuckets['past'] : array(),
	                        'Sin obligaciones pendientes de fechas pasadas.',
	                        $dashboardDateInput,
	                        $availabilityPropertyCode,
	                        $dashboardPeriod,
	                        'dashboard-activities-obligations',
	                        'dashboard-obligations-past',
                            $typeKey,
                            $obligationPaymentMethods
	                    ); ?>
	                  </div>
	                  <div class="reservation-tab-panel <?php echo $dashboardObligationTab === 'dashboard-obligations-today' ? 'is-active' : ''; ?>" id="dashboard-obligations-<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>-today" data-tab-panel>
	                    <?php pms_dashboard_render_obligation_list(
	                        isset($typeBuckets['today']) ? $typeBuckets['today'] : array(),
	                        'Sin obligaciones pendientes para hoy.',
	                        $dashboardDateInput,
	                        $availabilityPropertyCode,
	                        $dashboardPeriod,
	                        'dashboard-activities-obligations',
	                        'dashboard-obligations-today',
                            $typeKey,
                            $obligationPaymentMethods
	                    ); ?>
	                  </div>
	                  <div class="reservation-tab-panel <?php echo $dashboardObligationTab === 'dashboard-obligations-future' ? 'is-active' : ''; ?>" id="dashboard-obligations-<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>-future" data-tab-panel>
	                    <?php pms_dashboard_render_obligation_list(
	                        isset($typeBuckets['future']) ? $typeBuckets['future'] : array(),
	                        'Sin obligaciones pendientes futuras.',
	                        $dashboardDateInput,
	                        $availabilityPropertyCode,
	                        $dashboardPeriod,
	                        'dashboard-activities-obligations',
	                        'dashboard-obligations-future',
                            $typeKey,
                            $obligationPaymentMethods
	                    ); ?>
	                  </div>
	                </div>
                  </div>
                <?php endforeach; ?>
              </div>
	        </div>
	      </div>
      <div class="dashboard-availability-inline is-collapsed" data-availability-collapsible>
        <div class="dashboard-availability-header">
          <div>
            <h4>Disponibilidad rapida</h4>
            <p class="muted">Habitaciones libres desde <?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?> y noches continuas disponibles.</p>
          </div>
          <?php
            $availabilitySelected = null;
            $availabilityRooms = array();
            if ($availabilityPropertyCode !== '' && isset($availabilityByProperty[$availabilityPropertyCode])) {
                $availabilitySelected = $availabilityByProperty[$availabilityPropertyCode];
                $availabilityRooms = isset($availabilitySelected['rooms']) ? $availabilitySelected['rooms'] : array();
            }
            $availabilityRoomCount = count($availabilityRooms);
            $availabilityHeaderName = $availabilityPropertyName !== ''
                ? $availabilityPropertyName
                : ($availabilitySelected && isset($availabilitySelected['property_name']) ? (string)$availabilitySelected['property_name'] : '');
            $availabilityDisplayList = array();
            if ($isAllProperties) {
                foreach ($properties as $property) {
                    $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
                    if ($code === '') {
                        continue;
                    }
                    $rooms = isset($availabilityByProperty[$code]) ? $availabilityByProperty[$code]['rooms'] : array();
                    $availabilityDisplayList[] = array(
                        'property_code' => $code,
                        'property_name' => isset($property['name']) ? (string)$property['name'] : '',
                        'rooms' => $rooms
                    );
                }
            }
          ?>
          <div class="availability-toggle-wrap">
            <?php if (!$isAllProperties && $availabilityPropertyCode !== ''): ?>
              <span class="pill">Disponibles <?php echo $availabilityRoomCount; ?></span>
            <?php endif; ?>
            <button type="button" class="button-secondary availability-toggle" data-availability-toggle aria-expanded="false" aria-controls="dashboard-availability-body">Mostrar disponibilidad</button>
          </div>
        </div>
        <div class="dashboard-availability-body" id="dashboard-availability-body" data-availability-body>
          <?php if ($availabilityError): ?>
            <p class="error"><?php echo htmlspecialchars($availabilityError, ENT_QUOTES, 'UTF-8'); ?></p>
          <?php endif; ?>
          <?php if ($availabilityPropertyCode === ''): ?>
            <p class="muted">Selecciona una propiedad para ver disponibilidad.</p>
          <?php elseif ($isAllProperties): ?>
            <?php if (!$availabilityDisplayList): ?>
              <p class="muted">No hay propiedades disponibles para mostrar.</p>
            <?php else: ?>
              <div class="dashboard-property-list">
                <?php foreach ($availabilityDisplayList as $availabilityProperty): ?>
                  <?php
                    $propertyRooms = isset($availabilityProperty['rooms']) ? $availabilityProperty['rooms'] : array();
                    $propertyRoomCount = count($propertyRooms);
                    $propertyDisplayName = $availabilityProperty['property_name'] !== ''
                        ? $availabilityProperty['property_name']
                        : $availabilityProperty['property_code'];
                  ?>
                  <div class="availability-property">
                    <div class="availability-header">
                      <div>
                        <h3><?php echo htmlspecialchars($propertyDisplayName, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="muted"><?php echo htmlspecialchars($availabilityProperty['property_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                      </div>
                      <div class="pill-row">
                        <span class="pill">Disponibles <?php echo $propertyRoomCount; ?></span>
                      </div>
                    </div>
                    <?php if ($propertyRooms): ?>
                      <table class="compact-table availability-table">
                        <thead>
                          <tr>
                            <th>Habitacion</th>
                            <th>Noches libres</th>
                            <th>Proxima fecha</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($propertyRooms as $room): ?>
                            <tr>
                              <td><?php echo htmlspecialchars($room['room_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?php echo $room['available_nights'] === null ? 'Sin limite' : htmlspecialchars((string)$room['available_nights'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?php echo htmlspecialchars($room['next_date'] !== '' ? $room['next_date'] : 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php else: ?>
                      <p class="muted">No hay habitaciones disponibles en esta propiedad.</p>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="availability-property">
              <div class="availability-header">
                <div>
                  <h3><?php echo htmlspecialchars($availabilityHeaderName !== '' ? $availabilityHeaderName : $availabilityPropertyCode, ENT_QUOTES, 'UTF-8'); ?></h3>
                  <span class="muted"><?php echo htmlspecialchars($availabilityPropertyCode, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="pill-row">
                  <span class="pill">Disponibles <?php echo $availabilityRoomCount; ?></span>
                </div>
              </div>
              <?php if ($availabilityRooms): ?>
                <table class="compact-table availability-table">
                  <thead>
                    <tr>
                      <th>Habitacion</th>
                      <th>Noches libres</th>
                      <th>Proxima fecha</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($availabilityRooms as $room): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($room['room_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $room['available_nights'] === null ? 'Sin limite' : htmlspecialchars((string)$room['available_nights'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($room['next_date'] !== '' ? $room['next_date'] : 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <p class="muted">No hay habitaciones disponibles en esta propiedad.</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      </div>
    </div>
  <?php endif; ?>
  <div class="dashboard-arrivals-list">
    <h3>Proximas llegadas (7 dias)</h3>
    <?php if ($upcomingArrivals): ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Huesped</th>
              <th>Propiedad</th>
              <th>Codigo</th>
              <th>Noches</th>
              <th>Intereses</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($upcomingArrivals as $arrival):
              $nameParts = array();
              if (isset($arrival['names']) && $arrival['names'] !== '') {
                  $nameParts[] = $arrival['names'];
              }
              if (isset($arrival['last_name']) && $arrival['last_name'] !== '') {
                  $nameParts[] = $arrival['last_name'];
              }
              $name = trim(implode(' ', $nameParts));
              $checkIn = !empty($arrival['check_in_date']) ? new DateTime($arrival['check_in_date']) : null;
              $checkOut = !empty($arrival['check_out_date']) ? new DateTime($arrival['check_out_date']) : null;
              $nights = ($checkIn && $checkOut) ? max(1, (int)$checkIn->diff($checkOut)->days) : '';
              $interestRaw = isset($arrival['interest_list']) ? trim((string)$arrival['interest_list']) : '';
              $interestItems = $interestRaw !== '' ? array_filter(array_map('trim', explode(',', $interestRaw))) : array();
            ?>
              <tr>
                <td><?php echo htmlspecialchars($arrival['check_in_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($name !== '' ? $name : 'Sin huesped', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($arrival['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($arrival['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $nights; ?></td>
                <td>
                  <?php if ($interestItems): ?>
                    <div class="interest-tags is-compact">
                      <?php foreach ($interestItems as $interestLabel): ?>
                        <span class="interest-pill"><?php echo htmlspecialchars($interestLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span class="muted">Sin intereses</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="muted">Sin proximas llegadas registradas.</p>
    <?php endif; ?>
  </div>
</section>
