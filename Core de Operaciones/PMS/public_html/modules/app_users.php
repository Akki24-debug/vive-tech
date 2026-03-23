<?php
$currentUser = pms_current_user();
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyCode === '' || $companyId === 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}
pms_require_permission('users.view');

function pms_sync_user_properties_for_company($userId, $companyId, $propertyCodes, $actorUserId)
{
    if (!$userId || !$companyId) {
        return;
    }

    $codes = array();
    foreach ((array)$propertyCodes as $code) {
        $trimmed = trim((string)$code);
        if ($trimmed !== '') {
            $codes[] = $trimmed;
        }
    }
    $codes = array_values(array_unique($codes));

    $pdo = pms_get_connection();
    $stmtCompany = $pdo->prepare('SELECT code FROM company WHERE id_company = ? LIMIT 1');
    $stmtCompany->execute(array((int)$companyId));
    $companyCode = (string)$stmtCompany->fetchColumn();
    if ($companyCode === '') {
        throw new Exception('Company not found');
    }

    pms_call_procedure('sp_user_property_sync', array(
        $companyCode,
        (int)$userId,
        implode(',', $codes),
        $actorUserId
    ));
}

function pms_sync_user_roles_for_company($userId, $companyId, $roleIds, $actorUserId)
{
    if (!$userId || !$companyId) {
        return;
    }

    $ids = array();
    foreach ((array)$roleIds as $rawId) {
        $value = (int)$rawId;
        if ($value > 0) {
            $ids[] = $value;
        }
    }
    $ids = array_values(array_unique($ids));

    $pdo = pms_get_connection();
    $stmtCompany = $pdo->prepare('SELECT code FROM company WHERE id_company = ? LIMIT 1');
    $stmtCompany->execute(array((int)$companyId));
    $companyCode = (string)$stmtCompany->fetchColumn();
    if ($companyCode === '') {
        throw new Exception('Company not found');
    }

    pms_call_procedure('sp_user_role_sync', array(
        $companyCode,
        (int)$userId,
        implode(',', $ids),
        $actorUserId
    ));
}

$filters = array(
    'search' => isset($_POST['users_filter_search']) ? (string)$_POST['users_filter_search'] : '',
    'property_code' => isset($_POST['users_filter_property']) ? (string)$_POST['users_filter_property'] : '',
    'only_active' => isset($_POST['users_filter_only_active']) ? (int)$_POST['users_filter_only_active'] : 1,
);
$phoneCountries = function_exists('pms_phone_country_rows') ? pms_phone_country_rows() : array();
$defaultPhonePrefix = function_exists('pms_phone_prefix_default') ? pms_phone_prefix_default() : '+52';
$phonePrefixDialMap = function_exists('pms_phone_prefix_dials_map') ? pms_phone_prefix_dials_map() : array($defaultPhonePrefix => true);

$selectedUserId = isset($_POST['selected_user_id']) ? (int)$_POST['selected_user_id'] : 0;
if (isset($_GET['user_id'])) {
    $selectedUserId = (int)$_GET['user_id'];
}

$message = null;
$error = null;

$action = isset($_POST['users_action']) ? (string)$_POST['users_action'] : '';
if ($action === 'new_user') {
    $selectedUserId = 0;
} elseif ($action === 'toggle_active_filter') {
    $filters['only_active'] = $filters['only_active'] ? 0 : 1;
} elseif ($action === 'save_user') {
    $incomingId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? trim((string)$_POST['password']) : '';
    $names = isset($_POST['names']) ? trim((string)$_POST['names']) : '';
    $lastName = isset($_POST['last_name']) ? trim((string)$_POST['last_name']) : '';
    $maidenName = isset($_POST['maiden_name']) ? trim((string)$_POST['maiden_name']) : '';
    $phoneRaw = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
    $phonePrefixInput = isset($_POST['phone_prefix']) ? trim((string)$_POST['phone_prefix']) : '';
    if (function_exists('pms_phone_normalize_parts')) {
        $phoneParts = pms_phone_normalize_parts($phoneRaw, $phonePrefixInput, $defaultPhonePrefix);
        $phone = isset($phoneParts['full']) ? (string)$phoneParts['full'] : '';
    } else {
        $phonePrefix = function_exists('pms_phone_extract_dial')
            ? pms_phone_extract_dial($phonePrefixInput, $defaultPhonePrefix)
            : $defaultPhonePrefix;
        if ($phoneRaw !== '' && preg_match('/^(\+\d{1,4})\s*(.*)$/', $phoneRaw, $matches)) {
            $candidatePrefix = isset($matches[1]) ? (string)$matches[1] : '';
            if ($candidatePrefix !== '' && isset($phonePrefixDialMap[$candidatePrefix])) {
                $phonePrefix = $candidatePrefix;
                $phoneRaw = trim((string)$matches[2]);
            }
        }
        $phone = $phoneRaw === '' ? '' : trim($phonePrefix . ' ' . $phoneRaw);
    }
    $locale = isset($_POST['locale']) ? trim((string)$_POST['locale']) : '';
    $timezone = isset($_POST['timezone']) ? trim((string)$_POST['timezone']) : '';
    $displayName = isset($_POST['display_name']) ? trim((string)$_POST['display_name']) : '';
    $isOwner = isset($_POST['is_owner']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';
    $selectedProperties = isset($_POST['assigned_properties']) ? (array)$_POST['assigned_properties'] : array();
    $selectedRoles = isset($_POST['assigned_roles']) ? (array)$_POST['assigned_roles'] : array();

    pms_require_permission($incomingId > 0 ? 'users.edit' : 'users.create');
    pms_require_permission('users.assign_properties');
    pms_require_permission('users.assign_roles');

    if ($email === '' || $names === '') {
        $error = 'Correo y nombre son obligatorios.';
    } else {
        try {
            $resultSets = pms_call_procedure('sp_app_user_upsert', array(
                $companyCode,
                $incomingId,
                $email,
                $password === '' ? null : $password,
                $names,
                $lastName === '' ? null : $lastName,
                $maidenName === '' ? null : $maidenName,
                $phone === '' ? null : $phone,
                $locale === '' ? null : $locale,
                $timezone === '' ? null : $timezone,
                $isOwner,
                $isActive,
                $displayName === '' ? null : $displayName,
                $notes === '' ? null : $notes,
                $actorUserId
            ));
            $savedRow = isset($resultSets[0][0]) ? $resultSets[0][0] : null;
            if ($savedRow && isset($savedRow['id_user'])) {
                $selectedUserId = (int)$savedRow['id_user'];
                pms_sync_user_properties_for_company($selectedUserId, $companyId, $selectedProperties, $actorUserId);
                pms_sync_user_roles_for_company($selectedUserId, $companyId, $selectedRoles, $actorUserId);
                $message = $incomingId > 0 ? 'Usuario actualizado.' : 'Usuario creado.';
            } else {
                $error = 'No se pudo guardar el usuario.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

try {
    $sets = pms_call_procedure('sp_portal_app_user_data', array(
        $companyCode,
        $filters['search'] === '' ? null : $filters['search'],
        $filters['property_code'] === '' ? null : $filters['property_code'],
        $filters['only_active'],
        $selectedUserId
    ));
    $usersList = isset($sets[0]) ? $sets[0] : array();
    $userDetailSet = isset($sets[1]) ? $sets[1] : array();
    $propertiesSet = isset($sets[2]) ? $sets[2] : array();
    $rolesSet = isset($sets[3]) ? $sets[3] : array();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
    $usersList = array();
    $userDetailSet = array();
    $propertiesSet = array();
    $rolesSet = array();
}

$detail = $userDetailSet && isset($userDetailSet[0]['id_user']) ? $userDetailSet[0] : null;
$assignedPropertyCodes = array();
foreach ($propertiesSet as $row) {
    if (isset($row['is_assigned']) && (int)$row['is_assigned'] === 1) {
        $assignedPropertyCodes[] = isset($row['property_code']) ? (string)$row['property_code'] : '';
    }
}

$assignedRoleIds = array();
foreach ($rolesSet as $row) {
    if (isset($row['is_assigned']) && (int)$row['is_assigned'] === 1) {
        $assignedRoleIds[] = isset($row['id_role']) ? (int)$row['id_role'] : 0;
    }
}

$detailPhoneSource = $detail ? (string)$detail['phone'] : '';
$detailPhonePrefixSource = '';
if ($action === 'save_user' && $error !== null) {
    $detailPhoneSource = isset($_POST['phone']) ? trim((string)$_POST['phone']) : $detailPhoneSource;
    $detailPhonePrefixSource = isset($_POST['phone_prefix']) ? trim((string)$_POST['phone_prefix']) : '';
}
if (function_exists('pms_phone_normalize_parts')) {
    $detailPhoneParts = pms_phone_normalize_parts($detailPhoneSource, $detailPhonePrefixSource, $defaultPhonePrefix);
    $detailPhonePrefixForm = isset($detailPhoneParts['prefix']) ? (string)$detailPhoneParts['prefix'] : $defaultPhonePrefix;
    $detailPhoneForm = isset($detailPhoneParts['phone']) ? (string)$detailPhoneParts['phone'] : '';
} else {
    $detailPhonePrefixForm = $defaultPhonePrefix;
    $detailPhoneForm = $detailPhoneSource;
}

?>
<div class="tab-actions">
  <form method="post">
    <input type="hidden" name="selected_user_id" value="<?php echo (int)$selectedUserId; ?>">
    <label>
      Busqueda
      <input type="text" name="users_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre o correo">
    </label>
    <label>
      Propiedad
      <select name="users_filter_property" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach ($propertiesSet as $propertyRow):
          $code = isset($propertyRow['property_code']) ? (string)$propertyRow['property_code'] : '';
          $name = isset($propertyRow['property_name']) ? (string)$propertyRow['property_name'] : '';
          if ($code === '') {
              continue;
          }
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $filters['property_code'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="users_filter_only_active" value="1" <?php echo $filters['only_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
      Solo activos
    </label>
    <button type="submit">Aplicar</button>
  </form>
  <form method="post">
    <input type="hidden" name="users_action" value="new_user">
    <button type="submit">Nuevo usuario</button>
  </form>
</div>

<?php if ($error): ?>
  <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($message): ?>
  <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<section class="card detail-card">
  <h2><?php echo $selectedUserId ? 'Detalle de usuario' : 'Nuevo usuario'; ?></h2>
  <form method="post" class="form-grid grid-3">
    <input type="hidden" name="users_action" value="save_user">
    <input type="hidden" name="user_id" value="<?php echo (int)$selectedUserId; ?>">
    <input type="hidden" name="users_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="users_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="users_filter_only_active" value="<?php echo (int)$filters['only_active']; ?>">

    <label>
      Correo *
      <input type="email" name="email" required value="<?php echo htmlspecialchars($detail ? (string)$detail['email'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Contrasena
      <input type="password" name="password" placeholder="Dejar vacio para mantener">
    </label>
    <label>
      Nombre(s) *
      <input type="text" name="names" required value="<?php echo htmlspecialchars($detail ? (string)$detail['names'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Apellido paterno
      <input type="text" name="last_name" value="<?php echo htmlspecialchars($detail ? (string)$detail['last_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Apellido materno
      <input type="text" name="maiden_name" value="<?php echo htmlspecialchars($detail ? (string)$detail['maiden_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Telefono
      <div class="phone-input">
        <select name="phone_prefix" aria-label="Prefijo telefono usuario">
          <?php
            $userPrefixSelected = false;
            foreach ($phoneCountries as $phoneCountry):
                $prefix = isset($phoneCountry['dial']) ? (string)$phoneCountry['dial'] : '';
                if ($prefix === '') {
                    continue;
                }
                $countryName = isset($phoneCountry['name_es']) ? (string)$phoneCountry['name_es'] : '';
                $isSelected = (!$userPrefixSelected && $prefix === $detailPhonePrefixForm);
                if ($isSelected) {
                    $userPrefixSelected = true;
                }
          ?>
            <option value="<?php echo htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($countryName . ' (' . $prefix . ')', ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="tel" name="phone" value="<?php echo htmlspecialchars($detailPhoneForm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Telefono / WhatsApp">
      </div>
    </label>
    <label>
      Alias
      <input type="text" name="display_name" value="<?php echo htmlspecialchars($detail ? (string)$detail['display_name'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Locale
      <input type="text" name="locale" placeholder="es-MX" value="<?php echo htmlspecialchars($detail ? (string)$detail['locale'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Zona horaria
      <input type="text" name="timezone" placeholder="America/Mexico_City" value="<?php echo htmlspecialchars($detail ? (string)$detail['timezone'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label class="checkbox">
      <input type="checkbox" name="is_owner" value="1" <?php echo $detail && isset($detail['is_owner']) && (int)$detail['is_owner'] === 1 ? 'checked' : ''; ?>>
      Propietario
    </label>
    <label class="checkbox">
      <input type="checkbox" name="is_active" value="1" <?php echo !$detail || !isset($detail['is_active']) || (int)$detail['is_active'] === 1 ? 'checked' : ''; ?>>
      Activo
    </label>
    <label class="full">
      Notas
      <textarea name="notes" rows="3"><?php echo htmlspecialchars($detail ? (string)$detail['notes'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </label>

    <fieldset class="full related-group">
      <legend>Propiedades asignadas</legend>
      <div class="checkbox-grid">
        <?php foreach ($propertiesSet as $propertyRow):
          $code = isset($propertyRow['property_code']) ? (string)$propertyRow['property_code'] : '';
          $name = isset($propertyRow['property_name']) ? (string)$propertyRow['property_name'] : '';
          if ($code === '') {
              continue;
          }
          $checked = in_array($code, $assignedPropertyCodes, true);
        ?>
          <label class="checkbox">
            <input type="checkbox" name="assigned_properties[]" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $checked ? 'checked' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="full related-group">
      <legend>Roles asignados</legend>
      <div class="checkbox-grid">
        <?php foreach ($rolesSet as $roleRow):
          $roleId = isset($roleRow['id_role']) ? (int)$roleRow['id_role'] : 0;
          if ($roleId === 0) {
              continue;
          }
          $roleName = isset($roleRow['name']) ? (string)$roleRow['name'] : '';
          $propertyName = isset($roleRow['property_name']) ? (string)$roleRow['property_name'] : '';
          $checked = in_array($roleId, $assignedRoleIds, true);
        ?>
          <label class="checkbox">
            <input type="checkbox" name="assigned_roles[]" value="<?php echo $roleId; ?>" <?php echo $checked ? 'checked' : ''; ?>>
            <?php echo htmlspecialchars($propertyName . ' - ' . $roleName, ENT_QUOTES, 'UTF-8'); ?>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="muted">Los permisos se asignan a nivel rol. Puedes administrarlos en el editor de roles.</p>
    </fieldset>

    <div class="form-actions full">
      <button type="submit">Guardar usuario</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Usuarios</h2>
  <?php if ($usersList): ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Telefono</th>
            <th>Propiedades</th>
            <th>Roles</th>
            <th>Activo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usersList as $row):
            $idUser = isset($row['id_user']) ? (int)$row['id_user'] : 0;
            $isSelected = $idUser === (int)$selectedUserId;
            $display = isset($row['display_name']) && $row['display_name'] !== '' ? $row['display_name'] : (isset($row['names']) ? $row['names'] : '');
          ?>
            <tr class="<?php echo $isSelected ? 'is-selected' : ''; ?>">
              <td><?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($row['email']) ? (string)$row['email'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($row['phone']) ? (string)$row['phone'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($row['property_codes']) ? (string)$row['property_codes'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(isset($row['role_names']) ? (string)$row['role_names'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo isset($row['is_active']) && (int)$row['is_active'] === 1 ? 'Si' : 'No'; ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="selected_user_id" value="<?php echo $idUser; ?>">
                  <input type="hidden" name="users_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="users_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="users_filter_only_active" value="<?php echo (int)$filters['only_active']; ?>">
                  <button type="submit">Ver</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted">No se encontraron usuarios con los filtros seleccionados.</p>
  <?php endif; ?>
</section>
