<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/report_v2_lib.php';

$user = pms_current_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sesion invalida';
    exit;
}
if (!pms_user_can('reports.view') || !pms_user_can('reports.run')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No tienes permiso para exportar reportes';
    exit;
}

try {
    $pdo = pms_get_connection();
    $companyId = isset($user['company_id']) ? (int)$user['company_id'] : 0;
    if ($companyId <= 0) {
        throw new RuntimeException('Empresa invalida.');
    }

    $templateId = isset($_GET['selected_report_template_id']) ? (int)$_GET['selected_report_template_id'] : 0;
    if ($templateId <= 0) {
        throw new RuntimeException('Selecciona una plantilla valida.');
    }

    $format = isset($_GET['format']) ? strtolower(trim((string)$_GET['format'])) : 'excel';
    if ($format !== 'excel' && $format !== 'csv') {
        $format = 'excel';
    }

    $selectedTemplate = reports_v2_fetch_template($pdo, $companyId, $templateId);
    if (!$selectedTemplate) {
        throw new RuntimeException('La plantilla seleccionada no existe.');
    }

    $templateFields = reports_v2_fetch_template_fields($pdo, $templateId);
    if (empty($templateFields)) {
        throw new RuntimeException('La plantilla seleccionada no tiene campos.');
    }

    $dateTypeOptions = reports_v2_date_type_options();
    $hasTemplateDefaultPropertyCodeColumn = reports_v2_report_template_has_column($pdo, 'default_property_code');
    $hasTemplateDefaultStatusColumn = reports_v2_report_template_has_column($pdo, 'default_status');
    $hasTemplateDefaultDateTypeColumn = reports_v2_report_template_has_column($pdo, 'default_date_type');
    $hasTemplateDefaultDateFromColumn = reports_v2_report_template_has_column($pdo, 'default_date_from');
    $hasTemplateDefaultDateToColumn = reports_v2_report_template_has_column($pdo, 'default_date_to');

    $templateDefaultPropertyCode = (
        $hasTemplateDefaultPropertyCodeColumn && isset($selectedTemplate['default_property_code'])
    ) ? trim((string)$selectedTemplate['default_property_code']) : '';
    $templateDefaultStatus = (
        $hasTemplateDefaultStatusColumn && isset($selectedTemplate['default_status'])
    ) ? trim((string)$selectedTemplate['default_status']) : '';
    $templateDefaultDateType = (
        $hasTemplateDefaultDateTypeColumn && isset($selectedTemplate['default_date_type'])
    ) ? trim((string)$selectedTemplate['default_date_type']) : '';
    $templateDefaultDateFrom = (
        $hasTemplateDefaultDateFromColumn && isset($selectedTemplate['default_date_from'])
    ) ? trim((string)$selectedTemplate['default_date_from']) : '';
    $templateDefaultDateTo = (
        $hasTemplateDefaultDateToColumn && isset($selectedTemplate['default_date_to'])
    ) ? trim((string)$selectedTemplate['default_date_to']) : '';

    $runFilters = array(
        'property_code' => isset($_GET['report_property_code'])
            ? trim((string)$_GET['report_property_code'])
            : $templateDefaultPropertyCode,
        'status' => isset($_GET['report_status'])
            ? trim((string)$_GET['report_status'])
            : ($templateDefaultStatus !== '' ? $templateDefaultStatus : 'activas'),
        'date_type' => isset($_GET['report_date_type'])
            ? trim((string)$_GET['report_date_type'])
            : ($templateDefaultDateType !== '' ? $templateDefaultDateType : 'check_in_date'),
        'date_from' => isset($_GET['report_date_from'])
            ? trim((string)$_GET['report_date_from'])
            : ($templateDefaultDateFrom !== '' ? $templateDefaultDateFrom : date('Y-m-01')),
        'date_to' => isset($_GET['report_date_to'])
            ? trim((string)$_GET['report_date_to'])
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
        $swap = $runFilters['date_from'];
        $runFilters['date_from'] = $runFilters['date_to'];
        $runFilters['date_to'] = $swap;
    }

    $selectedRowSource = isset($selectedTemplate['row_source']) ? (string)$selectedTemplate['row_source'] : 'reservation';
    $selectedLineItemTypeScope = isset($selectedTemplate['line_item_type_scope']) ? (string)$selectedTemplate['line_item_type_scope'] : '';
    $lineItemCatalogs = reports_v2_fetch_line_item_catalogs($pdo, $companyId);
    $variableCatalog = reports_v2_build_variable_catalog($lineItemCatalogs);
    $calculationsById = reports_v2_fetch_calculations_indexed($pdo, $companyId);

    $baseRows = reports_v2_fetch_report_base_rows(
        $pdo,
        $companyId,
        $runFilters,
        500,
        $selectedRowSource === 'combined' ? 'reservation' : $selectedRowSource,
        $selectedLineItemTypeScope
    );

    $reservationIds = array();
    $baseLineItemIds = array();
    foreach ($baseRows as $baseRow) {
        $reservationIds[] = isset($baseRow['id_reservation']) ? (int)$baseRow['id_reservation'] : 0;
        $baseLineItemIds[] = isset($baseRow['base_line_item_id']) ? (int)$baseRow['base_line_item_id'] : 0;
    }

    $lineItemMetrics = reports_v2_fetch_line_item_metrics($pdo, $reservationIds);
    $lineItemTreeMetrics = reports_v2_fetch_line_item_tree_metrics($pdo, $baseLineItemIds);
    $reportRows = array();
    if ($selectedRowSource === 'combined') {
        $lineItemBaseRows = reports_v2_fetch_line_item_base_rows_for_reservations($pdo, $reservationIds, $selectedLineItemTypeScope);
        $lineItemBaseIds = array();
        foreach ($lineItemBaseRows as $lineItemBaseRow) {
            $lineItemBaseIds[] = isset($lineItemBaseRow['base_line_item_id']) ? (int)$lineItemBaseRow['base_line_item_id'] : 0;
        }
        $combinedTreeMetrics = reports_v2_fetch_line_item_tree_metrics($pdo, $lineItemBaseIds);
        list($reportRows) = reports_v2_build_combined_report_rows(
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
        list($reportRows) = reports_v2_build_report_rows(
            $baseRows,
            $templateFields,
            $calculationsById,
            $lineItemMetrics,
            $variableCatalog,
            $selectedTemplate,
            $lineItemTreeMetrics
        );
    }

    $reportDisplayTotals = array();
    $reportHasCalculatedTotals = false;
    foreach ($templateFields as $templateFieldRow) {
        if (reports_v2_field_calculates_total($templateFieldRow)) {
            $reportHasCalculatedTotals = true;
            break;
        }
    }
    if ($reportHasCalculatedTotals && !empty($reportRows)) {
        $reportDisplayTotals = reports_v2_compute_display_totals($reportRows, $templateFields, 'Totales generales');
    }

    $reportSubdivisions = array();
    $hasTemplateSubdivideColumn = reports_v2_report_template_has_subdivide_by_field_id_column($pdo);
    if ($hasTemplateSubdivideColumn) {
        $runtimeLevel1 = isset($_GET['report_grid_subdivide_field_id']) ? (int)$_GET['report_grid_subdivide_field_id'] : 0;
        $runtimeLevel2 = isset($_GET['report_grid_subdivide_field_id_level_2']) ? (int)$_GET['report_grid_subdivide_field_id_level_2'] : 0;
        $runtimeLevel3 = isset($_GET['report_grid_subdivide_field_id_level_3']) ? (int)$_GET['report_grid_subdivide_field_id_level_3'] : 0;
        $hasRuntimeSubdivide = $runtimeLevel1 > 0 || $runtimeLevel2 > 0 || $runtimeLevel3 > 0;
        $subdivideFieldIds = array();
        $level1 = $hasRuntimeSubdivide
            ? $runtimeLevel1
            : (isset($selectedTemplate['subdivide_by_field_id']) ? (int)$selectedTemplate['subdivide_by_field_id'] : 0);
        $level2 = $hasRuntimeSubdivide
            ? $runtimeLevel2
            : (isset($selectedTemplate['subdivide_by_field_id_level_2']) ? (int)$selectedTemplate['subdivide_by_field_id_level_2'] : 0);
        if ($level1 > 0) {
            $subdivideFieldIds[] = $level1;
            if ($level2 > 0 && $level2 !== $level1) {
                $subdivideFieldIds[] = $level2;
            }
        }
        if (!empty($subdivideFieldIds)) {
            $showTotals = array(
                1 => isset($_GET['report_grid_subdivide_show_totals_level_1'])
                    ? ((string)$_GET['report_grid_subdivide_show_totals_level_1'] === '1')
                    : (isset($selectedTemplate['subdivide_show_totals_level_1']) ? !empty($selectedTemplate['subdivide_show_totals_level_1']) : true),
                2 => isset($_GET['report_grid_subdivide_show_totals_level_2'])
                    ? ((string)$_GET['report_grid_subdivide_show_totals_level_2'] === '1')
                    : (isset($selectedTemplate['subdivide_show_totals_level_2']) ? !empty($selectedTemplate['subdivide_show_totals_level_2']) : true),
                3 => false,
            );
            $sortDirections = array(
                1 => isset($_GET['report_grid_subdivide_sort_level_1']) && strtolower(trim((string)$_GET['report_grid_subdivide_sort_level_1'])) === 'desc' ? 'desc' : 'asc',
                2 => isset($_GET['report_grid_subdivide_sort_level_2']) && strtolower(trim((string)$_GET['report_grid_subdivide_sort_level_2'])) === 'desc' ? 'desc' : 'asc',
            );
            $reportSubdivisions = reports_v2_build_report_subdivision_tree($reportRows, $subdivideFieldIds, $showTotals, $sortDirections);
        }
    }

    $headers = reports_v2_export_headers($templateFields);
    $rows = reports_v2_build_export_rows($templateFields, $reportRows, $reportSubdivisions, $reportDisplayTotals, true);
    $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim((string)$selectedTemplate['report_name']));
    if ($baseName === '') {
        $baseName = 'reporte';
    }
    $baseName .= '_' . date('Ymd_His');

    if ($format === 'csv') {
        reports_v2_send_csv_download($baseName . '.csv', $headers, $rows);
    } else {
        reports_v2_send_excel_html_download($baseName . '.xls', $headers, $rows, (string)$selectedTemplate['report_name']);
    }
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Export error: ' . $e->getMessage();
}
