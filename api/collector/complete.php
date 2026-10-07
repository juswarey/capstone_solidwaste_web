<?php
// POST { collection_schedule_id, done: true|false }
// Marks one of TODAY's pickups as collected (or undoes it).
require_once __DIR__ . '/../_bootstrap.php';
require_method('POST');
$cid = auth_collector($conn);

$in   = body();
$id   = (int) ($in['collection_schedule_id'] ?? 0);
$done = array_key_exists('done', $in) ? (bool) $in['done'] : true;

$stmt = $conn->prepare(
    "SELECT 1 FROM collection_schedule
     WHERE collection_schedule_id = ? AND garbage_collector_id = ?
       AND schedule_status = 'Active' AND day_of_week = DAYNAME(CURDATE())
     LIMIT 1"
);
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
if (!$stmt->get_result()->fetch_row()) {
    json_out(['error' => 'That pickup isn’t assigned to you today.'], 422);
}

if ($done) {
    $stmt = $conn->prepare(
        'INSERT INTO pickup_log (collection_schedule_id, garbage_collector_id, pickup_date)
         VALUES (?, ?, CURDATE())
         ON DUPLICATE KEY UPDATE completed_at = NOW()'
    );
    $stmt->bind_param('ii', $id, $cid);
} else {
    $stmt = $conn->prepare(
        'DELETE FROM pickup_log
         WHERE collection_schedule_id = ? AND garbage_collector_id = ? AND pickup_date = CURDATE()'
    );
    $stmt->bind_param('ii', $id, $cid);
}
$stmt->execute();

json_out(['ok' => true, 'done' => $done]);
