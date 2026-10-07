<?php
// POST: the app sends the truck's GPS position (saved to location_history, which
//       residents see on their Truck map).
// GET : the collector's last few pings.
require_once __DIR__ . '/../_bootstrap.php';
$cid    = auth_collector($conn);
$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'POST') {
    $in  = body();
    $lat = $in['latitude']  ?? null;
    $lng = $in['longitude'] ?? null;

    if (!is_numeric($lat) || !is_numeric($lng)
        || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        json_out(['error' => 'Invalid location.'], 422);
    }
    $lat = (float) $lat;
    $lng = (float) $lng;

    $stmt = $conn->prepare(
        'INSERT INTO location_history (garbage_collector_id, latitude, longitude) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('idd', $cid, $lat, $lng);
    $stmt->execute();
    json_out(['ok' => true], 201);
}

if ($method === 'GET') {
    $stmt = $conn->prepare(
        'SELECT location_history_id, latitude, longitude, `timestamp`
         FROM location_history
         WHERE garbage_collector_id = ?
         ORDER BY location_history_id DESC
         LIMIT 10'
    );
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    json_out($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

json_out(['error' => 'Method not allowed.'], 405);
