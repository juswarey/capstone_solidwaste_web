<?php
// The collector's assigned route (zone) and truck, as set by the admins on the web.
require_once __DIR__ . '/../_bootstrap.php';
require_method('GET');
$cid = auth_collector($conn);

$stmt = $conn->prepare(
    'SELECT gc.route_id, gc.truck_id,
            r.zone_name, r.collection_days, r.start_time, r.end_time, r.path_coordinates,
            t.plate_number, t.max_capacity_kg,
            ba.firstname AS admin_first, ba.lastname AS admin_last
     FROM garbage_collector gc
     LEFT JOIN route r ON r.route_id = gc.route_id
     LEFT JOIN truck t ON t.truck_id = gc.truck_id
     LEFT JOIN barangay_admin ba ON ba.barangay_admin_id = r.barangay_admin_id
     WHERE gc.garbage_collector_id = ? LIMIT 1'
);
$stmt->bind_param('i', $cid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: [];

$route = null;
if (!empty($row['route_id'])) {
    $route = [
        'zone_name'       => $row['zone_name'],
        'collection_days' => $row['collection_days'],
        'start_time'      => $row['start_time'],
        'end_time'        => $row['end_time'],
        'assigned_by'     => trim(($row['admin_first'] ?? '') . ' ' . ($row['admin_last'] ?? '')),
    ];
}

$truck = null;
if (!empty($row['truck_id'])) {
    $truck = [
        'plate_number'    => $row['plate_number'],
        'max_capacity_kg' => $row['max_capacity_kg'] !== null ? (float) $row['max_capacity_kg'] : null,
    ];
}

json_out([
    'route'      => $route,
    'truck'      => $truck,
    'route_path' => parse_path($row['path_coordinates'] ?? ''),
]);
