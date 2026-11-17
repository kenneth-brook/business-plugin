<?php
declare(strict_types=1);

$file = $_GET['file'] ?? '';
if (!is_string($file) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._ -]*\.(jpg|jpeg|png|webp|gif|mp3|wav|ogg)\z/i', $file)) {
    http_response_code(404);
    exit;
}
$path = __DIR__ . '/storage/uploads/' . $file;
if (!is_file($path) || is_link($path)) {
    http_response_code(404);
    exit;
}
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'webp' => 'image/webp', 'gif' => 'image/gif', 'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];
header('Content-Type: ' . $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))]);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
readfile($path);
