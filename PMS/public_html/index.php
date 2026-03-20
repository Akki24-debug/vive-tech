<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/layout.php';

$user = pms_current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}
$accessContext = pms_access_context(false);

$views = array(
    'dashboard'    => 'modules/dashboard.php',
    'dashboard_mobile' => 'modules/dashboard_mobile.php',
    'properties'   => 'modules/properties.php',
    'users'        => 'modules/app_users.php',
    'user_roles'   => 'modules/user_roles.php',
    'calendar'     => 'modules/calendar.php',
    'activities'   => 'modules/activities.php',
    'rooms'        => 'modules/rooms.php',
    'categories'   => 'modules/categories.php',
    'rateplans'    => 'modules/rateplans.php',
    'messages'     => 'modules/messages.php',
    'otas'         => 'modules/otas.php',
    'ota_ical'     => 'modules/ota_ical.php',
    'sale_items'   => 'modules/sale_items.php',
    'payments'     => 'modules/payments.php',
    'incomes'      => 'modules/incomes.php',
    'obligations'  => 'modules/obligations.php',
    'sale_item_report' => 'modules/sale_item_report.php',
    'reports'      => 'modules/reports.php',
    'settings'     => 'modules/settings.php',
    'guests'       => 'modules/guests.php',
    'reservations' => 'modules/reservations.php',
    'reservation_wizard' => 'modules/reservation_wizard.php',
);

if (!function_exists('pms_request_is_mobile')) {
    function pms_request_is_mobile()
    {
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower((string)$_SERVER['HTTP_USER_AGENT']) : '';
        if ($agent === '') {
            return false;
        }
        return (bool)preg_match('/android|webos|iphone|ipod|blackberry|iemobile|opera mini|mobile/i', $agent);
    }
}

$viewKey = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
if (!isset($views[$viewKey])) {
    $viewKey = 'dashboard';
}
if ($viewKey === 'dashboard') {
    $dashboardMode = isset($_GET['dashboard_mode']) ? strtolower(trim((string)$_GET['dashboard_mode'])) : '';
    if ($dashboardMode !== 'desktop' && pms_request_is_mobile()) {
        $viewKey = 'dashboard_mobile';
    }
}

$viewPermission = pms_module_view_permission($viewKey);
if ($viewPermission !== '' && !pms_user_can($viewPermission)) {
    http_response_code(403);
    pms_render_header('Acceso denegado');
    echo '<section class="card"><h2>Acceso denegado</h2><p class="error">No tienes permiso para abrir este modulo.</p></section>';
    pms_render_footer();
    exit;
}

if (!pms_is_owner_user()) {
    $allowedPropertyCodes = isset($accessContext['allowed_property_codes']) && is_array($accessContext['allowed_property_codes'])
        ? $accessContext['allowed_property_codes']
        : array();
    if (!$allowedPropertyCodes) {
        http_response_code(403);
        pms_render_header('Acceso denegado');
        echo '<section class="card"><h2>Acceso denegado</h2><p class="error">Tu usuario no tiene propiedades asignadas.</p></section>';
        pms_render_footer();
        exit;
    }
}

$headerTitleMap = array(
    'dashboard_mobile' => 'Dashboard',
    'obligations' => 'Obligaciones',
    'incomes' => 'Ingresos',
    'payments' => 'Pagos',
    'user_roles' => 'Roles y Permisos',
    'otas' => 'OTAs',
    'ota_ical' => 'iCal OTAs',
    'sale_item_report' => 'Cargos',
    'reports' => 'Reportes'
);
$headerTitle = isset($headerTitleMap[$viewKey]) ? $headerTitleMap[$viewKey] : ucfirst($viewKey);
pms_render_header($headerTitle);

$viewFile = __DIR__ . '/' . $views[$viewKey];
if (is_file($viewFile)) {
    try {
        require $viewFile;
    } catch (Throwable $e) {
        echo '<section class="card"><h2>Error</h2><p class="error">No fue posible mostrar la vista seleccionada.</p>';
        echo '<pre style="white-space:pre-wrap;font-size:0.8rem;background:rgba(255,255,255,0.08);padding:10px;border-radius:8px;">'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            . "\n"
            . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
            . '</pre></section>';
    }
} else {
    echo '<p class="error">Vista no disponible.</p>';
}

pms_render_footer();
