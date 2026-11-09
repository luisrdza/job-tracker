<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Env::class)]
class EnvTest extends TestCase
{
    public static function invalidArgumentsProvider(): iterable
    {
        return [
            'db_host missing' => [
                [
                    'DB_HOST' => null,
                    'DB_PORT' => 5432,
                    'DB_DATABASE' => 'test',
                    'DB_USERNAME' => 'user',
                    'DB_PASSWORD' => 'pass',
                    'TOKEN_WRITE' => 'user_can_write',
                    'TOKEN_READ' => 'user_only_reads'
                ]
            ],
            'db_port missing' => [
                [
                    'DB_HOST' => 'localhost',
                    'DB_PORT' => null,
                    'DB_DATABASE' => 'test',
                    'DB_USERNAME' => 'user',
                    'DB_PASSWORD' => 'pass',
                    'TOKEN_WRITE' => 'user_can_write',
                    'TOKEN_READ' => 'user_only_reads'
                ]
            ],
            'db_database missing' => [
                [
                    'DB_HOST' => 'localhost',
                    'DB_PORT' => 5432,
                    'DB_DATABASE' => null,
                    'DB_USERNAME' => 'user',
                    'DB_PASSWORD' => 'pass',
                    'TOKEN_WRITE' => 'user_can_write',
                    'TOKEN_READ' => 'user_only_reads'
                ]
            ],
            'db_username missing' => [
                [
                    'DB_HOST' => 'localhost',
                    'DB_PORT' => 5432,
                    'DB_DATABASE' => 'test',
                    'DB_USERNAME' => null,
                    'DB_PASSWORD' => 'pass',
                    'TOKEN_WRITE' => 'user_can_write',
                    'TOKEN_READ' => 'user_only_reads'
                ]
            ],
            'db_password missing' => [
                [
                    'DB_HOST' => 'localhost',
                    'DB_PORT' => 5432,
                    'DB_DATABASE' => 'test',
                    'DB_USERNAME' => 'user',
                    'DB_PASSWORD' => null,
                    'TOKEN_WRITE' => 'user_can_write',
                    'TOKEN_READ' => 'user_only_reads'
                ]
            ],
            'token_hash_write missing' => [
                [
                    'DB_HOST' => 'localhost',
                    'DB_PORT' => 5432,
                    'DB_DATABASE' => 'test',
                    'DB_USERNAME' => 'user',
                    'DB_PASSWORD' => 'pass',
                    'TOKEN_WRITE' => null,
                    'TOKEN_READ' => 'user_only_reads'
                ]
            ],
            'token_hash_read missing' => [
                [
                    'DB_HOST' => 'localhost',
                    'DB_PORT' => 5432,
                    'DB_DATABASE' => 'test',
                    'DB_USERNAME' => 'user',
                    'DB_PASSWORD' => 'pass',
                    'TOKEN_WRITE' => 'user_can_write',
                    'TOKEN_READ' => null
                ]
            ]
        ];
    }

    public function testValidArguments(): void
    {
        $args = [
            'DB_HOST' => 'localhost',
            'DB_PORT' => 5432,
            'DB_DATABASE' => 'test',
            'DB_USERNAME' => 'user',
            'DB_PASSWORD' => 'pass',
            'TOKEN_WRITE' => 'user_can_write',
            'TOKEN_READ' => 'user_only_reads'
        ];

        $env = new Env($args);
        $this->assertIsObject($env);
        $this->assertSame('localhost', $env->get('DB_HOST'));
        $this->assertSame('user', $env->get('DB_USERNAME'));
    }

    #[DataProvider('invalidArgumentsProvider')]
    public function testInvalidArguments(array $args): void
    {
        $this->expectException(\RuntimeException::class);
        new Env($args);
    }
}