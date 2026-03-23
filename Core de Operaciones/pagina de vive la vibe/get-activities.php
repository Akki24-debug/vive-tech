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

$companyCode = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($companyCode === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing company code.']);
    exit;
}

$documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR);
$imagesBasePath = $documentRoot !== ''
    ? $documentRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'activities'
    : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'activities';
$cdnDomain = 'vivelavibe.pxm.com.mx';
$imagesBaseUrl = 'https://' . $cdnDomain . '/images/activities';
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

function formatDurationLabel(?int $minutes): ?string
{
    if ($minutes === null || $minutes <= 0) {
        return null;
    }
    if ($minutes % 60 === 0) {
        $hours = (int)($minutes / 60);
        return $hours === 1 ? '1 hora' : "{$hours} horas";
    }
    if ($minutes < 60) {
        return "{$minutes} minutos";
    }
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours === 1 ? '1 hora' : "{$hours} horas";
    }
    if ($remaining > 0) {
        $parts[] = "{$remaining} minutos";
    }
    return implode(' ', $parts);
}

try {
    $pdo = createDatabaseConnection();
    $stmt = $pdo->prepare('CALL sp_get_company_activities(:company_code)');
    $stmt->bindValue(':company_code', $companyCode, PDO::PARAM_STR);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $stmt->closeCursor();

    $activities = [];
    foreach ($rows as $row) {
        $priceCents = isset($row['base_price_cents']) ? (int)$row['base_price_cents'] : null;
        $price = $priceCents !== null ? $priceCents / 100 : null;
        $duration = isset($row['duration_minutes']) ? (int)$row['duration_minutes'] : null;
        $activityCode = trim((string)($row['activity_code'] ?? ''));

        $gallery = [];
        if ($activityCode !== '') {
            $directory = $joinPath($imagesBasePath, [$activityCode]);
            $gallery = $collectImages($directory, $joinUrl($imagesBaseUrl, [$activityCode]));
        }
        $dbImage = isset($row['image_url']) ? (string)$row['image_url'] : '';
        if (!count($gallery) && $dbImage !== '') {
            $gallery[] = $dbImage;
        }

        $activities[] = [
            'id' => (string)($row['activity_code'] ?? $row['id_activity'] ?? ''),
            'code' => $activityCode !== '' ? $activityCode : null,
            'name' => $row['activity_name'] ?? null,
            'type' => $row['activity_type'] ?? null,
            'description' => $row['description'] ?? null,
            'durationMinutes' => $duration,
            'durationLabel' => formatDurationLabel($duration),
            'pricePerPerson' => $price,
            'currency' => $row['currency'] ?? 'MXN',
            'capacityDefault' => isset($row['capacity_default']) ? (int)$row['capacity_default'] : null,
            'location' => $row['location'] ?? null,
            'property' => [
                'id' => isset($row['id_property']) ? (int)$row['id_property'] : null,
                'code' => $row['property_code'] ?? null,
                'name' => $row['property_name'] ?? null,
            ],
            'updatedAt' => $row['updated_at'] ?? null,
            'images' => $gallery,
        ];
    }

    echo json_encode(['activities' => $activities], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load activities.',
        'details' => $e->getMessage(),
    ]);
}
