<?php
$moduleKey = 'reports';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyCode = (string)$currentUser['company_code'];
$companyId = (int)$currentUser['company_id'];
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : 0;
if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

pms_require_permission('reports.view');

require_once __DIR__ . '/../includes/report_v2_lib.php';

$pdo = pms_get_connection();
$properties = pms_fetch_properties($companyId);
$canDesign = pms_user_can('reports.design');
$canRun = pms_user_can('reports.run');

$missingTables = reports_v2_tables_ready($pdo);
if (!empty($missingTables)) {
    echo '<section class="card">';
    echo '<h2>Reportes v2 no instalado</h2>';
    echo '<p class="error">Faltan tablas del sistema nuevo de reportes: ' . reports_v2_h(implode(', ', $missingTables)) . '.</p>';
    echo '<p>Aplica primero la migracion <code>bd pms/migrate_reports_v2_templates.sql</code>.</p>';
    echo '</section>';
    return;
}

$lineItemCatalogs = reports_v2_fetch_line_item_catalogs($pdo, $companyId);
$variableCatalog = reports_v2_build_variable_catalog($lineItemCatalogs);
$displayVariableCatalog = $variableCatalog;
$reservationFieldCatalog = pms_report_reservation_field_catalog();
$metricOptions = reports_v2_metric_options();
$formatOptions = reports_v2_format_options();

$messages = array();
$errors = array();
$selectedTemplateId = isset($_REQUEST['selected_report_template_id']) ? (int)$_REQUEST['selected_report_template_id'] : 0;
$selectedCalculationId = isset($_REQUEST['selected_report_calculation_id']) ? (int)$_REQUEST['selected_report_calculation_id'] : 0;
$selectedFieldId = isset($_REQUEST['selected_report_field_id']) ? (int)$_REQUEST['selected_report_field_id'] : 0;
$activeTab = isset($_REQUEST['reports_tab']) ? trim((string)$_REQUEST['reports_tab']) : 'run';
$runFilters = array(
    'property_code' => isset($_REQUEST['report_property_code']) ? trim((string)$_REQUEST['report_property_code']) : '',
    'status' => isset($_REQUEST['report_status']) ? trim((string)$_REQUEST['report_status']) : 'activas',
    'date_from' => isset($_REQUEST['report_date_from']) ? trim((string)$_REQUEST['report_date_from']) : date('Y-m-01'),
    'date_to' => isset($_REQUEST['report_date_to']) ? trim((string)$_REQUEST['report_date_to']) : date('Y-m-t'),
    'search' => isset($_REQUEST['report_search']) ? trim((string)$_REQUEST['report_search']) : '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)reports_v2_post('reports_action'));
    try {
        if ($action === 'save_template') {
            pms_require_permission('reports.design');

            $templateId = (int)reports_v2_post('template_id', 0);
            $reportName = trim((string)reports_v2_post('template_name'));
            $description = trim((string)reports_v2_post('template_description'));
            $isActive = reports_v2_post('template_is_active', '1') === '1' ? 1 : 0;
            if ($reportName === '') {
                throw new RuntimeException('El nombre de la plantilla es obligatorio.');
            }

            if ($templateId > 0) {
                $existing = reports_v2_fetch_template($pdo, $companyId, $templateId);
                if (!$existing) {
                    throw new RuntimeException('La plantilla seleccionada no existe.');
                }
                $reportKey = reports_v2_build_unique_code($pdo, 'report_template', 'id_report_template', 'report_key', $companyId, $reportName, $templateId);
                $stmt = $pdo->prepare(
                    'UPDATE report_template
                     SET report_key = ?,
                         report_name = ?,
                         description = ?,
                         is_active = ?,
                         updated_at = NOW()
                     WHERE id_report_template = ?
                       AND id_company = ?'
                );
                $stmt->execute(array($reportKey, $reportName, $description !== '' ? $description : null, $isActive, $templateId, $companyId));
                $selectedTemplateId = $templateId;
                $messages[] = 'Plantilla actualizada.';
            } else {
                $reportKey = reports_v2_build_unique_code($pdo, 'report_template', 'id_report_template', 'report_key', $companyId, $reportName, 0);
                $stmt = $pdo->prepare(
                    'INSERT INTO report_template (
                        id_company,
                        report_key,
                        report_name,
                        description,
                        row_source,
                        is_active,
                        created_by,
                        created_at,
                        updated_at
                     ) VALUES (?, ?, ?, ?, \'reservation\', ?, ?, NOW(), NOW())'
                );
                $stmt->execute(array($companyId, $reportKey, $reportName, $description !== '' ? $description : null, $isActive, $actorUserId > 0 ? $actorUserId : null));
                $selectedTemplateId = (int)$pdo->lastInsertId();
                $messages[] = 'Plantilla creada.';
            }
            $activeTab = 'templates';
        } elseif ($action === 'delete_template') {
            pms_require_permission('reports.design');
            $templateId = (int)reports_v2_post('template_id', 0);
            $stmt = $pdo->prepare(
                'UPDATE report_template
                 SET is_active = 0,
                     deleted_at = NOW(),
                     updated_at = NOW()
                 WHERE id_report_template = ?
                   AND id_company = ?
                   AND deleted_at IS NULL'
            );
            $stmt->execute(array($templateId, $companyId));
            $selectedTemplateId = 0;
            $messages[] = 'Plantilla archivada.';
            $activeTab = 'templates';
        } elseif ($action === 'save_calculation') {
            pms_require_permission('reports.design');

            $calcId = (int)reports_v2_post('calculation_id', 0);
            $calcName = trim((string)reports_v2_post('calculation_name'));
            $description = trim((string)reports_v2_post('calculation_description'));
            $expression = trim((string)reports_v2_post('calculation_expression'));
            $formatHint = trim((string)reports_v2_post('calculation_format_hint', 'number'));
            $decimalPlaces = max(0, min(6, (int)reports_v2_post('calculation_decimal_places', 2)));
            $isActive = reports_v2_post('calculation_is_active', '1') === '1' ? 1 : 0;

            if ($calcName === '') {
                throw new RuntimeException('El nombre del calculo es obligatorio.');
            }
            if ($expression === '') {
                throw new RuntimeException('La expresion del calculo es obligatoria.');
            }
            $exprError = '';
            if (!reports_v2_validate_expression($expression, $variableCatalog, $exprError)) {
                throw new RuntimeException($exprError);
            }
            if (!isset($formatOptions[$formatHint]) || $formatHint === 'auto') {
                $formatHint = 'number';
            }

            if ($calcId > 0) {
                $existing = reports_v2_fetch_calculation($pdo, $companyId, $calcId);
                if (!$existing) {
                    throw new RuntimeException('El calculo seleccionado no existe.');
                }
                $calcCode = reports_v2_build_unique_code($pdo, 'report_calculation', 'id_report_calculation', 'calc_code', $companyId, $calcName, $calcId);
                $stmt = $pdo->prepare(
                    'UPDATE report_calculation
                     SET calc_code = ?,
                         calc_name = ?,
                         description = ?,
                         expression_text = ?,
                         format_hint = ?,
                         decimal_places = ?,
                         is_active = ?,
                         updated_at = NOW()
                     WHERE id_report_calculation = ?
                       AND id_company = ?'
                );
                $stmt->execute(array($calcCode, $calcName, $description !== '' ? $description : null, $expression, $formatHint, $decimalPlaces, $isActive, $calcId, $companyId));
                $selectedCalculationId = $calcId;
                $messages[] = 'Calculo actualizado.';
            } else {
                $calcCode = reports_v2_build_unique_code($pdo, 'report_calculation', 'id_report_calculation', 'calc_code', $companyId, $calcName, 0);
                $stmt = $pdo->prepare(
                    'INSERT INTO report_calculation (
                        id_company,
                        calc_code,
                        calc_name,
                        description,
                        expression_text,
                        format_hint,
                        decimal_places,
                        is_active,
                        created_by,
                        created_at,
                        updated_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute(array($companyId, $calcCode, $calcName, $description !== '' ? $description : null, $expression, $formatHint, $decimalPlaces, $isActive, $actorUserId > 0 ? $actorUserId : null));
                $selectedCalculationId = (int)$pdo->lastInsertId();
                $messages[] = 'Calculo creado.';
            }
            $activeTab = 'calculations';
        } elseif ($action === 'delete_calculation') {
            pms_require_permission('reports.design');
            $calcId = (int)reports_v2_post('calculation_id', 0);
            $stmt = $pdo->prepare(
                'UPDATE report_calculation
                 SET is_active = 0,
                     deleted_at = NOW(),
                     updated_at = NOW()
                 WHERE id_report_calculation = ?
                   AND id_company = ?
                   AND deleted_at IS NULL'
            );
            $stmt->execute(array($calcId, $companyId));
            $selectedCalculationId = 0;
            $messages[] = 'Calculo archivado.';
            $activeTab = 'calculations';
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$templates = reports_v2_fetch_templates($pdo, $companyId);
if ($selectedTemplateId <= 0 && !empty($templates)) {
    $selectedTemplateId = isset($templates[0]['id_report_template']) ? (int)$templates[0]['id_report_template'] : 0;
}
$selectedTemplate = $selectedTemplateId > 0 ? reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId) : null;
$templateFields = $selectedTemplate ? reports_v2_fetch_template_fields($pdo, $selectedTemplateId) : array();
$displayVariableCatalog = reports_v2_build_variable_catalog(
    reports_v2_filter_line_item_catalogs_for_template($lineItemCatalogs, $templateFields)
);
$nextFieldOrder = 3;
if (!empty($templateFields)) {
    $maxFieldOrder = 0;
    foreach ($templateFields as $fieldRow) {
        $rowOrder = isset($fieldRow['order_index']) ? (int)$fieldRow['order_index'] : 0;
        if ($rowOrder > $maxFieldOrder) {
            $maxFieldOrder = $rowOrder;
        }
    }
    $nextFieldOrder = $maxFieldOrder + 3;
}
$selectedField = null;
if ($selectedFieldId > 0 && !empty($templateFields)) {
    foreach ($templateFields as $fieldRow) {
        if ((int)(isset($fieldRow['id_report_template_field']) ? $fieldRow['id_report_template_field'] : 0) === $selectedFieldId) {
            $selectedField = $fieldRow;
            break;
        }
    }
}
if (!$selectedField) {
    $selectedFieldId = 0;
}

$calculations = reports_v2_fetch_calculations($pdo, $companyId);
$selectedCalculation = $selectedCalculationId > 0 ? reports_v2_fetch_calculation($pdo, $companyId, $selectedCalculationId) : null;
if (!$selectedCalculation && !empty($calculations)) {
    $selectedCalculationId = isset($calculations[0]['id_report_calculation']) ? (int)$calculations[0]['id_report_calculation'] : 0;
    $selectedCalculation = $selectedCalculationId > 0 ? reports_v2_fetch_calculation($pdo, $companyId, $selectedCalculationId) : null;
}
$calculationsById = array();
foreach ($calculations as $calculationRow) {
    $calcId = isset($calculationRow['id_report_calculation']) ? (int)$calculationRow['id_report_calculation'] : 0;
    if ($calcId > 0) {
        $calculationsById[$calcId] = $calculationRow;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)reports_v2_post('reports_action'));
    try {
        if ($action === 'save_field') {
            pms_require_permission('reports.design');

            $fieldId = (int)reports_v2_post('field_id', 0);
            $templateId = (int)reports_v2_post('template_id', 0);
            $fieldType = trim((string)reports_v2_post('field_type'));
            $displayName = trim((string)reports_v2_post('display_name'));
            $reservationFieldCode = trim((string)reports_v2_post('reservation_field_code'));
            $lineItemCatalogId = (int)reports_v2_post('line_item_catalog_id', 0);
            $lineItemCatalogIds = reports_v2_post('line_item_catalog_ids', array());
            if (!is_array($lineItemCatalogIds)) {
                $lineItemCatalogIds = array();
            }
            $sourceMetric = trim((string)reports_v2_post('source_metric', 'amount_cents'));
            $reportCalculationId = (int)reports_v2_post('report_calculation_id', 0);
            $formatHint = trim((string)reports_v2_post('field_format_hint', 'auto'));
            $defaultValue = trim((string)reports_v2_post('field_default_value'));
            $allowMultipleCatalogs = reports_v2_post('field_allow_multiple_catalogs', '0') === '1' ? 1 : 0;
            $orderIndex = max(1, (int)reports_v2_post('order_index', 1));
            $isVisible = reports_v2_post('field_is_visible', '1') === '1' ? 1 : 0;
            $isActive = reports_v2_post('field_is_active', '1') === '1' ? 1 : 0;

            $template = reports_v2_fetch_template($pdo, $companyId, $templateId);
            if (!$template) {
                throw new RuntimeException('Selecciona una plantilla valida.');
            }
            if (!isset(reports_v2_field_type_options()[$fieldType])) {
                throw new RuntimeException('Tipo de campo no valido.');
            }
            if (!isset($formatOptions[$formatHint])) {
                $formatHint = 'auto';
            }
            if (!isset($metricOptions[$sourceMetric])) {
                $sourceMetric = 'amount_cents';
            }
            if ($fieldType !== 'line_item') {
                $allowMultipleCatalogs = 0;
            }
            if ($fieldType === 'line_item' && $allowMultipleCatalogs && !reports_v2_template_field_catalog_table_ready($pdo)) {
                throw new RuntimeException('Aplica la migracion bd pms/migrate_report_template_field_multi_catalog.sql para usar multiples conceptos por campo.');
            }

            if ($fieldType === 'reservation') {
                if (!isset($reservationFieldCatalog[$reservationFieldCode])) {
                    throw new RuntimeException('Selecciona un campo de reservacion valido.');
                }
                if ($displayName === '') {
                    $displayName = $reservationFieldCatalog[$reservationFieldCode]['label'];
                }
                $lineItemCatalogId = 0;
                $reportCalculationId = 0;
                $sourceMetric = null;
            } elseif ($fieldType === 'line_item') {
                $selectedCatalogIds = array();
                if ($allowMultipleCatalogs) {
                    foreach ($lineItemCatalogIds as $catalogId) {
                        $catalogId = (int)$catalogId;
                        if ($catalogId > 0 && !in_array($catalogId, $selectedCatalogIds, true)) {
                            $selectedCatalogIds[] = $catalogId;
                        }
                    }
                    if ($lineItemCatalogId > 0 && !in_array($lineItemCatalogId, $selectedCatalogIds, true)) {
                        $selectedCatalogIds[] = $lineItemCatalogId;
                    }
                } elseif ($lineItemCatalogId > 0) {
                    $selectedCatalogIds[] = $lineItemCatalogId;
                }
                $catalogById = array();
                foreach ($lineItemCatalogs as $catalogRow) {
                    $catalogId = isset($catalogRow['id_line_item_catalog']) ? (int)$catalogRow['id_line_item_catalog'] : 0;
                    if ($catalogId > 0) {
                        $catalogById[$catalogId] = $catalogRow;
                    }
                }
                $selectedCatalogs = array();
                foreach ($selectedCatalogIds as $catalogId) {
                    if (isset($catalogById[$catalogId])) {
                        $selectedCatalogs[] = $catalogById[$catalogId];
                    }
                }
                if (empty($selectedCatalogs)) {
                    throw new RuntimeException('Selecciona un line item valido.');
                }
                if ($displayName === '') {
                    if (count($selectedCatalogs) === 1) {
                        $displayName = trim((string)$selectedCatalogs[0]['item_name']) . ' / ' . $metricOptions[$sourceMetric]['label'];
                    } else {
                        $displayName = 'Multiples conceptos / ' . $metricOptions[$sourceMetric]['label'];
                    }
                }
                $lineItemCatalogId = (int)$selectedCatalogIds[0];
                $reservationFieldCode = null;
                $reportCalculationId = 0;
            } else {
                $calcRow = reports_v2_fetch_calculation($pdo, $companyId, $reportCalculationId);
                if (!$calcRow) {
                    throw new RuntimeException('Selecciona un calculo valido.');
                }
                if ($displayName === '') {
                    $displayName = (string)$calcRow['calc_name'];
                }
                $reservationFieldCode = null;
                $lineItemCatalogId = 0;
                $sourceMetric = null;
            }

            if ($fieldId > 0) {
                $hasDefaultValueColumn = reports_v2_template_field_has_default_value_column($pdo);
                $hasAllowMultipleCatalogsColumn = reports_v2_template_field_has_allow_multiple_catalogs_column($pdo);
                $updateSql = 'UPDATE report_template_field
                     SET field_type = ?,
                         display_name = ?';
                $updateParams = array(
                    $fieldType,
                    $displayName,
                );
                if ($hasAllowMultipleCatalogsColumn) {
                    $updateSql .= ',
                         allow_multiple_catalogs = ?';
                    $updateParams[] = $allowMultipleCatalogs;
                }
                $updateSql .= ',
                         reservation_field_code = ?,
                         id_line_item_catalog = ?,
                         id_report_calculation = ?,
                         source_metric = ?,
                         format_hint = ?,
                         order_index = ?,
                         is_visible = ?,
                         is_active = ?';
                $updateParams = array_merge($updateParams, array(
                    $reservationFieldCode !== null && $reservationFieldCode !== '' ? $reservationFieldCode : null,
                    $lineItemCatalogId > 0 ? $lineItemCatalogId : null,
                    $reportCalculationId > 0 ? $reportCalculationId : null,
                    $sourceMetric !== null && $sourceMetric !== '' ? $sourceMetric : null,
                    $formatHint,
                    $orderIndex,
                    $isVisible,
                    $isActive,
                ));
                if ($hasDefaultValueColumn) {
                    $updateSql .= ',
                         default_value = ?';
                    $updateParams[] = $defaultValue !== '' ? $defaultValue : null;
                }
                $updateSql .= ',
                         updated_at = NOW()
                     WHERE id_report_template_field = ?
                       AND id_report_template = ?';
                $updateParams[] = $fieldId;
                $updateParams[] = $templateId;
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute($updateParams);
                $messages[] = 'Campo actualizado.';
                $selectedFieldId = $fieldId;
            } else {
                $hasDefaultValueColumn = reports_v2_template_field_has_default_value_column($pdo);
                $hasAllowMultipleCatalogsColumn = reports_v2_template_field_has_allow_multiple_catalogs_column($pdo);
                $insertColumns = array(
                    'id_report_template',
                    'field_type',
                    'display_name',
                );
                $insertValues = array(
                    $templateId,
                    $fieldType,
                    $displayName,
                );
                if ($hasAllowMultipleCatalogsColumn) {
                    $insertColumns[] = 'allow_multiple_catalogs';
                    $insertValues[] = $allowMultipleCatalogs;
                }
                $insertColumns = array_merge($insertColumns, array(
                    'reservation_field_code',
                    'id_line_item_catalog',
                    'id_report_calculation',
                    'source_metric',
                    'format_hint',
                    'order_index',
                    'is_visible',
                    'is_active',
                    'created_by'
                ));
                $insertValues = array_merge($insertValues, array(
                    $reservationFieldCode !== null && $reservationFieldCode !== '' ? $reservationFieldCode : null,
                    $lineItemCatalogId > 0 ? $lineItemCatalogId : null,
                    $reportCalculationId > 0 ? $reportCalculationId : null,
                    $sourceMetric !== null && $sourceMetric !== '' ? $sourceMetric : null,
                    $formatHint,
                    $orderIndex,
                    $isVisible,
                    $isActive,
                    $actorUserId > 0 ? $actorUserId : null
                ));
                if ($hasDefaultValueColumn) {
                    $insertColumns[] = 'default_value';
                    $insertValues[] = $defaultValue !== '' ? $defaultValue : null;
                }
                $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
                $insertSql = 'INSERT INTO report_template_field (
                        ' . implode(",\n                        ", $insertColumns) . ',
                        created_at,
                        updated_at
                     ) VALUES (' . $placeholders . ', NOW(), NOW())';
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute($insertValues);
                $messages[] = 'Campo agregado.';
                $selectedFieldId = (int)$pdo->lastInsertId();
            }

            if (reports_v2_template_field_catalog_table_ready($pdo) && $selectedFieldId > 0) {
                $deleteLinksStmt = $pdo->prepare(
                    'DELETE FROM report_template_field_catalog
                     WHERE id_report_template_field = ?'
                );
                $deleteLinksStmt->execute(array($selectedFieldId));
                if ($fieldType === 'line_item') {
                    $catalogIdsToPersist = array();
                    if ($allowMultipleCatalogs) {
                        foreach ($lineItemCatalogIds as $catalogId) {
                            $catalogId = (int)$catalogId;
                            if ($catalogId > 0 && !in_array($catalogId, $catalogIdsToPersist, true)) {
                                $catalogIdsToPersist[] = $catalogId;
                            }
                        }
                        if ($lineItemCatalogId > 0 && !in_array($lineItemCatalogId, $catalogIdsToPersist, true)) {
                            $catalogIdsToPersist[] = $lineItemCatalogId;
                        }
                    } elseif ($lineItemCatalogId > 0) {
                        $catalogIdsToPersist[] = $lineItemCatalogId;
                    }
                    if (!empty($catalogIdsToPersist)) {
                        $insertLinkStmt = $pdo->prepare(
                            'INSERT INTO report_template_field_catalog (
                                id_report_template_field,
                                id_line_item_catalog,
                                sort_order,
                                created_at,
                                created_by
                             ) VALUES (?, ?, ?, NOW(), ?)'
                        );
                        foreach (array_values($catalogIdsToPersist) as $sortIndex => $catalogId) {
                            $insertLinkStmt->execute(array(
                                $selectedFieldId,
                                (int)$catalogId,
                                $sortIndex + 1,
                                $actorUserId > 0 ? $actorUserId : null,
                            ));
                        }
                    }
                }
            }

            $selectedTemplateId = $templateId;
            $selectedTemplate = reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId);
            $templateFields = $selectedTemplate ? reports_v2_fetch_template_fields($pdo, $selectedTemplateId) : array();
            $selectedField = null;
            if ($selectedFieldId > 0) {
                foreach ($templateFields as $fieldRow) {
                    if ((int)(isset($fieldRow['id_report_template_field']) ? $fieldRow['id_report_template_field'] : 0) === $selectedFieldId) {
                        $selectedField = $fieldRow;
                        break;
                    }
                }
            }
            $activeTab = 'templates';
        } elseif ($action === 'delete_field') {
            pms_require_permission('reports.design');
            $fieldId = (int)reports_v2_post('field_id', 0);
            $templateId = (int)reports_v2_post('template_id', 0);
            $stmt = $pdo->prepare(
                'UPDATE report_template_field
                 SET is_active = 0,
                     deleted_at = NOW(),
                     updated_at = NOW()
                 WHERE id_report_template_field = ?
                   AND id_report_template = ?
                   AND deleted_at IS NULL'
            );
            $stmt->execute(array($fieldId, $templateId));
            $selectedTemplateId = $templateId;
            $selectedTemplate = reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId);
            $templateFields = $selectedTemplate ? reports_v2_fetch_template_fields($pdo, $selectedTemplateId) : array();
            if ($selectedFieldId === $fieldId) {
                $selectedFieldId = 0;
                $selectedField = null;
            }
            $messages[] = 'Campo archivado.';
            $activeTab = 'templates';
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

$reportRows = array();
$reportTotals = array();
$runTemplate = null;
if ($canRun && $selectedTemplate && !empty($templateFields)) {
    try {
        $runTemplate = $selectedTemplate;
        $baseRows = reports_v2_fetch_report_base_rows($pdo, $companyId, $runFilters, 500);
        $reservationIds = array();
        foreach ($baseRows as $baseRow) {
            $reservationIds[] = isset($baseRow['id_reservation']) ? (int)$baseRow['id_reservation'] : 0;
        }
        $lineItemMetrics = reports_v2_fetch_line_item_metrics($pdo, $reservationIds);
        list($reportRows, $reportTotals) = reports_v2_build_report_rows($baseRows, $templateFields, $calculationsById, $lineItemMetrics, $variableCatalog);
    } catch (Exception $e) {
        $errors[] = 'No fue posible ejecutar el reporte: ' . $e->getMessage();
    }
}
?>
<style>
  .report-v2-page { display: grid; gap: 18px; }
  .report-v2-card { border: 1px solid rgba(120, 150, 190, 0.25); border-radius: 18px; padding: 18px; background: rgba(6, 18, 40, 0.7); }
  .report-v2-grid { display: grid; gap: 18px; grid-template-columns: 320px minmax(0, 1fr); }
  .report-v2-card--full { grid-column: 1 / -1; }
  .report-v2-grid-bottom { display: grid; gap: 18px; grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr); }
  .report-v2-list { display: grid; gap: 10px; }
  .report-v2-list-item { border: 1px solid rgba(120, 150, 190, 0.22); border-radius: 14px; padding: 12px; background: rgba(11, 27, 56, 0.72); }
  .report-v2-list-item.is-selected { border-color: rgba(53, 199, 240, 0.55); box-shadow: 0 0 0 1px rgba(53, 199, 240, 0.2) inset; }
  .report-v2-list-item h4 { margin: 0 0 6px; font-size: 15px; }
  .report-v2-muted { opacity: 0.78; font-size: 13px; }
  .report-v2-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .report-v2-stack { display: grid; gap: 12px; }
  .report-v2-form-grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .report-v2-form-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .report-v2-form-grid--4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .report-v2-form-grid > label, .report-v2-stack > label { display: grid; gap: 6px; font-size: 13px; }
  .report-v2-form-grid input, .report-v2-form-grid select, .report-v2-form-grid textarea, .report-v2-stack input, .report-v2-stack select, .report-v2-stack textarea { width: 100%; }
  .report-v2-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: rgba(53, 199, 240, 0.14); border: 1px solid rgba(53, 199, 240, 0.24); font-size: 12px; }
  .report-v2-pill-list { display: flex; flex-wrap: wrap; gap: 8px; }
  .report-v2-table-wrap { overflow: auto; border: 1px solid rgba(120, 150, 190, 0.2); border-radius: 16px; }
  .report-v2-table { width: 100%; border-collapse: collapse; min-width: 920px; }
  .report-v2-table th, .report-v2-table td { padding: 10px 12px; border-bottom: 1px solid rgba(120, 150, 190, 0.14); text-align: left; vertical-align: top; }
  .report-v2-table th { position: sticky; top: 0; background: rgba(17, 34, 64, 0.97); z-index: 1; }
  .report-v2-error-list, .report-v2-message-list { display: grid; gap: 10px; }
  .report-v2-error, .report-v2-message { padding: 12px 14px; border-radius: 14px; }
  .report-v2-error { background: rgba(180, 35, 35, 0.18); border: 1px solid rgba(220, 90, 90, 0.3); }
  .report-v2-message { background: rgba(35, 140, 80, 0.18); border: 1px solid rgba(90, 220, 140, 0.3); }
  .report-v2-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
  .report-v2-tab { padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(120, 150, 190, 0.22); text-decoration: none; color: inherit; background: rgba(11, 27, 56, 0.52); }
  .report-v2-tab.is-active { border-color: rgba(53, 199, 240, 0.55); background: rgba(53, 199, 240, 0.16); }
  .report-v2-code-list { max-height: 240px; overflow: auto; border: 1px solid rgba(120, 150, 190, 0.18); border-radius: 14px; padding: 10px; background: rgba(11, 27, 56, 0.44); }
  .report-v2-code-list button { margin: 0 6px 6px 0; }
  @media (max-width: 1180px) {
    .report-v2-grid, .report-v2-grid-bottom { grid-template-columns: 1fr; }
    .report-v2-form-grid, .report-v2-form-grid--3, .report-v2-form-grid--4 { grid-template-columns: 1fr; }
  }
</style>
<section class="report-v2-page">
  <div class="report-v2-tabs">
    <a class="report-v2-tab <?php echo $activeTab === 'run' ? 'is-active' : ''; ?>" href="<?php echo reports_v2_h('?view=reports&reports_tab=run&selected_report_template_id=' . $selectedTemplateId); ?>">Ejecucion</a>
    <?php if ($canDesign): ?>
      <a class="report-v2-tab <?php echo $activeTab === 'templates' ? 'is-active' : ''; ?>" href="<?php echo reports_v2_h('?view=reports&reports_tab=templates&selected_report_template_id=' . $selectedTemplateId); ?>">Plantillas</a>
      <a class="report-v2-tab <?php echo $activeTab === 'calculations' ? 'is-active' : ''; ?>" href="<?php echo reports_v2_h('?view=reports&reports_tab=calculations&selected_report_template_id=' . $selectedTemplateId . '&selected_report_calculation_id=' . $selectedCalculationId); ?>">Calculos</a>
    <?php endif; ?>
  </div>
  <?php if (!empty($errors)): ?><div class="report-v2-error-list"><?php foreach ($errors as $errorMessage): ?><div class="report-v2-error"><?php echo reports_v2_h($errorMessage); ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php if (!empty($messages)): ?><div class="report-v2-message-list"><?php foreach ($messages as $messageText): ?><div class="report-v2-message"><?php echo reports_v2_h($messageText); ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php require __DIR__ . '/reports_v2_sections.php'; ?>
</section>
<script>
  (function () {
    var fieldType = document.getElementById('reports-v2-field-type');
    if (fieldType) {
      var togglePanels = function () {
        document.querySelectorAll('[data-field-panel]').forEach(function (panel) {
          panel.style.display = panel.getAttribute('data-field-panel') === fieldType.value ? '' : 'none';
        });
      };
      fieldType.addEventListener('change', togglePanels);
      togglePanels();
    }
    var expressionInput = document.getElementById('reports-v2-calc-expression');
    if (expressionInput) {
      document.querySelectorAll('.report-v2-insert-variable').forEach(function (button) {
        button.addEventListener('click', function () {
          var variableCode = button.getAttribute('data-variable') || '';
          if (!variableCode) { return; }
          var start = expressionInput.selectionStart || 0;
          var end = expressionInput.selectionEnd || 0;
          var value = expressionInput.value || '';
          var insertion = variableCode;
          if (value && start > 0 && /[A-Za-z0-9_]/.test(value.charAt(start - 1))) {
            insertion = ' ' + insertion;
          }
          expressionInput.value = value.slice(0, start) + insertion + value.slice(end);
          expressionInput.focus();
          var caret = start + insertion.length;
          expressionInput.setSelectionRange(caret, caret);
        });
      });
    }
  }());
</script>
