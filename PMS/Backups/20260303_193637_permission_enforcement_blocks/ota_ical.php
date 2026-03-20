<?php
$currentUser = pms_current_user();
if (!$currentUser) {
    echo '<p class="error">Sesion invalida.</p>';
    return;
}

require_once __DIR__ . '/../includes/ota_ical.php';

$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;
if ($companyId <= 0 || $companyCode === '') {
    echo '<p class="error">No se pudo determinar la empresa actual.</p>';
    return;
}

$db = pms_get_connection();
$properties = pms_fetch_properties($companyId);
$propertiesById = array();
foreach ($properties as $prop) {
    $pid = isset($prop['id_property']) ? (int)$prop['id_property'] : 0;
    if ($pid > 0) {
        $propertiesById[$pid] = $prop;
    }
}

if (!$properties) {
    echo '<section class="card"><h2>iCal OTAs</h2><p class="error">No hay propiedades activas para configurar feeds iCal.</p></section>';
    return;
}

$selectedPropertyId = 0;
if (isset($_POST['ota_ical_property_id'])) {
    $selectedPropertyId = (int)$_POST['ota_ical_property_id'];
} elseif (isset($_GET['ota_ical_property_id'])) {
    $selectedPropertyId = (int)$_GET['ota_ical_property_id'];
}
if ($selectedPropertyId <= 0 && $properties) {
    $selectedPropertyId = isset($properties[0]['id_property']) ? (int)$properties[0]['id_property'] : 0;
}
if ($selectedPropertyId > 0 && !isset($propertiesById[$selectedPropertyId])) {
    $selectedPropertyId = 0;
}

$message = '';
$error = '';
$syncMessages = array();
$syncWarnings = array();

if (!pms_ota_ical_table_exists($db, 'ota_ical_feed')) {
    echo '<section class="card"><h2>iCal OTAs</h2><p class="error">No existe la tabla <code>ota_ical_feed</code>. Ejecuta primero los scripts de esquema iCal.</p></section>';
    return;
}

$action = isset($_POST['ota_ical_action']) ? trim((string)$_POST['ota_ical_action']) : '';
if ($action !== '') {
    try {
        if ($action === 'save_feed') {
            $payload = array(
                'id_ota_ical_feed' => isset($_POST['id_ota_ical_feed']) ? (int)$_POST['id_ota_ical_feed'] : 0,
                'id_property' => isset($_POST['ota_ical_property_id']) ? (int)$_POST['ota_ical_property_id'] : 0,
                'scope_type' => isset($_POST['scope_type']) ? (string)$_POST['scope_type'] : 'room',
                'id_room' => isset($_POST['id_room']) ? (int)$_POST['id_room'] : 0,
                'id_category' => isset($_POST['id_category']) ? (int)$_POST['id_category'] : 0,
                'id_ota_account' => isset($_POST['id_ota_account']) ? (int)$_POST['id_ota_account'] : 0,
                'platform' => isset($_POST['platform']) ? (string)$_POST['platform'] : 'otro',
                'timezone' => isset($_POST['timezone']) ? (string)$_POST['timezone'] : 'America/Mexico_City',
                'feed_name' => isset($_POST['feed_name']) ? (string)$_POST['feed_name'] : '',
                'import_url' => isset($_POST['import_url']) ? (string)$_POST['import_url'] : '',
                'import_enabled' => isset($_POST['import_enabled']) ? 1 : 0,
                'import_ignore_our_uids' => isset($_POST['import_ignore_our_uids']) ? 1 : 0,
                'export_enabled' => isset($_POST['export_enabled']) ? 1 : 0,
                'export_summary_mode' => isset($_POST['export_summary_mode']) ? (string)$_POST['export_summary_mode'] : 'reserved',
                'export_include_reservations' => isset($_POST['export_include_reservations']) ? 1 : 0,
                'export_include_room_blocks' => isset($_POST['export_include_room_blocks']) ? 1 : 0,
                'sync_interval_minutes' => isset($_POST['sync_interval_minutes']) ? (int)$_POST['sync_interval_minutes'] : 30,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            );
            $saved = pms_ota_ical_upsert_feed($db, $companyId, $actorUserId, $payload);
            $selectedPropertyId = isset($saved['id_property']) ? (int)$saved['id_property'] : $selectedPropertyId;
            $message = 'Feed iCal guardado correctamente.';
        } elseif ($action === 'delete_feed') {
            $feedId = isset($_POST['id_ota_ical_feed']) ? (int)$_POST['id_ota_ical_feed'] : 0;
            pms_ota_ical_delete_feed($db, $companyId, $feedId, $actorUserId);
            $message = 'Feed iCal eliminado.';
        } elseif ($action === 'sync_feed') {
            $feedId = isset($_POST['id_ota_ical_feed']) ? (int)$_POST['id_ota_ical_feed'] : 0;
            $feed = pms_ota_ical_fetch_feed_by_id($db, $companyId, $feedId);
            if (!$feed) {
                throw new Exception('Feed iCal no encontrado.');
            }
            $syncResult = pms_ota_ical_sync_feed($db, $feed, $actorUserId);
            $syncMessages[] = sprintf(
                'Sync feed #%d: eventos=%d, creados=%d, actualizados=%d, desactivados=%d, bloques creados=%d, actualizados=%d, eliminados=%d.',
                $feedId,
                isset($syncResult['events_total']) ? (int)$syncResult['events_total'] : 0,
                isset($syncResult['events_created']) ? (int)$syncResult['events_created'] : 0,
                isset($syncResult['events_updated']) ? (int)$syncResult['events_updated'] : 0,
                isset($syncResult['events_deactivated']) ? (int)$syncResult['events_deactivated'] : 0,
                isset($syncResult['blocks_created']) ? (int)$syncResult['blocks_created'] : 0,
                isset($syncResult['blocks_updated']) ? (int)$syncResult['blocks_updated'] : 0,
                isset($syncResult['blocks_deleted']) ? (int)$syncResult['blocks_deleted'] : 0
            );
            $warnings = isset($syncResult['warnings']) && is_array($syncResult['warnings']) ? $syncResult['warnings'] : array();
            foreach ($warnings as $warn) {
                $syncWarnings[] = (string)$warn;
            }
        } elseif ($action === 'sync_property') {
            $feeds = pms_ota_ical_list_feeds($db, $companyId, $selectedPropertyId);
            foreach ($feeds as $feed) {
                if (empty($feed['import_enabled'])) {
                    continue;
                }
                $feedId = isset($feed['id_ota_ical_feed']) ? (int)$feed['id_ota_ical_feed'] : 0;
                if ($feedId <= 0) {
                    continue;
                }
                try {
                    $result = pms_ota_ical_sync_feed($db, $feed, $actorUserId);
                    $syncMessages[] = sprintf(
                        'Feed #%d sincronizado (%d eventos).',
                        $feedId,
                        isset($result['events_total']) ? (int)$result['events_total'] : 0
                    );
                    if (!empty($result['warnings']) && is_array($result['warnings'])) {
                        foreach ($result['warnings'] as $warn) {
                            $syncWarnings[] = 'Feed #' . $feedId . ': ' . (string)$warn;
                        }
                    }
                } catch (Exception $e) {
                    $syncWarnings[] = 'Feed #' . $feedId . ': ' . $e->getMessage();
                }
            }
            if (!$syncMessages && !$syncWarnings) {
                $syncWarnings[] = 'No hay feeds con import habilitado para sincronizar.';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$rooms = array();
$categories = array();
if ($selectedPropertyId > 0) {
    $stmt = $db->prepare(
        'SELECT id_room, code, name
         FROM room
         WHERE id_property = ?
           AND deleted_at IS NULL
           AND is_active = 1
         ORDER BY order_index, code'
    );
    $stmt->execute(array($selectedPropertyId));
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        'SELECT id_category, code, name
         FROM roomcategory
         WHERE id_property = ?
           AND deleted_at IS NULL
         ORDER BY order_index, name'
    );
    $stmt->execute(array($selectedPropertyId));
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$propertyCode = '';
if ($selectedPropertyId > 0 && isset($propertiesById[$selectedPropertyId]['code'])) {
    $propertyCode = strtoupper((string)$propertiesById[$selectedPropertyId]['code']);
}
$otaAccounts = pms_fetch_ota_accounts($companyId, $propertyCode, false);

$feeds = pms_ota_ical_list_feeds($db, $companyId, $selectedPropertyId);
$summaryModes = pms_ota_ical_summary_modes($db);
$summaryLabels = array(
    'reserved' => 'reserved',
    'reservation_code' => 'reservation_code',
    'guest_name' => 'guest_name',
    'busy' => 'busy',
    'detailed' => 'detailed'
);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';
$basePath = rtrim(dirname(isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : ''), '/\\');
$exportPrefix = $scheme . '://' . $host . ($basePath !== '' && $basePath !== '.' ? $basePath : '') . '/api/ota_ical_export.php?token=';
$syncApiUrl = $scheme . '://' . $host . ($basePath !== '' && $basePath !== '.' ? $basePath : '') . '/api/ota_ical_sync.php';

?>
<div class="page-header">
  <h2>iCal OTAs</h2>
  <p class="muted">Importa calendarios iCal de Booking/Airbnb/Expedia y exporta disponibilidad desde VLV PMS.</p>
</div>

<?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php if ($message): ?><p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php foreach ($syncMessages as $line): ?><p class="success"><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></p><?php endforeach; ?>
<?php foreach ($syncWarnings as $line): ?><p class="warning"><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></p><?php endforeach; ?>

<section class="card">
  <h3>Filtros</h3>
  <form method="get" class="form-grid grid-3">
    <input type="hidden" name="view" value="ota_ical">
    <label>Propiedad
      <select name="ota_ical_property_id">
        <?php foreach ($properties as $prop): ?>
          <?php $pid = isset($prop['id_property']) ? (int)$prop['id_property'] : 0; ?>
          <option value="<?php echo $pid; ?>" <?php echo $pid === $selectedPropertyId ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string)$prop['code'] . ' - ' . (string)$prop['name'], ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form-actions align-end">
      <button type="submit">Aplicar</button>
    </div>
  </form>
</section>

<section class="card">
  <h3>Automatizacion (cron)</h3>
  <p class="muted">Define la variable de entorno <code>PMS_ICAL_SYNC_TOKEN</code> en el servidor y ejecuta este endpoint periodicamente:</p>
  <input type="text" readonly value="<?php echo htmlspecialchars($syncApiUrl . '?token=***&company_code=' . $companyCode, ENT_QUOTES, 'UTF-8'); ?>" class="mono-input">
</section>

<section class="card">
  <h3>Nuevo / Editar Feed iCal</h3>
  <form method="post" class="form-grid grid-3" id="ota-ical-form">
    <input type="hidden" name="ota_ical_action" value="save_feed">
    <input type="hidden" name="id_ota_ical_feed" value="0" id="ota_ical_feed_id">
    <input type="hidden" name="ota_ical_property_id" value="<?php echo (int)$selectedPropertyId; ?>">

    <label>Nombre del feed
      <input type="text" name="feed_name" id="ota-ical-feed-name" maxlength="255" placeholder="Ej. Booking - JERONIMO2" required>
    </label>
    <label>Cuenta OTA
      <select name="id_ota_account" id="ota-ical-ota-account">
        <option value="0">(Sin cuenta OTA)</option>
        <?php foreach ($otaAccounts as $oa): ?>
          <?php $oid = isset($oa['id_ota_account']) ? (int)$oa['id_ota_account'] : 0; ?>
          <option value="<?php echo $oid; ?>">
            <?php echo htmlspecialchars((string)$oa['ota_name'] . ' [' . strtoupper((string)$oa['platform']) . ']', ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Scope
      <select name="scope_type" id="ota-ical-scope">
        <option value="room">Habitacion</option>
        <option value="category">Categoria</option>
      </select>
    </label>

    <label id="ota-ical-room-wrap">Habitacion
      <select name="id_room" id="ota-ical-room">
        <option value="0">Selecciona</option>
        <?php foreach ($rooms as $room): ?>
          <option value="<?php echo (int)$room['id_room']; ?>">
            <?php echo htmlspecialchars((string)$room['code'] . ' - ' . (string)$room['name'], ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label id="ota-ical-cat-wrap" style="display:none;">Categoria
      <select name="id_category" id="ota-ical-category">
        <option value="0">Selecciona</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?php echo (int)$cat['id_category']; ?>">
            <?php echo htmlspecialchars((string)$cat['code'] . ' - ' . (string)$cat['name'], ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Plataforma (fallback)
      <select name="platform" id="ota-ical-platform">
        <option value="booking">Booking</option>
        <option value="airbnb">Airbnb</option>
        <option value="expedia">Expedia</option>
        <option value="vrbo">VRBO</option>
        <option value="otro" selected>Otro</option>
      </select>
    </label>

    <label class="full">Import URL
      <input type="url" name="import_url" id="ota-ical-import-url" maxlength="1000" placeholder="https://.../calendar.ics">
    </label>
    <label>Zona horaria
      <input type="text" name="timezone" id="ota-ical-timezone" maxlength="64" value="America/Mexico_City">
    </label>
    <label>Intervalo sync (minutos)
      <input type="number" name="sync_interval_minutes" id="ota-ical-sync-interval" min="5" max="1440" value="30">
    </label>
    <label>Summary export
      <select name="export_summary_mode" id="ota-ical-summary-mode">
        <?php foreach ($summaryModes as $mode): ?>
          <?php $modeKey = (string)$mode; ?>
          <option value="<?php echo htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars(isset($summaryLabels[$modeKey]) ? $summaryLabels[$modeKey] : $modeKey, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="checkbox-inline"><input type="checkbox" name="import_enabled" id="ota-ical-import-enabled" value="1"> Import enabled</label>
    <label class="checkbox-inline"><input type="checkbox" name="import_ignore_our_uids" id="ota-ical-ignore-uids" value="1" checked> Ignorar UIDs propios</label>
    <label class="checkbox-inline"><input type="checkbox" name="export_enabled" id="ota-ical-export-enabled" value="1" checked> Export enabled</label>
    <label class="checkbox-inline"><input type="checkbox" name="export_include_reservations" id="ota-ical-export-reservations" value="1" checked> Incluir reservaciones</label>
    <label class="checkbox-inline"><input type="checkbox" name="export_include_room_blocks" id="ota-ical-export-blocks" value="1" checked> Incluir bloqueos</label>
    <label class="checkbox-inline"><input type="checkbox" name="is_active" id="ota-ical-is-active" value="1" checked> Feed activo</label>

    <div class="form-actions full">
      <button type="submit">Guardar feed</button>
      <button type="button" class="button-secondary" id="ota-ical-reset">Nuevo feed</button>
    </div>
  </form>
</section>

<section class="card">
  <h3>Feeds iCal</h3>
  <form method="post" style="margin-bottom:12px;">
    <input type="hidden" name="ota_ical_action" value="sync_property">
    <input type="hidden" name="ota_ical_property_id" value="<?php echo (int)$selectedPropertyId; ?>">
    <button type="submit">Sincronizar todos los feeds de esta propiedad</button>
  </form>

  <?php if (!$feeds): ?>
    <p class="muted">No hay feeds iCal registrados para esta propiedad.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Feed</th>
            <th>Scope</th>
            <th>OTA</th>
            <th>Import</th>
            <th>Export URL</th>
            <th>Ultimo sync</th>
            <th>Error</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($feeds as $feed): ?>
            <?php
              $scopeText = ((int)$feed['id_room'] > 0)
                  ? ('ROOM: ' . (string)$feed['room_code'])
                  : ('CAT: ' . (string)$feed['category_code']);
              $otaLabel = trim((string)(isset($feed['ota_name']) ? $feed['ota_name'] : ''));
              if ($otaLabel === '') {
                  $otaLabel = strtoupper((string)(isset($feed['platform']) ? $feed['platform'] : 'otro'));
              }
              $exportUrl = '';
              if (!empty($feed['export_enabled']) && !empty($feed['export_token'])) {
                  $exportUrl = $exportPrefix . rawurlencode((string)$feed['export_token']);
              }
              $feedPayload = array(
                  'id_ota_ical_feed' => (int)$feed['id_ota_ical_feed'],
                  'feed_name' => (string)$feed['feed_name'],
                  'scope_type' => ((int)$feed['id_category'] > 0 && (int)$feed['id_room'] <= 0) ? 'category' : 'room',
                  'id_room' => (int)$feed['id_room'],
                  'id_category' => (int)$feed['id_category'],
                  'id_ota_account' => (int)(isset($feed['id_ota_account']) ? $feed['id_ota_account'] : 0),
                  'platform' => (string)(isset($feed['platform']) ? $feed['platform'] : 'otro'),
                  'timezone' => (string)(isset($feed['timezone']) ? $feed['timezone'] : 'America/Mexico_City'),
                  'import_url' => (string)(isset($feed['import_url']) ? $feed['import_url'] : ''),
                  'sync_interval_minutes' => (int)(isset($feed['sync_interval_minutes']) ? $feed['sync_interval_minutes'] : 30),
                  'export_summary_mode' => (string)(isset($feed['export_summary_mode']) ? $feed['export_summary_mode'] : 'reserved'),
                  'import_enabled' => !empty($feed['import_enabled']) ? 1 : 0,
                  'import_ignore_our_uids' => !empty($feed['import_ignore_our_uids']) ? 1 : 0,
                  'export_enabled' => !empty($feed['export_enabled']) ? 1 : 0,
                  'export_include_reservations' => !empty($feed['export_include_reservations']) ? 1 : 0,
                  'export_include_room_blocks' => !empty($feed['export_include_room_blocks']) ? 1 : 0,
                  'is_active' => !empty($feed['is_active']) ? 1 : 0
              );
              $feedJson = htmlspecialchars(json_encode($feedPayload), ENT_QUOTES, 'UTF-8');
            ?>
            <tr>
              <td><?php echo (int)$feed['id_ota_ical_feed']; ?></td>
              <td><?php echo htmlspecialchars((string)$feed['feed_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($scopeText, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($otaLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo !empty($feed['import_enabled']) ? 'ON' : 'OFF'; ?></td>
              <td>
                <?php if ($exportUrl !== ''): ?>
                  <input type="text" readonly value="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mono-input">
                <?php else: ?>
                  <span class="muted">No exportado</span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars((string)($feed['last_sync_at'] ?: ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)($feed['last_error'] ?: ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <button type="button" class="button-secondary js-edit-feed" data-feed="<?php echo $feedJson; ?>">Editar</button>
                <form method="post" class="inline-form">
                  <input type="hidden" name="ota_ical_action" value="sync_feed">
                  <input type="hidden" name="ota_ical_property_id" value="<?php echo (int)$selectedPropertyId; ?>">
                  <input type="hidden" name="id_ota_ical_feed" value="<?php echo (int)$feed['id_ota_ical_feed']; ?>">
                  <button type="submit">Sync</button>
                </form>
                <form method="post" class="inline-form" onsubmit="return confirm('Eliminar feed iCal?');">
                  <input type="hidden" name="ota_ical_action" value="delete_feed">
                  <input type="hidden" name="ota_ical_property_id" value="<?php echo (int)$selectedPropertyId; ?>">
                  <input type="hidden" name="id_ota_ical_feed" value="<?php echo (int)$feed['id_ota_ical_feed']; ?>">
                  <button type="submit" class="button-secondary">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<style>
.grid-3 { display:grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap:12px; }
.grid-3 .full { grid-column: 1 / -1; }
.checkbox-inline { display:flex; align-items:center; gap:8px; }
.inline-form { display:inline-block; margin: 0 4px 4px 0; }
.js-edit-feed { margin: 0 4px 4px 0; }
.mono-input { width: 100%; min-width: 320px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
.warning { color: #ffd479; }
.align-end { align-self: end; }
@media (max-width: 980px) {
  .grid-3 { grid-template-columns: 1fr; }
  .mono-input { min-width: 220px; }
}
</style>

<script>
(function () {
  var form = document.getElementById('ota-ical-form');
  var scope = document.getElementById('ota-ical-scope');
  var roomWrap = document.getElementById('ota-ical-room-wrap');
  var catWrap = document.getElementById('ota-ical-cat-wrap');
  var feedId = document.getElementById('ota_ical_feed_id');
  var resetButton = document.getElementById('ota-ical-reset');
  var nameInput = document.getElementById('ota-ical-feed-name');
  var otaAccount = document.getElementById('ota-ical-ota-account');
  var roomInput = document.getElementById('ota-ical-room');
  var categoryInput = document.getElementById('ota-ical-category');
  var platformInput = document.getElementById('ota-ical-platform');
  var importUrlInput = document.getElementById('ota-ical-import-url');
  var timezoneInput = document.getElementById('ota-ical-timezone');
  var syncIntervalInput = document.getElementById('ota-ical-sync-interval');
  var summaryModeInput = document.getElementById('ota-ical-summary-mode');
  var importEnabled = document.getElementById('ota-ical-import-enabled');
  var ignoreUids = document.getElementById('ota-ical-ignore-uids');
  var exportEnabled = document.getElementById('ota-ical-export-enabled');
  var exportReservations = document.getElementById('ota-ical-export-reservations');
  var exportBlocks = document.getElementById('ota-ical-export-blocks');
  var isActive = document.getElementById('ota-ical-is-active');
  if (!form || !scope || !roomWrap || !catWrap || !feedId) {
    return;
  }

  var sync = function () {
    if (scope.value === 'category') {
      roomWrap.style.display = 'none';
      catWrap.style.display = '';
    } else {
      roomWrap.style.display = '';
      catWrap.style.display = 'none';
    }
  };
  scope.addEventListener('change', sync);
  sync();

  var selectHasValue = function (selectEl, expectedValue) {
    if (!selectEl) return false;
    for (var idx = 0; idx < selectEl.options.length; idx++) {
      if (String(selectEl.options[idx].value) === String(expectedValue)) {
        return true;
      }
    }
    return false;
  };

  var resetForm = function () {
    feedId.value = '0';
    if (nameInput) nameInput.value = '';
    if (otaAccount) otaAccount.value = '0';
    if (scope) scope.value = 'room';
    if (roomInput) roomInput.value = '0';
    if (categoryInput) categoryInput.value = '0';
    if (platformInput) platformInput.value = 'otro';
    if (importUrlInput) importUrlInput.value = '';
    if (timezoneInput) timezoneInput.value = 'America/Mexico_City';
    if (syncIntervalInput) syncIntervalInput.value = '30';
    if (summaryModeInput) {
      if (selectHasValue(summaryModeInput, 'reserved')) {
        summaryModeInput.value = 'reserved';
      } else if (summaryModeInput.options.length > 0) {
        summaryModeInput.value = summaryModeInput.options[0].value;
      }
    }
    if (importEnabled) importEnabled.checked = false;
    if (ignoreUids) ignoreUids.checked = true;
    if (exportEnabled) exportEnabled.checked = true;
    if (exportReservations) exportReservations.checked = true;
    if (exportBlocks) exportBlocks.checked = true;
    if (isActive) isActive.checked = true;
    sync();
  };

  if (resetButton) {
    resetButton.addEventListener('click', resetForm);
  }

  var editButtons = document.querySelectorAll('.js-edit-feed');
  for (var i = 0; i < editButtons.length; i++) {
    editButtons[i].addEventListener('click', function (ev) {
      var raw = ev.currentTarget.getAttribute('data-feed');
      if (!raw) return;
      var data = null;
      try {
        data = JSON.parse(raw);
      } catch (e) {
        return;
      }
      feedId.value = String(data.id_ota_ical_feed || 0);
      if (nameInput) nameInput.value = String(data.feed_name || '');
      if (otaAccount) otaAccount.value = String(data.id_ota_account || 0);
      if (scope) scope.value = (data.scope_type === 'category' ? 'category' : 'room');
      if (roomInput) roomInput.value = String(data.id_room || 0);
      if (categoryInput) categoryInput.value = String(data.id_category || 0);
      if (platformInput && data.platform) platformInput.value = String(data.platform);
      if (importUrlInput) importUrlInput.value = String(data.import_url || '');
      if (timezoneInput) timezoneInput.value = String(data.timezone || 'America/Mexico_City');
      if (syncIntervalInput) syncIntervalInput.value = String(data.sync_interval_minutes || 30);
      if (summaryModeInput && data.export_summary_mode) {
        var requestedSummary = String(data.export_summary_mode);
        if (selectHasValue(summaryModeInput, requestedSummary)) {
          summaryModeInput.value = requestedSummary;
        } else if (summaryModeInput.options.length > 0) {
          summaryModeInput.value = summaryModeInput.options[0].value;
        }
      }
      if (importEnabled) importEnabled.checked = !!Number(data.import_enabled || 0);
      if (ignoreUids) ignoreUids.checked = !!Number(data.import_ignore_our_uids || 0);
      if (exportEnabled) exportEnabled.checked = !!Number(data.export_enabled || 0);
      if (exportReservations) exportReservations.checked = !!Number(data.export_include_reservations || 0);
      if (exportBlocks) exportBlocks.checked = !!Number(data.export_include_room_blocks || 0);
      if (isActive) isActive.checked = !!Number(data.is_active || 0);
      sync();
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
})();
</script>
