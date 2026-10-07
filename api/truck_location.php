<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');
$rid     = auth_resident($conn);
$routeId = resident_route_id($conn, $rid);

if ($routeId === null) {
    json_out(['zone_name' => null, 'route_path' => [], 'trucks' => []]);
}

$stmt = $conn->prepare('SELECT zone_name, path_coordinates FROM route WHERE route_id = ?');
$stmt->bind_param('i', $routeId);
$stmt->execute();
$route = $stmt->get_result()->fetch_assoc();

// Latest ping of every collector assigned to this zone
// (either set on their account or via an active schedule row).
$stmt = $conn->prepare(
    "SELECT gc.garbage_collector_id, gc.first_name, t.plate_number,
            lh.latitude, lh.longitude, lh.timestamp,
            TIMESTAMPDIFF(MINUTE, lh.timestamp, NOW()) AS minutes_ago
     FROM garbage_collector gc
     LEFT JOIN truck t ON t.truck_id = gc.truck_id
     JOIN location_history lh
       ON lh.location_history_id = (
            SELECT MAX(l2.location_history_id)
            FROM location_history l2
            WHERE l2.garbage_collector_id = gc.garbage_collector_id
          )
     WHERE gc.route_id = ?
        OR gc.garbage_collector_id IN (
             SELECT garbage_collector_id FROM collection_schedule
             WHERE route_id = ? AND schedule_status = 'Active'
                   AND garbage_collector_id IS NOT NULL
           )"
);
$stmt->bind_param('ii', $routeId, $routeId);
$stmt->execute();
$trucks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($trucks as &$t) {
    $t['latitude']    = (float) $t['latitude'];
    $t['longitude']   = (float) $t['longitude'];
    $t['minutes_ago'] = (int) $t['minutes_ago'];
}
unset($t);

json_out([
    'zone_name'  => $route['zone_name'],
    'route_path' => parse_path($route['path_coordinates']),
    'trucks'     => $trucks,
]);
