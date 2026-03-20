<?php
require __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('guest_search_normalize_text')) {
    function guest_search_normalize_text($value)
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }
        $text = strtolower($text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false && $converted !== '') {
                $text = strtolower((string)$converted);
            }
        }
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string)$text);
    }
}

if (!function_exists('guest_search_stripos')) {
    function guest_search_stripos($haystack, $needle)
    {
        $h = (string)$haystack;
        $n = (string)$needle;
        if ($h === '' || $n === '') {
            return false;
        }
        if (function_exists('mb_stripos')) {
            return mb_stripos($h, $n, 0, 'UTF-8');
        }
        return stripos($h, $n);
    }
}

if (!function_exists('guest_search_score')) {
    function guest_search_score(array $row, $qText, $qDigits)
    {
        $names = guest_search_normalize_text(isset($row['names']) ? $row['names'] : '');
        $last = guest_search_normalize_text(isset($row['last_name']) ? $row['last_name'] : '');
        $email = guest_search_normalize_text(isset($row['email']) ? $row['email'] : '');
        $phoneRaw = trim((string)(isset($row['phone']) ? $row['phone'] : ''));
        $phoneDigits = preg_replace('/\D+/', '', $phoneRaw);
        $full = trim($names . ' ' . $last);
        $score = 100000;

        if ($qText !== '') {
            $prefixFields = array($names, $full, $last, $email);
            foreach ($prefixFields as $idx => $field) {
                if ($field === '') {
                    continue;
                }
                if (strpos($field, $qText) === 0) {
                    $lengthPenalty = max(0, strlen($field) - strlen($qText));
                    $score = min($score, 10 + ($idx * 10) + $lengthPenalty);
                }
            }

            $containsFields = array($names, $full, $last, $email);
            foreach ($containsFields as $idx => $field) {
                if ($field === '') {
                    continue;
                }
                $pos = guest_search_stripos($field, $qText);
                if ($pos !== false) {
                    $score = min($score, 120 + ($idx * 30) + ((int)$pos * 2));
                }
            }
        }

        if ($qDigits !== '' && strlen($qDigits) >= 2 && $phoneDigits !== '') {
            $startsAt = strpos($phoneDigits, $qDigits);
            if ($startsAt !== false) {
                $score = min($score, 80 + ((int)$startsAt * 3));
            }
        }

        return $score;
    }
}

$user = pms_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(array('error' => 'unauthorized'));
    exit;
}
$canGuestSearch = pms_user_can('guests.view')
    || pms_user_can('reservations.create')
    || pms_user_can('reservations.edit');
if (!$canGuestSearch) {
    http_response_code(403);
    echo json_encode(array('error' => 'forbidden'));
    exit;
}

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($query === '' || strlen($query) < 2) {
    echo json_encode(array('results' => array()));
    exit;
}

try {
    $sets = pms_call_procedure('sp_portal_guest_data', array(
        $user['company_code'],
        $query,
        1,
        0
    ));
    $rows = isset($sets[0]) ? $sets[0] : array();
    $qText = guest_search_normalize_text($query);
    $qDigits = preg_replace('/\D+/', '', $query);
    $scored = array();
    $seenIds = array();
    foreach ($rows as $row) {
        $idGuest = isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
        if ($idGuest <= 0 || isset($seenIds[$idGuest])) {
            continue;
        }
        $seenIds[$idGuest] = true;
        $scored[] = array(
            'id_guest' => $idGuest,
            'names' => isset($row['names']) ? (string)$row['names'] : '',
            'last_name' => isset($row['last_name']) ? (string)$row['last_name'] : '',
            'email' => isset($row['email']) ? (string)$row['email'] : '',
            'phone' => isset($row['phone']) ? (string)$row['phone'] : '',
            '_score' => guest_search_score($row, $qText, $qDigits)
        );
    }

    usort($scored, function ($a, $b) {
        $sa = isset($a['_score']) ? (int)$a['_score'] : 100000;
        $sb = isset($b['_score']) ? (int)$b['_score'] : 100000;
        if ($sa !== $sb) {
            return $sa < $sb ? -1 : 1;
        }
        $na = strtolower(trim((string)(isset($a['names']) ? $a['names'] : '')));
        $nb = strtolower(trim((string)(isset($b['names']) ? $b['names'] : '')));
        if ($na !== $nb) {
            return strcmp($na, $nb);
        }
        $la = strtolower(trim((string)(isset($a['last_name']) ? $a['last_name'] : '')));
        $lb = strtolower(trim((string)(isset($b['last_name']) ? $b['last_name'] : '')));
        if ($la !== $lb) {
            return strcmp($la, $lb);
        }
        $ea = strtolower(trim((string)(isset($a['email']) ? $a['email'] : '')));
        $eb = strtolower(trim((string)(isset($b['email']) ? $b['email'] : '')));
        return strcmp($ea, $eb);
    });

    $results = array();
    foreach ($scored as $item) {
        if (count($results) >= 12) {
            break;
        }
        unset($item['_score']);
        $results[] = $item;
    }

    echo json_encode(array('results' => $results));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}
