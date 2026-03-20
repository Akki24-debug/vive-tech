<?php
if (session_status() === PHP_SESSION_NONE) {
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
    static $cache = null;
    if ($cache !== null) {
        return (bool)$cache;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM pms_settings LIKE 'timezone'");
        $cache = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $cache = false;
    }
    return (bool)$cache;
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

function pms_lookup_property_id_for_company($companyId, $code)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare('SELECT id_property FROM property WHERE id_company = ? AND code = ? LIMIT 1');
    $stmt->execute(array($companyId, $code));
    $value = $stmt->fetchColumn();
    return $value !== false ? (int)$value : null;
}

function pms_fetch_properties($companyId)
{
    $pdo = pms_get_connection();
    $stmt = $pdo->prepare(
        'SELECT id_property, code, name, order_index
         FROM property
         WHERE id_company = ? AND is_active = 1 AND deleted_at IS NULL
         ORDER BY order_index, name'
    );
    $stmt->execute(array($companyId));
    return $stmt->fetchAll();
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
    return $stmt->fetchAll();
}

function pms_fetch_rooms_for_property($propertyId)
{
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
    static $cached = null;
    if ($cached !== null) {
        return (bool)$cached;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM ota_account LIKE 'color_hex'");
        $cached = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $cached = false;
    }
    return (bool)$cached;
}

function pms_fetch_ota_accounts($companyId, $propertyCode = null, $includeInactive = false)
{
    $pdo = pms_get_connection();
    $prop = strtoupper(trim((string)$propertyCode));
    $colorSelectSql = pms_ota_account_has_color_hex_column($pdo)
        ? "COALESCE(NULLIF(TRIM(oa.color_hex), ''), '') AS color_hex,"
        : "'' AS color_hex,";
    $sql = 'SELECT
            oa.id_ota_account,
            oa.id_company,
            oa.id_property,
            p.code AS property_code,
            p.name AS property_name,
            oa.platform,
            oa.ota_name,
            ' . $colorSelectSql . '
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
            $suffix = $platform !== '' ? strtoupper($platform) : 'OTA';
            $label = $name !== '' ? ($name . ' [' . $suffix . ']') : ('Origen #' . $id . ' [' . $suffix . ']');
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
    $sql = 'SELECT
              rsc.id_reservation_source,
              rsc.id_company,
              rsc.id_property,
              p.code AS property_code,
              p.name AS property_name,
              rsc.source_name,
              ' . $sourceCodeSelectSql . '
              ' . $sourceColorSelectSql . '
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
            'notes' => '',
            'id_property' => null,
            'property_code' => ''
        );
    }

    return $out;
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
        'last_login'   => isset($user['last_login_at']) ? $user['last_login_at'] : null,
    );

    $runtimeTimezone = 'America/Mexico_City';
    try {
        $runtimeTimezone = pms_fetch_company_timezone(
            pms_get_connection(),
            isset($user['id_company']) ? (int)$user['id_company'] : 0,
            $companyCode
        );
    } catch (Exception $e) {
        $runtimeTimezone = 'America/Mexico_City';
    }
    if (!pms_timezone_is_valid($runtimeTimezone)) {
        $runtimeTimezone = 'America/Mexico_City';
    }

    $_SESSION['pms_user']['timezone'] = $runtimeTimezone;
    $_SESSION['pms_runtime_timezone'] = $runtimeTimezone;
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
    return true;
}

function pms_logout()
{
    unset($_SESSION['pms_user']);
    unset($_SESSION['pms_runtime_timezone']);
    session_regenerate_id(true);
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
