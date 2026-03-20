<?php
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/services/RateplanPricingService.php';

function ai_tunnel_config_shared()
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    $config = require __DIR__ . '/config.php';
    if (!is_array($config)) {
        throw new RuntimeException('AI tunnel config.php must return an array.');
    }
    return $config;
}

function ai_tunnel_h_shared($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ai_tunnel_text_shared($value, $fallback = '-')
{
    $value = trim((string)$value);
    return $value !== '' ? $value : $fallback;
}

function ai_tunnel_currency_shared($cents, $currency)
{
    return trim((string)$currency) . ' ' . number_format(((int)$cents) / 100, 2, '.', ',');
}

function ai_tunnel_base_styles_shared()
{
    return 'body{font-family:Arial,sans-serif;background:#0b1220;color:#e2e8f0;margin:0;padding:24px}
            .page{max-width:1700px;margin:0 auto;display:grid;gap:20px}
            .card{background:#111c35;border:1px solid rgba(148,163,184,.18);border-radius:14px;padding:18px}
            .table-wrap{overflow-x:auto;margin-top:10px}
            table{width:100%;border-collapse:collapse;font-size:.9rem}
            th,td{border:1px solid rgba(148,163,184,.16);padding:8px 10px;text-align:left;vertical-align:top}
            th{background:#162541}
            .muted{color:#94a3b8}
            .pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:rgba(56,189,248,.12);border:1px solid rgba(56,189,248,.2);color:#dbeafe;font-size:.82rem;margin-right:8px;margin-top:8px}
            .section-grid{display:grid;gap:16px}
            .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
            .stat{background:#0c152b;border:1px solid rgba(148,163,184,.14);border-radius:12px;padding:14px}
            .stat-label{display:block;color:#94a3b8;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em}
            .stat-value{display:block;margin-top:6px;font-size:1rem;font-weight:700;color:#f8fafc}
            code{background:rgba(15,23,42,.85);padding:2px 6px;border-radius:6px}';
}

function ai_tunnel_validate_credential_shared($credential, array $allowedCredentials)
{
    $credential = trim((string)$credential);
    if ($credential === '') {
        return false;
    }
    foreach ($allowedCredentials as $allowed) {
        if (hash_equals((string)$allowed, $credential)) {
            return true;
        }
    }
    return false;
}

function ai_tunnel_fail_shared($statusCode, $title, $message)
{
    http_response_code((int)$statusCode);
    ?><!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?php echo ai_tunnel_h_shared($title); ?></title>
      <style><?php echo ai_tunnel_base_styles_shared(); ?></style>
    </head>
    <body>
      <div class="page">
        <section class="card">
          <h1><?php echo ai_tunnel_h_shared($title); ?></h1>
          <p><?php echo ai_tunnel_h_shared($message); ?></p>
        </section>
      </div>
    </body>
    </html><?php
    exit;
}

function ai_tunnel_render_table_shared(array $rows, array $columns, $emptyMessage)
{
    if (!$columns || !$rows) {
        echo '<p class="muted">' . ai_tunnel_h_shared($emptyMessage) . '</p>';
        return;
    }
    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . ai_tunnel_h_shared($column['label']) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            $key = $column['key'];
            echo '<td>' . ai_tunnel_h_shared(isset($row[$key]) ? $row[$key] : '') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function ai_tunnel_endpoint_contracts_shared()
{
    return array(
        'bootstrap' => array(
            'file' => 'solicitar_catalogo_operativo.php',
            'title' => 'Catalogo operativo',
            'description' => 'Bootstrap canonico: propiedades, categorias, habitaciones y actividad de apoyo.',
            'params' => 'credential, property_code, category_code, room_code, date_from, date_to, property_id',
            'defaults' => 'Actividad de apoyo: hoy..hoy. Catalogo base no depende de fechas.',
            'examples' => 'solicitar_catalogo_operativo.php?credential=[credential]&property_code=TRIZ'
        ),
        'availability' => array(
            'file' => 'solicitar_disponibilidad_30_dias.php',
            'title' => 'Disponibilidad filtrada',
            'description' => 'Disponibilidad y precios por fecha exacta, con matriz diaria y continuidad.',
            'params' => 'credential, property_code, category_code, room_code, status, date_from, date_to, property_id',
            'defaults' => 'Sin fechas: hoy..hoy+29. Si llega solo una fecha, se usa para ambos extremos.',
            'examples' => 'solicitar_disponibilidad_30_dias.php?credential=[credential]&property_code=CSJ&date_from=2026-03-18&date_to=2026-03-22'
        ),
        'guests' => array(
            'file' => 'solicitar_huespedes_en_casa.php',
            'title' => 'Huespedes en casa',
            'description' => 'Huespedes hospedados en una fecha puntual, con filtros por entidad.',
            'params' => 'credential, property_code, category_code, room_code, reservation_code, guest_query, status, date_at, property_id',
            'defaults' => 'Sin date_at: hoy. Sin status: EN CASA.',
            'examples' => 'solicitar_huespedes_en_casa.php?credential=[credential]&date_at=2026-03-18&guest_query=pablo'
        ),
        'reservations' => array(
            'file' => 'solicitar_reservaciones_detalle.php',
            'title' => 'Reservaciones detalle',
            'description' => 'Reservaciones, folios, pagos y line items dentro de la ventana pedida.',
            'params' => 'credential, property_code, category_code, room_code, reservation_code, folio_id, guest_query, status, date_from, date_to, property_id',
            'defaults' => 'Sin fechas: ventana operativa configurada en config.php.',
            'examples' => 'solicitar_reservaciones_detalle.php?credential=[credential]&reservation_code=AP-123&folio_id=15'
        ),
        'integral' => array(
            'file' => 'solicitar_estado_actual.php',
            'title' => 'Estado actual',
            'description' => 'Vista integral de respaldo usando los mismos filtros compartidos.',
            'params' => 'credential, property_code, category_code, room_code, reservation_code, folio_id, guest_query, status, date_from, date_to, date_at, property_id',
            'defaults' => 'Reservaciones: ventana operativa. Disponibilidad: hoy..hoy+29. Huespedes: hoy.',
            'examples' => 'solicitar_estado_actual.php?credential=[credential]&property_code=TRIZ&date_from=2026-03-18&date_to=2026-03-24'
        ),
    );
}

function ai_tunnel_allowed_params_shared($endpointKey)
{
    $shared = array('credential', 'property_code', 'category_code', 'room_code', 'property_id');
    if ($endpointKey === 'bootstrap') {
        return array_merge($shared, array('date_from', 'date_to'));
    }
    if ($endpointKey === 'availability') {
        return array_merge($shared, array('status', 'date_from', 'date_to'));
    }
    if ($endpointKey === 'guests') {
        return array_merge($shared, array('reservation_code', 'guest_query', 'status', 'date_at'));
    }
    if ($endpointKey === 'reservations') {
        return array_merge($shared, array('reservation_code', 'folio_id', 'guest_query', 'status', 'date_from', 'date_to'));
    }
    if ($endpointKey === 'integral') {
        return array_merge($shared, array('reservation_code', 'folio_id', 'guest_query', 'status', 'date_from', 'date_to', 'date_at'));
    }
    return $shared;
}

function ai_tunnel_parse_csv_shared($value, $numeric)
{
    $parts = preg_split('/\s*,\s*/', trim((string)$value));
    $values = array();
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part === '') {
            continue;
        }
        if ($numeric) {
            if (!ctype_digit($part)) {
                throw new InvalidArgumentException('El parametro debe ser una lista CSV numerica valida.');
            }
            $values[] = (string)((int)$part);
        } else {
            $values[] = strtoupper($part);
        }
    }
    return array_values(array_unique($values));
}

function ai_tunnel_parse_date_shared($value, $paramName)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('Fecha invalida para ' . $paramName . '. Usa formato YYYY-MM-DD.');
    }
    return $value;
}

function ai_tunnel_parse_filters_shared($endpointKey)
{
    $allowed = array_flip(ai_tunnel_allowed_params_shared($endpointKey));
    foreach ($_GET as $param => $value) {
        if (!isset($allowed[$param])) {
            throw new InvalidArgumentException('Parametro no soportado para este endpoint: ' . $param);
        }
    }
    if (isset($_GET['property_id']) && !ctype_digit((string)$_GET['property_id'])) {
        throw new InvalidArgumentException('property_id debe ser numerico.');
    }

    $dateFrom = ai_tunnel_parse_date_shared(isset($_GET['date_from']) ? $_GET['date_from'] : '', 'date_from');
    $dateTo = ai_tunnel_parse_date_shared(isset($_GET['date_to']) ? $_GET['date_to'] : '', 'date_to');
    if ($dateFrom && !$dateTo) {
        $dateTo = $dateFrom;
    } elseif ($dateTo && !$dateFrom) {
        $dateFrom = $dateTo;
    }

    return array(
        'credential' => trim((string)(isset($_GET['credential']) ? $_GET['credential'] : '')),
        'property_id' => isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0,
        'property_codes' => ai_tunnel_parse_csv_shared(isset($_GET['property_code']) ? $_GET['property_code'] : '', false),
        'category_codes' => ai_tunnel_parse_csv_shared(isset($_GET['category_code']) ? $_GET['category_code'] : '', false),
        'room_codes' => ai_tunnel_parse_csv_shared(isset($_GET['room_code']) ? $_GET['room_code'] : '', false),
        'statuses' => ai_tunnel_parse_csv_shared(isset($_GET['status']) ? $_GET['status'] : '', false),
        'reservation_codes' => ai_tunnel_parse_csv_shared(isset($_GET['reservation_code']) ? $_GET['reservation_code'] : '', false),
        'folio_ids' => ai_tunnel_parse_csv_shared(isset($_GET['folio_id']) ? $_GET['folio_id'] : '', true),
        'guest_query' => trim((string)(isset($_GET['guest_query']) ? $_GET['guest_query'] : '')),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'date_at' => ai_tunnel_parse_date_shared(isset($_GET['date_at']) ? $_GET['date_at'] : '', 'date_at'),
    );
}

function ai_tunnel_resolve_range_shared($dateFrom, $dateTo, $defaultFrom, $defaultTo)
{
    $from = $dateFrom ? $dateFrom : $defaultFrom;
    $to = $dateTo ? $dateTo : $defaultTo;
    if ($from > $to) {
        throw new InvalidArgumentException('date_from no puede ser mayor que date_to.');
    }
    return array($from, $to);
}

function ai_tunnel_match_value_shared($value, array $allowedValues)
{
    if (!$allowedValues) {
        return true;
    }
    return in_array(strtoupper(trim((string)$value)), $allowedValues, true);
}

function ai_tunnel_match_guest_query_shared(array $haystackValues, $guestQuery)
{
    $guestQuery = trim((string)$guestQuery);
    if ($guestQuery === '') {
        return true;
    }
    $needle = function_exists('mb_strtolower') ? mb_strtolower($guestQuery, 'UTF-8') : strtolower($guestQuery);
    foreach ($haystackValues as $value) {
        $haystack = trim((string)$value);
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
        if (strpos($haystack, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function ai_tunnel_fetch_company_and_properties_shared(PDO $pdo, $companyCode, array $filters)
{
    $stmt = $pdo->prepare(
        'SELECT c.id_company,
                c.code AS company_code,
                COALESCE(NULLIF(TRIM(c.trade_name), \'\'), NULLIF(TRIM(c.legal_name), \'\'), c.code) AS company_name,
                c.default_currency AS company_currency,
                p.id_property,
                p.code AS property_code,
                p.name AS property_name,
                p.currency,
                p.email AS property_email,
                p.phone AS property_phone,
                p.website AS property_website,
                p.address_line1,
                p.address_line2,
                p.city,
                p.state,
                p.postal_code,
                p.country,
                p.timezone,
                p.check_out_time,
                p.notes AS property_notes
         FROM company c
         JOIN property p
           ON p.id_company = c.id_company
          AND p.deleted_at IS NULL
          AND COALESCE(p.is_active, 1) = 1
         WHERE c.code = ?
         ORDER BY p.order_index, p.name, p.code'
    );
    $stmt->execute(array($companyCode));
    $rows = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($filters['property_id'] > 0 && (int)$row['id_property'] !== (int)$filters['property_id']) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($row['property_code']) ? $row['property_code'] : '', $filters['property_codes'])) {
            continue;
        }
        $rows[] = $row;
    }
    return $rows;
}

function ai_tunnel_fetch_property_categories_shared(PDO $pdo, array $propertyRow, array $filters)
{
    $stmt = $pdo->prepare(
        'SELECT rc.id_category, rc.code AS category_code, rc.name AS category_name, rc.description, rc.base_occupancy, rc.max_occupancy,
                rc.default_base_price_cents, rc.min_price_cents, rc.default_floor_cents, rc.default_ceil_cents,
                COALESCE(NULLIF(TRIM(rc.color_hex), \'\'), \'\') AS color_hex
         FROM roomcategory rc
         WHERE rc.id_property = ?
           AND rc.deleted_at IS NULL
           AND COALESCE(rc.is_active, 1) = 1
         ORDER BY rc.order_index, rc.name, rc.code'
    );
    $stmt->execute(array((int)$propertyRow['id_property']));
    $currency = isset($propertyRow['currency']) ? $propertyRow['currency'] : 'MXN';
    $rows = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!ai_tunnel_match_value_shared(isset($row['category_code']) ? $row['category_code'] : '', $filters['category_codes'])) {
            continue;
        }
        $rows[] = array(
            'id_category' => isset($row['id_category']) ? $row['id_category'] : '',
            'category_code' => ai_tunnel_text_shared(isset($row['category_code']) ? $row['category_code'] : ''),
            'category_name' => ai_tunnel_text_shared(isset($row['category_name']) ? $row['category_name'] : ''),
            'description' => ai_tunnel_text_shared(isset($row['description']) ? $row['description'] : ''),
            'base_occupancy' => isset($row['base_occupancy']) ? $row['base_occupancy'] : '',
            'max_occupancy' => isset($row['max_occupancy']) ? $row['max_occupancy'] : '',
            'default_base_price' => ai_tunnel_currency_shared(isset($row['default_base_price_cents']) ? $row['default_base_price_cents'] : 0, $currency),
            'min_price' => ai_tunnel_currency_shared(isset($row['min_price_cents']) ? $row['min_price_cents'] : 0, $currency),
            'price_floor' => ai_tunnel_currency_shared(isset($row['default_floor_cents']) ? $row['default_floor_cents'] : 0, $currency),
            'price_ceiling' => ai_tunnel_currency_shared(isset($row['default_ceil_cents']) ? $row['default_ceil_cents'] : 0, $currency),
            'color_hex' => ai_tunnel_text_shared(isset($row['color_hex']) ? $row['color_hex'] : '')
        );
    }
    return $rows;
}

function ai_tunnel_fetch_property_rooms_shared(PDO $pdo, array $propertyRow, array $filters)
{
    $stmt = $pdo->prepare(
        'SELECT r.id_room, r.code AS room_code, r.name AS room_name, rc.code AS category_code, rc.name AS category_name,
                r.capacity_total, r.max_adults, r.max_children, r.status, r.housekeeping_status, r.floor, r.building, r.bed_config,
                COALESCE(NULLIF(TRIM(r.color_hex), \'\'), \'\') AS color_hex, r.description
         FROM room r
         LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
         WHERE r.id_property = ?
           AND r.deleted_at IS NULL
           AND COALESCE(r.is_active, 1) = 1
         ORDER BY rc.order_index, rc.name, r.order_index, r.code'
    );
    $stmt->execute(array((int)$propertyRow['id_property']));
    $rows = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!ai_tunnel_match_value_shared(isset($row['category_code']) ? $row['category_code'] : '', $filters['category_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($row['room_code']) ? $row['room_code'] : '', $filters['room_codes'])) {
            continue;
        }
        $rows[] = array(
            'id_room' => isset($row['id_room']) ? $row['id_room'] : '',
            'room_code' => ai_tunnel_text_shared(isset($row['room_code']) ? $row['room_code'] : ''),
            'room_name' => ai_tunnel_text_shared(isset($row['room_name']) ? $row['room_name'] : ''),
            'category_code' => ai_tunnel_text_shared(isset($row['category_code']) ? $row['category_code'] : ''),
            'category_name' => ai_tunnel_text_shared(isset($row['category_name']) ? $row['category_name'] : ''),
            'capacity_total' => isset($row['capacity_total']) ? $row['capacity_total'] : '',
            'max_adults' => isset($row['max_adults']) ? $row['max_adults'] : '',
            'max_children' => isset($row['max_children']) ? $row['max_children'] : '',
            'status' => ai_tunnel_text_shared(isset($row['status']) ? $row['status'] : ''),
            'housekeeping_status' => ai_tunnel_text_shared(isset($row['housekeeping_status']) ? $row['housekeeping_status'] : ''),
            'floor' => ai_tunnel_text_shared(isset($row['floor']) ? $row['floor'] : ''),
            'building' => ai_tunnel_text_shared(isset($row['building']) ? $row['building'] : ''),
            'bed_config' => ai_tunnel_text_shared(isset($row['bed_config']) ? $row['bed_config'] : ''),
            'color_hex' => ai_tunnel_text_shared(isset($row['color_hex']) ? $row['color_hex'] : ''),
            'description' => ai_tunnel_text_shared(isset($row['description']) ? $row['description'] : '')
        );
    }
    return $rows;
}

function ai_tunnel_fetch_room_category_map_shared(PDO $pdo, array $propertyRow)
{
    $stmt = $pdo->prepare(
        'SELECT r.code AS room_code, COALESCE(rc.code, \'\') AS category_code, COALESCE(rc.name, \'\') AS category_name
         FROM room r
         LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
         WHERE r.id_property = ?
           AND r.deleted_at IS NULL
           AND COALESCE(r.is_active, 1) = 1'
    );
    $stmt->execute(array((int)$propertyRow['id_property']));
    $map = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string)$row['room_code']] = array(
            'category_code' => isset($row['category_code']) ? $row['category_code'] : '',
            'category_name' => isset($row['category_name']) ? $row['category_name'] : '',
        );
    }
    return $map;
}

function ai_tunnel_fetch_property_activity_shared(PDO $pdo, array $propertyRow, array $filters)
{
    list($from, $to) = ai_tunnel_resolve_range_shared(
        $filters['date_from'],
        $filters['date_to'],
        date('Y-m-d'),
        date('Y-m-d')
    );
    $stmt = $pdo->prepare(
        'SELECT r.code AS reservation_code, r.status, r.check_in_date, r.check_out_date,
                rm.code AS room_code, rm.name AS room_name, rc.code AS category_code, rc.name AS category_name,
                COALESCE(NULLIF(TRIM(g.full_name), \'\'), TRIM(CONCAT_WS(\' \', g.names, g.last_name))) AS guest_name,
                g.email, g.phone
         FROM reservation r
         LEFT JOIN room rm ON rm.id_room = r.id_room
         LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
         LEFT JOIN guest g ON g.id_guest = r.id_guest
         WHERE r.id_property = ?
           AND r.deleted_at IS NULL
           AND COALESCE(r.is_active, 1) = 1
           AND r.check_in_date <= ?
           AND r.check_out_date >= ?
         ORDER BY r.check_in_date, r.check_out_date, r.code'
    );
    $stmt->execute(array((int)$propertyRow['id_property'], $to, $from));
    $inHouse = array();
    $arrivals = array();
    $departures = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!ai_tunnel_match_value_shared(isset($row['status']) ? $row['status'] : '', $filters['statuses'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($row['reservation_code']) ? $row['reservation_code'] : '', $filters['reservation_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($row['category_code']) ? $row['category_code'] : '', $filters['category_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($row['room_code']) ? $row['room_code'] : '', $filters['room_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_guest_query_shared(
            array(
                isset($row['guest_name']) ? $row['guest_name'] : '',
                isset($row['email']) ? $row['email'] : '',
                isset($row['phone']) ? $row['phone'] : ''
            ),
            $filters['guest_query']
        )) {
            continue;
        }
        $entry = array(
            'reservation_code' => ai_tunnel_text_shared(isset($row['reservation_code']) ? $row['reservation_code'] : ''),
            'status' => ai_tunnel_text_shared(isset($row['status']) ? $row['status'] : ''),
            'guest_name' => ai_tunnel_text_shared(isset($row['guest_name']) ? $row['guest_name'] : ''),
            'room_code' => ai_tunnel_text_shared(isset($row['room_code']) ? $row['room_code'] : ''),
            'room_name' => ai_tunnel_text_shared(isset($row['room_name']) ? $row['room_name'] : ''),
            'category_code' => ai_tunnel_text_shared(isset($row['category_code']) ? $row['category_code'] : ''),
            'category_name' => ai_tunnel_text_shared(isset($row['category_name']) ? $row['category_name'] : ''),
            'check_in_date' => ai_tunnel_text_shared(isset($row['check_in_date']) ? $row['check_in_date'] : ''),
            'check_out_date' => ai_tunnel_text_shared(isset($row['check_out_date']) ? $row['check_out_date'] : ''),
        );
        if ($from >= (string)$row['check_in_date'] && $from < (string)$row['check_out_date']) {
            $inHouse[] = $entry;
        }
        if ((string)$row['check_in_date'] >= $from && (string)$row['check_in_date'] <= $to) {
            $arrivals[] = $entry;
        }
        if ((string)$row['check_out_date'] >= $from && (string)$row['check_out_date'] <= $to) {
            $departures[] = $entry;
        }
    }
    return array(
        'date_from' => $from,
        'date_to' => $to,
        'in_house' => $inHouse,
        'arrivals' => $arrivals,
        'departures' => $departures,
    );
}

function ai_tunnel_fetch_current_guests_shared(PDO $pdo, array $propertyRow, array $filters)
{
    $dateAt = $filters['date_at'] ? $filters['date_at'] : date('Y-m-d');
    $sql = 'SELECT r.code AS reservation_code, r.status AS reservation_status, r.check_in_date, r.check_out_date, r.adults, r.children, r.infants,
                   rm.code AS room_code, rm.name AS room_name, rc.code AS category_code, rc.name AS category_name, g.id_guest,
                   COALESCE(NULLIF(TRIM(g.full_name), \'\'), TRIM(CONCAT_WS(\' \', g.names, g.last_name))) AS guest_name,
                   g.email, g.phone, g.nationality, g.country_residence, g.doc_type, g.doc_number, g.language, g.notes_guest, g.notes_internal
            FROM reservation r
            LEFT JOIN room rm ON rm.id_room = r.id_room
            LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
            LEFT JOIN guest g ON g.id_guest = r.id_guest
            WHERE r.id_property = ?
              AND r.deleted_at IS NULL
              AND COALESCE(r.is_active, 1) = 1
              AND ? >= r.check_in_date
              AND ? < r.check_out_date';
    $params = array((int)$propertyRow['id_property'], $dateAt, $dateAt);
    if ($filters['statuses']) {
        $placeholders = implode(',', array_fill(0, count($filters['statuses']), '?'));
        $sql .= ' AND UPPER(COALESCE(r.status, \'\')) IN (' . $placeholders . ')';
        foreach ($filters['statuses'] as $status) {
            $params[] = $status;
        }
    } else {
        $sql .= ' AND LOWER(COALESCE(r.status, \'\')) = \'en casa\'';
    }
    $sql .= ' ORDER BY rm.code, r.check_out_date, guest_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!ai_tunnel_match_value_shared(isset($row['reservation_code']) ? $row['reservation_code'] : '', $filters['reservation_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($row['category_code']) ? $row['category_code'] : '', $filters['category_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($row['room_code']) ? $row['room_code'] : '', $filters['room_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_guest_query_shared(
            array(
                isset($row['guest_name']) ? $row['guest_name'] : '',
                isset($row['email']) ? $row['email'] : '',
                isset($row['phone']) ? $row['phone'] : ''
            ),
            $filters['guest_query']
        )) {
            continue;
        }
        $rows[] = array(
            'reservation_code' => ai_tunnel_text_shared(isset($row['reservation_code']) ? $row['reservation_code'] : ''),
            'reservation_status' => ai_tunnel_text_shared(isset($row['reservation_status']) ? $row['reservation_status'] : ''),
            'id_guest' => isset($row['id_guest']) ? $row['id_guest'] : '',
            'guest_name' => ai_tunnel_text_shared(isset($row['guest_name']) ? $row['guest_name'] : ''),
            'email' => ai_tunnel_text_shared(isset($row['email']) ? $row['email'] : ''),
            'phone' => ai_tunnel_text_shared(isset($row['phone']) ? $row['phone'] : ''),
            'room_code' => ai_tunnel_text_shared(isset($row['room_code']) ? $row['room_code'] : ''),
            'room_name' => ai_tunnel_text_shared(isset($row['room_name']) ? $row['room_name'] : ''),
            'category_code' => ai_tunnel_text_shared(isset($row['category_code']) ? $row['category_code'] : ''),
            'category_name' => ai_tunnel_text_shared(isset($row['category_name']) ? $row['category_name'] : ''),
            'check_in_date' => ai_tunnel_text_shared(isset($row['check_in_date']) ? $row['check_in_date'] : ''),
            'check_out_date' => ai_tunnel_text_shared(isset($row['check_out_date']) ? $row['check_out_date'] : ''),
            'adults' => isset($row['adults']) ? $row['adults'] : '',
            'children' => isset($row['children']) ? $row['children'] : '',
            'infants' => isset($row['infants']) ? $row['infants'] : '',
            'nationality' => ai_tunnel_text_shared(isset($row['nationality']) ? $row['nationality'] : ''),
            'country_residence' => ai_tunnel_text_shared(isset($row['country_residence']) ? $row['country_residence'] : ''),
            'doc_type' => ai_tunnel_text_shared(isset($row['doc_type']) ? $row['doc_type'] : ''),
            'doc_number' => ai_tunnel_text_shared(isset($row['doc_number']) ? $row['doc_number'] : ''),
            'language' => ai_tunnel_text_shared(isset($row['language']) ? $row['language'] : ''),
            'notes_guest' => ai_tunnel_text_shared(isset($row['notes_guest']) ? $row['notes_guest'] : ''),
            'notes_internal' => ai_tunnel_text_shared(isset($row['notes_internal']) ? $row['notes_internal'] : ''),
        );
    }
    return array('date_at' => $dateAt, 'rows' => $rows);
}

function ai_tunnel_collect_property_reservations_shared(PDO $pdo, $companyCode, array $propertyRow, array $filters, array $range)
{
    $roomCategoryMap = ai_tunnel_fetch_room_category_map_shared($pdo, $propertyRow);
    $sets = pms_call_procedure('sp_portal_reservation_data', array($companyCode, $propertyRow['property_code'], '', $range[0], $range[1], 0, 0));
    $reservationRows = isset($sets[0]) ? $sets[0] : array();
    $currency = isset($propertyRow['currency']) ? $propertyRow['currency'] : 'MXN';
    $payload = array('reservations' => array(), 'folios' => array(), 'payments' => array(), 'line_items' => array());

    foreach ($reservationRows as $reservationRow) {
        $reservationId = isset($reservationRow['id_reservation']) ? (int)$reservationRow['id_reservation'] : 0;
        if ($reservationId <= 0) {
            continue;
        }
        $detailSets = pms_call_procedure('sp_portal_reservation_data', array($companyCode, $propertyRow['property_code'], '', $range[0], $range[1], $reservationId, 0));
        $detailRow = isset($detailSets[1][0]) ? $detailSets[1][0] : $reservationRow;
        $folioRows = isset($detailSets[2]) ? $detailSets[2] : array();
        $lineItemRows = isset($detailSets[3]) ? $detailSets[3] : array();
        $paymentRows = isset($detailSets[5]) ? $detailSets[5] : array();

        $reservationCode = isset($detailRow['reservation_code']) ? $detailRow['reservation_code'] : (isset($reservationRow['reservation_code']) ? $reservationRow['reservation_code'] : '');
        $reservationStatus = isset($detailRow['status']) ? $detailRow['status'] : (isset($reservationRow['status']) ? $reservationRow['status'] : '');
        $roomCode = isset($detailRow['room_code']) ? $detailRow['room_code'] : '';
        $categoryCode = isset($detailRow['category_code']) ? $detailRow['category_code'] : '';
        $categoryName = isset($detailRow['category_name']) ? $detailRow['category_name'] : '';
        if ($categoryCode === '' && isset($roomCategoryMap[$roomCode])) {
            $categoryCode = $roomCategoryMap[$roomCode]['category_code'];
            $categoryName = $roomCategoryMap[$roomCode]['category_name'];
        }
        $guestName = trim((string)(
            (isset($detailRow['guest_full_name']) ? $detailRow['guest_full_name'] : '') ?:
            trim((string)(isset($detailRow['guest_names']) ? $detailRow['guest_names'] : '') . ' ' . (string)(isset($detailRow['guest_last_name']) ? $detailRow['guest_last_name'] : ''))
        ));

        if (!ai_tunnel_match_value_shared($reservationStatus, $filters['statuses'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared($reservationCode, $filters['reservation_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared($categoryCode, $filters['category_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared($roomCode, $filters['room_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_guest_query_shared(
            array(
                $guestName,
                isset($detailRow['guest_email']) ? $detailRow['guest_email'] : '',
                isset($detailRow['guest_phone']) ? $detailRow['guest_phone'] : ''
            ),
            $filters['guest_query']
        )) {
            continue;
        }

        $matchedFolioIds = array();
        foreach ($folioRows as $folioRow) {
            $folioId = isset($folioRow['id_folio']) ? (string)((int)$folioRow['id_folio']) : '';
            if (!$filters['folio_ids'] || in_array($folioId, $filters['folio_ids'], true)) {
                $matchedFolioIds[] = $folioId;
            }
        }
        if ($filters['folio_ids'] && !$matchedFolioIds) {
            continue;
        }

        $payload['reservations'][] = array(
            'reservation_code' => ai_tunnel_text_shared($reservationCode),
            'guest_name' => ai_tunnel_text_shared($guestName),
            'status' => ai_tunnel_text_shared($reservationStatus),
            'source' => ai_tunnel_text_shared(isset($detailRow['source']) ? $detailRow['source'] : ''),
            'ota_name' => ai_tunnel_text_shared(isset($detailRow['ota_name']) ? $detailRow['ota_name'] : ''),
            'check_in_date' => ai_tunnel_text_shared(isset($detailRow['check_in_date']) ? $detailRow['check_in_date'] : ''),
            'check_out_date' => ai_tunnel_text_shared(isset($detailRow['check_out_date']) ? $detailRow['check_out_date'] : ''),
            'room_code' => ai_tunnel_text_shared($roomCode),
            'category_code' => ai_tunnel_text_shared($categoryCode),
            'category_name' => ai_tunnel_text_shared($categoryName),
            'rateplan_name' => ai_tunnel_text_shared(isset($detailRow['rateplan_name']) ? $detailRow['rateplan_name'] : ''),
            'total_price' => ai_tunnel_currency_shared(isset($detailRow['total_price_cents']) ? $detailRow['total_price_cents'] : 0, isset($detailRow['currency']) ? $detailRow['currency'] : $currency),
            'balance_due' => ai_tunnel_currency_shared(isset($detailRow['balance_due_cents']) ? $detailRow['balance_due_cents'] : 0, isset($detailRow['currency']) ? $detailRow['currency'] : $currency)
        );

        foreach ($folioRows as $folioRow) {
            $folioId = isset($folioRow['id_folio']) ? (string)((int)$folioRow['id_folio']) : '';
            if ($filters['folio_ids'] && !in_array($folioId, $matchedFolioIds, true)) {
                continue;
            }
            $folioCurrency = isset($folioRow['currency']) ? $folioRow['currency'] : $currency;
            $payload['folios'][] = array(
                'reservation_code' => ai_tunnel_text_shared($reservationCode),
                'guest_name' => ai_tunnel_text_shared($guestName),
                'id_folio' => $folioId,
                'folio_name' => ai_tunnel_text_shared(isset($folioRow['folio_name']) ? $folioRow['folio_name'] : ''),
                'status' => ai_tunnel_text_shared(isset($folioRow['status']) ? $folioRow['status'] : ''),
                'total' => ai_tunnel_currency_shared(isset($folioRow['total_cents']) ? $folioRow['total_cents'] : 0, $folioCurrency),
                'balance' => ai_tunnel_currency_shared(isset($folioRow['balance_cents']) ? $folioRow['balance_cents'] : 0, $folioCurrency)
            );
        }

        foreach ($paymentRows as $paymentRow) {
            $folioId = isset($paymentRow['id_folio']) ? (string)((int)$paymentRow['id_folio']) : '';
            if ($filters['folio_ids'] && !in_array($folioId, $matchedFolioIds, true)) {
                continue;
            }
            $paymentCurrency = isset($paymentRow['currency']) ? $paymentRow['currency'] : $currency;
            $payload['payments'][] = array(
                'reservation_code' => ai_tunnel_text_shared($reservationCode),
                'guest_name' => ai_tunnel_text_shared($guestName),
                'id_payment' => isset($paymentRow['id_payment']) ? $paymentRow['id_payment'] : '',
                'id_folio' => $folioId,
                'folio_name' => ai_tunnel_text_shared(isset($paymentRow['folio_name']) ? $paymentRow['folio_name'] : ''),
                'payment_catalog' => ai_tunnel_text_shared(isset($paymentRow['id_payment_catalog']) ? $paymentRow['id_payment_catalog'] : ''),
                'method' => ai_tunnel_text_shared(isset($paymentRow['method']) ? $paymentRow['method'] : ''),
                'amount' => ai_tunnel_currency_shared(isset($paymentRow['amount_cents']) ? $paymentRow['amount_cents'] : 0, $paymentCurrency),
                'reference' => ai_tunnel_text_shared(isset($paymentRow['reference']) ? $paymentRow['reference'] : ''),
                'service_date' => ai_tunnel_text_shared(isset($paymentRow['service_date']) ? $paymentRow['service_date'] : ''),
                'status' => ai_tunnel_text_shared(isset($paymentRow['status']) ? $paymentRow['status'] : ''),
                'refunded_total' => ai_tunnel_currency_shared(isset($paymentRow['refunded_total_cents']) ? $paymentRow['refunded_total_cents'] : 0, $paymentCurrency)
            );
        }

        foreach ($lineItemRows as $lineItemRow) {
            $folioId = isset($lineItemRow['id_folio']) ? (string)((int)$lineItemRow['id_folio']) : '';
            if ($filters['folio_ids'] && !in_array($folioId, $matchedFolioIds, true)) {
                continue;
            }
            $itemCurrency = isset($lineItemRow['currency']) ? $lineItemRow['currency'] : $currency;
            $payload['line_items'][] = array(
                'reservation_code' => ai_tunnel_text_shared($reservationCode),
                'guest_name' => ai_tunnel_text_shared($guestName),
                'id_sale_item' => isset($lineItemRow['id_sale_item']) ? $lineItemRow['id_sale_item'] : '',
                'id_folio' => $folioId,
                'folio_name' => ai_tunnel_text_shared(isset($lineItemRow['folio_name']) ? $lineItemRow['folio_name'] : ''),
                'item_type' => ai_tunnel_text_shared(isset($lineItemRow['item_type']) ? $lineItemRow['item_type'] : ''),
                'item_name' => ai_tunnel_text_shared(isset($lineItemRow['item_name']) ? $lineItemRow['item_name'] : ''),
                'service_date' => ai_tunnel_text_shared(isset($lineItemRow['service_date']) ? $lineItemRow['service_date'] : ''),
                'quantity' => isset($lineItemRow['quantity']) ? $lineItemRow['quantity'] : '',
                'amount' => ai_tunnel_currency_shared(isset($lineItemRow['amount_cents']) ? $lineItemRow['amount_cents'] : 0, $itemCurrency),
                'status' => ai_tunnel_text_shared(isset($lineItemRow['status']) ? $lineItemRow['status'] : '')
            );
        }
    }
    return $payload;
}

function ai_tunnel_collect_property_availability_shared(RateplanPricingService $pricingService, array $propertyRow, array $filters)
{
    list($dateFrom, $dateTo) = ai_tunnel_resolve_range_shared(
        $filters['date_from'],
        $filters['date_to'],
        date('Y-m-d'),
        date('Y-m-d', strtotime('+29 day'))
    );
    $windowDays = (int)max(1, min(120, floor((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1));
    $calendarSets = pms_call_procedure('sp_property_room_calendar', array($propertyRow['property_code'], $dateFrom, $windowDays));
    $rooms = isset($calendarSets[0]) ? $calendarSets[0] : array();
    $days = isset($calendarSets[1]) ? $calendarSets[1] : array();
    $events = isset($calendarSets[2]) ? $calendarSets[2] : array();

    $occupiedByRoom = array();
    foreach ($events as $eventRow) {
        $roomId = isset($eventRow['id_room']) ? (int)$eventRow['id_room'] : 0;
        $checkInDate = isset($eventRow['check_in_date']) ? trim((string)$eventRow['check_in_date']) : '';
        $checkOutDate = isset($eventRow['check_out_date']) ? trim((string)$eventRow['check_out_date']) : '';
        $eventType = isset($eventRow['event_type']) ? trim((string)$eventRow['event_type']) : 'reservation';
        if ($roomId <= 0 || $checkInDate === '' || $checkOutDate === '') {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($eventRow['status']) ? $eventRow['status'] : '', $filters['statuses'])) {
            continue;
        }
        $endExclusive = $eventType === 'block' ? date('Y-m-d', strtotime($checkOutDate . ' +1 day')) : $checkOutDate;
        foreach ($days as $index => $dayRow) {
            $dateKey = isset($dayRow['date_key']) ? trim((string)$dayRow['date_key']) : '';
            if ($dateKey === '') {
                continue;
            }
            if ($dateKey >= $checkInDate && $dateKey < $endExclusive) {
                if (!isset($occupiedByRoom[$roomId])) {
                    $occupiedByRoom[$roomId] = array();
                }
                $occupiedByRoom[$roomId][$index] = true;
            }
        }
    }

    $priceRowsByRoom = array();
    foreach ($rooms as $roomRow) {
        if (!ai_tunnel_match_value_shared(isset($roomRow['category_code']) ? $roomRow['category_code'] : '', $filters['category_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($roomRow['room_code']) ? $roomRow['room_code'] : '', $filters['room_codes'])) {
            continue;
        }
        $roomCode = isset($roomRow['room_code']) ? (string)$roomRow['room_code'] : '';
        $categoryCode = isset($roomRow['category_code']) ? (string)$roomRow['category_code'] : '';
        try {
            $priceRowsByRoom[$roomCode] = $pricingService->getCalendarPricesByCodes($propertyRow['property_code'], '', $categoryCode, $roomCode, $dateFrom, $windowDays);
        } catch (Exception $e) {
            $priceRowsByRoom[$roomCode] = array();
        }
    }

    $columns = array(
        array('key' => 'room_code', 'label' => 'Habitacion'),
        array('key' => 'room_name', 'label' => 'Nombre'),
        array('key' => 'category_code', 'label' => 'Codigo categoria'),
        array('key' => 'category_name', 'label' => 'Categoria')
    );
    foreach ($days as $index => $dayRow) {
        $columns[] = array('key' => 'day_' . $index, 'label' => isset($dayRow['date_key']) ? $dayRow['date_key'] : ('Dia ' . ($index + 1)));
    }

    $payload = array(
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'next_date' => count($days) > 1 && isset($days[1]['date_key']) ? $days[1]['date_key'] : '',
        'starts_from' => array(),
        'starts_next_date' => array(),
        'daily_columns' => $columns,
        'daily_rows' => array(),
    );

    foreach ($rooms as $roomRow) {
        if (!ai_tunnel_match_value_shared(isset($roomRow['category_code']) ? $roomRow['category_code'] : '', $filters['category_codes'])) {
            continue;
        }
        if (!ai_tunnel_match_value_shared(isset($roomRow['room_code']) ? $roomRow['room_code'] : '', $filters['room_codes'])) {
            continue;
        }
        $roomId = isset($roomRow['id_room']) ? (int)$roomRow['id_room'] : 0;
        $roomCode = isset($roomRow['room_code']) ? (string)$roomRow['room_code'] : '';
        $roomName = isset($roomRow['room_name']) ? (string)$roomRow['room_name'] : '';
        $categoryCode = isset($roomRow['category_code']) ? (string)$roomRow['category_code'] : '';
        $categoryName = isset($roomRow['category_name']) ? (string)$roomRow['category_name'] : '';
        $priceRows = isset($priceRowsByRoom[$roomCode]) ? $priceRowsByRoom[$roomCode] : array();
        $dailyRow = array(
            'room_code' => $roomCode,
            'room_name' => $roomName,
            'category_code' => $categoryCode,
            'category_name' => $categoryName
        );
        foreach ($days as $index => $dayRow) {
            $priceRow = isset($priceRows[$index]) ? $priceRows[$index] : null;
            $isOccupied = ($roomId > 0 && isset($occupiedByRoom[$roomId][$index]));
            $finalCents = $priceRow && isset($priceRow['final_price_cents']) ? (int)$priceRow['final_price_cents'] : 0;
            $dailyRow['day_' . $index] = $isOccupied ? 'Ocupada' : 'Libre | ' . ai_tunnel_currency_shared($finalCents, isset($propertyRow['currency']) ? $propertyRow['currency'] : 'MXN');
        }
        $payload['daily_rows'][] = $dailyRow;

        foreach (array('starts_from' => 0, 'starts_next_date' => 1) as $bucket => $startIndex) {
            if (!isset($days[$startIndex])) {
                continue;
            }
            if ($roomId > 0 && isset($occupiedByRoom[$roomId][$startIndex])) {
                continue;
            }
            $continuousNights = 0;
            $nightlyPrices = array();
            for ($cursor = $startIndex; $cursor < count($days); $cursor++) {
                if ($roomId > 0 && isset($occupiedByRoom[$roomId][$cursor])) {
                    break;
                }
                $continuousNights++;
                $dateKey = isset($days[$cursor]['date_key']) ? $days[$cursor]['date_key'] : '';
                $priceRow = isset($priceRows[$cursor]) ? $priceRows[$cursor] : null;
                $finalCents = $priceRow && isset($priceRow['final_price_cents']) ? (int)$priceRow['final_price_cents'] : 0;
                $nightlyPrices[] = $dateKey . ': ' . ai_tunnel_currency_shared($finalCents, isset($propertyRow['currency']) ? $propertyRow['currency'] : 'MXN');
            }
            if ($continuousNights > 0) {
                $payload[$bucket][] = array(
                    'room_code' => $roomCode,
                    'room_name' => $roomName,
                    'category_code' => $categoryCode,
                    'category_name' => $categoryName,
                    'available_from' => isset($days[$startIndex]['date_key']) ? $days[$startIndex]['date_key'] : '',
                    'continuous_nights' => $continuousNights,
                    'nightly_prices' => implode(' | ', $nightlyPrices)
                );
            }
        }
    }

    return $payload;
}

function ai_tunnel_active_filters_summary_shared(array $filters)
{
    $summary = array();
    if ($filters['property_codes']) {
        $summary[] = 'property_code=' . implode(',', $filters['property_codes']);
    }
    if ($filters['category_codes']) {
        $summary[] = 'category_code=' . implode(',', $filters['category_codes']);
    }
    if ($filters['room_codes']) {
        $summary[] = 'room_code=' . implode(',', $filters['room_codes']);
    }
    if ($filters['statuses']) {
        $summary[] = 'status=' . implode(',', $filters['statuses']);
    }
    if ($filters['reservation_codes']) {
        $summary[] = 'reservation_code=' . implode(',', $filters['reservation_codes']);
    }
    if ($filters['folio_ids']) {
        $summary[] = 'folio_id=' . implode(',', $filters['folio_ids']);
    }
    if ($filters['guest_query'] !== '') {
        $summary[] = 'guest_query=' . $filters['guest_query'];
    }
    if ($filters['date_from']) {
        $summary[] = 'date_from=' . $filters['date_from'];
    }
    if ($filters['date_to']) {
        $summary[] = 'date_to=' . $filters['date_to'];
    }
    if ($filters['date_at']) {
        $summary[] = 'date_at=' . $filters['date_at'];
    }
    if ($filters['property_id'] > 0) {
        $summary[] = 'property_id=' . $filters['property_id'];
    }
    return $summary;
}

function ai_tunnel_render_contract_shared($basePath, array $contracts)
{
    $rows = array();
    foreach ($contracts as $contract) {
        $rows[] = array(
            'endpoint' => $contract['file'],
            'objetivo' => $contract['description'],
            'params' => $contract['params'],
            'defaults' => $contract['defaults'],
            'ejemplo' => $basePath . '/' . $contract['examples']
        );
    }
    ai_tunnel_render_table_shared(
        $rows,
        array(
            array('key' => 'endpoint', 'label' => 'Endpoint'),
            array('key' => 'objetivo', 'label' => 'Que trae'),
            array('key' => 'params', 'label' => 'Filtros aceptados'),
            array('key' => 'defaults', 'label' => 'Defaults'),
            array('key' => 'ejemplo', 'label' => 'Ejemplo')
        ),
        'Sin contrato de filtros.'
    );
}

function ai_tunnel_boot_shared($endpointKey)
{
    try {
        $filters = ai_tunnel_parse_filters_shared($endpointKey);
    } catch (InvalidArgumentException $e) {
        ai_tunnel_fail_shared(400, 'Solicitud invalida', $e->getMessage());
    }

    $config = ai_tunnel_config_shared();
    if (!ai_tunnel_validate_credential_shared($filters['credential'], isset($config['access_credentials']) ? (array)$config['access_credentials'] : array())) {
        ai_tunnel_fail_shared(401, 'Acceso denegado', 'Credential invalido o faltante.');
    }

    $companyCode = isset($config['company_code']) ? trim((string)$config['company_code']) : '';
    if ($companyCode === '') {
        ai_tunnel_fail_shared(500, 'Configuracion incompleta', 'company_code no esta configurado en ai tunnel/config.php.');
    }

    try {
        $pdo = pms_get_connection();
        pms_apply_runtime_timezone($pdo, pms_fetch_company_timezone($pdo, 0, $companyCode));
        $pricingService = new RateplanPricingService($pdo);
        $propertyRows = ai_tunnel_fetch_company_and_properties_shared($pdo, $companyCode, $filters);
    } catch (Exception $e) {
        ai_tunnel_fail_shared(500, 'Error de inicializacion', $e->getMessage());
    }

    $contracts = ai_tunnel_endpoint_contracts_shared();
    $basePath = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/') : '/ai tunnel';
    if ($basePath === '') {
        $basePath = '/ai tunnel';
    }

    return array($config, $companyCode, $pdo, $pricingService, $propertyRows, $filters, $contracts, $basePath);
}
