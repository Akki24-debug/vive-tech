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
$rowSourceOptions = reports_v2_row_source_options();
$lineItemRowTypeOptions = reports_v2_line_item_row_type_options();
$dateTypeOptions = reports_v2_date_type_options();
$supportsExtendedSourceMetric = reports_v2_template_field_source_metric_supports_extended_values($pdo);
$supportsLineItemRowSource = reports_v2_report_template_row_source_supports_line_item($pdo);
$hasTemplateSubdivideColumn = reports_v2_report_template_has_subdivide_by_field_id_column($pdo);
$hasTemplateSubdivideLevel2Column = reports_v2_report_template_has_subdivide_by_field_id_level_2_column($pdo);
$hasTemplateSubdivideLevel3Column = reports_v2_report_template_has_subdivide_by_field_id_level_3_column($pdo);
$hasTemplateSubdivideShowTotalsLevel1Column = reports_v2_report_template_has_subdivide_show_totals_level_1_column($pdo);
$hasTemplateSubdivideShowTotalsLevel2Column = reports_v2_report_template_has_subdivide_show_totals_level_2_column($pdo);
$hasTemplateSubdivideShowTotalsLevel3Column = reports_v2_report_template_has_subdivide_show_totals_level_3_column($pdo);
$hasFieldCalculateTotalColumn = reports_v2_template_field_has_calculate_total_column($pdo);

$messages = array();
$errors = array();
if (!$supportsExtendedSourceMetric) {
    $errors[] = 'La BD sigue con una definicion vieja en report_template_field.source_metric. Aplica bd pms/migrate_report_template_field_source_metric_varchar.sql. Mientras no lo hagas, metricas line item como Nombre, Tipo y Fecha servicio pueden terminar vacias o degradadas a Monto.';
}
$selectedTemplateId = isset($_REQUEST['selected_report_template_id']) ? (int)$_REQUEST['selected_report_template_id'] : 0;
$selectedCalculationId = isset($_REQUEST['selected_report_calculation_id']) ? (int)$_REQUEST['selected_report_calculation_id'] : 0;
$selectedFieldId = isset($_REQUEST['selected_report_field_id']) ? (int)$_REQUEST['selected_report_field_id'] : 0;
$editReservationId = isset($_REQUEST['report_edit_reservation_id']) ? (int)$_REQUEST['report_edit_reservation_id'] : 0;
$reportGridSubdivideFieldId = isset($_REQUEST['report_grid_subdivide_field_id']) ? (int)$_REQUEST['report_grid_subdivide_field_id'] : 0;
$activeTab = isset($_REQUEST['reports_tab']) ? trim((string)$_REQUEST['reports_tab']) : 'run';
$rowEditDraftValues = array();
$runFilters = array(
    'property_code' => isset($_REQUEST['report_property_code']) ? trim((string)$_REQUEST['report_property_code']) : '',
    'status' => isset($_REQUEST['report_status']) ? trim((string)$_REQUEST['report_status']) : 'activas',
    'date_type' => isset($_REQUEST['report_date_type']) ? trim((string)$_REQUEST['report_date_type']) : 'check_in_date',
    'date_from' => isset($_REQUEST['report_date_from']) ? trim((string)$_REQUEST['report_date_from']) : date('Y-m-01'),
    'date_to' => isset($_REQUEST['report_date_to']) ? trim((string)$_REQUEST['report_date_to']) : date('Y-m-t'),
    'search' => isset($_REQUEST['report_search']) ? trim((string)$_REQUEST['report_search']) : '',
);
if (!isset($dateTypeOptions[$runFilters['date_type']])) {
    $runFilters['date_type'] = 'check_in_date';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)reports_v2_post('reports_action'));
    try {
        if ($action === 'save_template') {
            pms_require_permission('reports.design');

            $templateId = (int)reports_v2_post('template_id', 0);
            $reportName = trim((string)reports_v2_post('template_name'));
            $description = trim((string)reports_v2_post('template_description'));
            $rowSource = trim((string)reports_v2_post('template_row_source', 'reservation'));
            $lineItemTypeScope = trim((string)reports_v2_post('template_line_item_type_scope', ''));
            $subdivideByFieldId = (int)reports_v2_post('template_subdivide_by_field_id', 0);
            $subdivideByFieldIdLevel2 = (int)reports_v2_post('template_subdivide_by_field_id_level_2', 0);
            $subdivideByFieldIdLevel3 = (int)reports_v2_post('template_subdivide_by_field_id_level_3', 0);
            $subdivideShowTotalsLevel1 = reports_v2_post('template_subdivide_show_totals_level_1', '1') === '1' ? 1 : 0;
            $subdivideShowTotalsLevel2 = reports_v2_post('template_subdivide_show_totals_level_2', '1') === '1' ? 1 : 0;
            $subdivideShowTotalsLevel3 = reports_v2_post('template_subdivide_show_totals_level_3', '1') === '1' ? 1 : 0;
            $isActive = reports_v2_post('template_is_active', '1') === '1' ? 1 : 0;
            if ($reportName === '') {
                throw new RuntimeException('El nombre de la plantilla es obligatorio.');
            }
            if (!isset($rowSourceOptions[$rowSource])) {
                $rowSource = 'reservation';
            }
            if ($rowSource !== 'line_item') {
                $lineItemTypeScope = null;
            } else {
                if (!$supportsLineItemRowSource) {
                    throw new RuntimeException('Tu MySQL todavia no soporta line items como objeto por fila. Aplica bd pms/migrate_report_template_line_item_row_source.sql y vuelve a guardar la plantilla.');
                }
                if (!reports_v2_report_template_has_line_item_type_scope_column($pdo)) {
                    throw new RuntimeException('Aplica bd pms/migrate_report_template_line_item_row_source.sql para usar line items como objeto por fila.');
                }
                if (!isset($lineItemRowTypeOptions[$lineItemTypeScope])) {
                    throw new RuntimeException('Selecciona un tipo de line item valido para la plantilla.');
                }
            }
            $templateFieldCatalog = $templateId > 0 ? reports_v2_fetch_template_fields($pdo, $templateId) : array();
            $validateSubdivideField = function ($candidateFieldId) use ($templateFieldCatalog) {
                $candidateFieldId = (int)$candidateFieldId;
                if ($candidateFieldId <= 0) {
                    return true;
                }
                foreach ($templateFieldCatalog as $templateFieldRow) {
                    $templateFieldRowId = isset($templateFieldRow['id_report_template_field']) ? (int)$templateFieldRow['id_report_template_field'] : 0;
                    if ($templateFieldRowId === $candidateFieldId && empty($templateFieldRow['deleted_at'])) {
                        return true;
                    }
                }
                return false;
            };
            if ($subdivideByFieldId > 0 || $subdivideByFieldIdLevel2 > 0 || $subdivideByFieldIdLevel3 > 0) {
                if (!$hasTemplateSubdivideColumn) {
                    throw new RuntimeException('Aplica bd pms/migrate_report_template_subdivide_by_field.sql para usar subdivision por columna.');
                }
            }
            if ($subdivideByFieldId > 0) {
                if (!$validateSubdivideField($subdivideByFieldId)) {
                    throw new RuntimeException('Selecciona una columna valida para la subdivision principal.');
                }
            } else {
                $subdivideByFieldId = null;
                $subdivideByFieldIdLevel2 = null;
                $subdivideByFieldIdLevel3 = null;
            }
            if ($subdivideByFieldIdLevel2 > 0) {
                if (!$hasTemplateSubdivideLevel2Column) {
                    throw new RuntimeException('Aplica la migracion multinivel de subdivision para usar subseccion 2.');
                }
                if (!$validateSubdivideField($subdivideByFieldIdLevel2)) {
                    throw new RuntimeException('Selecciona una columna valida para la subseccion 2.');
                }
                if ((int)$subdivideByFieldIdLevel2 === (int)$subdivideByFieldId) {
                    throw new RuntimeException('La subseccion 2 debe usar una columna distinta a la subdivision principal.');
                }
            } else {
                $subdivideByFieldIdLevel2 = null;
                $subdivideByFieldIdLevel3 = null;
            }
            if ($subdivideByFieldIdLevel3 > 0) {
                if (!$hasTemplateSubdivideLevel3Column) {
                    throw new RuntimeException('Aplica la migracion multinivel de subdivision para usar subseccion 3.');
                }
                if (!$validateSubdivideField($subdivideByFieldIdLevel3)) {
                    throw new RuntimeException('Selecciona una columna valida para la subseccion 3.');
                }
                if (
                    (int)$subdivideByFieldIdLevel3 === (int)$subdivideByFieldId
                    || (int)$subdivideByFieldIdLevel3 === (int)$subdivideByFieldIdLevel2
                ) {
                    throw new RuntimeException('La subseccion 3 debe usar una columna distinta a las anteriores.');
                }
            } else {
                $subdivideByFieldIdLevel3 = null;
            }

            if ($templateId > 0) {
                $existing = reports_v2_fetch_template($pdo, $companyId, $templateId);
                if (!$existing) {
                    throw new RuntimeException('La plantilla seleccionada no existe.');
                }
                $reportKey = reports_v2_build_unique_code($pdo, 'report_template', 'id_report_template', 'report_key', $companyId, $reportName, $templateId);
                $updateSql = 'UPDATE report_template
                     SET report_key = ?,
                         report_name = ?,
                         description = ?,
                         row_source = ?';
                $updateParams = array(
                    $reportKey,
                    $reportName,
                    $description !== '' ? $description : null,
                    $rowSource,
                );
                if (reports_v2_report_template_has_line_item_type_scope_column($pdo)) {
                    $updateSql .= ',
                         line_item_type_scope = ?';
                    $updateParams[] = $lineItemTypeScope !== null && $lineItemTypeScope !== '' ? $lineItemTypeScope : null;
                }
                if ($hasTemplateSubdivideColumn) {
                    $updateSql .= ',
                         subdivide_by_field_id = ?';
                    $updateParams[] = $subdivideByFieldId;
                }
                if ($hasTemplateSubdivideLevel2Column) {
                    $updateSql .= ',
                         subdivide_by_field_id_level_2 = ?';
                    $updateParams[] = $subdivideByFieldIdLevel2;
                }
                if ($hasTemplateSubdivideLevel3Column) {
                    $updateSql .= ',
                         subdivide_by_field_id_level_3 = ?';
                    $updateParams[] = $subdivideByFieldIdLevel3;
                }
                if ($hasTemplateSubdivideShowTotalsLevel1Column) {
                    $updateSql .= ',
                         subdivide_show_totals_level_1 = ?';
                    $updateParams[] = $subdivideShowTotalsLevel1;
                }
                if ($hasTemplateSubdivideShowTotalsLevel2Column) {
                    $updateSql .= ',
                         subdivide_show_totals_level_2 = ?';
                    $updateParams[] = $subdivideShowTotalsLevel2;
                }
                if ($hasTemplateSubdivideShowTotalsLevel3Column) {
                    $updateSql .= ',
                         subdivide_show_totals_level_3 = ?';
                    $updateParams[] = $subdivideShowTotalsLevel3;
                }
                $updateSql .= ',
                         is_active = ?,
                         updated_at = NOW()
                     WHERE id_report_template = ?
                       AND id_company = ?';
                $updateParams[] = $isActive;
                $updateParams[] = $templateId;
                $updateParams[] = $companyId;
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute($updateParams);
                $selectedTemplateId = $templateId;
                $messages[] = 'Plantilla actualizada.';
            } else {
                $reportKey = reports_v2_build_unique_code($pdo, 'report_template', 'id_report_template', 'report_key', $companyId, $reportName, 0);
                $insertColumns = array(
                    'id_company',
                    'report_key',
                    'report_name',
                    'description',
                    'row_source',
                );
                $insertValues = array(
                    $companyId,
                    $reportKey,
                    $reportName,
                    $description !== '' ? $description : null,
                    $rowSource,
                );
                if (reports_v2_report_template_has_line_item_type_scope_column($pdo)) {
                    $insertColumns[] = 'line_item_type_scope';
                    $insertValues[] = $lineItemTypeScope !== null && $lineItemTypeScope !== '' ? $lineItemTypeScope : null;
                }
                if ($hasTemplateSubdivideColumn) {
                    $insertColumns[] = 'subdivide_by_field_id';
                    $insertValues[] = $subdivideByFieldId;
                }
                if ($hasTemplateSubdivideLevel2Column) {
                    $insertColumns[] = 'subdivide_by_field_id_level_2';
                    $insertValues[] = $subdivideByFieldIdLevel2;
                }
                if ($hasTemplateSubdivideLevel3Column) {
                    $insertColumns[] = 'subdivide_by_field_id_level_3';
                    $insertValues[] = $subdivideByFieldIdLevel3;
                }
                if ($hasTemplateSubdivideShowTotalsLevel1Column) {
                    $insertColumns[] = 'subdivide_show_totals_level_1';
                    $insertValues[] = $subdivideShowTotalsLevel1;
                }
                if ($hasTemplateSubdivideShowTotalsLevel2Column) {
                    $insertColumns[] = 'subdivide_show_totals_level_2';
                    $insertValues[] = $subdivideShowTotalsLevel2;
                }
                if ($hasTemplateSubdivideShowTotalsLevel3Column) {
                    $insertColumns[] = 'subdivide_show_totals_level_3';
                    $insertValues[] = $subdivideShowTotalsLevel3;
                }
                $insertColumns = array_merge($insertColumns, array(
                    'is_active',
                    'created_by',
                    'created_at',
                    'updated_at'
                ));
                $insertValues[] = $isActive;
                $insertValues[] = $actorUserId > 0 ? $actorUserId : null;
                $insertSql = 'INSERT INTO report_template (
                        ' . implode(",\n                        ", $insertColumns) . '
                     ) VALUES (' . implode(', ', array_fill(0, count($insertValues), '?')) . ', NOW(), NOW())';
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute($insertValues);
                $selectedTemplateId = (int)$pdo->lastInsertId();
                $messages[] = 'Plantilla creada.';
            }
            $selectedTemplate = reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId);
            if ($rowSource === 'line_item') {
                $persistedRowSource = $selectedTemplate && isset($selectedTemplate['row_source']) ? trim((string)$selectedTemplate['row_source']) : '';
                if ($persistedRowSource !== 'line_item') {
                    $errors[] = 'La plantilla se intento guardar con objeto por fila "line item" pero la BD devolvio "' . ($persistedRowSource !== '' ? $persistedRowSource : 'vacio') . '". Aplica bd pms/migrate_report_template_line_item_row_source.sql y vuelve a guardar la plantilla.';
                }
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
        } elseif ($action === 'save_report_row') {
            $editReservationId = (int)reports_v2_post('reservation_id', 0);
            $rowEditDraftValues = reports_v2_post('row_edit_values', array());
            if (!is_array($rowEditDraftValues)) {
                $rowEditDraftValues = array();
            }
            if ($editReservationId <= 0) {
                throw new RuntimeException('No se especifico la reservacion a editar.');
            }
            if ($selectedTemplateId <= 0) {
                throw new RuntimeException('Selecciona una plantilla valida.');
            }
            $selectedTemplate = reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId);
            if (!$selectedTemplate) {
                throw new RuntimeException('La plantilla seleccionada no existe.');
            }
            $templateFields = reports_v2_fetch_template_fields($pdo, $selectedTemplateId);
            $updated = reports_v2_save_report_row_edits(
                $pdo,
                $companyId,
                $companyCode,
                $actorUserId,
                $templateFields,
                $editReservationId,
                $rowEditDraftValues
            );
            $messages[] = $updated ? 'Fila actualizada.' : 'No hubo cambios para guardar.';
            $activeTab = 'run';
            if ($updated) {
                $editReservationId = 0;
                $rowEditDraftValues = array();
            }
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
$editableReservationFields = reports_v2_template_editable_reservation_fields($templateFields);
$hasEditableReservationFields = !empty($editableReservationFields);
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
            $lineItemDisplayNameMode = trim((string)reports_v2_post('line_item_display_name_mode', 'name'));
            $reportCalculationId = (int)reports_v2_post('report_calculation_id', 0);
            $formatHint = trim((string)reports_v2_post('field_format_hint', 'auto'));
            $defaultValue = trim((string)reports_v2_post('field_default_value'));
            $isEditable = reports_v2_post('field_is_editable', '0') === '1' ? 1 : 0;
            $calculateTotal = reports_v2_post('field_calculate_total', '0') === '1' ? 1 : 0;
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
            if (
                !$supportsExtendedSourceMetric
                && $fieldType === 'line_item'
                && !in_array($sourceMetric, array('amount_cents', 'quantity'), true)
            ) {
                throw new RuntimeException('Tu MySQL todavia no soporta esa metrica de line item. Aplica bd pms/migrate_report_template_field_source_metric_varchar.sql y luego vuelve a guardar el campo.');
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
                $isEditable = pms_report_reservation_field_is_inline_editable($reservationFieldCode) && $isEditable ? 1 : 0;
                $lineItemCatalogId = 0;
                $reportCalculationId = 0;
                $sourceMetric = null;
            } elseif ($fieldType === 'line_item') {
                $isEditable = 0;
                if (!in_array($lineItemDisplayNameMode, array('alias', 'name', 'name_parent'), true)) {
                    $lineItemDisplayNameMode = 'name';
                }
                $templateRowSource = isset($template['row_source']) ? (string)$template['row_source'] : 'reservation';
                $allowCurrentRecordCatalog = $templateRowSource === 'line_item';
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
                if (empty($selectedCatalogs) && !$allowCurrentRecordCatalog) {
                    throw new RuntimeException('Selecciona un line item valido.');
                }
                if ($lineItemDisplayNameMode === 'name' || $lineItemDisplayNameMode === 'name_parent') {
                    if (empty($selectedCatalogs) && $allowCurrentRecordCatalog) {
                        $displayName = $lineItemDisplayNameMode === 'name_parent'
                            ? 'Nombre - nombre padre'
                            : 'Nombre';
                    } elseif (!empty($selectedCatalogs)) {
                        $primaryCatalog = $selectedCatalogs[0];
                        $baseItemName = trim((string)(isset($primaryCatalog['item_name']) ? $primaryCatalog['item_name'] : ''));
                        if ($lineItemDisplayNameMode === 'name_parent' && $baseItemName !== '') {
                            $displayName = trim($baseItemName . ' - nombre padre');
                        } else {
                            $displayName = $baseItemName !== '' ? $baseItemName : 'Nombre';
                        }
                    }
                }
                if ($displayName === '') {
                    if (empty($selectedCatalogs) && $allowCurrentRecordCatalog) {
                        $displayName = 'Registro actual / ' . $metricOptions[$sourceMetric]['label'];
                    } elseif (count($selectedCatalogs) === 1) {
                        $displayName = trim((string)$selectedCatalogs[0]['item_name']) . ' / ' . $metricOptions[$sourceMetric]['label'];
                    } else {
                        $displayName = 'Multiples conceptos / ' . $metricOptions[$sourceMetric]['label'];
                    }
                }
                $lineItemCatalogId = (int)$selectedCatalogIds[0];
                $reservationFieldCode = null;
                $reportCalculationId = 0;
            } else {
                $isEditable = 0;
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
                if (reports_v2_template_field_has_is_editable_column($pdo)) {
                    $updateSql .= ',
                         is_editable = ?';
                    $updateParams[] = $isEditable;
                }
                if ($hasFieldCalculateTotalColumn) {
                    $updateSql .= ',
                         calculate_total = ?';
                    $updateParams[] = $calculateTotal;
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
                if (reports_v2_template_field_has_is_editable_column($pdo)) {
                    $insertColumns[] = 'is_editable';
                    $insertValues[] = $isEditable;
                }
                if ($hasFieldCalculateTotalColumn) {
                    $insertColumns[] = 'calculate_total';
                    $insertValues[] = $calculateTotal;
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
            if ($fieldType === 'line_item') {
                $persistedMetric = $selectedField && isset($selectedField['source_metric']) ? trim((string)$selectedField['source_metric']) : '';
                if (($sourceMetric !== null ? trim((string)$sourceMetric) : '') !== '' && $persistedMetric !== trim((string)$sourceMetric)) {
                    $errors[] = 'La metrica se intento guardar como "' . trim((string)$sourceMetric) . '" pero la BD devolvio "' . ($persistedMetric !== '' ? $persistedMetric : 'vacia') . '". Aplica bd pms/migrate_report_template_field_source_metric_varchar.sql y vuelve a guardar el campo.';
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
            if ($hasTemplateSubdivideColumn) {
                $setParts = array('subdivide_by_field_id = NULL');
                $whereParts = array('subdivide_by_field_id = ?');
                $setParams = array();
                $whereParams = array($fieldId);
                if ($hasTemplateSubdivideLevel2Column) {
                    $setParts[] = 'subdivide_by_field_id_level_2 = CASE WHEN subdivide_by_field_id_level_2 = ? THEN NULL ELSE subdivide_by_field_id_level_2 END';
                    $whereParts[] = 'subdivide_by_field_id_level_2 = ?';
                    $setParams[] = $fieldId;
                    $whereParams[] = $fieldId;
                }
                if ($hasTemplateSubdivideLevel3Column) {
                    $setParts[] = 'subdivide_by_field_id_level_3 = CASE WHEN subdivide_by_field_id_level_3 = ? THEN NULL ELSE subdivide_by_field_id_level_3 END';
                    $whereParts[] = 'subdivide_by_field_id_level_3 = ?';
                    $setParams[] = $fieldId;
                    $whereParams[] = $fieldId;
                }
                $stmtClearSubdivision = $pdo->prepare(
                    'UPDATE report_template
                     SET ' . implode(",\n                         ", $setParts) . ',
                         updated_at = NOW()
                     WHERE id_report_template = ?
                       AND (' . implode(' OR ', $whereParts) . ')'
                );
                $stmtClearSubdivision->execute(array_merge($setParams, array($templateId), $whereParams));
            }
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
$reportDisplayTotals = array();
$reportSubdivisions = array();
$reportHasCalculatedTotals = false;
$reportSubdivideByFieldId = 0;
$reportSubdivideByFieldIdLevel2 = 0;
$reportSubdivideByFieldIdLevel3 = 0;
$reportSubdivisionShowTotals = array(1 => true, 2 => true, 3 => true);
$runTemplate = null;
if ($canRun && $selectedTemplate && !empty($templateFields)) {
    try {
        $runTemplate = $selectedTemplate;
        $baseRows = reports_v2_fetch_report_base_rows(
            $pdo,
            $companyId,
            $runFilters,
            500,
            isset($selectedTemplate['row_source']) ? (string)$selectedTemplate['row_source'] : 'reservation',
            isset($selectedTemplate['line_item_type_scope']) ? (string)$selectedTemplate['line_item_type_scope'] : ''
        );
        $reservationIds = array();
        foreach ($baseRows as $baseRow) {
            $reservationIds[] = isset($baseRow['id_reservation']) ? (int)$baseRow['id_reservation'] : 0;
        }
        $baseLineItemIds = array();
        foreach ($baseRows as $baseRow) {
            $baseLineItemIds[] = isset($baseRow['base_line_item_id']) ? (int)$baseRow['base_line_item_id'] : 0;
        }
        $lineItemMetrics = reports_v2_fetch_line_item_metrics($pdo, $reservationIds);
        $lineItemTreeMetrics = reports_v2_fetch_line_item_tree_metrics($pdo, $baseLineItemIds);
        list($reportRows, $reportTotals) = reports_v2_build_report_rows(
            $baseRows,
            $templateFields,
            $calculationsById,
            $lineItemMetrics,
            $variableCatalog,
            $selectedTemplate,
            $lineItemTreeMetrics
        );
        foreach ($templateFields as $templateFieldRow) {
            if (reports_v2_field_calculates_total($templateFieldRow)) {
                $reportHasCalculatedTotals = true;
                break;
            }
        }
        if ($reportHasCalculatedTotals && !empty($reportRows)) {
            $reportDisplayTotals = reports_v2_compute_display_totals($reportRows, $templateFields, 'Totales generales');
        }
        if ($hasTemplateSubdivideColumn) {
            $reportSubdivideByFieldId = $reportGridSubdivideFieldId > 0
                ? $reportGridSubdivideFieldId
                : (isset($selectedTemplate['subdivide_by_field_id']) ? (int)$selectedTemplate['subdivide_by_field_id'] : 0);
            $reportSubdivideByFieldIdLevel2 = $reportGridSubdivideFieldId > 0
                ? 0
                : (isset($selectedTemplate['subdivide_by_field_id_level_2']) ? (int)$selectedTemplate['subdivide_by_field_id_level_2'] : 0);
            $reportSubdivideByFieldIdLevel3 = $reportGridSubdivideFieldId > 0
                ? 0
                : (isset($selectedTemplate['subdivide_by_field_id_level_3']) ? (int)$selectedTemplate['subdivide_by_field_id_level_3'] : 0);
            $reportSubdivisionShowTotals = array(
                1 => isset($selectedTemplate['subdivide_show_totals_level_1']) ? !empty($selectedTemplate['subdivide_show_totals_level_1']) : true,
                2 => isset($selectedTemplate['subdivide_show_totals_level_2']) ? !empty($selectedTemplate['subdivide_show_totals_level_2']) : true,
                3 => isset($selectedTemplate['subdivide_show_totals_level_3']) ? !empty($selectedTemplate['subdivide_show_totals_level_3']) : true,
            );
            if ($reportSubdivideByFieldId > 0) {
                $validSubdivideFields = array();
                foreach ($templateFields as $templateFieldRow) {
                    $validSubdivideFields[] = (int)(isset($templateFieldRow['id_report_template_field']) ? $templateFieldRow['id_report_template_field'] : 0);
                }
                $fieldIds = array($reportSubdivideByFieldId);
                if ($reportSubdivideByFieldIdLevel2 > 0) {
                    $fieldIds[] = $reportSubdivideByFieldIdLevel2;
                }
                if ($reportSubdivideByFieldIdLevel3 > 0) {
                    $fieldIds[] = $reportSubdivideByFieldIdLevel3;
                }
                $allValid = true;
                foreach ($fieldIds as $candidateFieldId) {
                    if (!in_array((int)$candidateFieldId, $validSubdivideFields, true)) {
                        $allValid = false;
                        break;
                    }
                }
                if ($allValid) {
                    $reportSubdivisions = reports_v2_build_report_subdivision_tree($reportRows, $fieldIds, $reportSubdivisionShowTotals);
                } else {
                    $reportSubdivideByFieldId = 0;
                    $reportSubdivideByFieldIdLevel2 = 0;
                    $reportSubdivideByFieldIdLevel3 = 0;
                }
            }
        }
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
  .report-v2-table tfoot td { font-weight: 700; background: rgba(17, 34, 64, 0.72); }
  .report-v2-group-row td { padding-top: 12px; padding-bottom: 12px; border-bottom-color: rgba(53, 199, 240, 0.18); }
  .report-v2-group-row--level-2 td { background: rgba(25, 56, 94, 0.38); }
  .report-v2-group-row--level-3 td { background: rgba(20, 45, 76, 0.55); border-left: 3px solid rgba(53, 199, 240, 0.38); }
  .report-v2-group-row-inner { display: flex; align-items: center; gap: 10px; }
  .report-v2-group-row-kicker { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(162, 202, 235, 0.82); }
  .report-v2-group-row-label { font-weight: 700; }
  .report-v2-result-row--indent-2 td:first-child { padding-left: 26px; }
  .report-v2-result-row--indent-3 td:first-child { padding-left: 42px; }
  .report-v2-result-row--deep td { border-left: 3px solid rgba(53, 199, 240, 0.18); }
  .report-v2-inline-total-row td { font-weight: 700; background: rgba(14, 31, 58, 0.72); }
  .report-v2-inline-total-row--level-2 td { background: rgba(20, 45, 76, 0.42); }
  .report-v2-inline-total-row--level-3 td { background: rgba(16, 37, 63, 0.62); border-left: 3px solid rgba(53, 199, 240, 0.28); }
  .report-v2-subreport-title { margin: 0; font-size: 16px; }
  .report-v2-error-list, .report-v2-message-list { display: grid; gap: 10px; }
  .report-v2-error, .report-v2-message { padding: 12px 14px; border-radius: 14px; }
  .report-v2-error { background: rgba(180, 35, 35, 0.18); border: 1px solid rgba(220, 90, 90, 0.3); }
  .report-v2-message { background: rgba(35, 140, 80, 0.18); border: 1px solid rgba(90, 220, 140, 0.3); }
  .report-v2-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
  .report-v2-tab { padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(120, 150, 190, 0.22); text-decoration: none; color: inherit; background: rgba(11, 27, 56, 0.52); }
  .report-v2-tab.is-active { border-color: rgba(53, 199, 240, 0.55); background: rgba(53, 199, 240, 0.16); }
  .report-v2-code-list { max-height: 240px; overflow: auto; border: 1px solid rgba(120, 150, 190, 0.18); border-radius: 14px; padding: 10px; background: rgba(11, 27, 56, 0.44); }
  .report-v2-code-list button { margin: 0 6px 6px 0; }
  .report-v2-inline-cell-input { width: 100%; min-width: 120px; }
  .report-v2-table td.report-v2-actions-cell { white-space: nowrap; width: 1%; }
  .report-v2-inline-form { margin: 0; }
  .report-v2-grid-filter-bar { padding: 14px 16px; border: 1px solid rgba(120, 150, 190, 0.18); border-radius: 16px; background: rgba(11, 27, 56, 0.32); }
  .report-v2-grid-toolbar { display: flex; gap: 14px; align-items: end; flex-wrap: nowrap; }
  .report-v2-grid-toolbar-dates { display: flex; gap: 10px; align-items: end; flex: 1 1 auto; min-width: 0; flex-wrap: nowrap; }
  .report-v2-grid-toolbar-dates select { flex: 1 1 320px; min-width: 220px; }
  .report-v2-grid-toolbar-dates .report-v2-inline-range { flex: 0 0 auto; min-width: 280px; }
  .report-v2-grid-toolbar-search { display: flex; gap: 10px; align-items: end; min-width: 0; flex: 0 1 640px; }
  .report-v2-grid-toolbar-search input { min-width: 320px; width: 100%; }
  .report-v2-grid-toolbar-actions { display: flex; gap: 10px; align-items: center; justify-content: flex-end; }
  .report-v2-toolbar-label { font-size: 12px; opacity: 0.82; white-space: nowrap; }
  .report-v2-toolbar-connector { font-size: 12px; opacity: 0.82; align-self: end; white-space: nowrap; padding-bottom: 8px; }
  .report-v2-inline-range .pms-date-range-picker-trigger { min-width: 280px; }
  .report-v2-th-main { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
  .report-v2-th-actions { display: inline-flex; align-items: center; gap: 8px; flex-shrink: 0; }
  .report-v2-sort-button { border: 0; background: transparent; color: rgba(190, 210, 235, 0.72); width: 20px; height: 20px; padding: 0; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
  .report-v2-filter-button { border: 1px solid rgba(120, 150, 190, 0.28); background: rgba(17, 36, 72, 0.9); color: rgba(220, 235, 255, 0.92); border-radius: 12px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 0 0 1px rgba(255,255,255,0.02) inset; }
  .report-v2-filter-button:hover { border-color: rgba(53, 199, 240, 0.42); background: rgba(21, 44, 86, 0.96); }
  .report-v2-filter-button.is-active { border-color: rgba(53, 199, 240, 0.68); background: rgba(53, 199, 240, 0.22); color: #35c7f0; }
  .report-v2-filter-button svg { width: 16px; height: 16px; }
  .report-v2-sort-button svg { width: 18px; height: 18px; }
  .report-v2-sort-button .report-v2-sort-up,
  .report-v2-sort-button .report-v2-sort-down { opacity: 0.34; transition: opacity 0.15s ease, color 0.15s ease; }
  .report-v2-sort-button.is-asc .report-v2-sort-up,
  .report-v2-sort-button.is-desc .report-v2-sort-down { opacity: 1; }
  .report-v2-sort-button:hover { color: rgba(220, 235, 255, 0.96); }
  .report-v2-sort-button.is-asc,
  .report-v2-sort-button.is-desc { color: #35c7f0; }
  .report-v2-th-inner { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
  .report-v2-grid-filter-summary { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .report-v2-grid-filter-chip { padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(120, 150, 190, 0.22); background: rgba(11, 27, 56, 0.52); }
  .report-v2-lightbox-backdrop { position: fixed; inset: 0; background: rgba(2, 10, 24, 0.68); backdrop-filter: blur(2px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 24px; }
  .report-v2-lightbox-backdrop.is-open { display: flex; }
  .report-v2-lightbox { width: min(520px, 100%); max-height: min(80vh, 720px); overflow: hidden; display: flex; flex-direction: column; border-radius: 18px; border: 1px solid rgba(120, 150, 190, 0.22); background: rgba(7, 20, 44, 0.98); box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45); }
  .report-v2-lightbox-header, .report-v2-lightbox-footer { padding: 14px 16px; border-bottom: 1px solid rgba(120, 150, 190, 0.12); }
  .report-v2-lightbox-footer { border-bottom: 0; border-top: 1px solid rgba(120, 150, 190, 0.12); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
  .report-v2-lightbox-body { padding: 14px 16px; overflow: auto; display: flex; flex-direction: column; gap: 12px; }
  .report-v2-filter-checklist { display: grid; gap: 8px; }
  .report-v2-filter-check { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid rgba(120, 150, 190, 0.16); border-radius: 12px; background: rgba(11, 27, 56, 0.32); }
  .report-v2-advanced-filter-form { display: grid; gap: 12px; }
  .report-v2-advanced-filter-form input,
  .report-v2-advanced-filter-form select { width: 100%; }
  .report-v2-advanced-filter-row { display: grid; gap: 10px; grid-template-columns: 160px minmax(0, 1fr); align-items: end; }
  .report-v2-subreport-title.is-empty { opacity: 0.55; }
  .report-v2-context-menu { position: fixed; z-index: 1010; min-width: 240px; padding: 8px; border-radius: 14px; border: 1px solid rgba(120, 150, 190, 0.22); background: rgba(7, 20, 44, 0.98); box-shadow: 0 18px 48px rgba(0, 0, 0, 0.35); display: none; }
  .report-v2-context-menu.is-open { display: block; }
  .report-v2-context-menu button { width: 100%; text-align: left; }
  @media (max-width: 1180px) {
    .report-v2-grid, .report-v2-grid-bottom { grid-template-columns: 1fr; }
    .report-v2-form-grid, .report-v2-form-grid--3, .report-v2-form-grid--4 { grid-template-columns: 1fr; }
    .report-v2-grid-toolbar { display: grid; grid-template-columns: 1fr; }
    .report-v2-grid-toolbar-dates { display: grid; grid-template-columns: 1fr; }
    .report-v2-grid-toolbar-search { display: grid; }
    .report-v2-grid-toolbar-search input { min-width: 0; width: 100%; }
    .report-v2-grid-toolbar-actions { justify-content: flex-start; }
    .report-v2-advanced-filter-row { grid-template-columns: 1fr; }
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
    var editableReservationFieldCodes = <?php echo json_encode(array_values(array_keys(function_exists('pms_report_reservation_editable_field_catalog') ? pms_report_reservation_editable_field_catalog() : array()))); ?>;
    var templateRowSource = document.getElementById('reports-v2-template-row-source');
    if (templateRowSource) {
      var toggleTemplatePanels = function () {
        document.querySelectorAll('[data-template-row-source-panel]').forEach(function (panel) {
          panel.style.display = panel.getAttribute('data-template-row-source-panel') === templateRowSource.value ? '' : 'none';
        });
      };
      templateRowSource.addEventListener('change', toggleTemplatePanels);
      toggleTemplatePanels();
    }
    var fieldType = document.getElementById('reports-v2-field-type');
    var reservationFieldCode = document.getElementById('reports-v2-reservation-field-code');
    var editablePanel = document.querySelector('[data-field-editable-panel="global"]');
    var editableSelect = editablePanel ? editablePanel.querySelector('select[name="field_is_editable"]') : null;
    var editableHelpSupported = editablePanel ? editablePanel.querySelector('[data-field-editable-help="supported"]') : null;
    var editableHelpUnsupported = editablePanel ? editablePanel.querySelector('[data-field-editable-help="unsupported"]') : null;
    if (fieldType) {
      var togglePanels = function () {
        document.querySelectorAll('[data-field-panel]').forEach(function (panel) {
          panel.style.display = panel.getAttribute('data-field-panel') === fieldType.value ? '' : 'none';
        });
      };
      var toggleEditablePanel = function () {
        if (!editablePanel) {
          return;
        }
        var isReservationField = fieldType.value === 'reservation';
        var supportsInlineEdit = isReservationField
          && reservationFieldCode
          && editableReservationFieldCodes.indexOf(reservationFieldCode.value) !== -1;
        if (editableSelect) {
          editableSelect.disabled = !supportsInlineEdit;
          if (!supportsInlineEdit) {
            editableSelect.value = '0';
          }
        }
        if (editableHelpSupported) {
          editableHelpSupported.style.display = supportsInlineEdit ? '' : 'none';
        }
        if (editableHelpUnsupported) {
          editableHelpUnsupported.style.display = !supportsInlineEdit ? '' : 'none';
          editableHelpUnsupported.textContent = isReservationField
            ? 'Solo algunos campos de reservacion permiten edicion inline.'
            : 'Solo aplica a campos de tipo Reservacion.';
        }
      };
      fieldType.addEventListener('change', function () {
        togglePanels();
        toggleEditablePanel();
      });
      if (reservationFieldCode) {
        reservationFieldCode.addEventListener('change', toggleEditablePanel);
      }
      togglePanels();
      toggleEditablePanel();
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

    var gridSearchInput = document.getElementById('report-v2-grid-search');
    var activeDateForm = document.getElementById('report-v2-active-date-form');
    var clearGridFiltersButton = document.getElementById('report-v2-grid-clear-filters');
    var visibleCountChip = document.getElementById('report-v2-grid-visible-count');
    var filterModal = document.getElementById('report-v2-column-filter-modal');
    var filterModalTitle = document.getElementById('report-v2-column-filter-title');
    var filterModalOptions = document.getElementById('report-v2-column-filter-options');
    var filterModalSummary = document.getElementById('report-v2-column-filter-summary');
    var filterModalClose = document.getElementById('report-v2-column-filter-close');
    var filterCheckAll = document.getElementById('report-v2-column-filter-check-all');
    var filterUncheckAll = document.getElementById('report-v2-column-filter-uncheck-all');
    var advancedFilterModal = document.getElementById('report-v2-column-advanced-filter-modal');
    var advancedFilterModalTitle = document.getElementById('report-v2-column-advanced-filter-title');
    var advancedFilterModalForm = document.getElementById('report-v2-column-advanced-filter-form');
    var advancedFilterModalSummary = document.getElementById('report-v2-column-advanced-filter-summary');
    var advancedFilterModalApply = document.getElementById('report-v2-column-advanced-filter-apply');
    var advancedFilterModalClose = document.getElementById('report-v2-column-advanced-filter-close');
    var advancedFilterModalClear = document.getElementById('report-v2-column-advanced-filter-clear');
    var columnContextMenu = document.getElementById('report-v2-column-context-menu');
    var columnContextSubdivideButton = document.getElementById('report-v2-column-context-subdivide');
    var columnContextAdvancedFilterButton = document.getElementById('report-v2-column-context-advanced-filter');
    var sortButtons = Array.prototype.slice.call(document.querySelectorAll('[data-sort-field-id]'));
    var resultTables = Array.prototype.slice.call(document.querySelectorAll('.report-v2-result-table'));
    var globalTotalsTable = document.querySelector('.report-v2-result-totals-table');
    var activeColumnFilterFieldId = null;
    var activeAdvancedFilterFieldId = null;
    var activeContextFieldId = null;
    var columnValueCatalog = {};
    var columnFilterState = {};
    var columnAdvancedFilterState = {};
    var sortState = { fieldId: '', direction: '' };
    var gridRuntimeSubdivideFieldId = <?php echo (int)$reportGridSubdivideFieldId; ?>;

    var collectAllResultRows = function () {
      return Array.prototype.slice.call(document.querySelectorAll('.report-v2-result-table tbody tr.report-v2-result-row'));
    };

    var allResultRows = collectAllResultRows();

    if (allResultRows.length) {
      allResultRows.forEach(function (row, rowIndex) {
        row.setAttribute('data-original-index', String(rowIndex));
        var rowText = Array.prototype.slice.call(row.querySelectorAll('td[data-field-id]')).map(function (cell) {
          return (cell.getAttribute('data-cell-display') || '').trim();
        }).join(' ').toLowerCase();
        row.setAttribute('data-row-text', rowText);

        Array.prototype.slice.call(row.querySelectorAll('td[data-field-id]')).forEach(function (cell) {
          var fieldId = cell.getAttribute('data-field-id') || '';
          if (!fieldId) {
            return;
          }
          if (!columnValueCatalog[fieldId]) {
            columnValueCatalog[fieldId] = {};
          }
          var filterValue = cell.getAttribute('data-cell-filter-value') || '__EMPTY__';
          var displayLabel = filterValue === '__EMPTY__' ? 'Sin valor' : (cell.getAttribute('data-cell-display') || filterValue);
          if (!columnValueCatalog[fieldId][filterValue]) {
            columnValueCatalog[fieldId][filterValue] = {
              value: filterValue,
              label: displayLabel
            };
          }
        });
      });
    }

    var parseDateValue = function (value) {
      if (!value) {
        return null;
      }
      var datePart = String(value).slice(0, 10);
      if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
        return null;
      }
      return datePart;
    };

    var formatDateValue = function (value) {
      var dateValue = parseDateValue(value);
      if (!dateValue) {
        return '';
      }
      var parts = dateValue.split('-');
      return parts[2] + '/' + parts[1] + '/' + parts[0];
    };

    var formatTotalValue = function (rawValue, cellType) {
      var numericValue = Number(rawValue || 0);
      if (cellType === 'currency') {
        return '$' + (numericValue / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN';
      }
      if (cellType === 'integer') {
        return String(Math.trunc(numericValue));
      }
      if (cellType === 'number') {
        return numericValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      if (cellType === 'date') {
        return formatDateValue(rawValue);
      }
      return String(rawValue || '');
    };

    var getAllowedValuesForField = function (fieldId) {
      var catalog = columnValueCatalog[fieldId] || {};
      return Object.keys(catalog);
    };

    var fieldHasActiveColumnFilter = function (fieldId) {
      var allowedValues = getAllowedValuesForField(fieldId);
      var state = columnFilterState[fieldId];
      return Array.isArray(allowedValues) && allowedValues.length > 0 && state instanceof Set && state.size < allowedValues.length;
    };

    var getFieldTypeForAdvancedFilter = function (fieldId) {
      var header = document.querySelector('th[data-field-id="' + fieldId + '"]');
      var hintedType = header ? (header.getAttribute('data-format-hint') || '') : '';
      if (hintedType && hintedType !== 'auto') {
        return hintedType;
      }
      var firstCell = document.querySelector('td[data-field-id="' + fieldId + '"]');
      return firstCell ? (firstCell.getAttribute('data-cell-type') || 'text') : 'text';
    };

    var fieldHasActiveAdvancedFilter = function (fieldId) {
      var state = columnAdvancedFilterState[fieldId];
      return !!(
        state
        && typeof state === 'object'
        && (
          String(state.value || '').trim() !== ''
          || String(state.valueTo || '').trim() !== ''
        )
      );
    };

    var getFieldLabel = function (fieldId) {
      var headerLabelNode = document.querySelector('th[data-field-id="' + fieldId + '"] .report-v2-th-label');
      var label = headerLabelNode ? String(headerLabelNode.textContent || '').trim() : '';
      if (!label || label.toLowerCase() === 'null' || label.toLowerCase() === 'undefined') {
        return 'columna';
      }
      return label;
    };

    var updateFilterButtonStates = function () {
      document.querySelectorAll('[data-filter-field-id]').forEach(function (button) {
        var fieldId = button.getAttribute('data-filter-field-id') || '';
        button.classList.toggle('is-active', fieldHasActiveColumnFilter(fieldId) || fieldHasActiveAdvancedFilter(fieldId));
      });
    };

    var buildBaseReportUrl = function () {
      var url = new URL(window.location.href);
      url.searchParams.set('view', 'reports');
      url.searchParams.set('reports_tab', 'run');
      url.searchParams.set('selected_report_template_id', <?php echo json_encode((string)$selectedTemplateId); ?>);
      url.searchParams.set('selected_report_calculation_id', <?php echo json_encode((string)$selectedCalculationId); ?>);
      url.searchParams.set('report_property_code', <?php echo json_encode((string)$runFilters['property_code']); ?>);
      url.searchParams.set('report_status', <?php echo json_encode((string)$runFilters['status']); ?>);
      url.searchParams.set('report_date_type', <?php echo json_encode((string)$runFilters['date_type']); ?>);
      url.searchParams.set('report_date_from', <?php echo json_encode((string)$runFilters['date_from']); ?>);
      url.searchParams.set('report_date_to', <?php echo json_encode((string)$runFilters['date_to']); ?>);
      url.searchParams.set('report_search', <?php echo json_encode((string)$runFilters['search']); ?>);
      return url;
    };

    var closeColumnContextMenu = function () {
      if (!columnContextMenu) {
        return;
      }
      columnContextMenu.classList.remove('is-open');
      columnContextMenu.setAttribute('aria-hidden', 'true');
      activeContextFieldId = null;
    };

    var describeAdvancedFilter = function (fieldId) {
      var state = columnAdvancedFilterState[fieldId];
      if (!fieldHasActiveAdvancedFilter(fieldId)) {
        return 'Sin filtro especializado';
      }
      var fieldType = getFieldTypeForAdvancedFilter(fieldId);
      if (fieldType === 'text') {
        return 'Contiene "' + state.value + '"';
      }
      if (state.operator === 'between') {
        return 'Entre ' + state.value + ' y ' + state.valueTo;
      }
      var operatorLabels = {
        '=': 'Igual a',
        '!=': 'Distinto de',
        '>': 'Mayor que',
        '>=': 'Mayor o igual que',
        '<': 'Menor que',
        '<=': 'Menor o igual que'
      };
      return (operatorLabels[state.operator || '='] || 'Igual a') + ' ' + state.value;
    };

    var updateSortButtonStates = function () {
      sortButtons.forEach(function (button) {
        var fieldId = button.getAttribute('data-sort-field-id') || '';
        button.classList.toggle('is-asc', sortState.fieldId === fieldId && sortState.direction === 'asc');
        button.classList.toggle('is-desc', sortState.fieldId === fieldId && sortState.direction === 'desc');
      });
    };

    var isCellEmptyForSort = function (cell) {
      if (!cell) {
        return true;
      }
      var rawValue = cell.getAttribute('data-cell-raw');
      var displayValue = cell.getAttribute('data-cell-display') || '';
      return (rawValue === null || String(rawValue).trim() === '') && displayValue.trim() === '';
    };

    var compareCellValues = function (leftCell, rightCell) {
      var leftType = leftCell ? (leftCell.getAttribute('data-cell-type') || 'text') : 'text';
      var rightType = rightCell ? (rightCell.getAttribute('data-cell-type') || 'text') : 'text';
      var cellType = leftType !== 'text' ? leftType : rightType;
      var leftRaw = leftCell ? leftCell.getAttribute('data-cell-raw') : '';
      var rightRaw = rightCell ? rightCell.getAttribute('data-cell-raw') : '';
      var leftDisplay = leftCell ? (leftCell.getAttribute('data-cell-display') || '') : '';
      var rightDisplay = rightCell ? (rightCell.getAttribute('data-cell-display') || '') : '';

      if (cellType === 'currency' || cellType === 'number' || cellType === 'integer') {
        var leftNumber = leftRaw !== null && leftRaw !== '' && !isNaN(Number(leftRaw)) ? Number(leftRaw) : null;
        var rightNumber = rightRaw !== null && rightRaw !== '' && !isNaN(Number(rightRaw)) ? Number(rightRaw) : null;
        if (leftNumber === null && rightNumber === null) {
          return 0;
        }
        if (leftNumber === null) {
          return 1;
        }
        if (rightNumber === null) {
          return -1;
        }
        return leftNumber - rightNumber;
      }

      if (cellType === 'date' || cellType === 'datetime') {
        var leftDate = parseDateValue(leftRaw || leftDisplay);
        var rightDate = parseDateValue(rightRaw || rightDisplay);
        if (!leftDate && !rightDate) {
          return 0;
        }
        if (!leftDate) {
          return 1;
        }
        if (!rightDate) {
          return -1;
        }
        return leftDate.localeCompare(rightDate);
      }

      return String(leftDisplay || '').localeCompare(String(rightDisplay || ''), undefined, { sensitivity: 'base', numeric: true });
    };

    var sortTableRows = function (table) {
      if (!table) {
        return;
      }
      Array.prototype.slice.call(table.querySelectorAll('tbody')).forEach(function (tbody) {
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr.report-v2-result-row'));
        if (!rows.length) {
          return;
        }
        rows.sort(function (leftRow, rightRow) {
          if (!sortState.fieldId || !sortState.direction) {
            return Number(leftRow.getAttribute('data-original-index') || 0) - Number(rightRow.getAttribute('data-original-index') || 0);
          }
          var leftCell = leftRow.querySelector('td[data-field-id="' + sortState.fieldId + '"]');
          var rightCell = rightRow.querySelector('td[data-field-id="' + sortState.fieldId + '"]');
          var leftEmpty = isCellEmptyForSort(leftCell);
          var rightEmpty = isCellEmptyForSort(rightCell);
          if (leftEmpty && rightEmpty) {
            return Number(leftRow.getAttribute('data-original-index') || 0) - Number(rightRow.getAttribute('data-original-index') || 0);
          }
          if (leftEmpty) {
            return 1;
          }
          if (rightEmpty) {
            return -1;
          }
          var comparison = compareCellValues(leftCell, rightCell);
          if (comparison === 0) {
            comparison = Number(leftRow.getAttribute('data-original-index') || 0) - Number(rightRow.getAttribute('data-original-index') || 0);
          }
          return sortState.direction === 'desc' ? comparison * -1 : comparison;
        });
        rows.forEach(function (row) {
          tbody.appendChild(row);
        });
      });
    };

    var sortAllTables = function () {
      resultTables.forEach(function (table) {
        sortTableRows(table);
      });
      updateSortButtonStates();
    };

    var updateTotalsForTable = function (table, rows, fallbackLabel) {
      if (!table) {
        return;
      }
      var footerCells = Array.prototype.slice.call(table.querySelectorAll('tfoot td[data-total-field-id]'));
      if (!footerCells.length) {
        return;
      }
      var headerByFieldId = {};
      Array.prototype.slice.call(table.querySelectorAll('thead th[data-field-id]')).forEach(function (th) {
        headerByFieldId[th.getAttribute('data-field-id')] = th;
      });
      var labelFieldId = null;
      Object.keys(headerByFieldId).some(function (fieldId) {
        if ((headerByFieldId[fieldId].getAttribute('data-calculate-total') || '0') !== '1') {
          labelFieldId = fieldId;
          return true;
        }
        return false;
      });
      if (!labelFieldId) {
        labelFieldId = Object.keys(headerByFieldId).length ? Object.keys(headerByFieldId)[0] : null;
      }
      footerCells.forEach(function (cell) {
        var fieldId = cell.getAttribute('data-total-field-id') || '';
        var header = headerByFieldId[fieldId];
        if (!header) {
          return;
        }
        var calculateTotal = (header.getAttribute('data-calculate-total') || '0') === '1';
        if (!calculateTotal) {
          cell.textContent = fieldId === labelFieldId ? fallbackLabel : '';
          return;
        }
        var sum = 0;
        var cellType = 'number';
        rows.forEach(function (row) {
          var dataCell = row.querySelector('td[data-field-id="' + fieldId + '"]');
          if (!dataCell) {
            return;
          }
          cellType = dataCell.getAttribute('data-cell-type') || cellType;
          var rawValue = dataCell.getAttribute('data-cell-raw');
          if (rawValue !== null && rawValue !== '' && !isNaN(Number(rawValue))) {
            sum += Number(rawValue);
          }
        });
        cell.textContent = formatTotalValue(sum, cellType);
      });
    };

    var updateInlineTotalRow = function (table, totalRow, rows, fallbackLabel) {
      if (!table || !totalRow) {
        return;
      }
      var totalCells = Array.prototype.slice.call(totalRow.querySelectorAll('td[data-total-field-id]'));
      if (!totalCells.length) {
        return;
      }
      var headerByFieldId = {};
      Array.prototype.slice.call(table.querySelectorAll('thead th[data-field-id]')).forEach(function (th) {
        headerByFieldId[th.getAttribute('data-field-id')] = th;
      });
      var labelFieldId = null;
      Object.keys(headerByFieldId).some(function (fieldId) {
        if ((headerByFieldId[fieldId].getAttribute('data-calculate-total') || '0') !== '1') {
          labelFieldId = fieldId;
          return true;
        }
        return false;
      });
      if (!labelFieldId) {
        labelFieldId = Object.keys(headerByFieldId).length ? Object.keys(headerByFieldId)[0] : null;
      }
      totalCells.forEach(function (cell) {
        var fieldId = cell.getAttribute('data-total-field-id') || '';
        var header = headerByFieldId[fieldId];
        if (!header) {
          return;
        }
        var calculateTotal = (header.getAttribute('data-calculate-total') || '0') === '1';
        if (!calculateTotal) {
          cell.textContent = fieldId === labelFieldId ? fallbackLabel : '';
          return;
        }
        var sum = 0;
        var cellType = 'number';
        rows.forEach(function (row) {
          var dataCell = row.querySelector('td[data-field-id="' + fieldId + '"]');
          if (!dataCell) {
            return;
          }
          cellType = dataCell.getAttribute('data-cell-type') || cellType;
          var rawValue = dataCell.getAttribute('data-cell-raw');
          if (rawValue !== null && rawValue !== '' && !isNaN(Number(rawValue))) {
            sum += Number(rawValue);
          }
        });
        cell.textContent = formatTotalValue(sum, cellType);
      });
    };

    var updateNestedSubdivisionState = function (section) {
      if (!section) {
        return;
      }
      var table = section.querySelector('.report-v2-result-table');
      if (!table) {
        return;
      }
      Array.prototype.slice.call(section.querySelectorAll('tbody[data-group-role="rows"]')).forEach(function (body) {
        var visibleRows = Array.prototype.slice.call(body.querySelectorAll('tr.report-v2-result-row')).filter(function (row) {
          return row.style.display !== 'none';
        });
        body.style.display = visibleRows.length ? '' : 'none';
      });
      [3, 2].forEach(function (level) {
        Array.prototype.slice.call(section.querySelectorAll('tbody[data-group-role="marker"][data-group-level="' + level + '"]')).forEach(function (body) {
          var groupId = body.getAttribute('data-group-node-id') || '';
          var visibleRows = Array.prototype.slice.call(section.querySelectorAll('tr.report-v2-result-row[data-group-level-' + level + '="' + groupId + '"]')).filter(function (row) {
            return row.style.display !== 'none';
          });
          body.style.display = visibleRows.length ? '' : 'none';
        });
        Array.prototype.slice.call(section.querySelectorAll('tbody[data-group-role="total"][data-group-level="' + level + '"]')).forEach(function (body) {
          var groupId = body.getAttribute('data-group-node-id') || '';
          var visibleRows = Array.prototype.slice.call(section.querySelectorAll('tr.report-v2-result-row[data-group-level-' + level + '="' + groupId + '"]')).filter(function (row) {
            return row.style.display !== 'none';
          });
          body.style.display = visibleRows.length ? '' : 'none';
          var totalRow = body.querySelector('tr.report-v2-inline-total-row');
          if (totalRow) {
            updateInlineTotalRow(table, totalRow, visibleRows, body.getAttribute('data-inline-total-label') || 'Subtotal');
          }
        });
      });
    };

    var applyGridFilters = function () {
      var searchValue = gridSearchInput ? (gridSearchInput.value || '').trim().toLowerCase() : '';
      var visibleRows = [];

      allResultRows.forEach(function (row) {
        var isVisible = true;
        if (searchValue) {
          isVisible = (row.getAttribute('data-row-text') || '').indexOf(searchValue) !== -1;
        }
        if (isVisible) {
          Object.keys(columnFilterState).some(function (fieldId) {
            var allowedSet = columnFilterState[fieldId];
            if (!(allowedSet instanceof Set) || !fieldHasActiveColumnFilter(fieldId)) {
              return false;
            }
            var cell = row.querySelector('td[data-field-id="' + fieldId + '"]');
            var filterValue = cell ? (cell.getAttribute('data-cell-filter-value') || '__EMPTY__') : '__EMPTY__';
            if (!allowedSet.has(filterValue)) {
              isVisible = false;
              return true;
            }
            return false;
          });
        }
        if (isVisible) {
          Object.keys(columnAdvancedFilterState).some(function (fieldId) {
            if (!fieldHasActiveAdvancedFilter(fieldId)) {
              return false;
            }
            var state = columnAdvancedFilterState[fieldId];
            var cell = row.querySelector('td[data-field-id="' + fieldId + '"]');
            var cellRaw = cell ? (cell.getAttribute('data-cell-raw') || '') : '';
            var cellDisplay = cell ? (cell.getAttribute('data-cell-display') || '') : '';
            var cellType = cell ? (cell.getAttribute('data-cell-type') || getFieldTypeForAdvancedFilter(fieldId)) : getFieldTypeForAdvancedFilter(fieldId);
            var compareValue = String(state.value || '').trim();
            var compareValueTo = String(state.valueTo || '').trim();

            if (cellType === 'text') {
              if (String(cellDisplay || '').toLowerCase().indexOf(compareValue.toLowerCase()) === -1) {
                isVisible = false;
                return true;
              }
              return false;
            }

            if (cellType === 'date' || cellType === 'datetime') {
              var leftDate = parseDateValue(cellRaw || cellDisplay);
              var rightDate = parseDateValue(compareValue);
              if (!leftDate || !rightDate) {
                isVisible = false;
                return true;
              }
              if (state.operator === 'between') {
                var rightDateTo = parseDateValue(compareValueTo);
                if (!rightDateTo || !(leftDate >= rightDate && leftDate <= rightDateTo)) {
                  isVisible = false;
                  return true;
                }
                return false;
              }
              var dateCompare = leftDate.localeCompare(rightDate);
              if (
                (state.operator === '>' && !(dateCompare > 0)) ||
                (state.operator === '>=' && !(dateCompare >= 0)) ||
                (state.operator === '<' && !(dateCompare < 0)) ||
                (state.operator === '<=' && !(dateCompare <= 0)) ||
                (state.operator === '=' && !(dateCompare === 0)) ||
                (state.operator === '!=' && !(dateCompare !== 0))
              ) {
                isVisible = false;
                return true;
              }
              return false;
            }

            var leftNumber = cellRaw !== null && cellRaw !== '' && !isNaN(Number(cellRaw)) ? Number(cellRaw) : null;
            var rightNumber = compareValue !== '' && !isNaN(Number(compareValue)) ? Number(compareValue) : null;
            if (leftNumber === null || rightNumber === null) {
              isVisible = false;
              return true;
            }
            if (state.operator === 'between') {
              var rightNumberTo = compareValueTo !== '' && !isNaN(Number(compareValueTo)) ? Number(compareValueTo) : null;
              if (rightNumberTo === null || !(leftNumber >= rightNumber && leftNumber <= rightNumberTo)) {
                isVisible = false;
                return true;
              }
              return false;
            }
            if (
              (state.operator === '>' && !(leftNumber > rightNumber)) ||
              (state.operator === '>=' && !(leftNumber >= rightNumber)) ||
              (state.operator === '<' && !(leftNumber < rightNumber)) ||
              (state.operator === '<=' && !(leftNumber <= rightNumber)) ||
              (state.operator === '=' && !(leftNumber === rightNumber)) ||
              (state.operator === '!=' && !(leftNumber !== rightNumber))
            ) {
              isVisible = false;
              return true;
            }
            return false;
          });
        }

        row.style.display = isVisible ? '' : 'none';
        if (isVisible) {
          visibleRows.push(row);
        }
      });

      sortAllTables();

      document.querySelectorAll('.report-v2-result-subdivision').forEach(function (section) {
        var sectionVisibleRows = Array.prototype.slice.call(section.querySelectorAll('tbody tr.report-v2-result-row')).filter(function (row) {
          return row.style.display !== 'none';
        });
        section.style.display = sectionVisibleRows.length ? '' : 'none';
        var title = section.querySelector('.report-v2-subreport-title');
        if (title) {
          title.classList.toggle('is-empty', !sectionVisibleRows.length);
        }
        var table = section.querySelector('.report-v2-result-table');
        if (table) {
          updateNestedSubdivisionState(section);
          updateTotalsForTable(table, sectionVisibleRows, 'Subtotal');
        }
      });

      resultTables.forEach(function (table) {
        if (table.getAttribute('data-result-scope') === 'subdivision') {
          return;
        }
        var tableVisibleRows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.report-v2-result-row')).filter(function (row) {
          return row.style.display !== 'none';
        });
        updateTotalsForTable(table, tableVisibleRows, 'Totales generales');
      });

      updateTotalsForTable(globalTotalsTable, visibleRows, 'Totales generales');

      if (visibleCountChip) {
        visibleCountChip.textContent = visibleRows.length + ' visibles';
      }

      updateFilterButtonStates();
    };

    var renderColumnFilterModal = function (fieldId) {
      if (!filterModal || !filterModalOptions) {
        return;
      }
      activeColumnFilterFieldId = fieldId;
      var values = getAllowedValuesForField(fieldId);
      var currentSet = columnFilterState[fieldId];
      if (!(currentSet instanceof Set)) {
        currentSet = new Set(values);
        columnFilterState[fieldId] = currentSet;
      }
      var headerLabelNode = document.querySelector('th[data-field-id="' + fieldId + '"] .report-v2-th-label');
      if (filterModalTitle) {
        filterModalTitle.textContent = 'Filtrar columna: ' + (headerLabelNode ? headerLabelNode.textContent : fieldId);
      }
      filterModalOptions.innerHTML = '';
      values.sort(function (left, right) {
        var leftLabel = columnValueCatalog[fieldId][left].label;
        var rightLabel = columnValueCatalog[fieldId][right].label;
        return leftLabel.localeCompare(rightLabel);
      }).forEach(function (valueKey) {
        var meta = columnValueCatalog[fieldId][valueKey];
        var wrapper = document.createElement('label');
        wrapper.className = 'report-v2-filter-check';
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = valueKey;
        checkbox.checked = currentSet.has(valueKey);
        checkbox.addEventListener('change', function () {
          if (checkbox.checked) {
            currentSet.add(valueKey);
          } else {
            currentSet.delete(valueKey);
          }
          if (filterModalSummary) {
            filterModalSummary.textContent = currentSet.size + ' seleccionados';
          }
          applyGridFilters();
        });
        var text = document.createElement('span');
        text.textContent = meta.label;
        wrapper.appendChild(checkbox);
        wrapper.appendChild(text);
        filterModalOptions.appendChild(wrapper);
      });
      if (filterModalSummary) {
        filterModalSummary.textContent = currentSet.size + ' seleccionados';
      }
      filterModal.classList.add('is-open');
      filterModal.setAttribute('aria-hidden', 'false');
    };

    var closeColumnFilterModal = function () {
      if (!filterModal) {
        return;
      }
      filterModal.classList.remove('is-open');
      filterModal.setAttribute('aria-hidden', 'true');
      activeColumnFilterFieldId = null;
    };

    var closeAdvancedFilterModal = function () {
      if (!advancedFilterModal) {
        return;
      }
      advancedFilterModal.classList.remove('is-open');
      advancedFilterModal.setAttribute('aria-hidden', 'true');
      activeAdvancedFilterFieldId = null;
    };

    var renderAdvancedFilterModal = function (fieldId) {
      if (!advancedFilterModal || !advancedFilterModalForm) {
        return;
      }
      activeAdvancedFilterFieldId = fieldId;
      var fieldLabel = getFieldLabel(fieldId);
      var fieldType = getFieldTypeForAdvancedFilter(fieldId);
      var state = columnAdvancedFilterState[fieldId] || { operator: '>=', value: '', valueTo: '' };
      if (advancedFilterModalTitle) {
        advancedFilterModalTitle.textContent = 'Filtro especializado: ' + fieldLabel;
      }
      advancedFilterModalForm.innerHTML = '';
      advancedFilterModalForm.className = 'report-v2-advanced-filter-form';

      if (fieldType === 'text') {
        advancedFilterModalForm.innerHTML =
          '<label><span>Buscar texto</span><input type="text" id="report-v2-advanced-filter-value" value="' + String(state.value || '').replace(/"/g, '&quot;') + '" placeholder="Escribe una palabra o frase"></label>';
      } else {
        var inputType = (fieldType === 'date' || fieldType === 'datetime') ? 'date' : 'number';
        advancedFilterModalForm.innerHTML =
          '<div class="report-v2-advanced-filter-row">' +
            '<label><span>Condicion</span><select id="report-v2-advanced-filter-operator">' +
              '<option value=\"=\">Igual a</option>' +
              '<option value=\"!=\">Distinto de</option>' +
              '<option value=\">\">Mayor que</option>' +
              '<option value=\">=\">Mayor o igual que</option>' +
              '<option value=\"<\">Menor que</option>' +
              '<option value=\"<=\">Menor o igual que</option>' +
              '<option value=\"between\">Entre</option>' +
            '</select></label>' +
            '<label><span>Valor</span><input type="' + inputType + '" id="report-v2-advanced-filter-value" value="' + String(state.value || '').replace(/"/g, '&quot;') + '"></label>' +
            '<label id="report-v2-advanced-filter-value-to-wrap" style="display:none;"><span>Y</span><input type="' + inputType + '" id="report-v2-advanced-filter-value-to" value="' + String(state.valueTo || '').replace(/"/g, '&quot;') + '"></label>' +
          '</div>';
        var operatorSelect = advancedFilterModalForm.querySelector('#report-v2-advanced-filter-operator');
        if (operatorSelect) {
          operatorSelect.value = state.operator || '>=';
          var valueToWrap = advancedFilterModalForm.querySelector('#report-v2-advanced-filter-value-to-wrap');
          var syncSecondaryValue = function () {
            if (!valueToWrap) {
              return;
            }
            valueToWrap.style.display = operatorSelect.value === 'between' ? '' : 'none';
          };
          operatorSelect.addEventListener('change', syncSecondaryValue);
          syncSecondaryValue();
        }
      }
      if (advancedFilterModalSummary) {
        advancedFilterModalSummary.textContent = describeAdvancedFilter(fieldId);
      }
      advancedFilterModal.classList.add('is-open');
      advancedFilterModal.setAttribute('aria-hidden', 'false');
    };

    document.querySelectorAll('[data-filter-field-id]').forEach(function (button) {
      button.addEventListener('click', function () {
        renderColumnFilterModal(button.getAttribute('data-filter-field-id') || '');
      });
    });

    document.querySelectorAll('.report-v2-result-table thead th[data-field-id]').forEach(function (headerCell) {
      headerCell.addEventListener('contextmenu', function (event) {
        if (!columnContextMenu || !columnContextSubdivideButton) {
          return;
        }
        event.preventDefault();
        var fieldId = headerCell.getAttribute('data-field-id') || '';
        var fieldLabel = getFieldLabel(fieldId);
        activeContextFieldId = fieldId;
        columnContextSubdivideButton.textContent = 'Dividir reporte por ' + fieldLabel;
        if (columnContextAdvancedFilterButton) {
          columnContextAdvancedFilterButton.textContent = 'Filtro especializado para ' + fieldLabel;
        }
        columnContextMenu.style.left = event.clientX + 'px';
        columnContextMenu.style.top = event.clientY + 'px';
        columnContextMenu.classList.add('is-open');
        columnContextMenu.setAttribute('aria-hidden', 'false');
      });
    });

    sortButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        var fieldId = button.getAttribute('data-sort-field-id') || '';
        if (!fieldId) {
          return;
        }
        if (sortState.fieldId !== fieldId) {
          sortState.fieldId = fieldId;
          sortState.direction = 'asc';
        } else if (sortState.direction === 'asc') {
          sortState.direction = 'desc';
        } else if (sortState.direction === 'desc') {
          sortState.fieldId = '';
          sortState.direction = '';
        } else {
          sortState.direction = 'asc';
        }
        applyGridFilters();
      });
    });

    if (gridSearchInput) {
      gridSearchInput.addEventListener('input', applyGridFilters);
    }

    if (activeDateForm) {
      Array.prototype.slice.call(activeDateForm.querySelectorAll('.report-v2-auto-submit-date-filter')).forEach(function (control) {
        control.addEventListener('change', function () {
          activeDateForm.submit();
        });
      });
    }

    if (clearGridFiltersButton) {
      clearGridFiltersButton.addEventListener('click', function () {
        if (gridRuntimeSubdivideFieldId > 0) {
          var baseUrl = buildBaseReportUrl();
          baseUrl.searchParams.delete('report_grid_subdivide_field_id');
          window.location.assign(baseUrl.toString());
          return;
        }
        if (gridSearchInput) {
          gridSearchInput.value = '';
        }
        Object.keys(columnValueCatalog).forEach(function (fieldId) {
          columnFilterState[fieldId] = new Set(getAllowedValuesForField(fieldId));
          delete columnAdvancedFilterState[fieldId];
        });
        applyGridFilters();
      });
    }

    if (filterModalClose) {
      filterModalClose.addEventListener('click', closeColumnFilterModal);
    }
    if (advancedFilterModalClose) {
      advancedFilterModalClose.addEventListener('click', closeAdvancedFilterModal);
    }
    if (columnContextSubdivideButton) {
      columnContextSubdivideButton.addEventListener('click', function () {
        if (!activeContextFieldId) {
          return;
        }
        var baseUrl = buildBaseReportUrl();
        baseUrl.searchParams.set('report_grid_subdivide_field_id', activeContextFieldId);
        window.location.assign(baseUrl.toString());
      });
    }
    if (columnContextAdvancedFilterButton) {
      columnContextAdvancedFilterButton.addEventListener('click', function () {
        if (!activeContextFieldId) {
          return;
        }
        closeColumnContextMenu();
        renderAdvancedFilterModal(activeContextFieldId);
      });
    }
    if (filterModal) {
      filterModal.addEventListener('click', function (event) {
        if (event.target === filterModal) {
          closeColumnFilterModal();
        }
      });
    }
    if (advancedFilterModal) {
      advancedFilterModal.addEventListener('click', function (event) {
        if (event.target === advancedFilterModal) {
          closeAdvancedFilterModal();
        }
      });
    }
    document.addEventListener('click', function (event) {
      if (columnContextMenu && columnContextMenu.classList.contains('is-open') && !columnContextMenu.contains(event.target)) {
        closeColumnContextMenu();
      }
    });
    document.addEventListener('scroll', function () {
      closeColumnContextMenu();
    }, true);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeColumnContextMenu();
        closeAdvancedFilterModal();
      }
    });
    if (filterCheckAll) {
      filterCheckAll.addEventListener('click', function () {
        if (!activeColumnFilterFieldId) {
          return;
        }
        columnFilterState[activeColumnFilterFieldId] = new Set(getAllowedValuesForField(activeColumnFilterFieldId));
        renderColumnFilterModal(activeColumnFilterFieldId);
        applyGridFilters();
      });
    }
    if (filterUncheckAll) {
      filterUncheckAll.addEventListener('click', function () {
        if (!activeColumnFilterFieldId) {
          return;
        }
        columnFilterState[activeColumnFilterFieldId] = new Set();
        renderColumnFilterModal(activeColumnFilterFieldId);
        applyGridFilters();
      });
    }

    if (advancedFilterModalApply) {
      advancedFilterModalApply.addEventListener('click', function () {
        if (!activeAdvancedFilterFieldId) {
          return;
        }
        var fieldType = getFieldTypeForAdvancedFilter(activeAdvancedFilterFieldId);
        var valueInput = document.getElementById('report-v2-advanced-filter-value');
        var valueToInput = document.getElementById('report-v2-advanced-filter-value-to');
        var operatorInput = document.getElementById('report-v2-advanced-filter-operator');
        var value = valueInput ? String(valueInput.value || '').trim() : '';
        var valueTo = valueToInput ? String(valueToInput.value || '').trim() : '';
        var operator = operatorInput ? operatorInput.value : '>=';
        if (!value || (operator === 'between' && !valueTo)) {
          delete columnAdvancedFilterState[activeAdvancedFilterFieldId];
        } else {
          columnAdvancedFilterState[activeAdvancedFilterFieldId] = {
            kind: fieldType === 'text' ? 'text' : 'comparison',
            operator: operator,
            value: value,
            valueTo: operator === 'between' ? valueTo : ''
          };
        }
        closeAdvancedFilterModal();
        applyGridFilters();
      });
    }

    if (advancedFilterModalClear) {
      advancedFilterModalClear.addEventListener('click', function () {
        if (!activeAdvancedFilterFieldId) {
          return;
        }
        delete columnAdvancedFilterState[activeAdvancedFilterFieldId];
        closeAdvancedFilterModal();
        applyGridFilters();
      });
    }

    Object.keys(columnValueCatalog).forEach(function (fieldId) {
      columnFilterState[fieldId] = new Set(getAllowedValuesForField(fieldId));
    });
    applyGridFilters();
  }());
</script>
