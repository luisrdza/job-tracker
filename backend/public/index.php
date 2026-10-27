<?php

declare(strict_types=1);
use App\Infrastructure\Env;
use App\Domain\JobPosting;
use App\Domain\JobStatus;
use App\Infrastructure\PostgresJobRepository;

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

if ($method === 'POST' && $uri === '/api/v1/jobs') {
    $input = file_get_contents("php://input");
    try {
        $pdo = new \PDO(
            "pgsql:host={$env->get('DB_HOST')};port={$env->get('DB_PORT')};dbname={$env->get('DB_DATABASE')}",
            $env->get('DB_USERNAME'),
            $env->get('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );
        $json_array = json_decode(
            json: $input,
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );

        if (!is_array($json_array)) {
            throw new \InvalidArgumentException('Invalid JSON payload. Expected a JSON object.');
        }

        if (!array_key_exists('title', $json_array) || !is_string($json_array['title']) || trim($json_array['title']) === '') {
            throw new \InvalidArgumentException('The title is required.');
        }

        if (!array_key_exists('company', $json_array) || !is_string($json_array['company']) || trim($json_array['company']) === '') {
            throw new \InvalidArgumentException('The company is required.');
        }

        if (!array_key_exists('status', $json_array) || !is_string($json_array['status']) || trim($json_array['status']) === '') {
            throw new \InvalidArgumentException('Status must be a string value.');
        }

        if (!array_key_exists('salary_max', $json_array) || !filter_var($json_array['salary_max'], FILTER_VALIDATE_INT) || $json_array['salary_max'] <= 0) {
            throw new \InvalidArgumentException('Salary must be a integer value.');
        }

        $status = JobStatus::tryFrom($json_array['status']);
        if ($status === null) {
            throw new \InvalidArgumentException('Invalid status value.');
        }

        $createdAt = new \DateTimeImmutable();

        $request = new JobPosting(
            JobPosting::generateUuidV4(),
            $json_array['title'],
            $json_array['company'],
            JobStatus::from($json_array['status']),
            (int) $json_array['salary_max'],
            $createdAt
        );

        $repository = new PostgresJobRepository($pdo);

        $repository->save($request);

        http_response_code(201);
        echo json_encode([
            "id" => $request->id,
            "title" => $request->title,
            "company" => $request->company,
            "status" => $request->status->value,
            "salary_max" => $request->salaryMax,
            "created_at" => $request->createdAt->format(\DateTimeInterface::ATOM)
        ]);
        exit;
    } catch (\InvalidArgumentException $e) {
        http_response_code(422);
        error_log("POST /api/v1/jobs failed: " . $e->getMessage());
        echo json_encode([
            "code" => 422,
            "message" => "Invalid input data."
        ]);
        exit;
    } catch (\JsonException $e) {
        http_response_code(422);
        error_log("POST /api/v1/jobs failed: " . $e->getMessage());
        echo json_encode([
            "code" => 422,
            "message" => "Malformed JSON input."
        ]);
        exit;
    } catch (\PDOException $e) {
        http_response_code(503);
        error_log("POST /api/v1/jobs failed: " . $e->getMessage());
        echo json_encode([
            "code" => 503,
            "message" => "DB disconnected."
        ]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        error_log("CRITICAL ERROR: " . $e->getMessage());
        echo json_encode([
            "code" => 500,
            "message" => "Internal Server Error."
        ]);
        exit;
    }
}

// Ruta no encontrada
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
