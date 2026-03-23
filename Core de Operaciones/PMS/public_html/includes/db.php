<?php
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetimeSeconds = 7 * 24 * 60 * 60; // 7 days
    $isHttpsRequest = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    );

    ini_set('session.gc_maxlifetime', (string)$sessionLifetimeSeconds);
    ini_set('session.cookie_lifetime', (string)$sessionLifetimeSeconds);

    $cookieParams = session_get_cookie_params();
    $cookiePath = isset($cookieParams['path']) && (string)$cookieParams['path'] !== '' ? (string)$cookieParams['path'] : '/';
    $cookieDomain = isset($cookieParams['domain']) ? (string)$cookieParams['domain'] : '';

    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => $sessionLifetimeSeconds,
            'path' => $cookiePath,
            'domain' => $cookieDomain,
            'secure' => $isHttpsRequest,
            'httponly' => true,
            'samesite' => 'Lax'
        ));
    } else {
        session_set_cookie_params(
            $sessionLifetimeSeconds,
            $cookiePath . '; samesite=Lax',
            $cookieDomain,
            $isHttpsRequest,
            true
        );
    }

    session_start();
}

if (isset($_SESSION['pms_runtime_timezone'])) {
    $bootstrapTimezone = trim((string)$_SESSION['pms_runtime_timezone']);
    if ($bootstrapTimezone !== '' && in_array($bootstrapTimezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($bootstrapTimezone);
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($knownString, $userString)
    {
        $knownString = (string)$knownString;
        $userString = (string)$userString;
        if (strlen($knownString) !== strlen($userString)) {
            return false;
        }
        $res = $knownString ^ $userString;
        $ret = 0;
        for ($i = strlen($res) - 1; $i >= 0; $i--) {
            $ret |= ord($res[$i]);
        }
        return $ret === 0;
    }
}

if (!function_exists('password_get_info')) {
    function password_get_info($hash)
    {
        return array('algo' => 0, 'algoName' => null, 'options' => array());
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash)
    {
        return hash_equals($hash, $password);
    }
}

function pms_resolve_connection_path()
{
    $baseDir = __DIR__;
    $candidates = array(
        dirname($baseDir) . DIRECTORY_SEPARATOR . 'pms db connections' . DIRECTORY_SEPARATOR . 'connection.php',
        dirname($baseDir, 2) . DIRECTORY_SEPARATOR . 'pms db connections' . DIRECTORY_SEPARATOR . 'connection.php',
        dirname($baseDir) . DIRECTORY_SEPARATOR . 'pms_db_connections' . DIRECTORY_SEPARATOR . 'connection.php',
        dirname($baseDir, 2) . DIRECTORY_SEPARATOR . 'pms_db_connections' . DIRECTORY_SEPARATOR . 'connection.php'
    );

    foreach ($candidates as $candidate) {
        if ($candidate && file_exists($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Database connection bootstrap not found. Checked: ' . implode(', ', $candidates));
}

require_once pms_resolve_connection_path();

function pms_timezone_is_valid($timezone)
{
    $tz = trim((string)$timezone);
    if ($tz === '') {
        return false;
    }
    static $tzMap = null;
    if (!is_array($tzMap)) {
        $tzMap = array();
        foreach (timezone_identifiers_list() as $item) {
            $tzMap[$item] = true;
        }
    }
    return isset($tzMap[$tz]);
}

function pms_timezone_offset_string($timezone)
{
    try {
        $tz = new DateTimeZone((string)$timezone);
        $now = new DateTime('now', $tz);
        $offset = (int)$tz->getOffset($now);
    } catch (Exception $e) {
        $offset = 0;
    }
    $sign = $offset < 0 ? '-' : '+';
    $abs = abs($offset);
    $hours = (int)floor($abs / 3600);
    $minutes = (int)floor(($abs % 3600) / 60);
    return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
}

function pms_settings_has_timezone_column(PDO $pdo)
{
    return true;
}

function pms_fetch_company_timezone(PDO $pdo, $companyId = null, $companyCode = null)
{
    $idCompany = (int)$companyId;
    $code = trim((string)$companyCode);
    $defaultTimezone = 'America/Mexico_City';

    try {
        if ($idCompany > 0) {
            $stmtCompany = $pdo->prepare(
                'SELECT id_company, COALESCE(NULLIF(TRIM(default_timezone), \'\'), \'America/Mexico_City\') AS default_timezone
                 FROM company
                 WHERE id_company = ?
                   AND deleted_at IS NULL
                 LIMIT 1'
            );
            $stmtCompany->execute(array($idCompany));
        } elseif ($code !== '') {
            $stmtCompany = $pdo->prepare(
                'SELECT id_company, COALESCE(NULLIF(TRIM(default_timezone), \'\'), \'America/Mexico_City\') AS default_timezone
                 FROM company
                 WHERE code = ?
                   AND deleted_at IS NULL
                 LIMIT 1'
            );
            $stmtCompany->execute(array($code));
        } else {
            $stmtCompany = null;
        }

        if ($stmtCompany) {
            $company = $stmtCompany->fetch(PDO::FETCH_ASSOC);
            if ($company && isset($company['id_company'])) {
                $idCompany = (int)$company['id_company'];
                $defaultTimezone = isset($company['default_timezone'])
                    ? trim((string)$company['default_timezone'])
                    : $defaultTimezone;
            }
        }
    } catch (Exception $e) {
    }

    if (!pms_timezone_is_valid($defaultTimezone)) {
        $defaultTimezone = 'America/Mexico_City';
    }

    if ($idCompany <= 0 || !pms_settings_has_timezone_column($pdo)) {
        return $defaultTimezone;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT timezone
             FROM pms_settings
             WHERE id_company = ?
               AND id_property IS NULL
               AND timezone IS NOT NULL
               AND TRIM(timezone) <> \'\'
             ORDER BY updated_at DESC, id_setting DESC
             LIMIT 1'
        );
        $stmt->execute(array($idCompany));
        $settingTimezone = $stmt->fetchColumn();
        $candidate = trim((string)$settingTimezone);
        if (pms_timezone_is_valid($candidate)) {
            return $candidate;
        }
    } catch (Exception $e) {
    }

    return $defaultTimezone;
}

function pms_apply_runtime_timezone(PDO $pdo, $timezone)
{
    $tz = trim((string)$timezone);
    if (!pms_timezone_is_valid($tz)) {
        $tz = 'America/Mexico_City';
    }

    date_default_timezone_set($tz);
    $_SESSION['pms_runtime_timezone'] = $tz;

    try {
        $stmtTz = $pdo->prepare('SET time_zone = ?');
        $stmtTz->execute(array($tz));
    } catch (Exception $e) {
        try {
            $stmtOffset = $pdo->prepare('SET time_zone = ?');
            $stmtOffset->execute(array(pms_timezone_offset_string($tz)));
        } catch (Exception $e2) {
        }
    }

    return $tz;
}

function pms_bootstrap_runtime_timezone(PDO $pdo)
{
    $targetTimezone = '';
    if (isset($_SESSION['pms_runtime_timezone'])) {
        $targetTimezone = trim((string)$_SESSION['pms_runtime_timezone']);
    }

    if (!pms_timezone_is_valid($targetTimezone)) {
        $sessionUser = isset($_SESSION['pms_user']) && is_array($_SESSION['pms_user'])
            ? $_SESSION['pms_user']
            : array();
        $companyId = isset($sessionUser['company_id']) ? (int)$sessionUser['company_id'] : 0;
        $companyCode = isset($sessionUser['company_code']) ? (string)$sessionUser['company_code'] : '';
        $targetTimezone = pms_fetch_company_timezone($pdo, $companyId, $companyCode);
    }

    return pms_apply_runtime_timezone($pdo, $targetTimezone);
}

function pms_current_timezone()
{
    $tz = isset($_SESSION['pms_runtime_timezone']) ? trim((string)$_SESSION['pms_runtime_timezone']) : '';
    if (!pms_timezone_is_valid($tz)) {
        $tz = date_default_timezone_get();
    }
    if (!pms_timezone_is_valid($tz)) {
        $tz = 'America/Mexico_City';
    }
    return $tz;
}

function pms_theme_normalize_code($themeCode)
{
    $normalized = strtolower(trim((string)$themeCode));
    if ($normalized === '') {
        $normalized = 'default';
    }
    if (!in_array($normalized, array('default', 'ocean'), true)) {
        $normalized = 'default';
    }
    return $normalized;
}

function pms_fetch_company_theme(PDO $pdo, $companyCode = '', $companyId = 0)
{
    $idCompany = (int)$companyId;
    $code = trim((string)$companyCode);

    if (!pms_table_exists($pdo, 'pms_company_theme')) {
        return 'default';
    }

    try {
        if ($idCompany > 0) {
            $stmt = $pdo->prepare(
                'SELECT pct.theme_code
                 FROM pms_company_theme pct
                 WHERE pct.id_company = ?
                 LIMIT 1'
            );
            $stmt->execute(array($idCompany));
        } elseif ($code !== '') {
            $stmt = $pdo->prepare(
                'SELECT pct.theme_code
                 FROM company c
                 LEFT JOIN pms_company_theme pct
                   ON pct.id_company = c.id_company
                 WHERE c.code = ?
                   AND c.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($code));
        } else {
            $stmt = null;
        }

        if ($stmt) {
            $themeCode = $stmt->fetchColumn();
            return pms_theme_normalize_code($themeCode);
        }
    } catch (Exception $e) {
    }

    return 'default';
}

function pms_fetch_user_theme(PDO $pdo, $companyCode = '', $userId = null)
{
    $fallbackTheme = pms_fetch_company_theme($pdo, $companyCode);
    $idUser = (int)$userId;
    if ($idUser <= 0 || !pms_table_exists($pdo, 'pms_user_theme')) {
        return $fallbackTheme;
    }

    try {
        if (trim((string)$companyCode) !== '') {
            $stmt = $pdo->prepare(
                'SELECT put.theme_code
                 FROM pms_user_theme put
                 JOIN app_user au
                   ON au.id_user = put.id_user
                 JOIN company c
                   ON c.id_company = au.id_company
                 WHERE put.id_user = ?
                   AND c.code = ?
                   AND au.deleted_at IS NULL
                   AND c.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute(array($idUser, $companyCode));
        } else {
            $stmt = $pdo->prepare(
                'SELECT theme_code
                 FROM pms_user_theme
                 WHERE id_user = ?
                 LIMIT 1'
            );
            $stmt->execute(array($idUser));
        }

        $themeCode = $stmt->fetchColumn();
        if ($themeCode !== false && $themeCode !== null) {
            return pms_theme_normalize_code($themeCode);
        }
    } catch (Exception $e) {
    }

    return $fallbackTheme;
}

function pms_save_user_theme(PDO $pdo, $companyCode, $userId, $themeCode, $actorUserId = null)
{
    $normalizedTheme = pms_theme_normalize_code($themeCode);
    $idUser = (int)$userId;
    $idActor = $actorUserId !== null ? (int)$actorUserId : null;
    $normalizedCompanyCode = trim((string)$companyCode);

    if ($idUser > 0 && pms_table_exists($pdo, 'pms_user_theme')) {
        $stmtValidate = $pdo->prepare(
            'SELECT au.id_user
             FROM app_user au
             JOIN company c
               ON c.id_company = au.id_company
             WHERE au.id_user = ?
               AND c.code = ?
               AND au.deleted_at IS NULL
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $stmtValidate->execute(array($idUser, $normalizedCompanyCode));
        $validatedUserId = (int)$stmtValidate->fetchColumn();
        if ($validatedUserId <= 0) {
            throw new RuntimeException('No se pudo validar el usuario del tema.');
        }

        $stmtUpsert = $pdo->prepare(
            'INSERT INTO pms_user_theme (
                id_user,
                theme_code,
                updated_by,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                theme_code = VALUES(theme_code),
                updated_by = VALUES(updated_by),
                updated_at = NOW()'
        );
        $stmtUpsert->execute(array($validatedUserId, $normalizedTheme, $idActor > 0 ? $idActor : null));
    } else {
        pms_call_procedure('sp_pms_theme_upsert', array(
            $normalizedCompanyCode,
            $normalizedTheme,
            $idActor
        ));
    }

    if (isset($_SESSION['pms_user']) && is_array($_SESSION['pms_user'])) {
        $sessionUserId = isset($_SESSION['pms_user']['id_user']) ? (int)$_SESSION['pms_user']['id_user'] : 0;
        if ($sessionUserId === $idUser) {
            $_SESSION['pms_user']['theme_code'] = $normalizedTheme;
        }
    }

    return $normalizedTheme;
}

function pms_get_connection()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = createDatabaseConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    pms_bootstrap_runtime_timezone($pdo);

    return $pdo;
}

function pms_scope_allowed_property_set_for_result_filter()
{
    if (!isset($_SESSION['pms_user']) || !is_array($_SESSION['pms_user'])) {
        return null;
    }

    $isOwner = isset($_SESSION['pms_user']['is_owner']) && (int)$_SESSION['pms_user']['is_owner'] === 1;
    if (isset($_SESSION['pms_access']) && is_array($_SESSION['pms_access']) && isset($_SESSION['pms_access']['is_owner'])) {
        $isOwner = (int)$_SESSION['pms_access']['is_owner'] === 1;
    }
    if ($isOwner) {
        return array('__owner__' => true);
    }

    if (!isset($_SESSION['pms_access']) || !is_array($_SESSION['pms_access'])) {
        return null;
    }
    $codes = isset($_SESSION['pms_access']['allowed_property_codes']) && is_array($_SESSION['pms_access']['allowed_property_codes'])
        ? $_SESSION['pms_access']['allowed_property_codes']
        : null;
    if ($codes === null) {
        return null;
    }

    $set = array();
    foreach ($codes as $code) {
        $key = strtoupper(trim((string)$code));
        if ($key !== '') {
            $set[$key] = true;
        }
    }
    return $set;
}

function pms_scope_filter_result_rows(array $rows)
{
    if (!$rows) {
        return $rows;
    }

    $allowedSet = pms_scope_allowed_property_set_for_result_filter();
    if ($allowedSet === null || isset($allowedSet['__owner__'])) {
        return $rows;
    }

    $filtered = array();
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $filtered[] = $row;
            continue;
        }

        if (array_key_exists('property_code', $row)) {
            $propertyCode = strtoupper(trim((string)$row['property_code']));
            if ($propertyCode === '' || isset($allowedSet[$propertyCode])) {
                $filtered[] = $row;
            }
            continue;
        }

        if (array_key_exists('property_codes', $row)) {
            $codesRaw = trim((string)$row['property_codes']);
            if ($codesRaw === '') {
                $filtered[] = $row;
                continue;
            }
            $hasAllowed = false;
            foreach (explode(',', $codesRaw) as $candidate) {
                $candidateCode = strtoupper(trim((string)$candidate));
                if ($candidateCode !== '' && isset($allowedSet[$candidateCode])) {
                    $hasAllowed = true;
                    break;
                }
            }
            if ($hasAllowed) {
                $filtered[] = $row;
            }
            continue;
        }

        $filtered[] = $row;
    }

    return $filtered;
}

function pms_call_procedure($procedure, $params = array())
{
    $pdo = pms_get_connection();
    $placeholders = $params ? implode(',', array_fill(0, count($params), '?')) : '';
    $stmt = $pdo->prepare(sprintf('CALL %s(%s)', $procedure, $placeholders));
    $stmt->execute(array_values($params));

    $results = array();
    do {
        $rows = $stmt->fetchAll();
        if ($rows !== false) {
            $rows = pms_scope_filter_result_rows($rows);
            $results[] = $rows;
        }
    } while ($stmt->nextRowset());

    $stmt->closeCursor();
    return $results;
}

function pms_call_single($procedure, $params = array())
{
    $sets = pms_call_procedure($procedure, $params);
    return isset($sets[0]) ? $sets[0] : array();
}

function pms_call_create_reservation_hold($propertyCode, $roomCode, $checkIn, $checkOut, $totalCentsOverride, $notes, $userId)
{
    $params = array(
        $propertyCode,
        $roomCode,
        $checkIn,
        $checkOut,
        $totalCentsOverride,
        $notes,
        $userId
    );

    try {
        return pms_call_procedure('sp_create_reservation_hold', $params);
    } catch (PDOException $exception) {
        $message = (string)$exception->getMessage();
        $isSignatureMismatch = strpos($message, 'Incorrect number of arguments for PROCEDURE') !== false
            && strpos($message, 'sp_create_reservation_hold') !== false;
        if (!$isSignatureMismatch) {
            throw $exception;
        }

        return pms_call_procedure('sp_create_reservation_hold', array(
            $propertyCode,
            $roomCode,
            $checkIn,
            $checkOut,
            $notes,
            $userId
        ));
    }
}

function pms_fetch_company_property_codes_raw($companyId)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT code
         FROM property
         WHERE id_company = ?
           AND deleted_at IS NULL
           AND is_active = 1
         ORDER BY order_index, name, code'
    );
    $stmt->execute(array((int)$companyId));
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $codes = array();
    foreach ((array)$rows as $value) {
        $code = strtoupper(trim((string)$value));
        if ($code !== '') {
            $codes[] = $code;
        }
    }
    return array_values(array_unique($codes));
}

function pms_access_context($forceRefresh = false)
{
    $user = pms_current_user();
    if (!$user) {
        return array(
            'permission_codes' => array(),
            'allowed_property_codes' => array(),
            'mode' => 'audit',
            'is_owner' => 0,
            'loaded_at' => time(),
            'legacy_fallback' => 0
        );
    }

    if (!$forceRefresh && isset($_SESSION['pms_access']) && is_array($_SESSION['pms_access'])) {
        return $_SESSION['pms_access'];
    }

    $companyCode = isset($user['company_code']) ? (string)$user['company_code'] : '';
    $companyId = isset($user['company_id']) ? (int)$user['company_id'] : 0;
    $userId = isset($user['id_user']) ? (int)$user['id_user'] : 0;
    $sessionIsOwner = isset($user['is_owner']) ? (int)$user['is_owner'] : 0;

    $context = array(
        'permission_codes' => array(),
        'allowed_property_codes' => array(),
        'mode' => 'audit',
        'is_owner' => $sessionIsOwner,
        'loaded_at' => time(),
        'legacy_fallback' => 0
    );

    try {
        $sets = pms_call_procedure('sp_access_context_data', array($companyCode, $userId));
        $permissionsSet = isset($sets[0]) ? $sets[0] : array();
        $propertiesSet = isset($sets[1]) ? $sets[1] : array();
        $metaSet = isset($sets[2][0]) ? $sets[2][0] : array();

        $permissionCodes = array();
        foreach ($permissionsSet as $row) {
            $code = '';
            if (isset($row['code'])) {
                $code = (string)$row['code'];
            } elseif (isset($row[0])) {
                $code = (string)$row[0];
            }
            $code = trim($code);
            if ($code !== '') {
                $permissionCodes[] = $code;
            }
        }

        $propertyCodes = array();
        foreach ($propertiesSet as $row) {
            $code = '';
            if (isset($row['code'])) {
                $code = (string)$row['code'];
            } elseif (isset($row[0])) {
                $code = (string)$row[0];
            }
            $code = strtoupper(trim($code));
            if ($code !== '') {
                $propertyCodes[] = $code;
            }
        }

        $mode = isset($metaSet['authz_mode']) ? strtolower(trim((string)$metaSet['authz_mode'])) : 'audit';
        if ($mode !== 'enforce' && $mode !== 'audit') {
            $mode = 'audit';
        }

        $context['permission_codes'] = array_values(array_unique($permissionCodes));
        $context['allowed_property_codes'] = array_values(array_unique($propertyCodes));
        $context['mode'] = $mode;
        $context['is_owner'] = isset($metaSet['is_owner']) ? (int)$metaSet['is_owner'] : $sessionIsOwner;
    } catch (Exception $e) {
        $context['legacy_fallback'] = 1;
        $context['allowed_property_codes'] = $companyId > 0 ? pms_fetch_company_property_codes_raw($companyId) : array();
    }

    $_SESSION['pms_access'] = $context;
    return $context;
}

function pms_is_owner_user()
{
    $user = pms_current_user();
    if (!$user) {
        return false;
    }
    $context = pms_access_context(false);
    return (isset($context['is_owner']) && (int)$context['is_owner'] === 1)
        || (isset($user['is_owner']) && (int)$user['is_owner'] === 1);
}

function pms_allowed_property_codes()
{
    $context = pms_access_context(false);
    return isset($context['allowed_property_codes']) && is_array($context['allowed_property_codes'])
        ? $context['allowed_property_codes']
        : array();
}

function pms_allowed_property_code_set()
{
    $codes = pms_allowed_property_codes();
    $set = array();
    foreach ($codes as $code) {
        $key = strtoupper(trim((string)$code));
        if ($key !== '') {
            $set[$key] = true;
        }
    }
    return $set;
}

function pms_user_can($permissionCode, $propertyCode = null)
{
    $user = pms_current_user();
    if (!$user) {
        return false;
    }

    $property = strtoupper(trim((string)$propertyCode));
    if ($property !== '' && !pms_is_owner_user()) {
        $allowed = pms_allowed_property_code_set();
        if (!isset($allowed[$property])) {
            return false;
        }
    }

    $code = trim((string)$permissionCode);
    if ($code === '') {
        return true;
    }

    if (pms_is_owner_user()) {
        return true;
    }

    $context = pms_access_context(false);
    $permissionCodes = isset($context['permission_codes']) && is_array($context['permission_codes'])
        ? $context['permission_codes']
        : array();
    if (!$permissionCodes && !empty($context['legacy_fallback'])) {
        return true;
    }

    $set = array_fill_keys($permissionCodes, true);
    return isset($set[$code]);
}

function pms_require_permission($permissionCode, $propertyCode = null, $asJson = false)
{
    if (pms_user_can($permissionCode, $propertyCode)) {
        return true;
    }

    if ($asJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'error' => 'forbidden',
            'permission' => (string)$permissionCode
        ));
        exit;
    }

    http_response_code(403);
    echo '<section class="card"><h2>Acceso denegado</h2><p class="error">No tienes permiso para esta accion.</p></section>';
    exit;
}

function pms_require_property_access($propertyCode, $asJson = false)
{
    $code = strtoupper(trim((string)$propertyCode));
    if ($code === '') {
        return true;
    }
    if (pms_is_owner_user()) {
        return true;
    }
    $allowed = pms_allowed_property_code_set();
    if (isset($allowed[$code])) {
        return true;
    }

    if ($asJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'error' => 'forbidden_property',
            'property_code' => $code
        ));
        exit;
    }

    http_response_code(403);
    echo '<section class="card"><h2>Acceso denegado</h2><p class="error">No tienes acceso a la propiedad seleccionada.</p></section>';
    exit;
}

function pms_allowed_property_codes_csv()
{
    return implode(',', pms_allowed_property_codes());
}

function pms_resolve_allowed_property_or_fail($postedPropertyCode)
{
    $posted = strtoupper(trim((string)$postedPropertyCode));
    $allowed = pms_allowed_property_codes();
    if ($posted !== '') {
        pms_require_property_access($posted, false);
        return $posted;
    }
    if (!empty($allowed)) {
        return (string)$allowed[0];
    }
    if (pms_is_owner_user()) {
        return '';
    }
    http_response_code(403);
    echo '<section class="card"><h2>Acceso denegado</h2><p class="error">No tienes propiedades asignadas.</p></section>';
    exit;
}

function pms_module_view_permission($viewKey)
{
    static $map = array(
        'dashboard' => 'dashboard.view',
        'dashboard_mobile' => 'dashboard.view',
        'calendar' => 'calendar.view',
        'reservations' => 'reservations.view',
        'reservation_wizard' => 'reservations.view',
        'guests' => 'guests.view',
        'activities' => 'activities.view',
        'properties' => 'properties.view',
        'rooms' => 'rooms.view',
        'categories' => 'categories.view',
        'rateplans' => 'rateplans.view',
        'messages' => 'messages.view',
        'otas' => 'otas.view',
        'ota_ical' => 'ota_ical.view',
        'sale_items' => 'sale_items.view',
        'payments' => 'payments.view',
        'incomes' => 'incomes.view',
        'obligations' => 'obligations.view',
        'reports' => 'reports.view',
        'sale_item_report' => 'reports.view',
        'settings' => 'settings.view',
        'users' => 'users.view',
        'user_roles' => 'users.manage_roles'
    );
    return isset($map[$viewKey]) ? $map[$viewKey] : '';
}

function pms_lookup_property_id_for_company($companyId, $code, $enforceScope = true)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare('SELECT id_property FROM property WHERE id_company = ? AND code = ? LIMIT 1');
    $stmt->execute(array($companyId, $code));
    $value = $stmt->fetchColumn();
    if ($value === false) {
        return null;
    }
    $propertyCode = strtoupper(trim((string)$code));
    if ($enforceScope && $propertyCode !== '') {
        pms_require_property_access($propertyCode, false);
    }
    return (int)$value;
}

function pms_fetch_properties($companyId)
{
    $pdo = pms_get_connection();
    $colorSelectSql = pms_property_has_column($pdo, 'color_hex')
        ? ", COALESCE(NULLIF(TRIM(color_hex), ''), '') AS color_hex"
        : ", '' AS color_hex";
    $stmt = $pdo->prepare(
        'SELECT id_property, code, name, order_index' . $colorSelectSql . '
         FROM property
         WHERE id_company = ? AND is_active = 1 AND deleted_at IS NULL
         ORDER BY order_index, name'
    );
    $stmt->execute(array($companyId));
    $rows = $stmt->fetchAll();
    if (pms_is_owner_user()) {
        return $rows;
    }
    $allowed = pms_allowed_property_code_set();
    if (!$allowed) {
        return array();
    }
    $filtered = array();
    foreach ($rows as $row) {
        $code = isset($row['code']) ? strtoupper(trim((string)$row['code'])) : '';
        if ($code !== '' && isset($allowed[$code])) {
            $filtered[] = $row;
        }
    }
    return $filtered;
}

function pms_property_has_column(PDO $pdo, $columnName)
{
    static $cached = array();
    $column = strtolower(trim((string)$columnName));
    if ($column === '') {
        return false;
    }
    if (array_key_exists($column, $cached)) {
        return (bool)$cached[$column];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?'
        );
        $stmt->execute(array('property', $column));
        $cached[$column] = ((int)$stmt->fetchColumn() > 0);
    } catch (Exception $e) {
        $cached[$column] = false;
    }
    return (bool)$cached[$column];
}

function pms_property_normalize_color_hex($value)
{
    $hex = strtoupper(trim((string)$value));
    if ($hex === '') {
        return '';
    }
    if (strpos($hex, '#') !== 0) {
        $hex = '#' . $hex;
    }
    return preg_match('/^#[0-9A-F]{6}$/', $hex) ? $hex : '';
}

function pms_has_category_calendar_amenity_display_table(PDO $pdo)
{
    static $cached = null;
    if ($cached !== null) {
        return (bool)$cached;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?'
        );
        $stmt->execute(array('category_calendar_amenity_display'));
        $cached = ((int)$stmt->fetchColumn() > 0);
    } catch (Exception $e) {
        $cached = false;
    }
    return (bool)$cached;
}

function pms_fetch_rooms_for_company($companyId)
{
    $pdo = pms_get_connection();
    $hasCategoryCalendarDisplay = pms_has_category_calendar_amenity_display_table($pdo);
    $calendarSelectSql = $hasCategoryCalendarDisplay
        ? "COALESCE(cad.calendar_amenities_csv, '') AS calendar_amenities_csv,"
        : "'' AS calendar_amenities_csv,";
    $calendarJoinSql = $hasCategoryCalendarDisplay
        ? ' LEFT JOIN (
                SELECT
                    t.id_category,
                    GROUP_CONCAT(t.amenity_key ORDER BY t.display_order, t.id_category_calendar_amenity_display SEPARATOR \',\') AS calendar_amenities_csv
                FROM category_calendar_amenity_display t
                WHERE t.is_active = 1
                GROUP BY t.id_category
           ) cad ON cad.id_category = rc.id_category '
        : '';
    $stmt = $pdo->prepare(
        'SELECT r.id_room,
                r.code,
                r.name,
                r.id_property,
                r.id_category,
                r.order_index,
                rc.default_base_price_cents,
                rc.code AS category_code,
                rc.name AS category_name,
                rc.max_occupancy,
                rc.order_index AS category_order_index,
                ' . $calendarSelectSql . '
                p.code AS property_code,
                p.name AS property_name
         FROM room r
         JOIN property p ON p.id_property = r.id_property
         LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
         ' . $calendarJoinSql . '
         WHERE p.id_company = ? AND r.deleted_at IS NULL AND r.is_active = 1
         ORDER BY p.order_index, p.name, rc.order_index, r.order_index, r.code'
    );
    $stmt->execute(array($companyId));
    $rows = $stmt->fetchAll();
    if (pms_is_owner_user()) {
        return $rows;
    }
    $allowed = pms_allowed_property_code_set();
    if (!$allowed) {
        return array();
    }
    $filtered = array();
    foreach ($rows as $row) {
        $propertyCode = isset($row['property_code']) ? strtoupper(trim((string)$row['property_code'])) : '';
        if ($propertyCode !== '' && isset($allowed[$propertyCode])) {
            $filtered[] = $row;
        }
    }
    return $filtered;
}

function pms_fetch_rooms_for_property($propertyId)
{
    if ((int)$propertyId > 0 && !pms_is_owner_user()) {
        $pdoCheck = pms_get_connection();
        $stmtCheck = $pdoCheck->prepare('SELECT code FROM property WHERE id_property = ? LIMIT 1');
        $stmtCheck->execute(array((int)$propertyId));
        $propertyCode = strtoupper(trim((string)$stmtCheck->fetchColumn()));
        pms_require_property_access($propertyCode, false);
    }
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT id_room, code, name, order_index
         FROM room
         WHERE id_property = ? AND deleted_at IS NULL AND is_active = 1
         ORDER BY order_index, code'
    );
    $stmt->execute(array($propertyId));
    return $stmt->fetchAll();
}

function pms_fetch_categories_for_company($companyId)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT rc.id_category,
                rc.code,
                rc.name,
                rc.id_property,
                rc.order_index,
                p.code AS property_code,
                p.name AS property_name
         FROM roomcategory rc
         JOIN property p ON p.id_property = rc.id_property
         WHERE p.id_company = ?
           AND rc.deleted_at IS NULL
           AND rc.is_active = 1
           AND p.deleted_at IS NULL
         ORDER BY p.order_index, p.name, rc.order_index, rc.name'
    );
    $stmt->execute(array($companyId));
    $rows = $stmt->fetchAll();
    if (pms_is_owner_user()) {
        return $rows;
    }
    $allowed = pms_allowed_property_code_set();
    if (!$allowed) {
        return array();
    }
    $filtered = array();
    foreach ($rows as $row) {
        $propertyCode = isset($row['property_code']) ? strtoupper(trim((string)$row['property_code'])) : '';
        if ($propertyCode !== '' && isset($allowed[$propertyCode])) {
            $filtered[] = $row;
        }
    }
    return $filtered;
}

function pms_fetch_activities_map($companyId)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT id_activity, code, name, type
         FROM activity
         WHERE id_company = ? AND deleted_at IS NULL
         ORDER BY type, name'
    );
    $stmt->execute(array($companyId));
    return $stmt->fetchAll();
}

function pms_reservation_source_from_ota_platform($platform)
{
    $normalized = strtolower(trim((string)$platform));
    if ($normalized === 'booking') {
        return 'booking';
    }
    if ($normalized === 'airbnb' || $normalized === 'abb') {
        return 'airbnb';
    }
    if ($normalized === 'expedia') {
        return 'expedia';
    }
    return 'otro';
}

function pms_reservation_source_has_column(PDO $pdo, $columnName)
{
    static $cached = array();
    $column = strtolower(trim((string)$columnName));
    if ($column === '') {
        return false;
    }
    if (array_key_exists($column, $cached)) {
        return (bool)$cached[$column];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?'
        );
        $stmt->execute(array('reservation_source_catalog', $column));
        $cached[$column] = ((int)$stmt->fetchColumn() > 0);
    } catch (Exception $e) {
        $cached[$column] = false;
    }
    return (bool)$cached[$column];
}

function pms_reservation_source_normalize_code($value, $maxLen = 12)
{
    $code = strtoupper(trim((string)$value));
    if ($code === '') {
        return '';
    }
    $code = preg_replace('/[^A-Z0-9]+/', '', $code);
    $limit = (int)$maxLen > 0 ? (int)$maxLen : 12;
    if (function_exists('mb_substr')) {
        $code = mb_substr($code, 0, $limit);
    } else {
        $code = substr($code, 0, $limit);
    }
    return $code;
}

function pms_reservation_source_normalize_color_hex($value)
{
    $hex = strtoupper(trim((string)$value));
    if ($hex === '') {
        return '';
    }
    if (strpos($hex, '#') !== 0) {
        $hex = '#' . $hex;
    }
    return preg_match('/^#[0-9A-F]{6}$/', $hex) ? $hex : '';
}

function pms_ota_account_has_color_hex_column(PDO $pdo)
{
    return true;
}

function pms_ota_account_has_price_adjustment_columns(PDO $pdo)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME IN (?, ?)'
        );
        $stmt->execute(array('ota_account', 'price_adjustment_mode', 'price_adjustment_value'));
        $ready = ((int)$stmt->fetchColumn()) >= 2;
    } catch (Exception $e) {
        $ready = false;
    }
    return $ready;
}

function pms_ota_account_has_secondary_price_adjustment_column(PDO $pdo)
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?'
        );
        $stmt->execute(array('ota_account', 'secondary_price_adjustment_pct'));
        $ready = ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        $ready = false;
    }
    return $ready;
}

function pms_fetch_ota_accounts($companyId, $propertyCode = null, $includeInactive = false)
{
    $pdo = pms_get_connection();
    $prop = strtoupper(trim((string)$propertyCode));
    $colorSelectSql = pms_ota_account_has_color_hex_column($pdo)
        ? "COALESCE(NULLIF(TRIM(oa.color_hex), ''), '') AS color_hex,"
        : "'' AS color_hex,";
    $pricingSelectSql = pms_ota_account_has_price_adjustment_columns($pdo)
        ? "COALESCE(NULLIF(TRIM(oa.price_adjustment_mode), ''), 'none') AS price_adjustment_mode,
           COALESCE(oa.price_adjustment_value, 0) AS price_adjustment_value,
           " . (pms_ota_account_has_secondary_price_adjustment_column($pdo)
                ? "COALESCE(oa.secondary_price_adjustment_pct, 0) AS secondary_price_adjustment_pct,"
                : "0 AS secondary_price_adjustment_pct,")
        : "'none' AS price_adjustment_mode,
           0 AS price_adjustment_value,
           0 AS secondary_price_adjustment_pct,";
    $sql = 'SELECT
            oa.id_ota_account,
            oa.id_company,
            oa.id_property,
            p.code AS property_code,
            p.name AS property_name,
            oa.platform,
            oa.ota_name,
            ' . $colorSelectSql . '
            ' . $pricingSelectSql . '
            oa.external_code,
            oa.contact_email,
            oa.timezone,
            oa.notes,
            oa.id_service_fee_payment_catalog,
            COALESCE(sfp.item_name, \'\') AS service_fee_payment_catalog_name,
            oa.is_active,
            oa.deleted_at,
            oa.created_at,
            oa.updated_at
         FROM ota_account oa
         JOIN property p
           ON p.id_property = oa.id_property
          AND p.deleted_at IS NULL
         LEFT JOIN line_item_catalog sfp
           ON sfp.id_line_item_catalog = oa.id_service_fee_payment_catalog
          AND sfp.deleted_at IS NULL
         WHERE oa.id_company = ?
           AND (
                ? <> 0
                OR (oa.deleted_at IS NULL AND oa.is_active = 1)
           )
    ';
    $params = array((int)$companyId, $includeInactive ? 1 : 0);
    if ($prop !== '') {
        $sql .= ' AND p.code = ?';
        $params[] = $prop;
    }
    $sql .= ' ORDER BY p.code, oa.platform, oa.ota_name, oa.id_ota_account';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function pms_fetch_ota_accounts_grouped($companyId, $includeInactive = false)
{
    $rows = pms_fetch_ota_accounts($companyId, null, $includeInactive);
    $grouped = array();
    foreach ($rows as $row) {
        $propertyCode = strtoupper((string)(isset($row['property_code']) ? $row['property_code'] : ''));
        if ($propertyCode === '') {
            continue;
        }
        if (!isset($grouped[$propertyCode])) {
            $grouped[$propertyCode] = array();
        }
        $source = pms_reservation_source_from_ota_platform(isset($row['platform']) ? $row['platform'] : '');
        $grouped[$propertyCode][] = array(
            'id_ota_account' => isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0,
            'platform' => isset($row['platform']) ? (string)$row['platform'] : 'other',
            'ota_name' => isset($row['ota_name']) ? (string)$row['ota_name'] : '',
            'color_hex' => isset($row['color_hex']) ? (string)$row['color_hex'] : '',
            'price_adjustment_mode' => isset($row['price_adjustment_mode']) ? (string)$row['price_adjustment_mode'] : 'none',
            'price_adjustment_value' => isset($row['price_adjustment_value']) ? (float)$row['price_adjustment_value'] : 0,
            'secondary_price_adjustment_pct' => isset($row['secondary_price_adjustment_pct']) ? (float)$row['secondary_price_adjustment_pct'] : 0,
            'external_code' => isset($row['external_code']) ? (string)$row['external_code'] : '',
            'source' => $source
        );
    }
    return $grouped;
}

function pms_ota_options_for_property(array $grouped, $propertyCode, $includeNone = true)
{
    $out = array();
    $seenIds = array();
    if ($includeNone) {
        $out[] = array(
            'id_ota_account' => 0,
            'ota_name' => 'Sin origen',
            'platform' => 'other',
            'color_hex' => '',
            'source' => 'otro'
        );
    }

    $appendRows = function ($rows) use (&$out, &$seenIds) {
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $row) {
            $id = isset($row['id_ota_account']) ? (int)$row['id_ota_account'] : 0;
            if ($id <= 0 || isset($seenIds[$id])) {
                continue;
            }
            $seenIds[$id] = true;
            $name = trim((string)(isset($row['ota_name']) ? $row['ota_name'] : ''));
            $platform = strtolower(trim((string)(isset($row['platform']) ? $row['platform'] : 'other')));
            $colorHex = trim((string)(isset($row['color_hex']) ? $row['color_hex'] : ''));
            $source = pms_reservation_source_from_ota_platform(isset($row['source']) ? $row['source'] : $platform);
            $label = $name !== '' ? $name : ('Origen #' . $id);
            $out[] = array(
                'id_ota_account' => $id,
                'ota_name' => $label,
                'platform' => $platform,
                'color_hex' => $colorHex,
                'source' => $source
            );
        }
    };

    // OTA scope is company-wide: options are global across properties.
    foreach ($grouped as $propRows) {
        $appendRows($propRows);
    }

    return $out;
}

function pms_fetch_reservation_sources($companyId, $propertyCode = null, $includeInactive = false)
{
    $pdo = pms_get_connection();
    $prop = strtoupper(trim((string)$propertyCode));
    $sourceCodeSelectSql = pms_reservation_source_has_column($pdo, 'source_code')
        ? "COALESCE(NULLIF(TRIM(rsc.source_code), ''), '') AS source_code,"
        : "'' AS source_code,";
    $sourceColorSelectSql = pms_reservation_source_has_column($pdo, 'color_hex')
        ? "COALESCE(NULLIF(TRIM(rsc.color_hex), ''), '') AS color_hex,"
        : "'' AS color_hex,";
    $sourceLodgingCatalogSelectSql = pms_reservation_source_has_column($pdo, 'id_lodging_catalog')
        ? "COALESCE(rsc.id_lodging_catalog, 0) AS id_lodging_catalog,"
        : "0 AS id_lodging_catalog,";
    $sql = 'SELECT
              rsc.id_reservation_source,
              rsc.id_company,
              rsc.id_property,
              p.code AS property_code,
              p.name AS property_name,
              rsc.source_name,
              ' . $sourceCodeSelectSql . '
              ' . $sourceColorSelectSql . '
              ' . $sourceLodgingCatalogSelectSql . '
              COALESCE(rsc.notes, \'\') AS notes,
              rsc.is_active,
              rsc.deleted_at,
              rsc.created_at,
              rsc.updated_at
            FROM reservation_source_catalog rsc
            LEFT JOIN property p
              ON p.id_property = rsc.id_property
             AND p.deleted_at IS NULL
            WHERE rsc.id_company = ?
              AND (
                    ? <> 0
                    OR (rsc.deleted_at IS NULL AND rsc.is_active = 1)
              )';
    $params = array((int)$companyId, $includeInactive ? 1 : 0);

    if ($prop !== '') {
        $sql .= ' AND (
                    p.code = ?
                    OR rsc.id_property IS NULL
                 )';
        $params[] = $prop;
    }

    $sql .= ' ORDER BY
                CASE WHEN rsc.id_property IS NULL THEN 0 ELSE 1 END,
                p.code,
                rsc.source_name,
                rsc.id_reservation_source';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function pms_fetch_reservation_sources_grouped($companyId, $includeInactive = false)
{
    $rows = pms_fetch_reservation_sources($companyId, null, $includeInactive);
    $grouped = array('*' => array());
    foreach ($rows as $row) {
        $id = isset($row['id_reservation_source']) ? (int)$row['id_reservation_source'] : 0;
        if ($id <= 0) {
            continue;
        }
        $entry = array(
            'id_reservation_source' => $id,
            'source_name' => isset($row['source_name']) ? (string)$row['source_name'] : ('Origen #' . $id),
            'source_code' => isset($row['source_code']) ? (string)$row['source_code'] : '',
            'color_hex' => isset($row['color_hex']) ? (string)$row['color_hex'] : '',
            'id_lodging_catalog' => isset($row['id_lodging_catalog']) ? (int)$row['id_lodging_catalog'] : 0,
            'notes' => isset($row['notes']) ? (string)$row['notes'] : '',
            'id_property' => isset($row['id_property']) ? (int)$row['id_property'] : null,
            'property_code' => isset($row['property_code']) ? strtoupper((string)$row['property_code']) : ''
        );
        if (empty($entry['id_property'])) {
            $grouped['*'][] = $entry;
        } else {
            $propCode = $entry['property_code'];
            if ($propCode === '') {
                $grouped['*'][] = $entry;
            } else {
                if (!isset($grouped[$propCode])) {
                    $grouped[$propCode] = array();
                }
                $grouped[$propCode][] = $entry;
            }
        }
    }
    return $grouped;
}

function pms_reservation_source_options_for_property(array $grouped, $propertyCode, $includeFallback = true)
{
    $prop = strtoupper(trim((string)$propertyCode));
    $out = array();
    $seen = array();

    if (isset($grouped['*']) && is_array($grouped['*'])) {
        foreach ($grouped['*'] as $row) {
            $id = isset($row['id_reservation_source']) ? (int)$row['id_reservation_source'] : 0;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $row;
        }
    }

    if ($prop !== '' && isset($grouped[$prop]) && is_array($grouped[$prop])) {
        foreach ($grouped[$prop] as $row) {
            $id = isset($row['id_reservation_source']) ? (int)$row['id_reservation_source'] : 0;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $row;
        }
    }

    if (!$out && $includeFallback) {
        $out[] = array(
            'id_reservation_source' => 0,
            'source_name' => 'Directo',
            'source_code' => '',
            'color_hex' => '',
            'id_lodging_catalog' => 0,
            'notes' => '',
            'id_property' => null,
            'property_code' => ''
        );
    }

    return $out;
}

if (!function_exists('pms_table_exists')) {
    function pms_table_exists(PDO $pdo, $tableName)
    {
        static $cache = array();
        $table = trim((string)$tableName);
        if ($table === '') {
            return false;
        }
        $cacheKey = strtolower($table);
        if (array_key_exists($cacheKey, $cache)) {
            return (bool)$cache[$cacheKey];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?'
            );
            $stmt->execute(array($table));
            $cache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $cache[$cacheKey] = false;
        }
        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('pms_folio_role_by_name')) {
    function pms_folio_role_by_name($folioName)
    {
        $name = strtolower(trim((string)$folioName));
        if ($name === '') {
            return 'lodging';
        }
        $hasServiceWord = (strpos($name, 'servicio') !== false || strpos($name, 'service') !== false);
        $hasLodgingHint = (strpos($name, 'hosped') !== false || strpos($name, 'principal') !== false || strpos($name, 'main') !== false);
        if ($hasServiceWord && !$hasLodgingHint) {
            return 'services';
        }
        return 'lodging';
    }
}

if (!function_exists('pms_reservation_blocked_payment_catalog_ids_bulk')) {
    function pms_reservation_blocked_payment_catalog_ids_bulk($companyId, array $reservationIds)
    {
        $companyId = (int)$companyId;
        $result = array();
        $reservationIds = array_values(array_unique(array_filter(array_map('intval', $reservationIds), function ($id) {
            return $id > 0;
        })));
        foreach ($reservationIds as $reservationId) {
            $result[(int)$reservationId] = array();
        }
        if ($companyId <= 0 || !$reservationIds) {
            return $result;
        }

        try {
            $pdo = pms_get_connection();
            if (!pms_table_exists($pdo, 'pms_settings_lodging_payment_block')) {
                return $result;
            }

            $reservationPropertyId = array();
            $reservationChunks = array_chunk($reservationIds, 400);
            foreach ($reservationChunks as $reservationChunk) {
                if (!$reservationChunk) {
                    continue;
                }
                $placeholders = implode(',', array_fill(0, count($reservationChunk), '?'));
                $stmtReservation = $pdo->prepare(
                    'SELECT
                        r.id_reservation,
                        r.id_property
                     FROM reservation r
                     JOIN property p
                       ON p.id_property = r.id_property
                      AND p.id_company = ?
                      AND p.deleted_at IS NULL
                     WHERE r.deleted_at IS NULL
                       AND r.id_reservation IN (' . $placeholders . ')'
                );
                $stmtReservation->execute(array_merge(array($companyId), $reservationChunk));
                foreach ($stmtReservation->fetchAll(PDO::FETCH_ASSOC) as $reservationRow) {
                    $reservationId = isset($reservationRow['id_reservation']) ? (int)$reservationRow['id_reservation'] : 0;
                    $propertyId = isset($reservationRow['id_property']) ? (int)$reservationRow['id_property'] : 0;
                    if ($reservationId <= 0 || $propertyId <= 0) {
                        continue;
                    }
                    $reservationPropertyId[$reservationId] = $propertyId;
                }
            }
            if (!$reservationPropertyId) {
                return $result;
            }

            $propertyIds = array_values(array_unique(array_filter(array_map('intval', array_values($reservationPropertyId)), function ($id) {
                return $id > 0;
            })));
            $paramsBlocks = array($companyId);
            $scopeSql = ' AND (b.id_property IS NULL';
            if ($propertyIds) {
                $propertyPlaceholders = implode(',', array_fill(0, count($propertyIds), '?'));
                $scopeSql .= ' OR b.id_property IN (' . $propertyPlaceholders . ')';
                $paramsBlocks = array_merge($paramsBlocks, $propertyIds);
            }
            $scopeSql .= ')';

            $stmtBlocks = $pdo->prepare(
                'SELECT
                    b.id_property,
                    b.id_lodging_catalog,
                    b.id_payment_catalog
                 FROM pms_settings_lodging_payment_block b
                 WHERE b.id_company = ?
                   AND b.deleted_at IS NULL
                   AND COALESCE(b.is_active, 1) = 1'
                   . $scopeSql
            );
            $stmtBlocks->execute($paramsBlocks);
            $globalBlocksByLodging = array();
            $propertyBlocksByLodging = array();
            foreach ($stmtBlocks->fetchAll(PDO::FETCH_ASSOC) as $blockRow) {
                $propertyId = isset($blockRow['id_property']) ? (int)$blockRow['id_property'] : 0;
                $lodgingCatalogId = isset($blockRow['id_lodging_catalog']) ? (int)$blockRow['id_lodging_catalog'] : 0;
                $paymentCatalogId = isset($blockRow['id_payment_catalog']) ? (int)$blockRow['id_payment_catalog'] : 0;
                if ($lodgingCatalogId <= 0 || $paymentCatalogId <= 0) {
                    continue;
                }
                if ($propertyId > 0) {
                    if (!isset($propertyBlocksByLodging[$propertyId])) {
                        $propertyBlocksByLodging[$propertyId] = array();
                    }
                    if (!isset($propertyBlocksByLodging[$propertyId][$lodgingCatalogId])) {
                        $propertyBlocksByLodging[$propertyId][$lodgingCatalogId] = array();
                    }
                    $propertyBlocksByLodging[$propertyId][$lodgingCatalogId][$paymentCatalogId] = true;
                } else {
                    if (!isset($globalBlocksByLodging[$lodgingCatalogId])) {
                        $globalBlocksByLodging[$lodgingCatalogId] = array();
                    }
                    $globalBlocksByLodging[$lodgingCatalogId][$paymentCatalogId] = true;
                }
            }
            if (!$globalBlocksByLodging && !$propertyBlocksByLodging) {
                return $result;
            }

            $lodgingCatalogsByReservation = array();
            foreach ($reservationChunks as $reservationChunk) {
                if (!$reservationChunk) {
                    continue;
                }
                $placeholders = implode(',', array_fill(0, count($reservationChunk), '?'));
                $stmtLineItems = $pdo->prepare(
                    'SELECT
                        f.id_reservation,
                        COALESCE(f.folio_name, \'\') AS folio_name,
                        li.id_line_item_catalog
                     FROM folio f
                     JOIN reservation r
                       ON r.id_reservation = f.id_reservation
                      AND r.deleted_at IS NULL
                     JOIN property p
                       ON p.id_property = r.id_property
                      AND p.id_company = ?
                      AND p.deleted_at IS NULL
                     JOIN line_item li
                       ON li.id_folio = f.id_folio
                     WHERE f.deleted_at IS NULL
                       AND COALESCE(f.is_active, 1) = 1
                       AND li.deleted_at IS NULL
                       AND COALESCE(li.is_active, 1) = 1
                       AND LOWER(TRIM(COALESCE(li.item_type, \'\'))) = \'sale_item\'
                       AND (
                         li.status IS NULL
                         OR LOWER(TRIM(li.status)) NOT IN (\'void\', \'canceled\', \'cancelled\')
                       )
                       AND li.id_line_item_catalog IS NOT NULL
                       AND li.id_line_item_catalog > 0
                       AND f.id_reservation IN (' . $placeholders . ')'
                );
                $stmtLineItems->execute(array_merge(array($companyId), $reservationChunk));
                foreach ($stmtLineItems->fetchAll(PDO::FETCH_ASSOC) as $lineItemRow) {
                    $reservationId = isset($lineItemRow['id_reservation']) ? (int)$lineItemRow['id_reservation'] : 0;
                    $catalogId = isset($lineItemRow['id_line_item_catalog']) ? (int)$lineItemRow['id_line_item_catalog'] : 0;
                    if ($reservationId <= 0 || $catalogId <= 0) {
                        continue;
                    }
                    if (pms_folio_role_by_name(isset($lineItemRow['folio_name']) ? $lineItemRow['folio_name'] : '') !== 'lodging') {
                        continue;
                    }
                    if (!isset($lodgingCatalogsByReservation[$reservationId])) {
                        $lodgingCatalogsByReservation[$reservationId] = array();
                    }
                    $lodgingCatalogsByReservation[$reservationId][$catalogId] = true;
                }
            }

            foreach ($reservationPropertyId as $reservationId => $propertyId) {
                $reservationId = (int)$reservationId;
                $propertyId = (int)$propertyId;
                if ($reservationId <= 0 || $propertyId <= 0) {
                    continue;
                }
                $blocked = array();
                $lodgingCatalogs = isset($lodgingCatalogsByReservation[$reservationId]) ? $lodgingCatalogsByReservation[$reservationId] : array();
                foreach ($lodgingCatalogs as $lodgingCatalogId => $tmpTrue) {
                    $lodgingCatalogId = (int)$lodgingCatalogId;
                    if ($lodgingCatalogId <= 0) {
                        continue;
                    }
                    if (isset($globalBlocksByLodging[$lodgingCatalogId])) {
                        foreach ($globalBlocksByLodging[$lodgingCatalogId] as $paymentCatalogId => $tmpPayment) {
                            $blocked[(int)$paymentCatalogId] = true;
                        }
                    }
                    if (isset($propertyBlocksByLodging[$propertyId]) && isset($propertyBlocksByLodging[$propertyId][$lodgingCatalogId])) {
                        foreach ($propertyBlocksByLodging[$propertyId][$lodgingCatalogId] as $paymentCatalogId => $tmpPayment) {
                            $blocked[(int)$paymentCatalogId] = true;
                        }
                    }
                }
                $blockedIds = array_map('intval', array_keys($blocked));
                sort($blockedIds, SORT_NUMERIC);
                $result[$reservationId] = $blockedIds;
            }
        } catch (Exception $e) {
            return $result;
        }

        return $result;
    }
}

if (!function_exists('pms_reservation_blocked_payment_catalog_ids')) {
    function pms_reservation_blocked_payment_catalog_ids($companyId, $reservationId)
    {
        $reservationId = (int)$reservationId;
        if ($reservationId <= 0) {
            return array();
        }
        static $cache = array();
        $cacheKey = (int)$companyId . '|' . $reservationId;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        $map = pms_reservation_blocked_payment_catalog_ids_bulk($companyId, array($reservationId));
        $cache[$cacheKey] = isset($map[$reservationId]) && is_array($map[$reservationId]) ? $map[$reservationId] : array();
        return $cache[$cacheKey];
    }
}

if (!function_exists('pms_filter_payment_catalog_rows_by_blocked_ids')) {
    function pms_filter_payment_catalog_rows_by_blocked_ids(array $paymentRows, array $blockedIds)
    {
        if (!$paymentRows || !$blockedIds) {
            return $paymentRows;
        }
        $blockedMap = array();
        foreach ($blockedIds as $blockedId) {
            $blockedId = (int)$blockedId;
            if ($blockedId > 0) {
                $blockedMap[$blockedId] = true;
            }
        }
        if (!$blockedMap) {
            return $paymentRows;
        }
        $out = array();
        foreach ($paymentRows as $row) {
            $paymentCatalogId = isset($row['id_payment_catalog'])
                ? (int)$row['id_payment_catalog']
                : (isset($row['id_sale_item_catalog']) ? (int)$row['id_sale_item_catalog'] : 0);
            if ($paymentCatalogId > 0 && isset($blockedMap[$paymentCatalogId])) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }
}

function pms_phone_prefix_default()
{
    return '+52';
}

function pms_phone_country_rows()
{
    static $rows = null;
    if (is_array($rows)) {
        return $rows;
    }

    $rows = array();
    $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'phone_countries_es.json';
    if (is_file($file) && is_readable($file)) {
        $json = @file_get_contents($file);
        if (is_string($json) && strncmp($json, "\xEF\xBB\xBF", 3) === 0) {
            $json = substr($json, 3);
        }
        $decoded = is_string($json) ? json_decode($json, true) : null;
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $iso2 = strtolower(trim(isset($row['iso2']) ? (string)$row['iso2'] : ''));
                $name = trim(isset($row['name_es']) ? (string)$row['name_es'] : '');
                $dial = trim(isset($row['dial']) ? (string)$row['dial'] : '');
                if ($iso2 === '' || $dial === '' || !preg_match('/^\+\d{1,4}$/', $dial)) {
                    continue;
                }
                $rows[] = array(
                    'iso2' => $iso2,
                    'name_es' => ($name !== '' ? $name : strtoupper($iso2)),
                    'dial' => $dial
                );
            }
        }
    }

    if (!$rows) {
        $rows = array(
            array('iso2' => 'mx', 'name_es' => 'Mexico', 'dial' => '+52'),
            array('iso2' => 'us', 'name_es' => 'Estados Unidos', 'dial' => '+1'),
            array('iso2' => 'ca', 'name_es' => 'Canada', 'dial' => '+1'),
            array('iso2' => 'es', 'name_es' => 'Espana', 'dial' => '+34'),
            array('iso2' => 'co', 'name_es' => 'Colombia', 'dial' => '+57'),
            array('iso2' => 'ar', 'name_es' => 'Argentina', 'dial' => '+54'),
            array('iso2' => 'pe', 'name_es' => 'Peru', 'dial' => '+51'),
            array('iso2' => 'cl', 'name_es' => 'Chile', 'dial' => '+56')
        );
    }

    return $rows;
}

function pms_phone_prefix_dials_map()
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $map = array();
    $rows = pms_phone_country_rows();
    foreach ($rows as $row) {
        $dial = trim(isset($row['dial']) ? (string)$row['dial'] : '');
        if ($dial === '') {
            continue;
        }
        $map[$dial] = true;
    }
    return $map;
}

function pms_phone_extract_dial($value, $fallback = '')
{
    $raw = trim((string)$value);
    if ($raw !== '' && preg_match('/\+\d{1,4}/', $raw, $matches)) {
        return $matches[0];
    }
    return trim((string)$fallback);
}

function pms_phone_normalize_parts($phoneValue, $prefixValue = '', $fallbackPrefix = null)
{
    $dialMap = pms_phone_prefix_dials_map();
    $defaultPrefix = trim((string)($fallbackPrefix === null ? pms_phone_prefix_default() : $fallbackPrefix));
    $prefix = pms_phone_extract_dial($prefixValue, '');
    $phone = trim((string)$phoneValue);

    if ($prefix === '' && $phone !== '' && preg_match('/^(\+\d{1,4})\s*(.*)$/', $phone, $matches)) {
        $candidatePrefix = isset($matches[1]) ? trim((string)$matches[1]) : '';
        if ($candidatePrefix !== '' && isset($dialMap[$candidatePrefix])) {
            $prefix = $candidatePrefix;
            $phone = trim(isset($matches[2]) ? (string)$matches[2] : '');
        }
    }

    if ($phone !== '' && preg_match('/^(\+\d{1,4})\s*(.*)$/', $phone, $matches)) {
        $candidatePrefix = isset($matches[1]) ? trim((string)$matches[1]) : '';
        if ($candidatePrefix !== '' && isset($dialMap[$candidatePrefix])) {
            $prefix = $candidatePrefix;
            $phone = trim(isset($matches[2]) ? (string)$matches[2] : '');
        }
    }

    if ($prefix === '' || !isset($dialMap[$prefix])) {
        $prefix = $defaultPrefix;
    }
    if ($prefix !== '' && !isset($dialMap[$prefix])) {
        foreach ($dialMap as $dial => $_enabled) {
            $prefix = (string)$dial;
            break;
        }
    }

    $phone = preg_replace('/\s+/', ' ', $phone);
    $full = $phone === '' ? '' : trim($prefix . ' ' . $phone);

    return array(
        'prefix' => $prefix,
        'phone' => $phone,
        'full' => $full
    );
}

function pms_store_user_session($user)
{
    $displayName = isset($user['names']) && $user['names'] !== '' ? $user['names'] : $user['email'];
    $companyCode = isset($user['company_code']) ? $user['company_code'] : '';
    $companyName = isset($user['company_legal_name']) && $user['company_legal_name'] !== ''
        ? $user['company_legal_name']
        : $companyCode;

    $_SESSION['pms_user'] = array(
        'id_user'      => (int)$user['id_user'],
        'email'        => $user['email'],
        'display_name' => $displayName,
        'company_id'   => (int)$user['id_company'],
        'company_code' => $companyCode,
        'company_name' => $companyName,
        'is_owner'     => isset($user['is_owner']) ? (int)$user['is_owner'] : 0,
        'last_login'   => isset($user['last_login_at']) ? $user['last_login_at'] : null,
    );

    $runtimeTimezone = 'America/Mexico_City';
    $themeCode = 'default';
    try {
        $pdo = pms_get_connection();
        $runtimeTimezone = pms_fetch_company_timezone(
            $pdo,
            isset($user['id_company']) ? (int)$user['id_company'] : 0,
            $companyCode
        );
        $themeCode = pms_fetch_user_theme(
            $pdo,
            $companyCode,
            isset($user['id_user']) ? (int)$user['id_user'] : 0
        );
    } catch (Exception $e) {
        $runtimeTimezone = 'America/Mexico_City';
        $themeCode = 'default';
    }
    if (!pms_timezone_is_valid($runtimeTimezone)) {
        $runtimeTimezone = 'America/Mexico_City';
    }
    $themeCode = pms_theme_normalize_code($themeCode);

    $_SESSION['pms_user']['timezone'] = $runtimeTimezone;
    $_SESSION['pms_user']['theme_code'] = $themeCode;
    $_SESSION['pms_runtime_timezone'] = $runtimeTimezone;
    unset($_SESSION['pms_access']);
}

function pms_authenticate($email, $password)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT 
            au.id_user,
            au.id_company,
            au.email,
            au.password_hash,
            au.names,
            au.is_owner,
            au.last_name,
            au.last_login_at,
            c.code AS company_code,
            c.legal_name AS company_legal_name
         FROM app_user au
         JOIN company c ON c.id_company = au.id_company
         WHERE au.email = ? AND au.is_active = 1 AND au.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(array($email));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception('No existe un usuario activo con ese correo electr?nico.');
    }

    $hash = isset($user['password_hash']) ? (string)$user['password_hash'] : '';
    if ($hash === '') {
        throw new Exception('El usuario no tiene una contrase?a configurada.');
    }

    $info = password_get_info($hash);
    $isValid = false;
    if (!empty($info['algo']) && function_exists('password_verify')) {
        $isValid = password_verify($password, $hash);
    } else {
        $isValid = hash_equals($hash, $password);
    }

    if (!$isValid) {
        throw new Exception('Contrase?a incorrecta.');
    }

    session_regenerate_id(true);

    pms_store_user_session($user);
    pms_bootstrap_runtime_timezone($pdo);
    pms_access_context(true);

    try {
        $pdo->prepare('UPDATE app_user SET last_login_at = NOW() WHERE id_user = ?')->execute(array((int)$user['id_user']));
    } catch (Exception $e) {
        error_log('[PMS login] failed to update last_login_at: ' . $e->getMessage());
    }

    return true;
}

function pms_force_login_by_company($companyCode)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT 
            au.id_user,
            au.id_company,
            au.email,
            au.names,
            au.is_owner,
            au.last_name,
            au.last_login_at,
            c.code AS company_code,
            c.legal_name AS company_legal_name
         FROM app_user au
         JOIN company c ON c.id_company = au.id_company
         WHERE c.code = ? AND au.is_active = 1 AND au.deleted_at IS NULL
         ORDER BY au.id_user
         LIMIT 1'
    );
    $stmt->execute(array($companyCode));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }
    session_regenerate_id(true);
    pms_store_user_session($user);
    pms_bootstrap_runtime_timezone($pdo);
    pms_access_context(true);
    return true;
}

function pms_logout()
{
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $secure = (
            (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        );
        $cookiePath = isset($params['path']) && (string)$params['path'] !== '' ? (string)$params['path'] : '/';
        $cookieDomain = isset($params['domain']) ? (string)$params['domain'] : '';

        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', array(
                'expires' => time() - 3600,
                'path' => $cookiePath,
                'domain' => $cookieDomain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ));
        } else {
            setcookie(session_name(), '', time() - 3600, $cookiePath, $cookieDomain, $secure, true);
        }
    }

    session_destroy();
}

function pms_current_user()
{
    return isset($_SESSION['pms_user']) ? $_SESSION['pms_user'] : null;
}

function pms_require_login()
{
    $user = pms_current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}
