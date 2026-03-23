<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/ota_ical.php';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
if ($token === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing token';
    exit;
}

try {
    $db = pms_get_connection();
    $feed = pms_ota_ical_fetch_feed_by_token($db, $token);
    if (!$feed) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Feed not found';
        exit;
    }

    $ics = pms_ota_ical_render_export($db, $feed);
    $feedName = isset($feed['feed_name']) ? trim((string)$feed['feed_name']) : '';
    if ($feedName === '') {
        $feedName = 'calendar';
    }
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $feedName);
    if ($safeName === '') {
        $safeName = 'calendar';
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $safeName . '.ics"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $ics;
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Export error: ' . $e->getMessage();
}
