<?php
/**
 * Shared helpers for the mobile JSON API (residents + garbage collectors).
 * Location: <project>/api/_bootstrap.php
 *
 * Every endpoint starts with:  require_once __DIR__ . '/_bootstrap.php';
 * (files inside api/collector/ use '/../_bootstrap.php')
 */

require_once __DIR__ . '/../db.php';   // gives us $conn (mysqli)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Any uncaught error becomes a clean JSON 500 instead of an HTML page.
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again.']);
    // For debugging on localhost, temporarily uncomment:
    // error_log($e->getMessage());
    exit;
});

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_out(['error' => 'Method not allowed.'], 405);
    }
}

/** Decoded JSON request body (empty array if none/invalid). */
function body(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

/**
 * Checks a password. Properly hashed passwords use password_verify().
 * Old plain-text passwords are accepted once and upgraded to a hash.
 */
function verify_password_upgrade(
    mysqli $conn, string $table, string $idCol, int $id, string $stored, string $input
): bool {
    if (password_get_info($stored)['algo'] !== null) {
        return password_verify($input, $stored);
    }
    if (!hash_equals($stored, $input)) {
        return false;
    }
    $allowed = ['resident' => 'resident_id', 'garbage_collector' => 'garbage_collector_id'];
    if (($allowed[$table] ?? null) === $idCol) {
        $newHash = password_hash($input, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE {$table} SET password = ? WHERE {$idCol} = ?");
        $up->bind_param('si', $newHash, $id);
        $up->execute();
    }
    return true;
}

/** $role is 'resident' or 'collector'. */
function issue_token(mysqli $conn, string $role, int $userId): string
{
    $conn->query('DELETE FROM api_token WHERE expires_at < NOW()');

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    $resident  = $role === 'resident'  ? $userId : null;
    $collector = $role === 'collector' ? $userId : null;

    $stmt = $conn->prepare(
        'INSERT INTO api_token (token_hash, resident_id, collector_id, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    );
    $stmt->bind_param('sii', $hash, $resident, $collector);
    $stmt->execute();

    return $token;
}

function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/** Finds the logged-in account for this request, or stops with 401. */
function auth_token_row(mysqli $conn): array
{
    $token = bearer_token();
    if ($token === null) {
        json_out(['error' => 'Please log in again.'], 401);
    }

    $hash = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT resident_id, collector_id FROM api_token
         WHERE token_hash = ? AND expires_at > NOW() LIMIT 1'
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        json_out(['error' => 'Your session expired. Please log in again.'], 401);
    }
    return $row;
}

/** Returns the logged-in resident_id, or stops (401 / 403). */
function auth_resident(mysqli $conn): int
{
    $row = auth_token_row($conn);
    if ($row['resident_id'] === null) {
        json_out(['error' => 'This part of the app is for residents only.'], 403);
    }
    return (int) $row['resident_id'];
}

/** Returns the logged-in garbage_collector_id, or stops (401 / 403). */
function auth_collector(mysqli $conn): int
{
    $row = auth_token_row($conn);
    if ($row['collector_id'] === null) {
        json_out(['error' => 'This part of the app is for garbage collectors only.'], 403);
    }
    return (int) $row['collector_id'];
}

/** For endpoints both roles may use. Returns ['role' => ..., 'id' => ...]. */
function auth_any(mysqli $conn): array
{
    $row = auth_token_row($conn);
    if ($row['resident_id'] !== null) {
        return ['role' => 'resident', 'id' => (int) $row['resident_id']];
    }
    return ['role' => 'collector', 'id' => (int) $row['collector_id']];
}

/** The resident's zone (route_id) or null if none chosen yet. */
function resident_route_id(mysqli $conn, int $residentId): ?int
{
    $stmt = $conn->prepare('SELECT route_id FROM resident WHERE resident_id = ? LIMIT 1');
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ($row && $row['route_id'] !== null) ? (int) $row['route_id'] : null;
}

function route_exists(mysqli $conn, int $routeId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM route WHERE route_id = ? LIMIT 1');
    $stmt->bind_param('i', $routeId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
}

/**
 * Converts the admin-entered path text "8.2280,124.2452; 8.2295,124.2470"
 * into [{latitude, longitude}, ...] for the map.
 */
function parse_path(?string $raw): array
{
    $points = [];
    foreach (explode(';', (string) $raw) as $pair) {
        $parts = array_map('trim', explode(',', $pair));
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $lat = (float) $parts[0];
            $lng = (float) $parts[1];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                $points[] = ['latitude' => $lat, 'longitude' => $lng];
            }
        }
    }
    return $points;
}

function clean_str($value, int $maxLen): string
{
    $s = trim((string) $value);
    return mb_substr($s, 0, $maxLen);
}
