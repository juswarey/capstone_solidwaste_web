<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

$token = bearer_token();
if ($token !== null) {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('DELETE FROM api_token WHERE token_hash = ?');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
}
json_out(['ok' => true]);
