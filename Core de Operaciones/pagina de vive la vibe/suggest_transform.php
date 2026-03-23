<?php
// suggest_transform.php
// Transforma { results: [...], people: N } -> { combos, bands, bestCombination }
header('Content-Type: application/json; charset=utf-8');

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) throw new Exception('Invalid JSON body');
  $results = $input['results'] ?? [];
  $people  = isset($input['people']) ? intval($input['people']) : 1;
  if ($people < 1) $people = 1;

  // -------- Helpers --------
  function roomImagesFromDisk($lodgingId, $roomTypeId) {
    if (!$lodgingId || !$roomTypeId) return [];
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $lodgingId . DIRECTORY_SEPARATOR . $roomTypeId;
    if (!is_dir($baseDir)) return [];
    $pattern = $baseDir . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}';
    $files = glob($pattern, GLOB_BRACE) ?: [];
    $relative = [];
    foreach ($files as $file) {
      $normalized = str_replace('\\', '/', $file);
      $root = str_replace('\\', '/', __DIR__ . '/');
      if (strpos($normalized, $root) === 0) {
        $normalized = substr($normalized, strlen($root));
      }
      $relative[] = $normalized;
    }
    return $relative;
  }

  $safeNumber = function($v) {
    if (is_int($v) || is_float($v)) return is_finite($v) ? $v : null;
    if (is_string($v) && trim($v) !== '') {
      if (is_numeric($v)) return floatval($v);
    }
    return null;
  };

  // Group results by lodgingId
  $lodgingsMap = []; // lodgingId => [ 'lodgingId', 'lodgingName', 'rooms' => [instances...] ]
  foreach ($results as $row) {
    $lodgingId   = $row['lodgingId']   ?? null;
    $lodgingName = $row['lodgingName'] ?? ($lodgingId ?? 'Hospedaje');
    if (!$lodgingId) continue;

    $roomTypeId   = $row['roomTypeId']   ?? null;
    $roomTypeName = $row['roomTypeName'] ?? ($roomTypeId ?? 'Habitación');
    $capacity     = $safeNumber($row['capacity']) ?? 0;
    $price        = $safeNumber($row['pricePerNight']) ?? null;
    $priceMin     = $safeNumber($row['priceMin']);
    $priceMax     = $safeNumber($row['priceMax']);
    $currency     = $row['currency'] ?? 'MXN';
    $available    = intval($row['availableRooms'] ?? 1);
    if ($available < 0) $available = 0;
    $images       = isset($row['images']) && is_array($row['images']) ? $row['images'] : [];
    $images       = array_merge($images, roomImagesFromDisk($lodgingId, $roomTypeId));

    if (!$price && ($priceMin || $priceMax)) {
      $price = $priceMin ?? $priceMax; // fallback simple si no hay pricePerNight
    }

    if ($capacity <= 0 || !$price || $price <= 0 || $available === 0) {
      // inválido o sin disponibilidad
      continue;
    }

    if (!isset($lodgingsMap[$lodgingId])) {
      $lodgingsMap[$lodgingId] = [
        'lodgingId' => $lodgingId,
        'lodgingName' => $lodgingName,
        'images' => [], // no tenemos imágenes a nivel lodging
        'rooms' => []
      ];
    }

    // Duplicar "instancias" de habitación por availableRooms
    for ($i = 0; $i < $available; $i++) {
      $lodgingsMap[$lodgingId]['rooms'][] = [
        'lodgingId' => $lodgingId,
        'lodgingName' => $lodgingName,
        'roomTypeId' => $roomTypeId,
        'roomTypeName' => $roomTypeName,
        'capacity' => $capacity,
        'pricePerNight' => $price,
        'currency' => $currency,
        'images' => $images,
        'description' => '',
        'location' => null,
        'minOccupancy' => null
      ];
    }
  }

  $lodgings = array_values($lodgingsMap);

  // Max capacity por lodging
  $computeMaxCap = function($lodging) use ($safeNumber) {
    $sum = 0;
    foreach (($lodging['rooms'] ?? []) as $r) {
      $sum += $safeNumber($r['capacity']) ?? 0;
    }
    return $sum;
  };

  // Filtrar por cupo suficiente
  $candidates = array_values(array_filter($lodgings, function($l) use ($computeMaxCap, $people) {
    return $computeMaxCap($l) >= $people;
  }));

  // Ordenar rooms por capacidad asc, luego precio asc
  $getSortedRooms = function($lodging) use ($safeNumber) {
    $rooms = array_values(array_filter($lodging['rooms'] ?? [], function($r) use ($safeNumber) {
      $cap = $safeNumber($r['capacity']) ?? 0;
      $pr  = $safeNumber($r['pricePerNight']) ?? 0;
      return $cap > 0 && $pr > 0;
    }));
    usort($rooms, function($a, $b) use ($safeNumber) {
      $ca = $safeNumber($a['capacity']) ?? 0;
      $cb = $safeNumber($b['capacity']) ?? 0;
      if ($ca !== $cb) return $ca <=> $cb;
      $pa = $safeNumber($a['pricePerNight']) ?? 0;
      $pb = $safeNumber($b['pricePerNight']) ?? 0;
      return $pa <=> $pb;
    });
    return $rooms;
  };

  // Backtracking: primera combinación de tamaño k que cubra target
  $findFirstComboK = function($rooms, $k, $target) use (&$findFirstComboK, $safeNumber) {
    $n = count($rooms);
    $comboIdx = [];

    $backtrack = function($start, $left, $capSum, $priceSum) use (&$backtrack, $rooms, $n, $target, &$comboIdx, $safeNumber) {
      if ($left === 0) {
        if ($capSum >= $target) {
          // construir respuesta
          $picked = [];
          foreach ($comboIdx as $idx) $picked[] = $rooms[$idx];
          return [
            'capSum' => $capSum,
            'priceSum' => $priceSum,
            'rooms' => $picked
          ];
        }
        return null;
      }
      for ($i = $start; $i <= $n - $left; $i++) {
        $r = $rooms[$i];
        $comboIdx[] = $i;
        $res = $backtrack(
          $i + 1,
          $left - 1,
          $capSum + ($safeNumber($r['capacity']) ?? 0),
          $priceSum + ($safeNumber($r['pricePerNight']) ?? 0)
        );
        if ($res) return $res; // primera válida
        array_pop($comboIdx);
      }
      return null;
    };

    return $backtrack(0, $k, 0, 0);
  };

  // Agrupar instancias iguales por roomTypeId para salida {count, subtotal}
  $groupPickedRooms = function($picked) use ($safeNumber) {
    $map = []; // key = roomTypeId
    foreach ($picked as $r) {
      $key = $r['roomTypeId'] ?? (uniqid('room_', true));
      if (!isset($map[$key])) {
        $map[$key] = [
          'lodgingId' => $r['lodgingId'],
          'lodgingName' => $r['lodgingName'],
          'roomTypeId' => $r['roomTypeId'],
          'roomTypeName' => $r['roomTypeName'],
          'count' => 0,
          'capacity' => $safeNumber($r['capacity']) ?? 0,
          'totalCapacity' => 0,
          'pricePerNight' => $safeNumber($r['pricePerNight']) ?? 0,
          'subtotalPerNight' => 0,
          'images' => $r['images'] ?? [],
          'currency' => $r['currency'] ?? 'MXN',
          'description' => $r['description'] ?? '',
          'location' => $r['location'] ?? null,
          'minOccupancy' => $r['minOccupancy'] ?? null
        ];
      }
      $map[$key]['count'] += 1;
      $map[$key]['totalCapacity'] += ($safeNumber($r['capacity']) ?? 0);
      $map[$key]['subtotalPerNight'] += ($safeNumber($r['pricePerNight']) ?? 0);
    }
    return array_values($map);
  };

  // Buscar primera combinación por lodging (1 habitación, luego 2, 3, ...)
  $findComboForLodging = function($lodging, $people) use ($getSortedRooms, $findFirstComboK, $groupPickedRooms, $safeNumber) {
    $rooms = $getSortedRooms($lodging);
    $n = count($rooms);
    if ($n === 0) return null;
    $target = max(intval($people), 1);

    // 1 habitación
    for ($i = 0; $i < $n; $i++) {
      $cap = $safeNumber($rooms[$i]['capacity']) ?? 0;
      if ($cap >= $target) {
        $price = $safeNumber($rooms[$i]['pricePerNight']) ?? 0;
        return [
          'lodgingId' => $lodging['lodgingId'],
          'lodgingName' => $lodging['lodgingName'],
          'roomsCount' => 1,
          'totalCapacity' => $cap,
          'totalPerNight' => $price,
          'rooms' => $groupPickedRooms([$rooms[$i]]),
          'images' => $lodging['images'] ?? []
        ];
      }
    }

    // combinaciones k=2..n
    for ($k = 2; $k <= $n; $k++) {
      $found = $findFirstComboK($rooms, $k, $target);
      if ($found) {
        return [
          'lodgingId' => $lodging['lodgingId'],
          'lodgingName' => $lodging['lodgingName'],
          'roomsCount' => $k,
          'totalCapacity' => $found['capSum'],
          'totalPerNight' => $found['priceSum'],
          'rooms' => $groupPickedRooms($found['rooms']),
          'images' => $lodging['images'] ?? []
        ];
      }
    }
    return null;
  };

  // Para todos los hospedajes candidatos
  $combos = [];
  foreach ($candidates as $lodging) {
    $combo = $findComboForLodging($lodging, $people);
    if ($combo) $combos[] = $combo;
  }

  // Bands friendly/premium/standard
  $computeBands = function($combos) use ($safeNumber) {
    if (!count($combos)) {
      return [ 'friendly' => null, 'premium' => null, 'standard' => null, 'min' => null, 'max' => null ];
    }
    usort($combos, function($a, $b) use ($safeNumber) {
      $pa = $safeNumber($a['totalPerNight']) ?? INF;
      $pb = $safeNumber($b['totalPerNight']) ?? INF;
      return $pa <=> $pb;
    });
    $minC = $combos[0];
    $maxC = $combos[count($combos)-1];
    $minP = $safeNumber($minC['totalPerNight']) ?? 0;
    $maxP = $safeNumber($maxC['totalPerNight']) ?? 0;
    $mid  = ($minP + $maxP) / 2.0;
    $std  = round($mid / 100) * 100;

    return [
      'friendly' => [ 'price' => $minP, 'lodgingId' => $minC['lodgingId'], 'lodgingName' => $minC['lodgingName'] ],
      'premium'  => [ 'price' => $maxP, 'lodgingId' => $maxC['lodgingId'], 'lodgingName' => $maxC['lodgingName'] ],
      'standard' => [ 'price' => $std ],
      'min' => $minP,
      'max' => $maxP
    ];
  };

  $bands = $computeBands($combos);

  // bestCombination: más barato, desempata por menos rooms y menor overshoot
  $best = null;
  foreach ($combos as $c) {
    if ($best === null) { $best = $c; continue; }
    $a = $safeNumber($c['totalPerNight']) ?? INF;
    $b = $safeNumber($best['totalPerNight']) ?? INF;
    if ($a !== $b) { if ($a < $b) $best = $c; continue; }
    if ($c['roomsCount'] !== $best['roomsCount']) { if ($c['roomsCount'] < $best['roomsCount']) $best = $c; continue; }
    $oa = max(0, ($safeNumber($c['totalCapacity']) ?? 0) - $people);
    $ob = max(0, ($safeNumber($best['totalCapacity']) ?? 0) - $people);
    if ($oa < $ob) $best = $c;
  }

  echo json_encode([
    'combos' => $combos,
    'bands' => $bands,
    'bestCombination' => $best
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([ 'error' => $e->getMessage() ]);
}
