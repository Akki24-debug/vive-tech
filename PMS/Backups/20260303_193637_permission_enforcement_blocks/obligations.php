<?php
$moduleKey = 'obligations';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$companyId   = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

if (!function_exists('obligations_format_money')) {
    function obligations_format_money($cents, $currency)
    {
        $value = ((int)$cents) / 100;
        return '$' . number_format($value, 2, '.', ',') . ' ' . ($currency !== '' ? $currency : 'MXN');
    }
}

if (!function_exists('obligations_to_cents')) {
    function obligations_to_cents($raw)
    {
        $txt = trim((string)$raw);
        if ($txt === '') {
            return 0;
        }
        $txt = str_replace(',', '', $txt);
        if (!is_numeric($txt)) {
            return 0;
        }
        return (int)round(((float)$txt) * 100);
    }
}

if (!function_exists('obligations_render_filter_hidden_fields')) {
    function obligations_render_filter_hidden_fields(array $filters)
    {
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            echo '<input type="hidden" name="'
                . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
                . '" value="'
                . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
                . '">';
        }
    }
}

$properties = pms_fetch_properties($companyId);
$message = null;
$error = null;
$obligationPaymentMethods = array();
$obligationTypeOptions = array(
    'property_payment' => 'Pago a propiedad',
    'ota_payment' => 'Pago a OTA',
    'tax_payment' => 'Pago de impuesto'
);

$filters = array(
    'obligations_type' => isset($_POST['obligations_type']) ? strtolower(trim((string)$_POST['obligations_type'])) : 'property_payment',
    'obligations_property' => isset($_POST['obligations_property']) ? strtoupper(trim((string)$_POST['obligations_property'])) : '',
    'obligations_from' => isset($_POST['obligations_from']) ? trim((string)$_POST['obligations_from']) : '',
    'obligations_to' => isset($_POST['obligations_to']) ? trim((string)$_POST['obligations_to']) : '',
    'obligations_search' => isset($_POST['obligations_search']) ? trim((string)$_POST['obligations_search']) : '',
    'obligations_payment_status' => isset($_POST['obligations_payment_status']) ? strtolower(trim((string)$_POST['obligations_payment_status'])) : '',
    'obligations_reservation_id' => isset($_POST['obligations_reservation_id']) ? (int)$_POST['obligations_reservation_id'] : 0,
    'obligations_folio_id' => isset($_POST['obligations_folio_id']) ? (int)$_POST['obligations_folio_id'] : 0,
    'obligations_show_inactive' => isset($_POST['obligations_show_inactive']) ? 1 : 0,
    'obligations_limit' => isset($_POST['obligations_limit']) ? (int)$_POST['obligations_limit'] : 500
);

if (isset($_POST['obligations_reset']) && (string)$_POST['obligations_reset'] === '1') {
    $filters = array(
        'obligations_type' => 'property_payment',
        'obligations_property' => '',
        'obligations_from' => '',
        'obligations_to' => '',
        'obligations_search' => '',
        'obligations_payment_status' => '',
        'obligations_reservation_id' => 0,
        'obligations_folio_id' => 0,
        'obligations_show_inactive' => 0,
        'obligations_limit' => 500
    );
}

if (!isset($obligationTypeOptions[$filters['obligations_type']])) {
    $filters['obligations_type'] = 'property_payment';
}
if (!in_array($filters['obligations_payment_status'], array('', 'pending', 'partial', 'paid'), true)) {
    $filters['obligations_payment_status'] = '';
}
if (!in_array($filters['obligations_limit'], array(200, 500, 1000, 2000), true)) {
    $filters['obligations_limit'] = 500;
}

if (isset($_POST['obligations_action']) && in_array((string)$_POST['obligations_action'], array('apply_add', 'apply_set', 'apply_full', 'apply_all'), true)) {
    $action = (string)$_POST['obligations_action'];
    $obligationPaymentMethodId = isset($_POST['obligation_payment_method_id']) ? (int)$_POST['obligation_payment_method_id'] : 0;
    $obligationPaymentNotes = isset($_POST['obligation_payment_notes']) ? trim((string)$_POST['obligation_payment_notes']) : '';

    if ($action === 'apply_all') {
        $rawBulkIds = isset($_POST['obligation_bulk_line_item_id']) ? $_POST['obligation_bulk_line_item_id'] : array();
        $rawBulkAmounts = isset($_POST['obligation_bulk_amount_cents']) ? $_POST['obligation_bulk_amount_cents'] : array();
        $bulkIds = is_array($rawBulkIds) ? $rawBulkIds : array($rawBulkIds);
        $bulkAmounts = is_array($rawBulkAmounts) ? $rawBulkAmounts : array($rawBulkAmounts);
        $bulkMap = array();

        foreach ($bulkIds as $idx => $rawId) {
            $lineItemId = (int)$rawId;
            $amountCents = isset($bulkAmounts[$idx]) ? (int)$bulkAmounts[$idx] : 0;
            if ($lineItemId <= 0 || $amountCents <= 0) {
                continue;
            }
            if (!isset($bulkMap[$lineItemId]) || $amountCents > $bulkMap[$lineItemId]) {
                $bulkMap[$lineItemId] = $amountCents;
            }
        }

        if ($obligationPaymentMethodId <= 0) {
            $error = 'Selecciona un metodo de pago para pagar todas las obligaciones visibles.';
        } elseif (empty($bulkMap)) {
            $error = 'No hay obligaciones visibles validas para pagar.';
        } else {
            $appliedCount = 0;
            try {
                foreach ($bulkMap as $lineItemId => $amountCents) {
                    pms_call_procedure('sp_obligation_paid_upsert', array(
                        $companyCode,
                        (int)$lineItemId,
                        'set',
                        (int)$amountCents,
                        $obligationPaymentMethodId,
                        $obligationPaymentNotes,
                        $actorUserId
                    ));
                    $appliedCount++;
                }
                $message = 'Pago full aplicado a ' . $appliedCount . ' obligaciones visibles.';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        $lineItemId = isset($_POST['obligation_line_item_id']) ? (int)$_POST['obligation_line_item_id'] : 0;
        $mode = ($action === 'apply_add') ? 'add' : 'set';
        $amountCents = 0;

        if ($lineItemId <= 0) {
            $error = 'Selecciona una obligacion valida.';
        } elseif ($obligationPaymentMethodId <= 0) {
            $error = 'Selecciona un metodo de pago para la obligacion.';
        } else {
            if ($action === 'apply_full') {
                $amountCents = isset($_POST['obligation_full_amount_cents']) ? (int)$_POST['obligation_full_amount_cents'] : 0;
            } else {
                $amountCents = obligations_to_cents(isset($_POST['obligation_apply_amount']) ? $_POST['obligation_apply_amount'] : '');
            }

            if ($amountCents < 0) {
                $amountCents = 0;
            }

            if ($action === 'apply_add' && $amountCents <= 0) {
                $error = 'Indica un monto valido para aplicar.';
            } else {
                try {
                    $resultSets = pms_call_procedure('sp_obligation_paid_upsert', array(
                        $companyCode,
                        $lineItemId,
                        $mode,
                        $amountCents,
                        $obligationPaymentMethodId,
                        $obligationPaymentNotes,
                        $actorUserId
                    ));
                    $updated = isset($resultSets[0][0]) ? $resultSets[0][0] : null;
                    if ($updated) {
                        $updatedPaid = isset($updated['paid_cents']) ? (int)$updated['paid_cents'] : 0;
                        $updatedRemaining = isset($updated['remaining_cents']) ? (int)$updated['remaining_cents'] : 0;
                        $message = 'Obligacion actualizada. Pagado: '
                            . obligations_format_money($updatedPaid, 'MXN')
                            . ' | Pendiente: '
                            . obligations_format_money($updatedRemaining, 'MXN');
                    } else {
                        $message = 'Obligacion actualizada.';
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$rows = array();
$rowsRaw = array();
try {
    $sets = pms_call_procedure('sp_obligation_data', array(
        $companyCode,
        $filters['obligations_property'] !== '' ? $filters['obligations_property'] : null,
        $filters['obligations_from'] !== '' ? $filters['obligations_from'] : null,
        $filters['obligations_to'] !== '' ? $filters['obligations_to'] : null,
        $filters['obligations_search'] !== '' ? $filters['obligations_search'] : null,
        $filters['obligations_payment_status'] !== '' ? $filters['obligations_payment_status'] : null,
        !empty($filters['obligations_show_inactive']) ? 1 : 0,
        $filters['obligations_reservation_id'] > 0 ? $filters['obligations_reservation_id'] : 0,
        $filters['obligations_folio_id'] > 0 ? $filters['obligations_folio_id'] : 0,
        $filters['obligations_limit']
    ));
    $rowsRaw = isset($sets[0]) ? $sets[0] : array();
} catch (Exception $e) {
    $rowsRaw = array();
    $rows = array();
    if ($error === null) {
        $error = $e->getMessage();
    }
}

try {
    $pdo = pms_get_connection();
    $stmtObligationMethods = $pdo->prepare(
        'SELECT
            m.id_obligation_payment_method,
            m.method_name,
            COALESCE(m.method_description, \'\') AS method_description
         FROM pms_settings_obligation_payment_method m
         WHERE m.id_company = ?
           AND m.deleted_at IS NULL
           AND m.is_active = 1
           AND COALESCE(m.method_description, \'\') NOT LIKE \'[scope:income]%%\'
         ORDER BY m.method_name, m.id_obligation_payment_method'
    );
    $stmtObligationMethods->execute(array($companyId));
    $obligationPaymentMethods = $stmtObligationMethods->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $obligationPaymentMethods = array();
    if ($error === null) {
        $error = 'No fue posible cargar metodos de pago de obligaciones: ' . $e->getMessage();
    }
}

$obligationTypeCounts = array(
    'property_payment' => 0,
    'ota_payment' => 0,
    'tax_payment' => 0
);
foreach ($rowsRaw as $row) {
    $typeKey = isset($row['obligation_type_key']) ? (string)$row['obligation_type_key'] : 'property_payment';
    $hasTaxParentLineItemType = isset($row['has_tax_parent_line_item_type'])
        ? ((int)$row['has_tax_parent_line_item_type'] === 1)
        : false;
    $parentConceptText = strtolower(trim((string)($row['parent_concept_name'] ?? '')));
    $conceptText = strtolower(trim((string)($row['concept_display_name'] ?? '')));
    if ($hasTaxParentLineItemType) {
        $typeKey = 'tax_payment';
    } elseif (
        ($parentConceptText !== '' && strpos($parentConceptText, 'impuesto') !== false)
        || ($conceptText !== '' && strpos($conceptText, 'pago de impuestos') !== false)
    ) {
        $typeKey = 'tax_payment';
    }
    if (!isset($obligationTypeCounts[$typeKey])) {
        $typeKey = 'property_payment';
    }
    $obligationTypeCounts[$typeKey]++;
    if ($typeKey === $filters['obligations_type']) {
        $rows[] = $row;
    }
}

$summary = array(
    'count' => 0,
    'amount_cents' => 0,
    'paid_cents' => 0,
    'remaining_cents' => 0,
    'pending' => 0,
    'partial' => 0,
    'paid' => 0
);
foreach ($rows as $row) {
    $summary['count']++;
    $summary['amount_cents'] += isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0;
    $summary['paid_cents'] += isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0;
    $summary['remaining_cents'] += isset($row['remaining_cents']) ? (int)$row['remaining_cents'] : 0;
    $status = isset($row['payment_status']) ? (string)$row['payment_status'] : '';
    if ($status === 'pending') {
        $summary['pending']++;
    } elseif ($status === 'partial') {
        $summary['partial']++;
    } else {
        $summary['paid']++;
    }
}

$obligationPaymentLogRows = array();
$obligationPaymentLogError = null;
try {
    $pdo = pms_get_connection();
    $visibleLineItemIds = array();
    foreach ($rows as $row) {
        $lineItemId = isset($row['id_line_item']) ? (int)$row['id_line_item'] : 0;
        if ($lineItemId > 0) {
            $visibleLineItemIds[$lineItemId] = true;
        }
    }

    if ($visibleLineItemIds) {
        $lineItemList = implode(',', array_keys($visibleLineItemIds));
        $stmtPaymentLog = $pdo->prepare(
            'SELECT
                opl.id_obligation_payment_log,
                opl.id_line_item,
                opl.id_reservation,
                opl.id_folio,
                opl.payment_mode,
                opl.amount_input_cents,
                opl.amount_applied_cents,
                opl.paid_before_cents,
                opl.paid_after_cents,
                opl.notes,
                opl.created_at,
                opl.created_by,
                COALESCE(pm.method_name, \'\') AS payment_method_name,
                COALESCE(r.code, \'\') AS reservation_code,
                COALESCE(f.folio_name, \'\') AS folio_name,
                COALESCE(p.code, \'\') AS property_code,
                TRIM(CONCAT_WS(\' \', COALESCE(au.names, \'\'), COALESCE(au.last_name, \'\'))) AS actor_name,
                COALESCE(au.email, \'\') AS actor_email
             FROM obligation_payment_log opl
             LEFT JOIN pms_settings_obligation_payment_method pm
               ON pm.id_obligation_payment_method = opl.id_obligation_payment_method
             LEFT JOIN reservation r
               ON r.id_reservation = opl.id_reservation
             LEFT JOIN folio f
               ON f.id_folio = opl.id_folio
             LEFT JOIN property p
               ON p.id_property = r.id_property
             LEFT JOIN app_user au
               ON au.id_user = opl.created_by
             WHERE opl.id_company = ?
               AND opl.id_line_item IN (' . $lineItemList . ')
             ORDER BY opl.created_at DESC, opl.id_obligation_payment_log DESC
             LIMIT 300'
        );
        $stmtPaymentLog->execute(array($companyId));
        $obligationPaymentLogRows = $stmtPaymentLog->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $obligationPaymentLogRows = array();
    $obligationPaymentLogError = 'No fue posible cargar el historial de pagos: ' . $e->getMessage();
}
?>
<div class="reservation-tabs obligations-tabs" data-reservation-tabs="obligations">
  <div class="reservation-tab-nav">
    <button type="button" class="reservation-tab-trigger is-active" data-tab-target="obligations-tab-main">Obligaciones</button>
    <button type="button" class="reservation-tab-trigger" data-tab-target="obligations-tab-history">Historial de pagos</button>
  </div>

  <div class="reservation-tab-panel is-active" id="obligations-tab-main" data-tab-panel>
<div data-obligations-main-extra>
<section class="card">
  <h2>Obligaciones</h2>
  <p class="muted">Consulta tus obligaciones pendientes y registra pagos de forma rapida.</p>
  <?php if ($message !== null): ?>
    <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($error !== null): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if (!$error && !$obligationPaymentMethods): ?>
    <p class="muted">Antes de registrar pagos, configura tus metodos de pago en Configuraciones.</p>
  <?php endif; ?>

  <form method="post">
    <div class="form-inline">
      <label><strong>Tipo de obligacion</strong></label>
      <?php foreach ($obligationTypeOptions as $typeKey => $typeLabel): ?>
        <label class="inline">
          <input
            type="radio"
            name="obligations_type"
            value="<?php echo htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $filters['obligations_type'] === $typeKey ? 'checked' : ''; ?>
            onchange="this.form.submit();"
          >
          <?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
          (<?php echo (int)(isset($obligationTypeCounts[$typeKey]) ? $obligationTypeCounts[$typeKey] : 0); ?>)
        </label>
      <?php endforeach; ?>
    </div>
    <div class="form-inline">
      <label>
        Propiedad
        <select name="obligations_property">
          <option value="">(Todas)</option>
          <?php foreach ($properties as $prop): ?>
            <?php $code = isset($prop['code']) ? (string)$prop['code'] : ''; ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['obligations_property'] === $code ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($code . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Desde
        <input type="date" name="obligations_from" value="<?php echo htmlspecialchars($filters['obligations_from'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Hasta
        <input type="date" name="obligations_to" value="<?php echo htmlspecialchars($filters['obligations_to'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Estado pago
        <select name="obligations_payment_status">
          <option value="">(Todos)</option>
          <option value="pending" <?php echo $filters['obligations_payment_status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
          <option value="partial" <?php echo $filters['obligations_payment_status'] === 'partial' ? 'selected' : ''; ?>>Parcial</option>
          <option value="paid" <?php echo $filters['obligations_payment_status'] === 'paid' ? 'selected' : ''; ?>>Pagada</option>
        </select>
      </label>
      <label>
        Buscar
        <input type="text" name="obligations_search" value="<?php echo htmlspecialchars($filters['obligations_search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Reserva, huesped, folio, concepto...">
      </label>
      <label>
        Reserva ID
        <input type="number" min="0" name="obligations_reservation_id" value="<?php echo (int)$filters['obligations_reservation_id']; ?>">
      </label>
      <label>
        Folio ID
        <input type="number" min="0" name="obligations_folio_id" value="<?php echo (int)$filters['obligations_folio_id']; ?>">
      </label>
      <label>
        Limite
        <select name="obligations_limit">
          <?php foreach (array(200, 500, 1000, 2000) as $limit): ?>
            <option value="<?php echo $limit; ?>" <?php echo (int)$filters['obligations_limit'] === $limit ? 'selected' : ''; ?>><?php echo $limit; ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="inline">
        <input type="checkbox" name="obligations_show_inactive" value="1" <?php echo !empty($filters['obligations_show_inactive']) ? 'checked' : ''; ?>>
        Mostrar inactivos
      </label>
      <button type="submit" class="button-primary">Filtrar</button>
      <button type="submit" class="button-secondary" name="obligations_reset" value="1">Limpiar</button>
    </div>
  </form>
</section>
</div>
  </div>

  <div class="reservation-tab-panel" id="obligations-tab-history" data-tab-panel>
    <section class="card">
      <h3>Historial de pagos</h3>
      <p class="muted">Revisa aqui el detalle de cada pago registrado para las obligaciones visibles.</p>
      <?php
        $historyMethodOptions = array();
        foreach ($obligationPaymentLogRows as $hRow) {
            $methodNameRaw = trim((string)($hRow['payment_method_name'] ?? ''));
            if ($methodNameRaw === '') {
                continue;
            }
            $methodKey = strtolower($methodNameRaw);
            $historyMethodOptions[$methodKey] = $methodNameRaw;
        }
        ksort($historyMethodOptions);
      ?>
      <?php if ($obligationPaymentLogError !== null): ?>
        <p class="error"><?php echo htmlspecialchars($obligationPaymentLogError, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <div class="form-inline" data-obligation-log-filters>
        <label>
          Desde
          <input type="date" id="obligation-log-date-from">
        </label>
        <label>
          Hasta
          <input type="date" id="obligation-log-date-to">
        </label>
        <label>
          Metodo
          <select id="obligation-log-method">
            <option value="">(Todos)</option>
            <?php foreach ($historyMethodOptions as $methodKey => $methodLabel): ?>
              <option value="<?php echo htmlspecialchars($methodKey, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Tipo
          <select id="obligation-log-mode">
            <option value="">(Todos)</option>
            <option value="ADD">ABONO</option>
            <option value="SET">FIJAR</option>
          </select>
        </label>
        <label>
          Buscar
          <input type="text" id="obligation-log-search" placeholder="ID, reserva, folio, notas...">
        </label>
      </div>
      <div class="table-scroll">
        <table id="obligation-log-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>ID movimiento</th>
              <th>Obligacion</th>
              <th>Propiedad</th>
              <th>Reserva</th>
              <th>Folio</th>
              <th>Metodo</th>
              <th>Tipo</th>
              <th>Monto</th>
              <th>Aplicado</th>
              <th>Antes</th>
              <th>Despues</th>
              <th>Registrado por</th>
              <th>Notas</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$obligationPaymentLogRows): ?>
              <tr>
                <td colspan="14" class="muted">Aun no hay pagos registrados para las obligaciones visibles.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($obligationPaymentLogRows as $logRow): ?>
                <?php
                  $currency = 'MXN';
                  $actorLabel = trim((string)($logRow['actor_name'] ?? ''));
                  if ($actorLabel === '') {
                      $actorLabel = trim((string)($logRow['actor_email'] ?? ''));
                  }
                  if ($actorLabel === '') {
                      $actorLabel = 'Usuario #' . (int)($logRow['created_by'] ?? 0);
                  }
                ?>
                <?php
                  $rowMethodKey = strtolower(trim((string)($logRow['payment_method_name'] ?? '')));
                  $rowMode = strtoupper(trim((string)($logRow['payment_mode'] ?? '')));
                  $rowDate = substr((string)($logRow['created_at'] ?? ''), 0, 10);
                  $rowSearch = strtolower(trim(
                      (string)($logRow['id_obligation_payment_log'] ?? '')
                      . ' ' . (string)($logRow['id_line_item'] ?? '')
                      . ' ' . (string)($logRow['property_code'] ?? '')
                      . ' ' . (string)($logRow['reservation_code'] ?? '')
                      . ' ' . (string)($logRow['folio_name'] ?? '')
                      . ' ' . (string)($logRow['payment_method_name'] ?? '')
                      . ' ' . (string)($logRow['notes'] ?? '')
                  ));
                ?>
                <tr
                  data-log-date="<?php echo htmlspecialchars($rowDate, ENT_QUOTES, 'UTF-8'); ?>"
                  data-log-method="<?php echo htmlspecialchars($rowMethodKey, ENT_QUOTES, 'UTF-8'); ?>"
                  data-log-mode="<?php echo htmlspecialchars($rowMode, ENT_QUOTES, 'UTF-8'); ?>"
                  data-log-search="<?php echo htmlspecialchars($rowSearch, ENT_QUOTES, 'UTF-8'); ?>"
                >
                  <td><?php echo htmlspecialchars((string)($logRow['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo (int)($logRow['id_obligation_payment_log'] ?? 0); ?></td>
                  <td><?php echo (int)($logRow['id_line_item'] ?? 0); ?></td>
                  <td><?php echo htmlspecialchars((string)($logRow['property_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)($logRow['reservation_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)($logRow['folio_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)($logRow['payment_method_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(strtoupper((string)($logRow['payment_mode'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo obligations_format_money((int)($logRow['amount_input_cents'] ?? 0), $currency); ?></td>
                  <td><?php echo obligations_format_money((int)($logRow['amount_applied_cents'] ?? 0), $currency); ?></td>
                  <td><?php echo obligations_format_money((int)($logRow['paid_before_cents'] ?? 0), $currency); ?></td>
                  <td><?php echo obligations_format_money((int)($logRow['paid_after_cents'] ?? 0), $currency); ?></td>
                  <td><?php echo htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)($logRow['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var container = document.querySelector('[data-reservation-tabs="obligations"]');
  if (!container) return;
  var buttons = container.querySelectorAll('.reservation-tab-trigger[data-tab-target]');
  var panels = container.querySelectorAll('[data-tab-panel]');
  var mainExtras = container.ownerDocument.querySelectorAll('[data-obligations-main-extra]');

  function activateTab(targetId) {
    buttons.forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-tab-target') === targetId);
    });
    panels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.id === targetId);
    });
    mainExtras.forEach(function (block) {
      block.style.display = targetId === 'obligations-tab-main' ? '' : 'none';
    });
  }

  buttons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-tab-target');
      if (targetId) activateTab(targetId);
    });
  });

  var logTable = document.getElementById('obligation-log-table');
  var dateFrom = document.getElementById('obligation-log-date-from');
  var dateTo = document.getElementById('obligation-log-date-to');
  var method = document.getElementById('obligation-log-method');
  var mode = document.getElementById('obligation-log-mode');
  var search = document.getElementById('obligation-log-search');

  function applyLogFilters() {
    if (!logTable) return;
    var fromValue = dateFrom ? dateFrom.value : '';
    var toValue = dateTo ? dateTo.value : '';
    var methodValue = method ? (method.value || '').toLowerCase() : '';
    var modeValue = mode ? (mode.value || '').toUpperCase() : '';
    var searchValue = search ? (search.value || '').trim().toLowerCase() : '';
    var rows = logTable.querySelectorAll('tbody tr[data-log-date]');
    rows.forEach(function (row) {
      var rowDate = row.getAttribute('data-log-date') || '';
      var rowMethod = (row.getAttribute('data-log-method') || '').toLowerCase();
      var rowMode = (row.getAttribute('data-log-mode') || '').toUpperCase();
      var rowSearch = (row.getAttribute('data-log-search') || '').toLowerCase();
      var ok = true;
      if (fromValue && (!rowDate || rowDate < fromValue)) ok = false;
      if (toValue && (!rowDate || rowDate > toValue)) ok = false;
      if (methodValue && rowMethod !== methodValue) ok = false;
      if (modeValue && rowMode !== modeValue) ok = false;
      if (searchValue && rowSearch.indexOf(searchValue) === -1) ok = false;
      row.style.display = ok ? '' : 'none';
    });
  }

  [dateFrom, dateTo, method, mode, search].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input', applyLogFilters);
    el.addEventListener('change', applyLogFilters);
  });

  activateTab('obligations-tab-main');
  applyLogFilters();
});
</script>

<style>
.obligations-summary-table th:nth-child(7),
.obligations-summary-table td.obligation-concept-cell {
  max-width: 320px;
}

.obligations-summary-table td.obligation-concept-cell {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.obligation-row-actions {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 6px;
}

.obligation-row-actions .obligation-method-select {
  min-width: 120px;
  width: 120px;
}

.obligation-row-actions .obligation-notes-input {
  min-width: 140px;
  width: 180px;
}

.obligation-row-actions .obligation-amount-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}

.obligation-row-actions .obligation-amount-input {
  width: 82px;
}

@media (max-width: 1500px) {
  .obligation-row-actions {
    flex-wrap: wrap;
  }
}
</style>

<div data-obligations-main-extra>
<section class="card">
  <h3>Resumen</h3>
  <p class="muted">
    Registros: <?php echo (int)$summary['count']; ?>
    | Monto: <?php echo obligations_format_money($summary['amount_cents'], 'MXN'); ?>
    | Pagado: <?php echo obligations_format_money($summary['paid_cents'], 'MXN'); ?>
    | Pendiente: <?php echo obligations_format_money($summary['remaining_cents'], 'MXN'); ?>
    | Pendientes: <?php echo (int)$summary['pending']; ?>
    | Parciales: <?php echo (int)$summary['partial']; ?>
    | Pagadas: <?php echo (int)$summary['paid']; ?>
  </p>
  <?php
    $bulkDefaultMethodId = 0;
    if (!empty($obligationPaymentMethods) && isset($obligationPaymentMethods[0]['id_obligation_payment_method'])) {
        $bulkDefaultMethodId = (int)$obligationPaymentMethods[0]['id_obligation_payment_method'];
    }
    $bulkVisibleCount = 0;
    foreach ($rows as $bulkRow) {
        $bulkLineItemId = isset($bulkRow['id_line_item']) ? (int)$bulkRow['id_line_item'] : 0;
        $bulkAmountCents = isset($bulkRow['amount_cents']) ? (int)$bulkRow['amount_cents'] : 0;
        $bulkRemainingCents = isset($bulkRow['remaining_cents']) ? (int)$bulkRow['remaining_cents'] : 0;
        if ($bulkLineItemId > 0 && $bulkAmountCents > 0 && $bulkRemainingCents > 0) {
            $bulkVisibleCount++;
        }
    }
  ?>
  <form method="post" class="form-inline" style="gap:6px; flex-wrap:wrap; margin: 8px 0 12px 0;" onsubmit="return confirm('Se pagaran todas las obligaciones visibles. Deseas continuar?');">
    <?php obligations_render_filter_hidden_fields($filters); ?>
    <?php foreach ($rows as $bulkRow): ?>
      <?php
        $bulkLineItemId = isset($bulkRow['id_line_item']) ? (int)$bulkRow['id_line_item'] : 0;
        $bulkAmountCents = isset($bulkRow['amount_cents']) ? (int)$bulkRow['amount_cents'] : 0;
        $bulkRemainingCents = isset($bulkRow['remaining_cents']) ? (int)$bulkRow['remaining_cents'] : 0;
        if ($bulkLineItemId <= 0 || $bulkAmountCents <= 0 || $bulkRemainingCents <= 0) {
            continue;
        }
      ?>
      <input type="hidden" name="obligation_bulk_line_item_id[]" value="<?php echo $bulkLineItemId; ?>">
      <input type="hidden" name="obligation_bulk_amount_cents[]" value="<?php echo $bulkAmountCents; ?>">
    <?php endforeach; ?>
    <span class="muted">Visibles: <?php echo (int)$bulkVisibleCount; ?></span>
    <select name="obligation_payment_method_id" style="min-width:150px;" title="Metodo de pago" required <?php echo empty($obligationPaymentMethods) ? 'disabled' : ''; ?>>
      <option value="0">Metodo...</option>
      <?php foreach ($obligationPaymentMethods as $paymentMethod): ?>
        <?php
          $methodId = isset($paymentMethod['id_obligation_payment_method']) ? (int)$paymentMethod['id_obligation_payment_method'] : 0;
          if ($methodId <= 0) {
              continue;
          }
          $methodName = isset($paymentMethod['method_name']) ? (string)$paymentMethod['method_name'] : ('Metodo #' . $methodId);
        ?>
        <option value="<?php echo $methodId; ?>" <?php echo $methodId === $bulkDefaultMethodId ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8'); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="obligation_payment_notes" value="" maxlength="500" placeholder="Notas de pago para todas" style="min-width:190px;">
    <button type="submit" class="button-primary" name="obligations_action" value="apply_all" <?php echo (empty($obligationPaymentMethods) || $bulkVisibleCount <= 0) ? 'disabled' : ''; ?>>
      Pagar todas (visibles)
    </button>
  </form>

  <div class="table-scroll">
    <table class="obligations-summary-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha creacion</th>
          <th>Propiedad</th>
          <th>Reserva</th>
          <th>Folio</th>
          <th>Huesped</th>
          <th>Concepto</th>
          <th>Monto</th>
          <th>Pagado</th>
          <th>Pendiente</th>
          <th>Estado pago</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="12" class="muted">Sin obligaciones para los filtros seleccionados.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $lineItemId = isset($row['id_line_item']) ? (int)$row['id_line_item'] : 0;
              $amountCents = isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0;
              $paidCents = isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0;
              $remainingCents = isset($row['remaining_cents']) ? (int)$row['remaining_cents'] : 0;
              $currency = isset($row['currency']) && trim((string)$row['currency']) !== '' ? (string)$row['currency'] : 'MXN';
              $parentConcept = trim((string)($row['parent_concept_name'] ?? ''));
              $conceptLabel = isset($row['concept_display_name']) ? trim((string)$row['concept_display_name']) : '';
              if ($conceptLabel === '') {
                  $conceptLabel = trim((string)($row['catalog_item_name'] ?? ''));
                  if ($conceptLabel === '') {
                      $conceptLabel = trim((string)($row['category_name'] ?? '') . ' / ' . (string)($row['subcategory_name'] ?? '') . ' / ' . (string)($row['catalog_item_name'] ?? ''));
                  }
              }
              $conceptLabel = trim($conceptLabel, ' /');
              if ($conceptLabel === '') {
                  $conceptLabel = (string)($row['catalog_item_name'] ?? ('Obligacion #' . $lineItemId));
              }
              if ($parentConcept !== '' && strpos($conceptLabel, ' - ' . $parentConcept) === false) {
                  $conceptLabel .= ' - ' . $parentConcept;
              }
              $defaultAddValue = number_format(((float)$remainingCents) / 100, 2, '.', '');
              $defaultMethodId = 0;
              if (!empty($obligationPaymentMethods) && isset($obligationPaymentMethods[0]['id_obligation_payment_method'])) {
                  $defaultMethodId = (int)$obligationPaymentMethods[0]['id_obligation_payment_method'];
              }
            ?>
            <tr>
              <td><?php echo $lineItemId; ?></td>
              <td><?php echo htmlspecialchars((string)($row['obligation_date'] ?? ($row['service_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['property_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['reservation_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['folio_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)($row['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="obligation-concept-cell" title="<?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo obligations_format_money($amountCents, $currency); ?></td>
              <td><?php echo obligations_format_money($paidCents, $currency); ?></td>
              <td><?php echo obligations_format_money($remainingCents, $currency); ?></td>
              <td><?php echo htmlspecialchars((string)($row['payment_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <form method="post" class="form-inline obligation-row-actions">
                  <?php obligations_render_filter_hidden_fields($filters); ?>
                  <input type="hidden" name="obligation_line_item_id" value="<?php echo $lineItemId; ?>">
                  <input type="hidden" name="obligation_full_amount_cents" value="<?php echo $amountCents; ?>">
                  <select name="obligation_payment_method_id" class="obligation-method-select" title="Metodo de pago" required>
                    <option value="0">Metodo...</option>
                    <?php foreach ($obligationPaymentMethods as $paymentMethod): ?>
                      <?php
                        $methodId = isset($paymentMethod['id_obligation_payment_method']) ? (int)$paymentMethod['id_obligation_payment_method'] : 0;
                        if ($methodId <= 0) {
                            continue;
                        }
                        $methodName = isset($paymentMethod['method_name']) ? (string)$paymentMethod['method_name'] : ('Metodo #' . $methodId);
                      ?>
                      <option value="<?php echo $methodId; ?>" <?php echo $methodId === $defaultMethodId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($methodName, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="obligation_payment_notes" class="obligation-notes-input" value="" maxlength="500" placeholder="Notas de pago">
                  <span class="obligation-amount-wrap">
                    <input type="text" name="obligation_apply_amount" class="obligation-amount-input" value="<?php echo htmlspecialchars($defaultAddValue, ENT_QUOTES, 'UTF-8'); ?>" title="Monto a abonar">
                    <button type="submit" class="button-secondary" name="obligations_action" value="apply_add">Abonar</button>
                  </span>
                  <button type="submit" class="button-secondary" name="obligations_action" value="apply_set">Fijar pagado</button>
                  <button type="submit" class="button-primary" name="obligations_action" value="apply_full">Pago full</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
</div>
