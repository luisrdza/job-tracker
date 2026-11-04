<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Infrastructure\Security\TokenSecurityService;

#[CoversClass(TokenSecurityService::class)]
class TokenSecurityServiceTest extends TestCase
{
    private $securityService;
    public function setUp(): void
    {
        $this->securityService = new TokenSecurityService();
    }
    public static function invalidVerifySecretProvider(): iterable
    {
        $validHash = password_hash('secret', PASSWORD_ARGON2ID, [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS
        ]);
        return [
            'invalid secret' => ['simplePlainText', $validHash],
            'empty plain text' => ['', $validHash],
            'empty stored secret' => ['secret', ''],
            'empty entries' => ['', '']
        ];
    }

    public static function verifyTokenCasesProvider(): iterable
    {
        $token = password_hash('secret', PASSWORD_ARGON2ID);
        $userToken = password_hash('secrettwo', PASSWORD_ARGON2ID);
        return [
            'same tokens' => [$token, $token, true],
            'different tokens' => [$token, $userToken, false],
            'empty strings' => ['', '', false],
            'only spaces' => ['  ', ' ', false]
        ];
    }

    #[DataProvider('invalidVerifySecretProvider')]
    public function testVerifySecretFailedWithInvalidArguments($secretOne, $storedSecret): void
    {
        $actualResult = $this->securityService->verifySecret($secretOne, $storedSecret);
        $this->assertSame(false, $actualResult);
    }

    #[DataProvider('verifyTokenCasesProvider')]
    public function testVerifyTokenScenarios(string $token, string $userToken, bool $expectedResult): void
    {
        $actualResult = $this->securityService->verifyToken($token, $userToken);
        $this->assertSame($expectedResult, $actualResult);
    }

    public function testGenerateTokenSuccess(): void
    {
        $secret = 'secret';
        $validHash = $this->securityService->generateToken($secret);

        $actualResult = $this->securityService->verifySecret($secret, $validHash);
        $this->assertSame(true, $actualResult);
    }

    public function testGenerateTokenFailWithEmptyString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->securityService->generateToken('');
    }
}