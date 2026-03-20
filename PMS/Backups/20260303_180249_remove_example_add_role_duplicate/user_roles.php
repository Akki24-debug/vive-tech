<?php
$currentUser = pms_current_user();
$companyCode = isset($currentUser['company_code']) ? (string)$currentUser['company_code'] : '';
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyCode === '' || $companyId === 0) {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

pms_require_permission('users.manage_roles');

if (!function_exists('pms_user_roles_load_role_catalog_for_company')) {
    function pms_user_roles_load_role_catalog_for_company($companyId, $search = '', $propertyCode = '')
    {
        $pdo = pms_get_connection();
        $sql = 'SELECT
                    r.id_role,
                    r.name,
                    r.description,
                    r.id_property,
                    r.is_system,
                    r.is_active,
                    pr.code AS property_code,
                    CASE WHEN r.id_property IS NULL THEN \'Global\' ELSE pr.name END AS property_name
                FROM role r
                LEFT JOIN property pr
                  ON pr.id_property = r.id_property
                WHERE r.deleted_at IS NULL
                  AND (
                    r.id_property IS NULL
                    OR (
                      pr.id_company = :company_id
                      AND pr.deleted_at IS NULL
                    )
                  )';
        if ($search !== '') {
            $sql .= ' AND (r.name LIKE :search OR r.description LIKE :search)';
        }
        if ($propertyCode !== '') {
            if (strtoupper($propertyCode) === '__GLOBAL__') {
                $sql .= ' AND r.id_property IS NULL';
            } else {
                $sql .= ' AND pr.code = :property_code';
            }
        }
        $sql .= ' ORDER BY
                    CASE WHEN r.id_property IS NULL THEN 0 ELSE 1 END,
                    pr.name,
                    r.name';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_id', (int)$companyId, PDO::PARAM_INT);
        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        if ($propertyCode !== '' && strtoupper($propertyCode) !== '__GLOBAL__') {
            $stmt->bindValue(':property_code', $propertyCode, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

if (!function_exists('pms_user_roles_load_role_detail_for_company')) {
    function pms_user_roles_load_role_detail_for_company($companyId, $roleId)
    {
        $roleId = (int)$roleId;
        if ($roleId <= 0) {
            return null;
        }
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT
                r.id_role,
                r.name,
                r.description,
                r.id_property,
                r.is_system,
                r.is_active,
                pr.code AS property_code,
                CASE WHEN r.id_property IS NULL THEN \'Global\' ELSE pr.name END AS property_name
             FROM role r
             LEFT JOIN property pr
               ON pr.id_property = r.id_property
             WHERE r.id_role = ?
               AND r.deleted_at IS NULL
               AND (
                 r.id_property IS NULL
                 OR (
                   pr.id_company = ?
                   AND pr.deleted_at IS NULL
                 )
               )
             LIMIT 1'
        );
        $stmt->execute(array($roleId, (int)$companyId));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }
}

if (!function_exists('pms_user_roles_load_permission_catalog')) {
    function pms_user_roles_load_permission_catalog()
    {
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT
                id_permission,
                code,
                permission_name,
                description,
                resource,
                action
             FROM permission
             WHERE deleted_at IS NULL
               AND is_active = 1
             ORDER BY resource, action, code'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

if (!function_exists('pms_user_roles_load_role_permission_codes')) {
    function pms_user_roles_load_role_permission_codes($roleId)
    {
        $roleId = (int)$roleId;
        if ($roleId <= 0) {
            return array();
        }
        $pdo = pms_get_connection();
        $stmt = $pdo->prepare(
            'SELECT p.code
             FROM role_permission rp
             JOIN permission p
               ON p.id_permission = rp.id_permission
              AND p.deleted_at IS NULL
              AND p.is_active = 1
             WHERE rp.id_role = ?
               AND rp.deleted_at IS NULL
               AND rp.is_active = 1
               AND COALESCE(rp.allow, 1) = 1
             ORDER BY p.code'
        );
        $stmt->execute(array($roleId));
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (!function_exists('pms_user_roles_permission_label')) {
    function pms_user_roles_permission_label($resource, $action, $fallback, $code)
    {
        $resource = strtolower(trim((string)$resource));
        $action = strtolower(trim((string)$action));

        $resourceObjectLabels = array(
            'dashboard' => 'dashboard',
            'calendar' => 'calendario',
            'reservations' => 'reservacion',
            'guests' => 'huesped',
            'activities' => 'actividad',
            'properties' => 'propiedad',
            'rooms' => 'habitacion',
            'categories' => 'categoria',
            'rateplans' => 'tarifa',
            'messages' => 'mensaje',
            'otas' => 'OTA',
            'ota_ical' => 'feed iCal',
            'sale_items' => 'concepto',
            'payments' => 'pago',
            'incomes' => 'ingreso',
            'obligations' => 'obligacion',
            'reports' => 'reporte',
            'settings' => 'configuracion',
            'users' => 'usuario'
        );
        $customActionLabels = array(
            'create_hold' => 'Crear apartado',
            'move_reservation' => 'Mover reservacion',
            'manage_block' => 'Gestionar bloqueos',
            'register_payment' => 'Registrar pago',
            'status_change' => 'Cambiar estatus',
            'move_property' => 'Mover entre propiedades',
            'manage_folio' => 'Gestionar folios',
            'post_charge' => 'Registrar cargos',
            'post_payment' => 'Registrar pagos',
            'note_edit' => 'Editar notas',
            'template_edit' => 'Editar plantillas',
            'relations_edit' => 'Editar relaciones',
            'assign_roles' => 'Asignar roles',
            'assign_properties' => 'Asignar propiedades',
            'manage_roles' => 'Gestionar roles',
            'reconcile' => 'Conciliar',
            'sync' => 'Sincronizar',
            'pay' => 'Pagar',
            'run' => 'Ejecutar',
            'design' => 'Disenar'
        );
        $verbLabels = array(
            'view' => 'Ver',
            'create' => 'Crear',
            'edit' => 'Editar',
            'delete' => 'Eliminar',
            'book' => 'Reservar',
            'cancel' => 'Cancelar',
            'send' => 'Enviar',
            'refund' => 'Aplicar reembolso'
        );

        if (isset($customActionLabels[$action])) {
            return $customActionLabels[$action] . (isset($resourceObjectLabels[$resource]) ? ' de ' . $resourceObjectLabels[$resource] : '');
        }
        if (isset($verbLabels[$action]) && isset($resourceObjectLabels[$resource])) {
            return $verbLabels[$action] . ' ' . $resourceObjectLabels[$resource];
        }
        if ($fallback !== '') {
            return $fallback;
        }
        return $code;
    }
}

if (!function_exists('pms_user_roles_permission_group')) {
    function pms_user_roles_permission_group($resource)
    {
        $resource = strtolower(trim((string)$resource));
        if (in_array($resource, array('dashboard', 'calendar', 'reservations', 'guests', 'activities', 'messages'), true)) {
            return 'operations';
        }
        if (in_array($resource, array('properties', 'rooms', 'categories', 'rateplans', 'sale_items', 'settings', 'users'), true)) {
            return 'administration';
        }
        if (in_array($resource, array('payments', 'incomes', 'obligations', 'reports'), true)) {
            return 'finance';
        }
        if (in_array($resource, array('otas', 'ota_ical'), true)) {
            return 'integrations';
        }
        if ($resource === '') {
            return 'general';
        }
        return 'other';
    }
}

$filters = array(
    'search' => isset($_POST['roles_filter_search']) ? trim((string)$_POST['roles_filter_search']) : '',
    'property_code' => isset($_POST['roles_filter_property']) ? trim((string)$_POST['roles_filter_property']) : ''
);

$selectedRoleId = isset($_POST['selected_role_id']) ? (int)$_POST['selected_role_id'] : 0;
if (isset($_GET['role_id'])) {
    $selectedRoleId = (int)$_GET['role_id'];
}

$message = null;
$error = null;
$action = isset($_POST['user_roles_action']) ? (string)$_POST['user_roles_action'] : '';

if (!function_exists('pms_user_roles_format_sp_missing_error')) {
    function pms_user_roles_format_sp_missing_error($message, $procedureName)
    {
        $raw = (string)$message;
        if (stripos($raw, '1305') === false || stripos($raw, (string)$procedureName) === false) {
            return null;
        }
        $dbName = '';
        try {
            $pdo = pms_get_connection();
            $stmt = $pdo->query('SELECT DATABASE()');
            $dbName = $stmt ? (string)$stmt->fetchColumn() : '';
        } catch (Exception $ignore) {
            $dbName = '';
        }
        $where = $dbName !== '' ? (' en la BD activa `' . $dbName . '`') : '';
        return 'No existe el procedimiento `' . $procedureName . '`' . $where . '. Ejecuta `bd pms/rbac_install_current_schema.sql` en esa misma BD y recarga la pagina.';
    }
}

$formRole = array(
    'name' => '',
    'description' => '',
    'property_code' => '',
    'is_system' => 0,
    'is_active' => 1
);
$formPermissionCodes = array();

if ($action === 'new_role_editor') {
    $selectedRoleId = 0;
} elseif ($action === 'select_role_editor') {
    $selectedRoleId = isset($_POST['target_role_id']) ? (int)$_POST['target_role_id'] : 0;
} elseif ($action === 'save_role_editor') {
    $incomingRoleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $name = isset($_POST['role_name']) ? trim((string)$_POST['role_name']) : '';
    $description = isset($_POST['role_description']) ? trim((string)$_POST['role_description']) : '';
    $propertyCode = isset($_POST['role_property_code']) ? trim((string)$_POST['role_property_code']) : '';
    $isSystem = isset($_POST['role_is_system']) ? 1 : 0;
    $isActive = isset($_POST['role_is_active']) ? 1 : 0;
    $postedPermissionCodes = isset($_POST['role_permission_codes']) ? (array)$_POST['role_permission_codes'] : array();
    $cleanCodes = array();
    foreach ($postedPermissionCodes as $code) {
        $value = trim((string)$code);
        if ($value !== '') {
            $cleanCodes[] = $value;
        }
    }
    $formRole = array(
        'name' => $name,
        'description' => $description,
        'property_code' => $propertyCode,
        'is_system' => $isSystem,
        'is_active' => $isActive
    );
    $formPermissionCodes = array_values(array_unique($cleanCodes));

    if ($name === '') {
        $error = 'El nombre del rol es obligatorio.';
    } else {
        try {
            $roleSets = pms_call_procedure('sp_role_upsert', array(
                $companyCode,
                $incomingRoleId,
                $propertyCode === '' ? null : $propertyCode,
                $name,
                $description === '' ? null : $description,
                $isSystem,
                $isActive,
                $actorUserId
            ));
            $savedRole = isset($roleSets[0][0]) ? $roleSets[0][0] : null;
            if (!$savedRole || !isset($savedRole['id_role'])) {
                $error = 'No se pudo guardar el rol.';
            } else {
                $selectedRoleId = (int)$savedRole['id_role'];
                pms_call_procedure('sp_role_permission_sync', array(
                    $companyCode,
                    $selectedRoleId,
                    implode(',', $formPermissionCodes),
                    $actorUserId
                ));
                $message = $incomingRoleId > 0 ? 'Rol actualizado.' : 'Rol creado.';
            }
        } catch (Exception $e) {
            $friendly = pms_user_roles_format_sp_missing_error($e->getMessage(), 'sp_role_upsert');
            if ($friendly === null) {
                $friendly = pms_user_roles_format_sp_missing_error($e->getMessage(), 'sp_role_permission_sync');
            }
            $error = $friendly !== null ? $friendly : $e->getMessage();
        }
    }
}

$properties = pms_fetch_properties($companyId);
$rolesCatalog = pms_user_roles_load_role_catalog_for_company($companyId, $filters['search'], $filters['property_code']);
$permissionCatalog = pms_user_roles_load_permission_catalog();

$preservePostedValues = ($action === 'save_role_editor' && $error !== null);
$selectedRole = $selectedRoleId > 0 ? pms_user_roles_load_role_detail_for_company($companyId, $selectedRoleId) : null;
if (!$preservePostedValues && $selectedRole) {
    $formRole = array(
        'name' => isset($selectedRole['name']) ? (string)$selectedRole['name'] : '',
        'description' => isset($selectedRole['description']) ? (string)$selectedRole['description'] : '',
        'property_code' => isset($selectedRole['property_code']) ? (string)$selectedRole['property_code'] : '',
        'is_system' => isset($selectedRole['is_system']) ? (int)$selectedRole['is_system'] : 0,
        'is_active' => isset($selectedRole['is_active']) ? (int)$selectedRole['is_active'] : 1
    );
    $formPermissionCodes = pms_user_roles_load_role_permission_codes($selectedRoleId);
}

$groupLabels = array(
    'operations' => 'Operaciones',
    'administration' => 'Administracion',
    'finance' => 'Finanzas',
    'integrations' => 'Integraciones',
    'general' => 'General',
    'other' => 'Otros'
);
$groupOrder = array('operations', 'administration', 'finance', 'integrations', 'general', 'other');
$permissionGroups = array();
foreach ($permissionCatalog as $permissionRow) {
    $resource = isset($permissionRow['resource']) ? (string)$permissionRow['resource'] : '';
    $actionCode = isset($permissionRow['action']) ? (string)$permissionRow['action'] : '';
    $groupKey = pms_user_roles_permission_group($resource);
    if (!isset($permissionGroups[$groupKey])) {
        $permissionGroups[$groupKey] = array(
            'label' => isset($groupLabels[$groupKey]) ? $groupLabels[$groupKey] : 'Otros',
            'items' => array()
        );
    }
    $permissionCode = isset($permissionRow['code']) ? (string)$permissionRow['code'] : '';
    $permissionName = isset($permissionRow['permission_name']) ? (string)$permissionRow['permission_name'] : '';
    $permissionGroups[$groupKey]['items'][] = array(
        'code' => $permissionCode,
        'label' => pms_user_roles_permission_label($resource, $actionCode, $permissionName, $permissionCode),
        'description' => isset($permissionRow['description']) ? (string)$permissionRow['description'] : ''
    );
}
foreach ($permissionGroups as $groupKey => $groupData) {
    usort($permissionGroups[$groupKey]['items'], function ($a, $b) {
        return strcasecmp((string)$a['label'], (string)$b['label']);
    });
}
$selectedPermissionSet = array_fill_keys($formPermissionCodes, true);
?>

<div class="tab-actions">
  <form method="post">
    <input type="hidden" name="selected_role_id" value="<?php echo (int)$selectedRoleId; ?>">
    <label>
      Busqueda
      <input type="text" name="roles_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre o descripcion">
    </label>
    <label>
      Alcance
      <select name="roles_filter_property" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="__GLOBAL__" <?php echo strtoupper($filters['property_code']) === '__GLOBAL__' ? 'selected' : ''; ?>>Global</option>
        <?php foreach ($properties as $propertyRow):
          $code = isset($propertyRow['code']) ? (string)$propertyRow['code'] : '';
          $name = isset($propertyRow['name']) ? (string)$propertyRow['name'] : '';
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
    <button type="submit">Aplicar</button>
  </form>
  <form method="post">
    <input type="hidden" name="user_roles_action" value="new_role_editor">
    <input type="hidden" name="roles_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="roles_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit">Nuevo rol</button>
  </form>
</div>

<?php if ($error): ?>
  <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($message): ?>
  <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<section class="card">
  <h2>Roles registrados</h2>
  <?php if ($rolesCatalog): ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Alcance</th>
            <th>Sistema</th>
            <th>Activo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rolesCatalog as $roleRow):
            $roleId = isset($roleRow['id_role']) ? (int)$roleRow['id_role'] : 0;
            if ($roleId <= 0) {
                continue;
            }
            $isSelected = $roleId === (int)$selectedRoleId;
            $scopeName = isset($roleRow['property_name']) ? (string)$roleRow['property_name'] : 'Global';
          ?>
            <tr class="<?php echo $isSelected ? 'is-selected' : ''; ?>">
              <td><?php echo htmlspecialchars(isset($roleRow['name']) ? (string)$roleRow['name'] : '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($scopeName, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo isset($roleRow['is_system']) && (int)$roleRow['is_system'] === 1 ? 'Si' : 'No'; ?></td>
              <td><?php echo isset($roleRow['is_active']) && (int)$roleRow['is_active'] === 1 ? 'Si' : 'No'; ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="user_roles_action" value="select_role_editor">
                  <input type="hidden" name="target_role_id" value="<?php echo $roleId; ?>">
                  <input type="hidden" name="roles_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="roles_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
                  <button type="submit">Editar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="muted">No se encontraron roles con los filtros seleccionados.</p>
  <?php endif; ?>
</section>

<section class="card detail-card">
  <h2><?php echo $selectedRoleId > 0 ? 'Editar rol' : 'Nuevo rol'; ?></h2>
  <form method="post" class="form-grid grid-3">
    <input type="hidden" name="user_roles_action" value="save_role_editor">
    <input type="hidden" name="role_id" value="<?php echo (int)$selectedRoleId; ?>">
    <input type="hidden" name="roles_filter_search" value="<?php echo htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="roles_filter_property" value="<?php echo htmlspecialchars($filters['property_code'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="selected_role_id" value="<?php echo (int)$selectedRoleId; ?>">

    <label>
      Nombre *
      <input type="text" name="role_name" required value="<?php echo htmlspecialchars($formRole['name'], ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
      Alcance del rol
      <select name="role_property_code">
        <option value="">Global</option>
        <?php foreach ($properties as $propertyRow):
          $code = isset($propertyRow['code']) ? (string)$propertyRow['code'] : '';
          $name = isset($propertyRow['name']) ? (string)$propertyRow['name'] : '';
          if ($code === '') {
              continue;
          }
        ?>
          <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formRole['property_code'] === $code ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="role_is_active" value="1" <?php echo (int)$formRole['is_active'] === 1 ? 'checked' : ''; ?>>
      Activo
    </label>
    <label class="checkbox">
      <input type="checkbox" name="role_is_system" value="1" <?php echo (int)$formRole['is_system'] === 1 ? 'checked' : ''; ?>>
      Rol de sistema
    </label>
    <label class="full">
      Descripcion
      <textarea name="role_description" rows="3"><?php echo htmlspecialchars($formRole['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
    </label>

    <fieldset class="full related-group">
      <legend>Permisos del rol</legend>
      <?php foreach ($groupOrder as $groupKey):
        if (!isset($permissionGroups[$groupKey]) || empty($permissionGroups[$groupKey]['items'])) {
            continue;
        }
      ?>
        <div class="permission-group">
          <h3><?php echo htmlspecialchars($permissionGroups[$groupKey]['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
          <div class="checkbox-grid">
            <?php foreach ($permissionGroups[$groupKey]['items'] as $permissionItem):
              $code = isset($permissionItem['code']) ? (string)$permissionItem['code'] : '';
              if ($code === '') {
                  continue;
              }
              $checked = isset($selectedPermissionSet[$code]);
            ?>
              <label class="checkbox" title="<?php echo htmlspecialchars(isset($permissionItem['description']) ? (string)$permissionItem['description'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="checkbox" name="role_permission_codes[]" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars(isset($permissionItem['label']) ? (string)$permissionItem['label'] : $code, ENT_QUOTES, 'UTF-8'); ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>

    <div class="form-actions full">
      <button type="submit">Guardar rol</button>
    </div>
  </form>
</section>
