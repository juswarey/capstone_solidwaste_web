<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

$in        = body();
$first     = clean_str($in['first_name'] ?? '', 50);
$last      = clean_str($in['last_name'] ?? '', 50);
$email     = clean_str($in['email'] ?? '', 100);
$pass      = (string) ($in['password'] ?? '');
$contact   = clean_str($in['contact_number'] ?? '', 20);
$address   = clean_str($in['home_address'] ?? '', 500);
$routeId   = isset($in['route_id']) && $in['route_id'] !== '' ? (int) $in['route_id'] : null;

if ($first === '' || $last === '') {
    json_out(['error' => 'Enter your first and last name.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['error' => 'Enter a valid email address.'], 422);
}
if (strlen($pass) < 8) {
    json_out(['error' => 'Password must be at least 8 characters.'], 422);
}
if ($contact !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $contact)) {
    json_out(['error' => 'Enter a valid contact number.'], 422);
}
if ($routeId !== null && !route_exists($conn, $routeId)) {
    json_out(['error' => 'Choose a valid zone.'], 422);
}

$chk = $conn->prepare('SELECT 1 FROM resident WHERE email = ? LIMIT 1');
$chk->bind_param('s', $email);
$chk->execute();
if ($chk->get_result()->fetch_row()) {
    json_out(['error' => 'That email is already registered. Try logging in.'], 409);
}

$hash    = password_hash($pass, PASSWORD_DEFAULT);
$contact = $contact === '' ? null : $contact;
$address = $address === '' ? null : $address;

$stmt = $conn->prepare(
    'INSERT INTO resident (email, password, first_name, last_name, contact_number, home_address, route_id)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('ssssssi', $email, $hash, $first, $last, $contact, $address, $routeId);
$stmt->execute();
$residentId = (int) $conn->insert_id;

json_out([
    'token' => issue_token($conn, 'resident', $residentId),
    'role'  => 'resident',
    'name'  => $first,
], 201);
