<?php
// api.php – reads and saves the video calendar data in src/data.csv
declare(strict_types=1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

const DATA_FILE = __DIR__ . '/src/data.csv';
const TOTAL_DOORS = 24;
const CSV_HEADER = ['day', 'title', 'url', 'thumbnail', 'opened_date', 'rating', 'comment'];
const CSV_SEPARATOR = ';';

// The API is protected too: without a valid login session it returns 401
// so client-side JavaScript gets a clear error instead of the login page.
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// Reads src/data.csv and returns one entry per door (1-24).
// A door counts as opened when its opened_date is not empty.
function loadDoors(): array {
    $doors = [];
    for ($i = 1; $i <= TOTAL_DOORS; $i++) {
        $doors[$i] = [
            'day'         => (string)$i,
            'title'       => 'Video ' . $i,
            'url'         => 'https://example.com/video' . $i,
            'thumbnail'   => sprintf('src/thumbnails/%02d.png', $i),
            'opened_date' => '',
            'rating'      => '',
            'comment'     => '',
        ];
    }

    $handle = fopen(DATA_FILE, 'r');
    if ($handle) {
        fgetcsv($handle, null, CSV_SEPARATOR); // skip header row
        while (($row = fgetcsv($handle, null, CSV_SEPARATOR)) !== false) {
            if (count($row) < 6) {
                continue;
            }
            $day = (int)$row[0];
            if ($day < 1 || $day > TOTAL_DOORS) {
                continue;
            }
            $doors[$day] = [
                'day'         => (string)$day,
                'title'       => trim((string)$row[1]),
                'url'         => trim((string)$row[2]),
                'thumbnail'   => trim((string)$row[3]),
                'opened_date' => trim((string)$row[4]),
                'rating'      => trim((string)$row[5]),
                'comment'     => trim((string)($row[6] ?? '')),
            ];
        }
        fclose($handle);
    }

    ksort($doors);
    return array_values($doors);
}

// Writes all doors back to src/data.csv. LOCK_EX protects against simultaneous writes.
function saveDoors(array $doors): void {
    $handle = fopen(DATA_FILE, 'c');
    if (!$handle) {
        throw new RuntimeException('cannot open ' . DATA_FILE);
    }
    flock($handle, LOCK_EX);
    fputcsv($handle, CSV_HEADER, CSV_SEPARATOR);
    foreach ($doors as $door) {
        fputcsv($handle, [
            $door['day'],
            $door['title'],
            $door['url'],
            $door['thumbnail'],
            $door['opened_date'],
            $door['rating'],
            $door['comment'] ?? '',
        ], CSV_SEPARATOR);
    }
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    echo json_encode(['doors' => loadDoors()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $updates = $input['doors'] ?? null;

    if (!is_array($updates)) {
        http_response_code(422);
        echo json_encode(['error' => 'doors array is required']);
        exit;
    }

    // Video metadata (title/url/thumbnail) stays authoritative in src/data.csv;
    // only opened_date, rating and comment are updated from the client.
    $doors = loadDoors();
    $indexByDay = [];
    foreach ($doors as $i => $door) {
        $indexByDay[$door['day']] = $i;
    }

    foreach ($updates as $u) {
        if (!is_array($u)) {
            continue;
        }
        $day = (string)(int)($u['day'] ?? 0);
        if (!isset($indexByDay[$day])) {
            continue;
        }
        $i = $indexByDay[$day];
        $doors[$i]['opened_date'] = trim((string)($u['opened_date'] ?? $doors[$i]['opened_date']));
        $rating = $u['rating'] ?? $doors[$i]['rating'];
        if (is_int($rating)) {
            $rating = (string)$rating;
        }
        $doors[$i]['rating'] = is_string($rating) ? trim($rating) : '';
        $comment = $u['comment'] ?? $doors[$i]['comment'];
        $doors[$i]['comment'] = is_string($comment) ? trim($comment) : '';
    }

    saveDoors($doors);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
