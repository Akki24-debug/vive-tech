<?php
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';

if ($companyId <= 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

pms_require_permission('rateplans.view');

if (!function_exists('rateplans_settings_column_exists')) {
    function rateplans_settings_column_exists(PDO $pdo, $columnName)
    {
        $columnName = trim((string)$columnName);
        if ($columnName === '') {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?'
            );
            $stmt->execute(array('pms_settings', $columnName));
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

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

$message = null;
$error = null;
$pricingStrategyOptions = array(
    'use_bases' => 'Usar bases'
);
$pricingStrategy = 'use_bases';
$pricingStrategyColumnReady = false;
$rateplansAction = isset($_POST['rateplans_action']) ? trim((string)$_POST['rateplans_action']) : '';

try {
    $pdoSettings = pms_get_connection();
    $pricingStrategyColumnReady = rateplans_settings_column_exists($pdoSettings, 'pricing_strategy');
} catch (Exception $e) {
    $pricingStrategyColumnReady = false;
}

if ($rateplansAction === 'save_pricing_strategy') {
    pms_require_permission('rateplans.edit');
    if ($selectedProperty !== '') {
        pms_require_property_access($selectedProperty);
    }
    if ($selectedPropertyId <= 0) {
        $error = 'Selecciona una propiedad para guardar el modo de precios.';
    } elseif (!$pricingStrategyColumnReady) {
        $error = 'Falta la columna pricing_strategy en pms_settings. Aplica primero bd pms/migrate_pms_settings_pricing_strategy.sql.';
    } else {
        $incomingStrategy = isset($_POST['pricing_strategy']) ? strtolower(trim((string)$_POST['pricing_strategy'])) : 'use_bases';
        if (!isset($pricingStrategyOptions[$incomingStrategy])) {
            $incomingStrategy = 'use_bases';
        }
        try {
            $pdoSettings = isset($pdoSettings) ? $pdoSettings : pms_get_connection();
            $propertyRow = null;
            foreach ($properties as $propertyRowTmp) {
                $propertyCodeTmp = isset($propertyRowTmp['code']) ? strtoupper((string)$propertyRowTmp['code']) : '';
                if ($propertyCodeTmp === $selectedProperty) {
                    $propertyRow = $propertyRowTmp;
                    break;
                }
            }
            $propertyCompanyId = $propertyRow && isset($propertyRow['id_company']) ? (int)$propertyRow['id_company'] : $companyId;
            $stmt = $pdoSettings->prepare(
                'SELECT id_setting
                 FROM pms_settings
                 WHERE id_company = ?
                   AND id_property = ?
                 ORDER BY id_setting DESC
                 LIMIT 1'
            );
            $stmt->execute(array($propertyCompanyId, $selectedPropertyId));
            $existingId = (int)$stmt->fetchColumn();

            if ($existingId > 0) {
                $stmt = $pdoSettings->prepare(
                    'UPDATE pms_settings
                     SET pricing_strategy = ?,
                         updated_at = NOW()
                     WHERE id_setting = ?'
                );
                $stmt->execute(array($incomingStrategy, $existingId));
            } else {
                $stmt = $pdoSettings->prepare(
                    'INSERT INTO pms_settings (
                        id_company,
                        id_property,
                        pricing_strategy,
                        created_at,
                        created_by,
                        updated_at
                     ) VALUES (
                        ?, ?, ?, NOW(), ?, NOW()
                     )'
                );
                $stmt->execute(array(
                    $propertyCompanyId,
                    $selectedPropertyId,
                    $incomingStrategy,
                    isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null
                ));
            }
            $pricingStrategy = $incomingStrategy;
            $message = 'Modo de precios guardado.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if ($selectedPropertyId > 0 && $pricingStrategyColumnReady) {
    try {
        $pdoSettings = isset($pdoSettings) ? $pdoSettings : pms_get_connection();
        $stmt = $pdoSettings->prepare(
            'SELECT COALESCE(NULLIF(TRIM(pricing_strategy), \'\'), \'use_bases\') AS pricing_strategy
             FROM pms_settings
             WHERE id_company = ?
               AND (id_property = ? OR id_property IS NULL)
             ORDER BY CASE WHEN id_property = ? THEN 0 ELSE 1 END, id_setting DESC
             LIMIT 1'
        );
        $stmt->execute(array($companyId, $selectedPropertyId, $selectedPropertyId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['pricing_strategy']) && isset($pricingStrategyOptions[strtolower((string)$row['pricing_strategy'])])) {
            $pricingStrategy = strtolower((string)$row['pricing_strategy']);
        }
    } catch (Exception $e) {
        if ($error === null) {
            $error = $e->getMessage();
        }
    }
}

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

  <?php if ($message !== null): ?>
    <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if ($queryError !== null): ?>
    <p class="error"><?php echo htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <div class="panel" style="margin-top:16px;">
    <div class="panel-header">
      <div>
        <h3>Modo de calculo</h3>
        <p class="muted">Este ajuste se guarda por propiedad y define como se calcularan los precios operativos.</p>
      </div>
    </div>

    <?php if ($selectedPropertyId <= 0): ?>
      <p class="muted">Selecciona una propiedad para configurar el modo de precios.</p>
    <?php elseif (!$pricingStrategyColumnReady): ?>
      <p class="error">Falta la columna <code>pricing_strategy</code> en <code>pms_settings</code>. Aplica primero <code>bd pms/migrate_pms_settings_pricing_strategy.sql</code>.</p>
    <?php else: ?>
      <form method="post" class="form-grid">
        <input type="hidden" name="rateplans_action" value="save_pricing_strategy">
        <input type="hidden" name="rateplans_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
        <label>
          Estrategia
          <select name="pricing_strategy">
            <?php foreach ($pricingStrategyOptions as $strategyCode => $strategyLabel): ?>
              <option value="<?php echo htmlspecialchars($strategyCode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $pricingStrategy === $strategyCode ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($strategyLabel, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="inline-banner info">
          <strong>Usar bases:</strong>
          ignora planes de precio y calcula con precio base de categoria mas override por fecha si existe.
        </div>
        <div>
          <button type="submit">Guardar modo</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

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
