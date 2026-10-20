<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Env;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Env::class)]
class EnvTest extends TestCase
{
    public function testValidArguments(): void
    {
        $args = [
            'DB_HOST' => 'localhost',
            'DB_PORT' => 5432,
            'DB_DATABASE' => 'test',
            'DB_USERNAME' => 'user',
            'DB_PASSWORD' => 'pass'
        ];

        $env = new Env($args);
        $this->assertIsObject($env);
        $this->assertSame('localhost', $env->get('DB_HOST'));
        $this->assertSame('user', $env->get('DB_USERNAME'));
    }

    public function testInvalidArguments(): void
    {
        $args = [
            'DB_HOST' => 'localhost',
            'DB_PORT' => 5432,
            'DB_DATABASE' => 'test',
            'DB_USERNAME' => 'user',
            'DB_PASSWORD' => null // throws an error, password must not be null or empty
        ];
        $this->expectException(\RuntimeException::class);
        new Env($args);
    }
}