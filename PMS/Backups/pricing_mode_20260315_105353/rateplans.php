<?php
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';

if ($companyId <= 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

pms_require_permission('rateplans.view');

$properties = pms_fetch_properties($companyId);
$propertyIndex = array();
foreach ($properties as $property) {
    $code = isset($property['code']) ? strtoupper((string)$property['code']) : '';
    $idProperty = isset($property['id_property']) ? (int)$property['id_property'] : 0;
    if ($code !== '' && $idProperty > 0) {
        $propertyIndex[$code] = $idProperty;
    }
}

$selectedProperty = '';
if (isset($_POST['rateplans_filter_property'])) {
    $selectedProperty = strtoupper(trim((string)$_POST['rateplans_filter_property']));
} elseif (isset($_GET['rateplans_filter_property'])) {
    $selectedProperty = strtoupper(trim((string)$_GET['rateplans_filter_property']));
}
if ($selectedProperty !== '' && !isset($propertyIndex[$selectedProperty])) {
    $selectedProperty = '';
}
$selectedPropertyId = $selectedProperty !== '' && isset($propertyIndex[$selectedProperty])
    ? (int)$propertyIndex[$selectedProperty]
    : 0;

$legacyRateplans = array();
$overrideRows = array();
$summary = array(
    'rateplans' => 0,
    'overrides' => 0,
    'legacy_anchor_overrides' => 0
);
$queryError = null;

try {
    $pdo = pms_get_connection();

    $rateplanSql = 'SELECT
            rp.id_rateplan,
            rp.code,
            rp.name,
            rp.is_active,
            p.code AS property_code,
            p.name AS property_name
        FROM rateplan rp
        JOIN property p ON p.id_property = rp.id_property
        WHERE p.id_company = ?
          AND rp.deleted_at IS NULL';
    $rateplanParams = array($companyId);
    if ($selectedPropertyId > 0) {
        $rateplanSql .= ' AND rp.id_property = ?';
        $rateplanParams[] = $selectedPropertyId;
    }
    $rateplanSql .= ' ORDER BY p.order_index, p.name, rp.code';
    $stmt = $pdo->prepare($rateplanSql);
    $stmt->execute($rateplanParams);
    $legacyRateplans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $summary['rateplans'] = count($legacyRateplans);
    foreach ($legacyRateplans as $planRow) {
        if (isset($planRow['code']) && strtoupper((string)$planRow['code']) === 'LEGACY_OVERRIDES') {
            $summary['legacy_anchor_overrides']++;
        }
    }

    $overrideSql = 'SELECT
            ro.id_rateplan_override,
            ro.override_date,
            ro.price_cents,
            ro.notes,
            ro.is_active,
            rp.code AS rateplan_code,
            rp.name AS rateplan_name,
            p.code AS property_code,
            p.name AS property_name,
            rc.code AS category_code,
            rc.name AS category_name,
            r.code AS room_code,
            r.name AS room_name
        FROM rateplan_override ro
        JOIN rateplan rp ON rp.id_rateplan = ro.id_rateplan
        JOIN property p ON p.id_property = rp.id_property
        LEFT JOIN roomcategory rc ON rc.id_category = ro.id_category
        LEFT JOIN room r ON r.id_room = ro.id_room
        WHERE p.id_company = ?
          AND rp.deleted_at IS NULL';
    $overrideParams = array($companyId);
    if ($selectedPropertyId > 0) {
        $overrideSql .= ' AND p.id_property = ?';
        $overrideParams[] = $selectedPropertyId;
    }
    $overrideSql .= ' ORDER BY ro.override_date DESC, ro.id_rateplan_override DESC LIMIT 120';
    $stmt = $pdo->prepare($overrideSql);
    $stmt->execute($overrideParams);
    $overrideRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $summary['overrides'] = count($overrideRows);
} catch (Exception $e) {
    $queryError = $e->getMessage();
}
?>

<section class="panel">
  <div class="panel-header">
    <div>
      <h2>Tarifas Legacy</h2>
      <p class="muted">El motor complejo de planes de precio quedo fuera del flujo operativo actual.</p>
    </div>
  </div>

  <div class="inline-banner warning">
    <strong>Estado actual:</strong>
    Calendario, reservas y wizard usan precio base de categoria + override operativo.
    Esta vista se conserva solo como respaldo y referencia legacy.
  </div>

  <form method="post" class="filters-bar" style="margin-top:16px;">
    <label>
      Propiedad
      <select name="rateplans_filter_property" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach ($properties as $property): ?>
          <?php
            $propertyCode = isset($property['code']) ? strtoupper((string)$property['code']) : '';
            $propertyName = isset($property['name']) ? (string)$property['name'] : $propertyCode;
          ?>
          <option value="<?php echo htmlspecialchars($propertyCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedProperty === $propertyCode ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($propertyCode . ' - ' . $propertyName, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <?php if ($queryError !== null): ?>
    <p class="error"><?php echo htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <div class="stats-grid compact-grid" style="margin-top:16px;">
    <article class="stat-card">
      <span class="stat-label">Planes legacy</span>
      <strong class="stat-value"><?php echo (int)$summary['rateplans']; ?></strong>
    </article>
    <article class="stat-card">
      <span class="stat-label">Overrides visibles</span>
      <strong class="stat-value"><?php echo (int)$summary['overrides']; ?></strong>
    </article>
    <article class="stat-card">
      <span class="stat-label">Anclas legacy</span>
      <strong class="stat-value"><?php echo (int)$summary['legacy_anchor_overrides']; ?></strong>
    </article>
  </div>

  <div class="inline-banner info" style="margin-top:16px;">
    <strong>Uso recomendado:</strong>
    Ajusta precio base en Categorias. Captura overrides desde Calendario. No uses esta pantalla para operar tarifas nuevas.
  </div>
</section>

<section class="panel">
  <div class="panel-header">
    <div>
      <h3>Planes Conservados</h3>
      <p class="muted">Solo referencia. Los calculos legacy siguen en backend pero fuera del flujo actual.</p>
    </div>
  </div>

  <?php if (!$legacyRateplans): ?>
    <p class="muted">No hay planes legacy registrados para este filtro.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Propiedad</th>
            <th>Codigo</th>
            <th>Nombre</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($legacyRateplans as $planRow): ?>
            <?php
              $planCode = isset($planRow['code']) ? (string)$planRow['code'] : '';
              $isAnchor = strtoupper($planCode) === 'LEGACY_OVERRIDES';
              $nameLabel = isset($planRow['name']) ? (string)$planRow['name'] : $planCode;
              if ($isAnchor) {
                  $nameLabel .= ' (ancla overrides)';
              }
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$planRow['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($planCode, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($nameLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo !empty($planRow['is_active']) ? 'Activo' : 'Legacy / oculto'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-header">
    <div>
      <h3>Overrides Registrados</h3>
      <p class="muted">Los overrides siguen activos y el calendario los aplica aunque no haya plan asignado a la categoria.</p>
    </div>
  </div>

  <?php if (!$overrideRows): ?>
    <p class="muted">No hay overrides para este filtro.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Propiedad</th>
            <th>Scope</th>
            <th>Precio</th>
            <th>Ancla legacy</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($overrideRows as $overrideRow): ?>
            <?php
              $scopeLabel = 'Global';
              if (!empty($overrideRow['room_code'])) {
                  $scopeLabel = 'Habitacion ' . (string)$overrideRow['room_code'];
              } elseif (!empty($overrideRow['category_code'])) {
                  $scopeLabel = 'Categoria ' . (string)$overrideRow['category_code'];
              }
              $priceLabel = number_format(((int)$overrideRow['price_cents']) / 100, 2, '.', '');
              $anchorLabel = isset($overrideRow['rateplan_code']) ? (string)$overrideRow['rateplan_code'] : '';
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$overrideRow['override_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$overrideRow['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($anchorLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo !empty($overrideRow['is_active']) ? 'Activo' : 'Inactivo'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
