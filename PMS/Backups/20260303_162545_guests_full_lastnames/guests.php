<?php
$currentUser = pms_current_user();
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';

if ($companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

$filters = array(
    'search' => isset($_POST['guests_filter_search']) ? (string)$_POST['guests_filter_search'] : '',
    'only_active' => isset($_POST['guests_filter_only_active']) ? (int)$_POST['guests_filter_only_active'] : 1,
);

$selectedGuestId = isset($_POST['selected_guest_id']) ? (int)$_POST['selected_guest_id'] : 0;
if (isset($_GET['guest_id'])) {
    $selectedGuestId = (int)$_GET['guest_id'];
}

$message = null;
$error = null;

$action = isset($_POST['guests_action']) ? (string)$_POST['guests_action'] : '';
if ($action === 'new_guest') {
    $selectedGuestId = 0;
} elseif ($action === 'save_guest') {
    $email = isset($_POST['guest_email']) ? trim((string)$_POST['guest_email']) : '';
    $names = isset($_POST['guest_names']) ? trim((string)$_POST['guest_names']) : '';
    $lastName = isset($_POST['guest_last_name']) ? trim((string)$_POST['guest_last_name']) : '';
    $maidenName = isset($_POST['guest_maiden_name']) ? trim((string)$_POST['guest_maiden_name']) : '';
    $phone = isset($_POST['guest_phone']) ? trim((string)$_POST['guest_phone']) : '';
    $language = isset($_POST['guest_language']) ? trim((string)$_POST['guest_language']) : '';
    $marketing = isset($_POST['guest_marketing']) ? 1 : 0;
    $blacklisted = isset($_POST['guest_blacklisted']) ? 1 : 0;
    $notes = isset($_POST['guest_notes']) ? trim((string)$_POST['guest_notes']) : '';

    if ($names === '') {
        $error = 'Nombre es obligatorio.';
    } else {
        try {
            if ($selectedGuestId > 0) {
                $pdo = pms_get_connection();
                $stmt = $pdo->prepare(
                    'UPDATE guest
                     SET
                       email = NULLIF(?, \'\'),
                       phone = NULLIF(?, \'\'),
                       names = ?,
                       last_name = NULLIF(?, \'\'),
                       maiden_name = NULLIF(?, \'\'),
                       full_name = TRIM(CONCAT(?, \' \', COALESCE(NULLIF(?, \'\'), \'\'), \' \', COALESCE(NULLIF(?, \'\'), \'\'))),
                       language = COALESCE(NULLIF(?, \'\'), \'es\'),
                       marketing_opt_in = ?,
                       blacklisted = ?,
                       notes_internal = NULLIF(?, \'\'),
                       updated_at = NOW()
                     WHERE id_guest = ?'
                );
                $stmt->execute(array(
                    $email,
                    $phone,
                    $names,
                    $lastName,
                    $maidenName,
                    $names,
                    $lastName,
                    $maidenName,
                    $language,
                    $marketing,
                    $blacklisted,
                    $notes,
                    $selectedGuestId
                ));

                if ($stmt->rowCount() >= 0) {
                    $verify = $pdo->prepare('SELECT id_guest FROM guest WHERE id_guest = ? LIMIT 1');
                    $verify->execute(array($selectedGuestId));
                    $row = $verify->fetch();
                    if ($row && isset($row['id_guest'])) {
                        $message = 'Huesped guardado.';
                    } else {
                        $error = 'No se encontro el huesped para actualizar.';
                    }
                } else {
                    $error = 'No se pudo guardar el huesped.';
                }
            } else {
                $resultSets = pms_call_procedure('sp_guest_upsert', array(
                    $email,
                    $names,
                    $lastName === '' ? null : $lastName,
                    $maidenName === '' ? null : $maidenName,
                    $phone === '' ? null : $phone,
                    $language === '' ? null : $language,
                    $marketing,
                    $blacklisted,
                    $notes === '' ? null : $notes
                ));
                $row = isset($resultSets[0][0]) ? $resultSets[0][0] : null;
                if ($row && isset($row['id_guest'])) {
                    $selectedGuestId = (int)$row['id_guest'];
                    $message = 'Huesped guardado.';
                } else {
                    $error = 'No se pudo guardar el huesped.';
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

try {
    $sets = pms_call_procedure('sp_portal_guest_data', array(
        $companyCode,
        $filters['search'] === '' ? null : $filters['search'],
        $filters['only_active'],
        $selectedGuestId
    ));
    $guestsList = isset($sets[0]) ? $sets[0] : array();
    $guestDetailSet = isset($sets[1]) ? $sets[1] : array();
    $guestReservations = isset($sets[2]) ? $sets[2] : array();
    $guestActivities = isset($sets[3]) ? $sets[3] : array();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
    $guestsList = array();
    $guestDetailSet = array();
    $guestReservations = array();
    $guestActivities = array();
}

$guestDetail = $guestDetailSet && isset($guestDetailSet[0]['id_guest']) ? $guestDetailSet[0] : null;
$selectedGuestId = $guestDetail && isset($guestDetail['id_guest']) ? (int)$guestDetail['id_guest'] : $selectedGuestId;
?>
<div class="tab-actions">
  <form method="post">
    <input type="hidden" name="selected_guest_id" value="<?php echo (int)$selectedGuestId; ?>">
    <label>
      Busqueda
      <input type="text" name="guests_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre o correo">
    </label>
    <label class="checkbox">
      <input type="checkbox" name="guests_filter_only_active" value="1" <?php echo $filters['only_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
      Solo activos
    </label>
    <button type="submit">Buscar</button>
  </form>
  <form method="post">
    <input type="hidden" name="guests_action" value="new_guest">
    <button type="submit">Nuevo huesped</button>
  </form>
</div>

<?php if ($error): ?>
  <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($message): ?>
  <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<section class="card detail-card">
  <h2><?php echo $selectedGuestId ? 'Detalle de huesped' : 'Nuevo huesped'; ?></h2>
  <form method="post" class="form-grid grid-2">
    <input type="hidden" name="guests_action" value="save_guest">
    <input type="hidden" name="selected_guest_id" value="<?php echo (int)$selectedGuestId; ?>">
    <input type="hidden" name="guests_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="guests_filter_only_active" value="<?php echo (int)$filters['only_active']; ?>">

    <label>
      Correo
      <input type="email" name="guest_email" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['email'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Nombre(s) *
      <input type="text" name="guest_names" required value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['names'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Apellido paterno
      <input type="text" name="guest_last_name" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['last_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Apellido materno
      <input type="text" name="guest_maiden_name" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['maiden_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Telefono
      <input type="text" name="guest_phone" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['phone'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Idioma
      <input type="text" name="guest_language" value="<?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['language'] : 'es', ENT_QUOTES, 'UTF-8'); ?>" placeholder="es">
    </label>
    <label class="checkbox">
      <input type="checkbox" name="guest_marketing" value="1" <?php echo $guestDetail && isset($guestDetail['marketing_opt_in']) && (int)$guestDetail['marketing_opt_in'] === 1 ? 'checked' : ''; ?>>
      Acepta comunicaciones
    </label>
    <label class="checkbox">
      <input type="checkbox" name="guest_blacklisted" value="1" <?php echo $guestDetail && isset($guestDetail['blacklisted']) && (int)$guestDetail['blacklisted'] === 1 ? 'checked' : ''; ?>>
      Lista negra
    </label>
    <label class="full">
      Notas internas
      <textarea name="guest_notes" rows="3"><?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['notes_internal'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </label>
    <label class="full">
      Notas para el huesped
      <textarea rows="2" disabled><?php echo htmlspecialchars($guestDetail ? (string)$guestDetail['notes_guest'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </label>
    <div class="form-actions full">
      <button type="submit">Guardar huesped</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Huespedes</h2>
  <?php if ($guestsList): ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Telefono</th>
            <th>Reservas</th>
            <th>Ultima estancia</th>
            <th>Activo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($guestsList as $row):
            $guestId = isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
            $isSelected = $guestId === $selectedGuestId;
            $fullName = trim((isset($row['names']) ? $row['names'] : '') . ' ' . (isset($row['last_name']) ? $row['last_name'] : ''));
          ?>
            <tr class="<?php echo $isSelected ? 'is-selected' : ''; ?>">
              <td><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($row['email']) ? (string)$row['email'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($row['phone']) ? (string)$row['phone'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo isset($row['reservation_count']) ? (int)$row['reservation_count'] : 0; ?></td>
              <td><?php echo htmlspecialchars(isset($row['last_check_out']) ? (string)$row['last_check_out'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo isset($row['is_active']) && (int)$row['is_active'] === 1 ? 'Si' : 'No'; ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="selected_guest_id" value="<?php echo $guestId; ?>">
                  <input type="hidden" name="guests_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="guests_filter_only_active" value="<?php echo (int)$filters['only_active']; ?>">
                  <button type="submit">Ver</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted">No se encontraron huespedes.</p>
  <?php endif; ?>
</section>

<?php if ($selectedGuestId): ?>
<section class="card">
  <h2>Reservas vinculadas</h2>
  <?php if ($guestReservations): ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Propiedad</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Estatus</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($guestReservations as $reservation): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$reservation['reservation_code'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$reservation['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$reservation['check_in_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$reservation['check_out_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$reservation['status'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <a class="button-link" href="index.php?view=reservations&reservation_id=<?php echo (int)$reservation['id_reservation']; ?>">Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted">El huesped no tiene reservas asociadas.</p>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Actividades reservadas</h2>
  <?php if ($guestActivities): ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Actividad</th>
            <th>Programada</th>
            <th>Estatus</th>
            <th>Participantes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($guestActivities as $activity): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$activity['activity_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$activity['scheduled_at'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string)$activity['status'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo (int)$activity['num_adults']; ?> + <?php echo (int)$activity['num_children']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted">Sin actividades asociadas.</p>
  <?php endif; ?>
</section>
<?php endif; ?>
