<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Env;
use App\Infrastructure\PostgresJobRepository;
use App\Application\Console\JobSeeder;

try {
    $env = new Env();
} catch (\Throwable $e) {
    echo 'Error with the environment configuration: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}


try {
    $host = $env->get('DB_HOST');
    $port = $env->get('DB_PORT');
    $db = $env->get('DB_DATABASE');
    $dbUser = $env->get('DB_USERNAME');
    $dbPass = $env->get('DB_PASSWORD');

    $pdo = new \PDO(
        "pgsql:host={$host};port={$port};dbname={$db}",
        $dbUser,
        $dbPass,
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]
    );

    $repository = new PostgresJobRepository($pdo);
    $seeder = new JobSeeder($repository);
    $insertedCount = $seeder->run();
    $skippedCount = $seeder->getTotalCount() - $insertedCount;
    echo 'Inserted jobs: ' . $insertedCount . '; ignored by idempotency: ' . $skippedCount . PHP_EOL;
    exit(0);
} catch (\PDOException $e) {
    echo 'PDOError: ' . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (\Throwable $e) {
    echo 'Error executing JobSeeder: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
