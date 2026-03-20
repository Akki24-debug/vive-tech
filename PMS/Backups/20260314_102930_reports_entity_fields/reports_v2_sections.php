<?php
if (!function_exists('reports_v2_render_run_state_hidden_inputs')) {
    function reports_v2_render_run_state_hidden_inputs(array $ctx, $includeView = true)
    {
        if ($includeView) {
            ?>
            <input type="hidden" name="view" value="reports">
            <input type="hidden" name="reports_tab" value="run">
            <?php
        }
        ?>
        <input type="hidden" name="selected_report_template_id" value="<?php echo (int)$ctx['selectedTemplateId']; ?>">
        <input type="hidden" name="selected_report_calculation_id" value="<?php echo (int)$ctx['selectedCalculationId']; ?>">
        <input type="hidden" name="report_property_code" value="<?php echo reports_v2_h($ctx['runFilters']['property_code']); ?>">
        <input type="hidden" name="report_status" value="<?php echo reports_v2_h($ctx['runFilters']['status']); ?>">
        <input type="hidden" name="report_date_type" value="<?php echo reports_v2_h($ctx['runFilters']['date_type']); ?>">
        <input type="hidden" name="report_date_from" value="<?php echo reports_v2_h($ctx['runFilters']['date_from']); ?>">
        <input type="hidden" name="report_date_to" value="<?php echo reports_v2_h($ctx['runFilters']['date_to']); ?>">
        <?php if (!empty($ctx['reportGridSubdivideFieldId'])): ?>
            <input type="hidden" name="report_grid_subdivide_field_id" value="<?php echo (int)$ctx['reportGridSubdivideFieldId']; ?>">
        <?php endif; ?>
        <?php if (!empty($ctx['reportGridSubdivideFieldIdLevel2'])): ?>
            <input type="hidden" name="report_grid_subdivide_field_id_level_2" value="<?php echo (int)$ctx['reportGridSubdivideFieldIdLevel2']; ?>">
        <?php endif; ?>
        <?php if (!empty($ctx['reportGridSubdivideFieldIdLevel3'])): ?>
            <input type="hidden" name="report_grid_subdivide_field_id_level_3" value="<?php echo (int)$ctx['reportGridSubdivideFieldIdLevel3']; ?>">
        <?php endif; ?>
        <?php if (array_key_exists('reportGridSubdivideShowTotalsLevel1', $ctx) && $ctx['reportGridSubdivideShowTotalsLevel1'] !== null): ?>
            <input type="hidden" name="report_grid_subdivide_show_totals_level_1" value="<?php echo !empty($ctx['reportGridSubdivideShowTotalsLevel1']) ? '1' : '0'; ?>">
        <?php endif; ?>
        <?php if (array_key_exists('reportGridSubdivideShowTotalsLevel2', $ctx) && $ctx['reportGridSubdivideShowTotalsLevel2'] !== null): ?>
            <input type="hidden" name="report_grid_subdivide_show_totals_level_2" value="<?php echo !empty($ctx['reportGridSubdivideShowTotalsLevel2']) ? '1' : '0'; ?>">
        <?php endif;
    }
}

if (!function_exists('reports_v2_render_result_table_header_cells')) {
    function reports_v2_render_result_table_header_cells(array $templateFields, $hasEditableReservationFields)
    {
        foreach ($templateFields as $fieldRow) {
            $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0;
            ?>
            <th data-field-id="<?php echo $fieldId; ?>" data-calculate-total="<?php echo !empty($fieldRow['calculate_total']) ? '1' : '0'; ?>" data-format-hint="<?php echo reports_v2_h(isset($fieldRow['format_hint']) ? (string)$fieldRow['format_hint'] : 'auto'); ?>">
              <div class="report-v2-th-inner">
                <div class="report-v2-th-main">
                  <button type="button" class="report-v2-sort-button" data-sort-field-id="<?php echo $fieldId; ?>" aria-label="Ordenar columna <?php echo reports_v2_h(isset($fieldRow['display_name_resolved']) ? $fieldRow['display_name_resolved'] : $fieldRow['display_name']); ?>">
                    <svg viewBox="0 0 16 16" aria-hidden="true">
                      <path class="report-v2-sort-up" fill="currentColor" d="M8 3l3 4H5z"/>
                      <path class="report-v2-sort-down" fill="currentColor" d="M8 13l-3-4h6z"/>
                    </svg>
                  </button>
                  <span class="report-v2-th-label"><?php echo reports_v2_h(isset($fieldRow['display_name_resolved']) ? $fieldRow['display_name_resolved'] : $fieldRow['display_name']); ?></span>
                </div>
                <div class="report-v2-th-actions">
                  <button type="button" class="report-v2-filter-button" data-filter-field-id="<?php echo $fieldId; ?>" aria-label="Filtrar columna <?php echo reports_v2_h(isset($fieldRow['display_name_resolved']) ? $fieldRow['display_name_resolved'] : $fieldRow['display_name']); ?>">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2 3h12l-4.8 5.2v4.1l-2.6 1.7V8.2z"/></svg>
                  </button>
                </div>
              </div>
            </th>
            <?php
        }
        if ($hasEditableReservationFields) {
            echo '<th></th>';
        }
    }
}

if (!function_exists('reports_v2_render_grouped_result_row')) {
    function reports_v2_render_grouped_result_row(array $reportRow, array $templateFields, array $ctx, array &$renderedEditableReservationIds, $indentLevel = 0, array $groupIds = array())
    {
        $rowReservationId = isset($reportRow['reservation_id']) ? (int)$reportRow['reservation_id'] : 0;
        $rowCanEdit = !empty($ctx['hasEditableReservationFields']) && $rowReservationId > 0 && !in_array($rowReservationId, $renderedEditableReservationIds, true);
        if ($rowCanEdit) {
            $renderedEditableReservationIds[] = $rowReservationId;
        }
        $rowIsEditing = $rowCanEdit && !empty($ctx['editReservationId']) && (int)$ctx['editReservationId'] === $rowReservationId;
        $rowFormId = 'report-v2-edit-row-' . $rowReservationId;
        $rowBase = isset($reportRow['base']) && is_array($reportRow['base']) ? $reportRow['base'] : array();
        $rowCreatedDate = isset($rowBase['base_line_item_created_date']) && trim((string)$rowBase['base_line_item_created_date']) !== ''
            ? (string)$rowBase['base_line_item_created_date']
            : (isset($rowBase['reservation_created_date']) ? (string)$rowBase['reservation_created_date'] : '');
        $rowServiceDate = isset($rowBase['base_line_item_service_date']) && trim((string)$rowBase['base_line_item_service_date']) !== ''
            ? (string)$rowBase['base_line_item_service_date']
            : (isset($rowBase['reservation_service_date']) ? (string)$rowBase['reservation_service_date'] : '');
        $rowCheckInDate = isset($rowBase['reservation_check_in_date']) ? (string)$rowBase['reservation_check_in_date'] : '';
        $rowCheckOutDate = isset($rowBase['reservation_check_out_date']) ? (string)$rowBase['reservation_check_out_date'] : '';
        $rowClasses = array('report-v2-result-row');
        if ($rowIsEditing) {
            $rowClasses[] = 'is-editing';
        }
        if ($indentLevel > 1) {
            $rowClasses[] = 'report-v2-result-row--indent-' . (int)$indentLevel;
        }
        if ($indentLevel >= 3) {
            $rowClasses[] = 'report-v2-result-row--deep';
        }
        $rowAttributes = array(
            'class="' . reports_v2_h(implode(' ', $rowClasses)) . '"',
            'data-reservation-id="' . (int)$rowReservationId . '"',
            'data-inline-edit-row="' . ($rowCanEdit ? '1' : '0') . '"',
            'data-row-created-date="' . reports_v2_h($rowCreatedDate) . '"',
            'data-row-service-date="' . reports_v2_h($rowServiceDate) . '"',
            'data-row-check-in-date="' . reports_v2_h($rowCheckInDate) . '"',
            'data-row-check-out-date="' . reports_v2_h($rowCheckOutDate) . '"',
        );
        foreach ($groupIds as $groupLevel => $groupId) {
            $groupLevel = (int)$groupLevel;
            if ($groupLevel <= 1 || trim((string)$groupId) === '') {
                continue;
            }
            $rowAttributes[] = 'data-group-level-' . $groupLevel . '="' . reports_v2_h($groupId) . '"';
        }
        ?>
        <tr <?php echo implode(' ', $rowAttributes); ?>>
          <?php foreach ($templateFields as $fieldRow): ?>
            <?php
              $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0;
              $cell = isset($reportRow['cells'][$fieldId]) ? $reportRow['cells'][$fieldId] : array('display' => '');
              $fieldCode = isset($fieldRow['reservation_field_code']) ? (string)$fieldRow['reservation_field_code'] : '';
              $canEditCell = $rowCanEdit
                  && (isset($fieldRow['field_type']) ? (string)$fieldRow['field_type'] : '') === 'reservation'
                  && !empty($fieldRow['is_editable'])
                  && isset($ctx['editableInputCatalog'][$fieldCode]);
              $cellMeta = isset($cell['meta']) && is_array($cell['meta']) ? $cell['meta'] : array('data_type' => 'text', 'numeric' => false);
              $cellDisplay = isset($cell['display']) ? (string)$cell['display'] : '';
              $cellRaw = isset($cell['raw']) ? $cell['raw'] : '';
              $cellFilterValue = trim($cellDisplay) !== '' ? $cellDisplay : '__EMPTY__';
            ?>
            <td data-field-id="<?php echo $fieldId; ?>" data-cell-display="<?php echo reports_v2_h($cellDisplay); ?>" data-cell-filter-value="<?php echo reports_v2_h($cellFilterValue); ?>" data-cell-raw="<?php echo reports_v2_h(is_scalar($cellRaw) || $cellRaw === null ? (string)$cellRaw : ''); ?>" data-cell-type="<?php echo reports_v2_h(isset($cellMeta['data_type']) ? (string)$cellMeta['data_type'] : 'text'); ?>" title="<?php echo reports_v2_h(isset($cell['error']) ? $cell['error'] : ''); ?>">
              <?php if ($canEditCell): ?>
                <?php
                  $inputMeta = $ctx['editableInputCatalog'][$fieldCode];
                  $inputType = isset($inputMeta['input_type']) ? (string)$inputMeta['input_type'] : 'text';
                  $inputValue = array_key_exists($fieldId, $ctx['rowEditDraftValues'])
                      ? $ctx['rowEditDraftValues'][$fieldId]
                      : (isset($cell['raw']) ? $cell['raw'] : '');
                ?>
                <span class="report-v2-inline-cell-display"><?php echo reports_v2_h(isset($cell['display']) ? $cell['display'] : ''); ?></span>
                <input
                  class="report-v2-inline-cell-input"
                  type="<?php echo reports_v2_h($inputType); ?>"
                  name="row_edit_values[<?php echo $fieldId; ?>]"
                  value="<?php echo reports_v2_h($inputValue); ?>"
                  data-original-value="<?php echo reports_v2_h($inputValue); ?>"
                  form="<?php echo reports_v2_h($rowFormId); ?>"
                >
              <?php else: ?>
                <?php echo reports_v2_h(isset($cell['display']) ? $cell['display'] : ''); ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <?php if (!empty($ctx['hasEditableReservationFields'])): ?>
            <td class="report-v2-actions-cell">
              <?php if ($rowCanEdit): ?>
                <div class="report-v2-inline-actions">
                  <button type="button" class="report-v2-inline-edit-button">Editar</button>
                  <form method="post" class="report-v2-inline-form report-v2-inline-save-form" id="<?php echo reports_v2_h($rowFormId); ?>">
                    <input type="hidden" name="reports_action" value="save_report_row">
                    <input type="hidden" name="inline_ajax" value="1">
                    <input type="hidden" name="reports_tab" value="run">
                    <?php reports_v2_render_run_state_hidden_inputs($ctx, false); ?>
                    <input type="hidden" name="reservation_id" value="<?php echo $rowReservationId; ?>">
                    <button type="submit">Guardar</button>
                  </form>
                  <button type="button" class="report-v2-inline-cancel-button" aria-label="Cancelar cambios" title="Cancelar cambios">X</button>
                </div>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
        <?php
    }
}

if (!function_exists('reports_v2_render_grouped_rows_tbody')) {
    function reports_v2_render_grouped_rows_tbody(array $rows, array $templateFields, array $ctx, array &$renderedEditableReservationIds, $indentLevel = 0, array $groupIds = array(), array $tbodyAttributes = array())
    {
        ?>
        <tbody <?php echo implode(' ', $tbodyAttributes); ?>>
          <?php foreach ($rows as $reportRow): ?>
            <?php reports_v2_render_grouped_result_row($reportRow, $templateFields, $ctx, $renderedEditableReservationIds, $indentLevel, $groupIds); ?>
          <?php endforeach; ?>
        </tbody>
        <?php
    }
}

if (!function_exists('reports_v2_render_group_marker_tbody')) {
    function reports_v2_render_group_marker_tbody(array $node, array $templateFields, $hasEditableReservationFields, $nodeId, $parentNodeId = '')
    {
        $level = isset($node['level']) ? (int)$node['level'] : 2;
        $colspan = count($templateFields) + ($hasEditableReservationFields ? 1 : 0);
        $groupFieldId = isset($node['field_id']) ? (int)$node['field_id'] : 0;
        $groupFieldLabel = '';
        foreach ($templateFields as $templateFieldRow) {
            $templateFieldId = isset($templateFieldRow['id_report_template_field']) ? (int)$templateFieldRow['id_report_template_field'] : 0;
            if ($templateFieldId === $groupFieldId) {
                $groupFieldLabel = isset($templateFieldRow['display_name_resolved']) && trim((string)$templateFieldRow['display_name_resolved']) !== ''
                    ? (string)$templateFieldRow['display_name_resolved']
                    : (isset($templateFieldRow['display_name']) ? (string)$templateFieldRow['display_name'] : '');
                break;
            }
        }
        if ($groupFieldLabel === '') {
            $groupFieldLabel = 'Subdivision';
        }
        ?>
        <tbody class="report-v2-group-body report-v2-group-body--marker report-v2-group-body--level-<?php echo $level; ?>" data-group-role="marker" data-group-node-id="<?php echo reports_v2_h($nodeId); ?>" data-group-level="<?php echo $level; ?>" data-group-parent-id="<?php echo reports_v2_h($parentNodeId); ?>">
          <tr class="report-v2-group-row report-v2-group-row--level-<?php echo $level; ?>" data-group-node-id="<?php echo reports_v2_h($nodeId); ?>" data-group-level="<?php echo $level; ?>">
            <td colspan="<?php echo $colspan; ?>">
              <div class="report-v2-group-row-inner">
                <span class="report-v2-group-row-kicker"><?php echo reports_v2_h($groupFieldLabel); ?></span>
                <span class="report-v2-group-row-label"><?php echo reports_v2_h(isset($node['label']) ? $node['label'] : 'Sin valor'); ?></span>
              </div>
            </td>
          </tr>
        </tbody>
        <?php
    }
}

if (!function_exists('reports_v2_render_inline_total_tbody')) {
    function reports_v2_render_inline_total_tbody(array $totals, array $templateFields, $hasEditableReservationFields, $nodeId, $level, $label = 'Subtotal', $parentNodeId = '')
    {
        ?>
        <tbody class="report-v2-group-body report-v2-group-body--total report-v2-group-body--level-<?php echo (int)$level; ?>" data-group-role="total" data-group-node-id="<?php echo reports_v2_h($nodeId); ?>" data-group-level="<?php echo (int)$level; ?>" data-group-parent-id="<?php echo reports_v2_h($parentNodeId); ?>" data-inline-total-label="<?php echo reports_v2_h($label); ?>">
          <tr class="report-v2-inline-total-row report-v2-inline-total-row--level-<?php echo (int)$level; ?>" data-inline-total-group="<?php echo reports_v2_h($nodeId); ?>" data-group-level="<?php echo (int)$level; ?>">
            <?php foreach ($templateFields as $fieldRow): ?>
              <?php
                $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0;
                $totalCell = isset($totals[$fieldId]) ? $totals[$fieldId] : array('display' => '');
              ?>
              <td data-total-field-id="<?php echo $fieldId; ?>"><?php echo reports_v2_h(isset($totalCell['display']) ? $totalCell['display'] : ''); ?></td>
            <?php endforeach; ?>
            <?php if ($hasEditableReservationFields): ?><td></td><?php endif; ?>
          </tr>
        </tbody>
        <?php
    }
}

if (!function_exists('reports_v2_render_nested_group_tbodies')) {
    function reports_v2_render_nested_group_tbodies(array $nodes, array $templateFields, array $ctx, array &$renderedEditableReservationIds, array $ancestorGroupIds = array(), $parentNodeId = '')
    {
        foreach (array_values($nodes) as $nodeIndex => $node) {
            $level = isset($node['level']) ? (int)$node['level'] : 2;
            $nodeId = ($parentNodeId !== '' ? $parentNodeId . '-' : 'group-') . $level . '-' . ($nodeIndex + 1);
            reports_v2_render_group_marker_tbody($node, $templateFields, !empty($ctx['hasEditableReservationFields']), $nodeId, $parentNodeId);
            $nextGroupIds = $ancestorGroupIds;
            $nextGroupIds[$level] = $nodeId;

            if (!empty($node['children'])) {
                reports_v2_render_nested_group_tbodies($node['children'], $templateFields, $ctx, $renderedEditableReservationIds, $nextGroupIds, $nodeId);
            } else {
                $tbodyAttrs = array(
                    'class="report-v2-group-body report-v2-group-body--rows report-v2-group-body--level-' . $level . '"',
                    'data-group-role="rows"',
                    'data-group-node-id="' . reports_v2_h($nodeId) . '"',
                    'data-group-level="' . $level . '"',
                    'data-group-parent-id="' . reports_v2_h($parentNodeId) . '"',
                );
                reports_v2_render_grouped_rows_tbody($node['rows'], $templateFields, $ctx, $renderedEditableReservationIds, $level, $nextGroupIds, $tbodyAttrs);
            }

            if (!empty($ctx['reportHasCalculatedTotals']) && !empty($node['show_totals'])) {
                $nodeTotals = reports_v2_compute_display_totals($node['rows'], $templateFields, 'Subtotal');
                reports_v2_render_inline_total_tbody($nodeTotals, $templateFields, !empty($ctx['hasEditableReservationFields']), $nodeId, $level, 'Subtotal', $parentNodeId);
            }
        }
    }
}
?>
<?php if ($activeTab === 'templates'): ?>
<div class="report-v2-grid">
  <section class="report-v2-card report-v2-stack report-v2-card--full">
    <div>
      <h2>Plantillas</h2>
      <p class="report-v2-muted">Cada plantilla define las columnas visibles y el objeto que representa cada fila del reporte.</p>
    </div>
    <div class="report-v2-list">
      <?php if (empty($templates)): ?>
        <div class="report-v2-list-item"><p class="report-v2-muted">Todavia no hay plantillas creadas.</p></div>
      <?php else: ?>
        <?php foreach ($templates as $templateRow): ?>
          <?php $templateId = isset($templateRow['id_report_template']) ? (int)$templateRow['id_report_template'] : 0; ?>
          <div class="report-v2-list-item <?php echo $templateId === $selectedTemplateId ? 'is-selected' : ''; ?>">
            <h4><?php echo reports_v2_h($templateRow['report_name']); ?></h4>
            <div class="report-v2-pill-list">
              <span class="report-v2-chip"><?php echo reports_v2_h($templateRow['report_key']); ?></span>
              <span class="report-v2-chip"><?php echo reports_v2_h(isset($rowSourceOptions[$templateRow['row_source']]) ? $rowSourceOptions[$templateRow['row_source']] : 'Reservacion'); ?></span>
              <?php if ((isset($templateRow['row_source']) ? (string)$templateRow['row_source'] : 'reservation') === 'line_item' && !empty($templateRow['line_item_type_scope'])): ?>
                <span class="report-v2-chip"><?php echo reports_v2_h(isset($lineItemRowTypeOptions[$templateRow['line_item_type_scope']]) ? $lineItemRowTypeOptions[$templateRow['line_item_type_scope']] : $templateRow['line_item_type_scope']); ?></span>
              <?php endif; ?>
              <?php if (!empty($templateRow['subdivide_by_field_id'])): ?>
                <span class="report-v2-chip">Subdividida</span>
              <?php endif; ?>
              <span class="report-v2-chip"><?php echo (int)$templateRow['field_count']; ?> campos</span>
              <span class="report-v2-chip"><?php echo !empty($templateRow['is_active']) ? 'Activa' : 'Inactiva'; ?></span>
            </div>
            <?php if (!empty($templateRow['description'])): ?>
              <p class="report-v2-muted"><?php echo reports_v2_h($templateRow['description']); ?></p>
            <?php endif; ?>
            <form method="get">
              <input type="hidden" name="view" value="reports">
              <input type="hidden" name="reports_tab" value="<?php echo reports_v2_h($activeTab); ?>">
              <input type="hidden" name="selected_report_template_id" value="<?php echo $templateId; ?>">
              <input type="hidden" name="selected_report_calculation_id" value="<?php echo $selectedCalculationId; ?>">
              <input type="hidden" name="new_report_template" value="0">
              <button type="submit">Seleccionar</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($canDesign): ?>
      <div class="report-v2-card report-v2-stack">
        <h3><?php echo $selectedTemplate ? 'Editar plantilla' : 'Nueva plantilla'; ?></h3>
        <form method="post" class="report-v2-stack">
          <input type="hidden" name="reports_action" value="save_template">
          <input type="hidden" name="reports_tab" value="templates">
          <input type="hidden" name="selected_report_calculation_id" value="<?php echo $selectedCalculationId; ?>">
          <input type="hidden" name="new_report_template" value="0">
          <input type="hidden" name="template_id" value="<?php echo $selectedTemplate ? (int)$selectedTemplate['id_report_template'] : 0; ?>">
          <?php
            $selectedTemplateRowSource = $selectedTemplate && isset($selectedTemplate['row_source']) ? (string)$selectedTemplate['row_source'] : 'reservation';
            if (!isset($rowSourceOptions[$selectedTemplateRowSource])) {
                $selectedTemplateRowSource = 'reservation';
            }
            $selectedTemplateLineItemTypeScope = $selectedTemplate && isset($selectedTemplate['line_item_type_scope'])
                ? (string)$selectedTemplate['line_item_type_scope']
                : 'payment';
            if (!isset($lineItemRowTypeOptions[$selectedTemplateLineItemTypeScope])) {
                $selectedTemplateLineItemTypeScope = 'payment';
            }
            $selectedTemplateSubdivideByFieldId = $selectedTemplate && isset($selectedTemplate['subdivide_by_field_id'])
                ? (int)$selectedTemplate['subdivide_by_field_id']
                : 0;
            $selectedTemplateSubdivideByFieldIdLevel2 = $selectedTemplate && isset($selectedTemplate['subdivide_by_field_id_level_2'])
                ? (int)$selectedTemplate['subdivide_by_field_id_level_2']
                : 0;
            $selectedTemplateSubdivideShowTotalsLevel1 = !$selectedTemplate || !isset($selectedTemplate['subdivide_show_totals_level_1']) || !empty($selectedTemplate['subdivide_show_totals_level_1']) ? 1 : 0;
            $selectedTemplateSubdivideShowTotalsLevel2 = !$selectedTemplate || !isset($selectedTemplate['subdivide_show_totals_level_2']) || !empty($selectedTemplate['subdivide_show_totals_level_2']) ? 1 : 0;
          ?>
          <div class="report-v2-form-grid report-v2-form-grid--4">
            <label><span>Nombre</span><input type="text" name="template_name" value="<?php echo reports_v2_h($selectedTemplate ? $selectedTemplate['report_name'] : ''); ?>" required></label>
            <label><span>Estatus</span><select name="template_is_active"><option value="1" <?php echo (!$selectedTemplate || !empty($selectedTemplate['is_active'])) ? 'selected' : ''; ?>>Activa</option><option value="0" <?php echo ($selectedTemplate && empty($selectedTemplate['is_active'])) ? 'selected' : ''; ?>>Inactiva</option></select></label>
            <label><span>Objeto por fila</span><select name="template_row_source" id="reports-v2-template-row-source"><?php foreach ($rowSourceOptions as $rowSourceCode => $rowSourceLabel): ?><option value="<?php echo reports_v2_h($rowSourceCode); ?>" <?php echo $selectedTemplateRowSource === $rowSourceCode ? 'selected' : ''; ?>><?php echo reports_v2_h($rowSourceLabel); ?></option><?php endforeach; ?></select></label>
            <label data-template-row-source-panel="line_item"><span>Tipo de line item</span><select name="template_line_item_type_scope"><?php foreach ($lineItemRowTypeOptions as $typeCode => $typeLabel): ?><option value="<?php echo reports_v2_h($typeCode); ?>" <?php echo $selectedTemplateLineItemTypeScope === $typeCode ? 'selected' : ''; ?>><?php echo reports_v2_h($typeLabel); ?></option><?php endforeach; ?></select></label>
          </div>
          <div class="report-v2-form-grid report-v2-form-grid--4">
            <label><span>Seccion principal</span><select name="template_subdivide_by_field_id"><option value="0">Sin subdivision</option><?php foreach ($templateFields as $templateFieldOption): $templateFieldOptionId = isset($templateFieldOption['id_report_template_field']) ? (int)$templateFieldOption['id_report_template_field'] : 0; ?><option value="<?php echo $templateFieldOptionId; ?>" <?php echo $selectedTemplateSubdivideByFieldId === $templateFieldOptionId ? 'selected' : ''; ?>><?php echo reports_v2_h(isset($templateFieldOption['display_name_resolved']) ? $templateFieldOption['display_name_resolved'] : $templateFieldOption['display_name']); ?></option><?php endforeach; ?></select></label>
            <label><span>Totales seccion 1</span><select name="template_subdivide_show_totals_level_1"><option value="1" <?php echo $selectedTemplateSubdivideShowTotalsLevel1 ? 'selected' : ''; ?>>Si</option><option value="0" <?php echo !$selectedTemplateSubdivideShowTotalsLevel1 ? 'selected' : ''; ?>>No</option></select></label>
            <label><span>Subseccion 2</span><select name="template_subdivide_by_field_id_level_2"><option value="0">Sin subseccion</option><?php foreach ($templateFields as $templateFieldOption): $templateFieldOptionId = isset($templateFieldOption['id_report_template_field']) ? (int)$templateFieldOption['id_report_template_field'] : 0; ?><option value="<?php echo $templateFieldOptionId; ?>" <?php echo $selectedTemplateSubdivideByFieldIdLevel2 === $templateFieldOptionId ? 'selected' : ''; ?>><?php echo reports_v2_h(isset($templateFieldOption['display_name_resolved']) ? $templateFieldOption['display_name_resolved'] : $templateFieldOption['display_name']); ?></option><?php endforeach; ?></select></label>
            <label><span>Totales seccion 2</span><select name="template_subdivide_show_totals_level_2"><option value="1" <?php echo $selectedTemplateSubdivideShowTotalsLevel2 ? 'selected' : ''; ?>>Si</option><option value="0" <?php echo !$selectedTemplateSubdivideShowTotalsLevel2 ? 'selected' : ''; ?>>No</option></select></label>
          </div>
          <label><span>Descripcion</span><textarea name="template_description" rows="3"><?php echo reports_v2_h($selectedTemplate ? $selectedTemplate['description'] : ''); ?></textarea></label>
          <div class="report-v2-actions">
            <button type="submit"><?php echo $selectedTemplate ? 'Guardar plantilla' : 'Crear plantilla'; ?></button>
          </div>
        </form>
        <?php if ($selectedTemplate): ?>
          <div class="report-v2-actions">
            <a class="report-v2-chip" href="<?php echo reports_v2_h('?view=reports&reports_tab=templates&selected_report_template_id=0&selected_report_field_id=0&new_report_template=1'); ?>">Nueva</a>
            <form method="post" onsubmit="return confirm('Se archivara la plantilla seleccionada.');"><input type="hidden" name="reports_action" value="delete_template"><input type="hidden" name="reports_tab" value="templates"><input type="hidden" name="template_id" value="<?php echo (int)$selectedTemplate['id_report_template']; ?>"><button type="submit">Archivar</button></form>
          </div>
        <?php else: ?>
          <div class="report-v2-actions">
            <a class="report-v2-chip" href="<?php echo reports_v2_h('?view=reports&reports_tab=templates&selected_report_template_id=0&selected_report_field_id=0&new_report_template=1'); ?>">Nueva</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

</div>
<?php endif; ?>

<?php if ($canDesign && ($activeTab === 'templates' || $activeTab === 'calculations')): ?>
  <div class="report-v2-grid-bottom">
    <?php if ($activeTab === 'templates'): ?>
    <section class="report-v2-card report-v2-stack">
      <div><h2>Campos de la plantilla</h2><p class="report-v2-muted">Cada columna puede venir de un campo real de la reservacion, de un line item o de un calculo.</p></div>
      <?php if (!$selectedTemplate): ?>
        <p class="report-v2-muted">Selecciona una plantilla para configurar sus campos.</p>
      <?php else: ?>
        <div class="report-v2-table-wrap">
          <table class="report-v2-table">
            <thead><tr><th>Orden</th><th>Alias</th><th>Tipo</th><th>Origen</th><th>Default</th><th>Editable</th><th>Total</th><th>Formato</th><th>Visible</th><th>Activa</th><th></th><th></th></tr></thead>
            <tbody>
              <?php if (empty($templateFields)): ?>
                <tr><td colspan="12" class="report-v2-muted">La plantilla no tiene campos.</td></tr>
              <?php else: ?>
                <?php foreach ($templateFields as $fieldRow): ?>
                  <?php
                  $fieldRowId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0;
                  $originLabel = '';
                  if ((string)$fieldRow['field_type'] === 'reservation') {
                      $fieldCode = isset($fieldRow['reservation_field_code']) ? (string)$fieldRow['reservation_field_code'] : '';
                      $originLabel = isset($reservationFieldCatalog[$fieldCode]) ? $reservationFieldCatalog[$fieldCode]['label'] : $fieldCode;
                  } elseif ((string)$fieldRow['field_type'] === 'line_item') {
                      $catalogLabels = !empty($fieldRow['linked_catalog_labels']) && is_array($fieldRow['linked_catalog_labels'])
                          ? array_filter(array_map('trim', $fieldRow['linked_catalog_labels']))
                          : array();
                      if (!empty($catalogLabels)) {
                          $originLabel = implode(' | ', $catalogLabels) . ' / ' . (isset($metricOptions[$fieldRow['source_metric']]['label']) ? $metricOptions[$fieldRow['source_metric']]['label'] : (string)$fieldRow['source_metric']);
                      } elseif (empty($fieldRow['id_line_item_catalog'])) {
                          $originLabel = 'Registro actual / ' . (isset($metricOptions[$fieldRow['source_metric']]['label']) ? $metricOptions[$fieldRow['source_metric']]['label'] : (string)$fieldRow['source_metric']);
                      } else {
                          $originLabel = trim((string)$fieldRow['line_item_name']) . ' / ' . (isset($metricOptions[$fieldRow['source_metric']]['label']) ? $metricOptions[$fieldRow['source_metric']]['label'] : (string)$fieldRow['source_metric']);
                      }
                  } else {
                      $originLabel = isset($fieldRow['calc_name']) ? (string)$fieldRow['calc_name'] : 'Calculado';
                  }
                  ?>
                  <tr>
                    <td><?php echo (int)$fieldRow['order_index']; ?></td>
                    <td><?php echo reports_v2_h(isset($fieldRow['display_name_resolved']) ? $fieldRow['display_name_resolved'] : $fieldRow['display_name']); ?></td>
                    <td><?php echo reports_v2_h(reports_v2_field_type_options()[$fieldRow['field_type']]); ?></td>
                    <td><?php echo reports_v2_h($originLabel); ?></td>
                    <td><?php echo reports_v2_h(isset($fieldRow['default_value']) ? (string)$fieldRow['default_value'] : ''); ?></td>
                    <td><?php echo !empty($fieldRow['is_editable']) ? 'Si' : 'No'; ?></td>
                    <td><?php echo !empty($fieldRow['calculate_total']) ? 'Si' : 'No'; ?></td>
                    <td><?php echo reports_v2_h(isset($formatOptions[$fieldRow['format_hint']]) ? $formatOptions[$fieldRow['format_hint']] : $fieldRow['format_hint']); ?></td>
                    <td><?php echo !empty($fieldRow['is_visible']) ? 'Si' : 'No'; ?></td>
                    <td><?php echo !empty($fieldRow['is_active']) ? 'Si' : 'No'; ?></td>
                    <td><form method="get"><input type="hidden" name="view" value="reports"><input type="hidden" name="reports_tab" value="templates"><input type="hidden" name="selected_report_template_id" value="<?php echo (int)$selectedTemplate['id_report_template']; ?>"><input type="hidden" name="selected_report_calculation_id" value="<?php echo $selectedCalculationId; ?>"><input type="hidden" name="selected_report_field_id" value="<?php echo $fieldRowId; ?>"><button type="submit">Editar</button></form></td>
                    <td><form method="post" onsubmit="return confirm('Se archivara este campo.');"><input type="hidden" name="reports_action" value="delete_field"><input type="hidden" name="reports_tab" value="templates"><input type="hidden" name="template_id" value="<?php echo (int)$selectedTemplate['id_report_template']; ?>"><input type="hidden" name="field_id" value="<?php echo $fieldRowId; ?>"><button type="submit">Archivar</button></form></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="report-v2-card report-v2-stack">
          <h3><?php echo $selectedField ? 'Editar campo' : 'Agregar campo'; ?></h3>
          <p class="report-v2-muted">`Alias de columna` es el nombre con el que se mostrara esta columna en el reporte.</p>
          <?php
            $selectedFieldType = $selectedField ? (string)$selectedField['field_type'] : 'reservation';
            $selectedReservationFieldCode = $selectedField && isset($selectedField['reservation_field_code'])
                ? (string)$selectedField['reservation_field_code']
                : '';
            $selectedReservationFieldSupportsInlineEdit = $selectedFieldType === 'reservation'
                && $selectedReservationFieldCode !== ''
                && pms_report_reservation_field_is_inline_editable($selectedReservationFieldCode);
          ?>
          <form method="post" class="report-v2-stack">
            <input type="hidden" name="reports_action" value="save_field">
            <input type="hidden" name="reports_tab" value="templates">
            <input type="hidden" name="template_id" value="<?php echo (int)$selectedTemplate['id_report_template']; ?>">
            <input type="hidden" name="selected_report_template_id" value="<?php echo (int)$selectedTemplate['id_report_template']; ?>">
            <input type="hidden" name="field_id" value="<?php echo $selectedField ? (int)$selectedField['id_report_template_field'] : 0; ?>">
            <div class="report-v2-form-grid report-v2-form-grid--4">
              <label><span>Tipo</span><select name="field_type" id="reports-v2-field-type"><?php foreach (reports_v2_field_type_options() as $typeCode => $typeLabel): ?><option value="<?php echo reports_v2_h($typeCode); ?>" <?php echo $selectedFieldType === $typeCode ? 'selected' : ''; ?>><?php echo reports_v2_h($typeLabel); ?></option><?php endforeach; ?></select></label>
              <label><span>Orden</span><input type="number" name="order_index" min="1" value="<?php echo $selectedField ? (int)$selectedField['order_index'] : (int)$nextFieldOrder; ?>"></label>
              <label><span>Formato</span><select name="field_format_hint"><?php $selectedFieldFormat = $selectedField ? (string)$selectedField['format_hint'] : 'auto'; foreach ($formatOptions as $formatCode => $formatLabel): ?><option value="<?php echo reports_v2_h($formatCode); ?>" <?php echo $selectedFieldFormat === $formatCode ? 'selected' : ''; ?>><?php echo reports_v2_h($formatLabel); ?></option><?php endforeach; ?></select></label>
              <label><span>Visible</span><select name="field_is_visible"><option value="1" <?php echo (!$selectedField || !empty($selectedField['is_visible'])) ? 'selected' : ''; ?>>Si</option><option value="0" <?php echo ($selectedField && empty($selectedField['is_visible'])) ? 'selected' : ''; ?>>No</option></select></label>
            </div>
            <div class="report-v2-form-grid">
              <label><span>Alias de columna</span><input type="text" name="display_name" value="<?php echo reports_v2_h($selectedField ? (isset($selectedField['display_name_input']) ? $selectedField['display_name_input'] : $selectedField['display_name']) : ''); ?>" placeholder="Ejemplo: Total OTA, Tipo de pago, Huesped. Si lo dejas vacio toma el nombre base"></label>
              <label><span>Default</span><input type="text" name="field_default_value" value="<?php echo reports_v2_h($selectedField && isset($selectedField['default_value']) ? $selectedField['default_value'] : ''); ?>" placeholder="Se muestra si el valor sale vacio o en 0"></label>
            </div>
            <div class="report-v2-form-grid report-v2-form-grid--4">
              <label><span>Activa</span><select name="field_is_active"><option value="1" <?php echo (!$selectedField || !empty($selectedField['is_active'])) ? 'selected' : ''; ?>>Si</option><option value="0" <?php echo ($selectedField && empty($selectedField['is_active'])) ? 'selected' : ''; ?>>No</option></select></label>
              <label data-field-editable-panel="global">
                <span>Editable en reporte</span>
                <select name="field_is_editable" <?php echo $selectedReservationFieldSupportsInlineEdit ? '' : 'disabled'; ?>>
                  <option value="0" <?php echo (!$selectedField || empty($selectedField['is_editable'])) ? 'selected' : ''; ?>>No</option>
                  <option value="1" <?php echo ($selectedField && !empty($selectedField['is_editable'])) ? 'selected' : ''; ?>>Si</option>
                </select>
                <small class="report-v2-muted" data-field-editable-help="supported" style="<?php echo $selectedReservationFieldSupportsInlineEdit ? '' : 'display:none;'; ?>">Permite editar este valor directo en el grid.</small>
                <small class="report-v2-muted" data-field-editable-help="unsupported" style="<?php echo $selectedReservationFieldSupportsInlineEdit ? 'display:none;' : ''; ?>">
                  <?php echo $selectedFieldType === 'reservation'
                      ? 'Disponible solo para algunos campos de reservacion.'
                      : 'Visible, pero solo aplica a campos de tipo Reservacion.'; ?>
                </small>
              </label>
              <label><span>Calcular total</span><select name="field_calculate_total"><option value="0" <?php echo (!$selectedField || empty($selectedField['calculate_total'])) ? 'selected' : ''; ?>>No</option><option value="1" <?php echo ($selectedField && !empty($selectedField['calculate_total'])) ? 'selected' : ''; ?>>Si</option></select></label>
              <div class="report-v2-muted" style="align-self:end;padding-bottom:6px;">El switch de editable siempre queda visible aqui.</div>
            </div>
            <div class="report-v2-form-grid" data-field-panel="reservation">
              <label><span>Campo de reservacion</span><select name="reservation_field_code" id="reports-v2-reservation-field-code"><?php $fieldGroups = array(); foreach ($reservationFieldCatalog as $fieldCode => $fieldMeta) { $groupName = isset($fieldMeta['group']) ? $fieldMeta['group'] : 'Otros'; if (!isset($fieldGroups[$groupName])) { $fieldGroups[$groupName] = array(); } $fieldGroups[$groupName][$fieldCode] = $fieldMeta; } foreach ($fieldGroups as $groupName => $groupFields): ?><optgroup label="<?php echo reports_v2_h($groupName); ?>"><?php foreach ($groupFields as $fieldCode => $fieldMeta): ?><option value="<?php echo reports_v2_h($fieldCode); ?>" <?php echo $selectedReservationFieldCode === $fieldCode ? 'selected' : ''; ?>><?php echo reports_v2_h($fieldMeta['label']); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            </div>
            <div class="report-v2-form-grid report-v2-form-grid--3" data-field-panel="line_item" style="display:none;">
              <?php
              $selectedLineItemCatalogId = $selectedField ? (int)$selectedField['id_line_item_catalog'] : 0;
              $selectedLineItemCatalogIds = $selectedField && !empty($selectedField['linked_catalog_ids']) && is_array($selectedField['linked_catalog_ids'])
                  ? array_map('intval', $selectedField['linked_catalog_ids'])
                  : ($selectedLineItemCatalogId > 0 ? array($selectedLineItemCatalogId) : array());
              $selectedAllowMultipleCatalogs = $selectedField && !empty($selectedField['allow_multiple_catalogs']) ? 1 : 0;
              $selectedLineItemDisplayMode = $selectedField ? 'alias' : 'name';
              $selectedSourceMetricRaw = $selectedField ? trim((string)$selectedField['source_metric']) : 'amount_cents';
              $selectedSourceMetric = isset($metricOptions[$selectedSourceMetricRaw]) ? $selectedSourceMetricRaw : ($selectedField ? '' : 'amount_cents');
              if (
                  $selectedField
                  && isset($selectedField['display_name'])
                  && $selectedLineItemCatalogId > 0
              ) {
                  $selectedDisplayName = trim((string)$selectedField['display_name']);
                  foreach ($lineItemCatalogs as $catalogRow) {
                      $catalogId = isset($catalogRow['id_line_item_catalog']) ? (int)$catalogRow['id_line_item_catalog'] : 0;
                      if ($catalogId !== $selectedLineItemCatalogId) {
                          continue;
                      }
                      $itemName = trim((string)(isset($catalogRow['item_name']) ? $catalogRow['item_name'] : ''));
                      $categoryName = trim((string)(isset($catalogRow['category_name']) ? $catalogRow['category_name'] : ''));
                      $subcategoryName = trim((string)(isset($catalogRow['subcategory_name']) ? $catalogRow['subcategory_name'] : ''));
                      if ($selectedDisplayName !== '' && $selectedDisplayName === $itemName) {
                          $selectedLineItemDisplayMode = 'name';
                      } elseif (
                          $selectedDisplayName !== ''
                          && (
                              reports_v2_line_item_name_parent_token_extract($selectedDisplayName) !== null
                              || $selectedDisplayName === 'Nombre - nombre padre'
                              || $selectedDisplayName === ($itemName . ' - nombre padre')
                              || ($itemName !== '' && $categoryName !== '' && $selectedDisplayName === ($itemName . ' - ' . $categoryName))
                              || ($itemName !== '' && $subcategoryName !== '' && $selectedDisplayName === ($itemName . ' - ' . $subcategoryName))
                          )
                      ) {
                          $selectedLineItemDisplayMode = 'name_parent';
                      }
                      break;
                  }
              }
              $selectedTemplateForFieldRowSource = $selectedTemplate && isset($selectedTemplate['row_source'])
                  ? (string)$selectedTemplate['row_source']
                  : 'reservation';
              ?>
              <label><span>Nombre de columna</span><select name="line_item_display_name_mode"><option value="alias" <?php echo $selectedLineItemDisplayMode === 'alias' ? 'selected' : ''; ?>>Alias manual</option><option value="name" <?php echo $selectedLineItemDisplayMode === 'name' ? 'selected' : ''; ?>>Nombre</option><option value="name_parent" <?php echo $selectedLineItemDisplayMode === 'name_parent' ? 'selected' : ''; ?>>Nombre - nombre padre</option></select></label>
              <label><span>Modo</span><select name="field_allow_multiple_catalogs"><option value="0" <?php echo !$selectedAllowMultipleCatalogs ? 'selected' : ''; ?>>Un concepto</option><option value="1" <?php echo $selectedAllowMultipleCatalogs ? 'selected' : ''; ?>>Multiples conceptos</option></select></label>
              <label><span>Line item principal</span><select name="line_item_catalog_id"><?php if ($selectedTemplateForFieldRowSource === 'line_item'): ?><option value="0" <?php echo $selectedLineItemCatalogId <= 0 ? 'selected' : ''; ?>>Registro actual</option><?php endif; ?><?php foreach ($lineItemCatalogs as $catalogRow): $optionLabel = trim((string)$catalogRow['item_name']); if ($optionLabel === '') { $optionLabel = 'Line item #' . (int)$catalogRow['id_line_item_catalog']; } ?><option value="<?php echo (int)$catalogRow['id_line_item_catalog']; ?>" <?php echo $selectedLineItemCatalogId === (int)$catalogRow['id_line_item_catalog'] ? 'selected' : ''; ?>><?php echo reports_v2_h($optionLabel); ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="report-v2-form-grid report-v2-form-grid--3" data-field-panel="line_item" style="display:none;">
              <div class="report-v2-muted" style="align-self:end;padding-bottom:6px;">Si eliges `Nombre` o `Nombre - nombre padre`, el alias escrito arriba se ignora al guardar.</div>
              <label><span>Metrica</span><select name="source_metric"><?php if ($selectedField && $selectedSourceMetric === ''): ?><option value="" selected>Metrica invalida o no guardada</option><?php endif; foreach ($metricOptions as $metricCode => $metricMeta): ?><option value="<?php echo reports_v2_h($metricCode); ?>" <?php echo $selectedSourceMetric === $metricCode ? 'selected' : ''; ?>><?php echo reports_v2_h($metricMeta['label']); ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="report-v2-form-grid" data-field-panel="line_item" style="display:none;">
              <label><span>Conceptos adicionales (Ctrl/Cmd para varios)</span><select name="line_item_catalog_ids[]" multiple size="8"><?php foreach ($lineItemCatalogs as $catalogRow): $optionLabel = trim((string)$catalogRow['item_name']); if ($optionLabel === '') { $optionLabel = 'Line item #' . (int)$catalogRow['id_line_item_catalog']; } $catalogId = (int)$catalogRow['id_line_item_catalog']; ?><option value="<?php echo $catalogId; ?>" <?php echo in_array($catalogId, $selectedLineItemCatalogIds, true) ? 'selected' : ''; ?>><?php echo reports_v2_h($optionLabel); ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="report-v2-form-grid" data-field-panel="calculated" style="display:none;">
              <label><span>Calculo</span><select name="report_calculation_id"><option value="0">Selecciona</option><?php $selectedReportCalculationId = $selectedField ? (int)$selectedField['id_report_calculation'] : 0; foreach ($calculations as $calculationRow): ?><option value="<?php echo (int)$calculationRow['id_report_calculation']; ?>" <?php echo $selectedReportCalculationId === (int)$calculationRow['id_report_calculation'] ? 'selected' : ''; ?>><?php echo reports_v2_h($calculationRow['calc_name']); ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="report-v2-actions">
              <button type="submit"><?php echo $selectedField ? 'Guardar campo' : 'Agregar campo'; ?></button>
              <?php if ($selectedField): ?>
                <a class="report-v2-chip" href="<?php echo reports_v2_h('?view=reports&reports_tab=templates&selected_report_template_id=' . (int)$selectedTemplate['id_report_template'] . '&selected_report_calculation_id=' . $selectedCalculationId); ?>">Nuevo</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($activeTab === 'calculations'): ?>
    <section class="report-v2-card report-v2-stack">
      <div>
        <h2>Calculos</h2>
        <p class="report-v2-muted">Usa operadores como <code>+</code>, <code>-</code>, <code>*</code>, <code>/</code> y parentesis. Ejemplo: <code>reservation_adults + reservation_children</code>.</p>
        <p class="report-v2-muted">Los botones insertan el codigo exacto de la variable en la expresion.</p>
      </div>
      <div class="report-v2-list">
        <?php if (empty($calculations)): ?>
          <div class="report-v2-list-item"><p class="report-v2-muted">Todavia no hay calculos.</p></div>
        <?php else: ?>
          <?php foreach ($calculations as $calculationRow): $calcId = isset($calculationRow['id_report_calculation']) ? (int)$calculationRow['id_report_calculation'] : 0; ?>
            <div class="report-v2-list-item <?php echo $calcId === $selectedCalculationId ? 'is-selected' : ''; ?>">
              <h4><?php echo reports_v2_h($calculationRow['calc_name']); ?></h4>
              <div class="report-v2-pill-list"><span class="report-v2-chip"><?php echo reports_v2_h($calculationRow['calc_code']); ?></span><span class="report-v2-chip"><?php echo reports_v2_h($calculationRow['format_hint']); ?></span><span class="report-v2-chip"><?php echo !empty($calculationRow['is_active']) ? 'Activa' : 'Inactiva'; ?></span></div>
              <p><code><?php echo reports_v2_h($calculationRow['expression_text']); ?></code></p>
              <form method="get"><input type="hidden" name="view" value="reports"><input type="hidden" name="reports_tab" value="calculations"><input type="hidden" name="selected_report_template_id" value="<?php echo $selectedTemplateId; ?>"><input type="hidden" name="selected_report_calculation_id" value="<?php echo $calcId; ?>"><button type="submit">Editar</button></form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="report-v2-card report-v2-stack">
        <h3><?php echo $selectedCalculation ? 'Editar calculo' : 'Nuevo calculo'; ?></h3>
        <form method="post" class="report-v2-stack">
          <input type="hidden" name="reports_action" value="save_calculation">
          <input type="hidden" name="reports_tab" value="calculations">
          <input type="hidden" name="selected_report_template_id" value="<?php echo $selectedTemplateId; ?>">
          <input type="hidden" name="calculation_id" value="<?php echo $selectedCalculation ? (int)$selectedCalculation['id_report_calculation'] : 0; ?>">
          <div class="report-v2-form-grid">
            <label><span>Nombre</span><input type="text" name="calculation_name" value="<?php echo reports_v2_h($selectedCalculation ? $selectedCalculation['calc_name'] : ''); ?>" required></label>
            <label><span>Estatus</span><select name="calculation_is_active"><option value="1" <?php echo (!$selectedCalculation || !empty($selectedCalculation['is_active'])) ? 'selected' : ''; ?>>Activa</option><option value="0" <?php echo ($selectedCalculation && empty($selectedCalculation['is_active'])) ? 'selected' : ''; ?>>Inactiva</option></select></label>
          </div>
          <div class="report-v2-form-grid report-v2-form-grid--3">
            <label><span>Formato</span><select name="calculation_format_hint"><option value="number" <?php echo ($selectedCalculation && $selectedCalculation['format_hint'] === 'number') ? 'selected' : ''; ?>>Numero</option><option value="integer" <?php echo ($selectedCalculation && $selectedCalculation['format_hint'] === 'integer') ? 'selected' : ''; ?>>Entero</option><option value="currency" <?php echo ($selectedCalculation && $selectedCalculation['format_hint'] === 'currency') ? 'selected' : ''; ?>>Moneda</option></select></label>
            <label><span>Decimales</span><input type="number" name="calculation_decimal_places" min="0" max="6" value="<?php echo reports_v2_h($selectedCalculation ? $selectedCalculation['decimal_places'] : 2); ?>"></label>
          </div>
          <label><span>Descripcion</span><textarea name="calculation_description" rows="2"><?php echo reports_v2_h($selectedCalculation ? $selectedCalculation['description'] : ''); ?></textarea></label>
          <label><span>Expresion</span><textarea name="calculation_expression" id="reports-v2-calc-expression" rows="5" required><?php echo reports_v2_h($selectedCalculation ? $selectedCalculation['expression_text'] : ''); ?></textarea></label>
          <div class="report-v2-card" style="padding:12px 14px;">
            <div class="report-v2-muted" style="margin-bottom:8px;">Ejemplos utiles</div>
            <div class="report-v2-pill-list">
              <button type="button" class="report-v2-insert-variable" data-variable="reservation_adults + reservation_children">Adultos + ninos</button>
              <button type="button" class="report-v2-insert-variable" data-variable="reservation_total_price_cents - reservation_balance_due_cents">Pagado</button>
              <button type="button" class="report-v2-insert-variable" data-variable="reservation_balance_due_cents">Saldo pendiente</button>
            </div>
          </div>
          <div>
            <div class="report-v2-muted">Variables disponibles</div>
            <div class="report-v2-code-list">
              <?php
                $variableGroups = array();
                foreach ($displayVariableCatalog as $variableCode => $variableMeta) {
                    $groupName = isset($variableMeta['group']) ? $variableMeta['group'] : 'Otros';
                    if (!isset($variableGroups[$groupName])) {
                        $variableGroups[$groupName] = array();
                    }
                    $variableGroups[$groupName][$variableCode] = $variableMeta;
                }
              ?>
              <?php foreach ($variableGroups as $groupName => $groupVariables): ?>
                <div style="margin-bottom:10px;">
                  <div class="report-v2-muted" style="margin-bottom:6px;"><?php echo reports_v2_h($groupName); ?></div>
                  <?php foreach ($groupVariables as $variableCode => $variableMeta): ?>
                    <?php
                      $variableLabel = isset($variableMeta['label']) && trim((string)$variableMeta['label']) !== ''
                        ? (string)$variableMeta['label']
                        : $variableCode;
                    ?>
                    <button
                      type="button"
                      class="report-v2-insert-variable"
                      data-variable="<?php echo reports_v2_h($variableCode); ?>"
                      title="<?php echo reports_v2_h($variableCode); ?>"
                    ><?php echo reports_v2_h($variableLabel); ?></button>
                    <span class="report-v2-muted" style="display:inline-block;margin:0 10px 6px 0;font-size:11px;"><code><?php echo reports_v2_h($variableCode); ?></code></span>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="report-v2-actions">
            <button type="submit"><?php echo $selectedCalculation ? 'Guardar calculo' : 'Crear calculo'; ?></button>
          </div>
        </form>
        <?php if ($selectedCalculation): ?>
          <div class="report-v2-actions">
            <a class="report-v2-chip" href="<?php echo reports_v2_h('?view=reports&reports_tab=calculations&selected_report_template_id=' . $selectedTemplateId); ?>">Nuevo</a>
            <form method="post" onsubmit="return confirm('Se archivara el calculo seleccionado.');"><input type="hidden" name="reports_action" value="delete_calculation"><input type="hidden" name="reports_tab" value="calculations"><input type="hidden" name="selected_report_template_id" value="<?php echo $selectedTemplateId; ?>"><input type="hidden" name="calculation_id" value="<?php echo (int)$selectedCalculation['id_report_calculation']; ?>"><button type="submit">Archivar</button></form>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
$editableInputCatalog = function_exists('pms_report_reservation_editable_field_catalog')
    ? pms_report_reservation_editable_field_catalog()
    : array();
?>

<?php if ($activeTab === 'run'): ?>
<section class="report-v2-card report-v2-stack">
  <div>
    <h2>Resultado</h2>
    <p class="report-v2-muted">
      <?php if ($runTemplate && isset($runTemplate['row_source']) && (string)$runTemplate['row_source'] === 'line_item'): ?>
        <?php if (isset($runTemplate['line_item_type_scope']) && (string)$runTemplate['line_item_type_scope'] === 'all'): ?>
          Cada fila representa un line item sin distincion de tipo, manteniendo visibles los datos de la reservacion a la que pertenece.
        <?php else: ?>
          Cada fila representa un line item de tipo <?php echo reports_v2_h(isset($lineItemRowTypeOptions[$runTemplate['line_item_type_scope']]) ? $lineItemRowTypeOptions[$runTemplate['line_item_type_scope']] : (string)$runTemplate['line_item_type_scope']); ?>, manteniendo visibles los datos de la reservacion a la que pertenece.
        <?php endif; ?>
      <?php else: ?>
        Cada fila representa una reservacion. Se ejecuta contra reservacion, huesped, propiedad, habitacion, categoria, tarifa y agregados de line items.
      <?php endif; ?>
    </p>
  </div>
  <?php if (!$canRun): ?>
    <p class="error">Tu usuario no tiene permiso <code>reports.run</code>.</p>
  <?php elseif (!$selectedTemplate): ?>
    <p class="report-v2-muted">Selecciona o crea una plantilla para ejecutar el reporte.</p>
  <?php else: ?>
    <?php if (!$hasEditableReservationFields): ?>
      <p class="report-v2-muted">La columna de acciones solo aparece cuando la plantilla tiene al menos un campo de tipo Reservacion marcado como editable.</p>
    <?php endif; ?>
    <form method="get" class="report-v2-stack">
      <input type="hidden" name="view" value="reports">
      <input type="hidden" name="reports_tab" value="run">
      <input type="hidden" name="selected_report_calculation_id" value="<?php echo $selectedCalculationId; ?>">
      <?php if ($reportGridSubdivideFieldId > 0): ?><input type="hidden" name="report_grid_subdivide_field_id" value="<?php echo $reportGridSubdivideFieldId; ?>"><?php endif; ?>
      <?php if (!empty($reportGridSubdivideFieldIdLevel2)): ?><input type="hidden" name="report_grid_subdivide_field_id_level_2" value="<?php echo (int)$reportGridSubdivideFieldIdLevel2; ?>"><?php endif; ?>
      <?php if (!empty($reportGridSubdivideFieldIdLevel3)): ?><input type="hidden" name="report_grid_subdivide_field_id_level_3" value="<?php echo (int)$reportGridSubdivideFieldIdLevel3; ?>"><?php endif; ?>
      <?php if ($reportGridSubdivideShowTotalsLevel1 !== null): ?><input type="hidden" name="report_grid_subdivide_show_totals_level_1" value="<?php echo $reportGridSubdivideShowTotalsLevel1 ? '1' : '0'; ?>"><?php endif; ?>
      <?php if ($reportGridSubdivideShowTotalsLevel2 !== null): ?><input type="hidden" name="report_grid_subdivide_show_totals_level_2" value="<?php echo $reportGridSubdivideShowTotalsLevel2 ? '1' : '0'; ?>"><?php endif; ?>
      <div class="report-v2-form-grid">
        <label><span>Propiedad</span><select name="report_property_code"><option value="">Todas</option><?php foreach ($properties as $propertyRow): $propertyCode = isset($propertyRow['property_code']) ? (string)$propertyRow['property_code'] : ''; $propertyName = isset($propertyRow['property_name']) ? (string)$propertyRow['property_name'] : $propertyCode; ?><option value="<?php echo reports_v2_h($propertyCode); ?>" <?php echo strtoupper($runFilters['property_code']) === strtoupper($propertyCode) ? 'selected' : ''; ?>><?php echo reports_v2_h($propertyName); ?></option><?php endforeach; ?></select></label>
        <label><span>Estatus</span><select name="report_status"><option value="">Todos</option><option value="activas" <?php echo $runFilters['status'] === 'activas' ? 'selected' : ''; ?>>Activas</option><?php foreach (array('apartado', 'confirmado', 'en casa', 'salida', 'no-show', 'cancelada') as $statusOption): ?><option value="<?php echo reports_v2_h($statusOption); ?>" <?php echo $runFilters['status'] === $statusOption ? 'selected' : ''; ?>><?php echo reports_v2_h($statusOption); ?></option><?php endforeach; ?></select></label>
        <label><span>Plantilla</span><select name="selected_report_template_id"><?php foreach ($templates as $templateRow): ?><option value="<?php echo (int)$templateRow['id_report_template']; ?>" <?php echo (int)$templateRow['id_report_template'] === $selectedTemplateId ? 'selected' : ''; ?>><?php echo reports_v2_h($templateRow['report_name']); ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="report-v2-actions"><button type="submit">Ejecutar</button><span class="report-v2-chip">Maximo 500 <?php echo ($runTemplate && isset($runTemplate['row_source']) && (string)$runTemplate['row_source'] === 'line_item') ? 'line items' : 'reservaciones'; ?> por corrida</span><?php if ($runTemplate): ?><span class="report-v2-chip"><?php echo reports_v2_h($runTemplate['report_name']); ?></span><span class="report-v2-chip"><?php echo reports_v2_h(isset($rowSourceOptions[$runTemplate['row_source']]) ? $rowSourceOptions[$runTemplate['row_source']] : 'Reservacion'); ?></span><?php endif; ?></div>
    </form>

    <div class="report-v2-grid-filter-bar report-v2-stack">
      <?php if ($hasTemplateSubdivideColumn): ?>
      <form method="get" class="report-v2-grid-division-form">
        <input type="hidden" name="view" value="reports">
        <input type="hidden" name="reports_tab" value="run">
        <input type="hidden" name="selected_report_template_id" value="<?php echo $selectedTemplateId; ?>">
        <input type="hidden" name="selected_report_calculation_id" value="<?php echo $selectedCalculationId; ?>">
        <input type="hidden" name="report_property_code" value="<?php echo reports_v2_h($runFilters['property_code']); ?>">
        <input type="hidden" name="report_status" value="<?php echo reports_v2_h($runFilters['status']); ?>">
        <input type="hidden" name="report_date_type" value="<?php echo reports_v2_h($runFilters['date_type']); ?>">
        <input type="hidden" name="report_date_from" value="<?php echo reports_v2_h($runFilters['date_from']); ?>">
        <input type="hidden" name="report_date_to" value="<?php echo reports_v2_h($runFilters['date_to']); ?>">
        <div class="report-v2-grid-division-block">
          <label>
            <span>Primera division</span>
            <select name="report_grid_subdivide_field_id" class="report-v2-auto-submit-division-filter" data-subdivide-field-select="1">
              <option value="0">Sin division</option>
              <?php foreach ($templateFields as $templateFieldOption): $templateFieldOptionId = isset($templateFieldOption['id_report_template_field']) ? (int)$templateFieldOption['id_report_template_field'] : 0; ?>
                <option value="<?php echo $templateFieldOptionId; ?>" <?php echo (int)$reportSubdivideByFieldId === $templateFieldOptionId ? 'selected' : ''; ?>>
                  <?php echo reports_v2_h(isset($templateFieldOption['display_name_resolved']) ? $templateFieldOption['display_name_resolved'] : $templateFieldOption['display_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="report-v2-grid-division-toggle">
            <input type="hidden" name="report_grid_subdivide_show_totals_level_1" value="0">
            <input type="checkbox" name="report_grid_subdivide_show_totals_level_1" value="1" class="report-v2-auto-submit-division-filter" data-subdivide-totals-toggle="1" <?php echo !empty($reportRuntimeSubdivideShowTotalsLevel1) ? 'checked' : ''; ?>>
            <span>Totales primera division</span>
          </label>
        </div>
        <div class="report-v2-grid-division-block">
          <label>
            <span>Segunda division</span>
            <select name="report_grid_subdivide_field_id_level_2" class="report-v2-auto-submit-division-filter" data-subdivide-field-select="2">
              <option value="0">Sin division</option>
              <?php foreach ($templateFields as $templateFieldOption): $templateFieldOptionId = isset($templateFieldOption['id_report_template_field']) ? (int)$templateFieldOption['id_report_template_field'] : 0; ?>
                <option value="<?php echo $templateFieldOptionId; ?>" <?php echo (int)$reportSubdivideByFieldIdLevel2 === $templateFieldOptionId ? 'selected' : ''; ?>>
                  <?php echo reports_v2_h(isset($templateFieldOption['display_name_resolved']) ? $templateFieldOption['display_name_resolved'] : $templateFieldOption['display_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="report-v2-grid-division-toggle">
            <input type="hidden" name="report_grid_subdivide_show_totals_level_2" value="0">
            <input type="checkbox" name="report_grid_subdivide_show_totals_level_2" value="1" class="report-v2-auto-submit-division-filter" data-subdivide-totals-toggle="2" <?php echo !empty($reportRuntimeSubdivideShowTotalsLevel2) ? 'checked' : ''; ?>>
            <span>Totales segunda division</span>
          </label>
        </div>
      </form>
      <?php endif; ?>
      <div class="report-v2-grid-toolbar">
        <form method="get" class="report-v2-grid-toolbar-dates" id="report-v2-active-date-form">
          <input type="hidden" name="view" value="reports">
          <input type="hidden" name="reports_tab" value="run">
          <input type="hidden" name="selected_report_template_id" value="<?php echo $selectedTemplateId; ?>">
          <input type="hidden" name="selected_report_calculation_id" value="<?php echo $selectedCalculationId; ?>">
          <input type="hidden" name="report_property_code" value="<?php echo reports_v2_h($runFilters['property_code']); ?>">
          <input type="hidden" name="report_status" value="<?php echo reports_v2_h($runFilters['status']); ?>">
          <?php if ($reportGridSubdivideFieldId > 0): ?><input type="hidden" name="report_grid_subdivide_field_id" value="<?php echo $reportGridSubdivideFieldId; ?>"><?php endif; ?>
          <?php if (!empty($reportGridSubdivideFieldIdLevel2)): ?><input type="hidden" name="report_grid_subdivide_field_id_level_2" value="<?php echo (int)$reportGridSubdivideFieldIdLevel2; ?>"><?php endif; ?>
          <?php if (!empty($reportGridSubdivideFieldIdLevel3)): ?><input type="hidden" name="report_grid_subdivide_field_id_level_3" value="<?php echo (int)$reportGridSubdivideFieldIdLevel3; ?>"><?php endif; ?>
          <?php if ($reportGridSubdivideShowTotalsLevel1 !== null): ?><input type="hidden" name="report_grid_subdivide_show_totals_level_1" value="<?php echo $reportGridSubdivideShowTotalsLevel1 ? '1' : '0'; ?>"><?php endif; ?>
          <?php if ($reportGridSubdivideShowTotalsLevel2 !== null): ?><input type="hidden" name="report_grid_subdivide_show_totals_level_2" value="<?php echo $reportGridSubdivideShowTotalsLevel2 ? '1' : '0'; ?>"><?php endif; ?>
          <span class="report-v2-toolbar-label">Fechas activas</span>
          <select name="report_date_type" class="report-v2-auto-submit-date-filter"><?php foreach ($dateTypeOptions as $dateTypeCode => $dateTypeLabel): ?><option value="<?php echo reports_v2_h($dateTypeCode); ?>" <?php echo $runFilters['date_type'] === $dateTypeCode ? 'selected' : ''; ?>><?php echo reports_v2_h($dateTypeLabel); ?></option><?php endforeach; ?></select>
          <span class="report-v2-toolbar-connector">entre</span>
          <div class="pms-date-range-picker report-v2-inline-range" data-pms-date-range-picker data-submit-form="1">
            <button type="button" class="pms-date-range-picker-trigger" data-pms-date-range-trigger>
              <span data-pms-date-range-display-start><?php echo reports_v2_h($runFilters['date_from']); ?></span>
              <span class="pms-date-range-picker-separator">y</span>
              <span data-pms-date-range-display-end><?php echo reports_v2_h($runFilters['date_to']); ?></span>
            </button>
            <input type="hidden" name="report_date_from" value="<?php echo reports_v2_h($runFilters['date_from']); ?>" class="report-v2-auto-submit-date-filter" data-pms-date-range-start>
            <input type="hidden" name="report_date_to" value="<?php echo reports_v2_h($runFilters['date_to']); ?>" class="report-v2-auto-submit-date-filter" data-pms-date-range-end>
          </div>
        </form>
        <label class="report-v2-grid-toolbar-search">
          <span class="report-v2-toolbar-label">Buscar en resultado</span>
          <input type="text" id="report-v2-grid-search" placeholder="Busca en todo el texto visible del reporte">
        </label>
        <div class="report-v2-grid-toolbar-actions">
          <button type="button" id="report-v2-grid-clear-filters">Reiniciar filtros</button>
          <span class="report-v2-chip" id="report-v2-grid-visible-count">0 visibles</span>
        </div>
      </div>
    </div>

    <div class="report-v2-table-wrap">
      <?php if (empty($templateFields)): ?>
        <table class="report-v2-table"><tbody><tr><td class="report-v2-muted">La plantilla seleccionada todavia no tiene campos.</td></tr></tbody></table>
      <?php elseif (empty($reportRows)): ?>
        <table class="report-v2-table"><tbody><tr><td colspan="<?php echo count($templateFields); ?>" class="report-v2-muted">Sin resultados para los filtros actuales.</td></tr></tbody></table>
      <?php elseif (!empty($reportSubdivisions)): ?>
        <?php
          $groupedRenderContext = array(
              'selectedTemplateId' => $selectedTemplateId,
              'selectedCalculationId' => $selectedCalculationId,
              'runFilters' => $runFilters,
              'reportGridSubdivideFieldId' => $reportGridSubdivideFieldId,
              'reportGridSubdivideFieldIdLevel2' => isset($reportGridSubdivideFieldIdLevel2) ? (int)$reportGridSubdivideFieldIdLevel2 : 0,
              'reportGridSubdivideFieldIdLevel3' => isset($reportGridSubdivideFieldIdLevel3) ? (int)$reportGridSubdivideFieldIdLevel3 : 0,
              'reportGridSubdivideShowTotalsLevel1' => isset($reportGridSubdivideShowTotalsLevel1) ? $reportGridSubdivideShowTotalsLevel1 : null,
              'reportGridSubdivideShowTotalsLevel2' => isset($reportGridSubdivideShowTotalsLevel2) ? $reportGridSubdivideShowTotalsLevel2 : null,
              'hasEditableReservationFields' => $hasEditableReservationFields,
              'editReservationId' => $editReservationId,
              'rowEditDraftValues' => $rowEditDraftValues,
              'editableInputCatalog' => $editableInputCatalog,
              'reportHasCalculatedTotals' => $reportHasCalculatedTotals,
          );
        ?>
        <div class="report-v2-stack">
          <?php foreach ($reportSubdivisions as $reportSubdivision): ?>
            <div class="report-v2-stack report-v2-result-subdivision">
              <h3 class="report-v2-subreport-title"><?php echo reports_v2_h($reportSubdivision['label']); ?></h3>
              <?php $renderedEditableReservationIds = array(); ?>
              <table class="report-v2-table report-v2-result-table" data-result-scope="subdivision">
                <thead><tr><?php reports_v2_render_result_table_header_cells($templateFields, $hasEditableReservationFields); ?></tr></thead>
                <?php if (!empty($reportSubdivision['children'])): ?>
                  <?php reports_v2_render_nested_group_tbodies($reportSubdivision['children'], $templateFields, $groupedRenderContext, $renderedEditableReservationIds); ?>
                <?php else: ?>
                  <?php reports_v2_render_grouped_rows_tbody($reportSubdivision['rows'], $templateFields, $groupedRenderContext, $renderedEditableReservationIds); ?>
                <?php endif; ?>
                <?php if ($reportHasCalculatedTotals && !empty($reportSubdivision['show_totals'])): ?>
                  <tfoot><tr><?php foreach ($templateFields as $fieldRow): $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0; $totalCell = isset($reportSubdivision['totals'][$fieldId]) ? $reportSubdivision['totals'][$fieldId] : array('display' => ''); ?><td data-total-field-id="<?php echo $fieldId; ?>"><?php echo reports_v2_h(isset($totalCell['display']) ? $totalCell['display'] : ''); ?></td><?php endforeach; ?><?php if ($hasEditableReservationFields): ?><td></td><?php endif; ?></tr></tfoot>
                <?php endif; ?>
              </table>
            </div>
          <?php endforeach; ?>
          <?php if ($reportHasCalculatedTotals): ?>
            <div class="report-v2-stack">
              <h3 class="report-v2-subreport-title">Totales generales</h3>
              <table class="report-v2-table report-v2-result-totals-table" data-result-scope="global-totals">
                <thead><tr><?php foreach ($templateFields as $fieldRow): ?><th><?php echo reports_v2_h(isset($fieldRow['display_name_resolved']) ? $fieldRow['display_name_resolved'] : $fieldRow['display_name']); ?></th><?php endforeach; ?><?php if ($hasEditableReservationFields): ?><th></th><?php endif; ?></tr></thead>
                <tfoot><tr><?php foreach ($templateFields as $fieldRow): $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0; $totalCell = isset($reportDisplayTotals[$fieldId]) ? $reportDisplayTotals[$fieldId] : array('display' => ''); ?><td data-total-field-id="<?php echo $fieldId; ?>"><?php echo reports_v2_h(isset($totalCell['display']) ? $totalCell['display'] : ''); ?></td><?php endforeach; ?><?php if ($hasEditableReservationFields): ?><td></td><?php endif; ?></tr></tfoot>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php $renderedEditableReservationIds = array(); ?>
        <table class="report-v2-table report-v2-result-table" data-result-scope="main">
          <thead><tr><?php foreach ($templateFields as $fieldRow): $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0; $fieldDisplayName = isset($fieldRow['display_name_resolved']) ? $fieldRow['display_name_resolved'] : $fieldRow['display_name']; ?><th data-field-id="<?php echo $fieldId; ?>" data-calculate-total="<?php echo !empty($fieldRow['calculate_total']) ? '1' : '0'; ?>" data-format-hint="<?php echo reports_v2_h(isset($fieldRow['format_hint']) ? (string)$fieldRow['format_hint'] : 'auto'); ?>"><div class="report-v2-th-inner"><div class="report-v2-th-main"><button type="button" class="report-v2-sort-button" data-sort-field-id="<?php echo $fieldId; ?>" aria-label="Ordenar columna <?php echo reports_v2_h($fieldDisplayName); ?>"><svg viewBox="0 0 16 16" aria-hidden="true"><path class="report-v2-sort-up" fill="currentColor" d="M8 3l3 4H5z"/><path class="report-v2-sort-down" fill="currentColor" d="M8 13l-3-4h6z"/></svg></button><span class="report-v2-th-label"><?php echo reports_v2_h($fieldDisplayName); ?></span></div><div class="report-v2-th-actions"><button type="button" class="report-v2-filter-button" data-filter-field-id="<?php echo $fieldId; ?>" aria-label="Filtrar columna <?php echo reports_v2_h($fieldDisplayName); ?>"><svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2 3h12l-4.8 5.2v4.1l-2.6 1.7V8.2z"/></svg></button></div></div></th><?php endforeach; ?><?php if ($hasEditableReservationFields): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($reportRows as $reportRow): ?>
              <?php
                $rowReservationId = isset($reportRow['reservation_id']) ? (int)$reportRow['reservation_id'] : 0;
                $rowCanEdit = $hasEditableReservationFields && $rowReservationId > 0 && !in_array($rowReservationId, $renderedEditableReservationIds, true);
                if ($rowCanEdit) {
                    $renderedEditableReservationIds[] = $rowReservationId;
                }
                $rowIsEditing = $rowCanEdit && $editReservationId > 0 && $editReservationId === $rowReservationId;
                $rowFormId = 'report-v2-edit-row-' . $rowReservationId;
                $rowBase = isset($reportRow['base']) && is_array($reportRow['base']) ? $reportRow['base'] : array();
                $rowCreatedDate = isset($rowBase['base_line_item_created_date']) && trim((string)$rowBase['base_line_item_created_date']) !== ''
                    ? (string)$rowBase['base_line_item_created_date']
                    : (isset($rowBase['reservation_created_date']) ? (string)$rowBase['reservation_created_date'] : '');
                $rowServiceDate = isset($rowBase['base_line_item_service_date']) && trim((string)$rowBase['base_line_item_service_date']) !== ''
                    ? (string)$rowBase['base_line_item_service_date']
                    : (isset($rowBase['reservation_service_date']) ? (string)$rowBase['reservation_service_date'] : '');
                $rowCheckInDate = isset($rowBase['reservation_check_in_date']) ? (string)$rowBase['reservation_check_in_date'] : '';
                $rowCheckOutDate = isset($rowBase['reservation_check_out_date']) ? (string)$rowBase['reservation_check_out_date'] : '';
              ?>
              <tr class="report-v2-result-row <?php echo $rowIsEditing ? 'is-editing' : ''; ?>" data-reservation-id="<?php echo (int)$rowReservationId; ?>" data-inline-edit-row="<?php echo $rowCanEdit ? '1' : '0'; ?>" data-row-created-date="<?php echo reports_v2_h($rowCreatedDate); ?>" data-row-service-date="<?php echo reports_v2_h($rowServiceDate); ?>" data-row-check-in-date="<?php echo reports_v2_h($rowCheckInDate); ?>" data-row-check-out-date="<?php echo reports_v2_h($rowCheckOutDate); ?>">
                <?php foreach ($templateFields as $fieldRow): ?>
                  <?php
                    $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0;
                    $cell = isset($reportRow['cells'][$fieldId]) ? $reportRow['cells'][$fieldId] : array('display' => '');
                    $fieldCode = isset($fieldRow['reservation_field_code']) ? (string)$fieldRow['reservation_field_code'] : '';
                    $canEditCell = $rowCanEdit
                        && (isset($fieldRow['field_type']) ? (string)$fieldRow['field_type'] : '') === 'reservation'
                        && !empty($fieldRow['is_editable'])
                        && isset($editableInputCatalog[$fieldCode]);
                    $cellMeta = isset($cell['meta']) && is_array($cell['meta']) ? $cell['meta'] : array('data_type' => 'text', 'numeric' => false);
                    $cellDisplay = isset($cell['display']) ? (string)$cell['display'] : '';
                    $cellRaw = isset($cell['raw']) ? $cell['raw'] : '';
                    $cellFilterValue = trim($cellDisplay) !== '' ? $cellDisplay : '__EMPTY__';
                  ?>
                  <td data-field-id="<?php echo $fieldId; ?>" data-cell-display="<?php echo reports_v2_h($cellDisplay); ?>" data-cell-filter-value="<?php echo reports_v2_h($cellFilterValue); ?>" data-cell-raw="<?php echo reports_v2_h(is_scalar($cellRaw) || $cellRaw === null ? (string)$cellRaw : ''); ?>" data-cell-type="<?php echo reports_v2_h(isset($cellMeta['data_type']) ? (string)$cellMeta['data_type'] : 'text'); ?>" title="<?php echo reports_v2_h(isset($cell['error']) ? $cell['error'] : ''); ?>">
                    <?php if ($canEditCell): ?>
                      <?php
                        $inputMeta = $editableInputCatalog[$fieldCode];
                        $inputType = isset($inputMeta['input_type']) ? (string)$inputMeta['input_type'] : 'text';
                        $inputValue = array_key_exists($fieldId, $rowEditDraftValues)
                            ? $rowEditDraftValues[$fieldId]
                            : (isset($cell['raw']) ? $cell['raw'] : '');
                      ?>
                      <span class="report-v2-inline-cell-display"><?php echo reports_v2_h(isset($cell['display']) ? $cell['display'] : ''); ?></span>
                      <input
                        class="report-v2-inline-cell-input"
                        type="<?php echo reports_v2_h($inputType); ?>"
                        name="row_edit_values[<?php echo $fieldId; ?>]"
                        value="<?php echo reports_v2_h($inputValue); ?>"
                        data-original-value="<?php echo reports_v2_h($inputValue); ?>"
                        form="<?php echo reports_v2_h($rowFormId); ?>"
                      >
                    <?php else: ?>
                      <?php echo reports_v2_h(isset($cell['display']) ? $cell['display'] : ''); ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php if ($hasEditableReservationFields): ?>
                  <td class="report-v2-actions-cell">
                    <?php if ($rowCanEdit): ?>
                      <div class="report-v2-inline-actions">
                        <button type="button" class="report-v2-inline-edit-button">Editar</button>
                        <form method="post" class="report-v2-inline-form report-v2-inline-save-form" id="<?php echo reports_v2_h($rowFormId); ?>">
                          <input type="hidden" name="reports_action" value="save_report_row">
                          <input type="hidden" name="inline_ajax" value="1">
                          <input type="hidden" name="reports_tab" value="run">
                          <input type="hidden" name="selected_report_template_id" value="<?php echo $selectedTemplateId; ?>">
                          <input type="hidden" name="selected_report_calculation_id" value="<?php echo $selectedCalculationId; ?>">
                          <input type="hidden" name="report_property_code" value="<?php echo reports_v2_h($runFilters['property_code']); ?>">
                          <input type="hidden" name="report_status" value="<?php echo reports_v2_h($runFilters['status']); ?>">
                          <input type="hidden" name="report_date_type" value="<?php echo reports_v2_h($runFilters['date_type']); ?>">
                          <input type="hidden" name="report_date_from" value="<?php echo reports_v2_h($runFilters['date_from']); ?>">
                          <input type="hidden" name="report_date_to" value="<?php echo reports_v2_h($runFilters['date_to']); ?>">
                          <?php if ($reportGridSubdivideFieldId > 0): ?><input type="hidden" name="report_grid_subdivide_field_id" value="<?php echo $reportGridSubdivideFieldId; ?>"><?php endif; ?>
                          <?php if (!empty($reportGridSubdivideFieldIdLevel2)): ?><input type="hidden" name="report_grid_subdivide_field_id_level_2" value="<?php echo (int)$reportGridSubdivideFieldIdLevel2; ?>"><?php endif; ?>
                          <?php if (!empty($reportGridSubdivideFieldIdLevel3)): ?><input type="hidden" name="report_grid_subdivide_field_id_level_3" value="<?php echo (int)$reportGridSubdivideFieldIdLevel3; ?>"><?php endif; ?>
                          <?php if ($reportGridSubdivideShowTotalsLevel1 !== null): ?><input type="hidden" name="report_grid_subdivide_show_totals_level_1" value="<?php echo $reportGridSubdivideShowTotalsLevel1 ? '1' : '0'; ?>"><?php endif; ?>
                          <?php if ($reportGridSubdivideShowTotalsLevel2 !== null): ?><input type="hidden" name="report_grid_subdivide_show_totals_level_2" value="<?php echo $reportGridSubdivideShowTotalsLevel2 ? '1' : '0'; ?>"><?php endif; ?>
                          <input type="hidden" name="reservation_id" value="<?php echo $rowReservationId; ?>">
                          <button type="submit">Guardar</button>
                        </form>
                        <button type="button" class="report-v2-inline-cancel-button" aria-label="Cancelar cambios" title="Cancelar cambios">X</button>
                      </div>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <?php if ($reportHasCalculatedTotals): ?>
            <tfoot><tr><?php foreach ($templateFields as $fieldRow): $fieldId = isset($fieldRow['id_report_template_field']) ? (int)$fieldRow['id_report_template_field'] : 0; $totalCell = isset($reportDisplayTotals[$fieldId]) ? $reportDisplayTotals[$fieldId] : array('display' => ''); ?><td data-total-field-id="<?php echo $fieldId; ?>"><?php echo reports_v2_h(isset($totalCell['display']) ? $totalCell['display'] : ''); ?></td><?php endforeach; ?><?php if ($hasEditableReservationFields): ?><td></td><?php endif; ?></tr></tfoot>
          <?php endif; ?>
        </table>
      <?php endif; ?>
    </div>
    <div class="report-v2-lightbox-backdrop" id="report-v2-column-filter-modal" aria-hidden="true">
      <div class="report-v2-lightbox" role="dialog" aria-modal="true" aria-labelledby="report-v2-column-filter-title">
        <div class="report-v2-lightbox-header">
          <strong id="report-v2-column-filter-title">Filtrar columna</strong>
        </div>
        <div class="report-v2-lightbox-body">
          <div class="report-v2-actions">
            <button type="button" id="report-v2-column-filter-check-all">Marcar todos</button>
            <button type="button" id="report-v2-column-filter-uncheck-all">Desmarcar todos</button>
          </div>
          <div class="report-v2-filter-checklist" id="report-v2-column-filter-options"></div>
        </div>
        <div class="report-v2-lightbox-footer">
          <span class="report-v2-muted" id="report-v2-column-filter-summary">0 seleccionados</span>
          <div class="report-v2-actions">
            <button type="button" id="report-v2-column-filter-close">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
    <div class="report-v2-lightbox-backdrop" id="report-v2-column-advanced-filter-modal" aria-hidden="true">
      <div class="report-v2-lightbox" role="dialog" aria-modal="true" aria-labelledby="report-v2-column-advanced-filter-title">
        <div class="report-v2-lightbox-header">
          <strong id="report-v2-column-advanced-filter-title">Filtro especializado</strong>
        </div>
        <div class="report-v2-lightbox-body">
          <div class="report-v2-stack" id="report-v2-column-advanced-filter-form"></div>
        </div>
        <div class="report-v2-lightbox-footer">
          <span class="report-v2-muted" id="report-v2-column-advanced-filter-summary">Sin filtro especializado</span>
          <div class="report-v2-actions">
            <button type="button" id="report-v2-column-advanced-filter-clear">Quitar</button>
            <button type="button" id="report-v2-column-advanced-filter-close">Cerrar</button>
            <button type="button" id="report-v2-column-advanced-filter-apply">Aplicar</button>
          </div>
        </div>
      </div>
    </div>
    <div class="report-v2-context-menu" id="report-v2-column-context-menu" aria-hidden="true">
      <div class="report-v2-context-menu-group" id="report-v2-column-context-subdivide-group">
        <button type="button" class="report-v2-context-menu-button" id="report-v2-column-context-subdivide">
          <span class="report-v2-context-menu-button-label">
            <strong>Dividir reporte por columna</strong>
            <span class="report-v2-context-menu-button-meta">Elige el nivel de subdivision</span>
          </span>
          <span class="report-v2-context-menu-chevron">&rsaquo;</span>
        </button>
        <div class="report-v2-context-submenu" id="report-v2-column-context-subdivide-submenu">
          <button type="button" class="report-v2-context-menu-button" data-column-context-subdivide-level="1"></button>
          <button type="button" class="report-v2-context-menu-button" data-column-context-subdivide-level="2"></button>
        </div>
      </div>
      <button type="button" class="report-v2-context-menu-button" id="report-v2-column-context-advanced-filter">
        <span class="report-v2-context-menu-button-label">
          <strong>Filtro especializado</strong>
          <span class="report-v2-context-menu-button-meta">Crea una condicion puntual para esta columna</span>
        </span>
      </button>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>
