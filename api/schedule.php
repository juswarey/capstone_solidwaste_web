<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');
$rid     = auth_resident($conn);
$routeId = resident_route_id($conn, $rid);

if ($routeId === null) {
    json_out(['zone' => null, 'schedules' => []]);
}

$stmt = $conn->prepare(
    'SELECT route_id, zone_name, collection_days, start_time, end_time FROM route WHERE route_id = ?'
);
$stmt->bind_param('i', $routeId);
$stmt->execute();
$zone = $stmt->get_result()->fetch_assoc();

// collected_today is true when the collector marked this pickup as done today.
$stmt = $conn->prepare(
    "SELECT cs.collection_schedule_id, cs.day_of_week, cs.start_time, cs.end_time,
            wc.category_name,
            (pl.pickup_log_id IS NOT NULL) AS collected_today
     FROM collection_schedule cs
     LEFT JOIN waste_category wc ON wc.waste_category_id = cs.waste_category_id
     LEFT JOIN pickup_log pl
            ON pl.collection_schedule_id = cs.collection_schedule_id
           AND pl.pickup_date = CURDATE()
     WHERE cs.route_id = ? AND cs.schedule_status = 'Active'
     ORDER BY FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
              cs.start_time"
);
$stmt->bind_param('i', $routeId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($rows as &$r) {
    $r['collected_today'] = (bool) (int) $r['collected_today'];
}
unset($r);

json_out(['zone' => $zone, 'schedules' => $rows]);
