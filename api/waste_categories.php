<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');
auth_any($conn);   // residents and collectors both use the waste guide

$res = $conn->query(
    'SELECT waste_category_id, category_name, disposal_guidelines
     FROM waste_category
     ORDER BY category_name'
);
json_out($res->fetch_all(MYSQLI_ASSOC));
