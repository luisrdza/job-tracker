<?php

declare(strict_types=1);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'GET' && $uri === '/api/v1/health') {
    http_response_code(200);
    $timestampString = date('Y-m-d H:i:s');
    echo json_encode([
        "status" => "ok",
        "timestamp" => date(DATE_ATOM),
        "version" => "1.0.0"
    ]);
    exit;
}

// Ruta no encontrada
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
