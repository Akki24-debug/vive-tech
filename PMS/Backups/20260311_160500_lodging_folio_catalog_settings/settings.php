<?php
$moduleKey = 'settings';
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
pms_require_permission('settings.view');

$properties = pms_fetch_properties($companyId);

if (!function_exists('settings_rate_column')) {
    function settings_rate_column(PDO $pdo)
    {
        return 'percent_value';
    }
}

if (!function_exists('settings_table_exists')) {
    function settings_table_exists(PDO $pdo, $tableName)
    {
        $tableName = trim((string)$tableName);
        if ($tableName === '') {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?'
            );
            $stmt->execute(array($tableName));
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('settings_catalog_fallback')) {
    function settings_catalog_fallback($companyId, $propertyCode = null)
    {
        $rows = array();
        try {
            $pdo = pms_get_connection();
            $rateCol = settings_rate_column($pdo);
            $sql = 'SELECT
                        lic.id_line_item_catalog AS id_sale_item_catalog,
                        lic.catalog_type,
                        lic.id_category,
                        cat.category_name AS category,
                        lic.item_name,
                        lic.description,
                        lic.default_unit_price_cents,
                        lic.is_percent,
                        lic.' . $rateCol . ' AS percent_value,
                        lic.show_in_folio,
                        lic.is_active,
                        prop.code AS property_code
                    FROM line_item_catalog lic
                    JOIN sale_item_category cat
                      ON cat.id_sale_item_category = lic.id_category
                     AND cat.id_company = ?
                     AND cat.deleted_at IS NULL
                    LEFT JOIN property prop ON prop.id_property = cat.id_property
                    WHERE lic.deleted_at IS NULL
                      AND lic.is_active = 1
                      AND cat.is_active = 1
                      AND lic.catalog_type IN (\'sale_item\',\'payment\',\'obligation\',\'income\',\'tax_rule\')
                      AND (? IS NULL OR ? = \'\' OR prop.code IS NULL OR prop.code = ?)
                    ORDER BY cat.category_name, lic.item_name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array((int)$companyId, $propertyCode, $propertyCode, $propertyCode));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $rows = array();
        }
        return $rows;
    }
}

if (!function_exists('settings_timezone_options')) {
    function settings_timezone_options()
    {
        return array(
            'America/Mexico_City',
            'America/Monterrey',
            'America/Chihuahua',
            'America/Hermosillo',
            'America/Cancun',
            'America/Tijuana',
            'UTC'
        );
    }
}

if (!function_exists('settings_parse_csv_ids')) {
    function settings_parse_csv_ids($csv)
    {
        $raw = trim((string)$csv);
        if ($raw === '') {
            return array();
        }
        $ids = array_values(array_filter(array_map('intval', explode(',', $raw)), function ($id) {
            return $id > 0;
        }));
        $dedupe = array();
        foreach ($ids as $id) {
            $dedupe[(int)$id] = true;
        }
        return array_map('intval', array_keys($dedupe));
    }
}

if (!function_exists('settings_rebuild_folio_from_roots')) {
    function settings_rebuild_folio_from_roots($folioId, $reservationId, $actorUserId)
    {
        $folioId = (int)$folioId;
        $reservationId = (int)$reservationId;
        $actorUserId = $actorUserId !== null ? (int)$actorUserId : null;
        if ($folioId <= 0 || $reservationId <= 0) {
            throw new Exception('Folio o reservacion invalida para reconstruccion.');
        }

        $pdo = pms_get_connection();
        $stmtRoots = $pdo->prepare(
            'SELECT
                li.id_line_item,
                li.id_line_item_catalog,
                li.description,
                li.service_date,
                li.quantity,
                li.unit_price_cents,
                li.discount_amount_cents,
                li.status
             FROM line_item li
             WHERE li.id_folio = ?
               AND li.deleted_at IS NULL
               AND li.is_active = 1
               AND li.id_line_item_catalog IS NOT NULL
               AND (li.status IS NULL OR li.status NOT IN (\'void\',\'canceled\'))
               AND NOT EXISTS (
                    SELECT 1
                    FROM line_item_catalog_parent lcp
                    WHERE lcp.id_sale_item_catalog = li.id_line_item_catalog
                      AND lcp.deleted_at IS NULL
                      AND lcp.is_active = 1
               )
               AND (
                    COALESCE(li.description, \'\') = \'\'
                    OR INSTR(COALESCE(li.description, \'\'), \' / \') = 0
               )
               AND COALESCE(li.description, \'\') NOT LIKE \'[AUTO-DERIVED parent_line_item=%\'
             ORDER BY li.id_line_item ASC'
        );
        $stmtRoots->execute(array($folioId));
        $roots = $stmtRoots->fetchAll(PDO::FETCH_ASSOC);

        if (!$roots) {
            throw new Exception('No se encontraron conceptos raiz activos para reconstruir el folio #' . $folioId . '.');
        }

        $txStarted = false;
        if (!$pdo->inTransaction()) {
            $txStarted = (bool)$pdo->beginTransaction();
        }
        try {
            $stmtDelete = $pdo->prepare(
                'UPDATE line_item
                    SET is_active = 0,
                        status = \'void\',
                        deleted_at = NOW(),
                        updated_at = NOW()
                  WHERE id_folio = ?
                    AND deleted_at IS NULL
                    AND is_active = 1'
            );
            $stmtDelete->execute(array($folioId));

            $rootPairs = array();
            foreach ($roots as $root) {
                $catalogId = isset($root['id_line_item_catalog']) ? (int)$root['id_line_item_catalog'] : 0;
                if ($catalogId <= 0) {
                    continue;
                }
                $description = isset($root['description']) ? (string)$root['description'] : null;
                $serviceDate = isset($root['service_date']) && $root['service_date'] !== '' ? (string)$root['service_date'] : null;
                $quantity = isset($root['quantity']) ? (float)$root['quantity'] : 1;
                $unitPriceCents = isset($root['unit_price_cents']) ? (int)$root['unit_price_cents'] : 0;
                $discountCents = isset($root['discount_amount_cents']) ? (int)$root['discount_amount_cents'] : 0;
                $status = isset($root['status']) && trim((string)$root['status']) !== '' ? (string)$root['status'] : 'posted';

                pms_call_procedure('sp_sale_item_upsert', array(
                    'create',
                    0,
                    $folioId,
                    $reservationId,
                    $catalogId,
                    $description,
                    $serviceDate,
                    $quantity > 0 ? $quantity : 1,
                    $unitPriceCents,
                    $discountCents,
                    $status,
                    $actorUserId
                ));

                $pairKey = $catalogId . '|' . ($serviceDate !== null ? $serviceDate : 'NULL');
                $rootPairs[$pairKey] = array(
                    'catalog_id' => $catalogId,
                    'service_date' => $serviceDate
                );
            }

            foreach ($rootPairs as $pair) {
                pms_call_procedure('sp_line_item_percent_derived_upsert', array(
                    $folioId,
                    $reservationId,
                    (int)$pair['catalog_id'],
                    $pair['service_date'],
                    $actorUserId
                ));
            }

            pms_call_procedure('sp_folio_recalc', array($folioId));
            if ($txStarted && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($txStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
$filters = array(
    'property_code' => isset($_POST['settings_filter_property'])
        ? strtoupper((string)$_POST['settings_filter_property'])
        : (isset($_GET['settings_filter_property']) ? strtoupper((string)$_GET['settings_filter_property']) : '')
);
$settingsAction = isset($_POST['settings_action']) ? (string)$_POST['settings_action'] : '';
$settingsTab = isset($_POST['settings_tab'])
    ? strtolower(trim((string)$_POST['settings_tab']))
    : (isset($_GET['settings_tab']) ? strtolower(trim((string)$_GET['settings_tab'])) : 'general');
if (!in_array($settingsTab, array('general', 'payments', 'concepts'), true)) {
    $settingsTab = 'general';
}
if ($filters['property_code'] !== '') {
    pms_require_property_access($filters['property_code']);
}
if ($settingsAction !== '') {
    pms_require_permission('settings.edit');
}
$message = null;
$error = null;
$themeMessage = null;
$themeError = null;
$obligationPaymentMethods = array();
$incomePaymentMethods = array();

/* guardar config */
if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_lodging') {
    $propCode = isset($_POST['settings_filter_property']) ? strtoupper((string)$_POST['settings_filter_property']) : '';
    if ($propCode !== '') {
        pms_require_property_access($propCode);
    }
    $catalogIds = isset($_POST['setting_lodging_catalog_ids']) ? $_POST['setting_lodging_catalog_ids'] : array();
    if (!is_array($catalogIds)) {
        $catalogIds = array();
    }
    $catalogIds = array_values(array_filter(array_map('intval', $catalogIds), function ($id) {
        return $id > 0;
    }));
    $catalogCsv = $catalogIds ? implode(',', $catalogIds) : '';
    try {
        pms_call_procedure('sp_pms_settings_upsert', array(
            $companyCode,
            $propCode === '' ? null : $propCode,
            $catalogCsv !== '' ? $catalogCsv : '',
            null,
            null,
            null,
            $actorUserId
        ));
        $message = 'Configuracion guardada.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_interests') {
    $propCode = isset($_POST['settings_filter_property']) ? strtoupper((string)$_POST['settings_filter_property']) : '';
    if ($propCode !== '') {
        pms_require_property_access($propCode);
    }
    $catalogIds = isset($_POST['setting_interest_catalog_ids']) ? $_POST['setting_interest_catalog_ids'] : array();
    if (!is_array($catalogIds)) {
        $catalogIds = array();
    }
    $catalogIds = array_values(array_filter(array_map('intval', $catalogIds), function ($id) {
        return $id > 0;
    }));
    $catalogCsv = $catalogIds ? implode(',', $catalogIds) : '';
    try {
        pms_call_procedure('sp_pms_settings_upsert', array(
            $companyCode,
            $propCode === '' ? null : $propCode,
            null,
            $catalogCsv !== '' ? $catalogCsv : '',
            null,
            null,
            $actorUserId
        ));
        $message = 'Configuracion guardada.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_payment_concepts') {
    $propCode = isset($_POST['settings_filter_property']) ? strtoupper((string)$_POST['settings_filter_property']) : '';
    if ($propCode !== '') {
        pms_require_property_access($propCode);
    }
    $catalogIds = isset($_POST['setting_payment_catalog_ids']) ? $_POST['setting_payment_catalog_ids'] : array();
    if (!is_array($catalogIds)) {
        $catalogIds = array();
    }
    $catalogIds = array_values(array_filter(array_map('intval', $catalogIds), function ($id) {
        return $id > 0;
    }));
    $catalogCsv = $catalogIds ? implode(',', $catalogIds) : '';
    try {
        pms_call_procedure('sp_pms_settings_upsert', array(
            $companyCode,
            $propCode === '' ? null : $propCode,
            null,
            null,
            $catalogCsv !== '' ? $catalogCsv : '',
            null,
            $actorUserId
        ));
        $message = 'Conceptos de pago guardados.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_lodging_payment_blocks') {
    $propCode = isset($_POST['settings_filter_property']) ? strtoupper((string)$_POST['settings_filter_property']) : '';
    if ($propCode !== '') {
        pms_require_property_access($propCode);
    }
    $rawBlockMap = isset($_POST['setting_lodging_payment_block']) ? $_POST['setting_lodging_payment_block'] : array();
    if (!is_array($rawBlockMap)) {
        $rawBlockMap = array();
    }

    try {
        $pdoSettings = pms_get_connection();
        if (!settings_table_exists($pdoSettings, 'pms_settings_lodging_payment_block')) {
            throw new RuntimeException('Missing table pms_settings_lodging_payment_block. Run bd pms/migrate_lodging_payment_method_block.sql first.');
        }

        $scopePropertyId = null;
        if ($propCode !== '') {
            $stmtProperty = $pdoSettings->prepare(
                'SELECT id_property
                   FROM property
                  WHERE id_company = ?
                    AND UPPER(TRIM(code)) = ?
                    AND deleted_at IS NULL
                  LIMIT 1'
            );
            $stmtProperty->execute(array($companyId, $propCode));
            $scopePropertyId = (int)$stmtProperty->fetchColumn();
            if ($scopePropertyId <= 0) {
                throw new RuntimeException('Unknown property for payment blocking settings.');
            }
        }

        $setsScope = pms_call_procedure('sp_pms_settings_data', array(
            $companyCode,
            $propCode === '' ? null : $propCode
        ));
        $settingsRow = isset($setsScope[0][0]) ? $setsScope[0][0] : array();
        $allowedLodgingIds = settings_parse_csv_ids(isset($settingsRow['lodging_catalog_ids']) ? (string)$settingsRow['lodging_catalog_ids'] : '');
        $allowedPaymentIds = settings_parse_csv_ids(isset($settingsRow['payment_catalog_ids']) ? (string)$settingsRow['payment_catalog_ids'] : '');
        $allowedLodgingMap = array();
        foreach ($allowedLodgingIds as $tmpId) {
            $allowedLodgingMap[(int)$tmpId] = true;
        }
        $allowedPaymentMap = array();
        foreach ($allowedPaymentIds as $tmpId) {
            $allowedPaymentMap[(int)$tmpId] = true;
        }

        $pairsMap = array();
        foreach ($rawBlockMap as $lodgingCatalogRaw => $paymentCatalogRows) {
            $lodgingCatalogId = (int)$lodgingCatalogRaw;
            if ($lodgingCatalogId <= 0 || !isset($allowedLodgingMap[$lodgingCatalogId])) {
                continue;
            }
            if (!is_array($paymentCatalogRows)) {
                $paymentCatalogRows = array($paymentCatalogRows);
            }
            foreach ($paymentCatalogRows as $paymentCatalogRaw) {
                $paymentCatalogId = (int)$paymentCatalogRaw;
                if ($paymentCatalogId <= 0 || !isset($allowedPaymentMap[$paymentCatalogId])) {
                    continue;
                }
                $pairsMap[$lodgingCatalogId . ':' . $paymentCatalogId] = array(
                    'lodging' => $lodgingCatalogId,
                    'payment' => $paymentCatalogId
                );
            }
        }

        $startedTx = false;
        if (!$pdoSettings->inTransaction()) {
            $pdoSettings->beginTransaction();
            $startedTx = true;
        }

        if ($scopePropertyId > 0) {
            $stmtDeactivate = $pdoSettings->prepare(
                'UPDATE pms_settings_lodging_payment_block
                    SET is_active = 0,
                        deleted_at = NOW(),
                        updated_at = NOW(),
                        updated_by = ?
                  WHERE id_company = ?
                    AND id_property = ?
                    AND deleted_at IS NULL'
            );
            $stmtDeactivate->execute(array($actorUserId, $companyId, $scopePropertyId));
        } else {
            $stmtDeactivate = $pdoSettings->prepare(
                'UPDATE pms_settings_lodging_payment_block
                    SET is_active = 0,
                        deleted_at = NOW(),
                        updated_at = NOW(),
                        updated_by = ?
                  WHERE id_company = ?
                    AND id_property IS NULL
                    AND deleted_at IS NULL'
            );
            $stmtDeactivate->execute(array($actorUserId, $companyId));
        }

        if ($pairsMap) {
            $stmtUpsert = $pdoSettings->prepare(
                'INSERT INTO pms_settings_lodging_payment_block (
                    id_company,
                    id_property,
                    id_lodging_catalog,
                    id_payment_catalog,
                    is_active,
                    deleted_at,
                    created_at,
                    created_by,
                    updated_at,
                    updated_by
                ) VALUES (
                    ?, ?, ?, ?, 1, NULL, NOW(), ?, NOW(), ?
                )
                ON DUPLICATE KEY UPDATE
                    is_active = 1,
                    deleted_at = NULL,
                    updated_at = NOW(),
                    updated_by = VALUES(updated_by)'
            );
            $scopePropertyParam = $scopePropertyId > 0 ? $scopePropertyId : null;
            foreach ($pairsMap as $pair) {
                $stmtUpsert->execute(array(
                    $companyId,
                    $scopePropertyParam,
                    (int)$pair['lodging'],
                    (int)$pair['payment'],
                    $actorUserId,
                    $actorUserId
                ));
            }
        }

        if ($startedTx && $pdoSettings->inTransaction()) {
            $pdoSettings->commit();
        }
        $message = 'Bloqueos de metodos de pago guardados.';
    } catch (Exception $e) {
        if (isset($startedTx) && $startedTx && isset($pdoSettings) && $pdoSettings instanceof PDO && $pdoSettings->inTransaction()) {
            $pdoSettings->rollBack();
        }
        $error = $e->getMessage();
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_service_concepts') {
    $propCode = isset($_POST['settings_filter_property']) ? strtoupper((string)$_POST['settings_filter_property']) : '';
    if ($propCode !== '') {
        pms_require_property_access($propCode);
    }
    $catalogIds = isset($_POST['setting_service_catalog_ids']) ? $_POST['setting_service_catalog_ids'] : array();
    if (!is_array($catalogIds)) {
        $catalogIds = array();
    }
    $catalogIds = array_values(array_filter(array_map('intval', $catalogIds), function ($id) {
        return $id > 0;
    }));
    try {
        $pdoSettings = pms_get_connection();
        $startedTx = false;

        $stmtExists = $pdoSettings->prepare(
            'SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = \'pms_settings_service_catalog\''
        );
        $stmtExists->execute();
        if (((int)$stmtExists->fetchColumn()) <= 0) {
            throw new RuntimeException('Missing table pms_settings_service_catalog. Run table SQL first.');
        }

        $scopePropertyId = null;
        if ($propCode !== '') {
            $stmtProperty = $pdoSettings->prepare(
                'SELECT id_property
                   FROM property
                  WHERE id_company = ?
                    AND UPPER(TRIM(code)) = ?
                    AND deleted_at IS NULL
                  LIMIT 1'
            );
            $stmtProperty->execute(array($companyId, $propCode));
            $scopePropertyId = (int)$stmtProperty->fetchColumn();
            if ($scopePropertyId <= 0) {
                throw new RuntimeException('Unknown property for service settings.');
            }
        }

        if (!$pdoSettings->inTransaction()) {
            $pdoSettings->beginTransaction();
            $startedTx = true;
        }

        if ($scopePropertyId > 0) {
            $stmtDeactivate = $pdoSettings->prepare(
                'UPDATE pms_settings_service_catalog
                    SET is_active = 0,
                        deleted_at = NOW(),
                        updated_at = NOW()
                  WHERE id_company = ?
                    AND id_property = ?
                    AND deleted_at IS NULL'
            );
            $stmtDeactivate->execute(array($companyId, $scopePropertyId));
        } else {
            $stmtDeactivate = $pdoSettings->prepare(
                'UPDATE pms_settings_service_catalog
                    SET is_active = 0,
                        deleted_at = NOW(),
                        updated_at = NOW()
                  WHERE id_company = ?
                    AND id_property IS NULL
                    AND deleted_at IS NULL'
            );
            $stmtDeactivate->execute(array($companyId));
        }

        if (!empty($catalogIds)) {
            $catalogCsv = implode(',', $catalogIds);
            $stmtInsert = $pdoSettings->prepare(
                'INSERT INTO pms_settings_service_catalog (
                    id_company,
                    id_property,
                    id_sale_item_catalog,
                    is_active,
                    deleted_at,
                    created_at,
                    created_by,
                    updated_at
                )
                SELECT
                    ?,
                    ?,
                    lic.id_line_item_catalog,
                    1,
                    NULL,
                    NOW(),
                    ?,
                    NOW()
                FROM line_item_catalog lic
                JOIN sale_item_category cat
                  ON cat.id_sale_item_category = lic.id_category
                WHERE cat.id_company = ?
                  AND cat.deleted_at IS NULL
                  AND COALESCE(cat.is_active, 1) = 1
                  AND lic.deleted_at IS NULL
                  AND COALESCE(lic.is_active, 1) = 1
                  AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) = \'sale_item\'
                  AND (? IS NULL OR cat.id_property IS NULL OR cat.id_property = ?)
                  AND FIND_IN_SET(lic.id_line_item_catalog, ?)
                ON DUPLICATE KEY UPDATE
                  is_active = 1,
                  deleted_at = NULL,
                  updated_at = NOW()'
            );
            $propertyParam = $scopePropertyId > 0 ? $scopePropertyId : null;
            $stmtInsert->execute(array(
                $companyId,
                $propertyParam,
                $actorUserId,
                $companyId,
                $propertyParam,
                $propertyParam,
                $catalogCsv
            ));
        }

        if ($startedTx && $pdoSettings->inTransaction()) {
            $pdoSettings->commit();
        }
        $message = 'Conceptos de servicios guardados.';
    } catch (Exception $e) {
        if (isset($startedTx) && $startedTx && isset($pdoSettings) && $pdoSettings instanceof PDO && $pdoSettings->inTransaction()) {
            $pdoSettings->rollBack();
        }
        $error = $e->getMessage();
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_payment_method') {
    $propCode = isset($_POST['settings_filter_property']) ? strtoupper((string)$_POST['settings_filter_property']) : '';
    if ($propCode !== '') {
        pms_require_property_access($propCode);
    }
    $methodName = isset($_POST['settings_payment_method_name']) ? trim((string)$_POST['settings_payment_method_name']) : '';
    if ($methodName === '') {
        $error = 'Escribe el nombre del metodo de pago.';
    } else {
        try {
            pms_call_procedure('sp_pms_settings_payment_method_upsert', array(
                'create',
                0,
                $companyCode,
                $propCode === '' ? null : $propCode,
                $methodName,
                1,
                $actorUserId
            ));
            $message = 'Metodo de pago guardado.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'delete_payment_method') {
    $methodId = isset($_POST['settings_payment_method_id']) ? (int)$_POST['settings_payment_method_id'] : 0;
    if ($methodId <= 0) {
        $error = 'Selecciona un metodo de pago valido para eliminar.';
    } else {
        try {
            pms_call_procedure('sp_pms_settings_payment_method_upsert', array(
                'delete',
                $methodId,
                $companyCode,
                null,
                null,
                0,
                $actorUserId
            ));
            $message = 'Metodo de pago eliminado.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_reservation_source') {
    $sourceId = isset($_POST['settings_reservation_source_id']) ? (int)$_POST['settings_reservation_source_id'] : 0;
    $sourceName = isset($_POST['settings_reservation_source_name']) ? trim((string)$_POST['settings_reservation_source_name']) : '';
    $sourceCode = isset($_POST['settings_reservation_source_code']) ? trim((string)$_POST['settings_reservation_source_code']) : '';
    $sourceColorInput = isset($_POST['settings_reservation_source_color_hex']) ? trim((string)$_POST['settings_reservation_source_color_hex']) : '';
    $sourceLodgingCatalogId = isset($_POST['settings_reservation_source_lodging_catalog_id'])
        ? (int)$_POST['settings_reservation_source_lodging_catalog_id']
        : 0;
    if ($sourceLodgingCatalogId < 0) {
        $sourceLodgingCatalogId = 0;
    }
    $sourceNotes = isset($_POST['settings_reservation_source_notes']) ? trim((string)$_POST['settings_reservation_source_notes']) : '';
    $sourceInfoCatalogIdsRaw = isset($_POST['settings_reservation_source_info_catalog_id'])
        ? $_POST['settings_reservation_source_info_catalog_id']
        : array();
    $sourceInfoAliasRaw = isset($_POST['settings_reservation_source_info_alias'])
        ? $_POST['settings_reservation_source_info_alias']
        : array();
    if (!is_array($sourceInfoCatalogIdsRaw)) {
        $sourceInfoCatalogIdsRaw = array();
    }
    if (!is_array($sourceInfoAliasRaw)) {
        $sourceInfoAliasRaw = array();
    }
    $sourceInfoRows = array();
    $sourceInfoSeenCatalog = array();
    foreach ($sourceInfoCatalogIdsRaw as $idx => $catalogRaw) {
        $catalogId = (int)$catalogRaw;
        if ($catalogId <= 0 || isset($sourceInfoSeenCatalog[$catalogId])) {
            continue;
        }
        $alias = isset($sourceInfoAliasRaw[$idx]) ? trim((string)$sourceInfoAliasRaw[$idx]) : '';
        if (function_exists('mb_substr')) {
            $alias = mb_substr($alias, 0, 160);
        } else {
            $alias = substr($alias, 0, 160);
        }
        $sourceInfoRows[] = array(
            'catalog_id' => $catalogId,
            'alias' => $alias,
            'sort_order' => count($sourceInfoRows) + 1
        );
        $sourceInfoSeenCatalog[$catalogId] = true;
    }
    $scopePropertyCode = isset($_POST['settings_reservation_source_scope']) ? strtoupper(trim((string)$_POST['settings_reservation_source_scope'])) : '';
    $scopePropertyId = null;
    $sourceCode = function_exists('pms_reservation_source_normalize_code')
        ? pms_reservation_source_normalize_code($sourceCode, 12)
        : strtoupper(trim((string)$sourceCode));
    $sourceColorHex = function_exists('pms_reservation_source_normalize_color_hex')
        ? pms_reservation_source_normalize_color_hex($sourceColorInput)
        : strtoupper(trim((string)$sourceColorInput));
    $sourceCodeDb = $sourceCode === '' ? null : $sourceCode;
    $sourceColorDb = $sourceColorHex === '' ? null : $sourceColorHex;

    if ($sourceName === '') {
        $error = 'Escribe el nombre del origen.';
    } elseif ($sourceColorInput !== '' && $sourceColorHex === '') {
        $error = 'Color invalido. Usa formato #RRGGBB.';
    } else {
        if ($scopePropertyCode !== '') {
            pms_require_property_access($scopePropertyCode);
            $scopePropertyId = pms_lookup_property_id_for_company($companyId, $scopePropertyCode);
            if ($scopePropertyId === null || $scopePropertyId <= 0) {
                $error = 'La propiedad seleccionada para el origen no es valida.';
            }
        }
    }

    if ($error === null) {
        try {
            $pdo = pms_get_connection();
            $hasSourceCodeColumn = function_exists('pms_reservation_source_has_column')
                ? pms_reservation_source_has_column($pdo, 'source_code')
                : false;
            $hasColorHexColumn = function_exists('pms_reservation_source_has_column')
                ? pms_reservation_source_has_column($pdo, 'color_hex')
                : false;
            $hasLodgingCatalogColumn = function_exists('pms_reservation_source_has_column')
                ? pms_reservation_source_has_column($pdo, 'id_lodging_catalog')
                : false;
            $hasSourceInfoCatalogTable = settings_table_exists($pdo, 'reservation_source_info_catalog');
            if (!$hasSourceCodeColumn || !$hasColorHexColumn) {
                throw new Exception('Falta migracion de origenes visuales. Ejecuta bd pms/migrate_reservation_source_visuals.sql');
            }
            if ($sourceLodgingCatalogId > 0 && !$hasLodgingCatalogColumn) {
                throw new Exception('Falta migracion de concepto de hospedaje por origen. Ejecuta bd pms/migrate_reservation_source_lodging_catalog.sql');
            }
            if ($sourceInfoRows && !$hasSourceInfoCatalogTable) {
                throw new Exception('Falta migracion de conceptos informativos por origen. Ejecuta bd pms/migrate_reservation_source_info_catalog.sql');
            }
            if ($sourceLodgingCatalogId > 0) {
                $stmtLodgingCatalog = $pdo->prepare(
                    'SELECT lic.id_line_item_catalog
                       FROM line_item_catalog lic
                       JOIN sale_item_category cat
                         ON cat.id_sale_item_category = lic.id_category
                        AND cat.id_company = ?
                        AND cat.deleted_at IS NULL
                        AND COALESCE(cat.is_active, 1) = 1
                      WHERE lic.id_line_item_catalog = ?
                        AND lic.deleted_at IS NULL
                        AND COALESCE(lic.is_active, 1) = 1
                        AND LOWER(TRIM(COALESCE(lic.catalog_type, \'\'))) = \'sale_item\'
                      LIMIT 1'
                );
                $stmtLodgingCatalog->execute(array($companyId, $sourceLodgingCatalogId));
                if ((int)$stmtLodgingCatalog->fetchColumn() <= 0) {
                    throw new Exception('Selecciona un concepto de hospedaje valido para el origen.');
                }
            }
            if ($sourceInfoRows) {
                $sourceInfoCatalogIds = array_values(array_unique(array_map(function ($row) {
                    return isset($row['catalog_id']) ? (int)$row['catalog_id'] : 0;
                }, $sourceInfoRows)));
                $sourceInfoCatalogIds = array_values(array_filter($sourceInfoCatalogIds, function ($id) {
                    return (int)$id > 0;
                }));
                if ($sourceInfoCatalogIds) {
                    $placeholdersInfo = implode(',', array_fill(0, count($sourceInfoCatalogIds), '?'));
                    $paramsInfo = array_merge(array($companyId), $sourceInfoCatalogIds);
                    $stmtInfoCatalogs = $pdo->prepare(
                        'SELECT lic.id_line_item_catalog
                           FROM line_item_catalog lic
                           JOIN sale_item_category cat
                             ON cat.id_sale_item_category = lic.id_category
                            AND cat.id_company = ?
                            AND cat.deleted_at IS NULL
                            AND COALESCE(cat.is_active, 1) = 1
                          WHERE lic.deleted_at IS NULL
                            AND COALESCE(lic.is_active, 1) = 1
                            AND lic.id_line_item_catalog IN (' . $placeholdersInfo . ')'
                    );
                    $stmtInfoCatalogs->execute($paramsInfo);
                    $validInfoCatalogs = array();
                    foreach ($stmtInfoCatalogs->fetchAll(PDO::FETCH_COLUMN) as $validCatalogId) {
                        $validInfoCatalogs[(int)$validCatalogId] = true;
                    }
                    foreach ($sourceInfoRows as $sourceInfoRow) {
                        $catalogId = isset($sourceInfoRow['catalog_id']) ? (int)$sourceInfoRow['catalog_id'] : 0;
                        if ($catalogId > 0 && !isset($validInfoCatalogs[$catalogId])) {
                            throw new Exception('Selecciona conceptos validos para el cuadro informativo del origen.');
                        }
                    }
                }
            }
            $stmtDuplicate = $pdo->prepare(
                'SELECT rsc.id_reservation_source
                   FROM reservation_source_catalog rsc
                  WHERE rsc.id_company = ?
                    AND (rsc.id_property <=> ?)
                    AND rsc.deleted_at IS NULL
                    AND COALESCE(rsc.is_active, 1) = 1
                    AND LOWER(TRIM(COALESCE(rsc.source_name, \'\'))) = LOWER(TRIM(?))
                    AND (? <= 0 OR rsc.id_reservation_source <> ?)
                  ORDER BY rsc.id_reservation_source
                  LIMIT 1'
            );
            $stmtDuplicate->execute(array(
                $companyId,
                $scopePropertyId,
                $sourceName,
                $sourceId,
                $sourceId
            ));
            $duplicateSourceId = (int)$stmtDuplicate->fetchColumn();

            if ($duplicateSourceId > 0 && $sourceId > 0 && $duplicateSourceId !== $sourceId) {
                $error = 'Ya existe otro origen con ese nombre en el mismo alcance.';
            } else {
                if ($sourceId <= 0 && $duplicateSourceId > 0) {
                    $sourceId = $duplicateSourceId;
                }
                $savedSourceId = $sourceId;

                if ($sourceId > 0) {
                    if ($hasLodgingCatalogColumn) {
                        $stmt = $pdo->prepare(
                            'UPDATE reservation_source_catalog
                                SET id_property = ?,
                                    source_name = ?,
                                    source_code = ?,
                                    color_hex = ?,
                                    notes = ?,
                                    id_lodging_catalog = ?,
                                    is_active = 1,
                                    deleted_at = NULL,
                                    updated_at = NOW(),
                                    updated_by = ?
                              WHERE id_reservation_source = ?
                                AND id_company = ?'
                        );
                        $stmt->execute(array(
                            $scopePropertyId,
                            $sourceName,
                            $sourceCodeDb,
                            $sourceColorDb,
                            $sourceNotes === '' ? null : $sourceNotes,
                            $sourceLodgingCatalogId > 0 ? $sourceLodgingCatalogId : null,
                            $actorUserId,
                            $sourceId,
                            $companyId
                        ));
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE reservation_source_catalog
                                SET id_property = ?,
                                    source_name = ?,
                                    source_code = ?,
                                    color_hex = ?,
                                    notes = ?,
                                    is_active = 1,
                                    deleted_at = NULL,
                                    updated_at = NOW(),
                                    updated_by = ?
                              WHERE id_reservation_source = ?
                                AND id_company = ?'
                        );
                        $stmt->execute(array(
                            $scopePropertyId,
                            $sourceName,
                            $sourceCodeDb,
                            $sourceColorDb,
                            $sourceNotes === '' ? null : $sourceNotes,
                            $actorUserId,
                            $sourceId,
                            $companyId
                        ));
                    }
                    if ((int)$stmt->rowCount() <= 0) {
                        $error = 'No se encontro el origen para actualizar.';
                    } else {
                        $message = 'Origen actualizado.';
                        $savedSourceId = $sourceId;
                    }
                } else {
                    if ($hasLodgingCatalogColumn) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO reservation_source_catalog (
                                id_company,
                                id_property,
                                source_name,
                                source_code,
                                color_hex,
                                notes,
                                id_lodging_catalog,
                                is_active,
                                deleted_at,
                                created_at,
                                created_by,
                                updated_at,
                                updated_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NULL, NOW(), ?, NOW(), ?)'
                        );
                        $stmt->execute(array(
                            $companyId,
                            $scopePropertyId,
                            $sourceName,
                            $sourceCodeDb,
                            $sourceColorDb,
                            $sourceNotes === '' ? null : $sourceNotes,
                            $sourceLodgingCatalogId > 0 ? $sourceLodgingCatalogId : null,
                            $actorUserId,
                            $actorUserId
                        ));
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO reservation_source_catalog (
                                id_company,
                                id_property,
                                source_name,
                                source_code,
                                color_hex,
                                notes,
                                is_active,
                                deleted_at,
                                created_at,
                                created_by,
                                updated_at,
                                updated_by
                            ) VALUES (?, ?, ?, ?, ?, ?, 1, NULL, NOW(), ?, NOW(), ?)'
                        );
                        $stmt->execute(array(
                            $companyId,
                            $scopePropertyId,
                            $sourceName,
                            $sourceCodeDb,
                            $sourceColorDb,
                            $sourceNotes === '' ? null : $sourceNotes,
                            $actorUserId,
                            $actorUserId
                        ));
                    }
                    $savedSourceId = (int)$pdo->lastInsertId();
                    $message = 'Origen guardado.';
                }

                if ($error === null && $hasSourceInfoCatalogTable && $savedSourceId > 0) {
                    $stmtDeactivateInfo = $pdo->prepare(
                        'UPDATE reservation_source_info_catalog
                            SET is_active = 0,
                                deleted_at = NOW(),
                                updated_at = NOW()
                          WHERE id_reservation_source = ?
                            AND deleted_at IS NULL'
                    );
                    $stmtDeactivateInfo->execute(array($savedSourceId));

                    if ($sourceInfoRows) {
                        $stmtUpsertInfo = $pdo->prepare(
                            'UPDATE reservation_source_info_catalog
                                SET sort_order = ?,
                                    display_alias = ?,
                                    is_active = 1,
                                    deleted_at = NULL,
                                    updated_at = NOW()
                              WHERE id_reservation_source = ?
                                AND id_line_item_catalog = ?'
                        );
                        $stmtInsertInfo = $pdo->prepare(
                            'INSERT INTO reservation_source_info_catalog (
                                id_reservation_source,
                                id_line_item_catalog,
                                display_alias,
                                sort_order,
                                is_active,
                                deleted_at
                             )
                             SELECT
                                ?,
                                lic.id_line_item_catalog,
                                ?,
                                ?,
                                1,
                                NULL
                             FROM line_item_catalog lic
                             JOIN sale_item_category cat
                               ON cat.id_sale_item_category = lic.id_category
                              AND cat.id_company = ?
                              AND cat.deleted_at IS NULL
                              AND COALESCE(cat.is_active, 1) = 1
                             WHERE lic.id_line_item_catalog = ?
                               AND lic.deleted_at IS NULL
                               AND COALESCE(lic.is_active, 1) = 1
                             LIMIT 1'
                        );
                        foreach ($sourceInfoRows as $sourceInfoRow) {
                            $catalogId = isset($sourceInfoRow['catalog_id']) ? (int)$sourceInfoRow['catalog_id'] : 0;
                            if ($catalogId <= 0) {
                                continue;
                            }
                            $sortOrder = isset($sourceInfoRow['sort_order']) ? (int)$sourceInfoRow['sort_order'] : 0;
                            $alias = isset($sourceInfoRow['alias']) ? (string)$sourceInfoRow['alias'] : '';
                            $aliasDb = $alias !== '' ? $alias : null;
                            $stmtUpsertInfo->execute(array(
                                $sortOrder,
                                $aliasDb,
                                $savedSourceId,
                                $catalogId
                            ));
                            if ($stmtUpsertInfo->rowCount() <= 0) {
                                $stmtInsertInfo->execute(array(
                                    $savedSourceId,
                                    $aliasDb,
                                    $sortOrder,
                                    $companyId,
                                    $catalogId
                                ));
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'No se pudo guardar el origen: ' . $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'delete_reservation_source') {
    $sourceId = isset($_POST['settings_reservation_source_id']) ? (int)$_POST['settings_reservation_source_id'] : 0;
    if ($sourceId <= 0) {
        $error = 'Selecciona un origen valido para eliminar.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE reservation_source_catalog
                    SET is_active = 0,
                        deleted_at = NOW(),
                        updated_at = NOW(),
                        updated_by = ?
                  WHERE id_reservation_source = ?
                    AND id_company = ?
                    AND deleted_at IS NULL'
            );
            $stmt->execute(array($actorUserId, $sourceId, $companyId));
            if ((int)$stmt->rowCount() <= 0) {
                $error = 'No se encontro el origen para eliminar.';
            } else {
                if (settings_table_exists($pdo, 'reservation_source_info_catalog')) {
                    $stmtInfoDelete = $pdo->prepare(
                        'UPDATE reservation_source_info_catalog
                            SET is_active = 0,
                                deleted_at = NOW(),
                                updated_at = NOW()
                          WHERE id_reservation_source = ?
                            AND deleted_at IS NULL'
                    );
                    $stmtInfoDelete->execute(array($sourceId));
                }
                $message = 'Origen eliminado.';
            }
        } catch (Exception $e) {
            $error = 'No se pudo eliminar el origen: ' . $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_obligation_payment_method') {
    $methodName = isset($_POST['settings_obligation_payment_method_name']) ? trim((string)$_POST['settings_obligation_payment_method_name']) : '';
    $methodDescription = isset($_POST['settings_obligation_payment_method_description']) ? trim((string)$_POST['settings_obligation_payment_method_description']) : '';
    $methodDescriptionDb = ($methodDescription === '') ? null : $methodDescription;
    if ($methodName === '') {
        $error = 'Escribe el nombre del metodo de pago de obligaciones.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmtFind = $pdo->prepare(
                'SELECT id_obligation_payment_method
                 FROM pms_settings_obligation_payment_method
                 WHERE id_company = ?
                   AND deleted_at IS NULL
                   AND method_name = ?
                   AND COALESCE(method_description, \'\') NOT LIKE \'[scope:income]%%\'
                 LIMIT 1'
            );
            $stmtFind->execute(array($companyId, $methodName));
            $existingMethodId = (int)$stmtFind->fetchColumn();

            if ($existingMethodId > 0) {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE pms_settings_obligation_payment_method
                        SET method_name = ?,
                            method_description = ?,
                            is_active = 1,
                            updated_at = NOW(),
                            updated_by = ?
                      WHERE id_obligation_payment_method = ?'
                );
                $stmtUpdate->execute(array($methodName, $methodDescriptionDb, $actorUserId, $existingMethodId));
            } else {
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO pms_settings_obligation_payment_method (
                        id_company,
                        method_name,
                        method_description,
                        is_active,
                        deleted_at,
                        created_at,
                        created_by,
                        updated_at,
                        updated_by
                    ) VALUES (?, ?, ?, 1, NULL, NOW(), ?, NOW(), ?)'
                );
                $stmtInsert->execute(array($companyId, $methodName, $methodDescriptionDb, $actorUserId, $actorUserId));
            }
            $message = 'Metodo de pago de obligaciones guardado.';
        } catch (Exception $e) {
            $error = 'No se pudo guardar el metodo de pago de obligaciones: ' . $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'delete_obligation_payment_method') {
    $methodId = isset($_POST['settings_obligation_payment_method_id']) ? (int)$_POST['settings_obligation_payment_method_id'] : 0;
    if ($methodId <= 0) {
        $error = 'Selecciona un metodo de pago de obligaciones valido para eliminar.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmtDelete = $pdo->prepare(
                'UPDATE pms_settings_obligation_payment_method
                    SET is_active = 0,
                        deleted_at = NOW(),
                        updated_at = NOW(),
                        updated_by = ?
                  WHERE id_obligation_payment_method = ?
                    AND id_company = ?
                    AND COALESCE(method_description, \'\') NOT LIKE \'[scope:income]%%\'
                    AND deleted_at IS NULL'
            );
            $stmtDelete->execute(array($actorUserId, $methodId, $companyId));
            $message = 'Metodo de pago de obligaciones eliminado.';
        } catch (Exception $e) {
            $error = 'No se pudo eliminar el metodo de pago de obligaciones: ' . $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_income_payment_method') {
    $methodName = isset($_POST['settings_income_payment_method_name']) ? trim((string)$_POST['settings_income_payment_method_name']) : '';
    $methodDescription = isset($_POST['settings_income_payment_method_description']) ? trim((string)$_POST['settings_income_payment_method_description']) : '';
    $descPayload = '[scope:income]';
    if ($methodDescription !== '') {
        $descPayload .= ' ' . $methodDescription;
    }
    if ($methodName === '') {
        $error = 'Escribe el nombre del metodo de pago de ingresos.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmtFind = $pdo->prepare(
                'SELECT id_obligation_payment_method
                 FROM pms_settings_obligation_payment_method
                 WHERE id_company = ?
                   AND deleted_at IS NULL
                   AND method_name = ?
                   AND COALESCE(method_description, \'\') LIKE \'[scope:income]%%\'
                 LIMIT 1'
            );
            $stmtFind->execute(array($companyId, $methodName));
            $existingMethodId = (int)$stmtFind->fetchColumn();

            if ($existingMethodId > 0) {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE pms_settings_obligation_payment_method
                        SET method_name = ?,
                            method_description = ?,
                            is_active = 1,
                            deleted_at = NULL,
                            updated_at = NOW(),
                            updated_by = ?
                      WHERE id_obligation_payment_method = ?'
                );
                $stmtUpdate->execute(array($methodName, $descPayload, $actorUserId, $existingMethodId));
            } else {
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO pms_settings_obligation_payment_method (
                        id_company,
                        method_name,
                        method_description,
                        is_active,
                        deleted_at,
                        created_at,
                        created_by,
                        updated_at,
                        updated_by
                    ) VALUES (?, ?, ?, 1, NULL, NOW(), ?, NOW(), ?)'
                );
                $stmtInsert->execute(array($companyId, $methodName, $descPayload, $actorUserId, $actorUserId));
            }
            $message = 'Metodo de pago de ingresos guardado.';
        } catch (Exception $e) {
            $error = 'No se pudo guardar el metodo de pago de ingresos: ' . $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'delete_income_payment_method') {
    $methodId = isset($_POST['settings_income_payment_method_id']) ? (int)$_POST['settings_income_payment_method_id'] : 0;
    if ($methodId <= 0) {
        $error = 'Selecciona un metodo de pago de ingresos valido para eliminar.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmtDelete = $pdo->prepare(
                'UPDATE pms_settings_obligation_payment_method
                    SET is_active = 0,
                        deleted_at = NOW(),
                        updated_at = NOW(),
                        updated_by = ?
                  WHERE id_obligation_payment_method = ?
                    AND id_company = ?
                    AND COALESCE(method_description, \'\') LIKE \'[scope:income]%%\'
                    AND deleted_at IS NULL'
            );
            $stmtDelete->execute(array($actorUserId, $methodId, $companyId));
            $message = 'Metodo de pago de ingresos eliminado.';
        } catch (Exception $e) {
            $error = 'No se pudo eliminar el metodo de pago de ingresos: ' . $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_theme') {
    $themeCode = isset($_POST['settings_theme_code']) ? strtolower(trim((string)$_POST['settings_theme_code'])) : 'default';
    try {
        pms_save_user_theme(
            pms_get_connection(),
            $companyCode,
            isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : 0,
            $themeCode,
            $actorUserId
        );
        $themeMessage = 'Tema guardado.';
    } catch (Exception $e) {
        $themeError = $e->getMessage();
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'save_timezone') {
    $timezoneInput = isset($_POST['settings_timezone']) ? trim((string)$_POST['settings_timezone']) : '';
    if (!function_exists('pms_timezone_is_valid') || !pms_timezone_is_valid($timezoneInput)) {
        $error = 'Zona horaria invalida. Usa formato IANA, por ejemplo: America/Mexico_City.';
    } else {
        try {
            $pdo = pms_get_connection();
            $stmtUpdate = $pdo->prepare(
                'UPDATE pms_settings
                    SET timezone = ?,
                        updated_at = NOW()
                  WHERE id_company = ?
                    AND id_property IS NULL'
            );
            $stmtUpdate->execute(array($timezoneInput, $companyId));

            if ((int)$stmtUpdate->rowCount() === 0) {
                $stmtInsert = $pdo->prepare(
                    'INSERT INTO pms_settings (
                        id_company,
                        id_property,
                        timezone,
                        created_at,
                        created_by,
                        updated_at
                    ) VALUES (?, NULL, ?, NOW(), ?, NOW())'
                );
                $stmtInsert->execute(array($companyId, $timezoneInput, $actorUserId));
            }

            if (function_exists('pms_apply_runtime_timezone')) {
                pms_apply_runtime_timezone($pdo, $timezoneInput);
            }
            if (isset($_SESSION['pms_user']) && is_array($_SESSION['pms_user'])) {
                $_SESSION['pms_user']['timezone'] = $timezoneInput;
            }
            $message = 'Zona horaria guardada y aplicada.';
        } catch (Exception $e) {
            $error = 'No se pudo guardar la zona horaria: ' . $e->getMessage();
        }
    }
}

if (isset($_POST['settings_action']) && $_POST['settings_action'] === 'recalc_folio_nodes') {
    $selectedReservationIds = isset($_POST['settings_recalc_reservation_ids']) ? $_POST['settings_recalc_reservation_ids'] : array();
    if (!is_array($selectedReservationIds)) {
        $selectedReservationIds = array();
    }
    $selectedReservationIds = array_values(array_unique(array_filter(array_map('intval', $selectedReservationIds), function ($id) {
        return $id > 0;
    })));

    if (!$selectedReservationIds) {
        $error = 'Selecciona al menos una reservacion.';
    } else {
        try {
            $pdo = pms_get_connection();
            $inReservations = implode(',', array_fill(0, count($selectedReservationIds), '?'));
            $paramsReservations = array_merge(array($companyId), $selectedReservationIds);

            $stmtValidReservations = $pdo->prepare(
                'SELECT r.id_reservation,
                        p.code AS property_code
                   FROM reservation r
                   JOIN property p ON p.id_property = r.id_property
                  WHERE p.id_company = ?
                    AND r.deleted_at IS NULL
                    AND r.id_reservation IN (' . $inReservations . ')'
            );
            $stmtValidReservations->execute($paramsReservations);
            $validReservationRows = $stmtValidReservations->fetchAll(PDO::FETCH_ASSOC);
            $validReservationIds = array();
            foreach ($validReservationRows as $validRow) {
                $propertyCode = strtoupper(trim((string)($validRow['property_code'] ?? '')));
                if ($propertyCode !== '') {
                    pms_require_property_access($propertyCode);
                }
                $reservationId = isset($validRow['id_reservation']) ? (int)$validRow['id_reservation'] : 0;
                if ($reservationId > 0) {
                    $validReservationIds[] = $reservationId;
                }
            }
            $validReservationIds = array_values(array_unique($validReservationIds));

            if (!$validReservationIds) {
                $error = 'Las reservaciones seleccionadas no existen o no pertenecen a tu empresa.';
            } else {
                $inValidReservations = implode(',', array_fill(0, count($validReservationIds), '?'));
                $stmtFolios = $pdo->prepare(
                    'SELECT f.id_folio, f.id_reservation
                       FROM folio f
                       JOIN reservation r ON r.id_reservation = f.id_reservation
                       JOIN property p ON p.id_property = r.id_property
                      WHERE p.id_company = ?
                        AND r.deleted_at IS NULL
                        AND f.deleted_at IS NULL
                        AND f.id_reservation IN (' . $inValidReservations . ')
                      ORDER BY f.id_folio DESC'
                );
                $stmtFolios->execute(array_merge(array($companyId), $validReservationIds));
                $folioRows = $stmtFolios->fetchAll(PDO::FETCH_ASSOC);
                $folioMap = array();
                foreach ($folioRows as $fr) {
                    $fId = isset($fr['id_folio']) ? (int)$fr['id_folio'] : 0;
                    $rId = isset($fr['id_reservation']) ? (int)$fr['id_reservation'] : 0;
                    if ($fId <= 0 || $rId <= 0) {
                        continue;
                    }
                    $folioMap[$fId] = $rId;
                }
                $folioIds = array_keys($folioMap);

                if (!$folioIds) {
                    $error = 'Las reservaciones seleccionadas no tienen folios activos.';
                } else {
                    $rebuildErrors = array();
                    $foliosRebuilt = 0;
                    foreach ($folioIds as $folioId) {
                        try {
                            settings_rebuild_folio_from_roots((int)$folioId, (int)$folioMap[$folioId], $actorUserId);
                            $foliosRebuilt++;
                        } catch (Exception $inner) {
                            $rebuildErrors[] = 'Folio #' . (int)$folioId . ': ' . $inner->getMessage();
                        }
                    }
                    if ($foliosRebuilt > 0) {
                        $message = 'Reconstruccion completada. Reservaciones: ' . count($validReservationIds) . ' | Folios reconstruidos: ' . $foliosRebuilt . '.';
                    }
                    if ($rebuildErrors) {
                        $error = 'Algunos folios fallaron: ' . implode(' | ', $rebuildErrors);
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'No se pudo recalcular los folios: ' . $e->getMessage();
        }
    }
}

$recentReservations = array();
try {
    $pdo = pms_get_connection();
    $stmtRecentReservations = $pdo->prepare(
        'SELECT
            r.id_reservation,
            r.code AS reservation_code,
            r.check_in_date,
            r.check_out_date,
            r.created_at,
            g.full_name AS guest_full_name,
            g.names AS guest_names,
            g.last_name AS guest_last_name,
            rm.code AS room_code,
            rm.name AS room_name,
            p.code AS property_code
         FROM reservation r
         JOIN property p ON p.id_property = r.id_property
         LEFT JOIN guest g ON g.id_guest = r.id_guest
         LEFT JOIN room rm ON rm.id_room = r.id_room
         WHERE p.id_company = ?
           AND r.deleted_at IS NULL
           AND COALESCE(LOWER(TRIM(r.status)), \'\') NOT IN (\'cancelada\', \'cancelled\', \'canceled\')
         ORDER BY COALESCE(r.created_at, r.updated_at) DESC, r.id_reservation DESC
         LIMIT 500'
    );
    $stmtRecentReservations->execute(array($companyId));
    $recentReservations = $stmtRecentReservations->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentReservations = array();
}

/* cargar config actual */
$currentSetting = null;
try {
    $sets = pms_call_procedure('sp_pms_settings_data', array(
        $companyCode,
        $filters['property_code'] === '' ? null : $filters['property_code']
    ));
    $currentSetting = isset($sets[0][0]) ? $sets[0][0] : null;
} catch (Exception $e) {
    $error = $error ?: $e->getMessage();
}

$currentSoftwareTimezone = 'America/Mexico_City';
try {
    if (function_exists('pms_fetch_company_timezone')) {
        $currentSoftwareTimezone = pms_fetch_company_timezone(pms_get_connection(), $companyId, $companyCode);
    } elseif (function_exists('pms_current_timezone')) {
        $currentSoftwareTimezone = pms_current_timezone();
    }
} catch (Exception $e) {
    $currentSoftwareTimezone = function_exists('pms_current_timezone') ? pms_current_timezone() : 'America/Mexico_City';
}
if (!function_exists('pms_timezone_is_valid') || !pms_timezone_is_valid($currentSoftwareTimezone)) {
    $currentSoftwareTimezone = 'America/Mexico_City';
}
$timezoneOptions = settings_timezone_options();
if (!in_array($currentSoftwareTimezone, $timezoneOptions, true)) {
    array_unshift($timezoneOptions, $currentSoftwareTimezone);
}

$currentTheme = 'default';
try {
    $currentTheme = pms_fetch_user_theme(
        pms_get_connection(),
        $companyCode,
        isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : 0
    );
} catch (Exception $e) {
    $currentTheme = 'default';
}
$currentTheme = pms_theme_normalize_code($currentTheme);

/* cargar conceptos (solo activos) */
$concepts = array();
try {
    $catSets = pms_call_procedure('sp_sale_item_catalog_data', array(
        $companyCode,
        $filters['property_code'] === '' ? null : $filters['property_code'],
        0,
        0,
        0
    ));
    $concepts = isset($catSets[0]) ? $catSets[0] : array();
} catch (Exception $e) {
    $concepts = array();
}
if (!$concepts) {
    $concepts = settings_catalog_fallback($companyId, $filters['property_code'] === '' ? null : $filters['property_code']);
}
$lodgingConcepts = array();
$interestConcepts = array();
$paymentConcepts = array();
$serviceConcepts = array();
foreach ($concepts as $c) {
    $ctype = strtolower(trim((string)(isset($c['catalog_type']) ? $c['catalog_type'] : '')));
    if ($ctype === 'sale_item') {
        $lodgingConcepts[] = $c;
        $interestConcepts[] = $c;
        $serviceConcepts[] = $c;
    } elseif ($ctype === 'payment' || $ctype === 'pago') {
        $paymentConcepts[] = $c;
    }
}
$lodgingConceptOptionRows = array();
foreach ($lodgingConcepts as $c) {
    $catalogId = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
    if ($catalogId <= 0) {
        continue;
    }
    $itemName = isset($c['item_name']) ? trim((string)$c['item_name']) : ('Concepto #' . $catalogId);
    $categoryName = isset($c['category']) ? trim((string)$c['category']) : '';
    $propertyCodeTmp = isset($c['property_code']) ? strtoupper(trim((string)$c['property_code'])) : '';
    $parts = array();
    if ($propertyCodeTmp !== '') {
        $parts[] = $propertyCodeTmp;
    }
    if ($categoryName !== '') {
        $parts[] = $categoryName;
    }
    $parts[] = $itemName;
    $lodgingConceptOptionRows[] = array(
        'id' => $catalogId,
        'label' => implode(' / ', $parts)
    );
}
$sourceInfoConceptOptionRows = array();
$sourceInfoConceptOptionMap = array();
try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            lic.id_line_item_catalog,
            lic.item_name,
            lic.catalog_type,
            cat.category_name AS category,
            COALESCE(prop.code, "") AS property_code
         FROM line_item_catalog lic
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.id_company = ?
          AND cat.deleted_at IS NULL
          AND COALESCE(cat.is_active, 1) = 1
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE lic.deleted_at IS NULL
           AND COALESCE(lic.is_active, 1) = 1
         ORDER BY cat.category_name, lic.item_name'
    );
    $stmt->execute(array($companyId));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $infoCatalogRow) {
        $catalogId = isset($infoCatalogRow['id_line_item_catalog']) ? (int)$infoCatalogRow['id_line_item_catalog'] : 0;
        if ($catalogId <= 0) {
            continue;
        }
        $itemName = isset($infoCatalogRow['item_name']) ? trim((string)$infoCatalogRow['item_name']) : ('Concepto #' . $catalogId);
        $categoryName = isset($infoCatalogRow['category']) ? trim((string)$infoCatalogRow['category']) : '';
        $propertyCodeTmp = isset($infoCatalogRow['property_code']) ? strtoupper(trim((string)$infoCatalogRow['property_code'])) : '';
        $catalogType = isset($infoCatalogRow['catalog_type']) ? strtoupper(trim((string)$infoCatalogRow['catalog_type'])) : '';
        $parts = array();
        if ($propertyCodeTmp !== '') {
            $parts[] = $propertyCodeTmp;
        }
        if ($categoryName !== '') {
            $parts[] = $categoryName;
        }
        $parts[] = $itemName;
        $label = implode(' / ', $parts);
        if ($catalogType !== '') {
            $label .= ' [' . $catalogType . ']';
        }
        $sourceInfoConceptOptionRows[] = array(
            'id' => $catalogId,
            'label' => $label
        );
        $sourceInfoConceptOptionMap[$catalogId] = $label;
    }
} catch (Exception $e) {
    $sourceInfoConceptOptionRows = array();
    $sourceInfoConceptOptionMap = array();
}

$selectedCatalogIds = array();
if ($currentSetting && isset($currentSetting['lodging_catalog_ids']) && $currentSetting['lodging_catalog_ids'] !== '') {
    $selectedCatalogIds = array_filter(array_map('intval', explode(',', (string)$currentSetting['lodging_catalog_ids'])));
}
$selectedCatalogMap = array();
foreach ($selectedCatalogIds as $sid) {
    $selectedCatalogMap[(int)$sid] = true;
}
$selectedInterestIds = array();
if ($currentSetting && isset($currentSetting['interest_catalog_ids']) && $currentSetting['interest_catalog_ids'] !== '') {
    $selectedInterestIds = array_filter(array_map('intval', explode(',', (string)$currentSetting['interest_catalog_ids'])));
}
$selectedInterestMap = array();
foreach ($selectedInterestIds as $sid) {
    $selectedInterestMap[(int)$sid] = true;
}
$selectedPaymentCatalogIds = array();
if ($currentSetting && isset($currentSetting['payment_catalog_ids']) && $currentSetting['payment_catalog_ids'] !== '') {
    $selectedPaymentCatalogIds = array_filter(array_map('intval', explode(',', (string)$currentSetting['payment_catalog_ids'])));
}
$selectedPaymentCatalogMap = array();
foreach ($selectedPaymentCatalogIds as $sid) {
    $selectedPaymentCatalogMap[(int)$sid] = true;
}
$selectedServiceCatalogIds = array();
$serviceCatalogIdsLoadedFromSp = false;
if ($currentSetting && array_key_exists('service_catalog_ids', $currentSetting)) {
    $serviceCatalogIdsLoadedFromSp = true;
    if ((string)$currentSetting['service_catalog_ids'] !== '') {
        $selectedServiceCatalogIds = array_filter(array_map('intval', explode(',', (string)$currentSetting['service_catalog_ids'])));
    }
}
if (!$serviceCatalogIdsLoadedFromSp) {
    try {
        $pdoFallback = pms_get_connection();
        $stmtExists = $pdoFallback->prepare(
            'SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = \'pms_settings_service_catalog\''
        );
        $stmtExists->execute();
        $hasServiceCatalogTable = ((int)$stmtExists->fetchColumn()) > 0;
        if ($hasServiceCatalogTable) {
            if ($filters['property_code'] !== '') {
                $stmtFallback = $pdoFallback->prepare(
                    'SELECT DISTINCT s.id_sale_item_catalog
                       FROM pms_settings_service_catalog s
                       JOIN property pr
                         ON pr.id_property = s.id_property
                        AND pr.deleted_at IS NULL
                      WHERE s.id_company = ?
                        AND pr.id_company = ?
                        AND UPPER(TRIM(pr.code)) = ?
                        AND s.deleted_at IS NULL
                        AND COALESCE(s.is_active, 1) = 1
                      ORDER BY s.id_sale_item_catalog'
                );
                $stmtFallback->execute(array($companyId, $companyId, strtoupper((string)$filters['property_code'])));
            } else {
                $stmtFallback = $pdoFallback->prepare(
                    'SELECT DISTINCT s.id_sale_item_catalog
                       FROM pms_settings_service_catalog s
                      WHERE s.id_company = ?
                        AND s.id_property IS NULL
                        AND s.deleted_at IS NULL
                        AND COALESCE(s.is_active, 1) = 1
                      ORDER BY s.id_sale_item_catalog'
                );
                $stmtFallback->execute(array($companyId));
            }
            $selectedServiceCatalogIds = array_values(array_filter(array_map('intval', $stmtFallback->fetchAll(PDO::FETCH_COLUMN)), function ($id) {
                return $id > 0;
            }));
        }
    } catch (Exception $e) {
    }
}
$selectedServiceCatalogMap = array();
foreach ($selectedServiceCatalogIds as $sid) {
    $selectedServiceCatalogMap[(int)$sid] = true;
}

$lodgingConceptOptionMapById = array();
foreach ($lodgingConcepts as $c) {
    $catalogId = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
    if ($catalogId <= 0) {
        continue;
    }
    $itemName = isset($c['item_name']) ? (string)$c['item_name'] : ('Concepto #' . $catalogId);
    $categoryName = isset($c['category']) ? (string)$c['category'] : '';
    $propertyCodeTmp = isset($c['property_code']) ? strtoupper((string)$c['property_code']) : '';
    $full = ($propertyCodeTmp !== '' ? $propertyCodeTmp . ' - ' : '') . ($categoryName !== '' ? $categoryName . ' / ' : '') . $itemName;
    $lodgingConceptOptionMapById[$catalogId] = $full;
}

$paymentConceptOptionMapById = array();
foreach ($paymentConcepts as $c) {
    $catalogId = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
    if ($catalogId <= 0) {
        continue;
    }
    $itemName = isset($c['item_name']) ? (string)$c['item_name'] : ('Concepto #' . $catalogId);
    $categoryName = isset($c['category']) ? (string)$c['category'] : '';
    $propertyCodeTmp = isset($c['property_code']) ? strtoupper((string)$c['property_code']) : '';
    $full = ($propertyCodeTmp !== '' ? $propertyCodeTmp . ' - ' : '') . ($categoryName !== '' ? $categoryName . ' / ' : '') . $itemName;
    $paymentConceptOptionMapById[$catalogId] = $full;
}

$selectedLodgingPaymentBlockMap = array();
try {
    $pdoLodgingPaymentBlock = pms_get_connection();
    if (settings_table_exists($pdoLodgingPaymentBlock, 'pms_settings_lodging_payment_block')) {
        $scopePropertyId = null;
        if ($filters['property_code'] !== '') {
            $stmtScopeProperty = $pdoLodgingPaymentBlock->prepare(
                'SELECT id_property
                   FROM property
                  WHERE id_company = ?
                    AND UPPER(TRIM(code)) = ?
                    AND deleted_at IS NULL
                  LIMIT 1'
            );
            $stmtScopeProperty->execute(array($companyId, strtoupper((string)$filters['property_code'])));
            $scopePropertyId = (int)$stmtScopeProperty->fetchColumn();
        }

        if ($scopePropertyId > 0) {
            $stmtBlocks = $pdoLodgingPaymentBlock->prepare(
                'SELECT
                    id_lodging_catalog,
                    id_payment_catalog
                 FROM pms_settings_lodging_payment_block
                 WHERE id_company = ?
                   AND id_property = ?
                   AND deleted_at IS NULL
                   AND COALESCE(is_active, 1) = 1'
            );
            $stmtBlocks->execute(array($companyId, $scopePropertyId));
        } else {
            $stmtBlocks = $pdoLodgingPaymentBlock->prepare(
                'SELECT
                    id_lodging_catalog,
                    id_payment_catalog
                 FROM pms_settings_lodging_payment_block
                 WHERE id_company = ?
                   AND id_property IS NULL
                   AND deleted_at IS NULL
                   AND COALESCE(is_active, 1) = 1'
            );
            $stmtBlocks->execute(array($companyId));
        }
        foreach ($stmtBlocks->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lodgingCatalogId = isset($row['id_lodging_catalog']) ? (int)$row['id_lodging_catalog'] : 0;
            $paymentCatalogId = isset($row['id_payment_catalog']) ? (int)$row['id_payment_catalog'] : 0;
            if ($lodgingCatalogId <= 0 || $paymentCatalogId <= 0) {
                continue;
            }
            if (!isset($selectedLodgingPaymentBlockMap[$lodgingCatalogId])) {
                $selectedLodgingPaymentBlockMap[$lodgingCatalogId] = array();
            }
            $selectedLodgingPaymentBlockMap[$lodgingCatalogId][$paymentCatalogId] = true;
        }
    }
} catch (Exception $e) {
    $selectedLodgingPaymentBlockMap = array();
}

$paymentMethods = array();
try {
    $pmSets = pms_call_procedure('sp_pms_settings_payment_method_data', array(
        $companyCode,
        $filters['property_code'] === '' ? null : $filters['property_code'],
        0
    ));
    $paymentMethods = isset($pmSets[0]) ? $pmSets[0] : array();
} catch (Exception $e) {
    try {
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT pm.id_payment_method, pm.id_company, pm.id_property, prop.code AS property_code, pm.method_name,
                    pm.is_active, pm.deleted_at, pm.created_at, pm.created_by, pm.updated_at
             FROM pms_settings_payment_method pm
             LEFT JOIN property prop ON prop.id_property = pm.id_property
             WHERE pm.id_company = ?
               AND pm.deleted_at IS NULL
               AND pm.is_active = 1
               AND (? IS NULL OR ? = \'\' OR pm.id_property IS NULL OR prop.code = ?)
             ORDER BY pm.method_name, pm.id_payment_method'
        );
        $fprop = $filters['property_code'] === '' ? null : $filters['property_code'];
        $stmt->execute(array($companyId, $fprop, $fprop, $fprop));
        $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $paymentMethods = array();
    }
}

$reservationSources = array();
try {
    if (function_exists('pms_fetch_reservation_sources')) {
        $reservationSources = pms_fetch_reservation_sources(
            $companyId,
            $filters['property_code'] === '' ? null : $filters['property_code'],
            false
        );
    } else {
        $pdo = pms_get_connection();
        $hasLodgingCatalogColumn = function_exists('pms_reservation_source_has_column')
            ? pms_reservation_source_has_column($pdo, 'id_lodging_catalog')
            : false;
        $lodgingCatalogSelectSql = $hasLodgingCatalogColumn
            ? 'COALESCE(rsc.id_lodging_catalog, 0) AS id_lodging_catalog,'
            : '0 AS id_lodging_catalog,';
        $stmt = $pdo->prepare(
            'SELECT
                rsc.id_reservation_source,
                rsc.id_property,
                prop.code AS property_code,
                rsc.source_name,
                ' . $lodgingCatalogSelectSql . '
                COALESCE(rsc.notes, \'\') AS notes,
                rsc.is_active,
                rsc.deleted_at
             FROM reservation_source_catalog rsc
             LEFT JOIN property prop
               ON prop.id_property = rsc.id_property
             WHERE rsc.id_company = ?
               AND rsc.deleted_at IS NULL
               AND rsc.is_active = 1
               AND (? IS NULL OR ? = \'\' OR rsc.id_property IS NULL OR prop.code = ?)
             ORDER BY
               CASE WHEN rsc.id_property IS NULL THEN 0 ELSE 1 END,
               prop.code,
               rsc.source_name'
        );
        $fprop = $filters['property_code'] === '' ? null : $filters['property_code'];
        $stmt->execute(array($companyId, $fprop, $fprop, $fprop));
        $reservationSources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $reservationSources = array();
    if ($error === null) {
        $error = 'No se pudieron cargar los origenes: ' . $e->getMessage();
    }
}
$reservationSourceInfoRowsBySource = array();
try {
    $pdoSourceInfo = pms_get_connection();
    if (settings_table_exists($pdoSourceInfo, 'reservation_source_info_catalog')) {
        $stmtSourceInfo = $pdoSourceInfo->prepare(
            'SELECT
                rsic.id_reservation_source,
                rsic.id_line_item_catalog,
                rsic.sort_order,
                rsic.display_alias
             FROM reservation_source_info_catalog rsic
             JOIN reservation_source_catalog rsc
               ON rsc.id_reservation_source = rsic.id_reservation_source
              AND rsc.id_company = ?
              AND rsc.deleted_at IS NULL
              AND COALESCE(rsc.is_active, 1) = 1
             WHERE rsic.deleted_at IS NULL
               AND COALESCE(rsic.is_active, 1) = 1
             ORDER BY rsic.id_reservation_source, rsic.sort_order, rsic.id_line_item_catalog'
        );
        $stmtSourceInfo->execute(array($companyId));
        foreach ($stmtSourceInfo->fetchAll(PDO::FETCH_ASSOC) as $sourceInfoRow) {
            $sourceIdTmp = isset($sourceInfoRow['id_reservation_source']) ? (int)$sourceInfoRow['id_reservation_source'] : 0;
            $catalogIdTmp = isset($sourceInfoRow['id_line_item_catalog']) ? (int)$sourceInfoRow['id_line_item_catalog'] : 0;
            if ($sourceIdTmp <= 0 || $catalogIdTmp <= 0) {
                continue;
            }
            if (!isset($reservationSourceInfoRowsBySource[$sourceIdTmp])) {
                $reservationSourceInfoRowsBySource[$sourceIdTmp] = array();
            }
            $reservationSourceInfoRowsBySource[$sourceIdTmp][] = array(
                'catalog_id' => $catalogIdTmp,
                'sort_order' => isset($sourceInfoRow['sort_order']) ? (int)$sourceInfoRow['sort_order'] : 0,
                'alias' => isset($sourceInfoRow['display_alias']) ? (string)$sourceInfoRow['display_alias'] : ''
            );
        }
    }
} catch (Exception $e) {
    $reservationSourceInfoRowsBySource = array();
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            m.id_obligation_payment_method,
            m.method_name,
            COALESCE(m.method_description, \'\') AS method_description,
            m.is_active,
            m.deleted_at,
            m.created_at,
            m.updated_at
         FROM pms_settings_obligation_payment_method m
         WHERE m.id_company = ?
           AND m.deleted_at IS NULL
           AND m.is_active = 1
           AND COALESCE(m.method_description, \'\') NOT LIKE \'[scope:income]%%\'
         ORDER BY m.method_name, m.id_obligation_payment_method'
    );
    $stmt->execute(array($companyId));
    $obligationPaymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtIncome = $pdo->prepare(
        'SELECT
            m.id_obligation_payment_method,
            m.method_name,
            COALESCE(m.method_description, \'\') AS method_description,
            m.is_active,
            m.deleted_at,
            m.created_at,
            m.updated_at
         FROM pms_settings_obligation_payment_method m
         WHERE m.id_company = ?
           AND m.deleted_at IS NULL
           AND m.is_active = 1
           AND COALESCE(m.method_description, \'\') LIKE \'[scope:income]%%\'
         ORDER BY m.method_name, m.id_obligation_payment_method'
    );
    $stmtIncome->execute(array($companyId));
    $incomePaymentMethods = $stmtIncome->fetchAll(PDO::FETCH_ASSOC);
    foreach ($incomePaymentMethods as $idx => $row) {
        $raw = isset($row['method_description']) ? (string)$row['method_description'] : '';
        if (stripos($raw, '[scope:income]') === 0) {
            $raw = trim((string)substr($raw, 14));
        }
        $incomePaymentMethods[$idx]['method_description'] = $raw;
    }
} catch (Exception $e) {
    $obligationPaymentMethods = array();
    $incomePaymentMethods = array();
    if ($error === null) {
        $error = 'No se pudieron cargar los metodos de pago de obligaciones: ' . $e->getMessage();
    }
}

?>
<div class="settings-page" data-settings-active-tab="<?php echo htmlspecialchars($settingsTab, ENT_QUOTES, 'UTF-8'); ?>">
<div class="page-header">
  <h2>Configuraciones</h2>
  <p class="muted">Preferencias generales del PMS.</p>
</div>
<div class="filters">
  <form method="post" class="form-inline">
    <input type="hidden" name="settings_tab" value="<?php echo htmlspecialchars($settingsTab, ENT_QUOTES, 'UTF-8'); ?>">
    <label>Propiedad
      <select name="settings_filter_property" onchange="this.form.submit();">
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
  </form>
</div>
<?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php if ($message): ?><p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php if ($themeError): ?><p class="error"><?php echo htmlspecialchars($themeError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php if ($themeMessage): ?><p class="success"><?php echo htmlspecialchars($themeMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<script>
  (function () {
    var themeCode = <?php echo json_encode($currentTheme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (!document.body || !themeCode) {
      return;
    }
    document.body.classList.remove('theme-default', 'theme-ocean');
    document.body.classList.add('theme-' + themeCode);
  })();
</script>

<div class="settings-tabs" role="tablist" aria-label="Secciones de configuraciones">
  <button type="button" class="settings-tab-trigger<?php echo $settingsTab === 'general' ? ' is-active' : ''; ?>" data-settings-tab-trigger="general" role="tab" aria-selected="<?php echo $settingsTab === 'general' ? 'true' : 'false'; ?>">General</button>
  <button type="button" class="settings-tab-trigger<?php echo $settingsTab === 'payments' ? ' is-active' : ''; ?>" data-settings-tab-trigger="payments" role="tab" aria-selected="<?php echo $settingsTab === 'payments' ? 'true' : 'false'; ?>">Pagos</button>
  <button type="button" class="settings-tab-trigger<?php echo $settingsTab === 'concepts' ? ' is-active' : ''; ?>" data-settings-tab-trigger="concepts" role="tab" aria-selected="<?php echo $settingsTab === 'concepts' ? 'true' : 'false'; ?>">Conceptos</button>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="general">
  <h3>Mantenimiento de folio</h3>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="recalc_folio_nodes">
    <label class="full">Selecciona reservaciones para recalcular sus folios</label>
    <div class="reservation-checklist full">
      <?php if (!$recentReservations): ?>
        <p class="muted">Sin reservaciones disponibles.</p>
      <?php else: ?>
        <?php foreach ($recentReservations as $r):
          $reservationId = isset($r['id_reservation']) ? (int)$r['id_reservation'] : 0;
          if ($reservationId <= 0) continue;
          $reservationCode = trim((string)(isset($r['reservation_code']) ? $r['reservation_code'] : ''));
          $guestFullName = trim((string)(isset($r['guest_full_name']) ? $r['guest_full_name'] : ''));
          if ($guestFullName === '') {
              $guestNames = trim((string)(isset($r['guest_names']) ? $r['guest_names'] : ''));
              $guestLastName = trim((string)(isset($r['guest_last_name']) ? $r['guest_last_name'] : ''));
              $guestFullName = trim($guestNames . ' ' . $guestLastName);
          }
          $displayName = $reservationCode !== '' ? $reservationCode : ('Reserva #' . $reservationId);
          if ($guestFullName !== '') {
              $displayName .= ' - ' . $guestFullName;
          }
          $meta = array();
          if (!empty($r['property_code'])) $meta[] = strtoupper((string)$r['property_code']);
          if (!empty($r['room_code'])) $meta[] = (string)$r['room_code'];
          if (!empty($r['check_in_date']) || !empty($r['check_out_date'])) {
              $meta[] = trim((string)$r['check_in_date']) . ' -> ' . trim((string)$r['check_out_date']);
          }
        ?>
          <label class="reservation-checkbox">
            <input type="checkbox" name="settings_recalc_reservation_ids[]" value="<?php echo $reservationId; ?>">
            <span class="reservation-title"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($meta): ?>
              <small class="muted reservation-meta"><?php echo htmlspecialchars(implode(' | ', $meta), ENT_QUOTES, 'UTF-8'); ?></small>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="form-actions full">
      <button type="submit">Recalcular folios seleccionados</button>
    </div>
  </form>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="general">
  <h3>Paleta de colores</h3>
  <p class="muted">Este tema se guarda por usuario. Cada cuenta puede usar su propia paleta.</p>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_theme">
    <label class="full">Tema del usuario</label>
    <label>
      <select name="settings_theme_code">
        <option value="default" <?php echo $currentTheme === 'default' ? 'selected' : ''; ?>>Nocturno</option>
        <option value="ocean" <?php echo $currentTheme === 'ocean' ? 'selected' : ''; ?>>Claro</option>
      </select>
    </label>
    <div class="form-actions full">
      <button type="submit">Guardar tema</button>
    </div>
  </form>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="general">
  <h3>Zona horaria del sistema</h3>
  <p class="muted">Se usa para "Hoy" y fechas/hora del sistema (calendario, panel y reportes).</p>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_timezone">
    <label class="full">Zona horaria (IANA)</label>
    <label class="full">
      <input type="text" name="settings_timezone" list="settings-timezone-list" value="<?php echo htmlspecialchars($currentSoftwareTimezone, ENT_QUOTES, 'UTF-8'); ?>" placeholder="America/Mexico_City" required>
    </label>
    <datalist id="settings-timezone-list">
      <?php foreach ($timezoneOptions as $tzOption): ?>
        <option value="<?php echo htmlspecialchars((string)$tzOption, ENT_QUOTES, 'UTF-8'); ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <div class="form-actions full">
      <button type="submit">Guardar zona horaria</button>
    </div>
  </form>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="payments">
  <h3>Metodos de pago</h3>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_payment_method">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label>Nombre del metodo
      <input type="text" name="settings_payment_method_name" maxlength="120" placeholder="Transferencia, Efectivo, Tarjeta...">
    </label>
    <div class="form-actions">
      <button type="submit">Agregar metodo</button>
    </div>
  </form>

  <?php if (!$paymentMethods): ?>
    <p class="muted">Sin metodos de pago configurados.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Metodo</th>
            <th>Ambito</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paymentMethods as $pm): ?>
            <?php
              $pmId = isset($pm['id_payment_method']) ? (int)$pm['id_payment_method'] : 0;
              if ($pmId <= 0) {
                  continue;
              }
              $pmName = isset($pm['method_name']) ? (string)$pm['method_name'] : '';
              $pmProp = isset($pm['property_code']) ? (string)$pm['property_code'] : '';
            ?>
            <tr>
              <td><?php echo htmlspecialchars($pmName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($pmProp !== '' ? $pmProp : '(GLOBAL)', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <form method="post" class="inline-form">
                  <input type="hidden" name="settings_action" value="delete_payment_method">
                  <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="settings_payment_method_id" value="<?php echo (int)$pmId; ?>">
                  <button type="submit" class="button-secondary">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="payments">
  <h3>Metodos de pago de obligaciones</h3>
  <p class="muted">Estos metodos se usan al abonar obligaciones en Dashboard y modulo de Obligaciones.</p>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_obligation_payment_method">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label>Nombre del metodo
      <input type="text" name="settings_obligation_payment_method_name" maxlength="120" placeholder="Transferencia, SPEI, Efectivo..." required>
    </label>
    <label>Descripcion
      <input type="text" name="settings_obligation_payment_method_description" maxlength="255" placeholder="Opcional">
    </label>
    <div class="form-actions full">
      <button type="submit">Agregar metodo</button>
    </div>
  </form>

  <?php if (!$obligationPaymentMethods): ?>
    <p class="muted">Sin metodos de pago de obligaciones configurados.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Metodo</th>
            <th>Descripcion</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($obligationPaymentMethods as $method): ?>
            <?php
              $methodId = isset($method['id_obligation_payment_method']) ? (int)$method['id_obligation_payment_method'] : 0;
              if ($methodId <= 0) {
                  continue;
              }
              $methodName = isset($method['method_name']) ? (string)$method['method_name'] : '';
              $methodDescription = isset($method['method_description']) ? (string)$method['method_description'] : '';
            ?>
            <tr>
              <td><?php echo htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($methodDescription !== '' ? $methodDescription : '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <form method="post" class="inline-form">
                  <input type="hidden" name="settings_action" value="delete_obligation_payment_method">
                  <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="settings_obligation_payment_method_id" value="<?php echo (int)$methodId; ?>">
                  <button type="submit" class="button-secondary">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="payments">
  <h3>Metodos de pago de ingresos</h3>
  <p class="muted">Estos metodos se usan para confirmar o abonar line items tipo ingreso.</p>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_income_payment_method">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label>Nombre del metodo
      <input type="text" name="settings_income_payment_method_name" maxlength="120" placeholder="Transferencia, SPEI, Efectivo..." required>
    </label>
    <label>Descripcion
      <input type="text" name="settings_income_payment_method_description" maxlength="255" placeholder="Opcional">
    </label>
    <div class="form-actions full">
      <button type="submit">Agregar metodo</button>
    </div>
  </form>

  <?php if (!$incomePaymentMethods): ?>
    <p class="muted">Sin metodos de pago de ingresos configurados.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Metodo</th>
            <th>Descripcion</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incomePaymentMethods as $method): ?>
            <?php
              $methodId = isset($method['id_obligation_payment_method']) ? (int)$method['id_obligation_payment_method'] : 0;
              if ($methodId <= 0) {
                  continue;
              }
              $methodName = isset($method['method_name']) ? (string)$method['method_name'] : '';
              $methodDescription = isset($method['method_description']) ? (string)$method['method_description'] : '';
            ?>
            <tr>
              <td><?php echo htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($methodDescription !== '' ? $methodDescription : '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <form method="post" class="inline-form">
                  <input type="hidden" name="settings_action" value="delete_income_payment_method">
                  <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="settings_income_payment_method_id" value="<?php echo (int)$methodId; ?>">
                  <button type="submit" class="button-secondary">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="general">
  <h3>Or&iacute;genes de reservaci&oacute;n</h3>
  <p class="muted">Define or&iacute;genes manuales para reservaciones (globales o por propiedad).</p>
  <?php
    $newSourceInfoRows = array();
    if (
        isset($_POST['settings_action'])
        && (string)$_POST['settings_action'] === 'save_reservation_source'
        && isset($_POST['settings_reservation_source_id'])
        && (int)$_POST['settings_reservation_source_id'] <= 0
        && isset($sourceInfoRows)
        && is_array($sourceInfoRows)
    ) {
        $newSourceInfoRows = $sourceInfoRows;
    }
  ?>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_reservation_source">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="settings_reservation_source_id" value="0">
    <label>Nombre del origen
      <input type="text" name="settings_reservation_source_name" maxlength="120" placeholder="Directo, Walk-in, Referido..." required>
    </label>
    <label>Alcance
      <select name="settings_reservation_source_scope">
        <option value="">Global (todas las propiedades)</option>
        <?php foreach ($properties as $property):
          $scopeCode = strtoupper((string)$property['code']);
          $scopeLabel = $scopeCode . ' - ' . (string)$property['name'];
        ?>
          <option value="<?php echo htmlspecialchars($scopeCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $scopeCode === $filters['property_code'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>C&oacute;digo
      <input type="text" name="settings_reservation_source_code" maxlength="12" placeholder="MAP, DIR, REF...">
    </label>
    <label>Color
      <input type="color" name="settings_reservation_source_color_hex" value="#64748B">
    </label>
    <label>Concepto de hospedaje
      <select name="settings_reservation_source_lodging_catalog_id">
        <option value="0">(Sin concepto ligado)</option>
        <?php foreach ($lodgingConceptOptionRows as $lodgingOpt): ?>
          <option value="<?php echo (int)$lodgingOpt['id']; ?>">
            <?php echo htmlspecialchars((string)$lodgingOpt['label'], ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="full">Notas
      <textarea name="settings_reservation_source_notes" maxlength="500" placeholder="Opcional"></textarea>
    </label>
    <label class="full">Conceptos para cuadro informativo (con alias)</label>
    <div class="full source-info-editor" data-source-info-editor>
      <?php if (!$sourceInfoConceptOptionRows): ?>
        <p class="muted">Sin conceptos disponibles.</p>
      <?php else: ?>
        <div class="source-info-row-add">
          <select data-source-info-new-catalog>
            <option value="">Selecciona concepto...</option>
            <?php foreach ($sourceInfoConceptOptionRows as $sourceInfoOpt): ?>
              <option value="<?php echo (int)$sourceInfoOpt['id']; ?>">
                <?php echo htmlspecialchars((string)$sourceInfoOpt['label'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" data-source-info-new-alias maxlength="160" placeholder="Alias (opcional)">
          <button type="button" class="button-secondary" data-source-info-add>Agregar</button>
        </div>
        <div class="source-info-rows" data-source-info-rows>
          <?php foreach ($newSourceInfoRows as $sourceInfoRow): ?>
            <?php
              $catalogId = isset($sourceInfoRow['catalog_id']) ? (int)$sourceInfoRow['catalog_id'] : 0;
              if ($catalogId <= 0) {
                  continue;
              }
              $alias = isset($sourceInfoRow['alias']) ? (string)$sourceInfoRow['alias'] : '';
              $label = isset($sourceInfoConceptOptionMap[$catalogId])
                  ? (string)$sourceInfoConceptOptionMap[$catalogId]
                  : ('Concepto #' . $catalogId);
            ?>
            <div class="source-info-row" data-catalog-id="<?php echo (int)$catalogId; ?>">
              <input type="hidden" name="settings_reservation_source_info_catalog_id[]" value="<?php echo (int)$catalogId; ?>">
              <div class="source-info-row-title"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
              <input type="text" name="settings_reservation_source_info_alias[]" maxlength="160" placeholder="Alias en reservacion" value="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>">
              <button type="button" class="button-secondary" data-source-info-remove>Quitar</button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="form-actions full">
      <button type="submit">Agregar origen</button>
    </div>
  </form>

  <?php if (!$reservationSources): ?>
    <p class="muted">Sin or&iacute;genes configurados.</p>
  <?php else: ?>
    <div class="source-list">
      <?php foreach ($reservationSources as $sourceRow): ?>
        <?php
          $sourceId = isset($sourceRow['id_reservation_source']) ? (int)$sourceRow['id_reservation_source'] : 0;
          if ($sourceId <= 0) {
              continue;
          }
          $sourceName = trim((string)(isset($sourceRow['source_name']) ? $sourceRow['source_name'] : ''));
          $sourceCode = strtoupper(trim((string)(isset($sourceRow['source_code']) ? $sourceRow['source_code'] : '')));
          $sourceColorHex = function_exists('pms_reservation_source_normalize_color_hex')
              ? pms_reservation_source_normalize_color_hex(isset($sourceRow['color_hex']) ? (string)$sourceRow['color_hex'] : '')
              : '';
          if ($sourceColorHex === '') {
              $sourceColorHex = '#64748B';
          }
          $sourceNotes = (string)(isset($sourceRow['notes']) ? $sourceRow['notes'] : '');
          $sourcePropertyCode = strtoupper(trim((string)(isset($sourceRow['property_code']) ? $sourceRow['property_code'] : '')));
          $sourcePropertyId = isset($sourceRow['id_property']) ? (int)$sourceRow['id_property'] : 0;
          $sourceLodgingCatalogId = isset($sourceRow['id_lodging_catalog']) ? (int)$sourceRow['id_lodging_catalog'] : 0;
          $sourceInfoRowsForSource = isset($reservationSourceInfoRowsBySource[$sourceId]) && is_array($reservationSourceInfoRowsBySource[$sourceId])
              ? $reservationSourceInfoRowsBySource[$sourceId]
              : array();
          if (
              isset($_POST['settings_action'])
              && (string)$_POST['settings_action'] === 'save_reservation_source'
              && isset($_POST['settings_reservation_source_id'])
              && (int)$_POST['settings_reservation_source_id'] === $sourceId
              && isset($sourceInfoRows)
              && is_array($sourceInfoRows)
          ) {
              $sourceInfoRowsForSource = $sourceInfoRows;
          }
          if ($sourcePropertyCode === '' && $sourcePropertyId <= 0) {
              $sourcePropertyCode = '';
          }
        ?>
        <form method="post" class="form-grid grid-2 source-row-form">
          <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="settings_reservation_source_id" value="<?php echo (int)$sourceId; ?>">
          <label>Nombre del origen
            <input type="text" name="settings_reservation_source_name" maxlength="120" value="<?php echo htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8'); ?>" required>
          </label>
          <label>Alcance
            <select name="settings_reservation_source_scope">
              <option value="" <?php echo $sourcePropertyCode === '' ? 'selected' : ''; ?>>Global (todas las propiedades)</option>
              <?php foreach ($properties as $property):
                $scopeCode = strtoupper((string)$property['code']);
                $scopeLabel = $scopeCode . ' - ' . (string)$property['name'];
              ?>
                <option value="<?php echo htmlspecialchars($scopeCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $scopeCode === $sourcePropertyCode ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>C&oacute;digo
            <input type="text" name="settings_reservation_source_code" maxlength="12" value="<?php echo htmlspecialchars($sourceCode, ENT_QUOTES, 'UTF-8'); ?>" placeholder="MAP, DIR, REF...">
          </label>
          <label>Color
            <input type="color" name="settings_reservation_source_color_hex" value="<?php echo htmlspecialchars($sourceColorHex, ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>Concepto de hospedaje
            <select name="settings_reservation_source_lodging_catalog_id">
              <option value="0" <?php echo $sourceLodgingCatalogId <= 0 ? 'selected' : ''; ?>>(Sin concepto ligado)</option>
              <?php foreach ($lodgingConceptOptionRows as $lodgingOpt): ?>
                <option value="<?php echo (int)$lodgingOpt['id']; ?>" <?php echo $sourceLodgingCatalogId === (int)$lodgingOpt['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string)$lodgingOpt['label'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="full">Notas
            <textarea name="settings_reservation_source_notes" maxlength="500" placeholder="Opcional"><?php echo htmlspecialchars($sourceNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </label>
          <label class="full">Conceptos para cuadro informativo (con alias)</label>
          <div class="full source-info-editor" data-source-info-editor>
            <?php if (!$sourceInfoConceptOptionRows): ?>
              <p class="muted">Sin conceptos disponibles.</p>
            <?php else: ?>
              <div class="source-info-row-add">
                <select data-source-info-new-catalog>
                  <option value="">Selecciona concepto...</option>
                  <?php foreach ($sourceInfoConceptOptionRows as $sourceInfoOpt): ?>
                    <option value="<?php echo (int)$sourceInfoOpt['id']; ?>">
                      <?php echo htmlspecialchars((string)$sourceInfoOpt['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="text" data-source-info-new-alias maxlength="160" placeholder="Alias (opcional)">
                <button type="button" class="button-secondary" data-source-info-add>Agregar</button>
              </div>
              <div class="source-info-rows" data-source-info-rows>
                <?php foreach ($sourceInfoRowsForSource as $sourceInfoRow): ?>
                  <?php
                    $catalogId = isset($sourceInfoRow['catalog_id']) ? (int)$sourceInfoRow['catalog_id'] : 0;
                    if ($catalogId <= 0) {
                        continue;
                    }
                    $alias = isset($sourceInfoRow['alias']) ? (string)$sourceInfoRow['alias'] : '';
                    $label = isset($sourceInfoConceptOptionMap[$catalogId])
                        ? (string)$sourceInfoConceptOptionMap[$catalogId]
                        : ('Concepto #' . $catalogId);
                  ?>
                  <div class="source-info-row" data-catalog-id="<?php echo (int)$catalogId; ?>">
                    <input type="hidden" name="settings_reservation_source_info_catalog_id[]" value="<?php echo (int)$catalogId; ?>">
                    <div class="source-info-row-title"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                    <input type="text" name="settings_reservation_source_info_alias[]" maxlength="160" placeholder="Alias en reservacion" value="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="button" class="button-secondary" data-source-info-remove>Quitar</button>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="inline-actions full">
            <button type="submit" name="settings_action" value="save_reservation_source">Guardar</button>
            <button type="submit" name="settings_action" value="delete_reservation_source" class="button-secondary" formnovalidate onclick="return confirm('Se eliminara este origen. &iquest;Continuar?');">Eliminar</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="concepts">
  <h3>Hospedaje (cargo autom&aacute;tico en reservas)</h3>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_lodging">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label class="full">Conceptos permitidos de hospedaje</label>
    <div class="concept-checklist full">
      <?php if (!$lodgingConcepts): ?>
        <p class="muted">Sin conceptos disponibles.</p>
      <?php else: ?>
        <?php foreach ($lodgingConcepts as $c):
          $id = (int)$c['id_sale_item_catalog'];
          $label = (string)$c['item_name'];
          $cat = isset($c['category']) ? (string)$c['category'] : '';
          $prop = isset($c['property_code']) ? (string)$c['property_code'] : '';
          $full = ($prop !== '' ? $prop . ' - ' : '') . ($cat !== '' ? $cat . ' / ' : '') . $label;
          $checked = isset($selectedCatalogMap[$id]) ? 'checked' : '';
        ?>
          <label class="checkbox-inline">
            <input type="checkbox" name="setting_lodging_catalog_ids[]" value="<?php echo $id; ?>" <?php echo $checked; ?>>
            <?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="form-actions full">
      <button type="submit">Guardar configuracion</button>
    </div>
  </form>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="concepts">
  <h3>Intereses de reserva</h3>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_interests">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label class="full">Conceptos permitidos para intereses</label>
    <div class="concept-checklist full">
      <?php if (!$interestConcepts): ?>
        <p class="muted">Sin conceptos disponibles.</p>
      <?php else: ?>
        <?php foreach ($interestConcepts as $c):
          $id = (int)$c['id_sale_item_catalog'];
          $label = (string)$c['item_name'];
          $checked = isset($selectedInterestMap[$id]) ? 'checked' : '';
        ?>
          <label class="checkbox-inline">
            <input type="checkbox" name="setting_interest_catalog_ids[]" value="<?php echo $id; ?>" <?php echo $checked; ?>>
            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="form-actions full">
      <button type="submit">Guardar configuracion</button>
    </div>
  </form>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="concepts">
  <h3>Conceptos para registrar servicios (folio / calendario)</h3>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_service_concepts">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label class="full">Conceptos de tipo cargo/servicio permitidos</label>
    <div class="concept-checklist full">
      <?php if (!$serviceConcepts): ?>
        <p class="muted">Sin conceptos de servicios disponibles.</p>
      <?php else: ?>
        <?php foreach ($serviceConcepts as $c):
          $id = (int)$c['id_sale_item_catalog'];
          $label = (string)$c['item_name'];
          $cat = isset($c['category']) ? (string)$c['category'] : '';
          $prop = isset($c['property_code']) ? (string)$c['property_code'] : '';
          $full = ($prop !== '' ? $prop . ' - ' : '') . ($cat !== '' ? $cat . ' / ' : '') . $label;
          $checked = isset($selectedServiceCatalogMap[$id]) ? 'checked' : '';
        ?>
          <label class="checkbox-inline">
            <input type="checkbox" name="setting_service_catalog_ids[]" value="<?php echo $id; ?>" <?php echo $checked; ?>>
            <?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="form-actions full">
      <button type="submit">Guardar configuracion</button>
    </div>
  </form>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="concepts">
  <h3>Conceptos para registrar pagos en folio</h3>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="settings_action" value="save_payment_concepts">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label class="full">Conceptos de tipo pago permitidos</label>
    <div class="concept-checklist full">
      <?php if (!$paymentConcepts): ?>
        <p class="muted">Sin conceptos de pago disponibles.</p>
      <?php else: ?>
        <?php foreach ($paymentConcepts as $c):
          $id = (int)$c['id_sale_item_catalog'];
          $label = (string)$c['item_name'];
          $cat = isset($c['category']) ? (string)$c['category'] : '';
          $prop = isset($c['property_code']) ? (string)$c['property_code'] : '';
          $full = ($prop !== '' ? $prop . ' - ' : '') . ($cat !== '' ? $cat . ' / ' : '') . $label;
          $checked = isset($selectedPaymentCatalogMap[$id]) ? 'checked' : '';
        ?>
          <label class="checkbox-inline">
            <input type="checkbox" name="setting_payment_catalog_ids[]" value="<?php echo $id; ?>" <?php echo $checked; ?>>
            <?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="form-actions full">
      <button type="submit">Guardar configuracion</button>
    </div>
  </form>
</div>

<div class="panel settings-tab-panel" data-settings-tab-panel="concepts">
  <h3>Bloqueo de metodos de pago por concepto de hospedaje</h3>
  <p class="muted">Define que metodos de pago no deben aparecer al aplicar pagos cuando la reserva usa un concepto de hospedaje especifico.</p>
  <form method="post" class="form-grid grid-1">
    <input type="hidden" name="settings_action" value="save_lodging_payment_blocks">
    <input type="hidden" name="settings_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php
      $hasLodgingPaymentBlockTable = false;
      try {
          $hasLodgingPaymentBlockTable = settings_table_exists(pms_get_connection(), 'pms_settings_lodging_payment_block');
      } catch (Exception $e) {
          $hasLodgingPaymentBlockTable = false;
      }
      $lodgingRowsForBlock = array();
      foreach ($selectedCatalogMap as $lodgingCatalogId => $tmpTrue) {
          $lodgingCatalogId = (int)$lodgingCatalogId;
          if ($lodgingCatalogId <= 0 || !isset($lodgingConceptOptionMapById[$lodgingCatalogId])) {
              continue;
          }
          $lodgingRowsForBlock[$lodgingCatalogId] = $lodgingConceptOptionMapById[$lodgingCatalogId];
      }
      $paymentRowsForBlock = array();
      foreach ($selectedPaymentCatalogMap as $paymentCatalogId => $tmpTrue) {
          $paymentCatalogId = (int)$paymentCatalogId;
          if ($paymentCatalogId <= 0 || !isset($paymentConceptOptionMapById[$paymentCatalogId])) {
              continue;
          }
          $paymentRowsForBlock[$paymentCatalogId] = $paymentConceptOptionMapById[$paymentCatalogId];
      }
    ?>
    <?php if (!$hasLodgingPaymentBlockTable): ?>
      <p class="muted">Falta tabla de bloqueos. Ejecuta: <code>bd pms/migrate_lodging_payment_method_block.sql</code></p>
    <?php elseif (!$lodgingRowsForBlock): ?>
      <p class="muted">Primero selecciona conceptos en "Hospedaje (cargo automatico en reservas)".</p>
    <?php elseif (!$paymentRowsForBlock): ?>
      <p class="muted">Primero selecciona conceptos en "Conceptos para registrar pagos en folio".</p>
    <?php else: ?>
      <div class="lodging-payment-block-list full">
        <?php foreach ($lodgingRowsForBlock as $lodgingCatalogId => $lodgingLabel): ?>
          <div class="lodging-payment-block-row">
            <div class="lodging-payment-block-title"><?php echo htmlspecialchars($lodgingLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="lodging-payment-block-options">
              <?php foreach ($paymentRowsForBlock as $paymentCatalogId => $paymentLabel): ?>
                <?php $isChecked = isset($selectedLodgingPaymentBlockMap[(int)$lodgingCatalogId]) && isset($selectedLodgingPaymentBlockMap[(int)$lodgingCatalogId][(int)$paymentCatalogId]); ?>
                <label class="checkbox-inline">
                  <input
                    type="checkbox"
                    name="setting_lodging_payment_block[<?php echo (int)$lodgingCatalogId; ?>][]"
                    value="<?php echo (int)$paymentCatalogId; ?>"
                    <?php echo $isChecked ? 'checked' : ''; ?>>
                  <?php echo htmlspecialchars($paymentLabel, ENT_QUOTES, 'UTF-8'); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions full">
        <button type="submit">Guardar bloqueos</button>
      </div>
    <?php endif; ?>
  </form>
</div>

<style>
.settings-tabs { display: flex; gap: 8px; margin: 14px 0 10px; flex-wrap: wrap; }
.settings-tab-trigger {
  border: 1px solid rgba(120, 170, 215, 0.35);
  background: rgba(5, 18, 34, 0.5);
  color: #d8ebff;
  border-radius: 999px;
  padding: 7px 14px;
  cursor: pointer;
}
.settings-tab-trigger.is-active {
  border-color: rgba(56, 189, 248, 0.85);
  background: rgba(8, 56, 92, 0.45);
  color: #f3fbff;
}
.settings-tab-panel { display: none; }
.settings-page[data-settings-active-tab="general"] .settings-tab-panel[data-settings-tab-panel="general"] { display: block; }
.settings-page[data-settings-active-tab="payments"] .settings-tab-panel[data-settings-tab-panel="payments"] { display: block; }
.settings-page[data-settings-active-tab="concepts"] .settings-tab-panel[data-settings-tab-panel="concepts"] { display: block; }
.page-header h2 { margin: 0 0 4px 0; }
.page-header p { margin: 0; }
.filters { margin: 12px 0; }
.panel { border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 14px; margin-top: 12px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid.grid-1 { grid-template-columns: 1fr; }
.form-grid .full { grid-column: 1 / -1; }
.form-actions.full { grid-column: 1 / -1; }
.concept-checklist { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px 12px; }
.concept-checklist .muted { margin: 0; }
.concept-checklist .checkbox-inline { display: flex; align-items: center; gap: 8px; flex-direction: row; margin: 0; }
.concept-checklist .checkbox-inline input { margin: 0; }
.inline-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.inline-actions .inline-form { margin: 0; }
.panel textarea { width: 100%; min-height: 74px; resize: vertical; }
.source-list { display: grid; gap: 10px; margin-top: 10px; }
.source-row-form { border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 10px; }
.source-info-editor { display: grid; gap: 8px; }
.source-info-row-add {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}
.source-info-rows { display: grid; gap: 8px; }
.source-info-row {
  display: grid;
  grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  padding: 8px;
  border: 1px solid rgba(120, 170, 215, 0.25);
  border-radius: 10px;
  background: rgba(5, 16, 31, 0.5);
}
.source-info-row-title {
  color: #dff2ff;
  font-size: 0.9rem;
}
.reservation-checklist {
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
  padding: 10px;
  max-height: 320px;
  overflow-y: auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  gap: 10px 12px;
}
.reservation-checkbox {
  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 8px;
  align-items: start;
  padding: 8px 10px;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
}
.reservation-checkbox input {
  margin-top: 2px;
}
.reservation-title {
  white-space: normal;
  word-break: break-word;
  line-height: 1.35;
}
.reservation-meta {
  grid-column: 2;
  white-space: normal;
  word-break: break-word;
}
.lodging-payment-block-list { display: grid; gap: 10px; }
.lodging-payment-block-row {
  border: 1px solid rgba(120, 170, 215, 0.25);
  border-radius: 10px;
  padding: 10px;
  background: rgba(5, 16, 31, 0.45);
}
.lodging-payment-block-title {
  margin-bottom: 8px;
  color: #dff2ff;
  font-weight: 700;
}
.lodging-payment-block-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 8px 12px;
}
@media (max-width: 1024px) {
  .reservation-checklist {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 980px) {
  .source-info-row-add,
  .source-info-row {
    grid-template-columns: 1fr;
  }
}
</style>
<script>
(function () {
  var settingsPage = document.querySelector('.settings-page');
  if (!settingsPage) {
    return;
  }
  var defaultTab = (settingsPage.getAttribute('data-settings-active-tab') || 'general').toLowerCase();
  if (['general', 'payments', 'concepts'].indexOf(defaultTab) === -1) {
    defaultTab = 'general';
  }

  function syncFormTabValue(tabKey) {
    settingsPage.querySelectorAll('form').forEach(function (form) {
      var existing = form.querySelector('input[name="settings_tab"]');
      if (!existing) {
        existing = document.createElement('input');
        existing.type = 'hidden';
        existing.name = 'settings_tab';
        form.appendChild(existing);
      }
      existing.value = tabKey;
    });
  }

  function setActiveTab(tabKey) {
    var normalized = (tabKey || '').toLowerCase();
    if (['general', 'payments', 'concepts'].indexOf(normalized) === -1) {
      normalized = 'general';
    }
    settingsPage.setAttribute('data-settings-active-tab', normalized);
    settingsPage.querySelectorAll('[data-settings-tab-trigger]').forEach(function (trigger) {
      var isActive = (trigger.getAttribute('data-settings-tab-trigger') || '').toLowerCase() === normalized;
      trigger.classList.toggle('is-active', isActive);
      trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
    syncFormTabValue(normalized);
  }

  settingsPage.querySelectorAll('[data-settings-tab-trigger]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      setActiveTab(trigger.getAttribute('data-settings-tab-trigger') || 'general');
    });
  });

  setActiveTab(defaultTab);
})();

(function () {
  var editors = document.querySelectorAll('[data-source-info-editor]');
  if (!editors || !editors.length) {
    return;
  }

  function bindEditor(editor) {
    var rowsWrap = editor.querySelector('[data-source-info-rows]');
    var selectNew = editor.querySelector('[data-source-info-new-catalog]');
    var aliasNew = editor.querySelector('[data-source-info-new-alias]');
    var addBtn = editor.querySelector('[data-source-info-add]');
    if (!rowsWrap || !selectNew || !addBtn) {
      return;
    }

    function hasCatalog(catalogId) {
      return !!rowsWrap.querySelector('.source-info-row[data-catalog-id="' + String(catalogId) + '"]');
    }

    function createRow(catalogId, label, alias) {
      var row = document.createElement('div');
      row.className = 'source-info-row';
      row.setAttribute('data-catalog-id', String(catalogId));

      var hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'settings_reservation_source_info_catalog_id[]';
      hidden.value = String(catalogId);
      row.appendChild(hidden);

      var title = document.createElement('div');
      title.className = 'source-info-row-title';
      title.textContent = label || ('Concepto #' + String(catalogId));
      row.appendChild(title);

      var aliasInput = document.createElement('input');
      aliasInput.type = 'text';
      aliasInput.name = 'settings_reservation_source_info_alias[]';
      aliasInput.maxLength = 160;
      aliasInput.placeholder = 'Alias en reservacion';
      aliasInput.value = alias || '';
      row.appendChild(aliasInput);

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'button-secondary';
      removeBtn.setAttribute('data-source-info-remove', '1');
      removeBtn.textContent = 'Quitar';
      row.appendChild(removeBtn);

      return row;
    }

    addBtn.addEventListener('click', function () {
      var catalogId = parseInt(selectNew.value || '0', 10);
      if (!catalogId || hasCatalog(catalogId)) {
        return;
      }
      var option = selectNew.options[selectNew.selectedIndex];
      var label = option ? option.text : ('Concepto #' + String(catalogId));
      rowsWrap.appendChild(createRow(catalogId, label, aliasNew ? aliasNew.value : ''));
      if (aliasNew) {
        aliasNew.value = '';
      }
      selectNew.value = '';
    });

    rowsWrap.addEventListener('click', function (ev) {
      var removeBtn = ev.target.closest('[data-source-info-remove]');
      if (!removeBtn) {
        return;
      }
      var row = removeBtn.closest('.source-info-row');
      if (row) {
        row.remove();
      }
    });
  }

  editors.forEach(bindEditor);
})();
</script>
</div>
