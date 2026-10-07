<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');
auth_any($conn);

$res = $conn->query(
    'SELECT announcement_id, title, content, published_timestamp, expiry_date
     FROM announcement
     WHERE expiry_date IS NULL OR expiry_date >= CURDATE()
     ORDER BY published_timestamp DESC
     LIMIT 50'
);
json_out($res->fetch_all(MYSQLI_ASSOC));
