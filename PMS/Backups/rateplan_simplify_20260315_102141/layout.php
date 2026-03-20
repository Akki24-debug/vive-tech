<?php
function pms_render_header($title = 'PMS Console')
{
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $basePath = rtrim(dirname($scriptName), '/\\');
    $basePrefix = ($basePath === '' || $basePath === '.') ? '' : $basePath;
    $assetPath = $basePrefix . '/assets/style.css';
    $scriptPath = $basePrefix . '/assets/app.js';
    $dateRangeCssPath = $basePrefix . '/assets/pms_date_range_picker.css';
    $dateRangeScriptPath = $basePrefix . '/assets/pms_date_range_picker.js';
    $assetFile = dirname(__DIR__) . '/assets/style.css';
    $scriptFile = dirname(__DIR__) . '/assets/app.js';
    $dateRangeCssFile = dirname(__DIR__) . '/assets/pms_date_range_picker.css';
    $dateRangeScriptFile = dirname(__DIR__) . '/assets/pms_date_range_picker.js';
    $assetVersion = is_file($assetFile) ? (string)filemtime($assetFile) : (string)time();
    $scriptVersion = is_file($scriptFile) ? (string)filemtime($scriptFile) : (string)time();
    $dateRangeCssVersion = is_file($dateRangeCssFile) ? (string)filemtime($dateRangeCssFile) : '';
    $dateRangeScriptVersion = is_file($dateRangeScriptFile) ? (string)filemtime($dateRangeScriptFile) : '';
    $assetHref = $assetPath . '?v=' . rawurlencode($assetVersion);
    $scriptSrc = $scriptPath . '?v=' . rawurlencode($scriptVersion);
    $dateRangeCssHref = $dateRangeCssVersion !== '' ? ($dateRangeCssPath . '?v=' . rawurlencode($dateRangeCssVersion)) : '';
    $dateRangeScriptSrc = $dateRangeScriptVersion !== '' ? ($dateRangeScriptPath . '?v=' . rawurlencode($dateRangeScriptVersion)) : '';
    $indexPath = $basePrefix . '/index.php';
    $logoutPath = $basePrefix . '/logout.php';
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
    $currentView = isset($_GET['view']) ? (string)$_GET['view'] : 'dashboard';
    $selectedReportTemplateId = isset($_GET['selected_report_template_id']) ? (int)$_GET['selected_report_template_id'] : 0;
    $reportSidebarTemplatesByCategory = array();
    $reportsTab = isset($_GET['reports_tab']) ? (string)$_GET['reports_tab'] : 'run';
    $reportsManageHref = $indexPath . '?view=reports&reports_tab=templates&reports_manage=1';
    if ($can('reports.view') && $currentUser && isset($currentUser['company_id'])) {
        $reportLibFile = __DIR__ . '/report_v2_lib.php';
        if (is_file($reportLibFile)) {
            require_once $reportLibFile;
            if (function_exists('reports_v2_tables_ready') && function_exists('reports_v2_fetch_templates')) {
                try {
                    $reportPdo = pms_get_connection();
                    if (empty(reports_v2_tables_ready($reportPdo))) {
                        $reportSidebarTemplates = reports_v2_fetch_templates($reportPdo, (int)$currentUser['company_id']);
                        foreach ($reportSidebarTemplates as $reportTemplateRow) {
                            $categoryLabel = isset($reportTemplateRow['category_name']) ? trim((string)$reportTemplateRow['category_name']) : '';
                            if ($categoryLabel === '') {
                                $categoryLabel = 'Sin categoria';
                            }
                            if (!isset($reportSidebarTemplatesByCategory[$categoryLabel])) {
                                $reportSidebarTemplatesByCategory[$categoryLabel] = array();
                            }
                            $reportSidebarTemplatesByCategory[$categoryLabel][] = $reportTemplateRow;
                        }
                    }
                } catch (Throwable $e) {
                    $reportSidebarTemplatesByCategory = array();
                }
            }
        }
    }
    $themeClass = 'theme-default';
    if ($currentUser && isset($currentUser['company_code'])) {
        try {
            $themeCode = pms_fetch_user_theme(
                pms_get_connection(),
                (string)$currentUser['company_code'],
                isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : 0
            );
            $themeCode = pms_theme_normalize_code($themeCode);
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['pms_user']) && is_array($_SESSION['pms_user'])) {
                $_SESSION['pms_user']['theme_code'] = $themeCode;
            }
            $themeClass = 'theme-' . preg_replace('/[^a-z0-9_-]/i', '', $themeCode);
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
  <script>
    window.PMS_BASE_PREFIX = <?php echo json_encode($basePrefix, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    window.pmsBuildUrl = function (path) {
      var base = (window.PMS_BASE_PREFIX || '').replace(/\/+$/, '');
      var target = String(path || '');
      if (!target) return base || '';
      if (target.indexOf('http://') === 0 || target.indexOf('https://') === 0) return target;
      if (target.charAt(0) !== '/') target = '/' + target;
      return base + target;
    };
  </script>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($assetHref, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if ($dateRangeCssHref !== ''): ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($dateRangeCssHref, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <script src="<?php echo htmlspecialchars($scriptSrc, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
  <?php if ($dateRangeScriptSrc !== ''): ?>
  <script src="<?php echo htmlspecialchars($dateRangeScriptSrc, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
  <?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars($themeClass, ENT_QUOTES, 'UTF-8'); ?>">
  <header class="app-header">
    <div class="header-bar">
      <button class="nav-toggle" type="button" aria-label="Abrir menu" aria-controls="app-sidebar" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <a class="brand-link" href="<?php echo htmlspecialchars($indexPath . '?view=dashboard', ENT_QUOTES, 'UTF-8'); ?>" title="Dashboard">
        <span class="brand-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="M12 3.5L3.5 10.5V20a1 1 0 0 0 1 1h5.5v-6.5h4V21h5.5a1 1 0 0 0 1-1v-9.5L12 3.5z" />
          </svg>
        </span>
        <span class="brand-title"><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></span>
      </a>
      <div class="header-actions">
        <?php if ($can('calendar.view')): ?>
        <a class="icon-button calendar-button" href="<?php echo htmlspecialchars($indexPath . '?view=calendar', ENT_QUOTES, 'UTF-8'); ?>" title="Calendario">
          <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
            <path d="M7 3.5a1 1 0 0 1 1 1V6h8V4.5a1 1 0 1 1 2 0V6h1a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h1V4.5a1 1 0 0 1 2 0V6zm13 6.5H4V19h16v-9z" />
          </svg>
        </a>
        <?php endif; ?>
        <?php if ($currentUser): ?>
        <div class="user-meta">
          <span><?php echo htmlspecialchars($currentUser['display_name'] ? $currentUser['display_name'] : $currentUser['email'], ENT_QUOTES, 'UTF-8'); ?></span>
          <a href="<?php echo htmlspecialchars($logoutPath, ENT_QUOTES, 'UTF-8'); ?>">Cerrar sesion</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <div class="sidebar-backdrop" data-sidebar-backdrop></div>
  <aside class="app-sidebar" id="app-sidebar" aria-hidden="true">
    <div class="sidebar-header">
      <span class="sidebar-title">Menu</span>
      <div class="sidebar-header-actions">
        <?php if ($can('settings.view')): ?>
        <a class="sidebar-settings-link" href="<?php echo htmlspecialchars($indexPath . '?view=settings', ENT_QUOTES, 'UTF-8'); ?>" title="Configuraciones" aria-label="Configuraciones">
          <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
            <path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zm8.1 4.5-.03-.9 1.78-1.38-1.74-3.01-2.2.69a7.98 7.98 0 0 0-1.56-.9l-.42-2.25H10.1l-.42 2.25c-.55.2-1.08.5-1.56.9l-2.2-.69-1.74 3.01L6 12l-.03.9-1.82 1.29 1.74 3.01 2.24-.66c.46.38.98.68 1.53.9l.42 2.26h3.48l.42-2.26c.55-.22 1.07-.52 1.53-.9l2.24.66 1.74-3.01-1.82-1.29z"></path>
          </svg>
        </a>
        <?php endif; ?>
        <button class="sidebar-close" type="button" aria-label="Cerrar menu">&times;</button>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section" data-section-key="operations" data-default-open="1">
        <button class="sidebar-section-toggle" type="button">
          <span>Operaciones</span>
          <span class="chevron"></span>
        </button>
        <div class="sidebar-links">
          <?php if ($can('dashboard.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=dashboard', ENT_QUOTES, 'UTF-8'); ?>">Dashboard</a><?php endif; ?>
          <?php if ($can('calendar.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=calendar', ENT_QUOTES, 'UTF-8'); ?>">Calendario</a><?php endif; ?>
          <?php if ($can('reservations.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=reservations', ENT_QUOTES, 'UTF-8'); ?>">Reservas</a><?php endif; ?>
          <?php if ($can('guests.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=guests', ENT_QUOTES, 'UTF-8'); ?>">Huespedes</a><?php endif; ?>
          <?php if ($can('messages.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=messages', ENT_QUOTES, 'UTF-8'); ?>">Mensajes</a><?php endif; ?>
          <?php if ($can('activities.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=activities', ENT_QUOTES, 'UTF-8'); ?>">Actividades</a><?php endif; ?>
        </div>
      </div>
      <div class="sidebar-section" data-section-key="administration">
        <button class="sidebar-section-toggle" type="button">
          <span>Administracion</span>
          <span class="chevron"></span>
        </button>
        <div class="sidebar-links">
          <?php if ($can('users.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=users', ENT_QUOTES, 'UTF-8'); ?>">Usuarios</a><?php endif; ?>
          <?php if ($can('users.manage_roles')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=user_roles', ENT_QUOTES, 'UTF-8'); ?>">Roles y permisos</a><?php endif; ?>
        </div>
      </div>
      <div class="sidebar-section" data-section-key="property">
        <button class="sidebar-section-toggle" type="button">
          <span>Propiedad</span>
          <span class="chevron"></span>
        </button>
        <div class="sidebar-links">
          <?php if ($can('properties.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=properties', ENT_QUOTES, 'UTF-8'); ?>">Propiedades</a><?php endif; ?>
          <?php if ($can('categories.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=categories', ENT_QUOTES, 'UTF-8'); ?>">Categorias</a><?php endif; ?>
          <?php if ($can('rooms.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=rooms', ENT_QUOTES, 'UTF-8'); ?>">Habitaciones</a><?php endif; ?>
          <?php if ($can('rateplans.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=rateplans', ENT_QUOTES, 'UTF-8'); ?>">Tarifas</a><?php endif; ?>
          <?php if ($can('otas.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=otas', ENT_QUOTES, 'UTF-8'); ?>">OTAs</a><?php endif; ?>
          <?php if ($can('ota_ical.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=ota_ical', ENT_QUOTES, 'UTF-8'); ?>">iCal OTAs</a><?php endif; ?>
        </div>
      </div>
      <div class="sidebar-section" data-section-key="finance">
        <button class="sidebar-section-toggle" type="button">
          <span>Finanzas</span>
          <span class="chevron"></span>
        </button>
        <div class="sidebar-links">
          <?php if ($can('sale_items.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=sale_items', ENT_QUOTES, 'UTF-8'); ?>">Conceptos</a><?php endif; ?>
          <?php if ($can('payments.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=payments', ENT_QUOTES, 'UTF-8'); ?>">Pagos</a><?php endif; ?>
          <?php if ($can('incomes.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=incomes', ENT_QUOTES, 'UTF-8'); ?>">Ingresos</a><?php endif; ?>
          <?php if ($can('obligations.view')): ?><a href="<?php echo htmlspecialchars($indexPath . '?view=obligations', ENT_QUOTES, 'UTF-8'); ?>">Obligaciones</a><?php endif; ?>
        </div>
      </div>
      <?php if ($can('reports.view')): ?>
      <div class="sidebar-section" data-section-key="reports" data-default-open="<?php echo $currentView === 'reports' ? '1' : '0'; ?>">
        <button class="sidebar-section-toggle" type="button">
          <span>Reportes</span>
          <span class="chevron"></span>
        </button>
        <div class="sidebar-links sidebar-links--reports">
          <?php if (empty($reportSidebarTemplatesByCategory)): ?>
            <a class="<?php echo $currentView === 'reports' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars($indexPath . '?view=reports&reports_tab=run', ENT_QUOTES, 'UTF-8'); ?>">Ejecucion</a>
          <?php else: ?>
            <?php foreach ($reportSidebarTemplatesByCategory as $reportCategoryLabel => $categoryTemplates): ?>
              <?php
                $categoryTemplateIds = array();
                foreach ($categoryTemplates as $categoryTemplateRow) {
                    $categoryTemplateIds[] = isset($categoryTemplateRow['id_report_template']) ? (int)$categoryTemplateRow['id_report_template'] : 0;
                }
                $categoryIsOpen = $currentView === 'reports' && ($selectedReportTemplateId <= 0 || in_array($selectedReportTemplateId, $categoryTemplateIds, true));
              ?>
              <details class="sidebar-subgroup" <?php echo $categoryIsOpen ? 'open' : ''; ?>>
                <summary class="sidebar-subgroup-toggle">
                  <span><?php echo htmlspecialchars($reportCategoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </summary>
                <div class="sidebar-subgroup-links">
                  <?php foreach ($categoryTemplates as $categoryTemplateRow): ?>
                    <?php
                      $categoryTemplateId = isset($categoryTemplateRow['id_report_template']) ? (int)$categoryTemplateRow['id_report_template'] : 0;
                      $reportHref = $indexPath . '?view=reports&reports_tab=run&selected_report_template_id=' . $categoryTemplateId;
                      $reportLinkClasses = array();
                      if ($currentView === 'reports' && $selectedReportTemplateId === $categoryTemplateId) {
                          $reportLinkClasses[] = 'is-active';
                      }
                      if (isset($categoryTemplateRow['is_active']) && empty($categoryTemplateRow['is_active'])) {
                          $reportLinkClasses[] = 'is-muted';
                      }
                    ?>
                    <a class="<?php echo htmlspecialchars(implode(' ', $reportLinkClasses), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($reportHref, ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars((string)$categoryTemplateRow['report_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($can('reports.design')): ?>
            <a class="sidebar-report-manage <?php echo $currentView === 'reports' && ((isset($_GET['reports_manage']) && (int)$_GET['reports_manage'] === 1) || in_array($reportsTab, array('templates', 'calculations'), true)) ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars($reportsManageHref, ENT_QUOTES, 'UTF-8'); ?>">Editar plantillas</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
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
