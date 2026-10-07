<?php
// Public: lets the Register screen show the zone picker before login.
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$res = $conn->query(
    "SELECT route_id, zone_name, collection_days
     FROM route
     WHERE zone_name IS NOT NULL AND zone_name <> ''
     ORDER BY zone_name"
);
json_out($res->fetch_all(MYSQLI_ASSOC));
