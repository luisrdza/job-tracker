<?php

declare(strict_types=1);
use App\Infrastructure\Env;
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

try {
    $env = new Env();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Internal Server Error: Environment configuration failed."
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($method === 'GET' && $uri === '/api/v1/health') {
    try {
        $pdo = new \PDO(
            "pgsql:host={$env->get('DB_HOST')};port={$env->get('DB_PORT')};dbname={$env->get('DB_DATABASE')}",
            $env->get('DB_USERNAME'),
            $env->get('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );
        $pdo->query('SELECT 1');
    } catch (\PDOException $e) {
        http_response_code(503);
        echo json_encode([
            "status" => "failed",
            "timestamp" => date(DATE_ATOM),
            "version" => "1.0.0",
            "database" => "disconnected"
        ]);
        exit;
    }
    http_response_code(200);
    echo json_encode([
        "status" => "ok",
        "timestamp" => date(DATE_ATOM),
        "version" => "1.0.0",
        "database" => "connected"
    ]);
    exit;
}

// Ruta no encontrada
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
