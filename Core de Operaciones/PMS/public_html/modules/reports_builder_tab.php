<?php
$fieldOptions = array();
foreach ($builderFieldRows as $row) {
    $k = isset($row['field_key']) ? (string)$row['field_key'] : '';
    if ($k === '') {
        continue;
    }
    $fieldOptions[$k] = isset($row['field_label']) && (string)$row['field_label'] !== '' ? (string)$row['field_label'] : $k;
}
$resolveEntity = function ($fieldKey) {
    $key = strtolower(trim((string)$fieldKey));
    if ($key === '') {
        return 'reservacion';
    }
    if (
        strpos($key, 'guest_') === 0
        || in_array($key, array('id_guest','guest_id'), true)
    ) {
        return 'huesped';
    }
    if (
        strpos($key, 'line_item_') === 0
        || in_array($key, array(
            'id_line_item',
            'item_type',
            'line_item_status',
            'service_date',
            'quantity',
            'unit_price_cents',
            'discount_amount_cents',
            'amount_cents',
            'paid_cents',
            'currency',
            'id_folio',
            'catalog_id',
            'catalog_name',
            'subcategory_name',
            'category_name'
        ), true)
    ) {
        return 'line_item';
    }
    if (
        strpos($key, 'property_') === 0
        || strpos($key, 'room_') === 0
        || strpos($key, 'category_') === 0
        || in_array($key, array('id_property','id_room','id_category','room_id','category_id'), true)
    ) {
        return 'hospedaje';
    }
    return 'reservacion';
};
$fieldOptionMeta = array();
foreach ($fieldOptions as $k => $v) {
    $fieldOptionMeta[$k] = array(
        'label' => $v,
        'entity' => $resolveEntity($k)
    );
}
$catalogOptionsMap = array();
foreach ($builderCatalogRows as $row) {
    $cid = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
    if ($cid <= 0) {
        continue;
    }
    $catalogOptionsMap[$cid] = isset($row['item_name']) ? (string)$row['item_name'] : ('Catalogo #' . $cid);
}
$filterKeys = array();
foreach ($builderColumns as $row) {
    $k = isset($row['column_key']) ? (string)$row['column_key'] : '';
    if ($k === '') {
        continue;
    }
    $filterLabel = isset($row['display_name']) && (string)$row['display_name'] !== '' ? (string)$row['display_name'] : $k;
    $sourceFieldKey = isset($row['source_field_key']) ? (string)$row['source_field_key'] : '';
    $filterKeys[$k] = array(
        'label' => $filterLabel,
        'entity' => $sourceFieldKey !== '' ? $resolveEntity($sourceFieldKey) : $resolveEntity($k)
    );
}
foreach ($fieldOptionMeta as $k => $meta) {
    if (!isset($filterKeys[$k])) {
        $filterKeys[$k] = array(
            'label' => $meta['label'],
            'entity' => $meta['entity']
        );
    }
}
$builderSelectedIsFixed = !empty($builderSelectedReportIsFixed);
$builderAddFieldEntity = isset($_POST['builder_add_field_entity']) ? trim((string)$_POST['builder_add_field_entity']) : 'huesped';
$builderAddFilterEntity = isset($_POST['builder_add_filter_entity']) ? trim((string)$_POST['builder_add_filter_entity']) : 'huesped';
$builderCatalogMetricOptionsLocal = (isset($builderCatalogMetricOptions) && is_array($builderCatalogMetricOptions))
    ? $builderCatalogMetricOptions
    : array(
        'sum_amount' => array('label' => 'Monto total')
    );
$builderAddCatalogMetric = isset($_POST['builder_add_catalog_metric']) ? trim((string)$_POST['builder_add_catalog_metric']) : 'sum_amount';
if (!isset($builderCatalogMetricOptionsLocal[$builderAddCatalogMetric])) {
    $builderAddCatalogMetric = 'sum_amount';
}
if (!in_array($builderAddFieldEntity, array('huesped','reservacion','hospedaje','line_item','all'), true)) {
    $builderAddFieldEntity = 'huesped';
}
if (!in_array($builderAddFilterEntity, array('huesped','reservacion','hospedaje','line_item','all'), true)) {
    $builderAddFilterEntity = 'huesped';
}
?>
<section class="card">
  <h2>Reportes configurables</h2>
  <p class="muted">Administra reportes por reservacion, line item o propiedad con columnas y filtros configurables.</p>

  <?php if ($builderMessage !== ''): ?><p class="muted" style="color:#9ef1ba;"><?php echo htmlspecialchars($builderMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($builderError !== ''): ?><p class="error"><?php echo htmlspecialchars($builderError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <form method="post" class="form-inline">
    <input type="hidden" name="reports_active_tab" value="builder">
    <label>Reporte
      <select name="builder_report_id">
        <option value="0">Nuevo configurable</option>
        <?php if (!empty($builderConfigurableReports)): ?>
          <optgroup label="Configurables">
            <?php foreach ($builderConfigurableReports as $r): ?>
              <?php $rid = isset($r['id_report_config']) ? (int)$r['id_report_config'] : 0; ?>
              <option value="<?php echo $rid; ?>" <?php echo $builderState['selected_report_id'] === $rid ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(((string)$r['report_name']) . ' [' . ((string)$r['report_type']) . ']', ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
        <?php if (!empty($builderFixedReports)): ?>
          <optgroup label="Fijos (solo lectura)">
            <?php foreach ($builderFixedReports as $r): ?>
              <?php $rid = isset($r['id_report_config']) ? (int)$r['id_report_config'] : 0; ?>
              <option value="<?php echo $rid; ?>" <?php echo $builderState['selected_report_id'] === $rid ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(((string)$r['report_name']) . ' [' . ((string)$r['report_type']) . '] (fijo)', ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
      </select>
    </label>
    <label>Desde <input type="date" name="builder_run_from" value="<?php echo htmlspecialchars($builderState['date_from'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>Hasta <input type="date" name="builder_run_to" value="<?php echo htmlspecialchars($builderState['date_to'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>Limite <input type="number" min="1" max="5000" name="builder_run_limit" value="<?php echo (int)$builderState['limit']; ?>"></label>
    <button type="submit" class="btn">Cargar</button>
  </form>

  <?php if ($builderSelectedIsFixed): ?>
    <p class="muted report-fixed-hint">Reporte fijo detectado: vista solo lectura. Su configuracion vive en su modulo fijo.</p>
  <?php endif; ?>

  <?php if (!$builderSelectedIsFixed): ?>
  <form method="post" class="form-grid report-builder-box">
    <input type="hidden" name="reports_active_tab" value="builder">
    <input type="hidden" name="builder_action" value="save_report">
    <input type="hidden" name="builder_report_id" value="<?php echo (int)$builderState['selected_report_id']; ?>">
    <input type="hidden" name="builder_run_from" value="<?php echo htmlspecialchars($builderState['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="builder_run_to" value="<?php echo htmlspecialchars($builderState['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="builder_run_limit" value="<?php echo (int)$builderState['limit']; ?>">
    <input type="hidden" name="builder_property_code" value="<?php echo htmlspecialchars($builderState['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <label>Nombre <input type="text" name="builder_report_name" required value="<?php echo htmlspecialchars($builderState['report_name'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>Key <input type="text" name="builder_report_key" value="<?php echo htmlspecialchars($builderState['report_key'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>Tipo
      <select name="builder_report_type">
        <option value="reservation" <?php echo $builderState['report_type'] === 'reservation' ? 'selected' : ''; ?>>Reservacion</option>
        <option value="line_item" <?php echo $builderState['report_type'] === 'line_item' ? 'selected' : ''; ?>>Line item</option>
        <option value="property" <?php echo $builderState['report_type'] === 'property' ? 'selected' : ''; ?>>Propiedad</option>
      </select>
    </label>
    <label>Scope line item
      <select name="builder_line_item_type_scope">
        <option value="all" <?php echo $builderState['line_item_type_scope'] === 'all' ? 'selected' : ''; ?>>all</option>
        <option value="sale_item" <?php echo $builderState['line_item_type_scope'] === 'sale_item' ? 'selected' : ''; ?>>sale_item</option>
        <option value="tax_item" <?php echo $builderState['line_item_type_scope'] === 'tax_item' ? 'selected' : ''; ?>>tax_item</option>
        <option value="payment" <?php echo $builderState['line_item_type_scope'] === 'payment' ? 'selected' : ''; ?>>payment</option>
        <option value="obligation" <?php echo $builderState['line_item_type_scope'] === 'obligation' ? 'selected' : ''; ?>>obligation</option>
        <option value="income" <?php echo $builderState['line_item_type_scope'] === 'income' ? 'selected' : ''; ?>>income</option>
      </select>
    </label>
    <label class="report-builder-wide">Descripcion <input type="text" name="builder_report_description" value="<?php echo htmlspecialchars($builderState['description'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <div><button type="submit" class="btn">Guardar reporte</button></div>
  </form>
  <?php endif; ?>

  <?php if ($builderState['selected_report_id'] > 0 && !$builderSelectedIsFixed): ?>
    <form method="post" class="form-inline" style="margin-top:8px;">
      <input type="hidden" name="reports_active_tab" value="builder">
      <input type="hidden" name="builder_action" value="delete_report">
      <input type="hidden" name="builder_report_id" value="<?php echo (int)$builderState['selected_report_id']; ?>">
      <input type="hidden" name="builder_property_code" value="<?php echo htmlspecialchars($builderState['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
      <button type="submit" class="btn btn-ghost" onclick="return confirm('Eliminar reporte?');">Eliminar reporte</button>
    </form>

    <div class="report-builder-split">
      <div class="card-inner">
        <h3>Agregar columna base</h3>
        <p class="muted report-builder-inline-hint"><strong>Orden:</strong> posicion de la columna en el reporte (1 = primero, 2 = segundo, ...).</p>
        <form method="post" class="form-inline">
          <input type="hidden" name="reports_active_tab" value="builder">
          <input type="hidden" name="builder_action" value="add_field_column">
          <input type="hidden" name="builder_report_id" value="<?php echo (int)$builderState['selected_report_id']; ?>">
          <input type="hidden" name="builder_property_code" value="<?php echo htmlspecialchars($builderState['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="builder_run_from" value="<?php echo htmlspecialchars($builderState['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="builder_run_to" value="<?php echo htmlspecialchars($builderState['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="builder_run_limit" value="<?php echo (int)$builderState['limit']; ?>">
          <select name="builder_add_field_entity" class="builder-entity-select" data-target-select="builder-add-field-key">
            <option value="huesped" <?php echo $builderAddFieldEntity === 'huesped' ? 'selected' : ''; ?>>Huesped</option>
            <option value="reservacion" <?php echo $builderAddFieldEntity === 'reservacion' ? 'selected' : ''; ?>>Reservacion</option>
            <option value="line_item" <?php echo $builderAddFieldEntity === 'line_item' ? 'selected' : ''; ?>>Line item</option>
            <option value="hospedaje" <?php echo $builderAddFieldEntity === 'hospedaje' ? 'selected' : ''; ?>>Hospedaje</option>
            <option value="all" <?php echo $builderAddFieldEntity === 'all' ? 'selected' : ''; ?>(Todos)</option>
          </select>
          <select name="builder_add_field_key" id="builder-add-field-key">
            <option value="">Campo...</option>
            <?php foreach ($fieldOptionMeta as $fk => $meta): ?>
              <option value="<?php echo htmlspecialchars($fk, ENT_QUOTES, 'UTF-8'); ?>" data-entity="<?php echo htmlspecialchars((string)$meta['entity'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(((string)$meta['label']) . ' (' . $fk . ')', ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="builder_add_field_display" placeholder="Display name">
          <input type="number" min="1" name="builder_add_field_order" value="1" placeholder="Orden" title="Orden de aparicion (1 = primero)" aria-label="Orden de aparicion">
          <label class="pill"><input type="checkbox" name="builder_add_field_visible" value="1" checked><span>Visible</span></label>
          <label class="pill"><input type="checkbox" name="builder_add_field_filterable" value="1" checked><span>Filtrable</span></label>
          <button type="submit" class="btn">Agregar</button>
        </form>
      </div>
      <div class="card-inner">
        <h3>Agregar columna catalogo</h3>
        <p class="muted report-builder-inline-hint"><strong>Orden:</strong> posicion de la columna en el reporte (1 = primero, 2 = segundo, ...).</p>
        <p class="muted report-builder-inline-hint">Elige la <strong>metrica</strong> del catalogo: monto, cantidad, service_date, paid_cents, etc.</p>
        <form method="post" class="form-inline">
          <input type="hidden" name="reports_active_tab" value="builder">
          <input type="hidden" name="builder_action" value="add_catalog_column">
          <input type="hidden" name="builder_report_id" value="<?php echo (int)$builderState['selected_report_id']; ?>">
          <input type="hidden" name="builder_property_code" value="<?php echo htmlspecialchars($builderState['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="builder_run_from" value="<?php echo htmlspecialchars($builderState['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="builder_run_to" value="<?php echo htmlspecialchars($builderState['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="builder_run_limit" value="<?php echo (int)$builderState['limit']; ?>">
          <select name="builder_add_catalog_id">
            <option value="0">Catalogo...</option>
            <?php foreach ($catalogOptionsMap as $ck => $cl): ?><option value="<?php echo (int)$ck; ?>"><?php echo htmlspecialchars($cl . ' (#' . $ck . ')', ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
          </select>
          <select name="builder_add_catalog_metric" title="Metrica del catalogo">
            <?php foreach ($builderCatalogMetricOptionsLocal as $metricKey => $metricCfg): ?>
              <option value="<?php echo htmlspecialchars((string)$metricKey, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $builderAddCatalogMetric === (string)$metricKey ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string)$metricCfg['label'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="builder_add_catalog_display" placeholder="Display name">
          <input type="number" min="1" name="builder_add_catalog_order" value="1" placeholder="Orden" title="Orden de aparicion (1 = primero)" aria-label="Orden de aparicion">
          <label class="pill"><input type="checkbox" name="builder_add_catalog_visible" value="1" checked><span>Visible</span></label>
          <label class="pill"><input type="checkbox" name="builder_add_catalog_filterable" value="1" checked><span>Filtrable</span></label>
          <button type="submit" class="btn">Agregar</button>
        </form>
      </div>
    </div>

    <div class="card-inner">
      <h3>Columnas</h3>
      <?php if ($builderColumns): ?>
        <div class="report-builder-list">
          <?php foreach ($builderColumns as $c): ?>
            <form method="post" class="report-builder-row">
              <input type="hidden" name="reports_active_tab" value="builder">
              <input type="hidden" name="builder_report_id" value="<?php echo (int)$builderState['selected_report_id']; ?>">
              <input type="hidden" name="builder_property_code" value="<?php echo htmlspecialchars($builderState['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_run_from" value="<?php echo htmlspecialchars($builderState['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_run_to" value="<?php echo htmlspecialchars($builderState['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_run_limit" value="<?php echo (int)$builderState['limit']; ?>">
              <input type="hidden" name="builder_column_id" value="<?php echo (int)$c['id_report_config_column']; ?>">
              <input type="hidden" name="builder_column_key" value="<?php echo htmlspecialchars((string)$c['column_key'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_column_source" value="<?php echo htmlspecialchars((string)$c['column_source'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_column_source_field_key" value="<?php echo htmlspecialchars((string)$c['source_field_key'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_column_catalog_id" value="<?php echo (int)$c['id_line_item_catalog']; ?>">
              <strong><?php echo htmlspecialchars((string)$c['column_key'], ENT_QUOTES, 'UTF-8'); ?></strong>
              <input type="text" name="builder_column_display" value="<?php echo htmlspecialchars((string)$c['display_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Display">
              <input type="number" min="1" name="builder_column_order" value="<?php echo (int)$c['order_index']; ?>" placeholder="Orden" title="Orden de aparicion (1 = primero)" aria-label="Orden de aparicion">
              <label class="pill"><input type="checkbox" name="builder_column_visible" value="1" <?php echo !empty($c['is_visible']) ? 'checked' : ''; ?>><span>Visible</span></label>
              <label class="pill"><input type="checkbox" name="builder_column_filterable" value="1" <?php echo !empty($c['is_filterable']) ? 'checked' : ''; ?>><span>Filtrable</span></label>
              <button type="submit" class="btn btn-ghost" name="builder_action" value="update_column">Actualizar</button>
              <button type="submit" class="btn btn-ghost" name="builder_action" value="delete_column" onclick="return confirm('Eliminar columna?');">Eliminar</button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php else: ?><p class="muted">Sin columnas.</p><?php endif; ?>
    </div>

    <div class="card-inner">
      <h3>Filtros</h3>
      <p class="muted report-builder-inline-hint"><strong>Orden:</strong> prioridad de aplicacion de filtros (1 = primero).</p>
      <form method="post" class="form-inline">
        <input type="hidden" name="reports_active_tab" value="builder">
        <input type="hidden" name="builder_action" value="add_filter">
        <input type="hidden" name="builder_report_id" value="<?php echo (int)$builderState['selected_report_id']; ?>">
        <input type="hidden" name="builder_property_code" value="<?php echo htmlspecialchars($builderState['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="builder_run_from" value="<?php echo htmlspecialchars($builderState['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="builder_run_to" value="<?php echo htmlspecialchars($builderState['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="builder_run_limit" value="<?php echo (int)$builderState['limit']; ?>">
        <input type="hidden" name="builder_add_filter_from" value="">
        <input type="hidden" name="builder_add_filter_to" value="">
        <input type="hidden" name="builder_add_filter_list" value="">
        <input type="hidden" name="builder_add_filter_logic" value="AND">
        <select name="builder_add_filter_entity" class="builder-entity-select" data-target-select="builder-add-filter-key">
          <option value="huesped" <?php echo $builderAddFilterEntity === 'huesped' ? 'selected' : ''; ?>>Huesped</option>
          <option value="reservacion" <?php echo $builderAddFilterEntity === 'reservacion' ? 'selected' : ''; ?>>Reservacion</option>
          <option value="line_item" <?php echo $builderAddFilterEntity === 'line_item' ? 'selected' : ''; ?>>Line item</option>
          <option value="hospedaje" <?php echo $builderAddFilterEntity === 'hospedaje' ? 'selected' : ''; ?>>Hospedaje</option>
          <option value="all" <?php echo $builderAddFilterEntity === 'all' ? 'selected' : ''; ?>(Todos)</option>
        </select>
        <select name="builder_add_filter_key" id="builder-add-filter-key">
          <option value="">Campo...</option>
          <?php foreach ($filterKeys as $fk => $meta): ?>
            <option value="<?php echo htmlspecialchars($fk, ENT_QUOTES, 'UTF-8'); ?>" data-entity="<?php echo htmlspecialchars((string)$meta['entity'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars(((string)$meta['label']) . ' (' . $fk . ')', ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="builder_add_filter_operator"><?php foreach ($builderFilterOperators as $ok => $ol): ?><option value="<?php echo htmlspecialchars($ok, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ol, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
        <input type="text" name="builder_add_filter_value" placeholder="Valor">
        <input type="number" min="1" name="builder_add_filter_order" value="1" placeholder="Orden" title="Orden de aplicacion del filtro (1 = primero)" aria-label="Orden de filtro">
        <button type="submit" class="btn">Agregar filtro</button>
      </form>
      <?php if ($builderFilters): ?>
        <div class="report-builder-list">
          <?php foreach ($builderFilters as $f): ?>
            <form method="post" class="report-builder-row">
              <input type="hidden" name="reports_active_tab" value="builder">
              <input type="hidden" name="builder_report_id" value="<?php echo (int)$builderState['selected_report_id']; ?>">
              <input type="hidden" name="builder_property_code" value="<?php echo htmlspecialchars($builderState['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_run_from" value="<?php echo htmlspecialchars($builderState['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_run_to" value="<?php echo htmlspecialchars($builderState['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_run_limit" value="<?php echo (int)$builderState['limit']; ?>">
              <input type="hidden" name="builder_filter_id" value="<?php echo (int)$f['id_report_config_filter']; ?>">
              <input type="hidden" name="builder_filter_from" value="<?php echo htmlspecialchars((string)$f['value_from_text'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_filter_to" value="<?php echo htmlspecialchars((string)$f['value_to_text'], ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="builder_filter_list" value="<?php echo htmlspecialchars((string)$f['value_list_text'], ENT_QUOTES, 'UTF-8'); ?>">
              <select name="builder_filter_key">
                <?php foreach ($filterKeys as $fk => $meta): ?>
                  <option value="<?php echo htmlspecialchars($fk, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string)$f['filter_key'] === $fk) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(((string)$meta['label']) . ' (' . $fk . ')', ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <select name="builder_filter_operator"><?php foreach ($builderFilterOperators as $ok => $ol): ?><option value="<?php echo htmlspecialchars($ok, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string)$f['operator_key'] === $ok) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ol, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>
              <input type="text" name="builder_filter_value" value="<?php echo htmlspecialchars((string)$f['value_text'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Valor">
              <input type="number" min="1" name="builder_filter_order" value="<?php echo (int)$f['order_index']; ?>" placeholder="Orden" title="Orden de aplicacion del filtro (1 = primero)" aria-label="Orden de filtro">
              <select name="builder_filter_logic">
                <?php $logic = isset($f['logic_join']) ? (string)$f['logic_join'] : 'AND'; ?>
                <option value="AND" <?php echo $logic === 'AND' ? 'selected' : ''; ?>>AND</option>
                <option value="OR" <?php echo $logic === 'OR' ? 'selected' : ''; ?>>OR</option>
              </select>
              <label class="pill"><input type="checkbox" name="builder_filter_active" value="1" <?php echo !empty($f['is_active']) ? 'checked' : ''; ?>><span>Activo</span></label>
              <button type="submit" class="btn btn-ghost" name="builder_action" value="update_filter">Actualizar</button>
              <button type="submit" class="btn btn-ghost" name="builder_action" value="delete_filter" onclick="return confirm('Eliminar filtro?');">Eliminar</button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card-inner">
      <h3>Vista previa</h3>
      <?php
      $previewCols = array();
      foreach ($builderRunColumns as $rcol) {
          if (!empty($rcol['is_visible'])) {
              $previewCols[] = $rcol;
          }
      }
      if (!$previewCols) {
          $previewCols = $builderRunColumns;
      }
      ?>
      <?php if ($previewCols): ?>
        <div class="table-scroll">
          <table>
            <thead><tr><?php foreach ($previewCols as $pc): ?><th><?php echo htmlspecialchars((string)$pc['display_name'], ENT_QUOTES, 'UTF-8'); ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($builderRunRows as $r): ?>
                <tr>
                  <?php foreach ($previewCols as $pc): ?>
                    <?php
                    $key = isset($pc['column_key']) ? (string)$pc['column_key'] : '';
                    $dtype = isset($pc['data_type']) ? (string)$pc['data_type'] : 'text';
                    $val = ($key !== '' && array_key_exists($key, $r) && $r[$key] !== null) ? $r[$key] : '';
                    if ($dtype === 'money') {
                        $cur = isset($r['currency']) && (string)$r['currency'] !== '' ? (string)$r['currency'] : 'MXN';
                        $val = reports_format_money((int)$val, $cur);
                    }
                    ?>
                    <td><?php echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">Configura columnas para ver resultados.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <style>
    .report-builder-box { margin-top: 10px; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 12px; background: rgba(12,20,32,0.55); }
    .report-fixed-hint { margin-top: 10px; padding: 10px 12px; border: 1px solid rgba(255,220,120,.35); border-radius: 10px; background: rgba(130,96,20,.16); color: #ffd88d; }
    .report-builder-wide { grid-column: 1 / -1; }
    .report-builder-inline-hint { margin: 0 0 8px; font-size: 12px; color: #8fb8df; }
    .report-builder-split { margin-top: 12px; display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); }
    .report-builder-list { display: grid; gap: 8px; }
    .report-builder-row { display: grid; gap: 8px; grid-template-columns: 1.2fr 1fr 0.6fr auto auto auto; align-items: center; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 8px; background: rgba(16,24,36,0.64); }
    .builder-entity-select { min-width: 140px; }
    @media (max-width: 1180px) { .report-builder-row { grid-template-columns: 1fr; } }
  </style>
  <script>
    (function () {
      var toggles = document.querySelectorAll('.builder-entity-select[data-target-select]');
      if (!toggles.length) {
        return;
      }
      var applyFilter = function (entitySelect) {
        var targetId = entitySelect.getAttribute('data-target-select');
        if (!targetId) {
          return;
        }
        var target = document.getElementById(targetId);
        if (!target) {
          return;
        }
        var filter = entitySelect.value || 'all';
        var selectedVisible = true;
        Array.prototype.forEach.call(target.options, function (opt) {
          if (!opt.value) {
            opt.hidden = false;
            return;
          }
          var entity = opt.getAttribute('data-entity') || 'reservacion';
          var visible = filter === 'all' || entity === filter;
          opt.hidden = !visible;
          if (opt.selected && !visible) {
            selectedVisible = false;
          }
        });
        if (!selectedVisible) {
          target.value = '';
        }
      };
      Array.prototype.forEach.call(toggles, function (entitySelect) {
        entitySelect.addEventListener('change', function () {
          applyFilter(entitySelect);
        });
        applyFilter(entitySelect);
      });
    })();
  </script>
</section>
