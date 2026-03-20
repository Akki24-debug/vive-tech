<?php
function pms_render_header($title = 'PMS Console')
{
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $basePath = rtrim(dirname($scriptName), '/\\');
    $basePrefix = ($basePath === '' || $basePath === '.') ? '' : $basePath;
    $assetPath = $basePrefix . '/assets/style.css';
    $scriptPath = $basePrefix . '/assets/app.js';
    $assetFile = dirname(__DIR__) . '/assets/style.css';
    $scriptFile = dirname(__DIR__) . '/assets/app.js';
    $assetVersion = is_file($assetFile) ? (string)filemtime($assetFile) : (string)time();
    $scriptVersion = is_file($scriptFile) ? (string)filemtime($scriptFile) : (string)time();
    $assetHref = $assetPath . '?v=' . rawurlencode($assetVersion);
    $scriptSrc = $scriptPath . '?v=' . rawurlencode($scriptVersion);
    $currentUser = pms_current_user();
    $can = function ($permissionCode) {
        if ($permissionCode === '' || $permissionCode === null) {
            return true;
        }
        return pms_user_can((string)$permissionCode);
    };
    $companyName = $title;
    if ($currentUser && isset($currentUser['company_name']) && $currentUser['company_name'] !== '') {
        $companyName = (string)$currentUser['company_name'];
    }
    $themeClass = 'theme-default';
    if ($currentUser && isset($currentUser['company_code'])) {
        try {
            $themeSets = pms_call_procedure('sp_pms_theme_data', array((string)$currentUser['company_code']));
            $themeRow = isset($themeSets[0][0]) ? $themeSets[0][0] : null;
            if ($themeRow && isset($themeRow['theme_code']) && $themeRow['theme_code'] !== '') {
                $themeClass = 'theme-' . preg_replace('/[^a-z0-9_-]/i', '', (string)$themeRow['theme_code']);
            }
        } catch (Exception $e) {
            $themeClass = 'theme-default';
        }
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=0.25,maximum-scale=5,user-scalable=yes,viewport-fit=cover">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($assetHref, ENT_QUOTES, 'UTF-8'); ?>">
  <script src="<?php echo htmlspecialchars($scriptSrc, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</head>
<body class="<?php echo htmlspecialchars($themeClass, ENT_QUOTES, 'UTF-8'); ?>">
  <header class="app-header">
    <div class="header-bar">
      <button class="nav-toggle" type="button" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <a class="brand-link" href="index.php" title="Dashboard">
        <span class="brand-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="M12 3.5L3.5 10.5V20a1 1 0 0 0 1 1h5.5v-6.5h4V21h5.5a1 1 0 0 0 1-1v-9.5L12 3.5z" />
          </svg>
        </span>
        <span class="brand-title"><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></span>
      </a>
      <div class="header-actions">
        <?php if ($can('calendar.view')): ?>
        <a class="icon-button calendar-button" href="index.php?view=calendar" title="Calendario">
          <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
            <path d="M7 3.5a1 1 0 0 1 1 1V6h8V4.5a1 1 0 1 1 2 0V6h1a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h1V4.5a1 1 0 0 1 2 0V6zm13 6.5H4V19h16v-9z" />
          </svg>
        </a>
        <?php endif; ?>
        <?php if ($currentUser): ?>
        <div class="user-meta">
          <span><?php echo htmlspecialchars($currentUser['display_name'] ? $currentUser['display_name'] : $currentUser['email'], ENT_QUOTES, 'UTF-8'); ?></span>
          <a href="logout.php">Cerrar sesion</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <div class="sidebar-backdrop" data-sidebar-backdrop></div>
  <aside class="app-sidebar" id="app-sidebar" aria-hidden="true">
    <div class="sidebar-header">
      <span class="sidebar-title">Menu</span>
      <button class="sidebar-close" type="button" aria-label="Cerrar menu">&times;</button>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">
        <button class="sidebar-section-toggle" type="button">
          <span>Administracion</span>
          <span class="chevron"></span>
        </button>
        <div class="sidebar-links">
          <?php if ($can('dashboard.view')): ?><a href="index.php">Dashboard</a><?php endif; ?>
          <?php if ($can('calendar.view')): ?><a href="index.php?view=calendar">Calendario</a><?php endif; ?>
          <?php if ($can('properties.view')): ?><a href="index.php?view=properties">Propiedades</a><?php endif; ?>
          <?php if ($can('users.view')): ?><a href="index.php?view=users">Usuarios</a><?php endif; ?>
          <?php if ($can('activities.view')): ?><a href="index.php?view=activities">Actividades</a><?php endif; ?>
          <?php if ($can('rooms.view')): ?><a href="index.php?view=rooms">Habitaciones</a><?php endif; ?>
          <?php if ($can('categories.view')): ?><a href="index.php?view=categories">Categorias</a><?php endif; ?>
          <?php if ($can('rateplans.view')): ?><a href="index.php?view=rateplans">Tarifas</a><?php endif; ?>
          <?php if ($can('messages.view')): ?><a href="index.php?view=messages">Mensajes</a><?php endif; ?>
          <?php if ($can('guests.view')): ?><a href="index.php?view=guests">Huespedes</a><?php endif; ?>
          <?php if ($can('reservations.view')): ?><a href="index.php?view=reservations">Reservas</a><?php endif; ?>
          <?php if ($can('payments.view')): ?><a href="index.php?view=payments">Pagos</a><?php endif; ?>
          <?php if ($can('incomes.view')): ?><a href="index.php?view=incomes">Ingresos</a><?php endif; ?>
          <?php if ($can('otas.view')): ?><a href="index.php?view=otas">OTAs</a><?php endif; ?>
          <?php if ($can('ota_ical.view')): ?><a href="index.php?view=ota_ical">iCal OTAs</a><?php endif; ?>
          <?php if ($can('settings.view')): ?><a href="index.php?view=settings">Configuraciones</a><?php endif; ?>
        </div>
      </div>
      <div class="sidebar-section">
        <button class="sidebar-section-toggle" type="button">
          <span>Finanzas</span>
          <span class="chevron"></span>
        </button>
        <div class="sidebar-links">
          <?php if ($can('sale_items.view')): ?><a href="index.php?view=sale_items">Conceptos</a><?php endif; ?>
          <?php if ($can('payments.view')): ?><a href="index.php?view=payments">Pagos</a><?php endif; ?>
          <?php if ($can('incomes.view')): ?><a href="index.php?view=incomes">Ingresos</a><?php endif; ?>
          <?php if ($can('obligations.view')): ?><a href="index.php?view=obligations">Obligaciones</a><?php endif; ?>
          <?php if ($can('reports.view')): ?><a href="index.php?view=reports">Reportes</a><?php endif; ?>
        </div>
      </div>
    </nav>
  </aside>
  <?php
    $view = isset($_GET['view']) ? (string)$_GET['view'] : '';
    $mainClass = 'app-main';
    if ($view === 'calendar') {
        $mainClass .= ' app-main-wide';
    }
  ?>
  <main class="<?php echo htmlspecialchars($mainClass, ENT_QUOTES, 'UTF-8'); ?>">
<?php
}

function pms_render_footer()
{
    ?>
  </main>
  <footer class="app-footer">
    <small>Vive la Vibe PMS - <?php echo date('Y'); ?></small>
  </footer>
</body>
</html>
<?php
}

function pms_render_table($rows, $headers = array())
{
    if (!$rows) {
        echo '<p class="muted">Sin datos.</p>';
        return;
    }

    $firstRow = $rows[0];
    $columns = $headers ? $headers : array_keys($firstRow);

    echo '<div class="table-scroll"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . htmlspecialchars((string)$column, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            $value = isset($row[$column]) ? $row[$column] : '';
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            echo '<td>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function pms_value($source, $key, $default = '')
{
    return isset($source[$key]) ? (string)$source[$key] : $default;
}

function pms_subtabs_init($moduleKey, $defaultActive = 'static:general')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['pms_subtabs'])) {
        $_SESSION['pms_subtabs'] = array();
    }

    if (!isset($_SESSION['pms_subtabs'][$moduleKey])) {
        $_SESSION['pms_subtabs'][$moduleKey] = array(
            'open' => array(),
            'active' => $defaultActive,
            'dirty' => array()
        );
    }

    $state = $_SESSION['pms_subtabs'][$moduleKey];

    $actionKey = $moduleKey . '_subtab_action';
    $currentKey = $moduleKey . '_current_subtab';
    $dirtyKey = $moduleKey . '_dirty_tabs';
    if (isset($_POST[$actionKey]) && $_POST[$actionKey] !== '') {
        $action = (string)$_POST[$actionKey];
        $targetKey = $moduleKey . '_subtab_target';
        $target = isset($_POST[$targetKey]) ? (string)$_POST[$targetKey] : '';

        switch ($action) {
            case 'open':
                if ($target !== '' && !in_array($target, $state['open'], true)) {
                    $state['open'][] = $target;
                }
                if ($target !== '') {
                    $state['active'] = 'dynamic:' . $target;
                }
                break;
            case 'close':
                if ($target !== '') {
                    $state['open'] = array_values(array_filter(
                        $state['open'],
                        function ($item) use ($target) {
                            return $item !== $target;
                        }
                    ));
                    if ($state['active'] === 'dynamic:' . $target) {
                        $state['active'] = $defaultActive;
                    }
                }
                break;
            case 'activate':
                if (strpos($target, 'static:') === 0 || strpos($target, 'dynamic:') === 0) {
                    $state['active'] = $target;
                } elseif ($target !== '') {
                    $state['active'] = 'dynamic:' . $target;
                }
                break;
            case 'clear':
                $state = array(
                    'open' => array(),
                    'active' => $defaultActive,
                    'dirty' => array()
                );
                break;
        }
    }

    if (isset($_POST[$currentKey]) && $_POST[$currentKey] !== '') {
        $state['active'] = (string)$_POST[$currentKey];
    }

    if (isset($_POST[$dirtyKey])) {
        $dirtyRaw = (string)$_POST[$dirtyKey];
        if ($dirtyRaw === '') {
            $state['dirty'] = array();
        } else {
            $dirtyItems = array_filter(array_map('trim', explode(',', $dirtyRaw)), function ($value) {
                return $value !== '';
            });
            $state['dirty'] = array_values(array_unique($dirtyItems));
        }
    }

    $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
    $isDirectModuleNavigation = $requestMethod !== 'POST'
        && !isset($_POST[$actionKey])
        && !isset($_POST[$currentKey])
        && !isset($_POST[$dirtyKey]);
    if ($isDirectModuleNavigation) {
        $state['active'] = $defaultActive;
    }

    if (!isset($state['active']) || $state['active'] === '') {
        $state['active'] = $defaultActive;
    }

    if (strpos($state['active'], 'dynamic:') === 0) {
        $activeKey = substr($state['active'], strlen('dynamic:'));
        if (!in_array($activeKey, $state['open'], true)) {
            $state['active'] = $defaultActive;
        }
    }

    $_SESSION['pms_subtabs'][$moduleKey] = $state;

    return $state;
}

function pms_subtabs_clear_dirty($moduleKey, $targetKey)
{
    if (!isset($_SESSION['pms_subtabs'][$moduleKey]['dirty'])) {
        return;
    }
    $dirty = $_SESSION['pms_subtabs'][$moduleKey]['dirty'];
    $dirty = array_values(array_filter($dirty, function ($item) use ($targetKey) {
        return $item !== $targetKey;
    }));
    $_SESSION['pms_subtabs'][$moduleKey]['dirty'] = $dirty;
}

function pms_subtabs_close_active($moduleKey, $defaultActive = 'static:general')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['pms_subtabs'][$moduleKey])) {
        return array(
            'open' => array(),
            'active' => $defaultActive,
            'dirty' => array()
        );
    }
    $state = $_SESSION['pms_subtabs'][$moduleKey];
    $active = isset($state['active']) ? (string)$state['active'] : $defaultActive;
    if (strpos($active, 'dynamic:') === 0) {
        $target = substr($active, strlen('dynamic:'));
        $state['open'] = array_values(array_filter(
            isset($state['open']) ? $state['open'] : array(),
            function ($item) use ($target) {
                return $item !== $target;
            }
        ));
    }
    $state['active'] = $defaultActive;
    $_SESSION['pms_subtabs'][$moduleKey] = $state;
    return $state;
}

function pms_subtabs_form_state_fields($moduleKey, array $state, $suppressCurrent = false)
{
    $active = isset($state['active']) ? $state['active'] : 'static:general';
    $dirty = isset($state['dirty']) ? implode(',', $state['dirty']) : '';

    if (!$suppressCurrent) {
        echo '<input type="hidden" name="'
            . htmlspecialchars($moduleKey . '_current_subtab', ENT_QUOTES, 'UTF-8')
            . '" value="'
            . htmlspecialchars($active, ENT_QUOTES, 'UTF-8')
            . '" class="js-subtab-current">';
    }

    echo '<input type="hidden" name="'
        . htmlspecialchars($moduleKey . '_dirty_tabs', ENT_QUOTES, 'UTF-8')
        . '" value="'
        . htmlspecialchars($dirty, ENT_QUOTES, 'UTF-8')
        . '" class="js-subtab-dirty">';
}

function pms_render_subtabs($moduleKey, array $state, array $staticTabs, array $dynamicTabs)
{
    $active = isset($state['active']) ? $state['active'] : 'static:general';
    $dirtySet = array();
    if (isset($state['dirty'])) {
        foreach ($state['dirty'] as $dirtyKey) {
            $dirtySet[$dirtyKey] = true;
        }
    }

    echo '<div class="subtabs" data-module="'
        . htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8')
        . '" data-active="'
        . htmlspecialchars($active, ENT_QUOTES, 'UTF-8')
        . '">';

    echo '<div class="subtabs-nav">';
    foreach ($staticTabs as $tab) {
        $tabId = isset($tab['id']) ? (string)$tab['id'] : '';
        $tabLabel = isset($tab['title']) ? (string)$tab['title'] : $tabId;
        $tabKey = 'static:' . $tabId;
        $classes = array('subtab-trigger');
        if ($tabKey === $active) {
            $classes[] = 'is-active';
        }
        if (isset($dirtySet[$tabKey])) {
            $classes[] = 'is-dirty';
        }
        echo '<button type="button" class="'
            . implode(' ', $classes)
            . '" data-target="'
            . htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8')
            . '" data-static="1">'
            . htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8')
            . '</button>';
    }

    foreach ($dynamicTabs as $tab) {
        $tabKeyRaw = isset($tab['key']) ? (string)$tab['key'] : '';
        $tabTitle = isset($tab['title']) ? (string)$tab['title'] : $tabKeyRaw;
        $tabKey = 'dynamic:' . $tabKeyRaw;
        $classes = array('subtab-trigger', 'is-dynamic');
        if ($tabKey === $active) {
            $classes[] = 'is-active';
        }
        if (isset($dirtySet[$tabKey])) {
            $classes[] = 'is-dirty';
        }
        $panelId = isset($tab['panel_id']) ? (string)$tab['panel_id'] : ('panel-' . preg_replace('/[^a-zA-Z0-9_\-:]/', '-', $tabKeyRaw));
        echo '<span class="subtab-dynamic-item">';
        echo '<button type="button" class="'
            . implode(' ', $classes)
            . '" data-target="'
            . htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8')
            . '" data-panel-id="'
            . htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8')
            . '">'
            . htmlspecialchars($tabTitle, ENT_QUOTES, 'UTF-8')
            . '</button>';
        if (!isset($tab['no_close']) || !$tab['no_close']) {
            $closeRef = isset($tab['close_form_id']) ? (string)$tab['close_form_id'] : '';
            echo '<button type="button" class="subtab-close" data-close-form="'
                . htmlspecialchars($closeRef, ENT_QUOTES, 'UTF-8')
                . '" aria-label="Cerrar">'
                . '&times;'
                . '</button>';
        }
        echo '</span>';
    }
    echo '</div>';

    echo '<div class="subtabs-panels">';
    foreach ($staticTabs as $tab) {
        $tabId = isset($tab['id']) ? (string)$tab['id'] : '';
        $tabKey = 'static:' . $tabId;
        $isActive = $tabKey === $active;
        $panelClasses = array('subtab-panel');
        if ($isActive) {
            $panelClasses[] = 'is-active';
        }
        echo '<section class="'
            . implode(' ', $panelClasses)
            . '" data-tab-key="'
            . htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8')
            . '">';
        if (isset($tab['content'])) {
            echo $tab['content'];
        }
        echo '</section>';
    }

    foreach ($dynamicTabs as $tab) {
        $tabKeyRaw = isset($tab['key']) ? (string)$tab['key'] : '';
        $tabKey = 'dynamic:' . $tabKeyRaw;
        $panelId = isset($tab['panel_id']) ? (string)$tab['panel_id'] : ('panel-' . preg_replace('/[^a-zA-Z0-9_\-:]/', '-', $tabKeyRaw));
        $isActive = $tabKey === $active;
        $panelClasses = array('subtab-panel', 'is-dynamic');
        if ($isActive) {
            $panelClasses[] = 'is-active';
        }
        echo '<section class="'
            . implode(' ', $panelClasses)
            . '" data-tab-key="'
            . htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8')
            . '" id="'
            . htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8')
            . '">';
        if (isset($tab['content'])) {
            echo $tab['content'];
        }
        echo '</section>';
    }
    echo '</div>';

    echo '</div>';
}
