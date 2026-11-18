<?php
declare(strict_types=1);

function forge_api_cors(array $methods = ['GET'], bool $credentials = false): void
{
    header('Vary: Origin', false);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $ownOrigin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $allowed = ['https://review.365dtm.com'];
    $config = __DIR__ . '/cors-config.php';
    if (is_file($config)) {
        $allowed = require $config;
        if (!is_array($allowed)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            exit('{"error":"API origin configuration is invalid."}');
        }
    }
    if ($origin !== '' && $origin !== $ownOrigin && !in_array($origin, $allowed, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        exit('{"error":"This website origin is not allowed."}');
    }
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        if ($credentials) {
            header('Access-Control-Allow-Credentials: true');
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
        return;
    }
    $requestedMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? 'GET';
    $requestedHeaders = strtolower($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '');
    $headers = array_filter(array_map('trim', explode(',', $requestedHeaders)));
    if (!in_array($requestedMethod, $methods, true) || array_diff($headers, ['content-type'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        exit('{"error":"This cross-origin request is not allowed."}');
    }
    header('Access-Control-Allow-Methods: ' . implode(', ', array_merge($methods, ['OPTIONS'])));
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    http_response_code(204);
    exit;
}
