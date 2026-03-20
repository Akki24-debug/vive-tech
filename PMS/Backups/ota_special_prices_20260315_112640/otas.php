<?php
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
pms_require_permission('otas.view');

$properties = pms_fetch_properties($companyId);
$defaultPropertyCode = '';
if (!empty($properties) && isset($properties[0]['code'])) {
    $defaultPropertyCode = strtoupper((string)$properties[0]['code']);
}

$message = null;
$error = null;
$selectedTabId = isset($_POST['ota_selected_id'])
    ? (int)$_POST['ota_selected_id']
    : (isset($_GET['ota_edit']) ? (int)$_GET['ota_edit'] : 0);
$forceNew = isset($_GET['ota_new']) && (int)$_GET['ota_new'] === 1;

if (!function_exists('ota_normalize_color_hex')) {
    function ota_normalize_color_hex($value)
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

if (isset($_POST['ota_action']) && $_POST['ota_action'] === 'save_ota_account') {
    pms_require_permission('otas.edit');
    $otaAccountId = isset($_POST['ota_account_id']) ? (int)$_POST['ota_account_id'] : 0;
    $otaPropertyCode = isset($_POST['ota_property_code_internal'])
        ? strtoupper(trim((string)$_POST['ota_property_code_internal']))
        : $defaultPropertyCode;
    $otaPlatform = isset($_POST['ota_platform']) ? strtolower(trim((string)$_POST['ota_platform'])) : 'other';
    $otaName = isset($_POST['ota_name']) ? trim((string)$_POST['ota_name']) : '';
    $otaExternalCode = isset($_POST['ota_external_code']) ? trim((string)$_POST['ota_external_code']) : '';
    $otaContactEmail = isset($_POST['ota_contact_email']) ? trim((string)$_POST['ota_contact_email']) : '';
    $otaTimezone = isset($_POST['ota_timezone']) ? trim((string)$_POST['ota_timezone']) : 'America/Mexico_City';
    $otaColorHex = ota_normalize_color_hex(isset($_POST['ota_color_hex']) ? (string)$_POST['ota_color_hex'] : '');
    $otaNotes = isset($_POST['ota_notes']) ? trim((string)$_POST['ota_notes']) : '';
    $otaServiceFeePaymentCatalogId = isset($_POST['ota_service_fee_payment_catalog_id'])
        ? (int)$_POST['ota_service_fee_payment_catalog_id']
        : 0;
    $otaIsActive = isset($_POST['ota_is_active']) ? 1 : 0;
    $otaCatalogIds = isset($_POST['ota_lodging_catalog_ids']) ? $_POST['ota_lodging_catalog_ids'] : array();
    if (!is_array($otaCatalogIds)) {
        $otaCatalogIds = array();
    }
    $otaCatalogIds = array_values(array_unique(array_filter(array_map('intval', $otaCatalogIds), function ($id) {
        return $id > 0;
    })));
    $otaInfoCatalogIdsRaw = isset($_POST['ota_info_catalog_id']) ? $_POST['ota_info_catalog_id'] : array();
    $otaInfoAliasRaw = isset($_POST['ota_info_alias']) ? $_POST['ota_info_alias'] : array();
    if (!is_array($otaInfoCatalogIdsRaw)) {
        $otaInfoCatalogIdsRaw = array();
    }
    if (!is_array($otaInfoAliasRaw)) {
        $otaInfoAliasRaw = array();
    }
    $otaInfoRows = array();
    $otaInfoSeenCatalog = array();
    foreach ($otaInfoCatalogIdsRaw as $idx => $catalogRaw) {
        $catalogId = (int)$catalogRaw;
        if ($catalogId <= 0 || isset($otaInfoSeenCatalog[$catalogId])) {
            continue;
        }
        $alias = isset($otaInfoAliasRaw[$idx]) ? trim((string)$otaInfoAliasRaw[$idx]) : '';
        if (function_exists('mb_substr')) {
            $alias = mb_substr($alias, 0, 160);
        } else {
            $alias = substr($alias, 0, 160);
        }
        $otaInfoRows[] = array(
            'catalog_id' => $catalogId,
            'alias' => $alias,
            'sort_order' => count($otaInfoRows) + 1
        );
        $otaInfoSeenCatalog[$catalogId] = true;
    }
    $otaNameNorm = strtolower($otaName);
    if (strpos($otaNameNorm, 'booking') !== false) {
        $otaPlatform = 'booking';
    } elseif (strpos($otaNameNorm, 'airbnb') !== false || strpos($otaNameNorm, 'abb') !== false) {
        $otaPlatform = 'airbnb';
    } elseif (strpos($otaNameNorm, 'expedia') !== false) {
        $otaPlatform = 'expedia';
    } elseif (!in_array($otaPlatform, array('booking','airbnb','expedia','other'), true)) {
        $otaPlatform = 'other';
    }

    if ($otaName === '') {
        $error = 'El nombre OTA es obligatorio.';
    } elseif ($otaPropertyCode === '') {
        $error = 'No hay propiedades activas para registrar OTAs.';
    } elseif ($otaContactEmail !== '' && !preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$/', $otaContactEmail)) {
        $error = 'El correo de contacto OTA es invalido.';
    } elseif (trim((string)(isset($_POST['ota_color_hex']) ? $_POST['ota_color_hex'] : '')) !== '' && $otaColorHex === '') {
        $error = 'El color OTA debe tener formato HEX de 6 digitos (#RRGGBB).';
    } else {
        try {
            $saveSets = pms_call_procedure('sp_ota_account_upsert', array(
                $otaAccountId > 0 ? 'update' : 'create',
                $otaAccountId > 0 ? $otaAccountId : 0,
                $companyCode,
                $otaPropertyCode,
                $otaPlatform !== '' ? $otaPlatform : 'other',
                $otaName,
                $otaExternalCode !== '' ? $otaExternalCode : null,
                $otaContactEmail !== '' ? $otaContactEmail : null,
                $otaTimezone !== '' ? $otaTimezone : null,
                $otaNotes !== '' ? $otaNotes : null,
                $otaServiceFeePaymentCatalogId > 0 ? $otaServiceFeePaymentCatalogId : null,
                $otaIsActive,
                $actorUserId
            ));
            $savedRow = isset($saveSets[0][0]) ? $saveSets[0][0] : null;
            $savedOtaId = $savedRow && isset($savedRow['id_ota_account']) ? (int)$savedRow['id_ota_account'] : 0;
            if ($savedOtaId > 0) {
                try {
                    $pdoColor = pms_get_connection();
                    if (function_exists('pms_ota_account_has_color_hex_column') && pms_ota_account_has_color_hex_column($pdoColor)) {
                        $stmtColor = $pdoColor->prepare(
                            'UPDATE ota_account
                                SET color_hex = ?,
                                    updated_by = ?,
                                    updated_at = NOW()
                              WHERE id_ota_account = ?
                                AND id_company = ?'
                        );
                        $stmtColor->execute(array($otaColorHex !== '' ? $otaColorHex : null, $actorUserId, $savedOtaId, $companyId));
                    }
                } catch (Exception $e) {
                }

                pms_call_procedure('sp_ota_account_lodging_sync', array(
                    $companyCode,
                    $savedOtaId,
                    $otaCatalogIds ? implode(',', $otaCatalogIds) : '',
                    $actorUserId
                ));
                try {
                    $pdoInfo = pms_get_connection();
                    $stmtDeactivate = $pdoInfo->prepare(
                        'UPDATE ota_account_info_catalog
                            SET is_active = 0,
                                deleted_at = NOW(),
                                updated_at = NOW()
                          WHERE id_ota_account = ?
                            AND deleted_at IS NULL'
                    );
                    $stmtDeactivate->execute(array($savedOtaId));

                    if ($otaInfoRows) {
                        $stmtUpsert = $pdoInfo->prepare(
                            'UPDATE ota_account_info_catalog
                                SET sort_order = ?,
                                    display_alias = ?,
                                    is_active = 1,
                                    deleted_at = NULL,
                                    updated_at = NOW()
                              WHERE id_ota_account = ?
                                AND id_line_item_catalog = ?'
                        );
                        $stmtInsert = $pdoInfo->prepare(
                            'INSERT INTO ota_account_info_catalog (
                                id_ota_account,
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
                              AND cat.is_active = 1
                             WHERE lic.id_line_item_catalog = ?
                               AND lic.deleted_at IS NULL
                               AND lic.is_active = 1
                             LIMIT 1'
                        );
                        foreach ($otaInfoRows as $rowInfo) {
                            $catalogId = isset($rowInfo['catalog_id']) ? (int)$rowInfo['catalog_id'] : 0;
                            if ($catalogId <= 0) {
                                continue;
                            }
                            $sortOrder = isset($rowInfo['sort_order']) ? (int)$rowInfo['sort_order'] : 0;
                            $alias = isset($rowInfo['alias']) ? (string)$rowInfo['alias'] : '';
                            $aliasDb = $alias !== '' ? $alias : null;
                            $stmtUpsert->execute(array(
                                $sortOrder,
                                $aliasDb,
                                $savedOtaId,
                                $catalogId
                            ));
                            if ($stmtUpsert->rowCount() <= 0) {
                                $stmtInsert->execute(array(
                                    $savedOtaId,
                                    $aliasDb,
                                    $sortOrder,
                                    $companyId,
                                    $catalogId
                                ));
                            }
                        }
                    }
                } catch (Exception $e) {
                    throw new Exception('No se pudo guardar la configuracion informativa OTA: ' . $e->getMessage());
                }
            }
            $selectedTabId = $savedOtaId;
            $message = 'Cuenta OTA guardada.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['ota_action']) && $_POST['ota_action'] === 'delete_ota_account') {
    pms_require_permission('otas.edit');
    $otaAccountId = isset($_POST['ota_account_id']) ? (int)$_POST['ota_account_id'] : 0;
    if ($otaAccountId <= 0) {
        $error = 'Selecciona una OTA valida para eliminar.';
    } else {
        try {
            pms_call_procedure('sp_ota_account_upsert', array(
                'delete',
                $otaAccountId,
                $companyCode,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                0,
                $actorUserId
            ));
            $selectedTabId = 0;
            $message = 'Cuenta OTA eliminada.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$lodgingConcepts = array();
$infoLineItemConcepts = array();
$serviceFeePaymentConcepts = array();
try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT DISTINCT
            lic.id_line_item_catalog AS id_sale_item_catalog,
            lic.item_name,
            cat.category_name AS category,
            COALESCE(prop.code, "") AS property_code
         FROM pms_settings_lodging_catalog pslc
         JOIN line_item_catalog lic
           ON lic.id_line_item_catalog = pslc.id_sale_item_catalog
          AND lic.deleted_at IS NULL
          AND lic.is_active = 1
          AND lic.catalog_type = "sale_item"
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.id_company = ?
          AND cat.deleted_at IS NULL
          AND cat.is_active = 1
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE pslc.id_company = ?
           AND pslc.deleted_at IS NULL
           AND pslc.is_active = 1
         ORDER BY cat.category_name, lic.item_name'
    );
    $stmt->execute(array($companyId, $companyId));
    $lodgingConcepts = $stmt->fetchAll();
} catch (Exception $e) {
    $lodgingConcepts = array();
}

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
          AND cat.is_active = 1
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE lic.deleted_at IS NULL
           AND lic.is_active = 1
         ORDER BY cat.category_name, lic.item_name'
    );
    $stmt->execute(array($companyId));
    $infoLineItemConcepts = $stmt->fetchAll();
} catch (Exception $e) {
    $infoLineItemConcepts = array();
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            lic.id_line_item_catalog,
            lic.item_name,
            cat.category_name AS category,
            COALESCE(prop.code, "") AS property_code
         FROM line_item_catalog lic
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.id_company = ?
          AND cat.deleted_at IS NULL
          AND cat.is_active = 1
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE lic.deleted_at IS NULL
           AND lic.is_active = 1
           AND lic.catalog_type = "obligation"
         ORDER BY cat.category_name, lic.item_name'
    );
    $stmt->execute(array($companyId));
    $serviceFeePaymentConcepts = $stmt->fetchAll();
} catch (Exception $e) {
    $serviceFeePaymentConcepts = array();
}

$otaAccounts = array();
$otaLodgingByAccount = array();
$otaInfoByAccount = array();
try {
    $otaSets = pms_call_procedure('sp_ota_account_data', array(
        $companyCode,
        null,
        0,
        0
    ));
    $otaAccounts = isset($otaSets[0]) ? $otaSets[0] : array();
    if ($otaAccounts && function_exists('pms_ota_account_has_color_hex_column')) {
        $pdoColor = pms_get_connection();
        if (pms_ota_account_has_color_hex_column($pdoColor)) {
            $stmtColor = $pdoColor->prepare(
                'SELECT id_ota_account, COALESCE(NULLIF(TRIM(color_hex), \'\'), \'\') AS color_hex
                 FROM ota_account
                 WHERE id_company = ?'
            );
            $stmtColor->execute(array($companyId));
            $colorsById = array();
            foreach ($stmtColor->fetchAll() as $colorRow) {
                $colorId = isset($colorRow['id_ota_account']) ? (int)$colorRow['id_ota_account'] : 0;
                if ($colorId <= 0) {
                    continue;
                }
                $colorsById[$colorId] = isset($colorRow['color_hex']) ? (string)$colorRow['color_hex'] : '';
            }
            foreach ($otaAccounts as $idx => $otaRow) {
                $rowId = isset($otaRow['id_ota_account']) ? (int)$otaRow['id_ota_account'] : 0;
                if ($rowId > 0 && isset($colorsById[$rowId])) {
                    $otaAccounts[$idx]['color_hex'] = $colorsById[$rowId];
                }
            }
        }
    }
    $otaLodgingRows = isset($otaSets[1]) ? $otaSets[1] : array();
    foreach ($otaLodgingRows as $row) {
        $oid = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
        $cid = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($oid <= 0 || $cid <= 0) {
            continue;
        }
        if (!isset($otaLodgingByAccount[$oid])) {
            $otaLodgingByAccount[$oid] = array();
        }
        $otaLodgingByAccount[$oid][$cid] = true;
    }
} catch (Exception $e) {
    $error = $error ?: ('No se pudieron cargar las cuentas OTA: ' . $e->getMessage());
    $otaAccounts = array();
    $otaLodgingByAccount = array();
}

try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            oaic.id_ota_account,
            oaic.id_line_item_catalog,
            oaic.sort_order,
            oaic.display_alias AS display_alias
         FROM ota_account_info_catalog oaic
         JOIN ota_account oa
           ON oa.id_ota_account = oaic.id_ota_account
          AND oa.deleted_at IS NULL
          AND oa.is_active = 1
         WHERE oa.id_company = ?
           AND oaic.deleted_at IS NULL
           AND oaic.is_active = 1
         ORDER BY oaic.sort_order, oaic.id_line_item_catalog'
    );
    $stmt->execute(array($companyId));
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $oid = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
        $cid = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
        if ($oid <= 0 || $cid <= 0) {
            continue;
        }
        if (!isset($otaInfoByAccount[$oid])) {
            $otaInfoByAccount[$oid] = array();
        }
        $otaInfoByAccount[$oid][] = array(
            'catalog_id' => $cid,
            'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
            'alias' => isset($row['display_alias']) ? (string)$row['display_alias'] : ''
        );
    }
} catch (Exception $e) {
    $otaInfoByAccount = array();
}

$otaAccountsById = array();
foreach ($otaAccounts as $oa) {
    $oid = isset($oa['id_ota_account']) ? (int)$oa['id_ota_account'] : 0;
    if ($oid > 0) {
        $otaAccountsById[$oid] = $oa;
    }
}

if ($forceNew) {
    $selectedTabId = 0;
}
if ($selectedTabId > 0 && !isset($otaAccountsById[$selectedTabId])) {
    $selectedTabId = 0;
}
if ($selectedTabId <= 0 && !$forceNew && !empty($otaAccounts)) {
    $selectedTabId = isset($otaAccounts[0]['id_ota_account']) ? (int)$otaAccounts[0]['id_ota_account'] : 0;
}

$otaForm = array(
    'id_ota_account' => 0,
    'property_code' => $defaultPropertyCode,
    'platform' => 'other',
    'ota_name' => '',
    'external_code' => '',
    'contact_email' => '',
    'timezone' => 'America/Mexico_City',
    'color_hex' => '#64748B',
    'notes' => '',
    'service_fee_payment_catalog_id' => 0,
    'is_active' => 1,
    'lodging_catalog_ids' => array(),
    'info_rows' => array()
);

if ($selectedTabId > 0 && isset($otaAccountsById[$selectedTabId])) {
    $oa = $otaAccountsById[$selectedTabId];
    $otaForm['id_ota_account'] = $selectedTabId;
    $otaForm['property_code'] = isset($oa['property_code']) ? strtoupper((string)$oa['property_code']) : $defaultPropertyCode;
    $otaForm['platform'] = isset($oa['platform']) ? strtolower((string)$oa['platform']) : 'other';
    $otaForm['ota_name'] = isset($oa['ota_name']) ? (string)$oa['ota_name'] : '';
    $otaForm['external_code'] = isset($oa['external_code']) ? (string)$oa['external_code'] : '';
    $otaForm['contact_email'] = isset($oa['contact_email']) ? (string)$oa['contact_email'] : '';
    $otaForm['timezone'] = isset($oa['timezone']) ? (string)$oa['timezone'] : 'America/Mexico_City';
    $otaForm['color_hex'] = ota_normalize_color_hex(isset($oa['color_hex']) ? (string)$oa['color_hex'] : '') ?: '#64748B';
    $otaForm['notes'] = isset($oa['notes']) ? (string)$oa['notes'] : '';
    $otaForm['service_fee_payment_catalog_id'] = isset($oa['id_service_fee_payment_catalog'])
        ? (int)$oa['id_service_fee_payment_catalog']
        : 0;
    $otaForm['is_active'] = isset($oa['is_active']) ? (int)$oa['is_active'] : 1;
    $otaForm['lodging_catalog_ids'] = isset($otaLodgingByAccount[$selectedTabId]) ? array_keys($otaLodgingByAccount[$selectedTabId]) : array();
    $otaForm['info_rows'] = isset($otaInfoByAccount[$selectedTabId]) ? $otaInfoByAccount[$selectedTabId] : array();
}

if (isset($_POST['ota_action']) && $_POST['ota_action'] === 'save_ota_account' && $error) {
    $otaForm['id_ota_account'] = isset($_POST['ota_account_id']) ? (int)$_POST['ota_account_id'] : 0;
    $otaForm['property_code'] = isset($_POST['ota_property_code_internal'])
        ? strtoupper(trim((string)$_POST['ota_property_code_internal']))
        : $defaultPropertyCode;
    $otaForm['platform'] = isset($_POST['ota_platform']) ? strtolower(trim((string)$_POST['ota_platform'])) : 'other';
    $otaForm['ota_name'] = isset($_POST['ota_name']) ? (string)$_POST['ota_name'] : '';
    $otaForm['external_code'] = isset($_POST['ota_external_code']) ? (string)$_POST['ota_external_code'] : '';
    $otaForm['contact_email'] = isset($_POST['ota_contact_email']) ? (string)$_POST['ota_contact_email'] : '';
    $otaForm['timezone'] = isset($_POST['ota_timezone']) ? (string)$_POST['ota_timezone'] : 'America/Mexico_City';
    $otaForm['color_hex'] = ota_normalize_color_hex(isset($_POST['ota_color_hex']) ? (string)$_POST['ota_color_hex'] : '') ?: '#64748B';
    $otaForm['notes'] = isset($_POST['ota_notes']) ? (string)$_POST['ota_notes'] : '';
    $otaForm['service_fee_payment_catalog_id'] = isset($_POST['ota_service_fee_payment_catalog_id'])
        ? (int)$_POST['ota_service_fee_payment_catalog_id']
        : 0;
    $otaForm['is_active'] = isset($_POST['ota_is_active']) ? 1 : 0;
    $otaForm['lodging_catalog_ids'] = isset($_POST['ota_lodging_catalog_ids']) && is_array($_POST['ota_lodging_catalog_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $_POST['ota_lodging_catalog_ids']), function ($id) { return $id > 0; })))
        : array();
    $otaInfoCatalogIdsRaw = isset($_POST['ota_info_catalog_id']) && is_array($_POST['ota_info_catalog_id'])
        ? $_POST['ota_info_catalog_id']
        : array();
    $otaInfoAliasRaw = isset($_POST['ota_info_alias']) && is_array($_POST['ota_info_alias'])
        ? $_POST['ota_info_alias']
        : array();
    $tmpInfoRows = array();
    $tmpSeen = array();
    foreach ($otaInfoCatalogIdsRaw as $idx => $catalogRaw) {
        $catalogId = (int)$catalogRaw;
        if ($catalogId <= 0 || isset($tmpSeen[$catalogId])) {
            continue;
        }
        $alias = isset($otaInfoAliasRaw[$idx]) ? trim((string)$otaInfoAliasRaw[$idx]) : '';
        if (function_exists('mb_substr')) {
            $alias = mb_substr($alias, 0, 160);
        } else {
            $alias = substr($alias, 0, 160);
        }
        $tmpInfoRows[] = array(
            'catalog_id' => $catalogId,
            'alias' => $alias,
            'sort_order' => count($tmpInfoRows) + 1
        );
        $tmpSeen[$catalogId] = true;
    }
    $otaForm['info_rows'] = $tmpInfoRows;
    $selectedTabId = (int)$otaForm['id_ota_account'];
}
?>
<div class="page-header">
  <h2>OTAs</h2>
  <p class="muted">Registra OTAs de forma centralizada y configura sus conceptos de hospedaje.</p>
  <p><a class="button-secondary" href="index.php?view=ota_ical">Abrir iCal OTAs</a></p>
</div>

<?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php if ($message): ?><p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php
  $lodgingOptionMap = array();
  foreach ($lodgingConcepts as $c) {
      $id = isset($c['id_sale_item_catalog']) ? (int)$c['id_sale_item_catalog'] : 0;
      if ($id <= 0) {
          continue;
      }
      $label = isset($c['item_name']) ? (string)$c['item_name'] : '';
      $cat = isset($c['category']) ? (string)$c['category'] : '';
      $prop = isset($c['property_code']) ? (string)$c['property_code'] : '';
      $lodgingOptionMap[$id] = ($prop !== '' ? $prop . ' - ' : '') . ($cat !== '' ? $cat . ' / ' : '') . $label;
  }
  $infoOptionMap = array();
  foreach ($infoLineItemConcepts as $c) {
      $id = isset($c['id_line_item_catalog']) ? (int)$c['id_line_item_catalog'] : 0;
      if ($id <= 0) {
          continue;
      }
      $label = isset($c['item_name']) ? (string)$c['item_name'] : '';
      $cat = isset($c['category']) ? (string)$c['category'] : '';
      $prop = isset($c['property_code']) ? (string)$c['property_code'] : '';
      $type = isset($c['catalog_type']) ? strtoupper((string)$c['catalog_type']) : '';
      $infoOptionMap[$id] = ($prop !== '' ? $prop . ' - ' : '') . ($cat !== '' ? $cat . ' / ' : '') . $label . ($type !== '' ? (' [' . $type . ']') : '');
  }
  $serviceFeePaymentOptionMap = array();
  foreach ($serviceFeePaymentConcepts as $c) {
      $id = isset($c['id_line_item_catalog']) ? (int)$c['id_line_item_catalog'] : 0;
      if ($id <= 0) {
          continue;
      }
      $label = isset($c['item_name']) ? (string)$c['item_name'] : '';
      $cat = isset($c['category']) ? (string)$c['category'] : '';
      $prop = isset($c['property_code']) ? (string)$c['property_code'] : '';
      $serviceFeePaymentOptionMap[$id] = ($prop !== '' ? $prop . ' - ' : '') . ($cat !== '' ? $cat . ' / ' : '') . $label;
  }
?>

<div class="panel">
  <h3>OTAs registradas</h3>
  <div class="ota-tabs">
    <a class="ota-tab <?php echo (int)$otaForm['id_ota_account'] === 0 ? 'is-active' : ''; ?>" href="index.php?view=otas&ota_new=1">+ Nueva OTA</a>
    <?php foreach ($otaAccounts as $oa): ?>
      <?php
        $oaId = isset($oa['id_ota_account']) ? (int)$oa['id_ota_account'] : 0;
        if ($oaId <= 0) {
            continue;
        }
        $oaName = isset($oa['ota_name']) && trim((string)$oa['ota_name']) !== ''
            ? (string)$oa['ota_name']
            : ('OTA #' . $oaId);
      ?>
      <a class="ota-tab <?php echo (int)$otaForm['id_ota_account'] === $oaId ? 'is-active' : ''; ?>" href="index.php?view=otas&ota_edit=<?php echo (int)$oaId; ?>">
        <?php echo htmlspecialchars($oaName, ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h3><?php echo (int)$otaForm['id_ota_account'] > 0 ? 'Editar OTA' : 'Nueva OTA'; ?></h3>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="ota_action" value="save_ota_account">
    <input type="hidden" name="ota_selected_id" value="<?php echo (int)$otaForm['id_ota_account']; ?>">
    <input type="hidden" name="ota_account_id" value="<?php echo (int)$otaForm['id_ota_account']; ?>">
    <input type="hidden" name="ota_property_code_internal" value="<?php echo htmlspecialchars((string)$otaForm['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="ota_platform" value="<?php echo htmlspecialchars((string)$otaForm['platform'], ENT_QUOTES, 'UTF-8'); ?>">

    <label>Nombre OTA
      <input type="text" name="ota_name" maxlength="150" required value="<?php echo htmlspecialchars((string)$otaForm['ota_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Booking Casa Triz">
    </label>
    <label>Codigo externo
      <input type="text" name="ota_external_code" maxlength="120" value="<?php echo htmlspecialchars((string)$otaForm['external_code'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Hotel ID, Partner ID, etc.">
    </label>
    <label>Correo de contacto
      <input type="email" name="ota_contact_email" maxlength="190" value="<?php echo htmlspecialchars((string)$otaForm['contact_email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="soporte@ota.com">
    </label>
    <label>Zona horaria
      <input type="text" name="ota_timezone" maxlength="64" value="<?php echo htmlspecialchars((string)$otaForm['timezone'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="America/Mexico_City">
    </label>
    <label>Color en calendario
      <input type="color" name="ota_color_hex" value="<?php echo htmlspecialchars((string)$otaForm['color_hex'], ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Concepto obligacion tarifa al anfitrion
      <select name="ota_service_fee_payment_catalog_id">
        <option value="">(Sin configurar)</option>
        <?php foreach ($serviceFeePaymentOptionMap as $id => $full): ?>
          <option value="<?php echo (int)$id; ?>" <?php echo (int)$otaForm['service_fee_payment_catalog_id'] === (int)$id ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="full">Notas
      <textarea name="ota_notes" rows="2" placeholder="Notas operativas internas"><?php echo htmlspecialchars((string)$otaForm['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
    </label>
    <label class="checkbox-inline">
      <input type="checkbox" name="ota_is_active" value="1" <?php echo (int)$otaForm['is_active'] !== 0 ? 'checked' : ''; ?>>
      Cuenta activa
    </label>

    <label class="full">Conceptos de hospedaje vinculados</label>
    <div class="full ota-field-block">
      <?php if (!$lodgingOptionMap): ?>
        <p class="muted">Sin conceptos de hospedaje registrados en configuracion.</p>
      <?php else: ?>
        <select class="ota-multi-select" name="ota_lodging_catalog_ids[]" multiple size="10">
          <?php foreach ($lodgingOptionMap as $id => $full): ?>
            <option value="<?php echo (int)$id; ?>" <?php echo in_array((int)$id, (array)$otaForm['lodging_catalog_ids'], true) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="muted tiny">Tip: usa Ctrl/Cmd + click para seleccionar varios conceptos.</p>
      <?php endif; ?>
    </div>

    <label class="full">Conceptos para cuadro informativo (con alias)</label>
    <div class="full ota-field-block">
      <?php if (!$infoOptionMap): ?>
        <p class="muted">Sin conceptos disponibles.</p>
      <?php else: ?>
        <div class="ota-info-row-add">
          <select id="ota-info-new-catalog">
            <option value="">Selecciona concepto...</option>
            <?php foreach ($infoOptionMap as $id => $full): ?>
              <option value="<?php echo (int)$id; ?>"><?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" id="ota-info-new-alias" maxlength="160" placeholder="Alias (opcional)">
          <button type="button" class="button-secondary" id="ota-info-add-btn">Agregar</button>
        </div>
        <div id="ota-info-rows" class="ota-info-rows">
          <?php foreach ((array)$otaForm['info_rows'] as $row): ?>
            <?php
              $catalogId = isset($row['catalog_id']) ? (int)$row['catalog_id'] : 0;
              if ($catalogId <= 0) {
                  continue;
              }
              $alias = isset($row['alias']) ? (string)$row['alias'] : '';
            ?>
            <div class="ota-info-row" data-catalog-id="<?php echo (int)$catalogId; ?>">
              <input type="hidden" name="ota_info_catalog_id[]" value="<?php echo (int)$catalogId; ?>">
              <div class="ota-info-row-title"><?php echo htmlspecialchars(isset($infoOptionMap[$catalogId]) ? $infoOptionMap[$catalogId] : ('Concepto #' . $catalogId), ENT_QUOTES, 'UTF-8'); ?></div>
              <input type="text" name="ota_info_alias[]" maxlength="160" placeholder="Alias en reservacion" value="<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>">
              <button type="button" class="button-secondary ota-info-remove">Quitar</button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="form-actions full ota-form-actions">
      <button type="submit"><?php echo (int)$otaForm['id_ota_account'] > 0 ? 'Actualizar OTA' : 'Guardar OTA'; ?></button>
      <?php if ((int)$otaForm['id_ota_account'] > 0): ?>
        <a class="button-secondary" href="index.php?view=otas&ota_new=1">Nueva OTA</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ((int)$otaForm['id_ota_account'] > 0): ?>
    <form method="post" class="inline-form" onsubmit="return confirm('Eliminar esta OTA?');" style="margin-top:8px;">
      <input type="hidden" name="ota_action" value="delete_ota_account">
      <input type="hidden" name="ota_account_id" value="<?php echo (int)$otaForm['id_ota_account']; ?>">
      <input type="hidden" name="ota_selected_id" value="<?php echo (int)$otaForm['id_ota_account']; ?>">
      <button type="submit" class="button-secondary">Eliminar OTA</button>
    </form>
  <?php endif; ?>
</div>

<style>
.page-header h2 { margin: 0 0 4px 0; }
.page-header p { margin: 0; }
.panel { border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 14px; margin-top: 12px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid .full { grid-column: 1 / -1; }
.form-actions.full { grid-column: 1 / -1; }
.ota-form-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.ota-tabs { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.ota-tab {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 999px;
  color: #dceeff;
  text-decoration: none;
  background: rgba(10,18,30,0.5);
}
.ota-tab.is-active {
  border-color: #36d7f0;
  box-shadow: inset 0 0 0 1px rgba(54,215,240,0.35);
  background: rgba(20,35,58,0.9);
}
.ota-field-block { display: grid; gap: 8px; }
.ota-multi-select {
  width: 100%;
  min-height: 180px;
  border-radius: 10px;
  border: 1px solid rgba(120, 170, 215, 0.35);
  background: rgba(7, 18, 35, 0.65);
  color: #d8ecff;
  padding: 8px;
}
.ota-info-row-add {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}
.ota-info-rows {
  display: grid;
  gap: 8px;
}
.ota-info-row {
  display: grid;
  grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  padding: 8px;
  border: 1px solid rgba(120, 170, 215, 0.25);
  border-radius: 10px;
  background: rgba(5, 16, 31, 0.5);
}
.ota-info-row-title {
  color: #dff2ff;
  font-size: 0.9rem;
}
.tiny {
  font-size: 0.82rem;
  opacity: 0.85;
}
.panel textarea { width: 100%; min-height: 74px; resize: vertical; }
@media (max-width: 980px) {
  .ota-info-row-add,
  .ota-info-row {
    grid-template-columns: 1fr;
  }
}
</style>
<script>
(function () {
  var rowsWrap = document.getElementById('ota-info-rows');
  var selectNew = document.getElementById('ota-info-new-catalog');
  var aliasNew = document.getElementById('ota-info-new-alias');
  var addBtn = document.getElementById('ota-info-add-btn');
  if (!rowsWrap || !selectNew || !addBtn) {
    return;
  }

  function hasCatalog(catalogId) {
    return !!rowsWrap.querySelector('.ota-info-row[data-catalog-id="' + String(catalogId) + '"]');
  }

  function createRow(catalogId, label, alias) {
    var row = document.createElement('div');
    row.className = 'ota-info-row';
    row.setAttribute('data-catalog-id', String(catalogId));

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'ota_info_catalog_id[]';
    hidden.value = String(catalogId);
    row.appendChild(hidden);

    var title = document.createElement('div');
    title.className = 'ota-info-row-title';
    title.textContent = label || ('Concepto #' + String(catalogId));
    row.appendChild(title);

    var aliasInput = document.createElement('input');
    aliasInput.type = 'text';
    aliasInput.name = 'ota_info_alias[]';
    aliasInput.maxLength = 160;
    aliasInput.placeholder = 'Alias en reservacion';
    aliasInput.value = alias || '';
    row.appendChild(aliasInput);

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button-secondary ota-info-remove';
    remove.textContent = 'Quitar';
    row.appendChild(remove);

    return row;
  }

  addBtn.addEventListener('click', function () {
    var catalogId = parseInt(selectNew.value || '0', 10);
    if (!catalogId || hasCatalog(catalogId)) {
      return;
    }
    var option = selectNew.options[selectNew.selectedIndex];
    var label = option ? option.text : ('Concepto #' + String(catalogId));
    var row = createRow(catalogId, label, aliasNew ? aliasNew.value : '');
    rowsWrap.appendChild(row);
    if (aliasNew) {
      aliasNew.value = '';
    }
    selectNew.value = '';
  });

  rowsWrap.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.ota-info-remove');
    if (!btn) {
      return;
    }
    var row = btn.closest('.ota-info-row');
    if (row) {
      row.remove();
    }
  });
})();
</script>
