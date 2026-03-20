<?php

if (!function_exists('pms_ota_ical_table_exists')) {
    function pms_ota_ical_table_exists(PDO $pdo, $tableName)
    {
        static $cache = array();
        $table = strtolower(trim((string)$tableName));
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return (bool)$cache[$table];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = ?'
            );
            $stmt->execute(array($table));
            $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $cache[$table] = false;
        }
        return (bool)$cache[$table];
    }
}

if (!function_exists('pms_ota_ical_column_exists')) {
    function pms_ota_ical_column_exists(PDO $pdo, $tableName, $columnName)
    {
        static $cache = array();
        $table = strtolower(trim((string)$tableName));
        $column = strtolower(trim((string)$columnName));
        if ($table === '' || $column === '') {
            return false;
        }
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return (bool)$cache[$key];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = ?'
            );
            $stmt->execute(array($table, $column));
            $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $cache[$key] = false;
        }
        return (bool)$cache[$key];
    }
}

if (!function_exists('pms_ota_ical_generate_token')) {
    function pms_ota_ical_generate_token($length = 32)
    {
        $len = (int)$length;
        if ($len < 16) {
            $len = 16;
        }
        if ($len > 128) {
            $len = 128;
        }
        $bytes = (int)ceil($len / 2);
        try {
            $raw = bin2hex(random_bytes($bytes));
        } catch (Exception $e) {
            $raw = md5(uniqid('ota-ical-', true)) . sha1(uniqid('', true));
        }
        return substr($raw, 0, $len);
    }
}

if (!function_exists('pms_ota_ical_feed_columns')) {
    function pms_ota_ical_feed_columns(PDO $pdo)
    {
        static $columns = null;
        if (is_array($columns)) {
            return $columns;
        }
        $columns = array();
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_feed')) {
            return $columns;
        }
        try {
            $rows = $pdo->query('SHOW COLUMNS FROM ota_ical_feed')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $field = isset($row['Field']) ? strtolower((string)$row['Field']) : '';
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        } catch (Exception $e) {
            $columns = array();
        }
        return $columns;
    }
}

if (!function_exists('pms_ota_ical_event_columns')) {
    function pms_ota_ical_event_columns(PDO $pdo)
    {
        static $columns = null;
        if (is_array($columns)) {
            return $columns;
        }
        $columns = array();
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_event')) {
            return $columns;
        }
        try {
            $rows = $pdo->query('SHOW COLUMNS FROM ota_ical_event')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $field = isset($row['Field']) ? strtolower((string)$row['Field']) : '';
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        } catch (Exception $e) {
            $columns = array();
        }
        return $columns;
    }
}

if (!function_exists('pms_ota_ical_map_columns')) {
    function pms_ota_ical_map_columns(PDO $pdo)
    {
        static $columns = null;
        if (is_array($columns)) {
            return $columns;
        }
        $columns = array();
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_event_map')) {
            return $columns;
        }
        try {
            $rows = $pdo->query('SHOW COLUMNS FROM ota_ical_event_map')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $field = isset($row['Field']) ? strtolower((string)$row['Field']) : '';
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        } catch (Exception $e) {
            $columns = array();
        }
        return $columns;
    }
}

if (!function_exists('pms_ota_ical_feed_blocks_column_name')) {
    function pms_ota_ical_feed_blocks_column_name(PDO $pdo)
    {
        $columns = pms_ota_ical_feed_columns($pdo);
        if (isset($columns['export_include_room_blocks'])) {
            return 'export_include_room_blocks';
        }
        if (isset($columns['export_include_blocks'])) {
            return 'export_include_blocks';
        }
        return null;
    }
}

if (!function_exists('pms_ota_ical_feed_timezone')) {
    function pms_ota_ical_feed_timezone(array $feedRow)
    {
        $tzCandidates = array(
            isset($feedRow['ota_timezone']) ? $feedRow['ota_timezone'] : '',
            isset($feedRow['feed_timezone']) ? $feedRow['feed_timezone'] : '',
            isset($feedRow['timezone']) ? $feedRow['timezone'] : '',
            isset($feedRow['company_timezone']) ? $feedRow['company_timezone'] : '',
            'America/Mexico_City'
        );
        foreach ($tzCandidates as $candidate) {
            $tz = trim((string)$candidate);
            if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
                return $tz;
            }
        }
        return 'America/Mexico_City';
    }
}

if (!function_exists('pms_ota_ical_feed_enum_options')) {
    function pms_ota_ical_feed_enum_options(PDO $pdo, $columnName)
    {
        static $cache = array();
        $column = strtolower(trim((string)$columnName));
        if ($column === '') {
            return array();
        }
        if (isset($cache[$column])) {
            return $cache[$column];
        }
        if ($column === 'export_summary_mode') {
            $cache[$column] = array('reserved', 'reservation_code', 'guest_name');
            return $cache[$column];
        }
        $cache[$column] = array();
        return $cache[$column];
    }
}

if (!function_exists('pms_ota_ical_summary_modes')) {
    function pms_ota_ical_summary_modes(PDO $pdo)
    {
        $default = array('reserved', 'reservation_code', 'guest_name');
        $enumValues = pms_ota_ical_feed_enum_options($pdo, 'export_summary_mode');
        if (!$enumValues) {
            return $default;
        }
        return $enumValues;
    }
}

if (!function_exists('pms_ota_ical_escape_text')) {
    function pms_ota_ical_escape_text($value)
    {
        $text = (string)$value;
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(array("\r\n", "\r", "\n"), '\\n', $text);
        return $text;
    }
}

if (!function_exists('pms_ota_ical_unescape_text')) {
    function pms_ota_ical_unescape_text($value)
    {
        $text = (string)$value;
        $text = str_replace('\\n', "\n", $text);
        $text = str_replace('\\N', "\n", $text);
        $text = str_replace('\\,', ',', $text);
        $text = str_replace('\\;', ';', $text);
        $text = str_replace('\\\\', '\\', $text);
        return $text;
    }
}

if (!function_exists('pms_ota_ical_fold_line')) {
    function pms_ota_ical_fold_line($line)
    {
        $line = (string)$line;
        if ($line === '') {
            return "\r\n";
        }
        $maxBytes = 75;
        $out = '';
        $remaining = $line;
        $first = true;
        while ($remaining !== '') {
            if (function_exists('mb_strcut')) {
                $chunk = mb_strcut($remaining, 0, $maxBytes, 'UTF-8');
                $remaining = (string)mb_strcut($remaining, strlen($chunk), null, 'UTF-8');
            } else {
                $chunk = substr($remaining, 0, $maxBytes);
                $remaining = (string)substr($remaining, $maxBytes);
            }
            if (!$first) {
                $out .= ' ';
            }
            $out .= $chunk . "\r\n";
            $first = false;
        }
        return $out;
    }
}

if (!function_exists('pms_ota_ical_dt_utc')) {
    function pms_ota_ical_dt_utc($dateTimeString)
    {
        $source = trim((string)$dateTimeString);
        try {
            $dt = $source !== '' ? new DateTime($source) : new DateTime('now');
        } catch (Exception $e) {
            $dt = new DateTime('now');
        }
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Ymd\\THis\\Z');
    }
}

if (!function_exists('pms_ota_ical_date_add_days')) {
    function pms_ota_ical_date_add_days($date, $days)
    {
        $base = trim((string)$date);
        if ($base === '') {
            return '';
        }
        try {
            $dt = new DateTime($base);
            $dt->modify(((int)$days >= 0 ? '+' : '') . (int)$days . ' day');
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('pms_ota_ical_fetch_feed_by_token')) {
    function pms_ota_ical_fetch_feed_by_token(PDO $pdo, $token)
    {
        $token = trim((string)$token);
        if ($token === '') {
            return null;
        }
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_feed')) {
            return null;
        }
        $feedCols = pms_ota_ical_feed_columns($pdo);
        $hasOtaAccount = isset($feedCols['id_ota_account']) && pms_ota_ical_table_exists($pdo, 'ota_account');
        $hasFeedPlatform = isset($feedCols['platform']);
        $hasFeedTimezone = isset($feedCols['timezone']);
        $blocksCol = pms_ota_ical_feed_blocks_column_name($pdo);

        $select = array(
            'f.id_ota_ical_feed',
            'f.id_company',
            'f.id_property',
            'f.id_room',
            'f.id_category',
            'COALESCE(f.feed_name, \'\') AS feed_name',
            'COALESCE(f.import_url, \'\') AS import_url',
            'COALESCE(f.import_enabled, 0) AS import_enabled',
            'COALESCE(f.import_ignore_our_uids, 1) AS import_ignore_our_uids',
            'COALESCE(f.export_enabled, 0) AS export_enabled',
            'COALESCE(f.export_token, \'\') AS export_token',
            'COALESCE(f.export_summary_mode, \'reserved\') AS export_summary_mode',
            'COALESCE(f.export_include_reservations, 1) AS export_include_reservations',
            'COALESCE(f.sync_interval_minutes, 30) AS sync_interval_minutes',
            'f.last_sync_at',
            'f.last_success_at',
            'f.last_error',
            'f.http_etag',
            'f.http_last_modified',
            'COALESCE(f.is_active, 1) AS is_active',
            'f.deleted_at',
            'f.created_at',
            'f.updated_at',
            'p.code AS property_code',
            'p.name AS property_name'
        );

        if ($blocksCol !== null) {
            $select[] = 'COALESCE(f.' . $blocksCol . ', 1) AS export_include_room_blocks';
        } else {
            $select[] = '1 AS export_include_room_blocks';
        }
        if ($hasOtaAccount) {
            $select[] = 'f.id_ota_account';
            $select[] = 'COALESCE(oa.platform, ' . ($hasFeedPlatform ? 'f.platform' : '\'other\'') . ', \'other\') AS platform';
            $select[] = 'COALESCE(oa.timezone, ' . ($hasFeedTimezone ? 'f.timezone' : '\'America/Mexico_City\'') . ', \'America/Mexico_City\') AS timezone';
            $select[] = 'oa.ota_name';
        } else {
            $select[] = ($hasFeedPlatform ? 'COALESCE(f.platform, \'other\')' : '\'other\'') . ' AS platform';
            $select[] = ($hasFeedTimezone ? 'COALESCE(f.timezone, \'America/Mexico_City\')' : '\'America/Mexico_City\'') . ' AS timezone';
            $select[] = 'NULL AS id_ota_account';
            $select[] = 'NULL AS ota_name';
        }

        $sql = 'SELECT ' . implode(",\n       ", $select) . "\n                FROM ota_ical_feed f\n                JOIN property p\n                  ON p.id_property = f.id_property\n                 AND p.deleted_at IS NULL";
        if ($hasOtaAccount) {
            $sql .= "\n                LEFT JOIN ota_account oa\n                  ON oa.id_ota_account = f.id_ota_account\n                 AND oa.deleted_at IS NULL";
        }
        $sql .= "\n                WHERE f.export_token = ?\n                  AND f.deleted_at IS NULL\n                  AND COALESCE(f.is_active, 1) = 1\n                  AND COALESCE(f.export_enabled, 0) = 1\n                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($token));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}

if (!function_exists('pms_ota_ical_fetch_feed_by_id')) {
    function pms_ota_ical_fetch_feed_by_id(PDO $pdo, $companyId, $feedId)
    {
        $companyId = (int)$companyId;
        $feedId = (int)$feedId;
        if ($companyId <= 0 || $feedId <= 0) {
            return null;
        }
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_feed')) {
            return null;
        }
        $feedCols = pms_ota_ical_feed_columns($pdo);
        $hasOtaAccount = isset($feedCols['id_ota_account']) && pms_ota_ical_table_exists($pdo, 'ota_account');
        $hasFeedPlatform = isset($feedCols['platform']);
        $hasFeedTimezone = isset($feedCols['timezone']);
        $blocksCol = pms_ota_ical_feed_blocks_column_name($pdo);

        $select = array(
            'f.*',
            'p.code AS property_code',
            'p.name AS property_name'
        );
        if ($blocksCol !== null && $blocksCol !== 'export_include_room_blocks') {
            $select[] = 'COALESCE(f.' . $blocksCol . ', 1) AS export_include_room_blocks';
        }
        if ($hasOtaAccount) {
            $select[] = 'oa.ota_name';
            $select[] = 'oa.platform AS ota_platform';
            $select[] = 'oa.timezone AS ota_timezone';
        } else {
            $select[] = 'NULL AS ota_name';
            $select[] = 'NULL AS ota_platform';
            $select[] = 'NULL AS ota_timezone';
        }
        if (!$hasFeedPlatform) {
            $select[] = 'COALESCE(oa.platform, \'other\') AS platform';
        }
        if (!$hasFeedTimezone) {
            $select[] = 'COALESCE(oa.timezone, \'America/Mexico_City\') AS timezone';
        }

        $sql = 'SELECT ' . implode(",\n       ", $select) . "\n                FROM ota_ical_feed f\n                JOIN property p\n                  ON p.id_property = f.id_property\n                 AND p.deleted_at IS NULL";
        if ($hasOtaAccount) {
            $sql .= "\n                LEFT JOIN ota_account oa\n                  ON oa.id_ota_account = f.id_ota_account\n                 AND oa.deleted_at IS NULL";
        }
        $sql .= "\n                WHERE f.id_ota_ical_feed = ?\n                  AND f.id_company = ?\n                  AND f.deleted_at IS NULL\n                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($feedId, $companyId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}

if (!function_exists('pms_ota_ical_list_feeds')) {
    function pms_ota_ical_list_feeds(PDO $pdo, $companyId, $propertyId = 0)
    {
        $companyId = (int)$companyId;
        $propertyId = (int)$propertyId;
        if ($companyId <= 0) {
            return array();
        }
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_feed')) {
            return array();
        }

        $feedCols = pms_ota_ical_feed_columns($pdo);
        $hasOtaAccount = isset($feedCols['id_ota_account']) && pms_ota_ical_table_exists($pdo, 'ota_account');
        $hasFeedPlatform = isset($feedCols['platform']);
        $hasFeedTimezone = isset($feedCols['timezone']);
        $blocksCol = pms_ota_ical_feed_blocks_column_name($pdo);

        $select = array(
            'f.id_ota_ical_feed',
            'f.id_company',
            'f.id_property',
            'f.id_room',
            'f.id_category',
            'COALESCE(f.feed_name, \'\') AS feed_name',
            'COALESCE(f.import_url, \'\') AS import_url',
            'COALESCE(f.import_enabled, 0) AS import_enabled',
            'COALESCE(f.import_ignore_our_uids, 1) AS import_ignore_our_uids',
            'COALESCE(f.export_enabled, 0) AS export_enabled',
            'COALESCE(f.export_token, \'\') AS export_token',
            'COALESCE(f.export_summary_mode, \'reserved\') AS export_summary_mode',
            'COALESCE(f.export_include_reservations, 1) AS export_include_reservations',
            'COALESCE(f.sync_interval_minutes, 30) AS sync_interval_minutes',
            'f.last_sync_at',
            'f.last_success_at',
            'f.last_error',
            'COALESCE(f.is_active, 1) AS is_active',
            'f.created_at',
            'f.updated_at',
            'p.code AS property_code',
            'p.name AS property_name',
            'r.code AS room_code',
            'r.name AS room_name',
            'c.code AS category_code',
            'c.name AS category_name'
        );
        if ($blocksCol !== null) {
            $select[] = 'COALESCE(f.' . $blocksCol . ', 1) AS export_include_room_blocks';
        } else {
            $select[] = '1 AS export_include_room_blocks';
        }
        if ($hasOtaAccount) {
            $select[] = 'f.id_ota_account';
            $select[] = 'oa.ota_name';
            $select[] = 'COALESCE(oa.platform, ' . ($hasFeedPlatform ? 'f.platform' : '\'other\'') . ', \'other\') AS platform';
            $select[] = 'COALESCE(oa.timezone, ' . ($hasFeedTimezone ? 'f.timezone' : '\'America/Mexico_City\'') . ', \'America/Mexico_City\') AS timezone';
        } else {
            $select[] = 'NULL AS id_ota_account';
            $select[] = 'NULL AS ota_name';
            $select[] = ($hasFeedPlatform ? 'COALESCE(f.platform, \'other\')' : '\'other\'') . ' AS platform';
            $select[] = ($hasFeedTimezone ? 'COALESCE(f.timezone, \'America/Mexico_City\')' : '\'America/Mexico_City\'') . ' AS timezone';
        }

        $sql = 'SELECT ' . implode(",\n       ", $select) . "\n                FROM ota_ical_feed f\n                JOIN property p\n                  ON p.id_property = f.id_property\n                 AND p.deleted_at IS NULL\n                LEFT JOIN room r\n                  ON r.id_room = f.id_room\n                LEFT JOIN roomcategory c\n                  ON c.id_category = f.id_category";
        if ($hasOtaAccount) {
            $sql .= "\n                LEFT JOIN ota_account oa\n                  ON oa.id_ota_account = f.id_ota_account\n                 AND oa.deleted_at IS NULL";
        }
        $sql .= "\n                WHERE f.id_company = ?\n                  AND f.deleted_at IS NULL";
        $params = array($companyId);
        if ($propertyId > 0) {
            $sql .= ' AND f.id_property = ?';
            $params[] = $propertyId;
        }
        $sql .= "\n                ORDER BY p.code, COALESCE(r.code, c.code), f.id_ota_ical_feed";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pms_ota_ical_normalize_scope')) {
    function pms_ota_ical_normalize_scope($scopeRaw, $roomId, $categoryId)
    {
        $scope = strtolower(trim((string)$scopeRaw));
        $room = (int)$roomId;
        $category = (int)$categoryId;
        if ($scope === 'category' || ($scope === '' && $category > 0 && $room <= 0)) {
            return 'category';
        }
        return 'room';
    }
}

if (!function_exists('pms_ota_ical_upsert_feed')) {
    function pms_ota_ical_upsert_feed(PDO $pdo, $companyId, $actorUserId, array $payload)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            throw new Exception('Empresa invalida para iCal.');
        }
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_feed')) {
            throw new Exception('La tabla ota_ical_feed no existe en esta base de datos.');
        }

        $feedCols = pms_ota_ical_feed_columns($pdo);
        $feedId = isset($payload['id_ota_ical_feed']) ? (int)$payload['id_ota_ical_feed'] : 0;
        $propertyId = isset($payload['id_property']) ? (int)$payload['id_property'] : 0;
        $roomId = isset($payload['id_room']) ? (int)$payload['id_room'] : 0;
        $categoryId = isset($payload['id_category']) ? (int)$payload['id_category'] : 0;
        $scopeType = pms_ota_ical_normalize_scope(isset($payload['scope_type']) ? $payload['scope_type'] : '', $roomId, $categoryId);
        $feedName = trim((string)(isset($payload['feed_name']) ? $payload['feed_name'] : ''));
        $importUrl = trim((string)(isset($payload['import_url']) ? $payload['import_url'] : ''));
        $importEnabled = !empty($payload['import_enabled']) ? 1 : 0;
        $ignoreOurUids = !empty($payload['import_ignore_our_uids']) ? 1 : 0;
        $exportEnabled = !empty($payload['export_enabled']) ? 1 : 0;
        $summaryMode = strtolower(trim((string)(isset($payload['export_summary_mode']) ? $payload['export_summary_mode'] : 'reserved')));
        $includeReservations = !empty($payload['export_include_reservations']) ? 1 : 0;
        $includeBlocks = !empty($payload['export_include_room_blocks']) ? 1 : 0;
        $syncIntervalMinutes = isset($payload['sync_interval_minutes']) ? (int)$payload['sync_interval_minutes'] : 30;
        $isActive = !empty($payload['is_active']) ? 1 : 0;
        $idOtaAccount = isset($payload['id_ota_account']) ? (int)$payload['id_ota_account'] : 0;
        $platformRaw = strtolower(trim((string)(isset($payload['platform']) ? $payload['platform'] : 'otro')));
        $timezoneRaw = trim((string)(isset($payload['timezone']) ? $payload['timezone'] : ''));

        $summaryModesAllowed = pms_ota_ical_summary_modes($pdo);
        if (!$summaryModesAllowed) {
            $summaryModesAllowed = array('reserved', 'reservation_code', 'guest_name');
        }
        if (!in_array($summaryMode, $summaryModesAllowed, true)) {
            $summaryMode = $summaryModesAllowed[0];
        }
        if ($syncIntervalMinutes < 5) {
            $syncIntervalMinutes = 5;
        } elseif ($syncIntervalMinutes > 1440) {
            $syncIntervalMinutes = 1440;
        }

        if ($propertyId <= 0) {
            throw new Exception('Selecciona una propiedad para el feed iCal.');
        }

        $stmtProperty = $pdo->prepare(
            'SELECT id_property
             FROM property
             WHERE id_property = ?
               AND id_company = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmtProperty->execute(array($propertyId, $companyId));
        if (!$stmtProperty->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('La propiedad seleccionada no pertenece a la empresa actual.');
        }

        if ($scopeType === 'room') {
            if ($roomId <= 0) {
                throw new Exception('Selecciona una habitacion para el feed iCal.');
            }
            $categoryId = 0;
            $stmtRoom = $pdo->prepare(
                'SELECT id_room
                 FROM room
                 WHERE id_room = ?
                   AND id_property = ?
                   AND deleted_at IS NULL
                   AND is_active = 1
                 LIMIT 1'
            );
            $stmtRoom->execute(array($roomId, $propertyId));
            if (!$stmtRoom->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception('La habitacion seleccionada no es valida para la propiedad.');
            }
        } else {
            if ($categoryId <= 0) {
                throw new Exception('Selecciona una categoria para el feed iCal.');
            }
            $roomId = 0;
            $stmtCategory = $pdo->prepare(
                'SELECT id_category
                 FROM roomcategory
                 WHERE id_category = ?
                   AND id_property = ?
                   AND deleted_at IS NULL
                 LIMIT 1'
            );
            $stmtCategory->execute(array($categoryId, $propertyId));
            if (!$stmtCategory->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception('La categoria seleccionada no es valida para la propiedad.');
            }
        }

        if ($importEnabled && $importUrl === '') {
            throw new Exception('Import URL es obligatorio cuando el import esta activo.');
        }
        if ($importUrl !== '' && !preg_match('/^https?:\/\//i', $importUrl)) {
            throw new Exception('Import URL debe iniciar con http:// o https://');
        }

        if (isset($feedCols['id_ota_account']) && $idOtaAccount > 0) {
            $stmtOta = $pdo->prepare(
                'SELECT id_ota_account, platform, timezone
                 FROM ota_account
                 WHERE id_ota_account = ?
                   AND id_company = ?
                   AND id_property = ?
                   AND deleted_at IS NULL
                 LIMIT 1'
            );
            $stmtOta->execute(array($idOtaAccount, $companyId, $propertyId));
            $otaRow = $stmtOta->fetch(PDO::FETCH_ASSOC);
            if (!$otaRow) {
                throw new Exception('La cuenta OTA seleccionada no coincide con la propiedad.');
            }
            $platformRaw = strtolower(trim((string)(isset($otaRow['platform']) ? $otaRow['platform'] : $platformRaw)));
            if ($timezoneRaw === '') {
                $timezoneRaw = trim((string)(isset($otaRow['timezone']) ? $otaRow['timezone'] : ''));
            }
        }

        if ($platformRaw === 'abb') {
            $platformRaw = 'airbnb';
        }
        if (!in_array($platformRaw, array('airbnb', 'booking', 'expedia', 'vrbo', 'otro', 'other'), true)) {
            $platformRaw = 'otro';
        }
        if ($platformRaw === 'other') {
            $platformRaw = 'otro';
        }

        $timezoneFinal = $timezoneRaw !== '' ? $timezoneRaw : 'America/Mexico_City';
        if (!in_array($timezoneFinal, timezone_identifiers_list(), true)) {
            $timezoneFinal = 'America/Mexico_City';
        }

        if ($feedName === '') {
            $feedName = ($scopeType === 'room' ? 'Habitacion' : 'Categoria') . ' ' . ($scopeType === 'room' ? $roomId : $categoryId);
        }

        $blocksCol = pms_ota_ical_feed_blocks_column_name($pdo);
        $exportToken = trim((string)(isset($payload['export_token']) ? $payload['export_token'] : ''));
        if ($exportEnabled && $exportToken === '') {
            $exportToken = pms_ota_ical_generate_token(32);
        }

        $fields = array(
            'id_company' => $companyId,
            'id_property' => $propertyId,
            'id_room' => $scopeType === 'room' ? $roomId : null,
            'id_category' => $scopeType === 'category' ? $categoryId : null,
            'feed_name' => $feedName,
            'import_url' => ($importUrl !== '' ? $importUrl : null),
            'import_enabled' => $importEnabled,
            'import_ignore_our_uids' => $ignoreOurUids,
            'export_enabled' => $exportEnabled,
            'export_token' => ($exportToken !== '' ? $exportToken : null),
            'export_summary_mode' => $summaryMode,
            'export_include_reservations' => $includeReservations,
            'sync_interval_minutes' => $syncIntervalMinutes,
            'is_active' => $isActive
        );

        if ($blocksCol !== null) {
            $fields[$blocksCol] = $includeBlocks;
        }
        if (isset($feedCols['id_ota_account'])) {
            $fields['id_ota_account'] = $idOtaAccount > 0 ? $idOtaAccount : null;
        }
        if (isset($feedCols['platform'])) {
            $fields['platform'] = $platformRaw;
        }
        if (isset($feedCols['timezone'])) {
            $fields['timezone'] = $timezoneFinal;
        }
        if (isset($feedCols['updated_by'])) {
            $fields['updated_by'] = $actorUserId;
        }

        if ($feedId > 0) {
            $stmtOwned = $pdo->prepare(
                'SELECT id_ota_ical_feed
                 FROM ota_ical_feed
                 WHERE id_ota_ical_feed = ?
                   AND id_company = ?
                   AND deleted_at IS NULL
                 LIMIT 1'
            );
            $stmtOwned->execute(array($feedId, $companyId));
            if (!$stmtOwned->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception('Feed iCal no encontrado para esta empresa.');
            }

            $setClauses = array();
            $params = array();
            foreach ($fields as $column => $value) {
                if (!isset($feedCols[strtolower($column)])) {
                    continue;
                }
                $setClauses[] = $column . ' = ?';
                $params[] = $value;
            }
            $setClauses[] = 'updated_at = NOW()';
            $params[] = $feedId;
            $params[] = $companyId;

            $sql = 'UPDATE ota_ical_feed
                       SET ' . implode(', ', $setClauses) . '
                     WHERE id_ota_ical_feed = ?
                       AND id_company = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            if (isset($feedCols['created_by'])) {
                $fields['created_by'] = $actorUserId;
            }
            if (isset($feedCols['updated_by'])) {
                $fields['updated_by'] = $actorUserId;
            }
            $columns = array();
            $placeholders = array();
            $params = array();
            foreach ($fields as $column => $value) {
                if (!isset($feedCols[strtolower($column)])) {
                    continue;
                }
                $columns[] = $column;
                $placeholders[] = '?';
                $params[] = $value;
            }
            $sql = 'INSERT INTO ota_ical_feed (' . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $feedId = (int)$pdo->lastInsertId();
        }

        return pms_ota_ical_fetch_feed_by_id($pdo, $companyId, $feedId);
    }
}

if (!function_exists('pms_ota_ical_delete_feed')) {
    function pms_ota_ical_delete_feed(PDO $pdo, $companyId, $feedId, $actorUserId)
    {
        $companyId = (int)$companyId;
        $feedId = (int)$feedId;
        if ($companyId <= 0 || $feedId <= 0) {
            throw new Exception('Feed invalido para eliminar.');
        }
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_feed')) {
            throw new Exception('La tabla ota_ical_feed no existe en esta base de datos.');
        }
        $feedCols = pms_ota_ical_feed_columns($pdo);
        $set = array(
            'is_active = 0',
            'deleted_at = NOW()',
            'updated_at = NOW()'
        );
        $params = array();
        if (isset($feedCols['updated_by'])) {
            $set[] = 'updated_by = ?';
            $params[] = $actorUserId;
        }
        $params[] = $feedId;
        $params[] = $companyId;
        $sql = 'UPDATE ota_ical_feed
                   SET ' . implode(', ', $set) . '
                 WHERE id_ota_ical_feed = ?
                   AND id_company = ?
                   AND deleted_at IS NULL';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('pms_ota_ical_export_events')) {
    function pms_ota_ical_export_events(PDO $pdo, array $feedRow)
    {
        $propertyId = isset($feedRow['id_property']) ? (int)$feedRow['id_property'] : 0;
        $roomId = isset($feedRow['id_room']) ? (int)$feedRow['id_room'] : 0;
        $categoryId = isset($feedRow['id_category']) ? (int)$feedRow['id_category'] : 0;
        $summaryMode = strtolower(trim((string)(isset($feedRow['export_summary_mode']) ? $feedRow['export_summary_mode'] : 'reserved')));
        $includeReservations = !empty($feedRow['export_include_reservations']);
        $includeBlocks = !empty($feedRow['export_include_room_blocks']);
        if ($propertyId <= 0) {
            return array();
        }

        $paramsBase = array($propertyId);
        $scopeSql = '';
        if ($roomId > 0) {
            $scopeSql = ' AND src.id_room = ?';
            $paramsBase[] = $roomId;
        } elseif ($categoryId > 0) {
            $scopeSql = ' AND src.id_room IN (
                             SELECT rr.id_room
                             FROM room rr
                             WHERE rr.id_property = ?
                               AND rr.id_category = ?
                               AND rr.deleted_at IS NULL
                               AND rr.is_active = 1
                           )';
            $paramsBase[] = $propertyId;
            $paramsBase[] = $categoryId;
        }

        $events = array();
        if ($includeReservations) {
            $sql = 'SELECT
                      \'reservation\' AS source_type,
                      r.id_reservation AS source_id,
                      r.id_room,
                      r.check_in_date AS start_date,
                      r.check_out_date AS end_date,
                      r.code AS reservation_code,
                      CONCAT_WS(\' \' , COALESCE(g.names, \'\'), COALESCE(g.last_name, \'\')) AS guest_name,
                      COALESCE(r.notes_internal, r.notes_guest, \'\') AS detail_text,
                      COALESCE(r.updated_at, r.created_at, NOW()) AS updated_at
                    FROM reservation r
                    LEFT JOIN guest g ON g.id_guest = r.id_guest
                    JOIN (
                      SELECT id_room
                      FROM room
                      WHERE id_property = ?
                        AND deleted_at IS NULL
                    ) src ON src.id_room = r.id_room
                    WHERE r.deleted_at IS NULL
                      AND COALESCE(r.is_active, 1) = 1
                      AND COALESCE(r.status, \'confirmado\') NOT IN (\'cancelled\', \'canceled\', \'cancelado\', \'cancelada\')
                      AND r.check_out_date > r.check_in_date'
                    . $scopeSql;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($paramsBase);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $summary = 'Reserved';
                if ($summaryMode === 'reservation_code') {
                    $summary = trim((string)(isset($row['reservation_code']) ? $row['reservation_code'] : ''));
                    if ($summary === '') {
                        $summary = 'Reserved';
                    }
                } elseif ($summaryMode === 'guest_name') {
                    $summary = trim((string)(isset($row['guest_name']) ? $row['guest_name'] : ''));
                    if ($summary === '') {
                        $summary = 'Reserved';
                    }
                } elseif ($summaryMode === 'detailed') {
                    $summary = trim((string)(isset($row['reservation_code']) ? $row['reservation_code'] : ''));
                    if ($summary === '') {
                        $summary = 'Reserved';
                    }
                }
                $events[] = array(
                    'source_type' => 'reservation',
                    'source_id' => isset($row['source_id']) ? (int)$row['source_id'] : 0,
                    'id_room' => isset($row['id_room']) ? (int)$row['id_room'] : 0,
                    'start_date' => isset($row['start_date']) ? (string)$row['start_date'] : '',
                    'end_date' => isset($row['end_date']) ? (string)$row['end_date'] : '',
                    'summary' => $summary,
                    'description' => trim((string)(isset($row['detail_text']) ? $row['detail_text'] : '')),
                    'updated_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : '',
                    'uid' => 'vlv-feed-' . (int)$feedRow['id_ota_ical_feed'] . '-reservation-' . (int)(isset($row['source_id']) ? $row['source_id'] : 0) . '@vivelavibe-pms'
                );
            }
        }

        if ($includeBlocks) {
            $sql = 'SELECT
                      \'room_block\' AS source_type,
                      rb.id_room_block AS source_id,
                      rb.id_room,
                      rb.start_date AS start_date,
                      DATE_ADD(rb.end_date, INTERVAL 1 DAY) AS end_date,
                      rb.code AS block_code,
                      COALESCE(rb.description, \'\') AS detail_text,
                      COALESCE(rb.updated_at, rb.created_at, NOW()) AS updated_at
                    FROM room_block rb
                    JOIN (
                      SELECT id_room
                      FROM room
                      WHERE id_property = ?
                        AND deleted_at IS NULL
                    ) src ON src.id_room = rb.id_room
                    WHERE rb.deleted_at IS NULL
                      AND COALESCE(rb.is_active, 1) = 1
                      AND rb.end_date >= rb.start_date'
                    . $scopeSql;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($paramsBase);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $summary = 'Reserved';
                if ($summaryMode === 'reservation_code' || $summaryMode === 'detailed') {
                    $summary = trim((string)(isset($row['block_code']) ? $row['block_code'] : ''));
                    if ($summary === '') {
                        $summary = 'Reserved';
                    }
                }
                $events[] = array(
                    'source_type' => 'room_block',
                    'source_id' => isset($row['source_id']) ? (int)$row['source_id'] : 0,
                    'id_room' => isset($row['id_room']) ? (int)$row['id_room'] : 0,
                    'start_date' => isset($row['start_date']) ? (string)$row['start_date'] : '',
                    'end_date' => isset($row['end_date']) ? (string)$row['end_date'] : '',
                    'summary' => $summary,
                    'description' => trim((string)(isset($row['detail_text']) ? $row['detail_text'] : '')),
                    'updated_at' => isset($row['updated_at']) ? (string)$row['updated_at'] : '',
                    'uid' => 'vlv-feed-' . (int)$feedRow['id_ota_ical_feed'] . '-room-block-' . (int)(isset($row['source_id']) ? $row['source_id'] : 0) . '@vivelavibe-pms'
                );
            }
        }

        usort($events, function ($a, $b) {
            $sa = isset($a['start_date']) ? (string)$a['start_date'] : '';
            $sb = isset($b['start_date']) ? (string)$b['start_date'] : '';
            if ($sa === $sb) {
                $ua = isset($a['uid']) ? (string)$a['uid'] : '';
                $ub = isset($b['uid']) ? (string)$b['uid'] : '';
                return strcmp($ua, $ub);
            }
            return strcmp($sa, $sb);
        });

        return $events;
    }
}

if (!function_exists('pms_ota_ical_render_export')) {
    function pms_ota_ical_render_export(PDO $pdo, array $feedRow)
    {
        $calendarName = trim((string)(isset($feedRow['feed_name']) ? $feedRow['feed_name'] : ''));
        if ($calendarName === '') {
            $calendarName = 'VLV PMS Feed';
        }
        $timezone = pms_ota_ical_feed_timezone($feedRow);
        $events = pms_ota_ical_export_events($pdo, $feedRow);

        $lines = array(
            'BEGIN:VCALENDAR',
            'PRODID:-//Vive la Vibe//PMS OTA iCal//ES',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . pms_ota_ical_escape_text($calendarName),
            'X-WR-TIMEZONE:' . pms_ota_ical_escape_text($timezone)
        );

        foreach ($events as $event) {
            $startDate = isset($event['start_date']) ? (string)$event['start_date'] : '';
            $endDate = isset($event['end_date']) ? (string)$event['end_date'] : '';
            if ($startDate === '' || $endDate === '') {
                continue;
            }
            $dtStart = str_replace('-', '', $startDate);
            $dtEnd = str_replace('-', '', $endDate);
            if ($dtStart === '' || $dtEnd === '') {
                continue;
            }
            $summary = isset($event['summary']) ? (string)$event['summary'] : 'Reserved';
            $description = isset($event['description']) ? (string)$event['description'] : '';
            $updatedAt = isset($event['updated_at']) ? (string)$event['updated_at'] : '';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . pms_ota_ical_escape_text(isset($event['uid']) ? (string)$event['uid'] : ('uid-' . md5(json_encode($event))));
            $lines[] = 'DTSTAMP:' . pms_ota_ical_dt_utc($updatedAt);
            $lines[] = 'LAST-MODIFIED:' . pms_ota_ical_dt_utc($updatedAt);
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtStart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtEnd;
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'SUMMARY:' . pms_ota_ical_escape_text($summary);
            if ($description !== '') {
                $lines[] = 'DESCRIPTION:' . pms_ota_ical_escape_text($description);
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $ics = '';
        foreach ($lines as $line) {
            $ics .= pms_ota_ical_fold_line($line);
        }
        return $ics;
    }
}

if (!function_exists('pms_ota_ical_http_fetch')) {
    function pms_ota_ical_http_fetch($url, $etag = '', $lastModified = '')
    {
        $target = trim((string)$url);
        if ($target === '') {
            throw new Exception('URL de importacion vacia.');
        }

        $headers = array(
            'User-Agent: ViveLaVibe-PMS-iCal/1.0',
            'Accept: text/calendar,text/plain,*/*'
        );
        $etag = trim((string)$etag);
        $lastModified = trim((string)$lastModified);
        if ($etag !== '') {
            $headers[] = 'If-None-Match: ' . $etag;
        }
        if ($lastModified !== '') {
            $headers[] = 'If-Modified-Since: ' . $lastModified;
        }

        $responseHeaders = array();
        $statusCode = 0;
        $body = '';
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($target);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT => 35,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => function ($chHandle, $headerLine) use (&$responseHeaders, &$statusCode) {
                    $trimmed = trim((string)$headerLine);
                    if ($trimmed === '') {
                        return strlen($headerLine);
                    }
                    if (stripos($trimmed, 'HTTP/') === 0) {
                        $parts = explode(' ', $trimmed);
                        if (isset($parts[1])) {
                            $statusCode = (int)$parts[1];
                        }
                    } else {
                        $parts = explode(':', $trimmed, 2);
                        if (count($parts) === 2) {
                            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                        }
                    }
                    return strlen($headerLine);
                }
            ));
            $body = (string)curl_exec($ch);
            if ($body === '' && curl_errno($ch)) {
                $error = (string)curl_error($ch);
            }
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($statusCode <= 0) {
                $statusCode = $httpCode;
            }
            curl_close($ch);
        } else {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 35,
                    'ignore_errors' => true
                )
            ));
            $body = (string)@file_get_contents($target, false, $context);
            if ($body === '') {
                $lastError = error_get_last();
                if ($lastError && isset($lastError['message'])) {
                    $error = (string)$lastError['message'];
                }
            }
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $line) {
                    $trimmed = trim((string)$line);
                    if ($trimmed === '') {
                        continue;
                    }
                    if (stripos($trimmed, 'HTTP/') === 0) {
                        $parts = explode(' ', $trimmed);
                        if (isset($parts[1])) {
                            $statusCode = (int)$parts[1];
                        }
                    } else {
                        $parts = explode(':', $trimmed, 2);
                        if (count($parts) === 2) {
                            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                        }
                    }
                }
            }
        }

        return array(
            'status_code' => $statusCode,
            'body' => $body,
            'headers' => $responseHeaders,
            'etag' => isset($responseHeaders['etag']) ? (string)$responseHeaders['etag'] : '',
            'last_modified' => isset($responseHeaders['last-modified']) ? (string)$responseHeaders['last-modified'] : '',
            'error' => $error
        );
    }
}

if (!function_exists('pms_ota_ical_parse_datetime_to_date')) {
    function pms_ota_ical_parse_datetime_to_date($rawValue, $valueType = '', $tzid = '', $fallbackTimezone = 'UTC')
    {
        $value = trim((string)$rawValue);
        if ($value === '') {
            return '';
        }
        $type = strtoupper(trim((string)$valueType));
        $timezone = trim((string)$fallbackTimezone);
        if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'UTC';
        }
        if (strtoupper($type) === 'DATE' || preg_match('/^\d{8}$/', $value)) {
            $dt = DateTime::createFromFormat('Ymd', substr($value, 0, 8), new DateTimeZone($timezone));
            return $dt ? $dt->format('Y-m-d') : '';
        }

        $inputTz = $timezone;
        $tzid = trim((string)$tzid);
        if ($tzid !== '' && in_array($tzid, timezone_identifiers_list(), true)) {
            $inputTz = $tzid;
        }

        $candidates = array(
            array('Ymd\\THis\\Z', 'UTC'),
            array('Ymd\\THis', $inputTz),
            array('Ymd\\THi\\Z', 'UTC'),
            array('Ymd\\THi', $inputTz),
            array(DateTime::ATOM, $inputTz),
            array('Y-m-d H:i:s', $inputTz),
            array('Y-m-d', $inputTz)
        );

        foreach ($candidates as $candidate) {
            $fmt = $candidate[0];
            $tz = $candidate[1];
            try {
                $dt = DateTime::createFromFormat($fmt, $value, new DateTimeZone($tz));
                if ($dt instanceof DateTime) {
                    $dt->setTimezone(new DateTimeZone($timezone));
                    return $dt->format('Y-m-d');
                }
            } catch (Exception $e) {
            }
        }

        try {
            $dt = new DateTime($value, new DateTimeZone($inputTz));
            $dt->setTimezone(new DateTimeZone($timezone));
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('pms_ota_ical_parse_events')) {
    function pms_ota_ical_parse_events($icsText, $fallbackTimezone = 'UTC')
    {
        $raw = (string)$icsText;
        $rawLines = preg_split('/\r\n|\n|\r/', $raw);
        $lines = array();
        foreach ((array)$rawLines as $line) {
            if ($line === '') {
                continue;
            }
            $first = substr($line, 0, 1);
            if (($first === ' ' || $first === "\t") && !empty($lines)) {
                $lines[count($lines) - 1] .= substr($line, 1);
                continue;
            }
            $lines[] = $line;
        }

        $events = array();
        $inEvent = false;
        $props = array();
        $rawEventLines = array();
        foreach ($lines as $line) {
            $upper = strtoupper(trim((string)$line));
            if ($upper === 'BEGIN:VEVENT') {
                $inEvent = true;
                $props = array();
                $rawEventLines = array($line);
                continue;
            }
            if ($upper === 'END:VEVENT') {
                if ($inEvent) {
                    $rawEventLines[] = $line;
                    $uid = trim((string)(isset($props['UID']['value']) ? $props['UID']['value'] : ''));
                    if ($uid === '') {
                        $uid = 'uid-' . md5(implode("\n", $rawEventLines));
                    }

                    $dtStart = isset($props['DTSTART']) ? $props['DTSTART'] : array('value' => '', 'params' => array());
                    $dtEnd = isset($props['DTEND']) ? $props['DTEND'] : array('value' => '', 'params' => array());
                    $startDate = pms_ota_ical_parse_datetime_to_date(
                        isset($dtStart['value']) ? $dtStart['value'] : '',
                        isset($dtStart['params']['VALUE']) ? $dtStart['params']['VALUE'] : '',
                        isset($dtStart['params']['TZID']) ? $dtStart['params']['TZID'] : '',
                        $fallbackTimezone
                    );
                    $endDate = pms_ota_ical_parse_datetime_to_date(
                        isset($dtEnd['value']) ? $dtEnd['value'] : '',
                        isset($dtEnd['params']['VALUE']) ? $dtEnd['params']['VALUE'] : '',
                        isset($dtEnd['params']['TZID']) ? $dtEnd['params']['TZID'] : '',
                        $fallbackTimezone
                    );
                    if ($startDate !== '' && $endDate === '') {
                        $endDate = pms_ota_ical_date_add_days($startDate, 1);
                    }
                    if ($startDate !== '' && $endDate !== '' && $endDate <= $startDate) {
                        $endDate = pms_ota_ical_date_add_days($startDate, 1);
                    }

                    $status = strtoupper(trim((string)(isset($props['STATUS']['value']) ? $props['STATUS']['value'] : '')));
                    $summary = pms_ota_ical_unescape_text(isset($props['SUMMARY']['value']) ? $props['SUMMARY']['value'] : '');
                    $description = pms_ota_ical_unescape_text(isset($props['DESCRIPTION']['value']) ? $props['DESCRIPTION']['value'] : '');
                    $sequence = isset($props['SEQUENCE']['value']) ? (int)$props['SEQUENCE']['value'] : 0;
                    $dtstampRaw = isset($props['DTSTAMP']['value']) ? trim((string)$props['DTSTAMP']['value']) : '';
                    $lastModifiedRaw = isset($props['LAST-MODIFIED']['value']) ? trim((string)$props['LAST-MODIFIED']['value']) : '';

                    $rawEvent = implode("\r\n", $rawEventLines);
                    $events[] = array(
                        'uid' => $uid,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $status,
                        'summary' => $summary,
                        'description' => $description,
                        'sequence' => $sequence,
                        'dtstamp_raw' => $dtstampRaw,
                        'last_modified_raw' => $lastModifiedRaw,
                        'raw_vevent' => $rawEvent,
                        'hash_sha256' => hash('sha256', $rawEvent),
                        'is_cancelled' => in_array($status, array('CANCELLED', 'CANCELED'), true)
                    );
                }
                $inEvent = false;
                $props = array();
                $rawEventLines = array();
                continue;
            }
            if (!$inEvent) {
                continue;
            }
            $rawEventLines[] = $line;
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            $left = (string)$parts[0];
            $value = (string)$parts[1];
            $segments = explode(';', $left);
            $propName = strtoupper(trim((string)array_shift($segments)));
            if ($propName === '') {
                continue;
            }
            $params = array();
            foreach ($segments as $segment) {
                $kv = explode('=', $segment, 2);
                if (count($kv) !== 2) {
                    continue;
                }
                $k = strtoupper(trim((string)$kv[0]));
                $v = trim((string)$kv[1], "\" \t\r\n");
                if ($k !== '') {
                    $params[$k] = $v;
                }
            }
            $props[$propName] = array(
                'value' => $value,
                'params' => $params
            );
        }

        return $events;
    }
}

if (!function_exists('pms_ota_ical_room_scope_validation')) {
    function pms_ota_ical_room_scope_validation(PDO $pdo, array $feedRow)
    {
        $propertyId = isset($feedRow['id_property']) ? (int)$feedRow['id_property'] : 0;
        $roomId = isset($feedRow['id_room']) ? (int)$feedRow['id_room'] : 0;
        if ($propertyId <= 0) {
            throw new Exception('Feed iCal sin propiedad.');
        }
        if ($roomId > 0) {
            return array('id_room' => $roomId, 'id_property' => $propertyId);
        }
        $categoryId = isset($feedRow['id_category']) ? (int)$feedRow['id_category'] : 0;
        if ($categoryId <= 0) {
            throw new Exception('Feed iCal sin scope de habitacion/categoria.');
        }

        $stmt = $pdo->prepare(
            'SELECT id_room
             FROM room
             WHERE id_property = ?
               AND id_category = ?
               AND deleted_at IS NULL
               AND is_active = 1
             ORDER BY order_index, id_room
             LIMIT 1'
        );
        $stmt->execute(array($propertyId, $categoryId));
        $resolvedRoomId = (int)$stmt->fetchColumn();
        if ($resolvedRoomId <= 0) {
            throw new Exception('No hay habitaciones activas en la categoria del feed iCal.');
        }

        return array('id_room' => $resolvedRoomId, 'id_property' => $propertyId);
    }
}

if (!function_exists('pms_ota_ical_overlap_counts')) {
    function pms_ota_ical_overlap_counts(PDO $pdo, $roomId, $startDate, $endDate, $excludeBlockId = 0)
    {
        $roomId = (int)$roomId;
        $excludeBlockId = (int)$excludeBlockId;
        $start = (string)$startDate;
        $end = (string)$endDate;
        $resCount = 0;
        $blockCount = 0;

        $stmtRes = $pdo->prepare(
            'SELECT COUNT(*)
             FROM reservation
             WHERE id_room = ?
               AND deleted_at IS NULL
               AND COALESCE(is_active, 1) = 1
               AND COALESCE(status, \'confirmado\') NOT IN (\'cancelled\',\'canceled\',\'cancelado\',\'cancelada\')
               AND check_in_date < ?
               AND check_out_date > ?'
        );
        $stmtRes->execute(array($roomId, $end, $start));
        $resCount = (int)$stmtRes->fetchColumn();

        $stmtBlock = $pdo->prepare(
            'SELECT COUNT(*)
             FROM room_block
             WHERE id_room = ?
               AND deleted_at IS NULL
               AND COALESCE(is_active, 1) = 1
               AND id_room_block <> ?
               AND start_date < ?
               AND DATE_ADD(end_date, INTERVAL 1 DAY) > ?'
        );
        $stmtBlock->execute(array($roomId, $excludeBlockId, $end, $start));
        $blockCount = (int)$stmtBlock->fetchColumn();

        return array(
            'reservation_overlap' => $resCount,
            'block_overlap' => $blockCount
        );
    }
}

if (!function_exists('pms_ota_ical_soft_delete_room_block')) {
    function pms_ota_ical_soft_delete_room_block(PDO $pdo, $blockId)
    {
        $blockId = (int)$blockId;
        if ($blockId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE room_block
             SET is_active = 0,
                 deleted_at = NOW(),
                 updated_at = NOW()
             WHERE id_room_block = ?
               AND deleted_at IS NULL'
        );
        $stmt->execute(array($blockId));
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('pms_ota_ical_sync_feed')) {
    function pms_ota_ical_sync_feed(PDO $pdo, array $feedRow, $actorUserId = null)
    {
        $feedId = isset($feedRow['id_ota_ical_feed']) ? (int)$feedRow['id_ota_ical_feed'] : 0;
        if ($feedId <= 0) {
            throw new Exception('Feed iCal invalido.');
        }
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_event') || !pms_ota_ical_table_exists($pdo, 'ota_ical_event_map')) {
            throw new Exception('Faltan tablas ota_ical_event / ota_ical_event_map para sincronizar iCal.');
        }

        $importEnabled = !empty($feedRow['import_enabled']);
        $importUrl = trim((string)(isset($feedRow['import_url']) ? $feedRow['import_url'] : ''));
        if (!$importEnabled) {
            throw new Exception('El import iCal no esta habilitado para este feed.');
        }
        if ($importUrl === '') {
            throw new Exception('El feed iCal no tiene Import URL configurada.');
        }

        $timezone = pms_ota_ical_feed_timezone($feedRow);
        $etagOld = isset($feedRow['http_etag']) ? (string)$feedRow['http_etag'] : '';
        $lastModifiedOld = isset($feedRow['http_last_modified']) ? (string)$feedRow['http_last_modified'] : '';
        $http = pms_ota_ical_http_fetch($importUrl, $etagOld, $lastModifiedOld);
        $statusCode = isset($http['status_code']) ? (int)$http['status_code'] : 0;
        if ($statusCode === 304) {
            $stmt = $pdo->prepare(
                'UPDATE ota_ical_feed
                 SET last_sync_at = NOW(),
                     last_success_at = NOW(),
                     last_error = NULL,
                     updated_at = NOW()
                 WHERE id_ota_ical_feed = ?'
            );
            $stmt->execute(array($feedId));
            return array(
                'ok' => true,
                'status' => 'not_modified',
                'events_total' => 0,
                'events_created' => 0,
                'events_updated' => 0,
                'events_deactivated' => 0,
                'blocks_created' => 0,
                'blocks_updated' => 0,
                'blocks_deleted' => 0,
                'warnings' => array()
            );
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            $err = trim((string)(isset($http['error']) ? $http['error'] : ''));
            if ($err === '') {
                $err = 'HTTP ' . $statusCode . ' en import iCal.';
            }
            $stmt = $pdo->prepare(
                'UPDATE ota_ical_feed
                 SET last_sync_at = NOW(),
                     last_error = ?,
                     updated_at = NOW()
                 WHERE id_ota_ical_feed = ?'
            );
            $stmt->execute(array($err, $feedId));
            throw new Exception($err);
        }

        $events = pms_ota_ical_parse_events(isset($http['body']) ? (string)$http['body'] : '', $timezone);
        $ignoreOurUids = !empty($feedRow['import_ignore_our_uids']);
        if ($ignoreOurUids && $events) {
            $filtered = array();
            foreach ($events as $eventRow) {
                $uidRaw = strtolower(trim((string)(isset($eventRow['uid']) ? $eventRow['uid'] : '')));
                if ($uidRaw !== '' && (strpos($uidRaw, 'vlv-feed-') === 0 || strpos($uidRaw, '@vivelavibe-pms') !== false)) {
                    continue;
                }
                $filtered[] = $eventRow;
            }
            $events = $filtered;
        }
        $eventCols = pms_ota_ical_event_columns($pdo);
        $warnings = array();
        $stats = array(
            'ok' => true,
            'status' => 'synced',
            'events_total' => count($events),
            'events_created' => 0,
            'events_updated' => 0,
            'events_deactivated' => 0,
            'blocks_created' => 0,
            'blocks_updated' => 0,
            'blocks_deleted' => 0,
            'warnings' => array()
        );

        $scope = pms_ota_ical_room_scope_validation($pdo, $feedRow);
        $targetRoomId = (int)$scope['id_room'];
        $targetPropertyId = (int)$scope['id_property'];

        $pdo->beginTransaction();
        try {
            $existingRows = array();
            $stmtExisting = $pdo->prepare(
                'SELECT id_ota_ical_event, uid, hash_sha256, is_active, deleted_at
                 FROM ota_ical_event
                 WHERE id_ota_ical_feed = ?'
            );
            $stmtExisting->execute(array($feedId));
            foreach ($stmtExisting->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $uid = isset($row['uid']) ? (string)$row['uid'] : '';
                if ($uid !== '') {
                    $existingRows[$uid] = $row;
                }
            }

            $seen = array();
            foreach ($events as $event) {
                $uid = isset($event['uid']) ? trim((string)$event['uid']) : '';
                $startDate = isset($event['start_date']) ? (string)$event['start_date'] : '';
                $endDate = isset($event['end_date']) ? (string)$event['end_date'] : '';
                if ($uid === '' || $startDate === '' || $endDate === '') {
                    $warnings[] = 'Evento omitido por falta de UID o fechas.';
                    continue;
                }
                $seen[$uid] = true;
                $eventHash = isset($event['hash_sha256']) ? (string)$event['hash_sha256'] : hash('sha256', json_encode($event));
                $sequenceNo = isset($event['sequence']) ? (int)$event['sequence'] : 0;
                $dtstampRaw = isset($event['dtstamp_raw']) ? (string)$event['dtstamp_raw'] : '';
                $lastModifiedRaw = isset($event['last_modified_raw']) ? (string)$event['last_modified_raw'] : '';
                $summary = isset($event['summary']) ? (string)$event['summary'] : '';
                $description = isset($event['description']) ? (string)$event['description'] : '';
                $status = isset($event['status']) ? (string)$event['status'] : '';
                $rawVevent = isset($event['raw_vevent']) ? (string)$event['raw_vevent'] : '';

                if (isset($existingRows[$uid])) {
                    $existing = $existingRows[$uid];
                    $idEvent = isset($existing['id_ota_ical_event']) ? (int)$existing['id_ota_ical_event'] : 0;
                    $isChanged = ((string)(isset($existing['hash_sha256']) ? $existing['hash_sha256'] : '') !== $eventHash)
                        || (int)(isset($existing['is_active']) ? $existing['is_active'] : 0) === 0
                        || (isset($existing['deleted_at']) && $existing['deleted_at'] !== null);
                    if ($idEvent > 0) {
                        $setParts = array(
                            'start_date = ?',
                            'end_date = ?',
                            'summary = ?',
                            'description = ?',
                            'status = ?',
                            (isset($eventCols['sequence']) ? 'sequence = ?' : ''),
                            (isset($eventCols['dtstamp']) ? 'dtstamp = ?' : ''),
                            (isset($eventCols['last_modified']) ? 'last_modified = ?' : ''),
                            (isset($eventCols['raw_vevent']) ? 'raw_vevent = ?' : ''),
                            'hash_sha256 = ?',
                            'is_active = 1',
                            'deleted_at = NULL',
                            'updated_at = NOW()'
                        );
                        $setParts = array_values(array_filter($setParts, function ($item) {
                            return $item !== '';
                        }));
                        $params = array($startDate, $endDate, $summary, $description, $status);
                        if (isset($eventCols['sequence'])) {
                            $params[] = $sequenceNo;
                        }
                        if (isset($eventCols['dtstamp'])) {
                            $params[] = ($dtstampRaw !== '' ? pms_ota_ical_parse_datetime_to_date($dtstampRaw, '', '', 'UTC') . ' 00:00:00' : null);
                        }
                        if (isset($eventCols['last_modified'])) {
                            $params[] = ($lastModifiedRaw !== '' ? pms_ota_ical_parse_datetime_to_date($lastModifiedRaw, '', '', 'UTC') . ' 00:00:00' : null);
                        }
                        if (isset($eventCols['raw_vevent'])) {
                            $params[] = $rawVevent;
                        }
                        $params[] = $eventHash;
                        $params[] = $idEvent;
                        $stmtUpdate = $pdo->prepare(
                            'UPDATE ota_ical_event
                             SET ' . implode(', ', $setParts) . '
                             WHERE id_ota_ical_event = ?'
                        );
                        $stmtUpdate->execute($params);
                        if ($isChanged) {
                            $stats['events_updated']++;
                        }
                    }
                } else {
                    $columns = array(
                        'id_ota_ical_feed',
                        'uid',
                        'start_date',
                        'end_date',
                        'summary',
                        'description',
                        'status'
                    );
                    $values = array($feedId, $uid, $startDate, $endDate, $summary, $description, $status);
                    if (isset($eventCols['sequence'])) {
                        $columns[] = 'sequence';
                        $values[] = $sequenceNo;
                    }
                    if (isset($eventCols['dtstamp'])) {
                        $columns[] = 'dtstamp';
                        $values[] = ($dtstampRaw !== '' ? pms_ota_ical_parse_datetime_to_date($dtstampRaw, '', '', 'UTC') . ' 00:00:00' : null);
                    }
                    if (isset($eventCols['last_modified'])) {
                        $columns[] = 'last_modified';
                        $values[] = ($lastModifiedRaw !== '' ? pms_ota_ical_parse_datetime_to_date($lastModifiedRaw, '', '', 'UTC') . ' 00:00:00' : null);
                    }
                    if (isset($eventCols['raw_vevent'])) {
                        $columns[] = 'raw_vevent';
                        $values[] = $rawVevent;
                    }
                    $columns[] = 'hash_sha256';
                    $values[] = $eventHash;
                    $sqlInsert = 'INSERT INTO ota_ical_event (' . implode(', ', $columns) . ')
                                  VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
                    $stmtInsert = $pdo->prepare($sqlInsert);
                    $stmtInsert->execute($values);
                    $stats['events_created']++;
                }
            }

            foreach ($existingRows as $uid => $oldRow) {
                if (isset($seen[$uid])) {
                    continue;
                }
                $stmtDeactivate = $pdo->prepare(
                    'UPDATE ota_ical_event
                     SET is_active = 0,
                         deleted_at = NOW(),
                         updated_at = NOW()
                     WHERE id_ota_ical_feed = ?
                       AND uid = ?
                       AND deleted_at IS NULL'
                );
                $stmtDeactivate->execute(array($feedId, $uid));
                if ($stmtDeactivate->rowCount() > 0) {
                    $stats['events_deactivated']++;
                }
            }

            $stmtMapFind = $pdo->prepare(
                'SELECT id_ota_ical_event_map, entity_type, entity_id, link_status
                 FROM ota_ical_event_map
                 WHERE id_ota_ical_feed = ?
                   AND uid = ?
                 LIMIT 1'
            );
            $stmtMapUpsert = $pdo->prepare(
                'INSERT INTO ota_ical_event_map (
                    id_ota_ical_feed,
                    uid,
                    entity_type,
                    entity_id,
                    link_status,
                    created_at,
                    updated_at
                 ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    entity_type = VALUES(entity_type),
                    entity_id = VALUES(entity_id),
                    link_status = VALUES(link_status),
                    updated_at = NOW()'
            );

            foreach ($events as $event) {
                $uid = isset($event['uid']) ? trim((string)$event['uid']) : '';
                if ($uid === '') {
                    continue;
                }
                $isCancelled = !empty($event['is_cancelled']);
                $startDate = isset($event['start_date']) ? (string)$event['start_date'] : '';
                $endDate = isset($event['end_date']) ? (string)$event['end_date'] : '';
                $endDateInclusive = '';
                if ($startDate !== '' && $endDate !== '') {
                    $endDateInclusive = pms_ota_ical_date_add_days($endDate, -1);
                }
                $summary = trim((string)(isset($event['summary']) ? $event['summary'] : ''));
                $description = trim((string)(isset($event['description']) ? $event['description'] : ''));
                $blockDescription = trim($summary . ($description !== '' ? (' | ' . $description) : ''));
                if ($blockDescription === '') {
                    $blockDescription = 'Importado por iCal';
                }
                if (function_exists('mb_substr')) {
                    $blockDescription = mb_substr($blockDescription, 0, 250);
                } else {
                    $blockDescription = substr($blockDescription, 0, 250);
                }

                $stmtMapFind->execute(array($feedId, $uid));
                $mapRow = $stmtMapFind->fetch(PDO::FETCH_ASSOC);
                $mappedBlockId = ($mapRow && isset($mapRow['entity_type']) && $mapRow['entity_type'] === 'room_block')
                    ? (int)(isset($mapRow['entity_id']) ? $mapRow['entity_id'] : 0)
                    : 0;

                if ($isCancelled || $startDate === '' || $endDate === '' || $endDateInclusive === '' || $endDateInclusive < $startDate) {
                    if ($mappedBlockId > 0 && pms_ota_ical_soft_delete_room_block($pdo, $mappedBlockId)) {
                        $stats['blocks_deleted']++;
                    }
                    if ($mapRow) {
                        $stmtMapUpsert->execute(array($feedId, $uid, 'room_block', $mappedBlockId > 0 ? $mappedBlockId : 0, 'ignored'));
                    }
                    continue;
                }

                $overlap = pms_ota_ical_overlap_counts($pdo, $targetRoomId, $startDate, $endDate, $mappedBlockId);
                if ((int)$overlap['reservation_overlap'] > 0 || (int)$overlap['block_overlap'] > 0) {
                    $warnings[] = 'UID ' . $uid . ': conflicto de disponibilidad, no se aplico bloque.';
                    if ($mapRow) {
                        $stmtMapUpsert->execute(array($feedId, $uid, 'room_block', $mappedBlockId > 0 ? $mappedBlockId : 0, 'ignored'));
                    }
                    continue;
                }

                if ($mappedBlockId > 0) {
                    $stmtUpdateBlock = $pdo->prepare(
                        'UPDATE room_block
                         SET id_property = ?,
                             id_room = ?,
                             start_date = ?,
                             end_date = ?,
                             description = ?,
                             is_active = 1,
                             deleted_at = NULL,
                             updated_at = NOW()
                         WHERE id_room_block = ?'
                    );
                    $stmtUpdateBlock->execute(array(
                        $targetPropertyId,
                        $targetRoomId,
                        $startDate,
                        $endDateInclusive,
                        $blockDescription,
                        $mappedBlockId
                    ));
                    $stats['blocks_updated']++;
                    $stmtMapUpsert->execute(array($feedId, $uid, 'room_block', $mappedBlockId, 'linked'));
                } else {
                    $blockCode = 'ICAL-' . $feedId . '-' . strtoupper(substr(md5($uid), 0, 8));
                    $stmtInsertBlock = $pdo->prepare(
                        'INSERT INTO room_block (
                            id_room,
                            id_property,
                            id_user,
                            code,
                            description,
                            start_date,
                            end_date,
                            is_active,
                            created_at,
                            updated_at
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
                    );
                    $stmtInsertBlock->execute(array(
                        $targetRoomId,
                        $targetPropertyId,
                        $actorUserId,
                        $blockCode,
                        $blockDescription,
                        $startDate,
                        $endDateInclusive
                    ));
                    $newBlockId = (int)$pdo->lastInsertId();
                    $stats['blocks_created']++;
                    $stmtMapUpsert->execute(array($feedId, $uid, 'room_block', $newBlockId, 'linked'));
                }
            }

            foreach ($existingRows as $uid => $oldRow) {
                if (isset($seen[$uid])) {
                    continue;
                }
                $stmtMapFind->execute(array($feedId, $uid));
                $mapRow = $stmtMapFind->fetch(PDO::FETCH_ASSOC);
                if ($mapRow && isset($mapRow['entity_type']) && $mapRow['entity_type'] === 'room_block') {
                    $mappedBlockId = (int)(isset($mapRow['entity_id']) ? $mapRow['entity_id'] : 0);
                    if ($mappedBlockId > 0 && pms_ota_ical_soft_delete_room_block($pdo, $mappedBlockId)) {
                        $stats['blocks_deleted']++;
                    }
                    $stmtMapUpsert->execute(array($feedId, $uid, 'room_block', $mappedBlockId > 0 ? $mappedBlockId : 0, 'ignored'));
                }
            }

            $stmtUpdateFeed = $pdo->prepare(
                'UPDATE ota_ical_feed
                 SET last_sync_at = NOW(),
                     last_success_at = NOW(),
                     last_error = NULL,
                     http_etag = ?,
                     http_last_modified = ?,
                     updated_at = NOW()
                 WHERE id_ota_ical_feed = ?'
            );
            $stmtUpdateFeed->execute(array(
                isset($http['etag']) ? (string)$http['etag'] : null,
                isset($http['last_modified']) ? (string)$http['last_modified'] : null,
                $feedId
            ));

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $stmtError = $pdo->prepare(
                'UPDATE ota_ical_feed
                 SET last_sync_at = NOW(),
                     last_error = ?,
                     updated_at = NOW()
                 WHERE id_ota_ical_feed = ?'
            );
            $stmtError->execute(array($e->getMessage(), $feedId));
            throw $e;
        }

        $stats['warnings'] = $warnings;
        return $stats;
    }
}

if (!function_exists('pms_ota_ical_sync_due_feeds')) {
    function pms_ota_ical_sync_due_feeds(PDO $pdo, $companyId = 0, $propertyId = 0, $actorUserId = null, $limit = 50)
    {
        if (!pms_ota_ical_table_exists($pdo, 'ota_ical_feed')) {
            throw new Exception('La tabla ota_ical_feed no existe en esta base de datos.');
        }

        $companyId = (int)$companyId;
        $propertyId = (int)$propertyId;
        $limit = (int)$limit;
        if ($companyId <= 0) {
            throw new Exception('Empresa invalida para sincronizar feeds iCal.');
        }
        if ($limit <= 0) {
            $limit = 50;
        } elseif ($limit > 500) {
            $limit = 500;
        }

        $where = array(
            'f.deleted_at IS NULL',
            'COALESCE(f.is_active, 1) = 1',
            'COALESCE(f.import_enabled, 0) = 1',
            '(f.last_sync_at IS NULL OR TIMESTAMPDIFF(MINUTE, f.last_sync_at, NOW()) >= GREATEST(COALESCE(f.sync_interval_minutes, 30), 5))'
        );
        $params = array();
        $where[] = 'f.id_company = ?';
        $params[] = $companyId;
        if ($propertyId > 0) {
            $where[] = 'f.id_property = ?';
            $params[] = $propertyId;
        }

        $sql = 'SELECT f.id_ota_ical_feed
                FROM ota_ical_feed f
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY COALESCE(f.last_sync_at, \'1970-01-01 00:00:00\') ASC, f.id_ota_ical_feed ASC
                LIMIT ' . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $feedIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        $result = array(
            'feeds_selected' => count($feedIds),
            'feeds_synced' => 0,
            'feeds_failed' => 0,
            'events_total' => 0,
            'events_created' => 0,
            'events_updated' => 0,
            'events_deactivated' => 0,
            'blocks_created' => 0,
            'blocks_updated' => 0,
            'blocks_deleted' => 0,
            'warnings' => array(),
            'errors' => array()
        );

        foreach ($feedIds as $feedIdRaw) {
            $feedId = (int)$feedIdRaw;
            if ($feedId <= 0) {
                continue;
            }
            try {
                $feed = pms_ota_ical_fetch_feed_by_id($pdo, $companyId, $feedId);
                if (!$feed) {
                    $result['feeds_failed']++;
                    $result['errors'][] = 'Feed #' . $feedId . ': no encontrado.';
                    continue;
                }
                $sync = pms_ota_ical_sync_feed($pdo, $feed, $actorUserId);
                $result['feeds_synced']++;
                $result['events_total'] += (int)(isset($sync['events_total']) ? $sync['events_total'] : 0);
                $result['events_created'] += (int)(isset($sync['events_created']) ? $sync['events_created'] : 0);
                $result['events_updated'] += (int)(isset($sync['events_updated']) ? $sync['events_updated'] : 0);
                $result['events_deactivated'] += (int)(isset($sync['events_deactivated']) ? $sync['events_deactivated'] : 0);
                $result['blocks_created'] += (int)(isset($sync['blocks_created']) ? $sync['blocks_created'] : 0);
                $result['blocks_updated'] += (int)(isset($sync['blocks_updated']) ? $sync['blocks_updated'] : 0);
                $result['blocks_deleted'] += (int)(isset($sync['blocks_deleted']) ? $sync['blocks_deleted'] : 0);
                if (!empty($sync['warnings']) && is_array($sync['warnings'])) {
                    foreach ($sync['warnings'] as $warn) {
                        $result['warnings'][] = 'Feed #' . $feedId . ': ' . (string)$warn;
                    }
                }
            } catch (Exception $e) {
                $result['feeds_failed']++;
                $result['errors'][] = 'Feed #' . $feedId . ': ' . $e->getMessage();
            }
        }

        return $result;
    }
}
