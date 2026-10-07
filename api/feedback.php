<?php
require_once __DIR__ . '/_bootstrap.php';
$rid    = auth_resident($conn);
$method = $_SERVER['REQUEST_METHOD'] ?? '';

const REPORT_TYPES = [
    'Missed collection',
    'Illegal dumping',
    'Overflowing bin',
    'Truck concern',
    'Suggestion',
    'Other',
];

if ($method === 'POST') {
    $in   = body();
    $type = (string) ($in['report_type'] ?? '');
    $desc = clean_str($in['description'] ?? '', 1000);
    $lat  = $in['latitude']  ?? null;
    $lng  = $in['longitude'] ?? null;

    if (!in_array($type, REPORT_TYPES, true)) {
        json_out(['error' => 'Choose a report type.'], 422);
    }
    if (mb_strlen($desc) < 5) {
        json_out(['error' => 'Describe the problem in a few words.'], 422);
    }

    if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)
        || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        $lat = null;
        $lng = null;
    } else {
        $lat = (float) $lat;
        $lng = (float) $lng;
    }

    $stmt = $conn->prepare(
        'INSERT INTO feedback_report (resident_id, report_type, description, latitude, longitude)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issdd', $rid, $type, $desc, $lat, $lng);
    $stmt->execute();

    json_out(['ok' => true, 'feedback_report_id' => (int) $conn->insert_id], 201);
}

if ($method === 'GET') {
    $stmt = $conn->prepare(
        'SELECT feedback_report_id, report_type, description, status, latitude, longitude, created_at
         FROM feedback_report
         WHERE resident_id = ?
         ORDER BY feedback_report_id DESC
         LIMIT 50'
    );
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    json_out([
        'types'   => REPORT_TYPES,
        'reports' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
    ]);
}

json_out(['error' => 'Method not allowed.'], 405);
