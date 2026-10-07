<?php
// Who is logged in? Used by the app on start-up to pick the right screens.
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$who = auth_any($conn);

if ($who['role'] === 'resident') {
    $sql = 'SELECT first_name, last_name, email FROM resident WHERE resident_id = ? LIMIT 1';
} else {
    $sql = 'SELECT first_name, last_name, email FROM garbage_collector WHERE garbage_collector_id = ? LIMIT 1';
}
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $who['id']);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

json_out([
    'role'      => $who['role'],
    'name'      => $u['first_name'] ?? '',
    'full_name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
    'email'     => $u['email'] ?? '',
]);
