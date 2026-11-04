<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

class TokenSecurityService
{
    public function generateToken(string $secret): string
    {
        if (trim($secret) === '') {
            throw new \RuntimeException('Secret key cannot be empty');
        }

        return password_hash($secret, PASSWORD_ARGON2ID, [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
        ]);
    }

    public function verifySecret(string $plainText, string $storedSecret): bool
    {
        return password_verify($plainText, $storedSecret);
    }

    public function verifyToken(string $token, string $userToken): bool
    {
        if (trim($token) === '' || trim($userToken) === '') {
            return false;
        }
        return hash_equals($token, $userToken);
    }
}