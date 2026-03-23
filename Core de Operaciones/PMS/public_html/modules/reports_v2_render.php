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
$supportsCombinedRowSource = reports_v2_report_template_row_source_supports_combined($pdo);
$hasTemplateSubdivideColumn = reports_v2_report_template_has_subdivide_by_field_id_column($pdo);
$hasTemplateSubdivideLevel2Column = reports_v2_report_template_has_subdivide_by_field_id_level_2_column($pdo);
$hasTemplateSubdivideLevel3Column = reports_v2_report_template_has_subdivide_by_field_id_level_3_column($pdo);
$hasTemplateSubdivideShowTotalsLevel1Column = reports_v2_report_template_has_subdivide_show_totals_level_1_column($pdo);
$hasTemplateSubdivideShowTotalsLevel2Column = reports_v2_report_template_has_subdivide_show_totals_level_2_column($pdo);
$hasTemplateSubdivideShowTotalsLevel3Column = reports_v2_report_template_has_subdivide_show_totals_level_3_column($pdo);
$hasFieldCalculateTotalColumn = reports_v2_template_field_has_calculate_total_column($pdo);
$hasTemplateDefaultPropertyCodeColumn = reports_v2_report_template_has_column($pdo, 'default_property_code');
$hasTemplateDefaultStatusColumn = reports_v2_report_template_has_column($pdo, 'default_status');
$hasTemplateDefaultDateTypeColumn = reports_v2_report_template_has_column($pdo, 'default_date_type');
$hasTemplateDefaultDateFromColumn = reports_v2_report_template_has_column($pdo, 'default_date_from');
$hasTemplateDefaultDateToColumn = reports_v2_report_template_has_column($pdo, 'default_date_to');
$hasTemplateDefaultGridStateColumn = reports_v2_report_template_has_column($pdo, 'default_grid_state_json');

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
$reportGridSubdivideFieldIdLevel2 = isset($_REQUEST['report_grid_subdivide_field_id_level_2']) ? (int)$_REQUEST['report_grid_subdivide_field_id_level_2'] : 0;
$reportGridSubdivideFieldIdLevel3 = isset($_REQUEST['report_grid_subdivide_field_id_level_3']) ? (int)$_REQUEST['report_grid_subdivide_field_id_level_3'] : 0;
$reportGridSubdivideShowTotalsLevel1 = isset($_REQUEST['report_grid_subdivide_show_totals_level_1'])
    ? ((string)$_REQUEST['report_grid_subdivide_show_totals_level_1'] === '1' ? 1 : 0)
    : null;
$reportGridSubdivideShowTotalsLevel2 = isset($_REQUEST['report_grid_subdivide_show_totals_level_2'])
    ? ((string)$_REQUEST['report_grid_subdivide_show_totals_level_2'] === '1' ? 1 : 0)
    : null;
$reportGridSubdivideSortLevel1 = isset($_REQUEST['report_grid_subdivide_sort_level_1']) && strtolower(trim((string)$_REQUEST['report_grid_subdivide_sort_level_1'])) === 'desc'
    ? 'desc'
    : 'asc';
$reportGridSubdivideSortLevel2 = isset($_REQUEST['report_grid_subdivide_sort_level_2']) && strtolower(trim((string)$_REQUEST['report_grid_subdivide_sort_level_2'])) === 'desc'
    ? 'desc'
    : 'asc';
$reportLineItemVisualizationOptions = reports_v2_line_item_visualization_options();
$reportGridLineItemVisualization = isset($_REQUEST['report_grid_line_item_visualization']) ? trim((string)$_REQUEST['report_grid_line_item_visualization']) : 'standard';
if (!isset($reportLineItemVisualizationOptions[$reportGridLineItemVisualization])) {
    $reportGridLineItemVisualization = 'standard';
}
$activeTab = isset($_REQUEST['reports_tab']) ? trim((string)$_REQUEST['reports_tab']) : 'run';
$reportExportFormat = isset($_REQUEST['reports_export_format']) ? trim((string)$_REQUEST['reports_export_format']) : '';
$reportsManageMode = $canDesign && (
    ((int)(isset($_REQUEST['reports_manage']) ? $_REQUEST['reports_manage'] : 0) === 1)
    || in_array($activeTab, array('templates', 'calculations'), true)
);
if (!$reportsManageMode && $activeTab !== 'run') {
    $activeTab = 'run';
}
$isCreatingTemplate = (int)(isset($_REQUEST['new_report_template']) ? $_REQUEST['new_report_template'] : 0) === 1;
$rowEditDraftValues = array();
$isInlineAjaxRequest = (
    ($_SERVER['REQUEST_METHOD'] === 'POST')
    && (
        reports_v2_post('inline_ajax', '0') === '1'
        || (
            isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        )
    )
);
$runFilterRequestState = array(
    'property_code' => array(
        'present' => isset($_REQUEST['report_property_code']),
        'value' => isset($_REQUEST['report_property_code']) ? trim((string)$_REQUEST['report_property_code']) : '',
    ),
    'status' => array(
        'present' => isset($_REQUEST['report_status']),
        'value' => isset($_REQUEST['report_status']) ? trim((string)$_REQUEST['report_status']) : '',
    ),
    'date_type' => array(
        'present' => isset($_REQUEST['report_date_type']),
        'value' => isset($_REQUEST['report_date_type']) ? trim((string)$_REQUEST['report_date_type']) : '',
    ),
    'date_from' => array(
        'present' => isset($_REQUEST['report_date_from']),
        'value' => isset($_REQUEST['report_date_from']) ? trim((string)$_REQUEST['report_date_from']) : '',
    ),
    'date_to' => array(
        'present' => isset($_REQUEST['report_date_to']),
        'value' => isset($_REQUEST['report_date_to']) ? trim((string)$_REQUEST['report_date_to']) : '',
    ),
);
$runFilters = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)reports_v2_post('reports_action'));
    try {
        if ($action === 'save_template') {
            pms_require_permission('reports.design');

            $templateId = (int)reports_v2_post('template_id', 0);
            $reportName = trim((string)reports_v2_post('template_name'));
            $categoryName = trim((string)reports_v2_post('template_category_name'));
            $description = trim((string)reports_v2_post('template_description'));
            $rowSource = trim((string)reports_v2_post('template_row_source', 'reservation'));
            $lineItemTypeScope = trim((string)reports_v2_post('template_line_item_type_scope', ''));
            $subdivideByFieldId = (int)reports_v2_post('template_subdivide_by_field_id', 0);
            $subdivideByFieldIdLevel2 = (int)reports_v2_post('template_subdivide_by_field_id_level_2', 0);
            $subdivideByFieldIdLevel3 = 0;
            $subdivideShowTotalsLevel1 = reports_v2_post('template_subdivide_show_totals_level_1', '1') === '1' ? 1 : 0;
            $subdivideShowTotalsLevel2 = reports_v2_post('template_subdivide_show_totals_level_2', '1') === '1' ? 1 : 0;
            $subdivideShowTotalsLevel3 = 0;
            $isActive = reports_v2_post('template_is_active', '1') === '1' ? 1 : 0;
            if ($reportName === '') {
                throw new RuntimeException('El nombre de la plantilla es obligatorio.');
            }
            if (!isset($rowSourceOptions[$rowSource])) {
                $rowSource = 'reservation';
            }
            if (!in_array($rowSource, array('line_item', 'combined'), true)) {
                $lineItemTypeScope = null;
            } else {
                if ($rowSource === 'line_item' && !$supportsLineItemRowSource) {
                    throw new RuntimeException('Tu MySQL todavia no soporta line items como objeto por fila. Aplica bd pms/migrate_report_template_line_item_row_source.sql y vuelve a guardar la plantilla.');
                }
                if ($rowSource === 'combined' && !$supportsCombinedRowSource) {
                    throw new RuntimeException('Tu MySQL todavia no soporta el objeto por fila "combinado". Aplica bd pms/migrate_report_template_row_source_combined.sql y vuelve a guardar la plantilla.');
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
                         report_name = ?';
                $updateParams = array(
                    $reportKey,
                    $reportName,
                );
                if (reports_v2_report_template_has_category_name_column($pdo)) {
                    $updateSql .= ',
                         category_name = ?';
                    $updateParams[] = $categoryName !== '' ? $categoryName : null;
                }
                $updateSql .= ',
                         description = ?,
                         row_source = ?';
                $updateParams[] = $description !== '' ? $description : null;
                $updateParams[] = $rowSource;
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
                );
                $insertValues = array(
                    $companyId,
                    $reportKey,
                    $reportName,
                );
                if (reports_v2_report_template_has_category_name_column($pdo)) {
                    $insertColumns[] = 'category_name';
                    $insertValues[] = $categoryName !== '' ? $categoryName : null;
                }
                $insertColumns[] = 'description';
                $insertValues[] = $description !== '' ? $description : null;
                $insertColumns[] = 'row_source';
                $insertValues[] = $rowSource;
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
            if (in_array($rowSource, array('line_item', 'combined'), true)) {
                $persistedRowSource = $selectedTemplate && isset($selectedTemplate['row_source']) ? trim((string)$selectedTemplate['row_source']) : '';
                if ($persistedRowSource !== $rowSource) {
                    $errors[] = 'La plantilla se intento guardar con objeto por fila "' . $rowSource . '" pero la BD devolvio "' . ($persistedRowSource !== '' ? $persistedRowSource : 'vacio') . '". Aplica la migracion correspondiente del esquema de row_source y vuelve a guardar la plantilla.';
                }
            }
            $activeTab = 'templates';
        } elseif ($action === 'save_template_run_filters') {
            pms_require_permission('reports.design');

            $templateId = (int)reports_v2_post('template_id', 0);
            $template = reports_v2_fetch_template($pdo, $companyId, $templateId);
            if (!$template) {
                throw new RuntimeException('Selecciona una plantilla valida para guardar sus filtros.');
            }
            if (
                !$hasTemplateDefaultPropertyCodeColumn
                || !$hasTemplateDefaultStatusColumn
                || !$hasTemplateDefaultDateTypeColumn
                || !$hasTemplateDefaultDateFromColumn
                || !$hasTemplateDefaultDateToColumn
            ) {
                throw new RuntimeException('Aplica bd pms/migrate_report_template_default_filters.sql para guardar filtros por defecto en la plantilla.');
            }

            $propertyCode = trim((string)reports_v2_post('report_property_code'));
            $status = trim((string)reports_v2_post('report_status'));
            $dateType = trim((string)reports_v2_post('report_date_type', 'check_in_date'));
            $dateFrom = trim((string)reports_v2_post('report_date_from'));
            $dateTo = trim((string)reports_v2_post('report_date_to'));
            $defaultGridStateJson = trim((string)reports_v2_post('report_default_grid_state_json'));

            $propertyCodeValid = $propertyCode === '';
            if (!$propertyCodeValid) {
                foreach ($properties as $propertyRow) {
                    $candidateCode = isset($propertyRow['property_code']) ? trim((string)$propertyRow['property_code']) : '';
                    if ($candidateCode !== '' && strtoupper($candidateCode) === strtoupper($propertyCode)) {
                        $propertyCodeValid = true;
                        $propertyCode = $candidateCode;
                        break;
                    }
                }
            }
            if (!$propertyCodeValid) {
                throw new RuntimeException('Selecciona una propiedad valida para el filtro por defecto.');
            }

            $validStatuses = array('', 'activas', 'apartado', 'confirmado', 'en casa', 'salida', 'no-show', 'cancelada');
            if (!in_array($status, $validStatuses, true)) {
                $status = 'activas';
            }
            if (!isset($dateTypeOptions[$dateType])) {
                $dateType = 'check_in_date';
            }
            if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $dateFrom = date('Y-m-01');
            }
            if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $dateTo = date('Y-m-t');
            }
            if ($dateFrom > $dateTo) {
                $dateSwap = $dateFrom;
                $dateFrom = $dateTo;
                $dateTo = $dateSwap;
            }

            $normalizedDefaultGridStateJson = null;
            if ($defaultGridStateJson !== '') {
                if (!$hasTemplateDefaultGridStateColumn) {
                    throw new RuntimeException('Aplica bd pms/migrate_report_template_default_grid_state.sql para guardar filtros de columnas en la plantilla.');
                }
                $decodedGridState = json_decode($defaultGridStateJson, true);
                if (!is_array($decodedGridState)) {
                    throw new RuntimeException('El estado de filtros del grid no es valido.');
                }
                $normalizedGridState = array(
                    'column_filters' => array(),
                    'advanced_filters' => array(),
                );
                if (isset($decodedGridState['column_filters']) && is_array($decodedGridState['column_filters'])) {
                    foreach ($decodedGridState['column_filters'] as $fieldId => $allowedValues) {
                        $fieldKey = (string)(int)$fieldId;
                        if ($fieldKey === '0' || !is_array($allowedValues)) {
                            continue;
                        }
                        $normalizedGridState['column_filters'][$fieldKey] = array_values(array_map('strval', $allowedValues));
                    }
                }
                if (isset($decodedGridState['advanced_filters']) && is_array($decodedGridState['advanced_filters'])) {
                    foreach ($decodedGridState['advanced_filters'] as $fieldId => $filterState) {
                        $fieldKey = (string)(int)$fieldId;
                        if ($fieldKey === '0' || !is_array($filterState)) {
                            continue;
                        }
                        $value = isset($filterState['value']) ? trim((string)$filterState['value']) : '';
                        $valueTo = isset($filterState['valueTo']) ? trim((string)$filterState['valueTo']) : '';
                        $operator = isset($filterState['operator']) ? trim((string)$filterState['operator']) : '=';
                        if ($value === '' && $valueTo === '') {
                            continue;
                        }
                        $normalizedGridState['advanced_filters'][$fieldKey] = array(
                            'operator' => $operator !== '' ? $operator : '=',
                            'value' => $value,
                            'valueTo' => $valueTo,
                        );
                    }
                }
                if (!empty($normalizedGridState['column_filters']) || !empty($normalizedGridState['advanced_filters'])) {
                    $normalizedDefaultGridStateJson = json_encode($normalizedGridState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $updateColumns = array(
                'default_property_code = ?',
                'default_status = ?',
                'default_date_type = ?',
                'default_date_from = ?',
                'default_date_to = ?',
            );
            $updateParams = array(
                $propertyCode !== '' ? $propertyCode : null,
                $status !== '' ? $status : null,
                $dateType,
                $dateFrom,
                $dateTo,
            );
            if ($hasTemplateDefaultGridStateColumn) {
                $updateColumns[] = 'default_grid_state_json = ?';
                $updateParams[] = $normalizedDefaultGridStateJson;
            }
            $updateColumns[] = 'updated_at = NOW()';
            $stmt = $pdo->prepare(
                'UPDATE report_template
                    SET ' . implode(",\n                        ", $updateColumns) . '
                  WHERE id_report_template = ?
                    AND id_company = ?'
            );
            $updateParams[] = $templateId;
            $updateParams[] = $companyId;
            $stmt->execute($updateParams);
            $selectedTemplateId = $templateId;
            $selectedTemplate = reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId);
            $messages[] = 'Filtros de ejecucion guardados en la plantilla.';
            $activeTab = 'run';
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
        } elseif ($action === 'clone_template') {
            pms_require_permission('reports.design');

            $templateId = (int)reports_v2_post('template_id', 0);
            $template = reports_v2_fetch_template($pdo, $companyId, $templateId);
            if (!$template) {
                throw new RuntimeException('Selecciona una plantilla valida para clonar.');
            }

            $cloneName = trim((string)(isset($template['report_name']) ? $template['report_name'] : '')) . ' (copia)';
            if ($cloneName === ' (copia)') {
                $cloneName = 'Plantilla clonada';
            }
            $cloneKey = reports_v2_build_unique_code($pdo, 'report_template', 'id_report_template', 'report_key', $companyId, $cloneName, 0);

            $pdo->beginTransaction();
            try {
                $insertColumns = array(
                    'id_company',
                    'report_key',
                    'report_name',
                );
                $insertValues = array(
                    $companyId,
                    $cloneKey,
                    $cloneName,
                );

                if (reports_v2_report_template_has_category_name_column($pdo)) {
                    $insertColumns[] = 'category_name';
                    $insertValues[] = isset($template['category_name']) && trim((string)$template['category_name']) !== ''
                        ? (string)$template['category_name']
                        : null;
                }

                $insertColumns[] = 'description';
                $insertValues[] = isset($template['description']) && trim((string)$template['description']) !== ''
                    ? (string)$template['description']
                    : null;
                $insertColumns[] = 'row_source';
                $insertValues[] = isset($template['row_source']) ? (string)$template['row_source'] : 'reservation';

                if (reports_v2_report_template_has_line_item_type_scope_column($pdo)) {
                    $insertColumns[] = 'line_item_type_scope';
                    $insertValues[] = isset($template['line_item_type_scope']) && trim((string)$template['line_item_type_scope']) !== ''
                        ? (string)$template['line_item_type_scope']
                        : null;
                }
                if ($hasTemplateDefaultPropertyCodeColumn) {
                    $insertColumns[] = 'default_property_code';
                    $insertValues[] = isset($template['default_property_code']) && trim((string)$template['default_property_code']) !== ''
                        ? (string)$template['default_property_code']
                        : null;
                }
                if ($hasTemplateDefaultStatusColumn) {
                    $insertColumns[] = 'default_status';
                    $insertValues[] = isset($template['default_status']) && trim((string)$template['default_status']) !== ''
                        ? (string)$template['default_status']
                        : null;
                }
                if ($hasTemplateDefaultDateTypeColumn) {
                    $insertColumns[] = 'default_date_type';
                    $insertValues[] = isset($template['default_date_type']) && trim((string)$template['default_date_type']) !== ''
                        ? (string)$template['default_date_type']
                        : null;
                }
                if ($hasTemplateDefaultDateFromColumn) {
                    $insertColumns[] = 'default_date_from';
                    $insertValues[] = isset($template['default_date_from']) && trim((string)$template['default_date_from']) !== ''
                        ? (string)$template['default_date_from']
                        : null;
                }
                if ($hasTemplateDefaultDateToColumn) {
                    $insertColumns[] = 'default_date_to';
                    $insertValues[] = isset($template['default_date_to']) && trim((string)$template['default_date_to']) !== ''
                        ? (string)$template['default_date_to']
                        : null;
                }
                if ($hasTemplateDefaultGridStateColumn) {
                    $insertColumns[] = 'default_grid_state_json';
                    $insertValues[] = isset($template['default_grid_state_json']) && trim((string)$template['default_grid_state_json']) !== ''
                        ? (string)$template['default_grid_state_json']
                        : null;
                }
                if ($hasTemplateSubdivideColumn) {
                    $insertColumns[] = 'subdivide_by_field_id';
                    $insertValues[] = null;
                }
                if ($hasTemplateSubdivideLevel2Column) {
                    $insertColumns[] = 'subdivide_by_field_id_level_2';
                    $insertValues[] = null;
                }
                if ($hasTemplateSubdivideLevel3Column) {
                    $insertColumns[] = 'subdivide_by_field_id_level_3';
                    $insertValues[] = null;
                }
                if ($hasTemplateSubdivideShowTotalsLevel1Column) {
                    $insertColumns[] = 'subdivide_show_totals_level_1';
                    $insertValues[] = !isset($template['subdivide_show_totals_level_1']) || !empty($template['subdivide_show_totals_level_1']) ? 1 : 0;
                }
                if ($hasTemplateSubdivideShowTotalsLevel2Column) {
                    $insertColumns[] = 'subdivide_show_totals_level_2';
                    $insertValues[] = !isset($template['subdivide_show_totals_level_2']) || !empty($template['subdivide_show_totals_level_2']) ? 1 : 0;
                }
                if ($hasTemplateSubdivideShowTotalsLevel3Column) {
                    $insertColumns[] = 'subdivide_show_totals_level_3';
                    $insertValues[] = !isset($template['subdivide_show_totals_level_3']) || !empty($template['subdivide_show_totals_level_3']) ? 1 : 0;
                }

                $insertColumns = array_merge($insertColumns, array(
                    'is_active',
                    'created_by',
                    'created_at',
                    'updated_at'
                ));
                $insertValues[] = !isset($template['is_active']) || !empty($template['is_active']) ? 1 : 0;
                $insertValues[] = $actorUserId > 0 ? $actorUserId : null;

                $templateInsertSql = 'INSERT INTO report_template (
                        ' . implode(",\n                        ", $insertColumns) . '
                    ) VALUES (' . implode(', ', array_fill(0, count($insertValues), '?')) . ', NOW(), NOW())';
                $templateInsertStmt = $pdo->prepare($templateInsertSql);
                $templateInsertStmt->execute($insertValues);
                $clonedTemplateId = (int)$pdo->lastInsertId();

                $sourceFields = reports_v2_fetch_template_fields($pdo, $templateId);
                $fieldIdMap = array();
                foreach ($sourceFields as $fieldRow) {
                    $fieldInsertColumns = array(
                        'id_report_template',
                        'field_type',
                        'display_name',
                    );
                    $fieldInsertValues = array(
                        $clonedTemplateId,
                        isset($fieldRow['field_type']) ? (string)$fieldRow['field_type'] : 'reservation',
                        isset($fieldRow['display_name']) ? (string)$fieldRow['display_name'] : '',
                    );
                    if (reports_v2_template_field_has_default_value_column($pdo)) {
                        $fieldInsertColumns[] = 'default_value';
                        $fieldInsertValues[] = isset($fieldRow['default_value']) && trim((string)$fieldRow['default_value']) !== ''
                            ? (string)$fieldRow['default_value']
                            : null;
                    }
                    if (reports_v2_template_field_has_is_editable_column($pdo)) {
                        $fieldInsertColumns[] = 'is_editable';
                        $fieldInsertValues[] = !empty($fieldRow['is_editable']) ? 1 : 0;
                    }
                    if ($hasFieldCalculateTotalColumn) {
                        $fieldInsertColumns[] = 'calculate_total';
                        $fieldInsertValues[] = !empty($fieldRow['calculate_total']) ? 1 : 0;
                    }
                    if (reports_v2_template_field_has_allow_multiple_catalogs_column($pdo)) {
                        $fieldInsertColumns[] = 'allow_multiple_catalogs';
                        $fieldInsertValues[] = !empty($fieldRow['allow_multiple_catalogs']) ? 1 : 0;
                    }
                    $fieldInsertColumns[] = 'reservation_field_code';
                    $fieldInsertValues[] = isset($fieldRow['reservation_field_code']) && trim((string)$fieldRow['reservation_field_code']) !== ''
                        ? (string)$fieldRow['reservation_field_code']
                        : null;
                    $fieldInsertColumns[] = 'id_line_item_catalog';
                    $fieldInsertValues[] = !empty($fieldRow['id_line_item_catalog']) ? (int)$fieldRow['id_line_item_catalog'] : null;
                    $fieldInsertColumns[] = 'id_report_calculation';
                    $fieldInsertValues[] = !empty($fieldRow['id_report_calculation']) ? (int)$fieldRow['id_report_calculation'] : null;
                    $fieldInsertColumns[] = 'source_metric';
                    $fieldInsertValues[] = isset($fieldRow['source_metric']) && trim((string)$fieldRow['source_metric']) !== ''
                        ? (string)$fieldRow['source_metric']
                        : null;
                    $fieldInsertColumns[] = 'format_hint';
                    $fieldInsertValues[] = isset($fieldRow['format_hint']) ? (string)$fieldRow['format_hint'] : 'auto';
                    $fieldInsertColumns[] = 'order_index';
                    $fieldInsertValues[] = isset($fieldRow['order_index']) ? (int)$fieldRow['order_index'] : 1;
                    $fieldInsertColumns[] = 'is_visible';
                    $fieldInsertValues[] = !isset($fieldRow['is_visible']) || !empty($fieldRow['is_visible']) ? 1 : 0;
                    $fieldInsertColumns[] = 'is_active';
                    $fieldInsertValues[] = !isset($fieldRow['is_active']) || !empty($fieldRow['is_active']) ? 1 : 0;
                    $fieldInsertColumns[] = 'created_by';
                    $fieldInsertValues[] = $actorUserId > 0 ? $actorUserId : null;
                    $fieldInsertColumns[] = 'created_at';
                    $fieldInsertColumns[] = 'updated_at';

                    $fieldInsertSql = 'INSERT INTO report_template_field (
                            ' . implode(",\n                            ", $fieldInsertColumns) . '
                        ) VALUES (' . implode(', ', array_fill(0, count($fieldInsertValues), '?')) . ', NOW(), NOW())';
                    $fieldInsertStmt = $pdo->prepare($fieldInsertSql);
                    $fieldInsertStmt->execute($fieldInsertValues);
                    $fieldIdMap[(int)$fieldRow['id_report_template_field']] = (int)$pdo->lastInsertId();
                }

                if (!empty($fieldIdMap)) {
                    $sourceFieldIds = array_keys($fieldIdMap);
                    $catalogPlaceholders = implode(', ', array_fill(0, count($sourceFieldIds), '?'));
                    $catalogStmt = $pdo->prepare(
                        'SELECT id_report_template_field, id_line_item_catalog, sort_order
                           FROM report_template_field_catalog
                          WHERE id_report_template_field IN (' . $catalogPlaceholders . ')
                          ORDER BY id_report_template_field_catalog'
                    );
                    $catalogStmt->execute($sourceFieldIds);
                    $catalogRows = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($catalogRows)) {
                        $catalogInsertStmt = $pdo->prepare(
                            'INSERT INTO report_template_field_catalog (
                                id_report_template_field,
                                id_line_item_catalog,
                                sort_order,
                                created_at,
                                created_by
                             ) VALUES (?, ?, ?, NOW(), ?)'
                        );
                        foreach ($catalogRows as $catalogRow) {
                            $oldFieldId = isset($catalogRow['id_report_template_field']) ? (int)$catalogRow['id_report_template_field'] : 0;
                            if ($oldFieldId <= 0 || !isset($fieldIdMap[$oldFieldId])) {
                                continue;
                            }
                            $catalogInsertStmt->execute(array(
                                $fieldIdMap[$oldFieldId],
                                isset($catalogRow['id_line_item_catalog']) ? (int)$catalogRow['id_line_item_catalog'] : 0,
                                isset($catalogRow['sort_order']) ? (int)$catalogRow['sort_order'] : 1,
                                $actorUserId > 0 ? $actorUserId : null,
                            ));
                        }
                    }
                }

                if ($hasTemplateSubdivideColumn || $hasTemplateSubdivideLevel2Column || $hasTemplateSubdivideLevel3Column) {
                    $subdivideUpdateColumns = array();
                    $subdivideUpdateParams = array();
                    if ($hasTemplateSubdivideColumn) {
                        $oldSubdivideFieldId = isset($template['subdivide_by_field_id']) ? (int)$template['subdivide_by_field_id'] : 0;
                        $subdivideUpdateColumns[] = 'subdivide_by_field_id = ?';
                        $subdivideUpdateParams[] = ($oldSubdivideFieldId > 0 && isset($fieldIdMap[$oldSubdivideFieldId])) ? $fieldIdMap[$oldSubdivideFieldId] : null;
                    }
                    if ($hasTemplateSubdivideLevel2Column) {
                        $oldSubdivideFieldIdLevel2 = isset($template['subdivide_by_field_id_level_2']) ? (int)$template['subdivide_by_field_id_level_2'] : 0;
                        $subdivideUpdateColumns[] = 'subdivide_by_field_id_level_2 = ?';
                        $subdivideUpdateParams[] = ($oldSubdivideFieldIdLevel2 > 0 && isset($fieldIdMap[$oldSubdivideFieldIdLevel2])) ? $fieldIdMap[$oldSubdivideFieldIdLevel2] : null;
                    }
                    if ($hasTemplateSubdivideLevel3Column) {
                        $oldSubdivideFieldIdLevel3 = isset($template['subdivide_by_field_id_level_3']) ? (int)$template['subdivide_by_field_id_level_3'] : 0;
                        $subdivideUpdateColumns[] = 'subdivide_by_field_id_level_3 = ?';
                        $subdivideUpdateParams[] = ($oldSubdivideFieldIdLevel3 > 0 && isset($fieldIdMap[$oldSubdivideFieldIdLevel3])) ? $fieldIdMap[$oldSubdivideFieldIdLevel3] : null;
                    }
                    if (!empty($subdivideUpdateColumns)) {
                        $subdivideUpdateParams[] = $clonedTemplateId;
                        $subdivideUpdateParams[] = $companyId;
                        $subdivideStmt = $pdo->prepare(
                            'UPDATE report_template
                                SET ' . implode(",\n                                    ", $subdivideUpdateColumns) . ',
                                    updated_at = NOW()
                              WHERE id_report_template = ?
                                AND id_company = ?'
                        );
                        $subdivideStmt->execute($subdivideUpdateParams);
                    }
                }

                if ($hasTemplateDefaultGridStateColumn) {
                    $clonedDefaultGridStateJson = null;
                    if (isset($template['default_grid_state_json']) && trim((string)$template['default_grid_state_json']) !== '') {
                        $decodedClonedGridState = json_decode((string)$template['default_grid_state_json'], true);
                        if (is_array($decodedClonedGridState)) {
                            $remappedGridState = array(
                                'column_filters' => array(),
                                'advanced_filters' => array(),
                            );
                            if (isset($decodedClonedGridState['column_filters']) && is_array($decodedClonedGridState['column_filters'])) {
                                foreach ($decodedClonedGridState['column_filters'] as $fieldId => $allowedValues) {
                                    $oldFieldId = (int)$fieldId;
                                    if ($oldFieldId <= 0 || !isset($fieldIdMap[$oldFieldId]) || !is_array($allowedValues)) {
                                        continue;
                                    }
                                    $remappedGridState['column_filters'][(string)$fieldIdMap[$oldFieldId]] = array_values(array_map('strval', $allowedValues));
                                }
                            }
                            if (isset($decodedClonedGridState['advanced_filters']) && is_array($decodedClonedGridState['advanced_filters'])) {
                                foreach ($decodedClonedGridState['advanced_filters'] as $fieldId => $filterState) {
                                    $oldFieldId = (int)$fieldId;
                                    if ($oldFieldId <= 0 || !isset($fieldIdMap[$oldFieldId]) || !is_array($filterState)) {
                                        continue;
                                    }
                                    $remappedGridState['advanced_filters'][(string)$fieldIdMap[$oldFieldId]] = array(
                                        'operator' => isset($filterState['operator']) ? (string)$filterState['operator'] : '=',
                                        'value' => isset($filterState['value']) ? (string)$filterState['value'] : '',
                                        'valueTo' => isset($filterState['valueTo']) ? (string)$filterState['valueTo'] : '',
                                    );
                                }
                            }
                            if (!empty($remappedGridState['column_filters']) || !empty($remappedGridState['advanced_filters'])) {
                                $clonedDefaultGridStateJson = json_encode($remappedGridState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        }
                    }
                    $gridStateStmt = $pdo->prepare(
                        'UPDATE report_template
                            SET default_grid_state_json = ?,
                                updated_at = NOW()
                          WHERE id_report_template = ?
                            AND id_company = ?'
                    );
                    $gridStateStmt->execute(array(
                        $clonedDefaultGridStateJson,
                        $clonedTemplateId,
                        $companyId,
                    ));
                }

                $pdo->commit();
                $selectedTemplateId = $clonedTemplateId;
                $selectedFieldId = 0;
                $selectedTemplate = reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId);
                $messages[] = 'Plantilla clonada.';
                $activeTab = 'templates';
            } catch (Exception $cloneException) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $cloneException;
            }
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
            if ($isInlineAjaxRequest) {
                $refreshedContext = reports_v2_fetch_editable_reservation_context($pdo, $companyId, $editReservationId);
                if (!$refreshedContext) {
                    throw new RuntimeException('No fue posible volver a cargar la reservacion despues del guardado.');
                }
                $inlineCells = reports_v2_build_inline_reservation_field_payload(
                    $templateFields,
                    $refreshedContext,
                    'MXN'
                );
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'ok' => true,
                    'updated' => !empty($updated),
                    'message' => $updated ? 'Fila actualizada.' : 'No hubo cambios para guardar.',
                    'reservation_id' => $editReservationId,
                    'cells' => $inlineCells,
                ));
                return;
            }
            $messages[] = $updated ? 'Fila actualizada.' : 'No hubo cambios para guardar.';
            $activeTab = 'run';
            $editReservationId = 0;
            $rowEditDraftValues = array();
        }
    } catch (Exception $e) {
        if ($isInlineAjaxRequest) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'ok' => false,
                'message' => $e->getMessage(),
            ));
            return;
        }
        $errors[] = $e->getMessage();
    }
}

$templates = reports_v2_fetch_templates($pdo, $companyId);
if ($selectedTemplateId <= 0 && !empty($templates) && !($activeTab === 'templates' && $isCreatingTemplate)) {
    $selectedTemplateId = isset($templates[0]['id_report_template']) ? (int)$templates[0]['id_report_template'] : 0;
}
$selectedTemplate = $selectedTemplateId > 0 ? reports_v2_fetch_template($pdo, $companyId, $selectedTemplateId) : null;
$isCreatingTemplate = $activeTab === 'templates' && $isCreatingTemplate;
if ($isCreatingTemplate) {
    $selectedTemplateId = 0;
    $selectedTemplate = null;
    $selectedFieldId = 0;
}
$templateDefaultPropertyCode = (
    $selectedTemplate
    && $hasTemplateDefaultPropertyCodeColumn
    && isset($selectedTemplate['default_property_code'])
) ? trim((string)$selectedTemplate['default_property_code']) : '';
$templateDefaultStatus = (
    $selectedTemplate
    && $hasTemplateDefaultStatusColumn
    && isset($selectedTemplate['default_status'])
) ? trim((string)$selectedTemplate['default_status']) : '';
$templateDefaultDateType = (
    $selectedTemplate
    && $hasTemplateDefaultDateTypeColumn
    && isset($selectedTemplate['default_date_type'])
) ? trim((string)$selectedTemplate['default_date_type']) : '';
$templateDefaultDateFrom = (
    $selectedTemplate
    && $hasTemplateDefaultDateFromColumn
    && isset($selectedTemplate['default_date_from'])
) ? trim((string)$selectedTemplate['default_date_from']) : '';
$templateDefaultDateTo = (
    $selectedTemplate
    && $hasTemplateDefaultDateToColumn
    && isset($selectedTemplate['default_date_to'])
) ? trim((string)$selectedTemplate['default_date_to']) : '';
$templateDefaultGridState = array(
    'column_filters' => array(),
    'advanced_filters' => array(),
);
if (
    $selectedTemplate
    && $hasTemplateDefaultGridStateColumn
    && isset($selectedTemplate['default_grid_state_json'])
) {
    $templateDefaultGridStateRaw = trim((string)$selectedTemplate['default_grid_state_json']);
    if ($templateDefaultGridStateRaw !== '') {
        $decodedTemplateDefaultGridState = json_decode($templateDefaultGridStateRaw, true);
        if (is_array($decodedTemplateDefaultGridState)) {
            if (isset($decodedTemplateDefaultGridState['column_filters']) && is_array($decodedTemplateDefaultGridState['column_filters'])) {
                $templateDefaultGridState['column_filters'] = $decodedTemplateDefaultGridState['column_filters'];
            }
            if (isset($decodedTemplateDefaultGridState['advanced_filters']) && is_array($decodedTemplateDefaultGridState['advanced_filters'])) {
                $templateDefaultGridState['advanced_filters'] = $decodedTemplateDefaultGridState['advanced_filters'];
            }
        }
    }
}
$templateDefaultGridStateJson = json_encode($templateDefaultGridState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($templateDefaultGridStateJson === false) {
    $templateDefaultGridStateJson = '{"column_filters":{},"advanced_filters":{}}';
}
$runFilters = array(
    'property_code' => $runFilterRequestState['property_code']['present']
        ? $runFilterRequestState['property_code']['value']
        : $templateDefaultPropertyCode,
    'status' => $runFilterRequestState['status']['present']
        ? $runFilterRequestState['status']['value']
        : ($templateDefaultStatus !== '' ? $templateDefaultStatus : 'activas'),
    'date_type' => $runFilterRequestState['date_type']['present']
        ? $runFilterRequestState['date_type']['value']
        : ($templateDefaultDateType !== '' ? $templateDefaultDateType : 'check_in_date'),
    'date_from' => $runFilterRequestState['date_from']['present']
        ? $runFilterRequestState['date_from']['value']
        : ($templateDefaultDateFrom !== '' ? $templateDefaultDateFrom : date('Y-m-01')),
    'date_to' => $runFilterRequestState['date_to']['present']
        ? $runFilterRequestState['date_to']['value']
        : ($templateDefaultDateTo !== '' ? $templateDefaultDateTo : date('Y-m-t')),
    'search' => '',
);
if (!isset($dateTypeOptions[$runFilters['date_type']])) {
    $runFilters['date_type'] = 'check_in_date';
}
if ($runFilters['date_from'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $runFilters['date_from'])) {
    $runFilters['date_from'] = date('Y-m-01');
}
if ($runFilters['date_to'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $runFilters['date_to'])) {
    $runFilters['date_to'] = date('Y-m-t');
}
if ($runFilters['date_from'] > $runFilters['date_to']) {
    $runDateSwap = $runFilters['date_from'];
    $runFilters['date_from'] = $runFilters['date_to'];
    $runFilters['date_to'] = $runDateSwap;
}
$templateFields = $selectedTemplate ? reports_v2_fetch_template_fields($pdo, $selectedTemplateId) : array();
$editableReservationFields = reports_v2_template_editable_reservation_fields($templateFields);
$hasEditableReservationFields = !empty($editableReservationFields);
$displayVariableCatalog = reports_v2_build_variable_catalog(
    reports_v2_filter_line_item_catalogs_for_template($lineItemCatalogs, $templateFields)
);
$lineItemCatalogTypeLabels = array(
    'sale_item' => 'Cargo / servicio',
    'tax_rule' => 'Impuestos',
    'payment' => 'Pagos',
    'obligation' => 'Obligaciones',
    'income' => 'Ingresos',
);
$lineItemFilterCatalogByField = array();
foreach ($templateFields as $fieldRow) {
    $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0;
    if ($fieldId <= 0) {
        continue;
    }
    $fieldType = isset($fieldRow['field_type']) ? (string)$fieldRow['field_type'] : '';
    $sourceMetric = isset($fieldRow['source_metric']) ? (string)$fieldRow['source_metric'] : 'amount_cents';
    if ($fieldType !== 'line_item' || !in_array($sourceMetric, array('item_name', 'item_name_parent'), true)) {
        continue;
    }

    $groups = array();
    foreach ($lineItemCatalogs as $catalogRow) {
        $catalogType = isset($catalogRow['catalog_type']) ? (string)$catalogRow['catalog_type'] : '';
        $groupKey = $catalogType !== '' ? $catalogType : 'other';
        $groupLabel = isset($lineItemCatalogTypeLabels[$groupKey]) ? $lineItemCatalogTypeLabels[$groupKey] : ucfirst(str_replace('_', ' ', $groupKey));
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = array(
                'key' => $groupKey,
                'label' => $groupLabel,
                'options' => array(),
            );
        }

        $itemName = trim((string)(isset($catalogRow['item_name']) ? $catalogRow['item_name'] : ''));
        if ($itemName === '') {
            $itemName = 'Line item #' . (int)(isset($catalogRow['id_line_item_catalog']) ? $catalogRow['id_line_item_catalog'] : 0);
        }
        $optionValue = $itemName;
        $optionLabel = $itemName;
        if ($sourceMetric === 'item_name_parent') {
            $parentName = trim((string)(isset($catalogRow['category_name']) ? $catalogRow['category_name'] : ''));
            if ($parentName === '') {
                $parentName = trim((string)(isset($catalogRow['subcategory_name']) ? $catalogRow['subcategory_name'] : ''));
            }
            if ($parentName === '') {
                $parentName = $itemName;
            }
            $optionValue = reports_v2_line_item_name_parent_token_build($parentName);
            $optionLabel = $parentName;
        }

        $groups[$groupKey]['options'][$optionValue] = array(
            'value' => $optionValue,
            'label' => $optionLabel,
        );
    }

    foreach ($groups as $groupKey => $groupMeta) {
        $groups[$groupKey]['options'] = array_values($groupMeta['options']);
    }
    $lineItemFilterCatalogByField[(string)$fieldId] = array_values($groups);
}
$lineItemFilterCatalogByFieldJson = json_encode($lineItemFilterCatalogByField, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($lineItemFilterCatalogByFieldJson === false) {
    $lineItemFilterCatalogByFieldJson = '{}';
}
$nextFieldOrder = 4;
if (!empty($templateFields)) {
    $maxFieldOrder = 0;
    foreach ($templateFields as $fieldRow) {
        $rowOrder = isset($fieldRow['order_index']) ? (int)$fieldRow['order_index'] : 0;
        if ($rowOrder > $maxFieldOrder) {
            $maxFieldOrder = $rowOrder;
        }
    }
    $nextFieldOrder = $maxFieldOrder + 4;
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
                $allowCurrentRecordCatalog = in_array($templateRowSource, array('line_item', 'combined'), true);
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
                            ? reports_v2_line_item_name_parent_token_build('Registro actual')
                            : 'Nombre';
                    } elseif (!empty($selectedCatalogs)) {
                        $primaryCatalog = $selectedCatalogs[0];
                        $baseItemName = trim((string)(isset($primaryCatalog['item_name']) ? $primaryCatalog['item_name'] : ''));
                        if ($lineItemDisplayNameMode === 'name_parent') {
                            $displayName = reports_v2_line_item_name_parent_token_build($baseItemName !== '' ? $baseItemName : 'Nombre');
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
                $lineItemCatalogId = !empty($selectedCatalogIds) ? (int)$selectedCatalogIds[0] : 0;
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
$reportRuntimeSubdivideShowTotalsLevel1 = true;
$reportRuntimeSubdivideShowTotalsLevel2 = true;
$reportLineItemVisualizationAvailable = false;
$reportUsingLineItemFolioVisualization = false;
$runTemplate = null;
if ($canRun && $selectedTemplate && !empty($templateFields)) {
    try {
        $runTemplate = $selectedTemplate;
        $selectedRowSource = isset($selectedTemplate['row_source']) ? (string)$selectedTemplate['row_source'] : 'reservation';
        $selectedLineItemTypeScope = isset($selectedTemplate['line_item_type_scope']) ? (string)$selectedTemplate['line_item_type_scope'] : '';
        $reportLineItemVisualizationAvailable = reports_v2_line_item_folio_visualization_applicable($selectedTemplate);
        if (!$reportLineItemVisualizationAvailable) {
            $reportGridLineItemVisualization = 'standard';
        } elseif ($reportGridLineItemVisualization === 'folio') {
            $reportUsingLineItemFolioVisualization = true;
        }
        $baseRows = reports_v2_fetch_report_base_rows(
            $pdo,
            $companyId,
            $runFilters,
            500,
            $selectedRowSource === 'combined' ? 'reservation' : $selectedRowSource,
            $selectedLineItemTypeScope
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
        if ($selectedRowSource === 'combined') {
            $lineItemBaseRows = reports_v2_fetch_line_item_base_rows_for_reservations($pdo, $reservationIds, $selectedLineItemTypeScope);
            $lineItemBaseIds = array();
            foreach ($lineItemBaseRows as $lineItemBaseRow) {
                $lineItemBaseIds[] = isset($lineItemBaseRow['base_line_item_id']) ? (int)$lineItemBaseRow['base_line_item_id'] : 0;
            }
            $combinedTreeMetrics = reports_v2_fetch_line_item_tree_metrics($pdo, $lineItemBaseIds);
            list($reportRows, $reportTotals) = reports_v2_build_combined_report_rows(
                $baseRows,
                $lineItemBaseRows,
                $templateFields,
                $calculationsById,
                $lineItemMetrics,
                $variableCatalog,
                $selectedTemplate,
                $combinedTreeMetrics
            );
        } else {
            list($reportRows, $reportTotals) = reports_v2_build_report_rows(
                $baseRows,
                $templateFields,
                $calculationsById,
                $lineItemMetrics,
                $variableCatalog,
                $selectedTemplate,
                $lineItemTreeMetrics
            );
        }
        if ($reportUsingLineItemFolioVisualization) {
            $reportRows = reports_v2_prepare_line_item_rows_for_folio_visualization($reportRows, $templateFields);
        }
        foreach ($templateFields as $templateFieldRow) {
            if (reports_v2_field_calculates_total($templateFieldRow)) {
                $reportHasCalculatedTotals = true;
                break;
            }
        }
        if ($reportHasCalculatedTotals && !empty($reportRows)) {
            $reportDisplayTotals = reports_v2_compute_display_totals($reportRows, $templateFields, 'Totales generales');
        }
        if ($reportUsingLineItemFolioVisualization) {
            $reportSubdivisions = reports_v2_build_line_item_folio_visualization($reportRows, $templateFields);
            $reportSubdivideByFieldId = 0;
            $reportSubdivideByFieldIdLevel2 = 0;
            $reportSubdivideByFieldIdLevel3 = 0;
        } elseif ($hasTemplateSubdivideColumn) {
            $hasRuntimeSubdivide = $reportGridSubdivideFieldId > 0
                || $reportGridSubdivideFieldIdLevel2 > 0
                || $reportGridSubdivideFieldIdLevel3 > 0;
            $reportSubdivideByFieldId = $hasRuntimeSubdivide
                ? $reportGridSubdivideFieldId
                : (isset($selectedTemplate['subdivide_by_field_id']) ? (int)$selectedTemplate['subdivide_by_field_id'] : 0);
            $reportSubdivideByFieldIdLevel2 = $hasRuntimeSubdivide
                ? $reportGridSubdivideFieldIdLevel2
                : (isset($selectedTemplate['subdivide_by_field_id_level_2']) ? (int)$selectedTemplate['subdivide_by_field_id_level_2'] : 0);
            $reportSubdivideByFieldIdLevel3 = 0;
            if ($reportSubdivideByFieldId <= 0) {
                $reportSubdivideByFieldIdLevel2 = 0;
            }
            if ($reportSubdivideByFieldId > 0 && $reportSubdivideByFieldIdLevel2 > 0 && $reportSubdivideByFieldIdLevel2 === $reportSubdivideByFieldId) {
                $reportSubdivideByFieldIdLevel2 = 0;
            }
            $reportSubdivisionShowTotals = array(
                1 => isset($selectedTemplate['subdivide_show_totals_level_1']) ? !empty($selectedTemplate['subdivide_show_totals_level_1']) : true,
                2 => isset($selectedTemplate['subdivide_show_totals_level_2']) ? !empty($selectedTemplate['subdivide_show_totals_level_2']) : true,
                3 => isset($selectedTemplate['subdivide_show_totals_level_3']) ? !empty($selectedTemplate['subdivide_show_totals_level_3']) : true,
            );
            if ($reportGridSubdivideShowTotalsLevel1 !== null) {
                $reportSubdivisionShowTotals[1] = (bool)$reportGridSubdivideShowTotalsLevel1;
            }
            if ($reportGridSubdivideShowTotalsLevel2 !== null) {
                $reportSubdivisionShowTotals[2] = (bool)$reportGridSubdivideShowTotalsLevel2;
            }
            $reportRuntimeSubdivideShowTotalsLevel1 = !empty($reportSubdivisionShowTotals[1]);
            $reportRuntimeSubdivideShowTotalsLevel2 = !empty($reportSubdivisionShowTotals[2]);
            if ($reportSubdivideByFieldId > 0) {
                $validSubdivideFields = array();
                foreach ($templateFields as $templateFieldRow) {
                    $validSubdivideFields[] = (int)(isset($templateFieldRow['id_report_template_field']) ? $templateFieldRow['id_report_template_field'] : 0);
                }
                $fieldIds = array($reportSubdivideByFieldId);
                if ($reportSubdivideByFieldIdLevel2 > 0) {
                    $fieldIds[] = $reportSubdivideByFieldIdLevel2;
                }
                $allValid = true;
                foreach ($fieldIds as $candidateFieldId) {
                    if (!in_array((int)$candidateFieldId, $validSubdivideFields, true)) {
                        $allValid = false;
                        break;
                    }
                }
                if ($allValid) {
                    $reportSubdivisionSortDirections = array(
                        1 => $reportGridSubdivideSortLevel1,
                        2 => $reportGridSubdivideSortLevel2,
                    );
                    $reportSubdivisions = reports_v2_build_report_subdivision_tree($reportRows, $fieldIds, $reportSubdivisionShowTotals, $reportSubdivisionSortDirections);
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
  .report-v2-result-row--combined-reservation td { background: rgba(21, 45, 81, 0.42); border-top: 1px solid rgba(53, 199, 240, 0.24); }
  .report-v2-result-row--combined-reservation td:first-child { font-weight: 700; }
  .report-v2-result-row--combined-line-item td { background: rgba(7, 20, 42, 0.68); }
  .report-v2-result-row--combined-line-item td:first-child { padding-left: 28px; color: rgba(205, 226, 245, 0.92); }
  .report-v2-result-row--indent-2 td:first-child { padding-left: 26px; }
  .report-v2-result-row--indent-3 td:first-child { padding-left: 42px; }
  .report-v2-result-row--deep td { border-left: 3px solid rgba(53, 199, 240, 0.18); }
  .report-v2-inline-total-row td { font-weight: 700; background: rgba(14, 31, 58, 0.72); }
  .report-v2-inline-total-row--level-2 td { background: rgba(20, 45, 76, 0.42); }
  .report-v2-inline-total-row--level-3 td { background: rgba(16, 37, 63, 0.62); border-left: 3px solid rgba(53, 199, 240, 0.28); }
  .report-v2-folio-summary-row td { background: rgba(9, 24, 47, 0.9); border-top: 1px solid rgba(125, 211, 252, 0.2); font-weight: 600; }
  .report-v2-folio-summary-text { display:flex; flex-wrap:wrap; gap:14px; align-items:center; }
  .report-v2-folio-summary-text strong { color:#f8fbff; }
  .report-v2-empty-state-row td { padding: 18px 12px; color: rgba(188, 208, 230, 0.82); font-style: italic; }
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
  .report-v2-inline-cell-display { display: inline; }
  .report-v2-inline-cell-input { width: 100%; min-width: 120px; display: none; }
  .report-v2-entity-link { color: #8fd9ff; text-decoration: underline; text-underline-offset: 2px; }
  .report-v2-entity-link:hover { color: #c8ecff; }
  .report-v2-table td.report-v2-actions-cell { white-space: nowrap; width: 1%; min-width: 132px; }
  .report-v2-inline-form { margin: 0; }
  .report-v2-inline-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; min-height: 42px; }
  .report-v2-inline-edit-button,
  .report-v2-inline-save-form button,
  .report-v2-inline-cancel-button {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    height:38px;
    padding:0 14px;
    border-radius:12px;
    flex:0 0 auto;
  }
  .report-v2-inline-save-form { display:none; margin:0; }
  .report-v2-inline-cancel-button { min-width:38px; width:38px; padding:0; line-height:1; font-weight:800; }
  .report-v2-result-row.is-editing .report-v2-inline-cell-display { display: none; }
  .report-v2-result-row.is-editing .report-v2-inline-cell-input { display: block; }
  .report-v2-result-row.is-editing .report-v2-inline-edit-button { display:none; }
  .report-v2-result-row.is-editing .report-v2-inline-save-form { display:inline-flex; }
  .report-v2-result-row .report-v2-inline-cancel-button { display:none; }
  .report-v2-result-row.is-editing .report-v2-inline-cancel-button { display:inline-flex; }
  .report-v2-result-row.is-saving { opacity: 0.7; pointer-events: none; }
  .report-v2-grid-filter-bar { padding: 14px 16px; border: 1px solid rgba(120, 150, 190, 0.18); border-radius: 16px; background: rgba(11, 27, 56, 0.32); }
  .report-v2-grid-division-form { display:flex; gap:14px; align-items:end; flex-wrap:wrap; }
  .report-v2-grid-division-block { display:flex; gap:10px; align-items:end; flex-wrap:wrap; }
  .report-v2-grid-division-block label { min-width:220px; }
  .report-v2-grid-division-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .report-v2-division-sort-button { border:1px solid rgba(120, 150, 190, 0.22); background:rgba(11, 27, 56, 0.44); color:#dbeafe; border-radius:10px; min-width:38px; height:38px; padding:0 10px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; line-height:1; }
  .report-v2-division-sort-button:hover { background:rgba(17, 36, 72, 0.82); border-color:rgba(120, 150, 190, 0.34); }
  .report-v2-division-sort-button.is-desc { color:#7dd3fc; }
  .report-v2-division-sort-button:disabled { opacity:.45; cursor:not-allowed; background:rgba(11, 27, 56, 0.24); }
  .report-v2-grid-division-toggle { display:flex; align-items:center; gap:8px; min-height:44px; padding:10px 12px; border:1px solid rgba(120, 150, 190, 0.18); border-radius:12px; background:rgba(11, 27, 56, 0.44); }
  .report-v2-grid-division-toggle input[type="checkbox"] { width:16px; height:16px; accent-color:#35c7f0; }
  .report-v2-grid-division-toggle span { color:#cfe6ff; font-size:.88rem; white-space:nowrap; }
  .report-v2-grid-toolbar { display: flex; gap: 14px; align-items: end; flex-wrap: nowrap; }
  .report-v2-grid-toolbar-dates { display: flex; gap: 10px; align-items: end; flex: 1 1 auto; min-width: 0; flex-wrap: nowrap; }
  .report-v2-grid-toolbar-dates select { flex: 1 1 320px; min-width: 220px; }
  .report-v2-grid-toolbar-dates .report-v2-inline-range { flex: 0 0 auto; min-width: 280px; }
  .report-v2-grid-toolbar-search { display: flex; gap: 10px; align-items: end; min-width: 0; flex: 0 1 640px; }
  .report-v2-grid-toolbar-search input { min-width: 320px; width: 100%; }
  .report-v2-grid-toolbar-actions { display: flex; gap: 10px; align-items: center; justify-content: flex-end; }
  .report-v2-export-form { margin: 0; }
  .report-v2-export-button { white-space: nowrap; }
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
  .report-v2-filter-checklist-group { display: grid; gap: 8px; margin-bottom: 10px; }
  .report-v2-filter-checklist-group:last-child { margin-bottom: 0; }
  .report-v2-filter-checklist-group-title { padding: 0 4px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #8fc8ff; }
  .report-v2-filter-check { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid rgba(120, 150, 190, 0.16); border-radius: 12px; background: rgba(11, 27, 56, 0.32); }
  .report-v2-advanced-filter-form { display: grid; gap: 12px; }
  .report-v2-advanced-filter-form input,
  .report-v2-advanced-filter-form select { width: 100%; }
  .report-v2-advanced-filter-row { display: grid; gap: 10px; grid-template-columns: 160px minmax(0, 1fr); align-items: end; }
  .report-v2-subreport-title.is-empty { opacity: 0.55; }
  .report-v2-context-menu { position: fixed; z-index: 1010; min-width: 296px; padding: 8px; border-radius: 16px; border: 1px solid rgba(120, 150, 190, 0.26); background: linear-gradient(180deg, rgba(10, 26, 52, 0.985), rgba(6, 17, 37, 0.995)); box-shadow: 0 24px 64px rgba(0, 0, 0, 0.42), 0 0 0 1px rgba(255,255,255,0.02) inset; display: none; backdrop-filter: blur(8px); }
  .report-v2-context-menu.is-open { display: block; }
  .report-v2-context-menu-group { position: relative; }
  .report-v2-context-menu-button { width: 100%; border: 0; background: transparent; text-align: left; color: rgba(235, 244, 255, 0.96); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; cursor: pointer; font-size: 14px; line-height: 1.3; transition: background 0.16s ease, color 0.16s ease; }
  .report-v2-context-menu-button:hover,
  .report-v2-context-menu-group.is-open > .report-v2-context-menu-button { background: rgba(53, 199, 240, 0.16); color: #ffffff; }
  .report-v2-context-menu-button-label { min-width: 0; display: grid; gap: 2px; }
  .report-v2-context-menu-button-meta { color: rgba(168, 207, 236, 0.86); font-size: 12px; }
  .report-v2-context-menu-chevron { font-size: 18px; color: rgba(178, 216, 242, 0.88); flex-shrink: 0; line-height: 1; }
  .report-v2-context-submenu { position: absolute; top: -4px; left: calc(100% + 10px); min-width: 228px; padding: 8px; border-radius: 16px; border: 1px solid rgba(120, 150, 190, 0.26); background: linear-gradient(180deg, rgba(10, 26, 52, 0.99), rgba(6, 17, 37, 0.998)); box-shadow: 0 22px 56px rgba(0, 0, 0, 0.44); display: none; }
  .report-v2-context-menu-group.is-open .report-v2-context-submenu { display: block; }
  .report-v2-context-submenu .report-v2-context-menu-button { padding: 11px 13px; }
  .report-v2-context-submenu-kicker { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(159, 200, 232, 0.72); }
  @media (max-width: 1180px) {
    .report-v2-grid, .report-v2-grid-bottom { grid-template-columns: 1fr; }
    .report-v2-form-grid, .report-v2-form-grid--3, .report-v2-form-grid--4 { grid-template-columns: 1fr; }
    .report-v2-grid-toolbar { display: grid; grid-template-columns: 1fr; }
    .report-v2-grid-toolbar-dates { display: grid; grid-template-columns: 1fr; }
    .report-v2-grid-toolbar-search { display: grid; }
    .report-v2-grid-toolbar-search input { min-width: 0; width: 100%; }
    .report-v2-grid-toolbar-actions { justify-content: flex-start; }
    .report-v2-advanced-filter-row { grid-template-columns: 1fr; }
    .report-v2-context-submenu { left: 0; right: 0; top: calc(100% + 6px); min-width: 0; }
  }
</style>
<section class="report-v2-page">
  <?php if ($reportsManageMode): ?>
  <div class="report-v2-tabs">
    <a class="report-v2-tab <?php echo $activeTab === 'run' ? 'is-active' : ''; ?>" href="<?php echo reports_v2_h('?view=reports&reports_tab=run&reports_manage=1&selected_report_template_id=' . $selectedTemplateId); ?>">Ejecucion</a>
    <a class="report-v2-tab <?php echo $activeTab === 'templates' ? 'is-active' : ''; ?>" href="<?php echo reports_v2_h('?view=reports&reports_tab=templates&reports_manage=1&selected_report_template_id=' . $selectedTemplateId); ?>">Plantillas</a>
    <a class="report-v2-tab <?php echo $activeTab === 'calculations' ? 'is-active' : ''; ?>" href="<?php echo reports_v2_h('?view=reports&reports_tab=calculations&reports_manage=1&selected_report_template_id=' . $selectedTemplateId . '&selected_report_calculation_id=' . $selectedCalculationId); ?>">Calculos</a>
  </div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?><div class="report-v2-error-list"><?php foreach ($errors as $errorMessage): ?><div class="report-v2-error"><?php echo reports_v2_h($errorMessage); ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php if (!empty($messages)): ?><div class="report-v2-message-list"><?php foreach ($messages as $messageText): ?><div class="report-v2-message"><?php echo reports_v2_h($messageText); ?></div><?php endforeach; ?></div><?php endif; ?>
  <?php require __DIR__ . '/reports_v2_sections.php'; ?>
</section>
<script>
  (function () {
    var exportExcelButton = document.getElementById('report-v2-export-excel');
    var exportGoogleButton = document.getElementById('report-v2-export-google');
    var reportTitle = <?php echo json_encode($runTemplate && isset($runTemplate['report_name']) ? (string)$runTemplate['report_name'] : 'reporte'); ?>;
    var triggerExportFallback = function (button) {
      if (!button) {
        return;
      }
      var exportUrl = button.getAttribute('data-export-url') || '';
      if (exportUrl !== '') {
        window.location.href = exportUrl;
      }
    };
    var downloadTextFile = function (content, filename, mimeType) {
      var blob = new Blob([content], { type: mimeType });
      if (window.navigator && typeof window.navigator.msSaveOrOpenBlob === 'function') {
        window.navigator.msSaveOrOpenBlob(blob, filename);
        return;
      }
      var url = URL.createObjectURL(blob);
      var link = document.createElement('a');
      link.href = url;
      link.download = filename;
      link.style.display = 'none';
      link.rel = 'noopener';
      document.body.appendChild(link);
      if (typeof link.click === 'function') {
        link.click();
      } else {
        link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
      }
      window.setTimeout(function () {
        URL.revokeObjectURL(url);
        link.remove();
      }, 1500);
    };
    var buildExportBaseName = function () {
      var normalized = String(reportTitle || 'reporte')
        .replace(/[^a-z0-9_-]+/gi, '_')
        .replace(/^_+|_+$/g, '');
      if (!normalized) {
        normalized = 'reporte';
      }
      var now = new Date();
      var pad = function (value) { return String(value).padStart(2, '0'); };
      return normalized + '_'
        + now.getFullYear()
        + pad(now.getMonth() + 1)
        + pad(now.getDate()) + '_'
        + pad(now.getHours())
        + pad(now.getMinutes())
        + pad(now.getSeconds());
    };
    var getVisibleText = function (cell) {
      if (!cell) {
        return '';
      }
      return String(cell.innerText || cell.textContent || '').replace(/\s+\n/g, '\n').replace(/\n\s+/g, '\n').trim();
    };
    var rowIsVisible = function (row) {
      if (!row) {
        return false;
      }
      if (row.style && row.style.display === 'none') {
        return false;
      }
      if (row.hidden) {
        return false;
      }
      return true;
    };
    var collectVisibleReportRows = function () {
      var wrap = document.querySelector('.report-v2-table-wrap');
      if (!wrap) {
        return [];
      }
      var allRows = [];
      Array.prototype.slice.call(wrap.querySelectorAll('table[data-result-scope]')).forEach(function (table) {
        if (!rowIsVisible(table)) {
          return;
        }
        var section = table.closest('.report-v2-stack');
        var titleEl = section ? section.querySelector('.report-v2-subreport-title') : null;
        var headerCells = Array.prototype.slice.call(table.querySelectorAll('thead tr th'));
        var headers = headerCells.map(getVisibleText);
        if (titleEl && rowIsVisible(titleEl)) {
          var titleRow = new Array(headers.length).fill('');
          if (titleRow.length > 0) {
            titleRow[0] = getVisibleText(titleEl);
          }
          allRows.push(titleRow);
        }
        if (headers.length) {
          allRows.push(headers);
        }
        Array.prototype.slice.call(table.querySelectorAll('tbody tr, tfoot tr')).forEach(function (row) {
          if (!rowIsVisible(row)) {
            return;
          }
          var cells = Array.prototype.slice.call(row.children).map(getVisibleText);
          if (cells.some(function (value) { return value !== ''; })) {
            allRows.push(cells);
          }
        });
        allRows.push(new Array(headers.length).fill(''));
      });
      while (allRows.length && allRows[allRows.length - 1].every(function (value) { return value === ''; })) {
        allRows.pop();
      }
      return allRows;
    };
    var escapeCsvCell = function (value) {
      var text = String(value == null ? '' : value);
      if (/[",\n]/.test(text)) {
        return '"' + text.replace(/"/g, '""') + '"';
      }
      return text;
    };
    var exportVisibleReportToCsv = function () {
      var rows = collectVisibleReportRows();
      if (!rows.length) {
        triggerExportFallback(exportGoogleButton);
        return;
      }
      var csv = '\uFEFF' + rows.map(function (row) {
        return row.map(escapeCsvCell).join(',');
      }).join('\r\n');
      try {
        downloadTextFile(csv, buildExportBaseName() + '.csv', 'text/csv;charset=utf-8;');
      } catch (error) {
        triggerExportFallback(exportGoogleButton);
      }
    };
    var exportVisibleReportToExcel = function () {
      var rows = collectVisibleReportRows();
      if (!rows.length) {
        triggerExportFallback(exportExcelButton);
        return;
      }
      var html = '<html><head><meta charset="UTF-8"><title>' + String(reportTitle || 'Reporte') + '</title></head><body><table border="1">';
      rows.forEach(function (row, index) {
        html += '<tr>';
        row.forEach(function (value) {
          var tag = index === 0 ? 'th' : 'td';
          var escaped = String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
          html += '<' + tag + '>' + escaped.replace(/\n/g, '<br>') + '</' + tag + '>';
        });
        html += '</tr>';
      });
      html += '</table></body></html>';
      try {
        downloadTextFile('\uFEFF' + html, buildExportBaseName() + '.xls', 'application/vnd.ms-excel;charset=utf-8;');
      } catch (error) {
        triggerExportFallback(exportExcelButton);
      }
    };
    if (exportExcelButton) {
      exportExcelButton.addEventListener('click', function (event) {
        event.preventDefault();
        exportVisibleReportToExcel();
      });
    }
    if (exportGoogleButton) {
      exportGoogleButton.addEventListener('click', function (event) {
        event.preventDefault();
        exportVisibleReportToCsv();
      });
    }

    var editableReservationFieldCodes = <?php echo json_encode(array_values(array_keys(function_exists('pms_report_reservation_editable_field_catalog') ? pms_report_reservation_editable_field_catalog() : array()))); ?>;
    var templateRowSource = document.getElementById('reports-v2-template-row-source');
    if (templateRowSource) {
      var toggleTemplatePanels = function () {
        Array.prototype.slice.call(document.querySelectorAll('[data-template-row-source-panel]')).forEach(function (panel) {
          var supportedValues = (panel.getAttribute('data-template-row-source-panel') || '')
            .split(',')
            .map(function (value) { return value.trim(); })
            .filter(function (value) { return value !== ''; });
          panel.style.display = supportedValues.indexOf(templateRowSource.value) >= 0 ? '' : 'none';
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
        Array.prototype.slice.call(document.querySelectorAll('[data-field-panel]')).forEach(function (panel) {
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
      Array.prototype.slice.call(document.querySelectorAll('.report-v2-insert-variable')).forEach(function (button) {
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
    var templateRunFiltersForm = document.querySelector('[data-report-template-run-form="1"]');
    var templateGridStateInput = document.getElementById('report-v2-template-grid-state-json');
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
    var columnContextSubdivideGroup = document.getElementById('report-v2-column-context-subdivide-group');
    var columnContextSubdivideButton = document.getElementById('report-v2-column-context-subdivide');
    var columnContextSubdivideLevelButtons = Array.prototype.slice.call(document.querySelectorAll('[data-column-context-subdivide-level]'));
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
    var lineItemFilterCatalogByField = <?php echo $lineItemFilterCatalogByFieldJson; ?>;
    var templateDefaultGridState = <?php echo $templateDefaultGridStateJson; ?>;
    var gridRuntimeSubdivideFieldId = <?php echo (int)$reportGridSubdivideFieldId; ?>;
    var gridRuntimeSubdivideFieldIdLevel2 = <?php echo (int)$reportGridSubdivideFieldIdLevel2; ?>;
    var gridRuntimeSubdivideFieldIdLevel3 = <?php echo (int)$reportGridSubdivideFieldIdLevel3; ?>;
    var gridRuntimeSubdivideSortLevel1 = <?php echo json_encode($reportGridSubdivideSortLevel1); ?>;
    var gridRuntimeSubdivideSortLevel2 = <?php echo json_encode($reportGridSubdivideSortLevel2); ?>;
    var gridRuntimeSubdivideShowTotalsLevel1 = <?php echo $reportGridSubdivideShowTotalsLevel1 === null ? 'null' : ((int)$reportGridSubdivideShowTotalsLevel1); ?>;
    var gridRuntimeSubdivideShowTotalsLevel2 = <?php echo $reportGridSubdivideShowTotalsLevel2 === null ? 'null' : ((int)$reportGridSubdivideShowTotalsLevel2); ?>;

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

    var ensureFlashList = function (type) {
      var page = document.querySelector('.report-v2-page');
      if (!page) {
        return null;
      }
      var selector = type === 'error' ? '.report-v2-error-list' : '.report-v2-message-list';
      var className = type === 'error' ? 'report-v2-error-list' : 'report-v2-message-list';
      var itemClassName = type === 'error' ? 'report-v2-error' : 'report-v2-message';
      var list = page.querySelector(selector);
      if (!list) {
        list = document.createElement('div');
        list.className = className;
        var tabs = page.querySelector('.report-v2-tabs');
        if (tabs && tabs.nextSibling) {
          page.insertBefore(list, tabs.nextSibling);
        } else {
          page.appendChild(list);
        }
      }
      return { list: list, itemClassName: itemClassName };
    };

    var showFlashMessage = function (type, text) {
      var flash = ensureFlashList(type);
      if (!flash || !text) {
        return;
      }
      flash.list.innerHTML = '';
      var item = document.createElement('div');
      item.className = flash.itemClassName;
      item.textContent = text;
      flash.list.appendChild(item);
    };

    var refreshRowTextAndCatalog = function (row) {
      if (!row) {
        return;
      }
      var rowText = Array.prototype.slice.call(row.querySelectorAll('td[data-field-id]')).map(function (cell) {
        var displayValue = cell.getAttribute('data-cell-display') || '';
        var fieldId = cell.getAttribute('data-field-id') || '';
        var filterValue = cell.getAttribute('data-cell-filter-value') || '__EMPTY__';
        if (fieldId) {
          if (!columnValueCatalog[fieldId]) {
            columnValueCatalog[fieldId] = {};
          }
          if (!columnValueCatalog[fieldId][filterValue]) {
            columnValueCatalog[fieldId][filterValue] = {
              value: filterValue,
              label: filterValue === '__EMPTY__' ? 'Sin valor' : displayValue
            };
          } else if (displayValue && filterValue !== '__EMPTY__') {
            columnValueCatalog[fieldId][filterValue].label = displayValue;
          }
        }
        return displayValue.trim();
      }).join(' ').toLowerCase();
      row.setAttribute('data-row-text', rowText);
    };

    var toggleInlineEditRow = function (row, shouldEdit) {
      if (!row || row.getAttribute('data-inline-edit-row') !== '1') {
        return;
      }
      var inputNodes = Array.prototype.slice.call(row.querySelectorAll('.report-v2-inline-cell-input'));
      if (!shouldEdit) {
        inputNodes.forEach(function (input) {
          input.value = input.getAttribute('data-original-value') || '';
        });
      }
      row.classList.toggle('is-editing', !!shouldEdit);
    };

    var renderCellDisplayNode = function (targetNode, displayValue, href) {
      if (!targetNode) {
        return;
      }
      if (href && String(displayValue || '').trim() !== '') {
        targetNode.innerHTML = '';
        var anchor = document.createElement('a');
        anchor.className = 'report-v2-entity-link';
        anchor.href = href;
        anchor.textContent = String(displayValue || '');
        targetNode.appendChild(anchor);
        return;
      }
      targetNode.textContent = String(displayValue || '');
    };

    var updateReservationRowsFromPayload = function (reservationId, payloadCells) {
      var rows = Array.prototype.slice.call(document.querySelectorAll('.report-v2-result-row[data-reservation-id="' + reservationId + '"]'));
      rows.forEach(function (row) {
        Object.keys(payloadCells || {}).forEach(function (fieldId) {
          var payload = payloadCells[fieldId];
          var cell = row.querySelector('td[data-field-id="' + fieldId + '"]');
          if (!cell || !payload) {
            return;
          }
          cell.setAttribute('data-cell-display', String(payload.display || ''));
          cell.setAttribute('data-cell-raw', String(payload.raw || ''));
          cell.setAttribute('data-cell-filter-value', String(payload.filter_value || '__EMPTY__'));
          cell.setAttribute('data-cell-type', String(payload.data_type || 'text'));
          cell.setAttribute('data-cell-href', String(payload.href || ''));
          var displayNode = cell.querySelector('.report-v2-inline-cell-display');
          if (displayNode) {
            renderCellDisplayNode(displayNode, payload.display || '', payload.href || '');
          } else {
            renderCellDisplayNode(cell, payload.display || '', payload.href || '');
          }
          var inputNode = cell.querySelector('.report-v2-inline-cell-input');
          if (inputNode) {
            inputNode.value = String(payload.raw || '');
            inputNode.setAttribute('data-original-value', String(payload.raw || ''));
          }
        });
        refreshRowTextAndCatalog(row);
      });
    };

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
      if (lineItemFilterCatalogByField[fieldId]) {
        var groupedValues = [];
        if (!columnValueCatalog[fieldId]) {
          columnValueCatalog[fieldId] = {};
        }
        lineItemFilterCatalogByField[fieldId].forEach(function (group) {
          (group.options || []).forEach(function (option) {
            if (groupedValues.indexOf(option.value) === -1) {
              groupedValues.push(option.value);
            }
            if (!columnValueCatalog[fieldId][option.value]) {
              columnValueCatalog[fieldId][option.value] = {
                value: option.value,
                label: option.label
              };
            }
          });
        });
        Object.keys(columnValueCatalog[fieldId]).forEach(function (valueKey) {
          if (groupedValues.indexOf(valueKey) === -1) {
            groupedValues.push(valueKey);
          }
        });
        return groupedValues;
      }
      var catalog = columnValueCatalog[fieldId] || {};
      return Object.keys(catalog);
    };

    var buildFilterOptionNode = function (fieldId, valueKey, currentSet) {
      var fieldCatalog = columnValueCatalog[fieldId] || {};
      var meta = fieldCatalog[valueKey] || { value: valueKey, label: valueKey };
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
      return wrapper;
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

    var buildTemplateDefaultGridState = function () {
      var payload = {
        column_filters: {},
        advanced_filters: {}
      };

      Object.keys(columnFilterState).forEach(function (fieldId) {
        if (!fieldHasActiveColumnFilter(fieldId)) {
          return;
        }
        var allowedSet = columnFilterState[fieldId];
        if (!(allowedSet instanceof Set)) {
          return;
        }
        payload.column_filters[fieldId] = Array.from(allowedSet);
      });

      Object.keys(columnAdvancedFilterState).forEach(function (fieldId) {
        if (!fieldHasActiveAdvancedFilter(fieldId)) {
          return;
        }
        var state = columnAdvancedFilterState[fieldId];
        payload.advanced_filters[fieldId] = {
          operator: String(state.operator || '='),
          value: String(state.value || ''),
          valueTo: String(state.valueTo || '')
        };
      });

      return payload;
    };

    var syncTemplateDefaultGridStateInput = function () {
      if (!templateGridStateInput) {
        return;
      }
      templateGridStateInput.value = JSON.stringify(buildTemplateDefaultGridState());
    };

    var updateFilterButtonStates = function () {
      Array.prototype.slice.call(document.querySelectorAll('[data-filter-field-id]')).forEach(function (button) {
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
      url.searchParams.delete('report_search');
      if (gridRuntimeSubdivideFieldId > 0) {
        url.searchParams.set('report_grid_subdivide_field_id', String(gridRuntimeSubdivideFieldId));
      } else {
        url.searchParams.delete('report_grid_subdivide_field_id');
      }
      if (gridRuntimeSubdivideFieldIdLevel2 > 0) {
        url.searchParams.set('report_grid_subdivide_field_id_level_2', String(gridRuntimeSubdivideFieldIdLevel2));
      } else {
        url.searchParams.delete('report_grid_subdivide_field_id_level_2');
      }
      if (gridRuntimeSubdivideFieldIdLevel3 > 0) {
        url.searchParams.set('report_grid_subdivide_field_id_level_3', String(gridRuntimeSubdivideFieldIdLevel3));
      } else {
        url.searchParams.delete('report_grid_subdivide_field_id_level_3');
      }
      url.searchParams.set('report_grid_subdivide_sort_level_1', String(gridRuntimeSubdivideSortLevel1 === 'desc' ? 'desc' : 'asc'));
      url.searchParams.set('report_grid_subdivide_sort_level_2', String(gridRuntimeSubdivideSortLevel2 === 'desc' ? 'desc' : 'asc'));
      if (gridRuntimeSubdivideShowTotalsLevel1 !== null) {
        url.searchParams.set('report_grid_subdivide_show_totals_level_1', String(gridRuntimeSubdivideShowTotalsLevel1 ? 1 : 0));
      } else {
        url.searchParams.delete('report_grid_subdivide_show_totals_level_1');
      }
      if (gridRuntimeSubdivideShowTotalsLevel2 !== null) {
        url.searchParams.set('report_grid_subdivide_show_totals_level_2', String(gridRuntimeSubdivideShowTotalsLevel2 ? 1 : 0));
      } else {
        url.searchParams.delete('report_grid_subdivide_show_totals_level_2');
      }
      return url;
    };

    var closeColumnContextMenu = function () {
      if (!columnContextMenu) {
        return;
      }
      columnContextMenu.classList.remove('is-open');
      columnContextMenu.setAttribute('aria-hidden', 'true');
      if (columnContextSubdivideGroup) {
        columnContextSubdivideGroup.classList.remove('is-open');
      }
      activeContextFieldId = null;
    };

    var normalizeRuntimeSubdivideLevels = function (levels) {
      var normalized = [
        Number(levels[0] || 0),
        Number(levels[1] || 0),
        Number(levels[2] || 0)
      ];
      normalized = normalized.map(function (value, index) {
        return value > 0 && normalized.indexOf(value) === index ? value : 0;
      });
      if (!normalized[0] && normalized[1] > 0) {
        normalized[0] = normalized[1];
        normalized[1] = 0;
      }
      if (!normalized[0] && normalized[2] > 0) {
        normalized[0] = normalized[2];
        normalized[2] = 0;
      }
      if (normalized[0] > 0 && !normalized[1] && normalized[2] > 0) {
        normalized[1] = normalized[2];
        normalized[2] = 0;
      }
      return normalized;
    };

    var navigateWithRuntimeSubdivision = function (level, fieldId) {
      var numericFieldId = Number(fieldId || 0);
      if (!numericFieldId) {
        return;
      }
      var levelIndex = Math.max(0, Math.min(1, Number(level || 1) - 1));
      var levels = [gridRuntimeSubdivideFieldId, gridRuntimeSubdivideFieldIdLevel2, 0];
      levels[levelIndex] = numericFieldId;
      levels = levels.map(function (value, index) {
        return value === numericFieldId && index !== levelIndex ? 0 : value;
      });
      levels = normalizeRuntimeSubdivideLevels(levels);
      gridRuntimeSubdivideFieldId = levels[0];
      gridRuntimeSubdivideFieldIdLevel2 = levels[1];
      gridRuntimeSubdivideFieldIdLevel3 = 0;
      var baseUrl = buildBaseReportUrl();
      window.location.assign(baseUrl.toString());
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

    var isResultRowActuallyVisible = function (row) {
      if (!row || row.style.display === 'none') {
        return false;
      }
      var current = row;
      while (current && current !== document.body) {
        if (current.style && current.style.display === 'none') {
          return false;
        }
        current = current.parentElement;
      }
      return true;
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
          return isResultRowActuallyVisible(row);
        });
        body.style.display = visibleRows.length ? '' : 'none';
      });
      [3, 2].forEach(function (level) {
        Array.prototype.slice.call(section.querySelectorAll('tbody[data-group-role="marker"][data-group-level="' + level + '"]')).forEach(function (body) {
          var groupId = body.getAttribute('data-group-node-id') || '';
          var visibleRows = Array.prototype.slice.call(section.querySelectorAll('tr.report-v2-result-row[data-group-level-' + level + '="' + groupId + '"]')).filter(function (row) {
            return isResultRowActuallyVisible(row);
          });
          body.style.display = visibleRows.length ? '' : 'none';
        });
        Array.prototype.slice.call(section.querySelectorAll('tbody[data-group-role="total"][data-group-level="' + level + '"]')).forEach(function (body) {
          var groupId = body.getAttribute('data-group-node-id') || '';
          var visibleRows = Array.prototype.slice.call(section.querySelectorAll('tr.report-v2-result-row[data-group-level-' + level + '="' + groupId + '"]')).filter(function (row) {
            return isResultRowActuallyVisible(row);
          });
          body.style.display = visibleRows.length ? '' : 'none';
          var totalRow = body.querySelector('tr.report-v2-inline-total-row');
          if (totalRow) {
            updateInlineTotalRow(table, totalRow, visibleRows, body.getAttribute('data-inline-total-label') || 'Subtotal');
          }
        });
      });
    };

    var ensureEmptyStateForTable = function (table, visibleRows, message) {
      if (!table) {
        return;
      }
      var columnCount = table.querySelectorAll('thead th').length || 1;
      var emptyBody = table.querySelector('tbody[data-group-role="empty"]');
      if (!emptyBody) {
        emptyBody = document.createElement('tbody');
        emptyBody.setAttribute('data-group-role', 'empty');
        var emptyRow = document.createElement('tr');
        emptyRow.className = 'report-v2-empty-state-row';
        var emptyCell = document.createElement('td');
        emptyCell.colSpan = columnCount;
        emptyCell.textContent = message || 'Sin resultados visibles para los filtros actuales.';
        emptyRow.appendChild(emptyCell);
        emptyBody.appendChild(emptyRow);
        table.appendChild(emptyBody);
      } else {
        var existingCell = emptyBody.querySelector('td');
        if (existingCell) {
          existingCell.colSpan = columnCount;
          existingCell.textContent = message || 'Sin resultados visibles para los filtros actuales.';
        }
      }
      emptyBody.style.display = visibleRows.length ? 'none' : '';
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

      Array.prototype.slice.call(document.querySelectorAll('.report-v2-result-subdivision')).forEach(function (section) {
        var sectionVisibleRows = Array.prototype.slice.call(section.querySelectorAll('tbody tr.report-v2-result-row')).filter(function (row) {
          return isResultRowActuallyVisible(row);
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
          ensureEmptyStateForTable(table, sectionVisibleRows, 'Sin resultados visibles para esta subdivision.');
        }
      });

      resultTables.forEach(function (table) {
        if (table.getAttribute('data-result-scope') === 'subdivision') {
          return;
        }
        var tableVisibleRows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.report-v2-result-row')).filter(function (row) {
          return isResultRowActuallyVisible(row);
        });
        updateTotalsForTable(table, tableVisibleRows, 'Totales generales');
        ensureEmptyStateForTable(table, tableVisibleRows, 'Sin resultados visibles para los filtros actuales.');
      });

      updateTotalsForTable(globalTotalsTable, visibleRows.filter(function (row) {
        return isResultRowActuallyVisible(row);
      }), 'Totales generales');

      if (visibleCountChip) {
        var actualVisibleRows = visibleRows.filter(function (row) {
          return isResultRowActuallyVisible(row);
        });
        visibleCountChip.textContent = actualVisibleRows.length + ' visibles';
      }

      updateFilterButtonStates();
      syncTemplateDefaultGridStateInput();
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
      if (lineItemFilterCatalogByField[fieldId]) {
        var renderedValues = {};
        lineItemFilterCatalogByField[fieldId].forEach(function (group) {
          var groupWrap = document.createElement('div');
          groupWrap.className = 'report-v2-filter-checklist-group';
          var groupTitle = document.createElement('div');
          groupTitle.className = 'report-v2-filter-checklist-group-title';
          groupTitle.textContent = group.label || 'Otros';
          groupWrap.appendChild(groupTitle);

          (group.options || []).slice().sort(function (left, right) {
            return String(left.label || '').localeCompare(String(right.label || ''), undefined, { sensitivity: 'base', numeric: true });
          }).forEach(function (option) {
            renderedValues[option.value] = true;
            groupWrap.appendChild(buildFilterOptionNode(fieldId, option.value, currentSet));
          });
          filterModalOptions.appendChild(groupWrap);
        });

        values.filter(function (valueKey) {
          return !renderedValues[valueKey];
        }).sort(function (left, right) {
          var leftLabel = columnValueCatalog[fieldId][left].label;
          var rightLabel = columnValueCatalog[fieldId][right].label;
          return leftLabel.localeCompare(rightLabel);
        }).forEach(function (valueKey) {
          var orphanGroup = filterModalOptions.querySelector('[data-filter-group-orphans="1"]');
          if (!orphanGroup) {
            orphanGroup = document.createElement('div');
            orphanGroup.className = 'report-v2-filter-checklist-group';
            orphanGroup.setAttribute('data-filter-group-orphans', '1');
            var orphanTitle = document.createElement('div');
            orphanTitle.className = 'report-v2-filter-checklist-group-title';
            orphanTitle.textContent = 'Otros visibles';
            orphanGroup.appendChild(orphanTitle);
            filterModalOptions.appendChild(orphanGroup);
          }
          orphanGroup.appendChild(buildFilterOptionNode(fieldId, valueKey, currentSet));
        });
      } else {
        values.sort(function (left, right) {
          var leftLabel = columnValueCatalog[fieldId][left].label;
          var rightLabel = columnValueCatalog[fieldId][right].label;
          return leftLabel.localeCompare(rightLabel);
        }).forEach(function (valueKey) {
          filterModalOptions.appendChild(buildFilterOptionNode(fieldId, valueKey, currentSet));
        });
      }
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

    Array.prototype.slice.call(document.querySelectorAll('[data-filter-field-id]')).forEach(function (button) {
      button.addEventListener('click', function () {
        renderColumnFilterModal(button.getAttribute('data-filter-field-id') || '');
      });
    });

    document.addEventListener('contextmenu', function (event) {
      var headerCell = event.target && typeof event.target.closest === 'function'
        ? event.target.closest('.report-v2-result-table thead th[data-field-id]')
        : null;
      if (!headerCell || !columnContextMenu || !columnContextSubdivideButton) {
        return;
      }
      event.preventDefault();
      var fieldId = headerCell.getAttribute('data-field-id') || '';
      var fieldLabel = getFieldLabel(fieldId);
      var subdivideLabelWrap = columnContextSubdivideButton.querySelector('.report-v2-context-menu-button-label');
      var advancedLabelWrap = columnContextAdvancedFilterButton
        ? columnContextAdvancedFilterButton.querySelector('.report-v2-context-menu-button-label')
        : null;
      activeContextFieldId = fieldId;
      if (subdivideLabelWrap) {
        subdivideLabelWrap.innerHTML =
          '<strong>Dividir reporte por ' + fieldLabel + '</strong><span class="report-v2-context-menu-button-meta">Elige el nivel de division</span>';
      } else {
        columnContextSubdivideButton.textContent = 'Dividir reporte por ' + fieldLabel;
      }
      if (advancedLabelWrap) {
        advancedLabelWrap.innerHTML =
          '<strong>Filtro especializado para ' + fieldLabel + '</strong><span class="report-v2-context-menu-button-meta">Aplica una condicion puntual a esta columna</span>';
      } else if (columnContextAdvancedFilterButton) {
        columnContextAdvancedFilterButton.textContent = 'Filtro especializado para ' + fieldLabel;
      }
        columnContextSubdivideLevelButtons.forEach(function (button) {
          var level = Number(button.getAttribute('data-column-context-subdivide-level') || 1);
          var levelLabel = level === 1 ? 'Primera division' : 'Segunda division';
          button.innerHTML = '<span class="report-v2-context-menu-button-label"><strong>' + levelLabel + '</strong></span>';
          button.setAttribute('data-target-field-id', fieldId);
        });
      var menuWidth = 296;
      var menuHeight = 132;
      var left = Math.min(event.clientX, Math.max(12, window.innerWidth - menuWidth - 16));
      var top = Math.min(event.clientY, Math.max(12, window.innerHeight - menuHeight - 16));
      columnContextMenu.style.left = left + 'px';
      columnContextMenu.style.top = top + 'px';
      columnContextMenu.classList.add('is-open');
      columnContextMenu.setAttribute('aria-hidden', 'false');
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

    Array.prototype.slice.call(document.querySelectorAll('.report-v2-auto-submit-division-filter')).forEach(function (control) {
      control.addEventListener('change', function () {
        if (control.form) {
          control.form.submit();
        }
      });
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-division-sort-level]')).forEach(function (button) {
      button.addEventListener('click', function () {
        var level = Number(button.getAttribute('data-division-sort-level') || 0);
        if (level === 1) {
          gridRuntimeSubdivideSortLevel1 = gridRuntimeSubdivideSortLevel1 === 'desc' ? 'asc' : 'desc';
        } else if (level === 2) {
          gridRuntimeSubdivideSortLevel2 = gridRuntimeSubdivideSortLevel2 === 'desc' ? 'asc' : 'desc';
        } else {
          return;
        }
        var baseUrl = buildBaseReportUrl();
        window.location.assign(baseUrl.toString());
      });
    });

    var syncDivisionToggleState = function () {
      [1, 2].forEach(function (level) {
        var select = document.querySelector('[data-subdivide-field-select="' + level + '"]');
        var toggle = document.querySelector('[data-subdivide-totals-toggle="' + level + '"]');
        var sortButton = document.querySelector('[data-division-sort-level="' + level + '"]');
        if (!select || !toggle) {
          return;
        }
        var enabled = Number(select.value || 0) > 0;
        toggle.disabled = !enabled;
        if (sortButton) {
          sortButton.disabled = !enabled;
        }
      });
    };
    syncDivisionToggleState();
    Array.prototype.slice.call(document.querySelectorAll('[data-subdivide-field-select]')).forEach(function (select) {
      select.addEventListener('change', syncDivisionToggleState);
    });

    if (clearGridFiltersButton) {
      clearGridFiltersButton.addEventListener('click', function () {
        if (gridRuntimeSubdivideFieldId > 0 || gridRuntimeSubdivideFieldIdLevel2 > 0 || gridRuntimeSubdivideFieldIdLevel3 > 0 || gridRuntimeSubdivideShowTotalsLevel1 !== null || gridRuntimeSubdivideShowTotalsLevel2 !== null || gridRuntimeSubdivideSortLevel1 !== 'asc' || gridRuntimeSubdivideSortLevel2 !== 'asc') {
          var baseUrl = buildBaseReportUrl();
          baseUrl.searchParams.delete('report_grid_subdivide_field_id');
          baseUrl.searchParams.delete('report_grid_subdivide_field_id_level_2');
          baseUrl.searchParams.delete('report_grid_subdivide_field_id_level_3');
          baseUrl.searchParams.delete('report_grid_subdivide_sort_level_1');
          baseUrl.searchParams.delete('report_grid_subdivide_sort_level_2');
          baseUrl.searchParams.delete('report_grid_subdivide_show_totals_level_1');
          baseUrl.searchParams.delete('report_grid_subdivide_show_totals_level_2');
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
    if (columnContextSubdivideButton && columnContextSubdivideGroup) {
      columnContextSubdivideButton.addEventListener('click', function (event) {
        event.stopPropagation();
        columnContextSubdivideGroup.classList.toggle('is-open');
      });
      columnContextSubdivideButton.addEventListener('mouseenter', function () {
        columnContextSubdivideGroup.classList.add('is-open');
      });
      columnContextSubdivideGroup.addEventListener('mouseleave', function () {
        columnContextSubdivideGroup.classList.remove('is-open');
      });
      columnContextSubdivideLevelButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          var level = Number(button.getAttribute('data-column-context-subdivide-level') || 1);
          var fieldId = button.getAttribute('data-target-field-id') || activeContextFieldId;
          closeColumnContextMenu();
          navigateWithRuntimeSubdivision(level, fieldId);
        });
      });
    }
    if (columnContextAdvancedFilterButton) {
      columnContextAdvancedFilterButton.addEventListener('click', function () {
        var fieldId = activeContextFieldId;
        if (!fieldId) {
          return;
        }
        closeColumnContextMenu();
        renderAdvancedFilterModal(fieldId);
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

    document.addEventListener('click', function (event) {
      var editButton = event.target && typeof event.target.closest === 'function'
        ? event.target.closest('.report-v2-inline-edit-button')
        : null;
      if (editButton) {
        var editRow = editButton.closest('.report-v2-result-row');
        if (editRow) {
          toggleInlineEditRow(editRow, true);
          var firstInput = editRow.querySelector('.report-v2-inline-cell-input');
          if (firstInput) {
            firstInput.focus();
            if (typeof firstInput.select === 'function' && firstInput.type !== 'date') {
              firstInput.select();
            }
          }
        }
        return;
      }

      var cancelButton = event.target && typeof event.target.closest === 'function'
        ? event.target.closest('.report-v2-inline-cancel-button')
        : null;
      if (cancelButton) {
        var cancelRow = cancelButton.closest('.report-v2-result-row');
        if (cancelRow) {
          toggleInlineEditRow(cancelRow, false);
        }
      }
    });

    document.addEventListener('submit', function (event) {
      var saveForm = event.target && event.target.classList && event.target.classList.contains('report-v2-inline-save-form')
        ? event.target
        : null;
      if (!saveForm) {
        return;
      }
      event.preventDefault();
      var row = saveForm.closest('.report-v2-result-row');
      if (!row) {
        return;
      }
      row.classList.add('is-saving');
      var formData = new FormData(saveForm);
      fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().catch(function () {
            throw new Error('La respuesta del guardado no fue valida.');
          }).then(function (payload) {
            if (!response.ok || !payload || payload.ok !== true) {
              throw new Error(payload && payload.message ? payload.message : 'No fue posible guardar la fila.');
            }
            return payload;
          });
        })
        .then(function (payload) {
          updateReservationRowsFromPayload(String(payload.reservation_id || ''), payload.cells || {});
          toggleInlineEditRow(row, false);
          applyGridFilters();
          showFlashMessage('message', payload.message || 'Fila actualizada.');
        })
        .catch(function (error) {
          showFlashMessage('error', error && error.message ? error.message : 'No fue posible guardar la fila.');
        })
        .finally(function () {
          row.classList.remove('is-saving');
        });
    });

    if (templateRunFiltersForm) {
      templateRunFiltersForm.addEventListener('submit', syncTemplateDefaultGridStateInput);
    }

    Object.keys(columnValueCatalog).forEach(function (fieldId) {
      var allowedValues = getAllowedValuesForField(fieldId);
      var persistedAllowedValues = (
        templateDefaultGridState
        && templateDefaultGridState.column_filters
        && Array.isArray(templateDefaultGridState.column_filters[fieldId])
      ) ? templateDefaultGridState.column_filters[fieldId] : null;
      if (persistedAllowedValues) {
        var persistedSet = new Set();
        persistedAllowedValues.forEach(function (value) {
          var normalizedValue = String(value);
          if (allowedValues.indexOf(normalizedValue) !== -1) {
            persistedSet.add(normalizedValue);
          }
        });
        columnFilterState[fieldId] = persistedSet;
      } else {
        columnFilterState[fieldId] = new Set(allowedValues);
      }
    });

    if (templateDefaultGridState && templateDefaultGridState.advanced_filters) {
      Object.keys(templateDefaultGridState.advanced_filters).forEach(function (fieldId) {
        var state = templateDefaultGridState.advanced_filters[fieldId];
        if (!state || typeof state !== 'object') {
          return;
        }
        columnAdvancedFilterState[fieldId] = {
          operator: String(state.operator || '='),
          value: String(state.value || ''),
          valueTo: String(state.valueTo || '')
        };
      });
    }

    Object.keys(columnValueCatalog).forEach(function (fieldId) {
      if (!(columnFilterState[fieldId] instanceof Set)) {
        columnFilterState[fieldId] = new Set(getAllowedValuesForField(fieldId));
      }
    });
    applyGridFilters();
  }());
</script>
