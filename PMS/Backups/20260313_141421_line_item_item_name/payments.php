<?php
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
if ($companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('payments.view');

if (!function_exists('payments_format_money')) {
    function payments_format_money($cents, $currency)
    {
        $amount = ((int)$cents) / 100;
        return '$' . number_format($amount, 2, '.', ',') . ' ' . ($currency !== '' ? $currency : 'MXN');
    }
}

if (!function_exists('payments_format_datetime')) {
    function payments_format_datetime($value)
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('d/m/Y H:i', $ts);
    }
}

if (!function_exists('payments_format_date')) {
    function payments_format_date($value)
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('d/m/Y', $ts);
    }
}

$properties = pms_fetch_properties($companyId);

$filters = array(
    'property_code' => isset($_POST['payments_property']) ? strtoupper(trim((string)$_POST['payments_property'])) : '',
    'date_field' => isset($_POST['payments_date_field']) ? trim((string)$_POST['payments_date_field']) : 'created_at',
    'date_from' => isset($_POST['payments_from']) ? trim((string)$_POST['payments_from']) : '',
    'date_to' => isset($_POST['payments_to']) ? trim((string)$_POST['payments_to']) : '',
    'method' => isset($_POST['payments_method']) ? trim((string)$_POST['payments_method']) : '',
    'status' => isset($_POST['payments_status']) ? trim((string)$_POST['payments_status']) : '',
    'currency' => isset($_POST['payments_currency']) ? strtoupper(trim((string)$_POST['payments_currency'])) : '',
    'reservation_id' => isset($_POST['payments_reservation_id']) ? (int)$_POST['payments_reservation_id'] : 0,
    'search' => isset($_POST['payments_search']) ? trim((string)$_POST['payments_search']) : '',
    'show_inactive' => isset($_POST['payments_show_inactive']) ? 1 : 0,
    'limit' => isset($_POST['payments_limit']) ? (int)$_POST['payments_limit'] : 200
);

if (isset($_POST['payments_reset']) && (string)$_POST['payments_reset'] === '1') {
    $filters = array(
        'property_code' => '',
        'date_field' => 'created_at',
        'date_from' => '',
        'date_to' => '',
        'method' => '',
        'status' => '',
        'currency' => '',
        'reservation_id' => 0,
        'search' => '',
        'show_inactive' => 0,
        'limit' => 200
    );
}

if (!in_array($filters['date_field'], array('created_at', 'service_date'), true)) {
    $filters['date_field'] = 'created_at';
}
if (!in_array($filters['limit'], array(100, 200, 500, 1000), true)) {
    $filters['limit'] = 200;
}

$rows = array();
$methodOptions = array();
$statusOptions = array();
$error = '';

try {
    $pdo = pms_get_connection();

    $baseSql = " FROM line_item li
        JOIN folio f ON f.id_folio = li.id_folio
        JOIN reservation r ON r.id_reservation = f.id_reservation
        JOIN property p ON p.id_property = r.id_property
        LEFT JOIN guest g ON g.id_guest = r.id_guest
        LEFT JOIN room rm ON rm.id_room = r.id_room
        LEFT JOIN line_item_catalog lic ON lic.id_line_item_catalog = li.id_line_item_catalog
        WHERE p.id_company = ?
          AND li.item_type = 'payment'
          AND r.deleted_at IS NULL
          AND r.is_active = 1";

    $baseParams = array($companyId);

    if ($filters['property_code'] !== '') {
        $baseSql .= ' AND p.code = ?';
        $baseParams[] = $filters['property_code'];
    }

    if (empty($filters['show_inactive'])) {
        $baseSql .= ' AND li.deleted_at IS NULL
                      AND li.is_active = 1
                      AND f.deleted_at IS NULL
                      AND f.is_active = 1';
    }

    $sqlMethods = 'SELECT DISTINCT TRIM(li.method) AS method_value ' . $baseSql . " AND COALESCE(TRIM(li.method), '') <> '' ORDER BY method_value";
    $stmtMethods = $pdo->prepare($sqlMethods);
    $stmtMethods->execute($baseParams);
    $methodOptions = $stmtMethods->fetchAll(PDO::FETCH_COLUMN);

    $sqlStatus = 'SELECT DISTINCT TRIM(li.status) AS status_value ' . $baseSql . " AND COALESCE(TRIM(li.status), '') <> '' ORDER BY status_value";
    $stmtStatus = $pdo->prepare($sqlStatus);
    $stmtStatus->execute($baseParams);
    $statusOptions = $stmtStatus->fetchAll(PDO::FETCH_COLUMN);

    $sql = 'SELECT
              li.id_line_item,
              li.id_folio,
              li.id_line_item_catalog,
              li.method,
              li.reference,
              li.description,
              li.service_date,
              li.created_at,
              li.amount_cents,
              COALESCE(li.paid_cents, 0) AS paid_cents,
              li.currency,
              li.status,
              li.is_active,
              li.deleted_at,
              f.folio_name,
              f.status AS folio_status,
              r.id_reservation,
              r.code AS reservation_code,
              r.status AS reservation_status,
              r.source AS reservation_source,
              p.code AS property_code,
              p.name AS property_name,
              g.names AS guest_names,
              g.last_name AS guest_last_name,
              g.maiden_name AS guest_maiden_name,
              g.email AS guest_email,
              rm.code AS room_code,
              rm.name AS room_name,
              lic.item_name AS catalog_item_name
            ' . $baseSql;

    $params = $baseParams;

    if ($filters['reservation_id'] > 0) {
        $sql .= ' AND r.id_reservation = ?';
        $params[] = $filters['reservation_id'];
    }
    if ($filters['method'] !== '') {
        $sql .= ' AND li.method = ?';
        $params[] = $filters['method'];
    }
    if ($filters['status'] !== '') {
        $sql .= ' AND li.status = ?';
        $params[] = $filters['status'];
    }
    if ($filters['currency'] !== '') {
        $sql .= " AND UPPER(COALESCE(li.currency, '')) = ?";
        $params[] = $filters['currency'];
    }

    $dateExpr = $filters['date_field'] === 'service_date'
        ? 'COALESCE(li.service_date, DATE(li.created_at))'
        : 'DATE(li.created_at)';
    if ($filters['date_from'] !== '') {
        $sql .= ' AND ' . $dateExpr . ' >= ?';
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $sql .= ' AND ' . $dateExpr . ' <= ?';
        $params[] = $filters['date_to'];
    }

    if ($filters['search'] !== '') {
        $sql .= " AND (
            r.code LIKE ?
            OR f.folio_name LIKE ?
            OR COALESCE(g.names, '') LIKE ?
            OR COALESCE(g.last_name, '') LIKE ?
            OR COALESCE(g.maiden_name, '') LIKE ?
            OR COALESCE(g.email, '') LIKE ?
            OR COALESCE(rm.code, '') LIKE ?
            OR COALESCE(rm.name, '') LIKE ?
            OR COALESCE(lic.item_name, '') LIKE ?
            OR COALESCE(li.description, '') LIKE ?
            OR COALESCE(li.reference, '') LIKE ?
            OR COALESCE(li.method, '') LIKE ?
            OR COALESCE(p.code, '') LIKE ?
            OR COALESCE(p.name, '') LIKE ?
        )";
        $needle = '%' . $filters['search'] . '%';
        for ($i = 0; $i < 14; $i++) {
            $params[] = $needle;
        }
    }

    $sql .= ' ORDER BY li.created_at DESC, li.id_line_item DESC';
    $sql .= ' LIMIT ' . (int)$filters['limit'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
    $rows = array();
}

$totalAmountCents = 0;
$totalPaidCents = 0;
foreach ($rows as $row) {
    $totalAmountCents += isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0;
    $totalPaidCents += isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0;
}

$avgAmountCents = count($rows) > 0 ? (int)round($totalAmountCents / count($rows)) : 0;
?>

<style>
.payments-filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 10px;
}

.payments-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.payments-summary span {
  display: inline-flex;
  align-items: center;
  padding: 4px 9px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.55);
  font-size: 0.84rem;
}

.payments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
  margin-top: 12px;
}

.payments-card {
  border: 1px solid rgba(148, 163, 184, 0.26);
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.52);
  padding: 12px;
  display: grid;
  gap: 9px;
}

.payments-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.payments-card-date {
  color: #cbd5f5;
  font-size: 0.8rem;
}

.payments-card-amount {
  font-weight: 700;
  font-size: 1rem;
}

.payments-card-amount.is-negative {
  color: #fda4af;
}

.payments-card-amount.is-positive {
  color: #86efac;
}

.payments-res-link {
  display: block;
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 10px;
  background: rgba(8, 47, 73, 0.35);
  padding: 8px 10px;
}

.payments-res-primary {
  display: block;
  color: #e2e8f0;
  font-weight: 600;
  line-height: 1.2;
}

.payments-res-secondary {
  display: block;
  margin-top: 4px;
  color: #93c5fd;
  font-size: 0.82rem;
  line-height: 1.2;
}

.payments-meta-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.payments-meta-item {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 9px;
  padding: 6px 8px;
  background: rgba(15, 23, 42, 0.42);
}

.payments-meta-label {
  display: block;
  color: #94a3b8;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.payments-meta-value {
  display: block;
  margin-top: 2px;
  color: #e2e8f0;
  font-size: 0.86rem;
  line-height: 1.2;
  word-break: break-word;
}

.payments-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
}

@media (max-width: 760px) {
  .payments-grid {
    grid-template-columns: 1fr;
  }

  .payments-meta-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<section class="card">
  <h2>Pagos</h2>
  <p class="muted">Visualiza line items tipo pago con su informacion clave y filtros rapidos.</p>

  <?php if ($error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <form method="post">
    <div class="payments-filter-grid">
      <label>
        Propiedad
        <select name="payments_property">
          <option value="">(Todas)</option>
          <?php foreach ($properties as $prop): ?>
            <?php $code = isset($prop['code']) ? (string)$prop['code'] : ''; ?>
            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['property_code'] === $code ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($code . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Campo de fecha
        <select name="payments_date_field">
          <option value="created_at" <?php echo $filters['date_field'] === 'created_at' ? 'selected' : ''; ?>>Fecha creacion</option>
          <option value="service_date" <?php echo $filters['date_field'] === 'service_date' ? 'selected' : ''; ?>>Fecha servicio</option>
        </select>
      </label>

      <label>
        Desde
        <input type="date" name="payments_from" value="<?php echo htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>

      <label>
        Hasta
        <input type="date" name="payments_to" value="<?php echo htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>

      <label>
        Metodo
        <select name="payments_method">
          <option value="">(Todos)</option>
          <?php foreach ($methodOptions as $method): ?>
            <?php $method = trim((string)$method); ?>
            <?php if ($method === '') { continue; } ?>
            <option value="<?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['method'] === $method ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Estatus
        <select name="payments_status">
          <option value="">(Todos)</option>
          <?php foreach ($statusOptions as $status): ?>
            <?php $status = trim((string)$status); ?>
            <?php if ($status === '') { continue; } ?>
            <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Moneda
        <input type="text" name="payments_currency" placeholder="MXN" value="<?php echo htmlspecialchars($filters['currency'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>

      <label>
        ID reserva
        <input type="number" min="0" name="payments_reservation_id" value="<?php echo (int)$filters['reservation_id']; ?>">
      </label>

      <label>
        Buscar
        <input type="text" name="payments_search" placeholder="Reserva, huesped, ref, concepto" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>

      <label>
        Limite
        <select name="payments_limit">
          <option value="100" <?php echo $filters['limit'] === 100 ? 'selected' : ''; ?>>100</option>
          <option value="200" <?php echo $filters['limit'] === 200 ? 'selected' : ''; ?>>200</option>
          <option value="500" <?php echo $filters['limit'] === 500 ? 'selected' : ''; ?>>500</option>
          <option value="1000" <?php echo $filters['limit'] === 1000 ? 'selected' : ''; ?>>1000</option>
        </select>
      </label>
    </div>

    <div class="form-inline" style="margin-top:10px;">
      <label class="checkbox">
        <input type="checkbox" name="payments_show_inactive" value="1" <?php echo !empty($filters['show_inactive']) ? 'checked' : ''; ?>>
        Incluir inactivos
      </label>
      <button type="submit">Filtrar</button>
      <button type="submit" class="button-secondary" name="payments_reset" value="1">Limpiar</button>
    </div>
  </form>
</section>

<section class="card">
  <h3>Line items de pago</h3>
  <p class="muted">Por defecto se muestran primero los pagos mas nuevos.</p>

  <div class="payments-summary">
    <span>Registros: <?php echo (int)count($rows); ?></span>
    <span>Total pagos: <?php echo payments_format_money($totalAmountCents, 'MXN'); ?></span>
    <span>Total marcado pagado: <?php echo payments_format_money($totalPaidCents, 'MXN'); ?></span>
    <span>Promedio por registro: <?php echo payments_format_money($avgAmountCents, 'MXN'); ?></span>
  </div>

  <?php if (!$rows): ?>
    <p class="muted" style="margin-top:12px;">No se encontraron pagos para los filtros seleccionados.</p>
  <?php else: ?>
    <div class="payments-grid">
      <?php foreach ($rows as $row): ?>
        <?php
          $reservationId = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
          $reservationCode = trim((string)($row['reservation_code'] ?? ''));
          $guestName = trim(
              (string)($row['guest_names'] ?? '') . ' ' .
              (string)($row['guest_last_name'] ?? '') . ' ' .
              (string)($row['guest_maiden_name'] ?? '')
          );
          if ($guestName === '') {
              $guestName = trim((string)($row['guest_email'] ?? ''));
          }
          if ($guestName === '') {
              $guestName = 'Huesped sin nombre';
          }

          $reservationPrimary = $reservationCode !== ''
              ? ($reservationCode . ' - ' . $guestName)
              : ('Reserva #' . $reservationId . ' - ' . $guestName);

          $propertyCode = trim((string)($row['property_code'] ?? ''));
          $roomCode = trim((string)($row['room_code'] ?? ''));
          $roomName = trim((string)($row['room_name'] ?? ''));
          $folioName = trim((string)($row['folio_name'] ?? ''));
          $folioStatus = trim((string)($row['folio_status'] ?? ''));
          $roomLabel = trim($roomCode . ($roomName !== '' ? (' - ' . $roomName) : ''));
          $folioLabel = $folioName;
          if ($folioStatus !== '') {
              $folioLabel .= ' (' . $folioStatus . ')';
          }

          $secondaryParts = array();
          if ($propertyCode !== '') {
              $secondaryParts[] = $propertyCode;
          }
          if ($roomLabel !== '') {
              $secondaryParts[] = $roomLabel;
          }
          if ($folioLabel !== '') {
              $secondaryParts[] = 'Folio ' . $folioLabel;
          }
          $reservationSecondary = $secondaryParts ? implode(' / ', $secondaryParts) : 'Sin detalle de reserva';

          $methodLabel = trim((string)($row['method'] ?? ''));
          if ($methodLabel === '') {
              $methodLabel = 'Sin metodo';
          }

          $conceptLabel = trim((string)($row['catalog_item_name'] ?? ''));
          if ($conceptLabel === '') {
              $conceptLabel = trim((string)($row['description'] ?? ''));
          }
          if ($conceptLabel === '') {
              $conceptLabel = 'Pago';
          }

          $referenceLabel = trim((string)($row['reference'] ?? ''));
          if ($referenceLabel === '') {
              $referenceLabel = '-';
          }

          $statusLabel = trim((string)($row['status'] ?? ''));
          if ($statusLabel === '') {
              $statusLabel = '-';
          }

          $currency = trim((string)($row['currency'] ?? ''));
          if ($currency === '') {
              $currency = 'MXN';
          }
          $amountCents = isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0;
          $amountClass = $amountCents < 0 ? 'is-negative' : 'is-positive';
        ?>
        <article class="payments-card">
          <div class="payments-card-head">
            <span class="payments-card-date"><?php echo htmlspecialchars(payments_format_datetime((string)($row['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
            <strong class="payments-card-amount <?php echo htmlspecialchars($amountClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(payments_format_money($amountCents, $currency), ENT_QUOTES, 'UTF-8'); ?></strong>
          </div>

          <a class="payments-res-link" href="index.php?view=reservations&amp;open_reservation=<?php echo $reservationId; ?>">
            <span class="payments-res-primary"><?php echo htmlspecialchars($reservationPrimary, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="payments-res-secondary"><?php echo htmlspecialchars($reservationSecondary, ENT_QUOTES, 'UTF-8'); ?></span>
          </a>

          <div class="payments-meta-grid">
            <div class="payments-meta-item">
              <span class="payments-meta-label">Concepto</span>
              <span class="payments-meta-value"><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="payments-meta-item">
              <span class="payments-meta-label">Metodo</span>
              <span class="payments-meta-value"><?php echo htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="payments-meta-item">
              <span class="payments-meta-label">Referencia</span>
              <span class="payments-meta-value"><?php echo htmlspecialchars($referenceLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="payments-meta-item">
              <span class="payments-meta-label">Fecha servicio</span>
              <span class="payments-meta-value"><?php echo htmlspecialchars(payments_format_date((string)($row['service_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="payments-meta-item">
              <span class="payments-meta-label">Estatus</span>
              <span class="payments-meta-value"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="payments-meta-item">
              <span class="payments-meta-label">Line item</span>
              <span class="payments-meta-value">#<?php echo (int)($row['id_line_item'] ?? 0); ?></span>
            </div>
          </div>

          <div class="payments-actions">
            <a class="button-link" href="index.php?view=reservations&amp;open_reservation=<?php echo $reservationId; ?>">Ver reservacion</a>
            <a class="button-link" href="index.php?view=calendar">Calendario</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
