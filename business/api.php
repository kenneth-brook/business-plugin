<?php
declare(strict_types=1);

require_once __DIR__ . '/cors.php';
forge_api_cors(['GET']);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo '{"error":"Method not allowed."}';
    exit;
}

$id = 0;
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        http_response_code(400);
        echo '{"error":"Invalid business ID."}';
        exit;
    }
}

try {
    require_once __DIR__ . '/../includes/installer.php';
    require_once __DIR__ . '/store.php';
    $db = eventforge_bootstrap_connection();
    if (!$db) {
        throw new RuntimeException('Event Forge database is unavailable.');
    }
    $payload = [];
    if ($id > 0) {
        $business = business_find_public($db, $id);
        if ($business === null) {
            http_response_code(404);
            echo '{"error":"Business not found."}';
            exit;
        }
        $payload = ['business' => $business];
    } else {
        $payload = ['businesses' => business_list($db, true)];
    }
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    echo $body;
} catch (Throwable $e) {
    error_log('Business API: ' . $e->getMessage());
    http_response_code(503);
    echo '{"error":"Business directory unavailable."}';
}
