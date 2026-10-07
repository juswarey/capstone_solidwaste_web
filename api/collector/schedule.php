<?php
require_once __DIR__ . '/../_bootstrap.php';
require_method('GET');
$cid = auth_collector($conn);

$stmt = $conn->prepare(
    "SELECT cs.collection_schedule_id, cs.day_of_week, cs.start_time, cs.end_time,
            wc.category_name, r.zone_name
     FROM collection_schedule cs
     LEFT JOIN waste_category wc ON wc.waste_category_id = cs.waste_category_id
     LEFT JOIN route r ON r.route_id = cs.route_id
     WHERE cs.garbage_collector_id = ? AND cs.schedule_status = 'Active'
     ORDER BY FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
              cs.start_time"
);
$stmt->bind_param('i', $cid);
$stmt->execute();
json_out($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
