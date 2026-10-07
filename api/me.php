<?php
require_once __DIR__ . '/_bootstrap.php';
$rid = auth_resident($conn);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in      = body();
    $first   = clean_str($in['first_name'] ?? '', 50);
    $last    = clean_str($in['last_name'] ?? '', 50);
    $contact = clean_str($in['contact_number'] ?? '', 20);
    $address = clean_str($in['home_address'] ?? '', 500);
    $routeId = isset($in['route_id']) && $in['route_id'] !== '' ? (int) $in['route_id'] : null;

    if ($first === '' || $last === '') {
        json_out(['error' => 'Enter your first and last name.'], 422);
    }
    if ($contact !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $contact)) {
        json_out(['error' => 'Enter a valid contact number.'], 422);
    }
    if ($routeId !== null && !route_exists($conn, $routeId)) {
        json_out(['error' => 'Choose a valid zone.'], 422);
    }
    $contact = $contact === '' ? null : $contact;
    $address = $address === '' ? null : $address;

    $stmt = $conn->prepare(
        'UPDATE resident
         SET first_name = ?, last_name = ?, contact_number = ?, home_address = ?, route_id = ?
         WHERE resident_id = ?'
    );
    $stmt->bind_param('ssssii', $first, $last, $contact, $address, $routeId, $rid);
    $stmt->execute();
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_out(['error' => 'Method not allowed.'], 405);
}

$stmt = $conn->prepare(
    'SELECT r.resident_id, r.email, r.first_name, r.last_name, r.contact_number,
            r.home_address, r.route_id, rt.zone_name
     FROM resident r
     LEFT JOIN route rt ON rt.route_id = r.route_id
     WHERE r.resident_id = ? LIMIT 1'
);
$stmt->bind_param('i', $rid);
$stmt->execute();
json_out($stmt->get_result()->fetch_assoc());
