<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo '{"error":"Method not allowed."}';
    exit;
}

try {
    require_once __DIR__ . '/../includes/installer.php';
    require_once __DIR__ . '/store.php';
    $db = eventforge_bootstrap_connection();
    if (!$db) {
        throw new RuntimeException('Event Forge database is unavailable.');
    }
    $body = json_encode(['businesses' => business_list($db, true)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    echo $body;
} catch (Throwable $e) {
    error_log('Business API: ' . $e->getMessage());
    http_response_code(503);
    echo '{"error":"Business directory unavailable."}';
}
