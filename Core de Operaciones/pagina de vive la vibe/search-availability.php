<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Locate DB connection helper
$connCandidates = [
  '/home/u508158532/domains/pxm.com.mx/public_html/pms db connections/connection.php',
];
foreach ($connCandidates as $candidate) {
  if ($candidate && is_file($candidate)) { require_once $candidate; break; }
}
if (!function_exists('createDatabaseConnection')) {
  http_response_code(500);
  echo json_encode([
    'error' => 'Missing database connection include',
    'details' => 'Place main domain/pms db connections/connection.php within the document root or alongside this script.',
    'searched' => $connCandidates
  ]);
  exit;
}

// Read JSON body
$raw = file_get_contents('php://input') ?: '{}';
$data = json_decode($raw, true) ?: [];

$companyCode = isset($data['code']) ? trim((string)$data['code']) : 'VIBE';
$checkIn     = isset($data['checkIn']) ? (string)$data['checkIn'] : date('Y-m-d');
$nights      = isset($data['nights']) ? max(1, (int)$data['nights']) : 2;
$people      = isset($data['people']) ? max(1, (int)$data['people']) : 2;

try {
  $pdo = createDatabaseConnection();

  $stmt = $pdo->prepare('CALL sp_search_availability(:company_code, :check_in, :nights, :people)');
  $stmt->bindValue(':company_code', $companyCode, PDO::PARAM_STR);
  $stmt->bindValue(':check_in',     $checkIn,     PDO::PARAM_STR);
  $stmt->bindValue(':nights',       $nights,      PDO::PARAM_INT);
  $stmt->bindValue(':people',       $people,      PDO::PARAM_INT);
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $stmt->closeCursor();

  // Map to UI format (one suggestion per category)
  $results = [];
  $asCurrency = static function ($value): ?float {
    if ($value === null || $value === '' || !is_numeric($value)) {
      return null;
    }
    return round(((float)$value) / 100, 2);
  };

  foreach ($rows as $r) {
    $priceBase = $asCurrency($r['default_base_price_cents'] ?? null);
    $priceMin  = $asCurrency($r['default_floor_cents'] ?? null);
    $priceMax  = $asCurrency($r['default_ceil_cents'] ?? null);
    $img       = $r['image_url'] ?? null;

    $results[] = [
      'lodgingId'     => (string)($r['property_code'] ?? $r['id_property'] ?? ''),
      'lodgingName'   => (string)($r['property_name'] ?? ''),
      'roomTypeId'    => (string)($r['category_code'] ?? $r['id_category'] ?? ''),
      'roomTypeName'  => (string)($r['category_name'] ?? ''),
      'capacity'      => isset($r['max_occupancy']) ? (int)$r['max_occupancy'] : null,
      'pricePerNight' => $priceBase ?? $priceMin ?? $priceMax,
      'priceMin'      => $priceMin,
      'priceMax'      => $priceMax,
      'currency'      => (string)($r['currency'] ?? 'MXN'),
      'availableRooms'=> isset($r['available_rooms']) ? (int)$r['available_rooms'] : null,
      'images'        => $img ? [$img] : [],
    ];
  }

  echo json_encode(['results' => $results], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Availability search failed', 'details' => $e->getMessage()]);
}
