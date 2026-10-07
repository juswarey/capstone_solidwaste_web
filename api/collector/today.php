<?php
require_once __DIR__ . '/../_bootstrap.php';
require_method('GET');
$cid = auth_collector($conn);

$stmt = $conn->prepare('SELECT first_name FROM garbage_collector WHERE garbage_collector_id = ?');
$stmt->bind_param('i', $cid);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

$day = $conn->query('SELECT CURDATE() AS today, DAYNAME(CURDATE()) AS day_name')->fetch_assoc();

$stmt = $conn->prepare(
    "SELECT cs.collection_schedule_id, cs.start_time, cs.end_time,
            wc.category_name, r.zone_name,
            (pl.pickup_log_id IS NOT NULL) AS done
     FROM collection_schedule cs
     LEFT JOIN waste_category wc ON wc.waste_category_id = cs.waste_category_id
     LEFT JOIN route r ON r.route_id = cs.route_id
     LEFT JOIN pickup_log pl
            ON pl.collection_schedule_id = cs.collection_schedule_id
           AND pl.pickup_date = CURDATE()
     WHERE cs.garbage_collector_id = ?
       AND cs.schedule_status = 'Active'
       AND cs.day_of_week = DAYNAME(CURDATE())
     ORDER BY cs.start_time"
);
$stmt->bind_param('i', $cid);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($rows as &$r) {
    $r['done'] = (bool) (int) $r['done'];
}
unset($r);

json_out([
    'name'     => $me['first_name'] ?? '',
    'date'     => $day['today'],
    'day_name' => $day['day_name'],
    'pickups'  => $rows,
]);
