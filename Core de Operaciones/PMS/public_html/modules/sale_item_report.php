<?php
$moduleKey = 'sale_item_report';
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

$companyCode = (string)$currentUser['company_code'];
$companyId   = (int)$currentUser['company_id'];

if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

if (!function_exists('sale_report_format_money')) {
    function sale_report_format_money($cents, $currency = 'MXN')
    {
        $value = (float)$cents / 100;
        return '$' . number_format($value, 2) . ' ' . $currency;
    }
}

$properties = pms_fetch_properties($companyId);

$filters = array(
    'property_code' => isset($_POST['sale_report_property']) ? strtoupper((string)$_POST['sale_report_property']) : '',
    'date_from' => isset($_POST['sale_report_from']) ? (string)$_POST['sale_report_from'] : '',
    'date_to' => isset($_POST['sale_report_to']) ? (string)$_POST['sale_report_to'] : '',
    'search' => isset($_POST['sale_report_search']) ? (string)$_POST['sale_report_search'] : '',
    'status' => isset($_POST['sale_report_status']) ? (string)$_POST['sale_report_status'] : '',
    'folio_status' => isset($_POST['sale_report_folio_status']) ? (string)$_POST['sale_report_folio_status'] : '',
    'parent_category_id' => isset($_POST['sale_report_parent_category_id']) ? (int)$_POST['sale_report_parent_category_id'] : 0,
    'category_id' => isset($_POST['sale_report_category_id']) ? (int)$_POST['sale_report_category_id'] : 0,
    'catalog_id' => isset($_POST['sale_report_catalog_id']) ? (int)$_POST['sale_report_catalog_id'] : 0,
    'min_total' => isset($_POST['sale_report_min_total']) ? (string)$_POST['sale_report_min_total'] : '',
    'max_total' => isset($_POST['sale_report_max_total']) ? (string)$_POST['sale_report_max_total'] : '',
    'has_tax' => isset($_POST['sale_report_has_tax']) ? 1 : 0,
    'show_inactive' => isset($_POST['sale_report_show_inactive']) ? 1 : 0,
    'show_canceled_reservations' => isset($_POST['sale_report_show_canceled_reservations']) ? 1 : 0
);

$minTotal = trim($filters['min_total']) !== '' ? (float)str_replace(',', '', $filters['min_total']) : 0;
$maxTotal = trim($filters['max_total']) !== '' ? (float)str_replace(',', '', $filters['max_total']) : 0;
$minTotalCents = $minTotal > 0 ? (int)round($minTotal * 100) : 0;
$maxTotalCents = $maxTotal > 0 ? (int)round($maxTotal * 100) : 0;

$pdo = pms_get_connection();
$propertyId = null;
if ($filters['property_code'] !== '') {
    $stmt = $pdo->prepare('SELECT id_property FROM property WHERE id_company = ? AND code = ? LIMIT 1');
    $stmt->execute(array($companyId, $filters['property_code']));
    $propertyId = $stmt->fetchColumn();
    $propertyId = $propertyId !== false ? (int)$propertyId : null;
}

$categories = array();
try {
    $sql = 'SELECT id_sale_item_category, id_parent_sale_item_category, category_name, id_property
            FROM sale_item_category
            WHERE id_company = ? AND deleted_at IS NULL
            ORDER BY category_name';
    $params = array($companyId);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = array();
}

$parentCategories = array();
$subcategories = array();
foreach ($categories as $cat) {
    $catId = isset($cat['id_sale_item_category']) ? (int)$cat['id_sale_item_category'] : 0;
    $parentId = isset($cat['id_parent_sale_item_category']) ? (int)$cat['id_parent_sale_item_category'] : 0;
    $catPropertyId = isset($cat['id_property']) ? (int)$cat['id_property'] : 0;
    if ($propertyId !== null && $catPropertyId !== 0 && $catPropertyId !== $propertyId) {
        continue;
    }
    if ($parentId > 0) {
        if ($filters['parent_category_id'] && $parentId !== $filters['parent_category_id']) {
            continue;
        }
        $subcategories[] = $cat;
    } else {
        $parentCategories[] = $cat;
    }
}

$catalogItems = array();
try {
    $sql = 'SELECT sic.id_line_item_catalog AS id_sale_item_catalog, sic.item_name, sic.id_category, cat.category_name, cat.id_property
            FROM line_item_catalog sic
            JOIN sale_item_category cat ON cat.id_sale_item_category = sic.id_category
            WHERE sic.catalog_type = "sale_item" AND cat.id_company = ? AND sic.deleted_at IS NULL
            ORDER BY cat.category_name, sic.item_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($companyId));
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $rowPropertyId = isset($row['id_property']) ? (int)$row['id_property'] : 0;
        if ($propertyId !== null && $rowPropertyId !== 0 && $rowPropertyId !== $propertyId) {
            continue;
        }
        if ($filters['category_id'] && (int)$row['id_category'] !== $filters['category_id']) {
            continue;
        }
        $catalogItems[] = $row;
    }
} catch (Exception $e) {
    $catalogItems = array();
}

$saleItems = array();
try {
    try {
        try {
            $sets = pms_call_procedure('sp_sale_item_report_data', array(
                $companyCode,
                $filters['property_code'] === '' ? null : $filters['property_code'],
                $filters['date_from'] === '' ? null : $filters['date_from'],
                $filters['date_to'] === '' ? null : $filters['date_to'],
                $filters['search'] === '' ? null : $filters['search'],
                $filters['status'] === '' ? null : $filters['status'],
                $filters['folio_status'] === '' ? null : $filters['folio_status'],
                $filters['parent_category_id'] > 0 ? $filters['parent_category_id'] : 0,
                $filters['category_id'] > 0 ? $filters['category_id'] : 0,
                $filters['catalog_id'] > 0 ? $filters['catalog_id'] : 0,
                $minTotalCents > 0 ? $minTotalCents : 0,
                $maxTotalCents > 0 ? $maxTotalCents : 0,
                $filters['has_tax'],
                0,
                $filters['show_canceled_reservations']
            ));
        } catch (Exception $eParamNew) {
            $sets = pms_call_procedure('sp_sale_item_report_data', array(
                $companyCode,
                $filters['property_code'] === '' ? null : $filters['property_code'],
                $filters['date_from'] === '' ? null : $filters['date_from'],
                $filters['date_to'] === '' ? null : $filters['date_to'],
                $filters['search'] === '' ? null : $filters['search'],
                $filters['status'] === '' ? null : $filters['status'],
                $filters['folio_status'] === '' ? null : $filters['folio_status'],
                $filters['parent_category_id'] > 0 ? $filters['parent_category_id'] : 0,
                $filters['category_id'] > 0 ? $filters['category_id'] : 0,
                $filters['catalog_id'] > 0 ? $filters['catalog_id'] : 0,
                $minTotalCents > 0 ? $minTotalCents : 0,
                $maxTotalCents > 0 ? $maxTotalCents : 0,
                $filters['has_tax'],
                0
            ));
        }
    } catch (Exception $eParam) {
        $sets = pms_call_procedure('sp_sale_item_report_data', array(
            $companyCode,
            $filters['property_code'] === '' ? null : $filters['property_code'],
            $filters['date_from'] === '' ? null : $filters['date_from'],
            $filters['date_to'] === '' ? null : $filters['date_to'],
            $filters['search'] === '' ? null : $filters['search'],
            $filters['status'] === '' ? null : $filters['status'],
            $filters['folio_status'] === '' ? null : $filters['folio_status'],
            $filters['parent_category_id'] > 0 ? $filters['parent_category_id'] : 0,
            $filters['category_id'] > 0 ? $filters['category_id'] : 0,
            $filters['catalog_id'] > 0 ? $filters['catalog_id'] : 0,
            $minTotalCents > 0 ? $minTotalCents : 0,
            $maxTotalCents > 0 ? $maxTotalCents : 0,
            0
        ));
    }
    $saleItems = isset($sets[0]) ? $sets[0] : array();
} catch (Exception $e) {
    $saleItems = array();
}

if ($saleItems) {
    $reservationIds = array();
    foreach ($saleItems as $row) {
        $rid = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
        if ($rid > 0) {
            $reservationIds[$rid] = true;
        }
    }
    if ($reservationIds) {
        $reservationMap = array();
        try {
            $ridList = array_keys($reservationIds);
            $placeholders = implode(',', array_fill(0, count($ridList), '?'));
            $stmtRes = $pdo->prepare(
                'SELECT id_reservation, status, is_active
                   FROM reservation
                  WHERE id_reservation IN (' . $placeholders . ')
                    AND deleted_at IS NULL'
            );
            $stmtRes->execute($ridList);
            $rowsRes = $stmtRes->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsRes as $resRow) {
                $reservationMap[(int)$resRow['id_reservation']] = array(
                    'status' => isset($resRow['status']) ? strtolower(trim((string)$resRow['status'])) : '',
                    'is_active' => isset($resRow['is_active']) ? (int)$resRow['is_active'] : 0
                );
            }
        } catch (Exception $e) {
            $reservationMap = array();
        }

        $filtered = array();
        foreach ($saleItems as $row) {
            $rid = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
            if ($rid <= 0) {
                $filtered[] = $row;
                continue;
            }
            if (!isset($reservationMap[$rid])) {
                continue;
            }
            $resStatus = isset($reservationMap[$rid]['status']) ? (string)$reservationMap[$rid]['status'] : '';
            $resActive = isset($reservationMap[$rid]['is_active']) ? (int)$reservationMap[$rid]['is_active'] : 0;
            if ($resActive !== 1) {
                continue;
            }
            if (
                empty($filters['show_canceled_reservations']) &&
                in_array($resStatus, array('cancelled', 'canceled', 'cancelado', 'cancelada'), true)
            ) {
                continue;
            }
            $filtered[] = $row;
        }
        $saleItems = $filtered;
    }
}
$finalTotalsBySale = array();
if ($saleItems) {
    foreach ($saleItems as $row) {
        $saleId = isset($row['id_sale_item']) ? (int)$row['id_sale_item'] : 0;
        if ($saleId <= 0) {
            continue;
        }
        $finalTotalsBySale[$saleId] = isset($row['total_with_tax_cents']) ? (int)$row['total_with_tax_cents'] : 0;
    }
}
?>

<section class="card">
  <h2>Reporte de cargos</h2>
  <p class="muted">Consulta todos los cargos registrados.</p>

  <form method="post">
    <div class="form-inline">
      <label>
        Propiedad
        <select name="sale_report_property">
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
        Desde
        <input type="date" name="sale_report_from" value="<?php echo htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Hasta
        <input type="date" name="sale_report_to" value="<?php echo htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Buscar
        <input type="text" name="sale_report_search" placeholder="Reserva, huesped, folio, concepto" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Estatus
        <select name="sale_report_status">
          <option value="">(Todos)</option>
          <?php foreach (array('posted','void','canceled','cancelled') as $st): ?>
            <option value="<?php echo $st; ?>" <?php echo $filters['status'] === $st ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Folio
        <select name="sale_report_folio_status">
          <option value="">(Todos)</option>
          <?php foreach (array('open','closed') as $fs): ?>
            <option value="<?php echo $fs; ?>" <?php echo $filters['folio_status'] === $fs ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($fs, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="form-inline" style="margin-top:10px;">
      <label>
        Categor&iacute;a
        <select name="sale_report_parent_category_id">
          <option value="0">(Todas)</option>
          <?php foreach ($parentCategories as $cat): ?>
            <?php $cid = (int)$cat['id_sale_item_category']; ?>
            <option value="<?php echo $cid; ?>" <?php echo $filters['parent_category_id'] === $cid ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string)$cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Subcategor&iacute;a
        <select name="sale_report_category_id">
          <option value="0">(Todas)</option>
          <?php foreach ($subcategories as $cat): ?>
            <?php $cid = (int)$cat['id_sale_item_category']; ?>
            <option value="<?php echo $cid; ?>" <?php echo $filters['category_id'] === $cid ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars((string)$cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Concepto
        <select name="sale_report_catalog_id">
          <option value="0">(Todos)</option>
          <?php foreach ($catalogItems as $item): ?>
            <?php $cid = (int)$item['id_sale_item_catalog']; ?>
            <?php $label = (string)$item['category_name'] . ' / ' . (string)$item['item_name']; ?>
            <option value="<?php echo $cid; ?>" <?php echo $filters['catalog_id'] === $cid ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Min total (MXN)
        <input type="text" name="sale_report_min_total" value="<?php echo htmlspecialchars($filters['min_total'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Max total (MXN)
        <input type="text" name="sale_report_max_total" value="<?php echo htmlspecialchars($filters['max_total'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label class="inline">
        <input type="checkbox" name="sale_report_has_tax" value="1" <?php echo $filters['has_tax'] ? 'checked' : ''; ?>>
        Solo con impuestos
      </label>
      <label class="inline">
        <input type="checkbox" name="sale_report_show_canceled_reservations" value="1" <?php echo !empty($filters['show_canceled_reservations']) ? 'checked' : ''; ?>>
        Ver reservaciones canceladas
      </label>
      <button type="submit" class="button-primary">Filtrar</button>
    </div>
  </form>
</section>

<section class="card">
  <h3>Resultados</h3>
  <div class="table-scroll">
    <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Propiedad</th>
            <th>Reserva</th>
            <th>Huesped</th>
            <th>Folio</th>
            <th>Concepto</th>
            <th>Cantidad</th>
            <th>Precio unitario</th>
            <th>Subtotal</th>
            <th>Impuestos</th>
            <th>Total</th>
            <th>Total final</th>
            <th>Estatus</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($saleItems as $row): ?>
            <?php
              $saleId = isset($row['id_sale_item']) ? (int)$row['id_sale_item'] : 0;
              $currency = isset($row['currency']) ? (string)$row['currency'] : 'MXN';
              $subcategory = isset($row['subcategory_name']) ? trim((string)$row['subcategory_name']) : '';
              $conceptName = isset($row['concept_name']) ? trim((string)$row['concept_name']) : '';
              $conceptLabel = $conceptName;
              if ($subcategory !== '') {
                  $conceptLabel = $subcategory . ' / ' . $conceptName;
              }
              $guestLabel = isset($row['guest_name']) && trim((string)$row['guest_name']) !== ''
                  ? (string)$row['guest_name']
                  : (isset($row['guest_email']) ? (string)$row['guest_email'] : '');
              $propertyLabel = isset($row['property_code']) ? (string)$row['property_code'] : '';
              if (isset($row['property_name']) && (string)$row['property_name'] !== '') {
                  $propertyLabel = $propertyLabel !== '' ? ($propertyLabel . ' - ' . (string)$row['property_name']) : (string)$row['property_name'];
              }
              $folioLabel = isset($row['folio_name']) ? (string)$row['folio_name'] : '';
              if (isset($row['folio_status']) && (string)$row['folio_status'] !== '') {
                  $folioLabel .= ' (' . (string)$row['folio_status'] . ')';
              }
              $finalTotalCents = isset($finalTotalsBySale[$saleId]) ? (int)$finalTotalsBySale[$saleId] : (int)($row['total_with_tax_cents'] ?? 0);
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$row['service_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($propertyLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($guestLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($folioLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($conceptLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$row['quantity'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo sale_report_format_money(isset($row['unit_price_cents']) ? $row['unit_price_cents'] : 0, $currency); ?></td>
              <td><?php echo sale_report_format_money(isset($row['amount_cents']) ? $row['amount_cents'] : 0, $currency); ?></td>
              <td><?php echo sale_report_format_money(isset($row['tax_amount_cents']) ? $row['tax_amount_cents'] : 0, $currency); ?></td>
              <td><?php echo sale_report_format_money(isset($row['total_with_tax_cents']) ? $row['total_with_tax_cents'] : 0, $currency); ?></td>
              <td><?php echo sale_report_format_money($finalTotalCents, $currency); ?></td>
              <td><?php echo htmlspecialchars((string)$row['sale_status'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
    </table>
  </div>
</section>
