<?php
// One login for both roles: tries resident accounts first, then garbage collectors.
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

$in    = body();
$email = clean_str($in['email'] ?? '', 100);
$pass  = (string) ($in['password'] ?? '');

if ($email === '' || $pass === '') {
    json_out(['error' => 'Enter your email and password.'], 422);
}

$accounts = [
    ['role' => 'resident',  'table' => 'resident',          'id' => 'resident_id'],
    ['role' => 'collector', 'table' => 'garbage_collector', 'id' => 'garbage_collector_id'],
];

foreach ($accounts as $a) {
    $stmt = $conn->prepare(
        "SELECT {$a['id']} AS id, password, first_name FROM {$a['table']} WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        continue;
    }

    if (verify_password_upgrade($conn, $a['table'], $a['id'], (int) $row['id'], (string) $row['password'], $pass)) {
        json_out([
            'token' => issue_token($conn, $a['role'], (int) $row['id']),
            'role'  => $a['role'],
            'name'  => $row['first_name'],
        ]);
    }
}

json_out(['error' => 'Incorrect email or password.'], 401);
