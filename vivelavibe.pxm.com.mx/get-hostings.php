<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$connCandidates = [
    '/home/u508158532/domains/pxm.com.mx/public_html/pms db connections/connection.php',
];
foreach ($connCandidates as $candidate) {
    if ($candidate && is_file($candidate)) {
        require_once $candidate;
        break;
    }
}
if (!function_exists('createDatabaseConnection')) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Missing database connection include.',
        'details' => 'Could not locate main domain/pms db connections/connection.php. Place the folder alongside this script or within the document root.',
        'searched' => $connCandidates,
    ]);
    exit;
}

$companyCode = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
if ($companyCode === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing company code.']);
    exit;
}

$documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR);
$imagesBasePath = $documentRoot !== ''
    ? $documentRoot . DIRECTORY_SEPARATOR . 'images'
    : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? $scheme . '://' . $host : '';
$imagesBaseUrl = $baseUrl . '/images';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg'];

$joinPath = static function (string $base, array $segments): string {
    $path = rtrim($base, DIRECTORY_SEPARATOR);
    foreach ($segments as $segment) {
        if ($segment === null || $segment === '') {
            continue;
        }
        $path .= DIRECTORY_SEPARATOR . $segment;
    }
    return $path;
};

$joinUrl = static function (string $base, array $segments): string {
    $url = rtrim($base, '/');
    foreach ($segments as $segment) {
        if ($segment === null || $segment === '') {
            continue;
        }
        $url .= '/' . rawurlencode($segment);
    }
    return $url;
};

$collectImages = static function (string $directory, string $urlPrefix) use ($allowedExtensions): array {
    if (!is_dir($directory)) {
        return [];
    }
    $entries = scandir($directory);
    if ($entries === false) {
        return [];
    }
    $images = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $directory . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($fullPath)) {
            continue;
        }
        $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }
        $images[] = rtrim($urlPrefix, '/') . '/' . rawurlencode($entry);
    }
    sort($images);
    return $images;
};

try {
    $pdo = createDatabaseConnection();
    $stmt = $pdo->prepare('CALL sp_get_company_properties(:company_code)');
    $stmt->bindValue(':company_code', $companyCode, PDO::PARAM_STR);
    $stmt->execute();

    $propertiesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $roomsRaw = [];
    if ($stmt->nextRowset()) {
        $roomsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    $stmt->closeCursor();

    $propertyImages = [];
    foreach ($propertiesRaw as $row) {
        $propertyCode = trim((string) ($row['property_code'] ?? ''));
        if ($propertyCode === '' || isset($propertyImages[$propertyCode])) {
            continue;
        }
        $directory = $joinPath($imagesBasePath, [$propertyCode]);
        $urlPrefix = $joinUrl($imagesBaseUrl, [$propertyCode]);
        $propertyImages[$propertyCode] = $collectImages($directory, $urlPrefix);
    }

    $roomImages = [];
    $roomsByProperty = [];
    foreach ($roomsRaw as $roomRow) {
        $propertyId = (int) ($roomRow['id_property'] ?? 0);
        if ($propertyId <= 0) {
            continue;
        }
        $propertyCode = trim((string) ($roomRow['property_code'] ?? ''));
        $categoryCode = trim((string) ($roomRow['category_code'] ?? ''));
        $roomKey = $propertyCode !== '' && $categoryCode !== '' ? $propertyCode . '::' . $categoryCode : null;
        if ($roomKey && !isset($roomImages[$roomKey])) {
            $directory = $joinPath($imagesBasePath, [$propertyCode, $categoryCode]);
            $urlPrefix = $joinUrl($imagesBaseUrl, [$propertyCode, $categoryCode]);
            $roomImages[$roomKey] = $collectImages($directory, $urlPrefix);
        }

        $roomsByProperty[$propertyId][] = [
            'id' => (string) ($categoryCode !== '' ? $categoryCode : ($roomRow['id_category'] ?? '')),
            'code' => $categoryCode,
            'propertyCode' => $propertyCode,
            'name' => (string) ($roomRow['category_name'] ?? ''),
            'capacity' => isset($roomRow['max_occupancy']) ? (int) $roomRow['max_occupancy'] : null,
            'minRate' => isset($roomRow['default_floor_cents']) ? ((int) $roomRow['default_floor_cents']) / 100 : null,
            'maxRate' => isset($roomRow['default_ceil_cents']) ? ((int) $roomRow['default_ceil_cents']) / 100 : null,
            'description' => $roomRow['description'] ?? null,
            'dbImage' => $roomRow['image_url'] ?? null,
        ];
    }

    $lodgings = [];
    foreach ($propertiesRaw as $row) {
        $propertyId = (int) ($row['id_property'] ?? 0);
        $propertyCode = trim((string) ($row['property_code'] ?? ''));
        $rooms = $roomsByProperty[$propertyId] ?? [];
        $lobbyImages = $propertyImages[$propertyCode] ?? [];

        $roomTypes = [];
        foreach ($rooms as $room) {
            $roomKey = ($room['propertyCode'] ?? '') !== '' && ($room['code'] ?? '') !== ''
                ? $room['propertyCode'] . '::' . $room['code']
                : null;
            $gallery = $roomKey && isset($roomImages[$roomKey]) ? $roomImages[$roomKey] : [];
            if (!count($gallery) && !empty($room['dbImage'])) {
                $gallery[] = $room['dbImage'];
            }
            $gallery = array_values(array_unique(array_filter($gallery)));
            $price = $room['minRate'] ?? $room['maxRate'];

            $roomTypes[] = [
                'id' => $room['id'],
                'code' => $room['code'] ?? null,
                'name' => $room['name'],
                'capacity' => $room['capacity'],
                'pricePerNight' => $price,
                'priceRange' => [
                    'min' => $room['minRate'],
                    'max' => $room['maxRate'],
                ],
                'images' => $gallery,
                'description' => $room['description'],
            ];
        }

        $combinedImages = $lobbyImages;
        foreach ($roomTypes as $type) {
            if (!empty($type['images'])) {
                $combinedImages = array_merge($combinedImages, $type['images']);
            }
        }
        $combinedImages = array_values(array_unique($combinedImages));

        $lodgings[] = [
            'id' => (string) $propertyCode,
            'code' => (string) $propertyCode,
            'name' => (string) ($row['property_name'] ?? ''),
            'description' => $row['description'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'country' => $row['country'] ?? null,
            'currency' => $row['currency'] ?? null,
            'lobbyImages' => $lobbyImages,
            'images' => $combinedImages,
            'roomTypes' => $roomTypes,
        ];
    }

    echo json_encode(['lodgings' => $lodgings], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load hostings.',
        'details' => $e->getMessage(),
    ]);
}
