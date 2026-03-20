<?php
$moduleKey = 'incomes';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
if (!function_exists('incomes_money')) {
    function incomes_money($cents, $currency)
    {
        return '$' . number_format(((int)$cents) / 100, 2, '.', ',') . ' ' . ($currency !== '' ? $currency : 'MXN');
    }
}
if (!function_exists('incomes_to_cents')) {
    function incomes_to_cents($raw)
    {
        $txt = str_replace(',', '', trim((string)$raw));
        if ($txt === '' || !is_numeric($txt)) {
            return 0;
        }
        return (int)round(((float)$txt) * 100);
    }
}
if (!function_exists('incomes_hidden_filters')) {
    function incomes_hidden_filters(array $filters)
    {
        foreach ($filters as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            echo '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
        }
    }
}
if (!function_exists('incomes_status')) {
    function incomes_status($amountCents, $paidCents)
    {
        $amountCents = (int)$amountCents;
        $paidCents = (int)$paidCents;
        if ($amountCents <= 0) return 'paid';
        if ($paidCents <= 0) return 'pending';
        if ($paidCents >= $amountCents) return 'paid';
        return 'partial';
    }
}
if (!function_exists('incomes_apply')) {
    function incomes_apply($companyId, $lineItemId, $mode, $inputCents, $methodId, $notes, $actorUserId)
    {
        $pdo = pms_get_connection();
        $mode = strtolower(trim((string)$mode));
        if (!in_array($mode, array('add', 'set'), true)) {
            throw new Exception('Modo invalido.');
        }
        if ((int)$lineItemId <= 0 || (int)$methodId <= 0) {
            throw new Exception('Datos invalidos para confirmar ingreso.');
        }
        $inputCents = max(0, (int)$inputCents);

        $tx = false;
        if (!$pdo->inTransaction()) {
            $tx = (bool)$pdo->beginTransaction();
        }
        try {
            $stmtMethod = $pdo->prepare(
                'SELECT id_obligation_payment_method
                   FROM pms_settings_obligation_payment_method
                  WHERE id_obligation_payment_method = ?
                    AND id_company = ?
                    AND deleted_at IS NULL
                    AND is_active = 1
                    AND COALESCE(method_description, "") LIKE "[scope:income]%"
                  LIMIT 1'
            );
            $stmtMethod->execute(array((int)$methodId, (int)$companyId));
            if ((int)$stmtMethod->fetchColumn() <= 0) {
                throw new Exception('Metodo de pago de ingresos invalido.');
            }

            $stmtLine = $pdo->prepare(
                'SELECT p.id_company AS id_company,
                        li.id_folio,
                        f.id_reservation,
                        COALESCE(li.amount_cents, 0) AS amount_cents,
                        COALESCE(li.paid_cents, 0) AS paid_cents
                   FROM line_item li
                   JOIN folio f ON f.id_folio = li.id_folio AND f.deleted_at IS NULL AND f.is_active = 1
                   JOIN reservation r ON r.id_reservation = f.id_reservation AND r.deleted_at IS NULL
                   JOIN property p ON p.id_property = r.id_property AND p.deleted_at IS NULL
                  WHERE li.id_line_item = ?
                    AND li.item_type = "income"
                    AND li.deleted_at IS NULL
                    AND li.is_active = 1
                  LIMIT 1
                  FOR UPDATE'
            );
            $stmtLine->execute(array((int)$lineItemId));
            $row = $stmtLine->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('No se encontro el ingreso seleccionado.');
            }
            if ((int)($row['id_company'] ?? 0) !== (int)$companyId) {
                throw new Exception('El ingreso no pertenece a la empresa actual.');
            }

            $amount = (int)($row['amount_cents'] ?? 0);
            $paidBefore = (int)($row['paid_cents'] ?? 0);
            $paidAfter = ($mode === 'set') ? $inputCents : ($paidBefore + $inputCents);
            $paidAfter = max(0, $paidAfter);
            if ($amount >= 0) {
                $paidAfter = min($paidAfter, $amount);
            }
            $applied = $paidAfter - $paidBefore;

            $stmtUpdate = $pdo->prepare('UPDATE line_item SET paid_cents = ?, updated_at = NOW() WHERE id_line_item = ? AND deleted_at IS NULL AND is_active = 1');
            $stmtUpdate->execute(array($paidAfter, (int)$lineItemId));

            if ($applied !== 0) {
                $stmtLog = $pdo->prepare(
                    'INSERT INTO obligation_payment_log (
                        id_company, id_line_item, id_folio, id_reservation,
                        id_obligation_payment_method, payment_mode,
                        amount_input_cents, amount_applied_cents,
                        paid_before_cents, paid_after_cents, notes, created_by
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmtLog->execute(array(
                    (int)$companyId,
                    (int)$lineItemId,
                    (int)$row['id_folio'],
                    (int)$row['id_reservation'],
                    (int)$methodId,
                    $mode,
                    $inputCents,
                    $applied,
                    $paidBefore,
                    $paidAfter,
                    (trim((string)$notes) !== '' ? trim((string)$notes) : null),
                    $actorUserId
                ));
            }

            pms_call_procedure('sp_folio_recalc', array((int)$row['id_folio']));
            if ($tx && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return array(
                'amount_cents' => $amount,
                'paid_cents' => $paidAfter,
                'remaining_cents' => max(0, $amount - $paidAfter)
            );
        } catch (Exception $e) {
            if ($tx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

$properties = pms_fetch_properties($companyId);
$filters = array(
    'incomes_property' => isset($_POST['incomes_property']) ? strtoupper(trim((string)$_POST['incomes_property'])) : '',
    'incomes_from' => isset($_POST['incomes_from']) ? trim((string)$_POST['incomes_from']) : '',
    'incomes_to' => isset($_POST['incomes_to']) ? trim((string)$_POST['incomes_to']) : '',
    'incomes_search' => isset($_POST['incomes_search']) ? trim((string)$_POST['incomes_search']) : '',
    'incomes_payment_status' => isset($_POST['incomes_payment_status']) ? strtolower(trim((string)$_POST['incomes_payment_status'])) : '',
    'incomes_reservation_id' => isset($_POST['incomes_reservation_id']) ? (int)$_POST['incomes_reservation_id'] : 0,
    'incomes_folio_id' => isset($_POST['incomes_folio_id']) ? (int)$_POST['incomes_folio_id'] : 0,
    'incomes_show_inactive' => isset($_POST['incomes_show_inactive']) ? 1 : 0,
    'incomes_limit' => isset($_POST['incomes_limit']) ? (int)$_POST['incomes_limit'] : 500
);
if (isset($_POST['incomes_reset']) && (string)$_POST['incomes_reset'] === '1') {
    $filters = array(
        'incomes_property' => '',
        'incomes_from' => '',
        'incomes_to' => '',
        'incomes_search' => '',
        'incomes_payment_status' => '',
        'incomes_reservation_id' => 0,
        'incomes_folio_id' => 0,
        'incomes_show_inactive' => 0,
        'incomes_limit' => 500
    );
}
if (!in_array($filters['incomes_payment_status'], array('', 'pending', 'partial', 'paid'), true)) {
    $filters['incomes_payment_status'] = '';
}
if (!in_array($filters['incomes_limit'], array(200, 500, 1000, 2000), true)) {
    $filters['incomes_limit'] = 500;
}

$message = null;
$error = null;
$incomePaymentMethods = array();
try {
    $pdo = pms_get_connection();
    $stmtMethods = $pdo->prepare(
        'SELECT id_obligation_payment_method, method_name, COALESCE(method_description, "") AS method_description
           FROM pms_settings_obligation_payment_method
          WHERE id_company = ?
            AND deleted_at IS NULL
            AND is_active = 1
            AND COALESCE(method_description, "") LIKE "[scope:income]%"
          ORDER BY method_name, id_obligation_payment_method'
    );
    $stmtMethods->execute(array($companyId));
    $incomePaymentMethods = $stmtMethods->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'No fue posible cargar metodos de ingresos: ' . $e->getMessage();
}

if (isset($_POST['incomes_action']) && in_array((string)$_POST['incomes_action'], array('apply_add', 'apply_set', 'apply_full', 'apply_all'), true)) {
    $action = (string)$_POST['incomes_action'];
    $methodId = isset($_POST['income_payment_method_id']) ? (int)$_POST['income_payment_method_id'] : 0;
    $notes = isset($_POST['income_payment_notes']) ? trim((string)$_POST['income_payment_notes']) : '';

    if ($action === 'apply_all') {
        $idsRaw = isset($_POST['income_bulk_line_item_id']) ? $_POST['income_bulk_line_item_id'] : array();
        $amountsRaw = isset($_POST['income_bulk_amount_cents']) ? $_POST['income_bulk_amount_cents'] : array();
        $ids = is_array($idsRaw) ? $idsRaw : array($idsRaw);
        $amounts = is_array($amountsRaw) ? $amountsRaw : array($amountsRaw);
        $map = array();
        foreach ($ids as $i => $idRaw) {
            $id = (int)$idRaw;
            $amt = isset($amounts[$i]) ? (int)$amounts[$i] : 0;
            if ($id <= 0 || $amt < 0) {
                continue;
            }
            if (!isset($map[$id]) || $amt > $map[$id]) {
                $map[$id] = $amt;
            }
        }
        if ($methodId <= 0) {
            $error = 'Selecciona un metodo para confirmar ingresos visibles.';
        } elseif (!$map) {
            $error = 'No hay ingresos visibles validos para confirmar.';
        } else {
            $ok = 0;
            try {
                foreach ($map as $lineId => $fullAmount) {
                    incomes_apply($companyId, $lineId, 'set', $fullAmount, $methodId, $notes, $actorUserId);
                    $ok++;
                }
                $message = 'Confirmacion total aplicada a ' . $ok . ' ingresos visibles.';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        $lineItemId = isset($_POST['income_line_item_id']) ? (int)$_POST['income_line_item_id'] : 0;
        $mode = ($action === 'apply_add') ? 'add' : 'set';
        $amountCents = ($action === 'apply_full')
            ? (int)(isset($_POST['income_full_amount_cents']) ? $_POST['income_full_amount_cents'] : 0)
            : incomes_to_cents(isset($_POST['income_apply_amount']) ? $_POST['income_apply_amount'] : '');
        if ($amountCents < 0) {
            $amountCents = 0;
        }
        if ($lineItemId <= 0) {
            $error = 'Selecciona un ingreso valido.';
        } elseif ($methodId <= 0) {
            $error = 'Selecciona un metodo de pago.';
        } elseif ($action === 'apply_add' && $amountCents <= 0) {
            $error = 'Indica un monto valido para abonar.';
        } else {
            try {
                $updated = incomes_apply($companyId, $lineItemId, $mode, $amountCents, $methodId, $notes, $actorUserId);
                $message = 'Ingreso actualizado. Confirmado: '
                    . incomes_money((int)$updated['paid_cents'], 'MXN')
                    . ' | Pendiente: '
                    . incomes_money((int)$updated['remaining_cents'], 'MXN');
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$rows = array();
$summary = array('count' => 0, 'amount_cents' => 0, 'paid_cents' => 0, 'remaining_cents' => 0, 'pending' => 0, 'partial' => 0, 'paid' => 0);
try {
    $pdo = pms_get_connection();
    $sql = 'SELECT
              li.id_line_item,
              li.id_folio,
              li.id_line_item_catalog,
              li.description,
              li.service_date,
              li.created_at,
              COALESCE(li.amount_cents,0) AS amount_cents,
              COALESCE(li.paid_cents,0) AS paid_cents,
              GREATEST(COALESCE(li.amount_cents,0)-COALESCE(li.paid_cents,0),0) AS remaining_cents,
              COALESCE(li.currency, "MXN") AS currency,
              CASE
                WHEN COALESCE(li.amount_cents,0) <= 0 THEN "paid"
                WHEN COALESCE(li.paid_cents,0) <= 0 THEN "pending"
                WHEN COALESCE(li.paid_cents,0) >= COALESCE(li.amount_cents,0) THEN "paid"
                ELSE "partial"
              END AS payment_status,
              f.folio_name,
              r.id_reservation,
              r.code AS reservation_code,
              p.code AS property_code,
              TRIM(CONCAT_WS(" ", COALESCE(g.names, ""), COALESCE(g.last_name, ""), COALESCE(g.maiden_name, ""))) AS guest_name,
              COALESCE(g.email, "") AS guest_email,
              COALESCE(lic.item_name, "") AS catalog_item_name
            FROM line_item li
            JOIN folio f ON f.id_folio = li.id_folio
            JOIN reservation r ON r.id_reservation = f.id_reservation
            JOIN property p ON p.id_property = r.id_property
            LEFT JOIN guest g ON g.id_guest = r.id_guest
            LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = li.id_line_item_catalog
            WHERE p.id_company = ?
              AND li.item_type = "income"
              AND r.deleted_at IS NULL';
    $params = array($companyId);

    if ($filters['incomes_property'] !== '') { $sql .= ' AND p.code = ?'; $params[] = $filters['incomes_property']; }
    if (empty($filters['incomes_show_inactive'])) {
        $sql .= ' AND li.deleted_at IS NULL AND li.is_active = 1 AND (li.status IS NULL OR li.status NOT IN ("void","canceled")) AND f.deleted_at IS NULL AND f.is_active = 1';
    }
    if ($filters['incomes_reservation_id'] > 0) { $sql .= ' AND r.id_reservation = ?'; $params[] = $filters['incomes_reservation_id']; }
    if ($filters['incomes_folio_id'] > 0) { $sql .= ' AND li.id_folio = ?'; $params[] = $filters['incomes_folio_id']; }
    if ($filters['incomes_from'] !== '') { $sql .= ' AND COALESCE(li.service_date, DATE(li.created_at)) >= ?'; $params[] = $filters['incomes_from']; }
    if ($filters['incomes_to'] !== '') { $sql .= ' AND COALESCE(li.service_date, DATE(li.created_at)) <= ?'; $params[] = $filters['incomes_to']; }
    if ($filters['incomes_payment_status'] === 'pending') {
        $sql .= ' AND COALESCE(li.amount_cents,0) > 0 AND COALESCE(li.paid_cents,0) <= 0';
    } elseif ($filters['incomes_payment_status'] === 'partial') {
        $sql .= ' AND COALESCE(li.amount_cents,0) > 0 AND COALESCE(li.paid_cents,0) > 0 AND COALESCE(li.paid_cents,0) < COALESCE(li.amount_cents,0)';
    } elseif ($filters['incomes_payment_status'] === 'paid') {
        $sql .= ' AND (COALESCE(li.amount_cents,0) <= 0 OR COALESCE(li.paid_cents,0) >= COALESCE(li.amount_cents,0))';
    }
    if ($filters['incomes_search'] !== '') {
        $sql .= ' AND (
            r.code LIKE ? OR f.folio_name LIKE ? OR COALESCE(g.names, "") LIKE ? OR COALESCE(g.last_name, "") LIKE ?
            OR COALESCE(g.maiden_name, "") LIKE ? OR COALESCE(g.email, "") LIKE ? OR COALESCE(lic.item_name, "") LIKE ?
            OR COALESCE(li.description, "") LIKE ? OR COALESCE(p.code, "") LIKE ?
        )';
        $needle = '%' . $filters['incomes_search'] . '%';
        for ($i = 0; $i < 9; $i++) { $params[] = $needle; }
    }

    $sql .= ' ORDER BY li.created_at DESC, li.id_line_item DESC LIMIT ' . (int)$filters['incomes_limit'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if ($error === null) { $error = $e->getMessage(); }
}

foreach ($rows as $r) {
    $summary['count']++;
    $summary['amount_cents'] += (int)($r['amount_cents'] ?? 0);
    $summary['paid_cents'] += (int)($r['paid_cents'] ?? 0);
    $summary['remaining_cents'] += (int)($r['remaining_cents'] ?? 0);
    $st = (string)($r['payment_status'] ?? '');
    if ($st === 'pending') $summary['pending']++;
    elseif ($st === 'partial') $summary['partial']++;
    else $summary['paid']++;
}

$incomePaymentLogRows = array();
$incomePaymentLogError = null;
try {
    $pdo = pms_get_connection();
    $ids = array();
    foreach ($rows as $r) {
        $id = (int)($r['id_line_item'] ?? 0);
        if ($id > 0) $ids[$id] = true;
    }
    if ($ids) {
        $idList = implode(',', array_keys($ids));
        $stmt = $pdo->prepare(
            'SELECT opl.id_obligation_payment_log, opl.id_line_item, opl.created_at, opl.payment_mode,
                    opl.amount_input_cents, opl.amount_applied_cents, opl.paid_before_cents, opl.paid_after_cents,
                    opl.notes, COALESCE(pm.method_name, "") AS payment_method_name,
                    COALESCE(r.code, "") AS reservation_code, COALESCE(f.folio_name, "") AS folio_name,
                    COALESCE(p.code, "") AS property_code,
                    TRIM(CONCAT_WS(" ", COALESCE(au.names, ""), COALESCE(au.last_name, ""))) AS actor_name,
                    COALESCE(au.email, "") AS actor_email
               FROM obligation_payment_log opl
               LEFT JOIN pms_settings_obligation_payment_method pm ON pm.id_obligation_payment_method = opl.id_obligation_payment_method
               LEFT JOIN reservation r ON r.id_reservation = opl.id_reservation
               LEFT JOIN folio f ON f.id_folio = opl.id_folio
               LEFT JOIN property p ON p.id_property = r.id_property
               LEFT JOIN app_user au ON au.id_user = opl.created_by
              WHERE opl.id_company = ? AND opl.id_line_item IN (' . $idList . ')
              ORDER BY opl.created_at DESC, opl.id_obligation_payment_log DESC
              LIMIT 300'
        );
        $stmt->execute(array($companyId));
        $incomePaymentLogRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $incomePaymentLogError = 'No fue posible cargar historial: ' . $e->getMessage();
}
?>
<div class="reservation-tabs" data-reservation-tabs="incomes">
  <div class="reservation-tab-nav">
    <button type="button" class="reservation-tab-trigger is-active" data-tab-target="incomes-main">Ingresos</button>
    <button type="button" class="reservation-tab-trigger" data-tab-target="incomes-history">Historial</button>
  </div>

  <div class="reservation-tab-panel is-active" id="incomes-main" data-tab-panel>
    <section class="card">
      <h2>Ingresos</h2>
      <p class="muted">Filtra line items tipo ingreso y confirma montos parciales o totales.</p>
      <?php if ($message !== null): ?><p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($error !== null): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if (!$error && !$incomePaymentMethods): ?><p class="muted">Configura metodos en Configuraciones > Metodos de pago de ingresos.</p><?php endif; ?>

      <form method="post">
        <div class="form-inline">
          <label>Propiedad
            <select name="incomes_property">
              <option value="">(Todas)</option>
              <?php foreach ($properties as $p): $code = (string)($p['code'] ?? ''); ?>
                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['incomes_property'] === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($code . ' - ' . (string)($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Desde<input type="date" name="incomes_from" value="<?php echo htmlspecialchars($filters['incomes_from'], ENT_QUOTES, 'UTF-8'); ?>"></label>
          <label>Hasta<input type="date" name="incomes_to" value="<?php echo htmlspecialchars($filters['incomes_to'], ENT_QUOTES, 'UTF-8'); ?>"></label>
          <label>Estado
            <select name="incomes_payment_status">
              <option value="">(Todos)</option>
              <option value="pending" <?php echo $filters['incomes_payment_status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
              <option value="partial" <?php echo $filters['incomes_payment_status'] === 'partial' ? 'selected' : ''; ?>>Parcial</option>
              <option value="paid" <?php echo $filters['incomes_payment_status'] === 'paid' ? 'selected' : ''; ?>>Confirmado</option>
            </select>
          </label>
          <label>Buscar<input type="text" name="incomes_search" value="<?php echo htmlspecialchars($filters['incomes_search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Reserva, huesped, folio, concepto"></label>
          <label>Reserva ID<input type="number" min="0" name="incomes_reservation_id" value="<?php echo (int)$filters['incomes_reservation_id']; ?>"></label>
          <label>Folio ID<input type="number" min="0" name="incomes_folio_id" value="<?php echo (int)$filters['incomes_folio_id']; ?>"></label>
          <label>Limite
            <select name="incomes_limit">
              <?php foreach (array(200, 500, 1000, 2000) as $l): ?>
                <option value="<?php echo $l; ?>" <?php echo (int)$filters['incomes_limit'] === $l ? 'selected' : ''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="inline"><input type="checkbox" name="incomes_show_inactive" value="1" <?php echo !empty($filters['incomes_show_inactive']) ? 'checked' : ''; ?>> Mostrar inactivos</label>
          <button type="submit" class="button-primary">Filtrar</button>
          <button type="submit" class="button-secondary" name="incomes_reset" value="1">Limpiar</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h3>Resumen</h3>
      <p class="muted">
        Registros: <?php echo (int)$summary['count']; ?> |
        Monto: <?php echo incomes_money($summary['amount_cents'], 'MXN'); ?> |
        Confirmado: <?php echo incomes_money($summary['paid_cents'], 'MXN'); ?> |
        Pendiente: <?php echo incomes_money($summary['remaining_cents'], 'MXN'); ?> |
        Pendientes: <?php echo (int)$summary['pending']; ?> |
        Parciales: <?php echo (int)$summary['partial']; ?> |
        Confirmados: <?php echo (int)$summary['paid']; ?>
      </p>

      <?php
      $bulkMethod = !empty($incomePaymentMethods[0]['id_obligation_payment_method']) ? (int)$incomePaymentMethods[0]['id_obligation_payment_method'] : 0;
      $bulkVisible = 0;
      foreach ($rows as $br) {
          $lid = (int)($br['id_line_item'] ?? 0);
          $amt = (int)($br['amount_cents'] ?? 0);
          $rem = (int)($br['remaining_cents'] ?? 0);
          if ($lid > 0 && $amt > 0 && $rem > 0) { $bulkVisible++; }
      }
      ?>
      <form method="post" class="form-inline" style="gap:6px;flex-wrap:wrap;margin:8px 0 12px 0;" onsubmit="return confirm('Se confirmaran todos los ingresos visibles. Deseas continuar?');">
        <?php incomes_hidden_filters($filters); ?>
        <?php foreach ($rows as $br): $lid=(int)($br['id_line_item']??0); $amt=(int)($br['amount_cents']??0); $rem=(int)($br['remaining_cents']??0); if($lid<=0||$amt<=0||$rem<=0) continue; ?>
          <input type="hidden" name="income_bulk_line_item_id[]" value="<?php echo $lid; ?>">
          <input type="hidden" name="income_bulk_amount_cents[]" value="<?php echo $amt; ?>">
        <?php endforeach; ?>
        <span class="muted">Visibles: <?php echo (int)$bulkVisible; ?></span>
        <select name="income_payment_method_id" style="min-width:150px;" required <?php echo empty($incomePaymentMethods) ? 'disabled' : ''; ?>>
          <option value="0">Metodo...</option>
          <?php foreach ($incomePaymentMethods as $pm): $pid=(int)($pm['id_obligation_payment_method']??0); if($pid<=0) continue; ?>
            <option value="<?php echo $pid; ?>" <?php echo $pid === $bulkMethod ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($pm['method_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="income_payment_notes" value="" maxlength="500" placeholder="Notas para todas" style="min-width:190px;">
        <button type="submit" class="button-primary" name="incomes_action" value="apply_all" <?php echo (empty($incomePaymentMethods) || $bulkVisible<=0) ? 'disabled' : ''; ?>>Confirmar todas (visibles)</button>
      </form>

      <div class="table-scroll">
        <table class="incomes-summary-table">
          <thead><tr><th>ID</th><th>Fecha</th><th>Propiedad</th><th>Reserva</th><th>Folio</th><th>Huesped</th><th>Concepto</th><th>Monto</th><th>Confirmado</th><th>Pendiente</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="12" class="muted">Sin ingresos para los filtros seleccionados.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row):
                $lineItemId=(int)($row['id_line_item']??0);
                $amountCents=(int)($row['amount_cents']??0);
                $paidCents=(int)($row['paid_cents']??0);
                $remainingCents=(int)($row['remaining_cents']??0);
                $currency=trim((string)($row['currency']??'')); if($currency==='') $currency='MXN';
                $concept=trim((string)($row['catalog_item_name']??'')); if($concept==='') $concept=trim((string)($row['description']??'')); if($concept==='') $concept='Ingreso #'.$lineItemId;
                $guest=trim((string)($row['guest_name']??'')); if($guest==='') $guest=trim((string)($row['guest_email']??'')); if($guest==='') $guest='Huesped sin nombre';
                $defaultAddValue=number_format(((float)$remainingCents)/100,2,'.','');
                $defaultMethod=!empty($incomePaymentMethods[0]['id_obligation_payment_method']) ? (int)$incomePaymentMethods[0]['id_obligation_payment_method'] : 0;
              ?>
              <tr>
                <td><?php echo $lineItemId; ?></td>
                <td><?php echo htmlspecialchars((string)($row['service_date'] ?? ($row['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['property_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><a href="index.php?view=reservations&amp;open_reservation=<?php echo (int)($row['id_reservation'] ?? 0); ?>"><?php echo htmlspecialchars((string)($row['reservation_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><?php echo htmlspecialchars((string)($row['folio_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($guest, ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="income-concept-cell" title="<?php echo htmlspecialchars($concept, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($concept, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo incomes_money($amountCents, $currency); ?></td>
                <td><?php echo incomes_money($paidCents, $currency); ?></td>
                <td><?php echo incomes_money($remainingCents, $currency); ?></td>
                <td><?php echo htmlspecialchars((string)($row['payment_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <form method="post" class="form-inline income-row-actions">
                    <?php incomes_hidden_filters($filters); ?>
                    <input type="hidden" name="income_line_item_id" value="<?php echo $lineItemId; ?>">
                    <input type="hidden" name="income_full_amount_cents" value="<?php echo $amountCents; ?>">
                    <select name="income_payment_method_id" class="income-method-select" required>
                      <option value="0">Metodo...</option>
                      <?php foreach ($incomePaymentMethods as $pm): $pid=(int)($pm['id_obligation_payment_method']??0); if($pid<=0) continue; ?>
                        <option value="<?php echo $pid; ?>" <?php echo $pid === $defaultMethod ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($pm['method_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" name="income_payment_notes" class="income-notes-input" value="" maxlength="500" placeholder="Notas">
                    <span class="income-amount-wrap">
                      <input type="text" name="income_apply_amount" class="income-amount-input" value="<?php echo htmlspecialchars($defaultAddValue, ENT_QUOTES, 'UTF-8'); ?>">
                      <button type="submit" class="button-secondary" name="incomes_action" value="apply_add" onclick="return confirm('Se aplicara un abono al ingreso. Deseas continuar?');">Abonar</button>
                    </span>
                    <button type="submit" class="button-secondary" name="incomes_action" value="apply_set" onclick="return confirm('Se fijara el total confirmado. Deseas continuar?');">Fijar</button>
                    <button type="submit" class="button-primary" name="incomes_action" value="apply_full" onclick="return confirm('Se confirmara el total del ingreso. Deseas continuar?');">Full</button>
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

  <div class="reservation-tab-panel" id="incomes-history" data-tab-panel>
    <section class="card">
      <h3>Historial de confirmaciones</h3>
      <p class="muted">Detalle de movimientos registrados sobre ingresos visibles.</p>
      <?php if ($incomePaymentLogError !== null): ?><p class="error"><?php echo htmlspecialchars($incomePaymentLogError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Fecha</th><th>ID mov</th><th>Ingreso</th><th>Propiedad</th><th>Reserva</th><th>Folio</th><th>Metodo</th><th>Tipo</th><th>Monto</th><th>Aplicado</th><th>Antes</th><th>Despues</th><th>Usuario</th><th>Notas</th></tr></thead>
          <tbody>
            <?php if (!$incomePaymentLogRows): ?>
              <tr><td colspan="14" class="muted">Aun no hay confirmaciones registradas para los ingresos visibles.</td></tr>
            <?php else: ?>
              <?php foreach ($incomePaymentLogRows as $log):
                $actor=trim((string)($log['actor_name']??'')); if($actor==='') $actor=trim((string)($log['actor_email']??''));
                if($actor==='') $actor='-';
              ?>
              <tr>
                <td><?php echo htmlspecialchars((string)($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int)($log['id_obligation_payment_log'] ?? 0); ?></td>
                <td><?php echo (int)($log['id_line_item'] ?? 0); ?></td>
                <td><?php echo htmlspecialchars((string)($log['property_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($log['reservation_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($log['folio_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($log['payment_method_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(strtoupper((string)($log['payment_mode'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo incomes_money((int)($log['amount_input_cents'] ?? 0), 'MXN'); ?></td>
                <td><?php echo incomes_money((int)($log['amount_applied_cents'] ?? 0), 'MXN'); ?></td>
                <td><?php echo incomes_money((int)($log['paid_before_cents'] ?? 0), 'MXN'); ?></td>
                <td><?php echo incomes_money((int)($log['paid_after_cents'] ?? 0), 'MXN'); ?></td>
                <td><?php echo htmlspecialchars($actor, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($log['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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
  var container = document.querySelector('[data-reservation-tabs="incomes"]');
  if (!container) return;
  var buttons = container.querySelectorAll('.reservation-tab-trigger[data-tab-target]');
  var panels = container.querySelectorAll('[data-tab-panel]');
  function activateTab(targetId) {
    buttons.forEach(function (btn) { btn.classList.toggle('is-active', btn.getAttribute('data-tab-target') === targetId); });
    panels.forEach(function (panel) { panel.classList.toggle('is-active', panel.id === targetId); });
  }
  buttons.forEach(function (btn) { btn.addEventListener('click', function () { var t = btn.getAttribute('data-tab-target'); if (t) activateTab(t); }); });
  activateTab('incomes-main');
});
</script>

<style>
.incomes-summary-table th:nth-child(7), .incomes-summary-table td.income-concept-cell { max-width: 320px; }
.incomes-summary-table td.income-concept-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.income-row-actions { display:flex; align-items:center; flex-wrap:nowrap; gap:6px; }
.income-row-actions .income-method-select { min-width:120px; width:120px; }
.income-row-actions .income-notes-input { min-width:140px; width:170px; }
.income-row-actions .income-amount-wrap { display:inline-flex; align-items:center; gap:6px; white-space:nowrap; }
.income-row-actions .income-amount-input { width:82px; }
@media (max-width:1500px) { .income-row-actions { flex-wrap:wrap; } }
</style>
