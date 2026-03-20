<?php
$moduleKey = 'properties';
$currentUser = pms_current_user();
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyCode === '' || $companyId <= 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

$filters = array(
    'search'      => isset($_POST['properties_filter_search']) ? (string)$_POST['properties_filter_search'] : '',
    'only_active' => isset($_POST['properties_filter_only_active']) ? (int)$_POST['properties_filter_only_active'] : 1,
);

$amenityFields = array(
    'has_wifi' => 'Wi-Fi',
    'has_parking' => 'Estacionamiento',
    'has_shared_kitchen' => 'Cocina compartida',
    'has_dining_area' => 'Area de comedor',
    'has_cleaning_service' => 'Servicio de limpieza',
    'has_shared_laundry' => 'Lavadora/Secadora compartida',
    'has_purified_water' => 'Agua purificada',
    'has_security_24h' => 'Seguridad 24h',
    'has_self_checkin' => 'Self check-in',
    'has_pool' => 'Alberca / Piscina',
    'has_jacuzzi' => 'Jacuzzi',
    'has_garden_patio' => 'Jardin / Patio',
    'has_terrace_rooftop' => 'Terraza / Rooftop',
    'has_hammocks_loungers' => 'Hamacas / Camastros',
    'has_bbq_area' => 'Area BBQ',
    'has_beach_access' => 'Acceso a playa',
    'has_panoramic_views' => 'Vistas panoramicas',
    'has_outdoor_lounge' => 'Lounge exterior',
    'offers_airport_transfers' => 'Traslados aeropuerto',
    'offers_tours_activities' => 'Tours / Actividades',
    'has_breakfast_available' => 'Desayuno disponible',
    'offers_bike_rental' => 'Renta de bicicletas',
    'has_luggage_storage' => 'Guarda equipaje',
    'is_pet_friendly' => 'Pet friendly',
    'has_accessible_spaces' => 'Espacios accesibles'
);

$ownerObligationCatalogOptions = array();
try {
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT
            lic.id_line_item_catalog,
            lic.item_name,
            cat.category_name,
            COALESCE(prop.code, "") AS property_code
         FROM line_item_catalog lic
         JOIN sale_item_category cat
           ON cat.id_sale_item_category = lic.id_category
          AND cat.id_company = ?
          AND cat.deleted_at IS NULL
          AND cat.is_active = 1
         LEFT JOIN property prop
           ON prop.id_property = cat.id_property
         WHERE lic.deleted_at IS NULL
           AND lic.is_active = 1
           AND lic.catalog_type = "obligation"
         ORDER BY cat.category_name, lic.item_name'
    );
    $stmt->execute(array($companyId));
    $ownerObligationCatalogOptions = $stmt->fetchAll();
} catch (Exception $e) {
    $ownerObligationCatalogOptions = array();
}

$message = null;
$error = null;

// Subtabs state
$subtabState = pms_subtabs_init($moduleKey, 'static:list');
$openPropertyCodes = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'property:') === 0) {
        $code = substr($openKey, strlen('property:'));
        if ($code !== '' && !in_array($code, $openPropertyCodes, true)) {
            $openPropertyCodes[] = $code;
        }
    }
}

$action = isset($_POST['properties_action']) ? (string)$_POST['properties_action'] : '';
if ($action === 'toggle_active_filter') {
    $filters['only_active'] = $filters['only_active'] ? 0 : 1;
} elseif ($action === 'update_order') {
    $orderCode = isset($_POST['property_order_code']) ? trim((string)$_POST['property_order_code']) : '';
    $orderValue = isset($_POST['property_order_index']) && $_POST['property_order_index'] !== ''
        ? (int)$_POST['property_order_index']
        : 0;
    if ($orderCode !== '') {
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->prepare(
                'UPDATE property p
                 JOIN company c ON c.id_company = p.id_company
                 SET p.order_index = ?, p.updated_at = NOW()
                 WHERE p.code = ? AND c.code = ? AND p.deleted_at IS NULL'
            );
            $stmt->execute(array($orderValue, $orderCode, $companyCode));
            $message = 'Orden actualizado.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'new_property') {
    $_POST[$moduleKey . '_subtab_action'] = 'open';
    $_POST[$moduleKey . '_subtab_target'] = 'property:__new__';
} elseif ($action === 'save_property') {
    $code = isset($_POST['property_code']) ? trim((string)$_POST['property_code']) : '';
    $originalCode = isset($_POST['property_code_original']) ? trim((string)$_POST['property_code_original']) : '';
    $name = isset($_POST['property_name']) ? trim((string)$_POST['property_name']) : '';
    $description = isset($_POST['property_description']) ? trim((string)$_POST['property_description']) : '';
    $email = isset($_POST['property_email']) ? trim((string)$_POST['property_email']) : '';
    $phone = isset($_POST['property_phone']) ? trim((string)$_POST['property_phone']) : '';
    $website = isset($_POST['property_website']) ? trim((string)$_POST['property_website']) : '';
    $address1 = isset($_POST['property_address1']) ? trim((string)$_POST['property_address1']) : '';
    $address2 = isset($_POST['property_address2']) ? trim((string)$_POST['property_address2']) : '';
    $city = isset($_POST['property_city']) ? trim((string)$_POST['property_city']) : '';
    $state = isset($_POST['property_state']) ? trim((string)$_POST['property_state']) : '';
    $postal = isset($_POST['property_postal']) ? trim((string)$_POST['property_postal']) : '';
    $country = isset($_POST['property_country']) ? trim((string)$_POST['property_country']) : '';
    $timezone = isset($_POST['property_timezone']) ? trim((string)$_POST['property_timezone']) : '';
    $currency = isset($_POST['property_currency']) ? trim((string)$_POST['property_currency']) : '';
    $checkOutTime = isset($_POST['property_check_out_time']) ? trim((string)$_POST['property_check_out_time']) : '';
    $ownerPaymentObligationCatalogId = isset($_POST['property_owner_payment_obligation_catalog_id'])
        ? (int)$_POST['property_owner_payment_obligation_catalog_id']
        : 0;
    $orderIndex = isset($_POST['property_order_index']) && $_POST['property_order_index'] !== '' ? (int)$_POST['property_order_index'] : null;
    $isActive = isset($_POST['property_is_active']) ? 1 : 0;
    $notes = isset($_POST['property_notes']) ? trim((string)$_POST['property_notes']) : '';
    $amenityValues = array();
    foreach ($amenityFields as $amenityKey => $label) {
        $amenityValues[$amenityKey] = isset($_POST[$amenityKey]) ? 1 : 0;
    }
    $amenitiesActive = 1;

    if ($code === '' || $name === '') {
        $error = 'Codigo y nombre son obligatorios.';
    } else {
        try {
            pms_call_procedure('sp_property_upsert', array(
                $companyCode,
                $code,
                $name,
                $description === '' ? null : $description,
                $email === '' ? null : $email,
                $phone === '' ? null : $phone,
                $website === '' ? null : $website,
                $address1 === '' ? null : $address1,
                $address2 === '' ? null : $address2,
                $city === '' ? null : $city,
                $state === '' ? null : $state,
                $postal === '' ? null : $postal,
                $country === '' ? null : $country,
                $timezone === '' ? null : $timezone,
                $currency === '' ? null : $currency,
                $checkOutTime === '' ? null : $checkOutTime,
                $orderIndex,
                $isActive,
                $notes === '' ? null : $notes,
                $amenityValues['has_wifi'],
                $amenityValues['has_parking'],
                $amenityValues['has_shared_kitchen'],
                $amenityValues['has_dining_area'],
                $amenityValues['has_cleaning_service'],
                $amenityValues['has_shared_laundry'],
                $amenityValues['has_purified_water'],
                $amenityValues['has_security_24h'],
                $amenityValues['has_self_checkin'],
                $amenityValues['has_pool'],
                $amenityValues['has_jacuzzi'],
                $amenityValues['has_garden_patio'],
                $amenityValues['has_terrace_rooftop'],
                $amenityValues['has_hammocks_loungers'],
                $amenityValues['has_bbq_area'],
                $amenityValues['has_beach_access'],
                $amenityValues['has_panoramic_views'],
                $amenityValues['has_outdoor_lounge'],
                $amenityValues['offers_airport_transfers'],
                $amenityValues['offers_tours_activities'],
                $amenityValues['has_breakfast_available'],
                $amenityValues['offers_bike_rental'],
                $amenityValues['has_luggage_storage'],
                $amenityValues['is_pet_friendly'],
                $amenityValues['has_accessible_spaces'],
                $ownerPaymentObligationCatalogId > 0 ? $ownerPaymentObligationCatalogId : null,
                $amenitiesActive,
                $actorUserId
            ));
            $message = 'Propiedad guardada.';
            $_POST[$moduleKey . '_subtab_action'] = 'activate';
            $_POST[$moduleKey . '_subtab_target'] = 'static:list';
            $closeKey = $originalCode !== '' ? $originalCode : $code;
            $_POST[$moduleKey . '_subtab_target_close'] = 'property:' . $closeKey;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Refresh subtab state after possible open/close actions set above
$subtabState = pms_subtabs_init($moduleKey, 'static:list');
if (isset($_POST[$moduleKey . '_subtab_target_close']) && $_POST[$moduleKey . '_subtab_target_close'] !== '') {
    $toClose = (string)$_POST[$moduleKey . '_subtab_target_close'];
    if (isset($subtabState['open']) && is_array($subtabState['open'])) {
        $subtabState['open'] = array_values(array_filter($subtabState['open'], function ($item) use ($toClose) {
            return $item !== $toClose;
        }));
    }
    $subtabState['active'] = 'static:list';
    $_SESSION['pms_subtabs'][$moduleKey] = $subtabState;
}
$openPropertyCodes = array();
foreach (isset($subtabState['open']) ? $subtabState['open'] : array() as $openKey) {
    if (strpos($openKey, 'property:') === 0) {
        $code = substr($openKey, strlen('property:'));
        if ($code !== '' && !in_array($code, $openPropertyCodes, true)) {
            $openPropertyCodes[] = $code;
        }
    }
}

// Fetch list
try {
    $sets = pms_call_procedure('sp_portal_property_data', array(
        $companyCode,
        $filters['search'] === '' ? null : $filters['search'],
        $filters['only_active'],
        null
    ));
    $propertyList = isset($sets[0]) ? $sets[0] : array();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
    $propertyList = array();
}

// Helper to fetch detail
function properties_fetch_detail($companyCode, $code, array $amenityFields)
{
    try {
        $detailSets = pms_call_procedure('sp_portal_property_data', array(
            $companyCode,
            null,
            null,
            $code
        ));
        $detail = isset($detailSets[1][0]) ? $detailSets[1][0] : null;
    } catch (Exception $e) {
        $detail = null;
    }
    if (!$detail) {
        return null;
    }
    foreach ($amenityFields as $amenityKey => $_label) {
        if (!isset($detail[$amenityKey])) {
            $detail[$amenityKey] = 0;
        }
    }
    if (!isset($detail['check_out_time'])) {
        $detail['check_out_time'] = '';
    }
    if (!isset($detail['order_index'])) {
        $detail['order_index'] = 0;
    }
    if (!isset($detail['id_owner_payment_obligation_catalog'])) {
        $detail['id_owner_payment_obligation_catalog'] = 0;
    }
    return $detail;
}

// Static tab content (list)
ob_start();
?>
<div class="tab-actions">
  <form method="post" class="form-inline">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <label>
      Busqueda
      <input type="text" name="properties_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre o ciudad">
    </label>
    <label class="checkbox">
      <input type="checkbox" name="properties_filter_only_active" value="1" <?php echo $filters['only_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
      Solo activas
    </label>
    <button type="submit">Buscar</button>
  </form>
  <form method="post">
    <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
    <input type="hidden" name="properties_action" value="new_property">
    <button type="submit">Nueva propiedad</button>
  </form>
</div>
<?php if ($error): ?>
  <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($message): ?>
  <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($propertyList): ?>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Codigo</th>
          <th>Nombre</th>
          <th>Ciudad</th>
          <th>Orden</th>
          <th>Habitaciones</th>
          <th>Categorias</th>
          <th>Plan tarifario</th>
          <th>Activa</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($propertyList as $row):
          $code = isset($row['property_code']) ? (string)$row['property_code'] : '';
          $isOpen = in_array($code, $openPropertyCodes, true);
        ?>
          <tr class="<?php echo $isOpen ? 'is-selected' : ''; ?>">
            <td><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['property_name']) ? (string)$row['property_name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(isset($row['city']) ? (string)$row['city'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
              <form method="post" class="inline-form">
                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                <input type="hidden" name="properties_action" value="update_order">
                <input type="hidden" name="properties_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="properties_filter_only_active" value="<?php echo (int)$filters['only_active']; ?>">
                <input type="hidden" name="property_order_code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="number" name="property_order_index" min="0" value="<?php echo isset($row['property_order_index']) ? (int)$row['property_order_index'] : 0; ?>" onchange="this.form.submit()">
              </form>
            </td>
            <td><?php echo isset($row['room_count']) ? (int)$row['room_count'] : 0; ?></td>
            <td><?php echo isset($row['category_count']) ? (int)$row['category_count'] : 0; ?></td>
            <td><?php echo isset($row['rateplan_count']) ? (int)$row['rateplan_count'] : 0; ?></td>
            <td><?php echo isset($row['is_active']) && (int)$row['is_active'] === 1 ? 'Si' : 'No'; ?></td>
            <td>
              <form method="post">
                <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
                <input type="hidden" name="properties_action" value="">
                <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="open">
                <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="property:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="button-secondary">Abrir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <p class="muted">No se encontraron propiedades.</p>
<?php endif; ?>
<?php
$listContent = ob_get_clean();

// Dynamic tabs
$dynamicTabs = array();
foreach ($openPropertyCodes as $code) {
    $detail = null;
    if ($code === '__new__') {
        $detail = array(
            'property_code' => '',
            'property_name' => '',
            'description' => '',
            'email' => '',
            'phone' => '',
            'website' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
            'timezone' => 'America/Mexico_City',
            'currency' => 'MXN',
            'check_out_time' => '',
            'order_index' => 0,
            'id_owner_payment_obligation_catalog' => 0,
            'is_active' => 1,
            'notes' => ''
        );
        foreach ($amenityFields as $amenityKey => $_label) {
            $detail[$amenityKey] = 0;
        }
        $tabLabel = 'Nueva';
    } else {
        $detail = properties_fetch_detail($companyCode, $code, $amenityFields);
        if (!$detail) {
            continue;
        }
        $tabLabel = $code;
    }

    $panelId = 'property-panel-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '' ? 'new' : $code);
    $closeFormId = 'properties-close-' . preg_replace('/[^a-zA-Z0-9_\-]/', '-', $code === '' ? 'new' : $code);

    ob_start();
    ?>
    <div class="subtab-actions">
      <div>
        <h3><?php echo htmlspecialchars($code === '__new__' || $code === '' ? 'Nueva propiedad' : $code, ENT_QUOTES, 'UTF-8'); ?></h3>
      </div>
      <div class="subtab-actions">
        <form method="post" id="<?php echo htmlspecialchars($closeFormId, ENT_QUOTES, 'UTF-8'); ?>">
          <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_action', ENT_QUOTES, 'UTF-8'); ?>" value="close">
          <input type="hidden" name="<?php echo htmlspecialchars($moduleKey . '_subtab_target', ENT_QUOTES, 'UTF-8'); ?>" value="property:<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
          <button type="submit" class="button-secondary">Cerrar</button>
        </form>
      </div>
    </div>

    <form method="post" class="form-grid grid-3">
      <?php pms_subtabs_form_state_fields($moduleKey, $subtabState); ?>
      <input type="hidden" name="properties_action" value="save_property">
      <input type="hidden" name="selected_property" value="<?php echo htmlspecialchars($code === '__new__' ? '' : $code, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="property_code_original" value="<?php echo htmlspecialchars($code === '__new__' ? '__new__' : (string)$detail['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="properties_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="properties_filter_only_active" value="<?php echo (int)$filters['only_active']; ?>">

      <label>
        Codigo *
        <input type="text" name="property_code" required value="<?php echo htmlspecialchars((string)$detail['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Nombre *
        <input type="text" name="property_name" required value="<?php echo htmlspecialchars((string)$detail['property_name'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Orden
        <input type="number" name="property_order_index" min="0" value="<?php echo isset($detail['order_index']) ? (int)$detail['order_index'] : 0; ?>">
      </label>
      <label>
        Correo
        <input type="email" name="property_email" value="<?php echo htmlspecialchars((string)$detail['email'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Telefono
        <input type="text" name="property_phone" value="<?php echo htmlspecialchars((string)$detail['phone'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Sitio web
        <input type="text" name="property_website" value="<?php echo htmlspecialchars((string)$detail['website'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Ciudad
        <input type="text" name="property_city" value="<?php echo htmlspecialchars((string)$detail['city'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Estado
        <input type="text" name="property_state" value="<?php echo htmlspecialchars((string)$detail['state'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Codigo postal
        <input type="text" name="property_postal" value="<?php echo htmlspecialchars((string)$detail['postal_code'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Pais
        <input type="text" name="property_country" value="<?php echo htmlspecialchars((string)$detail['country'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Direccion 1
        <input type="text" name="property_address1" value="<?php echo htmlspecialchars((string)$detail['address_line1'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Direccion 2
        <input type="text" name="property_address2" value="<?php echo htmlspecialchars((string)$detail['address_line2'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Zona horaria
        <input type="text" name="property_timezone" placeholder="America/Mexico_City" value="<?php echo htmlspecialchars((string)$detail['timezone'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Moneda
        <input type="text" name="property_currency" placeholder="MXN" value="<?php echo htmlspecialchars((string)$detail['currency'], ENT_QUOTES, 'UTF-8'); ?>">
      </label>
      <label>
        Check-out (hora)
        <input type="time" name="property_check_out_time" value="<?php
          $timeVal = '';
          if (!empty($detail['check_out_time'])) {
              $ts = strtotime((string)$detail['check_out_time']);
              if ($ts) {
                  $timeVal = date('H:i', $ts);
              }
          }
          echo htmlspecialchars($timeVal, ENT_QUOTES, 'UTF-8');
        ?>">
      </label>
      <label>
        Concepto obligacion pago a propietario
        <select name="property_owner_payment_obligation_catalog_id">
          <option value="">(Sin configurar)</option>
          <?php foreach ($ownerObligationCatalogOptions as $opt): ?>
            <?php
              $optId = isset($opt['id_line_item_catalog']) ? (int)$opt['id_line_item_catalog'] : 0;
              if ($optId <= 0) {
                  continue;
              }
              $optLabel = trim((string)(isset($opt['item_name']) ? $opt['item_name'] : ''));
              $optCategory = trim((string)(isset($opt['category_name']) ? $opt['category_name'] : ''));
              $optPropertyCode = trim((string)(isset($opt['property_code']) ? $opt['property_code'] : ''));
              $optFull = ($optPropertyCode !== '' ? ($optPropertyCode . ' - ') : '')
                  . ($optCategory !== '' ? ($optCategory . ' / ') : '')
                  . $optLabel;
            ?>
            <option value="<?php echo (int)$optId; ?>" <?php echo (int)$detail['id_owner_payment_obligation_catalog'] === $optId ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($optFull, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="checkbox">
        <input type="checkbox" name="property_is_active" value="1" <?php echo isset($detail['is_active']) && (int)$detail['is_active'] === 1 ? 'checked' : ''; ?>>
        Activa
      </label>
      <label class="full">
        Descripcion
        <textarea name="property_description" rows="3"><?php echo htmlspecialchars((string)$detail['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>
      <label class="full">
        Notas
        <textarea name="property_notes" rows="2"><?php echo htmlspecialchars((string)$detail['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>

      <div class="full">
        <fieldset class="amenities-grid">
          <legend>Amenidades</legend>
          <?php foreach ($amenityFields as $amenityKey => $amenityLabel): ?>
            <label class="checkbox amenity-item">
              <input type="checkbox" name="<?php echo htmlspecialchars($amenityKey, ENT_QUOTES, 'UTF-8'); ?>" value="1" <?php echo !empty($detail[$amenityKey]) ? 'checked' : ''; ?>>
              <?php echo htmlspecialchars($amenityLabel, ENT_QUOTES, 'UTF-8'); ?>
            </label>
          <?php endforeach; ?>
        </fieldset>
      </div>

      <div class="form-actions full">
        <button type="submit">Guardar propiedad</button>
      </div>
    </form>
    <?php
    $panelContent = ob_get_clean();
    $dynamicTabs[] = array(
        'key' => 'property:' . $code,
        'title' => $tabLabel,
        'panel_id' => $panelId,
        'close_form_id' => $closeFormId,
        'content' => $panelContent
    );
}

$staticTabs = array(
    array(
        'id' => 'list',
        'title' => 'General',
        'content' => $listContent
    )
);

pms_render_subtabs($moduleKey, $subtabState, $staticTabs, $dynamicTabs);
